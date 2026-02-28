<?php
/**
 * core/ip_logger.php — Logger IP universel (fichier JSON/PHP)
 *
 * Capture automatiquement chaque visite cote serveur.
 * Stocke dans logs/ip_track.php (JSON protege par die()).
 * Rotation mensuelle automatique.
 *
 * Usage : require_once 'core/ip_logger.php'; logIp();
 */

define('IP_LOG_DIR', __DIR__ . '/../logs');
define('IP_LOG_PREFIX', '<?php die(\'Acces interdit\'); ?>' . "\n");
define('IP_DAILY_LIMIT', 20);

// IPs serveur Hostinger + Google a ne jamais bloquer
define('IP_WHITELIST', [
    '2a02:4780:8:5::11',
    '66.249.76.236',
]);

// Prefixes IP Google (Googlebot, Ads, etc.)
define('IP_GOOGLE_PREFIXES', [
    '66.249.',      // Googlebot
    '64.233.',      // Google
    '72.14.',       // Google
    '74.125.',      // Google
    '108.177.',     // Google
    '142.250.',     // Google
    '172.217.',     // Google
    '173.194.',     // Google
    '209.85.',      // Google
    '216.239.',     // Google
    '35.191.',      // Google Cloud health checks
    '35.196.',      // Google Cloud
    '2001:4860:',   // Google IPv6
]);

/**
 * Detecte l'IP reelle (CloudFlare, proxy, direct)
 */
function getVisitorIp() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Retourne le chemin du fichier log du mois en cours
 */
function ipLogFile($month = null) {
    $m = $month ?: date('Y-m');
    return IP_LOG_DIR . '/ip_track_' . $m . '.php';
}

/**
 * Lit le JSON depuis un fichier log (ignore la ligne PHP de protection)
 */
function readIpLog($file = null) {
    $file = $file ?: ipLogFile();
    if (!file_exists($file)) {
        return [
            'total_visits' => 0,
            'unique_ips' => 0,
            'last_update' => null,
            'daily' => [],
            'ips' => []
        ];
    }
    $raw = file_get_contents($file);
    if ($raw === false) return null;

    // Sauter la premiere ligne (<?php die()...)
    $pos = strpos($raw, "\n");
    if ($pos === false) return null;
    $json = substr($raw, $pos + 1);

    return json_decode($json, true) ?: [
        'total_visits' => 0,
        'unique_ips' => 0,
        'last_update' => null,
        'daily' => [],
        'ips' => []
    ];
}

/**
 * Ecrit les donnees JSON dans le fichier log
 */
function writeIpLog($data, $file = null) {
    $file = $file ?: ipLogFile();

    if (!is_dir(IP_LOG_DIR)) {
        mkdir(IP_LOG_DIR, 0755, true);
    }

    $content = IP_LOG_PREFIX . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $fp = fopen($file, 'c');
    if (!$fp) return false;

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $content);
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return true;
}

/**
 * Fichier compteur journalier (leger, separe du log principal)
 */
function ipDailyFile() {
    return IP_LOG_DIR . '/ip_daily_' . date('Y-m-d') . '.php';
}

/**
 * Lit les compteurs journaliers
 */
function readDailyCounters() {
    $file = ipDailyFile();
    if (!file_exists($file)) return [];
    $raw = file_get_contents($file);
    $pos = strpos($raw, "\n");
    if ($pos === false) return [];
    return json_decode(substr($raw, $pos + 1), true) ?: [];
}

/**
 * Incremente le compteur journalier d'une IP. Retourne le nouveau total.
 */
function incrementDailyCounter($ip) {
    $file = ipDailyFile();
    if (!is_dir(IP_LOG_DIR)) mkdir(IP_LOG_DIR, 0755, true);

    $fp = fopen($file, 'c+');
    if (!$fp) return 0;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return 0; }

    $raw = stream_get_contents($fp);
    $counters = [];
    if ($raw) {
        $pos = strpos($raw, "\n");
        if ($pos !== false) $counters = json_decode(substr($raw, $pos + 1), true) ?: [];
    }

    $counters[$ip] = ($counters[$ip] ?? 0) + 1;
    $count = $counters[$ip];

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, IP_LOG_PREFIX . json_encode($counters));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $count;
}

/**
 * Verifie si une IP est whitelistee (serveur ou Google)
 */
function isWhitelistedIp($ip) {
    if (in_array($ip, IP_WHITELIST, true)) return true;
    foreach (IP_GOOGLE_PREFIXES as $prefix) {
        if (strpos($ip, $prefix) === 0) return true;
    }
    return false;
}

/**
 * Verifie si une IP est connectee (cookie bk_token valide)
 */
function isUserLoggedIn() {
    return !empty($_COOKIE['bk_token']) || !empty($_COOKIE['bk_sa_token']);
}

/**
 * Affiche la page de blocage et arrete l'execution
 */
