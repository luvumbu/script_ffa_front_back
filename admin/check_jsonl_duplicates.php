<?php
/**
 * check_jsonl_duplicates.php — Scanne tous les .jsonl de archives/ et detecte
 * les doublons par cle primaire (PK simple ou composite).
 *
 * Sortie : rapport HTML (acces web) ou texte (CLI).
 *
 * Acces :
 *   - Web : admin/check_jsonl_duplicates.php?bk_key=...  (necessite la cle API ou cookie SA)
 *   - CLI : php admin/check_jsonl_duplicates.php
 */

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    require_once __DIR__ . '/../api/config.php';
    $providedKey = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
    $hasSaCookie = !empty($_COOKIE['bk_sa_token']);
    if ($providedKey !== BK_API_KEY && !$hasSaCookie) {
        http_response_code(403);
        exit('Forbidden');
    }
} else {
    require_once __DIR__ . '/../core/credentials.php';
    require_once __DIR__ . '/../core/db.php';
}

@ini_set('memory_limit', '1024M');
@set_time_limit(0);

$archiveDir = __DIR__ . '/../archives';
$files = glob($archiveDir . '/*.jsonl');
sort($files);

// Cache pour les PK lookup en BDD (pour les fichiers META incomplets)
$pkCacheBdd = [];
function getPkFromBdd($conn, $table, &$cache) {
    if (isset($cache[$table])) return $cache[$table];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return $cache[$table] = [];
    $res = @$conn->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
    if (!$res) return $cache[$table] = [];
    $cols = [];
    while ($r = $res->fetch_assoc()) $cols[] = $r['Column_name'];
    return $cache[$table] = $cols;
}

// Scanne un fichier et retourne le rapport
function scanFile($file, $conn, &$pkCacheBdd) {
    $name = basename($file);
    $size = filesize($file);

    $fp = fopen($file, 'r');
    if (!$fp) {
        return ['name' => $name, 'status' => 'error', 'message' => 'Impossible d\'ouvrir le fichier'];
    }

    $firstLine = fgets($fp);
    if (strpos($firstLine, '#META ') !== 0) {
        fclose($fp);
        return ['name' => $name, 'status' => 'error', 'message' => 'Pas d\'entete #META'];
    }
    $meta = json_decode(substr($firstLine, 6), true);
    if (!$meta || !isset($meta['table'])) {
        fclose($fp);
        return ['name' => $name, 'status' => 'error', 'message' => 'META illisible'];
    }

    $table = $meta['table'];

    // Detecte la PK : 1) depuis create_sql META, 2) depuis BDD, 3) premiere colonne
    $pkCols = [];
    if (!empty($meta['create_sql']) && preg_match('/PRIMARY KEY\s*\(([^)]+)\)/i', $meta['create_sql'], $m)) {
        preg_match_all('/`([^`]+)`/', $m[1], $cm);
        $pkCols = $cm[1];
    }
    if (empty($pkCols)) {
        $pkCols = getPkFromBdd($conn, $table, $pkCacheBdd);
    }
    if (empty($pkCols) && !empty($meta['columns'])) {
        $pkCols = [$meta['columns'][0]]; // fallback : 1ere colonne
    }
    if (empty($pkCols)) {
        fclose($fp);
        return ['name' => $name, 'status' => 'error', 'message' => 'Pas de PK detectable', 'table' => $table];
    }

    // Stream et comptage
    $seen = [];
    $totalRows = 0;
    $invalidLines = 0;
    $dupSamples = []; // 5 premieres PK dupliquees

    while (($line = fgets($fp)) !== false) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $row = json_decode($line, true);
        if (!is_array($row)) { $invalidLines++; continue; }
        $totalRows++;

        $keyParts = [];
        foreach ($pkCols as $c) $keyParts[] = (string)($row[$c] ?? 'NULL');
        $key = count($pkCols) === 1 ? $keyParts[0] : implode('|', $keyParts);

        if (isset($seen[$key])) {
            $seen[$key]++;
            if (count($dupSamples) < 5 && !in_array($key, $dupSamples, true)) {
                $dupSamples[] = $key;
            }
        } else {
            $seen[$key] = 1;
        }
    }
    fclose($fp);

    $uniqueRows = count($seen);
    $dupCount = $totalRows - $uniqueRows;

    return [
        'name' => $name,
        'table' => $table,
        'size_mb' => round($size / 1048576, 2),
        'total' => $totalRows,
        'unique' => $uniqueRows,
        'duplicates' => $dupCount,
        'invalid' => $invalidLines,
        'pk' => implode(' + ', $pkCols),
        'samples' => $dupSamples,
        'status' => $dupCount > 0 ? 'dup' : ($invalidLines > 0 ? 'warn' : 'ok'),
    ];
}

