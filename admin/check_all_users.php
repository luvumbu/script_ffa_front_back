<?php
/**
 * check_all_users.php — Activite de TOUS les users
 * AUTO-SUPPRESSION apres execution
 */
$key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') { http_response_code(403); die('Interdit'); }

require_once __DIR__ . '/../core/db.php';

$out = [];

// Tous les users
$r = $conn->query("SELECT id_user, email, nom, prenom, role, google_id, oauth_provider, date_creation FROM users ORDER BY id_user");
while ($user = $r->fetch_assoc()) {
    $uid = (int)$user['id_user'];
    $entry = $user;

    // Sessions
    $r2 = $conn->query("SELECT token, created_at FROM user_sessions WHERE id_user = $uid ORDER BY created_at DESC");
    $tokens = [];
    while ($s = $r2->fetch_assoc()) $tokens[] = $s['token'];
    $entry['nb_sessions'] = count($tokens);

    // Logs par token
    $entry['logs_total'] = 0;
    $entry['logs_par_action'] = [];
    $entry['logs_par_page'] = [];
    $entry['ips'] = [];
    if ($tokens) {
        $tokenList = implode(',', array_map(function($t) use ($conn) {
            return "'" . $conn->real_escape_string($t) . "'";
        }, $tokens));

        $r2 = $conn->query("SELECT COUNT(*) as total FROM logs WHERE sid IN ($tokenList)");
        $entry['logs_total'] = (int)$r2->fetch_assoc()['total'];

        $r2 = $conn->query("SELECT action, COUNT(*) as cnt FROM logs WHERE sid IN ($tokenList) GROUP BY action ORDER BY cnt DESC");
        while ($row = $r2->fetch_assoc()) $entry['logs_par_action'][] = $row;

        $r2 = $conn->query("SELECT page, COUNT(*) as cnt FROM logs WHERE sid IN ($tokenList) GROUP BY page ORDER BY cnt DESC LIMIT 10");
        while ($row = $r2->fetch_assoc()) $entry['logs_par_page'][] = $row;

        $r2 = $conn->query("SELECT DISTINCT ip FROM logs WHERE sid IN ($tokenList)");
        while ($row = $r2->fetch_assoc()) $entry['ips'][] = $row['ip'];
    }

    // Search tracking par IP
    $entry['search_tracking_total'] = 0;
    if (!empty($entry['ips'])) {
        $ipList = implode(',', array_map(function($ip) use ($conn) {
            return "'" . $conn->real_escape_string($ip) . "'";
        }, $entry['ips']));
        $r2 = $conn->query("SELECT COUNT(*) as total FROM search_tracking WHERE ip IN ($ipList)");
        $entry['search_tracking_total'] = (int)$r2->fetch_assoc()['total'];
    }

    $out[] = $entry;
}

// Stats globales logs
$r = $conn->query("SELECT COUNT(*) as total FROM logs");
$global_logs = (int)$r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(DISTINCT sid) as total FROM logs");
$global_sessions = (int)$r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(DISTINCT ip) as total FROM logs");
$global_ips = (int)$r->fetch_assoc()['total'];

$conn->close();
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'global' => [
        'total_logs' => $global_logs,
        'unique_sessions_in_logs' => $global_sessions,
        'unique_ips_in_logs' => $global_ips,
    ],
    'users' => $out,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
unlink(__FILE__);
