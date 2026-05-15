<?php
/**
 * core/profile_gate.php — Limite « offre gratuite » sur les fiches profil
 *
 * Règle (décidée avec le client) :
 *   - Offre gratuite (anonyme OU compte gratuit) = 1 seule fiche profil par jour,
 *     consultable pendant 2 minutes, puis blocage avec proposition d'abonnement.
 *   - Exemptés : super admin, abonnés actifs (Bronze/Argent/Or/Platine),
 *     bots & IP whitelistées (Google, Hostinger, localhost) → accès illimité,
 *     ce qui préserve le référencement.
 *
 * Le compteur est stocké par IP, par jour, dans logs/.profile_views.php.
 * On mémorise aussi l'horodatage de la 1re ouverture de chaque profil pour
 * que le minuteur de 2 min résiste au rechargement de la page.
 */

require_once __DIR__ . '/paths.php'; // BK_BASE

define('BK_PROFILE_FREE_SECONDS', 120);          // durée de consultation gratuite
define('BK_PROFILE_GATE_FILE', __DIR__ . '/../logs/.profile_views.php');

/** IP du visiteur (CloudFlare / proxy aware). */
function bkGateIp() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    return trim(explode(',', $ip)[0]);
}

/**
 * Le visiteur est-il exempté de la limite ?
 *  super admin · abonné actif · bot/IP whitelistée.
 */
function bkProfileGateExempt($conn) {
    // Super admin
    if (!empty($_COOKIE['bk_sa_token'])) return true;

    // Bots & IP whitelistées (Google, Hostinger, localhost) → SEO préservé
    $ip = bkGateIp();
    $wl = ['66.249.','66.102.','64.233.','72.14.','74.125.','209.85.','216.239.',
           '35.','34.','153.92.','31.170.','185.201.','127.0.0.1','::1'];
    foreach ($wl as $p) {
        if ($ip !== '' && strpos($ip, $p) === 0) return true;
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || stripos($ua, 'bot') !== false || stripos($ua, 'curl') !== false
        || stripos($ua, 'spider') !== false || stripos($ua, 'crawl') !== false) {
        return true;
    }

    // Abonné actif
    if (!empty($_COOKIE['bk_token'])) {
        if (!function_exists('getCurrentUser'))   require_once __DIR__ . '/auth.php';
        if (!function_exists('hasActiveSubscription')) require_once __DIR__ . '/subscription.php';
        $u = getCurrentUser($conn);
        if ($u && hasActiveSubscription($conn, $u['id_user'])) return true;
    }
    return false;
}

/** Lit le fichier compteur (remis à zéro automatiquement chaque jour). */
function bkGateRead() {
    $today = date('Y-m-d');
    if (!file_exists(BK_PROFILE_GATE_FILE)) {
        return ['date' => $today, 'ips' => []];
    }
    $raw = file_get_contents(BK_PROFILE_GATE_FILE);
    $pos = strpos($raw, "\n");
    $data = ($pos !== false) ? json_decode(substr($raw, $pos + 1), true) : null;
    if (!is_array($data) || ($data['date'] ?? '') !== $today) {
        return ['date' => $today, 'ips' => []]; // nouveau jour → reset
    }
    if (!isset($data['ips']) || !is_array($data['ips'])) $data['ips'] = [];
    return $data;
}

/** Écrit le fichier compteur (protégé par die()). */
function bkGateWrite($data) {
    @file_put_contents(
        BK_PROFILE_GATE_FILE,
        "<?php die('Acces interdit'); ?>\n" . json_encode($data),
        LOCK_EX
    );
}

/**
 * Statut de l'accès à une fiche profil pour le visiteur courant.
 * À appeler UNE SEULE FOIS par requête (effet de bord : enregistre la vue).
 *
 * @return array {
 *   allowed   : bool   le profil peut-il s'afficher ?
 *   exempt    : bool   visiteur illimité (pas de minuteur, pas de décompte)
 *   reason    : string 'exempt' | 'first' | 'same_profile' | 'limit' | 'timer_expired'
 *   remaining : int    secondes restantes avant blocage (minuteur), 0 si exempt/bloqué
 * }
 */
