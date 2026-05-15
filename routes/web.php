<?php
/**
 * routes/web.php — Definition des routes du dashboard
 *
 * Chaque route mappe ?page=X vers un [Controller, methode]
 */

$router = $app->getRouter();

$router->get('accueil',   [AccueilController::class,       'index']);
$router->get('athletes',  [AthletesController::class,      'index']);
$router->get('recherche', [RechercheController::class,     'index']);
$router->get('profil',    [ProfilController::class,        'show']);
$router->get('clubs',     [ClubsController::class,         'index']);
$router->get('epreuves',  [EpreuvesController::class,      'index']);
$router->get('epreuve',   [EpreuveDetailController::class, 'show']);
$router->get('villes',    [VillesController::class,        'index']);
$router->get('comparer',  [ComparerController::class,      'index']);
$router->get('tuto',      [TutoController::class,          'index']);
