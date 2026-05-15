<?php
/**
 * Vue : Accueil (page d'accueil) — Stats globales, graphiques, tops
 *
 * Variables attendues :
 *   $data       — Reponse API stats.php (comptages, par_sexe, par_categorie)
 *   $detailData — Donnees cache stats_detail_30.json ou null
 *   $baseUrl    — URL de base du site
 */
?>

<h1>Base de Donnees Athletisme Francais — Athletes, Clubs, Records</h1>

<?php if ($data && ($data['success'] ?? false)): ?>

<!-- ======== STAT CARDS ======== -->
<div class="grid">
    <a href="?page=athletes" class="card-link"><div class="card">
        <div class="num"><?= number_format($data['comptages']['athletes']['count'], 0, ',', ' ') ?></div>
        <div class="label">Athl&egrave;tes</div>
    </div></a>
    <a href="?page=clubs" class="card-link"><div class="card accent-green">
        <div class="num"><?= number_format($data['comptages']['clubs']['count'], 0, ',', ' ') ?></div>
        <div class="label">Clubs</div>
    </div></a>
    <a href="?page=epreuves" class="card-link"><div class="card accent-purple">
        <div class="num"><?= number_format($data['comptages']['epreuves']['count'], 0, ',', ' ') ?></div>
        <div class="label">&Eacute;preuves</div>
    </div></a>
    <div class="card accent-amber">
        <div class="num"><?= number_format($data['comptages']['athlete_resultats']['count'], 0, ',', ' ') ?></div>
        <div class="label">R&eacute;sultats</div>
    </div>
    <div class="card accent-rose">
        <div class="num"><?= number_format($data['comptages']['athlete_records']['count'], 0, ',', ' ') ?></div>
        <div class="label">Records</div>
    </div>
    <a href="?page=villes" class="card-link"><div class="card accent-green">
        <div class="num"><?= number_format($data['comptages']['villes']['count'], 0, ',', ' ') ?></div>
        <div class="label">Villes</div>
    </div></a>
</div>

<!-- ======== TOP 30 ATHLETES ======== -->
<?php if (!empty($detailData['top_athletes'])): ?>
<div style="margin-top:24px;margin-bottom:24px;">
    <h2 style="margin:0 0 12px;"><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127939;</span> Top 30 Athl&#232;tes</h2>
    <div class="table-wrap">
    <table class="bk-table"><tr><th style="width:40px;">#</th><th>Athl&#232;te</th><th>Club</th><th>Sexe</th><th>Records</th><th>Podiums</th></tr></table>
    <table class="bk-table" id="top30AthBody">
    <?php foreach (array_slice($detailData['top_athletes'], 0, 30) as $idx => $a): ?>
        <tr>
            <td style="color:#5a6580;"><?= $idx + 1 ?></td>
            <td><a href="?page=profil&id=<?= $a['athlete_id'] ?>" style="color:#a29bfe;text-decoration:none;font-weight:600;"><?= htmlspecialchars($a['nom']) ?></a></td>
            <td style="color:#8b949e;font-size:12px;"><?= htmlspecialchars(rtrim($a['club'] ?? '', '* ')) ?></td>
            <td><span class="badge badge-<?= strtolower($a['sexe'] ?? '') ?>" style="font-size:11px;"><?= htmlspecialchars($a['sexe'] ?? '-') ?></span></td>
            <td><?= ($a['nb_records'] ?? 0) > 0 ? '<span class="badge badge-perf">' . $a['nb_records'] . '</span>' : '-' ?></td>
            <td><?= ($a['nb_podiums'] ?? 0) > 0 ? '<span style="color:#34d399;font-weight:600;">' . $a['nb_podiums'] . '</span>' : '-' ?></td>
        </tr>
    <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th style="width:40px;">#</th><th>Athl&#232;te</th><th>Club</th><th>Sexe</th><th>Records</th><th>Podiums</th></tr></table>
    </div>
</div>
<?php endif; ?>

<!-- ======== GRAPHIQUES LIGNE 1 : Sexe + Categories ======== -->
<div class="charts-row">
    <div class="chart-card">
        <h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">M/F</span> Repartition par sexe</h3>
        <canvas id="chartSexe"></canvas>
    </div>
    <div class="chart-card">
        <h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#128202;</span> Cat&eacute;gories</h3>
        <canvas id="chartCategories"></canvas>
    </div>
</div>

<!-- ======== GRAPHIQUES LIGNE 2 : Top Clubs + Top Epreuves (charges en AJAX) ======== -->
<div class="charts-row">
    <div class="chart-card">
        <h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#127963;</span> Top 10 Clubs</h3>
        <canvas id="chartClubs"></canvas>
    </div>
    <div class="chart-card">
        <h3><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127939;</span> Top 10 &Eacute;preuves</h3>
        <canvas id="chartEpreuves"></canvas>
    </div>
</div>

<!-- ======== PANNEAU DETAIL CLUB (Accueil) ======== -->
<?php View::partial('club-panel', ['suffix' => 'Accueil', 'defaultMsg' => '']); ?>

<!-- ======== TABLEAUX DETAILS (charges en AJAX) ======== -->

