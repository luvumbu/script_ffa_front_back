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
$limit  = min(200, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;
$ordre  = $_GET['ordre'] ?? 'nom';
$niveau = isset($_GET['niveau']) ? trim($_GET['niveau']) : '';
$epreuve = isset($_GET['epreuve']) ? trim($_GET['epreuve']) : '';
$club    = isset($_GET['club']) ? trim($_GET['club']) : '';
// Mode strict : le niveau filtre s'applique SPECIFIQUEMENT a l'epreuve filtree
// (utilise athlete_niv_perfs au lieu du niveau global)
$niveauStrictEp = !empty($_GET['niveau_strict_ep']);
$sexeFilter = strtoupper(trim($_GET['sexe'] ?? ''));
if (!in_array($sexeFilter, ['M','F'], true)) $sexeFilter = '';
$recentYearMin = (int)($_GET['recent_year_min'] ?? 0);
// Annee exacte : si fourni, on filtre = $anneeExact au lieu de >= $recentYearMin
$anneeExact = (int)($_GET['annee_exact'] ?? 0);
// Niveau match : 'best' (defaut, niveau le plus haut) ou 'any' (a touche ce niveau dans son historique)
$niveauMatch = (isset($_GET['niveau_match']) && $_GET['niveau_match'] === 'any') ? 'any' : 'best';

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'liste_' . md5($page . '_' . $limit . '_' . $ordre . '_' . $niveau . '_' . $epreuve . '_' . $club . '_' . $sexeFilter . '_' . $recentYearMin . '_strict' . ($niveauStrictEp ? 1 : 0) . '_nm' . $niveauMatch . '_yE' . $anneeExact);
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
// TTL : 7 jours pour random, 7 jours en local (MySQL lent), 24h en prod
$cacheTTL = ($ordre === 'random' || (defined('BK_IS_LOCAL') && BK_IS_LOCAL)) ? 604800 : 86400;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; $conn->close(); exit; }
}

// Tri (utilise les colonnes du SELECT)
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

// Filtre niveaux : best level de l'athlete dans la liste demandee
$nivJoin = '';
$epJoin = '';

