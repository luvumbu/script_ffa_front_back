<?php
/**
 * api/admin_athlete.php — Edition athlete depuis le panel admin
 *
 * Auth : cookie bk_sa_token (super admin) OU bk_token (user avec panel_access)
 *
 * GET  ?id_ext=X        : retourne tous les champs editables
 * POST { id_ext, ... }  : met a jour les champs + vide cache
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

// --- Auto-creation colonnes bio_override + admin_note ---
$_bc = @$conn->query("SHOW COLUMNS FROM `athletes` LIKE 'bio_override'");
if ($_bc && $_bc->num_rows === 0) {
    @$conn->query("ALTER TABLE `athletes` ADD COLUMN `bio_override` TEXT NULL DEFAULT NULL");
}
if ($_bc) $_bc->free();
$_ac = @$conn->query("SHOW COLUMNS FROM `athletes` LIKE 'admin_note'");
if ($_ac && $_ac->num_rows === 0) {
    @$conn->query("ALTER TABLE `athletes` ADD COLUMN `admin_note` TEXT NULL DEFAULT NULL");
}
if ($_ac) $_ac->free();

// --- Auth admin ---
$_isAdmin = false;
if (!empty($_COOKIE['bk_sa_token'])) {
    $saFile = __DIR__ . '/../logs/.sa_sessions.php';
    if (file_exists($saFile)) {
        $saRaw = file_get_contents($saFile);
        $saPos = strpos($saRaw, "\n");
        if ($saPos !== false) {
            $saSessions = json_decode(substr($saRaw, $saPos + 1), true) ?: [];
            $_isAdmin = isset($saSessions[$_COOKIE['bk_sa_token']]) && ($saSessions[$_COOKIE['bk_sa_token']]['expires'] ?? 0) > time();
        }
    }
}
if (!$_isAdmin) {
    $pUser = getCurrentUser($conn);
    if ($pUser) {
        $paFile = __DIR__ . '/../logs/.panel_access.php';
        if (file_exists($paFile)) {
            $paRaw = file_get_contents($paFile);
            $paPos = strpos($paRaw, "\n");
            if ($paPos !== false) {
                $paList = json_decode(substr($paRaw, $paPos + 1), true) ?: [];
                $_isAdmin = isset($paList[strtolower($pUser['email'])]);
            }
        }
    }
}

if (!$_isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Acces refuse']);
    exit;
}

// --- Helper : vider cache athlete par id externe ---
function _bk_purge_athlete_cache($idExt) {
    $cacheDir = __DIR__ . '/../cache';
    $files = glob($cacheDir . '/athlete_*.json');
    if (!$files) return 0;
    $n = 0;
    foreach ($files as $f) {
        $json = @file_get_contents($f);
        if ($json && strpos($json, '"' . (int)$idExt . '"') !== false) {
            if (@unlink($f)) $n++;
        }
    }
    // Plus simple : on vide tous les caches athlete pour etre certain
    foreach ($files as $f) { @unlink($f); }
    return $n;
}

// =========================
// GET : retourne les champs
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $idExt = (int)($_GET['id_ext'] ?? 0);
    if ($idExt <= 0) {
        echo json_encode(['error' => 'id_ext requis']);
        exit;
    }
    $stmt = $conn->prepare("
        SELECT a.id_athlete, a.athlete_id_externe, a.nom_1_athlete, a.nom_2_athlete,
               a.nom_3_athlete, a.nom_4_athlete, a.nom_complet_athlete,
               a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete,
               a.taille_cm_athlete, a.poids_kg_athlete,
               a.id_ville_naissance, a.visible, a.bio_override, a.admin_note,
               v.nom_ville AS lieu_naissance
        FROM athletes a
        LEFT JOIN villes v ON v.id_ville = a.id_ville_naissance
        WHERE a.athlete_id_externe = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $idExt);
    $stmt->execute();
    $r = $stmt->get_result();
    if (!$r || $r->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Athlete non trouve']);
        exit;
    }
    $a = $r->fetch_assoc();
    $stmt->close();

    // Club actuel (le plus recent)
    $idInt = (int)$a['id_athlete'];
    $clubsArr = [];
    $rc = $conn->query("
        SELECT c.id_club, c.nom_club, ac.annee_debut, ac.annee_fin
        FROM athlete_clubs ac
        JOIN clubs c ON c.id_club = ac.id_club
        WHERE ac.id_athlete = $idInt
        ORDER BY ac.annee_debut DESC, ac.annee_fin DESC
    ");
    if ($rc) while ($row = $rc->fetch_assoc()) {
        $clubsArr[] = [
            'id_club'      => (int)$row['id_club'],
            'nom_club'     => $row['nom_club'],
            'annee_debut'  => $row['annee_debut'] ? (int)$row['annee_debut'] : null,
            'annee_fin'    => $row['annee_fin'] ? (int)$row['annee_fin'] : null,
        ];
    }

    echo json_encode([
        'success' => true,
        'athlete' => [
            'id_athlete'      => (int)$a['id_athlete'],
            'id_ext'          => (int)$a['athlete_id_externe'],
            'nom_1'           => $a['nom_1_athlete'] ?? '',
            'nom_2'           => $a['nom_2_athlete'] ?? '',
            'nom_3'           => $a['nom_3_athlete'] ?? '',
            'nom_4'           => $a['nom_4_athlete'] ?? '',
            'nom_complet'     => $a['nom_complet_athlete'] ?? '',
            'sexe'            => $a['sexe_athlete'] ?? '',
            'categorie'       => $a['categorie_athlete'] ?? '',
            'nationalite'     => $a['nationalite_athlete'] ?? '',
            'taille_cm'       => $a['taille_cm_athlete'] ? (int)$a['taille_cm_athlete'] : null,
            'poids_kg'        => $a['poids_kg_athlete'] ? (int)$a['poids_kg_athlete'] : null,
            'lieu_naissance'  => $a['lieu_naissance'] ?? '',
            'id_ville_naissance' => $a['id_ville_naissance'] ? (int)$a['id_ville_naissance'] : null,
            'visible'         => (int)$a['visible'],
            'bio_override'    => $a['bio_override'] ?? '',
            'admin_note'      => $a['admin_note'] ?? '',
            'clubs'           => $clubsArr,
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================
// POST : update
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    $idExt = (int)($input['id_ext'] ?? 0);
    if ($idExt <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'id_ext requis']);
        exit;
    }

    // Verifier que l'athlete existe + recup id interne
    $stmt = $conn->prepare("SELECT id_athlete FROM athletes WHERE athlete_id_externe = ? LIMIT 1");
    $stmt->bind_param('i', $idExt);
    $stmt->execute();
    $r = $stmt->get_result();
    if (!$r || $r->num_rows === 0) {
        $stmt->close();
        http_response_code(404);
        echo json_encode(['error' => 'Athlete non trouve']);
        exit;
    }
    $idInt = (int)$r->fetch_assoc()['id_athlete'];
    $stmt->close();

    // Champs autorises et leur validation
    $fields = [];
    $params = [];
    $types  = '';

    $textFields = [
        'nom_1'       => ['nom_1_athlete', 100],
        'nom_2'       => ['nom_2_athlete', 100],
        'nom_3'       => ['nom_3_athlete', 100],
        'nom_4'       => ['nom_4_athlete', 100],
        'nom_complet' => ['nom_complet_athlete', 200],
        'sexe'        => ['sexe_athlete', 1],
        'categorie'   => ['categorie_athlete', 10],
        'nationalite' => ['nationalite_athlete', 3],
    ];
    foreach ($textFields as $k => $meta) {
        if (array_key_exists($k, $input)) {
            $col = $meta[0]; $max = $meta[1];
            $val = trim((string)$input[$k]);
            if ($k === 'sexe') $val = strtoupper(substr($val, 0, 1));
            if ($k === 'nationalite') $val = strtoupper(substr($val, 0, 3));
            if ($k === 'categorie') $val = strtoupper(substr($val, 0, 10));
            if (mb_strlen($val) > $max) $val = mb_substr($val, 0, $max);
            $fields[] = "`$col` = ?";
            $params[] = $val;
            $types  .= 's';
        }
    }

    if (array_key_exists('taille_cm', $input)) {
        $v = (int)$input['taille_cm'];
        if ($v <= 0 || $v > 300) {
            $fields[] = "`taille_cm_athlete` = NULL";
        } else {
            $fields[] = "`taille_cm_athlete` = ?";
            $params[] = $v; $types .= 'i';
        }
    }
    if (array_key_exists('poids_kg', $input)) {
        $v = (int)$input['poids_kg'];
        if ($v <= 0 || $v > 500) {
            $fields[] = "`poids_kg_athlete` = NULL";
        } else {
            $fields[] = "`poids_kg_athlete` = ?";
            $params[] = $v; $types .= 'i';
        }
    }

    // Lieu de naissance : on cherche ou cree la ville
    if (array_key_exists('lieu_naissance', $input)) {
        $ville = trim((string)$input['lieu_naissance']);
        if ($ville === '') {
            $fields[] = "`id_ville_naissance` = NULL";
        } else {
            $stmtV = $conn->prepare("SELECT id_ville FROM villes WHERE nom_ville = ? LIMIT 1");
            $stmtV->bind_param('s', $ville);
            $stmtV->execute();
            $rv = $stmtV->get_result();
            $idV = 0;
            if ($rv && $rv->num_rows > 0) {
                $idV = (int)$rv->fetch_assoc()['id_ville'];
            }
            $stmtV->close();
            if ($idV === 0) {
                $stmtI = $conn->prepare("INSERT INTO villes (nom_ville) VALUES (?)");
                $stmtI->bind_param('s', $ville);
                $stmtI->execute();
                $idV = (int)$stmtI->insert_id;
                $stmtI->close();
            }
            if ($idV > 0) {
                $fields[] = "`id_ville_naissance` = ?";
                $params[] = $idV; $types .= 'i';
            }
        }
    }

    if (array_key_exists('visible', $input)) {
        $v = (int)$input['visible'] === 1 ? 1 : 0;
        $fields[] = "`visible` = ?";
        $params[] = $v; $types .= 'i';
    }

    if (array_key_exists('bio_override', $input)) {
        $v = trim((string)$input['bio_override']);
        if (mb_strlen($v) > 20000) $v = mb_substr($v, 0, 20000);
        if ($v === '') {
            $fields[] = "`bio_override` = NULL";
        } else {
            $fields[] = "`bio_override` = ?";
            $params[] = $v; $types .= 's';
        }
    }

    if (array_key_exists('admin_note', $input)) {
        $v = trim((string)$input['admin_note']);
        if (mb_strlen($v) > 5000) $v = mb_substr($v, 0, 5000);
        if ($v === '') {
            $fields[] = "`admin_note` = NULL";
        } else {
            $fields[] = "`admin_note` = ?";
            $params[] = $v; $types .= 's';
        }
    }

    if (empty($fields)) {
        echo json_encode(['success' => true, 'updated' => 0, 'msg' => 'Aucun champ a mettre a jour']);
        exit;
    }

    $sql = "UPDATE athletes SET " . implode(', ', $fields) . " WHERE athlete_id_externe = ?";
    $params[] = $idExt; $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed: ' . $conn->error]);
        exit;
    }

    _bk_purge_athlete_cache($idExt);

    echo json_encode(['success' => true, 'updated' => $affected, 'id_ext' => $idExt]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode non autorisee']);
