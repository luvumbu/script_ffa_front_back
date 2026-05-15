<?php
/**
 * api/verify_payment.php — Vérifie le paiement de l'utilisateur connecté
 *
 * POST (connexion requise) → interroge Stripe directement, met à jour la base,
 * et renvoie l'état d'abonnement consolidé.
 *
 * Sert de filet de sécurité : au retour d'un paiement (Payment Link / Checkout),
 * ou via un bouton « Vérifier mon paiement », si le webhook n'a pas encore traité
 * l'événement.
 *
 * Réponse : { success, active, plan, message }
 */

require_once __DIR__ . '/config.php';               // $conn, getCurrentUser(), jsonResponse()
require_once __DIR__ . '/../core/subscription.php'; // bkSyncFromStripe()

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Méthode non autorisée'], 405);
}

$user = getCurrentUser($conn);
if (!$user) {
    jsonResponse(['success' => false, 'error' => 'Connexion requise', 'need_login' => true], 401);
}

$res = bkSyncFromStripe($conn, (int)$user['id_user']);

jsonResponse([
    'success' => (bool)$res['ok'],
    'active'  => (bool)$res['active'],
    'plan'    => $res['plan'],
    'message' => $res['message'],
]);
