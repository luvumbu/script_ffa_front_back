<?php
/**
 * api/liste.php — Liste paginee de tous les athletes (enrichie)
 *
 * Usage :
 *   api/liste.php                     50 premiers athletes
 *   api/liste.php?page=2              Page 2
 *   api/liste.php?limit=100           100 par page (max 100)
 *   api/liste.php?ordre=date          Trier par date de naissance
 *   api/liste.php?ordre=id            Trier par ID athle.fr
 *   api/liste.php?ordre=nom           Trier par nom (defaut)
 *   api/liste.php?ordre=medailles     Trier par nombre de medailles
 *   api/liste.php?ordre=podiums       Trier par nombre de podiums
 *   api/liste.php?ordre=selections    Trier par nombre de selections
 *   api/liste.php?ordre=records       Trier par nombre de records
 */

require_once __DIR__ . '/config.php';

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;
$ordre  = $_GET['ordre'] ?? 'nom';

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'liste_' . md5($page . '_' . $limit . '_' . $ordre);
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
$cacheTTL = ($ordre === 'random') ? 604800 : 86400; // random = 7 jours, reste = 24h
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; $conn->close(); exit; }
}

// Tri
switch ($ordre) {
    case 'date':
        $orderBy = "a.date_naissance_athlete DESC";
        break;
    case 'id':
        $orderBy = "a.athlete_id_externe ASC";
        break;
    case 'recent':
        $orderBy = "a.id_athlete DESC";
        break;
    case 'medailles':
        $orderBy = "nb_medailles DESC, nb_records DESC";
        break;
    case 'podiums':
        $orderBy = "nb_podiums DESC, nb_records DESC";
        break;
    case 'selections':
        $orderBy = "nb_selections DESC, nb_records DESC";
        break;
    case 'records':
        $orderBy = "nb_records DESC";
        break;
    case 'random':
        $orderBy = "RAND()";
        break;
    default:
        $orderBy = "a.nom_complet_athlete ASC";
}

// Total
$res = $conn->query("SELECT COUNT(*) as c FROM athletes WHERE visible = 1");
$total = $res ? (int)$res->fetch_assoc()['c'] : 0;
$totalPages = ceil($total / $limit);

// Requete principale enrichie avec sous-requetes compteurs
$sql = "SELECT a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete,
               a.date_naissance_athlete, a.categorie_athlete, a.sexe_athlete,
               a.nationalite_athlete, a.taille_cm_athlete, a.poids_kg_athlete,
               COUNT(DISTINCT ar.id_record) as nb_records,
               (SELECT COUNT(*) FROM athlete_medailles am WHERE am.id_athlete = a.id_athlete) as nb_medailles,
               (SELECT COUNT(*) FROM athlete_podiums ap WHERE ap.id_athlete = a.id_athlete) as nb_podiums,
               (SELECT COUNT(*) FROM athlete_selections asel WHERE asel.id_athlete = a.id_athlete) as nb_selections,
               (SELECT COUNT(*) FROM athlete_resultats ares WHERE ares.id_athlete = a.id_athlete) as nb_resultats,
               (SELECT COUNT(*) FROM athlete_progressions aprog WHERE aprog.id_athlete = a.id_athlete) as nb_progressions
        FROM athletes a
        LEFT JOIN athlete_records ar ON ar.id_athlete = a.id_athlete
        WHERE a.visible = 1
        GROUP BY a.id_athlete
        ORDER BY $orderBy
        LIMIT $limit OFFSET $offset";

$res = $conn->query($sql);
$athletes = [];

if ($res) while ($row = $res->fetch_assoc()) {
    $athletes[] = [
        'id_athlete'       => (int)$row['id_athlete'],
        'athlete_id'       => (int)$row['athlete_id_externe'],
        'nom_complet'      => $row['nom_complet_athlete'],
        'date_naissance'   => $row['date_naissance_athlete'],
        'categorie'        => $row['categorie_athlete'],
        'sexe'             => $row['sexe_athlete'],
        'nationalite'      => $row['nationalite_athlete'],
        'taille_cm'        => $row['taille_cm_athlete'] ? (int)$row['taille_cm_athlete'] : null,
        'poids_kg'         => $row['poids_kg_athlete'] ? (float)$row['poids_kg_athlete'] : null,
        'nb_records'       => (int)$row['nb_records'],
        'nb_medailles'     => (int)$row['nb_medailles'],
        'nb_podiums'       => (int)$row['nb_podiums'],
        'nb_selections'    => (int)$row['nb_selections'],
        'nb_resultats'     => (int)$row['nb_resultats'],
        'nb_progressions'  => (int)$row['nb_progressions'],
        'niveaux'          => [],
        'medailles'        => ['or' => 0, 'argent' => 0, 'bronze' => 0],
        'club'             => null,
        'top_epreuve'      => null,
        'max_points'       => null,
        'meilleur_niveau'  => null,
    ];
}

