<?php
/**
 * TutoController — Page Tutoriel animé
 */

class TutoController extends Controller
{
    public function index()
    {
        $apiBase = $this->config('api_base_url');
        $stats = $this->apiCall("$apiBase/stats.php");

        $this->render('tuto', [
            'page' => 'tuto',
            'seo' => SeoService::build('tuto', []),
            'nbAth'  => $stats['comptages']['athletes']['count'] ?? 330000,
            'nbClub' => $stats['comptages']['clubs']['count'] ?? 3000,
            'nbEp'   => $stats['comptages']['epreuves']['count'] ?? 400,
        ]);
    }
}
