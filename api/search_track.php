<?php
/**
 * api/search_track.php — Endpoint leger de tracking recherches
 * POST (JSON ou sendBeacon) : q, type, source, entity_id, entity_name, results, pg
 * INSERT dans search_tracking, nettoyage probabiliste (1% → supprime >90 jours)
 */
require_once __DIR__ . '/../core/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// DELETE : effacer l'historique de l'utilisateur connecte
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_once __DIR__ . '/../core/auth.php';
    $delIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $delIp = trim(explode(',', $delIp)[0]);
    $stmt = $conn->prepare("DELETE FROM search_tracking WHERE ip = ?");
    $stmt->bind_param("s", $delIp);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    echo json_encode(['success' => true, 'ok' => true, 'deleted' => $deleted]);
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST ou DELETE requis']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$q         = trim($input['q'] ?? '');
$type      = $input['type'] ?? 'general';
$source    = $input['source'] ?? 'live_search';
$entityId  = isset($input['entity_id']) ? (int)$input['entity_id'] : null;
$entityName = trim($input['entity_name'] ?? '');
$results   = isset($input['results']) ? (int)$input['results'] : 0;
$pg        = trim($input['pg'] ?? '');

// Valider les enums
$validTypes = ['athlete', 'club', 'epreuve', 'ville', 'general'];
if (!in_array($type, $validTypes)) $type = 'general';

$validSources = ['live_search', 'page_view', 'panel_open'];
if (!in_array($source, $validSources)) $source = 'live_search';

// Ignorer les requetes vides sauf page_view/panel_open
if ($q === '' && $source === 'live_search') {
    echo json_encode(['success' => false, 'error' => 'query vide']);
    exit;
}

// IP visiteur
$ip = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';
$ip = trim(explode(',', $ip)[0]);

// INSERT
$stmt = $conn->prepare("INSERT INTO search_tracking (ip, query_text, search_type, source, entity_id, entity_name, result_count, page) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssisss", $ip, $q, $type, $source, $entityId, $entityName, $results, $pg);
$stmt->execute();
$stmt->close();

// Nettoyage probabiliste (1% chance → supprime >90 jours)
if (mt_rand(1, 100) === 1) {
    $conn->query("DELETE FROM search_tracking WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
}

echo json_encode(['success' => true]);
$conn->close();
