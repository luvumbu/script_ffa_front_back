<?php
if (PHP_SAPI !== 'cli') die('cli only');
$ctx = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true,
    'header' => "User-Agent: curl\r\n"]]);
$b = @file_get_contents('http://localhost/BK/athletes', false, $ctx);
echo "Page /athletes size: " . strlen($b) . "\n\n";

// Cherche les appels liste.php
preg_match_all('#/api/liste\.php[^"\'\s<]+#', $b, $m);
echo "URLs absolues:\n";
foreach (array_unique($m[0]) as $u) echo "  $u\n";

// Cherche les appels BASE_API + '/liste.php?...'
preg_match_all("#BASE_API\s*\+\s*['\"]\/liste\.php[^'\"]*#", $b, $m2);
echo "\nUsages JS BASE_API:\n";
foreach (array_unique($m2[0]) as $u) echo "  $u\n";

// Cherche les calls fetch
preg_match_all('#fetch\([^)]+liste[^)]+#', $b, $m3);
echo "\nFetches:\n";
foreach (array_slice(array_unique($m3[0]), 0, 10) as $u) echo "  " . substr($u, 0, 200) . "\n";

// Filtre actif ?
$cfgFile = __DIR__ . '/../logs/.athletes_filter.php';
if (file_exists($cfgFile)) {
    $raw = file_get_contents($cfgFile);
    $pos = strpos($raw, "\n");
    $cfg = $pos !== false ? json_decode(substr($raw, $pos + 1), true) : null;
    echo "\nFiltre admin (.athletes_filter.php):\n";
    echo "  " . json_encode($cfg, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "\n(.athletes_filter.php absent, comportement par defaut)\n";
}
