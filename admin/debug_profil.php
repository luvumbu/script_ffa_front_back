<?php
if (PHP_SAPI !== 'cli') die('cli only');
$ctx = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true,
    'header' => "User-Agent: Mozilla/5.0\r\n"]]);
$b = @file_get_contents('https://bokonzi.com/recherche?page=profil&id=809035', false, $ctx);

echo "Size HTML: " . strlen($b) . "\n\n";

echo "=== Mentions 'discipline' (max 200 chars autour) ===\n";
$pos = 0;
$nb = 0;
while (($pos = stripos($b, 'discipline', $pos)) !== false && $nb < 20) {
    $start = max(0, $pos - 120);
    $extract = substr($b, $start, 250);
    $extract = preg_replace('/\s+/', ' ', $extract);
    echo "  >>> " . $extract . "\n";
    $pos += 10;
    $nb++;
}

echo "\n=== Mentions 'epreuve' / 'épreuve' ===\n";
$pos = 0; $nb = 0;
while (($pos = stripos($b, 'preuve', $pos)) !== false && $nb < 20) {
    $start = max(0, $pos - 100);
    $extract = substr($b, $start, 200);
    $extract = preg_replace('/\s+/', ' ', $extract);
    echo "  >>> " . $extract . "\n";
    $pos += 6;
    $nb++;
}

echo "\n=== Mentions 'Polyvalent' ou 'specialise' ===\n";
foreach (['Polyvalent', 'sp', 'pécialis'] as $kw) {
    $pos = stripos($b, $kw);
    if ($pos !== false) {
        $extract = substr($b, $pos, 300);
        echo "  [$kw] " . preg_replace('/\s+/', ' ', $extract) . "\n";
    }
}
