<?php
/**
 * core/db.php — Connexion BDD uniquement (sans headers HTTP)
 * Utilise par les pages frontend (login, register, profil, performances)
 * et par api/config.php
 */

require_once __DIR__ . '/credentials.php';

$conn = new mysqli("localhost", $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    http_response_code(500);
    die("Connexion BDD echouee");
}
