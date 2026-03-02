<?php
/**
 * core/oauth_config.php — Configuration OAuth (Google, extensible pour Facebook, Instagram, etc.)
 * Les credentials sont dans oauth_credentials.php (gitignore)
 */

// Charger les credentials (fichier non committe)
require_once __DIR__ . '/oauth_credentials.php';

// Detection local vs prod
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])
        || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

$baseUrl = $isLocal ? 'http://localhost/BK' : 'https://bokonzi.com';

// ══════════════════════════════════════════════════════
//  GOOGLE OAuth 2.0
// ══════════════════════════════════════════════════════
define('GOOGLE_CLIENT_ID',     $GOOGLE_CLIENT_ID);
define('GOOGLE_CLIENT_SECRET', $GOOGLE_CLIENT_SECRET);
define('GOOGLE_REDIRECT_URI',  $baseUrl . '/api/auth/google_callback.php');

// ══════════════════════════════════════════════════════
//  FACEBOOK OAuth (a completer plus tard)
// ══════════════════════════════════════════════════════
// define('FACEBOOK_CLIENT_ID',     $FACEBOOK_CLIENT_ID);
// define('FACEBOOK_CLIENT_SECRET', $FACEBOOK_CLIENT_SECRET);
// define('FACEBOOK_REDIRECT_URI',  $baseUrl . '/api/auth/facebook_callback.php');

// ══════════════════════════════════════════════════════
//  INSTAGRAM OAuth (a completer plus tard)
// ══════════════════════════════════════════════════════
// define('INSTAGRAM_CLIENT_ID',     $INSTAGRAM_CLIENT_ID);
// define('INSTAGRAM_CLIENT_SECRET', $INSTAGRAM_CLIENT_SECRET);
// define('INSTAGRAM_REDIRECT_URI',  $baseUrl . '/api/auth/instagram_callback.php');
