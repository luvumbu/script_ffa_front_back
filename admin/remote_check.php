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
 *   ?bk_key=...&action=search_fill&ip=X → mettre IP a 1 recherche sous la limite
 *   ?bk_key=...&action=search_max&ip=X  → mettre IP a la limite (bloque)
 *   ?bk_key=...&action=search_reset&ip=X→ remettre IP a 0
 *   ?bk_key=...&action=query&q=SELECT...→ requete SQL en lecture seule (SELECT)
 *   ?bk_key=...&action=logs&limit=20    → derniers logs
 *   ?bk_key=...&action=sessions         → sessions actives
 *   ?bk_key=...&action=ping             → test connexion BDD
 *   ?bk_key=...&action=demo_list        → démos Platine lancées + taux de conversion
 *   ?bk_key=...&action=demo_reset&email=X → redonne le droit à une démo (ou &id_user=)
 *   ?bk_key=...&action=webhook_log&limit=50 → journal du webhook Stripe (paiements reçus)
 */

$key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') {
    http_response_code(403);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Interdit']));
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/search_limit.php'; // BK_SEARCH_LIMIT_FREE (limite recherches/jour)

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

    // ── Démo Platine 5 min : suivi & pilotage ───────────────────────────────
    case 'demo_list':
        // Démos lancées + taux de conversion (abonnement actif ensuite).
        $out['demo'] = function_exists('bkDemoListAll')
            ? bkDemoListAll($conn)
            : ['error' => 'demo_mode indisponible'];
        break;

    case 'demo_reset':
        // Redonne le droit à une démo (par ?email= ou ?id_user=).
        $uid   = (int)($_GET['id_user'] ?? 0);
        $email = trim($_GET['email'] ?? '');
        if ($uid <= 0 && $email !== '') {
            $st = $conn->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
            $st->bind_param("s", $email); $st->execute();
            $r = $st->get_result()->fetch_assoc(); $st->close();
            if ($r) $uid = (int)$r['id_user'];
        }
        if ($uid <= 0) { $out['ok'] = false; $out['error'] = 'Utilisateur introuvable'; break; }
        $out['ok']      = function_exists('bkDemoResetUid') ? bkDemoResetUid($uid) : false;
        $out['id_user'] = $uid;
        $out['message'] = $out['ok']
            ? 'Démo réinitialisée — le membre peut en relancer une.'
            : 'Aucune démo enregistrée pour ce membre.';
        break;

    // ── Journal du webhook Stripe (filet anti paiement perdu) ───────────────
    case 'webhook_log':
        $f = __DIR__ . '/../logs/.stripe_webhook.php';
        if (!file_exists($f)) {
            $out['exists'] = false;
            $out['lines']  = [];
            $out['note']   = 'Aucun événement Stripe reçu pour le moment (fichier inexistant).';
            break;
        }
        $raw  = file_get_contents($f);
        $pos  = strpos($raw, "\n");
        $body = $pos !== false ? substr($raw, $pos + 1) : '';
        $all  = array_values(array_filter(explode("\n", $body), 'strlen'));
        $n    = max(1, min(500, (int)($_GET['limit'] ?? 50)));
        $out['exists']      = true;
        $out['total_lines'] = count($all);
        $out['lines']       = array_slice($all, -$n);
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
        $out['data']             = $limData;
        $out['limit_free']       = defined('BK_SEARCH_LIMIT_FREE') ? BK_SEARCH_LIMIT_FREE : null;
        $out['cooldown_seconds'] = defined('BK_SEARCH_COOLDOWN')   ? BK_SEARCH_COOLDOWN   : null;
        $out['trial_anon']       = defined('BK_SEARCH_TRIAL_ANON') ? BK_SEARCH_TRIAL_ANON : null;
        break;

    // Diagnostic complet : pour un user_id donne, simule l'appel a bkSearchLimit
    // SANS consommer, en injectant directement la session associee. Renvoie l'etat
    // tel que vu par le serveur (search_used, search_limit, blocked, reason, etc.)
    case 'search_diag':
        $uid = (int)($_GET['user_id'] ?? 0);
        if ($uid <= 0) { $out['error'] = 'Parametre user_id requis'; break; }
        // 1) Recupere un token de session valide pour ce user
        $st = $conn->prepare("SELECT token FROM user_sessions WHERE id_user = ? AND expire_at > NOW() ORDER BY id_session DESC LIMIT 1");
        $st->bind_param("i", $uid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { $out['error'] = "Aucune session active pour user_id=$uid"; break; }
        // 2) Injecte le cookie + UA d'un navigateur pour mimer la requete reelle
        $_COOKIE['bk_token']        = $row['token'];
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (BK-Diag)';
        // 3) Charge la logique et appelle bkSearchLimit en mode lecture seule
        if (!function_exists('bkSearchLimit')) require_once __DIR__ . '/../core/search_limit.php';
        $sl = bkSearchLimit($conn, false);
        $out['user_id']     = $uid;
        $out['result']      = $sl;
        $out['limit_free']  = defined('BK_SEARCH_LIMIT_FREE') ? BK_SEARCH_LIMIT_FREE : null;
        $out['cooldown']    = defined('BK_SEARCH_COOLDOWN')   ? BK_SEARCH_COOLDOWN   : null;
        // Etat brut du compteur pour ce user
        $limFile = __DIR__ . '/../logs/.search_limits.php';
        $entry = null;
        if (file_exists($limFile)) {
            $raw = file_get_contents($limFile);
            $d = @json_decode(substr($raw, strpos($raw, "\n") + 1), true) ?: [];
            $entry = $d['u' . $uid] ?? null;
            $out['counter_date']  = $d['_date'] ?? null;
        }
        $out['counter_entry'] = $entry;
        break;

    // Etat / reset du compteur de fiches profil (logs/.profile_views.php)
    case 'profile_views':
        $pv = __DIR__ . '/../logs/.profile_views.php';
        $pvd = [];
        if (file_exists($pv)) {
            $raw = file_get_contents($pv);
            $pvd = @json_decode(substr($raw, strpos($raw, "\n") + 1), true) ?: [];
        }
        $out['data']        = $pvd;
        $out['per_day']     = defined('BK_PROFILE_FREE_PER_DAY') ? BK_PROFILE_FREE_PER_DAY : null;
        $out['seconds']     = defined('BK_PROFILE_FREE_SECONDS') ? BK_PROFILE_FREE_SECONDS : null;
        break;

    case 'profile_wipe':
        $pv = __DIR__ . '/../logs/.profile_views.php';
        if (file_exists($pv)) { @unlink($pv); $out['message'] = 'Compteur fiches profil efface'; }
        else { $out['message'] = 'Aucun compteur'; }
        $out['per_day'] = defined('BK_PROFILE_FREE_PER_DAY') ? BK_PROFILE_FREE_PER_DAY : null;
        break;

    // Etat du compteur anti-scraping (logs/.page_limits.php)
    case 'page_limits':
        $pf = __DIR__ . '/../logs/.page_limits.php';
        $pd = [];
        if (file_exists($pf)) {
            $raw = file_get_contents($pf);
            $pd  = @json_decode(substr($raw, strpos($raw, "\n") + 1), true) ?: [];
        }
        $out['data']   = $pd;
        $out['exists'] = file_exists($pf);
        break;

    // Efface le compteur anti-scraping
    case 'page_wipe':
        $pf = __DIR__ . '/../logs/.page_limits.php';
        if (file_exists($pf)) { @unlink($pf); $out['message'] = 'Compteur pages efface'; }
        else { $out['message'] = 'Aucun compteur'; }
        break;

    // Efface integralement le fichier compteur (remet a 0 TOUS les utilisateurs)
    case 'search_wipe':
        $limFile = __DIR__ . '/../logs/.search_limits.php';
        if (file_exists($limFile)) {
            @unlink($limFile);
            $out['message'] = 'Compteurs effaces — tous les utilisateurs ont un quota plein';
        } else {
            $out['message'] = 'Aucun compteur a effacer (fichier inexistant)';
        }
        $out['limit_free'] = defined('BK_SEARCH_LIMIT_FREE') ? BK_SEARCH_LIMIT_FREE : null;
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
        $max = BK_SEARCH_LIMIT_FREE;
        if ($action === 'search_fill') $limData[$ip] = max(0, $max - 1);
        elseif ($action === 'search_max') $limData[$ip] = $max;
        else unset($limData[$ip]);
        file_put_contents($limFile, "<?php die(); ?>\n" . json_encode($limData, JSON_UNESCAPED_UNICODE));
        $val = $action === 'search_reset' ? 0 : ($action === 'search_fill' ? max(0, $max - 1) : $max);
        $out['ip'] = $ip;
        $out['count'] = $val;
        $out['message'] = $action === 'search_reset' ? 'Reset OK' : "Compteur mis a $val/$max";
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
        $max = BK_SEARCH_LIMIT_FREE;
        $out['ip'] = $ip;
        $out['count_before'] = $cnt;
        $out['limit'] = $max;
        $out['blocked'] = $cnt >= $max;
        if ($cnt >= $max) {
            $out['message'] = 'BLOQUE — limite atteinte (' . $cnt . '/' . $max . ')';
        } else {
            $limData[$ip] = $cnt + 1;
            file_put_contents($limFile, "<?php die(); ?>\n" . json_encode($limData, JSON_UNESCAPED_UNICODE));
            $out['count_after'] = $cnt + 1;
            $out['remaining'] = $max - ($cnt + 1);
            $out['message'] = 'Recherche OK (' . ($cnt + 1) . '/' . $max . ')';
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

    case 'prog_errors':
        // Affiche les dernieres lignes du log d'erreurs progressions
        $errFile = __DIR__ . '/../archives/.prog_idx/_errors.log';
        if (!file_exists($errFile)) {
            $out['log_file'] = $errFile;
            $out['exists']   = false;
            $out['message']  = 'Aucun log : pas encore d\'erreur ou pas de scrape recent depuis le patch.';
            break;
        }
        $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
        $lines = file($errFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $out['log_file']  = basename($errFile);
        $out['log_size']  = filesize($errFile);
        $out['log_lines'] = count($lines);
        $out['log_tail']  = array_slice($lines, -$limit);
        break;

    case 'prog_diag':
        // Diagnostic du store progressions file-based
        require_once __DIR__ . '/../core/progressions_store.php';
        require_once __DIR__ . '/../core/data_source.php';
        $idAthlete = (int)($_GET['id_athlete'] ?? 0); // ID INTERNE (pas athlete_id_externe)
        $src = progStoreSourcePath();
        $out['prog_mode']       = dataSourceMode('athlete_progressions');
        $out['prog_file']       = $src;
        $out['prog_file_exists']= file_exists($src);
        $out['prog_file_size']  = file_exists($src) ? filesize($src) : 0;
        $out['prog_file_mtime'] = file_exists($src) ? date('Y-m-d H:i:s', filemtime($src)) : null;
        $out['prog_file_age_s'] = file_exists($src) ? (time() - filemtime($src)) : null;
        if ($idAthlete > 0) {
            $rows = progStoreLoadForAthlete($idAthlete);
            $out['athlete_id']     = $idAthlete;
            $out['athlete_rows']   = count($rows);
            // Affiche les 5 dernieres entrees telles que stockees (raw rows du store)
            $out['athlete_sample'] = array_slice($rows, -5);
            // Date de la derniere progression connue
            $latestDate = null;
            foreach ($rows as $r) {
                $d = $r['date_progression'] ?? null;
                if ($d && (!$latestDate || strcmp($d, $latestDate) > 0)) $latestDate = $d;
            }
            $out['athlete_latest_date'] = $latestDate;
        }
        break;

    // Accorder un abonnement à un membre (filet quand le webhook n'a rien enregistré).
    //   ?action=grant_sub&email=X&plan=bronze|argent|or|platine[&months=N]   (N=0 => illimité)
    case 'grant_sub':
        require_once __DIR__ . '/../core/stripe_config.php'; // $BK_PLANS
        $email  = trim($_GET['email'] ?? '');
        $plan   = strtolower(trim($_GET['plan'] ?? ''));
        $months = (int)($_GET['months'] ?? 0);
        if ($email === '' || !isset($BK_PLANS[$plan])) { $out['error'] = 'Param email + plan (bronze|argent|or|platine) requis'; break; }
        $st = $conn->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
        $st->bind_param("s", $email); $st->execute();
        $u = $st->get_result()->fetch_assoc(); $st->close();
        if (!$u) { $out['error'] = "Aucun compte pour $email"; break; }
        $uid = (int)$u['id_user'];
        $periodEnd = $months > 0 ? date('Y-m-d H:i:s', strtotime("+$months months")) : null;
        $sql = "INSERT INTO subscriptions
                    (id_user, plan, status, billing_period, current_period_end, cancel_at_period_end, stripe_subscription_id)
                VALUES (?, ?, 'active', 'manuel', ?, 0, NULL)
                ON DUPLICATE KEY UPDATE
                    plan = VALUES(plan), status = 'active', billing_period = 'manuel',
                    current_period_end = VALUES(current_period_end), cancel_at_period_end = 0";
        $st = $conn->prepare($sql);
        $st->bind_param("iss", $uid, $plan, $periodEnd);
        $out['ok'] = $st->execute();
        $st->close();
        $out['id_user'] = $uid;
        $out['plan'] = $plan;
        $out['until'] = $periodEnd ?: 'illimité';
        $out['message'] = $out['ok'] ? "Abonnement $plan accordé à $email" : ('Erreur SQL : ' . $conn->error);
        break;

    // Envoyer l'email de bienvenue / remerciement propre.
    //   ?action=send_sub_welcome&email=X[&plan=bronze][&force=1]
    case 'send_sub_welcome':
        require_once __DIR__ . '/../core/subscription.php';
        require_once __DIR__ . '/../core/emails.php';
        $email = trim($_GET['email'] ?? '');
        $plan  = strtolower(trim($_GET['plan'] ?? '')) ?: null;
        $force = isset($_GET['force']);
        if ($email === '') { $out['error'] = 'Param email requis'; break; }
        $st = $conn->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
        $st->bind_param("s", $email); $st->execute();
        $u = $st->get_result()->fetch_assoc(); $st->close();
        if (!$u) { $out['error'] = "Aucun compte pour $email"; break; }
        $out['result'] = bkSendSubscriptionWelcome($conn, (int)$u['id_user'], $plan, $force);
        break;

    // Envoyer (ou renvoyer) la notification admin pour un abonnement.
    //   ?action=notify_admin_sub&email=X[&plan=argent]
    case 'notify_admin_sub':
        require_once __DIR__ . '/../core/subscription.php';
        require_once __DIR__ . '/../core/emails.php';
        $email = trim($_GET['email'] ?? '');
        $plan  = strtolower(trim($_GET['plan'] ?? '')) ?: null;
        if ($email === '') { $out['error'] = 'Param email requis'; break; }
        $st = $conn->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
        $st->bind_param("s", $email); $st->execute();
        $u = $st->get_result()->fetch_assoc(); $st->close();
        if (!$u) { $out['error'] = "Aucun compte pour $email"; break; }
        if (!$plan && function_exists('getUserPlan')) $plan = getUserPlan($conn, (int)$u['id_user']);
        $out['sent'] = bkNotifyAdminNewSubscription($conn, (int)$u['id_user'], $plan);
        $out['to']   = BK_SMTP_FROM_EMAIL;
        $out['message'] = $out['sent'] ? 'Notification admin envoyée' : 'Échec envoi notification admin';
        break;

    case 'my_ip':
        $out['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? null;
        $out['cf_ip'] = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
        $out['forwarded'] = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        $out['detected'] = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        break;

    case 'scrape_status':
        // Snapshot complet de l'etat des 2 pipelines de scraping (v2 et principal).
        // Permet une verification a distance : "la BDD a-t-elle ete mise a jour ?"
        $base = dirname(__DIR__);

        // --- Pipeline scraping_v2 (URLs vers nom_et_liens) ---
        $v2Flag     = $base . '/scraping_v2/state/scraper_v2_running.flag';
        $v2Prog     = $base . '/scraping_v2/state/scraper_v2_progress.json';
        $v2Progress = file_exists($v2Prog) ? @json_decode(file_get_contents($v2Prog), true) : null;
        $out['scraping_v2'] = [
            'running'      => file_exists($v2Flag),
            'flag_age_s'   => file_exists($v2Flag) ? (time() - filemtime($v2Flag)) : null,
            'progress'     => $v2Progress,
            'last_action'  => $v2Progress['last_action_at'] ?? null,
            'last_url'     => $v2Progress['last_url'] ?? null,
            'annees'       => $v2Progress['annees'] ?? null,
            'inserts'      => $v2Progress['stats']['athletes_inserts'] ?? 0,
            'pages_done'   => $v2Progress['stats']['pages_traitees'] ?? 0,
            'pages_total'  => $v2Progress['stats']['pages_total'] ?? 0,
            'fetch_errors' => $v2Progress['stats']['fetch_errors'] ?? 0,
        ];

        // --- Pipeline scraper.php principal (URL -> 9 tables BDD) ---
        $mainFlag     = $base . '/scraping_running.flag';
        $mainProgFile = $base . '/progress.txt';
        $mainProgress = file_exists($mainProgFile) ? (int)trim(file_get_contents($mainProgFile)) : 0;
        $out['scraper_principal'] = [
            'running'    => file_exists($mainFlag),
            'flag_age_s' => file_exists($mainFlag) ? (time() - filemtime($mainFlag)) : null,
            'position'   => $mainProgress,
            'failed_count' => 0,
        ];
        $failedFile = $base . '/failed.json';
        if (file_exists($failedFile)) {
            $f = @json_decode(file_get_contents($failedFile), true);
            $out['scraper_principal']['failed_count'] = is_array($f) ? count($f) : 0;
        }

        // --- Etat de la BDD : compteurs critiques pour le scraping ---
        $rNol     = $conn->query("SELECT COUNT(*) AS c FROM nom_et_liens");
        $rAth     = $conn->query("SELECT COUNT(*) AS c FROM athletes");
        $rRec     = $conn->query("SELECT COUNT(*) AS c FROM athlete_records");
        $rProg    = $conn->query("SELECT COUNT(*) AS c FROM athlete_progressions");
        $out['bdd'] = [
            'nom_et_liens'          => $rNol ? (int)$rNol->fetch_assoc()['c'] : null,
            'athletes'              => $rAth ? (int)$rAth->fetch_assoc()['c'] : null,
            'athlete_records'       => $rRec ? (int)$rRec->fetch_assoc()['c'] : null,
            'athlete_progressions'  => $rProg ? (int)$rProg->fetch_assoc()['c'] : null,
        ];

        // --- Doublons potentiels dans nom_et_liens (puisque INSERT sans IGNORE) ---
        $rDup = $conn->query("SELECT COUNT(*) AS c FROM (SELECT url FROM nom_et_liens GROUP BY url HAVING COUNT(*) > 1) AS dup");
        $out['bdd']['nom_et_liens_doublons'] = $rDup ? (int)$rDup->fetch_assoc()['c'] : null;

        // --- 5 dernieres URLs ajoutees dans nom_et_liens (preuve d'insertion recente) ---
        $rLast = $conn->query("SELECT id_nom_et_liens, url FROM nom_et_liens ORDER BY id_nom_et_liens DESC LIMIT 5");
        $out['nom_et_liens_last_5'] = [];
        if ($rLast) while ($row = $rLast->fetch_assoc()) $out['nom_et_liens_last_5'][] = $row;

        // --- 5 derniers athletes inseres : vue COMPLETE des 4 champs noms + complet
        //     (preuve que la convention nom_1/2/3/4 + nom_complet est respectee)
        $rLastAth = $conn->query("
            SELECT id_athlete, athlete_id_externe,
                   nom_1_athlete, nom_2_athlete, nom_3_athlete, nom_4_athlete,
                   nom_complet_athlete, sexe_athlete, categorie_athlete,
                   nationalite_athlete, licence_athlete, annee_naissance_athlete,
                   date_creation_athlete
            FROM athletes
            ORDER BY id_athlete DESC LIMIT 5
        ");
        $out['athletes_last_5'] = [];
        if ($rLastAth) while ($row = $rLastAth->fetch_assoc()) $out['athletes_last_5'][] = $row;

        // --- Audit qualite : combien d'athletes ont des champs vides la ou ce n'est pas normal
        //     Si tout est OK : tous les compteurs doivent etre tres proches de 0 par rapport au total.
        $rAudit = $conn->query("
            SELECT
              SUM(nom_complet_athlete = '' OR nom_complet_athlete IS NULL)  AS sans_nom_complet,
              SUM(nom_1_athlete       = '' OR nom_1_athlete IS NULL)        AS sans_nom_1,
              SUM(sexe_athlete        = '' OR sexe_athlete IS NULL)         AS sans_sexe,
              SUM(categorie_athlete   = '' OR categorie_athlete IS NULL)    AS sans_categorie,
              SUM(nationalite_athlete = '' OR nationalite_athlete IS NULL)  AS sans_nationalite,
              SUM(licence_athlete     = '' OR licence_athlete IS NULL)      AS sans_licence,
              SUM(annee_naissance_athlete IS NULL)                          AS sans_annee_naissance
            FROM athletes
        ");
        $out['audit_qualite'] = $rAudit ? $rAudit->fetch_assoc() : null;

        // --- Cohérence pipeline : combien d'URLs scrappees (nom_et_liens) ne sont PAS encore en BDD ?
        //     C'est le "reste a faire" du scraper principal.
        $rRestants = $conn->query("
            SELECT COUNT(DISTINCT nl.url) AS c
            FROM nom_et_liens nl
            LEFT JOIN athletes a
              ON a.athlete_id_externe = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(nl.url,'/athletes/',-1),'/',1) AS UNSIGNED)
            WHERE a.id_athlete IS NULL
        ");
        $out['pipeline'] = [
            'urls_restantes_a_scraper' => $rRestants ? (int)$rRestants->fetch_assoc()['c'] : null,
            'note' => 'URLs presentes dans nom_et_liens mais athlete pas encore dans la table athletes',
        ];
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
