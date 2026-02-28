<?php
/**
 * cache_urls.php — Régénère le cache local des URLs d'athlètes
 *
 * À lancer manuellement si de nouveaux athlètes sont ajoutés
 * dans la table nom_et_liens sur la BDD.
 *
 * Usage : ouvrir cache_urls.php dans le navigateur
 *         → télécharge toutes les URLs → sauvegarde urls_cache.json
 */

require_once dirname(__DIR__) . "/Class/DatabaseHandler.php";

require_once dirname(__DIR__) . "/core/credentials.php";

$databaseHandler = new DatabaseHandler($dbname, $username, $password);
$result = $databaseHandler->select_custom_safe("SELECT * FROM `nom_et_liens` ORDER BY `id_nom_et_liens`");

if ($result['success']) {
    $count = count($result['data']);
    file_put_contents(dirname(__DIR__) . "/urls_cache.json", json_encode($result['data'], JSON_UNESCAPED_UNICODE));
    echo "Cache regenere : $count URLs → urls_cache.json\n";
} else {
    echo "Erreur : " . $result['message'] . "\n";
}

$databaseHandler->closeConnection();
