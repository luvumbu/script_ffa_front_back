<?php
/**
 * api/search.php — Recherche d'athletes avec filtres
 *
 * Usage :
 *   api/search.php?nom=dupont                          Recherche dans nom_complet
 *   api/search.php?nom1=dupont                         Recherche par nom de famille
 *   api/search.php?nom2=jean                           Recherche par prenom
 *   api/search.php?nom1=dupont&nom2=jean               Nom + prenom
 *   api/search.php?club=Paris                          Recherche par club
 *   api/search.php?categorie=SE                        Filtrer par categorie
 *   api/search.php?sexe=M                              Filtrer par sexe
 *   api/search.php?nationalite=FRA                     Filtrer par nationalite
 *   api/search.php?epreuve=100m                        Athletes ayant cette epreuve dans leurs records
 *   api/search.php?ville=Paris                         Athletes avec resultats dans cette ville
 *   api/search.php?competition=France                  Athletes avec cette competition (medailles)
 *   api/search.php?medaille=or                         Athletes ayant une medaille d'or
 *   api/search.php?annee=2024                          Athletes avec resultats en 2024
 *   api/search.php?licence=1234567                     Recherche par numero de licence
 *   api/search.php?nom=dupont&sexe=M&categorie=SE      Combinaison de filtres
 *   api/search.php?nom=dupont&page=2&limit=20          Pagination
 */

require_once __DIR__ . '/config.php';

// ---- Rate limiting recherches : 5/jour + 30 min entre 2 recherches ----
// Logique centralisee dans core/search_limit.php (cf. bkSearchLimit()).
// Le quota n'est consomme qu'une fois les filtres connus (voir plus bas).
require_once __DIR__ . '/../core/search_limit.php';
$_sl   = null;
$_isSA = false;

function highestNiveau($niveaux) {
    $order = ['IE'=>100,'IR'=>99];
    foreach (['N'=>90,'R'=>80,'D'=>70] as $p=>$b)
        for ($i=1;$i<=8;$i++) $order[$p.$i] = $b - $i;
    $best = null; $bestS = -1;
    foreach ($niveaux as $n) {
        $s = $order[trim($n)] ?? 0;
        if ($s > $bestS) { $bestS = $s; $best = trim($n); }
    }
    return $best;
}

// Parametres
$nom         = trim($_GET['nom'] ?? '');
$nom1        = trim($_GET['nom1'] ?? '');
$nom2        = trim($_GET['nom2'] ?? '');
$club        = trim($_GET['club'] ?? '');
$categorie   = trim($_GET['categorie'] ?? '');
$sexe        = trim($_GET['sexe'] ?? '');
$nationalite = trim($_GET['nationalite'] ?? '');
$epreuve     = trim($_GET['epreuve'] ?? '');
$ville       = trim($_GET['ville'] ?? '');
$competition = trim($_GET['competition'] ?? '');
$medaille    = trim($_GET['medaille'] ?? '');
$annee       = trim($_GET['annee'] ?? '');
$licence     = trim($_GET['licence'] ?? '');
$niveau      = trim($_GET['niveau'] ?? '');
// Filtres avances : athletes multi-clubs / multi-disciplines
$multiClubs       = !empty($_GET['multi_clubs']);
$multiDisciplines = !empty($_GET['multi_disciplines']);
$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset      = ($page - 1) * $limit;

// ---- Quota de recherches : 5/jour + 30 min de delai entre 2 recherches ----
// On ne consomme le quota que s'il s'agit d'une vraie recherche (au moins 1 filtre).
$hasFilter = ($nom !== '' || $nom1 !== '' || $nom2 !== '' || $club !== '' || $categorie !== ''
    || $sexe !== '' || $nationalite !== '' || $epreuve !== '' || $ville !== '' || $competition !== ''
    || $medaille !== '' || $annee !== '' || $licence !== '' || $niveau !== ''
    || $multiClubs || $multiDisciplines || trim($_GET['ep_type'] ?? '') !== '');
