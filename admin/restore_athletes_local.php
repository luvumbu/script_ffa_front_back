<?php
/**
 * admin/restore_athletes_local.php — Restore one-shot de la table athletes en local depuis l'archive.
 * Utilisable en CLI : php admin/restore_athletes_local.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); die('CLI only'); }

require_once __DIR__ . '/../core/db.php';

$path = __DIR__ . '/../archives/athletes_2026-05-14_191601.jsonl';
if (!file_exists($path)) die("Archive introuvable: $path\n");

$fp = fopen($path, 'r');
$meta = null;
while (($line = fgets($fp)) !== false) {
    $line = trim($line);
    if ($line === '') continue;
    if (str_starts_with($line, '#META ')) { $meta = json_decode(substr($line, 6), true); break; }
    if ($line[0] !== '#') { fseek($fp, 0); break; }
}
$colsArchive = $meta['columns'] ?? null;
if (!$colsArchive) die("Pas de colonnes dans META\n");

// Colonnes existantes dans la table locale
$localCols = [];
$r = $conn->query("SHOW COLUMNS FROM athletes");
while ($row = $r->fetch_assoc()) $localCols[] = $row['Field'];

// Intersection : on n'insere que les colonnes qui existent en local
$cols = array_values(array_intersect($colsArchive, $localCols));
$skipped = array_values(array_diff($colsArchive, $localCols));
if (!empty($skipped)) echo "Colonnes archive IGNOREES (absentes en local): " . implode(', ', $skipped) . "\n";

echo "Restore athletes (" . count($cols) . "/" . count($colsArchive) . " colonnes) vers `" . $conn->query('SELECT DATABASE()')->fetch_row()[0] . "`\n";

$conn->query('SET unique_checks=0');
$conn->query('SET foreign_key_checks=0');
$conn->query('TRUNCATE TABLE athletes');

$colList = '`' . implode('`,`', $cols) . '`';
$rowPh   = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
$batchSize = 1000;
$batch = [];
$total = 0;
$t0 = microtime(true);

$flush = function() use (&$batch, &$total, $conn, $colList, $cols, $rowPh) {
    if (empty($batch)) return;
    $n = count($batch);
    $sql = "INSERT INTO athletes ($colList) VALUES " . implode(',', array_fill(0, $n, $rowPh));
    $stmt = $conn->prepare($sql);
    if (!$stmt) { echo "PREPARE FAIL: " . $conn->error . "\n"; $batch = []; return; }
    $flat = [];
    foreach ($batch as $row) foreach ($cols as $col) $flat[] = $row[$col] ?? null;
    $types = str_repeat('s', count($flat));
    $stmt->bind_param($types, ...$flat);
    if (!$stmt->execute()) echo "EXEC FAIL: " . $stmt->error . "\n";
    $stmt->close();
    $total += $n;
    $batch = [];
    if ($total % 20000 === 0) {
        $dt = microtime(true) - $t0 = microtime(true);
        echo "$total inserts...\n";
    }
};

while (($line = fgets($fp)) !== false) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    $row = json_decode($line, true);
    if (!$row) continue;
    $batch[] = $row;
    if (count($batch) >= $batchSize) $flush();
}
$flush();
$conn->query('SET foreign_key_checks=1');
$conn->query('SET unique_checks=1');
fclose($fp);

$elapsed = round(microtime(true) - $t0, 1);
echo "DONE. $total lignes inserees en {$elapsed}s\n";
echo "COUNT en BDD: " . $conn->query('SELECT COUNT(*) FROM athletes')->fetch_row()[0] . "\n";
