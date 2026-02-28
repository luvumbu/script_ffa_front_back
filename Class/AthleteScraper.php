<?php
/**
 * AthleteScraper.php — Extracteur complet de données athlète depuis athle.fr
 * Récupère et structure toutes les informations pour insertion en base de données.
 */
class AthleteScraper
{
    private $baseUrl = "https://athle.fr/athletes/";
    private $athleteId;
    public $html;

    // Données structurées extraites
    public $identite     = [];
    public $clubs        = [];
    public $medailles    = [];
    public $selections   = [];
    public $progressions = [];
    public $records      = [];
    public $podiums      = [];
    public $resultats    = [];
    public $niveaux      = [];

    /**
     * @param int|string $input ID de l'athlète (ex: 123983) OU URL complète (ex: https://athle.fr/athletes/809035/bilans)
     */
    public function __construct($input)
    {
        // Si c'est une URL, on extrait l'ID
        if (filter_var($input, FILTER_VALIDATE_URL) || preg_match('#https?://#', $input)) {
            if (preg_match('#/athletes/(\d+)#', $input, $m)) {
                $this->athleteId = (int)$m[1];
            } else {
                throw new Exception("URL invalide : impossible de trouver l'ID athlète dans '$input'");
            }
        } else {
            $this->athleteId = (int)$input;
        }
    }

    /**
     * Lance le scraping complet de toutes les pages de l'athlète
     * @return array Toutes les données structurées
     */
    public function scrapeAll()
    {
        // Télécharger les 3 pages EN PARALLÈLE (curl_multi)
        $pages = $this->fetchAllPages();

        // Page bilans (contient identité + progressions)
        $this->html = $pages['bilans'] ?? null;
        if (!$this->html) {
            return ['success' => false, 'message' => 'Impossible de récupérer la page bilans'];
        }
        $this->extractIdentite();
        $this->extractMedailles();
        $this->extractProgressions();
        $this->extractClubs();
        $this->extractPodiums();
        $this->extractResultats();
        $this->extractNiveaux();

        // Page records
        if (!empty($pages['records'])) {
            $this->html = $pages['records'];
            $this->extractRecords();
        }

        // Page selections
        if (!empty($pages['selections'])) {
            $this->html = $pages['selections'];
            $this->extractSelections();
        }

        return [
            'success'      => true,
            'identite'     => $this->identite,
            'clubs'        => $this->clubs,
            'medailles'    => $this->medailles,
            'selections'   => $this->selections,
            'progressions' => $this->progressions,
            'records'      => $this->records,
            'podiums'      => $this->podiums,
            'resultats'    => $this->resultats,
            'niveaux'      => $this->niveaux,
        ];
    }

