<?php
/**
 * admin/progressions_init.php — Prepare le store fichier pour athlete_progressions.
 *
 * 1) Copie la derniere archive .jsonl en archives/athlete_progressions_live.jsonl
 * 2) Construit l'index sharde dans archives/.prog_idx/
 *
 * Idempotent : si _live.jsonl existe deja, ne re-copie pas. L'index est toujours reconstruit.
 *
 * Usage :
 *   CLI : php admin/progressions_init.php
 *   Web : https://bokonzi.com/admin/progressions_init.php?bk_key=bk_s3cr3t_2026_xK9mP
 */
$_isWeb = (PHP_SAPI !== 'cli');
if ($_isWeb) {
    $key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
    if ($key !== 'bk_s3cr3t_2026_xK9mP') { http_response_code(403); die('Interdit'); }
    header('Content-Type: text/plain; charset=utf-8');
    // Stream le buffer au fur et a mesure pour voir la progression
    @ini_set('output_buffering', '0');
    @ini_set('implicit_flush', '1');
    while (ob_get_level()) ob_end_flush();
    ob_implicit_flush(true);
}
@set_time_limit(0);
@ini_set('memory_limit', '1G');
@ignore_user_abort(true);

require_once __DIR__ . '/../core/progressions_store.php';

$base   = dirname(__DIR__);
$archD  = $base . '/archives';
$idxDir = $archD . '/.prog_idx';
$pointerFile = $idxDir . '/source.txt';

function _fmt($n) { return number_format($n, 0, '.', ' '); }
function _mb($b)  { return number_format($b / 1024 / 1024, 1, '.', ' ') . ' MB'; }

// ════════════════════════════════════════════════════════════
// 1) Detection du fichier source (sans copie)
// ════════════════════════════════════════════════════════════
if (!is_dir($idxDir)) @mkdir($idxDir, 0755, true);

// Si pas de pointer existant, on choisit le fichier le plus recent
if (!file_exists($pointerFile)) {
    $candidates = glob($archD . '/athlete_progressions_*.jsonl');
    if (empty($candidates)) die("Aucun fichier archives/athlete_progressions_*.jsonl trouve\n");
    usort($candidates, function($a, $b) { return filemtime($b) - filemtime($a); });
    $chosen = basename($candidates[0]);
    @file_put_contents($pointerFile, $chosen);
    echo "Fichier source choisi : $chosen (" . _mb(filesize($candidates[0])) . ")\n";
    echo "Pointer ecrit dans " . basename($pointerFile) . "\n\n";
} else {
    $chosen = trim(@file_get_contents($pointerFile));
    echo "Fichier source (depuis pointer) : $chosen\n\n";
}

$livePt = $archD . '/' . $chosen;
if (!file_exists($livePt)) die("[ERREUR] Le fichier $chosen est introuvable\n");

// ════════════════════════════════════════════════════════════
// 2) Reconstruction de l'index sharde
// ════════════════════════════════════════════════════════════
echo "Construction de l'index sharde (256 shards)...\n";

// Nettoie les anciens shards
foreach (glob($idxDir . '/*.json') as $f) @unlink($f);

$fp = fopen($livePt, 'rb');
if (!$fp) die("Impossible d'ouvrir $livePt\n");

$shards = []; // shard => {id_athlete => [offsets]}
$nLines = 0; $nValid = 0; $nMarkers = 0;
$offset = 0;
$t0 = microtime(true);
$lastReport = 0;
$totalSize = filesize($livePt);

while (($line = fgets($fp)) !== false) {
    $lineLen = strlen($line);
    $trimmed = ltrim($line);
    if ($trimmed === '' || $trimmed[0] === '#') { $offset += $lineLen; continue; }

    // Parse JSON
    $row = json_decode(trim($line), true);
    if (is_array($row)) {
        $idA = (int)($row['id_athlete'] ?? 0);
        if ($idA > 0) {
            $shard = $idA & 0xFF;
            $shards[$shard][(string)$idA][] = $offset;
            $nValid++;
            if (!empty($row['_op']) && $row['_op'] === 'delete') $nMarkers++;
        }
    }
    $nLines++;
    $offset += $lineLen;

    if ($offset - $lastReport > 200 * 1024 * 1024) {
        $pct = round($offset / $totalSize * 100, 1);
        $rate = $offset / max(0.01, microtime(true) - $t0) / 1024 / 1024;
        echo "  scan : $pct% (" . _fmt($nLines) . " lignes, " . round($rate) . " MB/s, RAM " . _mb(memory_get_usage(true)) . ")\n";
        $lastReport = $offset;
    }
}
fclose($fp);

echo "Scan termine en " . round(microtime(true) - $t0, 1) . "s\n";
echo "Lignes lues : " . _fmt($nLines) . " (" . _fmt($nValid) . " valides, " . _fmt($nMarkers) . " markers delete)\n";

// Ecriture des shards
echo "Ecriture de " . count($shards) . " shards...\n";
$t1 = microtime(true);
foreach ($shards as $shard => $data) {
    $path = $idxDir . '/' . $shard . '.json';
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}
echo "Ecriture terminee en " . round(microtime(true) - $t1, 1) . "s\n";

// Stats finales
$idxTotal = 0;
foreach (glob($idxDir . '/*.json') as $f) $idxTotal += filesize($f);
echo "\nIndex total : " . _mb($idxTotal) . " sur " . count(glob($idxDir . '/*.json')) . " shards\n";
echo "Athletes indexes : " . _fmt(array_sum(array_map('count', $shards))) . "\n";
echo "\nOK. Le store est pret. Pour activer :\n";
echo "  echo '{\"athlete_progressions\":\"file\"}' > config/data_source.json\n";
echo "Puis tester avec un athlete.\n";
