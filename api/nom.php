<?php
/**
 * api/nom.php — Recherche par nom de famille
 *
 * Usage :
 *   api/nom.php                       Top 100 noms de famille (les plus frequents)
 *   api/nom.php?limit=200             Top 200
 *   api/nom.php?q=luvumbu             Liste athletes + stats riches pour ce nom
 *   api/nom.php?q=luvumbu&detail=0    Sans la liste des athletes (stats seules)
 *
 * Retourne JSON :
 *   - sans q : { success, top_names: [{nom, count, M, F}, ...] }
 *   - avec q : { success, nom, total, stats: {...}, athletes: [...] }
 *
 * Cache fichier 24h (cache/nom_*.json).
 */

require_once __DIR__ . '/config.php';

// ===== Bareme FFA (calcul des niveaux a partir des performances) =====
// Reprend la meme logique que api/athlete.php pour _perfToFfaPts + _ffaPtsToLevel.
// Permet d'attribuer le niveau IA/IB/N1/... a partir de la performance brute,
// car athlete_niveaux et athlete_resultats.niveau_resultat ne sont pas toujours
// renseignes (notamment IA/IB qui sont des niveaux "calcules" depuis la perf).
$_nomBaremeConfig = @include(__DIR__ . '/../config/bareme_hommes.php');
$_nomBaremeBreakpoints = $_nomBaremeConfig['breakpoints'] ?? [];
$_nomBaremeMapping     = $_nomBaremeConfig['mapping'] ?? [];
$_nomBaremeUserMap     = [];
$_nomUserMapFile = __DIR__ . '/../logs/.bareme_user_mapping.php';
if (file_exists($_nomUserMapFile)) {
    $_umRaw = @file_get_contents($_nomUserMapFile);
    $_umPos = $_umRaw ? strpos($_umRaw, "\n") : false;
    if ($_umPos !== false) {
        $_um = json_decode(substr($_umRaw, $_umPos + 1), true);
        if (is_array($_um)) {
            foreach ($_um as $ep => $val) {
                if (is_string($val)) $_nomBaremeUserMap[$ep] = ['M' => $val, 'F' => $val];
                elseif (is_array($val)) $_nomBaremeUserMap[$ep] = ['M' => (string)($val['M'] ?? ''), 'F' => (string)($val['F'] ?? '')];
            }
        }
    }
}

function _nomIsDistEp($nom) {
    return (bool)preg_match('/(poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon|pentathlon)/i', $nom);
}
function _nomPerfToFfaPts($perfInt, $epName, $sexe = 'M') {
    global $_nomBaremeBreakpoints, $_nomBaremeMapping, $_nomBaremeUserMap;
    if ($perfInt <= 0) return 0;
    $sx = ($sexe === 'F') ? 'F' : 'M';
    $bName = '';
    if (isset($_nomBaremeUserMap[$epName][$sx]) && $_nomBaremeUserMap[$epName][$sx] !== '') {
        $bName = $_nomBaremeUserMap[$epName][$sx];
    } elseif (isset($_nomBaremeMapping[$epName]) && is_string($_nomBaremeMapping[$epName]) && $_nomBaremeMapping[$epName] !== '') {
        $bName = $_nomBaremeMapping[$epName];
    } else {
        $bName = $epName;
    }
    $bp = $_nomBaremeBreakpoints[$bName] ?? null;
    if (!$bp || empty($bp)) return 0;
    $isDist = _nomIsDistEp($epName);
    $nbBp = count($bp);
    for ($i = 0; $i < $nbBp; $i++) {
        $pts = $bp[$i][0]; $perf = $bp[$i][1];
        if ($isDist) {
            if ($perfInt >= $perf) {
                if ($i === 0) return $pts;
                $prevPts = $bp[$i-1][0]; $prevPerf = $bp[$i-1][1];
                return $pts + ($perfInt - $perf) / max(1, $prevPerf - $perf) * ($prevPts - $pts);
            }
        } else {
            if ($perfInt <= $perf) {
                if ($i === 0) return $pts;
                $prevPts = $bp[$i-1][0]; $prevPerf = $bp[$i-1][1];
                return $pts + ($perf - $perfInt) / max(1, $perf - $prevPerf) * ($prevPts - $pts);
            }
        }
    }
    $lastPts = $bp[$nbBp-1][0]; $lastPerf = $bp[$nbBp-1][1];
    if ($isDist) return max(1, $lastPts * $perfInt / max(1, $lastPerf));
    return max(1, $lastPts * $lastPerf / max(1, $perfInt));
}
function _nomFfaPtsToLevel($pts) {
    if ($pts >= 40) return 'IA';  if ($pts >= 35) return 'IB';
    if ($pts >= 30) return 'N1';  if ($pts >= 28) return 'N2';
    if ($pts >= 26) return 'N3';  if ($pts >= 24) return 'N4';
    if ($pts >= 21) return 'IR1'; if ($pts >= 20) return 'IR2';
    if ($pts >= 19) return 'IR3'; if ($pts >= 18) return 'IR4';
    if ($pts >= 15) return 'R1';  if ($pts >= 14) return 'R2';
    if ($pts >= 13) return 'R3';  if ($pts >= 12) return 'R4';
    if ($pts >= 11) return 'R5';  if ($pts >= 10) return 'R6';
    if ($pts >= 8)  return 'D1';  if ($pts >= 7)  return 'D2';
    if ($pts >= 6)  return 'D3';  if ($pts >= 5)  return 'D4';
    if ($pts >= 4)  return 'D5';  return '';
}

// ---- Cache ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

$q        = trim($_GET['q'] ?? '');
$search   = trim($_GET['search'] ?? '');
$level    = strtoupper(trim($_GET['level'] ?? ''));
$minPpl   = max(1, (int)($_GET['min'] ?? 2));
$orBetter = !empty($_GET['or_better']);
$limit    = min(500, max(1, (int)($_GET['limit'] ?? 100)));
$detail   = !isset($_GET['detail']) || $_GET['detail'] !== '0';
$nocache  = !empty($_GET['nocache']);

