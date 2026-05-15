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
$PROGRESS_FILE = dirname(__DIR__) . '/scraping/selections_progress.txt';
$TARGETS_FILE  = dirname(__DIR__) . '/scraping/selections_targets.json';
$FAILED_FILE   = dirname(__DIR__) . '/scraping/selections_failed.json';

require_once dirname(__DIR__) . '/core/credentials.php';
require_once dirname(__DIR__) . '/Class/AthleteScraper.php';

// Connexion BDD
$conn = new mysqli('localhost', $username, $password, $dbname);
if ($conn->connect_error) {
    die('DB error: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Levels par defaut
$levels = $_GET['levels'] ?? 'IA,IB,IE,N1,N2';
$levelList = array_filter(array_map('trim', explode(',', $levels)));
$levelSql = "'" . implode("','", array_map([$conn, 'real_escape_string'], $levelList)) . "'";

// Start / stop / reset
if (isset($_GET['start_resel'])) {
    file_put_contents($RUNNING_FLAG, json_encode(['started' => date('Y-m-d H:i:s'), 'levels' => $levels]));
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
if (isset($_GET['stop_resel'])) {
    if (file_exists($RUNNING_FLAG)) unlink($RUNNING_FLAG);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
if (isset($_GET['reset_to'])) {
    file_put_contents($PROGRESS_FILE, max(0, (int)$_GET['reset_to']));
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
if (isset($_GET['clear_targets'])) {
    @unlink($TARGETS_FILE);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
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

/** Telecharge les pages /selections de plusieurs athletes en parallele */
function fetchSelectionsParallel(array $athleteExtIds): array {
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
        $results[$idExt] = curl_multi_getcontent($ch) ?: null;
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
    <div class="stat">Statut : <?= $isRunning ? '<span class="ok">EN COURS</span>' : '<span class="err">ARRETE</span>' ?></div>

    <div class="bar"><div class="bar-inner" style="width:<?= $pct ?>%"></div></div>

    <div>
        <?php if (!$isRunning): ?>
            <a class="btn success" href="?start_resel=1&levels=<?= urlencode($levels) ?>">DEMARRER</a>
        <?php else: ?>
            <a class="btn danger" href="?stop_resel=1">ARRETER</a>
        <?php endif; ?>
        <a class="btn" href="?reset_to=0">Reset progression</a>
        <a class="btn" href="?clear_targets=1">Reload cible (changer niveaux)</a>
    </div>

    <div style="margin-top:16px;">
        <form method="get" style="display:inline-block;">
            Niveaux : <input type="text" name="levels" value="<?= htmlspecialchars($levels) ?>" style="background:#0a0e1a;color:#e0e0e0;border:1px solid #1a2540;padding:6px 10px;border-radius:6px;width:300px;">
            <button type="submit">Mettre a jour</button>
        </form>
    </div>

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
    $pages = fetchSelectionsParallel($extIds);

    foreach ($batch as $idx => [$idA, $idExt]) {
        $html = $pages[$idExt] ?? null;
        if (!$html) {
            echo "<span class='err'>[$progress] id=$idExt FETCH FAIL</span>\n";
            // Log echec
            $failed = file_exists($FAILED_FILE) ? json_decode(file_get_contents($FAILED_FILE), true) ?: [] : [];
            $failed[$idExt] = ['ts' => date('Y-m-d H:i:s'), 'reason' => 'fetch_fail'];
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

// Auto-refresh si toujours en cours
if (file_exists($RUNNING_FLAG)) {
    echo '<script>setTimeout(function(){location.reload();}, 1000);</script>';
}
?>
</body></html>
