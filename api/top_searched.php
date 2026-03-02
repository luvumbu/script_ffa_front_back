<?php
/**
 * api/top_searched.php — Top clubs et athletes les plus consultes
 *
 * GET ?type=clubs|athletes  (requis)
 *     &limit=50             (max items, defaut 50)
 *     &days=1|7|30|365      (periode, defaut=1 jour)
 *
 * Compte les vues depuis les tables de tracking (athlete_vues_ip / club_vues_ip)
 * filtrees par created_at selon la periode demandee.
 */
require_once __DIR__ . '/config.php';

// Reset compteurs vues (?reset=athletes ou ?reset=clubs ou ?reset=all) — NECESSITE bk_key
if (isset($_GET['reset']) && ($_GET['bk_key'] ?? '') === BK_API_KEY) {
    $reset = $_GET['reset'];
    if ($reset === 'athletes' || $reset === 'all') {
        $conn->query("UPDATE athletes SET vues = 0 WHERE vues > 0");
        $conn->query("TRUNCATE TABLE athlete_vues_ip");
    }
    if ($reset === 'clubs' || $reset === 'all') {
        $conn->query("UPDATE clubs SET vues = 0 WHERE vues > 0");
        $conn->query("TRUNCATE TABLE club_vues_ip");
    }
    // Vider le cache top searched
    $files = glob(__DIR__ . '/../cache/topsearched_*.json');
    if ($files) array_map('unlink', $files);
    jsonResponse(['success' => true, 'reset' => $reset]);
}

$type = $_GET['type'] ?? '';
if (!in_array($type, ['clubs', 'athletes'])) {
    jsonResponse(['success' => false, 'error' => 'type requis (clubs ou athletes)'], 400);
}

$limit = min(50, max(5, (int)($_GET['limit'] ?? 50)));
$days = (int)($_GET['days'] ?? 1);
if (!in_array($days, [1, 7, 30, 365])) $days = 1;
$nocache = isset($_GET['nocache']);

// Cache 10min (bypass avec ?nocache)
$cacheKey  = "topsearched_{$type}_{$limit}_{$days}d";
$cacheFile = __DIR__ . '/../cache/' . $cacheKey . '.json';
if (!$nocache && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 600) {
    echo file_get_contents($cacheFile);
    exit;
}

$items = [];

if ($type === 'athletes') {
    $sql = "SELECT a.athlete_id_externe,
                   CONCAT(a.prenom_athlete, ' ', a.nom_athlete) as nom,
                   a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete,
                   COUNT(v.ip) as vues,
                   (SELECT c.nom_club FROM clubs c
                    JOIN athlete_clubs ac ON ac.id_club = c.id_club
                    WHERE ac.id_athlete = a.id_athlete
                    ORDER BY ac.annee_debut DESC LIMIT 1) as club
            FROM athlete_vues_ip v
            JOIN athletes a ON a.athlete_id_externe = v.athlete_id_ext
            WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
            GROUP BY a.id_athlete
            ORDER BY vues DESC
            LIMIT $limit";

    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $items[] = [
                'id'          => (int)$row['athlete_id_externe'],
                'nom'         => trim($row['nom']),
                'sexe'        => $row['sexe_athlete'],
                'categorie'   => $row['categorie_athlete'],
                'nationalite' => $row['nationalite_athlete'],
                'club'        => rtrim($row['club'] ?? '', '* '),
                'vues'        => (int)$row['vues']
            ];
        }
    }

} elseif ($type === 'clubs') {
    $sql = "SELECT c.id_club, c.nom_club,
                   COUNT(v.ip) as vues,
                   (SELECT COUNT(DISTINCT ac.id_athlete)
                    FROM athlete_clubs ac WHERE ac.id_club = c.id_club) as nb_athletes
            FROM club_vues_ip v
            JOIN clubs c ON c.id_club = v.club_id
            WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
            GROUP BY c.id_club
            ORDER BY vues DESC
            LIMIT $limit";

    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $items[] = [
                'id'          => (int)$row['id_club'],
                'nom'         => $row['nom_club'],
                'nb_athletes' => (int)$row['nb_athletes'],
                'vues'        => (int)$row['vues']
            ];
        }
    }
}

$result = json_encode([
    'success' => true,
    'type'    => $type,
    'days'    => $days,
    'items'   => $items,
    'total'   => count($items),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

@file_put_contents($cacheFile, $result);

echo $result;
$conn->close();
