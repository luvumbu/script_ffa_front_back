<?php
/**
 * app/Models/Ville.php — Appels API villes
 */

class Ville
{
    public static function getList($params = [])
    {
        $url = Application::getInstance()->getConfig('api_base_url');
        $defaults = ['page' => 1, 'limit' => 50, 'has_athletes' => 1];
        $params = array_merge($defaults, $params);
        return ApiService::call($url . '/villes.php?' . http_build_query($params));
    }

    public static function getStats($params = [])
    {
        $url = Application::getInstance()->getConfig('api_base_url');
        return ApiService::call($url . '/ville_stats.php?' . http_build_query($params));
    }
}
