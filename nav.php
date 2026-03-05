<?php
/**
 * nav.php — Barre de navigation commune
 * Inclure dans chaque page : <?php include 'nav.php'; ? >
 */

// Chemins absolus pour les liens
require_once __DIR__ . '/core/paths.php';

// Charger l'auth si pas deja fait (pour afficher connexion/profil)
if (!function_exists('getCurrentUser')) {
    require_once __DIR__ . '/core/auth.php';
}
// Connexion BDD pour verifier la session (si pas deja connecte)
if (!isset($conn) || !($conn instanceof mysqli) || !@$conn->ping()) {
    require_once __DIR__ . '/core/credentials.php';
    $conn = new mysqli("localhost", $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
    $navOwnConn = true;
}
$navUser = getCurrentUser($conn);

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$navItems = [
    ['url' => BK_BASE . '/pages/recherche_live.php', 'label' => 'Recherche Live', 'icon' => '&#128269;'],
    ['url' => BK_BASE . '/pages/global_athlete.php', 'label' => 'Fiche Athlète',  'icon' => '&#9889;'],
    ['url' => BK_BASE . '/index.php',                'label' => 'Dashboard',       'icon' => '&#128202;'],
    ['url' => BK_BASE . '/pages/classement.php',     'label' => 'Classement',      'icon' => '&#127942;'],
    ['url' => BK_BASE . '/pages/exemples.php',       'label' => 'Exemples API',    'icon' => '&#128221;'],
];
?>
<style>
.bk-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: #0b1020;
    border-bottom: 2px solid #6c5ce7;
    display: flex;
    justify-content: center;
    gap: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}
.bk-nav-links {
    display: flex;
    align-items: center;
    gap: 0;
    flex: 1;
    justify-content: center;
}
.bk-nav a {
    color: #8b949e;
    text-decoration: none;
    padding: 12px 20px;
    font-size: 14px;
    transition: all 0.2s;
    border-bottom: 3px solid transparent;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.bk-nav a:hover {
    color: #fff;
    background: #111830;
}
.bk-nav a.active {
    color: #a29bfe;
    border-bottom-color: #6c5ce7;
    background: #111830;
    font-weight: bold;
}
.bk-nav-auth {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-right: 16px;
    margin-left: auto;
}
.bk-nav-auth .auth-name {
    color: #c9d1d9;
    font-size: 13px;
    font-weight: 500;
}
.bk-nav-auth .auth-role {
    color: #a29bfe;
    font-size: 11px;
    background: #6c5ce718;
    padding: 2px 8px;
    border-radius: 10px;
}
.bk-nav-auth .btn-auth {
    padding: 7px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: opacity 0.2s;
}
.bk-nav-auth .btn-login {
    background: transparent;
    border: 1px solid #6c5ce7;
    color: #a29bfe;
}
.bk-nav-auth .btn-logout {
    background: transparent;
    border: 1px solid #ff6b6b;
    color: #ff6b6b;
    cursor: pointer;
    font-family: inherit;
}
.bk-nav-auth .btn-auth:hover { opacity: 0.8; }
.bk-nav-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 2px solid #6c5ce7;
    object-fit: cover;
}
.bk-nav-spacer {
    height: 50px;
}
@media (max-width: 600px) {
    .bk-nav { flex-wrap: wrap; }
    .bk-nav-links { order: 2; flex: 100%; justify-content: space-around; }
    .bk-nav-links a { padding: 10px 8px; font-size: 12px; }
    .bk-nav-links a .nav-label { display: none; }
    .bk-nav-auth { order: 1; width: 100%; justify-content: flex-end; padding: 8px 12px; border-bottom: 1px solid #1e2a3a; }
    .bk-nav-spacer { height: 90px; }
}
</style>
<nav class="bk-nav">
    <div class="bk-nav-links">
    <?php foreach ($navItems as $item): ?>
        <a href="<?= $item['url'] ?>" class="<?= $currentPage === basename($item['url']) ? 'active' : '' ?>">
            <span><?= $item['icon'] ?></span>
            <span class="nav-label"><?= $item['label'] ?></span>
        </a>
    <?php endforeach; ?>
    </div>
    <div class="bk-nav-auth">
    <?php if ($navUser): ?>
        <?php if (!empty($navUser['picture'])): ?>
            <img src="<?= htmlspecialchars($navUser['picture']) ?>" alt="" class="bk-nav-avatar" referrerpolicy="no-referrer">
        <?php endif; ?>
        <span class="auth-name"><?= htmlspecialchars($navUser['prenom'] ?: $navUser['email']) ?></span>
        <span class="auth-role"><?= htmlspecialchars($navUser['role']) ?></span>
        <a href="<?= BK_BASE ?>/index.php?page=espace" class="btn-auth btn-login" style="border-color:#a29bfe;color:#a29bfe;">Mon Espace</a>
        <?php if ($navUser['role'] === 'athlete' || $navUser['role'] === 'coach'): ?>
            <a href="<?= BK_BASE ?>/pages/performances.php" class="btn-auth btn-login" style="border-color:#34d399;color:#34d399;">Perfs</a>
        <?php endif; ?>
        <?php
        // Verifier si l'email a acces au panel admin
        $_navHasPanel = false;
        // 1. Verifier liste d'acces par email (fichier .panel_access.php)
        $_navPanelFile = __DIR__ . '/logs/.panel_access.php';
        if (file_exists($_navPanelFile)) {
            $_navPaRaw = file_get_contents($_navPanelFile);
            $_navPaPos = strpos($_navPaRaw, "\n");
            if ($_navPaPos !== false) {
                $_navPaList = json_decode(substr($_navPaRaw, $_navPaPos + 1), true) ?: [];
                $_navHasPanel = isset($_navPaList[strtolower($navUser['email'])]);
            }
        }
        // 2. Verifier session super admin (validation complete du token)
        if (!$_navHasPanel && !empty($_COOKIE['bk_sa_token'])) {
            $_saFile = __DIR__ . '/logs/.sa_sessions.php';
            if (file_exists($_saFile)) {
                $_saRaw = file_get_contents($_saFile);
                $_saPos = strpos($_saRaw, "\n");
                if ($_saPos !== false) {
                    $_saSessions = json_decode(substr($_saRaw, $_saPos + 1), true) ?: [];
                    $_navHasPanel = isset($_saSessions[$_COOKIE['bk_sa_token']]) && ($_saSessions[$_COOKIE['bk_sa_token']]['expires'] ?? 0) > time();
                }
            }
        }
        ?>
        <?php if ($_navHasPanel): ?>
            <a href="<?= BK_BASE ?>/admin/panel.php" class="btn-auth btn-login" style="border-color:#f59e0b;color:#f59e0b;">Admin</a>
        <?php endif; ?>
        <button class="btn-auth btn-logout" onclick="bkLogout()">Déconnexion</button>
    <?php else: ?>
        <a href="<?= BK_BASE ?>/login.php" class="btn-auth btn-login">Connexion</a>
    <?php endif; ?>
    </div>
</nav>
<div class="bk-nav-spacer"></div>
<?php if ($navUser): ?>
<script>
function bkLogout() {
    fetch('<?= BK_BASE ?>/api/auth/logout.php', { method: 'POST', credentials: 'same-origin' })
        .then(function() { window.location.reload(); });
}
</script>
<?php endif; ?>
<?php
// Fermer la connexion si on l'a ouverte ici
if (!empty($navOwnConn)) {
    $conn->close();
    unset($conn);
}
?>
