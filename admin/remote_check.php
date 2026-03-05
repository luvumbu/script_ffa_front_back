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

    case 'delete_user':
        $delEmail = trim($_GET['email'] ?? '');
        if (empty($delEmail)) { $out['error'] = 'Parametre email requis'; break; }
        $stmt = $conn->prepare("SELECT id_user FROM users WHERE email = ?");
        $stmt->bind_param('s', $delEmail);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) { $out['error'] = 'User introuvable'; break; }
        $uid = (int)$row['id_user'];
        $conn->query("DELETE FROM user_sessions WHERE id_user = $uid");
        $conn->query("DELETE FROM users WHERE id_user = $uid");
        $out['message'] = 'User supprime';
        $out['email'] = $delEmail;
        $out['id_user'] = $uid;
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

        $html = '<html><body style="font-family:Arial,sans-serif;margin:0;padding:20px;background:#f4f4f4;">'
            . '<div style="max-width:500px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;">'
            . '<h1 style="color:#6c5ce7;text-align:center;">Bienvenue sur Bokonzi !</h1>'
            . '<p>Bonjour <b>' . htmlspecialchars($prenom) . '</b>,</p>'
            . '<p>Votre compte a ete cree avec succes. Vous pouvez maintenant :</p>'
            . '<ul>'
            . '<li>Rechercher parmi 330 000+ athletes</li>'
            . '<li>Suivre vos athletes et clubs favoris</li>'
            . '<li>Telecharger des fiches PDF</li>'
            . '<li>Consulter les classements en temps reel</li>'
            . '</ul>'
            . '<p style="text-align:center;margin-top:20px;"><a href="https://bokonzi.com" style="background:#6c5ce7;color:#fff;padding:12px 30px;text-decoration:none;border-radius:6px;">Explorer Bokonzi</a></p>'
            . '<p style="color:#999;font-size:11px;text-align:center;margin-top:20px;">bokonzi.com</p>'
            . '</div></body></html>';

        $subject = 'Bienvenue sur Bokonzi, ' . $prenom . ' !';
        $headers = "From: noreply@bokonzi.com\r\nContent-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0";
        $sent = mail($to, $subject, $html, $headers);
        $out['to'] = $to;
        $out['sent'] = $sent;
        $out['html_length'] = strlen($html);
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

    case 'test_scrape':
        // Tester le scraping d'un athlete (par ID ou premier lien de nom_et_liens)
        require_once __DIR__ . '/../Class/AthleteScraper.php';
        require_once __DIR__ . '/../core/insert_athle.php';

        $athId = trim($_GET['id'] ?? '');
        $skipBdd = isset($_GET['skip_bdd']);
        $forceRescrape = isset($_GET['force']);

        // Si pas d'ID, prendre le premier de nom_et_liens
        if (empty($athId)) {
            $r = $conn->query("SELECT url FROM nom_et_liens ORDER BY id_nom_et_liens LIMIT 1");
            if ($r && $row = $r->fetch_assoc()) {
                $url = $row['url'];
                if (preg_match('#/athletes/(\d+)#', $url, $m)) $athId = $m[1];
                $out['source'] = 'nom_et_liens (premier lien)';
                $out['url'] = $url;
            }
        }

        if (empty($athId)) { $out['error'] = 'Aucun ID athlete. Param: ?id=123456 ou table nom_et_liens vide'; break; }

        $athId = (int)$athId;
        $out['athlete_id'] = $athId;

        // Verifier si deja en BDD
        $r = $conn->query("SELECT id_athlete, nom_complet_athlete FROM athletes WHERE athlete_id_externe = $athId LIMIT 1");
        $existant = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
        $out['deja_en_bdd'] = $existant ? true : false;
        if ($existant) $out['nom_existant'] = $existant['nom_complet_athlete'];

        if ($existant && !$forceRescrape) {
            $out['message'] = 'Athlete deja en BDD. Ajouter &force pour re-scraper.';
            break;
        }

        // Scraper
        $t0 = microtime(true);
        try {
            $scraper = new AthleteScraper($athId);
            $data = $scraper->scrapeAll();
            $scrapeTime = round((microtime(true) - $t0) * 1000);

            $out['scrape_time_ms'] = $scrapeTime;
            $out['scrape_success'] = $data['success'] ?? false;

            if ($data['success']) {
                $out['identite'] = $data['identite'];
                $out['stats'] = [
                    'clubs' => count($data['clubs']),
                    'records' => count($data['records']),
                    'medailles' => count($data['medailles']),
                    'selections' => count($data['selections']),
                    'progressions' => count($data['progressions']),
                    'podiums' => count($data['podiums']),
                    'resultats' => count($data['resultats']),
                    'niveaux' => count($data['niveaux']),
                ];

                // Inserer en BDD sauf si skip_bdd
                if (!$skipBdd) {
                    $cache = loadRefCache($conn);
                    ob_start();
                    insertAthleteData($scraper, $conn, $cache);
                    $insertOutput = ob_get_clean();
                    $out['insert'] = 'OK';
                    $out['insert_detail'] = strip_tags($insertOutput);
                } else {
                    $out['insert'] = 'skip (skip_bdd)';
                }

                $out['message'] = 'Scraping reussi : ' . ($data['identite']['nom_complet'] ?? '?');
            } else {
                $out['message'] = $data['message'] ?? 'Echec scraping';
            }
        } catch (Exception $e) {
            $out['scrape_time_ms'] = round((microtime(true) - $t0) * 1000);
            $out['error'] = $e->getMessage();
        }
        break;

    case 'scrape_status':
        // Verifier l'etat du scraping : combien dans nom_et_liens, combien en BDD, combien restants
        $totalUrls = 0; $totalBdd = 0;
        $r = $conn->query("SELECT COUNT(*) as c FROM nom_et_liens"); if ($r) $totalUrls = (int)$r->fetch_assoc()['c'];
        $r = $conn->query("SELECT COUNT(*) as c FROM athletes"); if ($r) $totalBdd = (int)$r->fetch_assoc()['c'];
        $out['total_urls'] = $totalUrls;
        $out['total_bdd'] = $totalBdd;
        $out['restants'] = max(0, $totalUrls - $totalBdd);
        $out['pct'] = $totalUrls > 0 ? round(($totalBdd / $totalUrls) * 100, 2) : 0;
        // Progression fichier
        $progFile = __DIR__ . '/../progress.txt';
        $out['progress_file'] = file_exists($progFile) ? (int)file_get_contents($progFile) : 0;
        break;

    default:
        $out['error'] = 'Action inconnue. Actions: ping, users, sessions, columns, count, logs, query, search_limit, search_fill, search_max, search_reset, create_test_user, delete_test_user, test_search, test_scrape, scrape_status, my_ip';
}

$conn->close();
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
