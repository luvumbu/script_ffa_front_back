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

    $_p = htmlspecialchars($prenom);
    $html = '<html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:560px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
        . '<div style="background:linear-gradient(135deg,#6c5ce7,#5541d0);padding:36px 30px;text-align:center;">'
        . '<h1 style="color:#fff;font-size:28px;margin:0 0 6px;font-weight:800;">Bienvenue sur Bokonzi !</h1>'
        . '<p style="color:#e0d8ff;font-size:14px;margin:0;">La plus grande base de donnees de l\'athletisme francais</p>'
        . '</div>'
        . '<div style="padding:32px 30px;">'
        . '<p style="font-size:16px;color:#333;line-height:1.6;margin:0 0 20px;">Bonjour <b>' . $_p . '</b>,</p>'
        . '<p style="font-size:15px;color:#444;line-height:1.7;margin:0 0 24px;">Votre compte a ete cree avec succes. Vous pouvez desormais explorer librement toutes les donnees de l\'athletisme francais :</p>'
        . '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">'
        . '<tr><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:20px;width:36px;text-align:center;">&#128100;</td><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;color:#333;"><b style="color:#6c5ce7;">Consulter les profils</b> de plus de 330 000 athletes</td></tr>'
        . '<tr><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:20px;width:36px;text-align:center;">&#127963;</td><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;color:#333;"><b style="color:#10b981;">Decouvrir les clubs</b>, leurs athletes, epreuves et statistiques</td></tr>'
        . '<tr><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:20px;width:36px;text-align:center;">&#128202;</td><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;color:#333;"><b style="color:#3b82f6;">Analyser les performances</b>, records, progressions et classements</td></tr>'
        . '<tr><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:20px;width:36px;text-align:center;">&#9878;</td><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;color:#333;"><b style="color:#f59e0b;">Comparer des athletes</b> ou des clubs entre eux</td></tr>'
        . '<tr><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:20px;width:36px;text-align:center;">&#11088;</td><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;color:#333;"><b style="color:#c026d3;">Suivre vos favoris</b> et retrouver vos athletes preferes</td></tr>'
        . '<tr><td style="padding:10px 12px;font-size:20px;width:36px;text-align:center;">&#128196;</td><td style="padding:10px 12px;font-size:14px;color:#333;"><b style="color:#ea580c;">Telecharger des fiches PDF</b> avec le palmares complet</td></tr>'
        . '</table>'
        . '<p style="text-align:center;margin:0 0 24px;"><a href="https://bokonzi.com" style="display:inline-block;background:linear-gradient(135deg,#6c5ce7,#5541d0);color:#fff;padding:14px 40px;text-decoration:none;border-radius:8px;font-size:16px;font-weight:700;">Explorer Bokonzi</a></p>'
        . '<p style="font-size:13px;color:#999;line-height:1.5;margin:0;">Bonne exploration ' . $_p . ', et a tres bientot sur Bokonzi !</p>'
        . '</div>'
        . '<div style="background:#f9fafb;padding:20px 30px;text-align:center;border-top:1px solid #f0f0f0;">'
        . '<p style="font-size:11px;color:#999;margin:0;">Bokonzi — Base de donnees Athletisme francais<br><a href="https://bokonzi.com" style="color:#6c5ce7;text-decoration:none;">bokonzi.com</a></p>'
        . '</div>'
        . '</div></body></html>';

    $subject = 'Bienvenue ' . $prenom . ' - Bokonzi';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Bokonzi <noreply@bokonzi.com>\r\n";
    $headers .= "Reply-To: noreply@bokonzi.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    @mail($email, $subject, $html, $headers);

    // Notification admin : nouvel inscrit
    $adminHtml = '<html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:500px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
        . '<div style="background:#10b981;padding:24px 30px;text-align:center;">'
        . '<h1 style="color:#fff;font-size:22px;margin:0;font-weight:800;">Nouvel inscrit sur Bokonzi</h1>'
        . '</div>'
        . '<div style="padding:24px 30px;">'
        . '<table style="width:100%;border-collapse:collapse;">'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;width:100px;">Prenom</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($givenName ?: '-') . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Nom</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($familyName ?: '-') . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Email</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($email) . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Date</td><td style="padding:8px 0;color:#333;font-size:14px;">' . date('d/m/Y H:i:s') . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">IP</td><td style="padding:8px 0;color:#333;font-size:14px;">' . htmlspecialchars($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '') . '</td></tr>'
        . '</table>'
        . '<p style="text-align:center;margin:20px 0 0;"><a href="https://bokonzi.com/admin/panel.php" style="display:inline-block;background:#6c5ce7;color:#fff;padding:10px 24px;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;">Voir le panel admin</a></p>'
        . '</div></div></body></html>';
    require_once __DIR__ . '/../../core/mailer.php';
    bkMail('luvumbu.n@gmail.com', 'Nouvel inscrit : ' . ($givenName ?: '') . ' ' . ($familyName ?: '') . ' - Bokonzi', $adminHtml);
    // Mail rapide a contact@bokonzi.com
    bkMail('contact@bokonzi.com', 'Inscription : ' . $email, '<p>L\'adresse <b>' . htmlspecialchars($email) . '</b> vient de s\'inscrire sur Bokonzi (Google).</p><p>' . date('d/m/Y H:i:s') . '</p>');
} else {
    // Notification admin : connexion d'un user existant via Google
    $ipAddr = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    // [DEBUG TEMPORAIRE] log entree dans le else
    @file_put_contents(__DIR__ . '/../../logs/login_debug.log', date('Y-m-d H:i:s') . " | ELSE branch reached | email=$email | isNewUser=" . ($isNewUser ? '1' : '0') . "\n", FILE_APPEND);
    $loginHtml = '<html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:500px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
        . '<div style="background:#3b82f6;padding:24px 30px;text-align:center;">'
        . '<h1 style="color:#fff;font-size:22px;margin:0;font-weight:800;">Connexion sur Bokonzi</h1>'
        . '<p style="color:#dbeafe;font-size:13px;margin:6px 0 0;">via Google</p>'
        . '</div>'
        . '<div style="padding:24px 30px;">'
        . '<table style="width:100%;border-collapse:collapse;">'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;width:100px;">Prenom</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($givenName ?: '-') . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Nom</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($familyName ?: '-') . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Email</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($email) . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Date</td><td style="padding:8px 0;color:#333;font-size:14px;">' . date('d/m/Y H:i:s') . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">IP</td><td style="padding:8px 0;color:#333;font-size:14px;">' . htmlspecialchars($ipAddr) . '</td></tr>'
        . '</table>'
        . '<p style="text-align:center;margin:20px 0 0;"><a href="https://bokonzi.com/admin/panel.php" style="display:inline-block;background:#6c5ce7;color:#fff;padding:10px 24px;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;">Voir le panel admin</a></p>'
        . '</div></div></body></html>';
    require_once __DIR__ . '/../../core/mailer.php';
    $mailResult = bkMail('luvumbu.n@gmail.com', 'Connexion Google : ' . $email . ' - Bokonzi', $loginHtml);
    @file_put_contents(__DIR__ . '/../../logs/login_debug.log', date('Y-m-d H:i:s') . " | bkMail result = " . ($mailResult ? 'TRUE' : 'FALSE') . " | to=luvumbu.n@gmail.com\n", FILE_APPEND);

    // Notification utilisateur : confirmation de connexion
    $_p = htmlspecialchars($givenName ?: 'Athlete');
    $userHtml = '<html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:500px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
        . '<div style="background:#3b82f6;padding:24px 30px;text-align:center;">'
        . '<h1 style="color:#fff;font-size:22px;margin:0;font-weight:800;">Nouvelle connexion sur Bokonzi</h1>'
        . '</div>'
        . '<div style="padding:24px 30px;">'
        . '<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">Bonjour <b>' . $_p . '</b>,</p>'
        . '<p style="font-size:14px;color:#444;line-height:1.6;margin:0 0 16px;">Une connexion a ete effectuee sur votre compte Bokonzi via Google.</p>'
        . '<table style="width:100%;border-collapse:collapse;background:#f9fafb;border-radius:8px;margin-bottom:16px;">'
        . '<tr><td style="padding:10px 14px;color:#888;font-size:13px;width:80px;">Date</td><td style="padding:10px 14px;color:#333;font-size:14px;">' . date('d/m/Y H:i:s') . '</td></tr>'
        . '<tr><td style="padding:10px 14px;color:#888;font-size:13px;">IP</td><td style="padding:10px 14px;color:#333;font-size:14px;">' . htmlspecialchars($ipAddr) . '</td></tr>'
        . '</table>'
        . '<p style="font-size:13px;color:#888;line-height:1.6;margin:0;">Si ce n\'etait pas vous, contactez-nous immediatement.</p>'
        . '</div></div></body></html>';
    bkMail($email, 'Connexion sur Bokonzi', $userHtml);
    // Mail rapide a contact@bokonzi.com
    bkMail('contact@bokonzi.com', 'Connexion : ' . $email, '<p>L\'adresse <b>' . htmlspecialchars($email) . '</b> vient de se connecter sur Bokonzi (Google).</p><p>' . date('d/m/Y H:i:s') . '</p>');
}

// --- 8. Rediriger vers l'accueil ---
$conn->close();
$redirect = $homeUrl . ($isNewUser ? '?welcome=1' : '');
header('Location: ' . $redirect);
exit;
