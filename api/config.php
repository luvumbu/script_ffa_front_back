<?php
/**
 * api/config.php — Connexion BDD + headers JSON
 * Inclus par tous les endpoints API. Ne pas appeler directement.
 */

// Bloquer l'acces direct
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    die('Acces interdit');
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=utf-8");

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Cle API (conservee pour top_searched reset, etc.) ---
define('BK_API_KEY', 'bk_s3cr3t_2026_xK9mP');

// Connexion BDD (partagee avec core/db.php)
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/ip_logger.php';
logIp();

/**
 * Reponse JSON + fermeture connexion
 */
function jsonResponse($data, $code = 200) {
    global $conn;
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $conn->close();
    exit;
}
