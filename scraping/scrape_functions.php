<?php
/**
 * scrape_functions.php — Fonctions partagées pour le scraping athle.fr
 *
 * Utilisé par scraper.php et check_sync.php
 */

/**
 * Scrape N athlètes en parallèle (3 pages × N requêtes simultanées)
 *
 * @param array  $athleteIds  Liste simple d'IDs athlètes [123, 456, 789]
 * @param string $baseUrl     URL de base athle.fr
 * @return array [athleteId => ['bilans' => html|null, 'records' => html|null, 'selections' => html|null]]
 */
function scrapeParallel($athleteIds, $baseUrl = "https://athle.fr/athletes/")
{
    $sections = ['bilans', 'records', 'selections'];
    $mh = curl_multi_init();
    $handles = [];

    foreach ($athleteIds as $athleteId) {
        foreach ($sections as $section) {
            $url = $baseUrl . $athleteId . "/" . $section;
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_USERAGENT      => 'Mozilla/5.0',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$athleteId . "_" . $section] = $ch;
        }
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    $results = [];
    foreach ($athleteIds as $athleteId) {
        $pages = [];
        foreach ($sections as $section) {
            $ch = $handles[$athleteId . "_" . $section];
            $content = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $pages[$section] = ($httpCode === 200 && $content) ? $content : null;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        $results[$athleteId] = $pages;
    }

    curl_multi_close($mh);
    return $results;
}
