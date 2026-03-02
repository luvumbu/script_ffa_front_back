<?php
/**
 * api/auth/google_callback.php — Callback Google OAuth
 * Verifie le state CSRF, echange le code contre un token,
 * recupere le profil, cree/lie le user, cree la session BDD.
 */
session_start();

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/oauth_config.php';

$loginUrl = $isLocal ? '/BK/login.php' : '/login.php';
$homeUrl  = $isLocal ? '/BK/index.php' : '/index.php';

// --- 1. Verifier le state CSRF ---
$state = $_GET['state'] ?? '';
if (empty($state) || !isset($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
    unset($_SESSION['oauth_state']);
    header('Location: ' . $loginUrl . '?error=google&msg=state');
    exit;
}
unset($_SESSION['oauth_state']);

// --- 2. Verifier qu'on a un code ---
$code = $_GET['code'] ?? '';
if (empty($code)) {
    header('Location: ' . $loginUrl . '?error=google&msg=nocode');
    exit;
}

// --- 3. Echanger le code contre un access token ---
$tokenData = [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
];

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($tokenData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 10,
]);
$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$tokenResponse) {
    header('Location: ' . $loginUrl . '?error=google&msg=token');
    exit;
}

$tokenJson = json_decode($tokenResponse, true);
$accessToken = $tokenJson['access_token'] ?? '';
if (empty($accessToken)) {
    header('Location: ' . $loginUrl . '?error=google&msg=notoken');
    exit;
}

// --- 4. Recuperer le profil Google ---
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    CURLOPT_TIMEOUT        => 10,
]);
$profileResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$profileResponse) {
    header('Location: ' . $loginUrl . '?error=google&msg=profile');
    exit;
}

$profile = json_decode($profileResponse, true);
$googleId    = $profile['id'] ?? '';
$email       = $profile['email'] ?? '';
$givenName   = $profile['given_name'] ?? '';
$familyName  = $profile['family_name'] ?? '';

if (empty($googleId) || empty($email)) {
    header('Location: ' . $loginUrl . '?error=google&msg=noemail');
    exit;
}

// --- 5. Trouver ou creer le user ---
$userId = null;

// 5a. Chercher par google_id
$stmt = $conn->prepare("SELECT id_user FROM users WHERE google_id = ?");
$stmt->bind_param("s", $googleId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    // User deja lie a ce google_id → login direct
    $userId = (int)$row['id_user'];
} else {
    // 5b. Chercher par email (compte classique existant)
    $stmt = $conn->prepare("SELECT id_user FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        // Lier le google_id au compte existant
        $userId = (int)$row['id_user'];
        $stmt = $conn->prepare("UPDATE users SET google_id = ?, oauth_provider = 'google' WHERE id_user = ?");
        $stmt->bind_param("si", $googleId, $userId);
        $stmt->execute();
        $stmt->close();
    } else {
        // 5c. Creer un nouveau user
        $stmt = $conn->prepare(
            "INSERT INTO users (email, password_hash, nom, prenom, role, google_id, oauth_provider) VALUES (?, '', ?, ?, 'athlete', ?, 'google')"
        );
        $stmt->bind_param("ssss", $email, $familyName, $givenName, $googleId);
        $stmt->execute();
        $userId = (int)$stmt->insert_id;
        $stmt->close();
    }
}

// --- 6. Creer la session (meme systeme que login classique) ---
createSession($conn, $userId);

// --- 7. Rediriger vers l'accueil ---
$conn->close();
header('Location: ' . $homeUrl);
exit;
