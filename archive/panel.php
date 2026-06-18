<?php
require_once __DIR__ . '/core/db.php';

// Stats rapides
$totalAthletes = 0;
$totalRecords = 0;
$totalClubs = 0;
$totalEpreuves = 0;
$totalSrc = 0;

$r = $conn->query("SELECT COUNT(*) as c FROM athletes"); if ($r) $totalAthletes = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM athlete_records"); if ($r) $totalRecords = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM clubs"); if ($r) $totalClubs = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM epreuves"); if ($r) $totalEpreuves = (int)$r->fetch_assoc()['c'];

$srcDir = __DIR__ . '/src';
if (is_dir($srcDir)) {
    $totalSrc = count(glob($srcDir . '/*.php'));
}

// Fichiers de progression
$progressScraper = file_exists(__DIR__ . '/progress.txt') ? (int)file_get_contents(__DIR__ . '/progress.txt') : 0;
$progressSync = file_exists(__DIR__ . '/progress_absents.txt') ? (int)file_get_contents(__DIR__ . '/progress_absents.txt') : 0;
$absentsExist = file_exists(__DIR__ . '/absents2.json');
$failedCount = 0;
if (file_exists(__DIR__ . '/failed.json')) {
    $f = json_decode(file_get_contents(__DIR__ . '/failed.json'), true);
    $failedCount = is_array($f) ? count($f) : 0;
}
$failedAbsCount = 0;
if (file_exists(__DIR__ . '/failed_absents.json')) {
    $f = json_decode(file_get_contents(__DIR__ . '/failed_absents.json'), true);
    $failedAbsCount = is_array($f) ? count($f) : 0;
}

// Cache
$cacheDir = __DIR__ . '/cache';
$cacheCount = is_dir($cacheDir) ? count(glob($cacheDir . '/*.json')) : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Panel — Bokonzi</title>
<link rel="stylesheet" href="common.css">
<style>
h1 { font-size:26px; }
.subtitle { margin-bottom:30px; }
.stats { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px; margin-bottom:32px; }
.stat { padding:16px; }
.stat .val { font-size:26px; }
</style>
</head>
<body class="panel-body">

<h1><span>&#9881;</span> Panel Bokonzi</h1>
<div class="subtitle">Tour de controle — administration et outils</div>

<div class="stats">
    <div class="stat" style="background:#60a5fa10;border:1px solid #60a5fa25;">
        <div class="val" style="color:#60a5fa;"><?= number_format($totalAthletes, 0, '', ' ') ?></div>
        <div class="label">Athletes BDD</div>
    </div>
    <div class="stat" style="background:#34d39910;border:1px solid #34d39925;">
        <div class="val" style="color:#34d399;"><?= number_format($totalSrc, 0, '', ' ') ?></div>
        <div class="label">Fichiers src/</div>
    </div>
    <div class="stat" style="background:#a78bfa10;border:1px solid #a78bfa25;">
        <div class="val" style="color:#a78bfa;"><?= number_format($totalRecords, 0, '', ' ') ?></div>
        <div class="label">Records</div>
    </div>
    <div class="stat" style="background:#fbbf2410;border:1px solid #fbbf2425;">
        <div class="val" style="color:#fbbf24;"><?= number_format($totalClubs, 0, '', ' ') ?></div>
        <div class="label">Clubs</div>
    </div>
    <div class="stat" style="background:#f472b610;border:1px solid #f472b625;">
        <div class="val" style="color:#f472b6;"><?= number_format($totalEpreuves, 0, '', ' ') ?></div>
        <div class="label">Epreuves</div>
    </div>
    <div class="stat" style="background:#8b949e10;border:1px solid #8b949e25;">
        <div class="val" style="color:#8b949e;"><?= $cacheCount ?></div>
        <div class="label">Fichiers cache</div>
    </div>
</div>

<hr class="sep">

