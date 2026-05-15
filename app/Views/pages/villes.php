<?php
/**
 * Vue : Villes (Liste + Detail)
 *
 * Variables disponibles :
 *   $data      — API response (ville_stats.php ou villes.php)
 *   $p         — page courante (list mode)
 *   $mode      — 'list' ou 'detail'
 *   $openVille — nom de la ville (detail mode)
 *   $nomVille  — filtre recherche (list mode)
 *   $niv       — filtre niveaux (detail mode)
 *   $nat       — filtre nationalites (detail mode)
 *   $ans       — filtre annees (detail mode)
 *   $baseUrl   — base URL
 *
 * Fonctions globales attendues : highestNiveau(), nivBadgeHtml()
 */

// Helper niveaux inline
function villeNivStyle($code) {
    $nc = $code[0] ?? '';
    if ($nc === 'N') return ['#e11d4820','#e11d48','#fb7185'];
    if ($nc === 'I') return ['#c026d320','#c026d3','#e879f9'];
    if ($nc === 'R') return ['#0891b220','#0891b2','#22d3ee'];
    return ['#f9731620','#f97316','#fb923c'];
}
?>

<?php if ($mode === 'detail'): ?>
<?php
    $vp = max(1, (int)($_GET['vp'] ?? 1));
    $vniv = $niv;
    $vnat = $nat;
    $vans = $ans;
    $vd = $data;
    $vs = $_GET['s'] ?? 'all';
    // Base URL pour pagination (preserve tous les filtres)
    $vpBase = '?page=villes&open=' . urlencode($openVille);
    if ($vniv !== '') $vpBase .= '&niv=' . urlencode($vniv);
    if ($vnat !== '') $vpBase .= '&nat=' . urlencode($vnat);
    if ($vans !== '') $vpBase .= '&ans=' . urlencode($vans);
?>

<?php if ($vd && ($vd['success'] ?? false)): ?>

<div class="profil-header">
    <div>
        <div class="name"><?= htmlspecialchars($vd['ville']['nom_ville']) ?></div>
        <div class="meta">
            <b>Athlètes :</b> <?= number_format($vd['total_athletes'], 0, ',', ' ') ?> |
            <b>Résultats :</b> <?= number_format($vd['total_resultats'], 0, ',', ' ') ?>
            <?php if ($vd['annee_debut']): ?> | <b>Période :</b> <?= $vd['annee_debut'] ?> — <?= $vd['annee_fin'] ?: '...' ?><?php endif; ?>
            <br><a href="?page=villes" style="color:#8b949e;font-size:12px;">← Retour à la liste</a>
        </div>
    </div>
</div>

<?php
// ---- RESUME VILLE ----
$_vnom = htmlspecialchars($vd['ville']['nom_ville']);
$_vtotal = $vd['total_athletes'];
$_vres = $vd['total_resultats'];
$_vep = $vd['total_epreuves'];
$_vcl = $vd['total_clubs'];
$_vdeb = $vd['annee_debut'];
$_vfin = $vd['annee_fin'];
$_vsexe = $vd['par_sexe'] ?? [];
$_vcat = $vd['par_categorie'] ?? [];
$_vnatl = $vd['nationalites'] ?? [];
$_vniv = $vd['niveaux'] ?? [];
$_vtopEp = $vd['top_epreuves'] ?? [];
$_vtopCl = $vd['top_clubs'] ?? [];
$_vtopAth = $vd['top_athletes'] ?? [];
$_vannees = $vd['annees'] ?? [];
$_vselAns = $vd['selected_annees'] ?? [];
$_vselNiv = $vd['selected_niveaux'] ?? [];
$_vselNat = $vd['selected_nationalites'] ?? [];
$_hasFilter = !empty($_vselAns) || !empty($_vselNiv) || !empty($_vselNat);
$_vmed = $vd['medailles'] ?? ['or'=>0,'argent'=>0,'bronze'=>0];
$_vtotalMed = $vd['total_medailles'] ?? 0;
$_vmedDetail = $vd['medailles_detail'] ?? [];
$_vtopMedAth = $vd['top_medaille_athletes'] ?? [];
$_vpod = $vd['podiums'] ?? ['1er'=>0,'2e'=>0,'3e'=>0];
$_vtotalPod = $vd['total_podiums'] ?? 0;
$_vpodNiv = $vd['podium_niveaux'] ?? [];
$_vrec = $vd['records'] ?? [];
$_vtotalRec = $vd['total_records'] ?? 0;
$_vsel = $vd['selections'] ?? ['nb_selections'=>0,'nb_athletes'=>0,'nb_competitions'=>0];
$_vprog = $vd['progressions'] ?? ['nb_progressions'=>0,'nb_epreuves'=>0];
$_vresAnnee = $vd['resultats_par_annee'] ?? [];

$bio = [];

// P1 — Presentation generale
$p1 = "$_vnom est un site de compétitions d'athlétisme";
if ($_vdeb && $_vfin) {
    if ($_vdeb == $_vfin) $p1 .= " actif en $_vdeb";
    else $p1 .= " actif de $_vdeb à $_vfin, soit " . ($_vfin - $_vdeb + 1) . " saisons";
}
$p1 .= ". Au total, " . number_format($_vtotal, 0, ',', ' ') . " athlètes y ont été enregistrés pour " . number_format($_vres, 0, ',', ' ') . " résultats";
if ($_vep > 0) $p1 .= " répartis sur " . number_format($_vep, 0, ',', ' ') . " épreuves différentes";
$p1 .= ".";
$bio[] = $p1;

// P1b — Filtre actif
if ($_hasFilter) {
    $parts = [];
    if (!empty($_vselAns)) $parts[] = "année(s) : " . implode(', ', $_vselAns);
    if (!empty($_vselNiv)) $parts[] = "niveau(x) : " . implode(', ', $_vselNiv);
    if (!empty($_vselNat)) $parts[] = "nationalité(s) : " . implode(', ', $_vselNat);
    $bio[] = "Ce résumé est filtré par " . implode(' ; ', $parts) . ".";
}

// P2 — Repartition par sexe
if (!empty($_vsexe)) {
    $sparts = [];
    foreach ($_vsexe as $s => $c) {
        $label = $s === 'M' ? 'hommes' : ($s === 'F' ? 'femmes' : 'non renseigné');
        $pct = $_vtotal > 0 ? round($c / $_vtotal * 100) : 0;
        $sparts[] = number_format($c, 0, ',', ' ') . " $label ($pct%)";
    }
    $bio[] = "La répartition par sexe compte " . implode(' et ', $sparts) . ".";
}

// P3 — Categories
if (!empty($_vcat)) {
    $top3cat = array_slice($_vcat, 0, 3, true);
    $cparts = [];
    foreach ($top3cat as $cat => $c) {
        $cparts[] = "$cat (" . number_format($c, 0, ',', ' ') . ")";
    }
    $p3 = "Les catégories les plus représentées sont " . implode(', ', $cparts);
    if (count($_vcat) > 3) $p3 .= " parmi " . count($_vcat) . " catégories au total";
    $p3 .= ".";
    $bio[] = $p3;
}

