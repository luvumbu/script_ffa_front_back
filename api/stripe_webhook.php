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
require_once __DIR__ . '/../core/subscription.php';   // bkUpsertSubscriptionRow(), hasActiveSubscription()
require_once __DIR__ . '/../core/emails.php';         // bkSendSubscriptionWelcome()

/**
 * Envoie l'email de bienvenue / remerciement UNIQUEMENT si l'abonnement est
 * réellement payé (statut Stripe strictement 'active' — pas 'trialing' ni
 * 'past_due'). N'est appelé que depuis les événements de paiement encaissé.
 * Anti-doublon géré dans bkSendSubscriptionWelcome() (1 envoi par user+plan).
 */
function wh_welcome_if_active($conn, $idUser) {
    $idUser = (int)$idUser;
    if ($idUser <= 0) return;
    // On lit la ligne fraîche pour vérifier le statut exact.
    $sub = getUserSubscription($conn, $idUser, true);
    if (!$sub || ($sub['status'] ?? '') !== 'active') return;
    // Période payée encore valable ?
    if (!empty($sub['current_period_end']) && strtotime($sub['current_period_end']) < time()) return;
    $r = bkSendSubscriptionWelcome($conn, $idUser, $sub['plan'] ?? null);
    wh_log('EMAIL bienvenue uid=' . $idUser . ' plan=' . ($sub['plan'] ?? '?') . ' -> '
        . (!empty($r['sent']) ? 'ENVOYÉ' : (!empty($r['skipped']) ? 'déjà envoyé' : 'ÉCHEC (' . ($r['reason'] ?? '?') . ')'))
        . ' [' . ($r['to'] ?? '') . ']');
}

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

/**
 * Journalise chaque passage du webhook dans logs/.stripe_webhook.php (protégé
 * par die()). Filet anti « paiement perdu » : même quand le webhook n'est pas
 * encore configuré, on garde une trace lisible (action=webhook_log de
 * remote_check) pour réconcilier les paiements à la main. Rotation simple à 500 Ko.
 */
function wh_log($msg) {
    $f    = __DIR__ . '/../logs/.stripe_webhook.php';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    if (!file_exists($f) || @filesize($f) > 512000) {
        @file_put_contents($f, "<?php die('Acces interdit'); ?>\n" . $line, LOCK_EX);
        return;
    }
    @file_put_contents($f, $line, FILE_APPEND | LOCK_EX);
}

// ── 1) Récupère le corps brut + l'en-tête de signature ───────────────────
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === '' || $sigHeader === '') {
    wh_end(400, 'Payload ou signature manquant');
}
if (strpos(STRIPE_WEBHOOK_SECRET, 'A_REMPLIR') !== false) {
    // Webhook reçu mais pas encore configuré : on accuse réception sans planter,
    // MAIS on journalise l'événement (filet anti paiement perdu) pour pouvoir
    // réconcilier le paiement à la main en attendant la configuration des clés.
    $ev = json_decode($payload, true);
    if (is_array($ev) && !empty($ev['type'])) {
        $obj = $ev['data']['object'] ?? [];
        $em  = $obj['customer_details']['email'] ?? ($obj['customer_email'] ?? ($obj['email'] ?? ''));
        $amt = isset($obj['amount_paid'])  ? number_format($obj['amount_paid']  / 100, 2) . '€'
             : (isset($obj['amount_total']) ? number_format($obj['amount_total'] / 100, 2) . '€' : '');
        wh_log('⚠ WEBHOOK NON CONFIGURÉ — événement reçu et IGNORÉ. type=' . $ev['type']
            . ' email=' . ($em ?: '?') . ' montant=' . ($amt ?: '?')
            . ' → renseigne STRIPE_WEBHOOK_SECRET pour synchroniser automatiquement (paiement à valider à la main en attendant).');
    }
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
    wh_log('✗ Signature INVALIDE (clé STRIPE_WEBHOOK_SECRET incorrecte ?) — événement rejeté.');
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
        // Fin de Checkout / Payment Link. On met à jour la base, MAIS on n'envoie
        // l'email QUE si le paiement est réellement encaissé (payment_status = paid).
        // Un Checkout avec essai gratuit a payment_status = 'no_payment_required'
        // → pas d'email tant qu'aucun euro n'est encaissé.
        $subId     = $object['subscription'] ?? '';
        $idHint    = (int)($object['client_reference_id'] ?? 0);
        $emailHint = $object['customer_details']['email'] ?? ($object['customer_email'] ?? null);
        $isPaid    = (($object['payment_status'] ?? '') === 'paid');
        if ($subId) {
            $resp = stripeRequest('GET', 'subscriptions/' . $subId);
            if (!$resp['error'] && !empty($resp['body']['id'])) {
                $uid = bkUpsertSubscriptionRow($conn, $resp['body'], $idHint, $emailHint);
                if ($uid && $isPaid) wh_welcome_if_active($conn, $uid);
            }
        }
        break;

    case 'customer.subscription.created':
    case 'customer.subscription.updated':
    case 'customer.subscription.deleted':
        // Événements de cycle de vie : on met à jour le statut UNIQUEMENT.
        // Aucun email ici (création/essai/renouvellement/annulation ≠ paiement).
        if (!empty($object['id'])) {
            bkUpsertSubscriptionRow($conn, $object);
        }
        break;

    case 'invoice.payment_failed':
        // Échec de paiement : on rafraîchit le statut, pas d'email de bienvenue.
        $subId = $object['subscription'] ?? '';
        if ($subId) {
            $resp = stripeRequest('GET', 'subscriptions/' . $subId);
            if (!$resp['error'] && !empty($resp['body']['id'])) {
                bkUpsertSubscriptionRow($conn, $resp['body']);
            }
        }
        break;

    case 'invoice.paid':
        // Paiement RÉELLEMENT encaissé (montant > 0). C'est le seul signal sûr.
        // L'anti-doublon de bkSendSubscriptionWelcome() évite tout renvoi aux
        // renouvellements mensuels suivants (1 bienvenue par membre + plan).
        $subId    = $object['subscription'] ?? '';
        $amount   = (int)($object['amount_paid'] ?? 0);
        if ($subId) {
            $resp = stripeRequest('GET', 'subscriptions/' . $subId);
            if (!$resp['error'] && !empty($resp['body']['id'])) {
                $uid = bkUpsertSubscriptionRow($conn, $resp['body']);
                if ($uid && $amount > 0) wh_welcome_if_active($conn, $uid);
            }
        }
        break;

    default:
        // Évènement non géré : on accuse réception (Stripe ne réessaiera pas).
        break;
}

wh_log('✓ Événement traité: ' . $eventType . ' (id=' . $eventId . ')');
wh_end(200, 'OK');
