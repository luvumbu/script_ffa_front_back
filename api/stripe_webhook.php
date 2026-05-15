<?php
/**
 * api/stripe_webhook.php — Réception des événements Stripe
 *
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │  C'EST LA SOURCE DE VÉRITÉ de « la personne a-t-elle payé ? ».        │
 * │  Stripe appelle cette URL à chaque évènement (paiement, annulation,   │
 * │  échec…). On vérifie la signature, puis on met à jour la table        │
 * │  `subscriptions`. On ne se fie JAMAIS à la simple redirection de      │
 * │  retour côté navigateur.                                              │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * À configurer dans le dashboard Stripe → Developers → Webhooks :
 *   URL    : https://bokonzi.com/api/stripe_webhook.php
 *   Events : checkout.session.completed, customer.subscription.created,
 *            customer.subscription.updated, customer.subscription.deleted,
 *            invoice.payment_failed, invoice.paid
 *   => copie le « Signing secret » (whsec_...) dans core/stripe_config.php
 */

require_once __DIR__ . '/../core/db.php';             // $conn
require_once __DIR__ . '/../core/stripe_config.php';  // STRIPE_WEBHOOK_SECRET, stripeRequest()
require_once __DIR__ . '/../core/subscription.php';   // bkUpsertSubscriptionRow()

// On répond toujours vite et sobrement (texte brut, pas de JSON/CORS).
header('Content-Type: text/plain; charset=utf-8');

/** Termine le script avec un code HTTP + message court. */
function wh_end($code, $msg) {
    global $conn;
    http_response_code($code);
    echo $msg;
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    exit;
}

// ── 1) Récupère le corps brut + l'en-tête de signature ───────────────────
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === '' || $sigHeader === '') {
    wh_end(400, 'Payload ou signature manquant');
}
if (strpos(STRIPE_WEBHOOK_SECRET, 'A_REMPLIR') !== false) {
    // Webhook reçu mais pas encore configuré : on accuse réception sans planter.
    wh_end(200, 'Webhook non configure (ignore)');
}

// ── 2) Vérifie la signature (HMAC-SHA256, anti-rejeu) ────────────────────
$ts = null;
$signatures = [];
foreach (explode(',', $sigHeader) as $part) {
    $kv = explode('=', trim($part), 2);
    if (count($kv) !== 2) continue;
    if ($kv[0] === 't')  $ts = $kv[1];
    if ($kv[0] === 'v1') $signatures[] = $kv[1];
}

if ($ts === null || empty($signatures)) {
    wh_end(400, 'Signature illisible');
}
// Tolérance de 5 min pour bloquer les rejeux
if (abs(time() - (int)$ts) > 300) {
    wh_end(400, 'Horodatage hors tolerance');
}

$expected = hash_hmac('sha256', $ts . '.' . $payload, STRIPE_WEBHOOK_SECRET);
$valid = false;
foreach ($signatures as $sig) {
    if (hash_equals($expected, $sig)) { $valid = true; break; }
}
if (!$valid) {
    wh_end(400, 'Signature invalide');
}

// ── 3) Décode l'évènement ────────────────────────────────────────────────
$event = json_decode($payload, true);
if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
    wh_end(400, 'Evenement illisible');
}
$eventId   = $event['id'];
$eventType = $event['type'];
$object    = $event['data']['object'] ?? [];

// ── 4) Idempotence : un évènement traité une seule fois ──────────────────
$stmt = $conn->prepare("INSERT IGNORE INTO stripe_events (id, type) VALUES (?, ?)");
$stmt->bind_param("ss", $eventId, $eventType);
$stmt->execute();
$alreadySeen = ($stmt->affected_rows === 0);
$stmt->close();
if ($alreadySeen) {
    wh_end(200, 'Deja traite');
}

// ── 5) Traite les évènements qui nous intéressent ────────────────────────
// La mise à jour de la table `subscriptions` est faite par bkUpsertSubscriptionRow()
// (défini dans core/subscription.php — partagé avec la vérification à la demande).
switch ($eventType) {

    case 'checkout.session.completed':
        // L'utilisateur vient de payer (Checkout OU Payment Link).
        $subId     = $object['subscription'] ?? '';
        $idHint    = (int)($object['client_reference_id'] ?? 0);
        $emailHint = $object['customer_details']['email'] ?? ($object['customer_email'] ?? null);
        if ($subId) {
            $resp = stripeRequest('GET', 'subscriptions/' . $subId);
            if (!$resp['error'] && !empty($resp['body']['id'])) {
                bkUpsertSubscriptionRow($conn, $resp['body'], $idHint, $emailHint);
            }
        }
        break;

    case 'customer.subscription.created':
    case 'customer.subscription.updated':
    case 'customer.subscription.deleted':
        // L'objet est directement l'abonnement (statut à jour, y compris 'canceled').
        if (!empty($object['id'])) {
            bkUpsertSubscriptionRow($conn, $object);
        }
        break;

    case 'invoice.payment_failed':
    case 'invoice.paid':
        // Le changement de statut réel arrive via customer.subscription.updated,
        // mais on rafraîchit quand même par sécurité si on a l'abonnement.
        $subId = $object['subscription'] ?? '';
        if ($subId) {
            $resp = stripeRequest('GET', 'subscriptions/' . $subId);
            if (!$resp['error'] && !empty($resp['body']['id'])) {
                bkUpsertSubscriptionRow($conn, $resp['body']);
            }
        }
        break;

    default:
        // Évènement non géré : on accuse réception (Stripe ne réessaiera pas).
        break;
}

wh_end(200, 'OK');
