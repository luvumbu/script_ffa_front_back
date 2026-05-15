<?php
/**
 * api/similar.php — Profils similaires par club
 *
 * Usage : api/similar.php?id=809035 (athlete_id_externe)
 * Retourne 5 athletes par club frequente
 */

require_once __DIR__ . '/config.php';

$idExterne = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$idExterne) {
    jsonResponse(['success' => false, 'error' => 'Parametre ?id= requis'], 400);
}

// Mode : "niveau" (defaut, meme niveau exact), "niveau_sup" (niveau superieur),
//        "niveau_inf" (niveau inferieur), "perf" = tri pur par similarite records,
//        "epreuve" = filtre par epreuve
$mode = 'niveau';
if (isset($_GET['mode'])) {
    if ($_GET['mode'] === 'perf') $mode = 'perf';
    elseif ($_GET['mode'] === 'epreuve') $mode = 'epreuve';
    elseif ($_GET['mode'] === 'niveau_sup') $mode = 'niveau_sup';
    elseif ($_GET['mode'] === 'niveau_inf') $mode = 'niveau_inf';
}
$filterEp = ($mode === 'epreuve' && isset($_GET['ep'])) ? (int)$_GET['ep'] : 0;
if ($mode === 'epreuve' && !$filterEp) $mode = 'perf'; // fallback si pas d'ep

// Cache 24h (cle inclut le mode + epreuve)
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheSuffix = $mode . ($filterEp ? '_ep' . $filterEp : '');
$cacheFile = $cacheDir . '/similar_' . $idExterne . '_' . $cacheSuffix . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; $conn->close(); exit; }
}

// 1. Trouver l'id_athlete interne + infos de base
$res = $conn->query("SELECT id_athlete, sexe_athlete, categorie_athlete FROM athletes WHERE athlete_id_externe = $idExterne LIMIT 1");
if (!$res || !$res->num_rows) {
    jsonResponse(['success' => false, 'error' => 'Athlete non trouve']);
}
$athlete = $res->fetch_assoc();
$idInterne = (int)$athlete['id_athlete'];
$sexe = $conn->real_escape_string($athlete['sexe_athlete']);
$categorie = $conn->real_escape_string($athlete['categorie_athlete']);

// 2. Trouver tous les clubs de l'athlete
$clubs = [];
$res = $conn->query("
    SELECT ac.id_club, c.nom_club, ac.annee_debut, ac.annee_fin
    FROM athlete_clubs ac
    JOIN clubs c ON c.id_club = ac.id_club
    WHERE ac.id_athlete = $idInterne
    ORDER BY ac.annee_debut DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $clubs[] = $row;
}

// Pour les modes niveau_sup/niveau_inf, on n'a pas besoin de clubs (recherche globale)
if (empty($clubs) && $mode !== 'niveau_sup' && $mode !== 'niveau_inf') {
    $output = json_encode(['success' => true, 'mode' => $mode, 'clubs' => []], JSON_UNESCAPED_UNICODE);
    @file_put_contents($cacheFile, $output);
    echo $output;
    $conn->close();
    exit;
}

// 3. Helper : detecter epreuve distance (saut/lancer) vs temps (course)
function _isDistanceEvent($nom) {
    return (bool)preg_match('/(poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon|pentathlon)/i', $nom);
}
// Comparer perf : retourne "better", "worse" ou "equal"
function _comparePerf($myPerfInt, $simPerfInt, $epName) {
    if ($myPerfInt <= 0 || $simPerfInt <= 0) return 'equal';
    if ($myPerfInt === $simPerfInt) return 'equal';
    $isDist = _isDistanceEvent($epName);
    if ($isDist) {
        return ($simPerfInt > $myPerfInt) ? 'better' : 'worse';
    } else {
        return ($simPerfInt < $myPerfInt) ? 'better' : 'worse';
    }
}

// 3b. Bareme FFA — conversion perf → points (interpolation lineaire) — sex-aware
$_baremeConfig = @include(__DIR__ . '/../config/bareme_hommes.php');
$_baremeBreakpoints = $_baremeConfig['breakpoints'] ?? [];
$_baremeMapping = $_baremeConfig['mapping'] ?? [];

// Mapping admin (sex-specific)
$_baremeUserMap = [];
$_userMapFile = __DIR__ . '/../logs/.bareme_user_mapping.php';
if (file_exists($_userMapFile)) {
    $_umRaw = @file_get_contents($_userMapFile);
    $_umPos = $_umRaw ? strpos($_umRaw, "\n") : false;
    if ($_umPos !== false) {
        $_um = json_decode(substr($_umRaw, $_umPos + 1), true);
        if (is_array($_um)) {
            foreach ($_um as $ep => $val) {
                if (is_string($val)) $_baremeUserMap[$ep] = ['M' => $val, 'F' => $val];
                elseif (is_array($val)) $_baremeUserMap[$ep] = ['M' => (string)($val['M'] ?? ''), 'F' => (string)($val['F'] ?? '')];
            }
        }
    }
}
// Sexe de la reference (deja decode plus haut)
$_simSx = strtoupper(trim($athlete['sexe_athlete'] ?? 'M'));
if ($_simSx !== 'F') $_simSx = 'M';

