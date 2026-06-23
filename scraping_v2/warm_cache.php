<?php
/**
 * warm_cache.php — Pre-chauffe le cache des stats par annee (incremental, anti-timeout).
 *
 * Les requetes GROUP BY sur longtext mettent ~10-20s par table sur Hostinger.
 * 13 tables = ~2-4 min total, ce qui peut depasser le timeout PHP.
 *
 * Strategie : 1 table par requete HTTP. Auto-refresh pour passer a la suivante.
 * Apres la 13eme : ecrit le cache final + propose le lien vers par_annee.php.
 *
 * URL : https://bokonzi.com/scraping_v2/warm_cache.php
 */

@ini_set('max_execution_time', 30);
@set_time_limit(30);

require_once dirname(__DIR__) . '/core/db.php';
require __DIR__ . '/_guard.php';
require_once __DIR__ . '/lib/SourceTableReader.php';

$tempFile  = __DIR__ . '/state/stats_warming.json';
$finalFile = __DIR__ . '/state/stats_par_annee.json';
$stateDir  = dirname($tempFile);
if (!is_dir($stateDir)) @mkdir($stateDir, 0755, true);

if (isset($_GET['reset'])) {
    @unlink($tempFile);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$reader = new SourceTableReader($conn);
$tables = $reader->listerTables();

// Charger l'etat en cours
$state = file_exists($tempFile) ? json_decode(file_get_contents($tempFile), true) : ['index' => 0, 'agg' => []];
$index = (int)($state['index'] ?? 0);
$agg = $state['agg'] ?? [];

$p = 'u489596434_bokonzi_on';
$colEpr  = $p . '_epreuve';
$colPage = $p . '_page_total';

$done = ($index >= count($tables));

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Warm cache</title>";
echo "<style>body{background:#0a0e1a;color:#e0e0e0;font-family:monospace;padding:24px;}";
echo ".bar{height:18px;background:#1a2540;border-radius:9px;overflow:hidden;margin:12px 0;}";
echo ".bar-i{height:100%;background:linear-gradient(90deg,#34d399,#a29bfe);}";
echo "pre{background:#111830;padding:12px;border-radius:6px;}</style></head><body>";
echo "<h1>Warming cache (incremental)</h1>";

$pct = count($tables) > 0 ? round($index / count($tables) * 100, 1) : 0;
echo "<div>Table $index / " . count($tables) . " ($pct%)</div>";
echo "<div class='bar'><div class='bar-i' style='width:$pct%;'></div></div>";

if (!$done) {
    $t = $tables[$index];
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $t['nom']);
    echo "<p>Traitement : <b>$tableSafe</b>...</p>";
    if (ob_get_level()) { ob_flush(); flush(); }

    $t0 = microtime(true);
    $sql = "SELECT TRIM(SUBSTRING_INDEX(`$colEpr`, '|', 1)) AS annee,
                   COUNT(*) AS urls,
                   SUM(`$colPage`) AS pages
            FROM `$tableSafe`
            GROUP BY annee";
    $r = $conn->query($sql);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $an = (int)$row['annee'];
            if ($an < 1900 || $an > 2100) continue;
            if (!isset($agg[$an])) $agg[$an] = ['urls' => 0, 'pages' => 0, 'tables' => []];
            $agg[$an]['urls']   += (int)$row['urls'];
            $agg[$an]['pages']  += (int)$row['pages'];
            $agg[$an]['tables'][] = $t['nom'];
        }
    }
    $dur = round(microtime(true) - $t0, 1);
    echo "<p style='color:#34d399;'>OK en {$dur}s</p>";

    $state['index'] = $index + 1;
    $state['agg'] = $agg;
    file_put_contents($tempFile, json_encode($state, JSON_UNESCAPED_UNICODE));

    if ($index + 1 >= count($tables)) {
        // Toutes les tables faites → ecrit le cache final
        krsort($agg);
        file_put_contents($finalFile, json_encode($agg, JSON_UNESCAPED_UNICODE));
        @unlink($tempFile);
        echo "<p style='color:#34d399;font-size:18px;'>CACHE COMPLET ECRIT.</p>";
        echo "<pre>";
        foreach ($agg as $annee => $info) {
            echo sprintf("  %d → %5d URLs, %6d pages, %d tables\n", $annee, $info['urls'], $info['pages'], count($info['tables']));
        }
        echo "</pre>";
        echo "<p><a style='color:#a78bfa;' href='par_annee.php'>→ Aller a par_annee.php</a></p>";
    } else {
        // Refresh pour la prochaine table
        echo "<script>setTimeout(function(){location.reload();}, 500);</script>";
        echo "<p style='color:#8b949e;'>Auto-refresh dans 0.5s pour la table suivante...</p>";
    }
} else {
    echo "<p style='color:#34d399;'>Cache deja complet.</p>";
    echo "<p><a style='color:#a78bfa;' href='par_annee.php'>→ Aller a par_annee.php</a></p>";
    echo "<p><a style='color:#fbbf24;' href='?reset=1'>Reset et refaire</a></p>";
}

echo "</body></html>";
