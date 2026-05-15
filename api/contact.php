<?php
/**
 * api/contact.php — Endpoint contact (accessible sans restriction IP)
 * POST { nom, email, message }
 * Stocke en BDD table contact_messages
 */
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

// Auto-creation table si elle n'existe pas
$conn->query("CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id_msg` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `nom` VARCHAR(100) NOT NULL DEFAULT '',
    `email` VARCHAR(200) NOT NULL DEFAULT '',
    `message` TEXT NOT NULL,
    `lu` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Table historique des mails envoyes
$conn->query("CREATE TABLE IF NOT EXISTS `sent_emails` (
    `id_sent` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `to_email` VARCHAR(200) NOT NULL,
    `to_name` VARCHAR(200) NOT NULL DEFAULT '',
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `source` ENUM('reply_message','send_to_user','reply_report') NOT NULL,
    `ref_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `sent_by` VARCHAR(200) NOT NULL DEFAULT '',
    `success` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_sent_at` (`sent_at`),
    KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Verification admin — super admin OU user panel autorise
$_isAdmin = false;
if (!empty($_COOKIE['bk_sa_token'])) {
    $saFile = __DIR__ . '/../logs/.sa_sessions.php';
    if (file_exists($saFile)) {
        $saRaw = file_get_contents($saFile);
        $saPos = strpos($saRaw, "\n");
        if ($saPos !== false) {
            $saSessions = json_decode(substr($saRaw, $saPos + 1), true) ?: [];
            $_isAdmin = isset($saSessions[$_COOKIE['bk_sa_token']]) && ($saSessions[$_COOKIE['bk_sa_token']]['expires'] ?? 0) > time();
        }
    }
}
$_adminEmail = '';
if ($_isAdmin) $_adminEmail = 'super_admin';
if (!$_isAdmin) {
    $pUser = getCurrentUser($conn);
    if ($pUser) {
        $paFile = __DIR__ . '/../logs/.panel_access.php';
        if (file_exists($paFile)) {
            $paRaw = file_get_contents($paFile);
            $paPos = strpos($paRaw, "\n");
            if ($paPos !== false) {
                $paList = json_decode(substr($paRaw, $paPos + 1), true) ?: [];
                $_isAdmin = isset($paList[strtolower($pUser['email'])]);
                if ($_isAdmin) $_adminEmail = $pUser['email'];
            }
        }
    }
}

// GET : marquer un message comme lu (admin)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_isAdmin && isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE contact_messages SET lu = 1 WHERE id_msg = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
    echo json_encode(['success' => true]);
    exit;
}

// GET : supprimer un message (admin)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_isAdmin && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id_msg = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
    echo json_encode(['success' => true]);
    exit;
}

// GET : marquer comme non lu (admin)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_isAdmin && isset($_GET['mark_unread'])) {
    $id = (int)$_GET['mark_unread'];
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE contact_messages SET lu = 0 WHERE id_msg = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
    echo json_encode(['success' => true]);
    exit;
}

// GET : supprimer un token non confirme (admin)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_isAdmin && isset($_GET['delete_token'])) {
    $id = (int)$_GET['delete_token'];
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM contact_confirm_tokens WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
    echo json_encode(['success' => true]);
    exit;
}