$_sl   = bkSearchLimit($conn, $hasFilter);
$_isSA = $_sl['is_sa'];
if ($hasFilter && $_sl['blocked']) {
    jsonResponse(array_merge([
        'success'       => false,
        'limit_reached' => true,
        'reason'        => $_sl['reason'],
        'limit'         => $_sl['limit'],
        'remaining'     => 0,
        'error'         => $_sl['reason'] === 'trial'
            ? 'Decouverte gratuite terminee — inscrivez-vous pour continuer'
            : ($_sl['reason'] === 'cooldown'
                ? 'Patientez avant la prochaine recherche'
                : 'Limite de recherches atteinte'),
    ], bkSlFields($_sl)), 429);
}

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'search_' . md5($nom.'_'.$nom1.'_'.$nom2.'_'.$club.'_'.$categorie.'_'.$sexe.'_'.$nationalite.'_'.$epreuve.'_'.$ville.'_'.$competition.'_'.$medaille.'_'.$annee.'_'.$licence.'_'.$niveau.'_'.$page.'_'.$limit.'_mc'.($multiClubs?1:0).'_md'.($multiDisciplines?1:0).'_et'.strtolower(trim($_GET['ep_type'] ?? '')));
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) {
        // Le cache ne doit pas servir des compteurs de quota perimes : on les rafraichit.
        $_cd = json_decode($cached, true);
        if (is_array($_cd)) {
            echo json_encode(array_merge($_cd, bkSlFields($_sl)), JSON_UNESCAPED_UNICODE);
        } else {
            echo $cached;
        }
        $conn->close();
        exit;
    }
}

// Construire la requete
$where = ['1=1']; // garde-fou : evite "WHERE " vide si seuls des joins sont ajoutes (ex: filtre club uniquement avec super admin)
$joins = [];

if ($nom !== '') {
    $nomWords = preg_split('/\s+/', $nom);
    $nomConds = [];
    foreach ($nomWords as $w) {
        $wEsc = $conn->real_escape_string($w);
        $nomConds[] = "a.nom_complet_athlete LIKE '%$wEsc%'";
    }
    $where[] = '(' . implode(' AND ', $nomConds) . ')';
}

if ($nom1 !== '') {
    $nom1Esc = $conn->real_escape_string($nom1);
    $where[] = "a.nom_1_athlete LIKE '%$nom1Esc%'";
}

if ($nom2 !== '') {
    $nom2Esc = $conn->real_escape_string($nom2);
    $where[] = "a.nom_2_athlete LIKE '%$nom2Esc%'";
}

if ($categorie !== '') {
    $catEsc = $conn->real_escape_string($categorie);
    $where[] = "a.categorie_athlete = '$catEsc'";
}

if ($sexe !== '') {
    $sexeEsc = $conn->real_escape_string(strtoupper($sexe));
    $where[] = "a.sexe_athlete = '$sexeEsc'";
}

if ($nationalite !== '') {
    $natEsc = $conn->real_escape_string(strtoupper($nationalite));
    $where[] = "a.nationalite_athlete = '$natEsc'";
}

if ($club !== '') {
    $clubEsc = $conn->real_escape_string($club);
    $joins[] = "JOIN athlete_clubs ac ON ac.id_athlete = a.id_athlete";
    $joins[] = "JOIN clubs cl ON cl.id_club = ac.id_club AND cl.nom_club LIKE '%$clubEsc%'";
}