// MODE STRICT : niveau X SUR epreuve Y
// On utilise athlete_niv_perfs.code_perf_niveau (le niveau SPECIFIQUE a chaque perf/epreuve)
// au lieu de athlete_niveaux.code_niveau (le niveau GLOBAL/annuel)
if ($niveauStrictEp && $niveau !== '' && $epreuve !== '') {
    $allowedNiv = ['IA','IB','IE','IR','IR1','IR2','IR3','IR4','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
    $nivCodes = [];
    foreach (explode(',', $niveau) as $n) {
        $code = trim($n);
        if (in_array($code, $allowedNiv, true)) $nivCodes[] = "'" . $conn->real_escape_string($code) . "'";
    }
    // Match avec frontiere : exact OR suivi de ' (' (variantes hauteur Haies)
    // OR suivi de ' -' (variantes Salle/Piste Courte). Empeche "100m" d'attraper "1000m" ou "100m Haies"
    $epLikeConds = [];
    foreach (explode('|', $epreuve) as $epPart) {
        $epPart = trim($epPart);
        if ($epPart === '') continue;
        $epEsc = $conn->real_escape_string($epPart);
        $epLikeConds[] = "(ep_strict.nom_epreuve = '$epEsc' OR ep_strict.nom_epreuve LIKE '$epEsc (%' OR ep_strict.nom_epreuve LIKE '$epEsc -%')";
    }
    if (!empty($nivCodes) && !empty($epLikeConds)) {
        $nivList = implode(',', $nivCodes);
        $epLikeWhere = '(' . implode(' OR ', $epLikeConds) . ')';
        // Le filtre annee s'applique aussi au niveau strict
        if ($anneeExact > 0) {
            $yearNivClause = " AND an_strict.annee_niveau = $anneeExact";
        } else {
            $yearNivClause = ($recentYearMin > 0) ? " AND an_strict.annee_niveau >= $recentYearMin" : '';
        }
        // Filtre via athlete_niv_perfs.code_perf_niveau (niveau de la PERF specifique)
        $nivJoin = "INNER JOIN athlete_niveaux an_strict ON an_strict.id_athlete = a.id_athlete$yearNivClause
            INNER JOIN athlete_niv_perfs anp_strict ON anp_strict.id_niveau = an_strict.id_niveau AND anp_strict.code_perf_niveau IN ($nivList)
            INNER JOIN epreuves ep_strict ON ep_strict.id_epreuve = anp_strict.id_epreuve AND $epLikeWhere";
        // epJoin reste vide — le filtre epreuve est integre dans le strict join
    }
} else {
    // MODE NORMAL — filtres niveau et epreuve INDEPENDANTS

    // Filtre niveaux : 2 modes
    //   - 'best' (defaut) : best level global de l'athlete IN (filtres)
    //   - 'any'           : l'athlete a EU au moins un de ces niveaux dans son historique
    if ($niveau !== '') {
        $hierarchy = ['IA','IB','IE','IR','IR2','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
        $hierarchyList = "'" . implode("','", $hierarchy) . "'";
        $filterRanks = [];
        $nivCodesQuoted = [];
        foreach (explode(',', $niveau) as $n) {
            $code = trim($n);
            $idx = array_search($code, $hierarchy, true);
            if ($idx !== false) {
                $filterRanks[] = $idx + 1;
                $nivCodesQuoted[] = "'" . $conn->real_escape_string($code) . "'";
            }
        }
        if (!empty($filterRanks)) {
            if ($niveauMatch === 'any') {
                // Mode any : l'athlete a touche au moins un de ces niveaux
                $nivCodesList = implode(',', $nivCodesQuoted);
                $nivJoin = "INNER JOIN (
                    SELECT DISTINCT id_athlete
                    FROM athlete_niveaux
                    WHERE code_niveau IN ($nivCodesList)
                ) an_f ON an_f.id_athlete = a.id_athlete";
            } else {
                // Mode best : niveau le plus haut de l'athlete IN (filtres)
                $rankList = implode(',', $filterRanks);
                $nivJoin = "INNER JOIN (
                    SELECT id_athlete, MIN(FIELD(code_niveau, $hierarchyList)) as best_rank
                    FROM athlete_niveaux
                    WHERE FIELD(code_niveau, $hierarchyList) > 0
                    GROUP BY id_athlete
                ) an_f ON an_f.id_athlete = a.id_athlete AND an_f.best_rank IN ($rankList)";
            }
        }
    }

    // Filtre epreuves (noms separes par |)
    if ($epreuve !== '') {
        $epNames = array_map(function($e) use ($conn) { return "'" . $conn->real_escape_string(trim($e)) . "'"; }, explode('|', $epreuve));
        $epList = implode(',', $epNames);
        $epJoin = "INNER JOIN athlete_records ar_ep ON ar_ep.id_athlete = a.id_athlete INNER JOIN epreuves ep_f ON ep_f.id_epreuve = ar_ep.id_epreuve AND ep_f.nom_epreuve IN ($epList)";
    }
}

// Filtre club (recherche partielle pour gerer les variantes) — supporte plusieurs clubs separes par |
$clubJoin = '';
if ($club !== '') {
    $clubParts = array_filter(array_map('trim', explode('|', $club)), function($v){ return $v !== ''; });
    if (!empty($clubParts)) {
        $clubConds = [];
        foreach ($clubParts as $cp) {
            $clubConds[] = "cl_f.nom_club LIKE '%" . $conn->real_escape_string($cp) . "%'";
        }
        $clubJoin = "INNER JOIN athlete_clubs ac_f ON ac_f.id_athlete = a.id_athlete INNER JOIN clubs cl_f ON cl_f.id_club = ac_f.id_club AND (" . implode(' OR ', $clubConds) . ")";
    }
}

// Filtre sexe
$sxWhere = '';
if ($sexeFilter !== '') {
    $sxWhere = " AND a.sexe_athlete = '" . $conn->real_escape_string($sexeFilter) . "'";
}

$yearPrefilter = '';

