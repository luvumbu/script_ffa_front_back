<?php
/**
 * index.php — Interface complete pour explorer toutes les donnees
 *
 * Usage : http://localhost/BK/index.php
 */

$BASE_API = "https://bokonzi.com/api";
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/ip_logger.php';
logIp();

// === ANTI-SCRAPING : 20 pages/jour max par IP (sauf connectes + whitelist) ===
(function() {
    // Skip si utilisateur connecte (Google ou super admin)
    if (!empty($_COOKIE['bk_token']) || !empty($_COOKIE['bk_sa_token'])) return;

    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = trim(explode(',', $ip)[0]);
    if ($ip === '') return;

    // Whitelist Google + Hostinger + localhost + bots/API
    $wl = ['66.249.','66.102.','64.233.','72.14.','74.125.','209.85.','216.239.','35.','34.','153.92.','31.170.','185.201.','127.0.0.1','::1'];
    foreach ($wl as $p) { if (strpos($ip, $p) === 0) return; }
    // Bypass pour requetes API/curl/bots (pas de UA navigateur)
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || strpos($ua, 'curl') !== false || strpos($ua, 'bot') !== false || strpos($ua, 'Bot') !== false) return;

    // Compteur journalier par IP
    $file = __DIR__ . '/logs/.page_limits.php';
    $data = [];
    $today = date('Y-m-d');
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $pos = strpos($raw, "\n");
        if ($pos !== false) $data = json_decode(substr($raw, $pos + 1), true) ?: [];
    }
    // Nettoyer les jours passes
    if (($data['_date'] ?? '') !== $today) $data = ['_date' => $today];

    $count = ($data[$ip] ?? 0) + 1;
    $data[$ip] = $count;
    @file_put_contents($file, "<?php die('Acces interdit'); ?>\n" . json_encode($data));

    if ($count > 10) {
        // Rediriger vers login Google
        header('Location: ' . (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ? '/BK' : '') . '/login.php?limit=1');
        exit;
    }
})();

function dateFR($d) {
    if (!$d || $d === '-') return '-';
    if (str_starts_with($d, '0000')) return '-';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $d;
}

function highestNiveau($niveaux) {
    $order = ['IE'=>100,'IR'=>99];
    foreach (['N'=>90,'R'=>80,'D'=>70] as $p=>$b)
        for ($i=1;$i<=8;$i++) $order[$p.$i] = $b - $i;
    $best = null; $bestS = -1;
    foreach ($niveaux as $n) {
        $s = $order[trim($n)] ?? 0;
        if ($s > $bestS) { $bestS = $s; $best = trim($n); }
    }
    return $best;
}

function nivBadgeHtml($code) {
    if (!$code) return '-';
    $nc = $code[0] ?? '';
    if ($nc === 'N') { $bg = '#e11d4820'; $bc = '#e11d48'; $tc = '#fb7185'; }
    elseif ($nc === 'I') { $bg = '#c026d320'; $bc = '#c026d3'; $tc = '#e879f9'; }
    elseif ($nc === 'R') { $bg = '#0891b220'; $bc = '#0891b2'; $tc = '#22d3ee'; }
    else { $bg = '#f9731620'; $bc = '#f97316'; $tc = '#fb923c'; }
    return '<span style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;margin:1px;background:'.$bg.';border:1px solid '.$bc.'40;color:'.$tc.';">'.htmlspecialchars($code).'</span>';
}

/**
 * Appel API : lit le cache local JSON si disponible,
 * sinon fallback HTTP.
 */
function apiCall($url) {
    $cacheDir = __DIR__ . '/cache';

    // Tenter de lire le cache local pour eviter l'appel HTTP
    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '';
    $query = $parsed['query'] ?? '';

    if (preg_match('#/api/([a-zA-Z0-9_]+)\.php#', $path, $m)) {
        $apiName = $m[1];
        $params = [];
        if ($query) parse_str($query, $params);

        $cacheFile = null;
        if ($apiName === 'stats') {
            $detail = ($params['detail'] ?? '') === '1';
            $top = (int)($params['top'] ?? 50);
            $cacheFile = $cacheDir . '/stats' . ($detail ? '_detail_' . $top : '_base') . '.json';
        } elseif ($apiName === 'epreuve_stats') {
            $nom = $params['nom'] ?? '';
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '50';
            $sx = $params['sexe'] ?? '';
            $cat = $params['categorie'] ?? '';
            $cacheFile = $cacheDir . '/ep_' . md5($nom . '_' . $pg . '_' . $lim . '_' . $sx . '_' . $cat) . '.json';
        } elseif ($apiName === 'athlete') {
            $id = $params['id'] ?? '';
            $idAth = $params['id_athlete'] ?? '';
            $cacheFile = $cacheDir . '/athlete_' . md5($id . '_' . $idAth) . '.json';
        } elseif ($apiName === 'club_stats') {
            $id = $params['id'] ?? '0';
            $nom = $params['nom'] ?? '';
            $an = $params['annee'] ?? '0';
            $ep = $params['ep'] ?? '1';
            $rp = $params['rp'] ?? '1';
            $cacheFile = $cacheDir . '/clubstats_' . md5($id . '_' . $nom . '_' . $an . '_' . $ep . '_' . $rp) . '.json';
        } elseif ($apiName === 'ville_stats') {
            $nom = $params['nom'] ?? '';
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '30';
            $niv = $params['niv'] ?? '';
            $nat = $params['nat'] ?? '';
            $ans = $params['ans'] ?? '';
            $cacheFile = $cacheDir . '/villestats_' . md5($nom . '_' . $pg . '_' . $lim . '_' . $niv . '_' . $nat . '_' . $ans) . '.json';
        } elseif ($apiName === 'clubs') {
            $nom = $params['nom'] ?? '';
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '50';
            $ha = isset($params['has_athletes']) && $params['has_athletes'] == '1' ? 1 : 0;
            $ma = (int)($params['max_athletes'] ?? 0);
            $cacheFile = $cacheDir . '/clubs_' . md5($nom . '_' . $pg . '_' . $lim . '_' . $ha . '_' . $ma) . '.json';
        } elseif ($apiName === 'epreuves') {
            $nom = $params['nom'] ?? '';
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '50';
            $ha = isset($params['has_athletes']) && $params['has_athletes'] == '1' ? 1 : 0;
            $nl = isset($params['no_limit']) && $params['no_limit'] == '1' ? 1 : 0;
            $cacheFile = $cacheDir . '/epreuves_' . md5($nom . '_' . $pg . '_' . $lim . '_' . $ha . '_' . $nl) . '.json';
        } elseif ($apiName === 'villes') {
            $nom = $params['nom'] ?? '';
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '50';
            $ha = isset($params['has_athletes']) && $params['has_athletes'] == '1' ? 1 : 0;
            $cacheFile = $cacheDir . '/villes_' . md5($nom . '_' . $pg . '_' . $lim . '_' . $ha) . '.json';
        } elseif ($apiName === 'liste') {
            $pg = $params['page'] ?? '1';
            $lim = $params['limit'] ?? '50';
            $ord = $params['ordre'] ?? 'nom';
            $cacheFile = $cacheDir . '/liste_' . md5($pg . '_' . $lim . '_' . $ord) . '.json';
        } elseif ($apiName === 'search') {
            $cacheFile = $cacheDir . '/search_' . md5($query) . '.json';
        }

        if ($cacheFile && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $json = @file_get_contents($cacheFile);
            if ($json !== false) {
                $data = json_decode($json, true);
                if ($data) return $data;
            }
        }
    }

    // Fallback : appel HTTP
    $ctx = stream_context_create(['http' => ['timeout' => 30]]);
    $json = @file_get_contents($url, false, $ctx);
    if (!$json) return null;
    return json_decode($json, true);
}

$page    = $_GET['page'] ?? 'accueil';
$id      = $_GET['id'] ?? null;
$p       = max(1, (int)($_GET['p'] ?? 1));

// === SEO dynamique selon la page ===
$seoTitle = 'Bokonzi — Base de données Athlétisme français';
$seoDesc  = 'Bokonzi — Base de données complète d\'athlétisme français : athlètes, clubs, épreuves, records, classements.';

if ($page === 'clubs') {
    $openClubSeo = $_GET['open'] ?? '';
    if ($openClubSeo) {
        $seoTitle = htmlspecialchars($openClubSeo) . ' — Club Athlétisme | Bokonzi';
        $seoDesc  = 'Fiche du club ' . $openClubSeo . ' : athlètes, épreuves, records, nationalités, statistiques détaillées sur Bokonzi.';
    } else {
        $seoTitle = 'Clubs d\'athlétisme — Bokonzi';
        $seoDesc  = 'Liste complète des clubs d\'athlétisme français : effectifs, niveaux, statistiques détaillées.';
    }
} elseif ($page === 'epreuves') {
    $nomEp = $_GET['nom'] ?? '';
    if ($nomEp) {
        $seoTitle = htmlspecialchars($nomEp) . ' — Épreuve Athlétisme | Bokonzi';
        $seoDesc  = 'Statistiques de l\'épreuve ' . $nomEp . ' : classement, records, performances, athlètes sur Bokonzi.';
    } else {
        $seoTitle = 'Épreuves d\'athlétisme — Bokonzi';
        $seoDesc  = 'Toutes les épreuves d\'athlétisme : sprint, demi-fond, fond, sauts, lancers, épreuves combinées.';
    }
} elseif ($page === 'villes') {
    $nomVille = $_GET['open'] ?? ($_GET['nom'] ?? '');
    if ($nomVille) {
        $seoTitle = htmlspecialchars($nomVille) . ' — Ville Athlétisme | Bokonzi';
        $seoDesc  = 'Athlétisme à ' . $nomVille . ' : athlètes, compétitions, records, clubs sur Bokonzi.';
    } else {
        $seoTitle = 'Villes — Athlétisme France | Bokonzi';
        $seoDesc  = 'Toutes les villes d\'athlétisme en France : compétitions, clubs, athlètes par ville.';
    }
} elseif ($page === 'recherche') {
    $_rParts = [];
    if (!empty($_GET['club'])) $_rParts[] = htmlspecialchars($_GET['club']);
    if (!empty($_GET['nom'])) $_rParts[] = htmlspecialchars($_GET['nom']);
    if (!empty($_GET['epreuve'])) $_rParts[] = htmlspecialchars($_GET['epreuve']);
    if (!empty($_GET['nationalite'])) $_rParts[] = strtoupper($_GET['nationalite']);
    if (!empty($_GET['sexe'])) $_rParts[] = ($_GET['sexe'] === 'M' ? 'Hommes' : 'Femmes');
    if (!empty($_GET['categorie'])) $_rParts[] = htmlspecialchars($_GET['categorie']);
    $seoTitle = !empty($_rParts) ? implode(' · ', $_rParts) . ' — Bokonzi' : 'Recherche athlètes — Bokonzi';
    $seoDesc  = 'Recherche avancée d\'athlètes : filtres par épreuve, club, ville, performance, catégorie.';
} elseif ($page === 'athletes') {
    $seoTitle = 'Tous les athlètes — Bokonzi';
    $seoDesc  = 'Liste complète des athlètes français d\'athlétisme avec records, clubs et statistiques.';
} elseif ($page === 'profil' && $id) {
    $_profNom = '';
    $_profRes = $conn->query("SELECT nom_complet_athlete, categorie_athlete, sexe_athlete FROM athletes WHERE athlete_id_externe = " . intval($id) . " LIMIT 1");
    if ($_profRes && $_profRow = $_profRes->fetch_assoc()) {
        $_profNom = $_profRow['nom_complet_athlete'];
        $seoTitle = htmlspecialchars($_profNom) . ' — Athlète | Bokonzi';
        $seoDesc  = 'Fiche de ' . $_profNom . ' (' . $_profRow['sexe_athlete'] . ', ' . $_profRow['categorie_athlete'] . ') : records, progressions, résultats, clubs, médailles sur Bokonzi.';
    } else {
        $seoTitle = 'Profil athlète — Bokonzi';
        $seoDesc  = 'Fiche complète de l\'athlète : records, progressions, résultats, clubs, médailles.';
        $seoNoIndex = true;
    }
} elseif ($page === 'comparer') {
    $seoTitle = 'Comparateur athlètes & clubs — Bokonzi';
    $seoDesc  = 'Comparez visuellement les performances d\'athlètes et clubs d\'athlétisme avec graphiques interactifs.';
    $seoNoIndex = true;
} elseif ($page === 'espace') {
    $seoTitle = 'Mon Espace — Bokonzi';
    $seoDesc  = 'Gérez vos athlètes et clubs suivis, consultez votre historique de recherches.';
    $seoNoIndex = true;
} elseif ($page === 'tuto') {
    $seoTitle = 'Tutoriel — Comment utiliser Bokonzi';
    $seoDesc  = 'Guide interactif étape par étape pour explorer les données d\'athlétisme sur Bokonzi.';
    $seoNoIndex = true;
} elseif ($page === 'accueil') {
    $seoTitle = 'Bokonzi — Base de données Athlétisme français';
    $seoDesc  = 'Statistiques globales, top athlètes, top clubs, répartitions par catégorie et nationalité.';
}

// URL canonique
$_canonBase = 'https://bokonzi.com';
if ($page === 'accueil') {
    $seoCanonical = $_canonBase . '/';
} elseif ($page === 'profil' && $id) {
    $seoCanonical = $_canonBase . '/index.php?page=profil&id=' . intval($id);
} else {
    $seoCanonical = $_canonBase . '/index.php?page=' . urlencode($page);
    if ($page === 'clubs' && !empty($_GET['open'])) $seoCanonical .= '&open=' . urlencode($_GET['open']);
    if ($page === 'epreuves' && !empty($_GET['nom'])) $seoCanonical .= '&nom=' . urlencode($_GET['nom']);
    if ($page === 'villes' && !empty($_GET['open'])) $seoCanonical .= '&open=' . urlencode($_GET['open']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-KPNTVXDF');</script>
    <!-- End Google Tag Manager -->
    <!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7899923856846249"
     crossorigin="anonymous"></script>
    <meta name="google-adsense-account" content="ca-pub-7899923856846249">
    <title><?= $seoTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($seoDesc) ?>">
<?php if (!empty($seoNoIndex)): ?>
    <meta name="robots" content="noindex, follow">
<?php endif; ?>
    <link rel="canonical" href="<?= htmlspecialchars($seoCanonical) ?>">
    <meta property="og:title" content="<?= $seoTitle ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($seoCanonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Bokonzi">
    <meta property="og:image" content="<?= $_canonBase ?>/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="fr_FR">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $seoTitle ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta name="twitter:image" content="<?= $_canonBase ?>/og-image.png">
    <meta name="theme-color" content="#0d1117">
    <link rel="icon" type="image/svg+xml" href="<?= $_canonBase ?>/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $_canonBase ?>/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= $_canonBase ?>/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $_canonBase ?>/apple-touch-icon.png">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "Bokonzi",
        "url": "https://bokonzi.com",
        "description": "Base de données complète d'athlétisme français : athlètes, clubs, épreuves, records, classements.",
        "inLanguage": "fr-FR",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "https://bokonzi.com/index.php?page=recherche&nom={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SportsOrganization",
        "name": "Bokonzi",
        "url": "https://bokonzi.com",
        "logo": "https://bokonzi.com/og-image.png",
        "description": "Base de données complète de l'athlétisme français : athlètes, clubs, épreuves, records, classements et statistiques.",
        "sport": "Athlétisme",
        "areaServed": {
            "@type": "Country",
            "name": "France"
        },
        "knowsLanguage": "fr",
        "foundingDate": "2024"
    }
    </script>
<?php
// === BreadcrumbList JSON-LD dynamique ===
$_bcItems = [['name' => 'Accueil', 'url' => $_canonBase . '/']];
if ($page === 'athletes') {
    $_bcItems[] = ['name' => 'Athlètes'];
} elseif ($page === 'recherche') {
    $_bcItems[] = ['name' => 'Recherche'];
} elseif ($page === 'clubs') {
    $_bcItems[] = ['name' => 'Clubs', 'url' => $_canonBase . '/index.php?page=clubs'];
    if (!empty($_GET['open'])) $_bcItems[] = ['name' => htmlspecialchars($_GET['open'])];
} elseif ($page === 'epreuves') {
    $_bcItems[] = ['name' => 'Épreuves', 'url' => $_canonBase . '/index.php?page=epreuves'];
    if (!empty($_GET['nom'])) $_bcItems[] = ['name' => htmlspecialchars($_GET['nom'])];
} elseif ($page === 'villes') {
    $_bcItems[] = ['name' => 'Villes', 'url' => $_canonBase . '/index.php?page=villes'];
    if (!empty($_GET['open'])) $_bcItems[] = ['name' => htmlspecialchars($_GET['open'])];
} elseif ($page === 'profil' && $id) {
    $_bcItems[] = ['name' => 'Athlètes', 'url' => $_canonBase . '/index.php?page=athletes'];
    $_bcItems[] = ['name' => !empty($_profNom) ? htmlspecialchars($_profNom) : 'Profil athlète'];
}
if (count($_bcItems) > 1):
    $_bcList = [];
    foreach ($_bcItems as $_pos => $_bci) {
        $_item = ['@type' => 'ListItem', 'position' => $_pos + 1, 'name' => $_bci['name']];
        if (isset($_bci['url'])) $_item['item'] = $_bci['url'];
        $_bcList[] = $_item;
    }
?>
    <script type="application/ld+json">
    <?= json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $_bcList], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>

    </script>
<?php endif; ?>
    <link rel="stylesheet" href="dashboard.css">
    <style>.qr-share{text-align:center;padding:20px;margin-top:20px;border-top:1px solid #1a2540}.qr-share img{border-radius:8px;background:#fff;padding:6px}.qr-share .qr-label{color:#5a6580;font-size:12px;margin-top:8px}</style>
    <script>function bkQR(url){return '<div class="qr-share"><img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data='+encodeURIComponent(url)+'" alt="QR Code" width="120" height="120"><div class="qr-label">Scannez pour partager</div></div>';}</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
    (function(){
        try { if (localStorage.getItem('bk_tuto_seen')) return; } catch(e) { return; }
        var p = new URLSearchParams(window.location.search);
        var pg = p.get('page') || 'accueil';
        if (pg === 'tuto') return;
        if (pg === 'profil' && p.get('id')) return;
        if (pg === 'recherche' && (p.get('club') || p.get('epreuve') || p.get('nom'))) return;
        if (pg === 'villes' && p.get('open')) return;
        if (pg === 'clubs' && p.get('open')) return;
        if (pg === 'epreuves' && p.get('nom')) return;
        window.location.replace(window.location.pathname + '?page=tuto');
    })();
    </script>
    <style>
    /* PANIER COMPARAISON FLOTTANT */
    .cmp-basket {
        position: fixed; bottom: 24px; right: 24px; z-index: 9998;
        background: linear-gradient(135deg, #1a2035, #12182a);
        border: 1px solid #f59e0b60; border-radius: 14px;
        padding: 12px 18px; display: none; align-items: center; gap: 12px;
        box-shadow: 0 8px 32px #00000080; backdrop-filter: blur(12px);
        font-family: 'Segoe UI', system-ui, sans-serif;
        animation: basketSlideIn 0.3s ease;
    }
    @keyframes basketSlideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .cmp-basket .basket-counts { display: flex; gap: 10px; }
    .cmp-basket .basket-count {
        display: flex; align-items: center; gap: 5px;
        font-size: 13px; font-weight: 600; color: #c9d1d9;
    }
    .cmp-basket .basket-count .num {
        background: #f59e0b; color: #000; font-size: 12px; font-weight: 800;
        width: 22px; height: 22px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
    .cmp-basket .basket-go {
        padding: 8px 16px; background: linear-gradient(135deg, #f59e0b, #ec4899);
        border: none; border-radius: 8px; color: #fff; font-size: 12px;
        font-weight: 700; cursor: pointer; text-decoration: none; white-space: nowrap;
    }
    .cmp-basket .basket-go:hover { opacity: 0.9; }
    .cmp-basket .basket-clear {
        background: none; border: none; color: #ff6b6b; font-size: 18px;
        cursor: pointer; padding: 0 4px; line-height: 1;
    }
    /* Bouton + comparaison */
    .btn-cmp-add {
        display: inline-flex; align-items: center; justify-content: center;
        width: 24px; height: 24px; border-radius: 6px;
        background: #f59e0b18; border: 1px solid #f59e0b40; color: #f59e0b;
        font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.15s;
        text-decoration: none; line-height: 1; vertical-align: middle;
    }
    .btn-cmp-add:hover { background: #f59e0b33; border-color: #f59e0b; transform: scale(1.15); }
    .btn-cmp-add.added { background: #34d39920; border-color: #34d39960; color: #34d399; cursor: default; }
    /* Bouton Suivre athlete */
    .btn-follow {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 6px 16px; border-radius: 8px;
        background: linear-gradient(135deg, #ec4899, #8b5cf6); border: none; color: #fff;
        font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s;
        text-decoration: none; line-height: 1.4; vertical-align: middle;
        box-shadow: 0 2px 8px #ec489940;
    }
    .btn-follow:hover { transform: scale(1.08); box-shadow: 0 4px 16px #ec489960; }
    .btn-follow.following { background: linear-gradient(135deg, #34d399, #059669); box-shadow: 0 2px 8px #34d39940; }
    .btn-follow .follow-count { font-size: 11px; color: #8b949e; margin-left: 2px; }
    /* Bouton PDF */
    .btn-pdf {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 6px 16px; border-radius: 8px;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8); border: none; color: #fff;
        font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s;
        text-decoration: none; line-height: 1.4; vertical-align: middle;
        box-shadow: 0 2px 8px #3b82f640;
    }
    .btn-pdf:hover { transform: scale(1.08); box-shadow: 0 4px 16px #3b82f660; }
    /* Banniere newsletter */
    .newsletter-bar {
        display: none; position: fixed; bottom: 0; left: 0; width: 100%; z-index: 9998;
        background: linear-gradient(135deg, #161b22 0%, #1c2333 100%);
        border-top: 1px solid #30363d; padding: 16px 24px;
        animation: slideUpBar 0.4s ease;
    }
    .newsletter-bar.active { display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; }
    .newsletter-bar .nl-text { color: #c9d1d9; font-size: 14px; font-weight: 600; }
    .newsletter-bar .nl-sub { color: #8b949e; font-size: 12px; }
    .newsletter-bar input {
        padding: 10px 16px; background: #0d1117; border: 1px solid #1e2a3a; border-radius: 8px;
        color: #c9d1d9; font-size: 14px; width: 260px; max-width: 40vw;
    }
    .newsletter-bar input:focus { outline: none; border-color: #f59e0b; }
    .newsletter-bar .nl-btn {
        padding: 10px 20px; background: linear-gradient(135deg, #f59e0b, #ec4899); border: none;
        border-radius: 8px; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer;
    }
    .newsletter-bar .nl-btn:hover { transform: scale(1.05); }
    .newsletter-bar .nl-close {
        background: none; border: none; color: #484f58; font-size: 20px; cursor: pointer; margin-left: 8px;
    }
    .newsletter-bar .nl-close:hover { color: #f0f6fc; }
    .newsletter-bar .nl-ok { color: #34d399; font-weight: 700; font-size: 14px; }
    @keyframes slideUpBar { from { transform: translateY(100%); } to { transform: translateY(0); } }
    /* Modal follow */
    .follow-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center;
    }
    .follow-overlay.active { display: flex; }
    .follow-modal {
        background: #161b22; border: 1px solid #30363d; border-radius: 12px;
        padding: 32px; max-width: 400px; width: 90%; text-align: center;
        animation: fadeInPanel 0.25s ease;
    }
    .follow-modal h3 { color: #f0f6fc; font-size: 18px; margin-bottom: 8px; }
    .follow-modal p { color: #8b949e; font-size: 13px; margin-bottom: 20px; line-height: 1.5; }
    .follow-modal input {
        width: 100%; padding: 12px 16px; background: #0d1117; border: 1px solid #1e2a3a;
        border-radius: 8px; color: #c9d1d9; font-size: 15px; margin-bottom: 12px;
        transition: border-color 0.2s;
    }
    .follow-modal input:focus { outline: none; border-color: #a29bfe; box-shadow: 0 0 0 3px #a29bfe22; }
    .follow-modal .btn-confirm {
        width: 100%; padding: 12px; background: #a29bfe; color: #0d1117; border: none;
        border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s;
    }
    .follow-modal .btn-confirm:hover { background: #8b7cf6; }
    .follow-modal .btn-close {
        position: absolute; top: 12px; right: 16px; background: none; border: none;
        color: #8b949e; font-size: 20px; cursor: pointer;
    }
    .follow-modal .btn-close:hover { color: #f0f6fc; }
    .btn-cmp-add-club {
        background: #8b5cf618; border-color: #8b5cf640; color: #8b5cf6;
    }
    .btn-cmp-add-club:hover { background: #8b5cf633; border-color: #8b5cf6; }
    .btn-cmp-add-club.added { background: #34d39920; border-color: #34d39960; color: #34d399; }
    /* Bouton ignorer club */
    .btn-cmp-ignore {
        display: inline-flex; align-items: center; justify-content: center;
        width: 24px; height: 24px; border-radius: 6px;
        background: #ef444418; border: 1px solid #ef444440; color: #ef4444;
        font-size: 14px; cursor: pointer; transition: all 0.15s;
        line-height: 1; vertical-align: middle;
    }
    .btn-cmp-ignore:hover { background: #ef444433; border-color: #ef4444; transform: scale(1.15); }
    .btn-cmp-ignore.ignored { background: #34d39920; border-color: #34d39960; color: #34d399; }
    .ignored-panel {
        background: linear-gradient(135deg, #1a1015 0%, #1a1520 100%);
        border: 1px solid #ef444430; border-radius: 12px;
        padding: 16px 20px; margin-bottom: 16px;
    }
    .ignored-panel h3 { color: #ef4444; font-size: 14px; margin: 0 0 10px; display: flex; align-items: center; gap: 8px; }
    .ignored-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 12px; background: #ef444412; border: 1px solid #ef444425;
        border-radius: 8px; color: #fca5a5; font-size: 12px; font-weight: 600; margin: 3px;
    }
    .ignored-chip .restore {
        cursor: pointer; color: #34d399; font-weight: 700; margin-left: 4px;
        font-size: 11px; text-decoration: underline;
    }
    .ignored-chip .restore:hover { color: #6ee7b7; }
    .club-detail-panel {
        display: none; background: linear-gradient(135deg, #0d1520 0%, #141c2e 100%);
        border: 1px solid #6c5ce730; border-radius: 12px; padding: 24px; margin-bottom: 20px;
        animation: fadeInPanel 0.25s ease;
    }
    @keyframes fadeInPanel { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .club-detail-panel.active { display: block; }
    .club-detail-header {
        display: flex; align-items: center; gap: 16px; margin-bottom: 16px;
        flex-wrap: wrap;
    }
    .club-detail-header h2 { color: #a29bfe; font-size: 22px; margin: 0; flex: 1; }
    .club-detail-header .meta-info { color: #5a6580; font-size: 13px; }
    .club-detail-header .btn-close-detail {
        padding: 6px 16px; border-radius: 8px; border: 1px solid #1e2a3a;
        background: #0d1117; color: #c9d1d9; font-size: 13px; cursor: pointer;
    }
    .club-detail-header .btn-close-detail:hover { border-color: #ef4444; color: #ef4444; }
    .club-detail-tabs {
        display: flex; gap: 4px; margin-bottom: 16px; border-bottom: 1px solid #1e2a3a; padding-bottom: 8px;
    }
    .club-detail-tab {
        padding: 8px 18px; border-radius: 8px 8px 0 0; border: 1px solid transparent;
        background: transparent; color: #5a6580; font-size: 13px; font-weight: 600; cursor: pointer;
        transition: all 0.2s;
    }
    .club-detail-tab:hover { color: #c9d1d9; }
    .club-detail-tab.active { background: #1a2540; color: #a29bfe; border-color: #1a2540; }
    .club-detail-content { min-height: 100px; }
    .club-detail-content .loading-msg { color: #5a6580; text-align: center; padding: 30px; }
    .club-detail-grid {
        display: flex; flex-wrap: wrap; gap: 8px;
    }
    .club-detail-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 14px; background: #1e2a3a; border: 1px solid #2d3a4a;
        border-radius: 20px; color: #c9d1d9; font-size: 13px; font-weight: 500;
    }
    .club-detail-chip .chip-count {
        background: #6c5ce722; color: #a29bfe; font-size: 11px; font-weight: 700;
        padding: 2px 7px; border-radius: 10px;
    }
    .club-detail-content table { width: 100%; border-collapse: collapse; }
    .club-detail-content th {
        text-align: left; padding: 8px 12px; color: #8b949e; font-size: 12px;
        text-transform: uppercase; border-bottom: 1px solid #1e2a3a;
    }
    .club-detail-content td {
        padding: 10px 12px; border-bottom: 1px solid #1e2a3a08; font-size: 14px;
    }
    .club-detail-content tr:hover { background: #ffffff06; }
    .club-detail-content .perf-val { color: #55efc4; font-weight: 600; font-family: 'Courier New', monospace; }
    @media (max-width: 768px) {
        .hide-mobile { display: none !important; }
    }
    </style>
</head>
<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KPNTVXDF"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<!-- PANIER FLOTTANT -->
<div class="cmp-basket" id="cmpBasket">
    <div class="basket-counts">
        <span class="basket-count" id="basketAthCount" style="display:none;"><span class="num" id="basketAthNum">0</span> athletes</span>
        <span class="basket-count" id="basketClubCount" style="display:none;"><span class="num" id="basketClubNum" style="background:#8b5cf6;">0</span> clubs</span>
    </div>
    <a href="?page=comparer" class="basket-go">Comparer</a>
    <button class="basket-clear" onclick="clearBasket()" title="Vider">&times;</button>
</div>
<script>
// ═══════════ PANIER COMPARAISON (localStorage) ═══════════
function getBasketAthletes() {
    try { return JSON.parse(localStorage.getItem('bk_cmp_athletes') || '[]'); } catch(e) { return []; }
}
function getBasketClubs() {
    try { return JSON.parse(localStorage.getItem('bk_cmp_clubs') || '[]'); } catch(e) { return []; }
}
function saveBasketAthletes(list) { localStorage.setItem('bk_cmp_athletes', JSON.stringify(list)); updateBasketBadge(); }
function saveBasketClubs(list) { localStorage.setItem('bk_cmp_clubs', JSON.stringify(list)); updateBasketBadge(); }

function addAthleteToBasket(id, name) {
    var list = getBasketAthletes();
    if (list.find(function(a) { return a.id === id; })) return false;
    if (list.length >= 6) { alert('Maximum 6 athlètes dans le comparateur'); return false; }
    list.push({ id: id, name: name });
    saveBasketAthletes(list);
    return true;
}
function removeAthleteFromBasket(id) {
    saveBasketAthletes(getBasketAthletes().filter(function(a) { return a.id !== id; }));
}
function addClubToBasket(id, name) {
    var list = getBasketClubs();
    if (list.find(function(c) { return c.id === id; })) return false;
    list.push({ id: id, name: name });
    saveBasketClubs(list);
    return true;
}
function removeClubFromBasket(id) {
    saveBasketClubs(getBasketClubs().filter(function(c) { return c.id !== id; }));
}
function clearBasket() {
    localStorage.removeItem('bk_cmp_athletes');
    localStorage.removeItem('bk_cmp_clubs');
    updateBasketBadge();
    updateAllCmpButtons();
}
function isAthleteInBasket(id) {
    return !!getBasketAthletes().find(function(a) { return a.id === id; });
}
function isClubInBasket(id) {
    return !!getBasketClubs().find(function(c) { return c.id === id; });
}

// ═══════════ CLUBS IGNORES (localStorage) ═══════════
function getIgnoredClubs() {
    try { return JSON.parse(localStorage.getItem('bk_ignored_clubs') || '[]'); } catch(e) { return []; }
}
function saveIgnoredClubs(list) { localStorage.setItem('bk_ignored_clubs', JSON.stringify(list)); }
function isClubIgnoredById(id) {
    return !!getIgnoredClubs().find(function(c) { return c.id === id; });
}
function isClubIgnoredByName(name) {
    var n = name.toLowerCase();
    return !!getIgnoredClubs().find(function(c) { return c.name.toLowerCase() === n; });
}

function updateBasketBadge() {
    var ath = getBasketAthletes();
    var clubs = getBasketClubs();
    var basket = document.getElementById('cmpBasket');
    var total = ath.length + clubs.length;
    basket.style.display = total > 0 ? 'flex' : 'none';
    var athEl = document.getElementById('basketAthCount');
    var clubEl = document.getElementById('basketClubCount');
    if (ath.length > 0) { athEl.style.display = 'flex'; document.getElementById('basketAthNum').textContent = ath.length; }
    else { athEl.style.display = 'none'; }
    if (clubs.length > 0) { clubEl.style.display = 'flex'; document.getElementById('basketClubNum').textContent = clubs.length; }
    else { clubEl.style.display = 'none'; }
}

function toggleAthleteBasket(btn, id, name) {
    if (isAthleteInBasket(id)) {
        removeAthleteFromBasket(id);
        btn.classList.remove('added');
        btn.textContent = '+';
        btn.title = 'Ajouter au comparateur';
    } else {
        if (!addAthleteToBasket(id, name)) return;
        btn.classList.add('added');
        btn.textContent = '\u2713';
        btn.title = 'Dans le comparateur';
    }
}
function toggleClubBasket(btn, id, name) {
    if (isClubInBasket(id)) {
        removeClubFromBasket(id);
        btn.classList.remove('added');
        btn.textContent = '+';
    } else {
        addClubToBasket(id, name);
        btn.classList.add('added');
        btn.textContent = '\u2713';
    }
}

function updateAllCmpButtons() {
    document.querySelectorAll('[data-cmp-ath]').forEach(function(btn) {
        var id = parseInt(btn.getAttribute('data-cmp-ath'));
        if (isAthleteInBasket(id)) { btn.classList.add('added'); btn.textContent = '\u2713'; }
        else { btn.classList.remove('added'); btn.textContent = '+'; }
    });
    document.querySelectorAll('[data-cmp-club]').forEach(function(btn) {
        var id = parseInt(btn.getAttribute('data-cmp-club'));
        if (isClubInBasket(id)) { btn.classList.add('added'); btn.textContent = '\u2713'; }
        else { btn.classList.remove('added'); btn.textContent = '+'; }
    });
}

// Init badge au chargement
updateBasketBadge();
document.addEventListener('DOMContentLoaded', updateAllCmpButtons);
</script>
<?php include 'nav.php'; ?>

<nav aria-label="Navigation principale">
    <a href="<?= $_canonBase ?>/" class="logo">Bokonzi</a>
    <a href="<?= $_canonBase ?>/?page=accueil" class="<?= $page === 'accueil' ? 'active' : '' ?>">Accueil</a>
    <a href="<?= $_canonBase ?>/?page=athletes" class="<?= $page === 'athletes' ? 'active' : '' ?>">Athlètes</a>
    <a href="<?= $_canonBase ?>/?page=recherche" class="<?= $page === 'recherche' ? 'active' : '' ?>">Recherche</a>
    <a href="<?= $_canonBase ?>/?page=clubs" class="<?= $page === 'clubs' ? 'active' : '' ?>">Clubs</a>
    <a href="<?= $_canonBase ?>/?page=epreuves" class="<?= $page === 'epreuves' ? 'active' : '' ?>">Épreuves</a>
    <a href="<?= $_canonBase ?>/?page=villes" class="<?= $page === 'villes' ? 'active' : '' ?>">Villes</a>
    <a href="<?= $_canonBase ?>/?page=comparer" class="<?= $page === 'comparer' ? 'active' : '' ?>" style="color:#f59e0b;">Comparer</a>
    <?php if ($navUser): ?><a href="<?= $_canonBase ?>/?page=espace" class="<?= $page === 'espace' ? 'active' : '' ?>" style="color:#a29bfe;">Mon Espace</a><?php endif; ?>
    <a href="<?= $_canonBase ?>/?page=tuto" class="<?= $page === 'tuto' ? 'active' : '' ?>" style="color:#34d399;">Tuto</a>
</nav>

<div class="container">

<?php
// ================================================================
//  ACCUEIL — Stats globales
// ================================================================
if ($page === 'accueil'):
    $data = apiCall("$BASE_API/stats.php");
?>

<h1>Base de Donnees Athletisme Francais — Athletes, Clubs, Records</h1>

<?php if ($data && ($data['success'] ?? false)): ?>

<!-- ======== STAT CARDS ======== -->
<div class="grid">
    <a href="?page=athletes" class="card-link"><div class="card">
        <div class="num"><?= number_format($data['comptages']['athletes']['count'], 0, ',', ' ') ?></div>
        <div class="label">Athlètes</div>
    </div></a>
    <a href="?page=clubs" class="card-link"><div class="card accent-green">
        <div class="num"><?= number_format($data['comptages']['clubs']['count'], 0, ',', ' ') ?></div>
        <div class="label">Clubs</div>
    </div></a>
    <a href="?page=epreuves" class="card-link"><div class="card accent-purple">
        <div class="num"><?= number_format($data['comptages']['epreuves']['count'], 0, ',', ' ') ?></div>
        <div class="label">Épreuves</div>
    </div></a>
    <div class="card accent-amber">
        <div class="num"><?= number_format($data['comptages']['athlete_resultats']['count'], 0, ',', ' ') ?></div>
        <div class="label">Résultats</div>
    </div>
    <div class="card accent-rose">
        <div class="num"><?= number_format($data['comptages']['athlete_records']['count'], 0, ',', ' ') ?></div>
        <div class="label">Records</div>
    </div>
    <a href="?page=villes" class="card-link"><div class="card accent-green">
        <div class="num"><?= number_format($data['comptages']['villes']['count'], 0, ',', ' ') ?></div>
        <div class="label">Villes</div>
    </div></a>
</div>

<!-- ======== TOP 30 ATHLETES ======== -->
<?php if ($detailData && !empty($detailData['top_athletes'])): ?>
<div style="margin-top:24px;margin-bottom:24px;">
    <h2 style="margin:0 0 12px;"><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127939;</span> Top 30 Athl&#232;tes</h2>
    <div class="table-wrap">
    <table class="bk-table"><tr><th style="width:40px;">#</th><th>Athl&#232;te</th><th>Club</th><th>Sexe</th><th>Records</th><th>Podiums</th></tr></table>
    <table class="bk-table">
    <?php foreach (array_slice($detailData['top_athletes'], 0, 30) as $idx => $a): ?>
        <tr>
            <td style="color:#5a6580;"><?= $idx + 1 ?></td>
            <td><a href="?page=profil&id=<?= $a['athlete_id'] ?>" style="color:#a29bfe;text-decoration:none;font-weight:600;"><?= htmlspecialchars($a['nom']) ?></a></td>
            <td style="color:#8b949e;font-size:12px;"><?= htmlspecialchars(rtrim($a['club'] ?? '', '* ')) ?></td>
            <td><span class="badge badge-<?= strtolower($a['sexe'] ?? '') ?>" style="font-size:11px;"><?= htmlspecialchars($a['sexe'] ?? '-') ?></span></td>
            <td><?= ($a['nb_records'] ?? 0) > 0 ? '<span class="badge badge-perf">' . $a['nb_records'] . '</span>' : '-' ?></td>
            <td><?= ($a['nb_podiums'] ?? 0) > 0 ? '<span style="color:#34d399;font-weight:600;">' . $a['nb_podiums'] . '</span>' : '-' ?></td>
        </tr>
    <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th style="width:40px;">#</th><th>Athl&#232;te</th><th>Club</th><th>Sexe</th><th>Records</th><th>Podiums</th></tr></table>
    </div>
</div>
<?php endif; ?>

<!-- ======== GRAPHIQUES LIGNE 1 : Sexe + Categories ======== -->
<div class="charts-row">
    <div class="chart-card">
        <h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">M/F</span> Repartition par sexe</h3>
        <canvas id="chartSexe"></canvas>
    </div>
    <div class="chart-card">
        <h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#128202;</span> Catégories</h3>
        <canvas id="chartCategories"></canvas>
    </div>
</div>

<!-- ======== GRAPHIQUES LIGNE 2 : Top Clubs + Top Epreuves (chargés en AJAX) ======== -->
<div class="charts-row">
    <div class="chart-card">
        <h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#127963;</span> Top 10 Clubs</h3>
        <canvas id="chartClubs"></canvas>
    </div>
    <div class="chart-card">
        <h3><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127939;</span> Top 10 Épreuves</h3>
        <canvas id="chartEpreuves"></canvas>
    </div>
</div>

<!-- ======== TABLEAUX DETAILS (chargés en AJAX) ======== -->
<div id="clubDetailPanelAccueil" class="club-detail-panel">
    <div class="club-detail-header">
        <h2 id="clubDetailNameAccueil"></h2>
        <span class="meta-info" id="clubDetailMetaAccueil"></span>
        <button class="btn-follow btn-follow-club" id="btnFollowClubAccueil" style="display:none;">&#9825; Suivre</button>
        <button onclick="closeClubDetailAccueil()" class="btn-close-detail">&times; Fermer</button>
    </div>
    <div class="club-detail-tabs">
        <button class="club-detail-tab active" data-tab="epreuves" onclick="switchClubTabAccueil('epreuves')">Épreuves</button>
        <button class="club-detail-tab" data-tab="nationalites" onclick="switchClubTabAccueil('nationalites')">Nationalités</button>
        <button class="club-detail-tab" data-tab="stats" onclick="switchClubTabAccueil('stats')">Stats</button>
        <button class="club-detail-tab" data-tab="performances" onclick="switchClubTabAccueil('performances')">Performances</button>
        <button class="club-detail-tab" data-tab="resume" onclick="switchClubTabAccueil('resume')">Resume</button>
    </div>
    <div class="club-search-bar" id="clubSearchBarAccueil" style="display:none;padding:8px 16px;">
        <div style="position:relative;">
            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#5a6580;font-size:14px;">&#128269;</span>
            <input type="text" id="clubSearchInputAccueil" placeholder="Rechercher un athlète dans ce club..." autocomplete="off" style="width:100%;padding:8px 12px 8px 32px;border-radius:8px;border:1px solid #1e2a3a;background:#0d1117;color:#c9d1d9;font-size:13px;outline:none;transition:border-color .2s;">
        </div>
    </div>
    <div id="clubDetailContentAccueil" class="club-detail-content"></div>
    <div id="clubQRAccueil"></div>
</div>

<!-- Top Clubs -->
<div id="accueilClubsWrap" style="margin-bottom:24px;">
    <h2>Top Clubs <span id="accueilClubsCount" style="font-size:13px;color:#5a6580;font-weight:normal;"></span></h2>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Club</th><th>Athlètes</th><th>Records</th><th>Médailles</th><th>Niveaux</th><th></th></tr></table>
    <table class="bk-table"><tbody id="topClubsBody"><tr><td colspan="7" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr></tbody></table>
    <table class="bk-table"><tr><th>#</th><th>Club</th><th>Athlètes</th><th>Records</th><th>Médailles</th><th>Niveaux</th><th></th></tr></table>
    </div>
    <div id="topClubsPag" style="display:flex;justify-content:center;gap:8px;margin-top:12px;"></div>
</div>

<!-- Athlètes -->
<div id="accueilAthletesWrap" style="margin-bottom:24px;">
    <h2>Athlètes <span id="accueilAthletesCount" style="font-size:13px;color:#5a6580;font-weight:normal;"></span></h2>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>Athlète</th><th>Club</th><th>Cat</th><th>NAT</th><th>Médailles</th><th>Podiums</th><th>Sél.</th><th>Records</th><th>Niveaux</th><th></th></tr></table>
    <table class="bk-table"><tbody id="topAthletesBody"><tr><td colspan="10" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr></tbody></table>
    <table class="bk-table"><tr><th>Athlète</th><th>Club</th><th>Cat</th><th>NAT</th><th>Médailles</th><th>Podiums</th><th>Sél.</th><th>Records</th><th>Niveaux</th><th></th></tr></table>
    </div>
    <div id="topAthletesPag" style="display:flex;justify-content:center;gap:8px;margin-top:12px;"></div>
</div>

<!-- Top Villes -->
<div id="accueilVillesWrap" style="margin-bottom:24px;">
    <h2>Top Villes <span id="accueilVillesCount" style="font-size:13px;color:#5a6580;font-weight:normal;"></span></h2>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Ville</th><th>Résultats</th><th>Athlètes</th><th>Niveaux</th></tr></table>
    <table class="bk-table"><tbody id="topVillesBody"><tr><td colspan="5" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr></tbody></table>
    <table class="bk-table"><tr><th>#</th><th>Ville</th><th>Résultats</th><th>Athlètes</th><th>Niveaux</th></tr></table>
    </div>
    <div id="topVillesPag" style="display:flex;justify-content:center;gap:8px;margin-top:12px;"></div>
</div>

<!-- Top Épreuves -->
<div id="accueilEpreuvesWrap" style="margin-bottom:24px;">
    <h2>Top Épreuves <span id="accueilEpreuvesCount" style="font-size:13px;color:#5a6580;font-weight:normal;"></span></h2>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Records</th><th>Athlètes</th><th>Niveaux</th></tr></table>
    <table class="bk-table"><tbody id="topEpreuvesBody"><tr><td colspan="5" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr></tbody></table>
    <table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Records</th><th>Athlètes</th><th>Niveaux</th></tr></table>
    </div>
    <div id="topEpreuvesPag" style="display:flex;justify-content:center;gap:8px;margin-top:12px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    function _esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function _nivBar(np) {
        if (!np) return '-';
        var h = '<div style="display:flex;align-items:center;gap:2px;min-width:120px;" title="D:'+np.D+'% R:'+np.R+'% N:'+np.N+'% I:'+np.I+'% ('+np.total+' rés.)">';
        h += '<div style="display:flex;height:8px;flex:1;border-radius:4px;overflow:hidden;background:#1a2540;">';
        if (np.D > 0) h += '<div style="width:'+np.D+'%;background:#fb923c;"></div>';
        if (np.R > 0) h += '<div style="width:'+np.R+'%;background:#22d3ee;"></div>';
        if (np.N > 0) h += '<div style="width:'+np.N+'%;background:#fb7185;"></div>';
        if (np.I > 0) h += '<div style="width:'+np.I+'%;background:#e879f9;"></div>';
        h += '</div>';
        h += '<div style="display:flex;gap:3px;font-size:9px;white-space:nowrap;">';
        if (np.D > 0) h += '<span style="color:#fb923c;">D'+np.D+'</span>';
        if (np.R > 0) h += '<span style="color:#22d3ee;">R'+np.R+'</span>';
        if (np.N > 0) h += '<span style="color:#fb7185;">N'+np.N+'</span>';
        if (np.I > 0) h += '<span style="color:#e879f9;">I'+np.I+'</span>';
        h += '</div></div>';
        return h;
    }
    function _paginator(items, bodyId, pagId, perPage, renderRow) {
        var pg = 0;
        function render() {
            var start = pg * perPage, end = Math.min(start + perPage, items.length);
            var html = '';
            for (var i = start; i < end; i++) html += renderRow(items[i], i);
            document.getElementById(bodyId).innerHTML = html;
            var totalPages = Math.ceil(items.length / perPage);
            var ph = '';
            if (pg > 0) ph += '<button onclick="window._pg_' + bodyId + '(' + (pg-1) + ')" style="padding:6px 14px;background:#1a2540;border:1px solid #253560;border-radius:6px;color:#d0d7e0;cursor:pointer;">Précédent</button>';
            ph += '<span style="color:#5a6580;font-size:13px;padding:6px 8px;">' + (pg+1) + ' / ' + totalPages + '</span>';
            if (pg < totalPages - 1) ph += '<button onclick="window._pg_' + bodyId + '(' + (pg+1) + ')" style="padding:6px 14px;background:#1a2540;border:1px solid #253560;border-radius:6px;color:#d0d7e0;cursor:pointer;">Suivant</button>';
            document.getElementById(pagId).innerHTML = ph;
        }
        window['_pg_' + bodyId] = function(p) { pg = p; render(); };
        render();
    }

    // ---- Données detail injectées par PHP (cache local ou AJAX fallback) ----
    <?php
    // Lire le cache detail directement en PHP (0 HTTP, 0 MySQL)
    $detailCache = __DIR__ . '/cache/stats_detail_30.json';
    $detailData = null;
    if (file_exists($detailCache) && (time() - filemtime($detailCache)) < 86400) { // 24h
        $detailJson = @file_get_contents($detailCache);
        if ($detailJson) $detailData = json_decode($detailJson, true);
    }
    ?>
    function _buildAccueilTables(d) {
        if (!d || !d.success) return;

        // Top Clubs
        if (d.top_clubs && d.top_clubs.length > 0) {
            document.getElementById('accueilClubsCount').textContent = '(' + d.top_clubs.length + ' clubs)';
            _paginator(d.top_clubs, 'topClubsBody', 'topClubsPag', 10, function(c, i) {
                return '<tr><td>' + (i+1) + '</td>'
                    + '<td><a href="?page=clubs&open=' + encodeURIComponent(c.club) + '" style="color:#a29bfe;text-decoration:none;cursor:pointer;">' + _esc(c.club) + '</a></td>'
                    + '<td>' + (c.nb_athletes||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + (c.nb_records||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + (c.nb_medailles > 0 ? '<span style="color:#fbbf24;font-weight:600;">' + c.nb_medailles + '</span>' : '-') + '</td>'
                    + '<td>' + _nivBar(c.niveaux_pct) + '</td>'
                    + '<td><a href="?page=clubs&open=' + encodeURIComponent(c.club) + '" style="color:#5a6580;font-size:12px;">Détails →</a></td>'
                    + '</tr>';
            });
            var top10c = d.top_clubs.slice(0, 10);
            window._topClubsRaw = top10c.map(function(c) { return {name: c.club, count: c.nb_athletes}; });
            try {
                new Chart(document.getElementById('chartClubs'), {
                    type: 'bar',
                    data: { labels: top10c.map(function(c) { return c.club.substring(0, 20); }), datasets: [{ data: top10c.map(function(c) { return c.nb_athletes; }), backgroundColor: '#a78bfa', borderRadius: 6, barThickness: 22 }] },
                    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
                });
            } catch(e) {}
        }

        // Athlètes (shuffle)
        if (d.top_athletes && d.top_athletes.length > 0) {
            var ath = d.top_athletes;
            for (var i = ath.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = ath[i]; ath[i] = ath[j]; ath[j] = t; }
            document.getElementById('accueilAthletesCount').textContent = '(' + ath.length + ' athlètes)';
            _paginator(ath, 'topAthletesBody', 'topAthletesPag', 10, function(a, i) {
                var medH = a.nb_medailles > 0 ? '<span style="color:#fbbf24;font-weight:600;">' + a.nb_medailles + '</span>' : '-';
                return '<tr>'
                    + '<td><b><a href="?page=profil&id=' + a.athlete_id + '" style="color:#a29bfe;text-decoration:none;">' + _esc(a.nom) + '</a></b></td>'
                    + '<td>' + (a.club ? '<a href="?page=clubs&open=' + encodeURIComponent(a.club) + '" style="font-size:12px;color:#8b949e;text-decoration:none;">' + _esc(a.club).substring(0,25) + '</a>' : '-') + '</td>'
                    + '<td><a href="?page=recherche&categorie=' + encodeURIComponent(a.categorie||'') + '" style="text-decoration:none;"><span class="badge badge-cat">' + _esc(a.categorie||'') + '</span></a></td>'
                    + '<td><a href="?page=recherche&nationalite=' + encodeURIComponent(a.nationalite||'') + '" style="color:#c9d1d9;text-decoration:none;">' + _esc(a.nationalite||'') + '</a></td>'
                    + '<td>' + medH + '</td>'
                    + '<td>' + (a.nb_podiums > 0 ? '<span style="color:#34d399;font-weight:600;">' + a.nb_podiums + '</span>' : '-') + '</td>'
                    + '<td>' + (a.nb_selections > 0 ? '<span style="color:#818cf8;font-weight:600;">' + a.nb_selections + '</span>' : '-') + '</td>'
                    + '<td>' + (a.nb_records > 0 ? '<span class="badge badge-perf">' + a.nb_records + '</span>' : '-') + '</td>'
                    + '<td>' + _nivBar(a.niveaux_pct) + '</td>'
                    + '<td><button class="btn-cmp-add" data-cmp-ath="' + a.athlete_id + '" data-name="' + _esc(a.nom) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">+</button></td>'
                    + '</tr>';
            });
        }

        // Top Villes
        if (d.top_villes && d.top_villes.length > 0) {
            document.getElementById('accueilVillesCount').textContent = '(' + d.top_villes.length + ' villes)';
            _paginator(d.top_villes, 'topVillesBody', 'topVillesPag', 10, function(v, i) {
                return '<tr><td>' + (i+1) + '</td>'
                    + '<td><a href="?page=villes&open=' + encodeURIComponent(v.ville) + '" style="color:#a29bfe;text-decoration:none;cursor:pointer;">' + _esc(v.ville) + '</a></td>'
                    + '<td>' + (v.nb_resultats||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + (v.nb_athletes||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + _nivBar(v.niveaux_pct) + '</td>'
                    + '</tr>';
            });
        }

        // Top Épreuves
        if (d.top_epreuves && d.top_epreuves.length > 0) {
            document.getElementById('accueilEpreuvesCount').textContent = '(' + d.top_epreuves.length + ' épreuves)';
            _paginator(d.top_epreuves, 'topEpreuvesBody', 'topEpreuvesPag', 10, function(e, i) {
                return '<tr><td>' + (i+1) + '</td>'
                    + '<td><a href="?page=recherche&epreuve=' + encodeURIComponent(e.epreuve) + '" style="color:#a29bfe;text-decoration:none;">' + _esc(e.epreuve) + '</a></td>'
                    + '<td>' + (e.nb_records||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + (e.nb_athletes||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + _nivBar(e.niveaux_pct) + '</td>'
                    + '</tr>';
            });
            try {
                var top10e = d.top_epreuves.slice(0, 10);
                new Chart(document.getElementById('chartEpreuves'), {
                    type: 'bar',
                    data: { labels: top10e.map(function(e) { return e.epreuve; }), datasets: [{ label: 'Records', data: top10e.map(function(e) { return e.nb_records; }),
                        backgroundColor: function(ctx) { var g = ctx.chart.ctx.createLinearGradient(0,0,ctx.chart.width,0); g.addColorStop(0,'#ec4899'); g.addColorStop(1,'#f59e0b'); return g; },
                        borderRadius: 6, barThickness: 22 }] },
                    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
                });
            } catch(e) {}
        }
    }

    <?php if ($detailData): ?>
    // Cache disponible → injection directe (0 HTTP)
    _buildAccueilTables(<?= json_encode($detailData, JSON_UNESCAPED_UNICODE) ?>);
    window._topFallbackData = <?= json_encode(['top_athletes' => $detailData['top_athletes'] ?? [], 'top_clubs' => $detailData['top_clubs'] ?? []], JSON_UNESCAPED_UNICODE) ?>;
    <?php else: ?>
    // Pas de cache → AJAX fallback (1er visiteur uniquement)
    fetch(BASE_API + '/stats.php?detail=1&top=30')
        .then(function(r) { return r.json(); })
        .then(function(d) { _buildAccueilTables(d); })
        .catch(function() {
            ['topClubsBody','topAthletesBody','topVillesBody','topEpreuvesBody'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#ef4444;padding:20px;">Erreur de chargement</td></tr>';
            });
        });
    <?php endif; ?>
});
</script>

<!-- ======== TOP CONSULTES JS ======== -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    function _esc2(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Fallback : si search_tracking vide, utilise stats_detail_30 (top_athletes/top_clubs par score)
    function _fbMapAth(athletes) {
        return athletes.map(function(a) {
            return { id: a.athlete_id, nom: a.nom, club: (a.club || '').replace(/\*\s*$/, ''), categorie: a.categorie || '', sexe: a.sexe || '', vues: (a.nb_medailles||0)*5 + (a.nb_podiums||0)*3 + (a.nb_selections||0)*4 + (a.nb_records||0) };
        });
    }
    function _fbMapClubs(clubs) {
        return clubs.map(function(c) {
            return { id: 0, nom: (c.club || '').replace(/\*\s*$/, ''), nb_athletes: c.nb_athletes || 0, vues: c.nb_athletes || 0 };
        });
    }

    function _topSearchPag(items, bodyId, pagId, perPage, maxPages, renderRow) {
        var pg = 0, expanded = false;
        function render() {
            var pp = expanded ? 25 : perPage;
            var maxItems = expanded ? items.length : Math.min(items.length, perPage * maxPages);
            var visible = items.slice(0, maxItems);
            var totalPages = Math.ceil(visible.length / pp);
            var start = pg * pp, end = Math.min(start + pp, visible.length);
            var html = '';
            for (var i = start; i < end; i++) html += renderRow(visible[i], i);
            if (!html) html = '<tr><td colspan="3" style="text-align:center;color:#5a6580;padding:20px;">Aucune donn\u00e9e</td></tr>';
            document.getElementById(bodyId).innerHTML = html;
            var ph = '';
            if (totalPages > 1) {
                if (pg > 0) ph += '<button onclick="window._tsp_'+bodyId+'('+(pg-1)+')" style="padding:5px 12px;background:#1a2540;border:1px solid #253560;border-radius:6px;color:#d0d7e0;cursor:pointer;font-size:12px;">\u2190</button>';
                ph += '<span style="color:#5a6580;font-size:12px;padding:4px 8px;">'+(pg+1)+' / '+totalPages+'</span>';
                if (pg < totalPages - 1) ph += '<button onclick="window._tsp_'+bodyId+'('+(pg+1)+')" style="padding:5px 12px;background:#1a2540;border:1px solid #253560;border-radius:6px;color:#d0d7e0;cursor:pointer;font-size:12px;">\u2192</button>';
            }
            if (!expanded && items.length > perPage * maxPages) {
                ph += '<button onclick="window._tse_'+bodyId+'()" style="padding:5px 14px;background:#6c5ce722;border:1px solid #6c5ce7;border-radius:6px;color:#a29bfe;cursor:pointer;font-size:12px;margin-left:8px;">Voir tout ('+items.length+')</button>';
            }
            if (expanded && totalPages > 1) {
                ph += '<button onclick="window._tsc_'+bodyId+'()" style="padding:5px 14px;background:transparent;border:1px solid #253560;border-radius:6px;color:#5a6580;cursor:pointer;font-size:11px;margin-left:8px;">R\u00e9duire</button>';
            }
            document.getElementById(pagId).innerHTML = ph;
        }
        window['_tsp_'+bodyId] = function(p) { pg = p; render(); };
        window['_tse_'+bodyId] = function() { expanded = true; pg = 0; render(); };
        window['_tsc_'+bodyId] = function() { expanded = false; pg = 0; render(); };
        render();
    }

    // ---- Period tabs ----
    var _topPeriods = [{d:1,l:'Jour'},{d:7,l:'Semaine'},{d:30,l:'Mois'},{d:365,l:'Ann\u00e9e'}];
    var _clubDays = 1, _athDays = 1;
    var _tabStyle = 'padding:5px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid #253560;transition:all .2s;';
    var _tabActiveStyle = 'background:#6c5ce7;color:#fff;border-color:#6c5ce7;';
    var _tabInactiveStyle = 'background:transparent;color:#5a6580;border-color:#253560;';

    function _renderTabs(containerId, current, onClick) {
        var h = '';
        _topPeriods.forEach(function(p) {
            var active = p.d === current;
            h += '<button onclick="'+onClick+'('+p.d+')" style="'+_tabStyle+(active?_tabActiveStyle:_tabInactiveStyle)+'">'+p.l+'</button>';
        });
        document.getElementById(containerId).innerHTML = h;
    }

    // ---- Load & render clubs ----
    window._switchClubDays = function(d) { _clubDays = d; _renderTabs('topClubsTabs', _clubDays, '_switchClubDays'); _loadTopClubs(true); };
    function _loadTopClubs(nc) {
        document.getElementById('topSearchClubsBody').innerHTML = '<tr><td colspan="4" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr>';
        document.getElementById('topSearchClubsPag').innerHTML = '';
        fetch(BASE_API + '/top_searched.php?type=clubs&days=' + _clubDays + (nc ? '&nocache' : ''))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success && d.items && d.items.length) {
                    _renderTopClubs(d.items);
                } else if (window._topFallbackData && window._topFallbackData.top_clubs && window._topFallbackData.top_clubs.length) {
                    _renderTopClubs(_fbMapClubs(window._topFallbackData.top_clubs));
                } else {
                    _renderTopClubs([]);
                }
            })
            .catch(function() {
                if (window._topFallbackData && window._topFallbackData.top_clubs && window._topFallbackData.top_clubs.length) {
                    _renderTopClubs(_fbMapClubs(window._topFallbackData.top_clubs));
                } else { _renderTopClubs([]); }
            });
    }
    function _renderTopClubs(items) {
        if (!items || !items.length) {
            document.getElementById('topSearchClubsBody').innerHTML = '<tr><td colspan="4" style="text-align:center;color:#5a6580;padding:20px;">Aucune donn\u00e9e</td></tr>';
            document.getElementById('topSearchClubsCount').textContent = '';
            return;
        }
        document.getElementById('topSearchClubsCount').textContent = '(' + items.length + ')';
        _topSearchPag(items, 'topSearchClubsBody', 'topSearchClubsPag', 10, 5, function(c, i) {
            return '<tr>'
                + '<td style="color:#5a6580;width:40px;">' + (i+1) + '</td>'
                + '<td><a href="?page=recherche&club=' + encodeURIComponent(c.nom) + '" style="color:#a29bfe;text-decoration:none;font-weight:600;">' + _esc2(c.nom) + '</a></td>'
                + '<td style="color:#8b949e;font-size:12px;">' + (c.nb_athletes || '-') + '</td>'
                + '<td style="text-align:center;"><span style="color:#f59e0b;font-weight:600;">' + c.vues + '</span></td>'
                + '</tr>';
        });
    }

    // ---- Load & render athletes (depuis search_tracking) ----
    window._switchAthDays = function(d) { _athDays = d; _renderTabs('topAthTabs', _athDays, '_switchAthDays'); _loadTopAth(true); };
    function _loadTopAth(nc) {
        document.getElementById('topSearchAthBody').innerHTML = '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr>';
        document.getElementById('topSearchAthPag').innerHTML = '';
        fetch(BASE_API + '/top_searched.php?type=athletes&days=' + _athDays + (nc ? '&nocache' : ''))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success && d.items && d.items.length) {
                    _renderTopAth(d.items);
                } else {
                    _renderTopAth([]);
                }
            })
            .catch(function() { _renderTopAth([]); });
    }
    function _renderTopAth(items) {
        if (!items || !items.length) {
            document.getElementById('topSearchAthBody').innerHTML = '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:20px;">Aucune donn\u00e9e</td></tr>';
            document.getElementById('topSearchAthCount').textContent = '';
            return;
        }
        document.getElementById('topSearchAthCount').textContent = '(' + items.length + ')';
        _topSearchPag(items, 'topSearchAthBody', 'topSearchAthPag', 10, 5, function(a, i) {
            return '<tr>'
                + '<td style="color:#5a6580;width:40px;">' + (i+1) + '</td>'
                + '<td><a href="?page=profil&id=' + a.id + '" style="color:#a29bfe;text-decoration:none;font-weight:600;">' + _esc2(a.nom) + '</a></td>'
                + '<td style="color:#8b949e;font-size:12px;">' + _esc2(a.club || '-') + '</td>'
                + '<td><span class="badge badge-' + ((a.sexe||'').toLowerCase()) + '" style="font-size:11px;">' + _esc2(a.sexe || '-') + '</span></td>'
                + '<td style="text-align:center;"><span style="color:#f59e0b;font-weight:600;">' + a.vues + '</span></td>'
                + '</tr>';
        });
    }

    // Init tabs + load + auto-refresh toutes les 60s
    _renderTabs('topClubsTabs', _clubDays, '_switchClubDays');
    _renderTabs('topAthTabs', _athDays, '_switchAthDays');
    _loadTopClubs();
    _loadTopAth();
    setInterval(function() { _loadTopClubs(true); _loadTopAth(true); }, 60000);
});
</script>

<!-- ======== INIT CHARTS (données légères, chargement immédiat) ======== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.color = '#8892a8';
    Chart.defaults.borderColor = '#1e2a3a';
    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";

    // --- Sexe (Doughnut) ---
    new Chart(document.getElementById('chartSexe'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($data['par_sexe'] as $s => $nb) echo "'" . ($s === 'M' ? 'Hommes' : ($s === 'F' ? 'Femmes' : $s)) . "',"; ?>],
            datasets: [{
                data: [<?php echo implode(',', array_values($data['par_sexe'])); ?>],
                backgroundColor: ['#3b82f6', '#ec4899', '#8b5cf6', '#10b981'],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, pointStyleWidth: 10, font: { size: 12 } } }
            }
        }
    });

    // --- Categories (Bar horizontal) ---
    new Chart(document.getElementById('chartCategories'), {
        type: 'bar',
        data: {
            <?php $catsVisibles = ['SE','CA','JU','ES','MI','BE','MA']; $catsFiltrees = array_intersect_key($data['par_categorie'], array_flip($catsVisibles)); ?>
            labels: [<?php foreach ($catsFiltrees as $cat => $nb) echo "'" . htmlspecialchars($cat) . "',"; ?>],
            datasets: [{
                data: [<?php echo implode(',', array_values($catsFiltrees)); ?>],
                backgroundColor: '#10b981',
                borderRadius: 4,
                barThickness: 14
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#1e2a3a' }, ticks: { font: { size: 11 } } },
                y: { grid: { display: false }, ticks: { font: { size: 11, weight: 600 } } }
            }
        }
    });
});
</script>

<?php else: ?>
<div class="error">Impossible de contacter l'API. Verifiez que le serveur est en ligne.</div>
<?php endif; ?>


<?php
// ================================================================
//  LISTE ATHLETES
// ================================================================
elseif ($page === 'athletes'):
    $ordre = $_GET['ordre'] ?? 'random';
    $data = apiCall("$BASE_API/liste.php?page=$p&limit=50&ordre=$ordre");
?>

<h1>Athlètes</h1>

<div class="live-search">
    <span class="ls-icon">&#128269;</span>
    <input type="text" id="lsAthletes" placeholder="Rechercher un athlète par nom..." autocomplete="off">
    <div class="ls-status" id="lsAthletesStatus"></div>
</div>
<div class="ls-results" id="lsAthletesResults" style="display:none;"></div>

<div id="athletesPaginated">
<div style="margin:10px 0;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
    <span style="color:#8b949e;font-size:13px;">Trier par :</span>
    <a href="?page=athletes&ordre=random" class="btn <?= $ordre === 'random' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Random</a>
    <a href="?page=athletes&ordre=nom" class="btn <?= $ordre === 'nom' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Nom</a>
    <a href="?page=athletes&ordre=recent" class="btn <?= $ordre === 'recent' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Plus récents</a>
    <a href="?page=athletes&ordre=id" class="btn <?= $ordre === 'id' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">ID athle.fr</a>
    <a href="?page=athletes&ordre=medailles" class="btn <?= $ordre === 'medailles' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Médailles</a>
    <a href="?page=athletes&ordre=podiums" class="btn <?= $ordre === 'podiums' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Podiums</a>
    <a href="?page=athletes&ordre=selections" class="btn <?= $ordre === 'selections' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Sélections</a>
    <a href="?page=athletes&ordre=records" class="btn <?= $ordre === 'records' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Records</a>
</div>

<?php if ($data && ($data['success'] ?? false)):
    // Stats de la page courante
    $athSexe = []; $athCat = []; $athNat = [];
    foreach ($data['athletes'] as $a) {
        $s = $a['sexe'] ?: 'Inconnu';
        $athSexe[$s] = ($athSexe[$s] ?? 0) + 1;
        $c = $a['categorie'] ?: 'Autre';
        $athCat[$c] = ($athCat[$c] ?? 0) + 1;
        $n = $a['nationalite'] ?: 'Autre';
        $athNat[$n] = ($athNat[$n] ?? 0) + 1;
    }
    arsort($athNat);
    $athNat = array_slice($athNat, 0, 8, true);
?>
<p class="subtitle"><?= number_format($data['total'], 0, ',', ' ') ?> athletes — page <?= $data['page'] ?>/<?= $data['total_pages'] ?></p>

<!-- Graphiques de la page -->
<div class="charts-row-3" style="margin-bottom:20px;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">M/F</span> Sexe (page)</h3><canvas id="athChartSexe"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">Cat</span> Categories (page)</h3><canvas id="athChartCat"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">NAT</span> Nationalités (page)</h3><canvas id="athChartNat"></canvas></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('athChartSexe'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($athSexe as $k => $v) echo "'" . ($k==='M'?'Hommes':($k==='F'?'Femmes':$k)) . "',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($athSexe)) ?>], backgroundColor: ['#3b82f6','#ec4899','#8b5cf6','#64748b'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } } } }
    });
    new Chart(document.getElementById('athChartCat'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($athCat as $k => $v) echo "'$k',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($athCat)) ?>], backgroundColor: '#34d399', borderRadius: 4, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
    new Chart(document.getElementById('athChartNat'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($athNat as $k => $v) echo "'$k',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($athNat)) ?>], backgroundColor: '#a78bfa', borderRadius: 4, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
});
</script>

<?php
    // Calcul stats globales pour pourcentages
    $totalAthPage = count($data['athletes']);
    $sumMed = 0; $sumPod = 0; $sumSel = 0; $sumRec = 0; $sumRes = 0;
    $maxMed = 0; $maxPod = 0; $maxSel = 0; $maxRec = 0; $maxRes = 0;
    foreach ($data['athletes'] as $a) {
        $sumMed += $a['nb_medailles']; $sumPod += $a['nb_podiums']; $sumSel += $a['nb_selections'];
        $sumRec += $a['nb_records']; $sumRes += $a['nb_resultats'];
        if ($a['nb_medailles'] > $maxMed) $maxMed = $a['nb_medailles'];
        if ($a['nb_podiums'] > $maxPod) $maxPod = $a['nb_podiums'];
        if ($a['nb_selections'] > $maxSel) $maxSel = $a['nb_selections'];
        if ($a['nb_records'] > $maxRec) $maxRec = $a['nb_records'];
        if ($a['nb_resultats'] > $maxRes) $maxRes = $a['nb_resultats'];
    }
?>
<!-- Stats résumées de la page -->
<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
    <div style="flex:1;min-width:100px;text-align:center;padding:10px;background:#f59e0b10;border:1px solid #f59e0b30;border-radius:10px;">
        <div style="font-size:20px;font-weight:700;color:#fbbf24;"><?= number_format($sumMed, 0, ',', ' ') ?></div>
        <div style="font-size:11px;color:#8b949e;">Médailles</div>
    </div>
    <div style="flex:1;min-width:100px;text-align:center;padding:10px;background:#10b98110;border:1px solid #10b98130;border-radius:10px;">
        <div style="font-size:20px;font-weight:700;color:#34d399;"><?= number_format($sumPod, 0, ',', ' ') ?></div>
        <div style="font-size:11px;color:#8b949e;">Podiums</div>
    </div>
    <div style="flex:1;min-width:100px;text-align:center;padding:10px;background:#6366f110;border:1px solid #6366f130;border-radius:10px;">
        <div style="font-size:20px;font-weight:700;color:#818cf8;"><?= number_format($sumSel, 0, ',', ' ') ?></div>
        <div style="font-size:11px;color:#8b949e;">Sélections</div>
    </div>
    <div style="flex:1;min-width:100px;text-align:center;padding:10px;background:#8b5cf610;border:1px solid #8b5cf630;border-radius:10px;">
        <div style="font-size:20px;font-weight:700;color:#a78bfa;"><?= number_format($sumRec, 0, ',', ' ') ?></div>
        <div style="font-size:11px;color:#8b949e;">Records</div>
    </div>
    <div style="flex:1;min-width:100px;text-align:center;padding:10px;background:#06b6d410;border:1px solid #06b6d430;border-radius:10px;">
        <div style="font-size:20px;font-weight:700;color:#22d3ee;"><?= number_format($sumRes, 0, ',', ' ') ?></div>
        <div style="font-size:11px;color:#8b949e;">Résultats</div>
    </div>
</div>

<?php
function _athNivBadge($code) {
    $nc = $code[0] ?? '';
    if ($nc === 'N') { $bg='#e11d4820'; $bc='#e11d48'; $tc='#fb7185'; }
    elseif ($nc === 'I') { $bg='#c026d320'; $bc='#c026d3'; $tc='#e879f9'; }
    elseif ($nc === 'R') { $bg='#0891b220'; $bc='#0891b2'; $tc='#22d3ee'; }
    else { $bg='#f9731620'; $bc='#f97316'; $tc='#fb923c'; }
    return '<span style="display:inline-block;padding:2px 7px;border-radius:5px;font-size:10px;margin:1px;background:'.$bg.';border:1px solid '.$bc.'40;color:'.$tc.';">'.htmlspecialchars($code).'</span>';
}
?>

<div class="table-wrap">
<?php $thAthFull = '<tr><th>#</th><th>Athlète</th><th>Club</th><th>Cat</th><th>Sexe</th><th>NAT</th><th>Niveaux</th><th>Médailles</th><th>Podiums</th><th>Sél.</th><th>Records</th><th class="hide-mobile">Résultats</th><th class="hide-mobile">Spécialité</th><th></th><th></th></tr>'; ?>
<table class="bk-table"><?= $thAthFull ?></table>
<table class="bk-table">
    <?php foreach ($data['athletes'] as $idx => $a):
        $med = $a['medailles'] ?? ['or'=>0,'argent'=>0,'bronze'=>0];
        $totalMedA = $med['or'] + $med['argent'] + $med['bronze'];
        $topEp = $a['top_epreuve'] ?? null;
    ?>
    <tr>
        <td><?= ($p - 1) * 50 + $idx + 1 ?></td>
        <td>
            <b><a href="?page=profil&id=<?= $a['athlete_id'] ?>"><?= htmlspecialchars($a['nom_complet']) ?></a></b>
            <?php if ($a['date_naissance']): ?>
                <br><span style="font-size:11px;color:#5a6580;"><?= substr($a['date_naissance'], 0, 4) ?><?php
                if ($a['taille_cm'] || $a['poids_kg']) {
                    echo ' · ';
                    if ($a['taille_cm']) echo $a['taille_cm'] . 'cm';
                    if ($a['taille_cm'] && $a['poids_kg']) echo '/';
                    if ($a['poids_kg']) echo $a['poids_kg'] . 'kg';
                }
                if ($a['max_points']) echo ' · ' . number_format($a['max_points'], 0, ',', ' ') . ' pts';
                ?></span>
            <?php endif; ?>
        </td>
        <td><?php if ($a['club']): ?><a href="?page=clubs&open=<?= urlencode($a['club']) ?>" style="color:#a29bfe;text-decoration:none;font-size:12px;"><?= htmlspecialchars(mb_substr($a['club'], 0, 25)) ?><?= mb_strlen($a['club']) > 25 ? '…' : '' ?></a><?php else: ?>-<?php endif; ?></td>
        <td><a href="?page=recherche&categorie=<?= urlencode($a['categorie']) ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= $a['categorie'] ?></span></a></td>
        <td><a href="?page=recherche&sexe=<?= urlencode($a['sexe']) ?>" style="text-decoration:none;"><span class="badge badge-<?= strtolower($a['sexe']) ?>"><?= $a['sexe'] ?></span></a></td>
        <td><?php if ($a['nationalite']): ?><a href="?page=recherche&nationalite=<?= urlencode($a['nationalite']) ?>" style="color:#c9d1d9;text-decoration:none;"><?= $a['nationalite'] ?></a><?php else: ?>-<?php endif; ?></td>
        <td><?php
            if ($a['meilleur_niveau']) {
                echo _athNivBadge($a['meilleur_niveau']);
                $restNiv = array_filter($a['niveaux'] ?? [], function($n) use ($a) { return $n !== $a['meilleur_niveau']; });
                if (count($restNiv) > 0) echo '<span style="color:#5a6580;font-size:10px;margin-left:2px;">+' . count($restNiv) . '</span>';
            } else { echo '-'; }
        ?></td>
        <td><?php if ($totalMedA > 0) {
            if ($med['or'] > 0) echo '<span style="color:#fbbf24;font-size:12px;font-weight:600;" title="Or">🥇' . $med['or'] . '</span> ';
            if ($med['argent'] > 0) echo '<span style="color:#94a3b8;font-size:12px;font-weight:600;" title="Argent">🥈' . $med['argent'] . '</span> ';
            if ($med['bronze'] > 0) echo '<span style="color:#cd7f32;font-size:12px;font-weight:600;" title="Bronze">🥉' . $med['bronze'] . '</span>';
        } else { echo '-'; } ?></td>
        <td><?php if ($a['nb_podiums'] > 0): ?>
            <span style="display:inline-block;padding:2px 8px;background:#10b98115;border:1px solid #10b98130;border-radius:5px;color:#34d399;font-size:12px;font-weight:600;"><?= $a['nb_podiums'] ?></span>
        <?php else: ?>-<?php endif; ?></td>
        <td><?php if ($a['nb_selections'] > 0): ?>
            <span style="display:inline-block;padding:2px 8px;background:#6366f115;border:1px solid #6366f130;border-radius:5px;color:#818cf8;font-size:12px;font-weight:600;"><?= $a['nb_selections'] ?></span>
        <?php else: ?>-<?php endif; ?></td>
        <td><?php if ($a['nb_records'] > 0): ?><a href="?page=profil&id=<?= $a['athlete_id'] ?>&s=records" style="text-decoration:none;"><span class="badge badge-perf"><?= $a['nb_records'] ?></span></a><?php else: ?>-<?php endif; ?></td>
        <td class="hide-mobile"><?php if ($a['nb_resultats'] > 0): ?>
            <span style="color:#22d3ee;font-size:12px;"><?= number_format($a['nb_resultats'], 0, ',', ' ') ?></span>
            <?php if ($a['nb_progressions'] > 0): ?><br><span style="color:#5a6580;font-size:10px;">↗ <?= $a['nb_progressions'] ?> prog.</span><?php endif; ?>
        <?php else: ?>-<?php endif; ?></td>
        <td class="hide-mobile"><?php if ($topEp): ?>
            <a href="?page=recherche&epreuve=<?= urlencode($topEp['epreuve']) ?>" style="color:#a29bfe;font-size:11px;text-decoration:none;"><?= htmlspecialchars(mb_substr($topEp['epreuve'], 0, 20)) ?></a>
            <?php if ($topEp['best']): ?><br><span style="color:#5a6580;font-size:10px;">RP: <?= htmlspecialchars($topEp['best']) ?></span><?php endif; ?>
        <?php else: ?>-<?php endif; ?></td>
        <td><a href="?page=profil&id=<?= $a['athlete_id'] ?>&s=records" style="font-size:12px;">Profil</a></td>
        <td><button class="btn-cmp-add" data-cmp-ath="<?= $a['athlete_id'] ?>" data-name="<?= htmlspecialchars($a['nom_complet'], ENT_QUOTES) ?>" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">+</button></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><?= $thAthFull ?></table>
</div>

<div class="pager">
    <?php if ($p > 1): ?><a href="?page=athletes&ordre=<?= $ordre ?>&p=<?= $p - 1 ?>">Precedent</a><?php endif; ?>
    <?php
    $start = max(1, $p - 3);
    $end = min($data['total_pages'], $p + 3);
    for ($i = $start; $i <= $end; $i++):
    ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?page=athletes&ordre=<?= $ordre ?>&p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?page=athletes&ordre=<?= $ordre ?>&p=<?= $p + 1 ?>">Suivant</a><?php endif; ?>
    <span class="info">(<?= $data['total_pages'] ?> pages)</span>
</div>

<?php else: ?>
<div class="error">Erreur de chargement.</div>
<?php endif; ?>
</div>


<?php
// ================================================================
//  RECHERCHE
// ================================================================
elseif ($page === 'recherche'):
    // Incrementer le compteur de vues du club (1 seule fois par IP)
    if (!empty($_GET['club'])) {
        $__ip = $conn->real_escape_string(getVisitorIp());
        $__clubName = $conn->real_escape_string($_GET['club']);
        $__clubRes = $conn->query("SELECT id_club FROM clubs WHERE nom_club = '$__clubName' LIMIT 1");
        if ($__clubRes && ($__clubRow = $__clubRes->fetch_assoc())) {
            $__cid = (int)$__clubRow['id_club'];
            @$conn->query("INSERT IGNORE INTO club_vues_ip (ip, club_id) VALUES ('$__ip', $__cid)");
            if ($conn->affected_rows > 0) {
                @$conn->query("UPDATE clubs SET vues = vues + 1 WHERE id_club = $__cid");
            }
            // Tracking search_tracking
            $__stQ = $_GET['club'];
            $__stStmt = $conn->prepare("INSERT INTO search_tracking (ip, query_text, search_type, source, entity_id, entity_name, result_count, page) VALUES (?, ?, 'club', 'page_view', ?, ?, 0, 'recherche')");
            if ($__stStmt) { $__stStmt->bind_param("ssis", $__ip, $__stQ, $__cid, $__stQ); $__stStmt->execute(); $__stStmt->close(); }
        }
    }
?>

<?php if (!empty($_GET['club'])): ?>
<h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <span style="font-size:20px;">&#127963;</span>
    <?= htmlspecialchars($_GET['club']) ?>
    <?php if (!empty($_GET['nationalite']) || !empty($_GET['sexe']) || !empty($_GET['categorie'])): ?>
    <span style="font-size:14px;color:#64748b;font-weight:400;">
        &mdash;
        <?php
        $filtres = [];
        if (!empty($_GET['nationalite'])) $filtres[] = strtoupper($_GET['nationalite']);
        if (!empty($_GET['sexe'])) $filtres[] = ($_GET['sexe'] === 'M' ? 'Hommes' : 'Femmes');
        if (!empty($_GET['categorie'])) $filtres[] = htmlspecialchars($_GET['categorie']);
        echo implode(', ', $filtres);
        ?>
    </span>
    <?php endif; ?>
</h1>
<?php else: ?>
<h1>Recherche</h1>
<?php endif; ?>

<div class="live-search" id="lsRechercheWrap">
    <span class="ls-icon">&#128269;</span>
    <input type="text" id="lsRecherche" placeholder="<?= !empty($_GET['club']) ? 'Rechercher un athlète dans ' . htmlspecialchars($_GET['club']) . '...' : 'Recherche rapide par nom...' ?>" autocomplete="off">
    <?php if (!empty($_GET['club'])): ?>
    <div style="margin-top:6px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#6c5ce715;border:1px solid #6c5ce740;border-radius:6px;font-size:11px;color:#a29bfe;">&#127963; <?= htmlspecialchars($_GET['club']) ?></span>
        <span style="color:#3a4560;font-size:11px;">Tapez un nom pour chercher dans ce club</span>
    </div>
    <?php endif; ?>
    <div class="ls-status" id="lsRechercheStatus"></div>
</div>
<div class="ls-results" id="lsRechercheResults" style="display:none;"></div>

<div id="recherchePaginated">
<p class="subtitle" style="margin-top:10px;color:#484f58;font-size:12px;">Ou recherche avancee :</p>
<div class="search-box" style="margin-top:5px;">
    <form method="get" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
        <input type="hidden" name="page" value="recherche">
        <input type="text" name="nom" placeholder="Nom / prenom..." value="<?= htmlspecialchars($_GET['nom'] ?? '') ?>" style="width:200px;">
        <input type="text" name="club" placeholder="Club..." value="<?= htmlspecialchars($_GET['club'] ?? '') ?>" style="width:160px;">
        <input type="text" name="epreuve" placeholder="Épreuve..." value="<?= htmlspecialchars($_GET['epreuve'] ?? '') ?>" style="width:130px;">
        <select name="sexe">
            <option value="">Sexe</option>
            <option value="M" <?= ($_GET['sexe'] ?? '') === 'M' ? 'selected' : '' ?>>Homme</option>
            <option value="F" <?= ($_GET['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Femme</option>
        </select>
        <select name="categorie">
            <option value="">Categorie</option>
            <?php foreach (['EA','PO','BE','MI','CA','JU','ES','SE','V1','V2','V3','V4'] as $c): ?>
            <option value="<?= $c ?>" <?= ($_GET['categorie'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
        </select>
        <select name="nationalite" style="width:auto;">
            <option value="">Nationalité</option>
            <?php
            $natRes = $conn->query("SELECT nationalite_athlete, COUNT(*) as c FROM athletes WHERE nationalite_athlete IS NOT NULL AND nationalite_athlete != '' GROUP BY nationalite_athlete ORDER BY c DESC");
            if ($natRes) while ($nr = $natRes->fetch_assoc()):
            ?>
            <option value="<?= htmlspecialchars($nr['nationalite_athlete']) ?>" <?= ($_GET['nationalite'] ?? '') === $nr['nationalite_athlete'] ? 'selected' : '' ?>><?= htmlspecialchars($nr['nationalite_athlete']) ?> (<?= number_format($nr['c'], 0, ',', ' ') ?>)</option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn">Rechercher</button>
    </form>
</div>

<?php
$params = [];
foreach (['nom','club','epreuve','sexe','categorie','nationalite'] as $key) {
    if (!empty($_GET[$key])) $params[$key] = $_GET[$key];
}

if (!empty($params)):

    // ---- MODE EPREUVE : classement par performance ----
    $epFilter = trim($_GET['epreuve'] ?? '');
    $hasOnlyEpreuve = ($epFilter !== '');

    if ($hasOnlyEpreuve):
        $epParams = ['nom' => $epFilter, 'page' => $p, 'limit' => 50];
        if (!empty($_GET['sexe'])) $epParams['sexe'] = $_GET['sexe'];
        if (!empty($_GET['categorie'])) $epParams['categorie'] = $_GET['categorie'];
        $url = "$BASE_API/epreuve_stats.php?" . http_build_query($epParams);
        $data = apiCall($url);

        if ($data && ($data['success'] ?? false)):
            $epRecs = $data['records'] ?? [];
?>

<div style="margin-bottom:18px;">
    <h2 style="margin:0 0 6px 0;color:#c9d1d9;"><?= htmlspecialchars($data['epreuve']) ?></h2>
    <div style="color:#8b949e;font-size:13px;">
        <b><?= number_format($data['total_records'], 0, ',', ' ') ?></b> records
        — <b><?= number_format($data['total_athletes'], 0, ',', ' ') ?></b> athlètes
        <?php if ($data['annee_debut']): ?> — <?= $data['annee_debut'] ?> → <?= $data['annee_fin'] ?><?php endif; ?>
        <?php if (!empty($_GET['sexe'])): ?> — Sexe : <span class="badge badge-<?= strtolower($_GET['sexe']) ?>"><?= $_GET['sexe'] === 'M' ? 'Hommes' : 'Femmes' ?></span><?php endif; ?>
        <?php if (!empty($_GET['categorie'])): ?> — Cat : <span class="badge badge-cat"><?= htmlspecialchars($_GET['categorie']) ?></span><?php endif; ?>
    </div>
</div>

<!-- Filtres rapides par catégorie -->
<?php if (!empty($data['par_categorie'])): ?>
<div style="margin-bottom:14px;display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
    <span style="color:#8b949e;font-size:12px;margin-right:4px;">Catégories :</span>
    <?php
    $curCat = $_GET['categorie'] ?? '';
    foreach ($data['par_categorie'] as $catK => $catV):
        $catActive = ($curCat === $catK);
        $catLink = $_GET; $catLink['categorie'] = $catK; unset($catLink['p']);
        if ($catActive) { unset($catLink['categorie']); }
    ?>
    <a href="?<?= http_build_query($catLink) ?>" style="display:inline-block;padding:3px 10px;border-radius:5px;font-size:11px;font-weight:600;text-decoration:none;<?= $catActive ? 'background:linear-gradient(135deg,#6c5ce7,#a29bfe);color:#fff;' : 'background:#6c5ce720;border:1px solid #6c5ce740;color:#a29bfe;' ?>"><?= $catK ?> <span style="opacity:.7;font-size:10px;">(<?= $catV ?>)</span></a>
    <?php endforeach; ?>
    <?php if ($curCat !== ''): $clearCatLink = $_GET; unset($clearCatLink['categorie']); unset($clearCatLink['p']); ?>
    <a href="?<?= http_build_query($clearCatLink) ?>" style="display:inline-block;padding:3px 10px;border-radius:5px;font-size:11px;background:#ef444420;border:1px solid #ef444440;color:#f87171;text-decoration:none;">✕ Tout</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Filtres rapides par sexe -->
<?php if (!empty($data['par_sexe'])): ?>
<div style="margin-bottom:16px;display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
    <span style="color:#8b949e;font-size:12px;margin-right:4px;">Sexe :</span>
    <?php
    $curSexe = $_GET['sexe'] ?? '';
    foreach ($data['par_sexe'] as $sK => $sV):
        $sActive = ($curSexe === $sK);
        $sLink = $_GET; $sLink['sexe'] = $sK; unset($sLink['p']);
        if ($sActive) { unset($sLink['sexe']); }
        $sLabel = ($sK === 'M') ? 'Hommes' : (($sK === 'F') ? 'Femmes' : $sK);
    ?>
    <a href="?<?= http_build_query($sLink) ?>" style="display:inline-block;padding:3px 10px;border-radius:5px;font-size:11px;font-weight:600;text-decoration:none;<?= $sActive ? 'background:linear-gradient(135deg,#3b82f6,#60a5fa);color:#fff;' : 'background:#3b82f620;border:1px solid #3b82f640;color:#60a5fa;' ?>"><?= $sLabel ?> <span style="opacity:.7;font-size:10px;">(<?= $sV ?>)</span></a>
    <?php endforeach; ?>
    <?php if ($curSexe !== ''): $clearSLink = $_GET; unset($clearSLink['sexe']); unset($clearSLink['p']); ?>
    <a href="?<?= http_build_query($clearSLink) ?>" style="display:inline-block;padding:3px 10px;border-radius:5px;font-size:11px;background:#ef444420;border:1px solid #ef444440;color:#f87171;text-decoration:none;">✕ Tout</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Top clubs cliquables -->
<?php if (!empty($data['top_clubs'])): ?>
<div style="margin-bottom:16px;display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
    <span style="color:#8b949e;font-size:12px;margin-right:4px;">Top clubs :</span>
    <?php foreach ($data['top_clubs'] as $tc): ?>
    <a href="?page=recherche&club=<?= urlencode($tc['club']) ?>" style="display:inline-block;padding:3px 10px;border-radius:5px;font-size:11px;font-weight:600;text-decoration:none;background:#10b98120;border:1px solid #10b98140;color:#34d399;"><?= htmlspecialchars($tc['club']) ?> <span style="opacity:.7;font-size:10px;">(<?= $tc['nb_athletes'] ?>)</span></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ====== STATS RÉSUMÉES ====== -->
<?php
$hasMedial = ($data['total_medailles'] ?? 0) > 0;
$hasPodium = ($data['total_podiums'] ?? 0) > 0;
$hasSel = ($data['selections']['nb_selections'] ?? 0) > 0;
$hasProg = ($data['progressions']['nb_progressions'] ?? 0) > 0;
?>
<?php if ($hasMedial || $hasPodium || $hasSel || $hasProg): ?>
<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <?php if ($hasMedial): ?>
    <div style="flex:1;min-width:120px;text-align:center;padding:12px;background:#0d1520;border:1px solid #fbbf2430;border-radius:10px;">
        <div style="font-size:22px;font-weight:700;color:#fbbf24;"><?= $data['total_medailles'] ?></div>
        <div style="font-size:11px;color:#8b949e;margin-bottom:4px;">Médailles</div>
        <div style="font-size:11px;">
            <span style="color:#fbbf24;">🥇<?= $data['medailles']['or'] ?></span>
            <span style="color:#94a3b8;margin-left:4px;">🥈<?= $data['medailles']['argent'] ?></span>
            <span style="color:#cd7f32;margin-left:4px;">🥉<?= $data['medailles']['bronze'] ?></span>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($hasPodium): ?>
    <div style="flex:1;min-width:120px;text-align:center;padding:12px;background:#0d1520;border:1px solid #818cf830;border-radius:10px;">
        <div style="font-size:22px;font-weight:700;color:#818cf8;"><?= $data['total_podiums'] ?></div>
        <div style="font-size:11px;color:#8b949e;margin-bottom:4px;">Podiums</div>
        <div style="font-size:11px;color:#5a6580;">1er: <?= $data['podiums']['1er'] ?> · 2e: <?= $data['podiums']['2e'] ?> · 3e: <?= $data['podiums']['3e'] ?></div>
    </div>
    <?php endif; ?>
    <?php if ($hasSel): ?>
    <div style="flex:1;min-width:120px;text-align:center;padding:12px;background:#0d1520;border:1px solid #818cf830;border-radius:10px;">
        <div style="font-size:22px;font-weight:700;color:#818cf8;"><?= $data['selections']['nb_selections'] ?></div>
        <div style="font-size:11px;color:#8b949e;">Sélections</div>
        <div style="font-size:10px;color:#5a6580;"><?= $data['selections']['nb_athletes'] ?> athlètes</div>
    </div>
    <?php endif; ?>
    <?php if ($hasProg): ?>
    <div style="flex:1;min-width:120px;text-align:center;padding:12px;background:#0d1520;border:1px solid #34d39930;border-radius:10px;">
        <div style="font-size:22px;font-weight:700;color:#34d399;"><?= $data['progressions']['nb_progressions'] ?></div>
        <div style="font-size:11px;color:#8b949e;">Progressions</div>
        <div style="font-size:10px;color:#5a6580;"><?= $data['progressions']['nb_athletes'] ?> athlètes</div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ====== NIVEAUX & NATIONALITÉS ====== -->
<?php if (!empty($data['niveaux_resultats']) || !empty($data['nationalites'])): ?>
<div style="display:flex;flex-wrap:wrap;gap:20px;margin-bottom:24px;">
    <?php if (!empty($data['niveaux_resultats'])): ?>
    <div style="flex:1;min-width:250px;">
        <h3 style="margin:0 0 8px;color:#e879f9;font-size:13px;font-weight:600;">Niveaux de compétition</h3>
        <div style="display:flex;flex-wrap:wrap;gap:5px;">
            <?php foreach ($data['niveaux_resultats'] as $nv):
                $nc = $nv['niveau'][0] ?? '';
                if ($nc === 'N') { $nbg='#e11d4820'; $nbc='#e11d48'; $ntc='#fb7185'; }
                elseif ($nc === 'I') { $nbg='#c026d320'; $nbc='#c026d3'; $ntc='#e879f9'; }
                elseif ($nc === 'R') { $nbg='#0891b220'; $nbc='#0891b2'; $ntc='#22d3ee'; }
                else { $nbg='#f9731620'; $nbc='#f97316'; $ntc='#fb923c'; }
            ?>
            <span style="display:inline-block;padding:3px 9px;border-radius:5px;font-size:11px;background:<?= $nbg ?>;border:1px solid <?= $nbc ?>40;color:<?= $ntc ?>;font-weight:600;"><?= htmlspecialchars($nv['niveau']) ?> (<?= $nv['count'] ?>)</span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($data['nationalites'])): ?>
    <div style="flex:1;min-width:250px;">
        <h3 style="margin:0 0 8px;color:#f472b6;font-size:13px;font-weight:600;">Nationalités (<?= count($data['nationalites']) ?>)</h3>
        <div style="display:flex;flex-wrap:wrap;gap:5px;">
            <?php $nc2 = 0; foreach ($data['nationalites'] as $natK => $natV): if ($nc2 >= 15) break; $nc2++; ?>
            <a href="?page=recherche&nationalite=<?= urlencode($natK) ?>" style="display:inline-block;padding:3px 9px;border-radius:5px;font-size:11px;text-decoration:none;background:#ec489920;border:1px solid #ec489940;color:#f472b6;"><?= htmlspecialchars($natK) ?> (<?= $natV ?>)</a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ====== TOP CLUBS & VILLES ====== -->
<?php if (!empty($data['top_clubs']) || !empty($data['top_villes'])): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
    <?php if (!empty($data['top_clubs'])): ?>
    <div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;">
        <h3 style="margin:0 0 10px;color:#34d399;font-size:13px;font-weight:600;">Top <?= count($data['top_clubs']) ?> Clubs</h3>
        <table class="bk-table" style="font-size:12px;"><tr><th>#</th><th>Club</th><th>Ath.</th><th>Rec.</th></tr></table>
        <table class="bk-table" style="font-size:12px;">
            <?php foreach ($data['top_clubs'] as $i => $tc): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><a href="?page=recherche&club=<?= urlencode($tc['club']) ?>" style="color:#34d399;text-decoration:none;"><?= htmlspecialchars($tc['club']) ?></a></td>
                <td><?= $tc['nb_athletes'] ?></td>
                <td><?= $tc['nb_records'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <table class="bk-table" style="font-size:12px;"><tr><th>#</th><th>Club</th><th>Ath.</th><th>Rec.</th></tr></table>
    </div>
    <?php endif; ?>

    <?php if (!empty($data['top_villes'])): ?>
    <div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;">
        <h3 style="margin:0 0 10px;color:#60a5fa;font-size:13px;font-weight:600;">Top <?= count($data['top_villes']) ?> Villes</h3>
        <table class="bk-table" style="font-size:12px;"><tr><th>#</th><th>Ville</th><th>Rec.</th><th>Ath.</th></tr></table>
        <table class="bk-table" style="font-size:12px;">
            <?php foreach ($data['top_villes'] as $i => $tv): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><a href="?page=villes&open=<?= urlencode($tv['ville']) ?>" style="color:#60a5fa;text-decoration:none;"><?= htmlspecialchars($tv['ville']) ?></a></td>
                <td><?= $tv['nb_records'] ?></td>
                <td><?= $tv['nb_athletes'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <table class="bk-table" style="font-size:12px;"><tr><th>#</th><th>Ville</th><th>Rec.</th><th>Ath.</th></tr></table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ====== MÉDAILLES DÉTAIL & ÉVOLUTION ====== -->
<?php if (!empty($data['medailles_detail']) || !empty($data['resultats_par_annee'])): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
    <?php if (!empty($data['medailles_detail'])): ?>
    <div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;">
        <h3 style="margin:0 0 10px;color:#fbbf24;font-size:13px;font-weight:600;">Dernières médailles</h3>
        <table class="bk-table" style="font-size:11px;"><tr><th>#</th><th></th><th>Athlète</th><th class="hide-mobile">Compétition</th><th>Année</th></tr></table>
        <table class="bk-table" style="font-size:11px;">
            <?php foreach ($data['medailles_detail'] as $i => $md): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?php
                    $mt = strtolower($md['type'] ?? '');
                    if ($mt === 'or') echo '🥇';
                    elseif ($mt === 'argent') echo '🥈';
                    else echo '🥉';
                ?></td>
                <td><a href="?page=profil&id=<?= $md['athlete_id'] ?>" style="color:#a29bfe;text-decoration:none;font-size:11px;"><?= htmlspecialchars($md['athlete']) ?></a></td>
                <td class="hide-mobile" style="font-size:10px;color:#5a6580;"><?= htmlspecialchars($md['competition'] ?? '-') ?></td>
                <td><?= $md['annee'] ?? '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <table class="bk-table" style="font-size:11px;"><tr><th>#</th><th></th><th>Athlète</th><th class="hide-mobile">Compétition</th><th>Année</th></tr></table>
    </div>
    <?php endif; ?>

    <?php if (!empty($data['resultats_par_annee'])): ?>
    <div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;">
        <h3 style="margin:0 0 10px;color:#fb923c;font-size:13px;font-weight:600;">Évolution par année</h3>
        <table class="bk-table" style="font-size:12px;"><tr><th>Année</th><th>Résultats</th><th>Athlètes</th></tr></table>
        <table class="bk-table" style="font-size:12px;">
            <?php foreach (array_slice($data['resultats_par_annee'], 0, 10) as $rpa): ?>
            <tr>
                <td style="font-weight:600;"><?= $rpa['annee'] ?></td>
                <td><?= number_format($rpa['nb_resultats'], 0, ',', ' ') ?></td>
                <td><?= $rpa['nb_athletes'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <table class="bk-table" style="font-size:12px;"><tr><th>Année</th><th>Résultats</th><th>Athlètes</th></tr></table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ====== TABLEAU PERFORMANCES ====== -->
<h3 style="margin:24px 0 8px;color:#c9d1d9;font-size:15px;font-weight:600;">Classement des performances</h3>
<p class="subtitle"><?= number_format($data['total_records'], 0, ',', ' ') ?> performances — page <?= $data['page'] ?>/<?= $data['total_pages'] ?> (50 par page)</p>

<?php if (!empty($epRecs)): ?>
<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Athlète</th><th>Performance</th><th class="hide-mobile">Date</th><th class="hide-mobile">Club</th><th>Cat</th><th class="hide-mobile">Sexe</th><th class="hide-mobile">NAT</th><th>Niveaux</th><th></th></tr></table>
<table class="bk-table">
    <?php foreach ($epRecs as $idx => $rec): ?>
    <tr>
        <td><?= ($p - 1) * 50 + $idx + 1 ?></td>
        <td><b><a href="?page=profil&id=<?= $rec['athlete_id'] ?>" style="color:#a29bfe;"><?= htmlspecialchars($rec['athlete']) ?></a></b></td>
        <td><span class="badge badge-perf" style="font-size:13px;padding:3px 10px;"><?= htmlspecialchars($rec['performance'] ?? '') ?></span></td>
        <td class="hide-mobile"><?= $rec['date'] ? date('d/m/Y', strtotime($rec['date'])) : '-' ?></td>
        <td class="hide-mobile"><?php if (!empty($rec['club'])): ?><a href="?page=recherche&club=<?= urlencode(rtrim($rec['club'], '* ')) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars(mb_substr($rec['club'], 0, 25)) ?></a><?php else: ?>-<?php endif; ?></td>
        <td><a href="?page=recherche&epreuve=<?= urlencode($epFilter) ?>&categorie=<?= urlencode($rec['categorie'] ?? '') ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= htmlspecialchars($rec['categorie'] ?? '') ?></span></a></td>
        <td class="hide-mobile"><a href="?page=recherche&epreuve=<?= urlencode($epFilter) ?>&sexe=<?= urlencode($rec['sexe'] ?? '') ?>" style="text-decoration:none;"><span class="badge badge-<?= strtolower($rec['sexe'] ?? '') ?>"><?= htmlspecialchars($rec['sexe'] ?? '') ?></span></a></td>
        <td class="hide-mobile"><a href="?page=recherche&nationalite=<?= urlencode($rec['nationalite'] ?? '') ?>&epreuve=<?= urlencode($epFilter) ?>" style="color:#c9d1d9;text-decoration:none;"><?= htmlspecialchars($rec['nationalite'] ?? '') ?></a></td>
        <td><?= nivBadgeHtml(highestNiveau($rec['niveaux'] ?? [])) ?></td>
        <td><a href="?page=profil&id=<?= $rec['athlete_id'] ?>&s=records" style="color:#a29bfe;font-size:12px;">Profil</a></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Athlète</th><th>Performance</th><th class="hide-mobile">Date</th><th class="hide-mobile">Club</th><th>Cat</th><th class="hide-mobile">Sexe</th><th class="hide-mobile">NAT</th><th>Niveaux</th><th></th></tr></table>
</div>
<?php else: ?>
<div class="loading-msg">Aucune performance trouvée pour cette épreuve.</div>
<?php endif; ?>

<?php
    // Pagination épreuve
    if ($data['total_pages'] > 1):
        $base = $_GET; unset($base['p']);
?>
<div class="pager">
    <?php if ($p > 1): ?><a href="?<?= http_build_query(array_merge($base, ['p' => $p-1])) ?>">← Précédent</a><?php endif; ?>
    <?php for ($i = max(1,$p-3); $i <= min($data['total_pages'],$p+3); $i++): ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?<?= http_build_query(array_merge($base, ['p' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?<?= http_build_query(array_merge($base, ['p' => $p+1])) ?>">Suivant →</a><?php endif; ?>
    <span style="color:#666;margin-left:10px;">(<?= $data['total_pages'] ?> pages)</span>
</div>
<?php endif; ?>

<?php elseif ($data): ?>
<div class="error"><?= htmlspecialchars($data['error'] ?? 'Épreuve non trouvée') ?></div>
<?php else: ?>
<div class="error">Serveur injoignable.</div>
<?php endif; ?>

<?php
    // ---- MODE RECHERCHE CLASSIQUE (sans épreuve) ----
    else:
        $params['page'] = $p;
        $params['limit'] = 100;
        $url = "$BASE_API/search.php?" . http_build_query($params);
        $data = apiCall($url);

        if ($data && ($data['success'] ?? false)):
            // Stats recherche
            $rchSexe = []; $rchCat = [];
            foreach ($data['athletes'] as $a) {
                $s = $a['sexe'] ?: 'Inconnu'; $rchSexe[$s] = ($rchSexe[$s] ?? 0) + 1;
                $c = $a['categorie'] ?: 'Autre'; $rchCat[$c] = ($rchCat[$c] ?? 0) + 1;
            }
?>

<?php
$clubFilter = trim($_GET['club'] ?? '');
if ($clubFilter !== ''):
?>
<div id="clubDetailPanel" class="club-detail-panel">
    <div class="club-detail-header">
        <h2 id="clubDetailName"></h2>
        <span class="meta-info" id="clubDetailMeta"></span>
        <button class="btn-follow btn-follow-club" id="btnFollowClub" style="display:none;">&#9825; Suivre</button>
        <button onclick="closeClubDetail()" class="btn-close-detail">&times; Fermer</button>
    </div>
    <div class="club-detail-tabs">
        <button class="club-detail-tab active" data-tab="epreuves" onclick="switchClubTab('epreuves')">Épreuves</button>
        <button class="club-detail-tab" data-tab="nationalites" onclick="switchClubTab('nationalites')">Nationalités</button>
        <button class="club-detail-tab" data-tab="stats" onclick="switchClubTab('stats')">Stats</button>
        <button class="club-detail-tab" data-tab="performances" onclick="switchClubTab('performances')">Performances</button>
        <button class="club-detail-tab" data-tab="resume" onclick="switchClubTab('resume')">Resume</button>
    </div>
    <div class="club-search-bar" id="clubSearchBar" style="display:none;padding:8px 16px;">
        <div style="position:relative;">
            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#5a6580;font-size:14px;">&#128269;</span>
            <input type="text" id="clubSearchInput" placeholder="Rechercher un athlète dans ce club..." autocomplete="off" style="width:100%;padding:8px 12px 8px 32px;border-radius:8px;border:1px solid #1e2a3a;background:#0d1117;color:#c9d1d9;font-size:13px;outline:none;transition:border-color .2s;">
        </div>
    </div>
    <div id="clubDetailContent" class="club-detail-content">
        <div class="loading-msg">Chargement...</div>
    </div>
    <div id="clubQR"></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var clubUrl = BASE_API + '/club_stats.php?nom=<?= urlencode($clubFilter) ?>';
    <?php
    foreach (['nationalite','sexe','categorie'] as $_fk) {
        if (!empty($_GET[$_fk])) echo "clubUrl += '&" . $_fk . "=' + encodeURIComponent(" . json_encode($_GET[$_fk]) . ");\n    ";
    }
    ?>
    fetch(clubUrl)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            _fillClubPanel(data, '');
            document.getElementById('clubDetailPanel').classList.add('active');
        });
});
</script>
<?php endif; ?>

<p class="subtitle"><?= number_format($data['total'], 0, ',', ' ') ?> résultats — page <?= $data['page'] ?>/<?= $data['total_pages'] ?></p>

<!-- Graphiques recherche -->
<div class="charts-row" style="margin-bottom:20px;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">M/F</span> Sexe (résultats)</h3><canvas id="rchChartSexe"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">Cat</span> Catégories (résultats)</h3><canvas id="rchChartCat"></canvas></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('rchChartSexe'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($rchSexe as $k => $v) echo "'" . ($k==='M'?'Hommes':($k==='F'?'Femmes':$k)) . "',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($rchSexe)) ?>], backgroundColor: ['#3b82f6','#ec4899','#8b5cf6','#64748b'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } } } }
    });
    new Chart(document.getElementById('rchChartCat'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($rchCat as $k => $v) echo "'$k',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($rchCat)) ?>], backgroundColor: '#34d399', borderRadius: 4, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
});
</script>

<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Nom complet</th><th class="hide-mobile">Naissance</th><th>Cat</th><th class="hide-mobile">Sexe</th><th class="hide-mobile">NAT</th><th>Niveaux</th><th>Records (top 5)</th><th></th><th></th></tr></table>
<table class="bk-table">
    <?php foreach ($data['athletes'] as $idx => $a): ?>
    <tr>
        <td><?= ($p - 1) * 100 + $idx + 1 ?></td>
        <td><b><a href="?page=profil&id=<?= $a['athlete_id'] ?>"><?= htmlspecialchars($a['nom_complet']) ?></a></b></td>
        <td class="hide-mobile"><?= substr($a['date_naissance'] ?? '-', 0, 4) ?: '-' ?></td>
        <td><a href="?page=recherche&categorie=<?= urlencode($a['categorie']) ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= $a['categorie'] ?></span></a></td>
        <td class="hide-mobile"><a href="?page=recherche&sexe=<?= urlencode($a['sexe']) ?>" style="text-decoration:none;"><span class="badge badge-<?= strtolower($a['sexe']) ?>"><?= $a['sexe'] ?></span></a></td>
        <td class="hide-mobile"><a href="?page=recherche&nationalite=<?= urlencode($a['nationalite']) ?>" style="color:#c9d1d9;text-decoration:none;"><?= $a['nationalite'] ?></a></td>
        <td><?= nivBadgeHtml(highestNiveau($a['niveaux'] ?? [])) ?></td>
        <td><?php if (!empty($a['top_records'])):
            foreach ($a['top_records'] as $rec):
                ?><div style="display:flex;align-items:center;gap:4px;margin:2px 0;font-size:11px;">
                    <a href="?page=recherche&epreuve=<?= urlencode($rec['epreuve']) ?>" style="color:#818cf8;white-space:nowrap;text-decoration:none;"><?= htmlspecialchars($rec['epreuve']) ?></a>
                    <span class="perf-val" style="font-size:11px;"><?= htmlspecialchars($rec['performance']) ?></span>
                    <?= nivBadgeHtml(highestNiveau($rec['niveaux'] ?? [])) ?>
                </div><?php
            endforeach;
        elseif (($a['nb_records'] ?? 0) > 0): ?>
            <span class="badge badge-perf"><?= $a['nb_records'] ?></span>
        <?php else: ?>-<?php endif; ?></td>
        <td><a href="?page=profil&id=<?= $a['athlete_id'] ?>&s=records">Profil</a></td>
        <td><button class="btn-cmp-add" data-cmp-ath="<?= $a['athlete_id'] ?>" data-name="<?= htmlspecialchars($a['nom_complet'], ENT_QUOTES) ?>" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">+</button></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Nom complet</th><th class="hide-mobile">Naissance</th><th>Cat</th><th class="hide-mobile">Sexe</th><th class="hide-mobile">NAT</th><th>Niveaux</th><th>Records (top 5)</th><th></th><th></th></tr></table>
</div>
<?php
    // Pagination
    if ($data['total_pages'] > 1):
        $base = $_GET; unset($base['p']);
?>
<div class="pager">
    <?php if ($p > 1): ?><a href="?<?= http_build_query(array_merge($base, ['p' => $p-1])) ?>">← Précédent</a><?php endif; ?>
    <?php for ($i = max(1,$p-3); $i <= min($data['total_pages'],$p+3); $i++): ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?<?= http_build_query(array_merge($base, ['p' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?<?= http_build_query(array_merge($base, ['p' => $p+1])) ?>">Suivant →</a><?php endif; ?>
</div>
<?php endif; ?>

<?php elseif ($data): ?>
<div class="error"><?= htmlspecialchars($data['error'] ?? 'Erreur') ?></div>
<?php else: ?>
<div class="error">Serveur injoignable.</div>
<?php endif; ?>

<?php endif; // fin if hasOnlyEpreuve / else ?>

<?php else: ?>
<p class="subtitle">Entrez au moins un critère et cliquez sur Rechercher.</p>
<?php endif; ?>
</div>


<?php
// ================================================================
//  PROFIL ATHLETE COMPLET
// ================================================================
elseif ($page === 'profil' && $id):
    // Incrementer le compteur de vues (1 seule fois par IP)
    $__ip = $conn->real_escape_string(getVisitorIp());
    $__eid = (int)$id;
    @$conn->query("INSERT IGNORE INTO athlete_vues_ip (ip, athlete_id_ext) VALUES ('$__ip', $__eid)");
    if ($conn->affected_rows > 0) {
        @$conn->query("UPDATE athletes SET vues = vues + 1 WHERE athlete_id_externe = $__eid");
    }
    // Tracking search_tracking → fait côté JS (sendBeacon) après chargement profil

    $data = apiCall("$BASE_API/athlete.php?id=$id");
    $section = $_GET['s'] ?? 'all';

    if ($data && ($data['success'] ?? false)):
        $i = $data['identite'];
?>

<script>
(function(){
    var d = {q:<?= json_encode($i['nom_complet']) ?>,type:'athlete',source:'page_view',entity_id:<?= (int)$id ?>,entity_name:<?= json_encode($i['nom_complet']) ?>,results:1,pg:'profil'};
    try { navigator.sendBeacon(<?= json_encode($BASE_API . '/search_track.php') ?>, JSON.stringify(d)); } catch(e){}
})();
</script>
<div class="profil-header">
    <div>
        <div class="name"><?= htmlspecialchars($i['nom_complet']) ?>
            <button class="btn-cmp-add" data-cmp-ath="<?= $id ?>" data-name="<?= htmlspecialchars($i['nom_complet'], ENT_QUOTES) ?>" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)" style="margin-left:10px;vertical-align:middle;">+</button>
            <button class="btn-follow" id="btnFollow" onclick="toggleFollow(<?= $id ?>)" style="margin-left:8px;vertical-align:middle;">&#9825; Suivre</button>
            <button class="btn-pdf" id="btnPdf" onclick="downloadPdf(<?= $id ?>, '<?= htmlspecialchars($i['nom_complet'], ENT_QUOTES) ?>')" style="margin-left:8px;vertical-align:middle;">&#128196; PDF</button>
        </div>
        <div class="meta">
            <a href="?page=recherche&sexe=<?= urlencode($i['sexe']) ?>" style="text-decoration:none;"><span class="badge badge-<?= strtolower($i['sexe']) ?>"><?= $i['sexe'] === 'M' ? 'Homme' : 'Femme' ?></span></a>
            <a href="?page=recherche&categorie=<?= urlencode($i['categorie']) ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= htmlspecialchars($i['categorie']) ?></span></a>
            <?= $i['nationalite'] ? '<a href="?page=recherche&nationalite=' . urlencode($i['nationalite']) . '" style="text-decoration:none;"><span class="badge" style="background:#30363d;">' . htmlspecialchars($i['nationalite']) . '</span></a>' : '' ?>
            <br>
            <?php if ($i['date_naissance']): ?>
                <b>Naissance :</b> <?= substr($i['date_naissance'], 0, 4) ?>
                <?= $i['lieu_naissance'] ? ' — <a href="?page=villes&open=' . urlencode($i['lieu_naissance']) . '" style="color:#a29bfe;text-decoration:none;">' . htmlspecialchars($i['lieu_naissance']) . '</a>' : '' ?><br>
            <?php endif; ?>
            <?php if ($i['taille_cm']): ?><b>Taille :</b> <?= $i['taille_cm'] ?> cm | <?php endif; ?>
            <?php if ($i['poids_kg']): ?><b>Poids :</b> <?= $i['poids_kg'] ?> kg | <?php endif; ?>
            <?php if ($i['licence']): ?><b>Licence :</b> <?= htmlspecialchars($i['licence']) ?><?php endif; ?>
            <br><b>ID athle.fr :</b> <?= $i['athlete_id'] ?>
            &nbsp;|&nbsp; <a href="pages/profil.php?id=<?= $i['id_athlete'] ?>" target="_blank" style="color:#a29bfe;text-decoration:none;font-size:13px;">&#127760; Profil public</a>
        </div>
    </div>
</div>

<div class="chart-card" style="margin:16px 0;border-left:3px solid #6c5ce7;" id="bioCard">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#6c5ce720;color:#a29bfe;">&#128221;</span> R&eacute;sum&eacute;</h3>
    <div id="bioYearSelector" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:center;"></div>
    <p id="bioText" style="color:#c8cfd8;line-height:1.8;font-size:14px;margin:0;">Chargement...</p>
    <button onclick="navigator.clipboard.writeText(document.getElementById('bioText').textContent).then(function(){alert('R\u00e9sum\u00e9 copi\u00e9 !')})" style="margin-top:12px;background:#253049;color:#a29bfe;border:1px solid #6c5ce740;border-radius:8px;padding:6px 14px;cursor:pointer;font-size:12px;">&#128203; Copier le texte</button>
</div>
<script>
var ATHLETE_DATA = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
var _bioSelectedYears = [];
var _bioAvailableYears = [];

function _bioCollectYears(d) {
    var ys = {};
    (d.resultats||[]).forEach(function(r){if(r.annee)ys[r.annee]=1;});
    (d.progressions||[]).forEach(function(p){if(p.annee)ys[p.annee]=1;});
    (d.podiums||[]).forEach(function(p){if(p.annee)ys[p.annee]=1;});
    (d.medailles||[]).forEach(function(m){if(m.annee)ys[m.annee]=1;});
    (d.niveaux||[]).forEach(function(n){if(n.annee)ys[n.annee]=1;});
    (d.selections||[]).forEach(function(s){if(s.date){var y=parseInt(s.date.substring(0,4));if(y>0)ys[y]=1;}});
    (d.records||[]).forEach(function(r){if(r.date){var y=parseInt(r.date.substring(0,4));if(y>0)ys[y]=1;}});
    return Object.keys(ys).map(Number).sort(function(a,b){return a-b;});
}

function _bioRenderYearSelector() {
    var c = document.getElementById('bioYearSelector'); if(!c) return;
    var isTotal = _bioSelectedYears.length === 0;
    var h = '<button onclick="_bioSelectTotal()" style="padding:6px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid '+(isTotal?'#6c5ce7':'#1a2540')+';background:'+(isTotal?'linear-gradient(135deg,#6c5ce7,#5541d0)':'#080c14')+';color:'+(isTotal?'#fff':'#8b949e')+';">Total</button>';
    h += '<span style="color:#253049;font-size:18px;">|</span>';
    _bioAvailableYears.forEach(function(y){
        var sel = _bioSelectedYears.indexOf(y)!==-1;
        h += '<button onclick="_bioToggleYear('+y+')" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid '+(sel?'#6c5ce7':'#1a2540')+';background:'+(sel?'linear-gradient(135deg,#6c5ce7,#5541d0)':'#080c14')+';color:'+(sel?'#fff':'#8b949e')+';transition:all .2s;">'+y+'</button>';
    });
    if(_bioSelectedYears.length>0) h+='<span style="color:#5a6580;font-size:12px;margin-left:4px;">'+_bioSelectedYears.length+'/6 ann\u00e9es</span>';
    c.innerHTML = h;
}

function _bioSelectTotal(){_bioSelectedYears=[];_bioRenderYearSelector();_bioRebuild();}
function _bioToggleYear(y){
    var idx=_bioSelectedYears.indexOf(y);
    if(idx!==-1){_bioSelectedYears.splice(idx,1);}
    else{if(_bioSelectedYears.length>=6){alert('Maximum 6 ann\u00e9es');return;}_bioSelectedYears.push(y);_bioSelectedYears.sort(function(a,b){return a-b;});}
    _bioRenderYearSelector();_bioRebuild();
}
function _bioRebuild(){var el=document.getElementById('bioText');if(el)el.textContent=buildAthleteBio(ATHLETE_DATA,_bioSelectedYears);}

function buildAthleteBio(data, selectedYears) {
    var filterByYear = selectedYears.length > 0;
    var yearSet = {}; selectedYears.forEach(function(y){yearSet[y]=true;});
    function inYears(a){if(!filterByYear)return true;return yearSet[a]===true;}
    function dateInYears(d){if(!filterByYear)return true;if(!d)return false;var y=parseInt(d.substring(0,4));return yearSet[y]===true;}

    var i = data.identite;
    var eF = i.sexe==='F'?'e':'';
    var ilElle = i.sexe==='M'?'Il':'Elle';
    var ilElleMin = i.sexe==='M'?'il':'elle';
    var sonSa = i.sexe==='M'?'son':'sa';
    var bio = [];

    var natMap = {'FRA':'fran\u00e7ais'+eF,'MAR':'marocain'+eF,'SEN':'s\u00e9n\u00e9galais'+eF,'CMR':'camerounais'+eF,'ALG':'alg\u00e9rien'+(i.sexe==='F'?'ne':''),'TUN':'tunisien'+(i.sexe==='F'?'ne':''),'BEL':'belge','SUI':'suisse','CIV':'ivoirien'+(i.sexe==='F'?'ne':''),'GBR':'britannique','USA':'am\u00e9ricain'+eF,'ESP':'espagnol'+eF,'ITA':'italien'+(i.sexe==='F'?'ne':''),'POR':'portugais'+eF,'GER':'allemand'+eF,'BRA':'br\u00e9silien'+(i.sexe==='F'?'ne':''),'JAM':'jama\u00efcain'+eF,'HAI':'ha\u00eftien'+(i.sexe==='F'?'ne':''),'COD':'congolais'+eF,'COG':'congolais'+eF,'MLI':'malien'+(i.sexe==='F'?'ne':''),'GIN':'guin\u00e9en'+(i.sexe==='F'?'ne':''),'GAB':'gabonais'+eF,'BUR':'burkinab\u00e8','NIG':'nig\u00e9rien'+(i.sexe==='F'?'ne':''),'BEN':'b\u00e9ninois'+eF,'TOG':'togolais'+eF,'RWA':'rwandais'+eF,'MAD':'malgache','LUX':'luxembourgeois'+eF,'NED':'n\u00e9erlandais'+eF,'ROU':'roumain'+eF,'POL':'polonais'+eF,'GRE':'grec'+(i.sexe==='F'?'que':''),'TUR':'turc'+(i.sexe==='F'?'que':''),'KEN':'k\u00e9nyan'+eF,'ETH':'\u00e9thiopien'+(i.sexe==='F'?'ne':''),'RSA':'sud-africain'+eF,'JPN':'japonais'+eF,'CHN':'chinois'+eF,'AUS':'australien'+(i.sexe==='F'?'ne':''),'CAN':'canadien'+(i.sexe==='F'?'ne':''),'MEX':'mexicain'+eF,'COL':'colombien'+(i.sexe==='F'?'ne':''),'ARG':'argentin'+eF,'CHI':'chilien'+(i.sexe==='F'?'ne':''),'CUB':'cubain'+eF,'DOM':'dominicain'+eF,'TRI':'trinidadien'+(i.sexe==='F'?'ne':''),'BAH':'baham\u00e9en'+(i.sexe==='F'?'ne':'')};
    var catMap = {'SE':'Senior','ES':'Espoir','JU':'Junior','CA':'Cadet'+(i.sexe==='F'?'te':''),'MI':'Minime','BE':'Benjamin'+eF,'PO':'Poussin'+eF,'EA':'\u00c9veil athl\u00e9tique','MA':'Master','V1':'V\u00e9t\u00e9ran','V2':'V\u00e9t\u00e9ran','V3':'V\u00e9t\u00e9ran','V4':'V\u00e9t\u00e9ran','V5':'V\u00e9t\u00e9ran'};
    var nivMap = {'N1':'Niveau National 1 (\u00c9lite)','N2':'Niveau National 2','N3':'Niveau National 3','N4':'Niveau National 4','R1':'Niveau R\u00e9gional 1','R2':'Niveau R\u00e9gional 2','R3':'Niveau R\u00e9gional 3','R4':'Niveau R\u00e9gional 4','R5':'Niveau R\u00e9gional 5','R6':'Niveau R\u00e9gional 6','D1':'Niveau D\u00e9partemental 1','D2':'Niveau D\u00e9partemental 2','D3':'Niveau D\u00e9partemental 3','D4':'Niveau D\u00e9partemental 4','D5':'Niveau D\u00e9partemental 5','D6':'Niveau D\u00e9partemental 6','D7':'Niveau D\u00e9partemental 7','IR':'Interr\u00e9gional','IE':'International \u00c9lite'};

    // Filtrer les donn\u00e9es par ann\u00e9e
    var fResultats = (data.resultats||[]).filter(function(r){return inYears(r.annee);});
    var fProgressions = (data.progressions||[]).filter(function(p){return inYears(p.annee);});
    var fPodiums = (data.podiums||[]).filter(function(p){return inYears(p.annee);});
    var fMedailles = (data.medailles||[]).filter(function(m){return inYears(m.annee);});
    var fNiveaux = (data.niveaux||[]).filter(function(n){return inYears(n.annee);});
    var fSelections = (data.selections||[]).filter(function(s){return dateInYears(s.date);});
    var fRecords = (data.records||[]).filter(function(r){return dateInYears(r.date);});
    var fClubs = (data.clubs||[]).filter(function(c){
        if(!filterByYear)return true;
        for(var yy in yearSet){var y=parseInt(yy);var d=c.annee_debut||0;var f=c.annee_fin||9999;if(y>=d&&y<=f)return true;}
        return false;
    });

    // Ann\u00e9es d'activit\u00e9
    var derniereAnnee=0, premiereAnnee=9999;
    fResultats.forEach(function(r){if(r.annee>derniereAnnee)derniereAnnee=r.annee;if(r.annee>0&&r.annee<premiereAnnee)premiereAnnee=r.annee;});
    fProgressions.forEach(function(p){if(p.annee>derniereAnnee)derniereAnnee=p.annee;if(p.annee>0&&p.annee<premiereAnnee)premiereAnnee=p.annee;});
    fPodiums.forEach(function(p){if(p.annee>derniereAnnee)derniereAnnee=p.annee;if(p.annee>0&&p.annee<premiereAnnee)premiereAnnee=p.annee;});
    fMedailles.forEach(function(m){if(m.annee>derniereAnnee)derniereAnnee=m.annee;if(m.annee>0&&m.annee<premiereAnnee)premiereAnnee=m.annee;});
    fClubs.forEach(function(c){if(c.annee_debut&&c.annee_debut<premiereAnnee)premiereAnnee=c.annee_debut;});
    if(premiereAnnee===9999)premiereAnnee=0;
    var currentYear=new Date().getFullYear();
    var carriereTerminee=(derniereAnnee>0&&(currentYear-derniereAnnee)>2);

    // \u00c2ge
    var age=null;
    if(i.date_naissance){var bd=new Date(i.date_naissance);var td=new Date();age=td.getFullYear()-bd.getFullYear();var mo=td.getMonth()-bd.getMonth();if(mo<0||(mo===0&&td.getDate()<bd.getDate()))age--;}
    else if(i.annee_naissance){age=currentYear-i.annee_naissance;}

    // === 1. IDENTIT\u00c9 ===
    var intro = i.nom_complet;
    if(carriereTerminee){intro+=' est un'+eF+' ancien'+(i.sexe==='F'?'ne':'')+' athl\u00e8te';}
    else{intro+=' est un'+eF+' athl\u00e8te';}
    if(i.nationalite&&natMap[i.nationalite]){intro+=' '+natMap[i.nationalite];}
    else if(i.nationalite){intro+=' de nationalit\u00e9 '+i.nationalite;}
    if(i.categorie&&catMap[i.categorie]){intro+=' \u00e9voluant en cat\u00e9gorie '+catMap[i.categorie];}
    if(i.date_naissance||i.annee_naissance){
        intro+=', n\u00e9'+eF;
        intro+=' en '+(i.date_naissance?i.date_naissance.substring(0,4):i.annee_naissance);
        if(i.lieu_naissance)intro+=' \u00e0 '+i.lieu_naissance;
        if(age)intro+=' ('+age+' ans)';
    }
    if(i.taille_cm&&i.poids_kg){intro+=', mesurant '+(i.taille_cm/100).toFixed(2).replace('.',',')+' m pour '+i.poids_kg+' kg';}
    else if(i.taille_cm){intro+=', mesurant '+(i.taille_cm/100).toFixed(2).replace('.',',')+' m';}
    intro+='.';
    bio.push(intro);

    // === 2. CONTEXTE ANN\u00c9E ===
    if(filterByYear){
        if(selectedYears.length===1)bio.push('Ce r\u00e9sum\u00e9 couvre la saison '+selectedYears[0]+'.');
        else bio.push('Ce r\u00e9sum\u00e9 couvre les saisons '+selectedYears.join(', ')+'.');
    }

    // === 3. CARRI\u00c8RE ET CLUBS ===
    if(fClubs.length>0){
        var nbClubs=fClubs.length, clubRecent=fClubs[0], clubAncien=fClubs[nbClubs-1];
        var dureeCarriere=(premiereAnnee&&derniereAnnee)?(derniereAnnee-premiereAnnee):0;
        if(filterByYear){
            if(nbClubs===1)bio.push(ilElle+' \u00e9voluait au sein du club '+clubRecent.nom_club+'.');
            else{var cn=fClubs.map(function(c){return c.nom_club;});bio.push(ilElle+' \u00e9voluait au sein de '+nbClubs+' clubs : '+(cn.length<=3?cn.join(', '):cn.slice(0,3).join(', ')+' et '+(nbClubs-3)+' autre'+(nbClubs-3>1?'s':''))+'.');}
        }else{
            var uneSeuleAnnee=(dureeCarriere===0&&premiereAnnee>0);
            if(uneSeuleAnnee){
                var pc=ilElle+' n\'a effectu\u00e9 qu\'une seule saison en '+premiereAnnee;
                if(nbClubs===1)pc+=' au sein du club '+clubRecent.nom_club;
                pc+='.'; bio.push(pc);
            }else if(carriereTerminee){
                var pc=ilElle+' a men\u00e9 '+sonSa+' carri\u00e8re';
                if(premiereAnnee)pc+=' de '+premiereAnnee+' \u00e0 '+derniereAnnee;
                if(dureeCarriere>0)pc+=' ('+dureeCarriere+' ans d\'activit\u00e9)';
                if(nbClubs===1)pc+=' au sein du club '+clubRecent.nom_club;
                else pc+=', passant par '+nbClubs+' clubs';
                pc+='. '+ilElle+' a mis fin \u00e0 '+sonSa+' carri\u00e8re sportive en '+derniereAnnee+'.';
                bio.push(pc);
            }else{
                var pc;
                if(nbClubs===1){pc=ilElle+' \u00e9volue au '+clubRecent.nom_club;if(clubRecent.annee_debut)pc+=' depuis '+clubRecent.annee_debut;pc+='.';}
                else{
                    pc='Form\u00e9'+eF+' au '+clubAncien.nom_club;
                    if(clubAncien.annee_debut)pc+=' d\u00e8s '+clubAncien.annee_debut;
                    pc+=', '+ilElleMin+' \u00e9volue d\u00e9sormais au '+clubRecent.nom_club;
                    if(clubRecent.annee_debut)pc+=' depuis '+clubRecent.annee_debut;
                    if(nbClubs>2)pc+=' apr\u00e8s \u00eatre pass\u00e9'+eF+' par '+(nbClubs-2)+' autre'+(nbClubs-2>1?'s':'')+' club'+(nbClubs-2>1?'s':'');
                    pc+='.';
                }
                if(premiereAnnee){var dur=currentYear-premiereAnnee;if(dur>1)pc+=' Sa carri\u00e8re s\'\u00e9tend sur '+dur+' saisons.';else if(dur<=1)pc+=' '+ilElle+' en est \u00e0 '+sonSa+' premi\u00e8re saison.';}
                bio.push(pc);
            }
        }
    }

    // === 4. DISCIPLINES ET RECORDS ===
    var recordsToUse = filterByYear ? fRecords : (data.records||[]);
    if(recordsToUse.length>0){
        var recsByEp={};
        recordsToUse.forEach(function(r){if(r.epreuve&&r.performance_brut)recsByEp[r.epreuve]=r;});
        var epNames=Object.keys(recsByEp), nbEp=epNames.length;
        if(nbEp>0){
            var pr;
            if(nbEp===1){
                var rec=recsByEp[epNames[0]];
                pr=ilElle+' est sp\u00e9cialis\u00e9'+eF+' sur le '+epNames[0]+' o\u00f9 '+ilElleMin+' d\u00e9tient un record personnel de '+rec.performance_brut;
                if(rec.lieu)pr+=' r\u00e9alis\u00e9 \u00e0 '+rec.lieu;
                pr+='.';
            }else if(nbEp<=3){
                var rd=[];for(var ep in recsByEp){rd.push(recsByEp[ep].performance_brut+' au '+ep+(recsByEp[ep].lieu?' (\u00e0 '+recsByEp[ep].lieu+')':''));}
                pr=ilElle+' est sp\u00e9cialis\u00e9'+eF+' en '+epNames.join(' et ')+', avec des records personnels de '+rd.join(', ')+'.';
            }else{
                var top=epNames.slice(0,4);var rd=top.map(function(ep){return recsByEp[ep].performance_brut+' au '+ep;});
                pr='Polyvalent'+eF+' avec '+nbEp+' disciplines \u00e0 '+sonSa+' actif, '+ilElleMin+' affiche notamment '+rd.join(', ')+'.';
            }
            bio.push(pr);
        }
    }

    // === 5. M\u00c9DAILLES ===
    if(fMedailles.length>0){
        var medOr=0,medArgent=0,medBronze=0,competitions={},epreuvesMed={};
        fMedailles.forEach(function(m){
            if(m.type==='or')medOr++;else if(m.type==='argent')medArgent++;else if(m.type==='bronze')medBronze++;
            if(m.competition)competitions[m.competition]=1;if(m.epreuve)epreuvesMed[m.epreuve]=1;
        });
        var totalMed=medOr+medArgent+medBronze;
        if(totalMed>0){
            var pMed=filterByYear?'Sur cette p\u00e9riode, '+ilElleMin+' a remport\u00e9 '+totalMed+' m\u00e9daille'+(totalMed>1?'s':''):'Son palmar\u00e8s compte '+totalMed+' m\u00e9daille'+(totalMed>1?'s':'');
            var detMed=[];if(medOr>0)detMed.push(medOr+' en or');if(medArgent>0)detMed.push(medArgent+' en argent');if(medBronze>0)detMed.push(medBronze+' en bronze');
            if(detMed.length>1){var last=detMed.pop();pMed+=', dont '+detMed.join(', ')+' et '+last;}
            else if(detMed.length===1)pMed+=', dont '+detMed[0];
            var compNames=Object.keys(competitions);
            if(compNames.length===1)pMed+=', obtenue'+(totalMed>1?'s':'')+' lors des '+compNames[0];
            else if(compNames.length<=3&&compNames.length>1){var lc=compNames.pop();pMed+=', remport\u00e9e'+(totalMed>1?'s':'')+' aux '+compNames.join(', ')+' et '+lc;}
            else if(compNames.length>3)pMed+=', d\u00e9cern\u00e9e'+(totalMed>1?'s':'')+' lors de '+compNames.length+' comp\u00e9titions';
            var epMedNames=Object.keys(epreuvesMed);
            if(epMedNames.length>0&&epMedNames.length<=3)pMed+=' en '+epMedNames.join(', ');
            pMed+='.'; bio.push(pMed);
        }
    }

    // === 6. PODIUMS ===
    if(fPodiums.length>0){
        var nbPod=fPodiums.length,p1=0,p2=0,p3=0,podEp={},podNiv={};
        fPodiums.forEach(function(pod){var rg=pod.rang||0;if(rg===1)p1++;else if(rg===2)p2++;else if(rg===3)p3++;if(pod.epreuve)podEp[pod.epreuve]=1;if(pod.niveau_competition)podNiv[pod.niveau_competition]=1;});
        var pPod=filterByYear?ilElle+' est mont\u00e9'+eF+' sur '+nbPod+' podium'+(nbPod>1?'s':'')+' durant cette p\u00e9riode':ilElle+' est mont\u00e9'+eF+' sur '+nbPod+' podium'+(nbPod>1?'s':'');
        var detPod=[];if(p1>0)detPod.push(p1+' premi\u00e8re'+(p1>1?'s':'')+' place'+(p1>1?'s':''));if(p2>0)detPod.push(p2+' deuxi\u00e8me'+(p2>1?'s':'')+' place'+(p2>1?'s':''));if(p3>0)detPod.push(p3+' troisi\u00e8me'+(p3>1?'s':'')+' place'+(p3>1?'s':''));
        if(detPod.length>0){var ldp=detPod.pop();pPod+=' avec '+(detPod.length>0?detPod.join(', ')+' et '+ldp:ldp);}
        var podEpList=Object.keys(podEp);
        if(podEpList.length>0&&podEpList.length<=4)pPod+=', en '+podEpList.join(', ');
        else if(podEpList.length>4)pPod+=', r\u00e9partis sur '+podEpList.length+' \u00e9preuves';
        pPod+='.'; bio.push(pPod);
    }

    // === 7. S\u00c9LECTIONS ===
    if(fSelections.length>0){
        var nbSel=fSelections.length,selComp={},selEp={};
        fSelections.forEach(function(s){if(s.competition)selComp[s.competition]=1;if(s.epreuve)selEp[s.epreuve]=1;});
        var pSel=ilElle+' a \u00e9t\u00e9 s\u00e9lectionn\u00e9'+eF+' '+nbSel+' fois en \u00e9quipe nationale';
        var scl=Object.keys(selComp);if(scl.length>0&&scl.length<=3)pSel+=' pour '+scl.join(', ');else if(scl.length>3)pSel+=' pour '+scl.length+' comp\u00e9titions';
        var sel=Object.keys(selEp);if(sel.length>0&&sel.length<=3)pSel+=' en '+sel.join(', ');
        pSel+='.'; bio.push(pSel);
    }

    // === 8. ACTIVIT\u00c9 EN COMP\u00c9TITION ===
    if(fResultats.length>0){
        var nbRes=fResultats.length,anneesRes={},villesRes={},epreuvesRes={},bestPlace=999;
        fResultats.forEach(function(r){if(r.annee)anneesRes[r.annee]=1;if(r.lieu)villesRes[r.lieu]=1;if(r.epreuve)epreuvesRes[r.epreuve]=(epreuvesRes[r.epreuve]||0)+1;if(r.place&&r.place>0&&r.place<bestPlace)bestPlace=r.place;});
        var nbVilles=Object.keys(villesRes).length, nbEpRes=Object.keys(epreuvesRes).length;
        var annees=Object.keys(anneesRes).sort();
        var pRes=filterByYear?'Sur cette p\u00e9riode, '+nbRes+' participation'+(nbRes>1?'s':'')+' en comp\u00e9tition '+(nbRes>1?'sont':'est')+' recens\u00e9e'+(nbRes>1?'s':''):'Au total, '+nbRes+' participation'+(nbRes>1?'s':'')+' en comp\u00e9tition '+(nbRes>1?'sont':'est')+' recens\u00e9e'+(nbRes>1?'s':'');
        if(!filterByYear){if(annees.length>=2)pRes+=' sur la p\u00e9riode '+annees[0]+'-'+annees[annees.length-1];else if(annees.length===1)pRes+=' en '+annees[0];}
        if(nbEpRes>1)pRes+=', couvrant '+nbEpRes+' \u00e9preuves diff\u00e9rentes';
        if(nbVilles>1){pRes+=', \u00e0 travers '+nbVilles+' villes';var vl=Object.keys(villesRes);if(vl.length<=5)pRes+=' ('+vl.join(', ')+')';}
        else if(nbVilles===1)pRes+=' \u00e0 '+Object.keys(villesRes)[0];
        pRes+='.'; bio.push(pRes);
        if(bestPlace<999&&bestPlace<=10)bio.push('Sa meilleure place obtenue est la '+bestPlace+(bestPlace===1?'\u00e8re':'\u00e8me')+' position.');
    }

    // === 9. MEILLEURES PERFORMANCES ===
    if(fProgressions.length>0){
        var progByEp={};
        fProgressions.forEach(function(p){if(p.epreuve&&p.performance_brut){if(!progByEp[p.epreuve])progByEp[p.epreuve]=[];progByEp[p.epreuve].push(p);}});
        var progEpNames=Object.keys(progByEp);
        if(progEpNames.length>0){
            var bestPerfs=[];
            progEpNames.forEach(function(ep){var perfs=progByEp[ep];var best=perfs[0];perfs.forEach(function(p){if(p.performance&&p.performance<best.performance)best=p;});bestPerfs.push({epreuve:ep,perf:best.performance_brut,lieu:best.lieu});});
            if(bestPerfs.length<=4){var pp=bestPerfs.map(function(bp){return bp.perf+' au '+bp.epreuve+(bp.lieu?' \u00e0 '+bp.lieu:'');});bio.push('Ses meilleures performances incluent '+pp.join(', ')+'.');}
            else{var pp=bestPerfs.slice(0,4).map(function(bp){return bp.perf+' au '+bp.epreuve;});bio.push('Parmi ses meilleures performances, on note '+pp.join(', ')+', sur un total de '+progEpNames.length+' \u00e9preuves.');}
        }
    }

    // === 10. NIVEAUX DE PERFORMANCE ===
    if(fNiveaux.length>0){
        var meilleurNiv=null,meilleurPts=0;
        fNiveaux.forEach(function(niv){if((niv.points_niveau||0)>meilleurPts){meilleurPts=niv.points_niveau;meilleurNiv=niv;}});
        if(!meilleurNiv)meilleurNiv=fNiveaux[0];
        var nivNom=nivMap[meilleurNiv.code_niveau]||meilleurNiv.code_niveau;
        var pNiv='En termes de classement, '+ilElleMin+' a atteint le '+nivNom;
        if(meilleurNiv.annee)pNiv+=' en '+meilleurNiv.annee;
        if(meilleurPts>0)pNiv+=' avec '+meilleurPts+' points';
        if(meilleurNiv.club)pNiv+=' sous les couleurs du '+meilleurNiv.club;
        pNiv+='.';
        if(fNiveaux.length>1){var allNiv=fNiveaux.map(function(n){return(nivMap[n.code_niveau]||n.code_niveau)+' ('+n.annee+')';});pNiv+=' Les diff\u00e9rents niveaux atteints sont : '+allNiv.join(', ')+'.';}
        if(meilleurNiv.performances&&meilleurNiv.performances.length>0){var np=meilleurNiv.performances.slice(0,3).map(function(p){return(p.performance_brut||p.performance)+' en '+p.epreuve;});pNiv+=' Les performances correspondantes incluent '+np.join(', ')+'.';}
        bio.push(pNiv);
    }

    // === 11. CONCLUSION ===
    if(!filterByYear&&bio.length>2&&carriereTerminee){
        bio.push(i.nom_complet+' laisse derri\u00e8re '+(i.sexe==='M'?'lui':'elle')+' un parcours riche dans l\'athl\u00e9tisme.');
    }

    return bio.join(' ');
}

document.addEventListener('DOMContentLoaded', function(){
    _bioAvailableYears = _bioCollectYears(ATHLETE_DATA);
    _bioRenderYearSelector();
    _bioRebuild();
});
</script>

<div class="section-tabs">
    <a href="?page=profil&id=<?= $id ?>&s=all" class="<?= $section === 'all' ? 'active' : '' ?>">Tout</a>
    <a href="?page=profil&id=<?= $id ?>&s=clubs" class="<?= $section === 'clubs' ? 'active' : '' ?>">Clubs<span class="count"><?= count($data['clubs']) ?></span></a>
    <a href="?page=profil&id=<?= $id ?>&s=medailles" class="<?= $section === 'medailles' ? 'active' : '' ?>">Médailles<span class="count"><?= count($data['medailles']) ?></span></a>
    <a href="?page=profil&id=<?= $id ?>&s=records" class="<?= $section === 'records' ? 'active' : '' ?>">Records<span class="count"><?= count($data['records']) ?></span></a>
    <a href="?page=profil&id=<?= $id ?>&s=progressions" class="<?= $section === 'progressions' ? 'active' : '' ?>">Progressions<span class="count"><?= count($data['progressions']) ?></span></a>
    <a href="?page=profil&id=<?= $id ?>&s=podiums" class="<?= $section === 'podiums' ? 'active' : '' ?>">Podiums<span class="count"><?= count($data['podiums']) ?></span></a>
    <a href="?page=profil&id=<?= $id ?>&s=resultats" class="<?= $section === 'resultats' ? 'active' : '' ?>">Résultats<span class="count"><?= count($data['resultats']) ?></span></a>
    <a href="?page=profil&id=<?= $id ?>&s=selections" class="<?= $section === 'selections' ? 'active' : '' ?>">Sélections<span class="count"><?= count($data['selections']) ?></span></a>
    <a href="?page=profil&id=<?= $id ?>&s=niveaux" class="<?= $section === 'niveaux' ? 'active' : '' ?>">Niveaux<span class="count"><?= count($data['niveaux']) ?></span></a>
</div>

<?php
// ---- GRAPHIQUES PROFIL ----
if ($section === 'all' || $section === 'progressions' || $section === 'medailles' || $section === 'resultats' || $section === 'records'):
    // Progressions par epreuve et par annee (meilleure perf/annee)
    $progByEpreuve = [];
    foreach ($data['progressions'] as $pr) {
        if (empty($pr['epreuve']) || !$pr['performance']) continue;
        $ep = $pr['epreuve'];
        $an = $pr['annee'];
        if (!isset($progByEpreuve[$ep])) $progByEpreuve[$ep] = [];
        if (!isset($progByEpreuve[$ep][$an]) || $pr['performance'] < $progByEpreuve[$ep][$an]['perf']) {
            $progByEpreuve[$ep][$an] = ['perf' => $pr['performance'], 'brut' => $pr['performance_brut']];
        }
    }
    // Toutes les progressions detaillees par epreuve (chaque perf avec date)
    $progDetail = [];
    foreach ($data['progressions'] as $pr) {
        if (empty($pr['epreuve']) || !$pr['performance']) continue;
        $ep = $pr['epreuve'];
        if (!isset($progDetail[$ep])) $progDetail[$ep] = [];
        $progDetail[$ep][] = ['perf' => $pr['performance'], 'brut' => $pr['performance_brut'], 'date' => $pr['date'], 'annee' => $pr['annee'], 'lieu' => $pr['lieu'] ?? '', 'niveaux' => $pr['niveaux'] ?? []];
    }
    // Trier chaque epreuve par date
    foreach ($progDetail as $ep => &$arr) {
        usort($arr, function($a, $b) { return strcmp($a['date'], $b['date']); });
    }
    unset($arr);
    // Garder toutes les epreuves (meme avec 1 annee)
    // Toutes les annees
    $allYears = [];
    foreach ($progByEpreuve as $ep => $annees) { foreach ($annees as $an => $v) $allYears[$an] = true; }
    ksort($allYears);
    $yearLabels = array_keys($allYears);
    // Liste des epreuves pour le selecteur
    $progEpreuvesList = array_keys($progByEpreuve);
    sort($progEpreuvesList);

    // Medailles par type
    $medTypes = ['or' => 0, 'argent' => 0, 'bronze' => 0];
    foreach ($data['medailles'] as $m) {
        $t = $m['type'] ?? '';
        if (isset($medTypes[$t])) $medTypes[$t]++;
    }

    // Resultats par annee
    $resByYear = [];
    foreach ($data['resultats'] as $r) {
        $an = $r['annee'] ?? 0;
        if ($an > 0) $resByYear[$an] = ($resByYear[$an] ?? 0) + 1;
    }
    ksort($resByYear);

    $colors = ['#3b82f6','#ec4899','#10b981','#f59e0b','#8b5cf6','#06b6d4','#ef4444','#84cc16','#f472b6','#a78bfa'];
?>

<?php if (!empty($progByEpreuve) || !empty($progDetail) || array_sum($medTypes) > 0 || !empty($resByYear)): ?>

<?php if (!empty($progDetail)): ?>
<div class="chart-card" style="margin:20px 0;">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:14px;">
        <h3 style="margin:0;"><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#128200;</span> <span id="profilProgTitle">Progression par discipline</span></h3>
        <select id="profilDiscSelect" onchange="buildProfilProgChart(this.value)" style="background:#181f2e;color:#e0e6ed;border:1px solid #253049;border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer;min-width:180px;">
            <option value="">-- Toutes les disciplines --</option>
            <?php foreach ($progEpreuvesList as $ep): ?>
            <option value="<?= htmlspecialchars($ep) ?>"><?= htmlspecialchars($ep) ?> (<?= count($progDetail[$ep]) ?>)</option>
            <?php endforeach; ?>
        </select>
        <span style="color:#5a6580;font-size:13px;"><?= count($progEpreuvesList) ?> disciplines</span>
    </div>
    <canvas id="profilProgChart" style="max-height:450px;"></canvas>
    <div id="profilProgTable" style="margin-top:14px;"></div>
</div>
<?php endif; ?>

<div class="charts-row-3" style="margin:20px 0;">
    <?php if (array_sum($medTypes) > 0): ?>
    <div class="chart-card">
        <h3><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#127942;</span> Médailles</h3>
        <canvas id="profilMedChart"></canvas>
    </div>
    <?php endif; ?>
    <?php if (!empty($resByYear)): ?>
    <div class="chart-card"<?= array_sum($medTypes) > 0 ? '' : ' style="grid-column:span 2;"' ?>>
        <h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">&#128197;</span> Résultats par année</h3>
        <canvas id="profilResChart"></canvas>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.color = '#8892a8';
    Chart.defaults.borderColor = '#1e2a3a';
    var profColors = <?= json_encode($colors) ?>;

    <?php if (!empty($progDetail)): ?>
    // Donnees de progression detaillees par discipline
    var profilProgDetail = <?= json_encode($progDetail) ?>;
    var profilProgByYear = <?= json_encode($progByEpreuve) ?>;
    var profilProgChart = null;

    window.buildProfilProgChart = function(discipline) {
        var canvas = document.getElementById('profilProgChart');
        var tableDiv = document.getElementById('profilProgTable');
        if (!canvas) return;
        if (profilProgChart) profilProgChart.destroy();

        var isDistance = /poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon/i.test(discipline || '');

        if (discipline && profilProgDetail[discipline]) {
            // === Vue UNE discipline : chaque perf avec sa date ===
            document.getElementById('profilProgTitle').textContent = 'Progression — ' + discipline;
            var pts = profilProgDetail[discipline];
            var labels = pts.map(function(p) { return p.date || p.annee; });
            var dataPerf = pts.map(function(p) { return p.perf; });
            var dataBrut = pts.map(function(p) { return p.brut; });
            var dataLieu = pts.map(function(p) { return p.lieu || ''; });

            // Detecter direction pour cette discipline
            var isDist = /poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon/i.test(discipline);

            profilProgChart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: discipline,
                        data: dataPerf,
                        borderColor: profColors[0],
                        backgroundColor: profColors[0] + '33',
                        tension: 0.3,
                        pointRadius: 6,
                        pointHoverRadius: 10,
                        borderWidth: 3,
                        fill: true,
                        spanGaps: true
                    }]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: function(items) { return items[0].label; },
                                label: function(ctx) {
                                    var b = dataBrut[ctx.dataIndex] || ctx.parsed.y;
                                    var l = dataLieu[ctx.dataIndex];
                                    return discipline + ': ' + b + (l ? ' (' + l + ')' : '');
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { color: '#1e2a3a' }, ticks: { maxRotation: 45, font: { size: 11 } } },
                        y: { grid: { color: '#1e2a3a' }, reverse: !isDist, title: { display: true, text: isDist ? 'Performance (plus haut = meilleur)' : 'Performance (plus bas = meilleur)' } }
                    }
                }
            });

            // Tableau detaille sous le graphique
            var thRow = '<tr><th>#</th><th>Performance</th><th>Niveaux</th><th>Date</th><th>Lieu</th><th>Année</th></tr>';
            var html = '<div class="table-wrap">';
            html += '<table class="bk-table">' + thRow + '</table>';
            html += '<table class="bk-table">';
            pts.forEach(function(p, i) {
                html += '<tr><td>' + (i+1) + '</td>';
                html += '<td><span class="perf-val">' + escapeHtml(p.brut || String(p.perf)) + '</span></td>';
                html += '<td>' + _nivBadge(_highestNiveau(p.niveaux || [])) + '</td>';
                html += '<td>' + dateFR(p.date || '-') + '</td>';
                html += '<td>' + (p.lieu ? '<a href="?page=villes&open=' + encodeURIComponent(p.lieu) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(p.lieu) + '</a>' : '-') + '</td>';
                html += '<td>' + escapeHtml(String(p.annee || '-')) + '</td></tr>';
            });
            html += '</table>';
            html += '<table class="bk-table">' + thRow + '</table>';
            html += '</div>';
            tableDiv.innerHTML = html;
        } else {
            // === Vue TOUTES les disciplines : meilleure perf/annee ===
            document.getElementById('profilProgTitle').textContent = 'Progression par discipline';
            var allYears = {};
            var epNames = Object.keys(profilProgByYear).sort();
            epNames.forEach(function(ep) {
                var annees = profilProgByYear[ep];
                for (var y in annees) allYears[y] = true;
            });
            var yearLabels = Object.keys(allYears).sort();

            var datasets = [];
            epNames.forEach(function(ep, idx) {
                var annees = profilProgByYear[ep];
                datasets.push({
                    label: ep,
                    data: yearLabels.map(function(y) { return annees[y] ? annees[y].perf : null; }),
                    _brutMap: annees,
                    borderColor: profColors[idx % profColors.length],
                    backgroundColor: profColors[idx % profColors.length] + '33',
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    borderWidth: 2,
                    fill: false,
                    spanGaps: true
                });
            });

            profilProgChart = new Chart(canvas, {
                type: 'line',
                data: { labels: yearLabels, datasets: datasets },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var bm = ctx.dataset._brutMap;
                                    var yr = ctx.label;
                                    var brut = bm && bm[yr] ? bm[yr].brut : ctx.parsed.y;
                                    return ctx.dataset.label + ': ' + brut;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { color: '#1e2a3a' } },
                        y: { grid: { color: '#1e2a3a' }, reverse: true, title: { display: true, text: 'Performance (plus bas = meilleur)', font: { size: 11 } } }
                    }
                }
            });
            tableDiv.innerHTML = '';
        }
    };
    // Charger le graphique initial (toutes disciplines)
    buildProfilProgChart('');
    <?php endif; ?>

    <?php if (array_sum($medTypes) > 0): ?>
    new Chart(document.getElementById('profilMedChart'), {
        type: 'doughnut',
        data: {
            labels: ['Or', 'Argent', 'Bronze'],
            datasets: [{ data: [<?= $medTypes['or'] ?>, <?= $medTypes['argent'] ?>, <?= $medTypes['bronze'] ?>], backgroundColor: ['#fbbf24','#d1d5db','#d97706'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true } } } }
    });
    <?php endif; ?>

    <?php if (!empty($resByYear)): ?>
    new Chart(document.getElementById('profilResChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($resByYear as $y => $c) echo "'$y',"; ?>],
            datasets: [{ label: 'Résultats', data: [<?= implode(',', array_values($resByYear)) ?>],
                backgroundColor: '#3b82f688', borderColor: '#3b82f6', borderWidth: 1, borderRadius: 4 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true } } }
    });
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php if (($section === 'all' || $section === 'clubs') && !empty($data['clubs'])): ?>
<h2>Clubs</h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Club</th><th>Debut</th><th>Fin</th></tr></table>
<table class="bk-table">
    <?php foreach ($data['clubs'] as $_i => $c): ?>
    <tr>
        <td><?= $_i + 1 ?></td>
        <td><b><a href="?page=recherche&club=<?= urlencode($c['nom_club']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($c['nom_club']) ?></a></b></td>
        <td><?= $c['annee_debut'] ?? '-' ?></td>
        <td><?= $c['annee_fin'] ?? '-' ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Club</th><th>Debut</th><th>Fin</th></tr></table>
</div>
<?php endif; ?>

<?php if (($section === 'all' || $section === 'medailles') && !empty($data['medailles'])): ?>
<h2>Médailles</h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Type</th><th>Épreuve</th><th>Compétition</th><th>Année</th><th>Lieu</th></tr></table>
<table class="bk-table">
    <?php foreach ($data['medailles'] as $_i => $m): ?>
    <tr>
        <td><?= $_i + 1 ?></td>
        <td><span class="badge badge-<?= $m['type'] ?>"><?= ucfirst($m['type']) ?></span></td>
        <td><a href="?page=recherche&epreuve=<?= urlencode($m['epreuve']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($m['epreuve']) ?></a></td>
        <td><?php if (!empty($m['competition'])): ?><a href="?page=recherche&competition=<?= urlencode($m['competition']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($m['competition']) ?></a><?php else: ?>-<?php endif; ?></td>
        <td><?= $m['annee'] ?></td>
        <td><?php if (!empty($m['lieu'])): ?><a href="?page=villes&open=<?= urlencode($m['lieu']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($m['lieu']) ?></a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Type</th><th>Épreuve</th><th>Compétition</th><th>Année</th><th>Lieu</th></tr></table>
</div>
<?php endif; ?>

<?php if (($section === 'all' || $section === 'records') && !empty($data['records'])): ?>
<h2>Records personnels</h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Performance</th><th>Niveaux</th><th>Date</th><th>Club</th><th>Lieu</th><th>Cat</th></tr></table>
<table class="bk-table">
    <?php foreach ($data['records'] as $_i => $r): ?>
    <tr>
        <td><?= $_i + 1 ?></td>
        <td><a href="?page=recherche&epreuve=<?= urlencode($r['epreuve']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($r['epreuve']) ?></a></td>
        <td><span class="badge badge-perf"><?= htmlspecialchars($r['performance_brut'] ?: $r['performance']) ?></span></td>
        <td><?= nivBadgeHtml(highestNiveau($r['niveaux'] ?? [])) ?></td>
        <td><?= dateFR($r['date']) ?></td>
        <td><?php if (!empty($r['club'])): ?><a href="?page=recherche&club=<?= urlencode(rtrim($r['club'], '* ')) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($r['club']) ?></a><?php endif; ?></td>
        <td><?php if (!empty($r['lieu'])): ?><a href="?page=villes&open=<?= urlencode($r['lieu']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($r['lieu']) ?></a><?php endif; ?></td>
        <td><a href="?page=recherche&categorie=<?= urlencode($r['categorie']) ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= htmlspecialchars($r['categorie']) ?></span></a></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Performance</th><th>Niveaux</th><th>Date</th><th>Club</th><th>Lieu</th><th>Cat</th></tr></table>
</div>
<?php endif; ?>

<?php if (($section === 'all' || $section === 'progressions') && !empty($data['progressions'])): ?>
<h2>Progressions</h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Performance</th><th>Niveaux</th><th>Année</th><th>Vent</th><th>Date</th><th>Lieu</th><th>Club</th><th>Cat</th></tr></table>
<table class="bk-table">
    <?php foreach ($data['progressions'] as $_i => $pr): ?>
    <tr>
        <td><?= $_i + 1 ?></td>
        <td><a href="?page=recherche&epreuve=<?= urlencode($pr['epreuve']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($pr['epreuve']) ?></a></td>
        <td><span class="badge badge-perf"><?= htmlspecialchars($pr['performance_brut'] ?: $pr['performance']) ?></span></td>
        <td><?= nivBadgeHtml(highestNiveau($pr['niveaux'] ?? [])) ?></td>
        <td><?= $pr['annee'] ?></td>
        <td><?= htmlspecialchars($pr['vent']) ?></td>
        <td><?= dateFR($pr['date']) ?></td>
        <td><?php if (!empty($pr['lieu'])): ?><a href="?page=villes&open=<?= urlencode($pr['lieu']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($pr['lieu']) ?></a><?php endif; ?></td>
        <td><?php if (!empty($pr['club'])): ?><a href="?page=recherche&club=<?= urlencode(rtrim($pr['club'], '* ')) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($pr['club']) ?></a><?php endif; ?></td>
        <td><a href="?page=recherche&categorie=<?= urlencode($pr['categorie']) ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= htmlspecialchars($pr['categorie']) ?></span></a></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Performance</th><th>Niveaux</th><th>Année</th><th>Vent</th><th>Date</th><th>Lieu</th><th>Club</th><th>Cat</th></tr></table>
</div>
<?php endif; ?>

<?php if (($section === 'all' || $section === 'podiums') && !empty($data['podiums'])): ?>
<h2>Podiums</h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Performance</th><th>Place</th><th>Rang</th><th>Année</th><th>Niveau</th><th>Vent</th><th>Date</th><th>Lieu</th></tr></table>
<table class="bk-table">
    <?php foreach ($data['podiums'] as $_i => $pd): ?>
    <tr>
        <td><?= $_i + 1 ?></td>
        <td><a href="?page=recherche&epreuve=<?= urlencode($pd['epreuve']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($pd['epreuve']) ?></a></td>
        <td><span class="badge badge-perf"><?= htmlspecialchars($pd['performance_brut'] ?: $pd['performance']) ?></span></td>
        <td><?= htmlspecialchars($pd['place']) ?></td>
        <td><?= $pd['rang'] ?></td>
        <td><?= $pd['annee'] ?></td>
        <td><?php
            $pdnc = ($pd['niveau_competition'][0] ?? '');
            if ($pdnc === 'N') { $pdBg = '#e11d4820'; $pdBc = '#e11d48'; $pdTc = '#fb7185'; }
            elseif ($pdnc === 'I') { $pdBg = '#c026d320'; $pdBc = '#c026d3'; $pdTc = '#e879f9'; }
            elseif ($pdnc === 'R') { $pdBg = '#0891b220'; $pdBc = '#0891b2'; $pdTc = '#22d3ee'; }
            else { $pdBg = '#f9731620'; $pdBc = '#f97316'; $pdTc = '#fb923c'; }
        ?><span style="display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;background:<?= $pdBg ?>;border:1px solid <?= $pdBc ?>40;color:<?= $pdTc ?>;"><?= htmlspecialchars($pd['niveau_competition']) ?></span></td>
        <td><?= htmlspecialchars($pd['vent']) ?></td>
        <td><?= dateFR($pd['date']) ?></td>
        <td><?php if (!empty($pd['lieu'])): ?><a href="?page=villes&open=<?= urlencode($pd['lieu']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($pd['lieu']) ?></a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Performance</th><th>Place</th><th>Rang</th><th>Année</th><th>Niveau</th><th>Vent</th><th>Date</th><th>Lieu</th></tr></table>
</div>
<?php endif; ?>

<?php if (($section === 'all' || $section === 'resultats') && !empty($data['resultats'])): ?>
<h2>Résultats</h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Performance</th><th>Date</th><th>Place</th><th>Vent</th><th>Tour</th><th>Niveau</th><th>Points</th><th>Lieu</th></tr></table>
<table class="bk-table">
    <?php foreach ($data['resultats'] as $_i => $r): ?>
    <tr>
        <td><?= $_i + 1 ?></td>
        <td><a href="?page=recherche&epreuve=<?= urlencode($r['epreuve']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($r['epreuve']) ?></a></td>
        <td><span class="badge badge-perf"><?= htmlspecialchars($r['performance_brut'] ?: $r['performance']) ?></span></td>
        <td><?= dateFR($r['date']) ?></td>
        <td><?= $r['place'] ?></td>
        <td><?= htmlspecialchars($r['vent']) ?></td>
        <td><?= htmlspecialchars($r['tour']) ?></td>
        <td><?php
            $rnc = ($r['niveau'][0] ?? '');
            if ($rnc === 'N') { $rBg = '#e11d4820'; $rBc = '#e11d48'; $rTc = '#fb7185'; }
            elseif ($rnc === 'I') { $rBg = '#c026d320'; $rBc = '#c026d3'; $rTc = '#e879f9'; }
            elseif ($rnc === 'R') { $rBg = '#0891b220'; $rBc = '#0891b2'; $rTc = '#22d3ee'; }
            else { $rBg = '#f9731620'; $rBc = '#f97316'; $rTc = '#fb923c'; }
        ?><span style="display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;background:<?= $rBg ?>;border:1px solid <?= $rBc ?>40;color:<?= $rTc ?>;"><?= htmlspecialchars($r['niveau']) ?></span></td>
        <td><?= $r['points'] ?></td>
        <td><?php if (!empty($r['lieu'])): ?><a href="?page=villes&open=<?= urlencode($r['lieu']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($r['lieu']) ?></a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Performance</th><th>Date</th><th>Place</th><th>Vent</th><th>Tour</th><th>Niveau</th><th>Points</th><th>Lieu</th></tr></table>
</div>
<?php endif; ?>

<?php if (($section === 'all' || $section === 'selections') && !empty($data['selections'])): ?>
<h2>Sélections</h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Compétition</th><th>Épreuve</th><th>Performance</th><th>Niveaux</th><th>Type</th><th>Classement</th><th>Date</th><th>Durée</th><th>Age</th></tr></table>
<table class="bk-table">
    <?php foreach ($data['selections'] as $_i => $s): ?>
    <tr>
        <td><?= $_i + 1 ?></td>
        <td><a href="?page=recherche&competition=<?= urlencode($s['competition']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($s['competition']) ?></a></td>
        <td><a href="?page=recherche&epreuve=<?= urlencode($s['epreuve']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($s['epreuve']) ?></a></td>
        <td><span class="badge badge-perf"><?= htmlspecialchars($s['performance_brut'] ?: $s['performance']) ?></span></td>
        <td><?= nivBadgeHtml(highestNiveau($s['niveaux'] ?? [])) ?></td>
        <td><?= htmlspecialchars($s['type']) ?></td>
        <td><?= $s['classement'] ?></td>
        <td><?= dateFR($s['date']) ?></td>
        <td><?= $s['duree_jours'] ? $s['duree_jours'] . 'j' : '' ?></td>
        <td><?= $s['age'] ? $s['age'] . ' ans' : '' ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Compétition</th><th>Épreuve</th><th>Performance</th><th>Niveaux</th><th>Type</th><th>Classement</th><th>Date</th><th>Durée</th><th>Age</th></tr></table>
</div>
<?php endif; ?>

<?php if (($section === 'all' || $section === 'niveaux') && !empty($data['niveaux'])): ?>
<h2>Niveaux</h2>

<?php
// Prepare data for chart (sorted by year ASC)
$_nivChart = $data['niveaux'];
usort($_nivChart, function($a, $b) { return $a['annee'] - $b['annee']; });
$_nivRank = ['D8'=>1,'D7'=>2,'D6'=>3,'D5'=>4,'D4'=>5,'D3'=>6,'D2'=>7,'D1'=>8,'R6'=>9,'R5'=>10,'R4'=>11,'R3'=>12,'R2'=>13,'R1'=>14,'N4'=>15,'N3'=>16,'N2'=>17,'N1'=>18,'IR'=>19,'IE'=>20];
$_nivLabels = array_flip($_nivRank);
$_chartYears = []; $_chartRanks = []; $_chartPoints = []; $_chartCodes = []; $_hasPoints = false;
foreach ($_nivChart as $_nc) {
    $_chartYears[] = $_nc['annee'];
    $_chartRanks[] = $_nivRank[$_nc['code_niveau']] ?? 0;
    $_chartCodes[] = $_nc['code_niveau'];
    $_chartPoints[] = $_nc['points_niveau'] ?? 0;
    if (!empty($_nc['points_niveau'])) $_hasPoints = true;
}
?>
<div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:20px;">
    <div class="chart-card" style="flex:2;min-width:320px;">
        <h3><span class="chart-icon" style="background:#6366f120;color:#818cf8;">&#128200;</span> Évolution du niveau</h3>
        <canvas id="niveauEvolutionChart"></canvas>
    </div>
    <?php if ($_hasPoints): ?>
    <div class="chart-card" style="flex:1;min-width:260px;">
        <h3><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#127942;</span> Points par saison</h3>
        <canvas id="niveauPointsChart"></canvas>
    </div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var nivYears = <?= json_encode($_chartYears) ?>;
    var nivRanks = <?= json_encode($_chartRanks) ?>;
    var nivCodes = <?= json_encode($_chartCodes) ?>;
    var nivPoints = <?= json_encode($_chartPoints) ?>;
    var nivRankMap = <?= json_encode($_nivRank) ?>;
    var nivLabelMap = <?= json_encode($_nivLabels) ?>;

    // Color per code prefix
    function nivColor(code) {
        var p = (code||'')[0];
        if (p === 'I') return '#e879f9';
        if (p === 'N') return '#fb7185';
        if (p === 'R') return '#22d3ee';
        return '#fb923c';
    }
    function nivBg(code) {
        var p = (code||'')[0];
        if (p === 'I') return '#c026d340';
        if (p === 'N') return '#e11d4840';
        if (p === 'R') return '#0891b240';
        return '#f9731640';
    }

    // Point colors per year
    var ptColors = nivCodes.map(function(c) { return nivColor(c); });
    var ptBgColors = nivCodes.map(function(c) { return nivBg(c); });

    // All used rank values for Y axis ticks
    var allRanks = Object.keys(nivLabelMap).map(Number).sort(function(a,b){return a-b;});
    var minR = Math.max(1, Math.min.apply(null, nivRanks) - 1);
    var maxR = Math.min(20, Math.max.apply(null, nivRanks) + 1);

    new Chart(document.getElementById('niveauEvolutionChart'), {
        type: 'line',
        data: {
            labels: nivYears,
            datasets: [{
                label: 'Niveau',
                data: nivRanks,
                borderColor: '#818cf8',
                backgroundColor: '#6366f130',
                borderWidth: 3,
                pointRadius: 7,
                pointHoverRadius: 10,
                pointBackgroundColor: ptColors,
                pointBorderColor: ptColors,
                pointBorderWidth: 2,
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    min: minR,
                    max: maxR,
                    reverse: false,
                    ticks: {
                        stepSize: 1,
                        callback: function(v) { return nivLabelMap[v] || ''; },
                        font: { size: 11, weight: 'bold' },
                        color: function(ctx) { return nivColor(nivLabelMap[ctx.tick.value] || ''); }
                    },
                    grid: { color: '#1e2a3a' }
                },
                x: {
                    ticks: { font: { size: 12 } },
                    grid: { color: '#1e2a3a' }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var code = nivCodes[ctx.dataIndex];
                            var pts = nivPoints[ctx.dataIndex];
                            return code + (pts ? ' (' + pts + ' pts)' : '');
                        }
                    }
                }
            }
        }
    });

    <?php if ($_hasPoints): ?>
    new Chart(document.getElementById('niveauPointsChart'), {
        type: 'bar',
        data: {
            labels: nivYears,
            datasets: [{
                label: 'Points',
                data: nivPoints,
                backgroundColor: ptBgColors,
                borderColor: ptColors,
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, grid: { color: '#1e2a3a' }, ticks: { font: { size: 11 } } },
                x: { grid: { color: '#1e2a3a' }, ticks: { font: { size: 12 } } }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return nivCodes[ctx.dataIndex] + ' — ' + ctx.parsed.y + ' pts';
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php foreach ($data['niveaux'] as $n):
    $pnc = ($n['code_niveau'][0] ?? '');
    if ($pnc === 'N') { $pnBg = '#e11d4820'; $pnBc = '#e11d48'; $pnTc = '#fb7185'; }
    elseif ($pnc === 'I') { $pnBg = '#c026d320'; $pnBc = '#c026d3'; $pnTc = '#e879f9'; }
    elseif ($pnc === 'R') { $pnBg = '#0891b220'; $pnBc = '#0891b2'; $pnTc = '#22d3ee'; }
    else { $pnBg = '#f9731620'; $pnBc = '#f97316'; $pnTc = '#fb923c'; }
?>
<div class="card" style="margin:8px 0;">
    <b><?= $n['annee'] ?></b> —
    <span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;background:<?= $pnBg ?>;border:1px solid <?= $pnBc ?>40;color:<?= $pnTc ?>;font-weight:600;"><?= htmlspecialchars($n['code_niveau']) ?></span>
    <?= $n['points_niveau'] ? $n['points_niveau'] . ' pts' : '' ?>
    | <?php if (!empty($n['club'])): ?><a href="?page=recherche&club=<?= urlencode(rtrim($n['club'], '* ')) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($n['club']) ?></a><?php endif; ?>

    <?php if (!empty($n['performances'])): ?>
    <table class="bk-table" style="margin-top:8px;"><tr><th>#</th><th>Épreuve</th><th>Performance</th><th>Code</th></tr></table>
    <table class="bk-table">
        <?php foreach ($n['performances'] as $_i => $perf):
            $pc = ($perf['code_niveau'][0] ?? '');
            if ($pc === 'N') { $pcBg = '#e11d4820'; $pcBc = '#e11d48'; $pcTc = '#fb7185'; }
            elseif ($pc === 'I') { $pcBg = '#c026d320'; $pcBc = '#c026d3'; $pcTc = '#e879f9'; }
            elseif ($pc === 'R') { $pcBg = '#0891b220'; $pcBc = '#0891b2'; $pcTc = '#22d3ee'; }
            else { $pcBg = '#f9731620'; $pcBc = '#f97316'; $pcTc = '#fb923c'; }
        ?>
        <tr>
            <td><?= $_i + 1 ?></td>
            <td><a href="?page=recherche&epreuve=<?= urlencode($perf['epreuve']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($perf['epreuve']) ?></a></td>
            <td><span class="badge badge-perf"><?= htmlspecialchars($perf['performance_brut'] ?: $perf['performance']) ?></span></td>
            <td><span style="display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;background:<?= $pcBg ?>;border:1px solid <?= $pcBc ?>40;color:<?= $pcTc ?>;"><?= htmlspecialchars($perf['code_niveau']) ?></span></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Performance</th><th>Code</th></tr></table>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- QR Code profil -->
<div class="qr-share">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?= urlencode('https://bokonzi.com/pages/profil.php?id=' . intval($i['id_athlete'] ?? $id)) ?>" alt="QR Code profil <?= htmlspecialchars($i['nom_complet'] ?? '') ?> — Bokonzi" width="120" height="120">
    <div class="qr-label">Scannez pour partager ce profil</div>
</div>

<?php else: ?>
<div class="error">Athlète #<?= htmlspecialchars($id) ?> non trouvé.</div>
<?php endif; ?>


<?php
// ================================================================
//  CLUBS
// ================================================================
elseif ($page === 'clubs'):
    $nomClub = $_GET['nom'] ?? '';
    $params = ['page' => $p, 'limit' => 50, 'has_athletes' => 1, 'max_athletes' => 5000];
    if ($nomClub) $params['nom'] = $nomClub;
    $data = apiCall("$BASE_API/clubs.php?" . http_build_query($params));
?>

<h1>Clubs</h1>

<div id="ignoredClubsPanel" class="ignored-panel" style="display:none;">
    <h3>&#128683; Clubs ignores <span id="ignoredClubsCount" style="color:#fca5a5;font-weight:400;"></span></h3>
    <div id="ignoredClubsList"></div>
</div>

<div id="clubDetailPanel" class="club-detail-panel">
    <div class="club-detail-header">
        <h2 id="clubDetailName"></h2>
        <span class="meta-info" id="clubDetailMeta"></span>
        <button class="btn-follow btn-follow-club" id="btnFollowClub" style="display:none;">&#9825; Suivre</button>
        <button onclick="closeClubDetail()" class="btn-close-detail">&times; Fermer</button>
    </div>
    <div class="club-detail-tabs">
        <button class="club-detail-tab active" data-tab="epreuves" onclick="switchClubTab('epreuves')">Épreuves</button>
        <button class="club-detail-tab" data-tab="nationalites" onclick="switchClubTab('nationalites')">Nationalités</button>
        <button class="club-detail-tab" data-tab="stats" onclick="switchClubTab('stats')">Stats</button>
        <button class="club-detail-tab" data-tab="performances" onclick="switchClubTab('performances')">Performances</button>
        <button class="club-detail-tab" data-tab="resume" onclick="switchClubTab('resume')">Resume</button>
    </div>
    <div class="club-search-bar" id="clubSearchBar" style="display:none;padding:8px 16px;">
        <div style="position:relative;">
            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#5a6580;font-size:14px;">&#128269;</span>
            <input type="text" id="clubSearchInput" placeholder="Rechercher un athlète dans ce club..." autocomplete="off" style="width:100%;padding:8px 12px 8px 32px;border-radius:8px;border:1px solid #1e2a3a;background:#0d1117;color:#c9d1d9;font-size:13px;outline:none;transition:border-color .2s;">
        </div>
    </div>
    <div id="clubDetailContent" class="club-detail-content">
        <div class="loading-msg">Cliquez sur un club pour voir ses details</div>
    </div>
    <div id="clubQR"></div>
</div>

<div class="live-search">
    <span class="ls-icon">&#128269;</span>
    <input type="text" id="lsClubs" placeholder="Rechercher un club..." autocomplete="off">
    <div class="ls-status" id="lsClubsStatus"></div>
</div>
<div class="ls-results" id="lsClubsResults" style="display:none;"></div>

<div id="clubsPaginated">
<?php if ($data && ($data['success'] ?? false)):
    // Top 10 clubs de la page pour le graphique
    $clubChartData = array_slice($data['clubs'], 0, 10);
?>
<p class="subtitle"><?= number_format($data['total'], 0, ',', ' ') ?> clubs</p>

<!-- Graphique clubs -->
<div class="charts-row" style="margin-bottom:20px;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#127963;</span> Top Clubs (cette page)</h3><canvas id="clubsChart"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#128197;</span> Periode d'activite</h3><canvas id="clubsPeriodChart"></canvas></div>
</div>
<script>
// Donnees clubs stockees, charts construits par rebuildClubCharts()
window._clubsPageRaw = [<?php foreach ($clubChartData as $c) {
    echo "{label:'" . addslashes(mb_substr($c['nom_club'], 0, 25)) . "',";
    echo "labelShort:'" . addslashes(mb_substr($c['nom_club'], 0, 20)) . "',";
    echo "fullName:'" . addslashes($c['nom_club']) . "',";
    echo "count:" . $c['nb_athletes'] . ",";
    echo "start:" . ($c['annee_debut'] ?: 2000) . ",";
    echo "end:" . ($c['annee_fin'] ?: 2025) . "},";
} ?>];
</script>

<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Club</th><th>Athlètes</th><th>Top niveaux</th><th></th><th></th><th></th></tr></table>
<table class="bk-table">
    <?php foreach ($data['clubs'] as $idx => $c): ?>
    <tr data-club-name="<?= htmlspecialchars($c['nom_club'], ENT_QUOTES) ?>">
        <td><?= ($p - 1) * 50 + $idx + 1 ?></td>
        <td><b><a href="?page=recherche&club=<?= urlencode($c['nom_club']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($c['nom_club']) ?></a></b></td>
        <td><?= $c['nb_athletes'] ?></td>
        <td><?php if (!empty($c['top_niveaux'])): foreach ($c['top_niveaux'] as $tn):
            $nc = $tn['niveau'][0] ?? '';
            if ($nc === 'N') { $bg = '#e11d4820'; $bc = '#e11d48'; $tc = '#fb7185'; }
            elseif ($nc === 'I') { $bg = '#c026d320'; $bc = '#c026d3'; $tc = '#e879f9'; }
            elseif ($nc === 'R') { $bg = '#0891b220'; $bc = '#0891b2'; $tc = '#22d3ee'; }
            else { $bg = '#f9731620'; $bc = '#f97316'; $tc = '#fb923c'; }
            ?><span style="display:inline-block;padding:2px 7px;border-radius:5px;font-size:10px;margin:1px;background:<?= $bg ?>;border:1px solid <?= $bc ?>40;color:<?= $tc ?>;"><?= htmlspecialchars($tn['niveau']) ?> <?= $tn['pct'] ?>%</span><?php endforeach; else: ?>-<?php endif; ?></td>
        <td><a href="?page=recherche&club=<?= urlencode($c['nom_club']) ?>">Voir athletes</a></td>
        <td><button class="btn-cmp-add btn-cmp-add-club" data-cmp-club="<?= $c['id_club'] ?>" data-name="<?= htmlspecialchars($c['nom_club'], ENT_QUOTES) ?>" onclick="toggleClubBasket(this,parseInt(this.dataset.cmpClub),this.dataset.name)">+</button></td>
        <td><button class="btn-cmp-ignore" data-ignore-club="<?= $c['id_club'] ?>" data-name="<?= htmlspecialchars($c['nom_club'], ENT_QUOTES) ?>" onclick="toggleIgnoreClub(this,parseInt(this.dataset.ignoreClub),this.dataset.name)" title="Ignorer ce club">&#8856;</button></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Club</th><th>Athlètes</th><th>Top niveaux</th><th></th><th></th><th></th></tr></table>
</div>

<?php if ($data['total_pages'] > 1): ?>
<div class="pager">
    <?php if ($p > 1): ?><a href="?page=clubs&nom=<?= urlencode($nomClub) ?>&p=<?= $p - 1 ?>">Precedent</a><?php endif; ?>
    <?php for ($i = max(1,$p-3); $i <= min($data['total_pages'],$p+3); $i++): ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?page=clubs&nom=<?= urlencode($nomClub) ?>&p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?page=clubs&nom=<?= urlencode($nomClub) ?>&p=<?= $p + 1 ?>">Suivant</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
</div>

<?php
    $openClub = $_GET['open'] ?? '';
    if ($openClub): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    openClubDetail(null, <?= json_encode($openClub, JSON_UNESCAPED_UNICODE) ?>);
});
</script>
<?php endif; ?>

<?php
// ================================================================
//  EPREUVES
// ================================================================
elseif ($page === 'epreuves'):
    $nomEp = $_GET['nom'] ?? '';
    $params = ['page' => $p, 'limit' => 50, 'has_athletes' => 1];
    if ($nomEp) $params['nom'] = $nomEp;
    $data = apiCall("$BASE_API/epreuves.php?" . http_build_query($params));
?>

<h1>Épreuves</h1>

<div class="live-search">
    <span class="ls-icon">&#128269;</span>
    <input type="text" id="lsEpreuves" placeholder="Rechercher une épreuve..." autocomplete="off">
    <div class="ls-status" id="lsEpreuvesStatus"></div>
</div>
<div class="ls-results" id="lsEpreuvesResults" style="display:none;"></div>

<div id="epreuvesPaginated">
<?php if ($data && ($data['success'] ?? false)):
    $epChartData = array_slice($data['epreuves'], 0, 12);
    $epDoughnut = array_slice($data['epreuves'], 0, 6);
    $epReste = array_slice($data['epreuves'], 6);
    $resteTotal = 0;
    foreach ($epReste as $er) $resteTotal += $er['nb_athletes'];
?>
<p class="subtitle"><?= number_format($data['total'], 0, ',', ' ') ?> epreuves</p>

<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Athlètes avec record</th></tr></table>
<table class="bk-table">
    <?php foreach ($data['epreuves'] as $idx => $e): ?>
    <tr>
        <td><?= ($p - 1) * 50 + $idx + 1 ?></td>
        <td><b><a href="?page=recherche&epreuve=<?= urlencode($e['nom_epreuve']) ?>" style="color:#a29bfe;text-decoration:none;cursor:pointer;"><?= htmlspecialchars($e['nom_epreuve']) ?></a></b></td>
        <td><?= $e['nb_athletes'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Athlètes avec record</th></tr></table>
</div>

<?php if ($data['total_pages'] > 1): ?>
<div class="pager">
    <?php if ($p > 1): ?><a href="?page=epreuves&nom=<?= urlencode($nomEp) ?>&p=<?= $p - 1 ?>">Precedent</a><?php endif; ?>
    <?php for ($i = max(1,$p-3); $i <= min($data['total_pages'],$p+3); $i++): ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?page=epreuves&nom=<?= urlencode($nomEp) ?>&p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?page=epreuves&nom=<?= urlencode($nomEp) ?>&p=<?= $p + 1 ?>">Suivant</a><?php endif; ?>
</div>
<?php endif; ?>

<!-- Graphiques epreuves -->
<div class="charts-row" style="margin:20px 0;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127939;</span> Top Épreuves (cette page)</h3><canvas id="epBarChart"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#128200;</span> Repartition</h3><canvas id="epDoughChart"></canvas></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('epBarChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($epChartData as $e) echo "'" . addslashes($e['nom_epreuve']) . "',"; ?>],
            datasets: [{ label: 'Athletes', data: [<?php foreach ($epChartData as $e) echo $e['nb_athletes'] . ','; ?>],
                backgroundColor: function(ctx) { var g = ctx.chart.ctx.createLinearGradient(0,0,ctx.chart.width,0); g.addColorStop(0,'#ec4899'); g.addColorStop(1,'#f59e0b'); return g; },
                borderRadius: 6, barThickness: 18 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
    });
    new Chart(document.getElementById('epDoughChart'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($epDoughnut as $e) echo "'" . addslashes($e['nom_epreuve']) . "',"; ?><?= $resteTotal > 0 ? "'Autres'," : '' ?>],
            datasets: [{ data: [<?php foreach ($epDoughnut as $e) echo $e['nb_athletes'] . ','; ?><?= $resteTotal > 0 ? $resteTotal . ',' : '' ?>],
                backgroundColor: ['#3b82f6','#ec4899','#10b981','#f59e0b','#8b5cf6','#06b6d4','#64748b'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, font: { size: 10 } } } } }
    });
});
</script>

<?php endif; ?>
</div>

<?php
// ================================================================
//  EPREUVE DETAIL (page dédiée)
// ================================================================
elseif ($page === 'epreuve'):
    $epNom = trim($_GET['nom'] ?? '');
    $epPage = max(1, (int)($_GET['ep'] ?? 1));
    $epSection = $_GET['s'] ?? 'all';
    if ($epNom === ''): echo '<div class="error">Paramètre nom requis</div>';
    else:
    $epData = apiCall("$BASE_API/epreuve_stats.php?nom=" . urlencode($epNom) . "&page=$epPage&limit=50");
    if (!$epData || !($epData['success'] ?? false)):
        echo '<div class="error">Épreuve "' . htmlspecialchars($epNom) . '" non trouvée.</div>';
    else:
?>
<h1><?= htmlspecialchars($epData['epreuve']) ?></h1>
<div class="info">
    <b><?= number_format($epData['total_athletes'], 0, ',', ' ') ?></b> athlètes |
    <b><?= number_format($epData['total_records'], 0, ',', ' ') ?></b> records |
    <?php if ($epData['annee_debut']): ?><b>Période :</b> <?= $epData['annee_debut'] ?> — <?= $epData['annee_fin'] ?: '...' ?> |<?php endif; ?>
    <b><?= $epData['total_medailles'] ?? 0 ?></b> médailles |
    <b><?= $epData['total_podiums'] ?? 0 ?></b> podiums
    <?php $epSel = $epData['selections'] ?? []; if (($epSel['nb_selections'] ?? 0) > 0): ?> | <b><?= $epSel['nb_selections'] ?></b> sélections<?php endif; ?>
    <?php $epProg = $epData['progressions'] ?? []; if (($epProg['nb_progressions'] ?? 0) > 0): ?> | <b><?= $epProg['nb_progressions'] ?></b> progressions<?php endif; ?>
</div>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin:10px 0;">
    <a href="?page=recherche&epreuve=<?= urlencode($epNom) ?>" style="display:inline-block;padding:7px 18px;background:#6c5ce720;border:1px solid #6c5ce740;border-radius:6px;color:#a29bfe;font-size:13px;font-weight:600;text-decoration:none;">Recherche avancée &#8599;</a>
</div>

<!-- Onglets -->
<div class="tabs">
    <a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=all" class="<?= $epSection === 'all' ? 'active' : '' ?>">Tout</a>
    <a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=records" class="<?= $epSection === 'records' ? 'active' : '' ?>">Records<span class="count"><?= $epData['total_records'] ?></span></a>
    <a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=stats" class="<?= $epSection === 'stats' ? 'active' : '' ?>">Stats</a>
    <a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=resume" class="<?= $epSection === 'resume' ? 'active' : '' ?>">Résumé</a>
</div>

<?php
// ---- RECORDS ----
if ($epSection === 'all' || $epSection === 'records'):
    $epRecs = $epData['records'] ?? [];
?>
<h2>Records <span class="section-count">(<?= $epData['total_records'] ?>)</span></h2>
<?php if (!empty($epRecs)): ?>
<?php $epRecTh = '<tr><th>#</th><th>Athlète</th><th>Performance</th><th>Date</th><th>Club</th><th>Cat</th><th>Sexe</th><th>NAT</th><th>Niveaux</th></tr>'; ?>
<div class="table-wrap">
<table class="bk-table"><?= $epRecTh ?></table>
<table class="bk-table">
    <?php foreach ($epRecs as $_i => $rec):
        $recRank = ($epPage - 1) * 50 + $_i + 1;
    ?>
    <tr>
        <td><?= $recRank ?></td>
        <td><a href="?page=profil&id=<?= $rec['athlete_id'] ?>" style="color:#a29bfe;"><?= htmlspecialchars($rec['athlete']) ?></a></td>
        <td><span class="badge badge-perf"><?= htmlspecialchars($rec['performance'] ?? '') ?></span></td>
        <td><?= $rec['date'] ? date('d/m/Y', strtotime($rec['date'])) : '-' ?></td>
        <td><?php if (!empty($rec['club'])): ?><a href="#" onclick="openClubDetail(null,<?= htmlspecialchars(json_encode($rec['club'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>);return false;" style="color:#a29bfe;cursor:pointer;"><?= htmlspecialchars(mb_substr($rec['club'], 0, 25)) ?></a><?php else: ?>-<?php endif; ?></td>
        <td><span style="padding:2px 8px;border-radius:4px;font-size:11px;background:#6c5ce720;color:#a29bfe;"><?= htmlspecialchars($rec['categorie'] ?? '') ?></span></td>
        <td><?= htmlspecialchars($rec['sexe'] ?? '') ?></td>
        <td><?= htmlspecialchars($rec['nationalite'] ?? '') ?></td>
        <td><?= nivBadgeHtml(highestNiveau($rec['niveaux'] ?? [])) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><?= $epRecTh ?></table>
</div>
<?php if ($epData['total_pages'] > 1): ?>
<div class="pager">
    <?php if ($epPage > 1): ?><a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=<?= $epSection ?>&ep=<?= $epPage - 1 ?>">← Précédent</a><?php endif; ?>
    <?php for ($i = max(1, $epPage - 4); $i <= min($epData['total_pages'], $epPage + 4); $i++): ?>
        <?php if ($i == $epPage): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=<?= $epSection ?>&ep=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($epPage < $epData['total_pages']): ?><a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=<?= $epSection ?>&ep=<?= $epPage + 1 ?>">Suivant →</a><?php endif; ?>
    <span style="color:#666;margin-left:10px;">(<?= $epData['total_pages'] ?> pages)</span>
</div>
<?php endif; endif; endif; ?>

<?php
// ---- STATS ----
if ($epSection === 'all' || $epSection === 'stats'):
    $epMed = $epData['medailles'] ?? ['or'=>0,'argent'=>0,'bronze'=>0];
    $epPod = $epData['podiums'] ?? ['1er'=>0,'2e'=>0,'3e'=>0];
    $epMedDet = $epData['medailles_detail'] ?? [];
    $epNivRes = $epData['niveaux_resultats'] ?? [];
    $epTopClubs = $epData['top_clubs'] ?? [];
    $epTopVilles = $epData['top_villes'] ?? [];
    $epParAnnee = $epData['resultats_par_annee'] ?? [];
    $epParSexe = $epData['par_sexe'] ?? [];
    $epParCat = $epData['par_categorie'] ?? [];
    $epNats = $epData['nationalites'] ?? [];
?>
<h2>Statistiques</h2>

<!-- Répartition sexe + catégorie -->
<div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:20px;">
<?php if (!empty($epParSexe)): ?>
<div class="chart-card" style="flex:1;min-width:220px;">
    <h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">&#9893;</span> Répartition par sexe</h3>
    <canvas id="epStatSexeChart"></canvas>
</div>
<?php endif; ?>
<?php if (!empty($epParCat)): ?>
<div class="chart-card" style="flex:1;min-width:220px;">
    <h3><span class="chart-icon" style="background:#6c5ce720;color:#a29bfe;">&#127941;</span> Par catégorie</h3>
    <canvas id="epStatCatChart"></canvas>
</div>
<?php endif; ?>
<?php if (!empty($epNats)): ?>
<div class="chart-card" style="flex:1;min-width:220px;">
    <h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#127758;</span> Nationalités (<?= count($epNats) ?>)</h3>
    <canvas id="epStatNatChart"></canvas>
</div>
<?php endif; ?>
</div>

<!-- Médailles -->
<?php if (($epData['total_medailles'] ?? 0) > 0): $eTM = $epData['total_medailles']; ?>
<h3>Médailles (<?= $eTM ?>)</h3>
<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#fbbf24;font-weight:700;"><?= $epMed['or'] ?></div><div style="font-size:12px;color:#8b949e;">Or<?php if ($eTM > 0): ?> (<?= round($epMed['or']/$eTM*100) ?>%)<?php endif; ?></div></div>
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#94a3b8;font-weight:700;"><?= $epMed['argent'] ?></div><div style="font-size:12px;color:#8b949e;">Argent<?php if ($eTM > 0): ?> (<?= round($epMed['argent']/$eTM*100) ?>%)<?php endif; ?></div></div>
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#d97706;font-weight:700;"><?= $epMed['bronze'] ?></div><div style="font-size:12px;color:#8b949e;">Bronze<?php if ($eTM > 0): ?> (<?= round($epMed['bronze']/$eTM*100) ?>%)<?php endif; ?></div></div>
</div>
<?php if (!empty($epMedDet)): ?>
<div class="table-wrap">
<?php $epMedTh = '<tr><th>Type</th><th>Athlète</th><th>Compétition</th><th>Lieu</th><th>Année</th></tr>'; ?>
<table class="bk-table"><?= $epMedTh ?></table>
<table class="bk-table">
    <?php foreach ($epMedDet as $md): ?>
    <tr>
        <td><span class="badge badge-<?= $md['type'] ?>"><?= ucfirst($md['type']) ?></span></td>
        <td><a href="?page=profil&id=<?= $md['athlete_id'] ?>" style="color:#a29bfe;"><?= htmlspecialchars($md['athlete']) ?></a></td>
        <td><?= htmlspecialchars($md['competition'] ?? '') ?></td>
        <td><?= htmlspecialchars($md['lieu'] ?? '') ?></td>
        <td><?= $md['annee'] ?? '-' ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><?= $epMedTh ?></table>
</div>
<?php endif; endif; ?>

<!-- Podiums -->
<?php if (($epData['total_podiums'] ?? 0) > 0): $eTP = $epData['total_podiums']; ?>
<h3>Podiums (<?= $eTP ?>)</h3>
<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#fbbf24;font-weight:700;"><?= $epPod['1er'] ?></div><div style="font-size:12px;color:#8b949e;">1ère place<?php if ($eTP > 0): ?> (<?= round($epPod['1er']/$eTP*100) ?>%)<?php endif; ?></div></div>
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#94a3b8;font-weight:700;"><?= $epPod['2e'] ?></div><div style="font-size:12px;color:#8b949e;">2ème place<?php if ($eTP > 0): ?> (<?= round($epPod['2e']/$eTP*100) ?>%)<?php endif; ?></div></div>
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#d97706;font-weight:700;"><?= $epPod['3e'] ?></div><div style="font-size:12px;color:#8b949e;">3ème place<?php if ($eTP > 0): ?> (<?= round($epPod['3e']/$eTP*100) ?>%)<?php endif; ?></div></div>
</div>
<?php endif; ?>

<!-- Sélections -->
<?php if (($epSel['nb_selections'] ?? 0) > 0): ?>
<h3>Sélections nationales</h3>
<div class="card"><b><?= $epSel['nb_selections'] ?></b> sélections pour <b><?= $epSel['nb_athletes'] ?></b> athlètes</div>
<?php endif; ?>

<!-- Progressions -->
<?php if (($epProg['nb_progressions'] ?? 0) > 0): ?>
<h3>Progressions</h3>
<div class="card"><b><?= $epProg['nb_progressions'] ?></b> progressions pour <b><?= $epProg['nb_athletes'] ?></b> athlètes</div>
<?php endif; ?>

<!-- Top Clubs -->
<?php if (!empty($epTopClubs)): ?>
<h3>Top Clubs</h3>
<div class="table-wrap">
<?php $epClubTh = '<tr><th>#</th><th>Club</th><th>Athlètes</th><th>Records</th></tr>'; ?>
<table class="bk-table"><?= $epClubTh ?></table>
<table class="bk-table">
    <?php foreach ($epTopClubs as $_ci => $tc): ?>
    <tr>
        <td><?= $_ci + 1 ?></td>
        <td><a href="#" onclick="openClubDetail(null,<?= htmlspecialchars(json_encode($tc['club'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>);return false;" style="color:#a29bfe;cursor:pointer;"><?= htmlspecialchars($tc['club']) ?></a></td>
        <td><?= $tc['nb_athletes'] ?></td>
        <td><?= $tc['nb_records'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><?= $epClubTh ?></table>
</div>
<?php endif; ?>

<!-- Top Villes -->
<?php if (!empty($epTopVilles)): ?>
<h3>Top Villes</h3>
<div class="table-wrap">
<?php $epVilleTh = '<tr><th>#</th><th>Ville</th><th>Records</th><th>Athlètes</th></tr>'; ?>
<table class="bk-table"><?= $epVilleTh ?></table>
<table class="bk-table">
    <?php foreach ($epTopVilles as $_vi => $tv): ?>
    <tr>
        <td><?= $_vi + 1 ?></td>
        <td><a href="?page=villes&open=<?= urlencode($tv['ville']) ?>" style="color:#a29bfe;"><?= htmlspecialchars($tv['ville']) ?></a></td>
        <td><?= $tv['nb_records'] ?></td>
        <td><?= $tv['nb_athletes'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><?= $epVilleTh ?></table>
</div>
<?php endif; ?>

<!-- Niveaux de compétition -->
<?php if (!empty($epNivRes)): ?>
<h3>Niveaux de compétition</h3>
<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
<?php foreach ($epNivRes as $enr):
    $enp = ($enr['niveau'][0] ?? '');
    if ($enp === 'N') { $enbg='#e11d4820'; $enbc='#e11d48'; $entc='#fb7185'; }
    elseif ($enp === 'I') { $enbg='#c026d320'; $enbc='#c026d3'; $entc='#e879f9'; }
    elseif ($enp === 'R') { $enbg='#0891b220'; $enbc='#0891b2'; $entc='#22d3ee'; }
    else { $enbg='#f9731620'; $enbc='#f97316'; $entc='#fb923c'; }
?>
<span style="display:inline-block;padding:4px 12px;border-radius:6px;font-size:12px;background:<?= $enbg ?>;border:1px solid <?= $enbc ?>40;color:<?= $entc ?>;font-weight:600;"><?= htmlspecialchars($enr['niveau']) ?> <span style="color:#8b949e;font-weight:400;">(<?= $enr['count'] ?>)</span></span>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Évolution par année -->
<?php if (!empty($epParAnnee)): ?>
<h3>Évolution par année</h3>
<div class="chart-card" style="margin-bottom:16px;">
    <canvas id="epStatEvoChart"></canvas>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
<?php if (!empty($epParSexe)): ?>
    new Chart(document.getElementById('epStatSexeChart'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($epParSexe as $k => $v) echo "'" . ($k==='M'?'Hommes':($k==='F'?'Femmes':$k)) . "',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($epParSexe)) ?>], backgroundColor: ['#3b82f6','#ec4899','#64748b'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, font: { size: 11 } } } } }
    });
<?php endif; ?>
<?php if (!empty($epParCat)):
    $epCatLabels = array_keys($epParCat); $epCatValues = array_values($epParCat);
?>
    new Chart(document.getElementById('epStatCatChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($epCatLabels) ?>,
            datasets: [{ data: <?= json_encode($epCatValues) ?>, backgroundColor: '#6c5ce780', borderColor: '#6c5ce7', borderWidth: 1, borderRadius: 4 }]
        },
        options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
<?php endif; ?>
<?php if (!empty($epNats)):
    $epNatTop = array_slice($epNats, 0, 10, true);
?>
    new Chart(document.getElementById('epStatNatChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($epNatTop)) ?>,
            datasets: [{ data: <?= json_encode(array_values($epNatTop)) ?>, backgroundColor: ['#3b82f6','#ec4899','#10b981','#f59e0b','#8b5cf6','#ef4444','#06b6d4','#84cc16','#f97316','#6366f1'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, font: { size: 10 } } } } }
    });
<?php endif; ?>
<?php if (!empty($epParAnnee)):
    $epaYears = array_column($epParAnnee, 'annee');
    $epaRes = array_column($epParAnnee, 'nb_resultats');
    $epaAth = array_column($epParAnnee, 'nb_athletes');
?>
    new Chart(document.getElementById('epStatEvoChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_reverse($epaYears)) ?>,
            datasets: [
                { label: 'Résultats', data: <?= json_encode(array_reverse($epaRes)) ?>, borderColor: '#6c5ce7', backgroundColor: '#6c5ce730', fill: true, tension: 0.3, pointRadius: 4 },
                { label: 'Athlètes', data: <?= json_encode(array_reverse($epaAth)) ?>, borderColor: '#10b981', backgroundColor: '#10b98130', fill: true, tension: 0.3, pointRadius: 4 }
            ]
        },
        options: { responsive: true, scales: { x: { grid: { color: '#1e2a3a' } }, y: { beginAtZero: true, grid: { color: '#1e2a3a' } } }, plugins: { legend: { labels: { usePointStyle: true, font: { size: 11 } } } } }
    });
<?php endif; ?>
});
</script>
<?php endif; // stats ?>

<?php
// ---- RESUME ----
if ($epSection === 'all' || $epSection === 'resume'):
    $d = $epData;
    $bio = [];
    // §1 Intro
    $bio[] = "L'épreuve " . $d['epreuve'] . " regroupe " . number_format($d['total_athletes'], 0, ',', ' ') . " athlètes et " . number_format($d['total_records'], 0, ',', ' ') . " records personnels enregistrés.";
    if ($d['annee_debut']) $bio[] = "Les données couvrent la période " . $d['annee_debut'] . " à " . ($d['annee_fin'] ?: 'aujourd\'hui') . ".";
    // §2 Sexe
    if (!empty($d['par_sexe'])) {
        $parts = [];
        foreach ($d['par_sexe'] as $k => $v) { $parts[] = $v . " " . ($k === 'M' ? 'hommes' : ($k === 'F' ? 'femmes' : $k)); }
        $bio[] = "Répartition par sexe : " . implode(', ', $parts) . ".";
    }
    // §3 Catégories
    if (!empty($d['par_categorie'])) {
        $topCats = array_slice($d['par_categorie'], 0, 5, true);
        $parts = [];
        foreach ($topCats as $k => $v) $parts[] = $k . " (" . $v . ")";
        $bio[] = "Catégories principales : " . implode(', ', $parts) . ".";
    }
    // §4 Nationalités
    if (!empty($d['nationalites'])) {
        $bio[] = count($d['nationalites']) . " nationalités représentées. Top : " . implode(', ', array_map(function($k, $v) { return "$k ($v)"; }, array_keys(array_slice($d['nationalites'], 0, 5, true)), array_slice(array_values($d['nationalites']), 0, 5))) . ".";
    }
    // §5 Médailles
    $tm = $d['total_medailles'] ?? 0;
    if ($tm > 0) {
        $em = $d['medailles'];
        $bio[] = "$tm médailles attribuées : " . $em['or'] . " en or, " . $em['argent'] . " en argent, " . $em['bronze'] . " en bronze.";
        if (!empty($d['medailles_detail'])) {
            $last = $d['medailles_detail'][0];
            $bio[] = "Dernière médaille : " . ucfirst($last['type']) . " pour " . $last['athlete'] . (!empty($last['competition']) ? " (" . $last['competition'] . ")" : "") . (!empty($last['annee']) ? " en " . $last['annee'] : "") . ".";
        }
    }
    // §6 Podiums
    $tp = $d['total_podiums'] ?? 0;
    if ($tp > 0) {
        $ep2 = $d['podiums'];
        $bio[] = "$tp podiums : " . $ep2['1er'] . " victoires, " . $ep2['2e'] . " deuxièmes places, " . $ep2['3e'] . " troisièmes places.";
    }
    // §7 Sélections
    $es = $d['selections'] ?? [];
    if (($es['nb_selections'] ?? 0) > 0) {
        $bio[] = $es['nb_selections'] . " sélections nationales pour " . $es['nb_athletes'] . " athlètes.";
    }
    // §8 Top clubs
    if (!empty($d['top_clubs'])) {
        $tc3 = array_slice($d['top_clubs'], 0, 3);
        $parts = [];
        foreach ($tc3 as $tc) $parts[] = $tc['club'] . " (" . $tc['nb_athletes'] . " athlètes, " . $tc['nb_records'] . " records)";
        $bio[] = "Clubs les plus représentés : " . implode(' ; ', $parts) . ".";
    }
    // §9 Top villes
    if (!empty($d['top_villes'])) {
        $tv3 = array_slice($d['top_villes'], 0, 3);
        $parts = [];
        foreach ($tv3 as $tv) $parts[] = $tv['ville'] . " (" . $tv['nb_records'] . " records)";
        $bio[] = "Villes principales : " . implode(', ', $parts) . ".";
    }
    // §10 Niveaux
    if (!empty($d['niveaux_resultats'])) {
        $top3Niv = array_slice($d['niveaux_resultats'], 0, 3);
        $parts = [];
        foreach ($top3Niv as $tn) $parts[] = $tn['niveau'] . " (" . $tn['count'] . ")";
        $bio[] = "Niveaux de compétition dominants : " . implode(', ', $parts) . ".";
    }
    // §11 Évolution
    if (!empty($d['resultats_par_annee'])) {
        $peak = $d['resultats_par_annee'][0];
        $bio[] = "Année la plus active : " . $peak['annee'] . " avec " . $peak['nb_resultats'] . " résultats et " . $peak['nb_athletes'] . " athlètes.";
    }
    // §12 Progressions
    $epr = $d['progressions'] ?? [];
    if (($epr['nb_progressions'] ?? 0) > 0) {
        $bio[] = $epr['nb_progressions'] . " progressions enregistrées pour " . $epr['nb_athletes'] . " athlètes, témoignant d'une discipline avec un fort potentiel de développement.";
    }
    $bioText = implode("\n\n", $bio);
?>
<h2>Résumé</h2>
<div class="card" style="line-height:1.8;">
    <div id="epResumeText"><?= nl2br(htmlspecialchars($bioText)) ?></div>
    <button onclick="var t=document.getElementById('epResumeText').innerText;navigator.clipboard.writeText(t).then(function(){alert('Texte copié !');});" style="margin-top:12px;padding:6px 18px;background:#6c5ce730;border:1px solid #6c5ce7;border-radius:6px;color:#a29bfe;font-size:12px;cursor:pointer;">Copier le texte</button>
</div>
<?php endif; // resume ?>

<?php endif; // data found ?>
<?php endif; // nom not empty ?>


<?php
// ================================================================
//  VILLES
// ================================================================
elseif ($page === 'villes'):
    $openVille = $_GET['open'] ?? '';

    // ========== MODE DETAIL : ?page=villes&open=NomVille ==========
    if ($openVille !== ''):
        $vp = max(1, (int)($_GET['vp'] ?? 1));
        $vniv = $_GET['niv'] ?? '';
        $vnat = $_GET['nat'] ?? '';
        $vans = $_GET['ans'] ?? '';
        $apiUrl = "$BASE_API/ville_stats.php?nom=" . urlencode($openVille) . "&page=$vp&limit=30";
        if ($vniv !== '') $apiUrl .= "&niv=" . urlencode($vniv);
        if ($vnat !== '') $apiUrl .= "&nat=" . urlencode($vnat);
        if ($vans !== '') $apiUrl .= "&ans=" . urlencode($vans);
        $vd = apiCall($apiUrl);
        $vs = $_GET['s'] ?? 'all';
        // Base URL pour pagination (préserve tous les filtres)
        $vpBase = '?page=villes&open=' . urlencode($openVille);
        if ($vniv !== '') $vpBase .= '&niv=' . urlencode($vniv);
        if ($vnat !== '') $vpBase .= '&nat=' . urlencode($vnat);
        if ($vans !== '') $vpBase .= '&ans=' . urlencode($vans);
        if ($vd && ($vd['success'] ?? false)):
?>

<div class="profil-header">
    <div>
        <div class="name"><?= htmlspecialchars($vd['ville']['nom_ville']) ?></div>
        <div class="meta">
            <b>Athlètes :</b> <?= number_format($vd['total_athletes'], 0, ',', ' ') ?> |
            <b>Résultats :</b> <?= number_format($vd['total_resultats'], 0, ',', ' ') ?>
            <?php if ($vd['annee_debut']): ?> | <b>Période :</b> <?= $vd['annee_debut'] ?> — <?= $vd['annee_fin'] ?: '...' ?><?php endif; ?>
            <br><a href="?page=villes" style="color:#8b949e;font-size:12px;">← Retour à la liste</a>
        </div>
    </div>
</div>

<?php
// ---- RESUME VILLE ----
$_vnom = htmlspecialchars($vd['ville']['nom_ville']);
$_vtotal = $vd['total_athletes'];
$_vres = $vd['total_resultats'];
$_vep = $vd['total_epreuves'];
$_vcl = $vd['total_clubs'];
$_vdeb = $vd['annee_debut'];
$_vfin = $vd['annee_fin'];
$_vsexe = $vd['par_sexe'] ?? [];
$_vcat = $vd['par_categorie'] ?? [];
$_vnatl = $vd['nationalites'] ?? [];
$_vniv = $vd['niveaux'] ?? [];
$_vtopEp = $vd['top_epreuves'] ?? [];
$_vtopCl = $vd['top_clubs'] ?? [];
$_vtopAth = $vd['top_athletes'] ?? [];
$_vannees = $vd['annees'] ?? [];
$_vselAns = $vd['selected_annees'] ?? [];
$_vselNiv = $vd['selected_niveaux'] ?? [];
$_vselNat = $vd['selected_nationalites'] ?? [];
$_hasFilter = !empty($_vselAns) || !empty($_vselNiv) || !empty($_vselNat);
$_vmed = $vd['medailles'] ?? ['or'=>0,'argent'=>0,'bronze'=>0];
$_vtotalMed = $vd['total_medailles'] ?? 0;
$_vmedDetail = $vd['medailles_detail'] ?? [];
$_vtopMedAth = $vd['top_medaille_athletes'] ?? [];
$_vpod = $vd['podiums'] ?? ['1er'=>0,'2e'=>0,'3e'=>0];
$_vtotalPod = $vd['total_podiums'] ?? 0;
$_vpodNiv = $vd['podium_niveaux'] ?? [];
$_vrec = $vd['records'] ?? [];
$_vtotalRec = $vd['total_records'] ?? 0;
$_vsel = $vd['selections'] ?? ['nb_selections'=>0,'nb_athletes'=>0,'nb_competitions'=>0];
$_vprog = $vd['progressions'] ?? ['nb_progressions'=>0,'nb_epreuves'=>0];
$_vresAnnee = $vd['resultats_par_annee'] ?? [];

$bio = [];

// P1 — Présentation générale
$p1 = "$_vnom est un site de compétitions d'athlétisme";
if ($_vdeb && $_vfin) {
    if ($_vdeb == $_vfin) $p1 .= " actif en $_vdeb";
    else $p1 .= " actif de $_vdeb à $_vfin, soit " . ($_vfin - $_vdeb + 1) . " saisons";
}
$p1 .= ". Au total, " . number_format($_vtotal, 0, ',', ' ') . " athlètes y ont été enregistrés pour " . number_format($_vres, 0, ',', ' ') . " résultats";
if ($_vep > 0) $p1 .= " répartis sur " . number_format($_vep, 0, ',', ' ') . " épreuves différentes";
$p1 .= ".";
$bio[] = $p1;

// P1b — Filtre actif
if ($_hasFilter) {
    $parts = [];
    if (!empty($_vselAns)) $parts[] = "année(s) : " . implode(', ', $_vselAns);
    if (!empty($_vselNiv)) $parts[] = "niveau(x) : " . implode(', ', $_vselNiv);
    if (!empty($_vselNat)) $parts[] = "nationalité(s) : " . implode(', ', $_vselNat);
    $bio[] = "Ce résumé est filtré par " . implode(' ; ', $parts) . ".";
}

// P2 — Répartition par sexe
if (!empty($_vsexe)) {
    $sparts = [];
    foreach ($_vsexe as $s => $c) {
        $label = $s === 'M' ? 'hommes' : ($s === 'F' ? 'femmes' : 'non renseigné');
        $pct = $_vtotal > 0 ? round($c / $_vtotal * 100) : 0;
        $sparts[] = number_format($c, 0, ',', ' ') . " $label ($pct%)";
    }
    $bio[] = "La répartition par sexe compte " . implode(' et ', $sparts) . ".";
}

// P3 — Catégories
if (!empty($_vcat)) {
    $top3cat = array_slice($_vcat, 0, 3, true);
    $cparts = [];
    foreach ($top3cat as $cat => $c) {
        $cparts[] = "$cat (" . number_format($c, 0, ',', ' ') . ")";
    }
    $p3 = "Les catégories les plus représentées sont " . implode(', ', $cparts);
    if (count($_vcat) > 3) $p3 .= " parmi " . count($_vcat) . " catégories au total";
    $p3 .= ".";
    $bio[] = $p3;
}

// P4 — Nationalités
if (!empty($_vnatl)) {
    $top3nat = array_slice($_vnatl, 0, 3, true);
    $nparts = [];
    foreach ($top3nat as $nat => $c) {
        $nparts[] = "$nat (" . number_format($c, 0, ',', ' ') . " athlètes)";
    }
    $p4 = "En dehors des athlètes français, les nationalités étrangères les plus présentes sont " . implode(', ', $nparts);
    if (count($_vnatl) > 3) $p4 .= ", soit " . count($_vnatl) . " nationalités différentes au total";
    $p4 .= ".";
    $bio[] = $p4;
}

// P5 — Niveaux de compétition
if (!empty($_vniv)) {
    $topNiv = array_slice($_vniv, 0, 3);
    $nparts = [];
    foreach ($topNiv as $niv) {
        $nparts[] = $niv['niveau'] . " (" . $niv['pct'] . "%, " . number_format($niv['count'], 0, ',', ' ') . " résultats)";
    }
    $p5 = "Les niveaux de compétition dominants sont " . implode(', ', $nparts);
    if (count($_vniv) > 3) $p5 .= " sur " . count($_vniv) . " niveaux différents";
    $p5 .= ".";
    // Identifier la famille dominante
    $famCount = ['D' => 0, 'R' => 0, 'N' => 0, 'I' => 0];
    foreach ($_vniv as $niv) {
        $f = $niv['niveau'][0] ?? '';
        if (isset($famCount[$f])) $famCount[$f] += $niv['count'];
    }
    arsort($famCount);
    $famLabels = ['D' => 'départemental', 'R' => 'régional', 'N' => 'national', 'I' => 'international'];
    $topFam = array_keys($famCount)[0];
    if ($famCount[$topFam] > 0 && isset($famLabels[$topFam])) {
        $famPct = $_vres > 0 ? round($famCount[$topFam] / array_sum(array_values($famCount)) * 100) : 0;
        $p5 .= " Le niveau " . $famLabels[$topFam] . " représente $famPct% de l'ensemble des résultats classés.";
    }
    $bio[] = $p5;
}

// P6 — Top épreuves
if (!empty($_vtopEp)) {
    $top3ep = array_slice($_vtopEp, 0, 3);
    $eparts = [];
    foreach ($top3ep as $e) {
        $eparts[] = $e['epreuve'] . " (" . number_format($e['nb_resultats'], 0, ',', ' ') . " résultats, " . $e['nb_athletes'] . " athlètes)";
    }
    $bio[] = "Les épreuves phares de $_vnom sont " . implode(', ', $eparts) . ".";
}

// P7 — Top clubs
if (!empty($_vtopCl)) {
    $top3cl = array_slice($_vtopCl, 0, 3);
    $clparts = [];
    foreach ($top3cl as $c) {
        $clparts[] = $c['club'] . " (" . $c['nb_athletes'] . " athlètes)";
    }
    $p7 = "Les clubs les plus actifs sur ce site sont " . implode(', ', $clparts);
    if ($_vcl > 3) $p7 .= " parmi " . number_format($_vcl, 0, ',', ' ') . " clubs au total";
    $p7 .= ".";
    $bio[] = $p7;
}

// P8 — Top athlètes
if (!empty($_vtopAth)) {
    $top3ath = array_slice($_vtopAth, 0, 3);
    $aparts = [];
    foreach ($top3ath as $a) {
        $info = $a['nom_complet'];
        $details = [];
        if ($a['categorie']) $details[] = $a['categorie'];
        if ($a['sexe']) $details[] = ($a['sexe'] === 'M' ? 'H' : 'F');
        $details[] = $a['nb_resultats'] . " résultats";
        if ($a['best_place'] && $a['best_place'] <= 3) $details[] = "meilleure place : " . $a['best_place'] . ($a['best_place'] === 1 ? 'er' : 'e');
        $info .= " (" . implode(', ', $details) . ")";
        $aparts[] = $info;
    }
    $bio[] = "Les athlètes les plus actifs sont " . implode(' ; ', $aparts) . ".";
}

// P9 — Médailles
if ($_vtotalMed > 0) {
    $pMed = "Ce site a accueilli " . number_format($_vtotalMed, 0, ',', ' ') . " médaille" . ($_vtotalMed > 1 ? 's' : '');
    $medDet = [];
    if ($_vmed['or'] > 0) { $pctOr = round($_vmed['or'] / $_vtotalMed * 100); $medDet[] = $_vmed['or'] . " en or ($pctOr%)"; }
    if ($_vmed['argent'] > 0) { $pctAr = round($_vmed['argent'] / $_vtotalMed * 100); $medDet[] = $_vmed['argent'] . " en argent ($pctAr%)"; }
    if ($_vmed['bronze'] > 0) { $pctBr = round($_vmed['bronze'] / $_vtotalMed * 100); $medDet[] = $_vmed['bronze'] . " en bronze ($pctBr%)"; }
    if (!empty($medDet)) $pMed .= " (" . implode(', ', $medDet) . ")";
    $pMed .= ".";
    if (!empty($_vtopMedAth)) {
        $maParts = [];
        foreach (array_slice($_vtopMedAth, 0, 3) as $ma) {
            $maParts[] = $ma['athlete'] . " (" . $ma['total'] . " : " . $ma['or'] . "or/" . $ma['argent'] . "ar/" . $ma['bronze'] . "br)";
        }
        $pMed .= " Les plus médaillés : " . implode(' ; ', $maParts) . ".";
    }
    $bio[] = $pMed;
}

// P10 — Podiums
if ($_vtotalPod > 0) {
    $pPod = number_format($_vtotalPod, 0, ',', ' ') . " podium" . ($_vtotalPod > 1 ? 's' : '') . " enregistré" . ($_vtotalPod > 1 ? 's' : '');
    $podDet = [];
    if ($_vpod['1er'] > 0) { $pct1 = round($_vpod['1er'] / $_vtotalPod * 100); $podDet[] = $_vpod['1er'] . " première" . ($_vpod['1er'] > 1 ? 's' : '') . " place" . ($_vpod['1er'] > 1 ? 's' : '') . " ($pct1%)"; }
    if ($_vpod['2e'] > 0) { $pct2 = round($_vpod['2e'] / $_vtotalPod * 100); $podDet[] = $_vpod['2e'] . " deuxième" . ($_vpod['2e'] > 1 ? 's' : '') . " place" . ($_vpod['2e'] > 1 ? 's' : '') . " ($pct2%)"; }
    if ($_vpod['3e'] > 0) { $pct3 = round($_vpod['3e'] / $_vtotalPod * 100); $podDet[] = $_vpod['3e'] . " troisième" . ($_vpod['3e'] > 1 ? 's' : '') . " place" . ($_vpod['3e'] > 1 ? 's' : '') . " ($pct3%)"; }
    if (!empty($podDet)) $pPod .= " (" . implode(', ', $podDet) . ")";
    $pPod .= ".";
    if (!empty($_vpodNiv)) {
        $pnParts = array_map(function($n) { return $n['niveau'] . " (" . $n['count'] . ")"; }, array_slice($_vpodNiv, 0, 3));
        $pPod .= " Niveaux de compétition des podiums : " . implode(', ', $pnParts) . ".";
    }
    $bio[] = $pPod;
}

// P11 — Records
if ($_vtotalRec > 0) {
    $pRec = number_format($_vtotalRec, 0, ',', ' ') . " record" . ($_vtotalRec > 1 ? 's' : '') . " personnel" . ($_vtotalRec > 1 ? 's' : '') . " " . ($_vtotalRec > 1 ? 'ont été établis' : 'a été établi') . " sur ce site.";
    if (!empty($_vrec)) {
        $recEx = [];
        foreach (array_slice($_vrec, 0, 3) as $r) {
            $recEx[] = $r['performance'] . " au " . $r['epreuve'] . " par " . $r['athlete'];
        }
        $pRec .= " Meilleurs records : " . implode(' ; ', $recEx) . ".";
    }
    $bio[] = $pRec;
}

// P12 — Sélections
if ($_vsel['nb_selections'] > 0) {
    $bio[] = number_format($_vsel['nb_athletes'], 0, ',', ' ') . " athlète" . ($_vsel['nb_athletes'] > 1 ? 's' : '') . " ayant concouru sur ce site " . ($_vsel['nb_athletes'] > 1 ? 'ont' : 'a') . " été sélectionné" . ($_vsel['nb_athletes'] > 1 ? 's' : '') . " en équipe nationale, pour " . number_format($_vsel['nb_selections'], 0, ',', ' ') . " sélection" . ($_vsel['nb_selections'] > 1 ? 's' : '') . " au total.";
}

// P13 — Progressions
if ($_vprog['nb_progressions'] > 0) {
    $bio[] = number_format($_vprog['nb_progressions'], 0, ',', ' ') . " progression" . ($_vprog['nb_progressions'] > 1 ? 's' : '') . " enregistrée" . ($_vprog['nb_progressions'] > 1 ? 's' : '') . " sur " . $_vprog['nb_epreuves'] . " épreuve" . ($_vprog['nb_epreuves'] > 1 ? 's' : '') . ".";
}

// P14 — Évolution par année
if (!empty($_vresAnnee) && count($_vresAnnee) > 1) {
    $best = $_vresAnnee[0];
    foreach ($_vresAnnee as $ra) {
        if ($ra['nb_resultats'] > $best['nb_resultats']) $best = $ra;
    }
    $bio[] = "L'année la plus active est " . $best['annee'] . " avec " . number_format($best['nb_resultats'], 0, ',', ' ') . " résultats et " . number_format($best['nb_athletes'], 0, ',', ' ') . " athlètes.";
}

// P15 — Années d'activité
if (!empty($_vannees)) {
    $nbAn = count($_vannees);
    $recent = $_vannees[0] ?? null;
    $old = end($_vannees);
    if ($nbAn > 1) {
        $bio[] = "Le site couvre $nbAn saisons, de $old à $recent. La saison la plus récente enregistrée est $recent.";
    } elseif ($nbAn === 1) {
        $bio[] = "Une seule saison est enregistrée pour ce site : $recent.";
    }
}

$bioText = implode("\n\n", $bio);
?>

<div class="chart-card" style="margin:16px 0;border-left:3px solid #6c5ce7;" id="villeBioCard">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#6c5ce720;color:#a29bfe;">&#128221;</span> Résumé</h3>
    <p id="villeBioText" style="color:#c8cfd8;line-height:1.8;font-size:14px;margin:0;white-space:pre-line;"><?= htmlspecialchars($bioText) ?></p>
    <button onclick="navigator.clipboard.writeText(document.getElementById('villeBioText').textContent).then(function(){alert('Résumé copié !')})" style="margin-top:12px;background:#253049;color:#a29bfe;border:1px solid #6c5ce740;border-radius:8px;padding:6px 14px;cursor:pointer;font-size:12px;">&#128203; Copier le texte</button>
</div>

<?php
    // Niveaux helper
    function villeNivStyle($code) {
        $nc = $code[0] ?? '';
        if ($nc === 'N') return ['#e11d4820','#e11d48','#fb7185'];
        if ($nc === 'I') return ['#c026d320','#c026d3','#e879f9'];
        if ($nc === 'R') return ['#0891b220','#0891b2','#22d3ee'];
        return ['#f9731620','#f97316','#fb923c'];
    }

    // Ordre logique des niveaux (du plus bas au plus haut)
    $nivOrdre = ['D8','D7','D6','D5','D4','D3','D2','D1','R6','R5','R4','R3','R2','R1','N4','N3','N2','N1','IE','IR'];
    $allNiveaux = $vd['niveaux'] ?? [];
    // Trier les niveaux selon l'ordre logique
    $nivMap = [];
    foreach ($allNiveaux as $niv) {
        $nivMap[$niv['niveau']] = $niv;
    }
    $nivOrdered = [];
    foreach ($nivOrdre as $code) {
        if (isset($nivMap[$code])) $nivOrdered[] = $nivMap[$code];
    }
    // Ajouter ceux qui ne sont pas dans l'ordre prédéfini
    foreach ($allNiveaux as $niv) {
        if (!in_array($niv['niveau'], $nivOrdre)) $nivOrdered[] = $niv;
    }
    // Niveaux sélectionnés (tous par défaut)
    $selectedNiv = $vd['selected_niveaux'] ?? [];
    $noneSelected = in_array('_none', $selectedNiv);
    $allSelected = empty($selectedNiv) || ($noneSelected && count($selectedNiv) === 1);
    if ($noneSelected) { $selectedNiv = []; $allSelected = false; }
    $allNivCodes = array_map(function($n) { return $n['niveau']; }, $allNiveaux);
?>

<!-- Courbe de distribution des niveaux + sélecteur -->
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#128200;</span> Distribution des niveaux</h3>
    <div style="margin-bottom:16px;">
        <canvas id="villeNivCurve" height="100"></canvas>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
        <button onclick="toggleAllNiv()" id="btnNivAll" style="padding:6px 16px;border-radius:8px;border:1px solid <?= $allSelected ? '#a78bfa' : '#30363d' ?>;background:<?= $allSelected ? 'linear-gradient(135deg,#7c3aed,#a78bfa)' : '#161b22' ?>;color:<?= $allSelected ? '#fff' : '#8b949e' ?>;cursor:pointer;font-size:13px;font-weight:600;">Tout</button>
        <?php $noneSelected = !$allSelected && empty($selectedNiv); ?>
        <button onclick="clearAllNiv()" id="btnNivNone" style="padding:6px 16px;border-radius:8px;border:1px solid <?= $noneSelected ? '#ef4444' : '#30363d' ?>;background:<?= $noneSelected ? '#ef444420' : '#161b22' ?>;color:<?= $noneSelected ? '#f87171' : '#8b949e' ?>;cursor:pointer;font-size:13px;font-weight:600;">Aucun</button>
        <?php foreach ($nivOrdered as $niv):
            $code = $niv['niveau'];
            [$bg,$bc,$tc] = villeNivStyle($code);
            $isActive = $allSelected || in_array($code, $selectedNiv);
        ?>
        <button class="niv-filter-btn" data-niv="<?= htmlspecialchars($code) ?>"
            onclick="toggleNiv('<?= htmlspecialchars($code) ?>')"
            style="padding:5px 12px;border-radius:8px;font-size:12px;cursor:pointer;transition:all .2s;
            border:1px solid <?= $isActive ? $bc : '#30363d' ?>;
            background:<?= $isActive ? $bg : '#161b22' ?>;
            color:<?= $isActive ? $tc : '#484f58' ?>;
            opacity:<?= $isActive ? '1' : '0.5' ?>;">
            <?= htmlspecialchars($code) ?> <span style="font-size:10px;opacity:.7;"><?= $niv['pct'] ?>%</span>
        </button>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function() {
    var allNivCodes = <?= json_encode(array_map(function($n) { return $n['niveau']; }, $nivOrdered)) ?>;
    var selectedNiv = <?= json_encode($allSelected ? $allNivCodes : $selectedNiv) ?>;
    var allSelected = <?= $allSelected ? 'true' : 'false' ?>;

    function buildUrl(nivList) {
        var base = '?page=villes&open=<?= urlencode($openVille) ?>';
        if (nivList.length > 0) {
            base += '&niv=' + encodeURIComponent(nivList.join(','));
        }
        <?php if ($vnat !== ''): ?>base += '&nat=<?= urlencode($vnat) ?>';<?php endif; ?>
        <?php if ($vans !== ''): ?>base += '&ans=<?= urlencode($vans) ?>';<?php endif; ?>
        return base;
    }

    window.toggleAllNiv = function() {
        window.location.href = buildUrl([]);
    };

    window.clearAllNiv = function() {
        window.location.href = buildUrl(['_none']);
    };

    window.toggleNiv = function(code) {
        var current = allSelected ? allNivCodes.slice() : selectedNiv.slice();
        var idx = current.indexOf(code);
        if (idx >= 0) {
            current.splice(idx, 1);
        } else {
            current.push(code);
        }
        if (current.length === 0) {
            window.location.href = buildUrl(['_none']);
        } else if (current.length === allNivCodes.length) {
            window.location.href = buildUrl([]);
        } else {
            window.location.href = buildUrl(current);
        }
    };

    // Courbe
    var nivData = <?= json_encode($nivOrdered) ?>;
    var nivColors = {};
    <?php foreach ($nivOrdered as $niv) { [$bg,$bc,$tc] = villeNivStyle($niv['niveau']); echo "nivColors['" . addslashes($niv['niveau']) . "']='$tc';"; } ?>

    var activeCodes = allSelected ? allNivCodes : selectedNiv;
    var labels = nivData.map(function(n) { return n.niveau; });
    var counts = nivData.map(function(n) { return n.count; });
    var pointBg = nivData.map(function(n) { return activeCodes.indexOf(n.niveau) >= 0 ? nivColors[n.niveau] : '#30363d'; });
    var pointR = nivData.map(function(n) { return activeCodes.indexOf(n.niveau) >= 0 ? 6 : 3; });

    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('villeNivCurve').getContext('2d');
        var gradient = ctx.createLinearGradient(0, 0, ctx.canvas.width, 0);
        gradient.addColorStop(0, '#fb923c');
        gradient.addColorStop(0.4, '#22d3ee');
        gradient.addColorStop(0.7, '#fb7185');
        gradient.addColorStop(1, '#e879f9');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    borderColor: gradient,
                    backgroundColor: function(context) {
                        var c = context.chart.ctx;
                        var g = c.createLinearGradient(0, 0, 0, context.chart.height);
                        g.addColorStop(0, 'rgba(139,92,246,0.3)');
                        g.addColorStop(1, 'rgba(139,92,246,0)');
                        return g;
                    },
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointBackgroundColor: pointBg,
                    pointBorderColor: pointBg,
                    pointRadius: pointR,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var n = nivData[ctx.dataIndex];
                                return n.niveau + ' : ' + n.count.toLocaleString('fr-FR') + ' (' + n.pct + '%)';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: '#1e2a3a' },
                        ticks: { font: { size: 10 }, color: function(context) {
                            var code = labels[context.index];
                            return activeCodes.indexOf(code) >= 0 ? (nivColors[code] || '#8b949e') : '#30363d';
                        }}
                    },
                    y: {
                        grid: { color: '#1e2a3a' },
                        ticks: { callback: function(v) { return v >= 1000 ? (v/1000).toFixed(0) + 'k' : v; } }
                    }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    });
})();
</script>

<?php // ---- GRAPHIQUES ----
if (true):
    $vSexe = $vd['par_sexe'] ?? [];
    $vCat = $vd['par_categorie'] ?? [];
    $vNat = array_slice($vd['nationalites'] ?? [], 0, 10, true);
    $vEp = array_slice($vd['top_epreuves'] ?? [], 0, 10);
?>
<div class="charts-row" style="margin:20px 0;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">M/F</span> Répartition par sexe</h3><canvas id="villeSexeChart"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">Cat</span> Catégories</h3><canvas id="villeCatChart"></canvas></div>
</div>
<div class="charts-row" style="margin:20px 0;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">NAT</span> Nationalités</h3><canvas id="villeNatChart"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127939;</span> Top Épreuves</h3><canvas id="villeEpChart"></canvas></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('villeSexeChart'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($vSexe as $k => $v) echo "'" . ($k==='M'?'Hommes':($k==='F'?'Femmes':$k)) . "',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($vSexe)) ?>], backgroundColor: ['#3b82f6','#ec4899','#64748b'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } } } }
    });
    new Chart(document.getElementById('villeCatChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($vCat as $k => $v) echo "'$k',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($vCat)) ?>], backgroundColor: '#34d399', borderRadius: 4, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
    new Chart(document.getElementById('villeNatChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($vNat as $k => $v) echo "'$k',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($vNat)) ?>], backgroundColor: '#a78bfa', borderRadius: 4, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
    new Chart(document.getElementById('villeEpChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($vEp as $e) echo "'" . addslashes(mb_substr($e['epreuve'], 0, 20)) . "',"; ?>],
            datasets: [{ label: 'Résultats', data: [<?php foreach ($vEp as $e) echo $e['nb_resultats'] . ','; ?>],
                backgroundColor: function(ctx) { var g = ctx.chart.ctx.createLinearGradient(0,0,ctx.chart.width,0); g.addColorStop(0,'#ec4899'); g.addColorStop(1,'#f59e0b'); return g; },
                borderRadius: 6, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
    });
});
</script>
<?php endif; ?>

<?php // ---- NIVEAUX ----
if (true):
    $niveaux = $vd['niveaux'] ?? [];
    if (!empty($niveaux)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#f9731620;color:#fb923c;">&#127942;</span> Niveaux de compétition</h3>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
        <?php foreach ($niveaux as $niv):
            [$bg,$bc,$tc] = villeNivStyle($niv['niveau']);
        ?>
        <span style="display:inline-block;padding:6px 14px;border-radius:8px;font-size:13px;background:<?= $bg ?>;border:1px solid <?= $bc ?>40;color:<?= $tc ?>;">
            <?= htmlspecialchars($niv['niveau']) ?> <b><?= $niv['pct'] ?>%</b> <span style="opacity:.6;">(<?= number_format($niv['count'], 0, ',', ' ') ?>)</span>
        </span>
        <?php endforeach; ?>
    </div>
    <div class="charts-row" style="margin:0;">
        <div class="chart-card" style="margin:0;"><canvas id="villeNivChart"></canvas></div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var nivColors = {<?php foreach ($niveaux as $niv) { [$bg,$bc,$tc] = villeNivStyle($niv['niveau']); echo "'" . addslashes($niv['niveau']) . "':'$tc',"; } ?>};
        new Chart(document.getElementById('villeNivChart'), {
            type: 'doughnut',
            data: {
                labels: [<?php foreach ($niveaux as $niv) echo "'" . addslashes($niv['niveau']) . "',"; ?>],
                datasets: [{ data: [<?php foreach ($niveaux as $niv) echo $niv['count'] . ','; ?>],
                    backgroundColor: [<?php foreach ($niveaux as $niv) { [$bg,$bc,$tc] = villeNivStyle($niv['niveau']); echo "'$tc',"; } ?>], borderWidth: 0 }]
            },
            options: { responsive: true, cutout: '50%', plugins: { legend: { position: 'right', labels: { padding: 8, usePointStyle: true, font: { size: 11 } } } } }
        });
    });
    </script>
</div>
<?php endif; endif; ?>

<?php // ---- ATHLETES ----
if (true):
    $athList = $vd['top_athletes'] ?? [];
    if (!empty($athList)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">&#127939;</span> Athlètes (<?= number_format($vd['total_athletes'], 0, ',', ' ') ?>)</h3>
    <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:14px;align-items:center;">
        <span style="font-size:12px;color:#8b949e;margin-right:4px;">Filtrer par niveau :</span>
        <?php
        $athNivBase = '?page=villes&open=' . urlencode($openVille);
        ?>
        <button onclick="window.location.href='<?= $athNivBase ?>'" style="padding:4px 12px;border-radius:7px;font-size:11px;cursor:pointer;border:1px solid <?= $allSelected ? '#a78bfa' : '#30363d' ?>;background:<?= $allSelected ? 'linear-gradient(135deg,#7c3aed,#a78bfa)' : '#161b22' ?>;color:<?= $allSelected ? '#fff' : '#8b949e' ?>;font-weight:600;">Tout</button>
        <button onclick="clearAllNiv()" style="padding:4px 12px;border-radius:7px;font-size:11px;cursor:pointer;border:1px solid <?= $noneSelected ? '#ef4444' : '#30363d' ?>;background:<?= $noneSelected ? '#ef444420' : '#161b22' ?>;color:<?= $noneSelected ? '#f87171' : '#8b949e' ?>;font-weight:600;">Aucun</button>
        <?php foreach ($nivOrdered as $niv):
            $nc = $niv['niveau'];
            [$bg,$bc,$tc] = villeNivStyle($nc);
            $isAct = $allSelected || in_array($nc, $selectedNiv);
        ?>
        <button onclick="toggleNiv('<?= htmlspecialchars($nc) ?>')" style="padding:4px 10px;border-radius:7px;font-size:11px;cursor:pointer;border:1px solid <?= $isAct ? $bc : '#30363d' ?>;background:<?= $isAct ? $bg : '#161b22' ?>;color:<?= $isAct ? $tc : '#484f58' ?>;opacity:<?= $isAct ? '1' : '0.5' ?>;">
            <?= htmlspecialchars($nc) ?>
        </button>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>Niveaux</th><th>Résultats</th><th>Meilleure place</th><th></th></tr></table>
    <table class="bk-table">
        <?php foreach ($athList as $idx => $a): ?>
        <tr>
            <td><?= ($vp - 1) * 30 + $idx + 1 ?></td>
            <td><b><a href="?page=profil&id=<?= $a['athlete_id'] ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($a['nom_complet']) ?></a></b></td>
            <td><a href="?page=recherche&categorie=<?= urlencode($a['categorie'] ?? '') ?>&ville=<?= urlencode($openVille) ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= htmlspecialchars($a['categorie'] ?? '') ?></span></a></td>
            <td><a href="?page=recherche&sexe=<?= urlencode($a['sexe'] ?? '') ?>&ville=<?= urlencode($openVille) ?>" style="text-decoration:none;"><span class="badge badge-<?= strtolower($a['sexe'] ?? '') ?>"><?= htmlspecialchars($a['sexe'] ?? '') ?></span></a></td>
            <td><?= nivBadgeHtml(highestNiveau($a['niveaux'] ?? [])) ?></td>
            <td><?= $a['nb_resultats'] ?></td>
            <td><?= $a['best_place'] ? $a['best_place'] . ($a['best_place'] === 1 ? 'er' : 'e') : '-' ?></td>
            <td><a href="?page=profil&id=<?= $a['athlete_id'] ?>">Profil</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>Niveaux</th><th>Résultats</th><th>Meilleure place</th><th></th></tr></table>
    </div>
    <?php if ($vd['pages_athletes'] > 1): ?>
    <div class="pager" style="margin-top:12px;">
        <?php if ($vp > 1): ?><a href="<?= $vpBase ?>&vp=<?= $vp - 1 ?>">Précédent</a><?php endif; ?>
        <?php for ($i = max(1,$vp-3); $i <= min($vd['pages_athletes'],$vp+3); $i++): ?>
            <?php if ($i == $vp): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="<?= $vpBase ?>&vp=<?= $i ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($vp < $vd['pages_athletes']): ?><a href="<?= $vpBase ?>&vp=<?= $vp + 1 ?>">Suivant</a><?php endif; ?>
        <span class="info">(<?= $vd['pages_athletes'] ?> pages)</span>
    </div>
    <?php endif; ?>
</div>
<?php endif; endif; ?>

<?php // ---- EPREUVES ----
if (true):
    $epList = $vd['top_epreuves'] ?? [];
    if (!empty($epList)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127941;</span> Épreuves (<?= number_format($vd['total_epreuves'], 0, ',', ' ') ?>)</h3>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Résultats</th><th>Athlètes</th><th>Top niveaux</th></tr></table>
    <table class="bk-table">
        <?php foreach ($epList as $idx => $e): ?>
        <tr>
            <td><?= ($vp - 1) * 30 + $idx + 1 ?></td>
            <td><b><a href="?page=recherche&epreuve=<?= urlencode($e['epreuve']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($e['epreuve']) ?></a></b></td>
            <td><?= $e['nb_resultats'] ?></td>
            <td><?= $e['nb_athletes'] ?></td>
            <td><?php if (!empty($e['top_niveaux'])): ?><?php foreach ($e['top_niveaux'] as $eniv):
                [$ebg,$ebc,$etc] = villeNivStyle($eniv['niveau']);
            ?><span style="display:inline-block;margin:1px 2px;padding:2px 8px;border-radius:6px;font-size:11px;background:<?= $ebg ?>;border:1px solid <?= $ebc ?>40;color:<?= $etc ?>;"><?= htmlspecialchars($eniv['niveau']) ?> <b><?= $eniv['pct'] ?>%</b></span><?php endforeach; ?><?php else: ?>-<?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Résultats</th><th>Athlètes</th><th>Top niveaux</th></tr></table>
    </div>
    <?php if ($vd['pages_epreuves'] > 1): ?>
    <div class="pager" style="margin-top:12px;">
        <?php if ($vp > 1): ?><a href="<?= $vpBase ?>&vp=<?= $vp - 1 ?>">Précédent</a><?php endif; ?>
        <?php for ($i = max(1,$vp-3); $i <= min($vd['pages_epreuves'],$vp+3); $i++): ?>
            <?php if ($i == $vp): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="<?= $vpBase ?>&vp=<?= $i ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($vp < $vd['pages_epreuves']): ?><a href="<?= $vpBase ?>&vp=<?= $vp + 1 ?>">Suivant</a><?php endif; ?>
        <span class="info">(<?= $vd['pages_epreuves'] ?> pages)</span>
    </div>
    <?php endif; ?>
</div>
<?php endif; endif; ?>

<?php // ---- CLUBS ----
if (true):
    $clList = $vd['top_clubs'] ?? [];
    if (!empty($clList)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#127963;</span> Clubs (<?= number_format($vd['total_clubs'], 0, ',', ' ') ?>)</h3>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Club</th><th>Athlètes</th><th>Top niveaux</th></tr></table>
    <table class="bk-table">
        <?php foreach ($clList as $idx => $c): ?>
        <tr>
            <td><?= ($vp - 1) * 30 + $idx + 1 ?></td>
            <td><b><a href="?page=clubs&open=<?= urlencode($c['club']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($c['club']) ?></a></b></td>
            <td><?= $c['nb_athletes'] ?></td>
            <td><?php if (!empty($c['top_niveaux'])): ?><?php foreach ($c['top_niveaux'] as $cniv):
                [$cbg,$cbc,$ctc] = villeNivStyle($cniv['niveau']);
            ?><span style="display:inline-block;margin:1px 2px;padding:2px 8px;border-radius:6px;font-size:11px;background:<?= $cbg ?>;border:1px solid <?= $cbc ?>40;color:<?= $ctc ?>;"><?= htmlspecialchars($cniv['niveau']) ?> <b><?= $cniv['pct'] ?>%</b></span><?php endforeach; ?><?php else: ?>-<?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Club</th><th>Athlètes</th><th>Top niveaux</th></tr></table>
    </div>
    <?php if ($vd['pages_clubs'] > 1): ?>
    <div class="pager" style="margin-top:12px;">
        <?php if ($vp > 1): ?><a href="<?= $vpBase ?>&vp=<?= $vp - 1 ?>">Précédent</a><?php endif; ?>
        <?php for ($i = max(1,$vp-3); $i <= min($vd['pages_clubs'],$vp+3); $i++): ?>
            <?php if ($i == $vp): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="<?= $vpBase ?>&vp=<?= $i ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($vp < $vd['pages_clubs']): ?><a href="<?= $vpBase ?>&vp=<?= $vp + 1 ?>">Suivant</a><?php endif; ?>
        <span class="info">(<?= $vd['pages_clubs'] ?> pages)</span>
    </div>
    <?php endif; ?>
</div>
<?php endif; endif; ?>

<?php // ---- MEDAILLES ----
if ($_vtotalMed > 0):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#127942;</span> Médailles (<?= number_format($_vtotalMed, 0, ',', ' ') ?>)</h3>
    <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#f59e0b10;border:1px solid #f59e0b30;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#fbbf24;"><?= $_vmed['or'] ?></div>
            <div style="font-size:12px;color:#8b949e;">Or<?php if ($_vtotalMed > 0): ?> (<?= round($_vmed['or'] / $_vtotalMed * 100) ?>%)<?php endif; ?></div>
        </div>
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#94a3b810;border:1px solid #94a3b830;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#94a3b8;"><?= $_vmed['argent'] ?></div>
            <div style="font-size:12px;color:#8b949e;">Argent<?php if ($_vtotalMed > 0): ?> (<?= round($_vmed['argent'] / $_vtotalMed * 100) ?>%)<?php endif; ?></div>
        </div>
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#b4540010;border:1px solid #b4540030;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#cd7f32;"><?= $_vmed['bronze'] ?></div>
            <div style="font-size:12px;color:#8b949e;">Bronze<?php if ($_vtotalMed > 0): ?> (<?= round($_vmed['bronze'] / $_vtotalMed * 100) ?>%)<?php endif; ?></div>
        </div>
    </div>
    <?php if (!empty($_vmedDetail)): ?>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>Type</th><th>Athlète</th><th>Épreuve</th><th>Compétition</th><th>Année</th></tr></table>
    <table class="bk-table">
        <?php foreach ($_vmedDetail as $md): ?>
        <tr>
            <td><span style="font-weight:600;color:<?= strtolower($md['type'])==='or'?'#fbbf24':(strtolower($md['type'])==='argent'?'#94a3b8':'#cd7f32') ?>;"><?= ucfirst(htmlspecialchars($md['type'])) ?></span></td>
            <td><b><a href="?page=profil&id=<?= $md['athlete_id'] ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($md['athlete']) ?></a></b></td>
            <td><?php if (!empty($md['epreuve'])): ?><a href="?page=recherche&epreuve=<?= urlencode($md['epreuve']) ?>&ville=<?= urlencode($openVille) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($md['epreuve']) ?></a><?php else: ?>-<?php endif; ?></td>
            <td><?php if (!empty($md['competition'])): ?><a href="?page=recherche&competition=<?= urlencode($md['competition']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($md['competition']) ?></a><?php else: ?>-<?php endif; ?></td>
            <td><?= $md['annee'] ?? '-' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>Type</th><th>Athlète</th><th>Épreuve</th><th>Compétition</th><th>Année</th></tr></table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php // ---- PODIUMS ----
if ($_vtotalPod > 0):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#127941;</span> Podiums (<?= number_format($_vtotalPod, 0, ',', ' ') ?>)</h3>
    <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#fbbf2410;border:1px solid #fbbf2430;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#fbbf24;"><?= $_vpod['1er'] ?></div>
            <div style="font-size:12px;color:#8b949e;">1ère place<?php if ($_vtotalPod > 0): ?> (<?= round($_vpod['1er'] / $_vtotalPod * 100) ?>%)<?php endif; ?></div>
        </div>
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#94a3b810;border:1px solid #94a3b830;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#94a3b8;"><?= $_vpod['2e'] ?></div>
            <div style="font-size:12px;color:#8b949e;">2ème place<?php if ($_vtotalPod > 0): ?> (<?= round($_vpod['2e'] / $_vtotalPod * 100) ?>%)<?php endif; ?></div>
        </div>
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#cd7f3210;border:1px solid #cd7f3230;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#cd7f32;"><?= $_vpod['3e'] ?></div>
            <div style="font-size:12px;color:#8b949e;">3ème place<?php if ($_vtotalPod > 0): ?> (<?= round($_vpod['3e'] / $_vtotalPod * 100) ?>%)<?php endif; ?></div>
        </div>
    </div>
    <?php if (!empty($_vpodNiv)): ?>
    <div style="margin-top:8px;color:#8b949e;font-size:12px;">Niveaux de compétition :
        <?php foreach ($_vpodNiv as $pn):
            [$pnbg,$pnbc,$pntc] = villeNivStyle($pn['niveau'] ?? 'D');
        ?>
        <span style="display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;margin:2px;background:<?= $pnbg ?>;border:1px solid <?= $pnbc ?>40;color:<?= $pntc ?>;"><?= htmlspecialchars($pn['niveau']) ?> (<?= $pn['count'] ?>)</span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php // ---- RECORDS ----
if ($_vtotalRec > 0):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#ef444420;color:#f87171;">&#9201;</span> Records personnels (<?= number_format($_vtotalRec, 0, ',', ' ') ?>)</h3>
    <?php if (!empty($_vrec)): ?>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>Épreuve</th><th>Performance</th><th>Niveaux</th><th>Date</th></tr></table>
    <table class="bk-table">
        <?php foreach ($_vrec as $ri => $r): ?>
        <tr>
            <td><?= $ri + 1 ?></td>
            <td><b><a href="?page=profil&id=<?= $r['athlete_id'] ?>&s=records" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($r['athlete']) ?></a></b></td>
            <td><a href="?page=recherche&categorie=<?= urlencode($r['categorie'] ?? '') ?>&ville=<?= urlencode($openVille) ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= htmlspecialchars($r['categorie'] ?? '-') ?></span></a></td>
            <td><a href="?page=recherche&sexe=<?= urlencode($r['sexe'] ?? '') ?>&ville=<?= urlencode($openVille) ?>" style="text-decoration:none;"><span class="badge badge-<?= strtolower($r['sexe'] ?? '') ?>"><?= htmlspecialchars($r['sexe'] ?? '-') ?></span></a></td>
            <td><?php if (!empty($r['epreuve'])): ?><a href="?page=recherche&epreuve=<?= urlencode($r['epreuve']) ?>&ville=<?= urlencode($openVille) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($r['epreuve']) ?></a><?php else: ?>-<?php endif; ?></td>
            <td><span class="perf-val"><?= htmlspecialchars($r['performance'] ?? '-') ?></span></td>
            <td><?= nivBadgeHtml(highestNiveau($r['niveaux'] ?? [])) ?></td>
            <td style="font-size:12px;color:#8b949e;"><?= $r['date'] ? date('d/m/Y', strtotime($r['date'])) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>Épreuve</th><th>Performance</th><th>Niveaux</th><th>Date</th></tr></table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php // ---- EVOLUTION PAR ANNEE ----
if (!empty($_vresAnnee) && count($_vresAnnee) > 1):
    $raReversed = array_reverse($_vresAnnee);
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#6366f120;color:#818cf8;">&#128200;</span> Évolution par année</h3>
    <canvas id="villeEvoChart" style="max-height:300px;"></canvas>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new Chart(document.getElementById('villeEvoChart'), {
            type: 'line',
            data: {
                labels: [<?php foreach ($raReversed as $ra) echo $ra['annee'] . ','; ?>],
                datasets: [
                    { label: 'Résultats', data: [<?php foreach ($raReversed as $ra) echo $ra['nb_resultats'] . ','; ?>], borderColor: '#6366f1', backgroundColor: '#6366f120', fill: true, tension: 0.3, pointRadius: 3 },
                    { label: 'Athlètes', data: [<?php foreach ($raReversed as $ra) echo $ra['nb_athletes'] . ','; ?>], borderColor: '#34d399', backgroundColor: '#34d39920', fill: true, tension: 0.3, pointRadius: 3 }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top', labels: { usePointStyle: true, font: { size: 11 }, color: '#8b949e' } } },
                scales: {
                    x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } },
                    y: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e', callback: function(v) { return v >= 1000 ? (v/1000).toFixed(0) + 'k' : v; } } }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    });
    </script>
</div>
<?php endif; ?>

<?php // ---- NATIONALITES (sélecteur) ----
if (true):
    $natList = $vd['nationalites'] ?? [];
    $selNat = $vd['selected_nationalites'] ?? [];
    $allNatSelected = empty($selNat);
    $noNatSelected = !$allNatSelected && count($selNat) === 1 && $selNat[0] === '_none';
    if ($noNatSelected) { $selNat = []; $allNatSelected = false; }
    if (!empty($natList)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#127760;</span> Nationalités (<?= count($natList) ?>)</h3>
    <div style="display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
        <button onclick="natSelectAll()" style="padding:5px 14px;border-radius:7px;font-size:12px;cursor:pointer;border:1px solid <?= $allNatSelected ? '#fbbf24' : '#30363d' ?>;background:<?= $allNatSelected ? 'linear-gradient(135deg,#d97706,#fbbf24)' : '#161b22' ?>;color:<?= $allNatSelected ? '#fff' : '#8b949e' ?>;font-weight:600;">Tout</button>
        <button onclick="natSelectNone()" style="padding:5px 14px;border-radius:7px;font-size:12px;cursor:pointer;border:1px solid <?= $noNatSelected ? '#ef4444' : '#30363d' ?>;background:<?= $noNatSelected ? '#ef444420' : '#161b22' ?>;color:<?= $noNatSelected ? '#f87171' : '#8b949e' ?>;font-weight:600;">Aucun</button>
        <?php foreach ($natList as $nat => $cnt):
            $isNatActive = $allNatSelected || in_array($nat, $selNat);
        ?>
        <button onclick="toggleNat('<?= htmlspecialchars($nat) ?>')" style="padding:4px 10px;border-radius:7px;font-size:11px;cursor:pointer;
            border:1px solid <?= $isNatActive ? '#fbbf24' : '#30363d' ?>;
            background:<?= $isNatActive ? '#f59e0b20' : '#161b22' ?>;
            color:<?= $isNatActive ? '#fbbf24' : '#484f58' ?>;
            opacity:<?= $isNatActive ? '1' : '0.5' ?>;">
            <?= htmlspecialchars($nat) ?> <span style="font-size:10px;opacity:.7;"><?= $cnt ?></span>
        </button>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function() {
    var allNatCodes = <?= json_encode(array_keys($natList)) ?>;
    var selectedNat = <?= json_encode($allNatSelected ? array_keys($natList) : $selNat) ?>;
    var allNatSel = <?= $allNatSelected ? 'true' : 'false' ?>;

    function buildNatUrl(natList) {
        var base = '?page=villes&open=<?= urlencode($openVille) ?>';
        <?php if ($vniv !== ''): ?>base += '&niv=<?= urlencode($vniv) ?>';<?php endif; ?>
        <?php if ($vans !== ''): ?>base += '&ans=<?= urlencode($vans) ?>';<?php endif; ?>
        if (natList.length > 0 && natList.length < allNatCodes.length) {
            base += '&nat=' + encodeURIComponent(natList.join(','));
        }
        return base;
    }

    window.natSelectAll = function() { window.location.href = buildNatUrl([]); };
    window.natSelectNone = function() { window.location.href = buildNatUrl(['_none']); };

    window.toggleNat = function(code) {
        var current = allNatSel ? allNatCodes.slice() : selectedNat.slice();
        var idx = current.indexOf(code);
        if (idx >= 0) current.splice(idx, 1); else current.push(code);
        if (current.length === 0) {
            window.location.href = buildNatUrl(['_none']);
        } else if (current.length === allNatCodes.length) {
            window.location.href = buildNatUrl([]);
        } else {
            window.location.href = buildNatUrl(current);
        }
    };
})();
</script>
<?php endif; endif; ?>

<?php // ---- ANNEES (sélecteur) ----
if (true):
    $annees = $vd['annees'] ?? [];
    $selAns = $vd['selected_annees'] ?? [];
    $allAnsSelected = empty($selAns);
    $noAnsSelected = !$allAnsSelected && count($selAns) === 1 && $selAns[0] === 0;
    if ($noAnsSelected) { $selAns = []; $allAnsSelected = false; }
    if (!empty($annees)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#06b6d420;color:#22d3ee;">&#128197;</span> Années d'activité (<?= count($annees) ?> saisons)</h3>
    <div style="display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
        <button onclick="ansSelectAll()" style="padding:5px 14px;border-radius:7px;font-size:12px;cursor:pointer;border:1px solid <?= $allAnsSelected ? '#22d3ee' : '#30363d' ?>;background:<?= $allAnsSelected ? 'linear-gradient(135deg,#0891b2,#22d3ee)' : '#161b22' ?>;color:<?= $allAnsSelected ? '#fff' : '#8b949e' ?>;font-weight:600;">Tout</button>
        <button onclick="ansSelectNone()" style="padding:5px 14px;border-radius:7px;font-size:12px;cursor:pointer;border:1px solid <?= $noAnsSelected ? '#ef4444' : '#30363d' ?>;background:<?= $noAnsSelected ? '#ef444420' : '#161b22' ?>;color:<?= $noAnsSelected ? '#f87171' : '#8b949e' ?>;font-weight:600;">Aucun</button>
        <?php foreach ($annees as $y):
            $isAnsActive = $allAnsSelected || in_array($y, $selAns);
        ?>
        <button onclick="toggleAns(<?= $y ?>)" style="padding:4px 10px;border-radius:7px;font-size:11px;cursor:pointer;
            border:1px solid <?= $isAnsActive ? '#22d3ee' : '#30363d' ?>;
            background:<?= $isAnsActive ? '#0891b220' : '#161b22' ?>;
            color:<?= $isAnsActive ? '#22d3ee' : '#484f58' ?>;
            opacity:<?= $isAnsActive ? '1' : '0.5' ?>;">
            <?= $y ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function() {
    var allAnsCodes = <?= json_encode($annees) ?>;
    var selectedAns = <?= json_encode($allAnsSelected ? $annees : $selAns) ?>;
    var allAnsSel = <?= $allAnsSelected ? 'true' : 'false' ?>;

    function buildAnsUrl(ansList) {
        var base = '?page=villes&open=<?= urlencode($openVille) ?>';
        <?php if ($vniv !== ''): ?>base += '&niv=<?= urlencode($vniv) ?>';<?php endif; ?>
        <?php if ($vnat !== ''): ?>base += '&nat=<?= urlencode($vnat) ?>';<?php endif; ?>
        if (ansList.length > 0 && ansList.length < allAnsCodes.length) {
            base += '&ans=' + encodeURIComponent(ansList.join(','));
        }
        return base;
    }

    window.ansSelectAll = function() { window.location.href = buildAnsUrl([]); };
    window.ansSelectNone = function() { window.location.href = buildAnsUrl([0]); };

    window.toggleAns = function(year) {
        var current = allAnsSel ? allAnsCodes.slice() : selectedAns.slice();
        var idx = current.indexOf(year);
        if (idx >= 0) current.splice(idx, 1); else current.push(year);
        if (current.length === 0) {
            window.location.href = buildAnsUrl([0]);
        } else if (current.length === allAnsCodes.length) {
            window.location.href = buildAnsUrl([]);
        } else {
            window.location.href = buildAnsUrl(current);
        }
    };
})();
</script>
<!-- QR Code ville -->
<div class="qr-share">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?= urlencode('https://bokonzi.com/?page=villes&open=' . $openVille) ?>" alt="QR Code ville <?= htmlspecialchars($openVille) ?> — Bokonzi" width="120" height="120">
    <div class="qr-label">Scannez pour partager</div>
</div>
<?php endif; endif; ?>

<?php else: ?>
<div class="error">Ville non trouvée.</div>
<?php endif; ?>

<?php
    // ========== MODE LISTE : ?page=villes ==========
    else:
        $nomVille = $_GET['nom'] ?? '';
        $params = ['page' => $p, 'limit' => 50, 'has_athletes' => 1];
        if ($nomVille) $params['nom'] = $nomVille;
        $data = apiCall("$BASE_API/villes.php?" . http_build_query($params));
?>

<h1>Villes</h1>

<div class="live-search">
    <span class="ls-icon">&#128269;</span>
    <input type="text" id="lsVilles" placeholder="Rechercher une ville..." autocomplete="off">
    <div class="ls-status" id="lsVillesStatus"></div>
</div>
<div class="ls-results" id="lsVillesResults" style="display:none;"></div>

<div id="villesPaginated">
<?php if ($data && ($data['success'] ?? false)):
    $villeChartData = array_slice($data['villes'], 0, 10);
?>
<p class="subtitle"><?= number_format($data['total'], 0, ',', ' ') ?> villes</p>

<div class="charts-row" style="margin-bottom:20px;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#127961;</span> Top Villes (cette page)</h3><canvas id="villesChart"></canvas></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var vData = [<?php foreach ($villeChartData as $v) {
        echo "{label:'" . addslashes(mb_substr($v['nom_ville'], 0, 25)) . "',";
        echo "count:" . $v['nb_athletes'] . ",";
        echo "start:" . ($v['annee_debut'] ?: 2000) . ",";
        echo "end:" . ($v['annee_fin'] ?: 2025) . "},";
    } ?>];
    if (document.getElementById('villesChart')) {
        new Chart(document.getElementById('villesChart'), {
            type: 'bar',
            data: {
                labels: vData.map(v => v.label),
                datasets: [{ label: 'Athlètes', data: vData.map(v => v.count),
                    backgroundColor: function(ctx) { var g = ctx.chart.ctx.createLinearGradient(0,0,ctx.chart.width,0); g.addColorStop(0,'#10b981'); g.addColorStop(1,'#06b6d4'); return g; },
                    borderRadius: 6, barThickness: 18 }]
            },
            options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
        });
    }
});
</script>

<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Ville</th><th>Athlètes</th><th>Période</th><th>Top 3 niveaux</th></tr></table>
<table class="bk-table">
    <?php foreach ($data['villes'] as $idx => $v): ?>
    <tr>
        <td><?= ($p - 1) * 50 + $idx + 1 ?></td>
        <td><b><a href="?page=villes&open=<?= urlencode($v['nom_ville']) ?>" style="color:#a29bfe;text-decoration:none;cursor:pointer;"><?= htmlspecialchars($v['nom_ville']) ?></a></b></td>
        <td><?= $v['nb_athletes'] ?></td>
        <td><?= $v['annee_debut'] ? $v['annee_debut'] . ' - ' . ($v['annee_fin'] ?: '...') : '-' ?></td>
        <td><?php if (!empty($v['top_niveaux'])): ?><?php foreach ($v['top_niveaux'] as $niv): ?><?php
            $nc = $niv['niveau'][0] ?? '';
            if ($nc === 'N') { $bg = '#e11d4820'; $bc = '#e11d48'; $tc = '#fb7185'; }
            elseif ($nc === 'I') { $bg = '#c026d320'; $bc = '#c026d3'; $tc = '#e879f9'; }
            elseif ($nc === 'R') { $bg = '#0891b220'; $bc = '#0891b2'; $tc = '#22d3ee'; }
            else { $bg = '#f9731620'; $bc = '#f97316'; $tc = '#fb923c'; }
        ?><span style="display:inline-block;margin:1px 2px;padding:2px 8px;border-radius:6px;font-size:11px;background:<?= $bg ?>;border:1px solid <?= $bc ?>40;color:<?= $tc ?>;"><?= htmlspecialchars($niv['niveau']) ?> <b><?= $niv['pct'] ?>%</b></span><?php endforeach; ?><?php else: ?>-<?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Ville</th><th>Athlètes</th><th>Période</th><th>Top 3 niveaux</th></tr></table>
</div>

<?php if ($data['total_pages'] > 1): ?>
<div class="pager">
    <?php if ($p > 1): ?><a href="?page=villes&nom=<?= urlencode($nomVille) ?>&p=<?= $p - 1 ?>">Précédent</a><?php endif; ?>
    <?php for ($i = max(1,$p-3); $i <= min($data['total_pages'],$p+3); $i++): ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?page=villes&nom=<?= urlencode($nomVille) ?>&p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?page=villes&nom=<?= urlencode($nomVille) ?>&p=<?= $p + 1 ?>">Suivant</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
<?php endif; ?>

<?php
// ================================================================
//  COMPARATEUR D'ATHLETES
// ================================================================
elseif ($page === 'comparer'):
?>

<h1 style="background:linear-gradient(135deg,#f59e0b,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Comparateur</h1>
<p class="subtitle">Comparez athletes ou clubs visuellement</p>

<!-- ONGLETS Athletes / Clubs -->
<div class="section-tabs" style="margin-bottom:20px;">
    <a href="#" onclick="switchCmpTab('athletes');return false;" id="cmpTabAthletes" class="active">Athlètes</a>
    <a href="#" onclick="switchCmpTab('clubs');return false;" id="cmpTabClubs">Clubs</a>
</div>

<!-- ════════════════ TAB ATHLETES ════════════════ -->
<div id="cmpAthletesPanel">
<div class="compare-controls" style="background:linear-gradient(135deg,#12182a 0%,#1a2035 100%);border:1px solid #1e2a3a;border-radius:12px;padding:24px;margin:20px 0;">
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;position:relative;">
            <label style="display:block;color:#8b949e;font-size:12px;font-weight:600;text-transform:uppercase;margin-bottom:6px;">Ajouter un athlete</label>
            <input type="text" id="cmpSearch" placeholder="Rechercher par nom..." autocomplete="off"
                style="width:100%;padding:10px 14px;background:#0d1117;border:1px solid #1e2a3a;border-radius:8px;color:#c9d1d9;font-size:14px;">
            <div id="cmpSuggestions" style="background:#0d1117;border:1px solid #1e2a3a;border-radius:8px;max-height:200px;overflow-y:auto;display:none;position:absolute;z-index:50;width:100%;"></div>
        </div>
    </div>
    <div id="cmpSelected" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;"></div>
    <p id="cmpDescription" style="color:#8b949e;font-size:13px;line-height:1.6;margin:12px 0 16px;display:none;"></p>
    <div style="margin-top:16px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;">
            <label style="display:block;color:#8b949e;font-size:12px;font-weight:600;text-transform:uppercase;margin-bottom:6px;">Épreuve à comparer</label>
            <select id="cmpEpreuve" style="width:100%;padding:10px 14px;background:#0d1117;border:1px solid #1e2a3a;border-radius:8px;color:#c9d1d9;font-size:14px;">
                <option value="">-- Ajoutez des athletes d'abord --</option>
            </select>
        </div>
        <div style="display:flex;gap:8px;">
            <button onclick="compareNow()" style="padding:10px 28px;background:linear-gradient(135deg,#f59e0b,#ec4899);border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:600;cursor:pointer;">Comparer</button>
            <button id="btnCmpShare" onclick="copyCmpLink()" style="padding:10px 16px;background:#161b22;border:1px solid #1e2a3a;border-radius:8px;color:#8b949e;font-size:13px;cursor:pointer;">&#128279; Copier le lien</button>
        </div>
    </div>
</div>

<div id="cmpChartArea" style="display:none;">
    <div class="chart-card" style="margin:20px 0;">
        <h3><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">VS</span> <span id="cmpChartTitle">Comparaison</span></h3>
        <canvas id="cmpChart" style="max-height:500px;"></canvas>
    </div>
    <div class="chart-card" style="margin:20px 0;">
        <h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#128202;</span> Records personnels compares</h3>
        <div class="table-wrap" id="cmpRecordsTable"></div>
    </div>
    <div class="charts-row" style="margin:20px 0;">
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#127919;</span> Radar comparatif (epreuves communes)</h3>
            <canvas id="cmpRadar" style="max-height:400px;"></canvas>
        </div>
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">&#127942;</span> Médailles comparées</h3>
            <canvas id="cmpMedChart"></canvas>
        </div>
    </div>
    <div class="chart-card" style="margin:20px 0;border-left:3px solid #f59e0b;">
        <h3 style="margin-top:0;"><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#128221;</span> Analyse comparative</h3>
        <div id="cmpSummaryText" style="color:#c8cfd8;line-height:1.9;font-size:14px;"></div>
        <button onclick="navigator.clipboard.writeText(document.getElementById('cmpSummaryText').textContent).then(function(){alert('Analyse copiée !')})" style="margin-top:12px;background:#253049;color:#f59e0b;border:1px solid #f59e0b40;border-radius:8px;padding:6px 14px;cursor:pointer;font-size:12px;">&#128203; Copier le texte</button>
    </div>
</div>
</div>

<!-- ════════════════ TAB CLUBS ════════════════ -->
<div id="cmpClubsPanel" style="display:none;">
<div class="compare-controls" style="background:linear-gradient(135deg,#12182a 0%,#1a2035 100%);border:1px solid #1e2a3a;border-radius:12px;padding:24px;margin:20px 0;">
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;position:relative;">
            <label style="display:block;color:#8b949e;font-size:12px;font-weight:600;text-transform:uppercase;margin-bottom:6px;">Ajouter un club</label>
            <input type="text" id="cmpClubSearch" placeholder="Rechercher un club..." autocomplete="off"
                style="width:100%;padding:10px 14px;background:#0d1117;border:1px solid #1e2a3a;border-radius:8px;color:#c9d1d9;font-size:14px;">
            <div id="cmpClubSuggestions" style="background:#0d1117;border:1px solid #1e2a3a;border-radius:8px;max-height:200px;overflow-y:auto;display:none;position:absolute;z-index:50;width:100%;"></div>
        </div>
    </div>
    <div id="cmpClubSelected" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;"></div>
    <div style="margin-top:16px;display:flex;gap:8px;">
        <button onclick="compareClubsNow()" style="padding:10px 28px;background:linear-gradient(135deg,#8b5cf6,#06b6d4);border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:600;cursor:pointer;">Comparer les clubs</button>
        <button id="btnCmpShare" onclick="copyCmpLink()" style="padding:10px 16px;background:#161b22;border:1px solid #1e2a3a;border-radius:8px;color:#8b949e;font-size:13px;cursor:pointer;">&#128279; Copier le lien</button>
    </div>
</div>

<div id="cmpClubChartArea" style="display:none;">
    <!-- Effectifs -->
    <div class="charts-row" style="margin:20px 0;">
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#128101;</span> Nombre d'athletes</h3>
            <canvas id="clubCmpTotal"></canvas>
        </div>
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">M/F</span> Repartition par sexe</h3>
            <canvas id="clubCmpSexe"></canvas>
        </div>
    </div>
    <!-- Categories + Medailles -->
    <div class="charts-row" style="margin:20px 0;">
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">Cat</span> Athletes par categorie</h3>
            <canvas id="clubCmpCat"></canvas>
        </div>
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#127942;</span> Médailles</h3>
            <canvas id="clubCmpMed"></canvas>
        </div>
    </div>
    <!-- Top Epreuves + Top Athletes -->
    <div class="charts-row" style="margin:20px 0;">
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127939;</span> Top epreuves comparees</h3>
            <canvas id="clubCmpEpreuves"></canvas>
        </div>
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#128200;</span> Radar comparatif</h3>
            <canvas id="clubCmpRadar" style="max-height:400px;"></canvas>
        </div>
    </div>
    <!-- Tableau top athletes -->
    <div class="chart-card" style="margin:20px 0;">
        <h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">&#11088;</span> Top athletes par club</h3>
        <div class="table-wrap" id="clubCmpAthletesTable"></div>
    </div>
    <!-- Resume textuel comparaison clubs -->
    <div class="chart-card" style="margin:20px 0;border-left:3px solid #8b5cf6;">
        <h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#128221;</span> Resume comparatif</h3>
        <div id="cmpClubSummaryText" style="color:#c9d1d9;font-size:14px;line-height:1.8;padding:8px 0;"></div>
        <button onclick="var t=document.getElementById('cmpClubSummaryText').innerText;navigator.clipboard.writeText(t).then(function(){alert('Texte copié !');});" style="margin-top:10px;padding:6px 18px;background:#8b5cf630;border:1px solid #8b5cf6;border-radius:6px;color:#a78bfa;font-size:12px;cursor:pointer;">Copier le texte</button>
    </div>
</div>
</div>

<?php
// ================================================================
//  MON ESPACE — Suivis + Historique
// ================================================================
elseif ($page === 'espace'):
    // Rediriger si pas connecte
    $espUser = getCurrentUser($conn);
    if (!$espUser) {
        $loginUrl = $isLocal ? '/BK/login.php' : '/login.php';
        header('Location: ' . $loginUrl . '?redirect=espace');
        exit;
    }
    $espEmail = $conn->real_escape_string($espUser['email']);
    $espUserId = (int)$espUser['id_user'];

    // Charger athletes suivis
    $resAth = $conn->query("SELECT af.athlete_id_ext, af.created_at, a.nom_complet_athlete, a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete
        FROM athlete_follows af
        LEFT JOIN athletes a ON a.athlete_id_externe = af.athlete_id_ext
        WHERE af.email = '$espEmail'
        ORDER BY af.created_at DESC");
    $followedAthletes = [];
    if ($resAth) while ($row = $resAth->fetch_assoc()) $followedAthletes[] = $row;

    // Charger clubs suivis
    $resClub = $conn->query("SELECT cf.club_id, cf.created_at, c.nom_club
        FROM club_follows cf
        LEFT JOIN clubs c ON c.id_club = cf.club_id
        WHERE cf.email = '$espEmail'
        ORDER BY cf.created_at DESC");
    $followedClubs = [];
    if ($resClub) while ($row = $resClub->fetch_assoc()) $followedClubs[] = $row;

    // Charger historique recherches (par IP ou user, 50 derniers)
    $espIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $espIpEsc = $conn->real_escape_string($espIp);
    $resHist = $conn->query("SELECT query_text, search_type, source, entity_name, entity_id, created_at
        FROM search_tracking
        WHERE ip = '$espIpEsc'
        ORDER BY created_at DESC
        LIMIT 50");
    $history = [];
    if ($resHist) while ($row = $resHist->fetch_assoc()) $history[] = $row;
?>

<h1 style="margin-bottom:8px;">Mon Espace</h1>
<p style="color:#8b949e;font-size:14px;margin-bottom:24px;">Bienvenue <?= htmlspecialchars($espUser['prenom'] ?: $espUser['email']) ?> — gerez vos suivis et votre historique.</p>

<!-- ===== SUIVIS ===== -->
<div

<!-- Athletes suivis -->
<h2 style="font-size:18px;color:#a29bfe;margin-bottom:12px;">&#9889; Athletes suivis (<?= count($followedAthletes) ?>)</h2>
<?php if (empty($followedAthletes)): ?>
    <p style="color:#8b949e;font-size:14px;margin-bottom:24px;">Vous ne suivez aucun athlete. Visitez un profil et cliquez sur "Suivre" !</p>
<?php else: ?>
<div class="table-wrap" style="margin-bottom:24px;">
    <table class="bk-table"><tr><th>#</th><th>Athlete</th><th>Cat</th><th>Sexe</th><th>Nat</th><th>Suivi le</th><th></th></tr></table>
    <table class="bk-table">
    <?php foreach ($followedAthletes as $i => $fa): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><a href="?page=profil&id=<?= (int)$fa['athlete_id_ext'] ?>" style="color:#58a6ff;text-decoration:none;"><?= htmlspecialchars($fa['nom_complet_athlete'] ?: 'Athlete #' . $fa['athlete_id_ext']) ?></a></td>
            <td><?= htmlspecialchars($fa['categorie_athlete'] ?? '-') ?></td>
            <td><?= htmlspecialchars($fa['sexe_athlete'] ?? '-') ?></td>
            <td><?= htmlspecialchars($fa['nationalite_athlete'] ?? '-') ?></td>
            <td style="color:#8b949e;font-size:12px;"><?= $fa['created_at'] ? date('d/m/Y', strtotime($fa['created_at'])) : '-' ?></td>
            <td><button onclick="_espUnfollowAth(<?= (int)$fa['athlete_id_ext'] ?>, this)" style="padding:4px 10px;background:#ff6b6b20;border:1px solid #ff6b6b;border-radius:6px;color:#ff6b6b;font-size:11px;cursor:pointer;">Retirer</button></td>
        </tr>
    <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Athlete</th><th>Cat</th><th>Sexe</th><th>Nat</th><th>Suivi le</th><th></th></tr></table>
</div>
<?php endif; ?>

<!-- Clubs suivis -->
<h2 style="font-size:18px;color:#34d399;margin-bottom:12px;">&#127965; Clubs suivis (<?= count($followedClubs) ?>)</h2>
<?php if (empty($followedClubs)): ?>
    <p style="color:#8b949e;font-size:14px;">Vous ne suivez aucun club. Ouvrez un panneau club et cliquez sur "Suivre" !</p>
<?php else: ?>
<div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Club</th><th>Suivi le</th><th></th></tr></table>
    <table class="bk-table">
    <?php foreach ($followedClubs as $i => $fc): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><a href="?page=recherche&club=<?= urlencode(rtrim($fc['nom_club'] ?? '', '* ')) ?>" style="color:#58a6ff;text-decoration:none;"><?= htmlspecialchars($fc['nom_club'] ?: 'Club #' . $fc['club_id']) ?></a></td>
            <td style="color:#8b949e;font-size:12px;"><?= $fc['created_at'] ? date('d/m/Y', strtotime($fc['created_at'])) : '-' ?></td>
            <td><button onclick="_espUnfollowClub(<?= (int)$fc['club_id'] ?>, this)" style="padding:4px 10px;background:#ff6b6b20;border:1px solid #ff6b6b;border-radius:6px;color:#ff6b6b;font-size:11px;cursor:pointer;">Retirer</button></td>
        </tr>
    <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Club</th><th>Suivi le</th><th></th></tr></table>
</div>
<?php endif; ?>

</div>

<!-- ===== HISTORIQUE ===== -->
<div style="margin-top:32px;">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
    <h2 style="font-size:18px;color:#f59e0b;margin:0;">&#128337; Historique de recherches</h2>
    <?php if (!empty($history)): ?>
    <button onclick="_espClearHistory()" style="padding:6px 16px;background:#ff6b6b20;border:1px solid #ff6b6b;border-radius:6px;color:#ff6b6b;font-size:12px;font-weight:600;cursor:pointer;">Effacer l'historique</button>
    <?php endif; ?>
</div>

<?php if (empty($history)): ?>
    <p style="color:#8b949e;font-size:14px;">Aucun historique de recherche.</p>
<?php else: ?>
<div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Recherche / Consultation</th><th>Type</th><th>Source</th><th>Date</th></tr></table>
    <table class="bk-table" id="espHistTable">
    <?php
    $typeBadges = [
        'athlete' => 'background:#6c5ce720;color:#a29bfe;',
        'club'    => 'background:#34d39920;color:#34d399;',
        'epreuve' => 'background:#f59e0b20;color:#f59e0b;',
        'ville'   => 'background:#3b82f620;color:#60a5fa;',
        'general' => 'background:#8b949e20;color:#8b949e;',
    ];
    $srcLabels = ['live_search' => 'Recherche', 'page_view' => 'Consultation', 'panel_open' => 'Panneau'];
    foreach ($history as $i => $h):
        $label = $h['entity_name'] ?: $h['query_text'] ?: '-';
        $typeStyle = $typeBadges[$h['search_type']] ?? $typeBadges['general'];
        $srcLabel = $srcLabels[$h['source']] ?? $h['source'];
        $link = '';
        if ($h['search_type'] === 'athlete' && $h['entity_id']) {
            $link = '?page=profil&id=' . (int)$h['entity_id'];
        } elseif ($h['search_type'] === 'club' && $h['entity_name']) {
            $link = '?page=recherche&club=' . urlencode($h['entity_name']);
        } elseif ($h['search_type'] === 'epreuve' && $h['entity_name']) {
            $link = '?page=epreuves&nom=' . urlencode($h['entity_name']);
        } elseif ($h['search_type'] === 'ville' && $h['entity_name']) {
            $link = '?page=villes&open=' . urlencode($h['entity_name']);
        }
    ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?php if ($link): ?><a href="<?= $link ?>" style="color:#58a6ff;text-decoration:none;"><?= htmlspecialchars($label) ?></a><?php else: ?><?= htmlspecialchars($label) ?><?php endif; ?></td>
            <td><span style="<?= $typeStyle ?>padding:2px 8px;border-radius:10px;font-size:11px;"><?= htmlspecialchars($h['search_type']) ?></span></td>
            <td style="color:#8b949e;font-size:12px;"><?= htmlspecialchars($srcLabel) ?></td>
            <td style="color:#8b949e;font-size:12px;"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Recherche / Consultation</th><th>Type</th><th>Source</th><th>Date</th></tr></table>
</div>
<?php endif; ?>

</div>

<script>
function _espUnfollowAth(athleteId, btn) {
    if (!confirm('Retirer cet athlete de vos suivis ?')) return;
    fetch('<?= BK_BASE ?>/api/follow.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ athlete_id: athleteId })
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success && !d.following) {
            var tr = btn.closest('tr');
            tr.style.transition = 'opacity 0.3s';
            tr.style.opacity = '0';
            setTimeout(function() { tr.remove(); }, 300);
        }
    });
}

function _espUnfollowClub(clubId, btn) {
    if (!confirm('Retirer ce club de vos suivis ?')) return;
    fetch('<?= BK_BASE ?>/api/follow.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ club_id: clubId })
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success && !d.following) {
            var tr = btn.closest('tr');
            tr.style.transition = 'opacity 0.3s';
            tr.style.opacity = '0';
            setTimeout(function() { tr.remove(); }, 300);
        }
    });
}

function _espClearHistory() {
    if (!confirm('Effacer tout votre historique de recherches ?')) return;
    fetch('<?= BK_BASE ?>/api/search_track.php', {
        method: 'DELETE', credentials: 'same-origin'
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.ok || d.success) {
            document.getElementById('espHistTable').innerHTML = '';
            alert('Historique efface !');
        } else {
            alert('Erreur : ' + (d.error || 'echec'));
        }
    }).catch(function() { alert('Erreur reseau'); });
}
</script>

<?php
// ================================================================
//  TUTORIEL ANIMÉ
// ================================================================
elseif ($page === 'tuto'):
    $stats = apiCall("$BASE_API/stats.php");
    $nbAth = $stats['comptages']['athletes']['count'] ?? 330000;
    $nbClub = $stats['comptages']['clubs']['count'] ?? 3000;
    $nbEp = $stats['comptages']['epreuves']['count'] ?? 400;
?>

<div class="tuto-container">

<!-- Skip button -->
<button class="tuto-skip-btn" onclick="tutoSkip()">Passer &rarr;</button>

<!-- Progress bar -->
<div class="tuto-progress" id="tutoProgress">
<?php for ($ts = 1; $ts <= 9; $ts++): ?>
    <div class="tuto-progress-step" data-step="<?= $ts ?>" onclick="tutoGoStep(<?= $ts ?>)" style="cursor:pointer;">
        <span class="tuto-progress-dot"><?= $ts ?></span>
    </div>
<?php endfor; ?>
</div>

<!-- ========== ÉTAPE 1 : BIENVENUE ========== -->
<div class="tuto-step visible" data-step="1" id="tutoStep1">
    <div class="tuto-title" style="background:linear-gradient(135deg,#6c5ce7,#a29bfe);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
        <span id="tutoTyping"></span><span class="tuto-cursor"></span>
    </div>
    <p class="tuto-subtitle">
        Bokonzi est la base de données la plus complète de l'athlétisme français. Ce tutoriel interactif vous apprend à l'utiliser en quelques minutes.
    </p>
    <div class="tuto-cards">
        <div class="tuto-card"><div class="num" data-count="<?= $nbAth ?>">0</div><div class="label">Athlètes</div></div>
        <div class="tuto-card"><div class="num" data-count="<?= $nbClub ?>">0</div><div class="label">Clubs</div></div>
        <div class="tuto-card"><div class="num" data-count="<?= $nbEp ?>">0</div><div class="label">Épreuves</div></div>
    </div>
    <div style="text-align:center;margin-top:24px;">
        <button class="tuto-next-btn" onclick="tutoGoStep(2)" style="font-size:16px;padding:14px 36px;">Commencer le tutoriel &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 2 : RECHERCHER UN CLUB ========== -->
<div class="tuto-step" data-step="2" id="tutoStep2" style="display:none;">
    <div class="tuto-title" style="color:#34d399;">Recherchez un club</div>
    <p class="tuto-subtitle">Tapez le nom d'un club pour le trouver. Essayez par exemple <b>"Lille"</b>, <b>"Paris"</b> ou le nom de votre club.</p>

    <div class="tuto-live-search tuto-highlight" id="tutoClubSearchWrap">
        <span style="font-size:18px;flex-shrink:0;">&#128269;</span>
        <input type="text" id="tutoClubInput" placeholder="Tapez un nom de club..." autocomplete="off" oninput="_tutoSearchClubs(this.value)">
    </div>
    <div class="tuto-live-results" id="tutoClubResults"></div>
    <div id="tutoClubDone" style="display:none;text-align:center;margin-top:14px;">
        <div class="tuto-complete-badge">&#10003; Club sélectionné !</div>
        <button class="tuto-next-btn" onclick="tutoGoStep(3)" style="margin-top:10px;">Explorer le panneau club &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 3 : PANNEAU CLUB (EMBEDDED) ========== -->
<div class="tuto-step" data-step="3" id="tutoStep3" style="display:none;">
    <div class="tuto-title" style="color:#a29bfe;">Le panneau club</div>
    <p class="tuto-subtitle">Explorez les onglets du club. Naviguez dans au moins <b>2 onglets</b> pour continuer.</p>
    <p class="tuto-subtitle" id="tutoClubTabsProgress" style="font-size:12px;color:#5a6580;">Onglets visités : <span id="tutoTabsCount">0</span>/2</p>

    <div id="clubDetailPanelTuto" class="tuto-inline-panel">
        <div class="club-detail-header">
            <h2 id="clubDetailNameTuto" style="margin:0;"></h2>
            <span class="meta-info" id="clubDetailMetaTuto"></span>
        </div>
        <div class="club-detail-tabs" id="clubTabsTuto">
            <button class="club-detail-tab active" data-tab="epreuves" onclick="switchClubTabTuto('epreuves')">Épreuves</button>
            <button class="club-detail-tab" data-tab="nationalites" onclick="switchClubTabTuto('nationalites')">Nationalités</button>
            <button class="club-detail-tab" data-tab="stats" onclick="switchClubTabTuto('stats')">Stats</button>
            <button class="club-detail-tab" data-tab="performances" onclick="switchClubTabTuto('performances')">Performances</button>
            <button class="club-detail-tab" data-tab="resume" onclick="switchClubTabTuto('resume')">Résumé</button>
        </div>
        <!-- Bouton suite APRÈS les onglets (visible en haut) -->
        <div id="tutoClubTabsDoneTop" style="display:none;text-align:center;padding:10px;background:#10b98110;border:1px solid #10b98130;border-radius:8px;margin:8px 12px;">
            <div class="tuto-complete-badge" style="margin-bottom:6px;">&#10003; Onglets explorés !</div>
            <button class="tuto-next-btn" onclick="tutoGoStep(4)">Chercher un athlète &rarr;</button>
        </div>
        <div id="clubDetailContentTuto" class="club-detail-content">
            <div class="loading-msg">Sélectionnez un club à l'étape précédente</div>
        </div>
    </div>
    <!-- Bouton suite EN BAS (toujours visible par scroll) -->
    <div id="tutoClubTabsDone" style="display:none;text-align:center;margin-top:14px;">
        <div class="tuto-complete-badge">&#10003; Onglets explorés !</div>
        <button class="tuto-next-btn" onclick="tutoGoStep(4)" style="margin-top:10px;">Chercher un athlète &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 4 : CHERCHER UN ATHLÈTE ========== -->
<div class="tuto-step" data-step="4" id="tutoStep4" style="display:none;">
    <div class="tuto-title" style="color:#818cf8;">Cherchez un athlète</div>
    <p class="tuto-subtitle">Recherchez un athlète dans le club <b id="tutoAthClubName">sélectionné</b>. Tapez un nom ou laissez vide pour voir tous les athlètes.</p>

    <div class="tuto-live-search tuto-highlight" id="tutoAthSearchWrap">
        <span style="font-size:18px;flex-shrink:0;">&#128100;</span>
        <input type="text" id="tutoAthInput" placeholder="Tapez un nom d'athlète..." autocomplete="off" oninput="_tutoSearchAthletes(this.value)">
    </div>
    <div class="tuto-live-results" id="tutoAthResults"></div>
    <div id="tutoAthDone" style="display:none;text-align:center;margin-top:14px;">
        <div class="tuto-complete-badge">&#10003; Athlète sélectionné !</div>
        <button class="tuto-next-btn" onclick="tutoGoStep(5)" style="margin-top:10px;">Voir l'aperçu profil &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 5 : APERÇU PROFIL ATHLÈTE ========== -->
<div class="tuto-step" data-step="5" id="tutoStep5" style="display:none;">
    <div class="tuto-title" style="color:#ec4899;">Profil athlète</div>
    <p class="tuto-subtitle">Voici un aperçu du profil. Chaque élément est <b>cliquable</b> : clubs, épreuves, villes, catégories...</p>

    <div id="tutoAthPreview" class="tuto-inline-panel">
        <div class="loading-msg">Sélectionnez un athlète à l'étape précédente</div>
    </div>
    <div id="tutoAthDoneStep5" style="display:none;text-align:center;margin-top:14px;">
        <a href="#" id="tutoAthProfileLink" class="tuto-try" target="_blank" style="margin-right:10px;">&#128073; Voir le profil complet</a>
        <button class="tuto-next-btn" onclick="tutoGoStep(6)" style="margin-top:10px;">Continuer &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 6 : ÉPREUVES & VILLES ========== -->
<div class="tuto-step" data-step="6" id="tutoStep6" style="display:none;">
    <div class="tuto-title" style="color:#f59e0b;">Épreuves & Villes</div>
    <p class="tuto-subtitle">Explorez les données par <b>épreuve</b> (100m, saut en hauteur...) ou par <b>ville</b> de compétition.</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="tuto-mock" style="margin:0;">
            <div style="color:#a29bfe;font-weight:700;font-size:14px;margin-bottom:8px;">&#127939; Épreuves</div>
            <div class="tuto-mock-row"><span style="color:#ef4444;">Sprint</span> 100m, 200m, 400m</div>
            <div class="tuto-mock-row"><span style="color:#f59e0b;">Demi-fond</span> 800m, 1500m</div>
            <div class="tuto-mock-row"><span style="color:#3b82f6;">Sauts</span> Hauteur, Longueur, Perche</div>
            <div class="tuto-mock-row"><span style="color:#6366f1;">Lancers</span> Poids, Disque, Javelot</div>
            <div style="margin-top:10px;"><a href="?page=epreuves" class="tuto-try" style="font-size:12px;padding:8px 16px;">Essayer les Épreuves</a></div>
        </div>
        <div class="tuto-mock" style="margin:0;">
            <div style="color:#60a5fa;font-weight:700;font-size:14px;margin-bottom:8px;">&#127961; Villes</div>
            <div class="tuto-mock-row"><span style="color:#60a5fa;">Paris</span> Stade de France</div>
            <div class="tuto-mock-row"><span style="color:#60a5fa;">Lyon</span> Stade Gerland</div>
            <div class="tuto-mock-row"><span style="color:#60a5fa;">Marseille</span> Stade Delort</div>
            <div class="tuto-mock-row"><span style="color:#60a5fa;">Lille</span> Stadium Nord</div>
            <div style="margin-top:10px;"><a href="?page=villes" class="tuto-try" style="font-size:12px;padding:8px 16px;">Essayer les Villes</a></div>
        </div>
    </div>
    <p class="tuto-subtitle" style="margin-top:16px;">Chaque épreuve et chaque ville a son panneau détaillé avec graphiques, records et résumé.</p>
    <div style="text-align:center;margin-top:14px;">
        <button class="tuto-next-btn" onclick="tutoGoStep(7)">Continuer &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 7 : COMPARER ========== -->
<div class="tuto-step" data-step="7" id="tutoStep7" style="display:none;">
    <div class="tuto-title" style="color:#f59e0b;">Comparer</div>
    <p class="tuto-subtitle">Ajoutez des athlètes ou clubs au <b>panier de comparaison</b> avec le bouton <b style="color:#a29bfe;">+</b>, puis comparez-les visuellement.</p>

    <div class="tuto-mock">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
            <span style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;background:#6c5ce730;border:1px solid #6c5ce7;border-radius:6px;color:#a29bfe;font-weight:700;font-size:14px;">+</span>
            <span style="color:#8b949e;font-size:12px;">Cliquez ce bouton sur n'importe quel athlète ou club pour l'ajouter</span>
        </div>
        <div style="display:flex;gap:8px;margin-bottom:12px;">
            <span style="padding:6px 12px;background:#6c5ce720;border:1px solid #6c5ce7;border-radius:8px;font-size:12px;color:#a29bfe;">DUPONT Jean &#10005;</span>
            <span style="padding:6px 12px;background:#6c5ce720;border:1px solid #6c5ce7;border-radius:8px;font-size:12px;color:#a29bfe;">MARTIN Pierre &#10005;</span>
        </div>
        <div style="display:flex;gap:12px;">
            <div style="flex:1;height:80px;background:#3b82f620;border:1px solid #3b82f640;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#60a5fa;">Graphique barres</div>
            <div style="flex:1;height:80px;background:#ec489920;border:1px solid #ec489940;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#f472b6;">Graphique radar</div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;margin-top:14px;padding:12px;background:#f59e0b10;border:1px solid #f59e0b30;border-radius:10px;">
        <span style="font-size:20px;">&#128161;</span>
        <span style="color:#fbbf24;font-size:13px;">Le panier flottant en bas à droite de l'écran affiche le nombre d'éléments sélectionnés.</span>
    </div>
    <div style="text-align:center;margin-top:14px;">
        <a href="?page=comparer" class="tuto-try" style="margin-right:10px;">&#128073; Aller au Comparateur</a>
        <button class="tuto-next-btn" onclick="tutoGoStep(8)" style="margin-top:10px;">Continuer &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 8 : SUIVRE & NOTIFICATIONS ========== -->
<div class="tuto-step" data-step="8" id="tutoStep8" style="display:none;">
    <div class="tuto-title" style="color:#10b981;">Suivre & Notifications</div>
    <p class="tuto-subtitle">Restez informé des athlètes et clubs qui vous intéressent.</p>

    <div class="tuto-features">
        <div class="tuto-feature"><span class="icon">&#9825;</span><div><div class="title">Suivre un athlète</div><div class="desc">Cliquez le bouton &#9825; sur un profil pour le suivre. Renseignez votre email une seule fois.</div></div></div>
        <div class="tuto-feature"><span class="icon">&#127963;</span><div><div class="title">Suivre un club</div><div class="desc">Le bouton &#9825; dans le panneau club permet de suivre un club entier.</div></div></div>
        <div class="tuto-feature"><span class="icon">&#128233;</span><div><div class="title">Newsletter</div><div class="desc">Inscrivez-vous à la newsletter pour recevoir les actualités de l'athlétisme français.</div></div></div>
        <div class="tuto-feature"><span class="icon">&#128196;</span><div><div class="title">Télécharger PDF</div><div class="desc">Sur chaque profil, le bouton PDF génère une fiche imprimable complète.</div></div></div>
    </div>
    <div style="text-align:center;margin-top:14px;">
        <button class="tuto-next-btn" onclick="tutoGoStep(9)">Terminer &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 9 : C'EST PARTI ! ========== -->
<div class="tuto-step" data-step="9" id="tutoStep9" style="display:none;">
    <div class="tuto-title" style="background:linear-gradient(135deg,#6c5ce7,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Vous êtes prêt !</div>
    <p class="tuto-subtitle">Voici les codes couleurs des <b>niveaux de compétition</b> que vous verrez partout :</p>

    <div class="tuto-niv-demo">
        <span style="background:#f9731620;border:1px solid #f9731640;color:#fb923c;">D1-D8 Départemental</span>
        <span style="background:#0891b220;border:1px solid #0891b240;color:#22d3ee;">R1-R6 Régional</span>
        <span style="background:#e11d4820;border:1px solid #e11d4840;color:#fb7185;">N1-N4 National</span>
        <span style="background:#c026d320;border:1px solid #c026d340;color:#e879f9;">IE/IR International</span>
    </div>

    <p class="tuto-subtitle" style="margin-top:20px;">Récapitulatif des fonctionnalités :</p>
    <div class="tuto-features">
        <div class="tuto-feature"><span class="icon">&#128269;</span><div><div class="title">Recherche multi-critères</div><div class="desc">Nom, club, épreuve, sexe, catégorie, nationalité</div></div></div>
        <div class="tuto-feature"><span class="icon">&#127963;</span><div><div class="title">Panneaux détaillés</div><div class="desc">Clubs et épreuves avec 5+ onglets</div></div></div>
        <div class="tuto-feature"><span class="icon">&#128100;</span><div><div class="title">Profils complets</div><div class="desc">Bio auto-générée, filtrable par année</div></div></div>
        <div class="tuto-feature"><span class="icon">&#128202;</span><div><div class="title">Graphiques interactifs</div><div class="desc">Évolution, répartition, comparaison</div></div></div>
        <div class="tuto-feature"><span class="icon">&#128279;</span><div><div class="title">Tout est cliquable</div><div class="desc">Clubs, épreuves, villes, catégories, nationalités</div></div></div>
        <div class="tuto-feature"><span class="icon">&#9878;</span><div><div class="title">Filtres combinables</div><div class="desc">Club + nationalité + sexe + catégorie...</div></div></div>
    </div>

    <div style="text-align:center;margin-top:24px;">
        <button class="tuto-next-btn" onclick="tutoComplete()" style="font-size:16px;padding:14px 36px;background:linear-gradient(135deg,#6c5ce7,#ec4899);">&#127881; Commencer l'exploration !</button>
    </div>
</div>

</div>

<script>
// ═══════════════════════════════════════════════════
// TUTORIEL INTERACTIF — JS
// ═══════════════════════════════════════════════════

var _tutoState = {
    current: 1,
    completed: [],
    selectedClub: null,   // {id, name}
    selectedAthlete: null, // {id, name}
    visitedTabs: [],
    searchTimer: null
};

// Restore progress from localStorage
try {
    var saved = JSON.parse(localStorage.getItem('bk_tuto_progress') || '[]');
    if (Array.isArray(saved)) _tutoState.completed = saved;
} catch(e) {}

// ——— Navigation ———
function tutoGoStep(n) {
    // Hide all steps
    for (var i = 1; i <= 9; i++) {
        var el = document.getElementById('tutoStep' + i);
        if (el) {
            if (i === n) { el.style.display = ''; el.classList.add('visible','tuto-enter'); }
            else { el.style.display = 'none'; el.classList.remove('tuto-enter'); }
        }
    }
    _tutoState.current = n;
    // Update progress bar
    document.querySelectorAll('.tuto-progress-step').forEach(function(ps) {
        var s = parseInt(ps.dataset.step);
        ps.classList.remove('active', 'done');
        if (_tutoState.completed.indexOf(s) >= 0) ps.classList.add('done');
        if (s === n) ps.classList.add('active');
    });
    // Trigger step-specific animations
    _tutoTriggerStep(n);
    // Scroll to top of tuto container
    var container = document.querySelector('.tuto-container');
    if (container) container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    // Auto-load step 2 club suggestions
    if (n === 2 && !_tutoState.selectedClub) {
        _tutoLoadClubSuggestions();
    }
    // Auto-load step 3 club panel if club selected
    if (n === 3 && _tutoState.selectedClub && !document.getElementById('clubDetailNameTuto').textContent) {
        _tutoLoadClubPanel(_tutoState.selectedClub.id, _tutoState.selectedClub.name);
    }
    // Auto-load step 4 with club name
    if (n === 4 && _tutoState.selectedClub) {
        var cn = document.getElementById('tutoAthClubName');
        if (cn) cn.textContent = _tutoState.selectedClub.name;
        // Auto-search all athletes of club
        _tutoSearchAthletes('');
    }
    // Auto-load step 5 athlete preview
    if (n === 5 && _tutoState.selectedAthlete) {
        _tutoLoadAthPreview(_tutoState.selectedAthlete.id);
    }
    // Mark descriptive steps as auto-complete
    if ([6, 7, 8].indexOf(n) >= 0) {
        _tutoMarkComplete(n);
    }
}

function _tutoMarkComplete(n) {
    if (_tutoState.completed.indexOf(n) < 0) {
        _tutoState.completed.push(n);
        try { localStorage.setItem('bk_tuto_progress', JSON.stringify(_tutoState.completed)); } catch(e) {}
    }
    // Update progress dot
    var dot = document.querySelector('.tuto-progress-step[data-step="' + n + '"]');
    if (dot) dot.classList.add('done');
}

function tutoSkip() {
    try { localStorage.setItem('bk_tuto_seen', '1'); } catch(e) {}
    window.location.href = '?page=accueil';
}

function tutoComplete() {
    try { localStorage.setItem('bk_tuto_seen', '1'); } catch(e) {}
    _tutoMarkComplete(9);
    window.location.href = '?page=accueil';
}

// ——— Typing + Counter animations ———
function _tutoTypeText(el, text, speed, cb) {
    var i = 0; el.textContent = '';
    var iv = setInterval(function() {
        if (i < text.length) { el.textContent += text[i]; i++; }
        else { clearInterval(iv); if (cb) cb(); }
    }, speed || 50);
}
function _tutoAnimateCounter(el, target) {
    var duration = 1500, startTime = null;
    function step(ts) {
        if (!startTime) startTime = ts;
        var progress = Math.min((ts - startTime) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString('fr-FR');
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

var _tutoAnimated = {};
function _tutoTriggerStep(n) {
    if (_tutoAnimated[n]) return;
    _tutoAnimated[n] = true;
    if (n === 1) {
        var typEl = document.getElementById('tutoTyping');
        if (typEl) _tutoTypeText(typEl, 'Bienvenue sur Bokonzi', 60);
        setTimeout(function() {
            document.querySelectorAll('.tuto-step[data-step="1"] .tuto-card .num').forEach(function(el) {
                var target = parseInt(el.dataset.count);
                if (target) _tutoAnimateCounter(el, target);
            });
        }, 800);
    }
}

// ——— Step 2: Club search ———
function _tutoSearchClubs(query) {
    clearTimeout(_tutoState.searchTimer);
    var results = document.getElementById('tutoClubResults');
    if (!query || query.length < 2) { results.style.display = 'none'; return; }
    _tutoState.searchTimer = setTimeout(function() {
        results.style.display = 'block';
        results.innerHTML = '<div style="padding:12px;color:#5a6580;text-align:center;">Recherche...</div>';
        fetch(BASE_API + '/clubs.php?nom=' + encodeURIComponent(query) + '&limit=10')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.clubs || data.clubs.length === 0) {
                    results.innerHTML = '<div style="padding:12px;color:#5a6580;text-align:center;">Aucun club trouvé</div>';
                    return;
                }
                var html = '';
                data.clubs.forEach(function(c) {
                    html += '<div class="tuto-club-result" onclick="_tutoSelectClub(' + c.id_club + ', \'' + escapeHtml(c.nom_club).replace(/'/g, "\\'") + '\')">'
                        + '<div style="flex:1;"><span style="color:#a29bfe;font-weight:600;">' + escapeHtml(c.nom_club) + '</span></div>'
                        + '<span style="color:#34d399;font-size:12px;">' + (c.nb_athletes || 0) + ' athlètes</span>'
                        + '</div>';
                });
                results.innerHTML = html;
            })
            .catch(function() {
                results.innerHTML = '<div style="padding:12px;color:#ef4444;text-align:center;">Erreur de connexion</div>';
            });
    }, 300);
}

function _tutoSelectClub(id, name) {
    _tutoState.selectedClub = { id: id, name: name };
    document.getElementById('tutoClubResults').style.display = 'none';
    document.getElementById('tutoClubInput').value = name;
    document.getElementById('tutoClubDone').style.display = 'block';
    document.getElementById('tutoClubSearchWrap').classList.remove('tuto-highlight');
    _tutoMarkComplete(2);
}

// Suggestions populaires (auto-loaded)
function _tutoLoadClubSuggestions() {
    var results = document.getElementById('tutoClubResults');
    if (!results || _tutoState.selectedClub) return;
    results.style.display = 'block';
    results.innerHTML = '<div style="padding:8px 12px;color:#8b949e;font-size:11px;font-weight:600;">CLUBS POPULAIRES</div><div style="padding:12px;color:#5a6580;text-align:center;">Chargement...</div>';
    fetch(BASE_API + '/clubs.php?limit=8')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.clubs || data.clubs.length === 0) return;
            var html = '<div style="padding:8px 12px;color:#8b949e;font-size:11px;font-weight:600;">CLUBS POPULAIRES — cliquez pour sélectionner</div>';
            data.clubs.forEach(function(c) {
                html += '<div class="tuto-club-result" onclick="_tutoSelectClub(' + c.id_club + ', \'' + escapeHtml(c.nom_club).replace(/'/g, "\\'") + '\')">'
                    + '<div style="flex:1;"><span style="color:#a29bfe;font-weight:600;">' + escapeHtml(c.nom_club) + '</span></div>'
                    + '<span style="color:#34d399;font-size:12px;">' + (c.nb_athletes || 0) + ' athlètes</span>'
                    + '</div>';
            });
            html += '<div style="padding:8px 12px;color:#5a6580;font-size:11px;text-align:center;font-style:italic;">...ou tapez un nom ci-dessus pour chercher</div>';
            results.innerHTML = html;
        })
        .catch(function() {
            results.innerHTML = '<div style="padding:8px 12px;color:#8b949e;font-size:11px;font-weight:600;">CLUBS POPULAIRES</div><div style="padding:12px;color:#ef4444;text-align:center;">Erreur de chargement</div>';
        });
}

// ——— Step 3: Club panel (embedded) ———
function _tutoLoadClubPanel(id, name) {
    var content = document.getElementById('clubDetailContentTuto');
    if (!content) return;
    content.innerHTML = '<div class="loading-msg">Chargement de ' + escapeHtml(name) + '...</div>';
    document.getElementById('clubDetailNameTuto').textContent = name;
    document.getElementById('clubDetailMetaTuto').textContent = 'Chargement...';
    var url = BASE_API + '/club_stats.php?id=' + id;
    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { content.innerHTML = '<div class="loading-msg">Club non trouvé</div>'; return; }
            _fillClubPanel(data, 'Tuto');
            _tutoState.visitedTabs = ['epreuves'];
            _tutoUpdateTabCount();
        })
        .catch(function() { content.innerHTML = '<div class="loading-msg">Erreur de chargement</div>'; });
}

function switchClubTabTuto(tab) {
    _switchClubTab(tab, 'Tuto');
    // Track visited tabs
    if (_tutoState.visitedTabs.indexOf(tab) < 0) {
        _tutoState.visitedTabs.push(tab);
    }
    _tutoUpdateTabCount();
}
function closeClubDetailTuto() {} // no-op for embedded panel

function _tutoUpdateTabCount() {
    var cnt = _tutoState.visitedTabs.length;
    var el = document.getElementById('tutoTabsCount');
    if (el) el.textContent = cnt;
    if (cnt >= 2) {
        _tutoMarkComplete(3);
        var top = document.getElementById('tutoClubTabsDoneTop');
        var bot = document.getElementById('tutoClubTabsDone');
        if (top) top.style.display = 'block';
        if (bot) bot.style.display = 'block';
    }
}

// ——— Step 4: Athlete search ———
function _tutoSearchAthletes(query) {
    clearTimeout(_tutoState.searchTimer);
    var results = document.getElementById('tutoAthResults');
    if (!_tutoState.selectedClub) {
        results.innerHTML = '<div style="padding:12px;color:#5a6580;text-align:center;">Sélectionnez d\'abord un club</div>';
        results.style.display = 'block';
        return;
    }
    _tutoState.searchTimer = setTimeout(function() {
        results.style.display = 'block';
        results.innerHTML = '<div style="padding:12px;color:#5a6580;text-align:center;">Recherche...</div>';
        var params = 'club=' + encodeURIComponent(_tutoState.selectedClub.name) + '&limit=15';
        if (query && query.length >= 2) params += '&nom=' + encodeURIComponent(query);
        fetch(BASE_API + '/search.php?' + params)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.athletes || data.athletes.length === 0) {
                    results.innerHTML = '<div style="padding:12px;color:#5a6580;text-align:center;">Aucun athlète trouvé</div>';
                    return;
                }
                var html = '<div style="padding:8px 12px;color:#8b949e;font-size:11px;font-weight:600;">ATHLÈTES DU CLUB — cliquez pour sélectionner</div>';
                data.athletes.forEach(function(a) {
                    var extId = a.athlete_id || a.athlete_id_externe || a.id;
                    var nom = a.nom_complet || ((a.prenom_athlete || '') + ' ' + (a.nom_athlete || '')).trim();
                    var cat = a.categorie || a.categorie_athlete || '';
                    var sexe = a.sexe || a.sexe_athlete || '';
                    var nat = a.nationalite || a.nationalite_athlete || '';
                    html += '<div class="tuto-ath-result" onclick="_tutoSelectAthlete(' + extId + ', \'' + escapeHtml(nom).replace(/'/g, "\\'") + '\')">'
                        + '<div style="flex:1;">'
                        + '<span style="color:#c9d1d9;font-weight:600;">' + escapeHtml(nom) + '</span>'
                        + (cat ? ' <span class="badge badge-cat" style="font-size:10px;">' + escapeHtml(cat) + '</span>' : '')
                        + (sexe ? ' <span class="badge badge-' + sexe.toLowerCase() + '" style="font-size:10px;">' + escapeHtml(sexe) + '</span>' : '')
                        + '</div>'
                        + (nat ? '<span style="color:#8b949e;font-size:11px;">' + escapeHtml(nat) + '</span>' : '')
                        + '</div>';
                });
                results.innerHTML = html;
            })
            .catch(function() {
                results.innerHTML = '<div style="padding:12px;color:#ef4444;text-align:center;">Erreur de connexion</div>';
            });
    }, 300);
}

function _tutoSelectAthlete(id, name) {
    _tutoState.selectedAthlete = { id: id, name: name };
    document.getElementById('tutoAthResults').style.display = 'none';
    document.getElementById('tutoAthInput').value = name;
    document.getElementById('tutoAthDone').style.display = 'block';
    document.getElementById('tutoAthSearchWrap').classList.remove('tuto-highlight');
    _tutoMarkComplete(4);
}

// ——— Step 5: Athlete preview ———
function _tutoLoadAthPreview(id) {
    var container = document.getElementById('tutoAthPreview');
    if (!container) return;
    container.innerHTML = '<div class="loading-msg">Chargement du profil...</div>';
    fetch(BASE_API + '/athlete.php?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { container.innerHTML = '<div class="loading-msg">Athlète non trouvé</div>'; return; }
            var a = data.athlete || data;
            var nom = (a.prenom_athlete || '') + ' ' + (a.nom_athlete || '');
            var html = '<div style="padding:16px;">';
            // Header
            html += '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">';
            html += '<span style="font-size:18px;font-weight:800;color:#c9d1d9;">' + escapeHtml(nom.trim()) + '</span>';
            if (a.sexe_athlete) html += '<span class="badge badge-' + (a.sexe_athlete||'').toLowerCase() + '">' + escapeHtml(a.sexe_athlete) + '</span>';
            if (a.categorie_athlete) html += '<span class="badge badge-cat">' + escapeHtml(a.categorie_athlete) + '</span>';
            if (a.nationalite_athlete) html += '<span style="padding:3px 8px;border-radius:6px;font-size:11px;background:#30363d;color:#c9d1d9;">' + escapeHtml(a.nationalite_athlete) + '</span>';
            html += '</div>';
            // Infos
            var infos = [];
            if (a.date_naissance && a.date_naissance.indexOf('0000') !== 0) infos.push('Né(e) : ' + a.date_naissance.substring(0, 4));
            if (a.lieu_naissance) infos.push('Lieu : ' + a.lieu_naissance);
            if (infos.length) html += '<div style="color:#5a6580;font-size:12px;margin-bottom:10px;">' + escapeHtml(infos.join(' — ')) + '</div>';
            // Clubs
            if (data.clubs && data.clubs.length > 0) {
                html += '<div style="margin-bottom:10px;"><span style="color:#8b949e;font-size:11px;font-weight:600;">CLUBS :</span> ';
                data.clubs.forEach(function(c) {
                    html += '<span style="display:inline-block;margin:2px 4px;padding:3px 10px;background:#10b98115;border:1px solid #10b98130;border-radius:6px;font-size:12px;color:#34d399;">' + escapeHtml((c.nom_club||'').replace(/\*\s*$/, '')) + '</span>';
                });
                html += '</div>';
            }
            // Records summary
            if (data.records && data.records.length > 0) {
                html += '<div style="margin-bottom:10px;"><span style="color:#8b949e;font-size:11px;font-weight:600;">TOP RECORDS :</span>';
                var topRec = data.records.slice(0, 5);
                topRec.forEach(function(r) {
                    html += '<div style="display:flex;gap:8px;align-items:center;padding:4px 0;border-bottom:1px solid #1e2a3a15;">'
                        + '<span style="color:#a29bfe;font-size:12px;min-width:80px;">' + escapeHtml(r.epreuve || '') + '</span>'
                        + '<span style="color:#60a5fa;font-weight:600;font-size:13px;">' + escapeHtml(r.performance || '') + '</span>'
                        + '</div>';
                });
                html += '</div>';
            }
            // Stats summary
            var stats = [];
            if (data.medailles) {
                var m = data.medailles;
                if (m.or > 0) stats.push('<span style="color:#fbbf24;">&#129351;' + m.or + '</span>');
                if (m.argent > 0) stats.push('<span style="color:#94a3b8;">&#129352;' + m.argent + '</span>');
                if (m.bronze > 0) stats.push('<span style="color:#d97706;">&#129353;' + m.bronze + '</span>');
            }
            if (data.podiums && data.podiums.length > 0) stats.push('<span style="color:#34d399;">' + data.podiums.length + ' podiums</span>');
            if (data.selections && data.selections.length > 0) stats.push('<span style="color:#818cf8;">' + data.selections.length + ' sélections</span>');
            if (stats.length) html += '<div style="display:flex;gap:12px;flex-wrap:wrap;font-size:13px;margin-top:8px;">' + stats.join('') + '</div>';
            html += '</div>';
            container.innerHTML = html;
            // Show link + done
            var link = document.getElementById('tutoAthProfileLink');
            if (link) link.href = '?page=profil&id=' + id;
            document.getElementById('tutoAthDoneStep5').style.display = 'block';
            _tutoMarkComplete(5);
        })
        .catch(function() { container.innerHTML = '<div class="loading-msg">Erreur de chargement</div>'; });
}

// ——— Step 6: Advanced search ———
// ——— Init ———
document.addEventListener('DOMContentLoaded', function() {
    // Step 1 is visible by default, trigger its animation
    _tutoTriggerStep(1);
    // Update progress bar from saved state
    _tutoState.completed.forEach(function(n) {
        var dot = document.querySelector('.tuto-progress-step[data-step="' + n + '"]');
        if (dot) dot.classList.add('done');
    });
    // Mark step 1 as active
    var dot1 = document.querySelector('.tuto-progress-step[data-step="1"]');
    if (dot1) dot1.classList.add('active');
});
</script>

<?php endif; ?>

</div>

<!-- ====== PANNEAU EPREUVE DETAIL (global) ====== -->
<div id="epreuveDetailPanel" class="club-detail-panel">
    <div class="club-detail-header">
        <h2 id="epreuveDetailName"></h2>
        <span class="meta-info" id="epreuveDetailMeta"></span>
        <button onclick="closeEpreuveDetail()" class="btn-close-detail">&times; Fermer</button>
    </div>
    <div class="club-detail-tabs">
        <button class="club-detail-tab active" data-tab="records" onclick="switchEpreuveTab('records')">Records</button>
        <button class="club-detail-tab" data-tab="nationalites" onclick="switchEpreuveTab('nationalites')">Nationalités</button>
        <button class="club-detail-tab" data-tab="stats" onclick="switchEpreuveTab('stats')">Stats</button>
        <button class="club-detail-tab" data-tab="resume" onclick="switchEpreuveTab('resume')">Résumé</button>
    </div>
    <div id="epreuveDetailContent" class="club-detail-content">
        <div class="loading-msg">Cliquez sur une épreuve pour voir ses détails</div>
    </div>
    <div id="epreuveQR"></div>
</div>

<script>
const BASE_API = "https://bokonzi.com/api";

function escapeHtml(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
function _buildLimitMsg(data) {
    var sub = data.logged
        ? 'Vous avez atteint votre limite de <b style="color:#a29bfe;font-size:22px;">' + data.limit + ' recherches par jour</b>.<br><span style="color:#8b949e;font-size:16px;">La limite se reinitialise chaque jour a minuit.</span>'
        : 'Vous avez utilise vos <b style="color:#a29bfe;font-size:22px;">' + data.limit + ' recherches</b> du jour.<br><a href="login.php" style="color:#6c5ce7;text-decoration:underline;font-size:18px;font-weight:700;">Connectez-vous</a> pour passer a <b style="color:#55efc4;font-size:22px;">100 recherches/jour</b> !';
    return '<div style="text-align:center;padding:50px 30px;color:#c9d1d9;background:#0d1117;border:2px solid #ff7675;border-radius:16px;margin:20px 0;">'
        + '<div style="font-size:70px;margin-bottom:16px;">&#9203;</div>'
        + '<div style="font-size:28px;font-weight:800;color:#ff7675;margin-bottom:16px;text-transform:uppercase;letter-spacing:1px;">Limite de recherches atteinte</div>'
        + '<div style="font-size:18px;line-height:2;">' + sub + '</div>'
        + '</div>';
}
function dateFR(d) {
    if (!d || d === '-') return '-';
    if (d.indexOf('0000') === 0) return '-';
    var m = d.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] : d;
}

// ═══════════ GESTION CLUBS IGNORES ═══════════
function addIgnoredClub(id, name) {
    var list = getIgnoredClubs();
    if (list.find(function(c) { return c.id === id; })) return;
    list.push({ id: id, name: name });
    saveIgnoredClubs(list);
    applyIgnoredClubs();
    renderIgnoredPanel();
}
function removeIgnoredClub(id) {
    saveIgnoredClubs(getIgnoredClubs().filter(function(c) { return c.id !== id; }));
    applyIgnoredClubs();
    renderIgnoredPanel();
}
function toggleIgnoreClub(btn, id, name) {
    if (isClubIgnoredById(id)) {
        removeIgnoredClub(id);
    } else {
        addIgnoredClub(id, name);
    }
}
function applyIgnoredClubs() {
    var ignored = getIgnoredClubs();
    var ignoredNames = ignored.map(function(c) { return c.name.toLowerCase(); });
    document.querySelectorAll('tr[data-club-name]').forEach(function(row) {
        var name = row.getAttribute('data-club-name').toLowerCase();
        row.style.display = ignoredNames.indexOf(name) !== -1 ? 'none' : '';
    });
    rebuildClubCharts();
}
function renderIgnoredPanel() {
    var panel = document.getElementById('ignoredClubsPanel');
    if (!panel) return;
    var list = getIgnoredClubs();
    if (list.length === 0) { panel.style.display = 'none'; return; }
    panel.style.display = 'block';
    document.getElementById('ignoredClubsCount').textContent = '(' + list.length + ')';
    var html = '';
    list.forEach(function(c) {
        html += '<span class="ignored-chip">' + escapeHtml(c.name) + ' <span class="restore" onclick="removeIgnoredClub(' + c.id + ')">&#8617; Restaurer</span></span>';
    });
    document.getElementById('ignoredClubsList').innerHTML = html;
}
function rebuildClubCharts() {
    var ignored = getIgnoredClubs();
    var ignoredNames = ignored.map(function(c) { return c.name.toLowerCase(); });

    // --- Accueil: Top Clubs doughnut ---
    var canvas1 = document.getElementById('chartClubs');
    if (canvas1 && window._topClubsRaw) {
        var filtered = window._topClubsRaw.filter(function(c) {
            return ignoredNames.indexOf(c.name.toLowerCase()) === -1;
        });
        if (window._topClubsChart) window._topClubsChart.destroy();
        var ctx1 = canvas1.getContext('2d');
        var grad1 = ctx1.createLinearGradient(0, 0, 0, 300);
        grad1.addColorStop(0, '#6c5ce7'); grad1.addColorStop(1, '#fd79a8');
        var colors1 = ['#6c5ce7','#fd79a8','#55efc4','#fdcb6e','#ff7675','#a29bfe','#e84393','#00cec9','#e17055','#00b894'];
        window._topClubsChart = new Chart(canvas1, {
            type: 'doughnut',
            data: {
                labels: filtered.map(function(c) { return c.name; }),
                datasets: [{
                    data: filtered.map(function(c) { return c.count; }),
                    backgroundColor: colors1.slice(0, filtered.length),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { color: '#8b949e', padding: 8, font: { size: 11 } } }
                }
            }
        });
    }

    // --- Page Clubs: bar chart + period chart ---
    var canvas2 = document.getElementById('clubsChart');
    var canvas3 = document.getElementById('clubsPeriodChart');
    if ((canvas2 || canvas3) && window._clubsPageRaw) {
        var filtered2 = window._clubsPageRaw.filter(function(c) {
            return ignoredNames.indexOf(c.fullName.toLowerCase()) === -1;
        });

        if (canvas2) {
            if (window._clubsPageChart) window._clubsPageChart.destroy();
            var ctx2 = canvas2.getContext('2d');
            var grad2 = ctx2.createLinearGradient(0, 0, 0, 300);
            grad2.addColorStop(0, 'rgba(139,92,246,0.8)'); grad2.addColorStop(1, 'rgba(139,92,246,0.1)');
            window._clubsPageChart = new Chart(canvas2, {
                type: 'bar',
                data: {
                    labels: filtered2.map(function(c) { return c.labelShort; }),
                    datasets: [{
                        label: 'Athletes',
                        data: filtered2.map(function(c) { return c.count; }),
                        backgroundColor: grad2,
                        borderRadius: 6, borderSkipped: false
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: '#1e2a3a' }, ticks: { font: { size: 10 }, maxRotation: 45 } },
                        y: { grid: { color: '#1e2a3a' }, beginAtZero: true }
                    }
                }
            });
        }

        if (canvas3) {
            if (window._clubsPeriodChart) window._clubsPeriodChart.destroy();
            window._clubsPeriodChart = new Chart(canvas3, {
                type: 'bar',
                data: {
                    labels: filtered2.map(function(c) { return c.labelShort; }),
                    datasets: [
                        { label: 'Debut', data: filtered2.map(function(c) { return c.start; }), backgroundColor: '#14b8a6', borderRadius: 4 },
                        { label: 'Fin', data: filtered2.map(function(c) { return c.end; }), backgroundColor: '#f59e0b', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#8b949e' } } },
                    scales: {
                        x: { stacked: false, grid: { color: '#1e2a3a' }, ticks: { font: { size: 10 }, maxRotation: 45 } },
                        y: { stacked: false, grid: { color: '#1e2a3a' }, min: 1990 }
                    }
                }
            });
        }
    }
}

// --- Club Detail Panel (generique, supporte plusieurs panneaux) ---
function _fillClubPanel(data, suffix) {
    var s = suffix || '';
    window['_clubDetailData' + s] = data;
    window['_clubRecFilter' + s] = '';
    window['_clubResumeMode' + s] = 'global';
    window['_clubResumeYear' + s] = null;
    window['_clubYearDataCache' + s] = {};
    window['_clubCompareChart' + s] = null;
    window._ctxClubName = data.club.nom_club;
    document.getElementById('clubDetailName' + s).textContent = data.club.nom_club;
    var meta = data.total_athletes + ' athletes';
    if (data.annee_debut) meta += ' | ' + data.annee_debut + '-' + (data.annee_fin || '...');
    var med = data.medailles;
    if (med.or + med.argent + med.bronze > 0) {
        meta += ' | ';
        if (med.or > 0) meta += '\uD83E\uDD47' + med.or + ' ';
        if (med.argent > 0) meta += '\uD83E\uDD48' + med.argent + ' ';
        if (med.bronze > 0) meta += '\uD83E\uDD49' + med.bronze;
    }
    // Badges de filtre actif
    var filterBadges = '';
    if (data.filter_nationalite) filterBadges += ' <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;background:#ec489930;border:1px solid #ec489960;color:#f472b6;margin-left:4px;">\u{1F30D} ' + escapeHtml(data.filter_nationalite) + '</span>';
    if (data.filter_sexe) filterBadges += ' <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;background:#3b82f630;border:1px solid #3b82f660;color:#60a5fa;margin-left:4px;">' + (data.filter_sexe === 'M' ? 'Hommes' : 'Femmes') + '</span>';
    if (data.filter_categorie) filterBadges += ' <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;background:#10b98130;border:1px solid #10b98160;color:#34d399;margin-left:4px;">' + escapeHtml(data.filter_categorie) + '</span>';
    var metaEl = document.getElementById('clubDetailMeta' + s);
    metaEl.innerHTML = escapeHtml(meta) + filterBadges;
    // QR code dynamique
    var qrDiv = document.getElementById('clubQR' + s);
    if (qrDiv) qrDiv.innerHTML = bkQR('https://bokonzi.com/?page=clubs&open=' + encodeURIComponent(data.club.nom_club));
    // Bouton suivre club
    var btnFC = document.getElementById('btnFollowClub' + s);
    if (btnFC && data.club.id_club) {
        btnFC.style.display = '';
        btnFC.setAttribute('data-club-id', data.club.id_club);
        btnFC.onclick = function() { toggleFollowClub(data.club.id_club, s); };
        _checkClubFollowStatus(data.club.id_club, s);
    }
    _clubSearchInit(s);
    _renderClubTab('epreuves', s);
}
function _openClubPanel(fetchUrl, suffix) {
    var s = suffix || '';
    var panel = document.getElementById('clubDetailPanel' + s);
    var content = document.getElementById('clubDetailContent' + s);
    if (!panel || !content) return;
    panel.classList.add('active');
    content.innerHTML = '<div class="loading-msg">Chargement...</div>';
    document.getElementById('clubDetailName' + s).textContent = '';
    document.getElementById('clubDetailMeta' + s).textContent = '';
    var btnFC = document.getElementById('btnFollowClub' + s);
    if (btnFC) { btnFC.style.display = 'none'; btnFC.className = 'btn-follow btn-follow-club'; btnFC.innerHTML = '\u2661 Suivre'; }
    panel.querySelectorAll('.club-detail-tab').forEach(function(t) { t.classList.remove('active'); });
    var first = panel.querySelector('.club-detail-tab[data-tab="epreuves"]');
    if (first) first.classList.add('active');
    fetch(fetchUrl)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { content.innerHTML = '<div class="loading-msg">Club non trouve</div>'; return; }
            _fillClubPanel(data, s);
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            // Track club panel open
            var _cInfo = data.club || {};
            _trackSearch({ q: _cInfo.nom_club || '', type: 'club', source: 'panel_open', entity_id: _cInfo.id_club || null, entity_name: _cInfo.nom_club || '', pg: 'club_panel' });
        })
        .catch(function() { content.innerHTML = '<div class="loading-msg">Erreur de chargement</div>'; });
}
function _closeClubPanel(suffix) {
    var s = suffix || '';
    var panel = document.getElementById('clubDetailPanel' + s);
    if (panel) panel.classList.remove('active');
    var bar = document.getElementById('clubSearchBar' + s);
    if (bar) bar.style.display = 'none';
    window['_clubDetailData' + s] = null;
    window['_clubYearDataCache' + s] = {};
    if (window['_clubCompareChart' + s]) {
        window['_clubCompareChart' + s].destroy();
        window['_clubCompareChart' + s] = null;
    }
}
// --- Club athlete search ---
function _clubSearchInit(suffix) {
    var s = suffix || '';
    var bar = document.getElementById('clubSearchBar' + s);
    var inp = document.getElementById('clubSearchInput' + s);
    if (!bar || !inp) return;
    bar.style.display = 'block';
    inp.value = '';
    window['_clubSearchTimer' + s] = null;
    window['_clubSearchCtrl' + s] = null;
    inp.removeEventListener('input', inp._clubSearchHandler);
    inp._clubSearchHandler = function() { _clubSearchExec(s); };
    inp.addEventListener('input', inp._clubSearchHandler);
}
function _clubSearchExec(suffix) {
    var s = suffix || '';
    var inp = document.getElementById('clubSearchInput' + s);
    var content = document.getElementById('clubDetailContent' + s);
    var d = window['_clubDetailData' + s];
    if (!inp || !content || !d) return;
    var q = inp.value.trim();
    clearTimeout(window['_clubSearchTimer' + s]);
    if (q.length < 2) {
        inp.style.borderColor = '#1e2a3a';
        if (q.length === 0) {
            // Restore active tab
            var panel = document.getElementById('clubDetailPanel' + s);
            var activeTab = panel ? panel.querySelector('.club-detail-tab.active') : null;
            var tab = activeTab ? activeTab.getAttribute('data-tab') : 'epreuves';
            _renderClubTab(tab, s);
        }
        return;
    }
    inp.style.borderColor = '#a29bfe';
    content.innerHTML = '<div class="loading-msg"><span class="ls-spinner"></span> Recherche...</div>';
    window['_clubSearchTimer' + s] = setTimeout(function() {
        if (window['_clubSearchCtrl' + s]) window['_clubSearchCtrl' + s].abort();
        var ctrl = new AbortController();
        window['_clubSearchCtrl' + s] = ctrl;
        var clubName = d.club ? d.club.nom_club : '';
        var url = BASE_API + '/search.php?club=' + encodeURIComponent(clubName) + '&nom=' + encodeURIComponent(q) + '&limit=50';
        fetch(url, { signal: ctrl.signal })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                inp.style.borderColor = '#1e2a3a';
                if (!data.success) {
                    if (data.limit_reached) {
                        content.innerHTML = _buildLimitMsg(data);
                        return;
                    }
                    content.innerHTML = '<div class="loading-msg">' + escapeHtml(data.error || 'Erreur') + '</div>';
                    return;
                }
                var items = data.athletes || [];
                var total = data.total || 0;
                if (items.length === 0) {
                    content.innerHTML = '<div style="text-align:center;padding:30px;color:#5a6580;">Aucun athlète trouvé pour "<b>' + escapeHtml(q) + '</b>"</div>';
                    return;
                }
                var thRow = '<tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>NAT</th><th>Niveaux</th><th>Records</th></tr>';
                var html = '<div style="padding:8px 0 4px;color:#5a6580;font-size:12px;">' + total + ' résultat' + (total > 1 ? 's' : '') + ' pour "<b>' + escapeHtml(q) + '</b>"</div>';
                html += '<div class="table-wrap">';
                html += '<table class="bk-table">' + thRow + '</table>';
                html += '<table class="bk-table">';
                items.forEach(function(a, i) {
                    var topRecs = a.top_records || [];
                    var recHtml = '';
                    if (topRecs.length > 0) {
                        topRecs.forEach(function(tr) {
                            recHtml += '<div style="font-size:11px;line-height:1.6;"><a href="?page=recherche&epreuve=' + encodeURIComponent(tr.epreuve) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(tr.epreuve) + '</a> <span class="perf-val" style="font-size:11px;">' + escapeHtml(tr.performance) + '</span> ' + _nivBadge(tr.top_niveau || _highestNiveau(tr.niveaux || [])) + '</div>';
                        });
                    } else {
                        var nbRec = a.nb_records || 0;
                        recHtml = nbRec > 0 ? '<span class="badge badge-perf">' + nbRec + '</span>' : '-';
                    }
                    html += '<tr>'
                        + '<td>' + (i + 1) + '</td>'
                        + '<td><b><a href="?page=profil&id=' + a.athlete_id + '">' + highlight(a.nom_complet, q) + '</a></b>'
                        + (a.date_naissance ? '<br><span style="font-size:11px;color:#5a6580;">' + a.date_naissance.substring(0, 4) + '</span>' : '')
                        + '</td>'
                        + '<td><span class="badge badge-cat">' + escapeHtml(a.categorie) + '</span></td>'
                        + '<td><span class="badge badge-' + (a.sexe || '').toLowerCase() + '">' + escapeHtml(a.sexe) + '</span></td>'
                        + '<td>' + escapeHtml(a.nationalite) + '</td>'
                        + '<td>' + _nivBadge(_highestNiveau(a.niveaux || [])) + '</td>'
                        + '<td>' + recHtml + '</td>'
                        + '</tr>';
                });
                html += '</table>';
                html += '<table class="bk-table">' + thRow + '</table>';
                html += '</div>';
                content.innerHTML = html;
            })
            .catch(function(e) {
                if (e.name === 'AbortError') return;
                inp.style.borderColor = '#ff7675';
                content.innerHTML = '<div class="loading-msg">Erreur de connexion</div>';
            });
    }, 350);
}
function _switchClubTab(tab, suffix) {
    var s = suffix || '';
    var panel = document.getElementById('clubDetailPanel' + s);
    if (panel) panel.querySelectorAll('.club-detail-tab').forEach(function(t) {
        t.classList.toggle('active', t.getAttribute('data-tab') === tab);
    });
    // Clear search input when switching tabs
    var inp = document.getElementById('clubSearchInput' + s);
    if (inp) { inp.value = ''; inp.style.borderColor = '#1e2a3a'; }
    _renderClubTab(tab, s);
}
function _nivBadge(code) {
    if (!code) return '-';
    var nc = code.charAt(0);
    var bg, bc, tc;
    if (nc === 'N') { bg = '#e11d4820'; bc = '#e11d48'; tc = '#fb7185'; }
    else if (nc === 'I') { bg = '#c026d320'; bc = '#c026d3'; tc = '#e879f9'; }
    else if (nc === 'R') { bg = '#0891b220'; bc = '#0891b2'; tc = '#22d3ee'; }
    else { bg = '#f9731620'; bc = '#f97316'; tc = '#fb923c'; }
    return '<span style="display:inline-block;padding:2px 7px;border-radius:5px;font-size:10px;margin:1px;background:' + bg + ';border:1px solid ' + bc + '40;color:' + tc + ';">' + escapeHtml(code) + '</span>';
}
function _nivBadges(arr) {
    if (!arr || !arr.length) return '-';
    return arr.map(function(n) { return _nivBadge(n); }).join('');
}
function _highestNiveau(arr) {
    if (!arr || !arr.length) return null;
    var order = {IE:100,IR:99};
    ['N','R','D'].forEach(function(p,pi) {
        for (var i=1;i<=8;i++) order[p+i] = (90-pi*10)-i;
    });
    var best=null, bestS=-1;
    arr.forEach(function(n) { var s=order[n]||0; if(s>bestS){bestS=s;best=n;} });
    return best;
}

function _renderClubTab(tab, suffix) {
    var s = suffix || '';
    var content = document.getElementById('clubDetailContent' + s);
    var d = window['_clubDetailData' + s];
    if (!content || !d) return;
    var html = '';
    if (tab === 'epreuves') {
        var ep = d.epreuves || [];
        var totalEp = d.total_epreuves || ep.length;
        var epPage = d.ep_page || 1;
        var epPages = d.ep_pages || 1;
        if (ep.length === 0 && epPage === 1) { content.innerHTML = '<div class="loading-msg">Aucune épreuve trouvée</div>'; return; }
        // Sous-onglets Records du club / Records personnels
        var epMode = window['_clubEpMode' + s] || 'club';
        html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">';
        ['club','perso'].forEach(function(m) {
            var labels = {club:'Records du club', perso:'Records personnels'};
            var icons = {club:'&#127942;', perso:'&#128100;'};
            var active = epMode === m;
            html += '<button onclick="_clubSetEpMode(\'' + m + '\',\'' + s + '\')" style="padding:6px 16px;border-radius:8px;border:1px solid '+(active?'#a29bfe':'#1e2a3a')+';background:'+(active?'#a29bfe20':'transparent')+';color:'+(active?'#a29bfe':'#5a6580')+';font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;">' + icons[m] + ' ' + labels[m] + '</button>';
        });
        html += '<span style="color:#5a6580;font-size:13px;margin-left:auto;">' + totalEp.toLocaleString('fr-FR') + ' épreuves — Page ' + epPage + '/' + epPages + '</span>';
        html += '</div>';
        // Filtres par discipline
        var discMap = {};
        ep.forEach(function(e) { if (e.discipline && e.disc_color) discMap[e.discipline] = e.disc_color; });
        var discKeys = Object.keys(discMap);
        var discFilter = window['_clubDiscFilter' + s] || null; // null = tout
        if (discKeys.length > 1) {
            html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">';
            var allActive = !discFilter;
            html += '<button onclick="_clubToggleDisc(null,\'' + s + '\')" style="padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(allActive?'#a29bfe':'#1e2a3a')+';background:'+(allActive?'#a29bfe20':'transparent')+';color:'+(allActive?'#a29bfe':'#5a6580')+';transition:all .2s;">Tout</button>';
            discKeys.forEach(function(dk) {
                var dc = discMap[dk];
                var isOn = discFilter && discFilter.indexOf(dk) !== -1;
                html += '<button onclick="_clubToggleDisc(\'' + dk + '\',\'' + s + '\')" style="padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?dc:'#1e2a3a')+';background:'+(isOn?dc+'20':'transparent')+';color:'+(isOn?dc:'#5a6580')+';transition:all .2s;">' + escapeHtml(dk) + '</button>';
            });
            html += '</div>';
        }
        // Filtres par niveau de competition
        var nivMap = {};
        ep.forEach(function(e) {
            (e.niveaux || []).forEach(function(n) { if (n) nivMap[n] = 1; });
        });
        var nivKeys = Object.keys(nivMap).sort(function(a, b) {
            var ord = {IE:100,IR:99};
            for (var p in {N:90,R:80,D:70}) for (var i=1;i<=8;i++) ord[p+i] = {N:90,R:80,D:70}[p] - i;
            return (ord[b]||0) - (ord[a]||0);
        });
        var nivFilter = window['_clubNivFilter' + s] || null;
        if (nivKeys.length > 1) {
            html += '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;align-items:center;">';
            html += '<span style="color:#5a6580;font-size:11px;margin-right:4px;">Niveaux :</span>';
            var nivAllActive = !nivFilter;
            html += '<button onclick="_clubToggleNiv(null,\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(nivAllActive?'#a29bfe':'#1e2a3a')+';background:'+(nivAllActive?'#a29bfe20':'transparent')+';color:'+(nivAllActive?'#a29bfe':'#5a6580')+';transition:all .2s;">Tout</button>';
            nivKeys.forEach(function(nk) {
                var nc = nk.charAt(0);
                var clr = nc==='N'?'#fb7185': nc==='I'?'#e879f9': nc==='R'?'#22d3ee': '#fb923c';
                var isOn = nivFilter && nivFilter.indexOf(nk) !== -1;
                html += '<button onclick="_clubToggleNiv(\'' + nk + '\',\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?clr:'#1e2a3a')+';background:'+(isOn?clr+'20':'transparent')+';color:'+(isOn?clr:'#5a6580')+';transition:all .2s;">' + nk + '</button>';
            });
            html += '</div>';
        }
        // Filtre par année (server-side) + mode comparaison
        var anneesDisp = d.annees_disponibles || [];
        var yearFilter = d.annee_filtree || null;
        var epYearMode = window['_clubEpYearMode' + s] || 'filter';
        var cmpYears = window['_clubEpYearCmp' + s] || [];
        if (anneesDisp.length > 1) {
            html += '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;align-items:center;">';
            html += '<span style="color:#5a6580;font-size:11px;margin-right:4px;">Année :</span>';
            var isFilter = epYearMode === 'filter';
            var isCmp = epYearMode === 'compare';
            html += '<button onclick="_clubEpYearModeSet(\'filter\',\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isFilter?'#a29bfe':'#1e2a3a')+';background:'+(isFilter?'#a29bfe20':'transparent')+';color:'+(isFilter?'#a29bfe':'#5a6580')+';transition:all .2s;">Filtrer</button>';
            html += '<button onclick="_clubEpYearModeSet(\'compare\',\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isCmp?'#ffd700':'#1e2a3a')+';background:'+(isCmp?'#ffd70020':'transparent')+';color:'+(isCmp?'#ffd700':'#5a6580')+';transition:all .2s;">Comparer</button>';
            html += '</div>';
            var recentYears = anneesDisp.slice(-15).reverse();
            if (isFilter) {
                html += '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;align-items:center;">';
                var yrAllActive = !yearFilter;
                html += '<button onclick="_clubSetEpYear(null,\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(yrAllActive?'#a29bfe':'#1e2a3a')+';background:'+(yrAllActive?'#a29bfe20':'transparent')+';color:'+(yrAllActive?'#a29bfe':'#5a6580')+';transition:all .2s;">Tout</button>';
                recentYears.forEach(function(yr) {
                    var isOn = yearFilter && yearFilter == yr;
                    html += '<button onclick="_clubSetEpYear(' + yr + ',\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?'#a29bfe':'#1e2a3a')+';background:'+(isOn?'#a29bfe20':'transparent')+';color:'+(isOn?'#a29bfe':'#5a6580')+';transition:all .2s;">' + yr + '</button>';
                });
                if (anneesDisp.length > 15) {
                    html += '<select onchange="_clubSetEpYear(this.value ? parseInt(this.value) : null,\'' + s + '\')" style="padding:3px 8px;border-radius:6px;font-size:11px;background:#0d1117;border:1px solid #1e2a3a;color:#5a6580;cursor:pointer;">';
                    html += '<option value="">+ anciennes</option>';
                    anneesDisp.slice(0, anneesDisp.length - 15).forEach(function(yr) {
                        html += '<option value="' + yr + '"' + (yearFilter == yr ? ' selected' : '') + '>' + yr + '</option>';
                    });
                    html += '</select>';
                }
                html += '</div>';
            } else {
                // Mode comparaison : multi-select
                html += '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px;align-items:center;">';
                html += '<span style="color:#ffd700;font-size:11px;margin-right:4px;">Sélectionnez 2 à 5 années :</span>';
                recentYears.forEach(function(yr) {
                    var isOn = cmpYears.indexOf(yr) !== -1;
                    html += '<button onclick="_clubToggleEpYearCmp(' + yr + ',\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?'#ffd700':'#1e2a3a')+';background:'+(isOn?'#ffd70020':'transparent')+';color:'+(isOn?'#ffd700':'#5a6580')+';transition:all .2s;">' + yr + '</button>';
                });
                if (anneesDisp.length > 15) {
                    html += '<select onchange="if(this.value)_clubToggleEpYearCmp(parseInt(this.value),\'' + s + '\');this.selectedIndex=0;" style="padding:3px 8px;border-radius:6px;font-size:11px;background:#0d1117;border:1px solid #1e2a3a;color:#5a6580;cursor:pointer;">';
                    html += '<option value="">+ anciennes</option>';
                    anneesDisp.slice(0, anneesDisp.length - 15).forEach(function(yr) {
                        html += '<option value="' + yr + '">' + yr + (cmpYears.indexOf(yr)!==-1?' ✓':'') + '</option>';
                    });
                    html += '</select>';
                }
                html += '</div>';
                if (cmpYears.length >= 2) {
                    html += '<div style="margin-bottom:12px;"><button onclick="_clubRunEpYearCmp(\'' + s + '\')" style="padding:6px 20px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;border:1px solid #ffd700;background:linear-gradient(135deg,#ffd700,#ffaa00);color:#000;transition:all .2s;">Comparer ' + cmpYears.sort().join(', ') + '</button></div>';
                }
                // Afficher résultats comparaison si disponibles
                var cmpData = window['_clubEpYearCmpData' + s];
                if (cmpData && Object.keys(cmpData).length >= 2) {
                    html += _buildEpYearCmpHTML(cmpData, s);
                }
            }
        }
        var filteredEp = ep;
        if (discFilter) filteredEp = filteredEp.filter(function(e) { return discFilter.indexOf(e.discipline) !== -1; });
        if (nivFilter) filteredEp = filteredEp.filter(function(e) { return (e.niveaux||[]).some(function(n) { return nivFilter.indexOf(n) !== -1; }); });
        var thEp = '<tr><th>#</th><th>Épreuve</th><th style="color:#3b82f6;">Record ♂</th><th style="color:#3b82f6;">Par</th><th style="color:#3b82f6;">Année</th><th style="color:#ec4899;">Record ♀</th><th style="color:#ec4899;">Par</th><th style="color:#ec4899;">Année</th><th>Niveaux</th></tr>';
        html += '<div class="table-wrap">';
        html += '<table class="bk-table">' + thEp + '</table>';
        html += '<table class="bk-table">';
        var _lastDisc = '';
        var _rowNum = 0;
        filteredEp.forEach(function(e, i) {
            var _clubN = window._ctxClubName || '';
            if (e.discipline && e.discipline !== _lastDisc) {
                _lastDisc = e.discipline;
                var dc = e.disc_color || '#6b7280';
                html += '<tr><td colspan="9" style="background:' + dc + '15;border-left:3px solid ' + dc + ';padding:8px 14px;"><span style="color:' + dc + ';font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:1px;">' + escapeHtml(e.discipline) + '</span></td></tr>';
            }
            _rowNum++;
            html += '<tr><td>' + _rowNum + '</td>';
            html += '<td><b><a href="?page=recherche&epreuve=' + encodeURIComponent(e.epreuve) + '&club=' + encodeURIComponent(_clubN) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(e.epreuve) + '</a></b></td>';
            // Record Homme
            html += '<td>' + (e.best_perf_m ? '<span class="perf-val">' + escapeHtml(e.best_perf_m) + '</span>' : '<span style="color:#3a4560;">-</span>') + '</td>';
            html += '<td>' + (e.best_athlete_id_m ? '<a href="?page=profil&id=' + e.best_athlete_id_m + '" style="color:#3b82f6;text-decoration:none;">' + escapeHtml(e.best_athlete_m) + '</a>' : (e.best_athlete_m ? escapeHtml(e.best_athlete_m) : '<span style="color:#3a4560;">-</span>')) + '</td>';
            html += '<td style="color:#5a6580;font-size:12px;">' + (e.best_annee_m && e.best_annee_m != '0000' ? e.best_annee_m : '-') + '</td>';
            // Record Femme
            html += '<td>' + (e.best_perf_f ? '<span class="perf-val">' + escapeHtml(e.best_perf_f) + '</span>' : '<span style="color:#3a4560;">-</span>') + '</td>';
            html += '<td>' + (e.best_athlete_id_f ? '<a href="?page=profil&id=' + e.best_athlete_id_f + '" style="color:#ec4899;text-decoration:none;">' + escapeHtml(e.best_athlete_f) + '</a>' : (e.best_athlete_f ? escapeHtml(e.best_athlete_f) : '<span style="color:#3a4560;">-</span>')) + '</td>';
            html += '<td style="color:#5a6580;font-size:12px;">' + (e.best_annee_f && e.best_annee_f != '0000' ? e.best_annee_f : '-') + '</td>';
            html += '<td>' + _nivBadge(e.top_niveau) + '</td></tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thEp + '</table>';
        html += '</div>';
        // Pagination épreuves
        if (epPages > 1) {
            html += '<div class="pager" style="margin-top:12px;">';
            if (epPage > 1) html += '<a href="#" onclick="loadClubEpPage(' + (epPage - 1) + ',\'' + s + '\');return false;">Précédent</a> ';
            for (var pi = Math.max(1, epPage - 3); pi <= Math.min(epPages, epPage + 3); pi++) {
                if (pi === epPage) html += '<span class="current">' + pi + '</span> ';
                else html += '<a href="#" onclick="loadClubEpPage(' + pi + ',\'' + s + '\');return false;">' + pi + '</a> ';
            }
            if (epPage < epPages) html += '<a href="#" onclick="loadClubEpPage(' + (epPage + 1) + ',\'' + s + '\');return false;">Suivant</a>';
            html += ' <span class="info">(' + epPages + ' pages)</span>';
            html += '</div>';
        }
    } else if (tab === 'nationalites') {
        var nat = d.nationalites || {};
        var keys = Object.keys(nat);
        if (keys.length === 0) { content.innerHTML = '<div class="loading-msg">Aucune nationalité renseignée</div>'; return; }
        var totalNat = 0;
        keys.forEach(function(k) { totalNat += nat[k]; });
        var natMode = window['_clubNatMode' + s] || 'overview';
        // Sub-tabs
        html += '<div style="display:flex;gap:8px;margin-bottom:14px;align-items:center;">';
        [{m:'overview',l:'&#127760; Vue d\'ensemble'},{m:'compare',l:'&#128200; Comparer'},{m:'resume',l:'&#128221; Résumé'}].forEach(function(t) {
            var active = natMode === t.m;
            html += '<button onclick="_clubSetNatMode(\'' + t.m + '\',\'' + s + '\')" style="padding:6px 16px;border-radius:8px;border:1px solid '+(active?'#a29bfe':'#1e2a3a')+';background:'+(active?'#a29bfe20':'transparent')+';color:'+(active?'#a29bfe':'#5a6580')+';font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;">' + t.l + '</button>';
        });
        html += '</div>';
        html += '<div style="margin-bottom:12px;color:#5a6580;font-size:13px;">' + keys.length + ' nationalités — ' + totalNat.toLocaleString('fr-FR') + ' athlètes</div>';

        if (natMode === 'overview') {
            // Charts
            html += '<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">';
            html += '<div style="flex:1;min-width:200px;max-width:300px;"><canvas id="clubNatDonut' + s + '"></canvas></div>';
            html += '<div style="flex:2;min-width:300px;"><canvas id="clubNatBar' + s + '"></canvas></div>';
            html += '</div>';
            // Clickable nationality buttons
            html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">';
            keys.forEach(function(k) {
                var pct = totalNat > 0 ? Math.round(nat[k] / totalNat * 100) : 0;
                html += '<a href="?page=recherche&club=' + encodeURIComponent(d.club.nom_club) + '&nationalite=' + encodeURIComponent(k) + '" style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;background:#0d1525;border:1px solid #1a2540;border-radius:8px;color:#a29bfe;text-decoration:none;font-size:12px;transition:all .2s;" onmouseenter="this.style.borderColor=\'#6c5ce7\';this.style.background=\'#6c5ce715\'" onmouseleave="this.style.borderColor=\'#1a2540\';this.style.background=\'#0d1525\'">' + escapeHtml(k) + ' <span style="color:#8b949e;font-size:11px;">' + nat[k] + ' (' + pct + '%)</span></a>';
            });
            html += '</div>';
            // Table
            var thNat = '<tr><th>#</th><th>Nationalité</th><th>Athlètes</th><th>%</th><th></th></tr>';
            html += '<div class="table-wrap">';
            html += '<table class="bk-table">' + thNat + '</table>';
            html += '<table class="bk-table">';
            keys.forEach(function(k, i) {
                var pct = totalNat > 0 ? Math.round(nat[k] / totalNat * 100) : 0;
                html += '<tr><td>' + (i+1) + '</td><td><b>' + escapeHtml(k) + '</b></td><td>' + nat[k].toLocaleString('fr-FR') + '</td><td><div style="display:flex;align-items:center;gap:6px;"><div style="width:60px;height:6px;background:#1a2540;border-radius:3px;"><div style="width:' + Math.min(pct,100) + '%;height:100%;background:#a78bfa;border-radius:3px;"></div></div><span style="font-size:12px;">' + pct + '%</span></div></td>';
                html += '<td><a href="?page=recherche&club=' + encodeURIComponent(d.club.nom_club) + '&nationalite=' + encodeURIComponent(k) + '" style="color:#a29bfe;text-decoration:none;font-size:12px;">Voir athlètes →</a></td></tr>';
            });
            html += '</table>';
            html += '<table class="bk-table">' + thNat + '</table>';
            html += '</div>';
        } else if (natMode === 'compare') {
            // Mode Comparer
            var selNats = window['_clubNatSel' + s] || [];
            var cmpData = window['_clubNatCmp' + s] || null;
            // Sélection des nationalités
            html += '<div style="margin-bottom:14px;"><span style="color:#8b949e;font-size:12px;">Sélectionnez les nationalités à comparer :</span></div>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">';
            keys.forEach(function(k) {
                var isOn = selNats.indexOf(k) !== -1;
                html += '<button onclick="_clubToggleNatSel(\'' + escapeHtml(k) + '\',\'' + s + '\')" style="padding:5px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?'#a29bfe':'#1e2a3a')+';background:'+(isOn?'#a29bfe25':'transparent')+';color:'+(isOn?'#a29bfe':'#5a6580')+';transition:all .2s;">' + escapeHtml(k) + ' (' + nat[k] + ')</button>';
            });
            html += '</div>';
            if (selNats.length >= 2) {
                if (!cmpData) {
                    html += '<div class="loading-msg">Chargement de la comparaison...</div>';
                } else {
                    // Couleurs pour chaque nationalité
                    var natColors = ['#a78bfa','#f472b6','#34d399','#fbbf24','#60a5fa','#fb923c','#e879f9','#22d3ee'];
                    // --- Graphique barres nb athlètes ---
                    html += '<div style="margin-bottom:20px;"><canvas id="clubNatCmpBar' + s + '" height="120"></canvas></div>';
                    // --- Tableau comparatif ---
                    var thCmp = '<tr><th></th>';
                    cmpData.forEach(function(nd, ci) { thCmp += '<th style="color:' + natColors[ci % natColors.length] + ';">' + escapeHtml(nd.code) + '</th>'; });
                    thCmp += '</tr>';
                    html += '<div class="table-wrap">';
                    html += '<table class="bk-table">' + thCmp + '</table>';
                    html += '<table class="bk-table">';
                    // Athlètes
                    html += '<tr><td><b>Athlètes</b></td>';
                    cmpData.forEach(function(nd) { html += '<td>' + nd.nb_athletes + '</td>'; });
                    html += '</tr>';
                    // Sexe H/F
                    html += '<tr><td><b>Hommes</b></td>';
                    cmpData.forEach(function(nd) { html += '<td>' + (nd.par_sexe['M'] || 0) + '</td>'; });
                    html += '</tr><tr><td><b>Femmes</b></td>';
                    cmpData.forEach(function(nd) { html += '<td>' + (nd.par_sexe['F'] || 0) + '</td>'; });
                    html += '</tr>';
                    // Médailles
                    html += '<tr><td><b>&#129351; Or</b></td>';
                    cmpData.forEach(function(nd) { html += '<td>' + (nd.medailles.or || 0) + '</td>'; });
                    html += '</tr><tr><td><b>&#129352; Argent</b></td>';
                    cmpData.forEach(function(nd) { html += '<td>' + (nd.medailles.argent || 0) + '</td>'; });
                    html += '</tr><tr><td><b>&#129353; Bronze</b></td>';
                    cmpData.forEach(function(nd) { html += '<td>' + (nd.medailles.bronze || 0) + '</td>'; });
                    html += '</tr>';
                    // Meilleur niveau
                    html += '<tr><td><b>Meilleur niveau</b></td>';
                    cmpData.forEach(function(nd) { html += '<td>' + _nivBadge(nd.meilleur_niveau) + '</td>'; });
                    html += '</tr>';
                    html += '</table>';
                    html += '<table class="bk-table">' + thCmp + '</table>';
                    html += '</div>';
                    // --- Catégories side by side ---
                    html += '<h4 style="color:#c9d1d9;margin:20px 0 10px;font-size:14px;">Répartition par catégorie</h4>';
                    html += '<div style="margin-bottom:20px;"><canvas id="clubNatCmpCat' + s + '" height="160"></canvas></div>';
                    // --- Top épreuves par nationalité ---
                    html += '<h4 style="color:#c9d1d9;margin:20px 0 10px;font-size:14px;">Top épreuves</h4>';
                    html += '<div style="display:flex;gap:16px;flex-wrap:wrap;">';
                    cmpData.forEach(function(nd, ci) {
                        var clr = natColors[ci % natColors.length];
                        html += '<div style="flex:1;min-width:180px;background:#0d1525;border:1px solid ' + clr + '30;border-radius:10px;padding:12px;">';
                        html += '<div style="color:' + clr + ';font-weight:700;margin-bottom:8px;font-size:13px;">' + escapeHtml(nd.code) + '</div>';
                        if (nd.top_epreuves && nd.top_epreuves.length > 0) {
                            nd.top_epreuves.forEach(function(ep) {
                                html += '<div style="display:flex;justify-content:space-between;padding:3px 0;font-size:12px;color:#c9d1d9;border-bottom:1px solid #1a2540;"><span>' + escapeHtml(ep.epreuve) + '</span><span style="color:#8b949e;">' + ep.nb + '</span></div>';
                            });
                        } else {
                            html += '<div style="color:#5a6580;font-size:12px;">Aucune épreuve</div>';
                        }
                        html += '</div>';
                    });
                    html += '</div>';
                }
            } else if (selNats.length === 1) {
                html += '<div style="color:#5a6580;font-size:13px;padding:20px;text-align:center;">Sélectionnez au moins 2 nationalités pour comparer.</div>';
            }
        } else if (natMode === 'resume') {
            html += _buildResumeHTML(_buildNatResumeText(d));
        }
    } else if (tab === 'records') {
        var rec = d.records || [];
        var totalRec = d.total_records || rec.length;
        var recPage = d.rec_page || 1;
        var recPages = d.rec_pages || 1;
        if (rec.length === 0 && recPage === 1) { content.innerHTML = '<div class="loading-msg">Aucun record trouvé</div>'; return; }

        // Filtres par discipline
        var recDiscMap = {};
        rec.forEach(function(r) { if (r.discipline && r.disc_color) recDiscMap[r.discipline] = r.disc_color; });
        var recDiscKeys = Object.keys(recDiscMap);
        var recDiscFilter = window['_clubRecDiscFilter' + s] || null;
        if (recDiscKeys.length >= 1) {
            html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">';
            var allActive = !recDiscFilter;
            html += '<button onclick="_clubToggleRecDisc(null,\'' + s + '\')" style="padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(allActive?'#a29bfe':'#1e2a3a')+';background:'+(allActive?'#a29bfe20':'transparent')+';color:'+(allActive?'#a29bfe':'#5a6580')+';transition:all .2s;">Tout</button>';
            recDiscKeys.forEach(function(dk) {
                var dc = recDiscMap[dk];
                var isOn = recDiscFilter && recDiscFilter.indexOf(dk) !== -1;
                html += '<button onclick="_clubToggleRecDisc(\'' + dk + '\',\'' + s + '\')" style="padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?dc:'#1e2a3a')+';background:'+(isOn?dc+'20':'transparent')+';color:'+(isOn?dc:'#5a6580')+';transition:all .2s;">' + escapeHtml(dk) + '</button>';
            });
            html += '</div>';
        }
        var filteredRec = recDiscFilter ? rec.filter(function(r) { return recDiscFilter.indexOf(r.discipline) !== -1; }) : rec;

        html += '<div style="margin-bottom:12px;color:#5a6580;font-size:13px;">' + totalRec.toLocaleString('fr-FR') + ' records au total — Page ' + recPage + '/' + recPages + (recDiscFilter ? ' (filtre: ' + filteredRec.length + ' affichés)' : '') + '</div>';

        var thRec = '<tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>Épreuve</th><th>Discipline</th><th>Performance</th><th>Niveaux</th><th>Date</th><th></th></tr>';
        html += '<div class="table-wrap">';
        html += '<table class="bk-table">' + thRec + '</table>';
        html += '<table class="bk-table">';
        filteredRec.forEach(function(r, i) {
            var inB = r.athlete_id ? isAthleteInBasket(r.athlete_id) : false;
            html += '<tr><td>' + ((recPage - 1) * 10 + i + 1) + '</td>';
            html += '<td><b>' + (r.athlete_id ? '<a href="?page=profil&id=' + r.athlete_id + '&s=records" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(r.athlete) + '</a>' : escapeHtml(r.athlete)) + '</b></td>';
            var _clubR = window._ctxClubName || '';
            html += '<td><a href="?page=recherche&categorie=' + encodeURIComponent(r.categorie||'') + '&club=' + encodeURIComponent(_clubR) + '" style="text-decoration:none;"><span class="badge badge-cat">' + escapeHtml(r.categorie || '-') + '</span></a></td>';
            html += '<td><a href="?page=recherche&sexe=' + encodeURIComponent(r.sexe||'') + '&club=' + encodeURIComponent(_clubR) + '" style="text-decoration:none;">' + escapeHtml(r.sexe || '-') + '</a></td>';
            html += '<td><a href="?page=recherche&epreuve=' + encodeURIComponent(r.epreuve||'') + '&club=' + encodeURIComponent(_clubR) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(r.epreuve || '-') + '</a></td>';
            html += '<td>' + (r.disc_color ? '<span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:' + r.disc_color + '20;color:' + r.disc_color + ';border:1px solid ' + r.disc_color + '40;">' + escapeHtml(r.discipline || '') + '</span>' : '-') + '</td>';
            html += '<td><span class="perf-val">' + escapeHtml(r.performance || '-') + '</span></td>';
            html += '<td>' + _nivBadge(r.top_niveau || _highestNiveau(r.niveaux)) + '</td>';
            html += '<td>' + dateFR(r.date || '-') + '</td>';
            html += '<td>' + (r.athlete_id ? '<button class="btn-cmp-add' + (inB ? ' added' : '') + '" data-cmp-ath="' + r.athlete_id + '" data-name="' + escapeHtml(r.athlete) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">' + (inB ? '\u2713' : '+') + '</button>' : '') + '</td></tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thRec + '</table>';
        html += '</div>';

        // Pagination
        if (recPages > 1) {
            html += '<div class="pager" style="margin-top:12px;">';
            if (recPage > 1) html += '<a href="#" onclick="loadClubRecPage(' + (recPage - 1) + ',\'' + s + '\');return false;">Précédent</a> ';
            for (var pi = Math.max(1, recPage - 3); pi <= Math.min(recPages, recPage + 3); pi++) {
                if (pi === recPage) html += '<span class="current">' + pi + '</span> ';
                else html += '<a href="#" onclick="loadClubRecPage(' + pi + ',\'' + s + '\');return false;">' + pi + '</a> ';
            }
            if (recPage < recPages) html += '<a href="#" onclick="loadClubRecPage(' + (recPage + 1) + ',\'' + s + '\');return false;">Suivant</a>';
            html += ' <span class="info">(' + recPages + ' pages)</span>';
            html += '</div>';
        }
    } else if (tab === 'performances') {
        var perfs = d.performances || [];
        var totalPerfs = d.total_performances || perfs.length;
        var perfPage = d.perf_page || 1;
        var perfPages = d.perf_pages || 1;
        var perfMode = window['_clubPerfMode' + s] || 'all';
        if (perfs.length === 0 && perfPage === 1) {
            // Sous-onglets même si vide (pour pouvoir changer de mode)
            html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">';
            ['all','perso'].forEach(function(m) {
                var labels = {all:'Toutes les épreuves', perso:'Records personnels'};
                var icons = {all:'&#127942;', perso:'&#128100;'};
                var active = perfMode === m;
                html += '<button onclick="_clubSetPerfMode(\'' + m + '\',\'' + s + '\')" style="padding:6px 16px;border-radius:8px;border:1px solid '+(active?'#a29bfe':'#1e2a3a')+';background:'+(active?'#a29bfe20':'transparent')+';color:'+(active?'#a29bfe':'#5a6580')+';font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;">' + icons[m] + ' ' + labels[m] + '</button>';
            });
            html += '</div>';
            html += '<div class="loading-msg">Aucune performance trouvée</div>';
            content.innerHTML = html;
            return;
        }

        // Sous-onglets Toutes les épreuves / Records personnels
        html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">';
        ['all','perso'].forEach(function(m) {
            var labels = {all:'Toutes les épreuves', perso:'Records personnels'};
            var icons = {all:'&#127942;', perso:'&#128100;'};
            var active = perfMode === m;
            html += '<button onclick="_clubSetPerfMode(\'' + m + '\',\'' + s + '\')" style="padding:6px 16px;border-radius:8px;border:1px solid '+(active?'#a29bfe':'#1e2a3a')+';background:'+(active?'#a29bfe20':'transparent')+';color:'+(active?'#a29bfe':'#5a6580')+';font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;">' + icons[m] + ' ' + labels[m] + '</button>';
        });
        html += '<span style="color:#5a6580;font-size:13px;margin-left:auto;">' + totalPerfs.toLocaleString('fr-FR') + ' ' + (perfMode === 'perso' ? 'records' : 'performances') + ' — Page ' + perfPage + '/' + perfPages + '</span>';
        html += '</div>';

        var thPerf = '<tr><th>#</th><th>Athlète</th><th>Cat</th><th>Épreuve</th>'
            + '<th>Performance</th><th>Niveau</th><th>Place</th><th>Ville</th><th>Date</th><th></th></tr>';
        html += '<div class="table-wrap">';
        html += '<table class="bk-table">' + thPerf + '</table>';
        html += '<table class="bk-table">';
        var _clubP = window._ctxClubName || '';
        perfs.forEach(function(p, i) {
            var inB = p.athlete_id ? isAthleteInBasket(p.athlete_id) : false;
            html += '<tr><td>' + ((perfPage - 1) * 20 + i + 1) + '</td>';
            html += '<td><b>' + (p.athlete_id ? '<a href="?page=profil&id=' + p.athlete_id + '&s=resultats" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(p.athlete) + '</a>' : escapeHtml(p.athlete)) + '</b></td>';
            html += '<td><a href="?page=recherche&categorie=' + encodeURIComponent(p.categorie||'') + '&club=' + encodeURIComponent(_clubP) + '" style="text-decoration:none;"><span class="badge badge-cat">' + escapeHtml(p.categorie || '-') + '</span></a></td>';
            html += '<td><a href="?page=recherche&epreuve=' + encodeURIComponent(p.epreuve||'') + '&club=' + encodeURIComponent(_clubP) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(p.epreuve || '-') + '</a></td>';
            html += '<td><span class="perf-val">' + escapeHtml(p.performance || '-') + '</span></td>';
            html += '<td>' + _nivBadge(p.niveau) + '</td>';
            html += '<td>' + escapeHtml(p.place || '-') + '</td>';
            html += '<td>' + (p.ville ? '<a href="?page=villes&open=' + encodeURIComponent(p.ville) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(p.ville) + '</a>' : '-') + '</td>';
            html += '<td>' + dateFR(p.date || '-') + '</td>';
            html += '<td>' + (p.athlete_id ? '<button class="btn-cmp-add' + (inB ? ' added' : '') + '" data-cmp-ath="' + p.athlete_id + '" data-name="' + escapeHtml(p.athlete) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">' + (inB ? '\u2713' : '+') + '</button>' : '') + '</td></tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thPerf + '</table>';
        html += '</div>';

        // Pagination
        if (perfPages > 1) {
            html += '<div class="pager" style="margin-top:12px;">';
            if (perfPage > 1) html += '<a href="#" onclick="loadClubPerfsPage(' + (perfPage - 1) + ',\'' + s + '\');return false;">Précédent</a> ';
            for (var pi = Math.max(1, perfPage - 3); pi <= Math.min(perfPages, perfPage + 3); pi++) {
                if (pi === perfPage) html += '<span class="current">' + pi + '</span> ';
                else html += '<a href="#" onclick="loadClubPerfsPage(' + pi + ',\'' + s + '\');return false;">' + pi + '</a> ';
            }
            if (perfPage < perfPages) html += '<a href="#" onclick="loadClubPerfsPage(' + (perfPage + 1) + ',\'' + s + '\');return false;">Suivant</a>';
            html += ' <span class="info">(' + perfPages + ' pages)</span>';
            html += '</div>';
        }
    } else if (tab === 'stats') {
        // --- STATISTIQUES DU CLUB (charts sexe, categorie, evolution, medailles, podiums) ---
        var sexe = d.par_sexe || {};
        var cats = d.par_categorie || {};
        var nbAth = d.total_athletes || 0;
        var rpa = d.resultats_par_annee || [];
        var pod = d.podiums || {};
        var totalPod = d.total_podiums || 0;
        var med = d.medailles || {};
        var totalMed = (med.or || 0) + (med.argent || 0) + (med.bronze || 0);
        var sel = d.selections || {};

        // Row 1: Sexe + Categories
        html += '<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">';
        html += '<div style="flex:1;min-width:200px;max-width:300px;"><h4 style="color:#8b949e;font-size:13px;margin:0 0 8px;">Répartition par sexe</h4><canvas id="clubSexeChart' + s + '"></canvas></div>';
        html += '<div style="flex:2;min-width:300px;"><h4 style="color:#8b949e;font-size:13px;margin:0 0 8px;">Catégories</h4><canvas id="clubCatChart' + s + '"></canvas></div>';
        html += '</div>';

        // Row 2: Medailles + Podiums cards
        if (totalMed > 0 || totalPod > 0) {
            html += '<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">';
            if (totalMed > 0) {
                html += '<div style="flex:1;min-width:150px;text-align:center;padding:14px;background:#f59e0b10;border:1px solid #f59e0b30;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#fbbf24;">' + (med.or || 0) + '</div><div style="font-size:11px;color:#8b949e;">Or</div></div>';
                html += '<div style="flex:1;min-width:150px;text-align:center;padding:14px;background:#94a3b810;border:1px solid #94a3b830;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#94a3b8;">' + (med.argent || 0) + '</div><div style="font-size:11px;color:#8b949e;">Argent</div></div>';
                html += '<div style="flex:1;min-width:150px;text-align:center;padding:14px;background:#b4540010;border:1px solid #b4540030;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#cd7f32;">' + (med.bronze || 0) + '</div><div style="font-size:11px;color:#8b949e;">Bronze</div></div>';
            }
            if (totalPod > 0) {
                html += '<div style="flex:1;min-width:150px;text-align:center;padding:14px;background:#10b98110;border:1px solid #10b98130;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#34d399;">' + totalPod + '</div><div style="font-size:11px;color:#8b949e;">Podiums</div></div>';
            }
            html += '</div>';
        }

        // Selections
        if (sel.nb_selections > 0) {
            html += '<div style="margin-bottom:16px;padding:12px;background:#6366f110;border:1px solid #6366f130;border-radius:10px;display:flex;gap:20px;flex-wrap:wrap;">';
            html += '<div><span style="font-size:20px;font-weight:700;color:#818cf8;">' + sel.nb_athletes + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">athlètes sélectionnés</span></div>';
            html += '<div><span style="font-size:20px;font-weight:700;color:#818cf8;">' + sel.nb_selections + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">sélections nationales</span></div>';
            if (sel.nb_competitions > 0) html += '<div><span style="font-size:20px;font-weight:700;color:#818cf8;">' + sel.nb_competitions + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">compétitions</span></div>';
            html += '</div>';
        }

        // Row 3: Evolution par annee
        if (rpa.length > 1) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Évolution par année</h4>';
            html += '<canvas id="clubEvoChart' + s + '" style="max-height:250px;"></canvas>';
        }

        // Top villes
        var tv = d.top_villes || [];
        if (tv.length > 0) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Principaux lieux de compétition</h4>';
            var _tvTh = '<tr><th>#</th><th>Ville</th><th>Résultats</th><th>Athlètes</th></tr>';
            html += '<div class="table-wrap"><table class="bk-table">' + _tvTh + '</table><table class="bk-table">';
            tv.forEach(function(v, i) {
                html += '<tr><td>' + (i+1) + '</td><td><a href="?page=villes&open=' + encodeURIComponent(v.ville) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(v.ville) + '</a></td><td>' + v.nb_resultats + '</td><td>' + v.nb_athletes + '</td></tr>';
            });
            html += '</table><table class="bk-table">' + _tvTh + '</table></div>';
        }

        // Courbes niveaux de compétition (déplacées depuis épreuves)
        var nivCounts = {};
        var nivOrd = {IE:100,IR:99};
        ['N','R','D'].forEach(function(p){var b={N:90,R:80,D:70}[p];for(var i=1;i<=8;i++) nivOrd[p+i]=b-i;});
        var epForNiv = d.epreuves || [];
        epForNiv.forEach(function(e) {
            (e.niveaux || []).forEach(function(n) { if (n && nivOrd[n]) nivCounts[n] = (nivCounts[n]||0) + 1; });
        });
        var nivChartKeys = Object.keys(nivCounts).sort(function(a,b){ return (nivOrd[a]||0) - (nivOrd[b]||0); });
        var nivParAnnee = d.niveaux_par_annee || [];
        if (nivChartKeys.length > 2) {
            html += '<div style="margin-bottom:16px;background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;">';
            html += '<h4 style="margin:0 0 8px;color:#c9d1d9;font-size:13px;">Distribution des niveaux de compétition</h4>';
            html += '<canvas id="clubNivChart' + s + '" height="200"></canvas>';
            html += '</div>';
        }
        if (nivParAnnee.length > 1) {
            html += '<div style="margin-bottom:16px;background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;">';
            html += '<h4 style="margin:0 0 8px;color:#c9d1d9;font-size:13px;">\u00c9volution des niveaux par ann\u00e9e</h4>';
            html += '<canvas id="clubNivYearChart' + s + '" height="200"></canvas>';
            html += '</div>';
        }

    } else if (tab === 'resume') {
        // --- RESUME TEXTUEL DU CLUB (3 modes) ---
        var mode = window['_clubResumeMode' + s] || 'global';
        var anneesDisp = d.annees_disponibles || [];

        // Selecteur de mode
        html += '<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">';
        ['global','annee','comparer'].forEach(function(m) {
            var labels = {global:'Global',annee:'Par annee',comparer:'Comparer'};
            var active = mode === m;
            html += '<button onclick="_clubSetResumeMode(\'' + m + '\',\'' + s + '\')" style="padding:8px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid ' + (active ? '#6c5ce7' : '#1a2540') + ';background:' + (active ? 'linear-gradient(135deg,#6c5ce7,#5541d0)' : '#080c14') + ';color:' + (active ? '#fff' : '#8b949e') + ';">' + labels[m] + '</button>';
        });
        html += '</div>';

        if (mode === 'global') {
            html += _buildResumeHTML(_buildResumeText(d, null));
        } else if (mode === 'annee') {
            var selYear = window['_clubResumeYear' + s];
            html += '<div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;">';
            html += '<select id="clubYearSelect' + s + '" onchange="_clubYearChanged(\'' + s + '\')" style="padding:8px 14px;background:#080c14;border:1px solid #1a2540;border-radius:8px;color:#d0d7e0;font-size:14px;">';
            html += '<option value="">-- Choisir une annee --</option>';
            anneesDisp.forEach(function(y) {
                html += '<option value="' + y + '"' + (selYear == y ? ' selected' : '') + '>' + y + '</option>';
            });
            html += '</select>';
            html += '<span id="clubYearLoading' + s + '" style="color:#5a6580;font-size:13px;display:none;">Chargement...</span>';
            html += '</div>';
            html += '<div id="clubYearResume' + s + '">';
            if (selYear && window['_clubYearDataCache' + s][selYear]) {
                html += _buildResumeHTML(_buildResumeText(window['_clubYearDataCache' + s][selYear], selYear));
            } else if (!selYear) {
                html += '<div style="color:#5a6580;text-align:center;padding:40px;">Selectionnez une annee pour afficher le resume</div>';
            }
            html += '</div>';
        } else if (mode === 'comparer') {
            var selYears = window['_clubCompareYears' + s] || [];
            html += '<div style="margin-bottom:16px;">';
            html += '<p style="color:#8b949e;font-size:13px;margin-bottom:10px;">Selectionnez jusqu\'a 6 annees a comparer :</p>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">';
            anneesDisp.forEach(function(y) {
                var checked = selYears.indexOf(y) !== -1;
                html += '<label style="display:flex;align-items:center;gap:4px;padding:6px 12px;background:' + (checked ? '#6c5ce715' : '#080c14') + ';border:1px solid ' + (checked ? '#6c5ce7' : '#1a2540') + ';border-radius:6px;cursor:pointer;color:' + (checked ? '#a29bfe' : '#8b949e') + ';font-size:13px;">';
                html += '<input type="checkbox" value="' + y + '" ' + (checked ? 'checked' : '') + ' onchange="_clubToggleCompareYear(' + y + ',this.checked,\'' + s + '\')" style="accent-color:#6c5ce7;"> ' + y;
                html += '</label>';
            });
            html += '</div>';
            html += '<button onclick="_clubRunCompare(\'' + s + '\')" style="padding:8px 24px;background:linear-gradient(135deg,#6c5ce7,#5541d0);border:none;border-radius:8px;color:#fff;font-size:13px;font-weight:600;cursor:pointer;"' + (selYears.length < 2 ? ' disabled' : '') + '>Comparer</button>';
            html += '<span style="color:#5a6580;font-size:12px;margin-left:10px;">' + selYears.length + '/6 annees</span>';
            html += '</div>';
            html += '<div id="clubCompareResult' + s + '"></div>';
        }
    }
    content.innerHTML = html;
    // Post-render: graphique comparaison années pour onglet épreuves
    if (tab === 'epreuves') {
        // Graphique comparaison années
        var _cmpChartEl = document.getElementById('clubEpYearCmpChart' + s);
        var _cmpData = window['_clubEpYearCmpData' + s];
        if (_cmpChartEl && _cmpData) {
            var _cmpYrs = Object.keys(_cmpData).map(Number).sort();
            var _cmpColors = ['#6c5ce7','#00cec9','#fdcb6e','#e17055','#55efc4'];
            var _cmpDS = [
                { label:'Épreuves', key:'total_epreuves' },
                { label:'Athlètes', key:'total_athletes' },
                { label:'Records', key:'total_records' },
                { label:'Résultats', key:'nb_resultats' }
            ];
            new Chart(_cmpChartEl, {
                type: 'bar',
                data: {
                    labels: _cmpYrs.map(String),
                    datasets: _cmpDS.map(function(ds, di) {
                        return {
                            label: ds.label,
                            data: _cmpYrs.map(function(y) { return _cmpData[y][ds.key] || 0; }),
                            backgroundColor: _cmpColors[di]
                        };
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position:'bottom', labels: { color:'#8b949e', padding:12, font:{size:11} } } },
                    scales: {
                        x: { grid:{color:'#1e2a3a'}, ticks:{color:'#8b949e'} },
                        y: { beginAtZero:true, grid:{color:'#1e2a3a'}, ticks:{color:'#5a6580'} }
                    }
                }
            });
        }
    }
    // Post-render: charts for nationalités tab
    if (tab === 'nationalites') {
        var _nat = d.nationalites || {};
        var _nk = Object.keys(_nat);
        var _totalN = 0;
        _nk.forEach(function(k) { _totalN += _nat[k]; });
        var _colors = ['#3b82f6','#ec4899','#8b5cf6','#f59e0b','#10b981','#ef4444','#06b6d4','#f97316','#84cc16','#6366f1','#64748b'];
        // Doughnut
        var _dc = document.getElementById('clubNatDonut' + s);
        if (_dc && _nk.length > 0) {
            var _top10 = _nk.slice(0, 10);
            var _otherC = 0;
            _nk.slice(10).forEach(function(k) { _otherC += _nat[k]; });
            var _lbl = _top10.map(function(k) { return k; });
            var _dt = _top10.map(function(k) { return _nat[k]; });
            if (_otherC > 0) { _lbl.push('Autres'); _dt.push(_otherC); }
            new Chart(_dc, {
                type: 'doughnut',
                data: { labels: _lbl, datasets: [{ data: _dt, backgroundColor: _colors.slice(0, _lbl.length), borderWidth: 0 }] },
                options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, font: { size: 10 }, color: '#8b949e' } } } }
            });
        }
        // Bar
        var _bc = document.getElementById('clubNatBar' + s);
        if (_bc && _nk.length > 0) {
            var _top15 = _nk.slice(0, 15);
            new Chart(_bc, {
                type: 'bar',
                data: { labels: _top15, datasets: [{ data: _top15.map(function(k) { return _nat[k]; }), backgroundColor: '#a78bfa', borderRadius: 4, barThickness: 16 }] },
                options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { display: false }, ticks: { color: '#c8cfd8' } } } }
            });
        }
        // Comparison charts
        var _cmpD = window['_clubNatCmp' + s];
        var _cmpBar = document.getElementById('clubNatCmpBar' + s);
        if (_cmpBar && _cmpD) {
            var _natCl = ['#a78bfa','#f472b6','#34d399','#fbbf24','#60a5fa','#fb923c','#e879f9','#22d3ee'];
            new Chart(_cmpBar, {
                type: 'bar',
                data: { labels: _cmpD.map(function(n){return n.code;}), datasets: [{ label:'Athlètes', data: _cmpD.map(function(n){return n.nb_athletes;}), backgroundColor: _cmpD.map(function(_,i){return _natCl[i%_natCl.length];}), borderRadius:4, barThickness:28 }] },
                options: { responsive:true, plugins:{legend:{display:false}}, scales:{ x:{grid:{color:'#1e2a3a'},ticks:{color:'#c8cfd8',font:{weight:'bold'}}}, y:{grid:{color:'#1e2a3a'},ticks:{color:'#8b949e'},beginAtZero:true} } }
            });
        }
        var _cmpCat = document.getElementById('clubNatCmpCat' + s);
        if (_cmpCat && _cmpD) {
            var _natCl2 = ['#a78bfa','#f472b6','#34d399','#fbbf24','#60a5fa','#fb923c','#e879f9','#22d3ee'];
            var allCats = {};
            _cmpD.forEach(function(n) { Object.keys(n.par_categorie||{}).forEach(function(c) { allCats[c]=1; }); });
            var catKeys = Object.keys(allCats);
            new Chart(_cmpCat, {
                type: 'bar',
                data: { labels: catKeys, datasets: _cmpD.map(function(n,i) { return { label:n.code, data:catKeys.map(function(c){return (n.par_categorie||{})[c]||0;}), backgroundColor:_natCl2[i%_natCl2.length], borderRadius:3 }; }) },
                options: { responsive:true, plugins:{legend:{labels:{color:'#8b949e'}}}, scales:{ x:{grid:{color:'#1e2a3a'},ticks:{color:'#8b949e'}}, y:{grid:{color:'#1e2a3a'},ticks:{color:'#8b949e'},beginAtZero:true} } }
            });
        }
    }
    // Post-render: charts for stats tab
    if (tab === 'stats') {
        var _sexe = d.par_sexe || {};
        var _cats = d.par_categorie || {};
        var _rpa = (d.resultats_par_annee || []).slice().reverse();
        // Sexe doughnut
        var _sc = document.getElementById('clubSexeChart' + s);
        if (_sc) {
            var _sk = Object.keys(_sexe);
            new Chart(_sc, {
                type: 'doughnut',
                data: { labels: _sk.map(function(k){return k==='M'?'Hommes':(k==='F'?'Femmes':k);}), datasets: [{ data: _sk.map(function(k){return _sexe[k];}), backgroundColor: ['#3b82f6','#ec4899','#64748b'], borderWidth: 0 }] },
                options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, font: { size: 10 }, color: '#8b949e' } } } }
            });
        }
        // Categories bar
        var _cc = document.getElementById('clubCatChart' + s);
        if (_cc) {
            var _ck = Object.keys(_cats).slice(0, 12);
            new Chart(_cc, {
                type: 'bar',
                data: { labels: _ck, datasets: [{ data: _ck.map(function(k){return _cats[k];}), backgroundColor: '#34d399', borderRadius: 4, barThickness: 16 }] },
                options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { display: false }, ticks: { color: '#c8cfd8' } } } }
            });
        }
        // Evolution line chart
        var _ec = document.getElementById('clubEvoChart' + s);
        if (_ec && _rpa.length > 1) {
            new Chart(_ec, {
                type: 'line',
                data: {
                    labels: _rpa.map(function(r){return r.annee;}),
                    datasets: [
                        { label: 'Résultats', data: _rpa.map(function(r){return r.nb_resultats;}), borderColor: '#6366f1', backgroundColor: '#6366f120', fill: true, tension: 0.3, pointRadius: 3 },
                        { label: 'Athlètes', data: _rpa.map(function(r){return r.nb_athletes;}), borderColor: '#34d399', backgroundColor: '#34d39920', fill: true, tension: 0.3, pointRadius: 3 }
                    ]
                },
                options: { responsive: true, plugins: { legend: { position: 'top', labels: { usePointStyle: true, font: { size: 10 }, color: '#8b949e' } } }, scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } } }, interaction: { intersect: false, mode: 'index' } }
            });
        }
        // Distribution niveaux de compétition
        var _ep = d.epreuves || [];
        var _nivC = {}, _nivO = {IE:100,IR:99};
        ['N','R','D'].forEach(function(p){var b={N:90,R:80,D:70}[p];for(var i=1;i<=8;i++) _nivO[p+i]=b-i;});
        _ep.forEach(function(e) { (e.niveaux||[]).forEach(function(n){ if(n&&_nivO[n]) _nivC[n]=(_nivC[n]||0)+1; }); });
        var _nck = Object.keys(_nivC).sort(function(a,b){ return (_nivO[a]||0)-(_nivO[b]||0); });
        var _cvs = document.getElementById('clubNivChart' + s);
        if (_cvs && _nck.length > 2) {
            var _clrs = _nck.map(function(k){
                var c=k.charAt(0);
                return c==='I'?'#e879f9': c==='N'?'#fb7185': c==='R'?'#22d3ee': '#fb923c';
            });
            new Chart(_cvs, {
                type: 'line',
                data: {
                    labels: _nck,
                    datasets: [{
                        label: '\u00c9preuves',
                        data: _nck.map(function(k){ return _nivC[k]; }),
                        borderColor: '#a29bfe',
                        backgroundColor: '#a29bfe15',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: _clrs,
                        pointBorderColor: _clrs,
                        pointRadius: 5,
                        pointHoverRadius: 8,
                        borderWidth: 2.5
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: function(ctx) { return ctx.parsed.y + ' \u00e9preuve' + (ctx.parsed.y > 1 ? 's' : ''); } } }
                    },
                    scales: {
                        x: { grid: { color: '#1e2a3a' }, ticks: { color: function(ctx) { var lbl=ctx.tick.label||'';var c=lbl.charAt(0);return c==='I'?'#e879f9':c==='N'?'#fb7185':c==='R'?'#22d3ee':'#fb923c'; }, font: { weight:'bold', size:11 } } },
                        y: { beginAtZero: true, grid: { color: '#1e2a3a' }, ticks: { color: '#5a6580', stepSize: 1 } }
                    }
                }
            });
        }
        // Évolution niveaux par année
        var _npa = d.niveaux_par_annee || [];
        var _ycvs = document.getElementById('clubNivYearChart' + s);
        if (_ycvs && _npa.length > 1) {
            var _yLabels = _npa.map(function(r){ return r.annee; });
            var _families = [
                { key:'D', label:'D\u00e9partemental', color:'#fb923c', bg:'#fb923c20' },
                { key:'R', label:'R\u00e9gional', color:'#22d3ee', bg:'#22d3ee20' },
                { key:'N', label:'National', color:'#fb7185', bg:'#fb718520' },
                { key:'I', label:'International', color:'#e879f9', bg:'#e879f920' }
            ];
            var _yds = [];
            _families.forEach(function(f) {
                var hasData = _npa.some(function(r){ return (r[f.key]||0) > 0; });
                if (hasData) {
                    _yds.push({ label:f.label, data:_npa.map(function(r){return r[f.key]||0;}), borderColor:f.color, backgroundColor:f.bg, tension:0.4, fill:false, pointRadius:4, pointHoverRadius:7, borderWidth:2.5 });
                }
            });
            new Chart(_ycvs, {
                type: 'line',
                data: { labels: _yLabels, datasets: _yds },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position:'bottom', labels: { padding:12, usePointStyle:true, pointStyleWidth:10, font:{size:11}, color:'#8b949e' } } },
                    scales: {
                        x: { grid:{color:'#1e2a3a'}, ticks:{color:'#8b949e', font:{size:11}} },
                        y: { beginAtZero:true, grid:{color:'#1e2a3a'}, ticks:{color:'#5a6580'} }
                    }
                }
            });
        }
    }
}
// --- Resume club : fonctions utilitaires ---
function _buildResumeText(d, annee) {
    var txt = [];
    var nom = d.club.nom_club;
    var nbAth = d.total_athletes || 0;
    var sexe = d.par_sexe || {};
    var cats = d.par_categorie || {};
    var nats = d.nationalites || {};
    var med = d.medailles || { or: 0, argent: 0, bronze: 0 };
    var medDetail = d.medailles_detail || [];
    var pod = d.podiums || {};
    var totalPod = d.total_podiums || 0;
    var podNiv = d.podium_niveaux || [];
    var sel = d.selections || {};
    var ep = d.epreuves || [];
    var rec = d.records || [];
    var totalRec = d.total_records || 0;
    var topAth = d.top_athletes || [];
    var topVilles = d.top_villes || [];
    var prog = d.progressions || {};
    var nivRes = d.niveaux_resultats || [];
    var nbResGlobal = d.nb_resultats_global || 0;
    var nbEpGlobal = d.nb_epreuves_global || 0;
    var anDebut = d.annee_debut;
    var anFin = d.annee_fin;
    var anDispo = d.annees_disponibles || [];
    var nivData = d.niveaux || [];
    var bestNiv = d.meilleur_niveau;
    var nivMap = {N1:'National 1 (\u00c9lite)',N2:'National 2',N3:'National 3',N4:'National 4',R1:'R\u00e9gional 1',R2:'R\u00e9gional 2',R3:'R\u00e9gional 3',R4:'R\u00e9gional 4',R5:'R\u00e9gional 5',R6:'R\u00e9gional 6',D1:'D\u00e9partemental 1',D2:'D\u00e9partemental 2',D3:'D\u00e9partemental 3',D4:'D\u00e9partemental 4',D5:'D\u00e9partemental 5',D6:'D\u00e9partemental 6',D7:'D\u00e9partemental 7',D8:'D\u00e9partemental 8',IR:'Interr\u00e9gional',IE:'International \u00c9lite'};
    var catMap = {SE:'S\u00e9niors',ES:'Espoirs',JU:'Juniors',CA:'Cadets',MI:'Minimes',BE:'Benjamins',PO:'Poussins',EA:'\u00c9cole d\'athl\u00e9tisme',V1:'V\u00e9t\u00e9rans 1',V2:'V\u00e9t\u00e9rans 2',V3:'V\u00e9t\u00e9rans 3',V4:'V\u00e9t\u00e9rans 4'};
    function _n(v) { return v ? v.toLocaleString('fr-FR') : '0'; }
    function _pl(n, s, p) { return n > 1 ? (p || s + 's') : s; }

    // §1 — Introduction & période
    if (annee) {
        var intro = 'En ' + annee + ', le ' + nom + ' comptait ' + _n(nbAth) + ' athl\u00e8te' + _pl(nbAth, '') + ' actif' + _pl(nbAth, '') + '.';
        txt.push(intro);
    } else {
        var intro = 'Le ' + nom + ' est un club d\'athl\u00e9tisme';
        if (anDebut && anFin && anFin !== anDebut) {
            intro += ' actif de ' + anDebut + ' \u00e0 ' + anFin + ', soit ' + (anFin - anDebut + 1) + ' saisons d\'activit\u00e9';
        } else if (anDebut && anFin && anFin === anDebut) {
            intro += ' dont l\'activit\u00e9 se limite \u00e0 la saison ' + anDebut;
        } else if (anDebut) {
            intro += ' en activit\u00e9 depuis ' + anDebut;
        }
        intro += '. Il totalise ' + _n(nbAth) + ' athl\u00e8te' + _pl(nbAth, '') + ' enregistr\u00e9' + _pl(nbAth, '') + '.';
        txt.push(intro);
    }

    // §2 — Effectifs & répartition sexe
    if (nbAth > 0) {
        var pEff = '';
        var sParts = [];
        if (sexe['M']) { var pctM = Math.round(sexe['M'] / nbAth * 100); sParts.push(_n(sexe['M']) + ' hommes (' + pctM + '%)'); }
        if (sexe['F']) { var pctF = Math.round(sexe['F'] / nbAth * 100); sParts.push(_n(sexe['F']) + ' femmes (' + pctF + '%)'); }
        if (sParts.length > 0) pEff = 'La r\u00e9partition par sexe compte ' + sParts.join(' et ') + '.';
        if (pEff) txt.push(pEff);
    }

    // §3 — Catégories d'âge
    var catKeys = Object.keys(cats);
    if (catKeys.length > 0) {
        var totalCatAth = 0;
        catKeys.forEach(function(k) { totalCatAth += cats[k]; });
        if (catKeys.length <= 5) {
            var catParts = catKeys.map(function(k) { return (catMap[k] || k) + ' (' + _n(cats[k]) + ', ' + Math.round(cats[k]/totalCatAth*100) + '%)'; });
            txt.push('Les athl\u00e8tes se r\u00e9partissent en ' + catKeys.length + ' cat\u00e9gories : ' + catParts.join(', ') + '.');
        } else {
            var topCats = catKeys.slice(0, 4).map(function(k) { return (catMap[k] || k) + ' (' + _n(cats[k]) + ', ' + Math.round(cats[k]/totalCatAth*100) + '%)'; });
            txt.push('Le club couvre ' + catKeys.length + ' cat\u00e9gories d\'\u00e2ge. Les plus repr\u00e9sent\u00e9es sont : ' + topCats.join(', ') + '.');
        }
    }

    // §4 — Nationalités
    var natKeys = Object.keys(nats);
    if (natKeys.length > 0) {
        var totalNatAth = 0;
        natKeys.forEach(function(k) { totalNatAth += nats[k]; });
        if (natKeys.length === 1) {
            txt.push('Les athl\u00e8tes sont de nationalit\u00e9 ' + natKeys[0] + ' (' + _n(nats[natKeys[0]]) + ').');
        } else if (natKeys.length <= 5) {
            var natParts = natKeys.map(function(k) { return k + ' (' + _n(nats[k]) + ')'; });
            txt.push('Le club rassemble ' + natKeys.length + ' nationalit\u00e9s : ' + natParts.join(', ') + '.');
        } else {
            var topNats = natKeys.slice(0, 4).map(function(k) { return k + ' (' + _n(nats[k]) + ')'; });
            txt.push('Le club rassemble des athl\u00e8tes de ' + natKeys.length + ' nationalit\u00e9s diff\u00e9rentes, principalement ' + topNats.join(', ') + '.');
        }
    }

    // §5 — Résultats en compétition
    var nbRes = annee ? (d.nb_resultats || 0) : nbResGlobal;
    var nbEp = annee ? (d.nb_epreuves || 0) : nbEpGlobal;
    if (nbRes > 0) {
        var pRes = (annee ? 'Sur cette saison, ' : 'Au total, ') + _n(nbRes) + ' r\u00e9sultat' + _pl(nbRes, '') + ' en comp\u00e9tition ' + (nbRes > 1 ? 'ont \u00e9t\u00e9 enregistr\u00e9s' : 'a \u00e9t\u00e9 enregistr\u00e9') + ' sur ' + _n(nbEp) + ' \u00e9preuve' + _pl(nbEp, '') + '.';
        txt.push(pRes);
    }

    // §6 — Niveaux de résultats (D/R/N/I)
    if (nivRes.length > 0) {
        var famCount = {D:0, R:0, N:0, I:0};
        var totalNivRes = 0;
        nivRes.forEach(function(n) {
            var f = n.niveau.charAt(0);
            if (famCount[f] !== undefined) famCount[f] += n.count;
            totalNivRes += n.count;
        });
        var famLabels = {D:'d\u00e9partemental',R:'r\u00e9gional',N:'national',I:'international'};
        var famParts = [];
        ['D','R','N','I'].forEach(function(f) {
            if (famCount[f] > 0) {
                famParts.push(famLabels[f] + ' ' + Math.round(famCount[f]/totalNivRes*100) + '% (' + _n(famCount[f]) + ')');
            }
        });
        if (famParts.length > 0) {
            txt.push('La r\u00e9partition par niveau de comp\u00e9tition : ' + famParts.join(', ') + '. Les niveaux les plus fr\u00e9quents sont ' + nivRes.slice(0,3).map(function(n){return n.niveau + ' (' + _n(n.count) + ')';}).join(', ') + '.');
        }
    }

    // §7 — Disciplines & records
    if (ep.length > 0) {
        var topEp = ep.slice(0, 5).map(function(e) { return e.epreuve + ' (' + e.nb_athletes + ' athl\u00e8tes, ' + e.nb_records + ' records)'; });
        txt.push('Le club est actif sur ' + ep.length + ' discipline' + _pl(ep.length, '') + '. Les plus pratiqu\u00e9es : ' + topEp.join(', ') + '.');
        var bestRecs = ep.filter(function(e) { return e.best_perf && e.best_athlete; }).slice(0, 5);
        if (bestRecs.length > 0) {
            var recParts = bestRecs.map(function(e) { return e.best_perf + ' au ' + e.epreuve + ' par ' + e.best_athlete; });
            txt.push('Meilleurs records du club : ' + recParts.join(' ; ') + '.');
        }
    }

    // §8 — Total records
    if (totalRec > 0) {
        var discRec = {};
        rec.forEach(function(r) { if (r.epreuve) discRec[r.epreuve] = true; });
        var nbDiscRec = Object.keys(discRec).length;
        txt.push((annee ? 'Cette ann\u00e9e, ' : 'Au total, ') + _n(totalRec) + ' record' + _pl(totalRec, '') + ' personnel' + _pl(totalRec, '') + ' ' + (totalRec > 1 ? 'sont recens\u00e9s' : 'est recens\u00e9') + (nbDiscRec > 0 ? ', r\u00e9partis sur ' + nbDiscRec + ' discipline' + _pl(nbDiscRec, '') : '') + '.');
    }

    // §9 — Progressions
    if (prog.nb_progressions > 0) {
        txt.push(_n(prog.nb_progressions) + ' progression' + _pl(prog.nb_progressions, '') + ' enregistr\u00e9e' + _pl(prog.nb_progressions, '') + ' sur ' + prog.nb_epreuves + ' \u00e9preuve' + _pl(prog.nb_epreuves, '') + '.');
    }

    // §10 — Médailles
    var topMedAth = d.top_medaille_athletes || [];
    var topMedComp = d.top_medaille_competitions || [];
    var topMedEp = d.top_medaille_epreuves || [];
    var totalMed = (med.or || 0) + (med.argent || 0) + (med.bronze || 0);
    if (totalMed > 0) {
        var pMed = (annee ? 'Cette ann\u00e9e, les athl\u00e8tes ont remport\u00e9 ' : 'Les athl\u00e8tes du club ont collectivement remport\u00e9 ') + _n(totalMed) + ' m\u00e9daille' + _pl(totalMed, '');
        var detMed = [];
        if (med.or > 0) detMed.push(med.or + ' en or (' + Math.round(med.or/totalMed*100) + '%)');
        if (med.argent > 0) detMed.push(med.argent + ' en argent (' + Math.round(med.argent/totalMed*100) + '%)');
        if (med.bronze > 0) detMed.push(med.bronze + ' en bronze (' + Math.round(med.bronze/totalMed*100) + '%)');
        pMed += ' : ' + detMed.join(', ') + '.';
        txt.push(pMed);
        // Top médaillés
        if (topMedAth.length > 0) {
            var athMedParts = topMedAth.slice(0, 5).map(function(a) {
                var info = a.athlete + ' (' + a.total + ' m\u00e9d.';
                if (a.or > 0) info += ', ' + a.or + ' or';
                info += ')';
                return info;
            });
            txt.push('Les athl\u00e8tes les plus m\u00e9daill\u00e9s : ' + athMedParts.join(' ; ') + '.');
        }
        // Top compétitions médaillées
        if (topMedComp.length > 0) {
            var compParts = topMedComp.slice(0, 4).map(function(c) { return c.competition + ' (' + c.total + ' m\u00e9d.' + (c.or > 0 ? ', ' + c.or + ' or' : '') + ')'; });
            txt.push('Comp\u00e9titions les plus m\u00e9daill\u00e9es : ' + compParts.join(', ') + '.');
        }
        // Top épreuves médaillées
        if (topMedEp.length > 0) {
            var epMedParts = topMedEp.slice(0, 4).map(function(e) { return e.epreuve + ' (' + e.total + ' m\u00e9d.' + (e.or > 0 ? ', ' + e.or + ' or' : '') + ')'; });
            txt.push('\u00c9preuves les plus m\u00e9daill\u00e9es : ' + epMedParts.join(', ') + '.');
        }
        // Détail médailles récentes
        if (medDetail.length > 0) {
            var medEx = medDetail.slice(0, 5).map(function(m) {
                var s = m.type.charAt(0).toUpperCase() + m.type.slice(1) + ' : ' + m.athlete;
                if (m.epreuve) s += ' (' + m.epreuve + ')';
                if (m.competition) s += ' \u00e0 ' + m.competition;
                if (m.annee && !annee) s += ' en ' + m.annee;
                return s;
            });
            txt.push('Derni\u00e8res m\u00e9dailles : ' + medEx.join(' ; ') + '.');
        }
    }

    // §11 — Podiums
    var topPodEp = d.top_podium_epreuves || [];
    if (totalPod > 0) {
        var pPod = (annee ? 'Cette ann\u00e9e, ' : '') + _n(totalPod) + ' podium' + _pl(totalPod, '') + ' enregistr\u00e9' + _pl(totalPod, '');
        var podDet = [];
        if (pod['1er'] > 0) podDet.push(pod['1er'] + ' premi\u00e8re' + _pl(pod['1er'], '') + ' place' + _pl(pod['1er'], '') + ' (' + Math.round(pod['1er']/totalPod*100) + '%)');
        if (pod['2e'] > 0) podDet.push(pod['2e'] + ' deuxi\u00e8me' + _pl(pod['2e'], '') + ' place' + _pl(pod['2e'], '') + ' (' + Math.round(pod['2e']/totalPod*100) + '%)');
        if (pod['3e'] > 0) podDet.push(pod['3e'] + ' troisi\u00e8me' + _pl(pod['3e'], '') + ' place' + _pl(pod['3e'], '') + ' (' + Math.round(pod['3e']/totalPod*100) + '%)');
        if (podDet.length > 0) pPod += ' : ' + podDet.join(', ');
        pPod += '.';
        txt.push(pPod);
        if (podNiv.length > 0) {
            var pnParts = podNiv.map(function(n) { return n.niveau + ' (' + _n(n.count) + ')'; });
            txt.push('Les podiums ont \u00e9t\u00e9 obtenus aux niveaux : ' + pnParts.join(', ') + '.');
        }
        if (topPodEp.length > 0) {
            var podEpParts = topPodEp.slice(0, 4).map(function(e) { return e.epreuve + ' (' + e.total + ' podiums' + (e.nb_1er > 0 ? ', ' + e.nb_1er + ' victoire' + _pl(e.nb_1er, '') : '') + ')'; });
            txt.push('\u00c9preuves les plus repr\u00e9sent\u00e9es sur les podiums : ' + podEpParts.join(', ') + '.');
        }
    }

    // §12 — Sélections nationales
    var athSel = d.athletes_selectionnes || [];
    if (sel.nb_selections > 0) {
        var pSel = _n(sel.nb_athletes) + ' athl\u00e8te' + _pl(sel.nb_athletes, '') + ' du club ' + (sel.nb_athletes > 1 ? 'ont \u00e9t\u00e9 s\u00e9lectionn\u00e9s' : 'a \u00e9t\u00e9 s\u00e9lectionn\u00e9') + ' en \u00e9quipe nationale, pour un total de ' + _n(sel.nb_selections) + ' s\u00e9lection' + _pl(sel.nb_selections, '');
        if (sel.nb_competitions > 0) pSel += ' dans ' + sel.nb_competitions + ' comp\u00e9tition' + _pl(sel.nb_competitions, '');
        pSel += '.';
        if (athSel.length > 0) {
            var selParts = athSel.slice(0, 5).map(function(a) { return a.athlete + ' (' + a.nb_selections + ' s\u00e9l.)'; });
            pSel += ' Les plus s\u00e9lectionn\u00e9s : ' + selParts.join(', ') + '.';
        }
        txt.push(pSel);
    }

    // §12b — Évolution par année
    var rpa = d.resultats_par_annee || [];
    if (rpa.length > 1) {
        var rpaSorted = rpa.slice().sort(function(a,b) { return a.annee - b.annee; });
        var first = rpaSorted[0];
        var last = rpaSorted[rpaSorted.length - 1];
        var peak = rpaSorted.reduce(function(max, r) { return (r.nb_resultats||0) > (max.nb_resultats||0) ? r : max; }, rpaSorted[0]);
        var pEvo = 'L\'\u00e9volution de l\'activit\u00e9 montre ' + rpa.length + ' saisons de donn\u00e9es (de ' + first.annee + ' \u00e0 ' + last.annee + ').';
        pEvo += ' L\'ann\u00e9e la plus active est ' + peak.annee + ' avec ' + _n(peak.nb_resultats) + ' r\u00e9sultat' + _pl(peak.nb_resultats, '') + ' par ' + _n(peak.nb_athletes) + ' athl\u00e8te' + _pl(peak.nb_athletes, '') + '.';
        if (last.annee !== peak.annee) {
            pEvo += ' En ' + last.annee + ' : ' + _n(last.nb_resultats) + ' r\u00e9sultat' + _pl(last.nb_resultats, '') + ' par ' + _n(last.nb_athletes) + ' athl\u00e8te' + _pl(last.nb_athletes, '') + '.';
        }
        txt.push(pEvo);
    }

    // §13 — Niveaux de performance (athlete_niveaux)
    if (nivData.length > 0) {
        var totalNivAth = 0;
        nivData.forEach(function(n) { totalNivAth += n.nb_athletes; });
        var pNiv = 'En termes de niveau de performance (classement FFA), ' + _n(totalNivAth) + ' athl\u00e8te' + _pl(totalNivAth, '') + ' ' + (totalNivAth > 1 ? 'sont class\u00e9s' : 'est class\u00e9') + ' sur ' + nivData.length + ' niveau' + _pl(nivData.length, 'x', 'x');
        var topNivs = nivData.slice(0, 5).map(function(n) { return (nivMap[n.code_niveau] || n.code_niveau) + ' (' + _n(n.nb_athletes) + ' athl\u00e8te' + _pl(n.nb_athletes, '') + (n.max_points ? ', max ' + _n(n.max_points) + ' pts' : '') + ')'; });
        pNiv += ' : ' + topNivs.join(', ');
        if (nivData.length > 5) pNiv += ', etc';
        pNiv += '.';
        if (bestNiv) {
            pNiv += ' Le meilleur niveau atteint est ' + (nivMap[bestNiv.code_niveau] || bestNiv.code_niveau);
            if (bestNiv.athlete) pNiv += ' par ' + bestNiv.athlete;
            if (bestNiv.annee && !annee) pNiv += ' en ' + bestNiv.annee;
            if (bestNiv.points) pNiv += ' (' + _n(bestNiv.points) + ' points)';
            pNiv += '.';
        }
        txt.push(pNiv);
    }

    // §14 — Athlètes phares (top 5)
    if (topAth.length > 0) {
        var phares = topAth.slice(0, 5);
        var aParts = phares.map(function(a) {
            var info = a.nom_complet;
            var det = [];
            if (a.categorie) det.push(a.categorie);
            if (a.sexe) det.push(a.sexe === 'M' ? 'H' : 'F');
            if (a.nb_resultats > 0) det.push(a.nb_resultats + ' r\u00e9sultats');
            if (a.nb_records > 0) det.push(a.nb_records + ' records');
            if (det.length > 0) info += ' (' + det.join(', ') + ')';
            return info;
        });
        txt.push('Les athl\u00e8tes les plus actifs du club sont ' + aParts.join(' ; ') + '.');
    }

    // §15 — Lieux de compétition (top villes)
    if (topVilles.length > 0) {
        var vParts = topVilles.map(function(v) { return v.ville + ' (' + _n(v.nb_resultats) + ' r\u00e9sultats, ' + v.nb_athletes + ' athl\u00e8tes)'; });
        txt.push('Les principaux lieux de comp\u00e9tition : ' + vParts.join(', ') + '.');
    }

    // §16 — Durée & activité
    if (!annee && anDebut && anFin) {
        var duree = anFin - anDebut;
        if (duree === 0) {
            txt.push('Le club n\'a \u00e9t\u00e9 actif que durant une seule saison (' + anDebut + ').');
        } else if (duree > 0) {
            var inactif = new Date().getFullYear() - anFin;
            if (inactif > 2) {
                txt.push('Apr\u00e8s ' + (duree + 1) + ' ann\u00e9es d\'activit\u00e9, le club ne semble plus actif depuis ' + anFin + '.');
            } else {
                txt.push('Le club cumule ' + (duree + 1) + ' ann\u00e9es d\'activit\u00e9 \u00e0 ce jour.');
            }
        }
    }

    // §17 — Saisons disponibles
    if (!annee && anDispo.length > 1) {
        var recent = anDispo.slice(0, 3).join(', ');
        txt.push('Les saisons les plus r\u00e9centes avec donn\u00e9es : ' + recent + ' (sur ' + anDispo.length + ' saisons au total).');
    }

    return txt.join('\n\n');
}
function _buildNatResumeText(d) {
    var txt = [];
    var nom = d.club.nom_club;
    var nat = d.nationalites || {};
    var keys = Object.keys(nat);
    var totalNat = 0;
    keys.forEach(function(k) { totalNat += nat[k]; });
    if (keys.length === 0) return 'Aucune donnée de nationalité disponible pour ce club.';

    // Intro
    var p1 = 'Le club ' + nom + ' regroupe des athlètes de ' + keys.length + ' nationalité' + (keys.length > 1 ? 's' : '') + ' différente' + (keys.length > 1 ? 's' : '') + ', pour un total de ' + totalNat.toLocaleString('fr-FR') + ' athlètes.';
    txt.push(p1);

    // Nationalité dominante
    var top1 = keys[0];
    var top1pct = Math.round(nat[top1] / totalNat * 100);
    var p2 = 'La nationalité la plus représentée est ' + top1 + ' avec ' + nat[top1].toLocaleString('fr-FR') + ' athlète' + (nat[top1] > 1 ? 's' : '') + ', soit ' + top1pct + '% de l\'effectif.';
    if (keys.length >= 2) {
        var top2 = keys[1];
        var top2pct = Math.round(nat[top2] / totalNat * 100);
        p2 += ' Vient ensuite ' + top2 + ' avec ' + nat[top2].toLocaleString('fr-FR') + ' athlète' + (nat[top2] > 1 ? 's' : '') + ' (' + top2pct + '%).';
    }
    if (keys.length >= 3) {
        var top3 = keys[2];
        var top3pct = Math.round(nat[top3] / totalNat * 100);
        p2 += ' En troisième position, ' + top3 + ' avec ' + nat[top3].toLocaleString('fr-FR') + ' athlète' + (nat[top3] > 1 ? 's' : '') + ' (' + top3pct + '%).';
    }
    txt.push(p2);

    // Top 5
    if (keys.length >= 5) {
        var top5 = keys.slice(0, 5).map(function(k) { return k + ' (' + nat[k] + ')'; });
        txt.push('Le top 5 des nationalités : ' + top5.join(', ') + '.');
    }

    // Diversité
    if (keys.length >= 10) {
        var topHalf = 0;
        var topCount = Math.min(5, keys.length);
        for (var i = 0; i < topCount; i++) topHalf += nat[keys[i]];
        var topPct = Math.round(topHalf / totalNat * 100);
        txt.push('Les ' + topCount + ' premières nationalités concentrent ' + topPct + '% des athlètes. Les ' + (keys.length - topCount) + ' autres se partagent les ' + (100 - topPct) + '% restants, témoignant ' + (keys.length >= 15 ? 'd\'une grande diversité culturelle.' : 'd\'une diversité notable.'));
    }

    // Hors nationalité dominante
    if (keys.length >= 2) {
        var foreign = keys.filter(function(k) { return k !== top1; });
        var foreignTotal = 0;
        foreign.forEach(function(k) { foreignTotal += nat[k]; });
        var foreignPct = Math.round(foreignTotal / totalNat * 100);
        txt.push('En dehors des athlètes ' + top1 + ', ' + foreignTotal.toLocaleString('fr-FR') + ' athlète' + (foreignTotal > 1 ? 's' : '') + ' (' + foreignPct + '%) représentent ' + foreign.length + ' nationalité' + (foreign.length > 1 ? 's' : '') + ' étrangère' + (foreign.length > 1 ? 's' : '') + '.');
    }

    // Nationalités rares (1 seul athlète)
    var rares = keys.filter(function(k) { return nat[k] === 1; });
    if (rares.length > 0) {
        if (rares.length <= 5) {
            txt.push(rares.length + ' nationalité' + (rares.length > 1 ? 's sont' : ' est') + ' représentée' + (rares.length > 1 ? 's' : '') + ' par un seul athlète : ' + rares.join(', ') + '.');
        } else {
            txt.push(rares.length + ' nationalités ne comptent qu\'un seul représentant, parmi lesquelles ' + rares.slice(0, 5).join(', ') + '...');
        }
    }

    // Continents (estimation basique)
    var africa = ['MAR','ALG','TUN','SEN','CMR','CIV','CGO','COD','MLI','BEN','TGO','GIN','BFA','GAB','NER','MRT','TCD','RWA','BDI','ETH','KEN','GHA','NGA','EGY','MDG','COM','MUS','DJI','ERI','SOM','UGA','TZA','MOZ','ZAF','ZMB','ZWE','ANG','CPV'];
    var europe = ['FRA','GBR','ESP','POR','ITA','ALL','GER','DEU','BEL','NED','NLD','SUI','CHE','AUT','POL','ROU','HUN','CZE','SVK','GRE','BUL','CRO','SRB','BIH','MNE','MKD','SLO','SVN','LTU','LVA','EST','FIN','SWE','NOR','DEN','DNK','ISL','IRL','LUX','MLT','CYP','UKR','BLR','MDA','RUS','TUR','GEO','ARM','AZE'];
    var afCount = 0, euCount = 0, otherCount = 0;
    keys.forEach(function(k) {
        if (africa.indexOf(k) !== -1) afCount += nat[k];
        else if (europe.indexOf(k) !== -1) euCount += nat[k];
        else otherCount += nat[k];
    });
    var parts = [];
    if (euCount > 0) parts.push('Europe : ' + euCount.toLocaleString('fr-FR') + ' (' + Math.round(euCount/totalNat*100) + '%)');
    if (afCount > 0) parts.push('Afrique : ' + afCount.toLocaleString('fr-FR') + ' (' + Math.round(afCount/totalNat*100) + '%)');
    if (otherCount > 0) parts.push('Autres : ' + otherCount.toLocaleString('fr-FR') + ' (' + Math.round(otherCount/totalNat*100) + '%)');
    if (parts.length >= 2) {
        txt.push('Répartition géographique estimée : ' + parts.join(', ') + '.');
    }

    // Sexe par nationalité top 3 si données disponibles
    var sexe = d.par_sexe || {};
    var nbH = sexe['M'] || 0;
    var nbF = sexe['F'] || 0;
    if (nbH > 0 && nbF > 0) {
        var ratioHF = Math.round(nbH / (nbH + nbF) * 100);
        txt.push('Au global, le club compte ' + ratioHF + '% d\'hommes et ' + (100 - ratioHF) + '% de femmes toutes nationalités confondues.');
    }

    // Conclusion
    if (keys.length >= 5) {
        txt.push('Avec ' + keys.length + ' nationalités représentées, ' + nom + ' est un club à forte dimension internationale, reflet de la diversité de l\'athlétisme français.');
    } else if (keys.length >= 2) {
        txt.push(nom + ' accueille des athlètes de ' + keys.length + ' nationalités différentes.');
    }

    return txt.join('\n\n');
}
function _buildResumeHTML(text) {
    var h = '<div style="border-left:3px solid #6c5ce7;padding:16px 20px;">';
    h += '<p style="color:#c8cfd8;line-height:1.9;font-size:14px;margin:0;white-space:pre-line;">' + text + '</p>';
    h += '<button onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent).then(function(){alert(\'R\u00e9sum\u00e9 copi\u00e9 !\')})" style="margin-top:12px;background:#253049;color:#a29bfe;border:1px solid #6c5ce740;border-radius:8px;padding:6px 14px;cursor:pointer;font-size:12px;">&#128203; Copier le texte</button>';
    h += '</div>';
    return h;
}
function _clubSetResumeMode(mode, suffix) {
    var s = suffix || '';
    window['_clubResumeMode' + s] = mode;
    if (mode === 'comparer' && !window['_clubCompareYears' + s]) {
        window['_clubCompareYears' + s] = [];
    }
    _renderClubTab('resume', s);
}
function _clubYearChanged(suffix) {
    var s = suffix || '';
    var sel = document.getElementById('clubYearSelect' + s);
    var year = sel ? parseInt(sel.value) : 0;
    if (!year) {
        window['_clubResumeYear' + s] = null;
        var container = document.getElementById('clubYearResume' + s);
        if (container) container.innerHTML = '<div style="color:#5a6580;text-align:center;padding:40px;">Selectionnez une annee pour afficher le resume</div>';
        return;
    }
    window['_clubResumeYear' + s] = year;
    var cache = window['_clubYearDataCache' + s];
    if (cache[year]) {
        var container = document.getElementById('clubYearResume' + s);
        if (container) container.innerHTML = _buildResumeHTML(_buildResumeText(cache[year], year));
        return;
    }
    var loading = document.getElementById('clubYearLoading' + s);
    if (loading) loading.style.display = 'inline';
    var d = window['_clubDetailData' + s];
    fetch(BASE_API + '/club_stats.php?id=' + d.club.id_club + '&annee=' + year + _clubFilterParams(d))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (loading) loading.style.display = 'none';
            if (data.success) {
                window['_clubYearDataCache' + s][year] = data;
                var container = document.getElementById('clubYearResume' + s);
                if (container) container.innerHTML = _buildResumeHTML(_buildResumeText(data, year));
            }
        })
        .catch(function() { if (loading) loading.style.display = 'none'; });
}
function _clubToggleCompareYear(year, checked, suffix) {
    var s = suffix || '';
    var arr = window['_clubCompareYears' + s] || [];
    if (checked) {
        if (arr.length >= 6) {
            alert('Maximum 6 annees pour la comparaison');
            _renderClubTab('resume', s);
            return;
        }
        if (arr.indexOf(year) === -1) arr.push(year);
    } else {
        arr = arr.filter(function(y) { return y !== year; });
    }
    arr.sort(function(a,b) { return a - b; });
    window['_clubCompareYears' + s] = arr;
    _renderClubTab('resume', s);
}
function _clubRunCompare(suffix) {
    var s = suffix || '';
    var years = window['_clubCompareYears' + s] || [];
    if (years.length < 2) return;
    var d = window['_clubDetailData' + s];
    var cache = window['_clubYearDataCache' + s];
    var container = document.getElementById('clubCompareResult' + s);
    if (!container) return;
    container.innerHTML = '<div style="color:#5a6580;text-align:center;padding:20px;">Chargement des donnees...</div>';

    // Fetch missing years
    var fetches = years.map(function(y) {
        if (cache[y]) return Promise.resolve(cache[y]);
        return fetch(BASE_API + '/club_stats.php?id=' + d.club.id_club + '&annee=' + y + _clubFilterParams(d))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) cache[y] = data;
                return data;
            });
    });

    Promise.all(fetches).then(function(results) {
        var validYears = [];
        var validData = [];
        years.forEach(function(y, i) {
            if (results[i] && results[i].success) {
                validYears.push(y);
                validData.push(results[i]);
            }
        });
        if (validYears.length < 2) {
            container.innerHTML = '<div style="color:#ff6b6b;text-align:center;padding:20px;">Donnees insuffisantes pour comparer</div>';
            return;
        }
        // Build comparison table
        var h = '<div class="table-wrap" style="margin-bottom:20px;">';
        h += '<table class="bk-table"><tr><th style="min-width:160px;">Indicateur</th>';
        validYears.forEach(function(y) { h += '<th style="text-align:center;">' + y + '</th>'; });
        h += '</tr>';

        // Athletes actifs
        h += '<tr><td style="color:#8b949e;">Athletes actifs</td>';
        validData.forEach(function(dt) { h += '<td style="text-align:center;font-weight:600;color:#55efc4;">' + (dt.total_athletes || 0) + '</td>'; });
        h += '</tr>';

        // Hommes / Femmes
        h += '<tr><td style="color:#8b949e;">Hommes / Femmes</td>';
        validData.forEach(function(dt) {
            var sx = dt.par_sexe || {};
            h += '<td style="text-align:center;">' + (sx['M'] || 0) + ' / ' + (sx['F'] || 0) + '</td>';
        });
        h += '</tr>';

        // Nb resultats
        h += '<tr><td style="color:#8b949e;">Resultats</td>';
        validData.forEach(function(dt) { h += '<td style="text-align:center;">' + (dt.nb_resultats !== undefined ? dt.nb_resultats : '-') + '</td>'; });
        h += '</tr>';

        // Nb epreuves
        h += '<tr><td style="color:#8b949e;">Epreuves</td>';
        validData.forEach(function(dt) { h += '<td style="text-align:center;">' + (dt.nb_epreuves !== undefined ? dt.nb_epreuves : '-') + '</td>'; });
        h += '</tr>';

        // Medailles total
        h += '<tr><td style="color:#8b949e;">Medailles (total)</td>';
        validData.forEach(function(dt) {
            var m = dt.medailles || {};
            h += '<td style="text-align:center;font-weight:600;">' + ((m.or||0)+(m.argent||0)+(m.bronze||0)) + '</td>';
        });
        h += '</tr>';

        // Medailles detail
        h += '<tr><td style="color:#8b949e;padding-left:24px;">Or / Argent / Bronze</td>';
        validData.forEach(function(dt) {
            var m = dt.medailles || {};
            h += '<td style="text-align:center;font-size:12px;">' + (m.or||0) + ' / ' + (m.argent||0) + ' / ' + (m.bronze||0) + '</td>';
        });
        h += '</tr>';

        // Records
        h += '<tr><td style="color:#8b949e;">Records</td>';
        validData.forEach(function(dt) { h += '<td style="text-align:center;">' + (dt.records ? dt.records.length : 0) + '</td>'; });
        h += '</tr>';

        // Meilleur niveau
        var nivMap = {N1:'N1 (Elite)',N2:'N2',N3:'N3',N4:'N4',R1:'R1',R2:'R2',R3:'R3',R4:'R4',R5:'R5',R6:'R6',D1:'D1',D2:'D2',D3:'D3',D4:'D4',D5:'D5',D6:'D6',D7:'D7',IR:'IR',IE:'IE'};
        h += '<tr><td style="color:#8b949e;">Meilleur niveau</td>';
        validData.forEach(function(dt) {
            var bn = dt.meilleur_niveau;
            if (bn) {
                h += '<td style="text-align:center;"><span style="color:#a29bfe;font-weight:600;">' + (nivMap[bn.code_niveau]||bn.code_niveau) + '</span>';
                if (bn.athlete) h += '<br><span style="font-size:11px;color:#5a6580;">' + escapeHtml(bn.athlete) + '</span>';
                h += '</td>';
            } else {
                h += '<td style="text-align:center;color:#5a6580;">-</td>';
            }
        });
        h += '</tr>';

        // Top performeur
        h += '<tr><td style="color:#8b949e;">Top performeur</td>';
        validData.forEach(function(dt) {
            var ta = dt.top_athletes || [];
            if (ta.length > 0) {
                h += '<td style="text-align:center;"><span style="color:#a29bfe;">' + escapeHtml(ta[0].nom_complet) + '</span>';
                h += '<br><span style="font-size:11px;color:#5a6580;">' + ta[0].nb_resultats + ' res. / ' + ta[0].nb_records + ' rec.</span></td>';
            } else {
                h += '<td style="text-align:center;color:#5a6580;">-</td>';
            }
        });
        h += '</tr>';

        h += '</table></div>';

        // Chart.js grouped bar chart
        h += '<div style="margin-top:16px;"><canvas id="clubCompareChart' + s + '" height="280"></canvas></div>';

        container.innerHTML = h;

        // Render chart
        var ctx = document.getElementById('clubCompareChart' + s);
        if (ctx) {
            if (window['_clubCompareChart' + s]) window['_clubCompareChart' + s].destroy();
            var chartLabels = validYears.map(function(y) { return '' + y; });
            window['_clubCompareChart' + s] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [
                        {
                            label: 'Athletes',
                            data: validData.map(function(dt) { return dt.total_athletes || 0; }),
                            backgroundColor: '#6c5ce7'
                        },
                        {
                            label: 'Medailles',
                            data: validData.map(function(dt) { var m=dt.medailles||{}; return (m.or||0)+(m.argent||0)+(m.bronze||0); }),
                            backgroundColor: '#ffd700'
                        },
                        {
                            label: 'Records',
                            data: validData.map(function(dt) { return dt.records ? dt.records.length : 0; }),
                            backgroundColor: '#55efc4'
                        },
                        {
                            label: 'Resultats',
                            data: validData.map(function(dt) { return dt.nb_resultats || 0; }),
                            backgroundColor: '#ff7675'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#8b949e' } } },
                    scales: {
                        x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } },
                        y: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' }, beginAtZero: true }
                    }
                }
            });
        }
    }).catch(function() {
        container.innerHTML = '<div style="color:#ff6b6b;text-align:center;padding:20px;">Erreur lors du chargement</div>';
    });
}

// Wrappers pour chaque page
function openClubDetail(id, nom) {
    var url = nom ? BASE_API + '/club_stats.php?nom=' + encodeURIComponent(nom) : BASE_API + '/club_stats.php?id=' + id;
    _openClubPanel(url, '');
}
function closeClubDetail() { _closeClubPanel(''); }
function switchClubTab(tab) { _switchClubTab(tab, ''); }
function renderClubTab(tab) { _renderClubTab(tab, ''); }
function _clubFilterParams(d) {
    var p = '';
    if (d.filter_nationalite) p += '&nationalite=' + encodeURIComponent(d.filter_nationalite);
    if (d.filter_sexe) p += '&sexe=' + encodeURIComponent(d.filter_sexe);
    if (d.filter_categorie) p += '&categorie=' + encodeURIComponent(d.filter_categorie);
    return p;
}
function loadClubRecPage(page, suffix) {
    var s = suffix || '';
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&rp=' + page;
    if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement page ' + page + '...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.records = data.records;
        d.total_records = data.total_records;
        d.rec_page = data.rec_page;
        d.rec_pages = data.rec_pages;
        _renderClubTab('records', s);
    });
}
function loadClubPerfsPage(page, suffix) {
    var s = suffix || '';
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var perfMode = window['_clubPerfMode' + s] || 'all';
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&pp=' + page + '&pm=' + perfMode;
    if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement page ' + page + '...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.performances = data.performances;
        d.total_performances = data.total_performances;
        d.perf_page = data.perf_page;
        d.perf_pages = data.perf_pages;
        _renderClubTab('performances', s);
    });
}
function _clubSetPerfMode(mode, suffix) {
    var s = suffix || '';
    window['_clubPerfMode' + s] = mode;
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&pp=1&pm=' + mode;
    if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.performances = data.performances;
        d.total_performances = data.total_performances;
        d.perf_page = data.perf_page;
        d.perf_pages = data.perf_pages;
        _renderClubTab('performances', s);
    });
}
function loadClubEpPage(page, suffix) {
    var s = suffix || '';
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var epMode = window['_clubEpMode' + s] || 'club';
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&ep=' + page;
    if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
    if (epMode === 'perso') url += '&perso=1';
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement page ' + page + '...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.epreuves = data.epreuves;
        d.total_epreuves = data.total_epreuves;
        d.ep_page = data.ep_page;
        d.ep_pages = data.ep_pages;
        _renderClubTab('epreuves', s);
    });
}
function _clubToggleRecDisc(disc, suffix) {
    var s = suffix || '';
    if (disc === null) {
        window['_clubRecDiscFilter' + s] = null;
    } else {
        var cur = window['_clubRecDiscFilter' + s] || [];
        var idx = cur.indexOf(disc);
        if (idx === -1) { cur.push(disc); } else { cur.splice(idx, 1); }
        window['_clubRecDiscFilter' + s] = cur.length > 0 ? cur : null;
    }
    _renderClubTab('records', s);
}
function _clubToggleDisc(disc, suffix) {
    var s = suffix || '';
    if (disc === null) {
        window['_clubDiscFilter' + s] = null;
    } else {
        var cur = window['_clubDiscFilter' + s] || [];
        var idx = cur.indexOf(disc);
        if (idx === -1) { cur.push(disc); } else { cur.splice(idx, 1); }
        window['_clubDiscFilter' + s] = cur.length > 0 ? cur : null;
    }
    _renderClubTab('epreuves', s);
}
function _clubToggleNiv(niv, suffix) {
    var s = suffix || '';
    if (niv === null) {
        window['_clubNivFilter' + s] = null;
    } else {
        var cur = window['_clubNivFilter' + s] || [];
        var idx = cur.indexOf(niv);
        if (idx === -1) { cur.push(niv); } else { cur.splice(idx, 1); }
        window['_clubNivFilter' + s] = cur.length > 0 ? cur : null;
    }
    _renderClubTab('epreuves', s);
}
function _clubEpYearModeSet(mode, suffix) {
    var s = suffix || '';
    window['_clubEpYearMode' + s] = mode;
    if (mode === 'filter') {
        window['_clubEpYearCmp' + s] = [];
        window['_clubEpYearCmpData' + s] = null;
    }
    _renderClubTab('epreuves', s);
}
function _clubToggleEpYearCmp(yr, suffix) {
    var s = suffix || '';
    var cmp = window['_clubEpYearCmp' + s] || [];
    var idx = cmp.indexOf(yr);
    if (idx === -1) {
        if (cmp.length < 5) cmp.push(yr);
    } else {
        cmp.splice(idx, 1);
    }
    window['_clubEpYearCmp' + s] = cmp;
    window['_clubEpYearCmpData' + s] = null;
    _renderClubTab('epreuves', s);
}
function _clubRunEpYearCmp(suffix) {
    var s = suffix || '';
    var d = window['_clubDetailData' + s];
    var cmpYears = (window['_clubEpYearCmp' + s] || []).slice().sort();
    if (!d || !d.club || cmpYears.length < 2) return;
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement comparaison ' + cmpYears.join(' vs ') + '...</div>';
    var epMode = window['_clubEpMode' + s] || 'club';
    var results = {};
    var done = 0;
    cmpYears.forEach(function(yr) {
        var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&annee=' + yr;
        if (epMode === 'perso') url += '&perso=1';
        url += _clubFilterParams(d);
        fetch(url).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) results[yr] = data;
            done++;
            if (done === cmpYears.length) {
                window['_clubEpYearCmpData' + s] = results;
                _renderClubTab('epreuves', s);
            }
        }).catch(function() {
            done++;
            if (done === cmpYears.length) {
                window['_clubEpYearCmpData' + s] = results;
                _renderClubTab('epreuves', s);
            }
        });
    });
}
function _buildEpYearCmpHTML(cmpData, suffix) {
    var s = suffix || '';
    var years = Object.keys(cmpData).map(Number).sort();
    var h = '';
    // Tableau comparatif
    h += '<div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;margin-bottom:16px;">';
    h += '<h4 style="margin:0 0 12px;color:#c9d1d9;font-size:14px;">Comparaison ' + years.join(' / ') + '</h4>';
    var thRow = '<tr><th>Métrique</th>';
    years.forEach(function(y) { thRow += '<th style="text-align:center;">' + y + '</th>'; });
    thRow += '</tr>';
    h += '<div class="table-wrap">';
    h += '<table class="bk-table">' + thRow + '</table>';
    h += '<table class="bk-table">';
    // Lignes métriques
    var metrics = [
        {k:'total_epreuves', l:'Épreuves'},
        {k:'total_athletes', l:'Athlètes'},
        {k:'total_records', l:'Records'},
        {k:'nb_resultats', l:'Résultats'}
    ];
    metrics.forEach(function(m) {
        h += '<tr><td style="color:#8b949e;">' + m.l + '</td>';
        var vals = years.map(function(y) { return cmpData[y][m.k] || 0; });
        var maxV = Math.max.apply(null, vals);
        years.forEach(function(y, i) {
            var v = vals[i];
            var clr = v === maxV && maxV > 0 ? '#55efc4' : '#c9d1d9';
            h += '<td style="text-align:center;color:' + clr + ';font-weight:' + (v===maxV&&maxV>0?'700':'400') + ';">' + v.toLocaleString('fr-FR') + '</td>';
        });
        h += '</tr>';
    });
    // Médailles
    ['or','argent','bronze'].forEach(function(type) {
        var icons = {or:'&#129351;', argent:'&#129352;', bronze:'&#129353;'};
        h += '<tr><td style="color:#8b949e;">' + icons[type] + ' ' + type.charAt(0).toUpperCase()+type.slice(1) + '</td>';
        var vals = years.map(function(y) { var med = cmpData[y].medailles || {}; return med[type] || 0; });
        var maxV = Math.max.apply(null, vals);
        years.forEach(function(y, i) {
            var v = vals[i];
            var clr = v === maxV && maxV > 0 ? '#ffd700' : '#c9d1d9';
            h += '<td style="text-align:center;color:' + clr + ';font-weight:' + (v===maxV&&maxV>0?'700':'400') + ';">' + v + '</td>';
        });
        h += '</tr>';
    });
    // Niveaux D/R/N/I
    ['D','R','N','I'].forEach(function(fam) {
        var clrs = {D:'#fb923c',R:'#22d3ee',N:'#fb7185',I:'#e879f9'};
        h += '<tr><td style="color:' + clrs[fam] + ';">Niveau ' + fam + '</td>';
        var vals = years.map(function(y) {
            var npa = cmpData[y].niveaux_par_annee || [];
            var found = npa.find(function(n) { return n.annee == y; });
            return found ? (found[fam] || 0) : 0;
        });
        var maxV = Math.max.apply(null, vals);
        years.forEach(function(y, i) {
            var v = vals[i];
            h += '<td style="text-align:center;color:' + (v===maxV&&maxV>0?clrs[fam]:'#5a6580') + ';font-weight:' + (v===maxV&&maxV>0?'700':'400') + ';">' + v + '</td>';
        });
        h += '</tr>';
    });
    h += '</table>';
    h += '<table class="bk-table">' + thRow + '</table>';
    h += '</div></div>';
    // Graphique comparatif
    h += '<div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;margin-bottom:16px;">';
    h += '<canvas id="clubEpYearCmpChart' + s + '" height="250"></canvas>';
    h += '</div>';
    // Top épreuves par année
    h += '<div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;margin-bottom:16px;">';
    h += '<h4 style="margin:0 0 12px;color:#c9d1d9;font-size:14px;">Top épreuves par année</h4>';
    h += '<div style="display:flex;gap:12px;flex-wrap:wrap;">';
    years.forEach(function(y) {
        var epList = (cmpData[y].epreuves || []).slice(0, 5);
        h += '<div style="flex:1;min-width:200px;">';
        h += '<h5 style="color:#a29bfe;margin:0 0 8px;font-size:13px;">' + y + '</h5>';
        if (epList.length === 0) { h += '<span style="color:#5a6580;font-size:12px;">Aucune</span>'; }
        epList.forEach(function(e, i) {
            h += '<div style="font-size:12px;color:#c9d1d9;padding:2px 0;">' + (i+1) + '. ' + (e.epreuve||'') + '</div>';
        });
        h += '</div>';
    });
    h += '</div></div>';
    // Résumé textuel comparatif
    h += '<div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;margin-bottom:16px;">';
    h += '<h4 style="margin:0 0 12px;color:#c9d1d9;font-size:14px;">Résumé comparatif</h4>';
    h += '<div style="color:#c9d1d9;font-size:13px;line-height:1.8;">';
    var _rLines = [];
    // Meilleure année par métrique
    var _mKeys = [
        {k:'total_epreuves', l:'épreuves'},
        {k:'total_athletes', l:'athlètes'},
        {k:'total_records', l:'records'},
        {k:'nb_resultats', l:'résultats'}
    ];
    _mKeys.forEach(function(m) {
        var best = null, bestV = -1;
        years.forEach(function(y) {
            var v = cmpData[y][m.k] || 0;
            if (v > bestV) { bestV = v; best = y; }
        });
        if (best && bestV > 0) {
            var worst = null, worstV = Infinity;
            years.forEach(function(y) {
                var v = cmpData[y][m.k] || 0;
                if (v < worstV) { worstV = v; worst = y; }
            });
            var diff = bestV - worstV;
            _rLines.push('<b>' + best + '</b> est la meilleure année en <span style="color:#a29bfe;">' + m.l + '</span> avec <b>' + bestV.toLocaleString('fr-FR') + '</b>' + (diff > 0 && worst !== best ? ' (<span style="color:#55efc4;">+' + diff.toLocaleString('fr-FR') + '</span> vs ' + worst + ')' : '') + '.');
        }
    });
    // Évolution médailles
    var _medTotals = {};
    years.forEach(function(y) {
        var med = cmpData[y].medailles || {};
        _medTotals[y] = (med.or || 0) + (med.argent || 0) + (med.bronze || 0);
    });
    var _bestMedY = null, _bestMedV = -1;
    years.forEach(function(y) { if (_medTotals[y] > _bestMedV) { _bestMedV = _medTotals[y]; _bestMedY = y; } });
    if (_bestMedY && _bestMedV > 0) {
        _rLines.push('<b>' + _bestMedY + '</b> cumule le plus de <span style="color:#ffd700;">médailles</span> avec <b>' + _bestMedV + '</b> au total.');
    }
    // Or spécifiquement
    var _bestOrY = null, _bestOrV = -1;
    years.forEach(function(y) { var v = (cmpData[y].medailles||{}).or||0; if(v>_bestOrV){_bestOrV=v;_bestOrY=y;} });
    if (_bestOrY && _bestOrV > 0) {
        _rLines.push('<b>' + _bestOrY + '</b> détient le record de <span style="color:#ffd700;">médailles d\'or</span> avec <b>' + _bestOrV + '</b>.');
    }
    // Tendance (première vs dernière année)
    if (years.length >= 2) {
        var first = years[0], last = years[years.length - 1];
        var _trends = [];
        _mKeys.forEach(function(m) {
            var vF = cmpData[first][m.k] || 0;
            var vL = cmpData[last][m.k] || 0;
            if (vF > 0 && vL > 0) {
                var pct = Math.round((vL - vF) / vF * 100);
                if (pct !== 0) {
                    _trends.push({l: m.l, pct: pct, up: pct > 0});
                }
            }
        });
        if (_trends.length > 0) {
            var ups = _trends.filter(function(t){return t.up;});
            var downs = _trends.filter(function(t){return !t.up;});
            if (ups.length > 0) {
                _rLines.push('Entre <b>' + first + '</b> et <b>' + last + '</b>, progression en ' + ups.map(function(t){return '<span style="color:#55efc4;">' + t.l + ' (+' + t.pct + '%)</span>';}).join(', ') + '.');
            }
            if (downs.length > 0) {
                _rLines.push('En revanche, baisse en ' + downs.map(function(t){return '<span style="color:#ff6b6b;">' + t.l + ' (' + t.pct + '%)</span>';}).join(', ') + '.');
            }
        }
    }
    // Niveaux compétition
    var _nivFams = ['D','R','N','I'];
    var _nivLabels = {D:'départemental',R:'régional',N:'national',I:'international'};
    var _nivClrs = {D:'#fb923c',R:'#22d3ee',N:'#fb7185',I:'#e879f9'};
    _nivFams.forEach(function(fam) {
        var bestY = null, bestV = -1;
        years.forEach(function(y) {
            var npa = cmpData[y].niveaux_par_annee || [];
            var found = npa.find(function(n){return n.annee==y;});
            var v = found ? (found[fam]||0) : 0;
            if (v > bestV) { bestV = v; bestY = y; }
        });
        if (bestY && bestV > 0) {
            _rLines.push('Niveau <span style="color:' + _nivClrs[fam] + ';">' + _nivLabels[fam] + '</span> : <b>' + bestY + '</b> en tête avec <b>' + bestV + '</b> résultats.');
        }
    });
    _rLines.forEach(function(line) {
        h += '<p style="margin:0 0 6px;padding-left:12px;border-left:2px solid #1e2a3a;">' + line + '</p>';
    });
    h += '</div></div>';
    return h;
}
function _clubSetEpYear(year, suffix) {
    var s = suffix || '';
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var epMode = window['_clubEpMode' + s] || 'club';
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&ep=1';
    if (year) url += '&annee=' + year;
    if (epMode === 'perso') url += '&perso=1';
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.epreuves = data.epreuves;
        d.total_epreuves = data.total_epreuves;
        d.ep_page = data.ep_page;
        d.ep_pages = data.ep_pages;
        d.annee_filtree = year || null;
        if (data.niveaux_par_annee) d.niveaux_par_annee = data.niveaux_par_annee;
        _renderClubTab('epreuves', s);
    });
}
function _clubSetEpMode(mode, suffix) {
    var s = suffix || '';
    window['_clubEpMode' + s] = mode;
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&ep=1';
    if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
    if (mode === 'perso') url += '&perso=1';
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.epreuves = data.epreuves;
        d.total_epreuves = data.total_epreuves;
        d.ep_page = data.ep_page;
        d.ep_pages = data.ep_pages;
        _renderClubTab('epreuves', s);
    });
}
function _clubSetNatMode(mode, suffix) {
    var s = suffix || '';
    window['_clubNatMode' + s] = mode;
    if (mode === 'overview') { window['_clubNatCmp' + s] = null; }
    _renderClubTab('nationalites', s);
}
function _clubToggleNatSel(code, suffix) {
    var s = suffix || '';
    var sel = window['_clubNatSel' + s] || [];
    var idx = sel.indexOf(code);
    if (idx === -1) { sel.push(code); } else { sel.splice(idx, 1); }
    window['_clubNatSel' + s] = sel;
    window['_clubNatCmp' + s] = null; // reset comparison data
    if (sel.length >= 2) {
        var d = window['_clubDetailData' + s];
        if (!d || !d.club) return;
        var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&nat_detail=' + encodeURIComponent(sel.join(','));
        if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
        url += _clubFilterParams(d);
        _renderClubTab('nationalites', s);
        fetch(url).then(function(r) { return r.json(); }).then(function(data) {
            if (data.nat_compare) {
                window['_clubNatCmp' + s] = data.nat_compare;
                _renderClubTab('nationalites', s);
            }
        });
    } else {
        _renderClubTab('nationalites', s);
    }
}
function openClubDetailAccueil(nom) { _openClubPanel(BASE_API + '/club_stats.php?nom=' + encodeURIComponent(nom), 'Accueil'); }
function closeClubDetailAccueil() { _closeClubPanel('Accueil'); }
function switchClubTabAccueil(tab) { _switchClubTab(tab, 'Accueil'); }

// --- Epreuve Detail Panel (tabbed, like club) ---
var _epreuveDetailData = null;

function openEpreuveDetail(nom) {
    window._ctxEpreuveName = nom;
    var panel = document.getElementById('epreuveDetailPanel');
    var content = document.getElementById('epreuveDetailContent');
    if (!panel || !content) return;
    panel.classList.add('active');
    content.innerHTML = '<div class="loading-msg">Chargement...</div>';
    document.getElementById('epreuveDetailName').textContent = nom;
    document.getElementById('epreuveDetailMeta').textContent = '';
    panel.querySelectorAll('.club-detail-tab').forEach(function(t) { t.classList.remove('active'); });
    var first = panel.querySelector('.club-detail-tab[data-tab="records"]');
    if (first) first.classList.add('active');

    fetch(BASE_API + '/epreuve_stats.php?nom=' + encodeURIComponent(nom) + '&limit=50')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { content.innerHTML = '<div class="loading-msg">Épreuve non trouvée</div>'; return; }
            _epreuveDetailData = data;
            var meta = data.total_athletes + ' athlètes | ' + data.total_records + ' records';
            var natKeys = Object.keys(data.nationalites || {});
            if (natKeys.length > 0) meta += ' | ' + natKeys.length + ' nationalités';
            if (data.annee_debut) meta += ' | ' + data.annee_debut + '-' + (data.annee_fin || '...');
            var med = data.medailles || {};
            if ((med.or||0) + (med.argent||0) + (med.bronze||0) > 0) {
                meta += ' | ';
                if (med.or > 0) meta += '\uD83E\uDD47' + med.or + ' ';
                if (med.argent > 0) meta += '\uD83E\uDD48' + med.argent + ' ';
                if (med.bronze > 0) meta += '\uD83E\uDD49' + med.bronze;
            }
            document.getElementById('epreuveDetailMeta').textContent = meta;
            var eqr = document.getElementById('epreuveQR');
            if (eqr) eqr.innerHTML = bkQR('https://bokonzi.com/?page=epreuves&nom=' + encodeURIComponent(nom));
            _renderEpreuveTab('records');
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            // Track epreuve panel open
            _trackSearch({ q: nom, type: 'epreuve', source: 'panel_open', entity_name: nom, pg: 'epreuve_panel' });
        })
        .catch(function() { content.innerHTML = '<div class="loading-msg">Erreur de chargement</div>'; });
}
function closeEpreuveDetail() {
    var panel = document.getElementById('epreuveDetailPanel');
    if (panel) panel.classList.remove('active');
    _epreuveDetailData = null;
}
function switchEpreuveTab(tab) {
    var panel = document.getElementById('epreuveDetailPanel');
    if (panel) panel.querySelectorAll('.club-detail-tab').forEach(function(t) {
        t.classList.toggle('active', t.getAttribute('data-tab') === tab);
    });
    _renderEpreuveTab(tab);
}
function loadEpreuveRecPage(page) {
    if (!_epreuveDetailData) return;
    var content = document.getElementById('epreuveDetailContent');
    if (content) content.innerHTML = '<div class="loading-msg">Chargement page ' + page + '...</div>';
    fetch(BASE_API + '/epreuve_stats.php?nom=' + encodeURIComponent(_epreuveDetailData.epreuve) + '&page=' + page + '&limit=50')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            _epreuveDetailData.records = data.records;
            _epreuveDetailData.page = data.page;
            _epreuveDetailData.total_pages = data.total_pages;
            _renderEpreuveTab('records');
        });
}

function _renderEpreuveTab(tab) {
    var content = document.getElementById('epreuveDetailContent');
    var d = _epreuveDetailData;
    if (!content || !d) return;
    var html = '';

    if (tab === 'records') {
        var rec = d.records || [];
        var totalRec = d.total_records || rec.length;
        var pg = d.page || 1;
        var totalPages = d.total_pages || 1;
        if (rec.length === 0 && pg === 1) { content.innerHTML = '<div class="loading-msg">Aucun record trouvé</div>'; return; }

        html += '<div style="margin-bottom:12px;color:#5a6580;font-size:13px;">' + totalRec.toLocaleString('fr-FR') + ' records au total — Page ' + pg + '/' + totalPages + '</div>';
        var thEpRec = '<tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>NAT</th><th>Club</th><th>Performance</th><th>Niveaux</th><th>Date</th><th></th></tr>';
        html += '<div class="table-wrap">';
        html += '<table class="bk-table">' + thEpRec + '</table>';
        html += '<table class="bk-table">';
        rec.forEach(function(r, i) {
            var inB = r.athlete_id ? isAthleteInBasket(r.athlete_id) : false;
            html += '<tr>';
            html += '<td>' + ((pg - 1) * 50 + i + 1) + '</td>';
            html += '<td><b>' + (r.athlete_id ? '<a href="?page=profil&id=' + r.athlete_id + '&s=records" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(r.athlete) + '</a>' : escapeHtml(r.athlete)) + '</b></td>';
            var _epN = window._ctxEpreuveName || '';
            html += '<td><a href="?page=recherche&categorie=' + encodeURIComponent(r.categorie||'') + '&epreuve=' + encodeURIComponent(_epN) + '" style="text-decoration:none;"><span class="badge badge-cat">' + escapeHtml(r.categorie || '') + '</span></a></td>';
            html += '<td><a href="?page=recherche&sexe=' + encodeURIComponent(r.sexe||'') + '&epreuve=' + encodeURIComponent(_epN) + '" style="text-decoration:none;"><span class="badge badge-' + (r.sexe||'').toLowerCase() + '">' + escapeHtml(r.sexe || '') + '</span></a></td>';
            html += '<td><a href="?page=recherche&nationalite=' + encodeURIComponent(r.nationalite||'') + '&epreuve=' + encodeURIComponent(_epN) + '" style="color:#c9d1d9;text-decoration:none;">' + escapeHtml(r.nationalite || '') + '</a></td>';
            html += '<td>' + (r.club ? '<a href="?page=clubs&open=' + encodeURIComponent(r.club) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(r.club) + '</a>' : '-') + '</td>';
            html += '<td><span class="perf-val">' + escapeHtml(r.performance || '-') + '</span></td>';
            html += '<td>' + _nivBadge(_highestNiveau(r.niveaux || [])) + '</td>';
            html += '<td>' + dateFR(r.date || '-') + '</td>';
            html += '<td>' + (r.athlete_id ? '<button class="btn-cmp-add' + (inB ? ' added' : '') + '" data-cmp-ath="' + r.athlete_id + '" data-name="' + escapeHtml(r.athlete) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">' + (inB ? '\u2713' : '+') + '</button>' : '') + '</td>';
            html += '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thEpRec + '</table>';
        html += '</div>';
        // Pagination
        if (totalPages > 1) {
            html += '<div class="pager" style="margin-top:12px;">';
            if (pg > 1) html += '<a href="#" onclick="loadEpreuveRecPage(' + (pg - 1) + ');return false;">Précédent</a> ';
            for (var pi = Math.max(1, pg - 3); pi <= Math.min(totalPages, pg + 3); pi++) {
                if (pi === pg) html += '<span class="current">' + pi + '</span> ';
                else html += '<a href="#" onclick="loadEpreuveRecPage(' + pi + ');return false;">' + pi + '</a> ';
            }
            if (pg < totalPages) html += '<a href="#" onclick="loadEpreuveRecPage(' + (pg + 1) + ');return false;">Suivant</a>';
            html += ' <span class="info">(' + totalPages + ' pages)</span>';
            html += '</div>';
        }

    } else if (tab === 'nationalites') {
        var nat = d.nationalites || {};
        var cats = d.par_categorie || {};
        var keys = Object.keys(nat);
        var catKeys = Object.keys(cats);
        if (keys.length === 0 && catKeys.length === 0) { content.innerHTML = '<div class="loading-msg">Aucune donnée</div>'; return; }
        var totalNat = 0;
        keys.forEach(function(k) { totalNat += nat[k]; });

        html += '<div style="margin-bottom:12px;color:#5a6580;font-size:13px;">' + keys.length + ' nationalités — ' + totalNat.toLocaleString('fr-FR') + ' athlètes</div>';
        // Charts
        html += '<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">';
        html += '<div style="flex:1;min-width:200px;max-width:300px;"><canvas id="epNatDonut"></canvas></div>';
        html += '<div style="flex:2;min-width:300px;"><canvas id="epNatBar"></canvas></div>';
        html += '</div>';
        // Clickable buttons
        html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">';
        keys.forEach(function(k) {
            var pct = totalNat > 0 ? Math.round(nat[k] / totalNat * 100) : 0;
            html += '<a href="?page=recherche&epreuve=' + encodeURIComponent(d.epreuve) + '&nationalite=' + encodeURIComponent(k) + '" style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;background:#0d1525;border:1px solid #1a2540;border-radius:8px;color:#a29bfe;text-decoration:none;font-size:12px;transition:all .2s;" onmouseenter="this.style.borderColor=\'#6c5ce7\';this.style.background=\'#6c5ce715\'" onmouseleave="this.style.borderColor=\'#1a2540\';this.style.background=\'#0d1525\'">' + escapeHtml(k) + ' <span style="color:#8b949e;font-size:11px;">' + nat[k] + ' (' + pct + '%)</span></a>';
        });
        html += '</div>';
        // Table nationalités
        var _natTh = '<tr><th>#</th><th>Nationalité</th><th>Athlètes</th><th>%</th></tr>';
        html += '<div class="table-wrap"><table class="bk-table">' + _natTh + '</table><table class="bk-table">';
        keys.forEach(function(k, i) {
            var pct = totalNat > 0 ? Math.round(nat[k] / totalNat * 100) : 0;
            html += '<tr><td>' + (i+1) + '</td><td><b>' + escapeHtml(k) + '</b></td><td>' + nat[k].toLocaleString('fr-FR') + '</td><td><div style="display:flex;align-items:center;gap:6px;"><div style="width:60px;height:6px;background:#1a2540;border-radius:3px;"><div style="width:' + Math.min(pct,100) + '%;height:100%;background:#a78bfa;border-radius:3px;"></div></div><span style="font-size:12px;">' + pct + '%</span></div></td></tr>';
        });
        html += '</table><table class="bk-table">' + _natTh + '</table></div>';
        // Categories section
        if (catKeys.length > 0) {
            var totalCat = 0;
            catKeys.forEach(function(k) { totalCat += cats[k]; });
            html += '<h4 style="color:#8b949e;font-size:13px;margin:20px 0 8px;">Catégories (' + catKeys.length + ')</h4>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">';
            catKeys.forEach(function(k) {
                var pct = totalCat > 0 ? Math.round(cats[k] / totalCat * 100) : 0;
                html += '<span style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;background:#0d1525;border:1px solid #1a2540;border-radius:8px;color:#34d399;font-size:12px;"><span class="badge badge-cat">' + escapeHtml(k) + '</span> <span style="color:#8b949e;font-size:11px;">' + cats[k] + ' (' + pct + '%)</span></span>';
            });
            html += '</div>';
        }

    } else if (tab === 'stats') {
        var sexe = d.par_sexe || {};
        var cats = d.par_categorie || {};
        var nbAth = d.total_athletes || 0;
        var rpa = d.resultats_par_annee || [];
        var med = d.medailles || {};
        var totalMed = d.total_medailles || 0;
        var medDet = d.medailles_detail || [];
        var pod = d.podiums || {};
        var totalPod = d.total_podiums || 0;
        var sel = d.selections || {};
        var prog = d.progressions || {};
        var niv = d.niveaux_resultats || [];
        var topClubs = d.top_clubs || [];
        var topVilles = d.top_villes || [];

        // Row 1: Sexe + Categories charts
        html += '<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">';
        html += '<div style="flex:1;min-width:200px;max-width:300px;"><h4 style="color:#8b949e;font-size:13px;margin:0 0 8px;">Répartition par sexe</h4><canvas id="epSexeChart"></canvas></div>';
        html += '<div style="flex:2;min-width:300px;"><h4 style="color:#8b949e;font-size:13px;margin:0 0 8px;">Catégories</h4><canvas id="epCatChart"></canvas></div>';
        html += '</div>';

        // Row 2: Medailles + Podiums cards
        if (totalMed > 0 || totalPod > 0) {
            html += '<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">';
            if (totalMed > 0) {
                var pctOr = totalMed > 0 ? Math.round((med.or||0)/totalMed*100) : 0;
                var pctAg = totalMed > 0 ? Math.round((med.argent||0)/totalMed*100) : 0;
                var pctBr = totalMed > 0 ? Math.round((med.bronze||0)/totalMed*100) : 0;
                html += '<div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#f59e0b10;border:1px solid #f59e0b30;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#fbbf24;">' + (med.or||0) + '</div><div style="font-size:11px;color:#8b949e;">Or (' + pctOr + '%)</div></div>';
                html += '<div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#94a3b810;border:1px solid #94a3b830;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#94a3b8;">' + (med.argent||0) + '</div><div style="font-size:11px;color:#8b949e;">Argent (' + pctAg + '%)</div></div>';
                html += '<div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#b4540010;border:1px solid #b4540030;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#cd7f32;">' + (med.bronze||0) + '</div><div style="font-size:11px;color:#8b949e;">Bronze (' + pctBr + '%)</div></div>';
            }
            if (totalPod > 0) {
                html += '<div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#10b98110;border:1px solid #10b98130;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#34d399;">' + totalPod + '</div><div style="font-size:11px;color:#8b949e;">Podiums</div></div>';
            }
            html += '</div>';
        }

        // Medailles detail table
        if (medDet.length > 0) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:0 0 8px;">Dernières médailles</h4>';
            var _medTh = '<tr><th>Médaille</th><th>Athlète</th><th>Compétition</th><th>Lieu</th><th>Année</th></tr>';
            html += '<div class="table-wrap"><table class="bk-table">' + _medTh + '</table><table class="bk-table">';
            medDet.forEach(function(m) {
                var ico = m.type === 'or' ? '\uD83E\uDD47' : (m.type === 'argent' ? '\uD83E\uDD48' : '\uD83E\uDD49');
                html += '<tr><td>' + ico + ' ' + escapeHtml(m.type) + '</td>';
                html += '<td><b>' + (m.athlete_id ? '<a href="?page=profil&id=' + m.athlete_id + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(m.athlete) + '</a>' : escapeHtml(m.athlete)) + '</b></td>';
                html += '<td>' + (m.competition ? '<a href="?page=recherche&competition=' + encodeURIComponent(m.competition) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(m.competition) + '</a>' : '-') + '</td>';
                html += '<td>' + (m.lieu ? '<a href="?page=villes&open=' + encodeURIComponent(m.lieu) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(m.lieu) + '</a>' : '-') + '</td>';
                html += '<td>' + (m.annee || '-') + '</td></tr>';
            });
            html += '</table><table class="bk-table">' + _medTh + '</table></div>';
        }

        // Podiums detail
        if (totalPod > 0) {
            html += '<div style="display:flex;gap:12px;margin:16px 0;flex-wrap:wrap;">';
            html += '<div style="padding:10px 16px;background:#fbbf2415;border:1px solid #fbbf2430;border-radius:8px;color:#fbbf24;font-size:13px;font-weight:600;">1er: ' + (pod['1er']||0) + '</div>';
            html += '<div style="padding:10px 16px;background:#94a3b815;border:1px solid #94a3b830;border-radius:8px;color:#94a3b8;font-size:13px;font-weight:600;">2e: ' + (pod['2e']||0) + '</div>';
            html += '<div style="padding:10px 16px;background:#cd7f3215;border:1px solid #cd7f3230;border-radius:8px;color:#cd7f32;font-size:13px;font-weight:600;">3e: ' + (pod['3e']||0) + '</div>';
            html += '</div>';
        }

        // Selections
        if (sel.nb_selections > 0) {
            html += '<div style="margin-bottom:16px;padding:12px;background:#6366f110;border:1px solid #6366f130;border-radius:10px;display:flex;gap:20px;flex-wrap:wrap;">';
            html += '<div><span style="font-size:20px;font-weight:700;color:#818cf8;">' + sel.nb_athletes + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">athlètes sélectionnés</span></div>';
            html += '<div><span style="font-size:20px;font-weight:700;color:#818cf8;">' + sel.nb_selections + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">sélections nationales</span></div>';
            html += '</div>';
        }

        // Progressions
        if (prog.nb_progressions > 0) {
            html += '<div style="margin-bottom:16px;padding:12px;background:#f9731610;border:1px solid #f9731630;border-radius:10px;display:flex;gap:20px;flex-wrap:wrap;">';
            html += '<div><span style="font-size:20px;font-weight:700;color:#fb923c;">' + prog.nb_athletes + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">athlètes avec progression</span></div>';
            html += '<div><span style="font-size:20px;font-weight:700;color:#fb923c;">' + prog.nb_progressions + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">progressions enregistrées</span></div>';
            html += '</div>';
        }

        // Niveaux de competition
        if (niv.length > 0) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Niveaux de compétition</h4>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">';
            niv.forEach(function(n) {
                html += '<div style="display:flex;align-items:center;gap:6px;">' + _nivBadge(n.niveau) + '<span style="color:#8b949e;font-size:12px;">' + n.count.toLocaleString('fr-FR') + '</span></div>';
            });
            html += '</div>';
        }

        // Top clubs
        if (topClubs.length > 0) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Principaux clubs</h4>';
            var _tcTh = '<tr><th>#</th><th>Club</th><th>Athlètes</th><th>Records</th></tr>';
            html += '<div class="table-wrap"><table class="bk-table">' + _tcTh + '</table><table class="bk-table">';
            topClubs.forEach(function(c, i) {
                html += '<tr><td>' + (i+1) + '</td><td><a href="#" onclick="openClubDetail(null,\'' + escapeHtml(c.club).replace(/'/g,"\\'") + '\');return false;" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(c.club) + '</a></td><td>' + c.nb_athletes + '</td><td>' + c.nb_records + '</td></tr>';
            });
            html += '</table><table class="bk-table">' + _tcTh + '</table></div>';
        }

        // Top villes
        if (topVilles.length > 0) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Principaux lieux de compétition</h4>';
            var _tveTh = '<tr><th>#</th><th>Ville</th><th>Records</th><th>Athlètes</th></tr>';
            html += '<div class="table-wrap"><table class="bk-table">' + _tveTh + '</table><table class="bk-table">';
            topVilles.forEach(function(v, i) {
                html += '<tr><td>' + (i+1) + '</td><td><a href="?page=villes&open=' + encodeURIComponent(v.ville) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(v.ville) + '</a></td><td>' + v.nb_records + '</td><td>' + v.nb_athletes + '</td></tr>';
            });
            html += '</table><table class="bk-table">' + _tveTh + '</table></div>';
        }

        // Evolution par annee
        if (rpa.length > 1) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Évolution par année</h4>';
            html += '<canvas id="epEvoChart" style="max-height:250px;"></canvas>';
        }

    } else if (tab === 'resume') {
        html += _buildEpreuveResumeHTML(d);
    }

    content.innerHTML = html;

    // Post-render charts for nationalites
    if (tab === 'nationalites') {
        var _nat = d.nationalites || {};
        var _nk = Object.keys(_nat);
        var _totalN = 0;
        _nk.forEach(function(k) { _totalN += _nat[k]; });
        var _colors = ['#3b82f6','#ec4899','#8b5cf6','#f59e0b','#10b981','#ef4444','#06b6d4','#f97316','#84cc16','#6366f1','#64748b'];
        var _dc = document.getElementById('epNatDonut');
        if (_dc && _nk.length > 0) {
            var _top10 = _nk.slice(0, 10);
            var _otherC = 0; _nk.slice(10).forEach(function(k) { _otherC += _nat[k]; });
            var _lbl = _top10.map(function(k) { return k; });
            var _dt = _top10.map(function(k) { return _nat[k]; });
            if (_otherC > 0) { _lbl.push('Autres'); _dt.push(_otherC); }
            new Chart(_dc, {
                type: 'doughnut',
                data: { labels: _lbl, datasets: [{ data: _dt, backgroundColor: _colors.slice(0, _lbl.length), borderWidth: 0 }] },
                options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, font: { size: 10 }, color: '#8b949e' } } } }
            });
        }
        var _bc = document.getElementById('epNatBar');
        if (_bc && _nk.length > 0) {
            var _top15 = _nk.slice(0, 15);
            new Chart(_bc, {
                type: 'bar',
                data: { labels: _top15, datasets: [{ data: _top15.map(function(k) { return _nat[k]; }), backgroundColor: '#a78bfa', borderRadius: 4, barThickness: 16 }] },
                options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { display: false }, ticks: { color: '#c8cfd8' } } } }
            });
        }
    }
    // Post-render charts for stats
    if (tab === 'stats') {
        var _sexe = d.par_sexe || {};
        var _cats = d.par_categorie || {};
        var _rpa = (d.resultats_par_annee || []).slice().reverse();
        var _sc = document.getElementById('epSexeChart');
        if (_sc) {
            var _sk = Object.keys(_sexe);
            new Chart(_sc, {
                type: 'doughnut',
                data: { labels: _sk.map(function(k){return k==='M'?'Hommes':(k==='F'?'Femmes':k);}), datasets: [{ data: _sk.map(function(k){return _sexe[k];}), backgroundColor: ['#3b82f6','#ec4899','#64748b'], borderWidth: 0 }] },
                options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, font: { size: 10 }, color: '#8b949e' } } } }
            });
        }
        var _cc = document.getElementById('epCatChart');
        if (_cc) {
            var _ck = Object.keys(_cats).slice(0, 12);
            new Chart(_cc, {
                type: 'bar',
                data: { labels: _ck, datasets: [{ data: _ck.map(function(k){return _cats[k];}), backgroundColor: '#34d399', borderRadius: 4, barThickness: 16 }] },
                options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { display: false }, ticks: { color: '#c8cfd8' } } } }
            });
        }
        var _ec = document.getElementById('epEvoChart');
        if (_ec && _rpa.length > 1) {
            new Chart(_ec, {
                type: 'line',
                data: {
                    labels: _rpa.map(function(r){return r.annee;}),
                    datasets: [
                        { label: 'Résultats', data: _rpa.map(function(r){return r.nb_resultats;}), borderColor: '#6366f1', backgroundColor: '#6366f120', fill: true, tension: 0.3, pointRadius: 3 },
                        { label: 'Athlètes', data: _rpa.map(function(r){return r.nb_athletes;}), borderColor: '#34d399', backgroundColor: '#34d39920', fill: true, tension: 0.3, pointRadius: 3 }
                    ]
                },
                options: { responsive: true, plugins: { legend: { position: 'top', labels: { usePointStyle: true, font: { size: 10 }, color: '#8b949e' } } }, scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } } }, interaction: { intersect: false, mode: 'index' } }
            });
        }
    }
}

// Resume auto-genere pour une epreuve
function _buildEpreuveResumeHTML(d) {
    var txt = [];
    var nom = d.epreuve;
    var nbAth = d.total_athletes || 0;
    var nbRec = d.total_records || 0;
    var sexe = d.par_sexe || {};
    var cats = d.par_categorie || {};
    var nats = d.nationalites || {};
    var med = d.medailles || {};
    var totalMed = d.total_medailles || 0;
    var medDet = d.medailles_detail || [];
    var pod = d.podiums || {};
    var totalPod = d.total_podiums || 0;
    var sel = d.selections || {};
    var prog = d.progressions || {};
    var niv = d.niveaux_resultats || [];
    var topClubs = d.top_clubs || [];
    var topVilles = d.top_villes || [];
    var rpa = d.resultats_par_annee || [];

    // P1: Introduction
    var p1 = 'L\'épreuve ' + nom + ' regroupe ' + nbAth.toLocaleString('fr-FR') + ' athlète' + (nbAth > 1 ? 's' : '') + ' pour un total de ' + nbRec.toLocaleString('fr-FR') + ' record' + (nbRec > 1 ? 's' : '') + ' enregistré' + (nbRec > 1 ? 's' : '') + '.';
    if (d.annee_debut && d.annee_fin) p1 += ' Les données couvrent la période de ' + d.annee_debut + ' à ' + d.annee_fin + '.';
    txt.push(p1);

    // P2: Sexe
    var sk = Object.keys(sexe);
    if (sk.length > 0) {
        var totalS = 0; sk.forEach(function(k) { totalS += sexe[k]; });
        var parts = sk.map(function(k) {
            var pct = totalS > 0 ? Math.round(sexe[k] / totalS * 100) : 0;
            var label = k === 'M' ? 'hommes' : (k === 'F' ? 'femmes' : k);
            return sexe[k].toLocaleString('fr-FR') + ' ' + label + ' (' + pct + '%)';
        });
        txt.push('La répartition par sexe comprend ' + parts.join(', ') + '.');
    }

    // P3: Categories
    var ck = Object.keys(cats);
    if (ck.length > 0) {
        var totalC = 0; ck.forEach(function(k) { totalC += cats[k]; });
        var topCats = ck.slice(0, 5).map(function(k) {
            var pct = totalC > 0 ? Math.round(cats[k] / totalC * 100) : 0;
            return k + ' (' + pct + '%)';
        });
        txt.push('Les catégories les plus représentées sont ' + topCats.join(', ') + '.');
    }

    // P4: Nationalites
    var nk = Object.keys(nats);
    if (nk.length > 0) {
        var totalN = 0; nk.forEach(function(k) { totalN += nats[k]; });
        var topN = nk.slice(0, 5).map(function(k) {
            var pct = totalN > 0 ? Math.round(nats[k] / totalN * 100) : 0;
            return k + ' (' + pct + '%)';
        });
        txt.push(nk.length + ' nationalité' + (nk.length > 1 ? 's' : '') + ' sont représentées. Les principales : ' + topN.join(', ') + '.');
    }

    // P5: Medailles
    if (totalMed > 0) {
        var p5 = totalMed + ' médaille' + (totalMed > 1 ? 's' : '') + ' ont été décernées dans cette épreuve : ';
        var mp = [];
        if (med.or > 0) mp.push(med.or + ' or');
        if (med.argent > 0) mp.push(med.argent + ' argent');
        if (med.bronze > 0) mp.push(med.bronze + ' bronze');
        p5 += mp.join(', ') + '.';
        if (medDet.length > 0) {
            p5 += ' Dernière médaille : ' + medDet[0].athlete + ' (' + medDet[0].type + (medDet[0].annee ? ', ' + medDet[0].annee : '') + (medDet[0].competition ? ', ' + medDet[0].competition : '') + ').';
        }
        txt.push(p5);
    }

    // P6: Podiums
    if (totalPod > 0) {
        var p6 = totalPod + ' podium' + (totalPod > 1 ? 's' : '') + ' enregistré' + (totalPod > 1 ? 's' : '') + ' : ';
        var pp = [];
        if (pod['1er'] > 0) pp.push(pod['1er'] + ' première' + (pod['1er'] > 1 ? 's' : '') + ' place' + (pod['1er'] > 1 ? 's' : ''));
        if (pod['2e'] > 0) pp.push(pod['2e'] + ' deuxième' + (pod['2e'] > 1 ? 's' : '') + ' place' + (pod['2e'] > 1 ? 's' : ''));
        if (pod['3e'] > 0) pp.push(pod['3e'] + ' troisième' + (pod['3e'] > 1 ? 's' : '') + ' place' + (pod['3e'] > 1 ? 's' : ''));
        p6 += pp.join(', ') + '.';
        txt.push(p6);
    }

    // P7: Selections
    if (sel.nb_selections > 0) {
        txt.push(sel.nb_athletes + ' athlète' + (sel.nb_athletes > 1 ? 's' : '') + ' ont été sélectionné' + (sel.nb_athletes > 1 ? 's' : '') + ' en équipe nationale pour cette épreuve, totalisant ' + sel.nb_selections + ' sélection' + (sel.nb_selections > 1 ? 's' : '') + '.');
    }

    // P8: Niveaux
    if (niv.length > 0) {
        var nivParts = niv.slice(0, 5).map(function(n) { return n.niveau + ' (' + n.count + ')'; });
        txt.push('Les niveaux de compétition incluent : ' + nivParts.join(', ') + '.');
    }

    // P9: Top clubs
    if (topClubs.length > 0) {
        var tcParts = topClubs.slice(0, 5).map(function(c) { return c.club + ' (' + c.nb_athletes + ' athlètes)'; });
        txt.push('Les principaux clubs pratiquant cette épreuve sont ' + tcParts.join(', ') + '.');
    }

    // P10: Top villes
    if (topVilles.length > 0) {
        var tvParts = topVilles.slice(0, 5).map(function(v) { return v.ville + ' (' + v.nb_records + ' records)'; });
        txt.push('Les compétitions ont principalement eu lieu à ' + tvParts.join(', ') + '.');
    }

    // P11: Progressions
    if (prog.nb_progressions > 0) {
        txt.push(prog.nb_athletes + ' athlète' + (prog.nb_athletes > 1 ? 's' : '') + ' ont enregistré des progressions dans cette épreuve, pour un total de ' + prog.nb_progressions + ' progression' + (prog.nb_progressions > 1 ? 's' : '') + '.');
    }

    // P12: Evolution
    if (rpa.length > 1) {
        var first = rpa[rpa.length - 1];
        var last = rpa[0];
        txt.push('L\'activité dans cette épreuve s\'étend de ' + first.annee + ' à ' + last.annee + '. En ' + last.annee + ', ' + last.nb_resultats + ' résultat' + (last.nb_resultats > 1 ? 's' : '') + ' ont été enregistrés par ' + last.nb_athletes + ' athlète' + (last.nb_athletes > 1 ? 's' : '') + '.');
    }

    // Build HTML with copy button
    var resumeHtml = '<div style="margin-bottom:12px;display:flex;justify-content:flex-end;">';
    resumeHtml += '<button onclick="copyEpreuveResume()" style="padding:6px 14px;background:#6c5ce720;border:1px solid #6c5ce740;border-radius:8px;color:#a29bfe;font-size:12px;cursor:pointer;">Copier le texte</button>';
    resumeHtml += '</div>';
    resumeHtml += '<div id="epreuveResumeText" style="line-height:1.8;color:#c8cfd8;font-size:14px;">';
    txt.forEach(function(p) {
        resumeHtml += '<p style="margin-bottom:12px;">' + escapeHtml(p) + '</p>';
    });
    resumeHtml += '</div>';
    return resumeHtml;
}
function copyEpreuveResume() {
    var el = document.getElementById('epreuveResumeText');
    if (el) {
        navigator.clipboard.writeText(el.innerText).then(function() {
            var btn = el.parentElement.querySelector('button');
            if (btn) { btn.textContent = 'Copié !'; setTimeout(function() { btn.textContent = 'Copier le texte'; }, 2000); }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    applyIgnoredClubs();
    renderIgnoredPanel();
});

// --- Search tracking helper ---
function _trackSearch(params) {
    try {
        navigator.sendBeacon(BASE_API + '/search_track.php', JSON.stringify(params));
    } catch(e) {}
}
var _trackTimer = null;

function liveSearch(inputId, statusId, resultsId, paginatedId, config) {
    const input = document.getElementById(inputId);
    const status = document.getElementById(statusId);
    const results = document.getElementById(resultsId);
    const paginated = document.getElementById(paginatedId);
    if (!input) return;

    let timer = null;
    let controller = null;

    input.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(timer);
        clearTimeout(_trackTimer);

        var minLen = config.minLength || 2;
        if (q.length < minLen) {
            input.style.borderColor = '#1a2540';
            input.classList.remove('ls-loading');
            results.style.display = 'none';
            results.innerHTML = '';
            paginated.style.display = 'block';
            status.textContent = q.length > 0 && q.length < minLen ? 'Tapez au moins ' + minLen + ' caractère' + (minLen > 1 ? 's' : '') + '...' : '';
            status.className = 'ls-status';
            return;
        }

        input.style.borderColor = '#a29bfe';
        input.classList.add('ls-loading');
        status.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;"><span class="ls-spinner"></span> Recherche en cours...</span>';
        status.className = 'ls-status loading';

        timer = setTimeout(async () => {
            if (controller) controller.abort();
            controller = new AbortController();

            try {
                const url = config.url(q);
                const resp = await fetch(url, { signal: controller.signal });
                const data = await resp.json();

                input.style.borderColor = '#1a2540';
                input.classList.remove('ls-loading');

                if (!data.success) {
                    if (data.limit_reached) {
                        status.innerHTML = '';
                        results.innerHTML = _buildLimitMsg(data);
                        results.style.display = 'block';
                        paginated.style.display = 'none';
                        input.style.borderColor = '#ff7675';
                    } else {
                        status.textContent = data.error || 'Erreur';
                        status.className = 'ls-status error';
                        input.style.borderColor = '#ff7675';
                    }
                    return;
                }

                const items = data[config.key];
                const total = data.total || 0;

                status.innerHTML = '<span style="color:#34d399;">&#10003;</span> ' + total + ' résultat' + (total > 1 ? 's' : '') + (total > 50 ? ' (50 affichés)' : '');
                status.className = 'ls-status';
                input.style.borderColor = '#34d399';
                setTimeout(function() { input.style.borderColor = '#1a2540'; }, 1500);

                paginated.style.display = 'none';

                if (!items || items.length === 0) {
                    results.innerHTML = '<p style="color:#484f58;text-align:center;padding:20px;">Aucun résultat pour "' + escapeHtml(q) + '"</p>';
                    results.style.display = 'block';
                    // Track search with 0 results after 2s settled
                    clearTimeout(_trackTimer);
                    _trackTimer = setTimeout(function() {
                        _trackSearch({ q: q, type: config.trackType || 'general', source: 'live_search', results: 0, pg: config.trackPage || '' });
                    }, 2000);
                    return;
                }

                results.innerHTML = config.render(items, q);
                results.style.display = 'block';

                // Track search after 2s settled (debounce: only the final query)
                clearTimeout(_trackTimer);
                _trackTimer = setTimeout(function() {
                    _trackSearch({ q: q, type: config.trackType || 'general', source: 'live_search', results: total, pg: config.trackPage || '' });
                }, 2000);

            } catch (e) {
                if (e.name === 'AbortError') return;
                input.style.borderColor = '#ff7675';
                input.classList.remove('ls-loading');
                status.textContent = 'Erreur de connexion';
                status.className = 'ls-status error';
            }
        }, 300);
    });
}

function highlight(text, query) {
    if (!text) return '';
    const safe = escapeHtml(text);
    const regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return safe.replace(regex, '<mark style="background:#1f6feb44;color:#58a6ff;">$1</mark>');
}

// --- ATHLETES ---
liveSearch('lsAthletes', 'lsAthletesStatus', 'lsAthletesResults', 'athletesPaginated', {
    url: q => BASE_API + '/search.php?nom=' + encodeURIComponent(q) + '&limit=50',
    key: 'athletes',
    trackType: 'athlete',
    trackPage: 'athletes',
    render: (items, q) => {
        var thAth = '<tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>NAT</th><th>Niveaux</th><th>Records (top 5)</th><th></th><th></th></tr>';
        let html = '<div class="table-wrap">';
        html += '<table class="bk-table">' + thAth + '</table>';
        html += '<table class="bk-table">';
        var _num = 0;
        items.forEach(a => {
            _num++;
            var inBasket = isAthleteInBasket(a.athlete_id);
            var nbRec = a.nb_records || 0;
            var topRecs = a.top_records || [];
            var recHtml = '';
            if (topRecs.length > 0) {
                topRecs.forEach(function(tr) {
                    recHtml += '<div style="font-size:11px;line-height:1.6;"><a href="?page=recherche&epreuve=' + encodeURIComponent(tr.epreuve) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(tr.epreuve) + '</a> <span class="perf-val" style="font-size:11px;">' + escapeHtml(tr.performance) + '</span> ' + _nivBadge(tr.top_niveau || _highestNiveau(tr.niveaux || [])) + '</div>';
                });
            } else if (nbRec > 0) {
                recHtml = '<span class="badge badge-perf">' + nbRec + '</span>';
            } else {
                recHtml = '-';
            }
            html += '<tr>'
                + '<td>' + _num + '</td>'
                + '<td><b><a href="?page=profil&id=' + a.athlete_id + '">' + highlight(a.nom_complet, q) + '</a></b>'
                + (a.date_naissance ? '<br><span style="font-size:11px;color:#5a6580;">' + a.date_naissance.substring(0,4) + '</span>' : '')
                + '</td>'
                + '<td><span class="badge badge-cat">' + escapeHtml(a.categorie) + '</span></td>'
                + '<td><span class="badge badge-' + (a.sexe||'').toLowerCase() + '">' + escapeHtml(a.sexe) + '</span></td>'
                + '<td>' + escapeHtml(a.nationalite) + '</td>'
                + '<td>' + _nivBadge(_highestNiveau(a.niveaux || [])) + '</td>'
                + '<td>' + recHtml + '</td>'
                + '<td><a href="?page=profil&id=' + a.athlete_id + '&s=records" style="font-size:12px;">Profil</a></td>'
                + '<td><button class="btn-cmp-add' + (inBasket ? ' added' : '') + '" data-cmp-ath="' + a.athlete_id + '" data-name="' + escapeHtml(a.nom_complet) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">' + (inBasket ? '\u2713' : '+') + '</button></td>'
                + '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thAth + '</table>';
        html += '</div>';
        return html;
    }
});

// --- RECHERCHE ---
var _rchExtraParams = <?php
    $rchExtra = [];
    foreach (["club","sexe","categorie","nationalite","epreuve"] as $_k) {
        if (!empty($_GET[$_k])) $rchExtra[] = $_k . "=" . urlencode($_GET[$_k]);
    }
    echo json_encode(implode("&", $rchExtra));
?>;
liveSearch('lsRecherche', 'lsRechercheStatus', 'lsRechercheResults', 'recherchePaginated', {
    url: q => BASE_API + '/search.php?nom=' + encodeURIComponent(q) + '&limit=50' + (_rchExtraParams ? '&' + _rchExtraParams : ''),
    minLength: _rchExtraParams ? 1 : 2,
    key: 'athletes',
    trackType: 'athlete',
    trackPage: 'recherche',
    render: (items, q) => {
        var thAth2 = '<tr><th>#</th><th>Nom complet</th><th>Naissance</th><th>Cat</th><th>Sexe</th><th>NAT</th><th>Niveaux</th><th>Records</th><th></th><th></th></tr>';
        let html = '<div class="table-wrap">';
        html += '<table class="bk-table">' + thAth2 + '</table>';
        html += '<table class="bk-table">';
        var _num = 0;
        items.forEach(a => {
            _num++;
            var inBasket = isAthleteInBasket(a.athlete_id);
            var nbRec = a.nb_records || 0;
            html += '<tr>'
                + '<td>' + _num + '</td>'
                + '<td><b><a href="?page=profil&id=' + a.athlete_id + '">' + highlight(a.nom_complet, q) + '</a></b></td>'
                + '<td>' + (a.date_naissance ? a.date_naissance.substring(0,4) : '-') + '</td>'
                + '<td><span class="badge badge-cat">' + escapeHtml(a.categorie) + '</span></td>'
                + '<td><span class="badge badge-' + (a.sexe||'').toLowerCase() + '">' + escapeHtml(a.sexe) + '</span></td>'
                + '<td>' + escapeHtml(a.nationalite) + '</td>'
                + '<td>' + _nivBadge(_highestNiveau(a.niveaux || [])) + '</td>'
                + '<td>' + (nbRec > 0 ? '<span class="badge badge-perf">' + nbRec + '</span>' : '-') + '</td>'
                + '<td><a href="?page=profil&id=' + a.athlete_id + '&s=records">Profil</a></td>'
                + '<td><button class="btn-cmp-add' + (inBasket ? ' added' : '') + '" data-cmp-ath="' + a.athlete_id + '" data-name="' + escapeHtml(a.nom_complet) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">' + (inBasket ? '\u2713' : '+') + '</button></td>'
                + '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thAth2 + '</table>';
        html += '</div>';
        return html;
    }
});

// --- CLUBS ---
liveSearch('lsClubs', 'lsClubsStatus', 'lsClubsResults', 'clubsPaginated', {
    url: q => BASE_API + '/clubs.php?has_athletes=1&max_athletes=5000&nom=' + encodeURIComponent(q) + '&limit=50',
    key: 'clubs',
    trackType: 'club',
    trackPage: 'clubs',
    render: (items, q) => {
        var thClub = '<tr><th>#</th><th>Club</th><th>Athlètes</th><th></th><th></th><th></th></tr>';
        let html = '<div class="table-wrap">';
        html += '<table class="bk-table">' + thClub + '</table>';
        html += '<table class="bk-table">';
        var num = 0;
        items.forEach((c, i) => {
            if (isClubIgnoredById(c.id_club)) return;
            num++;
            var inBasket = isClubInBasket(c.id_club);
            html += '<tr data-club-name="' + escapeHtml(c.nom_club) + '">'
                + '<td>' + num + '</td>'
                + '<td><b><a href="#clubDetailPanel" onclick="openClubDetail(' + c.id_club + ');return false;" style="color:#a29bfe;text-decoration:none;cursor:pointer;">' + highlight(c.nom_club, q) + '</a></b></td>'
                + '<td>' + c.nb_athletes + '</td>'
                + '<td><a href="?page=recherche&club=' + encodeURIComponent(c.nom_club) + '">Voir athletes</a></td>'
                + '<td><button class="btn-cmp-add btn-cmp-add-club' + (inBasket ? ' added' : '') + '" data-cmp-club="' + c.id_club + '" data-name="' + escapeHtml(c.nom_club) + '" onclick="toggleClubBasket(this,parseInt(this.dataset.cmpClub),this.dataset.name)">' + (inBasket ? '\u2713' : '+') + '</button></td>'
                + '<td><button class="btn-cmp-ignore" data-ignore-club="' + c.id_club + '" data-name="' + escapeHtml(c.nom_club) + '" onclick="toggleIgnoreClub(this,parseInt(this.dataset.ignoreClub),this.dataset.name)" title="Ignorer ce club">&#8856;</button></td>'
                + '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thClub + '</table>';
        html += '</div>';
        return html;
    }
});

// --- EPREUVES ---
liveSearch('lsEpreuves', 'lsEpreuvesStatus', 'lsEpreuvesResults', 'epreuvesPaginated', {
    url: q => BASE_API + '/epreuves.php?has_athletes=1&nom=' + encodeURIComponent(q) + '&limit=50',
    key: 'epreuves',
    trackType: 'epreuve',
    trackPage: 'epreuves',
    render: (items, q) => {
        var thEpLs = '<tr><th>#</th><th>Épreuve</th><th>Athlètes avec record</th></tr>';
        let html = '<div class="table-wrap">';
        html += '<table class="bk-table">' + thEpLs + '</table>';
        html += '<table class="bk-table">';
        items.forEach((e, i) => {
            html += '<tr>'
                + '<td>' + (i + 1) + '</td>'
                + '<td><b><a href="?page=recherche&epreuve=' + encodeURIComponent(e.nom_epreuve) + '" style="color:#a29bfe;text-decoration:none;cursor:pointer;">' + highlight(e.nom_epreuve, q) + '</a></b></td>'
                + '<td>' + e.nb_athletes + '</td>'
                + '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thEpLs + '</table>';
        html += '</div>';
        return html;
    }
});

// --- VILLES ---
liveSearch('lsVilles', 'lsVillesStatus', 'lsVillesResults', 'villesPaginated', {
    url: q => BASE_API + '/villes.php?has_athletes=1&nom=' + encodeURIComponent(q) + '&limit=50',
    key: 'villes',
    trackType: 'ville',
    trackPage: 'villes',
    render: (items, q) => {
        var thVille = '<tr><th>#</th><th>Ville</th><th>Athlètes</th><th>Période</th><th>Top 3 niveaux</th></tr>';
        let html = '<div class="table-wrap">';
        html += '<table class="bk-table">' + thVille + '</table>';
        html += '<table class="bk-table">';
        items.forEach((v, i) => {
            var periode = v.annee_debut ? v.annee_debut + ' - ' + (v.annee_fin || '...') : '-';
            var niveaux = '-';
            if (v.top_niveaux && v.top_niveaux.length > 0) {
                niveaux = v.top_niveaux.map(n => {
                    var fc = (n.niveau||'')[0] || '';
                    var bg, bc, tc;
                    if (fc === 'N') { bg='#e11d4820'; bc='#e11d48'; tc='#fb7185'; }
                    else if (fc === 'I') { bg='#c026d320'; bc='#c026d3'; tc='#e879f9'; }
                    else if (fc === 'R') { bg='#0891b220'; bc='#0891b2'; tc='#22d3ee'; }
                    else { bg='#f9731620'; bc='#f97316'; tc='#fb923c'; }
                    return '<span style="display:inline-block;margin:1px 2px;padding:2px 8px;border-radius:6px;font-size:11px;background:'+bg+';border:1px solid '+bc+'40;color:'+tc+';">' + escapeHtml(n.niveau) + ' <b>' + n.pct + '%</b></span>';
                }).join('');
            }
            html += '<tr>'
                + '<td>' + (i + 1) + '</td>'
                + '<td><b><a href="?page=villes&open=' + encodeURIComponent(v.nom_ville) + '" style="color:#a29bfe;text-decoration:none;cursor:pointer;">' + highlight(v.nom_ville, q) + '</a></b></td>'
                + '<td>' + v.nb_athletes + '</td>'
                + '<td>' + periode + '</td>'
                + '<td>' + niveaux + '</td>'
                + '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thVille + '</table>';
        html += '</div>';
        return html;
    }
});

// --- VILLES (page dediee, pas de JS detail) ---

// ================================================================
//  COMPARATEUR D'ATHLETES - JS
// ================================================================
var cmpAthletes = []; // [{id, name, data}]
var cmpChart = null;
var cmpRadarChart = null;
var cmpMedChart = null;
var cmpColors = ['#3b82f6','#ec4899','#10b981','#f59e0b','#8b5cf6','#06b6d4','#ef4444','#84cc16'];
var cmpDebounce = null;

// Recherche athletes pour comparateur
(function() {
    var input = document.getElementById('cmpSearch');
    if (!input) return;
    var box = document.getElementById('cmpSuggestions');

    input.addEventListener('input', function() {
        clearTimeout(cmpDebounce);
        var q = this.value.trim();
        if (q.length < 2) { box.style.display = 'none'; return; }
        cmpDebounce = setTimeout(function() {
            fetch(BASE_API + '/search.php?nom=' + encodeURIComponent(q) + '&limit=8')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    box.innerHTML = '';
                    if (!data.success || !data.athletes || data.athletes.length === 0) { box.style.display = 'none'; return; }
                    data.athletes.forEach(function(a) {
                        // Ne pas ajouter si deja dans la liste
                        if (cmpAthletes.find(function(x) { return x.id === a.athlete_id; })) return;
                        var d = document.createElement('div');
                        d.textContent = a.nom_complet + ' (' + (a.categorie || '?') + ' / ' + (a.sexe || '?') + ')';
                        d.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:14px;border-bottom:1px solid #1e2a3a;color:#c9d1d9;';
                        d.onmouseover = function() { d.style.background = '#161b22'; };
                        d.onmouseout = function() { d.style.background = 'transparent'; };
                        d.onclick = function() { addAthleteToCompare(a.athlete_id, a.nom_complet); };
                        box.appendChild(d);
                    });
                    box.style.display = 'block';
                });
        }, 300);
    });

    // Fermer suggestions au clic dehors
    document.addEventListener('click', function(e) {
        if (e.target !== input && e.target !== box) box.style.display = 'none';
    });
})();

function addAthleteToCompare(id, name) {
    if (cmpAthletes.find(function(x) { return x.id === id; })) return;
    if (cmpAthletes.length >= 6) { alert('Maximum 6 athlètes'); return; }

    var searchEl = document.getElementById('cmpSearch');
    var suggestEl = document.getElementById('cmpSuggestions');
    if (searchEl) searchEl.value = '';
    if (suggestEl) suggestEl.style.display = 'none';

    var color = cmpColors[cmpAthletes.length % cmpColors.length];
    cmpAthletes.push({ id: id, name: name, data: null, color: color });
    addAthleteToBasket(id, name);
    updateAllCmpButtons();
    renderSelectedAthletes();

    // Charger les donnees de l'athlete
    fetch(BASE_API + '/athlete.php?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            var ath = cmpAthletes.find(function(x) { return x.id === id; });
            if (ath) ath.data = data;
            updateEpreuveList();
            renderSelectedAthletes();
            // Auto-compare si chargement depuis URL
            if (window._cmpAutoExpected && window._cmpAutoExpected > 0) {
                var ready = cmpAthletes.filter(function(a) { return a.data; });
                if (ready.length >= window._cmpAutoExpected && ready.length >= 2) {
                    window._cmpAutoExpected = 0;
                    setTimeout(function() {
                        // Description SEO
                        var descEl = document.getElementById('cmpDescription');
                        var names = cmpAthletes.map(function(a) { return a.name; });
                        if (descEl && names.length >= 2) {
                            descEl.style.display = 'block';
                            descEl.textContent = 'Comparaison entre ' + names.slice(0, -1).join(', ') + ' et ' + names[names.length - 1] + ' — records personnels, progressions, médailles et statistiques.';
                        }
                        // Pre-selectionner la 1ere epreuve commune
                        var sel = document.getElementById('cmpEpreuve');
                        if (sel && sel.options.length > 1) sel.selectedIndex = 1;
                        compareNow();
                    }, 300);
                }
            }
        });
}

function removeFromCompare(id) {
    cmpAthletes = cmpAthletes.filter(function(x) { return x.id !== id; });
    removeAthleteFromBasket(id);
    updateAllCmpButtons();
    renderSelectedAthletes();
    updateEpreuveList();
    if (cmpAthletes.length < 2) {
        document.getElementById('cmpChartArea').style.display = 'none';
    }
}

function renderSelectedAthletes() {
    var container = document.getElementById('cmpSelected');
    container.innerHTML = '';
    cmpAthletes.forEach(function(a) {
        var chip = document.createElement('span');
        chip.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:' + a.color + '22;border:1px solid ' + a.color + '60;border-radius:8px;color:' + a.color + ';font-size:13px;font-weight:600;';
        chip.innerHTML = '<a href="?page=profil&id=' + a.id + '" style="color:inherit;text-decoration:none;border-bottom:1px dashed ' + a.color + '60;" title="Voir le profil">' + escapeHtml(a.name) + '</a>' + (a.data ? '' : ' <span style="color:#5a6580;font-size:11px;">chargement...</span>') + ' <span onclick="removeFromCompare(' + a.id + ')" style="cursor:pointer;margin-left:4px;color:#ff6b6b;font-size:16px;">&times;</span>';
        container.appendChild(chip);
    });
}

function updateEpreuveList() {
    var sel = document.getElementById('cmpEpreuve');
    var epreuves = {};

    cmpAthletes.forEach(function(a) {
        if (!a.data) return;
        (a.data.progressions || []).forEach(function(p) {
            if (p.epreuve) epreuves[p.epreuve] = (epreuves[p.epreuve] || 0) + 1;
        });
    });

    // Trier par frequence
    var sorted = Object.keys(epreuves).sort(function(a, b) { return epreuves[b] - epreuves[a]; });

    sel.innerHTML = '<option value="">-- Choisir une epreuve --</option>';
    sorted.forEach(function(ep) {
        var opt = document.createElement('option');
        opt.value = ep;
        opt.textContent = ep + ' (' + epreuves[ep] + ' donnees)';
        sel.appendChild(opt);
    });
}

function compareNow() {
    var ready = cmpAthletes.filter(function(a) { return a.data; });
    if (ready.length < 2) { alert('Ajoutez au moins 2 athlètes (attendez le chargement)'); return; }

    var epreuve = document.getElementById('cmpEpreuve').value;
    document.getElementById('cmpChartArea').style.display = 'block';

    // 1. LINE CHART - Progressions croisees
    if (epreuve) {
        buildProgressionChart(ready, epreuve);
    }

    // 2. RADAR CHART - Epreuves communes
    buildRadarChart(ready);

    // 3. MEDAILLES CHART
    buildMedaillesChart(ready);

    // 4. RECORDS TABLE
    buildRecordsTable(ready);

    // 5. ANALYSE COMPARATIVE TEXTUELLE
    buildComparisonSummary(ready, epreuve);

    // Scroll vers les graphiques
    document.getElementById('cmpChartArea').scrollIntoView({ behavior: 'smooth' });
}

function buildProgressionChart(athletes, epreuve) {
    document.getElementById('cmpChartTitle').textContent = 'Progression — ' + epreuve;

    // Collecter toutes les annees
    var allYears = {};
    var datasets = [];

    athletes.forEach(function(a) {
        var progByYear = {};
        (a.data.progressions || []).forEach(function(p) {
            if (p.epreuve !== epreuve || !p.performance) return;
            var yr = p.annee;
            if (!progByYear[yr] || p.performance < progByYear[yr].perf) {
                progByYear[yr] = { perf: p.performance, brut: p.performance_brut };
            }
            allYears[yr] = true;
        });

        var sortedYears = Object.keys(allYears).sort();
        datasets.push({
            label: a.name,
            yearData: progByYear,
            borderColor: a.color,
            backgroundColor: a.color + '33',
            tension: 0.3,
            pointRadius: 5,
            pointHoverRadius: 9,
            borderWidth: 3,
            fill: false,
            spanGaps: true
        });
    });

    var sortedYears = Object.keys(allYears).sort();
    datasets.forEach(function(ds) {
        ds.data = sortedYears.map(function(y) { return ds.yearData[y] ? ds.yearData[y].perf : null; });
        ds._brutData = sortedYears.map(function(y) { return ds.yearData[y] ? ds.yearData[y].brut : null; });
        delete ds.yearData;
    });

    // Detecter si c'est une epreuve de distance
    var isDistance = /poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon/i.test(epreuve);

    var canvas = document.getElementById('cmpChart');
    if (cmpChart) cmpChart.destroy();
    cmpChart = new Chart(canvas, {
        type: 'line',
        data: { labels: sortedYears, datasets: datasets },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { padding: 16, usePointStyle: true, font: { size: 13, weight: 600 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var brut = ctx.dataset._brutData ? ctx.dataset._brutData[ctx.dataIndex] : null;
                            return ctx.dataset.label + ': ' + (brut || ctx.parsed.y);
                        }
                    }
                }
            },
            scales: {
                x: { grid: { color: '#1e2a3a' }, title: { display: true, text: 'Annee' } },
                y: { grid: { color: '#1e2a3a' }, reverse: !isDistance, title: { display: true, text: isDistance ? 'Performance (plus haut = meilleur)' : 'Performance centisecondes (plus bas = meilleur)' } }
            }
        }
    });
}

function buildRadarChart(athletes) {
    // Trouver les epreuves communes (au moins 2 athletes ont un record)
    var epCounts = {};
    athletes.forEach(function(a) {
        var seen = {};
        (a.data.records || []).forEach(function(r) {
            if (r.epreuve && r.performance && !seen[r.epreuve]) {
                seen[r.epreuve] = true;
                epCounts[r.epreuve] = (epCounts[r.epreuve] || 0) + 1;
            }
        });
    });

    var commonEp = Object.keys(epCounts).filter(function(ep) { return epCounts[ep] >= 2; }).slice(0, 8);
    if (commonEp.length < 2) {
        document.getElementById('cmpRadar').parentElement.parentElement.style.display = 'none';
        return;
    }
    document.getElementById('cmpRadar').parentElement.parentElement.style.display = '';

    // Normaliser: pour chaque epreuve, trouver le max pour normaliser a 100
    var maxPerf = {};
    athletes.forEach(function(a) {
        (a.data.records || []).forEach(function(r) {
            if (commonEp.indexOf(r.epreuve) === -1 || !r.performance) return;
            if (!maxPerf[r.epreuve] || r.performance > maxPerf[r.epreuve]) maxPerf[r.epreuve] = r.performance;
        });
    });

    var radarDatasets = athletes.map(function(a) {
        var data = commonEp.map(function(ep) {
            var best = null;
            (a.data.records || []).forEach(function(r) {
                if (r.epreuve === ep && r.performance) {
                    if (!best || r.performance < best) best = r.performance;
                }
            });
            if (!best || !maxPerf[ep]) return 0;
            // Inverser pour les courses (plus petit = meilleur = score plus haut)
            return Math.round((1 - (best / maxPerf[ep]) + 1) * 50);
        });
        return {
            label: a.name,
            data: data,
            borderColor: a.color,
            backgroundColor: a.color + '22',
            borderWidth: 2,
            pointRadius: 4
        };
    });

    var canvas = document.getElementById('cmpRadar');
    if (cmpRadarChart) cmpRadarChart.destroy();
    cmpRadarChart = new Chart(canvas, {
        type: 'radar',
        data: { labels: commonEp, datasets: radarDatasets },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top', labels: { padding: 12, usePointStyle: true, font: { size: 12 } } } },
            scales: { r: { grid: { color: '#1e2a3a' }, angleLines: { color: '#1e2a3a' }, pointLabels: { font: { size: 10 }, color: '#8b949e' }, ticks: { display: false } } }
        }
    });
}

function buildMedaillesChart(athletes) {
    var labels = athletes.map(function(a) { return a.name; });
    var ors = athletes.map(function(a) { return (a.data.medailles || []).filter(function(m) { return m.type === 'or'; }).length; });
    var argents = athletes.map(function(a) { return (a.data.medailles || []).filter(function(m) { return m.type === 'argent'; }).length; });
    var bronzes = athletes.map(function(a) { return (a.data.medailles || []).filter(function(m) { return m.type === 'bronze'; }).length; });

    var canvas = document.getElementById('cmpMedChart');
    if (cmpMedChart) cmpMedChart.destroy();
    cmpMedChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Or', data: ors, backgroundColor: '#fbbf24', borderRadius: 4 },
                { label: 'Argent', data: argents, backgroundColor: '#d1d5db', borderRadius: 4 },
                { label: 'Bronze', data: bronzes, backgroundColor: '#d97706', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top', labels: { padding: 12, usePointStyle: true } } },
            scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
}

function buildRecordsTable(athletes) {
    // Collecter toutes les epreuves avec records
    var allEp = {};
    athletes.forEach(function(a) {
        (a.data.records || []).forEach(function(r) {
            if (r.epreuve) allEp[r.epreuve] = true;
        });
    });
    var epreuves = Object.keys(allEp).sort();

    var html = '<table class="bk-table">';
    html += '<tr><th style="text-align:left;padding:8px 12px;font-size:12px;border-bottom:1px solid #1e2a3a;">Épreuve</th>';
    athletes.forEach(function(a) {
        html += '<th style="text-align:center;padding:8px 12px;font-size:12px;border-bottom:1px solid #1e2a3a;"><a href="?page=profil&id=' + a.id + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(a.name) + '</a></th>';
    });
    html += '</tr>';

    epreuves.forEach(function(ep) {
        // Trouver la meilleure perf (plus petite) pour highlighter
        var perfs = athletes.map(function(a) {
            var best = null;
            (a.data.records || []).forEach(function(r) {
                if (r.epreuve === ep && r.performance) {
                    if (!best || r.performance < best) best = { perf: r.performance, brut: r.performance_brut };
                }
            });
            return best;
        });
        var bestPerf = Math.min.apply(null, perfs.filter(function(p) { return p; }).map(function(p) { return p.perf; }));

        html += '<tr><td style="padding:8px 12px;border-bottom:1px solid #1e2a3a10;font-size:13px;"><a href="?page=recherche&epreuve=' + encodeURIComponent(ep) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(ep) + '</a></td>';
        perfs.forEach(function(p) {
            var isBest = p && p.perf === bestPerf && athletes.length > 1;
            var style = 'text-align:center;padding:8px 12px;border-bottom:1px solid #1e2a3a10;font-size:13px;font-family:Courier New,monospace;';
            if (isBest) style += 'color:#34d399;font-weight:700;';
            else style += 'color:#c9d1d9;';
            html += '<td style="' + style + '">' + (p ? escapeHtml(p.brut) : '-') + '</td>';
        });
        html += '</tr>';
    });

    html += '</table>';
    document.getElementById('cmpRecordsTable').innerHTML = html;
}

function buildComparisonSummary(athletes, epreuve) {
    var txt = [];

    // --- Helpers ---
    function getMedals(a) {
        var o = 0, ar = 0, b = 0;
        (a.data.medailles || []).forEach(function(m) { if (m.type === 'or') o++; else if (m.type === 'argent') ar++; else if (m.type === 'bronze') b++; });
        return { or: o, argent: ar, bronze: b, total: o + ar + b };
    }
    function getRecords(a) {
        var recs = {};
        (a.data.records || []).forEach(function(r) { if (r.epreuve && r.performance) recs[r.epreuve] = { perf: r.performance, brut: r.performance_brut }; });
        return recs;
    }
    function getYearsActive(a) {
        var min = 9999, max = 0;
        (a.data.progressions || []).forEach(function(p) { if (p.annee) { if (p.annee < min) min = p.annee; if (p.annee > max) max = p.annee; } });
        (a.data.resultats || []).forEach(function(r) { if (r.annee) { if (r.annee < min) min = r.annee; if (r.annee > max) max = r.annee; } });
        return min < 9999 ? { debut: min, fin: max } : null;
    }
    function getNbPodiums(a) {
        return (a.data.podiums || []).length;
    }
    function getNbSelections(a) {
        return (a.data.selections || []).length;
    }
    function getNbResultats(a) {
        return (a.data.resultats || []).length;
    }
    function getNbEpreuves(a) {
        var ep = {};
        (a.data.records || []).forEach(function(r) { if (r.epreuve) ep[r.epreuve] = true; });
        return Object.keys(ep).length;
    }
    function getIdentite(a) {
        return a.data.identite || {};
    }
    function getCat(a) {
        var catMap = { SE: 'Senior', ES: 'Espoir', JU: 'Junior', CA: 'Cadet', MI: 'Minime', V1: 'Veteran', V2: 'Veteran', V3: 'Veteran' };
        var id = getIdentite(a);
        return catMap[id.categorie] || id.categorie || '';
    }

    var a1 = athletes[0], a2 = athletes[1];
    var n1 = a1.name, n2 = a2.name;

    // --- INTRO ---
    txt.push('Cette analyse compare ' + n1 + ' et ' + n2 + ', deux athletes');
    var c1 = getCat(a1), c2 = getCat(a2);
    if (c1 && c2 && c1 === c2) { txt[0] += ' de la categorie ' + c1 + '.'; }
    else if (c1 && c2) { txt[0] += ' (' + n1 + ' en ' + c1 + ', ' + n2 + ' en ' + c2 + ').'; }
    else { txt[0] += '.'; }

    // --- EXPERIENCE / CARRIERE ---
    var y1 = getYearsActive(a1), y2 = getYearsActive(a2);
    if (y1 && y2) {
        var d1 = y1.fin - y1.debut, d2 = y2.fin - y2.debut;
        var oneYear1 = (d1 === 0), oneYear2 = (d2 === 0);
        // Cas ou l'un ou les deux n'ont fait qu'une seule saison
        if (oneYear1 && oneYear2) {
            if (y1.debut === y2.debut) txt.push('Les deux athletes n\'ont effectue qu\'une seule saison, en ' + y1.debut + '.');
            else txt.push(n1 + ' n\'a effectue qu\'une seule saison en ' + y1.debut + ', tout comme ' + n2 + ' en ' + y2.debut + '.');
        } else if (oneYear1) {
            txt.push(n1 + ' n\'a effectue qu\'une seule saison en ' + y1.debut + ', tandis que ' + n2 + ' a ete actif de ' + y2.debut + ' a ' + y2.fin + ' (' + d2 + ' saisons).');
        } else if (oneYear2) {
            txt.push(n2 + ' n\'a effectue qu\'une seule saison en ' + y2.debut + ', tandis que ' + n1 + ' a ete actif de ' + y1.debut + ' a ' + y1.fin + ' (' + d1 + ' saisons).');
        } else {
            if (y1.debut < y2.debut) {
                txt.push(n1 + ' a debute plus tot (des ' + y1.debut + ') tandis que ' + n2 + ' est apparu en ' + y2.debut + '.');
            } else if (y2.debut < y1.debut) {
                txt.push(n2 + ' a debute plus tot (des ' + y2.debut + ') tandis que ' + n1 + ' est apparu en ' + y1.debut + '.');
            }
            if (d1 > d2) txt.push(n1 + ' possede une carriere plus longue avec ' + d1 + ' saisons d\'activite contre ' + d2 + ' pour ' + n2 + '.');
            else if (d2 > d1) txt.push(n2 + ' possede une carriere plus longue avec ' + d2 + ' saisons contre ' + d1 + ' pour ' + n1 + '.');
            else txt.push('Les deux athletes partagent une duree de carriere similaire de ' + d1 + ' saisons.');
        }
    }

    // --- POLYVALENCE ---
    var ep1 = getNbEpreuves(a1), ep2 = getNbEpreuves(a2);
    if (ep1 > 0 && ep2 > 0) {
        if (ep1 > ep2) txt.push('En termes de polyvalence, ' + n1 + ' est plus diversifie avec ' + ep1 + ' disciplines contre ' + ep2 + ' pour ' + n2 + '.');
        else if (ep2 > ep1) txt.push(n2 + ' se montre plus polyvalent avec ' + ep2 + ' disciplines contre ' + ep1 + ' pour ' + n1 + '.');
        else txt.push('Les deux athletes pratiquent le meme nombre de disciplines (' + ep1 + ').');
    }

    // --- DUEL SUR L'EPREUVE SELECTIONNEE ---
    if (epreuve) {
        var r1 = getRecords(a1), r2 = getRecords(a2);
        var rec1 = r1[epreuve], rec2 = r2[epreuve];
        if (rec1 && rec2) {
            if (rec1.perf < rec2.perf) {
                txt.push('Sur le ' + epreuve + ', ' + n1 + ' devance ' + n2 + ' avec un record de ' + rec1.brut + ' contre ' + rec2.brut + '.');
            } else if (rec2.perf < rec1.perf) {
                txt.push('Sur le ' + epreuve + ', ' + n2 + ' prend l\'avantage avec ' + rec2.brut + ' face aux ' + rec1.brut + ' de ' + n1 + '.');
            } else {
                txt.push('Sur le ' + epreuve + ', les deux athletes sont au coude-a-coude avec un record identique de ' + rec1.brut + '.');
            }
        } else if (rec1 && !rec2) {
            txt.push('Seul ' + n1 + ' possede un record au ' + epreuve + ' (' + rec1.brut + '), ' + n2 + ' n\'ayant pas de reference sur cette discipline.');
        } else if (rec2 && !rec1) {
            txt.push('Seul ' + n2 + ' possede un record au ' + epreuve + ' (' + rec2.brut + '), ' + n1 + ' n\'ayant pas de reference sur cette discipline.');
        }
    }

    // --- EPREUVES COMMUNES : qui domine ---
    var r1 = getRecords(a1), r2 = getRecords(a2);
    var communes = [];
    for (var ep in r1) { if (r2[ep]) communes.push(ep); }
    if (communes.length > 0) {
        var wins1 = 0, wins2 = 0, ties = 0;
        communes.forEach(function(ep) {
            if (r1[ep].perf < r2[ep].perf) wins1++;
            else if (r2[ep].perf < r1[ep].perf) wins2++;
            else ties++;
        });
        if (communes.length >= 2) {
            if (wins1 > wins2) txt.push('Sur les ' + communes.length + ' epreuves communes, ' + n1 + ' domine avec ' + wins1 + ' meilleurs records contre ' + wins2 + ' pour ' + n2 + (ties > 0 ? ' (' + ties + ' egalite' + (ties > 1 ? 's' : '') + ')' : '') + '.');
            else if (wins2 > wins1) txt.push('Sur les ' + communes.length + ' epreuves communes, ' + n2 + ' s\'impose avec ' + wins2 + ' meilleurs records contre ' + wins1 + ' pour ' + n1 + (ties > 0 ? ' (' + ties + ' egalite' + (ties > 1 ? 's' : '') + ')' : '') + '.');
            else txt.push('Sur les ' + communes.length + ' epreuves communes, les deux athletes sont a egalite avec ' + wins1 + ' victoires chacun.');
        }
    }

    // --- PALMARES : MEDAILLES ---
    var m1 = getMedals(a1), m2 = getMedals(a2);
    if (m1.total > 0 || m2.total > 0) {
        if (m1.total > 0 && m2.total > 0) {
            if (m1.total > m2.total) {
                txt.push('Au niveau du palmares, ' + n1 + ' se distingue avec ' + m1.total + ' medailles (' + m1.or + ' or, ' + m1.argent + ' argent, ' + m1.bronze + ' bronze) contre ' + m2.total + ' pour ' + n2 + ' (' + m2.or + ' or, ' + m2.argent + ' argent, ' + m2.bronze + ' bronze).');
            } else if (m2.total > m1.total) {
                txt.push('Au niveau du palmares, ' + n2 + ' se distingue avec ' + m2.total + ' medailles (' + m2.or + ' or, ' + m2.argent + ' argent, ' + m2.bronze + ' bronze) contre ' + m1.total + ' pour ' + n1 + ' (' + m1.or + ' or, ' + m1.argent + ' argent, ' + m1.bronze + ' bronze).');
            } else {
                txt.push('Les deux athletes totalisent le meme nombre de medailles (' + m1.total + '), avec ' + m1.or + ' or pour ' + n1 + ' contre ' + m2.or + ' pour ' + n2 + '.');
            }
        } else if (m1.total > 0) {
            txt.push(n1 + ' possede ' + m1.total + ' medaille' + (m1.total > 1 ? 's' : '') + ' a son actif tandis que ' + n2 + ' n\'en compte aucune.');
        } else {
            txt.push(n2 + ' possede ' + m2.total + ' medaille' + (m2.total > 1 ? 's' : '') + ' a son actif tandis que ' + n1 + ' n\'en compte aucune.');
        }
    }

    // --- PODIUMS ---
    var pod1 = getNbPodiums(a1), pod2 = getNbPodiums(a2);
    if (pod1 > 0 || pod2 > 0) {
        if (pod1 > 0 && pod2 > 0) {
            if (pod1 > pod2) txt.push(n1 + ' totalise ' + pod1 + ' podiums en competition contre ' + pod2 + ' pour ' + n2 + '.');
            else if (pod2 > pod1) txt.push(n2 + ' totalise ' + pod2 + ' podiums contre ' + pod1 + ' pour ' + n1 + '.');
            else txt.push('Les deux athletes comptent le meme nombre de podiums (' + pod1 + ').');
        } else if (pod1 > 0) {
            txt.push(n1 + ' a decroche ' + pod1 + ' podium' + (pod1 > 1 ? 's' : '') + ' en competition, ' + n2 + ' n\'en comptant aucun.');
        } else {
            txt.push(n2 + ' a decroche ' + pod2 + ' podium' + (pod2 > 1 ? 's' : '') + ' en competition, ' + n1 + ' n\'en comptant aucun.');
        }
    }

    // --- SELECTIONS ---
    var sel1 = getNbSelections(a1), sel2 = getNbSelections(a2);
    if (sel1 > 0 || sel2 > 0) {
        if (sel1 > 0 && sel2 > 0) {
            if (sel1 > sel2) txt.push(n1 + ' a ete selectionne ' + sel1 + ' fois en equipe nationale contre ' + sel2 + ' pour ' + n2 + '.');
            else if (sel2 > sel1) txt.push(n2 + ' a ete selectionne ' + sel2 + ' fois contre ' + sel1 + ' pour ' + n1 + '.');
            else txt.push('Chacun compte ' + sel1 + ' selection' + (sel1 > 1 ? 's' : '') + ' en equipe nationale.');
        } else if (sel1 > 0) {
            txt.push(n1 + ' a ete selectionne ' + sel1 + ' fois en equipe nationale, contrairement a ' + n2 + '.');
        } else {
            txt.push(n2 + ' a ete selectionne ' + sel2 + ' fois en equipe nationale, contrairement a ' + n1 + '.');
        }
    }

    // --- VOLUME DE COMPETITION ---
    var res1 = getNbResultats(a1), res2 = getNbResultats(a2);
    if (res1 > 0 && res2 > 0) {
        if (res1 > res2 * 1.5) txt.push('En volume, ' + n1 + ' affiche une activite nettement plus dense avec ' + res1 + ' participations en competition contre ' + res2 + ' pour ' + n2 + '.');
        else if (res2 > res1 * 1.5) txt.push(n2 + ' se montre nettement plus actif en competition avec ' + res2 + ' participations contre ' + res1 + ' pour ' + n1 + '.');
        else if (res1 !== res2) txt.push('Les deux athletes affichent un volume de competition comparable : ' + res1 + ' participations pour ' + n1 + ' et ' + res2 + ' pour ' + n2 + '.');
    }

    // --- CONCLUSION ---
    var avantages1 = 0, avantages2 = 0;
    if (m1.total > m2.total) avantages1++; else if (m2.total > m1.total) avantages2++;
    if (pod1 > pod2) avantages1++; else if (pod2 > pod1) avantages2++;
    if (sel1 > sel2) avantages1++; else if (sel2 > sel1) avantages2++;
    if (communes.length > 0) {
        var w1 = 0, w2 = 0;
        communes.forEach(function(ep) { if (r1[ep].perf < r2[ep].perf) w1++; else if (r2[ep].perf < r1[ep].perf) w2++; });
        if (w1 > w2) avantages1++; else if (w2 > w1) avantages2++;
    }
    if (avantages1 > avantages2 && avantages1 >= 2) {
        txt.push('Au regard de l\'ensemble de ces criteres, ' + n1 + ' presente un profil globalement superieur, meme si ' + n2 + ' conserve des atouts indeniables.');
    } else if (avantages2 > avantages1 && avantages2 >= 2) {
        txt.push('Au regard de l\'ensemble de ces criteres, ' + n2 + ' presente un profil globalement superieur, bien que ' + n1 + ' ne soit pas en reste.');
    } else {
        txt.push('Au final, les deux athletes presentent des profils complementaires, chacun ayant ses points forts dans des domaines differents.');
    }

    // --- Pour 3+ athletes : ajouter un paragraphe supplementaire ---
    if (athletes.length > 2) {
        var others = athletes.slice(2);
        var otherNames = others.map(function(a) { return a.name; });
        txt.push('');
        txt.push('Ce comparatif inclut egalement ' + otherNames.join(' et ') + '.');
        others.forEach(function(a) {
            var m = getMedals(a);
            var ep = getNbEpreuves(a);
            var pod = getNbPodiums(a);
            var rec = getRecords(a);
            var parts = [];
            if (ep > 0) parts.push(ep + ' discipline' + (ep > 1 ? 's' : ''));
            if (m.total > 0) parts.push(m.total + ' medaille' + (m.total > 1 ? 's' : ''));
            if (pod > 0) parts.push(pod + ' podium' + (pod > 1 ? 's' : ''));
            if (parts.length > 0) txt.push(a.name + ' affiche ' + parts.join(', ') + '.');

            if (epreuve && rec[epreuve]) {
                txt.push('Au ' + epreuve + ', ' + a.name + ' detient un record de ' + rec[epreuve].brut + '.');
            }
        });
    }

    document.getElementById('cmpSummaryText').innerHTML = '<p>' + txt.join(' ') + '</p>';
}

// ================================================================
//  ONGLETS COMPARER : Athletes / Clubs
// ================================================================
function switchCmpTab(tab) {
    document.getElementById('cmpAthletesPanel').style.display = tab === 'athletes' ? 'block' : 'none';
    document.getElementById('cmpClubsPanel').style.display = tab === 'clubs' ? 'block' : 'none';
    document.getElementById('cmpTabAthletes').className = tab === 'athletes' ? 'active' : '';
    document.getElementById('cmpTabClubs').className = tab === 'clubs' ? 'active' : '';
}

// ================================================================
//  COMPARATEUR DE CLUBS
// ================================================================
var cmpClubs = []; // [{id, name, data, color}]
var clubCharts = {};
var cmpClubDebounce = null;

(function() {
    var input = document.getElementById('cmpClubSearch');
    if (!input) return;
    var box = document.getElementById('cmpClubSuggestions');

    input.addEventListener('input', function() {
        clearTimeout(cmpClubDebounce);
        var q = this.value.trim();
        if (q.length < 2) { box.style.display = 'none'; return; }
        cmpClubDebounce = setTimeout(function() {
            fetch(BASE_API + '/clubs.php?nom=' + encodeURIComponent(q) + '&limit=8')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    box.innerHTML = '';
                    if (!data.success || !data.clubs || data.clubs.length === 0) { box.style.display = 'none'; return; }
                    data.clubs.forEach(function(c) {
                        if (cmpClubs.find(function(x) { return x.id === c.id_club; })) return;
                        var d = document.createElement('div');
                        d.textContent = c.nom_club + ' (' + c.nb_athletes + ' athletes)';
                        d.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:14px;border-bottom:1px solid #1e2a3a;color:#c9d1d9;';
                        d.onmouseover = function() { d.style.background = '#161b22'; };
                        d.onmouseout = function() { d.style.background = 'transparent'; };
                        d.onclick = function() { addClubToCompare(c.id_club, c.nom_club); };
                        box.appendChild(d);
                    });
                    box.style.display = 'block';
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== input && e.target !== box) box.style.display = 'none';
    });
})();

function addClubToCompare(id, name) {
    if (cmpClubs.find(function(x) { return x.id === id; })) return;
    if (cmpClubs.length >= 6) { alert('Maximum 6 clubs'); return; }

    var searchEl = document.getElementById('cmpClubSearch');
    var suggestEl = document.getElementById('cmpClubSuggestions');
    if (searchEl) searchEl.value = '';
    if (suggestEl) suggestEl.style.display = 'none';

    var color = cmpColors[cmpClubs.length % cmpColors.length];
    cmpClubs.push({ id: id, name: name, data: null, color: color });
    addClubToBasket(id, name);
    updateAllCmpButtons();
    renderSelectedClubs();

    fetch(BASE_API + '/club_stats.php?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            var club = cmpClubs.find(function(x) { return x.id === id; });
            if (club) club.data = data;
            renderSelectedClubs();
            // Auto-compare clubs si chargement depuis URL
            if (window._cmpAutoExpectedClubs && window._cmpAutoExpectedClubs > 0) {
                var ready = cmpClubs.filter(function(c) { return c.data; });
                if (ready.length >= window._cmpAutoExpectedClubs && ready.length >= 2) {
                    window._cmpAutoExpectedClubs = 0;
                    setTimeout(function() { compareClubsNow(); }, 200);
                }
            }
        });
}

function removeClubFromCompare(id) {
    cmpClubs = cmpClubs.filter(function(x) { return x.id !== id; });
    removeClubFromBasket(id);
    updateAllCmpButtons();
    renderSelectedClubs();
    if (cmpClubs.length < 2) {
        document.getElementById('cmpClubChartArea').style.display = 'none';
    }
}

function renderSelectedClubs() {
    var container = document.getElementById('cmpClubSelected');
    container.innerHTML = '';
    cmpClubs.forEach(function(c) {
        var chip = document.createElement('span');
        chip.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:' + c.color + '22;border:1px solid ' + c.color + '60;border-radius:8px;color:' + c.color + ';font-size:13px;font-weight:600;';
        chip.innerHTML = '<a href="?page=recherche&club=' + encodeURIComponent(c.name) + '" style="color:inherit;text-decoration:none;border-bottom:1px dashed ' + c.color + '60;" title="Voir le club">' + escapeHtml(c.name) + '</a>' + (c.data ? ' <span style="color:#5a6580;font-size:11px;">(' + c.data.total_athletes + ' ath.)</span>' : ' <span style="color:#5a6580;font-size:11px;">chargement...</span>') + ' <span onclick="removeClubFromCompare(' + c.id + ')" style="cursor:pointer;margin-left:4px;color:#ff6b6b;font-size:16px;">&times;</span>';
        container.appendChild(chip);
    });
}

function compareClubsNow() {
    var ready = cmpClubs.filter(function(c) { return c.data; });
    if (ready.length < 2) { alert('Ajoutez au moins 2 clubs (attendez le chargement)'); return; }

    document.getElementById('cmpClubChartArea').style.display = 'block';

    // Destroy existing charts
    Object.keys(clubCharts).forEach(function(k) { if (clubCharts[k]) clubCharts[k].destroy(); });
    clubCharts = {};

    var labels = ready.map(function(c) { return c.name.length > 25 ? c.name.substring(0, 25) + '...' : c.name; });
    var bgColors = ready.map(function(c) { return c.color; });

    // 1. TOTAL ATHLETES
    clubCharts.total = new Chart(document.getElementById('clubCmpTotal'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{ label: 'Athletes', data: ready.map(function(c) { return c.data.total_athletes; }), backgroundColor: bgColors, borderRadius: 6, barThickness: 40 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true } } }
    });

    // 2. PAR SEXE (grouped bar)
    clubCharts.sexe = new Chart(document.getElementById('clubCmpSexe'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Hommes', data: ready.map(function(c) { return c.data.par_sexe['M'] || 0; }), backgroundColor: '#3b82f6', borderRadius: 4 },
                { label: 'Femmes', data: ready.map(function(c) { return c.data.par_sexe['F'] || 0; }), backgroundColor: '#ec4899', borderRadius: 4 }
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'top', labels: { padding: 12, usePointStyle: true } } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true } } }
    });

    // 3. PAR CATEGORIE (grouped horizontal bar)
    var allCats = {};
    ready.forEach(function(c) { Object.keys(c.data.par_categorie).forEach(function(cat) { allCats[cat] = true; }); });
    var catLabels = Object.keys(allCats).sort();

    clubCharts.cat = new Chart(document.getElementById('clubCmpCat'), {
        type: 'bar',
        data: {
            labels: catLabels,
            datasets: ready.map(function(c) {
                return {
                    label: c.name.length > 20 ? c.name.substring(0, 20) + '...' : c.name,
                    data: catLabels.map(function(cat) { return c.data.par_categorie[cat] || 0; }),
                    backgroundColor: c.color + 'cc',
                    borderRadius: 3
                };
            })
        },
        options: { responsive: true, plugins: { legend: { position: 'top', labels: { padding: 10, usePointStyle: true, font: { size: 10 } } } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true } } }
    });

    // 4. MEDAILLES (grouped bar)
    clubCharts.med = new Chart(document.getElementById('clubCmpMed'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Or', data: ready.map(function(c) { return c.data.medailles.or || 0; }), backgroundColor: '#fbbf24', borderRadius: 4 },
                { label: 'Argent', data: ready.map(function(c) { return c.data.medailles.argent || 0; }), backgroundColor: '#d1d5db', borderRadius: 4 },
                { label: 'Bronze', data: ready.map(function(c) { return c.data.medailles.bronze || 0; }), backgroundColor: '#d97706', borderRadius: 4 }
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'top', labels: { padding: 12, usePointStyle: true } } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true } } }
    });

    // 5. TOP EPREUVES (horizontal bar avec les epreuves communes)
    var allEp = {};
    ready.forEach(function(c) {
        (c.data.top_epreuves || []).forEach(function(e) { allEp[e.epreuve] = true; });
    });
    var epLabels = Object.keys(allEp).slice(0, 10);

    clubCharts.ep = new Chart(document.getElementById('clubCmpEpreuves'), {
        type: 'bar',
        data: {
            labels: epLabels,
            datasets: ready.map(function(c) {
                return {
                    label: c.name.length > 20 ? c.name.substring(0, 20) + '...' : c.name,
                    data: epLabels.map(function(ep) {
                        var found = (c.data.top_epreuves || []).find(function(e) { return e.epreuve === ep; });
                        return found ? found.nb_records : 0;
                    }),
                    backgroundColor: c.color + 'cc',
                    borderRadius: 3
                };
            })
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { position: 'top', labels: { padding: 10, usePointStyle: true, font: { size: 10 } } } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
    });

    // 6. RADAR (athletes, medailles, epreuves, categories normalises)
    var radarLabels = ['Athlètes', 'Médailles Or', 'Médailles Total', 'Catégories', 'Épreuves'];
    var maxVals = [0, 0, 0, 0, 0];
    ready.forEach(function(c) {
        var vals = [
            c.data.total_athletes,
            c.data.medailles.or || 0,
            (c.data.medailles.or || 0) + (c.data.medailles.argent || 0) + (c.data.medailles.bronze || 0),
            Object.keys(c.data.par_categorie).length,
            (c.data.top_epreuves || []).length
        ];
        vals.forEach(function(v, i) { if (v > maxVals[i]) maxVals[i] = v; });
    });

    clubCharts.radar = new Chart(document.getElementById('clubCmpRadar'), {
        type: 'radar',
        data: {
            labels: radarLabels,
            datasets: ready.map(function(c) {
                var vals = [
                    c.data.total_athletes,
                    c.data.medailles.or || 0,
                    (c.data.medailles.or || 0) + (c.data.medailles.argent || 0) + (c.data.medailles.bronze || 0),
                    Object.keys(c.data.par_categorie).length,
                    (c.data.top_epreuves || []).length
                ];
                return {
                    label: c.name.length > 20 ? c.name.substring(0, 20) + '...' : c.name,
                    data: vals.map(function(v, i) { return maxVals[i] > 0 ? Math.round(v / maxVals[i] * 100) : 0; }),
                    borderColor: c.color,
                    backgroundColor: c.color + '22',
                    borderWidth: 2,
                    pointRadius: 4
                };
            })
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } } },
            scales: { r: { grid: { color: '#1e2a3a' }, angleLines: { color: '#1e2a3a' }, pointLabels: { font: { size: 11 }, color: '#8b949e' }, ticks: { display: false }, max: 100 } }
        }
    });

    // 7. TABLEAU TOP ATHLETES
    var html = '<table class="bk-table">';
    ready.forEach(function(c) {
        html += '<tr><td colspan="5" style="padding:12px;color:' + c.color + ';font-weight:700;font-size:15px;border-bottom:2px solid ' + c.color + '40;background:' + c.color + '08;">' + escapeHtml(c.name) + '</td></tr>';
        html += '<tr><th style="text-align:left;padding:6px 12px;font-size:11px;">Athlete</th><th style="padding:6px 12px;font-size:11px;">Cat</th><th style="padding:6px 12px;font-size:11px;">Sexe</th><th style="padding:6px 12px;font-size:11px;">Resultats</th><th style="padding:6px 12px;font-size:11px;">Records</th></tr>';
        (c.data.top_athletes || []).forEach(function(a) {
            html += '<tr>';
            html += '<td style="padding:6px 12px;font-size:13px;border-bottom:1px solid #1e2a3a10;"><a href="?page=profil&id=' + a.athlete_id + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(a.nom_complet) + '</a></td>';
            html += '<td style="padding:6px 12px;font-size:13px;text-align:center;border-bottom:1px solid #1e2a3a10;">' + escapeHtml(a.categorie) + '</td>';
            html += '<td style="padding:6px 12px;font-size:13px;text-align:center;border-bottom:1px solid #1e2a3a10;">' + escapeHtml(a.sexe) + '</td>';
            html += '<td style="padding:6px 12px;font-size:13px;text-align:center;border-bottom:1px solid #1e2a3a10;color:#34d399;">' + a.nb_resultats + '</td>';
            html += '<td style="padding:6px 12px;font-size:13px;text-align:center;border-bottom:1px solid #1e2a3a10;color:#f59e0b;">' + a.nb_records + '</td>';
            html += '</tr>';
        });
    });
    html += '</table>';
    document.getElementById('clubCmpAthletesTable').innerHTML = html;

    buildClubComparisonSummary(ready);

    document.getElementById('cmpClubChartArea').scrollIntoView({ behavior: 'smooth' });
}

function buildClubComparisonSummary(clubs) {
    var txt = [];

    // --- Helpers ---
    function getTotalAthletes(c) { return c.data.total_athletes || 0; }
    function getHommes(c) { return c.data.par_sexe['M'] || 0; }
    function getFemmes(c) { return c.data.par_sexe['F'] || 0; }
    function getMedTotal(c) { return (c.data.medailles.or || 0) + (c.data.medailles.argent || 0) + (c.data.medailles.bronze || 0); }
    function getMedOr(c) { return c.data.medailles.or || 0; }
    function getMedArgent(c) { return c.data.medailles.argent || 0; }
    function getMedBronze(c) { return c.data.medailles.bronze || 0; }
    function getNbCategories(c) { return Object.keys(c.data.par_categorie || {}).length; }
    function getCategories(c) { return Object.keys(c.data.par_categorie || {}); }
    function getNbNationalites(c) { return Object.keys(c.data.nationalites || {}).length; }
    function getNbEpreuves(c) { return (c.data.epreuves || c.data.top_epreuves || []).length; }
    function getEpreuves(c) {
        return (c.data.epreuves || c.data.top_epreuves || []).map(function(e) { return e.epreuve || e.nom_epreuve; });
    }
    function getNbRecords(c) {
        var total = 0;
        (c.data.epreuves || c.data.top_epreuves || []).forEach(function(e) { total += (e.nb_records || e.c || 0); });
        return total;
    }
    function getPeriode(c) {
        return { debut: c.data.annee_debut, fin: c.data.annee_fin };
    }
    function getDuree(c) {
        var p = getPeriode(c);
        if (p.debut && p.fin) return p.fin - p.debut;
        return 0;
    }

    var c1 = clubs[0], c2 = clubs[1];
    var n1 = c1.name, n2 = c2.name;

    // --- INTRO ---
    txt.push('Cette analyse compare les clubs ' + n1 + ' et ' + n2 + '.');

    // --- PERIODE D'ACTIVITE ---
    var p1 = getPeriode(c1), p2 = getPeriode(c2);
    var d1 = getDuree(c1), d2 = getDuree(c2);
    if (p1.debut && p2.debut) {
        var oneS1 = (d1 === 0 && p1.debut), oneS2 = (d2 === 0 && p2.debut);
        if (oneS1 && oneS2) {
            if (p1.debut === p2.debut) txt.push('Les deux clubs n\'ont ete actifs que sur une seule saison, en ' + p1.debut + '.');
            else txt.push(n1 + ' n\'a ete actif qu\'une seule saison en ' + p1.debut + ', tout comme ' + n2 + ' en ' + p2.debut + '.');
        } else if (oneS1) {
            txt.push(n1 + ' n\'a ete actif qu\'une seule saison en ' + p1.debut + ', tandis que ' + n2 + ' a ete actif de ' + p2.debut + ' a ' + p2.fin + ' (' + d2 + ' ans).');
        } else if (oneS2) {
            txt.push(n2 + ' n\'a ete actif qu\'une seule saison en ' + p2.debut + ', tandis que ' + n1 + ' a ete actif de ' + p1.debut + ' a ' + p1.fin + ' (' + d1 + ' ans).');
        } else {
            if (p1.debut < p2.debut) txt.push(n1 + ' est un club plus ancien, actif depuis ' + p1.debut + ', tandis que ' + n2 + ' a demarre en ' + p2.debut + '.');
            else if (p2.debut < p1.debut) txt.push(n2 + ' est un club plus ancien, actif depuis ' + p2.debut + ', tandis que ' + n1 + ' a demarre en ' + p1.debut + '.');
            else txt.push('Les deux clubs ont demarre la meme annee, en ' + p1.debut + '.');
            if (d1 > d2) txt.push(n1 + ' affiche une longevite superieure avec ' + d1 + ' annees d\'activite contre ' + d2 + ' pour ' + n2 + '.');
            else if (d2 > d1) txt.push(n2 + ' affiche une longevite superieure avec ' + d2 + ' annees d\'activite contre ' + d1 + ' pour ' + n1 + '.');
        }
    }

    // --- EFFECTIFS ---
    var t1 = getTotalAthletes(c1), t2 = getTotalAthletes(c2);
    if (t1 > 0 && t2 > 0) {
        if (t1 > t2) txt.push('En termes d\'effectif, ' + n1 + ' est le plus grand club avec ' + t1 + ' athletes contre ' + t2 + ' pour ' + n2 + '.');
        else if (t2 > t1) txt.push(n2 + ' dispose d\'un effectif plus important avec ' + t2 + ' athletes contre ' + t1 + ' pour ' + n1 + '.');
        else txt.push('Les deux clubs comptent le meme nombre d\'athletes (' + t1 + ').');
    }

    // --- REPARTITION PAR SEXE ---
    var h1 = getHommes(c1), f1 = getFemmes(c1), h2 = getHommes(c2), f2 = getFemmes(c2);
    if ((h1 + f1) > 0 && (h2 + f2) > 0) {
        var pctF1 = t1 > 0 ? Math.round(f1 / t1 * 100) : 0;
        var pctF2 = t2 > 0 ? Math.round(f2 / t2 * 100) : 0;
        if (Math.abs(pctF1 - pctF2) > 10) {
            if (pctF1 > pctF2) txt.push(n1 + ' presente une feminisation plus marquee (' + pctF1 + '% de femmes) par rapport a ' + n2 + ' (' + pctF2 + '%).');
            else txt.push(n2 + ' affiche un taux de feminisation plus eleve (' + pctF2 + '% de femmes) contre ' + pctF1 + '% pour ' + n1 + '.');
        } else {
            txt.push('La repartition hommes/femmes est comparable entre les deux clubs (environ ' + pctF1 + '% et ' + pctF2 + '% de femmes respectivement).');
        }
    }

    // --- DIVERSITE DES CATEGORIES ---
    var cat1 = getNbCategories(c1), cat2 = getNbCategories(c2);
    if (cat1 > 0 && cat2 > 0) {
        if (cat1 > cat2) txt.push(n1 + ' couvre davantage de categories d\'age (' + cat1 + ') que ' + n2 + ' (' + cat2 + '), signe d\'une structure plus diversifiee.');
        else if (cat2 > cat1) txt.push(n2 + ' offre plus de diversite de categories (' + cat2 + ') contre ' + cat1 + ' pour ' + n1 + '.');
        else txt.push('Les deux clubs couvrent le meme nombre de categories d\'age (' + cat1 + ').');
    }

    // --- NATIONALITES ---
    var nat1 = getNbNationalites(c1), nat2 = getNbNationalites(c2);
    if (nat1 > 0 || nat2 > 0) {
        if (nat1 > 1 && nat2 > 1) {
            if (nat1 > nat2) txt.push(n1 + ' se distingue par sa diversite internationale avec ' + nat1 + ' nationalites representees contre ' + nat2 + ' pour ' + n2 + '.');
            else if (nat2 > nat1) txt.push(n2 + ' est plus cosmopolite avec ' + nat2 + ' nationalites contre ' + nat1 + ' pour ' + n1 + '.');
            else txt.push('Les deux clubs comptent le meme nombre de nationalites (' + nat1 + ').');
        } else if (nat1 > 1) {
            txt.push(n1 + ' compte ' + nat1 + ' nationalites representees.');
        } else if (nat2 > 1) {
            txt.push(n2 + ' compte ' + nat2 + ' nationalites representees.');
        }
    }

    // --- DISCIPLINES / EPREUVES ---
    var ep1 = getNbEpreuves(c1), ep2 = getNbEpreuves(c2);
    if (ep1 > 0 && ep2 > 0) {
        if (ep1 > ep2) txt.push('En matiere de disciplines, ' + n1 + ' propose un eventail plus large avec ' + ep1 + ' epreuves contre ' + ep2 + ' pour ' + n2 + '.');
        else if (ep2 > ep1) txt.push(n2 + ' couvre plus de disciplines (' + ep2 + ') que ' + n1 + ' (' + ep1 + ').');
        else txt.push('Les deux clubs pratiquent le meme nombre de disciplines (' + ep1 + ').');

        // Epreuves communes
        var epList1 = getEpreuves(c1), epList2 = getEpreuves(c2);
        var communes = epList1.filter(function(e) { return epList2.indexOf(e) !== -1; });
        if (communes.length > 0 && communes.length < Math.max(ep1, ep2)) {
            txt.push('Ils partagent ' + communes.length + ' epreuve' + (communes.length > 1 ? 's' : '') + ' en commun' + (communes.length <= 5 ? ' : ' + communes.join(', ') : '') + '.');
        } else if (communes.length === Math.min(ep1, ep2) && communes.length > 0) {
            txt.push('Toutes les epreuves du club le moins diversifie sont egalement pratiquees par l\'autre.');
        }
    }

    // --- RECORDS ---
    var rec1 = getNbRecords(c1), rec2 = getNbRecords(c2);
    if (rec1 > 0 || rec2 > 0) {
        if (rec1 > 0 && rec2 > 0) {
            if (rec1 > rec2) txt.push(n1 + ' totalise ' + rec1 + ' records enregistres contre ' + rec2 + ' pour ' + n2 + ', temoignant d\'un volume de performance superieur.');
            else if (rec2 > rec1) txt.push(n2 + ' cumule ' + rec2 + ' records contre ' + rec1 + ' pour ' + n1 + '.');
            else txt.push('Les deux clubs comptent le meme nombre de records (' + rec1 + ').');
        } else if (rec1 > 0) {
            txt.push(n1 + ' totalise ' + rec1 + ' records, ' + n2 + ' n\'en ayant aucun d\'enregistre.');
        } else {
            txt.push(n2 + ' totalise ' + rec2 + ' records, ' + n1 + ' n\'en ayant aucun d\'enregistre.');
        }
    }

    // --- MEDAILLES ---
    var mt1 = getMedTotal(c1), mt2 = getMedTotal(c2);
    if (mt1 > 0 || mt2 > 0) {
        var mo1 = getMedOr(c1), ma1 = getMedArgent(c1), mb1 = getMedBronze(c1);
        var mo2 = getMedOr(c2), ma2 = getMedArgent(c2), mb2 = getMedBronze(c2);
        if (mt1 > 0 && mt2 > 0) {
            if (mt1 > mt2) {
                txt.push('Au palmares, ' + n1 + ' domine avec ' + mt1 + ' medailles (dont ' + mo1 + ' en or, ' + ma1 + ' en argent et ' + mb1 + ' en bronze) contre ' + mt2 + ' pour ' + n2 + ' (' + mo2 + ' or, ' + ma2 + ' argent, ' + mb2 + ' bronze).');
            } else if (mt2 > mt1) {
                txt.push(n2 + ' s\'impose au palmares avec ' + mt2 + ' medailles (dont ' + mo2 + ' en or, ' + ma2 + ' en argent et ' + mb2 + ' en bronze) face aux ' + mt1 + ' de ' + n1 + ' (' + mo1 + ' or, ' + ma1 + ' argent, ' + mb1 + ' bronze).');
            } else {
                txt.push('Les deux clubs totalisent le meme nombre de medailles (' + mt1 + '), avec ' + mo1 + ' en or pour ' + n1 + ' contre ' + mo2 + ' pour ' + n2 + '.');
            }
        } else if (mt1 > 0) {
            txt.push(n1 + ' possede ' + mt1 + ' medaille' + (mt1 > 1 ? 's' : '') + ' a son actif (dont ' + mo1 + ' en or), tandis que ' + n2 + ' n\'en compte aucune.');
        } else {
            txt.push(n2 + ' possede ' + mt2 + ' medaille' + (mt2 > 1 ? 's' : '') + ' a son actif (dont ' + mo2 + ' en or), tandis que ' + n1 + ' n\'en compte aucune.');
        }
    }

    // --- TOP ATHLETES ---
    var top1 = c1.data.top_athletes || [], top2 = c2.data.top_athletes || [];
    if (top1.length > 0 && top2.length > 0) {
        var best1 = top1[0], best2 = top2[0];
        txt.push('L\'athlete le plus actif de ' + n1 + ' est ' + best1.nom_complet + ' avec ' + best1.nb_resultats + ' resultats et ' + best1.nb_records + ' records, tandis que chez ' + n2 + ' c\'est ' + best2.nom_complet + ' (' + best2.nb_resultats + ' resultats, ' + best2.nb_records + ' records).');
    }

    // --- CONCLUSION ---
    var avantages1 = 0, avantages2 = 0;
    if (t1 > t2) avantages1++; else if (t2 > t1) avantages2++;
    if (mt1 > mt2) avantages1++; else if (mt2 > mt1) avantages2++;
    if (ep1 > ep2) avantages1++; else if (ep2 > ep1) avantages2++;
    if (rec1 > rec2) avantages1++; else if (rec2 > rec1) avantages2++;
    if (cat1 > cat2) avantages1++; else if (cat2 > cat1) avantages2++;
    if (nat1 > nat2) avantages1++; else if (nat2 > nat1) avantages2++;

    if (avantages1 > avantages2 && avantages1 >= 3) {
        txt.push('En conclusion, ' + n1 + ' se demarque comme le club le plus complet sur la majorite des criteres analyses, meme si ' + n2 + ' conserve des atouts dans certains domaines.');
    } else if (avantages2 > avantages1 && avantages2 >= 3) {
        txt.push('En conclusion, ' + n2 + ' se positionne comme le club le plus complet sur la majorite des criteres, bien que ' + n1 + ' presente egalement des points forts notables.');
    } else {
        txt.push('En conclusion, les deux clubs presentent des profils complementaires avec chacun leurs forces, offrant des atouts differents a leurs athletes respectifs.');
    }

    // --- Pour 3+ clubs ---
    if (clubs.length > 2) {
        var others = clubs.slice(2);
        var otherNames = others.map(function(c) { return c.name; });
        txt.push('');
        txt.push('Ce comparatif inclut egalement ' + otherNames.join(' et ') + '.');
        others.forEach(function(c) {
            var parts = [];
            var ta = getTotalAthletes(c);
            if (ta > 0) parts.push(ta + ' athlete' + (ta > 1 ? 's' : ''));
            var mt = getMedTotal(c);
            if (mt > 0) parts.push(mt + ' medaille' + (mt > 1 ? 's' : ''));
            var ep = getNbEpreuves(c);
            if (ep > 0) parts.push(ep + ' discipline' + (ep > 1 ? 's' : ''));
            var nr = getNbRecords(c);
            if (nr > 0) parts.push(nr + ' records');
            if (parts.length > 0) txt.push(c.name + ' regroupe ' + parts.join(', ') + '.');
        });
    }

    document.getElementById('cmpClubSummaryText').innerHTML = '<p>' + txt.join(' ') + '</p>';
}

// ════════════ AUTO-LOAD from URL params or localStorage basket ════════════
(function() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('page') !== 'comparer') return;

    var urlIds = params.get('ids');
    var urlLicences = params.get('licences');
    var urlClubs = params.get('clubs');
    var loadedFromUrl = false;

    // Compter le total d'athletes attendus pour auto-compare
    var expectedCount = 0;
    if (urlIds) expectedCount += urlIds.split(',').filter(function(x) { return parseInt(x.trim()); }).length;
    if (urlLicences) expectedCount += urlLicences.split(',').filter(function(x) { return x.trim(); }).length;
    if (expectedCount >= 2) window._cmpAutoExpected = expectedCount;

    // Load athletes from URL ?ids=123,456,789 (par ID externe)
    if (urlIds) {
        loadedFromUrl = true;
        urlIds.split(',').forEach(function(rawId) {
            var id = parseInt(rawId.trim());
            if (!id) return;
            fetch(BASE_API + '/athlete.php?id=' + id)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.identite) return;
                    addAthleteToCompare(data.identite.athlete_id || id, data.identite.nom_complet || 'Athlete #' + id);
                });
        });
    }

    // Load athletes from URL ?licences=1234567,7654321 (par licence)
    if (urlLicences) {
        loadedFromUrl = true;
        urlLicences.split(',').forEach(function(lic) {
            lic = lic.trim();
            if (!lic) return;
            fetch(BASE_API + '/athlete.php?licence=' + encodeURIComponent(lic))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.identite) return;
                    addAthleteToCompare(data.identite.athlete_id, data.identite.nom_complet || 'Licence ' + lic);
                });
        });
    }

    // Load clubs from URL ?clubs=Nom1,Nom2
    if (urlClubs) {
        loadedFromUrl = true;
        var clubCount = urlClubs.split(',').filter(function(x) { return x.trim(); }).length;
        if (clubCount >= 2) window._cmpAutoExpectedClubs = clubCount;
        if (!urlIds && !urlLicences) switchCmpTab('clubs');
        urlClubs.split(',').forEach(function(name) {
            name = name.trim();
            if (!name) return;
            // Fetch club id then add
            fetch(BASE_API + '/club_stats.php?nom=' + encodeURIComponent(name))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.club) return;
                    addClubToCompare(data.club.id_club, data.club.nom_club);
                });
        });
    }

    // Fallback: load from localStorage if no URL params
    if (!loadedFromUrl) {
        var basketAth = getBasketAthletes();
        var basketClb = getBasketClubs();
        if (basketAth.length > 0) {
            basketAth.forEach(function(a) { addAthleteToCompare(a.id, a.name); });
        }
        if (basketClb.length > 0) {
            if (basketAth.length === 0) switchCmpTab('clubs');
            basketClb.forEach(function(c) { addClubToCompare(c.id, c.name); });
        }
    }
})();

// ════════════ Share comparison link ════════════
function getCmpShareLink() {
    var base = location.origin + location.pathname + '?page=comparer';
    if (cmpAthletes.length > 0) {
        base += '&ids=' + cmpAthletes.map(function(a) { return a.id; }).join(',');
    }
    if (cmpClubs.length > 0) {
        base += '&clubs=' + cmpClubs.map(function(c) { return encodeURIComponent(c.name); }).join(',');
    }
    return base;
}
function copyCmpLink() {
    var link = getCmpShareLink();
    navigator.clipboard.writeText(link).then(function() {
        var btn = document.getElementById('btnCmpShare');
        if (btn) { btn.textContent = '\u2713 Lien copié !'; setTimeout(function() { btn.innerHTML = '&#128279; Copier le lien'; }, 2000); }
    });
}
</script>

<!-- ====== TRACKING / LOGGING ====== -->
<script>
(function() {
    try {
    var LOG_URL = (typeof BASE_API !== 'undefined' ? BASE_API : '/api') + '/log.php';
    var SID;
    try { SID = sessionStorage.getItem('bk_sid'); } catch(e) { SID = null; }
    if (!SID) {
        SID = Math.random().toString(36).substr(2) + Date.now().toString(36);
        try { sessionStorage.setItem('bk_sid', SID); } catch(e) {}
    }
    var queue = [];
    var flushTimer = null;
    var pageStart = Date.now();
    var flushing = false;
    var leaveLogged = false;

    function bkLog(action, detail, value, target) {
        try {
            queue.push({
                sid: SID,
                page: (location.pathname + location.search).substring(0, 500),
                action: (action || 'unknown').substring(0, 50),
                detail: ((detail || '') + '').substring(0, 500),
                value: ((value || '') + '').substring(0, 1000),
                target: ((target || '') + '').substring(0, 200),
                screen: (typeof screen !== 'undefined' ? screen.width + 'x' + screen.height : ''),
                lang: (navigator.language || ''),
                referrer: (document.referrer || '').substring(0, 500),
                duration_ms: Date.now() - pageStart,
            });
            if (queue.length > 200) queue.splice(0, queue.length - 200);
            if (!flushTimer) flushTimer = setTimeout(flushLogs, 2000);
        } catch(e) {}
    }

    function flushLogs() {
        flushTimer = null;
        if (queue.length === 0 || flushing) return;
        flushing = true;
        var batch = queue.splice(0, 50);
        var body;
        try { body = JSON.stringify({ events: batch }); } catch(e) { flushing = false; return; }
        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(LOG_URL, new Blob([body], { type: 'application/json' }));
            } else if (typeof fetch !== 'undefined') {
                fetch(LOG_URL, { method: 'POST', body: body, headers: { 'Content-Type': 'application/json' }, keepalive: true }).catch(function(){});
            }
        } catch(e) {}
        flushing = false;
        if (queue.length > 0 && !flushTimer) flushTimer = setTimeout(flushLogs, 2000);
    }

    // 1. Page view
    bkLog('page_view', document.title);

    // 2. Clicks (links, buttons)
    document.addEventListener('click', function(e) {
        try {
            var el = e.target.closest ? e.target.closest('a, button, [onclick]') : null;
            if (!el) return;
            var tag = el.tagName.toLowerCase();
            var text = (el.textContent || '').trim().substring(0, 80);
            var href = el.getAttribute('href') || '';
            if (tag === 'a' && href) {
                bkLog('click_link', text, href, el.className || '');
            } else {
                bkLog('click_button', text, '', el.className || '');
            }
        } catch(e) {}
    }, true);

    // 3. Form submits
    document.addEventListener('submit', function(e) {
        try {
            var form = e.target;
            var data = {};
            try {
                var fd = new FormData(form);
                fd.forEach(function(v, k) {
                    if (k !== 'password' && k !== 'mot_de_passe') data[k] = (v + '').substring(0, 100);
                });
            } catch(ex) {}
            bkLog('form_submit', form.action || '', JSON.stringify(data));
        } catch(e) {}
    }, true);

    // 4. Search inputs (debounced)
    var searchTimers = {};
    document.addEventListener('input', function(e) {
        try {
            var el = e.target;
            if (el.tagName !== 'INPUT' && el.tagName !== 'SELECT' && el.tagName !== 'TEXTAREA') return;
            var name = el.name || el.id || el.placeholder || 'input';
            if (el.type === 'password') return;
            clearTimeout(searchTimers[name]);
            searchTimers[name] = setTimeout(function() {
                bkLog('input_change', name, (el.value || '').substring(0, 200));
            }, 1500);
        } catch(e) {}
    }, true);

    // 5. Tab switches / filter changes
    try {
        var origPushState = history.pushState;
        if (origPushState) {
            history.pushState = function() {
                origPushState.apply(history, arguments);
                bkLog('navigation', 'pushState', location.pathname + location.search);
            };
        }
    } catch(e) {}
    window.addEventListener('popstate', function() {
        bkLog('navigation', 'popstate', location.pathname + location.search);
    });

    // 6. Copy actions
    document.addEventListener('copy', function() {
        try {
            var sel = (window.getSelection() || '').toString().substring(0, 200);
            bkLog('copy', 'text_copied', sel);
        } catch(e) {}
    });

    // 7. Scroll depth (on page leave)
    var maxScroll = 0;
    window.addEventListener('scroll', function() {
        try {
            var pct = Math.round((window.scrollY + window.innerHeight) / document.documentElement.scrollHeight * 100);
            if (pct > maxScroll) maxScroll = pct;
        } catch(e) {}
    }, { passive: true });

    // 8. Flush on page leave (une seule fois)
    function onLeave() {
        if (leaveLogged) return;
        leaveLogged = true;
        bkLog('page_leave', 'scroll_depth=' + maxScroll + '%', '', '');
        flushLogs();
    }
    window.addEventListener('beforeunload', onLeave);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') onLeave();
    });

    // 9. Errors
    window.addEventListener('error', function(e) {
        try {
            bkLog('js_error', (e.message || '').substring(0, 200), (e.filename || '') + ':' + (e.lineno || 0));
        } catch(ex) {}
    });

    // Expose for manual logging
    window.bkLog = bkLog;

    } catch(e) { /* tracker init failed silently */ }
})();
</script>

<!-- Banniere Newsletter -->
<div class="newsletter-bar" id="newsletterBar">
    <span class="nl-text">&#128232; Recevez le classement hebdo par email</span>
    <span class="nl-sub">— Aucun spam, desabonnement en 1 clic</span>
    <input type="email" id="nlEmail" placeholder="votre@email.com" autocomplete="email">
    <button class="nl-btn" id="nlBtn" onclick="subscribeNewsletter()">S'inscrire</button>
    <button class="nl-close" onclick="closeNewsletter()">&times;</button>
</div>

<!-- Modal Connexion requise (Follow + PDF) -->
<div class="follow-overlay" id="loginRequiredOverlay">
    <div class="follow-modal" style="position:relative;text-align:center;">
        <button class="btn-close" onclick="closeLoginRequired()">&times;</button>
        <div style="font-size:40px;margin-bottom:12px;" id="loginReqIcon">&#9825;</div>
        <h3 id="loginReqTitle">Connexion requise</h3>
        <p id="loginReqDesc" style="color:#8b949e;margin-bottom:20px;">Connectez-vous avec Google pour utiliser cette fonctionnalite.</p>
        <a href="login.php" class="btn-google-modal" style="display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:14px;background:#fff;color:#3c4043;border:1px solid #dadce0;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;transition:background .2s,box-shadow .2s;">
            <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24.08 24.08 0 0 0 0 21.56l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
            Se connecter avec Google
        </a>
        <p style="font-size:11px;color:#484f58;margin-top:16px;margin-bottom:0;">Gratuit et rapide. Aucun spam.</p>
    </div>
</div>

<script>
(function() {
    var _followAthleteId = null;
    var _bkUser = null;

    // Verifier si l'utilisateur est connecte
    fetch(BASE_API + '/auth/me.php', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) { if (data.authenticated) _bkUser = data.user; })
        .catch(function() {});

    window._showLoginRequired = function(icon, title, desc) {
        document.getElementById('loginReqIcon').innerHTML = icon || '\u2661';
        document.getElementById('loginReqTitle').textContent = title || 'Connexion requise';
        document.getElementById('loginReqDesc').textContent = desc || 'Connectez-vous avec Google pour utiliser cette fonctionnalite.';
        document.getElementById('loginRequiredOverlay').classList.add('active');
    };
    window.closeLoginRequired = function() {
        document.getElementById('loginRequiredOverlay').classList.remove('active');
    };
    document.getElementById('loginRequiredOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeLoginRequired();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLoginRequired();
    });

    window.toggleFollow = function(athleteId) {
        _followAthleteId = athleteId;
        if (!_bkUser) {
            _showLoginRequired('\u2661', 'Suivre cet athlete', 'Connectez-vous avec Google pour suivre cet athlete et etre notifie de ses nouveaux resultats.');
            return;
        }
        _doFollow(athleteId, _bkUser.email);
    };

    function _doFollow(athleteId, email) {
        var btn = document.getElementById('btnFollow');
        if (btn) btn.textContent = '...';
        fetch(BASE_API + '/follow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ athlete_id: athleteId, email: email })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { if (btn) btn.textContent = '\u2661 Suivre'; return; }
            _updateFollowBtn(data.following, data.count);
        })
        .catch(function() { if (btn) btn.textContent = '\u2661 Suivre'; });
    }

    function _updateFollowBtn(following, count) {
        var btn = document.getElementById('btnFollow');
        if (!btn) return;
        if (following) {
            btn.className = 'btn-follow following';
            btn.innerHTML = '\u2665 Suivi' + (count > 0 ? ' <span class="follow-count">' + count + '</span>' : '');
        } else {
            btn.className = 'btn-follow';
            btn.innerHTML = '\u2661 Suivre' + (count > 0 ? ' <span class="follow-count">' + count + '</span>' : '');
        }
    }

    // Init : verifier etat au chargement du profil
    function _checkFollowStatus() {
        var btn = document.getElementById('btnFollow');
        if (!btn || !_bkUser) return;
        var athleteId = btn.getAttribute('onclick');
        if (!athleteId) return;
        var m = athleteId.match(/toggleFollow\((\d+)\)/);
        if (!m) return;
        var id = m[1];
        var url = BASE_API + '/follow.php?athlete_id=' + id + '&email=' + encodeURIComponent(_bkUser.email);
        fetch(url, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) _updateFollowBtn(data.following, data.count);
        })
        .catch(function() {});
    }
    // Attendre que _bkUser soit charge
    setTimeout(_checkFollowStatus, 500);
})();
</script>

<!-- Club Follow JS -->
<script>
(function() {
    var _clubFollowPending = null;

    window.toggleFollowClub = function(clubId, suffix) {
        _clubFollowPending = { clubId: clubId, suffix: suffix || '' };
        if (!window._bkUser) {
            _showLoginRequired('\u2661', 'Suivre ce club', 'Connectez-vous avec Google pour suivre ce club et etre notifie de ses nouveaux resultats.');
            return;
        }
        _doFollowClub(clubId, window._bkUser.email, suffix || '');
    };

    function _doFollowClub(clubId, email, suffix) {
        var s = suffix || '';
        var btn = document.getElementById('btnFollowClub' + s);
        if (btn) btn.textContent = '...';
        fetch(BASE_API + '/follow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ club_id: clubId, email: email })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { if (btn) btn.innerHTML = '\u2661 Suivre'; return; }
            _updateClubFollowBtn(data.following, data.count, s);
        })
        .catch(function() { if (btn) btn.innerHTML = '\u2661 Suivre'; });
    }

    function _updateClubFollowBtn(following, count, suffix) {
        var s = suffix || '';
        var btn = document.getElementById('btnFollowClub' + s);
        if (!btn) return;
        if (following) {
            btn.className = 'btn-follow btn-follow-club following';
            btn.innerHTML = '\u2665 Suivi' + (count > 0 ? ' <span class="follow-count">' + count + '</span>' : '');
        } else {
            btn.className = 'btn-follow btn-follow-club';
            btn.innerHTML = '\u2661 Suivre' + (count > 0 ? ' <span class="follow-count">' + count + '</span>' : '');
        }
    }

    window._checkClubFollowStatus = function(clubId, suffix) {
        var s = suffix || '';
        if (!window._bkUser) return;
        var url = BASE_API + '/follow.php?club_id=' + clubId + '&email=' + encodeURIComponent(window._bkUser.email);
        fetch(url, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) _updateClubFollowBtn(data.following, data.count, s);
        })
        .catch(function() {});
    };
})();
</script>

<!-- PDF + Newsletter JS -->
<script>
(function() {
    var _pdfAthleteId = null;
    var _pdfAthleteName = '';

    // ========== PDF ==========
    window.downloadPdf = function(athleteId, athleteName) {
        _pdfAthleteId = athleteId;
        _pdfAthleteName = athleteName;
        if (!window._bkUser) {
            _showLoginRequired('\ud83d\udcc4', 'Telecharger le PDF', 'Connectez-vous avec Google pour telecharger la fiche complete de cet athlete en PDF.');
            return;
        }
        _generatePdf(athleteId, athleteName, window._bkUser.email);
    };

    function _generatePdf(athleteId, athleteName, email) {
        // Enregistrer l'email
        fetch(BASE_API + '/subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, source: 'pdf', detail: athleteName + ' (id:' + athleteId + ')' })
        }).catch(function(){});

        // Envoyer la fiche par email
        fetch(BASE_API + '/send_profile_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, athlete_id: athleteId })
        }).catch(function(){});

        // Generer le PDF cote client
        var btn = document.getElementById('btnPdf');
        if (btn) btn.textContent = '...';

        fetch(BASE_API + '/athlete.php?id=' + athleteId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || !data.success) { if (btn) btn.innerHTML = '&#128196; PDF'; return; }
            _buildPdf(data, athleteName);
            if (btn) btn.innerHTML = '&#128196; PDF';
        })
        .catch(function() { if (btn) btn.innerHTML = '&#128196; PDF'; });
    }

    function _buildPdf(data, name) {
        var i = data.identite || {};
        var records = data.records || [];
        var clubs = data.clubs || [];
        var progressions = data.progressions || [];
        var medailles = data.medailles || [];

        var html = '';
        html += '<div style="font-family:Arial,sans-serif;max-width:800px;margin:0 auto;padding:40px;color:#222;">';
        html += '<h1 style="text-align:center;color:#1a1a2e;border-bottom:3px solid #ec4899;padding-bottom:12px;">' + (i.nom_complet || name) + '</h1>';

        // Identite
        html += '<table style="width:100%;margin:20px 0;border-collapse:collapse;">';
        if (i.sexe) html += '<tr><td style="padding:6px;font-weight:bold;width:150px;">Sexe</td><td style="padding:6px;">' + (i.sexe === 'M' ? 'Homme' : 'Femme') + '</td></tr>';
        if (i.categorie) html += '<tr><td style="padding:6px;font-weight:bold;">Categorie</td><td style="padding:6px;">' + i.categorie + '</td></tr>';
        if (i.nationalite) html += '<tr><td style="padding:6px;font-weight:bold;">Nationalite</td><td style="padding:6px;">' + i.nationalite + '</td></tr>';
        if (i.date_naissance) html += '<tr><td style="padding:6px;font-weight:bold;">Naissance</td><td style="padding:6px;">' + i.date_naissance + '</td></tr>';
        if (i.lieu_naissance) html += '<tr><td style="padding:6px;font-weight:bold;">Lieu</td><td style="padding:6px;">' + i.lieu_naissance + '</td></tr>';
        if (i.taille_cm) html += '<tr><td style="padding:6px;font-weight:bold;">Taille</td><td style="padding:6px;">' + i.taille_cm + ' cm</td></tr>';
        if (i.poids_kg) html += '<tr><td style="padding:6px;font-weight:bold;">Poids</td><td style="padding:6px;">' + i.poids_kg + ' kg</td></tr>';
        if (i.licence) html += '<tr><td style="padding:6px;font-weight:bold;">Licence</td><td style="padding:6px;">' + i.licence + '</td></tr>';
        html += '</table>';

        // Clubs
        if (clubs.length) {
            html += '<h2 style="color:#8b5cf6;margin-top:30px;">Clubs</h2>';
            html += '<ul>';
            clubs.forEach(function(c) { html += '<li>' + c.nom_club + ' (' + c.annee_debut + '-' + c.annee_fin + ')</li>'; });
            html += '</ul>';
        }

        // Medailles
        if (medailles.length) {
            html += '<h2 style="color:#f59e0b;margin-top:30px;">Medailles (' + medailles.length + ')</h2>';
            html += '<table style="width:100%;border-collapse:collapse;"><tr style="background:#f0f0f0;"><th style="padding:6px;text-align:left;">Type</th><th style="padding:6px;text-align:left;">Epreuve</th><th style="padding:6px;text-align:left;">Competition</th><th style="padding:6px;text-align:left;">Annee</th></tr>';
            medailles.forEach(function(m) {
                html += '<tr><td style="padding:6px;border-bottom:1px solid #eee;">' + m.type + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (m.epreuve||'') + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (m.competition||'') + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (m.annee||'') + '</td></tr>';
            });
            html += '</table>';
        }

        // Records
        if (records.length) {
            html += '<h2 style="color:#ec4899;margin-top:30px;">Records personnels (' + records.length + ')</h2>';
            html += '<table style="width:100%;border-collapse:collapse;"><tr style="background:#f0f0f0;"><th style="padding:6px;text-align:left;">Epreuve</th><th style="padding:6px;text-align:left;">Performance</th><th style="padding:6px;text-align:left;">Date</th><th style="padding:6px;text-align:left;">Lieu</th></tr>';
            records.forEach(function(r) {
                html += '<tr><td style="padding:6px;border-bottom:1px solid #eee;">' + r.epreuve + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (r.performance_brut||r.performance||'') + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (r.date||'') + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (r.lieu||'') + '</td></tr>';
            });
            html += '</table>';
        }

        // Progressions (top 20)
        if (progressions.length) {
            var top = progressions.slice(0, 20);
            html += '<h2 style="color:#3b82f6;margin-top:30px;">Progressions (top ' + top.length + '/' + progressions.length + ')</h2>';
            html += '<table style="width:100%;border-collapse:collapse;"><tr style="background:#f0f0f0;"><th style="padding:6px;text-align:left;">Epreuve</th><th style="padding:6px;text-align:left;">Performance</th><th style="padding:6px;text-align:left;">Annee</th><th style="padding:6px;text-align:left;">Lieu</th></tr>';
            top.forEach(function(p) {
                html += '<tr><td style="padding:6px;border-bottom:1px solid #eee;">' + p.epreuve + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (p.performance_brut||'') + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + p.annee + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (p.lieu||'') + '</td></tr>';
            });
            html += '</table>';
        }

        html += '<p style="text-align:center;color:#999;margin-top:40px;font-size:12px;">Genere par Bokonzi — bokonzi.com</p>';
        html += '</div>';

        // Ouvrir dans une nouvelle fenetre pour imprimer en PDF
        var w = window.open('', '_blank');
        w.document.write('<!DOCTYPE html><html><head><title>' + (i.nom_complet || name) + ' — Bokonzi</title></head><body>' + html + '<script>setTimeout(function(){window.print();},500);<\/script></body></html>');
        w.document.close();
    }

    // ========== NEWSLETTER ==========
    window.subscribeNewsletter = function() {
        var email = document.getElementById('nlEmail').value.trim();
        if (!email || email.indexOf('@') === -1 || email.indexOf('.') === -1) {
            document.getElementById('nlEmail').style.borderColor = '#f85149';
            return;
        }
        localStorage.setItem('bk_follow_email', email);
        localStorage.setItem('bk_nl_done', '1');

        var btn = document.getElementById('nlBtn');
        btn.textContent = '...';

        fetch(BASE_API + '/subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, source: 'newsletter' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('nlEmail').style.display = 'none';
            btn.style.display = 'none';
            var bar = document.getElementById('newsletterBar');
            var ok = document.createElement('span');
            ok.className = 'nl-ok';
            ok.textContent = 'Inscrit ! Merci.';
            bar.insertBefore(ok, bar.querySelector('.nl-close'));
            setTimeout(function() { closeNewsletter(); }, 3000);
        })
        .catch(function() { btn.textContent = "S'inscrire"; });
    };

    window.closeNewsletter = function() {
        document.getElementById('newsletterBar').classList.remove('active');
        localStorage.setItem('bk_nl_closed', '1');
    };

    document.getElementById('nlEmail').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') subscribeNewsletter();
    });

    // Afficher la banniere apres 30s OU scroll 50%, sauf si deja fermee/inscrit
    if (!localStorage.getItem('bk_nl_closed') && !localStorage.getItem('bk_nl_done')) {
        var _nlShown = false;
        function _showNl() {
            if (_nlShown) return;
            _nlShown = true;
            document.getElementById('newsletterBar').classList.add('active');
        }
        setTimeout(_showNl, 30000);
        window.addEventListener('scroll', function() {
            var pct = (window.scrollY + window.innerHeight) / document.documentElement.scrollHeight;
            if (pct > 0.5) _showNl();
        }, { passive: true });
    }
})();
</script>

<footer style="border-top:1px solid #1e2a3a;margin-top:60px;padding:40px 20px 30px;color:#5a6580;font-size:13px;">
<div style="max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;gap:30px;justify-content:space-between;">
    <div>
        <strong style="color:#c9d1d9;font-size:15px;">Bokonzi</strong>
        <p style="margin:8px 0 0;max-width:300px;line-height:1.5;">Base de données complète de l'athlétisme français : athlètes, clubs, épreuves, records et classements.</p>
    </div>
    <div>
        <strong style="color:#8b949e;">Explorer</strong>
        <nav style="display:flex;flex-direction:column;gap:6px;margin-top:8px;" aria-label="Footer navigation">
            <a href="<?= $_canonBase ?>/" style="color:#5a6580;text-decoration:none;">Accueil</a>
            <a href="<?= $_canonBase ?>/index.php?page=athletes" style="color:#5a6580;text-decoration:none;">Tous les athlètes</a>
            <a href="<?= $_canonBase ?>/index.php?page=recherche" style="color:#5a6580;text-decoration:none;">Recherche avancée</a>
            <a href="<?= $_canonBase ?>/index.php?page=clubs" style="color:#5a6580;text-decoration:none;">Clubs</a>
        </nav>
    </div>
    <div>
        <strong style="color:#8b949e;">Données</strong>
        <nav style="display:flex;flex-direction:column;gap:6px;margin-top:8px;">
            <a href="<?= $_canonBase ?>/index.php?page=epreuves" style="color:#5a6580;text-decoration:none;">Épreuves</a>
            <a href="<?= $_canonBase ?>/index.php?page=villes" style="color:#5a6580;text-decoration:none;">Villes</a>
            <a href="<?= $_canonBase ?>/pages/classement.php" style="color:#5a6580;text-decoration:none;">Classement</a>
        </nav>
    </div>
    <div>
        <strong style="color:#8b949e;">Contact</strong>
        <div style="margin-top:8px;">
            <button id="footerContactBtn" onclick="document.getElementById('footerContactForm').style.display='block';this.style.display='none';" style="background:#1e2a3a;border:1px solid #2d3a4a;color:#c9d1d9;font-size:13px;padding:8px 18px;border-radius:8px;cursor:pointer;">Nous contacter</button>
            <div id="footerContactForm" style="display:none;max-width:260px;">
                <input type="text" id="fcNom" maxlength="100" placeholder="Nom (optionnel)" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #1e2a3a;background:#0d1117;color:#c9d1d9;font-size:13px;margin-bottom:6px;">
                <input type="email" id="fcEmail" maxlength="200" placeholder="Email (optionnel)" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #1e2a3a;background:#0d1117;color:#c9d1d9;font-size:13px;margin-bottom:6px;">
                <textarea id="fcMsg" maxlength="2000" placeholder="Votre message..." style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #1e2a3a;background:#0d1117;color:#c9d1d9;font-size:13px;font-family:inherit;resize:vertical;min-height:70px;margin-bottom:6px;"></textarea>
                <button onclick="_footerContact()" style="width:100%;background:#6c5ce7;border:none;color:#fff;font-size:13px;font-weight:700;padding:9px;border-radius:8px;cursor:pointer;">Envoyer</button>
                <div id="fcStatus" style="font-size:12px;margin-top:6px;"></div>
            </div>
        </div>
    </div>
</div>
<div style="text-align:center;margin-top:24px;padding-top:16px;border-top:1px solid #1e2a3a;">
    <p>&copy; <?= date('Y') ?> Bokonzi — Base de données athlétisme français</p>
</div>
</footer>
<script>
function _footerContact(){
    var msg=document.getElementById('fcMsg').value.trim();
    if(!msg){document.getElementById('fcStatus').innerHTML='<span style="color:#ef4444">Ecrivez un message.</span>';return;}
    var btn=event.target;btn.disabled=true;btn.textContent='Envoi...';
    fetch('<?= $_canonBase ?>/api/contact.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({nom:document.getElementById('fcNom').value.trim(),email:document.getElementById('fcEmail').value.trim(),message:msg})}).then(function(r){return r.json()}).then(function(d){
        if(d.success){document.getElementById('footerContactForm').innerHTML='<p style="color:#10b981;font-size:13px;font-weight:600;margin-top:8px;">&#10003; Message envoye !</p>';}
        else{document.getElementById('fcStatus').innerHTML='<span style="color:#ef4444">'+(d.error||'Erreur')+'</span>';btn.disabled=false;btn.textContent='Envoyer';}
    }).catch(function(){document.getElementById('fcStatus').innerHTML='<span style="color:#ef4444">Erreur de connexion.</span>';btn.disabled=false;btn.textContent='Envoyer';});
}
</script>
<?php if (isset($_GET['welcome']) && $_GET['welcome'] === '1'): ?>
<div id="welcomeToast" style="position:fixed;top:70px;left:50%;transform:translateX(-50%);z-index:99999;background:linear-gradient(135deg,#6c5ce7,#5541d0);color:#fff;padding:20px 32px;border-radius:16px;box-shadow:0 8px 32px rgba(108,92,231,.4);font-family:Arial,sans-serif;text-align:center;max-width:420px;width:90%;animation:welcomeSlide .5s ease-out;">
    <div style="font-size:32px;margin-bottom:8px;">&#127881;</div>
    <div style="font-size:18px;font-weight:700;margin-bottom:6px;">Bienvenue sur Bokonzi !</div>
    <div style="font-size:14px;color:#e0d8ff;line-height:1.5;">Votre compte a ete cree avec succes. Explorez les athletes, clubs et records de l'athletisme francais.</div>
    <button onclick="this.parentElement.remove()" style="margin-top:14px;background:#fff3;border:1px solid #fff5;color:#fff;padding:8px 20px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;">C'est parti !</button>
</div>
<style>
@keyframes welcomeSlide { from { opacity:0; transform:translateX(-50%) translateY(-30px); } to { opacity:1; transform:translateX(-50%) translateY(0); } }
</style>
<script>setTimeout(function(){ var t=document.getElementById('welcomeToast'); if(t) t.style.transition='opacity .5s', t.style.opacity='0', setTimeout(function(){t.remove()},500); }, 8000);</script>
<?php endif; ?>
</body>
</html>