// Niveau + Epreuve : MODE STRICT (niveau X SUR epreuve Y, via athlete_niv_perfs.code_perf_niveau)
$strictNivEp = ($niveau !== '' && $epreuve !== '');
if ($strictNivEp) {
    $allowedNiv = ['IA','IB','IE','IR','IR1','IR2','IR3','IR4','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
    $nivCodes = [];
    foreach (explode(',', $niveau) as $n) {
        $code = trim($n);
        if (in_array($code, $allowedNiv, true)) $nivCodes[] = "'" . $conn->real_escape_string($code) . "'";
    }
    if (!empty($nivCodes)) {
        $nivList = implode(',', $nivCodes);
        $epEsc = $conn->real_escape_string($epreuve);
        $joins[] = "JOIN athlete_niveaux an_strict ON an_strict.id_athlete = a.id_athlete";
        $joins[] = "JOIN athlete_niv_perfs anp_strict ON anp_strict.id_niveau = an_strict.id_niveau AND anp_strict.code_perf_niveau IN ($nivList)";
        $joins[] = "JOIN epreuves ep_strict ON ep_strict.id_epreuve = anp_strict.id_epreuve AND ep_strict.nom_epreuve LIKE '%$epEsc%'";
    } else {
        $strictNivEp = false; // niveaux invalides → tomber sur mode normal
    }
}

// Mode normal : epreuve seule
if (!$strictNivEp && $epreuve !== '') {
    $epEsc = $conn->real_escape_string($epreuve);
    $joins[] = "JOIN athlete_records ar ON ar.id_athlete = a.id_athlete";
    $joins[] = "JOIN epreuves ep ON ep.id_epreuve = ar.id_epreuve AND ep.nom_epreuve LIKE '%$epEsc%'";
}

// Mode normal : niveau seul (best level global)
if (!$strictNivEp && $niveau !== '') {
    $hierarchy = ['IA','IB','IE','IR','IR2','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
    $hierarchyList = "'" . implode("','", $hierarchy) . "'";
    $filterRanks = [];
    foreach (explode(',', $niveau) as $n) {
        $code = trim($n);
        $idx = array_search($code, $hierarchy, true);
        if ($idx !== false) $filterRanks[] = $idx + 1;
    }
    if (!empty($filterRanks)) {
        $rankList = implode(',', $filterRanks);
        $joins[] = "INNER JOIN (
            SELECT id_athlete, MIN(FIELD(code_niveau, $hierarchyList)) as best_rank
            FROM athlete_niveaux
            WHERE FIELD(code_niveau, $hierarchyList) > 0
            GROUP BY id_athlete
        ) an_filt ON an_filt.id_athlete = a.id_athlete AND an_filt.best_rank IN ($rankList)";
    }
}

if ($ville !== '') {
    $villeEsc = $conn->real_escape_string($ville);
    $joins[] = "JOIN athlete_resultats ares ON ares.id_athlete = a.id_athlete";
    $joins[] = "JOIN villes vi ON vi.id_ville = ares.id_ville AND vi.nom_ville LIKE '%$villeEsc%'";
}

if ($competition !== '') {
    $compEsc = $conn->real_escape_string($competition);
    $joins[] = "JOIN athlete_medailles am ON am.id_athlete = a.id_athlete";
    $joins[] = "JOIN competitions co ON co.id_competition = am.id_competition AND co.nom_competition LIKE '%$compEsc%'";
}

if ($medaille !== '') {
    $medEsc = $conn->real_escape_string(strtolower($medaille));
    if (!in_array('athlete_medailles', array_map(function($j) { return strpos($j, 'athlete_medailles') !== false; }, $joins))) {
        $joins[] = "JOIN athlete_medailles am2 ON am2.id_athlete = a.id_athlete AND am2.type_medaille = '$medEsc'";
    }
}

if ($annee !== '') {
    $anneeEsc = (int)$annee;
    $joins[] = "JOIN athlete_resultats ares2 ON ares2.id_athlete = a.id_athlete AND ares2.annee_resultat = $anneeEsc";
}

if ($licence !== '') {
    $licEsc = $conn->real_escape_string($licence);
    $where[] = "a.licence_athlete LIKE '%$licEsc%'";
}

// Filtres avances : athletes multi-clubs (transferts)
if ($multiClubs) {
    $where[] = "(SELECT COUNT(DISTINCT id_club) FROM athlete_clubs WHERE id_athlete = a.id_athlete) >= 2";
}

// Filtres avances : athletes polyvalents (multi-disciplines, 2+ epreuves dans leurs records)
if ($multiDisciplines) {
    $where[] = "(SELECT COUNT(DISTINCT id_epreuve) FROM athlete_records WHERE id_athlete = a.id_athlete) >= 2";
}

// Filtres avances : type d'epreuve (sprint, sauts, lancers, etc.)
$epType = trim($_GET['ep_type'] ?? '');
if ($epType !== '') {
    // Regex MySQL par categorie FFA — appliquees sur epreuves.nom_epreuve
    $epTypeMap = [
        'sprint'    => '^(60m|100m|200m|400m)( |$|-)',
        'demi-fond' => '^(600m|800m|1000m|1500m|Mile|2000m)',
        'fond'      => '^(3000m|5000m|10000m|Marathon|Semi|Heure|Steeple)',
        'haies'     => 'Haies|Steeple',
        'sauts'     => '^(Longueur|Triple saut|Hauteur|Perche)',
        'lancers'   => '^(Poids|Disque|Marteau|Javelot)',
        'combines'  => '^(Decathlon|Heptathlon|Pentathlon|Triathlon|Tetrathlon)',
        'marche'    => '^Marche',
        'route'     => '(Route|Cross|Trail|Ekiden)',
    ];
    $epTypeKey = strtolower($epType);
    if (isset($epTypeMap[$epTypeKey])) {
        $regex = $conn->real_escape_string($epTypeMap[$epTypeKey]);
        $where[] = "EXISTS (
            SELECT 1 FROM athlete_records ar_t
            JOIN epreuves ep_t ON ep_t.id_epreuve = ar_t.id_epreuve
            WHERE ar_t.id_athlete = a.id_athlete
              AND ep_t.nom_epreuve REGEXP '$regex'
        )";
    }
}

