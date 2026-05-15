<?php
/**
 * core/stripe_config.php — Configuration Stripe + catalogue des offres
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │  À REMPLIR PAR TOI (depuis le dashboard Stripe)                      │
 * │  1. Les 3 clés ci-dessous (test en local, live en prod)             │
 * │  2. Les 8 identifiants de prix (price_...) dans $BK_PLANS            │
 * │     => crée d'abord les 4 produits + 8 prix (mensuel/annuel) Stripe  │
 * │  Tant que ce n'est pas rempli : la page tarifs et la détection       │
 * │  d'abonnement fonctionnent, mais le paiement réel est désactivé.     │
 * └─────────────────────────────────────────────────────────────────────┘
 */

require_once __DIR__ . '/paths.php';

// ── Détection environnement : clés TEST en local, clés LIVE en prod ──
if (BK_IS_LOCAL) {
    // ----- CLÉS DE TEST (mode local) -----
    define('STRIPE_PUBLISHABLE_KEY', 'pk_test_A_REMPLIR');
    define('STRIPE_SECRET_KEY',      'sk_test_A_REMPLIR');
    define('STRIPE_WEBHOOK_SECRET',  'whsec_test_A_REMPLIR');
} else {
    // ----- CLÉS LIVE (production bokonzi.com) -----
    define('STRIPE_PUBLISHABLE_KEY', 'pk_live_A_REMPLIR');
    define('STRIPE_SECRET_KEY',      'sk_live_A_REMPLIR');
    define('STRIPE_WEBHOOK_SECRET',  'whsec_live_A_REMPLIR');
}

// Version d'API Stripe figée (évite les surprises lors des mises à jour Stripe)
define('STRIPE_API_VERSION', '2024-06-20');

/**
 * Catalogue des offres BOKONZI.
 * - rank          : niveau hiérarchique (sert à comparer les plans)
 * - search_limit  : recherches/jour autorisées (-1 = illimité)
 * - price_month / price_year : montants en CENTIMES (affichage + cohérence)
 * - stripe_price_* : identifiants de prix Stripe → À REMPLIR
 * - features      : ce que CE palier ajoute par rapport au précédent
 */
$BK_PLANS = [
    'bronze' => [
        'key'              => 'bronze',
        'name'             => 'Bronze',
        'product_name'     => 'BOKONZI Bronze',
        'tagline'          => 'Pour les passionnés',
        'rank'             => 1,
        'color'            => '#cd7f32',
        'search_limit'     => 2000,
        'price_month'      => 199,
        'price_year'       => 1990,
        'stripe_price_month' => 'price_A_REMPLIR_bronze_mois',
        'stripe_price_year'  => 'price_A_REMPLIR_bronze_an',
        'payment_link'       => 'https://buy.stripe.com/dRm9AU7JEeMt7FVcpYdnW0H',
        'features'         => [
            '2 000 recherches par jour',
            'Suivi illimité d\'athlètes & de clubs',
            'Alertes à chaque nouvelle performance',
        ],
    ],
    'argent' => [
        'key'              => 'argent',
        'name'             => 'Argent',
        'product_name'     => 'BOKONZI Argent',
        'tagline'          => 'Pour les athlètes & coachs',
        'rank'             => 2,
        'color'            => '#9ca3af',
        'search_limit'     => -1,
        'price_month'      => 399,
        'price_year'       => 3990,
        'stripe_price_month' => 'price_A_REMPLIR_argent_mois',
        'stripe_price_year'  => 'price_A_REMPLIR_argent_an',
        'payment_link'       => 'https://buy.stripe.com/14A6oI8NI6fXaS73TsdnW0I',
        'features'         => [
            'Recherches illimitées',
            'Comparateur avancé',
            'Statistiques détaillées',
        ],
    ],
    'or' => [
        'key'              => 'or',
        'name'             => 'Or',
        'product_name'     => 'BOKONZI Or',
        'tagline'          => 'Pour aller au bout de l\'analyse',
        'rank'             => 3,
        'color'            => '#f59e0b',
        'search_limit'     => -1,
        'price_month'      => 699,
        'price_year'       => 6990,
        'stripe_price_month' => 'price_A_REMPLIR_or_mois',
        'stripe_price_year'  => 'price_A_REMPLIR_or_an',
        'payment_link'       => 'https://buy.stripe.com/cNi28s1lg33LbWb9dMdnW0J',
        'features'         => [
            'Export PDF des fiches profil',
            'Export PDF des bilans complets',
        ],
    ],
    'platine' => [
        'key'              => 'platine',
        'name'             => 'Platine',
        'product_name'     => 'BOKONZI Platine',
        'tagline'          => 'Pour les clubs & structures',
        'rank'             => 4,
        'color'            => '#6c5ce7',
        'search_limit'     => -1,
        'price_month'      => 1299,
        'price_year'       => 12990,
        'stripe_price_month' => 'price_A_REMPLIR_platine_mois',
        'stripe_price_year'  => 'price_A_REMPLIR_platine_an',
        'payment_link'       => 'https://buy.stripe.com/fZufZifc65bTgcr75EdnW0K',
        'features'         => [
            'Rapports de club complets',
            'Suivi multi-athlètes pour structures',
        ],
    ],
];

