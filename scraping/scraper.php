<?php
/**
 * scraper.php — Scraping PARALLÈLE + JSON + BDD (autonome)
 * Dashboard temps réel avec flush progressif
 */

session_start();

$TIME_LIMIT  = 25;  // secondes max par page
$PARALLEL    = 7;   // athlètes scrapés en parallèle

require_once dirname(__DIR__) . "/core/credentials.php";
require_once dirname(__DIR__) . "/Class/DatabaseHandler.php";
require_once dirname(__DIR__) . "/Class/AthleteScraper.php";
require_once dirname(__DIR__) . "/core/insert_athle.php";
require_once __DIR__ . "/scrape_functions.php";

// Reset progression si demande
if (isset($_GET['reset_to'])) {
    $resetVal = max(0, (int)$_GET['reset_to']);
    $progressFile = dirname(__DIR__) . "/progress.txt";
    file_put_contents($progressFile, $resetVal);
    unset($_SESSION["url"]);
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// =============================================
// 1. Charger les URLs (cache local)
// =============================================
$urlsCacheFile = dirname(__DIR__) . "/urls_cache.json";

if (!file_exists($urlsCacheFile)) {
    $dbRemote = new DatabaseHandler($dbname, $username, $password);
    $result = $dbRemote->select_custom_safe("SELECT * FROM `nom_et_liens` ORDER BY `id_nom_et_liens`");
    if ($result['success']) {
        file_put_contents($urlsCacheFile, json_encode($result['data'], JSON_UNESCAPED_UNICODE));
        $allUrlsRaw = $result['data'];
    } else {
        die("Erreur : " . $result['message']);
    }
    $dbRemote->closeConnection();
} else {
    $allUrlsRaw = json_decode(file_get_contents($urlsCacheFile), true);
}

$allUrls = [];
$maxId = 0;
foreach ($allUrlsRaw as $row) {
    $rowId = (int)$row['id_nom_et_liens'];
    $allUrls[$rowId] = $row;
    if ($rowId > $maxId) $maxId = $rowId;
}
$totalAthletes = count($allUrls);

// =============================================
// 2. Connexion BDD + schema + cache mémoire
// =============================================
$databaseHandler = new DatabaseHandler($dbname, $username, $password);
require_once dirname(__DIR__) . "/core/dbCheck_athle.php";

$conn = $databaseHandler->connection;
$cache = loadRefCache($conn);

// Charger athlete_id_externe deja en BDD
$existingAthletes = [];
$rExist = $conn->query("SELECT athlete_id_externe FROM athletes");
if ($rExist) while ($row = $rExist->fetch_assoc()) $existingAthletes[(int)$row['athlete_id_externe']] = true;
$nbExisting = count($existingAthletes);

// Compter les echecs
$failFile = dirname(__DIR__) . "/failed.json";
$nbFailed = 0;
if (file_exists($failFile)) {
    $failData = json_decode(file_get_contents($failFile), true);
    $nbFailed = is_array($failData) ? count($failData) : 0;
}

// =============================================
// 3. Progression
// =============================================
$progressFile = dirname(__DIR__) . "/progress.txt";

if (!isset($_SESSION["url"])) {
    $_SESSION["url"] = file_exists($progressFile) ? (int)file_get_contents($progressFile) : 0;
}
$id = $_SESSION["url"];

$done = 0;
foreach ($allUrls as $rowId => $row) {
    if ($rowId < $id) $done++;
}

$pctScan = $totalAthletes > 0 ? round(($done / $totalAthletes) * 100, 2) : 0;
$pctBdd = $totalAthletes > 0 ? round(($nbExisting / $totalAthletes) * 100, 2) : 0;
$remaining = $totalAthletes - $nbExisting;

// =============================================
// Envoyer le HTML du dashboard IMMEDIATEMENT
// =============================================
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scraping Athle.fr</title>
    <style>
        * { box-sizing: border-box; }
        body { background: #0a0a0a; color: #22c55e; font-family: 'Courier New', monospace; margin: 0; padding: 10px; }
        .error { color: #ef4444; }
        .skip { color: orange; }
        .dash { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; margin: 10px 0; }
        .dash-card { background: #111; border: 1px solid #333; border-radius: 8px; padding: 10px; text-align: center; }
        .dash-card .val { font-size: 24px; font-weight: bold; line-height: 1.2; }
        .dash-card .lbl { font-size: 10px; color: #888; margin-top: 3px; text-transform: uppercase; letter-spacing: 1px; }
        .c-green { color: #22c55e; } .c-cyan { color: #06b6d4; } .c-red { color: #ef4444; }
        .c-orange { color: #f97316; } .c-purple { color: #a78bfa; } .c-yellow { color: #eab308; }
        .c-blue { color: #3b82f6; } .c-white { color: #e5e7eb; }
        .bar-wrap { background: #222; border-radius: 8px; overflow: hidden; height: 28px; margin: 4px 0; border: 1px solid #333; }
        .bar-fill { height: 100%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #fff; font-size: 12px; transition: width 0.5s ease; min-width: 45px; }
        .log-box { max-height: 45vh; overflow-y: auto; background: #0d0d0d; border: 1px solid #222; border-radius: 6px; padding: 8px; margin-top: 6px; font-size: 12px; }
        .log-box p { margin: 2px 0; }
        .log-box hr { border-color: #222; margin: 4px 0; }
        h3.section { color: #a78bfa; border-bottom: 1px solid #333; padding-bottom: 4px; margin: 12px 0 6px; font-size: 13px; }
        .ctrl { margin: 8px 0; padding: 8px; background: #111; border: 1px solid #333; border-radius: 6px; display: inline-flex; gap: 8px; align-items: center; }
        .ctrl input { width: 100px; padding: 4px 8px; background: #222; border: 1px solid #555; color: #fff; border-radius: 4px; font-family: monospace; }
        .ctrl button { padding: 4px 12px; background: #222; border-radius: 4px; cursor: pointer; font-family: monospace; font-size: 12px; }
        .btn-orange { border: 1px solid orange; color: orange; }
        .btn-red { border: 1px solid #ef4444; color: #ef4444; }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
    </style>
</head>
<body>

<!-- Controles -->
<div class="ctrl">
    <form method="GET" style="display:flex;gap:6px;align-items:center;">
        <label style="color:cyan;font-size:12px;">Reprendre a :</label>
        <input type="number" name="reset_to" value="<?= $id ?>" min="0">
        <button type="submit" class="btn-orange">Reset</button>
    </form>
    <form method="GET" style="display:inline;"><input type="hidden" name="reset_to" value="0"><button type="submit" class="btn-red">Tout reprendre (0)</button></form>
</div>

<!-- Dashboard cards -->
<div class="dash">
    <div class="dash-card"><div class="val c-white" id="dTotal"><?= number_format($totalAthletes, 0, '', ' ') ?></div><div class="lbl">Total URLs</div></div>
    <div class="dash-card"><div class="val c-green" id="dBdd"><?= number_format($nbExisting, 0, '', ' ') ?></div><div class="lbl">En BDD</div></div>
    <div class="dash-card"><div class="val c-orange" id="dRest"><?= number_format($remaining, 0, '', ' ') ?></div><div class="lbl">Restants</div></div>
    <div class="dash-card"><div class="val c-red" id="dFail"><?= $nbFailed ?></div><div class="lbl">Echecs</div></div>
    <div class="dash-card"><div class="val c-cyan"><?= $PARALLEL ?></div><div class="lbl">Parallele</div></div>
    <div class="dash-card"><div class="val c-purple" id="dPos"><?= number_format($id, 0, '', ' ') ?></div><div class="lbl">Position ID</div></div>
    <div class="dash-card"><div class="val c-yellow" id="dSpeed">-</div><div class="lbl">Vitesse /s</div></div>
    <div class="dash-card"><div class="val c-blue" id="dEta">-</div><div class="lbl">ETA</div></div>
</div>

<!-- Barres de progression -->
<h3 class="section">Parcours des URLs</h3>
<div class="bar-wrap">
    <div class="bar-fill" id="barScan" style="width:<?= $pctScan ?>%;background:linear-gradient(90deg,#6c5ce7,#a78bfa);"><?= $pctScan ?>%</div>
</div>
<h3 class="section">Athletes en BDD</h3>
<div class="bar-wrap">
    <div class="bar-fill" id="barBdd" style="width:<?= $pctBdd ?>%;background:linear-gradient(90deg,#22c55e,#4ade80);"><?= $pctBdd ?>%</div>
</div>

<!-- Status -->
<h3 class="section"><span class="status-dot" id="statusDot" style="background:#22c55e;"></span><span id="statusText">Demarrage...</span></h3>

<!-- Log en direct -->
<div class="log-box" id="logBox"></div>

<!-- Resume (rempli a la fin) -->
<div id="resume"></div>

<script>
function _u(id, v) { document.getElementById(id).textContent = v; }
function _bar(id, pct) { var e = document.getElementById(id); e.style.width = pct + '%'; e.textContent = pct + '%'; }
function _log(html) { var b = document.getElementById('logBox'); b.innerHTML += html; b.scrollTop = b.scrollHeight; }
function _fmt(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
var _startTime = Date.now(), _totalInserted = 0, _totalAth = <?= $totalAthletes ?>;
</script>

<?php
// =============================================
// FLUSH — envoyer le dashboard au navigateur
// =============================================
if (ob_get_level()) ob_end_flush();
flush();

// =============================================
// 4. Boucle — par batch de $PARALLEL athlètes
// =============================================
$srcDir = dirname(__DIR__) . "/src";
if (!is_dir($srcDir)) mkdir($srcDir, 0755);

$pageStart = microtime(true);
$batchDone = 0;
$batchNum = 0;
$totalSkipped = 0;

while ($id <= $maxId) {

    if ((microtime(true) - $pageStart) > $TIME_LIMIT) break;

    // Vérifier la connexion MySQL
    if (!$conn->ping()) {
        $conn = new mysqli($databaseHandler->servername, $username, $password, $dbname);
        $conn->set_charset("utf8");
    }

    // Collecter les N prochains athlètes valides
    $batch = [];
    $batchUrls = [];
    $tempId = $id;
    $skippedThisBatch = 0;

    while (count($batch) < $PARALLEL && $tempId <= $maxId) {
        if (!isset($allUrls[$tempId])) {
            $tempId++;
            continue;
        }
        $url = $allUrls[$tempId]["url"] ?? null;
        if (empty($url)) {
            $tempId++;
            continue;
        }
        if (preg_match('#/athletes/(\d+)#', $url, $m)) {
            $athId = (int)$m[1];
            if (isset($existingAthletes[$athId])) {
                $skippedThisBatch++;
                $tempId++;
                continue;
            }
            $batch[$tempId] = $athId;
            $batchUrls[$tempId] = $url;
        }
        $tempId++;
    }
    $totalSkipped += $skippedThisBatch;

    if (empty($batch)) {
        $id = $tempId;
        $_SESSION["url"] = $id;
        file_put_contents($progressFile, $id);

        // Mettre a jour la barre scan meme si batch vide (skip rapide)
        $newDone = 0;
        foreach ($allUrls as $rid => $r) { if ($rid < $id) $newDone++; }
        $newPctScan = $totalAthletes > 0 ? round(($newDone / $totalAthletes) * 100, 2) : 0;
        echo "<script>_bar('barScan',{$newPctScan});_u('dPos','" . number_format($id, 0, '', ' ') . "');_u('statusText','Scan rapide... (skip " . number_format($totalSkipped, 0, '', ' ') . " deja en BDD)');</script>\n";
        flush();
        break;
    }

    $batchNum++;

    // Status : en cours
    echo "<script>_u('statusText','Batch #$batchNum — scrape " . count($batch) . " athletes...');</script>\n";
    flush();

    // --- Scraping parallèle ---
    $t0 = microtime(true);
    $pagesResults = scrapeParallel(array_values($batch));
    $scrapeTime = round((microtime(true) - $t0) * 1000);

    $batchInserted = 0;
    $batchErrors = 0;
    $names = [];

    // --- Traiter chaque athlète ---
    foreach ($batch as $idNom => $athleteIdExt) {
        $pages = $pagesResults[$athleteIdExt] ?? [];

        if (empty($pages['bilans'])) {
            $batchErrors++;
            $failFile = dirname(__DIR__) . "/failed.json";
            $fails = file_exists($failFile) ? json_decode(file_get_contents($failFile), true) : [];
            $fails[] = ['id_nom_et_liens' => $idNom, 'athlete_id' => $athleteIdExt, 'date' => date('Y-m-d H:i:s')];
            file_put_contents($failFile, json_encode($fails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "<script>_log('<p class=\"error\">#$idNom (athlete $athleteIdExt) : page bilans vide</p>');</script>\n";
            flush();
            continue;
        }

        try {
            $scraper = new AthleteScraper($athleteIdExt);
            $scraper->html = $pages['bilans'];
            $scraper->extractIdentite();
            $scraper->extractMedailles();
            $scraper->extractProgressions();
            $scraper->extractClubs();
            $scraper->extractPodiums();
            $scraper->extractResultats();
            $scraper->extractNiveaux();

            if (!empty($pages['records'])) {
                $scraper->html = $pages['records'];
                $scraper->extractRecords();
            }
            if (!empty($pages['selections'])) {
                $scraper->html = $pages['selections'];
                $scraper->extractSelections();
            }

            $nom = htmlspecialchars($scraper->identite['nom_complet'] ?? '?');

            // --- JSON ---
            $data = $scraper->toArray();
            $jsonString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $jsonPath = $srcDir . "/" . $athleteIdExt . ".php";
            $phpContent = "<?php\nheader(\"Access-Control-Allow-Origin: *\");\nheader(\"Content-Type: application/json; charset=utf-8\");\n?>\n" . $jsonString;
            file_put_contents($jsonPath, $phpContent);

            // --- BDD ---
            insertAthleteData($scraper, $conn, $cache);
            $existingAthletes[$athleteIdExt] = true;

            $names[] = $nom;
            $batchInserted++;
            $batchDone++;

        } catch (Exception $e) {
            $batchErrors++;
            $errMsg = htmlspecialchars($e->getMessage());
            $failFile = dirname(__DIR__) . "/failed.json";
            $fails = file_exists($failFile) ? json_decode(file_get_contents($failFile), true) : [];
            $fails[] = ['id_nom_et_liens' => $idNom, 'athlete_id' => $athleteIdExt, 'erreur' => $e->getMessage(), 'date' => date('Y-m-d H:i:s')];
            file_put_contents($failFile, json_encode($fails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "<script>_log('<p class=\"error\">#$idNom erreur : $errMsg</p>');</script>\n";
            flush();
        }
    }

    // Avancer la progression après le batch
    $id = $tempId;
    $_SESSION["url"] = $id;
    file_put_contents($progressFile, $id);

    // Calculer les nouvelles stats
    $newDone = 0;
    foreach ($allUrls as $rid => $r) { if ($rid < $id) $newDone++; }
    $newPctScan = $totalAthletes > 0 ? round(($newDone / $totalAthletes) * 100, 2) : 0;
    $newBdd = $nbExisting + $batchDone;
    $newPctBdd = $totalAthletes > 0 ? round(($newBdd / $totalAthletes) * 100, 2) : 0;
    $newRemaining = $totalAthletes - $newBdd;
    $elapsed = round(microtime(true) - $pageStart, 1);
    $speed = $elapsed > 0 ? round($batchDone / $elapsed, 1) : 0;

    // ETA
    $etaText = '-';
    if ($batchDone > 0) {
        $etaSec = round(($newRemaining * $elapsed) / $batchDone);
        $etaH = floor($etaSec / 3600);
        $etaM = floor(($etaSec % 3600) / 60);
        $etaText = $etaH . 'h ' . $etaM . 'm';
    }

    // Log du batch
    $namesStr = implode(', ', $names);
    $logHtml = "<p style='color:cyan;'>Batch #$batchNum : $batchInserted inseres, $batchErrors erreurs, {$scrapeTime}ms";
    if ($skippedThisBatch > 0) $logHtml .= ", $skippedThisBatch skips";
    $logHtml .= "</p>";
    if (!empty($namesStr)) {
        $logHtml .= "<p style='color:#4ade80;'>$namesStr</p>";
    }
    $logHtml .= "<hr/>";

    // Envoyer le script de mise a jour
    $jsNewBdd = number_format($newBdd, 0, '', ' ');
    $jsNewRem = number_format($newRemaining, 0, '', ' ');
    $jsPos = number_format($id, 0, '', ' ');
    $nbFailedNow = $nbFailed + $batchErrors;
    echo "<script>"
        . "_log('" . addslashes($logHtml) . "');"
        . "_bar('barScan',{$newPctScan});"
        . "_bar('barBdd',{$newPctBdd});"
        . "_u('dBdd','$jsNewBdd');"
        . "_u('dRest','$jsNewRem');"
        . "_u('dPos','$jsPos');"
        . "_u('dFail','$nbFailedNow');"
        . "_u('dSpeed','$speed/s');"
        . "_u('dEta','$etaText');"
        . "_u('statusText','Batch #$batchNum termine — pause 1s...');"
        . "_totalInserted=$batchDone;"
        . "</script>\n";
    flush();

    // Pause 1s entre chaque batch
    sleep(1);
}

// =============================================
// 5. Résumé final + auto-refresh
// =============================================
$databaseHandler->closeConnection();

$pageTime = round(microtime(true) - $pageStart, 1);
$finalBdd = $nbExisting + $batchDone;
$finalRemaining = $totalAthletes - $finalBdd;
$finalSpeed = $pageTime > 0 ? round($batchDone / $pageTime, 1) : 0;

if ($id <= $maxId) {
    // Encore du travail → refresh auto
    $etaText = '-';
    if ($batchDone > 0) {
        $etaSec = round(($finalRemaining * $pageTime) / $batchDone);
        $etaH = floor($etaSec / 3600);
        $etaM = floor(($etaSec % 3600) / 60);
        $etaText = $etaH . 'h ' . $etaM . 'm';
    }
    echo "<script>"
        . "_u('statusText','Cycle termine — refresh dans 2s...');"
        . "document.getElementById('statusDot').style.background='#eab308';"
        . "_u('dEta','$etaText');"
        . "setTimeout(function(){ window.location.reload(); }, 2000);"
        . "</script>\n";
} else {
    echo "<script>"
        . "_u('statusText','TERMINE ! " . number_format($totalAthletes, 0, '', ' ') . " athletes traites.');"
        . "document.getElementById('statusDot').style.background='#22c55e';"
        . "document.getElementById('resume').innerHTML='<h2 style=\"color:#22c55e;text-align:center;margin:20px 0;\">TERMINE !</h2>';"
        . "</script>\n";
}
flush();
?>
</body>
</html>