// Filtre clubs >5000 supprime — tous les athletes sont trouvables

// Au moins un filtre requis (on ignore la sentinelle '1=1')
$_realWhere = array_filter($where, function($w) { return $w !== '1=1'; });
if (empty($_realWhere) && empty($joins)) {
    jsonResponse([
        'success' => false,
        'error'   => 'Au moins un filtre requis',
        'usage'   => [
            'nom'         => 'Recherche dans nom_complet (partiel) → table athletes',
            'nom1'        => 'Recherche par nom de famille → table athletes.nom_1_athlete',
            'nom2'        => 'Recherche par prenom → table athletes.nom_2_athlete',
            'sexe'        => 'M ou F → table athletes',
            'categorie'   => 'Code FFA (SE, ES, JU, CA, MI, BE, V1...) → table athletes',
            'nationalite' => 'Code ISO 3 lettres (FRA, MAR, SEN...) → table athletes',
            'club'        => 'Nom du club (partiel) → table clubs via athlete_clubs',
            'epreuve'     => 'Nom d\'epreuve (partiel) → table epreuves via athlete_records',
            'ville'       => 'Nom de ville (partiel) → table villes via athlete_resultats',
            'competition' => 'Nom de competition (partiel) → table competitions via athlete_medailles',
            'medaille'    => 'Type de medaille (or, argent, bronze) → table athlete_medailles',
            'annee'       => 'Annee de resultat (2024, 2023...) → table athlete_resultats',
            'licence'     => 'Numero de licence (partiel) → table athletes.licence_athlete',
            'multi_clubs'       => '1 = uniquement athletes ayant porte 2+ clubs differents (transferts)',
            'multi_disciplines' => '1 = uniquement athletes avec records dans 2+ epreuves differentes (polyvalents)',
            'ep_type'           => 'Type d\'epreuve : sprint, demi-fond, fond, haies, sauts, lancers, combines, marche, route',
            'page'        => 'Numero de page (defaut: 1)',
            'limit'       => 'Resultats par page (defaut: 50, max: 100)',
        ]
    ], 400);
}

