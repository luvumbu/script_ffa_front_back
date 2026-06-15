<?php
/**
 * core/test_mode.php — « Aperçu en tant que » pour le super admin.
 *
 * Permet à un super admin (cookie bk_sa_token VALIDE) de parcourir le site
 * comme s'il était :
 *   - visitor : visiteur anonyme (non connecté)
 *   - free    : membre connecté SANS abonnement
 *   - bronze / argent / or / platine : abonné de l'offre correspondante
 *
 * Activé via le cookie `bk_test_role` (posé depuis admin/panel.php).
 * Le mode est IGNORÉ pour tout visiteur qui n'est pas super admin → aucun
 * risque qu'un utilisateur se fabrique un faux abonnement.
 *
 * Branché dans : core/subscription.php (plan simulé), core/paywall.php
 * (paywall), core/search_limit.php (quota de recherche). Bannière affichée
 * en bas de page via bkTestBanner().
 */

if (!function_exists('bkTestSaValid')) {
    /** Le cookie bk_sa_token correspond-il à une session super admin valide ? */
    function bkTestSaValid() {
        static $v = null;
        if ($v !== null) return $v;
        $v = false;
        if (!empty($_COOKIE['bk_sa_token'])) {
            $f = __DIR__ . '/../logs/.sa_sessions.php';
            if (file_exists($f)) {
                $raw = file_get_contents($f);
                $pos = strpos($raw, "\n");
                if ($pos !== false) {
                    $sessions = json_decode(substr($raw, $pos + 1), true) ?: [];
                    $t = $_COOKIE['bk_sa_token'];
                    $v = isset($sessions[$t]) && (($sessions[$t]['expires'] ?? 0) > time());
                }
            }
        }
        return $v;
    }
}

if (!function_exists('bkTestRole')) {
    /** Rôle de test actif ('' si aucun ou si non super admin). */
    function bkTestRole() {
        static $r = null;
        if ($r !== null) return $r;
        $r = '';
        $role  = $_COOKIE['bk_test_role'] ?? '';
        $valid = ['visitor', 'free', 'bronze', 'argent', 'or', 'platine'];
        if (in_array($role, $valid, true) && bkTestSaValid()) $r = $role;
        return $r;
    }

    /** Le rôle de test simule-t-il un abonné (plan payant) ? */
    function bkTestIsPlan() {
        return in_array(bkTestRole(), ['bronze', 'argent', 'or', 'platine'], true);
    }

    /** Liste des rôles disponibles (clé => libellé). */
    function bkTestRolesList() {
        return [
            'visitor' => 'Visiteur (non connecté)',
            'free'    => 'Membre gratuit',
            'bronze'  => 'Abonné Bronze',
            'argent'  => 'Abonné Argent',
            'or'      => 'Abonné Or',
            'platine' => 'Abonné Platine',
        ];
    }

    /** Bannière fixe affichée en bas du site quand un mode test est actif. */
    function bkTestBanner() {
        $role = bkTestRole();
        if (!$role) return '';
        $labels = bkTestRolesList();
        $lbl = htmlspecialchars($labels[$role] ?? $role);
        return '<div id="bkTestBanner" style="position:fixed;left:0;right:0;bottom:0;z-index:2147483600;'
            . 'background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff;font-family:Arial,Helvetica,sans-serif;'
            . 'font-size:13px;font-weight:700;padding:9px 16px;display:flex;align-items:center;justify-content:center;'
            . 'gap:14px;flex-wrap:wrap;box-shadow:0 -2px 14px rgba(0,0,0,.35);">'
            . '<span>&#129514; Mode test : <b>' . $lbl . '</b> &mdash; tu vois le site comme cet utilisateur.</span>'
            . '<button onclick="document.cookie=\'bk_test_role=;path=/;max-age=0\';location.reload();" '
            . 'style="background:#fff;color:#b91c1c;border:none;border-radius:6px;padding:6px 14px;font-weight:800;cursor:pointer;font-size:13px;">'
            . 'Quitter le mode test</button></div>';
    }
}
