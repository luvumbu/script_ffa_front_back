<?php
/**
 * api/composition.php — Meilleure composition sprint d'un club
 *
 * Params GET :
 *   club      (string, requis) : nom du club
 *   annees    (string)         : annees separees par virgules (ex: 2023,2024). Vide = toutes
 *   sexe      (string)         : M ou F. Vide = les deux
 *   exclure   (string)         : IDs externes d'athletes a exclure (virgules)
 *   inclure   (string)         : IDs externes exclusifs (si rempli, seuls ceux-la)
 *   cat       (string)         : filtre categorie (SE, ES, JU, etc.)
 *   top       (int)            : nombre max de sprinters par epreuve (defaut 8)
 *
 * Retourne : meilleurs sprinters par epreuve + composition 4x100m optimale
 */

require_once __DIR__ . '/config.php';

// --- Params ---
$clubNom = trim($_GET['club'] ?? '');
if ($clubNom === '') {
    jsonResponse(['success' => false, 'error' => 'Parametre ?club= requis'], 400);
}

$annees = [];
if (!empty($_GET['annees'])) {
    $annees = array_filter(array_map('intval', explode(',', $_GET['annees'])));
}

$sexeFilter = strtoupper(trim($_GET['sexe'] ?? ''));
if ($sexeFilter !== 'M' && $sexeFilter !== 'F') $sexeFilter = '';

$exclureIds = [];
if (!empty($_GET['exclure'])) {
    $exclureIds = array_filter(array_map('intval', explode(',', $_GET['exclure'])));
}

$inclureIds = [];
if (!empty($_GET['inclure'])) {
    $inclureIds = array_filter(array_map('intval', explode(',', $_GET['inclure'])));
}

$catFilter = $conn->real_escape_string(trim($_GET['cat'] ?? ''));
$topN = min(30, max(1, (int)($_GET['top'] ?? 8)));

// --- Trouver le club ---
$clubEsc = $conn->real_escape_string($clubNom);
$res = $conn->query("SELECT id_club, nom_club FROM clubs WHERE nom_club = '$clubEsc' LIMIT 1");
if (!$res || !$res->num_rows) {
    $res = $conn->query("SELECT id_club, nom_club FROM clubs WHERE nom_club LIKE '%$clubEsc%' ORDER BY LENGTH(nom_club) ASC LIMIT 1");
    if (!$res || !$res->num_rows) {
        jsonResponse(['success' => false, 'error' => 'Club non trouve'], 404);
    }
}
$club = $res->fetch_assoc();
$cid = (int)$club['id_club'];

// --- Epreuves sprint : trouver les IDs en BDD ---
$sprintPatterns = [
    '100m', '200m', '400m',
    '110m Haies%', '100m Haies%',
    '400m Haies%',
    '80m Haies%'
];
$epConds = [];
foreach ($sprintPatterns as $p) {
    if (strpos($p, '%') !== false) {
        $epConds[] = "nom_epreuve LIKE '" . $conn->real_escape_string($p) . "'";
    } else {
        $epConds[] = "nom_epreuve = '" . $conn->real_escape_string($p) . "'";
    }
}
$epRes = $conn->query("SELECT id_epreuve, nom_epreuve FROM epreuves WHERE " . implode(' OR ', $epConds));
$sprintEpreuves = []; // id => nom
$sprintIds = [];
if ($epRes) {
    while ($er = $epRes->fetch_assoc()) {
        $sprintEpreuves[(int)$er['id_epreuve']] = $er['nom_epreuve'];
        $sprintIds[] = (int)$er['id_epreuve'];
    }
}

if (empty($sprintIds)) {
    jsonResponse(['success' => false, 'error' => 'Aucune epreuve sprint trouvee en BDD'], 404);
}

$sprintIdsList = implode(',', $sprintIds);

// --- Filtres athletes ---
$athConds = [];
if ($sexeFilter !== '') {
    $athConds[] = "a.sexe_athlete = '$sexeFilter'";
}
if ($catFilter !== '') {
    $athConds[] = "a.categorie_athlete = '$catFilter'";
}
if (!empty($exclureIds)) {
    $athConds[] = "a.athlete_id_externe NOT IN (" . implode(',', $exclureIds) . ")";
}
if (!empty($inclureIds)) {
    $athConds[] = "a.athlete_id_externe IN (" . implode(',', $inclureIds) . ")";
}
$athWhere = !empty($athConds) ? ' AND ' . implode(' AND ', $athConds) : '';

