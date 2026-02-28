<?php
/**
 * admin/logs.php — Visualisation des logs de visite
 * Requiert le role admin
 */
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

// Acces restreint par email
$allowedEmails = ['luvumbu.n@gmail.com'];
$user = getCurrentUser($conn);
if (!$user || !in_array($user['email'], $allowedEmails, true)) {
    header('Location: ../login.php');
    exit;
}

// ============ FILTRES ============
$filterDate   = $_GET['date'] ?? date('Y-m-d');
$filterAction = $_GET['action'] ?? '';
$filterIp     = trim($_GET['ip'] ?? '');
$filterPage   = trim($_GET['page_filter'] ?? '');
$filterSid    = trim($_GET['sid'] ?? '');
$limit        = min(500, max(1, (int)($_GET['limit'] ?? 100)));
$page         = max(1, (int)($_GET['p'] ?? 1));
$offset       = ($page - 1) * $limit;

// Tri
$allowedSort = ['ts', 'ip', 'action', 'page', 'uname', 'sid', 'duration_ms'];
$sort = in_array($_GET['sort'] ?? '', $allowedSort, true) ? $_GET['sort'] : 'ts';
$dir  = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

// Build WHERE
$where = [];
if ($filterDate !== '' && $filterDate !== 'all') {
    $dateEsc = $conn->real_escape_string(preg_replace('/[^0-9\-]/', '', $filterDate));
    $where[] = "DATE(ts) = '$dateEsc'";
}
if ($filterAction !== '') {
    $where[] = "action = '" . $conn->real_escape_string($filterAction) . "'";
}
if ($filterIp !== '') {
    $where[] = "ip = '" . $conn->real_escape_string($filterIp) . "'";
}
if ($filterPage !== '') {
    $where[] = "page LIKE '%" . $conn->real_escape_string($filterPage) . "%'";
}
if ($filterSid !== '') {
    $where[] = "sid = '" . $conn->real_escape_string($filterSid) . "'";
}
$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ============ STATS ============
$total = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM `logs` $whereSQL");
if ($res) $total = (int)$res->fetch_assoc()['cnt'];

$totalSessions = 0;
$res = $conn->query("SELECT COUNT(DISTINCT sid) as cnt FROM `logs` $whereSQL");
if ($res) $totalSessions = (int)$res->fetch_assoc()['cnt'];

$totalIps = 0;
$res = $conn->query("SELECT COUNT(DISTINCT ip) as cnt FROM `logs` $whereSQL");
if ($res) $totalIps = (int)$res->fetch_assoc()['cnt'];

$pageViews = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM `logs` $whereSQL" . ($whereSQL ? " AND " : " WHERE ") . "action='page_view'");
if ($res) $pageViews = (int)$res->fetch_assoc()['cnt'];

// Stats actions
$actionsStats = [];
$res = $conn->query("SELECT action, COUNT(*) as cnt FROM `logs` $whereSQL GROUP BY action ORDER BY cnt DESC");
if ($res) while ($r = $res->fetch_assoc()) $actionsStats[$r['action']] = (int)$r['cnt'];

// Top IPs
$topIps = [];
$res = $conn->query("SELECT ip, COUNT(*) as cnt FROM `logs` $whereSQL GROUP BY ip ORDER BY cnt DESC LIMIT 10");
if ($res) while ($r = $res->fetch_assoc()) $topIps[$r['ip']] = (int)$r['cnt'];

// Top pages
$topPages = [];
$res = $conn->query("SELECT page, COUNT(*) as cnt FROM `logs` $whereSQL GROUP BY page ORDER BY cnt DESC LIMIT 10");
if ($res) while ($r = $res->fetch_assoc()) $topPages[$r['page']] = (int)$r['cnt'];

// ============ ENTRIES ============
$entries = [];
$res = $conn->query("SELECT * FROM `logs` $whereSQL ORDER BY `$sort` $dir LIMIT $limit OFFSET $offset");
if ($res) while ($row = $res->fetch_assoc()) $entries[] = $row;

$totalPages = max(1, ceil($total / $limit));

