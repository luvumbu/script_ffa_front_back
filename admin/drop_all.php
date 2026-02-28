<?php
/**
 * drop_all.php — Supprime uniquement les tables du projet athle
 *
 * Usage : http://localhost/BK/drop_all.php
 */

require_once dirname(__DIR__) . "/Class/DatabaseHandler.php";

require_once dirname(__DIR__) . "/core/credentials.php";

$databaseHandler = new DatabaseHandler($dbname, $username, $password);
$conn = $databaseHandler->connection;

// Uniquement les tables du projet athle
$tables = [
    // Enfants d'abord (FK)
    'athlete_niv_perfs',
    'athlete_niveaux',
    'athlete_resultats',
    'athlete_podiums',
    'athlete_records',
    'athlete_progressions',
    'athlete_selections',
    'athlete_medailles',
    'athlete_clubs',
    // Table principale
    'athletes',
    // Tables de référence
    'clubs',
    'epreuves',
    'competitions',
    'categories',
    'comment',
];

$conn->query("SET FOREIGN_KEY_CHECKS = 0");

foreach ($tables as $table) {
    if ($conn->query("DROP TABLE IF EXISTS `$table`")) {
        echo "DROP TABLE `$table` → OK<br/>";
    } else {
        echo "DROP TABLE `$table` → ERREUR : " . $conn->error . "<br/>";
    }
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "<br/><b>" . count($tables) . " tables supprimees.</b>";

$databaseHandler->closeConnection();
