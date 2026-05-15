/**
 * Epreuve Detail Panel — Extracted from index.php
 *
 * Main functions:
 *   openEpreuveDetail(nom) — opens the epreuve detail panel
 *   closeEpreuveDetail()   — closes it
 *   switchEpreuveTab(tab)  — switches tab
 *   _renderEpreuveTab(tab) — renders content for each of the 4 tabs (records, nationalites, stats, resume)
 *   loadEpreuveRecPage(page) — pagination for records
 *   _buildEpreuveResumeHTML(d) — auto-generated resume text
 *   copyEpreuveResume()    — copy resume to clipboard
 *
 * Dependencies: BASE_API, escapeHtml(), _nivBadge(), _highestNiveau(),
 *               dateFR(), isAthleteInBasket(), toggleAthleteBasket(),
 *               bkQR(), _trackSearch(), openClubDetail(), Chart.js
 *
 * Uses window._ctxEpreuveName to store the current epreuve name.
 */

// --- Epreuve Detail Panel (tabbed, like club) ---
var _epreuveDetailData = null;

function openEpreuveDetail(nom) {
    window._ctxEpreuveName = nom;
    var panel = document.getElementById('epreuveDetailPanel');
    var content = document.getElementById('epreuveDetailContent');
    if (!panel || !content) return;
    panel.classList.add('active');
    content.innerHTML = '<div class="loading-msg">Chargement...</div>';
    document.getElementById('epreuveDetailName').textContent = nom;
    document.getElementById('epreuveDetailMeta').textContent = '';
    panel.querySelectorAll('.club-detail-tab').forEach(function(t) { t.classList.remove('active'); });
    var first = panel.querySelector('.club-detail-tab[data-tab="records"]');
    if (first) first.classList.add('active');

    fetch(BASE_API + '/epreuve_stats.php?nom=' + encodeURIComponent(nom) + '&limit=50')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { content.innerHTML = '<div class="loading-msg">Épreuve non trouvée</div>'; return; }
            _epreuveDetailData = data;
            var meta = data.total_athletes + ' athlètes | ' + data.total_records + ' records';
            var natKeys = Object.keys(data.nationalites || {});
            if (natKeys.length > 0) meta += ' | ' + natKeys.length + ' nationalités';
            if (data.annee_debut) meta += ' | ' + data.annee_debut + '-' + (data.annee_fin || '...');
            var med = data.medailles || {};
            if ((med.or||0) + (med.argent||0) + (med.bronze||0) > 0) {
                meta += ' | ';
                if (med.or > 0) meta += '\uD83E\uDD47' + med.or + ' ';
                if (med.argent > 0) meta += '\uD83E\uDD48' + med.argent + ' ';
                if (med.bronze > 0) meta += '\uD83E\uDD49' + med.bronze;
            }
            document.getElementById('epreuveDetailMeta').textContent = meta;
            var eqr = document.getElementById('epreuveQR');
            if (eqr) eqr.innerHTML = bkQR('https://bokonzi.com/?page=epreuves&nom=' + encodeURIComponent(nom));
            _renderEpreuveTab('records');
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            // Track epreuve panel open
            _trackSearch({ q: nom, type: 'epreuve', source: 'panel_open', entity_name: nom, pg: 'epreuve_panel' });
        })
        .catch(function() { content.innerHTML = '<div class="loading-msg">Erreur de chargement</div>'; });
}
function closeEpreuveDetail() {
    var panel = document.getElementById('epreuveDetailPanel');
    if (panel) panel.classList.remove('active');
    _epreuveDetailData = null;
}
function switchEpreuveTab(tab) {
    var panel = document.getElementById('epreuveDetailPanel');
    if (panel) panel.querySelectorAll('.club-detail-tab').forEach(function(t) {
        t.classList.toggle('active', t.getAttribute('data-tab') === tab);
    });
    _renderEpreuveTab(tab);
}
function loadEpreuveRecPage(page) {
    if (!_epreuveDetailData) return;
    var content = document.getElementById('epreuveDetailContent');
    if (content) content.innerHTML = '<div class="loading-msg">Chargement page ' + page + '...</div>';
    fetch(BASE_API + '/epreuve_stats.php?nom=' + encodeURIComponent(_epreuveDetailData.epreuve) + '&page=' + page + '&limit=50')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            _epreuveDetailData.records = data.records;
            _epreuveDetailData.page = data.page;
            _epreuveDetailData.total_pages = data.total_pages;
            _renderEpreuveTab('records');
        });
}

