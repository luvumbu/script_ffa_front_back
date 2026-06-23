<?php
/**
 * hub.php — Centre de controle SCRAPING (tout au meme endroit).
 *
 * Une seule page pour :
 *   - voir les compteurs cles en direct (athletes, nom_et_liens, clubs, runner ON/OFF)
 *   - acceder a TOUS les outils de scraping, groupes par usage
 *
 * Reserve ADMIN (bkUserCanPurge / bk_sa_token). Propage ?bk_key= dans tous les liens.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/athlete_purge.php'; // bkUserCanPurge()

// --- Acces admin -----------------------------------------------------------
if (!bkUserCanPurge($conn)) {
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset="utf-8"><body style="background:#0d1117;color:#e6edf3;font-family:sans-serif;padding:40px;text-align:center;"><h1>403 — Acces reserve</h1><p>Ajoute ta cle : <code>?bk_key=…</code></p></body>';
    exit;
}

$bkKey = $_GET['bk_key'] ?? '';
$keyQs = $bkKey !== '' ? ('?bk_key=' . urlencode($bkKey)) : '';
// Helper liens : ajoute la cle proprement (gere le ? deja present)
function L(string $href): string {
    global $bkKey;
    if ($bkKey === '') return $href;
    $sep = (strpos($href, '?') !== false) ? '&' : '?';
    return $href . $sep . 'bk_key=' . urlencode($bkKey);
}

// --- Compteurs (legers / tolerants aux erreurs) ---------------------------
function q1(mysqli $conn, string $sql): int {
    $r = @$conn->query($sql);
    if ($r && ($row = $r->fetch_row())) return (int)$row[0];
    return 0;
}
$nAthletes = q1($conn, "SELECT COUNT(*) FROM athletes");
$nLiens    = q1($conn, "SELECT COUNT(*) FROM nom_et_liens");
$nClubs    = q1($conn, "SELECT COUNT(*) FROM clubs");
$restants  = max(0, $nLiens - $nAthletes);
$pct       = $nLiens > 0 ? round($nAthletes / $nLiens * 100, 1) : 0;

// Runner principal + v2 (flags)
$flagMain = file_exists(dirname(__DIR__) . '/scraping/scraping_running.flag');
$flagV2   = file_exists(__DIR__ . '/state/scraper_v2_running.flag');

// Derniers scrapes (athlete_scrape_log si dispo)
$lastScrapes = [];
$rL = @$conn->query("SELECT athlete_id_ext, last_action, last_scraped_at
                     FROM athlete_scrape_log ORDER BY last_scraped_at DESC LIMIT 6");
if ($rL) while ($row = $rL->fetch_assoc()) $lastScrapes[] = $row;

// --- Catalogue des outils --------------------------------------------------
// existe? => on n'affiche que les fichiers presents
function ex(string $rel): bool { return is_file(dirname(__DIR__) . '/' . ltrim($rel, '/')); }

$groups = [
    'Découverte — faire entrer les athlètes en base' => [
        ['scraping_v2/index.php',              '🧠', 'Analyseur intelligent',  'Choisir une table + années à cocher, puis scraper les classements.'],
        ['scraping_v2/par_annee.php',          '📅', 'Par année',              '1 année = tout scraper. Couverture BDD vs athle.fr par année.'],
        ['scraping_v2/par_epreuve.php',        '🏃', 'Par épreuve',            'Vérifier / Scraper / MAJ profils, épreuve par épreuve (console live).'],
        ['scraping_v2/explore_classement.php', '🔍', 'Explorer un classement', 'Drill-down page par page, comparaison BDD, re-scrape ciblé.'],
        ['scraping/scraper.php',               '⚙️', 'Scraper principal',      'Orchestrateur batch (nom_et_liens → 9 tables). Start/Stop persistant.'],
    ],
    'Maintenance — mettre à jour ce qui est déjà en base' => [
        ['scraping_v2/update_club.php',        '🏟', 'MAJ un club entier',     'Re-scrape tous les membres d\'un club. Reprenable (sauvegarde d\'état).'],
        ['scraping_v2/update_profile.php',     '👤', 'MAJ un profil',          'Re-scrape un athlète précis depuis sa fiche FFA.'],
        ['scraping/refresh_existing.php',      '🔄', 'Rafraîchir (par date)',  'Re-scrape tous les athlètes ajoutés depuis une date (console live).'],
        ['scraping/rescrape_selections.php',   '🎽', 'Re-scrape sélections',   'Re-scrape ciblé des sélections uniquement.'],
    ],
    'Suivi & diagnostic' => [
        ['scraping_v2/jarvis.php',             '📡', 'HUD live (Jarvis)',      'Tableau de bord temps réel : jauge, compteurs, console.'],
        ['scraping_v2/diagnose.php',           '🩺', 'Diagnostic',             'Vue d\'ensemble pré-formatée du pipeline v2.'],
        ['admin/audit_athletes.php',           '📋', 'Audit athlètes',         'Athlètes incomplets (8 tables enfants), top 200, re-scrape.'],
        ['admin/remote_check.php',             '🛰', 'Remote check (API)',     'État pipelines, compteurs, prog_diag, test_scrape à distance.'],
    ],
    'Nettoyage & import' => [
        ['scraping_v2/cleanup_duplicates.php', '🧹', 'Doublons nom_et_liens',  'Nettoyage en 3 phases (index → analyze → delete → unique).'],
        ['scraping/check_sync.php',            '✅', 'Vérifier la synchro',    'Compare nom_et_liens vs src/ et scrape les absents.'],
        ['scraping/check_athletes.php',        '🔎', 'Audit complétude',       'Compare table athletes vs fichiers src/ → absents.json.'],
        ['scraping/import_bdd.php',            '📥', 'Import JSON → BDD',       'Importe les fichiers src/*.php dans la base.'],
        ['scraping_v2/warm_cache.php',         '🔥', 'Préchauffer le cache',   'Pré-génère le cache d\'URLs.'],
        ['scraping_v2/test_page.php',          '🧪', 'Tester le parser',       'Teste l\'extraction sur une URL unique.'],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Centre de contrôle — Scraping BOKONZI</title>
<style>
    :root { --bg:#0d1117; --card:#161b22; --card2:#1c2330; --border:#30363d; --text:#e6edf3; --muted:#8b949e;
            --green:#22c55e; --red:#ef4444; --blue:#3b82f6; --violet:#a78bfa; --orange:#f59e0b; }
    * { box-sizing:border-box; }
    body { background:var(--bg); color:var(--text); font-family:system-ui,Segoe UI,sans-serif; margin:0; padding:24px; }
    .wrap { max-width:1200px; margin:0 auto; }
    h1 { font-size:24px; margin:0 0 4px; display:flex; align-items:center; gap:10px; }
    .sub { color:var(--muted); font-size:14px; margin:0 0 20px; }
    /* KPIs */
    .kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:14px; }
    .kpi { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:14px 16px; }
    .kpi .v { font-size:26px; font-weight:800; font-variant-numeric:tabular-nums; }
    .kpi .l { color:var(--muted); font-size:13px; margin-top:2px; }
    .kpi.athletes .v{color:#79c0ff;} .kpi.liens .v{color:var(--violet);} .kpi.clubs .v{color:#f0a;}
    .kpi.rest .v{color:var(--orange);}
    .bar { height:10px; background:#0d1117; border:1px solid var(--border); border-radius:99px; overflow:hidden; margin:6px 0 22px; }
    .bar > div { height:100%; background:linear-gradient(90deg,#6d28d9,#22c55e); }
    .status { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:22px; font-size:13px; }
    .dot { display:inline-flex; align-items:center; gap:7px; background:var(--card); border:1px solid var(--border); padding:7px 13px; border-radius:99px; }
    .dot b{ width:9px; height:9px; border-radius:50%; display:inline-block; }
    .on{ background:var(--green); box-shadow:0 0 8px var(--green); } .off{ background:#4b5563; }
    /* Groupes */
    .group { margin-bottom:26px; }
    .group > h2 { font-size:15px; color:var(--violet); margin:0 0 12px; text-transform:uppercase; letter-spacing:.04em; border-left:3px solid var(--violet); padding-left:10px; }
    .cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:12px; }
    a.tool { display:block; text-decoration:none; color:inherit; background:var(--card); border:1px solid var(--border); border-radius:12px; padding:16px; transition:.15s; }
    a.tool:hover { border-color:var(--violet); background:var(--card2); transform:translateY(-2px); box-shadow:0 8px 22px rgba(0,0,0,.45); }
    a.tool .top { display:flex; align-items:center; gap:10px; margin-bottom:7px; }
    a.tool .ico { font-size:22px; }
    a.tool .ttl { font-weight:700; font-size:15px; }
    a.tool .desc { color:var(--muted); font-size:13px; line-height:1.45; }
    .recent { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:14px 16px; margin-bottom:22px; font-size:13px; }
    .recent h3 { margin:0 0 8px; font-size:13px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; }
    .recent a { color:#58a6ff; text-decoration:none; }
    .recent .maj{color:var(--violet);} .recent .new{color:#79c0ff;}
    .recent .row { display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid #1c2330; }
    .recent .row:last-child{ border-bottom:none; }
</style>
</head>
<body>
<div class="wrap">
    <h1>🛰 Centre de contrôle — Scraping</h1>
    <p class="sub">Tous les outils au même endroit. Compteurs en direct ci-dessous, outils groupés par usage.</p>

    <div class="kpis">
        <div class="kpi athletes"><div class="v"><?= number_format($nAthletes, 0, ',', ' ') ?></div><div class="l">Athlètes en base</div></div>
        <div class="kpi liens"><div class="v"><?= number_format($nLiens, 0, ',', ' ') ?></div><div class="l">URLs (nom_et_liens)</div></div>
        <div class="kpi clubs"><div class="v"><?= number_format($nClubs, 0, ',', ' ') ?></div><div class="l">Clubs</div></div>
        <div class="kpi rest"><div class="v"><?= number_format($restants, 0, ',', ' ') ?></div><div class="l">Restants à scraper</div></div>
    </div>
    <div class="bar" title="<?= $pct ?>% des URLs sont en base"><div style="width:<?= $pct ?>%;"></div></div>

    <div class="status">
        <span class="dot"><b class="<?= $flagMain ? 'on' : 'off' ?>"></b> Scraper principal : <?= $flagMain ? 'EN COURS' : 'arrêté' ?></span>
        <span class="dot"><b class="<?= $flagV2 ? 'on' : 'off' ?>"></b> Scraper v2 : <?= $flagV2 ? 'EN COURS' : 'arrêté' ?></span>
        <span class="dot">📊 <?= $pct ?>% couvert</span>
    </div>

    <?php if ($lastScrapes): ?>
    <div class="recent">
        <h3>Derniers athlètes scrapés</h3>
        <?php foreach ($lastScrapes as $s): ?>
            <div class="row">
                <span>
                    <span class="<?= strtolower($s['last_action']) ?>"><?= htmlspecialchars($s['last_action']) ?></span>
                    — <a href="https://athle.fr/athletes/<?= (int)$s['athlete_id_ext'] ?>/bilans" target="_blank">#<?= (int)$s['athlete_id_ext'] ?></a>
                </span>
                <span class="muted" style="color:var(--muted);"><?= htmlspecialchars($s['last_scraped_at']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($groups as $title => $tools): ?>
    <div class="group">
        <h2><?= htmlspecialchars($title) ?></h2>
        <div class="cards">
            <?php foreach ($tools as [$rel, $ico, $ttl, $desc]):
                if (!ex($rel)) continue; ?>
                <a class="tool" href="<?= htmlspecialchars(L('../' . $rel)) ?>">
                    <div class="top"><span class="ico"><?= $ico ?></span><span class="ttl"><?= htmlspecialchars($ttl) ?></span></div>
                    <div class="desc"><?= htmlspecialchars($desc) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <p class="sub" style="margin-top:20px;">⚠ Outils réservés à l'administration. Chaque lien transporte ta clé pour rester connecté.</p>
</div>
</body>
</html>