/**
 * Capacités débloquées et le rang MINIMUM requis pour chacune.
 * userCan() (core/subscription.php) compare le rang du plan de l'user à ça.
 */
$BK_CAPABILITIES = [
    'search_extended'   => 1, // quota de recherche augmenté (Bronze+)
    'follow_unlimited'  => 1, // suivi illimité athlètes/clubs
    'alerts'            => 1, // alertes nouvelles perfs
    'search_unlimited'  => 2, // recherches illimitées (Argent+)
    'compare_advanced'  => 2, // comparateur avancé
    'stats_advanced'    => 2, // statistiques détaillées
    'pdf_export'        => 3, // export PDF (Or+)
    'club_reports'      => 4, // rapports de club (Platine+)
    'multi_athlete'     => 4, // suivi multi-athlètes (Platine+)
];

/**
 * Statuts Stripe considérés comme « abonnement actif ».
 * (un abonnement annulé mais encore dans sa période payée reste 'active'
 *  jusqu'à la fin de période — Stripe gère ça via current_period_end.)
 */
$BK_ACTIVE_STATUSES = ['active', 'trialing', 'past_due'];

/**
 * Indique si Stripe est réellement configuré (clés + au moins un price_id remplis).
 * Permet à la page tarifs / aux endpoints de se comporter proprement avant
 * que tu aies collé tes vraies valeurs.
 */
function bkStripeConfigured() {
    if (strpos(STRIPE_SECRET_KEY, 'A_REMPLIR') !== false) {
        return false;
    }
    global $BK_PLANS;
    foreach ($BK_PLANS as $p) {
        if (strpos($p['stripe_price_month'], 'A_REMPLIR') !== false) {
            return false;
        }
    }
    return true;
}

/**
 * Retrouve le plan correspondant à un identifiant de prix Stripe.
 * @return array|null  ['plan' => 'bronze', 'period' => 'month'|'year'] ou null
 */
function bkPlanFromPriceId($priceId) {
    global $BK_PLANS;
    foreach ($BK_PLANS as $key => $p) {
        if ($p['stripe_price_month'] === $priceId) return ['plan' => $key, 'period' => 'month'];
        if ($p['stripe_price_year']  === $priceId) return ['plan' => $key, 'period' => 'year'];
    }
    return null;
}

/**
 * Retrouve le plan à partir du MONTANT (en centimes) — utile avec les
 * Payment Links, où l'on ne connaît pas l'identifiant de prix Stripe.
 * @return array|null  ['plan' => 'bronze', 'period' => 'month'|'year'] ou null
 */
function bkPlanFromAmount($cents) {
    global $BK_PLANS;
    $cents = (int)$cents;
    foreach ($BK_PLANS as $key => $p) {
        if ((int)$p['price_month'] === $cents) return ['plan' => $key, 'period' => 'month'];
        if ((int)$p['price_year']  === $cents) return ['plan' => $key, 'period' => 'year'];
    }
    return null;
}

/**
 * Appel bas niveau à l'API Stripe (curl, sans dépendance / sans Composer).
 *
 * @param string $method  GET | POST | DELETE
 * @param string $path    ex: 'checkout/sessions', 'customers'
 * @param array  $params  paramètres (tableaux imbriqués gérés par http_build_query)
 * @return array          ['code' => int, 'body' => array, 'error' => string|null]
 */
function stripeRequest($method, $path, $params = []) {
    if (strpos(STRIPE_SECRET_KEY, 'A_REMPLIR') !== false) {
        return ['code' => 0, 'body' => [], 'error' => 'Stripe non configuré (clé secrète manquante)'];
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    $ch  = curl_init();

    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Stripe-Version: ' . STRIPE_API_VERSION,
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ];

    if ($method === 'GET') {
        if (!empty($params)) {
            $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
        }
    } else {
        // http_build_query produit bien le format imbriqué attendu par Stripe :
        // line_items[0][price]=price_xxx&line_items[0][quantity]=1
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    }

    curl_setopt_array($ch, $opts);
    $raw   = curl_exec($ch);
    $errNo = curl_errno($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errNo) {
        return ['code' => 0, 'body' => [], 'error' => 'curl #' . $errNo];
    }

    $body = json_decode($raw, true);
    if (!is_array($body)) {
        return ['code' => $code, 'body' => [], 'error' => 'Réponse Stripe illisible'];
    }

    $error = null;
    if ($code >= 400) {
        $error = $body['error']['message'] ?? ('Erreur Stripe HTTP ' . $code);
    }

    return ['code' => $code, 'body' => $body, 'error' => $error];
}
