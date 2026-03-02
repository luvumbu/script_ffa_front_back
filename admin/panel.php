<?php
/**
 * admin/panel.php — Dashboard Super Admin
 *
 * Acces : login avec identifiants BDD (username + password)
 * Cookie : bk_sa_token (7 jours)
 */
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/ip_logger.php';

// === VERIFIER SESSION SUPER ADMIN ===
function isSuperAdmin() {
    if (empty($_COOKIE['bk_sa_token'])) return false;
    $token = $_COOKIE['bk_sa_token'];
    $saFile = __DIR__ . '/../logs/.sa_sessions.php';
    if (!file_exists($saFile)) return false;
    $raw = file_get_contents($saFile);
    $pos = strpos($raw, "\n");
    if ($pos === false) return false;
    $sessions = json_decode(substr($raw, $pos + 1), true) ?: [];
    return isset($sessions[$token]) && ($sessions[$token]['expires'] ?? 0) > time();
}

if (!isSuperAdmin()) {
    header('Location: ../login.php');
    exit;
}

// === DECONNEXION ===
if (isset($_GET['logout'])) {
    setcookie('bk_sa_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    header('Location: ../login.php');
    exit;
}

// === ACTIONS POST (ignore/unignore IP search tracking) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['st_action'])) {
    $stIgnFile = __DIR__ . '/../logs/.st_ignored_ips.php';
    $stIgnored = [];
    if (file_exists($stIgnFile)) {
        $raw = file_get_contents($stIgnFile);
        $pos = strpos($raw, "\n");
        if ($pos !== false) $stIgnored = json_decode(substr($raw, $pos + 1), true) ?: [];
    }
    if ($_POST['st_action'] === 'ignore_ip' && !empty($_POST['ip'])) {
        $ip = trim($_POST['ip']);
        $label = trim($_POST['label'] ?? '');
        $stIgnored[$ip] = ['added' => date('Y-m-d H:i:s'), 'label' => $label];
        file_put_contents($stIgnFile, "<?php die('Acces interdit'); ?>\n" . json_encode($stIgnored, JSON_PRETTY_PRINT));
    } elseif ($_POST['st_action'] === 'unignore_ip' && !empty($_POST['ip'])) {
        unset($stIgnored[trim($_POST['ip'])]);
        file_put_contents($stIgnFile, "<?php die('Acces interdit'); ?>\n" . json_encode($stIgnored, JSON_PRETTY_PRINT));
    } elseif ($_POST['st_action'] === 'reset_tracking') {
        $resetType = $_POST['reset_type'] ?? '';
        if ($resetType === 'athlete') $conn->query("DELETE FROM search_tracking WHERE search_type = 'athlete'");
        elseif ($resetType === 'club') $conn->query("DELETE FROM search_tracking WHERE search_type = 'club'");
        elseif ($resetType === 'all') $conn->query("TRUNCATE TABLE search_tracking");
        $files = glob(__DIR__ . '/../cache/topsearched_*.json');
        if ($files) array_map('unlink', $files);
    }
    header('Location: ' . $_SERVER['REQUEST_URI'] . '#stSection');
    exit;
}

// ============================================================
// DATA COLLECTION
// ============================================================

// === STATS BDD ===
$stats = [];
$tables = ['athletes', 'clubs', 'epreuves', 'villes', 'competitions', 'users', 'user_sessions', 'logs',
           'athlete_records', 'athlete_resultats', 'athlete_medailles', 'athlete_podiums', 'athlete_selections',
           'athlete_progressions', 'athlete_niveaux', 'athlete_clubs', 'athlete_follows', 'club_follows', 'email_subscribers',
           'search_tracking'];

foreach ($tables as $t) {
    $r = $conn->query("SELECT COUNT(*) as c FROM `$t`");
    $stats[$t] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}

// Taille BDD par table
$tableSizes = [];
$rSizes = $conn->query("SELECT table_name, data_length + index_length as size, table_rows FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY size DESC");
$dbSize = 0;
if ($rSizes) while ($row = $rSizes->fetch_assoc()) { $tableSizes[] = $row; $dbSize += (float)$row['size']; }

// === USERS ===
$lastUsers = [];
$rUsers = $conn->query("SELECT id_user, email, nom, prenom, role, id_athlete FROM users ORDER BY id_user DESC LIMIT 20");
if ($rUsers) while ($row = $rUsers->fetch_assoc()) $lastUsers[] = $row;

$usersByRole = [];
$rRoles = $conn->query("SELECT role, COUNT(*) as c FROM users GROUP BY role ORDER BY c DESC");
if ($rRoles) while ($row = $rRoles->fetch_assoc()) $usersByRole[$row['role']] = (int)$row['c'];

// Sessions actives
$activeSessions = 0;
$rSess = $conn->query("SELECT COUNT(*) as c FROM user_sessions WHERE expire_at > NOW()");
if ($rSess) $activeSessions = (int)$rSess->fetch_assoc()['c'];

// === FOLLOWS & SUBSCRIBERS ===
$lastFollowsAth = [];
$r = $conn->query("SELECT email, athlete_id_ext, created_at FROM athlete_follows ORDER BY id_follow DESC LIMIT 15");
if ($r) while ($row = $r->fetch_assoc()) $lastFollowsAth[] = $row;

$lastFollowsClub = [];
$r = $conn->query("SELECT email, club_id, created_at FROM club_follows ORDER BY id_follow DESC LIMIT 15");
if ($r) while ($row = $r->fetch_assoc()) $lastFollowsClub[] = $row;

$lastSubs = [];
$r = $conn->query("SELECT email, source, detail, created_at FROM email_subscribers ORDER BY id_sub DESC LIMIT 15");
if ($r) while ($row = $r->fetch_assoc()) $lastSubs[] = $row;

// === LOGS BDD ===
$todayLogs = 0;
$rToday = $conn->query("SELECT COUNT(*) as c FROM logs WHERE DATE(ts) = CURDATE()");
if ($rToday) $todayLogs = (int)$rToday->fetch_assoc()['c'];

$yesterdayLogs = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM logs WHERE DATE(ts) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
if ($r) $yesterdayLogs = (int)$r->fetch_assoc()['c'];

// Top IPs aujourd'hui (avec UA)
$topIpsToday = [];
$rTopIp = $conn->query("SELECT ip, ua, COUNT(*) as c, MIN(ts) as first_seen, MAX(ts) as last_seen FROM logs WHERE DATE(ts) = CURDATE() GROUP BY ip ORDER BY c DESC LIMIT 15");
if ($rTopIp) while ($row = $rTopIp->fetch_assoc()) $topIpsToday[] = $row;

// Top pages aujourd'hui
$topPagesToday = [];
$r = $conn->query("SELECT page, COUNT(*) as c FROM logs WHERE DATE(ts) = CURDATE() AND action='page_view' GROUP BY page ORDER BY c DESC LIMIT 15");
if ($r) while ($row = $r->fetch_assoc()) $topPagesToday[] = $row;

// Actions distribution aujourd'hui
$actionsToday = [];
$r = $conn->query("SELECT action, COUNT(*) as c FROM logs WHERE DATE(ts) = CURDATE() GROUP BY action ORDER BY c DESC");
if ($r) while ($row = $r->fetch_assoc()) $actionsToday[$row['action']] = (int)$row['c'];

// Sessions uniques aujourd'hui
$sessionsToday = 0;
$r = $conn->query("SELECT COUNT(DISTINCT sid) as c FROM logs WHERE DATE(ts) = CURDATE()");
if ($r) $sessionsToday = (int)$r->fetch_assoc()['c'];

// Unique IPs aujourd'hui
$uniqueIpsToday = 0;
$r = $conn->query("SELECT COUNT(DISTINCT ip) as c FROM logs WHERE DATE(ts) = CURDATE()");
if ($r) $uniqueIpsToday = (int)$r->fetch_assoc()['c'];

// Erreurs JS aujourd'hui
$jsErrors = [];
$r = $conn->query("SELECT detail, value, ip, ts FROM logs WHERE action='js_error' AND DATE(ts) = CURDATE() ORDER BY ts DESC LIMIT 10");
if ($r) while ($row = $r->fetch_assoc()) $jsErrors[] = $row;

// Dernières requêtes BDD (les 30 dernières)
$lastLogs = [];
$r = $conn->query("SELECT ts, ip, ua, action, page, detail, sid, screen, lang, duration_ms, uname FROM logs ORDER BY id_log DESC LIMIT 30");
if ($r) while ($row = $r->fetch_assoc()) $lastLogs[] = $row;

// Activité par heure aujourd'hui
$hourlyActivity = array_fill(0, 24, 0);
$r = $conn->query("SELECT HOUR(ts) as h, COUNT(*) as c FROM logs WHERE DATE(ts) = CURDATE() GROUP BY HOUR(ts)");
if ($r) while ($row = $r->fetch_assoc()) $hourlyActivity[(int)$row['h']] = (int)$row['c'];

// Logs 7 derniers jours
$weeklyLogs = [];
$r = $conn->query("SELECT DATE(ts) as d, COUNT(*) as c, COUNT(DISTINCT ip) as ips, COUNT(DISTINCT sid) as sessions FROM logs WHERE ts >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(ts) ORDER BY d DESC");
if ($r) while ($row = $r->fetch_assoc()) $weeklyLogs[] = $row;

// Devices (screen resolutions)
$devices = [];
$r = $conn->query("SELECT screen, COUNT(*) as c FROM logs WHERE DATE(ts) = CURDATE() AND screen != '' GROUP BY screen ORDER BY c DESC LIMIT 10");
if ($r) while ($row = $r->fetch_assoc()) $devices[] = $row;

// Langues
$languages = [];
$r = $conn->query("SELECT lang, COUNT(*) as c FROM logs WHERE DATE(ts) = CURDATE() AND lang != '' GROUP BY lang ORDER BY c DESC LIMIT 10");
if ($r) while ($row = $r->fetch_assoc()) $languages[] = $row;

// Referrers
$referrers = [];
$r = $conn->query("SELECT referrer, COUNT(*) as c FROM logs WHERE DATE(ts) = CURDATE() AND referrer != '' AND referrer NOT LIKE '%bokonzi%' GROUP BY referrer ORDER BY c DESC LIMIT 10");
if ($r) while ($row = $r->fetch_assoc()) $referrers[] = $row;

// Bots detection dans IP tracker
$ipData = readIpLog();
$lastRequests = array_reverse($ipData['last_requests'] ?? []);

// === SERVEUR ===
$phpVersion = phpversion();
$mysqlVersion = '';
$r = $conn->query("SELECT VERSION() as v");
if ($r) $mysqlVersion = $r->fetch_assoc()['v'];

// Espace disque
$diskFree = @disk_free_space('/');
$diskTotal = @disk_total_space('/');

// Cache files
$cacheFiles = glob(__DIR__ . '/../cache/*.json') ?: [];
$cacheSize = 0;
$oldestCache = null; $newestCache = null;
foreach ($cacheFiles as $f) {
    $cacheSize += filesize($f);
    $mt = filemtime($f);
    if (!$oldestCache || $mt < $oldestCache) $oldestCache = $mt;
    if (!$newestCache || $mt > $newestCache) $newestCache = $mt;
}

// Log files IP tracker
$ipLogMonths = listIpLogFiles();

// Failed login attempts (security)
$failedLogins = [];
$r = $conn->query("SELECT detail, value, ip, ts FROM logs WHERE action='form_submit' AND detail LIKE '%login%' AND DATE(ts) >= DATE_SUB(CURDATE(), INTERVAL 3 DAY) ORDER BY ts DESC LIMIT 20");
if ($r) while ($row = $r->fetch_assoc()) $failedLogins[] = $row;

// === VUES TRACKING (profils & clubs) ===
$hasVuesTables = false;
$r = $conn->query("SHOW TABLES LIKE 'athlete_vues_ip'");
if ($r && $r->num_rows > 0) $hasVuesTables = true;

$totalVuesAthletes = 0; $totalVuesClubs = 0;
$nbAthVus = 0; $nbClubsVus = 0;
$uniqueVueIpsAth = 0; $uniqueVueIpsClub = 0;
$vuesTodayAth = 0; $vuesTodayClub = 0;
$topVuesAthletes = []; $topVuesClubs = [];
$lastVuesAthletes = []; $lastVuesClubs = [];
$topVuesIps = [];
$vuesParJour = [];

