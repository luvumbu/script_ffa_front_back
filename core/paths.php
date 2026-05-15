<?php
/**
 * paths.php — Helpers de chemins/URLs auto-adaptes local/prod
 *
 * BK_BASE        : prefixe chemin relatif ('' en prod, '/BK' en local)
 *                  Ex : BK_BASE . '/index.php' donne '/index.php' (prod) ou '/BK/index.php' (local)
 *
 * BK_URL($path)  : URL absolue complete adaptee a l'hote courant
 *                  Ex : BK_URL('/index.php') donne 'https://bokonzi.com/index.php' (prod)
 *                       ou 'http://localhost/BK/index.php' (local)
 *
 * BK_HOST        : hote courant (bokonzi.com ou localhost)
 *
 * Objectif : tous les liens internes doivent rester sur le meme domaine,
 * jamais de basculement entre local et bokonzi.com.
 */

if (!defined('BK_BASE')) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
    define('BK_BASE', $isLocal ? '/BK' : '');
    define('BK_HOST', $host ?: ($isLocal ? 'localhost' : 'bokonzi.com'));
    define('BK_IS_LOCAL', $isLocal);
}

if (!function_exists('BK_URL')) {
    /**
     * Construit une URL absolue adaptee a l'environnement courant.
     * @param string $path Chemin relatif (ex: '/index.php?page=foo' ou 'admin/panel.php')
     * @return string URL absolue (ex: https://bokonzi.com/index.php?page=foo)
     */
    function BK_URL($path = '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? BK_HOST;
        $base = BK_BASE;
        // Normalise le path : enleve les / superflus
        $path = '/' . ltrim($path, '/');
        return $scheme . '://' . $host . $base . $path;
    }
}
