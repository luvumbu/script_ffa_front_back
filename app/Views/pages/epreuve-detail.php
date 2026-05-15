<?php
/**
 * Vue : Detail d'une epreuve (page dediee)
 *
 * Variables attendues :
 *   $data    — Reponse API epreuve_stats.php (stats completes de l'epreuve)
 *   $nom     — Nom de l'epreuve (depuis GET)
 *   $baseUrl — URL de base du site
 */

$epData = $data;
$epNom = $nom;
$epPage = max(1, (int)($_GET['ep'] ?? 1));
$epSection = $_GET['s'] ?? 'all';
?>

<?php if ($epNom === ''): ?>
<div class="error">Parametre nom requis</div>
<?php elseif (!$epData || !($epData['success'] ?? false)): ?>
<div class="error">Epreuve "<?= htmlspecialchars($epNom) ?>" non trouvee.</div>
<?php else: ?>

<h1><?= htmlspecialchars($epData['epreuve']) ?></h1>
<div class="info">
    <b><?= number_format($epData['total_athletes'], 0, ',', ' ') ?></b> athletes |
    <b><?= number_format($epData['total_records'], 0, ',', ' ') ?></b> records |
    <?php if ($epData['annee_debut']): ?><b>Periode :</b> <?= $epData['annee_debut'] ?> — <?= $epData['annee_fin'] ?: '...' ?> |<?php endif; ?>
    <b><?= $epData['total_medailles'] ?? 0 ?></b> medailles |
    <b><?= $epData['total_podiums'] ?? 0 ?></b> podiums
    <?php $epSel = $epData['selections'] ?? []; if (($epSel['nb_selections'] ?? 0) > 0): ?> | <b><?= $epSel['nb_selections'] ?></b> selections<?php endif; ?>
    <?php $epProg = $epData['progressions'] ?? []; if (($epProg['nb_progressions'] ?? 0) > 0): ?> | <b><?= $epProg['nb_progressions'] ?></b> progressions<?php endif; ?>
</div>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin:10px 0;">
    <a href="?page=recherche&epreuve=<?= urlencode($epNom) ?>" style="display:inline-block;padding:7px 18px;background:#6c5ce720;border:1px solid #6c5ce740;border-radius:6px;color:#a29bfe;font-size:13px;font-weight:600;text-decoration:none;">Recherche avancee &#8599;</a>
</div>

<!-- Onglets -->
<div class="tabs">
    <a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=all" class="<?= $epSection === 'all' ? 'active' : '' ?>">Tout</a>
    <a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=records" class="<?= $epSection === 'records' ? 'active' : '' ?>">Records<span class="count"><?= $epData['total_records'] ?></span></a>
    <a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=stats" class="<?= $epSection === 'stats' ? 'active' : '' ?>">Stats</a>
    <a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=resume" class="<?= $epSection === 'resume' ? 'active' : '' ?>">Resume</a>
</div>

<?php
// ---- RECORDS ----
if ($epSection === 'all' || $epSection === 'records'):
    $epRecs = $epData['records'] ?? [];
?>
<h2>Records <span class="section-count">(<?= $epData['total_records'] ?>)</span></h2>
<?php if (!empty($epRecs)): ?>
<?php $epRecTh = '<tr><th>#</th><th>Athlete</th><th>Performance</th><th>Date</th><th>Club</th><th>Cat</th><th>Sexe</th><th>NAT</th><th>Niveaux</th></tr>'; ?>
<div class="table-wrap">
<table class="bk-table"><?= $epRecTh ?></table>
<table class="bk-table">
    <?php foreach ($epRecs as $_i => $rec):
        $recRank = ($epPage - 1) * 50 + $_i + 1;
    ?>
    <tr>
        <td><?= $recRank ?></td>
        <td><a href="?page=profil&id=<?= $rec['athlete_id'] ?>" style="color:#a29bfe;"><?= htmlspecialchars($rec['athlete']) ?></a></td>
        <td><span class="badge badge-perf"><?= htmlspecialchars($rec['performance'] ?? '') ?></span></td>
        <td><?= $rec['date'] ? date('d/m/Y', strtotime($rec['date'])) : '-' ?></td>
        <td><?php if (!empty($rec['club'])): ?><a href="#" onclick="openClubDetail(null,<?= htmlspecialchars(json_encode($rec['club'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>);return false;" style="color:#a29bfe;cursor:pointer;"><?= htmlspecialchars(mb_substr($rec['club'], 0, 25)) ?></a><?php else: ?>-<?php endif; ?></td>
        <td><span style="padding:2px 8px;border-radius:4px;font-size:11px;background:#6c5ce720;color:#a29bfe;"><?= htmlspecialchars($rec['categorie'] ?? '') ?></span></td>
        <td><?= htmlspecialchars($rec['sexe'] ?? '') ?></td>
        <td><?= htmlspecialchars($rec['nationalite'] ?? '') ?></td>
        <td><?= nivBadgeHtml(highestNiveau($rec['niveaux'] ?? [])) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><?= $epRecTh ?></table>