// Batch enrichissements pour les athletes de cette page
if (!empty($athletes)) {
    $athIds = array_map(function($a) { return (int)$a['id_athlete']; }, $athletes);
    $idsList = implode(',', $athIds);

    // 1. Niveaux
    $nRes = $conn->query("
        SELECT n.id_athlete, n.code_niveau, n.points_niveau
        FROM athlete_niveaux n
        WHERE n.id_athlete IN ($idsList)
        ORDER BY n.id_athlete, n.code_niveau ASC
    ");
    $nivMap = [];
    $pointsMap = [];
    $bestNivMap = [];
    $nivOrder = ['IE'=>1,'IR'=>2,'N1'=>3,'N2'=>4,'N3'=>5,'N4'=>6,'R1'=>7,'R2'=>8,'R3'=>9,'R4'=>10,'R5'=>11,'R6'=>12,'D1'=>13,'D2'=>14,'D3'=>15,'D4'=>16,'D5'=>17,'D6'=>18,'D7'=>19,'D8'=>20];
    if ($nRes) while ($nr = $nRes->fetch_assoc()) {
        $aid = (int)$nr['id_athlete'];
        $nivMap[$aid][] = $nr['code_niveau'];
        $pts = $nr['points_niveau'] ? (int)$nr['points_niveau'] : 0;
        if ($pts > ($pointsMap[$aid] ?? 0)) $pointsMap[$aid] = $pts;
        $rank = $nivOrder[$nr['code_niveau']] ?? 99;
        if (!isset($bestNivMap[$aid]) || $rank < ($nivOrder[$bestNivMap[$aid]] ?? 99)) {
            $bestNivMap[$aid] = $nr['code_niveau'];
        }
    }

    // 2. Detail medailles (or/argent/bronze)
    $mRes = $conn->query("
        SELECT am.id_athlete, am.type_medaille, COUNT(*) as cnt
        FROM athlete_medailles am
        WHERE am.id_athlete IN ($idsList) AND am.type_medaille IN ('or','argent','bronze')
        GROUP BY am.id_athlete, am.type_medaille
    ");
    $medMap = [];
    if ($mRes) while ($mr = $mRes->fetch_assoc()) {
        $aid = (int)$mr['id_athlete'];
        if (!isset($medMap[$aid])) $medMap[$aid] = ['or'=>0,'argent'=>0,'bronze'=>0];
        $medMap[$aid][$mr['type_medaille']] = (int)$mr['cnt'];
    }

    // 3. Club actuel (le plus recent)
    $cRes = $conn->query("
        SELECT ac.id_athlete, c.nom_club, ac.annee_debut, ac.annee_fin
        FROM athlete_clubs ac
        JOIN clubs c ON c.id_club = ac.id_club
        WHERE ac.id_athlete IN ($idsList)
        ORDER BY ac.id_athlete, COALESCE(ac.annee_fin, 9999) DESC, ac.annee_debut DESC
    ");
    $clubMap = [];
    if ($cRes) while ($cr = $cRes->fetch_assoc()) {
        $aid = (int)$cr['id_athlete'];
        if (!isset($clubMap[$aid])) {
            $clubMap[$aid] = $cr['nom_club'];
        }
    }

    // 4. Top epreuve (celle avec le plus de records)
    $eRes = $conn->query("
        SELECT ar.id_athlete, e.nom_epreuve, COUNT(*) as cnt, MIN(ar.performance_brut_record) as best_perf
        FROM athlete_records ar
        JOIN epreuves e ON e.id_epreuve = ar.id_epreuve
        WHERE ar.id_athlete IN ($idsList)
        GROUP BY ar.id_athlete, ar.id_epreuve
        ORDER BY ar.id_athlete, cnt DESC
    ");
    $epMap = [];
    if ($eRes) while ($er = $eRes->fetch_assoc()) {
        $aid = (int)$er['id_athlete'];
        if (!isset($epMap[$aid])) {
            $epMap[$aid] = ['epreuve' => $er['nom_epreuve'], 'nb' => (int)$er['cnt'], 'best' => $er['best_perf']];
        }
    }

    // Assigner les donnees enrichies
    foreach ($athletes as &$a) {
        $aid = $a['id_athlete'];
        $a['niveaux'] = $nivMap[$aid] ?? [];
        $a['max_points'] = $pointsMap[$aid] ?? null;
        $a['meilleur_niveau'] = $bestNivMap[$aid] ?? null;
        $a['medailles'] = $medMap[$aid] ?? ['or'=>0,'argent'=>0,'bronze'=>0];
        $a['club'] = $clubMap[$aid] ?? null;
        $a['top_epreuve'] = $epMap[$aid] ?? null;
    }
    unset($a);
}

$resp = [
    'success'     => true,
    'total'       => $total,
    'page'        => $page,
    'limit'       => $limit,
    'total_pages' => $totalPages,
    'ordre'       => $ordre,
    'athletes'    => $athletes,
];
$json = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($resp);