// Filtre annee strict via EXISTS (utilise les index, beaucoup plus rapide que HAVING)
// Mode exact (annee_exact) prioritaire sur mode min (recent_year_min)
// Defini AVANT le total pour que le COUNT en tienne compte.
$yearWhere = '';
if ($anneeExact > 0) {
    $yearWhere = " AND (
        EXISTS (SELECT 1 FROM athlete_records ar2 WHERE ar2.id_athlete = a.id_athlete AND YEAR(ar2.date_record) = $anneeExact)
        OR EXISTS (SELECT 1 FROM athlete_resultats ares2 WHERE ares2.id_athlete = a.id_athlete AND YEAR(ares2.date_resultat) = $anneeExact)
        OR EXISTS (SELECT 1 FROM athlete_progressions aprog2 WHERE aprog2.id_athlete = a.id_athlete AND aprog2.annee_progression = $anneeExact)
    )";
} elseif ($recentYearMin > 0) {
    $yearWhere = " AND (
        EXISTS (SELECT 1 FROM athlete_records ar2 WHERE ar2.id_athlete = a.id_athlete AND YEAR(ar2.date_record) >= $recentYearMin)
        OR EXISTS (SELECT 1 FROM athlete_resultats ares2 WHERE ares2.id_athlete = a.id_athlete AND YEAR(ares2.date_resultat) >= $recentYearMin)
        OR EXISTS (SELECT 1 FROM athlete_progressions aprog2 WHERE aprog2.id_athlete = a.id_athlete AND aprog2.annee_progression >= $recentYearMin)
    )";
}

// Total
$totalSql = "SELECT COUNT(DISTINCT a.id_athlete) as c FROM athletes a $nivJoin $epJoin $clubJoin WHERE a.visible = 1$sxWhere$yearWhere";
$res = $conn->query($totalSql);
$total = $res ? (int)$res->fetch_assoc()['c'] : 0;
$totalPages = max(1, ceil($total / $limit));

// Sous-requete : annee la plus recente (utilisee pour affichage uniquement)
$latestYearSub = "(SELECT GREATEST(
                      COALESCE((SELECT MAX(YEAR(ar2.date_record)) FROM athlete_records ar2 WHERE ar2.id_athlete = a.id_athlete), 0),
                      COALESCE((SELECT MAX(YEAR(ares2.date_resultat)) FROM athlete_resultats ares2 WHERE ares2.id_athlete = a.id_athlete), 0),
                      COALESCE((SELECT MAX(aprog2.annee_progression) FROM athlete_progressions aprog2 WHERE aprog2.id_athlete = a.id_athlete), 0)
                  ))";

$priorityOrder = '';
$havingClause = '';

// Requete principale (comme avant — version qui marchait)
$sql = "SELECT DISTINCT a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete,
               a.date_naissance_athlete, a.categorie_athlete, a.sexe_athlete,
               a.nationalite_athlete, a.taille_cm_athlete, a.poids_kg_athlete,
               COUNT(DISTINCT ar.id_record) as nb_records,
               $latestYearSub as latest_year,
               (SELECT COUNT(*) FROM athlete_medailles am WHERE am.id_athlete = a.id_athlete) as nb_medailles,
               (SELECT COUNT(*) FROM athlete_podiums ap WHERE ap.id_athlete = a.id_athlete) as nb_podiums,
               (SELECT COUNT(*) FROM athlete_selections asel WHERE asel.id_athlete = a.id_athlete) as nb_selections,
               (SELECT COUNT(*) FROM athlete_resultats ares WHERE ares.id_athlete = a.id_athlete) as nb_resultats,
               (SELECT COUNT(*) FROM athlete_progressions aprog WHERE aprog.id_athlete = a.id_athlete) as nb_progressions
        FROM athletes a
        $nivJoin
        $epJoin
        $clubJoin
        LEFT JOIN athlete_records ar ON ar.id_athlete = a.id_athlete
        WHERE a.visible = 1$sxWhere$yearWhere
        GROUP BY a.id_athlete
        ORDER BY $priorityOrder$orderBy
        LIMIT $limit OFFSET $offset";

$res = $conn->query($sql);
$athletes = [];

if ($res) while ($row = $res->fetch_assoc()) {
    $latestY = (int)($row['latest_year'] ?? 0);
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
        'latest_year'      => $latestY > 0 ? $latestY : null,
        'is_recent'        => ($recentYearMin > 0 && $latestY >= $recentYearMin),
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
    // Hierarchie complete (du meilleur au pire). IR1-IR4 = sous-niveaux d'International Releve.
    $nivOrder = ['IA'=>1,'IB'=>2,'IE'=>3,'IR'=>4,'IR1'=>5,'IR2'=>6,'IR3'=>7,'IR4'=>8,'N1'=>9,'N2'=>10,'N3'=>11,'N4'=>12,'R1'=>13,'R2'=>14,'R3'=>15,'R4'=>16,'R5'=>17,'R6'=>18,'D1'=>19,'D2'=>20,'D3'=>21,'D4'=>22,'D5'=>23,'D6'=>24,'D7'=>25,'D8'=>26];
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