</div>
<?php if ($epData['total_pages'] > 1): ?>
<div class="pager">
    <?php if ($epPage > 1): ?><a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=<?= $epSection ?>&ep=<?= $epPage - 1 ?>">&#8592; Precedent</a><?php endif; ?>
    <?php for ($i = max(1, $epPage - 4); $i <= min($epData['total_pages'], $epPage + 4); $i++): ?>
        <?php if ($i == $epPage): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=<?= $epSection ?>&ep=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($epPage < $epData['total_pages']): ?><a href="?page=epreuve&nom=<?= urlencode($epNom) ?>&s=<?= $epSection ?>&ep=<?= $epPage + 1 ?>">Suivant &#8594;</a><?php endif; ?>
    <span style="color:#666;margin-left:10px;">(<?= $epData['total_pages'] ?> pages)</span>
</div>
<?php endif; endif; endif; ?>

<?php
// ---- STATS ----
if ($epSection === 'all' || $epSection === 'stats'):
    $epMed = $epData['medailles'] ?? ['or'=>0,'argent'=>0,'bronze'=>0];
    $epPod = $epData['podiums'] ?? ['1er'=>0,'2e'=>0,'3e'=>0];
    $epMedDet = $epData['medailles_detail'] ?? [];
    $epNivRes = $epData['niveaux_resultats'] ?? [];
    $epTopClubs = $epData['top_clubs'] ?? [];
    $epTopVilles = $epData['top_villes'] ?? [];
    $epParAnnee = $epData['resultats_par_annee'] ?? [];
    $epParSexe = $epData['par_sexe'] ?? [];
    $epParCat = $epData['par_categorie'] ?? [];
    $epNats = $epData['nationalites'] ?? [];
?>
<h2>Statistiques</h2>

<!-- Repartition sexe + categorie -->
<div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:20px;">
<?php if (!empty($epParSexe)): ?>
<div class="chart-card" style="flex:1;min-width:220px;">
    <h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">&#9893;</span> Repartition par sexe</h3>
    <canvas id="epStatSexeChart"></canvas>
</div>
<?php endif; ?>
<?php if (!empty($epParCat)): ?>
<div class="chart-card" style="flex:1;min-width:220px;">
    <h3><span class="chart-icon" style="background:#6c5ce720;color:#a29bfe;">&#127941;</span> Par categorie</h3>
    <canvas id="epStatCatChart"></canvas>
</div>
<?php endif; ?>
<?php if (!empty($epNats)): ?>
<div class="chart-card" style="flex:1;min-width:220px;">
    <h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#127758;</span> Nationalites (<?= count($epNats) ?>)</h3>
    <canvas id="epStatNatChart"></canvas>
</div>
<?php endif; ?>
</div>

<!-- Medailles -->
<?php if (($epData['total_medailles'] ?? 0) > 0): $eTM = $epData['total_medailles']; ?>
<h3>Medailles (<?= $eTM ?>)</h3>
<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#fbbf24;font-weight:700;"><?= $epMed['or'] ?></div><div style="font-size:12px;color:#8b949e;">Or<?php if ($eTM > 0): ?> (<?= round($epMed['or']/$eTM*100) ?>%)<?php endif; ?></div></div>
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#94a3b8;font-weight:700;"><?= $epMed['argent'] ?></div><div style="font-size:12px;color:#8b949e;">Argent<?php if ($eTM > 0): ?> (<?= round($epMed['argent']/$eTM*100) ?>%)<?php endif; ?></div></div>
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#d97706;font-weight:700;"><?= $epMed['bronze'] ?></div><div style="font-size:12px;color:#8b949e;">Bronze<?php if ($eTM > 0): ?> (<?= round($epMed['bronze']/$eTM*100) ?>%)<?php endif; ?></div></div>
</div>
<?php if (!empty($epMedDet)): ?>
<div class="table-wrap">
<?php $epMedTh = '<tr><th>Type</th><th>Athlete</th><th>Competition</th><th>Lieu</th><th>Annee</th></tr>'; ?>
<table class="bk-table"><?= $epMedTh ?></table>
<table class="bk-table">
    <?php foreach ($epMedDet as $md): ?>
    <tr>
        <td><span class="badge badge-<?= $md['type'] ?>"><?= ucfirst($md['type']) ?></span></td>
        <td><a href="?page=profil&id=<?= $md['athlete_id'] ?>" style="color:#a29bfe;"><?= htmlspecialchars($md['athlete']) ?></a></td>
        <td><?= htmlspecialchars($md['competition'] ?? '') ?></td>
        <td><?= htmlspecialchars($md['lieu'] ?? '') ?></td>
        <td><?= $md['annee'] ?? '-' ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><?= $epMedTh ?></table>
