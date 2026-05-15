<?php
/**
 * AccueilController — Page d'accueil (stats globales, tops, graphiques)
 */

class AccueilController extends Controller
{
    public function index()
    {
        $apiBase = $this->config('api_base_url');

        // Stats globales
        $data = $this->apiCall("$apiBase/stats.php?detail=1&top=30");

        // Données détaillées pour injection directe (évite AJAX au premier chargement)
        $detailData = null;
        $cacheFile = Application::getInstance()->getRootPath() . '/cache/stats_detail_30.json';
        if (file_exists($cacheFile)) {
            $detailData = json_decode(file_get_contents($cacheFile), true);
        }

        $this->render('accueil', [
            'page'       => 'accueil',
            'seo'        => SeoService::build('accueil', []),
            'data'       => $data,
            'detailData' => $detailData,
        ]);
    }
}
