<?php
/**
 * api/auth/login.php — Connexion admin (email/mot de passe)
 * POST : email, password
 * Rate limit : 3 tentatives max par IP, blocage apres 3 echecs
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/paths.php';

// === Identifiants super admin attendus ===
// IMPORTANT : a capturer AVANT que $password ne soit ecrase par l'input (plus bas).
// En local, core/db.php a charge credentials_local.php => $username/$password = root/"" (creds MySQL).
// On utilise alors $localAuthUser/$localAuthPass dedies a l'auth (ex: root/root).
// En prod, on garde les identifiants BDD ($username/$password).
if (defined('BK_IS_LOCAL') && BK_IS_LOCAL && !empty($GLOBALS['localAuthUser'])) {
    $ADMIN_USER = $GLOBALS['localAuthUser'];
    $ADMIN_PASS = $GLOBALS['localAuthPass'] ?? '';
} else {
    $ADMIN_USER = $username;
    $ADMIN_PASS = $password;
}

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

if (empty($email)) {
    jsonResponse(['success' => false, 'error' => 'Identifiant requis'], 400);
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

// === SUPER ADMIN : login avec identifiants dedies (captures plus haut) ===
if ($email === $ADMIN_USER && hash_equals($ADMIN_PASS, $password)) {
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
$stmt = $conn->prepare("SELECT id_user, email, password_hash, nom, prenom, role, id_athlete, oauth_provider, email_verified FROM users WHERE email = ?");
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

// Notification admin : connexion email/password
$loginHtml = '<html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">'
    . '<div style="max-width:500px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
    . '<div style="background:#3b82f6;padding:24px 30px;text-align:center;">'
    . '<h1 style="color:#fff;font-size:22px;margin:0;font-weight:800;">Connexion sur Bokonzi</h1>'
    . '<p style="color:#dbeafe;font-size:13px;margin:6px 0 0;">via email / mot de passe</p>'
    . '</div>'
    . '<div style="padding:24px 30px;">'
    . '<table style="width:100%;border-collapse:collapse;">'
    . '<tr><td style="padding:8px 0;color:#888;font-size:13px;width:100px;">Prenom</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($user['prenom'] ?: '-') . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Nom</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($user['nom'] ?: '-') . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Email</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($user['email']) . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Date</td><td style="padding:8px 0;color:#333;font-size:14px;">' . date('d/m/Y H:i:s') . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">IP</td><td style="padding:8px 0;color:#333;font-size:14px;">' . htmlspecialchars($ip) . '</td></tr>'
    . '</table>'
    . '<p style="text-align:center;margin:20px 0 0;"><a href="https://bokonzi.com/admin/panel.php" style="display:inline-block;background:#6c5ce7;color:#fff;padding:10px 24px;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;">Voir le panel admin</a></p>'
    . '</div></div></body></html>';
require_once __DIR__ . '/../../core/mailer.php';
bkMail('luvumbu.n@gmail.com', 'Connexion email : ' . $user['email'] . ' - Bokonzi', $loginHtml);

// Notification utilisateur : confirmation de connexion
$_p = htmlspecialchars($user['prenom'] ?: 'Athlete');
$userHtml = '<html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">'
    . '<div style="max-width:500px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
    . '<div style="background:#3b82f6;padding:24px 30px;text-align:center;">'
    . '<h1 style="color:#fff;font-size:22px;margin:0;font-weight:800;">Nouvelle connexion sur Bokonzi</h1>'
    . '</div>'
    . '<div style="padding:24px 30px;">'
    . '<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">Bonjour <b>' . $_p . '</b>,</p>'
    . '<p style="font-size:14px;color:#444;line-height:1.6;margin:0 0 16px;">Une connexion a ete effectuee sur votre compte Bokonzi avec votre email et mot de passe.</p>'
    . '<table style="width:100%;border-collapse:collapse;background:#f9fafb;border-radius:8px;margin-bottom:16px;">'
    . '<tr><td style="padding:10px 14px;color:#888;font-size:13px;width:80px;">Date</td><td style="padding:10px 14px;color:#333;font-size:14px;">' . date('d/m/Y H:i:s') . '</td></tr>'
    . '<tr><td style="padding:10px 14px;color:#888;font-size:13px;">IP</td><td style="padding:10px 14px;color:#333;font-size:14px;">' . htmlspecialchars($ip) . '</td></tr>'
    . '</table>'
    . '<p style="font-size:13px;color:#888;line-height:1.6;margin:0;">Si ce n\'etait pas vous, contactez-nous immediatement.</p>'
    . '</div></div></body></html>';
bkMail($user['email'], 'Connexion sur Bokonzi', $userHtml);
// Mail rapide a contact@bokonzi.com
bkMail('contact@bokonzi.com', 'Connexion : ' . $user['email'], '<p>L\'adresse <b>' . htmlspecialchars($user['email']) . '</b> vient de se connecter sur Bokonzi (email/password).</p><p>' . date('d/m/Y H:i:s') . '</p>');

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