</div>
<?php endif; endif; ?>

<!-- Podiums -->
<?php if (($epData['total_podiums'] ?? 0) > 0): $eTP = $epData['total_podiums']; ?>
<h3>Podiums (<?= $eTP ?>)</h3>
<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#fbbf24;font-weight:700;"><?= $epPod['1er'] ?></div><div style="font-size:12px;color:#8b949e;">1ere place<?php if ($eTP > 0): ?> (<?= round($epPod['1er']/$eTP*100) ?>%)<?php endif; ?></div></div>
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#94a3b8;font-weight:700;"><?= $epPod['2e'] ?></div><div style="font-size:12px;color:#8b949e;">2eme place<?php if ($eTP > 0): ?> (<?= round($epPod['2e']/$eTP*100) ?>%)<?php endif; ?></div></div>
    <div class="card" style="text-align:center;min-width:100px;"><div style="font-size:28px;color:#d97706;font-weight:700;"><?= $epPod['3e'] ?></div><div style="font-size:12px;color:#8b949e;">3eme place<?php if ($eTP > 0): ?> (<?= round($epPod['3e']/$eTP*100) ?>%)<?php endif; ?></div></div>
</div>
<?php endif; ?>

<!-- Selections -->
<?php if (($epSel['nb_selections'] ?? 0) > 0): ?>
<h3>Selections nationales</h3>
<div class="card"><b><?= $epSel['nb_selections'] ?></b> selections pour <b><?= $epSel['nb_athletes'] ?></b> athletes</div>
<?php endif; ?>

<!-- Progressions -->
<?php if (($epProg['nb_progressions'] ?? 0) > 0): ?>
<h3>Progressions</h3>
<div class="card"><b><?= $epProg['nb_progressions'] ?></b> progressions pour <b><?= $epProg['nb_athletes'] ?></b> athletes</div>
<?php endif; ?>

