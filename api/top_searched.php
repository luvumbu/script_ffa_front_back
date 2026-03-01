<?php
/**
 * api/top_searched.php — Top clubs et athletes les plus recherches
 *
 * GET ?type=clubs|athletes  (requis)
 *     &limit=50             (max items, defaut 50)
 *     &days=30              (periode en jours, defaut 30)
 */
require_once __DIR__ . '/config.php';

$type = $_GET['type'] ?? '';
if (!in_array($type, ['clubs', 'athletes'])) {
    jsonResponse(['success' => false, 'error' => 'type requis (clubs ou athletes)'], 400);
}

$limit = min(50, max(5, (int)($_GET['limit'] ?? 50)));
$days  = min(365, max(1, (int)($_GET['days'] ?? 30)));

// Cache 1h
$cacheKey  = "topsearched_{$type}_{$limit}_{$days}";
$cacheFile = __DIR__ . '/../cache/' . $cacheKey . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
    echo file_get_contents($cacheFile);
    exit;
}

// days=1 → aujourd'hui, days=7 → 7 jours en arriere, etc.
$dateLimit = $conn->real_escape_string(date('Y-m-d', strtotime("-" . ($days - 1) . " days")));
$items = [];

if ($type === 'athletes') {
    // Top profil views : page contient "page=profil" + "id=XXXXX"
    $sql = "SELECT
                SUBSTRING_INDEX(SUBSTRING_INDEX(page, 'id=', -1), '&', 1) as ext_id,
                COUNT(*) as vues
            FROM logs
            WHERE action = 'page_view'
              AND page LIKE '%page=profil%'
              AND page LIKE '%id=%'
              AND ts >= '$dateLimit'
            GROUP BY ext_id
            HAVING ext_id REGEXP '^[0-9]+$'
            ORDER BY vues DESC
            LIMIT $limit";

    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $extIds  = [];
        $vuesMap = [];
        while ($r = $res->fetch_assoc()) {
            $eid = (int)$r['ext_id'];
            $extIds[]      = $eid;
            $vuesMap[$eid]  = (int)$r['vues'];
        }

        if (!empty($extIds)) {
            $idsList = implode(',', $extIds);
            $ath = $conn->query("
                SELECT a.athlete_id_externe, a.nom_athlete, a.prenom_athlete,
                       a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete,
                       (SELECT c.nom_club FROM clubs c
                        JOIN athlete_clubs ac ON ac.id_club = c.id_club
                        WHERE ac.id_athlete = a.id_athlete
                        ORDER BY ac.annee_debut DESC LIMIT 1) as club
                FROM athletes a
                WHERE a.athlete_id_externe IN ($idsList)
            ");

            $athMap = [];
            if ($ath) {
                while ($row = $ath->fetch_assoc()) {
                    $athMap[(int)$row['athlete_id_externe']] = $row;
                }
            }

            foreach ($extIds as $eid) {
                if (isset($athMap[$eid])) {
                    $row = $athMap[$eid];
                    $items[] = [
                        'id'          => $eid,
                        'nom'         => trim($row['prenom_athlete'] . ' ' . $row['nom_athlete']),
                        'sexe'        => $row['sexe_athlete'],
                        'categorie'   => $row['categorie_athlete'],
                        'nationalite' => $row['nationalite_athlete'],
                        'club'        => rtrim($row['club'] ?? '', '* '),
                        'vues'        => $vuesMap[$eid]
                    ];
                }
            }
        }
    }

} elseif ($type === 'clubs') {
    // Top club searches : page contient "page=recherche" + "club=NOM"
    $sql = "SELECT
                SUBSTRING_INDEX(SUBSTRING_INDEX(page, 'club=', -1), '&', 1) as club_name,
                COUNT(*) as vues
            FROM logs
            WHERE action = 'page_view'
              AND page LIKE '%page=recherche%'
              AND page LIKE '%club=%'
              AND ts >= '$dateLimit'
            GROUP BY club_name
            HAVING club_name != ''
            ORDER BY vues DESC
            LIMIT $limit";

    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        // Merge doublons d'encodage URL (Club+Name vs Club%20Name)
        $vuesMap   = [];
        while ($r = $res->fetch_assoc()) {
            $name = trim(urldecode($r['club_name']));
            if ($name === '') continue;
            $vuesMap[$name] = ($vuesMap[$name] ?? 0) + (int)$r['vues'];
        }
        // Re-trier par vues decroissantes apres merge
        arsort($vuesMap);
        $vuesMap = array_slice($vuesMap, 0, $limit, true);
        $clubNames = array_keys($vuesMap);

        if (!empty($clubNames)) {
            $escapedNames = array_map(function($n) use ($conn) {
                return "'" . $conn->real_escape_string($n) . "'";
            }, $clubNames);
            $namesList = implode(',', $escapedNames);

            $cl = $conn->query("
                SELECT c.id_club, c.nom_club,
                       (SELECT COUNT(DISTINCT ac.id_athlete)
                        FROM athlete_clubs ac WHERE ac.id_club = c.id_club) as nb_athletes
                FROM clubs c
                WHERE c.nom_club IN ($namesList)
            ");

            $clubMap = [];
            if ($cl) {
                while ($row = $cl->fetch_assoc()) {
                    $clubMap[$row['nom_club']] = $row;
                }
            }

            foreach ($clubNames as $name) {
                $vues = $vuesMap[$name];
                if (isset($clubMap[$name])) {
                    $row = $clubMap[$name];
                    $items[] = [
                        'id'          => (int)$row['id_club'],
                        'nom'         => $row['nom_club'],
                        'nb_athletes' => (int)$row['nb_athletes'],
                        'vues'        => $vues
                    ];
                } else {
                    $items[] = [
                        'id'          => 0,
                        'nom'         => $name,
                        'nb_athletes' => 0,
                        'vues'        => $vues
                    ];
                }
            }
        }
    }
}

$result = json_encode([
    'success' => true,
    'type'    => $type,
    'items'   => $items,
    'total'   => count($items),
    'days'    => $days
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

@file_put_contents($cacheFile, $result);

echo $result;
$conn->close();
