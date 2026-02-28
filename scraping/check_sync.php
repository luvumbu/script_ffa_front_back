<?php
/**
 * check_sync.php — Vérifie src/ via nom_et_liens + scrape automatiquement les absents
 *
 * Phase 1 : vérifie tous les fichiers, génère absents2.json (une seule fois)
 * Phase 2 : scrape les absents par batch de 7, pause 1s, refresh auto (comme index.php)
 */
set_time_limit(0);
ini_set('memory_limit', '512M');
ob_implicit_flush(true);
session_start();

$TIME_LIMIT = 25;
$PARALLEL   = 7;

require_once dirname(__DIR__) . '/core/credentials.php';

require_once dirname(__DIR__) . "/Class/DatabaseHandler.php";
require_once dirname(__DIR__) . "/Class/AthleteScraper.php";
require_once dirname(__DIR__) . "/core/insert_athle.php";
require_once dirname(__DIR__) . '/core/db.php';

require_once __DIR__ . "/scrape_functions.php";

$srcDir = dirname(__DIR__) . '/src';
if (!is_dir($srcDir)) mkdir($srcDir, 0755);

$absentsFile  = dirname(__DIR__) . '/absents2.json';
$progressFile = dirname(__DIR__) . '/progress_absents.txt';

// =============================================
// Déterminer la phase
// Si absents2.json existe et progress_absents.txt existe → Phase 2 directement
// Sinon → Phase 1 puis Phase 2
// =============================================
$skipPhase1 = false;
if (file_exists($absentsFile) && file_exists($progressFile)) {
    $absentsData = json_decode(file_get_contents($absentsFile), true);
    if ($absentsData && !empty($absentsData['absents'])) {
        $skipPhase1 = true;
    }
}

// Reset si demandé
if (isset($_GET['reset'])) {
    @unlink($absentsFile);
    @unlink($progressFile);
    @unlink(dirname(__DIR__) . '/failed_absents.json');
    unset($_SESSION['abs_idx']);
    header("Location: check_sync.php?reseted=1");
    exit;
}

$justReseted = isset($_GET['reseted']);