function _renderEpreuveTab(tab) {
    var content = document.getElementById('epreuveDetailContent');
    var d = _epreuveDetailData;
    if (!content || !d) return;
    var html = '';

    if (tab === 'records') {
        var rec = d.records || [];
        var totalRec = d.total_records || rec.length;
        var pg = d.page || 1;
        var totalPages = d.total_pages || 1;
        if (rec.length === 0 && pg === 1) { content.innerHTML = '<div class="loading-msg">Aucun record trouvé</div>'; return; }

        html += '<div style="margin-bottom:12px;color:#5a6580;font-size:13px;">' + totalRec.toLocaleString('fr-FR') + ' records au total — Page ' + pg + '/' + totalPages + '</div>';
        var thEpRec = '<tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>NAT</th><th>Club</th><th>Performance</th><th>Niveaux</th><th>Date</th><th></th></tr>';
        html += '<div class="table-wrap">';
        html += '<table class="bk-table">' + thEpRec + '</table>';
        html += '<table class="bk-table">';
        rec.forEach(function(r, i) {
            var inB = r.athlete_id ? isAthleteInBasket(r.athlete_id) : false;
            html += '<tr>';
            html += '<td>' + ((pg - 1) * 50 + i + 1) + '</td>';
            html += '<td><b>' + (r.athlete_id ? '<a href="?page=profil&id=' + r.athlete_id + '&s=records" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(r.athlete) + '</a>' : escapeHtml(r.athlete)) + '</b></td>';
            var _epN = window._ctxEpreuveName || '';
            html += '<td><a href="?page=recherche&categorie=' + encodeURIComponent(r.categorie||'') + '&epreuve=' + encodeURIComponent(_epN) + '" style="text-decoration:none;"><span class="badge badge-cat">' + escapeHtml(r.categorie || '') + '</span></a></td>';
            html += '<td><a href="?page=recherche&sexe=' + encodeURIComponent(r.sexe||'') + '&epreuve=' + encodeURIComponent(_epN) + '" style="text-decoration:none;"><span class="badge badge-' + (r.sexe||'').toLowerCase() + '">' + escapeHtml(r.sexe || '') + '</span></a></td>';
            html += '<td><a href="?page=recherche&nationalite=' + encodeURIComponent(r.nationalite||'') + '&epreuve=' + encodeURIComponent(_epN) + '" style="color:#c9d1d9;text-decoration:none;">' + escapeHtml(r.nationalite || '') + '</a></td>';
            html += '<td>' + (r.club ? '<a href="?page=clubs&open=' + encodeURIComponent(r.club) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(r.club) + '</a>' : '-') + '</td>';
            html += '<td><span class="perf-val">' + escapeHtml(r.performance || '-') + '</span></td>';
            html += '<td>' + _nivBadge(_highestNiveau(r.niveaux || [])) + '</td>';
            html += '<td>' + dateFR(r.date || '-') + '</td>';
            html += '<td>' + (r.athlete_id ? '<button class="btn-cmp-add' + (inB ? ' added' : '') + '" data-cmp-ath="' + r.athlete_id + '" data-name="' + escapeHtml(r.athlete) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">' + (inB ? '\u2713' : '+') + '</button>' : '') + '</td>';
            html += '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thEpRec + '</table>';
        html += '</div>';
        // Pagination
        if (totalPages > 1) {
            html += '<div class="pager" style="margin-top:12px;">';
            if (pg > 1) html += '<a href="#" onclick="loadEpreuveRecPage(' + (pg - 1) + ');return false;">Précédent</a> ';
            for (var pi = Math.max(1, pg - 3); pi <= Math.min(totalPages, pg + 3); pi++) {
                if (pi === pg) html += '<span class="current">' + pi + '</span> ';
                else html += '<a href="#" onclick="loadEpreuveRecPage(' + pi + ');return false;">' + pi + '</a> ';
            }
            if (pg < totalPages) html += '<a href="#" onclick="loadEpreuveRecPage(' + (pg + 1) + ');return false;">Suivant</a>';
            html += ' <span class="info">(' + totalPages + ' pages)</span>';
            html += '</div>';
        }

    } else if (tab === 'nationalites') {
        var nat = d.nationalites || {};
        var cats = d.par_categorie || {};
        var keys = Object.keys(nat);
        var catKeys = Object.keys(cats);
        if (keys.length === 0 && catKeys.length === 0) { content.innerHTML = '<div class="loading-msg">Aucune donnée</div>'; return; }
        var totalNat = 0;
        keys.forEach(function(k) { totalNat += nat[k]; });

        html += '<div style="margin-bottom:12px;color:#5a6580;font-size:13px;">' + keys.length + ' nationalités — ' + totalNat.toLocaleString('fr-FR') + ' athlètes</div>';
        // Charts
        html += '<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">';
        html += '<div style="flex:1;min-width:200px;max-width:300px;"><canvas id="epNatDonut"></canvas></div>';
        html += '<div style="flex:2;min-width:300px;"><canvas id="epNatBar"></canvas></div>';
        html += '</div>';
        // Clickable buttons
        html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">';
        keys.forEach(function(k) {
            var pct = totalNat > 0 ? Math.round(nat[k] / totalNat * 100) : 0;
            html += '<a href="?page=recherche&epreuve=' + encodeURIComponent(d.epreuve) + '&nationalite=' + encodeURIComponent(k) + '" style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;background:#0d1525;border:1px solid #1a2540;border-radius:8px;color:#a29bfe;text-decoration:none;font-size:12px;transition:all .2s;" onmouseenter="this.style.borderColor=\'#6c5ce7\';this.style.background=\'#6c5ce715\'" onmouseleave="this.style.borderColor=\'#1a2540\';this.style.background=\'#0d1525\'">' + escapeHtml(k) + ' <span style="color:#8b949e;font-size:11px;">' + nat[k] + ' (' + pct + '%)</span></a>';
        });
        html += '</div>';
        // Table nationalités
        var _natTh = '<tr><th>#</th><th>Nationalité</th><th>Athlètes</th><th>%</th></tr>';
        html += '<div class="table-wrap"><table class="bk-table">' + _natTh + '</table><table class="bk-table">';
        keys.forEach(function(k, i) {
            var pct = totalNat > 0 ? Math.round(nat[k] / totalNat * 100) : 0;
            html += '<tr><td>' + (i+1) + '</td><td><b>' + escapeHtml(k) + '</b></td><td>' + nat[k].toLocaleString('fr-FR') + '</td><td><div style="display:flex;align-items:center;gap:6px;"><div style="width:60px;height:6px;background:#1a2540;border-radius:3px;"><div style="width:' + Math.min(pct,100) + '%;height:100%;background:#a78bfa;border-radius:3px;"></div></div><span style="font-size:12px;">' + pct + '%</span></div></td></tr>';
        });
        html += '</table><table class="bk-table">' + _natTh + '</table></div>';
        // Categories section
        if (catKeys.length > 0) {
            var totalCat = 0;
            catKeys.forEach(function(k) { totalCat += cats[k]; });
            html += '<h4 style="color:#8b949e;font-size:13px;margin:20px 0 8px;">Catégories (' + catKeys.length + ')</h4>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">';
            catKeys.forEach(function(k) {
                var pct = totalCat > 0 ? Math.round(cats[k] / totalCat * 100) : 0;
                html += '<span style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;background:#0d1525;border:1px solid #1a2540;border-radius:8px;color:#34d399;font-size:12px;"><span class="badge badge-cat">' + escapeHtml(k) + '</span> <span style="color:#8b949e;font-size:11px;">' + cats[k] + ' (' + pct + '%)</span></span>';
            });
            html += '</div>';
        }

    } else if (tab === 'stats') {
        var sexe = d.par_sexe || {};
        var cats = d.par_categorie || {};
        var nbAth = d.total_athletes || 0;
        var rpa = d.resultats_par_annee || [];
        var med = d.medailles || {};
        var totalMed = d.total_medailles || 0;
        var medDet = d.medailles_detail || [];
        var pod = d.podiums || {};
        var totalPod = d.total_podiums || 0;
        var sel = d.selections || {};
        var prog = d.progressions || {};
        var niv = d.niveaux_resultats || [];
        var topClubs = d.top_clubs || [];
        var topVilles = d.top_villes || [];

        // Row 1: Sexe + Categories charts
        html += '<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">';
        html += '<div style="flex:1;min-width:200px;max-width:300px;"><h4 style="color:#8b949e;font-size:13px;margin:0 0 8px;">Répartition par sexe</h4><canvas id="epSexeChart"></canvas></div>';
        html += '<div style="flex:2;min-width:300px;"><h4 style="color:#8b949e;font-size:13px;margin:0 0 8px;">Catégories</h4><canvas id="epCatChart"></canvas></div>';
        html += '</div>';

        // Row 2: Medailles + Podiums cards
        if (totalMed > 0 || totalPod > 0) {
            html += '<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">';
            if (totalMed > 0) {
                var pctOr = totalMed > 0 ? Math.round((med.or||0)/totalMed*100) : 0;
                var pctAg = totalMed > 0 ? Math.round((med.argent||0)/totalMed*100) : 0;
                var pctBr = totalMed > 0 ? Math.round((med.bronze||0)/totalMed*100) : 0;
                html += '<div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#f59e0b10;border:1px solid #f59e0b30;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#fbbf24;">' + (med.or||0) + '</div><div style="font-size:11px;color:#8b949e;">Or (' + pctOr + '%)</div></div>';
                html += '<div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#94a3b810;border:1px solid #94a3b830;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#94a3b8;">' + (med.argent||0) + '</div><div style="font-size:11px;color:#8b949e;">Argent (' + pctAg + '%)</div></div>';
                html += '<div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#b4540010;border:1px solid #b4540030;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#cd7f32;">' + (med.bronze||0) + '</div><div style="font-size:11px;color:#8b949e;">Bronze (' + pctBr + '%)</div></div>';
            }
            if (totalPod > 0) {
                html += '<div style="flex:1;min-width:120px;text-align:center;padding:14px;background:#10b98110;border:1px solid #10b98130;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#34d399;">' + totalPod + '</div><div style="font-size:11px;color:#8b949e;">Podiums</div></div>';
            }
            html += '</div>';
        }

        // Medailles detail table
        if (medDet.length > 0) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:0 0 8px;">Dernières médailles</h4>';
            var _medTh = '<tr><th>Médaille</th><th>Athlète</th><th>Compétition</th><th>Lieu</th><th>Année</th></tr>';
            html += '<div class="table-wrap"><table class="bk-table">' + _medTh + '</table><table class="bk-table">';
            medDet.forEach(function(m) {
                var ico = m.type === 'or' ? '\uD83E\uDD47' : (m.type === 'argent' ? '\uD83E\uDD48' : '\uD83E\uDD49');
                html += '<tr><td>' + ico + ' ' + escapeHtml(m.type) + '</td>';
                html += '<td><b>' + (m.athlete_id ? '<a href="?page=profil&id=' + m.athlete_id + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(m.athlete) + '</a>' : escapeHtml(m.athlete)) + '</b></td>';
                html += '<td>' + (m.competition ? '<a href="?page=recherche&competition=' + encodeURIComponent(m.competition) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(m.competition) + '</a>' : '-') + '</td>';
                html += '<td>' + (m.lieu ? '<a href="?page=villes&open=' + encodeURIComponent(m.lieu) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(m.lieu) + '</a>' : '-') + '</td>';
                html += '<td>' + (m.annee || '-') + '</td></tr>';
            });
            html += '</table><table class="bk-table">' + _medTh + '</table></div>';
        }

        // Podiums detail
        if (totalPod > 0) {
            html += '<div style="display:flex;gap:12px;margin:16px 0;flex-wrap:wrap;">';
            html += '<div style="padding:10px 16px;background:#fbbf2415;border:1px solid #fbbf2430;border-radius:8px;color:#fbbf24;font-size:13px;font-weight:600;">1er: ' + (pod['1er']||0) + '</div>';
            html += '<div style="padding:10px 16px;background:#94a3b815;border:1px solid #94a3b830;border-radius:8px;color:#94a3b8;font-size:13px;font-weight:600;">2e: ' + (pod['2e']||0) + '</div>';
            html += '<div style="padding:10px 16px;background:#cd7f3215;border:1px solid #cd7f3230;border-radius:8px;color:#cd7f32;font-size:13px;font-weight:600;">3e: ' + (pod['3e']||0) + '</div>';
            html += '</div>';
        }

        // Selections
        if (sel.nb_selections > 0) {
            html += '<div style="margin-bottom:16px;padding:12px;background:#6366f110;border:1px solid #6366f130;border-radius:10px;display:flex;gap:20px;flex-wrap:wrap;">';
            html += '<div><span style="font-size:20px;font-weight:700;color:#818cf8;">' + sel.nb_athletes + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">athlètes sélectionnés</span></div>';
            html += '<div><span style="font-size:20px;font-weight:700;color:#818cf8;">' + sel.nb_selections + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">sélections nationales</span></div>';
            html += '</div>';
        }

        // Progressions
        if (prog.nb_progressions > 0) {
            html += '<div style="margin-bottom:16px;padding:12px;background:#f9731610;border:1px solid #f9731630;border-radius:10px;display:flex;gap:20px;flex-wrap:wrap;">';
            html += '<div><span style="font-size:20px;font-weight:700;color:#fb923c;">' + prog.nb_athletes + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">athlètes avec progression</span></div>';
            html += '<div><span style="font-size:20px;font-weight:700;color:#fb923c;">' + prog.nb_progressions + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">progressions enregistrées</span></div>';
            html += '</div>';
        }

        // Niveaux de competition
        if (niv.length > 0) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Niveaux de compétition</h4>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">';
            niv.forEach(function(n) {
                html += '<div style="display:flex;align-items:center;gap:6px;">' + _nivBadge(n.niveau) + '<span style="color:#8b949e;font-size:12px;">' + n.count.toLocaleString('fr-FR') + '</span></div>';
            });
            html += '</div>';
        }

        // Top clubs
        if (topClubs.length > 0) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Principaux clubs</h4>';
            var _tcTh = '<tr><th>#</th><th>Club</th><th>Athlètes</th><th>Records</th></tr>';
            html += '<div class="table-wrap"><table class="bk-table">' + _tcTh + '</table><table class="bk-table">';
            topClubs.forEach(function(c, i) {
                html += '<tr><td>' + (i+1) + '</td><td><a href="#" onclick="openClubDetail(null,\'' + escapeHtml(c.club).replace(/'/g,"\\'") + '\');return false;" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(c.club) + '</a></td><td>' + c.nb_athletes + '</td><td>' + c.nb_records + '</td></tr>';
            });
            html += '</table><table class="bk-table">' + _tcTh + '</table></div>';
        }

        // Top villes
        if (topVilles.length > 0) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Principaux lieux de compétition</h4>';
            var _tveTh = '<tr><th>#</th><th>Ville</th><th>Records</th><th>Athlètes</th></tr>';
            html += '<div class="table-wrap"><table class="bk-table">' + _tveTh + '</table><table class="bk-table">';
            topVilles.forEach(function(v, i) {
                html += '<tr><td>' + (i+1) + '</td><td><a href="?page=villes&open=' + encodeURIComponent(v.ville) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(v.ville) + '</a></td><td>' + v.nb_records + '</td><td>' + v.nb_athletes + '</td></tr>';
            });
            html += '</table><table class="bk-table">' + _tveTh + '</table></div>';
        }

        // Evolution par annee
        if (rpa.length > 1) {
            html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Évolution par année</h4>';
            html += '<canvas id="epEvoChart" style="max-height:250px;"></canvas>';
        }

    } else if (tab === 'resume') {
        html += _buildEpreuveResumeHTML(d);
    }

    content.innerHTML = html;

    // Post-render charts for nationalites
    if (tab === 'nationalites') {
        var _nat = d.nationalites || {};
        var _nk = Object.keys(_nat);
        var _totalN = 0;
        _nk.forEach(function(k) { _totalN += _nat[k]; });
        var _colors = ['#3b82f6','#ec4899','#8b5cf6','#f59e0b','#10b981','#ef4444','#06b6d4','#f97316','#84cc16','#6366f1','#64748b'];
        var _dc = document.getElementById('epNatDonut');
        if (_dc && _nk.length > 0) {
            var _top10 = _nk.slice(0, 10);
            var _otherC = 0; _nk.slice(10).forEach(function(k) { _otherC += _nat[k]; });
            var _lbl = _top10.map(function(k) { return k; });
            var _dt = _top10.map(function(k) { return _nat[k]; });
            if (_otherC > 0) { _lbl.push('Autres'); _dt.push(_otherC); }
            new Chart(_dc, {
                type: 'doughnut',
                data: { labels: _lbl, datasets: [{ data: _dt, backgroundColor: _colors.slice(0, _lbl.length), borderWidth: 0 }] },
                options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, font: { size: 10 }, color: '#8b949e' } } } }
            });
        }
        var _bc = document.getElementById('epNatBar');
        if (_bc && _nk.length > 0) {
            var _top15 = _nk.slice(0, 15);
            new Chart(_bc, {
                type: 'bar',
                data: { labels: _top15, datasets: [{ data: _top15.map(function(k) { return _nat[k]; }), backgroundColor: '#a78bfa', borderRadius: 4, barThickness: 16 }] },
                options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { display: false }, ticks: { color: '#c8cfd8' } } } }
            });
        }
    }
    // Post-render charts for stats
    if (tab === 'stats') {
        var _sexe = d.par_sexe || {};
        var _cats = d.par_categorie || {};
        var _rpa = (d.resultats_par_annee || []).slice().reverse();
        var _sc = document.getElementById('epSexeChart');
        if (_sc) {
            var _sk = Object.keys(_sexe);
            new Chart(_sc, {
                type: 'doughnut',
                data: { labels: _sk.map(function(k){return k==='M'?'Hommes':(k==='F'?'Femmes':k);}), datasets: [{ data: _sk.map(function(k){return _sexe[k];}), backgroundColor: ['#3b82f6','#ec4899','#64748b'], borderWidth: 0 }] },
                options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, font: { size: 10 }, color: '#8b949e' } } } }
            });
        }
        var _cc = document.getElementById('epCatChart');
        if (_cc) {
            var _ck = Object.keys(_cats).slice(0, 12);
            new Chart(_cc, {
                type: 'bar',
                data: { labels: _ck, datasets: [{ data: _ck.map(function(k){return _cats[k];}), backgroundColor: '#34d399', borderRadius: 4, barThickness: 16 }] },
                options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { display: false }, ticks: { color: '#c8cfd8' } } } }
            });
        }
        var _ec = document.getElementById('epEvoChart');
        if (_ec && _rpa.length > 1) {
            new Chart(_ec, {
                type: 'line',
                data: {
                    labels: _rpa.map(function(r){return r.annee;}),
                    datasets: [
                        { label: 'Résultats', data: _rpa.map(function(r){return r.nb_resultats;}), borderColor: '#6366f1', backgroundColor: '#6366f120', fill: true, tension: 0.3, pointRadius: 3 },
                        { label: 'Athlètes', data: _rpa.map(function(r){return r.nb_athletes;}), borderColor: '#34d399', backgroundColor: '#34d39920', fill: true, tension: 0.3, pointRadius: 3 }
                    ]
                },
                options: { responsive: true, plugins: { legend: { position: 'top', labels: { usePointStyle: true, font: { size: 10 }, color: '#8b949e' } } }, scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } } }, interaction: { intersect: false, mode: 'index' } }
            });
        }
    }
}