<h2 class="section-title">Pages principales</h2>
<div class="grid">
    <div class="card">
        <div class="card-title"><span class="icon">&#128202;</span> Dashboard</div>
        <div class="card-desc">Interface complete : athletes, recherche, profils, clubs, epreuves, villes, comparaison.</div>
        <div class="actions">
            <a href="index.php" class="btn btn-primary">Ouvrir</a>
            <a href="index.php?page=athletes" class="btn btn-blue">Athletes</a>
            <a href="index.php?page=recherche" class="btn btn-blue">Recherche</a>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">&#128203;</span> Statistiques</div>
        <div class="card-desc">Vue globale des donnees : totaux, repartitions, top athletes, top clubs.</div>
        <div class="actions">
            <a href="index.php?page=accueil" class="btn btn-primary">Voir stats</a>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">&#128100;</span> Profil athlete</div>
        <div class="card-desc">Page publique d'un athlete avec toutes ses donnees detaillees.</div>
        <div class="actions">
            <a href="pages/profil.php" class="btn btn-primary">Profil public</a>
            <a href="pages/global_athlete.php" class="btn btn-blue">Recherche globale</a>
        </div>
    </div>
</div>

<hr class="sep">

<h2 class="section-title">Scraping &amp; Synchronisation</h2>
<div class="grid">
    <div class="card">
        <div class="card-title"><span class="icon">&#128268;</span> Scraper principal</div>
        <div class="card-desc">Scrape athle.fr pour tous les athletes de nom_et_liens. Batch de 7, pause entre chaque, auto-refresh.</div>
        <div class="card-info">Progression : <span><?= number_format($progressScraper, 0, '', ' ') ?></span><?= $failedCount > 0 ? " | Echecs : <span style='color:#f87171'>$failedCount</span>" : '' ?></div>
        <div class="actions">
            <a href="scraping/scraper.php" class="btn btn-green">Lancer</a>
            <a href="admin/reset.php" class="btn btn-red">Reset</a>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">&#128260;</span> Check &amp; Sync</div>
        <div class="card-desc">Verifie les fichiers src/ manquants via nom_et_liens puis scrape automatiquement les absents.</div>
        <div class="card-info"><?= $absentsExist ? "absents2.json present | Progression : <span>$progressSync</span>" : "Pas encore lance" ?><?= $failedAbsCount > 0 ? " | Echecs : <span style='color:#f87171'>$failedAbsCount</span>" : '' ?></div>
        <div class="actions">
            <a href="scraping/check_sync.php" class="btn btn-green">Lancer</a>
            <a href="scraping/check_sync.php?reset=1" class="btn btn-red">Reset</a>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">&#128269;</span> Check Athletes BDD</div>
        <div class="card-desc">Verifie si les fichiers src/ existent pour les athletes en base (table athletes). Genere absents.json.</div>
        <div class="actions">
            <a href="scraping/check_athletes.php" class="btn btn-orange">Lancer</a>
        </div>
    </div>
</div>

<hr class="sep">

<h2 class="section-title">API &amp; Donnees</h2>
<div class="grid">
    <div class="card">
        <div class="card-title"><span class="icon">&#128640;</span> API Stats globales</div>
        <div class="card-desc">Statistiques generales : totaux athletes, records, medailles, clubs, epreuves.</div>
        <div class="actions">
            <a href="api/stats.php" class="btn btn-blue" target="_blank">stats.php</a>
            <a href="api/liste.php" class="btn btn-blue" target="_blank">liste.php</a>
            <a href="api/search.php" class="btn btn-blue" target="_blank">search.php</a>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">&#128451;</span> Cache</div>
        <div class="card-desc"><?= $cacheCount ?> fichiers en cache (TTL 24h). Suppression manuelle si besoin.</div>
        <div class="actions">
            <a href="admin/cache_clear.php" class="btn btn-red">Vider le cache</a>
        </div>
    </div>
</div>

<hr class="sep">

<h2 class="section-title">Administration</h2>
<div class="grid">
    <div class="card">
        <div class="card-title"><span class="icon">&#128274;</span> Authentification</div>
        <div class="card-desc">Connexion et inscription des utilisateurs.</div>
        <div class="actions">
            <a href="login.php" class="btn btn-blue">Login</a>
            <a href="register.php" class="btn btn-blue">Register</a>
        </div>
    </div>
</div>

<div style="margin-top:40px;text-align:center;color:#4b5563;font-size:12px;">
    Bokonzi Panel — <?= date('d/m/Y H:i') ?>
</div>

</body>
</html>
