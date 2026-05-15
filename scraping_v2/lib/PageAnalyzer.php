<?php
/**
 * PageAnalyzer.php — Telecharge et parse une page de classement bases.athle.fr
 *
 * Pattern HTML cible : table simple, 1 <tr> par athlete avec 9 cellules.
 * Colonnes attendues : Rang | Perf | Nom (lien) | Club | Ligue | Dept | Cat/An | Date | Lieu
 *
 * Usage minimal :
 *   $pa = new PageAnalyzer();
 *   $r = $pa->analyze("https://www.athle.fr/bases/liste.aspx?...");
 *   foreach ($r['athletes'] as $a) echo $a['id'].' '.$a['nom']."\n";
 */

class PageAnalyzer
{
    private $timeout;
    private $userAgent;

    public function __construct($timeout = 20)
    {
        $this->timeout = $timeout;
        $this->userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';
    }

    /**
     * Pipeline complet : telecharge l'URL et retourne l'analyse structuree.
     */
    public function analyze($url)
    {
        $t0 = microtime(true);
        $fetched = $this->fetch($url);
        $duree = (int) round((microtime(true) - $t0) * 1000);

        $base = [
            'url'           => $url,
            'success'       => false,
            'http_code'     => $fetched['http_code'],
            'duree_ms'      => $duree,
            'taille_html'   => strlen($fetched['html'] ?? ''),
            'erreur'        => $fetched['erreur'],
            'total_resultats' => null,
            'pagination'    => ['page_actuelle' => null, 'total_pages' => null],
            'athletes'      => [],
        ];

        if (!empty($fetched['erreur']) || $fetched['http_code'] !== 200) {
            return $base;
        }

        $parsed = $this->parse($fetched['html']);
        return array_merge($base, [
            'success'         => true,
            'total_resultats' => $parsed['total_resultats'],
            'pagination'      => $parsed['pagination'],
            'athletes'        => $parsed['athletes'],
        ]);
    }

    /**
     * Telecharge le HTML d'une URL (curl).
     */
    public function fetch($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_ENCODING       => '',
        ]);
        $html = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreur = $html === false ? curl_error($ch) : null;
        curl_close($ch);

        return [
            'html'      => $html ?: '',
            'http_code' => $httpCode,
            'erreur'    => $erreur,
        ];
    }

    /**
     * Parse le HTML : extrait pagination, total resultats, lignes athletes.
     */
    public function parse($html)
    {
        return [
            'total_resultats' => $this->extractTotalResultats($html),
            'pagination'      => $this->extractPagination($html),
            'athletes'        => $this->extractAthletes($html),
        ];
    }

    /**
     * Extrait toutes les lignes <tr> contenant un lien /athletes/N/bilans
     * et decoupe en cellules.
     */
    private function extractAthletes($html)
    {
        $athletes = [];
        if (!preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $html, $rows)) return $athletes;

        $seen = [];
        foreach ($rows[1] as $rowHtml) {
            if (!preg_match('#/athletes/(\d+)#i', $rowHtml, $idMatch)) continue;
            $id = (int) $idMatch[1];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;

            // Decoupe les <td>
            preg_match_all('#<td[^>]*>(.*?)</td>#is', $rowHtml, $cellsMatch);
            $cellsRaw = $cellsMatch[1] ?? [];
            $cells = array_map([$this, 'cleanCell'], $cellsRaw);

            // Extraire le nom depuis le lien athlete (cellule 3 typiquement)
            $nom = '';
            if (preg_match('#<a[^>]+href=["\'][^"\']*/athletes/' . $id . '[^"\']*["\'][^>]*>(.*?)</a>#is', $rowHtml, $nomMatch)) {
                $nom = $this->cleanCell($nomMatch[1]);
            }

            // Mapping positionnel souple (athle.fr peut avoir 7 a 10 cellules selon l'epreuve)
            $athletes[] = [
                'id'        => $id,
                'url_fiche' => "/athletes/$id/bilans",
                'rang'      => $cells[0] ?? '',
                'perf'      => $cells[1] ?? '',
                'nom'       => $nom !== '' ? $nom : ($cells[2] ?? ''),
                'club'      => $cells[3] ?? '',
                'ligue'     => $cells[4] ?? '',
                'dept'      => $cells[5] ?? '',
                'cat_an'    => $cells[6] ?? '',
                'date'      => $cells[7] ?? '',
                'lieu'      => $cells[8] ?? '',
                'cells'     => $cells,
            ];
        }
        return $athletes;
    }

    /**
     * Cherche "Page > NNN/MMM <" dans le HTML.
     * Format athle.fr 2025+ : utilise &nbsp; au lieu d'espace.
     */
    private function extractPagination($html)
    {
        $pa = ['page_actuelle' => null, 'total_pages' => null];
        // Normaliser : remplacer &nbsp; et &#160; par espaces, puis matcher simplement
        $normalized = str_replace(['&nbsp;', '&#160;', "\xc2\xa0"], ' ', $html);
        if (preg_match('~Page\s*&gt;\s*(\d+)\s*/\s*(\d+)\s*&lt;~u', $normalized, $m)
         || preg_match('~Page\s*>\s*(\d+)\s*/\s*(\d+)\s*<~u', $normalized, $m)) {
            $pa['page_actuelle'] = (int) $m[1];
            $pa['total_pages']   = (int) $m[2];
        }
        return $pa;
    }

    /**
     * Cherche le compteur global type "3846 resultats".
     */
    private function extractTotalResultats($html)
    {
        if (preg_match('#(\d{1,6})\s*r[eé]sultats?#iu', $html, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Nettoie une cellule HTML : strip tags, normalise espaces, decode entites.
     */
    private function cleanCell($html)
    {
        $txt = preg_replace('#<br\s*/?>#i', ' ', $html);
        $txt = strip_tags($txt);
        $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $txt = preg_replace('#\s+#u', ' ', $txt);
        return trim($txt);
    }
}
