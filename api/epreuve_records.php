<?php
/**
 * api/epreuve_records.php — Records pour une epreuve donnee
 * GET params: nom (nom_epreuve)
 * Retourne: liste des records (athlete, performance, date, club)
 */
require_once __DIR__ . '/config.php';

$nom = trim($_GET['nom'] ?? '');
if ($nom === '') {
    jsonResponse(['success' => false, 'error' => 'Parametre nom requis'], 400);
}

$nomEsc = $conn->real_escape_string($nom);

// Trouver l'epreuve
$stmt = $conn->prepare("SELECT id_epreuve, nom_epreuve FROM epreuves WHERE nom_epreuve = ? LIMIT 1");
$stmt->bind_param("s", $nom);
$stmt->execute();
$epreuve = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$epreuve) {
    jsonResponse(['success' => false, 'error' => 'Epreuve non trouvee'], 404);
}

$eid = (int) $epreuve['id_epreuve'];

// Nombre total d'athletes avec cette epreuve
$res = $conn->query("SELECT COUNT(DISTINCT id_athlete) as c FROM athlete_records WHERE id_epreuve = $eid");
$totalAthletes = $res ? (int) $res->fetch_assoc()['c'] : 0;

// Records (top 50, tries par performance)
$records = [];
$res = $conn->query("
    SELECT a.nom_complet_athlete, a.athlete_id_externe, a.categorie_athlete, a.sexe_athlete, a.nationalite_athlete,
           ar.performance_brut_record, ar.date_record,
           c.nom_club,
           (SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
            FROM athlete_resultats ares
            WHERE ares.id_athlete = ar.id_athlete AND ares.id_epreuve = ar.id_epreuve
              AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
    FROM athlete_records ar
    JOIN athletes a ON a.id_athlete = ar.id_athlete
    LEFT JOIN athlete_clubs ac ON ac.id_athlete = a.id_athlete
    LEFT JOIN clubs c ON c.id_club = ac.id_club
    WHERE ar.id_epreuve = $eid
    ORDER BY ar.performance_record " . (preg_match('/(poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon|pentathlon)/i', $epreuve['nom_epreuve']) ? 'DESC' : 'ASC') . ", ar.date_record DESC
    LIMIT 50
");
if ($res) while ($row = $res->fetch_assoc()) {
    $nivList = array_filter(explode(',', $row['niveaux'] ?? ''));
    $records[] = [
        'athlete'     => $row['nom_complet_athlete'],
        'athlete_id'  => (int) $row['athlete_id_externe'],
        'categorie'   => $row['categorie_athlete'],
        'sexe'        => $row['sexe_athlete'],
        'nationalite' => $row['nationalite_athlete'],
        'performance' => $row['performance_brut_record'],
        'date'        => $row['date_record'],
        'club'        => $row['nom_club'],
        'niveaux'     => array_values($nivList),
    ];
}

// Nationalites presentes
$nationalites = [];
$res = $conn->query("
    SELECT a.nationalite_athlete, COUNT(DISTINCT a.id_athlete) as c
    FROM athlete_records ar
    JOIN athletes a ON a.id_athlete = ar.id_athlete
    WHERE ar.id_epreuve = $eid AND a.nationalite_athlete IS NOT NULL AND a.nationalite_athlete != ''
    GROUP BY a.nationalite_athlete ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $nationalites[$row['nationalite_athlete']] = (int) $row['c'];
}

// Categories presentes
$categories = [];
$res = $conn->query("
    SELECT a.categorie_athlete, COUNT(DISTINCT a.id_athlete) as c
    FROM athlete_records ar
    JOIN athletes a ON a.id_athlete = ar.id_athlete
    WHERE ar.id_epreuve = $eid AND a.categorie_athlete IS NOT NULL AND a.categorie_athlete != ''
    GROUP BY a.categorie_athlete ORDER BY c DESC
");
if ($res) while ($row = $res->fetch_assoc()) {
    $categories[$row['categorie_athlete']] = (int) $row['c'];
}

jsonResponse([
    'success'        => true,
    'epreuve'        => $epreuve['nom_epreuve'],
    'total_athletes' => $totalAthletes,
    'records'        => $records,
    'nationalites'   => $nationalites,
    'categories'     => $categories,
]);
