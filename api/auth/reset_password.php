<?php
/**
 * api/auth/reset_password.php — Reinitialisation du mot de passe
 * POST : { token, password }
 * Valide le token, met a jour le mot de passe, invalide les sessions
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../core/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Methode POST requise'], 405);
}

// Auto-creation table si elle n'existe pas
$conn->query("CREATE TABLE IF NOT EXISTS `password_resets` (
    `id_reset` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `id_user` INT UNSIGNED NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `expire_at` DATETIME NOT NULL,
    `used` TINYINT(1) UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pr_token` (`token`),
    INDEX `idx_pr_user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$input = json_decode(file_get_contents('php://input'), true);
$token    = trim($input['token'] ?? '');
$password = $input['password'] ?? '';

if (empty($token)) {
    jsonResponse(['success' => false, 'error' => 'Token requis'], 400);
}

if (empty($password) || mb_strlen($password) < 8) {
    jsonResponse(['success' => false, 'error' => 'Le mot de passe doit contenir au moins 8 caracteres'], 400);
}

// Verifier le token
$stmt = $conn->prepare(
    "SELECT pr.id_reset, pr.id_user, pr.expire_at, pr.used, u.email
     FROM password_resets pr
     JOIN users u ON u.id_user = pr.id_user
     WHERE pr.token = ?"
);
$stmt->bind_param("s", $token);
$stmt->execute();
$reset = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reset) {
    jsonResponse(['success' => false, 'error' => 'Lien de reinitialisation invalide'], 400);
}

if ($reset['used']) {
    jsonResponse(['success' => false, 'error' => 'Ce lien a deja ete utilise'], 400);
}

if (strtotime($reset['expire_at']) < time()) {
    jsonResponse(['success' => false, 'error' => 'Ce lien a expire. Veuillez faire une nouvelle demande.'], 400);
}

// Mettre a jour le mot de passe
$hash = hashPassword($password);
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id_user = ?");
$stmt->bind_param("si", $hash, $reset['id_user']);
$stmt->execute();
$stmt->close();

// Marquer le token comme utilise
$stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id_reset = ?");
$stmt->bind_param("i", $reset['id_reset']);
$stmt->execute();
$stmt->close();

// Invalider toutes les sessions existantes (securite)
$stmt = $conn->prepare("DELETE FROM user_sessions WHERE id_user = ?");
$stmt->bind_param("i", $reset['id_user']);
$stmt->execute();
$stmt->close();

jsonResponse([
    'success' => true,
    'message' => 'Mot de passe reinitialise avec succes. Vous pouvez maintenant vous connecter.',
]);
