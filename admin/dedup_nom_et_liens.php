<?php
/**
 * dedup_nom_et_liens.php — Nettoyage doublons + ajout UNIQUE sur nom_et_liens.url
 *
 * Usage (toujours via curl ou navigateur, jamais en CLI car BDD prod) :
 *   ?bk_key=...                    → DRY RUN : compte et estime, ne touche a rien
 *   ?bk_key=...&step=delete        → DELETE des doublons (garde MIN(id) par URL)
 *   ?bk_key=...&step=alter         → ALTER ADD UNIQUE KEY (necessite que les doublons soient deletes)
 *   ?bk_key=...&step=verify        → verifie l'etat final (compteurs + index)
 *
 * Idempotent : peut etre relance, ne re-cree pas l'index s'il existe deja.
 * One-shot : a executer une fois apres deploiement de par_epreuve.php (avec INSERT IGNORE).
 */

@ini_set('display_errors', '1');
@set_time_limit(600); // 10 min, le DELETE peut etre long

$key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') {
    http_response_code(403);
    die('Interdit');
}

require_once __DIR__ . '/../core/db.php';
header('Content-Type: text/plain; charset=utf-8');

$step = $_GET['step'] ?? 'dryrun';

echo "=== dedup_nom_et_liens — step=$step — " . date('Y-m-d H:i:s') . " ===\n\n";

// Etat actuel
$r = $conn->query("SELECT COUNT(*) AS total, COUNT(DISTINCT url) AS uniques FROM nom_et_liens");
$row = $r->fetch_assoc();
$total = (int)$row['total'];
$uniques = (int)$row['uniques'];
$doublons = $total - $uniques;
echo "Etat actuel : total=$total uniques=$uniques doublons=$doublons\n\n";

// Existence index UNIQUE ?
$idxR = $conn->query("SHOW INDEX FROM nom_et_liens WHERE Non_unique = 0 AND Column_name = 'url'");
$hasUnique = $idxR && $idxR->num_rows > 0;
echo "UNIQUE sur url existe deja ? " . ($hasUnique ? "OUI" : "NON") . "\n\n";

if ($step === 'dryrun') {
    echo "=== DRY RUN ===\n";
    echo "Pour executer :\n";
    echo "  1) ?bk_key=...&step=delete  → supprime $doublons doublons (estime ~30s)\n";
    echo "  2) ?bk_key=...&step=alter   → ajoute UNIQUE (estime 2-3min sur $uniques rows)\n";
    echo "  3) ?bk_key=...&step=verify  → check final\n";
    exit;
}

if ($step === 'delete') {
    if ($doublons === 0) {
        echo "Aucun doublon a supprimer. Skip.\n";
        exit;
    }
    echo "DELETE des doublons (garde la row avec le plus petit id_nom_et_liens par url)...\n";
    $t0 = microtime(true);
    // Strategie : DELETE n1 FROM nom_et_liens n1 JOIN nom_et_liens n2 ON n1.url = n2.url AND n1.id > n2.id
    // Avec 353k lignes, c'est viable mais a chronometrer.
    $sql = "DELETE n1 FROM nom_et_liens n1
            INNER JOIN nom_et_liens n2
              ON n1.url = n2.url AND n1.id_nom_et_liens > n2.id_nom_et_liens";
    $ok = $conn->query($sql);
    $elapsed = round(microtime(true) - $t0, 2);
    if (!$ok) {
        echo "ERREUR : " . $conn->error . "\n";
        exit;
    }
    echo "OK — " . $conn->affected_rows . " lignes supprimees en {$elapsed}s\n\n";

    // Re-check
    $r = $conn->query("SELECT COUNT(*) AS total, COUNT(DISTINCT url) AS uniques FROM nom_et_liens");
    $row = $r->fetch_assoc();
    echo "Nouvel etat : total=" . $row['total'] . " uniques=" . $row['uniques'] .
         " doublons=" . ((int)$row['total'] - (int)$row['uniques']) . "\n";
    echo "\nProchaine etape : ?bk_key=...&step=alter\n";
    exit;
}

if ($step === 'alter') {
    if ($hasUnique) {
        echo "UNIQUE deja en place. Rien a faire.\n";
        exit;
    }
    if ($doublons > 0) {
        echo "ATTENTION : il reste $doublons doublons. Lance d'abord step=delete.\n";
        exit;
    }
    echo "ALTER TABLE pour reduire VARCHAR(2000) -> VARCHAR(100) et ajouter UNIQUE...\n";
    $t0 = microtime(true);
    // 1) Reduire la taille (max actuel = 40 chars, on prend 100 pour marge)
    // 2) Ajouter UNIQUE sur url
    $sql = "ALTER TABLE nom_et_liens
              MODIFY url VARCHAR(100) NOT NULL,
              ADD UNIQUE KEY uk_nom_et_liens_url (url)";
    $ok = $conn->query($sql);
    $elapsed = round(microtime(true) - $t0, 2);
    if (!$ok) {
        echo "ERREUR : " . $conn->error . "\n";
        // Fallback : try without MODIFY (in case the column is already shrunk)
        echo "Retry sans MODIFY...\n";
        $sql2 = "ALTER TABLE nom_et_liens ADD UNIQUE KEY uk_nom_et_liens_url (url)";
        $ok2 = $conn->query($sql2);
        if (!$ok2) { echo "ERREUR 2 : " . $conn->error . "\n"; exit; }
        $elapsed = round(microtime(true) - $t0, 2);
    }
    echo "OK — ALTER applique en {$elapsed}s\n\n";
    echo "Prochaine etape : ?bk_key=...&step=verify\n";
    exit;
}

if ($step === 'verify') {
    echo "=== VERIFICATION FINALE ===\n\n";
    // Compte
    $r = $conn->query("SELECT COUNT(*) AS total, COUNT(DISTINCT url) AS uniques FROM nom_et_liens");
    $row = $r->fetch_assoc();
    echo "Compteur : " . $row['total'] . " lignes, " . $row['uniques'] . " uniques\n";
    echo "Doublons : " . ((int)$row['total'] - (int)$row['uniques']) . " (doit etre 0)\n\n";
    // Index
    echo "Index sur la table :\n";
    $r = $conn->query("SHOW INDEX FROM nom_et_liens");
    while ($idx = $r->fetch_assoc()) {
        echo "  - " . $idx['Key_name'] . " (col=" . $idx['Column_name'] . ", unique=" .
             ($idx['Non_unique'] == 0 ? 'OUI' : 'non') . ")\n";
    }
    echo "\n";
    // Schema
    $r = $conn->query("SHOW COLUMNS FROM nom_et_liens WHERE Field='url'");
    $col = $r->fetch_assoc();
    echo "Colonne url : Type=" . $col['Type'] . " Key=" . $col['Key'] . "\n\n";
    echo "Si tout est OK : UNIQUE en place + 0 doublon → mission accomplie.\n";
    echo "Test recommande : lance un SCRAPER sur une epreuve deja faite, tu dois voir des 'doublons ignores' dans la console SSE.\n";
    exit;
}

echo "Step inconnu : $step\n";
echo "Steps valides : dryrun, delete, alter, verify\n";
