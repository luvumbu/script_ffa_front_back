<?php
/**
 * admin/db_archive.php — Archivage BDD reversible + bascule de source
 *
 * Workflow :
 *  - EXPORT  : table -> fichier .jsonl (archives/)
 *  - VIDER   : truncate la table (apres export)
 *  - RESTORE : reinjecte le fichier en BDD
 *
 * Source par table (config/data_source.json) :
 *  - "bdd"  : les API/pages lisent dans MySQL (defaut)
 *  - "file" : les API/pages lisent dans le fichier .jsonl
 *
 * Bouton "Basculer vers Fichier" = Export + Vider + setSource('file')
 * Bouton "Basculer vers BDD"     = Restore + setSource('bdd')
 *
 * Usage local : http://localhost/BK/admin/db_archive.php?bk_key=bk_s3cr3t_2026_xK9mP
 * Usage prod  : https://bokonzi.com/admin/db_archive.php?bk_key=bk_s3cr3t_2026_xK9mP
 */

$key = $_GET['bk_key'] ?? $_POST['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') {
    http_response_code(403);
    die('Interdit');
}

// Force max execution time + abandon de l'output buffering
@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
ignore_user_abort(true);

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/paths.php';
require_once __DIR__ . '/../core/data_source.php';

$archiveDir = __DIR__ . '/../archives';
if (!is_dir($archiveDir)) mkdir($archiveDir, 0755, true);

// ════════════════════════════════════════════
// PROGRESS TRACKING (fichier .progress lu par le frontend)
// ════════════════════════════════════════════
$progressFile = $archiveDir . '/.progress.json';

function writeProgress($data) {
    global $progressFile;
    $data['ts'] = microtime(true);
    @file_put_contents($progressFile, json_encode($data));
}

function clearProgress() {
    global $progressFile;
    @unlink($progressFile);
}

// Endpoint AJAX : retourne l'etat de progression en JSON
if (isset($_GET['progress'])) {
    header('Content-Type: application/json');
    if (file_exists($progressFile)) {
        echo file_get_contents($progressFile);
    } else {
        echo json_encode(['step' => 'idle']);
    }
    exit;
}

// ════════════════════════════════════════════
// ENDPOINTS AJAX CHUNKED (anti-timeout Hostinger)
// ════════════════════════════════════════════

// ─── EXPORT par chunks (5000 rows max par appel, < 5s, anti-503)
if (($_GET['ajax'] ?? '') === 'export_chunk') {
    header('Content-Type: application/json');
    $table = $_GET['table'] ?? '';
    $filename = $_GET['filename'] ?? '';
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $chunkSize = (int)($_GET['size'] ?? 5000);
    $chunkSize = max(500, min(10000, $chunkSize));
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) { echo json_encode(['error' => 'Invalid table']); exit; }

    $isFirst = ($offset === 0);
    if ($isFirst) {
        // Premier chunk : creer le fichier + meta (incluant CREATE TABLE pour redeploiement)
        $ts = date('Y-m-d_His');
        $filename = "{$table}_{$ts}.jsonl";
        $path = $archiveDir . '/' . $filename;
        $fp = fopen($path, 'w');
        if (!$fp) { echo json_encode(['error' => "Cannot open $filename"]); exit; }

        $colsRes = $conn->query("SHOW COLUMNS FROM `$table`");
        $cols = [];
        while ($c = $colsRes->fetch_assoc()) $cols[] = $c['Field'];

        // CREATE TABLE complet (utilisable pour recreer la table ailleurs)
        $createSql = null;
        $r = $conn->query("SHOW CREATE TABLE `$table`");
        if ($r) { $row = $r->fetch_assoc(); $createSql = $row['Create Table'] ?? null; }

        fwrite($fp, "#META " . json_encode([
            'table' => $table, 'exported_at' => date('Y-m-d H:i:s'),
            'columns' => $cols, 'create_sql' => $createSql,
        ]) . "\n");
        $total = (int)$conn->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0];
    } else {
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.jsonl$/', $filename)) { echo json_encode(['error' => 'Invalid filename']); exit; }
        $path = $archiveDir . '/' . $filename;
        if (!file_exists($path)) { echo json_encode(['error' => 'File missing']); exit; }
        $fp = fopen($path, 'a');
        $total = (int)($_GET['total'] ?? 0);
    }

    // Process chunk
    $r = $conn->query("SELECT * FROM `$table` LIMIT $offset, $chunkSize");
    $written = 0;
    while ($row = $r->fetch_assoc()) {
        fwrite($fp, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
        $written++;
    }
    fclose($fp);

    $newOffset = $offset + $written;
    $finished = ($newOffset >= $total) || ($written < $chunkSize);

    writeProgress([
        'step' => $finished ? 'export_done' : 'export',
        'table' => $table, 'done' => $newOffset, 'total' => $total,
        'message' => "Export : $newOffset / $total lignes"
    ]);

    echo json_encode([
        'ok' => true, 'table' => $table, 'filename' => $filename,
        'offset' => $newOffset, 'total' => $total, 'written' => $written,
        'finished' => $finished,
    ]);
    exit;
}

// ─── RESTORE par chunks
if (($_GET['ajax'] ?? '') === 'restore_chunk') {
    header('Content-Type: application/json');
    $filename = $_GET['filename'] ?? $_POST['filename'] ?? '';
    $lineStart = max(0, (int)($_GET['line_start'] ?? 0));
    $chunkLines = 2000;
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.jsonl$/', $filename)) { echo json_encode(['error' => 'Invalid filename']); exit; }
    $path = $archiveDir . '/' . $filename;
    if (!file_exists($path)) { echo json_encode(['error' => 'File missing']); exit; }

    $fp = fopen($path, 'r');
    $firstLine = fgets($fp);
    $meta = (strpos($firstLine, '#META ') === 0) ? json_decode(substr($firstLine, 6), true) : null;
    if (!$meta) { fclose($fp); echo json_encode(['error' => 'No meta in file']); exit; }
    $tbl = $meta['table'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tbl)) { fclose($fp); echo json_encode(['error' => 'Invalid table in meta']); exit; }

    // Total une seule fois (lent pour gros fichiers, on le passe via param)
    $total = (int)($_GET['total'] ?? 0);
    if ($total === 0 && $lineStart === 0) {
        $tmp = fopen($path, 'r'); fgets($tmp);
        while (($l = fgets($tmp)) !== false) if (trim($l) !== '' && $l[0] !== '#') $total++;
        fclose($tmp);
    }

    // Saute les lignes deja traitees
    $skipped = 0;
    while ($skipped < $lineStart && ($l = fgets($fp)) !== false) {
        if (trim($l) === '' || $l[0] === '#') continue;
        $skipped++;
    }

    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $batch = [];
    $cols = null;
    $processed = 0;
    while ($processed < $chunkLines && ($l = fgets($fp)) !== false) {
        $l = trim($l);
        if ($l === '' || $l[0] === '#') continue;
        $row = json_decode($l, true);
        if (!$row) continue;
        if ($cols === null) $cols = array_keys($row);
        $vals = [];
        foreach ($cols as $c) {
            $v = $row[$c] ?? null;
            if ($v === null) $vals[] = 'NULL';
            else $vals[] = "'" . $conn->real_escape_string($v) . "'";
        }
        $batch[] = '(' . implode(',', $vals) . ')';
        $processed++;
    }
    fclose($fp);

    if (!empty($batch)) {
        $sql = "INSERT INTO `$tbl` (`" . implode('`,`', $cols) . "`) VALUES " . implode(',', $batch);
        $conn->query($sql);
    }
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    $newLine = $lineStart + $processed;
    $finished = ($processed < $chunkLines) || ($total > 0 && $newLine >= $total);

    writeProgress([
        'step' => $finished ? 'restore_done' : 'restore',
        'table' => $tbl, 'done' => $newLine, 'total' => $total,
        'message' => "Restore : $newLine / $total lignes"
    ]);

    echo json_encode([
        'ok' => true, 'table' => $tbl, 'filename' => $filename,
        'line_start' => $newLine, 'total' => $total, 'processed' => $processed,
        'finished' => $finished,
    ]);
    exit;
}

// ─── TRUNCATE (rapide, 1 appel)
if (($_GET['ajax'] ?? '') === 'truncate_safe') {
    header('Content-Type: application/json');
    $table = $_POST['table'] ?? $_GET['table'] ?? '';
    $filename = $_POST['filename'] ?? $_GET['filename'] ?? '';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) { echo json_encode(['error' => 'Invalid table']); exit; }
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.jsonl$/', $filename)) { echo json_encode(['error' => 'Invalid filename']); exit; }
    $path = $archiveDir . '/' . $filename;
    // Verification rapide : compte les lignes du fichier vs BDD
    $fp = fopen($path, 'r'); fgets($fp);
    $fileRows = 0;
    while (($l = fgets($fp)) !== false) if (trim($l) !== '' && $l[0] !== '#') $fileRows++;
    fclose($fp);
    $bddRows = (int)$conn->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0];
    if ($fileRows !== $bddRows) {
        echo json_encode(['error' => "Mismatch : fichier=$fileRows, BDD=$bddRows"]);
        exit;
    }
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $ok = $conn->query("TRUNCATE TABLE `$table`");
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    if (!$ok) { echo json_encode(['error' => $conn->error]); exit; }
    echo json_encode(['ok' => true, 'truncated' => $bddRows]);
    exit;
}

// ─── VERIFICATION D'EXISTENCE DE TABLE + INFOS DU FICHIER
// Lit le META du fichier et compare avec la BDD courante
if (($_GET['ajax'] ?? '') === 'inspect_file') {
    header('Content-Type: application/json');
    $filename = $_GET['filename'] ?? '';
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.jsonl$/', $filename)) { echo json_encode(['error' => 'Invalid filename']); exit; }
    $path = $archiveDir . '/' . $filename;
    if (!file_exists($path)) { echo json_encode(['error' => 'File not found']); exit; }

    $fp = fopen($path, 'r');
    $firstLine = fgets($fp);
    if (strpos($firstLine, '#META ') !== 0) { fclose($fp); echo json_encode(['error' => 'No META in file']); exit; }
    $meta = json_decode(substr($firstLine, 6), true);
    if (!$meta) { fclose($fp); echo json_encode(['error' => 'Invalid META']); exit; }

    // Compte les lignes data
    $fileRows = 0;
    while (($l = fgets($fp)) !== false) if (trim($l) !== '' && $l[0] !== '#') $fileRows++;
    fclose($fp);

    $tbl = $meta['table'] ?? '';
    $tableExists = false;
    $bddRows = 0;
    $bddColumns = [];
    $columnsMatch = false;
    if (preg_match('/^[a-zA-Z0-9_]+$/', $tbl)) {
        $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tbl) . "'");
        $tableExists = ($check && $check->num_rows > 0);
        if ($tableExists) {
            $bddRows = (int)$conn->query("SELECT COUNT(*) FROM `$tbl`")->fetch_row()[0];
            $r = $conn->query("SHOW COLUMNS FROM `$tbl`");
            while ($c = $r->fetch_assoc()) $bddColumns[] = $c['Field'];
            $columnsMatch = (array_diff($meta['columns'] ?? [], $bddColumns) === [] &&
                             array_diff($bddColumns, $meta['columns'] ?? []) === []);
        }
    }

    echo json_encode([
        'ok' => true,
        'filename' => $filename,
        'table' => $tbl,
        'exported_at' => $meta['exported_at'] ?? null,
        'file_columns' => $meta['columns'] ?? [],
        'file_rows' => $fileRows,
        'has_create_sql' => !empty($meta['create_sql']),
        'table_exists' => $tableExists,
        'bdd_rows' => $bddRows,
        'bdd_columns' => $bddColumns,
        'columns_match' => $columnsMatch,
        'can_install' => $tableExists ? $columnsMatch : !empty($meta['create_sql']),
    ]);
    exit;
}

// ─── CREATION DE LA TABLE depuis le META d'un fichier (avant restore)
if (($_GET['ajax'] ?? '') === 'create_table_from_file') {
    header('Content-Type: application/json');
    $filename = $_POST['filename'] ?? $_GET['filename'] ?? '';
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.jsonl$/', $filename)) { echo json_encode(['error' => 'Invalid filename']); exit; }
    $path = $archiveDir . '/' . $filename;
    if (!file_exists($path)) { echo json_encode(['error' => 'File not found']); exit; }

    $fp = fopen($path, 'r');
    $firstLine = fgets($fp);
    fclose($fp);
    if (strpos($firstLine, '#META ') !== 0) { echo json_encode(['error' => 'No META']); exit; }
    $meta = json_decode(substr($firstLine, 6), true);
    if (empty($meta['create_sql'])) {
        echo json_encode(['error' => 'Aucun CREATE TABLE dans le META. Fichier exporte avec ancienne version. Cree la table manuellement.']);
        exit;
    }
    $tbl = $meta['table'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tbl)) { echo json_encode(['error' => 'Invalid table name']); exit; }

    // Si la table existe deja, on ne fait rien
    $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tbl) . "'");
    if ($check && $check->num_rows > 0) {
        echo json_encode(['ok' => true, 'msg' => "Table $tbl existe deja, pas de creation."]);
        exit;
    }

    // Desactive les FK le temps de creer (au cas ou)
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $ok = $conn->query($meta['create_sql']);
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    if (!$ok) {
        echo json_encode(['error' => "Echec creation : " . $conn->error]);
        exit;
    }
    echo json_encode(['ok' => true, 'msg' => "Table <code>$tbl</code> creee depuis le META du fichier"]);
    exit;
}

