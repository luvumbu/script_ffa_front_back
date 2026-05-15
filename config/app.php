<?php
/**
 * config/app.php — Configuration applicative
 */

$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

return [
    'base_url'     => $isLocal ? '/BK' : '',
    'api_base_url' => $isLocal ? 'http://' . $host . '/BK/api' : 'https://bokonzi.com/api',
    'debug'        => $isLocal,
    'cache_dir'    => dirname(__DIR__) . '/cache',
    'logs_dir'     => dirname(__DIR__) . '/logs',
    'gtm_id'       => 'GTM-KPNTVXDF',
    'adsense_id'   => 'ca-pub-7899923856846249',
    'is_local'     => $isLocal,
];