<!-- Top Clubs -->
<?php if (!empty($epTopClubs)): ?>
<h3>Top Clubs</h3>
<div class="table-wrap">
<?php $epClubTh = '<tr><th>#</th><th>Club</th><th>Athletes</th><th>Records</th></tr>'; ?>
<table class="bk-table"><?= $epClubTh ?></table>
<table class="bk-table">
    <?php foreach ($epTopClubs as $_ci => $tc): ?>
    <tr>
        <td><?= $_ci + 1 ?></td>
        <td><a href="#" onclick="openClubDetail(null,<?= htmlspecialchars(json_encode($tc['club'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>);return false;" style="color:#a29bfe;cursor:pointer;"><?= htmlspecialchars($tc['club']) ?></a></td>
        <td><?= $tc['nb_athletes'] ?></td>
        <td><?= $tc['nb_records'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><?= $epClubTh ?></table>
</div>
<?php endif; ?>

<!-- Top Villes -->
<?php if (!empty($epTopVilles)): ?>
<h3>Top Villes</h3>
<div class="table-wrap">
<?php $epVilleTh = '<tr><th>#</th><th>Ville</th><th>Records</th><th>Athletes</th></tr>'; ?>
<table class="bk-table"><?= $epVilleTh ?></table>
<table class="bk-table">
    <?php foreach ($epTopVilles as $_vi => $tv): ?>
    <tr>
        <td><?= $_vi + 1 ?></td>
        <td><a href="?page=villes&open=<?= urlencode($tv['ville']) ?>" style="color:#a29bfe;"><?= htmlspecialchars($tv['ville']) ?></a></td>
        <td><?= $tv['nb_records'] ?></td>
        <td><?= $tv['nb_athletes'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><?= $epVilleTh ?></table>
</div>
<?php endif; ?>

<!-- Niveaux de competition -->
<?php if (!empty($epNivRes)): ?>
<h3>Niveaux de competition</h3>
<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
<?php foreach ($epNivRes as $enr):
    $enp = ($enr['niveau'][0] ?? '');
    if ($enp === 'N') { $enbg='#e11d4820'; $enbc='#e11d48'; $entc='#fb7185'; }
    elseif ($enp === 'I') { $enbg='#c026d320'; $enbc='#c026d3'; $entc='#e879f9'; }
    elseif ($enp === 'R') { $enbg='#0891b220'; $enbc='#0891b2'; $entc='#22d3ee'; }
    else { $enbg='#f9731620'; $enbc='#f97316'; $entc='#fb923c'; }
?>
<span style="display:inline-block;padding:4px 12px;border-radius:6px;font-size:12px;background:<?= $enbg ?>;border:1px solid <?= $enbc ?>40;color:<?= $entc ?>;font-weight:600;"><?= htmlspecialchars($enr['niveau']) ?> <span style="color:#8b949e;font-weight:400;">(<?= $enr['count'] ?>)</span></span>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Evolution par annee -->
<?php if (!empty($epParAnnee)): ?>
<h3>Evolution par annee</h3>
<div class="chart-card" style="margin-bottom:16px;">
    <canvas id="epStatEvoChart"></canvas>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
<?php if (!empty($epParSexe)): ?>
    new Chart(document.getElementById('epStatSexeChart'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($epParSexe as $k => $v) echo "'" . ($k==='M'?'Hommes':($k==='F'?'Femmes':$k)) . "',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($epParSexe)) ?>], backgroundColor: ['#3b82f6','#ec4899','#64748b'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, font: { size: 11 } } } } }
    });
<?php endif; ?>
<?php if (!empty($epParCat)):
    $epCatLabels = array_keys($epParCat); $epCatValues = array_values($epParCat);
?>
    new Chart(document.getElementById('epStatCatChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($epCatLabels) ?>,
            datasets: [{ data: <?= json_encode($epCatValues) ?>, backgroundColor: '#6c5ce780', borderColor: '#6c5ce7', borderWidth: 1, borderRadius: 4 }]
        },
        options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
<?php endif; ?>
<?php if (!empty($epNats)):
    $epNatTop = array_slice($epNats, 0, 10, true);
?>
    new Chart(document.getElementById('epStatNatChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($epNatTop)) ?>,
            datasets: [{ data: <?= json_encode(array_values($epNatTop)) ?>, backgroundColor: ['#3b82f6','#ec4899','#10b981','#f59e0b','#8b5cf6','#ef4444','#06b6d4','#84cc16','#f97316','#6366f1'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, font: { size: 10 } } } } }
    });
<?php endif; ?>
<?php if (!empty($epParAnnee)):
    $epaYears = array_column($epParAnnee, 'annee');
    $epaRes = array_column($epParAnnee, 'nb_resultats');
    $epaAth = array_column($epParAnnee, 'nb_athletes');
?>
    new Chart(document.getElementById('epStatEvoChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_reverse($epaYears)) ?>,
            datasets: [
                { label: 'Resultats', data: <?= json_encode(array_reverse($epaRes)) ?>, borderColor: '#6c5ce7', backgroundColor: '#6c5ce730', fill: true, tension: 0.3, pointRadius: 4 },
                { label: 'Athletes', data: <?= json_encode(array_reverse($epaAth)) ?>, borderColor: '#10b981', backgroundColor: '#10b98130', fill: true, tension: 0.3, pointRadius: 4 }
            ]
        },
        options: { responsive: true, scales: { x: { grid: { color: '#1e2a3a' } }, y: { beginAtZero: true, grid: { color: '#1e2a3a' } } }, plugins: { legend: { labels: { usePointStyle: true, font: { size: 11 } } } } }
    });
<?php endif; ?>
});
</script>
<?php endif; // stats ?>

