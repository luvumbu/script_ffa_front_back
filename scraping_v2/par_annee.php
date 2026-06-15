<?php
/**
 * scraping_v2/par_annee.php — Interface "1 annee = tout scraper"
 *
 * Choisis UNE annee, le systeme :
 *  1. Agrege toutes les tables sources (sprint, demi_fond, saut, lancer, haies, marche, combine)
 *  2. Charge toutes les URLs de classement pour cette annee
 *  3. Utilise le page_total stocke en BDD (pas besoin de le saisir)
 *  4. Scrape page par page avec cycle 25s + auto-refresh + start/stop
 *
 * URL :
 *   ?annee=2024                  -> Pre-sélectionne 2024
 *   ?action=start&annee=2024     -> Lance
 *   ?action=stop / ?action=reset
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

$dbFile   = dirname(__DIR__) . '/core/db.php';
$libDir   = __DIR__ . '/lib';
$dataFile = __DIR__ . '/data/parametres_athle.json';

$prerequisites = [
    'core/db.php'                 => $dbFile,
    'lib/UrlAnalyzer.php'         => "$libDir/UrlAnalyzer.php",
    'lib/EpreuveMapper.php'       => "$libDir/EpreuveMapper.php",
    'lib/SourceTableReader.php'   => "$libDir/SourceTableReader.php",
    'lib/ScrapingRunner.php'      => "$libDir/ScrapingRunner.php",
    'data/parametres_athle.json'  => $dataFile,
];
$missing = [];
foreach ($prerequisites as $label => $path) {
    if (!file_exists($path)) $missing[] = $label;
}
if (!empty($missing)) {
    echo "<h1 style='color:#f87171;'>Fichiers manquants</h1><ul>";
    foreach ($missing as $m) echo "<li>$m</li>";
    echo "</ul>";
    exit;
}

require_once $dbFile;
require_once "$libDir/SourceTableReader.php";
require_once "$libDir/ScrapingRunner.php";
require_once "$libDir/DiscoverRunner.php";

$reader   = new SourceTableReader($conn);
$runner   = new ScrapingRunner($conn);
$discover = new DiscoverRunner($conn);

// =========================================================================
// MODE API : JSON pour l'AJAX (anti-blocant)
// =========================================================================
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $mode = $_GET['api'];

    if ($mode === 'progress') {
        echo json_encode([
            'scraper'  => ['running' => $runner->isRunning(),   'progress' => $runner->getProgress()],
            'discover' => ['running' => $discover->isRunning(), 'progress' => $discover->getProgress()],
            'now'      => date('Y-m-d H:i:s'),
        ]);
        exit;
    }

    if ($mode === 'cycle_scraper') {
        $result = $runner->isRunning() ? $runner->runCycle(25) : null;
        echo json_encode([
            'ok'      => true,
            'running' => $runner->isRunning(),
            'log'     => $result['log'] ?? [],
            'duree_s' => $result['duree_s'] ?? 0,
            'progress'=> $runner->getProgress(),
        ]);
        exit;
    }

    if ($mode === 'cycle_discover') {
        $result = $discover->isRunning() ? $discover->runCycle(25) : null;
        echo json_encode([
            'ok'      => true,
            'running' => $discover->isRunning(),
            'log'     => $result['log'] ?? [],
            'duree_s' => $result['duree_s'] ?? 0,
            'progress'=> $discover->getProgress(),
        ]);
        exit;
    }

    echo json_encode(['error' => 'mode inconnu']);
    exit;
}

// =========================================================================
// Actions
// =========================================================================
$anneeChoisie = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $annee  = (int)($_POST['annee'] ?? 0);
    if ($action === 'start' && $annee >= 1900) {
        $runner->reset();
        $runner->startForYear($annee);
        header("Location: par_annee.php?annee=$annee");
        exit;
    }
    if ($action === 'stop') {
        $runner->stop();
        header("Location: par_annee.php?annee=$anneeChoisie");
        exit;
    }
    if ($action === 'reset') {
        $runner->reset();
        header("Location: par_annee.php?annee=$anneeChoisie");
        exit;
    }
    if ($action === 'discover' && $annee >= 1900) {
        $discover->reset();
        $discover->start($annee);
        header("Location: par_annee.php?annee=$annee&disc=1");
        exit;
    }
    if ($action === 'verify' && $annee >= 1900) {
        $discover->reset();
        $discover->startVerify($annee);
        header("Location: par_annee.php?annee=$annee&disc=1");
        exit;
    }
    if ($action === 'discover_stop') {
        $discover->stop();
        header("Location: par_annee.php?annee=$anneeChoisie");
        exit;
    }
    if ($action === 'discover_reset') {
        $discover->reset();
        header("Location: par_annee.php?annee=$anneeChoisie");
        exit;
    }
}

// =========================================================================
// Donnees pour l'affichage
// =========================================================================
$forceRefresh = isset($_GET['refresh_stats']);
$stats = $reader->statsParAnneeGlobal($forceRefresh);
$statsCacheFile = __DIR__ . '/state/stats_par_annee.json';
$statsCacheAge = file_exists($statsCacheFile) ? (time() - filemtime($statsCacheFile)) : -1;

// =========================================================================
// COUVERTURE BDD : pour chaque annee, combien d'athletes ont au moins une
// donnee datee de cette annee dans la BDD ? Cache 1h (queries COUNT DISTINCT
// peuvent etre lentes sur grosse table).
// =========================================================================
$ATHLETE_PER_PAGE   = 50; // estimation athle.fr (classements paginated par 50)
$bddCacheFile = __DIR__ . '/state/coverage_bdd.json';
$bddCacheTtl  = 3600;
$forceCov     = isset($_GET['refresh_coverage']);
$bddByAnnee   = [];
if (!$forceCov && file_exists($bddCacheFile) && (time() - filemtime($bddCacheFile)) < $bddCacheTtl) {
    $bddByAnnee = json_decode(file_get_contents($bddCacheFile), true) ?: [];
} else {
    // Source principale : athlete_progressions (1 entry/athlete/annee/epreuve, integer annee)
    $r = $conn->query("SELECT annee_progression AS an, COUNT(DISTINCT id_athlete) AS n
                       FROM athlete_progressions
                       WHERE annee_progression BETWEEN 1990 AND 2100
                       GROUP BY annee_progression");
    if ($r) while ($row = $r->fetch_assoc()) $bddByAnnee[(int)$row['an']] = (int)$row['n'];

    // Fallback / complement : athlete_resultats (au cas ou progressions vide pour une annee)
    $r2 = $conn->query("SELECT YEAR(date_resultat) AS an, COUNT(DISTINCT id_athlete) AS n
                        FROM athlete_resultats
                        WHERE date_resultat IS NOT NULL AND YEAR(date_resultat) BETWEEN 1990 AND 2100
                        GROUP BY YEAR(date_resultat)");
    if ($r2) while ($row = $r2->fetch_assoc()) {
        $an = (int)$row['an'];
        $n  = (int)$row['n'];
        if (!isset($bddByAnnee[$an]) || $n > $bddByAnnee[$an]) $bddByAnnee[$an] = $n;
    }
    if (!is_dir(dirname($bddCacheFile))) @mkdir(dirname($bddCacheFile), 0755, true);
    @file_put_contents($bddCacheFile, json_encode($bddByAnnee));
}
$bddCacheAge = file_exists($bddCacheFile) ? (time() - filemtime($bddCacheFile)) : -1;

// Comptage UNIQUE de tables sources (au lieu de l'appeler dans le loop)
$totalTables = count($reader->listerTables());

$progress = $runner->getProgress();
$running  = $runner->isRunning();

$discProgress = $discover->getProgress();
$discRunning  = $discover->isRunning();
// NOTE : aucune execution de cycle ici — c'est l'AJAX qui s'en charge (anti-blocant)

$stPages    = (int)($progress['stats']['pages_total']      ?? 0);
$stDone     = (int)($progress['stats']['pages_traitees']   ?? 0);
$stInserts  = (int)($progress['stats']['athletes_inserts'] ?? 0);
$stErr      = (int)($progress['stats']['fetch_errors']     ?? 0);
$stUrls     = (int)($progress['stats']['urls_total']       ?? 0);
$stUrlsDone = (int)($progress['stats']['urls_terminees']   ?? 0);
$pct = $stPages > 0 ? round($stDone / $stPages * 100, 1) : 0;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Scraping v2 — Par annee</title>
    <style>
    * { box-sizing: border-box; }
    body { font-family: 'Inter',sans-serif; background:#0d1117; color:#c9d1d9; padding:24px; max-width:1100px; margin:auto; }
    h1 { color:#a78bfa; margin:0 0 4px; }
    .sub { color:#8b949e; font-size:13px; margin-bottom:24px; }
    .card { background:#161b22; border:1px solid #1f2937; border-radius:10px; padding:16px; margin-bottom:16px; }
    .grid-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:16px; }
    .stat { background:#1e2a3a; padding:14px; border-radius:8px; text-align:center; }
    .stat b { display:block; font-size:24px; color:#a78bfa; }
    .stat span { color:#8b949e; font-size:11px; text-transform:uppercase; letter-spacing:1px; }
    .bar { height:22px; background:#1a2540; border-radius:11px; overflow:hidden; margin:12px 0; }
    .bar-inner { height:100%; background:linear-gradient(90deg,#34d399,#a29bfe); transition:width .5s; }
    .yr-table { width:100%; border-collapse:collapse; font-size:13px; }
    .yr-table th, .yr-table td { padding:8px 10px; border-bottom:1px solid #1f2937; text-align:left; }
    .yr-table th { color:#8b949e; text-transform:uppercase; font-size:11px; letter-spacing:1px; }
    .yr-row { cursor:pointer; transition: background .15s; }
    .yr-row:hover { background:#1e2a3a; }
    .yr-row.selected { background:#312e81; }
    .yr-row.selected td { color:#fff; font-weight:600; }
    .btn { background:#6366f1; color:#fff; border:none; padding:10px 22px; border-radius:6px; font-weight:600; cursor:pointer; font-size:14px; text-decoration:none; display:inline-block; }
    .btn:hover { background:#4f46e5; }
    .btn-danger { background:#dc2626; }
    .btn-danger:hover { background:#b91c1c; }
    .btn-gray { background:#374151; }
    .badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
    .badge-running { background:#064e3b; color:#6ee7b7; animation: pulse 1.5s infinite; }
    .badge-stopped { background:#450a0a; color:#fca5a5; }
    @keyframes pulse { 0%,100% {opacity:1;} 50% {opacity:.5;} }
    pre { background:#0a0e15; padding:10px; border-radius:6px; font-size:11px; max-height:280px; overflow:auto; color:#a78bfa; }
    a { color:#60a5fa; }
    </style>
</head>
<body>
    <h1>Scraping v2 — Mode par annee</h1>
    <p class="sub">
        Choisis une annee. Toutes les tables (sprint, fond, sauts, lancers, haies, marche, combine) seront scrapees.
        Le nombre de pages est lu directement depuis la BDD (colonne <code>page_total</code>).
        <a href="index.php">Mode classique</a> | <a href="?annee=<?= $anneeChoisie ?>&t=1">Rafraichir</a>
    </p>

<?php if ($discRunning): ?>
    <!-- VUE DECOUVERTE EN COURS (AJAX) -->
    <div class="card" id="card-discover" style="border-color:#34d399;">
        <h3 style="margin:0 0 8px;color:#34d399;">
            <span id="dc-mode-label">Decouverte</span> en cours — annee <b id="dc-annee"><?= $discProgress['target_year'] ?? '?' ?></b>
            <span class="badge badge-running">EN COURS</span>
            <span id="dc-pulse" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#34d399;margin-left:8px;animation:pulse 1.5s infinite;"></span>
        </h3>
        <div class="bar"><div class="bar-inner" id="dc-bar" style="width:0%;background:linear-gradient(90deg,#34d399,#60a5fa);"></div></div>
        <div style="text-align:center;color:#8b949e;font-size:14px;" id="dc-progress-text">0 / 0 URLs (0%)</div>
        <div style="text-align:center;color:#5a6580;font-size:11px;margin-top:4px;" id="dc-cycle-info">en attente du prochain cycle...</div>
    </div>

    <div class="grid-stats">
        <div class="stat"><b style="color:#34d399;" id="dc-inserted">0</b><span>Inserees / MAJ</span></div>
        <div class="stat"><b style="color:#8b949e;" id="dc-skipped">0</b><span>Skipees</span></div>
        <div class="stat"><b style="color:#fbbf24;" id="dc-empty">0</b><span>Vides</span></div>
        <div class="stat"><b style="color:#f87171;" id="dc-errors">0</b><span>Erreurs</span></div>
        <div class="stat"><b id="dc-restant">0</b><span>Restant</span></div>
    </div>

    <form method="post" style="display:inline;">
        <input type="hidden" name="action" value="discover_stop">
        <button type="submit" class="btn btn-danger">ARRETER decouverte</button>
    </form>

    <div class="card" style="margin-top:16px;">
        <h3 style="margin:0 0 8px;color:#60a5fa;">Log live (40 dernieres lignes)</h3>
        <pre id="dc-log" style="min-height:80px;">en attente...</pre>
    </div>

<?php elseif (!$running): ?>
    <!-- Annees connues -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin:0 0 12px;">
            <h3 style="margin:0;color:#34d399;">Annees deja peuplees</h3>
            <a href="?annee=<?= $anneeChoisie ?>&refresh_coverage=1" style="font-size:11px;color:#60a5fa;text-decoration:none;border:1px solid #1f2937;padding:3px 10px;border-radius:6px;" title="Recalcule la couverture BDD (cache 1h)">
                Recalculer couverture
                <?php if ($bddCacheAge >= 0): ?>
                    <span style="color:#6b7280;">(cache : <?= $bddCacheAge < 60 ? $bddCacheAge.'s' : round($bddCacheAge/60).'min' ?>)</span>
                <?php endif; ?>
            </a>
        </div>
        <table class="yr-table">
            <thead><tr>
                <th>Annee</th>
                <th>URLs (classements)</th>
                <th>Pages total</th>
                <th title="Estimation : pages x 50 athletes/page sur athle.fr">Athletes attendus</th>
                <th title="Athletes distincts en BDD avec donnee datee de cette annee (progressions + resultats)">BDD</th>
                <th title="bdd / athletes attendus">Couverture</th>
                <th>Tables</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($stats as $annee => $info):
                $sel = ($annee === $anneeChoisie);
                $expected = (int)$info['pages'] * $ATHLETE_PER_PAGE;
                $bdd      = (int)($bddByAnnee[$annee] ?? 0);
                $covPct   = $expected > 0 ? min(100, round($bdd / $expected * 100, 1)) : null;
                // Couleur progressive : rouge < 50% / orange < 80% / vert >= 80%
                if ($covPct === null)       { $covColor = '#6b7280'; $covBg = '#1f2937'; }
                elseif ($covPct >= 95)      { $covColor = '#34d399'; $covBg = '#022c22'; }
                elseif ($covPct >= 80)      { $covColor = '#86efac'; $covBg = '#0a2818'; }
                elseif ($covPct >= 50)      { $covColor = '#fbbf24'; $covBg = '#422006'; }
                else                        { $covColor = '#f87171'; $covBg = '#450a0a'; }
            ?>
            <tr class="yr-row<?= $sel ? ' selected' : '' ?>" onclick="window.location.href='?annee=<?= $annee ?>'">
                <td><b><?= $annee ?></b></td>
                <td><?= number_format($info['urls']) ?></td>
                <td><?= number_format($info['pages']) ?></td>
                <td style="color:#a78bfa;"><?= number_format($expected) ?></td>
                <td style="color:#34d399;"><?= number_format($bdd) ?></td>
                <td>
                    <?php if ($covPct === null): ?>
                        <span style="color:#6b7280;">--</span>
                    <?php else: ?>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:0 0 80px;height:14px;background:#0a0e15;border-radius:7px;overflow:hidden;border:1px solid #1f2937;">
                                <div style="height:100%;width:<?= $covPct ?>%;background:<?= $covColor ?>;transition:width 0.3s;"></div>
                            </div>
                            <b style="color:<?= $covColor ?>;font-size:13px;min-width:50px;text-align:right;"><?= $covPct ?>%</b>
                        </div>
                    <?php endif; ?>
                </td>
                <td><?= count($info['tables']) ?> / <?= $totalTables ?></td>
                <td>
                    <?php if ($sel): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="start">
                            <input type="hidden" name="annee" value="<?= $annee ?>">
                            <button type="submit" class="btn" onclick="return confirm('Lancer le scraping pour <?= $annee ?> ?\n\n<?= $info['urls'] ?> URLs / <?= $info['pages'] ?> pages a traiter.')">DEMARRER scraping</button>
                        </form>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="verify">
                            <input type="hidden" name="annee" value="<?= $annee ?>">
                            <button type="submit" class="btn" style="background:#60a5fa;" onclick="return confirm('Verifier le page_total de chaque URL de <?= $annee ?> ?\n\nVa visiter toutes les pages jusqua tomber sur une vide.\nSeule <?= $annee ?> est modifiee.')">VERIFIER pages</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($stats)): ?>
            <p style="color:#f87171;">Aucune annee detectee dans les tables sources.</p>
        <?php endif; ?>
    </div>

    <!-- Découverte d'une annee non encore peuplee -->
    <div class="card" style="border-color:#60a5fa;">
        <h3 style="margin:0 0 12px;color:#60a5fa;">Decouvrir une nouvelle annee (ex: 2026)</h3>
        <p style="font-size:13px;color:#8b949e;margin:0 0 12px;">
            Si l'annee voulue n'est pas dans le tableau ci-dessus, lance la decouverte :
            on visite la page 1 de chaque epreuve connue (depuis l'annee de reference la plus recente),
            on note <code>page_total</code> et on cree les lignes en BDD.
        </p>
        <form method="post" style="display:flex;gap:10px;align-items:center;">
            <input type="hidden" name="action" value="discover">
            <label style="color:#8b949e;">Annee a decouvrir :</label>
            <input type="number" name="annee" value="<?= $anneeChoisie ?>" min="2000" max="2030" style="background:#0a0e15;color:#fff;border:1px solid #30363d;padding:8px 12px;border-radius:6px;width:120px;">
            <button type="submit" class="btn" onclick="return confirm('Lancer la decouverte pour cette annee ?\n\nVa visiter ~80 URLs sur athle.fr (environ 3-5 min).')">DECOUVRIR</button>
        </form>
        <?php if ($discProgress && empty($discRunning)): ?>
        <div style="margin-top:16px;padding:12px;background:#1e2a3a;border-radius:6px;font-size:13px;">
            <b>Derniere decouverte</b> — annee <?= $discProgress['target_year'] ?? '?' ?> —
            <span style="color:#34d399;"><?= $discProgress['stats']['inserted'] ?? 0 ?> inserees</span> /
            <span style="color:#fbbf24;"><?= $discProgress['stats']['empty'] ?? 0 ?> vides</span> /
            <span style="color:#8b949e;"><?= $discProgress['stats']['skipped'] ?? 0 ?> skipees</span> /
            <span style="color:#f87171;"><?= $discProgress['stats']['errors'] ?? 0 ?> erreurs</span>
            <form method="post" style="display:inline;margin-left:10px;">
                <input type="hidden" name="action" value="discover_reset">
                <button type="submit" class="btn btn-gray" style="padding:4px 10px;font-size:11px;">Reset</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- Vue scraping en cours (mise a jour via AJAX) -->
    <div class="card" id="card-scraper">
        <h3 style="margin:0 0 8px;">
            Scraping en cours
            <span class="badge badge-running" id="sc-badge">EN COURS</span>
            <span id="sc-pulse" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#34d399;margin-left:8px;animation:pulse 1.5s infinite;"></span>
            <span style="float:right;font-size:13px;color:#8b949e;">Annee : <b style="color:#a78bfa;" id="sc-annee"><?= implode(', ', $progress['annees'] ?? []) ?></b></span>
        </h3>
        <div class="bar"><div class="bar-inner" id="sc-bar" style="width:0%;"></div></div>
        <div style="text-align:center;color:#8b949e;font-size:14px;" id="sc-progress-text">0 / 0 pages (0%)</div>
        <div style="text-align:center;color:#5a6580;font-size:11px;margin-top:4px;" id="sc-cycle-info">en attente du prochain cycle...</div>
    </div>

    <div class="grid-stats">
        <div class="stat"><b id="sc-urls-done">0</b><span>URLs faites</span></div>
        <div class="stat"><b id="sc-urls-total">0</b><span>URLs total</span></div>
        <div class="stat"><b id="sc-inserts">0</b><span>Athletes inseres</span></div>
        <div class="stat"><b id="sc-errors">0</b><span>Erreurs fetch</span></div>
        <div class="stat"><b id="sc-tables">0</b><span>Tables sources</span></div>
    </div>

    <form method="post" style="display:inline;">
        <input type="hidden" name="action" value="stop">
        <button type="submit" class="btn btn-danger">ARRETER</button>
    </form>

    <div class="card" style="margin-top:16px;">
        <h3 style="margin:0 0 8px;color:#34d399;">Log live (40 dernieres lignes)</h3>
        <pre id="sc-log" style="min-height:80px;">en attente...</pre>
    </div>

<?php endif; ?>

<?php if ($progress && empty($running)): ?>
    <!-- Etape 2 : MAJ profils -->
    <?php
    $sinceDate = $progress['started_at'] ?? date('Y-m-d');
    if (strlen($sinceDate) > 10) $sinceDate = substr($sinceDate, 0, 10);
    ?>
    <div class="card" style="border-color:#34d399;background:linear-gradient(135deg,#161b22 0%,#0a2818 100%);">
        <h3 style="margin:0 0 12px;color:#34d399;">Etape 2 : Mise a jour des profils</h3>
        <p style="color:#8b949e;font-size:13px;margin:0 0 12px;">
            Le scraping est fini. Maintenant on rafraichit les profils des athletes apparus dans <code>nom_et_liens</code>
            depuis le <b><?= htmlspecialchars($sinceDate) ?></b>.
        </p>
        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
            <a class="btn"
               style="background:#10b981;font-size:16px;padding:14px 28px;"
               href="../scraping/refresh_existing.php?since=<?= urlencode($sinceDate) ?>&autostart=1"
               onclick="return confirm('Lancer la MAJ profils maintenant ?\n\nLe nombre exact d\'athletes sera calcule sur la page suivante.')">
                DEMARRER MAJ profils →
            </a>
            <a class="btn btn-gray" href="../scraping/refresh_existing.php?since=<?= urlencode($sinceDate) ?>" style="font-size:13px;">
                Voir le panneau MAJ (sans demarrer)
            </a>
        </div>
        <p style="color:#8b949e;font-size:11px;margin:12px 0 0;">
            (Le nombre exact d'athletes a traiter sera calcule sur <code>refresh_existing.php</code> — requete lente sur les 346k URLs evitee ici.)
        </p>
    </div>

    <!-- Dernier run scraping -->
    <div class="card">
        <h3 style="margin:0 0 8px;">Dernier scraping <span class="badge badge-stopped"><?= !empty($progress['finished']) ? 'TERMINE' : 'ARRETE' ?></span></h3>
        <div class="grid-stats">
            <div class="stat"><b><?= number_format($stDone) ?></b><span>Pages faites</span></div>
            <div class="stat"><b><?= number_format($stPages) ?></b><span>Pages total</span></div>
            <div class="stat"><b><?= number_format($stInserts) ?></b><span>Athletes inseres</span></div>
            <div class="stat"><b style="color:<?= $stErr > 0 ? '#fbbf24' : '#34d399' ?>;"><?= number_format($stErr) ?></b><span>Erreurs</span></div>
            <div class="stat"><b><?= $pct ?>%</b><span>Avancement</span></div>
        </div>
        <p style="color:#8b949e;font-size:12px;">Demarre : <?= $progress['started_at'] ?? '?' ?> &nbsp;|&nbsp; Stop : <?= $progress['stopped_at'] ?? '?' ?></p>
        <form method="post" style="display:inline;margin-right:8px;">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="btn btn-gray" onclick="return confirm('Effacer la progression ?')">Reset</button>
        </form>
    </div>
<?php endif; ?>

<script>
/* =========================================================================
 *  AJAX anti-blocant : poll de progression + lancement de cycles en background
 * ========================================================================= */
