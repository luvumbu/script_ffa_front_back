<?php
/**
 * api/performances.php — CRUD performances manuelles
 * GET    : liste perfs (param id_athlete)
 * POST   : ajouter une perf (auth requise)
 * PUT    : modifier une perf (auteur seulement)
 * DELETE : supprimer une perf (auteur seulement)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET : lister les performances ───────────────────────────────
if ($method === 'GET') {
    $idAthlete = intval($_GET['id_athlete'] ?? 0);
    if ($idAthlete <= 0) {
        jsonResponse(['success' => false, 'error' => 'Parametre id_athlete requis'], 400);
    }

    $stmt = $conn->prepare(
        "SELECT pm.id_perf, pm.id_athlete, pm.id_user, pm.id_epreuve,
                pm.performance, pm.performance_brut, pm.date_perf, pm.lieu, pm.notes,
                pm.created_at, pm.updated_at,
                e.nom_epreuve,
                u.prenom AS auteur_prenom, u.nom AS auteur_nom
         FROM athlete_perfs_manuelles pm
         LEFT JOIN epreuves e ON e.id_epreuve = pm.id_epreuve
         LEFT JOIN users u ON u.id_user = pm.id_user
         WHERE pm.id_athlete = ?
         ORDER BY pm.date_perf DESC, pm.created_at DESC"
    );
    $stmt->bind_param("i", $idAthlete);
    $stmt->execute();
    $result = $stmt->get_result();

    $perfs = [];
    while ($row = $result->fetch_assoc()) {
        $perfs[] = $row;
    }
    $stmt->close();

    jsonResponse(['success' => true, 'performances' => $perfs]);
}

// ─── POST : ajouter une performance ─────────────────────────────
if ($method === 'POST') {
    $user = requireAuthApi($conn);
    $input = json_decode(file_get_contents('php://input'), true);

    $idAthlete   = intval($input['id_athlete'] ?? 0);
    $idEpreuve   = intval($input['id_epreuve'] ?? 0);
    $perfBrut    = trim($input['performance_brut'] ?? '');
    $performance = intval($input['performance'] ?? 0);
    $datePerf    = trim($input['date_perf'] ?? '');
    $lieu        = trim($input['lieu'] ?? '');
    $notes       = trim($input['notes'] ?? '');

    if ($idAthlete <= 0) {
        jsonResponse(['success' => false, 'error' => 'id_athlete requis'], 400);
    }
    if (empty($perfBrut)) {
        jsonResponse(['success' => false, 'error' => 'performance_brut requis'], 400);
    }

    $stmt = $conn->prepare(
        "INSERT INTO athlete_perfs_manuelles
            (id_athlete, id_user, id_epreuve, performance, performance_brut, date_perf, lieu, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $idEpreuveOrNull = $idEpreuve > 0 ? $idEpreuve : null;
    $perfOrNull      = $performance > 0 ? $performance : null;
    $dateOrNull      = !empty($datePerf) ? $datePerf : null;

    $stmt->bind_param("iiisssss",
        $idAthlete, $user['id_user'], $idEpreuveOrNull,
        $perfOrNull, $perfBrut, $dateOrNull, $lieu, $notes
    );
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    jsonResponse(['success' => true, 'id_perf' => $newId], 201);
}

// ─── PUT : modifier une performance ─────────────────────────────
if ($method === 'PUT') {
    $user = requireAuthApi($conn);
    $input = json_decode(file_get_contents('php://input'), true);

    $idPerf = intval($input['id_perf'] ?? 0);
    if ($idPerf <= 0) {
        jsonResponse(['success' => false, 'error' => 'id_perf requis'], 400);
    }

    // Verifier que l'auteur est le meme (ou admin)
    $stmt = $conn->prepare("SELECT id_user FROM athlete_perfs_manuelles WHERE id_perf = ?");
    $stmt->bind_param("i", $idPerf);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        jsonResponse(['success' => false, 'error' => 'Performance introuvable'], 404);
    }
    if ($existing['id_user'] != $user['id_user'] && $user['role'] !== 'admin') {
        jsonResponse(['success' => false, 'error' => 'Non autorise'], 403);
    }

    $sets = [];
    $params = [];
    $types = "";

    if (isset($input['id_epreuve'])) {
        $sets[] = "id_epreuve = ?";
        $params[] = intval($input['id_epreuve']) ?: null;
        $types .= "i";
    }
    if (isset($input['performance'])) {
        $sets[] = "performance = ?";
        $params[] = intval($input['performance']) ?: null;
        $types .= "i";
    }
    if (isset($input['performance_brut'])) {
        $sets[] = "performance_brut = ?";
        $params[] = $input['performance_brut'];
        $types .= "s";
    }
    if (isset($input['date_perf'])) {
        $sets[] = "date_perf = ?";
        $params[] = $input['date_perf'] ?: null;
        $types .= "s";
    }
    if (isset($input['lieu'])) {
        $sets[] = "lieu = ?";
        $params[] = $input['lieu'];
        $types .= "s";
    }
    if (isset($input['notes'])) {
        $sets[] = "notes = ?";
        $params[] = $input['notes'];
        $types .= "s";
    }

    if (empty($sets)) {
        jsonResponse(['success' => false, 'error' => 'Rien a modifier'], 400);
    }

    $sql = "UPDATE athlete_perfs_manuelles SET " . implode(", ", $sets) . " WHERE id_perf = ?";
    $params[] = $idPerf;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    jsonResponse(['success' => true, 'message' => 'Performance modifiee']);
}

// ─── DELETE : supprimer une performance ──────────────────────────
if ($method === 'DELETE') {
    $user = requireAuthApi($conn);
    $input = json_decode(file_get_contents('php://input'), true);

    $idPerf = intval($input['id_perf'] ?? $_GET['id_perf'] ?? 0);
    if ($idPerf <= 0) {
        jsonResponse(['success' => false, 'error' => 'id_perf requis'], 400);
    }

    // Verifier auteur
    $stmt = $conn->prepare("SELECT id_user FROM athlete_perfs_manuelles WHERE id_perf = ?");
    $stmt->bind_param("i", $idPerf);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        jsonResponse(['success' => false, 'error' => 'Performance introuvable'], 404);
    }
    if ($existing['id_user'] != $user['id_user'] && $user['role'] !== 'admin') {
        jsonResponse(['success' => false, 'error' => 'Non autorise'], 403);
    }

    $stmt = $conn->prepare("DELETE FROM athlete_perfs_manuelles WHERE id_perf = ?");
    $stmt->bind_param("i", $idPerf);
    $stmt->execute();
    $stmt->close();

    jsonResponse(['success' => true, 'message' => 'Performance supprimee']);
}

jsonResponse(['success' => false, 'error' => 'Methode non supportee'], 405);
