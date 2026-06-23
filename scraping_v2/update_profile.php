<?php
/**
 * update_profile.php — Mise a jour d'UN profil unique depuis la FFA (athle.fr)
 *
 * Flux demande :
 *   1. On arrive avec ?id=<athlete_id_externe> (depuis le bouton du profil)
 *   2. La page SCRAPE en direct la fiche athle.fr et MONTRE l'etat
 *      (identite + compteurs parses) face a l'etat actuel en BDD — SANS rien ecrire
 *   3. SEULEMENT apres clic sur "Mettre a jour", les donnees sont ecrites en BDD
 *   4. Bouton "Retour au profil" (via &return=)
 *
 * Acces reserve : super admin (bk_sa_token) / email panel / cle maitre (bk_key) / local.
 */

@ini_set('display_errors', '1');
@error_reporting(E_ALL);
@set_time_limit(120);

// Requete API (JSON) : on coupe l'affichage des erreurs et on bufferise DES
// MAINTENANT (avant les require), pour qu'aucun warning ne pollue le JSON.
$UP_IS_API = isset($_GET['api']) || isset($_POST['api']);
if ($UP_IS_API) {
    @ini_set('display_errors', '0');
    @ini_set('html_errors', '0');
    ob_start();
}

require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/athlete_purge.php'; // bkUserCanPurge()
require_once dirname(__DIR__) . '/Class/AthleteScraper.php';
require_once dirname(__DIR__) . '/core/insert_athle.php';
require_once dirname(__DIR__) . '/core/progressions_store.php'; // athlete_progressions en mode fichier (archives/)
require_once dirname(__DIR__) . '/scraping/scrape_functions.php';

// ---------------------------------------------------------------------------
// Acces : PUBLIC (tout le monde peut mettre a jour un profil, A TOUT MOMENT).
// Aucun delai d'attente par profil. Seul garde-fou : un plafond par IP tres
// large pour eviter le scraping de masse abusif (l'admin le contourne).
// ---------------------------------------------------------------------------
$UP_IS_ADMIN     = bkUserCanPurge($conn);
const UP_MAX_IP_HOUR  = 200;  // actions max / heure / IP (large, anti-abus seulement)

function up_clientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if ($ip !== '') return $ip;
        }
    }
    return 'unknown';
}

/** Plafond par IP (fichier compteur horaire). Retourne true si OK, false si depasse. */
function up_ipRateOk(): bool {
    $file = dirname(__DIR__) . '/logs/.update_profile_limits.php';
    $ip = up_clientIp();
    $now = time();
    $data = [];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        $pos = $raw !== false ? strpos($raw, "\n") : false;
        if ($pos !== false) $data = json_decode(substr($raw, $pos + 1), true) ?: [];
    }
    // Purge entrees > 1h
    foreach ($data as $k => $entry) {
        if (($entry['t'] ?? 0) < $now - 3600) unset($data[$k]);
    }
    $cur = $data[$ip] ?? ['c' => 0, 't' => $now];
    if (($cur['t'] ?? 0) < $now - 3600) $cur = ['c' => 0, 't' => $now];
    if (($cur['c'] ?? 0) >= UP_MAX_IP_HOUR) return false;
    $cur['c'] = ($cur['c'] ?? 0) + 1;
    $cur['t'] = $cur['t'] ?? $now;
    $data[$ip] = $cur;
    @file_put_contents($file, "<?php die(); ?>\n" . json_encode($data));
    return true;
}