// ───────────────────────── SCAN ALL ─────────────────────────
$startTotal = microtime(true);
$reports = [];

if ($isCli) {
    echo "Scan de " . count($files) . " fichiers...\n\n";
}

foreach ($files as $idx => $file) {
    $r = scanFile($file, $conn, $pkCacheBdd);
    $reports[] = $r;
    if ($isCli) {
        $st = strtoupper($r['status']);
        echo sprintf("[%2d/%d] %-55s [%s]\n", $idx + 1, count($files), substr($r['name'], 0, 53), $st);
        if (($r['duplicates'] ?? 0) > 0) {
            echo "       Doublons: " . $r['duplicates'] . " (exemples: " . implode(', ', $r['samples']) . ")\n";
        }
    }
}

$elapsedTotal = round(microtime(true) - $startTotal, 1);
$totalFiles = count($reports);
$filesWithDups = 0;
$totalDups = 0;
$filesWithError = 0;
foreach ($reports as $r) {
    if (($r['duplicates'] ?? 0) > 0) { $filesWithDups++; $totalDups += $r['duplicates']; }
    if ($r['status'] === 'error') $filesWithError++;
}

// ───────────────────────── SORTIE CLI ─────────────────────────
if ($isCli) {
    echo "\n========================================\n";
    echo "RESUME : $totalFiles fichiers en {$elapsedTotal}s\n";
    echo "Fichiers avec doublons : $filesWithDups\n";
    echo "Total doublons         : $totalDups\n";
    echo "Erreurs                : $filesWithError\n";
    exit;
}

