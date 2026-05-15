<?php
/**
 * api/auth/register.php — Inscription classique (email/mot de passe)
 * POST JSON : { email, password, nom, prenom }
 * Si le compte existe deja → on met a jour le mdp et on connecte.
 */

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Methode POST requise'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email    = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');
$nom      = trim($input['nom'] ?? '');
$prenom   = trim($input['prenom'] ?? '');

if (empty($email) || empty($password) || empty($nom) || empty($prenom)) {
    jsonResponse(['success' => false, 'error' => 'Tous les champs sont obligatoires'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'Adresse email invalide'], 400);
}
if (strlen($password) < 6) {
    jsonResponse(['success' => false, 'error' => 'Mot de passe : 6 caracteres minimum'], 400);
}

// Check si email existe
$stmt = $conn->prepare("SELECT id_user FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isNewUser = false;
if ($existing) {
    // Compte existe → mettre a jour mdp + nom/prenom et connecter
    $userId = (int)$existing['id_user'];
    $hash = hashPassword($password);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ?, nom = ?, prenom = ?, last_login = NOW() WHERE id_user = ?");
    $stmt->bind_param("sssi", $hash, $nom, $prenom, $userId);
    $stmt->execute();
    $stmt->close();
} else {
    // Nouveau compte
    $isNewUser = true;
    $hash = hashPassword($password);
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, nom, prenom, role, oauth_provider, email_verified, last_login) VALUES (?, ?, ?, ?, 'athlete', 'email', 0, NOW())");
    $stmt->bind_param("ssss", $email, $hash, $nom, $prenom);
    $stmt->execute();
    $userId = (int)$stmt->insert_id;
    $stmt->close();
    if ($userId <= 0) {
        jsonResponse(['success' => false, 'error' => 'Erreur creation du compte'], 500);
    }
}

// Session immediate
createSession($conn, $userId);

// Envoyer mail de bienvenue (on tente, si ca marche tant mieux)
$isLocal = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
$baseUrl = $isLocal ? 'http://localhost/BK' : 'https://bokonzi.com';
$_p = htmlspecialchars($prenom);
$html = '<html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">'
    . '<div style="max-width:560px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
    // Header violet
    . '<div style="background:linear-gradient(135deg,#6c5ce7,#5541d0);padding:36px 30px;text-align:center;">'
    . '<h1 style="color:#fff;font-size:28px;margin:0 0 6px;font-weight:800;">Bienvenue sur Bokonzi !</h1>'
    . '<p style="color:#e0d8ff;font-size:14px;margin:0;">La plus grande base de donnees de l\'athletisme francais</p>'
    . '</div>'
    // Corps
    . '<div style="padding:32px 30px;">'
    . '<p style="font-size:16px;color:#333;line-height:1.6;margin:0 0 20px;">Bonjour <b>' . $_p . '</b>,</p>'
    . '<p style="font-size:15px;color:#444;line-height:1.7;margin:0 0 24px;">Votre compte a ete cree avec succes. Vous pouvez desormais explorer librement toutes les donnees de l\'athletisme francais :</p>'
    // Liste des fonctionnalites
    . '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">'
    . '<tr><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:20px;width:36px;text-align:center;">&#128100;</td><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;color:#333;"><b style="color:#6c5ce7;">Consulter les profils</b> de plus de 330 000 athletes</td></tr>'
    . '<tr><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:20px;width:36px;text-align:center;">&#127963;</td><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;color:#333;"><b style="color:#10b981;">Decouvrir les clubs</b>, leurs athletes, epreuves et statistiques</td></tr>'
    . '<tr><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:20px;width:36px;text-align:center;">&#128202;</td><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;color:#333;"><b style="color:#3b82f6;">Analyser les performances</b>, records, progressions et classements</td></tr>'
    . '<tr><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:20px;width:36px;text-align:center;">&#9878;</td><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;color:#333;"><b style="color:#f59e0b;">Comparer des athletes</b> ou des clubs entre eux</td></tr>'
    . '<tr><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:20px;width:36px;text-align:center;">&#11088;</td><td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;color:#333;"><b style="color:#c026d3;">Suivre vos favoris</b> et retrouver vos athletes preferes</td></tr>'
    . '<tr><td style="padding:10px 12px;font-size:20px;width:36px;text-align:center;">&#128196;</td><td style="padding:10px 12px;font-size:14px;color:#333;"><b style="color:#ea580c;">Telecharger des fiches PDF</b> avec le palmares complet</td></tr>'
    . '</table>'
    // Bouton CTA
    . '<p style="text-align:center;margin:0 0 24px;"><a href="' . $baseUrl . '" style="display:inline-block;background:linear-gradient(135deg,#6c5ce7,#5541d0);color:#fff;padding:14px 40px;text-decoration:none;border-radius:8px;font-size:16px;font-weight:700;">Explorer Bokonzi</a></p>'
    . '<p style="font-size:13px;color:#999;line-height:1.5;margin:0;">Bonne exploration ' . $_p . ', et a tres bientot sur Bokonzi !</p>'
    . '</div>'
    // Footer
    . '<div style="background:#f9fafb;padding:20px 30px;text-align:center;border-top:1px solid #f0f0f0;">'
    . '<p style="font-size:11px;color:#999;margin:0;">Bokonzi — Base de donnees Athletisme francais<br><a href="' . $baseUrl . '" style="color:#6c5ce7;text-decoration:none;">bokonzi.com</a></p>'
    . '</div>'
    . '</div></body></html>';

