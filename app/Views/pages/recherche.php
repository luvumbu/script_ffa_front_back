<?php
/**
 * Vue : Recherche avancee multi-criteres
 *
 * Variables :
 *   $data         — API response from search.php (ou null si pas de filtres)
 *   $p            — page courante (int)
 *   $filters      — array des filtres actifs
 *   $nationalites — array de nationalites depuis BDD [{code_nationalite, nb}]
 *   $clubFilter   — nom du club si filtre club actif (string)
 *   $clubTitle    — titre pour le header club (string)
 *   $baseUrl      — base URL
 */
?>

<?php if ($clubFilter !== ''): ?>
<!-- ====== TITRE CLUB ====== -->
<h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <span style="font-size:20px;">&#127963;</span>
    <?= htmlspecialchars($clubFilter) ?>
    <?php
    $hasFilterBadges = !empty($filters['nationalite']) || !empty($filters['sexe']) || !empty($filters['categorie']);
    if ($hasFilterBadges):
    ?>
    <span style="font-size:14px;color:#64748b;font-weight:400;">
        &mdash;
        <?php
        $filtresLabels = [];
        if (!empty($filters['nationalite'])) $filtresLabels[] = strtoupper($filters['nationalite']);
        if (!empty($filters['sexe'])) $filtresLabels[] = ($filters['sexe'] === 'M' ? 'Hommes' : 'Femmes');
        if (!empty($filters['categorie'])) $filtresLabels[] = htmlspecialchars($filters['categorie']);
        echo implode(', ', $filtresLabels);
        ?>
    </span>
    <?php endif; ?>
</h1>
<?php else: ?>
<h1>Recherche</h1>
<?php endif; ?>

<!-- ====== LIVE SEARCH BAR ====== -->
<div class="live-search" id="lsRechercheWrap">
    <span class="ls-icon">&#128269;</span>
    <input type="text" id="lsRecherche" placeholder="<?= $clubFilter !== '' ? 'Rechercher un athlete dans ' . htmlspecialchars($clubFilter) . '...' : 'Recherche rapide par nom...' ?>" autocomplete="off">
    <?php if ($clubFilter !== ''): ?>
    <div style="margin-top:6px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#6c5ce715;border:1px solid #6c5ce740;border-radius:6px;font-size:11px;color:#a29bfe;">&#127963; <?= htmlspecialchars($clubFilter) ?></span>
        <span style="color:#3a4560;font-size:11px;">Tapez un nom pour chercher dans ce club</span>
    </div>
    <?php endif; ?>
    <div class="ls-status" id="lsRechercheStatus"></div>
</div>
<div class="ls-results" id="lsRechercheResults" style="display:none;"></div>

<!-- ====== _rchExtraParams : injection PHP -> JS (AVANT live-search.js) ====== -->
<script>
var _rchExtraParams = <?php
    $rchExtra = [];
    foreach (["club","sexe","categorie","nationalite","epreuve"] as $_k) {
        if (!empty($filters[$_k])) $rchExtra[] = $_k . "=" . urlencode($filters[$_k]);
    }
    echo json_encode(implode("&", $rchExtra));
?>;
</script>

<!-- ====== CLUB DETAIL PANEL (si filtre club actif) ====== -->
<?php if ($clubFilter !== ''): ?>
<?php View::partial('club-panel', ['suffix' => '', 'defaultMsg' => 'Chargement...']); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var clubUrl = BASE_API + '/club_stats.php?nom=<?= urlencode($clubFilter) ?>';
    <?php
    foreach (['nationalite','sexe','categorie'] as $_fk) {
        if (!empty($filters[$_fk])) echo "clubUrl += '&" . $_fk . "=' + encodeURIComponent(" . json_encode($filters[$_fk]) . ");\n    ";
    }
    ?>
    fetch(clubUrl)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            _fillClubPanel(data, '');
            document.getElementById('clubDetailPanel').classList.add('active');
        });
});
</script>
<?php endif; ?>