// ============================================================
//  MODE RECHERCHE APPROFONDIE — noms ayant >=min personnes a un niveau donne
// ============================================================
if ($level !== '') {
    $hierarchy = ['IA','IB','IE','N1','N2','N3','N4','IR','IR1','IR2','IR3','IR4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
    $idx = array_search($level, $hierarchy, true);
    if ($idx === false) {
        echo json_encode(['success'=>false, 'error'=>'Niveau invalide. Utilisez IA, IB, N1, R3, D1, etc.'], JSON_UNESCAPED_UNICODE);
        $conn->close(); exit;
    }
    // Rang FIELD : 1=IA, 2=IB, 3=IE, 4=N1, ... 26=D8. Plus le rang est BAS, meilleur est le niveau.
    $targetRank = $idx + 1; // ex: N1 => 4
    $hListSql = "'" . implode("','", $hierarchy) . "'";

    $cacheKey = 'nom_lvl_' . md5($level . '_' . $minPpl . '_' . ($orBetter ? 1 : 0) . '_' . $limit);
    $cacheFile = $cacheDir . '/' . $cacheKey . '.json';
    if (!$nocache && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
        echo @file_get_contents($cacheFile);
        $conn->close(); exit;
    }

    // ===== Compteurs de scan (preuve d'execution) =====
    $t0 = microtime(true);
    $scanStats = ['scanned_at' => date('Y-m-d H:i:s')];

    // Total d'athletes en BDD (visible)
    $rA = $conn->query("SELECT COUNT(*) AS n FROM athletes WHERE visible = 1");
    $scanStats['total_athletes_db'] = $rA ? (int)$rA->fetch_assoc()['n'] : 0;

    // Total qui ont AU MOINS un niveau enregistre (sur les 2 sources)
    $rB = $conn->query("
        SELECT COUNT(DISTINCT id_athlete) AS n FROM (
            SELECT id_athlete FROM athlete_niveaux WHERE FIELD(code_niveau, $hListSql) > 0
            UNION
            SELECT id_athlete FROM athlete_resultats WHERE niveau_resultat IS NOT NULL AND niveau_resultat <> '' AND FIELD(niveau_resultat, $hListSql) > 0
        ) t
    ");
    $scanStats['athletes_with_any_level'] = $rB ? (int)$rB->fetch_assoc()['n'] : 0;

    // ===== FIX (2026-05-23) =====
    // Avant : on comptait tout athlete ayant UNE entree au niveau cible. Resultat : un athlete
    // qui a un N1 quelque part mais dont le vrai meilleur niveau est IB etait compte comme "N1",
    // ce qui faisait remonter trop de noms en mode strict.
    // Maintenant : on calcule pour CHAQUE athlete son MEILLEUR rang (MIN(FIELD)) en consolidant
    // athlete_niveaux + athlete_resultats.niveau_resultat, puis on filtre :
    //   - strict   : best_rk = $targetRank  (le meilleur niveau de l'athlete = exactement le niveau cible)
    //   - orBetter : best_rk <= $targetRank (le meilleur niveau de l'athlete = cible ou superieur)
    // C'est aligne avec ce que la page de detail affiche pour chaque athlete.
    $rankCond = $orBetter ? "abn.best_rk <= $targetRank" : "abn.best_rk = $targetRank";

    $sql = "
        SELECT
            UPPER(TRIM(a.nom_1_athlete)) AS nom,
            COUNT(DISTINCT a.id_athlete) AS total,
            SUM(CASE WHEN a.sexe_athlete = 'M' THEN 1 ELSE 0 END) AS nbM,
            SUM(CASE WHEN a.sexe_athlete = 'F' THEN 1 ELSE 0 END) AS nbF
        FROM athletes a
        JOIN (
            SELECT id_athlete, MIN(rk) AS best_rk
            FROM (
                SELECT id_athlete, FIELD(code_niveau, $hListSql) AS rk
                  FROM athlete_niveaux
                  WHERE FIELD(code_niveau, $hListSql) > 0
                UNION ALL
                SELECT id_athlete, FIELD(niveau_resultat, $hListSql) AS rk
                  FROM athlete_resultats
                  WHERE niveau_resultat IS NOT NULL AND niveau_resultat <> ''
                    AND FIELD(niveau_resultat, $hListSql) > 0
            ) all_lvls
            GROUP BY id_athlete
        ) abn ON abn.id_athlete = a.id_athlete
        WHERE a.visible = 1
          AND a.nom_1_athlete IS NOT NULL
          AND TRIM(a.nom_1_athlete) <> ''
          AND $rankCond
        GROUP BY UPPER(TRIM(a.nom_1_athlete))
        HAVING total >= $minPpl
        ORDER BY total DESC, nom ASC
        LIMIT $limit
    ";
    $res = $conn->query($sql);
    $results = [];
    $totalAthletesMatching = 0;
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $results[] = [
                'nom'   => $r['nom'],
                'count' => (int)$r['total'],
                'M'     => (int)$r['nbM'],
                'F'     => (int)$r['nbF'],
            ];
            $totalAthletesMatching += (int)$r['total'];
        }
    }

    // Compte separe : nb total d'athletes qui satisfont le critere niveau (avant filtre min)
    $rC = $conn->query("
        SELECT COUNT(DISTINCT a.id_athlete) AS n
        FROM athletes a
        JOIN (
            SELECT id_athlete, MIN(rk) AS best_rk
            FROM (
                SELECT id_athlete, FIELD(code_niveau, $hListSql) AS rk
                  FROM athlete_niveaux WHERE FIELD(code_niveau, $hListSql) > 0
                UNION ALL
                SELECT id_athlete, FIELD(niveau_resultat, $hListSql) AS rk
                  FROM athlete_resultats
                  WHERE niveau_resultat IS NOT NULL AND niveau_resultat <> ''
                    AND FIELD(niveau_resultat, $hListSql) > 0
            ) all_lvls
            GROUP BY id_athlete
        ) abn ON abn.id_athlete = a.id_athlete
        WHERE a.visible = 1
          AND a.nom_1_athlete IS NOT NULL
          AND TRIM(a.nom_1_athlete) <> ''
          AND $rankCond
    ");
    $scanStats['athletes_matching_level'] = $rC ? (int)$rC->fetch_assoc()['n'] : 0;

    // Nb total de noms distincts qui ont au moins 1 athlete du niveau (avant filtre min)
    $rD = $conn->query("
        SELECT COUNT(DISTINCT UPPER(TRIM(a.nom_1_athlete))) AS n
        FROM athletes a
        JOIN (
            SELECT id_athlete, MIN(rk) AS best_rk
            FROM (
                SELECT id_athlete, FIELD(code_niveau, $hListSql) AS rk
                  FROM athlete_niveaux WHERE FIELD(code_niveau, $hListSql) > 0
                UNION ALL
                SELECT id_athlete, FIELD(niveau_resultat, $hListSql) AS rk
                  FROM athlete_resultats
                  WHERE niveau_resultat IS NOT NULL AND niveau_resultat <> ''
                    AND FIELD(niveau_resultat, $hListSql) > 0
            ) all_lvls
            GROUP BY id_athlete
        ) abn ON abn.id_athlete = a.id_athlete
        WHERE a.visible = 1
          AND a.nom_1_athlete IS NOT NULL
          AND TRIM(a.nom_1_athlete) <> ''
          AND $rankCond
    ");
    $scanStats['surnames_with_at_least_one'] = $rD ? (int)$rD->fetch_assoc()['n'] : 0;
    $scanStats['surnames_passing_min']       = count($results);
    $scanStats['athletes_validating']        = $totalAthletesMatching;
    $scanStats['duration_ms']                = (int)round((microtime(true) - $t0) * 1000);

    // Codes inclus pour info (utilise par le UI pour la phrase explicative)
    $codes = $orBetter ? array_slice($hierarchy, 0, $idx + 1) : [$level];
    $payload = [
        'success'   => true,
        'mode'      => 'level',
        'level'     => $level,
        'or_better' => $orBetter,
        'min'       => $minPpl,
        'codes'     => $codes,
        'results'   => $results,
        'count'     => count($results),
        'scan'      => $scanStats,
        'note'      => 'Compte les athletes dont le MEILLEUR niveau (athlete_niveaux + athlete_resultats.niveau_resultat) correspond au critere. Les niveaux IA/IB calcules uniquement depuis la performance ne sont pas pris en compte.',
    ];
    $out = json_encode($payload, JSON_UNESCAPED_UNICODE);
    @file_put_contents($cacheFile, $out);
    echo $out;
    $conn->close();
    exit;
}

// ============================================================
//  MODE LIVE — recherche par prefixe (autocomplete)
// ============================================================
if ($search !== '') {
    // Min 2 caracteres pour eviter de scanner la table sur 1 lettre
    if (mb_strlen($search) < 2) {
        echo json_encode(['success' => true, 'mode' => 'search', 'results' => [], 'query' => $search], JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    }
    $sCache = $cacheDir . '/nom_search_' . md5(strtolower($search)) . '.json';
    if (!$nocache && file_exists($sCache) && (time() - filemtime($sCache)) < 3600) {
        echo @file_get_contents($sCache);
        $conn->close();
        exit;
    }
    $sEsc = $conn->real_escape_string($search);
    $sUp  = strtoupper($sEsc);
    // Prefix prioritaire (UPPER LIKE '...%') ; fallback substring
    $sqlSearch = "
        SELECT
            UPPER(TRIM(nom_1_athlete)) AS nom,
            COUNT(*) AS total,
            SUM(CASE WHEN sexe_athlete = 'M' THEN 1 ELSE 0 END) AS nbM,
            SUM(CASE WHEN sexe_athlete = 'F' THEN 1 ELSE 0 END) AS nbF,
            CASE WHEN UPPER(TRIM(nom_1_athlete)) LIKE '$sUp%' THEN 1 ELSE 2 END AS pri
        FROM athletes
        WHERE nom_1_athlete IS NOT NULL
          AND TRIM(nom_1_athlete) <> ''
          AND visible = 1
          AND UPPER(TRIM(nom_1_athlete)) LIKE '%$sUp%'
        GROUP BY UPPER(TRIM(nom_1_athlete))
        ORDER BY pri ASC, total DESC, nom ASC
        LIMIT 20
    ";
    $res = $conn->query($sqlSearch);
    $results = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $results[] = [
                'nom'   => $r['nom'],
                'count' => (int)$r['total'],
                'M'     => (int)$r['nbM'],
                'F'     => (int)$r['nbF'],
            ];
        }
    }
    $out = json_encode([
        'success' => true,
        'mode'    => 'search',
        'query'   => $search,
        'results' => $results,
    ], JSON_UNESCAPED_UNICODE);
    @file_put_contents($sCache, $out);
    echo $out;
    $conn->close();
    exit;
}

$cacheKey = 'nom_' . md5(strtolower($q) . '_' . $limit . '_' . ($detail ? 1 : 0));
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
if (!$nocache && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) {
        echo $cached;
        $conn->close();
        exit;
    }
}

