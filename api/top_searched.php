<?php
/**
 * api/top_searched.php — Top clubs et athletes les plus consultes
 *
 * GET ?type=clubs|athletes  (requis)
 *     &limit=50             (max items, defaut 50)
 *     &days=1|7|30|365      (periode, defaut=1 jour)
 *
 * Compte les recherches/consultations depuis la table search_tracking
 * avec COUNT(DISTINCT ip) comme vues.
 */
require_once __DIR__ . '/config.php';

// Reset (?reset=athletes|clubs|all) — NECESSITE bk_key
if (isset($_GET['reset']) && ($_GET['bk_key'] ?? '') === BK_API_KEY) {
    $reset = $_GET['reset'];
    if ($reset === 'athletes' || $reset === 'all') {
        $conn->query("UPDATE athletes SET vues = 0 WHERE vues > 0");
        $conn->query("TRUNCATE TABLE athlete_vues_ip");
        $conn->query("DELETE FROM search_tracking WHERE search_type = 'athlete'");
    }
    if ($reset === 'clubs' || $reset === 'all') {
        $conn->query("UPDATE clubs SET vues = 0 WHERE vues > 0");
        $conn->query("TRUNCATE TABLE club_vues_ip");
        $conn->query("DELETE FROM search_tracking WHERE search_type = 'club'");
    }
    if ($reset === 'all') {
        $conn->query("TRUNCATE TABLE search_tracking");
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
    // Recherches + consultations d'athletes depuis search_tracking
    $sql = "SELECT st.entity_name as nom, st.entity_id,
                   COUNT(DISTINCT st.ip) as vues
            FROM search_tracking st
            WHERE st.search_type = 'athlete'
              AND st.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
              AND (st.entity_name IS NOT NULL AND st.entity_name != '')
            GROUP BY st.entity_name
            ORDER BY vues DESC
            LIMIT $limit";

    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $item = [
                'id'   => (int)($row['entity_id'] ?? 0),
                'nom'  => trim($row['nom']),
                'vues' => (int)$row['vues']
            ];
            // Enrichir avec infos athlete si entity_id disponible
            if ($item['id'] > 0) {
                $aRes = $conn->query("SELECT a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete,
                    (SELECT c.nom_club FROM clubs c JOIN athlete_clubs ac ON ac.id_club = c.id_club
                     WHERE ac.id_athlete = a.id_athlete ORDER BY ac.annee_debut DESC LIMIT 1) as club
                    FROM athletes a WHERE a.athlete_id_externe = {$item['id']} LIMIT 1");
                if ($aRes && ($aRow = $aRes->fetch_assoc())) {
                    $item['sexe'] = $aRow['sexe_athlete'];
                    $item['categorie'] = $aRow['categorie_athlete'];
                    $item['nationalite'] = $aRow['nationalite_athlete'];
                    $item['club'] = rtrim($aRow['club'] ?? '', '* ');
                }
            }
            $items[] = $item;
        }
    }

    // Si pas assez de resultats via entity_name, chercher aussi par query_text (live_search)
    if (count($items) < $limit) {
        $existingNames = array_map(function($i) { return strtolower($i['nom']); }, $items);
        $sql2 = "SELECT st.query_text, COUNT(DISTINCT st.ip) as vues
                 FROM search_tracking st
                 WHERE st.search_type = 'athlete'
                   AND st.source = 'live_search'
                   AND st.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                   AND st.query_text != ''
                 GROUP BY st.query_text
                 ORDER BY vues DESC
                 LIMIT 100";
        $res2 = $conn->query($sql2);
        if ($res2) {
            while ($row2 = $res2->fetch_assoc()) {
                if (count($items) >= $limit) break;
                if (in_array(strtolower(trim($row2['query_text'])), $existingNames)) continue;
                $items[] = [
                    'id' => 0,
                    'nom' => trim($row2['query_text']),
                    'vues' => (int)$row2['vues']
                ];
                $existingNames[] = strtolower(trim($row2['query_text']));
            }
        }
    }

} elseif ($type === 'clubs') {
    // Recherches + consultations de clubs depuis search_tracking
    $sql = "SELECT st.entity_name as nom, st.entity_id,
                   COUNT(DISTINCT st.ip) as vues
            FROM search_tracking st
            WHERE st.search_type = 'club'
              AND st.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
              AND (st.entity_name IS NOT NULL AND st.entity_name != '')
            GROUP BY st.entity_name
            ORDER BY vues DESC
            LIMIT $limit";

    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $item = [
                'id'   => (int)($row['entity_id'] ?? 0),
                'nom'  => $row['nom'],
                'vues' => (int)$row['vues']
            ];
            // Enrichir avec nb_athletes si entity_id disponible
            if ($item['id'] > 0) {
                $cRes = $conn->query("SELECT COUNT(DISTINCT ac.id_athlete) as nb FROM athlete_clubs ac WHERE ac.id_club = {$item['id']}");
                if ($cRes && ($cRow = $cRes->fetch_assoc())) {
                    $item['nb_athletes'] = (int)$cRow['nb'];
                }
            }
            $items[] = $item;
        }
    }

    // Si pas assez, chercher aussi les recherches par query_text
    if (count($items) < $limit) {
        $existingNames = array_map(function($i) { return strtolower($i['nom']); }, $items);
        $sql2 = "SELECT st.query_text, COUNT(DISTINCT st.ip) as vues
                 FROM search_tracking st
                 WHERE st.search_type = 'club'
                   AND st.source = 'live_search'
                   AND st.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                   AND st.query_text != ''
                 GROUP BY st.query_text
                 ORDER BY vues DESC
                 LIMIT 100";
        $res2 = $conn->query($sql2);
        if ($res2) {
            while ($row2 = $res2->fetch_assoc()) {
                if (count($items) >= $limit) break;
                if (in_array(strtolower(trim($row2['query_text'])), $existingNames)) continue;
                $items[] = [
                    'id' => 0,
                    'nom' => trim($row2['query_text']),
                    'vues' => (int)$row2['vues']
                ];
                $existingNames[] = strtolower(trim($row2['query_text']));
            }
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
