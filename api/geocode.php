<?php
/**
 * api/geocode.php — Geocodage des villes (nom -> lat/lng) avec cache persistant
 *
 * Entree  : POST JSON { "villes": ["PARIS","LYON",...] }   ou   GET ?villes=PARIS,LYON
 * Sortie  : { success, coords:{ "PARIS":{lat,lng}, ... }, total, located, pending, done }
 *
 * Cache   : logs/.geocode.php  — JSON protege par die(), HORS de cache/ pour survivre
 *           aux vidages de cache (clear_cache.php). Le geocodage est couteux : on ne
 *           veut pas le perdre.
 * Source  : Nominatim / OpenStreetMap (gratuit, sans cle). Limite a 1 req/s : on ne
 *           geocode qu'un petit lot par appel, le front rappelle l'endpoint en boucle
 *           jusqu'a ce que toutes les villes soient localisees (remplissage progressif).
 */

require_once __DIR__ . '/config.php';

@set_time_limit(45);

$GEO_FILE   = __DIR__ . '/../logs/.geocode.php';
$GEO_HEADER = "<?php die('Acces interdit'); ?>\n";
$BATCH_MAX  = 6;   // villes geocodees par appel (respect du 1 req/s Nominatim)
$TIME_LIMIT = 25;  // secondes max passees a geocoder dans cet appel

/** Lit le cache de geocodage (tableau associatif CLE_MAJ => ['lat','lng'] | ['nf'=>1]) */
function geoLoad($file) {
    if (!is_file($file)) return [];
    $raw = file_get_contents($file);
    if ($raw === false) return [];
    $nl = strpos($raw, "\n");
    if ($nl !== false) $raw = substr($raw, $nl + 1);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Fusionne de nouvelles entrees dans le cache (read-modify-write sous verrou exclusif) */
function geoSave($file, $header, array $newEntries) {
    if (empty($newEntries)) return;
    $fp = @fopen($file, 'c+');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    rewind($fp);
    $raw = stream_get_contents($fp);
    $existing = [];
    if ($raw !== false && $raw !== '') {
        $nl = strpos($raw, "\n");
        $j  = json_decode($nl !== false ? substr($raw, $nl + 1) : $raw, true);
        if (is_array($j)) $existing = $j;
    }
    $merged = $newEntries + $existing; // les nouvelles entrees priment en cas de collision
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $header . json_encode($merged, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * Une requete Nominatim. $fr = true => resultat limite a la France.
 * featureType=settlement : Nominatim ne renvoie que des localites habitees
 * (ville/commune/village/prefecture...), ce qui evite qu'une rue ou un commerce
 * homonyme soit pris pour une ville (ex : "Tokyo" -> un resto a Paris).
 */
function nominatimQuery($q, $fr) {
    $params = [
        'q'           => $q,
        'format'      => 'jsonv2',
        'limit'       => 3,
        'featureType' => 'settlement',
    ];
    if ($fr) $params['countrycodes'] = 'fr';
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query($params);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_USERAGENT      => 'Bokonzi/1.0 (+https://bokonzi.com)',
        CURLOPT_HTTPHEADER     => ['Accept-Language: fr'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$resp) return null;
    $arr = json_decode($resp, true);
    if (!is_array($arr)) return null;
    foreach ($arr as $hit) {
        if (!isset($hit['lat'], $hit['lon'])) continue;
        return ['lat' => round((float)$hit['lat'], 5), 'lng' => round((float)$hit['lon'], 5)];
    }
    return null;
}

/** Geocode une ville : essai France d'abord (athle.fr = surtout des villes FR), puis monde */
function geocodeCity($name) {
    $name = trim($name);
    if ($name === '') return null;
    $c = nominatimQuery($name, true);
    if (!$c) $c = nominatimQuery($name, false);
    return $c;
}

// ── Entree : liste des villes (POST JSON prioritaire, fallback GET) ───────────────
$villes = [];
$body = file_get_contents('php://input');
if ($body) {
    $j = json_decode($body, true);
    if (is_array($j) && isset($j['villes']) && is_array($j['villes'])) $villes = $j['villes'];
}
if (empty($villes) && isset($_GET['villes'])) {
    $villes = explode(',', $_GET['villes']);
}
if (!is_array($villes)) $villes = [];

// Nettoyage : trim, non vide, longueur raisonnable, dedup, plafond
$clean = [];
foreach ($villes as $v) {
    if (!is_string($v)) continue;
    $v = trim($v);
    if ($v === '' || mb_strlen($v) > 150) continue;
    $clean[$v] = true;
}
$villes = array_keys($clean);
if (count($villes) > 200) $villes = array_slice($villes, 0, 200);

if (empty($villes)) {
    jsonResponse([
        'success' => false, 'error' => 'Aucune ville fournie',
        'coords' => [], 'total' => 0, 'located' => 0, 'pending' => 0, 'done' => true,
    ]);
}

// ── Lecture du cache : ce qui est connu vs ce qu'il faut geocoder ────────────────
$cache     = geoLoad($GEO_FILE);
$coords    = [];
$toGeocode = [];
foreach ($villes as $v) {
    $key = mb_strtoupper($v);
    if (array_key_exists($key, $cache)) {
        $e = $cache[$key];
        if (is_array($e) && isset($e['lat'], $e['lng'])) {
            $coords[$v] = ['lat' => $e['lat'], 'lng' => $e['lng']];
        }
        // sinon : entree 'nf' (deja tentee, introuvable) -> on ne renvoie rien, pas de re-essai
    } else {
        $toGeocode[] = $v;
    }
}

// ── Geocodage d'un lot limite (Nominatim : 1 req/s) ─────────────────────────────
$newEntries = [];
$start = microtime(true);
$done  = 0;
foreach ($toGeocode as $v) {
    if ($done >= $BATCH_MAX) break;
    if (microtime(true) - $start > $TIME_LIMIT) break;
    if ($done > 0) usleep(1100000); // 1,1 s entre deux requetes
    $c   = geocodeCity($v);
    $key = mb_strtoupper($v);
    if ($c) {
        $newEntries[$key] = $c;
        $coords[$v] = $c;
    } else {
        $newEntries[$key] = ['nf' => 1]; // not found : evite de re-tenter indefiniment
    }
    $done++;
}

geoSave($GEO_FILE, $GEO_HEADER, $newEntries);

$pending = max(0, count($toGeocode) - $done);
jsonResponse([
    'success' => true,
    'coords'  => $coords,                 // { "PARIS": {lat,lng}, ... } — cle = nom envoye
    'total'   => count($villes),
    'located' => count($coords),
    'pending' => $pending,
    'done'    => $pending === 0,
]);
