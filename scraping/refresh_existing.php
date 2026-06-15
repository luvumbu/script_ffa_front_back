<?php
/**
 * refresh_existing.php — Mise a jour des profils (existants + nouveaux)
 *
 * Cas d'usage : apres avoir scrape les classements 2026 (scraping_v2/par_annee.php),
 * nom_et_liens contient les URLs de tous les athletes apparus en 2026.
 * Ce script les re-scrape COMPLETEMENT (3 pages) et :
 *   - Pour les athletes DEJA en BDD : UPDATE identite + DELETE/INSERT enfants
 *   - Pour les athletes NOUVEAUX : INSERT initial complet
 *
 * insertAthleteData() gere automatiquement les deux cas (UPDATE vs INSERT).
 *
 * Ce script :
 *  1. Selectionne les URLs de nom_et_liens ajoutees depuis une date "depuis"
 *  2. Extrait athlete_id_externe via regex sur l'URL
 *  3. Batch parallel 7 athletes (curl_multi via scrapeParallel)
 *  4. Re-scrape complet : AthleteScraper + insertAthleteData
 *  5. Cycle 25s + flag + auto-refresh
 *
 * URL : https://bokonzi.com/scraping/refresh_existing.php
 *       ?since=YYYY-MM-DD&autostart=1 pour demarrer en 1 clic depuis par_annee.php
 */

ob_start();
session_start();
ini_set('max_execution_time', 0);

$TIME_LIMIT = 25;
$PARALLEL   = 4;   // 4 athletes parallel = 12 fetches simultanees (anti-503 athle.fr)

require_once dirname(__DIR__) . '/core/credentials.php';
require_once dirname(__DIR__) . '/Class/AthleteScraper.php';
require_once dirname(__DIR__) . '/core/insert_athle.php';
require_once __DIR__ . '/scrape_functions.php';

$RUNNING_FLAG  = __DIR__ . '/refresh_existing_running.flag';
$PROGRESS_FILE = __DIR__ . '/refresh_existing_progress.txt';
$TARGETS_FILE  = __DIR__ . '/refresh_existing_targets.json';
$STATS_FILE    = __DIR__ . '/refresh_existing_stats.json';
$LOG_FILE      = __DIR__ . '/refresh_existing_log.json';
$LOG_MAX       = 200; // garde les 200 dernieres entrees (rolling buffer)

/**
 * Append une entree au log live et garde uniquement les LOG_MAX dernieres.
 * Lu par l'AJAX (?api=log) pour affichage temps reel.
 */