// ─── SUPPRESSION SECURISEE D'UN FICHIER ARCHIVE
// Refuse si :
//  - La table est en mode FICHIER (donnees seraient perdues)
//  - La BDD a moins de lignes que le fichier (donnees seraient perdues)
if (($_GET['ajax'] ?? '') === 'delete_archive_safe') {
    header('Content-Type: application/json');
    $filename = $_POST['filename'] ?? $_GET['filename'] ?? '';
    $force = ($_POST['force'] ?? $_GET['force'] ?? '') === '1';
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.jsonl$/', $filename)) { echo json_encode(['error' => 'Invalid filename']); exit; }
    $path = $archiveDir . '/' . $filename;
    if (!file_exists($path)) { echo json_encode(['error' => 'File not found']); exit; }

    // Extrait le nom de table depuis le filename : "logs_2026-05-13_143012.jsonl" -> "logs"
    $table = null;
    if (preg_match('/^(.+?)_\d{4}-\d{2}-\d{2}_\d{6}\.jsonl$/', $filename, $m)) {
        $table = $m[1];
    }

    if (!$table) {
        // Fichier au format non-standard : autorise suppression directe en mode force
        if (!$force) {
            echo json_encode(['error' => "Nom de fichier non-standard. Utiliser force=1 pour supprimer sans verification."]);
            exit;
        }
    } else {
        // Verification : table en mode fichier ?
        if (dataSourceMode($table) === 'file') {
            echo json_encode(['error' => "REFUS : la table <b>$table</b> est en mode FICHIER. Les donnees ne sont QUE dans ce fichier. Bascule vers BDD d'abord (bouton Vers BDD)."]);
            exit;
        }

        // Compte les lignes du fichier
        $fileRows = 0;
        $fileValid = false;
        $fp = @fopen($path, 'r');
        if ($fp) {
            $first = fgets($fp);
            $fileValid = (strpos($first, '#META ') === 0);
            while (($l = fgets($fp)) !== false) if (trim($l) !== '' && $l[0] !== '#') $fileRows++;
            fclose($fp);
        }

        // Compte les lignes de la BDD
        $bddRows = (int)$conn->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0];

        // Fichier corrompu OU incomplet (moins de lignes que BDD) -> autorise (c'est un export rate)
        // Fichier complet (>= lignes BDD) -> verifie que la BDD est OK
        if ($fileValid && $fileRows > $bddRows && !$force) {
            echo json_encode([
                'error' => "REFUS : le fichier contient <b>$fileRows lignes</b> mais la BDD n'en a que <b>$bddRows</b>. Supprimer perdrait des donnees. Pour forcer : utiliser le bouton 'Forcer suppression'.",
                'file_rows' => $fileRows, 'bdd_rows' => $bddRows, 'table' => $table,
            ]);
            exit;
        }
    }

    if (!@unlink($path)) {
        echo json_encode(['error' => 'Echec suppression']);
        exit;
    }
    echo json_encode(['ok' => true, 'msg' => "Fichier $filename supprime"]);
    exit;
}

// ─── SET SOURCE (instantane)
if (($_GET['ajax'] ?? '') === 'set_source') {
    header('Content-Type: application/json');
    $table = $_POST['table'] ?? $_GET['table'] ?? '';
    $mode = $_POST['mode'] ?? $_GET['mode'] ?? 'bdd';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) { echo json_encode(['error' => 'Invalid table']); exit; }
    setDataSourceMode($table, $mode === 'file' ? 'file' : 'bdd');
    echo json_encode(['ok' => true, 'table' => $table, 'mode' => $mode]);
    exit;
}

// Endpoint AJAX : retourne les KPI live (pour mise a jour temps reel)
if (isset($_GET['stats'])) {
    header('Content-Type: application/json');
    $dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];
    $r = $conn->query("
        SELECT COUNT(*) as nb_tables,
               SUM(table_rows) as total_rows,
               ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as total_mb
        FROM information_schema.tables WHERE table_schema = '$dbName'
    ")->fetch_assoc();
    $archives = glob($archiveDir . '/*.jsonl');
    $totalArcMb = 0;
    foreach ($archives as $f) $totalArcMb += filesize($f);
    echo json_encode([
        'tables' => (int)$r['nb_tables'],
        'rows' => (int)$r['total_rows'],
        'db_mb' => (float)$r['total_mb'],
        'archives' => count($archives),
        'arc_mb' => round($totalArcMb / 1024 / 1024, 2),
        'ts' => date('H:i:s'),
    ]);
    exit;
}

$msg = '';
$msgType = '';
$action = $_POST['action'] ?? '';
$table = $_POST['table'] ?? '';
$file = $_POST['file'] ?? '';

function safeName($s) { return preg_match('/^[a-zA-Z0-9_]+$/', $s) === 1; }
function safeFile($s) { return preg_match('/^[a-zA-Z0-9_\-\.]+\.jsonl$/', $s) === 1; }

/**
 * SECURITE CRITIQUE : verifie l'integrite d'un fichier d'archive
 * avant tout TRUNCATE.
 *
 * Verifie :
 *  1) Fichier existe et > 0 octets
 *  2) Premiere ligne = #META valide
 *  3) Le nom de table dans META = celui attendu
 *  4) Nombre de lignes data = nombre de rows en BDD (exact)
 *  5) Premiere et derniere row sont du JSON valide
 *  6) Les colonnes du JSON correspondent aux colonnes BDD
 *
 * Retourne [bool ok, string message, int file_rows, int bdd_rows]
 */
function verifyArchive($conn, $table, $archivePath) {
    if (!file_exists($archivePath)) {
        return [false, "Fichier d'archive introuvable", 0, 0];
    }
    $size = filesize($archivePath);
    if ($size === 0) {
        return [false, "Fichier d'archive vide (0 octet)", 0, 0];
    }

    // Lit le header META
    $fp = fopen($archivePath, 'r');
    $firstLine = fgets($fp);
    if (strpos($firstLine, '#META ') !== 0) {
        fclose($fp);
        return [false, "Fichier sans entete #META — corrompu ou format invalide", 0, 0];
    }
    $meta = json_decode(substr($firstLine, 6), true);
    if (!$meta || !isset($meta['table']) || !isset($meta['columns'])) {
        fclose($fp);
        return [false, "Entete #META illisible ou incomplete", 0, 0];
    }
    if ($meta['table'] !== $table) {
        fclose($fp);
        return [false, "Table attendue '$table' mais fichier contient '" . $meta['table'] . "'", 0, 0];
    }

    // Compte les lignes data + valide premiere et derniere
    $fileRows = 0;
    $firstRow = null;
    $lastRow = null;
    while (($l = fgets($fp)) !== false) {
        $l = trim($l);
        if ($l === '' || $l[0] === '#') continue;
        $row = json_decode($l, true);
        if (!is_array($row)) {
            fclose($fp);
            return [false, "Ligne JSON invalide a l'index " . ($fileRows + 1), $fileRows, 0];
        }
        if ($firstRow === null) $firstRow = $row;
        $lastRow = $row;
        $fileRows++;
    }
    fclose($fp);

    // Verifie colonnes du JSON vs colonnes BDD
    if ($firstRow !== null) {
        $jsonCols = array_keys($firstRow);
        $bddColsRes = $conn->query("SHOW COLUMNS FROM `$table`");
        $bddCols = [];
        while ($c = $bddColsRes->fetch_assoc()) $bddCols[] = $c['Field'];
        $missing = array_diff($bddCols, $jsonCols);
        $extra = array_diff($jsonCols, $bddCols);
        if (!empty($missing)) {
            return [false, "Colonnes manquantes dans le fichier : " . implode(', ', $missing), $fileRows, 0];
        }
        if (!empty($extra)) {
            return [false, "Colonnes inconnues dans le fichier : " . implode(', ', $extra), $fileRows, 0];
        }
    }

    // Compare avec le nombre de rows en BDD
    $bddRows = (int)$conn->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0];
    if ($fileRows !== $bddRows) {
        return [false, "Mismatch : fichier = $fileRows rows, BDD = $bddRows rows. Re-exporter d'abord.", $fileRows, $bddRows];
    }

    return [true, "Verification OK : $fileRows rows identiques (BDD = fichier)", $fileRows, $bddRows];
}

// Recupere le CREATE TABLE complet d'une table (pour recreation au restore)
function getCreateTableSql($conn, $table) {
    $r = $conn->query("SHOW CREATE TABLE `$table`");
    if (!$r) return null;
    $row = $r->fetch_assoc();
    return $row['Create Table'] ?? null;
}

// Helper interne : export une table vers un fichier .jsonl
function doExport($conn, $table, $archiveDir) {
    set_time_limit(300);
    $ts = date('Y-m-d_His');
    $filename = "{$table}_{$ts}.jsonl";
    $path = "$archiveDir/$filename";

    // Total a exporter (pour la progression)
    $totalToExport = (int)$conn->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0];
    writeProgress(['step' => 'export', 'table' => $table, 'done' => 0, 'total' => $totalToExport, 'message' => "Demarrage de l'export de $table..."]);

    $fp = fopen($path, 'w');
    if (!$fp) { clearProgress(); return [false, "Impossible d'ouvrir $filename", 0]; }

    $colsRes = $conn->query("SHOW COLUMNS FROM `$table`");
    $cols = [];
    while ($c = $colsRes->fetch_assoc()) $cols[] = $c['Field'];

    // Recupere le CREATE TABLE complet pour pouvoir recreer la table sur une autre BDD
    $createSql = getCreateTableSql($conn, $table);

    fwrite($fp, "#META " . json_encode([
        'table' => $table,
        'exported_at' => date('Y-m-d H:i:s'),
        'columns' => $cols,
        'create_sql' => $createSql,
    ]) . "\n");

    $offset = 0;
    $batch = 5000;
    $total = 0;
    while (true) {
        $r = $conn->query("SELECT * FROM `$table` LIMIT $offset, $batch");
        if (!$r || $r->num_rows === 0) break;
        while ($row = $r->fetch_assoc()) {
            fwrite($fp, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
            $total++;
            if ($total % 1000 === 0) {
                writeProgress([
                    'step' => 'export', 'table' => $table,
                    'done' => $total, 'total' => $totalToExport,
                    'message' => "Export en cours : $total / $totalToExport lignes ecrites dans $filename"
                ]);
            }
        }
        if ($r->num_rows < $batch) break;
        $offset += $batch;
    }
    fclose($fp);
    writeProgress(['step' => 'export_done', 'table' => $table, 'done' => $total, 'total' => $total, 'message' => "Export termine : $total lignes ecrites"]);
    return [true, $filename, $total];
}

// Helper interne : truncate une table
function doTruncate($conn, $table) {
    writeProgress(['step' => 'truncate', 'table' => $table, 'message' => "Vidage de la table $table..."]);
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $before = (int)$conn->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0];
    $ok = $conn->query("TRUNCATE TABLE `$table`");
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    writeProgress(['step' => 'truncate_done', 'table' => $table, 'done' => $before, 'total' => $before, 'message' => "$before lignes supprimees"]);
    return [$ok, $before];
}

// Helper interne : restore le fichier le plus recent pour une table
function doRestore($conn, $table, $archiveDir) {
    $files = glob("$archiveDir/{$table}_*.jsonl");
    if (empty($files)) return [false, "Aucun fichier d'archive pour $table", 0];
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    return doRestoreFile($conn, basename($files[0]), $archiveDir);
}

function doRestoreFile($conn, $fileName, $archiveDir) {
    set_time_limit(600);
    $path = "$archiveDir/$fileName";
    if (!file_exists($path)) return [false, "Fichier introuvable", 0];

    $fp = fopen($path, 'r');
    $line = fgets($fp);
    $meta = null;
    if (strpos($line, '#META ') === 0) {
        $meta = json_decode(substr($line, 6), true);
    } else {
        rewind($fp);
    }
    if (!$meta || !isset($meta['table'])) {
        fclose($fp);
        return [false, "Fichier invalide (meta manquante)", 0];
    }
    $tbl = $meta['table'];
    if (!safeName($tbl)) {
        fclose($fp);
        return [false, "Nom de table invalide", 0];
    }

    // Total a restaurer (compte les lignes du fichier)
    $totalToRestore = 0;
    $tmp = fopen($path, 'r');
    fgets($tmp); // skip meta
    while (($l = fgets($tmp)) !== false) if (trim($l) !== '' && $l[0] !== '#') $totalToRestore++;
    fclose($tmp);
    writeProgress(['step' => 'restore', 'table' => $tbl, 'done' => 0, 'total' => $totalToRestore, 'message' => "Demarrage du restore de $tbl ($totalToRestore lignes)..."]);

    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn->begin_transaction();
    $count = 0;
    $batch = [];
    $batchSize = 500;
    $cols = null;
    try {
        while (($l = fgets($fp)) !== false) {
            $l = trim($l);
            if ($l === '' || $l[0] === '#') continue;
            $row = json_decode($l, true);
            if (!$row) continue;
            if ($cols === null) $cols = array_keys($row);
            $vals = [];
            foreach ($cols as $c) {
                $v = $row[$c] ?? null;
                if ($v === null) $vals[] = 'NULL';
                else $vals[] = "'" . $conn->real_escape_string($v) . "'";
            }
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= $batchSize) {
                $sql = "INSERT INTO `$tbl` (`" . implode('`,`', $cols) . "`) VALUES " . implode(',', $batch);
                $conn->query($sql);
                $count += count($batch);
                $batch = [];
                writeProgress([
                    'step' => 'restore', 'table' => $tbl,
                    'done' => $count, 'total' => $totalToRestore,
                    'message' => "Restore en cours : $count / $totalToRestore lignes inserees"
                ]);
            }
        }
        if (!empty($batch)) {
            $sql = "INSERT INTO `$tbl` (`" . implode('`,`', $cols) . "`) VALUES " . implode(',', $batch);
            $conn->query($sql);
            $count += count($batch);
        }
        $conn->commit();
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        fclose($fp);
        writeProgress(['step' => 'restore_done', 'table' => $tbl, 'done' => $count, 'total' => $count, 'message' => "Restore termine : $count lignes inserees"]);
        return [true, $tbl, $count];
    } catch (Exception $e) {
        $conn->rollback();
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        fclose($fp);
        writeProgress(['step' => 'error', 'table' => $tbl, 'message' => "Erreur : " . $e->getMessage()]);
        return [false, $e->getMessage(), 0];
    }
}

// ════════════════════════════════════════════
// ACTIONS
// ════════════════════════════════════════════

if ($action === 'export' && safeName($table)) {
    clearProgress();
    [$ok, $info, $count] = doExport($conn, $table, $archiveDir);
    clearProgress();
    $msg = $ok ? "Export OK : <b>$count lignes</b> dans <code>$info</code>" : "Erreur : $info";
    $msgType = $ok ? 'ok' : 'err';
}

