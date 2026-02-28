<?php
/**
 * api/competitions.php — Liste des competitions avec nombre de medailles + periode
 *
 * Usage :
 *   api/competitions.php                  Toutes les competitions
 *   api/competitions.php?nom=france       Recherche par nom
 *   api/competitions.php?page=2           Pagination
 */

require_once __DIR__ . '/config.php';

$nom    = trim($_GET['nom'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

$where = "";
if ($nom !== '') {
    $nomEsc = $conn->real_escape_string($nom);
    $where = "WHERE co.nom_competition LIKE '%$nomEsc%'";
}

$res = $conn->query("SELECT COUNT(*) as c FROM competitions co $where");
$total = $res ? (int)$res->fetch_assoc()['c'] : 0;

$sql = "SELECT co.id_competition, co.nom_competition,
               COUNT(DISTINCT am.id_athlete) as nb_athletes,
               MIN(am.annee_medaille) as annee_debut,
               MAX(am.annee_medaille) as annee_fin
        FROM competitions co
        LEFT JOIN athlete_medailles am ON am.id_competition = co.id_competition
        $where
        GROUP BY co.id_competition
        ORDER BY nb_athletes DESC
        LIMIT $limit OFFSET $offset";

$res = $conn->query($sql);
$competitions = [];

if ($res) while ($row = $res->fetch_assoc()) {
    $competitions[] = [
        'id_competition'  => (int)$row['id_competition'],
        'nom_competition' => $row['nom_competition'],
        'nb_athletes'     => (int)$row['nb_athletes'],
        'annee_debut'     => $row['annee_debut'] ? (int)$row['annee_debut'] : null,
        'annee_fin'       => $row['annee_fin'] ? (int)$row['annee_fin'] : null,
    ];
}

jsonResponse([
    'success'      => true,
    'total'        => $total,
    'page'         => $page,
    'limit'        => $limit,
    'total_pages'  => ceil($total / $limit),
    'competitions' => $competitions,
]);
