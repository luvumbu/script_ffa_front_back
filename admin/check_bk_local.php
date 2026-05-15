<?php
/**
 * admin/check_bk_local.php — Diagnostic complet BDD + API
 * Usage : php admin/check_bk_local.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); die('CLI only'); }
require_once __DIR__ . '/../core/db.php';

$BASE = 'http://localhost/BK';

function section($t) { echo "\n" . str_repeat('=', 72) . "\n  $t\n" . str_repeat('=', 72) . "\n"; }
function line($k, $v, $w = 50) { echo str_pad($k, $w) . ' = ' . $v . "\n"; }
function fmt($n) { return number_format((float)$n, 0, '.', ' '); }
function ok($t)  { echo "  [OK] $t\n"; }
function bad($t) { echo "  [KO] $t\n"; }
function hit($url, $timeout = 30) {
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true,
        'header' => "User-Agent: Mozilla/5.0 (check)\r\nAccept: application/json\r\n"]]);
    $t0 = microtime(true);
    $body = @file_get_contents($url, false, $ctx);
    $dt = round(microtime(true) - $t0, 2);
    $http = $http_response_header[0] ?? 'NO_RESP';
    $j = $body === false ? null : json_decode($body, true);
    return ['ms' => $dt, 'http' => $http, 'size' => $body === false ? 0 : strlen($body), 'json' => $j, 'raw' => $body];
}

// ════════════════════════════════════════════════════════
section('[1] ENVIRONNEMENT');
// ════════════════════════════════════════════════════════
$dbname = $conn->query('SELECT DATABASE()')->fetch_row()[0];
line('Base de donnees', $dbname);
line('MySQL version',  $conn->query('SELECT VERSION()')->fetch_row()[0]);
line('URL de base',    $BASE);

// ════════════════════════════════════════════════════════
section('[2] COMPTAGES TABLES PRINCIPALES');
// ════════════════════════════════════════════════════════
$tables = ['athletes','athlete_records','athlete_clubs','athlete_progressions','athlete_resultats',
           'athlete_medailles','athlete_podiums','athlete_selections','athlete_niveaux','athlete_niv_perfs',
           'clubs','epreuves','villes','competitions','categories','nationalites',
           'users','user_sessions','logs','search_tracking'];
foreach ($tables as $t) {
    $exists = $conn->query("SHOW TABLES LIKE '$t'")->num_rows > 0;
    if (!$exists) { line($t, 'TABLE ABSENTE'); continue; }
    $n = $conn->query("SELECT COUNT(*) FROM `$t`")->fetch_row()[0];
    line($t, fmt($n));
}

// ════════════════════════════════════════════════════════
section('[3] COHERENCE FK (orphelins)');
// ════════════════════════════════════════════════════════
$fkChecks = [
    ['athlete_records',      'id_athlete', 'athletes',    'id_athlete'],
    ['athlete_clubs',        'id_athlete', 'athletes',    'id_athlete'],
    ['athlete_clubs',        'id_club',    'clubs',       'id_club'],
    ['athlete_progressions', 'id_athlete', 'athletes',    'id_athlete'],
    ['athlete_progressions', 'id_club',    'clubs',       'id_club'],
    ['athlete_resultats',    'id_athlete', 'athletes',    'id_athlete'],
    ['athlete_resultats',    'id_epreuve', 'epreuves',    'id_epreuve'],
    ['athlete_medailles',    'id_athlete', 'athletes',    'id_athlete'],
    ['athlete_podiums',      'id_athlete', 'athletes',    'id_athlete'],
    ['athlete_selections',   'id_athlete', 'athletes',    'id_athlete'],
    ['athlete_niveaux',      'id_athlete', 'athletes',    'id_athlete'],
];
foreach ($fkChecks as [$tc, $cc, $tp, $cp]) {
    $sql = "SELECT COUNT(*) FROM `$tc` x WHERE x.`$cc` IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `$tp` p WHERE p.`$cp` = x.`$cc`)";
    $n = $conn->query($sql)->fetch_row()[0];
    $msg = "$tc.$cc -> $tp.$cp";
    if ((int)$n === 0) ok("$msg : 0 orphelin");
    else bad("$msg : " . fmt($n) . " orphelins");
}

// ════════════════════════════════════════════════════════
section('[4] COUVERTURE DONNEES');
// ════════════════════════════════════════════════════════
$totalAthletes = $conn->query("SELECT COUNT(*) FROM athletes")->fetch_row()[0];
line('Athletes total',                                fmt($totalAthletes));
line('Athletes avec >=1 record',                      fmt($conn->query("SELECT COUNT(DISTINCT id_athlete) FROM athlete_records")->fetch_row()[0]));
line('Athletes avec >=1 club',                        fmt($conn->query("SELECT COUNT(DISTINCT id_athlete) FROM athlete_clubs")->fetch_row()[0]));
line('Athletes avec >=1 progression',                 fmt($conn->query("SELECT COUNT(DISTINCT id_athlete) FROM athlete_progressions")->fetch_row()[0]));
line('Athletes avec >=1 medaille',                    fmt($conn->query("SELECT COUNT(DISTINCT id_athlete) FROM athlete_medailles")->fetch_row()[0]));
line('Athletes avec >=1 podium',                      fmt($conn->query("SELECT COUNT(DISTINCT id_athlete) FROM athlete_podiums")->fetch_row()[0]));
line('Athletes avec >=1 selection',                   fmt($conn->query("SELECT COUNT(DISTINCT id_athlete) FROM athlete_selections")->fetch_row()[0]));
line('Athletes Hommes',                               fmt($conn->query("SELECT COUNT(*) FROM athletes WHERE sexe_athlete='M'")->fetch_row()[0]));
line('Athletes Femmes',                               fmt($conn->query("SELECT COUNT(*) FROM athletes WHERE sexe_athlete='F'")->fetch_row()[0]));
line('Clubs avec >=1 athlete',                        fmt($conn->query("SELECT COUNT(DISTINCT id_club) FROM athlete_clubs WHERE id_club IS NOT NULL")->fetch_row()[0]));
line('Epreuves avec >=1 record',                      fmt($conn->query("SELECT COUNT(DISTINCT id_epreuve) FROM athlete_records WHERE id_epreuve IS NOT NULL")->fetch_row()[0]));
line('Villes avec >=1 record',                        fmt($conn->query("SELECT COUNT(DISTINCT id_ville) FROM athlete_records WHERE id_ville IS NOT NULL")->fetch_row()[0]));

// ════════════════════════════════════════════════════════
section('[5] EXEMPLES DONNEES');
// ════════════════════════════════════════════════════════
echo "\n-- Athlete TOP records --\n";
$r = $conn->query("SELECT a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete, a.sexe_athlete,
                          (SELECT COUNT(*) FROM athlete_records WHERE id_athlete=a.id_athlete) nr
                   FROM athletes a ORDER BY nr DESC LIMIT 3");
while ($x = $r->fetch_assoc()) echo "  #{$x['id_athlete']} ext={$x['athlete_id_externe']} {$x['nom_complet_athlete']} ({$x['sexe_athlete']}) {$x['nr']} records\n";

echo "\n-- TOP 5 Clubs par nb athletes --\n";
$r = $conn->query("SELECT c.nom_club, COUNT(DISTINCT ac.id_athlete) nb FROM clubs c
                   JOIN athlete_clubs ac ON ac.id_club=c.id_club
                   GROUP BY c.id_club ORDER BY nb DESC LIMIT 5");
while ($x = $r->fetch_assoc()) echo "  " . str_pad($x['nom_club'], 50) . " " . fmt($x['nb']) . " athletes\n";

echo "\n-- TOP 5 Epreuves par nb records --\n";
$r = $conn->query("SELECT e.nom_epreuve, COUNT(*) nb FROM epreuves e
                   JOIN athlete_records r ON r.id_epreuve=e.id_epreuve
                   GROUP BY e.id_epreuve ORDER BY nb DESC LIMIT 5");
while ($x = $r->fetch_assoc()) echo "  " . str_pad($x['nom_epreuve'], 40) . " " . fmt($x['nb']) . " records\n";

echo "\n-- TOP 5 Villes par nb records --\n";
$r = $conn->query("SELECT v.nom_ville, COUNT(*) nb FROM villes v
                   JOIN athlete_records r ON r.id_ville=v.id_ville
                   GROUP BY v.id_ville ORDER BY nb DESC LIMIT 5");
while ($x = $r->fetch_assoc()) echo "  " . str_pad($x['nom_ville'], 30) . " " . fmt($x['nb']) . " records\n";

// ════════════════════════════════════════════════════════
section('[6] TEST API ENDPOINTS');
// ════════════════════════════════════════════════════════
$tests = [
    'search.php?nom=MARTIN&limit=3'                          => ['key' => 'total',       'min' => 100],
    'search.php?nom=DUPONT&limit=3'                          => ['key' => 'total',       'min' => 50],
    'search.php?nom=MARTIN&sexe=F&limit=3'                   => ['key' => 'total',       'min' => 20],
    'search.php?categorie=SE&limit=3'                        => ['key' => 'total',       'min' => 100],
    'search.php?nationalite=FRA&limit=3'                     => ['key' => 'total',       'min' => 100],
    'clubs.php?limit=10'                                     => ['key' => 'total',       'min' => 100],
    'epreuves.php?limit=10'                                  => ['key' => 'total',       'min' => 100],
    'villes.php?limit=10'                                    => ['key' => 'total',       'min' => 100],
    'stats.php'                                              => ['key' => 'comptages',   'min' => 0, 'type' => 'array'],
    'classement.php?epreuve=1&limit=5'                       => ['key' => null],
];

// Pick a sample athlete id externe
$sample = $conn->query("SELECT athlete_id_externe FROM athletes WHERE nom_complet_athlete!='' ORDER BY id_athlete DESC LIMIT 1")->fetch_row()[0] ?? 0;
if ($sample) $tests["athlete.php?id=$sample"] = ['key' => 'identite', 'type' => 'array'];

// Pick a sample club
$club = $conn->query("SELECT nom_club FROM clubs ORDER BY id_club LIMIT 1 OFFSET 50")->fetch_row()[0] ?? '';
if ($club) $tests['club_stats.php?nom=' . urlencode($club)] = ['key' => 'club', 'type' => 'array'];

// Pick a sample ville
$ville = $conn->query("SELECT v.nom_ville FROM villes v JOIN athlete_records r ON r.id_ville=v.id_ville GROUP BY v.id_ville ORDER BY COUNT(*) DESC LIMIT 1")->fetch_row()[0] ?? '';
if ($ville) $tests['ville_stats.php?nom=' . urlencode($ville)] = ['key' => null];

// Pick a sample epreuve
$ep = $conn->query("SELECT e.nom_epreuve FROM epreuves e JOIN athlete_records r ON r.id_epreuve=e.id_epreuve GROUP BY e.id_epreuve ORDER BY COUNT(*) DESC LIMIT 1")->fetch_row()[0] ?? '';
if ($ep) $tests['epreuve_stats.php?nom=' . urlencode($ep)] = ['key' => null];

foreach ($tests as $path => $cfg) {
    $url = "$BASE/api/$path";
    $r = hit($url, 60);
    $status = '';
    $det = '';
    if (!is_array($r['json'])) {
        bad("$path -> non-JSON ({$r['http']}, {$r['ms']}s, " . $r['size'] . " bytes)");
        if ($r['raw']) echo "       Body[:200]: " . substr(strip_tags($r['raw']), 0, 200) . "\n";
        continue;
    }
    $j = $r['json'];
    if (isset($j['error'])) {
        bad("$path -> error: {$j['error']}");
        continue;
    }
    // Check key
    $det = "ms={$r['ms']}s";
    if ($cfg['key'] === null) {
        if (isset($j['success']) && $j['success']) ok("$path  $det success=true");
        else bad("$path  $det no success");
        continue;
    }
    $val = $j[$cfg['key']] ?? null;
    if (($cfg['type'] ?? null) === 'array') {
        if (is_array($val)) ok("$path  $det {$cfg['key']}=array(" . count($val) . ")");
        else bad("$path  $det {$cfg['key']} pas un array");
    } else {
        $n = is_numeric($val) ? (int)$val : 0;
        $min = $cfg['min'] ?? 0;
        if ($n >= $min) ok("$path  $det {$cfg['key']}=" . fmt($n));
        else bad("$path  $det {$cfg['key']}=" . fmt($n) . " < $min attendu");
    }
}

// ════════════════════════════════════════════════════════
section('[7] TEST PAGES PUBLIQUES');
// ════════════════════════════════════════════════════════
$pages = [
    '/index.php?page=accueil'                  => ['expect' => 'Bokonzi'],
    '/recherche?nom=MARTIN'                    => ['expect' => 'MARTIN'],
    '/index.php?page=clubs'                    => ['expect' => 'Clubs'],
    '/index.php?page=villes'                   => ['expect' => 'Villes'],
    '/index.php?page=epreuves'                 => ['expect' => 'Epreuves|épreuve'],
];
if ($sample) $pages["/profil/$sample"] = ['expect' => null];

foreach ($pages as $path => $cfg) {
    $url = "$BASE$path";
    $r = hit($url, 60);
    if ($r['size'] === 0) { bad("$path -> 0 bytes ({$r['http']})"); continue; }
    if ($cfg['expect']) {
        if (preg_match('/(' . $cfg['expect'] . ')/i', $r['raw'])) ok("$path  ms={$r['ms']}s  size=" . fmt($r['size']) . "  match");
        else bad("$path  ms={$r['ms']}s  size=" . fmt($r['size']) . "  NO MATCH for '{$cfg['expect']}'");
    } else {
        ok("$path  ms={$r['ms']}s  size=" . fmt($r['size']));
    }
}

echo "\n" . str_repeat('=', 72) . "\n  FIN\n" . str_repeat('=', 72) . "\n";
