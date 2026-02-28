<?php
/**
 * api/auth/register.php — Inscription d'un nouvel utilisateur
 * POST : email, password, nom, prenom, role (optionnel)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../core/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Methode POST requise'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$nom      = trim($input['nom'] ?? '');
$prenom   = trim($input['prenom'] ?? '');
$role     = $input['role'] ?? 'athlete';

// Validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'Email invalide'], 400);
}
if (strlen($password) < 8) {
    jsonResponse(['success' => false, 'error' => 'Mot de passe minimum 8 caracteres'], 400);
}
$validRoles = ['athlete', 'coach', 'club'];
if (!in_array($role, $validRoles, true)) {
    $role = 'athlete';
}

// Verifier unicite email
$stmt = $conn->prepare("SELECT id_user FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $stmt->close();
    jsonResponse(['success' => false, 'error' => 'Cet email est deja utilise'], 409);
}
$stmt->close();

// Insertion
$hash = hashPassword($password);
$stmt = $conn->prepare("INSERT INTO users (email, password_hash, nom, prenom, role) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $email, $hash, $nom, $prenom, $role);
$stmt->execute();
$userId = $stmt->insert_id;
$stmt->close();

// Creer session automatiquement
$token = createSession($conn, $userId);

jsonResponse([
    'success' => true,
    'token'   => $token,
    'user'    => [
        'id_user' => $userId,
        'email'   => $email,
        'nom'     => $nom,
        'prenom'  => $prenom,
        'role'    => $role,
    ],
], 201);
