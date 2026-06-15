<?php
/**
 * api/report.php — Signalement de profil athlete
 * POST { athlete_id, athlete_name, reason, message, email? }
 * GET admin : ?mark_read=ID, ?delete=ID, ?resolve=ID
 */
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/athlete_purge.php';

// Auto-creation table
$conn->query("CREATE TABLE IF NOT EXISTS `profile_reports` (
    `id_report` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `athlete_id_ext` INT UNSIGNED NOT NULL,
    `athlete_name` VARCHAR(200) NOT NULL DEFAULT '',
    `reason` VARCHAR(100) NOT NULL DEFAULT '',
    `message` TEXT NOT NULL,
    `email` VARCHAR(200) NOT NULL DEFAULT '',
    `status` ENUM('new','read','resolved') NOT NULL DEFAULT 'new',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ajouter colonne visible si elle n'existe pas
$_vc = @$conn->query("SHOW COLUMNS FROM `athletes` LIKE 'visible'");
if ($_vc && $_vc->num_rows === 0) {
    @$conn->query("ALTER TABLE `athletes` ADD COLUMN `visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");
}
if ($_vc) $_vc->free();

// Admin actions (GET) — super admin OU user panel autorise
$_isAdmin = false;
// 1. Cookie super admin
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
// 2. User Google avec email autorise (panel access)
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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_isAdmin) {
    if (isset($_GET['mark_read'])) {
        $id = (int)$_GET['mark_read'];
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE profile_reports SET status = 'read' WHERE id_report = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true]);
        exit;
    }
    if (isset($_GET['resolve'])) {
        $id = (int)$_GET['resolve'];
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE profile_reports SET status = 'resolved' WHERE id_report = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true]);
        exit;
    }
    if (isset($_GET['hide_athlete'])) {
        $eid = (int)$_GET['hide_athlete'];
        if ($eid > 0) {
            $purge = purgeAthleteByExternalId($conn, $eid, 'admin_panel');
            echo json_encode(['success' => true, 'purged' => true, 'detail' => $purge]);
        } else {
            echo json_encode(['success' => false, 'error' => 'id invalide']);
        }
        exit;
    }
    // Soft hide : SET visible=0 (donnees conservees, reversible)
    if (isset($_GET['soft_hide'])) {
        $eid = (int)$_GET['soft_hide'];
        if ($eid > 0) {
            $stmt = $conn->prepare("UPDATE athletes SET visible = 0 WHERE athlete_id_externe = ?");
            $stmt->bind_param('i', $eid);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            // Vider cache athlete + caches agreges
            $cacheDir = __DIR__ . '/../cache';
            $cleared = 0;
            if (is_dir($cacheDir)) {
                foreach (glob($cacheDir . '/athlete_*.json') ?: [] as $f) {
                    $json = @file_get_contents($f);
                    if ($json && (strpos($json, '"' . $eid . '"') !== false || strpos($json, ':' . $eid) !== false)) {
                        if (@unlink($f)) $cleared++;
                    }
                }
                foreach (['search_*.json', 'liste_*.json', 'stats_*.json', 'topsearched_*.json'] as $pat) {
                    foreach (glob($cacheDir . '/' . $pat) ?: [] as $f) {
                        if (@unlink($f)) $cleared++;
                    }
                }
            }
            echo json_encode(['success' => true, 'visible' => 0, 'affected' => $affected, 'cache_cleared' => $cleared]);
        } else {
            echo json_encode(['success' => false, 'error' => 'id invalide']);
        }
        exit;
    }
    // Restore : SET visible=1
    if (isset($_GET['restore'])) {
        $eid = (int)$_GET['restore'];
        if ($eid > 0) {
            $stmt = $conn->prepare("UPDATE athletes SET visible = 1 WHERE athlete_id_externe = ?");
            $stmt->bind_param('i', $eid);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            // Vider caches pour que le profil reapparaisse partout
            $cacheDir = __DIR__ . '/../cache';
            $cleared = 0;
            if (is_dir($cacheDir)) {
                foreach (glob($cacheDir . '/athlete_*.json') ?: [] as $f) {
                    if (@unlink($f)) $cleared++;
                }
                foreach (['search_*.json', 'liste_*.json', 'stats_*.json', 'topsearched_*.json'] as $pat) {
                    foreach (glob($cacheDir . '/' . $pat) ?: [] as $f) {
                        if (@unlink($f)) $cleared++;
                    }
                }
            }
            echo json_encode(['success' => true, 'visible' => 1, 'affected' => $affected, 'cache_cleared' => $cleared]);
        } else {
            echo json_encode(['success' => false, 'error' => 'id invalide']);
        }
        exit;
    }
    if (isset($_GET['show_athlete'])) {
        // Reactivation impossible : la purge est definitive (DELETE complet + blacklist).
        // Pour reapparaitre, il faut retirer manuellement l'entree de athlete_blacklist
        // puis attendre le prochain scraping.
        $eid = (int)$_GET['show_athlete'];
        $unblacklisted = 0;
        if ($eid > 0 && _tableExists($conn, 'athlete_blacklist')) {
            $stmt = $conn->prepare("DELETE FROM athlete_blacklist WHERE athlete_id_ext = ?");
            $stmt->bind_param('i', $eid);
            $stmt->execute();
            $unblacklisted = $stmt->affected_rows;
            $stmt->close();
        }
        echo json_encode([
            'success'       => true,
            'unblacklisted' => $unblacklisted,
            'message'       => $unblacklisted > 0
                ? 'Athlete retire de la blacklist. Il sera re-scrap&eacute; au prochain passage du pipeline (peut prendre plusieurs jours).'
                : 'Aucune entree blacklist trouvee pour cet ID.'
        ]);
        exit;
    }
    if (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM profile_reports WHERE id_report = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true]);
        exit;
    }
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
if ($_action === 'reply' && !$_isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Acces refuse — connectez-vous en tant qu\'administrateur.']);
    exit;
}

