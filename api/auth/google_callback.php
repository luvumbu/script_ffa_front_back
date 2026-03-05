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
$googleId      = $profile['id'] ?? '';
$email         = $profile['email'] ?? '';
$givenName     = $profile['given_name'] ?? '';
$familyName    = $profile['family_name'] ?? '';
$picture       = $profile['picture'] ?? null;
$emailVerified = isset($profile['verified_email']) ? ($profile['verified_email'] ? 1 : 0) : null;
$locale        = $profile['locale'] ?? null;

if (empty($googleId) || empty($email)) {
    header('Location: ' . $loginUrl . '?error=google&msg=noemail');
    exit;
}

// --- 4b. Nettoyer l'URL photo Google (forcer taille 200px) ---
if (!empty($picture)) {
    $picture = preg_replace('/=s\d+-c/', '=s200-c', $picture);
}

// --- 5. Trouver ou creer le user ---
$userId = null;
$isNewUser = false;

// 5a. Chercher par google_id
$stmt = $conn->prepare("SELECT id_user FROM users WHERE google_id = ?");
$stmt->bind_param("s", $googleId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    // User deja lie a ce google_id → login direct
    $userId = (int)$row['id_user'];
    // Mettre a jour les infos Google (photo, locale, etc.) a chaque login
    $stmt = $conn->prepare("UPDATE users SET picture = ?, email_verified = ?, locale = ?, last_login = NOW() WHERE id_user = ?");
    $stmt->bind_param("sisi", $picture, $emailVerified, $locale, $userId);
    $stmt->execute();
    $stmt->close();
} else {
    // 5b. Chercher par email (compte classique existant)
    $stmt = $conn->prepare("SELECT id_user FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        // Lier le google_id au compte existant + infos profil
        $userId = (int)$row['id_user'];
        $stmt = $conn->prepare("UPDATE users SET google_id = ?, oauth_provider = 'google', picture = ?, email_verified = ?, locale = ?, last_login = NOW() WHERE id_user = ?");
        $stmt->bind_param("ssisi", $googleId, $picture, $emailVerified, $locale, $userId);
        $stmt->execute();
        $stmt->close();
    } else {
        // 5c. Creer un nouveau user avec toutes les infos Google
        $isNewUser = true;
        $stmt = $conn->prepare(
            "INSERT INTO users (email, password_hash, nom, prenom, role, google_id, oauth_provider, picture, email_verified, locale, last_login) VALUES (?, '', ?, ?, 'athlete', ?, 'google', ?, ?, ?, NOW())"
        );
        $stmt->bind_param("sssssss", $email, $familyName, $givenName, $googleId, $picture, $emailVerified, $locale);
        $stmt->execute();
        $userId = (int)$stmt->insert_id;
        $stmt->close();
    }
}

// --- 6. Creer la session (meme systeme que login classique) ---
createSession($conn, $userId);

// --- 7. Email de bienvenue pour les nouveaux users ---
if ($isNewUser) {
    $prenom = $givenName ?: 'Athlete';

    $html = '<html><body style="font-family:Arial,sans-serif;margin:0;padding:20px;background:#f4f4f4;">'
        . '<div style="max-width:500px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;">'
        . '<h1 style="color:#6c5ce7;text-align:center;">Bienvenue sur Bokonzi !</h1>'
        . '<p>Bonjour <b>' . htmlspecialchars($prenom) . '</b>,</p>'
        . '<p>Votre compte a ete cree avec succes. Vous pouvez maintenant :</p>'
        . '<ul>'
        . '<li>Rechercher parmi 330 000+ athletes</li>'
        . '<li>Suivre vos athletes et clubs favoris</li>'
        . '<li>Telecharger des fiches PDF</li>'
        . '<li>Consulter les classements en temps reel</li>'
        . '</ul>'
        . '<p style="text-align:center;margin-top:20px;"><a href="https://bokonzi.com" style="background:#6c5ce7;color:#fff;padding:12px 30px;text-decoration:none;border-radius:6px;">Explorer Bokonzi</a></p>'
        . '<p style="color:#999;font-size:11px;text-align:center;margin-top:20px;">bokonzi.com</p>'
        . '</div></body></html>';

    $subject = 'Bienvenue sur Bokonzi, ' . $prenom . ' !';
    $headers = "From: noreply@bokonzi.com\r\nContent-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0";
    mail($email, $subject, $html, $headers);
}

// --- 8. Rediriger vers l'accueil ---
$conn->close();
$redirect = $homeUrl . ($isNewUser ? '?welcome=1' : '');
header('Location: ' . $redirect);
exit;
