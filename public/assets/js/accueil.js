/**
 * accueil.js — Accueil (home page) JavaScript for Bokonzi
 *
 * Extracted from index.php (lines ~885-1221).
 * Contains:
 *   - _buildAccueilTables(d) : renders top clubs, athletes, villes, epreuves tables + charts
 *   - Top Consultes (clubs & athletes) : _loadTopClubs, _loadTopAth, period tabs, auto-refresh
 *   - _initAccueilCharts(parSexeData, parCategorieData) : initializes sexe & categories charts
 *
 * PHP data injection is done inline in index.php after loading this script.
 */

document.addEventListener('DOMContentLoaded', function(){
    function _esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function _nivBar(np) {
        if (!np) return '-';
        var h = '<div style="display:flex;align-items:center;gap:2px;min-width:120px;" title="D:'+np.D+'% R:'+np.R+'% N:'+np.N+'% I:'+np.I+'% ('+np.total+' r\u00e9s.)">';
        h += '<div style="display:flex;height:8px;flex:1;border-radius:4px;overflow:hidden;background:#1a2540;">';
        if (np.D > 0) h += '<div style="width:'+np.D+'%;background:#fb923c;"></div>';
        if (np.R > 0) h += '<div style="width:'+np.R+'%;background:#22d3ee;"></div>';
        if (np.N > 0) h += '<div style="width:'+np.N+'%;background:#fb7185;"></div>';
        if (np.I > 0) h += '<div style="width:'+np.I+'%;background:#e879f9;"></div>';
        h += '</div>';
        h += '<div style="display:flex;gap:3px;font-size:9px;white-space:nowrap;">';
        if (np.D > 0) h += '<span style="color:#fb923c;">D'+np.D+'</span>';
        if (np.R > 0) h += '<span style="color:#22d3ee;">R'+np.R+'</span>';
        if (np.N > 0) h += '<span style="color:#fb7185;">N'+np.N+'</span>';
        if (np.I > 0) h += '<span style="color:#e879f9;">I'+np.I+'</span>';
        h += '</div></div>';
        return h;
    }
    function _paginator(items, bodyId, pagId, perPage, renderRow) {
        var pg = 0;
        function render() {
            var start = pg * perPage, end = Math.min(start + perPage, items.length);
            var html = '';
            for (var i = start; i < end; i++) html += renderRow(items[i], i);
            document.getElementById(bodyId).innerHTML = html;
            var totalPages = Math.ceil(items.length / perPage);
            var ph = '';
            if (pg > 0) ph += '<button onclick="window._pg_' + bodyId + '(' + (pg-1) + ')" style="padding:6px 14px;background:#1a2540;border:1px solid #253560;border-radius:6px;color:#d0d7e0;cursor:pointer;">Pr\u00e9c\u00e9dent</button>';
            ph += '<span style="color:#5a6580;font-size:13px;padding:6px 8px;">' + (pg+1) + ' / ' + totalPages + '</span>';
            if (pg < totalPages - 1) ph += '<button onclick="window._pg_' + bodyId + '(' + (pg+1) + ')" style="padding:6px 14px;background:#1a2540;border:1px solid #253560;border-radius:6px;color:#d0d7e0;cursor:pointer;">Suivant</button>';
            document.getElementById(pagId).innerHTML = ph;
        }
        window['_pg_' + bodyId] = function(p) { pg = p; render(); };
        render();
    }

    // ---- Build accueil tables (called with detail data from PHP or AJAX) ----
    window._buildAccueilTables = function(d) {
        if (!d || !d.success) return;

        // Top Clubs
        if (d.top_clubs && d.top_clubs.length > 0) {
            document.getElementById('accueilClubsCount').textContent = '(' + d.top_clubs.length + ' clubs)';
            _paginator(d.top_clubs, 'topClubsBody', 'topClubsPag', 10, function(c, i) {
                return '<tr><td>' + (i+1) + '</td>'
                    + '<td><a href="?page=clubs&open=' + encodeURIComponent(c.club) + '" style="color:#a29bfe;text-decoration:none;cursor:pointer;">' + _esc(c.club) + '</a></td>'
                    + '<td>' + (c.nb_athletes||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + (c.nb_records||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + (c.nb_medailles > 0 ? '<span style="color:#fbbf24;font-weight:600;">' + c.nb_medailles + '</span>' : '-') + '</td>'
                    + '<td>' + _nivBar(c.niveaux_pct) + '</td>'
                    + '<td><a href="?page=clubs&open=' + encodeURIComponent(c.club) + '" style="color:#5a6580;font-size:12px;">D\u00e9tails \u2192</a></td>'
                    + '</tr>';
            });
            var top10c = d.top_clubs.slice(0, 10);
            window._topClubsRaw = top10c.map(function(c) { return {name: c.club, count: c.nb_athletes}; });
            try {
                new Chart(document.getElementById('chartClubs'), {
                    type: 'bar',
                    data: { labels: top10c.map(function(c) { return c.club.substring(0, 20); }), datasets: [{ data: top10c.map(function(c) { return c.nb_athletes; }), backgroundColor: '#a78bfa', borderRadius: 6, barThickness: 22 }] },
                    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
                });
            } catch(e) {}
        }

        // Ath\u00e8tes (shuffle)
        if (d.top_athletes && d.top_athletes.length > 0) {
            var ath = d.top_athletes;
            for (var i = ath.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = ath[i]; ath[i] = ath[j]; ath[j] = t; }
            document.getElementById('accueilAthletesCount').textContent = '(' + ath.length + ' athl\u00e8tes)';
            _paginator(ath, 'topAthletesBody', 'topAthletesPag', 10, function(a, i) {
                var medH = a.nb_medailles > 0 ? '<span style="color:#fbbf24;font-weight:600;">' + a.nb_medailles + '</span>' : '-';
                return '<tr>'
                    + '<td><b><a href="?page=profil&id=' + a.athlete_id + '" style="color:#a29bfe;text-decoration:none;">' + _esc(a.nom) + '</a></b></td>'
                    + '<td>' + (a.club ? '<a href="?page=clubs&open=' + encodeURIComponent(a.club) + '" style="font-size:12px;color:#8b949e;text-decoration:none;">' + _esc(a.club).substring(0,25) + '</a>' : '-') + '</td>'
                    + '<td><a href="?page=recherche&categorie=' + encodeURIComponent(a.categorie||'') + '" style="text-decoration:none;"><span class="badge badge-cat">' + _esc(a.categorie||'') + '</span></a></td>'
                    + '<td><a href="?page=recherche&nationalite=' + encodeURIComponent(a.nationalite||'') + '" style="color:#c9d1d9;text-decoration:none;">' + _esc(a.nationalite||'') + '</a></td>'
                    + '<td>' + medH + '</td>'
                    + '<td>' + (a.nb_podiums > 0 ? '<span style="color:#34d399;font-weight:600;">' + a.nb_podiums + '</span>' : '-') + '</td>'
                    + '<td>' + (a.nb_selections > 0 ? '<span style="color:#818cf8;font-weight:600;">' + a.nb_selections + '</span>' : '-') + '</td>'
                    + '<td>' + (a.nb_records > 0 ? '<span class="badge badge-perf">' + a.nb_records + '</span>' : '-') + '</td>'
                    + '<td>' + _nivBar(a.niveaux_pct) + '</td>'
                    + '<td><button class="btn-cmp-add" data-cmp-ath="' + a.athlete_id + '" data-name="' + _esc(a.nom) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">+</button></td>'
                    + '</tr>';
            });
        }

        // Top Villes
        if (d.top_villes && d.top_villes.length > 0) {
            document.getElementById('accueilVillesCount').textContent = '(' + d.top_villes.length + ' villes)';
            _paginator(d.top_villes, 'topVillesBody', 'topVillesPag', 10, function(v, i) {
                return '<tr><td>' + (i+1) + '</td>'
                    + '<td><a href="?page=villes&open=' + encodeURIComponent(v.ville) + '" style="color:#a29bfe;text-decoration:none;cursor:pointer;">' + _esc(v.ville) + '</a></td>'
                    + '<td>' + (v.nb_resultats||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + (v.nb_athletes||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + _nivBar(v.niveaux_pct) + '</td>'
                    + '</tr>';
            });
        }

        // Top \u00c9preuves
        if (d.top_epreuves && d.top_epreuves.length > 0) {
            document.getElementById('accueilEpreuvesCount').textContent = '(' + d.top_epreuves.length + ' \u00e9preuves)';
            _paginator(d.top_epreuves, 'topEpreuvesBody', 'topEpreuvesPag', 10, function(e, i) {
                return '<tr><td>' + (i+1) + '</td>'
                    + '<td><a href="?page=recherche&epreuve=' + encodeURIComponent(e.epreuve) + '" style="color:#a29bfe;text-decoration:none;">' + _esc(e.epreuve) + '</a></td>'
                    + '<td>' + (e.nb_records||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + (e.nb_athletes||0).toLocaleString('fr-FR') + '</td>'
                    + '<td>' + _nivBar(e.niveaux_pct) + '</td>'
                    + '</tr>';
            });
            try {
                var top10e = d.top_epreuves.slice(0, 10);
                new Chart(document.getElementById('chartEpreuves'), {
                    type: 'bar',
                    data: { labels: top10e.map(function(e) { return e.epreuve; }), datasets: [{ label: 'Records', data: top10e.map(function(e) { return e.nb_records; }),
                        backgroundColor: function(ctx) { var g = ctx.chart.ctx.createLinearGradient(0,0,ctx.chart.width,0); g.addColorStop(0,'#ec4899'); g.addColorStop(1,'#f59e0b'); return g; },
                        borderRadius: 6, barThickness: 22 }] },
                    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false } } } }
                });
            } catch(e) {}
        }
    };
});

/* ======== TOP CONSULTES JS ======== */
document.addEventListener('DOMContentLoaded', function(){
    function _esc2(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Fallback : si search_tracking vide, utilise stats_detail_30 (top_athletes/top_clubs par score)
    function _fbMapAth(athletes) {
        return athletes.map(function(a) {
            return { id: a.athlete_id, nom: a.nom, club: (a.club || '').replace(/\*\s*$/, ''), categorie: a.categorie || '', sexe: a.sexe || '', vues: (a.nb_medailles||0)*5 + (a.nb_podiums||0)*3 + (a.nb_selections||0)*4 + (a.nb_records||0) };
        });
    }
    function _fbMapClubs(clubs) {
        return clubs.map(function(c) {
            return { id: 0, nom: (c.club || '').replace(/\*\s*$/, ''), nb_athletes: c.nb_athletes || 0, vues: c.nb_athletes || 0 };
        });
    }

    function _topSearchPag(items, bodyId, pagId, perPage, maxPages, renderRow) {
        var pg = 0, expanded = false;
        function render() {
            var pp = expanded ? 25 : perPage;
            var maxItems = expanded ? items.length : Math.min(items.length, perPage * maxPages);
            var visible = items.slice(0, maxItems);
            var totalPages = Math.ceil(visible.length / pp);
            var start = pg * pp, end = Math.min(start + pp, visible.length);
            var html = '';
            for (var i = start; i < end; i++) html += renderRow(visible[i], i);
            if (!html) html = '<tr><td colspan="3" style="text-align:center;color:#5a6580;padding:20px;">Aucune donn\u00e9e</td></tr>';
            document.getElementById(bodyId).innerHTML = html;
            var ph = '';
            if (totalPages > 1) {
                if (pg > 0) ph += '<button onclick="window._tsp_'+bodyId+'('+(pg-1)+')" style="padding:5px 12px;background:#1a2540;border:1px solid #253560;border-radius:6px;color:#d0d7e0;cursor:pointer;font-size:12px;">\u2190</button>';
                ph += '<span style="color:#5a6580;font-size:12px;padding:4px 8px;">'+(pg+1)+' / '+totalPages+'</span>';
                if (pg < totalPages - 1) ph += '<button onclick="window._tsp_'+bodyId+'('+(pg+1)+')" style="padding:5px 12px;background:#1a2540;border:1px solid #253560;border-radius:6px;color:#d0d7e0;cursor:pointer;font-size:12px;">\u2192</button>';
            }
            if (!expanded && items.length > perPage * maxPages) {
                ph += '<button onclick="window._tse_'+bodyId+'()" style="padding:5px 14px;background:#6c5ce722;border:1px solid #6c5ce7;border-radius:6px;color:#a29bfe;cursor:pointer;font-size:12px;margin-left:8px;">Voir tout ('+items.length+')</button>';
            }
            if (expanded && totalPages > 1) {
                ph += '<button onclick="window._tsc_'+bodyId+'()" style="padding:5px 14px;background:transparent;border:1px solid #253560;border-radius:6px;color:#5a6580;cursor:pointer;font-size:11px;margin-left:8px;">R\u00e9duire</button>';
            }
            document.getElementById(pagId).innerHTML = ph;
        }
        window['_tsp_'+bodyId] = function(p) { pg = p; render(); };
        window['_tse_'+bodyId] = function() { expanded = true; pg = 0; render(); };
        window['_tsc_'+bodyId] = function() { expanded = false; pg = 0; render(); };
        render();
    }

    // ---- Period tabs ----
    var _topPeriods = [{d:1,l:'Jour'},{d:7,l:'Semaine'},{d:30,l:'Mois'},{d:365,l:'Ann\u00e9e'}];
    var _clubDays = 1, _athDays = 1;
    var _tabStyle = 'padding:5px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid #253560;transition:all .2s;';
    var _tabActiveStyle = 'background:#6c5ce7;color:#fff;border-color:#6c5ce7;';
    var _tabInactiveStyle = 'background:transparent;color:#5a6580;border-color:#253560;';

    function _renderTabs(containerId, current, onClick) {
        var h = '';
        _topPeriods.forEach(function(p) {
            var active = p.d === current;
            h += '<button onclick="'+onClick+'('+p.d+')" style="'+_tabStyle+(active?_tabActiveStyle:_tabInactiveStyle)+'">'+p.l+'</button>';
        });
        document.getElementById(containerId).innerHTML = h;
    }

    // ---- Load & render clubs ----
    window._switchClubDays = function(d) { _clubDays = d; _renderTabs('topClubsTabs', _clubDays, '_switchClubDays'); _loadTopClubs(true); };
    function _loadTopClubs(nc) {
        document.getElementById('topSearchClubsBody').innerHTML = '<tr><td colspan="4" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr>';
        document.getElementById('topSearchClubsPag').innerHTML = '';
        fetch(BASE_API + '/top_searched.php?type=clubs&days=' + _clubDays + (nc ? '&nocache' : ''))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success && d.items && d.items.length) {
                    _renderTopClubs(d.items);
                } else if (window._topFallbackData && window._topFallbackData.top_clubs && window._topFallbackData.top_clubs.length) {
                    _renderTopClubs(_fbMapClubs(window._topFallbackData.top_clubs));
                } else {
                    _renderTopClubs([]);
                }
            })
            .catch(function() {
                if (window._topFallbackData && window._topFallbackData.top_clubs && window._topFallbackData.top_clubs.length) {
                    _renderTopClubs(_fbMapClubs(window._topFallbackData.top_clubs));
                } else { _renderTopClubs([]); }
            });
    }
    function _renderTopClubs(items) {
        if (!items || !items.length) {
            document.getElementById('topSearchClubsBody').innerHTML = '<tr><td colspan="4" style="text-align:center;color:#5a6580;padding:20px;">Aucune donn\u00e9e</td></tr>';
            document.getElementById('topSearchClubsCount').textContent = '';
            return;
        }
        document.getElementById('topSearchClubsCount').textContent = '(' + items.length + ')';
        _topSearchPag(items, 'topSearchClubsBody', 'topSearchClubsPag', 10, 5, function(c, i) {
            return '<tr>'
                + '<td style="color:#5a6580;width:40px;">' + (i+1) + '</td>'
                + '<td><a href="?page=recherche&club=' + encodeURIComponent(c.nom) + '" style="color:#a29bfe;text-decoration:none;font-weight:600;">' + _esc2(c.nom) + '</a></td>'
                + '<td style="color:#8b949e;font-size:12px;">' + (c.nb_athletes || '-') + '</td>'
                + '<td style="text-align:center;"><span style="color:#f59e0b;font-weight:600;">' + c.vues + '</span></td>'
                + '</tr>';
        });
    }

    // ---- Load & render athletes (depuis search_tracking) ----
    window._switchAthDays = function(d) { _athDays = d; _renderTabs('topAthTabs', _athDays, '_switchAthDays'); _loadTopAth(true); };
    function _loadTopAth(nc) {
        document.getElementById('topSearchAthBody').innerHTML = '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:20px;">Chargement...</td></tr>';
        document.getElementById('topSearchAthPag').innerHTML = '';
        fetch(BASE_API + '/top_searched.php?type=athletes&days=' + _athDays + (nc ? '&nocache' : ''))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success && d.items && d.items.length) {
                    _renderTopAth(d.items);
                } else {
                    _renderTopAth([]);
                }
            })
            .catch(function() { _renderTopAth([]); });
    }
    function _renderTopAth(items) {
        if (!items || !items.length) {
            document.getElementById('topSearchAthBody').innerHTML = '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:20px;">Aucune donn\u00e9e</td></tr>';
            document.getElementById('topSearchAthCount').textContent = '';
            return;
        }
        document.getElementById('topSearchAthCount').textContent = '(' + items.length + ')';
        _topSearchPag(items, 'topSearchAthBody', 'topSearchAthPag', 10, 5, function(a, i) {
            return '<tr>'
                + '<td style="color:#5a6580;width:40px;">' + (i+1) + '</td>'
                + '<td><a href="?page=profil&id=' + a.id + '" style="color:#a29bfe;text-decoration:none;font-weight:600;">' + _esc2(a.nom) + '</a></td>'
                + '<td style="color:#8b949e;font-size:12px;">' + _esc2(a.club || '-') + '</td>'
                + '<td><span class="badge badge-' + ((a.sexe||'').toLowerCase()) + '" style="font-size:11px;">' + _esc2(a.sexe || '-') + '</span></td>'
                + '<td style="text-align:center;"><span style="color:#f59e0b;font-weight:600;">' + a.vues + '</span></td>'
                + '</tr>';
        });
    }

    // Init tabs + load + auto-refresh toutes les 60s
    _renderTabs('topClubsTabs', _clubDays, '_switchClubDays');
    _renderTabs('topAthTabs', _athDays, '_switchAthDays');
    _loadTopClubs();
    _loadTopAth();
    setInterval(function() { _loadTopClubs(true); _loadTopAth(true); }, 60000);
});

/* ======== INIT CHARTS (sexe + categories — data injected by PHP inline) ======== */
window._initAccueilCharts = function(parSexeLabels, parSexeData, parCatLabels, parCatData) {
    Chart.defaults.color = '#8892a8';
    Chart.defaults.borderColor = '#1e2a3a';
    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";

    // --- Sexe (Doughnut) ---
    new Chart(document.getElementById('chartSexe'), {
        type: 'doughnut',
        data: {
            labels: parSexeLabels,
            datasets: [{
                data: parSexeData,
                backgroundColor: ['#3b82f6', '#ec4899', '#8b5cf6', '#10b981'],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, pointStyleWidth: 10, font: { size: 12 } } }
            }
        }
    });

    // --- Categories (Bar horizontal) ---
    new Chart(document.getElementById('chartCategories'), {
        type: 'bar',
        data: {
            labels: parCatLabels,
            datasets: [{
                data: parCatData,
                backgroundColor: '#10b981',
                borderRadius: 4,
                barThickness: 14
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#1e2a3a' }, ticks: { font: { size: 11 } } },
                y: { grid: { display: false }, ticks: { font: { size: 11, weight: 600 } } }
            }
        }
    });
};
