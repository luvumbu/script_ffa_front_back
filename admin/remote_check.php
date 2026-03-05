<?php
/**
 * remote_check.php — Outil de verification a distance (permanent)
 * Securise par cle API uniquement
 *
 * Usage :
 *   ?bk_key=...&action=users           → liste tous les users
 *   ?bk_key=...&action=columns&table=X → colonnes d'une table
 *   ?bk_key=...&action=count            → compteurs toutes tables
 *   ?bk_key=...&action=search_limit     → status limites recherche (toutes IPs)
 *   ?bk_key=...&action=search_fill&ip=X → mettre IP a 49/50
 *   ?bk_key=...&action=search_max&ip=X  → mettre IP a 50/50 (bloque)
 *   ?bk_key=...&action=search_reset&ip=X→ remettre IP a 0
 *   ?bk_key=...&action=query&q=SELECT...→ requete SQL en lecture seule (SELECT)
 *   ?bk_key=...&action=logs&limit=20    → derniers logs
 *   ?bk_key=...&action=sessions         → sessions actives
 *   ?bk_key=...&action=ping             → test connexion BDD
 */

$key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') {
    http_response_code(403);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Interdit']));
}

require_once __DIR__ . '/../core/db.php';

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? 'ping';
$out = ['action' => $action, 'time' => date('Y-m-d H:i:s')];