// ============ HELPERS ============
function actionColor($action) {
    $map = [
        'page_view'    => ['bg' => '#55efc420', 'border' => '#55efc450', 'color' => '#55efc4'],
        'click_link'   => ['bg' => '#a29bfe20', 'border' => '#a29bfe50', 'color' => '#a29bfe'],
        'click_button' => ['bg' => '#74b9ff20', 'border' => '#74b9ff50', 'color' => '#74b9ff'],
        'form_submit'  => ['bg' => '#ffeaa720', 'border' => '#ffeaa750', 'color' => '#ffeaa7'],
        'input_change' => ['bg' => '#fdcb6e20', 'border' => '#fdcb6e50', 'color' => '#fdcb6e'],
        'page_leave'   => ['bg' => '#636e7220', 'border' => '#636e7250', 'color' => '#b2bec3'],
        'js_error'     => ['bg' => '#ff767520', 'border' => '#ff767550', 'color' => '#ff7675'],
        'navigation'   => ['bg' => '#00cec920', 'border' => '#00cec950', 'color' => '#00cec9'],
        'copy'         => ['bg' => '#fd79a820', 'border' => '#fd79a850', 'color' => '#fd79a8'],
    ];
    return $map[$action] ?? ['bg' => '#5a658020', 'border' => '#5a658050', 'color' => '#7c85a0'];
}

function buildUrl($overrides) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    unset($params['p']); // reset page si filtre change
    if (isset($overrides['p'])) $params['p'] = $overrides['p'];
    return '?' . http_build_query($params);
}

function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function sortHeader($col, $label) {
    global $sort, $dir;
    $newDir = ($sort === $col && $dir === 'DESC') ? 'asc' : 'desc';
    $arrow = '';
    if ($sort === $col) $arrow = $dir === 'ASC' ? ' ▲' : ' ▼';
    $url = buildUrl(['sort' => $col, 'dir' => $newDir, 'p' => null]);
    return '<a href="' . $url . '" style="color:inherit;text-decoration:none;">' . $label . $arrow . '</a>';
}

function truncate($s, $max) {
    $s = (string)$s;
    return mb_strlen($s) > $max ? mb_substr($s, 0, $max) . '...' : $s;
}

