<?php
/**
 * update_club.php — Mise a jour de TOUS les athletes d'un club depuis la FFA.
 *
 * Reserve ADMIN (bkUserCanPurge). Traitement par LOTS en arriere-plan (AJAX) :
 *   1. ?api=roster&club=NOM  -> liste des athletes du club (ids externes)
 *   2. ?api=batch&ids=a,b,c  -> scrape + ecrit chaque athlete du lot
 *        - skip ceux deja mis a jour < 7 jours (athlete_scrape_log) si skip=1
 *        - scrapeParallel (curl_multi) sur le lot, puis insertAthleteData
 *        - vide le cache de chaque profil traite
 *   Le client boucle lot par lot, affiche la progression, peut mettre en pause.
 *
 * Garde-fou athle.fr : lots de 5, petite pause cote client, skip 7j par defaut.
 */

@ini_set('display_errors', '1');
@error_reporting(E_ALL);
@set_time_limit(0);

// Requete API : sortie JSON blindee (aucun warning ne doit polluer le JSON)
$UC_IS_API = isset($_GET['api']) || isset($_POST['api']);
if ($UC_IS_API) {
    @ini_set('display_errors', '0');
    @ini_set('html_errors', '0');
    ob_start();
}

require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/athlete_purge.php'; // bkUserCanPurge()
require_once dirname(__DIR__) . '/Class/AthleteScraper.php';
require_once dirname(__DIR__) . '/core/insert_athle.php';
require_once dirname(__DIR__) . '/core/progressions_store.php';
require_once dirname(__DIR__) . '/scraping/scrape_functions.php';

// ---------------------------------------------------------------------------
// Acces : ADMIN UNIQUEMENT
// ---------------------------------------------------------------------------
$UC_IS_ADMIN = bkUserCanPurge($conn);
if (!$UC_IS_ADMIN) {
    if ($UC_IS_API) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Acces reserve a l\'administration.']);
        exit;
    }
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset="utf-8"><body style="background:#0d1117;color:#e6edf3;font-family:sans-serif;padding:40px;text-align:center;"><h1>403 — Acces reserve</h1><p>Ajoute ta cle : <code>?bk_key=…</code></p></body>';
    exit;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function uc_ensureScrapeLog(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $conn->query("CREATE TABLE IF NOT EXISTS athlete_scrape_log (
        athlete_id_ext INT UNSIGNED PRIMARY KEY,
        last_scraped_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_action ENUM('NEW','MAJ') DEFAULT 'MAJ',
        INDEX idx_last_scraped (last_scraped_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

// ---------------------------------------------------------------------------
// Sauvegarde d'etat (reprise a tout moment) : 1 fichier JSON par club
//   { club, ids:[...frozen order...], cursor, stats:{done,NEW,MAJ,SKIP,ERR}, updated }
// Le serveur est maitre du curseur -> fermer/rouvrir l'onglet ne perd rien.
// ---------------------------------------------------------------------------
function uc_stateDir(): string {
    $d = __DIR__ . '/state/uc_progress';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}
function uc_stateFile(string $key): string {
    $key = preg_replace('/[^a-f0-9]/', '', $key);   // md5 only -> safe path
    return uc_stateDir() . '/' . $key . '.json';
}
function uc_loadState(string $key): ?array {
    if ($key === '') return null;
    $f = uc_stateFile($key);
    if (!is_file($f)) return null;
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j) ? $j : null;
}
function uc_saveState(string $key, array $data): void {
    if ($key === '') return;
    $data['updated'] = date('Y-m-d H:i:s');
    @file_put_contents(uc_stateFile($key), json_encode($data), LOCK_EX);
}
function uc_clearState(string $key): void {
    if ($key === '') return;
    @unlink(uc_stateFile($key));
}
function uc_freshStats(): array {
    return ['done' => 0, 'NEW' => 0, 'MAJ' => 0, 'SKIP' => 0, 'ERR' => 0];
}

/** Scrape + ecrit un LOT d'ids (skip 7j optionnel). Retourne la liste des resultats. */
function uc_runBatch(mysqli $conn, array $ids, bool $skipRecent): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($x) => $x > 0)));
    if (empty($ids)) return [];
    $results = [];

    // 1) Filtrer les "recents" (skip 7j) sans toucher athle.fr
    $toScrape = $ids;
    if ($skipRecent) {
        $inList = implode(',', $ids);
        $rc = $conn->query("SELECT athlete_id_ext FROM athlete_scrape_log
                            WHERE athlete_id_ext IN ($inList)
                              AND last_scraped_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $recent = [];
        if ($rc) while ($x = $rc->fetch_assoc()) $recent[(int)$x['athlete_id_ext']] = true;
        $toScrape = [];
        foreach ($ids as $idx) {
            if (isset($recent[$idx])) $results[] = ['id' => $idx, 'action' => 'SKIP', 'recent' => true];
            else $toScrape[] = $idx;
        }
    }

    // 2) Scrape parallele du lot + ecriture
    if (!empty($toScrape)) {
        $pagesAll = scrapeParallel($toScrape);
        $cache = loadRefCache($conn);
        foreach ($toScrape as $idx) {
            $pages = $pagesAll[$idx] ?? [];
            $results[] = uc_processOne($conn, $idx, is_array($pages) ? $pages : [], $cache);
        }
    }
    return $results;
}

