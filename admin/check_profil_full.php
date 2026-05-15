<?php
/**
 * admin/check_profil_full.php — Verification totale du profil athlete
 *
 * Compare ce que renvoie l'API avec ce qui est dans le HTML rendu.
 * Teste sur plusieurs profils varies.
 */
if (PHP_SAPI !== 'cli') die('cli only');
@set_time_limit(0);

$ctx = stream_context_create(['http' => ['timeout' => 60, 'ignore_errors' => true,
    'header' => "User-Agent: Mozilla/5.0\r\n"]]);

$profiles = [
    ['571043',  'Kevin MAYER (decathlonien)'],
    ['809035',  'Ndenga LUVUMBU (polyvalent)'],
    ['119122',  'Rene RAUSCHER (veteran)'],
    ['1772693', 'Au hasard'],
];

function fmt($n) { return number_format((int)$n, 0, '.', ' '); }
function hr()    { echo str_repeat('-', 78) . "\n"; }

foreach ($profiles as [$id, $label]) {
    echo "\n" . str_repeat('=', 78) . "\n";
    echo "  PROFIL : $label (id externe $id)\n";
    echo str_repeat('=', 78) . "\n";

    $t0 = microtime(true);
    $apiBody  = @file_get_contents("http://localhost/BK/api/athlete.php?id=$id", false, $ctx);
    $tApi = round(microtime(true) - $t0, 2);
    $j = json_decode($apiBody, true);
    if (!$j || empty($j['success'])) { echo "  [SKIP] profil inaccessible\n"; continue; }

    $t0 = microtime(true);
    $htmlBody = @file_get_contents("http://localhost/BK/recherche?page=profil&id=$id", false, $ctx);
    $tHtml = round(microtime(true) - $t0, 2);

    echo "Time : API={$tApi}s  HTML={$tHtml}s  HTML size=" . fmt(strlen($htmlBody)) . " bytes\n\n";

    // === [1] IDENTITE ===
    echo "[1] Identite :\n";
    $i = $j['identite'] ?? [];
    foreach (['nom_complet','sexe','categorie','nationalite','taille_cm','poids_kg','meilleur_niveau'] as $k) {
        $v = $i[$k] ?? '-';
        $present = ($v && $v !== '-') ? '✓' : ' ';
        echo "  $present  " . str_pad($k, 18) . " = " . (is_scalar($v) ? $v : '?') . "\n";
    }

    // === [2] CARRIERE EN CHIFFRES (compteurs du profil rendu) ===
    echo "\n[2] Career stats (HTML rendered) :\n";
    preg_match_all('#<div class="p2-career-stat"><div class="v">([^<]+)</div><div class="k">([^<]+)</div>#', $htmlBody, $m, PREG_SET_ORDER);
    $career = [];
    foreach ($m as $x) {
        $career[$x[2]] = $x[1];
        echo "  " . str_pad($x[2], 22) . " = " . $x[1] . "\n";
    }

    // === [3] DONNEES API (verite) ===
    echo "\n[3] API data counts (verite) :\n";
    foreach (['records','resultats','progressions','podiums','selections','medailles','niveaux','clubs'] as $k) {
        echo "  " . str_pad($k, 18) . " = " . fmt(count($j[$k] ?? [])) . "\n";
    }

    // === [4] COHERENCE ===
    echo "\n[4] Coherence (compteur HTML vs verite API) :\n";
    $checks = [
        'Records perso'    => ['records', count($j['records']??[])],
        'Podiums'          => ['podiums', count($j['podiums']??[])],
        'Selections'       => ['selections', count($j['selections']??[])],
    ];
    foreach ($checks as $lbl => [$apiKey, $apiVal]) {
        $htmlVal = (int)($career[$lbl] ?? -1);
        $ok = ($htmlVal === (int)$apiVal);
        $marker = $ok ? '✓' : '✗';
        echo "  $marker  " . str_pad($lbl, 22) . " HTML=" . $htmlVal . "  API=" . $apiVal . ($ok ? '' : "  ECART") . "\n";
    }

    // Disciplines : depuis records + resultats + progressions
    $disc = [];
    foreach ($j['records']??[] as $r) if (!empty($r['epreuve'])) $disc[$r['epreuve']] = 1;
    foreach ($j['resultats']??[] as $r) if (!empty($r['epreuve'])) $disc[$r['epreuve']] = 1;
    foreach ($j['progressions']??[] as $r) if (!empty($r['epreuve'])) $disc[$r['epreuve']] = 1;
    $htmlDisc = (int)($career['Disciplines'] ?? -1);
    $okD = ($htmlDisc === count($disc));
    echo "  " . ($okD?'✓':'✗') . "  " . str_pad('Disciplines', 22) . " HTML=" . $htmlDisc . "  Verif=" . count($disc) . ($okD?'':'  ECART') . "\n";

    // Annees actives
    $years = [];
    foreach ($j['records']??[] as $r) { $y = (int)substr($r['date']??'', 0, 4); if ($y) $years[$y]=1; }
    foreach ($j['resultats']??[] as $r) if (!empty($r['annee'])) $years[$r['annee']]=1;
    foreach ($j['progressions']??[] as $r) if (!empty($r['annee'])) $years[$r['annee']]=1;
    $htmlY = (int)($career['Annees actives'] ?? -1);
    $okY = ($htmlY === count($years));
    echo "  " . ($okY?'✓':'✗') . "  " . str_pad('Annees actives', 22) . " HTML=" . $htmlY . "  Verif=" . count($years) . ($okY?'':'  ECART') . "\n";

    // === [5] PRESENCE SECTIONS HTML ===
    echo "\n[5] Sections dans le HTML rendu :\n";
    $sections = [
        'Bio auto-generee'    => '"p2-bio-text"',
        'Tableau Records'     => 'p2-rec-list',
        'Tableau Podiums'     => 'p2-pod-list',
        'Tableau Medailles'   => 'p2-medal',
        'Tableau Selections'  => 'p2-sel-',
        'Tableau Resultats'   => 'p2-res-',
        'Graph Progressions'  => 'p2-prog-canvas',
        'Career chiffres'     => 'p2-career-stat',
        'Carte lieux'         => 'p2-map',
        'Timeline'            => 'p2-timeline',
    ];
    foreach ($sections as $lbl => $needle) {
        $n = substr_count($htmlBody, $needle);
        echo "  " . ($n > 0 ? '✓' : ' ') . "  " . str_pad($lbl, 22) . " (" . $n . " hits)\n";
    }
}

echo "\n" . str_repeat('=', 78) . "\n";
echo "  FIN VERIFICATION\n";
echo str_repeat('=', 78) . "\n";
