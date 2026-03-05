<?php
/**
 * index2.php — Scraping PARALLÈLE + JSON + BDD (autonome)
 *
 * Scrape 5 athlètes en même temps (curl_multi) → JSON → BDD → Refresh
 * 300k athlètes : ~3.5 jours au lieu de 17
 */

ob_start();
session_start();

$TIME_LIMIT  = 25;  // secondes max par page
$PARALLEL    = 7;   // athlètes scrapés en parallèle

require_once dirname(__DIR__) . "/core/credentials.php";

require_once dirname(__DIR__) . "/Class/DatabaseHandler.php";
require_once dirname(__DIR__) . "/Class/AthleteScraper.php";
require_once dirname(__DIR__) . "/core/insert_athle.php";

require_once __DIR__ . "/scrape_functions.php";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scraping Athle.fr</title>
    <style>
        body { background-color: black; color: green; font-family: monospace; }
        .timer { color: cyan; }
        .error { color: red; }
        .skip { color: orange; }
    </style>
</head>
<body>

<?php
// Reset progression si demande
if (isset($_GET['reset_to'])) {
    $resetVal = max(0, (int)$_GET['reset_to']);
    $progressFile = dirname(__DIR__) . "/progress.txt";
    file_put_contents($progressFile, $resetVal);
    unset($_SESSION["url"]);
    echo "<p style='color:lime;font-size:18px;'>Progression reinitialise a <b>$resetVal</b></p>";
    echo "<script>setTimeout(function(){ window.location.href = window.location.pathname; }, 1000);</script>";
    echo "</body></html>";
    exit;
}
?>

<!-- Reset progression -->
<div style="margin:10px 0;padding:10px;background:#111;border:1px solid #333;border-radius:6px;display:inline-flex;gap:8px;align-items:center;">
    <form method="GET" style="display:flex;gap:6px;align-items:center;">
        <label style="color:cyan;font-size:13px;">Reprendre a :</label>
        <input type="number" name="reset_to" value="0" min="0" style="width:100px;padding:4px 8px;background:#222;border:1px solid #555;color:#fff;border-radius:4px;font-family:monospace;">
        <button type="submit" style="padding:4px 12px;background:#333;border:1px solid orange;color:orange;border-radius:4px;cursor:pointer;font-family:monospace;">Reset</button>
    </form>
    <form method="GET" style="display:inline;"><input type="hidden" name="reset_to" value="0"><button type="submit" style="padding:4px 12px;background:#333;border:1px solid red;color:red;border-radius:4px;cursor:pointer;font-family:monospace;">Tout reprendre (0)</button></form>
</div>

<?php
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
        die("<p class='error'>Erreur : " . $result['message'] . "</p>");
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

// Charger tous les athlete_id_externe deja en BDD → skip sans scraper
$existingAthletes = [];
$rExist = $conn->query("SELECT athlete_id_externe FROM athletes");
if ($rExist) while ($row = $rExist->fetch_assoc()) $existingAthletes[(int)$row['athlete_id_externe']] = true;
$nbExisting = count($existingAthletes);
echo "<p style='color:cyan;'>$nbExisting athletes deja en BDD (seront ignores)</p>";

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

$pct = $totalAthletes > 0 ? round(($done / $totalAthletes) * 100, 2) : 0;
echo "<h2 style='color:red'>$done / $totalAthletes traites ($pct%) | Parallele : $PARALLEL</h2>";
echo "<div style='background:#333;border-radius:8px;overflow:hidden;height:30px;margin:10px 0;'>";
echo "<div style='background:red;height:100%;width:{$pct}%;display:flex;align-items:center;justify-content:center;font-weight:bold;color:#fff;transition:width 0.3s;min-width:40px;'>{$pct}%</div>";
echo "</div>";

// =============================================
// 4. Boucle — par batch de $PARALLEL athlètes
// =============================================
$srcDir = dirname(__DIR__) . "/src";
if (!is_dir($srcDir)) mkdir($srcDir, 0755);

$pageStart = microtime(true);
$batchDone = 0;

