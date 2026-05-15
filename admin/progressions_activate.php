<?php
/**
 * admin/progressions_activate.php — Bascule securisee de athlete_progressions vers le mode fichier.
 *
 * Verifie d'abord que :
 *   - archives/athlete_progressions_live.jsonl existe et n'est pas vide
 *   - archives/.prog_idx/ contient les 256 shards
 *   - L'index couvre bien la majorite des athletes
 * Puis :
 *   - TRUNCATE athlete_progressions
 *   - Cree/met a jour config/data_source.json
 *
 * Usage : https://bokonzi.com/admin/progressions_activate.php?bk_key=bk_s3cr3t_2026_xK9mP
 *
 * Pour rollback (re-importer le fichier en BDD) : panel db_archive.php -> "Basculer vers BDD"
 */
$key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') { http_response_code(403); die('Interdit'); }
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(120);

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/progressions_store.php';
require_once __DIR__ . '/../core/data_source.php';

echo "=== Activation mode fichier pour athlete_progressions ===\n\n";

// 1. Verifier le fichier source
$srcPath = progStoreSourcePath();
echo "1) Fichier source : " . basename($srcPath) . "\n";
if (!file_exists($srcPath)) {
    die("   [ERREUR] Fichier ABSENT. Renomme d'abord athlete_progressions_2026-*.jsonl en athlete_progressions_live.jsonl\n");
}
$size = filesize($srcPath);
echo "   Taille : " . round($size / 1024 / 1024, 1) . " MB\n";
if ($size < 100 * 1024 * 1024) {
    die("   [ERREUR] Fichier suspicieusement petit (<100 MB). Verifie.\n");
}

// 2. Verifier l'index
$idxDir = dirname($srcPath) . '/.prog_idx';
$shards = glob($idxDir . '/*.json');
echo "\n2) Index : " . count($shards) . " shards\n";
if (count($shards) < 200) {
    die("   [ERREUR] Index incomplet. Lance d'abord progressions_init.php pour le construire.\n");
}

// Compte les athletes indexes
$nbAth = 0;
foreach ($shards as $f) {
    $j = json_decode(@file_get_contents($f) ?: '{}', true);
    if (is_array($j)) $nbAth += count($j);
}
echo "   Athletes indexes : " . number_format($nbAth, 0, '.', ' ') . "\n";
if ($nbAth < 100000) {
    die("   [ERREUR] Tres peu d'athletes indexes. Quelque chose ne va pas.\n");
}

// 3. Stats BDD actuelle
$nLignes = (int)$conn->query("SELECT COUNT(*) FROM athlete_progressions")->fetch_row()[0];
$tailleMb = (float)$conn->query("SELECT ROUND((data_length+index_length)/1024/1024, 1) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name='athlete_progressions'")->fetch_row()[0];
echo "\n3) Etat BDD actuelle :\n";
echo "   athlete_progressions : " . number_format($nLignes, 0, '.', ' ') . " lignes (" . $tailleMb . " MB)\n";

// 4. Test fonctionnel : on lit un athlete au hasard du store et on verifie qu'on a des donnees
$testIds = [571043, 119122, 1, 100000];
echo "\n4) Test lecture store :\n";
$okTests = 0;
foreach ($testIds as $idA) {
    $rows = progStoreLoadForAthlete($idA);
    echo "   id_athlete=" . $idA . " : " . count($rows) . " progressions\n";
    if (count($rows) > 0) $okTests++;
}
if ($okTests === 0) {
    die("\n[ERREUR] Aucun athlete test n'a renvoye de donnees. Index probable corrompu.\n");
}

// 5. Confirmation
if (empty($_GET['confirm'])) {
    echo "\n==========================================\n";
    echo "VERIFICATIONS OK. Pour passer a l'action :\n";
    echo "Ouvre cette URL avec &confirm=1 :\n\n";
    echo "https://" . ($_SERVER['HTTP_HOST'] ?? 'bokonzi.com') . $_SERVER['REQUEST_URI'] . "&confirm=1\n\n";
    echo "Ca va :\n";
    echo "  - TRUNCATE athlete_progressions (-" . $tailleMb . " MB BDD)\n";
    echo "  - Activer data_source.athlete_progressions = file\n";
    echo "==========================================\n";
    exit;
}

// 6. EXECUTION
echo "\n=== EXECUTION ===\n";

echo "TRUNCATE athlete_progressions... ";
$conn->query("SET foreign_key_checks=0");
$conn->query("TRUNCATE TABLE athlete_progressions");
$conn->query("SET foreign_key_checks=1");
echo "OK\n";

echo "Activation data_source... ";
$ok = setDataSourceMode('athlete_progressions', 'file');
echo $ok ? "OK\n" : "ECHEC\n";

// Verification finale
echo "\n=== ETAT FINAL ===\n";
$nLignes2 = (int)$conn->query("SELECT COUNT(*) FROM athlete_progressions")->fetch_row()[0];
echo "athlete_progressions : " . $nLignes2 . " lignes (avant: " . number_format($nLignes, 0, '.', ' ') . ")\n";

$mode = dataSourceMode('athlete_progressions');
echo "data_source.athlete_progressions = " . $mode . "\n";

// Re-test reading
$rows = progStoreLoadForAthlete(571043);
echo "Test Kevin Mayer (id=571043) : " . count($rows) . " progressions depuis fichier\n";

echo "\n[OK] Bascule terminee. Verifie un profil pour confirmer.\n";
echo "Pour rollback : panel db_archive.php -> 'Basculer vers BDD' (re-importe le fichier).\n";