// P4 — Nationalites
if (!empty($_vnatl)) {
    $top3nat = array_slice($_vnatl, 0, 3, true);
    $nparts = [];
    foreach ($top3nat as $nat => $c) {
        $nparts[] = "$nat (" . number_format($c, 0, ',', ' ') . " athlètes)";
    }
    $p4 = "En dehors des athlètes français, les nationalités étrangères les plus présentes sont " . implode(', ', $nparts);
    if (count($_vnatl) > 3) $p4 .= ", soit " . count($_vnatl) . " nationalités différentes au total";
    $p4 .= ".";
    $bio[] = $p4;
}

// P5 — Niveaux de competition
if (!empty($_vniv)) {
    $topNiv = array_slice($_vniv, 0, 3);
    $nparts = [];
    foreach ($topNiv as $niv) {
        $nparts[] = $niv['niveau'] . " (" . $niv['pct'] . "%, " . number_format($niv['count'], 0, ',', ' ') . " résultats)";
    }
    $p5 = "Les niveaux de compétition dominants sont " . implode(', ', $nparts);
    if (count($_vniv) > 3) $p5 .= " sur " . count($_vniv) . " niveaux différents";
    $p5 .= ".";
    // Identifier la famille dominante
    $famCount = ['D' => 0, 'R' => 0, 'N' => 0, 'I' => 0];
    foreach ($_vniv as $niv) {
        $f = $niv['niveau'][0] ?? '';
        if (isset($famCount[$f])) $famCount[$f] += $niv['count'];
    }
    arsort($famCount);
    $famLabels = ['D' => 'départemental', 'R' => 'régional', 'N' => 'national', 'I' => 'international'];
    $topFam = array_keys($famCount)[0];
    if ($famCount[$topFam] > 0 && isset($famLabels[$topFam])) {
        $famPct = $_vres > 0 ? round($famCount[$topFam] / array_sum(array_values($famCount)) * 100) : 0;
        $p5 .= " Le niveau " . $famLabels[$topFam] . " représente $famPct% de l'ensemble des résultats classés.";
    }
    $bio[] = $p5;
}

// P6 — Top epreuves
if (!empty($_vtopEp)) {
    $top3ep = array_slice($_vtopEp, 0, 3);
    $eparts = [];
    foreach ($top3ep as $e) {
        $eparts[] = $e['epreuve'] . " (" . number_format($e['nb_resultats'], 0, ',', ' ') . " résultats, " . $e['nb_athletes'] . " athlètes)";
    }
    $bio[] = "Les épreuves phares de $_vnom sont " . implode(', ', $eparts) . ".";
}

// P7 — Top clubs
if (!empty($_vtopCl)) {
    $top3cl = array_slice($_vtopCl, 0, 3);
    $clparts = [];
    foreach ($top3cl as $c) {
        $clparts[] = $c['club'] . " (" . $c['nb_athletes'] . " athlètes)";
    }
    $p7 = "Les clubs les plus actifs sur ce site sont " . implode(', ', $clparts);
    if ($_vcl > 3) $p7 .= " parmi " . number_format($_vcl, 0, ',', ' ') . " clubs au total";
    $p7 .= ".";
    $bio[] = $p7;
}

// P8 — Top athletes
if (!empty($_vtopAth)) {
    $top3ath = array_slice($_vtopAth, 0, 3);
    $aparts = [];
    foreach ($top3ath as $a) {
        $info = $a['nom_complet'];
        $details = [];
        if ($a['categorie']) $details[] = $a['categorie'];
        if ($a['sexe']) $details[] = ($a['sexe'] === 'M' ? 'H' : 'F');
        $details[] = $a['nb_resultats'] . " résultats";
        if ($a['best_place'] && $a['best_place'] <= 3) $details[] = "meilleure place : " . $a['best_place'] . ($a['best_place'] === 1 ? 'er' : 'e');
        $info .= " (" . implode(', ', $details) . ")";
        $aparts[] = $info;
    }
    $bio[] = "Les athlètes les plus actifs sont " . implode(' ; ', $aparts) . ".";
}

// P9 — Medailles
if ($_vtotalMed > 0) {
    $pMed = "Ce site a accueilli " . number_format($_vtotalMed, 0, ',', ' ') . " médaille" . ($_vtotalMed > 1 ? 's' : '');
    $medDet = [];
    if ($_vmed['or'] > 0) { $pctOr = round($_vmed['or'] / $_vtotalMed * 100); $medDet[] = $_vmed['or'] . " en or ($pctOr%)"; }
    if ($_vmed['argent'] > 0) { $pctAr = round($_vmed['argent'] / $_vtotalMed * 100); $medDet[] = $_vmed['argent'] . " en argent ($pctAr%)"; }
    if ($_vmed['bronze'] > 0) { $pctBr = round($_vmed['bronze'] / $_vtotalMed * 100); $medDet[] = $_vmed['bronze'] . " en bronze ($pctBr%)"; }
    if (!empty($medDet)) $pMed .= " (" . implode(', ', $medDet) . ")";
    $pMed .= ".";
    if (!empty($_vtopMedAth)) {
        $maParts = [];
        foreach (array_slice($_vtopMedAth, 0, 3) as $ma) {
            $maParts[] = $ma['athlete'] . " (" . $ma['total'] . " : " . $ma['or'] . "or/" . $ma['argent'] . "ar/" . $ma['bronze'] . "br)";
        }
        $pMed .= " Les plus médaillés : " . implode(' ; ', $maParts) . ".";
    }
    $bio[] = $pMed;
}

// P10 — Podiums
if ($_vtotalPod > 0) {
    $pPod = number_format($_vtotalPod, 0, ',', ' ') . " podium" . ($_vtotalPod > 1 ? 's' : '') . " enregistré" . ($_vtotalPod > 1 ? 's' : '');
    $podDet = [];
    if ($_vpod['1er'] > 0) { $pct1 = round($_vpod['1er'] / $_vtotalPod * 100); $podDet[] = $_vpod['1er'] . " première" . ($_vpod['1er'] > 1 ? 's' : '') . " place" . ($_vpod['1er'] > 1 ? 's' : '') . " ($pct1%)"; }
    if ($_vpod['2e'] > 0) { $pct2 = round($_vpod['2e'] / $_vtotalPod * 100); $podDet[] = $_vpod['2e'] . " deuxième" . ($_vpod['2e'] > 1 ? 's' : '') . " place" . ($_vpod['2e'] > 1 ? 's' : '') . " ($pct2%)"; }
    if ($_vpod['3e'] > 0) { $pct3 = round($_vpod['3e'] / $_vtotalPod * 100); $podDet[] = $_vpod['3e'] . " troisième" . ($_vpod['3e'] > 1 ? 's' : '') . " place" . ($_vpod['3e'] > 1 ? 's' : '') . " ($pct3%)"; }
    if (!empty($podDet)) $pPod .= " (" . implode(', ', $podDet) . ")";
    $pPod .= ".";
    if (!empty($_vpodNiv)) {
        $pnParts = array_map(function($n) { return $n['niveau'] . " (" . $n['count'] . ")"; }, array_slice($_vpodNiv, 0, 3));
        $pPod .= " Niveaux de compétition des podiums : " . implode(', ', $pnParts) . ".";
    }
    $bio[] = $pPod;
}

// P11 — Records
if ($_vtotalRec > 0) {
    $pRec = number_format($_vtotalRec, 0, ',', ' ') . " record" . ($_vtotalRec > 1 ? 's' : '') . " personnel" . ($_vtotalRec > 1 ? 's' : '') . " " . ($_vtotalRec > 1 ? 'ont été établis' : 'a été établi') . " sur ce site.";
    if (!empty($_vrec)) {
        $recEx = [];
        foreach (array_slice($_vrec, 0, 3) as $r) {
            $recEx[] = $r['performance'] . " au " . $r['epreuve'] . " par " . $r['athlete'];
        }
        $pRec .= " Meilleurs records : " . implode(' ; ', $recEx) . ".";
    }
    $bio[] = $pRec;
}

