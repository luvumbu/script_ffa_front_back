<?php
/**
 * app/Services/AntiScrapingService.php — Limite 20 pages/jour par IP
 *
 * Copie exacte de la logique anti-scraping d'index.php (lignes 13-47)
 */

class AntiScrapingService
{
    private static $limit = 20;

    /**
     * Verifie si le visiteur a depasse la limite.
     * Redirige vers login.php si oui.
     */
    public static function check()
    {
        // Skip si utilisateur connecte (Google ou super admin)
        if (!empty($_COOKIE['bk_token']) || !empty($_COOKIE['bk_sa_token'])) return;

        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
        $ip = trim(explode(',', $ip)[0]);
        if ($ip === '') return;

        // Whitelist Google + Hostinger + localhost
        $whitelist = [
            '66.249.', '66.102.', '64.233.', '72.14.', '74.125.',
            '209.85.', '216.239.', '35.', '34.',
            '153.92.', '31.170.', '185.201.',
            '127.0.0.1', '::1'
        ];
        foreach ($whitelist as $prefix) {
            if (strpos($ip, $prefix) === 0) return;
        }

        // Compteur journalier par IP
        $rootPath = Application::getInstance()->getRootPath();
        $file = $rootPath . '/logs/.page_limits.php';
        $data = [];
        $today = date('Y-m-d');

        if (file_exists($file)) {
            $raw = file_get_contents($file);
            $pos = strpos($raw, "\n");
            if ($pos !== false) {
                $data = json_decode(substr($raw, $pos + 1), true) ?: [];
            }
        }

        // Nettoyer les jours passes
        if (($data['_date'] ?? '') !== $today) {
            $data = ['_date' => $today];
        }

        $count = ($data[$ip] ?? 0) + 1;
        $data[$ip] = $count;
        @file_put_contents($file, "<?php die('Acces interdit'); ?>\n" . json_encode($data));

        if ($count > self::$limit) {
            $baseUrl = Application::getInstance()->getConfig('base_url');
            header('Location: ' . $baseUrl . '/login.php?limit=1');
            exit;
        }
    }
}
