<?php
/**
 * _guard.php — Garde d'acces commune aux outils de scraping.
 *
 * Regle : acces autorise SI
 *   - session super admin connectee (cookie bk_sa_token valide)  -> sans token
 *   - OU email Google present dans logs/.panel_access.php
 *   - OU cle maitre fournie (?bk_key=… ou header X-BK-KEY)        -> secours
 * Sinon : 403 et arret.
 *
 * Inclure tout en haut de chaque outil :  require __DIR__ . '/_guard.php';
 * Idempotent (define), peut etre inclus plusieurs fois sans risque.
 */
if (!defined('BK_SCRAPING_GUARD')) {
    define('BK_SCRAPING_GUARD', 1);

    $__g_db   = dirname(__DIR__) . '/core/db.php';
    $__g_auth = dirname(__DIR__) . '/core/auth.php';
    $__g_perm = dirname(__DIR__) . '/core/athlete_purge.php';
    if (is_file($__g_db))   require_once $__g_db;     // fournit $conn
    if (is_file($__g_auth)) require_once $__g_auth;   // getCurrentUser()
    if (is_file($__g_perm)) require_once $__g_perm;   // bkUserCanPurge()

    $__g_conn = isset($conn) ? $conn : null;
    $__g_ok = function_exists('bkUserCanPurge') ? bkUserCanPurge($__g_conn) : false;

    if (!$__g_ok) {
        $__g_isApi = isset($_GET['api']) || isset($_POST['api']);
        if ($__g_isApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Acces reserve : connecte-toi en admin ou ajoute ?bk_key=.']);
        } else {
            http_response_code(403);
            echo '<!DOCTYPE html><meta charset="utf-8"><body style="background:#0d1117;color:#e6edf3;font-family:system-ui,sans-serif;padding:40px;text-align:center;">'
               . '<h1>403 &mdash; Acces reserve</h1>'
               . '<p>Connecte-toi en super admin, ou ajoute ta cle : <code>?bk_key=&hellip;</code></p>'
               . '<p style="margin-top:18px;"><a style="color:#a78bfa;font-weight:700;" href="' . htmlspecialchars(dirname($_SERVER['SCRIPT_NAME'] ?? '')) . '/../login.php">Se connecter</a></p>'
               . '</body>';
        }
        exit;
    }
}
