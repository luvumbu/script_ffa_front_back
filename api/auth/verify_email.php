<?php
/**
 * api/auth/verify_email.php — Validation email par token
 * GET : ?token=XXXX
 * Verifie le token, active le compte, cree la session, redirige vers l'accueil.
 */

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';

$token = trim($_GET['token'] ?? '');
$isLocal = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
$loginUrl = $isLocal ? '/BK/login.php' : '/login.php';
$homeUrl  = $isLocal ? '/BK/index.php' : '/index.php';

if (empty($token)) {
    header('Location: ' . $loginUrl . '?verify=invalid');
    exit;
}

// Verifier que la table existe
$tableCheck = $conn->query("SHOW TABLES LIKE 'email_verifications'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    header('Location: ' . $loginUrl . '?verify=invalid');
    exit;
}

// Verifier le token (non utilise, non expire)
$stmt = $conn->prepare(
    "SELECT ev.id, ev.id_user, ev.used FROM email_verifications ev WHERE ev.token = ? LIMIT 1"
);
$stmt->bind_param("s", $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    // Token introuvable
    header('Location: ' . $loginUrl . '?verify=invalid');
    exit;
}

// Token deja utilise → verifier si le compte est deja actif
if ((int)$row['used'] === 1) {
    $userId = (int)$row['id_user'];
    $stmt = $conn->prepare("SELECT email_verified FROM users WHERE id_user = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($u && (int)$u['email_verified'] === 1) {
        // Deja verifie → connecter et rediriger directement
        createSession($conn, $userId);
        $conn->close();
        header('Location: ' . $homeUrl . '?verified=1');
        exit;
    }
    // Token utilise mais compte pas verifie → lien expire
    header('Location: ' . $loginUrl . '?verify=expired');
    exit;
}

// Verifier l'expiration
$stmt = $conn->prepare("SELECT id, id_user FROM email_verifications WHERE token = ? AND used = 0 AND expire_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$valid = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$valid) {
    header('Location: ' . $loginUrl . '?verify=expired');
    exit;
}

$userId = (int)$valid['id_user'];

// 1. Marquer le token comme utilise
$stmt = $conn->prepare("UPDATE email_verifications SET used = 1 WHERE id = ?");
$stmt->bind_param("i", $valid['id']);
$stmt->execute();
$stmt->close();

// 2. Activer le compte (email_verified = 1)
$stmt = $conn->prepare("UPDATE users SET email_verified = 1, last_login = NOW() WHERE id_user = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->close();

// 3. Creer la session (connecte automatiquement)
createSession($conn, $userId);

// 4. Email de bienvenue (optionnel, ne bloque pas)
$stmt = $conn->prepare("SELECT prenom, email FROM users WHERE id_user = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user) {
    $prenom = $user['prenom'] ?: 'Athlete';
    $html = '<html><body style="font-family:Arial,sans-serif;margin:0;padding:20px;background:#f4f4f4;">'
        . '<div style="max-width:500px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;">'
        . '<h1 style="color:#6c5ce7;text-align:center;">Email confirme !</h1>'
        . '<p>Bonjour <b>' . htmlspecialchars($prenom) . '</b>,</p>'
        . '<p>Votre adresse email a ete confirmee avec succes. Bonne navigation sur Bokonzi !</p>'
        . '<p style="text-align:center;margin-top:20px;"><a href="https://bokonzi.com" style="background:#6c5ce7;color:#fff;padding:12px 30px;text-decoration:none;border-radius:6px;">Explorer Bokonzi</a></p>'
        . '<p style="color:#999;font-size:11px;text-align:center;margin-top:20px;">bokonzi.com</p>'
        . '</div></body></html>';

    $subject = 'Email confirme - Bokonzi';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Bokonzi <noreply@bokonzi.com>\r\n";
    $headers .= "Reply-To: noreply@bokonzi.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    @mail($user['email'], $subject, $html, $headers);
}

$conn->close();

// 5. Rediriger vers l'accueil — le cookie bk_token est set, l'utilisateur est connecte
header('Location: ' . $homeUrl . '?verified=1');
exit;