// P12 — Selections
if ($_vsel['nb_selections'] > 0) {
    $bio[] = number_format($_vsel['nb_athletes'], 0, ',', ' ') . " athlète" . ($_vsel['nb_athletes'] > 1 ? 's' : '') . " ayant concouru sur ce site " . ($_vsel['nb_athletes'] > 1 ? 'ont' : 'a') . " été sélectionné" . ($_vsel['nb_athletes'] > 1 ? 's' : '') . " en équipe nationale, pour " . number_format($_vsel['nb_selections'], 0, ',', ' ') . " sélection" . ($_vsel['nb_selections'] > 1 ? 's' : '') . " au total.";
}

// P13 — Progressions
if ($_vprog['nb_progressions'] > 0) {
    $bio[] = number_format($_vprog['nb_progressions'], 0, ',', ' ') . " progression" . ($_vprog['nb_progressions'] > 1 ? 's' : '') . " enregistrée" . ($_vprog['nb_progressions'] > 1 ? 's' : '') . " sur " . $_vprog['nb_epreuves'] . " épreuve" . ($_vprog['nb_epreuves'] > 1 ? 's' : '') . ".";
}

// P14 — Evolution par annee
if (!empty($_vresAnnee) && count($_vresAnnee) > 1) {
    $best = $_vresAnnee[0];
    foreach ($_vresAnnee as $ra) {
        if ($ra['nb_resultats'] > $best['nb_resultats']) $best = $ra;
    }
    $bio[] = "L'année la plus active est " . $best['annee'] . " avec " . number_format($best['nb_resultats'], 0, ',', ' ') . " résultats et " . number_format($best['nb_athletes'], 0, ',', ' ') . " athlètes.";
}

// P15 — Annees d'activite
if (!empty($_vannees)) {
    $nbAn = count($_vannees);
    $recent = $_vannees[0] ?? null;
    $old = end($_vannees);
    if ($nbAn > 1) {
        $bio[] = "Le site couvre $nbAn saisons, de $old à $recent. La saison la plus récente enregistrée est $recent.";
    } elseif ($nbAn === 1) {
        $bio[] = "Une seule saison est enregistrée pour ce site : $recent.";
    }
}

$bioText = implode("\n\n", $bio);
?>

<div class="chart-card" style="margin:16px 0;border-left:3px solid #6c5ce7;" id="villeBioCard">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#6c5ce720;color:#a29bfe;">&#128221;</span> Résumé</h3>
    <p id="villeBioText" style="color:#c8cfd8;line-height:1.8;font-size:14px;margin:0;white-space:pre-line;"><?= htmlspecialchars($bioText) ?></p>
    <button onclick="navigator.clipboard.writeText(document.getElementById('villeBioText').textContent).then(function(){alert('Résumé copié !')})" style="margin-top:12px;background:#253049;color:#a29bfe;border:1px solid #6c5ce740;border-radius:8px;padding:6px 14px;cursor:pointer;font-size:12px;">&#128203; Copier le texte</button>
</div>

<?php
    // Ordre logique des niveaux (du plus bas au plus haut)
    $nivOrdre = ['D8','D7','D6','D5','D4','D3','D2','D1','R6','R5','R4','R3','R2','R1','N4','N3','N2','N1','IE','IR'];
    $allNiveaux = $vd['niveaux'] ?? [];
    // Trier les niveaux selon l'ordre logique
    $nivMap = [];
    foreach ($allNiveaux as $niv) {
        $nivMap[$niv['niveau']] = $niv;
    }
    $nivOrdered = [];
    foreach ($nivOrdre as $code) {
        if (isset($nivMap[$code])) $nivOrdered[] = $nivMap[$code];
    }
    // Ajouter ceux qui ne sont pas dans l'ordre predefini
    foreach ($allNiveaux as $niv) {
        if (!in_array($niv['niveau'], $nivOrdre)) $nivOrdered[] = $niv;
    }
    // Niveaux selectionnes (tous par defaut)
    $selectedNiv = $vd['selected_niveaux'] ?? [];
    $noneSelected = in_array('_none', $selectedNiv);
    $allSelected = empty($selectedNiv) || ($noneSelected && count($selectedNiv) === 1);
    if ($noneSelected) { $selectedNiv = []; $allSelected = false; }
    $allNivCodes = array_map(function($n) { return $n['niveau']; }, $allNiveaux);
?>

