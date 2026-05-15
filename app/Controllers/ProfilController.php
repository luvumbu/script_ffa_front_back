<?php
/**
 * ProfilController — Fiche profil athlète
 */

class ProfilController extends Controller
{
    public function show()
    {
        $request = new Request();
        $apiBase = $this->config('api_base_url');
        $conn = $this->getConnection();
        $id = $request->getString('id', '');
        $section = $request->getString('s', 'all');

        if (!$id) {
            Response::notFound();
            return;
        }

        $data = $this->apiCall("$apiBase/athlete.php?id=$id");

        if (!$data || !($data['success'] ?? false)) {
            $this->render('profil', [
                'page'    => 'profil',
                'seo'     => SeoService::build('profil', ['id' => $id]),
                'data'    => null,
                'id'      => $id,
                'section' => $section,
            ]);
            return;
        }

        // Search tracking profil
        if ($conn && !empty($data['athlete'])) {
            $ath = $data['athlete'];
            $nom = trim(($ath['nom_athlete'] ?? '') . ' ' . ($ath['prenom_athlete'] ?? ''));
            $extId = $ath['athlete_id_externe'] ?? $id;
            SearchTrackingService::track($conn, 'athlete', 'page_view', $extId, $nom);
        }

        $seoParams = ['id' => $id];
        if (!empty($data['athlete'])) {
            $seoParams['athlete'] = $data['athlete'];
        }

        $this->render('profil', [
            'page'    => 'profil',
            'seo'     => SeoService::build('profil', $seoParams, $conn),
            'data'    => $data,
            'id'      => $id,
            'section' => $section,
        ]);
    }
}