// Admin avec cle API peut voir les athletes masques
if (!$_isSA) {
    $where[] = "a.visible = 1";
}
$joinSql = implode("\n", $joins);
$whereSql = "WHERE " . implode(" AND ", $where);

// Colonnes supplementaires selon le filtre
$extraCols = "";
if ($club !== '') {
    $extraCols = ", NULLIF(ac.annee_debut, 0) as filtre_debut, NULLIF(ac.annee_fin, 0) as filtre_fin";
} elseif ($ville !== '') {
    $extraCols = ", MIN(NULLIF(ares.annee_resultat, 0)) as filtre_debut, MAX(NULLIF(ares.annee_resultat, 0)) as filtre_fin";
} elseif ($epreuve !== '') {
    $extraCols = ", MIN(ar.date_record) as filtre_debut, MAX(ar.date_record) as filtre_fin";
} elseif ($competition !== '') {
    $extraCols = ", MIN(NULLIF(am.annee_medaille, 0)) as filtre_debut, MAX(NULLIF(am.annee_medaille, 0)) as filtre_fin";
}

$useGroup = ($ville !== '' || $epreuve !== '' || $competition !== '');

// Compter le total
$countSql = "SELECT COUNT(DISTINCT a.id_athlete) as total FROM athletes a $joinSql $whereSql";
$countRes = $conn->query($countSql);
$total = $countRes ? (int)$countRes->fetch_assoc()['total'] : 0;

// Sous-requete pour compter les records par athlete
$nbRecordsCol = "(SELECT COUNT(*) FROM athlete_records _rc WHERE _rc.id_athlete = a.id_athlete) as nb_records";

// Recuperer les athletes
if ($useGroup) {
    $sql = "SELECT a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete,
                   a.nom_1_athlete, a.nom_2_athlete,
                   a.date_naissance_athlete, a.annee_naissance_athlete,
                   a.categorie_athlete, a.sexe_athlete, a.nationalite_athlete,
                   a.licence_athlete, $nbRecordsCol $extraCols
            FROM athletes a
            $joinSql
            $whereSql
            GROUP BY a.id_athlete
            ORDER BY a.nom_complet_athlete ASC
            LIMIT $limit OFFSET $offset";
} else {
    $sql = "SELECT DISTINCT a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete,
                   a.nom_1_athlete, a.nom_2_athlete,
                   a.date_naissance_athlete, a.annee_naissance_athlete,
                   a.categorie_athlete, a.sexe_athlete, a.nationalite_athlete,
                   a.licence_athlete, $nbRecordsCol $extraCols
            FROM athletes a
            $joinSql
            $whereSql
            ORDER BY a.nom_complet_athlete ASC
            LIMIT $limit OFFSET $offset";
}

$res = $conn->query($sql);
$athletes = [];

if ($res) while ($row = $res->fetch_assoc()) {
    // Club le plus recent
    $clubName = '';
    $rcl = $conn->query("SELECT c.nom_club FROM athlete_clubs ac JOIN clubs c ON c.id_club = ac.id_club WHERE ac.id_athlete = " . (int)$row['id_athlete'] . " ORDER BY ac.annee_debut DESC LIMIT 1");
    if ($rcl && $rcr = $rcl->fetch_assoc()) $clubName = rtrim($rcr['nom_club'] ?? '', '* ');

    $athlete = [
        'id_athlete'       => (int)$row['id_athlete'],
        'athlete_id'       => (int)$row['athlete_id_externe'],
        'nom_complet'      => $row['nom_complet_athlete'],
        'nom_athlete'      => $row['nom_1_athlete'] ?? '',
        'prenom_athlete'   => $row['nom_2_athlete'] ?? '',
        'date_naissance'   => $row['date_naissance_athlete'],
        'annee_naissance'  => $row['annee_naissance_athlete'] ? (int)$row['annee_naissance_athlete'] : null,
        'categorie'        => $row['categorie_athlete'],
        'sexe'             => $row['sexe_athlete'],
        'nationalite'      => $row['nationalite_athlete'],
        'licence'          => $row['licence_athlete'],
        'nb_records'       => (int)$row['nb_records'],
        'club'             => $clubName,
        'url_detail'       => 'api/athlete.php?id=' . $row['athlete_id_externe'],
    ];
    if (isset($row['filtre_debut'])) {
        $athlete['filtre_debut'] = $row['filtre_debut'];
        $athlete['filtre_fin']   = $row['filtre_fin'];
    }
    $athletes[] = $athlete;
}

