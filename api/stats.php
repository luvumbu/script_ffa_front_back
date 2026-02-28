<?php
/**
 * api/stats.php — Statistiques globales de la BDD
 *
 * Usage :
 *   api/stats.php              Toutes les stats (cache 10 min)
 *   api/stats.php?detail=1     Stats + top clubs/epreuves/villes (cache 5 min)
 *   api/stats.php?nocache=1    Forcer le recalcul
 */

require_once __DIR__ . '/config.php';

$detail = isset($_GET['detail']) && $_GET['detail'] == '1';
$topLimit = min(200, max(10, (int)($_GET['top'] ?? 50)));
$noCache = isset($_GET['nocache']) && $_GET['nocache'] == '1';

// ---- Cache fichier ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'stats' . ($detail ? '_detail_' . $topLimit : '_base');
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
$cacheTtl = 86400; // 24h

if (!$noCache && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) {
        echo $cached;
        $conn->close();
        exit;
    }
}

// Comptages principaux
$stats = [];

$tables = [
    'athletes'              => 'Total athletes',
    'athlete_clubs'         => 'Total affiliations clubs',
    'athlete_medailles'     => 'Total medailles',
    'athlete_selections'    => 'Total selections',
    'athlete_progressions'  => 'Total progressions',
    'athlete_records'       => 'Total records',
    'athlete_podiums'       => 'Total podiums',
    'athlete_resultats'     => 'Total resultats',
    'athlete_niveaux'       => 'Total niveaux',
    'athlete_niv_perfs'     => 'Total niveaux performances',
    'clubs'                 => 'Clubs uniques',
    'epreuves'              => 'Epreuves uniques',
    'villes'                => 'Villes uniques',
    'competitions'          => 'Competitions uniques',
    'nationalites'          => 'Nationalites uniques',
];

$comptages = [];
foreach ($tables as $table => $label) {
    $res = $conn->query("SELECT COUNT(*) as c FROM `$table`");
    $comptages[$table] = [
        'label' => $label,
        'count' => $res ? (int)$res->fetch_assoc()['c'] : 0,
    ];
}

// Repartition par sexe
$parSexe = [];
$res = $conn->query("SELECT sexe_athlete, COUNT(*) as c FROM athletes GROUP BY sexe_athlete ORDER BY c DESC");
if ($res) while ($row = $res->fetch_assoc()) {
    $parSexe[$row['sexe_athlete'] ?: 'inconnu'] = (int)$row['c'];
}

// Repartition par categorie
$parCategorie = [];
$res = $conn->query("SELECT categorie_athlete, COUNT(*) as c FROM athletes WHERE categorie_athlete != '' GROUP BY categorie_athlete ORDER BY c DESC");
if ($res) while ($row = $res->fetch_assoc()) {
    $parCategorie[$row['categorie_athlete']] = (int)$row['c'];
}

// Repartition par nationalite (top 10)
$parNationalite = [];
$res = $conn->query("SELECT nationalite_athlete, COUNT(*) as c FROM athletes WHERE nationalite_athlete != '' GROUP BY nationalite_athlete ORDER BY c DESC LIMIT 10");
if ($res) while ($row = $res->fetch_assoc()) {
    $parNationalite[$row['nationalite_athlete']] = (int)$row['c'];
}

// Medailles par type
$medaillesType = [];
$res = $conn->query("SELECT type_medaille, COUNT(*) as c FROM athlete_medailles GROUP BY type_medaille ORDER BY FIELD(type_medaille, 'or', 'argent', 'bronze', 'autre')");
if ($res) while ($row = $res->fetch_assoc()) {
    $medaillesType[$row['type_medaille']] = (int)$row['c'];
}

$response = [
    'success'           => true,
    'comptages'         => $comptages,
    'par_sexe'          => $parSexe,
    'par_categorie'     => $parCategorie,
    'par_nationalite'   => $parNationalite,
    'medailles_par_type' => $medaillesType,
];