// ─── TRUNCATE SECURISE ────────────────────────────────────────
// Refuse de vider si aucune archive recente verifiee.
if ($action === 'truncate' && safeName($table)) {
    clearProgress();
    writeProgress(['step' => 'verify', 'table' => $table, 'message' => "Verification de l'archive avant truncate..."]);
    $files = glob("$archiveDir/{$table}_*.jsonl");
    if (empty($files)) {
        $msg = "REFUS de vider <code>$table</code> : aucune archive trouvee. Exporte d'abord (bouton Export ou Vers Fichier).";
        $msgType = 'err';
    } else {
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        $latest = $files[0];
        [$vok, $vmsg, $fileRows, $bddRows] = verifyArchive($conn, $table, $latest);
        if (!$vok) {
            $msg = "REFUS de vider <code>$table</code> : verification echouee.<br><b>$vmsg</b><br>Fichier : <code>" . basename($latest) . "</code>";
            $msgType = 'err';
        } else {
            [$ok, $before] = doTruncate($conn, $table);
            if ($ok) {
                $msg = "Verification OK ($fileRows rows). Table <code>$table</code> videe : <b>$before lignes</b> supprimees.<br>Archive : <code>" . basename($latest) . "</code>";
                $msgType = 'ok';
            } else {
                $msg = "Erreur truncate : " . $conn->error;
                $msgType = 'err';
            }
        }
    }
}

// ─── BASCULE VERS FICHIER : Export + VERIFY + Truncate + setSource('file')
if ($action === 'to_file' && safeName($table)) {
    clearProgress();
    // 1) Export
    [$ok1, $info1, $count1] = doExport($conn, $table, $archiveDir);
    if (!$ok1) {
        $msg = "Bascule echouee a l'export : $info1";
        $msgType = 'err';
    } else {
        // 2) VERIFICATION OBLIGATOIRE avant truncate
        $exportPath = "$archiveDir/$info1";
        [$vok, $vmsg, $fileRows, $bddRows] = verifyArchive($conn, $table, $exportPath);
        if (!$vok) {
            // Verification echouee : on supprime le fichier corrompu et on garde la BDD intacte
            @unlink($exportPath);
            $msg = "REFUS de basculer vers Fichier : <b>$vmsg</b><br>La BDD est intacte. Le fichier export corrompu a ete supprime.";
            $msgType = 'err';
        } else {
            // 3) Truncate uniquement apres verification OK
            [$ok2, $before] = doTruncate($conn, $table);
            if (!$ok2) {
                $msg = "Export verifie OK mais truncate echoue : " . $conn->error . "<br>BDD intacte, fichier conserve : <code>$info1</code>";
                $msgType = 'err';
            } else {
                setDataSourceMode($table, 'file');
                $msg = "Bascule vers Fichier OK<br>" .
                       "&bull; Export : <b>$count1 lignes</b> dans <code>$info1</code><br>" .
                       "&bull; Verification : $vmsg<br>" .
                       "&bull; Truncate : $before lignes supprimees<br>" .
                       "&bull; Source = <b>Fichier</b>";
                $msgType = 'ok';
            }
        }
    }
}

// BASCULE VERS BDD : restore latest + setSource('bdd')
if ($action === 'to_bdd' && safeName($table)) {
    clearProgress();
    // Verifie que la table est bien vide avant restore (sinon doublons)
    $cnt = (int)$conn->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0];
    if ($cnt > 0) {
        $msg = "La table <code>$table</code> contient deja $cnt lignes. Vide-la d'abord (bouton Vider) pour eviter les doublons.";
        $msgType = 'err';
    } else {
        [$ok, $info, $count] = doRestore($conn, $table, $archiveDir);
        if ($ok) {
            setDataSourceMode($table, 'bdd');
            $msg = "Bascule vers BDD OK : <b>$count lignes</b> restaurees dans <code>$info</code>. Source = <b>BDD</b>.";
            $msgType = 'ok';
        } else {
            $msg = "Erreur restore : $info";
            $msgType = 'err';
        }
    }
}

// ─── VERIFICATION SEULE (lecture, ne touche a rien)
if ($action === 'verify' && safeName($table)) {
    $files = glob("$archiveDir/{$table}_*.jsonl");
    if (empty($files)) {
        $msg = "Aucune archive trouvee pour <code>$table</code>.";
        $msgType = 'err';
    } else {
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        $latest = $files[0];
        [$vok, $vmsg, $fileRows, $bddRows] = verifyArchive($conn, $table, $latest);
        $msg = ($vok ? "Verification OK" : "Verification ECHOUEE") . " — <b>$vmsg</b><br>" .
               "Archive : <code>" . basename($latest) . "</code> ($fileRows rows) | BDD : $bddRows rows";
        $msgType = $vok ? 'ok' : 'err';
    }
}

if ($action === 'restore' && safeFile($file)) {
    [$ok, $info, $count] = doRestoreFile($conn, $file, $archiveDir);
    $msg = $ok ? "Restauration OK : <b>$count lignes</b> dans <code>$info</code>" : "Erreur : $info";
    $msgType = $ok ? 'ok' : 'err';
}

if ($action === 'delete_file' && safeFile($file)) {
    $path = "$archiveDir/$file";
    if (file_exists($path) && unlink($path)) {
        $msg = "Fichier <code>$file</code> supprime";
        $msgType = 'ok';
    } else {
        $msg = "Suppression echouee";
        $msgType = 'err';
    }
}

if ($action === 'force_source' && safeName($table)) {
    $mode = $_POST['mode'] ?? 'bdd';
    setDataSourceMode($table, $mode === 'file' ? 'file' : 'bdd');
    $msg = "Source de <code>$table</code> forcee a <b>" . ($mode === 'file' ? 'Fichier' : 'BDD') . "</b> (sans deplacer les donnees)";
    $msgType = 'ok';
}

// BOUTON GLOBAL : bascule la source de TOUTES les tables d'un coup (sans deplacer les donnees)
if ($action === 'force_source_all') {
    $mode    = ($_POST['mode'] ?? 'bdd') === 'file' ? 'file' : 'bdd';
    $cfgPath = __DIR__ . '/../config/data_source.json';
    if ($mode === 'bdd') {
        // Tout en BDD : config vide => toutes les tables lisent dans MySQL (toujours sans risque)
        file_put_contents($cfgPath, "{}");
        $msg = "Toutes les tables basculees sur <b>BDD</b> &mdash; le site lit desormais entierement dans MySQL.";
        $msgType = 'ok';
    } else {
        // Tout en Fichier : on ne bascule que les tables que le code sait lire en fichier
        // (fileBackedTables) ET qui ont deja une archive — sinon la page afficherait 0 ligne.
        $newCfg = []; $switched = []; $noArchive = [];
        foreach (fileBackedTables() as $tn) {
            if (latestArchivePath($tn) !== null) { $newCfg[$tn] = 'file'; $switched[] = $tn; }
            else { $noArchive[] = $tn; }
        }
        file_put_contents($cfgPath, $newCfg ? json_encode($newCfg, JSON_PRETTY_PRINT) : "{}");
        if ($switched) {
            $msg = count($switched) . " table(s) basculee(s) sur <b>Fichier</b> : <code>" . implode('</code> <code>', $switched) . "</code>.";
            if ($noArchive) $msg .= " Ignorees faute d'archive : <code>" . implode('</code> <code>', $noArchive) . "</code> (fais un Export d'abord).";
            $msgType = 'ok';
        } else {
            $msg = "Aucune table basculee sur Fichier : " . ($noArchive
                ? "les tables supportees (<code>" . implode('</code> <code>', $noArchive) . "</code>) n'ont pas encore d'archive."
                : "aucune table supportee par le code en mode fichier.");
            $msgType = 'err';
        }
    }
}

// BOUTON GLOBAL : exporte en .jsonl UNIQUEMENT les tables qui n'ont pas encore d'archive.
// Verifie l'existence d'une archive AVANT — ne re-exporte jamais une table deja archivee,
// ne touche pas a la BDD (c'est un simple export, pas un truncate).
if ($action === 'export_missing') {
    clearProgress();
    $allT = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name");
    $exported = []; $skipped = []; $failed = [];
    while ($allT && ($row = $allT->fetch_assoc())) {
        $tn = $row['table_name'];
        if (!safeName($tn)) continue;
        if (latestArchivePath($tn) !== null) { $skipped[] = $tn; continue; } // archive deja presente -> on saute
        [$eok, $einfo, $ecount] = doExport($conn, $tn, $archiveDir);
        if ($eok) $exported[] = "$tn ($ecount)";
        else      $failed[]   = "$tn : $einfo";
    }
    clearProgress();
    $parts = [];
    if ($exported) $parts[] = "<b>" . count($exported) . " table(s) exportee(s)</b> : <code>" . implode('</code> <code>', $exported) . "</code>";
    if ($skipped)  $parts[] = count($skipped) . " ignoree(s) (archive deja presente)";
    if ($failed)   $parts[] = "<b>" . count($failed) . " echec(s)</b> : " . implode(' ; ', $failed);
    $msg = $parts ? implode('<br>', $parts) : "Aucune table a exporter.";
    $msgType = $failed ? 'err' : 'ok';
}

