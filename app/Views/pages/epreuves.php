<?php
/**
 * Vue : Liste des epreuves
 * Variables : $data, $p, $nomEpreuve, $baseUrl
 */
?>

<h1>Épreuves</h1>

<?php View::partial('epreuve-panel'); ?>

<div class="live-search">
    <span class="ls-icon">&#128269;</span>
    <input type="text" id="lsEpreuves" placeholder="Rechercher une épreuve..." autocomplete="off">
    <div class="ls-status" id="lsEpreuvesStatus"></div>
</div>
<div class="ls-results" id="lsEpreuvesResults" style="display:none;"></div>

<div id="epreuvesPaginated">
<?php if ($data && ($data['success'] ?? false)):
    $epChartData = array_slice($data['epreuves'], 0, 12);
    $epDoughnut = array_slice($data['epreuves'], 0, 6);
    $epReste = array_slice($data['epreuves'], 6);
    $resteTotal = 0;
    foreach ($epReste as $er) $resteTotal += $er['nb_athletes'];
?>
<p class="subtitle"><?= number_format($data['total'], 0, ',', ' ') ?> epreuves</p>

<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Athlètes avec record</th></tr></table>
<table class="bk-table">
    <?php foreach ($data['epreuves'] as $idx => $e): ?>
    <tr>
        <td><?= ($p - 1) * 50 + $idx + 1 ?></td>
        <td><b><a href="?page=recherche&epreuve=<?= urlencode($e['nom_epreuve']) ?>" style="color:#a29bfe;text-decoration:none;cursor:pointer;"><?= htmlspecialchars($e['nom_epreuve']) ?></a></b></td>
        <td><?= $e['nb_athletes'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Athlètes avec record</th></tr></table>
</div>

<?php if ($data['total_pages'] > 1): ?>
<div class="pager">
    <?php if ($p > 1): ?><a href="?page=epreuves&nom=<?= urlencode($nomEpreuve) ?>&p=<?= $p - 1 ?>">Precedent</a><?php endif; ?>
    <?php for ($i = max(1,$p-3); $i <= min($data['total_pages'],$p+3); $i++): ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?page=epreuves&nom=<?= urlencode($nomEpreuve) ?>&p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?page=epreuves&nom=<?= urlencode($nomEpreuve) ?>&p=<?= $p + 1 ?>">Suivant</a><?php endif; ?>
</div>
<?php endif; ?>

<!-- Graphiques epreuves -->
<div class="charts-row" style="margin:20px 0;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127939;</span> Top Épreuves (cette page)</h3><canvas id="epBarChart"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#128200;</span> Repartition</h3><canvas id="epDoughChart"></canvas></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('epBarChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($epChartData as $e) echo "'" . addslashes($e['nom_epreuve']) . "',"; ?>],
            datasets: [{ label: 'Athletes', data: [<?php foreach ($epChartData as $e) echo $e['nb_athletes'] . ','; ?>],
                backgroundColor: function(ctx) { var g = ctx.chart.ctx.createLinearGradient(0,0,ctx.chart.width,0); g.addColorStop(0,'#ec4899'); g.addColorStop(1,'#f59e0b'); return g; },
                borderRadius: 6, barThickness: 18 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
    });
    new Chart(document.getElementById('epDoughChart'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($epDoughnut as $e) echo "'" . addslashes($e['nom_epreuve']) . "',"; ?><?= $resteTotal > 0 ? "'Autres'," : '' ?>],
            datasets: [{ data: [<?php foreach ($epDoughnut as $e) echo $e['nb_athletes'] . ','; ?><?= $resteTotal > 0 ? $resteTotal . ',' : '' ?>],
                backgroundColor: ['#3b82f6','#ec4899','#10b981','#f59e0b','#8b5cf6','#06b6d4','#64748b'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, font: { size: 10 } } } } }
    });
});
</script>

<?php endif; ?>
</div>
