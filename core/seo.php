<?php
/**
 * core/seo.php — Génération SEO pour les pages athlètes
 *
 * Génère : meta description, canonical, Open Graph, Twitter Cards, Schema.org JSON-LD
 *
 * 2 modes d'utilisation :
 *   1) Depuis les fichiers src/ (lecture directe, rapide, pas de BDD) :
 *      $seo = seoFromSrcFile($athleteIdExterne);
 *
 *   2) Depuis les données API déjà chargées :
 *      $seo = generateAthleteSEO($data);
 *
 *   Puis dans <head> :
 *      echo $seo['meta'];
 *      echo $seo['jsonld'];
 */

/**
 * Charge le JSON depuis src/{id}.php et génère le SEO
 * Pas d'appel API, pas de BDD — lecture fichier uniquement
 */
function seoFromSrcFile($athleteIdExterne, $pageType = 'profil') {
    $srcFile = dirname(__DIR__) . '/src/' . intval($athleteIdExterne) . '.php';
    if (!file_exists($srcFile)) return null;

    $json = @file_get_contents($srcFile);
    if (!$json) return null;

    $data = json_decode($json, true);
    if (!$data || empty($data['identite'])) return null;

    return generateAthleteSEO($data, $pageType);
}

function generateAthleteSEO($data, $pageType = 'profil') {
    // Supporte les 2 formats : API (avec 'success') et src/ (sans 'success')
    if (!$data) return ['title' => '', 'meta' => '', 'jsonld' => ''];
    // Si c'est un retour API, vérifier success
    if (isset($data['success']) && !$data['success']) return ['title' => '', 'meta' => '', 'jsonld' => ''];

    $i         = $data['identite'] ?? [];
    $clubs     = $data['clubs'] ?? [];
    $records   = $data['records'] ?? [];
    $medailles = $data['medailles'] ?? [];
    $selections = $data['selections'] ?? [];

    $nom       = $i['nom_complet'] ?? 'Athlète';
    $prenom    = $i['nom_2'] ?? explode(' ', $nom)[0];
    $nomFamille = $i['nom_1'] ?? '';
    $clubName  = !empty($clubs) ? $clubs[0]['nom_club'] : '';
    $categorie = $i['categorie'] ?? '';
    $sexe      = $i['sexe'] ?? '';
    $nationalite = $i['nationalite'] ?? '';
    $athleteId = $i['athlete_id'] ?? 0;
    $idAthlete = $i['id_athlete'] ?? 0;

    // Épreuve principale (premier record)
    $epreuvePrincipale = '';
    if (!empty($records)) {
        $epreuvePrincipale = $records[0]['epreuve'] ?? '';
    }

    // Compteurs médailles
    $nbOr     = count(array_filter($medailles, function($m){ return $m['type'] === 'or'; }));
    $nbArgent = count(array_filter($medailles, function($m){ return $m['type'] === 'argent'; }));
    $nbBronze = count(array_filter($medailles, function($m){ return $m['type'] === 'bronze'; }));
    $totalMed = $nbOr + $nbArgent + $nbBronze;

    // === TITLE ===
    $title = htmlspecialchars($nom);
    if ($epreuvePrincipale) $title .= ' — ' . htmlspecialchars($epreuvePrincipale);
    if ($clubName) $title .= ' | ' . htmlspecialchars($clubName);
    $title .= ' — Bokonzi';

    // === META DESCRIPTION ===
    $descParts = [];
    $descParts[] = 'Fiche athlète de ' . $nom;
    if ($clubName) $descParts[] = $clubName;
    if ($categorie) $descParts[] = 'catégorie ' . $categorie;
    if (count($records) > 0) $descParts[] = count($records) . ' records personnels';
    if ($totalMed > 0) $descParts[] = $totalMed . ' médailles';
    if (count($selections) > 0) $descParts[] = count($selections) . ' sélections';
    $description = implode(', ', $descParts) . '. Résultats, progressions et palmarès sur Bokonzi.';

    // === URLs ===
    $baseUrl = 'https://bokonzi.com';
    if ($pageType === 'profil') {
        $canonicalUrl = $baseUrl . '/index.php?page=profil&id=' . $athleteId;
    } else {
        $canonicalUrl = $baseUrl . '/index.php?page=profil&id=' . $athleteId;
    }

    // === META TAGS ===
    $meta = '';
    $meta .= '    <meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
    $meta .= '    <link rel="canonical" href="' . htmlspecialchars($canonicalUrl) . '">' . "\n";

    // Open Graph
    $meta .= '    <meta property="og:title" content="' . $title . '">' . "\n";
    $meta .= '    <meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $meta .= '    <meta property="og:url" content="' . htmlspecialchars($canonicalUrl) . '">' . "\n";
    $meta .= '    <meta property="og:type" content="profile">' . "\n";
    $meta .= '    <meta property="og:site_name" content="Bokonzi">' . "\n";
    if ($prenom) $meta .= '    <meta property="profile:first_name" content="' . htmlspecialchars($prenom) . '">' . "\n";
    if ($nomFamille) $meta .= '    <meta property="profile:last_name" content="' . htmlspecialchars($nomFamille) . '">' . "\n";

    // Twitter Card
    $meta .= '    <meta name="twitter:card" content="summary">' . "\n";
    $meta .= '    <meta name="twitter:title" content="' . $title . '">' . "\n";
    $meta .= '    <meta name="twitter:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $meta .= '    <meta name="twitter:site" content="@bokonzi">' . "\n";

    // === JSON-LD : Person ===
    $person = [
        '@context' => 'https://schema.org',
        '@type'    => 'Person',
        'name'     => $nom,
        'url'      => $canonicalUrl,
    ];
    if ($prenom) $person['givenName'] = $prenom;
    if ($nomFamille) $person['familyName'] = $nomFamille;

    if (!empty($i['date_naissance'])) {
        $person['birthDate'] = $i['date_naissance'];
    }
    if (!empty($i['lieu_naissance'])) {
        $person['birthPlace'] = ['@type' => 'Place', 'name' => $i['lieu_naissance']];
    }

    $natNames = ['FRA'=>'France','MAR'=>'Maroc','SEN'=>'Sénégal','CMR'=>'Cameroun','ALG'=>'Algérie','TUN'=>'Tunisie','BEL'=>'Belgique','SUI'=>'Suisse','CIV'=>'Côte d\'Ivoire','GBR'=>'Royaume-Uni','USA'=>'États-Unis','ESP'=>'Espagne','ITA'=>'Italie','POR'=>'Portugal','GER'=>'Allemagne','BRA'=>'Brésil','JAM'=>'Jamaïque','HAI'=>'Haïti','COD'=>'RD Congo','COG'=>'Congo','MLI'=>'Mali','GIN'=>'Guinée','GAB'=>'Gabon','BUR'=>'Burkina Faso','NIG'=>'Niger','BEN'=>'Bénin','TOG'=>'Togo','RWA'=>'Rwanda','MAD'=>'Madagascar','KEN'=>'Kenya','ETH'=>'Éthiopie','RSA'=>'Afrique du Sud','JPN'=>'Japon','AUS'=>'Australie','CAN'=>'Canada'];
    if ($nationalite) {
        $natNom = $natNames[$nationalite] ?? $nationalite;
        $person['nationality'] = ['@type' => 'Country', 'name' => $natNom];
    }

    if (!empty($i['taille_cm'])) {
        $person['height'] = ['@type' => 'QuantitativeValue', 'value' => (int)$i['taille_cm'], 'unitCode' => 'CMT'];
    }
    if (!empty($i['poids_kg'])) {
        $person['weight'] = ['@type' => 'QuantitativeValue', 'value' => (int)$i['poids_kg'], 'unitCode' => 'KGM'];
    }
    if ($clubName) {
        $person['memberOf'] = ['@type' => 'SportsTeam', 'name' => $clubName];
    }
    // sameAs externe masque pour ne pas exposer la source des donnees

    // Description structurée
    $structDesc = $nom;
    if ($sexe === 'M') $structDesc .= ', athlète masculin';
    elseif ($sexe === 'F') $structDesc .= ', athlète féminine';
    if ($categorie) $structDesc .= ', catégorie ' . $categorie;
    if ($clubName) $structDesc .= ', ' . $clubName;
    if (count($records) > 0) $structDesc .= '. ' . count($records) . ' records personnels';
    if ($totalMed > 0) $structDesc .= ', ' . $totalMed . ' médailles';
    $person['description'] = $structDesc . '.';

    // Médailles comme awards
    if ($totalMed > 0) {
        $awards = [];
        foreach ($medailles as $m) {
            $award = ucfirst($m['type']);
            if (!empty($m['epreuve'])) $award .= ' — ' . $m['epreuve'];
            if (!empty($m['competition'])) $award .= ' (' . $m['competition'] . ')';
            if (!empty($m['annee'])) $award .= ' ' . $m['annee'];
            $awards[] = $award;
        }
        $person['award'] = $awards;
    }

    // === JSON-LD : BreadcrumbList ===
    $breadcrumb = [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => $baseUrl . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Athlètes', 'item' => $baseUrl . '/index.php?page=athletes'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $nom],
        ],
    ];

    $jsonld = '    <script type="application/ld+json">' . "\n";
    $jsonld .= '    ' . json_encode($person, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    $jsonld .= '    </script>' . "\n";
    $jsonld .= '    <script type="application/ld+json">' . "\n";
    $jsonld .= '    ' . json_encode($breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    $jsonld .= '    </script>' . "\n";

    return [
        'title' => $title,
        'meta'  => $meta,
        'jsonld' => $jsonld,
        'canonical' => $canonicalUrl,
        'description' => $description,
    ];
}
