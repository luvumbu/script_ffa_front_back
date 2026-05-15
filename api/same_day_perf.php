<?php
/**
 * api/same_day_perf.php — Liste des athletes ayant fait la meme epreuve le meme jour
 *
 * Usage : api/same_day_perf.php?epreuve=100m&date=2024-06-15&exclude_id=12345
 *         &nocache=1 pour forcer un re-fetch
 *         &debug=1 pour voir les details
 *
 * Fouille dans 5 tables : athlete_resultats, athlete_records, athlete_progressions,
 * athlete_podiums, athlete_selections (toutes ayant id_epreuve + date)
 */

require_once __DIR__ . '/config.php';

$epreuve   = trim($_GET['epreuve'] ?? '');
$date      = trim($_GET['date'] ?? '');
$excludeId = (int)($_GET['exclude_id'] ?? 0);
$limit     = min(200, max(1, (int)($_GET['limit'] ?? 100)));
$noCache   = isset($_GET['nocache']);
$debug     = isset($_GET['debug']);

if ($epreuve === '') jsonResponse(['error' => 'epreuve requise'], 400);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonResponse(['error' => 'date invalide (format YYYY-MM-DD)'], 400);
if ($date === '0000-00-00') jsonResponse(['error' => 'date invalide'], 400);

$cacheKey = 'sameday_' . md5($epreuve . '|' . $date . '|' . $excludeId . '|' . $limit);
$cacheFile = __DIR__ . '/../cache/' . $cacheKey . '.json';
if (!$noCache && !$debug && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    echo file_get_contents($cacheFile);
    $conn->close();
    exit;
}

$epEsc = $conn->real_escape_string($epreuve);
$dtEsc = $conn->real_escape_string($date);

// Trouver TOUS les id_epreuve correspondant au nom (peut avoir des doublons d'imports)
$epIdRows = $conn->query("SELECT id_epreuve FROM epreuves WHERE nom_epreuve = '$epEsc'");
$epIds = [];
if ($epIdRows) while ($row = $epIdRows->fetch_assoc()) $epIds[] = (int)$row['id_epreuve'];
if (empty($epIds)) {
    // Tentative tolerante : LIKE sur nom (utile si "100m" vs "100 m")
    $epEscLike = str_replace([' ', "\xc2\xa0"], '', $epreuve);
    $r2 = $conn->query("SELECT id_epreuve, nom_epreuve FROM epreuves WHERE REPLACE(REPLACE(nom_epreuve, ' ', ''), '\xc2\xa0', '') = '" . $conn->real_escape_string($epEscLike) . "' LIMIT 10");
    if ($r2) while ($row = $r2->fetch_assoc()) $epIds[] = (int)$row['id_epreuve'];
}
if (empty($epIds)) {
    $out = json_encode(['success' => true, 'count' => 0, 'athletes' => [], 'note' => 'Epreuve introuvable: ' . $epreuve]);
    @file_put_contents($cacheFile, $out, LOCK_EX);
    echo $out;
    $conn->close();
    exit;
}
$epIdsList = implode(',', $epIds);

$_visCol = $conn->query("SHOW COLUMNS FROM athletes LIKE 'visible'");
$_hasVisible = ($_visCol && $_visCol->num_rows > 0);
$_visFilter = $_hasVisible ? "AND (a.visible = 1 OR a.visible IS NULL)" : "";

$excl = $excludeId > 0 ? "AND a.athlete_id_externe != $excludeId" : '';

$isDist = (bool)preg_match('/(poids|disque|javelot|marteau|hauteur|perche|longueur|triple)/i', $epreuve);
$orderBy = $isDist ? 'u.perf_int DESC' : 'u.perf_int ASC';

// Detecter si athlete_selections.date_selection existe
$_selDateCol = $conn->query("SHOW COLUMNS FROM athlete_selections LIKE 'date_selection'");
$_hasSelDate = ($_selDateCol && $_selDateCol->num_rows > 0);

$selUnion = $_hasSelDate ? "
    UNION ALL

    SELECT id_athlete, id_epreuve,
           performance_brut_selection, performance_selection,
           classement_selection, NULL, NULL, NULL, 'selection'
    FROM athlete_selections
    WHERE id_epreuve IN ($epIdsList) AND DATE(date_selection) = '$dtEsc'
" : '';

// UNION sur toutes les tables (filtre date + epreuve)
$sql = "
SELECT a.athlete_id_externe, a.nom_complet_athlete, a.nom_1_athlete, a.nom_2_athlete,
       a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete,
       u.perf_brut, u.perf_int, u.place, u.niveau, u.vent, u.id_ville, u.src,
       v.nom_ville
