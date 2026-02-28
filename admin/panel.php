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

// ============================================================
// DATA COLLECTION
// ============================================================

// === STATS BDD ===
$stats = [];
$tables = ['athletes', 'clubs', 'epreuves', 'villes', 'competitions', 'users', 'user_sessions', 'logs',
           'athlete_records', 'athlete_resultats', 'athlete_medailles', 'athlete_podiums', 'athlete_selections',
           'athlete_progressions', 'athlete_niveaux', 'athlete_clubs', 'athlete_follows', 'club_follows', 'email_subscribers'];

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

        @media (max-width: 900px) {
            .cols-2, .cols-3 { grid-template-columns: 1fr; }
            .grid { grid-template-columns: repeat(2, 1fr); }
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
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['page']) ?>"><?= htmlspecialchars($log['page']) ?></td>
            <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['detail']) ?>"><?= htmlspecialchars(mb_substr($log['detail'], 0, 60)) ?></td>
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
<!-- SECTION 12 : ACTIONS RAPIDES -->
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
    <a href="setup_bdd.php" class="btn">Setup BDD</a>
    <a href="reset.php" class="btn btn-danger">Reset</a>
    <a href="drop_all.php" class="btn btn-danger">Drop tables</a>
</div>

<div style="text-align:center;padding:30px;color:#2d3a4a;font-size:11px;">
    Super Admin Panel — Bokonzi — <?= date('Y') ?>
</div>

</body>
</html>
