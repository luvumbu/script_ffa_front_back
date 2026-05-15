<?php
/**
 * ClubsController — Liste des clubs + panneau détail
 */

class ClubsController extends Controller
{
    public function index()
    {
        $request = new Request();
        $p = max(1, $request->getInt('p', 1));
        $nom = $request->getString('nom', '');
        $apiBase = $this->config('api_base_url');

        $params = ['page' => $p, 'limit' => 50];
        if ($nom) $params['nom'] = $nom;
        $data = $this->apiCall("$apiBase/clubs.php?" . http_build_query($params));

        $this->render('clubs', [
            'page'    => 'clubs',
            'seo'     => SeoService::build('clubs', ['p' => $p]),
            'data'    => $data,
            'p'       => $p,
            'nomClub' => $nom,
        ]);
    }
}
