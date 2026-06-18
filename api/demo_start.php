<?php
/**
 * api/demo_start.php — Active la démo Platine de 5 minutes (self-service).
 *
 * Réservé aux MEMBRES CONNECTÉS, UNE SEULE FOIS (définitif). L'état est stocké
 * côté serveur dans logs/.demo_used.php (aucun cookie : voir core/demo_mode.php).
 *
 * POST → { ok:bool, remaining:int, message:string }
 */

require_once __DIR__ . '/../core/db.php';           // $conn
require_once __DIR__ . '/../core/auth.php';         // getCurrentUser()
require_once __DIR__ . '/../core/subscription.php'; // tire demo_mode.php + hasActiveSubscription()

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

$r = bkDemoStart($conn);

$messages = [
    'not_logged'         => 'Connectez-vous pour activer votre démo Platine gratuite.',
    'already_subscriber' => 'Vous êtes déjà abonné : tout est déjà débloqué !',
    'already_used'       => 'Vous avez déjà profité de votre démo gratuite. Passez à Platine pour un accès permanent.',
];

echo json_encode([
    'ok'        => (bool)$r['ok'],
    'remaining' => (int)$r['remaining'],
    'message'   => $r['ok']
        ? 'Démo Platine activée — accès complet pendant 5 minutes !'
        : ($messages[$r['reason']] ?? 'Démo indisponible pour le moment.'),
]);
