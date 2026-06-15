<?php
/**
 * athlete.php — Affiche les données JSON d'un athlète
 *
 * Usage :
 *   http://localhost/BK/athlete.php?id=26134
 *   http://localhost/BK/athlete.php?id=809035
 */
function dateFR($d) {
    if (!$d || $d === '-') return '-';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $d;
}

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    die("Usage : athlete.php?id=26134");
}

// Charger le JSON depuis src/{id}.php
$jsonUrl = "https://bokonzi.com/src/$id.php";
$json = @file_get_contents($jsonUrl);

if (!$json) {
    die("Athlete $id non trouve. Verifiez que src/$id.php existe.");
}

$data = json_decode($json, true);

if (!$data) {
    die("Erreur JSON pour l'athlete $id.");
}

$identite     = $data['identite']     ?? [];
$clubs        = $data['clubs']        ?? [];
$medailles    = $data['medailles']    ?? [];
$selections   = $data['selections']   ?? [];
$progressions = $data['progressions'] ?? [];
$records      = $data['records']      ?? [];
$podiums      = $data['podiums']      ?? [];
$resultats    = $data['resultats']    ?? [];
$niveaux      = $data['niveaux']      ?? [];

$nom = htmlspecialchars($identite['nom_complet'] ?? 'Inconnu');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-adsense-account" content="ca-pub-7899923856846249">
    <title><?= $nom ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #1a1a2e; color: #eee; padding: 20px; }
        h1 { color: #00d4ff; margin-bottom: 5px; }
        h2 { color: #00d4ff; margin: 25px 0 10px; border-bottom: 1px solid #333; padding-bottom: 5px; }
        .info { color: #aaa; margin-bottom: 20px; }
        .card { background: #16213e; border-radius: 8px; padding: 15px; margin-bottom: 10px; }
        .card span { color: #aaa; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th { background: #0f3460; color: #00d4ff; padding: 8px; text-align: left; }
        td { padding: 6px 8px; border-bottom: 1px solid #222; }
        tr:hover { background: #1a1a3e; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-right: 5px; }
        .or { background: #ffd700; color: #000; }
        .argent { background: #c0c0c0; color: #000; }
        .bronze { background: #cd7f32; color: #000; }
        .section-count { color: #666; font-size: 14px; }
        a { color: #00d4ff; text-decoration: none; }
    </style>
    <?php require_once __DIR__ . '/../core/theme.php'; bkRenderThemeHead(); ?>
</head>
<body>

<!-- IDENTITÉ -->
<h1><?= $nom ?></h1>
<div class="info">
    <?php if (!empty($identite['date_naissance'])): ?>
        Naissance : <?= dateFR($identite['date_naissance']) ?>
        <?php if (!empty($identite['lieu_naissance'])): ?> — <?= htmlspecialchars($identite['lieu_naissance']) ?><?php endif; ?>
        <br/>
    <?php endif; ?>
    <?php if (!empty($identite['categorie'])): ?>Categorie : <?= htmlspecialchars($identite['categorie']) ?> | <?php endif; ?>
    <?php if (!empty($identite['sexe'])): ?>Sexe : <?= htmlspecialchars($identite['sexe']) ?> | <?php endif; ?>
    <?php if (!empty($identite['nationalite'])): ?>Nationalite : <?= htmlspecialchars($identite['nationalite']) ?> | <?php endif; ?>
    <?php if (!empty($identite['taille_cm'])): ?>Taille : <?= $identite['taille_cm'] ?>cm | <?php endif; ?>
    <?php if (!empty($identite['poids_kg'])): ?>Poids : <?= $identite['poids_kg'] ?>kg | <?php endif; ?>
    <?php if (!empty($identite['licence'])): ?>Licence : <?= htmlspecialchars($identite['licence']) ?><?php endif; ?>
</div>

<!-- CLUBS -->
<?php if (!empty($clubs)): ?>
<h2>Clubs <span class="section-count">(<?= count($clubs) ?>)</span></h2>
<?php foreach ($clubs as $c): ?>
    <div class="card">
        <b><?= htmlspecialchars($c['nom_club']) ?></b>
        <span><?= ($c['annee_debut'] ?? '?') ?> → <?= ($c['annee_fin'] ?? '?') ?></span>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<!-- MÉDAILLES -->
<?php if (!empty($medailles)): ?>
<h2>Medailles <span class="section-count">(<?= count($medailles) ?>)</span></h2>
<table>
    <tr><th>Type</th><th>Annee</th><th>Epreuve</th><th>Competition</th><th>Lieu</th></tr>
    <?php foreach ($medailles as $m): ?>
    <tr>
        <td><span class="badge <?= $m['type'] ?>"><?= $m['type'] ?></span></td>
        <td><?= $m['annee'] ?></td>
        <td><?= htmlspecialchars($m['epreuve']) ?></td>
        <td><?= htmlspecialchars($m['competition']) ?></td>
        <td><?= htmlspecialchars($m['lieu'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- RECORDS -->
<?php if (!empty($records)): ?>
<h2>Records personnels <span class="section-count">(<?= count($records) ?>)</span></h2>
<table>
    <tr><th>Epreuve</th><th>Performance</th><th>Date</th><th>Lieu</th><th>Club</th></tr>
    <?php foreach ($records as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r['epreuve']) ?></td>
        <td><b><?= htmlspecialchars($r['performance_brut'] ?? $r['performance'] ?? '') ?></b></td>
        <td><?= dateFR($r['date'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['lieu'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['club'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- PROGRESSIONS -->
<?php if (!empty($progressions)): ?>
<h2>Progressions <span class="section-count">(<?= count($progressions) ?>)</span></h2>
<table>
    <tr><th>Epreuve</th><th>Annee</th><th>Performance</th><th>Vent</th><th>Date</th><th>Lieu</th></tr>
    <?php foreach ($progressions as $p): ?>
    <tr>
        <td><?= htmlspecialchars($p['epreuve']) ?></td>
        <td><?= $p['annee'] ?></td>
        <td><b><?= htmlspecialchars($p['performance_brut'] ?? $p['performance'] ?? '') ?></b></td>
        <td><?= htmlspecialchars($p['vent'] ?? '') ?></td>
        <td><?= dateFR($p['date'] ?? '') ?></td>
        <td><?= htmlspecialchars($p['lieu'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- PODIUMS -->
<?php if (!empty($podiums)): ?>
<h2>Podiums <span class="section-count">(<?= count($podiums) ?>)</span></h2>
<table>
    <tr><th>Annee</th><th>Niveau</th><th>Place</th><th>Epreuve</th><th>Performance</th><th>Lieu</th></tr>
    <?php foreach ($podiums as $p): ?>
    <tr>
        <td><?= $p['annee'] ?></td>
        <td><?= htmlspecialchars($p['niveau_competition'] ?? '') ?></td>
        <td><?= htmlspecialchars($p['place'] ?? '') ?></td>
        <td><?= htmlspecialchars($p['epreuve']) ?></td>
        <td><b><?= htmlspecialchars($p['performance_brut'] ?? $p['performance'] ?? '') ?></b></td>
        <td><?= htmlspecialchars($p['lieu'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- RÉSULTATS -->
<?php if (!empty($resultats)): ?>
<h2>Resultats <span class="section-count">(<?= count($resultats) ?>)</span></h2>
<table>
    <tr><th>Date</th><th>Epreuve</th><th>Perf</th><th>Vent</th><th>Tour</th><th>Place</th><th>Niveau</th><th>Lieu</th></tr>
    <?php foreach ($resultats as $r): ?>
    <tr>
        <td><?= dateFR($r['date'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['epreuve']) ?></td>
        <td><b><?= htmlspecialchars($r['performance_brut'] ?? $r['performance'] ?? '') ?></b></td>
        <td><?= htmlspecialchars($r['vent'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['tour'] ?? '') ?></td>
        <td><?= $r['place'] ?? '' ?></td>
        <td><?= htmlspecialchars($r['niveau'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['lieu'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- SÉLECTIONS -->
<?php if (!empty($selections)): ?>
<h2>Selections <span class="section-count">(<?= count($selections) ?>)</span></h2>
<table>
    <tr><th>Type</th><th>Date</th><th>Competition</th><th>Epreuve</th><th>Classement</th><th>Performance</th></tr>
    <?php foreach ($selections as $s): ?>
    <tr>
        <td><?= htmlspecialchars($s['type'] ?? '') ?></td>
        <td><?= dateFR($s['date'] ?? '') ?></td>
        <td><?= htmlspecialchars($s['competition'] ?? '') ?></td>
        <td><?= htmlspecialchars($s['epreuve'] ?? '') ?></td>
        <td><?= $s['classement'] ?? '' ?></td>
        <td><b><?= htmlspecialchars($s['performance_brut'] ?? $s['performance'] ?? '') ?></b></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- NIVEAUX -->
<?php if (!empty($niveaux)): ?>
<h2>Niveaux <span class="section-count">(<?= count($niveaux) ?>)</span></h2>
<?php foreach ($niveaux as $n): ?>
    <div class="card">
        <b><?= $n['annee'] ?></b> — <?= htmlspecialchars($n['code_niveau']) ?>
        (<?= $n['points_niveau'] ?? '?' ?> pts)
        <span>| <?= htmlspecialchars($n['club'] ?? '') ?></span>
        <?php if (!empty($n['performances'])): ?>
            <table style="margin-top:8px">
                <tr><th>Epreuve</th><th>Performance</th><th>Code</th></tr>
                <?php foreach ($n['performances'] as $perf): ?>
                <tr>
                    <td><?= htmlspecialchars($perf['epreuve']) ?></td>
                    <td><b><?= htmlspecialchars($perf['performance_brut'] ?? $perf['performance'] ?? '') ?></b></td>
                    <td><?= htmlspecialchars($perf['code_niveau'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<!-- JSON BRUT -->
<h2>JSON brut</h2>
<p><a href="../src/<?= $id ?>.php" target="_blank">Voir le JSON brut → src/<?= $id ?>.php</a></p>

</body>
</html>
