<?php
/**
 * test_mail_ndenga.php — Simule exactement ce que fait register.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<pre style="font-family:monospace;background:#0d1117;color:#c9d1d9;padding:20px;border-radius:8px;max-width:700px;margin:40px auto;">';

// 1. Charger les memes fichiers que register.php
echo "=== 1. CHARGEMENT CONFIG ===\n";
try {
    require_once __DIR__ . '/api/config.php';
    echo "config.php: OK\n";
    echo "conn: " . ($conn ? 'CONNECTE' : 'NULL') . "\n";
} catch (Exception $e) {
    echo "ERREUR config: " . $e->getMessage() . "\n";
    echo '</pre>';
    exit;
}

// 2. Donnees test
$email = 'ndenga.lu@gmail.com';
$password = 'test123456';
$nom = 'Test';
$prenom = 'Ndenga';

// 3. Verifier si email existe
echo "\n=== 2. CHECK EMAIL EN BDD ===\n";
$stmt = $conn->prepare("SELECT id_user, email_verified, password_hash, oauth_provider FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    echo "Email EXISTE en BDD:\n";
    echo "  id_user: " . $existing['id_user'] . "\n";
    echo "  email_verified: " . var_export($existing['email_verified'], true) . "\n";
    echo "  password_hash: " . ($existing['password_hash'] === '' ? 'VIDE' : 'REMPLI (' . strlen($existing['password_hash']) . ' chars)') . "\n";
    echo "  oauth_provider: " . var_export($existing['oauth_provider'], true) . "\n";

    $userId = (int)$existing['id_user'];

    // Simuler la logique de register.php
    if ($existing['password_hash'] === '' && !empty($existing['oauth_provider'])) {
        echo "\n>>> CAS: Compte Google sans mdp → ajout mdp\n";
    } else {
        echo "\n>>> CAS: Compte classique existant → BLOQUE (erreur 'email deja utilisee')\n";
        echo ">>> C'EST ICI QUE CA BLOQUE. Le mail n'est JAMAIS envoye.\n";
        echo "\nSolution: supprimer le user ou utiliser un autre email.\n";

        // Envoyer le mail quand meme pour tester
        echo "\n=== 3. ENVOI MAIL DE TEST QUAND MEME ===\n";
        goto testMail;
    }
} else {
    echo "Email PAS en BDD → nouveau compte\n";
    $userId = 0;
}

testMail:
echo "\n=== 4. TEST ENVOI MAIL ===\n";
$verifyUrl = 'https://bokonzi.com/index.php?welcome=1';

$html = '<html><body style="font-family:Arial,sans-serif;margin:0;padding:20px;background:#f4f4f4;">'
    . '<div style="max-width:500px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;">'
    . '<h1 style="color:#6c5ce7;text-align:center;">Bienvenue sur Bokonzi !</h1>'
    . '<p>Bonjour <b>' . htmlspecialchars($prenom) . '</b>,</p>'
    . '<p>Pour confirmer votre email :</p>'
    . '<p style="text-align:center;margin:30px 0;"><a href="' . $verifyUrl . '" style="display:inline-block;background:#6c5ce7;color:#fff;padding:16px 40px;text-decoration:none;border-radius:8px;font-size:16px;font-weight:700;">Confirmer</a></p>'
    . '</div></body></html>';

$subject = 'Test register Bokonzi - ' . date('H:i:s');
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Bokonzi <noreply@bokonzi.com>\r\n";
$headers .= "Reply-To: noreply@bokonzi.com\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$r = mail($email, $subject, $html, $headers);
echo "mail() retourne: " . ($r ? 'TRUE → verifie ta boite' : 'FALSE → echec') . "\n";

if (!$r) {
    $err = error_get_last();
    echo "Erreur: " . ($err['message'] ?? 'aucune') . "\n";
}

echo "\n=== DIAGNOSTIC ===\n";
if ($existing && $existing['password_hash'] !== '' && $existing['oauth_provider'] !== 'google') {
    echo "PROBLEME TROUVE: ton email existe deja avec un mot de passe.\n";
    echo "Le code register.php retourne 'email deja utilisee' SANS envoyer de mail.\n";
    echo "\nPour tester: utilise un AUTRE email ou supprime ce user:\n";
    echo "DELETE FROM users WHERE email = '$email';\n";
} else {
    echo "Le mail devrait arriver. Sinon le probleme est ailleurs.\n";
}

echo '</pre>';
