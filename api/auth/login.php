<?php
/**
 * api/auth/login.php — Connexion admin (email/mot de passe)
 * POST : email, password
 * Rate limit : 3 tentatives max par IP, blocage apres 3 echecs
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../core/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Methode POST requise'], 405);
}

// === RATE LIMITING : 5 tentatives par jour par IP ===
$attemptsFile = __DIR__ . '/../../logs/.admin_attempts.php';
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip = explode(',', $ip)[0];
$ip = trim($ip);
$maxAttempts = 5;
$blockDuration = 86400; // 24h de blocage

// Whitelist Google + Hostinger (illimite)
$whitelistPrefixes = ['66.249.', '66.102.', '64.233.', '72.14.', '74.125.', '209.85.', '216.239.', '35.', '34.', '153.92.', '31.170.', '185.201.', '127.0.0.1', '::1'];
$isWhitelisted = false;
foreach ($whitelistPrefixes as $prefix) {
    if (strpos($ip, $prefix) === 0) { $isWhitelisted = true; break; }
}
if ($isWhitelisted) {
    // Skip rate limiting pour Google/Hostinger
    goto skipRateLimit;
}

// Lire les tentatives
$attempts = [];
if (file_exists($attemptsFile)) {
    $raw = file_get_contents($attemptsFile);
    $pos = strpos($raw, "\n");
    if ($pos !== false) $attempts = json_decode(substr($raw, $pos + 1), true) ?: [];
}

// Nettoyer les entrees expirees
$now = time();
foreach ($attempts as $k => $v) {
    if (($v['blocked_until'] ?? 0) < $now && ($v['last_attempt'] ?? 0) < $now - $blockDuration) {
        unset($attempts[$k]);
    }
}

// Verifier si IP bloquee
$ipData = $attempts[$ip] ?? ['count' => 0, 'last_attempt' => 0, 'blocked_until' => 0];
if ($ipData['blocked_until'] > $now) {
    $remain = ceil(($ipData['blocked_until'] - $now) / 60);
    jsonResponse([
        'success' => false,
        'error' => 'IP bloquee. Reessayez dans ' . $remain . ' minute' . ($remain > 1 ? 's' : '') . '.',
        'blocked' => true,
    ], 429);
}

skipRateLimit:
$input = json_decode(file_get_contents('php://input'), true);
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    jsonResponse(['success' => false, 'error' => 'Identifiant et mot de passe requis'], 400);
}

// Fonction pour enregistrer un echec
function recordFailure($attemptsFile, &$attempts, $ip, $maxAttempts, $blockDuration) {
    $now = time();
    if (!isset($attempts[$ip])) {
        $attempts[$ip] = ['count' => 0, 'last_attempt' => 0, 'blocked_until' => 0];
    }
    $attempts[$ip]['count']++;
    $attempts[$ip]['last_attempt'] = $now;

    $remaining = $maxAttempts - $attempts[$ip]['count'];

    if ($attempts[$ip]['count'] >= $maxAttempts) {
        $attempts[$ip]['blocked_until'] = $now + $blockDuration;
        file_put_contents($attemptsFile, "<?php die('Acces interdit'); ?>\n" . json_encode($attempts, JSON_PRETTY_PRINT));
        return ['blocked' => true, 'remaining' => 0];
    }

    file_put_contents($attemptsFile, "<?php die('Acces interdit'); ?>\n" . json_encode($attempts, JSON_PRETTY_PRINT));
    return ['blocked' => false, 'remaining' => max(0, $remaining)];
}

// Fonction pour effacer les tentatives apres succes
function clearAttempts($attemptsFile, &$attempts, $ip) {
    unset($attempts[$ip]);
    file_put_contents($attemptsFile, "<?php die('Acces interdit'); ?>\n" . json_encode($attempts, JSON_PRETTY_PRINT));
}

// === SUPER ADMIN : login avec identifiants BDD ===
require_once __DIR__ . '/../../core/credentials.php';
if ($email === $username && $password === $GLOBALS['password']) {
    // Succes → effacer les tentatives
    clearAttempts($attemptsFile, $attempts, $ip);

    // Generer un token super admin (cookie separe)
    $saToken = bin2hex(random_bytes(32));
    $expiry = time() + 86400 * 7; // 7 jours
    // Stocker en fichier (pas en BDD, independant)
    $saFile = __DIR__ . '/../../logs/.sa_sessions.php';
    $sessions = [];
    if (file_exists($saFile)) {
        $raw = file_get_contents($saFile);
        $pos = strpos($raw, "\n");
        if ($pos !== false) $sessions = json_decode(substr($raw, $pos + 1), true) ?: [];
    }
    // Nettoyer les sessions expirees
    $sessions = array_filter($sessions, function($s) { return ($s['expires'] ?? 0) > time(); });
    $sessions[$saToken] = ['created' => date('Y-m-d H:i:s'), 'expires' => $expiry, 'ip' => $ip];
    file_put_contents($saFile, "<?php die('Acces interdit'); ?>\n" . json_encode($sessions, JSON_PRETTY_PRINT));

    setcookie('bk_sa_token', $saToken, [
        'expires'  => $expiry,
        'path'     => '/',
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);
    jsonResponse([
        'success' => true,
        'superadmin' => true,
        'redirect' => 'admin/panel.php',
        'token' => $saToken,
        'user' => [
            'id_user' => 0,
            'email' => 'superadmin',
            'nom' => 'Super',
            'prenom' => 'Admin',
            'role' => 'superadmin',
            'id_athlete' => null,
        ],
    ]);
}

// === LOGIN NORMAL ===
$stmt = $conn->prepare("SELECT id_user, email, password_hash, nom, prenom, role, id_athlete, oauth_provider FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $r = recordFailure($attemptsFile, $attempts, $ip, $maxAttempts, $blockDuration);
    $resp = ['success' => false, 'error' => 'Identifiant ou mot de passe incorrect'];
    if ($r['blocked']) {
        $resp['error'] = 'Trop de tentatives. IP bloquee pour 24 heures.';
        $resp['blocked'] = true;
    } else {
        $resp['remaining'] = $r['remaining'];
    }
    jsonResponse($resp, $r['blocked'] ? 429 : 401);
}

// User OAuth sans mot de passe
if ($user['password_hash'] === '' && !empty($user['oauth_provider'])) {
    $r = recordFailure($attemptsFile, $attempts, $ip, $maxAttempts, $blockDuration);
    $resp = ['success' => false, 'error' => 'Ce compte utilise la connexion ' . ucfirst($user['oauth_provider']) . '.'];
    if ($r['blocked']) { $resp['blocked'] = true; }
    else { $resp['remaining'] = $r['remaining']; }
    jsonResponse($resp, $r['blocked'] ? 429 : 401);
}

if (!verifyPassword($password, $user['password_hash'])) {
    $r = recordFailure($attemptsFile, $attempts, $ip, $maxAttempts, $blockDuration);
    $resp = ['success' => false, 'error' => 'Identifiant ou mot de passe incorrect'];
    if ($r['blocked']) {
        $resp['error'] = 'Trop de tentatives. IP bloquee pour 24 heures.';
        $resp['blocked'] = true;
    } else {
        $resp['remaining'] = $r['remaining'];
    }
    jsonResponse($resp, $r['blocked'] ? 429 : 401);
}

// Succes → effacer les tentatives
clearAttempts($attemptsFile, $attempts, $ip);

$token = createSession($conn, $user['id_user']);

jsonResponse([
    'success' => true,
    'token'   => $token,
    'user'    => [
        'id_user'    => $user['id_user'],
        'email'      => $user['email'],
        'nom'        => $user['nom'],
        'prenom'     => $user['prenom'],
        'role'       => $user['role'],
        'id_athlete' => $user['id_athlete'],
    ],
]);
