<?php
/**
 * exemples.php — Exemples d'utilisation de l'API Bokonzi
 *
 * Usage : http://localhost/BK/exemples.php
 */

$BASE = "https://bokonzi.com/api";

function dateFR($d) {
    if (!$d || $d === '-') return '-';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $d;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exemples API Bokonzi</title>
    <style>
        body { background: #0d1117; color: #c9d1d9; font-family: 'Segoe UI', Arial, sans-serif; padding: 30px; max-width: 1000px; margin: 0 auto; }
        h1 { color: #58a6ff; }
        h2 { color: #58a6ff; margin-top: 40px; border-bottom: 1px solid #21262d; padding-bottom: 8px; }
        pre { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 15px; overflow-x: auto; font-size: 13px; color: #7ee787; margin: 10px 0; }
        .result { background: #1c2128; border: 1px solid #30363d; border-radius: 8px; padding: 15px; margin: 10px 0; }
        .label { color: #8b949e; font-size: 12px; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #21262d; color: #58a6ff; padding: 8px 12px; text-align: left; font-size: 13px; }
        td { padding: 6px 12px; border-top: 1px solid #21262d; font-size: 13px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .perf { background: #8957e533; color: #bc8cff; }
        .or { background: #ffd70033; color: #ffd700; }
        .argent { background: #c0c0c033; color: #c0c0c0; }
        .bronze { background: #cd7f3233; color: #cd7f32; }
        .error { color: #f85149; }

        /* Wrapper scrollable pour les tableaux */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 10px 0; }
        .table-wrap table { margin: 0; }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            body { padding: 15px; }
            pre { font-size: 12px; padding: 10px; }
            th, td { padding: 5px 8px; font-size: 12px; white-space: nowrap; }
            h1 { font-size: 22px; }
            h2 { font-size: 16px; }
        }
        @media (max-width: 600px) {
            body { padding: 10px; }
            pre { font-size: 11px; overflow-x: auto; }
            h1 { font-size: 18px; }
        }
    </style>
</head>
<body>
<?php include dirname(__DIR__) . '/nav.php'; ?>

<h1>Exemples d'utilisation de l'API</h1>
<p style="color:#8b949e;">Chaque exemple montre le code PHP + le resultat en direct depuis <b><?= $BASE ?></b></p>


<!-- ======================================== -->
<h2>1. Recuperer un athlete par son ID</h2>
<!-- ======================================== -->

<pre>
$json = file_get_contents("<?= $BASE ?>/athlete.php?id=26134");
$data = json_decode($json, true);

echo $data['identite']['nom_complet'];
echo $data['identite']['sexe'];
echo $data['identite']['categorie'];
</pre>

<div class="label">Resultat :</div>
<div class="result">
<?php
$json = @file_get_contents("$BASE/athlete.php?id=26134");
if ($json):
    $data = json_decode($json, true);
    if ($data && ($data['success'] ?? false)):
        $i = $data['identite'];
?>
    <b>Nom :</b> <?= htmlspecialchars($i['nom_complet']) ?><br>
    <b>Sexe :</b> <?= $i['sexe'] ?><br>
    <b>Categorie :</b> <?= htmlspecialchars($i['categorie']) ?><br>
    <b>Naissance :</b> <?= dateFR($i['date_naissance'] ?? '-') ?><br>
    <b>Nationalite :</b> <?= htmlspecialchars($i['nationalite']) ?><br>
    <b>Licence :</b> <?= htmlspecialchars($i['licence']) ?><br>
<?php else: ?>
    <span class="error">Athlete non trouve</span>
<?php endif; else: ?>
    <span class="error">Serveur injoignable</span>
<?php endif; ?>
</div>


<!-- ======================================== -->
<h2>2. Lister les records d'un athlete</h2>
<!-- ======================================== -->

<pre>
$json = file_get_contents("<?= $BASE ?>/athlete.php?id=26134");
$data = json_decode($json, true);

foreach ($data['records'] as $r) {
    echo $r['epreuve'] . " : " . $r['performance_brut'] . "\n";
}
</pre>

<div class="label">Resultat :</div>
<div class="result">
<?php
if ($data && !empty($data['records'])):
?>
<div class="table-wrap"><table>
    <tr><th>Epreuve</th><th>Performance</th><th>Date</th><th>Lieu</th><th>Club</th></tr>
    <?php foreach ($data['records'] as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r['epreuve']) ?></td>
        <td><span class="badge perf"><?= htmlspecialchars($r['performance_brut'] ?: $r['performance']) ?></span></td>
        <td><?= dateFR($r['date'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['lieu']) ?></td>
        <td><?= htmlspecialchars($r['club']) ?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php elseif ($data): ?>
    <span style="color:#8b949e;">Aucun record pour cet athlete</span>
<?php endif; ?>
</div>


<!-- ======================================== -->
<h2>3. Lister les clubs d'un athlete</h2>
<!-- ======================================== -->

<pre>
$json = file_get_contents("<?= $BASE ?>/athlete.php?id=26134");
$data = json_decode($json, true);

foreach ($data['clubs'] as $c) {
    echo $c['nom_club'] . " (" . $c['annee_debut'] . " - " . $c['annee_fin'] . ")\n";
}
</pre>

<div class="label">Resultat :</div>
<div class="result">
<?php if ($data && !empty($data['clubs'])): ?>
    <?php foreach ($data['clubs'] as $c): ?>
        <b><?= htmlspecialchars($c['nom_club']) ?></b> (<?= $c['annee_debut'] ?? '?' ?> - <?= $c['annee_fin'] ?? '?' ?>)<br>
    <?php endforeach; ?>
<?php else: ?>
    <span style="color:#8b949e;">Aucun club</span>
<?php endif; ?>
</div>


<!-- ======================================== -->
<h2>4. Lister les medailles</h2>
<!-- ======================================== -->

<pre>
foreach ($data['medailles'] as $m) {
    echo $m['type'] . " | " . $m['epreuve'] . " | " . $m['competition'] . "\n";
}
</pre>

<div class="label">Resultat :</div>
<div class="result">
<?php if ($data && !empty($data['medailles'])): ?>
<div class="table-wrap"><table>
    <tr><th>Type</th><th>Annee</th><th>Epreuve</th><th>Competition</th><th>Lieu</th></tr>
    <?php foreach ($data['medailles'] as $m): ?>
    <tr>
        <td><span class="badge <?= $m['type'] ?>"><?= ucfirst($m['type']) ?></span></td>
        <td><?= $m['annee'] ?></td>
        <td><?= htmlspecialchars($m['epreuve']) ?></td>
        <td><?= htmlspecialchars($m['competition']) ?></td>
        <td><?= htmlspecialchars($m['lieu']) ?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php else: ?>
    <span style="color:#8b949e;">Aucune medaille</span>
<?php endif; ?>
</div>


<!-- ======================================== -->
<h2>5. Lister les resultats</h2>
<!-- ======================================== -->

<pre>
foreach ($data['resultats'] as $r) {
    echo dateFR($r['date']) . " | " . $r['epreuve'] . " | " . $r['performance_brut'] . " | place " . $r['place'] . "\n";
}
</pre>

<div class="label">Resultat (20 premiers) :</div>
<div class="result">
<?php if ($data && !empty($data['resultats'])): ?>
<div class="table-wrap"><table>
    <tr><th>Date</th><th>Epreuve</th><th>Perf</th><th>Vent</th><th>Tour</th><th>Place</th><th>Niveau</th><th>Lieu</th></tr>
    <?php foreach (array_slice($data['resultats'], 0, 20) as $r): ?>
    <tr>
        <td><?= dateFR($r['date'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['epreuve']) ?></td>
        <td><span class="badge perf"><?= htmlspecialchars($r['performance_brut'] ?: $r['performance']) ?></span></td>
        <td><?= htmlspecialchars($r['vent']) ?></td>
        <td><?= htmlspecialchars($r['tour']) ?></td>
        <td><?= $r['place'] ?></td>
        <td><?= htmlspecialchars($r['niveau']) ?></td>
        <td><?= htmlspecialchars($r['lieu']) ?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php if (count($data['resultats']) > 20): ?>
    <p style="color:#8b949e;">... et <?= count($data['resultats']) - 20 ?> autres resultats</p>
<?php endif; ?>
<?php else: ?>
    <span style="color:#8b949e;">Aucun resultat</span>
<?php endif; ?>
</div>


<!-- ======================================== -->
<h2>6. Lister les progressions</h2>
<!-- ======================================== -->

<pre>
foreach ($data['progressions'] as $p) {
    echo $p['annee'] . " | " . $p['epreuve'] . " | " . $p['performance_brut'] . "\n";
}
</pre>

<div class="label">Resultat :</div>
<div class="result">
<?php if ($data && !empty($data['progressions'])): ?>
<div class="table-wrap"><table>
    <tr><th>Annee</th><th>Epreuve</th><th>Performance</th><th>Vent</th><th>Date</th><th>Lieu</th><th>Club</th></tr>
    <?php foreach ($data['progressions'] as $pr): ?>
    <tr>
        <td><?= $pr['annee'] ?></td>
        <td><?= htmlspecialchars($pr['epreuve']) ?></td>
        <td><span class="badge perf"><?= htmlspecialchars($pr['performance_brut'] ?: $pr['performance']) ?></span></td>
        <td><?= htmlspecialchars($pr['vent']) ?></td>
        <td><?= dateFR($pr['date'] ?? '') ?></td>
        <td><?= htmlspecialchars($pr['lieu']) ?></td>
        <td><?= htmlspecialchars($pr['club']) ?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php else: ?>
    <span style="color:#8b949e;">Aucune progression</span>
<?php endif; ?>
</div>


<!-- ======================================== -->
<h2>7. Lister les selections</h2>
<!-- ======================================== -->

<pre>
foreach ($data['selections'] as $s) {
    echo $s['type'] . " | " . $s['competition'] . " | " . $s['epreuve'] . "\n";
}
</pre>

<div class="label">Resultat :</div>
<div class="result">
<?php if ($data && !empty($data['selections'])): ?>
<div class="table-wrap"><table>
    <tr><th>Type</th><th>Date</th><th>Competition</th><th>Epreuve</th><th>Classement</th><th>Performance</th></tr>
    <?php foreach ($data['selections'] as $s): ?>
    <tr>
        <td><?= htmlspecialchars($s['type']) ?></td>
        <td><?= dateFR($s['date'] ?? '') ?></td>
        <td><?= htmlspecialchars($s['competition']) ?></td>
        <td><?= htmlspecialchars($s['epreuve']) ?></td>
        <td><?= $s['classement'] ?></td>
        <td><span class="badge perf"><?= htmlspecialchars($s['performance_brut'] ?: $s['performance']) ?></span></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php else: ?>
    <span style="color:#8b949e;">Aucune selection</span>
<?php endif; ?>
</div>


<!-- ======================================== -->
<h2>8. Lister les niveaux + performances</h2>
<!-- ======================================== -->

<pre>
foreach ($data['niveaux'] as $n) {
    echo $n['annee'] . " | " . $n['code_niveau'] . " | " . $n['points_niveau'] . " pts\n";
    foreach ($n['performances'] as $perf) {
        echo "   " . $perf['epreuve'] . " : " . $perf['performance_brut'] . "\n";
    }
}
</pre>

<div class="label">Resultat :</div>
<div class="result">
<?php if ($data && !empty($data['niveaux'])): ?>
    <?php foreach ($data['niveaux'] as $n): ?>
    <div style="margin-bottom:12px;padding:10px;background:#161b22;border-radius:6px;">
        <b><?= $n['annee'] ?></b> — <span class="badge perf"><?= htmlspecialchars($n['code_niveau']) ?></span>
        <?= $n['points_niveau'] ? $n['points_niveau'] . ' pts' : '' ?>
        | <?= htmlspecialchars($n['club']) ?>
        <?php if (!empty($n['performances'])): ?>
        <table style="margin-top:6px;">
            <tr><th>Epreuve</th><th>Performance</th><th>Code</th></tr>
            <?php foreach ($n['performances'] as $perf): ?>
            <tr>
                <td><?= htmlspecialchars($perf['epreuve']) ?></td>
                <td><span class="badge perf"><?= htmlspecialchars($perf['performance_brut'] ?: $perf['performance']) ?></span></td>
                <td><?= htmlspecialchars($perf['code_niveau']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <span style="color:#8b949e;">Aucun niveau</span>
<?php endif; ?>
</div>


<!-- ======================================== -->
<h2>9. Rechercher des athletes par nom</h2>
<!-- ======================================== -->

<pre>
$json = file_get_contents("<?= $BASE ?>/search.php?nom=dupont&sexe=M&limit=5");
$data = json_decode($json, true);

echo "Total : " . $data['total'] . " resultats\n";
foreach ($data['athletes'] as $a) {
    echo $a['nom_complet'] . " | " . $a['categorie'] . " | " . $a['nationalite'] . "\n";
}
</pre>

<div class="label">Resultat :</div>
<div class="result">
<?php
$json2 = @file_get_contents("$BASE/search.php?nom=dupont&sexe=M&limit=5");
if ($json2):
    $d2 = json_decode($json2, true);
    if ($d2 && ($d2['success'] ?? false)):
?>
    <b>Total : <?= $d2['total'] ?> resultats</b> (5 premiers affiches)<br><br>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>Nom</th><th>Naissance</th><th>Cat</th><th>Sexe</th><th>NAT</th></tr>
        <?php foreach ($d2['athletes'] as $a): ?>
        <tr>
            <td><?= $a['athlete_id'] ?></td>
            <td><b><?= htmlspecialchars($a['nom_complet']) ?></b></td>
            <td><?= dateFR($a['date_naissance'] ?? '-') ?></td>
            <td><?= $a['categorie'] ?></td>
            <td><?= $a['sexe'] ?></td>
            <td><?= $a['nationalite'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
<?php else: ?>
    <span class="error"><?= htmlspecialchars($d2['error'] ?? 'Erreur') ?></span>
<?php endif; else: ?>
    <span class="error">Serveur injoignable</span>
<?php endif; ?>
</div>


<!-- ======================================== -->
<h2>10. Statistiques globales</h2>
<!-- ======================================== -->

<pre>
$json = file_get_contents("<?= $BASE ?>/stats.php");
$data = json_decode($json, true);

echo "Athletes : " . $data['comptages']['athletes']['count'] . "\n";
echo "Clubs : " . $data['comptages']['clubs']['count'] . "\n";
echo "Records : " . $data['comptages']['athlete_records']['count'] . "\n";
</pre>

<div class="label">Resultat :</div>
<div class="result">
<?php
$json3 = @file_get_contents("$BASE/stats.php");
if ($json3):
    $d3 = json_decode($json3, true);
    if ($d3 && ($d3['success'] ?? false)):
        foreach ($d3['comptages'] as $key => $info):
?>
    <b><?= $info['label'] ?> :</b> <?= number_format($info['count'], 0, ',', ' ') ?><br>
<?php
        endforeach;
    endif;
else: ?>
    <span class="error">Serveur injoignable</span>
<?php endif; ?>
</div>


<!-- ======================================== -->
<h2>11. Lister les podiums</h2>
<!-- ======================================== -->

<pre>
foreach ($data['podiums'] as $p) {
    echo $p['annee'] . " | " . $p['place'] . " | " . $p['epreuve'] . " | " . $p['performance_brut'] . "\n";
}
</pre>

<div class="label">Resultat :</div>
<div class="result">
<?php if ($data && !empty($data['podiums'])): ?>
<div class="table-wrap"><table>
    <tr><th>Annee</th><th>Niveau</th><th>Place</th><th>Epreuve</th><th>Performance</th><th>Lieu</th></tr>
    <?php foreach ($data['podiums'] as $pd): ?>
    <tr>
        <td><?= $pd['annee'] ?></td>
        <td><?= htmlspecialchars($pd['niveau_competition']) ?></td>
        <td><?= htmlspecialchars($pd['place']) ?></td>
        <td><?= htmlspecialchars($pd['epreuve']) ?></td>
        <td><span class="badge perf"><?= htmlspecialchars($pd['performance_brut'] ?: $pd['performance']) ?></span></td>
        <td><?= htmlspecialchars($pd['lieu']) ?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php else: ?>
    <span style="color:#8b949e;">Aucun podium</span>
<?php endif; ?>
</div>


</body>
</html>
