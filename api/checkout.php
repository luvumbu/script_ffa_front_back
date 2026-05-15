<?php
/**
 * api/checkout.php — Crée une session de paiement Stripe Checkout (abonnement)
 *
 * POST (JSON ou form) : { plan: bronze|argent|or|platine, period: month|year }
 * Réponse : { success: true, url: "https://checkout.stripe.com/..." }
 *
 * Le front redirige vers cette URL. Google Pay / Apple Pay / CB s'affichent
 * automatiquement sur la page Stripe selon le navigateur du visiteur.
 *
 * L'utilisateur DOIT être connecté (cookie bk_token).
 */

require_once __DIR__ . '/config.php';            // $conn, getCurrentUser(), jsonResponse(), headers JSON
require_once __DIR__ . '/../core/paths.php';     // BK_URL()
require_once __DIR__ . '/../core/stripe_config.php';

// ── Méthode ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Méthode non autorisée'], 405);
}

// ── Authentification ─────────────────────────────────────────────────────
$user = getCurrentUser($conn);
if (!$user) {
    jsonResponse([
        'success'  => false,
        'error'    => 'Connexion requise',
        'need_login' => true,
        'login_url'  => BK_URL('/login.php'),
    ], 401);
}

// ── Stripe configuré ? ───────────────────────────────────────────────────
if (!bkStripeConfigured()) {
    jsonResponse([
        'success' => false,
        'error'   => 'Le paiement n\'est pas encore activé. Reviens très bientôt !',
        'not_configured' => true,
    ], 503);
}

// ── Lecture des paramètres (JSON body ou $_POST) ─────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = [];
$plan   = strtolower(trim($body['plan']   ?? $_POST['plan']   ?? ''));
$period = strtolower(trim($body['period'] ?? $_POST['period'] ?? 'month'));

if (!isset($BK_PLANS[$plan])) {
    jsonResponse(['success' => false, 'error' => 'Offre inconnue'], 400);
}
if (!in_array($period, ['month', 'year'], true)) {
    $period = 'month';
}

$priceId = $period === 'year'
    ? $BK_PLANS[$plan]['stripe_price_year']
    : $BK_PLANS[$plan]['stripe_price_month'];

if (strpos($priceId, 'A_REMPLIR') !== false) {
    jsonResponse([
        'success' => false,
        'error'   => 'Cette offre n\'est pas encore disponible à l\'achat.',
        'not_configured' => true,
    ], 503);
}

// ── Récupère ou crée le client Stripe lié à cet utilisateur ──────────────
$idUser = (int)$user['id_user'];
$customerId = null;

$stmt = $conn->prepare("SELECT stripe_customer_id FROM users WHERE id_user = ? LIMIT 1");
$stmt->bind_param("i", $idUser);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($row && !empty($row['stripe_customer_id'])) {
    $customerId = $row['stripe_customer_id'];
}

if (!$customerId) {
    $fullName = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
    $create = stripeRequest('POST', 'customers', [
        'email'    => $user['email'],
        'name'     => $fullName !== '' ? $fullName : $user['email'],
        'metadata' => ['id_user' => $idUser],
    ]);
    if ($create['error'] || empty($create['body']['id'])) {
        jsonResponse([
            'success' => false,
            'error'   => 'Impossible de créer le compte de facturation : ' . ($create['error'] ?? 'erreur Stripe'),
        ], 502);
    }
    $customerId = $create['body']['id'];

    // On mémorise le customer Stripe sur l'utilisateur
    $upd = $conn->prepare("UPDATE users SET stripe_customer_id = ? WHERE id_user = ?");
    $upd->bind_param("si", $customerId, $idUser);
    $upd->execute();
    $upd->close();
}

// ── Crée la session Checkout (mode abonnement) ───────────────────────────
$session = stripeRequest('POST', 'checkout/sessions', [
    'mode'        => 'subscription',
    'customer'    => $customerId,
    'client_reference_id' => (string)$idUser,
    'line_items'  => [
        ['price' => $priceId, 'quantity' => 1],
    ],
    'subscription_data' => [
        'metadata' => ['id_user' => $idUser, 'plan' => $plan],
    ],
    'allow_promotion_codes' => 'true',
    'billing_address_collection' => 'auto',
    'locale'      => 'fr',
    'success_url' => BK_URL('/tarifs') . '?checkout=success&session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'  => BK_URL('/tarifs') . '?checkout=cancel',
]);

if ($session['error'] || empty($session['body']['url'])) {
    jsonResponse([
        'success' => false,
        'error'   => 'Impossible de démarrer le paiement : ' . ($session['error'] ?? 'erreur Stripe'),
    ], 502);
}

jsonResponse([
    'success' => true,
    'url'     => $session['body']['url'],
]);
