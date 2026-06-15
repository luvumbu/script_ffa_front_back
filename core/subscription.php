<?php
/**
 * core/subscription.php — Détection de l'état d'abonnement
 *
 * C'est LE point d'entrée pour savoir « est-ce que la personne a payé ? ».
 * La table `subscriptions` est la source de vérité locale : elle est tenue
 * à jour automatiquement par le webhook Stripe (api/stripe_webhook.php).
 *
 * Usage typique :
 *   require_once __DIR__ . '/subscription.php';
 *   if (hasActiveSubscription($conn, $user['id_user'])) { ... }
 *   if (userCan($conn, $user['id_user'], 'pdf_export')) { ... }
 */

require_once __DIR__ . '/stripe_config.php'; // $BK_PLANS, $BK_CAPABILITIES, $BK_ACTIVE_STATUSES
require_once __DIR__ . '/test_mode.php';     // bkTestRole() — « aperçu en tant que » super admin

// Limite de recherches/jour par défaut pour un utilisateur connecté SANS abonnement.
// Si api/search.php est chargé, sa constante BK_SEARCH_LIMIT_LOGGED prime (voir getUserSearchLimit).
if (!defined('BK_FREE_LOGGED_SEARCH_LIMIT')) {
    define('BK_FREE_LOGGED_SEARCH_LIMIT', 5);
}

/**
 * Récupère la ligne d'abonnement brute d'un utilisateur (peu importe le statut).
 * Résultat mis en cache pour la durée de la requête (évite les SELECT répétés).
 *
 * @return array|null
 */
function getUserSubscription($conn, $idUser, $fresh = false) {
    static $cache = [];
    static $tableMissing = null; // null = inconnu, true/false = testé une fois
    $idUser = (int)$idUser;
    if ($idUser <= 0) return null;

    // Mode test super admin : on renvoie un abonnement simulé (ou aucun) pour
    // que tout le site (paywall, capacités, badge nav) réagisse en conséquence.
    if (function_exists('bkTestRole')) {
        $tr = bkTestRole();
        if ($tr === 'visitor' || $tr === 'free') return null;
        if (in_array($tr, ['bronze', 'argent', 'or', 'platine'], true)) {
            return [
                'id_subscription'        => 0,
                'id_user'                => $idUser,
                'stripe_customer_id'     => '',
                'stripe_subscription_id' => 'TEST',
                'plan'                   => $tr,
                'status'                 => 'active',
                'billing_period'         => 'test',
                'current_period_end'     => null,
                'cancel_at_period_end'   => 0,
                'created_at'             => null,
                'updated_at'             => null,
            ];
        }
    }

    if (!$fresh && array_key_exists($idUser, $cache)) return $cache[$idUser];

    // Si la table `subscriptions` n'existe pas (migration Stripe non jouée),
    // on considère simplement qu'aucun utilisateur n'a d'abonnement, au lieu
    // de faire planter tout le site (nav.php, index.php...).
    if ($tableMissing === null) {
        try {
            $chk = $conn->query("SHOW TABLES LIKE 'subscriptions'");
            $tableMissing = !($chk && $chk->num_rows > 0);
        } catch (\Throwable $e) {
            $tableMissing = true;
        }
    }
    if ($tableMissing) {
        $cache[$idUser] = null;
        return null;
    }

    $sub = null;
    try {
        $stmt = $conn->prepare(
            "SELECT id_subscription, id_user, stripe_customer_id, stripe_subscription_id,
                    plan, status, billing_period, current_period_end, cancel_at_period_end,
                    created_at, updated_at
             FROM subscriptions WHERE id_user = ? LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param("i", $idUser);
            $stmt->execute();
            $res = $stmt->get_result();
            $sub = $res ? $res->fetch_assoc() : null;
            $stmt->close();
        }
    } catch (\Throwable $e) {
        // Table disparue entre-temps ou autre souci SQL → pas d'abonnement
        $tableMissing = true;
        $sub = null;
    }

    $cache[$idUser] = $sub ?: null;
    return $cache[$idUser];
}

