<?php
/**
 * reset_password.php — Page de saisie du nouveau mot de passe
 * Acces via lien email : ?token=XXXX
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

// Auto-creation table si elle n'existe pas
$conn->query("CREATE TABLE IF NOT EXISTS `password_resets` (
    `id_reset` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `id_user` INT UNSIGNED NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `expire_at` DATETIME NOT NULL,
    `used` TINYINT(1) UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$token = trim($_GET['token'] ?? '');
$tokenValid = false;

if (!empty($token)) {
    $stmt = $conn->prepare(
        "SELECT id_reset FROM password_resets WHERE token = ? AND used = 0 AND expire_at > NOW()"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $tokenValid = $result->num_rows > 0;
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe - Bokonzi</title>
    <link rel="stylesheet" href="common.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-logo">
            <h1>BOKONZI</h1>
            <p>Nouveau mot de passe</p>
        </div>

        <?php if (empty($token) || !$tokenValid): ?>
        <div class="msg-error" style="display:block;">
            <?php if (empty($token)): ?>
                Lien invalide. Aucun token de reinitialisation fourni.
            <?php else: ?>
                Ce lien de reinitialisation est invalide ou a expire. <br>Veuillez faire une nouvelle demande.
            <?php endif; ?>
        </div>
        <div class="auth-footer">
            <a href="forgot_password.php">Nouvelle demande</a> &middot; <a href="login.php">Connexion</a>
        </div>

        <?php else: ?>
        <div class="msg-error" id="msgError"></div>
        <div class="msg-success" id="msgSuccess" style="display:none;background:#00d4ff15;border:1px solid #00d4ff30;color:#00d4ff;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;line-height:1.5;"></div>

        <form id="resetForm">
            <div class="form-group">
                <label for="password">Nouveau mot de passe</label>
                <input type="password" id="password" name="password" required placeholder="Minimum 8 caracteres" autocomplete="new-password" minlength="8">
            </div>
            <div class="form-group">
                <label for="password2">Confirmer le mot de passe</label>
                <input type="password" id="password2" name="password2" required placeholder="Retapez votre mot de passe" autocomplete="new-password" minlength="8">
            </div>
            <button type="submit" class="btn-submit" id="btnSubmit">Reinitialiser</button>
        </form>

        <div class="auth-footer">
            <a href="login.php">Retour a la connexion</a>
        </div>

        <script>
        document.getElementById('resetForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            var btn = document.getElementById('btnSubmit');
            var errDiv = document.getElementById('msgError');
            var okDiv = document.getElementById('msgSuccess');
            errDiv.style.display = 'none';
            okDiv.style.display = 'none';

            var pw1 = document.getElementById('password').value;
            var pw2 = document.getElementById('password2').value;

            if (pw1 !== pw2) {
                errDiv.textContent = 'Les mots de passe ne correspondent pas';
                errDiv.style.display = 'block';
                return;
            }

            if (pw1.length < 8) {
                errDiv.textContent = 'Le mot de passe doit contenir au moins 8 caracteres';
                errDiv.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Reinitialisation...';

            try {
                var res = await fetch('api/auth/reset_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        token: <?php echo json_encode($token); ?>,
                        password: pw1,
                    }),
                });
                var data = await res.json();
                if (data.success) {
                    document.getElementById('resetForm').style.display = 'none';
                    okDiv.innerHTML = '<strong>Mot de passe reinitialise !</strong><br>Vous allez etre redirige vers la page de connexion...';
                    okDiv.style.display = 'block';
                    setTimeout(function() { window.location.href = 'login.php'; }, 3000);
                } else {
                    errDiv.textContent = data.error || 'Erreur lors de la reinitialisation';
                    errDiv.style.display = 'block';
                }
            } catch (err) {
                errDiv.textContent = 'Erreur reseau, veuillez reessayer';
                errDiv.style.display = 'block';
            }
            btn.disabled = false;
            btn.textContent = 'Reinitialiser';
        });
        </script>
        <?php endif; ?>
    </div>
</body>
</html>
