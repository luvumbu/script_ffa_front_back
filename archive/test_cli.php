<?php
/**
 * test_cli.php — Test CLI : scrape 3 athlètes sans navigateur
 *
 * Lancement : ouvrir le terminal et taper :
 *   php C:\xampp\htdocs\BK\test_cli.php
 */

require_once "Class/DatabaseHandler.php";
require_once "Class/AthleteScraper.php";

$dbname   = "u489596434_bokonzi_on";
$username = "u489596434_bokonzi_on";
$password = "v3p9r3e@59A";

// Connexion BDD distante pour récupérer les URLs
$db = new DatabaseHandler($dbname, $username, $password);

// Test sur 3 athlètes seulement
$total = 3;

echo "=== TEST CLI — Scraping de $total athletes ===\n\n";

for ($i = 0; $i < $total; $i++) {

    echo "[$i / $total] Recherche URL... ";

    // Récupérer l'URL de l'athlète
    $sql = 'SELECT * FROM `nom_et_liens` WHERE `id_nom_et_liens`="' . $i . '";';
    $result = $db->select_custom_safe($sql, 'mes_projets');
    global $mes_projets;

    if (!$result['success'] || empty($mes_projets)) {
        echo "ERREUR : pas d'URL pour id=$i\n";
        continue;
    }

    $url = $mes_projets[0]["url"];
    echo "OK → $url\n";

    // Scraper
    echo "  Scraping... ";
    $scraper = new AthleteScraper($url);
    $scraper->scrapeAll();
    echo "OK\n";

    // Afficher les résultats
    $nom = $scraper->identite['nom_complet'] ?? 'inconnu';
    echo "  Nom          : $nom\n";
    echo "  Clubs        : " . count($scraper->clubs) . "\n";
    echo "  Medailles    : " . count($scraper->medailles) . "\n";
    echo "  Progressions : " . count($scraper->progressions) . "\n";
    echo "  Records      : " . count($scraper->records) . "\n";
    echo "  Podiums      : " . count($scraper->podiums) . "\n";
    echo "  Resultats    : " . count($scraper->resultats) . "\n";
    echo "  Niveaux      : " . count($scraper->niveaux) . "\n";
    echo "  Selections   : " . count($scraper->selections) . "\n";
    echo "\n";

    // Pause 3 secondes entre chaque
    if ($i < $total - 1) {
        echo "  Pause 3s...\n\n";
        sleep(3);
    }
}

$db->closeConnection();
echo "=== TERMINE ===\n";