/**
 * L'utilisateur a-t-il un abonnement actif (= a bien payé, accès débloqué) ?
 *
 * Actif = statut Stripe « vivant » ET période payée non expirée.
 */
function hasActiveSubscription($conn, $idUser) {
    global $BK_ACTIVE_STATUSES;
    $sub = getUserSubscription($conn, $idUser);
    if (!$sub) return false;

    if (!in_array($sub['status'], $BK_ACTIVE_STATUSES, true)) {
        return false;
    }
    // current_period_end NULL = pas encore renseigné par le webhook → on tolère
    if (!empty($sub['current_period_end'])) {
        if (strtotime($sub['current_period_end']) < time()) {
            return false;
        }
    }
    return true;
}

/**
 * Clé du plan actif de l'utilisateur ('bronze'|'argent'|'or'|'platine') ou null.
 */
function getUserPlan($conn, $idUser) {
    if (!hasActiveSubscription($conn, $idUser)) return null;
    $sub = getUserSubscription($conn, $idUser);
    global $BK_PLANS;
    $plan = $sub['plan'] ?? '';
    return isset($BK_PLANS[$plan]) ? $plan : null;
}

/**
 * Rang du plan actif (1=Bronze … 4=Platine). 0 = aucun abonnement actif.
 */
function getUserPlanRank($conn, $idUser) {
    $plan = getUserPlan($conn, $idUser);
    if (!$plan) return 0;
    global $BK_PLANS;
    return (int)($BK_PLANS[$plan]['rank'] ?? 0);
}

/**
 * Infos d'affichage du plan actif (nom, couleur, etc.) ou null.
 */
function getUserPlanInfo($conn, $idUser) {
    $plan = getUserPlan($conn, $idUser);
    if (!$plan) return null;
    global $BK_PLANS;
    return $BK_PLANS[$plan];
}

/**
 * Limite de recherches/jour applicable à un utilisateur CONNECTÉ.
 *  - Bronze         : 2 000/jour
 *  - Argent/Or/Platine : illimité (-1)
 *  - Connecté sans abo : 5/jour
 * (Les visiteurs anonymes sont gérés directement dans api/search.php.)
 *
 * @return int  nombre de recherches/jour, ou -1 pour illimité
 */
function getUserSearchLimit($conn, $idUser) {
    $info = getUserPlanInfo($conn, $idUser);
    if ($info) {
        return (int)$info['search_limit']; // 2000 (bronze) ou -1 (argent+)
    }
    // Pas d'abonnement actif : on s'aligne sur la limite « connecté » de search.php si dispo
    if (defined('BK_SEARCH_LIMIT_LOGGED')) {
        return (int)BK_SEARCH_LIMIT_LOGGED;
    }
    return BK_FREE_LOGGED_SEARCH_LIMIT;
}

/**
 * L'utilisateur a-t-il accès à une capacité donnée ?
 * Capacités possibles : voir $BK_CAPABILITIES dans core/stripe_config.php
 * (search_unlimited, compare_advanced, stats_advanced, pdf_export,
 *  club_reports, multi_athlete, follow_unlimited, alerts, search_extended)
 */
function userCan($conn, $idUser, $capability) {
    global $BK_CAPABILITIES;
    if (!isset($BK_CAPABILITIES[$capability])) {
        return false; // capacité inconnue → refus par sécurité
    }
    $required = (int)$BK_CAPABILITIES[$capability];
    return getUserPlanRank($conn, $idUser) >= $required;
}

/**
 * Petit résumé prêt à afficher (badge nav, page Mon Espace…).
 *
 * @return array {
 *   active: bool, plan: string|null, plan_name: string|null,
 *   color: string|null, status: string|null, period_end: string|null,
 *   cancel_at_period_end: bool
 * }
 */
