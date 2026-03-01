<?php
/**
 * Correction des performances entières mal calculées.
 * Bug : 10''9 stocké comme 1009 (10.09s) au lieu de 1090 (10.9s)
 * Cause : str_pad manquant pour les dixièmes (1 chiffre après '')
 * Fix : FLOOR(perf/100)*100 + MOD(perf,100)*10
 */
require_once __DIR__ . '/../api/config.php';

$tables = [
    ['athlete_records',      'performance_record',      'performance_brut_record',      'id_record'],
    ['athlete_progressions', 'performance_progression', 'performance_brut_progression', 'id_progression'],
    ['athlete_resultats',    'performance_resultat',    'performance_brut_resultat',    'id_resultat'],
];

$dryRun = !isset($_GET['go']);
$results = [];

foreach ($tables as [$table, $colInt, $colBrut, $colPk]) {
    // Compter
    $r = $conn->query("
        SELECT COUNT(*) as c FROM `$table`
        WHERE `$colBrut` REGEXP \"''[0-9]$\"
          AND `$colBrut` NOT REGEXP \"''[0-9][0-9]$\"
          AND `$colInt` > 0
    ");
    $count = $r ? (int)$r->fetch_assoc()['c'] : 0;

    // Exemples avant/après
    $examples = [];
    $r2 = $conn->query("
        SELECT `$colPk`, `$colBrut`, `$colInt` FROM `$table`
        WHERE `$colBrut` REGEXP \"''[0-9]$\"
          AND `$colBrut` NOT REGEXP \"''[0-9][0-9]$\"
          AND `$colInt` > 0
        LIMIT 10
    ");
    if ($r2) while ($row = $r2->fetch_assoc()) {
        $old = (int)$row[$colInt];
        $new = intval($old / 100) * 100 + ($old % 100) * 10;
        $examples[] = [
            'brut' => $row[$colBrut],
            'old'  => $old,
            'new'  => $new,
        ];
    }

    $updated = 0;
    if (!$dryRun && $count > 0) {
        $conn->query("
            UPDATE `$table`
            SET `$colInt` = FLOOR(`$colInt` / 100) * 100 + MOD(`$colInt`, 100) * 10
            WHERE `$colBrut` REGEXP \"''[0-9]$\"
              AND `$colBrut` NOT REGEXP \"''[0-9][0-9]$\"
              AND `$colInt` > 0
        ");
        $updated = $conn->affected_rows;
    }

    $results[] = [
        'table'    => $table,
        'affected' => $count,
        'updated'  => $dryRun ? 'dry_run' : $updated,
        'examples' => $examples,
    ];
}

jsonResponse([
    'success'  => true,
    'dry_run'  => $dryRun,
    'message'  => $dryRun ? "Ajoutez ?go pour executer les corrections" : "Corrections appliquees",
    'tables'   => $results,
]);