// ============================================================
//  MODE 1 — sans q : top des noms de famille les plus frequents
// ============================================================
if ($q === '') {
    $sql = "
        SELECT
            UPPER(TRIM(nom_1_athlete)) AS nom,
            COUNT(*) AS total,
            SUM(CASE WHEN sexe_athlete = 'M' THEN 1 ELSE 0 END) AS nbM,
            SUM(CASE WHEN sexe_athlete = 'F' THEN 1 ELSE 0 END) AS nbF
        FROM athletes
        WHERE nom_1_athlete IS NOT NULL
          AND TRIM(nom_1_athlete) <> ''
          AND visible = 1
        GROUP BY UPPER(TRIM(nom_1_athlete))
        HAVING total >= 2
        ORDER BY total DESC, nom ASC
        LIMIT $limit
    ";
    $res = $conn->query($sql);
    $topNames = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $topNames[] = [
                'nom'   => $r['nom'],
                'count' => (int)$r['total'],
                'M'     => (int)$r['nbM'],
                'F'     => (int)$r['nbF'],
            ];
        }
    }

    $payload = [
        'success'      => true,
        'top_names'    => $topNames,
        'total_names'  => count($topNames),
        'generated_at' => date('Y-m-d H:i:s'),
    ];
    $out = json_encode($payload, JSON_UNESCAPED_UNICODE);
    @file_put_contents($cacheFile, $out);
    echo $out;
    $conn->close();
    exit;
}

