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

// Admin actions (GET)
$_isAdmin = !empty($_COOKIE['bk_sa_token']);

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
            $stmt = $conn->prepare("UPDATE athletes SET visible = 0 WHERE athlete_id_externe = ?");
            $stmt->bind_param('i', $eid);
            $stmt->execute();
            $stmt->close();
            // Vider le cache de cet athlete (meme cle que athlete.php)
            $cacheDir = __DIR__ . '/../cache';
            @unlink($cacheDir . '/athlete_' . md5($eid . '__') . '.json');
        }
        echo json_encode(['success' => true, 'visible' => 0]);
        exit;
    }
    if (isset($_GET['show_athlete'])) {
        $eid = (int)$_GET['show_athlete'];
        if ($eid > 0) {
            $stmt = $conn->prepare("UPDATE athletes SET visible = 1 WHERE athlete_id_externe = ?");
            $stmt->bind_param('i', $eid);
            $stmt->execute();
            $stmt->close();
            // Vider le cache de cet athlete (meme cle que athlete.php)
            $cacheDir = __DIR__ . '/../cache';
            @unlink($cacheDir . '/athlete_' . md5($eid . '__') . '.json');
        }
        echo json_encode(['success' => true, 'visible' => 1]);
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
