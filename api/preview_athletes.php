<?php
/**
 * api/preview_athletes.php — Simulation server-side de la page /athletes
 *
 * Reproduit en 1 seul appel ce que la page Athletes affiche : boucle niveaux x sexes
 * via api/liste.php, puis merge/interleave comme index.php le fait (lignes ~2270-2289).
 *
 * Source des filtres :
 *   - Par defaut : logs/.athletes_filter.php (config admin sauvegardee)
 *   - Override possible via GET params (admin uniquement)
 *
 * Parametres GET (tous optionnels) :
 *   niveaux=IA,IB                  Codes separes par virgules
 *   annee=2026                     recent_year_min
 *   nb_hommes=50                   max H (0..200)
 *   nb_femmes=50                   max F (0..200)
 *   club=ES MASSY                  Filtre club (LIKE)
 *   epreuve=100m|200m              Liste pipe-separee (active mode strict si fourni)
 *   strict=1                       Force le mode strict niveau-par-epreuve
 *   ordre=medailles                Tri (medailles|podiums|selections|records|nom|date|id|recent)
 *   debug=1                        Inclut les URLs liste.php utilisees
 *
 * Reponse JSON :
 *   { success, count, count_h, count_f, athletes[], by_sexe: {M[], F[]},
 *     filters_used, elapsed_ms, [debug_urls] }
 */

require_once __DIR__ . '/config.php';

$tStart = microtime(true);

// === Auth admin (cookie bk_sa_token ou panel_access) requise pour les overrides ===
function _previewIsAdmin() {
    if (!empty($_COOKIE['bk_sa_token'])) {
        $saFile = __DIR__ . '/../logs/.sa_sessions.php';
        if (file_exists($saFile)) {
            $raw = @file_get_contents($saFile);
            $pos = $raw ? strpos($raw, "\n") : false;
            if ($pos !== false) {
                $sessions = json_decode(substr($raw, $pos + 1), true) ?: [];
                $tok = $_COOKIE['bk_sa_token'];
                if (isset($sessions[$tok]) && ($sessions[$tok]['expires'] ?? 0) > time()) return true;
            }
        }
    }
    global $conn;
    $u = function_exists('getCurrentUser') ? getCurrentUser($conn) : null;
    if ($u) {
        $accFile = __DIR__ . '/../logs/.panel_access.php';
        if (file_exists($accFile)) {
            $raw = @file_get_contents($accFile);
            $pos = $raw ? strpos($raw, "\n") : false;
            if ($pos !== false) {
                $list = json_decode(substr($raw, $pos + 1), true) ?: [];
                if (isset($list[$u['email']])) return true;
            }
        }
    }
    return false;
}

// === Lecture du filtre sauvegarde ===
$filterFile = __DIR__ . '/../logs/.athletes_filter.php';
$saved = [
    'niveaux' => ['IA','IB'],
    'annee' => (int)date('Y'),
    'nb_hommes' => 50,
    'nb_femmes' => 50,
    'club_filter' => '',
    'epreuve_filter' => '',
    'filter_cible_enabled' => false,
];
if (file_exists($filterFile)) {
    $raw = @file_get_contents($filterFile);
    $pos = $raw ? strpos($raw, "\n") : false;
    if ($pos !== false) {
        $d = json_decode(substr($raw, $pos + 1), true) ?: [];
        if (!empty($d['niveaux']) && is_array($d['niveaux'])) $saved['niveaux'] = $d['niveaux'];
        if (isset($d['annee'])) $saved['annee'] = (int)$d['annee'];
        if (isset($d['nb_hommes'])) $saved['nb_hommes'] = (int)$d['nb_hommes'];
        if (isset($d['nb_femmes'])) $saved['nb_femmes'] = (int)$d['nb_femmes'];
        $saved['club_filter'] = (string)($d['club_filter'] ?? '');
        $saved['epreuve_filter'] = (string)($d['epreuve_filter'] ?? '');
        $saved['filter_cible_enabled'] = !empty($d['filter_cible_enabled']);
    }
}

// === Si on detecte un override, exiger admin ===
$hasOverride = isset($_GET['niveaux']) || isset($_GET['annee']) || isset($_GET['nb_hommes'])
    || isset($_GET['nb_femmes']) || isset($_GET['club']) || isset($_GET['epreuve'])
    || isset($_GET['strict']) || isset($_GET['ordre']);
if ($hasOverride && !_previewIsAdmin()) {
    jsonResponse(['success' => false, 'error' => 'Override params reserved to admin'], 403);
}

// === Resolution des parametres effectifs ===
$allowedNiv = ['IA','IB','IE','IR','IR2','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
$nivRaw = isset($_GET['niveaux']) ? (string)$_GET['niveaux'] : implode(',', $saved['niveaux']);
$niveaux = array_values(array_intersect(
    array_map('trim', explode(',', $nivRaw)),
    $allowedNiv
));
if (empty($niveaux)) $niveaux = $saved['niveaux'];

$annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)$saved['annee'];
if ($annee < 2000 || $annee > (int)date('Y') + 1) $annee = (int)date('Y');

$nbH = isset($_GET['nb_hommes']) ? (int)$_GET['nb_hommes'] : (int)$saved['nb_hommes'];
$nbF = isset($_GET['nb_femmes']) ? (int)$_GET['nb_femmes'] : (int)$saved['nb_femmes'];
$nbH = max(0, min(200, $nbH));
$nbF = max(0, min(200, $nbF));

$clubFilter = isset($_GET['club']) ? trim((string)$_GET['club']) : (string)$saved['club_filter'];
$epreuveFilter = isset($_GET['epreuve']) ? trim((string)$_GET['epreuve']) : (string)$saved['epreuve_filter'];
$useTargeted = !empty($saved['filter_cible_enabled']) || isset($_GET['epreuve']) || isset($_GET['club']);
if (!$useTargeted) {
    $clubFilter = '';
    $epreuveFilter = '';
}