if ($hasVuesTables) {
    // Totaux
    $r = $conn->query("SELECT SUM(vues) as s, COUNT(*) as c FROM athletes WHERE vues > 0");
    if ($r) { $row = $r->fetch_assoc(); $totalVuesAthletes = (int)($row['s'] ?? 0); $nbAthVus = (int)$row['c']; }

    $r = $conn->query("SELECT SUM(vues) as s, COUNT(*) as c FROM clubs WHERE vues > 0");
    if ($r) { $row = $r->fetch_assoc(); $totalVuesClubs = (int)($row['s'] ?? 0); $nbClubsVus = (int)$row['c']; }

    // IPs uniques
    $r = $conn->query("SELECT COUNT(DISTINCT ip) as c FROM athlete_vues_ip");
    if ($r) $uniqueVueIpsAth = (int)$r->fetch_assoc()['c'];
    $r = $conn->query("SELECT COUNT(DISTINCT ip) as c FROM club_vues_ip");
    if ($r) $uniqueVueIpsClub = (int)$r->fetch_assoc()['c'];

    // Vues aujourd'hui
    $r = $conn->query("SELECT COUNT(*) as c FROM athlete_vues_ip WHERE DATE(created_at) = CURDATE()");
    if ($r) $vuesTodayAth = (int)$r->fetch_assoc()['c'];
    $r = $conn->query("SELECT COUNT(*) as c FROM club_vues_ip WHERE DATE(created_at) = CURDATE()");
    if ($r) $vuesTodayClub = (int)$r->fetch_assoc()['c'];

    // Top 50 athletes consultes
    $r = $conn->query("SELECT a.athlete_id_externe, CONCAT(a.prenom_athlete, ' ', a.nom_athlete) as nom,
                               a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete, a.vues,
                               (SELECT c.nom_club FROM clubs c JOIN athlete_clubs ac ON ac.id_club = c.id_club
                                WHERE ac.id_athlete = a.id_athlete ORDER BY ac.annee_debut DESC LIMIT 1) as club,
                               (SELECT COUNT(DISTINCT v.ip) FROM athlete_vues_ip v WHERE v.athlete_id_ext = a.athlete_id_externe) as ips_uniques,
                               (SELECT MAX(v.created_at) FROM athlete_vues_ip v WHERE v.athlete_id_ext = a.athlete_id_externe) as derniere_vue
                        FROM athletes a WHERE a.vues > 0 ORDER BY a.vues DESC LIMIT 50");
    if ($r) while ($row = $r->fetch_assoc()) $topVuesAthletes[] = $row;

    // Top 50 clubs consultes
    $r = $conn->query("SELECT c.id_club, c.nom_club, c.vues,
                               (SELECT COUNT(DISTINCT ac.id_athlete) FROM athlete_clubs ac WHERE ac.id_club = c.id_club) as nb_athletes,
                               (SELECT COUNT(DISTINCT v.ip) FROM club_vues_ip v WHERE v.club_id = c.id_club) as ips_uniques,
                               (SELECT MAX(v.created_at) FROM club_vues_ip v WHERE v.club_id = c.id_club) as derniere_vue
                        FROM clubs c WHERE c.vues > 0 ORDER BY c.vues DESC LIMIT 50");
    if ($r) while ($row = $r->fetch_assoc()) $topVuesClubs[] = $row;

    // 50 dernieres vues athletes (flux temps reel)
    $r = $conn->query("SELECT v.ip, v.athlete_id_ext, v.created_at,
                               CONCAT(a.prenom_athlete, ' ', a.nom_athlete) as nom,
                               a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete
                        FROM athlete_vues_ip v
                        LEFT JOIN athletes a ON a.athlete_id_externe = v.athlete_id_ext
                        ORDER BY v.created_at DESC LIMIT 50");
    if ($r) while ($row = $r->fetch_assoc()) $lastVuesAthletes[] = $row;

    // 50 dernieres vues clubs (flux temps reel)
    $r = $conn->query("SELECT v.ip, v.club_id, v.created_at, c.nom_club
                        FROM club_vues_ip v
                        LEFT JOIN clubs c ON c.id_club = v.club_id
                        ORDER BY v.created_at DESC LIMIT 50");
    if ($r) while ($row = $r->fetch_assoc()) $lastVuesClubs[] = $row;

    // Top IPs : les plus actives (profils + clubs vus)
    $r = $conn->query("SELECT combined.ip,
                               SUM(combined.nb_ath) as nb_profils,
                               SUM(combined.nb_club) as nb_clubs,
                               MIN(combined.first_vue) as first_vue,
                               MAX(combined.last_vue) as last_vue
                        FROM (
                            SELECT ip, COUNT(*) as nb_ath, 0 as nb_club,
                                   MIN(created_at) as first_vue, MAX(created_at) as last_vue
                            FROM athlete_vues_ip GROUP BY ip
                            UNION ALL
                            SELECT ip, 0 as nb_ath, COUNT(*) as nb_club,
                                   MIN(created_at) as first_vue, MAX(created_at) as last_vue
                            FROM club_vues_ip GROUP BY ip
                        ) combined
                        GROUP BY combined.ip
                        ORDER BY (SUM(combined.nb_ath) + SUM(combined.nb_club)) DESC
                        LIMIT 30");
    if ($r) while ($row = $r->fetch_assoc()) $topVuesIps[] = $row;

    // Pour chaque top IP, récupérer les 5 derniers profils vus
    foreach ($topVuesIps as &$ipRow) {
        $escapedIp = $conn->real_escape_string($ipRow['ip']);
        $ipRow['derniers_profils'] = [];
        $r2 = $conn->query("SELECT v.athlete_id_ext, CONCAT(a.prenom_athlete, ' ', a.nom_athlete) as nom, v.created_at
                             FROM athlete_vues_ip v LEFT JOIN athletes a ON a.athlete_id_externe = v.athlete_id_ext
                             WHERE v.ip = '$escapedIp' ORDER BY v.created_at DESC LIMIT 5");
        if ($r2) while ($row2 = $r2->fetch_assoc()) $ipRow['derniers_profils'][] = $row2;

        $ipRow['derniers_clubs'] = [];
        $r2 = $conn->query("SELECT v.club_id, c.nom_club, v.created_at
                             FROM club_vues_ip v LEFT JOIN clubs c ON c.id_club = v.club_id
                             WHERE v.ip = '$escapedIp' ORDER BY v.created_at DESC LIMIT 5");
        if ($r2) while ($row2 = $r2->fetch_assoc()) $ipRow['derniers_clubs'][] = $row2;
    }
    unset($ipRow);

    // Historique vues par jour (14 derniers jours)
    $r = $conn->query("SELECT d, SUM(ath) as ath, SUM(club) as club FROM (
                            SELECT DATE(created_at) as d, COUNT(*) as ath, 0 as club FROM athlete_vues_ip
                            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(created_at)
                            UNION ALL
                            SELECT DATE(created_at) as d, 0 as ath, COUNT(*) as club FROM club_vues_ip
                            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(created_at)
                        ) combined GROUP BY d ORDER BY d DESC");
    if ($r) while ($row = $r->fetch_assoc()) $vuesParJour[] = $row;
}

// === IPs RATE LIMITEES (ayant depasse la limite et vu la page inscription) ===
$rateLimitedIps = [];
$rateLimitDailyLimit = defined('IP_DAILY_LIMIT') ? IP_DAILY_LIMIT : 20;
$logDir = defined('IP_LOG_DIR') ? IP_LOG_DIR : __DIR__ . '/../logs';

// Lire les fichiers daily des 5 derniers jours
for ($d = 0; $d < 5; $d++) {
    $dateStr = date('Y-m-d', strtotime("-$d days"));
    $dailyFile = $logDir . '/ip_daily_' . $dateStr . '.php';
    if (!file_exists($dailyFile)) continue;

    $raw = file_get_contents($dailyFile);
    if (!$raw) continue;
    $pos = strpos($raw, "\n");
    if ($pos === false) continue;
    $counters = json_decode(substr($raw, $pos + 1), true) ?: [];

    foreach ($counters as $ip => $count) {
        if ($count > $rateLimitDailyLimit) {
            $rateLimitedIps[] = [
                'ip' => $ip,
                'date' => $dateStr,
                'count' => (int)$count,
                'limite' => $rateLimitDailyLimit,
                'depassement' => (int)$count - $rateLimitDailyLimit
            ];
        }
    }
}

// Trier par date desc puis par count desc
usort($rateLimitedIps, function($a, $b) {
    $cmp = strcmp($b['date'], $a['date']);
    return $cmp !== 0 ? $cmp : $b['count'] - $a['count'];
});

// === IPs BANNIES DEFINITIVEMENT ===
$bannedIps = [];
$bannedFile = $logDir . '/ip_banned.php';
if (file_exists($bannedFile)) {
    $rawB = file_get_contents($bannedFile);
    $posB = strpos($rawB, "\n");
    if ($posB !== false) {
        $bannedIps = json_decode(substr($rawB, $posB + 1), true) ?: [];
    }
}

// === MESSAGES DE CONTACT ===
$contactMessages = [];
$conn->query("CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id_msg` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `nom` VARCHAR(100) NOT NULL DEFAULT '',
    `email` VARCHAR(200) NOT NULL DEFAULT '',
    `message` TEXT NOT NULL,
    `lu` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$resContact = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
if ($resContact) {
    while ($row = $resContact->fetch_assoc()) $contactMessages[] = $row;
    $resContact->free();
}
$unreadCount = 0;
foreach ($contactMessages as $cm) { if (!$cm['lu']) $unreadCount++; }

// === SEARCH TRACKING — FULL DATA ===
// IPs ignorees
$stIgnFile = __DIR__ . '/../logs/.st_ignored_ips.php';
$stIgnoredIps = [];
if (file_exists($stIgnFile)) {
    $raw = file_get_contents($stIgnFile);
    $pos = strpos($raw, "\n");
    if ($pos !== false) $stIgnoredIps = json_decode(substr($raw, $pos + 1), true) ?: [];
}
$stIpFilter = '';
if (!empty($stIgnoredIps)) {
    $ips = array_map(function($ip) use ($conn) { return "'" . $conn->real_escape_string($ip) . "'"; }, array_keys($stIgnoredIps));
    $stIpFilter = " AND ip NOT IN (" . implode(',', $ips) . ")";
}

// Compteurs globaux
$stTotal = $stToday = $stWeek = $stMonth = $stUniqueIps = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM search_tracking WHERE 1=1 $stIpFilter"); if ($r) $stTotal = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM search_tracking WHERE created_at >= CURDATE() $stIpFilter"); if ($r) $stToday = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM search_tracking WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) $stIpFilter"); if ($r) $stWeek = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM search_tracking WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) $stIpFilter"); if ($r) $stMonth = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(DISTINCT ip) as c FROM search_tracking WHERE 1=1 $stIpFilter"); if ($r) $stUniqueIps = (int)$r->fetch_assoc()['c'];

// Taux succes
$stSuccessRate = 0;
$r = $conn->query("SELECT SUM(result_count > 0) as ok, COUNT(*) as tot FROM search_tracking WHERE source='live_search' $stIpFilter");
if ($r) { $row = $r->fetch_assoc(); $stSuccessRate = $row['tot'] > 0 ? round(100 * $row['ok'] / $row['tot']) : 0; }

// Derniere recherche
$stLastSearch = null;
$r = $conn->query("SELECT created_at FROM search_tracking WHERE 1=1 $stIpFilter ORDER BY id_search DESC LIMIT 1");
if ($r && ($row = $r->fetch_assoc())) $stLastSearch = $row['created_at'];

// Par type
$stByType = [];
$r = $conn->query("SELECT search_type, COUNT(*) as c, COUNT(DISTINCT ip) as ips FROM search_tracking WHERE 1=1 $stIpFilter GROUP BY search_type ORDER BY c DESC");
if ($r) while ($row = $r->fetch_assoc()) $stByType[] = $row;

// Par source
$stBySource = [];
$r = $conn->query("SELECT source, COUNT(*) as c, COUNT(DISTINCT ip) as ips FROM search_tracking WHERE 1=1 $stIpFilter GROUP BY source ORDER BY c DESC");
if ($r) while ($row = $r->fetch_assoc()) $stBySource[] = $row;

// 14 derniers jours
$stParJour = [];
$r = $conn->query("SELECT DATE(created_at) as d,
    COUNT(*) as total,
    SUM(search_type='athlete') as athlete, SUM(search_type='club') as club,
    SUM(search_type='epreuve') as epreuve, SUM(search_type='ville') as ville,
    SUM(search_type='general') as general
    FROM search_tracking WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) $stIpFilter
    GROUP BY DATE(created_at) ORDER BY d ASC");
if ($r) while ($row = $r->fetch_assoc()) $stParJour[] = $row;

// Distribution horaire
$stParHeure = array_fill(0, 24, 0);
$r = $conn->query("SELECT HOUR(created_at) as h, COUNT(*) as c FROM search_tracking WHERE 1=1 $stIpFilter GROUP BY HOUR(created_at)");
if ($r) while ($row = $r->fetch_assoc()) $stParHeure[(int)$row['h']] = (int)$row['c'];

// Top queries
$stTopQueries = [];
$r = $conn->query("SELECT query_text, search_type, COUNT(*) as c, COUNT(DISTINCT ip) as ips,
    ROUND(AVG(result_count)) as avg_results, MAX(created_at) as last_at
    FROM search_tracking WHERE query_text != '' $stIpFilter GROUP BY query_text, search_type ORDER BY c DESC LIMIT 50");
if ($r) while ($row = $r->fetch_assoc()) $stTopQueries[] = $row;

// TOUS les athletes
$stAllAthletes = [];
$r = $conn->query("SELECT query_text, entity_name, entity_id, ip, source, page, result_count, created_at
    FROM search_tracking WHERE search_type='athlete' $stIpFilter ORDER BY created_at DESC");
if ($r) while ($row = $r->fetch_assoc()) $stAllAthletes[] = $row;

// TOUS les clubs
$stAllClubs = [];
$r = $conn->query("SELECT query_text, entity_name, entity_id, ip, source, page, result_count, created_at
    FROM search_tracking WHERE search_type='club' $stIpFilter ORDER BY created_at DESC");
if ($r) while ($row = $r->fetch_assoc()) $stAllClubs[] = $row;

// Top entites (epreuves + villes)
$stTopEntities = [];
$r = $conn->query("SELECT entity_name, search_type, COUNT(*) as c, COUNT(DISTINCT ip) as ips
    FROM search_tracking WHERE search_type IN ('epreuve','ville') AND entity_name IS NOT NULL AND entity_name != '' $stIpFilter
    GROUP BY entity_name, search_type ORDER BY c DESC LIMIT 50");
if ($r) while ($row = $r->fetch_assoc()) $stTopEntities[] = $row;

// Toutes les IPs
$stTopIps = [];
$r = $conn->query("SELECT ip, COUNT(*) as c, COUNT(DISTINCT query_text) as queries, MAX(created_at) as last_at
    FROM search_tracking WHERE 1=1 $stIpFilter GROUP BY ip ORDER BY c DESC");
if ($r) while ($row = $r->fetch_assoc()) $stTopIps[] = $row;

// IP du visiteur actuel
$stMyIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$stMyIp = trim(explode(',', $stMyIp)[0]);

$conn->close();

// Helpers
function fmtSize($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' Go';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' Ko';
    return $bytes . ' o';
}
function pctChange($today, $yesterday) {
    if ($yesterday == 0) return $today > 0 ? '+100%' : '0%';
    $pct = round(($today - $yesterday) / $yesterday * 100);
    return ($pct >= 0 ? '+' : '') . $pct . '%';
}
$actionColors = [
    'page_view' => '#10b981', 'click_link' => '#8b5cf6', 'click_button' => '#3b82f6',
    'form_submit' => '#f59e0b', 'input_change' => '#6366f1', 'copy' => '#ec4899',
    'page_leave' => '#64748b', 'js_error' => '#ef4444', 'navigation' => '#0891b2',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Super Admin — Bokonzi</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0e14; color: #c9d1d9; font-family: 'Segoe UI', system-ui, sans-serif; }
        .topbar {
            background: #161b22; border-bottom: 1px solid #e11d4840; padding: 12px 24px;
            display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100;
        }
        .topbar h1 { font-size: 18px; color: #e11d48; }
        .topbar h1 span { color: #5a6580; font-weight: 400; font-size: 13px; }
        .topbar a { color: #5a6580; text-decoration: none; font-size: 13px; }
        .topbar a:hover { color: #ef4444; }
        .topbar .links { display: flex; gap: 16px; flex-wrap: wrap; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; padding: 16px 24px; }
        .card { background: #161b22; border: 1px solid #1e2a3a; border-radius: 10px; padding: 14px; }
        .card .num { font-size: 24px; font-weight: 800; color: #55efc4; }
        .card .num.warn { color: #f59e0b; }
        .card .num.danger { color: #ef4444; }
        .card .num.info { color: #6c5ce7; }
        .card .num.pink { color: #f472b6; }
        .card .label { font-size: 11px; color: #5a6580; margin-top: 4px; }
        .card .sub { font-size: 11px; color: #3b82f6; margin-top: 2px; }

        .section { padding: 0 24px 16px; }
        .section h2 { color: #8b949e; font-size: 14px; margin-bottom: 10px; border-bottom: 1px solid #1e2a3a; padding-bottom: 8px; }

        table { width: 100%; border-collapse: collapse; background: #161b22; border-radius: 10px; overflow: hidden; margin-bottom: 16px; font-size: 12px; }
        th { background: #1a2035; color: #8b949e; font-size: 10px; text-transform: uppercase; padding: 8px 10px; text-align: left; }
        td { padding: 6px 10px; border-bottom: 1px solid #1e2a3a08; }
        tr:hover { background: #ffffff06; }
        .mono { font-family: monospace; color: #f59e0b; font-size: 11px; }
        .green { color: #55efc4; }
        .time { color: #55efc4; font-family: monospace; font-size: 11px; white-space: nowrap; }
        .dim { color: #5a6580; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; }
        .badge-admin { background: #e11d4830; color: #fb7185; }
        .badge-athlete { background: #10b98130; color: #34d399; }
        .badge-coach { background: #6c5ce730; color: #a29bfe; }
        .badge-club { background: #f59e0b30; color: #fbbf24; }
        .badge-superadmin { background: #e11d4830; color: #fb7185; }

        .actions { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 24px 16px; }
        .btn {
            padding: 7px 14px; border-radius: 8px; border: 1px solid #1e2a3a;
            background: #161b22; color: #c9d1d9; font-size: 12px; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;
        }
        .btn:hover { border-color: #6c5ce7; color: #a29bfe; }
        .btn-danger { border-color: #ef444440; color: #ef4444; }
        .btn-danger:hover { background: #ef444420; border-color: #ef4444; }

        .cols-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 0 24px 16px; }
        .cols-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; padding: 0 24px 16px; }

        .bar-chart { display: flex; flex-direction: column; gap: 4px; }
        .bar-row { display: flex; align-items: center; gap: 8px; font-size: 11px; }
        .bar-row .bar-label { width: 80px; text-align: right; color: #8b949e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bar-row .bar-fill { height: 18px; border-radius: 4px; min-width: 2px; transition: width 0.3s; }
        .bar-row .bar-val { color: #c9d1d9; font-weight: 600; min-width: 40px; }

        .hour-chart { display: flex; align-items: flex-end; gap: 2px; height: 60px; }
        .hour-bar { flex: 1; background: #6c5ce7; border-radius: 2px 2px 0 0; min-height: 2px; position: relative; cursor: pointer; }
        .hour-bar:hover { background: #8b7cf7; }
        .hour-bar .htip { display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #1a2035; border: 1px solid #2d3a4a; border-radius: 4px; padding: 4px 8px; font-size: 10px; white-space: nowrap; z-index: 10; }
        .hour-bar:hover .htip { display: block; }

        .live-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; animation: pulse 1.5s infinite; margin-right: 6px; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

        /* Drawer panel — LARGE */
        .vue-overlay { position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:199;display:none;backdrop-filter:blur(4px); }
        .vue-overlay.open { display:block; }
        .vue-drawer { position:fixed;right:-750px;top:0;width:720px;height:100vh;background:#0a0e14;border-left:3px solid #6c5ce7;z-index:200;transition:right .35s cubic-bezier(.4,0,.2,1);overflow-y:auto;box-shadow:-12px 0 50px rgba(0,0,0,.6); }
        .vue-drawer.open { right:0; }
        .vue-drawer-head { position:sticky;top:0;background:#101520;padding:20px 28px;border-bottom:2px solid #6c5ce730;display:flex;justify-content:space-between;align-items:center;z-index:1; }
        .vue-drawer-head .vd-title { color:#e2e8f0;font-weight:800;font-size:18px;max-width:580px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
        .vue-drawer-head .vd-close { background:#1a2035;border:1px solid #2d3a4a;border-radius:8px;color:#8b949e;font-size:20px;cursor:pointer;width:38px;height:38px;display:flex;align-items:center;justify-content:center;transition:all .2s; }
        .vue-drawer-head .vd-close:hover { background:#ef444430;border-color:#ef4444;color:#ef4444; }
        .vd-body { padding:24px 28px; }
        .vd-section { margin-bottom:28px; }
        .vd-section h4 { color:#a29bfe;font-size:13px;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;font-weight:700;border-bottom:1px solid #1e2a3a;padding-bottom:6px; }
        .vd-kpi { display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap; }
        .vd-kpi-item { background:#161b22;border:1px solid #1e2a3a;border-radius:12px;padding:16px 20px;flex:1;min-width:120px;text-align:center; }
        .vd-kpi-item .vd-num { font-size:28px;font-weight:800; }
        .vd-kpi-item .vd-lbl { font-size:11px;color:#5a6580;margin-top:4px; }
        .vd-link { display:inline-block;padding:12px 24px;background:#6c5ce720;border:1px solid #6c5ce7;border-radius:10px;color:#a29bfe;text-decoration:none;font-size:14px;font-weight:700;margin-bottom:16px;transition:all .2s; }
        .vd-link:hover { background:#6c5ce740;transform:translateY(-1px); }
        .vd-drawer-table { font-size:13px; }
        .vd-drawer-table td, .vd-drawer-table th { padding:10px 12px; }

        /* Tabs */
        .vue-tabs { display:flex;gap:4px;margin:0 24px 0;flex-wrap:wrap; }
        .vue-tab { padding:11px 24px;background:#161b22;border:1px solid #1e2a3a;border-bottom:none;border-radius:10px 10px 0 0;color:#5a6580;cursor:pointer;font-size:13px;font-weight:700;transition:all .2s; }
        .vue-tab.active { background:#0d1117;color:#a29bfe;border-color:#6c5ce740;border-bottom-color:#0d1117; }
        .vue-tab:hover:not(.active) { color:#8b949e;background:#1a2035; }
        .vue-tab-body { background:#0d1117;border:1px solid #1e2a3a;border-radius:0 10px 10px 10px;margin:0 24px 20px;padding:20px; }

        /* Interactive rows */
        .vue-row { cursor:pointer;transition:all .15s; }
        .vue-row:hover { background:#6c5ce712 !important;border-left:3px solid #6c5ce7; }

        /* Search + Sort */
        .vue-search { width:100%;padding:10px 16px;background:#161b22;border:1px solid #1e2a3a;border-radius:10px;color:#c9d1d9;font-size:13px;margin-bottom:14px;transition:border-color .2s; }
        .vue-search:focus { outline:none;border-color:#6c5ce7;box-shadow:0 0 0 3px #6c5ce720; }
        .vue-search::placeholder { color:#3a4560; }
        .vue-sort { cursor:pointer;user-select:none;white-space:nowrap; }
        .vue-sort:hover { color:#a29bfe; }
        .vue-sort.asc::after { content:' \u25B2';font-size:9px;color:#55efc4; }
        .vue-sort.desc::after { content:' \u25BC';font-size:9px;color:#55efc4; }

        @media (max-width: 900px) {
            .cols-2, .cols-3 { grid-template-columns: 1fr; }
            .grid { grid-template-columns: repeat(2, 1fr); }
            .vue-drawer { width:100%;right:-100%; }
        }
    </style>
</head>
<body>

<div class="topbar">
    <h1>SUPER ADMIN <span>Bokonzi — <?= date('d/m/Y H:i') ?></span></h1>
    <div class="links">
        <a href="../index.php">Site</a>
        <a href="logs.php">Logs BDD</a>
        <a href="../logs/ip_view.php">IP Tracker</a>
        <a href="clear_cache.php">Vider cache</a>
        <a href="?logout=1" style="color:#ef4444;">Deconnexion</a>
    </div>
</div>

<?php if ($unreadCount > 0): ?>
<!-- ALERTE MESSAGES NON LUS -->
<div id="contactAlertTop" style="background:#6c5ce720;border:2px solid #6c5ce7;border-radius:12px;padding:16px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;animation:_alertPulse 2s ease-in-out 3;">
    <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-size:28px;">&#9993;</span>
        <div>
            <strong style="color:#e2e8f0;font-size:15px;"><?= $unreadCount ?> nouveau<?= $unreadCount > 1 ? 'x' : '' ?> message<?= $unreadCount > 1 ? 's' : '' ?> de contact</strong>
            <?php $firstUnread = null; foreach ($contactMessages as $_cm) { if (!$_cm['lu']) { $firstUnread = $_cm; break; } } ?>
            <?php if ($firstUnread): ?>
            <p style="color:#8b949e;font-size:12px;margin:2px 0 0;">Dernier : <strong style="color:#c9d1d9;"><?= htmlspecialchars($firstUnread['nom'] ?: 'Anonyme') ?></strong> — "<?= htmlspecialchars(mb_substr($firstUnread['message'], 0, 80)) ?><?= mb_strlen($firstUnread['message']) > 80 ? '...' : '' ?>"</p>
            <?php endif; ?>
        </div>
    </div>
    <a href="#contactSection" style="background:#6c5ce7;color:#fff;padding:8px 20px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;">Voir les messages</a>
</div>
<style>@keyframes _alertPulse { 0%,100%{border-color:#6c5ce7;} 50%{border-color:#ef4444;} }</style>
<?php endif; ?>

<!-- ============================================================ -->
<!-- SECTION 1 : VUE D'ENSEMBLE -->
<!-- ============================================================ -->
<div class="grid">
    <div class="card"><div class="num"><?= number_format($stats['athletes']) ?></div><div class="label">Athletes</div></div>
    <div class="card"><div class="num"><?= number_format($stats['clubs']) ?></div><div class="label">Clubs</div></div>
    <div class="card"><div class="num"><?= number_format($stats['epreuves']) ?></div><div class="label">Epreuves</div></div>
    <div class="card"><div class="num"><?= number_format($stats['villes']) ?></div><div class="label">Villes</div></div>
    <div class="card"><div class="num info"><?= number_format($stats['users']) ?></div><div class="label">Utilisateurs inscrits</div></div>
    <div class="card"><div class="num info"><?= number_format($activeSessions) ?></div><div class="label">Sessions actives</div></div>
    <div class="card">
        <div class="num warn"><?= number_format($todayLogs) ?></div>
        <div class="label">Events aujourd'hui</div>
        <div class="sub"><?= pctChange($todayLogs, $yesterdayLogs) ?> vs hier (<?= number_format($yesterdayLogs) ?>)</div>
    </div>
    <div class="card"><div class="num warn"><?= number_format($sessionsToday) ?></div><div class="label">Sessions aujourd'hui</div></div>
    <div class="card"><div class="num pink"><?= number_format($uniqueIpsToday) ?></div><div class="label">IPs uniques aujourd'hui</div></div>
    <div class="card"><div class="num"><?= number_format($ipData['total_visits'] ?? 0) ?></div><div class="label">Visites IP (mois)</div></div>
    <div class="card"><div class="num"><?= number_format($ipData['unique_ips'] ?? 0) ?></div><div class="label">IPs uniques (mois)</div></div>
    <div class="card"><div class="num"><?= number_format($stats['athlete_follows']) ?></div><div class="label">Follows athletes</div></div>
    <div class="card"><div class="num"><?= number_format($stats['club_follows']) ?></div><div class="label">Follows clubs</div></div>
    <div class="card"><div class="num"><?= number_format($stats['email_subscribers']) ?></div><div class="label">Emails collectes</div></div>
    <div class="card"><div class="num" style="font-size:16px;color:#8b949e;"><?= fmtSize($dbSize) ?></div><div class="label">Taille BDD</div></div>
    <div class="card"><div class="num" style="font-size:16px;color:#8b949e;"><?= count($cacheFiles) ?> (<?= fmtSize($cacheSize) ?>)</div><div class="label">Fichiers cache</div></div>
</div>

<!-- ============================================================ -->
<!-- SECTION 2 : ACTIVITE TEMPS REEL -->
<!-- ============================================================ -->
<div class="section">
    <h2><span class="live-dot"></span>Dernieres requetes (IP Tracker — temps reel)</h2>
    <table>
        <thead><tr><th>Heure</th><th>IP</th><th>Page</th><th>Methode</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($lastRequests, 0, 20) as $req): ?>
        <tr>
            <td class="time"><?= htmlspecialchars($req['time']) ?></td>
            <td class="mono"><?= htmlspecialchars($req['ip']) ?></td>
            <td><?= htmlspecialchars($req['page']) ?></td>
            <td><span class="badge" style="background:<?= ($req['method'] ?? 'GET') === 'POST' ? '#f59e0b30;color:#f59e0b' : '#6c5ce720;color:#a29bfe' ?>;"><?= htmlspecialchars($req['method'] ?? 'GET') ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ============================================================ -->
<!-- SECTION 3 : LOGS BDD — DERNIERES ACTIONS -->
<!-- ============================================================ -->
<div class="section">
    <h2>Dernieres actions utilisateurs (Logs BDD — 30 derniers)</h2>
    <table>
        <thead><tr><th>Heure</th><th>IP</th><th>Action</th><th>Page</th><th>Detail</th><th>User</th><th>Ecran</th><th>Langue</th><th>Duree</th></tr></thead>
        <tbody>
        <?php foreach ($lastLogs as $log):
            $ac = $log['action'] ?? '';
            $acColor = $actionColors[$ac] ?? '#5a6580';
        ?>
        <tr>
            <td class="time"><?= htmlspecialchars($log['ts']) ?></td>
            <td class="mono"><?= htmlspecialchars($log['ip']) ?></td>
            <td><span class="badge" style="background:<?= $acColor ?>25;color:<?= $acColor ?>;"><?= htmlspecialchars($ac) ?></span></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['page']) ?>"><a href="https://bokonzi.com<?= htmlspecialchars($log['page']) ?>" target="_blank" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($log['page']) ?></a></td>
            <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['detail']) ?>"><?php $__det = $log['detail']; if (strpos($__det, '/?') === 0 || strpos($__det, '/api/') === 0): ?><a href="https://bokonzi.com<?= htmlspecialchars($__det) ?>" target="_blank" style="color:#58a6ff;text-decoration:none;"><?= htmlspecialchars(mb_substr($__det, 0, 60)) ?></a><?php else: ?><?= htmlspecialchars(mb_substr($__det, 0, 60)) ?><?php endif; ?></td>
            <td><?= $log['uname'] ? '<span style="color:#55efc4;">' . htmlspecialchars($log['uname']) . '</span>' : '<span class="dim">-</span>' ?></td>
            <td class="dim"><?= htmlspecialchars($log['screen']) ?></td>
            <td class="dim"><?= htmlspecialchars($log['lang']) ?></td>
            <td class="dim"><?= $log['duration_ms'] ? round($log['duration_ms'] / 1000) . 's' : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ============================================================ -->
<!-- SECTION 4 : GRAPHIQUES -->
<!-- ============================================================ -->

<!-- Activité par heure -->
<div class="section">
    <h2>Activite par heure (aujourd'hui)</h2>
    <?php $maxH = max(1, max($hourlyActivity)); ?>
    <div class="hour-chart">
        <?php for ($h = 0; $h < 24; $h++):
            $val = $hourlyActivity[$h];
            $height = max(2, round(($val / $maxH) * 60));
        ?>
        <div class="hour-bar" style="height:<?= $height ?>px;<?= $h === (int)date('G') ? 'background:#f59e0b;' : '' ?>">
            <div class="htip"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>h : <?= $val ?> events</div>
        </div>
        <?php endfor; ?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:10px;color:#5a6580;margin-top:4px;">
        <span>00h</span><span>06h</span><span>12h</span><span>18h</span><span>23h</span>
    </div>
</div>

<div class="cols-3">
    <!-- Actions distribution -->
    <div class="section">
        <h2>Actions aujourd'hui</h2>
        <div class="bar-chart">
        <?php $maxAc = max(1, !empty($actionsToday) ? max($actionsToday) : 1);
        foreach ($actionsToday as $action => $count):
            $color = $actionColors[$action] ?? '#5a6580';
            $w = round(($count / $maxAc) * 100);
        ?>
        <div class="bar-row">
            <span class="bar-label"><?= $action ?></span>
            <div class="bar-fill" style="width:<?= $w ?>%;background:<?= $color ?>;"></div>
            <span class="bar-val"><?= number_format($count) ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Devices -->
    <div class="section">
        <h2>Ecrans (resolutions)</h2>
        <table>
            <thead><tr><th>Resolution</th><th>Nb</th><th>Type</th></tr></thead>
            <tbody>
            <?php foreach ($devices as $d):
                $w = (int)explode('x', $d['screen'])[0];
                $type = $w <= 480 ? 'Mobile' : ($w <= 1024 ? 'Tablette' : 'Desktop');
                $typeColor = $w <= 480 ? '#f59e0b' : ($w <= 1024 ? '#8b5cf6' : '#10b981');
            ?>
            <tr>
                <td class="mono"><?= htmlspecialchars($d['screen']) ?></td>
                <td><?= $d['c'] ?></td>
                <td><span class="badge" style="background:<?= $typeColor ?>25;color:<?= $typeColor ?>;"><?= $type ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($devices)): ?><tr><td colspan="3" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Langues + Referrers -->
    <div class="section">
        <h2>Langues</h2>
        <table>
            <thead><tr><th>Langue</th><th>Nb</th></tr></thead>
            <tbody>
            <?php foreach ($languages as $l): ?>
            <tr><td><?= htmlspecialchars($l['lang']) ?></td><td><?= $l['c'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($languages)): ?><tr><td colspan="2" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 5 : SOURCES DE TRAFIC -->
<!-- ============================================================ -->
<div class="cols-2">
    <div class="section">
        <h2>Sources de trafic (referrers externes)</h2>
        <table>
            <thead><tr><th>Referrer</th><th>Visites</th></tr></thead>
            <tbody>
            <?php foreach ($referrers as $ref): ?>
            <tr>
                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($ref['referrer']) ?>"><?= htmlspecialchars($ref['referrer']) ?></td>
                <td class="green"><?= $ref['c'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($referrers)): ?><tr><td colspan="2" class="dim" style="text-align:center;">Aucun referrer externe</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Top pages aujourd'hui</h2>
        <table>
            <thead><tr><th>Page</th><th>Vues</th></tr></thead>
            <tbody>
            <?php foreach ($topPagesToday as $p): ?>
            <tr>
                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($p['page']) ?>"><?= htmlspecialchars($p['page']) ?></td>
                <td class="green"><?= $p['c'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($topPagesToday)): ?><tr><td colspan="2" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 6 : HISTORIQUE 7 JOURS -->
<!-- ============================================================ -->
<div class="section">
    <h2>Historique 7 derniers jours</h2>
    <table>
        <thead><tr><th>Date</th><th>Events</th><th>IPs uniques</th><th>Sessions</th></tr></thead>
        <tbody>
        <?php foreach ($weeklyLogs as $w): ?>
        <tr>
            <td><?= $w['d'] ?></td>
            <td class="green"><?= number_format($w['c']) ?></td>
            <td><?= number_format($w['ips']) ?></td>
            <td><?= number_format($w['sessions']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ============================================================ -->
<!-- SECTION 7 : TOP IPS + SECURITE -->
<!-- ============================================================ -->
<div class="cols-2">
    <div class="section">
        <h2>Top IPs aujourd'hui (avec User-Agent)</h2>
        <table>
            <thead><tr><th>#</th><th>IP</th><th>Req</th><th>Premier</th><th>Dernier</th><th>User Agent</th></tr></thead>
            <tbody>
            <?php $i = 0; foreach ($topIpsToday as $tip): $i++;
                $isBot = preg_match('/bot|crawl|spider|slurp/i', $tip['ua'] ?? '');
            ?>
            <tr style="<?= $isBot ? 'opacity:0.5;' : '' ?>">
                <td><?= $i ?></td>
                <td class="mono"><?= htmlspecialchars($tip['ip']) ?></td>
                <td class="green"><?= number_format($tip['c']) ?></td>
                <td class="time"><?= substr($tip['first_seen'], 11) ?></td>
                <td class="time"><?= substr($tip['last_seen'], 11) ?></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($tip['ua'] ?? '') ?>">
                    <?= $isBot ? '<span class="badge" style="background:#ef444430;color:#ef4444;">BOT</span> ' : '' ?><?= htmlspecialchars(mb_substr($tip['ua'] ?? '', 0, 80)) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($topIpsToday)): ?><tr><td colspan="6" class="dim" style="text-align:center;">Aucune activite</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ERREURS JS -->
    <div class="section">
        <h2>Erreurs JavaScript (aujourd'hui) — <?= count($jsErrors) ?></h2>
        <table>
            <thead><tr><th>Heure</th><th>IP</th><th>Erreur</th><th>Fichier</th></tr></thead>
            <tbody>
            <?php foreach ($jsErrors as $err): ?>
            <tr>
                <td class="time"><?= substr($err['ts'], 11) ?></td>
                <td class="mono"><?= htmlspecialchars($err['ip']) ?></td>
                <td style="color:#ef4444;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($err['detail']) ?>"><?= htmlspecialchars(mb_substr($err['detail'], 0, 80)) ?></td>
                <td class="dim" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars(mb_substr($err['value'], 0, 60)) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($jsErrors)): ?><tr><td colspan="4" class="dim" style="text-align:center;">Aucune erreur</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 8 : ENGAGEMENT — FOLLOWS + EMAILS -->
<!-- ============================================================ -->
<div class="cols-3">
    <div class="section">
        <h2>Derniers follows athletes (<?= $stats['athlete_follows'] ?>)</h2>
        <table>
            <thead><tr><th>Email</th><th>Athlete</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($lastFollowsAth as $f): ?>
            <tr>
                <td class="mono"><?= htmlspecialchars($f['email']) ?></td>
                <td><a href="../index.php?page=profil&id=<?= (int)$f['athlete_id_ext'] ?>" style="color:#55efc4;">#<?= $f['athlete_id_ext'] ?></a></td>
                <td class="dim"><?= $f['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lastFollowsAth)): ?><tr><td colspan="3" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Derniers follows clubs (<?= $stats['club_follows'] ?>)</h2>
        <table>
            <thead><tr><th>Email</th><th>Club</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($lastFollowsClub as $f): ?>
            <tr>
                <td class="mono"><?= htmlspecialchars($f['email']) ?></td>
                <td><?= (int)$f['club_id'] ?></td>
                <td class="dim"><?= $f['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lastFollowsClub)): ?><tr><td colspan="3" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Emails collectes (<?= $stats['email_subscribers'] ?>)</h2>
        <table>
            <thead><tr><th>Email</th><th>Source</th><th>Detail</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($lastSubs as $s): ?>
            <tr>
                <td class="mono"><?= htmlspecialchars($s['email']) ?></td>
                <td><span class="badge" style="background:#6c5ce720;color:#a29bfe;"><?= htmlspecialchars($s['source']) ?></span></td>
                <td class="dim"><?= htmlspecialchars(mb_substr($s['detail'], 0, 30)) ?></td>
                <td class="dim"><?= $s['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lastSubs)): ?><tr><td colspan="4" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 9 : UTILISATEURS -->
<!-- ============================================================ -->
<div class="cols-2">
    <div class="section">
        <h2>Derniers inscrits (<?= $stats['users'] ?> total)</h2>
        <table>
            <thead><tr><th>#</th><th>Email</th><th>Nom</th><th>Role</th><th>Athlete</th></tr></thead>
            <tbody>
            <?php foreach ($lastUsers as $u): ?>
            <tr>
                <td><?= $u['id_user'] ?></td>
                <td class="mono"><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?></td>
                <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
                <td><?= $u['id_athlete'] ? '<a href="../index.php?page=profil&id=' . $u['id_athlete'] . '" style="color:#55efc4;">#' . $u['id_athlete'] . '</a>' : '-' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Repartition par role</h2>
        <div class="bar-chart">
        <?php $maxRole = max(1, !empty($usersByRole) ? max($usersByRole) : 1);
        foreach ($usersByRole as $role => $count):
            $colors = ['admin' => '#e11d48', 'athlete' => '#10b981', 'coach' => '#6c5ce7', 'club' => '#f59e0b'];
            $color = $colors[$role] ?? '#5a6580';
            $w = round(($count / $maxRole) * 100);
        ?>
        <div class="bar-row">
            <span class="bar-label"><?= $role ?></span>
            <div class="bar-fill" style="width:<?= $w ?>%;background:<?= $color ?>;"></div>
            <span class="bar-val"><?= number_format($count) ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 10 : BASE DE DONNEES + SERVEUR -->
<!-- ============================================================ -->
<div class="cols-2">
    <div class="section">
        <h2>Tables BDD — <?= fmtSize($dbSize) ?></h2>
        <table>
            <thead><tr><th>Table</th><th>Lignes</th><th>Taille</th></tr></thead>
            <tbody>
            <?php foreach ($tableSizes as $ts): ?>
            <tr>
                <td class="mono"><?= $ts['table_name'] ?></td>
                <td><?= number_format((int)$ts['table_rows']) ?></td>
                <td class="dim"><?= fmtSize((float)$ts['size']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Serveur</h2>
        <table>
            <tbody>
            <tr><td>PHP</td><td class="mono"><?= $phpVersion ?></td></tr>
            <tr><td>MySQL</td><td class="mono"><?= $mysqlVersion ?></td></tr>
            <tr><td>Disque libre</td><td class="mono"><?= $diskFree ? fmtSize($diskFree) : 'N/A' ?></td></tr>
            <tr><td>Disque total</td><td class="mono"><?= $diskTotal ? fmtSize($diskTotal) : 'N/A' ?></td></tr>
            <tr><td>Disque utilise</td><td class="mono"><?= ($diskTotal && $diskFree) ? round(($diskTotal - $diskFree) / $diskTotal * 100, 1) . '%' : 'N/A' ?></td></tr>
            <tr><td>Cache fichiers</td><td class="mono"><?= count($cacheFiles) ?> fichiers (<?= fmtSize($cacheSize) ?>)</td></tr>
            <tr><td>Cache plus ancien</td><td class="dim"><?= $oldestCache ? date('d/m/Y H:i', $oldestCache) : '-' ?></td></tr>
            <tr><td>Cache plus recent</td><td class="dim"><?= $newestCache ? date('d/m/Y H:i', $newestCache) : '-' ?></td></tr>
            <tr><td>IP Tracker mois dispo</td><td class="mono"><?= implode(', ', $ipLogMonths) ?: '-' ?></td></tr>
            <tr><td>Heure serveur</td><td class="mono"><?= date('Y-m-d H:i:s') ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 11 : DONNEES ATHLETES -->
<!-- ============================================================ -->
<div class="section">
    <h2>Donnees athletes</h2>
    <div class="grid" style="padding:0;">
        <div class="card"><div class="num"><?= number_format($stats['athlete_records']) ?></div><div class="label">Records</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_resultats']) ?></div><div class="label">Resultats</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_medailles']) ?></div><div class="label">Medailles</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_podiums']) ?></div><div class="label">Podiums</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_selections']) ?></div><div class="label">Selections</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_progressions']) ?></div><div class="label">Progressions</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_niveaux']) ?></div><div class="label">Niveaux</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_clubs']) ?></div><div class="label">Clubs-Athletes</div></div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 12 : ANALYTICS VUES (Profils & Clubs) — INTERACTIF -->
<!-- ============================================================ -->
<?php if ($hasVuesTables): ?>
<script>
var VUES_DATA = <?= json_encode([
    'totals' => [
        'athVues' => $totalVuesAthletes, 'clubVues' => $totalVuesClubs,
        'nbAth' => $nbAthVus, 'nbClubs' => $nbClubsVus,
        'ipsAth' => $uniqueVueIpsAth, 'ipsClub' => $uniqueVueIpsClub,
        'todayAth' => $vuesTodayAth, 'todayClub' => $vuesTodayClub
    ],
    'parJour' => $vuesParJour,
    'topAthletes' => $topVuesAthletes,
    'topClubs' => $topVuesClubs,
    'lastAth' => $lastVuesAthletes,
    'lastClub' => $lastVuesClubs,
    'topIps' => $topVuesIps,
    'rateLimited' => $rateLimitedIps,
    'rateLimitMax' => $rateLimitDailyLimit,
    'bannedIps' => $bannedIps
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- KPI Cards Vues (PHP statique) -->
<div class="section"><h2 style="color:#f59e0b;font-size:16px;border-color:#f59e0b40;">&#128065; Analytics Vues — Profils &amp; Clubs</h2></div>
<div class="grid">
    <div class="card" style="border-color:#f59e0b40;"><div class="num warn"><?= number_format($totalVuesAthletes) ?></div><div class="label">Vues profils (total)</div><div class="sub"><?= $nbAthVus ?> athletes</div></div>
    <div class="card" style="border-color:#8b5cf640;"><div class="num info"><?= number_format($totalVuesClubs) ?></div><div class="label">Vues clubs (total)</div><div class="sub"><?= $nbClubsVus ?> clubs</div></div>
    <div class="card" style="border-color:#10b98140;"><div class="num green"><?= number_format($uniqueVueIpsAth) ?></div><div class="label">IPs uniques (profils)</div></div>
    <div class="card" style="border-color:#10b98140;"><div class="num green"><?= number_format($uniqueVueIpsClub) ?></div><div class="label">IPs uniques (clubs)</div></div>
    <div class="card" style="border-color:#3b82f640;"><div class="num" style="color:#60a5fa;"><?= number_format($vuesTodayAth) ?></div><div class="label">Profils vus aujourd'hui</div></div>
    <div class="card" style="border-color:#3b82f640;"><div class="num" style="color:#60a5fa;"><?= number_format($vuesTodayClub) ?></div><div class="label">Clubs vus aujourd'hui</div></div>
    <div class="card" style="border-color:#ec489940;"><div class="num pink"><?= number_format($totalVuesAthletes + $totalVuesClubs) ?></div><div class="label">Total combiné</div></div>
    <div class="card" style="border-color:#ec489940;"><div class="num pink"><?= number_format($vuesTodayAth + $vuesTodayClub) ?></div><div class="label">Aujourd'hui (total)</div></div>
</div>

<!-- Chart 14 jours (cliquable) -->
<div class="section">
    <h2>Historique 14 jours <span style="font-size:11px;color:#5a6580;font-weight:400;">— cliquez sur une barre pour le detail</span></h2>
    <div style="height:200px;position:relative;"><canvas id="vueChart14"></canvas></div>
</div>

<!-- Onglets interactifs -->
<div class="vue-tabs" id="vueTabs">
    <div class="vue-tab active" onclick="_vueTab('athletes')">Athletes</div>
    <div class="vue-tab" onclick="_vueTab('clubs')">Clubs</div>
    <div class="vue-tab" onclick="_vueTab('ips')">IPs</div>
    <div class="vue-tab" onclick="_vueTab('live')"><span class="live-dot"></span>Temps reel</div>
    <div class="vue-tab" onclick="_vueTab('blocked')" style="color:#ef4444;">&#9888; Bloques</div>
</div>
<div class="vue-tab-body" id="vueTabBody"></div>

<!-- Drawer -->
<div class="vue-overlay" id="vueOverlay" onclick="_vueClose()"></div>
<div class="vue-drawer" id="vueDrawer">
    <div class="vue-drawer-head">
        <span class="vd-title" id="vueDrawerTitle"></span>
        <button class="vd-close" onclick="_vueClose()">&times;</button>
    </div>
    <div class="vd-body" id="vueDrawerBody"></div>
</div>

<script>
(function(){
var D = VUES_DATA;
var _curTab = 'athletes';
var _sortKey = 'vues', _sortDir = 'desc', _filter = '';

function _esc(s) { var d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }
function _fmtDate(s) { if (!s) return '-'; var d = new Date(s); return (d.getDate()<10?'0':'')+d.getDate()+'/'+(d.getMonth()<9?'0':'')+(d.getMonth()+1)+' '+(d.getHours()<10?'0':'')+d.getHours()+':'+(d.getMinutes()<10?'0':'')+d.getMinutes(); }
function _fmtDateFull(s) { if (!s) return '-'; var d = new Date(s); return (d.getDate()<10?'0':'')+d.getDate()+'/'+(d.getMonth()<9?'0':'')+(d.getMonth()+1)+'/'+d.getFullYear()+' '+(d.getHours()<10?'0':'')+d.getHours()+':'+(d.getMinutes()<10?'0':'')+d.getMinutes()+':'+(d.getSeconds()<10?'0':'')+d.getSeconds(); }

// === CHART 14 JOURS ===
function _vueRenderChart() {
    var pj = D.parJour.slice().reverse();
    var labels = pj.map(function(d) { return d.d.substring(5); });
    var athData = pj.map(function(d) { return parseInt(d.ath)||0; });
    var clubData = pj.map(function(d) { return parseInt(d.club)||0; });
    var ctx = document.getElementById('vueChart14');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Profils', data: athData, backgroundColor: '#f59e0b', borderRadius: 4 },
                { label: 'Clubs', data: clubData, backgroundColor: '#8b5cf6', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                x: { stacked: true, grid: { color: '#1e2a3a' }, ticks: { color: '#5a6580', font: { size: 10 } } },
                y: { stacked: true, grid: { color: '#1e2a3a' }, ticks: { color: '#5a6580' }, beginAtZero: true }
            },
            plugins: { legend: { labels: { color: '#8b949e', boxWidth: 12 } } },
            onClick: function(evt, elems) {
                if (elems.length > 0) {
                    var idx = elems[0].index;
                    _vueShowDay(pj[idx].d);
                }
            }
        }
    });
}

// === TABS ===
window._vueTab = function(tab) {
    _curTab = tab; _filter = ''; _sortKey = 'vues'; _sortDir = 'desc';
    var tabs = document.querySelectorAll('.vue-tab');
    tabs.forEach(function(t) { t.classList.remove('active'); });
    tabs[['athletes','clubs','ips','live','blocked'].indexOf(tab)].classList.add('active');
    _vueRenderTab();
};

function _vueRenderTab() {
    var el = document.getElementById('vueTabBody');
    if (_curTab === 'athletes') _vueRenderAthletes(el);
    else if (_curTab === 'clubs') _vueRenderClubs(el);
    else if (_curTab === 'ips') _vueRenderIps(el);
    else if (_curTab === 'blocked') _vueRenderBlocked(el);
    else _vueRenderLive(el);
}

function _sortHeader(key, label) {
    var cls = 'vue-sort' + (_sortKey === key ? ' ' + _sortDir : '');
    return '<th class="'+cls+'" onclick="_vueSortBy(\''+key+'\')">'+label+'</th>';
}
window._vueSortBy = function(key) {
    if (_sortKey === key) _sortDir = _sortDir === 'desc' ? 'asc' : 'desc';
    else { _sortKey = key; _sortDir = 'desc'; }
    _vueRenderTab();
};
window._vueFilter = function(q) { _filter = q.toLowerCase(); _vueRenderTab(); };

function _sortItems(arr, key) {
    return arr.slice().sort(function(a, b) {
        var va = key === 'nom' ? (a[key]||'').toLowerCase() : (parseFloat(a[key])||0);
        var vb = key === 'nom' ? (b[key]||'').toLowerCase() : (parseFloat(b[key])||0);
        if (typeof va === 'string') return _sortDir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
        return _sortDir === 'asc' ? va - vb : vb - va;
    });
}

// === ATHLETES TAB (simplifie : Nom + Club + Vues + IPs) ===
function _vueRenderAthletes(el) {
    var items = D.topAthletes;
    if (_filter) items = items.filter(function(a) {
        return (a.nom||'').toLowerCase().indexOf(_filter) >= 0 || (a.club||'').toLowerCase().indexOf(_filter) >= 0
            || (a.nationalite_athlete||'').toLowerCase().indexOf(_filter) >= 0;
    });
    items = _sortItems(items, _sortKey);
    var h = '<input type="text" class="vue-search" placeholder="&#128269; Rechercher un athlete, club, nationalite..." oninput="_vueFilter(this.value)" value="'+_esc(_filter)+'">';
    h += '<p style="color:#5a6580;font-size:11px;margin-bottom:10px;">'+items.length+' athlete'+(items.length>1?'s':'')+' — cliquez sur une ligne pour voir le detail</p>';
    h += '<div style="max-height:520px;overflow-y:auto;"><table style="font-size:13px;"><thead><tr>'
        + '<th style="width:40px;">#</th>'
        + _sortHeader('nom','Nom Prenom')
        + '<th>Club</th>'
        + _sortHeader('vues','Vues')
        + _sortHeader('ips_uniques','IPs uniques')
        + '</tr></thead><tbody>';
    if (!items.length) h += '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucune donnee</td></tr>';
    items.forEach(function(a, i) {
        h += '<tr class="vue-row" onclick="_vueShowAth('+i+')">'
            + '<td class="dim" style="font-size:12px;">'+(i+1)+'</td>'
            + '<td style="font-weight:700;color:#e2e8f0;font-size:14px;">'+_esc(a.nom)+'<br><span style="font-size:10px;color:#5a6580;font-weight:400;">'+(a.sexe_athlete||'')+' '+_esc(a.categorie_athlete)+' '+_esc(a.nationalite_athlete)+'</span></td>'
            + '<td style="color:#8b949e;font-size:12px;">'+_esc((a.club||'').replace(/\*\s*$/,''))+'</td>'
            + '<td style="text-align:center;"><span style="background:#f59e0b20;color:#f59e0b;font-weight:800;font-size:16px;padding:4px 12px;border-radius:8px;">'+a.vues+'</span></td>'
            + '<td style="text-align:center;"><span style="color:#55efc4;font-weight:700;font-size:14px;">'+(a.ips_uniques||0)+'</span></td>'
            + '</tr>';
    });
    h += '</tbody></table></div>';
    el.innerHTML = h;
}

// === CLUBS TAB (simplifie : Nom + Vues + IPs) ===
function _vueRenderClubs(el) {
    var items = D.topClubs;
    if (_filter) items = items.filter(function(c) {
        return (c.nom_club||'').toLowerCase().indexOf(_filter) >= 0;
    });
    items = _sortItems(items, _sortKey === 'nom' ? 'nom_club' : _sortKey);
    var h = '<input type="text" class="vue-search" placeholder="&#128269; Rechercher un club..." oninput="_vueFilter(this.value)" value="'+_esc(_filter)+'">';
    h += '<p style="color:#5a6580;font-size:11px;margin-bottom:10px;">'+items.length+' club'+(items.length>1?'s':'')+' — cliquez sur une ligne pour voir le detail</p>';
    h += '<div style="max-height:520px;overflow-y:auto;"><table style="font-size:13px;"><thead><tr>'
        + '<th style="width:40px;">#</th>'
        + _sortHeader('nom_club','Nom du Club')
        + _sortHeader('nb_athletes','Athletes')
        + _sortHeader('vues','Vues')
        + _sortHeader('ips_uniques','IPs uniques')
        + '</tr></thead><tbody>';
    if (!items.length) h += '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucune donnee</td></tr>';
    items.forEach(function(c, i) {
        h += '<tr class="vue-row" onclick="_vueShowClub('+i+')">'
            + '<td class="dim" style="font-size:12px;">'+(i+1)+'</td>'
            + '<td style="font-weight:700;color:#e2e8f0;font-size:14px;">'+_esc(c.nom_club)+'</td>'
            + '<td style="color:#8b949e;text-align:center;">'+(c.nb_athletes||0)+'</td>'
            + '<td style="text-align:center;"><span style="background:#f59e0b20;color:#f59e0b;font-weight:800;font-size:16px;padding:4px 12px;border-radius:8px;">'+c.vues+'</span></td>'
            + '<td style="text-align:center;"><span style="color:#55efc4;font-weight:700;font-size:14px;">'+(c.ips_uniques||0)+'</span></td>'
            + '</tr>';
    });
    h += '</tbody></table></div>';
    el.innerHTML = h;
}

// === IPS TAB (simplifie) ===
function _vueRenderIps(el) {
    var items = D.topIps;
    if (_filter) items = items.filter(function(ip) { return ip.ip.indexOf(_filter) >= 0; });
    var h = '<input type="text" class="vue-search" placeholder="&#128269; Rechercher une IP..." oninput="_vueFilter(this.value)" value="'+_esc(_filter)+'">';
    h += '<p style="color:#5a6580;font-size:11px;margin-bottom:10px;">'+items.length+' IP'+(items.length>1?'s':'')+' — cliquez pour voir la navigation complete</p>';
    h += '<div style="max-height:520px;overflow-y:auto;"><table style="font-size:13px;"><thead><tr><th>Adresse IP</th><th style="text-align:center;">Profils</th><th style="text-align:center;">Clubs</th><th style="text-align:center;">Total</th><th>Derniere visite</th></tr></thead><tbody>';
    if (!items.length) h += '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucune donnee</td></tr>';
    items.forEach(function(ip, i) {
        var total = (parseInt(ip.nb_profils)||0) + (parseInt(ip.nb_clubs)||0);
        h += '<tr class="vue-row" onclick="_vueShowIp('+i+')">'
            + '<td class="mono" style="font-weight:700;font-size:13px;">'+_esc(ip.ip)+'</td>'
            + '<td style="text-align:center;"><span style="background:#f59e0b20;color:#f59e0b;font-weight:700;padding:3px 10px;border-radius:6px;">'+(ip.nb_profils||0)+'</span></td>'
            + '<td style="text-align:center;"><span style="background:#8b5cf620;color:#a78bfa;font-weight:700;padding:3px 10px;border-radius:6px;">'+(ip.nb_clubs||0)+'</span></td>'
            + '<td style="text-align:center;"><span style="color:#55efc4;font-weight:800;font-size:16px;">'+total+'</span></td>'
            + '<td class="time" style="font-size:12px;">'+_fmtDate(ip.last_vue)+'</td>'
            + '</tr>';
    });
    h += '</tbody></table></div>';
    el.innerHTML = h;
}

// === LIVE TAB (simplifie) ===
function _vueRenderLive(el) {
    var all = [];
    (D.lastAth||[]).forEach(function(v) { all.push({type:'athlete', ip:v.ip, nom:v.nom||'?', id:v.athlete_id_ext, date:v.created_at}); });
    (D.lastClub||[]).forEach(function(v) { all.push({type:'club', ip:v.ip, nom:v.nom_club||'?', id:v.club_id, date:v.created_at}); });
    all.sort(function(a,b) { return (b.date||'').localeCompare(a.date||''); });
    var h = '<p style="color:#5a6580;font-size:11px;margin-bottom:10px;">'+all.length+' dernieres vues — profils + clubs melanges</p>';
    h += '<div style="max-height:550px;overflow-y:auto;"><table style="font-size:13px;"><thead><tr><th>Date/Heure</th><th>IP</th><th>Type</th><th>Nom</th></tr></thead><tbody>';
    if (!all.length) h += '<tr><td colspan="4" style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucune vue</td></tr>';
    all.forEach(function(v) {
        var typeColor = v.type === 'athlete' ? '#f59e0b' : '#8b5cf6';
        var typeBg = v.type === 'athlete' ? '#f59e0b20' : '#8b5cf620';
        var typeLabel = v.type === 'athlete' ? 'Profil' : 'Club';
        var link = v.type === 'athlete' ? '../index.php?page=profil&id='+v.id : '../index.php?page=recherche&club='+encodeURIComponent(v.nom);
        h += '<tr class="vue-row" onclick="window.open(\''+link+'\',\'_blank\')">'
            + '<td class="time" style="font-size:12px;">'+_fmtDateFull(v.date)+'</td>'
            + '<td class="mono" style="font-size:12px;">'+_esc(v.ip)+'</td>'
            + '<td><span style="background:'+typeBg+';color:'+typeColor+';font-weight:700;font-size:11px;padding:3px 10px;border-radius:6px;">'+typeLabel+'</span></td>'
            + '<td style="font-weight:700;color:#e2e8f0;font-size:14px;">'+_esc(v.nom)+'</td>'
            + '</tr>';
    });
    h += '</tbody></table></div>';
    el.innerHTML = h;
}

// === BLOCKED TAB (IPs rate limitees) ===
function _vueRenderBlocked(el) {
    var items = D.rateLimited || [];
    var limit = D.rateLimitMax || 20;
    if (_filter) items = items.filter(function(r) { return r.ip.indexOf(_filter) >= 0 || r.date.indexOf(_filter) >= 0; });

    // Grouper par IP pour stats globales
    var byIp = {};
    items.forEach(function(r) {
        if (!byIp[r.ip]) byIp[r.ip] = { ip: r.ip, totalReq: 0, totalDep: 0, jours: 0, dates: [] };
        byIp[r.ip].totalReq += r.count;
        byIp[r.ip].totalDep += r.depassement;
        byIp[r.ip].jours++;
        byIp[r.ip].dates.push(r);
    });
    var uniqueIps = Object.keys(byIp).length;

    var h = '<input type="text" class="vue-search" placeholder="&#128269; Rechercher par IP ou date..." oninput="_vueFilter(this.value)" value="'+_esc(_filter)+'">';
    h += '<div style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap;">';
    h += '<div style="background:#ef444420;border:1px solid #ef444440;border-radius:10px;padding:10px 16px;flex:1;min-width:120px;text-align:center;">'
        + '<div style="color:#ef4444;font-weight:800;font-size:22px;">'+items.length+'</div>'
        + '<div style="color:#8b949e;font-size:11px;">Blocages (7j)</div></div>';
    h += '<div style="background:#f59e0b20;border:1px solid #f59e0b40;border-radius:10px;padding:10px 16px;flex:1;min-width:120px;text-align:center;">'
        + '<div style="color:#f59e0b;font-weight:800;font-size:22px;">'+uniqueIps+'</div>'
        + '<div style="color:#8b949e;font-size:11px;">IPs uniques bloquees</div></div>';
    h += '<div style="background:#8b5cf620;border:1px solid #8b5cf640;border-radius:10px;padding:10px 16px;flex:1;min-width:120px;text-align:center;">'
        + '<div style="color:#a78bfa;font-weight:800;font-size:22px;">'+limit+'</div>'
        + '<div style="color:#8b949e;font-size:11px;">Limite / jour</div></div>';
    h += '</div>';

    h += '<p style="color:#5a6580;font-size:11px;margin-bottom:10px;">IPs ayant depasse la limite de '+limit+' requetes/jour et ayant vu la page d\'inscription — cliquez pour le detail</p>';

    h += '<div style="max-height:520px;overflow-y:auto;"><table style="font-size:13px;"><thead><tr>'
        + '<th>Date</th>'
        + '<th>Adresse IP</th>'
        + '<th style="text-align:center;">Requetes</th>'
        + '<th style="text-align:center;">Depassement</th>'
        + '<th style="text-align:center;">Statut</th>'
        + '</tr></thead><tbody>';

    if (!items.length) h += '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucune IP bloquee ces 5 derniers jours</td></tr>';

    items.forEach(function(r, i) {
        var severity = r.depassement > 20 ? '#ef4444' : r.depassement > 5 ? '#f59e0b' : '#eab308';
        var severityBg = r.depassement > 20 ? '#ef444420' : r.depassement > 5 ? '#f59e0b20' : '#eab30820';
        var severityLabel = r.depassement > 20 ? 'Abusif' : r.depassement > 5 ? 'Excessif' : 'Limite';
        h += '<tr class="vue-row" onclick="_vueShowBlocked('+i+')">'
            + '<td class="time" style="font-size:12px;">'+_esc(r.date)+'</td>'
            + '<td class="mono" style="font-weight:700;font-size:13px;">'+_esc(r.ip)+'</td>'
            + '<td style="text-align:center;"><span style="background:#ef444420;color:#ef4444;font-weight:800;font-size:16px;padding:4px 12px;border-radius:8px;">'+r.count+'</span></td>'
            + '<td style="text-align:center;"><span style="color:#f59e0b;font-weight:700;">+'+r.depassement+'</span></td>'
            + '<td style="text-align:center;"><span style="background:'+severityBg+';color:'+severity+';font-weight:700;font-size:11px;padding:3px 10px;border-radius:6px;">'+severityLabel+'</span></td>'
            + '</tr>';
    });
    h += '</tbody></table></div>';

    // IPs bannies definitivement
    var banned = D.bannedIps || {};
    var bannedKeys = Object.keys(banned);
    if (bannedKeys.length > 0) {
        h += '<div style="margin-top:16px;"><h4 style="color:#ef4444;font-size:13px;margin-bottom:8px;">&#128274; IPs bannies definitivement ('+bannedKeys.length+')</h4>';
        h += '<table style="font-size:12px;"><thead><tr><th>IP</th><th>Date du ban</th></tr></thead><tbody>';
        bannedKeys.forEach(function(ip) {
            h += '<tr class="vue-row">'
                + '<td class="mono" style="font-weight:700;" onclick="_vueShowBlockedIp(\''+_esc(ip)+'\')">'+_esc(ip)+'</td>'
                + '<td><span style="background:#ef444420;color:#ef4444;font-weight:600;padding:3px 10px;border-radius:6px;font-size:11px;">'+_esc(banned[ip])+'</span></td>'
                + '<td><button onclick="_unbanIp(\''+_esc(ip)+'\',this)" style="background:#10b98120;border:1px solid #10b98140;color:#10b981;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Debannir</button></td>'
                + '</tr>';
        });
        h += '</tbody></table></div>';
    }

    el.innerHTML = h;
}

// Drawer: detail IP bloquee (une entree = 1 jour)
window._vueShowBlocked = function(idx) {
    var items = D.rateLimited || [];
    if (_filter) items = items.filter(function(r) { return r.ip.indexOf(_filter) >= 0 || r.date.indexOf(_filter) >= 0; });
    var r = items[idx]; if (!r) return;
    var limit = D.rateLimitMax || 20;

    var h = '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#ef4444;">'+r.count+'</div><div class="vd-lbl">Requetes ce jour</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">+'+r.depassement+'</div><div class="vd-lbl">Au-dela de la limite</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#8b949e;">'+limit+'</div><div class="vd-lbl">Limite / jour</div></div>'
        + '</div>';

    h += '<div class="vd-section"><h4>Informations</h4><table>'
        + '<tr><td class="dim">Adresse IP</td><td class="mono" style="font-weight:700;">'+_esc(r.ip)+'</td></tr>'
        + '<tr><td class="dim">Date</td><td>'+_esc(r.date)+'</td></tr>'
        + '<tr><td class="dim">Requetes effectuees</td><td style="color:#ef4444;font-weight:700;">'+r.count+'</td></tr>'
        + '<tr><td class="dim">Limite journaliere</td><td>'+limit+'</td></tr>'
        + '<tr><td class="dim">Depassement</td><td style="color:#f59e0b;font-weight:700;">+'+r.depassement+' requetes</td></tr>'
        + '</table></div>';

    // Verifier si cette IP a aussi consulte des profils/clubs
    var profils = (D.lastAth||[]).filter(function(v) { return v.ip === r.ip; });
    var clubs = (D.lastClub||[]).filter(function(v) { return v.ip === r.ip; });

    if (profils.length) {
        h += '<div class="vd-section"><h4>Profils consultes par cette IP ('+profils.length+')</h4>';
        h += '<table><thead><tr><th>Date</th><th>Athlete</th><th>ID</th></tr></thead><tbody>';
        profils.forEach(function(p) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=profil&id='+p.athlete_id_ext+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(p.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(p.nom||'?')+'</td>'
                + '<td class="mono">'+p.athlete_id_ext+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }

    if (clubs.length) {
        h += '<div class="vd-section"><h4>Clubs consultes par cette IP ('+clubs.length+')</h4>';
        h += '<table><thead><tr><th>Date</th><th>Club</th></tr></thead><tbody>';
        clubs.forEach(function(c) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=recherche&club='+encodeURIComponent(c.nom_club||'')+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(c.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(c.nom_club||'?')+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }

    if (!profils.length && !clubs.length) {
        h += '<div class="vd-section"><p class="dim">Cette IP n\'apparait pas dans les 50 dernieres vues de profils/clubs enregistrees.</p></div>';
    }

    h += '<div class="vd-section" style="margin-top:16px;padding:12px;background:#ef444410;border:1px solid #ef444430;border-radius:8px;">'
        + '<p style="color:#ef4444;font-size:12px;font-weight:600;margin-bottom:4px;">&#9888; Page d\'inscription affichee</p>'
        + '<p style="color:#8b949e;font-size:11px;">Cette IP a depasse la limite de '+limit+' requetes le '+_esc(r.date)+'. '
        + 'La page proposant de creer un compte ou de se connecter lui a ete presentee.</p></div>';

    _openDrawer('IP bloquee — ' + _esc(r.ip), h);
};

// Drawer: recap IP bloquee (toutes dates confondues)
window._vueShowBlockedIp = function(ip) {
    var items = (D.rateLimited||[]).filter(function(r) { return r.ip === ip; });
    if (!items.length) return;
    var limit = D.rateLimitMax || 20;
    var totalReq = 0, totalDep = 0;
    items.forEach(function(r) { totalReq += r.count; totalDep += r.depassement; });

    var h = '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#ef4444;">'+items.length+'</div><div class="vd-lbl">Jours bloques</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">'+totalReq+'</div><div class="vd-lbl">Total requetes</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#e2e8f0;">+'+totalDep+'</div><div class="vd-lbl">Total depassement</div></div>'
        + '</div>';

    h += '<div class="vd-section"><h4>Historique des blocages</h4>';
    h += '<table><thead><tr><th>Date</th><th style="text-align:center;">Requetes</th><th style="text-align:center;">Depassement</th></tr></thead><tbody>';
    items.forEach(function(r) {
        h += '<tr><td>'+_esc(r.date)+'</td>'
            + '<td style="text-align:center;"><span style="background:#ef444420;color:#ef4444;font-weight:700;padding:3px 10px;border-radius:6px;">'+r.count+'</span></td>'
            + '<td style="text-align:center;color:#f59e0b;font-weight:700;">+'+r.depassement+'</td></tr>';
    });
    h += '</tbody></table></div>';

    // Cross-reference with profils/clubs
    var profils = (D.lastAth||[]).filter(function(v) { return v.ip === ip; });
    var clubs = (D.lastClub||[]).filter(function(v) { return v.ip === ip; });

    if (profils.length) {
        h += '<div class="vd-section"><h4>Profils consultes ('+profils.length+')</h4>';
        h += '<table><thead><tr><th>Date</th><th>Athlete</th></tr></thead><tbody>';
        profils.forEach(function(p) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=profil&id='+p.athlete_id_ext+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(p.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(p.nom||'?')+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }

    if (clubs.length) {
        h += '<div class="vd-section"><h4>Clubs consultes ('+clubs.length+')</h4>';
        h += '<table><thead><tr><th>Date</th><th>Club</th></tr></thead><tbody>';
        clubs.forEach(function(c) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=recherche&club='+encodeURIComponent(c.nom_club||'')+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(c.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(c.nom_club||'?')+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }

    _openDrawer('IP bannie — ' + _esc(ip), h);
};

// === DRAWER ===
function _openDrawer(title, bodyHtml) {
    document.getElementById('vueDrawerTitle').textContent = title;
    document.getElementById('vueDrawerBody').innerHTML = bodyHtml;
    document.getElementById('vueDrawer').classList.add('open');
    document.getElementById('vueOverlay').classList.add('open');
}
window._vueClose = function() {
    document.getElementById('vueDrawer').classList.remove('open');
    document.getElementById('vueOverlay').classList.remove('open');
};

// Drawer: detail athlete
window._vueShowAth = function(idx) {
    var items = D.topAthletes;
    if (_filter) items = items.filter(function(a) {
        return (a.nom||'').toLowerCase().indexOf(_filter) >= 0 || (a.club||'').toLowerCase().indexOf(_filter) >= 0
            || (a.nationalite_athlete||'').toLowerCase().indexOf(_filter) >= 0 || (a.athlete_id_externe+'').indexOf(_filter) >= 0;
    });
    items = _sortItems(items, _sortKey);
    var a = items[idx]; if (!a) return;
    var h = '<div class="vd-section">'
        + '<a href="../index.php?page=profil&id='+a.athlete_id_externe+'" class="vd-link" target="_blank">Voir le profil de '+_esc(a.nom)+' &rarr;</a>'
        + '</div>';
    h += '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">'+a.vues+'</div><div class="vd-lbl">Vues totales</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#55efc4;">'+(a.ips_uniques||0)+'</div><div class="vd-lbl">IPs uniques</div></div>'
        + '</div>';
    h += '<div class="vd-section"><h4>Informations</h4><table>'
        + '<tr><td class="dim">ID</td><td class="mono">'+a.athlete_id_externe+'</td></tr>'
        + '<tr><td class="dim">Sexe</td><td>'+_esc(a.sexe_athlete)+'</td></tr>'
        + '<tr><td class="dim">Categorie</td><td>'+_esc(a.categorie_athlete)+'</td></tr>'
        + '<tr><td class="dim">Nationalite</td><td>'+_esc(a.nationalite_athlete)+'</td></tr>'
        + '<tr><td class="dim">Club</td><td>'+_esc((a.club||'').replace(/\*\s*$/,''))+'</td></tr>'
        + '<tr><td class="dim">Derniere vue</td><td class="time">'+_fmtDateFull(a.derniere_vue)+'</td></tr>'
        + '</table></div>';
    // IPs qui ont vu ce profil
    var ips = (D.lastAth||[]).filter(function(v) { return v.athlete_id_ext == a.athlete_id_externe; });
    h += '<div class="vd-section"><h4>IPs ayant consulte ce profil ('+ips.length+')</h4>';
    if (ips.length) {
        h += '<table><thead><tr><th>Date</th><th>IP</th></tr></thead><tbody>';
        ips.forEach(function(v) {
            h += '<tr><td class="time">'+_fmtDateFull(v.created_at)+'</td><td class="mono">'+_esc(v.ip)+'</td></tr>';
        });
        h += '</tbody></table>';
    } else h += '<p class="dim">Aucune IP enregistree dans les 50 dernieres vues</p>';
    h += '</div>';
    _openDrawer(_esc(a.nom) + ' (#' + a.athlete_id_externe + ')', h);
};

// Drawer: detail club
window._vueShowClub = function(idx) {
    var items = D.topClubs;
    if (_filter) items = items.filter(function(c) {
        return (c.nom_club||'').toLowerCase().indexOf(_filter) >= 0 || (c.id_club+'').indexOf(_filter) >= 0;
    });
    items = _sortItems(items, _sortKey === 'nom' ? 'nom_club' : _sortKey);
    var c = items[idx]; if (!c) return;
    var h = '<div class="vd-section">'
        + '<a href="../index.php?page=recherche&club='+encodeURIComponent(c.nom_club)+'" class="vd-link" target="_blank">Voir le club '+_esc(c.nom_club)+' &rarr;</a>'
        + '</div>';
    h += '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">'+c.vues+'</div><div class="vd-lbl">Vues totales</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#55efc4;">'+(c.ips_uniques||0)+'</div><div class="vd-lbl">IPs uniques</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#8b5cf6;">'+(c.nb_athletes||0)+'</div><div class="vd-lbl">Athletes</div></div>'
        + '</div>';
    h += '<div class="vd-section"><h4>Informations</h4><table>'
        + '<tr><td class="dim">ID Club</td><td class="mono">'+c.id_club+'</td></tr>'
        + '<tr><td class="dim">Derniere vue</td><td class="time">'+_fmtDateFull(c.derniere_vue)+'</td></tr>'
        + '</table></div>';
    var ips = (D.lastClub||[]).filter(function(v) { return v.club_id == c.id_club; });
    h += '<div class="vd-section"><h4>IPs ayant consulte ce club ('+ips.length+')</h4>';
    if (ips.length) {
        h += '<table><thead><tr><th>Date</th><th>IP</th></tr></thead><tbody>';
        ips.forEach(function(v) {
            h += '<tr><td class="time">'+_fmtDateFull(v.created_at)+'</td><td class="mono">'+_esc(v.ip)+'</td></tr>';
        });
        h += '</tbody></table>';
    } else h += '<p class="dim">Aucune IP dans les 50 dernieres vues</p>';
    h += '</div>';
    _openDrawer(_esc(c.nom_club) + ' (#' + c.id_club + ')', h);
};

// Drawer: detail IP
window._vueShowIp = function(idx) {
    var items = D.topIps;
    if (_filter) items = items.filter(function(ip) { return ip.ip.indexOf(_filter) >= 0; });
    var ip = items[idx]; if (!ip) return;
    var total = (parseInt(ip.nb_profils)||0) + (parseInt(ip.nb_clubs)||0);
    var h = '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">'+(ip.nb_profils||0)+'</div><div class="vd-lbl">Profils</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#8b5cf6;">'+(ip.nb_clubs||0)+'</div><div class="vd-lbl">Clubs</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#55efc4;">'+total+'</div><div class="vd-lbl">Total</div></div>'
        + '</div>';
    h += '<div class="vd-section"><h4>Periode</h4><table>'
        + '<tr><td class="dim">Premiere vue</td><td class="time">'+_fmtDateFull(ip.first_vue)+'</td></tr>'
        + '<tr><td class="dim">Derniere vue</td><td class="time">'+_fmtDateFull(ip.last_vue)+'</td></tr>'
        + '</table></div>';
    // Profils consultes
    var profils = ip.derniers_profils || [];
    h += '<div class="vd-section"><h4>Profils consultes ('+profils.length+')</h4>';
    if (profils.length) {
        h += '<table><thead><tr><th>Date</th><th>Athlete</th><th>ID</th></tr></thead><tbody>';
        profils.forEach(function(p) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=profil&id='+p.athlete_id_ext+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(p.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(p.nom||'?')+'</td>'
                + '<td class="mono">'+p.athlete_id_ext+'</td></tr>';
        });
        h += '</tbody></table>';
    } else h += '<p class="dim">Aucun profil</p>';
    h += '</div>';
    // Clubs consultes
    var clubs = ip.derniers_clubs || [];
    h += '<div class="vd-section"><h4>Clubs consultes ('+clubs.length+')</h4>';
    if (clubs.length) {
        h += '<table><thead><tr><th>Date</th><th>Club</th><th>ID</th></tr></thead><tbody>';
        clubs.forEach(function(c) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=recherche&club='+encodeURIComponent(c.nom_club||'')+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(c.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(c.nom_club||'?')+'</td>'
                + '<td class="mono">'+c.club_id+'</td></tr>';
        });
        h += '</tbody></table>';
    } else h += '<p class="dim">Aucun club</p>';
    h += '</div>';
    _openDrawer('IP ' + _esc(ip.ip), h);
};

// Drawer: detail jour
window._vueShowDay = function(dateStr) {
    var athDay = (D.lastAth||[]).filter(function(v) { return (v.created_at||'').substring(0,10) === dateStr; });
    var clubDay = (D.lastClub||[]).filter(function(v) { return (v.created_at||'').substring(0,10) === dateStr; });
    var h = '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">'+athDay.length+'</div><div class="vd-lbl">Profils</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#8b5cf6;">'+clubDay.length+'</div><div class="vd-lbl">Clubs</div></div>'
        + '</div>';
    if (athDay.length) {
        h += '<div class="vd-section"><h4>Profils consultes</h4><table><thead><tr><th>Heure</th><th>IP</th><th>Athlete</th></tr></thead><tbody>';
        athDay.forEach(function(v) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=profil&id='+v.athlete_id_ext+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(v.created_at)+'</td>'
                + '<td class="mono">'+_esc(v.ip)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(v.nom||'?')+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }
    if (clubDay.length) {
        h += '<div class="vd-section"><h4>Clubs consultes</h4><table><thead><tr><th>Heure</th><th>IP</th><th>Club</th></tr></thead><tbody>';
        clubDay.forEach(function(v) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=recherche&club='+encodeURIComponent(v.nom_club||'')+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(v.created_at)+'</td>'
                + '<td class="mono">'+_esc(v.ip)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(v.nom_club||'?')+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }
    if (!athDay.length && !clubDay.length) h += '<p class="dim" style="text-align:center;padding:20px;">Aucune vue dans les 50 dernieres enregistrees pour ce jour</p>';
    _openDrawer('Vues du ' + dateStr, h);
};

// === INIT ===
_vueRenderChart();
_vueRenderTab();
})();
</script>

<?php endif; /* hasVuesTables */ ?>

<!-- ============================================================ -->
<!-- SECTION 13 : IPs RATE LIMITEES (bloquees) -->
<!-- ============================================================ -->
<div class="section"><h2 style="color:#ef4444;font-size:16px;border-color:#ef444440;">&#9888; IPs Bloquees &amp; Bannies</h2></div>

<div class="grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="card" style="border-color:#ef444440;"><div class="num" style="color:#ef4444;"><?= count($bannedIps) ?></div><div class="label">IPs bannies</div></div>
    <div class="card" style="border-color:#f59e0b40;"><div class="num warn"><?= count($rateLimitedIps) ?></div><div class="label">Blocages (5 jours)</div></div>
    <div class="card" style="border-color:#8b5cf640;"><div class="num info"><?= count(array_unique(array_column($rateLimitedIps, 'ip'))) ?></div><div class="label">IPs uniques bloquees</div></div>
    <div class="card" style="border-color:#6c5ce740;"><div class="num" style="color:#8b949e;"><?= $rateLimitDailyLimit ?></div><div class="label">Limite / jour</div></div>
</div>

<!-- IPs bannies definitivement -->
<div class="section">
    <h4 style="color:#ef4444;font-size:14px;margin-bottom:8px;">&#128274; IPs bannies definitivement (<?= count($bannedIps) ?>)</h4>
    <p style="color:#8b949e;font-size:11px;margin-bottom:10px;">Ces IPs sont bloquees de maniere permanente — elles doivent s'inscrire pour acceder au site.</p>
    <?php if (empty($bannedIps)): ?>
    <p style="text-align:center;color:#5a6580;padding:20px;font-size:13px;">Aucune IP bannie pour le moment.</p>
    <?php else: ?>
    <div style="max-height:400px;overflow-y:auto;">
    <table style="font-size:12px;">
        <thead><tr><th>IP</th><th>Date du ban</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($bannedIps as $bIp => $bDate): ?>
        <tr class="vue-row ban-row">
            <td class="mono" style="font-weight:700;"><?= htmlspecialchars($bIp) ?></td>
            <td><span style="background:#ef444420;color:#ef4444;font-weight:600;padding:3px 10px;border-radius:6px;font-size:11px;"><?= htmlspecialchars($bDate) ?></span></td>
            <td><button onclick="_unbanIp('<?= htmlspecialchars($bIp, ENT_QUOTES) ?>',this)" style="background:#10b98120;border:1px solid #10b98140;color:#10b981;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Debannir</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- Historique blocages journaliers -->
<?php if (!empty($rateLimitedIps)): ?>
<div class="section">
    <h4 style="color:#f59e0b;font-size:14px;margin-bottom:8px;">Historique blocages (5 derniers jours)</h4>
    <p style="color:#8b949e;font-size:11px;margin-bottom:10px;">IPs ayant depasse la limite de <?= $rateLimitDailyLimit ?> requetes/jour.</p>
    <input type="text" class="vue-search" id="rlSearch" placeholder="&#128269; Rechercher par IP ou date..." oninput="_rlFilter(this.value)" style="max-width:400px;margin-bottom:10px;">
    <div style="max-height:500px;overflow-y:auto;">
        <table id="rlTable" style="font-size:13px;">
            <thead><tr>
                <th>Date</th>
                <th>Adresse IP</th>
                <th style="text-align:center;">Requetes</th>
                <th style="text-align:center;">Depassement</th>
                <th style="text-align:center;">Severite</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rateLimitedIps as $rl):
                $dep = $rl['depassement'];
                if ($dep > 20) { $sevLabel = 'Abusif'; $sevColor = '#ef4444'; $sevBg = '#ef444420'; }
                elseif ($dep > 5) { $sevLabel = 'Excessif'; $sevColor = '#f59e0b'; $sevBg = '#f59e0b20'; }
                else { $sevLabel = 'Limite'; $sevColor = '#eab308'; $sevBg = '#eab30820'; }
            ?>
            <tr class="vue-row rl-row" data-ip="<?= htmlspecialchars($rl['ip']) ?>" data-date="<?= $rl['date'] ?>">
                <td class="time" style="font-size:12px;"><?= $rl['date'] ?></td>
                <td class="mono" style="font-weight:700;font-size:13px;"><?= htmlspecialchars($rl['ip']) ?></td>
                <td style="text-align:center;"><span style="background:#ef444420;color:#ef4444;font-weight:800;font-size:16px;padding:4px 12px;border-radius:8px;"><?= $rl['count'] ?></span></td>
                <td style="text-align:center;"><span style="color:#f59e0b;font-weight:700;">+<?= $dep ?></span></td>
                <td style="text-align:center;"><span style="background:<?= $sevBg ?>;color:<?= $sevColor ?>;font-weight:700;font-size:11px;padding:3px 10px;border-radius:6px;"><?= $sevLabel ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; /* rateLimitedIps */ ?>

<script>
function _unbanIp(ip, btn) {
    if (!confirm('Debannir ' + ip + ' ?')) return;
    fetch('/api/contact.php?unban_ip=' + encodeURIComponent(ip)).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            var row = btn.closest('tr');
            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(function() { row.remove(); }, 300);
        }
    });
}
function _rlFilter(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.rl-row').forEach(function(row) {
        var ip = row.getAttribute('data-ip') || '';
        var date = row.getAttribute('data-date') || '';
        row.style.display = (!q || ip.indexOf(q) >= 0 || date.indexOf(q) >= 0) ? '' : 'none';
    });
}
</script>

<!-- ============================================================ -->
<!-- SECTION 14 : MESSAGES DE CONTACT -->
<!-- ============================================================ -->

<div id="contactSection"></div>
<div class="section"><h2 style="color:#6c5ce7;font-size:16px;border-color:#6c5ce740;">&#9993; Messages de contact<?php if ($unreadCount): ?> <span style="background:#ef4444;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;margin-left:6px;"><?= $unreadCount ?> non lu<?= $unreadCount > 1 ? 's' : '' ?></span><?php endif; ?></h2></div>

<div class="grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="card" style="border-color:#6c5ce740;"><div class="num" style="color:#6c5ce7;"><?= count($contactMessages) ?></div><div class="label">Total messages</div></div>
    <div class="card" style="border-color:#ef444440;"><div class="num" style="color:#ef4444;"><?= $unreadCount ?></div><div class="label">Non lus</div></div>
    <div class="card" style="border-color:#10b98140;"><div class="num" style="color:#10b981;"><?= count($contactMessages) - $unreadCount ?></div><div class="label">Lus</div></div>
</div>

<div class="section">
    <?php if (empty($contactMessages)): ?>
    <p style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucun message de contact pour le moment.</p>
    <?php else: ?>

    <?php if ($unreadCount > 1): ?>
    <div style="margin-bottom:12px;text-align:right;">
        <button id="markAllReadBtn" onclick="_markAllRead()" style="background:#6c5ce720;border:1px solid #6c5ce740;color:#a29bfe;font-size:12px;font-weight:600;padding:6px 16px;border-radius:8px;cursor:pointer;">Tout marquer comme lu (<?= $unreadCount ?>)</button>
    </div>
    <?php endif; ?>

    <?php
    $unread = array_filter($contactMessages, function($m) { return !$m['lu']; });
    $read = array_filter($contactMessages, function($m) { return $m['lu']; });
    ?>

    <?php if (!empty($unread)): ?>
    <h4 style="color:#ef4444;font-size:13px;margin-bottom:10px;">Non lus (<?= count($unread) ?>)</h4>
    <?php foreach ($unread as $cm): ?>
        <div class="contact-card" data-id="<?= $cm['id_msg'] ?>" style="background:#1a1f2e;border:1px solid #6c5ce740;border-left:3px solid #6c5ce7;border-radius:10px;padding:16px;margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div>
                    <span style="color:#e2e8f0;font-weight:700;font-size:14px;"><?= htmlspecialchars($cm['nom'] ?: 'Anonyme') ?></span>
                    <?php if ($cm['email']): ?><span style="color:#8b949e;font-size:12px;margin-left:8px;"><?= htmlspecialchars($cm['email']) ?></span><?php endif; ?>
                    <span class="badge-new" style="background:#6c5ce7;color:#fff;font-size:10px;padding:1px 6px;border-radius:4px;margin-left:6px;">NOUVEAU</span>
                </div>
                <span style="color:#5a6580;font-size:11px;"><?= $cm['created_at'] ?></span>
            </div>
            <p style="color:#c9d1d9;font-size:13px;line-height:1.5;margin:0;white-space:pre-wrap;"><?= htmlspecialchars($cm['message']) ?></p>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
                <span class="mono" style="color:#5a6580;font-size:11px;"><?= htmlspecialchars($cm['ip']) ?></span>
                <button class="btn-mark" onclick="_markRead(<?= $cm['id_msg'] ?>,this)" style="background:#1e2a3a;border:1px solid #2d3a4a;color:#8b949e;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Marquer lu</button>
                <button onclick="_deleteMsg(<?= $cm['id_msg'] ?>,this)" style="background:#ef444420;border:1px solid #ef444440;color:#ef4444;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Supprimer</button>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($read)): ?>
    <h4 style="color:#10b981;font-size:13px;margin:20px 0 10px;">Lus (<?= count($read) ?>)</h4>
    <?php foreach ($read as $cm): ?>
        <div class="contact-card" data-id="<?= $cm['id_msg'] ?>" style="background:#161b22;border:1px solid #1e2a3a;border-radius:10px;padding:16px;margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div>
                    <span style="color:#e2e8f0;font-weight:700;font-size:14px;"><?= htmlspecialchars($cm['nom'] ?: 'Anonyme') ?></span>
                    <?php if ($cm['email']): ?><span style="color:#8b949e;font-size:12px;margin-left:8px;"><?= htmlspecialchars($cm['email']) ?></span><?php endif; ?>
                </div>
                <span style="color:#5a6580;font-size:11px;"><?= $cm['created_at'] ?></span>
            </div>
            <p style="color:#c9d1d9;font-size:13px;line-height:1.5;margin:0;white-space:pre-wrap;"><?= htmlspecialchars($cm['message']) ?></p>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
                <span class="mono" style="color:#5a6580;font-size:11px;"><?= htmlspecialchars($cm['ip']) ?></span>
                <button onclick="_deleteMsg(<?= $cm['id_msg'] ?>,this)" style="background:#ef444420;border:1px solid #ef444440;color:#ef4444;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Supprimer</button>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
function _deleteMsg(id, btn) {
    if (!confirm('Supprimer ce message ?')) return;
    fetch('/api/contact.php?delete=' + id).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            var card = btn.closest('.contact-card');
            card.style.transition = 'opacity 0.3s';
            card.style.opacity = '0';
            setTimeout(function() { card.remove(); }, 300);
        }
    });
}
function _markRead(id, btn) {
    fetch('/api/contact.php?mark_read=' + id).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            var card = btn.closest('.contact-card');
            card.style.borderColor = '#1e2a3a';
            card.style.borderLeft = '1px solid #1e2a3a';
            card.style.background = '#161b22';
            var badge = card.querySelector('.badge-new');
            if (badge) badge.remove();
            btn.remove();
        }
    });
}
function _markAllRead() {
    var cards = document.querySelectorAll('.contact-card .btn-mark');
    var ids = [];
    cards.forEach(function(btn) {
        var card = btn.closest('.contact-card');
        ids.push(card.getAttribute('data-id'));
    });
    var done = 0;
    ids.forEach(function(id) {
        fetch('/api/contact.php?mark_read=' + id).then(function(r) { return r.json(); }).then(function() {
            done++;
            if (done >= ids.length) location.reload();
        });
    });
}
</script>

<!-- ============================================================ -->
<!-- SECTION 15 : SEARCH TRACKING — INTERACTIF -->
<!-- ============================================================ -->
<div id="stSection"></div>
<script>
var ST_DATA = <?= json_encode([
    'totals' => ['total'=>$stTotal,'today'=>$stToday,'week'=>$stWeek,'month'=>$stMonth,'uniqueIps'=>$stUniqueIps,'successRate'=>$stSuccessRate,'lastSearch'=>$stLastSearch],
    'byType' => $stByType,
    'bySource' => $stBySource,
    'parJour' => $stParJour,
    'parHeure' => $stParHeure,
    'topQueries' => $stTopQueries,
    'allAthletes' => $stAllAthletes,
    'allClubs' => $stAllClubs,
    'topEntities' => $stTopEntities,
    'topIps' => $stTopIps,
    'ignoredIps' => $stIgnoredIps,
    'myIp' => $stMyIp
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="section"><h2 style="color:#34d399;font-size:16px;border-color:#34d39940;">&#128270; Search Tracking</h2></div>

<!-- KPI Cards -->
<div class="grid">
    <div class="card" style="border-color:#34d39940;"><div class="num" style="color:#34d399;"><?= number_format($stTotal) ?></div><div class="label">Total recherches</div></div>
    <div class="card" style="border-color:#a29bfe40;"><div class="num" style="color:#a29bfe;"><?= number_format($stToday) ?></div><div class="label">Aujourd'hui</div></div>
    <div class="card" style="border-color:#f59e0b40;"><div class="num" style="color:#f59e0b;"><?= number_format($stWeek) ?></div><div class="label">7 jours</div></div>
    <div class="card" style="border-color:#3b82f640;"><div class="num" style="color:#60a5fa;"><?= number_format($stMonth) ?></div><div class="label">30 jours</div></div>
    <div class="card" style="border-color:#10b98140;"><div class="num" style="color:#10b981;"><?= number_format($stUniqueIps) ?></div><div class="label">IPs uniques</div></div>
    <div class="card" style="border-color:#55efc440;"><div class="num" style="color:#55efc4;"><?= $stSuccessRate ?>%</div><div class="label">Taux succes</div></div>
    <div class="card" style="border-color:#ef444440;"><div class="num" style="color:#ef4444;"><?= count($stIgnoredIps) ?></div><div class="label">IPs ignorees</div></div>
    <div class="card" style="border-color:#ec489940;"><div class="num" style="color:#ec4899;font-size:16px;"><?= $stLastSearch ? date('H:i:s', strtotime($stLastSearch)) : '-' ?></div><div class="label">Derniere recherche</div></div>
</div>

<!-- Par type + source (mini cards) -->
<div style="padding:0 24px;display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
    <?php
    $typeColors = ['athlete'=>'#f59e0b','club'=>'#8b5cf6','epreuve'=>'#3b82f6','ville'=>'#10b981','general'=>'#6366f1'];
    $sourceColors = ['live_search'=>'#34d399','page_view'=>'#a29bfe','panel_open'=>'#f59e0b'];
    foreach ($stByType as $bt): $tc = $typeColors[$bt['search_type']] ?? '#5a6580'; ?>
    <span style="background:<?=$tc?>15;border:1px solid <?=$tc?>40;color:<?=$tc?>;padding:4px 12px;border-radius:8px;font-size:12px;font-weight:700;"><?=$bt['search_type']?> <b><?=number_format((int)$bt['c'])?></b> <span style="opacity:.6;font-weight:400;">(<?=$bt['ips']?> IPs)</span></span>
    <?php endforeach; ?>
    <span style="color:#2d3a4a;">|</span>
    <?php foreach ($stBySource as $bs): $sc = $sourceColors[$bs['source']] ?? '#5a6580'; ?>
    <span style="background:<?=$sc?>15;border:1px solid <?=$sc?>40;color:<?=$sc?>;padding:4px 12px;border-radius:8px;font-size:12px;font-weight:700;"><?=$bs['source']?> <b><?=number_format((int)$bs['c'])?></b></span>
    <?php endforeach; ?>
</div>

<!-- Chart 14 jours -->
<div class="section">
    <h2>Historique 14 jours</h2>
    <div style="height:200px;position:relative;"><canvas id="stChart14"></canvas></div>
</div>

<!-- Onglets interactifs -->
<div class="vue-tabs" id="stTabs">
    <div class="vue-tab active" onclick="_stTab('queries')">Recherches</div>
    <div class="vue-tab" onclick="_stTab('athletes')" style="color:#f59e0b;">Athletes (<?=count($stAllAthletes)?>)</div>
    <div class="vue-tab" onclick="_stTab('clubs')" style="color:#8b5cf6;">Clubs (<?=count($stAllClubs)?>)</div>
    <div class="vue-tab" onclick="_stTab('entities')">Entites</div>
    <div class="vue-tab" onclick="_stTab('ips')">IPs (<?=count($stTopIps)?>)</div>
    <div class="vue-tab" onclick="_stTab('hourly')">Horaire</div>
    <div class="vue-tab" onclick="_stTab('sources')">Sources</div>
</div>
<div class="vue-tab-body" id="stTabBody" style="min-height:300px;"></div>

<!-- Reset + IPs ignorees -->
<div style="padding:16px 24px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <span style="color:#8b949e;font-size:12px;font-weight:700;margin-right:8px;">RESET :</span>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer TOUT le tracking athletes ?')"><input type="hidden" name="st_action" value="reset_tracking"><input type="hidden" name="reset_type" value="athlete"><button class="btn" style="border-color:#f59e0b40;color:#f59e0b;cursor:pointer;">Reset athletes</button></form>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer TOUT le tracking clubs ?')"><input type="hidden" name="st_action" value="reset_tracking"><input type="hidden" name="reset_type" value="club"><button class="btn" style="border-color:#8b5cf640;color:#8b5cf6;cursor:pointer;">Reset clubs</button></form>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer TOUT le tracking ? Cette action est irreversible !')"><input type="hidden" name="st_action" value="reset_tracking"><input type="hidden" name="reset_type" value="all"><button class="btn" style="border-color:#ef444440;color:#ef4444;cursor:pointer;">Reset TOUT</button></form>
</div>

<!-- IPs ignorees -->
<div style="padding:0 24px 16px;">
    <h3 style="color:#ef4444;font-size:13px;margin-bottom:10px;">IPs ignorees (exclues des stats)</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
        <form method="POST" style="display:inline-flex;gap:6px;align-items:center;">
            <input type="hidden" name="st_action" value="ignore_ip">
            <input type="text" name="ip" placeholder="IP a ignorer" style="background:#161b22;border:1px solid #1e2a3a;color:#c9d1d9;padding:6px 12px;border-radius:8px;font-size:12px;width:160px;">
            <input type="text" name="label" placeholder="Label (ex: Mon IP)" style="background:#161b22;border:1px solid #1e2a3a;color:#c9d1d9;padding:6px 12px;border-radius:8px;font-size:12px;width:140px;">
            <button class="btn" style="cursor:pointer;border-color:#ef444440;color:#ef4444;">Ignorer</button>
        </form>
        <?php if (!isset($stIgnoredIps[$stMyIp])): ?>
        <form method="POST" style="display:inline;"><input type="hidden" name="st_action" value="ignore_ip"><input type="hidden" name="ip" value="<?=htmlspecialchars($stMyIp)?>"><input type="hidden" name="label" value="Mon IP"><button class="btn" style="cursor:pointer;border-color:#f59e0b40;color:#f59e0b;">Ignorer mon IP (<?=htmlspecialchars($stMyIp)?>)</button></form>
        <?php endif; ?>
    </div>
    <?php if (!empty($stIgnoredIps)): ?>
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <?php foreach ($stIgnoredIps as $igIp => $igData): ?>
        <span style="background:#ef444415;border:1px solid #ef444430;color:#ef4444;padding:4px 10px;border-radius:8px;font-size:12px;display:inline-flex;align-items:center;gap:6px;">
            <code style="color:#f87171;"><?=htmlspecialchars($igIp)?></code>
            <?php if (!empty($igData['label'])): ?><span style="color:#8b949e;">(<?=htmlspecialchars($igData['label'])?>)</span><?php endif; ?>
            <form method="POST" style="display:inline;margin:0;"><input type="hidden" name="st_action" value="unignore_ip"><input type="hidden" name="ip" value="<?=htmlspecialchars($igIp)?>"><button style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:14px;padding:0 2px;" title="Reactiver">&times;</button></form>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function(){
var D = ST_DATA;
var _stCurTab = 'queries', _stSort = 'c', _stDir = 'desc', _stFilter = '';
var _stTypeColors = {athlete:'#f59e0b',club:'#8b5cf6',epreuve:'#3b82f6',ville:'#10b981',general:'#6366f1'};
var _stSourceColors = {live_search:'#34d399',page_view:'#a29bfe',panel_open:'#f59e0b'};

function _esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
function _fmtDt(s){if(!s)return'-';var d=new Date(s.replace(' ','T'));return (d.getDate()<10?'0':'')+d.getDate()+'/'+(d.getMonth()<9?'0':'')+(d.getMonth()+1)+' '+(d.getHours()<10?'0':'')+d.getHours()+':'+(d.getMinutes()<10?'0':'')+d.getMinutes()+':'+(d.getSeconds()<10?'0':'')+d.getSeconds();}
function _badge(text,color){return '<span style="background:'+color+'20;color:'+color+';padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;">'+_esc(text)+'</span>';}

// Chart 14 jours
function _stRenderChart(){
    var pj=D.parJour;if(!pj.length)return;
    var labels=pj.map(function(d){return d.d.substring(5);});
    new Chart(document.getElementById('stChart14'),{
        type:'bar',data:{labels:labels,datasets:[
            {label:'Athletes',data:pj.map(function(d){return parseInt(d.athlete)||0;}),backgroundColor:'#f59e0b',borderRadius:3},
            {label:'Clubs',data:pj.map(function(d){return parseInt(d.club)||0;}),backgroundColor:'#8b5cf6',borderRadius:3},
            {label:'Epreuves',data:pj.map(function(d){return parseInt(d.epreuve)||0;}),backgroundColor:'#3b82f6',borderRadius:3},
            {label:'Villes',data:pj.map(function(d){return parseInt(d.ville)||0;}),backgroundColor:'#10b981',borderRadius:3},
            {label:'General',data:pj.map(function(d){return parseInt(d.general)||0;}),backgroundColor:'#6366f1',borderRadius:3}
        ]},options:{responsive:true,maintainAspectRatio:false,scales:{x:{stacked:true,grid:{color:'#1e2a3a'},ticks:{color:'#5a6580',font:{size:10}}},y:{stacked:true,grid:{color:'#1e2a3a'},ticks:{color:'#5a6580'},beginAtZero:true}},plugins:{legend:{labels:{color:'#8b949e',boxWidth:12}}}}
    });
}

// Tabs
window._stTab=function(tab){
    _stCurTab=tab;_stFilter='';_stSort=tab==='athletes'||tab==='clubs'?'created_at':'c';_stDir='desc';
    document.querySelectorAll('#stTabs .vue-tab').forEach(function(t){t.classList.remove('active');});
    var tabs=['queries','athletes','clubs','entities','ips','hourly','sources'];
    var idx=tabs.indexOf(tab);if(idx>=0)document.querySelectorAll('#stTabs .vue-tab')[idx].classList.add('active');
    _stRender();
};
function _stSortH(key,label){
    var cls='vue-sort'+(_stSort===key?' '+_stDir:'');
    return '<th class="'+cls+'" onclick="_stSortBy(\''+key+'\')">'+label+'</th>';
}
window._stSortBy=function(key){
    if(_stSort===key)_stDir=_stDir==='desc'?'asc':'desc';
    else{_stSort=key;_stDir='desc';}
    _stRender();
};
window._stFilterFn=function(q){_stFilter=q.toLowerCase();_stRender();};

function _stSortArr(arr,key){
    return arr.slice().sort(function(a,b){
        var va=key==='query_text'||key==='entity_name'||key==='ip'||key==='nom'||key==='source'?(a[key]||'').toLowerCase():(parseFloat(a[key])||0);
        var vb=key==='query_text'||key==='entity_name'||key==='ip'||key==='nom'||key==='source'?(b[key]||'').toLowerCase():(parseFloat(b[key])||0);
        if(typeof va==='string')return _stDir==='asc'?va.localeCompare(vb):vb.localeCompare(va);
        return _stDir==='asc'?va-vb:vb-va;
    });
}

function _stRender(){
    var el=document.getElementById('stTabBody');
    if(_stCurTab==='queries')_stRenderQueries(el);
    else if(_stCurTab==='athletes')_stRenderAthletes(el);
    else if(_stCurTab==='clubs')_stRenderClubs(el);
    else if(_stCurTab==='entities')_stRenderEntities(el);
    else if(_stCurTab==='ips')_stRenderIps(el);
    else if(_stCurTab==='hourly')_stRenderHourly(el);
    else if(_stCurTab==='sources')_stRenderSources(el);
}

// TAB: Recherches
function _stRenderQueries(el){
    var items=D.topQueries;
    if(_stFilter)items=items.filter(function(q){return(q.query_text||'').toLowerCase().indexOf(_stFilter)>=0||(q.search_type||'').toLowerCase().indexOf(_stFilter)>=0;});
    items=_stSortArr(items,_stSort);
    var h='<div style="padding:14px;"><input class="vue-search" placeholder="Filtrer les recherches..." oninput="_stFilterFn(this.value)"></div>';
    h+='<div style="overflow-x:auto;max-height:600px;overflow-y:auto;"><table style="width:100%;"><thead><tr>';
    h+=_stSortH('query_text','Recherche')+_stSortH('search_type','Type')+'<th>Source</th>'+_stSortH('c','Total')+_stSortH('ips','IPs')+_stSortH('avg_results','Moy. res.')+_stSortH('last_at','Derniere');
    h+='</tr></thead><tbody>';
    items.forEach(function(q,i){
        var tc=_stTypeColors[q.search_type]||'#5a6580';
        h+='<tr class="vue-row"><td style="color:#e2e8f0;font-weight:600;">'+_esc(q.query_text)+'</td>';
        h+='<td>'+_badge(q.search_type,tc)+'</td>';
        h+='<td style="color:#8b949e;font-size:11px;">'+(q.source||'-')+'</td>';
        h+='<td style="text-align:right;"><span style="background:#f59e0b20;color:#f59e0b;padding:2px 10px;border-radius:6px;font-weight:800;">'+q.c+'</span></td>';
        h+='<td style="text-align:right;color:#34d399;font-weight:600;">'+q.ips+'</td>';
        h+='<td style="text-align:right;color:#8b949e;">'+((q.avg_results||0))+'</td>';
        h+='<td class="time">'+_fmtDt(q.last_at)+'</td></tr>';
    });
    if(!items.length)h+='<tr><td colspan="7" style="text-align:center;color:#5a6580;padding:30px;">Aucune recherche</td></tr>';
    h+='</tbody></table></div>';
    el.innerHTML=h;
}

// TAB: Athletes
function _stRenderAthletes(el){
    var items=D.allAthletes;
    if(_stFilter)items=items.filter(function(a){return(a.entity_name||a.query_text||'').toLowerCase().indexOf(_stFilter)>=0||(a.ip||'').toLowerCase().indexOf(_stFilter)>=0;});
    items=_stSortArr(items,_stSort);
    var h='<div style="padding:14px;display:flex;gap:10px;align-items:center;"><input class="vue-search" style="flex:1;" placeholder="Filtrer athletes... ('+D.allAthletes.length+' total)" oninput="_stFilterFn(this.value)"><span style="color:#f59e0b;font-size:13px;font-weight:700;">'+items.length+' resultats</span></div>';
    h+='<div style="overflow-x:auto;max-height:700px;overflow-y:auto;"><table style="width:100%;"><thead><tr>';
    h+='<th>#</th>'+_stSortH('entity_name','Nom')+_stSortH('entity_id','ID')+'<th>Recherche</th>'+_stSortH('source','Source')+'<th>Page</th>'+_stSortH('ip','IP')+_stSortH('result_count','Res.')+_stSortH('created_at','Heure');
    h+='</tr></thead><tbody>';
    items.forEach(function(a,i){
        var nom=a.entity_name||a.query_text||'-';
        var sc=_stSourceColors[a.source]||'#5a6580';
        h+='<tr class="vue-row">';
        h+='<td style="color:#484f58;">'+(i+1)+'</td>';
        h+='<td style="color:#e2e8f0;font-weight:600;">'+_esc(nom)+'</td>';
        h+='<td class="mono">'+(a.entity_id||'-')+'</td>';
        h+='<td style="color:#8b949e;font-size:11px;max-width:120px;overflow:hidden;text-overflow:ellipsis;">'+_esc(a.query_text||'-')+'</td>';
        h+='<td>'+_badge(a.source||'-',sc)+'</td>';
        h+='<td style="color:#8b949e;font-size:11px;">'+(a.page||'-')+'</td>';
        h+='<td class="mono" style="font-size:11px;">'+_esc(a.ip)+'</td>';
        h+='<td style="text-align:right;color:#34d399;">'+(a.result_count||0)+'</td>';
        h+='<td class="time">'+_fmtDt(a.created_at)+'</td>';
        h+='</tr>';
    });
    if(!items.length)h+='<tr><td colspan="9" style="text-align:center;color:#5a6580;padding:30px;">Aucun athlete</td></tr>';
    h+='</tbody></table></div>';
    el.innerHTML=h;
}

// TAB: Clubs
function _stRenderClubs(el){
    var items=D.allClubs;
    if(_stFilter)items=items.filter(function(a){return(a.entity_name||a.query_text||'').toLowerCase().indexOf(_stFilter)>=0||(a.ip||'').toLowerCase().indexOf(_stFilter)>=0;});
    items=_stSortArr(items,_stSort);
    var h='<div style="padding:14px;display:flex;gap:10px;align-items:center;"><input class="vue-search" style="flex:1;" placeholder="Filtrer clubs... ('+D.allClubs.length+' total)" oninput="_stFilterFn(this.value)"><span style="color:#8b5cf6;font-size:13px;font-weight:700;">'+items.length+' resultats</span></div>';
    h+='<div style="overflow-x:auto;max-height:700px;overflow-y:auto;"><table style="width:100%;"><thead><tr>';
    h+='<th>#</th>'+_stSortH('entity_name','Nom')+_stSortH('entity_id','ID')+'<th>Recherche</th>'+_stSortH('source','Source')+'<th>Page</th>'+_stSortH('ip','IP')+_stSortH('result_count','Res.')+_stSortH('created_at','Heure');
    h+='</tr></thead><tbody>';
    items.forEach(function(a,i){
        var nom=a.entity_name||a.query_text||'-';
        var sc=_stSourceColors[a.source]||'#5a6580';
        h+='<tr class="vue-row">';
        h+='<td style="color:#484f58;">'+(i+1)+'</td>';
        h+='<td style="color:#e2e8f0;font-weight:600;">'+_esc(nom)+'</td>';
        h+='<td class="mono">'+(a.entity_id||'-')+'</td>';
        h+='<td style="color:#8b949e;font-size:11px;max-width:120px;overflow:hidden;text-overflow:ellipsis;">'+_esc(a.query_text||'-')+'</td>';
        h+='<td>'+_badge(a.source||'-',sc)+'</td>';
        h+='<td style="color:#8b949e;font-size:11px;">'+(a.page||'-')+'</td>';
        h+='<td class="mono" style="font-size:11px;">'+_esc(a.ip)+'</td>';
        h+='<td style="text-align:right;color:#34d399;">'+(a.result_count||0)+'</td>';
        h+='<td class="time">'+_fmtDt(a.created_at)+'</td>';
        h+='</tr>';
    });
    if(!items.length)h+='<tr><td colspan="9" style="text-align:center;color:#5a6580;padding:30px;">Aucun club</td></tr>';
    h+='</tbody></table></div>';
    el.innerHTML=h;
}

// TAB: Entites (epreuves + villes)
function _stRenderEntities(el){
    var items=D.topEntities;
    if(_stFilter)items=items.filter(function(e){return(e.entity_name||'').toLowerCase().indexOf(_stFilter)>=0;});
    items=_stSortArr(items,_stSort);
    var h='<div style="padding:14px;"><input class="vue-search" placeholder="Filtrer entites..." oninput="_stFilterFn(this.value)"></div>';
    h+='<div style="overflow-x:auto;max-height:600px;overflow-y:auto;"><table style="width:100%;"><thead><tr>';
    h+='<th>#</th>'+_stSortH('entity_name','Nom')+_stSortH('search_type','Type')+_stSortH('c','Total')+_stSortH('ips','IPs');
    h+='</tr></thead><tbody>';
    items.forEach(function(e,i){
        var tc=_stTypeColors[e.search_type]||'#5a6580';
        h+='<tr class="vue-row"><td style="color:#484f58;">'+(i+1)+'</td>';
        h+='<td style="color:#e2e8f0;font-weight:600;">'+_esc(e.entity_name)+'</td>';
        h+='<td>'+_badge(e.search_type,tc)+'</td>';
        h+='<td style="text-align:right;"><span style="background:#f59e0b20;color:#f59e0b;padding:2px 10px;border-radius:6px;font-weight:800;">'+e.c+'</span></td>';
        h+='<td style="text-align:right;color:#34d399;font-weight:600;">'+e.ips+'</td></tr>';
    });
    if(!items.length)h+='<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:30px;">Aucune entite</td></tr>';
    h+='</tbody></table></div>';
    el.innerHTML=h;
}

// TAB: IPs
function _stRenderIps(el){
    var items=D.topIps;
    if(_stFilter)items=items.filter(function(ip){return(ip.ip||'').indexOf(_stFilter)>=0;});
    items=_stSortArr(items,_stSort);
    var h='<div style="padding:14px;"><input class="vue-search" placeholder="Filtrer IPs..." oninput="_stFilterFn(this.value)"></div>';
    h+='<div style="overflow-x:auto;max-height:600px;overflow-y:auto;"><table style="width:100%;"><thead><tr>';
    h+='<th>#</th>'+_stSortH('ip','IP')+_stSortH('c','Recherches')+_stSortH('queries','Queries uniques')+_stSortH('last_at','Derniere')+'<th>Action</th>';
    h+='</tr></thead><tbody>';
    items.forEach(function(ip,i){
        var isMe=ip.ip===D.myIp;
        h+='<tr class="vue-row"'+(isMe?' style="background:#f59e0b08;"':'')+'>';
        h+='<td style="color:#484f58;">'+(i+1)+'</td>';
        h+='<td class="mono">'+_esc(ip.ip)+(isMe?' <span style="background:#f59e0b30;color:#f59e0b;padding:1px 6px;border-radius:4px;font-size:10px;">MOI</span>':'')+'</td>';
        h+='<td style="text-align:right;"><span style="background:#f59e0b20;color:#f59e0b;padding:2px 10px;border-radius:6px;font-weight:800;">'+ip.c+'</span></td>';
        h+='<td style="text-align:right;color:#a29bfe;font-weight:600;">'+ip.queries+'</td>';
        h+='<td class="time">'+_fmtDt(ip.last_at)+'</td>';
        h+='<td><button onclick="_stIgnoreIp(\''+_esc(ip.ip)+'\')" class="btn" style="font-size:11px;padding:3px 10px;cursor:pointer;border-color:#ef444440;color:#ef4444;">Ignorer</button></td>';
        h+='</tr>';
    });
    if(!items.length)h+='<tr><td colspan="6" style="text-align:center;color:#5a6580;padding:30px;">Aucune IP</td></tr>';
    h+='</tbody></table></div>';
    el.innerHTML=h;
}

// TAB: Horaire
function _stRenderHourly(el){
    var h='<div style="padding:14px;"><div style="height:250px;position:relative;"><canvas id="stChartHourly"></canvas></div></div>';
    el.innerHTML=h;
    var hours=D.parHeure;
    var labels=[];for(var i=0;i<24;i++)labels.push(i+'h');
    new Chart(document.getElementById('stChartHourly'),{
        type:'bar',data:{labels:labels,datasets:[{label:'Recherches',data:hours,backgroundColor:hours.map(function(v,i){return i===new Date().getHours()?'#f59e0b':'#34d399';}),borderRadius:4}]},
        options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',scales:{x:{grid:{color:'#1e2a3a'},ticks:{color:'#5a6580'}},y:{grid:{color:'#1e2a3a08'},ticks:{color:'#8b949e',font:{size:11}}}},plugins:{legend:{display:false}}}
    });
}

// TAB: Sources
function _stRenderSources(el){
    var h='<div style="padding:14px;display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">';
    h+='<div style="width:280px;height:280px;position:relative;"><canvas id="stChartSources"></canvas></div>';
    h+='<div style="flex:1;min-width:250px;">';
    h+='<h4 style="color:#8b949e;font-size:12px;text-transform:uppercase;margin-bottom:10px;">Detail par source</h4>';
    h+='<table style="width:100%;"><thead><tr><th>Source</th><th>Total</th><th>IPs</th></tr></thead><tbody>';
    D.bySource.forEach(function(s){
        var sc=_stSourceColors[s.source]||'#5a6580';
        h+='<tr><td>'+_badge(s.source,sc)+'</td><td style="text-align:right;color:#f59e0b;font-weight:700;">'+s.c+'</td><td style="text-align:right;color:#34d399;">'+s.ips+'</td></tr>';
    });
    h+='</tbody></table>';
    h+='<h4 style="color:#8b949e;font-size:12px;text-transform:uppercase;margin:16px 0 10px;">Par type</h4>';
    h+='<table style="width:100%;"><thead><tr><th>Type</th><th>Total</th><th>IPs</th></tr></thead><tbody>';
    D.byType.forEach(function(t){
        var tc=_stTypeColors[t.search_type]||'#5a6580';
        h+='<tr><td>'+_badge(t.search_type,tc)+'</td><td style="text-align:right;color:#f59e0b;font-weight:700;">'+t.c+'</td><td style="text-align:right;color:#34d399;">'+t.ips+'</td></tr>';
    });
    h+='</tbody></table></div></div>';
    el.innerHTML=h;
    // Doughnut
    var srcLabels=D.bySource.map(function(s){return s.source;});
    var srcData=D.bySource.map(function(s){return parseInt(s.c);});
    var srcColors=D.bySource.map(function(s){return _stSourceColors[s.source]||'#5a6580';});
    new Chart(document.getElementById('stChartSources'),{
        type:'doughnut',data:{labels:srcLabels,datasets:[{data:srcData,backgroundColor:srcColors,borderWidth:0}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{color:'#8b949e',boxWidth:12}}}}
    });
}

// Ignore IP via form submission
window._stIgnoreIp=function(ip){
    if(!confirm('Ignorer l\'IP '+ip+' dans les stats ?'))return;
    var f=document.createElement('form');f.method='POST';f.style.display='none';
    f.innerHTML='<input name="st_action" value="ignore_ip"><input name="ip" value="'+ip+'"><input name="label" value="">';
    document.body.appendChild(f);f.submit();
};

// Init
_stRenderChart();
_stRender();
})();
</script>

<!-- SECTION 16 : ACTIONS RAPIDES -->
<!-- ============================================================ -->
<div class="section"><h2>Actions rapides</h2></div>
<div class="actions">
    <a href="clear_cache.php" class="btn">Vider tout le cache</a>
    <a href="clear_cache.php?prefix=stats" class="btn">Cache stats</a>
    <a href="clear_cache.php?prefix=clubstats" class="btn">Cache clubs</a>
    <a href="clear_cache.php?prefix=athlete" class="btn">Cache athletes</a>
    <a href="clear_cache.php?prefix=search" class="btn">Cache recherche</a>
    <a href="clear_cache.php?prefix=ep" class="btn">Cache epreuves</a>
    <a href="clear_cache.php?prefix=villestats" class="btn">Cache villes</a>
    <a href="cache_urls.php" class="btn">Pre-generer cache</a>
    <button onclick="_resetVues('athletes')" class="btn" style="border:1px solid #f59e0b40;color:#f59e0b;cursor:pointer;">Reset vues athletes</button>
    <button onclick="_resetVues('clubs')" class="btn" style="border:1px solid #f59e0b40;color:#f59e0b;cursor:pointer;">Reset vues clubs</button>
    <button onclick="_resetVues('all')" class="btn" style="border:1px solid #ef444440;color:#ef4444;cursor:pointer;">Reset toutes les vues</button>
    <a href="setup_bdd.php" class="btn">Setup BDD</a>
</div>

<script>
function _resetVues(type) {
    var label = type === 'all' ? 'athletes + clubs' : type;
    if (!confirm('Remettre a zero les vues ' + label + ' ?')) return;
    fetch('/api/top_searched.php?reset=' + type + '&bk_key=bk_s3cr3t_2026_xK9mP').then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { alert('Vues ' + label + ' remises a zero !'); location.reload(); }
        else alert('Erreur');
    }).catch(function() { alert('Erreur de connexion'); });
}
</script>

<div style="text-align:center;padding:30px;color:#2d3a4a;font-size:11px;">
    Super Admin Panel — Bokonzi — <?= date('Y') ?>
</div>

</body>
</html>
