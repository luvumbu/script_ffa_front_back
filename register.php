<?php
/**
 * register.php — Page d'inscription Bokonzi
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
    <title>Inscription - Bokonzi</title>
    <link rel="stylesheet" href="common.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-logo">
            <h1>BOKONZI</h1>
            <p>Creez votre compte</p>
        </div>

        <div class="msg-error" id="msgError"></div>

        <form id="registerForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="prenom">Prenom</label>
                    <input type="text" id="prenom" name="prenom" required placeholder="Jean">
                </div>
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" required placeholder="Dupont">
                </div>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="votre@email.com" autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required placeholder="Minimum 8 caracteres" autocomplete="new-password" minlength="8">
            </div>
            <div class="form-group">
                <label for="role">Vous etes</label>
                <select id="role" name="role">
                    <option value="athlete">Athlete</option>
                    <option value="coach">Coach / Entraineur</option>
                    <option value="club">Club</option>
                </select>
            </div>
            <button type="submit" class="btn-submit" id="btnSubmit">Creer mon compte</button>
        </form>

        <div class="auth-footer">
            Deja un compte ? <a href="login.php">Se connecter</a>
        </div>
    </div>

    <script>
    document.getElementById('registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmit');
        const errDiv = document.getElementById('msgError');
        errDiv.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Inscription...';

        try {
            const res = await fetch('api/auth/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    prenom: document.getElementById('prenom').value,
                    nom: document.getElementById('nom').value,
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value,
                    role: document.getElementById('role').value,
                }),
            });
            const data = await res.json();
            if (data.success) {
                window.location.href = 'index.php';
            } else {
                errDiv.textContent = data.error || 'Erreur lors de l\'inscription';
                errDiv.style.display = 'block';
            }
        } catch (err) {
            errDiv.textContent = 'Erreur reseau, veuillez reessayer';
            errDiv.style.display = 'block';
        }
        btn.disabled = false;
        btn.textContent = 'Creer mon compte';
    });
    </script>
</body>
</html>
