<?php
/**
 * admin/refresh_villes_stats.php — Recalcule les stats denormalisees de la table villes.
 *
 * Pourquoi : la page /villes lit directement v.nb_athletes (pre-calcule) au lieu de
 * faire un GROUP BY sur 1.5M de athlete_resultats. Cette table de stats doit
 * etre rafraichie apres chaque batch de scraping (5-10s seulement).
 *
 * Usage :
 *   CLI : php admin/refresh_villes_stats.php
 *   Web : https://bokonzi.com/admin/refresh_villes_stats.php?bk_key=bk_s3cr3t_2026_xK9mP
 */
$_isWeb = (PHP_SAPI !== 'cli');
if ($_isWeb) {
    $key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
    if ($key !== 'bk_s3cr3t_2026_xK9mP') { http_response_code(403); die('Interdit'); }
    header('Content-Type: text/plain; charset=utf-8');
}
@set_time_limit(0);
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/../core/db.php';

$t0 = microtime(true);

// Assure que les colonnes existent (idempotent)
foreach ([
    'nb_athletes'      => 'INT UNSIGNED DEFAULT 0',
    'annee_debut_perf' => 'SMALLINT UNSIGNED NULL',
    'annee_fin_perf'   => 'SMALLINT UNSIGNED NULL',
] as $col => $type) {
    @$conn->query("ALTER TABLE villes ADD COLUMN $col $type");
}
@$conn->query('ALTER TABLE villes ADD INDEX idx_villes_nb (nb_athletes)');

echo "Recalcul stats villes...\n";

// Source : athlete_resultats (la seule table qui joint sur id_ville pour la majorite des perfs)
$conn->query("
    UPDATE villes v
    LEFT JOIN (
        SELECT id_ville,
               COUNT(DISTINCT id_athlete) as nb,
               MIN(annee_resultat) as ad,
               MAX(annee_resultat) as af
        FROM athlete_resultats
        WHERE id_ville IS NOT NULL
        GROUP BY id_ville
    ) s ON s.id_ville = v.id_ville
    SET v.nb_athletes      = COALESCE(s.nb, 0),
        v.annee_debut_perf = s.ad,
        v.annee_fin_perf   = s.af
");

$dt = round(microtime(true) - $t0, 1);
echo "Stats globales villes : OK en {$dt}s\n";

// --- Stats par annee (table villes_stats_annee) ---
echo "Recalcul stats par annee...\n";
$t1 = microtime(true);
@$conn->query("
    CREATE TABLE IF NOT EXISTS villes_stats_annee (
        id_ville INT UNSIGNED NOT NULL,
        annee SMALLINT UNSIGNED NOT NULL,
        nb_athletes INT UNSIGNED NOT NULL,
        PRIMARY KEY (id_ville, annee),
        INDEX idx_annee_nb (annee, nb_athletes)
    ) ENGINE=InnoDB
");
$conn->query("TRUNCATE TABLE villes_stats_annee");
$conn->query("
    INSERT INTO villes_stats_annee (id_ville, annee, nb_athletes)
    SELECT id_ville, annee_resultat, COUNT(DISTINCT id_athlete)
    FROM athlete_resultats
    WHERE id_ville IS NOT NULL AND annee_resultat > 0
    GROUP BY id_ville, annee_resultat
");
echo "Stats par annee : OK en " . round(microtime(true)-$t1, 1) . "s\n";

$r = $conn->query("SELECT COUNT(*) FROM villes_stats_annee");
echo "Lignes (ville x annee) : " . number_format($r->fetch_row()[0], 0, '.', ' ') . "\n";

// Verification
$r = $conn->query("SELECT COUNT(*) FROM villes WHERE nb_athletes > 0");
echo "Villes avec >=1 athlete : " . number_format($r->fetch_row()[0], 0, '.', ' ') . "\n";

$r = $conn->query("SELECT nom_ville, nb_athletes FROM villes ORDER BY nb_athletes DESC LIMIT 5");
echo "Top 5 :\n";
while ($x = $r->fetch_assoc()) echo "  " . str_pad($x['nom_ville'], 25) . ' ' . number_format($x['nb_athletes'], 0, '.', ' ') . "\n";