// --- Filtre annees ---
$yearFilterRec = '';
$yearFilterProg = '';
$memberFilter = '';
if (!empty($annees)) {
    $yearsList = implode(',', $annees);
    $yearFilterRec = " AND YEAR(ar.date_record) IN ($yearsList)";
    $yearFilterProg = " AND ap.annee_progression IN ($yearsList)";
    $yearOrConds = [];
    foreach ($annees as $y) {
        $yearOrConds[] = "($y BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))";
    }
    $memberFilter = ' AND (' . implode(' OR ', $yearOrConds) . ')';
}

// Membership period check for records
$mcRec = "AND (ar.date_record IS NULL OR YEAR(ar.date_record) BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))";

// --- Requete UNION records + progressions (meme pattern que club_stats.php) ---
$sql = "
    SELECT
        a.id_athlete,
        a.athlete_id_externe,
        a.nom_complet_athlete,
        a.sexe_athlete,
        a.categorie_athlete,
        a.nationalite_athlete,
        e.id_epreuve,
        e.nom_epreuve,
        best.best_perf,
        best.best_annee
    FROM (
        SELECT
            u.id_athlete,
            u.id_epreuve,
            MIN(u.perf) as best_perf,
            MIN(u.annee) as best_annee
        FROM (
            SELECT ar.id_athlete, ar.id_epreuve,
                   ar.performance_record as perf,
                   YEAR(ar.date_record) as annee
            FROM athlete_records ar
            JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete AND ac.id_club = $cid $memberFilter $mcRec
            WHERE ar.id_epreuve IN ($sprintIdsList)
              AND ar.performance_record > 0
              $yearFilterRec

            UNION ALL

            SELECT ap.id_athlete, ap.id_epreuve,
                   ap.performance_progression as perf,
                   ap.annee_progression as annee
            FROM athlete_progressions ap
            JOIN athlete_clubs ac ON ac.id_athlete = ap.id_athlete AND ac.id_club = $cid $memberFilter
            WHERE ap.id_epreuve IN ($sprintIdsList)
              AND ap.performance_progression > 0
              AND (ap.annee_progression IS NULL OR ap.annee_progression = 0
                   OR ap.annee_progression BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))
              $yearFilterProg
        ) u
        GROUP BY u.id_athlete, u.id_epreuve
    ) best
    JOIN athletes a ON a.id_athlete = best.id_athlete
    JOIN epreuves e ON e.id_epreuve = best.id_epreuve
    WHERE 1=1 $athWhere
    ORDER BY e.nom_epreuve ASC, best.best_perf ASC
";

$res = $conn->query($sql);
if (!$res) {
    jsonResponse(['success' => false, 'error' => 'Erreur SQL', 'detail' => $conn->error], 500);
}

// --- Organiser par epreuve + sexe ---
$parEpreuve = [];
$tousAthletes = [];
while ($row = $res->fetch_assoc()) {
    $ep = $row['nom_epreuve'];
    $sex = $row['sexe_athlete'];
    if (!isset($parEpreuve[$ep])) $parEpreuve[$ep] = ['M' => [], 'F' => []];
    if (count($parEpreuve[$ep][$sex]) < $topN) {
        $parEpreuve[$ep][$sex][] = [
            'id_externe' => (int)$row['athlete_id_externe'],
            'nom' => $row['nom_complet_athlete'],
            'sexe' => $sex,
            'categorie' => $row['categorie_athlete'],
            'nationalite' => $row['nationalite_athlete'],
            'perf_int' => (int)$row['best_perf'],
            'annee' => (int)$row['best_annee']
        ];
    }
    $tousAthletes[(int)$row['athlete_id_externe']] = [
        'id_externe' => (int)$row['athlete_id_externe'],
        'nom' => $row['nom_complet_athlete'],
        'sexe' => $sex,
        'categorie' => $row['categorie_athlete'],
        'nationalite' => $row['nationalite_athlete']
    ];
}