// ════════════════════════════════════════════
// RECUPERATION : tailles tables + fichiers
// ════════════════════════════════════════════
$dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$tablesRes = $conn->query("
    SELECT table_name, table_rows,
           ROUND((data_length + index_length) / 1024 / 1024, 2) AS total_mb
    FROM information_schema.tables
    WHERE table_schema = '$dbName'
    ORDER BY (data_length + index_length) DESC
");
$tables = [];
while ($r = $tablesRes->fetch_assoc()) $tables[] = $r;

$files = [];
foreach (glob("$archiveDir/*.jsonl") as $f) {
    $files[] = [
        'name' => basename($f),
        'size_mb' => round(filesize($f) / 1024 / 1024, 2),
        'mtime' => date('Y-m-d H:i:s', filemtime($f)),
    ];
}
usort($files, fn($a, $b) => strcmp($b['mtime'], $a['mtime']));

$fileBacked = fileBackedTables();

function fmt($n) { return number_format((float)$n, 0, ',', ' '); }
?>
<?php
// Calcul des KPIs
$totalDbMb = array_sum(array_column($tables, 'total_mb'));
$totalDbRows = array_sum(array_column($tables, 'table_rows'));
$totalFilesMb = array_sum(array_column($files, 'size_mb'));
$tablesInFile = 0;
foreach ($tables as $t) if (dataSourceMode($t['table_name']) === 'file') $tablesInFile++;
$maxMb = !empty($tables) ? max(array_column($tables, 'total_mb')) : 1;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BDD Archive — <?= htmlspecialchars($dbName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0a0e14;
    --panel: #11161f;
    --panel-2: #161c28;
    --border: #232b3a;
    --border-soft: #1c2330;
    --text: #d9e1ec;
    --text-dim: #7a869a;
    --text-muted: #525d72;
    --accent: #6366f1;
    --accent-2: #8b5cf6;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --gold: #fbbf24;
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(180deg, #0a0e14 0%, #060912 100%);
    background-attachment: fixed;
    color: var(--text);
    min-height: 100vh;
    padding: 24px 16px 60px;
    -webkit-font-smoothing: antialiased;
  }
  .wrap { max-width: 1400px; margin: 0 auto; }
  code, .mono { font-family: 'JetBrains Mono', 'SF Mono', Consolas, monospace; }
  code { background: #1a2230; color: #a5b4fc; padding: 2px 7px; border-radius: 4px; font-size: 12px; }

  /* HEADER */
  .header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 16px;
  }
  .title { font-size: 28px; font-weight: 700; letter-spacing: -0.5px; margin: 0; color: #fff; }
  .subtitle { color: var(--text-dim); font-size: 13px; margin-top: 4px; }
  .subtitle code { background: transparent; color: var(--text-dim); padding: 0; }
  .pill {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--panel); border: 1px solid var(--border);
    padding: 6px 12px; border-radius: 20px; font-size: 12px; color: var(--text-dim);
  }
  .pill .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--success); animation: pulse 2s infinite; }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }

  /* KPI CARDS */
  .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
  .kpi {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px 18px;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
  }
  .kpi:hover { border-color: var(--accent); transform: translateY(-1px); }
  .kpi::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    opacity: 0; transition: opacity 0.2s;
  }
  .kpi:hover::before { opacity: 1; }
  .kpi-label { color: var(--text-muted); font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; }
  .kpi-value { font-size: 24px; font-weight: 700; color: #fff; line-height: 1; font-variant-numeric: tabular-nums; }
  .kpi-unit { font-size: 13px; color: var(--text-dim); margin-left: 4px; font-weight: 500; }
  .kpi-sub { font-size: 11px; color: var(--text-dim); margin-top: 6px; }
  .kpi.danger .kpi-value { color: var(--danger); }
  .kpi.warning .kpi-value { color: var(--warning); }
  .kpi.success .kpi-value { color: var(--success); }
  .kpi.info .kpi-value { color: var(--info); }

  /* MESSAGES */
  .msg {
    padding: 14px 18px; border-radius: 10px; margin-bottom: 20px;
    border: 1px solid; font-size: 13px; line-height: 1.6;
    display: flex; align-items: flex-start; gap: 12px;
  }
  .msg .icon { font-size: 18px; flex-shrink: 0; }
  .msg.ok { background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.3); color: #6ee7b7; }
  .msg.err { background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.3); color: #fca5a5; }

  /* INFO PANELS */
  .panel-info {
    background: var(--panel); border: 1px solid var(--border);
    border-left: 3px solid var(--accent);
    padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;
    font-size: 13px; line-height: 1.7; color: var(--text-dim);
  }
  .panel-info b { color: #fff; }
  .panel-info.security { border-left-color: var(--success); }
  .panel-info .title-mini {
    color: #fff; font-weight: 600; font-size: 13px;
    display: flex; align-items: center; gap: 8px; margin-bottom: 6px;
  }

  /* SECTION HEADER */
  .section-h {
    display: flex; justify-content: space-between; align-items: center;
    margin: 32px 0 14px;
  }
  .section-h h2 {
    font-size: 16px; font-weight: 600; color: #fff; margin: 0;
    letter-spacing: -0.2px;
    display: flex; align-items: center; gap: 10px;
  }
  .section-h h2 .count {
    background: var(--panel); border: 1px solid var(--border);
    padding: 2px 9px; border-radius: 12px; font-size: 12px;
    color: var(--text-dim); font-weight: 500;
  }
  .search-input {
    background: var(--panel); border: 1px solid var(--border);
    color: var(--text); padding: 7px 12px; border-radius: 6px;
    font-size: 13px; width: 220px; font-family: inherit;
    outline: none; transition: border 0.2s;
  }
  .search-input:focus { border-color: var(--accent); }
  .search-input::placeholder { color: var(--text-muted); }

  /* TABLE */
  .table-card {
    background: var(--panel); border: 1px solid var(--border);
    border-radius: 12px; overflow: hidden;
  }
  table { width: 100%; border-collapse: collapse; }
  th { background: var(--panel-2); color: var(--text-dim); font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 11px 14px; text-align: left; border-bottom: 1px solid var(--border); }
  td { padding: 10px 14px; border-bottom: 1px solid var(--border-soft); font-size: 13px; color: var(--text); }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr { transition: background 0.15s; }
  tbody tr:hover { background: rgba(99, 102, 241, 0.04); }
  td.num { text-align: right; font-variant-numeric: tabular-nums; font-family: 'JetBrains Mono', monospace; }
  .table-name { font-weight: 600; color: #fff; font-family: 'JetBrains Mono', monospace; font-size: 12.5px; }
  .table-name.heavy { color: var(--warning); }

  /* SIZE BAR */
  .size-bar-wrap { display: flex; align-items: center; gap: 8px; justify-content: flex-end; }
  .size-bar {
    width: 40px; height: 6px; background: var(--border-soft);
    border-radius: 3px; overflow: hidden; flex-shrink: 0;
  }
  .size-bar-fill {
    height: 100%; background: linear-gradient(90deg, var(--accent), var(--accent-2));
    border-radius: 3px;
  }
  .size-bar-fill.warning { background: linear-gradient(90deg, var(--warning), #fb923c); }
  .size-bar-fill.danger { background: linear-gradient(90deg, var(--danger), #f87171); }

  /* BADGES */
  .badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.3px;
  }
  .badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; }
  .badge-bdd { background: rgba(59, 130, 246, 0.12); color: #93c5fd; }
  .badge-bdd::before { background: #3b82f6; }
  .badge-file { background: rgba(245, 158, 11, 0.12); color: #fcd34d; }
  .badge-file::before { background: var(--warning); }
  .badge-supported { background: rgba(16, 185, 129, 0.12); color: #6ee7b7; }
  .badge-supported::before { background: var(--success); }
  .badge-unsupported { background: rgba(239, 68, 68, 0.12); color: #fca5a5; }
  .badge-unsupported::before { background: var(--danger); }

  /* BUTTONS */
  .actions { display: flex; gap: 4px; flex-wrap: wrap; justify-content: flex-end; }
  .btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 10px; border-radius: 5px; border: none; cursor: pointer;
    font-size: 11px; font-weight: 600; font-family: inherit;
    transition: all 0.15s; white-space: nowrap;
  }
  .btn:hover { transform: translateY(-1px); }
  .btn:active { transform: translateY(0); }
  .btn:disabled { opacity: 0.3; cursor: not-allowed; transform: none; }
  .btn-export { background: rgba(59, 130, 246, 0.15); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.3); }
  .btn-export:hover:not(:disabled) { background: rgba(59, 130, 246, 0.25); }
  .btn-trunc { background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); }
  .btn-trunc:hover:not(:disabled) { background: rgba(239, 68, 68, 0.25); }
  .btn-restore { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.3); }
  .btn-restore:hover:not(:disabled) { background: rgba(16, 185, 129, 0.25); }
  .btn-del { background: rgba(100, 116, 139, 0.15); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3); }
  .btn-del:hover:not(:disabled) { background: rgba(100, 116, 139, 0.25); }
  .btn-toFile { background: linear-gradient(135deg, #f59e0b, #d97706); color: #000; border: 1px solid #f59e0b; }
  .btn-toFile:hover:not(:disabled) { box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.3); }
  .btn-toBdd { background: linear-gradient(135deg, #06b6d4, #0891b2); color: #000; border: 1px solid #06b6d4; }
  .btn-toBdd:hover:not(:disabled) { box-shadow: 0 0 0 2px rgba(6, 182, 212, 0.3); }
  .btn-verify { background: rgba(168, 85, 247, 0.15); color: #c4b5fd; border: 1px solid rgba(168, 85, 247, 0.3); }
  .btn-verify:hover:not(:disabled) { background: rgba(168, 85, 247, 0.25); }
  form { display: inline-block; margin: 0; }

  /* EMPTY STATE */
  .empty {
    padding: 40px 20px; text-align: center; color: var(--text-muted);
    background: var(--panel); border: 1px dashed var(--border); border-radius: 10px;
  }
  .empty .icon { font-size: 36px; opacity: 0.4; margin-bottom: 8px; }

  /* FOOTER */
  .footer-info {
    margin-top: 40px; padding: 16px 18px;
    background: var(--panel); border: 1px solid var(--border-soft);
    border-radius: 8px; font-size: 11.5px; color: var(--text-muted);
    line-height: 1.7;
  }
  .footer-info code { background: transparent; color: var(--text-dim); }

  /* RESPONSIVE */
  @media (max-width: 768px) {
    .actions { justify-content: flex-start; }
    th, td { padding: 8px 8px; font-size: 12px; }
    .title { font-size: 22px; }
  }

  /* ─────── ANIMATIONS ─────── */

  /* Overlay loading plein ecran */
  #loadingOverlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(6, 9, 18, 0.92);
    backdrop-filter: blur(8px);
    display: none;
    align-items: center; justify-content: center;
    flex-direction: column; gap: 24px;
    animation: fadeIn 0.2s ease;
  }
  #loadingOverlay.show { display: flex; }
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

  .loading-card {
    background: var(--panel); border: 1px solid var(--border);
    padding: 32px 48px; border-radius: 16px;
    box-shadow: 0 20px 60px rgba(99, 102, 241, 0.2);
    text-align: center; max-width: 420px;
    animation: cardPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  }
  @keyframes cardPop {
    from { transform: scale(0.85); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
  }

  /* Spinner orbital */
  .spinner {
    width: 64px; height: 64px; margin: 0 auto 20px;
    position: relative;
  }
  .spinner::before, .spinner::after {
    content: ''; position: absolute; inset: 0;
    border-radius: 50%; border: 3px solid transparent;
  }
  .spinner::before {
    border-top-color: var(--accent);
    border-right-color: var(--accent);
    animation: spin 1s linear infinite;
  }
  .spinner::after {
    border-bottom-color: var(--accent-2);
    animation: spin 1.5s linear infinite reverse;
    inset: 8px;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  .spinner-core {
    position: absolute; inset: 22px;
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    border-radius: 50%;
    animation: pulse2 1.2s ease-in-out infinite;
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.6);
  }
  @keyframes pulse2 {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.6; }
  }

  .loading-title { color: #fff; font-size: 16px; font-weight: 600; margin: 0 0 4px; }
  .loading-sub { color: var(--text-dim); font-size: 13px; }

  /* Barre de progression pulse */
  .progress-bar {
    margin-top: 18px; height: 4px; background: var(--border-soft);
    border-radius: 2px; overflow: hidden; position: relative;
  }
  .progress-bar::after {
    content: ''; position: absolute; top: 0; left: -40%;
    width: 40%; height: 100%;
    background: linear-gradient(90deg, transparent, var(--accent), var(--accent-2), transparent);
    animation: slideBar 1.3s ease-in-out infinite;
  }
  @keyframes slideBar {
    to { left: 100%; }
  }

  /* Animation du message resultat */
  .msg {
    animation: msgSlide 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    transform-origin: top center;
  }
  @keyframes msgSlide {
    0% { opacity: 0; transform: translateY(-20px) scale(0.95); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
  }
  .msg.ok { position: relative; overflow: hidden; }
  .msg.ok::after {
    content: ''; position: absolute; top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.15), transparent);
    animation: shine 1.5s ease-out;
  }
  @keyframes shine { to { left: 100%; } }
  .msg.err {
    animation: msgShake 0.5s ease;
  }
  @keyframes msgShake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-6px); }
    40%, 80% { transform: translateX(6px); }
  }

  /* Click feedback boutons */
  .btn { position: relative; overflow: hidden; }
  .btn::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at center, rgba(255,255,255,0.4) 0%, transparent 70%);
    opacity: 0; transition: opacity 0.4s;
    pointer-events: none;
  }
  .btn:active::after { opacity: 1; transition: 0s; }

  /* Pulse subtil sur les KPI au load */
  .kpi { animation: kpiIn 0.4s ease backwards; }
  .kpi:nth-child(1) { animation-delay: 0.05s; }
  .kpi:nth-child(2) { animation-delay: 0.1s; }
  .kpi:nth-child(3) { animation-delay: 0.15s; }
  .kpi:nth-child(4) { animation-delay: 0.2s; }
  @keyframes kpiIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
  }

  /* Icone success bounce */
  .msg .icon-anim {
    display: inline-block;
    animation: iconBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s backwards;
  }
  @keyframes iconBounce {
    0% { transform: scale(0) rotate(-180deg); opacity: 0; }
    60% { transform: scale(1.3) rotate(10deg); opacity: 1; }
    100% { transform: scale(1) rotate(0); }
  }

  /* Points anim du titre */
  .dots span {
    display: inline-block;
    animation: dotBlink 1.4s infinite;
    opacity: 0.3;
  }
  .dots span:nth-child(2) { animation-delay: 0.2s; }
  .dots span:nth-child(3) { animation-delay: 0.4s; }
  @keyframes dotBlink { 0%, 60%, 100% { opacity: 0.3; } 30% { opacity: 1; } }

  /* Barre de travail toujours visible en haut quand op en cours */
  #workingBar {
    position: fixed; top: 0; left: 0; right: 0; height: 3px;
    background: var(--border-soft); z-index: 10000;
    display: none;
  }
  #workingBar.show { display: block; }
  #workingBar::after {
    content: ''; display: block; height: 100%; width: 30%;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    animation: workBar 1.2s linear infinite;
  }
  @keyframes workBar {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(400%); }
  }
</style>
</head>
<body>
<div class="wrap">

<div class="header">
  <div>
    <h1 class="title">Archivage BDD</h1>
    <div class="subtitle"><code><?= htmlspecialchars($dbName) ?></code> &middot; Stockage <code>archives/</code> &middot; Config <code>config/data_source.json</code></div>
  </div>
  <div class="pill"><span class="dot"></span> Connecte</div>
</div>

<?php
$_envIsLocal = defined('BK_IS_LOCAL') && BK_IS_LOCAL;
$_envHost    = $_SERVER['HTTP_HOST'] ?? 'cli';
$_envUser    = $username ?? '?';
$_envHasLocalFile = file_exists(__DIR__ . '/../core/credentials_local.php');
$_envSource  = ($_envIsLocal && $_envHasLocalFile) ? 'core/credentials_local.php' : 'core/credentials.php';
$_envColor   = $_envIsLocal ? '#10b981' : '#ef4444';
$_envBg      = $_envIsLocal ? 'rgba(16,185,129,.08)' : 'rgba(239,68,68,.08)';
$_envLabel   = $_envIsLocal ? 'LOCAL' : 'PROD';
?>
<div style="margin:0 0 20px;padding:14px 18px;border:2px solid <?= $_envColor ?>;border-radius:12px;background:<?= $_envBg ?>;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
  <div style="font-weight:800;font-size:20px;letter-spacing:1px;color:<?= $_envColor ?>;padding:4px 12px;border:2px solid <?= $_envColor ?>;border-radius:8px;font-family:'JetBrains Mono',monospace;">
    <?= $_envLabel ?>
  </div>
  <div style="display:grid;grid-template-columns:auto 1fr;gap:4px 12px;font-family:'JetBrains Mono',monospace;font-size:13px;flex:1;min-width:280px;">
    <span style="color:#7a869a;">Host :</span>           <span style="color:#d9e1ec;"><?= htmlspecialchars($_envHost) ?></span>
    <span style="color:#7a869a;">DB :</span>             <span style="color:#fbbf24;font-weight:600;"><?= htmlspecialchars($dbName) ?></span>
    <span style="color:#7a869a;">MySQL user :</span>     <span style="color:#d9e1ec;"><?= htmlspecialchars($_envUser) ?></span>
    <span style="color:#7a869a;">Credentials :</span>    <span style="color:#06b6d4;"><?= htmlspecialchars($_envSource) ?></span>
  </div>
</div>

<!-- KPI CARDS -->
<div class="kpis">
  <div class="kpi">
    <div class="kpi-label">Tables</div>
    <div class="kpi-value"><?= count($tables) ?></div>
    <div class="kpi-sub"><?= fmt($totalDbRows) ?> lignes au total</div>
  </div>
  <div class="kpi <?= $totalDbMb > 500 ? 'danger' : ($totalDbMb > 200 ? 'warning' : '') ?>">
    <div class="kpi-label">Taille BDD</div>
    <div class="kpi-value"><?= number_format($totalDbMb, 1, ',', ' ') ?><span class="kpi-unit">MB</span></div>
    <div class="kpi-sub"><?= $totalDbMb > 500 ? 'Pleine — archive recommandee' : 'Espace OK' ?></div>
  </div>
  <div class="kpi info">
    <div class="kpi-label">Archives</div>
    <div class="kpi-value"><?= count($files) ?></div>
    <div class="kpi-sub"><?= number_format($totalFilesMb, 1, ',', ' ') ?> MB stockes</div>
  </div>
  <div class="kpi <?= $tablesInFile > 0 ? 'warning' : 'success' ?>">
    <div class="kpi-label">Mode Fichier</div>
    <div class="kpi-value"><?= $tablesInFile ?><span class="kpi-unit">/ <?= count($tables) ?></span></div>
    <div class="kpi-sub"><?= $tablesInFile === 0 ? 'Toutes en BDD' : "$tablesInFile table" . ($tablesInFile > 1 ? 's' : '') . ' deportee' . ($tablesInFile > 1 ? 's' : '') ?></div>
  </div>
</div>

<?php if ($msg): ?>
  <div class="msg <?= $msgType ?>">
    <span class="icon"><?= $msgType === 'ok' ? '&#10003;' : '&#9888;' ?></span>
    <div><?= $msg ?></div>
  </div>
<?php endif; ?>

