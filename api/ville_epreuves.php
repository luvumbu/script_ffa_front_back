<?php
/**
 * api/ville_epreuves.php — Recherche d'epreuves dans une ville
 * GET params: nom (nom_ville), q (recherche epreuve), limit
 */
require_once __DIR__ . '/config.php';

$nomVille = trim($_GET['nom'] ?? '');
$q        = trim($_GET['q'] ?? '');
$limit    = min(50, max(1, (int)($_GET['limit'] ?? 20)));

if ($nomVille === '') {
    jsonResponse(['success' => false, 'error' => 'Parametre nom requis'], 400);
}

// Trouver la ville
$stmt = $conn->prepare("SELECT id_ville FROM villes WHERE nom_ville = ? LIMIT 1");
$stmt->bind_param("s", $nomVille);
$stmt->execute();
$ville = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ville) {
    jsonResponse(['success' => false, 'error' => 'Ville non trouvee'], 404);
}

$vid = (int) $ville['id_ville'];

$qWhere = '';
if ($q !== '') {
    $qEsc = $conn->real_escape_string($q);
    $qWhere = " AND e.nom_epreuve LIKE '%$qEsc%'";
}

$res = $conn->query("
    SELECT e.nom_epreuve, COUNT(*) as nb_resultats, COUNT(DISTINCT ar.id_athlete) as nb_athletes,
           MIN(ar.annee_resultat) as annee_debut, MAX(ar.annee_resultat) as annee_fin
    FROM athlete_resultats ar
    JOIN epreuves e ON e.id_epreuve = ar.id_epreuve
    WHERE ar.id_ville = $vid$qWhere
    GROUP BY ar.id_epreuve
    ORDER BY nb_resultats DESC
    LIMIT $limit
");

$epreuves = [];
if ($res) while ($row = $res->fetch_assoc()) {
    $epreuves[] = [
        'epreuve'      => $row['nom_epreuve'],
        'nb_resultats' => (int) $row['nb_resultats'],
        'nb_athletes'  => (int) $row['nb_athletes'],
        'annee_debut'  => $row['annee_debut'] ? (int) $row['annee_debut'] : null,
        'annee_fin'    => $row['annee_fin'] ? (int) $row['annee_fin'] : null,
    ];
}

jsonResponse([
    'success'  => true,
    'total'    => count($epreuves),
    'epreuves' => $epreuves,
]);
