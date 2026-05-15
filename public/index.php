<?php
/**
 * public/index.php — Point d'entree MVC
 *
 * Autoloader + bootstrap + dispatch
 */

// === Autoloader PSR-4 simplifie (sans Composer) ===
spl_autoload_register(function ($class) {
    $baseDirs = [
        'app/Core/',
        'app/Controllers/',
        'app/Models/',
        'app/Services/',
        'app/Middlewares/',
    ];
    $rootPath = dirname(__DIR__);
    foreach ($baseDirs as $dir) {
        $file = $rootPath . '/' . $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// === Charger les dependances globales ===
$rootPath = dirname(__DIR__);

// Connexion BDD via core/db.php (rend $conn disponible globalement)
require_once $rootPath . '/core/db.php';

// IP logger
require_once $rootPath . '/core/ip_logger.php';
logIp();

// Anti-scraping (20 pages/jour)
AntiScrapingService::check();

// === Bootstrap Application ===
$app = Application::getInstance();

// Charger les routes
require $rootPath . '/routes/web.php';

// Dispatcher la requete
$app->run();