// Resume auto-genere pour une epreuve
function _buildEpreuveResumeHTML(d) {
    var txt = [];
    var nom = d.epreuve;
    var nbAth = d.total_athletes || 0;
    var nbRec = d.total_records || 0;
    var sexe = d.par_sexe || {};
    var cats = d.par_categorie || {};
    var nats = d.nationalites || {};
    var med = d.medailles || {};
    var totalMed = d.total_medailles || 0;
    var medDet = d.medailles_detail || [];
    var pod = d.podiums || {};
    var totalPod = d.total_podiums || 0;
    var sel = d.selections || {};
    var prog = d.progressions || {};
    var niv = d.niveaux_resultats || [];
    var topClubs = d.top_clubs || [];
    var topVilles = d.top_villes || [];
    var rpa = d.resultats_par_annee || [];

    // P1: Introduction
    var p1 = 'L\'épreuve ' + nom + ' regroupe ' + nbAth.toLocaleString('fr-FR') + ' athlète' + (nbAth > 1 ? 's' : '') + ' pour un total de ' + nbRec.toLocaleString('fr-FR') + ' record' + (nbRec > 1 ? 's' : '') + ' enregistré' + (nbRec > 1 ? 's' : '') + '.';
    if (d.annee_debut && d.annee_fin) p1 += ' Les données couvrent la période de ' + d.annee_debut + ' à ' + d.annee_fin + '.';
    txt.push(p1);

    // P2: Sexe
    var sk = Object.keys(sexe);
    if (sk.length > 0) {
        var totalS = 0; sk.forEach(function(k) { totalS += sexe[k]; });
        var parts = sk.map(function(k) {
            var pct = totalS > 0 ? Math.round(sexe[k] / totalS * 100) : 0;
            var label = k === 'M' ? 'hommes' : (k === 'F' ? 'femmes' : k);
            return sexe[k].toLocaleString('fr-FR') + ' ' + label + ' (' + pct + '%)';
        });
        txt.push('La répartition par sexe comprend ' + parts.join(', ') + '.');
    }

    // P3: Categories
    var ck = Object.keys(cats);
    if (ck.length > 0) {
        var totalC = 0; ck.forEach(function(k) { totalC += cats[k]; });
        var topCats = ck.slice(0, 5).map(function(k) {
            var pct = totalC > 0 ? Math.round(cats[k] / totalC * 100) : 0;
            return k + ' (' + pct + '%)';
        });
        txt.push('Les catégories les plus représentées sont ' + topCats.join(', ') + '.');
    }

    // P4: Nationalites
    var nk = Object.keys(nats);
    if (nk.length > 0) {
        var totalN = 0; nk.forEach(function(k) { totalN += nats[k]; });
        var topN = nk.slice(0, 5).map(function(k) {
            var pct = totalN > 0 ? Math.round(nats[k] / totalN * 100) : 0;
            return k + ' (' + pct + '%)';
        });
        txt.push(nk.length + ' nationalité' + (nk.length > 1 ? 's' : '') + ' sont représentées. Les principales : ' + topN.join(', ') + '.');
    }

    // P5: Medailles
    if (totalMed > 0) {
        var p5 = totalMed + ' médaille' + (totalMed > 1 ? 's' : '') + ' ont été décernées dans cette épreuve : ';
        var mp = [];
        if (med.or > 0) mp.push(med.or + ' or');
        if (med.argent > 0) mp.push(med.argent + ' argent');
        if (med.bronze > 0) mp.push(med.bronze + ' bronze');
        p5 += mp.join(', ') + '.';
        if (medDet.length > 0) {
            p5 += ' Dernière médaille : ' + medDet[0].athlete + ' (' + medDet[0].type + (medDet[0].annee ? ', ' + medDet[0].annee : '') + (medDet[0].competition ? ', ' + medDet[0].competition : '') + ').';
        }
        txt.push(p5);
    }

    // P6: Podiums
    if (totalPod > 0) {
        var p6 = totalPod + ' podium' + (totalPod > 1 ? 's' : '') + ' enregistré' + (totalPod > 1 ? 's' : '') + ' : ';
        var pp = [];
        if (pod['1er'] > 0) pp.push(pod['1er'] + ' première' + (pod['1er'] > 1 ? 's' : '') + ' place' + (pod['1er'] > 1 ? 's' : ''));
        if (pod['2e'] > 0) pp.push(pod['2e'] + ' deuxième' + (pod['2e'] > 1 ? 's' : '') + ' place' + (pod['2e'] > 1 ? 's' : ''));
        if (pod['3e'] > 0) pp.push(pod['3e'] + ' troisième' + (pod['3e'] > 1 ? 's' : '') + ' place' + (pod['3e'] > 1 ? 's' : ''));
        p6 += pp.join(', ') + '.';
        txt.push(p6);
    }

    // P7: Selections
    if (sel.nb_selections > 0) {
        txt.push(sel.nb_athletes + ' athlète' + (sel.nb_athletes > 1 ? 's' : '') + ' ont été sélectionné' + (sel.nb_athletes > 1 ? 's' : '') + ' en équipe nationale pour cette épreuve, totalisant ' + sel.nb_selections + ' sélection' + (sel.nb_selections > 1 ? 's' : '') + '.');
    }

    // P8: Niveaux
    if (niv.length > 0) {
        var nivParts = niv.slice(0, 5).map(function(n) { return n.niveau + ' (' + n.count + ')'; });
        txt.push('Les niveaux de compétition incluent : ' + nivParts.join(', ') + '.');
    }

    // P9: Top clubs
    if (topClubs.length > 0) {
        var tcParts = topClubs.slice(0, 5).map(function(c) { return c.club + ' (' + c.nb_athletes + ' athlètes)'; });
        txt.push('Les principaux clubs pratiquant cette épreuve sont ' + tcParts.join(', ') + '.');
    }

    // P10: Top villes
    if (topVilles.length > 0) {
        var tvParts = topVilles.slice(0, 5).map(function(v) { return v.ville + ' (' + v.nb_records + ' records)'; });
        txt.push('Les compétitions ont principalement eu lieu à ' + tvParts.join(', ') + '.');
    }

    // P11: Progressions
    if (prog.nb_progressions > 0) {
        txt.push(prog.nb_athletes + ' athlète' + (prog.nb_athletes > 1 ? 's' : '') + ' ont enregistré des progressions dans cette épreuve, pour un total de ' + prog.nb_progressions + ' progression' + (prog.nb_progressions > 1 ? 's' : '') + '.');
    }

    // P12: Evolution
    if (rpa.length > 1) {
        var first = rpa[rpa.length - 1];
        var last = rpa[0];
        txt.push('L\'activité dans cette épreuve s\'étend de ' + first.annee + ' à ' + last.annee + '. En ' + last.annee + ', ' + last.nb_resultats + ' résultat' + (last.nb_resultats > 1 ? 's' : '') + ' ont été enregistrés par ' + last.nb_athletes + ' athlète' + (last.nb_athletes > 1 ? 's' : '') + '.');
    }

    // Build HTML with copy button
    var resumeHtml = '<div style="margin-bottom:12px;display:flex;justify-content:flex-end;">';
    resumeHtml += '<button onclick="copyEpreuveResume()" style="padding:6px 14px;background:#6c5ce720;border:1px solid #6c5ce740;border-radius:8px;color:#a29bfe;font-size:12px;cursor:pointer;">Copier le texte</button>';
    resumeHtml += '</div>';
    resumeHtml += '<div id="epreuveResumeText" style="line-height:1.8;color:#c8cfd8;font-size:14px;">';
    txt.forEach(function(p) {
        resumeHtml += '<p style="margin-bottom:12px;">' + escapeHtml(p) + '</p>';
    });
    resumeHtml += '</div>';
    return resumeHtml;
}
function copyEpreuveResume() {
    var el = document.getElementById('epreuveResumeText');
    if (el) {
        navigator.clipboard.writeText(el.innerText).then(function() {
            var btn = el.parentElement.querySelector('button');
            if (btn) { btn.textContent = 'Copié !'; setTimeout(function() { btn.textContent = 'Copier le texte'; }, 2000); }
        });
    }
}
