<?php
/**
 * api/epreuves.php — Liste des epreuves avec nombre de records + periode
 *
 * Usage :
 *   api/epreuves.php                  Toutes les epreuves
 *   api/epreuves.php?nom=100          Recherche par nom
 *   api/epreuves.php?page=2           Pagination
 */

require_once __DIR__ . '/config.php';

$nom          = trim($_GET['nom'] ?? '');
$hasAthletes  = isset($_GET['has_athletes']) && $_GET['has_athletes'] == '1';
$noLimit      = isset($_GET['no_limit']) && $_GET['no_limit'] == '1';
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset       = ($page - 1) * $limit;

$where = "";
$having = "";
if ($nom !== '') {
    $nomEsc = $conn->real_escape_string($nom);
    $where = "WHERE e.nom_epreuve LIKE '%$nomEsc%'";
}
if ($hasAthletes) {
    $having = "HAVING nb_athletes > 0";
}

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'epreuves_' . md5($nom . '_' . $page . '_' . $limit . '_' . ($hasAthletes?1:0) . '_' . ($noLimit?1:0));
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; $conn->close(); exit; }
}

// Count total (with has_athletes filter if needed)
if ($hasAthletes) {
    $countSql = "SELECT COUNT(*) as c FROM (
        SELECT e.id_epreuve, COUNT(DISTINCT ar.id_athlete) as nb_athletes
        FROM epreuves e
        INNER JOIN athlete_records ar ON ar.id_epreuve = e.id_epreuve
        $where
        GROUP BY e.id_epreuve
    ) sub";
} else {
    $countSql = "SELECT COUNT(*) as c FROM epreuves e $where";
}
$res = $conn->query($countSql);
$total = $res ? (int)$res->fetch_assoc()['c'] : 0;

$join = $hasAthletes ? "INNER JOIN" : "LEFT JOIN";
$limitClause = $noLimit ? "" : "LIMIT $limit OFFSET $offset";

$sql = "SELECT e.id_epreuve, e.nom_epreuve,
               COUNT(DISTINCT ar.id_athlete) as nb_athletes,
               MIN(ar.date_record) as date_debut,
               MAX(ar.date_record) as date_fin
        FROM epreuves e
        $join athlete_records ar ON ar.id_epreuve = e.id_epreuve
        $where
        GROUP BY e.id_epreuve
        $having
        ORDER BY nb_athletes DESC
        $limitClause";

$res = $conn->query($sql);
$epreuves = [];

if ($res) while ($row = $res->fetch_assoc()) {
    $epreuves[] = [
        'id_epreuve'   => (int)$row['id_epreuve'],
        'nom_epreuve'  => $row['nom_epreuve'],
        'nb_athletes'  => (int)$row['nb_athletes'],
        'date_debut'   => $row['date_debut'],
        'date_fin'     => $row['date_fin'],
    ];
}

$resp = [
    'success'     => true,
    'total'       => $total,
    'page'        => $page,
    'limit'       => $limit,
    'total_pages' => ceil($total / $limit),
    'epreuves'    => $epreuves,
];
$json = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($resp);