if (!$skipPhase1) {
    // =============================================
    // PHASE 1 : Vérification
    // =============================================
    $res = $conn->query("SELECT COUNT(*) as c FROM nom_et_liens");
    $total = (int) $res->fetch_assoc()['c'];
    $batchSize = 5000;
    $offset = 0;
    $absents = [];
    $presents = 0;
    $traites = 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Phase 1 — Vérification <?= number_format($total, 0, '', ' ') ?> URLs</title>
<link rel="stylesheet" href="../common.css">
<style>
.progress-bar { background:linear-gradient(90deg,#8b5cf6,#a78bfa,#c4b5fd); }
</style>
</head>
<body class="panel-body">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
    <h1>Phase 1 — <span>Vérification</span></h1>
    <a class="reset" href="?reset=1">Reset</a>
</div>
<div class="subtitle"><?= number_format($total, 0, '', ' ') ?> URLs à vérifier dans nom_et_liens</div>

<?php if ($justReseted): ?>
<div style="background:#fbbf2415;border:1px solid #fbbf2430;border-radius:8px;padding:12px 20px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
    <span style="font-size:20px;">&#9888;</span>
    <span style="color:#fbbf24;font-size:14px;">Reset effectué — absents2.json, progress et échecs supprimés — relance depuis zéro</span>
</div>
<?php endif; ?>

<div class="stats">
    <div class="stat" style="background:#60a5fa10;border:1px solid #60a5fa25;">
        <div class="val" style="color:#60a5fa;" id="s-total">0</div>
        <div class="label">Vérifiés</div>
    </div>
    <div class="stat" style="background:#34d39910;border:1px solid #34d39925;">
        <div class="val" style="color:#34d399;" id="s-ok">0</div>
        <div class="label">Présents</div>
    </div>
    <div class="stat" style="background:#f8717110;border:1px solid #f8717125;">
        <div class="val" style="color:#f87171;" id="s-ko">0</div>
        <div class="label">Absents</div>
    </div>
</div>

<div class="progress-wrap">
    <div class="progress-bar" id="pbar" style="width:0%"></div>
    <div class="progress-text" id="ptxt">0 / <?= number_format($total, 0, '', ' ') ?></div>
</div>

<div class="log" id="log"></div>

<?php ob_flush(); flush(); ?>

<?php
    while ($offset < $total) {
        $res = $conn->query("SELECT id_nom_et_liens, url FROM nom_et_liens ORDER BY id_nom_et_liens ASC LIMIT $batchSize OFFSET $offset");

        while ($row = $res->fetch_assoc()) {
            $traites++;
            $url = $row['url'];

            if (preg_match('#/athletes/(\d+)/#', $url, $m)) {
                $numero = $m[1];
            } else {
                continue;
            }

            $file = $srcDir . '/' . $numero . '.php';

            if (file_exists($file)) {
                $presents++;
            } else {
                $absents[] = [
                    'id_nom_et_liens' => (int) $row['id_nom_et_liens'],
                    'numero'          => (int) $numero,
                    'url'             => $url,
                ];

                echo "<script>
                    var l=document.getElementById('log');
                    l.innerHTML+='<div><span style=\"color:#f87171\">ABSENT</span> — fichier : <span style=\"color:#a78bfa;font-weight:bold\">src/{$numero}.php</span></div>';
                    l.scrollTop=l.scrollHeight;
                </script>\n";
                ob_flush(); flush();
            }

            if ($traites % 500 === 0) {
                $pct = round(($traites / $total) * 100, 1);
                echo "<script>
                    document.getElementById('s-total').textContent='".number_format($traites,0,'',' ')."';
                    document.getElementById('s-ok').textContent='".number_format($presents,0,'',' ')."';
                    document.getElementById('s-ko').textContent='".count($absents)."';
                    document.getElementById('pbar').style.width='{$pct}%';
                    document.getElementById('ptxt').textContent='".number_format($traites,0,'',' ')." / ".number_format($total,0,'',' ')."';
                </script>\n";
                ob_flush(); flush();
            }
        }

        $offset += $batchSize;
    }

    $nbAbsents = count($absents);

    // Sauvegarder absents2.json
    $outputJson = [
        'generated_at'   => date('Y-m-d H:i:s'),
        'source'         => 'nom_et_liens',
        'total_verifies' => $traites,
        'total_presents' => $presents,
        'total_absents'  => $nbAbsents,
        'absents'        => $absents,
    ];
    file_put_contents($absentsFile, json_encode($outputJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    file_put_contents($progressFile, '0');

    if ($nbAbsents === 0) {
        echo "<script>
            document.getElementById('pbar').style.width='100%';
            document.getElementById('ptxt').textContent='".number_format($traites,0,'',' ')." / ".number_format($total,0,'',' ')."';
        </script>";
        echo "<div style='background:#34d39915;border:1px solid #34d39930;border-radius:10px;padding:20px;text-align:center;margin-top:20px;'>";
        echo "<h2 style='color:#34d399;font-size:18px;'>Tous les fichiers sont présents</h2>";
        echo "<p style='color:#8b949e;font-size:13px;'>Aucun scraping nécessaire.</p></div>";
        $conn->close();
        echo "</body></html>";
        exit;
    }

    // Phase 1 finie → refresh pour lancer Phase 2
    echo "<script>
        document.getElementById('s-total').textContent='".number_format($traites,0,'',' ')."';
        document.getElementById('s-ok').textContent='".number_format($presents,0,'',' ')."';
        document.getElementById('s-ko').textContent='{$nbAbsents}';
        document.getElementById('pbar').style.width='100%';
        document.getElementById('ptxt').textContent='".number_format($traites,0,'',' ')." / ".number_format($total,0,'',' ')."';
        var l=document.getElementById('log');
        l.innerHTML+='<div style=\"color:#34d399;font-weight:bold;margin:8px 0\">--- Phase 1 terminée : {$nbAbsents} absents — lancement du scraping dans 2s ---</div>';
        l.scrollTop=l.scrollHeight;
        setTimeout(function(){ window.location.href='check_sync.php'; }, 2000);
    </script>\n";
    $conn->close();
    echo "</body></html>";
    exit;

} // fin Phase 1

// =============================================
// PHASE 2 : Scraping des absents (comme index.php)
// =============================================
$absentsData = json_decode(file_get_contents($absentsFile), true);
$absentsList = $absentsData['absents'];
$nbAbsents   = count($absentsList);

$idx = file_exists($progressFile) ? (int) file_get_contents($progressFile) : 0;

$done = $idx;
$remaining = $nbAbsents - $idx;
$pct = $nbAbsents > 0 ? round(($done / $nbAbsents) * 100, 2) : 0;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Phase 2 — Scraping <?= $remaining ?> absents</title>
<link rel="stylesheet" href="../common.css">
<style>
.progress-bar { background:linear-gradient(90deg,#6366f1,#34d399); }
.reset { margin-top:16px; }
</style>
</head>
<body class="panel-body">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
    <h1>Phase 2 — <span>Scraping des absents</span></h1>
    <a class="reset" href="?reset=1">Reset</a>
</div>
<div class="subtitle"><?= number_format($nbAbsents, 0, '', ' ') ?> absents au total — <?= number_format($done, 0, '', ' ') ?> déjà traités — <?= number_format($remaining, 0, '', ' ') ?> restants — batch de <?= $PARALLEL ?></div>

<div class="stats">
    <div class="stat" style="background:#60a5fa10;border:1px solid #60a5fa25;">
        <div class="val" style="color:#60a5fa;" id="s-done"><?= number_format($done, 0, '', ' ') ?></div>
        <div class="label">Traités</div>
    </div>
    <div class="stat" style="background:#34d39910;border:1px solid #34d39925;">
        <div class="val" style="color:#34d399;" id="s-scrape">0</div>
        <div class="label">Scrapés</div>
    </div>
    <div class="stat" style="background:#fbbf2410;border:1px solid #fbbf2425;">
        <div class="val" style="color:#fbbf24;" id="s-fail">0</div>
        <div class="label">Échecs</div>
    </div>
    <div class="stat" style="background:#f8717110;border:1px solid #f8717125;">
        <div class="val" style="color:#f87171;" id="s-remain"><?= number_format($remaining, 0, '', ' ') ?></div>
        <div class="label">Restants</div>
    </div>
</div>

<div class="progress-wrap">
    <div class="progress-bar" id="pbar" style="width:<?= $pct ?>%"></div>
    <div class="progress-text" id="ptxt"><?= number_format($done, 0, '', ' ') ?> / <?= number_format($nbAbsents, 0, '', ' ') ?></div>
</div>

<div class="log" id="log"></div>

<?php ob_flush(); flush(); ?>

<?php
$databaseHandler = new DatabaseHandler($dbname, $username, $password);
require_once dirname(__DIR__) . "/core/dbCheck_athle.php";
$connScrape = $databaseHandler->connection;
$cache = loadRefCache($connScrape);

$scraped = 0;
$failed = 0;
$pageStart = microtime(true);
$batchDone = 0;

while ($idx < $nbAbsents) {

    if ((microtime(true) - $pageStart) > $TIME_LIMIT) break;

    if (!$connScrape->ping()) {
        $connScrape = new mysqli($databaseHandler->servername, $username, $password, $dbname);
        $connScrape->set_charset("utf8");
    }

    // Collecter le batch de 7 (skip ceux déjà scrapés)
    $batch = [];
    $batchIdx = [];
    $tempIdx = $idx;
    $skipped = 0;
    while (count($batch) < $PARALLEL && $tempIdx < $nbAbsents) {
        $num = (int) $absentsList[$tempIdx]['numero'];
        if (file_exists($srcDir . '/' . $num . '.php')) {
            $skipped++;
            $tempIdx++;
            continue;
        }
        $batch[] = $num;
        $batchIdx[] = $tempIdx;
        $tempIdx++;
    }
    if ($skipped > 0) {
        echo "<script>
            var l=document.getElementById('log');
            l.innerHTML+='<div style=\"color:#8b949e\">{$skipped} déjà présents — ignorés</div>';
            l.scrollTop=l.scrollHeight;
        </script>\n";
        ob_flush(); flush();
    }

    if (empty($batch)) break;

    // Scraping parallèle
    $t0 = microtime(true);
    $pagesResults = scrapeParallel($batch);
    $scrapeTime = round((microtime(true) - $t0) * 1000);

    echo "<script>
        var l=document.getElementById('log');
        l.innerHTML+='<div style=\"color:cyan\">" . count($batch) . " athletes scrapés en {$scrapeTime}ms</div>';
        l.scrollTop=l.scrollHeight;
    </script>\n";
    ob_flush(); flush();

    // Traiter chaque athlète
    foreach ($batch as $athleteIdExt) {
        $pages = $pagesResults[$athleteIdExt] ?? [];

        if (empty($pages['bilans'])) {
            $failed++;
            echo "<script>
                var l=document.getElementById('log');
                l.innerHTML+='<div><span style=\"color:#fbbf24\">ÉCHEC</span> — athlete {$athleteIdExt} : bilans vide</div>';
                l.scrollTop=l.scrollHeight;
                document.getElementById('s-fail').textContent='{$failed}';
            </script>\n";
            ob_flush(); flush();

            $failFile = dirname(__DIR__) . "/failed_absents.json";
            $fails = file_exists($failFile) ? json_decode(file_get_contents($failFile), true) : [];
            $fails[] = ['numero' => $athleteIdExt, 'date' => date('Y-m-d H:i:s')];
            file_put_contents($failFile, json_encode($fails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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

            $nom = addslashes(htmlspecialchars($scraper->identite['nom_complet'] ?? '?'));

            // JSON → src/
            $data = $scraper->toArray();
            $jsonString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $jsonPath = $srcDir . "/" . $athleteIdExt . ".php";
            $phpContent = "<?php\nheader(\"Access-Control-Allow-Origin: *\");\nheader(\"Content-Type: application/json; charset=utf-8\");\n?>\n" . $jsonString;
            file_put_contents($jsonPath, $phpContent);

            // BDD
            insertAthleteData($scraper, $connScrape, $cache);

            $scraped++;
            $batchDone++;
            echo "<script>
                var l=document.getElementById('log');
                l.innerHTML+='<div><span style=\"color:#34d399\">OK</span> — <span style=\"color:#a78bfa;font-weight:bold\">src/{$athleteIdExt}.php</span> — {$nom}</div>';
                l.scrollTop=l.scrollHeight;
            </script>\n";
            ob_flush(); flush();

        } catch (Exception $e) {
            $failed++;
            $err = addslashes(htmlspecialchars($e->getMessage()));
            echo "<script>
                var l=document.getElementById('log');
                l.innerHTML+='<div><span style=\"color:#f87171\">ERREUR</span> — athlete {$athleteIdExt} : {$err}</div>';
                l.scrollTop=l.scrollHeight;
                document.getElementById('s-fail').textContent='{$failed}';
            </script>\n";
            ob_flush(); flush();

            $failFile = dirname(__DIR__) . "/failed_absents.json";
            $fails = file_exists($failFile) ? json_decode(file_get_contents($failFile), true) : [];
            $fails[] = ['numero' => $athleteIdExt, 'erreur' => $e->getMessage(), 'date' => date('Y-m-d H:i:s')];
            file_put_contents($failFile, json_encode($fails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    // Sauvegarder progression
    $idx = $tempIdx;
    file_put_contents($progressFile, $idx);

    // Mise à jour UI
    $currentDone = $idx;
    $currentRemain = $nbAbsents - $idx;
    $pctNow = round(($idx / $nbAbsents) * 100, 1);
    echo "<script>
        document.getElementById('s-done').textContent='".number_format($currentDone,0,'',' ')."';
        document.getElementById('s-scrape').textContent='{$scraped}';
        document.getElementById('s-remain').textContent='".number_format($currentRemain,0,'',' ')."';
        document.getElementById('pbar').style.width='{$pctNow}%';
        document.getElementById('ptxt').textContent='".number_format($currentDone,0,'',' ')." / ".number_format($nbAbsents,0,'',' ')."';
    </script>\n";
    ob_flush(); flush();

    // Pause 1s entre chaque batch
    sleep(1);
}

$databaseHandler->closeConnection();
$conn->close();

$remaining = $nbAbsents - $idx;
$pageTime = round(microtime(true) - $pageStart, 1);

if ($remaining > 0):
    // ETA
    $etaSec = $batchDone > 0 ? round(($remaining * $pageTime) / $batchDone) : 0;
    $etaH = floor($etaSec / 3600);
    $etaMin = floor(($etaSec % 3600) / 60);

    echo "<script>
        var l=document.getElementById('log');
        l.innerHTML+='<div style=\"color:cyan;margin:8px 0\">{$scraped} scrapés en {$pageTime}s — {$remaining} restants — ETA ~{$etaH}h {$etaMin}min — refresh...</div>';
        l.scrollTop=l.scrollHeight;
        setTimeout(function(){ window.location.href='check_sync.php'; }, 1000);
    </script>\n";
?>
<div class="done" style="background:#60a5fa15;border:1px solid #60a5fa30;">
    <h2 style="color:#60a5fa;">En cours...</h2>
    <p><?= $scraped ?> scrapés cette page — <?= number_format($remaining, 0, '', ' ') ?> restants — ETA ~<?= $etaH ?>h <?= $etaMin ?>min</p>
</div>
<?php else:
    // Terminé → nettoyer progression
    @unlink($progressFile);
?>
<script>
document.getElementById('pbar').style.width='100%';
document.getElementById('ptxt').textContent='<?= number_format($nbAbsents,0,'',' ') ?> / <?= number_format($nbAbsents,0,'',' ') ?>';
document.getElementById('s-remain').textContent='0';
var l=document.getElementById('log');
l.innerHTML+='<div style="color:#34d399;font-weight:bold;margin:8px 0">--- Terminé ---</div>';
l.scrollTop=l.scrollHeight;
</script>
<div class="done" style="background:#34d39915;border:1px solid #34d39930;">
    <h2 style="color:#34d399;">Terminé</h2>
    <p><?= number_format($nbAbsents, 0, '', ' ') ?> absents traités — <?= $scraped ?> scrapés — <?= $failed ?> échecs</p>
</div>
<?php endif; ?>

</body>
</html>
