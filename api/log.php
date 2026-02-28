<?php
/**
 * api/log.php — Enregistre les actions utilisateur en BDD (table logs)
 *
 * POST : enregistre un ou plusieurs evenements
 * GET  : lecture des logs avec filtres SQL
 */
require_once __DIR__ . '/config.php';

// IP reelle (derriere proxy/CDN)
function getRealIp() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = explode(',', $_SERVER[$h])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        jsonResponse(['success' => false, 'error' => 'Body vide'], 400);
    }
    $input = json_decode($raw, true);
    if (!$input || !is_array($input)) {
        jsonResponse(['success' => false, 'error' => 'JSON invalide'], 400);
    }

    $ip = $conn->real_escape_string(getRealIp());
    $ua = $conn->real_escape_string(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500));

    // Detecter user connecte (optionnel)
    $userId = 'NULL';
    $userName = '';
    if (function_exists('getCurrentUser')) {
        try {
            $user = getCurrentUser($conn);
            if ($user) {
                $userId = (int) $user['id_user'];
                $userName = $conn->real_escape_string(trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')));
            }
        } catch (Exception $e) {}
    }

    // Support batch ou evenement unique
    $events = isset($input['events']) && is_array($input['events']) ? $input['events'] : [$input];
    if (count($events) > 100) {
        $events = array_slice($events, 0, 100);
    }

    // Batch INSERT
    $vals = [];
    foreach ($events as $evt) {
        if (!is_array($evt)) continue;
        $sid      = $conn->real_escape_string(substr((string)($evt['sid'] ?? ''), 0, 100));
        $page     = $conn->real_escape_string(substr((string)($evt['page'] ?? ''), 0, 500));
        $action   = $conn->real_escape_string(substr((string)($evt['action'] ?? 'unknown'), 0, 50));
        $detail   = $conn->real_escape_string(substr((string)($evt['detail'] ?? ''), 0, 500));
        $value    = $conn->real_escape_string(substr((string)($evt['value'] ?? ''), 0, 1000));
        $target   = $conn->real_escape_string(substr((string)($evt['target'] ?? ''), 0, 200));
        $screen   = $conn->real_escape_string(substr((string)($evt['screen'] ?? ''), 0, 20));
        $lang     = $conn->real_escape_string(substr((string)($evt['lang'] ?? ''), 0, 10));
        $referrer = $conn->real_escape_string(substr((string)($evt['referrer'] ?? ''), 0, 500));
        $duration = isset($evt['duration_ms']) ? (int) $evt['duration_ms'] : 'NULL';

        $vals[] = "(NOW(), '$ip', '$ua', '$sid', $userId, '$userName', '$page', '$action', '$detail', '$value', '$target', '$screen', '$lang', '$referrer', $duration)";
    }

    if (empty($vals)) {
        jsonResponse(['success' => true, 'logged' => 0]);
    }

    $sql = "INSERT INTO `logs` (`ts`, `ip`, `ua`, `sid`, `uid`, `uname`, `page`, `action`, `detail`, `value`, `target`, `screen`, `lang`, `referrer`, `duration_ms`) VALUES " . implode(",", $vals);
    $conn->query($sql);

    if ($conn->error) {
        jsonResponse(['success' => false, 'error' => 'Erreur INSERT: ' . $conn->error], 500);
    }

    jsonResponse(['success' => true, 'logged' => count($vals)]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $limit  = min(500, max(1, (int) ($_GET['limit'] ?? 100)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));

    // Filtres
    $where = [];
    if (!empty($_GET['date'])) {
        $date = $conn->real_escape_string(preg_replace('/[^0-9\-]/', '', $_GET['date']));
        $where[] = "DATE(ts) = '$date'";
    }
    if (!empty($_GET['ip'])) {
        $where[] = "ip = '" . $conn->real_escape_string($_GET['ip']) . "'";
    }
    if (!empty($_GET['action'])) {
        $where[] = "action = '" . $conn->real_escape_string($_GET['action']) . "'";
    }
    if (!empty($_GET['page_filter'])) {
        $where[] = "page LIKE '%" . $conn->real_escape_string($_GET['page_filter']) . "%'";
    }
    if (!empty($_GET['sid'])) {
        $where[] = "sid = '" . $conn->real_escape_string($_GET['sid']) . "'";
    }

    $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Total
    $res = $conn->query("SELECT COUNT(*) as cnt FROM `logs` $whereSQL");
    $total = $res ? (int) $res->fetch_assoc()['cnt'] : 0;

    // Entries
    $entries = [];
    $res = $conn->query("SELECT * FROM `logs` $whereSQL ORDER BY ts DESC LIMIT $limit OFFSET $offset");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['id_log'] = (int) $row['id_log'];
            $row['uid'] = $row['uid'] ? (int) $row['uid'] : null;
            $row['duration_ms'] = $row['duration_ms'] ? (int) $row['duration_ms'] : null;
            $entries[] = $row;
        }
    }

    // Stats
    $stats = ['top_ips' => [], 'actions' => [], 'top_pages' => [], 'sessions' => 0];

    $res = $conn->query("SELECT ip, COUNT(*) as cnt FROM `logs` $whereSQL GROUP BY ip ORDER BY cnt DESC LIMIT 20");
    if ($res) while ($r = $res->fetch_assoc()) $stats['top_ips'][$r['ip']] = (int) $r['cnt'];

    $res = $conn->query("SELECT action, COUNT(*) as cnt FROM `logs` $whereSQL GROUP BY action ORDER BY cnt DESC");
    if ($res) while ($r = $res->fetch_assoc()) $stats['actions'][$r['action']] = (int) $r['cnt'];

    $res = $conn->query("SELECT page, COUNT(*) as cnt FROM `logs` $whereSQL GROUP BY page ORDER BY cnt DESC LIMIT 20");
    if ($res) while ($r = $res->fetch_assoc()) $stats['top_pages'][$r['page']] = (int) $r['cnt'];

    $res = $conn->query("SELECT COUNT(DISTINCT sid) as cnt FROM `logs` $whereSQL");
    if ($res) $stats['sessions'] = (int) $res->fetch_assoc()['cnt'];

    jsonResponse([
        'success'  => true,
        'total'    => $total,
        'offset'   => $offset,
        'limit'    => $limit,
        'entries'  => $entries,
        'stats'    => $stats,
    ]);

} else {
    jsonResponse(['success' => false, 'error' => 'Methode non supportee'], 405);
}