while ($id <= $maxId) {

    if ((microtime(true) - $pageStart) > $TIME_LIMIT) break;

    // Vérifier la connexion MySQL
    if (!$conn->ping()) {
        $conn = new mysqli($databaseHandler->servername, $username, $password, $dbname);
        $conn->set_charset("utf8");
    }

    // Collecter les N prochains athlètes valides
    $batch = []; // [id_nom_et_liens => athlete_id_from_url]
    $batchUrls = []; // [id_nom_et_liens => full_url]
    $tempId = $id;

    while (count($batch) < $PARALLEL && $tempId <= $maxId) {
        if (!isset($allUrls[$tempId])) {
            $tempId++;
            continue;
        }
        $url = $allUrls[$tempId]["url"] ?? null;
        if (empty($url)) {
            echo "<p class='skip'>#$tempId : skip</p>";
            $tempId++;
            continue;
        }
        // Extraire l'ID athlète depuis l'URL
        if (preg_match('#/athletes/(\d+)#', $url, $m)) {
            $athId = (int)$m[1];
            // Skip si deja present en BDD
            if (isset($existingAthletes[$athId])) {
                $tempId++;
                continue;
            }
            $batch[$tempId] = $athId;
            $batchUrls[$tempId] = $url;
        }
        $tempId++;
    }

    if (empty($batch)) {
        $id = $tempId;
        $_SESSION["url"] = $id;
        file_put_contents($progressFile, $id);
        break;
    }

    // --- Scraping parallèle (5 athlètes × 3 pages = 15 requêtes en même temps) ---
    $t0 = microtime(true);
    $pagesResults = scrapeParallel(array_values($batch));
    $scrapeTime = round((microtime(true) - $t0) * 1000);

    echo "<p class='timer'>Scrape " . count($batch) . " athletes en {$scrapeTime}ms</p>";

    // --- Traiter chaque athlète ---
    foreach ($batch as $idNom => $athleteIdExt) {
        $pages = $pagesResults[$athleteIdExt] ?? [];

        if (empty($pages['bilans'])) {
            $debugUrl = "https://athle.fr/athletes/" . $athleteIdExt . "/bilans";
            echo "<p class='error'>#$idNom (athlete $athleteIdExt) : page bilans vide → skip | <a href='$debugUrl' target='_blank' style='color:yellow'>tester</a></p>";

            // Sauvegarder dans le JSON des échecs
            $failFile = dirname(__DIR__) . "/failed.json";
            $fails = file_exists($failFile) ? json_decode(file_get_contents($failFile), true) : [];
            $fails[] = ['id_nom_et_liens' => $idNom, 'athlete_id' => $athleteIdExt, 'date' => date('Y-m-d H:i:s')];
            file_put_contents($failFile, json_encode($fails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            continue;
        }

        try {
            // Créer le scraper et injecter les pages déjà téléchargées
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

            echo "<p>#$idNom $nom</p>";
            $batchDone++;

        } catch (Exception $e) {
            echo "<p class='error'>#$idNom erreur : " . htmlspecialchars($e->getMessage()) . "</p>";

            $failFile = dirname(__DIR__) . "/failed.json";
            $fails = file_exists($failFile) ? json_decode(file_get_contents($failFile), true) : [];
            $fails[] = ['id_nom_et_liens' => $idNom, 'athlete_id' => $athleteIdExt, 'erreur' => $e->getMessage(), 'date' => date('Y-m-d H:i:s')];
            file_put_contents($failFile, json_encode($fails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    // Avancer la progression après le batch
    $id = $tempId;
    $_SESSION["url"] = $id;
    file_put_contents($progressFile, $id);

    echo "<hr/>";

    // Pause 1s entre chaque batch pour ne pas surcharger athle.fr
    sleep(1);
}

// =============================================
// 5. Résumé + refresh
// =============================================
$databaseHandler->closeConnection();

$pageTime = round(microtime(true) - $pageStart, 1);
$totalDone = $done + $batchDone;
$remaining = $totalAthletes - $totalDone;

echo "<p class='timer'>$batchDone athletes en {$pageTime}s | Total : $totalDone / $totalAthletes | Restant : $remaining</p>";

if ($id <= $maxId) {
    $etaSec = $batchDone > 0 ? round(($remaining * $pageTime) / $batchDone) : 0;
    $etaH = floor($etaSec / 3600);
    $etaMin = floor(($etaSec % 3600) / 60);
    echo "<p class='timer'>ETA : ~{$etaH}h {$etaMin}min</p>";
    header("Refresh: 1");
} else {
    echo "<h2>TERMINE ! $totalAthletes athletes traites.</h2>";
}
?>

</body>
</html>