<?php
// Etat global actuel : ou le site pioche-t-il les donnees ?
$_allBdd  = ($tablesInFile === 0);
$_allFile = ($tablesInFile > 0 && $tablesInFile === count($tables));
if ($_allBdd)      { $_srcLbl = 'la BDD MySQL';                  $_srcCol = '#3b82f6'; $_srcIco = '&#128451;&#65039;'; }
elseif ($_allFile) { $_srcLbl = 'les fichiers .jsonl';          $_srcCol = '#f59e0b'; $_srcIco = '&#128193;'; }
else               { $_srcLbl = 'sources MIXTES (' . $tablesInFile . ' table' . ($tablesInFile > 1 ? 's' : '') . ' en Fichier, le reste en BDD)'; $_srcCol = '#f59e0b'; $_srcIco = '&#9888;&#65039;'; }
// Nombre de tables sans archive (= a exporter)
$_missingCount = 0;
foreach ($tables as $_t) { if (latestArchivePath($_t['table_name']) === null) $_missingCount++; }
// Liste des archives a restaurer : 1 fichier (le plus recent) par table
$_restoreList = [];
foreach (glob($archiveDir . '/*.jsonl') as $_af) {
    $_abn = basename($_af);
    if (preg_match('/^(.+?)_\d{4}-\d{2}-\d{2}_\d{6}\.jsonl$/', $_abn, $_am)) {
        $_atbl = $_am[1];
        $_amt  = filemtime($_af);
        if (!isset($_restoreList[$_atbl]) || $_amt > $_restoreList[$_atbl]['mt']) {
            $_restoreList[$_atbl] = ['file' => $_abn, 'mt' => $_amt];
        }
    }
}
$_restoreFiles = array_map(function($x) use ($archiveDir) {
    return ['f' => $x['file'], 's' => (int)(@filesize($archiveDir . '/' . $x['file']) ?: 0)];
}, array_values($_restoreList));
$_restoreCount = count($_restoreFiles);
?>
<div class="panel-info" style="border-color:var(--accent);">
  <div class="title-mini"><span style="color:var(--accent)">&#127760;</span> Source globale des donnees &mdash; tout diriger d'un coup</div>

  <!-- ETAT ACTUEL : ou le programme pioche les datas (bien visible) -->
  <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin:12px 0 14px;padding:16px 20px;background:#0d1117;border:2px solid <?= $_srcCol ?>;border-radius:10px;">
    <span style="font-size:12px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.6px;font-weight:700;">&#128205; Le site lit actuellement dans</span>
    <span style="font-size:19px;font-weight:900;color:<?= $_srcCol ?>;"><?= $_srcIco ?> <?= $_srcLbl ?></span>
  </div>

  <div style="margin:0 0 12px;color:var(--text-dim);font-size:12px;line-height:1.6;">
    &bull; <b>Exporter les tables manquantes</b> : cree le fichier .jsonl <em>uniquement</em> pour les tables qui n'en ont pas encore (verifie avant, ne re-exporte jamais l'existant). Ne touche pas a la BDD.<br>
    &bull; <b>Tout restaurer depuis les fichiers</b> : reconstruit la BDD a partir des .jsonl de <code>archives/</code>. Table par table, en chunks (anti-timeout sur grosse base) : cree les tables manquantes, restaure, <em>verifie le nombre de lignes</em>, et <b>saute les tables qui ont deja des donnees</b> (zero doublon, rejouable sans risque).<br>
    &bull; <b>Tout BDD</b> : le site lit 100% dans MySQL (toujours sans risque).<br>
    &bull; <b>Tout Fichier</b> : ne bascule que les tables supportees par le code <em>et</em> qui ont deja une archive (les autres restent en BDD pour ne rien casser).
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <form method="POST" onsubmit="return confirm('Exporter en .jsonl les <?= $_missingCount ?> table(s) qui n ont pas encore d archive ?\n\nCela peut prendre plusieurs minutes selon la taille. Ne ferme pas la page.')">
      <input type="hidden" name="bk_key" value="<?= htmlspecialchars($key) ?>">
      <input type="hidden" name="action" value="export_missing">
      <button class="btn btn-export"<?= $_missingCount === 0 ? ' disabled title="Toutes les tables ont deja une archive"' : '' ?>>&#128228; Exporter les <?= $_missingCount ?> table(s) manquante(s)</button>
    </form>
    <button type="button" class="btn" onclick="restoreAll()" style="background:linear-gradient(135deg,#22c55e,#16a34a);color:#04210f;border:1px solid #22c55e;font-weight:700;"<?= $_restoreCount === 0 ? ' disabled title="Aucune archive .jsonl dans le dossier archives/"' : '' ?>>&#128229; Tout restaurer depuis les fichiers (<?= $_restoreCount ?>)</button>
    <form method="POST" onsubmit="return confirm('Faire lire TOUT le site dans la BDD MySQL ?')">
      <input type="hidden" name="bk_key" value="<?= htmlspecialchars($key) ?>">
      <input type="hidden" name="action" value="force_source_all">
      <input type="hidden" name="mode" value="bdd">
      <button class="btn btn-toBdd"<?= $_allBdd ? ' disabled title="Le site lit deja entierement dans la BDD"' : '' ?>>&#128451;&#65039; Tout lire depuis la BDD</button>
    </form>
    <form method="POST" onsubmit="return confirm('Faire lire le site dans les fichiers .jsonl (tables supportees avec archive) ?')">
      <input type="hidden" name="bk_key" value="<?= htmlspecialchars($key) ?>">
      <input type="hidden" name="action" value="force_source_all">
      <input type="hidden" name="mode" value="file">
      <button class="btn btn-toFile"<?= $_allFile ? ' disabled title="Le site lit deja dans les fichiers"' : '' ?>>&#128193; Tout lire depuis les fichiers</button>
    </form>
  </div>
</div>

<div class="panel-info">
  <div class="title-mini"><span style="color:var(--accent)">&#9881;</span> Comment basculer</div>
  &bull; <span class="btn btn-toFile" style="cursor:default">&rarr; Fichier</span> = Export + Vide la BDD (apres verif) + Source = Fichier<br>
  &bull; <span class="btn btn-toBdd" style="cursor:default">&rarr; BDD</span> = Restore le fichier + Source = BDD<br>
  &bull; <span class="badge badge-supported">SUPPORTE</span> = le code applicatif sait lire le fichier (sinon les pages afficheront 0 ligne)<br>
  &bull; Tables supportees : <code><?= implode('</code> <code>', $fileBacked) ?></code>
</div>

<div class="panel-info security">
  <div class="title-mini"><span style="color:var(--success)">&#128274;</span> Securite TRUNCATE</div>
  Avant toute suppression de donnees BDD, le systeme verifie : <b>(1)</b> fichier present, <b>(2)</b> entete #META valide, <b>(3)</b> colonnes identiques, <b>(4)</b> nombre de lignes fichier = BDD <em>exact</em>, <b>(5)</b> JSON parseable.<br>
  Si la verification echoue : la BDD reste intacte, le fichier corrompu est supprime.
</div>

<div class="section-h">
  <h2>Tables de la BDD <span class="count"><?= count($tables) ?></span></h2>
  <input type="text" id="tableFilter" class="search-input" placeholder="Filtrer les tables..." oninput="filterTables(this.value)">
</div>

<div class="table-card">
<table>
  <thead>
    <tr>
      <th>Table</th>
      <th>Source</th>
      <th>Patch</th>
      <th class="num">Lignes BDD</th>
      <th class="num">Taille</th>
      <th style="text-align:right">Actions</th>
    </tr>
  </thead>
  <tbody id="tblBody">
<?php foreach ($tables as $t):
    $tn = $t['table_name'];
    $src = dataSourceMode($tn);
    $isSupported = in_array($tn, $fileBacked, true);
    $isHeavy = $t['total_mb'] >= 5;
    // Recherche d'une archive recente pour cette table
    $tableArchives = glob("$archiveDir/{$tn}_*.jsonl");
    $hasArchive = !empty($tableArchives);
    if ($hasArchive) {
        usort($tableArchives, fn($a, $b) => filemtime($b) - filemtime($a));
        $latestName = basename($tableArchives[0]);
    }
?>
    <tr data-name="<?= htmlspecialchars(strtolower($tn)) ?>">
      <td><span class="table-name <?= $isHeavy ? 'heavy' : '' ?>"><?= htmlspecialchars($tn) ?></span></td>
      <td>
        <?php if ($src === 'file'): ?>
          <span class="badge badge-file">Fichier</span>
        <?php else: ?>
          <span class="badge badge-bdd">BDD</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($isSupported): ?>
          <span class="badge badge-supported">OK</span>
        <?php else: ?>
          <span class="badge badge-unsupported" title="Le code ne sait pas encore lire le fichier pour cette table">N/A</span>
        <?php endif; ?>
      </td>
      <td class="num"><?= fmt($t['table_rows']) ?></td>
      <td class="num">
        <div class="size-bar-wrap">
          <div class="size-bar"><div class="size-bar-fill <?= $t['total_mb'] > 50 ? 'danger' : ($t['total_mb'] > 10 ? 'warning' : '') ?>" style="width:<?= min(100, ($t['total_mb'] / $maxMb) * 100) ?>%"></div></div>
          <span style="min-width:55px;text-align:right;"><?= number_format($t['total_mb'], 2, ',', ' ') ?> MB</span>
        </div>
      </td>
      <td><div class="actions">
        <?php if ($src === 'bdd'): ?>
          <form method="POST" onsubmit="return confirm('Basculer <?= $tn ?> vers FICHIER ?\n\n1) Export en .jsonl\n2) VERIFICATION integrite\n3) Truncate UNIQUEMENT si verif OK\n4) Source = Fichier<?= $isSupported ? '' : '\n\nATTENTION : non supporte par le code, les pages afficheront 0 ligne.' ?>')">
            <input type="hidden" name="bk_key" value="<?= htmlspecialchars($key) ?>">
            <input type="hidden" name="action" value="to_file">
            <input type="hidden" name="table" value="<?= htmlspecialchars($tn) ?>">
            <button class="btn btn-toFile">&rarr; Fichier</button>
          </form>
        <?php else: ?>
          <form method="POST" onsubmit="return confirm('Basculer <?= $tn ?> vers BDD ?\n\n1) Restore le fichier en BDD\n2) Le site lira desormais dans la BDD')">
            <input type="hidden" name="bk_key" value="<?= htmlspecialchars($key) ?>">
            <input type="hidden" name="action" value="to_bdd">
            <input type="hidden" name="table" value="<?= htmlspecialchars($tn) ?>">
            <button class="btn btn-toBdd">&rarr; BDD</button>
          </form>
        <?php endif; ?>

        <form method="POST" onsubmit="return confirm('Exporter <?= $tn ?> sans vider ?')">
          <input type="hidden" name="bk_key" value="<?= htmlspecialchars($key) ?>">
          <input type="hidden" name="action" value="export">
          <input type="hidden" name="table" value="<?= htmlspecialchars($tn) ?>">
          <button class="btn btn-export" title="Export uniquement, garde la table en BDD">Export</button>
        </form>

        <?php if ($hasArchive): ?>
        <form method="POST">
          <input type="hidden" name="bk_key" value="<?= htmlspecialchars($key) ?>">
          <input type="hidden" name="action" value="verify">
          <input type="hidden" name="table" value="<?= htmlspecialchars($tn) ?>">
          <button class="btn btn-verify" title="Verifier integrite fichier vs BDD (lecture seule)">Verifier</button>
        </form>
        <form method="POST" onsubmit="return confirm('Vider <?= $tn ?> ?\n\nLe systeme va d abord VERIFIER l archive (<?= $latestName ?>).\nSi la verification echoue, la BDD NE sera PAS videe.\n\nContinuer ?')">
          <input type="hidden" name="bk_key" value="<?= htmlspecialchars($key) ?>">
          <input type="hidden" name="action" value="truncate">
          <input type="hidden" name="table" value="<?= htmlspecialchars($tn) ?>">
          <button class="btn btn-trunc" title="Vider apres verification archive">Vider</button>
        </form>
        <?php else: ?>
        <button class="btn btn-trunc" disabled title="Aucune archive : exporte d abord">Vider</button>
        <?php endif; ?>
      </div></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="section-h">
  <h2>Archives disponibles <span class="count"><?= count($files) ?></span></h2>
</div>
<?php if (empty($files)): ?>
  <div class="empty">
    <div class="icon">&#128193;</div>
    <div>Aucune archive pour le moment.<br><span style="font-size:12px;color:var(--text-dim)">Clique sur "Export" ou "&rarr; Fichier" sur une table pour en creer une.</span></div>
  </div>
<?php else: ?>
<div class="table-card">
<table>
  <thead>
    <tr>
      <th>Fichier</th>
      <th class="num">Taille</th>
      <th>Date</th>
      <th style="text-align:right">Actions</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($files as $f): ?>
    <tr>
      <td><span class="mono" style="color:var(--text)"><?= htmlspecialchars($f['name']) ?></span></td>
      <td class="num"><?= number_format($f['size_mb'], 2, ',', ' ') ?> MB</td>
      <td style="color:var(--text-dim);font-size:12px"><?= htmlspecialchars($f['mtime']) ?></td>
      <td>
        <div class="actions">
          <button class="btn btn-export" onclick="inspectArchive('<?= htmlspecialchars($f['name'], ENT_QUOTES) ?>')" title="Voir les infos du fichier (table, lignes, colonnes)">Inspecter</button>
          <button class="btn btn-restore" onclick="smartRestore('<?= htmlspecialchars($f['name'], ENT_QUOTES) ?>')" title="Verifie l'existence de la table puis restaure (cree la table si besoin)">Installer</button>
          <button class="btn btn-del" onclick="deleteArchive('<?= htmlspecialchars($f['name'], ENT_QUOTES) ?>', false)" title="Supprime si la BDD a les memes donnees (ou plus)">Supprimer</button>
          <button class="btn btn-del" onclick="deleteArchive('<?= htmlspecialchars($f['name'], ENT_QUOTES) ?>', true)" style="background:rgba(220, 38, 38, 0.25);color:#fca5a5;border:1px solid rgba(239, 68, 68, 0.5)" title="Force la suppression sans verification (fichier echoue/corrompu)">Forcer</button>
        </div>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<div class="footer-info">
  <b>Format</b> <code>.jsonl</code> (JSON Lines, 1 row par ligne) &mdash; streaming pour gros volumes.<br>
  <b>Securite</b> <code>.htaccess Deny from all</code> dans <code>archives/</code> &mdash; fichiers inaccessibles via web.<br>
  <b>Etendre</b> editer <code>core/data_source.php</code> &raquo; <code>fileBackedTables()</code> et patcher les pages qui lisent la table.
</div>

</div><!-- /wrap -->

<!-- Barre de travail toujours visible en haut -->
<div id="workingBar"></div>