$mH  = "MIME-Version: 1.0\r\n";
$mH .= "Content-Type: text/html; charset=UTF-8\r\n";
$mH .= "From: Bokonzi <noreply@bokonzi.com>\r\n";
$mH .= "Reply-To: noreply@bokonzi.com\r\n";
$mH .= "X-Mailer: PHP/" . phpversion() . "\r\n";
@mail($email, 'Bienvenue ' . $prenom . ' - Bokonzi', $html, $mH);

// Notification admin : nouvel inscrit (uniquement nouveaux comptes)
if ($isNewUser) {
    $adminHtml = '<html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:500px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">'
        . '<div style="background:#10b981;padding:24px 30px;text-align:center;">'
        . '<h1 style="color:#fff;font-size:22px;margin:0;font-weight:800;">Nouvel inscrit sur Bokonzi</h1>'
        . '<p style="color:#d1fae5;font-size:13px;margin:6px 0 0;">Inscription par email</p>'
        . '</div>'
        . '<div style="padding:24px 30px;">'
        . '<table style="width:100%;border-collapse:collapse;">'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;width:100px;">Prenom</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($prenom ?: '-') . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Nom</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($nom ?: '-') . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Email</td><td style="padding:8px 0;color:#333;font-size:14px;font-weight:600;">' . htmlspecialchars($email) . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">Date</td><td style="padding:8px 0;color:#333;font-size:14px;">' . date('d/m/Y H:i:s') . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#888;font-size:13px;">IP</td><td style="padding:8px 0;color:#333;font-size:14px;">' . htmlspecialchars($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '') . '</td></tr>'
        . '</table>'
        . '<p style="text-align:center;margin:20px 0 0;"><a href="https://bokonzi.com/admin/panel.php" style="display:inline-block;background:#6c5ce7;color:#fff;padding:10px 24px;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;">Voir le panel admin</a></p>'
        . '</div></div></body></html>';
    require_once __DIR__ . '/../../core/mailer.php';
    bkMail('luvumbu.n@gmail.com', 'Nouvel inscrit : ' . $prenom . ' ' . $nom . ' - Bokonzi', $adminHtml);
    // Mail rapide a contact@bokonzi.com
    bkMail('contact@bokonzi.com', 'Inscription : ' . $email, '<p>L\'adresse <b>' . htmlspecialchars($email) . '</b> vient de s\'inscrire sur Bokonzi (email/password).</p><p>' . date('d/m/Y H:i:s') . '</p>');
}

$redirect = ($isLocal ? '/BK' : '') . '/index.php?welcome=1';
jsonResponse(['success' => true, 'redirect' => $redirect]);
