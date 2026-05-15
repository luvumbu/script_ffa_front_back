<?php
/**
 * api/athlete_socials.php — Reseaux sociaux d'un athlete
 *
 * GET  ?id=X                  → recupere les liens enregistres
 * POST { id: X, facebook, tiktok, instagram, youtube, twitter } → enregistre
 *
 * Auth POST : super admin OU panel access OU owner match (nom/email)
 * Stockage : logs/.athlete_socials.php (JSON, BDD intacte)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/social_match.php';

$method = $_SERVER['REQUEST_METHOD'];
$socFile = __DIR__ . '/../logs/.athlete_socials.php';

function bk_load_all_socials($file) {
    if (!file_exists($file)) return [];
    $raw = @file_get_contents($file);
    if ($raw === false) return [];
    $pos = strpos($raw, "\n");
    if ($pos === false) return [];
    $data = json_decode(substr($raw, $pos + 1), true);
    return is_array($data) ? $data : [];
}
function bk_save_all_socials($file, $data) {
    $payload = "<?php die(); ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return @file_put_contents($file, $payload) !== false;
}

// === GET ===
if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(['success' => false, 'error' => 'id requis'], 400);
    $all = bk_load_all_socials($socFile);
    jsonResponse([
        'success' => true,
        'socials' => $all[$id] ?? [],
    ]);
}

// === POST ===
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = $_POST;
    $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(['success' => false, 'error' => 'id requis'], 400);

    // Charger l'athlete pour matching
    $rs = $conn->query("SELECT nom_complet_athlete FROM athletes WHERE athlete_id_externe = $id LIMIT 1");
    if (!$rs || !$rs->num_rows) jsonResponse(['success' => false, 'error' => 'athlete inconnu'], 404);
    $athName = $rs->fetch_assoc()['nom_complet_athlete'];

    // === AUTH ===
    $isAuthorized = false;
    $authReason = '';

    // 1. Super admin (cookie)
    if (!empty($_COOKIE['bk_sa_token'])) {
        $saFile = __DIR__ . '/../logs/.sa_sessions.php';
        if (file_exists($saFile)) {
            $saRaw = @file_get_contents($saFile);
            $saPos = strpos($saRaw, "\n");
            if ($saPos !== false) {
                $saSessions = json_decode(substr($saRaw, $saPos + 1), true) ?: [];
                if (isset($saSessions[$_COOKIE['bk_sa_token']]) && ($saSessions[$_COOKIE['bk_sa_token']]['expires'] ?? 0) > time()) {
                    $isAuthorized = true;
                    $authReason = 'super_admin';
                }
            }
        }
    }

    // 2. Owner match (nom + email)
    if (!$isAuthorized) {
        $user = getCurrentUser($conn);
        if (!$user) jsonResponse(['success' => false, 'error' => 'connexion requise'], 401);

        $matchInfo = bk_athlete_owner_match(
            $user['prenom'] ?? '',
            $user['nom'] ?? '',
            $user['email'] ?? '',
            $athName
        );
        if ($matchInfo['match']) {
            $isAuthorized = true;
            $authReason = 'owner_' . $matchInfo['reason'] . '_' . $matchInfo['score'] . '%';
        }
    }

    // 3. Panel access (whitelist email)
    if (!$isAuthorized && !empty($_COOKIE['bk_token'])) {
        $user = getCurrentUser($conn);
        if ($user) {
            $paFile = __DIR__ . '/../logs/.panel_access.php';
            if (file_exists($paFile)) {
                $paRaw = @file_get_contents($paFile);
                $paPos = strpos($paRaw, "\n");
                if ($paPos !== false) {
                    $paList = json_decode(substr($paRaw, $paPos + 1), true) ?: [];
                    if (isset($paList[strtolower($user['email'])])) {
                        $isAuthorized = true;
                        $authReason = 'panel_access';
                    }
                }
            }
        }
    }

    if (!$isAuthorized) {
        jsonResponse(['success' => false, 'error' => 'Vous n\'etes pas autorise a editer cette fiche.'], 403);
    }

    // === VALIDATION URLS ===
    $platforms = [
        'facebook'  => ['facebook.com', 'fb.com', 'm.facebook.com'],
        'tiktok'    => ['tiktok.com'],
        'instagram' => ['instagram.com'],
        'youtube'   => ['youtube.com', 'youtu.be'],
        'twitter'   => ['twitter.com', 'x.com'],
    ];
    // Tous les domaines acceptes pour le champ _embed (preview iframe)
    $embedDomains = ['youtube.com','youtu.be','tiktok.com','instagram.com','twitter.com','x.com','facebook.com','fb.com'];
    $entry = [];
    foreach ($platforms as $key => $domains) {
        $url = trim((string)($body[$key] ?? ''));
        if ($url === '') continue;
        if (mb_strlen($url) > 500) continue;
        if (!preg_match('#^https?://#i', $url)) continue;
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) continue;
        $host = strtolower($host);
        $ok = false;
        foreach ($domains as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) { $ok = true; break; }
        }
        if (!$ok) continue;
        $entry[$key] = $url;
    }
    // Champ _embed : URL d'un contenu social (post/video) pour preview iframe
    $embedUrl = trim((string)($body['_embed'] ?? ''));
    if ($embedUrl !== '' && mb_strlen($embedUrl) <= 500 && preg_match('#^https?://#i', $embedUrl)) {
        $eHost = strtolower(parse_url($embedUrl, PHP_URL_HOST) ?: '');
        $eOk = false;
        foreach ($embedDomains as $d) {
            if ($eHost === $d || str_ends_with($eHost, '.' . $d)) { $eOk = true; break; }
        }
        if ($eOk) $entry['_embed'] = $embedUrl;
    }

    // === SAVE ===
    $all = bk_load_all_socials($socFile);
    if (!empty($entry)) {
        $all[$id] = $entry;
    } else {
        unset($all[$id]);
    }
    if (!bk_save_all_socials($socFile, $all)) {
        jsonResponse(['success' => false, 'error' => 'Erreur d\'ecriture'], 500);
    }

    jsonResponse([
        'success' => true,
        'socials' => $entry,
        'auth'    => $authReason,
    ]);
}

jsonResponse(['success' => false, 'error' => 'Methode non supportee'], 405);