<!-- ====== FORMULAIRE RECHERCHE AVANCEE ====== -->
<div id="recherchePaginated">
<p class="subtitle" style="margin-top:10px;color:#484f58;font-size:12px;">Ou recherche avancee :</p>
<div class="search-box" style="margin-top:5px;">
    <form method="get" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
        <input type="hidden" name="page" value="recherche">
        <input type="text" name="nom" placeholder="Nom / prenom..." value="<?= htmlspecialchars($filters['nom'] ?? '') ?>" style="width:200px;">
        <input type="text" name="club" placeholder="Club..." value="<?= htmlspecialchars($filters['club'] ?? '') ?>" style="width:160px;">
        <input type="text" name="epreuve" placeholder="Epreuve..." value="<?= htmlspecialchars($filters['epreuve'] ?? '') ?>" style="width:130px;">
        <input type="text" name="ville" placeholder="Ville..." value="<?= htmlspecialchars($filters['ville'] ?? '') ?>" style="width:120px;">
        <input type="text" name="competition" placeholder="Competition..." value="<?= htmlspecialchars($filters['competition'] ?? '') ?>" style="width:140px;">
        <select name="sexe">
            <option value="">Sexe</option>
            <option value="M" <?= ($filters['sexe'] ?? '') === 'M' ? 'selected' : '' ?>>Homme</option>
            <option value="F" <?= ($filters['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Femme</option>
        </select>
        <select name="categorie">
            <option value="">Categorie</option>
            <?php foreach (['EA','PO','BE','MI','CA','JU','ES','SE','V1','V2','V3','V4'] as $c): ?>
            <option value="<?= $c ?>" <?= ($filters['categorie'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
        </select>
        <select name="nationalite" style="width:auto;">
            <option value="">Nationalite</option>
            <?php foreach ($nationalites as $nr): ?>
            <option value="<?= htmlspecialchars($nr['code_nationalite']) ?>" <?= ($filters['nationalite'] ?? '') === $nr['code_nationalite'] ? 'selected' : '' ?>><?= htmlspecialchars($nr['code_nationalite']) ?> (<?= number_format($nr['nb'], 0, ',', ' ') ?>)</option>
            <?php endforeach; ?>
        </select>
        <select name="medaille">
            <option value="">Medaille</option>
            <option value="or" <?= ($filters['medaille'] ?? '') === 'or' ? 'selected' : '' ?>>Or</option>
            <option value="argent" <?= ($filters['medaille'] ?? '') === 'argent' ? 'selected' : '' ?>>Argent</option>
            <option value="bronze" <?= ($filters['medaille'] ?? '') === 'bronze' ? 'selected' : '' ?>>Bronze</option>
        </select>
        <input type="text" name="annee" placeholder="Annee..." value="<?= htmlspecialchars($filters['annee'] ?? '') ?>" style="width:80px;">
        <input type="text" name="licence" placeholder="Licence..." value="<?= htmlspecialchars($filters['licence'] ?? '') ?>" style="width:110px;">
        <button type="submit" class="btn">Rechercher</button>
    </form>
</div>

<?php if ($data && ($data['success'] ?? false)):
    // Calculer stats pour graphiques
    $rchSexe = []; $rchCat = [];
    foreach ($data['athletes'] as $a) {
        $s = $a['sexe'] ?: 'Inconnu'; $rchSexe[$s] = ($rchSexe[$s] ?? 0) + 1;
        $c = $a['categorie'] ?: 'Autre'; $rchCat[$c] = ($rchCat[$c] ?? 0) + 1;
    }
?>

<!-- ====== SOUS-TITRE RESULTATS ====== -->
<p class="subtitle"><?= number_format($data['total'], 0, ',', ' ') ?> resultats — page <?= $data['page'] ?>/<?= $data['total_pages'] ?></p>

<!-- ====== GRAPHIQUES RECHERCHE ====== -->
<div class="charts-row" style="margin-bottom:20px;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">M/F</span> Sexe (resultats)</h3><canvas id="rchChartSexe"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">Cat</span> Categories (resultats)</h3><canvas id="rchChartCat"></canvas></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('rchChartSexe'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($rchSexe as $k => $v) echo "'" . ($k==='M'?'Hommes':($k==='F'?'Femmes':$k)) . "',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($rchSexe)) ?>], backgroundColor: ['#3b82f6','#ec4899','#8b5cf6','#64748b'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } } } }
    });
    new Chart(document.getElementById('rchChartCat'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($rchCat as $k => $v) echo "'$k',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($rchCat)) ?>], backgroundColor: '#34d399', borderRadius: 4, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
});
</script>