// Niveaux par athlete
$athIds = array_map(function($a) { return $a['id_athlete']; }, $athletes);
if (!empty($athIds)) {
    $idsList = implode(',', $athIds);
    $nRes = $conn->query("
        SELECT n.id_athlete, n.code_niveau
        FROM athlete_niveaux n
        WHERE n.id_athlete IN ($idsList) AND n.code_niveau IS NOT NULL AND n.code_niveau != ''
        ORDER BY n.id_athlete, n.points_niveau DESC
    ");
    $nivParAth = [];
    if ($nRes) while ($nr = $nRes->fetch_assoc()) {
        $aid = (int)$nr['id_athlete'];
        if (!isset($nivParAth[$aid])) $nivParAth[$aid] = [];
        if (!in_array($nr['code_niveau'], $nivParAth[$aid])) {
            $nivParAth[$aid][] = $nr['code_niveau'];
        }
    }
    foreach ($athletes as &$ath) {
        $ath['niveaux'] = $nivParAth[$ath['id_athlete']] ?? [];
    }
    unset($ath);

    // Top 5 records par athlete
    $recRes = $conn->query("
        SELECT ar.id_athlete, e.nom_epreuve, ar.performance_brut_record,
               (SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
                FROM athlete_resultats ares
                WHERE ares.id_athlete = ar.id_athlete AND ares.id_epreuve = ar.id_epreuve
                  AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
        FROM athlete_records ar
        JOIN epreuves e ON e.id_epreuve = ar.id_epreuve
        WHERE ar.id_athlete IN ($idsList)
        ORDER BY ar.id_athlete, e.nom_epreuve,
            CASE WHEN e.nom_epreuve REGEXP '(Poids|Disque|Javelot|Marteau|Hauteur|Perche|Longueur|Triple|Decathlon|Heptathlon|Pentathlon)'
                 THEN -CAST(ar.performance_record AS SIGNED)
                 ELSE ar.performance_record END ASC
    ");
    $recParAth = [];
    if ($recRes) while ($rr = $recRes->fetch_assoc()) {
        $aid = (int)$rr['id_athlete'];
        if (!isset($recParAth[$aid])) $recParAth[$aid] = [];
        if (count($recParAth[$aid]) < 5) {
            $nivList = array_filter(explode(',', $rr['niveaux'] ?? ''));
            $recParAth[$aid][] = [
                'epreuve' => $rr['nom_epreuve'],
                'performance' => $rr['performance_brut_record'],
                'niveaux' => array_values($nivList),
                'top_niveau' => highestNiveau(array_values($nivList)),
            ];
        }
    }
    foreach ($athletes as &$ath) {
        $ath['top_records'] = $recParAth[$ath['id_athlete']] ?? [];
    }
    unset($ath);
}

$totalPages = ceil($total / $limit);

$resp = array_merge([
    'success'    => true,
    'total'      => $total,
    'page'       => $page,
    'limit'      => $limit,
    'total_pages' => $totalPages,
    'athletes'   => $athletes,
], bkSlFields($_sl));
$json = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($resp);
