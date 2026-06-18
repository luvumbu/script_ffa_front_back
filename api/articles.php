<?php
/**
 * api/articles.php — Feed paginé du « Fil BOKONZI » (JSON, scroll infini)
 *   ?page=N&limit=8  → articles classés par pertinence pour le visiteur.
 */
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/articles_feed.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$page  = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 8);

echo json_encode(bkFetchArticles($conn, $page, $limit, bkArticlesIp()), JSON_UNESCAPED_UNICODE);