// POST action=reply : repondre a un signalement (admin)
if ($_isAdmin && $_action === 'reply') {
    $idReport = (int)($input['id_report'] ?? 0);
    $subject = trim($input['subject'] ?? '');
    $body = trim($input['body'] ?? '');

    if ($idReport <= 0 || $subject === '' || $body === '') {
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

    $stmt = $conn->prepare("SELECT email, athlete_name, reason, message FROM profile_reports WHERE id_report = ?");
    $stmt->bind_param('i', $idReport);
    $stmt->execute();
    $stmt->bind_result($destEmail, $athName, $rpReason, $rpMessage);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found || !$destEmail || !filter_var($destEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(404);
        echo json_encode(['error' => 'Signalement ou email destinataire introuvable']);
        exit;
    }

    require_once __DIR__ . '/../core/mailer.php';

    $bodyHtml = nl2br(htmlspecialchars($body));
    $rpMsgHtml = nl2br(htmlspecialchars(mb_substr($rpMessage ?: '', 0, 1500)));

    $htmlMail = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#080c14;font-family:Segoe UI,system-ui,sans-serif;">
<div style="max-width:600px;margin:30px auto;background:#111830;border:1px solid #1a2540;border-radius:14px;padding:32px 30px;">
    <div style="color:#a78bfa;font-size:12px;text-transform:uppercase;letter-spacing:2px;margin-bottom:8px;">Reponse de l\'equipe Bokonzi</div>
    <h2 style="color:#f0f6fc;font-size:20px;margin:0 0 18px;">' . htmlspecialchars($subject) . '</h2>
    <div style="color:#e6edf3;font-size:14px;line-height:1.7;margin-bottom:24px;">' . $bodyHtml . '</div>
    <div style="border-top:1px solid #1a2540;padding-top:16px;margin-top:16px;">
        <p style="color:#5a6580;font-size:11px;text-transform:uppercase;margin:0 0 6px;letter-spacing:1px;">Votre signalement concernant ' . htmlspecialchars($athName ?: '') . '</p>
        <p style="color:#8b949e;font-size:13px;line-height:1.6;margin:0 0 6px;font-style:italic;">Motif : ' . htmlspecialchars($rpReason ?: '') . '</p>
        ' . ($rpMsgHtml !== '' ? '<p style="color:#8b949e;font-size:13px;line-height:1.6;margin:6px 0 0;font-style:italic;">' . $rpMsgHtml . '</p>' : '') . '
    </div>
    <p style="color:#5a6580;font-size:12px;margin:24px 0 0;line-height:1.5;">Cordialement,<br>L\'equipe Bokonzi<br><a href="https://bokonzi.com" style="color:#6c5ce7;text-decoration:none;">bokonzi.com</a></p>
</div>
</body></html>';

    $sent = bkMail($destEmail, $subject, $htmlMail);

    if ($sent) {
        $stmt = $conn->prepare("UPDATE profile_reports SET status = 'read' WHERE id_report = ? AND status = 'new'");
        $stmt->bind_param('i', $idReport);
        $stmt->execute();
        $stmt->close();
    }

    // Log dans l'historique (table commune)
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
    $sentInt = $sent ? 1 : 0;
    $logSrc = 'reply_report';
    $logName = $athName ?: '';
    $stmt = $conn->prepare("INSERT INTO sent_emails (to_email, to_name, subject, body, source, ref_id, sent_by, success) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssisi', $destEmail, $logName, $subject, $body, $logSrc, $idReport, $_adminEmail, $sentInt);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => $sent,
        'sent_to' => $destEmail,
        'error' => $sent ? null : 'Echec de l\'envoi (verifier la config SMTP)'
    ]);
    exit;
}