switch ($action) {

    case 'ping':
        $out['status'] = 'ok';
        $out['db'] = $conn->ping() ? 'connected' : 'error';
        break;

    case 'users':
        $r = $conn->query("SELECT id_user, email, nom, prenom, role, google_id, oauth_provider, picture, email_verified, locale, last_login, date_creation FROM users ORDER BY id_user");
        $out['users'] = [];
        while ($row = $r->fetch_assoc()) $out['users'][] = $row;
        $out['total'] = count($out['users']);
        break;

    case 'sessions':
        $r = $conn->query("SELECT s.id_session, s.id_user, u.email, s.created_at, s.expire_at, (s.expire_at > NOW()) as active FROM user_sessions s JOIN users u ON u.id_user = s.id_user ORDER BY s.created_at DESC LIMIT 50");
        $out['sessions'] = [];
        while ($row = $r->fetch_assoc()) $out['sessions'][] = $row;
        break;

    case 'columns':
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['table'] ?? 'users');
        $r = $conn->query("SHOW COLUMNS FROM `$table`");
        $out['table'] = $table;
        $out['columns'] = [];
        if ($r) while ($row = $r->fetch_assoc()) $out['columns'][] = $row;
        else $out['error'] = 'Table introuvable';
        break;

    case 'count':
        $tables = ['athletes','clubs','epreuves','villes','competitions','users','user_sessions','logs','search_tracking','athlete_follows','club_follows','email_subscribers','contact_messages'];
        $out['counts'] = [];
        foreach ($tables as $t) {
            $r = $conn->query("SELECT COUNT(*) as c FROM `$t`");
            $out['counts'][$t] = $r ? (int)$r->fetch_assoc()['c'] : 'error';
        }
        break;

    case 'logs':
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $r = $conn->query("SELECT * FROM logs ORDER BY ts DESC LIMIT $limit");
        $out['logs'] = [];
        while ($row = $r->fetch_assoc()) $out['logs'][] = $row;
        break;

    case 'query':
        $q = trim($_GET['q'] ?? '');
        if (empty($q)) { $out['error'] = 'Parametre q requis'; break; }
        // Securite : SELECT uniquement
        if (!preg_match('/^\s*SELECT\s/i', $q)) {
            $out['error'] = 'Seules les requetes SELECT sont autorisees';
            break;
        }
        $r = $conn->query($q);
        if (!$r) { $out['error'] = $conn->error; break; }
        $out['rows'] = [];
        while ($row = $r->fetch_assoc()) $out['rows'][] = $row;
        $out['total'] = count($out['rows']);
        break;

    // --- Search limit tools ---
    case 'search_limit':
        $limFile = __DIR__ . '/../logs/.search_limits.php';
        $limData = [];
        if (file_exists($limFile)) {
            $raw = file_get_contents($limFile);
            $limData = @json_decode(substr($raw, strpos($raw, "\n") + 1), true) ?: [];
        }
        $out['data'] = $limData;
        break;

    case 'search_fill':
    case 'search_max':
    case 'search_reset':
        $ip = trim($_GET['ip'] ?? '');
        if (empty($ip)) { $out['error'] = 'Parametre ip requis'; break; }
        $limFile = __DIR__ . '/../logs/.search_limits.php';
        $today = date('Y-m-d');
        $limData = [];
        if (file_exists($limFile)) {
            $raw = file_get_contents($limFile);
            $limData = @json_decode(substr($raw, strpos($raw, "\n") + 1), true) ?: [];
        }
        if (($limData['_date'] ?? '') !== $today) $limData = ['_date' => $today];
        if ($action === 'search_fill') $limData[$ip] = 49;
        elseif ($action === 'search_max') $limData[$ip] = 50;
        else unset($limData[$ip]);
        file_put_contents($limFile, "<?php die(); ?>\n" . json_encode($limData, JSON_UNESCAPED_UNICODE));
        $val = $action === 'search_reset' ? 0 : ($action === 'search_fill' ? 49 : 50);
        $out['ip'] = $ip;
        $out['count'] = $val;
        $out['message'] = $action === 'search_reset' ? 'Reset OK' : "Compteur mis a $val/50";
        break;

    case 'create_test_user':
        $testEmail = 'claude.test@bokonzi.com';
        $r = $conn->query("SELECT id_user FROM users WHERE email = '" . $conn->real_escape_string($testEmail) . "'");
        if ($r && $r->num_rows > 0) {
            $out['message'] = 'User test existe deja';
            $out['user'] = $r->fetch_assoc();
        } else {
            $conn->query("INSERT INTO users (email, password_hash, nom, prenom, role, oauth_provider) VALUES ('$testEmail', '', 'Test', 'Claude', 'athlete', 'test')");
            $out['message'] = 'User test cree';
            $out['user'] = ['id_user' => $conn->insert_id, 'email' => $testEmail];
        }
        break;

    case 'delete_test_user':
        $testEmail = 'claude.test@bokonzi.com';
        $conn->query("DELETE FROM user_sessions WHERE id_user IN (SELECT id_user FROM users WHERE email = '" . $conn->real_escape_string($testEmail) . "')");
        $conn->query("DELETE FROM users WHERE email = '" . $conn->real_escape_string($testEmail) . "'");
        $out['message'] = 'User test supprime';
        break;

    case 'test_search':
        // Simule une recherche comme un user connecte (compte dans le rate limit)
        $q = trim($_GET['q'] ?? 'dupont');
        $ip = trim($_GET['ip'] ?? '');
        if (empty($ip)) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        $limFile = __DIR__ . '/../logs/.search_limits.php';
        $today = date('Y-m-d');
        $limData = [];
        if (file_exists($limFile)) {
            $raw = file_get_contents($limFile);
            $limData = @json_decode(substr($raw, strpos($raw, "\n") + 1), true) ?: [];
        }
        if (($limData['_date'] ?? '') !== $today) $limData = ['_date' => $today];
        $cnt = (int)($limData[$ip] ?? 0);
        $out['ip'] = $ip;
        $out['count_before'] = $cnt;
        $out['limit'] = 50;
        $out['blocked'] = $cnt >= 50;
        if ($cnt >= 50) {
            $out['message'] = 'BLOQUE — limite atteinte (' . $cnt . '/50)';
        } else {
            $limData[$ip] = $cnt + 1;
            file_put_contents($limFile, "<?php die(); ?>\n" . json_encode($limData, JSON_UNESCAPED_UNICODE));
            $out['count_after'] = $cnt + 1;
            $out['remaining'] = 50 - ($cnt + 1);
            $out['message'] = 'Recherche OK (' . ($cnt + 1) . '/50)';
        }
        break;

    case 'my_ip':
        $out['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? null;
        $out['cf_ip'] = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
        $out['forwarded'] = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        $out['detected'] = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        break;

    default:
        $out['error'] = 'Action inconnue. Actions: ping, users, sessions, columns, count, logs, query, search_limit, search_fill, search_max, search_reset, create_test_user, delete_test_user, test_search, my_ip';
}

$conn->close();
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
