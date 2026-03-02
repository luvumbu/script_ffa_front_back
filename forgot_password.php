<?php
/**
 * forgot_password.php — Page de demande de reinitialisation mot de passe
 */
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/ip_logger.php';
logIp();

// Si deja connecte, rediriger
$user = getCurrentUser($conn);
if ($user) {
    header('Location: index.php');
    exit;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublie - Bokonzi</title>
    <link rel="stylesheet" href="common.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-logo">
            <h1>BOKONZI</h1>
            <p>Reinitialisation du mot de passe</p>
        </div>

        <div class="msg-error" id="msgError"></div>
        <div class="msg-success" id="msgSuccess" style="display:none;background:#00d4ff15;border:1px solid #00d4ff30;color:#00d4ff;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;line-height:1.5;"></div>

        <form id="forgotForm">
            <div class="form-group">
                <label for="email">Email de votre compte</label>
                <input type="email" id="email" name="email" required placeholder="votre@email.com" autocomplete="email">
            </div>
            <button type="submit" class="btn-submit" id="btnSubmit">Envoyer le lien</button>
        </form>

        <div class="auth-footer">
            <a href="login.php">Retour a la connexion</a>
        </div>
    </div>

    <script>
    document.getElementById('forgotForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        var btn = document.getElementById('btnSubmit');
        var errDiv = document.getElementById('msgError');
        var okDiv = document.getElementById('msgSuccess');
        errDiv.style.display = 'none';
        okDiv.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Envoi en cours...';

        try {
            var res = await fetch('api/auth/forgot_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    email: document.getElementById('email').value,
                }),
            });
            var data = await res.json();
            if (data.success) {
                document.getElementById('forgotForm').style.display = 'none';
                okDiv.innerHTML = '<strong>Email envoye !</strong><br>Si un compte existe avec cet email, vous recevrez un lien de reinitialisation valable 1 heure. Pensez a verifier vos spams.';
                okDiv.style.display = 'block';
            } else {
                errDiv.textContent = data.error || 'Erreur lors de l\'envoi';
                errDiv.style.display = 'block';
            }
        } catch (err) {
            errDiv.textContent = 'Erreur reseau, veuillez reessayer';
            errDiv.style.display = 'block';
        }
        btn.disabled = false;
        btn.textContent = 'Envoyer le lien';
    });
    </script>
</body>
</html>
