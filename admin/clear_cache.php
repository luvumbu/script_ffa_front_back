<?php
/**
 * admin/clear_cache.php — Vider le cache API
 * Usage : /admin/clear_cache.php          (tout vider)
 *         /admin/clear_cache.php?prefix=clubstats  (vider seulement clubstats_*.json)
 */

$cacheDir = __DIR__ . '/../cache';
$prefix = trim($_GET['prefix'] ?? '');

if (!is_dir($cacheDir)) {
    echo json_encode(['success' => false, 'error' => 'Dossier cache inexistant']);
    exit;
}

$files = glob($cacheDir . '/' . ($prefix !== '' ? $prefix . '_*.json' : '*.json'));
$count = 0;
foreach ($files as $f) {
    if (@unlink($f)) $count++;
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'deleted' => $count, 'prefix' => $prefix ?: '*']);
