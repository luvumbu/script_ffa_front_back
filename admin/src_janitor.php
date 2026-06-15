<?php
/**
 * admin/src_janitor.php — Purge des fichiers src/{id}.php pour liberer des inodes (Hostinger)
 *
 * src/{id}.php = 1 fichier par athlete scrape (~300k fichiers = ~300k inodes).
 * Ces fichiers ne servent QU'A une optim SEO : pages/profil.php et
 * pages/global_athlete.php retombent sur l'API/BDD si le fichier n'existe pas.
 * => Les supprimer ne casse rien, le SEO repasse par le cache API (nettoye par cache_janitor.php).
 *
 * A LANCER VIA LE CRON ou en HTTP manuel (jamais critique dans une requete visiteur).
 * Memory constante (opendir/readdir streaming) + borne dans le temps : zero impact perf,
 * reprend au passage suivant si le dossier est enorme.
 *
 * Usage cron (Hostinger) :
 *   /usr/bin/php /home/UXXXX/domains/bokonzi.com/public_html/admin/src_janitor.php bk_key=bk_s3cr3t_2026_xK9mP
 * Usage HTTP :
 *   https://bokonzi.com/admin/src_janitor.php?bk_key=...
 *   &dry=1            => simulation, ne supprime rien (compte seulement) -- A FAIRE EN PREMIER
 *   &days=0           => age minimum en JOURS (defaut 0 = tout purger ; ex 30 = garder les recents)
 *   &max_seconds=50   => budget temps par passage (defaut 50)
 *
 * Reponse JSON : scanned, deleted, kept_fresh, bytes_freed, elapsed, done
 *   done=false => dossier pas entierement parcouru, relancer (ou attendre le prochain cron)
 */

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

@set_time_limit(0);

$srcDir     = __DIR__ . '/../src';
$days       = max(0, (int)($_GET['days'] ?? 0));      // 0 = tout, sinon garde les < N jours
$ttlSeconds = $days * 86400;
$dryRun     = !empty($_GET['dry']);
$maxSeconds = max(5, min(120, (int)($_GET['max_seconds'] ?? 50)));

$start = microtime(true);
$now   = time();

$out = [
    'success'     => true,
    'dir'         => 'src',
    'min_age_days'=> $days,
    'dry_run'     => $dryRun,
    'scanned'     => 0,
    'deleted'     => 0,
    'kept_fresh'  => 0,
    'errors'      => 0,
    'bytes_freed' => 0,
    'done'        => true,
];

if (!is_dir($srcDir)) {
    $out['success'] = false;
    $out['error'] = 'Dossier src inexistant (rien a purger)';
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

$dh = @opendir($srcDir);
if (!$dh) {
    $out['success'] = false;
    $out['error'] = 'Impossible d\'ouvrir le dossier src';
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

while (($name = readdir($dh)) !== false) {
    if ($name === '.' || $name === '..') continue;
    if (substr($name, -4) !== '.php') continue; // on ne touche que les {id}.php

    $out['scanned']++;
    $path = $srcDir . '/' . $name;

    if ($ttlSeconds > 0) {
        $mtime = @filemtime($path);
        if ($mtime !== false && ($now - $mtime) < $ttlSeconds) {
            $out['kept_fresh']++; // assez recent => on garde (optim SEO conservee)
            continue;
        }
    }

    $size = @filesize($path) ?: 0;
    if ($dryRun) {
        $out['deleted']++;
        $out['bytes_freed'] += $size;
    } elseif (@unlink($path)) {
        $out['deleted']++;
        $out['bytes_freed'] += $size;
    } else {
        $out['errors']++;
    }

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
