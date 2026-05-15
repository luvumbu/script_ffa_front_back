<?php
/**
 * AthletesController — Liste paginée des athlètes
 */

class AthletesController extends Controller
{
    public function index()
    {
        $request = new Request();
        $p = max(1, $request->getInt('p', 1));
        $ordre = $request->getString('ordre', 'random');
        $apiBase = $this->config('api_base_url');

        $data = $this->apiCall("$apiBase/liste.php?page=$p&limit=50&ordre=$ordre");

        $this->render('athletes', [
            'page'  => 'athletes',
            'seo'   => SeoService::build('athletes', ['p' => $p]),
            'data'  => $data,
            'p'     => $p,
            'ordre' => $ordre,
        ]);
    }
}
