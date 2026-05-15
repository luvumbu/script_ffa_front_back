<?php
/**
 * jarvis.php — Dashboard data style HUD futuriste
 *
 * Affiche en temps reel :
 *   - Compteurs nom_et_liens (total, uniques, doublons)
 *   - Avancement scraping (gauge circulaire)
 *   - Console log des dernieres URLs scrapees
 *   - Status systeme (runner ON/OFF)
 *
 * Auto-refresh toutes les 5s (compatible anti-DDoS Hostinger).
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once dirname(__DIR__) . '/core/db.php';

$stateDir     = __DIR__ . '/state';
$flagFile     = $stateDir . '/scraper_v2_running.flag';
$progressFile = $stateDir . '/scraper_v2_progress.json';

// =========================================================================
// Recolte des donnees
// =========================================================================
$totalLignes  = (int) $conn->query("SELECT COUNT(*) c FROM nom_et_liens")->fetch_assoc()['c'];
$urlsUniques  = (int) $conn->query("SELECT COUNT(DISTINCT url) c FROM nom_et_liens")->fetch_assoc()['c'];
$doublons     = $totalLignes - $urlsUniques;
$totalAthletes = 0;
$r = $conn->query("SELECT COUNT(*) c FROM athletes");
if ($r) $totalAthletes = (int) $r->fetch_assoc()['c'];

$runnerRunning = file_exists($flagFile);
$progress = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) : null;

$pctAvancement = 0;
$urlsCurrent = 0;
$urlsTotalRunner = 0;
$insertsRunner = 0;
$tableActive = '';
if ($progress && !empty($progress['stats'])) {
    $st = $progress['stats'];
    if (!empty($st['pages_total']) && $st['pages_total'] > 0) {
        $pctAvancement = round(($st['pages_traitees'] / $st['pages_total']) * 100, 1);
    }
    $urlsCurrent = (int)($st['urls_terminees'] ?? 0);
    $urlsTotalRunner = (int)($st['urls_total'] ?? 0);
    $insertsRunner = (int)($st['athletes_inserts'] ?? 0);
    $tableActive = $progress['table'] ?? '';
}

// Dernieres URLs ajoutees dans nom_et_liens (pour console log)
$lastUrls = [];
$r = $conn->query("SELECT id_nom_et_liens, url FROM nom_et_liens ORDER BY id_nom_et_liens DESC LIMIT 12");
if ($r) while ($row = $r->fetch_assoc()) $lastUrls[] = $row;

// Auto-refresh si runner en cours
if ($runnerRunning) {
    header("Refresh: 5");
}

// Cercle SVG : circumference = 2 * pi * 100 = 628.3
$ringCircumference = 628.3;
$ringOffset = $ringCircumference - ($ringCircumference * $pctAvancement / 100);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>JARVIS DATA // scraping_v2</title>
    <link rel="stylesheet" href="styles/jarvis.css">
</head>
<body>

<!-- ============== HEADER HUD ============== -->
<header class="hud-header">
    <div>
        <div class="hud-title">JARVIS // SCRAPING_V2</div>
        <div class="hud-subtitle">data acquisition system &mdash; athle.fr feed</div>
    </div>
    <div style="text-align:center;">
        <div class="hud-clock" id="hud-clock"><?= date('H:i:s') ?></div>
        <div class="hud-subtitle"><?= date('l d F Y') ?></div>
    </div>
    <div>
        <span class="hud-status-pill <?= $runnerRunning ? 'alert' : '' ?>">
            <span class="dot"></span>
            <?= $runnerRunning ? 'RUNNING' : 'STANDBY' ?>
        </span>
    </div>
</header>

<!-- ============== NAV ============== -->
<nav class="hud-nav">
    <a href="index.php">Runner</a>
    <a href="cleanup_duplicates.php">Cleanup</a>
    <a href="test_page.php">Parser test</a>
    <a href="diagnose.php">Diagnose</a>
    <a href="jarvis.php" class="active">Dashboard</a>
</nav>

<!-- ============== ROW 1 : 4 TILES ============== -->
<section class="hud-main">
    <div class="tile">
        <div class="tile-label">nom_et_liens // total</div>
        <div class="tile-value"><?= number_format($totalLignes, 0, ',', ' ') ?></div>
        <div class="tile-trend">all rows including duplicates</div>
    </div>
    <div class="tile">
        <div class="tile-label">unique urls</div>
        <div class="tile-value ok"><?= number_format($urlsUniques, 0, ',', ' ') ?></div>
        <div class="tile-trend">distinct url count</div>
    </div>
    <div class="tile">
        <div class="tile-label">duplicates pending</div>
        <div class="tile-value <?= $doublons > 0 ? 'alert' : 'ok' ?>"><?= number_format($doublons, 0, ',', ' ') ?></div>
        <div class="tile-trend">
            <?php if ($doublons > 0): ?>
                <a href="cleanup_duplicates.php" style="color:var(--orange);text-decoration:none;">&rarr; cleanup recommande</a>
            <?php else: ?>
                table propre
            <?php endif; ?>
        </div>
    </div>
    <div class="tile">
        <div class="tile-label">athletes en BDD</div>
        <div class="tile-value"><?= number_format($totalAthletes, 0, ',', ' ') ?></div>
        <div class="tile-trend">scrapes par scraper.php</div>
    </div>
</section>

<!-- ============== ROW 2 : CIRCLE + CONSOLE ============== -->
<section class="hud-main-2">
    <!-- Console log -->
    <div class="hud-console">
        <div class="hud-console-header">
            <div class="hud-console-title">// last_inserts.log</div>
            <div class="hud-console-status">[<?= count($lastUrls) ?> entries]</div>
        </div>
        <div class="hud-console-body">
            <?php foreach ($lastUrls as $row):
                preg_match('#/athletes/(\d+)#', $row['url'], $m);
                $athId = $m[1] ?? '?';
            ?>
                <div>
                    <span class="ts">#<?= $row['id_nom_et_liens'] ?></span>
                    <span class="tag">[ATH:<?= $athId ?>]</span>
                    <span class="url"><?= htmlspecialchars($row['url']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (empty($lastUrls)): ?>
                <div style="color:var(--txt-dim);text-align:center;padding:30px;">
                    Aucune URL en BDD pour le moment.<br>
                    Lance le runner depuis l'index.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Circle gauge -->
    <div class="hud-circle-wrap">
        <div class="hud-circle-title">// scraping progress</div>
        <div class="hud-circle">
            <svg viewBox="0 0 220 220">
                <!-- Anneau decoratif tournant -->
                <circle class="ring-deco" cx="110" cy="110" r="102"></circle>
                <!-- Anneau de fond -->
                <circle class="ring-bg" cx="110" cy="110" r="100"></circle>
                <!-- Anneau de progression -->
                <circle class="ring-fg" cx="110" cy="110" r="100"
                    stroke-dasharray="<?= $ringCircumference ?>"
                    stroke-dashoffset="<?= $ringOffset ?>"></circle>
            </svg>
            <div class="hud-circle-text">
                <div class="hud-circle-pct"><?= $pctAvancement ?>%</div>
                <div class="hud-circle-label">avancement</div>
            </div>
        </div>

        <div style="margin-top:18px;display:grid;grid-template-columns:1fr 1fr;gap:10px;font-family:'Share Tech Mono',monospace;font-size:12px;">
            <div style="text-align:left;">
                <div style="color:var(--txt-dim);font-size:9px;letter-spacing:2px;">URLS</div>
                <div style="color:var(--cyan);font-size:18px;text-shadow:0 0 6px var(--cyan-glow);">
                    <?= $urlsCurrent ?>/<?= $urlsTotalRunner ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="color:var(--txt-dim);font-size:9px;letter-spacing:2px;">INSERTS</div>
                <div style="color:var(--cyan);font-size:18px;text-shadow:0 0 6px var(--cyan-glow);">
                    <?= number_format($insertsRunner, 0, ',', ' ') ?>
                </div>
            </div>
        </div>

        <?php if ($tableActive): ?>
            <div style="margin-top:14px;font-size:10px;color:var(--txt-dim);letter-spacing:1px;text-align:center;border-top:1px solid var(--cyan-dim);padding-top:10px;">
                TARGET: <span style="color:var(--cyan-soft);"><?= htmlspecialchars($tableActive) ?></span>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============== FOOTER ============== -->
<footer class="hud-footer">
    JARVIS DATA UI &mdash; auto-sync <?= $runnerRunning ? '5s' : 'manual' ?> &mdash; sys clock <?= date('Y-m-d H:i:s') ?>
</footer>

<!-- Live clock JS -->
<script>
setInterval(() => {
    const c = document.getElementById('hud-clock');
    if (!c) return;
    const d = new Date();
    c.textContent = String(d.getHours()).padStart(2,'0') + ':' +
                    String(d.getMinutes()).padStart(2,'0') + ':' +
                    String(d.getSeconds()).padStart(2,'0');
}, 1000);
</script>

</body>
</html>
