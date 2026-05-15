<?php
/**
 * RechercheController — Recherche avancée 12 filtres
 */

class RechercheController extends Controller
{
    public function index()
    {
        $request = new Request();
        $apiBase = $this->config('api_base_url');
        $conn = $this->getConnection();
        $p = max(1, $request->getInt('p', 1));

        // Collecter les filtres
        $filters = [];
        $filterKeys = ['nom','nom1','nom2','club','categorie','sexe','nationalite','epreuve','ville','competition','medaille','annee','licence'];
        foreach ($filterKeys as $k) {
            $v = $request->getString($k, '');
            if ($v !== '') $filters[$k] = $v;
        }
        $filters['page'] = $p;
        $filters['limit'] = 50;

        $data = null;
        if (!empty($filters['nom']) || !empty($filters['club']) || !empty($filters['epreuve']) || !empty($filters['nationalite']) || !empty($filters['sexe']) || !empty($filters['categorie']) || !empty($filters['ville']) || !empty($filters['competition']) || !empty($filters['medaille']) || !empty($filters['annee']) || !empty($filters['licence']) || !empty($filters['nom1']) || !empty($filters['nom2'])) {
            $data = $this->apiCall("$apiBase/search.php?" . http_build_query($filters));
        }

        // Select nationalités depuis BDD pour le formulaire
        $nationalites = [];
        if ($conn) {
            $res = $conn->query("SELECT code_nationalite, COUNT(DISTINCT a.id_athlete) as nb FROM nationalites n JOIN athletes a ON a.id_nationalite = n.id_nationalite GROUP BY n.id_nationalite, n.code_nationalite ORDER BY nb DESC");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $nationalites[] = $row;
                }
            }
        }

        // Titre club si filtre club actif
        $clubFilter = trim($filters['club'] ?? '');
        $clubTitle = '';
        if ($clubFilter) {
            $clubTitle = $clubFilter;
        }

        // Search tracking
        if ($clubFilter && $conn) {
            $clubData = $this->apiCall("$apiBase/clubs.php?nom=" . urlencode($clubFilter) . "&limit=1");
            $clubId = $clubData['clubs'][0]['id_club'] ?? null;
            if ($clubId) {
                SearchTrackingService::track($conn, 'club', 'page_view', $clubId, $clubFilter);
            }
        }

        $this->render('recherche', [
            'page'         => 'recherche',
            'seo'          => SeoService::build('recherche', $filters, $conn),
            'data'         => $data,
            'p'            => $p,
            'filters'      => $filters,
            'nationalites' => $nationalites,
            'clubFilter'   => $clubFilter,
            'clubTitle'    => $clubTitle,
        ]);
    }
}
