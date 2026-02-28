<?php
/**
 * api/auth/me.php — Retourne l'utilisateur connecte
 * GET : verifie le cookie bk_token
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../core/auth.php';

$user = getCurrentUser($conn);

if (!$user) {
    jsonResponse(['success' => false, 'authenticated' => false], 200);
}

jsonResponse([
    'success'       => true,
    'authenticated' => true,
    'user'          => $user,
]);
