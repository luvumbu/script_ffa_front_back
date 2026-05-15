<?php
/**
 * api/auth/confirm_contact.php — Confirmation envoi message contact par token email
 * GET : ?token=XXXX
 * Verifie le token, enregistre le message en BDD, notifie l'admin, affiche confirmation.
 */

require_once __DIR__ . '/../../core/db.php';

$token = trim($_GET['token'] ?? '');
$isLocal = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
$homeUrl = $isLocal ? '/BK/index.php' : '/index.php';

function showResult($title, $message, $icon, $color, $homeUrl) {
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex"><title>' . htmlspecialchars($title) . ' — Bokonzi</title>
    <style>*{margin:0;padding:0;box-sizing:border-box;}body{background:#080c14;color:#c9d1d9;font-family:"Segoe UI",system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;}
    .card{background:#111830;border:1px solid #1a2540;border-radius:16px;padding:40px 36px;max-width:520px;width:90%;text-align:center;}</style></head>
    <body><div class="card">
    <div style="font-size:56px;margin-bottom:20px;">' . $icon . '</div>
    <h2 style="color:' . $color . ';font-size:22px;margin-bottom:12px;">' . htmlspecialchars($title) . '</h2>
    <p style="color:#8b949e;font-size:14px;line-height:1.6;margin-bottom:24px;">' . $message . '</p>
    <a href="' . $homeUrl . '" style="display:inline-block;padding:10px 24px;background:#1e2a3a;border:1px solid #2a3560;border-radius:8px;color:#a29bfe;text-decoration:none;font-size:14px;font-weight:600;">Retour a l\'accueil</a>
    </div></body></html>';
    exit;
}

if (empty($token)) {
    showResult('Lien invalide', 'Ce lien de confirmation est invalide ou incomplet.', '&#10060;', '#ef4444', $homeUrl);
}

// Auto-creation table (au cas ou)
$conn->query("CREATE TABLE IF NOT EXISTS `contact_confirm_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `nom` VARCHAR(100) NOT NULL DEFAULT '',
    `email` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `used` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Verifier le token
$stmt = $conn->prepare("SELECT id, ip, nom, email, message, used, expires_at FROM contact_confirm_tokens WHERE token = ? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    showResult('Lien invalide', 'Ce lien de confirmation n\'existe pas.', '&#10060;', '#ef4444', $homeUrl);
}

if ((int)$row['used'] === 1) {
    showResult('Deja confirme', 'Ce message a deja ete envoye. Nous l\'avons bien recu, merci !', '&#9989;', '#3fb950', $homeUrl);
}

if (strtotime($row['expires_at']) < time()) {
    showResult('Lien expire', 'Ce lien de confirmation a expire (24h). Vous pouvez renvoyer votre message depuis le site.', '&#8987;', '#f0ad4e', $homeUrl);
}

// Tout est bon → enregistrer le message en BDD
$conn->query("CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id_msg` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `nom` VARCHAR(100) NOT NULL DEFAULT '',
    `email` VARCHAR(200) NOT NULL DEFAULT '',
    `message` TEXT NOT NULL,
    `lu` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare("INSERT INTO contact_messages (ip, nom, email, message) VALUES (?, ?, ?, ?)");
$stmt->bind_param('ssss', $row['ip'], $row['nom'], $row['email'], $row['message']);
$stmt->execute();
$stmt->close();

// Marquer le token comme utilise
$stmt = $conn->prepare("UPDATE contact_confirm_tokens SET used = 1 WHERE id = ?");
$stmt->bind_param('i', $row['id']);
$stmt->execute();
$stmt->close();

// Notification admin par email
require_once __DIR__ . '/../../core/mailer.php';
$adminHtml = '<html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">'
    . '<div style="max-width:500px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
    . '<div style="background:#6c5ce7;padding:24px 30px;text-align:center;">'
    . '<h1 style="color:#fff;font-size:20px;margin:0;font-weight:800;">Nouveau message de contact</h1>'
    . '<p style="color:#ffffffaa;font-size:12px;margin:6px 0 0;">Email verifie par confirmation</p>'
    . '</div>'
    . '<div style="padding:24px 30px;">'
    . '<table style="width:100%;border-collapse:collapse;">'
    . '<tr><td style="padding:8px 0;color:#888;font-size:13px;width:80px;">Nom</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($row['nom'] ?: 'Anonyme') . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Email</td><td style="padding:8px 0;color:#333;font-size:14px;">' . htmlspecialchars($row['email']) . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">IP</td><td style="padding:8px 0;color:#333;font-size:13px;color:#999;">' . htmlspecialchars($row['ip']) . '</td></tr>'
    . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Date</td><td style="padding:8px 0;color:#333;font-size:13px;">' . date('d/m/Y H:i:s') . '</td></tr>'
    . '</table>'
    . '<div style="margin-top:16px;padding:16px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;">'
    . '<p style="color:#888;font-size:11px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 8px;">Message</p>'
    . '<p style="color:#333;font-size:14px;line-height:1.6;margin:0;white-space:pre-wrap;">' . htmlspecialchars($row['message']) . '</p>'
    . '</div>'
    . '<p style="text-align:center;margin:20px 0 0;"><a href="https://bokonzi.com/admin/panel.php#contactSection" style="display:inline-block;background:#6c5ce7;color:#fff;padding:10px 24px;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;">Voir dans le panel</a></p>'
    . '</div></div></body></html>';
bkMail('luvumbu.n@gmail.com', 'Message de ' . ($row['nom'] ?: 'Anonyme') . ' - Bokonzi', $adminHtml, $row['email']);

$conn->close();

showResult(
    'Message envoye avec succes',
    'Votre message a bien ete transmis a l\'equipe Bokonzi. Nous vous repondrons dans les plus brefs delais a l\'adresse <strong>' . htmlspecialchars($row['email']) . '</strong>.',
    '&#9989;',
    '#3fb950',
    $homeUrl
);
