<?php
/**
 * api/classement.php — Classement des athletes par epreuve / categorie / sexe
 * GET params: epreuve (id), categorie (code), sexe (M/F), annee, limit, offset
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/visibility.php';
$_isAdminInt = isAdminViewing() ? 1 : 0;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Methode GET requise'], 405);
}

$idEpreuve   = isset($_GET['epreuve']) ? intval($_GET['epreuve']) : 0;
$categorie   = trim($_GET['categorie'] ?? '');
$sexe        = strtoupper(trim($_GET['sexe'] ?? ''));
$annee       = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$limit       = min(max(intval($_GET['limit'] ?? 50), 1), 200);
$offset      = max(intval($_GET['offset'] ?? 0), 0);

if ($idEpreuve <= 0) {
    jsonResponse(['success' => false, 'error' => 'Parametre epreuve (id) requis'], 400);
}

// Construire la requete
$sql = "SELECT
            a.id_athlete, a.nom_complet_athlete, a.sexe_athlete, a.categorie_athlete,
            c.nom_club,
            p.performance_brut_progression AS performance,
            p.performance_progression AS perf_int,
            p.date_progression,
            p.annee_progression,
            e.nom_epreuve
        FROM athlete_progressions p
        JOIN athletes a ON a.id_athlete = p.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
        JOIN epreuves e ON e.id_epreuve = p.id_epreuve
        LEFT JOIN athlete_clubs ac ON ac.id_athlete = a.id_athlete
        LEFT JOIN clubs c ON c.id_club = ac.id_club
        WHERE p.id_epreuve = ?";

$params = [$idEpreuve];
$types  = "i";

if ($sexe === 'M' || $sexe === 'F') {
    $sql .= " AND a.sexe_athlete = ?";
    $params[] = $sexe;
    $types .= "s";
}

if (!empty($categorie)) {
    $sql .= " AND a.categorie_athlete = ?";
    $params[] = $categorie;
    $types .= "s";
}

if ($annee > 0) {
    $sql .= " AND p.annee_progression = ?";
    $params[] = $annee;
    $types .= "i";
}

// Grouper par athlete (meilleure perf uniquement)
$sql .= " AND p.performance_progression IS NOT NULL AND p.performance_progression > 0";

// Detecter si c'est une epreuve de temps (course) ou de distance (lancers, sauts)
// Les epreuves de course ont generalement des performances en centisecondes plus petites
// On trie ASC pour les courses (plus petit = meilleur), DESC pour les concours
$eprStmt = $conn->prepare("SELECT nom_epreuve FROM epreuves WHERE id_epreuve = ?");
$eprStmt->bind_param("i", $idEpreuve);
$eprStmt->execute();
$eprResult = $eprStmt->get_result()->fetch_assoc();
$eprStmt->close();

$nomEpreuve = strtolower($eprResult['nom_epreuve'] ?? '');
$isDistance = preg_match('/(poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon|pentathlon)/i', $nomEpreuve);
$sortOrder = $isDistance ? 'DESC' : 'ASC';

// Sous-requete pour meilleure perf par athlete
$sql = "SELECT ranked.* FROM (
    SELECT
        a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete, a.sexe_athlete, a.categorie_athlete,
        c.nom_club,
        p.performance_brut_progression AS performance,
        p.performance_progression AS perf_int,
        p.date_progression,
        p.annee_progression,
        e.nom_epreuve,
        ROW_NUMBER() OVER (PARTITION BY a.id_athlete ORDER BY p.performance_progression $sortOrder) as rn
    FROM athlete_progressions p
    JOIN athletes a ON a.id_athlete = p.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    JOIN epreuves e ON e.id_epreuve = p.id_epreuve
    LEFT JOIN (
        SELECT id_athlete, id_club
        FROM athlete_clubs
        GROUP BY id_athlete
    ) ac ON ac.id_athlete = a.id_athlete
    LEFT JOIN clubs c ON c.id_club = ac.id_club
    WHERE p.id_epreuve = ?
    AND p.performance_progression IS NOT NULL
    AND p.performance_progression > 0
    AND a.visible = 1";

// Reconstruire les params
$params = [$idEpreuve];
$types  = "i";

if ($sexe === 'M' || $sexe === 'F') {
    $sql .= " AND a.sexe_athlete = ?";
    $params[] = $sexe;
    $types .= "s";
}
if (!empty($categorie)) {
    $sql .= " AND a.categorie_athlete = ?";
    $params[] = $categorie;
    $types .= "s";
}
if ($annee > 0) {
    $sql .= " AND p.annee_progression = ?";
    $params[] = $annee;
    $types .= "i";
}

$sql .= ") ranked WHERE ranked.rn = 1 ORDER BY ranked.perf_int $sortOrder LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$athletes = [];
$rang = $offset + 1;
while ($row = $result->fetch_assoc()) {
    unset($row['rn']);
    $row['rang'] = $rang++;
    $athletes[] = $row;
}
$stmt->close();

// Compter le total
$countSql = "SELECT COUNT(DISTINCT a.id_athlete) as total
    FROM athlete_progressions p
    JOIN athletes a ON a.id_athlete = p.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    WHERE p.id_epreuve = ?
    AND p.performance_progression IS NOT NULL
    AND p.performance_progression > 0
    AND a.visible = 1";

$countParams = [$idEpreuve];
$countTypes  = "i";

if ($sexe === 'M' || $sexe === 'F') {
    $countSql .= " AND a.sexe_athlete = ?";
    $countParams[] = $sexe;
    $countTypes .= "s";
}
if (!empty($categorie)) {
    $countSql .= " AND a.categorie_athlete = ?";
    $countParams[] = $categorie;
    $countTypes .= "s";
}
if ($annee > 0) {
    $countSql .= " AND p.annee_progression = ?";
    $countParams[] = $annee;
    $countTypes .= "i";
}

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

jsonResponse([
    'success'    => true,
    'total'      => (int) $total,
    'limit'      => $limit,
    'offset'     => $offset,
    'sort'       => $sortOrder,
    'classement' => $athletes,
]);
