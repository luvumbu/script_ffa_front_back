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

    case 'test_mail':
        $to = trim($_GET['to'] ?? '');
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) { $out['error'] = 'Parametre to requis (email valide)'; break; }
        $subject = 'Test email Bokonzi';
        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;padding:40px;text-align:center;">';
        $body .= '<h1 style="color:#6c5ce7;">Test email Bokonzi</h1>';
        $body .= '<p>Si vous recevez cet email, la fonction mail() fonctionne correctement.</p>';
        $body .= '<p style="color:#999;font-size:12px;">Envoye le ' . date('Y-m-d H:i:s') . '</p>';
        $body .= '</body></html>';
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Bokonzi <noreply@bokonzi.com>\r\n";
        $headers .= "Reply-To: noreply@bokonzi.com\r\n";
        $sent = mail($to, $subject, $body, $headers);
        $out['to'] = $to;
        $out['sent'] = $sent;
        $out['message'] = $sent ? 'Email envoye (verifie spam/inbox)' : 'Echec mail() — verifier config SMTP Hostinger';
        $out['mail_error'] = error_get_last();
        break;

    case 'test_mail_plain':
        $to = trim($_GET['to'] ?? '');
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) { $out['error'] = 'Parametre to requis'; break; }
        $subject = 'Test simple Bokonzi ' . date('H:i:s');
        $body = 'Ceci est un test simple en texte brut. ' . date('Y-m-d H:i:s');
        $headers = "From: noreply@bokonzi.com\r\n";
        $sent = mail($to, $subject, $body, $headers);
        $out['to'] = $to;
        $out['sent'] = $sent;
        $out['method'] = 'plain text';
        // Aussi tester phpinfo mail
        $out['sendmail_path'] = ini_get('sendmail_path');
        $out['smtp'] = ini_get('SMTP');
        $out['smtp_port'] = ini_get('smtp_port');
        break;

    case 'send_welcome':
        $to = trim($_GET['to'] ?? '');
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) { $out['error'] = 'Parametre to requis'; break; }
        $stmt = $conn->prepare("SELECT prenom FROM users WHERE email = ?");
        $stmt->bind_param('s', $to);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $prenom = $row['prenom'] ?? 'Athlete';
        $h = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0;">';
        $html .= '<div style="max-width:600px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.1);">';
        $html .= '<div style="background:linear-gradient(135deg,#6c5ce7,#5541d0);padding:40px;text-align:center;color:#fff;">';
        $html .= '<div style="font-size:48px;margin-bottom:12px;">&#127939;</div>';
        $html .= '<h1 style="margin:0 0 8px;font-size:28px;">Bienvenue sur Bokonzi !</h1>';
        $html .= '<p style="margin:0;opacity:0.9;font-size:16px;">La base de donnees de l\'athletisme francais</p>';
        $html .= '</div>';
        $html .= '<div style="padding:32px 40px;">';
        $html .= '<p style="font-size:16px;color:#333;line-height:1.6;">Bonjour <strong>' . $h($prenom) . '</strong>,</p>';
        $html .= '<p style="font-size:15px;color:#555;line-height:1.6;">Votre compte a ete cree avec succes. Vous avez maintenant acces a toutes les fonctionnalites de Bokonzi :</p>';
        $html .= '<div style="margin:24px 0;">';
        $features = [
            ['&#128269;', 'Recherche avancee', 'Explorez plus de 330 000 athletes avec 12 filtres combinables'],
            ['&#9825;', 'Suivi athletes & clubs', 'Suivez vos athletes et clubs favoris pour ne rien manquer'],
            ['&#128196;', 'Fiches PDF', 'Telechargez les fiches completes des athletes'],
            ['&#127942;', 'Classements', 'Consultez les classements par epreuve en temps reel'],
        ];
        foreach ($features as $f) {
            $html .= '<div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:16px;">';
            $html .= '<div style="font-size:24px;flex-shrink:0;">' . $f[0] . '</div>';
            $html .= '<div><strong style="color:#333;font-size:14px;">' . $h($f[1]) . '</strong><br><span style="color:#777;font-size:13px;">' . $h($f[2]) . '</span></div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '<div style="text-align:center;margin:32px 0 16px;">';
        $html .= '<a href="https://bokonzi.com" style="display:inline-block;background:linear-gradient(135deg,#6c5ce7,#5541d0);color:#fff;text-decoration:none;padding:14px 40px;border-radius:8px;font-size:16px;font-weight:600;">Explorer Bokonzi</a>';
        $html .= '</div></div>';
        $html .= '<div style="padding:20px 40px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;color:#9ca3af;font-size:12px;">';
        $html .= '<p>Cet email a ete envoye automatiquement lors de la creation de votre compte.</p>';
        $html .= '<p><a href="https://bokonzi.com" style="color:#6c5ce7;">bokonzi.com</a></p>';
        $html .= '</div></div></body></html>';

        $subject = 'Bienvenue sur Bokonzi, ' . $prenom . ' !';
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Bokonzi <noreply@bokonzi.com>\r\n";
        $headers .= "Reply-To: noreply@bokonzi.com\r\n";
        $sent = mail($to, $subject, $html, $headers);
        $out['to'] = $to;
        $out['sent'] = $sent;
        $out['message'] = $sent ? 'Email bienvenue envoye' : 'Echec mail()';
        break;

    case 'welcome_log':
        $logFile = __DIR__ . '/../logs/.welcome_mail_log.php';
        if (file_exists($logFile)) {
            $raw = file_get_contents($logFile);
            $out['log'] = json_decode(substr($raw, strpos($raw, "\n") + 1), true);
        } else {
            $out['log'] = null;
            $out['message'] = 'Aucun log — email de bienvenue jamais envoye';
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