// --- Chercher les perfs brutes ---
$brutMap = [];
$idAthletes = array_keys($tousAthletes);
if (!empty($idAthletes)) {
    $extIds = implode(',', $idAthletes);
    $mapRes = $conn->query("SELECT id_athlete, athlete_id_externe FROM athletes WHERE athlete_id_externe IN ($extIds)");
    $extToInt = [];
    if ($mapRes) while ($mr = $mapRes->fetch_assoc()) $extToInt[(int)$mr['athlete_id_externe']] = (int)$mr['id_athlete'];

    if (!empty($extToInt)) {
        $intIds = implode(',', array_values($extToInt));

        // Bruts depuis records
        $brutRes = $conn->query("
            SELECT ar.id_athlete, ar.id_epreuve, ar.performance_brut_record as brut, ar.performance_record as perf
            FROM athlete_records ar
            WHERE ar.id_athlete IN ($intIds) AND ar.id_epreuve IN ($sprintIdsList) AND ar.performance_record > 0
        ");
        if ($brutRes) {
            $intToExt = array_flip($extToInt);
            while ($br = $brutRes->fetch_assoc()) {
                $key = $intToExt[(int)$br['id_athlete']] . '-' . (int)$br['id_epreuve'];
                if (!isset($brutMap[$key]) || (int)$br['perf'] < ($brutMap[$key]['perf'] ?? 999999)) {
                    $brutMap[$key] = ['brut' => $br['brut'], 'vent' => '', 'perf' => (int)$br['perf']];
                }
            }
        }

        // Bruts depuis progressions
        $brutRes2 = $conn->query("
            SELECT ap.id_athlete, ap.id_epreuve, ap.performance_brut_progression as brut,
                   ap.performance_progression as perf, ap.vent_progression as vent
            FROM athlete_progressions ap
            WHERE ap.id_athlete IN ($intIds) AND ap.id_epreuve IN ($sprintIdsList) AND ap.performance_progression > 0
            ORDER BY ap.performance_progression ASC
        ");
        if ($brutRes2) {
            $intToExt = array_flip($extToInt);
            while ($br = $brutRes2->fetch_assoc()) {
                $key = $intToExt[(int)$br['id_athlete']] . '-' . (int)$br['id_epreuve'];
                if (!isset($brutMap[$key]) || (int)$br['perf'] < ($brutMap[$key]['perf'] ?? 999999)) {
                    $brutMap[$key] = ['brut' => $br['brut'], 'vent' => $br['vent'] ?? '', 'perf' => (int)$br['perf']];
                }
            }
        }
    }
}

// Injecter les bruts
foreach ($parEpreuve as $epNom => &$sexes) {
    $epId = array_search($epNom, $sprintEpreuves);
    if ($epId === false) continue;
    foreach ($sexes as $sex => &$athletes) {
        foreach ($athletes as &$ath) {
            $key = $ath['id_externe'] . '-' . $epId;
            $ath['perf_brut'] = $brutMap[$key]['brut'] ?? '';
            $ath['vent'] = $brutMap[$key]['vent'] ?? '';
        }
    }
}
unset($sexes, $athletes, $ath);

// --- Composition 4x100m optimale ---
function build4x100($parEpreuve, $sex) {
    $coureurs100 = [];
    foreach ($parEpreuve as $epNom => $sexes) {
        if ($epNom === '100m' && isset($sexes[$sex])) {
            foreach ($sexes[$sex] as $ath) {
                $coureurs100[$ath['id_externe']] = $ath;
            }
        }
    }

    $coureurs200 = [];
    foreach ($parEpreuve as $epNom => $sexes) {
        if ($epNom === '200m' && isset($sexes[$sex])) {
            foreach ($sexes[$sex] as $ath) {
                if (!isset($coureurs100[$ath['id_externe']])) {
                    $coureurs200[$ath['id_externe']] = $ath;
                }
            }
        }
    }

    $list100 = array_values($coureurs100);
    usort($list100, function($a, $b) { return $a['perf_int'] - $b['perf_int']; });

    $equipe = array_slice($list100, 0, 4);

    if (count($equipe) < 4) {
        $list200 = array_values($coureurs200);
        usort($list200, function($a, $b) { return $a['perf_int'] - $b['perf_int']; });
        $usedIds = array_map(function($a) { return $a['id_externe']; }, $equipe);
        foreach ($list200 as $ath) {
            if (count($equipe) >= 4) break;
            if (!in_array($ath['id_externe'], $usedIds)) {
                $ath['source'] = '200m';
                $equipe[] = $ath;
                $usedIds[] = $ath['id_externe'];
            }
        }
    }

    // Postes : 1=depart virage (2e meilleur), 2=virage (3e/4e), 3=ligne droite (1er), 4=finisseur
    $postes = [];
    if (count($equipe) >= 4) {
        $postes = [
            ['poste' => 1, 'role' => 'Depart (virage)', 'athlete' => $equipe[1]],
            ['poste' => 2, 'role' => 'Virage', 'athlete' => $equipe[2]],
            ['poste' => 3, 'role' => 'Ligne droite', 'athlete' => $equipe[0]],
            ['poste' => 4, 'role' => 'Finisseur', 'athlete' => $equipe[3]]
        ];
    } elseif (count($equipe) >= 1) {
        foreach ($equipe as $i => $ath) {
            $postes[] = ['poste' => $i + 1, 'role' => 'Poste ' . ($i + 1), 'athlete' => $ath];
        }
    }

    $usedIds = array_map(function($p) { return $p['athlete']['id_externe']; }, $postes);
    $remplacants = [];
    foreach ($list100 as $ath) {
        if (!in_array($ath['id_externe'], $usedIds) && count($remplacants) < 2) {
            $remplacants[] = $ath;
        }
    }

    $tempsEstime = 0;
    if (count($postes) === 4) {
        $somme = 0;
        foreach ($postes as $p) $somme += $p['athlete']['perf_int'];
        $tempsEstime = max(0, $somme - 250);
    }

    return [
        'equipe' => $postes,
        'remplacants' => $remplacants,
        'disponibles' => count($list100),
        'temps_estime_int' => $tempsEstime,
        'temps_estime_brut' => $tempsEstime > 0 ? perfIntToStr($tempsEstime) : ''
    ];
}

function perfIntToStr($perf) {
    if ($perf <= 0) return '';
    $cs = $perf % 100;
    $totalSec = (int)($perf / 100);
    $sec = $totalSec % 60;
    $min = (int)($totalSec / 60);
    if ($min > 0) return sprintf("%d'%02d''%02d", $min, $sec, $cs);
    return sprintf("%d''%02d", $sec, $cs);
}

$relay = [];
if ($sexeFilter === '' || $sexeFilter === 'M') $relay['M'] = build4x100($parEpreuve, 'M');
if ($sexeFilter === '' || $sexeFilter === 'F') $relay['F'] = build4x100($parEpreuve, 'F');

// --- Annees disponibles (depuis progressions du club via membership) ---
$anneesDisp = [];
$adRes = $conn->query("
    SELECT DISTINCT ap.annee_progression as annee
    FROM athlete_progressions ap
    JOIN athlete_clubs ac ON ac.id_athlete = ap.id_athlete AND ac.id_club = $cid
    WHERE ap.id_epreuve IN ($sprintIdsList)
      AND ap.performance_progression > 0
      AND ap.annee_progression > 0
      AND ap.annee_progression BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999)
    ORDER BY annee DESC
");
if ($adRes) while ($ar = $adRes->fetch_assoc()) $anneesDisp[] = (int)$ar['annee'];

// Completer avec annees des records si absentes
$adRes2 = $conn->query("
    SELECT DISTINCT YEAR(ar.date_record) as annee
    FROM athlete_records ar
    JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete AND ac.id_club = $cid
    WHERE ar.id_epreuve IN ($sprintIdsList)
      AND ar.performance_record > 0
      AND ar.date_record IS NOT NULL
      AND YEAR(ar.date_record) BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999)
    ORDER BY annee DESC
");
if ($adRes2) while ($ar2 = $adRes2->fetch_assoc()) {
    $y = (int)$ar2['annee'];
    if ($y > 0 && !in_array($y, $anneesDisp)) $anneesDisp[] = $y;
}
rsort($anneesDisp);

// --- Estimation 4x100m par annee ---
// Pour chaque annee dispo, on cherche les 4 meilleurs 100m de cette annee
$id100m = null;
foreach ($sprintEpreuves as $eid => $enom) {
    if ($enom === '100m') { $id100m = $eid; break; }
}

$estimParAnnee = [];
if ($id100m && !empty($anneesDisp)) {
    // Filtre athletes (exclure/inclure/sexe/cat)
    $athFilterEst = '';
    if ($sexeFilter !== '') $athFilterEst .= " AND a.sexe_athlete = '$sexeFilter'";
    if ($catFilter !== '') $athFilterEst .= " AND a.categorie_athlete = '$catFilter'";
    if (!empty($exclureIds)) $athFilterEst .= " AND a.athlete_id_externe NOT IN (" . implode(',', $exclureIds) . ")";
    if (!empty($inclureIds)) $athFilterEst .= " AND a.athlete_id_externe IN (" . implode(',', $inclureIds) . ")";

    foreach (['M', 'F'] as $sx) {
        if ($sexeFilter !== '' && $sexeFilter !== $sx) continue;
        $sexCond = "AND a.sexe_athlete = '$sx'";

        foreach ($anneesDisp as $yr) {
            // Meilleurs 100m de cette annee dans ce club
            $sqlYr = "
                SELECT a.athlete_id_externe, a.nom_complet_athlete,
                       MIN(u.perf) as best_perf
                FROM (
                    SELECT ap.id_athlete, ap.performance_progression as perf
                    FROM athlete_progressions ap
                    JOIN athlete_clubs ac ON ac.id_athlete = ap.id_athlete AND ac.id_club = $cid
                        AND ($yr BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))
                    WHERE ap.id_epreuve = $id100m AND ap.performance_progression > 0
                      AND ap.annee_progression = $yr
                      AND ap.annee_progression BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999)

                    UNION ALL

                    SELECT ar.id_athlete, ar.performance_record as perf
                    FROM athlete_records ar
                    JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete AND ac.id_club = $cid
                        AND ($yr BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))
                    WHERE ar.id_epreuve = $id100m AND ar.performance_record > 0
                      AND YEAR(ar.date_record) = $yr
                      AND YEAR(ar.date_record) BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999)
                ) u
                JOIN athletes a ON a.id_athlete = u.id_athlete
                WHERE 1=1 $sexCond $athFilterEst
                GROUP BY a.id_athlete
                ORDER BY best_perf ASC
                LIMIT 4
            ";
            $yrRes = $conn->query($sqlYr);
            if (!$yrRes) continue;

            $coureurs = [];
            while ($r = $yrRes->fetch_assoc()) {
                $coureurs[] = [
                    'id_externe' => (int)$r['athlete_id_externe'],
                    'nom' => $r['nom_complet_athlete'],
                    'perf_int' => (int)$r['best_perf'],
                    'perf_brut' => perfIntToStr((int)$r['best_perf'])
                ];
            }

            if (count($coureurs) >= 4) {
                $somme = 0;
                foreach ($coureurs as $c) $somme += $c['perf_int'];
                $tempsEst = max(0, $somme - 250);
                $estimParAnnee[] = [
                    'annee' => $yr,
                    'sexe' => $sx,
                    'coureurs' => $coureurs,
                    'temps_estime_int' => $tempsEst,
                    'temps_estime_brut' => perfIntToStr($tempsEst),
                    'nb_disponibles' => count($coureurs)
                ];
            } elseif (count($coureurs) >= 1) {
                $estimParAnnee[] = [
                    'annee' => $yr,
                    'sexe' => $sx,
                    'coureurs' => $coureurs,
                    'temps_estime_int' => 0,
                    'temps_estime_brut' => '',
                    'nb_disponibles' => count($coureurs)
                ];
            }
        }
    }
}