function appendLiveLog($logFile, $entry, $max = 200) {
    $log = [];
    if (file_exists($logFile)) {
        $raw = @file_get_contents($logFile);
        if ($raw) $log = @json_decode($raw, true) ?: [];
    }
    $log[] = $entry;
    if (count($log) > $max) $log = array_slice($log, -$max);
    @file_put_contents($logFile, json_encode($log, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ====================================================================
// Endpoint AJAX : log live + stats (lecture seule, fichiers uniquement,
// pas besoin de connexion BDD — appele en boucle toutes les 1.5s)
// ====================================================================
if (($_GET['api'] ?? '') === 'log') {
    header('Content-Type: application/json; charset=utf-8');
    $log         = file_exists($LOG_FILE)      ? @json_decode(file_get_contents($LOG_FILE), true) ?: []         : [];
    $globalStats = file_exists($STATS_FILE)    ? @json_decode(file_get_contents($STATS_FILE), true) ?: []       : [];
    $progress    = file_exists($PROGRESS_FILE) ? (int)@file_get_contents($PROGRESS_FILE)                        : 0;
    $isRunning   = file_exists($RUNNING_FLAG);
    $targets     = file_exists($TARGETS_FILE)  ? @json_decode(file_get_contents($TARGETS_FILE), true) ?: []     : [];
    echo json_encode([
        'running'  => $isRunning,
        'progress' => $progress,
        'total'    => count($targets),
        'pct'      => count($targets) > 0 ? round($progress / count($targets) * 100, 1) : 0,
        'stats'    => array_merge(['updated' => 0, 'inserted' => 0, 'errors' => 0, 'http503' => 0], $globalStats),
        'log'      => $log,
        'now'      => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Connexion BDD (apres l'endpoint API qui n'en a pas besoin)
$conn = new mysqli('localhost', $username, $password, $dbname);
if ($conn->connect_error) die('DB error: ' . $conn->connect_error);
$conn->set_charset('utf8mb4');

// ====================================================================
// Actions
// ====================================================================
$sinceDate = $_GET['since'] ?? $_POST['since'] ?? date('Y-m-d', strtotime('-30 days'));
$autostart = isset($_GET['autostart']) || isset($_POST['autostart']);

if (isset($_GET['start']) || isset($_POST['start']) || $autostart) {
    if (!file_exists($RUNNING_FLAG)) {
        file_put_contents($RUNNING_FLAG, json_encode(['started' => date('Y-m-d H:i:s'), 'since' => $sinceDate]));
        @unlink($PROGRESS_FILE);
        @unlink($TARGETS_FILE);
        @unlink($STATS_FILE);
        @unlink($LOG_FILE);
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . "?since=$sinceDate");
    exit;
}
if (isset($_GET['stop'])) {
    if (file_exists($RUNNING_FLAG)) unlink($RUNNING_FLAG);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
if (isset($_GET['reset'])) {
    @unlink($RUNNING_FLAG);
    @unlink($PROGRESS_FILE);
    @unlink($TARGETS_FILE);
    @unlink($STATS_FILE);
    @unlink($LOG_FILE);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$isRunning = file_exists($RUNNING_FLAG);

// ====================================================================
// Charge ou recalcule la liste des cibles (cache fichier)
// ====================================================================
function loadTargets(mysqli $conn, $sinceDate, $targetsFile) {
    if (file_exists($targetsFile)) {
        $data = json_decode(file_get_contents($targetsFile), true);
        if (is_array($data) && !empty($data)) return $data;
    }
    $escDate = $conn->real_escape_string($sinceDate);
    // URLs ajoutees depuis la date — ON N'EXIGE PLUS qu'ils soient deja en BDD
    // insertAthleteData gere automatiquement UPDATE (existant) ou INSERT (nouveau)
    $sql = "SELECT DISTINCT
                CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(n.url, '/athletes/', -1), '/', 1) AS UNSIGNED) AS id_ext
            FROM nom_et_liens n
            WHERE n.nom_et_liens_date >= '$escDate'
              AND n.url REGEXP '/athletes/[0-9]+/'
            ORDER BY id_ext";
    $r = $conn->query($sql);
    $list = [];
    if ($r) while ($row = $r->fetch_assoc()) {
        $idExt = (int)$row['id_ext'];
        if ($idExt > 0) $list[] = $idExt;
    }
    file_put_contents($targetsFile, json_encode($list));
    return $list;
}

// ====================================================================
// Affichage UI
// ====================================================================
$progress = (int)@file_get_contents($PROGRESS_FILE);
$targets = $isRunning ? loadTargets($conn, $sinceDate, $TARGETS_FILE) : (file_exists($TARGETS_FILE) ? json_decode(file_get_contents($TARGETS_FILE), true) : []);
$total = count($targets);
$pct = $total > 0 ? round($progress / $total * 100, 1) : 0;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Refresh existants</title>
    <style>
    body { background:#0a0e1a; color:#e0e0e0; font-family:'Segoe UI',monospace; padding:20px; max-width:900px; margin:auto; }
    h1 { color:#a29bfe; }
    .stat { display:inline-block; background:#111830; border:1px solid #1a2540; padding:10px 16px; border-radius:8px; margin:4px; }
    .stat b { color:#a29bfe; font-size:18px; }
    .bar { height:18px; background:#1a2540; border-radius:9px; overflow:hidden; margin:12px 0; }
    .bar-inner { height:100%; background:linear-gradient(90deg,#34d399,#a29bfe); transition:width 0.5s; }
    .btn { background:#1e2a3a; color:#a29bfe; border:1px solid #a29bfe; padding:8px 20px; border-radius:8px; cursor:pointer; text-decoration:none; display:inline-block; margin:4px; font-size:14px; }
    .btn.danger { color:#f87171; border-color:#f87171; }
    .btn.success { color:#34d399; border-color:#34d399; }
    pre { background:#111830; padding:12px; border-radius:8px; overflow:auto; max-height:300px; font-size:11px; }
    .ok { color:#34d399; }
    .err { color:#f87171; }
    input[type=date] { background:#0a0e1a;color:#e0e0e0;border:1px solid #1a2540;padding:6px 10px;border-radius:6px; }
    </style>
</head>
<body>
    <h1>Mise a jour profils (existants + nouveaux)</h1>
    <p style="color:#8b949e;font-size:13px;">
        Pour chaque URL ajoutee dans <code>nom_et_liens</code> depuis la date choisie :
        full re-scrape (3 pages) → si l'athlete existe deja → UPDATE complet, sinon → INSERT initial.
        Anti-503 actif (4 en parallele, backoff auto).
    </p>

    <div class="stat">Cible : <b><?= number_format($total) ?></b> athletes</div>
    <div class="stat">Avance : <b><?= number_format($progress) ?></b> (<?= $pct ?>%)</div>
    <div class="stat">Restant : <b><?= number_format(max(0, $total - $progress)) ?></b></div>
    <div class="stat">Statut : <?= $isRunning ? '<span class="ok">EN COURS</span>' : '<span class="err">ARRETE</span>' ?></div>

    <div class="bar"><div class="bar-inner" style="width:<?= $pct ?>%"></div></div>

    <?php if (!$isRunning): ?>
    <form method="post" style="margin-top:16px;">
        <label>URLs ajoutees depuis : </label>
        <input type="date" name="since" value="<?= htmlspecialchars($sinceDate) ?>" required>
        <button type="submit" name="start" value="1" class="btn success" onclick="return confirm('Demarrer le rafraichissement ?')">DEMARRER</button>
        <a class="btn" href="?reset=1">Reset</a>
    </form>
    <?php else: ?>
        <a class="btn danger" href="?stop=1">ARRETER</a>
    <?php endif; ?>

    <!-- ============================================================== -->
    <!-- LIVE CONSOLE : log athlete-par-athlete, mis a jour toutes les 1.5s -->
    <!-- ============================================================== -->
    <div style="margin-top:24px;background:#0d1320;border:1px solid #1a2540;border-radius:10px;padding:16px;">
        <h3 style="margin:0 0 12px;color:#a29bfe;">Live console <span id="liveDot" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#34d399;animation:pulse 1.5s infinite;vertical-align:middle;margin-left:6px;"></span></h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-bottom:12px;">
            <div class="stat" style="background:#0a2818;border-color:#34d399;display:block;text-align:center;">
                <div style="color:#6ee7b7;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Nouveaux (NEW)</div>
                <b id="liveNew" style="color:#34d399;font-size:22px;">0</b>
            </div>
            <div class="stat" style="background:#1a1a3a;border-color:#a29bfe;display:block;text-align:center;">
                <div style="color:#c4b5fd;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Mis a jour (MAJ)</div>
                <b id="liveUpdated" style="color:#a29bfe;font-size:22px;">0</b>
            </div>
            <div class="stat" style="background:#2a1a1a;border-color:#f87171;display:block;text-align:center;">
                <div style="color:#fca5a5;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Erreurs</div>
                <b id="liveErrors" style="color:#f87171;font-size:22px;">0</b>
            </div>
            <div class="stat" style="background:#0a1e2a;border-color:#60a5fa;display:block;text-align:center;">
                <div style="color:#93c5fd;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Avancement</div>
                <b id="livePct" style="color:#60a5fa;font-size:22px;">0%</b>
                <div style="color:#8b949e;font-size:10px;"><span id="liveDone">0</span> / <span id="liveTotal">0</span></div>
            </div>
        </div>

        <div style="background:#000;border-radius:6px;padding:10px;max-height:340px;overflow-y:auto;font-family:'JetBrains Mono','Consolas',monospace;font-size:11.5px;line-height:1.6;" id="liveLog">
            <div style="color:#8b949e;">[En attente du premier athlete...]</div>
        </div>

        <div style="margin-top:8px;font-size:11px;color:#8b949e;">
            <span id="liveStatus">--</span>
            <span style="float:right;">Derniere maj : <span id="liveTs">--</span></span>
        </div>
    </div>
    <style>
    @keyframes pulse { 0%,100% { opacity:1; transform:scale(1);} 50% { opacity:0.4; transform:scale(0.9);} }
    .log-new { color:#34d399; }
    .log-upd { color:#a29bfe; }
    .log-err { color:#f87171; }
    .log-fetcherr { color:#fbbf24; }
    .log-name { color:#fff; font-weight:600; }
    .log-meta { color:#8b949e; }
    .log-link { color:#60a5fa; text-decoration:none; }
    .log-link:hover { text-decoration:underline; }
    </style>
    <script>
    (function() {
        var lastSeenPos = -1;
        function fmt(n) { return Number(n||0).toLocaleString('fr-FR'); }
        function tagClass(action) {
            if (action === 'NEW') return 'log-new';
            if (action === 'MAJ') return 'log-upd';
            if (action === 'ERR_FETCH') return 'log-fetcherr';
            return 'log-err';
        }
        function tagLabel(action) {
            if (action === 'NEW')       return 'NEW';
            if (action === 'MAJ')       return 'MAJ';
            if (action === 'ERR_FETCH') return 'FETCH KO';
            return 'ERR';
        }
        function renderEntry(e) {
            var cls   = tagClass(e.action);
            var label = tagLabel(e.action);
            var name  = e.name ? '<span class="log-name">' + escHtml(e.name) + '</span>' : '<span class="log-meta">(sans nom)</span>';
            var meta  = '';
            if (e.action === 'NEW' || e.action === 'MAJ') {
                meta = ' <span class="log-meta">R:' + e.records + ' P:' + e.progressions + ' M:' + e.medailles + ' S:' + e.selections + ' C:' + e.clubs + '</span>';
            } else if (e.msg) {
                meta = ' <span class="log-meta">' + escHtml(e.msg) + '</span>';
            }
            var link = ' <a href="' + e.url + '" target="_blank" rel="noopener" class="log-link" title="Voir sur athle.fr">[#' + e.id_ext + ']</a>';
            return '<div class="' + cls + '">[' + e.ts + '] <b>[' + label + ']</b> ' + name + link + meta + '</div>';
        }
        function escHtml(s) {
            return String(s).replace(/[&<>"']/g, function(c) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
            });
        }
        function refresh() {
            fetch('?api=log&t=' + Date.now())
              .then(function(r) { return r.json(); })
              .then(function(d) {
                if (!d) return;
                document.getElementById('liveNew').textContent     = fmt(d.stats.inserted);
                document.getElementById('liveUpdated').textContent = fmt(d.stats.updated);
                document.getElementById('liveErrors').textContent  = fmt(d.stats.errors);
                document.getElementById('liveDone').textContent    = fmt(d.progress);
                document.getElementById('liveTotal').textContent   = fmt(d.total);
                document.getElementById('livePct').textContent     = d.pct + '%';
                document.getElementById('liveTs').textContent      = d.now;
                document.getElementById('liveStatus').innerHTML    = d.running
                    ? '<span style="color:#34d399;">SCRAPING ACTIF</span>'
                    : '<span style="color:#8b949e;">arrete</span>';
                document.getElementById('liveDot').style.background = d.running ? '#34d399' : '#8b949e';

                var box  = document.getElementById('liveLog');
                var html = '';
                if (!d.log || d.log.length === 0) {
                    html = '<div style="color:#8b949e;">[En attente du premier athlete...]</div>';
                } else {
                    var lastEntries = d.log.slice(-80);
                    for (var i = 0; i < lastEntries.length; i++) html += renderEntry(lastEntries[i]);
                }
                // Conserve le scroll en bas s'il y a une nouvelle entree
                var lastPos = d.log && d.log.length ? d.log[d.log.length - 1].pos : -1;
                var atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 50;
                box.innerHTML = html;
                if (lastPos !== lastSeenPos && atBottom) {
                    box.scrollTop = box.scrollHeight;
                    lastSeenPos = lastPos;
                }
              })
              .catch(function() { /* silencieux */ });
        }
        refresh();
        setInterval(refresh, 1500);
    })();
    </script>

<?php
// ====================================================================
// Boucle de scraping
// ====================================================================
if (!$isRunning) {
    echo '<p style="color:#8b949e;margin-top:24px;">Selectionne une date et clique DEMARRER.</p>';
    echo '</body></html>';
    exit;
}

echo '<h3 style="margin-top:24px;">Log du cycle</h3><pre>';

$start = microtime(true);
$processed = 0;
$updated   = 0;
$inserted  = 0;
$errors    = 0;
$http503   = 0;
$cache = loadRefCache($conn);

// Charger les stats globales accumulees
$globalStats = file_exists($STATS_FILE) ? json_decode(file_get_contents($STATS_FILE), true) : ['updated' => 0, 'inserted' => 0, 'errors' => 0, 'http503' => 0];

while ((microtime(true) - $start) < $TIME_LIMIT) {
    clearstatcache();
    if (!file_exists($RUNNING_FLAG)) {
        echo "FLAG SUPPRIME — arret propre.\n";
        break;
    }
    if ($progress >= $total) {
        echo "TERMINE : tous les athletes rafraichis.\n";
        @unlink($RUNNING_FLAG);
        break;
    }

    $batch = array_slice($targets, $progress, $PARALLEL);
    if (empty($batch)) break;

    $extIds = $batch; // $targets contient des int (id_externe), plus de paires
    $pages = scrapeParallel($extIds);

    foreach ($extIds as $idExt) {
        try {
            $allPages = $pages[$idExt] ?? null;
            if (!$allPages || empty($allPages['bilans'])) {
                echo "<span class='err'>[$progress] id=$idExt FETCH FAIL</span>\n";
                $errors++;
                // Detection 503 / page d'erreur courte
                $bil = $allPages['bilans'] ?? '';
                if (strpos($bil, '503') !== false || strlen($bil) < 1000) $http503++;
                appendLiveLog($LOG_FILE, [
                    'ts'     => date('H:i:s'),
                    'pos'    => $progress,
                    'id_ext' => $idExt,
                    'name'   => '',
                    'action' => 'ERR_FETCH',
                    'url'    => "https://athle.fr/athletes/$idExt/bilans",
                ], $LOG_MAX);
                $progress++;
                continue;
            }
            // Detection si l'athlete existait deja en BDD
            $rExist = $conn->query("SELECT id_athlete FROM athletes WHERE athlete_id_externe = $idExt LIMIT 1");
            $wasExisting = ($rExist && $rExist->num_rows > 0);

            $scraper = new AthleteScraper($idExt);
            $scraper->html = $allPages['bilans'];
            $scraper->extractIdentite();
            $scraper->extractMedailles();
            $scraper->extractProgressions();
            $scraper->extractClubs();
            $scraper->extractPodiums();
            $scraper->extractResultats();
            $scraper->extractNiveaux();
            if (!empty($allPages['records'])) {
                $scraper->html = $allPages['records'];
                $scraper->extractRecords();
            }
            if (!empty($allPages['selections'])) {
                $scraper->html = $allPages['selections'];
                $scraper->extractSelections();
            }

            ob_start();
            insertAthleteData($scraper, $conn, $cache);
            ob_end_clean();

            $nbR  = count($scraper->records);
            $nbP  = count($scraper->progressions);
            $nbS  = count($scraper->selections);
            $nbM  = count($scraper->medailles);
            $nbC  = count($scraper->clubs);
            $name = trim($scraper->identite['nom_complet'] ?? '');
            $tag  = $wasExisting ? 'MAJ' : 'NEW';
            echo "<span class='ok'>[$progress] id=$idExt $tag — R:$nbR P:$nbP S:$nbS</span>\n";
            // Append live log (lu par ?api=log via AJAX)
            appendLiveLog($LOG_FILE, [
                'ts'           => date('H:i:s'),
                'pos'          => $progress,
                'id_ext'       => $idExt,
                'name'         => $name,
                'action'       => $tag,
                'records'      => $nbR,
                'progressions' => $nbP,
                'selections'   => $nbS,
                'medailles'    => $nbM,
                'clubs'        => $nbC,
                'url'          => "https://athle.fr/athletes/$idExt/bilans",
            ], $LOG_MAX);
            if ($wasExisting) $updated++;
            else              $inserted++;
        } catch (Throwable $e) {
            echo "<span class='err'>[$progress] id=$idExt ERR " . htmlspecialchars($e->getMessage()) . "</span>\n";
            $errors++;
            appendLiveLog($LOG_FILE, [
                'ts'     => date('H:i:s'),
                'pos'    => $progress,
                'id_ext' => $idExt,
                'name'   => '',
                'action' => 'ERR',
                'msg'    => substr($e->getMessage(), 0, 200),
                'url'    => "https://athle.fr/athletes/$idExt/bilans",
            ], $LOG_MAX);
        }
        $processed++;
        $progress++;
    }

    file_put_contents($PROGRESS_FILE, $progress);

    // Anti-503 : backoff si trop d'erreurs HTTP recentes
    if ($http503 > 0 && $http503 >= $processed * 0.3) {
        echo "<span class='err'>BACKOFF 5s — trop d'erreurs HTTP detectees</span>\n";
        sleep(5);
        $http503 = 0;
    }

    if (ob_get_level()) { ob_flush(); flush(); }
}

// Sauvegarde stats globales
$globalStats['updated']  += $updated;
$globalStats['inserted'] += $inserted;
$globalStats['errors']   += $errors;
$globalStats['http503']  += $http503;
file_put_contents($STATS_FILE, json_encode($globalStats));

$elapsed = round(microtime(true) - $start, 1);
echo "</pre>";
echo "<p>Cycle termine : <b>$processed</b> traites en {$elapsed}s — <b>$updated</b> MAJ, <b>$inserted</b> NEW, <b>$errors</b> err.</p>";
echo "<p style='color:#8b949e;font-size:12px;'>Total : {$globalStats['updated']} MAJ + {$globalStats['inserted']} NEW depuis le debut.</p>";

if (file_exists($RUNNING_FLAG)) {
    echo '<script>setTimeout(function(){location.reload();}, 1500);</script>';
}
?>
</body></html>