$allowedOrdre = ['nom','date','id','recent','medailles','podiums','selections','records','random'];
$ordre = isset($_GET['ordre']) && in_array($_GET['ordre'], $allowedOrdre, true) ? $_GET['ordre'] : 'medailles';

// Mode strict : niveau filtre = niveau ATTEINT SUR cette epreuve precise
$epDefault = '100m|200m|400m Haies (76)|400m Haies (91)|110m Haies (91)|110m Haies (99)|110m Haies (106)|Longueur|Triple saut|Perche';
$strictMode = !empty($_GET['strict']) || ($epreuveFilter !== '');
$epreuves = $epreuveFilter !== '' ? $epreuveFilter : $epDefault;

if ($nbH === 0 && $nbF === 0) {
    jsonResponse([
        'success' => false,
        'error' => 'nb_hommes et nb_femmes sont a 0',
    ], 400);
}

// === Construction des URLs liste.php (1 par niveau x sexe) ===
$nbLvls = max(1, count($niveaux));
$perLvlH = (int)ceil($nbH / $nbLvls);
$perLvlF = (int)ceil($nbF / $nbLvls);

// Determine la base URL pour les loopback (Hostinger : http(s) + host + dossier api/)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseApi = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

function _previewBuildUrl($baseApi, $sexe, $limit, $niveau, $ordre, $annee, $epreuves, $club, $strict) {
    $params = [
        'limit' => $limit,
        'ordre' => $ordre,
        'niveau' => $niveau,
        'sexe' => $sexe,
        'annee_exact' => $annee,
        'epreuve' => $epreuves,
    ];
    if ($club !== '') $params['club'] = $club;
    if ($strict) $params['niveau_strict_ep'] = 1;
    return $baseApi . '/liste.php?' . http_build_query($params);
}

$jobs = [];
foreach ($niveaux as $lvl) {
    if ($nbH > 0) $jobs[] = ['sexe' => 'M', 'niveau' => $lvl, 'url' => _previewBuildUrl($baseApi, 'M', $perLvlH, $lvl, $ordre, $annee, $epreuves, $clubFilter, $strictMode)];
    if ($nbF > 0) $jobs[] = ['sexe' => 'F', 'niveau' => $lvl, 'url' => _previewBuildUrl($baseApi, 'F', $perLvlF, $lvl, $ordre, $annee, $epreuves, $clubFilter, $strictMode)];
}

// === Fetch parallele via curl_multi ===
$mh = curl_multi_init();
$handles = [];
foreach ($jobs as $i => $job) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $job['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'BK-Preview/1.0');
    curl_multi_add_handle($mh, $ch);
    $handles[$i] = $ch;
}

$running = null;
do {
    curl_multi_exec($mh, $running);
    if ($running > 0) curl_multi_select($mh, 0.1);
} while ($running > 0);

$athsM = [];
$athsF = [];
$errors = [];
$debugUrls = [];

foreach ($handles as $i => $ch) {
    $body = curl_multi_getcontent($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $job = $jobs[$i];
    if ($code !== 200 || empty($body)) {
        $errors[] = ['sexe' => $job['sexe'], 'niveau' => $job['niveau'], 'http' => $code];
    } else {
        $data = json_decode($body, true);
        $list = $data['athletes'] ?? [];
        if (is_array($list)) {
            if ($job['sexe'] === 'M') $athsM = array_merge($athsM, $list);
            else $athsF = array_merge($athsF, $list);
        }
    }
    if (!empty($_GET['debug'])) $debugUrls[] = $job['url'];
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

// === Dedup par athlete_id (mode any peut faire ressortir le meme athlete sur plusieurs niveaux) ===
$_dedup = function($arr) {
    $seen = [];
    $out = [];
    foreach ($arr as $a) {
        $id = (int)($a['athlete_id'] ?? 0);
        if ($id <= 0 || isset($seen[$id])) continue;
        $seen[$id] = true;
        $out[] = $a;
    }
    return $out;
};
$athsM = $_dedup($athsM);
$athsF = $_dedup($athsF);

// === Tronque + interleave (memes regles que index.php:2281-2288) ===
$athsM = array_slice($athsM, 0, $nbH);
$athsF = array_slice($athsF, 0, $nbF);

$merged = [];
$maxLen = max(count($athsM), count($athsF));
for ($i = 0; $i < $maxLen; $i++) {
    if (isset($athsM[$i])) $merged[] = $athsM[$i];
    if (isset($athsF[$i])) $merged[] = $athsF[$i];
}

$elapsed = (int)round((microtime(true) - $tStart) * 1000);

$response = [
    'success'    => true,
    'count'      => count($merged),
    'count_h'    => count($athsM),
    'count_f'    => count($athsF),
    'athletes'   => $merged,
    'by_sexe'    => ['M' => $athsM, 'F' => $athsF],
    'filters_used' => [
        'niveaux'        => $niveaux,
        'annee'          => $annee,
        'nb_hommes'      => $nbH,
        'nb_femmes'      => $nbF,
        'per_level_h'    => $perLvlH,
        'per_level_f'    => $perLvlF,
        'club'           => $clubFilter,
        'epreuves'       => $epreuves,
        'epreuve_admin'  => $epreuveFilter,
        'strict_mode'    => $strictMode,
        'ordre'          => $ordre,
        'source'         => $hasOverride ? 'override' : 'saved',
    ],
    'jobs_count' => count($jobs),
    'errors'     => $errors,
    'elapsed_ms' => $elapsed,
];
if (!empty($_GET['debug'])) $response['debug_urls'] = $debugUrls;

jsonResponse($response);