<!-- Top Clubs -->
<div id="accueilClubsWrap" style="margin-bottom:24px;">
    <h2>Top Clubs <span id="accueilClubsCount" style="font-size:13px;color:#5a6580;font-weight:normal;"></span></h2>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Club</th><th>Athl&egrave;tes</th><th>Records</th><th>M&eacute;dailles</th><th>Niveaux</th><th></th></tr></table>
    <table class="bk-table"><tbody id="topClubsBody"><tr><td colspan="7" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr></tbody></table>
    <table class="bk-table"><tr><th>#</th><th>Club</th><th>Athl&egrave;tes</th><th>Records</th><th>M&eacute;dailles</th><th>Niveaux</th><th></th></tr></table>
    </div>
    <div id="topClubsPag" style="display:flex;justify-content:center;gap:8px;margin-top:12px;"></div>
</div>

<!-- Athletes -->
<div id="accueilAthletesWrap" style="margin-bottom:24px;">
    <h2>Athl&egrave;tes <span id="accueilAthletesCount" style="font-size:13px;color:#5a6580;font-weight:normal;"></span></h2>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>Athl&egrave;te</th><th>Club</th><th>Cat</th><th>NAT</th><th>M&eacute;dailles</th><th>Podiums</th><th>S&eacute;l.</th><th>Records</th><th>Niveaux</th><th></th></tr></table>
    <table class="bk-table"><tbody id="topAthletesBody"><tr><td colspan="10" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr></tbody></table>
    <table class="bk-table"><tr><th>Athl&egrave;te</th><th>Club</th><th>Cat</th><th>NAT</th><th>M&eacute;dailles</th><th>Podiums</th><th>S&eacute;l.</th><th>Records</th><th>Niveaux</th><th></th></tr></table>
    </div>
    <div id="topAthletesPag" style="display:flex;justify-content:center;gap:8px;margin-top:12px;"></div>
</div>

<!-- Top Villes -->
<div id="accueilVillesWrap" style="margin-bottom:24px;">
    <h2>Top Villes <span id="accueilVillesCount" style="font-size:13px;color:#5a6580;font-weight:normal;"></span></h2>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Ville</th><th>R&eacute;sultats</th><th>Athl&egrave;tes</th><th>Niveaux</th></tr></table>
    <table class="bk-table"><tbody id="topVillesBody"><tr><td colspan="5" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr></tbody></table>
    <table class="bk-table"><tr><th>#</th><th>Ville</th><th>R&eacute;sultats</th><th>Athl&egrave;tes</th><th>Niveaux</th></tr></table>
    </div>
    <div id="topVillesPag" style="display:flex;justify-content:center;gap:8px;margin-top:12px;"></div>
</div>

<!-- Top Epreuves -->
<div id="accueilEpreuvesWrap" style="margin-bottom:24px;">
    <h2>Top &Eacute;preuves <span id="accueilEpreuvesCount" style="font-size:13px;color:#5a6580;font-weight:normal;"></span></h2>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>&Eacute;preuve</th><th>Records</th><th>Athl&egrave;tes</th><th>Niveaux</th></tr></table>
    <table class="bk-table"><tbody id="topEpreuvesBody"><tr><td colspan="5" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr></tbody></table>
    <table class="bk-table"><tr><th>#</th><th>&Eacute;preuve</th><th>Records</th><th>Athl&egrave;tes</th><th>Niveaux</th></tr></table>
    </div>
    <div id="topEpreuvesPag" style="display:flex;justify-content:center;gap:8px;margin-top:12px;"></div>
</div>

<!-- ======== SCRIPTS : accueil.js + data injection ======== -->
<script src="<?= $baseUrl ?>/public/assets/js/accueil.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    <?php if ($detailData): ?>
    // Cache disponible -> injection directe (0 HTTP)
    _buildAccueilTables(<?= json_encode($detailData, JSON_UNESCAPED_UNICODE) ?>);
    // Fallback data pour Top Consultés si search_tracking est vide
    window._topFallbackData = <?= json_encode([
        'top_athletes' => $detailData['top_athletes'] ?? [],
        'top_clubs'    => $detailData['top_clubs'] ?? [],
    ], JSON_UNESCAPED_UNICODE) ?>;
    <?php else: ?>
    // Pas de cache -> AJAX fallback (1er visiteur uniquement)
    fetch(BASE_API + '/stats.php?detail=1&top=30')
        .then(function(r) { return r.json(); })
        .then(function(d) { _buildAccueilTables(d); })
        .catch(function() {
            ['topClubsBody','topAthletesBody','topVillesBody','topEpreuvesBody'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#ef4444;padding:20px;">Erreur de chargement</td></tr>';
            });
        });
    <?php endif; ?>

    // --- Charts: Sexe (Doughnut) + Categories (Bar horizontal) ---
    <?php
    $catsVisibles = ['SE','CA','JU','ES','MI','BE','MA'];
    $catsFiltrees = array_intersect_key($data['par_categorie'], array_flip($catsVisibles));
    ?>
    _initAccueilCharts(
        [<?php foreach ($data['par_sexe'] as $s => $nb) echo "'" . ($s === 'M' ? 'Hommes' : ($s === 'F' ? 'Femmes' : $s)) . "',"; ?>],
        [<?php echo implode(',', array_values($data['par_sexe'])); ?>],
        [<?php foreach ($catsFiltrees as $cat => $nb) echo "'" . htmlspecialchars($cat) . "',"; ?>],
        [<?php echo implode(',', array_values($catsFiltrees)); ?>]
    );
});
</script>

<?php else: ?>
<div class="error">Impossible de contacter l'API. Verifiez que le serveur est en ligne.</div>
<?php endif; ?>
