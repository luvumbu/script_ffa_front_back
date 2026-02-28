<?php
/**
 * json_athle.php — Exporte les données scrapées en JSON + sauvegarde dans src/
 *
 * Attend :
 *   $scraper → instance AthleteScraper avec données extraites
 *
 * Produit :
 *   - Crée le dossier src/ si il n'existe pas
 *   - Sauvegarde src/{athlete_id}.php (fichier PHP qui sert le JSON avec CORS)
 *   - Affiche un message de confirmation
 */

// Récupérer l'ID athlète depuis l'URL scrapée
$athleteId = $scraper->identite['athlete_id'] ?? 0;

// Créer le dossier src/ si il n'existe pas
$srcDir = __DIR__ . "/src";
if (!is_dir($srcDir)) {
    mkdir($srcDir, 0755);
}

// Préparer les données JSON
$data = $scraper->toArray();
$jsonString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Sauvegarder dans src/{athlete_id}.php (écrase si existe déjà)
$jsonPath = $srcDir . "/" . $athleteId . ".php";
$existed = file_exists($jsonPath);

// Le fichier .php envoie les headers CORS puis affiche le JSON brut
$phpContent = "<?php\nheader(\"Access-Control-Allow-Origin: *\");\nheader(\"Content-Type: application/json; charset=utf-8\");\n?>\n" . $jsonString;
file_put_contents($jsonPath, $phpContent);

echo $existed
    ? "<p>JSON écrasé → src/$athleteId.php</p>"
    : "<p>JSON créé → src/$athleteId.php</p>";
