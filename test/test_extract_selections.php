<?php
/**
 * Test du parsing extractSelections() sur des athletes avec selections connues
 * Usage : php C:\xampp\php\php.exe test/test_extract_selections.php
 */

require_once __DIR__ . '/../Class/AthleteScraper.php';

$athletes = [
    264469  => 'Renaud LAVILLENIE (Perche)',
    1692235 => 'Jimmy GRESSIER (Course)',
    741074  => 'Christophe LEMAITRE (Sprint)',
];

foreach ($athletes as $id => $label) {
    echo "===== $label (id=$id) =====\n";

    $url = "https://athle.fr/athletes/$id/selections";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) {
        echo "FETCH FAILED\n\n";
        continue;
    }

    $s = new AthleteScraper($id);
    $s->html = $html;
    $s->extractSelections();

    $n = count($s->selections);
    echo "Nb selections parsees : $n\n";

    // Afficher les 5 premieres pour verification
    foreach (array_slice($s->selections, 0, 5) as $i => $sel) {
        echo sprintf(
            "  [%d] %s | %s | %s | %s | %s | rang=%d | perf=%s (%d) | tour=%s\n",
            $i + 1,
            $sel['type'],
            $sel['date'],
            $sel['duree_jours'] . 'j',
            substr($sel['competition'], 0, 45),
            substr($sel['epreuve'], 0, 25),
            $sel['classement'],
            $sel['performance_brut'],
            $sel['performance'],
            $sel['tour']
        );
    }
    if ($n > 5) echo "  ... " . ($n - 5) . " autres\n";
    echo "\n";
}