<!-- Courbe de distribution des niveaux + selecteur -->
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#128200;</span> Distribution des niveaux</h3>
    <div style="margin-bottom:16px;">
        <canvas id="villeNivCurve" height="100"></canvas>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
        <button onclick="toggleAllNiv()" id="btnNivAll" style="padding:6px 16px;border-radius:8px;border:1px solid <?= $allSelected ? '#a78bfa' : '#30363d' ?>;background:<?= $allSelected ? 'linear-gradient(135deg,#7c3aed,#a78bfa)' : '#161b22' ?>;color:<?= $allSelected ? '#fff' : '#8b949e' ?>;cursor:pointer;font-size:13px;font-weight:600;">Tout</button>
        <?php $noneSelected = !$allSelected && empty($selectedNiv); ?>
        <button onclick="clearAllNiv()" id="btnNivNone" style="padding:6px 16px;border-radius:8px;border:1px solid <?= $noneSelected ? '#ef4444' : '#30363d' ?>;background:<?= $noneSelected ? '#ef444420' : '#161b22' ?>;color:<?= $noneSelected ? '#f87171' : '#8b949e' ?>;cursor:pointer;font-size:13px;font-weight:600;">Aucun</button>
        <?php foreach ($nivOrdered as $niv):
            $code = $niv['niveau'];
            [$bg,$bc,$tc] = villeNivStyle($code);
            $isActive = $allSelected || in_array($code, $selectedNiv);
        ?>
        <button class="niv-filter-btn" data-niv="<?= htmlspecialchars($code) ?>"
            onclick="toggleNiv('<?= htmlspecialchars($code) ?>')"
            style="padding:5px 12px;border-radius:8px;font-size:12px;cursor:pointer;transition:all .2s;
            border:1px solid <?= $isActive ? $bc : '#30363d' ?>;
            background:<?= $isActive ? $bg : '#161b22' ?>;
            color:<?= $isActive ? $tc : '#484f58' ?>;
            opacity:<?= $isActive ? '1' : '0.5' ?>;">
            <?= htmlspecialchars($code) ?> <span style="font-size:10px;opacity:.7;"><?= $niv['pct'] ?>%</span>
        </button>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function() {
    var allNivCodes = <?= json_encode(array_map(function($n) { return $n['niveau']; }, $nivOrdered)) ?>;
    var selectedNiv = <?= json_encode($allSelected ? $allNivCodes : $selectedNiv) ?>;
    var allSelected = <?= $allSelected ? 'true' : 'false' ?>;

    function buildUrl(nivList) {
        var base = '?page=villes&open=<?= urlencode($openVille) ?>';
        if (nivList.length > 0) {
            base += '&niv=' + encodeURIComponent(nivList.join(','));
        }
        <?php if ($vnat !== ''): ?>base += '&nat=<?= urlencode($vnat) ?>';<?php endif; ?>
        <?php if ($vans !== ''): ?>base += '&ans=<?= urlencode($vans) ?>';<?php endif; ?>
        return base;
    }

    window.toggleAllNiv = function() {
        window.location.href = buildUrl([]);
    };

    window.clearAllNiv = function() {
        window.location.href = buildUrl(['_none']);
    };

    window.toggleNiv = function(code) {
        var current = allSelected ? allNivCodes.slice() : selectedNiv.slice();
        var idx = current.indexOf(code);
        if (idx >= 0) {
            current.splice(idx, 1);
        } else {
            current.push(code);
        }
        if (current.length === 0) {
            window.location.href = buildUrl(['_none']);
        } else if (current.length === allNivCodes.length) {
            window.location.href = buildUrl([]);
        } else {
            window.location.href = buildUrl(current);
        }
    };

    // Courbe
    var nivData = <?= json_encode($nivOrdered) ?>;
    var nivColors = {};
    <?php foreach ($nivOrdered as $niv) { [$bg,$bc,$tc] = villeNivStyle($niv['niveau']); echo "nivColors['" . addslashes($niv['niveau']) . "']='$tc';"; } ?>

    var activeCodes = allSelected ? allNivCodes : selectedNiv;
    var labels = nivData.map(function(n) { return n.niveau; });
    var counts = nivData.map(function(n) { return n.count; });
    var pointBg = nivData.map(function(n) { return activeCodes.indexOf(n.niveau) >= 0 ? nivColors[n.niveau] : '#30363d'; });
    var pointR = nivData.map(function(n) { return activeCodes.indexOf(n.niveau) >= 0 ? 6 : 3; });

    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('villeNivCurve').getContext('2d');
        var gradient = ctx.createLinearGradient(0, 0, ctx.canvas.width, 0);
        gradient.addColorStop(0, '#fb923c');
        gradient.addColorStop(0.4, '#22d3ee');
        gradient.addColorStop(0.7, '#fb7185');
        gradient.addColorStop(1, '#e879f9');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    borderColor: gradient,
                    backgroundColor: function(context) {
                        var c = context.chart.ctx;
                        var g = c.createLinearGradient(0, 0, 0, context.chart.height);
                        g.addColorStop(0, 'rgba(139,92,246,0.3)');
                        g.addColorStop(1, 'rgba(139,92,246,0)');
                        return g;
                    },
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointBackgroundColor: pointBg,
                    pointBorderColor: pointBg,
                    pointRadius: pointR,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var n = nivData[ctx.dataIndex];
                                return n.niveau + ' : ' + n.count.toLocaleString('fr-FR') + ' (' + n.pct + '%)';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: '#1e2a3a' },
                        ticks: { font: { size: 10 }, color: function(context) {
                            var code = labels[context.index];
                            return activeCodes.indexOf(code) >= 0 ? (nivColors[code] || '#8b949e') : '#30363d';
                        }}
                    },
                    y: {
                        grid: { color: '#1e2a3a' },
                        ticks: { callback: function(v) { return v >= 1000 ? (v/1000).toFixed(0) + 'k' : v; } }
                    }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    });
})();
</script>

<?php // ---- GRAPHIQUES ----
if (true):
    $vSexe = $vd['par_sexe'] ?? [];
    $vCat = $vd['par_categorie'] ?? [];
    $vNat = array_slice($vd['nationalites'] ?? [], 0, 10, true);
    $vEp = array_slice($vd['top_epreuves'] ?? [], 0, 10);