<!-- ───── OVERLAY LOADING (toujours visible que le programme travaille) ───── -->
<div id="loadingOverlay">
  <div class="loading-card">
    <div class="spinner"><div class="spinner-core"></div></div>
    <div class="loading-title" id="loadTitle">Operation en cours<span class="dots"><span>.</span><span>.</span><span>.</span></span></div>
    <div class="loading-sub" id="loadSub">Initialisation</div>

    <!-- Progression GLOBALE (restore complet de la base — basee sur la taille des fichiers) -->
    <div id="globalProgress" style="display:none;margin-top:14px;padding:14px 16px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.4);border-radius:8px">
      <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px">
        <span style="color:#86efac;font-size:11px;text-transform:uppercase;letter-spacing:0.6px;font-weight:700">&#128202; Progression globale</span>
        <span id="gpPct" style="color:#fff;font-weight:800;font-size:22px;font-family:'JetBrains Mono',monospace">0%</span>
      </div>
      <div style="height:14px;background:var(--border-soft);border-radius:7px;overflow:hidden">
        <div id="gpBar" style="height:100%;width:0%;background:linear-gradient(90deg,#22c55e,#16a34a);transition:width 0.5s ease"></div>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:10px;font-family:'JetBrains Mono',monospace;font-size:12px">
        <span style="color:var(--text-dim)">Table <b id="gpTables" style="color:#fff">0 / 0</b></span>
        <span style="color:var(--text-dim)">Temps restant total : <b id="gpEta" style="color:#fcd34d">calcul...</b></span>
      </div>
    </div>

    <div class="progress-bar"></div>

    <!-- Compteur temps reel -->
    <div id="liveCounter" style="margin-top:18px;display:none">
      <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px">
        <span style="color:var(--text-dim);font-size:11px;text-transform:uppercase;letter-spacing:0.5px">Lignes traitees</span>
        <span id="livePct" style="color:#fff;font-weight:700;font-size:16px;font-family:'JetBrains Mono',monospace">0%</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-family:'JetBrains Mono',monospace;font-size:13px">
        <span id="liveDone" style="color:var(--accent);font-weight:600">0</span>
        <span style="color:var(--text-muted)">/</span>
        <span id="liveTotal" style="color:var(--text-dim)">0</span>
      </div>
      <div style="margin-top:10px;height:6px;background:var(--border-soft);border-radius:3px;overflow:hidden">
        <div id="liveBar" style="height:100%;width:0%;background:linear-gradient(90deg,var(--accent),var(--accent-2));transition:width 0.4s ease"></div>
      </div>
    </div>

    <!-- Stats live (temps ecoule, ETA, vitesse, etape) -->
    <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:10px;font-family:'JetBrains Mono',monospace;font-size:11px">
      <div style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:6px;padding:10px;text-align:center">
        <div style="color:var(--text-muted);font-size:10px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">Temps ecoule</div>
        <div id="liveTime" style="color:#fff;font-size:16px;font-weight:700">00:00</div>
      </div>
      <div style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.25);border-radius:6px;padding:10px;text-align:center">
        <div style="color:var(--text-muted);font-size:10px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">Temps restant</div>
        <div id="liveEta" style="color:#fcd34d;font-size:16px;font-weight:700">Calcul...</div>
      </div>
      <div style="background:rgba(168,85,247,0.08);border:1px solid rgba(168,85,247,0.2);border-radius:6px;padding:10px;text-align:center">
        <div style="color:var(--text-muted);font-size:10px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">Vitesse</div>
        <div id="liveSpeed" style="color:#fff;font-size:14px;font-weight:600">--</div>
      </div>
      <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:6px;padding:10px;text-align:center">
        <div style="color:var(--text-muted);font-size:10px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">Etape</div>
        <div id="liveEtape" style="color:#fff;font-size:14px;font-weight:600">--</div>
      </div>
    </div>

    <!-- Estimation finale (heure de fin prevue) -->
    <div style="margin-top:10px;font-size:11px;color:var(--text-dim);text-align:center;font-family:'JetBrains Mono',monospace">
      Fin estimee : <span id="liveEndTime" style="color:#a5b4fc;font-weight:600">--</span>
    </div>

    <!-- Panneau d'infos detaillees -->
    <div style="margin-top:16px;padding:10px 12px;background:rgba(0,0,0,0.3);border:1px solid var(--border-soft);border-radius:6px;font-family:'JetBrains Mono',monospace;font-size:10.5px;color:var(--text-dim);line-height:1.7">
      <div style="display:flex;justify-content:space-between"><span>Chunks traites :</span> <span id="liveChunks" style="color:#fff">0</span></div>
      <div style="display:flex;justify-content:space-between"><span>Derniere reponse :</span> <span id="liveLatency" style="color:#fff">--</span></div>
      <div style="display:flex;justify-content:space-between"><span>Volume traite :</span> <span id="liveVolume" style="color:#fff">0 MB</span></div>
      <div style="display:flex;justify-content:space-between"><span>Tentatives retry :</span> <span id="liveRetries" style="color:#fff">0</span></div>
      <div style="display:flex;justify-content:space-between"><span>Derniere activite :</span> <span id="liveHeartbeat" style="color:#6ee7b7">--</span></div>
    </div>

    <!-- Console de logs (3 dernieres entrees) -->
    <div id="liveLog" style="margin-top:10px;padding:8px 10px;background:rgba(0,0,0,0.4);border-radius:6px;font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-muted);max-height:80px;overflow-y:auto;line-height:1.5">
      <div style="color:var(--text-dim)">[Pret a demarrer]</div>
    </div>

    <!-- Indicateur "ne pas fermer" -->
    <div style="margin-top:18px;padding:8px 12px;background:rgba(245,158,11,0.1);border:1px dashed rgba(245,158,11,0.3);border-radius:6px;color:#fcd34d;font-size:11px;text-align:center">
      Le programme travaille — ne ferme pas cette page
    </div>
  </div>
</div>

<!-- ───── BARRE LIVE EN HAUT (toujours visible) ───── -->
<div id="liveBar2" style="position:fixed;bottom:16px;right:16px;background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:10px 14px;font-size:11px;color:var(--text-dim);display:flex;gap:14px;align-items:center;box-shadow:0 4px 16px rgba(0,0,0,0.3);z-index:100">
  <span style="display:flex;align-items:center;gap:5px"><span style="width:6px;height:6px;border-radius:50%;background:var(--success);box-shadow:0 0 8px var(--success);animation:pulse 2s infinite"></span> Live</span>
  <span>BDD <b id="liveDbMb" style="color:#fff">--</b> MB</span>
  <span>Lignes <b id="liveRows" style="color:#fff">--</b></span>
  <span>Archives <b id="liveArc" style="color:#fff">--</b></span>
  <span style="color:var(--text-muted);font-size:10px" id="liveTs"></span>
</div>

<script>
const BK_KEY = <?= json_encode($key) ?>;

function filterTables(query) {
  const q = query.toLowerCase().trim();
  document.querySelectorAll('#tblBody tr').forEach(tr => {
    const name = tr.dataset.name || '';
    tr.style.display = (q === '' || name.includes(q)) ? '' : 'none';
  });
}

// ─── OVERLAY LOADING + POLLING TEMPS REEL ───
const overlay = document.getElementById('loadingOverlay');
const loadTitle = document.getElementById('loadTitle');
const loadSub = document.getElementById('loadSub');
const liveCounter = document.getElementById('liveCounter');
const livePct = document.getElementById('livePct');
const liveDone = document.getElementById('liveDone');
const liveTotal = document.getElementById('liveTotal');
const liveBar = document.getElementById('liveBar');
const liveEtape = document.getElementById('liveEtape');

const STEP_LABELS = {
  'export': 'Export en cours',
  'export_done': 'Export termine',
  'truncate': 'Vidage de la BDD',
  'truncate_done': 'Vidage termine',
  'restore': 'Restore en cours',
  'restore_done': 'Restore termine',
  'verify': 'Verification',
  'idle': 'En attente',
  'error': 'Erreur',
};

let pollTimer = null;
let workStartTs = 0;
let workTimeInterval = null;
let lastDone = 0;
let lastSpeedTs = 0;
let currentStep = '';

// Garde un historique de vitesse pour lisser l'ETA
let speedHistory = [];
let chunkCount = 0;
let retryCount = 0;
let lastActivityTs = 0;
let heartbeatInterval = null;

// Ajoute une ligne dans la console live
function logLive(msg, type = 'info') {
  const colors = { info: '#94a3b8', ok: '#6ee7b7', err: '#fca5a5', warn: '#fcd34d' };
  const c = colors[type] || colors.info;
  const time = new Date().toTimeString().slice(0, 8);
  const log = document.getElementById('liveLog');
  if (!log) return;
  // Si "Pret a demarrer" est encore la, on l'enleve
  if (log.children.length === 1 && log.children[0].textContent.includes('Pret a demarrer')) log.innerHTML = '';
  const line = document.createElement('div');
  line.style.color = c;
  line.innerHTML = `<span style="color:var(--text-muted)">[${time}]</span> ${msg}`;
  log.appendChild(line);
  // Garde uniquement les 20 dernieres lignes
  while (log.children.length > 20) log.removeChild(log.firstChild);
  log.scrollTop = log.scrollHeight;
  lastActivityTs = Date.now();
}

function showOverlay(actionName) {
  // Reset state
  workStartTs = Date.now();
  lastDone = 0;
  lastSpeedTs = workStartTs;
  currentStep = '';
  speedHistory = [];
  chunkCount = 0;
  retryCount = 0;
  lastActivityTs = Date.now();
  loadTitle.firstChild.textContent = actionName || 'Operation en cours';
  loadSub.textContent = 'Initialisation';
  liveCounter.style.display = 'none';
  document.getElementById('liveSpeed').textContent = '--';
  document.getElementById('liveEta').textContent = 'Calcul...';
  document.getElementById('liveEndTime').textContent = '--';
  document.getElementById('liveEtape').textContent = '--';
  document.getElementById('globalProgress').style.display = 'none';
  document.getElementById('liveTime').textContent = '00:00';
  document.getElementById('liveChunks').textContent = '0';
  document.getElementById('liveLatency').textContent = '--';
  document.getElementById('liveVolume').textContent = '0 MB';
  document.getElementById('liveRetries').textContent = '0';
  document.getElementById('liveHeartbeat').textContent = 'maintenant';
  document.getElementById('liveLog').innerHTML = '<div style="color:var(--text-dim)">[' + new Date().toTimeString().slice(0,8) + '] Demarrage : ' + actionName + '</div>';
  overlay.classList.add('show');
  document.getElementById('workingBar').classList.add('show');

  // Timer en temps reel
  if (workTimeInterval) clearInterval(workTimeInterval);
  workTimeInterval = setInterval(() => {
    const elapsed = Math.floor((Date.now() - workStartTs) / 1000);
    const m = Math.floor(elapsed / 60).toString().padStart(2, '0');
    const s = (elapsed % 60).toString().padStart(2, '0');
    document.getElementById('liveTime').textContent = `${m}:${s}`;
  }, 250);

  // Heartbeat : indique combien de secondes depuis la derniere activite
  if (heartbeatInterval) clearInterval(heartbeatInterval);
  heartbeatInterval = setInterval(() => {
    const idle = Math.floor((Date.now() - lastActivityTs) / 1000);
    const hb = document.getElementById('liveHeartbeat');
    if (idle < 2) {
      hb.style.color = '#6ee7b7'; hb.textContent = 'a l\'instant';
    } else if (idle < 10) {
      hb.style.color = '#fcd34d'; hb.textContent = `il y a ${idle}s`;
    } else {
      hb.style.color = '#fca5a5'; hb.textContent = `il y a ${idle}s (verifier la connexion)`;
    }
  }, 500);

  pollProgress();
  pollTimer = setInterval(pollProgress, 400);
}

// Formate des secondes en duree lisible
function fmtDuration(sec) {
  if (sec < 0 || !isFinite(sec)) return '--';
  if (sec < 60) return Math.round(sec) + 's';
  if (sec < 3600) {
    const m = Math.floor(sec / 60);
    const s = Math.round(sec % 60);
    return `${m}m ${s.toString().padStart(2, '0')}s`;
  }
  const h = Math.floor(sec / 3600);
  const m = Math.floor((sec % 3600) / 60);
  return `${h}h ${m.toString().padStart(2, '0')}m`;
}

// Heure prevue de fin (format HH:MM:SS)
function fmtEndTime(secFromNow) {
  if (secFromNow < 0 || !isFinite(secFromNow)) return '--';
  const end = new Date(Date.now() + secFromNow * 1000);
  return end.toTimeString().slice(0, 8);
}

function hideOverlay() {
  overlay.classList.remove('show');
  document.getElementById('workingBar').classList.remove('show');
  if (workTimeInterval) { clearInterval(workTimeInterval); workTimeInterval = null; }
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  if (heartbeatInterval) { clearInterval(heartbeatInterval); heartbeatInterval = null; }
}

function fmtNum(n) {
  return new Intl.NumberFormat('fr-FR').format(n);
}

async function pollProgress() {
  try {
    const r = await fetch(`?bk_key=${encodeURIComponent(BK_KEY)}&progress=1&t=${Date.now()}`);
    const d = await r.json();
    if (d.step === 'idle') return;

    loadSub.textContent = d.message || STEP_LABELS[d.step] || d.step;
    document.getElementById('liveEtape').textContent = (STEP_LABELS[d.step] || d.step || '').replace(' en cours', '');

    if (typeof d.done === 'number' && typeof d.total === 'number' && d.total > 0) {
      updateLive(d.done, d.total, d.message || STEP_LABELS[d.step]);
    }
  } catch (e) {
    // Ignorer les erreurs de polling pendant la nav
  }
}

// Hook sur tous les forms : intercepter et utiliser chunked AJAX pour les operations lourdes
document.querySelectorAll('form[method="POST"]').forEach(form => {
  form.addEventListener('submit', function(e) {
    const action = form.querySelector('input[name="action"]')?.value || 'operation';
    const table = form.querySelector('input[name="table"]')?.value || '';
    const file = form.querySelector('input[name="file"]')?.value || '';

    // Actions lourdes -> chunked AJAX (anti-timeout)
    const chunkedActions = ['export', 'to_file', 'to_bdd', 'truncate', 'restore'];
    if (chunkedActions.includes(action)) {
      e.preventDefault();
      runChunked(action, table, file);
      return false;
    }

    // Actions rapides -> submit normal avec overlay
    const labels = {
      'verify': 'Verification', 'delete_file': 'Suppression',
    };
    showOverlay(labels[action] || 'Operation en cours');
  });
});

// Nom du dernier fichier cree pendant cette operation (pour pouvoir le supprimer en cas d'erreur)
let lastCreatedFile = null;

