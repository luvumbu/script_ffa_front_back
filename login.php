<?php
/**
 * login.php — Page de connexion Bokonzi
 */
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/ip_logger.php';
logIp();

// Si deja connecte, rediriger vers dashboard
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
    <title>Connexion - Bokonzi</title>
    <link rel="stylesheet" href="common.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-logo">
            <h1>BOKONZI</h1>
            <p>Connectez-vous a votre compte</p>
        </div>

        <div class="msg-error" id="msgError"></div>

        <form id="loginForm">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="text" id="email" name="email" required placeholder="votre@email.com" autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required placeholder="Minimum 8 caracteres" autocomplete="current-password">
            </div>
            <button type="submit" class="btn-submit" id="btnSubmit">Se connecter</button>
        </form>

        <div class="auth-footer">
            Pas encore de compte ? <a href="register.php">Creer un compte</a>
        </div>
    </div>

    <script>
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmit');
        const errDiv = document.getElementById('msgError');
        errDiv.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Connexion...';

        try {
            const res = await fetch('api/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value,
                }),
            });
            const data = await res.json();
            if (data.success) {
                window.location.href = data.superadmin ? (data.redirect || 'admin/panel.php') : 'index.php';
            } else {
                errDiv.textContent = data.error || 'Erreur de connexion';
                errDiv.style.display = 'block';
            }
        } catch (err) {
            errDiv.textContent = 'Erreur reseau, veuillez reessayer';
            errDiv.style.display = 'block';
        }
        btn.disabled = false;
        btn.textContent = 'Se connecter';
    });
    </script>
</body>
</html>