?>
<div class="charts-row" style="margin:20px 0;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">M/F</span> Répartition par sexe</h3><canvas id="villeSexeChart"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">Cat</span> Catégories</h3><canvas id="villeCatChart"></canvas></div>
</div>
<div class="charts-row" style="margin:20px 0;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">NAT</span> Nationalités</h3><canvas id="villeNatChart"></canvas></div>
    <div class="chart-card"><h3><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127939;</span> Top Épreuves</h3><canvas id="villeEpChart"></canvas></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('villeSexeChart'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($vSexe as $k => $v) echo "'" . ($k==='M'?'Hommes':($k==='F'?'Femmes':$k)) . "',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($vSexe)) ?>], backgroundColor: ['#3b82f6','#ec4899','#64748b'], borderWidth: 0 }]
        },
        options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } } } }
    });
    new Chart(document.getElementById('villeCatChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($vCat as $k => $v) echo "'$k',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($vCat)) ?>], backgroundColor: '#34d399', borderRadius: 4, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
    new Chart(document.getElementById('villeNatChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($vNat as $k => $v) echo "'$k',"; ?>],
            datasets: [{ data: [<?= implode(',', array_values($vNat)) ?>], backgroundColor: '#a78bfa', borderRadius: 4, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
    });
    new Chart(document.getElementById('villeEpChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($vEp as $e) echo "'" . addslashes(mb_substr($e['epreuve'], 0, 20)) . "',"; ?>],
            datasets: [{ label: 'Résultats', data: [<?php foreach ($vEp as $e) echo $e['nb_resultats'] . ','; ?>],
                backgroundColor: function(ctx) { var g = ctx.chart.ctx.createLinearGradient(0,0,ctx.chart.width,0); g.addColorStop(0,'#ec4899'); g.addColorStop(1,'#f59e0b'); return g; },
                borderRadius: 6, barThickness: 16 }]
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
    });
});
</script>
<?php endif; ?>

<?php // ---- NIVEAUX ----
if (true):
    $niveaux = $vd['niveaux'] ?? [];
    if (!empty($niveaux)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#f9731620;color:#fb923c;">&#127942;</span> Niveaux de compétition</h3>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
        <?php foreach ($niveaux as $niv):
            [$bg,$bc,$tc] = villeNivStyle($niv['niveau']);
        ?>
        <span style="display:inline-block;padding:6px 14px;border-radius:8px;font-size:13px;background:<?= $bg ?>;border:1px solid <?= $bc ?>40;color:<?= $tc ?>;">
            <?= htmlspecialchars($niv['niveau']) ?> <b><?= $niv['pct'] ?>%</b> <span style="opacity:.6;">(<?= number_format($niv['count'], 0, ',', ' ') ?>)</span>
        </span>
        <?php endforeach; ?>
    </div>
    <div class="charts-row" style="margin:0;">
        <div class="chart-card" style="margin:0;"><canvas id="villeNivChart"></canvas></div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var nivColors = {<?php foreach ($niveaux as $niv) { [$bg,$bc,$tc] = villeNivStyle($niv['niveau']); echo "'" . addslashes($niv['niveau']) . "':'$tc',"; } ?>};
        new Chart(document.getElementById('villeNivChart'), {
            type: 'doughnut',
            data: {
                labels: [<?php foreach ($niveaux as $niv) echo "'" . addslashes($niv['niveau']) . "',"; ?>],
                datasets: [{ data: [<?php foreach ($niveaux as $niv) echo $niv['count'] . ','; ?>],
                    backgroundColor: [<?php foreach ($niveaux as $niv) { [$bg,$bc,$tc] = villeNivStyle($niv['niveau']); echo "'$tc',"; } ?>], borderWidth: 0 }]
            },
            options: { responsive: true, cutout: '50%', plugins: { legend: { position: 'right', labels: { padding: 8, usePointStyle: true, font: { size: 11 } } } } }
        });
    });
    </script>
</div>
<?php endif; endif; ?>

<?php // ---- ATHLETES ----
if (true):
    $athList = $vd['top_athletes'] ?? [];
    if (!empty($athList)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">&#127939;</span> Athlètes (<?= number_format($vd['total_athletes'], 0, ',', ' ') ?>)</h3>
    <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:14px;align-items:center;">
        <span style="font-size:12px;color:#8b949e;margin-right:4px;">Filtrer par niveau :</span>
        <?php
        $athNivBase = '?page=villes&open=' . urlencode($openVille);
        ?>
        <button onclick="window.location.href='<?= $athNivBase ?>'" style="padding:4px 12px;border-radius:7px;font-size:11px;cursor:pointer;border:1px solid <?= $allSelected ? '#a78bfa' : '#30363d' ?>;background:<?= $allSelected ? 'linear-gradient(135deg,#7c3aed,#a78bfa)' : '#161b22' ?>;color:<?= $allSelected ? '#fff' : '#8b949e' ?>;font-weight:600;">Tout</button>
        <button onclick="clearAllNiv()" style="padding:4px 12px;border-radius:7px;font-size:11px;cursor:pointer;border:1px solid <?= $noneSelected ? '#ef4444' : '#30363d' ?>;background:<?= $noneSelected ? '#ef444420' : '#161b22' ?>;color:<?= $noneSelected ? '#f87171' : '#8b949e' ?>;font-weight:600;">Aucun</button>
        <?php foreach ($nivOrdered as $niv):
            $nc = $niv['niveau'];
            [$bg,$bc,$tc] = villeNivStyle($nc);
            $isAct = $allSelected || in_array($nc, $selectedNiv);
        ?>
        <button onclick="toggleNiv('<?= htmlspecialchars($nc) ?>')" style="padding:4px 10px;border-radius:7px;font-size:11px;cursor:pointer;border:1px solid <?= $isAct ? $bc : '#30363d' ?>;background:<?= $isAct ? $bg : '#161b22' ?>;color:<?= $isAct ? $tc : '#484f58' ?>;opacity:<?= $isAct ? '1' : '0.5' ?>;">
            <?= htmlspecialchars($nc) ?>
        </button>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>Niveaux</th><th>Résultats</th><th>Meilleure place</th><th></th></tr></table>
    <table class="bk-table">
        <?php foreach ($athList as $idx => $a): ?>
        <tr>
            <td><?= ($vp - 1) * 30 + $idx + 1 ?></td>
            <td><b><a href="?page=profil&id=<?= $a['athlete_id'] ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($a['nom_complet']) ?></a></b></td>
            <td><a href="?page=recherche&categorie=<?= urlencode($a['categorie'] ?? '') ?>&ville=<?= urlencode($openVille) ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= htmlspecialchars($a['categorie'] ?? '') ?></span></a></td>
            <td><a href="?page=recherche&sexe=<?= urlencode($a['sexe'] ?? '') ?>&ville=<?= urlencode($openVille) ?>" style="text-decoration:none;"><span class="badge badge-<?= strtolower($a['sexe'] ?? '') ?>"><?= htmlspecialchars($a['sexe'] ?? '') ?></span></a></td>
            <td><?= nivBadgeHtml(highestNiveau($a['niveaux'] ?? [])) ?></td>
            <td><?= $a['nb_resultats'] ?></td>
            <td><?= $a['best_place'] ? $a['best_place'] . ($a['best_place'] === 1 ? 'er' : 'e') : '-' ?></td>
            <td><a href="?page=profil&id=<?= $a['athlete_id'] ?>">Profil</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>Niveaux</th><th>Résultats</th><th>Meilleure place</th><th></th></tr></table>
    </div>
    <?php if ($vd['pages_athletes'] > 1): ?>
    <div class="pager" style="margin-top:12px;">
        <?php if ($vp > 1): ?><a href="<?= $vpBase ?>&vp=<?= $vp - 1 ?>">Précédent</a><?php endif; ?>
        <?php for ($i = max(1,$vp-3); $i <= min($vd['pages_athletes'],$vp+3); $i++): ?>
            <?php if ($i == $vp): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="<?= $vpBase ?>&vp=<?= $i ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($vp < $vd['pages_athletes']): ?><a href="<?= $vpBase ?>&vp=<?= $vp + 1 ?>">Suivant</a><?php endif; ?>
        <span class="info">(<?= $vd['pages_athletes'] ?> pages)</span>
    </div>
    <?php endif; ?>
</div>
<?php endif; endif; ?>

<?php // ---- EPREUVES ----
if (true):
    $epList = $vd['top_epreuves'] ?? [];
    if (!empty($epList)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127941;</span> Épreuves (<?= number_format($vd['total_epreuves'], 0, ',', ' ') ?>)</h3>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Résultats</th><th>Athlètes</th><th>Top niveaux</th></tr></table>
    <table class="bk-table">
        <?php foreach ($epList as $idx => $e): ?>
        <tr>
            <td><?= ($vp - 1) * 30 + $idx + 1 ?></td>
            <td><b><a href="?page=recherche&epreuve=<?= urlencode($e['epreuve']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($e['epreuve']) ?></a></b></td>
            <td><?= $e['nb_resultats'] ?></td>
            <td><?= $e['nb_athletes'] ?></td>
            <td><?php if (!empty($e['top_niveaux'])): ?><?php foreach ($e['top_niveaux'] as $eniv):
                [$ebg,$ebc,$etc] = villeNivStyle($eniv['niveau']);
            ?><span style="display:inline-block;margin:1px 2px;padding:2px 8px;border-radius:6px;font-size:11px;background:<?= $ebg ?>;border:1px solid <?= $ebc ?>40;color:<?= $etc ?>;"><?= htmlspecialchars($eniv['niveau']) ?> <b><?= $eniv['pct'] ?>%</b></span><?php endforeach; ?><?php else: ?>-<?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Épreuve</th><th>Résultats</th><th>Athlètes</th><th>Top niveaux</th></tr></table>
    </div>
    <?php if ($vd['pages_epreuves'] > 1): ?>
    <div class="pager" style="margin-top:12px;">
        <?php if ($vp > 1): ?><a href="<?= $vpBase ?>&vp=<?= $vp - 1 ?>">Précédent</a><?php endif; ?>
        <?php for ($i = max(1,$vp-3); $i <= min($vd['pages_epreuves'],$vp+3); $i++): ?>
            <?php if ($i == $vp): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="<?= $vpBase ?>&vp=<?= $i ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($vp < $vd['pages_epreuves']): ?><a href="<?= $vpBase ?>&vp=<?= $vp + 1 ?>">Suivant</a><?php endif; ?>
        <span class="info">(<?= $vd['pages_epreuves'] ?> pages)</span>
    </div>
    <?php endif; ?>
</div>
<?php endif; endif; ?>

<?php // ---- CLUBS ----
if (true):
    $clList = $vd['top_clubs'] ?? [];
    if (!empty($clList)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#127963;</span> Clubs (<?= number_format($vd['total_clubs'], 0, ',', ' ') ?>)</h3>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Club</th><th>Athlètes</th><th>Top niveaux</th></tr></table>
    <table class="bk-table">
        <?php foreach ($clList as $idx => $c): ?>
        <tr>
            <td><?= ($vp - 1) * 30 + $idx + 1 ?></td>
            <td><b><a href="?page=clubs&open=<?= urlencode($c['club']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($c['club']) ?></a></b></td>
            <td><?= $c['nb_athletes'] ?></td>
            <td><?php if (!empty($c['top_niveaux'])): ?><?php foreach ($c['top_niveaux'] as $cniv):
                [$cbg,$cbc,$ctc] = villeNivStyle($cniv['niveau']);
            ?><span style="display:inline-block;margin:1px 2px;padding:2px 8px;border-radius:6px;font-size:11px;background:<?= $cbg ?>;border:1px solid <?= $cbc ?>40;color:<?= $ctc ?>;"><?= htmlspecialchars($cniv['niveau']) ?> <b><?= $cniv['pct'] ?>%</b></span><?php endforeach; ?><?php else: ?>-<?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Club</th><th>Athlètes</th><th>Top niveaux</th></tr></table>
    </div>
    <?php if ($vd['pages_clubs'] > 1): ?>
    <div class="pager" style="margin-top:12px;">
        <?php if ($vp > 1): ?><a href="<?= $vpBase ?>&vp=<?= $vp - 1 ?>">Précédent</a><?php endif; ?>
        <?php for ($i = max(1,$vp-3); $i <= min($vd['pages_clubs'],$vp+3); $i++): ?>
            <?php if ($i == $vp): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="<?= $vpBase ?>&vp=<?= $i ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($vp < $vd['pages_clubs']): ?><a href="<?= $vpBase ?>&vp=<?= $vp + 1 ?>">Suivant</a><?php endif; ?>
        <span class="info">(<?= $vd['pages_clubs'] ?> pages)</span>
    </div>
    <?php endif; ?>
</div>
<?php endif; endif; ?>

<?php // ---- MEDAILLES ----
if ($_vtotalMed > 0):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#127942;</span> Médailles (<?= number_format($_vtotalMed, 0, ',', ' ') ?>)</h3>
    <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#f59e0b10;border:1px solid #f59e0b30;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#fbbf24;"><?= $_vmed['or'] ?></div>
            <div style="font-size:12px;color:#8b949e;">Or<?php if ($_vtotalMed > 0): ?> (<?= round($_vmed['or'] / $_vtotalMed * 100) ?>%)<?php endif; ?></div>
        </div>
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#94a3b810;border:1px solid #94a3b830;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#94a3b8;"><?= $_vmed['argent'] ?></div>
            <div style="font-size:12px;color:#8b949e;">Argent<?php if ($_vtotalMed > 0): ?> (<?= round($_vmed['argent'] / $_vtotalMed * 100) ?>%)<?php endif; ?></div>
        </div>
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#b4540010;border:1px solid #b4540030;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#cd7f32;"><?= $_vmed['bronze'] ?></div>
            <div style="font-size:12px;color:#8b949e;">Bronze<?php if ($_vtotalMed > 0): ?> (<?= round($_vmed['bronze'] / $_vtotalMed * 100) ?>%)<?php endif; ?></div>
        </div>
    </div>
    <?php if (!empty($_vmedDetail)): ?>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>Type</th><th>Athlète</th><th>Épreuve</th><th>Compétition</th><th>Année</th></tr></table>
    <table class="bk-table">
        <?php foreach ($_vmedDetail as $md): ?>
        <tr>
            <td><span style="font-weight:600;color:<?= strtolower($md['type'])==='or'?'#fbbf24':(strtolower($md['type'])==='argent'?'#94a3b8':'#cd7f32') ?>;"><?= ucfirst(htmlspecialchars($md['type'])) ?></span></td>
            <td><b><a href="?page=profil&id=<?= $md['athlete_id'] ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($md['athlete']) ?></a></b></td>
            <td><?php if (!empty($md['epreuve'])): ?><a href="?page=recherche&epreuve=<?= urlencode($md['epreuve']) ?>&ville=<?= urlencode($openVille) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($md['epreuve']) ?></a><?php else: ?>-<?php endif; ?></td>
            <td><?php if (!empty($md['competition'])): ?><a href="?page=recherche&competition=<?= urlencode($md['competition']) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($md['competition']) ?></a><?php else: ?>-<?php endif; ?></td>
            <td><?= $md['annee'] ?? '-' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>Type</th><th>Athlète</th><th>Épreuve</th><th>Compétition</th><th>Année</th></tr></table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php // ---- PODIUMS ----
if ($_vtotalPod > 0):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#127941;</span> Podiums (<?= number_format($_vtotalPod, 0, ',', ' ') ?>)</h3>
    <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#fbbf2410;border:1px solid #fbbf2430;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#fbbf24;"><?= $_vpod['1er'] ?></div>
            <div style="font-size:12px;color:#8b949e;">1ère place<?php if ($_vtotalPod > 0): ?> (<?= round($_vpod['1er'] / $_vtotalPod * 100) ?>%)<?php endif; ?></div>
        </div>
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#94a3b810;border:1px solid #94a3b830;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#94a3b8;"><?= $_vpod['2e'] ?></div>
            <div style="font-size:12px;color:#8b949e;">2ème place<?php if ($_vtotalPod > 0): ?> (<?= round($_vpod['2e'] / $_vtotalPod * 100) ?>%)<?php endif; ?></div>
        </div>
        <div style="flex:1;min-width:120px;text-align:center;padding:16px;background:#cd7f3210;border:1px solid #cd7f3230;border-radius:12px;">
            <div style="font-size:28px;font-weight:700;color:#cd7f32;"><?= $_vpod['3e'] ?></div>
            <div style="font-size:12px;color:#8b949e;">3ème place<?php if ($_vtotalPod > 0): ?> (<?= round($_vpod['3e'] / $_vtotalPod * 100) ?>%)<?php endif; ?></div>
        </div>
    </div>
    <?php if (!empty($_vpodNiv)): ?>
    <div style="margin-top:8px;color:#8b949e;font-size:12px;">Niveaux de compétition :
        <?php foreach ($_vpodNiv as $pn):
            [$pnbg,$pnbc,$pntc] = villeNivStyle($pn['niveau'] ?? 'D');
        ?>
        <span style="display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;margin:2px;background:<?= $pnbg ?>;border:1px solid <?= $pnbc ?>40;color:<?= $pntc ?>;"><?= htmlspecialchars($pn['niveau']) ?> (<?= $pn['count'] ?>)</span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php // ---- RECORDS ----
if ($_vtotalRec > 0):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#ef444420;color:#f87171;">&#9201;</span> Records personnels (<?= number_format($_vtotalRec, 0, ',', ' ') ?>)</h3>
    <?php if (!empty($_vrec)): ?>
    <div class="table-wrap">
    <table class="bk-table"><tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>Épreuve</th><th>Performance</th><th>Niveaux</th><th>Date</th></tr></table>
    <table class="bk-table">
        <?php foreach ($_vrec as $ri => $r): ?>
        <tr>
            <td><?= $ri + 1 ?></td>
            <td><b><a href="?page=profil&id=<?= $r['athlete_id'] ?>&s=records" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($r['athlete']) ?></a></b></td>
            <td><a href="?page=recherche&categorie=<?= urlencode($r['categorie'] ?? '') ?>&ville=<?= urlencode($openVille) ?>" style="text-decoration:none;"><span class="badge badge-cat"><?= htmlspecialchars($r['categorie'] ?? '-') ?></span></a></td>
            <td><a href="?page=recherche&sexe=<?= urlencode($r['sexe'] ?? '') ?>&ville=<?= urlencode($openVille) ?>" style="text-decoration:none;"><span class="badge badge-<?= strtolower($r['sexe'] ?? '') ?>"><?= htmlspecialchars($r['sexe'] ?? '-') ?></span></a></td>
            <td><?php if (!empty($r['epreuve'])): ?><a href="?page=recherche&epreuve=<?= urlencode($r['epreuve']) ?>&ville=<?= urlencode($openVille) ?>" style="color:#a29bfe;text-decoration:none;"><?= htmlspecialchars($r['epreuve']) ?></a><?php else: ?>-<?php endif; ?></td>
            <td><span class="perf-val"><?= htmlspecialchars($r['performance'] ?? '-') ?></span></td>
            <td><?= nivBadgeHtml(highestNiveau($r['niveaux'] ?? [])) ?></td>
            <td style="font-size:12px;color:#8b949e;"><?= $r['date'] ? date('d/m/Y', strtotime($r['date'])) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table class="bk-table"><tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>Épreuve</th><th>Performance</th><th>Niveaux</th><th>Date</th></tr></table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php // ---- EVOLUTION PAR ANNEE ----
if (!empty($_vresAnnee) && count($_vresAnnee) > 1):
    $raReversed = array_reverse($_vresAnnee);
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#6366f120;color:#818cf8;">&#128200;</span> Évolution par année</h3>
    <canvas id="villeEvoChart" style="max-height:300px;"></canvas>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new Chart(document.getElementById('villeEvoChart'), {
            type: 'line',
            data: {
                labels: [<?php foreach ($raReversed as $ra) echo $ra['annee'] . ','; ?>],
                datasets: [
                    { label: 'Résultats', data: [<?php foreach ($raReversed as $ra) echo $ra['nb_resultats'] . ','; ?>], borderColor: '#6366f1', backgroundColor: '#6366f120', fill: true, tension: 0.3, pointRadius: 3 },
                    { label: 'Athlètes', data: [<?php foreach ($raReversed as $ra) echo $ra['nb_athletes'] . ','; ?>], borderColor: '#34d399', backgroundColor: '#34d39920', fill: true, tension: 0.3, pointRadius: 3 }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top', labels: { usePointStyle: true, font: { size: 11 }, color: '#8b949e' } } },
                scales: {
                    x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } },
                    y: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e', callback: function(v) { return v >= 1000 ? (v/1000).toFixed(0) + 'k' : v; } } }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    });
    </script>
</div>
<?php endif; ?>

<?php // ---- NATIONALITES (selecteur) ----
if (true):
    $natList = $vd['nationalites'] ?? [];
    $selNat = $vd['selected_nationalites'] ?? [];
    $allNatSelected = empty($selNat);
    $noNatSelected = !$allNatSelected && count($selNat) === 1 && $selNat[0] === '_none';
    if ($noNatSelected) { $selNat = []; $allNatSelected = false; }
    if (!empty($natList)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#127760;</span> Nationalités (<?= count($natList) ?>)</h3>
    <div style="display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
        <button onclick="natSelectAll()" style="padding:5px 14px;border-radius:7px;font-size:12px;cursor:pointer;border:1px solid <?= $allNatSelected ? '#fbbf24' : '#30363d' ?>;background:<?= $allNatSelected ? 'linear-gradient(135deg,#d97706,#fbbf24)' : '#161b22' ?>;color:<?= $allNatSelected ? '#fff' : '#8b949e' ?>;font-weight:600;">Tout</button>
        <button onclick="natSelectNone()" style="padding:5px 14px;border-radius:7px;font-size:12px;cursor:pointer;border:1px solid <?= $noNatSelected ? '#ef4444' : '#30363d' ?>;background:<?= $noNatSelected ? '#ef444420' : '#161b22' ?>;color:<?= $noNatSelected ? '#f87171' : '#8b949e' ?>;font-weight:600;">Aucun</button>
        <?php foreach ($natList as $nat => $cnt):
            $isNatActive = $allNatSelected || in_array($nat, $selNat);
        ?>
        <button onclick="toggleNat('<?= htmlspecialchars($nat) ?>')" style="padding:4px 10px;border-radius:7px;font-size:11px;cursor:pointer;
            border:1px solid <?= $isNatActive ? '#fbbf24' : '#30363d' ?>;
            background:<?= $isNatActive ? '#f59e0b20' : '#161b22' ?>;
            color:<?= $isNatActive ? '#fbbf24' : '#484f58' ?>;
            opacity:<?= $isNatActive ? '1' : '0.5' ?>;">
            <?= htmlspecialchars($nat) ?> <span style="font-size:10px;opacity:.7;"><?= $cnt ?></span>
        </button>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function() {
    var allNatCodes = <?= json_encode(array_keys($natList)) ?>;
    var selectedNat = <?= json_encode($allNatSelected ? array_keys($natList) : $selNat) ?>;
    var allNatSel = <?= $allNatSelected ? 'true' : 'false' ?>;

    function buildNatUrl(natList) {
        var base = '?page=villes&open=<?= urlencode($openVille) ?>';
        <?php if ($vniv !== ''): ?>base += '&niv=<?= urlencode($vniv) ?>';<?php endif; ?>
        <?php if ($vans !== ''): ?>base += '&ans=<?= urlencode($vans) ?>';<?php endif; ?>
        if (natList.length > 0 && natList.length < allNatCodes.length) {
            base += '&nat=' + encodeURIComponent(natList.join(','));
        }
        return base;
    }

    window.natSelectAll = function() { window.location.href = buildNatUrl([]); };
    window.natSelectNone = function() { window.location.href = buildNatUrl(['_none']); };

    window.toggleNat = function(code) {
        var current = allNatSel ? allNatCodes.slice() : selectedNat.slice();
        var idx = current.indexOf(code);
        if (idx >= 0) current.splice(idx, 1); else current.push(code);
        if (current.length === 0) {
            window.location.href = buildNatUrl(['_none']);
        } else if (current.length === allNatCodes.length) {
            window.location.href = buildNatUrl([]);
        } else {
            window.location.href = buildNatUrl(current);
        }
    };
})();
</script>
<?php endif; endif; ?>

<?php // ---- ANNEES (selecteur) ----
if (true):
    $annees = $vd['annees'] ?? [];
    $selAns = $vd['selected_annees'] ?? [];
    $allAnsSelected = empty($selAns);
    $noAnsSelected = !$allAnsSelected && count($selAns) === 1 && $selAns[0] === 0;
    if ($noAnsSelected) { $selAns = []; $allAnsSelected = false; }
    if (!empty($annees)):
?>
<div class="chart-card" style="margin:16px 0;">
    <h3 style="margin-top:0;"><span class="chart-icon" style="background:#06b6d420;color:#22d3ee;">&#128197;</span> Années d'activité (<?= count($annees) ?> saisons)</h3>
    <div style="display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
        <button onclick="ansSelectAll()" style="padding:5px 14px;border-radius:7px;font-size:12px;cursor:pointer;border:1px solid <?= $allAnsSelected ? '#22d3ee' : '#30363d' ?>;background:<?= $allAnsSelected ? 'linear-gradient(135deg,#0891b2,#22d3ee)' : '#161b22' ?>;color:<?= $allAnsSelected ? '#fff' : '#8b949e' ?>;font-weight:600;">Tout</button>
        <button onclick="ansSelectNone()" style="padding:5px 14px;border-radius:7px;font-size:12px;cursor:pointer;border:1px solid <?= $noAnsSelected ? '#ef4444' : '#30363d' ?>;background:<?= $noAnsSelected ? '#ef444420' : '#161b22' ?>;color:<?= $noAnsSelected ? '#f87171' : '#8b949e' ?>;font-weight:600;">Aucun</button>
        <?php foreach ($annees as $y):
            $isAnsActive = $allAnsSelected || in_array($y, $selAns);
        ?>
        <button onclick="toggleAns(<?= $y ?>)" style="padding:4px 10px;border-radius:7px;font-size:11px;cursor:pointer;
            border:1px solid <?= $isAnsActive ? '#22d3ee' : '#30363d' ?>;
            background:<?= $isAnsActive ? '#0891b220' : '#161b22' ?>;
            color:<?= $isAnsActive ? '#22d3ee' : '#484f58' ?>;
            opacity:<?= $isAnsActive ? '1' : '0.5' ?>;">
            <?= $y ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>
<script>
(function() {
    var allAnsCodes = <?= json_encode($annees) ?>;
    var selectedAns = <?= json_encode($allAnsSelected ? $annees : $selAns) ?>;
    var allAnsSel = <?= $allAnsSelected ? 'true' : 'false' ?>;

    function buildAnsUrl(ansList) {
        var base = '?page=villes&open=<?= urlencode($openVille) ?>';
        <?php if ($vniv !== ''): ?>base += '&niv=<?= urlencode($vniv) ?>';<?php endif; ?>
        <?php if ($vnat !== ''): ?>base += '&nat=<?= urlencode($vnat) ?>';<?php endif; ?>
        if (ansList.length > 0 && ansList.length < allAnsCodes.length) {
            base += '&ans=' + encodeURIComponent(ansList.join(','));
        }
        return base;
    }

    window.ansSelectAll = function() { window.location.href = buildAnsUrl([]); };
    window.ansSelectNone = function() { window.location.href = buildAnsUrl([0]); };

    window.toggleAns = function(year) {
        var current = allAnsSel ? allAnsCodes.slice() : selectedAns.slice();
        var idx = current.indexOf(year);
        if (idx >= 0) current.splice(idx, 1); else current.push(year);
        if (current.length === 0) {
            window.location.href = buildAnsUrl([0]);
        } else if (current.length === allAnsCodes.length) {
            window.location.href = buildAnsUrl([]);
        } else {
            window.location.href = buildAnsUrl(current);
        }
    };
})();
</script>
<!-- QR Code ville -->
<div class="qr-share">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?= urlencode('https://bokonzi.com/?page=villes&open=' . $openVille) ?>" alt="QR Code ville <?= htmlspecialchars($openVille) ?> — Bokonzi" width="120" height="120">
    <div class="qr-label">Scannez pour partager</div>
</div>
<?php endif; endif; ?>

<?php else: ?>
<div class="error">Ville non trouvée.</div>
<?php endif; ?>

<?php
// ========== MODE LISTE : ?page=villes ==========
else: ?>

<h1>Villes</h1>

<div class="live-search">
    <span class="ls-icon">&#128269;</span>
    <input type="text" id="lsVilles" placeholder="Rechercher une ville..." autocomplete="off">
    <div class="ls-status" id="lsVillesStatus"></div>
</div>
<div class="ls-results" id="lsVillesResults" style="display:none;"></div>

<div id="villesPaginated">
<?php if ($data && ($data['success'] ?? false)):
    $villeChartData = array_slice($data['villes'], 0, 10);
?>
<p class="subtitle"><?= number_format($data['total'], 0, ',', ' ') ?> villes</p>

<div class="charts-row" style="margin-bottom:20px;">
    <div class="chart-card"><h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#127961;</span> Top Villes (cette page)</h3><canvas id="villesChart"></canvas></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var vData = [<?php foreach ($villeChartData as $v) {
        echo "{label:'" . addslashes(mb_substr($v['nom_ville'], 0, 25)) . "',";
        echo "count:" . $v['nb_athletes'] . ",";
        echo "start:" . ($v['annee_debut'] ?: 2000) . ",";
        echo "end:" . ($v['annee_fin'] ?: 2025) . "},";
    } ?>];
    if (document.getElementById('villesChart')) {
        new Chart(document.getElementById('villesChart'), {
            type: 'bar',
            data: {
                labels: vData.map(v => v.label),
                datasets: [{ label: 'Athlètes', data: vData.map(v => v.count),
                    backgroundColor: function(ctx) { var g = ctx.chart.ctx.createLinearGradient(0,0,ctx.chart.width,0); g.addColorStop(0,'#10b981'); g.addColorStop(1,'#06b6d4'); return g; },
                    borderRadius: 6, barThickness: 18 }]
            },
            options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
        });
    }
});
</script>

<div class="table-wrap">
<table class="bk-table"><tr><th>#</th><th>Ville</th><th>Athlètes</th><th>Période</th><th>Top 3 niveaux</th></tr></table>
<table class="bk-table">
    <?php foreach ($data['villes'] as $idx => $v): ?>
    <tr>
        <td><?= ($p - 1) * 50 + $idx + 1 ?></td>
        <td><b><a href="?page=villes&open=<?= urlencode($v['nom_ville']) ?>" style="color:#a29bfe;text-decoration:none;cursor:pointer;"><?= htmlspecialchars($v['nom_ville']) ?></a></b></td>
        <td><?= $v['nb_athletes'] ?></td>
        <td><?= $v['annee_debut'] ? $v['annee_debut'] . ' - ' . ($v['annee_fin'] ?: '...') : '-' ?></td>
        <td><?php if (!empty($v['top_niveaux'])): ?><?php foreach ($v['top_niveaux'] as $niv): ?><?php
            $nc = $niv['niveau'][0] ?? '';
            if ($nc === 'N') { $bg = '#e11d4820'; $bc = '#e11d48'; $tc = '#fb7185'; }
            elseif ($nc === 'I') { $bg = '#c026d320'; $bc = '#c026d3'; $tc = '#e879f9'; }
            elseif ($nc === 'R') { $bg = '#0891b220'; $bc = '#0891b2'; $tc = '#22d3ee'; }
            else { $bg = '#f9731620'; $bc = '#f97316'; $tc = '#fb923c'; }
        ?><span style="display:inline-block;margin:1px 2px;padding:2px 8px;border-radius:6px;font-size:11px;background:<?= $bg ?>;border:1px solid <?= $bc ?>40;color:<?= $tc ?>;"><?= htmlspecialchars($niv['niveau']) ?> <b><?= $niv['pct'] ?>%</b></span><?php endforeach; ?><?php else: ?>-<?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>#</th><th>Ville</th><th>Athlètes</th><th>Période</th><th>Top 3 niveaux</th></tr></table>
</div>

<?php if ($data['total_pages'] > 1): ?>
<div class="pager">
    <?php if ($p > 1): ?><a href="?page=villes&nom=<?= urlencode($nomVille) ?>&p=<?= $p - 1 ?>">Précédent</a><?php endif; ?>
    <?php for ($i = max(1,$p-3); $i <= min($data['total_pages'],$p+3); $i++): ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?page=villes&nom=<?= urlencode($nomVille) ?>&p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?page=villes&nom=<?= urlencode($nomVille) ?>&p=<?= $p + 1 ?>">Suivant</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
<?php endif; ?>
