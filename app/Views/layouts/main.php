<?php
/**
 * Layout principal — Squelette HTML qui entoure toutes les pages
 *
 * Variables attendues : $seo (array), $page, $baseUrl, $__content
 */
$seo = $seo ?? [];
$seoTitle = $seo['title'] ?? 'Bokonzi — Base de données Athlétisme français';
$seoDesc = $seo['desc'] ?? '';
$seoCanonical = $seo['canonical'] ?? 'https://bokonzi.com/';
$seoNoIndex = $seo['noIndex'] ?? false;
$breadcrumbs = $seo['breadcrumbs'] ?? null;
$canonBase = 'https://bokonzi.com';
$page = $page ?? 'accueil';
$baseUrl = $baseUrl ?? '';
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
    <title><?= $seoTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($seoDesc) ?>">
<?php if ($seoNoIndex): ?>
    <meta name="robots" content="noindex, follow">
<?php endif; ?>
    <link rel="canonical" href="<?= htmlspecialchars($seoCanonical) ?>">
    <meta property="og:title" content="<?= $seoTitle ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($seoCanonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Bokonzi">
    <meta property="og:image" content="<?= $canonBase ?>/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="fr_FR">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $seoTitle ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta name="twitter:image" content="<?= $canonBase ?>/og-image.png">
    <meta name="theme-color" content="#0d1117">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $canonBase ?>/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= $canonBase ?>/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $canonBase ?>/apple-touch-icon.png">
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
        "areaServed": { "@type": "Country", "name": "France" },
        "knowsLanguage": "fr",
        "foundingDate": "2024"
    }
    </script>
<?php if ($breadcrumbs): ?>
    <script type="application/ld+json">
    <?= json_encode($breadcrumbs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>
<?php endif; ?>
    <link rel="stylesheet" href="<?= $baseUrl ?>/dashboard.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/css/components.css">
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
</head>
<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KPNTVXDF"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<?php View::partial('basket', ['baseUrl' => $baseUrl]); ?>

<?php include Application::getInstance()->getRootPath() . '/nav.php'; ?>

<?php View::partial('navigation', ['page' => $page, 'canonBase' => $canonBase]); ?>

<div class="container">
<?= $__content ?>
</div>

<?php View::partial('modals', ['baseUrl' => $baseUrl, 'canonBase' => $canonBase]); ?>

<?php View::partial('footer', ['canonBase' => $canonBase, 'baseUrl' => $baseUrl]); ?>

<!-- JS global : BASE_API en relatif (suit le host courant) -->
<script>var BASE_API = <?= json_encode($baseUrl . '/api') ?>;</script>
<script src="<?= $baseUrl ?>/public/assets/js/utils.js"></script>
<script src="<?= $baseUrl ?>/public/assets/js/club-panel.js"></script>
<script src="<?= $baseUrl ?>/public/assets/js/epreuve-panel.js"></script>
<script src="<?= $baseUrl ?>/public/assets/js/live-search.js"></script>
<script src="<?= $baseUrl ?>/public/assets/js/follow.js"></script>
<script src="<?= $baseUrl ?>/public/assets/js/pdf-newsletter.js"></script>
<script src="<?= $baseUrl ?>/public/assets/js/tracking.js"></script>
<script src="<?= $baseUrl ?>/public/assets/js/footer-contact.js"></script>
</body>
</html>