function getSubscriptionSummary($conn, $idUser) {
    $sub    = getUserSubscription($conn, $idUser);
    $active = hasActiveSubscription($conn, $idUser);
    $info   = $active ? getUserPlanInfo($conn, $idUser) : null;

    return [
        'active'               => $active,
        'plan'                 => $info ? $info['key'] : null,
        'plan_name'            => $info ? $info['name'] : null,
        'color'                => $info ? $info['color'] : null,
        'status'               => $sub ? $sub['status'] : null,
        'period_end'           => $sub ? $sub['current_period_end'] : null,
        'cancel_at_period_end' => $sub ? (bool)$sub['cancel_at_period_end'] : false,
    ];
}

/**
 * Insère / met à jour la ligne `subscriptions` à partir d'un objet « subscription » Stripe.
 * Utilisé par le webhook ET par la synchronisation à la demande (bkSyncFromStripe).
 *
 * @param array       $sub        objet subscription Stripe
 * @param int|null    $idUserHint id_user déjà connu (client_reference_id, sync admin…)
 * @param string|null $emailHint  email du payeur — dernier recours pour rattacher
 * @return int|false  l'id_user rattaché, ou false si aucun utilisateur trouvé
 */
function bkUpsertSubscriptionRow($conn, $sub, $idUserHint = null, $emailHint = null) {
    $stripeSubId = $sub['id'] ?? '';
    $customerId  = $sub['customer'] ?? '';
    $status      = $sub['status'] ?? '';
    $cancelEnd   = !empty($sub['cancel_at_period_end']) ? 1 : 0;
    $periodEnd   = !empty($sub['current_period_end'])
        ? date('Y-m-d H:i:s', (int)$sub['current_period_end']) : null;

    // Plan : via l'identifiant de prix, sinon via le montant (cas des Payment Links)
    $item    = $sub['items']['data'][0] ?? [];
    $priceId = $item['price']['id'] ?? '';
    $amount  = $item['price']['unit_amount'] ?? null;
    $mapped  = $priceId ? bkPlanFromPriceId($priceId) : null;
    if (!$mapped && $amount !== null) $mapped = bkPlanFromAmount((int)$amount);
    $plan    = $mapped['plan']   ?? ($sub['metadata']['plan'] ?? '');
    $period  = $mapped['period'] ?? '';

    // Rattachement à un utilisateur : metadata → hint → customer id → email
    $idUser = (int)($sub['metadata']['id_user'] ?? 0);
    if ($idUser <= 0 && $idUserHint) $idUser = (int)$idUserHint;
    if ($idUser <= 0 && $customerId !== '') {
        $st = $conn->prepare("SELECT id_user FROM users WHERE stripe_customer_id = ? LIMIT 1");
        $st->bind_param("s", $customerId); $st->execute();
        $r = $st->get_result()->fetch_assoc(); $st->close();
        if ($r) $idUser = (int)$r['id_user'];
    }
    if ($idUser <= 0 && $emailHint) {
        $st = $conn->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
        $st->bind_param("s", $emailHint); $st->execute();
        $r = $st->get_result()->fetch_assoc(); $st->close();
        if ($r) $idUser = (int)$r['id_user'];
    }
    if ($idUser <= 0) return false;

    // Mémorise le customer Stripe sur l'utilisateur s'il n'est pas encore connu
    if ($customerId !== '') {
        $st = $conn->prepare("UPDATE users SET stripe_customer_id = ? WHERE id_user = ? AND (stripe_customer_id IS NULL OR stripe_customer_id = '')");
        $st->bind_param("si", $customerId, $idUser); $st->execute(); $st->close();
    }

    // UPSERT : 1 abonnement courant par utilisateur (clé unique uk_sub_user)
    $sql = "INSERT INTO subscriptions
                (id_user, stripe_customer_id, stripe_subscription_id, plan, status,
                 billing_period, current_period_end, cancel_at_period_end)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                stripe_customer_id     = VALUES(stripe_customer_id),
                stripe_subscription_id = VALUES(stripe_subscription_id),
                plan                   = VALUES(plan),
                status                 = VALUES(status),
                billing_period         = VALUES(billing_period),
                current_period_end     = VALUES(current_period_end),
                cancel_at_period_end   = VALUES(cancel_at_period_end)";
    $st = $conn->prepare($sql);
    $st->bind_param("issssssi", $idUser, $customerId, $stripeSubId, $plan, $status, $period, $periodEnd, $cancelEnd);
    $ok = $st->execute(); $st->close();

    // Le cache statique de getUserSubscription doit être rafraîchi
    getUserSubscription($conn, $idUser, true);
    return $ok ? $idUser : false;
}

