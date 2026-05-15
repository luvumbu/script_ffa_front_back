<?php
/**
 * config/database.php — Configuration base de donnees
 * Lit les identifiants depuis core/credentials.php pour compatibilite
 */

require_once dirname(__DIR__) . '/core/credentials.php';

return [
    'host'     => 'localhost',
    'username' => $username,
    'password' => $password,
    'dbname'   => $dbname,
    'charset'  => 'utf8mb4',
];
