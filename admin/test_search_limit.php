<?php
/**
 * test_search_limit.php — Simule la limite de recherches pour tester
 * AUTO-SUPPRESSION apres execution
 */
$key = $_GET['bk_key'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') { http_response_code(403); die('Interdit'); }

$action = $_GET['action'] ?? 'status';
$limFile = __DIR__ . '/../logs/.search_limits.php';
$today = date('Y-m-d');

// Lire le fichier actuel
$limData = [];
if (file_exists($limFile)) {
    $raw = file_get_contents($limFile);
    $limData = @json_decode(substr($raw, strpos($raw, "\n") + 1), true) ?: [];
}
if (($limData['_date'] ?? '') !== $today) $limData = ['_date' => $today];

// Detecter IP
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);

$out = ['ip' => $ip, 'date' => $today, 'action' => $action];

if ($action === 'fill') {
    // Mettre le compteur a 49 (une recherche de plus = bloque)
    $limData[$ip] = 49;
    file_put_contents($limFile, "<?php die(); ?>\n" . json_encode($limData, JSON_UNESCAPED_UNICODE));
    $out['message'] = 'Compteur mis a 49/50. La prochaine recherche sera la derniere autorisee.';
    $out['count'] = 49;
} elseif ($action === 'max') {
    // Mettre le compteur a 50 (bloque immediatement)
    $limData[$ip] = 50;
    file_put_contents($limFile, "<?php die(); ?>\n" . json_encode($limData, JSON_UNESCAPED_UNICODE));
    $out['message'] = 'Compteur mis a 50/50. Toutes les recherches sont bloquees.';
    $out['count'] = 50;
} elseif ($action === 'reset') {
    // Remettre a zero
    unset($limData[$ip]);
    file_put_contents($limFile, "<?php die(); ?>\n" . json_encode($limData, JSON_UNESCAPED_UNICODE));
    $out['message'] = 'Compteur remis a zero.';
    $out['count'] = 0;
} else {
    // Status
    $out['count'] = (int)($limData[$ip] ?? 0);
    $out['limit'] = 50;
    $out['remaining'] = max(0, 50 - $out['count']);
    $out['all_ips'] = $limData;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