<!-- ====== TABLEAU RESULTATS (pattern 3-tables) ====== -->
<div class="table-wrap">
<?php $thRow = '<tr><th>#</th><th>Nom complet</th><th class="hide-mobile">Naissance</th><th>Cat</th><th class="hide-mobile">Sexe</th><th class="hide-mobile">NAT</th><th>Niveaux</th><th>Records (top 5)</th><th></th><th></th></tr>'; ?>
<table class="bk-table"><?= $thRow ?></table>
<table class="bk-table">
    <?php foreach ($data['athletes'] as $idx => $a): ?>
    <tr>
        <td><?= ($p - 1) * 50 + $idx + 1 ?></td>
        <td><b><a href="?page=profil&id=<?= $a['athlete_id'] ?>"><?= htmlspecialchars($a['nom_complet']) ?></a></b></td>
        <td class="hide-mobile"><?= substr($a['date_naissance'] ?? '-', 0, 4) ?: '-' ?></td>
        <td><a href="?page=recherche&categorie=<?= urlencode($a['categorie']) ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= $a['categorie'] ?></span></a></td>
        <td class="hide-mobile"><a href="?page=recherche&sexe=<?= urlencode($a['sexe']) ?>" style="text-decoration:none;"><span class="badge badge-<?= strtolower($a['sexe']) ?>"><?= $a['sexe'] ?></span></a></td>
        <td class="hide-mobile"><a href="?page=recherche&nationalite=<?= urlencode($a['nationalite']) ?>" style="color:#c9d1d9;text-decoration:none;"><?= $a['nationalite'] ?></a></td>
        <td><?= nivBadgeHtml(highestNiveau($a['niveaux'] ?? [])) ?></td>
        <td><?php if (!empty($a['top_records'])):
            foreach ($a['top_records'] as $rec):
                ?><div style="display:flex;align-items:center;gap:4px;margin:2px 0;font-size:11px;">
                    <a href="?page=recherche&epreuve=<?= urlencode($rec['epreuve']) ?>" style="color:#818cf8;white-space:nowrap;text-decoration:none;"><?= htmlspecialchars($rec['epreuve']) ?></a>
                    <span class="perf-val" style="font-size:11px;"><?= htmlspecialchars($rec['performance']) ?></span>
                    <?= nivBadgeHtml(highestNiveau($rec['niveaux'] ?? [])) ?>
                </div><?php
            endforeach;
        elseif (($a['nb_records'] ?? 0) > 0): ?>
            <span class="badge badge-perf"><?= $a['nb_records'] ?></span>
        <?php else: ?>-<?php endif; ?></td>
        <td><a href="?page=profil&id=<?= $a['athlete_id'] ?>&s=records">Profil</a></td>
        <td><button class="btn-cmp-add" data-cmp-ath="<?= $a['athlete_id'] ?>" data-name="<?= htmlspecialchars($a['nom_complet'], ENT_QUOTES) ?>" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">+</button></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><?= $thRow ?></table>
</div>

<!-- ====== PAGINATION ====== -->
<?php if ($data['total_pages'] > 1):
    // Construire les params URL de pagination (exclure page/limit API, garder page=recherche)
    $pagerParams = $filters;
    unset($pagerParams['page'], $pagerParams['limit']);
    $pagerParams['page'] = 'recherche';
?>
<div class="pager">
    <?php if ($p > 1): ?><a href="?<?= http_build_query(array_merge($pagerParams, ['p' => $p-1])) ?>">&#8592; Precedent</a><?php endif; ?>
    <?php for ($i = max(1,$p-3); $i <= min($data['total_pages'],$p+3); $i++): ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?<?= http_build_query(array_merge($pagerParams, ['p' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?<?= http_build_query(array_merge($pagerParams, ['p' => $p+1])) ?>">Suivant &#8594;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php elseif ($data): ?>
<div class="error"><?= htmlspecialchars($data['error'] ?? 'Erreur') ?></div>
<?php elseif (!empty($filters['nom']) || !empty($filters['club']) || !empty($filters['epreuve']) || !empty($filters['nationalite']) || !empty($filters['sexe']) || !empty($filters['categorie']) || !empty($filters['ville']) || !empty($filters['competition']) || !empty($filters['medaille']) || !empty($filters['annee']) || !empty($filters['licence']) || !empty($filters['nom1']) || !empty($filters['nom2'])): ?>
<div class="error">Serveur injoignable.</div>
<?php else: ?>
<p class="subtitle">Entrez au moins un critere et cliquez sur Rechercher.</p>
<?php endif; ?>
</div>
