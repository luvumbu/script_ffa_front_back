<?php
/**
 * admin/setup_articles.php — Crée la table `articles` (Fil BOKONZI).
 * Sécurisé par clé API. Idempotent (CREATE TABLE IF NOT EXISTS).
 *   Usage : /admin/setup_articles.php?bk_key=...
 */
$key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') { http_response_code(403); die('Interdit'); }

require_once __DIR__ . '/../core/db.php';
header('Content-Type: application/json; charset=utf-8');

$sql = "CREATE TABLE IF NOT EXISTS articles (
    id_article    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type          VARCHAR(32)  NOT NULL,           -- club_epreuve | club_global | athlete | ville_epreuve
    title         VARCHAR(255) NOT NULL,
    slug          VARCHAR(255) NOT NULL,
    excerpt       TEXT,
    body          MEDIUMTEXT,
    ref_key       VARCHAR(191) NOT NULL,           -- ex 'club_epreuve:123:456' (anti-doublon + régénération)
    tags          VARCHAR(512) NOT NULL DEFAULT '',-- 'bordeaux es-massy 100m sprint fra' → personnalisation du feed
    cover         JSON,                            -- stats clés pour la carte du feed
    entity_club   INT NULL,
    entity_epreuve INT NULL,
    entity_athlete INT NULL,
    entity_ville  INT NULL,
    views         INT UNSIGNED NOT NULL DEFAULT 0,
    status        VARCHAR(16)  NOT NULL DEFAULT 'published',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug),
    UNIQUE KEY uk_ref (ref_key),
    INDEX idx_type (type),
    INDEX idx_status_created (status, created_at),
    INDEX idx_views (views),
    FULLTEXT KEY ft_relevance (title, excerpt, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$ok = $conn->query($sql);
echo json_encode([
    'ok'      => (bool)$ok,
    'error'   => $ok ? null : $conn->error,
    'message' => $ok ? "Table 'articles' prête." : "Échec création table.",
    'count'   => ($r = $conn->query("SELECT COUNT(*) c FROM articles")) ? (int)$r->fetch_assoc()['c'] : null,
], JSON_UNESCAPED_UNICODE);
