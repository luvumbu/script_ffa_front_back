<?php
/**
 * api/athlete.php — Recupere TOUTES les donnees d'un athlete
 *
 * Usage :
 *   api/athlete.php?id=26134          (par athlete_id_externe = ID athle.fr)
 *   api/athlete.php?id_athlete=5      (par id_athlete interne)
 *   api/athlete.php?licence=1234567   (par numero de licence)
 */

require_once __DIR__ . '/config.php';

// Bareme FFA pour calculer les niveaux manquants
$_baremeConfig = @include(__DIR__ . '/../config/bareme_hommes.php');
$_baremeBreakpoints = $_baremeConfig['breakpoints'] ?? [];
$_baremeMapping = $_baremeConfig['mapping'] ?? [];

// Mapping utilisateur (admin) — par sexe (M/F)
// Ancien format : ['ep' => 'bareme_name'] ; Nouveau format : ['ep' => ['M' => 'name', 'F' => 'name']]
$_baremeUserMap = [];
$_userMapFile = __DIR__ . '/../logs/.bareme_user_mapping.php';
if (file_exists($_userMapFile)) {
    $_umRaw = @file_get_contents($_userMapFile);
    $_umPos = $_umRaw ? strpos($_umRaw, "\n") : false;
    if ($_umPos !== false) {
        $_um = json_decode(substr($_umRaw, $_umPos + 1), true);
        if (is_array($_um)) {
            foreach ($_um as $ep => $val) {
                if (is_string($val)) {
                    $_baremeUserMap[$ep] = ['M' => $val, 'F' => $val];
                } elseif (is_array($val)) {
                    $_baremeUserMap[$ep] = ['M' => (string)($val['M'] ?? ''), 'F' => (string)($val['F'] ?? '')];
                }
            }
        }
    }
}

function _isDistEp($nom) {
    return (bool)preg_match('/(poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon|pentathlon)/i', $nom);
}

function _perfToFfaPts($perfInt, $epName, $sexe = 'M') {
    global $_baremeBreakpoints, $_baremeMapping, $_baremeUserMap;
    if ($perfInt <= 0) return 0;
    $sx = ($sexe === 'F') ? 'F' : 'M';
    // Priorite : userMap[ep][sexe] > _baremeMapping[ep] > ep
    $bName = '';
    if (isset($_baremeUserMap[$epName][$sx]) && $_baremeUserMap[$epName][$sx] !== '') {
        $bName = $_baremeUserMap[$epName][$sx];
    } elseif (isset($_baremeMapping[$epName]) && is_string($_baremeMapping[$epName]) && $_baremeMapping[$epName] !== '') {
        $bName = $_baremeMapping[$epName];
    } else {
        $bName = $epName;
    }
    $bp = $_baremeBreakpoints[$bName] ?? null;
    if (!$bp || empty($bp)) return 0;
    $isDist = _isDistEp($epName);
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
    else return max(1, $lastPts * $lastPerf / max(1, $perfInt));
}

