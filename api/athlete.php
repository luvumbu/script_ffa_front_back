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

$idExterne = $_GET['id'] ?? null;
$idInterne = $_GET['id_athlete'] ?? null;
$licence   = $_GET['licence'] ?? null;

if (!$idExterne && !$idInterne && !$licence) {
    jsonResponse(['success' => false, 'error' => 'Parametre ?id=, ?id_athlete= ou ?licence= requis'], 400);
}

// ---- Cache fichier (24h) ----
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheKey = 'athlete_' . md5(($idExterne ?? '') . '_' . ($idInterne ?? '') . '_' . ($licence ?? ''));
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
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

// Profil masque : aucune info
if (isset($athlete['visible']) && (int)$athlete['visible'] === 0) {
    jsonResponse(['success' => false, 'visible' => false, 'error' => 'Profil non disponible'], 404);
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
    'date_naissance'   => $athlete['date_naissance_athlete'],
    'annee_naissance'  => $athlete['annee_naissance_athlete'] ? (int)$athlete['annee_naissance_athlete'] : null,
    'lieu_naissance'   => $villeNaissance,
    'categorie'        => $athlete['categorie_athlete'],
    'sexe'             => $athlete['sexe_athlete'],
    'nationalite'      => $athlete['nationalite_athlete'],
    'taille_cm'        => $athlete['taille_cm_athlete'] ? (int)$athlete['taille_cm_athlete'] : null,
    'poids_kg'         => $athlete['poids_kg_athlete'] ? (int)$athlete['poids_kg_athlete'] : null,
    'licence'          => $athlete['licence_athlete'],
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
}

// 6. Progressions
$progressions = [];
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

// Reponse finale
$resp = [
    'success'      => true,
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
@file_put_contents($cacheFile, $json, LOCK_EX);
jsonResponse($resp);
