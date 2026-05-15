<?php
/**
 * api/ville_stats.php — Stats detaillees d'une ville
 * GET params: nom (nom_ville)
 * Retourne: nb athletes, par sexe, par categorie, nationalites, top epreuves, top athletes, top clubs, niveaux
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/visibility.php';
$_isAdminInt = isAdminViewing() ? 1 : 0;

$nomVille = trim($_GET['nom'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = min(100, max(1, (int)($_GET['limit'] ?? 30)));
$offset   = ($page - 1) * $limit;
$nivFilter = trim($_GET['niv'] ?? '');
$natFilter = trim($_GET['nat'] ?? '');
$anneeFilter = trim($_GET['ans'] ?? '');

if ($nomVille === '') {
    jsonResponse(['success' => false, 'error' => 'Parametre nom requis'], 400);
}

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'villestats_' . md5($nomVille . '_' . $page . '_' . $limit . '_' . $nivFilter . '_' . $natFilter . '_' . $anneeFilter);
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; $conn->close(); exit; }
}

// Trouver la ville
$stmt = $conn->prepare("SELECT id_ville, nom_ville FROM villes WHERE nom_ville = ? LIMIT 1");
$stmt->bind_param("s", $nomVille);
$stmt->execute();
$ville = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ville) {
    jsonResponse(['success' => false, 'error' => 'Ville non trouvee'], 404);
}

$vid = (int) $ville['id_ville'];

// Filtre niveaux
$nivWhere = '';
$nivWhere2 = '';
$selectedNiv = [];
if ($nivFilter !== '') {
    $nivParts = array_map(function($n) use ($conn) { return "'" . $conn->real_escape_string(trim($n)) . "'"; }, explode(',', $nivFilter));
    $selectedNiv = array_map(function($n) { return trim($n); }, explode(',', $nivFilter));
    $nivWhere = " AND ar.niveau_resultat IN (" . implode(',', $nivParts) . ")";
    $nivWhere2 = " AND niveau_resultat IN (" . implode(',', $nivParts) . ")";
}

// Filtre nationalites
$natWhere = '';
$selectedNat = [];
if ($natFilter !== '') {
    $natParts = array_map(function($n) use ($conn) { return "'" . $conn->real_escape_string(trim($n)) . "'"; }, explode(',', $natFilter));
    $selectedNat = array_map(function($n) { return trim($n); }, explode(',', $natFilter));
    $natWhere = " AND a.nationalite_athlete IN (" . implode(',', $natParts) . ")";
}

// Filtre annees
$anneeWhere = '';
$anneeWhere2 = '';
$selectedAnnees = [];
if ($anneeFilter !== '') {
    $anneeParts = array_map(function($a) { return (int) trim($a); }, explode(',', $anneeFilter));
    $selectedAnnees = $anneeParts;
    $anneeWhere = " AND ar.annee_resultat IN (" . implode(',', $anneeParts) . ")";
    $anneeWhere2 = " AND annee_resultat IN (" . implode(',', $anneeParts) . ")";
}

// Nombre total d'athletes
$natJoin = $natFilter !== '' ? " JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})" : "";
$res = $conn->query("SELECT COUNT(DISTINCT ar.id_athlete) as c FROM athlete_resultats ar$natJoin WHERE ar.id_ville = $vid$nivWhere$natWhere$anneeWhere");
$totalAthletes = $res ? (int) $res->fetch_assoc()['c'] : 0;

// Nombre total de resultats
$res = $conn->query("SELECT COUNT(*) as c FROM athlete_resultats ar$natJoin WHERE ar.id_ville = $vid$nivWhere$natWhere$anneeWhere");
$totalResultats = $res ? (int) $res->fetch_assoc()['c'] : 0;

// Periode
$res = $conn->query("SELECT MIN(ar.annee_resultat) as d, MAX(ar.annee_resultat) as f FROM athlete_resultats ar$natJoin WHERE ar.id_ville = $vid AND ar.annee_resultat > 0$nivWhere$natWhere$anneeWhere");
$periode = $res ? $res->fetch_assoc() : ['d' => null, 'f' => null];

// Par sexe
$parSexe = [];
$res = $conn->query("
    SELECT a.sexe_athlete, COUNT(DISTINCT a.id_athlete) as c
    FROM athlete_resultats ar
    JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE ar.id_ville = $vid$nivWhere$natWhere$anneeWhere
    GROUP BY a.sexe_athlete ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $parSexe[$row['sexe_athlete'] ?: 'Inconnu'] = (int) $row['c'];
}

// Par categorie
$parCategorie = [];
$res = $conn->query("
    SELECT a.categorie_athlete, COUNT(DISTINCT a.id_athlete) as c
    FROM athlete_resultats ar
    JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE ar.id_ville = $vid AND a.categorie_athlete != ''$nivWhere$natWhere$anneeWhere
    GROUP BY a.categorie_athlete ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $parCategorie[$row['categorie_athlete']] = (int) $row['c'];
}

// Nationalites (pas filtré par annee pour garder la liste complète dans le sélecteur)
$nationalites = [];
$res = $conn->query("
    SELECT a.nationalite_athlete, COUNT(DISTINCT a.id_athlete) as c
    FROM athlete_resultats ar
    JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE ar.id_ville = $vid AND a.nationalite_athlete IS NOT NULL AND a.nationalite_athlete != '' AND a.nationalite_athlete != 'FRA'$nivWhere$anneeWhere
    GROUP BY a.nationalite_athlete ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $nationalites[$row['nationalite_athlete']] = (int) $row['c'];
}

// Total epreuves
$res = $conn->query("SELECT COUNT(DISTINCT ar.id_epreuve) as c FROM athlete_resultats ar$natJoin WHERE ar.id_ville = $vid$nivWhere$natWhere$anneeWhere");
$totalEpreuves = $res ? (int) $res->fetch_assoc()['c'] : 0;

// Epreuves paginées
$topEpreuves = [];
$res = $conn->query("
    SELECT e.id_epreuve, e.nom_epreuve, COUNT(*) as nb_resultats, COUNT(DISTINCT ar.id_athlete) as nb_athletes
    FROM athlete_resultats ar
    JOIN epreuves e ON e.id_epreuve = ar.id_epreuve
    " . ($natFilter !== '' ? "JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})" : "") . "
    WHERE ar.id_ville = $vid$nivWhere$natWhere$anneeWhere
    GROUP BY ar.id_epreuve ORDER BY nb_resultats DESC
    LIMIT $limit OFFSET $offset
");
$epIds = [];
if ($res) while ($row = $res->fetch_assoc()) {
    $topEpreuves[] = [
        'id_epreuve'   => (int) $row['id_epreuve'],
        'epreuve'      => $row['nom_epreuve'],
        'nb_resultats' => (int) $row['nb_resultats'],
        'nb_athletes'  => (int) $row['nb_athletes'],
        'top_niveaux'  => [],
    ];
    $epIds[] = (int) $row['id_epreuve'];
}

// Top 3 niveaux par epreuve
if (!empty($epIds)) {
    $epIdsList = implode(',', $epIds);
    $nRes = $conn->query("
        SELECT ar.id_epreuve, ar.niveau_resultat, COUNT(*) as cnt
        FROM athlete_resultats ar
        WHERE ar.id_ville = $vid AND ar.id_epreuve IN ($epIdsList)
          AND ar.niveau_resultat IS NOT NULL AND ar.niveau_resultat != ''
        GROUP BY ar.id_epreuve, ar.niveau_resultat
        ORDER BY ar.id_epreuve, cnt DESC
    ");
    $nivParEp = [];
    if ($nRes) while ($nr = $nRes->fetch_assoc()) {
        $eid = (int) $nr['id_epreuve'];
        if (!isset($nivParEp[$eid])) $nivParEp[$eid] = [];
        $nivParEp[$eid][] = ['niveau' => $nr['niveau_resultat'], 'cnt' => (int) $nr['cnt']];
    }
    foreach ($topEpreuves as &$ep) {
        $eid = $ep['id_epreuve'];
        if (!isset($nivParEp[$eid])) continue;
        $rows = array_slice($nivParEp[$eid], 0, 3);
        $totalN = 0;
        foreach ($rows as $r) $totalN += $r['cnt'];
        foreach ($rows as $r) {
            $ep['top_niveaux'][] = ['niveau' => $r['niveau'], 'pct' => $totalN > 0 ? round($r['cnt'] / $totalN * 100) : 0];
        }
    }
    unset($ep);
}

// Total clubs
$res = $conn->query("
    SELECT COUNT(*) as c FROM (
        SELECT ac.id_club, COUNT(DISTINCT ar.id_athlete) as nb
        FROM athlete_resultats ar
        JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete
        " . ($natFilter !== '' ? "JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})" : "") . "
        WHERE ar.id_ville = $vid$nivWhere$natWhere$anneeWhere
        GROUP BY ac.id_club HAVING nb < 5000
    ) sub
");
$totalClubs = $res ? (int) $res->fetch_assoc()['c'] : 0;

// Clubs paginés
$topClubs = [];
$res = $conn->query("
    SELECT cl.nom_club, COUNT(DISTINCT ar.id_athlete) as nb_athletes
    FROM athlete_resultats ar
    JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete
    JOIN clubs cl ON cl.id_club = ac.id_club
    " . ($natFilter !== '' ? "JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})" : "") . "
    WHERE ar.id_ville = $vid$nivWhere$natWhere$anneeWhere
    GROUP BY ac.id_club
    HAVING nb_athletes < 5000
    ORDER BY nb_athletes DESC
    LIMIT $limit OFFSET $offset
");
$clubIds = [];
if ($res) while ($row = $res->fetch_assoc()) {
    $topClubs[] = [
        'club'        => $row['nom_club'],
        'nb_athletes' => (int) $row['nb_athletes'],
        'top_niveaux' => [],
    ];
    $clubIds[$row['nom_club']] = count($topClubs) - 1;
}

// Top 3 niveaux par club
if (!empty($topClubs)) {
    $clubNames = array_map(function($c) use ($conn) { return "'" . $conn->real_escape_string($c['club']) . "'"; }, $topClubs);
    $nRes = $conn->query("
        SELECT cl.nom_club, ar.niveau_resultat, COUNT(*) as cnt
        FROM athlete_resultats ar
        JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete
        JOIN clubs cl ON cl.id_club = ac.id_club
        WHERE ar.id_ville = $vid AND cl.nom_club IN (" . implode(',', $clubNames) . ")
          AND ar.niveau_resultat IS NOT NULL AND ar.niveau_resultat != ''
        GROUP BY cl.nom_club, ar.niveau_resultat
        ORDER BY cl.nom_club, cnt DESC
    ");
    $nivParClub = [];
    if ($nRes) while ($nr = $nRes->fetch_assoc()) {
        $cn = $nr['nom_club'];
        if (!isset($nivParClub[$cn])) $nivParClub[$cn] = [];
        $nivParClub[$cn][] = ['niveau' => $nr['niveau_resultat'], 'cnt' => (int) $nr['cnt']];
    }
    foreach ($topClubs as &$cl) {
        $cn = $cl['club'];
        if (!isset($nivParClub[$cn])) continue;
        $rows = array_slice($nivParClub[$cn], 0, 3);
        $totalN = 0;
        foreach ($rows as $r) $totalN += $r['cnt'];
        foreach ($rows as $r) {
            $cl['top_niveaux'][] = ['niveau' => $r['niveau'], 'pct' => $totalN > 0 ? round($r['cnt'] / $totalN * 100) : 0];
        }
    }
    unset($cl);
}

// Athletes paginés
$topAthletes = [];
$res = $conn->query("
    SELECT a.athlete_id_externe, a.nom_complet_athlete, a.categorie_athlete, a.sexe_athlete,
           COUNT(*) as nb_resultats,
           MIN(ar.place_resultat) as best_place,
           GROUP_CONCAT(DISTINCT ar.niveau_resultat ORDER BY ar.niveau_resultat SEPARATOR ',') as niveaux
    FROM athlete_resultats ar
    JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE ar.id_ville = $vid$nivWhere$natWhere$anneeWhere
    GROUP BY a.id_athlete
    ORDER BY nb_resultats DESC
    LIMIT $limit OFFSET $offset
");
if ($res) while ($row = $res->fetch_assoc()) {
    $nivList = array_filter(explode(',', $row['niveaux'] ?? ''));
    $topAthletes[] = [
        'athlete_id'   => (int) $row['athlete_id_externe'],
        'nom_complet'  => $row['nom_complet_athlete'],
        'categorie'    => $row['categorie_athlete'],
        'sexe'         => $row['sexe_athlete'],
        'nb_resultats' => (int) $row['nb_resultats'],
        'best_place'   => $row['best_place'] ? (int) $row['best_place'] : null,
        'niveaux'      => array_values($nivList),
    ];
}

// Niveaux de competition
$niveaux = [];
$res = $conn->query("
    SELECT niveau_resultat, COUNT(*) as cnt
    FROM athlete_resultats
    WHERE id_ville = $vid AND niveau_resultat IS NOT NULL AND niveau_resultat != ''
    GROUP BY niveau_resultat
    ORDER BY cnt DESC
");
$totalNiv = 0;
$nivRows = [];
if ($res) while ($row = $res->fetch_assoc()) {
    $nivRows[] = $row;
    $totalNiv += (int) $row['cnt'];
}
foreach ($nivRows as $row) {
    $niveaux[] = [
        'niveau' => $row['niveau_resultat'],
        'count'  => (int) $row['cnt'],
        'pct'    => $totalNiv > 0 ? round((int) $row['cnt'] / $totalNiv * 100) : 0,
    ];
}

// Annees disponibles
$annees = [];
$res = $conn->query("
    SELECT DISTINCT annee_resultat as annee
    FROM athlete_resultats
    WHERE id_ville = $vid AND annee_resultat > 0$nivWhere2
    ORDER BY annee_resultat DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $annees[] = (int) $row['annee'];
}

// --- Données enrichies (médailles, podiums, records, sélections, progressions) ---

// Médailles dans cette ville
$medailles = ['or' => 0, 'argent' => 0, 'bronze' => 0];
$res = $conn->query("
    SELECT am.type_medaille, COUNT(*) as c
    FROM athlete_medailles am
    WHERE am.id_ville = $vid
    GROUP BY am.type_medaille
");
if ($res) while ($row = $res->fetch_assoc()) {
    $type = strtolower($row['type_medaille']);
    if (isset($medailles[$type])) $medailles[$type] = (int) $row['c'];
}
$totalMedailles = $medailles['or'] + $medailles['argent'] + $medailles['bronze'];

// Médailles détaillées (top 15)
$medaillesDetail = [];
$res = $conn->query("
    SELECT am.type_medaille, am.annee_medaille,
           a.nom_complet_athlete, a.athlete_id_externe,
           e.nom_epreuve, co.nom_competition
    FROM athlete_medailles am
    JOIN athletes a ON a.id_athlete = am.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    LEFT JOIN epreuves e ON e.id_epreuve = am.id_epreuve
    LEFT JOIN competitions co ON co.id_competition = am.id_competition
    WHERE am.id_ville = $vid
    ORDER BY am.annee_medaille DESC
    LIMIT 15
");
if ($res) while ($row = $res->fetch_assoc()) {
    $medaillesDetail[] = [
        'type' => $row['type_medaille'],
        'athlete' => $row['nom_complet_athlete'],
        'athlete_id' => (int) $row['athlete_id_externe'],
        'epreuve' => $row['nom_epreuve'],
        'competition' => $row['nom_competition'],
        'annee' => $row['annee_medaille'] ? (int) $row['annee_medaille'] : null,
    ];
}

// Top médaillés
$topMedailleAthletes = [];
$res = $conn->query("
    SELECT a.nom_complet_athlete, a.athlete_id_externe,
           SUM(am.type_medaille = 'or') as nb_or,
           SUM(am.type_medaille = 'argent') as nb_argent,
           SUM(am.type_medaille = 'bronze') as nb_bronze,
           COUNT(*) as total
    FROM athlete_medailles am
    JOIN athletes a ON a.id_athlete = am.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE am.id_ville = $vid
    GROUP BY am.id_athlete ORDER BY total DESC LIMIT 5
");
if ($res) while ($row = $res->fetch_assoc()) {
    $topMedailleAthletes[] = [
        'athlete' => $row['nom_complet_athlete'],
        'athlete_id' => (int) $row['athlete_id_externe'],
        'or' => (int) $row['nb_or'],
        'argent' => (int) $row['nb_argent'],
        'bronze' => (int) $row['nb_bronze'],
        'total' => (int) $row['total'],
    ];
}

// Podiums dans cette ville
$podiums = ['1er' => 0, '2e' => 0, '3e' => 0];
$totalPodiums = 0;
$res = $conn->query("
    SELECT p.rang_podium, COUNT(*) as c
    FROM athlete_podiums p
    WHERE p.id_ville = $vid
    GROUP BY p.rang_podium
");
if ($res) while ($row = $res->fetch_assoc()) {
    $rang = (int) $row['rang_podium'];
    if ($rang === 1) $podiums['1er'] = (int) $row['c'];
    elseif ($rang === 2) $podiums['2e'] = (int) $row['c'];
    elseif ($rang === 3) $podiums['3e'] = (int) $row['c'];
    $totalPodiums += (int) $row['c'];
}

// Podium niveaux de compétition
$podiumNiveaux = [];
$res = $conn->query("
    SELECT p.niveau_competition, COUNT(*) as c
    FROM athlete_podiums p
    WHERE p.id_ville = $vid AND p.niveau_competition IS NOT NULL AND p.niveau_competition != ''
    GROUP BY p.niveau_competition ORDER BY c DESC LIMIT 5
");
if ($res) while ($row = $res->fetch_assoc()) {
    $podiumNiveaux[] = ['niveau' => $row['niveau_competition'], 'count' => (int) $row['c']];
}

// Records dans cette ville — paginés
$totalRecords = 0;
$res = $conn->query("SELECT COUNT(*) as c FROM athlete_records WHERE id_ville = $vid");
$totalRecords = $res ? (int) $res->fetch_assoc()['c'] : 0;

$records = [];
$res = $conn->query("
    SELECT a.nom_complet_athlete, a.athlete_id_externe, a.categorie_athlete, a.sexe_athlete,
           e.nom_epreuve, r.performance_brut_record, r.date_record,
           (SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
            FROM athlete_resultats ares
            WHERE ares.id_athlete = r.id_athlete AND ares.id_epreuve = r.id_epreuve
              AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
    FROM athlete_records r
    JOIN athletes a ON a.id_athlete = r.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    JOIN epreuves e ON e.id_epreuve = r.id_epreuve
    WHERE r.id_ville = $vid
    ORDER BY e.nom_epreuve ASC,
        CASE WHEN e.nom_epreuve REGEXP '(Poids|Disque|Javelot|Marteau|Hauteur|Perche|Longueur|Triple|Decathlon|Heptathlon|Pentathlon)'
             THEN -CAST(r.performance_record AS SIGNED)
             ELSE r.performance_record END ASC
    LIMIT $limit OFFSET $offset
");
if ($res) while ($row = $res->fetch_assoc()) {
    $nivList = array_filter(explode(',', $row['niveaux'] ?? ''));
    $records[] = [
        'athlete' => $row['nom_complet_athlete'],
        'athlete_id' => (int) $row['athlete_id_externe'],
        'categorie' => $row['categorie_athlete'],
        'sexe' => $row['sexe_athlete'],
        'epreuve' => $row['nom_epreuve'],
        'performance' => $row['performance_brut_record'],
        'date' => $row['date_record'],
        'niveaux' => array_values($nivList),
    ];
}

// Sélections (athlètes ayant des résultats dans cette ville)
$selections = ['nb_selections' => 0, 'nb_athletes' => 0, 'nb_competitions' => 0];
$res = $conn->query("
    SELECT COUNT(*) as nb_sel, COUNT(DISTINCT s.id_athlete) as nb_ath, COUNT(DISTINCT s.id_competition) as nb_comp
    FROM athlete_selections s
    WHERE s.id_athlete IN (SELECT DISTINCT ar.id_athlete FROM athlete_resultats ar WHERE ar.id_ville = $vid)
");
if ($res) {
    $row = $res->fetch_assoc();
    $selections['nb_selections'] = (int) $row['nb_sel'];
    $selections['nb_athletes'] = (int) $row['nb_ath'];
    $selections['nb_competitions'] = (int) $row['nb_comp'];
}

// Progressions dans cette ville
$progressions = ['nb_progressions' => 0, 'nb_epreuves' => 0];
$res = $conn->query("
    SELECT COUNT(*) as c, COUNT(DISTINCT id_epreuve) as ep
    FROM athlete_progressions WHERE id_ville = $vid
");
if ($res) {
    $row = $res->fetch_assoc();
    $progressions['nb_progressions'] = (int) $row['c'];
    $progressions['nb_epreuves'] = (int) $row['ep'];
}

// Résultats par année (évolution)
$resultatsParAnnee = [];
$res = $conn->query("
    SELECT annee_resultat as annee, COUNT(*) as nb_resultats, COUNT(DISTINCT id_athlete) as nb_athletes
    FROM athlete_resultats
    WHERE id_ville = $vid AND annee_resultat > 0
    GROUP BY annee_resultat
    ORDER BY annee_resultat DESC
    LIMIT 15
");
if ($res) while ($row = $res->fetch_assoc()) {
    $resultatsParAnnee[] = [
        'annee' => (int) $row['annee'],
        'nb_resultats' => (int) $row['nb_resultats'],
        'nb_athletes' => (int) $row['nb_athletes'],
    ];
}

$resp = [
    'success'          => true,
    'ville'            => ['id_ville' => $vid, 'nom_ville' => $ville['nom_ville']],
    'total_athletes'   => $totalAthletes,
    'total_resultats'  => $totalResultats,
    'total_epreuves'   => $totalEpreuves,
    'total_clubs'      => $totalClubs,
    'page'             => $page,
    'limit'            => $limit,
    'pages_athletes'   => (int) ceil($totalAthletes / $limit),
    'pages_epreuves'   => (int) ceil($totalEpreuves / $limit),
    'pages_clubs'      => (int) ceil($totalClubs / $limit),
    'annee_debut'      => $periode['d'] ? (int) $periode['d'] : null,
    'annee_fin'        => $periode['f'] ? (int) $periode['f'] : null,
    'par_sexe'         => $parSexe,
    'par_categorie'    => $parCategorie,
    'nationalites'     => $nationalites,
    'top_epreuves'     => $topEpreuves,
    'top_clubs'        => $topClubs,
    'top_athletes'     => $topAthletes,
    'niveaux'          => $niveaux,
    'selected_niveaux' => $selectedNiv,
    'selected_nationalites' => $selectedNat,
    'selected_annees' => $selectedAnnees,
    'annees'           => $annees,
    'medailles'        => $medailles,
    'total_medailles'  => $totalMedailles,
    'medailles_detail' => $medaillesDetail,
    'top_medaille_athletes' => $topMedailleAthletes,
    'podiums'          => $podiums,
    'total_podiums'    => $totalPodiums,
    'podium_niveaux'   => $podiumNiveaux,
    'records'          => $records,
    'total_records'    => $totalRecords,
    'pages_records'    => (int) ceil($totalRecords / $limit),
    'selections'       => $selections,
    'progressions'     => $progressions,
    'resultats_par_annee' => $resultatsParAnnee,
];
// ============================================================
//  FILTRE ANNEES : garder uniquement annee en cours + precedente
// ============================================================
$__bkMinYear = (int)date('Y') - 1;
$__bkMaxYear = (int)date('Y');

$__bkYearKeys = ['annee','annee_resultat','annee_medaille','annee_podium','annee_progression','annee_niveau','annee_selection'];
$__bkFilterByYear = function($items) use ($__bkMinYear, $__bkMaxYear, $__bkYearKeys) {
    if (!is_array($items)) return $items;
    $out = [];
    foreach ($items as $it) {
        if (!is_array($it)) { $out[] = $it; continue; }
        $y = null;
        foreach ($__bkYearKeys as $k) {
            if (isset($it[$k]) && (int)$it[$k] > 0) { $y = (int)$it[$k]; break; }
        }
        if ($y === null && isset($it['date'])) {
            if (preg_match('/(\d{4})/', (string)$it['date'], $m)) $y = (int)$m[1];
        }
        if ($y === null || ($y >= $__bkMinYear && $y <= $__bkMaxYear)) $out[] = $it;
    }
    return $out;
};

// NB: 'progressions' et 'selections' sont des objets de stats (nb_progressions, nb_selections...)
// pas des listes d'items annuels — ne pas les filtrer (sinon array_values casse les cles)
foreach (['resultats_par_annee','medailles_detail','top_medaille_athletes','records'] as $__k) {
    if (isset($resp[$__k]) && is_array($resp[$__k])) {
        $resp[$__k] = array_values($__bkFilterByYear($resp[$__k]));
    }
}

if (isset($resp['annees']) && is_array($resp['annees'])) {
    $resp['annees'] = array_values(array_filter($resp['annees'], function($y) use ($__bkMinYear, $__bkMaxYear) {
        $y = (int)$y;
        return $y >= $__bkMinYear && $y <= $__bkMaxYear;
    }));
}

$json = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($resp);