    /**
     * Télécharge les 3 pages en parallèle avec curl_multi
     * → ~3x plus rapide que 3 file_get_contents séquentiels
     */
    private function fetchAllPages()
    {
        $sections = ['bilans', 'records', 'selections'];
        $results = [];

        $mh = curl_multi_init();
        $handles = [];

        foreach ($sections as $section) {
            $url = $this->baseUrl . $this->athleteId . "/" . $section;
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
            $handles[$section] = $ch;
        }

        // Exécuter toutes les requêtes en parallèle
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        // Récupérer les résultats
        foreach ($handles as $section => $ch) {
            $content = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $results[$section] = ($httpCode === 200 && $content) ? $content : null;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        return $results;
    }

    /**
     * Récupère le HTML d'une page de l'athlète (fallback séquentiel)
     */
    private function fetchPage($section)
    {
        $url = $this->baseUrl . $this->athleteId . "/" . $section;

        $context = stream_context_create([
            'http' => [
                'header'  => "User-Agent: Mozilla/5.0\r\n",
                'timeout' => 15,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        return $content ?: null;
    }

    // =========================================================================
    //  IDENTITE
    // =========================================================================
    public function extractIdentite()
    {
        $data = [
            'athlete_id'  => $this->athleteId,
            'nom_1'       => '',
            'nom_2'       => '',
            'nom_3'       => '',
            'nom_4'       => '',
            'nom_complet' => '',
            'date_naissance'  => null,
            'annee_naissance' => null,
            'lieu_naissance'  => '',
            'taille_cm'       => null,
            'poids_kg'        => null,
            'categorie'       => '',
            'sexe'            => '',
            'nationalite'     => '',
            'licence'         => '',
        ];

        // Nom complet depuis le h1
        // Format athle.fr : <h1>Ndenga<br>LUVUMBU</h1>
        // Le <br> sépare prénom et nom → on le remplace par un espace avant strip_tags
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $this->html, $m)) {
            // Remplacer <br>, <br/>, <br /> par un espace AVANT de supprimer les tags
            $nomHtml = preg_replace('/<br\s*\/?>/i', ' ', $m[1]);
            $nomComplet = trim(preg_replace('/\s+/', ' ', strip_tags($nomHtml)));
            $data['nom_complet'] = $nomComplet;

            $noms = self::splitNomPrenom($nomComplet);
            $data['nom_1'] = $noms['nom_famille'];
            $data['nom_2'] = $noms['prenom_1'];
            $data['nom_3'] = $noms['prenom_2'];
            $data['nom_4'] = $noms['prenom_3'];
        }

        // Date et lieu de naissance — on cherche dans le texte propre (sans HTML)
        // pour éviter de matcher des dates de compétition
        $htmlClean = strip_tags($this->html);

        // Format 1 : "Né(e) le : 26/08/1978 à Nanterre" (date complète)
        if (preg_match('/Né(?:e|\(e\))?\s*le\s*:?\s*(\d{2})\/(\d{2})\/(\d{4})(?:\s*[àa]\s+(.+))?/iu', $htmlClean, $m)) {
            $data['date_naissance'] = $m[3] . '-' . $m[2] . '-' . $m[1];
            $data['annee_naissance'] = (int)$m[3];
            if (isset($m[4]) && !empty(trim($m[4]))) {
                $data['lieu_naissance'] = trim($m[4]);
            }
        }
        // Format 2 : "Né(e) en : 1991" (année seule)
        elseif (preg_match('/Né(?:e|\(e\))?\s*en\s*:?\s*(\d{4})/iu', $htmlClean, $m)) {
            $data['date_naissance'] = $m[1] . '-01-01';
            $data['annee_naissance'] = (int)$m[1];
        }

        // Taille / poids : "168cm / 54kg" ou "185cm / 80kg"
        if (preg_match('/(\d+)\s*cm\s*\/\s*(\d+)\s*kg/i', $htmlClean, $m)) {
            $data['taille_cm'] = (int)$m[1];
            $data['poids_kg']  = (int)$m[2];
        }

        // Catégorie / Nationalité : "-/F/FRA" ou "SE/M/FRA" ou "-/M/ANG"
        if (preg_match('/([A-Z\-]{0,4})\s*\/\s*([MF])\s*\/\s*([A-Z]{3})/i', $htmlClean, $m)) {
            $data['categorie']   = trim($m[1]);
            $data['sexe']        = strtoupper(trim($m[2]));
            $data['nationalite'] = strtoupper(trim($m[3]));
        }

        // N° de licence : "N° de licence : 1053697" ou "licence : 747214"
        if (preg_match('/licence\s*:?\s*(\d+)/i', $htmlClean, $m)) {
            $data['licence'] = trim($m[1]);
        }

        $this->identite = $data;
    }

    // =========================================================================
    //  CLUBS (déduit depuis les progressions)
    //  Pas de section "clubs" sur athle.fr → on les extrait des lignes de perf
    // =========================================================================
    public function extractClubs()
    {
        // Parcourir les progressions déjà extraites pour collecter les clubs
        // Pour chaque club unique, on prend la première et dernière année
        $clubData = []; // nom_club => ['min' => annee, 'max' => annee]

        foreach ($this->progressions as $prog) {
            $nom = trim($prog['club'] ?? '');
            if (empty($nom)) continue;

            // Nettoyer le * en fin de nom (club actuel sur athle.fr)
            $nom = rtrim($nom, '* ');
            $nom = trim($nom);
            if (empty($nom)) continue;

            $annee = (int)$prog['annee'];

            if (!isset($clubData[$nom])) {
                $clubData[$nom] = ['min' => $annee, 'max' => $annee];
            } else {
                if ($annee < $clubData[$nom]['min']) $clubData[$nom]['min'] = $annee;
                if ($annee > $clubData[$nom]['max']) $clubData[$nom]['max'] = $annee;
            }
        }

        $clubs = [];
        foreach ($clubData as $nom => $annees) {
            $clubs[] = [
                'athlete_id'  => $this->athleteId,
                'nom_club'    => $nom,
                'annee_debut' => $annees['min'],
                'annee_fin'   => $annees['max'],
            ];
        }

        $this->clubs = $clubs;
    }

    // =========================================================================
    //  MEDAILLES
    // =========================================================================
    public function extractMedailles()
    {
        $medailles = [];

        // Chercher les blocs de médailles
        // Pattern : "Médaille d'or / 1997 - Ljubljana (SLO) : 4 x 400 m"
        // ou "Médaille de bronze / 1997 - Ljubljana (SLO) : 400 m"
        $pattern = '/[Mm]édaille\s+(?:d[\'e]\s*)?(\w+)\s*\/\s*(\d{4})\s*-\s*([^:]+?)\s*:\s*([^<\n]+)/u';
        if (preg_match_all($pattern, $this->html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $type = mb_strtolower(trim($m[1]));
                // Normaliser le type
                $typeNorm = 'autre';
                if (strpos($type, 'or') !== false)      $typeNorm = 'or';
                if (strpos($type, 'argent') !== false)   $typeNorm = 'argent';
                if (strpos($type, 'bronze') !== false)   $typeNorm = 'bronze';

                $lieu = trim($m[3]);
                $pays = '';
                // Extraire le pays entre parenthèses : "Ljubljana (SLO)"
                if (preg_match('/(.+?)\s*\(([A-Z]{3})\)/u', $lieu, $lm)) {
                    $lieu = trim($lm[1]);
                    $pays = trim($lm[2]);
                }

                $medailles[] = [
                    'athlete_id'  => $this->athleteId,
                    'type'        => $typeNorm,
                    'annee'       => (int)$m[2],
                    'lieu'        => $lieu,
                    'pays'        => $pays,
                    'epreuve'     => trim(strip_tags($m[4])),
                    'competition' => '', // sera enrichi si détecté dans le contexte
                ];
            }
        }

        // Détecter le nom de la compétition (ex: "Championnats d'Europe Juniors")
        if (preg_match_all('/<h[34][^>]*>(Championnats?[^<]+)<\/h[34]>/iu', $this->html, $compMatches)) {
            $lastComp = '';
            foreach ($compMatches[1] as $comp) {
                $lastComp = trim(strip_tags($comp));
            }
            // Associer la compétition à toutes les médailles trouvées après ce titre
            foreach ($medailles as &$med) {
                if (empty($med['competition'])) {
                    $med['competition'] = $lastComp;
                }
            }
            unset($med);
        }

        $this->medailles = $medailles;
    }

    // =========================================================================
    //  SELECTIONS
    // =========================================================================
    public function extractSelections()
    {
        $selections = [];

        // Pattern des sélections :
        // "Jeune - 22/07/2000 (1j) - (22 ans)"
        // "Liverpool GBR - FRA - GER moins de 23 ans : 4 x 400 m - 2è 3'43''65"
        $blocks = preg_split('/<(?:div|tr|li)[^>]*>/i', $this->html);

        $currentType = '';
        $currentDate = '';
        $currentDuree = '';
        $currentAge = '';

        foreach ($blocks as $block) {
            $text = trim(strip_tags($block));
            if (empty($text)) continue;

            // Ligne d'en-tête de sélection : "A - 22/07/2000 (1j) - (22 ans)" ou "Jeune - 22/07/2000..."
            if (preg_match('/(A|Jeune|Junior|Espoir)\s*-\s*(\d{2}\/\d{2}\/\d{4})\s*\((\d+j?)\)\s*-\s*\((\d+)\s*ans\)/iu', $text, $m)) {
                $currentType  = trim($m[1]);
                $currentDate  = $this->convertDateToSql($m[2]);
                $currentDuree = trim($m[3]);
                $currentAge   = (int)$m[4];
                continue;
            }

            // Ligne de résultat : "Liverpool GBR - FRA - GER moins de 23 ans : 4 x 400 m - 2è 3'43''65"
            if (!empty($currentDate) && preg_match('/(.+?)\s*:\s*(.+?)\s*-\s*(\d+)[èe]?\s+([\d\'\'\"\.:]+\d+)/u', $text, $m)) {
                $selections[] = [
                    'athlete_id'  => $this->athleteId,
                    'type'        => $currentType,
                    'date'        => $currentDate,
                    'duree_jours' => (int)$currentDuree,
                    'age'         => $currentAge,
                    'competition' => trim($m[1]),
                    'epreuve'     => trim($m[2]),
                    'classement'  => (int)$m[3],
                    'performance'     => self::performanceToInt(trim($m[4])),
                    'performance_brut' => trim($m[4]),
                ];
            }
        }

        $this->selections = $selections;
    }

    // =========================================================================
    //  PROGRESSIONS (bilans par saison)
    // =========================================================================
    public function extractProgressions()
    {
        $progressions = [];

        // Isoler la section "Meilleures performances par saison"
        $startPos = stripos($this->html, 'Meilleures performances par saison');
        if ($startPos === false) $startPos = stripos($this->html, 'section_4');
        $searchHtml = ($startPos !== false) ? substr($this->html, $startPos) : $this->html;

        // Limiter à la fin de la section pour ne pas déborder
        $endPos = stripos($searchHtml, '</section>');
        if ($endPos !== false) {
            $searchHtml = substr($searchHtml, 0, $endPos);
        }

        $currentEpreuve = '';

        // Extraire tous les blocs <tr ...>...</tr> (non-greedy → s'arrête au premier </tr>)
        if (!preg_match_all('/<tr([^>]*)>(.*?)<\/tr>/si', $searchHtml, $allRows, PREG_SET_ORDER)) {
            $this->progressions = [];
            return;
        }

        foreach ($allRows as $row) {
            $trAttrs = $row[1]; // attributs du <tr> (class="detail-row ..." etc.)
            $inner   = $row[2]; // contenu entre <tr> et </tr>

            // SKIP detail-row (lignes mobiles avec tables imbriquées)
            if (stripos($trAttrs, 'detail-row') !== false) {
                continue;
            }

            // SKIP lignes d'en-tête avec <th>
            if (stripos($inner, '<th') !== false) {
                continue;
            }

            // Détecter le nom d'épreuve : <div class="headers ...">NOM</div>
            if (preg_match('/class="headers[^"]*"[^>]*>(.*?)<\/div>/si', $inner, $hm)) {
                $epreuve = trim(strip_tags($hm[1]));
                if (!empty($epreuve)) {
                    $currentEpreuve = $epreuve;
                }
                continue;
            }

            // Extraire les cellules <td>
            if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $inner, $cells)) {
                continue;
            }

            $values = array_map(function ($v) {
                return trim(strip_tags($v));
            }, $cells[1]);

            // Minimum 6 colonnes + épreuve connue
            if (count($values) < 6 || empty($currentEpreuve)) {
                continue;
            }

            // La première colonne DOIT être une année (4 chiffres)
            $saison = $values[0];
            if (!preg_match('/^\d{4}$/', $saison)) {
                continue;
            }

            $date     = $values[1];
            $perfBrut = $values[2];
            $club     = $values[3];
            $ligDpt   = $values[4];
            $lieu     = $values[5];

            // Extraire le vent : "12'09 (-0.1)" → perf="12'09", vent="-0.1"
            $perf = $perfBrut;
            $vent = '';
            if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', $perfBrut, $vm)) {
                $perf = trim($vm[1]);
                $vent = trim($vm[2]);
            }

            // Validation : la performance doit contenir un chiffre et < 20 caractères
            // (rejette les noms d'épreuves qui se seraient glissés par erreur)
            if (!preg_match('/\d/', $perf) || mb_strlen($perf) > 20) {
                continue;
            }

            $codeCat = self::getCategorieCode(
                $this->identite['annee_naissance'] ?? null,
                (int)$saison
            );

            $progressions[] = [
                'athlete_id'       => $this->athleteId,
                'epreuve'          => $currentEpreuve,
                'annee'            => (int)$saison,
                'categorie'        => $codeCat,
                'performance'      => self::performanceToInt($perf),
                'performance_brut' => $perf,
                'vent'             => $vent,
                'date'             => $this->convertDateToSql($date),
                'lieu'             => $lieu,
                'club'             => $club,
                'ligue_dept'       => $ligDpt,
            ];
        }

