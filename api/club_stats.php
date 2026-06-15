<?php
/**
 * api/club_stats.php — Stats detaillees d'un club
 * GET params: id (id_club) ou nom (nom_club), annee (optionnel)
 * Retourne: nb athletes, par sexe, par categorie, medailles, top epreuves, top athletes, annees_disponibles
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/visibility.php';
// Filtre visibilite : si admin, bypass. Sinon, masque les athletes visible=0
$_isAdminInt = isAdminViewing() ? 1 : 0;
$_visJoin = " AND (a.visible = 1 OR 1=$_isAdminInt)"; // a inserer apres chaque JOIN athletes a ON ...

$idClub  = intval($_GET['id'] ?? 0);
$nomClub = trim($_GET['nom'] ?? '');
// Filtre annee(s) : accepte une seule annee ou une liste "2021,2023,2024".
// Vide = toutes les annees.
$anneeList = [];
foreach (explode(',', (string)($_GET['annee'] ?? '')) as $__y) {
    $__yi = intval(trim($__y));
    if ($__yi > 0) $anneeList[] = $__yi;
}
$anneeList = array_values(array_unique($anneeList));
sort($anneeList);
$hasYear = !empty($anneeList);
$yearIn  = implode(',', $anneeList);           // sur de l'injection (uniquement des entiers)
$anneeKey = $hasYear ? $yearIn : '0';          // pour la cle de cache
$annee   = $hasYear ? $anneeList[0] : 0;       // compat : 1ere annee si besoin ponctuel

// Helper : retourne " AND <col> IN (y1,y2)" ou "" si aucune annee selectionnee.
function _bkYearIn($col, $list) {
    return empty($list) ? '' : " AND $col IN (" . implode(',', $list) . ")";
}
$recPage = max(1, (int)($_GET['rp'] ?? 1));
$recLimit = 10;
$recOffset = ($recPage - 1) * $recLimit;
$epPage = max(1, (int)($_GET['ep'] ?? 1));
$epLimit = 50;
$epOffset = ($epPage - 1) * $epLimit;
$perfPage = max(1, (int)($_GET['pp'] ?? 1));
$perfLimit = 20;
$perfOffset = ($perfPage - 1) * $perfLimit;
$perso = isset($_GET['perso']) ? 1 : 0;
$perfMode = trim($_GET['pm'] ?? 'all'); // 'all' = tous resultats, 'perso' = records personnels
$natDetail = trim($_GET['nat_detail'] ?? '');
$filterNat  = trim($_GET['nationalite'] ?? '');
$filterSexe = trim($_GET['sexe'] ?? '');
$filterCat  = trim($_GET['categorie'] ?? '');
// Filtre par annee(s) de naissance (liste separee par virgules)
$filterNaissance = trim($_GET['annee_naissance'] ?? '');
$naissanceList = [];
if ($filterNaissance !== '') {
    foreach (explode(',', $filterNaissance) as $_y) {
        $_y = (int) trim($_y);
        if ($_y > 1900 && $_y < 2100) $naissanceList[] = $_y;
    }
}
$naissanceList = array_values(array_unique($naissanceList));
$naissanceIn = implode(',', $naissanceList);
$filterNaissance = $naissanceIn; // normalise (retire les valeurs invalides)

if ($idClub <= 0 && $nomClub === '') {
    jsonResponse(['success' => false, 'error' => 'Parametre id ou nom requis'], 400);
}

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'clubstats_' . md5($idClub . '_' . $nomClub . '_' . $anneeKey . '_' . $epPage . '_' . $recPage . '_' . $perfPage . '_' . $perfMode . '_' . $perso . '_' . $natDetail . '_' . $filterNat . '_' . $filterSexe . '_' . $filterCat . '_' . $filterNaissance);
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
$noCache = isset($_GET['nocache']);
if (!$noCache && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; $conn->close(); exit; }
}

// Trouver le club
if ($idClub > 0) {
    $stmt = $conn->prepare("SELECT id_club, nom_club FROM clubs WHERE id_club = ?");
    $stmt->bind_param("i", $idClub);
} else {
    $stmt = $conn->prepare("SELECT id_club, nom_club FROM clubs WHERE nom_club = ? LIMIT 1");
    $stmt->bind_param("s", $nomClub);
}
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$club) {
    jsonResponse(['success' => false, 'error' => 'Club non trouve'], 404);
}

$cid = (int) $club['id_club'];

// Filtre athletes (nationalite, sexe, categorie, annee de naissance) — sous-requete universelle
$athFilter = '';
$athFilterProg = '';
$afConds = [];
if ($filterNat !== '')  $afConds[] = "_af.nationalite_athlete = '" . $conn->real_escape_string(strtoupper($filterNat)) . "'";
if ($filterSexe !== '') $afConds[] = "_af.sexe_athlete = '" . $conn->real_escape_string(strtoupper($filterSexe)) . "'";
if ($filterCat !== '')  $afConds[] = "_af.categorie_athlete = '" . $conn->real_escape_string($filterCat) . "'";
// Conditions hors annee de naissance — sert a lister TOUTES les annees disponibles du club
$afCondsNoNaiss = $afConds;
if (!empty($naissanceList)) $afConds[] = "_af.annee_naissance_athlete IN ($naissanceIn)";
if (!empty($afConds)) {
    $athFilter     = " AND ac.id_athlete IN (SELECT _af.id_athlete FROM athletes _af WHERE " . implode(' AND ', $afConds) . ")";
    $athFilterProg = " AND ap.id_athlete IN (SELECT _af.id_athlete FROM athletes _af WHERE " . implode(' AND ', $afConds) . ")";
}

// Filtre annee pour progressions
$progFilterYear = _bkYearIn('ap.annee_progression', $anneeList);

// Filtres d'appartenance : ne compter que les perfs réalisées pendant la période de membership
$mcRec  = "AND (ar.date_record IS NULL OR YEAR(ar.date_record) BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))";
$mcRes  = "AND (ares.annee_resultat IS NULL OR ares.annee_resultat = 0 OR ares.annee_resultat BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))";
$mcMed  = "AND (am.annee_medaille IS NULL OR am.annee_medaille = 0 OR am.annee_medaille BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))";
$mcPod  = "AND (ap.annee_podium IS NULL OR ap.annee_podium = 0 OR ap.annee_podium BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))";
$mcProg = "AND (ap.annee_progression IS NULL OR ap.annee_progression = 0 OR ap.annee_progression BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))";
$mcNiv  = "AND (n.annee_niveau IS NULL OR n.annee_niveau = 0 OR n.annee_niveau BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))";
$mcSel  = "AND (s.date_selection IS NULL OR YEAR(s.date_selection) BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))";

// Mode perso : pas de filtre membership sur les records
$recMcRec = $perso ? '' : $mcRec;

// Hiérarchie des niveaux : retourner le plus haut uniquement
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

// Helper: mettre a jour le meilleur record par epreuve/sexe
function _updateBestBySex(&$epBestBySex, $row) {
    $eid = (int)$row['id_epreuve'];
    $sex = $row['sexe_athlete'] ?: '?';
    $perfInt = (int)$row['perf_int'];
    if ($perfInt <= 0) return; // ignorer les performances invalides (conversion echouee)
    $isDistance = preg_match('/(poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon|pentathlon)/i', $row['nom_epreuve']);
    $recDate = $row['perf_date'] ? substr($row['perf_date'], 0, 4) : null;
    $entry = [
        'perf'       => $row['perf_brut'],
        'perf_int'   => $perfInt,
        'athlete'    => $row['nom_complet_athlete'],
        'athlete_id' => $row['athlete_id_externe'] ? (int)$row['athlete_id_externe'] : null,
        'annee'      => $recDate,
    ];
    if (!isset($epBestBySex[$eid][$sex])) {
        $epBestBySex[$eid][$sex] = $entry;
    } else {
        $cur = $epBestBySex[$eid][$sex]['perf_int'];
        $better = $isDistance ? ($perfInt > $cur) : ($perfInt < $cur);
        if ($better) {
            $epBestBySex[$eid][$sex] = $entry;
        }
    }
}

// Filtre athletes actifs sur une annee donnee (sous-requete reutilisable)
$activeFilter = '';
if ($hasYear) {
    $activeFilter = " AND ac.id_athlete IN (
        SELECT ares.id_athlete FROM athlete_resultats ares WHERE ares.annee_resultat IN ($yearIn)
        UNION
        SELECT ap.id_athlete FROM athlete_progressions ap WHERE ap.annee_progression IN ($yearIn)
    )";
}

// Nombre total d'athletes
$res = $conn->query("SELECT COUNT(DISTINCT ac.id_athlete) as c FROM athlete_clubs ac WHERE ac.id_club = $cid $athFilter $activeFilter");
$totalAthletes = $res ? (int) $res->fetch_assoc()['c'] : 0;

// Par sexe
$parSexe = [];
$res = $conn->query("
    SELECT a.sexe_athlete, COUNT(DISTINCT a.id_athlete) as c
    FROM athlete_clubs ac
    JOIN athletes a ON a.id_athlete = ac.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE ac.id_club = $cid $athFilter $activeFilter
    GROUP BY a.sexe_athlete ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $parSexe[$row['sexe_athlete'] ?: 'Inconnu'] = (int) $row['c'];
}

// Par categorie
$parCategorie = [];
$res = $conn->query("
    SELECT a.categorie_athlete, COUNT(DISTINCT a.id_athlete) as c
    FROM athlete_clubs ac
    JOIN athletes a ON a.id_athlete = ac.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE ac.id_club = $cid AND a.categorie_athlete != '' $activeFilter
    GROUP BY a.categorie_athlete ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $parCategorie[$row['categorie_athlete']] = (int) $row['c'];
}

// Medailles des athletes du club
$medailles = ['or' => 0, 'argent' => 0, 'bronze' => 0];
$medailleFilter = _bkYearIn('am.annee_medaille', $anneeList);
$res = $conn->query("
    SELECT am.type_medaille, COUNT(*) as c
    FROM athlete_medailles am
    JOIN athlete_clubs ac ON ac.id_athlete = am.id_athlete AND ac.id_club = $cid $athFilter $mcMed
    WHERE am.type_medaille IN ('or','argent','bronze') $medailleFilter
    GROUP BY am.type_medaille
");
if ($res) while ($row = $res->fetch_assoc()) {
    $medailles[$row['type_medaille']] = (int) $row['c'];
}

// Nationalites des athletes du club — UNIQUEMENT les etrangers (on exclut FRA).
// Si le club n'a aucun athlete etranger, la liste est vide => les sections
// "Nationalites" sont automatiquement masquees cote affichage (gardes !empty).
$nationalites = [];
$res = $conn->query("
    SELECT a.nationalite_athlete, COUNT(DISTINCT a.id_athlete) as c
    FROM athlete_clubs ac
    JOIN athletes a ON a.id_athlete = ac.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE ac.id_club = $cid AND a.nationalite_athlete IS NOT NULL AND a.nationalite_athlete != ''
      AND a.nationalite_athlete != 'FRA' $activeFilter
    GROUP BY a.nationalite_athlete ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $nationalites[$row['nationalite_athlete']] = (int) $row['c'];
}

// Classification des épreuves par discipline
function getEpreuveDiscipline($nom) {
    if (preg_match('/haies/i', $nom)) return ['disc' => 'Haies', 'ord' => 2, 'clr' => '#f97316'];
    if (preg_match('/steeple/i', $nom)) return ['disc' => 'Steeple', 'ord' => 4, 'clr' => '#84cc16'];
    if (preg_match('/marche/i', $nom)) return ['disc' => 'Marche', 'ord' => 6, 'clr' => '#14b8a6'];
    if (preg_match('/relais|^\d+\s*x/i', $nom)) return ['disc' => 'Relais', 'ord' => 10, 'clr' => '#ec4899'];
    if (preg_match('/decathlon|heptathlon|pentathlon|triathlon|tetrathlon|octathlon/i', $nom)) return ['disc' => 'Combinés', 'ord' => 9, 'clr' => '#a855f7'];
    if (preg_match('/poids|disque|javelot|marteau/i', $nom)) return ['disc' => 'Lancers', 'ord' => 8, 'clr' => '#6366f1'];
    if (preg_match('/hauteur|longueur|triple|perche/i', $nom)) return ['disc' => 'Sauts', 'ord' => 7, 'clr' => '#3b82f6'];
    if (preg_match('/marathon|cross|semi|trail|route|heure/i', $nom)) return ['disc' => 'Fond', 'ord' => 5, 'clr' => '#22c55e'];
    if (preg_match('/^([\d\s]+?)\s*(m|km)/i', $nom, $m)) {
        $dist = (int) str_replace(' ', '', $m[1]);
        if (strtolower($m[2]) === 'km') $dist *= 1000;
        if ($dist >= 3000) return ['disc' => 'Fond', 'ord' => 5, 'clr' => '#22c55e'];
        if ($dist >= 800) return ['disc' => 'Demi-fond', 'ord' => 3, 'clr' => '#f59e0b'];
        return ['disc' => 'Sprint', 'ord' => 1, 'clr' => '#ef4444'];
    }
    return ['disc' => 'Autres', 'ord' => 11, 'clr' => '#6b7280'];
}

// Toutes les epreuves (avec nb athletes + nb records + meilleur record du club) — paginé
// Source : UNION athlete_records + athlete_progressions pour cohérence complète
$recordFilter = _bkYearIn('YEAR(ar.date_record)', $anneeList);

// Sous-requete UNION : tous les (athlete, epreuve) du club depuis records + progressions
$epUnionSub = "
    SELECT DISTINCT sub.id_epreuve, sub.id_athlete FROM (
        SELECT ar.id_epreuve, ar.id_athlete
        FROM athlete_records ar
        JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete AND ac.id_club = $cid $athFilter $recMcRec
        WHERE 1=1 $recordFilter
";
if (!$perso) {
    $epUnionSub .= "
        UNION ALL
        SELECT ap.id_epreuve, ap.id_athlete
        FROM athlete_progressions ap
        WHERE ap.id_club = $cid AND ap.id_epreuve IS NOT NULL $athFilterProg $progFilterYear
    ";
}
$epUnionSub .= ") AS sub";

// Total epreuves
$res = $conn->query("
    SELECT COUNT(DISTINCT id_epreuve) as c FROM ($epUnionSub) AS combined_ep
");
$totalEpreuves = $res ? (int) $res->fetch_assoc()['c'] : 0;

$epreuves = [];
$res = $conn->query("
    SELECT e.nom_epreuve, e.id_epreuve,
           COUNT(DISTINCT comb.id_athlete) as nb_athletes,
           COUNT(*) as nb_records
    FROM ($epUnionSub) AS comb
    JOIN epreuves e ON e.id_epreuve = comb.id_epreuve
    GROUP BY comb.id_epreuve
    ORDER BY
        CASE
            WHEN e.nom_epreuve REGEXP 'Haies' THEN 2
            WHEN e.nom_epreuve REGEXP 'Steeple' THEN 4
            WHEN e.nom_epreuve REGEXP 'Marche' THEN 6
            WHEN e.nom_epreuve REGEXP '(Relais|^[0-9]+x)' THEN 10
            WHEN e.nom_epreuve REGEXP '(Decathlon|Heptathlon|Pentathlon|Triathlon|Tetrathlon|Octathlon)' THEN 9
            WHEN e.nom_epreuve REGEXP '(Poids|Disque|Javelot|Marteau)' THEN 8
            WHEN e.nom_epreuve REGEXP '(Hauteur|Longueur|Triple|Perche)' THEN 7
            WHEN e.nom_epreuve REGEXP '(Marathon|Cross|Semi|Trail|Route|Heure)' THEN 5
            WHEN CAST(e.nom_epreuve AS UNSIGNED) >= 3000 THEN 5
            WHEN CAST(e.nom_epreuve AS UNSIGNED) >= 800 THEN 3
            WHEN CAST(e.nom_epreuve AS UNSIGNED) > 0 THEN 1
            ELSE 11
        END ASC,
        CAST(e.nom_epreuve AS UNSIGNED) ASC,
        e.nom_epreuve ASC
    LIMIT $epLimit OFFSET $epOffset
");
$epRows = [];
if ($res) while ($row = $res->fetch_assoc()) {
    $epRows[] = $row;
}
// Pour chaque epreuve, trouver niveaux via athlete_resultats
$epIds = array_map(function($r) { return (int)$r['id_epreuve']; }, $epRows);
$epNiveaux = [];
if (!empty($epIds)) {
    $epIdsList = implode(',', $epIds);
    $nRes = $conn->query("
        SELECT comb.id_epreuve, ares.niveau_resultat, COUNT(*) as cnt
        FROM ($epUnionSub) AS comb
        JOIN athlete_resultats ares ON ares.id_athlete = comb.id_athlete AND ares.id_epreuve = comb.id_epreuve
        WHERE comb.id_epreuve IN ($epIdsList) AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != ''
        GROUP BY comb.id_epreuve, ares.niveau_resultat
        ORDER BY comb.id_epreuve, cnt DESC
    ");
    if ($nRes) while ($nr = $nRes->fetch_assoc()) {
        $eid2 = (int)$nr['id_epreuve'];
        if (!isset($epNiveaux[$eid2])) $epNiveaux[$eid2] = [];
        if (!in_array($nr['niveau_resultat'], $epNiveaux[$eid2])) {
            $epNiveaux[$eid2][] = $nr['niveau_resultat'];
        }
    }
}
// Map id_epreuve → nom pour detecter distance vs temps
$epNameMap = [];
foreach ($epRows as $r) { $epNameMap[(int)$r['id_epreuve']] = $r['nom_epreuve']; }

// Batch: meilleur record par (epreuve, sexe) — depuis records + progressions
$epBestBySex = []; // [id_epreuve][M|F] => {perf, perf_int, athlete, athlete_id}
if (!empty($epIds)) {
    $epIdsList2 = implode(',', $epIds);
    // Source 1 : athlete_records
    $bRes = $conn->query("
        SELECT ar.id_epreuve, a.sexe_athlete,
               ar.performance_brut_record AS perf_brut, ar.performance_record AS perf_int,
               ar.date_record AS perf_date,
               a.nom_complet_athlete, a.athlete_id_externe,
               e.nom_epreuve
        FROM athlete_records ar
        JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete AND ac.id_club = $cid $athFilter $recMcRec
        JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
        JOIN epreuves e ON e.id_epreuve = ar.id_epreuve
        WHERE ar.id_epreuve IN ($epIdsList2) AND ar.performance_record > 0 $recordFilter
    ");
    if ($bRes) while ($br = $bRes->fetch_assoc()) {
        _updateBestBySex($epBestBySex, $br);
    }
    // Source 2 : athlete_progressions (mode non-perso uniquement)
    if (!$perso) {
        $bRes2 = $conn->query("
            SELECT ap.id_epreuve, a.sexe_athlete,
                   ap.performance_brut_progression AS perf_brut, ap.performance_progression AS perf_int,
                   COALESCE(ap.date_progression, CONCAT(ap.annee_progression, '-01-01')) AS perf_date,
                   a.nom_complet_athlete, a.athlete_id_externe,
                   e.nom_epreuve
            FROM athlete_progressions ap
            JOIN athletes a ON a.id_athlete = ap.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
            JOIN epreuves e ON e.id_epreuve = ap.id_epreuve
            WHERE ap.id_club = $cid AND ap.id_epreuve IN ($epIdsList2) AND ap.performance_progression > 0 $athFilterProg $progFilterYear
        ");
        if ($bRes2) while ($br = $bRes2->fetch_assoc()) {
            _updateBestBySex($epBestBySex, $br);
        }
    }
}
foreach ($epRows as $row) {
    $eid = (int) $row['id_epreuve'];
    $bestM = $epBestBySex[$eid]['M'] ?? null;
    $bestF = $epBestBySex[$eid]['F'] ?? null;
    $disc = getEpreuveDiscipline($row['nom_epreuve']);
    $epreuves[] = [
        'epreuve'          => $row['nom_epreuve'],
        'discipline'       => $disc['disc'],
        'disc_color'       => $disc['clr'],
        'nb_athletes'      => (int) $row['nb_athletes'],
        'nb_records'       => (int) $row['nb_records'],
        'best_perf_m'      => $bestM ? $bestM['perf'] : null,
        'best_athlete_m'   => $bestM ? $bestM['athlete'] : null,
        'best_athlete_id_m'=> $bestM ? $bestM['athlete_id'] : null,
        'best_annee_m'     => $bestM ? $bestM['annee'] : null,
        'best_perf_f'      => $bestF ? $bestF['perf'] : null,
        'best_athlete_f'   => $bestF ? $bestF['athlete'] : null,
        'best_athlete_id_f'=> $bestF ? $bestF['athlete_id'] : null,
        'best_annee_f'     => $bestF ? $bestF['annee'] : null,
        'niveaux'          => $epNiveaux[$eid] ?? [],
        'top_niveau'       => highestNiveau($epNiveaux[$eid] ?? []),
    ];
}

// Records des athletes du club — UNION records + progressions, dedup par (athlete, epreuve)
// Sous-requete UNION pour les records
$recUnionSub = "
    SELECT ar.id_athlete, ar.id_epreuve,
           ar.performance_record AS performance_int,
           ar.performance_brut_record AS performance_brut,
           ar.date_record AS perf_date
    FROM athlete_records ar
    JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete AND ac.id_club = $cid $athFilter $recMcRec
    WHERE ar.performance_record > 0 $recordFilter
";
if (!$perso) {
    $recUnionSub .= "
    UNION ALL
    SELECT ap.id_athlete, ap.id_epreuve,
           ap.performance_progression AS performance_int,
           ap.performance_brut_progression AS performance_brut,
           ap.date_progression AS perf_date
    FROM athlete_progressions ap
    WHERE ap.id_club = $cid AND ap.id_epreuve IS NOT NULL AND ap.performance_progression > 0 $athFilterProg $progFilterYear
    ";
}

// Total records (dedup par athlete+epreuve)
$res = $conn->query("
    SELECT COUNT(*) as c FROM (
        SELECT comb.id_athlete, comb.id_epreuve
        FROM ($recUnionSub) AS comb
        GROUP BY comb.id_athlete, comb.id_epreuve
    ) AS uniq
");
$totalRecords = $res ? (int) $res->fetch_assoc()['c'] : 0;

// Records paginés avec ROW_NUMBER() pour garder la meilleure perf par (athlete, epreuve)
$records = [];
$res = $conn->query("
    SELECT ranked.nom_complet_athlete, ranked.athlete_id_externe, ranked.categorie_athlete, ranked.sexe_athlete,
           ranked.nom_epreuve, ranked.performance_brut, ranked.perf_date,
           (SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
            FROM athlete_resultats ares
            WHERE ares.id_athlete = ranked.id_athlete AND ares.id_epreuve = ranked.id_epreuve
              AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
    FROM (
        SELECT comb.id_athlete, comb.id_epreuve, comb.performance_int, comb.performance_brut, comb.perf_date,
               a.nom_complet_athlete, a.athlete_id_externe, a.categorie_athlete, a.sexe_athlete,
               e.nom_epreuve,
               ROW_NUMBER() OVER (
                   PARTITION BY comb.id_athlete, comb.id_epreuve
                   ORDER BY CASE WHEN e.nom_epreuve REGEXP '(Poids|Disque|Javelot|Marteau|Hauteur|Perche|Longueur|Triple|Decathlon|Heptathlon|Pentathlon)'
                                 THEN -CAST(comb.performance_int AS SIGNED)
                                 ELSE comb.performance_int END ASC
               ) AS rn
        FROM ($recUnionSub) AS comb
        JOIN athletes a ON a.id_athlete = comb.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
        JOIN epreuves e ON e.id_epreuve = comb.id_epreuve
    ) AS ranked
    WHERE ranked.rn = 1
    ORDER BY ranked.nom_epreuve ASC,
        CASE WHEN ranked.nom_epreuve REGEXP '(Poids|Disque|Javelot|Marteau|Hauteur|Perche|Longueur|Triple|Decathlon|Heptathlon|Pentathlon)'
             THEN -CAST(ranked.performance_int AS SIGNED)
             ELSE ranked.performance_int END ASC
    LIMIT $recLimit OFFSET $recOffset
");
if ($res) while ($row = $res->fetch_assoc()) {
    $nivList = array_filter(explode(',', $row['niveaux'] ?? ''));
    $disc = getEpreuveDiscipline($row['nom_epreuve']);
    $records[] = [
        'athlete'     => $row['nom_complet_athlete'],
        'athlete_id'  => (int) $row['athlete_id_externe'],
        'categorie'   => $row['categorie_athlete'],
        'sexe'        => $row['sexe_athlete'],
        'epreuve'     => $row['nom_epreuve'],
        'discipline'  => $disc['disc'],
        'disc_color'  => $disc['clr'],
        'performance' => $row['performance_brut'],
        'date'        => $row['perf_date'],
        'niveaux'     => array_values($nivList),
        'top_niveau'  => highestNiveau(array_values($nivList)),
    ];
}

// ---- Performances (tous les résultats OU records personnels) ----
$totalPerformances = 0;
$performances = [];

if ($perfMode === 'perso') {
    // Mode Records personnels : table athlete_records, sans filtre période
    $perfRecFilter = _bkYearIn('YEAR(ar.date_record)', $anneeList);
    $resCount = $conn->query("
        SELECT COUNT(*) as c
        FROM athlete_records ar
        JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete AND ac.id_club = $cid $athFilter
        WHERE 1=1 $perfRecFilter
    ");
    if ($resCount) {
        $totalPerformances = (int) $resCount->fetch_assoc()['c'];
    }
    $res = $conn->query("
        SELECT a.nom_complet_athlete, a.athlete_id_externe, a.categorie_athlete, a.sexe_athlete,
               e.nom_epreuve, ar.performance_brut_record, ar.date_record, v.nom_ville,
               (SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
                FROM athlete_resultats ares
                WHERE ares.id_athlete = ar.id_athlete AND ares.id_epreuve = ar.id_epreuve
                  AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
        FROM athlete_records ar
        JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete AND ac.id_club = $cid $athFilter
        JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
        JOIN epreuves e ON e.id_epreuve = ar.id_epreuve
        LEFT JOIN villes v ON v.id_ville = ar.id_ville
        WHERE 1=1 $perfRecFilter
        ORDER BY ar.date_record DESC, a.nom_complet_athlete ASC
        LIMIT $perfLimit OFFSET $perfOffset
    ");
    if ($res) while ($row = $res->fetch_assoc()) {
        $nivList = array_filter(explode(',', $row['niveaux'] ?? ''));
        $performances[] = [
            'athlete'     => $row['nom_complet_athlete'],
            'athlete_id'  => (int) $row['athlete_id_externe'],
            'categorie'   => $row['categorie_athlete'],
            'sexe'        => $row['sexe_athlete'],
            'epreuve'     => $row['nom_epreuve'],
            'performance' => $row['performance_brut_record'],
            'date'        => $row['date_record'],
            'annee'       => $row['date_record'] ? substr($row['date_record'], 0, 4) : null,
            'niveau'      => highestNiveau(array_values($nivList)),
            'niveaux'     => array_values($nivList),
            'place'       => null,
            'ville'       => $row['nom_ville'],
        ];
    }
} else {
    // Mode Toutes les épreuves : table athlete_resultats avec filtre période
    $perfAnneeFilter = _bkYearIn('ares.annee_resultat', $anneeList);
    $resCount = $conn->query("
        SELECT COUNT(*) as c
        FROM athlete_resultats ares
        JOIN athlete_clubs ac ON ac.id_athlete = ares.id_athlete AND ac.id_club = $cid $athFilter $mcRes
        WHERE 1=1 $perfAnneeFilter
    ");
    if ($resCount) {
        $totalPerformances = (int) $resCount->fetch_assoc()['c'];
    }
    $res = $conn->query("
        SELECT a.nom_complet_athlete, a.athlete_id_externe, a.categorie_athlete, a.sexe_athlete,
               e.nom_epreuve, ares.performance_brut_resultat, ares.date_resultat, ares.annee_resultat,
               ares.niveau_resultat, ares.place_resultat, v.nom_ville
        FROM athlete_resultats ares
        JOIN athlete_clubs ac ON ac.id_athlete = ares.id_athlete AND ac.id_club = $cid $athFilter $mcRes
        JOIN athletes a ON a.id_athlete = ares.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
        JOIN epreuves e ON e.id_epreuve = ares.id_epreuve
        LEFT JOIN villes v ON v.id_ville = ares.id_ville
        WHERE 1=1 $perfAnneeFilter
        ORDER BY ares.date_resultat DESC, a.nom_complet_athlete ASC
        LIMIT $perfLimit OFFSET $perfOffset
    ");
    if ($res) while ($row = $res->fetch_assoc()) {
        $performances[] = [
            'athlete'     => $row['nom_complet_athlete'],
            'athlete_id'  => (int) $row['athlete_id_externe'],
            'categorie'   => $row['categorie_athlete'],
            'sexe'        => $row['sexe_athlete'],
            'epreuve'     => $row['nom_epreuve'],
            'performance' => $row['performance_brut_resultat'],
            'date'        => $row['date_resultat'],
            'annee'       => $row['annee_resultat'],
            'niveau'      => $row['niveau_resultat'],
            'place'       => $row['place_resultat'],
            'ville'       => $row['nom_ville'],
        ];
    }
}

// Top 10 epreuves legacy (par nombre d'athletes — UNION records + progressions)
$topEpreuves = [];
$res = $conn->query("
    SELECT e.nom_epreuve, COUNT(DISTINCT comb.id_athlete) as c
    FROM ($epUnionSub) AS comb
    JOIN epreuves e ON e.id_epreuve = comb.id_epreuve
    GROUP BY comb.id_epreuve ORDER BY c DESC LIMIT 10
");
if ($res) while ($row = $res->fetch_assoc()) {
    $topEpreuves[] = ['epreuve' => $row['nom_epreuve'], 'nb_records' => (int) $row['c']];
}

// Top 10 athletes (par nombre de resultats)
$topAthletes = [];
$topAthFilter = _bkYearIn('ares.annee_resultat', $anneeList);
$res = $conn->query("
    SELECT a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete, a.categorie_athlete, a.sexe_athlete,
           COUNT(DISTINCT ares.id_resultat) as nb_resultats,
           COUNT(DISTINCT ar.id_record) as nb_records
    FROM athlete_clubs ac
    JOIN athletes a ON a.id_athlete = ac.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    LEFT JOIN athlete_resultats ares ON ares.id_athlete = a.id_athlete $topAthFilter
        AND (ares.annee_resultat IS NULL OR ares.annee_resultat = 0 OR ares.annee_resultat BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))
    LEFT JOIN athlete_records ar ON ar.id_athlete = a.id_athlete
        AND (ar.date_record IS NULL OR YEAR(ar.date_record) BETWEEN IFNULL(NULLIF(ac.annee_debut,0),0) AND IFNULL(NULLIF(ac.annee_fin,0),9999))
    WHERE ac.id_club = $cid $athFilter
    GROUP BY a.id_athlete
    HAVING nb_resultats > 0
    ORDER BY nb_resultats DESC
    LIMIT 10
");
if ($res) while ($row = $res->fetch_assoc()) {
    $topAthletes[] = [
        'id_athlete'   => (int) $row['id_athlete'],
        'athlete_id'   => (int) $row['athlete_id_externe'],
        'nom_complet'  => $row['nom_complet_athlete'],
        'categorie'    => $row['categorie_athlete'],
        'sexe'         => $row['sexe_athlete'],
        'nb_resultats' => (int) $row['nb_resultats'],
        'nb_records'   => (int) $row['nb_records'],
    ];
}

// Niveaux des athletes du club (repartition par code_niveau)
$niveauFilter = _bkYearIn('n.annee_niveau', $anneeList);
$niveaux = [];
$res = $conn->query("
    SELECT n.code_niveau, COUNT(DISTINCT n.id_athlete) as nb_athletes,
           MAX(n.points_niveau) as max_points
    FROM athlete_niveaux n
    JOIN athlete_clubs ac ON ac.id_athlete = n.id_athlete AND ac.id_club = $cid $athFilter $mcNiv
    WHERE n.code_niveau IS NOT NULL AND n.code_niveau != '' $niveauFilter
    GROUP BY n.code_niveau
    ORDER BY max_points DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $niveaux[] = [
        'code_niveau'  => $row['code_niveau'],
        'nb_athletes'  => (int) $row['nb_athletes'],
        'max_points'   => $row['max_points'] ? (int) $row['max_points'] : null,
    ];
}

// Meilleur niveau global du club
$meilleurNiveauClub = null;
$res = $conn->query("
    SELECT n.code_niveau, n.points_niveau, a.nom_complet_athlete, n.annee_niveau
    FROM athlete_niveaux n
    JOIN athlete_clubs ac ON ac.id_athlete = n.id_athlete AND ac.id_club = $cid $athFilter $mcNiv
    JOIN athletes a ON a.id_athlete = n.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE n.code_niveau IS NOT NULL AND n.code_niveau != '' $niveauFilter
    ORDER BY n.points_niveau DESC
    LIMIT 1
");
if ($res) {
    $row = $res->fetch_assoc();
    if ($row) {
        $meilleurNiveauClub = [
            'code_niveau' => $row['code_niveau'],
            'points'      => $row['points_niveau'] ? (int) $row['points_niveau'] : null,
            'athlete'     => $row['nom_complet_athlete'],
            'annee'       => $row['annee_niveau'] ? (int) $row['annee_niveau'] : null,
        ];
    }
}

// Periode activite
$res = $conn->query("SELECT MIN(ac.annee_debut) as d, MAX(ac.annee_fin) as f FROM athlete_clubs ac WHERE ac.id_club = $cid $athFilter");
$periode = $res ? $res->fetch_assoc() : ['d' => null, 'f' => null];

// Podiums du club (top places)
$podiums = ['1er' => 0, '2e' => 0, '3e' => 0];
$podiumFilter = _bkYearIn('ap.annee_podium', $anneeList);
$res = $conn->query("
    SELECT ap.place_podium, COUNT(*) as c
    FROM athlete_podiums ap
    JOIN athlete_clubs ac ON ac.id_athlete = ap.id_athlete AND ac.id_club = $cid $athFilter $mcPod
    WHERE ap.place_podium IN (1,2,3) $podiumFilter
    GROUP BY ap.place_podium
");
if ($res) while ($row = $res->fetch_assoc()) {
    $p = (int)$row['place_podium'];
    if ($p === 1) $podiums['1er'] = (int)$row['c'];
    elseif ($p === 2) $podiums['2e'] = (int)$row['c'];
    elseif ($p === 3) $podiums['3e'] = (int)$row['c'];
}
$totalPodiums = $podiums['1er'] + $podiums['2e'] + $podiums['3e'];

// Niveaux de compétition des podiums (top 3)
$podiumNiveaux = [];
$res = $conn->query("
    SELECT ap.niveau_competition, COUNT(*) as c
    FROM athlete_podiums ap
    JOIN athlete_clubs ac ON ac.id_athlete = ap.id_athlete AND ac.id_club = $cid $athFilter $mcPod
    WHERE ap.niveau_competition IS NOT NULL AND ap.niveau_competition != '' $podiumFilter
    GROUP BY ap.niveau_competition ORDER BY c DESC LIMIT 5
");
if ($res) while ($row = $res->fetch_assoc()) {
    $podiumNiveaux[] = ['niveau' => $row['niveau_competition'], 'count' => (int)$row['c']];
}

// Sélections nationales
$selections = [];
$selFilter = _bkYearIn('YEAR(s.date_selection)', $anneeList);
$res = $conn->query("
    SELECT COUNT(*) as nb_selections, COUNT(DISTINCT s.id_athlete) as nb_athletes_selectionnes,
           COUNT(DISTINCT s.id_competition) as nb_competitions
    FROM athlete_selections s
    JOIN athlete_clubs ac ON ac.id_athlete = s.id_athlete AND ac.id_club = $cid $athFilter $mcSel
    WHERE 1=1 $selFilter
");
if ($res) {
    $row = $res->fetch_assoc();
    $selections = [
        'nb_selections' => (int)($row['nb_selections'] ?? 0),
        'nb_athletes' => (int)($row['nb_athletes_selectionnes'] ?? 0),
        'nb_competitions' => (int)($row['nb_competitions'] ?? 0),
    ];
}

// Top villes (lieux de compétition)
$topVilles = [];
$villeFilter = _bkYearIn('ares.annee_resultat', $anneeList);
$res = $conn->query("
    SELECT v.nom_ville, COUNT(*) as nb_resultats, COUNT(DISTINCT ares.id_athlete) as nb_athletes
    FROM athlete_resultats ares
    JOIN athlete_clubs ac ON ac.id_athlete = ares.id_athlete AND ac.id_club = $cid $athFilter $mcRes
    JOIN villes v ON v.id_ville = ares.id_ville
    WHERE v.nom_ville IS NOT NULL AND v.nom_ville != '' $villeFilter
    GROUP BY ares.id_ville ORDER BY nb_resultats DESC LIMIT 5
");
if ($res) while ($row = $res->fetch_assoc()) {
    $topVilles[] = ['ville' => $row['nom_ville'], 'nb_resultats' => (int)$row['nb_resultats'], 'nb_athletes' => (int)$row['nb_athletes']];
}

// Progressions (nb total + nb épreuves)
$progressions = ['nb_progressions' => 0, 'nb_epreuves' => 0];
$progFilter = _bkYearIn('ap.annee_progression', $anneeList);
$res = $conn->query("
    SELECT COUNT(*) as nb, COUNT(DISTINCT ap.id_epreuve) as nb_ep
    FROM athlete_progressions ap
    JOIN athlete_clubs ac ON ac.id_athlete = ap.id_athlete AND ac.id_club = $cid $athFilter $mcProg
    WHERE 1=1 $progFilter
");
if ($res) {
    $row = $res->fetch_assoc();
    $progressions = ['nb_progressions' => (int)($row['nb'] ?? 0), 'nb_epreuves' => (int)($row['nb_ep'] ?? 0)];
}

// Résultats globaux (toujours, pas seulement en mode année)
$nbResultatsGlobal = 0;
$nbEpreuvesGlobal = 0;
$res = $conn->query("
    SELECT COUNT(*) as nb_resultats, COUNT(DISTINCT ares.id_epreuve) as nb_epreuves
    FROM athlete_resultats ares
    JOIN athlete_clubs ac ON ac.id_athlete = ares.id_athlete AND ac.id_club = $cid $athFilter $mcRes
");
if ($res) {
    $row = $res->fetch_assoc();
    $nbResultatsGlobal = (int)($row['nb_resultats'] ?? 0);
    $nbEpreuvesGlobal = (int)($row['nb_epreuves'] ?? 0);
}

// Top niveaux de résultats (répartition D/R/N/I)
$niveauxResultats = [];
$nivResFilter = _bkYearIn('ares.annee_resultat', $anneeList);
$res = $conn->query("
    SELECT ares.niveau_resultat, COUNT(*) as c
    FROM athlete_resultats ares
    JOIN athlete_clubs ac ON ac.id_athlete = ares.id_athlete AND ac.id_club = $cid $athFilter $mcRes
    WHERE ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '' $nivResFilter
    GROUP BY ares.niveau_resultat ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $niveauxResultats[] = ['niveau' => $row['niveau_resultat'], 'count' => (int)$row['c']];
}

// Médailles détaillées (compétitions + épreuves)
$medaillesDetail = [];
$res = $conn->query("
    SELECT am.type_medaille, co.nom_competition, e.nom_epreuve, a.nom_complet_athlete, am.annee_medaille
    FROM athlete_medailles am
    JOIN athlete_clubs ac ON ac.id_athlete = am.id_athlete AND ac.id_club = $cid $athFilter $mcMed
    JOIN athletes a ON a.id_athlete = am.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    LEFT JOIN competitions co ON co.id_competition = am.id_competition
    LEFT JOIN epreuves e ON e.id_epreuve = am.id_epreuve
    WHERE am.type_medaille IN ('or','argent','bronze') $medailleFilter
    ORDER BY FIELD(am.type_medaille,'or','argent','bronze'), am.annee_medaille DESC
    LIMIT 15
");
if ($res) while ($row = $res->fetch_assoc()) {
    $medaillesDetail[] = [
        'type' => $row['type_medaille'],
        'competition' => $row['nom_competition'],
        'epreuve' => $row['nom_epreuve'],
        'athlete' => $row['nom_complet_athlete'],
        'annee' => $row['annee_medaille'] ? (int)$row['annee_medaille'] : null,
    ];
}

// Annees disponibles (toujours retourne)
$anneesDisponibles = [];
$res = $conn->query("
    SELECT DISTINCT annee FROM (
        SELECT ares.annee_resultat AS annee FROM athlete_resultats ares
        JOIN athlete_clubs ac ON ac.id_athlete = ares.id_athlete AND ac.id_club = $cid $athFilter $mcRes
        WHERE ares.annee_resultat IS NOT NULL AND ares.annee_resultat > 0
        UNION
        SELECT ap.annee_progression AS annee FROM athlete_progressions ap
        JOIN athlete_clubs ac ON ac.id_athlete = ap.id_athlete AND ac.id_club = $cid $athFilter $mcProg
        WHERE ap.annee_progression IS NOT NULL AND ap.annee_progression > 0
        UNION
        SELECT am.annee_medaille AS annee FROM athlete_medailles am
        JOIN athlete_clubs ac ON ac.id_athlete = am.id_athlete AND ac.id_club = $cid $athFilter $mcMed
        WHERE am.annee_medaille IS NOT NULL AND am.annee_medaille > 0
        UNION
        SELECT n.annee_niveau AS annee FROM athlete_niveaux n
        JOIN athlete_clubs ac ON ac.id_athlete = n.id_athlete AND ac.id_club = $cid $athFilter $mcNiv
        WHERE n.annee_niveau IS NOT NULL AND n.annee_niveau > 0
    ) AS all_years
    ORDER BY annee DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $anneesDisponibles[] = (int) $row['annee'];
}

// Top athletes médaillés
$topMedailleAthletes = [];
$res = $conn->query("
    SELECT a.nom_complet_athlete, a.athlete_id_externe,
           SUM(am.type_medaille = 'or') as nb_or,
           SUM(am.type_medaille = 'argent') as nb_argent,
           SUM(am.type_medaille = 'bronze') as nb_bronze,
           COUNT(*) as total
    FROM athlete_medailles am
    JOIN athlete_clubs ac ON ac.id_athlete = am.id_athlete AND ac.id_club = $cid $athFilter $mcMed
    JOIN athletes a ON a.id_athlete = am.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE am.type_medaille IN ('or','argent','bronze') $medailleFilter
    GROUP BY am.id_athlete ORDER BY total DESC LIMIT 5
");
if ($res) while ($row = $res->fetch_assoc()) {
    $topMedailleAthletes[] = [
        'athlete' => $row['nom_complet_athlete'],
        'athlete_id' => (int)$row['athlete_id_externe'],
        'or' => (int)$row['nb_or'], 'argent' => (int)$row['nb_argent'], 'bronze' => (int)$row['nb_bronze'],
        'total' => (int)$row['total'],
    ];
}

// Top compétitions médaillées
$topMedailleCompetitions = [];
$res = $conn->query("
    SELECT co.nom_competition, COUNT(*) as total,
           SUM(am.type_medaille = 'or') as nb_or
    FROM athlete_medailles am
    JOIN athlete_clubs ac ON ac.id_athlete = am.id_athlete AND ac.id_club = $cid $athFilter $mcMed
    JOIN competitions co ON co.id_competition = am.id_competition
    WHERE am.type_medaille IN ('or','argent','bronze') $medailleFilter
    GROUP BY am.id_competition ORDER BY total DESC LIMIT 5
");
if ($res) while ($row = $res->fetch_assoc()) {
    $topMedailleCompetitions[] = ['competition' => $row['nom_competition'], 'total' => (int)$row['total'], 'or' => (int)$row['nb_or']];
}

// Top épreuves médaillées
$topMedailleEpreuves = [];
$res = $conn->query("
    SELECT e.nom_epreuve, COUNT(*) as total,
           SUM(am.type_medaille = 'or') as nb_or
    FROM athlete_medailles am
    JOIN athlete_clubs ac ON ac.id_athlete = am.id_athlete AND ac.id_club = $cid $athFilter $mcMed
    JOIN epreuves e ON e.id_epreuve = am.id_epreuve
    WHERE am.type_medaille IN ('or','argent','bronze') $medailleFilter
    GROUP BY am.id_epreuve ORDER BY total DESC LIMIT 5
");
if ($res) while ($row = $res->fetch_assoc()) {
    $topMedailleEpreuves[] = ['epreuve' => $row['nom_epreuve'], 'total' => (int)$row['total'], 'or' => (int)$row['nb_or']];
}

// Top épreuves podiums
$topPodiumEpreuves = [];
$res = $conn->query("
    SELECT e.nom_epreuve, COUNT(*) as total, SUM(ap.place_podium = 1) as nb_1er
    FROM athlete_podiums ap
    JOIN athlete_clubs ac ON ac.id_athlete = ap.id_athlete AND ac.id_club = $cid $athFilter $mcPod
    JOIN epreuves e ON e.id_epreuve = ap.id_epreuve
    WHERE ap.place_podium IN (1,2,3) $podiumFilter
    GROUP BY ap.id_epreuve ORDER BY total DESC LIMIT 5
");
if ($res) while ($row = $res->fetch_assoc()) {
    $topPodiumEpreuves[] = ['epreuve' => $row['nom_epreuve'], 'total' => (int)$row['total'], 'nb_1er' => (int)$row['nb_1er']];
}

// Athletes sélectionnés (noms)
$athletesSelectionnes = [];
$res = $conn->query("
    SELECT DISTINCT a.nom_complet_athlete, a.athlete_id_externe, COUNT(*) as nb_sel
    FROM athlete_selections s
    JOIN athlete_clubs ac ON ac.id_athlete = s.id_athlete AND ac.id_club = $cid $athFilter $mcSel
    JOIN athletes a ON a.id_athlete = s.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE 1=1 $selFilter
    GROUP BY s.id_athlete ORDER BY nb_sel DESC LIMIT 5
");
if ($res) while ($row = $res->fetch_assoc()) {
    $athletesSelectionnes[] = ['athlete' => $row['nom_complet_athlete'], 'athlete_id' => (int)$row['athlete_id_externe'], 'nb_selections' => (int)$row['nb_sel']];
}

// Résultats par année (évolution)
$resultatsParAnnee = [];
$res = $conn->query("
    SELECT ares.annee_resultat as annee, COUNT(*) as nb, COUNT(DISTINCT ares.id_athlete) as nb_ath
    FROM athlete_resultats ares
    JOIN athlete_clubs ac ON ac.id_athlete = ares.id_athlete AND ac.id_club = $cid $athFilter $mcRes
    WHERE ares.annee_resultat > 0
    GROUP BY ares.annee_resultat ORDER BY ares.annee_resultat DESC LIMIT 10
");
if ($res) while ($row = $res->fetch_assoc()) {
    $resultatsParAnnee[] = ['annee' => (int)$row['annee'], 'nb_resultats' => (int)$row['nb'], 'nb_athletes' => (int)$row['nb_ath']];
}

// Niveaux par année (courbe évolution)
$niveauxParAnnee = [];
$res = $conn->query("
    SELECT ares.annee_resultat as annee,
           SUBSTRING(ares.niveau_resultat, 1, 1) as famille,
           COUNT(*) as nb
    FROM athlete_resultats ares
    JOIN athlete_clubs ac ON ac.id_athlete = ares.id_athlete AND ac.id_club = $cid $athFilter $mcRes
    WHERE ares.annee_resultat > 0
      AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != ''
    GROUP BY ares.annee_resultat, famille
    ORDER BY ares.annee_resultat
");
if ($res) while ($row = $res->fetch_assoc()) {
    $a = (int)$row['annee'];
    $f = $row['famille'];
    if (!in_array($f, ['D','R','N','I'])) continue;
    if (!isset($niveauxParAnnee[$a])) $niveauxParAnnee[$a] = ['annee' => $a, 'D' => 0, 'R' => 0, 'N' => 0, 'I' => 0];
    $niveauxParAnnee[$a][$f] += (int)$row['nb'];
}
$niveauxParAnnee = array_values($niveauxParAnnee);

// Compteurs supplementaires pour comparaison annuelle
$nbResultats = null;
$nbEpreuves = null;
if ($hasYear) {
    $res = $conn->query("
        SELECT COUNT(*) as nb_resultats, COUNT(DISTINCT ares.id_epreuve) as nb_epreuves
        FROM athlete_resultats ares
        JOIN athlete_clubs ac ON ac.id_athlete = ares.id_athlete AND ac.id_club = $cid $athFilter $mcRes
        WHERE ares.annee_resultat IN ($yearIn)
    ");
    if ($res) {
        $row = $res->fetch_assoc();
        $nbResultats = (int) $row['nb_resultats'];
        $nbEpreuves = (int) $row['nb_epreuves'];
    }
}

// Détail par nationalité pour comparaison
$natCompare = [];
if ($natDetail !== '') {
    $natCodes = array_filter(array_map('trim', explode(',', $natDetail)));
    foreach ($natCodes as $nc) {
        $ncEsc = $conn->real_escape_string(strtoupper($nc));
        $nd = ['code' => strtoupper($nc), 'nb_athletes' => 0, 'par_sexe' => [], 'par_categorie' => [], 'top_epreuves' => [], 'niveaux' => [], 'medailles' => ['or'=>0,'argent'=>0,'bronze'=>0]];

        // Nb athletes
        $r = $conn->query("SELECT COUNT(DISTINCT ac.id_athlete) as c FROM athlete_clubs ac JOIN athletes a ON a.id_athlete=ac.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt}) WHERE ac.id_club=$cid AND a.nationalite_athlete='$ncEsc' $activeFilter");
        if ($r) $nd['nb_athletes'] = (int)$r->fetch_assoc()['c'];

        // Par sexe
        $r = $conn->query("SELECT a.sexe_athlete, COUNT(DISTINCT a.id_athlete) as c FROM athlete_clubs ac JOIN athletes a ON a.id_athlete=ac.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt}) WHERE ac.id_club=$cid AND a.nationalite_athlete='$ncEsc' $activeFilter GROUP BY a.sexe_athlete ORDER BY c DESC");
        if ($r) while ($row = $r->fetch_assoc()) $nd['par_sexe'][$row['sexe_athlete'] ?: 'Inconnu'] = (int)$row['c'];

        // Par categorie
        $r = $conn->query("SELECT a.categorie_athlete, COUNT(DISTINCT a.id_athlete) as c FROM athlete_clubs ac JOIN athletes a ON a.id_athlete=ac.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt}) WHERE ac.id_club=$cid AND a.nationalite_athlete='$ncEsc' AND a.categorie_athlete!='' $activeFilter GROUP BY a.categorie_athlete ORDER BY c DESC");
        if ($r) while ($row = $r->fetch_assoc()) $nd['par_categorie'][$row['categorie_athlete']] = (int)$row['c'];

        // Top 5 epreuves
        $r = $conn->query("SELECT e.nom_epreuve, COUNT(DISTINCT ar.id_athlete) as c FROM athlete_clubs ac JOIN athletes a ON a.id_athlete=ac.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt}) JOIN athlete_records ar ON ar.id_athlete=a.id_athlete JOIN epreuves e ON e.id_epreuve=ar.id_epreuve WHERE ac.id_club=$cid AND a.nationalite_athlete='$ncEsc' $recMcRec $activeFilter GROUP BY e.nom_epreuve ORDER BY c DESC LIMIT 5");
        if ($r) while ($row = $r->fetch_assoc()) $nd['top_epreuves'][] = ['epreuve' => $row['nom_epreuve'], 'nb' => (int)$row['c']];

        // Niveaux
        $r = $conn->query("SELECT n.code_niveau, COUNT(DISTINCT n.id_athlete) as c FROM athlete_clubs ac JOIN athletes a ON a.id_athlete=ac.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt}) JOIN athlete_niveaux n ON n.id_athlete=a.id_athlete WHERE ac.id_club=$cid AND a.nationalite_athlete='$ncEsc' AND n.code_niveau IS NOT NULL AND n.code_niveau!='' $mcNiv $activeFilter GROUP BY n.code_niveau ORDER BY c DESC");
        if ($r) while ($row = $r->fetch_assoc()) $nd['niveaux'][$row['code_niveau']] = (int)$row['c'];

        // Medailles
        $r = $conn->query("SELECT am.type_medaille, COUNT(*) as c FROM athlete_clubs ac JOIN athletes a ON a.id_athlete=ac.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt}) JOIN athlete_medailles am ON am.id_athlete=a.id_athlete WHERE ac.id_club=$cid AND a.nationalite_athlete='$ncEsc' $mcMed $activeFilter GROUP BY am.type_medaille");
        if ($r) while ($row = $r->fetch_assoc()) { if (isset($nd['medailles'][$row['type_medaille']])) $nd['medailles'][$row['type_medaille']] = (int)$row['c']; }

        // Meilleur niveau
        $allNiv = array_keys($nd['niveaux']);
        $nd['meilleur_niveau'] = !empty($allNiv) ? highestNiveau($allNiv) : null;

        $natCompare[] = $nd;
    }
}

// Annees de naissance disponibles dans le club (pour l'onglet Generations)
// Respecte les autres filtres (nat/sexe/cat) mais PAS le filtre annee de naissance
// pour que le selecteur affiche toujours toutes les annees.
$anneesNaissanceDisponibles = [];
$naissWhere = !empty($afCondsNoNaiss)
    ? (' AND a.id_athlete IN (SELECT _af.id_athlete FROM athletes _af WHERE ' . implode(' AND ', $afCondsNoNaiss) . ')')
    : '';
$resN = $conn->query("
    SELECT a.annee_naissance_athlete AS annee, COUNT(DISTINCT a.id_athlete) AS nb
    FROM athletes a
    JOIN athlete_clubs ac ON ac.id_athlete = a.id_athlete AND ac.id_club = $cid
    WHERE a.annee_naissance_athlete IS NOT NULL AND a.annee_naissance_athlete > 0 $naissWhere
    GROUP BY a.annee_naissance_athlete
    ORDER BY a.annee_naissance_athlete DESC
");
if ($resN) {
    while ($rowN = $resN->fetch_assoc()) {
        $anneesNaissanceDisponibles[] = ['annee' => (int) $rowN['annee'], 'nb' => (int) $rowN['nb']];
    }
}

$response = [
    'success'             => true,
    'club'                => ['id_club' => $cid, 'nom_club' => $club['nom_club']],
    'total_athletes'      => $totalAthletes,
    'par_sexe'            => $parSexe,
    'par_categorie'       => $parCategorie,
    'nationalites'        => $nationalites,
    'medailles'           => $medailles,
    'medailles_detail'    => $medaillesDetail,
    'podiums'             => $podiums,
    'total_podiums'       => $totalPodiums,
    'podium_niveaux'      => $podiumNiveaux,
    'selections'          => $selections,
    'epreuves'            => $epreuves,
    'total_epreuves'      => $totalEpreuves,
    'ep_page'             => $epPage,
    'ep_pages'            => (int) ceil($totalEpreuves / $epLimit),
    'records'             => $records,
    'total_records'       => $totalRecords,
    'rec_page'            => $recPage,
    'rec_pages'           => (int) ceil($totalRecords / $recLimit),
    'performances'        => $performances,
    'total_performances'  => $totalPerformances,
    'perf_page'           => $perfPage,
    'perf_pages'          => (int) ceil($totalPerformances / $perfLimit),
    'perf_mode'           => $perfMode,
    'top_epreuves'        => $topEpreuves,
    'top_athletes'        => $topAthletes,
    'top_villes'          => $topVilles,
    'niveaux'             => $niveaux,
    'niveaux_resultats'   => $niveauxResultats,
    'meilleur_niveau'     => $meilleurNiveauClub,
    'progressions'             => $progressions,
    'nb_resultats_global'      => $nbResultatsGlobal,
    'nb_epreuves_global'       => $nbEpreuvesGlobal,
    'top_medaille_athletes'    => $topMedailleAthletes,
    'top_medaille_competitions'=> $topMedailleCompetitions,
    'top_medaille_epreuves'    => $topMedailleEpreuves,
    'top_podium_epreuves'      => $topPodiumEpreuves,
    'athletes_selectionnes'    => $athletesSelectionnes,
    'resultats_par_annee'      => $resultatsParAnnee,
    'niveaux_par_annee'        => $niveauxParAnnee,
    'annee_debut'              => $periode['d'] ? (int) $periode['d'] : null,
    'annee_fin'                => $periode['f'] ? (int) $periode['f'] : null,
    'annees_disponibles'       => $anneesDisponibles,
    'annee_filtree'            => $hasYear ? $yearIn : null,
    'filter_nationalite'       => $filterNat !== '' ? $filterNat : null,
    'filter_sexe'              => $filterSexe !== '' ? $filterSexe : null,
    'filter_categorie'         => $filterCat !== '' ? $filterCat : null,
    'annees_naissance_disponibles' => $anneesNaissanceDisponibles,
    'filter_annee_naissance'   => $filterNaissance !== '' ? $filterNaissance : null,
];
if (!empty($natCompare)) {
    $response['nat_compare'] = $natCompare;
}
if ($hasYear) {
    $response['nb_resultats'] = $nbResultats;
    $response['nb_epreuves']  = $nbEpreuves;
}

// ============================================================
//  Plafond annuel leve : toutes les annees sont desormais
//  selectionnables et visibles. Le filtrage se fait via le
//  parametre `annee` (liste IN), pas par un fenetrage fixe.
// ============================================================

$json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($response);