function _ffaPtsToLevel($pts) {
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

// Parser une performance brute en centiemes/centimetres (meme logique que AthleteScraper::performanceToInt)
function _parsePerfBrut($p) {
    $p = trim($p);
    if ($p === '' || $p === '0') return 0;
    // Xh YY'ZZ''CC  ex: 2h43'44''00
    if (preg_match('/^(\d+)h\s*(\d+)[\'′](\d+)[\'\'″]{0,2}(\d*)$/', $p, $m)) {
        $c = $m[4] !== '' ? (int)str_pad($m[4], 2, '0', STR_PAD_RIGHT) : 0;
        return ((int)$m[1]*3600 + (int)$m[2]*60 + (int)$m[3]) * 100 + $c;
    }
    // X'YY''CC  ex: 3'01''49
    if (preg_match('/^(\d+)[\'′](\d+)[\'\'″]{1,2}(\d*)$/', $p, $m)) {
        $c = $m[3] !== '' ? (int)str_pad($m[3], 2, '0', STR_PAD_RIGHT) : 0;
        return ((int)$m[1]*60 + (int)$m[2]) * 100 + $c;
    }
    // X'YY  ex: 35'18
    if (preg_match('/^(\d+)[\'′](\d+)$/', $p, $m)) {
        return ((int)$m[1]*60 + (int)$m[2]) * 100;
    }
    // XX''CC  ex: 10''48
    if (preg_match('/^(\d+)[\'\'″]{1,2}(\d+)$/', $p, $m)) {
        return (int)$m[1]*100 + (int)str_pad($m[2], 2, '0', STR_PAD_RIGHT);
    }
    // XmYY  ex: 6m30
    if (preg_match('/^(\d+)m(\d+)$/', $p, $m)) {
        return (int)$m[1]*100 + (int)str_pad($m[2], 2, '0', STR_PAD_RIGHT);
    }
    // X.YY  ex: 7.34
    if (preg_match('/^(\d+)\.(\d+)$/', $p, $m)) {
        return (int)$m[1]*100 + (int)str_pad($m[2], 2, '0', STR_PAD_RIGHT);
    }
    // X:YY:ZZ  ex: 1:18:20
    if (preg_match('/^(\d+):(\d+):(\d+)$/', $p, $m)) {
        return ((int)$m[1]*3600 + (int)$m[2]*60 + (int)$m[3]) * 100;
    }
    // Entier seul
    if (preg_match('/^(\d+)$/', $p, $m)) {
        return (int)$m[1] * 100;
    }
    return 0;
}

// Calculer le niveau FFA si niveaux vides — sex-aware
function _fillNiveau(&$item, $perfKey = 'performance', $epKey = 'epreuve', $sexe = 'M') {
    if (!empty($item['niveaux'])) return;
    $perf = (int)($item[$perfKey] ?? 0);
    $ep = $item[$epKey] ?? '';
    if ($perf <= 0 && !empty($item['performance_brut'])) {
        $perf = _parsePerfBrut($item['performance_brut']);
    }
    if ($perf > 0 && $ep !== '') {
        $pts = _perfToFfaPts($perf, $ep, $sexe);
        if ($pts > 0) {
            $code = _ffaPtsToLevel($pts);
            if ($code !== '') $item['niveaux'] = [$code];
        }
    }
}

// Meme chose pour les resultats (champ 'niveau' au lieu de 'niveaux') — sex-aware
function _fillNiveauResultat(&$item, $sexe = 'M') {
    if (!empty($item['niveau'])) return;
    $perf = (int)($item['performance'] ?? 0);
    $ep = $item['epreuve'] ?? '';
    if ($perf <= 0 && !empty($item['performance_brut'])) {
        $perf = _parsePerfBrut($item['performance_brut']);
    }
    if ($perf > 0 && $ep !== '') {
        $pts = _perfToFfaPts($perf, $ep, $sexe);
        if ($pts > 0) {
            $code = _ffaPtsToLevel($pts);
            if ($code !== '') $item['niveau'] = $code;
        }
    }
}

$idExterne = $_GET['id'] ?? null;
$idInterne = $_GET['id_athlete'] ?? null;
$licence   = $_GET['licence'] ?? null;

if (!$idExterne && !$idInterne && !$licence) {
    jsonResponse(['success' => false, 'error' => 'Parametre ?id=, ?id_athlete= ou ?licence= requis'], 400);
}

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$_skipCache = isset($_GET['_all']);
$cacheKey = 'athlete_' . md5(($idExterne ?? '') . '_' . ($idInterne ?? '') . '_' . ($licence ?? ''));
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
if (!$_skipCache && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; $conn->close(); exit; }
}

// 1. Recuperer l'athlete
if ($idExterne) {
    $idEsc = $conn->real_escape_string($idExterne);
    $res = $conn->query("SELECT * FROM athletes WHERE athlete_id_externe = '$idEsc' LIMIT 1");
} elseif ($idInterne) {
    $idEsc = (int)$idInterne;
    $res = $conn->query("SELECT * FROM athletes WHERE id_athlete = '$idEsc' LIMIT 1");
} else {
    $licEsc = $conn->real_escape_string($licence);
    $res = $conn->query("SELECT * FROM athletes WHERE licence_athlete = '$licEsc' LIMIT 1");
}