/**
 * Vérifie le paiement EN INTERROGEANT STRIPE directement, et met à jour la
 * table locale. Sert de filet de sécurité (retour Checkout, action admin) si
 * le webhook n'a pas encore traité l'événement.
 *
 * @return array ['ok'=>bool, 'found'=>bool, 'active'=>bool, 'plan'=>?string, 'message'=>string]
 */
function bkSyncFromStripe($conn, $idUser) {
    $idUser = (int)$idUser;
    $fail = function ($msg, $ok = false) {
        return ['ok' => $ok, 'found' => false, 'active' => false, 'plan' => null, 'message' => $msg];
    };
    if ($idUser <= 0) return $fail('Utilisateur invalide');
    if (!function_exists('stripeRequest') || strpos(STRIPE_SECRET_KEY, 'A_REMPLIR') !== false) {
        return $fail('Stripe non configuré (clé secrète manquante)');
    }

    $st = $conn->prepare("SELECT email, stripe_customer_id FROM users WHERE id_user = ? LIMIT 1");
    $st->bind_param("i", $idUser); $st->execute();
    $u = $st->get_result()->fetch_assoc(); $st->close();
    if (!$u) return $fail('Utilisateur introuvable');

    $customerId = $u['stripe_customer_id'] ?? '';
    $email      = $u['email'] ?? '';

    // Pas de client Stripe connu → on le cherche par email
    if ($customerId === '' || $customerId === null) {
        if ($email === '') return $fail('Aucun email pour la recherche Stripe', true);
        $r = stripeRequest('GET', 'customers', ['email' => $email, 'limit' => 1]);
        if ($r['error']) return $fail('Stripe : ' . $r['error']);
        $cust = $r['body']['data'][0] ?? null;
        if (!$cust) return $fail('Aucun client Stripe pour cet email', true);
        $customerId = $cust['id'];
        $st = $conn->prepare("UPDATE users SET stripe_customer_id = ? WHERE id_user = ?");
        $st->bind_param("si", $customerId, $idUser); $st->execute(); $st->close();
    }

    // Abonnements de ce client
    $r = stripeRequest('GET', 'subscriptions', ['customer' => $customerId, 'status' => 'all', 'limit' => 10]);
    if ($r['error']) return $fail('Stripe : ' . $r['error']);
    $subs = $r['body']['data'] ?? [];
    if (empty($subs)) return $fail('Aucun abonnement Stripe pour ce client', true);

    // Le meilleur : actif/trialing/past_due en priorité, sinon le plus récent
    $best = null;
    foreach ($subs as $s) {
        if (in_array($s['status'] ?? '', ['active', 'trialing', 'past_due'], true)) { $best = $s; break; }
    }
    if (!$best) $best = $subs[0];

    bkUpsertSubscriptionRow($conn, $best, $idUser, $email);

    $active = hasActiveSubscription($conn, $idUser);
    $plan   = getUserPlan($conn, $idUser);
    return [
        'ok'      => true,
        'found'   => true,
        'active'  => $active,
        'plan'    => $plan,
        'message' => $active
            ? ('Abonnement actif : BOKONZI ' . ucfirst((string)$plan))
            : ('Abonnement trouvé mais inactif (statut Stripe : ' . ($best['status'] ?? '?') . ')'),
    ];
}
