<?php
/**
 * EpreuvesController — Liste des épreuves + panneau détail
 */

class EpreuvesController extends Controller
{
    public function index()
    {
        $request = new Request();
        $p = max(1, $request->getInt('p', 1));
        $nom = $request->getString('nom', '');
        $apiBase = $this->config('api_base_url');

        $params = ['page' => $p, 'limit' => 50];
        if ($nom) $params['nom'] = $nom;
        $data = $this->apiCall("$apiBase/epreuves.php?" . http_build_query($params));

        $this->render('epreuves', [
            'page' => 'epreuves',
            'seo'  => SeoService::build('epreuves', ['p' => $p]),
            'data' => $data,
            'p'    => $p,
            'nomEpreuve' => $nom,
        ]);
    }
}
