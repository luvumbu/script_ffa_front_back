<?php
/**
 * core/search_limit.php — Quota de recherches (source de verite unique)
 *
 * Utilise par api/search.php, api/clubs.php, api/villes.php (consommation),
 * par api/quota.php et le badge nav d'index.php (lecture seule).
 *
 * Regles :
 *   - Super admin (cookie bk_sa_token) ou cle API bk_key : illimite
 *   - Abonne Argent/Or/Platine (search_limit = -1)        : illimite
 *   - Abonne Bronze                                        : quota du plan, aucun cooldown
 *   - Connecte sans abonnement                             : 5/jour + 30 min entre 2 recherches
 *   - Anonyme (non connecte)                               : decouverte gratuite de 60 s puis blocage
 *                                                            (le minuteur demarre a l'arrivee sur le site,
 *                                                             via le cookie bk_anon_t0 pose par index.php)
 *   - Whitelist (Google, Hostinger, localhost, bots)       : ignore (illimite)
 *
 * Fichier compteur (connectes uniquement) : logs/.search_limits.php
 *   { "_date": "Y-m-d", "<ip>": { "c": <nb du jour>, "t": <ts derniere recherche> } }
 *   Remis a zero automatiquement au changement de date.
 */

if (!defined('BK_SEARCH_LIMIT_FREE'))   define('BK_SEARCH_LIMIT_FREE', 5);     // recherches/jour : CONNECTE sans abonnement
if (!defined('BK_SEARCH_COOLDOWN'))     define('BK_SEARCH_COOLDOWN', 1800);    // 30 min entre 2 recherches (connecte sans abo)
if (!defined('BK_SEARCH_TRIAL_ANON'))   define('BK_SEARCH_TRIAL_ANON', 60);    // ANONYME : 60 s de recherche libre des l'arrivee, puis blocage
// Compat : core/subscription.php lit BK_SEARCH_LIMIT_LOGGED
if (!defined('BK_SEARCH_LIMIT_LOGGED')) define('BK_SEARCH_LIMIT_LOGGED', BK_SEARCH_LIMIT_FREE);

/** IP reelle du visiteur (CloudFlare / proxy / direct). */
function bkSearchClientIp() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    return trim(explode(',', $ip)[0]);
}

/**
 * Verifie (et eventuellement consomme) le quota de recherches.
 *
 * @param mysqli|null $conn    connexion BDD (pour la detection d'abonnement)
 * @param bool        $consume true  = compte cette recherche (endpoints de recherche)
 *                             false = lecture seule (badge nav, api/quota.php)
 * @return array {
 *   blocked: bool,                              // true => recherche refusee
 *   reason: 'daily'|'cooldown'|'trial'|'',       // motif du blocage
 *   used: int, limit: int, remaining: int,      // compteur du jour (connectes)
 *   cooldown_remaining: int, cooldown_total: int,
 *   anon_trial: bool,                           // true => visiteur anonyme en mode "decouverte 60 s"
 *   trial_remaining: int, trial_total: int,     // secondes restantes / totales de la decouverte gratuite
 *   unlimited: bool, logged: bool, is_sa: bool
 * }
 */
