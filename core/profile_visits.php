<?php
/**
 * core/profile_visits.php — Compteur de visites par fiche profil (IP uniques)
 *
 * Stockage fichier JSON protege : logs/.profile_visits.php
 *   { "<athlete_id_externe>": { "n": <visiteurs uniques>, "t": <vues totales>, "h": { "<iphash>": 1 } } }
 *
 * - "n" = nombre d'IP uniques (le compteur affiche). "t" = total brut.
 * - Les IP sont hachees (md5 tronque) : on ne stocke jamais l'IP en clair.
 * - Bots & IP whitelistees (Google, Hostinger, localhost) NE sont PAS comptes
 *   (sinon le crawl SEO gonflerait le compteur).
 */

define('BK_PVISIT_FILE', __DIR__ . '/../logs/.profile_visits.php');

/** IP du visiteur (CloudFlare / proxy aware). */
function bkPVisitIp() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    return trim(explode(',', $ip)[0]);
}

/** Bot ou IP whitelistee → on ne compte pas. */
function bkPVisitIsBot() {
    $ip = bkPVisitIp();
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
    return false;
}

/** Lecture du fichier compteur. */
function bkPVisitRead() {
    if (!file_exists(BK_PVISIT_FILE)) return [];
    $raw = file_get_contents(BK_PVISIT_FILE);
    $pos = strpos($raw, "\n");
    $d = ($pos !== false) ? json_decode(substr($raw, $pos + 1), true) : null;
    return is_array($d) ? $d : [];
}

/** Ecriture (protegee par die()). */
function bkPVisitWrite($d) {
    @file_put_contents(BK_PVISIT_FILE, "<?php die('Acces interdit'); ?>\n" . json_encode($d), LOCK_EX);
}

/**
 * Enregistre une visite et renvoie le compteur.
 * @param int  $aid    athlete_id_externe
 * @param bool $count  false = lecture seule (ex : super admin, pour ne pas gonfler)
 * @return array { unique:int, total:int, is_new:bool }
 */
function bkProfileVisit($aid, $count = true) {
    $aid = (int)$aid;
    if ($aid <= 0) return ['unique' => 0, 'total' => 0, 'is_new' => false];

    $data = bkPVisitRead();
    $rec  = $data[$aid] ?? ['n' => 0, 't' => 0, 'h' => []];
    $isNew = false;

    if ($count && !bkPVisitIsBot()) {
        $h = substr(md5(bkPVisitIp() . '|bkpv2026'), 0, 12);
        if (empty($rec['h']) || !isset($rec['h'][$h])) {
            $rec['h'][$h] = 1;
            $rec['n'] = (int)($rec['n'] ?? 0) + 1;
            $isNew = true;
        }
        $rec['t'] = (int)($rec['t'] ?? 0) + 1;
        $data[$aid] = $rec;
        bkPVisitWrite($data);
    }

    return ['unique' => (int)($rec['n'] ?? 0), 'total' => (int)($rec['t'] ?? 0), 'is_new' => $isNew];
}

/**
 * Phrase humoristique selon le nombre de visiteurs uniques.
 * @return string HTML pret a afficher (entites deja echappees).
 */
function bkProfileVisitFunLine($unique) {
    $n = (int)$unique;
    $fmt = number_format($n, 0, ',', "\u{202F}"); // espace fine insecable
    if ($n <= 0)      return "&#128064; Personne n'est encore venu fouiner ici&hellip; Soyez le tout premier&nbsp;!";
    if ($n === 1)     return "&#128064; <b>1</b> seul curieux est pass&eacute; par l&agrave;. (coucou, c'est peut-&ecirc;tre vous)";
    if ($n < 10)      return "&#128064; <b>$fmt</b> curieux ont d&eacute;j&agrave; fouin&eacute; sur cette fiche.";
    if ($n < 50)      return "&#128293; D&eacute;j&agrave; <b>$fmt</b> visiteurs uniques &mdash; &ccedil;a commence &agrave; jaser&nbsp;!";
    if ($n < 200)     return "&#11088; <b>$fmt</b> visiteurs sont pass&eacute;s par ici. Petite c&eacute;l&eacute;brit&eacute; locale.";
    if ($n < 1000)    return "&#127942; <b>$fmt</b> visiteurs uniques&nbsp;! On fr&ocirc;le la starification.";
    if ($n < 10000)   return "&#128293; <b>$fmt</b> visiteurs&nbsp;?! Une v&eacute;ritable l&eacute;gende vivante.";
    return "&#128081; <b>$fmt</b> visiteurs uniques. Bient&ocirc;t une statue sur la grande place.";
}
