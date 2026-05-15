<?php
/**
 * api/billing_portal.php — Lien vers le portail client Stripe
 *
 * POST (connexion requise) → { success: true, url: "https://billing.stripe.com/..." }
 *
 * Le portail Stripe permet à l'abonné de gérer lui-même son abonnement :
 * changer de carte, voir ses factures, mettre à niveau, ou résilier.
 *
 * Pré-requis : à activer une fois dans le dashboard Stripe
 *   → Settings → Billing → Customer portal → « Activate ».
 */

require_once __DIR__ . '/config.php';            // $conn, getCurrentUser(), jsonResponse()
require_once __DIR__ . '/../core/paths.php';     // BK_URL()
require_once __DIR__ . '/../core/stripe_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Méthode non autorisée'], 405);
}

$user = getCurrentUser($conn);
if (!$user) {
    jsonResponse(['success' => false, 'error' => 'Connexion requise', 'need_login' => true], 401);
}

if (!bkStripeConfigured()) {
    jsonResponse(['success' => false, 'error' => 'Paiement non encore activé', 'not_configured' => true], 503);
}

// Récupère le client Stripe de l'utilisateur
$idUser = (int)$user['id_user'];
$stmt = $conn->prepare("SELECT stripe_customer_id FROM users WHERE id_user = ? LIMIT 1");
$stmt->bind_param("i", $idUser);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$customerId = $row['stripe_customer_id'] ?? '';
if ($customerId === '' || $customerId === null) {
    jsonResponse([
        'success' => false,
        'error'   => 'Aucun abonnement à gérer pour le moment.',
        'no_customer' => true,
    ], 404);
}

$session = stripeRequest('POST', 'billing_portal/sessions', [
    'customer'   => $customerId,
    'return_url' => BK_URL('/tarifs'),
]);

if ($session['error'] || empty($session['body']['url'])) {
    jsonResponse([
        'success' => false,
        'error'   => 'Impossible d\'ouvrir le portail : ' . ($session['error'] ?? 'erreur Stripe'),
    ], 502);
}

jsonResponse(['success' => true, 'url' => $session['body']['url']]);
