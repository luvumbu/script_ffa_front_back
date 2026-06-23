<?php
/**
 * rescrape_selections.php — Re-scraping cible des selections uniquement.
 *
 * Scrape la page /selections de chaque athlete (haut niveau par defaut),
 * parse avec le nouveau extractSelections() et reinsère dans athlete_selections.
 *
 * Strategie :
 *  - Cible configurable via ?levels=IA,IB,IE,N1,N2 (defaut)
 *  - Batch parallel curl_multi (7 athletes simultanes)
 *  - Resumable : progress.txt + skip athletes deja faits
 *  - Auto-refresh toutes les 25s tant que le flag est present
 *  - Bouton start / stop
 *
 * URLs utiles :
 *   ?start_resel=1     → demarrer (cree le flag)
 *   ?stop_resel=1      → arreter
 *   ?reset_to=0        → recommencer a l'index N
 *   ?levels=IA,IB,IE   → restreindre/elargir la cible
 *   ?test_id=264469    → tester un athlete unique (parse + insert)
 */

ob_start();
session_start();
ini_set('max_execution_time', 0);

$TIME_LIMIT = 25;
$PARALLEL   = 7;

$RUNNING_FLAG = dirname(__DIR__) . '/scraping/selections_running.flag';
$FAILED_FILE   = dirname(__DIR__) . '/scraping/selections_failed.json';
$CHOICE_FILE   = dirname(__DIR__) . '/scraping/selections_levels_choice.txt'; // choix enregistre (persistant)
// $PROGRESS_FILE et $TARGETS_FILE sont definis plus bas, APRES le parsing des niveaux :
// 1 jeu de fichiers par combinaison de niveaux -> plus de melange IA / autres.

require_once dirname(__DIR__) . '/core/credentials.php';
require_once dirname(__DIR__) . '/Class/AthleteScraper.php';

