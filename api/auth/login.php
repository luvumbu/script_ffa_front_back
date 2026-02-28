<?php
/**
 * api/auth/login.php — Connexion utilisateur
 * POST : email, password
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../core/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Methode POST requise'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    jsonResponse(['success' => false, 'error' => 'Email et mot de passe requis'], 400);
}

// === SUPER ADMIN : login avec identifiants BDD ===
require_once __DIR__ . '/../../core/credentials.php';
if ($email === $username && $password === $GLOBALS['password']) {
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
    $sessions[$saToken] = ['created' => date('Y-m-d H:i:s'), 'expires' => $expiry, 'ip' => $_SERVER['REMOTE_ADDR'] ?? ''];
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
// Chercher l'utilisateur
$stmt = $conn->prepare("SELECT id_user, email, password_hash, nom, prenom, role, id_athlete FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !verifyPassword($password, $user['password_hash'])) {
    jsonResponse(['success' => false, 'error' => 'Email ou mot de passe incorrect'], 401);
}

// Creer session
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
