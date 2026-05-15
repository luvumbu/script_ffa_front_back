<?php
/**
 * app/Core/Application.php — Bootstrap de l'application MVC
 */

class Application
{
    private static $instance = null;
    private $config = [];
    private $router;
    private $conn = null;
    private $rootPath;

    private function __construct()
    {
        $this->rootPath = dirname(dirname(__DIR__));
        $this->config = require $this->rootPath . '/config/app.php';
        $this->router = new Router();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne le routeur
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * Retourne une valeur de config
     */
    public function getConfig($key)
    {
        return $this->config[$key] ?? null;
    }

    /**
     * Retourne la connexion mysqli (lazy-init)
     */
    public function getConnection()
    {
        if ($this->conn === null) {
            $dbConfig = require $this->rootPath . '/config/database.php';
            $this->conn = new \mysqli(
                $dbConfig['host'],
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['dbname']
            );
            $this->conn->set_charset($dbConfig['charset']);
            if ($this->conn->connect_error) {
                http_response_code(500);
                die('Connexion BDD echouee');
            }
        }
        return $this->conn;
    }

    /**
     * Chemin racine du projet
     */
    public function getRootPath()
    {
        return $this->rootPath;
    }

    /**
     * Lancer l'application
     */
    public function run()
    {
        $request = new Request();
        $this->router->dispatch($request);
    }
}