$athleteId = (int)($input['athlete_id'] ?? 0);
$athleteName = trim($input['athlete_name'] ?? '');
$reason = trim($input['reason'] ?? '');
$message = trim($input['message'] ?? '');
$email = trim($input['email'] ?? '');

if ($athleteId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'athlete_id requis']);
    exit;
}
if ($reason === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Motif requis']);
    exit;
}
if ($email === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Adresse email obligatoire pour valider votre signalement.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Adresse email invalide. Veuillez verifier votre saisie.']);
    exit;
}
if (mb_strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message trop long (max 2000 caracteres)']);
    exit;
}

// IP
$ip = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['HTTP_X_REAL_IP']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}

// Rate limit : max 3 signalements par IP par jour
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) FROM profile_reports WHERE ip = ? AND DATE(created_at) = ?");
$stmt->bind_param('ss', $ip, $today);
$stmt->execute();
$stmt->bind_result($countToday);
$stmt->fetch();
$stmt->close();

if ($countToday >= 3) {
    http_response_code(429);
    echo json_encode(['error' => 'Limite de 3 signalements par jour atteinte']);
    exit;
}

// Doublon : meme IP + meme athlete dans les 24h
$stmt = $conn->prepare("SELECT COUNT(*) FROM profile_reports WHERE ip = ? AND athlete_id_ext = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt->bind_param('si', $ip, $athleteId);
$stmt->execute();
$stmt->bind_result($alreadyReported);
$stmt->fetch();
$stmt->close();

if ($alreadyReported > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Vous avez deja signale ce profil']);
    exit;
}

// --- Retrait self-service : si motif "retrait" + email fourni → envoi mail de confirmation ---
if ($reason === 'retrait' && $email !== '') {
    // Table tokens auto-hide
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

    // Anti-abus : une adresse email ne peut masquer qu'1 seul profil (tous temps confondus)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM profile_hide_tokens WHERE email = ? AND used = 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($usedCount);
    $stmt->fetch();
    $stmt->close();

    if ($usedCount > 0) {
        // On enregistre le signalement normalement mais sans envoyer de lien
        $stmt = $conn->prepare("INSERT INTO profile_reports (ip, athlete_id_ext, athlete_name, reason, message, email) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sissss', $ip, $athleteId, $athleteName, $reason, $message, $email);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Signalement envoye. Nous examinerons votre demande.']);
        exit;
    }

    // Limiter : 1 demande par email+athlete par 24h
    $stmt = $conn->prepare("SELECT COUNT(*) FROM profile_hide_tokens WHERE email = ? AND athlete_id_ext = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->bind_param('si', $email, $athleteId);
    $stmt->execute();
    $stmt->bind_result($pendingCount);
    $stmt->fetch();
    $stmt->close();

    if ($pendingCount > 0) {
        echo json_encode(['success' => true, 'confirm_sent' => true, 'message' => 'Un email de confirmation a deja ete envoye a cette adresse. Verifiez votre boite mail (et les spams).']);
        exit;
    }

    // Generer token + expiration 48h
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 48 * 3600);

    $stmt = $conn->prepare("INSERT INTO profile_hide_tokens (athlete_id_ext, athlete_name, email, token, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issss', $athleteId, $athleteName, $email, $token, $expiresAt);
    $stmt->execute();
    $stmt->close();

    // Enregistrer aussi le signalement normalement
    $stmt = $conn->prepare("INSERT INTO profile_reports (ip, athlete_id_ext, athlete_name, reason, message, email) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sissss', $ip, $athleteId, $athleteName, $reason, $message, $email);
    $stmt->execute();
    $stmt->close();

    // Envoyer l'email de confirmation
    require_once __DIR__ . '/../core/mailer.php';
    $isLocal = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
    $baseUrl = $isLocal ? 'http://localhost/BK' : 'https://bokonzi.com';
    $confirmLink = $baseUrl . '/api/auth/confirm_hide.php?token=' . $token;

    $htmlMail = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#080c14;font-family:Segoe UI,system-ui,sans-serif;">
    <div style="max-width:520px;margin:40px auto;background:#111830;border:1px solid #1a2540;border-radius:16px;padding:40px 36px;text-align:center;">
        <div style="font-size:48px;margin-bottom:16px;">&#128274;</div>
        <h2 style="color:#f0f6fc;font-size:22px;margin:0 0 12px;">Confirmer le retrait de votre profil</h2>
        <p style="color:#8b949e;font-size:14px;line-height:1.6;margin:0 0 8px;">Vous avez demande le retrait du profil suivant sur Bokonzi :</p>
        <p style="color:#c9d1d9;font-size:16px;font-weight:700;margin:0 0 24px;">' . htmlspecialchars($athleteName) . '</p>
        <p style="color:#fcd34d;font-size:14px;line-height:1.6;margin:0 0 24px;">En cliquant ci-dessous, votre profil sera <strong>retire des resultats de recherche, du classement et de Google</strong>. Vos donnees seront conservees mais inaccessibles publiquement.</p>
        <a href="' . $confirmLink . '" style="display:inline-block;padding:14px 36px;background:#f59e0b;color:#fff;font-size:15px;font-weight:700;text-decoration:none;border-radius:10px;">Oui, masquer mon profil</a>
        <p style="color:#5a6580;font-size:12px;margin:24px 0 0;line-height:1.5;">Ce lien expire dans 48 heures.<br>Si vous n\'avez pas fait cette demande, ignorez simplement cet email.</p>
    </div>
    </body></html>';

    $mailSent = bkMail($email, 'Confirmer le retrait de votre profil — Bokonzi', $htmlMail);

    $conn->close();
    echo json_encode([
        'success' => true,
        'confirm_sent' => true,
        'message' => 'Un email de confirmation a ete envoye a ' . $email . '. Cliquez sur le lien dans l\'email pour masquer votre profil.'
    ]);
    exit;
}

// --- Signalement classique (pas de retrait self-service) ---
$stmt = $conn->prepare("INSERT INTO profile_reports (ip, athlete_id_ext, athlete_name, reason, message, email) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('sissss', $ip, $athleteId, $athleteName, $reason, $message, $email);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Signalement envoye. Nous examinerons votre demande.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