if (!$res || $res->num_rows === 0) {
    jsonResponse(['success' => false, 'error' => 'Athlete non trouve'], 404);
}

$athlete = $res->fetch_assoc();
$id = (int)$athlete['id_athlete'];
$_sx = strtoupper(trim($athlete['sexe_athlete'] ?? 'M'));
if ($_sx !== 'F') $_sx = 'M';

// Profil masque : bloque uniquement les non-connectes
$_athleteHidden = (isset($athlete['visible']) && (int)$athlete['visible'] === 0);
if ($_athleteHidden && !isset($_GET['_all'])) {
    $currentUser = getCurrentUser($conn);
    if (!$currentUser) {
        jsonResponse(['success' => false, 'visible' => false, 'error' => 'Profil non disponible'], 404);
    }
}

// 2. Ville de naissance
$villeNaissance = '';
if (!empty($athlete['id_ville_naissance'])) {
    $r = $conn->query("SELECT nom_ville FROM villes WHERE id_ville = " . (int)$athlete['id_ville_naissance']);
    if ($r && $r->num_rows > 0) $villeNaissance = $r->fetch_assoc()['nom_ville'];
}

$identite = [
    'athlete_id'       => (int)$athlete['athlete_id_externe'],
    'id_athlete'       => $id,
    'nom_complet'      => $athlete['nom_complet_athlete'],
    'nom_1'            => $athlete['nom_1_athlete'],
    'nom_2'            => $athlete['nom_2_athlete'],
    'nom_3'            => $athlete['nom_3_athlete'],
    'nom_4'            => $athlete['nom_4_athlete'],
    'date_naissance'   => null,
    'annee_naissance'  => null,
    'lieu_naissance'   => $villeNaissance,
    'categorie'        => $athlete['categorie_athlete'],
    'sexe'             => $athlete['sexe_athlete'],
    'nationalite'      => $athlete['nationalite_athlete'],
    'taille_cm'        => $athlete['taille_cm_athlete'] ? (int)$athlete['taille_cm_athlete'] : null,
    'poids_kg'         => $athlete['poids_kg_athlete'] ? (int)$athlete['poids_kg_athlete'] : null,
    'licence'          => null,
];

// 3. Clubs
$clubs = [];
$res = $conn->query("
    SELECT c.nom_club, ac.annee_debut, ac.annee_fin
    FROM athlete_clubs ac
    JOIN clubs c ON c.id_club = ac.id_club
    WHERE ac.id_athlete = $id
    ORDER BY ac.annee_debut DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $clubs[] = [
        'nom_club'     => $row['nom_club'],
        'annee_debut'  => $row['annee_debut'] ? (int)$row['annee_debut'] : null,
        'annee_fin'    => $row['annee_fin'] ? (int)$row['annee_fin'] : null,
    ];
}

// 4. Medailles
$medailles = [];
$res = $conn->query("
    SELECT am.type_medaille, am.annee_medaille,
           e.nom_epreuve, co.nom_competition, v.nom_ville
    FROM athlete_medailles am
    LEFT JOIN epreuves e ON e.id_epreuve = am.id_epreuve
    LEFT JOIN competitions co ON co.id_competition = am.id_competition
    LEFT JOIN villes v ON v.id_ville = am.id_ville
    WHERE am.id_athlete = $id
    ORDER BY am.annee_medaille DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $medailles[] = [
        'type'        => $row['type_medaille'],
        'annee'       => (int)$row['annee_medaille'],
        'epreuve'     => $row['nom_epreuve'] ?? '',
        'competition' => $row['nom_competition'] ?? '',
        'lieu'        => $row['nom_ville'] ?? '',
    ];
}

