<?php
/**
 * api/auth/confirm_hide.php — Confirmation retrait profil par token email
 * GET : ?token=XXXX
 * Verifie le token, masque le profil (visible=0), vide le cache, affiche confirmation.
 */

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/athlete_purge.php';

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

// Tout est bon → MASQUAGE SEO du profil (soft delete, reversible par l'admin)
// L'utilisateur n'a PAS le droit de detruire les donnees : SET visible=0 uniquement.
// La destruction definitive reste reservee a l'admin.
$eid = (int)$row['athlete_id_ext'];

// 1. Marquer le token comme utilise
$stmt = $conn->prepare("UPDATE profile_hide_tokens SET used = 1 WHERE id = ?");
$stmt->bind_param('i', $row['id']);
$stmt->execute();
$stmt->close();

// 2. SET visible=0 (donnees conservees, profil invisible partout y compris pour admin)
$stmt = $conn->prepare("UPDATE athletes SET visible = 0 WHERE athlete_id_externe = ?");
$stmt->bind_param('i', $eid);
$stmt->execute();
$stmt->close();

// 3. Vider les caches concernes pour que le 410 prenne effet immediatement
$cacheDir = __DIR__ . '/../../cache';
if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . '/athlete_*.json') ?: [] as $f) {
        $json = @file_get_contents($f);
        if ($json && (strpos($json, '"' . $eid . '"') !== false || strpos($json, ':' . $eid) !== false)) {
            @unlink($f);
        }
    }
    foreach (['search_*.json', 'liste_*.json', 'stats_*.json', 'topsearched_*.json'] as $pat) {
        foreach (glob($cacheDir . '/' . $pat) ?: [] as $f) @unlink($f);
    }
}

// 4. Envoyer mail de confirmation finale (le profil a bien ete masque)
$_userEmail = trim((string)($row['email'] ?? ''));
if ($_userEmail !== '' && filter_var($_userEmail, FILTER_VALIDATE_EMAIL)) {
    require_once __DIR__ . '/../../core/mailer.php';
    $_athName = htmlspecialchars($row['athlete_name'] ?? '');
    $_confirmHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#080c14;font-family:Segoe UI,system-ui,sans-serif;">
<div style="max-width:560px;margin:40px auto;background:#111830;border:1px solid #1a2540;border-radius:16px;padding:36px 32px;">
    <div style="text-align:center;font-size:48px;margin-bottom:16px;">&#9989;</div>
    <h2 style="color:#3fb950;font-size:22px;margin:0 0 16px;text-align:center;">Votre profil a bien ete masque</h2>
    <p style="color:#c9d1d9;font-size:15px;line-height:1.7;margin:0 0 14px;">Bonjour,</p>
    <p style="color:#c9d1d9;font-size:15px;line-height:1.7;margin:0 0 14px;">Nous vous confirmons que le profil <strong style="color:#a29bfe;">' . $_athName . '</strong> a ete <strong>retire de Bokonzi</strong> avec succes.</p>
    <div style="background:#0d1117;border-left:3px solid #3fb950;padding:14px 18px;margin:18px 0;border-radius:6px;">
        <p style="color:#8b949e;font-size:13px;line-height:1.6;margin:0 0 8px;font-weight:700;">Ce qui a ete effectue :</p>
        <ul style="color:#8b949e;font-size:13px;line-height:1.7;margin:0;padding-left:18px;">
            <li>Retire des resultats de recherche du site</li>
            <li>Retire du classement et des statistiques</li>
            <li>Retire de Google (HTTP 410, desindexation sous 24h-7j)</li>
            <li>Plus accessible via l\'URL directe du profil</li>
        </ul>
    </div>
    <p style="color:#8b949e;font-size:13px;line-height:1.6;margin:14px 0;">Vos donnees sont conservees en base mais ne sont plus accessibles publiquement. Si vous souhaitez reapparaitre, contactez-nous via le formulaire de contact sur <a href="https://bokonzi.com" style="color:#a29bfe;text-decoration:none;">bokonzi.com</a>.</p>
    <p style="color:#5a6580;font-size:12px;margin:24px 0 0;border-top:1px solid #1a2540;padding-top:16px;">Cordialement,<br>L\'equipe Bokonzi<br><a href="https://bokonzi.com" style="color:#6c5ce7;text-decoration:none;">bokonzi.com</a></p>
</div>
</body></html>';
    @bkMail($_userEmail, 'Votre profil a bien ete retire de Bokonzi', $_confirmHtml);
}

$conn->close();

showResult(
    'Profil masque avec succes',
    'Le profil de <strong>' . htmlspecialchars($row['athlete_name']) . '</strong> n\'est plus visible sur Bokonzi.<br><br>'
    . 'Il a ete retire des resultats de recherche, du classement et de Google. Vos donnees sont conservees mais inaccessibles publiquement.<br><br>'
    . '<span style="color:#5a6580;font-size:12px;">Si vous souhaitez reapparaitre, contactez-nous via le formulaire de contact.</span>',
    '&#128584;',
    '#3fb950',
    $homeUrl
);
