<?php
/**
 * cleanup_duplicates.php — Supprime les doublons de nom_et_liens par BATCH
 *
 * Strategie :
 *   1. Optionnel : ajouter un INDEX simple (NON UNIQUE) sur url → joins x100 plus rapides
 *   2. DELETE par lots de 500 lignes max → evite timeout Hostinger
 *   3. Auto-refresh tant qu'il reste des doublons
 *
 * Compatible avec doublons autorises a l'insertion (pas de UNIQUE INDEX).
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once dirname(__DIR__) . '/core/db.php';

$stateDir = __DIR__ . '/state';
if (!is_dir($stateDir)) @mkdir($stateDir, 0755, true);
$flagFile     = $stateDir . '/cleanup_running.flag';
$progressFile = $stateDir . '/cleanup_progress.json';

$batchSize    = 500;        // lignes supprimees par requete DELETE
$maxSeconds   = 18;         // duree max d'un cycle (sous timeout Hostinger 30s)
$pauseUs      = 100000;     // 0.1s entre 2 DELETE pour laisser respirer la BDD

$message = null;
$messageType = null;

// =============================================================
// Detection de l'index sur 'url'
// =============================================================
$indexUrl = null;
$r = $conn->query("SHOW INDEX FROM nom_et_liens WHERE Column_name = 'url'");
if ($r && $r->num_rows > 0) $indexUrl = $r->fetch_assoc();

// =============================================================
// Action : ajouter l'INDEX (non unique)
// =============================================================
if (($_POST['action'] ?? '') === 'add_index') {
    $t0 = microtime(true);
    // INDEX prefix sur 191 chars (limite InnoDB pour utf8mb4)
    $ok = $conn->query("ALTER TABLE nom_et_liens ADD INDEX idx_url (url(191))");
    $duree = (int) round((microtime(true) - $t0) * 1000);
    if ($ok) {
        $message = "INDEX cree sur url(191) en {$duree}ms. Le cleanup sera maintenant tres rapide.";
        $messageType = 'ok';
    } else {
        $message = "Echec ajout INDEX : " . htmlspecialchars($conn->error);
        $messageType = 'bad';
    }
    // Refresh la detection
    $r = $conn->query("SHOW INDEX FROM nom_et_liens WHERE Column_name = 'url'");
    if ($r && $r->num_rows > 0) $indexUrl = $r->fetch_assoc();
}

// =============================================================
// Action : DEMARRER le cleanup (cree le flag + reset progress)
// =============================================================
if (($_POST['action'] ?? '') === 'cleanup_start') {
    file_put_contents($flagFile, date('Y-m-d H:i:s'));
    file_put_contents($progressFile, json_encode([
        'started_at' => date('Y-m-d H:i:s'),
        'total_deleted' => 0,
        'cycles' => 0,
    ]));
    header("Location: cleanup_duplicates.php");
    exit;
}

// =============================================================
// Action : ARRETER le cleanup
// =============================================================
if (($_POST['action'] ?? '') === 'cleanup_stop') {
    if (file_exists($flagFile)) @unlink($flagFile);
    header("Location: cleanup_duplicates.php");
    exit;
}

// =============================================================
// Action : EFFACER l'etat
// =============================================================
if (($_POST['action'] ?? '') === 'reset') {
    if (file_exists($flagFile))     @unlink($flagFile);
    if (file_exists($progressFile)) @unlink($progressFile);
    header("Location: cleanup_duplicates.php");
    exit;
}

// =============================================================
// Si flag present : EXECUTER UN CYCLE de cleanup
// =============================================================
$cycleResult = null;
if (file_exists($flagFile)) {
    $progress = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) : ['total_deleted' => 0, 'cycles' => 0];
    $cycleStart = microtime(true);
    $cycleDeleted = 0;
    $batches = 0;

    $sql = "DELETE n1 FROM nom_et_liens n1
            INNER JOIN nom_et_liens n2
              ON n1.url = n2.url
             AND n1.id_nom_et_liens > n2.id_nom_et_liens
            LIMIT $batchSize";

    while ((microtime(true) - $cycleStart) < $maxSeconds) {
        $ok = $conn->query($sql);
        if (!$ok) {
            $progress['error'] = $conn->error;
            break;
        }
        $aff = $conn->affected_rows;
        if ($aff === 0) {
            // Plus rien a supprimer → fini
            $progress['finished_at'] = date('Y-m-d H:i:s');
            @unlink($flagFile);
            break;
        }
        $cycleDeleted += $aff;
        $batches++;
        usleep($pauseUs);
    }

    $progress['total_deleted'] += $cycleDeleted;
    $progress['cycles']++;
    $progress['last_cycle_deleted'] = $cycleDeleted;
    $progress['last_cycle_batches'] = $batches;
    $progress['last_cycle_duree_s'] = round(microtime(true) - $cycleStart, 1);
    $progress['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents($progressFile, json_encode($progress));

    $cycleResult = $progress;

    // Auto-refresh tant que le flag existe (= encore des doublons a supprimer)
    if (file_exists($flagFile)) {
        header("Refresh: 2");
    }
}

// =============================================================
// Etat actuel (toujours calcule)
// =============================================================
$totalLignes  = (int) $conn->query("SELECT COUNT(*) c FROM nom_et_liens")->fetch_assoc()['c'];
$urlsUniques  = (int) $conn->query("SELECT COUNT(DISTINCT url) c FROM nom_et_liens")->fetch_assoc()['c'];
$doublons     = $totalLignes - $urlsUniques;
$cleanupRunning = file_exists($flagFile);
$lastProgress = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>nom_et_liens — Nettoyage doublons (batch)</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #0d1117; color: #c9d1d9; margin: 0; padding: 24px; }
        h1 { color: #a78bfa; font-size: 22px; margin: 0 0 4px; }
        h2 { color: #60a5fa; font-size: 15px; margin: 22px 0 8px; padding-bottom: 6px; border-bottom: 1px solid #1f2937; }
        .sub { color: #8b949e; font-size: 13px; margin-bottom: 22px; }
        .card { background: #161b22; border: 1px solid #1f2937; border-radius: 8px; padding: 16px; margin-bottom: 12px; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 14px; }
        .stat { background: #161b22; border: 1px solid #1f2937; border-radius: 8px; padding: 14px; text-align: center; }
        .stat .v { font-size: 26px; font-weight: 700; }
        .stat .l { font-size: 11px; color: #8b949e; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }
        .stat.total .v  { color: #fff; }
        .stat.uniq .v   { color: #34d399; }
        .stat.dupes .v  { color: #f87171; }
        .stat.zero .v   { color: #34d399; }
        .ok-box { background: #022c22; border-left: 3px solid #34d399; padding: 12px; border-radius: 4px; margin: 8px 0; color: #6ee7b7; }
        .bad-box { background: #450a0a; border-left: 3px solid #f87171; padding: 12px; border-radius: 4px; margin: 8px 0; color: #fca5a5; }
        .warn-box { background: #422006; border-left: 3px solid #fbbf24; padding: 12px; border-radius: 4px; margin: 8px 0; color: #fde68a; }
        .danger { background: #450a0a; border: 2px solid #f87171; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .danger h3 { color: #f87171; margin: 0 0 8px; font-size: 14px; }
        .running { background: #1e3a8a30; border: 2px solid #60a5fa; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .running h3 { color: #60a5fa; margin: 0 0 8px; font-size: 14px; }
        .btn { background: #6366f1; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #ef4444; }
        .btn-warn { background: #d97706; }
        .btn-warn:hover { background: #f59e0b; }
        .btn-ok { background: #16a34a; }
        .btn-back { background: #374151; }
        .nav { display: flex; gap: 8px; margin-bottom: 16px; }
        .small { font-size: 11px; color: #6b7280; }
        .progress-bar { background: #0a0e15; border-radius: 6px; height: 24px; overflow: hidden; border: 1px solid #1f2937; margin: 8px 0; }
        .progress-fill { background: linear-gradient(90deg,#dc2626,#f87171); height: 100%; transition: width 0.5s; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 12px; min-width: 50px; }
    </style>
</head>
<body>

<h1>Nettoyage doublons — nom_et_liens</h1>
<div class="sub">Suppression par BATCH (500 lignes/requete) + auto-refresh pour eviter timeout Hostinger.</div>

<div class="nav">
    <a href="index.php" class="btn btn-back" style="text-decoration:none;">&larr; Index</a>
    <a href="test_page.php" class="btn btn-back" style="text-decoration:none;">Test parser</a>
    <a href="diagnose.php" class="btn btn-back" style="text-decoration:none;">Diagnose</a>
    <a href="cleanup_duplicates.php" class="btn btn-back" style="text-decoration:none;">Recharger</a>
</div>

<?php if ($message): ?>
    <div class="<?= $messageType === 'ok' ? 'ok-box' : 'bad-box' ?>"><?= $message ?></div>
<?php endif; ?>

<!-- ============== INDEX OPTIMISATION ============== -->
<?php if (!$indexUrl): ?>
    <div class="warn-box">
        <strong>Performance : aucun INDEX sur la colonne <code>url</code></strong>.
        Le DELETE sans index est <strong>tres lent</strong> sur grosse table (timeout 503 garantis &gt;100k lignes).
        Recommandation : creer un INDEX simple (non UNIQUE, donc doublons toujours autorises).
        <form method="POST" style="margin-top:8px;">
            <input type="hidden" name="action" value="add_index">
            <button type="submit" class="btn btn-warn">Creer INDEX idx_url (recommande)</button>
        </form>
    </div>
<?php else: ?>
    <div class="ok-box small">
        INDEX present : <code><?= htmlspecialchars($indexUrl['Key_name']) ?></code>
        (<?= $indexUrl['Non_unique'] ? 'non unique' : 'unique' ?>) — DELETE sera rapide.
    </div>
<?php endif; ?>

<h2>Etat actuel</h2>
<div class="stats">
    <div class="stat total">
        <div class="v"><?= number_format($totalLignes, 0, ',', ' ') ?></div>
        <div class="l">Total lignes</div>
    </div>
    <div class="stat uniq">
        <div class="v"><?= number_format($urlsUniques, 0, ',', ' ') ?></div>
        <div class="l">URLs uniques</div>
    </div>
    <div class="stat <?= $doublons === 0 ? 'zero' : 'dupes' ?>">
        <div class="v"><?= number_format($doublons, 0, ',', ' ') ?></div>
        <div class="l">Doublons a supprimer</div>
    </div>
</div>

<?php if ($cleanupRunning): ?>
    <!-- ============== EN COURS ============== -->
    <div class="running">
        <h3>NETTOYAGE EN COURS — auto-refresh 2s</h3>
        <?php if ($cycleResult): ?>
            <?php
            $totalDel = (int)($cycleResult['total_deleted'] ?? 0);
            $estStart = $totalDel + $doublons;
            $pct = $estStart > 0 ? round(($totalDel / $estStart) * 100, 1) : 0;
            ?>
            <div style="font-size:13px;margin-bottom:8px;">
                Total supprime : <strong style="color:#f87171;font-size:18px;"><?= number_format($totalDel, 0, ',', ' ') ?></strong>
                / Reste : <strong><?= number_format($doublons, 0, ',', ' ') ?></strong>
                / Cycles : <?= $cycleResult['cycles'] ?>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $pct ?>%"><?= $pct ?>%</div>
            </div>
            <div class="small">
                Dernier cycle : <?= number_format($cycleResult['last_cycle_deleted'] ?? 0, 0, ',', ' ') ?> lignes
                en <?= $cycleResult['last_cycle_batches'] ?? 0 ?> batches (<?= $cycleResult['last_cycle_duree_s'] ?? 0 ?>s)
            </div>
            <?php if (!empty($cycleResult['error'])): ?>
                <div class="bad-box small"><strong>Erreur :</strong> <?= htmlspecialchars($cycleResult['error']) ?></div>
            <?php endif; ?>
        <?php endif; ?>
        <form method="POST" style="margin-top:10px;">
            <input type="hidden" name="action" value="cleanup_stop">
            <button type="submit" class="btn btn-danger">ARRETER</button>
        </form>
    </div>

<?php elseif ($doublons === 0): ?>
    <!-- ============== AUCUN DOUBLON ============== -->
    <div class="ok-box">
        <strong>Aucun doublon detecte.</strong>
        La table contient <?= number_format($totalLignes, 0, ',', ' ') ?> URLs uniques. Rien a nettoyer.
    </div>
    <?php if ($lastProgress && !empty($lastProgress['finished_at'])): ?>
        <div class="card small">
            Dernier cleanup termine : <?= htmlspecialchars($lastProgress['finished_at']) ?>
            (<?= number_format($lastProgress['total_deleted'] ?? 0, 0, ',', ' ') ?> doublons supprimes)
            <form method="POST" style="margin-top:8px;">
                <input type="hidden" name="action" value="reset">
                <button type="submit" class="btn btn-back">Effacer la progression</button>
            </form>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- ============== PRET A LANCER ============== -->
    <div class="danger">
        <h3>Suppression par batch</h3>
        <p style="font-size:13px;color:#fde68a;">
            Va supprimer <strong><?= number_format($doublons, 0, ',', ' ') ?> lignes</strong>
            par lots de <?= $batchSize ?>. Auto-refresh toutes les 2s.
            Garde la plus ancienne pour chaque URL. <strong>Action irreversible.</strong>
        </p>
        <?php if (!$indexUrl): ?>
            <p style="font-size:12px;color:#fbbf24;">
                <strong>Conseil :</strong> cree d'abord l'INDEX au-dessus, ou ce sera tres lent et risque de timeout.
            </p>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('Demarrer le cleanup de <?= $doublons ?> doublons par batch ?');">
            <input type="hidden" name="action" value="cleanup_start">
            <button type="submit" class="btn btn-danger">Demarrer cleanup batch</button>
        </form>
    </div>
<?php endif; ?>

<div style="margin-top:30px;color:#6b7280;font-size:11px;text-align:center;">
    Batch <?= $batchSize ?> lignes / <?= $maxSeconds ?>s par cycle / pause <?= $pauseUs/1000 ?>ms entre lots
</div>

</body>
</html>
