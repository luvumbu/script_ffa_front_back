<?php
/**
 * core/auth.php — Bibliotheque d'authentification Bokonzi
 * Gestion des sessions par token cookie + BDD
 */

/**
 * Hash un mot de passe
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verifie un mot de passe contre son hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Genere un token de session aleatoire (64 caracteres hex)
 */
function generateToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Cree une session en BDD et set le cookie
 * @param mysqli $conn
 * @param int $userId
 * @return string Le token genere
 */
function createSession($conn, $userId) {
    $token = generateToken();
    $expireAt = date('Y-m-d H:i:s', strtotime('+30 days'));

    $stmt = $conn->prepare("INSERT INTO user_sessions (id_user, token, expire_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $token, $expireAt);
    $stmt->execute();
    $stmt->close();

    setcookie('bk_token', $token, [
        'expires'  => strtotime('+30 days'),
        'path'     => '/',
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);

    return $token;
}

/**
 * Recupere l'utilisateur connecte a partir du cookie bk_token
 * @param mysqli $conn
 * @return array|null Donnees utilisateur ou null
 */
function getCurrentUser($conn) {
    if (empty($_COOKIE['bk_token'])) {
        return null;
    }

    $token = $_COOKIE['bk_token'];
    $stmt = $conn->prepare(
        "SELECT u.id_user, u.email, u.nom, u.prenom, u.role, u.id_athlete
         FROM user_sessions s
         JOIN users u ON u.id_user = s.id_user
         WHERE s.token = ? AND s.expire_at > NOW()"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

/**
 * Exige une authentification, redirige vers login.php sinon
 * @param mysqli $conn
 * @return array Donnees utilisateur
 */
function requireAuth($conn) {
    $user = getCurrentUser($conn);
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

/**
 * Exige un role specifique
 * @param mysqli $conn
 * @param array $roles Roles autorises ex: ['coach', 'admin']
 * @return array Donnees utilisateur
 */
function requireRole($conn, $roles) {
    $user = requireAuth($conn);
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo "Acces interdit";
        exit;
    }
    return $user;
}

/**
 * Deconnexion : supprime la session BDD + cookie
 * @param mysqli $conn
 */
function logout($conn) {
    if (!empty($_COOKIE['bk_token'])) {
        $token = $_COOKIE['bk_token'];
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
    }

    setcookie('bk_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Exige une authentification pour les endpoints API (retourne JSON au lieu de redirect)
 * @param mysqli $conn
 * @return array Donnees utilisateur
 */
function requireAuthApi($conn) {
    $user = getCurrentUser($conn);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non authentifie']);
        exit;
    }
    return $user;
}
