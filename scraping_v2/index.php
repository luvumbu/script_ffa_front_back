<?php
/**
 * scraping_v2/index.php — Interface demo "Analyseur intelligent d'URLs"
 *
 * Phase 1 : on lit la 1ere URL d'une table source, on la decode, on affiche
 * tout ce qu'on en comprend. Pas de scraping reel.
 *
 * Usage : http://localhost/BK/scraping_v2/  ou  https://bokonzi.com/scraping_v2/
 *         ?table=NOM_TABLE pour changer la table analysee
 *         ?annees[]=2024&annees[]=2025 pour selectionner les annees a garder
 */

// Affichage des erreurs (a desactiver une fois en production stable)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Sanity checks avant include
$dbFile      = dirname(__DIR__) . '/core/db.php';
$libDir      = __DIR__ . '/lib';
$dataFile    = __DIR__ . '/data/parametres_athle.json';

$prerequisites = [
    'core/db.php'                 => $dbFile,
    'lib/UrlAnalyzer.php'         => "$libDir/UrlAnalyzer.php",
    'lib/EpreuveMapper.php'       => "$libDir/EpreuveMapper.php",
    'lib/SourceTableReader.php'   => "$libDir/SourceTableReader.php",
    'data/parametres_athle.json'  => $dataFile,
];

$missing = [];
foreach ($prerequisites as $label => $path) {
    if (!file_exists($path)) $missing[] = $label;
}

if (!empty($missing)) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Erreur</title></head><body style='font-family:monospace;background:#0d1117;color:#fff;padding:30px;'>";
    echo "<h1 style='color:#f87171;'>Fichiers manquants sur le serveur</h1>";
    echo "<p>Les fichiers suivants n'ont pas ete trouves :</p><ul>";
    foreach ($missing as $m) echo "<li style='color:#fbbf24;'>$m</li>";
    echo "</ul><p>Verifie l'upload sur Hostinger.</p></body></html>";
    exit;
}

require_once $dbFile;
require_once "$libDir/UrlAnalyzer.php";
require_once "$libDir/EpreuveMapper.php";
require_once "$libDir/SourceTableReader.php";
require_once "$libDir/ScrapingRunner.php";

// Debug : afficher l'etat de la connexion
if (isset($_GET['debug'])) {
    echo "<pre style='background:#000;color:#0f0;padding:20px;'>";
    echo "DB connected: " . ($conn->ping() ? 'YES' : 'NO') . "\n";
    echo "DB name: " . $conn->query("SELECT DATABASE() AS d")->fetch_assoc()['d'] . "\n";
    echo "PHP version: " . PHP_VERSION . "\n";
    echo "</pre>";
}

$reader = new SourceTableReader($conn);
$tables = $reader->listerTables();

$tableActive = $_GET['table'] ?? ($tables[0]['nom'] ?? null);

// Distribution des annees AVANT la selection (pour construire les checkboxes)
$statsAnnee = $tableActive ? $reader->compterParAnnee($tableActive) : [];

// Selection d'annees : tableau d'annees cochees
// - Si ?annees[]=YYYY est present → on prend celles-la
// - Si ?user_picked=1 (pose par bkSyncUrl du JS) → on respecte le choix de l'utilisateur,
//   meme si vide. Sans ce marqueur, on ne sait pas distinguer "premier chargement"
//   de "utilisateur a tout decoche", et on retomberait sur le defaut alors qu'il a
//   explicitement choisi 0 annee.
// - Sinon (premier chargement non interactif) : toutes les annees >= 2024 sont cochees
$anneesSelectionnees = [];
$userPicked          = !empty($_GET['user_picked']);
if (isset($_GET['annees']) && is_array($_GET['annees'])) {
    foreach ($_GET['annees'] as $a) {
        $a = (int)$a;
        if ($a > 1900 && $a < 2100) $anneesSelectionnees[] = $a;
    }
} elseif ($userPicked || isset($_GET['table'])) {
    // L'utilisateur a interagi avec les cases (ou submit du formulaire) → on respecte sa selection vide
    $anneesSelectionnees = [];
} else {
    // Premier chargement strict : par defaut on coche les annees >= 2024
    foreach (array_keys($statsAnnee) as $an) {
        if ($an >= 2024) $anneesSelectionnees[] = $an;
    }
}
$selSet = array_flip($anneesSelectionnees);

