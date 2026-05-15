<?php
/**
 * api/quota.php — Statut du quota de recherches (lecture seule)
 *
 * Ne consomme PAS le quota — sert juste a rafraichir le badge de la nav.
 * Toute la logique est dans core/search_limit.php.
 * Retourne : search_used, search_limit, cooldown_remaining, cooldown_total,
 *            anon_trial, trial_remaining, trial_total, logged, is_sa, unlimited.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../core/search_limit.php';

$sl = bkSearchLimit($conn, false); // false = lecture seule, ne consomme rien

jsonResponse(array_merge(['success' => true], bkSlFields($sl)));