<?php
// ---- RESUME ----
if ($epSection === 'all' || $epSection === 'resume'):
    $d = $epData;
    $bio = [];
    // p1 Intro
    $bio[] = "L'epreuve " . $d['epreuve'] . " regroupe " . number_format($d['total_athletes'], 0, ',', ' ') . " athletes et " . number_format($d['total_records'], 0, ',', ' ') . " records personnels enregistres.";
    if ($d['annee_debut']) $bio[] = "Les donnees couvrent la periode " . $d['annee_debut'] . " a " . ($d['annee_fin'] ?: 'aujourd\'hui') . ".";
    // p2 Sexe
    if (!empty($d['par_sexe'])) {
        $parts = [];
        foreach ($d['par_sexe'] as $k => $v) { $parts[] = $v . " " . ($k === 'M' ? 'hommes' : ($k === 'F' ? 'femmes' : $k)); }
        $bio[] = "Repartition par sexe : " . implode(', ', $parts) . ".";
    }
    // p3 Categories
    if (!empty($d['par_categorie'])) {
        $topCats = array_slice($d['par_categorie'], 0, 5, true);
        $parts = [];
        foreach ($topCats as $k => $v) $parts[] = $k . " (" . $v . ")";
        $bio[] = "Categories principales : " . implode(', ', $parts) . ".";
    }
    // p4 Nationalites
    if (!empty($d['nationalites'])) {
        $bio[] = count($d['nationalites']) . " nationalites representees. Top : " . implode(', ', array_map(function($k, $v) { return "$k ($v)"; }, array_keys(array_slice($d['nationalites'], 0, 5, true)), array_slice(array_values($d['nationalites']), 0, 5))) . ".";
    }
    // p5 Medailles
    $tm = $d['total_medailles'] ?? 0;
    if ($tm > 0) {
        $em = $d['medailles'];
        $bio[] = "$tm medailles attribuees : " . $em['or'] . " en or, " . $em['argent'] . " en argent, " . $em['bronze'] . " en bronze.";
        if (!empty($d['medailles_detail'])) {
            $last = $d['medailles_detail'][0];
            $bio[] = "Derniere medaille : " . ucfirst($last['type']) . " pour " . $last['athlete'] . (!empty($last['competition']) ? " (" . $last['competition'] . ")" : "") . (!empty($last['annee']) ? " en " . $last['annee'] : "") . ".";
        }
    }
    // p6 Podiums
    $tp = $d['total_podiums'] ?? 0;
    if ($tp > 0) {
        $ep2 = $d['podiums'];
        $bio[] = "$tp podiums : " . $ep2['1er'] . " victoires, " . $ep2['2e'] . " deuxiemes places, " . $ep2['3e'] . " troisiemes places.";
    }
    // p7 Selections
    $es = $d['selections'] ?? [];
    if (($es['nb_selections'] ?? 0) > 0) {
        $bio[] = $es['nb_selections'] . " selections nationales pour " . $es['nb_athletes'] . " athletes.";
    }
    // p8 Top clubs
    if (!empty($d['top_clubs'])) {
        $tc3 = array_slice($d['top_clubs'], 0, 3);
        $parts = [];
        foreach ($tc3 as $tc) $parts[] = $tc['club'] . " (" . $tc['nb_athletes'] . " athletes, " . $tc['nb_records'] . " records)";
        $bio[] = "Clubs les plus representes : " . implode(' ; ', $parts) . ".";
    }
    // p9 Top villes
    if (!empty($d['top_villes'])) {
        $tv3 = array_slice($d['top_villes'], 0, 3);
        $parts = [];
        foreach ($tv3 as $tv) $parts[] = $tv['ville'] . " (" . $tv['nb_records'] . " records)";
        $bio[] = "Villes principales : " . implode(', ', $parts) . ".";
    }
    // p10 Niveaux
    if (!empty($d['niveaux_resultats'])) {
        $top3Niv = array_slice($d['niveaux_resultats'], 0, 3);
        $parts = [];
        foreach ($top3Niv as $tn) $parts[] = $tn['niveau'] . " (" . $tn['count'] . ")";
        $bio[] = "Niveaux de competition dominants : " . implode(', ', $parts) . ".";
    }
    // p11 Evolution
    if (!empty($d['resultats_par_annee'])) {
        $peak = $d['resultats_par_annee'][0];
        $bio[] = "Annee la plus active : " . $peak['annee'] . " avec " . $peak['nb_resultats'] . " resultats et " . $peak['nb_athletes'] . " athletes.";
    }
    // p12 Progressions
    $epr = $d['progressions'] ?? [];
    if (($epr['nb_progressions'] ?? 0) > 0) {
        $bio[] = $epr['nb_progressions'] . " progressions enregistrees pour " . $epr['nb_athletes'] . " athletes, temoignant d'une discipline avec un fort potentiel de developpement.";
    }
    $bioText = implode("\n\n", $bio);
?>
<h2>Resume</h2>
<div class="card" style="line-height:1.8;">
    <div id="epResumeText"><?= nl2br(htmlspecialchars($bioText)) ?></div>
    <button onclick="var t=document.getElementById('epResumeText').innerText;navigator.clipboard.writeText(t).then(function(){alert('Texte copie !');});" style="margin-top:12px;padding:6px 18px;background:#6c5ce730;border:1px solid #6c5ce7;border-radius:6px;color:#a29bfe;font-size:12px;cursor:pointer;">Copier le texte</button>
</div>
<?php endif; // resume ?>

<?php endif; // data found ?>
