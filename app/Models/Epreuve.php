<?php
/**
 * app/Models/Epreuve.php — Appels API epreuves
 */

class Epreuve
{
    public static function getList($params = [])
    {
        $url = Application::getInstance()->getConfig('api_base_url');
        $defaults = ['page' => 1, 'limit' => 50, 'has_athletes' => 1];
        $params = array_merge($defaults, $params);
        return ApiService::call($url . '/epreuves.php?' . http_build_query($params));
    }

    public static function getStats($params = [])
    {
        $url = Application::getInstance()->getConfig('api_base_url');
        return ApiService::call($url . '/epreuve_stats.php?' . http_build_query($params));
    }

    public static function getRecords($nom)
    {
        $url = Application::getInstance()->getConfig('api_base_url');
        return ApiService::call($url . '/epreuve_records.php?nom=' . urlencode($nom));
    }
}
