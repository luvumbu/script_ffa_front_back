<?php
/**
 * sitemap.php — Sitemap Index XML
 *
 * Google limite à 50 000 URLs / 50 Mo par sitemap.
 * Ce fichier génère un index qui pointe vers des sous-sitemaps paginés.
 *
 * Usage :
 *   sitemap.php          → sitemap index
 *   sitemap.php?page=0   → pages principales + clubs + epreuves + villes
 *   sitemap.php?page=1   → athlètes 1-500
 *   sitemap.php?page=2   → athlètes 501-1000
 *   ...
 */

require_once __DIR__ . '/core/db.php';

$baseUrl = 'https://bokonzi.com';
$perPage = 500;

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

$page = isset($_GET['page']) ? (int)$_GET['page'] : -1;

// === SITEMAP INDEX (par défaut) ===
if ($page < 0) {
    $r = $conn->query("SELECT COUNT(*) as c FROM athletes WHERE visible = 1");
    $total = $r ? (int)$r->fetch_assoc()['c'] : 0;
    $nbPages = ceil($total / $perPage);
    // Date réelle de dernière modification des données
    $lastModRes = $conn->query("SELECT MAX(date_creation_athlete) as last_update FROM athletes WHERE visible = 1");
    $lastUpdate = ($lastModRes && $row = $lastModRes->fetch_assoc()) ? ($row['last_update'] ?? date('Y-m-d')) : date('Y-m-d');
    $sitemapDate = substr($lastUpdate, 0, 10);
    $conn->close();
?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc><?= $baseUrl ?>/sitemap.php?page=0</loc>
        <lastmod><?= $sitemapDate ?></lastmod>
    </sitemap>
<?php for ($i = 1; $i <= $nbPages; $i++): ?>
    <sitemap>
        <loc><?= $baseUrl ?>/sitemap.php?page=<?= $i ?></loc>
        <lastmod><?= $sitemapDate ?></lastmod>
    </sitemap>
<?php endfor; ?>
</sitemapindex>
<?php
    exit;
}

// === PAGE 0 : pages principales + clubs + epreuves + villes ===
if ($page === 0) {
    $today = date('Y-m-d');
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Pages principales -->
    <url>
        <loc><?= $baseUrl ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
        <lastmod><?= $today ?></lastmod>
    </url>
    <url>
        <loc><?= $baseUrl ?>/index.php?page=athletes</loc>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
        <lastmod><?= $today ?></lastmod>
    </url>
    <url>
        <loc><?= $baseUrl ?>/index.php?page=recherche</loc>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
        <lastmod><?= $today ?></lastmod>
    </url>
    <url>
        <loc><?= $baseUrl ?>/index.php?page=clubs</loc>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
        <lastmod><?= $today ?></lastmod>
    </url>
    <url>
        <loc><?= $baseUrl ?>/index.php?page=epreuves</loc>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
        <lastmod><?= $today ?></lastmod>
    </url>
    <url>
        <loc><?= $baseUrl ?>/index.php?page=villes</loc>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
        <lastmod><?= $today ?></lastmod>
    </url>
    <url>
        <loc><?= $baseUrl ?>/pages/classement.php</loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
<?php
    // Clubs
    $res = $conn->query("SELECT nom_club FROM clubs ORDER BY nom_club");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $nom = htmlspecialchars($r['nom_club']);
            $url = $baseUrl . '/index.php?page=clubs&open=' . urlencode($r['nom_club']);
?>
    <url>
        <loc><?= htmlspecialchars($url) ?></loc>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
<?php
        }
    }

    // Epreuves
    $res = $conn->query("SELECT nom_epreuve FROM epreuves ORDER BY nom_epreuve");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $url = $baseUrl . '/index.php?page=epreuves&nom=' . urlencode($r['nom_epreuve']);
?>
    <url>
        <loc><?= htmlspecialchars($url) ?></loc>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
<?php
        }
    }

    // Villes
    $res = $conn->query("SELECT nom_ville FROM villes ORDER BY nom_ville");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $url = $baseUrl . '/index.php?page=villes&open=' . urlencode($r['nom_ville']);
?>
    <url>
        <loc><?= htmlspecialchars($url) ?></loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
<?php
        }
    }
    $conn->close();
?>
</urlset>
<?php
    exit;
}

// === PAGE 1+ : athlètes paginés (500 par page) ===
$offset = ($page - 1) * $perPage;
$res = $conn->query("SELECT athlete_id_externe FROM athletes WHERE visible = 1 ORDER BY id_athlete LIMIT $perPage OFFSET $offset");
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $idExt = (int)$row['athlete_id_externe'];
?>
    <url>
        <loc><?= $baseUrl ?>/index.php?page=profil&amp;id=<?= $idExt ?></loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
<?php
    }
}
$conn->close();
?>
</urlset>