$allActions = ['page_view','click_link','click_button','form_submit','input_change','copy','page_leave','js_error','navigation'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs — Bokonzi Admin</title>
    <link rel="stylesheet" href="../dashboard.css">
    <style>
        body { padding-top: 0; }
        .admin-header {
            background: linear-gradient(180deg, #10162b 0%, #0b1020 100%);
            border-bottom: 1px solid #1a2540;
            padding: 14px 25px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .admin-header h1 { font-size: 20px; margin: 0; flex: 1; }
        .admin-header a { color: #5a6580; text-decoration: none; font-size: 13px; transition: color 0.2s; }
        .admin-header a:hover { color: #a29bfe; }
        .bk-table th { top: 52px; }
        .bk-table td { padding: 12px 16px; }
        .bk-table th { padding: 12px 16px; }
        .action-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .bar-chart { margin: 4px 0; }
        .bar-row { display: flex; align-items: center; gap: 8px; margin: 4px 0; font-size: 12px; }
        .bar-label { min-width: 100px; color: #7c85a0; text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bar-fill { height: 18px; border-radius: 4px; min-width: 2px; transition: width 0.3s; }
        .bar-count { color: #5a6580; font-size: 11px; min-width: 35px; }
        .filter-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .filter-row label { color: #5a6580; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .active-filters { margin: 10px 0 0; display: flex; gap: 6px; flex-wrap: wrap; }
        .active-filter {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 20px; font-size: 11px;
            background: #6c5ce720; border: 1px solid #6c5ce740; color: #a29bfe;
        }
        .active-filter a { color: #ff7675; text-decoration: none; font-weight: 700; }
        .sid-short { font-family: 'Courier New', monospace; font-size: 11px; color: #74b9ff; }
        .ip-link { color: #55efc4 !important; font-family: 'Courier New', monospace; font-size: 12px; }
        .user-badge { color: #fdcb6e; font-size: 12px; }
        .duration { color: #b2bec3; font-family: 'Courier New', monospace; font-size: 12px; }
        @media (max-width: 900px) {
            .charts-row { grid-template-columns: 1fr; }
            .hide-mobile { display: none; }
        }
    </style>
</head>
<body>

<div class="admin-header">
    <h1>Logs</h1>
    <a href="../index.php">Retour au site</a>
</div>

<div class="container">

<!-- STAT CARDS -->
<div class="grid">
    <div class="card accent-purple">
        <div class="num"><?= number_format($total, 0, ',', ' ') ?></div>
        <div class="label">Evenements</div>
    </div>
    <div class="card accent-green">
        <div class="num"><?= number_format($totalSessions, 0, ',', ' ') ?></div>
        <div class="label">Sessions</div>
    </div>
    <div class="card accent-amber">
        <div class="num"><?= number_format($totalIps, 0, ',', ' ') ?></div>
        <div class="label">IPs uniques</div>
    </div>
    <div class="card accent-rose">
        <div class="num"><?= number_format($pageViews, 0, ',', ' ') ?></div>
        <div class="label">Pages vues</div>
    </div>
</div>

<!-- FILTRES -->
<form method="GET" class="search-box">
    <div class="filter-row">
        <label>Date</label>
        <input type="date" name="date" value="<?= esc($filterDate) ?>">
        <label>Action</label>
        <select name="action">
            <option value="">Toutes</option>
            <?php foreach ($allActions as $a): ?>
            <option value="<?= $a ?>" <?= $filterAction === $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
        </select>
        <label>IP</label>
        <input type="text" name="ip" value="<?= esc($filterIp) ?>" placeholder="ex: 192.168.1.1" style="width:140px;">
        <label>Page</label>
        <input type="text" name="page_filter" value="<?= esc($filterPage) ?>" placeholder="ex: profil" style="width:120px;">
        <label>Session</label>
        <input type="text" name="sid" value="<?= esc($filterSid) ?>" placeholder="ID session" style="width:120px;">
        <button type="submit" class="btn">Filtrer</button>
        <?php if (!empty($where)): ?>
        <a href="?" style="color:#ff7675;font-size:13px;text-decoration:none;">Reinitialiser</a>
        <?php endif; ?>
    </div>
    <?php
    $activeFilters = [];
    if ($filterDate && $filterDate !== 'all') $activeFilters[] = ['label' => 'Date: '.$filterDate, 'key' => 'date'];
    if ($filterAction) $activeFilters[] = ['label' => 'Action: '.$filterAction, 'key' => 'action'];
    if ($filterIp) $activeFilters[] = ['label' => 'IP: '.$filterIp, 'key' => 'ip'];
    if ($filterPage) $activeFilters[] = ['label' => 'Page: '.$filterPage, 'key' => 'page_filter'];
    if ($filterSid) $activeFilters[] = ['label' => 'Session: '.substr($filterSid,0,8), 'key' => 'sid'];
    if (!empty($activeFilters)): ?>
    <div class="active-filters">
        <?php foreach ($activeFilters as $af): ?>
        <span class="active-filter"><?= esc($af['label']) ?> <a href="<?= buildUrl([$af['key'] => null]) ?>">&times;</a></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</form>

<!-- GRAPHIQUES CSS -->
<div class="charts-row">
    <!-- Repartition actions -->
    <div class="chart-card">
        <h3>Actions</h3>
        <div class="bar-chart">
        <?php
        $maxAction = !empty($actionsStats) ? max($actionsStats) : 1;
        foreach ($actionsStats as $act => $cnt):
            $pct = round($cnt / $maxAction * 100);
            $c = actionColor($act);
        ?>
        <div class="bar-row">
            <span class="bar-label"><?= esc($act) ?></span>
            <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $c['color'] ?>30;border:1px solid <?= $c['color'] ?>50;"></div>
            <span class="bar-count"><?= number_format($cnt, 0, ',', ' ') ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Top IPs -->
    <div class="chart-card">
        <h3>Top IPs</h3>
        <div class="bar-chart">
        <?php
        $maxIp = !empty($topIps) ? max($topIps) : 1;
        foreach ($topIps as $ip => $cnt):
            $pct = round($cnt / $maxIp * 100);
        ?>
        <div class="bar-row">
            <span class="bar-label"><a href="<?= buildUrl(['ip' => $ip, 'p' => null]) ?>" class="ip-link"><?= esc($ip) ?></a></span>
            <div class="bar-fill" style="width:<?= $pct ?>%;background:#55efc430;border:1px solid #55efc450;"></div>
            <span class="bar-count"><?= number_format($cnt, 0, ',', ' ') ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Top Pages -->
<?php if (!empty($topPages)): ?>
<div class="chart-card" style="margin-bottom:20px;">
    <h3>Top Pages</h3>
    <div class="bar-chart">
    <?php
    $maxPg = max($topPages);
    foreach ($topPages as $pg => $cnt):
        $pct = round($cnt / $maxPg * 100);
    ?>
    <div class="bar-row">
        <?php $fullUrl = 'https://bokonzi.com/' . ltrim($pg, '/'); ?>
        <span class="bar-label" style="min-width:350px;max-width:500px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= esc($fullUrl) ?>"><a href="<?= esc($fullUrl) ?>" target="_blank" style="color:#a29bfe;text-decoration:none;font-size:11px;"><?= esc($fullUrl) ?></a></span>
        <div class="bar-fill" style="width:<?= $pct ?>%;background:#a29bfe30;border:1px solid #a29bfe50;"></div>
        <span class="bar-count"><?= number_format($cnt, 0, ',', ' ') ?></span>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- TABLEAU PRINCIPAL -->
<h2>Evenements (<?= number_format($total, 0, ',', ' ') ?>)</h2>

<?php if (empty($entries)): ?>
<div style="padding:40px;text-align:center;color:#5a6580;">Aucun log pour ces filtres.</div>
<?php else: ?>

<div class="table-wrap" style="overflow-x:auto;">
<table class="bk-table">
<thead>
<tr>
    <th>#</th>
    <th><?= sortHeader('ts', 'Date') ?></th>
    <th><?= sortHeader('ip', 'IP') ?></th>
    <th><?= sortHeader('action', 'Action') ?></th>
    <th><?= sortHeader('page', 'Page') ?></th>
    <th>Detail</th>
    <th><?= sortHeader('uname', 'User') ?></th>
    <th class="hide-mobile"><?= sortHeader('sid', 'Session') ?></th>
    <th class="hide-mobile">Ecran</th>
    <th class="hide-mobile"><?= sortHeader('duration_ms', 'Duree') ?></th>
</tr>
</thead>
<tbody>
<?php foreach ($entries as $e):
    $c = actionColor($e['action']);
    $ts = date('d/m H:i:s', strtotime($e['ts']));
    $dur = $e['duration_ms'] ? round((int)$e['duration_ms'] / 1000, 1) . 's' : '';
?>
<tr>
    <td style="color:#5a6580;font-size:11px;"><?= (int)$e['id_log'] ?></td>
    <td style="white-space:nowrap;font-size:12px;color:#7c85a0;"><?= $ts ?></td>
    <td><a href="<?= buildUrl(['ip' => $e['ip'], 'p' => null]) ?>" class="ip-link"><?= esc($e['ip']) ?></a></td>
    <td><span class="action-badge" style="background:<?= $c['bg'] ?>;border:1px solid <?= $c['border'] ?>;color:<?= $c['color'] ?>;"><?= esc($e['action']) ?></span></td>
    <?php $fullPg = 'https://bokonzi.com/' . ltrim($e['page'], '/'); ?>
    <td title="<?= esc($fullPg) ?>"><a href="<?= esc($fullPg) ?>" target="_blank" style="color:#a29bfe;text-decoration:none;"><?= esc($fullPg) ?></a></td>
    <td title="<?= esc($e['detail']) ?>" style="color:#7c85a0;font-size:12px;"><?= esc(truncate($e['detail'], 80)) ?></td>
    <td><?= $e['uname'] ? '<span class="user-badge">' . esc($e['uname']) . '</span>' : '<span style="color:#3a4050;">—</span>' ?></td>
    <td class="hide-mobile"><?php if ($e['sid']): ?><a href="<?= buildUrl(['sid' => $e['sid'], 'p' => null]) ?>" class="sid-short" title="<?= esc($e['sid']) ?>"><?= esc(substr($e['sid'], 0, 8)) ?></a><?php else: ?>—<?php endif; ?></td>
    <td class="hide-mobile" style="color:#5a6580;font-size:11px;"><?= esc($e['screen']) ?></td>
    <td class="hide-mobile"><?= $dur ? '<span class="duration">' . $dur . '</span>' : '' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- PAGINATION -->
<?php if ($totalPages > 1): ?>
<div class="pager">
    <?php if ($page > 1): ?>
    <a href="<?= buildUrl(['p' => $page - 1]) ?>">Precedent</a>
    <?php endif; ?>
    <?php
    $start = max(1, $page - 3);
    $end = min($totalPages, $page + 3);
    if ($start > 1): ?><a href="<?= buildUrl(['p' => 1]) ?>">1</a><?php if ($start > 2): ?><span class="info">...</span><?php endif; endif;
    for ($i = $start; $i <= $end; $i++):
        if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= buildUrl(['p' => $i]) ?>"><?= $i ?></a>
        <?php endif;
    endfor;
    if ($end < $totalPages): if ($end < $totalPages - 1): ?><span class="info">...</span><?php endif; ?><a href="<?= buildUrl(['p' => $totalPages]) ?>"><?= $totalPages ?></a><?php endif; ?>
    <?php if ($page < $totalPages): ?>
    <a href="<?= buildUrl(['p' => $page + 1]) ?>">Suivant</a>
    <?php endif; ?>
    <span class="info">Page <?= $page ?>/<?= $totalPages ?></span>
</div>
<?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
