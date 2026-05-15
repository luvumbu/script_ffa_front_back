<?php
/**
 * core/db.php — Connexion BDD avec bascule auto local/prod + check tables en local
 *
 * Comportement :
 *  - Si on est sur localhost ET que core/credentials_local.php existe -> utilise ces credentials
 *  - Sinon -> utilise core/credentials.php (prod Hostinger)
 *  - En LOCAL : si une table essentielle manque, redirige vers admin/local_setup.php
 *
 * Pour activer en local :
 *  1) Cree une BDD locale dans phpMyAdmin (ex: bk_local)
 *  2) Edite core/credentials_local.php avec les bons credentials locaux
 */

require_once __DIR__ . '/credentials.php';

// Detection environnement local
$isLocal = (
    in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true) ||
    in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) ||
    str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost') ||
    PHP_SAPI === 'cli'
);

if ($isLocal && file_exists(__DIR__ . '/credentials_local.php')) {
    require __DIR__ . '/credentials_local.php';
}

// En LOCAL : desactive le cache navigateur (pour developpement)
if ($isLocal && PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$conn = new mysqli("localhost", $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    die("Connexion BDD echouee : " . $conn->connect_error);
}

// ══════════════════════════════════════════════════════
//  CHECK AUTO TABLES (uniquement en local + API LOCALE)
//  Desactive par defaut car le mode API distante (bokonzi.com)
//  ne necessite pas que les tables existent en local.
//
//  Pour reactiver : mettre $checkLocalTables = true dans credentials_local.php
// ══════════════════════════════════════════════════════
$shouldCheckTables = !empty($GLOBALS['checkLocalTables']);
if ($isLocal && $shouldCheckTables && PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/paths.php';
    $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
    $currentUri = $_SERVER['REQUEST_URI'] ?? '';
    $bypassScripts = ['/admin/local_setup.php','/admin/setup_bdd.php','/admin/db_archive.php','/admin/db_size.php'];
    $isBypass = false;
    foreach ($bypassScripts as $bp) {
        if (strpos($currentScript, $bp) !== false || strpos($currentUri, $bp) !== false) { $isBypass = true; break; }
    }
    $isApi = (strpos($currentScript, '/api/') !== false || strpos($currentUri, '/api/') !== false);
    if (!$isBypass && !$isApi) {
        $check = $conn->query("SHOW TABLES LIKE 'athletes'");
        if (!$check || $check->num_rows === 0) {
            $redirectUrl = BK_BASE . '/admin/local_setup.php?from=' . urlencode($currentUri ?: $currentScript);
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}
