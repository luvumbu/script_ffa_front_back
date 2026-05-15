<?php
/**
 * core/visibility.php — Filtre visibilite athletes
 *
 * Detection : si l'utilisateur est admin (cookie bk_sa_token valide), pas de filtre.
 * Sinon : les athletes avec visible=0 sont CACHES partout (stats, listes, jointures).
 *
 * Usage dans une requete SQL :
 *   $vis = athleteVisibilityClause('a');           // -> "a.visible = 1" ou ""
 *   $sql = "SELECT ... FROM athletes a WHERE ... AND $vis";
 *
 * Pour les requetes sans alias :
 *   $vis = athleteVisibilityClause();              // -> "athletes.visible = 1" ou ""
 *
 * Pour un AND prefix :
 *   $vis = athleteVisibilityAnd('a');              // -> " AND a.visible = 1" ou ""
 */

if (!function_exists('isAdminViewing')) {
    function isAdminViewing(): bool {
        static $cached = null;
        if ($cached !== null) return $cached;

        // Verifie cookie super admin
        if (!empty($_COOKIE['bk_sa_token'])) {
            $saFile = __DIR__ . '/../logs/.sa_sessions.php';
            if (file_exists($saFile)) {
                $raw = file_get_contents($saFile);
                $pos = strpos($raw, "\n");
                $sessions = $pos !== false ? (json_decode(substr($raw, $pos + 1), true) ?: []) : [];
                $token = $_COOKIE['bk_sa_token'];
                if (isset($sessions[$token]) && ($sessions[$token]['expires'] ?? 0) > time()) {
                    return $cached = true;
                }
            }
        }
        // Verifie bk_key URL ou header (debugging admin)
        if (($_GET['bk_key'] ?? '') === 'bk_s3cr3t_2026_xK9mP') return $cached = true;
        if (($_SERVER['HTTP_X_BK_KEY'] ?? '') === 'bk_s3cr3t_2026_xK9mP') return $cached = true;

        return $cached = false;
    }
}

if (!function_exists('athleteVisibilityClause')) {
    function athleteVisibilityClause(string $alias = 'a'): string {
        if (isAdminViewing()) return '1=1';
        $col = $alias === '' ? 'visible' : "$alias.visible";
        return "$col = 1";
    }
}

if (!function_exists('athleteVisibilityAnd')) {
    function athleteVisibilityAnd(string $alias = 'a'): string {
        if (isAdminViewing()) return '';
        $col = $alias === '' ? 'visible' : "$alias.visible";
        return " AND $col = 1";
    }
}
