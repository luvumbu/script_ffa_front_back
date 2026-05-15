<?php
/**
 * api/epreuve_stats.php — Stats detaillees d'une epreuve
 * GET params: nom (nom_epreuve), page, limit
 * Retourne: records, medailles, podiums, top clubs, top villes, niveaux, selections, evolution
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/visibility.php';
$_isAdminInt = isAdminViewing() ? 1 : 0;

$nom = trim($_GET['nom'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

// Filtres optionnels
$filterSexe = trim($_GET['sexe'] ?? '');
$filterCat  = trim($_GET['categorie'] ?? '');
if ($filterSexe !== '' && !in_array($filterSexe, ['M','F'])) $filterSexe = '';
if ($filterCat !== '' && !preg_match('/^[A-Z0-9]{2,3}$/', $filterCat)) $filterCat = '';

// ---- Cache fichier (3 min) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'ep_' . md5($nom . '_' . $page . '_' . $limit . '_' . $filterSexe . '_' . $filterCat);
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) { // 24h
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; $conn->close(); exit; }
}

if ($nom === '') {
    jsonResponse(['success' => false, 'error' => 'Parametre nom requis'], 400);
}

// Trouver l'epreuve
$stmt = $conn->prepare("SELECT id_epreuve, nom_epreuve FROM epreuves WHERE nom_epreuve = ? LIMIT 1");
$stmt->bind_param("s", $nom);
$stmt->execute();
$epreuve = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$epreuve) {
    jsonResponse(['success' => false, 'error' => 'Epreuve non trouvee'], 404);
}

$eid = (int) $epreuve['id_epreuve'];

// Clause WHERE pour filtres sexe/categorie
$filterWhere = '';
if ($filterSexe !== '') $filterWhere .= " AND a.sexe_athlete = '" . $conn->real_escape_string($filterSexe) . "'";
if ($filterCat !== '')  $filterWhere .= " AND a.categorie_athlete = '" . $conn->real_escape_string($filterCat) . "'";

// Total athletes avec records (avec filtres)
if ($filterWhere !== '') {
    $res = $conn->query("SELECT COUNT(DISTINCT ar.id_athlete) as c FROM athlete_records ar JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt}) WHERE ar.id_epreuve = $eid $filterWhere");
    $totalAthletes = $res ? (int) $res->fetch_assoc()['c'] : 0;
    $res = $conn->query("SELECT COUNT(*) as c FROM athlete_records ar JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt}) WHERE ar.id_epreuve = $eid $filterWhere");
    $totalRecords = $res ? (int) $res->fetch_assoc()['c'] : 0;
} else {
    $res = $conn->query("SELECT COUNT(DISTINCT id_athlete) as c FROM athlete_records WHERE id_epreuve = $eid");
    $totalAthletes = $res ? (int) $res->fetch_assoc()['c'] : 0;
    $res = $conn->query("SELECT COUNT(*) as c FROM athlete_records WHERE id_epreuve = $eid");
    $totalRecords = $res ? (int) $res->fetch_assoc()['c'] : 0;
}

// Par sexe
$parSexe = [];
$res = $conn->query("
    SELECT a.sexe_athlete, COUNT(DISTINCT a.id_athlete) as c
    FROM athlete_records ar
    JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE ar.id_epreuve = $eid
    GROUP BY a.sexe_athlete ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $parSexe[$row['sexe_athlete'] ?: 'Inconnu'] = (int) $row['c'];
}

// Par categorie
$parCategorie = [];
$res = $conn->query("
    SELECT a.categorie_athlete, COUNT(DISTINCT a.id_athlete) as c
    FROM athlete_records ar
    JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE ar.id_epreuve = $eid AND a.categorie_athlete != ''
    GROUP BY a.categorie_athlete ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $parCategorie[$row['categorie_athlete']] = (int) $row['c'];
}

// Nationalites
$nationalites = [];
$res = $conn->query("
    SELECT a.nationalite_athlete, COUNT(DISTINCT a.id_athlete) as c
    FROM athlete_records ar
    JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE ar.id_epreuve = $eid AND a.nationalite_athlete IS NOT NULL AND a.nationalite_athlete != ''
    GROUP BY a.nationalite_athlete ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $nationalites[$row['nationalite_athlete']] = (int) $row['c'];
}

// Records pagines avec niveaux
$isDistanceEp = preg_match('/(poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon|pentathlon)/i', $epreuve['nom_epreuve']);
$perfSort = $isDistanceEp ? 'ar.performance_record DESC' : 'ar.performance_record ASC';
$records = [];
$res = $conn->query("
    SELECT a.nom_complet_athlete, a.athlete_id_externe, a.categorie_athlete, a.sexe_athlete, a.nationalite_athlete,
           ar.performance_brut_record, ar.date_record,
           c.nom_club,
           (SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
            FROM athlete_resultats ares
            WHERE ares.id_athlete = ar.id_athlete AND ares.id_epreuve = ar.id_epreuve
              AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
    FROM athlete_records ar
    JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    LEFT JOIN athlete_clubs ac ON ac.id_athlete = a.id_athlete
    LEFT JOIN clubs c ON c.id_club = ac.id_club
    WHERE ar.id_epreuve = $eid $filterWhere
    ORDER BY $perfSort
    LIMIT $limit OFFSET $offset
");
if ($res) while ($row = $res->fetch_assoc()) {
    $nivList = array_filter(explode(',', $row['niveaux'] ?? ''));
    $records[] = [
        'athlete'     => $row['nom_complet_athlete'],
        'athlete_id'  => (int) $row['athlete_id_externe'],
        'categorie'   => $row['categorie_athlete'],
        'sexe'        => $row['sexe_athlete'],
        'nationalite' => $row['nationalite_athlete'],
        'performance' => $row['performance_brut_record'],
        'date'        => $row['date_record'],
        'club'        => $row['nom_club'],
        'niveaux'     => array_values($nivList),
    ];
}

// Medailles pour cette epreuve
$medailles = ['or' => 0, 'argent' => 0, 'bronze' => 0];
$res = $conn->query("
    SELECT am.type_medaille, COUNT(*) as c
    FROM athlete_medailles am
    WHERE am.id_epreuve = $eid
    GROUP BY am.type_medaille
");
if ($res) while ($row = $res->fetch_assoc()) {
    $type = strtolower($row['type_medaille']);
    if (isset($medailles[$type])) $medailles[$type] = (int) $row['c'];
}
$totalMedailles = $medailles['or'] + $medailles['argent'] + $medailles['bronze'];

// Detail medailles (top 15)
$medaillesDetail = [];
$res = $conn->query("
    SELECT am.type_medaille, am.annee_medaille,
           a.nom_complet_athlete, a.athlete_id_externe,
           co.nom_competition, v.nom_ville
    FROM athlete_medailles am
    JOIN athletes a ON a.id_athlete = am.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    LEFT JOIN competitions co ON co.id_competition = am.id_competition
    LEFT JOIN villes v ON v.id_ville = am.id_ville
    WHERE am.id_epreuve = $eid
    ORDER BY am.annee_medaille DESC
    LIMIT 15
");
if ($res) while ($row = $res->fetch_assoc()) {
    $medaillesDetail[] = [
        'type' => $row['type_medaille'],
        'athlete' => $row['nom_complet_athlete'],
        'athlete_id' => (int) $row['athlete_id_externe'],
        'competition' => $row['nom_competition'],
        'lieu' => $row['nom_ville'],
        'annee' => $row['annee_medaille'] ? (int) $row['annee_medaille'] : null,
    ];
}

// Podiums pour cette epreuve
$podiums = ['1er' => 0, '2e' => 0, '3e' => 0];
$totalPodiums = 0;
$res = $conn->query("
    SELECT p.rang_podium, COUNT(*) as c
    FROM athlete_podiums p
    WHERE p.id_epreuve = $eid
    GROUP BY p.rang_podium
");
if ($res) while ($row = $res->fetch_assoc()) {
    $rang = (int) $row['rang_podium'];
    if ($rang === 1) $podiums['1er'] = (int) $row['c'];
    elseif ($rang === 2) $podiums['2e'] = (int) $row['c'];
    elseif ($rang === 3) $podiums['3e'] = (int) $row['c'];
    $totalPodiums += (int) $row['c'];
}

// Top clubs pour cette epreuve
$topClubs = [];
$res = $conn->query("
    SELECT cl.nom_club, COUNT(DISTINCT ar.id_athlete) as nb_athletes, COUNT(*) as nb_records
    FROM athlete_records ar
    JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete
    JOIN clubs cl ON cl.id_club = ac.id_club
    WHERE ar.id_epreuve = $eid
    GROUP BY ac.id_club
    HAVING nb_athletes < 5000
    ORDER BY nb_records DESC
    LIMIT 10
");
if ($res) while ($row = $res->fetch_assoc()) {
    $topClubs[] = [
        'club' => $row['nom_club'],
        'nb_athletes' => (int) $row['nb_athletes'],
        'nb_records' => (int) $row['nb_records'],
    ];
}

// Top villes pour cette epreuve
$topVilles = [];
$res = $conn->query("
    SELECT v.nom_ville, COUNT(*) as nb_records, COUNT(DISTINCT ar.id_athlete) as nb_athletes
    FROM athlete_records ar
    JOIN villes v ON v.id_ville = ar.id_ville
    WHERE ar.id_epreuve = $eid AND ar.id_ville IS NOT NULL
    GROUP BY ar.id_ville
    ORDER BY nb_records DESC
    LIMIT 10
");
if ($res) while ($row = $res->fetch_assoc()) {
    $topVilles[] = [
        'ville' => $row['nom_ville'],
        'nb_records' => (int) $row['nb_records'],
        'nb_athletes' => (int) $row['nb_athletes'],
    ];
}

// Niveaux de competition
$niveauxResultats = [];
$res = $conn->query("
    SELECT ares.niveau_resultat, COUNT(*) as cnt
    FROM athlete_resultats ares
    WHERE ares.id_epreuve = $eid AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != ''
    GROUP BY ares.niveau_resultat ORDER BY cnt DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $niveauxResultats[] = ['niveau' => $row['niveau_resultat'], 'count' => (int) $row['cnt']];
}

// Selections nationales pour cette epreuve
$selections = ['nb_selections' => 0, 'nb_athletes' => 0];
$res = $conn->query("
    SELECT COUNT(*) as nb_sel, COUNT(DISTINCT s.id_athlete) as nb_ath
    FROM athlete_selections s
    WHERE s.id_epreuve = $eid
");
if ($res) {
    $row = $res->fetch_assoc();
    $selections['nb_selections'] = (int) $row['nb_sel'];
    $selections['nb_athletes'] = (int) $row['nb_ath'];
}

// Resultats par annee
$resultatsParAnnee = [];
$res = $conn->query("
    SELECT ares.annee_resultat as annee, COUNT(*) as nb_resultats, COUNT(DISTINCT ares.id_athlete) as nb_athletes
    FROM athlete_resultats ares
    WHERE ares.id_epreuve = $eid AND ares.annee_resultat > 0
    GROUP BY ares.annee_resultat ORDER BY ares.annee_resultat DESC
    LIMIT 15
");
if ($res) while ($row = $res->fetch_assoc()) {
    $resultatsParAnnee[] = [
        'annee' => (int) $row['annee'],
        'nb_resultats' => (int) $row['nb_resultats'],
        'nb_athletes' => (int) $row['nb_athletes'],
    ];
}

// Progressions
$progressions = ['nb_progressions' => 0, 'nb_athletes' => 0];
$res = $conn->query("
    SELECT COUNT(*) as c, COUNT(DISTINCT id_athlete) as nb_ath
    FROM athlete_progressions WHERE id_epreuve = $eid
");
if ($res) {
    $row = $res->fetch_assoc();
    $progressions['nb_progressions'] = (int) $row['c'];
    $progressions['nb_athletes'] = (int) $row['nb_ath'];
}

// Periode
$res = $conn->query("SELECT MIN(YEAR(date_record)) as d, MAX(YEAR(date_record)) as f FROM athlete_records WHERE id_epreuve = $eid AND date_record IS NOT NULL");
$periode = $res ? $res->fetch_assoc() : ['d' => null, 'f' => null];

$resp = [
    'success'          => true,
    'epreuve'          => $epreuve['nom_epreuve'],
    'id_epreuve'       => $eid,
    'total_athletes'   => $totalAthletes,
    'total_records'    => $totalRecords,
    'page'             => $page,
    'limit'            => $limit,
    'total_pages'      => (int) ceil($totalRecords / $limit),
    'annee_debut'      => $periode['d'] ? (int) $periode['d'] : null,
    'annee_fin'        => $periode['f'] ? (int) $periode['f'] : null,
    'par_sexe'         => $parSexe,
    'par_categorie'    => $parCategorie,
    'nationalites'     => $nationalites,
    'records'          => $records,
    'medailles'        => $medailles,
    'total_medailles'  => $totalMedailles,
    'medailles_detail' => $medaillesDetail,
    'podiums'          => $podiums,
    'total_podiums'    => $totalPodiums,
    'top_clubs'        => $topClubs,
    'top_villes'       => $topVilles,
    'niveaux_resultats' => $niveauxResultats,
    'selections'       => $selections,
    'progressions'     => $progressions,
    'resultats_par_annee' => $resultatsParAnnee,
];
$json = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($resp);