// GET : debannir une IP (admin)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_isAdmin && isset($_GET['unban_ip'])) {
    $ipToUnban = trim($_GET['unban_ip']);
    $logDir = __DIR__ . '/../logs';
    $bannedFile = $logDir . '/ip_banned.php';
    $prefix = '<?php die(\'Acces interdit\'); ?>' . "\n";
    if ($ipToUnban !== '' && file_exists($bannedFile)) {
        $fp = fopen($bannedFile, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            $raw = stream_get_contents($fp);
            $banned = [];
            if ($raw) {
                $pos = strpos($raw, "\n");
                if ($pos !== false) $banned = json_decode(substr($raw, $pos + 1), true) ?: [];
            }
            unset($banned[$ipToUnban]);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $prefix . json_encode($banned, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

// Si une action admin est demandee mais l'auth echoue, on refuse explicitement
$_action = $input['action'] ?? '';
if (in_array($_action, ['send_to_user', 'reply'], true) && !$_isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Acces refuse — connectez-vous en tant qu\'administrateur (cookie bk_sa_token ou panel_access).']);
    exit;
}

// POST action=send_to_user : envoyer un mail a un user inscrit (admin)
if ($_isAdmin && (($input['action'] ?? '') === 'send_to_user')) {
    $idUser = (int)($input['id_user'] ?? 0);
    $subject = trim($input['subject'] ?? '');
    $body = trim($input['body'] ?? '');

    if ($idUser <= 0 || $subject === '' || $body === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Champs requis manquants']);
        exit;
    }
    if (mb_strlen($subject) > 200) {
        http_response_code(400);
        echo json_encode(['error' => 'Sujet trop long (max 200 caracteres)']);
        exit;
    }
    if (mb_strlen($body) > 10000) {
        http_response_code(400);
        echo json_encode(['error' => 'Message trop long (max 10000 caracteres)']);
        exit;
    }

    $stmt = $conn->prepare("SELECT email, nom, prenom FROM users WHERE id_user = ?");
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $stmt->bind_result($destEmail, $destNom, $destPrenom);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found || !$destEmail || !filter_var($destEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(404);
        echo json_encode(['error' => 'Utilisateur ou email introuvable']);
        exit;
    }

    require_once __DIR__ . '/../core/mailer.php';

    $bodyHtml = nl2br(htmlspecialchars($body));
    $fullName = trim(($destPrenom ?: '') . ' ' . ($destNom ?: ''));
    $greeting = $fullName !== '' ? 'Bonjour ' . htmlspecialchars($fullName) . ',' : 'Bonjour,';

    $htmlMail = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#080c14;font-family:Segoe UI,system-ui,sans-serif;">
<div style="max-width:600px;margin:30px auto;background:#111830;border:1px solid #1a2540;border-radius:14px;padding:32px 30px;">
    <div style="color:#a78bfa;font-size:12px;text-transform:uppercase;letter-spacing:2px;margin-bottom:8px;">Message de l\'equipe Bokonzi</div>
    <h2 style="color:#f0f6fc;font-size:20px;margin:0 0 18px;">' . htmlspecialchars($subject) . '</h2>
    <p style="color:#e6edf3;font-size:14px;line-height:1.7;margin:0 0 14px;">' . $greeting . '</p>
    <div style="color:#e6edf3;font-size:14px;line-height:1.7;margin-bottom:24px;">' . $bodyHtml . '</div>
    <p style="color:#5a6580;font-size:12px;margin:24px 0 0;line-height:1.5;border-top:1px solid #1a2540;padding-top:16px;">Cordialement,<br>L\'equipe Bokonzi<br><a href="https://bokonzi.com" style="color:#6c5ce7;text-decoration:none;">bokonzi.com</a></p>
</div>
</body></html>';

    $sent = bkMail($destEmail, $subject, $htmlMail);

    // Log dans l'historique
    $sentInt = $sent ? 1 : 0;
    $logSrc = 'send_to_user';
    $stmt = $conn->prepare("INSERT INTO sent_emails (to_email, to_name, subject, body, source, ref_id, sent_by, success) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssisi', $destEmail, $fullName, $subject, $body, $logSrc, $idUser, $_adminEmail, $sentInt);
    $stmt->execute();
    $stmt->close();

    $conn->close();
    echo json_encode([
        'success' => $sent,
        'sent_to' => $destEmail,
        'error' => $sent ? null : 'Echec de l\'envoi (verifier la config SMTP)'
    ]);
    exit;
}

// POST action=reply : repondre a un message (admin)
if ($_isAdmin && (($input['action'] ?? '') === 'reply')) {
    $idMsg = (int)($input['id_msg'] ?? 0);
    $subject = trim($input['subject'] ?? '');
    $body = trim($input['body'] ?? '');

    if ($idMsg <= 0 || $subject === '' || $body === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Champs requis manquants']);
        exit;
    }
    if (mb_strlen($subject) > 200) {
        http_response_code(400);
        echo json_encode(['error' => 'Sujet trop long (max 200 caracteres)']);
        exit;
    }
    if (mb_strlen($body) > 10000) {
        http_response_code(400);
        echo json_encode(['error' => 'Message trop long (max 10000 caracteres)']);
        exit;
    }

    $stmt = $conn->prepare("SELECT email, nom, message FROM contact_messages WHERE id_msg = ?");
    $stmt->bind_param('i', $idMsg);
    $stmt->execute();
    $stmt->bind_result($destEmail, $destNom, $origMsg);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found || !$destEmail || !filter_var($destEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(404);
        echo json_encode(['error' => 'Message ou email destinataire introuvable']);
        exit;
    }

    require_once __DIR__ . '/../core/mailer.php';

    $bodyHtml = nl2br(htmlspecialchars($body));
    $origHtml = nl2br(htmlspecialchars(mb_substr($origMsg, 0, 1500)));
    $greeting = $destNom ? 'Bonjour ' . htmlspecialchars($destNom) . ',' : 'Bonjour,';

    $htmlMail = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#080c14;font-family:Segoe UI,system-ui,sans-serif;">
<div style="max-width:600px;margin:30px auto;background:#111830;border:1px solid #1a2540;border-radius:14px;padding:32px 30px;">
    <div style="color:#a78bfa;font-size:12px;text-transform:uppercase;letter-spacing:2px;margin-bottom:8px;">Reponse de l\'equipe Bokonzi</div>
    <h2 style="color:#f0f6fc;font-size:20px;margin:0 0 18px;">' . htmlspecialchars($subject) . '</h2>
    <p style="color:#e6edf3;font-size:14px;line-height:1.7;margin:0 0 14px;">' . $greeting . '</p>
    <div style="color:#e6edf3;font-size:14px;line-height:1.7;margin-bottom:24px;">' . $bodyHtml . '</div>
    <div style="border-top:1px solid #1a2540;padding-top:16px;margin-top:16px;">
        <p style="color:#5a6580;font-size:11px;text-transform:uppercase;margin:0 0 6px;letter-spacing:1px;">Votre message original</p>
        <p style="color:#8b949e;font-size:13px;line-height:1.6;margin:0;font-style:italic;">' . $origHtml . '</p>
    </div>
    <p style="color:#5a6580;font-size:12px;margin:24px 0 0;line-height:1.5;">Cordialement,<br>L\'equipe Bokonzi<br><a href="https://bokonzi.com" style="color:#6c5ce7;text-decoration:none;">bokonzi.com</a></p>
</div>
</body></html>';

    $sent = bkMail($destEmail, $subject, $htmlMail);

    if ($sent) {
        $stmt = $conn->prepare("UPDATE contact_messages SET lu = 1 WHERE id_msg = ?");
        $stmt->bind_param('i', $idMsg);
        $stmt->execute();
        $stmt->close();
    }

    // Log dans l'historique
    $sentInt = $sent ? 1 : 0;
    $logSrc = 'reply_message';
    $logName = $destNom ?: '';
    $stmt = $conn->prepare("INSERT INTO sent_emails (to_email, to_name, subject, body, source, ref_id, sent_by, success) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssisi', $destEmail, $logName, $subject, $body, $logSrc, $idMsg, $_adminEmail, $sentInt);
    $stmt->execute();
    $stmt->close();

    $conn->close();
    echo json_encode([
        'success' => $sent,
        'sent_to' => $destEmail,
        'error' => $sent ? null : 'Echec de l\'envoi (verifier la config SMTP)'
    ]);
    exit;
}

$nom = trim($input['nom'] ?? '');
$email = trim($input['email'] ?? '');
$message = trim($input['message'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Veuillez indiquer une adresse email valide']);
    exit;
}
if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message requis']);
    exit;
}
if (mb_strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message trop long (max 2000 caracteres)']);
    exit;
}

// IP du visiteur
$ip = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['HTTP_X_REAL_IP']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}

// Table tokens de confirmation contact
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

// Rate limit : max 3 demandes par IP par jour
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) FROM contact_confirm_tokens WHERE ip = ? AND DATE(created_at) = ?");
$stmt->bind_param('ss', $ip, $today);
$stmt->execute();
$stmt->bind_result($countToday);
$stmt->fetch();
$stmt->close();

if ($countToday >= 3) {
    http_response_code(429);
    echo json_encode(['error' => 'Limite de 3 messages par jour atteinte']);
    exit;
}

// Generer token + expiration 24h
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 24 * 3600);