// ---------------------------------------------------------------------------
// Helpers : compteurs BDD + scrape live
// ---------------------------------------------------------------------------
function up_bddState(mysqli $conn, int $idExt): array {
    $r = $conn->query("SELECT id_athlete, nom_complet_athlete, date_creation_athlete,
                              licence_athlete, annee_naissance_athlete, categorie_athlete,
                              nationalite_athlete, taille_cm_athlete, poids_kg_athlete
                       FROM athletes WHERE athlete_id_externe = $idExt LIMIT 1");
    if (!$r || $r->num_rows === 0) {
        return ['present' => false, 'counts' => [], 'name' => '', 'last_resultat' => null, 'created' => null, 'identite' => []];
    }
    $a = $r->fetch_assoc();
    $idA = (int)$a['id_athlete'];
    $det = $conn->query("
        SELECT
          (SELECT COUNT(*) FROM athlete_clubs        WHERE id_athlete = $idA) AS clubs,
          (SELECT COUNT(*) FROM athlete_records      WHERE id_athlete = $idA) AS records,
          (SELECT COUNT(*) FROM athlete_progressions WHERE id_athlete = $idA) AS progressions,
          (SELECT COUNT(*) FROM athlete_resultats    WHERE id_athlete = $idA) AS resultats,
          (SELECT COUNT(*) FROM athlete_medailles    WHERE id_athlete = $idA) AS medailles,
          (SELECT COUNT(*) FROM athlete_podiums      WHERE id_athlete = $idA) AS podiums,
          (SELECT COUNT(*) FROM athlete_selections   WHERE id_athlete = $idA) AS selections,
          (SELECT COUNT(*) FROM athlete_niveaux      WHERE id_athlete = $idA) AS niveaux,
          (SELECT MAX(date_resultat) FROM athlete_resultats WHERE id_athlete = $idA) AS last_resultat
    ")->fetch_assoc();

    // athlete_progressions peut etre en mode fichier (archives/) : compter la vraie source
    $progCount = (int)$det['progressions'];
    $progSource = 'bdd';
    if (function_exists('progStoreEnabled') && progStoreEnabled()) {
        $progSource = 'fichier';
        try {
            $rows = progStoreLoadForAthlete($idA);
            $progCount = is_array($rows) ? count($rows) : 0;
        } catch (Throwable $e) { /* garde le compte BDD en secours */ }
    }

    return [
        'present'       => true,
        'id_athlete'    => $idA,
        'name'          => $a['nom_complet_athlete'],
        'created'       => $a['date_creation_athlete'],
        'last_resultat' => $det['last_resultat'],
        'prog_source'   => $progSource,
        'identite'      => [
            'licence'         => trim((string)($a['licence_athlete'] ?? '')),
            'annee_naissance' => (int)($a['annee_naissance_athlete'] ?? 0),
            'categorie'       => trim((string)($a['categorie_athlete'] ?? '')),
            'nationalite'     => trim((string)($a['nationalite_athlete'] ?? '')),
            'taille_cm'       => (int)($a['taille_cm_athlete'] ?? 0),
            'poids_kg'        => (int)($a['poids_kg_athlete'] ?? 0),
        ],
        'counts'        => [
            'clubs'        => (int)$det['clubs'],
            'records'      => (int)$det['records'],
            'progressions' => $progCount,
            'resultats'    => (int)$det['resultats'],
            'medailles'    => (int)$det['medailles'],
            'podiums'      => (int)$det['podiums'],
            'selections'   => (int)$det['selections'],
            'niveaux'      => (int)$det['niveaux'],
        ],
    ];
}

/**
 * Compare l'etat AVANT et APRES une mise a jour et construit un resume lisible
 * des ajouts / rectifications. Retourne { changed, lines[], added_total }.
 */
function up_diffSummary(array $before, array $after): array {
    $lines = [];
    $addedTotal = 0;
    $labels = [
        'clubs' => 'club', 'records' => 'record', 'progressions' => 'progression',
        'resultats' => 'resultat', 'medailles' => 'medaille', 'podiums' => 'podium',
        'selections' => 'selection', 'niveaux' => 'niveau',
    ];
    $bc = $before['counts'] ?? [];
    $ac = $after['counts'] ?? [];
    foreach ($labels as $k => $lib) {
        $b = (int)($bc[$k] ?? 0);
        $a = (int)($ac[$k] ?? 0);
        if ($a === $b) continue;
        $delta = $a - $b;
        if ($delta > 0) $addedTotal += $delta;
        $sign = $delta > 0 ? '+' . $delta : (string)$delta;
        $plur = abs($a) > 1 ? 's' : '';
        $lines[] = ($delta > 0 ? '+' : '') . $delta . ' ' . $lib . (abs($delta) > 1 ? 's' : '') . ' (' . $b . ' &rarr; ' . $a . ')';
    }

    // Identite : champs nouvellement renseignes
    $bi = $before['identite'] ?? [];
    $ai = $after['identite'] ?? [];
    if (empty($bi['licence']) && !empty($ai['licence'])) {
        $lines[] = 'Licence ajoutee (' . $ai['licence'] . ')';
    }
    if ((int)($bi['annee_naissance'] ?? 0) === 0 && (int)($ai['annee_naissance'] ?? 0) > 0) {
        $lines[] = 'Annee de naissance ajoutee (' . (int)$ai['annee_naissance'] . ')';
    }
    if (empty($bi['categorie']) && !empty($ai['categorie'])) {
        $lines[] = 'Categorie ajoutee (' . $ai['categorie'] . ')';
    }

    // Nouvelle derniere competition
    $lb = $before['last_resultat'] ?? null;
    $la = $after['last_resultat'] ?? null;
    if ($la && $la !== $lb) {
        $lines[] = 'Nouvelle competition : ' . $la;
    }

    return [
        'changed'     => !empty($lines),
        'added_total' => $addedTotal,
        'lines'       => $lines,
    ];
}

/** Scrape live la fiche athle.fr et renvoie le scraper rempli (ou null). */
function up_scrape(int $idExt): ?AthleteScraper {
    $pages = scrapeParallel([$idExt]);
    $allPages = $pages[$idExt] ?? null;
    if (!$allPages || empty($allPages['bilans'])) return null;

    $scraper = new AthleteScraper($idExt);
    $scraper->html = $allPages['bilans'];
    $scraper->extractIdentite();
    $scraper->extractMedailles();
    $scraper->extractProgressions();
    $scraper->extractClubs();
    $scraper->extractPodiums();
    $scraper->extractResultats();
    $scraper->extractNiveaux();
    if (!empty($allPages['records'])) {
        $scraper->html = $allPages['records'];
        $scraper->extractRecords();
    }
    if (!empty($allPages['selections'])) {
        $scraper->html = $allPages['selections'];
        $scraper->extractSelections();
    }
    return $scraper;
}

function up_scrapeCounts(AthleteScraper $s): array {
    return [
        'clubs'        => count($s->clubs),
        'records'      => count($s->records),
        'progressions' => count($s->progressions),
        'resultats'    => count($s->resultats),
        'medailles'    => count($s->medailles),
        'podiums'      => count($s->podiums),
        'selections'   => count($s->selections),
        'niveaux'      => count($s->niveaux),
    ];
}

// ---------------------------------------------------------------------------
// API AJAX
// ---------------------------------------------------------------------------
$api = $_GET['api'] ?? $_POST['api'] ?? null;
if ($api) {
    // --- Sortie JSON blindee (buffer deja ouvert tout en haut du fichier) ---
    header('Content-Type: application/json; charset=utf-8');
    $UP_DEBUG = $UP_IS_ADMIN; // n'expose les details d'erreur qu'a l'admin (bk_key/SA)
    register_shutdown_function(function () use ($UP_DEBUG) {
        // Vide toutes les couches de buffer encore ouvertes.
        $out = '';
        while (ob_get_level() > 0) { $out = ob_get_clean() . $out; }
        $jsonPos = strpos($out, '{'); // notre JSON commence par {
        if ($jsonPos !== false) {
            // Du HTML d'erreur a pu preceder le JSON : on ne garde que le JSON.
            echo substr($out, $jsonPos);
            return;
        }
        // Pas de JSON => fatal pur. On renvoie un JSON d'erreur propre.
        $err = error_get_last();
        $msg = 'Erreur serveur pendant le traitement.';
        if ($UP_DEBUG && $err) {
            $msg .= ' [' . $err['message'] . ' @ ' . basename($err['file'] ?? '') . ':' . ($err['line'] ?? '?') . ']';
        }
        if ($out !== '' && $UP_DEBUG) {
            $msg .= ' Sortie: ' . mb_substr(trim(strip_tags($out)), 0, 300);
        }
        echo json_encode(['ok' => false, 'error' => $msg]);
    });

    $idExt = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if (!$idExt) { echo json_encode(['ok' => false, 'error' => 'id manquant']); exit; }

    // Auto-MAJ a la consultation : si skip_recent=1 et deja scrape < 7 jours, on sort
    // tout de suite (avant meme le plafond IP) -> quasi gratuit, ne consomme rien.
    if ($api === 'commit' && (!empty($_POST['skip_recent']) || !empty($_GET['skip_recent']))) {
        $rChk = @$conn->query("SELECT 1 FROM athlete_scrape_log
                               WHERE athlete_id_ext = $idExt
                                 AND last_scraped_at > DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1");
        if ($rChk && $rChk->num_rows > 0) {
            echo json_encode(['ok' => true, 'skipped' => true, 'recent' => true, 'id_ext' => $idExt]);
            exit;
        }
    }

    // Plafond par IP (sauf admin)
    if (!$UP_IS_ADMIN && !up_ipRateOk()) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Trop de mises a jour depuis votre connexion. Reessayez dans une heure.']);
        exit;
    }

    // --- PREVIEW : scrape live + etat BDD, AUCUNE ecriture ----------------
    if ($api === 'preview') {
        try {
            $t0 = microtime(true);
            $bdd = up_bddState($conn, $idExt);
            $s = up_scrape($idExt);
            if (!$s || empty($s->identite['nom_complet'])) {
                echo json_encode(['ok' => false, 'error' => 'Fetch athle.fr a echoue (profil introuvable ou page vide).', 'id_ext' => $idExt]);
                exit;
            }
            echo json_encode([
                'ok'       => true,
                'id_ext'   => $idExt,
                'bdd'      => $bdd,
                'scraped'  => [
                    'nom_complet'   => $s->identite['nom_complet'] ?? '',
                    'categorie'     => $s->identite['categorie'] ?? '',
                    'sexe'          => $s->identite['sexe'] ?? '',
                    'nationalite'   => $s->identite['nationalite'] ?? '',
                    'licence'       => $s->identite['licence'] ?? '',
                    'annee_naissance' => $s->identite['annee_naissance'] ?? '',
                    'counts'        => up_scrapeCounts($s),
                ],
                'duree_s'  => round(microtime(true) - $t0, 1),
            ]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'id_ext' => $idExt]);
        }
        exit;
    }

    // --- COMMIT : scrape + ECRITURE en BDD --------------------------------
    if ($api === 'commit') {
        try {
            $t0 = microtime(true);

            // Pas de delai d'attente : la mise a jour est possible a tout moment.

            // Etat AVANT (pour le resume des ajouts / rectifications)
            $before = up_bddState($conn, $idExt);
            $wasExisting = !empty($before['present']);

            $s = up_scrape($idExt);
            if (!$s || empty($s->identite['nom_complet'])) {
                echo json_encode(['ok' => false, 'error' => 'Fetch athle.fr a echoue.', 'id_ext' => $idExt]);
                exit;
            }
            $cache = loadRefCache($conn);
            ob_start();
            insertAthleteData($s, $conn, $cache);
            ob_end_clean();

            // Regenere le dump JSON src/{id}.php SEULEMENT si le dossier est deja
            // utilise (pipeline principal). On ne ressuscite pas un dossier mort.
            try {
                $srcDir = dirname(__DIR__) . '/src';
                if (is_dir($srcDir)) {
                    $phpContent = "<?php\nheader(\"Access-Control-Allow-Origin: *\");\nheader(\"Content-Type: application/json; charset=utf-8\");\n?>\n"
                        . json_encode($s->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    @file_put_contents($srcDir . '/' . $idExt . '.php', $phpContent);
                }
            } catch (Throwable $eSrc) { /* non bloquant */ }

            // Log scrape OK (skip 24h des autres outils)
            $conn->query("CREATE TABLE IF NOT EXISTS athlete_scrape_log (
                athlete_id_ext INT UNSIGNED PRIMARY KEY,
                last_scraped_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_action ENUM('NEW','MAJ') DEFAULT 'MAJ',
                INDEX idx_last_scraped (last_scraped_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $action = $wasExisting ? 'MAJ' : 'NEW';
            $stmt = $conn->prepare("INSERT INTO athlete_scrape_log (athlete_id_ext, last_action)
                                    VALUES (?, ?)
                                    ON DUPLICATE KEY UPDATE last_scraped_at = NOW(), last_action = VALUES(last_action)");
            if ($stmt) { $stmt->bind_param('is', $idExt, $action); $stmt->execute(); $stmt->close(); }

            // Vider le cache de ce profil pour que la fiche reflete la MAJ.
            // api/athlete.php : cle = athlete_md5(ext_'_'int_'_'lic), sortie JSON_PRETTY
            // (donc "athlete_id": 809035 AVEC espace -> matcher tolerant obligatoire).
            $cacheDir = dirname(__DIR__) . '/cache';
            if (is_dir($cacheDir)) {
                $idInt = 0;
                $rI = $conn->query("SELECT id_athlete FROM athletes WHERE athlete_id_externe = $idExt LIMIT 1");
                if ($rI && $rI->num_rows) $idInt = (int)$rI->fetch_assoc()['id_athlete'];
                // Cles deterministes : lookup par ?id= (externe) et ?id_athlete= (interne)
                $keys = ['athlete_' . md5($idExt . '__')];
                if ($idInt) $keys[] = 'athlete_' . md5('_' . $idInt . '_');
                foreach ($keys as $k) { @unlink($cacheDir . '/' . $k . '.json'); }
                // Filet de securite : tout cache contenant cet athlete (tolere l'espace JSON_PRETTY)
                foreach (glob($cacheDir . '/athlete_*.json') ?: [] as $f) {
                    $json = @file_get_contents($f);
                    if ($json && preg_match('/"athlete_id":\s*' . $idExt . '\b/', $json)) {
                        @unlink($f);
                    }
                }
            }

            $after   = up_bddState($conn, $idExt); // etat APRES ecriture
            $summary = up_diffSummary($before, $after);

            echo json_encode([
                'ok'      => true,
                'action'  => $action,
                'id_ext'  => $idExt,
                'name'    => $s->identite['nom_complet'] ?? '',
                'counts'  => up_scrapeCounts($s),
                'bdd'     => $after,
                'summary' => $summary,
                'duree_s' => round(microtime(true) - $t0, 1),
            ]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'id_ext' => $idExt]);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'API inconnue']);
    exit;
}

// ---------------------------------------------------------------------------
// PAGE
// ---------------------------------------------------------------------------
$idExt   = (int)($_GET['id'] ?? 0);
$return  = $_GET['return'] ?? '';
// Securise le retour : seulement un chemin relatif interne
if ($return !== '' && (strpos($return, '://') !== false || strpos($return, '//') === 0)) {
    $return = '';
}
$bkKey   = $_GET['bk_key'] ?? '';
$keyQs   = $bkKey !== '' ? '&bk_key=' . urlencode($bkKey) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Mise a jour profil — FFA</title>
<style>
    :root { --bg:#0d1117; --card:#161b22; --border:#30363d; --text:#e6edf3; --muted:#8b949e;
            --accent:#6c5ce7; --green:#3fb950; --red:#f85149; --blue:#3b82f6; --orange:#f59e0b; }
    * { box-sizing: border-box; }
    body { background: var(--bg); color: var(--text); font-family: system-ui, -apple-system, Segoe UI, sans-serif;
           margin: 0; padding: 24px; }
    .wrap { max-width: 880px; margin: 0 auto; }
    h1 { font-size: 22px; margin: 0 0 4px; }
    .sub { color: var(--muted); font-size: 14px; margin: 0 0 20px; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
    .row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    input[type=text] { background: #0d1117; border: 1px solid var(--border); color: var(--text);
                       padding: 10px 12px; border-radius: 8px; font-size: 15px; flex: 1; min-width: 200px; }
    button { border: none; border-radius: 8px; padding: 10px 18px; font-size: 14px; font-weight: 700;
             cursor: pointer; color: #fff; }
    .btn-go    { background: linear-gradient(135deg,#1e3a8a,#3b82f6); }
    .btn-commit{ background: linear-gradient(135deg,#15803d,#22c55e); }
    .btn-commit:disabled { opacity: .4; cursor: not-allowed; }
    .btn-back  { background: #30363d; }
    a.btn-back { display: inline-block; text-decoration: none; }
    .id-badge { color: var(--blue); font-weight: 700; }
    table.cmp { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px; }
    table.cmp th, table.cmp td { padding: 7px 10px; text-align: left; border-bottom: 1px solid var(--border); }
    table.cmp th { color: var(--muted); font-weight: 600; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .delta-up   { color: var(--green); font-weight: 700; }
    .delta-zero { color: var(--muted); }
    .ident { display: grid; grid-template-columns: repeat(auto-fit,minmax(140px,1fr)); gap: 10px; margin-top: 10px; }
    .ident .it { background:#0d1117; border:1px solid var(--border); border-radius:8px; padding:8px 10px; }
    .ident .it small { color: var(--muted); display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
    .ident .it span { font-size: 15px; font-weight: 600; }
    .state { font-size: 14px; }
    .pill { display:inline-block; padding:2px 10px; border-radius:99px; font-size:12px; font-weight:700; }
    .pill.new { background:#1f6feb33; color:#79c0ff; border:1px solid #1f6feb; }
    .pill.maj { background:#6c5ce733; color:#b3a7f5; border:1px solid var(--accent); }
    .pill.absent { background:#f8514933; color:#ff7b72; border:1px solid var(--red); }
    .spinner { display:inline-block; width:14px; height:14px; border:2px solid #ffffff55; border-top-color:#fff;
               border-radius:50%; animation: spin .7s linear infinite; vertical-align:-2px; margin-right:6px; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .err { color: var(--red); font-weight: 600; }
    .ok  { color: var(--green); font-weight: 700; }
    .muted { color: var(--muted); }
    .ffa-link { color: var(--blue); text-decoration: none; }
    .ffa-link:hover { text-decoration: underline; }
    .note { font-size: 13px; color: var(--muted); margin-top: 10px; line-height: 1.5; }
</style>
</head>
<body>
<div class="wrap">
    <h1>&#127942; Mise a jour d'un profil depuis la FFA</h1>
    <p class="sub">Scrape en direct la fiche athle.fr, montre l'etat, puis ecrit en base seulement apres validation.</p>

    <div class="card">
        <form id="loadForm" class="row" onsubmit="return false;">
            <input type="text" id="idInput" placeholder="ID athle.fr ou URL (ex: 123456)" value="<?= $idExt ?: '' ?>" autofocus>
            <button class="btn-go" id="btnLoad" onclick="loadPreview()">Analyser la fiche</button>
            <?php if ($return): ?>
                <a class="btn-back" href="<?= htmlspecialchars($return) ?>">&#8592; Retour au profil</a>
            <?php endif; ?>
        </form>
        <p class="note">
            Source : la fiche officielle est connue via <code>athlete_id_externe</code>.
            Etape 1 = lecture seule (rien n'est modifie). Etape 2 = ecriture en BDD (insert/update + cache vide).
        </p>
    </div>

    <div id="result"></div>
</div>

<script>
var KEY_QS = <?= json_encode($keyQs) ?>;
var RETURN_URL = <?= json_encode($return) ?>;
var LABELS = {
    clubs:'Clubs', records:'Records', progressions:'Progressions', resultats:'Resultats',
    medailles:'Medailles', podiums:'Podiums', selections:'Selections', niveaux:'Niveaux'
};

function parseId(v) {
    v = (v || '').trim();
    if (/^\d+$/.test(v)) return parseInt(v, 10);
    var m = v.match(/\/athletes\/(\d+)/);
    return m ? parseInt(m[1], 10) : 0;
}

function esc(s) { return (s == null ? '' : String(s)).replace(/[&<>"]/g, function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

function loadPreview() {
    var id = parseId(document.getElementById('idInput').value);
    var res = document.getElementById('result');
    if (!id) { res.innerHTML = '<div class="card err">ID invalide. Entrez un nombre ou une URL athle.fr.</div>'; return; }

    // Reflete l'id dans l'URL (pour partage / refresh)
    try { history.replaceState(null,'', '?id=' + id + (RETURN_URL ? '&return=' + encodeURIComponent(RETURN_URL) : '')); } catch(e){}

    res.innerHTML = '<div class="card"><span class="spinner"></span> Lecture de la fiche athle.fr #' + id + ' &hellip;</div>';

    fetch('?api=preview&id=' + id + KEY_QS)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok) { res.innerHTML = '<div class="card err">' + esc(d.error || 'Echec') + '</div>'; return; }
            renderPreview(d);
        })
        .catch(function(e){ res.innerHTML = '<div class="card err">Erreur reseau : ' + esc(e.message) + '</div>'; });
}

function renderPreview(d) {
    var s = d.scraped, b = d.bdd;
    var present = b.present;
    var pill = present
        ? '<span class="pill maj">Deja en BDD &rarr; MISE A JOUR</span>'
        : '<span class="pill new">Absent &rarr; CREATION</span>';

    var html = '<div class="card">';
    html += '<div class="row" style="justify-content:space-between;">';
    html += '<div><strong style="font-size:18px;">' + esc(s.nom_complet) + '</strong> ' + pill + '</div>';
    html += '<a class="ffa-link" target="_blank" rel="noopener" href="https://athle.fr/athletes/' + d.id_ext + '/bilans">Voir sur athle.fr &#8599;</a>';
    html += '</div>';

    html += '<div class="ident">';
    html += identCell('Categorie', s.categorie);
    html += identCell('Sexe', s.sexe);
    html += identCell('Nationalite', s.nationalite);
    html += identCell('Licence', s.licence);
    html += identCell('Naissance', s.annee_naissance);
    html += '</div>';

    // Comparatif compteurs BDD actuelle vs FFA scrapee
    html += '<table class="cmp"><tr><th>Donnee</th><th class="num">En BDD</th><th class="num">Sur la FFA</th><th class="num">Delta</th></tr>';
    Object.keys(LABELS).forEach(function(k){
        var cur = present ? (b.counts[k] || 0) : 0;
        var ffa = s.counts[k] || 0;
        var delta = ffa - cur;
        var dCls = delta > 0 ? 'delta-up' : 'delta-zero';
        var dStr = delta > 0 ? '+' + delta : (delta === 0 ? '=' : delta);
        html += '<tr><td>' + LABELS[k] + '</td><td class="num">' + cur + '</td><td class="num">' + ffa + '</td><td class="num ' + dCls + '">' + dStr + '</td></tr>';
    });
    html += '</table>';

    if (present && b.last_resultat) {
        html += '<p class="note">Dernier resultat en BDD : <strong>' + esc(b.last_resultat) + '</strong> &middot; cree le ' + esc(b.created) + '</p>';
    }
    if (present && b.prog_source === 'fichier') {
        html += '<p class="note">&#9432; Les <strong>progressions</strong> sont stockees en mode fichier (archives/), pas en table BDD &mdash; le compteur ci-dessus lit la vraie source.</p>';
    }
    html += '<p class="note">Fiche lue en ' + d.duree_s + 's. Aucune donnee n\'a encore ete modifiee.</p>';

    html += '<div class="row" style="margin-top:14px;">';
    html += '<button class="btn-commit" id="btnCommit" onclick="commit(' + d.id_ext + ')">&#128190; Mettre a jour les donnees en BDD</button>';
    html += '<button class="btn-back" onclick="loadPreview()">&#8635; Re-analyser</button>';
    html += '</div>';
    html += '</div>';

    document.getElementById('result').innerHTML = html;
}

function identCell(label, val) {
    return '<div class="it"><small>' + label + '</small><span>' + (val ? esc(val) : '<span class="muted">&mdash;</span>') + '</span></div>';
}

function commit(id) {
    var btn = document.getElementById('btnCommit');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Ecriture en cours&hellip;'; }

    var fd = new FormData();
    fd.append('id', id);
    fetch('?api=commit' + KEY_QS, { method:'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok) {
                if (btn) { btn.disabled = false; btn.innerHTML = '&#128190; Reessayer'; }
                appendError(d.error || 'Echec de l\'ecriture');
                return;
            }
            renderDone(d);
        })
        .catch(function(e){
            if (btn) { btn.disabled = false; btn.innerHTML = '&#128190; Reessayer'; }
            appendError('Erreur reseau : ' + e.message);
        });
}

function appendError(msg) {
    var div = document.createElement('div');
    div.className = 'card err';
    div.textContent = msg;
    document.getElementById('result').appendChild(div);
}

function renderDone(d) {
    var actLabel = d.action === 'NEW' ? 'Profil CREE' : 'Profil MIS A JOUR';
    var html = '<div class="card">';
    html += '<p class="ok" style="font-size:17px;">&#10004; ' + actLabel + ' &mdash; ' + esc(d.name) + '</p>';
    html += '<table class="cmp"><tr><th>Donnee</th><th class="num">Ecrit (FFA)</th></tr>';
    Object.keys(LABELS).forEach(function(k){
        html += '<tr><td>' + LABELS[k] + '</td><td class="num">' + (d.counts[k] || 0) + '</td></tr>';
    });
    html += '</table>';
    html += '<p class="note">Ecrit en ' + d.duree_s + 's. Cache du profil vide.</p>';
    html += '<div class="row" style="margin-top:14px;">';
    if (RETURN_URL) {
        html += '<a class="btn-back" href="' + esc(RETURN_URL) + '">&#8592; Retour au profil</a>';
    }
    html += '<button class="btn-go" onclick="loadPreview()">&#8635; Re-analyser</button>';
    html += '</div>';
    html += '</div>';
    document.getElementById('result').innerHTML = html;
}

// Auto-load si ?id= present
<?php if ($idExt): ?>
loadPreview();
<?php endif; ?>
</script>
</body>
</html>