function bkProfileGateStatus($conn, $athleteIdExterne) {
    if (bkProfileGateExempt($conn)) {
        return ['allowed' => true, 'exempt' => true, 'reason' => 'exempt', 'remaining' => 0];
    }

    $ip  = bkGateIp();
    $aid = (int)$athleteIdExterne;
    if ($ip === '' || $aid <= 0) {
        // Cas dégradé : on n'enferme pas le visiteur s'il manque une info technique
        return ['allowed' => true, 'exempt' => false, 'reason' => 'first', 'remaining' => BK_PROFILE_FREE_SECONDS];
    }

    $data = bkGateRead();
    $list = $data['ips'][$ip] ?? []; // [ {id, ts}, ... ]
    $now  = time();

    // Profil déjà ouvert aujourd'hui par cette IP → on calcule le temps restant
    foreach ($list as $entry) {
        if ((int)$entry['id'] === $aid) {
            $elapsed   = $now - (int)$entry['ts'];
            $remaining = BK_PROFILE_FREE_SECONDS - $elapsed;
            if ($remaining <= 0) {
                return ['allowed' => false, 'exempt' => false, 'reason' => 'timer_expired', 'remaining' => 0];
            }
            return ['allowed' => true, 'exempt' => false, 'reason' => 'same_profile', 'remaining' => $remaining];
        }
    }

    // Profil différent : autorisé seulement si aucune fiche déjà consultée aujourd'hui
    if (count($list) >= 1) {
        return ['allowed' => false, 'exempt' => false, 'reason' => 'limit', 'remaining' => 0];
    }

    // 1re fiche de la journée → on l'enregistre
    $data['ips'][$ip][] = ['id' => $aid, 'ts' => $now];
    bkGateWrite($data);
    return ['allowed' => true, 'exempt' => false, 'reason' => 'first', 'remaining' => BK_PROFILE_FREE_SECONDS];
}

/**
 * HTML du mur de paiement affiché côté serveur quand l'accès est refusé.
 * @param string $mode 'limit' (déjà 1 profil vu aujourd'hui) | 'timer_expired' (2 min écoulées)
 */
