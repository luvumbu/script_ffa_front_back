<?php
/**
 * admin/panel.php — Dashboard Super Admin
 *
 * Acces : login avec identifiants BDD (username + password)
 * Cookie : bk_sa_token (7 jours)
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// === EMAILS AUTORISES AU PANEL ===
function getPanelAccessList() {
    $f = __DIR__ . '/../logs/.panel_access.php';
    if (!file_exists($f)) return [];
    $raw = file_get_contents($f);
    $pos = strpos($raw, "\n");
    if ($pos === false) return [];
    return json_decode(substr($raw, $pos + 1), true) ?: [];
}
function savePanelAccessList($list) {
    $f = __DIR__ . '/../logs/.panel_access.php';
    file_put_contents($f, "<?php die('Acces interdit'); ?>\n" . json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$_isSA = isSuperAdmin();
$_isPanelUser = false;
$_panelUserEmail = '';

if (!$_isSA) {
    // Verifier si user Google connecte avec email autorise
    require_once __DIR__ . '/../core/auth.php';
    $pUser = getCurrentUser($conn);
    if ($pUser) {
        $panelList = getPanelAccessList();
        if (isset($panelList[$pUser['email']])) {
            $_isPanelUser = true;
            $_panelUserEmail = $pUser['email'];
        }
    }
}

if (!$_isSA && !$_isPanelUser) {
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

// === ACTIONS POST : gestion acces panel (super admin uniquement) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['panel_action']) && $_isSA) {
    $paList = getPanelAccessList();
    if ($_POST['panel_action'] === 'grant' && !empty($_POST['email'])) {
        $em = strtolower(trim($_POST['email']));
        if (filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $paList[$em] = ['added' => date('Y-m-d H:i:s'), 'by' => 'super_admin'];
            savePanelAccessList($paList);
        }
    } elseif ($_POST['panel_action'] === 'revoke' && !empty($_POST['email'])) {
        unset($paList[strtolower(trim($_POST['email']))]);
        savePanelAccessList($paList);
    }
    header('Location: ' . $_SERVER['REQUEST_URI'] . '#panelAccess');
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

// Taille BDD (juste le total pour le cache)
$cacheFiles = glob(__DIR__ . '/../cache/*.json') ?: [];
$cacheSize = 0;
foreach ($cacheFiles as $f) { $cacheSize += filesize($f); }

// Sessions actives
$activeSessions = 0;
$rSess = $conn->query("SELECT COUNT(*) as c FROM user_sessions WHERE expire_at > NOW()");
if ($rSess) $activeSessions = (int)$rSess->fetch_assoc()['c'];

// === LOGS BDD (juste les compteurs pour les cards) ===
$todayLogs = 0;
$rToday = $conn->query("SELECT COUNT(*) as c FROM logs WHERE DATE(ts) = CURDATE()");
if ($rToday) $todayLogs = (int)$rToday->fetch_assoc()['c'];

$yesterdayLogs = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM logs WHERE DATE(ts) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
if ($r) $yesterdayLogs = (int)$r->fetch_assoc()['c'];

$sessionsToday = 0;
$r = $conn->query("SELECT COUNT(DISTINCT sid) as c FROM logs WHERE DATE(ts) = CURDATE()");
if ($r) $sessionsToday = (int)$r->fetch_assoc()['c'];

$uniqueIpsToday = 0;
$r = $conn->query("SELECT COUNT(DISTINCT ip) as c FROM logs WHERE DATE(ts) = CURDATE()");
if ($r) $uniqueIpsToday = (int)$r->fetch_assoc()['c'];

// Bots detection dans IP tracker
$ipData = readIpLog();

// === UTILISATEURS INSCRITS (avec stats) ===
$_users = [];
$rUsers = $conn->query("
    SELECT u.id_user, u.email, u.nom, u.prenom, u.role, u.picture, u.date_creation, u.last_login,
        (SELECT COUNT(*) FROM user_sessions us WHERE us.id_user = u.id_user) as nb_sessions_total,
        (SELECT COUNT(DISTINCT sid) FROM logs l WHERE l.uid = u.id_user AND l.sid IS NOT NULL AND l.sid != '') as nb_sessions_log,
        (SELECT COUNT(*) FROM logs l WHERE l.uid = u.id_user) as nb_actions
    FROM users u
    ORDER BY u.last_login DESC, u.id_user DESC
");
if ($rUsers) while ($row = $rUsers->fetch_assoc()) $_users[] = $row;

// Recherches et URLs par user (via logs.uid → ip → search_tracking)
$_userSearches = [];
$_userUrls = [];
foreach ($_users as $u) {
    $uid = (int)$u['id_user'];
    // IPs de ce user
    $ips = [];
    $r = $conn->query("SELECT DISTINCT ip FROM logs WHERE uid = $uid AND ip != '' LIMIT 10");
    if ($r) while ($row = $r->fetch_assoc()) $ips[] = "'" . $conn->real_escape_string($row['ip']) . "'";

    if (!empty($ips)) {
        $ipList = implode(',', $ips);
        // Nombre de recherches
        $r = $conn->query("SELECT COUNT(*) as c FROM search_tracking WHERE ip IN ($ipList)");
        $_userSearches[$uid] = $r ? (int)$r->fetch_assoc()['c'] : 0;
        // Toutes les URLs visitees
        $urls = [];
        $r = $conn->query("SELECT page, detail, value, ts FROM logs WHERE uid = $uid AND action = 'page_view' ORDER BY ts DESC");
        if ($r) while ($row = $r->fetch_assoc()) $urls[] = $row;
        $_userUrls[$uid] = $urls;
    } else {
        $_userSearches[$uid] = 0;
        $_userUrls[$uid] = [];
    }
}

// === SECTION 17 : PROFILS COMPORTEMENTAUX ===
// Users avec >1 connexion, tries par nombre de connexions
$_behaviorUsers = [];
foreach ($_users as $_u) {
    $uid = (int)$_u['id_user'];
    $nbCo = max((int)$_u['nb_sessions_total'], (int)$_u['nb_sessions_log']);
    if ($nbCo < 2) continue;

    $nom = trim(($_u['nom'] ?? '') . ' ' . ($_u['prenom'] ?? ''));
    $prenom = trim($_u['prenom'] ?? '');
    $nomFam = trim($_u['nom'] ?? '');

    // IPs de ce user
    $userIps = [];
    $r = $conn->query("SELECT DISTINCT ip FROM logs WHERE uid = $uid AND ip != '' LIMIT 20");
    if ($r) while ($row = $r->fetch_assoc()) $userIps[] = $conn->real_escape_string($row['ip']);

    // Profils athletes visites (depuis logs)
    $visitedProfiles = [];
    $r = $conn->query("SELECT page, detail, value, ts FROM logs WHERE uid = $uid AND action = 'page_view' AND (page LIKE '%page=profil%' OR page LIKE '%pages/profil%') ORDER BY ts DESC LIMIT 100");
    if ($r) while ($row = $r->fetch_assoc()) $visitedProfiles[] = $row;

    // Profils consultes depuis search_tracking (type=athlete, source=page_view)
    $viewedAthletes = [];
    if (!empty($userIps)) {
        $ipList = implode("','", $userIps);
        $r = $conn->query("SELECT entity_name, entity_id, COUNT(*) as nb, MAX(created_at) as last_view FROM search_tracking WHERE ip IN ('$ipList') AND search_type = 'athlete' AND source = 'page_view' GROUP BY entity_name, entity_id ORDER BY nb DESC LIMIT 30");
        if ($r) while ($row = $r->fetch_assoc()) $viewedAthletes[] = $row;
    }

    // Clubs consultes
    $viewedClubs = [];
    if (!empty($userIps)) {
        $ipList = implode("','", $userIps);
        $r = $conn->query("SELECT entity_name, COUNT(*) as nb FROM search_tracking WHERE ip IN ('$ipList') AND search_type = 'club' GROUP BY entity_name ORDER BY nb DESC LIMIT 20");
        if ($r) while ($row = $r->fetch_assoc()) $viewedClubs[] = $row;
    }

    // Recherches textuelles
    $searchQueries = [];
    if (!empty($userIps)) {
        $ipList = implode("','", $userIps);
        $r = $conn->query("SELECT query_text, search_type, COUNT(*) as nb FROM search_tracking WHERE ip IN ('$ipList') AND query_text != '' GROUP BY query_text, search_type ORDER BY nb DESC LIMIT 20");
        if ($r) while ($row = $r->fetch_assoc()) $searchQueries[] = $row;
    }

    // Follows (athletes + clubs)
    $email = $conn->real_escape_string($_u['email']);
    $followedAthletes = [];
    $r = $conn->query("SELECT af.athlete_id_ext, CONCAT(a.prenom_athlete, ' ', a.nom_athlete) as nom_athlete FROM athlete_follows af LEFT JOIN athletes a ON a.athlete_id_externe = af.athlete_id_ext WHERE af.email = '$email'");
    if ($r) while ($row = $r->fetch_assoc()) $followedAthletes[] = $row;

    $followedClubs = [];
    $r = $conn->query("SELECT cf.club_id, c.nom_club FROM club_follows cf LEFT JOIN clubs c ON c.id_club = cf.club_id WHERE cf.email = '$email'");
    if ($r) while ($row = $r->fetch_assoc()) $followedClubs[] = $row;

    // Horaires d'activite (repartition par tranche)
    $hourDistrib = ['matin' => 0, 'apresmidi' => 0, 'soir' => 0, 'nuit' => 0];
    $r = $conn->query("SELECT HOUR(ts) as h, COUNT(*) as c FROM logs WHERE uid = $uid GROUP BY HOUR(ts)");
    if ($r) while ($row = $r->fetch_assoc()) {
        $h = (int)$row['h'];
        if ($h >= 6 && $h < 12) $hourDistrib['matin'] += (int)$row['c'];
        elseif ($h >= 12 && $h < 18) $hourDistrib['apresmidi'] += (int)$row['c'];
        elseif ($h >= 18 && $h < 23) $hourDistrib['soir'] += (int)$row['c'];
        else $hourDistrib['nuit'] += (int)$row['c'];
    }

    // Jours actifs
    $activeDays = 0;
    $r = $conn->query("SELECT COUNT(DISTINCT DATE(ts)) as d FROM logs WHERE uid = $uid");
    if ($r) $activeDays = (int)$r->fetch_assoc()['d'];

    // Derniere activite
    $lastActivity = null;
    $r = $conn->query("SELECT MAX(ts) as last_ts FROM logs WHERE uid = $uid");
    if ($r) $lastActivity = $r->fetch_assoc()['last_ts'];

    // Devices (mobile vs desktop depuis screen)
    $devices = ['mobile' => 0, 'desktop' => 0];
    $r = $conn->query("SELECT screen, COUNT(*) as c FROM logs WHERE uid = $uid AND screen != '' GROUP BY screen LIMIT 10");
    if ($r) while ($row = $r->fetch_assoc()) {
        $w = (int)explode('x', $row['screen'])[0];
        if ($w > 0 && $w <= 768) $devices['mobile'] += (int)$row['c'];
        else $devices['desktop'] += (int)$row['c'];
    }

    // Pages les plus visitees (top 5)
    $topPages = [];
    $r = $conn->query("SELECT page, COUNT(*) as c FROM logs WHERE uid = $uid AND action = 'page_view' AND page != '' GROUP BY page ORDER BY c DESC LIMIT 5");
    if ($r) while ($row = $r->fetch_assoc()) $topPages[] = $row;

    // Nombre total de recherches
    $nbSearch = $_userSearches[$uid] ?? 0;

    // Detection : nom/prenom correspond a un athlete visite ?
    $selfProfileMatch = null;
    if ($prenom !== '' && $nomFam !== '') {
        foreach ($viewedAthletes as $va) {
            $eName = mb_strtolower($va['entity_name'] ?? '');
            $uNom = mb_strtolower($nomFam);
            $uPrenom = mb_strtolower($prenom);
            if (strpos($eName, $uNom) !== false && strpos($eName, $uPrenom) !== false) {
                $selfProfileMatch = $va;
                break;
            }
        }
    }

    // Generer la phrase de profil
    $profile = '';
    $fullName = trim(($_u['prenom'] ?? '') . ' ' . ($_u['nom'] ?? '')) ?: $_u['email'];

    // Type d'utilisateur
    $daysSinceCreation = $_u['date_creation'] ? max(1, (int)((time() - strtotime($_u['date_creation'])) / 86400)) : 1;
    $coPerDay = round($nbCo / $daysSinceCreation, 2);

    if ($nbCo >= 20) $profile .= "$fullName est un utilisateur tres actif ($nbCo connexions";
    elseif ($nbCo >= 5) $profile .= "$fullName est un utilisateur regulier ($nbCo connexions";
    else $profile .= "$fullName est un utilisateur occasionnel ($nbCo connexions";
    $profile .= " en $activeDays jour" . ($activeDays > 1 ? 's' : '') . " depuis le " . ($daysSinceCreation > 0 ? date('d/m/Y', strtotime($_u['date_creation'])) : '?') . ").";

    // Self-profile match
    if ($selfProfileMatch) {
        $profile .= " Il consulte probablement son propre profil (" . htmlspecialchars($selfProfileMatch['entity_name']) . " — " . $selfProfileMatch['nb'] . " visite" . ($selfProfileMatch['nb'] > 1 ? 's' : '') . "), ce qui suggere qu'il est l'athlete correspondant.";
    }

    // Horaire prefere
    arsort($hourDistrib);
    $topHour = array_key_first($hourDistrib);
    $hourLabels = ['matin' => 'le matin (6h-12h)', 'apresmidi' => 'l\'apres-midi (12h-18h)', 'soir' => 'le soir (18h-23h)', 'nuit' => 'la nuit (23h-6h)'];
    if ($hourDistrib[$topHour] > 0) {
        $profile .= " Il se connecte principalement " . $hourLabels[$topHour] . ".";
    }

    // Device
    if ($devices['mobile'] + $devices['desktop'] > 0) {
        if ($devices['mobile'] > $devices['desktop']) $profile .= " Navigation majoritairement sur mobile.";
        elseif ($devices['desktop'] > $devices['mobile']) $profile .= " Navigation majoritairement sur desktop.";
        else $profile .= " Navigation mixte mobile/desktop.";
    }

    // Centres d'interet
    if (!empty($viewedAthletes)) {
        $topAth = array_slice($viewedAthletes, 0, 3);
        $names = array_map(function($a) { return htmlspecialchars($a['entity_name']) . ' (' . $a['nb'] . 'x)'; }, $topAth);
        $profile .= " Athletes les plus consultes : " . implode(', ', $names) . ".";
    }
    if (!empty($viewedClubs)) {
        $topCl = array_slice($viewedClubs, 0, 3);
        $names = array_map(function($c) { return htmlspecialchars($c['entity_name']) . ' (' . $c['nb'] . 'x)'; }, $topCl);
        $profile .= " Clubs suivis : " . implode(', ', $names) . ".";
    }
    if (!empty($followedAthletes)) {
        $profile .= " Suit " . count($followedAthletes) . " athlete" . (count($followedAthletes) > 1 ? 's' : '');
        $fNames = array_map(function($f) { return htmlspecialchars($f['nom_athlete']); }, array_slice($followedAthletes, 0, 3));
        $profile .= " (" . implode(', ', $fNames) . ").";
    }
    if (!empty($followedClubs)) {
        $profile .= " Suit " . count($followedClubs) . " club" . (count($followedClubs) > 1 ? 's' : '');
        $fNames = array_map(function($f) { return htmlspecialchars($f['nom_club']); }, array_slice($followedClubs, 0, 3));
        $profile .= " (" . implode(', ', $fNames) . ").";
    }

    // Recherches
    if ($nbSearch > 0) {
        $profile .= " $nbSearch recherche" . ($nbSearch > 1 ? 's' : '') . " effectuee" . ($nbSearch > 1 ? 's' : '') . ".";
        if (!empty($searchQueries)) {
            $topQ = array_slice($searchQueries, 0, 3);
            $qs = array_map(function($q) { return '"' . htmlspecialchars($q['query_text']) . '"'; }, $topQ);
            $profile .= " Termes frequents : " . implode(', ', $qs) . ".";
        }
    }

    // Derniere activite
    if ($lastActivity) {
        $daysAgo = (int)((time() - strtotime($lastActivity)) / 86400);
        if ($daysAgo === 0) $profile .= " Actif aujourd'hui.";
        elseif ($daysAgo === 1) $profile .= " Derniere activite hier.";
        elseif ($daysAgo <= 7) $profile .= " Derniere activite il y a $daysAgo jours.";
        else $profile .= " Inactif depuis $daysAgo jours.";
    }

    $_behaviorUsers[] = [
        'user' => $_u,
        'nb_co' => $nbCo,
        'nb_search' => $nbSearch,
        'nb_actions' => (int)$_u['nb_actions'],
        'active_days' => $activeDays,
        'visited_profiles' => $visitedProfiles,
        'viewed_athletes' => $viewedAthletes,
        'viewed_clubs' => $viewedClubs,
        'search_queries' => $searchQueries,
        'followed_athletes' => $followedAthletes,
        'followed_clubs' => $followedClubs,
        'hour_distrib' => $hourDistrib,
        'devices' => $devices,
        'top_pages' => $topPages,
        'self_match' => $selfProfileMatch,
        'profile_text' => $profile,
        'last_activity' => $lastActivity,
    ];
}
// Trier par nombre de connexions decroissant
usort($_behaviorUsers, function($a, $b) { return $b['nb_co'] - $a['nb_co']; });

$hasVuesTables = false; // Sections 3-12 desactivees

if (false) { // DESACTIVE — sections supprimees
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

// === COURRIER NON CONFIRME ===
$unconfirmedMessages = [];
$resUnconf = $conn->query("SELECT * FROM contact_confirm_tokens WHERE used = 0 ORDER BY created_at DESC");
if ($resUnconf) {
    while ($row = $resUnconf->fetch_assoc()) $unconfirmedMessages[] = $row;
    $resUnconf->free();
}

// === SIGNALEMENTS PROFIL ===
$profileReports = [];
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
// Ajouter colonne visible si elle n'existe pas
$_vc = @$conn->query("SHOW COLUMNS FROM `athletes` LIKE 'visible'");
$_hasVisible = ($_vc && $_vc->num_rows > 0);
if ($_vc) $_vc->free();
if (!$_hasVisible) {
    @$conn->query("ALTER TABLE `athletes` ADD COLUMN `visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");
}
$resReports = $conn->query("SELECT pr.*, COALESCE(a.visible, 1) as athlete_visible FROM profile_reports pr LEFT JOIN athletes a ON a.athlete_id_externe = pr.athlete_id_ext ORDER BY pr.created_at DESC");
if ($resReports) {
    while ($row = $resReports->fetch_assoc()) $profileReports[] = $row;
    $resReports->free();
}
$newReportsCount = 0;
foreach ($profileReports as $pr) { if ($pr['status'] === 'new') $newReportsCount++; }

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

// $conn->close() deplace en fin de fichier

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
        /* TEST — bloc rouge bordure blanche */
        .test-block {
            background: #dc2626;
            border: 3px solid #fff;
            border-radius: 12px;
            padding: 30px 32px;
            margin: 20px 0;
            color: #fff;
            text-align: center;
        }
        .test-block-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .test-block-value {
            font-size: 36px;
            font-weight: 800;
        }
        .test-block-items {
            max-height: 320px;
            overflow-y: auto;
            text-align: left;
        }
        .test-block-items::-webkit-scrollbar { width: 6px; }
        .test-block-items::-webkit-scrollbar-track { background: rgba(0,0,0,.2); border-radius: 3px; }
        .test-block-items::-webkit-scrollbar-thumb { background: rgba(255,255,255,.35); border-radius: 3px; }
        .test-block-item {
            font-size: 28px;
            font-weight: 700;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            cursor: pointer;
            user-select: all;
        }
        .test-block-item:hover { background: rgba(255,255,255,.1); }
        .test-block-item:last-child {
            border-bottom: none;
        }
        .test-block-selectall {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 18px;
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.4);
            border-radius: 8px;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }
        .test-block-selectall:hover { background: rgba(255,255,255,.3); }
        .test-block--yellow {
            background: #ca8a04;
        }
        .test-block--blue {
            background: #1e3a5f;
            border: 1px solid #2563eb40;
        }
        /* Recherche athlete panel */
        .panel-search {
            background: #161b22;
            border: 2px solid #6c5ce7;
            border-radius: 12px;
            padding: 24px;
            margin: 20px 0;
        }
        .panel-search-title {
            color: #a29bfe;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .panel-search-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .panel-search-input {
            flex: 1;
            min-width: 180px;
            padding: 12px 16px;
            background: #0d1117;
            border: 1px solid #1e2a3a;
            border-radius: 8px;
            color: #f0f6fc;
            font-size: 15px;
        }
        .panel-search-input:focus {
            outline: none;
            border-color: #6c5ce7;
            box-shadow: 0 0 0 3px #6c5ce722;
        }
        .panel-search-btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .panel-search-btn--primary {
            background: #6c5ce7;
            color: #fff;
        }
        .panel-search-btn--primary:hover {
            background: #5a4bd1;
        }
        .panel-search-btn--swap {
            background: #f59e0b20;
            border: 1px solid #f59e0b40;
            color: #f59e0b;
        }
        .panel-search-btn--swap:hover {
            background: #f59e0b33;
        }
        .panel-search-results {
            margin-top: 16px;
        }
        .panel-search-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid #1e2a3a;
            color: #c9d1d9;
            font-size: 15px;
        }
        .panel-search-item:hover {
            background: #1e2a3a40;
        }
        .panel-search-item:last-child {
            border-bottom: none;
        }
        .panel-search-name {
            font-weight: 700;
            color: #f0f6fc;
        }
        .panel-search-meta {
            color: #5a6580;
            font-size: 12px;
        }
        .panel-search-count {
            color: #5a6580;
            font-size: 13px;
            margin-top: 12px;
        }
        /* Live search (copie dashboard.css) */
        .live-search {
            position: relative;
            margin-bottom: 8px;
        }
        .live-search input {
            width: 100%;
            padding: 14px 16px 14px 44px;
            background: #0d1117;
            border: 2px solid #1a2540;
            border-radius: 10px;
            color: #f0f6fc;
            font-size: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .live-search input:focus {
            outline: none;
            border-color: #a29bfe;
            box-shadow: 0 0 0 3px #a29bfe22;
        }
        .ls-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            opacity: 0.5;
            pointer-events: none;
        }
        .ls-status {
            font-size: 12px;
            color: #5a6580;
            margin-top: 6px;
            min-height: 18px;
        }
        .ls-status.error { color: #ff7675; }
        .ls-spinner {
            display: inline-block;
            width: 12px; height: 12px;
            border: 2px solid #a29bfe44;
            border-top-color: #a29bfe;
            border-radius: 50%;
            animation: _lsSpin 0.6s linear infinite;
        }
        @keyframes _lsSpin { to { transform: rotate(360deg); } }
        .ls-results { display: none; }
        /* bk-table pour resultats */
        .bk-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .bk-table th {
            background: #0d1117;
            color: #8b949e;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            text-align: left;
            position: sticky;
            top: 0;
        }
        .bk-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #1e2a3a;
            color: #c9d1d9;
        }
        .bk-table tr:hover td { background: #1e2a3a40; }
        .bk-table a { color: #a29bfe; text-decoration: none; }
        .bk-table a:hover { text-decoration: underline; }
        .table-wrap { overflow-x: auto; border-radius: 8px; border: 1px solid #1e2a3a; margin-top: 8px; }
        .badge-cat { background: #6c5ce720; color: #a29bfe; }
        .badge-m { background: #3b82f620; color: #60a5fa; }
        .badge-f { background: #ec489920; color: #f472b6; }
        .badge-perf { background: #f59e0b20; color: #fbbf24; }
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

<!-- ============================================================ -->
<!-- RECHERCHE ATHLETE (live search) -->
<!-- ============================================================ -->
<div class="panel-search">
    <div class="panel-search-title">Recherche Athlete</div>
    <div class="live-search">
        <span class="ls-icon">&#128269;</span>
        <input type="text" id="psInput" placeholder="Recherche rapide par nom..." autocomplete="off">
        <div class="ls-status" id="psStatus"></div>
    </div>
    <div class="ls-results" id="psResults"></div>
</div>
<script>
function _selectAllItems(id) {
    var el = document.getElementById(id);
    if (!el) return;
    var range = document.createRange();
    range.selectNodeContents(el);
    var sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
}
</script>
<script>
(function() {
    var input = document.getElementById('psInput');
    var status = document.getElementById('psStatus');
    var results = document.getElementById('psResults');
    var timer = null;
    var base = location.hostname === 'localhost' ? '/BK' : '';

    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function hl(text, q) {
        if (!text) return '';
        var s = esc(text);
        var r = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return s.replace(r, '<mark style="background:#1f6feb44;color:#58a6ff;">$1</mark>');
    }

    input.addEventListener('input', function() {
        var q = this.value.trim();
        clearTimeout(timer);
        if (q.length < 2) {
            input.style.borderColor = '#1a2540';
            results.style.display = 'none';
            results.innerHTML = '';
            status.textContent = q.length === 1 ? 'Tapez au moins 2 caracteres...' : '';
            status.className = 'ls-status';
            return;
        }
        input.style.borderColor = '#a29bfe';
        status.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;"><span class="ls-spinner"></span> Recherche en cours...</span>';
        status.className = 'ls-status loading';

        timer = setTimeout(function() {
            fetch(base + '/api/search.php?nom=' + encodeURIComponent(q) + '&limit=50&bk_key=bk_s3cr3t_2026_xK9mP', { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    input.style.borderColor = '#1a2540';
                    if (!data.success || !data.athletes || data.athletes.length === 0) {
                        status.innerHTML = '<span style="color:#ff7675;">&#10007;</span> Aucun resultat pour "' + esc(q) + '"';
                        status.className = 'ls-status';
                        results.style.display = 'none';
                        return;
                    }
                    var total = data.total || 0;
                    status.innerHTML = '<span style="color:#34d399;">&#10003;</span> ' + total + ' resultat' + (total > 1 ? 's' : '') + (total > 50 ? ' (50 affiches)' : '');
                    status.className = 'ls-status';
                    input.style.borderColor = '#34d399';
                    setTimeout(function() { input.style.borderColor = '#1a2540'; }, 1500);

                    var th = '<tr><th>#</th><th>Nom complet</th><th>Club</th><th>Cat</th><th>Sexe</th><th>NAT</th></tr>';
                    var html = '<div class="table-wrap">';
                    html += '<table class="bk-table">' + th + '</table>';
                    html += '<table class="bk-table">';
                    data.athletes.forEach(function(a, i) {
                        var nom = (a.nom_complet || (a.nom_athlete + ' ' + a.prenom_athlete)) || '';
                        var aid = a.athlete_id || a.athlete_id_externe || '';
                        html += '<tr>';
                        html += '<td>' + (i + 1) + '</td>';
                        html += '<td><b><a href="' + base + '/?page=profil&id=' + aid + '" target="_blank">' + hl(nom, q) + '</a></b></td>';
                        html += '<td>' + esc(a.club || '') + '</td>';
                        html += '<td><span class="badge badge-cat">' + esc(a.categorie || a.categorie_athlete || '') + '</span></td>';
                        html += '<td><span class="badge badge-' + (a.sexe || a.sexe_athlete || '').toLowerCase() + '">' + esc(a.sexe || a.sexe_athlete || '') + '</span></td>';
                        html += '<td>' + esc(a.nationalite || a.nationalite_athlete || '') + '</td>';
                        html += '</tr>';
                    });
                    html += '</table>';
                    html += '<table class="bk-table">' + th + '</table>';
                    html += '</div>';
                    results.innerHTML = html;
                    results.style.display = 'block';
                })
                .catch(function() {
                    input.style.borderColor = '#ff7675';
                    status.textContent = 'Erreur de connexion';
                    status.className = 'ls-status error';
                });
        }, 300);
    });
})();
</script>

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
    <div class="card"><div class="num info"><?= number_format($stats['users']) ?></div><div class="label">Utilisateurs inscrits</div></div>
    <div class="card"><div class="num info"><?= number_format($activeSessions) ?></div><div class="label">Sessions actives</div></div>
    <div class="card">
        <div class="num warn"><?= number_format($todayLogs) ?></div>
        <div class="label">Events aujourd'hui</div>
        <div class="sub"><?= pctChange($todayLogs, $yesterdayLogs) ?> vs hier (<?= number_format($yesterdayLogs) ?>)</div>
    </div>
    <div class="card"><div class="num warn"><?= number_format($sessionsToday) ?></div><div class="label">Sessions aujourd'hui</div></div>
    <div class="card"><div class="num pink"><?= number_format($uniqueIpsToday) ?></div><div class="label">IPs uniques aujourd'hui</div></div>
</div>

<!-- ============================================================ -->
<!-- 5 DERNIERS CLUBS RECHERCHES -->
<!-- ============================================================ -->
<?php
// --- DERNIERS CLUBS RECHERCHES (50) ---
$_lastClubs = [];
$_lcRes = $conn->query("
    SELECT st.entity_name, st.ip, st.source, st.created_at,
           COALESCE(NULLIF(CONCAT(u.prenom,' ',u.nom),' '), u.email, NULL) as user_name
    FROM search_tracking st
    LEFT JOIN logs l ON l.ip = st.ip AND l.uid IS NOT NULL AND l.ts >= DATE_SUB(st.created_at, INTERVAL 1 HOUR) AND l.ts <= DATE_ADD(st.created_at, INTERVAL 1 HOUR)
    LEFT JOIN users u ON u.id_user = l.uid
    WHERE st.search_type = 'club' AND st.entity_name IS NOT NULL AND st.entity_name != ''
    GROUP BY st.id_search
    ORDER BY st.created_at DESC
    LIMIT 50
");
if ($_lcRes) { while ($_lcRow = $_lcRes->fetch_assoc()) $_lastClubs[] = $_lcRow; }
?>
<div style="background:#111830;border:1px solid #1e2a3a;border-radius:12px;padding:20px;margin:20px 0;">
    <h3 style="color:#f59e0b;font-size:16px;margin:0 0 14px;font-weight:700;">&#127963; Derniers clubs recherches (<?= count($_lastClubs) ?>)</h3>
    <?php if ($_lastClubs): ?>
    <div style="overflow-x:auto;overflow-y:auto;max-height:500px;border-radius:8px;border:1px solid #1e2a3a;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;background:#0c1020;">
    <tr style="background:#0f1525;position:sticky;top:0;z-index:1;">
        <th style="padding:10px 10px;text-align:center;color:#7c85a0;font-size:10px;text-transform:uppercase;background:#0f1525;width:35px;">#</th>
        <th style="padding:10px 10px;text-align:left;color:#7c85a0;font-size:10px;text-transform:uppercase;background:#0f1525;">Club</th>
        <th style="padding:10px 10px;text-align:left;color:#7c85a0;font-size:10px;text-transform:uppercase;background:#0f1525;">Utilisateur / IP</th>
        <th style="padding:10px 10px;text-align:center;color:#7c85a0;font-size:10px;text-transform:uppercase;background:#0f1525;">Source</th>
        <th style="padding:10px 10px;text-align:right;color:#7c85a0;font-size:10px;text-transform:uppercase;background:#0f1525;">Date</th>
    </tr>
    <?php foreach ($_lastClubs as $_i => $_c):
        $srcLabel = ['live_search'=>'Recherche','page_view'=>'Page','panel_open'=>'Panneau'][$_c['source']] ?? $_c['source'];
        $srcColor = ['live_search'=>'#3b82f6','page_view'=>'#10b981','panel_open'=>'#f59e0b'][$_c['source']] ?? '#8b949e';
    ?>
    <tr style="border-top:1px solid #121a30;" onmouseover="this.style.background='#131b35'" onmouseout="this.style.background='transparent'">
        <td style="padding:7px 10px;text-align:center;color:#5a6580;"><?= $_i + 1 ?></td>
        <td style="padding:7px 10px;color:#fbbf24;font-weight:600;"><?= htmlspecialchars($_c['entity_name']) ?></td>
        <td style="padding:7px 10px;">
            <?php if ($_c['user_name']): ?>
                <span style="color:#e2e8f0;"><?= htmlspecialchars($_c['user_name']) ?></span>
                <span style="color:#5a6580;font-size:11px;margin-left:4px;">(<?= htmlspecialchars($_c['ip']) ?>)</span>
            <?php else: ?>
                <span style="color:#8b949e;font-family:monospace;"><?= htmlspecialchars($_c['ip']) ?></span>
            <?php endif; ?>
        </td>
        <td style="padding:7px 10px;text-align:center;"><span style="background:<?= $srcColor ?>20;color:<?= $srcColor ?>;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;"><?= $srcLabel ?></span></td>
        <td style="padding:7px 10px;text-align:right;color:#5a6580;font-size:12px;"><?= date('d/m H:i', strtotime($_c['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </table>
    </div>
    <?php else: ?>
    <div style="color:#5a6580;text-align:center;padding:20px;">Aucun club recherche</div>
    <?php endif; ?>
</div>

<?php
// --- DERNIERS ATHLETES CONSULTES (50) ---
$_lastSelections = [];
$_lsRes = $conn->query("
    SELECT st.entity_name, st.entity_id, st.ip, st.source, st.created_at,
           COALESCE(NULLIF(CONCAT(u.prenom,' ',u.nom),' '), u.email, NULL) as user_name
    FROM search_tracking st
    LEFT JOIN logs l ON l.ip = st.ip AND l.uid IS NOT NULL AND l.ts >= DATE_SUB(st.created_at, INTERVAL 1 HOUR) AND l.ts <= DATE_ADD(st.created_at, INTERVAL 1 HOUR)
    LEFT JOIN users u ON u.id_user = l.uid
    WHERE st.search_type = 'athlete' AND st.entity_name IS NOT NULL AND st.entity_name != ''
    GROUP BY st.id_search
    ORDER BY st.created_at DESC
    LIMIT 50
");
if ($_lsRes) { while ($_lsRow = $_lsRes->fetch_assoc()) $_lastSelections[] = $_lsRow; }
?>
<div style="background:#111830;border:1px solid #1e2a3a;border-radius:12px;padding:20px;margin:20px 0;">
    <h3 style="color:#a29bfe;font-size:16px;margin:0 0 14px;font-weight:700;">&#127939; Derniers athletes consultes (<?= count($_lastSelections) ?>)</h3>
    <?php if ($_lastSelections): ?>
    <div style="overflow-x:auto;overflow-y:auto;max-height:500px;border-radius:8px;border:1px solid #1e2a3a;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;background:#0c1020;">
    <tr style="background:#0f1525;position:sticky;top:0;z-index:1;">
        <th style="padding:10px 10px;text-align:center;color:#7c85a0;font-size:10px;text-transform:uppercase;background:#0f1525;width:35px;">#</th>
        <th style="padding:10px 10px;text-align:left;color:#7c85a0;font-size:10px;text-transform:uppercase;background:#0f1525;">Athlete</th>
        <th style="padding:10px 10px;text-align:left;color:#7c85a0;font-size:10px;text-transform:uppercase;background:#0f1525;">Utilisateur / IP</th>
        <th style="padding:10px 10px;text-align:center;color:#7c85a0;font-size:10px;text-transform:uppercase;background:#0f1525;">Source</th>
        <th style="padding:10px 10px;text-align:center;color:#7c85a0;font-size:10px;text-transform:uppercase;background:#0f1525;">ID</th>
        <th style="padding:10px 10px;text-align:right;color:#7c85a0;font-size:10px;text-transform:uppercase;background:#0f1525;">Date</th>
    </tr>
    <?php foreach ($_lastSelections as $_i => $_a):
        $srcLabel = ['live_search'=>'Recherche','page_view'=>'Page','panel_open'=>'Panneau'][$_a['source']] ?? $_a['source'];
        $srcColor = ['live_search'=>'#3b82f6','page_view'=>'#10b981','panel_open'=>'#f59e0b'][$_a['source']] ?? '#8b949e';
    ?>
    <tr style="border-top:1px solid #121a30;" onmouseover="this.style.background='#131b35'" onmouseout="this.style.background='transparent'">
        <td style="padding:7px 10px;text-align:center;color:#5a6580;"><?= $_i + 1 ?></td>
        <td style="padding:7px 10px;color:#a29bfe;font-weight:600;"><?= htmlspecialchars($_a['entity_name']) ?></td>
        <td style="padding:7px 10px;">
            <?php if ($_a['user_name']): ?>
                <span style="color:#e2e8f0;"><?= htmlspecialchars($_a['user_name']) ?></span>
                <span style="color:#5a6580;font-size:11px;margin-left:4px;">(<?= htmlspecialchars($_a['ip']) ?>)</span>
            <?php else: ?>
                <span style="color:#8b949e;font-family:monospace;"><?= htmlspecialchars($_a['ip']) ?></span>
            <?php endif; ?>
        </td>
        <td style="padding:7px 10px;text-align:center;"><span style="background:<?= $srcColor ?>20;color:<?= $srcColor ?>;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;"><?= $srcLabel ?></span></td>
        <td style="padding:7px 10px;text-align:center;color:#5a6580;font-size:11px;font-family:monospace;"><?= $_a['entity_id'] ? htmlspecialchars($_a['entity_id']) : '—' ?></td>
        <td style="padding:7px 10px;text-align:right;color:#5a6580;font-size:12px;"><?= date('d/m H:i', strtotime($_a['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </table>
    </div>
    <?php else: ?>
    <div style="color:#5a6580;text-align:center;padding:20px;">Aucun athlete consulte</div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- CLASSEMENT ATHLETES LES PLUS RECHERCHES -->
<!-- ============================================================ -->
<?php
$_topAthletes = [];
$_taRes = $conn->query("
    SELECT st.entity_name, COUNT(*) as nb_recherches, COUNT(DISTINCT st.ip) as nb_ips,
           MAX(st.created_at) as derniere_recherche
    FROM search_tracking st
    WHERE st.search_type = 'athlete' AND st.entity_name IS NOT NULL AND st.entity_name != ''
    GROUP BY st.entity_name
    ORDER BY nb_recherches DESC
    LIMIT 50
");
if ($_taRes) {
    while ($_taRow = $_taRes->fetch_assoc()) {
        $_topAthletes[] = $_taRow;
    }
}
// Preparer les IPs par athlete pour le detail au clic
$_topAthIps = [];
foreach ($_topAthletes as $_ta) {
    $eName = $conn->real_escape_string($_ta['entity_name']);
    $_ipRes = $conn->query("
        SELECT st.ip, COUNT(*) as nb, MAX(st.created_at) as derniere,
               COALESCE(NULLIF(CONCAT(u.prenom,' ',u.nom),' '), u.email, NULL) as user_name
        FROM search_tracking st
        LEFT JOIN logs l ON l.ip = st.ip AND l.uid IS NOT NULL AND l.ts >= DATE_SUB(st.created_at, INTERVAL 1 HOUR) AND l.ts <= DATE_ADD(st.created_at, INTERVAL 1 HOUR)
        LEFT JOIN users u ON u.id_user = l.uid
        WHERE st.search_type = 'athlete' AND st.entity_name = '$eName'
        GROUP BY st.ip
        ORDER BY nb DESC
    ");
    $ips = [];
    if ($_ipRes) {
        while ($r = $_ipRes->fetch_assoc()) $ips[] = $r;
    }
    $_topAthIps[] = $ips;
}
?>
<div class="test-block test-block--blue">
    <div class="test-block-title">Classement athletes les plus recherches (<?= count($_topAthletes) ?>)</div>
    <?php if ($_topAthletes): ?>
        <div style="overflow-x:auto;overflow-y:auto;max-height:600px;border-radius:8px;border:1px solid #1e2a3a;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;background:#0c1020;">
        <tr style="background:#111830;position:sticky;top:0;z-index:1;">
            <th style="padding:10px 12px;text-align:center;color:#7c85a0;font-size:11px;text-transform:uppercase;letter-spacing:.5px;background:#111830;width:40px;">#</th>
            <th style="padding:10px 12px;text-align:left;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">Athlete</th>
            <th style="padding:10px 12px;text-align:center;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">Recherches</th>
            <th style="padding:10px 12px;text-align:center;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">IPs uniques</th>
            <th style="padding:10px 12px;text-align:right;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">Derniere</th>
        </tr>
        <?php foreach ($_topAthletes as $_ti => $_ta):
            $pct = $_topAthletes[0]['nb_recherches'] > 0 ? round(($_ta['nb_recherches'] / $_topAthletes[0]['nb_recherches']) * 100) : 0;
            $ipData = $_topAthIps[$_ti];
        ?>
        <tr style="border-top:1px solid #121a30;cursor:pointer;" onclick="var d=document.getElementById('taIps<?= $_ti ?>');d.style.display=d.style.display==='none'?'table-row':'none';" onmouseover="this.style.background='#131b35'" onmouseout="this.style.background='transparent'">
            <td style="padding:8px 12px;text-align:center;color:#5a6580;font-weight:600;"><?= $_ti + 1 ?></td>
            <td style="padding:8px 12px;color:#e2e8f0;font-weight:600;">
                <?= htmlspecialchars($_ta['entity_name']) ?>
                <span style="font-size:10px;color:#6c5ce7;margin-left:6px;">&#9660; IPs</span>
                <div style="margin-top:4px;height:3px;background:#1e2a3a;border-radius:2px;overflow:hidden;">
                    <div style="width:<?= $pct ?>%;height:100%;background:linear-gradient(90deg,#6c5ce7,#a29bfe);border-radius:2px;"></div>
                </div>
            </td>
            <td style="padding:8px 12px;text-align:center;color:#a29bfe;font-weight:700;font-size:15px;"><?= number_format($_ta['nb_recherches']) ?></td>
            <td style="padding:8px 12px;text-align:center;color:#8b949e;"><?= number_format($_ta['nb_ips']) ?></td>
            <td style="padding:8px 12px;text-align:right;color:#5a6580;font-size:12px;"><?= date('d/m H:i', strtotime($_ta['derniere_recherche'])) ?></td>
        </tr>
        <tr id="taIps<?= $_ti ?>" style="display:none;">
            <td colspan="5" style="padding:0 12px 12px 40px;background:#0a0e1a;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:6px;">
                <tr>
                    <th style="padding:6px 10px;text-align:left;color:#6c5ce7;font-size:10px;text-transform:uppercase;">IP</th>
                    <th style="padding:6px 10px;text-align:left;color:#6c5ce7;font-size:10px;text-transform:uppercase;">Utilisateur</th>
                    <th style="padding:6px 10px;text-align:center;color:#6c5ce7;font-size:10px;text-transform:uppercase;">Nb</th>
                    <th style="padding:6px 10px;text-align:right;color:#6c5ce7;font-size:10px;text-transform:uppercase;">Derniere</th>
                </tr>
                <?php foreach ($ipData as $ip): ?>
                <tr style="border-top:1px solid #1e2a3a20;">
                    <td style="padding:5px 10px;color:#e2e8f0;font-family:monospace;"><?= htmlspecialchars($ip['ip']) ?></td>
                    <td style="padding:5px 10px;color:#8b949e;"><?= $ip['user_name'] ? htmlspecialchars($ip['user_name']) : '<span style="opacity:.4">anonyme</span>' ?></td>
                    <td style="padding:5px 10px;text-align:center;color:#a29bfe;font-weight:600;"><?= $ip['nb'] ?></td>
                    <td style="padding:5px 10px;text-align:right;color:#5a6580;"><?= date('d/m H:i', strtotime($ip['derniere'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </table>
            </td>
        </tr>
        <?php endforeach; ?>
        </table>
        </div>
    <?php else: ?>
        <div class="test-block-value">Aucune donnee</div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- UTILISATEURS INSCRITS -->
<!-- ============================================================ -->
<div class="section"><h2 style="color:#a29bfe;font-size:16px;border-color:#a29bfe40;">&#128100; Utilisateurs inscrits (<?= count($_users) ?>)</h2></div>

<div style="overflow-x:auto;overflow-y:auto;max-height:500px;border-radius:12px;border:1px solid #1e2a3a;margin-bottom:20px;">
<table style="width:100%;border-collapse:collapse;font-size:13px;background:#0c1020;">
<tr style="background:#111830;position:sticky;top:0;z-index:1;">
    <th style="padding:12px 14px;text-align:left;color:#7c85a0;font-size:11px;text-transform:uppercase;letter-spacing:.5px;background:#111830;">#</th>
    <th style="padding:12px 14px;text-align:left;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">Utilisateur</th>
    <th style="padding:12px 14px;text-align:left;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">Role</th>
    <th style="padding:12px 14px;text-align:left;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">Inscription</th>
    <th style="padding:12px 14px;text-align:left;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">Derniere connexion</th>
    <th style="padding:12px 14px;text-align:center;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">Connexions</th>
    <th style="padding:12px 14px;text-align:center;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">Actions</th>
    <th style="padding:12px 14px;text-align:center;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">Recherches</th>
    <th style="padding:12px 14px;text-align:center;color:#7c85a0;font-size:11px;text-transform:uppercase;background:#111830;">Pages</th>
</tr>
<?php foreach ($_users as $_ui => $_u):
    $uid = (int)$_u['id_user'];
    $nbSearch = $_userSearches[$uid] ?? 0;
    $urls = $_userUrls[$uid] ?? [];
    $roleBg = ['admin'=>'#e11d4830','coach'=>'#6c5ce730','club'=>'#f59e0b30','athlete'=>'#10b98130'][$_u['role']] ?? '#1e2a3a';
    $roleColor = ['admin'=>'#fb7185','coach'=>'#a29bfe','club'=>'#fbbf24','athlete'=>'#34d399'][$_u['role']] ?? '#8b949e';
?>
<tr style="border-top:1px solid #121a30;" onmouseover="this.style.background='#131b35'" onmouseout="this.style.background='transparent'">
    <td style="padding:10px 14px;color:#5a6580;"><?= $_ui + 1 ?></td>
    <td style="padding:10px 14px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <?php if (!empty($_u['picture'])): ?>
                <img src="<?= htmlspecialchars($_u['picture']) ?>" alt="" style="width:28px;height:28px;border-radius:50%;border:1px solid #1e2a3a;" referrerpolicy="no-referrer">
            <?php else: ?>
                <div style="width:28px;height:28px;border-radius:50%;background:#1e2a3a;display:flex;align-items:center;justify-content:center;font-size:12px;color:#5a6580;">&#128100;</div>
            <?php endif; ?>
            <div>
                <div style="color:#e2e8f0;font-weight:600;font-size:13px;"><?= htmlspecialchars(trim(($_u['prenom'] ?? '') . ' ' . ($_u['nom'] ?? '')) ?: '—') ?></div>
                <div style="color:#5a6580;font-size:11px;"><?= htmlspecialchars($_u['email']) ?></div>
            </div>
        </div>
    </td>
    <td style="padding:10px 14px;"><span style="background:<?= $roleBg ?>;color:<?= $roleColor ?>;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:600;"><?= htmlspecialchars($_u['role']) ?></span></td>
    <td style="padding:10px 14px;color:#8b949e;font-size:12px;"><?= $_u['date_creation'] ? date('d/m/Y', strtotime($_u['date_creation'])) : '—' ?></td>
    <td style="padding:10px 14px;color:#8b949e;font-size:12px;"><?= $_u['last_login'] ? date('d/m/Y H:i', strtotime($_u['last_login'])) : '—' ?></td>
    <?php $nbCo = max((int)$_u['nb_sessions_total'], (int)$_u['nb_sessions_log']); ?>
    <td style="padding:10px 14px;text-align:center;"><span style="color:#a29bfe;font-weight:700;"><?= $nbCo > 0 ? number_format($nbCo) : '<span style="color:#f59e0b;">1</span>' ?></span></td>
    <td style="padding:10px 14px;text-align:center;"><span style="color:#f59e0b;font-weight:700;"><?= number_format($_u['nb_actions']) ?></span></td>
    <td style="padding:10px 14px;text-align:center;"><span style="color:#34d399;font-weight:700;"><?= number_format($nbSearch) ?></span></td>
    <td style="padding:10px 14px;text-align:center;">
        <?php if (!empty($urls)): ?>
        <button onclick="var d=document.getElementById('ud<?= $uid ?>');d.style.display=d.style.display==='none'?'table-row':'none'" style="background:#1e2a3a;border:1px solid #2a3560;color:#a29bfe;padding:5px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;"><?= count($urls) ?> &#9660;</button>
        <?php else: ?>
        <span style="color:#3a4560;">—</span>
        <?php endif; ?>
    </td>
</tr>
<?php if (!empty($urls)): ?>
<tr id="ud<?= $uid ?>" style="display:none;">
    <td colspan="9" style="padding:0;">
        <?php
        $_pageCount = [];
        foreach ($urls as $_url) {
            $p = $_url['page'] ?: 'autre';
            $_pageCount[$p] = ($_pageCount[$p] ?? 0) + 1;
        }
        arsort($_pageCount);
        foreach ($_pageCount as $_pg => $_cnt):
            // Construire le lien direct
            $pgClean = urldecode($_pg);
            if (strpos($pgClean, '/') === 0 || strpos($pgClean, '?') === 0) {
                $href = 'https://bokonzi.com' . $pgClean;
            } else {
                $href = 'https://bokonzi.com/' . $pgClean;
            }
            // Label court pour l'affichage
            $label = $pgClean;
            if (preg_match('/page=([^&]+)/', $pgClean, $m)) {
                $label = $m[1];
                if (preg_match('/id=(\d+)/', $pgClean, $m2)) $label .= ' #' . $m2[1];
                if (preg_match('/open=([^&]+)/', $pgClean, $m3)) $label .= ' — ' . urldecode($m3[1]);
                if (preg_match('/club=([^&]+)/', $pgClean, $m4)) $label .= ' — ' . urldecode($m4[1]);
            }
        ?>
        <a href="<?= htmlspecialchars($href) ?>" target="_blank" style="display:block;padding:10px 20px;color:#a29bfe;text-decoration:none;font-size:13px;font-weight:600;border-bottom:1px solid #121a30;background:#080c14;transition:background .15s;"
           onmouseover="this.style.background='#131b35'"
           onmouseout="this.style.background='#080c14'"
        ><?= htmlspecialchars($label) ?> <span style="color:#5a6580;font-weight:400;float:right;"><?= $_cnt ?> visite<?= $_cnt > 1 ? 's' : '' ?></span></a>
        <?php endforeach; ?>
    </td>
</tr>
<?php endif; ?>
<?php endforeach; ?>
</table>
</div>

<!-- ============================================================ -->
<!-- ACCES PANEL ADMIN -->
<!-- ============================================================ -->
<div id="panelAccess" class="section"><h2 style="color:#f59e0b;font-size:16px;border-color:#f59e0b40;">&#128272; Acces Panel Admin</h2></div>

<?php $paList = getPanelAccessList(); ?>
<div style="border:1px solid #1e2a3a;border-radius:12px;overflow:hidden;margin-bottom:20px;">

<!-- Super Admin -->
<div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:#111830;border-bottom:1px solid #1e2a3a;">
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:32px;height:32px;border-radius:50%;background:#e11d4830;display:flex;align-items:center;justify-content:center;font-size:14px;">&#128081;</div>
        <div>
            <div style="color:#fb7185;font-weight:700;font-size:14px;">Super Admin</div>
            <div style="color:#5a6580;font-size:11px;">Credentials BDD</div>
        </div>
    </div>
    <span style="background:#e11d4830;color:#fb7185;padding:4px 12px;border-radius:8px;font-size:11px;font-weight:600;">Permanent</span>
</div>

<!-- Membres autorises -->
<?php if (empty($paList)): ?>
<div style="padding:20px;text-align:center;color:#5a6580;font-size:13px;background:#080c14;">Aucun membre autorise</div>
<?php else: ?>
<?php foreach ($paList as $_email => $_info): ?>
<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:#080c14;border-bottom:1px solid #121a30;"
     onmouseover="this.style.background='#0e1325'" onmouseout="this.style.background='#080c14'">
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:32px;height:32px;border-radius:50%;background:#f59e0b20;display:flex;align-items:center;justify-content:center;font-size:14px;">&#128100;</div>
        <div>
            <div style="color:#e2e8f0;font-weight:600;font-size:13px;"><?= htmlspecialchars($_email) ?></div>
            <div style="color:#5a6580;font-size:11px;">Ajoute le <?= isset($_info['added']) ? date('d/m/Y', strtotime($_info['added'])) : '—' ?></div>
        </div>
    </div>
    <?php if ($_isSA): ?>
    <form method="post" style="margin:0;" onsubmit="return confirm('Revoquer l\'acces de <?= htmlspecialchars($_email) ?> ?')">
        <input type="hidden" name="panel_action" value="revoke">
        <input type="hidden" name="email" value="<?= htmlspecialchars($_email) ?>">
        <button type="submit" style="background:#ef444420;border:1px solid #ef444440;color:#ef4444;padding:5px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;"
                onmouseover="this.style.background='#ef444440'" onmouseout="this.style.background='#ef444420'">Revoquer</button>
    </form>
    <?php else: ?>
    <span style="color:#34d399;font-size:11px;font-weight:600;">Actif</span>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Ajouter un membre (super admin uniquement) -->
<?php if ($_isSA): ?>
<div style="padding:14px 20px;background:#0a0f1c;display:flex;align-items:center;gap:10px;">
    <form method="post" style="display:flex;gap:8px;flex:1;margin:0;">
        <input type="hidden" name="panel_action" value="grant">
        <input type="email" name="email" placeholder="Email Google a autoriser..." required style="flex:1;padding:10px 14px;background:#080c14;border:1px solid #1e2a3a;border-radius:8px;color:#e2e8f0;font-size:13px;outline:none;" onfocus="this.style.borderColor='#f59e0b'" onblur="this.style.borderColor='#1e2a3a'">
        <button type="submit" style="background:#f59e0b;border:none;color:#000;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">+ Ajouter</button>
    </form>
</div>
<?php endif; ?>

</div>

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
var _ctBase = (location.hostname === 'localhost' ? '/BK' : '') + '/api/contact.php';
function _unbanIp(ip, btn) {
    if (!confirm('Debannir ' + ip + ' ?')) return;
    fetch(_ctBase + '?unban_ip=' + encodeURIComponent(ip), {credentials:'same-origin'}).then(function(r) { return r.json(); }).then(function(d) {
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

<!-- COURRIER NON CONFIRME -->
<?php if (!empty($unconfirmedMessages)): ?>
<div class="section" style="border:2px solid #ef4444;border-radius:12px;padding:16px;margin-bottom:20px;background:#ef444410;">
    <h2 style="color:#ef4444;font-size:16px;border-color:#ef444440;margin-bottom:12px;">&#9888; Courrier non confirmé <span style="background:#ef4444;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;margin-left:6px;"><?= count($unconfirmedMessages) ?></span></h2>
    <p style="color:#8b949e;font-size:12px;margin-bottom:14px;">Ces messages ont été envoyés mais l'expéditeur n'a pas cliqué sur le lien de confirmation.</p>
    <?php foreach ($unconfirmedMessages as $uc):
        $expired = strtotime($uc['expires_at']) < time();
    ?>
    <div style="background:#161b22;border:1px solid #ef444440;border-left:3px solid #ef4444;border-radius:10px;padding:14px;margin-bottom:10px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <div>
                <span style="color:#e2e8f0;font-weight:700;font-size:14px;"><?= htmlspecialchars($uc['nom'] ?: 'Anonyme') ?></span>
                <span style="color:#8b949e;font-size:12px;margin-left:8px;"><?= htmlspecialchars($uc['email']) ?></span>
                <span style="background:#ef4444;color:#fff;font-size:9px;padding:1px 6px;border-radius:4px;margin-left:6px;">NON CONFIRMÉ</span>
                <?php if ($expired): ?><span style="background:#f59e0b;color:#000;font-size:9px;padding:1px 6px;border-radius:4px;margin-left:4px;">EXPIRÉ</span><?php endif; ?>
            </div>
            <span style="color:#5a6580;font-size:11px;"><?= $uc['created_at'] ?></span>
        </div>
        <p style="color:#c9d1d9;font-size:13px;line-height:1.5;margin:0;white-space:pre-wrap;"><?= htmlspecialchars($uc['message']) ?></p>
        <div style="margin-top:8px;">
            <span class="mono" style="color:#5a6580;font-size:11px;"><?= htmlspecialchars($uc['ip']) ?></span>
            <span style="color:#5a6580;font-size:11px;margin-left:10px;">Expire : <?= $uc['expires_at'] ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

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
var _ctBase2 = (location.hostname === 'localhost' ? '/BK' : '') + '/api/contact.php';
function _deleteMsg(id, btn) {
    if (!confirm('Supprimer ce message ?')) return;
    fetch(_ctBase2 + '?delete=' + id, {credentials:'same-origin'}).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            var card = btn.closest('.contact-card');
            card.style.transition = 'opacity 0.3s';
            card.style.opacity = '0';
            setTimeout(function() { card.remove(); }, 300);
        }
    });
}
function _markRead(id, btn) {
    fetch(_ctBase2 + '?mark_read=' + id, {credentials:'same-origin'}).then(function(r) { return r.json(); }).then(function(d) {
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
        fetch(_ctBase2 + '?mark_read=' + id, {credentials:'same-origin'}).then(function(r) { return r.json(); }).then(function() {
            done++;
            if (done >= ids.length) location.reload();
        });
    });
}
</script>

<!-- ============================================================ -->
<!-- SECTION 14B : SIGNALEMENTS PROFIL -->
<!-- ============================================================ -->

<div class="section"><h2 style="color:#da3636;font-size:16px;border-color:#da363640;">&#9888; Signalements profil<?php if ($newReportsCount): ?> <span style="background:#da3636;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;margin-left:6px;animation:pulse 2s infinite;"><?= $newReportsCount ?> nouveau<?= $newReportsCount > 1 ? 'x' : '' ?></span><?php endif; ?></h2></div>

<div class="grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="card" style="border-color:#da363640;"><div class="num" style="color:#da3636;"><?= count($profileReports) ?></div><div class="label">Total signalements</div></div>
    <div class="card" style="border-color:#ef444440;"><div class="num" style="color:#ef4444;"><?= $newReportsCount ?></div><div class="label">Nouveaux</div></div>
    <div class="card" style="border-color:#f59e0b40;"><div class="num" style="color:#f59e0b;"><?= count(array_filter($profileReports, function($r) { return $r['status'] === 'read'; })) ?></div><div class="label">Lus</div></div>
    <div class="card" style="border-color:#10b98140;"><div class="num" style="color:#10b981;"><?= count(array_filter($profileReports, function($r) { return $r['status'] === 'resolved'; })) ?></div><div class="label">Resolus</div></div>
</div>

<?php
$reasonLabels = [
    'retrait' => 'Retrait profil',
    'donnees_incorrectes' => 'Donnees incorrectes',
    'usurpation' => 'Usurpation identite',
    'vie_privee' => 'Vie privee',
    'autre' => 'Autre'
];
?>

<div class="section">
    <?php if (empty($profileReports)): ?>
    <p style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucun signalement pour le moment.</p>
    <?php else: ?>

    <?php
    $rNew = array_filter($profileReports, function($r) { return $r['status'] === 'new'; });
    $rRead = array_filter($profileReports, function($r) { return $r['status'] === 'read'; });
    $rResolved = array_filter($profileReports, function($r) { return $r['status'] === 'resolved'; });
    ?>

    <?php if (!empty($rNew)): ?>
    <h4 style="color:#da3636;font-size:13px;margin-bottom:10px;">Nouveaux (<?= count($rNew) ?>)</h4>
    <?php foreach ($rNew as $rp): ?>
        <div class="report-card" data-id="<?= $rp['id_report'] ?>" style="background:#1a1f2e;border:1px solid #da363640;border-left:3px solid #da3636;border-radius:10px;padding:16px;margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div>
                    <a href="/?page=profil&id=<?= $rp['athlete_id_ext'] ?>" target="_blank" style="color:#a29bfe;font-weight:700;font-size:14px;text-decoration:none;"><?= htmlspecialchars($rp['athlete_name']) ?></a>
                    <span style="color:#8b949e;font-size:12px;margin-left:6px;">#<?= $rp['athlete_id_ext'] ?></span>
                    <span style="background:#da363630;color:#f85149;font-size:10px;padding:2px 8px;border-radius:4px;margin-left:6px;font-weight:600;"><?= htmlspecialchars($reasonLabels[$rp['reason']] ?? $rp['reason']) ?></span>
                    <span style="background:#da3636;color:#fff;font-size:10px;padding:1px 6px;border-radius:4px;margin-left:4px;">NOUVEAU</span>
                </div>
                <span style="color:#5a6580;font-size:11px;"><?= $rp['created_at'] ?></span>
            </div>
            <?php if ($rp['message']): ?>
            <p style="color:#c9d1d9;font-size:13px;line-height:1.5;margin:0 0 8px;white-space:pre-wrap;"><?= htmlspecialchars($rp['message']) ?></p>
            <?php endif; ?>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span class="mono" style="color:#5a6580;font-size:11px;"><?= htmlspecialchars($rp['ip']) ?></span>
                <?php if ($rp['email']): ?><span style="color:#8b949e;font-size:11px;"><?= htmlspecialchars($rp['email']) ?></span><?php endif; ?>
                <button onclick="_reportAction('mark_read',<?= $rp['id_report'] ?>,this)" style="background:#1e2a3a;border:1px solid #2d3a4a;color:#8b949e;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Marquer lu</button>
                <button onclick="_reportAction('resolve',<?= $rp['id_report'] ?>,this)" style="background:#10b98120;border:1px solid #10b98140;color:#10b981;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Resolu</button>
                <?php if ($rp['athlete_visible']): ?>
                <button onclick="_toggleVisibility(<?= $rp['athlete_id_ext'] ?>,0,this)" style="background:#f59e0b20;border:1px solid #f59e0b40;color:#f59e0b;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Masquer le profil</button>
                <?php else: ?>
                <button onclick="_toggleVisibility(<?= $rp['athlete_id_ext'] ?>,1,this)" style="background:#3b82f620;border:1px solid #3b82f640;color:#3b82f6;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Rendre visible</button>
                <?php endif; ?>
                <button onclick="_reportDelete(<?= $rp['id_report'] ?>,this)" style="background:#ef444420;border:1px solid #ef444440;color:#ef4444;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Supprimer</button>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($rRead)): ?>
    <h4 style="color:#f59e0b;font-size:13px;margin:20px 0 10px;">Lus (<?= count($rRead) ?>)</h4>
    <?php foreach ($rRead as $rp): ?>
        <div class="report-card" data-id="<?= $rp['id_report'] ?>" style="background:#161b22;border:1px solid #1e2a3a;border-radius:10px;padding:16px;margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div>
                    <a href="/?page=profil&id=<?= $rp['athlete_id_ext'] ?>" target="_blank" style="color:#a29bfe;font-weight:700;font-size:14px;text-decoration:none;"><?= htmlspecialchars($rp['athlete_name']) ?></a>
                    <span style="color:#8b949e;font-size:12px;margin-left:6px;">#<?= $rp['athlete_id_ext'] ?></span>
                    <span style="background:#da363630;color:#f85149;font-size:10px;padding:2px 8px;border-radius:4px;margin-left:6px;font-weight:600;"><?= htmlspecialchars($reasonLabels[$rp['reason']] ?? $rp['reason']) ?></span>
                </div>
                <span style="color:#5a6580;font-size:11px;"><?= $rp['created_at'] ?></span>
            </div>
            <?php if ($rp['message']): ?>
            <p style="color:#c9d1d9;font-size:13px;line-height:1.5;margin:0 0 8px;white-space:pre-wrap;"><?= htmlspecialchars($rp['message']) ?></p>
            <?php endif; ?>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span class="mono" style="color:#5a6580;font-size:11px;"><?= htmlspecialchars($rp['ip']) ?></span>
                <?php if ($rp['email']): ?><span style="color:#8b949e;font-size:11px;"><?= htmlspecialchars($rp['email']) ?></span><?php endif; ?>
                <button onclick="_reportAction('resolve',<?= $rp['id_report'] ?>,this)" style="background:#10b98120;border:1px solid #10b98140;color:#10b981;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Resolu</button>
                <?php if ($rp['athlete_visible']): ?>
                <button onclick="_toggleVisibility(<?= $rp['athlete_id_ext'] ?>,0,this)" style="background:#f59e0b20;border:1px solid #f59e0b40;color:#f59e0b;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Masquer le profil</button>
                <?php else: ?>
                <button onclick="_toggleVisibility(<?= $rp['athlete_id_ext'] ?>,1,this)" style="background:#3b82f620;border:1px solid #3b82f640;color:#3b82f6;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Rendre visible</button>
                <?php endif; ?>
                <button onclick="_reportDelete(<?= $rp['id_report'] ?>,this)" style="background:#ef444420;border:1px solid #ef444440;color:#ef4444;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Supprimer</button>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($rResolved)): ?>
    <h4 style="color:#10b981;font-size:13px;margin:20px 0 10px;">Resolus (<?= count($rResolved) ?>)</h4>
    <?php foreach ($rResolved as $rp): ?>
        <div class="report-card" data-id="<?= $rp['id_report'] ?>" style="background:#161b22;border:1px solid #10b98120;border-radius:10px;padding:16px;margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div>
                    <a href="/?page=profil&id=<?= $rp['athlete_id_ext'] ?>" target="_blank" style="color:#a29bfe;font-weight:700;font-size:14px;text-decoration:none;"><?= htmlspecialchars($rp['athlete_name']) ?></a>
                    <span style="color:#8b949e;font-size:12px;margin-left:6px;">#<?= $rp['athlete_id_ext'] ?></span>
                    <span style="background:#10b98130;color:#10b981;font-size:10px;padding:2px 8px;border-radius:4px;margin-left:6px;font-weight:600;">RESOLU</span>
                </div>
                <span style="color:#5a6580;font-size:11px;"><?= $rp['created_at'] ?></span>
            </div>
            <div style="margin-top:4px;display:flex;gap:8px;align-items:center;">
                <span class="mono" style="color:#5a6580;font-size:11px;"><?= htmlspecialchars($rp['ip']) ?></span>
                <?php if ($rp['athlete_visible']): ?>
                <button onclick="_toggleVisibility(<?= $rp['athlete_id_ext'] ?>,0,this)" style="background:#f59e0b20;border:1px solid #f59e0b40;color:#f59e0b;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Masquer le profil</button>
                <?php else: ?>
                <button onclick="_toggleVisibility(<?= $rp['athlete_id_ext'] ?>,1,this)" style="background:#3b82f620;border:1px solid #3b82f640;color:#3b82f6;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Rendre visible</button>
                <?php endif; ?>
                <button onclick="_reportDelete(<?= $rp['id_report'] ?>,this)" style="background:#ef444420;border:1px solid #ef444440;color:#ef4444;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Supprimer</button>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
var _rpBase = (location.hostname === 'localhost' ? '/BK' : '') + '/api/report.php';
function _reportAction(action, id, btn) {
    fetch(_rpBase + '?' + action + '=' + id, {credentials:'same-origin'}).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) location.reload();
    });
}
function _reportDelete(id, btn) {
    if (!confirm('Supprimer ce signalement ?')) return;
    fetch(_rpBase + '?delete=' + id, {credentials:'same-origin'}).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            var card = btn.closest('.report-card');
            card.style.transition = 'opacity 0.3s';
            card.style.opacity = '0';
            setTimeout(function() { card.remove(); }, 300);
        }
    });
}
function _toggleVisibility(athleteId, show, btn) {
    var action = show ? 'show_athlete' : 'hide_athlete';
    var msg = show ? 'Rendre ce profil visible ?' : 'Masquer ce profil du site ?';
    if (!confirm(msg)) return;
    fetch(_rpBase + '?' + action + '=' + athleteId, {credentials:'same-origin'}).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) location.reload();
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

<!-- ============================================================ -->
<!-- SECTION 17 : PROFILS COMPORTEMENTAUX -->
<!-- ============================================================ -->
<div class="section"><h2 style="color:#e879f9;font-size:16px;border-color:#c026d340;">&#129504; Profils comportementaux — Utilisateurs fideles (<?= count($_behaviorUsers) ?>)</h2></div>

<?php if (empty($_behaviorUsers)): ?>
<div style="padding:20px 24px;color:#5a6580;font-size:13px;">Aucun utilisateur avec plus d'une connexion.</div>
<?php else: ?>

<div style="padding:0 24px 16px;">
<?php foreach ($_behaviorUsers as $_bi => $_bd):
    $_bu = $_bd['user'];
    $uid = (int)$_bu['id_user'];
    $roleBg = ['admin'=>'#e11d4830','coach'=>'#6c5ce730','club'=>'#f59e0b30','athlete'=>'#10b98130'][$_bu['role']] ?? '#1e2a3a';
    $roleColor = ['admin'=>'#fb7185','coach'=>'#a29bfe','club'=>'#fbbf24','athlete'=>'#34d399'][$_bu['role']] ?? '#8b949e';
    $coColor = $_bd['nb_co'] >= 20 ? '#e879f9' : ($_bd['nb_co'] >= 5 ? '#a29bfe' : '#8b949e');
    $selfMatch = $_bd['self_match'];
?>
<div style="background:#111830;border:1px solid <?= $selfMatch ? '#c026d360' : '#1e2a3a' ?>;border-radius:12px;padding:20px;margin-bottom:14px;<?= $selfMatch ? 'box-shadow:0 0 12px #c026d320;' : '' ?>">
    <!-- Header user -->
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
        <?php if (!empty($_bu['picture'])): ?>
            <img src="<?= htmlspecialchars($_bu['picture']) ?>" alt="" style="width:42px;height:42px;border-radius:50%;border:2px solid <?= $coColor ?>;" referrerpolicy="no-referrer">
        <?php else: ?>
            <div style="width:42px;height:42px;border-radius:50%;background:#1e2a3a;display:flex;align-items:center;justify-content:center;font-size:18px;color:#5a6580;">&#128100;</div>
        <?php endif; ?>
        <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="color:#e2e8f0;font-weight:700;font-size:15px;"><?= htmlspecialchars(trim(($_bu['prenom'] ?? '') . ' ' . ($_bu['nom'] ?? '')) ?: '—') ?></span>
                <span style="background:<?= $roleBg ?>;color:<?= $roleColor ?>;padding:2px 10px;border-radius:10px;font-size:10px;font-weight:600;"><?= htmlspecialchars($_bu['role']) ?></span>
                <?php if ($selfMatch): ?>
                <span style="background:#c026d330;color:#e879f9;padding:2px 10px;border-radius:10px;font-size:10px;font-weight:700;">&#127939; Probablement l'athlete</span>
                <?php endif; ?>
            </div>
            <div style="color:#5a6580;font-size:11px;margin-top:2px;"><?= htmlspecialchars($_bu['email']) ?> — Inscrit le <?= $_bu['date_creation'] ? date('d/m/Y', strtotime($_bu['date_creation'])) : '?' ?></div>
        </div>
        <div style="text-align:right;">
            <div style="color:<?= $coColor ?>;font-size:28px;font-weight:800;"><?= number_format($_bd['nb_co']) ?></div>
            <div style="color:#5a6580;font-size:10px;text-transform:uppercase;letter-spacing:.5px;">connexions</div>
        </div>
    </div>

    <!-- KPI row -->
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
        <div style="background:#0c1020;border:1px solid #1e2a3a;border-radius:8px;padding:8px 14px;flex:1;min-width:80px;text-align:center;">
            <div style="color:#f59e0b;font-weight:700;font-size:16px;"><?= number_format($_bd['nb_actions']) ?></div>
            <div style="color:#5a6580;font-size:9px;text-transform:uppercase;">Actions</div>
        </div>
        <div style="background:#0c1020;border:1px solid #1e2a3a;border-radius:8px;padding:8px 14px;flex:1;min-width:80px;text-align:center;">
            <div style="color:#34d399;font-weight:700;font-size:16px;"><?= number_format($_bd['nb_search']) ?></div>
            <div style="color:#5a6580;font-size:9px;text-transform:uppercase;">Recherches</div>
        </div>
        <div style="background:#0c1020;border:1px solid #1e2a3a;border-radius:8px;padding:8px 14px;flex:1;min-width:80px;text-align:center;">
            <div style="color:#a29bfe;font-weight:700;font-size:16px;"><?= $_bd['active_days'] ?></div>
            <div style="color:#5a6580;font-size:9px;text-transform:uppercase;">Jours actifs</div>
        </div>
        <div style="background:#0c1020;border:1px solid #1e2a3a;border-radius:8px;padding:8px 14px;flex:1;min-width:80px;text-align:center;">
            <div style="color:#22d3ee;font-weight:700;font-size:16px;"><?= count($_bd['viewed_athletes']) ?></div>
            <div style="color:#5a6580;font-size:9px;text-transform:uppercase;">Profils vus</div>
        </div>
        <div style="background:#0c1020;border:1px solid #1e2a3a;border-radius:8px;padding:8px 14px;flex:1;min-width:80px;text-align:center;">
            <div style="color:#fb7185;font-weight:700;font-size:16px;"><?= count($_bd['followed_athletes']) + count($_bd['followed_clubs']) ?></div>
            <div style="color:#5a6580;font-size:9px;text-transform:uppercase;">Suivis</div>
        </div>
        <?php
            $devTotal = $_bd['devices']['mobile'] + $_bd['devices']['desktop'];
            $devIcon = $_bd['devices']['mobile'] > $_bd['devices']['desktop'] ? '&#128241;' : '&#128187;';
            $devLabel = $_bd['devices']['mobile'] > $_bd['devices']['desktop'] ? 'Mobile' : 'Desktop';
        ?>
        <div style="background:#0c1020;border:1px solid #1e2a3a;border-radius:8px;padding:8px 14px;flex:1;min-width:80px;text-align:center;">
            <div style="color:#fbbf24;font-size:16px;"><?= $devIcon ?></div>
            <div style="color:#5a6580;font-size:9px;text-transform:uppercase;"><?= $devLabel ?></div>
        </div>
    </div>

    <!-- Phrase de profil -->
    <div style="background:#0c1020;border:1px solid <?= $selfMatch ? '#c026d340' : '#1e2a3a' ?>;border-radius:8px;padding:14px 16px;margin-bottom:12px;">
        <div style="color:#c9d1d9;font-size:13px;line-height:1.6;"><?= $_bd['profile_text'] ?></div>
    </div>

    <!-- Details expandables -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if (!empty($_bd['viewed_athletes'])): ?>
        <button onclick="var d=document.getElementById('bh_ath<?= $uid ?>');d.style.display=d.style.display==='none'?'block':'none'" style="background:#1e2a3a;border:1px solid #2a3560;color:#22d3ee;padding:5px 14px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;">&#128065; Athletes vus (<?= count($_bd['viewed_athletes']) ?>)</button>
        <?php endif; ?>
        <?php if (!empty($_bd['viewed_clubs'])): ?>
        <button onclick="var d=document.getElementById('bh_cl<?= $uid ?>');d.style.display=d.style.display==='none'?'block':'none'" style="background:#1e2a3a;border:1px solid #2a3560;color:#f59e0b;padding:5px 14px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;">&#127963; Clubs vus (<?= count($_bd['viewed_clubs']) ?>)</button>
        <?php endif; ?>
        <?php if (!empty($_bd['search_queries'])): ?>
        <button onclick="var d=document.getElementById('bh_sq<?= $uid ?>');d.style.display=d.style.display==='none'?'block':'none'" style="background:#1e2a3a;border:1px solid #2a3560;color:#34d399;padding:5px 14px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;">&#128269; Recherches (<?= count($_bd['search_queries']) ?>)</button>
        <?php endif; ?>
        <?php if (!empty($_bd['top_pages'])): ?>
        <button onclick="var d=document.getElementById('bh_pg<?= $uid ?>');d.style.display=d.style.display==='none'?'block':'none'" style="background:#1e2a3a;border:1px solid #2a3560;color:#a29bfe;padding:5px 14px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;">&#128196; Pages top (<?= count($_bd['top_pages']) ?>)</button>
        <?php endif; ?>
        <?php if (!empty($_bd['followed_athletes']) || !empty($_bd['followed_clubs'])): ?>
        <button onclick="var d=document.getElementById('bh_fw<?= $uid ?>');d.style.display=d.style.display==='none'?'block':'none'" style="background:#1e2a3a;border:1px solid #2a3560;color:#fb7185;padding:5px 14px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;">&#10084; Suivis (<?= count($_bd['followed_athletes']) + count($_bd['followed_clubs']) ?>)</button>
        <?php endif; ?>
    </div>

    <!-- Athletes vus (detail) -->
    <?php if (!empty($_bd['viewed_athletes'])): ?>
    <div id="bh_ath<?= $uid ?>" style="display:none;margin-top:10px;overflow-x:auto;">
        <table style="width:100%;font-size:12px;background:#0c1020;border-radius:8px;">
            <tr><th style="color:#22d3ee;background:#0a0e18;padding:8px 10px;">Athlete</th><th style="color:#22d3ee;background:#0a0e18;padding:8px 10px;text-align:center;">Vues</th><th style="color:#22d3ee;background:#0a0e18;padding:8px 10px;">Derniere</th>
            <?php if ($selfMatch): ?><th style="color:#c026d3;background:#0a0e18;padding:8px 10px;text-align:center;">Match</th><?php endif; ?>
            </tr>
            <?php foreach ($_bd['viewed_athletes'] as $va):
                $isMatch = $selfMatch && ($va['entity_id'] ?? '') === ($selfMatch['entity_id'] ?? '---');
            ?>
            <tr style="<?= $isMatch ? 'background:#c026d315;' : '' ?>">
                <td style="padding:6px 10px;color:#c9d1d9;font-weight:<?= $isMatch ? '700' : '400' ?>;">
                    <?php if ($va['entity_id']): ?><a href="../?page=profil&id=<?= (int)$va['entity_id'] ?>" target="_blank" style="color:<?= $isMatch ? '#e879f9' : '#c9d1d9' ?>;text-decoration:none;"><?= htmlspecialchars($va['entity_name']) ?></a>
                    <?php else: ?><?= htmlspecialchars($va['entity_name']) ?><?php endif; ?>
                </td>
                <td style="padding:6px 10px;text-align:center;color:#f59e0b;font-weight:700;"><?= $va['nb'] ?></td>
                <td style="padding:6px 10px;color:#5a6580;font-size:11px;"><?= $va['last_view'] ? date('d/m H:i', strtotime($va['last_view'])) : '—' ?></td>
                <?php if ($selfMatch): ?>
                <td style="padding:6px 10px;text-align:center;"><?= $isMatch ? '<span style="color:#e879f9;font-weight:700;">&#127939; OUI</span>' : '' ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- Clubs vus (detail) -->
    <?php if (!empty($_bd['viewed_clubs'])): ?>
    <div id="bh_cl<?= $uid ?>" style="display:none;margin-top:10px;overflow-x:auto;">
        <table style="width:100%;font-size:12px;background:#0c1020;border-radius:8px;">
            <tr><th style="color:#f59e0b;background:#0a0e18;padding:8px 10px;">Club</th><th style="color:#f59e0b;background:#0a0e18;padding:8px 10px;text-align:center;">Vues</th></tr>
            <?php foreach ($_bd['viewed_clubs'] as $vc): ?>
            <tr><td style="padding:6px 10px;color:#c9d1d9;"><?= htmlspecialchars($vc['entity_name']) ?></td><td style="padding:6px 10px;text-align:center;color:#f59e0b;font-weight:700;"><?= $vc['nb'] ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- Recherches (detail) -->
    <?php if (!empty($_bd['search_queries'])): ?>
    <div id="bh_sq<?= $uid ?>" style="display:none;margin-top:10px;overflow-x:auto;">
        <table style="width:100%;font-size:12px;background:#0c1020;border-radius:8px;">
            <tr><th style="color:#34d399;background:#0a0e18;padding:8px 10px;">Recherche</th><th style="color:#34d399;background:#0a0e18;padding:8px 10px;">Type</th><th style="color:#34d399;background:#0a0e18;padding:8px 10px;text-align:center;">Nb</th></tr>
            <?php foreach ($_bd['search_queries'] as $sq):
                $typeBg = ['athlete'=>'#10b98130','club'=>'#f59e0b30','epreuve'=>'#6c5ce730','ville'=>'#0891b230','general'=>'#1e2a3a'][$sq['search_type']] ?? '#1e2a3a';
                $typeColor = ['athlete'=>'#34d399','club'=>'#fbbf24','epreuve'=>'#a29bfe','ville'=>'#22d3ee','general'=>'#8b949e'][$sq['search_type']] ?? '#8b949e';
            ?>
            <tr><td style="padding:6px 10px;color:#c9d1d9;"><?= htmlspecialchars($sq['query_text']) ?></td>
                <td style="padding:6px 10px;"><span style="background:<?= $typeBg ?>;color:<?= $typeColor ?>;padding:2px 8px;border-radius:8px;font-size:10px;font-weight:600;"><?= htmlspecialchars($sq['search_type']) ?></span></td>
                <td style="padding:6px 10px;text-align:center;color:#34d399;font-weight:700;"><?= $sq['nb'] ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- Pages top (detail) -->
    <?php if (!empty($_bd['top_pages'])): ?>
    <div id="bh_pg<?= $uid ?>" style="display:none;margin-top:10px;overflow-x:auto;">
        <table style="width:100%;font-size:12px;background:#0c1020;border-radius:8px;">
            <tr><th style="color:#a29bfe;background:#0a0e18;padding:8px 10px;">Page</th><th style="color:#a29bfe;background:#0a0e18;padding:8px 10px;text-align:center;">Visites</th></tr>
            <?php foreach ($_bd['top_pages'] as $tp):
                $pgLabel = $tp['page'];
                if (preg_match('/page=([^&]+)/', $pgLabel, $m)) {
                    $pgLabel = $m[1];
                    if (preg_match('/id=(\d+)/', $tp['page'], $m2)) $pgLabel .= ' #' . $m2[1];
                    if (preg_match('/open=([^&]+)/', $tp['page'], $m3)) $pgLabel .= ' — ' . urldecode($m3[1]);
                }
            ?>
            <tr><td style="padding:6px 10px;"><a href="https://bokonzi.com/<?= htmlspecialchars($tp['page']) ?>" target="_blank" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($pgLabel) ?></a></td>
                <td style="padding:6px 10px;text-align:center;color:#a29bfe;font-weight:700;"><?= $tp['c'] ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- Suivis (detail) -->
    <?php if (!empty($_bd['followed_athletes']) || !empty($_bd['followed_clubs'])): ?>
    <div id="bh_fw<?= $uid ?>" style="display:none;margin-top:10px;overflow-x:auto;">
        <table style="width:100%;font-size:12px;background:#0c1020;border-radius:8px;">
            <tr><th style="color:#fb7185;background:#0a0e18;padding:8px 10px;">Nom</th><th style="color:#fb7185;background:#0a0e18;padding:8px 10px;">Type</th></tr>
            <?php foreach ($_bd['followed_athletes'] as $fa): ?>
            <tr><td style="padding:6px 10px;color:#c9d1d9;"><?= htmlspecialchars($fa['nom_athlete'] ?? 'Athlete #' . $fa['athlete_id_ext']) ?></td>
                <td style="padding:6px 10px;"><span style="background:#10b98130;color:#34d399;padding:2px 8px;border-radius:8px;font-size:10px;font-weight:600;">athlete</span></td></tr>
            <?php endforeach; ?>
            <?php foreach ($_bd['followed_clubs'] as $fc): ?>
            <tr><td style="padding:6px 10px;color:#c9d1d9;"><?= htmlspecialchars($fc['nom_club'] ?? 'Club #' . $fc['club_id']) ?></td>
                <td style="padding:6px 10px;"><span style="background:#f59e0b30;color:#fbbf24;padding:2px 8px;border-radius:8px;font-size:10px;font-weight:600;">club</span></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

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
    fetch((location.hostname === 'localhost' ? '/BK' : '') + '/api/top_searched.php?reset=' + type + '&bk_key=bk_s3cr3t_2026_xK9mP', {credentials:'same-origin'}).then(function(r) { return r.json(); }).then(function(d) {
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
<?php $conn->close(); ?>
