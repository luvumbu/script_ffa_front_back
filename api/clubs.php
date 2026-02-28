<?php
/**
 * api/clubs.php — Liste des clubs avec nombre d'athletes + periode
 *
 * Usage :
 *   api/clubs.php                     Tous les clubs
 *   api/clubs.php?nom=paris           Recherche par nom
 *   api/clubs.php?page=2&limit=50     Pagination
 */

require_once __DIR__ . '/config.php';

$nom          = trim($_GET['nom'] ?? '');
$hasAthletes  = isset($_GET['has_athletes']) && $_GET['has_athletes'] == '1';
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset       = ($page - 1) * $limit;

$where = "";
if ($nom !== '') {
    $nomEsc = $conn->real_escape_string($nom);
    $where = "WHERE cl.nom_club LIKE '%$nomEsc%'";
}

$maxAthletes = isset($_GET['max_athletes']) ? (int)$_GET['max_athletes'] : 0;

$havingParts = [];
if ($hasAthletes) $havingParts[] = "nb_athletes > 0";
if ($maxAthletes > 0) $havingParts[] = "nb_athletes < $maxAthletes";
$having = count($havingParts) ? "HAVING " . implode(' AND ', $havingParts) : "";

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'clubs_' . md5($nom . '_' . $page . '_' . $limit . '_' . ($hasAthletes?1:0) . '_' . $maxAthletes);
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; $conn->close(); exit; }
}
$join = ($hasAthletes || $maxAthletes > 0) ? "INNER JOIN" : "LEFT JOIN";

// Total
if ($hasAthletes || $maxAthletes > 0) {
    $countSql = "SELECT COUNT(*) as c FROM (
        SELECT cl.id_club, COUNT(DISTINCT ac.id_athlete) as nb_athletes
        FROM clubs cl
        INNER JOIN athlete_clubs ac ON ac.id_club = cl.id_club
        $where
        GROUP BY cl.id_club
        $having
    ) sub";
} else {
    $countSql = "SELECT COUNT(*) as c FROM clubs cl $where";
}
$res = $conn->query($countSql);
$total = $res ? (int)$res->fetch_assoc()['c'] : 0;

// Liste avec annee debut/fin
$sql = "SELECT cl.id_club, cl.nom_club,
               COUNT(DISTINCT ac.id_athlete) as nb_athletes,
               MIN(ac.annee_debut) as annee_debut,
               MAX(ac.annee_fin) as annee_fin
        FROM clubs cl
        $join athlete_clubs ac ON ac.id_club = cl.id_club
        $where
        GROUP BY cl.id_club
        $having
        ORDER BY nb_athletes DESC
        LIMIT $limit OFFSET $offset";

$res = $conn->query($sql);
$clubs = [];

if ($res) while ($row = $res->fetch_assoc()) {
    $clubs[] = [
        'id_club'      => (int)$row['id_club'],
        'nom_club'     => $row['nom_club'],
        'nb_athletes'  => (int)$row['nb_athletes'],
        'annee_debut'  => $row['annee_debut'] ? (int)$row['annee_debut'] : null,
        'annee_fin'    => $row['annee_fin'] ? (int)$row['annee_fin'] : null,
        'top_niveaux'  => [],
    ];
}

// Top 3 niveaux par club
$clubIds = array_map(function($c) { return $c['id_club']; }, $clubs);
if (!empty($clubIds)) {
    $idsList = implode(',', $clubIds);
    $nRes = $conn->query("
        SELECT ac.id_club, n.code_niveau, COUNT(DISTINCT n.id_athlete) as cnt
        FROM athlete_niveaux n
        JOIN athlete_clubs ac ON ac.id_athlete = n.id_athlete
        WHERE ac.id_club IN ($idsList) AND n.code_niveau IS NOT NULL AND n.code_niveau != ''
        GROUP BY ac.id_club, n.code_niveau
        ORDER BY ac.id_club, cnt DESC
    ");
    $nivParClub = [];
    if ($nRes) while ($nr = $nRes->fetch_assoc()) {
        $cid = (int)$nr['id_club'];
        if (!isset($nivParClub[$cid])) $nivParClub[$cid] = [];
        $nivParClub[$cid][] = ['niveau' => $nr['code_niveau'], 'cnt' => (int)$nr['cnt']];
    }
    foreach ($clubs as &$club) {
        $cid = $club['id_club'];
        if (!isset($nivParClub[$cid])) continue;
        $rows = array_slice($nivParClub[$cid], 0, 3);
        $totalNiv = 0;
        foreach ($rows as $r) $totalNiv += $r['cnt'];
        foreach ($rows as $r) {
            $club['top_niveaux'][] = ['niveau' => $r['niveau'], 'pct' => $totalNiv > 0 ? round($r['cnt'] / $totalNiv * 100) : 0];
        }
    }
    unset($club);
}

$resp = [
    'success'     => true,
    'total'       => $total,
    'page'        => $page,
    'limit'       => $limit,
    'total_pages' => ceil($total / $limit),
    'clubs'       => $clubs,
];
$json = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($resp);
