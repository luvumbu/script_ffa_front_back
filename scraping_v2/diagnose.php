<?php
/**
 * diagnose.php — Diagnostic complet de scraping_v2
 *
 * Teste chaque composant un par un. Si quelque chose cloche, le voyant rouge
 * t'indique exactement quoi.
 *
 * Tests :
 *   1. Connexion BDD
 *   2. Existence des fichiers requis
 *   3. Permissions du dossier state/
 *   4. Etat du runner (flag + progress)
 *   5. Existence de la table nom_et_liens
 *   6. Test INSERT (avec ROLLBACK)
 *   7. Test fetch athle.fr (curl + parsing)
 *   8. Test cycle complet sur 1 URL
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

$libDir = __DIR__ . '/lib';
$stateDir = __DIR__ . '/state';

// =========================================================================
// Helpers d'affichage
// =========================================================================
$tests = [];
function t($label, $ok, $detail = '', $extra = '') {
    global $tests;
    $tests[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail, 'extra' => $extra];
}

// =========================================================================
// TEST 1 : Connexion BDD
// =========================================================================
$conn = null;
$dbFile = dirname(__DIR__) . '/core/db.php';
if (!file_exists($dbFile)) {
    t('1. Connexion BDD', false, "core/db.php introuvable");
} else {
    try {
        require_once $dbFile;
        $ping = $conn->ping();
        $dbname = $conn->query("SELECT DATABASE() AS d")->fetch_assoc()['d'];
        t('1. Connexion BDD', $ping, "Connecte a: <code>$dbname</code>");
    } catch (Exception $e) {
        t('1. Connexion BDD', false, "Erreur: " . $e->getMessage());
    }
}

// =========================================================================
// TEST 2 : Fichiers requis
// =========================================================================
$required = [
    'lib/PageAnalyzer.php',
    'lib/ScrapingRunner.php',
    'lib/UrlAnalyzer.php',
    'lib/SourceTableReader.php',
    'lib/EpreuveMapper.php',
    'data/parametres_athle.json',
    'index.php',
    'test_page.php',
    'cleanup_duplicates.php',
];
$missing = [];
foreach ($required as $f) {
    if (!file_exists(__DIR__ . '/' . $f)) $missing[] = $f;
}
t('2. Fichiers requis', empty($missing),
   empty($missing) ? count($required) . ' fichiers presents' : 'Manquants : <code>' . implode('</code>, <code>', $missing) . '</code>');

// =========================================================================
// TEST 3 : Dossier state/
// =========================================================================
$stateExists = is_dir($stateDir);
$stateWritable = false;
$stateCreated = false;
if (!$stateExists) {
    $stateCreated = @mkdir($stateDir, 0755, true);
    $stateExists = is_dir($stateDir);
}
if ($stateExists) {
    $testFile = $stateDir . '/.write_test';
    $stateWritable = @file_put_contents($testFile, 'test') !== false;
    if ($stateWritable) @unlink($testFile);
}
t('3. Dossier state/ ecrivable', $stateWritable,
   $stateWritable ? "OK : <code>$stateDir</code>" .
   ($stateCreated ? ' (cree maintenant)' : '')
   : "ECHEC : impossible d'ecrire dans <code>$stateDir</code>. Permission insuffisante. CHMOD 755 ou 777 sur Hostinger.");

// =========================================================================
// TEST 4 : Etat du runner
// =========================================================================
$flagFile     = $stateDir . '/scraper_v2_running.flag';
$progressFile = $stateDir . '/scraper_v2_progress.json';
$flagExists = file_exists($flagFile);
$progressExists = file_exists($progressFile);
$progressContent = $progressExists ? json_decode(file_get_contents($progressFile), true) : null;
$detail = '';
if (!$flagExists && !$progressExists) {
    $detail = "Aucun runner en cours, aucune progression sauvegardee. Etat propre.";
} elseif ($flagExists && $progressExists) {
    $detail = "Runner EN COURS. Flag cree : " . file_get_contents($flagFile)
            . "<br>Progression : url_index = " . ($progressContent['url_index'] ?? '?')
            . ", page = " . ($progressContent['page_courante'] ?? '?')
            . ", inserts = " . ($progressContent['stats']['athletes_inserts'] ?? '?');
} elseif ($flagExists && !$progressExists) {
    $detail = "ANOMALIE : flag existe mais pas de progress.json. Cliquer reset dans index.php.";
} else {
    $detail = "Pas de flag, mais progress.json existe (= dernier run termine ou stoppe).";
}
t('4. Etat du runner', true, $detail);

// =========================================================================
// TEST 5 : Table nom_et_liens
// =========================================================================
if ($conn) {
    $r = $conn->query("SHOW TABLES LIKE 'nom_et_liens'");
    if ($r && $r->num_rows > 0) {
        $cnt = (int) $conn->query("SELECT COUNT(*) c FROM nom_et_liens")->fetch_assoc()['c'];
        $cols = [];
        $r2 = $conn->query("DESCRIBE nom_et_liens");
        while ($col = $r2->fetch_assoc()) $cols[] = $col['Field'] . ' (' . $col['Type'] . ')';
        t('5. Table nom_et_liens', true,
          number_format($cnt, 0, ',', ' ') . ' lignes actuelles. Colonnes : ' . implode(', ', $cols));
    } else {
        t('5. Table nom_et_liens', false, "La table n'existe pas ! Sans elle, INSERT impossible.");
    }
}

// =========================================================================
// TEST 6 : Test INSERT (sans commit reel)
// =========================================================================
if ($conn) {
    $testUrl = 'https://athle.fr/athletes/0/__diag_test_' . microtime(true);
    $stmt = $conn->prepare("INSERT INTO nom_et_liens (url) VALUES (?)");
    if ($stmt) {
        $stmt->bind_param('s', $testUrl);
        $ok = $stmt->execute();
        $insertId = $conn->insert_id;
        $stmt->close();
        if ($ok && $insertId > 0) {
            // Cleanup : on supprime la ligne de test
            $conn->query("DELETE FROM nom_et_liens WHERE id_nom_et_liens = $insertId");
            t('6. Test INSERT', true, "INSERT OK (id $insertId, supprime apres test)");
        } else {
            t('6. Test INSERT', false, "Execute a echoue : " . $conn->error);
        }
    } else {
        t('6. Test INSERT', false, "Prepare a echoue : " . $conn->error);
    }
}

// =========================================================================
// TEST 7 : Fetch athle.fr (curl + parser)
// =========================================================================
$curlOk = false;
$nbAthletes = 0;
$pageInfo = '';
if (file_exists("$libDir/PageAnalyzer.php")) {
    require_once "$libDir/PageAnalyzer.php";
    $pa = new PageAnalyzer();
    $testUrl = 'https://www.athle.fr/bases/liste.aspx?frmpostback=true&frmbase=bilans&frmmode=1&frmespace=0&frmannee=2026&frmsexe=F&frmepreuve=110&frmposition=1';
    $r = $pa->analyze($testUrl);
    $curlOk = $r['success'];
    $nbAthletes = count($r['athletes']);
    $pageInfo = "HTTP {$r['http_code']}, " . number_format($r['taille_html']) . " octets en {$r['duree_ms']}ms";
    if (!empty($r['erreur'])) $pageInfo .= " — Erreur: " . $r['erreur'];
    t('7. Fetch athle.fr (parser)', $curlOk && $nbAthletes > 0,
      $pageInfo . " — <strong>$nbAthletes athletes extraits</strong>");
}

// =========================================================================
// TEST 8 : Cycle complet sur 1 URL (sans inserer en BDD)
// =========================================================================
$cycleStatus = '';
if ($conn && file_exists("$libDir/ScrapingRunner.php") && file_exists("$libDir/SourceTableReader.php")) {
    require_once "$libDir/SourceTableReader.php";
    $reader = new SourceTableReader($conn);
    $tables = $reader->listerTables();
    if (count($tables) === 0) {
        t('8. Tables sources visibles', false, "Aucune table source detectee (prefixe u489596434_bokonzi_on_*)");
    } else {
        $list = [];
        foreach ($tables as $t) $list[] = $t['nom'] . ' (' . $t['lignes_approx'] . ')';
        t('8. Tables sources visibles', true,
          count($tables) . " tables : " . implode(', ', array_slice($list, 0, 5))
          . (count($tables) > 5 ? ', ...' : ''));
    }
}

// Total tests passes
$totalOk = count(array_filter($tests, fn($x) => $x['ok']));
$totalKo = count($tests) - $totalOk;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic scraping_v2</title>
    <style>
        body { font-family: 'Inter', -apple-system, sans-serif; background: #0d1117; color: #c9d1d9; margin: 0; padding: 24px; line-height: 1.6; }
        h1 { color: #a78bfa; font-size: 22px; margin: 0 0 4px; }
        .sub { color: #8b949e; font-size: 13px; margin-bottom: 22px; }
        .summary { background: #161b22; border: 1px solid #1f2937; border-radius: 8px; padding: 16px; margin-bottom: 16px; display: flex; gap: 24px; align-items: center; }
        .summary .big { font-size: 36px; font-weight: 700; }
        .summary .ok-c { color: #34d399; }
        .summary .ko-c { color: #f87171; }
        .test { background: #161b22; border-left: 4px solid #1f2937; padding: 12px 16px; margin-bottom: 8px; border-radius: 4px; }
        .test.ok { border-left-color: #34d399; }
        .test.ko { border-left-color: #f87171; background: #2c0a0a; }
        .test .label { font-weight: 600; font-size: 14px; color: #fff; margin-bottom: 4px; }
        .test .label .badge { display: inline-block; margin-right: 8px; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
        .test.ok .badge { background: #022c22; color: #6ee7b7; }
        .test.ko .badge { background: #450a0a; color: #fca5a5; }
        .test .detail { font-size: 12px; color: #8b949e; }
        code { background: #0a0e15; padding: 2px 6px; border-radius: 3px; color: #fbbf24; font-size: 11px; }
        .nav { display: flex; gap: 8px; margin-bottom: 16px; }
        .nav a { background: #374151; color: #fff; text-decoration: none; padding: 8px 14px; border-radius: 4px; font-size: 12px; }
        .help { background: #422006; border-left: 3px solid #fbbf24; padding: 14px; border-radius: 4px; margin-top: 20px; color: #fde68a; font-size: 13px; }
        .help h3 { color: #fbbf24; margin: 0 0 8px; font-size: 14px; }
    </style>
</head>
<body>

<h1>Diagnostic scraping_v2</h1>
<div class="sub">Verifie chaque composant pour trouver pourquoi le runner ne demarre pas.</div>

<div class="nav">
    <a href="index.php">&larr; Retour index</a>
    <a href="test_page.php">Test parser</a>
    <a href="cleanup_duplicates.php">Cleanup doublons</a>
    <a href="diagnose.php">Recharger</a>
</div>

<div class="summary">
    <div>
        <div class="big ok-c"><?= $totalOk ?></div>
        <div style="color:#34d399;font-size:11px;text-transform:uppercase;">tests OK</div>
    </div>
    <div>
        <div class="big ko-c"><?= $totalKo ?></div>
        <div style="color:#f87171;font-size:11px;text-transform:uppercase;">tests KO</div>
    </div>
    <div style="flex:1;text-align:right;color:#6b7280;font-size:11px;">
        Diagnostic genere : <?= date('Y-m-d H:i:s') ?>
    </div>
</div>

<?php foreach ($tests as $t): ?>
    <div class="test <?= $t['ok'] ? 'ok' : 'ko' ?>">
        <div class="label">
            <span class="badge"><?= $t['ok'] ? 'OK' : 'KO' ?></span>
            <?= htmlspecialchars($t['label']) ?>
        </div>
        <div class="detail"><?= $t['detail'] ?></div>
    </div>
<?php endforeach; ?>

<?php if ($totalKo > 0): ?>
    <div class="help">
        <h3>Quelques tests echouent. Voici les causes typiques :</h3>
        <ul>
            <li><strong>BDD KO</strong> : credentials Hostinger differents, ou serveur MySQL down</li>
            <li><strong>Fichier manquant</strong> : un fichier n'a pas ete uploade. Verifier File Manager Hostinger</li>
            <li><strong>state/ pas ecrivable</strong> : CHMOD 755 ou 777 sur le dossier <code>scraping_v2/state/</code> via le panneau Hostinger</li>
            <li><strong>Fetch athle.fr KO</strong> : Hostinger bloque les sorties HTTPS, ou athle.fr renvoie 403/timeout. Tester depuis test_page.php</li>
            <li><strong>nom_et_liens manquante</strong> : creer la table d'abord (CREATE TABLE)</li>
            <li><strong>INSERT KO</strong> : permissions BDD insuffisantes ou colonne url avec contrainte qui bloque</li>
        </ul>
    </div>
<?php else: ?>
    <div class="help" style="background:#022c22;border-left-color:#34d399;color:#6ee7b7;">
        <h3>Tout est vert.</h3>
        Le runner devrait marcher. Si tu cliques DEMARRER et que rien ne se passe :
        <ul>
            <li>Verifier que au moins 1 annee est cochee (sinon le bouton est grise)</li>
            <li>Recharger index.php apres avoir clique DEMARRER (le redirect post-POST devrait deja le faire)</li>
            <li>Regarder cette page diagnose.php apres le clic : le test 4 doit montrer "Runner EN COURS"</li>
        </ul>
    </div>
<?php endif; ?>

</body>
</html>