/** Incremente les compteurs cumules a partir d'une liste de resultats. */
function uc_tallyStats(array $stats, array $results): array {
    foreach ($results as $res) {
        $stats['done'] = ($stats['done'] ?? 0) + 1;
        $a = $res['action'] ?? 'ERR';
        if (!isset($stats[$a])) $stats[$a] = 0;
        $stats[$a]++;
    }
    return $stats;
}

/** Scrape + ecrit UN athlete (pages deja telechargees). Retourne le resultat. */
function uc_processOne(mysqli $conn, int $idExt, array $pages, array &$cache): array {
    if (empty($pages['bilans'])) {
        return ['id' => $idExt, 'action' => 'ERR', 'error' => 'fetch athle.fr'];
    }
    try {
        $rE = $conn->query("SELECT id_athlete FROM athletes WHERE athlete_id_externe = $idExt LIMIT 1");
        $wasExisting = ($rE && $rE->num_rows > 0);

        $s = new AthleteScraper($idExt);
        $s->html = $pages['bilans'];
        $s->extractIdentite();
        $s->extractMedailles();
        $s->extractProgressions();
        $s->extractClubs();
        $s->extractPodiums();
        $s->extractResultats();
        $s->extractNiveaux();
        if (!empty($pages['records']))    { $s->html = $pages['records'];    $s->extractRecords(); }
        if (!empty($pages['selections'])) { $s->html = $pages['selections']; $s->extractSelections(); }

        if (empty($s->identite['nom_complet'])) {
            return ['id' => $idExt, 'action' => 'ERR', 'error' => 'profil vide / parse'];
        }

        ob_start();
        insertAthleteData($s, $conn, $cache);
        ob_end_clean();

        // Log scrape (skip 7j)
        $action = $wasExisting ? 'MAJ' : 'NEW';
        $stmt = $conn->prepare("INSERT INTO athlete_scrape_log (athlete_id_ext, last_action)
                                VALUES (?, ?) ON DUPLICATE KEY UPDATE last_scraped_at = NOW(), last_action = VALUES(last_action)");
        if ($stmt) { $stmt->bind_param('is', $idExt, $action); $stmt->execute(); $stmt->close(); }

        // Vide le cache de ce profil (cle deterministe + filet regex)
        uc_clearAthleteCache($conn, $idExt);

        return [
            'id'     => $idExt,
            'action' => $action,
            'name'   => $s->identite['nom_complet'] ?? '',
            'counts' => [
                'records'      => count($s->records),
                'progressions' => count($s->progressions),
                'resultats'    => count($s->resultats),
                'podiums'      => count($s->podiums),
                'niveaux'      => count($s->niveaux),
            ],
        ];
    } catch (Throwable $e) {
        return ['id' => $idExt, 'action' => 'ERR', 'error' => $e->getMessage()];
    }
}

function uc_clearAthleteCache(mysqli $conn, int $idExt): void {
    $cacheDir = dirname(__DIR__) . '/cache';
    if (!is_dir($cacheDir)) return;
    $idInt = 0;
    $rI = $conn->query("SELECT id_athlete FROM athletes WHERE athlete_id_externe = $idExt LIMIT 1");
    if ($rI && $rI->num_rows) $idInt = (int)$rI->fetch_assoc()['id_athlete'];
    $keys = ['athlete_' . md5($idExt . '__')];
    if ($idInt) $keys[] = 'athlete_' . md5('_' . $idInt . '_');
    foreach ($keys as $k) { @unlink($cacheDir . '/' . $k . '.json'); }
}

// ---------------------------------------------------------------------------
// API
// ---------------------------------------------------------------------------
if ($UC_IS_API) {
    header('Content-Type: application/json; charset=utf-8');
    register_shutdown_function(function () {
        $out = '';
        while (ob_get_level() > 0) { $out = ob_get_clean() . $out; }
        $pos = strpos($out, '{');
        if ($pos !== false) { echo substr($out, $pos); return; }
        $err = error_get_last();
        echo json_encode(['ok' => false, 'error' => 'Erreur serveur' . ($err ? ': ' . $err['message'] : '')]);
    });

    $api = $_GET['api'] ?? $_POST['api'];

    // --- Roster : liste des athletes du club -----------------------------
    if ($api === 'roster') {
        $clubName = trim($_GET['club'] ?? $_POST['club'] ?? '');
        $clubId   = (int)($_GET['club_id'] ?? $_POST['club_id'] ?? 0);
        if ($clubName === '' && !$clubId) { echo json_encode(['ok' => false, 'error' => 'club manquant']); exit; }

        if ($clubId) {
            $where = "c.id_club = " . $clubId;
        } else {
            $esc = $conn->real_escape_string(rtrim($clubName, '* '));
            $where = "c.nom_club = '$esc'";
        }
        $sql = "SELECT DISTINCT a.athlete_id_externe AS id, a.nom_complet_athlete AS name, c.nom_club AS club
                FROM clubs c
                JOIN athlete_clubs ac ON ac.id_club = c.id_club
                JOIN athletes a ON a.id_athlete = ac.id_athlete
                WHERE $where AND a.athlete_id_externe > 0
                ORDER BY a.nom_complet_athlete";
        $r = $conn->query($sql);
        if (!$r) { echo json_encode(['ok' => false, 'error' => 'Requete club echouee']); exit; }
        $list = [];
        $clubReal = '';
        $freshMap = [];                       // id => name (ordre BDD)
        while ($row = $r->fetch_assoc()) {
            $id = (int)$row['id'];
            $list[] = ['id' => $id, 'name' => $row['name']];
            $freshMap[$id] = $row['name'];
            $clubReal = $row['club'];
        }

        // --- Etat de reprise -------------------------------------------------
        $stateKey = md5($clubId ? ('id:' . $clubId) : ('nom:' . mb_strtolower($clubReal ?: $clubName)));
        $force    = (($_GET['force'] ?? $_POST['force'] ?? '') === '1');
        $state    = $force ? null : uc_loadState($stateKey);
        $resume   = null;

        if ($state && !empty($state['ids'])) {
            // On REUTILISE l'ordre fige : le curseur garde son sens meme si la BDD a bouge.
            $ordered = [];
            foreach ($state['ids'] as $id) {
                $id = (int)$id;
                if (isset($freshMap[$id])) { $ordered[] = ['id' => $id, 'name' => $freshMap[$id]]; unset($freshMap[$id]); }
            }
            foreach ($freshMap as $id => $nm) $ordered[] = ['id' => (int)$id, 'name' => $nm]; // nouveaux licencies a la fin
            $list   = $ordered;
            $cursor = min((int)($state['cursor'] ?? 0), count($list));
            $resume = ['cursor' => $cursor, 'stats' => $state['stats'] ?? uc_freshStats(), 'updated' => $state['updated'] ?? null];
        } else {
            $cursor = 0;
        }

        // (Re)ecrit l'etat avec l'ordre fige courant
        uc_saveState($stateKey, [
            'club'   => $clubReal ?: $clubName,
            'ids'    => array_map(fn($a) => $a['id'], $list),
            'cursor' => $cursor,
            'stats'  => $resume ? $resume['stats'] : uc_freshStats(),
        ]);

        echo json_encode([
            'ok'        => true,
            'club'      => $clubReal ?: $clubName,
            'total'     => count($list),
            'athletes'  => $list,
            'state_key' => $stateKey,
            'resume'    => $resume,        // null si rien a reprendre
        ]);
        exit;
    }

    // --- Batch : traite le lot suivant -----------------------------------
    if ($api === 'batch') {
        uc_ensureScrapeLog($conn);
        $skipRecent = ($_POST['skip'] ?? $_GET['skip'] ?? '1') !== '0';
        $batchSize  = max(1, min(10, (int)($_POST['size'] ?? $_GET['size'] ?? 5)));
        $stateKey   = preg_replace('/[^a-f0-9]/', '', $_POST['state_key'] ?? $_GET['state_key'] ?? '');

        // Mode pilote par l'etat : le serveur lit le curseur, prend le lot suivant, avance, sauvegarde.
        if ($stateKey !== '') {
            $state = uc_loadState($stateKey);
            if (!$state || empty($state['ids'])) {
                echo json_encode(['ok' => false, 'error' => 'Etat introuvable — recharge le club.']); exit;
            }
            $allIds = array_map('intval', $state['ids']);
            $total  = count($allIds);
            $cursor = max(0, min((int)($state['cursor'] ?? 0), $total));
            $batch  = array_slice($allIds, $cursor, $batchSize);

            $results = uc_runBatch($conn, $batch, $skipRecent);

            $stats  = uc_tallyStats($state['stats'] ?? uc_freshStats(), $results);
            $cursor = min($cursor + count($batch), $total);
            $state['cursor'] = $cursor;
            $state['stats']  = $stats;
            uc_saveState($stateKey, $state);

            echo json_encode([
                'ok'      => true,
                'results' => $results,
                'cursor'  => $cursor,
                'total'   => $total,
                'stats'   => $stats,
                'done'    => $cursor >= $total,
            ]);
            exit;
        }

        // Mode legacy : ids explicites fournis par le client (sans sauvegarde d'etat).
        $idsRaw = $_POST['ids'] ?? $_GET['ids'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', $idsRaw)), fn($x) => $x > 0);
        if (empty($ids)) { echo json_encode(['ok' => true, 'results' => []]); exit; }
        echo json_encode(['ok' => true, 'results' => uc_runBatch($conn, $ids, $skipRecent)]);
        exit;
    }

    // --- Reset : efface la progression sauvegardee d'un club -------------
    if ($api === 'reset') {
        $stateKey = preg_replace('/[^a-f0-9]/', '', $_POST['state_key'] ?? $_GET['state_key'] ?? '');
        uc_clearState($stateKey);
        echo json_encode(['ok' => true]);
        exit;
    }

    // --- Autocomplete club : suggestions par debut de nom -----------------
    if ($api === 'clubs') {
        $q = trim($_GET['q'] ?? $_POST['q'] ?? '');
        if (mb_strlen($q) < 2) { echo json_encode(['ok' => true, 'clubs' => []]); exit; }
        $esc = $conn->real_escape_string($q);
        // On limite d'abord a 15 clubs (prefixe prioritaire) PUIS on compte -> rapide.
        $sql = "SELECT t.nom_club AS nom,
                       (SELECT COUNT(DISTINCT ac.id_athlete) FROM athlete_clubs ac WHERE ac.id_club = t.id_club) AS nb
                FROM (
                    SELECT id_club, nom_club FROM clubs
                    WHERE nom_club LIKE '%$esc%'
                    ORDER BY (nom_club LIKE '$esc%') DESC, CHAR_LENGTH(nom_club), nom_club
                    LIMIT 15
                ) t";
        $r = $conn->query($sql);
        $out = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $out[] = ['nom' => $row['nom'], 'nb' => (int)$row['nb']];
        }
        echo json_encode(['ok' => true, 'clubs' => $out]);
        exit;
    }

    // --- Clubs commencant par une lettre (parcours alphabetique) ----------
    if ($api === 'clubs_letter') {
        $l = mb_strtoupper(trim($_GET['l'] ?? $_POST['l'] ?? ''));
        if (!preg_match('/^[A-Z0-9]$/', $l)) { echo json_encode(['ok' => true, 'clubs' => []]); exit; }
        $esc = $conn->real_escape_string($l);
        $sql = "SELECT t.nom_club AS nom,
                       (SELECT COUNT(DISTINCT ac.id_athlete) FROM athlete_clubs ac WHERE ac.id_club = t.id_club) AS nb
                FROM (
                    SELECT id_club, nom_club FROM clubs
                    WHERE nom_club LIKE '$esc%'
                    ORDER BY nom_club
                    LIMIT 1500
                ) t";
        $r = $conn->query($sql);
        $out = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            if ((int)$row['nb'] === 0) continue; // on masque les clubs sans athlete
            $out[] = ['nom' => $row['nom'], 'nb' => (int)$row['nb']];
        }
        echo json_encode(['ok' => true, 'letter' => $l, 'total' => count($out), 'clubs' => $out]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'API inconnue']);
    exit;
}