$analyzer = new UrlAnalyzer();
$mapper   = new EpreuveMapper($conn);
$mapper->chargerDepuisTables(array_column($tables, 'nom'));

$premieres = $tableActive ? $reader->premieresLignes($tableActive, 5) : [];
$urlPrincipale = $premieres[0]['url'] ?? null;
$analyse = $urlPrincipale ? $analyzer->analyze($urlPrincipale) : null;

if ($analyse && !empty($analyse['epreuve_code'])) {
    $analyse['epreuve_libelle'] = $mapper->libelle($analyse['epreuve_code']);
}

$lignesTotales = $tableActive ? $reader->compterLignes($tableActive) : 0;
$lignesFiltrees = 0;
foreach ($statsAnnee as $an => $n) if (isset($selSet[$an])) $lignesFiltrees += $n;

// =========================================================================
// SCRAPING RUNNER : start / stop / reset / cycle
// =========================================================================
$runner = new ScrapingRunner($conn);
$cycleResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'start' && $tableActive && !empty($anneesSelectionnees)) {
        $runner->reset();
        $runner->start($tableActive, $anneesSelectionnees);
        // Redirect post-POST avec les params dans l'URL
        $qs = http_build_query(['table' => $tableActive, 'annees' => $anneesSelectionnees]);
        header("Location: index.php?$qs");
        exit;
    }
    if ($action === 'stop') {
        $runner->stop();
        $qs = http_build_query(['table' => $tableActive, 'annees' => $anneesSelectionnees]);
        header("Location: index.php?$qs");
        exit;
    }
    if ($action === 'reset') {
        $runner->reset();
        $qs = http_build_query(['table' => $tableActive, 'annees' => $anneesSelectionnees]);
        header("Location: index.php?$qs");
        exit;
    }
}