FROM (
    SELECT id_athlete, id_epreuve,
           performance_brut_resultat AS perf_brut,
           performance_resultat AS perf_int,
           place_resultat AS place,
           niveau_resultat AS niveau,
           vent_resultat AS vent,
           id_ville,
           'resultat' AS src
    FROM athlete_resultats
    WHERE id_epreuve IN ($epIdsList) AND DATE(date_resultat) = '$dtEsc'

    UNION ALL

    SELECT id_athlete, id_epreuve,
           performance_brut_record, performance_record,
           NULL, NULL, NULL, id_ville, 'record'
    FROM athlete_records
    WHERE id_epreuve IN ($epIdsList) AND DATE(date_record) = '$dtEsc'

    UNION ALL

    SELECT id_athlete, id_epreuve,
           performance_brut_progression, performance_progression,
           NULL, NULL, vent_progression, id_ville, 'progression'
    FROM athlete_progressions
    WHERE id_epreuve IN ($epIdsList) AND DATE(date_progression) = '$dtEsc'

    UNION ALL

    SELECT id_athlete, id_epreuve,
           performance_brut_podium, performance_podium,
           rang_podium, NULL, vent_podium, id_ville, 'podium'
    FROM athlete_podiums
    WHERE id_epreuve IN ($epIdsList) AND DATE(date_podium) = '$dtEsc'
    $selUnion
) u
JOIN athletes a ON a.id_athlete = u.id_athlete
LEFT JOIN villes v ON v.id_ville = u.id_ville
WHERE 1=1 $_visFilter $excl
ORDER BY $orderBy
LIMIT $limit
";

$res = @$conn->query($sql);
$err = $conn->error;
$results = [];
$seen = [];
$rawCount = 0;
if ($res) while ($row = $res->fetch_assoc()) {
    $rawCount++;
    $aid = (int)$row['athlete_id_externe'];
    if (isset($seen[$aid])) continue;
    $seen[$aid] = 1;
    // Construire le nom : utiliser nom_complet, sinon concatener nom_1 + nom_2
    $nom = trim($row['nom_complet_athlete'] ?? '');
    if ($nom === '') {
        $nom = trim(($row['nom_1_athlete'] ?? '') . ' ' . ($row['nom_2_athlete'] ?? ''));
    }
    $results[] = [
        'id'       => $aid,
        'nom'      => $nom !== '' ? $nom : 'Athlete #' . $aid,
        'sexe'     => $row['sexe_athlete'] ?? '',
        'cat'      => $row['categorie_athlete'] ?? '',
        'nat'      => $row['nationalite_athlete'] ?? '',
        'perf'     => $row['perf_brut'] ?? '',
        'place'    => $row['place'] ? (int)$row['place'] : null,
        'niveau'   => $row['niveau'] ?? '',
        'vent'     => $row['vent'] ?? '',
        'lieu'     => $row['nom_ville'] ?? '',
        'src'      => $row['src'] ?? '',
    ];
}

$diag = null;
if ($debug || count($results) === 0) {
    $diag = ['epreuve_ids' => $epIds, 'date' => $date, 'has_visible_col' => $_hasVisible, 'has_sel_date' => $_hasSelDate];
    $tables = [
        'resultats'     => ['athlete_resultats',     'date_resultat'],
        'records'       => ['athlete_records',       'date_record'],
        'progressions'  => ['athlete_progressions',  'date_progression'],
        'podiums'       => ['athlete_podiums',       'date_podium'],
    ];
    if ($_hasSelDate) $tables['selections'] = ['athlete_selections', 'date_selection'];
    foreach ($tables as $k => $info) {
        $cnt = 0;
        $r = @$conn->query("SELECT COUNT(*) as n FROM {$info[0]} WHERE id_epreuve IN ($epIdsList) AND DATE({$info[1]}) = '$dtEsc'");
        if ($r && $cr = $r->fetch_assoc()) $cnt = (int)$cr['n'];
        $diag['nb_in_' . $k] = $cnt;
        // Total sans date pour voir si l'epreuve a des donnees
        $cnt2 = 0;
        $r2 = @$conn->query("SELECT COUNT(*) as n FROM {$info[0]} WHERE id_epreuve IN ($epIdsList)");
        if ($r2 && $cr2 = $r2->fetch_assoc()) $cnt2 = (int)$cr2['n'];
        $diag['total_in_' . $k] = $cnt2;
    }
    // 5 dates exemples pour cette epreuve dans athlete_resultats
    $sample = [];
    $r = @$conn->query("SELECT DISTINCT DATE(date_resultat) as d FROM athlete_resultats WHERE id_epreuve IN ($epIdsList) AND date_resultat IS NOT NULL AND date_resultat != '0000-00-00' ORDER BY d DESC LIMIT 5");
    if ($r) while ($row = $r->fetch_assoc()) $sample[] = $row['d'];
    $diag['sample_dates_resultats'] = $sample;
    if ($err) $diag['sql_error'] = $err;
    if ($debug) $diag['sql'] = $sql;
}

$out = json_encode([
    'success'  => true,
    'epreuve'  => $epreuve,
    'epreuve_ids' => $epIds,
    'date'     => $date,
    'count'    => count($results),
    'raw_count'=> $rawCount,
    'athletes' => $results,
    'diag'     => $diag,
], JSON_UNESCAPED_UNICODE);

if (!$debug) @file_put_contents($cacheFile, $out, LOCK_EX);
echo $out;
$conn->close();
