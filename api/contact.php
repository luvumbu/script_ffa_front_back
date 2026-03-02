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

// Verification admin pour actions sensibles (mark_read, delete, unban_ip)
$_isAdmin = !empty($_COOKIE['bk_sa_token']);

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
$nom = trim($input['nom'] ?? '');
$email = trim($input['email'] ?? '');
$message = trim($input['message'] ?? '');

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

// Rate limit : max 3 messages par IP par jour
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) FROM contact_messages WHERE ip = ? AND DATE(created_at) = ?");
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

$stmt = $conn->prepare("INSERT INTO contact_messages (ip, nom, email, message) VALUES (?, ?, ?, ?)");
$stmt->bind_param('ssss', $ip, $nom, $email, $message);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Message envoye']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
