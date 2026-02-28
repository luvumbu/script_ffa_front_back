<?php
/**
 * api/follow.php — Suivi d'athletes et clubs par email
 *
 * POST : toggle follow/unfollow { email, athlete_id } ou { email, club_id }
 * GET  : check status ?email=X&athlete_id=Y ou ?email=X&club_id=Y
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !is_array($input)) {
        jsonResponse(['success' => false, 'error' => 'JSON invalide'], 400);
    }

    $athleteId = (int)($input['athlete_id'] ?? 0);
    $clubId    = (int)($input['club_id'] ?? 0);

    if ($athleteId <= 0 && $clubId <= 0) {
        jsonResponse(['success' => false, 'error' => 'athlete_id ou club_id requis'], 400);
    }

    // Email : soit user connecte, soit fourni
    $user = getCurrentUser($conn);
    $email = '';
    $userId = null;

    if ($user) {
        $email = $user['email'];
        $userId = (int)$user['id_user'];
    } else {
        $email = trim($input['email'] ?? '');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'error' => 'Email invalide'], 400);
    }

    $emailEsc = $conn->real_escape_string($email);
    $userIdSQL = $userId ? $userId : 'NULL';

    if ($clubId > 0) {
        // === CLUB FOLLOW ===
        $res = $conn->query("SELECT id_follow FROM club_follows WHERE email = '$emailEsc' AND club_id = $clubId LIMIT 1");
        $exists = $res && $res->num_rows > 0;

        if ($exists) {
            $conn->query("DELETE FROM club_follows WHERE email = '$emailEsc' AND club_id = $clubId");
            $following = false;
        } else {
            $conn->query("INSERT INTO club_follows (email, club_id, id_user) VALUES ('$emailEsc', $clubId, $userIdSQL)");
            $following = true;
        }

        $res = $conn->query("SELECT COUNT(*) as cnt FROM club_follows WHERE club_id = $clubId");
        $count = $res ? (int)$res->fetch_assoc()['cnt'] : 0;
    } else {
        // === ATHLETE FOLLOW ===
        $res = $conn->query("SELECT id_follow FROM athlete_follows WHERE email = '$emailEsc' AND athlete_id_ext = $athleteId LIMIT 1");
        $exists = $res && $res->num_rows > 0;

        if ($exists) {
            $conn->query("DELETE FROM athlete_follows WHERE email = '$emailEsc' AND athlete_id_ext = $athleteId");
            $following = false;
        } else {
            $conn->query("INSERT INTO athlete_follows (email, athlete_id_ext, id_user) VALUES ('$emailEsc', $athleteId, $userIdSQL)");
            $following = true;
        }

        $res = $conn->query("SELECT COUNT(*) as cnt FROM athlete_follows WHERE athlete_id_ext = $athleteId");
        $count = $res ? (int)$res->fetch_assoc()['cnt'] : 0;
    }

    jsonResponse(['success' => true, 'following' => $following, 'count' => $count]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $athleteId = (int)($_GET['athlete_id'] ?? 0);
    $clubId    = (int)($_GET['club_id'] ?? 0);

    if ($athleteId <= 0 && $clubId <= 0) {
        jsonResponse(['success' => false, 'error' => 'athlete_id ou club_id requis'], 400);
    }

    // Email : soit user connecte, soit param
    $user = getCurrentUser($conn);
    $email = '';
    if ($user) {
        $email = $user['email'];
    } else {
        $email = trim($_GET['email'] ?? '');
    }

    $following = false;

    if ($clubId > 0) {
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailEsc = $conn->real_escape_string($email);
            $res = $conn->query("SELECT id_follow FROM club_follows WHERE email = '$emailEsc' AND club_id = $clubId LIMIT 1");
            $following = $res && $res->num_rows > 0;
        }
        $res = $conn->query("SELECT COUNT(*) as cnt FROM club_follows WHERE club_id = $clubId");
        $count = $res ? (int)$res->fetch_assoc()['cnt'] : 0;
    } else {
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailEsc = $conn->real_escape_string($email);
            $res = $conn->query("SELECT id_follow FROM athlete_follows WHERE email = '$emailEsc' AND athlete_id_ext = $athleteId LIMIT 1");
            $following = $res && $res->num_rows > 0;
        }
        $res = $conn->query("SELECT COUNT(*) as cnt FROM athlete_follows WHERE athlete_id_ext = $athleteId");
        $count = $res ? (int)$res->fetch_assoc()['cnt'] : 0;
    }

    jsonResponse(['success' => true, 'following' => $following, 'count' => $count]);

} else {
    jsonResponse(['success' => false, 'error' => 'Methode non supportee'], 405);
}
