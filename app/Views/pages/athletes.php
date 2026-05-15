<?php
/**
 * Vue : Liste paginée des athlètes
 * Variables : $data (API response), $p (page courante), $ordre (tri), $baseUrl
 */

// Badge niveau specifique a la page athletes (padding/radius legerement differents de nivBadgeHtml)
if (!function_exists('_athNivBadge')) {
function _athNivBadge($code) {
    $nc = $code[0] ?? '';
    if ($nc === 'N') { $bg='#e11d4820'; $bc='#e11d48'; $tc='#fb7185'; }
    elseif ($nc === 'I') { $bg='#c026d320'; $bc='#c026d3'; $tc='#e879f9'; }
    elseif ($nc === 'R') { $bg='#0891b220'; $bc='#0891b2'; $tc='#22d3ee'; }
    else { $bg='#f9731620'; $bc='#f97316'; $tc='#fb923c'; }
    return '<span style="display:inline-block;padding:2px 7px;border-radius:5px;font-size:10px;margin:1px;background:'.$bg.';border:1px solid '.$bc.'40;color:'.$tc.';">'.htmlspecialchars($code).'</span>';
}
}
?>

<h1>Athlètes</h1>

<div class="live-search">
    <span class="ls-icon">&#128269;</span>
    <input type="text" id="lsAthletes" placeholder="Rechercher un athlète par nom..." autocomplete="off">
    <div class="ls-status" id="lsAthletesStatus"></div>
</div>
<div class="ls-results" id="lsAthletesResults" style="display:none;"></div>

<div id="athletesPaginated">
<div style="margin:10px 0;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
    <span style="color:#8b949e;font-size:13px;">Trier par :</span>
    <a href="?page=athletes&ordre=random" class="btn <?= $ordre === 'random' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Random</a>
    <a href="?page=athletes&ordre=nom" class="btn <?= $ordre === 'nom' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Nom</a>
    <a href="?page=athletes&ordre=recent" class="btn <?= $ordre === 'recent' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Plus récents</a>
    <a href="?page=athletes&ordre=id" class="btn <?= $ordre === 'id' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">ID athle.fr</a>
    <a href="?page=athletes&ordre=medailles" class="btn <?= $ordre === 'medailles' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Médailles</a>
    <a href="?page=athletes&ordre=podiums" class="btn <?= $ordre === 'podiums' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Podiums</a>
    <a href="?page=athletes&ordre=selections" class="btn <?= $ordre === 'selections' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Sélections</a>
    <a href="?page=athletes&ordre=records" class="btn <?= $ordre === 'records' ? 'btn-blue' : '' ?>" style="font-size:12px;padding:5px 12px;">Records</a>
</div>

<?php if ($data && ($data['success'] ?? false)):
    // Stats de la page courante
    $athSexe = []; $athCat = []; $athNat = [];
    foreach ($data['athletes'] as $a) {
        $s = $a['sexe'] ?: 'Inconnu';
        $athSexe[$s] = ($athSexe[$s] ?? 0) + 1;
        $c = $a['categorie'] ?: 'Autre';
        $athCat[$c] = ($athCat[$c] ?? 0) + 1;
        $n = $a['nationalite'] ?: 'Autre';
        $athNat[$n] = ($athNat[$n] ?? 0) + 1;
    }
    arsort($athNat);
    $athNat = array_slice($athNat, 0, 8, true);
?>
<p class="subtitle"><?= number_format($data['total'], 0, ',', ' ') ?> athletes — page <?= $data['page'] ?>/<?= $data['total_pages'] ?></p>

