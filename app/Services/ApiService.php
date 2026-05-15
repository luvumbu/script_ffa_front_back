<?php
/**
 * app/Services/ApiService.php — Appel API avec lecture cache locale
 *
 * Copie exacte de la fonction apiCall() d'index.php (lignes 82-169)
 */

class ApiService
{
    /**
     * Appelle une URL API. Tente d'abord le cache local JSON,
     * sinon fallback HTTP.
     */
    public static function call($url)
    {
        $cacheDir = Application::getInstance()->getRootPath() . '/cache';

        // Tenter de lire le cache local pour eviter l'appel HTTP
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';

        if (preg_match('#/api/([a-zA-Z0-9_]+)\.php#', $path, $m)) {
            $apiName = $m[1];
            $params = [];
            if ($query) parse_str($query, $params);

            $cacheFile = self::getCacheFile($cacheDir, $apiName, $params, $query);

            if ($cacheFile && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
                $json = @file_get_contents($cacheFile);
                if ($json !== false) {
                    $data = json_decode($json, true);
                    if ($data) return $data;
                }
            }
        }

        // Fallback : appel HTTP
        $ctx = stream_context_create(['http' => ['timeout' => 30]]);
        $json = @file_get_contents($url, false, $ctx);
        if (!$json) return null;
        return json_decode($json, true);
    }

    /**
     * Determine le fichier cache selon l'API et les params
     */
    private static function getCacheFile($cacheDir, $apiName, $params, $query)
    {
        if ($apiName === 'stats') {
            $detail = ($params['detail'] ?? '') === '1';
            $top = (int)($params['top'] ?? 50);
            return $cacheDir . '/stats' . ($detail ? '_detail_' . $top : '_base') . '.json';
        }
        if ($apiName === 'epreuve_stats') {
            $nom = $params['nom'] ?? '';
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '50';
            $sx = $params['sexe'] ?? '';
            $cat = $params['categorie'] ?? '';
            return $cacheDir . '/ep_' . md5($nom . '_' . $pg . '_' . $lim . '_' . $sx . '_' . $cat) . '.json';
        }
        if ($apiName === 'athlete') {
            $id = $params['id'] ?? '';
            $idAth = $params['id_athlete'] ?? '';
            return $cacheDir . '/athlete_' . md5($id . '_' . $idAth) . '.json';
        }
        if ($apiName === 'club_stats') {
            $id = $params['id'] ?? '0';
            $nom = $params['nom'] ?? '';
            $an = $params['annee'] ?? '0';
            $ep = $params['ep'] ?? '1';
            $rp = $params['rp'] ?? '1';
            return $cacheDir . '/clubstats_' . md5($id . '_' . $nom . '_' . $an . '_' . $ep . '_' . $rp) . '.json';
        }
        if ($apiName === 'ville_stats') {
            $nom = $params['nom'] ?? '';
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '30';
            $niv = $params['niv'] ?? '';
            $nat = $params['nat'] ?? '';
            $ans = $params['ans'] ?? '';
            return $cacheDir . '/villestats_' . md5($nom . '_' . $pg . '_' . $lim . '_' . $niv . '_' . $nat . '_' . $ans) . '.json';
        }
        if ($apiName === 'clubs') {
            $nom = $params['nom'] ?? '';
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '50';
            $ha = isset($params['has_athletes']) && $params['has_athletes'] == '1' ? 1 : 0;
            $ma = (int)($params['max_athletes'] ?? 0);
            return $cacheDir . '/clubs_' . md5($nom . '_' . $pg . '_' . $lim . '_' . $ha . '_' . $ma) . '.json';
        }
        if ($apiName === 'epreuves') {
            $nom = $params['nom'] ?? '';
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '50';
            $ha = isset($params['has_athletes']) && $params['has_athletes'] == '1' ? 1 : 0;
            $nl = isset($params['no_limit']) && $params['no_limit'] == '1' ? 1 : 0;
            return $cacheDir . '/epreuves_' . md5($nom . '_' . $pg . '_' . $lim . '_' . $ha . '_' . $nl) . '.json';
        }
        if ($apiName === 'villes') {
            $nom = $params['nom'] ?? '';
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '50';
            $ha = isset($params['has_athletes']) && $params['has_athletes'] == '1' ? 1 : 0;
            return $cacheDir . '/villes_' . md5($nom . '_' . $pg . '_' . $lim . '_' . $ha) . '.json';
        }
        if ($apiName === 'liste') {
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '50';
            $ord = $params['ordre'] ?? 'nom';
            return $cacheDir . '/liste_' . md5($pg . '_' . $lim . '_' . $ord) . '.json';
        }
        if ($apiName === 'search') {
            return $cacheDir . '/search_' . md5($query) . '.json';
        }
        return null;
    }
}