// ============================================================
//  MODE 2 — avec q : details + stats pour un nom de famille
// ============================================================
$qEsc = $conn->real_escape_string($q);
$qUp  = strtoupper(trim($q));

// Nom canonique (forme la plus courante en BDD)
$resC = $conn->query("
    SELECT TRIM(nom_1_athlete) AS form, COUNT(*) AS n
    FROM athletes
    WHERE UPPER(TRIM(nom_1_athlete)) = '" . $conn->real_escape_string($qUp) . "'
      AND visible = 1
    GROUP BY TRIM(nom_1_athlete)
    ORDER BY n DESC
    LIMIT 1
");
$nomCanon = $qUp;
if ($resC && ($rowC = $resC->fetch_assoc())) {
    $nomCanon = $rowC['form'];
}

// ---- Liste des athletes (limit haut, mais on agrege les stats sur l'ensemble) ----
$listLimit = 500;
$sqlList = "
    SELECT
        a.id_athlete,
        a.athlete_id_externe,
        a.nom_complet_athlete,
        a.nom_1_athlete,
        a.nom_2_athlete,
        a.sexe_athlete,
        a.categorie_athlete,
        a.nationalite_athlete,
        a.annee_naissance_athlete,
        a.date_naissance_athlete,
        a.visible
    FROM athletes a
    WHERE UPPER(TRIM(a.nom_1_athlete)) = '" . $conn->real_escape_string($qUp) . "'
      AND a.visible = 1
    ORDER BY a.nom_2_athlete ASC, a.annee_naissance_athlete DESC
    LIMIT $listLimit
";
$resList = $conn->query($sqlList);
$athletes = [];
$athIds = [];
$athIdsExt = [];
if ($resList) {
    while ($r = $resList->fetch_assoc()) {
        $athletes[] = $r;
        $athIds[]    = (int)$r['id_athlete'];
        $athIdsExt[] = (int)$r['athlete_id_externe'];
    }
}

// $total : nombre d'athletes RETOURNES dans la liste (max = $listLimit)
// $totalReal : nombre TOTAL en BDD pour ce nom (calcule plus bas via SQL)
$total = count($athletes);
if ($total === 0) {
    $payload = [
        'success'   => true,
        'nom'       => $nomCanon,
        'total'     => 0,
        'stats'     => null,
        'athletes'  => [],
    ];
    $out = json_encode($payload, JSON_UNESCAPED_UNICODE);
    @file_put_contents($cacheFile, $out);
    echo $out;
    $conn->close();
    exit;
}

$idsList = implode(',', $athIds);
$thisYear = (int)date('Y');

// ---- Batch : meilleur niveau par athlete (hierarchie FFA) ----
// Echelle de points : IA=26 (top) -> D8=1 (bas). N = nombre total de niveaux dans la hierarchie.
// IMPORTANT : on combine 2 sources comme api/athlete.php le fait pour `meilleur_niveau` :
//   1) athlete_niveaux.code_niveau (qualifications formelles)
//   2) athlete_resultats.niveau_resultat (niveaux atteints en competition — souvent contient IA/IB
//      que la table athlete_niveaux ne contient pas).
// Sans cette fusion, un athlete IB via resultats apparaissait a tort comme N1 sur la page noms.
$hierarchy = ['IA','IB','IE','N1','N2','N3','N4','IR','IR1','IR2','IR3','IR4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
$nivCount  = count($hierarchy);
$nivPoints = function($code) use ($hierarchy, $nivCount) {
    $idx = array_search($code, $hierarchy, true);
    return $idx === false ? null : ($nivCount - $idx); // IA=26, D8=1
};
$hierarchyList = "'" . implode("','", $hierarchy) . "'";
$nivByAth = [];

// Source 1 : athlete_niveaux
$resN = $conn->query("
    SELECT id_athlete, code_niveau
    FROM athlete_niveaux
    WHERE id_athlete IN ($idsList)
      AND FIELD(code_niveau, $hierarchyList) > 0
");
if ($resN) {
    while ($r = $resN->fetch_assoc()) {
        $aid = (int)$r['id_athlete'];
        $code = trim($r['code_niveau']);
        $idx = array_search($code, $hierarchy, true);
        if ($idx === false) continue;
        if (!isset($nivByAth[$aid]) || $idx < $nivByAth[$aid]['idx']) {
            $nivByAth[$aid] = ['idx' => $idx, 'code' => $code, 'pts' => $nivCount - $idx];
        }
    }
}

// Source 2 : athlete_resultats.niveau_resultat (DISTINCT par athlete pour limiter le volume)
$resR2 = $conn->query("
    SELECT id_athlete, niveau_resultat AS code_niveau
    FROM athlete_resultats
    WHERE id_athlete IN ($idsList)
      AND niveau_resultat IS NOT NULL AND niveau_resultat <> ''
      AND FIELD(niveau_resultat, $hierarchyList) > 0
    GROUP BY id_athlete, niveau_resultat
");
if ($resR2) {
    while ($r = $resR2->fetch_assoc()) {
        $aid = (int)$r['id_athlete'];
        $code = trim($r['code_niveau']);
        $idx = array_search($code, $hierarchy, true);
        if ($idx === false) continue;
        if (!isset($nivByAth[$aid]) || $idx < $nivByAth[$aid]['idx']) {
            $nivByAth[$aid] = ['idx' => $idx, 'code' => $code, 'pts' => $nivCount - $idx];
        }
    }
}

// Source 3 : calcul depuis les performances via le bareme FFA.
// C'est la source la plus fiable pour les niveaux IA / IB qui sont rarement stockes
// en BDD (api/athlete.php les calcule a la volee). On lit records ET progressions :
// les progressions sont les meilleures perfs annuelles et capturent souvent un pic
// au niveau IA / IB meme si l'athlete n'a pas de qualification IB officielle.
//
// Cache sexe par athlete (la fonction _nomPerfToFfaPts en a besoin).
$sexByAth = [];
foreach ($athletes as $_a) { $sexByAth[(int)$_a['id_athlete']] = $_a['sexe_athlete'] ?: 'M'; }

$_sqlPerfRecs = "
    SELECT ar.id_athlete, ar.performance_record AS perf, ar.performance_brut_record AS perf_brut, e.nom_epreuve AS ep
    FROM athlete_records ar
    JOIN epreuves e ON e.id_epreuve = ar.id_epreuve
    WHERE ar.id_athlete IN ($idsList)
      AND (ar.performance_record > 0 OR (ar.performance_brut_record IS NOT NULL AND ar.performance_brut_record <> ''))
";
$resPR = $conn->query($_sqlPerfRecs);
if ($resPR) {
    while ($r = $resPR->fetch_assoc()) {
        $aid = (int)$r['id_athlete'];
        $perf = (int)$r['perf'];
        if ($perf <= 0) continue; // la conversion bareme requiert un perfInt > 0
        $ep = $r['ep'] ?: '';
        if ($ep === '') continue;
        $sx = $sexByAth[$aid] ?? 'M';
        $pts = _nomPerfToFfaPts($perf, $ep, $sx);
        if ($pts <= 0) continue;
        $code = _nomFfaPtsToLevel($pts);
        if ($code === '') continue;
        $idx = array_search($code, $hierarchy, true);
        if ($idx === false) continue;
        if (!isset($nivByAth[$aid]) || $idx < $nivByAth[$aid]['idx']) {
            $nivByAth[$aid] = ['idx' => $idx, 'code' => $code, 'pts' => $nivCount - $idx];
        }
    }
}

// Idem sur progressions (souvent contient les meilleures perfs annuelles, indispensable pour IA/IB)
$_sqlPerfProg = "
    SELECT ap.id_athlete, ap.performance_progression AS perf, ap.performance_brut_progression AS perf_brut, e.nom_epreuve AS ep
    FROM athlete_progressions ap
    JOIN epreuves e ON e.id_epreuve = ap.id_epreuve
    WHERE ap.id_athlete IN ($idsList)
      AND ap.performance_progression > 0
";
$resPP = $conn->query($_sqlPerfProg);
if ($resPP) {
    while ($r = $resPP->fetch_assoc()) {
        $aid = (int)$r['id_athlete'];
        $perf = (int)$r['perf'];
        if ($perf <= 0) continue;
        $ep = $r['ep'] ?: '';
        if ($ep === '') continue;
        $sx = $sexByAth[$aid] ?? 'M';
        $pts = _nomPerfToFfaPts($perf, $ep, $sx);
        if ($pts <= 0) continue;
        $code = _nomFfaPtsToLevel($pts);
        if ($code === '') continue;
        $idx = array_search($code, $hierarchy, true);
        if ($idx === false) continue;
        if (!isset($nivByAth[$aid]) || $idx < $nivByAth[$aid]['idx']) {
            $nivByAth[$aid] = ['idx' => $idx, 'code' => $code, 'pts' => $nivCount - $idx];
        }
    }
}

// ---- Batch : club courant (dernier en date) par athlete ----
$clubByAth = [];
$resCl = $conn->query("
    SELECT ac.id_athlete, cl.nom_club, ac.annee_debut, ac.annee_fin
    FROM athlete_clubs ac
    JOIN clubs cl ON cl.id_club = ac.id_club
    WHERE ac.id_athlete IN ($idsList)
    ORDER BY ac.id_athlete, IFNULL(ac.annee_fin, 9999) DESC, ac.annee_debut DESC
");
if ($resCl) {
    while ($r = $resCl->fetch_assoc()) {
        $aid = (int)$r['id_athlete'];
        if (!isset($clubByAth[$aid])) {
            $clubByAth[$aid] = rtrim((string)$r['nom_club'], '* ');
        }
    }
}

// ---- Batch : compteurs records + medailles ----
$recByAth = [];
$resR = $conn->query("
    SELECT id_athlete, COUNT(*) AS n
    FROM athlete_records
    WHERE id_athlete IN ($idsList)
    GROUP BY id_athlete
");
if ($resR) while ($r = $resR->fetch_assoc()) $recByAth[(int)$r['id_athlete']] = (int)$r['n'];

$medByAth = [];
$resM = $conn->query("
    SELECT id_athlete, COUNT(*) AS n
    FROM athlete_medailles
    WHERE id_athlete IN ($idsList)
    GROUP BY id_athlete
");
if ($resM) while ($r = $resM->fetch_assoc()) $medByAth[(int)$r['id_athlete']] = (int)$r['n'];

// ---- Stats : top clubs (toutes periodes confondues) ----
$topClubs = [];
$resTC = $conn->query("
    SELECT cl.nom_club AS nom, COUNT(DISTINCT ac.id_athlete) AS n
    FROM athlete_clubs ac
    JOIN clubs cl ON cl.id_club = ac.id_club
    WHERE ac.id_athlete IN ($idsList)
    GROUP BY cl.id_club, cl.nom_club
    ORDER BY n DESC, cl.nom_club ASC
    LIMIT 15
");
if ($resTC) while ($r = $resTC->fetch_assoc()) $topClubs[] = ['nom' => rtrim($r['nom'], '* '), 'count' => (int)$r['n']];

// ---- Stats : top epreuves (records) ----
$topEpreuves = [];
$resTE = $conn->query("
    SELECT ep.nom_epreuve AS nom, COUNT(DISTINCT ar.id_athlete) AS n
    FROM athlete_records ar
    JOIN epreuves ep ON ep.id_epreuve = ar.id_epreuve
    WHERE ar.id_athlete IN ($idsList)
    GROUP BY ep.id_epreuve, ep.nom_epreuve
    ORDER BY n DESC, ep.nom_epreuve ASC
    LIMIT 15
");
if ($resTE) while ($r = $resTE->fetch_assoc()) $topEpreuves[] = ['nom' => $r['nom'], 'count' => (int)$r['n']];

// ---- Stats : top villes (lieu de naissance) ----
$topVilles = [];
$resTV = $conn->query("
    SELECT vi.nom_ville AS nom, COUNT(*) AS n
    FROM athletes a
    JOIN villes vi ON vi.id_ville = a.id_ville_naissance
    WHERE a.id_athlete IN ($idsList)
    GROUP BY vi.id_ville, vi.nom_ville
    ORDER BY n DESC, vi.nom_ville ASC
    LIMIT 10
");
if ($resTV) while ($r = $resTV->fetch_assoc()) $topVilles[] = ['nom' => $r['nom'], 'count' => (int)$r['n']];

// ================================================================
//  STATS AGREGATIONS — sur TOUS les athletes du nom (pas seulement les 500 affiches)
// ================================================================
// Pourquoi : avant ces stats etaient calculees par iteration PHP sur les 500 athletes
// listes. Quand un nom a plus de 500 athletes (MARTIN, DUPONT, etc), les KPI
// sous-estimaient les comptes et ne correspondaient pas au deep search.
// Maintenant : requetes SQL sur l'ensemble du nom => stats coherentes avec
// le deep search par niveau et le total reel.

$qUpEsc = $conn->real_escape_string($qUp);
$nomWhere = "UPPER(TRIM(a.nom_1_athlete)) = '$qUpEsc' AND a.visible = 1";

// Total reel (sans limite)
$totalReal = $total;
$rTot = $conn->query("SELECT COUNT(*) AS n FROM athletes a WHERE $nomWhere");
if ($rTot && ($rt = $rTot->fetch_assoc())) $totalReal = (int)$rt['n'];

// Repartition par sexe (full)
$bySex = ['M' => 0, 'F' => 0];
$rSx = $conn->query("SELECT sexe_athlete AS sx, COUNT(*) AS n FROM athletes a WHERE $nomWhere GROUP BY sexe_athlete");
if ($rSx) while ($r = $rSx->fetch_assoc()) {
    if ($r['sx'] === 'M' || $r['sx'] === 'F') $bySex[$r['sx']] = (int)$r['n'];
}

// Repartition par categorie (full)
$byCat = [];
$rCt = $conn->query("SELECT categorie_athlete AS cat, COUNT(*) AS n FROM athletes a WHERE $nomWhere AND categorie_athlete <> '' GROUP BY categorie_athlete ORDER BY n DESC");
if ($rCt) while ($r = $rCt->fetch_assoc()) $byCat[$r['cat']] = (int)$r['n'];

// Repartition par nationalite (full)
$byNat = [];
$rNt = $conn->query("SELECT nationalite_athlete AS nat, COUNT(*) AS n FROM athletes a WHERE $nomWhere AND nationalite_athlete <> '' GROUP BY nationalite_athlete ORDER BY n DESC LIMIT 10");
if ($rNt) while ($r = $rNt->fetch_assoc()) $byNat[$r['nat']] = (int)$r['n'];

// Tranches d'age + ages min/max + moyenne (full)
$byAge = ['0-17' => 0, '18-22' => 0, '23-29' => 0, '30-39' => 0, '40-49' => 0, '50-59' => 0, '60+' => 0, 'inconnu' => 0];
$rAg = $conn->query("
    SELECT
        CASE
            WHEN annee_naissance_athlete IS NULL OR annee_naissance_athlete = 0 THEN 'inconnu'
            WHEN $thisYear - annee_naissance_athlete <= 17 THEN '0-17'
            WHEN $thisYear - annee_naissance_athlete <= 22 THEN '18-22'
            WHEN $thisYear - annee_naissance_athlete <= 29 THEN '23-29'
            WHEN $thisYear - annee_naissance_athlete <= 39 THEN '30-39'
            WHEN $thisYear - annee_naissance_athlete <= 49 THEN '40-49'
            WHEN $thisYear - annee_naissance_athlete <= 59 THEN '50-59'
            ELSE '60+'
        END AS tranche,
        COUNT(*) AS n
    FROM athletes a
    WHERE $nomWhere
    GROUP BY tranche
");
if ($rAg) while ($r = $rAg->fetch_assoc()) $byAge[$r['tranche']] = (int)$r['n'];

$anneeMin = null; $anneeMax = null;
$rAY = $conn->query("SELECT MIN(annee_naissance_athlete) AS amin, MAX(annee_naissance_athlete) AS amax, COUNT(annee_naissance_athlete) AS nb FROM athletes a WHERE $nomWhere AND annee_naissance_athlete > 0");
if ($rAY && ($r = $rAY->fetch_assoc())) {
    $anneeMin = $r['amin'] ? (int)$r['amin'] : null;
    $anneeMax = $r['amax'] ? (int)$r['amax'] : null;
}

// Age moyen global + par sexe (full)
$rAm = $conn->query("
    SELECT
        sexe_athlete AS sx,
        AVG($thisYear - annee_naissance_athlete) AS age_moy,
        COUNT(*) AS nb
    FROM athletes a
    WHERE $nomWhere AND annee_naissance_athlete > 0
    GROUP BY sexe_athlete WITH ROLLUP
");
$ageMoy = null; $ageMoyM = null; $ageMoyF = null;
$ageRensCount = 0; $ageRensCountM = 0; $ageRensCountF = 0;
if ($rAm) while ($r = $rAm->fetch_assoc()) {
    $sx = $r['sx'];
    if ($sx === null) { // ROLLUP = total
        $ageMoy = $r['age_moy'] !== null ? round((float)$r['age_moy'], 1) : null;
        $ageRensCount = (int)$r['nb'];
    } elseif ($sx === 'M') {
        $ageMoyM = $r['age_moy'] !== null ? round((float)$r['age_moy'], 1) : null;
        $ageRensCountM = (int)$r['nb'];
    } elseif ($sx === 'F') {
        $ageMoyF = $r['age_moy'] !== null ? round((float)$r['age_moy'], 1) : null;
        $ageRensCountF = (int)$r['nb'];
    }
}

// Repartition niveaux + niveau moyen (full set, 2 sources : athlete_niveaux + athlete_resultats)
// Aligne avec le deep search => les comptes correspondent.
$byNivFam = ['I' => 0, 'N' => 0, 'R' => 0, 'D' => 0, 'non_classe' => 0];
$byNivCodes = []; // ex: ['IA'=>2, 'IB'=>1, 'N1'=>3, ...]
$nivSum = 0; $nivCountAth = 0;
$nivSumM = 0; $nivCountM = 0;
$nivSumF = 0; $nivCountF = 0;
$hL = "'" . implode("','", $hierarchy) . "'";
$rNivAll = $conn->query("
    SELECT
        a.sexe_athlete AS sx,
        abn.best_rk AS rk
    FROM athletes a
    LEFT JOIN (
        SELECT id_athlete, MIN(rk) AS best_rk
        FROM (
            SELECT id_athlete, FIELD(code_niveau, $hL) AS rk
              FROM athlete_niveaux
              WHERE FIELD(code_niveau, $hL) > 0
            UNION ALL
            SELECT id_athlete, FIELD(niveau_resultat, $hL) AS rk
              FROM athlete_resultats
              WHERE niveau_resultat IS NOT NULL AND niveau_resultat <> ''
                AND FIELD(niveau_resultat, $hL) > 0
        ) all_lvls
        GROUP BY id_athlete
    ) abn ON abn.id_athlete = a.id_athlete
    WHERE $nomWhere
");
if ($rNivAll) {
    while ($r = $rNivAll->fetch_assoc()) {
        $rk = $r['rk'] !== null ? (int)$r['rk'] : 0;
        $sx = $r['sx'] ?: '';
        if ($rk <= 0) {
            $byNivFam['non_classe']++;
            continue;
        }
        $code = $hierarchy[$rk - 1] ?? '';
        if ($code === '') { $byNivFam['non_classe']++; continue; }
        $byNivCodes[$code] = ($byNivCodes[$code] ?? 0) + 1;
        $f = $code[0];
        if ($f === 'I')      $byNivFam['I']++;
        elseif ($f === 'N')  $byNivFam['N']++;
        elseif ($f === 'R')  $byNivFam['R']++;
        elseif ($f === 'D')  $byNivFam['D']++;
        else                 $byNivFam['non_classe']++;
        $pts = $nivCount - ($rk - 1); // IA=26, D8=1
        $nivSum += $pts; $nivCountAth++;
        if ($sx === 'M') { $nivSumM += $pts; $nivCountM++; }
        elseif ($sx === 'F') { $nivSumF += $pts; $nivCountF++; }
    }
}

// ---- Top clubs (full) ----
$topClubs = [];
$rTC = $conn->query("
    SELECT cl.nom_club AS nom, COUNT(DISTINCT ac.id_athlete) AS n
    FROM athletes a
    JOIN athlete_clubs ac ON ac.id_athlete = a.id_athlete
    JOIN clubs cl ON cl.id_club = ac.id_club
    WHERE $nomWhere
    GROUP BY cl.id_club, cl.nom_club
    ORDER BY n DESC, cl.nom_club ASC
    LIMIT 15
");
if ($rTC) while ($r = $rTC->fetch_assoc()) $topClubs[] = ['nom' => rtrim($r['nom'], '* '), 'count' => (int)$r['n']];

// ---- Top epreuves (full) ----
$topEpreuves = [];
$rTE = $conn->query("
    SELECT ep.nom_epreuve AS nom, COUNT(DISTINCT ar.id_athlete) AS n
    FROM athletes a
    JOIN athlete_records ar ON ar.id_athlete = a.id_athlete
    JOIN epreuves ep ON ep.id_epreuve = ar.id_epreuve
    WHERE $nomWhere
    GROUP BY ep.id_epreuve, ep.nom_epreuve
    ORDER BY n DESC, ep.nom_epreuve ASC
    LIMIT 15
");
if ($rTE) while ($r = $rTE->fetch_assoc()) $topEpreuves[] = ['nom' => $r['nom'], 'count' => (int)$r['n']];

// ---- Top villes (lieu de naissance, full) ----
$topVilles = [];
$rTV = $conn->query("
    SELECT vi.nom_ville AS nom, COUNT(*) AS n
    FROM athletes a
    JOIN villes vi ON vi.id_ville = a.id_ville_naissance
    WHERE $nomWhere
    GROUP BY vi.id_ville, vi.nom_ville
    ORDER BY n DESC, vi.nom_ville ASC
    LIMIT 10
");
if ($rTV) while ($r = $rTV->fetch_assoc()) $topVilles[] = ['nom' => $r['nom'], 'count' => (int)$r['n']];

// Boucle restante : construit uniquement la liste $outAthletes pour le tableau.
// Les stats sont desormais calculees via SQL ci-dessus sur l'ensemble du nom.
$outAthletes = [];
foreach ($athletes as $a) {
    $aid    = (int)$a['id_athlete'];
    $sx     = $a['sexe_athlete'] ?: '';
    $cat    = $a['categorie_athlete'] ?: '';
    $nat    = $a['nationalite_athlete'] ?: '';
    $an     = $a['annee_naissance_athlete'] ? (int)$a['annee_naissance_athlete'] : null;
    $age    = $an ? ($thisYear - $an) : null;
    $niv    = $nivByAth[$aid]['code'] ?? null;

    if ($detail) {
        $outAthletes[] = [
            'athlete_id_externe' => (int)$a['athlete_id_externe'],
            'nom_complet'        => $a['nom_complet_athlete'],
            'nom_1'              => $a['nom_1_athlete'],
            'nom_2'              => $a['nom_2_athlete'],
            'sexe'               => $sx,
            'categorie'          => $cat,
            'nationalite'        => $nat,
            'annee_naissance'    => $an,
            'age'                => $age,
            'club'               => $clubByAth[$aid] ?? '',
            'meilleur_niveau'    => $niv,
            'nb_records'         => $recByAth[$aid] ?? 0,
            'nb_medailles'       => $medByAth[$aid] ?? 0,
        ];
    }
}

// Tri descendant par categories / nationalites pour affichage propre
arsort($byCat);
arsort($byNat);

// Tri du tableau athletes : meilleur niveau (par index ASC), puis age ASC
if ($detail) {
    usort($outAthletes, function($a, $b) {
        $hier = ['IA','IB','IE','N1','N2','N3','N4','IR','IR1','IR2','IR3','IR4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
        $rA = $a['meilleur_niveau'] ? array_search($a['meilleur_niveau'], $hier, true) : 999;
        $rB = $b['meilleur_niveau'] ? array_search($b['meilleur_niveau'], $hier, true) : 999;
        if ($rA === false) $rA = 999;
        if ($rB === false) $rB = 999;
        if ($rA !== $rB) return $rA - $rB;
        $agA = $a['age'] ?? 999;
        $agB = $b['age'] ?? 999;
        return $agA - $agB;
    });
}

$payload = [
    'success'      => true,
    'nom'          => $nomCanon,
    'total'        => $totalReal, // total reel (full BDD) — KPI cards basent dessus
    'listed_count' => $total,     // nombre d'athletes effectivement listes (max = listLimit)
    'stats'        => [
        'by_sex'              => $bySex,
        'by_categorie'        => $byCat,
        'by_nationalite'      => $byNat,
        'by_age_range'        => $byAge,
        'by_niveau_famille'   => $byNivFam,
        'by_niveau_codes'     => $byNivCodes, // distribution detaillee par code (IA, IB, N1, ...)
        'top_clubs'           => $topClubs,
        'top_epreuves'        => $topEpreuves,
        'top_villes_naissance'=> $topVilles,
        'annee_naissance_min' => $anneeMin,
        'annee_naissance_max' => $anneeMax,
        'age_moyen'           => $ageMoy,
        'age_moyen_M'         => $ageMoyM,
        'age_moyen_F'         => $ageMoyF,
        'age_renseigne_count' => $ageRensCount,
        // Niveau moyen : echelle de points IA=26 ... D8=1. Moyenne, puis re-mappee au code le plus proche.
        'niveau_moyen' => (function() use ($nivSum, $nivCountAth, $hierarchy, $nivCount) {
            if ($nivCountAth === 0) return null;
            $avgPts = $nivSum / $nivCountAth;
            $closestIdx = max(0, min($nivCount - 1, (int)round($nivCount - $avgPts)));
            return [
                'code'        => $hierarchy[$closestIdx],
                'points'      => round($avgPts, 2),
                'points_max'  => $nivCount, // 26
                'rang'        => $closestIdx + 1, // 1 = IA, 26 = D8
                'classes_count' => $nivCountAth, // combien d'athletes ont un niveau
            ];
        })(),
        'niveau_moyen_M' => (function() use ($nivSumM, $nivCountM, $hierarchy, $nivCount) {
            if ($nivCountM === 0) return null;
            $avgPts = $nivSumM / $nivCountM;
            $closestIdx = max(0, min($nivCount - 1, (int)round($nivCount - $avgPts)));
            return ['code' => $hierarchy[$closestIdx], 'points' => round($avgPts, 2), 'classes_count' => $nivCountM];
        })(),
        'niveau_moyen_F' => (function() use ($nivSumF, $nivCountF, $hierarchy, $nivCount) {
            if ($nivCountF === 0) return null;
            $avgPts = $nivSumF / $nivCountF;
            $closestIdx = max(0, min($nivCount - 1, (int)round($nivCount - $avgPts)));
            return ['code' => $hierarchy[$closestIdx], 'points' => round($avgPts, 2), 'classes_count' => $nivCountF];
        })(),
        'truncated'           => $totalReal > $total,
    ],
    'athletes'     => $outAthletes,
    'generated_at' => date('Y-m-d H:i:s'),
];

$out = json_encode($payload, JSON_UNESCAPED_UNICODE);
@file_put_contents($cacheFile, $out);
echo $out;
$conn->close();
