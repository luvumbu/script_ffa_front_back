<?php
/**
 * EpreuveDetailController — Page détail épreuve (classement server-side)
 */

class EpreuveDetailController extends Controller
{
    public function show()
    {
        $request = new Request();
        $apiBase = $this->config('api_base_url');
        $nom = $request->getString('nom', '');

        $data = null;
        if ($nom) {
            $data = $this->apiCall("$apiBase/epreuve_stats.php?nom=" . urlencode($nom));
        }

        $this->render('epreuve-detail', [
            'page' => 'epreuve',
            'seo'  => SeoService::build('epreuve', ['nom' => $nom]),
            'data' => $data,
            'nom'  => $nom,
        ]);
    }
}