function _perfToFfaPoints($perfInt, $epName) {
    global $_baremeBreakpoints, $_baremeMapping, $_baremeUserMap, $_simSx;
    if ($perfInt <= 0) return 0;
    // Priorite : userMap[ep][sexe] > _baremeMapping[ep] > ep
    $bName = '';
    if (isset($_baremeUserMap[$epName][$_simSx]) && $_baremeUserMap[$epName][$_simSx] !== '') {
        $bName = $_baremeUserMap[$epName][$_simSx];
    } elseif (isset($_baremeMapping[$epName]) && is_string($_baremeMapping[$epName]) && $_baremeMapping[$epName] !== '') {
        $bName = $_baremeMapping[$epName];
    } else {
        $bName = $epName;
    }
    $bp = $_baremeBreakpoints[$bName] ?? null;
    if (!$bp || empty($bp)) return 0;

    $isDist = _isDistanceEvent($epName);
    // bp est trie par points desc : [40, perfIA], [35, perfIB], ...
    // Pour temps : perf basse = bon → IA a la perf la plus basse
    // Pour distance : perf haute = bon → IA a la perf la plus haute

    $nbBp = count($bp);
    for ($i = 0; $i < $nbBp; $i++) {
        $pts = $bp[$i][0];
        $perf = $bp[$i][1];
        if ($isDist) {
            // Distance : si perf >= breakpoint → au moins ce niveau
            if ($perfInt >= $perf) {
                if ($i === 0) return $pts; // meilleur que IA
                // Interpoler entre ce niveau et le precedent
                $prevPts = $bp[$i-1][0];
                $prevPerf = $bp[$i-1][1];
                $ratio = ($perfInt - $perf) / max(1, $prevPerf - $perf);
                return $pts + $ratio * ($prevPts - $pts);
            }
        } else {
            // Temps : si perf <= breakpoint → au moins ce niveau
            if ($perfInt <= $perf) {
                if ($i === 0) return $pts; // meilleur que IA
                $prevPts = $bp[$i-1][0];
                $prevPerf = $bp[$i-1][1];
                $ratio = ($perf - $perfInt) / max(1, $perf - $prevPerf);
                return $pts + $ratio * ($prevPts - $pts);
            }
        }
    }
    // Pire que le dernier niveau
    $lastPts = $bp[$nbBp-1][0];
    $lastPerf = $bp[$nbBp-1][1];
    if ($isDist) {
        // En dessous du min distance
        $ratio = $perfInt / max(1, $lastPerf);
        return max(1, $lastPts * $ratio);
    } else {
        // Au dessus du max temps
        $ratio = $lastPerf / max(1, $perfInt);
        return max(1, $lastPts * $ratio);
    }
}

// 3c. Convertir points FFA → code niveau
function _ffaPointsToLevel($pts) {
    if ($pts >= 40) return 'IA';
    if ($pts >= 35) return 'IB';
    if ($pts >= 30) return 'N1';
    if ($pts >= 28) return 'N2';
    if ($pts >= 26) return 'N3';
    if ($pts >= 24) return 'N4';
    if ($pts >= 21) return 'IR1';
    if ($pts >= 20) return 'IR2';
    if ($pts >= 19) return 'IR3';
    if ($pts >= 18) return 'IR4';
    if ($pts >= 15) return 'R1';
    if ($pts >= 14) return 'R2';
    if ($pts >= 13) return 'R3';
    if ($pts >= 12) return 'R4';
    if ($pts >= 11) return 'R5';
    if ($pts >= 10) return 'R6';
    if ($pts >= 8)  return 'D1';
    if ($pts >= 7)  return 'D2';
    if ($pts >= 6)  return 'D3';
    if ($pts >= 5)  return 'D4';
    if ($pts >= 4)  return 'D5';
    return '';
}

// 3d. Calculer le meilleur niveau d'un athlete a partir de ses records
function _calcBestLevel($records, $epNames) {
    $bestPts = 0;
    $bestCode = '';
    foreach ($records as $epId => $perfInt) {
        $epNom = $epNames[$epId] ?? '';
        $pts = _perfToFfaPoints($perfInt, $epNom);
        if ($pts > $bestPts) {
            $bestPts = $pts;
            $code = _ffaPointsToLevel($pts);
            if ($code !== '') $bestCode = $code;
        }
    }
    return $bestCode;
}
// 3e. Calculer aussi l'epreuve qui a donne ce niveau (pour affichage)
function _calcBestLevelEpreuve($records, $epNames) {
    $bestPts = 0;
    $bestEp  = '';
    foreach ($records as $epId => $perfInt) {
        $epNom = $epNames[$epId] ?? '';
        $pts = _perfToFfaPoints($perfInt, $epNom);
        if ($pts > $bestPts) {
            $bestPts = $pts;
            $bestEp  = $epNom;
        }
    }
    return $bestEp;
}

// 4. Hierarchie des niveaux (score numerique)
$nivScores = [
    'IA'=>110,'IB'=>105,
    'IE'=>100,'IR'=>99,
    'IR1'=>95,'IR2'=>94,'IR3'=>93,'IR4'=>92,'IR5'=>91,'IR6'=>90,
    'N1'=>89,'N2'=>88,'N3'=>87,'N4'=>86,
    'R1'=>80,'R2'=>79,'R3'=>78,'R4'=>77,'R5'=>76,'R6'=>75,
    'D1'=>70,'D2'=>69,'D3'=>68,'D4'=>67,'D5'=>66,'D6'=>65,'D7'=>64,'D8'=>63
];

// 4. Niveau le plus eleve de l'athlete courant (BDD d'abord, sinon calcule via bareme)
$myMaxNiv = 0;
$myMaxNivCode = '';
$res = $conn->query("SELECT DISTINCT code_niveau FROM athlete_niveaux WHERE id_athlete = $idInterne");
if ($res) while ($row = $res->fetch_assoc()) {
    $code = $row['code_niveau'];
    $s = $nivScores[$code] ?? 0;
    if ($s > $myMaxNiv) { $myMaxNiv = $s; $myMaxNivCode = $code; }
}
// NOTE: si $myMaxNivCode reste vide, on le calculera apres chargement des records (section 5b)

