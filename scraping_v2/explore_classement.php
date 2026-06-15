<?php
/**
 * explore_classement.php — Drill-down par classement (epreuve + annee + sexe)
 *
 * Workflow :
 *   1. Choisir une URL de classement existante (dropdown depuis tables sources)
 *   2. Fetch live d'une page athle.fr (50 athletes par page)
 *   3. Pour chaque athlete : compare avec la BDD (present/absent/partiel)
 *   4. Bouton "Re-scraper" par athlete (AJAX, repare immediatement)
 *   5. Bouton "Re-scraper tout" pour traiter la page en sequence
 *
 * Cas d'usage : "je sais que je n'ai pas les datas 2026 du 100m F,
 *  donc je veux voir la liste et mettre a jour les sportives une par une"
 */

@ini_set('display_errors', '1');
@error_reporting(E_ALL);
@set_time_limit(120);

require_once dirname(__DIR__) . '/core/db.php';
require_once __DIR__ . '/lib/SourceTableReader.php';
require_once __DIR__ . '/lib/PageAnalyzer.php';
require_once __DIR__ . '/lib/UrlAnalyzer.php';
require_once dirname(__DIR__) . '/Class/AthleteScraper.php';
require_once dirname(__DIR__) . '/core/insert_athle.php';
require_once dirname(__DIR__) . '/scraping/scrape_functions.php';

$reader = new SourceTableReader($conn);

