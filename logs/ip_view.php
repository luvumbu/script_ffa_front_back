<?php
/**
 * logs/ip_view.php — Viewer distant des logs IP universels
 *
 * Acces : https://bokonzi.com/logs/ip_view.php
 * Auth : whitelist email (meme que admin/logs.php)
 * Params : ?month=2026-02, ?ip=X, ?raw=1 (JSON brut)
 */
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/ip_logger.php';

// === AUTH ===
$allowedEmails = ['luvumbu.n@gmail.com'];
$user = getCurrentUser($conn);
if (!$user || !in_array($user['email'], $allowedEmails, true)) {
    header('HTTP/1.0 403 Forbidden');
    die('Acces interdit. <a href="../login.php">Connexion</a>');
}

// === PARAMS ===
$month = $_GET['month'] ?? date('Y-m');
$filterIp = trim($_GET['ip'] ?? '');
$raw = isset($_GET['raw']);

// === LIRE LE LOG ===
$file = ipLogFile($month);
$data = readIpLog($file);
$months = listIpLogFiles();

// === MODE RAW JSON ===
if ($raw) {
    header('Content-Type: application/json; charset=UTF-8');
    // Filtrer par IP si demande
    if ($filterIp && isset($data['ips'])) {
        $filtered = array_filter($data['ips'], function($k) use ($filterIp) {
            return strpos($k, $filterIp) !== false;
        }, ARRAY_FILTER_USE_KEY);
        echo json_encode([
            'month' => $month,
            'filter_ip' => $filterIp,
            'results' => count($filtered),
            'ips' => $filtered
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        // Retirer ips_seen des daily (interne)
        $output = $data;
        foreach ($output['daily'] as &$d) { unset($d['ips_seen']); }
        echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}

// === Trier IPs par count DESC ===
$ips = $data['ips'] ?? [];
uasort($ips, function($a, $b) { return $b['count'] - $a['count']; });

// Filtrer par IP si demande
if ($filterIp) {
    $ips = array_filter($ips, function($k) use ($filterIp) {
        return strpos($k, $filterIp) !== false;
    }, ARRAY_FILTER_USE_KEY);
}

// Daily stats (retirer ips_seen pour l'affichage)
$daily = $data['daily'] ?? [];
krsort($daily);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>IP Tracker — Bokonzi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0d1117; color: #c9d1d9; font-family: 'Segoe UI', system-ui, sans-serif; padding: 20px; }
        h1 { color: #6c5ce7; margin-bottom: 20px; font-size: 22px; }
        h2 { color: #8b949e; font-size: 16px; margin: 24px 0 12px; }
        a { color: #6c5ce7; text-decoration: none; }
        a:hover { text-decoration: underline; }

        .stats { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
        .stat-card {
            background: #161b22; border: 1px solid #1e2a3a; border-radius: 10px;
            padding: 16px 24px; min-width: 160px;
        }
        .stat-card .num { font-size: 28px; font-weight: 800; color: #55efc4; }
        .stat-card .label { font-size: 12px; color: #5a6580; margin-top: 4px; }

        .filters {
            background: #161b22; border: 1px solid #1e2a3a; border-radius: 10px;
            padding: 16px; margin-bottom: 20px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
        }
        .filters select, .filters input {
            background: #0d1117; color: #c9d1d9; border: 1px solid #2d3a4a;
            border-radius: 6px; padding: 8px 12px; font-size: 13px;
        }
        .filters button {
            background: #6c5ce7; color: #fff; border: none; border-radius: 6px;
            padding: 8px 16px; cursor: pointer; font-size: 13px; font-weight: 600;
        }
        .filters button:hover { background: #5a4bd1; }

        table { width: 100%; border-collapse: collapse; background: #161b22; border-radius: 10px; overflow: hidden; }
        th { background: #1a2035; color: #8b949e; font-size: 11px; text-transform: uppercase; padding: 10px 12px; text-align: left; }
        td { padding: 10px 12px; border-bottom: 1px solid #1e2a3a08; font-size: 13px; }
        tr:hover { background: #ffffff06; }
        .ip { color: #f59e0b; font-family: monospace; }
        .count { color: #55efc4; font-weight: 700; }
        .date { color: #5a6580; font-size: 12px; }
        .pages { color: #8b949e; font-size: 11px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }

        .daily-chart { display: flex; gap: 4px; align-items: flex-end; height: 100px; margin: 12px 0; }
        .daily-bar {
            flex: 1; min-width: 20px; background: #6c5ce7; border-radius: 4px 4px 0 0;
            position: relative; cursor: pointer; transition: background 0.2s;
        }
        .daily-bar:hover { background: #8b7cf7; }
        .daily-bar .tip {
            display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
            background: #1a2035; border: 1px solid #2d3a4a; border-radius: 6px; padding: 6px 10px;
            font-size: 11px; white-space: nowrap; z-index: 10;
        }
        .daily-bar:hover .tip { display: block; }

        .back { margin-bottom: 16px; display: inline-block; }
    </style>
</head>
<body>

<a href="../admin/logs.php" class="back">&larr; Dashboard logs</a>
<h1>IP Tracker — <?= htmlspecialchars($month) ?></h1>

<!-- STATS -->
<div class="stats">
    <div class="stat-card">
        <div class="num"><?= number_format($data['total_visits'] ?? 0) ?></div>
        <div class="label">Visites totales</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= number_format($data['unique_ips'] ?? 0) ?></div>
        <div class="label">IPs uniques</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= count($daily) ?></div>
        <div class="label">Jours actifs</div>
    </div>
    <div class="stat-card">
        <div class="num" style="font-size:14px;color:#8b949e;"><?= htmlspecialchars($data['last_update'] ?? '-') ?></div>
        <div class="label">Derniere visite</div>
    </div>
</div>

<!-- FILTRES -->
<div class="filters">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <label style="color:#5a6580;font-size:12px;">Mois</label>
        <select name="month" onchange="this.form.submit()">
            <?php foreach ($months as $m): ?>
            <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= $m ?></option>
            <?php endforeach; ?>
            <?php if (empty($months)): ?>
            <option value="<?= date('Y-m') ?>"><?= date('Y-m') ?></option>
            <?php endif; ?>
        </select>
        <label style="color:#5a6580;font-size:12px;">IP</label>
        <input type="text" name="ip" value="<?= htmlspecialchars($filterIp) ?>" placeholder="Filtrer par IP...">
        <button type="submit">Filtrer</button>
        <?php if ($filterIp): ?>
        <a href="?month=<?= $month ?>" style="color:#ef4444;font-size:12px;">Effacer filtre</a>
        <?php endif; ?>
        <a href="?month=<?= $month ?>&raw=1" style="margin-left:auto;font-size:12px;" target="_blank">JSON brut</a>
    </form>
</div>

<!-- GRAPHIQUE DAILY -->
<?php if (!empty($daily)):
    $maxVisits = max(array_column($daily, 'visits'));
    $last14 = array_slice($daily, 0, 14, true);
    $last14 = array_reverse($last14, true);
?>
<h2>Activite des 14 derniers jours</h2>
<div class="daily-chart">
    <?php foreach ($last14 as $date => $d):
        $h = $maxVisits > 0 ? max(4, round(($d['visits'] / $maxVisits) * 100)) : 4;
    ?>
    <div class="daily-bar" style="height:<?= $h ?>px;">
        <div class="tip"><?= $date ?><br><?= $d['visits'] ?> visites / <?= $d['unique'] ?> IPs</div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- DERNIÈRES REQUÊTES (temps réel) -->
<?php
$lastReqs = array_reverse($data['last_requests'] ?? []);
?>
<h2>Dernières requêtes (<?= count($lastReqs) ?>)</h2>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Heure</th>
            <th>IP</th>
            <th>Page</th>
            <th>URL complète</th>
            <th>Méthode</th>
            <th>Referrer</th>
            <th>User Agent</th>
        </tr>
    </thead>
    <tbody>
    <?php $i = 0; foreach ($lastReqs as $req): $i++; if ($i > 200) break; ?>
        <tr>
            <td><?= $i ?></td>
            <td style="color:#55efc4;font-family:monospace;white-space:nowrap;"><?= htmlspecialchars($req['time']) ?></td>
            <td class="ip"><?= htmlspecialchars($req['ip']) ?></td>
            <td><?= htmlspecialchars($req['page']) ?></td>
            <td class="pages" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($req['url'] ?? '') ?>"><?= htmlspecialchars($req['url'] ?? '') ?></td>
            <td><span class="badge" style="background:<?= ($req['method'] ?? 'GET') === 'POST' ? '#f59e0b30;color:#f59e0b' : '#6c5ce720;color:#a29bfe' ?>;"><?= htmlspecialchars($req['method'] ?? 'GET') ?></span></td>
            <td class="pages" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($req['referrer'] ?? '') ?>"><?= htmlspecialchars($req['referrer'] ?? '-') ?></td>
            <td class="pages" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($req['ua_short'] ?? '') ?>"><?= htmlspecialchars($req['ua_short'] ?? '-') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- TABLE IPS -->
<h2>Top IPs (<?= count($ips) ?>)</h2>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>IP</th>
            <th>Visites</th>
            <th>Premiere visite</th>
            <th>Derniere visite</th>
            <th>Dernières requêtes</th>
            <th>User Agent</th>
        </tr>
    </thead>
    <tbody>
    <?php $i = 0; foreach ($ips as $ip => $info): $i++; if ($i > 200) break;
        $reqs = array_reverse($info['requests'] ?? []);
    ?>
        <tr>
            <td><?= $i ?></td>
            <td class="ip"><?= htmlspecialchars($ip) ?></td>
            <td class="count"><?= number_format($info['count']) ?></td>
            <td class="date"><?= htmlspecialchars($info['first']) ?></td>
            <td class="date"><?= htmlspecialchars($info['last']) ?></td>
            <td class="pages">
                <?php foreach (array_slice($reqs, 0, 5) as $r): ?>
                <div style="margin:2px 0;"><span style="color:#55efc4;font-family:monospace;font-size:11px;"><?= htmlspecialchars(substr($r['time'], 11)) ?></span> <span style="color:#8b949e;"><?= htmlspecialchars($r['page']) ?></span></div>
                <?php endforeach; ?>
                <?php if (count($reqs) > 5): ?>
                <div style="color:#5a6580;font-size:11px;">+<?= count($reqs) - 5 ?> autres</div>
                <?php endif; ?>
            </td>
            <td class="pages" title="<?= htmlspecialchars($info['ua'] ?? '') ?>"><?= htmlspecialchars(mb_substr($info['ua'] ?? '', 0, 60)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- TABLE DAILY -->
<h2>Stats par jour</h2>
<table>
    <thead><tr><th>Date</th><th>Visites</th><th>IPs uniques</th></tr></thead>
    <tbody>
    <?php foreach ($daily as $date => $d): ?>
        <tr>
            <td><?= htmlspecialchars($date) ?></td>
            <td class="count"><?= number_format($d['visits']) ?></td>
            <td><?= $d['unique'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