function showRateLimitPage($ip, $count) {
    http_response_code(429);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex">
        <title>Limite atteinte — Bokonzi</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: #0d1117; color: #c9d1d9; font-family: 'Segoe UI', system-ui, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
            .box { background: #161b22; border: 1px solid #1e2a3a; border-radius: 16px; padding: 40px; max-width: 500px; text-align: center; }
            .icon { font-size: 60px; margin-bottom: 20px; }
            h1 { color: #f59e0b; font-size: 22px; margin-bottom: 12px; }
            p { color: #8b949e; font-size: 14px; line-height: 1.6; margin-bottom: 20px; }
            .count { color: #ef4444; font-weight: 700; font-size: 32px; font-family: monospace; }
            .limit { color: #5a6580; font-size: 13px; }
            .btn {
                display: inline-block; padding: 12px 32px; border-radius: 10px; font-size: 15px; font-weight: 700;
                text-decoration: none; margin: 8px; transition: all 0.2s;
            }
            .btn-primary { background: #6c5ce7; color: #fff; }
            .btn-primary:hover { background: #5a4bd1; }
            .btn-secondary { background: transparent; border: 1px solid #1e2a3a; color: #c9d1d9; }
            .btn-secondary:hover { border-color: #6c5ce7; color: #a29bfe; }
            .reset { color: #5a6580; font-size: 12px; margin-top: 16px; }
        </style>
    </head>
    <body>
        <div class="box">
            <div class="icon">&#9888;&#65039;</div>
            <h1>Limite de consultation atteinte</h1>
            <p>Vous avez effectue</p>
            <div class="count"><?= (int)$count ?></div>
            <div class="limit">requetes aujourd'hui (limite : <?= IP_DAILY_LIMIT ?>)</div>
            <p style="margin-top:20px;">Creez un compte gratuit pour un acces illimite a toutes les donnees de Bokonzi.</p>
            <a href="/register.php" class="btn btn-primary">Creer un compte gratuit</a>
            <a href="/login.php" class="btn btn-secondary">Se connecter</a>
            <div class="reset">La limite se reinitialise chaque jour a minuit.</div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Log une visite — appeler sur chaque page view
 */
function logIp() {
    // Ne pas logger les bots connus
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/bot|crawl|spider|slurp|curl|wget/i', $ua)) {
        return;
    }

    $ip = getVisitorIp();
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $page = $_GET['page'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'unknown');
    $fullUrl = ($_SERVER['REQUEST_URI'] ?? $page);
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';

    // === RATE LIMITING : 60 req/jour par IP (sauf si connecte, whitelist ou Google) ===
    if (!isUserLoggedIn() && !isWhitelistedIp($ip)) {
        // Ne pas bloquer les pages login/register/api auth
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $isAuthPage = (strpos($script, 'login') !== false || strpos($script, 'register') !== false || strpos($script, 'api/auth') !== false);

        if (!$isAuthPage) {
            $dailyCount = incrementDailyCounter($ip);
            if ($dailyCount > IP_DAILY_LIMIT) {
                showRateLimitPage($ip, $dailyCount);
            }
        }
    }

    $file = ipLogFile();
    $fp = fopen($file, 'c+');
    if (!$fp) return;

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return;
    }

    // Lire les donnees existantes
    $raw = stream_get_contents($fp);
    $data = null;
    if ($raw) {
        $pos = strpos($raw, "\n");
        if ($pos !== false) {
            $data = json_decode(substr($raw, $pos + 1), true);
        }
    }

    if (!$data) {
        $data = [
            'total_visits' => 0,
            'unique_ips' => 0,
            'last_update' => null,
            'daily' => [],
            'ips' => [],
            'last_requests' => []
        ];
    }
    if (!isset($data['last_requests'])) $data['last_requests'] = [];

    // Mettre a jour les stats
    $data['total_visits']++;
    $data['last_update'] = $now;

    // === LOG GLOBAL : dernières 500 requêtes ===
    $data['last_requests'][] = [
        'time' => $now,
        'ip' => $ip,
        'page' => $page,
        'url' => mb_substr($fullUrl, 0, 300),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'referrer' => mb_substr($referrer, 0, 200),
        'ua_short' => mb_substr($ua, 0, 80)
    ];
    // Garder uniquement les 500 dernières
    if (count($data['last_requests']) > 500) {
        $data['last_requests'] = array_slice($data['last_requests'], -500);
    }

    // Stats du jour
    if (!isset($data['daily'][$today])) {
        $data['daily'][$today] = ['visits' => 0, 'unique' => 0, 'ips_seen' => []];
    }
    $data['daily'][$today]['visits']++;
    if (!in_array($ip, $data['daily'][$today]['ips_seen'] ?? [])) {
        $data['daily'][$today]['ips_seen'][] = $ip;
        $data['daily'][$today]['unique'] = count($data['daily'][$today]['ips_seen']);
    }

    // Stats par IP
    if (!isset($data['ips'][$ip])) {
        $data['ips'][$ip] = [
            'count' => 0,
            'first' => $now,
            'last' => $now,
            'pages' => [],
            'requests' => [],
            'ua' => mb_substr($ua, 0, 200)
        ];
    }
    if (!isset($data['ips'][$ip]['requests'])) $data['ips'][$ip]['requests'] = [];

    $data['ips'][$ip]['count']++;
    $data['ips'][$ip]['last'] = $now;

    // Historique par IP : dernières 100 requêtes avec heure + page + url
    $data['ips'][$ip]['requests'][] = [
        'time' => $now,
        'page' => $page,
        'url' => mb_substr($fullUrl, 0, 300)
    ];
    if (count($data['ips'][$ip]['requests']) > 100) {
        $data['ips'][$ip]['requests'] = array_slice($data['ips'][$ip]['requests'], -100);
    }

    // Ajouter la page aux pages uniques (max 50)
    if (!in_array($page, $data['ips'][$ip]['pages']) && count($data['ips'][$ip]['pages']) < 50) {
        $data['ips'][$ip]['pages'][] = $page;
    }

    // Compter les IPs uniques
    $data['unique_ips'] = count($data['ips']);

    // Ecrire
    $content = IP_LOG_PREFIX . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $content);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * Liste les fichiers de log disponibles (tous les mois)
 */
function listIpLogFiles() {
    $files = glob(IP_LOG_DIR . '/ip_track_*.php');
    $months = [];
    foreach ($files as $f) {
        if (preg_match('/ip_track_(\d{4}-\d{2})\.php$/', $f, $m)) {
            $months[] = $m[1];
        }
    }
    rsort($months);
    return $months;
}