// Connexion BDD
$conn = new mysqli('localhost', $username, $password, $dbname);
if ($conn->connect_error) {
    die('DB error: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Niveaux : priorite URL > choix enregistre (fichier) > defaut.
// => une fois le choix enregistre, il persiste meme sans ?levels= dans l'URL.
$savedChoice = is_file($CHOICE_FILE) ? trim((string)@file_get_contents($CHOICE_FILE)) : '';
$levels = $_GET['levels'] ?? ($savedChoice !== '' ? $savedChoice : 'IA,IB,IE,N1,N2');
$levelList = array_filter(array_map('trim', explode(',', $levels)));
$levelSql = "'" . implode("','", array_map([$conn, 'real_escape_string'], $levelList)) . "'";

// Cle canonique du jeu de niveaux (triee + normalisee) -> fichiers dedies.
// Ainsi ?levels=IA a SES propres cible/progression, jamais melangees avec le defaut.
$canon = $levelList;
sort($canon);
$levelKey = preg_replace('/[^A-Z0-9_]/', '', strtoupper(implode('_', $canon)));
if ($levelKey === '') $levelKey = 'default';
$PROGRESS_FILE = dirname(__DIR__) . '/scraping/selections_progress_' . $levelKey . '.txt';
$TARGETS_FILE  = dirname(__DIR__) . '/scraping/selections_targets_'  . $levelKey . '.json';
$SPEED_FILE    = dirname(__DIR__) . '/scraping/selections_speed_'    . $levelKey . '.json'; // vitesse memorisee (athletes/sec)
$DEFAULT_RATE  = 0.45; // athletes/sec : estimation de depart tant qu'aucune mesure reelle n'existe

// IMPORTANT : on PRESERVE le parametre levels dans toutes les redirections,
// sinon le script retombe sur le jeu par defaut (IA,IB,IE,N1,N2) et melange tout.
$selfRedirect = strtok($_SERVER['REQUEST_URI'], '?') . '?levels=' . urlencode($levels);

// Enregistrer le choix de niveaux (persistant) -> puis on continue depuis ce choix
if (isset($_GET['save_levels'])) {
    $list  = array_filter(array_map('trim', explode(',', (string)$_GET['save_levels'])));
    $clean = implode(',', $list);
    if ($clean !== '') {
        file_put_contents($CHOICE_FILE, $clean);
    } else {
        @unlink($CHOICE_FILE); // choix vide => retour au defaut
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?levels=' . urlencode($clean) . '&saved=1');
    exit;
}

// Start / stop / reset
if (isset($_GET['start_resel'])) {
    // On memorise l'instant de demarrage ET la progression de depart -> sert a estimer le temps restant (ETA).
    $startProgress = (int)@file_get_contents($PROGRESS_FILE);
    file_put_contents($RUNNING_FLAG, json_encode([
        'started'        => date('Y-m-d H:i:s'),
        'started_ts'     => time(),
        'start_progress' => $startProgress,
        'levels'         => $levels,
    ]));
    header('Location: ' . $selfRedirect);
    exit;
}
if (isset($_GET['stop_resel'])) {
    if (file_exists($RUNNING_FLAG)) unlink($RUNNING_FLAG);
    header('Location: ' . $selfRedirect);
    exit;
}
if (isset($_GET['reset_to'])) {
    file_put_contents($PROGRESS_FILE, max(0, (int)$_GET['reset_to']));
    header('Location: ' . $selfRedirect);
    exit;
}
if (isset($_GET['clear_targets'])) {
    @unlink($TARGETS_FILE);
    header('Location: ' . $selfRedirect);
    exit;
}

$isRunning = file_exists($RUNNING_FLAG);

// =====================================================
// Fonctions utilitaires
// =====================================================

/** Parse + insere les selections d'1 athlete */
function processAthlete(mysqli $conn, int $idAthlete, int $athleteIdExt, string $html): array {
    $scraper = new AthleteScraper($athleteIdExt);
    $scraper->html = $html;
    $scraper->extractSelections();
    $nb = count($scraper->selections);

    if ($nb === 0) {
        // Pas de selections : on supprime quand meme l'eventuel cache (idempotent)
        $conn->query("DELETE FROM athlete_selections WHERE id_athlete = $idAthlete");
        return ['ok' => true, 'nb' => 0, 'msg' => 'aucune selection'];
    }

    // Pre-charger competitions et epreuves uniques en memoire pour lookup
    static $compCache = [];
    static $epCache   = [];

    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM athlete_selections WHERE id_athlete = $idAthlete");

        $vals = [];
        foreach ($scraper->selections as $s) {
            // Competition : insert if not exists
            $compName = trim($s['competition']);
            $idComp = 'NULL';
            if ($compName !== '') {
                $key = mb_strtolower($compName);
                if (!isset($compCache[$key])) {
                    $escComp = $conn->real_escape_string($compName);
                    $r = $conn->query("SELECT id_competition FROM competitions WHERE nom_competition = '$escComp' LIMIT 1");
                    if ($r && $row = $r->fetch_assoc()) {
                        $compCache[$key] = (int)$row['id_competition'];
                    } else {
                        $conn->query("INSERT INTO competitions (nom_competition) VALUES ('$escComp')");
                        $compCache[$key] = $conn->insert_id;
                    }
                }
                $idComp = (int)$compCache[$key];
            }

            // Epreuve : insert if not exists
            $epName = trim($s['epreuve']);
            $idEp = 'NULL';
            if ($epName !== '') {
                $key = mb_strtolower($epName);
                if (!isset($epCache[$key])) {
                    $escEp = $conn->real_escape_string($epName);
                    $r = $conn->query("SELECT id_epreuve FROM epreuves WHERE nom_epreuve = '$escEp' LIMIT 1");
                    if ($r && $row = $r->fetch_assoc()) {
                        $epCache[$key] = (int)$row['id_epreuve'];
                    } else {
                        $conn->query("INSERT INTO epreuves (nom_epreuve) VALUES ('$escEp')");
                        $epCache[$key] = $conn->insert_id;
                    }
                }
                $idEp = (int)$epCache[$key];
            }

            $date  = $s['date'] ? "'" . $conn->real_escape_string($s['date']) . "'" : 'NULL';
            $type  = $conn->real_escape_string($s['type']);
            $duree = (int)$s['duree_jours'];
            $age   = (int)$s['age'];
            $rang  = (int)$s['classement'];
            $perf  = (int)$s['performance'];
            $pBrut = $conn->real_escape_string($s['performance_brut'] ?? '');

            $vals[] = "($idAthlete, '$type', $date, $duree, $age, $idComp, $idEp, $rang, $perf, '$pBrut')";
        }

        if ($vals) {
            $sql = "INSERT INTO athlete_selections
                    (id_athlete, type_selection, date_selection, duree_jours_selection, age_selection,
                     id_competition, id_epreuve, classement_selection, performance_selection, performance_brut_selection)
                    VALUES " . implode(',', $vals);
            $conn->query($sql);
        }

        $conn->commit();
        return ['ok' => true, 'nb' => $nb, 'msg' => "$nb selections inserees"];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'nb' => 0, 'msg' => 'Erreur SQL : ' . $e->getMessage()];
    }
}

/**
 * Telecharge les pages /selections de plusieurs athletes en parallele.
 * $errors (optionnel, par reference) recoit pour chaque echec la vraie raison
 * (code HTTP + erreur curl) -> sert a diagnostiquer les FETCH FAIL.
 */
function fetchSelectionsParallel(array $athleteExtIds, ?array &$errors = null): array {
    $errors = [];
    $mh = curl_multi_init();
    $handles = [];
    foreach ($athleteExtIds as $idExt) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://athle.fr/athletes/$idExt/selections",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$idExt] = $ch;
    }
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    $results = [];
    foreach ($handles as $idExt => $ch) {
        $body  = curl_multi_getcontent($ch);
        $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $errmsg = curl_error($ch);
        if (!$body || $http < 200 || $http >= 400) {
            // On note la raison precise de l'echec.
            if ($errno) {
                $errors[$idExt] = "curl #$errno: $errmsg";
            } elseif ($http === 0) {
                $errors[$idExt] = "pas de reponse (timeout/connexion)";
            } elseif (!$body) {
                $errors[$idExt] = "HTTP $http (page vide)";
            } else {
                $errors[$idExt] = "HTTP $http";
            }
            $results[$idExt] = null;
        } else {
            $results[$idExt] = $body;
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

/** Charge la liste des athletes cibles (en cache) */
function loadTargets(mysqli $conn, string $levelSql, string $targetsFile): array {
    if (file_exists($targetsFile)) {
        $data = json_decode(file_get_contents($targetsFile), true);
        if (is_array($data) && !empty($data)) return $data;
    }
    $sql = "SELECT DISTINCT a.id_athlete, a.athlete_id_externe
            FROM athletes a
            JOIN athlete_niveaux n ON n.id_athlete = a.id_athlete
            WHERE n.code_niveau IN ($levelSql)
              AND a.athlete_id_externe IS NOT NULL
              AND a.athlete_id_externe > 0
            ORDER BY a.id_athlete";
    $r = $conn->query($sql);
    $list = [];
    while ($row = $r->fetch_assoc()) {
        $list[] = [(int)$row['id_athlete'], (int)$row['athlete_id_externe']];
    }
    file_put_contents($targetsFile, json_encode($list));
    return $list;
}

// =====================================================
// Mode test (1 athlete)
// =====================================================
if (isset($_GET['test_id'])) {
    $tid = (int)$_GET['test_id'];
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test selections</title>";
    echo "<style>body{background:#0a0e1a;color:#e0e0e0;font-family:monospace;padding:20px;}</style></head><body>";
    echo "<h2>Test parsing selections — athlete_id_externe = $tid</h2>";

    $r = $conn->query("SELECT id_athlete, nom_complet_athlete FROM athletes WHERE athlete_id_externe = $tid LIMIT 1");
    if (!$r || !($row = $r->fetch_assoc())) {
        echo "<p style='color:#f87171;'>Athlete introuvable en BDD</p></body></html>";
        exit;
    }
    $idA = (int)$row['id_athlete'];
    echo "<p>BDD : <b>" . htmlspecialchars($row['nom_complet_athlete']) . "</b> (id=$idA)</p>";

    $start = microtime(true);
    $pages = fetchSelectionsParallel([$tid]);
    $html = $pages[$tid] ?? null;
    if (!$html) {
        echo "<p style='color:#f87171;'>Fetch failed</p></body></html>";
        exit;
    }
    echo "<p>Page recue : " . number_format(strlen($html)) . " octets</p>";

    $result = processAthlete($conn, $idA, $tid, $html);
    $ms = round((microtime(true) - $start) * 1000);
    $color = $result['ok'] ? '#34d399' : '#f87171';
    echo "<p style='color:$color;'>[$ms ms] " . htmlspecialchars($result['msg']) . "</p>";

    // Re-fetch from BDD pour verifier
    $q = "SELECT s.type_selection, s.date_selection, c.nom_competition, e.nom_epreuve, s.classement_selection, s.performance_brut_selection
          FROM athlete_selections s
          LEFT JOIN competitions c ON c.id_competition = s.id_competition
          LEFT JOIN epreuves e ON e.id_epreuve = s.id_epreuve
          WHERE s.id_athlete = $idA
          ORDER BY s.date_selection DESC
          LIMIT 20";
    $r = $conn->query($q);
    echo "<table style='border-collapse:collapse;'><tr><th>Type</th><th>Date</th><th>Comp.</th><th>Epreuve</th><th>Rang</th><th>Perf</th></tr>";
    while ($row = $r->fetch_assoc()) {
        echo "<tr style='border-top:1px solid #333;'>";
        foreach (['type_selection','date_selection','nom_competition','nom_epreuve','classement_selection','performance_brut_selection'] as $k) {
            echo "<td style='padding:4px 8px;'>" . htmlspecialchars($row[$k] ?? '') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    echo "</body></html>";
    exit;
}

// =====================================================
// Affichage du dashboard
// =====================================================
$progress = (int)@file_get_contents($PROGRESS_FILE);
$targets = loadTargets($conn, $levelSql, $TARGETS_FILE);
$total = count($targets);
$pct = $total > 0 ? round($progress / $total * 100, 1) : 0;

/** Formate une duree en secondes -> "1h 23m 45s" (compact, sans zeros inutiles) */
function fmtDuration(int $sec): string {
    if ($sec < 0) $sec = 0;
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    $s = $sec % 60;
    $out = [];
    if ($h > 0) $out[] = $h . 'h';
    if ($m > 0 || $h > 0) $out[] = $m . 'm';
    $out[] = $s . 's';
    return implode(' ', $out);
}

// =====================================================
// Estimation du temps restant (ETA)
// Base : vitesse moyenne depuis le demarrage du run courant.
// =====================================================
$eta = null; // ['rate_min','elapsed','remaining_count','eta_sec','finish_ts','done_session','measured']
if ($isRunning) {
    $flagData = json_decode((string)@file_get_contents($RUNNING_FLAG), true) ?: [];
    $startedTs    = (int)($flagData['started_ts'] ?? 0);
    // Fallback pour un flag cree avant l'ajout de l'ETA : on parse la date texte.
    if ($startedTs === 0 && !empty($flagData['started'])) {
        $startedTs = (int)strtotime((string)$flagData['started']);
    }
    $startProgress = (int)($flagData['start_progress'] ?? $progress);
    $elapsed = $startedTs > 0 ? max(1, time() - $startedTs) : 0;
    $doneSession = max(0, $progress - $startProgress);
    $remainingCount = max(0, $total - $progress);

    // Vitesse : 1) mesure du run en cours (la plus fiable) 2) vitesse memorisee
    //           3) vitesse par defaut. On a TOUJOURS un chiffre.
    $measured = false;
    if ($elapsed > 0 && $doneSession > 0) {
        $ratePerSec = $doneSession / $elapsed;
        $measured = true;
    } else {
        $saved = json_decode((string)@file_get_contents($SPEED_FILE), true);
        $ratePerSec = (is_array($saved) && !empty($saved['rate']) && $saved['rate'] > 0)
            ? (float)$saved['rate']
            : $DEFAULT_RATE;
    }
    if ($ratePerSec <= 0) $ratePerSec = $DEFAULT_RATE;

    $etaSec = (int)round($remainingCount / $ratePerSec);
    $eta = [
        'rate_min'        => round($ratePerSec * 60, 1),
        'elapsed'         => $elapsed,
        'remaining_count' => $remainingCount,
        'eta_sec'         => $etaSec,
        'finish_ts'       => time() + $etaSec,
        'done_session'    => $doneSession,
        'measured'        => $measured,
    ];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Re-scraping selections</title>
    <style>
    body { background:#0a0e1a; color:#e0e0e0; font-family:'Segoe UI',monospace; padding:20px; max-width:900px; margin:auto; }
    h1 { color:#a29bfe; }
    .stat { display:inline-block; background:#111830; border:1px solid #1a2540; padding:10px 16px; border-radius:8px; margin:4px; }
    .stat b { color:#a29bfe; font-size:18px; }
    .bar { height:18px; background:#1a2540; border-radius:9px; overflow:hidden; margin:12px 0; }
    .bar-inner { height:100%; background:linear-gradient(90deg,#34d399,#a29bfe); transition:width 0.5s; }
    button, .btn { background:#1e2a3a; color:#a29bfe; border:1px solid #a29bfe; padding:8px 20px; border-radius:8px; cursor:pointer; text-decoration:none; display:inline-block; margin:4px; font-size:14px; }
    .btn.danger { color:#f87171; border-color:#f87171; }
    .btn.success { color:#34d399; border-color:#34d399; }
    pre { background:#111830; padding:12px; border-radius:8px; overflow:auto; max-height:300px; font-size:11px; }
    .ok { color:#34d399; }
    .err { color:#f87171; }
    .skip { color:#fbbf24; }
    </style>
</head>
<body>
    <h1>Re-scraping selections athletes</h1>

    <div class="stat">Niveaux cibles : <b><?= htmlspecialchars($levels) ?></b></div>
    <div class="stat">Cible : <b><?= number_format($total) ?></b> athletes</div>
    <div class="stat">Avance : <b><?= number_format($progress) ?></b> (<?= $pct ?>%)</div>
    <div class="stat">Restant : <b><?= number_format(max(0, $total - $progress)) ?></b></div>
    <div class="stat">Statut : <?= $isRunning ? '<span class="ok">EN COURS</span>' : '<span class="err">ARRETE</span>' ?>
        <?php if ($isRunning): ?>
            &nbsp;&#9201; <b id="etaInline" style="color:#a29bfe;"><?= htmlspecialchars(fmtDuration($eta['eta_sec'])) ?></b>
        <?php endif; ?>
    </div>

    <div class="bar"><div class="bar-inner" style="width:<?= $pct ?>%"></div></div>

    <?php if ($isRunning): ?>
    <div style="margin:8px 0;background:#0f1a2e;border:1px solid #a29bfe;border-radius:8px;padding:12px 16px;">
        <div style="color:#a29bfe;font-weight:700;margin-bottom:6px;">
            &#9201; Estimation du temps
            <span id="etaMode" style="font-weight:400;font-size:12px;color:#8b949e;"><?= $eta['measured'] ? '(vitesse mesurée)' : '(estimation de départ, s’affine en scrapant)' ?></span>
        </div>
        <div class="stat">Temps restant : <b id="etaCountdown"><?= htmlspecialchars(fmtDuration($eta['eta_sec'])) ?></b></div>
        <div class="stat">Fin estimée vers : <b id="etaFinish"><?= date('H:i:s', $eta['finish_ts']) ?></b></div>
        <div class="stat">Vitesse : <b id="etaRate"><?= $eta['rate_min'] ?></b> /min</div>
        <div class="stat">Écoulé : <b id="etaElapsed"><?= htmlspecialchars(fmtDuration($eta['elapsed'])) ?></b></div>
    </div>
    <script>
    (function(){
        // Compte a rebours vivant. bkEta porte l'etat ; bkEtaSync() le re-cale sur la
        // vraie mesure serveur (au chargement ET a la fin de chaque cycle de scraping).
        function fmt(sec){
            sec = Math.max(0, Math.round(sec));
            var h = Math.floor(sec/3600), m = Math.floor((sec%3600)/60), s = sec%60;
            var out = [];
            if (h > 0) out.push(h+'h');
            if (m > 0 || h > 0) out.push(m+'m');
            out.push(s+'s');
            return out.join(' ');
        }
        window.bkEta = window.bkEta || { remain: <?= (int)$eta['eta_sec'] ?>, elapsed: <?= (int)$eta['elapsed'] ?> };
        window.bkEtaSync = function(remainSec, elapsedSec, rateMin, finishTxt){
            window.bkEta.remain  = remainSec;
            window.bkEta.elapsed = elapsedSec;
            var r = document.getElementById('etaRate');   if (r && rateMin   != null) r.textContent = rateMin;
            var f = document.getElementById('etaFinish');  if (f && finishTxt != null) f.textContent = finishTxt;
            var m = document.getElementById('etaMode');    if (m) m.textContent = '(vitesse mesurée)';
        };
        function paint(){
            var cd  = document.getElementById('etaCountdown');
            var inl = document.getElementById('etaInline');
            var el  = document.getElementById('etaElapsed');
            var txt = fmt(window.bkEta.remain);
            if (cd)  cd.textContent  = txt;
            if (inl) inl.textContent = txt;
            if (el)  el.textContent  = fmt(window.bkEta.elapsed);
        }
        if (!window.bkEtaTick) {
            window.bkEtaTick = setInterval(function(){
                if (window.bkEta.remain > 0) window.bkEta.remain--;
                window.bkEta.elapsed++;
                paint();
            }, 1000);
        }
        paint();
    })();
    </script>
    <?php endif; ?>

    <div>
        <?php $lvlQs = '&levels=' . urlencode($levels); ?>
        <?php if (!$isRunning): ?>
            <a class="btn success" href="?start_resel=1<?= $lvlQs ?>">DEMARRER</a>
        <?php else: ?>
            <a class="btn danger" href="?stop_resel=1<?= $lvlQs ?>">ARRETER</a>
        <?php endif; ?>
        <a class="btn" href="?reset_to=0<?= $lvlQs ?>">Reset progression</a>
        <a class="btn" href="?clear_targets=1<?= $lvlQs ?>">Reload cible (changer niveaux)</a>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div style="margin-top:16px;background:#0f2a1a;border:1px solid #34d399;border-radius:8px;padding:12px 16px;color:#34d399;">
        ✓ <b>Choix enregistré</b> : <b><?= htmlspecialchars($levels) ?></b> — Cible : <b><?= number_format($total) ?></b> athlète(s).
        Tu peux maintenant cliquer <b>DEMARRER</b> pour lancer (ou continuer) le scraping sur ce choix.
    </div>
    <?php endif; ?>

    <div style="margin-top:16px;background:#111830;border:1px solid #1a2540;border-radius:8px;padding:14px;">
        <div style="margin-bottom:4px;color:#a29bfe;font-weight:600;">Choisir les niveaux à cibler :</div>
        <div style="margin-bottom:10px;font-size:12px;color:#8b949e;">
            Choix enregistré actuel : <b style="color:#a29bfe;"><?= $savedChoice !== '' ? htmlspecialchars($savedChoice) : '(aucun — défaut IA,IB,IE,N1,N2)' ?></b>
        </div>
        <?php
        $selSet = array_flip(array_map('strtoupper', $levelList)); // niveaux deja coches
        $famNiv = [
            'International' => ['IA','IB','IE','IR'],
            'National'     => ['N1','N2','N3','N4'],
            'Régional'     => ['R1','R2','R3','R4','R5','R6'],
            'Départemental'=> ['D1','D2','D3','D4','D5','D6','D7','D8'],
        ];
        foreach ($famNiv as $fam => $codes): ?>
            <div style="margin:6px 0;display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                <span style="width:110px;color:#8b949e;font-size:13px;"><?= $fam ?></span>
                <?php foreach ($codes as $code):
                    $on = isset($selSet[$code]); ?>
                    <label style="display:inline-flex;align-items:center;gap:5px;background:<?= $on ? '#1e2a3a' : '#0a0e1a' ?>;border:1px solid <?= $on ? '#a29bfe' : '#1a2540' ?>;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:13px;user-select:none;">
                        <input type="checkbox" class="lvlchk" value="<?= $code ?>" <?= $on ? 'checked' : '' ?> style="cursor:pointer;accent-color:#a29bfe;">
                        <?= $code ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <button type="button" class="btn" onclick="lvlCheckAll(true)">Tout cocher</button>
            <button type="button" class="btn" onclick="lvlCheckAll(false)">Tout décocher</button>
            <button type="button" class="btn success" style="font-weight:700;" onclick="lvlSave()">&#128190; Enregistrer le choix</button>
            <span style="color:#8b949e;font-size:12px;">Sélection cochée : <b style="color:#a29bfe;"><?= htmlspecialchars($levels) ?></b></span>
        </div>
        <div style="margin-top:6px;font-size:12px;color:#8b949e;">Enregistre d'abord ton choix, puis clique <b>DEMARRER</b> ci-dessus pour lancer/continuer dessus.</div>
    </div>
    <script>
    function lvlCheckAll(v){ document.querySelectorAll('.lvlchk').forEach(function(c){ c.checked = v; }); }
    function lvlSave(){
        var sel = Array.prototype.slice.call(document.querySelectorAll('.lvlchk:checked')).map(function(c){ return c.value; });
        if (!sel.length){ alert('Coche au moins un niveau avant d\'enregistrer.'); return; }
        window.location = '?save_levels=' + encodeURIComponent(sel.join(','));
    }
    </script>

    <div style="margin-top:16px;">
        <form method="get" style="display:inline-block;">
            Test athlete (athlete_id_externe) : <input type="number" name="test_id" placeholder="ex: 264469" style="background:#0a0e1a;color:#e0e0e0;border:1px solid #1a2540;padding:6px 10px;border-radius:6px;width:180px;">
            <button type="submit">Tester</button>
        </form>
    </div>

<?php
// =====================================================
// Boucle de scraping (si le flag est present)
// =====================================================
if (!$isRunning) {
    echo '<p style="margin-top:24px;color:#8b949e;">Cliquez DEMARRER pour lancer.</p>';
    echo '</body></html>';
    exit;
}

echo '<h3 style="margin-top:24px;">Log du cycle</h3><pre>';

$start = microtime(true);
$processed = 0;
$inserted  = 0;

while ((microtime(true) - $start) < $TIME_LIMIT) {
    // Verifier le flag a chaque boucle (stop propre)
    clearstatcache();
    if (!file_exists($RUNNING_FLAG)) {
        echo "FLAG SUPPRIME — arret propre.\n";
        break;
    }

    if ($progress >= $total) {
        echo "TERMINE : tous les athletes traites.\n";
        if (file_exists($RUNNING_FLAG)) unlink($RUNNING_FLAG);
        break;
    }

    // Prendre le prochain batch
    $batch = array_slice($targets, $progress, $PARALLEL);
    if (empty($batch)) break;

    $extIds = array_map(fn($p) => $p[1], $batch);
    $fetchErr = [];
    $pages = fetchSelectionsParallel($extIds, $fetchErr);

    foreach ($batch as $idx => [$idA, $idExt]) {
        $html = $pages[$idExt] ?? null;
        if (!$html) {
            $reason = $fetchErr[$idExt] ?? 'echec inconnu';
            echo "<span class='err'>[$progress] id=$idExt FETCH FAIL — " . htmlspecialchars($reason) . "</span>\n";
            // Log echec (avec la raison precise)
            $failed = file_exists($FAILED_FILE) ? json_decode(file_get_contents($FAILED_FILE), true) ?: [] : [];
            $failed[$idExt] = ['ts' => date('Y-m-d H:i:s'), 'reason' => $reason];
            file_put_contents($FAILED_FILE, json_encode($failed));
            $progress++;
            continue;
        }
        $r = processAthlete($conn, $idA, $idExt, $html);
        $cls = $r['ok'] ? ($r['nb'] > 0 ? 'ok' : 'skip') : 'err';
        echo "<span class='$cls'>[$progress] id=$idExt — " . htmlspecialchars($r['msg']) . "</span>\n";
        $processed++;
        $inserted += $r['nb'];
        $progress++;
    }

    file_put_contents($PROGRESS_FILE, $progress);

    if (ob_get_level()) {
        ob_flush();
        flush();
    }
}

$elapsed = round(microtime(true) - $start, 1);
echo "</pre>";
echo "<p>Cycle termine : <b>$processed</b> athletes en {$elapsed}s, <b>$inserted</b> selections inserees au total.</p>";

// Re-synchroniser l'ETA en direct avec la vitesse mesuree depuis le demarrage du run.
// => le compte a rebours s'affiche/se corrige des la fin de CE cycle, sans attendre le reload.
$sessElapsed = isset($startedTs) && $startedTs > 0 ? (time() - $startedTs) : 0;
$sessDone    = isset($startProgress) ? max(0, $progress - $startProgress) : 0;
if ($sessElapsed > 0 && $sessDone > 0) {
    $sessRate   = $sessDone / $sessElapsed;                 // athletes / seconde
    $sessRemain = max(0, $total - $progress);
    $sessEta    = (int)round($sessRemain / $sessRate);
    $sessRateMin = round($sessRate * 60, 1);
    $sessFinish  = date('H:i:s', time() + $sessEta);
    // Memoriser la vitesse -> au prochain demarrage, l'ETA part deja d'une vraie mesure.
    @file_put_contents($SPEED_FILE, json_encode(['rate' => $sessRate, 'updated' => date('Y-m-d H:i:s')]));
    echo '<script>if(window.bkEtaSync){window.bkEtaSync(' . $sessEta . ',' . $sessElapsed . ',' . $sessRateMin . ',"' . $sessFinish . '");}</script>';
}

// Auto-refresh si toujours en cours
if (file_exists($RUNNING_FLAG)) {
    echo '<script>setTimeout(function(){location.reload();}, 1000);</script>';
}
?>
</body></html>