// ============================================================================
// TABLE de tracking : last scrape OK par athlete (pour skip 24h)
// ============================================================================
$conn->query("CREATE TABLE IF NOT EXISTS athlete_scrape_log (
    athlete_id_ext INT UNSIGNED PRIMARY KEY,
    last_scraped_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_action ENUM('NEW','MAJ') DEFAULT 'MAJ',
    INDEX idx_last_scraped (last_scraped_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ============================================================================
// API AJAX : verifier le statut BDD d'un athlete + le re-scraper
// ============================================================================
$api = $_GET['api'] ?? $_POST['api'] ?? null;
if ($api) {
    header('Content-Type: application/json; charset=utf-8');

    // --- Statut BDD d'un athlete (par id_athle.fr) -----------------
    if ($api === 'status') {
        $idExt = (int)($_GET['id'] ?? 0);
        if (!$idExt) { echo json_encode(['ok' => false, 'error' => 'id manquant']); exit; }
        $r = $conn->query("SELECT id_athlete, nom_complet_athlete, date_creation_athlete FROM athletes WHERE athlete_id_externe = $idExt LIMIT 1");
        if (!$r || $r->num_rows === 0) {
            echo json_encode(['ok' => true, 'present' => false]);
            exit;
        }
        $a = $r->fetch_assoc();
        $idA = (int)$a['id_athlete'];
        $det = $conn->query("
            SELECT
              (SELECT COUNT(*) FROM athlete_clubs        WHERE id_athlete = $idA) AS nb_clubs,
              (SELECT COUNT(*) FROM athlete_records      WHERE id_athlete = $idA) AS nb_records,
              (SELECT COUNT(*) FROM athlete_progressions WHERE id_athlete = $idA) AS nb_progressions,
              (SELECT COUNT(*) FROM athlete_resultats    WHERE id_athlete = $idA) AS nb_resultats,
              (SELECT COUNT(*) FROM athlete_medailles    WHERE id_athlete = $idA) AS nb_medailles,
              (SELECT COUNT(*) FROM athlete_podiums      WHERE id_athlete = $idA) AS nb_podiums,
              (SELECT COUNT(*) FROM athlete_selections   WHERE id_athlete = $idA) AS nb_selections,
              (SELECT COUNT(*) FROM athlete_niveaux      WHERE id_athlete = $idA) AS nb_niveaux,
              (SELECT MAX(date_resultat) FROM athlete_resultats WHERE id_athlete = $idA) AS last_resultat
        ")->fetch_assoc();
        echo json_encode([
            'ok'      => true,
            'present' => true,
            'id_athlete' => $idA,
            'name'    => $a['nom_complet_athlete'],
            'created' => $a['date_creation_athlete'],
            'counts'  => array_map('intval', array_filter($det, fn($k) => $k !== 'last_resultat', ARRAY_FILTER_USE_KEY)),
            'last_resultat' => $det['last_resultat'],
        ]);
        exit;
    }

    // --- Verifie si l'athlete a deja ete scrape recemment (skip 24h) ----
    if ($api === 'check_recent') {
        $idExt = (int)($_GET['id'] ?? 0);
        if (!$idExt) { echo json_encode(['ok' => false, 'error' => 'id manquant']); exit; }
        $r = $conn->query("SELECT last_scraped_at, TIMESTAMPDIFF(SECOND, last_scraped_at, NOW()) AS age_s
                           FROM athlete_scrape_log
                           WHERE athlete_id_ext = $idExt LIMIT 1");
        if ($r && $r->num_rows > 0) {
            $row = $r->fetch_assoc();
            echo json_encode([
                'ok'      => true,
                'present' => true,
                'last'    => $row['last_scraped_at'],
                'age_s'   => (int)$row['age_s'],
                'recent'  => (int)$row['age_s'] < 86400,
            ]);
        } else {
            echo json_encode(['ok' => true, 'present' => false, 'recent' => false]);
        }
        exit;
    }

    // --- Re-scraper un athlete (full scrape + upsert) --------------
    if ($api === 'rescrape') {
        $idExt = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $skipIfRecent = !empty($_POST['skip_if_recent']) || !empty($_GET['skip_if_recent']);
        if (!$idExt) { echo json_encode(['ok' => false, 'error' => 'id manquant']); exit; }

        // Skip si dernier scrape OK < 24h ?
        if ($skipIfRecent) {
            $rChk = $conn->query("SELECT last_scraped_at, TIMESTAMPDIFF(SECOND, last_scraped_at, NOW()) AS age_s
                                   FROM athlete_scrape_log
                                   WHERE athlete_id_ext = $idExt
                                     AND last_scraped_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1");
            if ($rChk && $rChk->num_rows > 0) {
                $r0 = $rChk->fetch_assoc();
                $ageH = round($r0['age_s'] / 3600, 1);
                echo json_encode([
                    'ok'      => true,
                    'skipped' => true,
                    'id_ext'  => $idExt,
                    'reason'  => 'recent',
                    'last'    => $r0['last_scraped_at'],
                    'age_h'   => $ageH,
                ]);
                exit;
            }
        }
        try {
            $t0 = microtime(true);
            // Verifier si existait avant
            $rE = $conn->query("SELECT id_athlete FROM athletes WHERE athlete_id_externe = $idExt LIMIT 1");
            $wasExisting = ($rE && $rE->num_rows > 0);

            $pages = scrapeParallel([$idExt]);
            $allPages = $pages[$idExt] ?? null;
            if (!$allPages || empty($allPages['bilans'])) {
                echo json_encode(['ok' => false, 'error' => 'Fetch athle.fr a echoue', 'id_ext' => $idExt]);
                exit;
            }
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
            $cache = loadRefCache($conn);
            ob_start();
            insertAthleteData($scraper, $conn, $cache);
            ob_end_clean();
            // Marque le scrape comme reussi dans le log (pour le skip 24h)
            $action = $wasExisting ? 'MAJ' : 'NEW';
            $stmt = $conn->prepare("INSERT INTO athlete_scrape_log (athlete_id_ext, last_action)
                                    VALUES (?, ?)
                                    ON DUPLICATE KEY UPDATE last_scraped_at = NOW(), last_action = VALUES(last_action)");
            if ($stmt) {
                $stmt->bind_param('is', $idExt, $action);
                $stmt->execute();
                $stmt->close();
            }
            $duree_s = round(microtime(true) - $t0, 1);
            echo json_encode([
                'ok'      => true,
                'action'  => $action,
                'id_ext'  => $idExt,
                'name'    => $scraper->identite['nom_complet'] ?? '',
                'counts'  => [
                    'clubs'        => count($scraper->clubs),
                    'records'      => count($scraper->records),
                    'progressions' => count($scraper->progressions),
                    'resultats'    => count($scraper->resultats),
                    'medailles'    => count($scraper->medailles),
                    'podiums'      => count($scraper->podiums),
                    'selections'   => count($scraper->selections),
                    'niveaux'      => count($scraper->niveaux),
                ],
                'duree_s' => $duree_s,
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'id_ext' => $idExt]);
            exit;
        }
    }

    echo json_encode(['ok' => false, 'error' => 'API inconnue']);
    exit;
}

// ============================================================================
// PAGE : selection du classement + fetch live + comparaison BDD
// ============================================================================

// Catalogue des classements connus (depuis source tables) ----
// On les regroupe en (table, epreuve_id, libelle, annee, sexe, page_total, url)
$catalogue = [];
$tables = $reader->listerTables();
$p = rtrim('u489596434_bokonzi_on_', '_');
foreach ($tables as $t) {
    $tn = $t['nom'];
    $colId   = $p . '_id';
    $colUrl  = $p . '_url';
    $colPage = $p . '_page_total';
    $colEpr  = $p . '_epreuve';
    $sql = "SELECT `$colId` AS id, `$colUrl` AS url, `$colPage` AS page_total, `$colEpr` AS epreuve
            FROM `$tn` ORDER BY `$colEpr`";
    $r = $conn->query($sql);
    if (!$r) continue;
    while ($row = $r->fetch_assoc()) {
        $eprStr = (string)$row['epreuve'];
        $parts = array_map('trim', explode('|', $eprStr));
        $annee  = isset($parts[0]) ? (int)$parts[0] : 0;
        $libelle= $parts[1] ?? '';
        $sexe   = $parts[2] ?? '';
        if ($annee < 1990 || $annee > 2100) continue;
        $catalogue[] = [
            'table'     => $tn,
            'id'        => (int)$row['id'],
            'annee'     => $annee,
            'libelle'   => $libelle,
            'sexe'      => $sexe,
            'page_total'=> (int)$row['page_total'],
            'url'       => $row['url'],
        ];
    }
}
// Tri : annee DESC, puis libelle
usort($catalogue, function($a, $b) {
    if ($a['annee'] !== $b['annee']) return $b['annee'] - $a['annee'];
    return strcmp($a['libelle'], $b['libelle']);
});

$selectedKey = $_GET['classement'] ?? '';
$selectedPage = max(1, (int)($_GET['page'] ?? 1));

// Parse selectedKey "table|id" → trouver l'entry catalogue
$selected = null;
if ($selectedKey !== '' && strpos($selectedKey, '|') !== false) {
    [$selTable, $selId] = explode('|', $selectedKey, 2);
    foreach ($catalogue as $c) {
        if ($c['table'] === $selTable && $c['id'] === (int)$selId) { $selected = $c; break; }
    }
}

// Fetch la page athle.fr du classement choisi ----
$pageData = null;
$athletesEnrichis = [];
if ($selected) {
    $ua = new UrlAnalyzer();
    $pageUrl = $ua->urlPourPage($selected['url'], $selectedPage);
    $pa = new PageAnalyzer(20);
    $pageData = $pa->analyze($pageUrl);

    // Pour chaque athlete trouve, lookup en BDD
    if ($pageData && $pageData['success'] && !empty($pageData['athletes'])) {
        $idsExt = array_column($pageData['athletes'], 'id');
        $idsList = implode(',', array_map('intval', $idsExt));
        $bddInfo = [];
        if (!empty($idsList)) {
            $r = $conn->query("
                SELECT a.athlete_id_externe, a.id_athlete, a.nom_complet_athlete, a.date_creation_athlete,
                       (SELECT COUNT(*) FROM athlete_records      WHERE id_athlete = a.id_athlete) AS nb_rec,
                       (SELECT COUNT(*) FROM athlete_progressions WHERE id_athlete = a.id_athlete) AS nb_prog,
                       (SELECT COUNT(*) FROM athlete_resultats    WHERE id_athlete = a.id_athlete) AS nb_res,
                       (SELECT MAX(date_resultat) FROM athlete_resultats WHERE id_athlete = a.id_athlete) AS last_res
                FROM athletes a
                WHERE a.athlete_id_externe IN ($idsList)
            ");
            if ($r) while ($row = $r->fetch_assoc()) {
                $bddInfo[(int)$row['athlete_id_externe']] = $row;
            }
        }
        // Construit le tableau enrichi
        foreach ($pageData['athletes'] as $a) {
            $idE = (int)$a['id'];
            $bdd = $bddInfo[$idE] ?? null;
            $athletesEnrichis[] = ['athlete' => $a, 'bdd' => $bdd];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Explorer classement — drill-down par athlete</title>
<style>
* { box-sizing: border-box; }
body { font-family: 'Inter', -apple-system, sans-serif; background: #0a0e14; color: #d9e1ec; margin: 0; padding: 24px; max-width: 1400px; margin-left: auto; margin-right: auto; }
h1 { color: #a78bfa; margin: 0 0 4px; font-size: 22px; }
.sub { color: #7a869a; font-size: 13px; margin-bottom: 22px; }
.btn { background: #6366f1; color: #fff; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 12px; text-decoration: none; display: inline-block; transition: all 0.15s; }
.btn:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(1.1); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-back { background: #374151; }
.btn-rescrape { background: linear-gradient(135deg, #f59e0b, #d97706); color: #000; font-weight: 700; }
.btn-rescrape.done { background: linear-gradient(135deg, #10b981, #059669); color: #000; }
.btn-rescrape.err { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; }
.card { background: #11161f; border: 1px solid #232b3a; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
select, input { background: #11161f; color: #d9e1ec; border: 1px solid #232b3a; padding: 8px 12px; border-radius: 6px; font-family: inherit; font-size: 13px; }
select { min-width: 280px; }
table { width: 100%; border-collapse: collapse; font-size: 12.5px; background: #11161f; border: 1px solid #232b3a; border-radius: 10px; overflow: hidden; }
th, td { padding: 8px 10px; border-bottom: 1px solid #1c2330; text-align: left; }
th { background: #161c28; color: #7a869a; font-size: 10px; text-transform: uppercase; letter-spacing: 0.6px; }
tr:hover td { background: #161c28; }
.flag { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; }
.flag-ok { background: rgba(16,185,129,0.18); color: #6ee7b7; }
.flag-no { background: rgba(239,68,68,0.18); color: #fca5a5; }
.flag-partial { background: rgba(251,191,36,0.18); color: #fcd34d; }
code { background: #1a2230; color: #a5b4fc; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-family: 'JetBrains Mono', monospace; }
.spinner { display: inline-block; width: 12px; height: 12px; border: 2px solid #6366f1; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; }
@keyframes spin { to { transform: rotate(360deg); } }
.stat { display: inline-block; background: #161c28; border: 1px solid #232b3a; padding: 6px 12px; border-radius: 6px; margin-right: 6px; font-size: 12px; }
.stat b { color: #fff; }
.url { color: #a5b4fc; word-break: break-all; font-family: 'JetBrains Mono', monospace; font-size: 11px; }
</style>
</head>
<body>

<h1>Explorer classement — drill-down par athlete</h1>
<div class="sub">Choisis un classement, on fetch la page athle.fr live + compare avec ta BDD athlete par athlete. Re-scrape ciblee par bouton.</div>

<div style="display:flex;gap:8px;margin-bottom:16px;">
    <a href="par_annee.php" class="btn btn-back">&larr; Par annee</a>
    <a href="par_epreuve.php" class="btn btn-back">Par epreuve</a>
    <a href="index.php" class="btn btn-back">Index</a>
</div>

<?php
// Liste des annees disponibles (extraites du catalogue)
$anneesDispo = [];
foreach ($catalogue as $c) $anneesDispo[$c['annee']] = true;
$anneesDispo = array_keys($anneesDispo);
rsort($anneesDispo);
?>

<!-- FILTRE ANNEES (cases a cocher, persiste en localStorage) -->
<div class="card" style="background:linear-gradient(135deg,#0a2818 0%,#11161f 100%);border:2px solid #10b981;">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:10px;">
        <span style="font-size:13px;color:#6ee7b7;font-weight:700;">&#128197; Annees a prendre en compte :</span>
        <span style="font-size:11px;color:#7a869a;">(coche celles que tu veux scraper en mode chaine)</span>
        <button type="button" onclick="yearsAll(true)"  style="font-size:11px;background:rgba(16,185,129,0.15);color:#6ee7b7;border:1px solid #10b981;padding:3px 10px;border-radius:4px;cursor:pointer;">Tout cocher</button>
        <button type="button" onclick="yearsAll(false)" style="font-size:11px;background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid #ef4444;padding:3px 10px;border-radius:4px;cursor:pointer;">Tout decocher</button>
        <span id="yearsCount" style="margin-left:auto;font-size:11px;color:#7a869a;font-weight:600;">--</span>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:6px;" id="yearsBox">
        <?php foreach ($anneesDispo as $an):
            // Compte le nombre de classements pour cette annee
            $nbCl = 0;
            foreach ($catalogue as $c) if ($c['annee'] === $an) $nbCl++;
        ?>
            <label class="year-chk" style="display:inline-flex;align-items:center;gap:5px;padding:5px 11px;background:#0a0e14;border:1px solid #232b3a;border-radius:6px;cursor:pointer;font-size:12px;transition:all 0.15s;">
                <input type="checkbox" data-annee="<?= $an ?>" style="margin:0;cursor:pointer;accent-color:#10b981;">
                <span style="color:#d9e1ec;font-weight:600;"><?= $an ?></span>
                <span style="color:#7a869a;font-size:10px;">(<?= $nbCl ?>)</span>
            </label>
        <?php endforeach; ?>
    </div>
</div>

<!-- SELECTION DU CLASSEMENT -->
<form method="GET" class="card">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <label style="color:#7a869a;font-size:12px;">Classement :</label>
        <select name="classement" onchange="this.form.submit()">
            <option value="">-- Choisir une epreuve + annee + sexe --</option>
            <?php
            // Regroupe par annee pour le optgroup
            $byAnnee = [];
            foreach ($catalogue as $c) {
                $byAnnee[$c['annee']][] = $c;
            }
            krsort($byAnnee);
            foreach ($byAnnee as $an => $items): ?>
                <optgroup label="Annee <?= $an ?>">
                    <?php foreach ($items as $c):
                        $key = $c['table'] . '|' . $c['id'];
                        $sel = ($key === $selectedKey) ? 'selected' : '';
                        $label = htmlspecialchars($c['libelle']) . ' — ' . htmlspecialchars($c['sexe']) . ' (' . $c['page_total'] . ' pages)';
                    ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $sel ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <?php if ($selected): ?>
            <label style="color:#7a869a;font-size:12px;margin-left:12px;">Page :</label>
            <select name="page" onchange="this.form.submit()">
                <?php for ($p_ = 1; $p_ <= max(1, $selected['page_total']); $p_++): ?>
                    <option value="<?= $p_ ?>" <?= $p_ === $selectedPage ? 'selected' : '' ?>><?= $p_ ?> / <?= $selected['page_total'] ?></option>
                <?php endfor; ?>
            </select>
        <?php endif; ?>
    </div>
</form>

<?php if (!$selected): ?>
    <div class="card" style="color:#7a869a;font-size:13px;">
        Selectionne un classement dans la liste pour voir les sportifs de cette epreuve+annee.
        Chaque ligne te dira si l'athlete est en BDD et avec quelles donnees.
    </div>
<?php else: ?>

    <!-- INFO CLASSEMENT -->
    <div class="card">
        <div style="font-size:16px;font-weight:700;color:#fff;margin-bottom:6px;">
            <?= htmlspecialchars($selected['libelle']) ?> — <?= htmlspecialchars($selected['sexe']) ?> — <?= $selected['annee'] ?>
        </div>
        <div class="stat">Table source : <b><?= htmlspecialchars(substr($selected['table'], strrpos($selected['table'], 'on_') + 3)) ?></b></div>
        <div class="stat">Pages total : <b><?= $selected['page_total'] ?></b></div>
        <div class="stat">Page courante : <b><?= $selectedPage ?></b></div>
        <?php if ($pageData): ?>
            <div class="stat">Athletes sur la page : <b><?= count($pageData['athletes']) ?></b></div>
            <div class="stat">HTTP : <b style="color:<?= $pageData['http_code'] === 200 ? '#10b981' : '#ef4444' ?>;"><?= $pageData['http_code'] ?></b></div>
            <div class="stat">Fetch : <b><?= $pageData['duree_ms'] ?>ms</b></div>
        <?php endif; ?>
        <div style="margin-top:10px;">
            <a href="<?= htmlspecialchars($ua->urlPourPage($selected['url'], $selectedPage)) ?>" target="_blank" rel="noopener" class="url">
                Voir la page athle.fr &rarr;
            </a>
        </div>
    </div>

    <!-- BARRE DE CONTROLE AUTO-ADVANCE (toujours visible des qu'un classement est selectionne) -->
    <div class="card" style="background:linear-gradient(135deg,#1e1b4b 0%,#11161f 100%);border:2px solid #6366f1;">
        <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
            <span style="font-size:13px;color:#a5b4fc;font-weight:600;">&#9881; Auto-advance :</span>
            <label style="display:inline-flex;align-items:center;gap:10px;background:#0a0e14;border:2px solid #6366f1;padding:10px 16px;border-radius:8px;cursor:pointer;font-size:14px;color:#fff;font-weight:600;user-select:none;">
                <input type="checkbox" id="chkAutoAdvance" style="width:18px;height:18px;cursor:pointer;accent-color:#10b981;">
                <span>Page suivante (auto)</span>
            </label>
            <label style="display:inline-flex;align-items:center;gap:10px;background:#0a0e14;border:2px solid #a78bfa;padding:10px 16px;border-radius:8px;cursor:pointer;font-size:14px;color:#fff;font-weight:600;user-select:none;">
                <input type="checkbox" id="chkAutoAdvanceEpreuve" style="width:18px;height:18px;cursor:pointer;accent-color:#a78bfa;">
                <span>Epreuve suivante (auto)</span>
            </label>
            <label style="display:inline-flex;align-items:center;gap:10px;background:#0a0e14;border:2px solid #fbbf24;padding:10px 16px;border-radius:8px;cursor:pointer;font-size:14px;color:#fff;font-weight:600;user-select:none;" title="Si l'athlete a deja ete scrape avec succes dans les 24h, on le saute (gagne du temps).">
                <input type="checkbox" id="chkSkip24h" style="width:18px;height:18px;cursor:pointer;accent-color:#fbbf24;">
                <span>Skip si scrape &lt; 24h</span>
            </label>
            <span id="autoAdvanceStatus" style="color:#7a869a;font-size:11px;margin-left:auto;font-style:italic;">--</span>
        </div>
        <div style="font-size:12px;color:#a5b4fc;margin-top:4px;">
            <?php if ($selectedPage < $selected['page_total']): ?>
                Page <b style="color:#fff;"><?= $selectedPage ?></b> / <?= $selected['page_total'] ?> &mdash; reste <b style="color:#fbbf24;"><?= $selected['page_total'] - $selectedPage ?></b> pages dans cette epreuve.
            <?php else: ?>
                <b style="color:#10b981;">Derniere page</b> de cette epreuve.
            <?php endif; ?>
            <span id="epreuveQueueInfo" style="color:#c4b5fd;margin-left:6px;"></span>
        </div>
        <div style="font-size:11px;color:#7a869a;margin-top:8px;line-height:1.5;">
            &bull; <b style="color:#a5b4fc;">Page</b> : enchaine les pages du classement courant.
            <br>
            &bull; <b style="color:#c4b5fd;">Epreuve</b> : a la fin du dernier page, passe a l'epreuve suivante (filtre par annees cochees ci-dessus).
            <br>
            &bull; <b style="color:#fcd34d;">Skip 24h</b> : evite de re-scraper les athletes dont le precedent scrape OK est trop recent. Decoche pour forcer le re-scrape complet.
        </div>
    </div>

    <?php if (!$pageData || !$pageData['success']): ?>
        <div class="card" style="color:#fca5a5;">
            <strong>Echec fetch :</strong> <?= htmlspecialchars($pageData['erreur'] ?? 'HTTP ' . ($pageData['http_code'] ?? '?')) ?>
        </div>
    <?php elseif (empty($athletesEnrichis)): ?>
        <div class="card" style="color:#fcd34d;">
            Aucun athlete extrait de cette page (page vide ou structure HTML differente).
        </div>
    <?php else: ?>

        <!-- COMPTEURS RAPIDES -->
        <?php
        $nAbsent = 0; $nPresent = 0; $nPartiel = 0;
        foreach ($athletesEnrichis as $e) {
            if (!$e['bdd']) { $nAbsent++; continue; }
            $nbProg = (int)$e['bdd']['nb_prog'];
            $nbRec  = (int)$e['bdd']['nb_rec'];
            if ($nbProg === 0 || $nbRec === 0) $nPartiel++;
            else $nPresent++;
        }
        ?>
        <div class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <div class="stat" style="background:rgba(16,185,129,0.1);">Complets : <b style="color:#10b981;"><?= $nPresent ?></b></div>
            <div class="stat" style="background:rgba(251,191,36,0.1);">Partiels : <b style="color:#fbbf24;"><?= $nPartiel ?></b></div>
            <div class="stat" style="background:rgba(239,68,68,0.1);">Absents : <b style="color:#ef4444;"><?= $nAbsent ?></b></div>
            <button class="btn btn-rescrape" id="btnRescrapeAll" onclick="rescrapeAllVisible(this)" style="margin-left:auto;">
                Re-scraper TOUT
            </button>
        </div>

        <!-- TABLE DES ATHLETES -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nom (athle.fr)</th>
                    <th>Perf</th>
                    <th>Date</th>
                    <th>Lieu</th>
                    <th>ID athle.fr</th>
                    <th>BDD</th>
                    <th>Records / Progr / Result</th>
                    <th>Dernier resultat</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($athletesEnrichis as $i => $e):
                $a   = $e['athlete'];
                $bdd = $e['bdd'];
                $idE = (int)$a['id'];
                if (!$bdd) {
                    $statusFlag = '<span class="flag flag-no">ABSENT</span>';
                } else {
                    $nbProg = (int)$bdd['nb_prog'];
                    $nbRec  = (int)$bdd['nb_rec'];
                    if ($nbProg === 0 || $nbRec === 0) {
                        $statusFlag = '<span class="flag flag-partial">PARTIEL</span>';
                    } else {
                        $statusFlag = '<span class="flag flag-ok">COMPLET</span>';
                    }
                }
            ?>
                <tr data-id-ext="<?= $idE ?>">
                    <td><?= $a['rang'] ?: ($i + 1) ?></td>
                    <td><strong><?= htmlspecialchars($a['nom']) ?></strong></td>
                    <td><code><?= htmlspecialchars($a['perf']) ?></code></td>
                    <td><?= htmlspecialchars($a['date']) ?></td>
                    <td><?= htmlspecialchars($a['lieu']) ?></td>
                    <td><a href="https://athle.fr<?= htmlspecialchars($a['url_fiche']) ?>" target="_blank" rel="noopener" style="color:#a5b4fc;text-decoration:none;">#<?= $idE ?> &rarr;</a></td>
                    <td><?= $statusFlag ?></td>
                    <td>
                        <?php if ($bdd): ?>
                            <span style="color:<?= $bdd['nb_rec'] > 0 ? '#6ee7b7' : '#fca5a5' ?>;">R:<?= $bdd['nb_rec'] ?></span>
                            <span style="color:<?= $bdd['nb_prog'] > 0 ? '#6ee7b7' : '#fca5a5' ?>;">P:<?= $bdd['nb_prog'] ?></span>
                            <span style="color:<?= $bdd['nb_res'] > 0 ? '#6ee7b7' : '#fca5a5' ?>;">Res:<?= $bdd['nb_res'] ?></span>
                        <?php else: ?>
                            <span style="color:#7a869a;">--</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#7a869a;font-size:11px;"><?= $bdd ? htmlspecialchars($bdd['last_res'] ?? '--') : '--' ?></td>
                    <td>
                        <button class="btn btn-rescrape" onclick="rescrapeOne(<?= $idE ?>, this)" title="Fetch athle.fr + insert/update BDD">
                            Re-scraper
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- LOG -->
        <div class="card" style="margin-top:14px;">
            <div style="color:#7a869a;font-size:11px;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px;">Console live</div>
            <div id="liveLog" style="background:#000;padding:10px;border-radius:6px;font-family:'JetBrains Mono',monospace;font-size:11.5px;line-height:1.6;max-height:220px;overflow-y:auto;">
                <div style="color:#7a869a;">[Pret. Clique sur un bouton Re-scraper.]</div>
            </div>
        </div>

    <?php endif; ?>
<?php endif; ?>

<script>
// Catalogue de tous les classements (pour navigation auto epreuve suivante)
// Format : [{key:"table|id", annee:2026, libelle:"800m", sexe:"Femmes", page_total:47}, ...]
const BK_CATALOGUE = <?= json_encode(array_map(function($c) {
    return [
        'key'      => $c['table'] . '|' . $c['id'],
        'annee'    => $c['annee'],
        'libelle'  => $c['libelle'],
        'sexe'     => $c['sexe'],
        'page_total' => $c['page_total'],
    ];
}, $catalogue), JSON_UNESCAPED_UNICODE) ?>;
const BK_CURRENT_KEY = <?= json_encode($selectedKey) ?>;
const BK_CURRENT_PAGE = <?= (int)$selectedPage ?>;
const BK_CURRENT_PAGE_TOTAL = <?= (int)($selected['page_total'] ?? 1) ?>;

function logLine(msg, type) {
    const box = document.getElementById('liveLog');
    if (!box) return;
    const line = document.createElement('div');
    const color = type === 'err' ? '#fca5a5' : (type === 'ok' ? '#6ee7b7' : (type === 'warn' ? '#fcd34d' : '#a5b4fc'));
    line.style.color = color;
    const ts = new Date().toTimeString().substr(0,8);
    line.textContent = '[' + ts + '] ' + msg;
    box.appendChild(line);
    box.scrollTop = box.scrollHeight;
}

// === GESTION DES CHECKBOX ANNEES (persistance localStorage) ===
const YEARS_KEY = 'bk_explore_years';
function getSelectedYears() {
    const all = document.querySelectorAll('input[data-annee]:checked');
    return Array.from(all).map(cb => parseInt(cb.dataset.annee, 10));
}
function saveSelectedYears() {
    const arr = getSelectedYears();
    localStorage.setItem(YEARS_KEY, JSON.stringify(arr));
    updateYearsCount();
    updateEpreuveQueue();
}
function loadSelectedYears() {
    let saved = [];
    try { saved = JSON.parse(localStorage.getItem(YEARS_KEY) || '[]'); } catch (e) {}
    document.querySelectorAll('input[data-annee]').forEach(cb => {
        cb.checked = saved.includes(parseInt(cb.dataset.annee, 10));
    });
    refreshYearChips();
    updateYearsCount();
    updateEpreuveQueue();
}
function yearsAll(state) {
    document.querySelectorAll('input[data-annee]').forEach(cb => cb.checked = state);
    refreshYearChips();
    saveSelectedYears();
}
function refreshYearChips() {
    document.querySelectorAll('label.year-chk').forEach(lbl => {
        const cb = lbl.querySelector('input');
        if (cb.checked) {
            lbl.style.background = '#022c22';
            lbl.style.borderColor = '#10b981';
        } else {
            lbl.style.background = '#0a0e14';
            lbl.style.borderColor = '#232b3a';
        }
    });
}
function updateYearsCount() {
    const sel = getSelectedYears();
    const el = document.getElementById('yearsCount');
    if (el) el.textContent = sel.length + ' annee' + (sel.length > 1 ? 's' : '') + ' cochee' + (sel.length > 1 ? 's' : '');
}

// === FILE D'ATTENTE EPREUVES (catalogue filtre par annees cochees) ===
function getEpreuveQueue() {
    const years = getSelectedYears();
    // Si aucune annee cochee : queue vide (force l'utilisateur a choisir)
    if (years.length === 0) return [];
    return BK_CATALOGUE.filter(c => years.includes(c.annee));
}
function getNextEpreuve() {
    const queue = getEpreuveQueue();
    if (queue.length === 0) return null;
    // Trouve l'index de l'epreuve courante dans la queue
    const idx = queue.findIndex(c => c.key === BK_CURRENT_KEY);
    if (idx === -1) {
        // Epreuve courante pas dans les annees cochees → premier de la queue
        return queue[0];
    }
    return queue[idx + 1] || null; // null si on est sur la derniere
}
function updateEpreuveQueue() {
    const el = document.getElementById('epreuveQueueInfo');
    if (!el) return;
    const queue = getEpreuveQueue();
    if (queue.length === 0) {
        el.textContent = '— aucune annee cochee, queue vide';
        return;
    }
    const next = getNextEpreuve();
    if (!next) {
        el.textContent = '— derniere epreuve de la queue (' + queue.length + ' au total)';
    } else {
        const idx = queue.findIndex(c => c.key === BK_CURRENT_KEY);
        const pos = idx === -1 ? '?' : (idx + 1);
        el.textContent = '— epreuve ' + pos + ' / ' + queue.length + ' (next: ' + next.libelle + ' ' + next.sexe + ' ' + next.annee + ')';
    }
}

// === FONCTIONS RE-SCRAPE ===
async function rescrapeOne(idExt, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Scraping...';
    logLine('Re-scrape de #' + idExt + '...', 'info');
    try {
        const fd = new FormData();
        fd.append('id', idExt);
        // Le bouton individuel ignore le skip 24h (utilisateur a clique explicitement)
        const r = await fetch('?api=rescrape', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok && !d.skipped) {
            btn.classList.add('done');
            btn.textContent = d.action + ' OK (' + d.duree_s + 's)';
            const c = d.counts;
            logLine('OK #' + idExt + ' [' + d.action + '] ' + (d.name || '(sans nom)') + ' — C:' + c.clubs + ' R:' + c.records + ' P:' + c.progressions + ' Res:' + c.resultats + ' M:' + c.medailles + ' S:' + c.selections, 'ok');
            const tr = btn.closest('tr');
            if (tr) tr.style.background = 'rgba(16,185,129,0.05)';
        } else {
            btn.classList.add('err');
            btn.textContent = 'Erreur';
            logLine('ECHEC #' + idExt + ' : ' + (d.error || 'inconnue'), 'err');
        }
    } catch (e) {
        btn.classList.add('err');
        btn.textContent = 'Net err';
        logLine('Erreur reseau #' + idExt + ' : ' + e.message, 'err');
    }
    setTimeout(() => {
        btn.disabled = false;
        if (!btn.classList.contains('done')) btn.textContent = 'Re-scraper';
    }, 2000);
}

async function rescrapeAllVisible(btn, skipConfirm) {
    if (!skipConfirm && !confirm('Re-scraper tous les athletes visibles sequentiellement ? Peut prendre 1-2 minutes.')) return;
    btn.disabled = true;
    const original = btn.textContent;
    const rows = document.querySelectorAll('tr[data-id-ext]');
    const skip24h = document.getElementById('chkSkip24h')?.checked || false;
    logLine('Demarrage : ' + rows.length + ' athletes' + (skip24h ? ' (skip 24h ON)' : '') + '...', 'warn');
    let done = 0, failed = 0, skipped = 0;
    for (const tr of rows) {
        const idExt = parseInt(tr.dataset.idExt, 10);
        const rowBtn = tr.querySelector('button.btn-rescrape');
        btn.textContent = 'En cours : ' + (done + failed + skipped + 1) + ' / ' + rows.length;
        try {
            const fd = new FormData();
            fd.append('id', idExt);
            if (skip24h) fd.append('skip_if_recent', '1');
            const r = await fetch('?api=rescrape', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok && d.skipped) {
                skipped++;
                if (rowBtn) {
                    rowBtn.style.background = '#374151';
                    rowBtn.style.color = '#9ca3af';
                    rowBtn.textContent = 'SKIP <24h (' + d.age_h + 'h)';
                    rowBtn.disabled = true;
                }
                tr.style.background = 'rgba(100,116,139,0.05)';
                logLine('SKIP #' + idExt + ' (' + d.age_h + 'h)', 'info');
                // Pas de pause athle.fr pour les skips (pas de fetch)
                continue;
            }
            if (d.ok) {
                done++;
                if (rowBtn) {
                    rowBtn.classList.add('done');
                    rowBtn.textContent = d.action + ' OK';
                    rowBtn.disabled = true;
                }
                tr.style.background = 'rgba(16,185,129,0.05)';
                logLine('OK #' + idExt + ' [' + d.action + '] ' + (d.name || '?'), 'ok');
            } else {
                failed++;
                if (rowBtn) { rowBtn.classList.add('err'); rowBtn.textContent = 'Erreur'; }
                logLine('FAIL #' + idExt + ' : ' + (d.error || 'inconnue'), 'err');
            }
        } catch (e) {
            failed++;
            logLine('Net err #' + idExt + ' : ' + e.message, 'err');
        }
        // Pause anti-ban athle.fr UNIQUEMENT si on a vraiment fetch
        await new Promise(res => setTimeout(res, 400));
    }
    btn.disabled = false;
    btn.textContent = original;
    logLine('TERMINE page : ' + done + ' OK, ' + skipped + ' skip, ' + failed + ' erreurs', 'warn');

    // === AUTO-ADVANCE : 1) page suivante si pas la derniere
    //                    2) sinon, epreuve suivante si checkbox cochee
    const chkPage    = document.getElementById('chkAutoAdvance');
    const chkEpreuve = document.getElementById('chkAutoAdvanceEpreuve');
    const pageOn    = chkPage && chkPage.checked;
    const epreuveOn = chkEpreuve && chkEpreuve.checked;

    if (BK_CURRENT_PAGE < BK_CURRENT_PAGE_TOTAL) {
        // Pas la derniere page : auto-advance page
        if (pageOn) {
            const nextPage = BK_CURRENT_PAGE + 1;
            logLine('Auto-advance PAGE → ' + nextPage + ' / ' + BK_CURRENT_PAGE_TOTAL + ' dans 3s...', 'warn');
            scheduleRedirect(3, () => {
                if (!chkPage.checked) return false; // annule si decoche
                const url = new URL(window.location.href);
                url.searchParams.set('page', nextPage);
                url.searchParams.set('autoadvance', '1');
                window.location.href = url.toString();
                return true;
            });
        } else {
            logLine('Auto-advance PAGE OFF — stop a la fin de la page', 'info');
        }
    } else {
        // Derniere page : auto-advance epreuve
        if (epreuveOn) {
            const next = getNextEpreuve();
            if (!next) {
                logLine('FINI : pas d\'epreuve suivante dans la queue (annees cochees epuisees)', 'warn');
            } else {
                logLine('Auto-advance EPREUVE → ' + next.libelle + ' ' + next.sexe + ' ' + next.annee + ' (' + next.page_total + ' pages) dans 4s...', 'warn');
                scheduleRedirect(4, () => {
                    if (!chkEpreuve.checked) return false;
                    const url = new URL(window.location.href);
                    url.searchParams.set('classement', next.key);
                    url.searchParams.set('page', '1');
                    url.searchParams.set('autoadvance', '1');
                    window.location.href = url.toString();
                    return true;
                });
            }
        } else {
            logLine('Derniere page atteinte. Auto-advance EPREUVE OFF — stop.', 'info');
        }
    }
}

function scheduleRedirect(seconds, doRedirect) {
    let remaining = seconds;
    const countdown = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            clearInterval(countdown);
            if (!doRedirect()) {
                logLine('Auto-advance ANNULE (checkbox decochee)', 'warn');
            }
        }
    }, 1000);
}

// === INITIALISATION : persistance des checkboxes + auto-relance si autoadvance=1 ===
(function() {
    // Annees
    document.querySelectorAll('input[data-annee]').forEach(cb => {
        cb.addEventListener('change', () => { refreshYearChips(); saveSelectedYears(); });
    });
    loadSelectedYears();

    // Checkboxes auto-advance : page / epreuve / skip 24h
    function bindCb(id, key, label, statusFn) {
        const cb = document.getElementById(id);
        if (!cb) return;
        cb.checked = localStorage.getItem(key) === '1';
        cb.addEventListener('change', () => {
            localStorage.setItem(key, cb.checked ? '1' : '0');
            if (statusFn) statusFn();
        });
        if (statusFn) statusFn();
    }
    function updateStatus() {
        const el = document.getElementById('autoAdvanceStatus');
        if (!el) return;
        const p = document.getElementById('chkAutoAdvance')?.checked;
        const e = document.getElementById('chkAutoAdvanceEpreuve')?.checked;
        const s = document.getElementById('chkSkip24h')?.checked;
        const parts = [];
        if (p) parts.push('page auto');
        if (e) parts.push('epreuve auto');
        if (s) parts.push('skip 24h');
        if (parts.length === 0) {
            el.textContent = 'tous OFF — s\'arretera en fin de page';
            el.style.color = '#7a869a';
        } else {
            el.textContent = '* ' + parts.join(' + ');
            el.style.color = '#10b981';
            el.style.fontWeight = '700';
        }
    }
    bindCb('chkAutoAdvance',         'bk_explore_autoadvance',         'page',    updateStatus);
    bindCb('chkAutoAdvanceEpreuve',  'bk_explore_autoadvance_epreuve', 'epreuve', updateStatus);
    bindCb('chkSkip24h',             'bk_explore_skip24h',             'skip24h', updateStatus);

    // Auto-relance si URL ?autoadvance=1
    const url = new URL(window.location.href);
    if (url.searchParams.get('autoadvance') === '1') {
        url.searchParams.delete('autoadvance');
        history.replaceState(null, '', url.toString());
        const btn = document.getElementById('btnRescrapeAll');
        if (btn) {
            logLine('Page chargee automatiquement → relance du re-scrape dans 1.5s...', 'warn');
            setTimeout(() => rescrapeAllVisible(btn, true), 1500);
        }
    }
})();
</script>

</body>
</html>