// ---------------------------------------------------------------------------
// PAGE
// ---------------------------------------------------------------------------
$club = trim($_GET['club'] ?? '');
$bkKey = $_GET['bk_key'] ?? '';
$keyQs = $bkKey !== '' ? '&bk_key=' . urlencode($bkKey) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>MAJ club — FFA</title>
<style>
    :root { --bg:#0d1117; --card:#161b22; --border:#30363d; --text:#e6edf3; --muted:#8b949e;
            --green:#22c55e; --red:#ef4444; --blue:#3b82f6; --violet:#a78bfa; --orange:#f59e0b; }
    * { box-sizing:border-box; }
    body { background:var(--bg); color:var(--text); font-family:system-ui,Segoe UI,sans-serif; margin:0; padding:24px; }
    .wrap { max-width:960px; margin:0 auto; }
    h1 { font-size:22px; margin:0 0 4px; }
    .sub { color:var(--muted); font-size:14px; margin:0 0 18px; }
    .card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:18px; margin-bottom:16px; }
    .row { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    input[type=text] { background:#0d1117; border:1px solid var(--border); color:var(--text); padding:10px 12px; border-radius:8px; font-size:15px; flex:1; min-width:220px; }
    .ac-wrap { position:relative; flex:1; min-width:240px; }
    .ac-wrap input[type=text] { width:100%; }
    .ac-dropdown { position:absolute; left:0; right:0; top:calc(100% + 4px); background:#0d1117; border:1px solid var(--border); border-radius:8px; max-height:300px; overflow:auto; z-index:50; display:none; box-shadow:0 10px 28px rgba(0,0,0,.55); }
    .ac-dropdown.open { display:block; }
    .ac-item { padding:9px 12px; cursor:pointer; display:flex; justify-content:space-between; gap:12px; align-items:center; font-size:14px; border-bottom:1px solid #1c2330; }
    .ac-item:last-child { border-bottom:none; }
    .ac-item.active, .ac-item:hover { background:#1c2741; }
    .ac-item .ac-nb { color:var(--muted); font-size:12px; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .ac-item mark { background:transparent; color:var(--violet); font-weight:700; }
    .ac-empty { padding:9px 12px; color:var(--muted); font-size:13px; }
    .letters { display:flex; flex-wrap:wrap; gap:4px; }
    .letters button { background:#0d1117; border:1px solid var(--border); color:var(--text); padding:6px 0; width:34px; border-radius:6px; font-size:13px; font-weight:700; }
    .letters button:hover { background:#1c2741; }
    .letters button.active { background:linear-gradient(135deg,#6d28d9,#a78bfa); border-color:#a78bfa; }
    .club-select { width:100%; background:#0d1117; border:1px solid var(--border); color:var(--text); padding:10px 12px; border-radius:8px; font-size:14px; margin-top:10px; }
    label.chk { display:flex; align-items:center; gap:6px; font-size:14px; color:var(--muted); cursor:pointer; }
    button { border:none; border-radius:8px; padding:10px 18px; font-size:14px; font-weight:700; cursor:pointer; color:#fff; }
    .btn-load { background:linear-gradient(135deg,#6d28d9,#a78bfa); }
    .btn-go { background:linear-gradient(135deg,#15803d,#22c55e); }
    .btn-pause { background:#b45309; }
    .btn-stop { background:#7f1d1d; }
    button:disabled { opacity:.45; cursor:not-allowed; }
    .bar { height:14px; background:#0d1117; border:1px solid var(--border); border-radius:99px; overflow:hidden; margin:12px 0 8px; }
    .bar > div { height:100%; width:0; background:linear-gradient(90deg,#6d28d9,#22c55e); transition:width .3s; }
    .counters { display:flex; gap:14px; flex-wrap:wrap; font-size:14px; margin:8px 0; }
    .counters b { font-variant-numeric:tabular-nums; }
    .c-new{color:#79c0ff;} .c-maj{color:var(--violet);} .c-skip{color:var(--muted);} .c-err{color:#ff7b72;}
    .log { background:#0a0e14; border:1px solid var(--border); border-radius:8px; padding:10px; height:340px; overflow:auto; font-family:ui-monospace,Consolas,monospace; font-size:12.5px; line-height:1.5; }
    .log .l-new{color:#79c0ff;} .log .l-maj{color:var(--violet);} .log .l-skip{color:#6b7280;} .log .l-err{color:#ff7b72;}
    .log a { color:#58a6ff; text-decoration:none; }
    .muted { color:var(--muted); } .big { font-size:26px; font-weight:800; }
    .pill { display:inline-block; padding:2px 10px; border-radius:99px; font-size:12px; font-weight:700; background:#6d28d933; color:var(--violet); border:1px solid #6d28d9; }
    .warn { font-size:13px; color:var(--orange); margin-top:8px; }
</style>
</head>
<body>
<div class="wrap">
    <h1>&#127942; Mise à jour d'un club entier depuis la FFA</h1>
    <p class="sub">Admin uniquement. Scrape et met à jour <strong>tous les athlètes</strong> du club, par lots, en arrière-plan.
        &nbsp;·&nbsp; <a href="hub.php<?= $keyQs !== '' ? '?'.ltrim($keyQs,'&') : '' ?>" style="color:#a78bfa;font-weight:700;text-decoration:none;">&#128225; Hub central</a>
    </p>

    <div class="card">
        <div class="row">
            <div class="ac-wrap">
                <input type="text" id="clubInput" placeholder="Tape les 1res lettres du club…" value="<?= htmlspecialchars($club) ?>" autocomplete="off">
                <div id="clubAC" class="ac-dropdown"></div>
            </div>
            <button class="btn-load" id="btnLoad" onclick="loadRoster()">Charger le club</button>
        </div>
        <div style="margin-top:14px;">
            <div class="muted" style="font-size:13px;margin-bottom:6px;">Ou parcourir par lettre :</div>
            <div class="letters" id="lettersBar"></div>
            <select id="clubSelect" class="club-select" style="display:none;"></select>
        </div>
        <div class="row" style="margin-top:10px;">
            <label class="chk"><input type="checkbox" id="skip24" checked> Ignorer les athlètes déjà mis à jour il y a moins de 7 jours</label>
        </div>
        <p class="warn">⚠ Un gros club = beaucoup de requêtes vers athle.fr. Le traitement est volontairement par petits lots pour ne pas se faire bloquer.</p>
    </div>

    <div id="panel" style="display:none;">
        <div class="card">
            <div class="row" style="justify-content:space-between;">
                <div><span class="pill" id="clubName"></span> <span class="muted">— <b id="total">0</b> athlète(s)</span></div>
                <div class="row">
                    <button class="btn-go" id="btnStart" onclick="startRun()">▶ Démarrer</button>
                    <button class="btn-pause" id="btnPause" onclick="pauseRun()" style="display:none;">⏸ Pause</button>
                    <button class="btn-stop" id="btnReset" onclick="resetRun()" style="display:none;">↺ Recommencer</button>
                </div>
            </div>
            <div class="bar"><div id="barFill"></div></div>
            <div class="counters">
                <span>Traités : <b id="cDone">0</b> / <b id="cTotal">0</b></span>
                <span class="c-new">Nouveaux : <b id="cNew">0</b></span>
                <span class="c-maj">Mis à jour : <b id="cMaj">0</b></span>
                <span class="c-skip">Ignorés (7j) : <b id="cSkip">0</b></span>
                <span class="c-err">Erreurs : <b id="cErr">0</b></span>
                <span class="muted">⏱ <b id="elapsed">0s</b></span>
            </div>
        </div>
        <div class="card">
            <div class="log" id="log"></div>
        </div>
    </div>
</div>

<script>
var KEY_QS = <?= json_encode($keyQs) ?>;
var BATCH_SIZE = 5;      // athletes par lot (curl_multi = 15 requetes/lot)
var PAUSE_MS = 350;      // pause entre lots (gentil pour athle.fr)
var roster = [], nameMap = {}, cursor = 0, total = 0, stateKey = '', running = false, t0 = 0, timer = null;
var stats = { done:0, NEW:0, MAJ:0, SKIP:0, ERR:0 };

function $(id){ return document.getElementById(id); }
function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

// ===== Autocomplete club =====
var acTimer = null, acItems = [], acIdx = -1;
(function initAC(){
    var inp = $('clubInput'), box = $('clubAC');
    if (!inp || !box) return;
    inp.addEventListener('input', function(){
        clearTimeout(acTimer);
        var q = inp.value.trim();
        if (q.length < 2) { closeAC(); return; }
        acTimer = setTimeout(function(){ fetchAC(q); }, 220);
    });
    inp.addEventListener('keydown', function(e){
        if (!box.classList.contains('open')) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); moveAC(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); moveAC(-1); }
        else if (e.key === 'Enter') { if (acIdx >= 0) { e.preventDefault(); selectAC(acIdx); } }
        else if (e.key === 'Escape') { closeAC(); }
    });
    document.addEventListener('click', function(e){ if (!e.target.closest('.ac-wrap')) closeAC(); });
})();

function fetchAC(q){
    fetch('?api=clubs&q=' + encodeURIComponent(q) + KEY_QS)
        .then(function(r){ return r.json(); })
        .then(function(d){ if (!d.ok) { closeAC(); return; } renderAC(d.clubs || [], q); })
        .catch(function(){ closeAC(); });
}
function renderAC(list, q){
    var box = $('clubAC');
    acItems = list; acIdx = -1;
    if (!list.length) { box.innerHTML = '<div class="ac-empty">Aucun club ne correspond.</div>'; box.classList.add('open'); return; }
    var re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
    box.innerHTML = list.map(function(c, i){
        var nm = esc(c.nom).replace(re, '<mark>$1</mark>');
        return '<div class="ac-item" data-i="' + i + '" onmousedown="event.preventDefault()" onclick="selectAC(' + i + ')">'
             + '<span>' + nm + '</span><span class="ac-nb">' + c.nb + ' ath.</span></div>';
    }).join('');
    box.classList.add('open');
}
function moveAC(dir){
    var els = $('clubAC').querySelectorAll('.ac-item');
    if (!els.length) return;
    if (acIdx >= 0 && els[acIdx]) els[acIdx].classList.remove('active');
    acIdx = (acIdx + dir + els.length) % els.length;
    els[acIdx].classList.add('active');
    els[acIdx].scrollIntoView({ block: 'nearest' });
}
window.selectAC = function(i){
    var c = acItems[i]; if (!c) return;
    $('clubInput').value = c.nom;
    closeAC();
    loadRoster();   // charge directement le club choisi
};
function closeAC(){ var b = $('clubAC'); if (b){ b.classList.remove('open'); b.innerHTML = ''; } acIdx = -1; }

// ===== Parcours par lettre =====
(function initLetters(){
    var bar = $('lettersBar'); if (!bar) return;
    var letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'.split('');
    bar.innerHTML = letters.map(function(L){
        return '<button type="button" onclick="pickLetter(\'' + L + '\',this)">' + L + '</button>';
    }).join('');
    var sel = $('clubSelect');
    if (sel) sel.addEventListener('change', function(){ if (this.value) { $('clubInput').value = this.value; loadRoster(); } });
})();

window.pickLetter = function(L, btn){
    var bar = $('lettersBar');
    if (bar) bar.querySelectorAll('button').forEach(function(b){ b.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    var sel = $('clubSelect');
    sel.style.display = ''; sel.innerHTML = '<option>⏳ Chargement…</option>';
    fetch('?api=clubs_letter&l=' + encodeURIComponent(L) + KEY_QS)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok || !d.clubs || !d.clubs.length) { sel.innerHTML = '<option value="">Aucun club en « ' + L + ' »</option>'; return; }
            var opts = '<option value="">— ' + d.total + ' club(s) en « ' + L + ' » — choisis-en un —</option>';
            opts += d.clubs.map(function(c){ return '<option value="' + esc(c.nom) + '">' + esc(c.nom) + ' (' + c.nb + ' ath.)</option>'; }).join('');
            sel.innerHTML = opts;
        })
        .catch(function(){ sel.innerHTML = '<option value="">Erreur de chargement</option>'; });
};

function loadRoster() {
    var club = $('clubInput').value.trim();
    if (!club) { alert('Indique le nom du club.'); return; }
    $('btnLoad').disabled = true; $('btnLoad').textContent = 'Chargement…';
    fetch('?api=roster&club=' + encodeURIComponent(club) + KEY_QS)
        .then(function(r){ return r.json(); })
        .then(function(d){
            $('btnLoad').disabled = false; $('btnLoad').textContent = 'Charger le club';
            if (!d.ok) { alert(d.error || 'Erreur'); return; }
            if (!d.total) { alert('Aucun athlète trouvé pour « ' + club +' ». Vérifie le nom exact.'); return; }
            roster = d.athletes; total = d.total; stateKey = d.state_key || '';
            nameMap = {}; roster.forEach(function(a){ nameMap[a.id] = a.name; });
            try { localStorage.setItem('bk_uc_lastclub', d.club); } catch(e){}

            $('clubName').textContent = d.club;
            $('total').textContent = d.total;
            $('cTotal').textContent = d.total;
            $('log').innerHTML = '';
            $('panel').style.display = '';
            $('btnStart').style.display = ''; $('btnStart').disabled = false;
            $('btnPause').style.display = 'none';

            if (d.resume && d.resume.cursor > 0) {
                // Reprise : on restaure le curseur + les compteurs sauvegardes cote serveur
                cursor = d.resume.cursor;
                stats  = d.resume.stats || { done:cursor, NEW:0, MAJ:0, SKIP:0, ERR:0 };
                $('btnStart').textContent = '▶ Reprendre (' + cursor + '/' + total + ')';
                $('btnReset').style.display = '';
                logLine('muted', '↻ Reprise disponible : ' + cursor + ' / ' + total + ' déjà traités' +
                    (d.resume.updated ? ' (dernier passage ' + esc(d.resume.updated) + ')' : '') +
                    '. Clique « Reprendre », ou « Recommencer » pour repartir de zéro.');
            } else {
                cursor = 0;
                stats  = { done:0, NEW:0, MAJ:0, SKIP:0, ERR:0 };
                $('btnStart').textContent = '▶ Démarrer';
                $('btnReset').style.display = 'none';
                logLine('muted', 'Club chargé : ' + d.total + ' athlète(s). Prêt à démarrer.');
            }
            updateProgress();
        })
        .catch(function(e){ $('btnLoad').disabled = false; $('btnLoad').textContent = 'Charger le club'; alert('Erreur réseau : ' + e.message); });
}

function logLine(cls, html) {
    var d = document.createElement('div');
    d.className = 'l-' + cls;
    d.innerHTML = html;
    var box = $('log');
    var atBottom = box.scrollTop + box.clientHeight >= box.scrollHeight - 30;
    box.appendChild(d);
    if (atBottom) box.scrollTop = box.scrollHeight;
}

function startRun() {
    if (running) return;
    if (cursor >= total && total > 0) { logLine('muted', 'Déjà terminé. Clique « Recommencer » pour refaire.'); return; }
    running = true;
    if (!t0) { t0 = Date.now(); timer = setInterval(tick, 1000); }
    $('btnStart').style.display = 'none';
    $('btnPause').style.display = '';
    $('btnReset').style.display = 'none';
    nextBatch();
}
function pauseRun() {
    running = false;
    $('btnPause').style.display = 'none';
    $('btnStart').style.display = ''; $('btnStart').textContent = '▶ Reprendre (' + cursor + '/' + total + ')';
    $('btnReset').style.display = '';
    logLine('muted', '⏸ En pause à ' + cursor + ' / ' + total + '. Tu peux fermer la page — la progression est sauvegardée.');
}
function tick() { $('elapsed').textContent = Math.round((Date.now()-t0)/1000) + 's'; }

function nextBatch() {
    if (!running) return;
    if (cursor >= total) { finish(); return; }

    var fd = new FormData();
    fd.append('state_key', stateKey);
    fd.append('size', BATCH_SIZE);
    fd.append('skip', $('skip24').checked ? '1' : '0');

    fetch('?api=batch' + KEY_QS, { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok) {
                logLine('err', 'Lot en erreur : ' + esc(d.error||'?'));
                running = false;
                $('btnPause').style.display = 'none'; $('btnStart').style.display = '';
                return;
            }
            (d.results || []).forEach(function(res){ applyResult(res, nameMap[res.id]); });
            if (d.stats) stats = d.stats;          // compteurs cumules cote serveur (fiables apres reprise)
            cursor = d.cursor;
            updateProgress();
            if (d.done) { finish(); return; }
            setTimeout(nextBatch, PAUSE_MS);
        })
        .catch(function(e){
            logLine('err', 'Erreur réseau sur un lot : ' + esc(e.message) + ' — reprise dans 3s (progression sauvegardée)');
            setTimeout(function(){ if (running) nextBatch(); }, 3000);
        });
}

function applyResult(res, name) {
    var nm = esc(name || res.name || ('#' + res.id));
    var link = '<a href="https://athle.fr/athletes/' + res.id + '/bilans" target="_blank">[#' + res.id + ']</a>';
    if (res.action === 'NEW' || res.action === 'MAJ') {
        var c = res.counts || {};
        logLine(res.action.toLowerCase(),
            (res.action === 'NEW' ? '🟦 NEW' : '🟪 MAJ') + ' — ' + nm + ' ' + link +
            ' <span class="muted">(rec ' + (c.records||0) + ', prog ' + (c.progressions||0) + ', res ' + (c.resultats||0) + ', pod ' + (c.podiums||0) + ')</span>');
    } else if (res.action === 'SKIP') {
        logLine('skip', '⏭ déjà à jour (7j) — ' + nm + ' ' + link);
    } else {
        logLine('err', '❌ ERR — ' + nm + ' ' + link + ' <span class="muted">' + esc(res.error||'') + '</span>');
    }
}

function updateProgress() {
    $('cDone').textContent = stats.done || 0;
    $('cNew').textContent  = stats.NEW  || 0;
    $('cMaj').textContent  = stats.MAJ  || 0;
    $('cSkip').textContent = stats.SKIP || 0;
    $('cErr').textContent  = stats.ERR  || 0;
    var pct = total ? Math.round(cursor / total * 100) : 0;
    $('barFill').style.width = pct + '%';
}

function resetRun() {
    if (running) { alert('Mets d\'abord en pause.'); return; }
    if (!stateKey) return;
    if (!confirm('Recommencer ce club depuis le début ?\n\nLa progression sauvegardée sera effacée (les athlètes déjà à jour seront tout de même ignorés par le skip 7j).')) return;
    var fd = new FormData(); fd.append('state_key', stateKey);
    fetch('?api=reset' + KEY_QS, { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(){
            cursor = 0; stats = { done:0, NEW:0, MAJ:0, SKIP:0, ERR:0 };
            updateProgress();
            $('btnStart').textContent = '▶ Démarrer'; $('btnStart').disabled = false; $('btnStart').style.display = '';
            $('btnReset').style.display = 'none';
            logLine('muted', '↺ Progression réinitialisée — repart de 0.');
        });
}

function finish() {
    running = false;
    if (timer) { clearInterval(timer); timer = null; }
    $('btnPause').style.display = 'none';
    $('btnStart').style.display = '';
    $('btnStart').textContent = '✓ Terminé'; $('btnStart').disabled = true;
    $('btnReset').style.display = '';
    var maj = (stats.NEW||0) + (stats.MAJ||0);
    logLine('muted', '━━━━━━━━━━━━━━━━━━━━━━━━');
    logLine('maj', '✅ Terminé — ' + maj + ' mis à jour (' + (stats.NEW||0) + ' nouveaux, ' + (stats.MAJ||0) + ' MAJ), ' +
        (stats.SKIP||0) + ' ignorés, ' + (stats.ERR||0) + ' erreurs. Cache des profils vidé.');
}

// Auto-charger si ?club= present
<?php if ($club !== ''): ?>
loadRoster();
<?php endif; ?>
</script>
</body>
</html>
