<?php
/**
 * api/villes.php — Liste des villes avec nombre d'athletes + periode
 *
 * Usage :
 *   api/villes.php                  Toutes les villes
 *   api/villes.php?nom=lyon         Recherche par nom
 *   api/villes.php?page=2           Pagination
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
    $where = "WHERE v.nom_ville LIKE '%$nomEsc%'";
}

$having = $hasAthletes ? "HAVING nb_athletes > 0" : "";
$join = $hasAthletes ? "INNER JOIN" : "LEFT JOIN";

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'villes_' . md5($nom . '_' . $page . '_' . $limit . '_' . ($hasAthletes?1:0));
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; $conn->close(); exit; }
}

if ($hasAthletes) {
    $countSql = "SELECT COUNT(*) as c FROM (
        SELECT v.id_ville, COUNT(DISTINCT ar.id_athlete) as nb_athletes
        FROM villes v
        INNER JOIN athlete_resultats ar ON ar.id_ville = v.id_ville
        $where
        GROUP BY v.id_ville
        $having
    ) sub";
} else {
    $countSql = "SELECT COUNT(*) as c FROM villes v $where";
}
$res = $conn->query($countSql);
$total = $res ? (int)$res->fetch_assoc()['c'] : 0;

$sql = "SELECT v.id_ville, v.nom_ville,
               COUNT(DISTINCT ar.id_athlete) as nb_athletes,
               MIN(ar.annee_resultat) as annee_debut,
               MAX(ar.annee_resultat) as annee_fin
        FROM villes v
        $join athlete_resultats ar ON ar.id_ville = v.id_ville
        $where
        GROUP BY v.id_ville
        $having
        ORDER BY nb_athletes DESC
        LIMIT $limit OFFSET $offset";

$res = $conn->query($sql);
$villes = [];

if ($res) while ($row = $res->fetch_assoc()) {
    $villes[] = [
        'id_ville'     => (int)$row['id_ville'],
        'nom_ville'    => $row['nom_ville'],
        'nb_athletes'  => (int)$row['nb_athletes'],
        'annee_debut'  => $row['annee_debut'] ? (int)$row['annee_debut'] : null,
        'annee_fin'    => $row['annee_fin'] ? (int)$row['annee_fin'] : null,
        'top_niveaux'  => [],
    ];
}

// Top 3 niveaux par ville — une seule requête
$villeIds = array_map(function($v) { return $v['id_ville']; }, $villes);
if (!empty($villeIds)) {
    $idsList = implode(',', $villeIds);
    $nRes = $conn->query("
        SELECT id_ville, niveau_resultat, COUNT(*) as cnt
        FROM athlete_resultats
        WHERE id_ville IN ($idsList) AND niveau_resultat IS NOT NULL AND niveau_resultat != ''
        GROUP BY id_ville, niveau_resultat
        ORDER BY id_ville, cnt DESC
    ");
    $nivParVille = [];
    if ($nRes) while ($nr = $nRes->fetch_assoc()) {
        $vid = (int)$nr['id_ville'];
        if (!isset($nivParVille[$vid])) $nivParVille[$vid] = [];
        $nivParVille[$vid][] = ['niveau' => $nr['niveau_resultat'], 'cnt' => (int)$nr['cnt']];
    }
    foreach ($villes as &$ville) {
        $vid = $ville['id_ville'];
        if (!isset($nivParVille[$vid])) continue;
        $rows = array_slice($nivParVille[$vid], 0, 3);
        $totalNiv = 0;
        foreach ($rows as $r) $totalNiv += $r['cnt'];
        foreach ($rows as $r) {
            $ville['top_niveaux'][] = ['niveau' => $r['niveau'], 'pct' => $totalNiv > 0 ? round($r['cnt'] / $totalNiv * 100) : 0];
        }
    }
    unset($ville);
}

$resp = [
    'success'     => true,
    'total'       => $total,
    'page'        => $page,
    'limit'       => $limit,
    'total_pages' => ceil($total / $limit),
    'villes'      => $villes,
];
$json = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($resp);
