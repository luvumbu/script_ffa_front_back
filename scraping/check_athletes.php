<?php
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once dirname(__DIR__) . '/core/db.php';

$srcDir = dirname(__DIR__) . '/src';
$batchSize = 5000;
$offset = 0;

// Compter le total
$res = $conn->query("SELECT COUNT(*) as c FROM athletes");
$total = (int) $res->fetch_assoc()['c'];

$absents = [];
$presents = 0;
$traites = 0;
$srcExists = is_dir($srcDir);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Vérification src/ — <?= number_format($total, 0, '', ' ') ?> athletes</title>
<link rel="stylesheet" href="../common.css">
<style>
.stat { min-width:140px; }
.progress-wrap { margin-bottom:20px; }
.log { height:300px; }
.log-line { padding:2px 0; border-bottom:1px solid #1e2a3a10; }
.log-line .num { color:#6366f1; }
.log-line .ok { color:#34d399; }
.log-line .ko { color:#f87171; }
.done { background:linear-gradient(135deg,#34d39915,#6366f115); border:1px solid #34d39930; }
.done h2 { color:#34d399; }
</style>
</head>
<body class="panel-body">

<h1>Vérification <span>src/</span></h1>
<div class="subtitle"><?= number_format($total, 0, '', ' ') ?> athletes à vérifier</div>

<div class="stats">
    <div class="stat" style="background:#60a5fa10;border:1px solid #60a5fa25;">
        <div class="val" style="color:#60a5fa;" id="s-total">0</div>
        <div class="label">Traités</div>
    </div>
    <div class="stat" style="background:#34d39910;border:1px solid #34d39925;">
        <div class="val" style="color:#34d399;" id="s-ok">0</div>
        <div class="label">Présents</div>
    </div>
    <div class="stat" style="background:#f8717110;border:1px solid #f8717125;">
        <div class="val" style="color:#f87171;" id="s-ko">0</div>
        <div class="label">Absents</div>
    </div>
    <div class="stat" style="background:#fbbf2410;border:1px solid #fbbf2425;">
        <div class="val" style="color:#fbbf24;" id="s-pct">0%</div>
        <div class="label">Progression</div>
    </div>
</div>

<div class="progress-wrap">
    <div class="progress-bar" id="pbar" style="width:0%"></div>
    <div class="progress-text" id="ptxt">0 / <?= number_format($total, 0, '', ' ') ?></div>
</div>

<div class="log" id="log"></div>

<?php ob_flush(); flush(); ?>

<?php if (!$srcExists): ?>
<div style="background:#f8717115;border:2px solid #f87171;border-radius:10px;padding:24px;text-align:center;margin-top:20px;">
    <div style="font-size:40px;margin-bottom:10px;">&#9888;</div>
    <h2 style="color:#f87171;font-size:20px;margin-bottom:10px;">Le dossier src/ n'existe pas</h2>
    <p style="color:#c9d1d9;font-size:14px;margin-bottom:6px;">Chemin attendu : <code style="background:#161b22;padding:4px 10px;border-radius:4px;color:#a78bfa;"><?= htmlspecialchars($srcDir) ?></code></p>
    <p style="color:#8b949e;font-size:13px;margin-top:12px;">Créez le dossier <strong>src/</strong> à la racine du projet et placez-y les fichiers PHP des athletes (ex: src/2688957.php) avant de relancer la vérification.</p>
</div>
<?php
    // Générer quand même le JSON avec tous les athletes comme absents
    $res2 = $conn->query("SELECT id_athlete, athlete_id_externe, nom_complet_athlete FROM athletes ORDER BY id_athlete ASC");
    $allAbsents = [];
    while ($r = $res2->fetch_assoc()) {
        $allAbsents[] = [
            'id_athlete'         => (int) $r['id_athlete'],
            'athlete_id_externe' => (int) $r['athlete_id_externe'],
            'nom_complet'        => $r['nom_complet_athlete'],
            'fichier_attendu'    => 'src/' . $r['athlete_id_externe'] . '.php',
        ];
    }
    $output = [
        'generated_at'   => date('Y-m-d H:i:s'),
        'erreur'         => 'Le dossier src/ n\'existe pas',
        'total_verifies' => 0,
        'total_presents' => 0,
        'total_absents'  => count($allAbsents),
        'absents'        => $allAbsents,
    ];
    file_put_contents(dirname(__DIR__) . '/absents.json', json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
?>
<p style="color:#8b949e;text-align:center;margin-top:12px;font-size:12px;">absents.json généré avec <?= number_format(count($allAbsents),0,'',' ') ?> athletes (tous absents car le dossier n'existe pas)</p>
</body></html>
<?php $conn->close(); exit; endif; ?>

<?php
while ($offset < $total) {
    $res = $conn->query("SELECT id_athlete, athlete_id_externe, nom_complet_athlete FROM athletes ORDER BY id_athlete ASC LIMIT $batchSize OFFSET $offset");

    while ($row = $res->fetch_assoc()) {
        $traites++;
        $id = $row['athlete_id_externe'];
        $file = $srcDir . '/' . $id . '.php';

        if (file_exists($file)) {
            $presents++;
        } else {
            $absents[] = [
                'id_athlete'         => (int) $row['id_athlete'],
                'athlete_id_externe' => (int) $id,
                'nom_complet'        => $row['nom_complet_athlete'],
                'fichier_attendu'    => "src/$id.php",
            ];
        }

        if (!file_exists($file)) {
            $nom = addslashes(htmlspecialchars($row['nom_complet_athlete']));
            echo "<script>
                var l=document.getElementById('log');
                l.innerHTML+='<div class=\"log-line\"><span class=\"ko\">ABSENT</span> — fichier : <span style=\"color:#a78bfa;font-weight:bold\">src/{$id}.php</span> — athlete : <span style=\"color:#fff\">{$nom}</span> (id: {$row['id_athlete']})</div>';
                l.scrollTop=l.scrollHeight;
            </script>\n";
            ob_flush();
            flush();
        }

        if ($traites % 500 === 0) {
            $pct = round(($traites / $total) * 100, 1);
            $nbAbsents = count($absents);
            echo "<script>
                document.getElementById('s-total').textContent='".number_format($traites,0,'',' ')."';
                document.getElementById('s-ok').textContent='".number_format($presents,0,'',' ')."';
                document.getElementById('s-ko').textContent='".number_format($nbAbsents,0,'',' ')."';
                document.getElementById('s-pct').textContent='{$pct}%';
                document.getElementById('pbar').style.width='{$pct}%';
                document.getElementById('ptxt').textContent='".number_format($traites,0,'',' ')." / ".number_format($total,0,'',' ')."';
            </script>\n";
            ob_flush();
            flush();
        }
    }

    $offset += $batchSize;
}

// Générer le fichier JSON
$output = [
    'generated_at'   => date('Y-m-d H:i:s'),
    'total_verifies' => $traites,
    'total_presents' => $presents,
    'total_absents'  => count($absents),
    'absents'        => $absents,
];
file_put_contents(dirname(__DIR__) . '/absents.json', json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

$nbAbsents = count($absents);
$pct = 100;
?>

<script>
document.getElementById('s-total').textContent='<?= number_format($traites,0,'',' ') ?>';
document.getElementById('s-ok').textContent='<?= number_format($presents,0,'',' ') ?>';
document.getElementById('s-ko').textContent='<?= number_format($nbAbsents,0,'',' ') ?>';
document.getElementById('s-pct').textContent='100%';
document.getElementById('pbar').style.width='100%';
document.getElementById('ptxt').textContent='<?= number_format($traites,0,'',' ') ?> / <?= number_format($total,0,'',' ') ?>';
var l=document.getElementById('log');
l.innerHTML+='<div class="log-line"><span class="ok">--- Terminé ---</span></div>';
l.scrollTop=l.scrollHeight;
</script>

<div class="done">
    <h2>Vérification terminée</h2>
    <p><?= number_format($traites,0,'',' ') ?> vérifiés — <?= number_format($presents,0,'',' ') ?> présents — <?= number_format($nbAbsents,0,'',' ') ?> absents</p>
    <p style="margin-top:8px;">Fichier généré : <span class="file">absents.json</span></p>
</div>

</body>
</html>
<?php $conn->close(); ?>
