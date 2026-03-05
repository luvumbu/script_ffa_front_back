<?php
/**
 * check_activity.php — Comptage activite d'un user
 * AUTO-SUPPRESSION apres execution
 */
$key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') { http_response_code(403); die('Interdit'); }

require_once __DIR__ . '/../core/db.php';

$email = 'Alexandre.puren@yahoo.com';
$out = [];

// 1. Recuperer le user + son token de session
$stmt = $conn->prepare("SELECT u.id_user, s.token, s.created_at as session_start FROM users u LEFT JOIN user_sessions s ON s.id_user = u.id_user WHERE u.email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$out['user_id'] = $user['id_user'] ?? null;
$out['session_start'] = $user['session_start'] ?? null;
$token = $user['token'] ?? '';

// 2. Logs par session token (sid)
if ($token) {
    // Total logs
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM logs WHERE sid = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $out['logs_total'] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Logs par action
    $stmt = $conn->prepare("SELECT action, COUNT(*) as cnt FROM logs WHERE sid = ? GROUP BY action ORDER BY cnt DESC");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $out['logs_par_action'] = [];
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $out['logs_par_action'][] = $row;
    $stmt->close();

    // Logs par page
    $stmt = $conn->prepare("SELECT page, COUNT(*) as cnt FROM logs WHERE sid = ? GROUP BY page ORDER BY cnt DESC");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $out['logs_par_page'] = [];
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $out['logs_par_page'][] = $row;
    $stmt->close();

    // IP utilisee
    $stmt = $conn->prepare("SELECT DISTINCT ip FROM logs WHERE sid = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $out['ips'] = [];
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $out['ips'][] = $row['ip'];
    $stmt->close();

    // Derniers 20 logs details
    $stmt = $conn->prepare("SELECT ts, ip, action, page, detail FROM logs WHERE sid = ? ORDER BY ts DESC LIMIT 20");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $out['derniers_logs'] = [];
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $out['derniers_logs'][] = $row;
    $stmt->close();
}

// 3. Search tracking par les IPs trouvees
if (!empty($out['ips'])) {
    $ipList = implode(',', array_map(function($ip) use ($conn) {
        return "'" . $conn->real_escape_string($ip) . "'";
    }, $out['ips']));

    $r = $conn->query("SELECT COUNT(*) as total FROM search_tracking WHERE ip IN ($ipList)");
    $out['search_tracking_total'] = $r->fetch_assoc()['total'];

    $r = $conn->query("SELECT * FROM search_tracking WHERE ip IN ($ipList) ORDER BY created_at DESC LIMIT 20");
    $out['search_tracking'] = [];
    while ($row = $r->fetch_assoc()) $out['search_tracking'][] = $row;
}

$conn->close();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
unlink(__FILE__);