// --- Categories disponibles ---
$catsDisp = [];
$cdRes = $conn->query("
    SELECT DISTINCT a.categorie_athlete as cat
    FROM athletes a
    JOIN athlete_clubs ac ON ac.id_athlete = a.id_athlete AND ac.id_club = $cid
    WHERE a.categorie_athlete IS NOT NULL AND a.categorie_athlete != '' AND a.categorie_athlete != '-'
    ORDER BY a.categorie_athlete
");
if ($cdRes) while ($cr = $cdRes->fetch_assoc()) $catsDisp[] = $cr['cat'];

// --- Liste SPRINTERS du club (athletes ayant des perfs sprint) ---
$sprinters = [];
$laWhere = $sexeFilter ? "AND a.sexe_athlete = '$sexeFilter'" : "";
if ($catFilter !== '') $laWhere .= " AND a.categorie_athlete = '$catFilter'";
$spRes = $conn->query("
    SELECT a.athlete_id_externe, a.nom_complet_athlete, a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete,
           GROUP_CONCAT(DISTINCT e.nom_epreuve ORDER BY e.nom_epreuve SEPARATOR ', ') as epreuves,
           MIN(u.perf) as best_100m,
           MAX(u.annee) as derniere_annee
    FROM (
        SELECT ap.id_athlete, ap.id_epreuve, ap.performance_progression as perf, ap.annee_progression as annee
        FROM athlete_progressions ap
        JOIN athlete_clubs ac ON ac.id_athlete = ap.id_athlete AND ac.id_club = $cid
        WHERE ap.id_epreuve IN ($sprintIdsList) AND ap.performance_progression > 0
          AND ap.annee_progression BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999)

        UNION ALL

        SELECT ar.id_athlete, ar.id_epreuve, ar.performance_record as perf, YEAR(ar.date_record) as annee
        FROM athlete_records ar
        JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete AND ac.id_club = $cid
        WHERE ar.id_epreuve IN ($sprintIdsList) AND ar.performance_record > 0
          AND (ar.date_record IS NULL OR YEAR(ar.date_record) BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))
    ) u
    JOIN athletes a ON a.id_athlete = u.id_athlete
    JOIN epreuves e ON e.id_epreuve = u.id_epreuve
    WHERE 1=1 $laWhere
    GROUP BY a.id_athlete
    ORDER BY best_100m ASC, a.nom_complet_athlete ASC
    LIMIT 200
