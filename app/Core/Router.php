<?php
/**
 * app/Core/Router.php — Routeur simple base sur ?page=X
 */

class Router
{
    private $routes = [];

    /**
     * Enregistre une route : page => [ControllerClass, method]
     */
    public function get($page, $handler)
    {
        $this->routes[$page] = $handler;
    }

    /**
     * Dispatche la requete vers le bon controller
     */
    public function dispatch(Request $request)
    {
        $page = $request->getPage();

        if (!isset($this->routes[$page])) {
            Response::notFound();
            return;
        }

        $handler = $this->routes[$page];
        $class = $handler[0];
        $method = $handler[1];

        $controller = new $class();
        $controller->$method($request);
    }

    /**
     * Verifie si une route existe
     */
    public function hasRoute($page)
    {
        return isset($this->routes[$page]);
    }

    /**
     * Retourne toutes les routes enregistrees
     */
    public function getRoutes()
    {
        return $this->routes;
    }
}