// Details supplementaires
if ($detail) {
    // Top clubs (LIMIT applique, sans sous-requetes correlees)
    $topClubs = [];
    $res = $conn->query("
        SELECT cl.nom_club, cl.id_club, COUNT(DISTINCT ac.id_athlete) as nb_athletes
        FROM athlete_clubs ac
        JOIN clubs cl ON cl.id_club = ac.id_club
        GROUP BY ac.id_club
        HAVING nb_athletes < 5000
        ORDER BY nb_athletes DESC
        LIMIT $topLimit
    ");
    if ($res) while ($row = $res->fetch_assoc()) {
        $topClubs[] = [
            'club' => $row['nom_club'],
            'id_club' => (int)$row['id_club'],
            'nb_athletes' => (int)$row['nb_athletes'],
            'nb_medailles' => 0,
            'nb_records' => 0,
        ];
    }

    // Batch medailles et records pour les clubs retenus
    if (!empty($topClubs)) {
        $clubIds = array_map(function($c) { return (int)$c['id_club']; }, $topClubs);
        $clubIdsList = implode(',', $clubIds);
        $clubIdx = array_flip(array_column($topClubs, 'id_club'));

        $res = $conn->query("SELECT ac.id_club, COUNT(*) as c FROM athlete_medailles am JOIN athlete_clubs ac ON ac.id_athlete = am.id_athlete WHERE ac.id_club IN ($clubIdsList) GROUP BY ac.id_club");
        if ($res) while ($row = $res->fetch_assoc()) {
            $k = $clubIdx[(int)$row['id_club']] ?? null;
            if ($k !== null) $topClubs[$k]['nb_medailles'] = (int)$row['c'];
        }

        $res = $conn->query("SELECT ac.id_club, COUNT(*) as c FROM athlete_records ar JOIN athlete_clubs ac ON ac.id_athlete = ar.id_athlete WHERE ac.id_club IN ($clubIdsList) GROUP BY ac.id_club");
        if ($res) while ($row = $res->fetch_assoc()) {
            $k = $clubIdx[(int)$row['id_club']] ?? null;
            if ($k !== null) $topClubs[$k]['nb_records'] = (int)$row['c'];
        }
    }
    $response['top_clubs'] = $topClubs;

    // Top epreuves (LIMIT)
    $topEpreuves = [];
    $res = $conn->query("
        SELECT e.nom_epreuve, COUNT(*) as nb_records, COUNT(DISTINCT ar.id_athlete) as nb_athletes
        FROM athlete_records ar
        JOIN epreuves e ON e.id_epreuve = ar.id_epreuve
        GROUP BY ar.id_epreuve
        ORDER BY nb_records DESC
        LIMIT $topLimit
    ");
    if ($res) while ($row = $res->fetch_assoc()) {
        $topEpreuves[] = ['epreuve' => $row['nom_epreuve'], 'nb_records' => (int)$row['nb_records'], 'nb_athletes' => (int)$row['nb_athletes']];
    }
    $response['top_epreuves'] = $topEpreuves;

    // Top villes (LIMIT)
    $topVilles = [];
    $res = $conn->query("
        SELECT v.nom_ville, COUNT(*) as nb_resultats, COUNT(DISTINCT r.id_athlete) as nb_athletes
        FROM athlete_resultats r
        JOIN villes v ON v.id_ville = r.id_ville
        GROUP BY r.id_ville
        ORDER BY nb_resultats DESC
        LIMIT $topLimit
    ");
    if ($res) while ($row = $res->fetch_assoc()) {
        $topVilles[] = ['ville' => $row['nom_ville'], 'nb_resultats' => (int)$row['nb_resultats'], 'nb_athletes' => (int)$row['nb_athletes']];
    }
    $response['top_villes'] = $topVilles;

    // Top athletes (LIMIT, avec sous-requetes)
    $topAthletes = [];
    $res = $conn->query("
        SELECT a.athlete_id_externe, a.nom_complet_athlete, a.categorie_athlete, a.sexe_athlete, a.nationalite_athlete,
               (SELECT COUNT(*) FROM athlete_records ar WHERE ar.id_athlete = a.id_athlete) as nb_records,
               (SELECT COUNT(*) FROM athlete_medailles am WHERE am.id_athlete = a.id_athlete) as nb_medailles,
               (SELECT COUNT(*) FROM athlete_podiums ap WHERE ap.id_athlete = a.id_athlete) as nb_podiums,
               (SELECT COUNT(*) FROM athlete_selections asel WHERE asel.id_athlete = a.id_athlete) as nb_selections,
               (SELECT COUNT(*) FROM athlete_resultats ares WHERE ares.id_athlete = a.id_athlete) as nb_resultats,
               (SELECT c2.nom_club FROM athlete_clubs ac2 JOIN clubs c2 ON c2.id_club = ac2.id_club WHERE ac2.id_athlete = a.id_athlete ORDER BY COALESCE(ac2.annee_fin, 9999) DESC LIMIT 1) as club
        FROM athletes a
        HAVING (nb_medailles + nb_podiums + nb_records + nb_selections) > 0
        ORDER BY (nb_medailles * 5 + nb_podiums * 3 + nb_selections * 4 + nb_records) DESC
        LIMIT $topLimit
    ");
    if ($res) while ($row = $res->fetch_assoc()) {
        $topAthletes[] = [
            'athlete_id' => (int)$row['athlete_id_externe'],
            'nom' => $row['nom_complet_athlete'],
            'categorie' => $row['categorie_athlete'],
            'sexe' => $row['sexe_athlete'],
            'nationalite' => $row['nationalite_athlete'],
            'club' => $row['club'],
            'nb_records' => (int)$row['nb_records'],
            'nb_medailles' => (int)$row['nb_medailles'],
            'nb_podiums' => (int)$row['nb_podiums'],
            'nb_selections' => (int)$row['nb_selections'],
            'nb_resultats' => (int)$row['nb_resultats'],
            'score' => (int)$row['nb_medailles'] * 5 + (int)$row['nb_podiums'] * 3 + (int)$row['nb_selections'] * 4 + (int)$row['nb_records'],
        ];
    }
    $response['top_athletes'] = $topAthletes;

    // ---- Batch niveaux % (uniquement pour les IDs retenus) ----
    function _nivPct($d, $r, $n, $i) {
        $t = $d + $r + $n + $i;
        if ($t === 0) return null;
        return [
            'D' => round($d / $t * 100),
            'R' => round($r / $t * 100),
            'N' => round($n / $t * 100),
            'I' => round($i / $t * 100),
            'total' => $t,
        ];
    }

    // 1. Niveaux par club (uniquement les clubs retenus)
    if (!empty($topClubs)) {
        $clubNiv = [];
        $res = $conn->query("
            SELECT ac.id_club,
                   SUM(LEFT(ares.niveau_resultat,1) = 'D') as d,
                   SUM(LEFT(ares.niveau_resultat,1) = 'R') as r,
                   SUM(LEFT(ares.niveau_resultat,1) = 'N') as n,
                   SUM(LEFT(ares.niveau_resultat,1) = 'I') as i
            FROM athlete_resultats ares
            JOIN athlete_clubs ac ON ac.id_athlete = ares.id_athlete
            WHERE ac.id_club IN ($clubIdsList)
              AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != ''
            GROUP BY ac.id_club
        ");
        if ($res) while ($row = $res->fetch_assoc()) {
            $clubNiv[(int)$row['id_club']] = _nivPct((int)$row['d'], (int)$row['r'], (int)$row['n'], (int)$row['i']);
        }
        foreach ($topClubs as &$c) {
            $c['niveaux_pct'] = $clubNiv[$c['id_club']] ?? null;
        }
        unset($c);
        $response['top_clubs'] = $topClubs;
    }

    // 2. Niveaux par epreuve (uniquement les epreuves retenues)
    if (!empty($topEpreuves)) {
        $epNames = array_map(function($e) use ($conn) { return "'" . $conn->real_escape_string($e['epreuve']) . "'"; }, $topEpreuves);
        $epNamesList = implode(',', $epNames);
        $epNiv = [];
        $res = $conn->query("
            SELECT e.nom_epreuve,
                   SUM(LEFT(ares.niveau_resultat,1) = 'D') as d,
                   SUM(LEFT(ares.niveau_resultat,1) = 'R') as r,
                   SUM(LEFT(ares.niveau_resultat,1) = 'N') as n,
                   SUM(LEFT(ares.niveau_resultat,1) = 'I') as i
            FROM athlete_resultats ares
            JOIN epreuves e ON e.id_epreuve = ares.id_epreuve
            WHERE e.nom_epreuve IN ($epNamesList)
              AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != ''
            GROUP BY ares.id_epreuve
        ");
        if ($res) while ($row = $res->fetch_assoc()) {
            $epNiv[$row['nom_epreuve']] = _nivPct((int)$row['d'], (int)$row['r'], (int)$row['n'], (int)$row['i']);
        }
        foreach ($topEpreuves as &$ep) {
            $ep['niveaux_pct'] = $epNiv[$ep['epreuve']] ?? null;
        }
        unset($ep);
        $response['top_epreuves'] = $topEpreuves;
    }

    // 3. Niveaux par ville (uniquement les villes retenues)
    if (!empty($topVilles)) {
        $villeNames = array_map(function($v) use ($conn) { return "'" . $conn->real_escape_string($v['ville']) . "'"; }, $topVilles);
        $villeNamesList = implode(',', $villeNames);
        $villeNiv = [];
        $res = $conn->query("
            SELECT v.nom_ville,
                   SUM(LEFT(ares.niveau_resultat,1) = 'D') as d,
                   SUM(LEFT(ares.niveau_resultat,1) = 'R') as r,
                   SUM(LEFT(ares.niveau_resultat,1) = 'N') as n,
                   SUM(LEFT(ares.niveau_resultat,1) = 'I') as i
            FROM athlete_resultats ares
            JOIN villes v ON v.id_ville = ares.id_ville
            WHERE v.nom_ville IN ($villeNamesList)
              AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != ''
            GROUP BY ares.id_ville
        ");
        if ($res) while ($row = $res->fetch_assoc()) {
            $villeNiv[$row['nom_ville']] = _nivPct((int)$row['d'], (int)$row['r'], (int)$row['n'], (int)$row['i']);
        }
        foreach ($topVilles as &$v) {
            $v['niveaux_pct'] = $villeNiv[$v['ville']] ?? null;
        }
        unset($v);
        $response['top_villes'] = $topVilles;
    }

    // 4. Niveaux par athlete (uniquement les athletes retenus)
    $athIds = array_map(function($a) { return (int)$a['athlete_id']; }, $topAthletes);
    if (!empty($athIds)) {
        $extToInt = [];
        $idsList = implode(',', $athIds);
        $res = $conn->query("SELECT id_athlete, athlete_id_externe FROM athletes WHERE athlete_id_externe IN ($idsList)");
        if ($res) while ($row = $res->fetch_assoc()) {
            $extToInt[(int)$row['athlete_id_externe']] = (int)$row['id_athlete'];
        }
        $intIds = array_values($extToInt);
        if (!empty($intIds)) {
            $intIdsList = implode(',', $intIds);
            $athNiv = [];
            $res = $conn->query("
                SELECT ares.id_athlete,
                       SUM(LEFT(ares.niveau_resultat,1) = 'D') as d,
                       SUM(LEFT(ares.niveau_resultat,1) = 'R') as r,
                       SUM(LEFT(ares.niveau_resultat,1) = 'N') as n,
                       SUM(LEFT(ares.niveau_resultat,1) = 'I') as i
                FROM athlete_resultats ares
                WHERE ares.id_athlete IN ($intIdsList)
                  AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != ''
                GROUP BY ares.id_athlete
            ");
            if ($res) while ($row = $res->fetch_assoc()) {
                $athNiv[(int)$row['id_athlete']] = _nivPct((int)$row['d'], (int)$row['r'], (int)$row['n'], (int)$row['i']);
            }
            foreach ($topAthletes as &$a) {
                $intId = $extToInt[$a['athlete_id']] ?? null;
                $a['niveaux_pct'] = $intId ? ($athNiv[$intId] ?? null) : null;
            }
            unset($a);
        }
    }
    $response['top_athletes'] = $topAthletes;
}

// Sauvegarder en cache
$json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json, LOCK_EX);

// Proteger le dossier cache
$htaccess = $cacheDir . '/.htaccess';
if (!file_exists($htaccess)) @file_put_contents($htaccess, "Deny from all\n");

jsonResponse($response);