// Si en cours : executer un cycle de 25s
$runnerProgress = $runner->getProgress();
$runnerRunning  = $runner->isRunning();
if ($runnerRunning) {
    $cycleResult = $runner->runCycle(25);
    if ($cycleResult) $runnerProgress = $cycleResult['progress'];
    // Auto-refresh PROGRESSIF : demarre doux pour eviter anti-DDoS Hostinger,
    // puis accelere si tout va bien (mesure : nb pages deja traitees)
    if ($runner->isRunning() && empty($runnerProgress['finished'])) {
        $pages = (int)($runnerProgress['stats']['pages_traitees'] ?? 0);
        $errs  = (int)($runnerProgress['stats']['fetch_errors'] ?? 0);

        if ($errs > 0 && $errs >= $pages * 0.3) {
            $refresh = 15;            // > 30% d'erreurs : backoff serieux
        } elseif ($pages < 5) {
            $refresh = 10;            // demarrage doux
        } elseif ($pages < 20) {
            $refresh = 7;
        } elseif ($pages < 50) {
            $refresh = 5;
        } else {
            $refresh = 3;             // vitesse de croisiere
        }
        header("Refresh: $refresh");
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Scraping v2 — Analyseur intelligent</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #0d1117; color: #c9d1d9; margin: 0; padding: 24px; line-height: 1.5; }
        h1 { color: #a78bfa; font-size: 24px; margin: 0 0 4px; }
        h2 { color: #60a5fa; font-size: 16px; margin: 24px 0 8px; padding-bottom: 6px; border-bottom: 1px solid #1f2937; }
        .sub { color: #8b949e; font-size: 13px; margin-bottom: 24px; }
        .grid { display: grid; gap: 16px; grid-template-columns: 1fr 1fr; margin-bottom: 16px; }
        .card { background: #161b22; border: 1px solid #1f2937; border-radius: 8px; padding: 16px; }
        .card h3 { margin: 0 0 12px; color: #34d399; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
        .kv { display: grid; grid-template-columns: 180px 1fr; gap: 6px 12px; font-size: 13px; }
        .kv .k { color: #8b949e; }
        .kv .v { color: #fff; }
        .kv .v.code { font-family: monospace; color: #fbbf24; }
        .kv .v.muted { color: #6b7280; font-style: italic; }
        .kv .v.ok { color: #34d399; }
        .kv .v.warn { color: #fbbf24; }
        .kv .v.bad { color: #f87171; }
        .url { background: #0a0e15; padding: 10px; border-radius: 6px; font-family: monospace; font-size: 11px; color: #a78bfa; word-break: break-all; border-left: 3px solid #6366f1; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 6px 10px; border-bottom: 1px solid #1f2937; }
        th { color: #8b949e; font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .badge-h { background: #1e3a8a; color: #93c5fd; }
        .badge-f { background: #831843; color: #fbcfe8; }
        .badge-ok { background: #064e3b; color: #6ee7b7; }
        .badge-bad { background: #7f1d1d; color: #fca5a5; }
        .ctrl { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; background: #161b22; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #1f2937; }
        .ctrl select, .ctrl input { background: #0d1117; color: #fff; border: 1px solid #30363d; padding: 6px 10px; border-radius: 4px; font-family: inherit; font-size: 13px; }
        .ctrl button { background: #6366f1; color: #fff; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .ctrl label { font-size: 12px; color: #8b949e; }
        .alerte { background: #422006; border-left: 3px solid #fbbf24; padding: 8px 12px; margin: 8px 0; font-size: 13px; color: #fde68a; border-radius: 4px; }
        .ok-box { background: #022c22; border-left: 3px solid #34d399; padding: 12px; border-radius: 4px; }
        .bad-box { background: #450a0a; border-left: 3px solid #f87171; padding: 12px; border-radius: 4px; }
        .small { font-size: 11px; color: #6b7280; }
        .stat-line { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #1f2937; font-size: 13px; }
        .stat-line.kept { color: #34d399; font-weight: 600; }
        .stat-line.skipped { color: #6b7280; }
    </style>
</head>
<body>

<h1>Scraping v2 — Analyseur intelligent</h1>
<div class="sub">Lecture et comprehension automatique des URLs athle.fr depuis les tables sources</div>

<form class="ctrl" method="GET">
    <label>Table source :</label>
    <select name="table" onchange="this.form.submit()">
        <?php foreach ($tables as $t): ?>
            <option value="<?= htmlspecialchars($t['nom']) ?>" <?= $t['nom'] === $tableActive ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['nom']) ?> (<?= $t['lignes_approx'] ?> lignes)
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Analyser</button>

    <span style="color:#6b7280;font-size:11px;margin-left:auto;">
        <?= count($tables) ?> tables sources detectees |
        <?= $mapper->nombreMappings() ?> codes epreuves appris
    </span>

    <div style="flex-basis:100%;border-top:1px solid #1f2937;padding-top:10px;margin-top:4px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
            <span style="color:#8b949e;font-size:12px;font-weight:600;">Annees a garder :</span>
            <button type="button" onclick="bkAnneesAll(true)"  style="padding:3px 10px;background:#064e3b;border:1px solid #34d399;color:#6ee7b7;border-radius:4px;cursor:pointer;font-size:11px;">Tout cocher</button>
            <button type="button" onclick="bkAnneesAll(false)" style="padding:3px 10px;background:#7f1d1d;border:1px solid #f87171;color:#fca5a5;border-radius:4px;cursor:pointer;font-size:11px;">Tout decocher</button>
            <button type="button" onclick="bkAnneesMin(2024)" style="padding:3px 10px;background:#1e3a8a;border:1px solid #60a5fa;color:#93c5fd;border-radius:4px;cursor:pointer;font-size:11px;">&ge; 2024</button>
            <button type="button" onclick="bkAnneesMin(2020)" style="padding:3px 10px;background:#1e3a8a;border:1px solid #60a5fa;color:#93c5fd;border-radius:4px;cursor:pointer;font-size:11px;">&ge; 2020</button>
            <span style="color:#6b7280;font-size:11px;margin-left:auto;">
                <span id="bkAnneesCount"><?= count($anneesSelectionnees) ?> / <?= count($statsAnnee) ?> cochees</span>
                &mdash; <strong style="color:#34d399;"><?= $lignesFiltrees ?></strong> / <?= $lignesTotales ?> lignes
            </span>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php foreach ($statsAnnee as $an => $n):
                $checked = isset($selSet[$an]);
            ?>
                <label class="bk-annee <?= $checked ? 'on' : '' ?>" style="display:inline-flex;align-items:center;gap:4px;padding:4px 8px;background:<?= $checked ? '#022c22' : '#0d1117' ?>;border:1px solid <?= $checked ? '#34d399' : '#30363d' ?>;border-radius:4px;cursor:pointer;font-size:12px;">
                    <input type="checkbox" name="annees[]" value="<?= $an ?>" <?= $checked ? 'checked' : '' ?> data-annee="<?= $an ?>" style="margin:0;">
                    <span style="color:<?= $checked ? '#6ee7b7' : '#c9d1d9' ?>;"><?= $an ?></span>
                    <span style="color:#6b7280;font-size:10px;">(<?= $n ?>)</span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
</form>

<!-- ====================================================================== -->
<!-- SCRAPING RUNNER : bouton DEMARRER / ARRETER + barre de progression     -->
<!-- ====================================================================== -->
<div style="background:#161b22;border:2px solid <?= $runnerRunning ? '#f87171' : '#1f2937' ?>;border-radius:8px;padding:16px;margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <div>
            <h2 style="margin:0;color:<?= $runnerRunning ? '#f87171' : '#a78bfa' ?>;font-size:16px;border:none;padding:0;">
                <?php if ($runnerRunning): ?>
                    SCRAPING EN COURS &mdash; cycle 25s + auto-refresh 1s
                <?php else: ?>
                    Scraping reel (Phase 2)
                <?php endif; ?>
            </h2>
            <div style="font-size:12px;color:#8b949e;margin-top:2px;">
                <?php if ($runnerRunning && $runnerProgress): ?>
                    Table : <code style="color:#a78bfa;"><?= htmlspecialchars($runnerProgress['table']) ?></code>
                    &mdash; Annees : <?= htmlspecialchars(implode(', ', $runnerProgress['annees'])) ?>
                <?php else: ?>
                    Va scraper <strong style="color:#34d399;"><?= $lignesFiltrees ?></strong> URLs filtrees de la table
                    <code style="color:#a78bfa;"><?= htmlspecialchars($tableActive ?? '') ?></code>.
                    INSERT dans <code>nom_et_liens</code> avec doublons autorises.
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <a href="cleanup_duplicates.php" style="font-size:12px;color:#fbbf24;text-decoration:none;border:1px solid #fbbf24;padding:6px 12px;border-radius:4px;">Nettoyer doublons &rarr;</a>
            <a href="test_page.php" style="font-size:12px;color:#60a5fa;text-decoration:none;border:1px solid #30363d;padding:6px 12px;border-radius:4px;">Test parser</a>
        </div>
    </div>

    <?php if ($runnerRunning && $runnerProgress):
        $st = $runnerProgress['stats'];
        $pctPages = $st['pages_total'] > 0 ? round(($st['pages_traitees'] / $st['pages_total']) * 100, 1) : 0;
    ?>
        <!-- Stats en cours -->
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:10px;">
            <div style="background:#0a0e15;padding:10px;border-radius:4px;text-align:center;">
                <div style="font-size:20px;font-weight:700;color:#fff;"><?= $st['urls_terminees'] ?>/<?= $st['urls_total'] ?></div>
                <div style="font-size:10px;color:#8b949e;text-transform:uppercase;">URLs terminees</div>
            </div>
            <div style="background:#0a0e15;padding:10px;border-radius:4px;text-align:center;">
                <div style="font-size:20px;font-weight:700;color:#fff;"><?= number_format($st['pages_traitees'], 0, ',', ' ') ?>/<?= number_format($st['pages_total'], 0, ',', ' ') ?></div>
                <div style="font-size:10px;color:#8b949e;text-transform:uppercase;">Pages traitees</div>
            </div>
            <div style="background:#0a0e15;padding:10px;border-radius:4px;text-align:center;">
                <div style="font-size:20px;font-weight:700;color:#34d399;"><?= number_format($st['athletes_inserts'], 0, ',', ' ') ?></div>
                <div style="font-size:10px;color:#8b949e;text-transform:uppercase;">Athletes INSERT</div>
            </div>
            <div style="background:#0a0e15;padding:10px;border-radius:4px;text-align:center;">
                <div style="font-size:20px;font-weight:700;color:<?= $st['fetch_errors'] > 0 ? '#f87171' : '#fff' ?>;"><?= $st['fetch_errors'] ?></div>
                <div style="font-size:10px;color:#8b949e;text-transform:uppercase;">Erreurs fetch</div>
            </div>
            <div style="background:#0a0e15;padding:10px;border-radius:4px;text-align:center;">
                <div style="font-size:20px;font-weight:700;color:#a78bfa;"><?= $pctPages ?>%</div>
                <div style="font-size:10px;color:#8b949e;text-transform:uppercase;">Avancement</div>
            </div>
        </div>

        <!-- Barre de progression -->
        <div style="background:#0a0e15;border-radius:6px;height:24px;overflow:hidden;margin-bottom:10px;border:1px solid #1f2937;">
            <div style="background:linear-gradient(90deg,#6366f1,#a78bfa);height:100%;width:<?= $pctPages ?>%;transition:width 0.5s;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px;min-width:50px;">
                <?= $pctPages ?>%
            </div>
        </div>

        <!-- Derniere URL + log du cycle -->
        <?php if ($cycleResult): ?>
            <div style="font-size:11px;color:#6b7280;margin-bottom:8px;">
                Dernier cycle : <?= $cycleResult['cycle_pages'] ?> pages, <?= $cycleResult['cycle_inserts'] ?> INSERT en <?= $cycleResult['duree_s'] ?>s
            </div>
            <?php if (!empty($cycleResult['log'])): ?>
                <div style="background:#0a0e15;border:1px solid #1f2937;border-radius:4px;padding:8px;max-height:160px;overflow-y:auto;font-family:monospace;font-size:11px;">
                    <?php foreach ($cycleResult['log'] as $line): ?>
                        <div style="color:<?= strpos($line, 'KO') !== false ? '#f87171' : (strpos($line, 'TERMINE') !== false ? '#34d399' : '#93c5fd') ?>;"><?= htmlspecialchars($line) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Boutons stop -->
        <form method="POST" style="margin-top:10px;display:flex;gap:8px;">
            <input type="hidden" name="action" value="stop">
            <button type="submit" style="background:#dc2626;color:#fff;border:none;padding:10px 24px;border-radius:4px;cursor:pointer;font-weight:700;font-size:13px;">ARRETER</button>
            <a href="?<?= http_build_query(['table' => $tableActive, 'annees' => $anneesSelectionnees]) ?>" style="background:#374151;color:#fff;text-decoration:none;padding:10px 24px;border-radius:4px;font-weight:600;font-size:13px;">Pause auto-refresh</a>
        </form>

    <?php elseif (!empty($runnerProgress) && !empty($runnerProgress['finished'])):
        $st = $runnerProgress['stats'];
    ?>
        <!-- Termine -->
        <div style="background:#022c22;border-left:3px solid #34d399;padding:14px;border-radius:4px;margin-bottom:10px;">
            <div style="color:#6ee7b7;font-size:16px;font-weight:700;margin-bottom:6px;">SCRAPING TERMINE</div>
            <div style="font-size:13px;">
                <?= number_format($st['urls_terminees'], 0, ',', ' ') ?> URLs traitees,
                <?= number_format($st['pages_traitees'], 0, ',', ' ') ?> pages,
                <strong style="color:#34d399;"><?= number_format($st['athletes_inserts'], 0, ',', ' ') ?> INSERT</strong>
                dans nom_et_liens, <?= $st['fetch_errors'] ?> erreurs fetch.
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset">
            <button type="submit" style="background:#374151;color:#fff;border:none;padding:8px 18px;border-radius:4px;cursor:pointer;font-weight:600;font-size:12px;">Effacer la progression</button>
        </form>

    <?php else: ?>
        <!-- Pret a demarrer -->
        <form method="POST" onsubmit="return confirm('Demarrer le scraping de <?= $lignesFiltrees ?> URLs filtrees ? Va INSERT dans nom_et_liens.');">
            <input type="hidden" name="action" value="start">
            <button type="submit"
                <?= ($lignesFiltrees > 0 && $tableActive) ? '' : 'disabled' ?>
                style="background:<?= ($lignesFiltrees > 0) ? '#16a34a' : '#374151' ?>;color:#fff;border:none;padding:12px 32px;border-radius:6px;cursor:pointer;font-weight:700;font-size:14px;<?= ($lignesFiltrees > 0) ? '' : 'opacity:0.5;cursor:not-allowed;' ?>">
                DEMARRER SCRAPING (<?= $lignesFiltrees ?> URLs)
            </button>
            <?php if ($lignesFiltrees === 0): ?>
                <span style="color:#fbbf24;font-size:12px;margin-left:10px;">Coche au moins 1 annee.</span>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<script>
function bkAnneesAll(state) {
    document.querySelectorAll('input[name="annees[]"]').forEach(cb => cb.checked = state);
    bkRefreshLabels();
    bkSyncUrl();
}
function bkAnneesMin(min) {
    document.querySelectorAll('input[name="annees[]"]').forEach(cb => {
        cb.checked = parseInt(cb.dataset.annee) >= min;
    });
    bkRefreshLabels();
    bkSyncUrl();
}
function bkRefreshLabels() {
    document.querySelectorAll('label.bk-annee').forEach(lbl => {
        const cb = lbl.querySelector('input');
        const span = lbl.querySelector('span');
        if (cb.checked) {
            lbl.style.background = '#022c22';
            lbl.style.borderColor = '#34d399';
            span.style.color = '#6ee7b7';
        } else {
            lbl.style.background = '#0d1117';
            lbl.style.borderColor = '#30363d';
            span.style.color = '#c9d1d9';
        }
    });
}
// Synchronise l'URL avec l'etat reel des cases (sans recharger la page).
// Indispensable pour que le bouton DEMARRER (POST) et l'auto-refresh (Refresh header)
// utilisent la selection courante au lieu de la valeur par defaut PHP.
function bkSyncUrl() {
    const params = new URLSearchParams();
    const tbl = document.querySelector('select[name="table"]');
    if (tbl && tbl.value) params.set('table', tbl.value);
    document.querySelectorAll('input[name="annees[]"]:checked').forEach(cb => {
        params.append('annees[]', cb.value);
    });
    // Marqueur explicite : "l'utilisateur a interagi avec les cases".
    // Empeche PHP de retomber sur le defaut ">=2024" quand l'utilisateur a tout decoche.
    params.set('user_picked', '1');
    const url = window.location.pathname + '?' + params.toString();
    history.replaceState(null, '', url);
    // Met aussi a jour le compteur "X / Y cochees" affiche en haut a droite
    bkRefreshCounter();
}
function bkRefreshCounter() {
    const allBoxes = document.querySelectorAll('input[name="annees[]"]');
    const checked  = document.querySelectorAll('input[name="annees[]"]:checked');
    const counter  = document.getElementById('bkAnneesCount');
    if (counter) counter.textContent = checked.length + ' / ' + allBoxes.length + ' cochees';
}
document.querySelectorAll('input[name="annees[]"]').forEach(cb => {
    cb.addEventListener('change', () => { bkRefreshLabels(); bkSyncUrl(); });
});
</script>

<?php if ($analyse): ?>

<h2>1. URL brute (premiere ligne de la table)</h2>
<div class="card">
    <div class="small" style="margin-bottom:8px;">id #<?= htmlspecialchars($premieres[0]['id']) ?> &mdash; epreuve : <code style="color:#fbbf24;"><?= htmlspecialchars($premieres[0]['epreuve']) ?></code> &mdash; <?= htmlspecialchars($premieres[0]['page_total']) ?> pages au total</div>
    <div class="url"><?= htmlspecialchars($urlPrincipale) ?></div>
</div>

<h2>2. Analyse intelligente de l'URL</h2>
<div class="grid">
    <div class="card">
        <h3>Decodage des parametres</h3>
        <div class="kv">
            <div class="k">Hote</div>
            <div class="v"><?= htmlspecialchars($analyse['hote']) ?></div>

            <div class="k">Type de page</div>
            <div class="v"><?= htmlspecialchars($analyse['type_page']) ?></div>

            <div class="k">Annee</div>
            <div class="v <?= isset($selSet[$analyse['annee']]) ? 'ok' : 'warn' ?>"><?= htmlspecialchars($analyse['annee']) ?></div>

            <div class="k">Code epreuve</div>
            <div class="v code">frmepreuve=<?= htmlspecialchars($analyse['epreuve_code']) ?></div>

            <div class="k">&rarr; libelle (mapping auto)</div>
            <div class="v <?= $analyse['epreuve_libelle'] ? 'ok' : 'muted' ?>">
                <?= $analyse['epreuve_libelle'] ? htmlspecialchars($analyse['epreuve_libelle']) : '(non appris)' ?>
            </div>

            <div class="k">Sexe</div>
            <div class="v">
                <?php $s = $analyse['parametres_bruts']['frmsexe'] ?? ''; ?>
                <span class="badge badge-<?= strtolower($s) === 'f' ? 'f' : 'h' ?>"><?= htmlspecialchars($analyse['sexe']) ?></span>
            </div>

            <div class="k">Categorie</div>
            <div class="v"><?= htmlspecialchars($analyse['categorie']) ?></div>

            <div class="k">Departement</div>
            <div class="v muted"><?= htmlspecialchars($analyse['departement']) ?></div>

            <div class="k">Ligue</div>
            <div class="v muted"><?= htmlspecialchars($analyse['ligue']) ?></div>

            <div class="k">Nationalite</div>
            <div class="v muted"><?= htmlspecialchars($analyse['nationalite']) ?></div>

            <div class="k">Pagination</div>
            <div class="v">position <?= $analyse['pagination']['position'] ?> &rarr; page <?= $analyse['pagination']['page_estimee'] ?> (<?= $analyse['pagination']['taille_page'] ?>/page)</div>
        </div>
    </div>

    <div class="card">
        <h3>Verdict du filtre annee</h3>
        <?php if (isset($selSet[$analyse['annee']])): ?>
            <div class="ok-box">
                <div style="color:#34d399;font-size:18px;font-weight:700;margin-bottom:4px;">
                    A SCRAPER (annee <?= $analyse['annee'] ?> cochee)
                </div>
                <div class="small">Cette URL passe le filtre : <?= $analyse['annee'] ?> est dans les annees selectionnees.</div>
            </div>
        <?php else: ?>
            <div class="bad-box">
                <div style="color:#f87171;font-size:18px;font-weight:700;margin-bottom:4px;">
                    SKIP (annee <?= $analyse['annee'] ?> non cochee)
                </div>
                <div class="small">Cette URL ne passe PAS le filtre. Coche <?= $analyse['annee'] ?> dans les annees pour la garder.</div>
            </div>
        <?php endif; ?>

        <?php if (!empty($analyse['alertes'])): ?>
            <h3 style="margin-top:18px;">Alertes</h3>
            <?php foreach ($analyse['alertes'] as $a): ?>
                <div class="alerte"><?= htmlspecialchars($a) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h3 style="margin-top:18px;">Resume sur 1 ligne</h3>
        <div style="font-size:14px;color:#fff;background:#0a0e15;padding:8px;border-radius:4px;font-family:monospace;">
            <?= htmlspecialchars($analyzer->resume($urlPrincipale)) ?>
        </div>
    </div>
</div>

<h2>3. Distribution des annees dans la table</h2>
<div class="card">
    <div style="font-size:13px;margin-bottom:8px;color:#8b949e;">
        Total : <strong style="color:#fff;"><?= $lignesTotales ?></strong> lignes &mdash;
        Annees cochees : <strong style="color:#34d399;"><?= $lignesFiltrees ?></strong> &mdash;
        Skip : <strong style="color:#6b7280;"><?= $lignesTotales - $lignesFiltrees ?></strong>
    </div>
    <?php foreach ($statsAnnee as $an => $n):
        $passe = isset($selSet[$an]);
        $pct = $lignesTotales > 0 ? round(($n / $lignesTotales) * 100, 1) : 0;
    ?>
        <div class="stat-line <?= $passe ? 'kept' : 'skipped' ?>">
            <span><?= $passe ? '&check;' : '&times;' ?> <?= $an ?></span>
            <span><?= $n ?> lignes (<?= $pct ?>%)</span>
        </div>
    <?php endforeach; ?>
</div>

<h2>4. Mapping codes epreuves appris</h2>
<div class="card">
    <div style="font-size:12px;color:#8b949e;margin-bottom:8px;">
        Construit dynamiquement en croisant <code>frmepreuve=N</code> (URL) avec la colonne <code>epreuve</code> (libelle).
        Le mapping s'enrichit automatiquement avec chaque table.
    </div>
    <table>
        <thead>
            <tr><th>Code FFA</th><th>Libelle</th><th>Occurrences</th><th>Tables</th></tr>
        </thead>
        <tbody>
            <?php foreach ($mapper->tousLesMappings() as $code => $info): ?>
                <tr>
                    <td><code style="color:#fbbf24;"><?= htmlspecialchars($code) ?></code></td>
                    <td style="color:#fff;font-weight:600;"><?= htmlspecialchars($info['libelle']) ?></td>
                    <td><?= $info['occurrences'] ?></td>
                    <td class="small">
                        <?= count($info['tables']) ?> table<?= count($info['tables']) > 1 ? 's' : '' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h2>5. Apercu : 5 premieres lignes de la table</h2>
<div class="card">
    <table>
        <thead>
            <tr><th>id</th><th>epreuve</th><th>pages</th><th>annee</th><th>verdict</th></tr>
        </thead>
        <tbody>
            <?php foreach ($premieres as $row):
                $a = $analyzer->analyze($row['url']);
                $passe = !empty($a['annee']) && isset($selSet[$a['annee']]);
            ?>
                <tr>
                    <td>#<?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['epreuve']) ?></td>
                    <td><?= htmlspecialchars($row['page_total']) ?></td>
                    <td><?= htmlspecialchars($a['annee'] ?? '?') ?></td>
                    <td>
                        <span class="badge badge-<?= $passe ? 'ok' : 'bad' ?>">
                            <?= $passe ? 'GARDE' : 'SKIP' ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php else: ?>
    <div class="bad-box">Aucune table source detectee dans la BDD.</div>
<?php endif; ?>

<div style="margin-top:40px;color:#6b7280;font-size:11px;text-align:center;">
    Phase 1 : analyse uniquement &mdash; aucun scraping reel n'est effectue.
</div>

</body>
</html>