(function(){
    // Etat passe depuis PHP
    var initialRunning  = <?= $running ? 'true' : 'false' ?>;
    var initialDiscRun  = <?= $discRunning ? 'true' : 'false' ?>;
    if (!initialRunning && !initialDiscRun) return; // rien a faire

    var cycleScraperBusy  = false;
    var cycleDiscoverBusy = false;
    var stopFlag = false;

    function fmt(n) { return Number(n||0).toLocaleString('fr-FR'); }
    function pct(a, b) { return b > 0 ? Math.round(a/b*1000)/10 : 0; }

    function setText(id, txt) {
        var el = document.getElementById(id);
        if (el) el.textContent = txt;
    }
    function setHtml(id, html) {
        var el = document.getElementById(id);
        if (el) el.innerHTML = html;
    }
    function setStyle(id, prop, val) {
        var el = document.getElementById(id);
        if (el) el.style[prop] = val;
    }

    /* Met a jour le DOM du scraper avec un objet progress */
    function renderScraper(prog) {
        if (!prog || !prog.stats) return;
        var s = prog.stats;
        var totalP = s.pages_total || 0;
        var doneP  = s.pages_traitees || 0;
        var p = pct(doneP, totalP);
        setStyle('sc-bar', 'width', p + '%');
        setText('sc-progress-text', fmt(doneP) + ' / ' + fmt(totalP) + ' pages (' + p + '%)');
        setText('sc-urls-done', fmt(s.urls_terminees));
        setText('sc-urls-total', fmt(s.urls_total));
        setText('sc-inserts', fmt(s.athletes_inserts));
        setText('sc-errors', fmt(s.fetch_errors));
        setText('sc-tables', fmt(s.tables_count || 0));
        if (prog.annees) setText('sc-annee', prog.annees.join(', '));
    }

    /* Met a jour le DOM de la decouverte avec un objet progress */
    function renderDiscover(prog) {
        if (!prog || !prog.stats) return;
        var s = prog.stats;
        var total = s.total || 0;
        var idx = prog.queue_index || 0;
        var p = pct(idx, total);
        setStyle('dc-bar', 'width', p + '%');
        setText('dc-progress-text', fmt(idx) + ' / ' + fmt(total) + ' URLs (' + p + '%)');
        setText('dc-inserted', fmt(s.inserted));
        setText('dc-skipped', fmt(s.skipped));
        setText('dc-empty', fmt(s.empty));
        setText('dc-errors', fmt(s.errors));
        setText('dc-restant', fmt(total - idx));
        if (prog.target_year) setText('dc-annee', prog.target_year);
        if (prog.mode === 'verify') setText('dc-mode-label', 'Verification pages');
        if (prog.log_tail && prog.log_tail.length) {
            // Inverser pour avoir le plus recent en haut
            setText('dc-log', prog.log_tail.slice().reverse().join('\n'));
        }
    }

    /* Poll de progression (lightweight, ~50ms) */
    function pollProgress() {
        if (stopFlag) return;
        fetch('par_annee.php?api=progress', { cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.scraper && data.scraper.running) {
                    renderScraper(data.scraper.progress);
                } else if (initialRunning) {
                    // Scraping vient de finir → recharger pour afficher la suite (Etape 2)
                    location.reload();
                }
                if (data.discover && data.discover.running) {
                    renderDiscover(data.discover.progress);
                } else if (initialDiscRun) {
                    location.reload();
                }
            })
            .catch(function(){});
    }

    /* Cycle scraper (25s, en background) */
    function cycleScraper() {
        if (stopFlag || cycleScraperBusy) return;
        cycleScraperBusy = true;
        setText('sc-cycle-info', 'cycle en cours (~25s)...');
        var t0 = Date.now();
        fetch('par_annee.php?api=cycle_scraper', { method: 'POST', cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                cycleScraperBusy = false;
                var dur = Math.round((Date.now() - t0)/1000);
                if (data.running) {
                    setText('sc-cycle-info', 'cycle ' + dur + 's — prochain cycle dans 1s...');
                    setTimeout(cycleScraper, 1000);
                } else {
                    setText('sc-cycle-info', 'TERMINE');
                    location.reload();
                }
                // Pousser le log dans le pre
                if (data.log && data.log.length) {
                    var prev = (document.getElementById('sc-log') || {}).textContent || '';
                    if (prev === 'en attente...') prev = '';
                    var newLines = data.log.slice().reverse().join('\n');
                    setText('sc-log', newLines + (prev ? '\n' + prev : ''));
                    // Cap a 100 lignes pour pas exploser
                    var lines = document.getElementById('sc-log').textContent.split('\n');
                    if (lines.length > 100) setText('sc-log', lines.slice(0, 100).join('\n'));
                }
            })
            .catch(function(){
                cycleScraperBusy = false;
                setText('sc-cycle-info', 'erreur reseau, retry dans 5s...');
                setTimeout(cycleScraper, 5000);
            });
    }

    /* Cycle discover (25s, en background) */
    function cycleDiscover() {
        if (stopFlag || cycleDiscoverBusy) return;
        cycleDiscoverBusy = true;
        setText('dc-cycle-info', 'cycle en cours (~25s)...');
        var t0 = Date.now();
        fetch('par_annee.php?api=cycle_discover', { method: 'POST', cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                cycleDiscoverBusy = false;
                var dur = Math.round((Date.now() - t0)/1000);
                if (data.running) {
                    setText('dc-cycle-info', 'cycle ' + dur + 's — prochain cycle dans 1s...');
                    setTimeout(cycleDiscover, 1000);
                } else {
                    setText('dc-cycle-info', 'TERMINE');
                    location.reload();
                }
            })
            .catch(function(){
                cycleDiscoverBusy = false;
                setText('dc-cycle-info', 'erreur reseau, retry dans 5s...');
                setTimeout(cycleDiscover, 5000);
            });
    }

    /* Lancement */
    pollProgress();
    setInterval(pollProgress, 1500);   // poll toutes les 1.5s
    if (initialRunning)  cycleScraper();
    if (initialDiscRun)  cycleDiscover();

    /* Stop quand on quitte la page */
    window.addEventListener('beforeunload', function() { stopFlag = true; });
})();
</script>

</body></html>
