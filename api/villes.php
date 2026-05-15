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
// Filtre annee : si fourni, lit depuis villes_stats_annee (pre-calcule par annee)
$annee        = (int)($_GET['annee'] ?? 0);
if ($annee < 1990 || $annee > (int)date('Y')) $annee = 0;

// Rate limiting recherches (cf. core/search_limit.php) : applique uniquement sur recherche par nom.
$_sl = null;
if ($nom !== '') {
    require_once __DIR__ . '/../core/search_limit.php';
    $_sl = bkSearchLimit($conn, true);
    if ($_sl['blocked']) {
        jsonResponse(array_merge([
            'success'       => false,
            'limit_reached' => true,
            'reason'        => $_sl['reason'],
            'limit'         => $_sl['limit'],
            'remaining'     => 0,
            'error'         => $_sl['reason'] === 'trial'
                ? 'Decouverte gratuite terminee — inscrivez-vous pour continuer'
                : ($_sl['reason'] === 'cooldown'
                    ? 'Patientez avant la prochaine recherche'
                    : 'Limite de recherches atteinte'),
        ], bkSlFields($_sl)), 429);
    }
}

// Filtres en WHERE direct (colonnes denormalisees : nb_athletes pre-calcule)
$conds = [];
if ($nom !== '') {
    $nomEsc = $conn->real_escape_string($nom);
    $conds[] = "v.nom_ville LIKE '%$nomEsc%'";
}
if ($hasAthletes) $conds[] = "v.nb_athletes > 0";
$where = $conds ? "WHERE " . implode(' AND ', $conds) : "";

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'villes_' . md5($nom . '_' . $page . '_' . $limit . '_' . ($hasAthletes?1:0) . '_y' . $annee);
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) {
        // Rafraichir le quota du visiteur (le cache ne doit pas servir des compteurs perimes)
        if ($_sl) {
            $_cd = json_decode($cached, true);
            if (is_array($_cd)) {
                echo json_encode(array_merge($_cd, bkSlFields($_sl)), JSON_UNESCAPED_UNICODE);
                $conn->close(); exit;
            }
        }
        echo $cached; $conn->close(); exit;
    }
}

if ($annee > 0) {
    // Mode annee filtree : utilise villes_stats_annee (pre-calculee par admin/refresh_villes_stats.php)
    $countSql = "SELECT COUNT(*) as c FROM villes v INNER JOIN villes_stats_annee s ON s.id_ville = v.id_ville AND s.annee = $annee $where";
    $res = $conn->query($countSql);
    $total = $res ? (int)$res->fetch_assoc()['c'] : 0;

    $sql = "SELECT v.id_ville, v.nom_ville,
                   s.nb_athletes,
                   $annee as annee_debut,
                   $annee as annee_fin
            FROM villes v
            INNER JOIN villes_stats_annee s ON s.id_ville = v.id_ville AND s.annee = $annee
            $where
            ORDER BY s.nb_athletes DESC
            LIMIT $limit OFFSET $offset";
} else {
    // Mode all-time : colonnes denormalisees sur villes
    $countSql = "SELECT COUNT(*) as c FROM villes v $where";
    $res = $conn->query($countSql);
    $total = $res ? (int)$res->fetch_assoc()['c'] : 0;

    $sql = "SELECT v.id_ville, v.nom_ville,
                   v.nb_athletes,
                   v.annee_debut_perf as annee_debut,
                   v.annee_fin_perf as annee_fin
            FROM villes v
            $where
            ORDER BY v.nb_athletes DESC
            LIMIT $limit OFFSET $offset";
}

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
if ($_sl) $resp = array_merge($resp, bkSlFields($_sl));
$json = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($resp);
