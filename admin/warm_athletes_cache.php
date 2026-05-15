<?php
/**
 * admin/warm_athletes_cache.php — Pre-genere le cache des 4 appels liste.php
 * que fait la page /athletes par defaut. CLI uniquement (pas de timeout HTTP).
 * Usage : php admin/warm_athletes_cache.php
 */
if (PHP_SAPI !== 'cli') die('CLI only');
@set_time_limit(0);
@ini_set('memory_limit', '1G');

// Simule un appel HTTP normal pour que le cache se cree correctement.
// On passe par cURL local sans timeout serre — l'API ecrit le cache fichier elle-meme.
$ep = urlencode('100m|200m|400m Haies (76)|400m Haies (91)|110m Haies (91)|110m Haies (99)|110m Haies (106)|Longueur|Triple saut|Perche');
$year = (int)date('Y');

$urls = [];
foreach (['IA','IB'] as $lvl) {
    foreach (['M','F'] as $sx) {
        $urls[] = "http://localhost/BK/api/liste.php?limit=25&ordre=medailles&niveau={$lvl}&sexe={$sx}&annee_exact={$year}&epreuve={$ep}";
    }
}

foreach ($urls as $u) {
    echo "Fetching: " . substr($u, 30, 80) . "...\n";
    $ch = curl_init($u);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 1800,   // 30 min max par appel
        CURLOPT_USERAGENT      => 'BK warm-cache',
    ]);
    $t0 = microtime(true);
    $body = curl_exec($ch);
    $dt = round(microtime(true) - $t0, 1);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $j = $body ? json_decode($body, true) : null;
    $total = $j['total'] ?? '?';
    $nb = isset($j['athletes']) ? count($j['athletes']) : 0;
    echo "  -> HTTP $code  {$dt}s  total=$total  nb=$nb" . ($err ? "  err=$err" : '') . "\n";
}

echo "\nCache files:\n";
foreach (glob(__DIR__ . '/../cache/liste_*.json') as $f) {
    echo '  ' . basename($f) . ' (' . filesize($f) . " bytes)\n";
}
