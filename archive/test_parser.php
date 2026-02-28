<?php
/**
 * Script de test : charge le HTML local et teste le parsing des progressions
 * Usage : php test_parser.php  (ou via navigateur)
 */
require_once "Class/AthleteScraper.php";

// Charger le HTML sauvegardé localement
$html = file_get_contents("temp_athle.html");
if (!$html) {
    die("Impossible de lire temp_athle.html");
}

echo "<h2>Test du parsing des progressions</h2>";
echo "<p>Taille HTML : " . strlen($html) . " octets</p>";

// Créer un scraper et injecter le HTML manuellement
$scraper = new AthleteScraper(809035);

// On utilise ReflectionClass pour accéder à la propriété privée $html
$ref = new ReflectionClass($scraper);
$prop = $ref->getProperty('html');
$prop->setAccessible(true);
$prop->setValue($scraper, $html);

// Extraire l'identité d'abord (nécessaire pour getCategorieCode)
$method = $ref->getMethod('extractIdentite');
$method->setAccessible(true);
$method->invoke($scraper);

echo "<p>Année naissance : " . ($scraper->identite['annee_naissance'] ?? 'N/A') . "</p>";

// Extraire les progressions
$method = $ref->getMethod('extractProgressions');
$method->setAccessible(true);
$method->invoke($scraper);

echo "<p>Nombre de progressions : " . count($scraper->progressions) . "</p>";

// Afficher les résultats dans un tableau pour vérifier
echo "<table border='1' cellpadding='4' cellspacing='0' style='font-size:12px'>";
echo "<tr><th>#</th><th>Épreuve</th><th>Année</th><th>Perf brute</th><th>Perf int</th><th>Vent</th><th>Date</th><th>Club</th><th>Lieu</th></tr>";

$errors = 0;
foreach ($scraper->progressions as $i => $p) {
    // Détecter les anomalies : performance qui ressemble à un nom d'épreuve
    $isAnomaly = (mb_strlen($p['performance_brut']) > 15 || $p['performance'] === null);
    $style = $isAnomaly ? ' style="background:red;color:white"' : '';
    if ($isAnomaly) $errors++;

    echo "<tr$style>";
    echo "<td>" . ($i + 1) . "</td>";
    echo "<td>" . htmlspecialchars($p['epreuve']) . "</td>";
    echo "<td>" . $p['annee'] . "</td>";
    echo "<td>" . htmlspecialchars($p['performance_brut']) . "</td>";
    echo "<td>" . ($p['performance'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($p['vent']) . "</td>";
    echo "<td>" . ($p['date'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($p['club']) . "</td>";
    echo "<td>" . htmlspecialchars($p['lieu']) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Résultat : " . ($errors === 0 ? "OK - aucune anomalie" : "$errors anomalies détectées (en rouge)") . "</h3>";

// Aussi tester les épreuves uniques
$epreuves = array_unique(array_column($scraper->progressions, 'epreuve'));
echo "<h3>Épreuves détectées (" . count($epreuves) . ") :</h3><ul>";
foreach ($epreuves as $e) {
    echo "<li>" . htmlspecialchars($e) . "</li>";
}
echo "</ul>";

// Clubs uniques
$clubs = array_unique(array_column($scraper->progressions, 'club'));
echo "<h3>Clubs détectés (" . count($clubs) . ") :</h3><ul>";
foreach ($clubs as $c) {
    echo "<li>" . htmlspecialchars($c) . "</li>";
}
echo "</ul>";