// ─── Pilotage des operations chunked (tout passe par fetchResilient)
async function runChunked(action, table, file) {
  lastCreatedFile = null;
  try {
    if (action === 'export') {
      showOverlay('Export en cours');
      lastCreatedFile = await chunkedExport(table);
      finishMsg('ok', `Export de <b>${table}</b> termine. Fichier <code>${lastCreatedFile}</code> cree.`);
    }
    else if (action === 'to_file') {
      showOverlay('Bascule vers Fichier');
      const filename = await chunkedExport(table);
      lastCreatedFile = filename;
      document.getElementById('liveEtape').textContent = 'VERIFY';
      loadSub.textContent = 'Verification de l\'integrite (lignes BDD vs fichier)...';
      const trd = await fetchResilient(`?bk_key=${encodeURIComponent(BK_KEY)}&ajax=truncate_safe&table=${encodeURIComponent(table)}&filename=${encodeURIComponent(filename)}`, {method:'POST'}, 'verification');
      if (trd.error) throw new Error(trd.error);
      document.getElementById('liveEtape').textContent = 'SOURCE';
      loadSub.textContent = 'Bascule de la source vers Fichier...';
      await fetchResilient(`?bk_key=${encodeURIComponent(BK_KEY)}&ajax=set_source&table=${encodeURIComponent(table)}&mode=file`, {method:'POST'}, 'set source');
      lastCreatedFile = null;
      finishMsg('ok', `Bascule vers Fichier OK : <b>${table}</b> exportee + videe (<b>${fmtNum(trd.truncated || 0)} lignes</b>). Source = Fichier.`);
    }
    else if (action === 'truncate') {
      showOverlay('Vidage de la BDD');
      document.getElementById('liveEtape').textContent = 'LOOKUP';
      loadSub.textContent = 'Recherche de la derniere archive...';
      const lastFile = await getLatestArchive(table);
      if (!lastFile) throw new Error('Aucune archive trouvee');
      document.getElementById('liveEtape').textContent = 'VERIFY';
      loadSub.textContent = `Verification de l'archive ${lastFile}...`;
      const trd = await fetchResilient(`?bk_key=${encodeURIComponent(BK_KEY)}&ajax=truncate_safe&table=${encodeURIComponent(table)}&filename=${encodeURIComponent(lastFile)}`, {method:'POST'}, 'truncate');
      if (trd.error) throw new Error(trd.error);
      finishMsg('ok', `Table <b>${table}</b> videe : <b>${fmtNum(trd.truncated)} lignes</b> supprimees.`);
    }
    else if (action === 'to_bdd') {
      showOverlay('Bascule vers BDD');
      document.getElementById('liveEtape').textContent = 'LOOKUP';
      loadSub.textContent = 'Recherche de la derniere archive...';
      const lastFile = await getLatestArchive(table);
      if (!lastFile) throw new Error('Aucune archive trouvee');
      document.getElementById('liveEtape').textContent = 'RESTORE';
      await chunkedRestore(lastFile);
      document.getElementById('liveEtape').textContent = 'SOURCE';
      loadSub.textContent = 'Bascule de la source vers BDD...';
      await fetchResilient(`?bk_key=${encodeURIComponent(BK_KEY)}&ajax=set_source&table=${encodeURIComponent(table)}&mode=bdd`, {method:'POST'}, 'set source');
      finishMsg('ok', `Bascule vers BDD OK : <b>${table}</b> restauree depuis <code>${lastFile}</code>.`);
    }
    else if (action === 'restore') {
      showOverlay('Restauration');
      await chunkedRestore(file);
      finishMsg('ok', `Restauration OK : <b>${file}</b> reinjecte en BDD.`);
    }
  } catch (err) {
    // Si un fichier a ete cree pendant l'op, on propose un bouton pour le nettoyer
    let extra = '';
    if (lastCreatedFile) {
      extra = `<br><br><div style="margin-top:8px;padding:10px 12px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:6px">
        <b>Le fichier <code>${lastCreatedFile}</code> a ete cree mais l'operation a echoue.</b><br>
        <button class="btn btn-del" style="margin-top:8px;background:rgba(239,68,68,0.25);color:#fca5a5;border:1px solid rgba(239,68,68,0.5);padding:8px 14px;font-size:12px" onclick="forceDeleteCreated()">Supprimer ce fichier et recommencer</button>
      </div>`;
    }
    finishMsg('err', `Erreur : ${err.message || err}${extra}`);
  }
}

// Suppression du fichier cree pendant la derniere operation echouee
async function forceDeleteCreated() {
  if (!lastCreatedFile) return;
  const f = lastCreatedFile;
  if (!confirm(`Supprimer ${f} en mode FORCE (pas de verification) ?`)) return;
  showOverlay('Suppression forcee');
  try {
    const fd = new FormData();
    fd.append('filename', f);
    fd.append('force', '1');
    const r = await fetch(`?bk_key=${encodeURIComponent(BK_KEY)}&ajax=delete_archive_safe`, {method: 'POST', body: fd});
    const d = await r.json();
    if (d.error) throw new Error(d.error);
    lastCreatedFile = null;
    finishMsg('ok', `Fichier <code>${f}</code> supprime. Tu peux recommencer.`);
  } catch (err) {
    finishMsg('err', `Suppression echouee : ${err.message}`);
  }
}

async function getLatestArchive(table) {
  // Cherche dans le DOM la 1ere archive dont le nom commence par "table_"
  const rows = document.querySelectorAll('.table-card table tbody tr');
  for (const tr of rows) {
    const span = tr.querySelector('.mono');
    if (span && span.textContent.startsWith(table + '_') && span.textContent.endsWith('.jsonl')) {
      return span.textContent;
    }
  }
  return null;
}

// ─── FETCH RESILIENT : retry infini avec backoff exponentiel, jamais de blocage
async function fetchResilient(url, options = {}, label = 'requete') {
  let delay = 1000;
  let attempt = 0;
  while (true) {
    attempt++;
    const t0 = Date.now();
    try {
      const r = await fetch(url, options);
      const latency = Date.now() - t0;
      document.getElementById('liveLatency').textContent = latency + ' ms';
      lastActivityTs = Date.now();

      if (r.status >= 500) {
        retryCount++;
        document.getElementById('liveRetries').textContent = retryCount;
        loadSub.textContent = `Serveur HTTP ${r.status} — retry dans ${delay/1000}s (tentative ${attempt})`;
        document.getElementById('liveEtape').textContent = 'RETRY';
        logLive(`[${label}] HTTP ${r.status} - retry dans ${delay/1000}s`, 'err');
        await sleep(delay);
        delay = Math.min(delay * 2, 30000);
        continue;
      }
      if (r.status === 429) {
        retryCount++;
        document.getElementById('liveRetries').textContent = retryCount;
        loadSub.textContent = `Rate limit 429 — pause ${delay/1000}s`;
        document.getElementById('liveEtape').textContent = 'PAUSE';
        logLive(`[${label}] Rate limit 429 - pause ${delay/1000}s`, 'warn');
        await sleep(delay);
        delay = Math.min(delay * 2, 30000);
        continue;
      }
      const text = await r.text();
      let data;
      try { data = JSON.parse(text); }
      catch (e) {
        retryCount++;
        document.getElementById('liveRetries').textContent = retryCount;
        loadSub.textContent = `Reponse non-JSON, retry dans ${delay/1000}s`;
        logLive(`[${label}] Reponse non-JSON (HTML/PHP error) - retry`, 'err');
        await sleep(delay);
        delay = Math.min(delay * 2, 30000);
        continue;
      }
      return data;
    } catch (err) {
      retryCount++;
      document.getElementById('liveRetries').textContent = retryCount;
      loadSub.textContent = `Erreur reseau — retry dans ${delay/1000}s`;
      document.getElementById('liveEtape').textContent = 'NETWORK';
      logLive(`[${label}] Erreur reseau : ${err.message} - retry ${delay/1000}s`, 'err');
      await sleep(delay);
      delay = Math.min(delay * 2, 30000);
    }
  }
}

function sleep(ms) { return new Promise(res => setTimeout(res, ms)); }

// Delai entre chunks pour pas hammerer le serveur (anti-503)
const CHUNK_DELAY_MS = 300;

async function chunkedExport(table) {
  let offset = 0;
  let total = 0;
  let filename = '';
  logLive(`Export demarre pour la table "${table}"`, 'info');
  while (true) {
    const url = new URL(window.location.href);
    url.search = `?bk_key=${encodeURIComponent(BK_KEY)}&ajax=export_chunk&table=${encodeURIComponent(table)}&offset=${offset}&filename=${encodeURIComponent(filename)}&total=${total}&size=5000`;
    const d = await fetchResilient(url.toString(), {}, 'export');
    if (d.error) {
      retryCount++;
      document.getElementById('liveRetries').textContent = retryCount;
      logLive(`Erreur appli : ${d.error} - retry dans 2s`, 'err');
      await sleep(2000);
      continue;
    }
    offset = d.offset;
    total = d.total;
    filename = d.filename;
    chunkCount++;
    const totalChunks = Math.ceil(total / 5000);
    document.getElementById('liveChunks').textContent = `${chunkCount} / ${totalChunks}`;
    // Estimation volume : taille du fichier sur disque (recupere via stats)
    updateLive(d.offset, d.total, `Export : chunk ${chunkCount}/${totalChunks} — ${fmtNum(offset)} / ${fmtNum(total)} lignes`);
    if (chunkCount % 5 === 0 || d.finished) logLive(`Chunk ${chunkCount}/${totalChunks} OK : ${fmtNum(offset)}/${fmtNum(total)} (${d.written} ecrites)`, 'ok');
    if (d.finished) {
      logLive(`Export termine : ${fmtNum(offset)} lignes dans ${filename}`, 'ok');
      return filename;
    }
    await sleep(CHUNK_DELAY_MS);
  }
}

async function chunkedRestore(filename, onChunk) {
  let lineStart = 0;
  let total = 0;
  logLive(`Restore demarre depuis ${filename}`, 'info');
  while (true) {
    const url = new URL(window.location.href);
    url.search = `?bk_key=${encodeURIComponent(BK_KEY)}&ajax=restore_chunk&filename=${encodeURIComponent(filename)}&line_start=${lineStart}&total=${total}`;
    const d = await fetchResilient(url.toString(), {}, 'restore');
    if (d.error) {
      retryCount++;
      document.getElementById('liveRetries').textContent = retryCount;
      logLive(`Erreur appli : ${d.error} - retry dans 2s`, 'err');
      await sleep(2000);
      continue;
    }
    lineStart = d.line_start;
    total = d.total;
    chunkCount++;
    const totalChunks = total > 0 ? Math.ceil(total / 2000) : 0;
    document.getElementById('liveChunks').textContent = totalChunks > 0 ? `${chunkCount} / ${totalChunks}` : chunkCount;
    updateLive(lineStart, total, `Restore : chunk ${chunkCount}${totalChunks > 0 ? '/' + totalChunks : ''} — ${fmtNum(lineStart)} / ${fmtNum(total)} lignes`);
    if (onChunk) onChunk(total > 0 ? lineStart / total : 0);
    if (chunkCount % 5 === 0 || d.finished) logLive(`Chunk ${chunkCount} OK : ${fmtNum(lineStart)}/${fmtNum(total)} (${d.processed} inserees)`, 'ok');
    if (d.finished) {
      logLive(`Restore termine : ${fmtNum(lineStart)} lignes inserees`, 'ok');
      return;
    }
    await sleep(CHUNK_DELAY_MS);
  }
}

function updateLive(done, total, msg) {
  liveCounter.style.display = 'block';
  loadSub.textContent = msg;
  if (total > 0) {
    const pct = Math.min(100, Math.round((done / total) * 100));
    liveDone.textContent = fmtNum(done);
    liveTotal.textContent = fmtNum(total);
    livePct.textContent = pct + '%';
    liveBar.style.width = pct + '%';
  }

  // Vitesse (lignes/sec) calculee toutes les 0.5s
  const now = Date.now();
  const dt = (now - lastSpeedTs) / 1000;
  if (dt >= 0.5 && done > lastDone) {
    const instantSpeed = (done - lastDone) / dt;
    speedHistory.push(instantSpeed);
    if (speedHistory.length > 5) speedHistory.shift(); // garde les 5 dernieres mesures
    lastDone = done;
    lastSpeedTs = now;
  }

  // Vitesse moyenne (sur les 5 dernieres mesures) + Vitesse globale (depuis le debut)
  const elapsedSec = (now - workStartTs) / 1000;
  const globalSpeed = elapsedSec > 0 ? done / elapsedSec : 0;
  const recentSpeed = speedHistory.length > 0
    ? speedHistory.reduce((a, b) => a + b, 0) / speedHistory.length
    : 0;
  // Pondere : 30% global, 70% recent (privilegie la vitesse actuelle mais stabilise)
  const blendedSpeed = recentSpeed > 0 ? (0.3 * globalSpeed + 0.7 * recentSpeed) : globalSpeed;

  if (blendedSpeed > 0) {
    document.getElementById('liveSpeed').textContent = fmtNum(Math.round(blendedSpeed)) + '/s';
  }

  // ETA = (total - done) / vitesse
  if (total > 0 && done < total && blendedSpeed > 0 && elapsedSec >= 2) {
    const remaining = total - done;
    const etaSec = remaining / blendedSpeed;
    document.getElementById('liveEta').textContent = fmtDuration(etaSec);
    document.getElementById('liveEndTime').textContent = fmtEndTime(etaSec);
  } else if (done >= total && total > 0) {
    document.getElementById('liveEta').textContent = 'Termine';
    document.getElementById('liveEndTime').textContent = new Date().toTimeString().slice(0, 8);
  }
}