function bkSearchLimit($conn, $consume = true) {
    $ip       = bkSearchClientIp();
    $isLogged = !empty($_COOKIE['bk_token']);
    $isSA     = !empty($_COOKIE['bk_sa_token']);
    if (!$isSA && ((($_GET['bk_key'] ?? '') === 'bk_s3cr3t_2026_xK9mP')
                || (($_SERVER['HTTP_X_BK_KEY'] ?? '') === 'bk_s3cr3t_2026_xK9mP'))) {
        $isSA = true;
    }

    $res = [
        'blocked'            => false,
        'reason'             => '',
        'used'               => 0,
        'limit'              => BK_SEARCH_LIMIT_FREE,
        'remaining'          => BK_SEARCH_LIMIT_FREE,
        'cooldown_remaining' => 0,
        'cooldown_total'     => BK_SEARCH_COOLDOWN,
        'anon_trial'         => false,
        'trial_remaining'    => 0,
        'trial_total'        => 0,
        'unlimited'          => false,
        'logged'             => $isLogged,
        'is_sa'              => $isSA,
    ];

    $asUnlimited = function () use (&$res) {
        $res['unlimited']      = true;
        $res['cooldown_total'] = 0;
        return $res;
    };

    // Super admin / cle API => illimite
    if ($isSA) return $asUnlimited();

    // Abonnement : Argent/Or/Platine = illimite, Bronze = quota etendu (sans cooldown)
    $planLimit = null; // null => aucun abonnement actif
    if ($isLogged && $conn && function_exists('getCurrentUser')) {
        require_once __DIR__ . '/subscription.php';
        $u = getCurrentUser($conn);
        if ($u && !empty($u['id_user'])) {
            $info = getUserPlanInfo($conn, $u['id_user']);
            if ($info) {
                if ((int)$info['search_limit'] === -1) return $asUnlimited(); // Argent / Or / Platine
                $planLimit = (int)$info['search_limit'];                       // Bronze
            }
        }
    }

    // Whitelist (Google, Hostinger, localhost) + bots / curl => illimite
    $wl = ['66.249.','66.102.','64.233.','72.14.','74.125.','209.85.','216.239.',
           '35.','34.','153.92.','31.170.','185.201.','127.0.0.1','::1'];
    foreach ($wl as $p) {
        if ($ip !== '' && strpos($ip, $p) === 0) return $asUnlimited();
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || stripos($ua, 'curl') !== false || stripos($ua, 'bot') !== false) return $asUnlimited();
    if ($ip === '') return $asUnlimited();

    // ============ ANONYME : decouverte gratuite de 60 s puis blocage ============
    // Le minuteur demarre a l'arrivee sur le site (cookie bk_anon_t0 pose par index.php).
    // Passe ce delai, toute recherche est refusee jusqu'a la connexion / inscription.
    if (!$isLogged) {
        $res['anon_trial']     = true;
        $res['trial_total']    = BK_SEARCH_TRIAL_ANON;
        $res['limit']          = 0;   // pas de quota au compteur pour l'anonyme
        $res['cooldown_total'] = 0;
        $t0 = isset($_COOKIE['bk_anon_t0']) ? (int)$_COOKIE['bk_anon_t0'] : 0;
        if ($t0 <= 0) {
            // Minuteur pas encore demarre (n'a pas charge le site) : on laisse passer
            $res['trial_remaining'] = BK_SEARCH_TRIAL_ANON;
            return $res;
        }
        $elapsed = time() - $t0;
        $res['trial_remaining'] = max(0, BK_SEARCH_TRIAL_ANON - $elapsed);
        if ($elapsed >= BK_SEARCH_TRIAL_ANON) {
            $res['blocked'] = true;
            $res['reason']  = 'trial';
        }
        return $res;
    }

    // ============ CONNECTE sans abonnement / Bronze : quota jour + cooldown ============
    //   - abonne Bronze     : quota du plan, aucun cooldown
    //   - connecte sans abo : 5/jour + 30 min entre 2 recherches
    $isBronze = ($planLimit !== null);
    $limit    = $isBronze ? $planLimit : BK_SEARCH_LIMIT_FREE;
    $cooldown = $isBronze ? 0          : BK_SEARCH_COOLDOWN;
    $res['limit']          = $limit;
    $res['cooldown_total'] = $cooldown;

    // --- Lecture du fichier compteur ---
    $file  = __DIR__ . '/../logs/.search_limits.php';
    $today = date('Y-m-d');
    $data  = [];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $pos = strpos($raw, "\n");
        if ($pos !== false) $data = json_decode(substr($raw, $pos + 1), true) ?: [];
    }
    // Remise a zero quotidienne automatique
    if (($data['_date'] ?? '') !== $today) $data = ['_date' => $today];

    $entry = $data[$ip] ?? ['c' => 0, 't' => 0];
    if (!is_array($entry)) $entry = ['c' => (int)$entry, 't' => 0]; // compat ancien format {ip: int}
    $count = (int)($entry['c'] ?? 0);
    $last  = (int)($entry['t'] ?? 0);
    $now   = time();

    $res['used']      = $count;
    $res['remaining'] = max(0, $limit - $count);

    // Cooldown en cours ?
    if ($cooldown > 0 && $last > 0 && ($now - $last) < $cooldown) {
        $res['cooldown_remaining'] = $cooldown - ($now - $last);
    }

    // Limite journaliere atteinte ?
    if ($count >= $limit) {
        $res['blocked'] = true;
        $res['reason']  = 'daily';
        return $res;
    }
    // Cooldown actif ?
    if ($res['cooldown_remaining'] > 0) {
        $res['blocked'] = true;
        $res['reason']  = 'cooldown';
        return $res;
    }

    // --- Consommation ---
    if ($consume) {
        $count++;
        $data[$ip] = ['c' => $count, 't' => $now];
        @file_put_contents($file, "<?php die('Acces interdit'); ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
        $res['used']      = $count;
        $res['remaining'] = max(0, $limit - $count);
        // La recherche vient d'etre consommee : le prochain cooldown demarre maintenant
        if ($cooldown > 0) $res['cooldown_remaining'] = $cooldown;
    }

    return $res;
}

/**
 * Champs publics standard a inclure dans les reponses JSON des endpoints de recherche
 * (et api/quota.php). Centralise pour eviter la duplication.
 *
 * @param array $sl  resultat de bkSearchLimit()
 * @return array
 */
function bkSlFields($sl) {
    return [
        'search_used'        => $sl['unlimited'] ? 0 : (int)$sl['used'],
        'search_limit'       => $sl['unlimited'] ? 0 : (int)$sl['limit'],
        'cooldown_remaining' => (int)$sl['cooldown_remaining'],
        'cooldown_total'     => (int)$sl['cooldown_total'],
        'anon_trial'         => (bool)$sl['anon_trial'],
        'trial_remaining'    => (int)$sl['trial_remaining'],
        'trial_total'        => (int)$sl['trial_total'],
        'logged'             => (bool)$sl['logged'],
        'is_sa'              => (bool)$sl['is_sa'],
        'unlimited'          => (bool)$sl['unlimited'],
    ];
}