// ───────────────────────── SORTIE HTML ─────────────────────────
?><!DOCTYPE html>
<html lang="fr"><head>
<meta charset="utf-8">
<title>Verification doublons archives — BOKONZI</title>
<style>
* { box-sizing: border-box; }
body { background: #0d1117; color: #c9d1d9; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 24px; }
h1 { color: #f0f6fc; margin: 0 0 8px; font-size: 22px; }
.sub { color: #8b949e; font-size: 13px; margin-bottom: 24px; }
.summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
.card { background: #161b22; border: 1px solid #30363d; border-radius: 10px; padding: 16px; }
.card .lbl { color: #8b949e; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; }
.card .val { color: #f0f6fc; font-size: 24px; font-weight: 700; margin-top: 4px; }
.card.ok .val { color: #34d399; }
.card.warn .val { color: #fbbf24; }
.card.bad .val { color: #ef4444; }
.banner { padding: 16px 20px; border-radius: 10px; font-size: 15px; font-weight: 600; margin-bottom: 24px; }
.banner.ok { background: #10b98115; border: 1px solid #10b98140; color: #34d399; }
.banner.bad { background: #ef444415; border: 1px solid #ef444440; color: #fca5a5; }
table { width: 100%; border-collapse: collapse; background: #161b22; border-radius: 10px; overflow: hidden; }
th { background: #1c2330; color: #c9d1d9; font-weight: 600; font-size: 12px; text-align: left; padding: 10px 12px; border-bottom: 1px solid #30363d; text-transform: uppercase; letter-spacing: 0.04em; }
td { padding: 10px 12px; border-bottom: 1px solid #21262d; font-size: 13px; }
tr:last-child td { border-bottom: none; }
tr:hover { background: #1c2330; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge.ok { background: #10b98120; color: #34d399; border: 1px solid #10b98140; }
.badge.warn { background: #fbbf2420; color: #fbbf24; border: 1px solid #fbbf2440; }
.badge.dup { background: #ef444420; color: #fca5a5; border: 1px solid #ef444440; }
.badge.error { background: #6b7280; color: #d1d5db; border: 1px solid #6b728060; }
.samples { color: #8b949e; font-size: 11px; font-family: 'Courier New', monospace; }
.t-right { text-align: right; }
.btn-back { display: inline-block; padding: 8px 16px; background: #1e2a3a; border: 1px solid #2a3560; border-radius: 6px; color: #a29bfe; text-decoration: none; font-size: 13px; margin-bottom: 20px; }
.btn-back:hover { background: #2a3560; }
</style>
</head><body>

<a class="btn-back" href="panel.php">&larr; Retour au panel</a>

<h1>Verification doublons — Archives JSONL</h1>
<div class="sub">Scan complet de <?= $totalFiles ?> fichier<?= $totalFiles > 1 ? 's' : '' ?> dans <code>archives/</code> &middot; Duree : <?= $elapsedTotal ?>s</div>

<?php if ($filesWithDups === 0 && $filesWithError === 0): ?>
<div class="banner ok">[OK] AUCUN DOUBLON detecte dans toutes les archives. Tes donnees sont saines.</div>
<?php elseif ($filesWithDups > 0): ?>
<div class="banner bad">[ALERTE] <?= $filesWithDups ?> fichier<?= $filesWithDups > 1 ? 's' : '' ?> contiennent des doublons (<?= number_format($totalDups, 0, ',', ' ') ?> doublon<?= $totalDups > 1 ? 's' : '' ?> au total). Voir details ci-dessous.</div>
<?php else: ?>
<div class="banner warn" style="background:#fbbf2415;border:1px solid #fbbf2440;color:#fbbf24;"><?= $filesWithError ?> fichier<?= $filesWithError > 1 ? 's' : '' ?> non scannable<?= $filesWithError > 1 ? 's' : '' ?> (voir details). Pas de doublons trouves sur les autres.</div>
<?php endif; ?>

<div class="summary">
    <div class="card ok"><div class="lbl">Fichiers scannes</div><div class="val"><?= $totalFiles ?></div></div>
    <div class="card <?= $filesWithDups === 0 ? 'ok' : 'bad' ?>"><div class="lbl">Avec doublons</div><div class="val"><?= $filesWithDups ?></div></div>
    <div class="card <?= $totalDups === 0 ? 'ok' : 'bad' ?>"><div class="lbl">Total doublons</div><div class="val"><?= number_format($totalDups, 0, ',', ' ') ?></div></div>
    <div class="card <?= $filesWithError === 0 ? 'ok' : 'warn' ?>"><div class="lbl">Erreurs</div><div class="val"><?= $filesWithError ?></div></div>
</div>

<table>
    <thead>
        <tr>
            <th>Fichier</th>
            <th>Table</th>
            <th class="t-right">Taille</th>
            <th class="t-right">Lignes</th>
            <th class="t-right">Uniques</th>
            <th class="t-right">Doublons</th>
            <th>PK</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($reports as $r): ?>
        <?php
            $st = $r['status'] ?? 'error';
            $stLabel = ['ok' => 'OK', 'dup' => 'DOUBLONS', 'warn' => 'WARN', 'error' => 'ERREUR'][$st] ?? 'OK';
        ?>
        <tr>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= htmlspecialchars($r['table'] ?? '—') ?></td>
            <td class="t-right"><?= isset($r['size_mb']) ? $r['size_mb'] . ' MB' : '—' ?></td>
            <td class="t-right"><?= isset($r['total']) ? number_format($r['total'], 0, ',', ' ') : '—' ?></td>
            <td class="t-right"><?= isset($r['unique']) ? number_format($r['unique'], 0, ',', ' ') : '—' ?></td>
            <td class="t-right" style="<?= ($r['duplicates'] ?? 0) > 0 ? 'color:#fca5a5;font-weight:700;' : '' ?>">
                <?= isset($r['duplicates']) ? number_format($r['duplicates'], 0, ',', ' ') : '—' ?>
            </td>
            <td><code style="font-size:11px;color:#8b949e;"><?= htmlspecialchars($r['pk'] ?? '—') ?></code></td>
            <td><span class="badge <?= $st ?>"><?= $stLabel ?></span></td>
        </tr>
        <?php if (!empty($r['samples'])): ?>
        <tr><td colspan="8" class="samples">&nbsp;&nbsp;Exemples PK dupliquees : <?= implode(' &middot; ', array_map('htmlspecialchars', $r['samples'])) ?></td></tr>
        <?php endif; ?>
        <?php if ($st === 'error' && !empty($r['message'])): ?>
        <tr><td colspan="8" class="samples" style="color:#fca5a5;">&nbsp;&nbsp;Erreur : <?= htmlspecialchars($r['message']) ?></td></tr>
        <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<p style="color:#8b949e;font-size:12px;margin-top:24px;">
    Logique : pour chaque fichier, on extrait la cle primaire (depuis l'entete <code>#META.create_sql</code>,
    sinon depuis la BDD via <code>SHOW KEYS</code>, sinon la premiere colonne), puis on stream toutes les lignes
    et on compte combien ont la meme valeur de PK. Si <b>Lignes &gt; Uniques</b>, il y a doublon.
</p>

</body></html>