function bkProfilePaywallHtml($mode = 'limit') {
    $isTimer = ($mode === 'timer_expired');
    $title = $isTimer ? 'Votre aperçu gratuit est terminé' : 'Limite gratuite atteinte';
    $intro = $isTimer
        ? 'Vous avez consulté cette fiche pendant 2 minutes — la durée offerte par la formule gratuite.'
        : 'La formule gratuite donne accès à <b>une seule fiche athlète par jour</b>. Vous l\'avez déjà utilisée aujourd\'hui.';
    $base = BK_BASE;
    ob_start();
    ?>
    <div class="bk-pgate">
        <div class="bk-pgate-card">
            <div class="bk-pgate-ic">&#128274;</div>
            <h2 class="bk-pgate-h">&#9670; <?= $title ?></h2>
            <p class="bk-pgate-p"><?= $intro ?></p>
            <p class="bk-pgate-p">Passez à un abonnement BOKONZI pour consulter <b>autant de profils que vous voulez, sans minuteur</b> — à partir de 1,99&nbsp;€/mois.</p>
            <div class="bk-pgate-btns">
                <a href="<?= $base ?>/tarifs" class="bk-pgate-cta">Voir les offres &rarr;</a>
                <?php if (empty($_COOKIE['bk_token'])): ?>
                <a href="<?= $base ?>/login.php" class="bk-pgate-cta2">Se connecter</a>
                <?php endif; ?>
            </div>
            <p class="bk-pgate-note">Sans abonnement, l'accès se réinitialise demain : 1 nouvelle fiche gratuite par jour.</p>
        </div>
    </div>
    <style>
    .bk-pgate{display:flex;align-items:center;justify-content:center;min-height:62vh;padding:40px 20px;font-family:Inter,system-ui,sans-serif;}
    .bk-pgate-card{max-width:480px;width:100%;background:linear-gradient(150deg,#131a28,#0d1117);border:1px solid #1e2a3a;border-radius:18px;padding:38px 30px;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.5);}
    .bk-pgate-ic{font-size:46px;margin-bottom:10px;}
    .bk-pgate-h{color:#fff;font-size:21px;font-weight:800;margin:0 0 12px;border:none;}
    .bk-pgate-p{color:#8b949e;font-size:14px;line-height:1.6;margin:0 0 12px;}
    .bk-pgate-p b{color:#c9d1d9;}
    .bk-pgate-btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin:20px 0 14px;}
    .bk-pgate-cta{display:inline-block;padding:12px 22px;border-radius:11px;font-size:14px;font-weight:700;text-decoration:none;background:linear-gradient(135deg,#6c5ce7,#ec4899);color:#fff;}
    .bk-pgate-cta:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(236,72,153,.4);}
    .bk-pgate-cta2{display:inline-block;padding:12px 22px;border-radius:11px;font-size:14px;font-weight:700;text-decoration:none;border:1.5px solid #1e2a3a;color:#c9d1d9;}
    .bk-pgate-cta2:hover{border-color:#6c5ce7;color:#a78bfa;}
    .bk-pgate-note{color:#5a6580;font-size:12px;margin:6px 0 0;line-height:1.5;}
    </style>
    <?php
    return ob_get_clean();
}

/**
 * <script> qui bloque la page après le temps restant (minuteur 2 min).
 * À insérer dans la page profil quand l'accès est autorisé mais NON exempté.
 * @param int $remaining secondes restantes avant blocage
 */
function bkProfileTimerBlock($remaining) {
    $remaining = max(1, (int)$remaining);
    $base = BK_BASE;
    $loginBtn = empty($_COOKIE['bk_token'])
        ? '<a href="' . $base . '/login.php" style="display:inline-block;padding:12px 22px;border-radius:11px;font-size:14px;font-weight:700;text-decoration:none;border:1.5px solid #30363d;color:#c9d1d9;">Se connecter</a>'
        : '';
    ob_start();
    ?>
    <script>
    (function(){
        var SECS = <?= $remaining ?>;
        function bkLockProfile(){
            if (document.getElementById('bkProfileWall')) return;
            var ov = document.createElement('div');
            ov.id = 'bkProfileWall';
            ov.style.cssText = 'position:fixed;inset:0;z-index:2147483600;background:rgba(8,12,20,.93);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:24px;font-family:Inter,system-ui,sans-serif;';
            ov.innerHTML =
                '<div style="max-width:480px;width:100%;background:linear-gradient(150deg,#131a28,#0d1117);border:1px solid #1e2a3a;border-radius:18px;padding:38px 30px;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.6);">'
              + '<div style="font-size:46px;margin-bottom:10px;">&#128274;</div>'
              + '<h2 style="color:#fff;font-size:21px;font-weight:800;margin:0 0 12px;border:none;">&#9670; Votre aper&ccedil;u gratuit est termin&eacute;</h2>'
              + '<p style="color:#8b949e;font-size:14px;line-height:1.6;margin:0 0 12px;">La formule gratuite offre <b style="color:#c9d1d9;">2 minutes</b> de consultation sur <b style="color:#c9d1d9;">1 fiche par jour</b>.</p>'
              + '<p style="color:#8b949e;font-size:14px;line-height:1.6;margin:0 0 20px;">Abonnez-vous pour un acc&egrave;s <b style="color:#c9d1d9;">illimit&eacute;, sans minuteur</b> &mdash; d&egrave;s 1,99&nbsp;&euro;/mois.</p>'
              + '<div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">'
              + '<a href="<?= $base ?>/tarifs" style="display:inline-block;padding:12px 22px;border-radius:11px;font-size:14px;font-weight:700;text-decoration:none;background:linear-gradient(135deg,#6c5ce7,#ec4899);color:#fff;">Voir les offres &rarr;</a>'
              + '<?= $loginBtn ?>'
              + '</div>'
              + '<p style="color:#5a6580;font-size:12px;margin:16px 0 0;">Revenez demain pour 1 nouvelle fiche gratuite.</p>'
              + '</div>';
            document.body.appendChild(ov);
            document.body.style.overflow = 'hidden';
        }
        if (SECS <= 0) { bkLockProfile(); }
        else { setTimeout(bkLockProfile, SECS * 1000); }
    })();
    </script>
    <?php
    return ob_get_clean();
}
