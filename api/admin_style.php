<?php
/**
 * api/admin_style.php — Selection du theme global du site
 *
 * Auth : cookie bk_sa_token OU bk_token (avec panel_access)
 * GET   : retourne la liste des themes + theme actif
 * POST  : { theme: 'id' } sauvegarde le choix
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/theme.php';

// --- Auth admin (super_admin OU panel_access) ---
$_isAdmin = false;
if (!empty($_COOKIE['bk_sa_token'])) {
    $saFile = __DIR__ . '/../logs/.sa_sessions.php';
    if (file_exists($saFile)) {
        $saRaw = file_get_contents($saFile);
        $saPos = strpos($saRaw, "\n");
        if ($saPos !== false) {
            $saSessions = json_decode(substr($saRaw, $saPos + 1), true) ?: [];
            $_isAdmin = isset($saSessions[$_COOKIE['bk_sa_token']]) && ($saSessions[$_COOKIE['bk_sa_token']]['expires'] ?? 0) > time();
        }
    }
}
if (!$_isAdmin) {
    $pUser = getCurrentUser($conn);
    if ($pUser) {
        $paFile = __DIR__ . '/../logs/.panel_access.php';
        if (file_exists($paFile)) {
            $paRaw = file_get_contents($paFile);
            $paPos = strpos($paRaw, "\n");
            if ($paPos !== false) {
                $paList = json_decode(substr($paRaw, $paPos + 1), true) ?: [];
                $_isAdmin = isset($paList[strtolower($pUser['email'])]);
            }
        }
    }
}
if (!$_isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Acces refuse']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $all = bkAllThemes();
    $list = [];
    foreach ($all as $id => $t) {
        $list[] = [
            'id'          => $t['id'],
            'nom'         => $t['nom'],
            'description' => $t['description'],
            'primary'     => $t['primary'],
            'accent'      => $t['accent'],
            'radius'      => $t['radius'],
            'font_family' => $t['font_family'],
            'heading_family' => $t['heading_family'] ?: $t['font_family'],
            'body_size'   => $t['body_size'],
            'font_google' => $t['font_google'],
        ];
    }
    // Liste des polices disponibles pour le mode personnalise
    $fontsList = [];
    foreach (bkAllFonts() as $fk => $fv) {
        $fontsList[] = [
            'id'          => $fk,
            'label'       => $fv[0],
            'font_family' => $fv[1],
            'google'      => $fv[2],
        ];
    }
    echo json_encode([
        'success' => true,
        'current' => bkGetThemeId(),
        'themes'  => $list,
        'fonts'   => $fontsList,
        'custom'  => bkGetCustomConfig(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $id = (string)($input['theme'] ?? '');
    if ($id !== 'custom') {
        $all = bkAllThemes();
        if (!isset($all[$id])) {
            http_response_code(400);
            echo json_encode(['error' => 'Theme inconnu']);
            exit;
        }
    }
    $custom = is_array($input['custom'] ?? null) ? $input['custom'] : null;
    $ok = bkSaveTheme($id, $custom);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Sauvegarde impossible']);
        exit;
    }
    echo json_encode(['success' => true, 'theme' => $id]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode non autorisee']);
