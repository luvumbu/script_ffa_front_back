<?php
/**
 * app/Models/Stats.php — Appels API stats
 */

class Stats
{
    public static function getGlobal()
    {
        $url = Application::getInstance()->getConfig('api_base_url');
        return ApiService::call($url . '/stats.php');
    }

    public static function getDetail($top = 30)
    {
        $url = Application::getInstance()->getConfig('api_base_url');
        return ApiService::call($url . '/stats.php?detail=1&top=' . intval($top));
    }

    /**
     * Lit le cache detail directement (pour injection JS page accueil)
     */
    public static function getDetailFromCache($top = 30)
    {
        $cacheFile = Application::getInstance()->getRootPath() . '/cache/stats_detail_' . intval($top) . '.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $json = @file_get_contents($cacheFile);
            if ($json !== false) {
                $data = json_decode($json, true);
                if ($data) return $data;
            }
        }
        return null;
    }
}
