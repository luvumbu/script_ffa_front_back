<?php
/**
 * reset.php — Remet tout à zéro (compteur + BDD)
 *
 * Usage :
 *   reset.php           → reset compteur uniquement, redirige vers index.php
 *   reset.php?bdd=1     → reset compteur + vide TOUTES les tables, redirige vers index.php
 */

session_start();
session_destroy();

// Remet le compteur à 0
file_put_contents(dirname(__DIR__) . "/progress.txt", "0");

// Supprime le cache URLs (sera régénéré au prochain lancement)
$cacheFile = dirname(__DIR__) . "/urls_cache.json";
if (file_exists($cacheFile)) unlink($cacheFile);

// =============================================
// Si ?bdd=1 → vider toutes les tables
// =============================================
if (isset($_GET['bdd']) && $_GET['bdd'] == '1') {

    require_once dirname(__DIR__) . "/Class/DatabaseHandler.php";

    require_once dirname(__DIR__) . "/core/credentials.php";

    $databaseHandler = new DatabaseHandler($dbname, $username, $password);
    $conn = $databaseHandler->connection;

    // Désactiver les FK checks (sinon TRUNCATE refuse à cause des liaisons)
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    // Vider toutes les tables (enfants d'abord, puis parents, puis référence)
    $tables = [
        // Enfants (données liées aux athlètes)
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
        'villes',
        'clubs',
        'epreuves',
        'competitions',
        'nationalites',
        'categories',
    ];

    foreach ($tables as $table) {
        $conn->query("TRUNCATE TABLE `$table`");
    }

    // Réactiver les FK checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    // Ré-insérer les catégories FFA (vidées par TRUNCATE)
    $cats = [
        ['EA', 'Eveil Athletique', 4, 7],
        ['PO', 'Poussin',          8, 9],
        ['BE', 'Benjamin',         10, 11],
        ['MI', 'Minime',           12, 13],
        ['CA', 'Cadet',            14, 15],
        ['JU', 'Junior',           16, 17],
        ['ES', 'Espoir',           18, 22],
        ['SE', 'Senior',           23, 39],
        ['V1', 'Master 1',        40, 49],
        ['V2', 'Master 2',        50, 59],
        ['V3', 'Master 3',        60, 69],
        ['V4', 'Master 4',        70, 99],
    ];
    foreach ($cats as $c) {
        $conn->query("INSERT INTO `categories` (`code_categorie`, `nom_categorie`, `age_min`, `age_max`) VALUES ('{$c[0]}', '{$c[1]}', {$c[2]}, {$c[3]})");
    }

    $databaseHandler->closeConnection();
}

header("Location: ../index.php");
exit;