$stmt = $conn->prepare("INSERT INTO contact_confirm_tokens (ip, nom, email, message, token, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('ssssss', $ip, $nom, $email, $message, $token, $expiresAt);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
    $conn->close();
    exit;
}

// Envoyer l'email de confirmation
require_once __DIR__ . '/../core/mailer.php';
$isLocal = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
$baseUrl = $isLocal ? 'http://localhost/BK' : 'https://bokonzi.com';
$confirmLink = $baseUrl . '/api/auth/confirm_contact.php?token=' . $token;

$htmlMail = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#080c14;font-family:Segoe UI,system-ui,sans-serif;">
<div style="max-width:520px;margin:40px auto;background:#111830;border:1px solid #1a2540;border-radius:16px;padding:40px 36px;text-align:center;">
    <div style="font-size:48px;margin-bottom:16px;">&#9993;</div>
    <h2 style="color:#f0f6fc;font-size:22px;margin:0 0 12px;">Confirmez l\'envoi de votre message</h2>
    <p style="color:#8b949e;font-size:14px;line-height:1.6;margin:0 0 20px;">Vous avez soumis un message de contact sur Bokonzi. Pour que votre message nous parvienne, veuillez confirmer en cliquant sur le bouton ci-dessous.</p>
    <div style="background:#161b22;border:1px solid #30363d;border-radius:10px;padding:16px;margin:0 0 24px;text-align:left;">
        <p style="color:#5a6580;font-size:11px;text-transform:uppercase;margin:0 0 6px;">Votre message</p>
        <p style="color:#c9d1d9;font-size:14px;line-height:1.6;margin:0;white-space:pre-wrap;">' . htmlspecialchars(mb_substr($message, 0, 300)) . (mb_strlen($message) > 300 ? '...' : '') . '</p>
    </div>
    <a href="' . $confirmLink . '" style="display:inline-block;padding:14px 36px;background:#6c5ce7;color:#fff;font-size:15px;font-weight:700;text-decoration:none;border-radius:10px;">Confirmer et envoyer mon message</a>
    <p style="color:#5a6580;font-size:12px;margin:24px 0 0;line-height:1.5;">Ce lien expire dans 24 heures.<br>Si vous n\'avez pas fait cette demande, ignorez simplement cet email.</p>
</div>
</body></html>';

bkMail($email, 'Confirmez votre message de contact — Bokonzi', $htmlMail);

$conn->close();
echo json_encode([
    'success' => true,
    'confirm_sent' => true,
    'message' => 'Un email de confirmation a ete envoye a ' . $email . '. Cliquez sur le lien pour que votre message nous parvienne.'
]);
