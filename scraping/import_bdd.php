<?php
/**
 * import_bdd.php — Importe les fichiers JSON (src/) en BDD
 *
 * Lit les fichiers src/{id}.php un par un → insère en BDD
 * Autonome avec Refresh: 1
 *
 * Usage : http://localhost/BK/import_bdd.php
 *         ou sur Hostinger
 *
 * Reset : supprimer import_progress.txt pour recommencer
 */

ob_start();
session_start();

require_once dirname(__DIR__) . "/core/credentials.php";

require_once dirname(__DIR__) . "/Class/DatabaseHandler.php";
require_once dirname(__DIR__) . "/core/insert_athle.php";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import BDD</title>
    <style>
        body { background-color: black; color: green; font-family: monospace; }
        .timer { color: cyan; }
        .error { color: red; }
        .skip { color: orange; }
    </style>
</head>
<body>

<a href="../admin/reset.php"><img width="64" height="64" src="https://img.icons8.com/sf-black/64/FA5252/recurring-appointment.png" alt="recurring-appointment"/></a>

<?php
// =============================================
// 1. Scanner les fichiers JSON dans src/
// =============================================
$srcDir = dirname(__DIR__) . "/src";

if (!is_dir($srcDir)) {
    die("<p class='error'>Dossier src/ introuvable. Lance d'abord index.php pour scraper.</p>");
}

// Lister tous les fichiers .php dans src/ (triés par nom)
$files = glob($srcDir . "/*.php");
sort($files);
$totalFiles = count($files);

if ($totalFiles === 0) {
    die("<p class='error'>Aucun fichier JSON dans src/. Lance d'abord index.php pour scraper.</p>");
}

// =============================================
// 2. Connexion BDD + schema
// =============================================
$databaseHandler = new DatabaseHandler($dbname, $username, $password);
require_once dirname(__DIR__) . "/core/dbCheck_athle.php";

$conn = $databaseHandler->connection;

// =============================================
// 3. Cache mémoire des tables de référence
// =============================================
$cache = loadRefCache($conn);

// =============================================
// 4. Progression (fichier séparé de index.php)
// =============================================
$progressFile = dirname(__DIR__) . "/import_progress.txt";

if (!isset($_SESSION["import"])) {
    $_SESSION["import"] = file_exists($progressFile) ? (int)file_get_contents($progressFile) : 0;
}
$current = $_SESSION["import"];

// Terminé ?
if ($current >= $totalFiles) {
    echo "<h2>TERMINE ! $totalFiles athletes importes en BDD.</h2>";
    echo "</body></html>";
    exit;
}

$remaining = $totalFiles - $current;
echo "<h2>Import BDD : $current / $totalFiles | Restant : $remaining</h2>";

// =============================================
// 5. Lire le fichier JSON + insérer en BDD
// =============================================
$file = $files[$current];
$fileName = basename($file, ".php");

$t0 = microtime(true);
echo "<h3>Fichier : src/$fileName.php</h3>";

// Lire le contenu JSON (le fichier PHP contient des headers + JSON)
$rawContent = file_get_contents($file);

// Extraire le JSON (après la balise de fermeture PHP "?>")
$jsonStart = strpos($rawContent, "?>\n");
if ($jsonStart === false) {
    $jsonStart = strpos($rawContent, "?>");
}

if ($jsonStart !== false) {
    $jsonString = substr($rawContent, $jsonStart + 3);
} else {
    // Pas de PHP, c'est du JSON pur
    $jsonString = $rawContent;
}

$data = json_decode(trim($jsonString), true);

if (!$data || !isset($data['identite'])) {
    echo "<p class='error'>JSON invalide ou vide → skip</p>";
} else {
    // Créer un objet simulé compatible avec insertAthleteData()
    $scraper = new stdClass();
    $scraper->identite     = $data['identite']     ?? [];
    $scraper->clubs        = $data['clubs']        ?? [];
    $scraper->medailles    = $data['medailles']    ?? [];
    $scraper->selections   = $data['selections']   ?? [];
    $scraper->progressions = $data['progressions'] ?? [];
    $scraper->records      = $data['records']      ?? [];
    $scraper->podiums      = $data['podiums']      ?? [];
    $scraper->resultats    = $data['resultats']    ?? [];
    $scraper->niveaux      = $data['niveaux']      ?? [];

    $nom = htmlspecialchars($scraper->identite['nom_complet'] ?? 'inconnu');
    echo "<p>$nom</p>";

    // Insertion optimisée
    insertAthleteData($scraper, $conn, $cache);

    $elapsed = round((microtime(true) - $t0) * 1000);
    echo "<p class='timer'>→ {$elapsed}ms</p>";
}

// =============================================
// 6. Sauvegarder + refresh automatique
// =============================================
$databaseHandler->closeConnection();

$_SESSION["import"] = $current + 1;
file_put_contents($progressFile, $current + 1);

header("Refresh: 1");
?>

</body>
</html>
