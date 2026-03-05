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

// ---- Rate limiting recherches : 50/jour par IP ----
$SEARCH_LIMIT = 50;
$_searchIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (strpos($_searchIp, ',') !== false) $_searchIp = trim(explode(',', $_searchIp)[0]);

// Whitelist : Google, Hostinger, localhost, users connectes
$_searchWhitelisted = false;
$_wlPrefixes = ['66.249.','66.102.','64.233.','72.14.','74.125.','209.85.','216.239.','35.','34.','153.92.','31.170.','185.201.','127.0.0.1','::1'];
foreach ($_wlPrefixes as $p) { if (strpos($_searchIp, $p) === 0) { $_searchWhitelisted = true; break; } }
if (!$_searchWhitelisted && (!empty($_COOKIE['bk_token']) || !empty($_COOKIE['bk_sa_token']))) $_searchWhitelisted = true;

if (!$_searchWhitelisted) {
    $limFile = __DIR__ . '/../logs/.search_limits.php';
    $today = date('Y-m-d');
    $limData = [];
    if (file_exists($limFile)) {
        $raw = file_get_contents($limFile);
        $limData = @json_decode(substr($raw, strpos($raw, "\n") + 1), true) ?: [];
    }
    // Reset si jour different
    if (($limData['_date'] ?? '') !== $today) $limData = ['_date' => $today];
    $cnt = (int)($limData[$_searchIp] ?? 0);
    if ($cnt >= $SEARCH_LIMIT) {
        jsonResponse([
            'success' => false,
            'error' => 'Vous avez atteint la limite de ' . $SEARCH_LIMIT . ' recherches pour aujourd\'hui. Revenez demain pour continuer vos recherches !',
            'limit_reached' => true,
            'limit' => $SEARCH_LIMIT,
            'reset' => 'minuit'
        ], 429);
    }
    // Incrementer
    $limData[$_searchIp] = $cnt + 1;
    file_put_contents($limFile, "<?php die(); ?>\n" . json_encode($limData, JSON_UNESCAPED_UNICODE));
}

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
$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset      = ($page - 1) * $limit;

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'search_' . md5($nom.'_'.$nom1.'_'.$nom2.'_'.$club.'_'.$categorie.'_'.$sexe.'_'.$nationalite.'_'.$epreuve.'_'.$ville.'_'.$competition.'_'.$medaille.'_'.$annee.'_'.$licence.'_'.$page.'_'.$limit);
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; $conn->close(); exit; }
}

// Construire la requete
$where = [];
$joins = [];

if ($nom !== '') {
    $nomEsc = $conn->real_escape_string($nom);
    $where[] = "a.nom_complet_athlete LIKE '%$nomEsc%'";
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

if ($epreuve !== '') {
    $epEsc = $conn->real_escape_string($epreuve);
    $joins[] = "JOIN athlete_records ar ON ar.id_athlete = a.id_athlete";
    $joins[] = "JOIN epreuves ep ON ep.id_epreuve = ar.id_epreuve AND ep.nom_epreuve LIKE '%$epEsc%'";
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

// Exclure les athletes des gros clubs (>5000 athletes) sauf si on filtre explicitement par club
if ($club === '') {
    $joins[] = "LEFT JOIN athlete_clubs ac_big ON ac_big.id_athlete = a.id_athlete
        AND ac_big.id_club IN (SELECT id_club FROM athlete_clubs GROUP BY id_club HAVING COUNT(DISTINCT id_athlete) > 5000)";
    $where[] = "ac_big.id_athlete IS NULL";
}

// Au moins un filtre requis
if (empty($where) && empty($joins)) {
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
            'page'        => 'Numero de page (defaut: 1)',
            'limit'       => 'Resultats par page (defaut: 50, max: 100)',
        ]
    ], 400);
}

$joinSql = implode("\n", $joins);
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

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
    $athlete = [
        'id_athlete'       => (int)$row['id_athlete'],
        'athlete_id'       => (int)$row['athlete_id_externe'],
        'nom_complet'      => $row['nom_complet_athlete'],
        'date_naissance'   => $row['date_naissance_athlete'],
        'annee_naissance'  => $row['annee_naissance_athlete'] ? (int)$row['annee_naissance_athlete'] : null,
        'categorie'        => $row['categorie_athlete'],
        'sexe'             => $row['sexe_athlete'],
        'nationalite'      => $row['nationalite_athlete'],
        'licence'          => $row['licence_athlete'],
        'nb_records'       => (int)$row['nb_records'],
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

$resp = [
    'success'    => true,
    'total'      => $total,
    'page'       => $page,
    'limit'      => $limit,
    'total_pages' => $totalPages,
    'athletes'   => $athletes,
];
$json = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($resp);
