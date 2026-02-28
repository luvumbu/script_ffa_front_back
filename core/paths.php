<?php
/**
 * paths.php — Definit BK_BASE pour les liens absolus
 *
 * Permet aux liens de fonctionner depuis la racine et les sous-dossiers (pages/, scraping/)
 */
if (!defined('BK_BASE')) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
    define('BK_BASE', $isLocal ? '/BK' : '');
}
