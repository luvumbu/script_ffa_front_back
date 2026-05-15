<?php
if (PHP_SAPI !== 'cli') die('cli only');
$ctx = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]);
$b = @file_get_contents('https://bokonzi.com/admin/db_archive.php?bk_key=bk_s3cr3t_2026_xK9mP', false, $ctx);

echo "Page db_archive prod : " . strlen($b) . " bytes\n\n";

preg_match_all('/athlete_progressions_[\d_-]+\.jsonl/', $b, $m);
$files = array_unique($m[0]);
echo "Fichiers archives progressions trouves sur prod : " . count($files) . "\n";
foreach ($files as $f) echo "  $f\n";

echo "\n";
// Cherche aussi _live
if (strpos($b, 'athlete_progressions_live.jsonl') !== false) {
    echo "[OK] athlete_progressions_live.jsonl present sur prod\n";
} else {
    echo "athlete_progressions_live.jsonl ABSENT sur prod\n";
}

// Check data_source
if (strpos($b, "Fichier") !== false) {
    echo "\nRecherche du mode actif pour athlete_progressions...\n";
    if (preg_match('/athlete_progressions.*?Source[^<]*<[^>]+>([^<]+)/i', $b, $mm)) {
        echo "  Mode dans la page : " . trim($mm[1]) . "\n";
    }
}
