<?php
/**
 * Vue : Liste des clubs
 * Variables : $data, $p, $nomClub, $baseUrl
 */
?>

<h1>Clubs</h1>

<div id="ignoredClubsPanel" class="ignored-panel" style="display:none;">
    <h3>&#128683; Clubs ignores <span id="ignoredClubsCount" style="color:#fca5a5;font-weight:400;"></span></h3>
    <div id="ignoredClubsList"></div>
</div>

<?php View::partial('club-panel', ['suffix' => '', 'defaultMsg' => 'Cliquez sur un club pour voir ses details']); ?>

<div class="live-search">
    <span class="ls-icon">&#128269;</span>
    <input type="text" id="lsClubs" placeholder="Rechercher un club..." autocomplete="off">
    <div class="ls-status" id="lsClubsStatus"></div>
</div>
<div class="ls-results" id="lsClubsResults" style="display:none;"></div>

<div id="clubsPaginated">
<?php if ($data && ($data['success'] ?? false)):
    // Top 10 clubs de la page pour le graphique
    $clubChartData = array_slice($data['clubs'], 0, 10);
?>
<p class="subtitle"><?= number_format($data['total'], 0, ',', ' ') ?> clubs</p>

<!-- Graphique clubs -->
<div class="charts-row" style="margin-bottom:20px;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#127963;</span> Top Clubs (cette page)</h3><canvas id="clubsChart"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#128197;</span> Periode d'activite</h3><canvas id="clubsPeriodChart"></canvas></div>
</div>
<script>
// Donnees clubs stockees, charts construits par rebuildClubCharts()
window._clubsPageRaw = [<?php foreach ($clubChartData as $c) {
    echo "{label:'" . addslashes(mb_substr($c['nom_club'], 0, 25)) . "',";
    echo "labelShort:'" . addslashes(mb_substr($c['nom_club'], 0, 20)) . "',";
    echo "fullName:'" . addslashes($c['nom_club']) . "',";
    echo "count:" . $c['nb_athletes'] . ",";
    echo "start:" . ($c['annee_debut'] ?: 2000) . ",";
    echo "end:" . ($c['annee_fin'] ?: 2025) . "},";
} ?>];
</script>

<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Club</th><th>Athlètes</th><th>Top niveaux</th><th></th><th></th><th></th></tr></table>
<table class="bk-table">
    <?php foreach ($data['clubs'] as $idx => $c): ?>
    <tr data-club-name="<?= htmlspecialchars($c['nom_club'], ENT_QUOTES) ?>">
        <td><?= ($p - 1) * 50 + $idx + 1 ?></td>
        <td><b><a href="?page=recherche&club=<?= urlencode($c['nom_club']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($c['nom_club']) ?></a></b></td>
        <td><?= $c['nb_athletes'] ?></td>
        <td><?php if (!empty($c['top_niveaux'])): foreach ($c['top_niveaux'] as $tn):
            $nc = $tn['niveau'][0] ?? '';
            if ($nc === 'N') { $bg = '#e11d4820'; $bc = '#e11d48'; $tc = '#fb7185'; }
            elseif ($nc === 'I') { $bg = '#c026d320'; $bc = '#c026d3'; $tc = '#e879f9'; }
            elseif ($nc === 'R') { $bg = '#0891b220'; $bc = '#0891b2'; $tc = '#22d3ee'; }
            else { $bg = '#f9731620'; $bc = '#f97316'; $tc = '#fb923c'; }
            ?><span style="display:inline-block;padding:2px 7px;border-radius:5px;font-size:10px;margin:1px;background:<?= $bg ?>;border:1px solid <?= $bc ?>40;color:<?= $tc ?>;"><?= htmlspecialchars($tn['niveau']) ?> <?= $tn['pct'] ?>%</span><?php endforeach; else: ?>-<?php endif; ?></td>
        <td><a href="?page=recherche&club=<?= urlencode($c['nom_club']) ?>">Voir athletes</a></td>
        <td><button class="btn-cmp-add btn-cmp-add-club" data-cmp-club="<?= $c['id_club'] ?>" data-name="<?= htmlspecialchars($c['nom_club'], ENT_QUOTES) ?>" onclick="toggleClubBasket(this,parseInt(this.dataset.cmpClub),this.dataset.name)">+</button></td>
        <td><button class="btn-cmp-ignore" data-ignore-club="<?= $c['id_club'] ?>" data-name="<?= htmlspecialchars($c['nom_club'], ENT_QUOTES) ?>" onclick="toggleIgnoreClub(this,parseInt(this.dataset.ignoreClub),this.dataset.name)" title="Ignorer ce club">&#8856;</button></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Club</th><th>Athlètes</th><th>Top niveaux</th><th></th><th></th><th></th></tr></table>
</div>

<?php if ($data['total_pages'] > 1): ?>
<div class="pager">
    <?php if ($p > 1): ?><a href="?page=clubs&nom=<?= urlencode($nomClub) ?>&p=<?= $p - 1 ?>">Precedent</a><?php endif; ?>
    <?php for ($i = max(1,$p-3); $i <= min($data['total_pages'],$p+3); $i++): ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?page=clubs&nom=<?= urlencode($nomClub) ?>&p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?page=clubs&nom=<?= urlencode($nomClub) ?>&p=<?= $p + 1 ?>">Suivant</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
</div>

<?php
    $openClub = $_GET['open'] ?? '';
    if ($openClub): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    openClubDetail(null, <?= json_encode($openClub, JSON_UNESCAPED_UNICODE) ?>);
});
</script>
<?php endif; ?>
