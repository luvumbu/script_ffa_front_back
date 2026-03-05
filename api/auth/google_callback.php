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
    $h = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };
    $prenom = $givenName ?: 'Athlete';

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0;">';
    $html .= '<div style="max-width:600px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.1);">';

    // Header
    $html .= '<div style="background:linear-gradient(135deg,#6c5ce7,#5541d0);padding:40px;text-align:center;color:#fff;">';
    $html .= '<div style="font-size:48px;margin-bottom:12px;">&#127939;</div>';
    $html .= '<h1 style="margin:0 0 8px;font-size:28px;">Bienvenue sur Bokonzi !</h1>';
    $html .= '<p style="margin:0;opacity:0.9;font-size:16px;">La base de donnees de l\'athletisme francais</p>';
    $html .= '</div>';

    // Corps
    $html .= '<div style="padding:32px 40px;">';
    $html .= '<p style="font-size:16px;color:#333;line-height:1.6;">Bonjour <strong>' . $h($prenom) . '</strong>,</p>';
    $html .= '<p style="font-size:15px;color:#555;line-height:1.6;">Votre compte a ete cree avec succes. Vous avez maintenant acces a toutes les fonctionnalites de Bokonzi :</p>';

    $html .= '<div style="margin:24px 0;">';
    $features = [
        ['&#128269;', 'Recherche avancee', 'Explorez plus de 330 000 athletes avec 12 filtres combinables'],
        ['&#9825;', 'Suivi athletes & clubs', 'Suivez vos athletes et clubs favoris pour ne rien manquer'],
        ['&#128196;', 'Fiches PDF', 'Telechargez les fiches completes des athletes'],
        ['&#127942;', 'Classements', 'Consultez les classements par epreuve en temps reel'],
    ];
    foreach ($features as $f) {
        $html .= '<div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:16px;">';
        $html .= '<div style="font-size:24px;flex-shrink:0;">' . $f[0] . '</div>';
        $html .= '<div><strong style="color:#333;font-size:14px;">' . $h($f[1]) . '</strong><br><span style="color:#777;font-size:13px;">' . $h($f[2]) . '</span></div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '<div style="text-align:center;margin:32px 0 16px;">';
    $html .= '<a href="https://bokonzi.com" style="display:inline-block;background:linear-gradient(135deg,#6c5ce7,#5541d0);color:#fff;text-decoration:none;padding:14px 40px;border-radius:8px;font-size:16px;font-weight:600;">Explorer Bokonzi</a>';
    $html .= '</div>';

    $html .= '</div>';

    // Footer
    $html .= '<div style="padding:20px 40px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;color:#9ca3af;font-size:12px;">';
    $html .= '<p>Cet email a ete envoye automatiquement lors de la creation de votre compte.</p>';
    $html .= '<p><a href="https://bokonzi.com" style="color:#6c5ce7;">bokonzi.com</a></p>';
    $html .= '</div></div></body></html>';

    $subject = 'Bienvenue sur Bokonzi, ' . $prenom . ' !';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Bokonzi <noreply@bokonzi.com>\r\n";
    $headers .= "Reply-To: noreply@bokonzi.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $mailResult = mail($email, $subject, $html, $headers);
    // Log debug
    $logFile = __DIR__ . '/../../logs/.welcome_mail_log.php';
    $logData = "<?php die(); ?>\n" . json_encode([
        'time' => date('Y-m-d H:i:s'),
        'email' => $email,
        'prenom' => $prenom,
        'mail_result' => $mailResult,
        'userId' => $userId,
    ], JSON_UNESCAPED_UNICODE);
    file_put_contents($logFile, $logData);
}

// --- 8. Rediriger vers l'accueil ---
$conn->close();
$redirect = $homeUrl . ($isNewUser ? '?welcome=1' : '');
header('Location: ' . $redirect);
exit;
