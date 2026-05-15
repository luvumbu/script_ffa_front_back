<?php
/**
 * core/data_source.php — Bascule source de donnees BDD <-> Fichier
 *
 * Permet de choisir pour chaque table si on lit dans la BDD MySQL
 * ou dans un fichier .jsonl stocke dans archives/.
 *
 * Config : config/data_source.json
 *   { "logs": "file", "search_tracking": "bdd", ... }
 *
 * Tables NON listees -> source par defaut = "bdd"
 *
 * Utilisation (cote lecteur) :
 *   require_once __DIR__ . '/../core/data_source.php';
 *   if (dataSourceMode('logs') === 'file') {
 *       $rows = loadArchive('logs');  // lit le fichier
 *   } else {
 *       $rows = $conn->query("SELECT * FROM logs ...");
 *   }
 */

if (!function_exists('dataSourceMode')) {

/**
 * Retourne 'bdd' ou 'file' pour une table donnee.
 */
function dataSourceMode(string $table): string {
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/../config/data_source.json';
        $cfg = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
    }
    return ($cfg[$table] ?? 'bdd') === 'file' ? 'file' : 'bdd';
}

/**
 * Met a jour la source d'une table (ecrit le config file).
 */
function setDataSourceMode(string $table, string $mode): bool {
    $path = __DIR__ . '/../config/data_source.json';
    $cfg = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
    if ($mode === 'bdd') {
        unset($cfg[$table]);
    } else {
        $cfg[$table] = 'file';
    }
    return file_put_contents($path, json_encode($cfg, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Retourne le chemin du fichier d'archive le plus recent pour une table.
 * Null si aucune archive.
 */
function latestArchivePath(string $table): ?string {
    $dir = __DIR__ . '/../archives';
    $files = glob("$dir/{$table}_*.jsonl");
    if (empty($files)) return null;
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    return $files[0];
}

/**
 * Charge TOUT le contenu d'un fichier archive en memoire (array de rows).
 * Pratique pour les petits volumes (< 100 MB).
 * Pour les gros volumes, utiliser streamArchive() avec callback.
 */
function loadArchive(string $table): array {
    $path = latestArchivePath($table);
    if (!$path) return [];
    $rows = [];
    $fp = fopen($path, 'r');
    while (($line = fgets($fp)) !== false) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $row = json_decode($line, true);
        if ($row) $rows[] = $row;
    }
    fclose($fp);
    return $rows;
}

/**
 * Stream le fichier archive ligne par ligne, applique un callback($row) sur chaque row.
 * Si $callback retourne false, le streaming s'arrete.
 * Retourne le nombre de rows traitees.
 */
function streamArchive(string $table, callable $callback): int {
    $path = latestArchivePath($table);
    if (!$path) return 0;
    $fp = fopen($path, 'r');
    $count = 0;
    while (($line = fgets($fp)) !== false) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $row = json_decode($line, true);
        if (!$row) continue;
        if ($callback($row) === false) break;
        $count++;
    }
    fclose($fp);
    return $count;
}

/**
 * Compte les rows d'un fichier archive (lecture rapide ligne par ligne).
 */
function countArchive(string $table): int {
    $path = latestArchivePath($table);
    if (!$path) return 0;
    $count = 0;
    $fp = fopen($path, 'r');
    while (($line = fgets($fp)) !== false) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $count++;
    }
    fclose($fp);
    return $count;
}

/**
 * Liste des tables pour lesquelles le code applicatif sait
 * lire depuis le fichier (= tables "patchees").
 * Les autres ne devraient PAS etre basculees en mode file
 * (sinon les pages qui les utilisent retourneront 0 resultats).
 */
function fileBackedTables(): array {
    return [
        'logs',          // admin/logs.php patche
        // a etendre au fur et a mesure :
        // 'search_tracking',
        // 'contact_messages',
        // 'sent_emails',
        // 'profile_reports',
    ];
}

} // fin function_exists
