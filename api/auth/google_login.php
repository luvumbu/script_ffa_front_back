<?php
/**
 * api/auth/google_login.php — Initie le flux OAuth Google
 * Genere un state CSRF, stocke en session, redirige vers Google.
 */
session_start();

require_once __DIR__ . '/../../core/oauth_config.php';

// Generer un state CSRF
$state = bin2hex(random_bytes(32));
$_SESSION['oauth_state'] = $state;

// Construire l'URL d'autorisation Google
$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
