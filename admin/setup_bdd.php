<?php
/**
 * setup_bdd.php — Fichier autonome pour créer la BDD + toutes les tables
 *
 * Usage : ouvrir dans le navigateur ou lancer en CLI
 *         → crée la BDD si elle n'existe pas
 *         → crée les 16 tables + 30 FK
 *
 * Modifier $dbname ci-dessous pour choisir le nom de la BDD.
 */

require_once dirname(__DIR__) . "/Class/DatabaseHandler.php";

// =============================================
// PARAMÈTRES À MODIFIER
// =============================================
require_once dirname(__DIR__) . "/core/credentials.php";
// =============================================
// Créer la BDD si elle n'existe pas
// =============================================
$conn = new mysqli($servername ?? 'localhost', $username, $password);
if ($conn->connect_error) {
    die("Connexion échouée : " . $conn->connect_error);
}

$dbname_esc = $conn->real_escape_string($dbname);
$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname_esc` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "<p>BDD <strong>$dbname</strong> prête.</p>";
$conn->close();

// =============================================
// Connexion à la BDD + création des tables
// =============================================
$databaseHandler = new DatabaseHandler($dbname, $username, $password);

require_once dirname(__DIR__) . "/core/dbCheck_athle.php";

$databaseHandler->closeConnection();

echo "<h2>Setup terminé !</h2>";