<!-- Graphiques de la page -->
<div class="charts-row-3" style="margin-bottom:20px;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">M/F</span> Sexe (page)</h3><canvas id="athChartSexe"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">Cat</span> Categories (page)</h3><canvas id="athChartCat"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">NAT</span> Nationalités (page)</h3><canvas id="athChartNat"></canvas></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('athChartSexe'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($athSexe as $k => $v) echo "'" . ($k==='M'?'Hommes':($k==='F'?'Femmes':$k)) . "',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($athSexe)) ?>], backgroundColor: ['#3b82f6','#ec4899','#8b5cf6','#64748b'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } } } }
    });
    new Chart(document.getElementById('athChartCat'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($athCat as $k => $v) echo "'$k',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($athCat)) ?>], backgroundColor: '#34d399', borderRadius: 4, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
    new Chart(document.getElementById('athChartNat'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($athNat as $k => $v) echo "'$k',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($athNat)) ?>], backgroundColor: '#a78bfa', borderRadius: 4, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
});
</script>

<?php
    // Calcul stats globales pour pourcentages
    $totalAthPage = count($data['athletes']);
    $sumMed = 0; $sumPod = 0; $sumSel = 0; $sumRec = 0; $sumRes = 0;
    $maxMed = 0; $maxPod = 0; $maxSel = 0; $maxRec = 0; $maxRes = 0;
    foreach ($data['athletes'] as $a) {
        $sumMed += $a['nb_medailles']; $sumPod += $a['nb_podiums']; $sumSel += $a['nb_selections'];
        $sumRec += $a['nb_records']; $sumRes += $a['nb_resultats'];
        if ($a['nb_medailles'] > $maxMed) $maxMed = $a['nb_medailles'];
        if ($a['nb_podiums'] > $maxPod) $maxPod = $a['nb_podiums'];
        if ($a['nb_selections'] > $maxSel) $maxSel = $a['nb_selections'];
        if ($a['nb_records'] > $maxRec) $maxRec = $a['nb_records'];
        if ($a['nb_resultats'] > $maxRes) $maxRes = $a['nb_resultats'];
    }
?>
<!-- Stats résumées de la page -->
<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
    <div style="flex:1;min-width:100px;text-align:center;padding:10px;background:#f59e0b10;border:1px solid #f59e0b30;border-radius:10px;">
        <div style="font-size:20px;font-weight:700;color:#fbbf24;"><?= number_format($sumMed, 0, ',', ' ') ?></div>
        <div style="font-size:11px;color:#8b949e;">Médailles</div>
    </div>
    <div style="flex:1;min-width:100px;text-align:center;padding:10px;background:#10b98110;border:1px solid #10b98130;border-radius:10px;">
        <div style="font-size:20px;font-weight:700;color:#34d399;"><?= number_format($sumPod, 0, ',', ' ') ?></div>
        <div style="font-size:11px;color:#8b949e;">Podiums</div>
    </div>
    <div style="flex:1;min-width:100px;text-align:center;padding:10px;background:#6366f110;border:1px solid #6366f130;border-radius:10px;">
        <div style="font-size:20px;font-weight:700;color:#818cf8;"><?= number_format($sumSel, 0, ',', ' ') ?></div>
        <div style="font-size:11px;color:#8b949e;">Sélections</div>
    </div>
    <div style="flex:1;min-width:100px;text-align:center;padding:10px;background:#8b5cf610;border:1px solid #8b5cf630;border-radius:10px;">
        <div style="font-size:20px;font-weight:700;color:#a78bfa;"><?= number_format($sumRec, 0, ',', ' ') ?></div>
        <div style="font-size:11px;color:#8b949e;">Records</div>
    </div>
    <div style="flex:1;min-width:100px;text-align:center;padding:10px;background:#06b6d410;border:1px solid #06b6d430;border-radius:10px;">
        <div style="font-size:20px;font-weight:700;color:#22d3ee;"><?= number_format($sumRes, 0, ',', ' ') ?></div>
        <div style="font-size:11px;color:#8b949e;">Résultats</div>
    </div>
</div>

<div class="table-wrap">
<?php $thAthFull = '<tr><th>#</th><th>Athlète</th><th>Club</th><th>Cat</th><th>Sexe</th><th>NAT</th><th>Niveaux</th><th>Médailles</th><th>Podiums</th><th>Sél.</th><th>Records</th><th class="hide-mobile">Résultats</th><th class="hide-mobile">Spécialité</th><th></th><th></th></tr>'; ?>
<table class="bk-table"><?= $thAthFull ?></table>
<table class="bk-table">
    <?php foreach ($data['athletes'] as $idx => $a):
        $med = $a['medailles'] ?? ['or'=>0,'argent'=>0,'bronze'=>0];
        $totalMedA = $med['or'] + $med['argent'] + $med['bronze'];
        $topEp = $a['top_epreuve'] ?? null;
    ?>
    <tr>
        <td><?= ($p - 1) * 50 + $idx + 1 ?></td>
        <td>
            <b><a href="?page=profil&id=<?= $a['athlete_id'] ?>"><?= htmlspecialchars($a['nom_complet']) ?></a></b>
            <?php if ($a['date_naissance']): ?>
                <br><span style="font-size:11px;color:#5a6580;"><?= substr($a['date_naissance'], 0, 4) ?><?php
                if ($a['taille_cm'] || $a['poids_kg']) {
                    echo ' · ';
                    if ($a['taille_cm']) echo $a['taille_cm'] . 'cm';
                    if ($a['taille_cm'] && $a['poids_kg']) echo '/';
                    if ($a['poids_kg']) echo $a['poids_kg'] . 'kg';
                }
                if ($a['max_points']) echo ' · ' . number_format($a['max_points'], 0, ',', ' ') . ' pts';
                ?></span>
            <?php endif; ?>
        </td>
        <td><?php if ($a['club']): ?><a href="?page=clubs&open=<?= urlencode($a['club']) ?>" style="color:#a29bfe;text-decoration:none;font-size:12px;"><?= htmlspecialchars(mb_substr($a['club'], 0, 25)) ?><?= mb_strlen($a['club']) > 25 ? '…' : '' ?></a><?php else: ?>-<?php endif; ?></td>
        <td><a href="?page=recherche&categorie=<?= urlencode($a['categorie']) ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= $a['categorie'] ?></span></a></td>
        <td><a href="?page=recherche&sexe=<?= urlencode($a['sexe']) ?>" style="text-decoration:none;"><span class="badge badge-<?= strtolower($a['sexe']) ?>"><?= $a['sexe'] ?></span></a></td>
        <td><?php if ($a['nationalite']): ?><a href="?page=recherche&nationalite=<?= urlencode($a['nationalite']) ?>" style="color:#c9d1d9;text-decoration:none;"><?= $a['nationalite'] ?></a><?php else: ?>-<?php endif; ?></td>
        <td><?php
            if ($a['meilleur_niveau']) {
                echo _athNivBadge($a['meilleur_niveau']);
                $restNiv = array_filter($a['niveaux'] ?? [], function($n) use ($a) { return $n !== $a['meilleur_niveau']; });
                if (count($restNiv) > 0) echo '<span style="color:#5a6580;font-size:10px;margin-left:2px;">+' . count($restNiv) . '</span>';
            } else { echo '-'; }
        ?></td>
        <td><?php if ($totalMedA > 0) {
            if ($med['or'] > 0) echo '<span style="color:#fbbf24;font-size:12px;font-weight:600;" title="Or">🥇' . $med['or'] . '</span> ';
            if ($med['argent'] > 0) echo '<span style="color:#94a3b8;font-size:12px;font-weight:600;" title="Argent">🥈' . $med['argent'] . '</span> ';
            if ($med['bronze'] > 0) echo '<span style="color:#cd7f32;font-size:12px;font-weight:600;" title="Bronze">🥉' . $med['bronze'] . '</span>';
        } else { echo '-'; } ?></td>
        <td><?php if ($a['nb_podiums'] > 0): ?>
            <span style="display:inline-block;padding:2px 8px;background:#10b98115;border:1px solid #10b98130;border-radius:5px;color:#34d399;font-size:12px;font-weight:600;"><?= $a['nb_podiums'] ?></span>
        <?php else: ?>-<?php endif; ?></td>
        <td><?php if ($a['nb_selections'] > 0): ?>
            <span style="display:inline-block;padding:2px 8px;background:#6366f115;border:1px solid #6366f130;border-radius:5px;color:#818cf8;font-size:12px;font-weight:600;"><?= $a['nb_selections'] ?></span>
        <?php else: ?>-<?php endif; ?></td>
        <td><?php if ($a['nb_records'] > 0): ?><a href="?page=profil&id=<?= $a['athlete_id'] ?>&s=records" style="text-decoration:none;"><span class="badge badge-perf"><?= $a['nb_records'] ?></span></a><?php else: ?>-<?php endif; ?></td>
        <td class="hide-mobile"><?php if ($a['nb_resultats'] > 0): ?>
            <span style="color:#22d3ee;font-size:12px;"><?= number_format($a['nb_resultats'], 0, ',', ' ') ?></span>
            <?php if ($a['nb_progressions'] > 0): ?><br><span style="color:#5a6580;font-size:10px;">↗ <?= $a['nb_progressions'] ?> prog.</span><?php endif; ?>
        <?php else: ?>-<?php endif; ?></td>
        <td class="hide-mobile"><?php if ($topEp): ?>
            <a href="?page=recherche&epreuve=<?= urlencode($topEp['epreuve']) ?>" style="color:#a29bfe;font-size:11px;text-decoration:none;"><?= htmlspecialchars(mb_substr($topEp['epreuve'], 0, 20)) ?></a>
            <?php if ($topEp['best']): ?><br><span style="color:#5a6580;font-size:10px;">RP: <?= htmlspecialchars($topEp['best']) ?></span><?php endif; ?>
        <?php else: ?>-<?php endif; ?></td>
        <td><a href="?page=profil&id=<?= $a['athlete_id'] ?>&s=records" style="font-size:12px;">Profil</a></td>
        <td><button class="btn-cmp-add" data-cmp-ath="<?= $a['athlete_id'] ?>" data-name="<?= htmlspecialchars($a['nom_complet'], ENT_QUOTES) ?>" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">+</button></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><?= $thAthFull ?></table>
</div>

<div class="pager">
    <?php if ($p > 1): ?><a href="?page=athletes&ordre=<?= $ordre ?>&p=<?= $p - 1 ?>">Precedent</a><?php endif; ?>
    <?php
    $start = max(1, $p - 3);
    $end = min($data['total_pages'], $p + 3);
    for ($i = $start; $i <= $end; $i++):
    ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?page=athletes&ordre=<?= $ordre ?>&p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?page=athletes&ordre=<?= $ordre ?>&p=<?= $p + 1 ?>">Suivant</a><?php endif; ?>
    <span class="info">(<?= $data['total_pages'] ?> pages)</span>
</div>

<?php else: ?>
<div class="error">Erreur de chargement.</div>
<?php endif; ?>
</div>
