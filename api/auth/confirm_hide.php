<?php
/**
 * api/auth/confirm_hide.php — Confirmation retrait profil par token email
 * GET : ?token=XXXX
 * Verifie le token, masque le profil (visible=0), vide le cache, affiche confirmation.
 */

require_once __DIR__ . '/../../core/db.php';

$token = trim($_GET['token'] ?? '');
$isLocal = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
$homeUrl = $isLocal ? '/BK/index.php' : '/index.php';

// Auto-creation table (au cas ou)
$conn->query("CREATE TABLE IF NOT EXISTS `profile_hide_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `athlete_id_ext` INT UNSIGNED NOT NULL,
    `athlete_name` VARCHAR(200) NOT NULL DEFAULT '',
    `email` VARCHAR(200) NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `used` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Page HTML de resultat
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

// Verifier le token
$stmt = $conn->prepare("SELECT id, athlete_id_ext, athlete_name, email, used, expires_at FROM profile_hide_tokens WHERE token = ? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    showResult('Lien invalide', 'Ce lien de confirmation n\'existe pas ou a deja expire.', '&#10060;', '#ef4444', $homeUrl);
}

if ((int)$row['used'] === 1) {
    showResult('Deja traite', 'Ce lien a deja ete utilise. Le profil <strong>' . htmlspecialchars($row['athlete_name']) . '</strong> est deja masque.', '&#9989;', '#3fb950', $homeUrl);
}

if (strtotime($row['expires_at']) < time()) {
    showResult('Lien expire', 'Ce lien de confirmation a expire (48h). Vous pouvez refaire une demande de retrait depuis la page du profil.', '&#8987;', '#f0ad4e', $homeUrl);
}

// Tout est bon → masquer le profil
$eid = (int)$row['athlete_id_ext'];

// 1. Mettre visible = 0
$stmt = $conn->prepare("UPDATE athletes SET visible = 0 WHERE athlete_id_externe = ?");
$stmt->bind_param('i', $eid);
$stmt->execute();
$stmt->close();

// 2. Marquer le token comme utilise
$stmt = $conn->prepare("UPDATE profile_hide_tokens SET used = 1 WHERE id = ?");
$stmt->bind_param('i', $row['id']);
$stmt->execute();
$stmt->close();

// 3. Vider les caches de cet athlete
$cacheDir = __DIR__ . '/../../cache';
$files = glob($cacheDir . '/athlete_*.json');
if ($files) foreach ($files as $f) {
    $json = @file_get_contents($f);
    if ($json && strpos($json, '"' . $eid . '"') !== false) @unlink($f);
}

$conn->close();

showResult(
    'Profil masque avec succes',
    'Le profil de <strong>' . htmlspecialchars($row['athlete_name']) . '</strong> n\'est plus visible publiquement sur Bokonzi.<br><br>'
    . '<span style="color:#5a6580;font-size:12px;">Si vous souhaitez reactiver votre profil, contactez-nous via le formulaire de contact sur le site.</span>',
    '&#128274;',
    '#3fb950',
    $homeUrl
);