// ─── Inspection d'un fichier (affiche un panneau d'infos)
async function inspectArchive(filename) {
  showOverlay('Inspection du fichier');
  loadSub.textContent = 'Lecture du META et comparaison BDD...';
  try {
    const r = await fetch(`?bk_key=${encodeURIComponent(BK_KEY)}&ajax=inspect_file&filename=${encodeURIComponent(filename)}`);
    const d = await r.json();
    if (d.error) { finishMsg('err', d.error); return; }
    hideOverlay();

    const statusBdd = d.table_exists
      ? `<span style="color:#6ee7b7">EXISTE</span> (${fmtNum(d.bdd_rows)} lignes)`
      : `<span style="color:#fca5a5">N'EXISTE PAS</span>`;
    const colsStatus = d.table_exists
      ? (d.columns_match ? `<span style="color:#6ee7b7">colonnes identiques</span>` : `<span style="color:#fca5a5">colonnes differentes</span>`)
      : `<span style="color:#fcd34d">a creer depuis le META</span>`;
    const canInstall = d.can_install
      ? `<span style="color:#6ee7b7">OUI</span>`
      : `<span style="color:#fca5a5">NON — ${d.has_create_sql ? 'colonnes incompatibles' : 'pas de CREATE TABLE dans META'}</span>`;

    const html = `
      <div style="font-family:'JetBrains Mono',monospace;font-size:12px;line-height:1.8">
        <b style="color:#fff">${d.filename}</b><br>
        <span style="color:var(--text-dim)">Exporte le : ${d.exported_at || 'inconnu'}</span><br><br>
        Table         : <b>${d.table}</b><br>
        Lignes fichier: <b>${fmtNum(d.file_rows)}</b><br>
        Colonnes      : ${(d.file_columns || []).length} (${(d.file_columns || []).slice(0, 5).join(', ')}${d.file_columns.length > 5 ? '...' : ''})<br>
        CREATE TABLE  : ${d.has_create_sql ? '<span style="color:#6ee7b7">inclus</span>' : '<span style="color:#fca5a5">absent (ancien export)</span>'}<br>
        <br>
        Table en BDD  : ${statusBdd}<br>
        Compatibilite : ${colsStatus}<br>
        <br>
        <b>Installable ?</b> ${canInstall}
      </div>
    `;
    finishMsg('ok', html);
  } catch (err) {
    finishMsg('err', `Erreur : ${err.message}`);
  }
}

// ─── Installation intelligente : verifie, cree si besoin, puis restore
async function smartRestore(filename) {
  if (!confirm(`Installer ${filename} dans la BDD ?\n\n1) Inspection du fichier\n2) Verification que la table existe (sinon creation)\n3) Restauration des lignes\n\nContinuer ?`)) return;

  showOverlay('Installation du fichier');
  loadSub.textContent = 'Inspection du fichier...';
  try {
    // Etape 1 : inspecter
    const ir = await fetch(`?bk_key=${encodeURIComponent(BK_KEY)}&ajax=inspect_file&filename=${encodeURIComponent(filename)}`);
    const info = await ir.json();
    if (info.error) throw new Error(info.error);

    document.getElementById('liveEtape').textContent = 'INSPECT';
    loadSub.textContent = `Table "${info.table}" : ${info.table_exists ? 'EXISTE' : "N'EXISTE PAS"}`;

    // Etape 2 : creer si besoin
    if (!info.table_exists) {
      if (!info.has_create_sql) {
        throw new Error(`La table "${info.table}" n'existe pas et le fichier ne contient pas de CREATE TABLE. Fichier exporte avec ancienne version.`);
      }
      document.getElementById('liveEtape').textContent = 'CREATE';
      loadSub.textContent = `Creation de la table "${info.table}"...`;
      const fd = new FormData();
      fd.append('filename', filename);
      const cr = await fetch(`?bk_key=${encodeURIComponent(BK_KEY)}&ajax=create_table_from_file`, {method: 'POST', body: fd});
      const crd = await cr.json();
      if (crd.error) throw new Error(`Creation echouee : ${crd.error}`);
    } else if (!info.columns_match) {
      throw new Error(`La table "${info.table}" existe mais ses colonnes different du fichier. Renommez/supprimez la table existante ou modifiez-la pour matcher.`);
    } else if (info.bdd_rows > 0) {
      if (!confirm(`La table "${info.table}" contient deja ${fmtNum(info.bdd_rows)} lignes. Le restore va AJOUTER ${fmtNum(info.file_rows)} lignes (risque de doublons).\n\nContinuer quand meme ?`)) {
        hideOverlay();
        return;
      }
    }

    // Etape 3 : restore chunked
    document.getElementById('liveEtape').textContent = 'RESTORE';
    await chunkedRestore(filename);

    finishMsg('ok',
      `Installation de <b>${filename}</b> reussie.<br>` +
      `Table : <code>${info.table}</code><br>` +
      `Lignes inserees : <b>${fmtNum(info.file_rows)}</b>` +
      (!info.table_exists ? `<br>Table creee depuis le META.` : ``)
    );
  } catch (err) {
    finishMsg('err', `Installation echouee : ${err.message}`);
  }
}

// ─── RESTAURATION GLOBALE : reconstruit toute la BDD depuis les .jsonl
// Table par table, en chunks (anti-timeout). Cree les tables manquantes, verifie le
// nombre de lignes apres restore, saute les tables deja remplies (zero doublon).
var _bkRestoreFiles = <?= json_encode($_restoreFiles ?? [], JSON_UNESCAPED_UNICODE) ?>;
async function restoreAll() {
  var files = _bkRestoreFiles || [];
  if (!files.length) { alert('Aucune archive .jsonl dans le dossier archives/.'); return; }
  if (!confirm(
    'Reconstruire la BDD depuis ' + files.length + ' fichier(s) d archive ?\n\n' +
    '- Chaque table est restauree une par une, en chunks (anti-timeout)\n' +
    '- Les tables manquantes sont creees automatiquement\n' +
    '- Les tables qui ont DEJA des donnees sont IGNOREES (aucun doublon)\n' +
    '- Sur une grosse base cela peut etre long : NE FERME PAS la page\n\nContinuer ?'
  )) return;

  showOverlay('Reconstruction de la BDD');

  // ── Progression globale : basee sur la taille cumulee des fichiers (octets traites) ──
  var totalBytes = 0;
  files.forEach(function (f) { totalBytes += (f.s || 0); });
  var bytesBefore = 0;            // octets des tables deja terminees
  var gStart = Date.now();
  document.getElementById('globalProgress').style.display = 'block';
  function fmtDur(sec) {
    sec = Math.max(0, Math.round(sec));
    if (sec < 60) return sec + 's';
    var m = Math.floor(sec / 60), s = sec % 60;
    if (m < 60) return m + 'm ' + (s < 10 ? '0' : '') + s + 's';
    var h = Math.floor(m / 60); m = m % 60;
    return h + 'h ' + (m < 10 ? '0' : '') + m + 'm';
  }
  function updateGlobal(idx, frac) {
    var cur = files[idx] ? (files[idx].s || 0) : 0;
    var processed = bytesBefore + frac * cur;
    var pct = totalBytes > 0 ? Math.min(100, processed / totalBytes * 100) : 0;
    document.getElementById('gpBar').style.width = pct.toFixed(1) + '%';
    document.getElementById('gpPct').textContent = Math.round(pct) + '%';
    document.getElementById('gpTables').textContent = (idx + 1) + ' / ' + files.length;
    var elapsed = (Date.now() - gStart) / 1000;
    if (elapsed > 3 && processed > 0) {
      var rate = processed / elapsed;                       // octets/sec moyens depuis le debut
      document.getElementById('gpEta').textContent = rate > 0 ? fmtDur((totalBytes - processed) / rate) : 'calcul...';
    }
  }
  updateGlobal(0, 0);

  var done = [], skipped = [], failed = [];
  for (var i = 0; i < files.length; i++) {
    var filename = files[i].f;
    var pos = (i + 1) + ' / ' + files.length;
    document.getElementById('liveEtape').textContent = 'TABLE ' + pos;
    loadSub.textContent = pos + ' — inspection de ' + filename + '...';
    updateGlobal(i, 0);
    try {
      // 1) Inspection du fichier
      var ir = await fetch('?bk_key=' + encodeURIComponent(BK_KEY) + '&ajax=inspect_file&filename=' + encodeURIComponent(filename));
      var info = await ir.json();
      if (info.error) throw new Error(info.error);

      // 2) Table deja remplie -> on saute (anti-doublon, rejouable sans risque)
      if (info.table_exists && info.bdd_rows > 0) {
        skipped.push(info.table + ' (' + fmtNum(info.bdd_rows) + ' lignes deja en BDD)');
        logLive('IGNOREE : ' + info.table + ' a deja ' + fmtNum(info.bdd_rows) + ' lignes', 'info');
        bytesBefore += files[i].s || 0; updateGlobal(i, 1);
        continue;
      }
      // 3) Table existante mais colonnes differentes -> on ne touche pas, on signale
      if (info.table_exists && !info.columns_match) {
        failed.push(filename + ' : colonnes incompatibles avec la table existante');
        logLive('ECHEC : ' + info.table + ' — colonnes incompatibles, table laissee intacte', 'err');
        bytesBefore += files[i].s || 0; updateGlobal(i, 1);
        continue;
      }
      // 4) Table absente -> creation depuis le CREATE TABLE du #META
      if (!info.table_exists) {
        if (!info.has_create_sql) {
          failed.push(filename + ' : table absente et pas de CREATE TABLE dans le fichier');
          logLive('ECHEC : ' + filename + ' — pas de CREATE TABLE dans le META', 'err');
          bytesBefore += files[i].s || 0; updateGlobal(i, 1);
          continue;
        }
        document.getElementById('liveEtape').textContent = 'CREATE ' + pos;
        loadSub.textContent = pos + ' — creation de la table ' + info.table + '...';
        var fd = new FormData();
        fd.append('filename', filename);
        var cr = await fetch('?bk_key=' + encodeURIComponent(BK_KEY) + '&ajax=create_table_from_file', { method: 'POST', body: fd });
        var crd = await cr.json();
        if (crd.error) {
          failed.push(filename + ' : creation table — ' + crd.error);
          logLive('ECHEC creation : ' + crd.error, 'err');
          bytesBefore += files[i].s || 0; updateGlobal(i, 1);
          continue;
        }
        logLive('Table ' + info.table + ' creee depuis le META', 'ok');
      }
      // 5) Restore en chunks de 2000 lignes — la progression globale avance en temps reel
      document.getElementById('liveEtape').textContent = 'RESTORE ' + pos;
      chunkCount = 0;
      await chunkedRestore(filename, function (frac) { updateGlobal(i, frac); });

      // 6) Verification post-restore : nb lignes BDD == nb lignes fichier ?
      var vr = await fetch('?bk_key=' + encodeURIComponent(BK_KEY) + '&ajax=inspect_file&filename=' + encodeURIComponent(filename));
      var vinfo = await vr.json();
      if (!vinfo.error && vinfo.bdd_rows === vinfo.file_rows) {
        done.push(info.table + ' (' + fmtNum(vinfo.bdd_rows) + ' lignes)');
        logLive('OK : ' + info.table + ' — ' + fmtNum(vinfo.bdd_rows) + ' lignes restaurees', 'ok');
      } else {
        var got = vinfo.error ? '?' : fmtNum(vinfo.bdd_rows);
        failed.push(filename + ' : restore incomplet (' + got + ' / ' + fmtNum(info.file_rows) + ' lignes)');
        logLive('ATTENTION : ' + info.table + ' — ' + got + ' / ' + fmtNum(info.file_rows) + ' lignes (incomplet)', 'err');
      }
    } catch (err) {
      failed.push(filename + ' : ' + err.message);
      logLive('ECHEC : ' + filename + ' — ' + err.message, 'err');
    }
    bytesBefore += files[i].s || 0;
    updateGlobal(i, 1);
  }

  document.getElementById('gpBar').style.width = '100%';
  document.getElementById('gpPct').textContent = '100%';
  document.getElementById('gpEta').textContent = 'termine';
  var html = '<b>Reconstruction terminee.</b><br>' +
    '&#10003; ' + done.length + ' table(s) restauree(s)<br>' +
    '&#8594; ' + skipped.length + ' ignoree(s) (donnees deja presentes)<br>' +
    (failed.length ? '&#9888; ' + failed.length + ' echec(s) :<br>' + failed.join('<br>') : '&#10003; aucun echec');
  finishMsg(failed.length ? 'err' : 'ok', html);
}

// ─── Suppression securisee d'une archive
async function deleteArchive(filename, force) {
  const verb = force ? 'FORCER la suppression' : 'Supprimer';
  const warn = force
    ? `${verb} de ${filename} ?\n\nATTENTION : aucune verification, le fichier sera detruit meme si la BDD ne contient pas les memes donnees.\n\nUtilisez cette option uniquement pour les fichiers echoues ou corrompus.`
    : `${verb} de ${filename} ?\n\nLe systeme va d'abord verifier :\n - la table n'est pas en mode FICHIER\n - la BDD contient au moins autant de lignes que le fichier\n\nSi verification echoue, le fichier sera CONSERVE.`;
  if (!confirm(warn)) return;

  showOverlay(force ? 'Suppression forcee' : 'Suppression securisee');
  loadSub.textContent = 'Verification en cours...';
  try {
    const fd = new FormData();
    fd.append('filename', filename);
    if (force) fd.append('force', '1');
    const r = await fetch(`?bk_key=${encodeURIComponent(BK_KEY)}&ajax=delete_archive_safe`, {method: 'POST', body: fd});
    const d = await r.json();
    if (d.error) {
      finishMsg('err', d.error);
      return;
    }
    finishMsg('ok', `Fichier <b>${filename}</b> supprime.`);
  } catch (err) {
    finishMsg('err', `Erreur : ${err.message || err}`);
  }
}

function finishMsg(type, html) {
  hideOverlay();
  // Construit un message en haut de page
  const wrap = document.querySelector('.wrap');
  let m = document.querySelector('.msg.live');
  if (!m) {
    m = document.createElement('div');
    m.className = 'msg live';
    wrap.insertBefore(m, wrap.firstChild.nextSibling.nextSibling);
  }
  m.className = `msg live ${type}`;
  m.innerHTML = `<span class="icon icon-anim">${type === 'ok' ? '&#10003;' : '&#9888;'}</span><div>${html}</div>`;
  // Refresh stats apres un court delai pour voir les changements
  setTimeout(() => { refreshStats(); setTimeout(() => location.reload(), 1200); }, 400);
}

// ─── KPI LIVE (toutes les 5s) ───
async function refreshStats() {
  try {
    const r = await fetch(`?bk_key=${encodeURIComponent(BK_KEY)}&stats=1&t=${Date.now()}`);
    const d = await r.json();
    document.getElementById('liveDbMb').textContent = d.db_mb;
    document.getElementById('liveRows').textContent = fmtNum(d.rows);
    document.getElementById('liveArc').textContent = `${d.archives} (${d.arc_mb} MB)`;
    document.getElementById('liveTs').textContent = d.ts;
  } catch (e) {}
}
refreshStats();
setInterval(refreshStats, 5000);
</script>

</body>
</html>
