<?php
/**
 * api/auth/register.php — DESACTIVE
 * L'inscription se fait uniquement via Google OAuth (google_callback.php)
 */
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'error' => 'Inscription classique desactivee. Utilisez Google OAuth.']);
exit;
