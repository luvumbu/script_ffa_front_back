<?php
/**
 * api/auth/forgot_password.php — Demande de reinitialisation mot de passe
 * POST : { email }
 * Genere un token unique, l'enregistre en BDD, envoie un email avec le lien
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../core/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Methode POST requise'], 405);
}

// Auto-creation table si elle n'existe pas
$conn->query("CREATE TABLE IF NOT EXISTS `password_resets` (
    `id_reset` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `id_user` INT UNSIGNED NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `expire_at` DATETIME NOT NULL,
    `used` TINYINT(1) UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pr_token` (`token`),
    INDEX `idx_pr_user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'Email valide requis'], 400);
}

// Chercher l'utilisateur
$stmt = $conn->prepare("SELECT id_user, email, prenom FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Toujours retourner succes (securite : ne pas reveler si l'email existe)
if (!$user) {
    jsonResponse([
        'success' => true,
        'message' => 'Si un compte existe avec cet email, un lien de reinitialisation a ete envoye.',
    ]);
}

// Verifier rate limit : max 3 demandes par heure pour cet utilisateur
$stmt = $conn->prepare(
    "SELECT COUNT(*) as cnt FROM password_resets WHERE id_user = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$stmt->bind_param("i", $user['id_user']);
$stmt->execute();
$rlRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($rlRow['cnt'] >= 3) {
    jsonResponse(['success' => false, 'error' => 'Trop de demandes. Reessayez dans 1 heure.'], 429);
}

// Invalider les anciens tokens non utilises pour cet utilisateur
$stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id_user = ? AND used = 0");
$stmt->bind_param("i", $user['id_user']);
$stmt->execute();
$stmt->close();

// Generer un nouveau token
$token = generateToken();
$expireAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

$stmt = $conn->prepare("INSERT INTO password_resets (id_user, token, expire_at) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user['id_user'], $token, $expireAt);
$stmt->execute();
$stmt->close();

// Construire le lien de reinitialisation
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'bokonzi.com';
$basePath = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])));
$basePath = rtrim($basePath, '/');
$resetLink = "{$protocol}://{$host}{$basePath}/reset_password.php?token={$token}";

// Envoyer l'email
$subject = "Bokonzi - Reinitialisation de votre mot de passe";
$prenom = $user['prenom'] ?: 'Utilisateur';
$htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0d1117;font-family:Arial,sans-serif;">
<div style="max-width:500px;margin:40px auto;background:linear-gradient(135deg,#12182a,#1a2035);border:1px solid #1e2a3a;border-radius:16px;padding:40px;">
    <h1 style="color:#00d4ff;text-align:center;font-size:28px;margin:0 0 10px;letter-spacing:-0.5px;">BOKONZI</h1>
    <p style="color:#5a6580;text-align:center;font-size:14px;margin:0 0 30px;">Reinitialisation du mot de passe</p>
    <p style="color:#c9d1d9;font-size:15px;line-height:1.6;">Bonjour {$prenom},</p>
    <p style="color:#c9d1d9;font-size:15px;line-height:1.6;">Vous avez demande la reinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe :</p>
    <div style="text-align:center;margin:30px 0;">
        <a href="{$resetLink}" style="display:inline-block;padding:13px 32px;background:linear-gradient(135deg,#00d4ff,#0099cc);border-radius:8px;color:#fff;font-size:15px;font-weight:600;text-decoration:none;">Reinitialiser mon mot de passe</a>
    </div>
    <p style="color:#5a6580;font-size:13px;line-height:1.5;">Ce lien expire dans <strong style="color:#c9d1d9;">1 heure</strong>. Si vous n'avez pas fait cette demande, ignorez simplement cet email.</p>
    <hr style="border:none;border-top:1px solid #1e2a3a;margin:25px 0;">
    <p style="color:#5a6580;font-size:12px;text-align:center;">Si le bouton ne fonctionne pas, copiez ce lien :<br><a href="{$resetLink}" style="color:#00d4ff;word-break:break-all;">{$resetLink}</a></p>
</div>
</body>
</html>
HTML;

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Bokonzi <noreply@bokonzi.com>\r\n";
$headers .= "Reply-To: noreply@bokonzi.com\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

@mail($user['email'], $subject, $htmlBody, $headers);

jsonResponse([
    'success' => true,
    'message' => 'Si un compte existe avec cet email, un lien de reinitialisation a ete envoye.',
]);
