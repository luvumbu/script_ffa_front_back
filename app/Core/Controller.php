<?php
/**
 * app/Core/Controller.php — Classe de base pour tous les controllers
 */

abstract class Controller
{
    /**
     * Rend une vue avec le layout principal
     */
    protected function render($template, $data = [])
    {
        // Injecter les variables globales
        $app = Application::getInstance();
        $data['baseUrl'] = $app->getConfig('base_url');
        $data['apiBaseUrl'] = $app->getConfig('api_base_url');

        View::renderWithLayout($template, $data);
    }

    /**
     * Appel API avec cache local
     */
    protected function apiCall($url)
    {
        return ApiService::call($url);
    }

    /**
     * Obtenir la connexion BDD
     */
    protected function getConnection()
    {
        return Application::getInstance()->getConnection();
    }

    /**
     * Obtenir la config
     */
    protected function config($key)
    {
        return Application::getInstance()->getConfig($key);
    }
}
