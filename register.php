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
    <style>
    .btn-google { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:14px; background:#fff; color:#3c4043; border:1px solid #dadce0; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; text-decoration:none; transition:background .2s, box-shadow .2s; }
    .btn-google:hover { background:#f7f8f8; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .btn-google svg { flex-shrink:0; }
    </style>
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-logo">
            <h1>BOKONZI</h1>
            <p>Creez votre compte</p>
        </div>

        <div class="msg-error" id="msgError"></div>

        <a href="api/auth/google_login.php" class="btn-google">
            <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24.08 24.08 0 0 0 0 21.56l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
            S'inscrire avec Google
        </a>

        <div class="auth-footer" style="margin-top:20px;">
            Deja un compte ? <a href="login.php">Se connecter</a>
        </div>
    </div>

    <script>
    (function() {
        var p = new URLSearchParams(window.location.search);
        if (p.get('error') === 'google') {
            var d = document.getElementById('msgError');
            d.textContent = 'Echec de l\'inscription avec Google. Veuillez reessayer.';
            d.style.display = 'block';
        }
    })();
    </script>
</body>
</html>
