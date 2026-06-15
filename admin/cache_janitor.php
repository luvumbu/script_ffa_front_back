<?php
/**
 * admin/cache_janitor.php — Nettoyeur de cache pour limiter les inodes (Hostinger)
 *
 * A LANCER UNIQUEMENT VIA LE CRON (jamais pendant une requete visiteur).
 * Supprime les fichiers cache/*.json plus vieux que le TTL : ils sont deja
 * consideres comme perimes par les API, donc aucun cache "chaud" n'est touche.
 *
 * Concu pour ne PAS impacter les performances :
 *   - parcours en streaming (opendir/readdir) => memoire constante meme sur 300k fichiers
 *   - borne dans le temps (max_seconds) => le cron n'est jamais tue en plein milieu,
 *     le reste est balaye au passage suivant
 *
 * Usage cron (Hostinger -> Cron Jobs, 1x/jour la nuit suffit) :
 *   /usr/bin/php /home/UXXXX/domains/bokonzi.com/public_html/admin/cache_janitor.php bk_key=bk_s3cr3t_2026_xK9mP
 * Usage HTTP (manuel / test) :
 *   https://bokonzi.com/admin/cache_janitor.php?bk_key=...&hours=24
 *   &dry=1            => simulation, ne supprime rien (compte seulement)
 *   &hours=48         => seuil d'age en heures (defaut 24)
 *   &prefix=search    => cible un prefixe (search_*, athlete_*, ...) ; defaut = tous
 *   &max_seconds=50   => budget temps par passage (defaut 50)
 *
 * Reponse JSON : scanned, deleted, kept_fresh, bytes_freed, elapsed, done
 *   done=false => le dossier n'a pas ete entierement parcouru (relancer / attendre le prochain cron)
 */

// --- Recuperation des params en mode CLI (cron) ET HTTP ---
$cli = (php_sapi_name() === 'cli');
if ($cli) {
    foreach (array_slice($argv, 1) as $a) {
        if (strpos($a, '=') !== false) { list($k, $v) = explode('=', $a, 2); $_GET[$k] = $v; }
    }
}

$key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') {
    if (!$cli) http_response_code(403);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Interdit']));
}

@set_time_limit(0); // on gere nous-memes la borne via max_seconds

$cacheDir   = __DIR__ . '/../cache';
$ttlHours   = max(1, (int)($_GET['hours'] ?? 24));
$ttlSeconds = $ttlHours * 3600;
$prefix     = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['prefix'] ?? ''); // securise : pas de path traversal
$dryRun     = !empty($_GET['dry']);
$maxSeconds = max(5, min(120, (int)($_GET['max_seconds'] ?? 50)));

$start = microtime(true);
$now   = time();

$out = [
    'success'     => true,
    'dir'         => 'cache',
    'ttl_hours'   => $ttlHours,
    'prefix'      => $prefix ?: '*',
    'dry_run'     => $dryRun,
    'scanned'     => 0,
    'deleted'     => 0,
    'kept_fresh'  => 0,
    'errors'      => 0,
    'bytes_freed' => 0,
    'done'        => true,
];

if (!is_dir($cacheDir)) {
    $out['success'] = false;
    $out['error'] = 'Dossier cache inexistant';
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

$dh = @opendir($cacheDir);
if (!$dh) {
    $out['success'] = false;
    $out['error'] = 'Impossible d\'ouvrir le dossier cache';
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

$prefixLen = strlen($prefix);
while (($name = readdir($dh)) !== false) {
    if ($name === '.' || $name === '..') continue;
    // On ne traite que les .json (laisse .htaccess et autres tranquilles)
    if (substr($name, -5) !== '.json') continue;
    // Filtre prefixe eventuel (ex: search_, athlete_)
    if ($prefixLen && (strncmp($name, $prefix . '_', $prefixLen + 1) !== 0)) continue;

    $out['scanned']++;
    $path = $cacheDir . '/' . $name;
    $mtime = @filemtime($path);
    if ($mtime === false) { $out['errors']++; continue; }

    if (($now - $mtime) < $ttlSeconds) {
        $out['kept_fresh']++; // cache encore valide => on n'y touche pas
        continue;
    }

    $size = @filesize($path) ?: 0;
    if ($dryRun) {
        $out['deleted']++;          // ce qui SERAIT supprime
        $out['bytes_freed'] += $size;
    } elseif (@unlink($path)) {
        $out['deleted']++;
        $out['bytes_freed'] += $size;
    } else {
        $out['errors']++;
    }

    // Borne temps : si on depasse le budget, on s'arrete proprement.
    // Le reste sera balaye au prochain passage du cron.
    if ((microtime(true) - $start) > $maxSeconds) {
        $out['done'] = false;
        break;
    }
}
closedir($dh);

$out['elapsed_s']   = round(microtime(true) - $start, 2);
$out['freed_human'] = round($out['bytes_freed'] / 1048576, 2) . ' Mo';

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_PRETTY_PRINT);
