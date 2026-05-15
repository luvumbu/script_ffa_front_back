<?php
/**
 * VillesController — Liste villes + détail ville (server-side)
 */

class VillesController extends Controller
{
    public function index()
    {
        $request = new Request();
        $apiBase = $this->config('api_base_url');
        $p = max(1, $request->getInt('p', 1));
        $openVille = $request->getString('open', '');

        if ($openVille) {
            // Mode détail ville
            $this->showDetail($request, $apiBase, $openVille);
        } else {
            // Mode liste
            $this->showList($request, $apiBase, $p);
        }
    }

    private function showList($request, $apiBase, $p)
    {
        $nomVille = $request->getString('nom', '');
        $params = ['page' => $p, 'limit' => 50, 'has_athletes' => 1];
        if ($nomVille) $params['nom'] = $nomVille;
        $data = $this->apiCall("$apiBase/villes.php?" . http_build_query($params));

        $this->render('villes', [
            'page'     => 'villes',
            'seo'      => SeoService::build('villes', ['p' => $p]),
            'data'     => $data,
            'p'        => $p,
            'nomVille' => $nomVille,
            'mode'     => 'list',
        ]);
    }

    private function showDetail($request, $apiBase, $openVille)
    {
        $data = $this->apiCall("$apiBase/ville_stats.php?nom=" . urlencode($openVille));

        // Filtres additionnels
        $niv = $request->getString('niv', '');
        $nat = $request->getString('nat', '');
        $ans = $request->getString('ans', '');

        if ($niv || $nat || $ans) {
            $params = ['nom' => $openVille];
            if ($niv) $params['niv'] = $niv;
            if ($nat) $params['nat'] = $nat;
            if ($ans) $params['ans'] = $ans;
            $data = $this->apiCall("$apiBase/ville_stats.php?" . http_build_query($params));
        }

        $p = max(1, $request->getInt('p', 1));

        $this->render('villes', [
            'page'      => 'villes',
            'seo'       => SeoService::build('villes', ['open' => $openVille]),
            'data'      => $data,
            'p'         => $p,
            'openVille' => $openVille,
            'niv'       => $niv,
            'nat'       => $nat,
            'ans'       => $ans,
            'mode'      => 'detail',
        ]);
    }
}