");
if ($spRes) while ($sp = $spRes->fetch_assoc()) {
    // best_100m : ne garder que si c'est du 100m
    $b100 = null;
    if (strpos($sp['epreuves'], '100m') !== false) {
        $b100 = (int)$sp['best_100m'];
    }
    $sprinters[] = [
        'id' => (int)$sp['athlete_id_externe'],
        'nom' => $sp['nom_complet_athlete'],
        'sexe' => $sp['sexe_athlete'],
        'cat' => $sp['categorie_athlete'],
        'nat' => $sp['nationalite_athlete'],
        'epreuves' => $sp['epreuves'],
        'derniere_annee' => (int)$sp['derniere_annee'],
        'best_100m' => $b100 ? perfIntToStr($b100) : null
    ];
}

// --- Formatage de sortie ---
$epreuvesOutput = [];
foreach ($parEpreuve as $epNom => $sexes) {
    $epId = array_search($epNom, $sprintEpreuves);
    $epData = ['nom' => $epNom, 'id' => $epId !== false ? $epId : 0];
    foreach (['M', 'F'] as $s) {
        if (!empty($sexes[$s])) $epData[$s] = $sexes[$s];
    }
    $epreuvesOutput[] = $epData;
}

// Tri logique
usort($epreuvesOutput, function($a, $b) {
    $ordre = ['100m' => 1, '200m' => 2, '400m' => 3];
    $oa = $ordre[$a['nom']] ?? 50;
    $ob = $ordre[$b['nom']] ?? 50;
    if (stripos($a['nom'], 'Haies') !== false) $oa += 10;
    if (stripos($b['nom'], 'Haies') !== false) $ob += 10;
    if ($oa === $ob) return strcmp($a['nom'], $b['nom']);
    return $oa - $ob;
});

jsonResponse([
    'success' => true,
    'club' => $club['nom_club'],
    'club_id' => $cid,
    'filtres' => [
        'annees' => $annees,
        'sexe' => $sexeFilter,
        'categorie' => $catFilter,
        'exclure' => $exclureIds,
        'inclure' => $inclureIds,
        'top' => $topN
    ],
    'epreuves' => $epreuvesOutput,
    'relay_4x100' => $relay,
    'estimation_par_annee' => $estimParAnnee,
    'annees_disponibles' => $anneesDisp,
    'categories_disponibles' => $catsDisp,
    'sprinters' => $sprinters
]);