// 5. Selections
$selections = [];
$res = $conn->query("
    SELECT s.type_selection, s.date_selection, s.duree_jours_selection, s.age_selection,
           s.classement_selection, s.performance_selection, s.performance_brut_selection,
           co.nom_competition, e.nom_epreuve,
           (SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
            FROM athlete_resultats ares
            WHERE ares.id_athlete = s.id_athlete AND ares.id_epreuve = s.id_epreuve
              AND YEAR(ares.date_resultat) = YEAR(s.date_selection)
              AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
    FROM athlete_selections s
    LEFT JOIN competitions co ON co.id_competition = s.id_competition
    LEFT JOIN epreuves e ON e.id_epreuve = s.id_epreuve
    WHERE s.id_athlete = $id
    ORDER BY s.date_selection DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $nivList = array_filter(explode(',', $row['niveaux'] ?? ''));
    $selections[] = [
        'type'             => $row['type_selection'],
        'date'             => $row['date_selection'],
        'duree_jours'      => $row['duree_jours_selection'] ? (int)$row['duree_jours_selection'] : null,
        'age'              => $row['age_selection'] ? (int)$row['age_selection'] : null,
        'competition'      => $row['nom_competition'] ?? '',
        'epreuve'          => $row['nom_epreuve'] ?? '',
        'classement'       => $row['classement_selection'] ? (int)$row['classement_selection'] : null,
        'performance'      => $row['performance_selection'] ? (int)$row['performance_selection'] : null,
        'performance_brut' => $row['performance_brut_selection'],
        'niveaux'          => array_values($nivList),
    ];
    _fillNiveau($selections[count($selections)-1], 'performance', 'epreuve', $_sx);
}

// 6. Progressions — depuis store fichier si active, sinon BDD
$progressions = [];
require_once __DIR__ . '/../core/progressions_store.php';
if (progStoreEnabled()) {
    $rawRows = progStoreLoadForAthlete((int)$id);
    $progressions = progStoreEnrichForProfile($conn, (int)$id, $rawRows);
    foreach ($progressions as &$_p) _fillNiveau($_p, 'performance', 'epreuve', $_sx);
    unset($_p);
} else {
    $res = $conn->query("
        SELECT p.annee_progression, p.performance_progression, p.performance_brut_progression,
               p.vent_progression, p.date_progression, p.ligue_dept_progression,
               e.nom_epreuve, v.nom_ville, ca.code_categorie, cl.nom_club,
               (SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
                FROM athlete_resultats ares
                WHERE ares.id_athlete = p.id_athlete AND ares.id_epreuve = p.id_epreuve
                  AND ares.annee_resultat = p.annee_progression
                  AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
        FROM athlete_progressions p
        LEFT JOIN epreuves e ON e.id_epreuve = p.id_epreuve
        LEFT JOIN villes v ON v.id_ville = p.id_ville
        LEFT JOIN categories ca ON ca.id_categorie = p.id_categorie
        LEFT JOIN clubs cl ON cl.id_club = p.id_club
        WHERE p.id_athlete = $id
        ORDER BY p.annee_progression DESC
    ");
    if ($res) while ($row = $res->fetch_assoc()) {
        $nivList = array_filter(explode(',', $row['niveaux'] ?? ''));
        $progressions[] = [
            'epreuve'          => $row['nom_epreuve'] ?? '',
            'annee'            => (int)$row['annee_progression'],
            'performance'      => $row['performance_progression'] ? (int)$row['performance_progression'] : null,
            'performance_brut' => $row['performance_brut_progression'],
            'vent'             => $row['vent_progression'],
            'date'             => $row['date_progression'],
            'lieu'             => $row['nom_ville'] ?? '',
            'categorie'        => $row['code_categorie'] ?? '',
            'club'             => $row['nom_club'] ?? '',
            'ligue_dept'       => $row['ligue_dept_progression'],
            'niveaux'          => array_values($nivList),
        ];
        _fillNiveau($progressions[count($progressions)-1], 'performance', 'epreuve', $_sx);
    }
}

// 7. Records
$records = [];
$res = $conn->query("
    SELECT r.performance_record, r.performance_brut_record, r.date_record, r.ligue_dept_record,
           e.nom_epreuve, cl.nom_club, v.nom_ville, ca.code_categorie,
           (SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
            FROM athlete_resultats ares
            WHERE ares.id_athlete = r.id_athlete AND ares.id_epreuve = r.id_epreuve
              AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
    FROM athlete_records r
    LEFT JOIN epreuves e ON e.id_epreuve = r.id_epreuve
    LEFT JOIN clubs cl ON cl.id_club = r.id_club
    LEFT JOIN villes v ON v.id_ville = r.id_ville
    LEFT JOIN categories ca ON ca.id_categorie = r.id_categorie
    WHERE r.id_athlete = $id
");
if ($res) while ($row = $res->fetch_assoc()) {
    $nivList = array_filter(explode(',', $row['niveaux'] ?? ''));
    $records[] = [
        'epreuve'          => $row['nom_epreuve'] ?? '',
        'performance'      => $row['performance_record'] ? (int)$row['performance_record'] : null,
        'performance_brut' => $row['performance_brut_record'],
        'date'             => $row['date_record'],
        'club'             => $row['nom_club'] ?? '',
        'lieu'             => $row['nom_ville'] ?? '',
        'categorie'        => $row['code_categorie'] ?? '',
        'ligue_dept'       => $row['ligue_dept_record'],
        'niveaux'          => array_values($nivList),
    ];
    _fillNiveau($records[count($records)-1], 'performance', 'epreuve', $_sx);
}

// 8. Podiums
$podiums = [];
$res = $conn->query("
    SELECT p.annee_podium, p.niveau_competition, p.place_podium, p.rang_podium,
           p.performance_podium, p.performance_brut_podium, p.vent_podium, p.date_podium,
           e.nom_epreuve, v.nom_ville
    FROM athlete_podiums p
    LEFT JOIN epreuves e ON e.id_epreuve = p.id_epreuve
    LEFT JOIN villes v ON v.id_ville = p.id_ville
    WHERE p.id_athlete = $id
    ORDER BY p.annee_podium DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $podiums[] = [
        'annee'              => (int)$row['annee_podium'],
        'niveau_competition' => $row['niveau_competition'],
        'place'              => $row['place_podium'],
        'rang'               => $row['rang_podium'] ? (int)$row['rang_podium'] : null,
        'epreuve'            => $row['nom_epreuve'] ?? '',
        'performance'        => $row['performance_podium'] ? (int)$row['performance_podium'] : null,
        'performance_brut'   => $row['performance_brut_podium'],
        'vent'               => $row['vent_podium'],
        'date'               => $row['date_podium'],
        'lieu'               => $row['nom_ville'] ?? '',
    ];
}

// 9. Resultats
$resultats = [];
$res = $conn->query("
    SELECT r.annee_resultat, r.date_resultat, r.performance_resultat, r.performance_brut_resultat,
           r.vent_resultat, r.tour_resultat, r.place_resultat, r.niveau_resultat, r.points_resultat,
           e.nom_epreuve, v.nom_ville
    FROM athlete_resultats r
    LEFT JOIN epreuves e ON e.id_epreuve = r.id_epreuve
    LEFT JOIN villes v ON v.id_ville = r.id_ville
    WHERE r.id_athlete = $id
    ORDER BY r.date_resultat DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $resultats[] = [
        'annee'            => (int)$row['annee_resultat'],
        'date'             => $row['date_resultat'],
        'epreuve'          => $row['nom_epreuve'] ?? '',
        'performance'      => $row['performance_resultat'] ? (int)$row['performance_resultat'] : null,
        'performance_brut' => $row['performance_brut_resultat'],
        'vent'             => $row['vent_resultat'],
        'tour'             => $row['tour_resultat'],
        'place'            => $row['place_resultat'] ? (int)$row['place_resultat'] : null,
        'niveau'           => $row['niveau_resultat'],
        'points'           => $row['points_resultat'] ? (int)$row['points_resultat'] : null,
        'lieu'             => $row['nom_ville'] ?? '',
    ];
    _fillNiveauResultat($resultats[count($resultats)-1], $_sx);
}

// 10. Niveaux + perfs
$niveaux = [];
$res = $conn->query("
    SELECT n.id_niveau, n.annee_niveau, n.code_niveau, n.points_niveau, cl.nom_club
    FROM athlete_niveaux n
    LEFT JOIN clubs cl ON cl.id_club = n.id_club
    WHERE n.id_athlete = $id
    ORDER BY n.annee_niveau DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $niv = [
        'annee'        => (int)$row['annee_niveau'],
        'code_niveau'  => $row['code_niveau'],
        'points_niveau' => $row['points_niveau'] ? (int)$row['points_niveau'] : null,
        'club'         => $row['nom_club'] ?? '',
        'performances' => [],
    ];

    $idNiv = (int)$row['id_niveau'];
    $resP = $conn->query("
        SELECT np.performance_niveau_perf, np.performance_brut_niveau_perf, np.code_perf_niveau,
               e.nom_epreuve
        FROM athlete_niv_perfs np
        LEFT JOIN epreuves e ON e.id_epreuve = np.id_epreuve
        WHERE np.id_niveau = $idNiv
    ");
    if ($resP) while ($p = $resP->fetch_assoc()) {
        $niv['performances'][] = [
            'epreuve'          => $p['nom_epreuve'] ?? '',
            'performance'      => $p['performance_niveau_perf'] ? (int)$p['performance_niveau_perf'] : null,
            'performance_brut' => $p['performance_brut_niveau_perf'],
            'code_niveau'      => $p['code_perf_niveau'],
        ];
    }

    $niveaux[] = $niv;
}

// Calculer le meilleur niveau global (BDD + bareme sur tous les records/progressions)
$_nivOrder = ['IA'=>40,'IB'=>35,'N1'=>30,'N2'=>28,'N3'=>26,'N4'=>24,'IR1'=>21,'IR2'=>20,'IR3'=>19,'IR4'=>18,'R1'=>15,'R2'=>14,'R3'=>13,'R4'=>12,'R5'=>11,'R6'=>10,'D1'=>8,'D2'=>7,'D3'=>6,'D4'=>5,'D5'=>4,'D6'=>3,'D7'=>2];
$_bestNivPts = 0;
$_bestNivCode = '';
// 1) Depuis niveaux BDD
foreach ($niveaux as $n) {
    $code = $n['code_niveau'] ?? '';
    $pts = $_nivOrder[$code] ?? 0;
    if ($pts > $_bestNivPts) { $_bestNivPts = $pts; $_bestNivCode = $code; }
}
// 2) Depuis records (calcules via bareme)
foreach ($records as $r) {
    foreach ($r['niveaux'] ?? [] as $code) {
        $pts = $_nivOrder[$code] ?? 0;
        if ($pts > $_bestNivPts) { $_bestNivPts = $pts; $_bestNivCode = $code; }
    }
}
// 3) Depuis progressions (calcules via bareme)
foreach ($progressions as $p) {
    foreach ($p['niveaux'] ?? [] as $code) {
        $pts = $_nivOrder[$code] ?? 0;
        if ($pts > $_bestNivPts) { $_bestNivPts = $pts; $_bestNivCode = $code; }
    }
}
$identite['meilleur_niveau'] = $_bestNivCode;

// Reveler annee/date de naissance + age UNIQUEMENT pour les athletes IA / IB
if ($_bestNivCode === 'IA' || $_bestNivCode === 'IB') {
    $_anNa = isset($athlete['annee_naissance_athlete']) && $athlete['annee_naissance_athlete']
             ? (int)$athlete['annee_naissance_athlete'] : null;
    $_dtNa = isset($athlete['date_naissance_athlete']) && $athlete['date_naissance_athlete']
             ? $athlete['date_naissance_athlete'] : null;
    $identite['annee_naissance'] = $_anNa;
    $identite['date_naissance']  = $_dtNa;
    if ($_anNa) {
        $_today = new DateTime();
        if ($_dtNa) {
            try { $_age = (new DateTime($_dtNa))->diff($_today)->y; }
            catch (Exception $e) { $_age = (int)$_today->format('Y') - $_anNa; }
        } else {
            $_age = (int)$_today->format('Y') - $_anNa;
        }
        $identite['age'] = $_age > 0 ? $_age : null;
    } else {
        $identite['age'] = null;
    }
} else {
    $identite['age'] = null;
}

// Reponse finale
$resp = [
    'success'      => true,
    'visible'      => !$_athleteHidden,
    'identite'     => $identite,
    'clubs'        => $clubs,
    'medailles'    => $medailles,
    'selections'   => $selections,
    'progressions' => $progressions,
    'records'      => $records,
    'podiums'      => $podiums,
    'resultats'    => $resultats,
    'niveaux'      => $niveaux,
];
$json = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if (!$_skipCache) @file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($resp);
