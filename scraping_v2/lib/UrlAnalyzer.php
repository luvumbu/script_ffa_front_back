<?php
/**
 * UrlAnalyzer.php — Decode et comprend les URLs athle.fr
 *
 * Prend une URL bases.athle.fr/asp.net/liste.aspx?... et retourne
 * une analyse structuree : annee, epreuve, sexe, categorie, page, etc.
 */

class UrlAnalyzer
{
    private $referentiel;

    public function __construct()
    {
        $jsonPath = __DIR__ . '/../data/parametres_athle.json';
        $this->referentiel = json_decode(file_get_contents($jsonPath), true);
    }

    /**
     * Analyse complete d'une URL athle.fr
     *
     * @param string $url
     * @return array Structure : type, annee, epreuve_code, sexe, categorie,
     *               page, pagination, hote, base, parametres_bruts, valide, alertes
     */
    public function analyze($url)
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['query'])) {
            return [
                'valide' => false,
                'erreur' => 'URL invalide ou sans query string',
                'url_brute' => $url,
            ];
        }

        parse_str($parts['query'], $params);

        $analysis = [
            'valide'           => true,
            'url_brute'        => $url,
            'hote'             => $parts['host'] ?? null,
            'chemin'           => $parts['path'] ?? null,
            'parametres_bruts' => $params,
            // Champs decodes
            'type_page'        => $this->decodeBase($params['frmbase'] ?? null),
            'annee'            => isset($params['frmannee']) ? (int)$params['frmannee'] : null,
            'epreuve_code'     => $params['frmepreuve'] ?? null,
            'epreuve_libelle'  => null, // Renseigne par EpreuveMapper si dispo
            'sexe'             => $this->decodeSexe($params['frmsexe'] ?? null),
            'categorie'        => $this->decodeCategorie($params['frmcategorie'] ?? null),
            'departement'      => empty($params['frmdepartement']) ? '(toute la France)' : $params['frmdepartement'],
            'ligue'            => empty($params['frmligue']) ? '(toutes ligues)' : $params['frmligue'],
            'nationalite'      => empty($params['frmnationalite']) ? '(toutes)' : $params['frmnationalite'],
            'vent'             => empty($params['frmvent']) ? '(aucun filtre)' : $params['frmvent'],
            'pagination'       => [
                'position'      => isset($params['frmposition']) ? (int)$params['frmposition'] : 0,
                // athle.fr 2025+ : frmposition = (page - 1), 0-indexed
                'page_estimee'  => isset($params['frmposition']) ? ((int)$params['frmposition']) + 1 : 1,
                'taille_page'   => 50,
            ],
            'alertes'          => [],
        ];

        // Verifications de coherence
        if (empty($analysis['annee'])) {
            $analysis['alertes'][] = "Aucune annee trouvee dans l'URL";
        }
        if (empty($analysis['epreuve_code'])) {
            $analysis['alertes'][] = "Aucun code epreuve trouve";
        }
        if (empty($params['frmsexe'])) {
            $analysis['alertes'][] = "Aucun sexe specifie";
        }

        return $analysis;
    }

    /**
     * Verifie si l'URL passe le filtre annee minimale
     */
    public function passesYearFilter($url, $minYear)
    {
        $a = $this->analyze($url);
        if (!$a['valide'] || empty($a['annee'])) return false;
        return $a['annee'] >= $minYear;
    }

    /**
     * Decode frmbase (type de classement)
     */
    private function decodeBase($value)
    {
        $map = $this->referentiel['parametres']['frmbase']['valeurs'] ?? [];
        return $map[$value] ?? ($value ? "Inconnu ($value)" : '(non specifie)');
    }

    /**
     * Decode frmsexe
     */
    private function decodeSexe($value)
    {
        $map = $this->referentiel['parametres']['frmsexe']['valeurs'] ?? [];
        return $map[$value] ?? ($value ? "Inconnu ($value)" : '(non specifie)');
    }

    /**
     * Decode frmcategorie
     */
    private function decodeCategorie($value)
    {
        if (empty($value)) return 'Toutes categories';
        $map = $this->referentiel['parametres']['frmcategorie']['valeurs'] ?? [];
        return $map[$value] ?? "Inconnu ($value)";
    }

    /**
     * Genere une URL avec pagination ajustee
     */
    public function urlPourPage($url, $numeroPage)
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $params);
        // athle.fr 2025+ : frmposition = (page - 1), 0-indexed (plus de ×50)
        $params['frmposition'] = max(0, (int)$numeroPage - 1);
        $newQuery = http_build_query($params);
        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? '') . '?' . $newQuery;
    }

    /**
     * Resume textuel d'une URL (1 ligne)
     */
    public function resume($url)
    {
        $a = $this->analyze($url);
        if (!$a['valide']) return 'URL invalide';
        $parts = [];
        if ($a['annee']) $parts[] = $a['annee'];
        if ($a['epreuve_libelle']) $parts[] = $a['epreuve_libelle'];
        elseif ($a['epreuve_code']) $parts[] = "epreuve#{$a['epreuve_code']}";
        if ($a['sexe'] !== '(non specifie)') $parts[] = $a['sexe'];
        return implode(' | ', $parts);
    }
}
