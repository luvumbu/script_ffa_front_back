<?php
/**
 * api/subscribe.php — Collecte email (newsletter, PDF, etc.)
 *
 * POST : { email, source: "newsletter"|"pdf", detail: "..." }
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Methode POST requise'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    jsonResponse(['success' => false, 'error' => 'JSON invalide'], 400);
}

$email  = trim($input['email'] ?? '');
$source = trim($input['source'] ?? 'newsletter');
$detail = trim($input['detail'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'Email invalide'], 400);
}

$validSources = ['newsletter', 'pdf'];
if (!in_array($source, $validSources, true)) {
    $source = 'newsletter';
}

$emailEsc  = $conn->real_escape_string($email);
$sourceEsc = $conn->real_escape_string(substr($source, 0, 30));
$detailEsc = $conn->real_escape_string(substr($detail, 0, 255));

// INSERT IGNORE pour ne pas doublonner
$conn->query("INSERT IGNORE INTO email_subscribers (email, source, detail) VALUES ('$emailEsc', '$sourceEsc', '$detailEsc')");

jsonResponse(['success' => true, 'message' => 'Email enregistre']);