        $this->progressions = $progressions;
    }

    // =========================================================================
    //  RECORDS PERSONNELS
    // =========================================================================
    public function extractRecords()
    {
        $records = [];

        // Chercher la section "Records personnels"
        $startPos = stripos($this->html, 'Records personnels');
        $searchHtml = ($startPos !== false) ? substr($this->html, $startPos) : $this->html;

        // Limiter à la fin de la section
        $endPos = stripos($searchHtml, '</section>');
        if ($endPos !== false) {
            $searchHtml = substr($searchHtml, 0, $endPos);
        }

        // Extraire tous les blocs <tr ...>...</tr>
        if (!preg_match_all('/<tr([^>]*)>(.*?)<\/tr>/si', $searchHtml, $allRows, PREG_SET_ORDER)) {
            $this->records = [];
            return;
        }

        foreach ($allRows as $row) {
            $trAttrs = $row[1];
            $inner   = $row[2];

            // SKIP detail-row
            if (stripos($trAttrs, 'detail-row') !== false) {
                continue;
            }

            // SKIP lignes d'en-tête avec <th>
            if (stripos($inner, '<th') !== false) {
                continue;
            }

            // Extraire les cellules <td>
            if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $inner, $cells)) {
                continue;
            }

            $values = array_map(function ($v) {
                return trim(strip_tags($v));
            }, $cells[1]);

            // Colonnes : Epreuve | Performance | Date | Categorie | Club | Lig./Dpt. | Lieu | (chevron)
            if (count($values) < 7) {
                continue;
            }

            $epreuve = $values[0];
            $perf    = $values[1];

            // Vérifier que c'est un vrai record (perf contient un chiffre, < 20 chars)
            if (empty($epreuve) || empty($perf) || !preg_match('/\d/', $perf) || mb_strlen($perf) > 20) {
                continue;
            }

            // Extraire le vent si présent : "10''45 (-0.1)"
            $perfClean = $perf;
            $ventRec = '';
            if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', $perf, $vm)) {
                $perfClean = trim($vm[1]);
                $ventRec = trim($vm[2]);
            }

            $records[] = [
                'athlete_id'       => $this->athleteId,
                'epreuve'          => $epreuve,
                'performance'      => self::performanceToInt($perfClean),
                'performance_brut' => $perfClean,
                'date'             => $this->convertDateToSql($values[2]),
                'categorie'        => $values[3],
                'club'             => $values[4],
                'ligue_dept'       => $values[5],
                'lieu'             => $values[6],
            ];
        }

        $this->records = $records;
    }

    // =========================================================================
    //  PODIUMS (section 6 - titres de champion)
    // =========================================================================
    public function extractPodiums()
    {
        $podiums = [];

        // Isoler la section_6 (Podiums)
        $startPos = stripos($this->html, 'data-section="section_6"');
        if ($startPos === false) {
            $this->podiums = [];
            return;
        }
        $searchHtml = substr($this->html, $startPos);
        $endPos = stripos($searchHtml, '</section>');
        if ($endPos !== false) {
            $searchHtml = substr($searchHtml, 0, $endPos);
        }

        $currentNiveau = ''; // Interrégional, Régional, Départemental

        if (!preg_match_all('/<tr([^>]*)>(.*?)<\/tr>/si', $searchHtml, $allRows, PREG_SET_ORDER)) {
            $this->podiums = [];
            return;
        }

        foreach ($allRows as $row) {
            $trAttrs = $row[1];
            $inner   = $row[2];

            if (stripos($trAttrs, 'detail-row') !== false) continue;
            if (stripos($inner, '<th') !== false) continue;

            // Détecter le niveau : <div class="headers ...">Interrégional</div>
            if (preg_match('/class="headers[^"]*"[^>]*>(.*?)<\/div>/si', $inner, $hm)) {
                $niveau = trim(strip_tags($hm[1]));
                if (!empty($niveau)) {
                    $currentNiveau = $niveau;
                }
                continue;
            }

            if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $inner, $cells)) continue;

            $values = array_map(function ($v) {
                return trim(strip_tags($v));
            }, $cells[1]);

            // Colonnes : Année | Place | Épreuve | Performance | Date | Lieu | (chevron)
            if (count($values) < 6 || empty($currentNiveau)) continue;

            $annee = $values[0];
            if (!preg_match('/^\d{4}$/', $annee)) continue;

            $place    = $values[1];
            $epreuve  = $values[2];
            $perfBrut = $values[3];
            $date     = $values[4];
            $lieu     = $values[5];

            // Extraire vent depuis la performance
            $perf = $perfBrut;
            $vent = '';
            if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', $perfBrut, $vm)) {
                $perf = trim($vm[1]);
                $vent = trim($vm[2]);
            }

            // Extraire le rang depuis le texte du classement
            $rang = self::parsePlacePodium($place);

            $podiums[] = [
                'athlete_id'         => $this->athleteId,
                'annee'              => (int)$annee,
                'niveau_competition' => $currentNiveau,
                'place'              => $place,
                'rang'               => $rang,
                'epreuve'            => $epreuve,
                'performance'        => self::performanceToInt($perf),
                'performance_brut'   => $perf,
                'vent'               => $vent,
                'date'               => $this->convertDateToSql($date),
                'lieu'               => $lieu,
            ];
        }

        $this->podiums = $podiums;
    }

    /**
     * Extrait le rang (1/2/3) depuis le texte de place podium
     * "Champion SEM - NPC" → 1, "Vice champion JUM" → 2, "3ème (place)..." → 3
     */
    private static function parsePlacePodium($place)
    {
        if (preg_match('/^Champion\b/i', $place)) return 1;
        if (preg_match('/^Vice champion/i', $place)) return 2;
        if (preg_match('/^3[èe]me/i', $place)) return 3;
        return null;
    }

    // =========================================================================
    //  RÉSULTATS (section 3 - résultats par compétition)
    // =========================================================================
    public function extractResultats()
    {
        $resultats = [];

        // Isoler la section_3 (Résultats)
        $startPos = stripos($this->html, 'data-section="section_3"');
        if ($startPos === false) {
            $this->resultats = [];
            return;
        }
        $searchHtml = substr($this->html, $startPos);
        $endPos = stripos($searchHtml, '</section>');
        if ($endPos !== false) {
            $searchHtml = substr($searchHtml, 0, $endPos);
        }

        // Extraire l'année depuis le dropdown : <span class="select-text">2025</span>
        $annee = null;
        if (preg_match('/<span[^>]*class="select-text"[^>]*>(\d{4})<\/span>/i', $searchHtml, $ym)) {
            $annee = (int)$ym[1];
        }
        if (!$annee) {
            $this->resultats = [];
            return;
        }

        if (!preg_match_all('/<tr([^>]*)>(.*?)<\/tr>/si', $searchHtml, $allRows, PREG_SET_ORDER)) {
            $this->resultats = [];
            return;
        }

        foreach ($allRows as $row) {
            $trAttrs = $row[1];
            $inner   = $row[2];

            if (stripos($trAttrs, 'detail-row') !== false) continue;
            if (stripos($inner, '<th') !== false) continue;

            if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $inner, $cells)) continue;

            $values = array_map(function ($v) {
                return trim(strip_tags($v));
            }, $cells[1]);

            // Colonnes : Date | Épreuve | Performance | Vent | Tour | Place | Niveau | Points | Lieu | (chevron)
            if (count($values) < 9) continue;

            $datePartielle = $values[0]; // "26 Avr."
            $epreuve       = $values[1];
            $perfBrut      = $values[2];
            $vent          = $values[3];
            $tour          = $values[4];
            $place         = $values[5];
            $niveau        = $values[6];
            $points        = $values[7];
            $lieu          = $values[8];

            if (empty($epreuve)) continue;

            // Construire la date complète : "26 Avr." + 2025 → "26 Avr. 2025"
            $dateComplete = $datePartielle . ' ' . $annee;
            $dateSql = $this->convertDateToSql($dateComplete);

            $resultats[] = [
                'athlete_id'       => $this->athleteId,
                'annee'            => $annee,
                'date'             => $dateSql,
                'epreuve'          => $epreuve,
                'performance'      => self::performanceToInt($perfBrut),
                'performance_brut' => $perfBrut,
                'vent'             => $vent,
                'tour'             => $tour,
                'place'            => !empty($place) ? (int)$place : null,
                'niveau'           => $niveau,
                'points'           => !empty($points) ? (int)$points : null,
                'lieu'             => $lieu,
            ];
        }

        $this->resultats = $resultats;
    }

    // =========================================================================
    //  NIVEAUX (section 9 - classement annuel)
    // =========================================================================
    public function extractNiveaux()
    {
        $niveaux = [];

        // Isoler la section_9 (Niveau)
        $startPos = stripos($this->html, 'data-section="section_9"');
        if ($startPos === false) {
            $this->niveaux = [];
            return;
        }
        $searchHtml = substr($this->html, $startPos);
        $endPos = stripos($searchHtml, '</section>');
        if ($endPos !== false) {
            $searchHtml = substr($searchHtml, 0, $endPos);
        }

        if (!preg_match_all('/<tr([^>]*)>(.*?)<\/tr>/si', $searchHtml, $allRows, PREG_SET_ORDER)) {
            $this->niveaux = [];
            return;
        }

        foreach ($allRows as $row) {
            $trAttrs = $row[1];
            $inner   = $row[2];

            if (stripos($trAttrs, 'detail-row') !== false) continue;
            if (stripos($inner, '<th') !== false) continue;

            // Extraire les <td> AVANT strip_tags pour garder les <br>
            if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $inner, $cells)) continue;

            // Colonnes : Année | Niveau | Club | Performances | (chevron)
            if (count($cells[1]) < 4) continue;

            $annee           = trim(strip_tags($cells[1][0]));
            $niveauBrut      = trim(strip_tags($cells[1][1]));
            $club            = trim(strip_tags($cells[1][2]));
            $performancesHtml = $cells[1][3]; // garder le HTML pour les <br>

            if (!preg_match('/^\d{4}$/', $annee)) continue;

            // Parser "N2 (28 pts)" → code="N2", points=28
            $codeNiveau   = '';
            $pointsNiveau = null;
            if (preg_match('/^([A-Z0-9]+)\s*\((\d+)\s*pts?\)/i', $niveauBrut, $nm)) {
                $codeNiveau   = trim($nm[1]);
                $pointsNiveau = (int)$nm[2];
            } else {
                $codeNiveau = $niveauBrut;
            }

            // Nettoyer le nom du club
            $club = rtrim($club, '* ');
            $club = trim($club);

            // Séparer les performances par <br>
            $performancesTexts = preg_split('/<br\s*\/?>/i', $performancesHtml);
            $performances = [];

            foreach ($performancesTexts as $perfHtml) {
                $perfText = trim(strip_tags($perfHtml));
                if (empty($perfText)) continue;

                // Parser : "400m Haies (91) : 52''40 (N2)"
                if (preg_match('/^(.+?)\s*:\s*(.+?)\s*\(([A-Z0-9]+)\)\s*$/i', $perfText, $pm)) {
                    $epreuve       = trim($pm[1]);
                    $perfBrut      = trim($pm[2]);
                    $codePerfNiveau = trim($pm[3]);

                    $performances[] = [
                        'epreuve'          => $epreuve,
                        'performance'      => self::performanceToInt($perfBrut),
                        'performance_brut' => $perfBrut,
                        'code_niveau'      => $codePerfNiveau,
                    ];
                }
            }

            $niveaux[] = [
                'athlete_id'    => $this->athleteId,
                'annee'         => (int)$annee,
                'code_niveau'   => $codeNiveau,
                'points_niveau' => $pointsNiveau,
                'club'          => $club,
                'performances'  => $performances,
            ];
        }

        $this->niveaux = $niveaux;
    }

    // =========================================================================
    //  UTILITAIRES
    // =========================================================================

    /**
     * Sépare un nom complet athle.fr en nom de famille + prénoms
     * Règle : MAJUSCULES = nom de famille, PremièreLettreMaj = prénom
     *
     * Exemples :
     *   "VAN DER ZYPPE Antonine"     → nom_famille="VAN DER ZYPPE", prenom_1="Antonine"
     *   "BOKONZI Jean Pierre"        → nom_famille="BOKONZI", prenom_1="Jean", prenom_2="Pierre"
     *   "EGA Cindy"                  → nom_famille="EGA", prenom_1="Cindy"
     *   "DA SILVA PEREIRA Ana Maria" → nom_famille="DA SILVA PEREIRA", prenom_1="Ana", prenom_2="Maria"
     *
     * @param string $nomComplet Le nom complet brut depuis athle.fr
     * @return array ['nom_famille', 'prenom_1', 'prenom_2', 'prenom_3']
     */
    public static function splitNomPrenom($nomComplet)
    {
        $result = [
            'nom_famille' => '',
            'prenom_1'    => '',
            'prenom_2'    => '',
            'prenom_3'    => '',
        ];

        $nomComplet = trim($nomComplet);
        if (empty($nomComplet)) return $result;

        $mots = preg_split('/\s+/', $nomComplet);

        $partsNom     = [];
        $partsPrenoms = [];

        // Séparer : MAJUSCULES = nom de famille, reste = prénoms
        // Fonctionne dans les 2 sens :
        //   "VAN DER ZYPPE Antonine"  → NOM d'abord
        //   "Ndenga LUVUMBU"          → Prénom d'abord
        //   "Jean Pierre DUPONT"      → Prénoms d'abord
        foreach ($mots as $mot) {
            if (mb_strtoupper($mot) === $mot && mb_strlen($mot) > 1) {
                $partsNom[] = $mot;
            } else {
                $partsPrenoms[] = $mot;
            }
        }

        $result['nom_famille'] = implode(' ', $partsNom);

        if (isset($partsPrenoms[0])) $result['prenom_1'] = $partsPrenoms[0];
        if (isset($partsPrenoms[1])) $result['prenom_2'] = $partsPrenoms[1];
        if (isset($partsPrenoms[2])) $result['prenom_3'] = $partsPrenoms[2];

        return $result;
    }

    /**
     * Retourne le code catégorie FFA à partir de l'âge
     * @param int $anneeNaissance Année de naissance
     * @param int $anneeSaison   Année de la saison/compétition
     * @return string Code catégorie (EA, PO, BE, MI, CA, JU, ES, SE, V1...)
     */
    public static function getCategorieCode($anneeNaissance, $anneeSaison)
    {
        if (!$anneeNaissance || !$anneeSaison) return null;
        $age = $anneeSaison - $anneeNaissance;
        if ($age < 0) return null;

        if ($age <= 7)  return 'EA';
        if ($age <= 9)  return 'PO';
        if ($age <= 11) return 'BE';
        if ($age <= 13) return 'MI';
        if ($age <= 15) return 'CA';
        if ($age <= 17) return 'JU';
        if ($age <= 22) return 'ES';
        if ($age <= 39) return 'SE';
        if ($age <= 49) return 'V1';
        if ($age <= 59) return 'V2';
        if ($age <= 69) return 'V3';
        return 'V4';
    }

    /**
     * Convertit une performance athle.fr en entier (centièmes)
     * Temps  → centièmes de seconde : 6''37 = 637, 3'43''65 = 22365
     * Distance → centimètres         : 7.34 = 734, 15.67 = 1567
     *
     * @param string $perf Performance brute (ex: "6''37", "3'43''65", "7.34")
     * @return int|null Valeur en centièmes, ou null si non parsable
     */
    public static function performanceToInt($perf)
    {
        $perf = trim($perf);
        if (empty($perf)) return null;

        // Normaliser les différents types de quotes
        $perf = str_replace(["\u{2032}", "\u{2019}"], "'", $perf);   // prime, right quote → '
        $perf = str_replace(["\u{2033}", "\u{201D}"], "''", $perf);  // double prime → ''

        // 1h23'45''12 ou 1h23'45 (heures)
        if (preg_match("/^(\d+)h\s*(\d{1,2})'(\d{2})(?:''(\d{1,2}))?$/", $perf, $m)) {
            $cent = isset($m[4]) && $m[4] !== '' ? (int)$m[4] : 0;
            return (((int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3]) * 100) + $cent;
        }

        // 3'43''65 (minutes + secondes + centièmes)
        if (preg_match("/^(\d+)'(\d{2})''(\d{1,2})$/", $perf, $m)) {
            return ((int)$m[1] * 60 + (int)$m[2]) * 100 + (int)$m[3];
        }

        // 12'09 (minutes + secondes, sans centièmes)
        if (preg_match("/^(\d+)'(\d{2})$/", $perf, $m)) {
            return ((int)$m[1] * 60 + (int)$m[2]) * 100;
        }

        // 10''45 ou 6''37 (secondes + centièmes)
        if (preg_match("/^(\d+)''(\d{1,2})$/", $perf, $m)) {
            return (int)$m[1] * 100 + (int)$m[2];
        }

        // 6m30 ou 14m52 (distance mètres + centimètres avec "m")
        if (preg_match("/^(\d+)m(\d{1,2})$/i", $perf, $m)) {
            return (int)$m[1] * 100 + (int)str_pad($m[2], 2, '0', STR_PAD_RIGHT);
        }

        // 7.34 ou 15.67 (distance en mètres → centimètres)
        if (preg_match("/^(\d+)\.(\d{1,2})$/", $perf, $m)) {
            return (int)$m[1] * 100 + (int)str_pad($m[2], 2, '0', STR_PAD_RIGHT);
        }

        // Entier seul : 734 ou 15
        if (preg_match("/^(\d+)$/", $perf, $m)) {
            return (int)$m[1] * 100;
        }

        return null;
    }

    /**
     * Convertit une date FR "25 Juil 1997" ou "17/05/2008" en "2008-05-17"
     */
    private function convertDateToSql($dateStr)
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr) || $dateStr === '-') return null;

        // Format JJ/MM/AAAA
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        // Format "25 Juil 1997" ou "12 Janv. 2008"
        $moisFR = [
            'janv'  => '01', 'jan'  => '01', 'janvier'  => '01',
            'fev'   => '02', 'févr' => '02', 'février'  => '02', 'fevr' => '02',
            'mars'  => '03', 'mar'  => '03',
            'avr'   => '04', 'avril' => '04',
            'mai'   => '05',
            'juin'  => '06', 'jun'  => '06',
            'juil'  => '07', 'juillet' => '07', 'jul' => '07',
            'août'  => '08', 'aout' => '08', 'aoû' => '08',
            'sept'  => '09', 'sep'  => '09', 'septembre' => '09',
            'oct'   => '10', 'octobre' => '10',
            'nov'   => '11', 'novembre' => '11',
            'dec'   => '12', 'déc'  => '12', 'décembre' => '12',
        ];

        if (preg_match('/(\d{1,2})\s+([A-Za-zÀ-ü]+)\.?\s+(\d{4})/u', $dateStr, $m)) {
            $jour  = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mois  = mb_strtolower(trim($m[2]));
            $annee = $m[3];

            // Chercher le mois correspondant
            foreach ($moisFR as $key => $val) {
                if (strpos($mois, $key) === 0) {
                    return $annee . '-' . $val . '-' . $jour;
                }
            }
        }

        return null;
    }

    /**
     * Retourne le SQL pour créer toutes les tables normalisées
     * Tables de référence (villes, clubs, epreuves, competitions)
     * + tables de données liées par clés étrangères (IDs)
     */
    public static function getCreateTableSQL()
    {
        return [

            // ── TABLES DE RÉFÉRENCE (données uniques, jamais dupliquées) ──

            'villes' => "CREATE TABLE IF NOT EXISTS `villes` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nom` VARCHAR(150) NOT NULL,
                `pays` CHAR(3) DEFAULT '',
                UNIQUE KEY `uk_ville` (`nom`, `pays`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'clubs' => "CREATE TABLE IF NOT EXISTS `clubs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nom` VARCHAR(200) NOT NULL,
                `departement` VARCHAR(100) DEFAULT '',
                `region` VARCHAR(100) DEFAULT '',
                UNIQUE KEY `uk_club` (`nom`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'epreuves' => "CREATE TABLE IF NOT EXISTS `epreuves` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nom` VARCHAR(100) NOT NULL,
                UNIQUE KEY `uk_epreuve` (`nom`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'competitions' => "CREATE TABLE IF NOT EXISTS `competitions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nom` VARCHAR(300) NOT NULL,
                UNIQUE KEY `uk_competition` (`nom`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // ── TABLE PRINCIPALE ──

            'athletes' => "CREATE TABLE IF NOT EXISTS `athletes` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `athlete_id` INT NOT NULL UNIQUE,
                `nom` VARCHAR(100) DEFAULT '',
                `prenom` VARCHAR(100) DEFAULT '',
                `nom_complet` VARCHAR(200) DEFAULT '',
                `date_naissance` DATE DEFAULT NULL,
                `ville_naissance_id` INT DEFAULT NULL,
                `taille_cm` SMALLINT DEFAULT NULL,
                `poids_kg` SMALLINT DEFAULT NULL,
                `categorie` VARCHAR(10) DEFAULT '',
                `sexe` CHAR(1) DEFAULT '',
                `nationalite` CHAR(3) DEFAULT '',
                `licence` VARCHAR(20) DEFAULT '',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (`ville_naissance_id`),
                INDEX (`nationalite`),
                INDEX (`sexe`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // ── TABLES DE DONNÉES (liées par IDs) ──

            'athlete_clubs' => "CREATE TABLE IF NOT EXISTS `athlete_clubs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `athlete_id` INT NOT NULL,
                `club_id` INT NOT NULL,
                `annee_debut` SMALLINT DEFAULT NULL,
                `annee_fin` SMALLINT DEFAULT NULL,
                INDEX (`athlete_id`),
                INDEX (`club_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'athlete_medailles' => "CREATE TABLE IF NOT EXISTS `athlete_medailles` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `athlete_id` INT NOT NULL,
                `type` ENUM('or','argent','bronze','autre') NOT NULL,
                `annee` SMALLINT NOT NULL,
                `competition_id` INT DEFAULT NULL,
                `epreuve_id` INT NOT NULL,
                `ville_id` INT DEFAULT NULL,
                INDEX (`athlete_id`),
                INDEX (`epreuve_id`),
                INDEX (`ville_id`),
                INDEX (`competition_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'athlete_selections' => "CREATE TABLE IF NOT EXISTS `athlete_selections` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `athlete_id` INT NOT NULL,
                `type` VARCHAR(20) DEFAULT '',
                `date` DATE DEFAULT NULL,
                `duree_jours` SMALLINT DEFAULT NULL,
                `age` TINYINT DEFAULT NULL,
                `competition_id` INT DEFAULT NULL,
                `epreuve_id` INT DEFAULT NULL,
                `classement` TINYINT DEFAULT NULL,
                `performance` VARCHAR(30) DEFAULT '',
                INDEX (`athlete_id`),
                INDEX (`epreuve_id`),
                INDEX (`competition_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'athlete_progressions' => "CREATE TABLE IF NOT EXISTS `athlete_progressions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `athlete_id` INT NOT NULL,
                `epreuve_id` INT NOT NULL,
                `annee` SMALLINT NOT NULL,
                `age` TINYINT DEFAULT NULL,
                `performance` VARCHAR(30) NOT NULL,
                `vent` VARCHAR(10) DEFAULT '',
                `date` DATE DEFAULT NULL,
                `ville_id` INT DEFAULT NULL,
                INDEX (`athlete_id`),
                INDEX (`epreuve_id`),
                INDEX (`ville_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'athlete_records' => "CREATE TABLE IF NOT EXISTS `athlete_records` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `athlete_id` INT NOT NULL,
                `epreuve_id` INT NOT NULL,
                `performance` VARCHAR(30) NOT NULL,
                `date` DATE DEFAULT NULL,
                `categorie` VARCHAR(10) DEFAULT '',
                `club_id` INT DEFAULT NULL,
                `ligue_dept` VARCHAR(50) DEFAULT '',
                `ville_id` INT DEFAULT NULL,
                INDEX (`athlete_id`),
                INDEX (`epreuve_id`),
                INDEX (`club_id`),
                INDEX (`ville_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }

    // =========================================================================
    //  HELPERS : INSERT OR GET ID (tables de référence)
    // =========================================================================

    /**
     * Insère une valeur dans une table de référence si elle n'existe pas,
     * et retourne l'ID dans tous les cas.
     */
    /**
     * Insère dans une table de référence si n'existe pas, retourne l'ID
     * @param string $idCol   Nom de la colonne ID (ex: 'id_ville')
     * @param string $uniqueCol Nom de la colonne unique à chercher
     */
    private function getOrCreateRef($db, $table, $data, $idCol, $uniqueCol)
    {
        $val = $db->connection->real_escape_string($data[$uniqueCol]);

        // Chercher si existe déjà
        if ($table === 'villes' && isset($data['pays_ville'])) {
            $pays = $db->connection->real_escape_string($data['pays_ville']);
            $sql = "SELECT `$idCol` FROM `$table` WHERE `$uniqueCol` = '$val' AND `pays_ville` = '$pays' LIMIT 1";
        } else {
            $sql = "SELECT `$idCol` FROM `$table` WHERE `$uniqueCol` = '$val' LIMIT 1";
        }

        $res = $db->connection->query($sql);
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            return (int)$row[$idCol];
        }

        // Sinon insérer
        $cols = implode("`, `", array_keys($data));
        $vals = implode("', '", array_map([$db->connection, 'real_escape_string'], array_values($data)));
        $db->connection->query("INSERT INTO `$table` (`$cols`) VALUES ('$vals')");

        return (int)$db->connection->insert_id;
    }

    private function getVilleId($db, $nom, $pays = '')
    {
        $nom = trim($nom);
        if (empty($nom)) return null;
        return $this->getOrCreateRef($db, 'villes',
            ['nom_ville' => $nom, 'pays_ville' => $pays],
            'id_ville', 'nom_ville');
    }

    private function getClubId($db, $nom)
    {
        $nom = trim($nom);
        if (empty($nom)) return null;
        return $this->getOrCreateRef($db, 'clubs',
            ['nom_club' => $nom],
            'id_club', 'nom_club');
    }

    private function getEpreuveId($db, $nom)
    {
        $nom = trim($nom);
        if (empty($nom)) return null;
        return $this->getOrCreateRef($db, 'epreuves',
            ['nom_epreuve' => $nom],
            'id_epreuve', 'nom_epreuve');
    }

    private function getCompetitionId($db, $nom)
    {
        $nom = trim($nom);
        if (empty($nom)) return null;
        return $this->getOrCreateRef($db, 'competitions',
            ['nom_competition' => $nom],
            'id_competition', 'nom_competition');
    }

    // =========================================================================
    //  SAUVEGARDE EN BDD NORMALISÉE
    // =========================================================================

    /**
     * Crée les tables et insère toutes les données normalisées
     * @param DatabaseHandler $db Instance du DatabaseHandler
     * @return array Résumé des insertions
     */
    public function saveToDatabase(DatabaseHandler $db)
    {
        $resultats = [];

        // 1. Créer toutes les tables
        $tables = self::getCreateTableSQL();
        foreach ($tables as $sql) {
            $db->action_sql($sql);
        }

        // 2. Insérer l'identité
        if (!empty($this->identite['nom_complet'])) {
            $villeNaissId = $this->getVilleId($db, $this->identite['lieu_naissance'] ?? '');

            $row = [
                'athlete_id_externe'    => $this->identite['athlete_id'],
                'nom_1_athlete'         => $this->identite['nom_1'],
                'nom_2_athlete'         => $this->identite['nom_2'],
                'nom_3_athlete'         => $this->identite['nom_3'],
                'nom_4_athlete'         => $this->identite['nom_4'],
                'nom_complet_athlete'   => $this->identite['nom_complet'],
                'date_naissance_athlete' => $this->identite['date_naissance'] ?? '',
                'id_ville_naissance'    => $villeNaissId ?? 0,
                'taille_cm_athlete'     => $this->identite['taille_cm'] ?? 0,
                'poids_kg_athlete'      => $this->identite['poids_kg'] ?? 0,
                'categorie_athlete'     => $this->identite['categorie'],
                'sexe_athlete'          => $this->identite['sexe'],
                'nationalite_athlete'   => $this->identite['nationalite'],
                'licence_athlete'       => $this->identite['licence'],
            ];
            $res = $db->insert_sql_safe('athletes', $row, 'athlete_id_externe');
            $resultats['identite'] = $res;
        }

        // 3. Clubs
        $count = 0;
        foreach ($this->clubs as $club) {
            $clubId = $this->getClubId($db, $club['nom_club']);
            if ($clubId) {
                $res = $db->insert_sql('athlete_clubs', [
                    'id_athlete'  => $club['athlete_id'],
                    'id_club'     => $clubId,
                    'annee_debut' => $club['annee_debut'] ?? 0,
                    'annee_fin'   => $club['annee_fin'] ?? 0,
                ]);
                if ($res['success']) $count++;
            }
        }
        $resultats['clubs'] = $count;

        // 4. Médailles
        $count = 0;
        foreach ($this->medailles as $med) {
            $epreuveId = $this->getEpreuveId($db, $med['epreuve']);
            $villeId   = $this->getVilleId($db, $med['lieu'], $med['pays']);
            $compId    = $this->getCompetitionId($db, $med['competition']);

            $res = $db->insert_sql('athlete_medailles', [
                'id_athlete'     => $med['athlete_id'],
                'type_medaille'  => $med['type'],
                'annee_medaille' => $med['annee'],
                'id_competition' => $compId ?? 0,
                'id_epreuve'     => $epreuveId ?? 0,
                'id_ville'       => $villeId ?? 0,
            ]);
            if ($res['success']) $count++;
        }
        $resultats['medailles'] = $count;

        // 5. Sélections
        $count = 0;
        foreach ($this->selections as $sel) {
            $epreuveId = $this->getEpreuveId($db, $sel['epreuve']);
            $compId    = $this->getCompetitionId($db, $sel['competition']);

            $res = $db->insert_sql('athlete_selections', [
                'id_athlete'              => $sel['athlete_id'],
                'type_selection'          => $sel['type'],
                'date_selection'          => $sel['date'] ?? '',
                'duree_jours_selection'   => $sel['duree_jours'] ?? 0,
                'age_selection'           => $sel['age'] ?? 0,
                'id_competition'          => $compId ?? 0,
                'id_epreuve'              => $epreuveId ?? 0,
                'classement_selection'    => $sel['classement'] ?? 0,
                'performance_selection'   => $sel['performance'],
            ]);
            if ($res['success']) $count++;
        }
        $resultats['selections'] = $count;

        // 6. Progressions
        $count = 0;
        foreach ($this->progressions as $prog) {
            $epreuveId = $this->getEpreuveId($db, $prog['epreuve']);
            $villeId   = $this->getVilleId($db, $prog['lieu']);

            $res = $db->insert_sql('athlete_progressions', [
                'id_athlete'               => $prog['athlete_id'],
                'id_epreuve'               => $epreuveId ?? 0,
                'annee_progression'        => $prog['annee'],
                'age_progression'          => $prog['age'] ?? 0,
                'performance_progression'  => $prog['performance'],
                'vent_progression'         => $prog['vent'],
                'date_progression'         => $prog['date'] ?? '',
                'id_ville'                 => $villeId ?? 0,
            ]);
            if ($res['success']) $count++;
        }
        $resultats['progressions'] = $count;

        // 7. Records
        $count = 0;
        foreach ($this->records as $rec) {
            $epreuveId = $this->getEpreuveId($db, $rec['epreuve']);
            $clubId    = $this->getClubId($db, $rec['club']);
            $villeId   = $this->getVilleId($db, $rec['lieu']);

            $res = $db->insert_sql('athlete_records', [
                'id_athlete'         => $rec['athlete_id'],
                'id_epreuve'         => $epreuveId ?? 0,
                'performance_record' => $rec['performance'],
                'date_record'        => $rec['date'] ?? '',
                'categorie_record'   => $rec['categorie'],
                'id_club'            => $clubId ?? 0,
                'ligue_dept_record'  => $rec['ligue_dept'],
                'id_ville'           => $villeId ?? 0,
            ]);
            if ($res['success']) $count++;
        }
        $resultats['records'] = $count;

        return $resultats;
    }

    /**
     * Retourne toutes les données sous forme de tableau PHP propre
     * Idéal pour debug ou export fichier
     */
    public function toArray()
    {
        return [
            'identite'     => $this->identite,
            'clubs'        => $this->clubs,
            'medailles'    => $this->medailles,
            'selections'   => $this->selections,
            'progressions' => $this->progressions,
            'records'      => $this->records,
            'podiums'      => $this->podiums,
            'resultats'    => $this->resultats,
            'niveaux'      => $this->niveaux,
        ];
    }
}