// 5. Tous les records de l'athlete courant (base du calcul de similarite)
// D'abord athlete_records, si vide fallback athlete_progressions (meilleure perf par epreuve)
$myRecords = []; // id_epreuve => performance (int)
$myEpreuves = [];
$myEpreuvesNoms = []; // pour le select frontend
$myEpNames = []; // id_epreuve => nom (pour detection temps/distance)
$mySource = 'records';
$res = $conn->query("
    SELECT r.id_epreuve, r.performance_record, e.nom_epreuve, r.performance_brut_record
    FROM athlete_records r
    JOIN epreuves e ON e.id_epreuve = r.id_epreuve
    WHERE r.id_athlete = $idInterne AND r.performance_record > 0
    ORDER BY e.nom_epreuve
");
if ($res) while ($row = $res->fetch_assoc()) {
    $epId = (int)$row['id_epreuve'];
    $myEpreuves[] = $epId;
    $myRecords[$epId] = (int)$row['performance_record'];
    $myEpNames[$epId] = $row['nom_epreuve'];
    $myEpreuvesNoms[] = ['id' => $epId, 'nom' => $row['nom_epreuve'], 'perf' => $row['performance_brut_record']];
}
// Fallback : progressions (meilleure perf par epreuve)
if (empty($myRecords)) {
    $mySource = 'progressions';
    $res = $conn->query("
        SELECT p.id_epreuve, MAX(p.performance_progression) AS perf_int, e.nom_epreuve,
               (SELECT pp.performance_brut_progression FROM athlete_progressions pp WHERE pp.id_athlete = $idInterne AND pp.id_epreuve = p.id_epreuve ORDER BY pp.performance_progression DESC LIMIT 1) AS perf_brut
        FROM athlete_progressions p
        JOIN epreuves e ON e.id_epreuve = p.id_epreuve
        WHERE p.id_athlete = $idInterne AND p.performance_progression > 0
        GROUP BY p.id_epreuve, e.nom_epreuve
        ORDER BY e.nom_epreuve
    ");
    if ($res) while ($row = $res->fetch_assoc()) {
        $epId = (int)$row['id_epreuve'];
        $myEpreuves[] = $epId;
        $myRecords[$epId] = (int)$row['perf_int'];
        $myEpNames[$epId] = $row['nom_epreuve'];
        $myEpreuvesNoms[] = ['id' => $epId, 'nom' => $row['nom_epreuve'], 'perf' => $row['perf_brut']];
    }
}

// 5b. Si pas de niveau en BDD, calculer via bareme FFA sur les records
if ($myMaxNivCode === '' && !empty($myRecords)) {
    $myMaxNivCode = _calcBestLevel($myRecords, $myEpNames);
    $myMaxNiv = $nivScores[$myMaxNivCode] ?? 0;
}

// === BRANCHE SPECIALE niveau_sup / niveau_inf : recherche globale ===
// Pas restreint aux clubs de l'athlete (sinon on trouve rarement des athletes
// de niveau superieur dans le meme club).
if ($mode === 'niveau_sup' || $mode === 'niveau_inf') {
    $hierarchy = ['IA','IB','IE','IR','IR1','IR2','IR3','IR4','IR5','IR6','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
    // Si le code BDD n'est pas dans la hierarchie connue, recalculer via bareme FFA
    if ($myMaxNivCode !== '' && array_search($myMaxNivCode, $hierarchy) === false && !empty($myRecords)) {
        $_calc = _calcBestLevel($myRecords, $myEpNames);
        if ($_calc !== '' && array_search($_calc, $hierarchy) !== false) {
            $myMaxNivCode = $_calc;
            $myMaxNiv = $nivScores[$_calc] ?? 0;
        }
    }
    $myIdx = array_search($myMaxNivCode, $hierarchy);

    // Determiner les codes niveau cibles
    $targetCodes = [];
    if ($mode === 'niveau_sup') {
        // Niveau superieur : UNIQUEMENT le niveau juste au-dessus (ex: D2 -> D1)
        if ($myIdx !== false && $myIdx > 0) {
            $targetCodes = [$hierarchy[$myIdx - 1]];
        } elseif ($myIdx === 0 || $myMaxNivCode === 'IA') {
            // Athlete IA : pas de niveau plus haut → fallback meme niveau (IA) avec meilleure perf
            $targetCodes = ['IA'];
        }
    } else { // niveau_inf : UNIQUEMENT le niveau juste en-dessous (ex: D2 -> D3)
        if ($myIdx !== false && $myIdx < count($hierarchy) - 1) {
            $targetCodes = [$hierarchy[$myIdx + 1]];
        }
    }

    if (!empty($targetCodes)) {
        $codesEsc = "'" . implode("','", array_map(function($c) use ($conn){ return $conn->real_escape_string($c); }, $targetCodes)) . "'";
        // Recherche globale : on prend le PLUS HAUT niveau de chaque athlete (sur TOUS ses codes),
        // PUIS on filtre sur le niveau cible. Sinon un IA avec une vieille annee IB serait classe IB.
        $globalSql = "SELECT a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete,
                             a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete,
                             best_niv.code_niveau AS best_niv_code
                      FROM athletes a
                      INNER JOIN (
                          SELECT n.id_athlete,
                                 SUBSTRING_INDEX(GROUP_CONCAT(n.code_niveau ORDER BY FIELD(n.code_niveau, '" . implode("','", $hierarchy) . "') ASC), ',', 1) AS code_niveau
                          FROM athlete_niveaux n
                          GROUP BY n.id_athlete
                      ) best_niv ON best_niv.id_athlete = a.id_athlete
                      WHERE a.visible = 1
                        AND a.sexe_athlete = '$sexe'
                        AND a.id_athlete != $idInterne
                        AND best_niv.code_niveau IN ($codesEsc)
                      ORDER BY FIELD(best_niv.code_niveau, '" . implode("','", $hierarchy) . "') " . ($mode === 'niveau_sup' ? 'ASC' : 'DESC') . "
                      LIMIT 60";
        $globalRes = $conn->query($globalSql);
        $globalCands = [];
        if ($globalRes) while ($row = $globalRes->fetch_assoc()) {
            $row['_bestNiv'] = $row['best_niv_code'];
            $row['_bestNivFromDb'] = true;
            $globalCands[] = $row;
        }

        // Recuperer les records des candidats pour calculer la similarite
        if (!empty($globalCands)) {
            $candIdsList = implode(',', array_map(function($c) { return (int)$c['id_athlete']; }, $globalCands));
            $allRecords = []; // id_athlete => [id_epreuve => ['perf_int','perf_brut','annee','nom']]
            $rRes = $conn->query("
                SELECT r.id_athlete, r.id_epreuve, r.performance_record AS perf_int,
                       r.performance_brut_record AS perf_brut, YEAR(r.date_record) AS annee,
                       e.nom_epreuve AS nom
                FROM athlete_records r
                JOIN epreuves e ON e.id_epreuve = r.id_epreuve
                WHERE r.id_athlete IN ($candIdsList) AND r.performance_record > 0
            ");
            if ($rRes) while ($r = $rRes->fetch_assoc()) {
                $aid = (int)$r['id_athlete'];
                $eid = (int)$r['id_epreuve'];
                $allRecords[$aid][$eid] = ['perf_int'=>(int)$r['perf_int'], 'perf_brut'=>$r['perf_brut'], 'annee'=>(int)$r['annee'], 'nom'=>$r['nom']];
            }

            // Recuperer le club actuel de chaque candidat (pour le grouping)
            $clubsByAth = [];
            $cRes = $conn->query("
                SELECT ac.id_athlete, c.nom_club
                FROM athlete_clubs ac
                JOIN clubs c ON c.id_club = ac.id_club
                WHERE ac.id_athlete IN ($candIdsList)
                ORDER BY ac.id_athlete, COALESCE(ac.annee_fin, 9999) DESC, ac.annee_debut DESC
            ");
            if ($cRes) while ($cr = $cRes->fetch_assoc()) {
                $aid = (int)$cr['id_athlete'];
                if (!isset($clubsByAth[$aid])) $clubsByAth[$aid] = $cr['nom_club'];
            }

            // Construire les athletes avec similarite
            $globalAths = [];
            foreach ($globalCands as $row) {
                $simId = (int)$row['id_athlete'];
                $simRecords = $allRecords[$simId] ?? [];
                $allEp = [];
                $bestEp = null; $bestEpScore = 0;
                foreach ($simRecords as $epId => $ep) {
                    $commun = in_array($epId, $myEpreuves, true);
                    $cmp = 'equal';
                    if ($commun && isset($myRecords[$epId]) && $myRecords[$epId] > 0 && $ep['perf_int'] > 0) {
                        $epNom = $myEpNames[$epId] ?? $ep['nom'];
                        $cmp = _comparePerf($myRecords[$epId], $ep['perf_int'], $epNom);
                    }
                    $allEp[] = [
                        'epreuve' => $ep['nom'],
                        'perf'    => $ep['perf_brut'],
                        'commun'  => $commun,
                        'cmp'     => $cmp,
                    ];
                    if ($commun && $bestEpScore < 2) { $bestEp = $ep['nom']; $bestEpScore = 2; }
                    elseif (!$bestEp) { $bestEp = $ep['nom']; }
                }
                // Score similarite
                $cIdx = array_search($row['_bestNiv'], $hierarchy);
                $diff = ($cIdx !== false && $myIdx !== false) ? abs($cIdx - $myIdx) : 99;
                $similarite = max(20, 100 - $diff * 8);

                // Cas special : athlete IA en mode niveau_sup
                // → bonus si ce candidat IA bat l'utilisateur sur au moins une epreuve commune
                if ($myMaxNivCode === 'IA' && $mode === 'niveau_sup') {
                    $beatsUser = false;
                    $hasCommun = false;
                    foreach ($simRecords as $epId => $ep) {
                        if (in_array($epId, $myEpreuves, true) && isset($myRecords[$epId]) && $myRecords[$epId] > 0 && $ep['perf_int'] > 0) {
                            $hasCommun = true;
                            $epNom = $myEpNames[$epId] ?? $ep['nom'];
                            $cmp = _comparePerf($myRecords[$epId], $ep['perf_int'], $epNom);
                            if ($cmp === 'better') { $beatsUser = true; break; }
                        }
                    }
                    // Ne garder que les athletes IA qui le battent sur une epreuve commune
                    if (!$beatsUser) continue;
                    $similarite = 95;
                }

                $globalAths[] = [
                    'athlete_id'     => (int)$row['athlete_id_externe'],
                    'nom_complet'    => $row['nom_complet_athlete'],
                    'categorie'      => $row['categorie_athlete'],
                    'sexe'           => $row['sexe_athlete'],
                    'nationalite'    => $row['nationalite_athlete'],
                    'niveau'         => $row['_bestNiv'],
                    'niveau_ep'      => $bestEp,
                    'epreuves'       => $allEp,
                    'similarite'     => $similarite,
                    '_clubNom'       => $clubsByAth[$simId] ?? '—',
                ];
            }
            // Tri : meilleur niveau en premier (closer to user)
            usort($globalAths, function($a, $b) { return $b['similarite'] - $a['similarite']; });
            $globalAths = array_slice($globalAths, 0, 30);

            // Grouper par club (au moins virtuellement pour le format de retour)
            $clubGroups = [];
            foreach ($globalAths as $a) {
                $cn = $a['_clubNom'];
                if (!isset($clubGroups[$cn])) $clubGroups[$cn] = [];
                unset($a['_clubNom']);
                $clubGroups[$cn][] = $a;
            }
            foreach ($clubGroups as $cn => $list) {
                $result[] = ['club_nom' => $cn, 'athletes' => $list];
            }
        }
    }

    // Output et exit (skip la boucle clubs ci-dessous)
    $output = json_encode([
        'success'      => true,
        'mode'         => $mode,
        'mon_niveau'   => $myMaxNivCode,
        'mes_epreuves' => $myEpreuvesNoms,
        'clubs'        => $result,
    ], JSON_UNESCAPED_UNICODE);
    @file_put_contents($cacheFile, $output);
    echo $output;
    $conn->close();
    exit;
}

// 6. Pour chaque club, trouver les athletes similaires
$result = [];
foreach ($clubs as $club) {
    $clubId = (int)$club['id_club'];
    $clubNom = $club['nom_club'];

    if ($mode === 'epreuve' && $filterEp > 0) {
        // MODE EPREUVE : requete legere — cherche dans records ET progressions
        $myEpPerf = $myRecords[$filterEp] ?? 0;
        $res = $conn->query("
            SELECT a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete, a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete,
                   COALESCE(ar_ep.performance_record, best_prog.best_perf) AS ep_perf_int
            FROM athlete_clubs ac2
            JOIN athletes a ON a.id_athlete = ac2.id_athlete
            LEFT JOIN athlete_records ar_ep ON ar_ep.id_athlete = a.id_athlete AND ar_ep.id_epreuve = $filterEp AND ar_ep.performance_record > 0
            LEFT JOIN (
                SELECT id_athlete, MAX(performance_progression) AS best_perf
                FROM athlete_progressions
                WHERE id_epreuve = $filterEp AND performance_progression > 0
                GROUP BY id_athlete
            ) best_prog ON best_prog.id_athlete = a.id_athlete
            WHERE ac2.id_club = $clubId
              AND ac2.id_athlete != $idInterne
              AND a.visible = 1
              AND a.sexe_athlete = '$sexe'
              AND (ar_ep.performance_record IS NOT NULL OR best_prog.best_perf IS NOT NULL)
        ");
        $candidates = [];
        if ($res) while ($row = $res->fetch_assoc()) {
            $row['_nivDiff'] = 0;
            $row['_bestNiv'] = '';
            $row['niveaux'] = '';
            $candidates[] = $row;
        }
        // Trier par proximite de perf sur l'epreuve (meme sexe deja filtre en SQL)
        if ($myEpPerf > 0) {
            usort($candidates, function($a, $b) use ($myEpPerf) {
                return abs($myEpPerf - (int)$a['ep_perf_int']) - abs($myEpPerf - (int)$b['ep_perf_int']);
            });
        }
        $candidates = array_slice($candidates, 0, 30);

        // Batch-load niveaux seulement pour les finalistes
        if (!empty($candidates)) {
            $finIds = implode(',', array_map(function($c) { return (int)$c['id_athlete']; }, $candidates));
            $nivRes = $conn->query("SELECT id_athlete, GROUP_CONCAT(DISTINCT code_niveau) AS niveaux FROM athlete_niveaux WHERE id_athlete IN ($finIds) GROUP BY id_athlete");
            $nivMap = [];
            if ($nivRes) while ($nr = $nivRes->fetch_assoc()) $nivMap[(int)$nr['id_athlete']] = $nr['niveaux'];
            foreach ($candidates as &$c) {
                $c['niveaux'] = $nivMap[(int)$c['id_athlete']] ?? '';
                $bestNiv = 0; $bestNivCode = '';
                if ($c['niveaux']) {
                    foreach (explode(',', $c['niveaux']) as $code) {
                        $s = $nivScores[trim($code)] ?? 0;
                        if ($s > $bestNiv) { $bestNiv = $s; $bestNivCode = trim($code); }
                    }
                }
                // Si pas de niveau BDD, calculer via bareme sur la perf de l'epreuve
                if ($bestNivCode === '' && isset($c['ep_perf_int']) && (int)$c['ep_perf_int'] > 0) {
                    $pts = _perfToFfaPoints((int)$c['ep_perf_int'], $epName);
                    if ($pts > 0) $bestNivCode = _ffaPointsToLevel($pts);
                }
                $c['_bestNiv'] = $bestNivCode;
            }
            unset($c);
        }
    } else {
        // MODE NIVEAU / PERF : requete avec sous-requete niveaux (candidats limites)
        $res = $conn->query("
            SELECT a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete, a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete,
                   (SELECT GROUP_CONCAT(DISTINCT n.code_niveau) FROM athlete_niveaux n WHERE n.id_athlete = a.id_athlete) AS niveaux
            FROM athlete_clubs ac2
            JOIN athletes a ON a.id_athlete = ac2.id_athlete
            WHERE ac2.id_club = $clubId
              AND ac2.id_athlete != $idInterne
              AND a.visible = 1
              AND a.sexe_athlete = '$sexe'
        ");
        $candidates = [];
        if ($res) while ($row = $res->fetch_assoc()) {
            $bestNiv = 0; $bestNivCode = '';
            if ($row['niveaux']) {
                foreach (explode(',', $row['niveaux']) as $code) {
                    $s = $nivScores[trim($code)] ?? 0;
                    if ($s > $bestNiv) { $bestNiv = $s; $bestNivCode = trim($code); }
                }
            }
            $row['_nivDiff'] = abs($myMaxNiv - $bestNiv);
            $row['_bestNiv'] = $bestNivCode;
            $row['_bestNivFromDb'] = ($bestNivCode !== ''); // flag: niveau vient de la BDD ou pas
            $candidates[] = $row;
        }
        if ($mode === 'perf') {
            // MODE PERF : garder tous les candidats (meme sexe deja filtre en SQL)
            $candidates = array_slice($candidates, 0, 50);
        } else {
            // MODE NIVEAU / NIVEAU_SUP / NIVEAU_INF : filtre selon hierarchie
            if ($myMaxNivCode === '') {
                $candidates = [];
            } else {
                // Hierarchie ordonnee (rang 0 = meilleur)
                $_hierarchy = ['IA','IB','IE','IR','IR1','IR2','IR3','IR4','IR5','IR6','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
                $myIdx = array_search($myMaxNivCode, $_hierarchy);
                if ($myIdx === false) {
                    $candidates = [];
                } else {
                    $candidates = array_values(array_filter($candidates, function($c) use ($myMaxNivCode, $myIdx, $_hierarchy, $mode) {
                        if ($mode === 'niveau') {
                            return $c['_bestNiv'] === $myMaxNivCode;
                        }
                        $cIdx = array_search($c['_bestNiv'], $_hierarchy);
                        if ($cIdx === false) return false;
                        if ($mode === 'niveau_sup') return $cIdx < $myIdx; // niveau plus haut
                        if ($mode === 'niveau_inf') return $cIdx > $myIdx; // niveau plus bas
                        return false;
                    }));
                }
            }
        }
    }

    if (empty($candidates)) continue;
    $candIds = array_map(function($c) { return (int)$c['id_athlete']; }, $candidates);
    $candIdsList = implode(',', $candIds);

    if ($mode === 'epreuve' && $filterEp > 0) {
        // MODE EPREUVE : on ne compare que sur l'epreuve selectionnee
        // Charger la perf de l'epreuve depuis records OU progressions
        $epPerfs = []; // id_athlete => {perf, perf_int, annee}
        $epName = '';
        // D'abord records
        $batchRes = $conn->query("
            SELECT r.id_athlete, e.nom_epreuve,
                   r.performance_brut_record AS performance,
                   r.performance_record AS perf_int,
                   YEAR(r.date_record) AS annee
            FROM athlete_records r
            JOIN epreuves e ON e.id_epreuve = r.id_epreuve
            WHERE r.id_athlete IN ($candIdsList)
              AND r.id_epreuve = $filterEp
              AND r.performance_record > 0
        ");
        if ($batchRes) while ($br = $batchRes->fetch_assoc()) {
            $epName = $br['nom_epreuve'];
            $epPerfs[(int)$br['id_athlete']] = [
                'perf' => $br['performance'],
                'perf_int' => (int)$br['perf_int'],
                'annee' => $br['annee'] ? (int)$br['annee'] : null,
            ];
        }
        // Completer avec progressions pour ceux qui n'ont pas de record
        $missingIds = array_diff($candIds, array_keys($epPerfs));
        if (!empty($missingIds)) {
            $missList = implode(',', $missingIds);
            $batchRes = $conn->query("
                SELECT p.id_athlete, e.nom_epreuve,
                       p.performance_brut_progression AS performance,
                       p.performance_progression AS perf_int,
                       p.annee_progression AS annee
                FROM athlete_progressions p
                JOIN epreuves e ON e.id_epreuve = p.id_epreuve
                WHERE p.id_athlete IN ($missList)
                  AND p.id_epreuve = $filterEp
                  AND p.performance_progression > 0
                ORDER BY p.performance_progression DESC
            ");
            if ($batchRes) while ($br = $batchRes->fetch_assoc()) {
                $aid = (int)$br['id_athlete'];
                if (isset($epPerfs[$aid])) continue; // garder la meilleure (1ere = DESC)
                $epName = $br['nom_epreuve'];
                $epPerfs[$aid] = [
                    'perf' => $br['performance'],
                    'perf_int' => (int)$br['perf_int'],
                    'annee' => $br['annee'] ? (int)$br['annee'] : null,
                ];
            }
        }
        // Nom epreuve fallback
        if (!$epName) {
            $nr = $conn->query("SELECT nom_epreuve FROM epreuves WHERE id_epreuve = $filterEp LIMIT 1");
            if ($nr && $r = $nr->fetch_assoc()) $epName = $r['nom_epreuve'];
        }
        $myEpPerf = $myRecords[$filterEp] ?? 0;
        // Ma perf brute pour l'affichage
        $myEpBrut = '';
        foreach ($myEpreuvesNoms as $mep) {
            if ($mep['id'] === $filterEp) { $myEpBrut = $mep['perf']; break; }
        }

        $athletes = [];
        foreach ($candidates as $row) {
            $simId = (int)$row['id_athlete'];
            $ep = $epPerfs[$simId] ?? null;
            if (!$ep || $ep['perf_int'] <= 0) continue;

            // % via points FFA sur l'epreuve choisie
            $similarite = 0;
            if ($myEpPerf > 0) {
                $myPts = _perfToFfaPoints($myEpPerf, $epName);
                $simPts = _perfToFfaPoints($ep['perf_int'], $epName);
                if ($myPts > 0 && $simPts > 0) {
                    $maxPts = max($myPts, $simPts);
                    $similarite = round((1 - abs($myPts - $simPts) / $maxPts) * 100);
                    if ($similarite < 0) $similarite = 0;
                } else {
                    $similarite = round(min($myEpPerf, $ep['perf_int']) / max($myEpPerf, $ep['perf_int']) * 100);
                }
            }

            $athletes[] = [
                'athlete_id'  => (int)$row['athlete_id_externe'],
                'nom_complet' => $row['nom_complet_athlete'],
                'sexe'        => $row['sexe_athlete'],
                'categorie'   => $row['categorie_athlete'],
                'nationalite' => $row['nationalite_athlete'],
                'niveau'      => $row['_bestNiv'],
                'niveau_ep'   => $row['_bestNivEp'] ?? '',
                'similarite'  => $similarite,
                'epreuves'    => [
                    ['epreuve' => $epName, 'perf' => $ep['perf'], 'annee' => $ep['annee'], 'commun' => true, 'cmp' => _comparePerf($myEpPerf, $ep['perf_int'], $epName)],
                ],
            ];
        }
    } else {
        // MODE NIVEAU / PERF : charger tous les records, calcul multi-epreuves
        $allRecords = [];
        // Records
        $batchRes = $conn->query("
            SELECT r.id_athlete, e.nom_epreuve, e.id_epreuve,
                   r.performance_brut_record AS performance,
                   r.performance_record AS perf_int,
                   YEAR(r.date_record) AS annee
            FROM athlete_records r
            JOIN epreuves e ON e.id_epreuve = r.id_epreuve
            WHERE r.id_athlete IN ($candIdsList)
              AND r.performance_record > 0
            ORDER BY e.nom_epreuve
        ");
        if ($batchRes) while ($br = $batchRes->fetch_assoc()) {
            $aid = (int)$br['id_athlete'];
            if (!isset($allRecords[$aid])) $allRecords[$aid] = [];
            $allRecords[$aid][(int)$br['id_epreuve']] = [
                'nom' => $br['nom_epreuve'],
                'perf' => $br['performance'],
                'perf_int' => (int)$br['perf_int'],
                'annee' => $br['annee'] ? (int)$br['annee'] : null,
            ];
        }
        // Completer avec progressions (meilleure perf par athlete+epreuve)
        // ORDER BY perf DESC → le continue garde la meilleure perf par athlete+epreuve
        $batchRes = $conn->query("
            SELECT p.id_athlete, e.nom_epreuve, e.id_epreuve,
                   p.performance_brut_progression AS performance,
                   p.performance_progression AS perf_int,
                   p.annee_progression AS annee
            FROM athlete_progressions p
            JOIN epreuves e ON e.id_epreuve = p.id_epreuve
            WHERE p.id_athlete IN ($candIdsList)
              AND p.performance_progression > 0
            ORDER BY p.performance_progression DESC
        ");
        if ($batchRes) while ($br = $batchRes->fetch_assoc()) {
            $aid = (int)$br['id_athlete'];
            $epId = (int)$br['id_epreuve'];
            // Ne pas ecraser un record existant ou une meilleure progression deja trouvee
            if (isset($allRecords[$aid][$epId])) continue;
            if (!isset($allRecords[$aid])) $allRecords[$aid] = [];
            $allRecords[$aid][$epId] = [
                'nom' => $br['nom_epreuve'],
                'perf' => $br['performance'],
                'perf_int' => (int)$br['perf_int'],
                'annee' => $br['annee'] ? (int)$br['annee'] : null,
            ];
        }

        // Calculer le PLUS HAUT niveau (BDD + bareme FFA) pour chaque candidat + epreuve source
        foreach ($candidates as &$_c) {
            $_c['_bestNivEp'] = '';
            if (!empty($allRecords[(int)$_c['id_athlete']])) {
                $simRecs = $allRecords[(int)$_c['id_athlete']];
                $simEpNames = [];
                foreach ($simRecs as $_eid => $_er) $simEpNames[$_eid] = $_er['nom'];
                $simRecsInt = [];
                foreach ($simRecs as $_eid => $_er) $simRecsInt[$_eid] = $_er['perf_int'];
                $calcNiv = _calcBestLevel($simRecsInt, $simEpNames);
                $calcEp  = _calcBestLevelEpreuve($simRecsInt, $simEpNames);
                // Toujours associer l'epreuve la plus forte du palmares (meilleure perf en points FFA)
                if ($calcEp) $_c['_bestNivEp'] = $calcEp;
                if ($calcNiv !== '') {
                    $calcScore    = $nivScores[$calcNiv] ?? 0;
                    $currentScore = $nivScores[$_c['_bestNiv']] ?? 0;
                    if ($calcScore > $currentScore) {
                        $_c['_bestNiv']   = $calcNiv;
                        $_c['_nivDiff']   = abs($myMaxNiv - $calcScore);
                    }
                }
            }
        }
        unset($_c);

        // Re-filtrer mode niveau apres calcul des niveaux manquants
        if ($mode !== 'perf' && $mode !== 'epreuve' && $myMaxNivCode !== '') {
            $_hierarchy = ['IA','IB','IE','IR','IR1','IR2','IR3','IR4','IR5','IR6','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
            $myIdx = array_search($myMaxNivCode, $_hierarchy);
            $candidates = array_values(array_filter($candidates, function($c) use ($myMaxNivCode, $myIdx, $_hierarchy, $mode) {
                if ($mode === 'niveau') return $c['_bestNiv'] === $myMaxNivCode;
                if ($myIdx === false) return false;
                $cIdx = array_search($c['_bestNiv'], $_hierarchy);
                if ($cIdx === false) return false;
                if ($mode === 'niveau_sup') return $cIdx < $myIdx;
                if ($mode === 'niveau_inf') return $cIdx > $myIdx;
                return false;
            }));
            if (empty($candidates)) continue;
            $candIds = array_map(function($c) { return (int)$c['id_athlete']; }, $candidates);
            $candIdsList = implode(',', $candIds);
        }

        $athletes = [];
        foreach ($candidates as $row) {
            $simId = (int)$row['id_athlete'];
            $simRecords = $allRecords[$simId] ?? [];

            $allEp = [];
            foreach ($simRecords as $epId => $ep) {
                $commun = in_array($epId, $myEpreuves, true);
                $score = 0;
                if ($commun && isset($myRecords[$epId]) && $myRecords[$epId] > 0 && $ep['perf_int'] > 0) {
                    $score = 2 + min($myRecords[$epId], $ep['perf_int']) / max($myRecords[$epId], $ep['perf_int']);
                } elseif ($commun) {
                    $score = 1;
                }
                $cmp = 'equal';
                if ($commun && isset($myRecords[$epId]) && $myRecords[$epId] > 0 && $ep['perf_int'] > 0) {
                    $epNom = $myEpNames[$epId] ?? $ep['nom'];
                    $cmp = _comparePerf($myRecords[$epId], $ep['perf_int'], $epNom);
                }
                $allEp[] = [
                    'epreuve' => $ep['nom'], 'perf' => $ep['perf'],
                    'annee' => $ep['annee'], 'commun' => $commun, 'cmp' => $cmp, '_score' => $score,
                ];
            }
            usort($allEp, function($a, $b) { return $b['_score'] <=> $a['_score']; });
            $epreuves = [];
            foreach (array_slice($allEp, 0, 3) as $ep) {
                $epreuves[] = ['epreuve' => $ep['epreuve'], 'perf' => $ep['perf'], 'annee' => $ep['annee'], 'commun' => $ep['commun'], 'cmp' => $ep['cmp']];
            }

            $nbCommun = 0; $totalPtsDiff = 0; $totalMaxPts = 0;
            foreach ($myRecords as $epId => $myPerf) {
                $simPerf = $simRecords[$epId]['perf_int'] ?? 0;
                if ($simPerf > 0 && $myPerf > 0) {
                    $epNom = $myEpNames[$epId] ?? '';
                    $myPts = _perfToFfaPoints($myPerf, $epNom);
                    $simPts = _perfToFfaPoints($simPerf, $epNom);
                    if ($myPts > 0 && $simPts > 0) {
                        $totalPtsDiff += abs($myPts - $simPts);
                        $totalMaxPts += max($myPts, $simPts);
                        $nbCommun++;
                    } else {
                        // Fallback ratio min/max si pas dans le bareme
                        $totalPtsDiff += 0;
                        $totalMaxPts += 1;
                        $r = min($myPerf, $simPerf) / max($myPerf, $simPerf);
                        $totalPtsDiff += (1 - $r);
                        $totalMaxPts += 1;
                        $nbCommun++;
                    }
                }
            }
            $similarite = 0;
            if ($nbCommun > 0 && $totalMaxPts > 0) {
                // Similarite = 1 - (ecart moyen en points / max moyen en points)
                $similarite = round((1 - $totalPtsDiff / $totalMaxPts) * 100);
                if ($similarite < 0) $similarite = 0;
                if ($mode !== 'perf') {
                    // Mode Niveau : penalite si peu d'epreuves en commun
                    $nbMax = max(count($myRecords), count($simRecords));
                    $similarite = ($nbMax > 0) ? round($similarite * ($nbCommun / $nbMax)) : 0;
                }
            }

            $athletes[] = [
                'athlete_id'  => (int)$row['athlete_id_externe'],
                'nom_complet' => $row['nom_complet_athlete'],
                'sexe'        => $row['sexe_athlete'],
                'categorie'   => $row['categorie_athlete'],
                'nationalite' => $row['nationalite_athlete'],
                'niveau'      => $row['_bestNiv'],
                'niveau_ep'   => $row['_bestNivEp'] ?? '',
                'similarite'  => $similarite,
                'epreuves'    => $epreuves,
            ];
        }
    }

    // Trier par % decroissant, garder les 20 meilleurs (meme sexe deja filtre en SQL)
    usort($athletes, function($a, $b) { return $b['similarite'] - $a['similarite']; });
    $athletes = array_slice($athletes, 0, 20);

    if (!empty($athletes)) {
        $result[] = [
            'club_nom'  => $clubNom,
            'club_id'   => $clubId,
            'athletes'  => $athletes,
        ];
    }
}

$output = json_encode(['success' => true, 'mode' => $mode, 'mon_niveau' => $myMaxNivCode, 'mes_epreuves' => $myEpreuvesNoms, 'clubs' => $result], JSON_UNESCAPED_UNICODE);
@file_put_contents($cacheFile, $output);
echo $output;
$conn->close();
