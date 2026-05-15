<?php
/**
 * admin/db_size.php — Diagnostic taille BDD
 * Usage local : http://localhost/BK/admin/db_size.php?bk_key=bk_s3cr3t_2026_xK9mP
 * Usage prod  : https://bokonzi.com/admin/db_size.php?bk_key=bk_s3cr3t_2026_xK9mP
 * Optionnel : &json=1 pour sortie JSON brute
 */

$key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') {
    http_response_code(403);
    die('Interdit');
}

require_once __DIR__ . '/../core/db.php';

$dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];

$sql = "SELECT
            table_name,
            table_rows,
            data_length,
            index_length,
            (data_length + index_length) AS total_size,
            ROUND(data_length / 1024 / 1024, 2) AS data_mb,
            ROUND(index_length / 1024 / 1024, 2) AS index_mb,
            ROUND((data_length + index_length) / 1024 / 1024, 2) AS total_mb,
            engine
        FROM information_schema.tables
        WHERE table_schema = '$dbName'
        ORDER BY (data_length + index_length) DESC";

$res = $conn->query($sql);

$tables = [];
$totalBytes = 0;
$totalRows = 0;
while ($row = $res->fetch_assoc()) {
    $tables[] = $row;
    $totalBytes += (int)$row['total_size'];
    $totalRows += (int)$row['table_rows'];
}

$totalMb = round($totalBytes / 1024 / 1024, 2);

if (isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'database' => $dbName,
        'total_size_mb' => $totalMb,
        'total_rows' => $totalRows,
        'tables_count' => count($tables),
        'tables' => $tables,
    ], JSON_PRETTY_PRINT);
    exit;
}

function fmt($n) { return number_format((float)$n, 0, ',', ' '); }
function fmtMb($n) { return number_format((float)$n, 2, ',', ' ') . ' MB'; }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>BDD Size — <?= htmlspecialchars($dbName) ?></title>
<style>
  body { font-family: -apple-system, sans-serif; background: #0d1117; color: #c9d1d9; padding: 20px; }
  h1 { color: #fff; margin: 0 0 8px; }
  .meta { color: #8b949e; margin-bottom: 20px; }
  .kpi { display: inline-block; background: #161b22; padding: 12px 18px; border-radius: 8px; margin-right: 10px; }
  .kpi b { color: #ffd700; font-size: 18px; }
  table { width: 100%; border-collapse: collapse; margin-top: 18px; background: #161b22; border-radius: 8px; overflow: hidden; }
  th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #30363d; }
  th { background: #1f2937; color: #ffd700; font-weight: 600; }
  td.num { text-align: right; font-variant-numeric: tabular-nums; }
  tr:hover { background: #21262d; }
  .bar { display: inline-block; height: 12px; background: linear-gradient(90deg, #6c5ce7, #fb7185); border-radius: 2px; vertical-align: middle; margin-right: 6px; }
  .warn { color: #fb7185; font-weight: bold; }
  .ok { color: #22d3ee; }
</style>
</head>
<body>

<h1>Diagnostic BDD : <?= htmlspecialchars($dbName) ?></h1>
<div class="meta">Genere le <?= date('Y-m-d H:i:s') ?></div>

<div class="kpi">Taille totale : <b><?= fmtMb($totalMb) ?></b></div>
<div class="kpi">Lignes totales : <b><?= fmt($totalRows) ?></b></div>
<div class="kpi">Tables : <b><?= count($tables) ?></b></div>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Table</th>
      <th class="num">Lignes</th>
      <th class="num">Data</th>
      <th class="num">Index</th>
      <th class="num">Total</th>
      <th>% total</th>
      <th>Engine</th>
    </tr>
  </thead>
  <tbody>
<?php $i = 0; foreach ($tables as $t):
    $i++;
    $pct = $totalBytes > 0 ? round($t['total_size'] / $totalBytes * 100, 1) : 0;
    $barW = max(2, (int)$pct * 4);
    $rowClass = $pct >= 10 ? 'warn' : '';
?>
    <tr>
      <td><?= $i ?></td>
      <td class="<?= $rowClass ?>"><?= htmlspecialchars($t['table_name']) ?></td>
      <td class="num"><?= fmt($t['table_rows']) ?></td>
      <td class="num"><?= fmtMb($t['data_mb']) ?></td>
      <td class="num"><?= fmtMb($t['index_mb']) ?></td>
      <td class="num"><b><?= fmtMb($t['total_mb']) ?></b></td>
      <td><span class="bar" style="width:<?= $barW ?>px"></span><?= $pct ?> %</td>
      <td><?= htmlspecialchars($t['engine']) ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>

<p style="margin-top:20px; color:#8b949e; font-size:12px;">
  Note : <code>table_rows</code> est estime par MySQL pour les tables InnoDB. Pour un compte exact, utiliser <code>SELECT COUNT(*)</code>.<br>
  Sortie JSON : <a style="color:#22d3ee" href="?bk_key=<?= htmlspecialchars($key) ?>&json=1">?json=1</a>
</p>

</body>
</html>
