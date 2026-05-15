<?php
/**
 * register.php — Page d'inscription Bokonzi
 */
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/ip_logger.php';
require_once __DIR__ . '/core/paths.php';
logIp();

$user = getCurrentUser($conn);
if ($user) { header('Location: index.php'); exit; }
$conn->close();

// En local, l'inscription via Google ne marche pas (OAuth non configure)
// On redirige vers login.php qui montrera le formulaire admin
if (BK_IS_LOCAL) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Bokonzi</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Segoe UI',system-ui,-apple-system,sans-serif; background:#060a13; color:#c9d1d9; min-height:100vh; display:flex; align-items:center; justify-content:center; }
    /* Fond anime */
    body::before { content:''; position:fixed; top:0; left:0; right:0; bottom:0; background:radial-gradient(ellipse at 20% 50%, #6c5ce710 0%, transparent 50%), radial-gradient(ellipse at 80% 20%, #3b82f608 0%, transparent 50%), radial-gradient(ellipse at 50% 80%, #10b98108 0%, transparent 50%); pointer-events:none; z-index:0; }
    .page { position:relative; z-index:1; width:100%; max-width:460px; margin:20px; }
    /* Card principale */
    .reg-card { background:linear-gradient(145deg, #0d1220 0%, #111830 50%, #0d1220 100%); border:1px solid #1a254080; border-radius:20px; padding:44px 40px; box-shadow:0 20px 60px rgba(0,0,0,.5), 0 0 0 1px #ffffff06 inset; }
    @media(max-width:500px) { .reg-card { padding:32px 24px; border-radius:16px; } }
    /* Logo */
    .logo { text-align:center; margin-bottom:32px; }
    .logo h1 { font-size:32px; font-weight:900; letter-spacing:-1px; background:linear-gradient(135deg, #6c5ce7, #a29bfe, #6c5ce7); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .logo p { color:#5a6580; font-size:14px; margin-top:6px; }
    .logo .stats-line { display:flex; justify-content:center; gap:20px; margin-top:14px; }
    .logo .stats-line span { font-size:11px; color:#3a4560; }
    .logo .stats-line b { color:#8b949e; }
    /* Google btn */
    .btn-google { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:14px 20px; background:#fff; color:#3c4043; border:1px solid #dadce0; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; text-decoration:none; transition:all .2s; }
    .btn-google:hover { background:#f7f8f8; box-shadow:0 2px 8px rgba(0,0,0,.15); transform:translateY(-1px); }
    .btn-google svg { flex-shrink:0; }
    /* Separator */
    .sep { display:flex; align-items:center; gap:14px; margin:24px 0; }
    .sep::before, .sep::after { content:''; flex:1; height:1px; background:linear-gradient(90deg, transparent, #253560, transparent); }
    .sep span { color:#3a4560; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; }
    /* Form */
    .row { display:flex; gap:12px; }
    .row .field { flex:1; }
    .field { margin-bottom:16px; }
    .field label { display:block; color:#8b949e; font-size:12px; font-weight:600; margin-bottom:6px; text-transform:uppercase; letter-spacing:.5px; }
    .field input { width:100%; padding:12px 16px; background:#080c14; border:1px solid #1a2540; border-radius:10px; color:#e0e6ed; font-size:14px; transition:all .2s; }
    .field input:focus { outline:none; border-color:#6c5ce7; box-shadow:0 0 0 3px #6c5ce715; }
    .field input::placeholder { color:#2a3550; }
    .field .hint { color:#3a4560; font-size:11px; margin-top:5px; }
    /* Strength bar */
    .pwd-strength { height:3px; border-radius:3px; margin-top:6px; background:#1a2540; overflow:hidden; }
    .pwd-strength .bar { height:100%; width:0; border-radius:3px; transition:all .3s; }
    /* Submit */
    .btn-register { width:100%; padding:14px; background:linear-gradient(135deg, #6c5ce7, #5541d0); border:none; border-radius:10px; color:#fff; font-size:16px; font-weight:700; cursor:pointer; transition:all .2s; letter-spacing:.3px; position:relative; overflow:hidden; }
    .btn-register:hover { transform:translateY(-1px); box-shadow:0 6px 20px #6c5ce740; }
    .btn-register:active { transform:translateY(0); }
    .btn-register:disabled { opacity:.5; cursor:not-allowed; transform:none; box-shadow:none; }
    .btn-register::after { content:''; position:absolute; top:0; left:-100%; width:100%; height:100%; background:linear-gradient(90deg, transparent, rgba(255,255,255,.1), transparent); transition:left .5s; }
    .btn-register:hover::after { left:100%; }
    /* Messages */
    .msg-error { background:#ff353512; border:1px solid #ff353530; color:#ff6b6b; padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; display:none; text-align:center; line-height:1.5; }
    .msg-success { background:#10b98112; border:1px solid #10b98130; color:#34d399; padding:16px; border-radius:10px; font-size:14px; margin-bottom:16px; display:none; text-align:center; line-height:1.6; }
    /* Footer */
    .foot { text-align:center; margin-top:24px; font-size:14px; color:#3a4560; }
    .foot a { color:#a29bfe; text-decoration:none; font-weight:600; }
    .foot a:hover { color:#6c5ce7; text-decoration:underline; }
    /* Bottom links */
    .bottom-links { text-align:center; margin-top:20px; padding-top:20px; border-top:1px solid #1a254060; }
    .bottom-links a { color:#5a6580; font-size:12px; text-decoration:none; transition:color .2s; }
    .bottom-links a:hover { color:#a29bfe; }
    .bottom-links .pipe { color:#253560; margin:0 8px; }
    /* Avantages */
    .perks { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin:20px 0 24px; }
    .perk { display:flex; align-items:center; gap:8px; font-size:12px; color:#5a6580; padding:8px 10px; background:#080c1480; border-radius:8px; border:1px solid #1a254040; }
    .perk .icon { font-size:16px; flex-shrink:0; }
    @media(max-width:400px) { .perks { grid-template-columns:1fr; } .row { flex-direction:column; gap:0; } }
    </style>
</head>
<body>
<div class="page">
    <div class="reg-card">
        <div class="logo">
            <h1>BOKONZI</h1>
            <p>Rejoignez la communaute de l'athletisme francais</p>
            <div class="stats-line">
                <span><b>330 000+</b> athletes</span>
                <span><b>4 000+</b> clubs</span>
                <span><b>100%</b> gratuit</span>
            </div>
        </div>

        <div class="msg-error" id="msgError"></div>
        <div class="msg-success" id="msgSuccess"></div>

        <a href="api/auth/google_login.php" class="btn-google">
            <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24.08 24.08 0 0 0 0 21.56l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
            Continuer avec Google
        </a>

        <div class="sep"><span>ou par email</span></div>

        <form id="registerForm" autocomplete="on">
            <div class="row">
                <div class="field">
                    <label for="regPrenom">Prenom</label>
                    <input type="text" id="regPrenom" required placeholder="Jean" maxlength="100" autocomplete="given-name">
                </div>
                <div class="field">
                    <label for="regNom">Nom</label>
                    <input type="text" id="regNom" required placeholder="Dupont" maxlength="100" autocomplete="family-name">
                </div>
            </div>
            <div class="field">
                <label for="regEmail">Adresse email</label>
                <input type="email" id="regEmail" required placeholder="jean.dupont@email.com" maxlength="200" autocomplete="email">
            </div>
            <div class="field">
                <label for="regPassword">Mot de passe</label>
                <input type="password" id="regPassword" required placeholder="Choisissez un mot de passe" minlength="6" autocomplete="new-password">
                <div class="pwd-strength"><div class="bar" id="pwdBar"></div></div>
                <div class="hint" id="pwdHint">6 caracteres minimum</div>
            </div>
            <button type="submit" class="btn-register" id="btnRegister">Creer mon compte gratuit</button>
        </form>

        <div class="perks">
            <div class="perk"><span class="icon">&#128269;</span> Recherche illimitee</div>
            <div class="perk"><span class="icon">&#128202;</span> Stats detaillees</div>
            <div class="perk"><span class="icon">&#11088;</span> Suivi athletes</div>
            <div class="perk"><span class="icon">&#128196;</span> Export PDF</div>
        </div>

        <div class="foot">
            Deja inscrit ? <a href="login.php">Se connecter</a>
        </div>

        <div class="bottom-links">
            <a href="#" onclick="event.preventDefault();var c=document.getElementById('contactBox'),s=document.getElementById('signalBox');c.style.display=c.style.display==='none'?'block':'none';s.style.display='none';">Nous contacter</a>
            <span class="pipe">|</span>
            <a href="#" onclick="event.preventDefault();var s=document.getElementById('signalBox'),c=document.getElementById('contactBox');s.style.display=s.style.display==='none'?'block':'none';c.style.display='none';">Signaler un profil</a>
        </div>

        <!-- Contact -->
        <div id="contactBox" style="display:none;margin-top:14px;padding:16px;background:#080c14;border:1px solid #1a2540;border-radius:12px;">
            <input type="text" id="rcNom" placeholder="Votre nom (facultatif)" style="width:100%;padding:10px 14px;background:#060a13;border:1px solid #1a2540;border-radius:8px;color:#c9d1d9;font-size:13px;margin-bottom:8px;">
            <input type="email" id="rcEmail" placeholder="Votre email (facultatif)" style="width:100%;padding:10px 14px;background:#060a13;border:1px solid #1a2540;border-radius:8px;color:#c9d1d9;font-size:13px;margin-bottom:8px;">
            <textarea id="rcMsg" placeholder="Votre message..." maxlength="2000" style="width:100%;padding:10px 14px;background:#060a13;border:1px solid #1a2540;border-radius:8px;color:#c9d1d9;font-size:13px;resize:vertical;min-height:70px;margin-bottom:8px;font-family:inherit;"></textarea>
            <button onclick="_rc()" id="rcBtn" style="width:100%;padding:10px;background:#6c5ce7;border:none;border-radius:8px;color:#fff;font-size:13px;font-weight:700;cursor:pointer;">Envoyer</button>
            <div id="rcFb" style="margin-top:6px;font-size:12px;text-align:center;"></div>
        </div>

        <!-- Signalement -->
        <div id="signalBox" style="display:none;margin-top:14px;padding:16px;background:#f59e0b08;border:1px solid #f59e0b20;border-radius:12px;">
            <p style="color:#c9d1d9;font-size:12px;line-height:1.5;margin-bottom:10px;"><strong style="color:#f59e0b;">Signaler un profil</strong> — Indiquez le nom ou l'URL de l'athlete.</p>
            <input type="text" id="rsNom" maxlength="200" placeholder="Nom de l'athlete ou URL *" style="width:100%;padding:10px 14px;background:#060a13;border:1px solid #1a2540;border-radius:8px;color:#c9d1d9;font-size:13px;margin-bottom:8px;">
            <select id="rsReason" style="width:100%;padding:10px 14px;background:#060a13;border:1px solid #1a2540;border-radius:8px;color:#c9d1d9;font-size:13px;margin-bottom:8px;"><option value="">-- Motif --</option><option value="retrait">Retirer mon profil</option><option value="donnees_incorrectes">Donnees incorrectes</option><option value="usurpation">Usurpation d'identite</option><option value="vie_privee">Vie privee</option><option value="autre">Autre</option></select>
            <textarea id="rsMsg" maxlength="2000" placeholder="Details (facultatif)" style="width:100%;padding:10px 14px;background:#060a13;border:1px solid #1a2540;border-radius:8px;color:#c9d1d9;font-size:13px;resize:vertical;min-height:50px;margin-bottom:8px;font-family:inherit;"></textarea>
            <input type="email" id="rsEmail" maxlength="200" placeholder="Votre email (facultatif)" style="width:100%;padding:10px 14px;background:#060a13;border:1px solid #1a2540;border-radius:8px;color:#c9d1d9;font-size:13px;margin-bottom:8px;">
            <button onclick="_rs()" id="rsBtn" style="width:100%;background:#da3636;border:none;color:#fff;font-size:13px;font-weight:700;padding:10px;border-radius:8px;cursor:pointer;">Envoyer le signalement</button>
            <div id="rsFb" style="font-size:12px;margin-top:6px;text-align:center;"></div>
        </div>
    </div>
</div>

<script>
// Password strength
document.getElementById('regPassword').addEventListener('input', function() {
    var v = this.value, bar = document.getElementById('pwdBar'), hint = document.getElementById('pwdHint');
    var s = 0;
    if (v.length >= 6) s++;
    if (v.length >= 10) s++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
    if (/\d/.test(v)) s++;
    if (/[^a-zA-Z0-9]/.test(v)) s++;
    var pct = Math.min(100, s * 20);
    var col = s <= 1 ? '#ef4444' : s <= 2 ? '#f59e0b' : s <= 3 ? '#eab308' : '#10b981';
    bar.style.width = pct + '%';
    bar.style.background = col;
    hint.textContent = s <= 1 ? 'Faible' : s <= 2 ? 'Moyen' : s <= 3 ? 'Bon' : 'Excellent';
    hint.style.color = col;
});

// Register
document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('btnRegister');
    var err = document.getElementById('msgError');
    var suc = document.getElementById('msgSuccess');
    err.style.display = 'none';
    suc.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Creation en cours...';

    var base = (location.hostname === 'localhost') ? '/BK' : '';
    fetch(base + '/api/auth/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
            prenom: document.getElementById('regPrenom').value.trim(),
            nom: document.getElementById('regNom').value.trim(),
            email: document.getElementById('regEmail').value.trim(),
            password: document.getElementById('regPassword').value
        })
    })
    .then(function(r) {
        if (!r.ok) return r.text().then(function(t) { throw new Error('HTTP ' + r.status + ': ' + t); });
        return r.json();
    })
    .then(function(data) {
        if (data.success) {
            try { localStorage.removeItem('bk_auth_wall'); } catch(ex) {}
            suc.innerHTML = '<div style="font-size:32px;margin-bottom:8px;">&#127881;</div><strong>Bienvenue sur Bokonzi !</strong><br>Votre compte a ete cree. Redirection...';
            suc.style.display = 'block';
            document.getElementById('registerForm').style.display = 'none';
            document.querySelector('.btn-google').style.display = 'none';
            document.querySelector('.sep').style.display = 'none';
            document.querySelector('.perks').style.display = 'none';
            setTimeout(function() { window.location.href = data.redirect || 'index.php?welcome=1'; }, 1500);
        } else {
            err.textContent = data.error || 'Erreur lors de l\'inscription';
            err.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Creer mon compte gratuit';
        }
    })
    .catch(function(e) {
        err.textContent = e.message || 'Erreur reseau';
        err.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Creer mon compte gratuit';
    });
});

// OAuth error
(function() {
    var p = new URLSearchParams(window.location.search);
    if (p.get('error') === 'google') {
        var d = document.getElementById('msgError');
        d.textContent = 'Echec de la connexion Google. Veuillez reessayer.';
        d.style.display = 'block';
    }
})();

// Contact
function _rc() {
    var msg = document.getElementById('rcMsg').value.trim(), fb = document.getElementById('rcFb'), btn = document.getElementById('rcBtn');
    if (!msg) { fb.innerHTML = '<span style="color:#ef4444">Ecrivez un message.</span>'; return; }
    btn.disabled = true; btn.textContent = 'Envoi...';
    var base = (location.hostname === 'localhost') ? '/BK' : '';
    fetch(base + '/api/contact.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ nom: document.getElementById('rcNom').value.trim() || 'Visiteur (inscription)', email: document.getElementById('rcEmail').value.trim(), message: msg }) })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) { fb.innerHTML = '<span style="color:#10b981">&#10003; Message envoye !</span>'; document.getElementById('rcMsg').value = ''; btn.textContent = 'Envoye'; }
        else { fb.innerHTML = '<span style="color:#ef4444">' + (d.error || 'Erreur') + '</span>'; btn.disabled = false; btn.textContent = 'Envoyer'; }
    }).catch(function() { fb.innerHTML = '<span style="color:#ef4444">Erreur reseau.</span>'; btn.disabled = false; btn.textContent = 'Envoyer'; });
}

// Signalement
function _rs() {
    var nom = document.getElementById('rsNom').value.trim(), reason = document.getElementById('rsReason').value, fb = document.getElementById('rsFb');
    if (!nom) { fb.innerHTML = '<span style="color:#ef4444">Indiquez le nom ou l\'URL.</span>'; return; }
    if (!reason) { fb.innerHTML = '<span style="color:#ef4444">Choisissez un motif.</span>'; return; }
    var btn = document.getElementById('rsBtn'); btn.disabled = true; btn.textContent = 'Envoi...';
    var msg = '[Signalement] Athlete: ' + nom + ' | Motif: ' + reason;
    var detail = document.getElementById('rsMsg').value.trim();
    if (detail) msg += ' | ' + detail;
    var base = (location.hostname === 'localhost') ? '/BK' : '';
    fetch(base + '/api/contact.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ nom: 'Signalement', email: document.getElementById('rsEmail').value.trim(), message: msg }) })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) { document.getElementById('signalBox').innerHTML = '<p style="color:#10b981;font-size:13px;font-weight:600;text-align:center;padding:10px;">&#10003; Signalement envoye !</p>'; }
        else { fb.innerHTML = '<span style="color:#ef4444">' + (d.error || 'Erreur') + '</span>'; btn.disabled = false; btn.textContent = 'Envoyer le signalement'; }
    }).catch(function() { fb.innerHTML = '<span style="color:#ef4444">Erreur reseau.</span>'; btn.disabled = false; btn.textContent = 'Envoyer le signalement'; });
}
</script>
</body>
</html>
