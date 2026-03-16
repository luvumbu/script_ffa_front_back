<?php
/**
 * login.php — Page de connexion Bokonzi
 * Onglet 1 : Connexion Google OAuth (utilisateurs)
 * Onglet 2 : Connexion Admin (email/mdp, rate limited)
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

$tab = ($_GET['tab'] ?? '') === 'admin' ? 'admin' : 'user';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Bokonzi</title>
    <link rel="stylesheet" href="common.css">
    <style>
    .btn-google { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:14px; background:#fff; color:#3c4043; border:1px solid #dadce0; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; text-decoration:none; transition:background .2s, box-shadow .2s; }
    .btn-google:hover { background:#f7f8f8; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .btn-google svg { flex-shrink:0; }
    .auth-tabs { display:flex; gap:0; margin-bottom:24px; border-radius:8px; overflow:hidden; border:1px solid #253560; }
    .auth-tab { flex:1; padding:10px; text-align:center; font-size:13px; font-weight:600; cursor:pointer; transition:all .2s; text-decoration:none; }
    .auth-tab.active { background:#6c5ce7; color:#fff; }
    .auth-tab:not(.active) { background:transparent; color:#5a6580; }
    .auth-tab:not(.active):hover { color:#a29bfe; }
    .tab-content { display:none; }
    .tab-content.active { display:block; }
    .msg-blocked { background:#e11d4820; border:1px solid #e11d48; color:#fb7185; padding:12px; border-radius:8px; font-size:13px; text-align:center; margin-bottom:16px; }
    </style>
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-logo">
            <h1>BOKONZI</h1>
            <p>Connectez-vous a votre compte</p>
        </div>

        <div class="auth-tabs">
            <a class="auth-tab <?= $tab === 'user' ? 'active' : '' ?>" href="?tab=user">Connexion</a>
            <a class="auth-tab <?= $tab === 'admin' ? 'active' : '' ?>" href="?tab=admin">Admin</a>
        </div>

        <?php if (isset($_GET['limit'])): ?>
        <div style="background:#f59e0b15;border:1px solid #f59e0b40;color:#f59e0b;padding:14px;border-radius:8px;font-size:13px;text-align:center;margin-bottom:16px;line-height:1.5;">
            <strong>Limite atteinte</strong><br>
            Vous avez atteint la limite de 5 pages par jour.<br>
            Connectez-vous avec Google pour continuer a naviguer sans limite.
        </div>

        <?php endif; ?>

        <div class="msg-error" id="msgError"></div>

        <!-- ONGLET USER : Google OAuth -->
        <div class="tab-content <?= $tab === 'user' ? 'active' : '' ?>" id="tabUser">
            <a href="api/auth/google_login.php" class="btn-google">
                <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24.08 24.08 0 0 0 0 21.56l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                Se connecter avec Google
            </a>

            <div class="auth-footer" style="margin-top:20px;">
                Pas encore de compte ? <a href="register.php">Creer un compte</a>
            </div>
        </div>

        <!-- ONGLET ADMIN : Email / Mot de passe -->
        <div class="tab-content <?= $tab === 'admin' ? 'active' : '' ?>" id="tabAdmin">
            <form id="adminForm">
                <div class="form-group">
                    <label for="adminEmail">Identifiant</label>
                    <input type="text" id="adminEmail" name="email" required placeholder="Identifiant admin" autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="adminPassword">Mot de passe</label>
                    <input type="password" id="adminPassword" name="password" required placeholder="Mot de passe" autocomplete="current-password">
                </div>
                <button type="submit" class="btn-submit" id="btnAdminSubmit">Connexion Admin</button>
            </form>
            <div id="attemptsLeft" style="text-align:center;margin-top:12px;font-size:12px;color:#5a6580;"></div>
        </div>

        <!-- Lien contact -->
        <div style="text-align:center;margin-top:16px;padding-top:16px;border-top:1px solid #30363d;">
            <a href="#" onclick="document.getElementById('contactFormWrap').style.display=document.getElementById('contactFormWrap').style.display==='none'?'block':'none';return false;" style="color:#8b949e;font-size:13px;text-decoration:none;">&#9993; Nous contacter</a>
        </div>
        <div id="contactFormWrap" style="display:none;margin-top:12px;">
            <input type="text" id="ctNom" placeholder="Votre nom (facultatif)" style="width:100%;padding:10px 12px;background:#0d1117;border:1px solid #30363d;border-radius:8px;color:#c9d1d9;font-size:13px;margin-bottom:8px;box-sizing:border-box;">
            <input type="email" id="ctEmail" placeholder="Votre email (facultatif)" style="width:100%;padding:10px 12px;background:#0d1117;border:1px solid #30363d;border-radius:8px;color:#c9d1d9;font-size:13px;margin-bottom:8px;box-sizing:border-box;">
            <textarea id="ctMsg" placeholder="Votre message..." maxlength="2000" style="width:100%;padding:10px 12px;background:#0d1117;border:1px solid #30363d;border-radius:8px;color:#c9d1d9;font-size:13px;margin-bottom:10px;box-sizing:border-box;resize:vertical;min-height:70px;font-family:inherit;"></textarea>
            <button onclick="_sendContact()" id="ctBtn" style="width:100%;padding:10px;background:#6c5ce7;border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;">Envoyer</button>
            <div id="ctFb" style="margin-top:8px;font-size:13px;text-align:center;"></div>
        </div>
    </div>

    <script>
    // Erreur OAuth
    (function() {
        var p = new URLSearchParams(window.location.search);
        if (p.get('error') === 'google') {
            var d = document.getElementById('msgError');
            d.textContent = 'Echec de la connexion avec Google. Veuillez reessayer.';
            d.style.display = 'block';
        }
    })();

    // Admin login form
    var adminForm = document.getElementById('adminForm');
    if (adminForm) {
        adminForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            var btn = document.getElementById('btnAdminSubmit');
            var errDiv = document.getElementById('msgError');
            errDiv.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Connexion...';

            try {
                var res = await fetch('api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        email: document.getElementById('adminEmail').value,
                        password: document.getElementById('adminPassword').value,
                    }),
                });
                var data = await res.json();
                if (data.success) {
                    window.location.href = data.superadmin ? (data.redirect || 'admin/panel.php') : 'index.php';
                } else {
                    errDiv.textContent = data.error || 'Erreur de connexion';
                    errDiv.style.display = 'block';
                    if (data.blocked) {
                        btn.disabled = true;
                        btn.textContent = 'IP bloquee';
                        btn.style.background = '#e11d4840';
                        btn.style.borderColor = '#e11d48';
                    } else if (typeof data.remaining === 'number') {
                        document.getElementById('attemptsLeft').textContent = data.remaining + ' tentative' + (data.remaining > 1 ? 's' : '') + ' restante' + (data.remaining > 1 ? 's' : '');
                    }
                }
            } catch (err) {
                errDiv.textContent = 'Erreur reseau, veuillez reessayer';
                errDiv.style.display = 'block';
            }
            if (!btn.dataset.locked) {
                btn.disabled = false;
                btn.textContent = 'Connexion Admin';
            }
        });
    }
    </script>

    <script>
    function _sendContact() {
        var msg = document.getElementById('ctMsg').value.trim();
        var fb = document.getElementById('ctFb');
        var btn = document.getElementById('ctBtn');
        if (!msg) { fb.innerHTML = '<span style="color:#f85149;">Veuillez ecrire un message.</span>'; return; }
        btn.disabled = true; btn.textContent = 'Envoi...';
        fetch('api/contact.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nom: document.getElementById('ctNom').value.trim() || 'Visiteur (page login)',
                email: document.getElementById('ctEmail').value.trim(),
                message: msg
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                fb.innerHTML = '<span style="color:#3fb950;">&#10003; Message envoye !</span>';
                btn.textContent = 'Envoye';
                document.getElementById('ctMsg').value = '';
            } else {
                fb.innerHTML = '<span style="color:#f85149;">' + (d.error || 'Erreur') + '</span>';
                btn.disabled = false; btn.textContent = 'Envoyer';
            }
        })
        .catch(function() {
            fb.innerHTML = '<span style="color:#f85149;">Erreur reseau.</span>';
            btn.disabled = false; btn.textContent = 'Envoyer';
        });
    }
    </script>
</body>
</html>
