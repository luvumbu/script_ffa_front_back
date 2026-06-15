<?php
/**
 * api/classement.php — Classement des athletes par epreuve / categorie / sexe
 *
 * Source : table `athlete_records` (records personnels).
 * NB : la table `athlete_progressions` a ete deportee en fichier JSONL
 *      (config/data_source.json), elle est vide en BDD — on ne peut donc plus
 *      l'utiliser ici. `athlete_records` contient la meilleure perf par
 *      athlete/epreuve et reste indexee : c'est la source adaptee au classement.
 *
 * GET params: epreuve (id), categorie (code), sexe (M/F), annee, limit, offset
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/visibility.php';
$_isAdminInt = isAdminViewing() ? 1 : 0;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Methode GET requise'], 405);
}

$idEpreuve = isset($_GET['epreuve']) ? intval($_GET['epreuve']) : 0;
$categorie = trim($_GET['categorie'] ?? '');
$sexe      = strtoupper(trim($_GET['sexe'] ?? ''));
$annee     = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$limit     = min(max(intval($_GET['limit'] ?? 50), 1), 200);
$offset    = max(intval($_GET['offset'] ?? 0), 0);

if ($idEpreuve <= 0) {
    jsonResponse(['success' => false, 'error' => 'Parametre epreuve (id) requis'], 400);
}

// Nom de l'epreuve → detecter course (temps, tri ASC) vs concours (distance, tri DESC)
$eprStmt = $conn->prepare("SELECT nom_epreuve FROM epreuves WHERE id_epreuve = ?");
$eprStmt->bind_param("i", $idEpreuve);
$eprStmt->execute();
$eprResult = $eprStmt->get_result()->fetch_assoc();
$eprStmt->close();

if (!$eprResult) {
    jsonResponse(['success' => false, 'error' => 'Epreuve introuvable'], 404);
}

$nomEpreuve = strtolower($eprResult['nom_epreuve'] ?? '');
$isDistance = preg_match('/(poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon|pentathlon)/i', $nomEpreuve);
$sortOrder  = $isDistance ? 'DESC' : 'ASC';

// Filtres communs (WHERE) — partages entre la requete principale et le COUNT.
// NOT REGEXP : ecarte les bruts parasites "chiffres nus" 1-3 caracteres
// (ex: un 100m enregistre "5" ou "9") qui polluent le haut du classement.
$where  = " WHERE r.id_epreuve = ? AND r.performance_record IS NOT NULL AND r.performance_record > 0"
        . " AND r.performance_brut_record NOT REGEXP '^[0-9]{1,3}$'";
$params = [$idEpreuve];
$types  = "i";

if ($sexe === 'M' || $sexe === 'F') {
    $where .= " AND a.sexe_athlete = ?";
    $params[] = $sexe;
    $types  .= "s";
}
if ($categorie !== '') {
    $where .= " AND a.categorie_athlete = ?";
    $params[] = $categorie;
    $types  .= "s";
}
if ($annee > 0) {
    $where .= " AND YEAR(r.date_record) = ?";
    $params[] = $annee;
    $types  .= "i";
}

// Requete principale : meilleure perf par athlete (ROW_NUMBER dedoublonne),
// puis tri global + pagination.
$sql = "SELECT ranked.* FROM (
    SELECT
        a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete, a.sexe_athlete, a.categorie_athlete,
        c.nom_club,
        r.performance_brut_record AS performance,
        r.performance_record AS perf_int,
        r.date_record AS date_progression,
        YEAR(r.date_record) AS annee_progression,
        e.nom_epreuve,
        ROW_NUMBER() OVER (PARTITION BY a.id_athlete ORDER BY r.performance_record $sortOrder) AS rn
    FROM athlete_records r
    JOIN athletes a ON a.id_athlete = r.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    JOIN epreuves e ON e.id_epreuve = r.id_epreuve
    LEFT JOIN clubs c ON c.id_club = r.id_club
    $where
) ranked
WHERE ranked.rn = 1
ORDER BY ranked.perf_int $sortOrder
LIMIT ? OFFSET ?";

$mainParams = array_merge($params, [$limit, $offset]);
$mainTypes  = $types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($mainTypes, ...$mainParams);
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

// Total : nombre d'athletes distincts ayant un record valide sur cette epreuve
$countSql = "SELECT COUNT(DISTINCT a.id_athlete) AS total
    FROM athlete_records r
    JOIN athletes a ON a.id_athlete = r.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
    $where";

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

jsonResponse([
    'success'    => true,
    'total'      => $total,
    'limit'      => $limit,
    'offset'     => $offset,
    'sort'       => $sortOrder,
    'classement' => $athletes,
]);
