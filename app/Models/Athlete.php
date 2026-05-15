<?php
/**
 * app/Models/Athlete.php — Appels API athletes
 */

class Athlete
{
    public static function getById($id)
    {
        $url = Application::getInstance()->getConfig('api_base_url');
        return ApiService::call($url . '/athlete.php?id=' . intval($id));
    }

    public static function getByInternalId($idAthlete)
    {
        $url = Application::getInstance()->getConfig('api_base_url');
        return ApiService::call($url . '/athlete.php?id_athlete=' . intval($idAthlete));
    }

    public static function getList($page = 1, $limit = 50, $ordre = 'nom')
    {
        $url = Application::getInstance()->getConfig('api_base_url');
        return ApiService::call($url . '/liste.php?' . http_build_query([
            'page'  => $page,
            'limit' => $limit,
            'ordre' => $ordre,
        ]));
    }

    public static function search($filters = [])
    {
        $url = Application::getInstance()->getConfig('api_base_url');
        return ApiService::call($url . '/search.php?' . http_build_query($filters));
    }
}
