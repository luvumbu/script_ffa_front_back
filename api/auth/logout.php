<?php
/**
 * api/auth/logout.php — Deconnexion utilisateur
 * POST : supprime la session en BDD et le cookie
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../core/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Methode POST requise'], 405);
}

logout($conn);

jsonResponse(['success' => true, 'message' => 'Deconnecte']);
