/**
 * Club Panel JS — Panneau detail club (5 onglets)
 * Fonctions reutilisables avec suffixe pour multi-instance ('', 'Accueil')
 * Depends on: utils.js (BASE_API, escapeHtml, dateFR, _nivBadge, _nivBadges, _highestNiveau)
 *             basket.js (isAthleteInBasket, toggleAthleteBasket)
 *             follow.js (toggleFollowClub, _checkClubFollowStatus)
 *             tracking.js (_trackSearch)
 */

function _fillClubPanel(data, suffix) {
    var s = suffix || '';
    window['_clubDetailData' + s] = data;
    window['_clubRecFilter' + s] = '';
    window['_clubResumeMode' + s] = 'global';
    window['_clubResumeYear' + s] = null;
    window['_clubYearDataCache' + s] = {};
    window['_clubCompareChart' + s] = null;
    window._ctxClubName = data.club.nom_club;
    document.getElementById('clubDetailName' + s).textContent = data.club.nom_club;
    var meta = data.total_athletes + ' athletes';
    if (data.annee_debut) meta += ' | ' + data.annee_debut + '-' + (data.annee_fin || '...');
    var med = data.medailles;
    if (med.or + med.argent + med.bronze > 0) {
        meta += ' | ';
        if (med.or > 0) meta += '\uD83E\uDD47' + med.or + ' ';
        if (med.argent > 0) meta += '\uD83E\uDD48' + med.argent + ' ';
        if (med.bronze > 0) meta += '\uD83E\uDD49' + med.bronze;
    }
    var filterBadges = '';
    if (data.filter_nationalite) filterBadges += ' <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;background:#ec489930;border:1px solid #ec489960;color:#f472b6;margin-left:4px;">\u{1F30D} ' + escapeHtml(data.filter_nationalite) + '</span>';
    if (data.filter_sexe) filterBadges += ' <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;background:#3b82f630;border:1px solid #3b82f660;color:#60a5fa;margin-left:4px;">' + (data.filter_sexe === 'M' ? 'Hommes' : 'Femmes') + '</span>';
    if (data.filter_categorie) filterBadges += ' <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;background:#10b98130;border:1px solid #10b98160;color:#34d399;margin-left:4px;">' + escapeHtml(data.filter_categorie) + '</span>';
    var metaEl = document.getElementById('clubDetailMeta' + s);
    metaEl.innerHTML = escapeHtml(meta) + filterBadges;
    var qrDiv = document.getElementById('clubQR' + s);
    if (qrDiv) qrDiv.innerHTML = bkQR('https://bokonzi.com/?page=clubs&open=' + encodeURIComponent(data.club.nom_club));
    var btnFC = document.getElementById('btnFollowClub' + s);
    if (btnFC && data.club.id_club) {
        btnFC.style.display = '';
        btnFC.setAttribute('data-club-id', data.club.id_club);
        btnFC.onclick = function() { toggleFollowClub(data.club.id_club, s); };
        _checkClubFollowStatus(data.club.id_club, s);
    }
    _renderClubTab('epreuves', s);
}

function _openClubPanel(fetchUrl, suffix) {
    var s = suffix || '';
    var panel = document.getElementById('clubDetailPanel' + s);
    var content = document.getElementById('clubDetailContent' + s);
    if (!panel || !content) return;
    panel.classList.add('active');
    content.innerHTML = '<div class="loading-msg">Chargement...</div>';
    document.getElementById('clubDetailName' + s).textContent = '';
    document.getElementById('clubDetailMeta' + s).textContent = '';
    var btnFC = document.getElementById('btnFollowClub' + s);
    if (btnFC) { btnFC.style.display = 'none'; btnFC.className = 'btn-follow btn-follow-club'; btnFC.innerHTML = '\u2661 Suivre'; }
    panel.querySelectorAll('.club-detail-tab').forEach(function(t) { t.classList.remove('active'); });
    var first = panel.querySelector('.club-detail-tab[data-tab="epreuves"]');
    if (first) first.classList.add('active');
    fetch(fetchUrl)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { content.innerHTML = '<div class="loading-msg">Club non trouve</div>'; return; }
            _fillClubPanel(data, s);
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            var _cInfo = data.club || {};
            _trackSearch({ q: _cInfo.nom_club || '', type: 'club', source: 'panel_open', entity_id: _cInfo.id_club || null, entity_name: _cInfo.nom_club || '', pg: 'club_panel' });
        })
        .catch(function() { content.innerHTML = '<div class="loading-msg">Erreur de chargement</div>'; });
}

function _closeClubPanel(suffix) {
    var s = suffix || '';
    var panel = document.getElementById('clubDetailPanel' + s);
    if (panel) panel.classList.remove('active');
    window['_clubDetailData' + s] = null;
    window['_clubYearDataCache' + s] = {};
    if (window['_clubCompareChart' + s]) {
        window['_clubCompareChart' + s].destroy();
        window['_clubCompareChart' + s] = null;
    }
}

function _switchClubTab(tab, suffix) {
    var s = suffix || '';
    var panel = document.getElementById('clubDetailPanel' + s);
    if (panel) panel.querySelectorAll('.club-detail-tab').forEach(function(t) {
        t.classList.toggle('active', t.getAttribute('data-tab') === tab);
    });
    _renderClubTab(tab, s);
}

function _clubFilterParams(d) {
    var p = '';
    if (d.filter_nationalite) p += '&nationalite=' + encodeURIComponent(d.filter_nationalite);
    if (d.filter_sexe) p += '&sexe=' + encodeURIComponent(d.filter_sexe);
    if (d.filter_categorie) p += '&categorie=' + encodeURIComponent(d.filter_categorie);
    return p;
}

// ============================================================
// _renderClubTab — Main rendering function for all 6 tabs
// ============================================================
function _renderClubTab(tab, suffix) {
    var s = suffix || '';
    var content = document.getElementById('clubDetailContent' + s);
    var d = window['_clubDetailData' + s];
    if (!content || !d) return;
    var html = '';

    // ---- TAB: EPREUVES ----
    if (tab === 'epreuves') {
        html = _renderClubTabEpreuves(d, s);
    // ---- TAB: NATIONALITES ----
    } else if (tab === 'nationalites') {
        html = _renderClubTabNationalites(d, s, content);
        if (html === false) return; // early return handled inside
    // ---- TAB: RECORDS ----
    } else if (tab === 'records') {
        html = _renderClubTabRecords(d, s, content);
        if (html === false) return;
    // ---- TAB: PERFORMANCES ----
    } else if (tab === 'performances') {
        html = _renderClubTabPerformances(d, s, content);
        if (html === false) return;
    // ---- TAB: STATS ----
    } else if (tab === 'stats') {
        html = _renderClubTabStats(d, s);
    // ---- TAB: RESUME ----
    } else if (tab === 'resume') {
        html = _renderClubTabResume(d, s);
    }

    content.innerHTML = html;

    // Post-render charts
    _postRenderClubCharts(tab, d, s);
}

// ---- Epreuves tab ----
function _renderClubTabEpreuves(d, s) {
    var ep = d.epreuves || [];
    var totalEp = d.total_epreuves || ep.length;
    var epPage = d.ep_page || 1;
    var epPages = d.ep_pages || 1;
    if (ep.length === 0 && epPage === 1) return '<div class="loading-msg">Aucune épreuve trouvée</div>';

    var html = '';
    var epMode = window['_clubEpMode' + s] || 'club';
    html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">';
    ['club','perso'].forEach(function(m) {
        var labels = {club:'Records du club', perso:'Records personnels'};
        var icons = {club:'&#127942;', perso:'&#128100;'};
        var active = epMode === m;
        html += '<button onclick="_clubSetEpMode(\'' + m + '\',\'' + s + '\')" style="padding:6px 16px;border-radius:8px;border:1px solid '+(active?'#a29bfe':'#1e2a3a')+';background:'+(active?'#a29bfe20':'transparent')+';color:'+(active?'#a29bfe':'#5a6580')+';font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;">' + icons[m] + ' ' + labels[m] + '</button>';
    });
    html += '<span style="color:#5a6580;font-size:13px;margin-left:auto;">' + totalEp.toLocaleString('fr-FR') + ' épreuves — Page ' + epPage + '/' + epPages + '</span>';
    html += '</div>';

    // Discipline filters
    var discMap = {};
    ep.forEach(function(e) { if (e.discipline && e.disc_color) discMap[e.discipline] = e.disc_color; });
    var discKeys = Object.keys(discMap);
    var discFilter = window['_clubDiscFilter' + s] || null;
    if (discKeys.length > 1) {
        html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">';
        var allActive = !discFilter;
        html += '<button onclick="_clubToggleDisc(null,\'' + s + '\')" style="padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(allActive?'#a29bfe':'#1e2a3a')+';background:'+(allActive?'#a29bfe20':'transparent')+';color:'+(allActive?'#a29bfe':'#5a6580')+';transition:all .2s;">Tout</button>';
        discKeys.forEach(function(dk) {
            var dc = discMap[dk];
            var isOn = discFilter && discFilter.indexOf(dk) !== -1;
            html += '<button onclick="_clubToggleDisc(\'' + dk + '\',\'' + s + '\')" style="padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?dc:'#1e2a3a')+';background:'+(isOn?dc+'20':'transparent')+';color:'+(isOn?dc:'#5a6580')+';transition:all .2s;">' + escapeHtml(dk) + '</button>';
        });
        html += '</div>';
    }

    // Level filters
    var nivMap = {};
    ep.forEach(function(e) { (e.niveaux || []).forEach(function(n) { if (n) nivMap[n] = 1; }); });
    var nivKeys = Object.keys(nivMap).sort(function(a, b) {
        var ord = {IE:100,IR:99};
        for (var p in {N:90,R:80,D:70}) for (var i=1;i<=8;i++) ord[p+i] = {N:90,R:80,D:70}[p] - i;
        return (ord[b]||0) - (ord[a]||0);
    });
    var nivFilter = window['_clubNivFilter' + s] || null;
    if (nivKeys.length > 1) {
        html += '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;align-items:center;">';
        html += '<span style="color:#5a6580;font-size:11px;margin-right:4px;">Niveaux :</span>';
        var nivAllActive = !nivFilter;
        html += '<button onclick="_clubToggleNiv(null,\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(nivAllActive?'#a29bfe':'#1e2a3a')+';background:'+(nivAllActive?'#a29bfe20':'transparent')+';color:'+(nivAllActive?'#a29bfe':'#5a6580')+';transition:all .2s;">Tout</button>';
        nivKeys.forEach(function(nk) {
            var nc = nk.charAt(0);
            var clr = nc==='N'?'#fb7185': nc==='I'?'#e879f9': nc==='R'?'#22d3ee': '#fb923c';
            var isOn = nivFilter && nivFilter.indexOf(nk) !== -1;
            html += '<button onclick="_clubToggleNiv(\'' + nk + '\',\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?clr:'#1e2a3a')+';background:'+(isOn?clr+'20':'transparent')+';color:'+(isOn?clr:'#5a6580')+';transition:all .2s;">' + nk + '</button>';
        });
        html += '</div>';
    }

    // Year filter + comparison mode
    var anneesDisp = d.annees_disponibles || [];
    var yearFilter = d.annee_filtree || null;
    var epYearMode = window['_clubEpYearMode' + s] || 'filter';
    var cmpYears = window['_clubEpYearCmp' + s] || [];
    if (anneesDisp.length > 1) {
        html += '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;align-items:center;">';
        html += '<span style="color:#5a6580;font-size:11px;margin-right:4px;">Année :</span>';
        var isFilter = epYearMode === 'filter';
        var isCmp = epYearMode === 'compare';
        html += '<button onclick="_clubEpYearModeSet(\'filter\',\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isFilter?'#a29bfe':'#1e2a3a')+';background:'+(isFilter?'#a29bfe20':'transparent')+';color:'+(isFilter?'#a29bfe':'#5a6580')+';transition:all .2s;">Filtrer</button>';
        html += '<button onclick="_clubEpYearModeSet(\'compare\',\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isCmp?'#ffd700':'#1e2a3a')+';background:'+(isCmp?'#ffd70020':'transparent')+';color:'+(isCmp?'#ffd700':'#5a6580')+';transition:all .2s;">Comparer</button>';
        html += '</div>';
        var recentYears = anneesDisp.slice(-15).reverse();
        if (isFilter) {
            html += '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;align-items:center;">';
            var yrAllActive = !yearFilter;
            html += '<button onclick="_clubSetEpYear(null,\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(yrAllActive?'#a29bfe':'#1e2a3a')+';background:'+(yrAllActive?'#a29bfe20':'transparent')+';color:'+(yrAllActive?'#a29bfe':'#5a6580')+';transition:all .2s;">Tout</button>';
            recentYears.forEach(function(yr) {
                var isOn = yearFilter && yearFilter == yr;
                html += '<button onclick="_clubSetEpYear(' + yr + ',\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?'#a29bfe':'#1e2a3a')+';background:'+(isOn?'#a29bfe20':'transparent')+';color:'+(isOn?'#a29bfe':'#5a6580')+';transition:all .2s;">' + yr + '</button>';
            });
            if (anneesDisp.length > 15) {
                html += '<select onchange="_clubSetEpYear(this.value ? parseInt(this.value) : null,\'' + s + '\')" style="padding:3px 8px;border-radius:6px;font-size:11px;background:#0d1117;border:1px solid #1e2a3a;color:#5a6580;cursor:pointer;">';
                html += '<option value="">+ anciennes</option>';
                anneesDisp.slice(0, anneesDisp.length - 15).forEach(function(yr) {
                    html += '<option value="' + yr + '"' + (yearFilter == yr ? ' selected' : '') + '>' + yr + '</option>';
                });
                html += '</select>';
            }
            html += '</div>';
        } else {
            html += '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px;align-items:center;">';
            html += '<span style="color:#ffd700;font-size:11px;margin-right:4px;">Sélectionnez 2 à 5 années :</span>';
            recentYears.forEach(function(yr) {
                var isOn = cmpYears.indexOf(yr) !== -1;
                html += '<button onclick="_clubToggleEpYearCmp(' + yr + ',\'' + s + '\')" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?'#ffd700':'#1e2a3a')+';background:'+(isOn?'#ffd70020':'transparent')+';color:'+(isOn?'#ffd700':'#5a6580')+';transition:all .2s;">' + yr + '</button>';
            });
            if (anneesDisp.length > 15) {
                html += '<select onchange="if(this.value)_clubToggleEpYearCmp(parseInt(this.value),\'' + s + '\');this.selectedIndex=0;" style="padding:3px 8px;border-radius:6px;font-size:11px;background:#0d1117;border:1px solid #1e2a3a;color:#5a6580;cursor:pointer;">';
                html += '<option value="">+ anciennes</option>';
                anneesDisp.slice(0, anneesDisp.length - 15).forEach(function(yr) {
                    html += '<option value="' + yr + '">' + yr + (cmpYears.indexOf(yr)!==-1?' ✓':'') + '</option>';
                });
                html += '</select>';
            }
            html += '</div>';
            if (cmpYears.length >= 2) {
                html += '<div style="margin-bottom:12px;"><button onclick="_clubRunEpYearCmp(\'' + s + '\')" style="padding:6px 20px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;border:1px solid #ffd700;background:linear-gradient(135deg,#ffd700,#ffaa00);color:#000;transition:all .2s;">Comparer ' + cmpYears.sort().join(', ') + '</button></div>';
            }
            var cmpData = window['_clubEpYearCmpData' + s];
            if (cmpData && Object.keys(cmpData).length >= 2) {
                html += _buildEpYearCmpHTML(cmpData, s);
            }
        }
    }

    // Filtered events table
    var filteredEp = ep;
    if (discFilter) filteredEp = filteredEp.filter(function(e) { return discFilter.indexOf(e.discipline) !== -1; });
    if (nivFilter) filteredEp = filteredEp.filter(function(e) { return (e.niveaux||[]).some(function(n) { return nivFilter.indexOf(n) !== -1; }); });
    var thEp = '<tr><th>#</th><th>Épreuve</th><th style="color:#3b82f6;">Record ♂</th><th style="color:#3b82f6;">Par</th><th style="color:#3b82f6;">Année</th><th style="color:#ec4899;">Record ♀</th><th style="color:#ec4899;">Par</th><th style="color:#ec4899;">Année</th><th>Niveaux</th></tr>';
    html += '<div class="table-wrap">';
    html += '<table class="bk-table">' + thEp + '</table>';
    html += '<table class="bk-table">';
    var _lastDisc = '';
    var _rowNum = 0;
    filteredEp.forEach(function(e) {
        var _clubN = window._ctxClubName || '';
        if (e.discipline && e.discipline !== _lastDisc) {
            _lastDisc = e.discipline;
            var dc = e.disc_color || '#6b7280';
            html += '<tr><td colspan="9" style="background:' + dc + '15;border-left:3px solid ' + dc + ';padding:8px 14px;"><span style="color:' + dc + ';font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:1px;">' + escapeHtml(e.discipline) + '</span></td></tr>';
        }
        _rowNum++;
        html += '<tr><td>' + _rowNum + '</td>';
        html += '<td><b><a href="?page=recherche&epreuve=' + encodeURIComponent(e.epreuve) + '&club=' + encodeURIComponent(_clubN) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(e.epreuve) + '</a></b></td>';
        html += '<td>' + (e.best_perf_m ? '<span class="perf-val">' + escapeHtml(e.best_perf_m) + '</span>' : '<span style="color:#3a4560;">-</span>') + '</td>';
        html += '<td>' + (e.best_athlete_id_m ? '<a href="?page=profil&id=' + e.best_athlete_id_m + '" style="color:#3b82f6;text-decoration:none;">' + escapeHtml(e.best_athlete_m) + '</a>' : (e.best_athlete_m ? escapeHtml(e.best_athlete_m) : '<span style="color:#3a4560;">-</span>')) + '</td>';
        html += '<td style="color:#5a6580;font-size:12px;">' + (e.best_annee_m && e.best_annee_m != '0000' ? e.best_annee_m : '-') + '</td>';
        html += '<td>' + (e.best_perf_f ? '<span class="perf-val">' + escapeHtml(e.best_perf_f) + '</span>' : '<span style="color:#3a4560;">-</span>') + '</td>';
        html += '<td>' + (e.best_athlete_id_f ? '<a href="?page=profil&id=' + e.best_athlete_id_f + '" style="color:#ec4899;text-decoration:none;">' + escapeHtml(e.best_athlete_f) + '</a>' : (e.best_athlete_f ? escapeHtml(e.best_athlete_f) : '<span style="color:#3a4560;">-</span>')) + '</td>';
        html += '<td style="color:#5a6580;font-size:12px;">' + (e.best_annee_f && e.best_annee_f != '0000' ? e.best_annee_f : '-') + '</td>';
        html += '<td>' + _nivBadge(e.top_niveau) + '</td></tr>';
    });
    html += '</table>';
    html += '<table class="bk-table">' + thEp + '</table>';
    html += '</div>';

    // Pagination
    if (epPages > 1) {
        html += '<div class="pager" style="margin-top:12px;">';
        if (epPage > 1) html += '<a href="#" onclick="loadClubEpPage(' + (epPage - 1) + ',\'' + s + '\');return false;">Précédent</a> ';
        for (var pi = Math.max(1, epPage - 3); pi <= Math.min(epPages, epPage + 3); pi++) {
            if (pi === epPage) html += '<span class="current">' + pi + '</span> ';
            else html += '<a href="#" onclick="loadClubEpPage(' + pi + ',\'' + s + '\');return false;">' + pi + '</a> ';
        }
        if (epPage < epPages) html += '<a href="#" onclick="loadClubEpPage(' + (epPage + 1) + ',\'' + s + '\');return false;">Suivant</a>';
        html += ' <span class="info">(' + epPages + ' pages)</span>';
        html += '</div>';
    }
    return html;
}

// ---- Nationalites tab ----
function _renderClubTabNationalites(d, s, content) {
    var nat = d.nationalites || {};
    var keys = Object.keys(nat);
    if (keys.length === 0) { content.innerHTML = '<div class="loading-msg">Aucune nationalité renseignée</div>'; return false; }
    var totalNat = 0;
    keys.forEach(function(k) { totalNat += nat[k]; });
    var natMode = window['_clubNatMode' + s] || 'overview';
    var html = '';

    // Sub-tabs
    html += '<div style="display:flex;gap:8px;margin-bottom:14px;align-items:center;">';
    [{m:'overview',l:'&#127760; Vue d\'ensemble'},{m:'compare',l:'&#128200; Comparer'},{m:'resume',l:'&#128221; Résumé'}].forEach(function(t) {
        var active = natMode === t.m;
        html += '<button onclick="_clubSetNatMode(\'' + t.m + '\',\'' + s + '\')" style="padding:6px 16px;border-radius:8px;border:1px solid '+(active?'#a29bfe':'#1e2a3a')+';background:'+(active?'#a29bfe20':'transparent')+';color:'+(active?'#a29bfe':'#5a6580')+';font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;">' + t.l + '</button>';
    });
    html += '</div>';
    html += '<div style="margin-bottom:12px;color:#5a6580;font-size:13px;">' + keys.length + ' nationalités — ' + totalNat.toLocaleString('fr-FR') + ' athlètes</div>';

    if (natMode === 'overview') {
        html += '<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">';
        html += '<div style="flex:1;min-width:200px;max-width:300px;"><canvas id="clubNatDonut' + s + '"></canvas></div>';
        html += '<div style="flex:2;min-width:300px;"><canvas id="clubNatBar' + s + '"></canvas></div>';
        html += '</div>';
        html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">';
        keys.forEach(function(k) {
            var pct = totalNat > 0 ? Math.round(nat[k] / totalNat * 100) : 0;
            html += '<a href="?page=recherche&club=' + encodeURIComponent(d.club.nom_club) + '&nationalite=' + encodeURIComponent(k) + '" style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;background:#0d1525;border:1px solid #1a2540;border-radius:8px;color:#a29bfe;text-decoration:none;font-size:12px;transition:all .2s;" onmouseenter="this.style.borderColor=\'#6c5ce7\';this.style.background=\'#6c5ce715\'" onmouseleave="this.style.borderColor=\'#1a2540\';this.style.background=\'#0d1525\'">' + escapeHtml(k) + ' <span style="color:#8b949e;font-size:11px;">' + nat[k] + ' (' + pct + '%)</span></a>';
        });
        html += '</div>';
        var thNat = '<tr><th>#</th><th>Nationalité</th><th>Athlètes</th><th>%</th><th></th></tr>';
        html += '<div class="table-wrap">';
        html += '<table class="bk-table">' + thNat + '</table>';
        html += '<table class="bk-table">';
        keys.forEach(function(k, i) {
            var pct = totalNat > 0 ? Math.round(nat[k] / totalNat * 100) : 0;
            html += '<tr><td>' + (i+1) + '</td><td><b>' + escapeHtml(k) + '</b></td><td>' + nat[k].toLocaleString('fr-FR') + '</td><td><div style="display:flex;align-items:center;gap:6px;"><div style="width:60px;height:6px;background:#1a2540;border-radius:3px;"><div style="width:' + Math.min(pct,100) + '%;height:100%;background:#a78bfa;border-radius:3px;"></div></div><span style="font-size:12px;">' + pct + '%</span></div></td>';
            html += '<td><a href="?page=recherche&club=' + encodeURIComponent(d.club.nom_club) + '&nationalite=' + encodeURIComponent(k) + '" style="color:#a29bfe;text-decoration:none;font-size:12px;">Voir athlètes →</a></td></tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thNat + '</table>';
        html += '</div>';
    } else if (natMode === 'compare') {
        var selNats = window['_clubNatSel' + s] || [];
        var cmpData = window['_clubNatCmp' + s] || null;
        html += '<div style="margin-bottom:14px;"><span style="color:#8b949e;font-size:12px;">Sélectionnez les nationalités à comparer :</span></div>';
        html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">';
        keys.forEach(function(k) {
            var isOn = selNats.indexOf(k) !== -1;
            html += '<button onclick="_clubToggleNatSel(\'' + escapeHtml(k) + '\',\'' + s + '\')" style="padding:5px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?'#a29bfe':'#1e2a3a')+';background:'+(isOn?'#a29bfe25':'transparent')+';color:'+(isOn?'#a29bfe':'#5a6580')+';transition:all .2s;">' + escapeHtml(k) + ' (' + nat[k] + ')</button>';
        });
        html += '</div>';
        if (selNats.length >= 2) {
            if (!cmpData) {
                html += '<div class="loading-msg">Chargement de la comparaison...</div>';
            } else {
                var natColors = ['#a78bfa','#f472b6','#34d399','#fbbf24','#60a5fa','#fb923c','#e879f9','#22d3ee'];
                html += '<div style="margin-bottom:20px;"><canvas id="clubNatCmpBar' + s + '" height="120"></canvas></div>';
                var thCmp = '<tr><th></th>';
                cmpData.forEach(function(nd, ci) { thCmp += '<th style="color:' + natColors[ci % natColors.length] + ';">' + escapeHtml(nd.code) + '</th>'; });
                thCmp += '</tr>';
                html += '<div class="table-wrap">';
                html += '<table class="bk-table">' + thCmp + '</table>';
                html += '<table class="bk-table">';
                html += '<tr><td><b>Athlètes</b></td>';
                cmpData.forEach(function(nd) { html += '<td>' + nd.nb_athletes + '</td>'; });
                html += '</tr>';
                html += '<tr><td><b>Hommes</b></td>';
                cmpData.forEach(function(nd) { html += '<td>' + (nd.par_sexe['M'] || 0) + '</td>'; });
                html += '</tr><tr><td><b>Femmes</b></td>';
                cmpData.forEach(function(nd) { html += '<td>' + (nd.par_sexe['F'] || 0) + '</td>'; });
                html += '</tr>';
                html += '<tr><td><b>&#129351; Or</b></td>';
                cmpData.forEach(function(nd) { html += '<td>' + (nd.medailles.or || 0) + '</td>'; });
                html += '</tr><tr><td><b>&#129352; Argent</b></td>';
                cmpData.forEach(function(nd) { html += '<td>' + (nd.medailles.argent || 0) + '</td>'; });
                html += '</tr><tr><td><b>&#129353; Bronze</b></td>';
                cmpData.forEach(function(nd) { html += '<td>' + (nd.medailles.bronze || 0) + '</td>'; });
                html += '</tr>';
                html += '<tr><td><b>Meilleur niveau</b></td>';
                cmpData.forEach(function(nd) { html += '<td>' + _nivBadge(nd.meilleur_niveau) + '</td>'; });
                html += '</tr>';
                html += '</table>';
                html += '<table class="bk-table">' + thCmp + '</table>';
                html += '</div>';
                html += '<h4 style="color:#c9d1d9;margin:20px 0 10px;font-size:14px;">Répartition par catégorie</h4>';
                html += '<div style="margin-bottom:20px;"><canvas id="clubNatCmpCat' + s + '" height="160"></canvas></div>';
                html += '<h4 style="color:#c9d1d9;margin:20px 0 10px;font-size:14px;">Top épreuves</h4>';
                html += '<div style="display:flex;gap:16px;flex-wrap:wrap;">';
                cmpData.forEach(function(nd, ci) {
                    var clr = natColors[ci % natColors.length];
                    html += '<div style="flex:1;min-width:180px;background:#0d1525;border:1px solid ' + clr + '30;border-radius:10px;padding:12px;">';
                    html += '<div style="color:' + clr + ';font-weight:700;margin-bottom:8px;font-size:13px;">' + escapeHtml(nd.code) + '</div>';
                    if (nd.top_epreuves && nd.top_epreuves.length > 0) {
                        nd.top_epreuves.forEach(function(ep) {
                            html += '<div style="display:flex;justify-content:space-between;padding:3px 0;font-size:12px;color:#c9d1d9;border-bottom:1px solid #1a2540;"><span>' + escapeHtml(ep.epreuve) + '</span><span style="color:#8b949e;">' + ep.nb + '</span></div>';
                        });
                    } else {
                        html += '<div style="color:#5a6580;font-size:12px;">Aucune épreuve</div>';
                    }
                    html += '</div>';
                });
                html += '</div>';
            }
        } else if (selNats.length === 1) {
            html += '<div style="color:#5a6580;font-size:13px;padding:20px;text-align:center;">Sélectionnez au moins 2 nationalités pour comparer.</div>';
        }
    } else if (natMode === 'resume') {
        html += _buildResumeHTML(_buildNatResumeText(d));
    }
    return html;
}

// ---- Records tab ----
function _renderClubTabRecords(d, s, content) {
    var rec = d.records || [];
    var totalRec = d.total_records || rec.length;
    var recPage = d.rec_page || 1;
    var recPages = d.rec_pages || 1;
    if (rec.length === 0 && recPage === 1) { content.innerHTML = '<div class="loading-msg">Aucun record trouvé</div>'; return false; }

    var html = '';
    var recDiscMap = {};
    rec.forEach(function(r) { if (r.discipline && r.disc_color) recDiscMap[r.discipline] = r.disc_color; });
    var recDiscKeys = Object.keys(recDiscMap);
    var recDiscFilter = window['_clubRecDiscFilter' + s] || null;
    if (recDiscKeys.length >= 1) {
        html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">';
        var allActive = !recDiscFilter;
        html += '<button onclick="_clubToggleRecDisc(null,\'' + s + '\')" style="padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(allActive?'#a29bfe':'#1e2a3a')+';background:'+(allActive?'#a29bfe20':'transparent')+';color:'+(allActive?'#a29bfe':'#5a6580')+';transition:all .2s;">Tout</button>';
        recDiscKeys.forEach(function(dk) {
            var dc = recDiscMap[dk];
            var isOn = recDiscFilter && recDiscFilter.indexOf(dk) !== -1;
            html += '<button onclick="_clubToggleRecDisc(\'' + dk + '\',\'' + s + '\')" style="padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid '+(isOn?dc:'#1e2a3a')+';background:'+(isOn?dc+'20':'transparent')+';color:'+(isOn?dc:'#5a6580')+';transition:all .2s;">' + escapeHtml(dk) + '</button>';
        });
        html += '</div>';
    }
    var filteredRec = recDiscFilter ? rec.filter(function(r) { return recDiscFilter.indexOf(r.discipline) !== -1; }) : rec;

    html += '<div style="margin-bottom:12px;color:#5a6580;font-size:13px;">' + totalRec.toLocaleString('fr-FR') + ' records au total — Page ' + recPage + '/' + recPages + (recDiscFilter ? ' (filtre: ' + filteredRec.length + ' affichés)' : '') + '</div>';

    var thRec = '<tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>Épreuve</th><th>Discipline</th><th>Performance</th><th>Niveaux</th><th>Date</th><th></th></tr>';
    html += '<div class="table-wrap">';
    html += '<table class="bk-table">' + thRec + '</table>';
    html += '<table class="bk-table">';
    filteredRec.forEach(function(r, i) {
        var inB = r.athlete_id ? isAthleteInBasket(r.athlete_id) : false;
        html += '<tr><td>' + ((recPage - 1) * 10 + i + 1) + '</td>';
        html += '<td><b>' + (r.athlete_id ? '<a href="?page=profil&id=' + r.athlete_id + '&s=records" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(r.athlete) + '</a>' : escapeHtml(r.athlete)) + '</b></td>';
        var _clubR = window._ctxClubName || '';
        html += '<td><a href="?page=recherche&categorie=' + encodeURIComponent(r.categorie||'') + '&club=' + encodeURIComponent(_clubR) + '" style="text-decoration:none;"><span class="badge badge-cat">' + escapeHtml(r.categorie || '-') + '</span></a></td>';
        html += '<td><a href="?page=recherche&sexe=' + encodeURIComponent(r.sexe||'') + '&club=' + encodeURIComponent(_clubR) + '" style="text-decoration:none;">' + escapeHtml(r.sexe || '-') + '</a></td>';
        html += '<td><a href="?page=recherche&epreuve=' + encodeURIComponent(r.epreuve||'') + '&club=' + encodeURIComponent(_clubR) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(r.epreuve || '-') + '</a></td>';
        html += '<td>' + (r.disc_color ? '<span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:' + r.disc_color + '20;color:' + r.disc_color + ';border:1px solid ' + r.disc_color + '40;">' + escapeHtml(r.discipline || '') + '</span>' : '-') + '</td>';
        html += '<td><span class="perf-val">' + escapeHtml(r.performance || '-') + '</span></td>';
        html += '<td>' + _nivBadge(r.top_niveau || _highestNiveau(r.niveaux)) + '</td>';
        html += '<td>' + dateFR(r.date || '-') + '</td>';
        html += '<td>' + (r.athlete_id ? '<button class="btn-cmp-add' + (inB ? ' added' : '') + '" data-cmp-ath="' + r.athlete_id + '" data-name="' + escapeHtml(r.athlete) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">' + (inB ? '\u2713' : '+') + '</button>' : '') + '</td></tr>';
    });
    html += '</table>';
    html += '<table class="bk-table">' + thRec + '</table>';
    html += '</div>';

    if (recPages > 1) {
        html += '<div class="pager" style="margin-top:12px;">';
        if (recPage > 1) html += '<a href="#" onclick="loadClubRecPage(' + (recPage - 1) + ',\'' + s + '\');return false;">Précédent</a> ';
        for (var pi = Math.max(1, recPage - 3); pi <= Math.min(recPages, recPage + 3); pi++) {
            if (pi === recPage) html += '<span class="current">' + pi + '</span> ';
            else html += '<a href="#" onclick="loadClubRecPage(' + pi + ',\'' + s + '\');return false;">' + pi + '</a> ';
        }
        if (recPage < recPages) html += '<a href="#" onclick="loadClubRecPage(' + (recPage + 1) + ',\'' + s + '\');return false;">Suivant</a>';
        html += ' <span class="info">(' + recPages + ' pages)</span>';
        html += '</div>';
    }
    return html;
}

// ---- Performances tab ----
function _renderClubTabPerformances(d, s, content) {
    var perfs = d.performances || [];
    var totalPerfs = d.total_performances || perfs.length;
    var perfPage = d.perf_page || 1;
    var perfPages = d.perf_pages || 1;
    var perfMode = window['_clubPerfMode' + s] || 'all';
    var html = '';

    if (perfs.length === 0 && perfPage === 1) {
        html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">';
        ['all','perso'].forEach(function(m) {
            var labels = {all:'Toutes les épreuves', perso:'Records personnels'};
            var icons = {all:'&#127942;', perso:'&#128100;'};
            var active = perfMode === m;
            html += '<button onclick="_clubSetPerfMode(\'' + m + '\',\'' + s + '\')" style="padding:6px 16px;border-radius:8px;border:1px solid '+(active?'#a29bfe':'#1e2a3a')+';background:'+(active?'#a29bfe20':'transparent')+';color:'+(active?'#a29bfe':'#5a6580')+';font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;">' + icons[m] + ' ' + labels[m] + '</button>';
        });
        html += '</div>';
        html += '<div class="loading-msg">Aucune performance trouvée</div>';
        content.innerHTML = html;
        return false;
    }

    html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">';
    ['all','perso'].forEach(function(m) {
        var labels = {all:'Toutes les épreuves', perso:'Records personnels'};
        var icons = {all:'&#127942;', perso:'&#128100;'};
        var active = perfMode === m;
        html += '<button onclick="_clubSetPerfMode(\'' + m + '\',\'' + s + '\')" style="padding:6px 16px;border-radius:8px;border:1px solid '+(active?'#a29bfe':'#1e2a3a')+';background:'+(active?'#a29bfe20':'transparent')+';color:'+(active?'#a29bfe':'#5a6580')+';font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;">' + icons[m] + ' ' + labels[m] + '</button>';
    });
    html += '<span style="color:#5a6580;font-size:13px;margin-left:auto;">' + totalPerfs.toLocaleString('fr-FR') + ' ' + (perfMode === 'perso' ? 'records' : 'performances') + ' — Page ' + perfPage + '/' + perfPages + '</span>';
    html += '</div>';

    var thPerf = '<tr><th>#</th><th>Athlète</th><th>Cat</th><th>Épreuve</th><th>Performance</th><th>Niveau</th><th>Place</th><th>Ville</th><th>Date</th><th></th></tr>';
    html += '<div class="table-wrap">';
    html += '<table class="bk-table">' + thPerf + '</table>';
    html += '<table class="bk-table">';
    var _clubP = window._ctxClubName || '';
    perfs.forEach(function(p, i) {
        var inB = p.athlete_id ? isAthleteInBasket(p.athlete_id) : false;
        html += '<tr><td>' + ((perfPage - 1) * 20 + i + 1) + '</td>';
        html += '<td><b>' + (p.athlete_id ? '<a href="?page=profil&id=' + p.athlete_id + '&s=resultats" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(p.athlete) + '</a>' : escapeHtml(p.athlete)) + '</b></td>';
        html += '<td><a href="?page=recherche&categorie=' + encodeURIComponent(p.categorie||'') + '&club=' + encodeURIComponent(_clubP) + '" style="text-decoration:none;"><span class="badge badge-cat">' + escapeHtml(p.categorie || '-') + '</span></a></td>';
        html += '<td><a href="?page=recherche&epreuve=' + encodeURIComponent(p.epreuve||'') + '&club=' + encodeURIComponent(_clubP) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(p.epreuve || '-') + '</a></td>';
        html += '<td><span class="perf-val">' + escapeHtml(p.performance || '-') + '</span></td>';
        html += '<td>' + _nivBadge(p.niveau) + '</td>';
        html += '<td>' + escapeHtml(p.place || '-') + '</td>';
        html += '<td>' + (p.ville ? '<a href="?page=villes&open=' + encodeURIComponent(p.ville) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(p.ville) + '</a>' : '-') + '</td>';
        html += '<td>' + dateFR(p.date || '-') + '</td>';
        html += '<td>' + (p.athlete_id ? '<button class="btn-cmp-add' + (inB ? ' added' : '') + '" data-cmp-ath="' + p.athlete_id + '" data-name="' + escapeHtml(p.athlete) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">' + (inB ? '\u2713' : '+') + '</button>' : '') + '</td></tr>';
    });
    html += '</table>';
    html += '<table class="bk-table">' + thPerf + '</table>';
    html += '</div>';

    if (perfPages > 1) {
        html += '<div class="pager" style="margin-top:12px;">';
        if (perfPage > 1) html += '<a href="#" onclick="loadClubPerfsPage(' + (perfPage - 1) + ',\'' + s + '\');return false;">Précédent</a> ';
        for (var pi = Math.max(1, perfPage - 3); pi <= Math.min(perfPages, perfPage + 3); pi++) {
            if (pi === perfPage) html += '<span class="current">' + pi + '</span> ';
            else html += '<a href="#" onclick="loadClubPerfsPage(' + pi + ',\'' + s + '\');return false;">' + pi + '</a> ';
        }
        if (perfPage < perfPages) html += '<a href="#" onclick="loadClubPerfsPage(' + (perfPage + 1) + ',\'' + s + '\');return false;">Suivant</a>';
        html += ' <span class="info">(' + perfPages + ' pages)</span>';
        html += '</div>';
    }
    return html;
}

// ---- Stats tab ----
function _renderClubTabStats(d, s) {
    var sexe = d.par_sexe || {};
    var cats = d.par_categorie || {};
    var rpa = d.resultats_par_annee || [];
    var pod = d.podiums || {};
    var totalPod = d.total_podiums || 0;
    var med = d.medailles || {};
    var totalMed = (med.or || 0) + (med.argent || 0) + (med.bronze || 0);
    var sel = d.selections || {};
    var html = '';

    html += '<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">';
    html += '<div style="flex:1;min-width:200px;max-width:300px;"><h4 style="color:#8b949e;font-size:13px;margin:0 0 8px;">Répartition par sexe</h4><canvas id="clubSexeChart' + s + '"></canvas></div>';
    html += '<div style="flex:2;min-width:300px;"><h4 style="color:#8b949e;font-size:13px;margin:0 0 8px;">Catégories</h4><canvas id="clubCatChart' + s + '"></canvas></div>';
    html += '</div>';

    if (totalMed > 0 || totalPod > 0) {
        html += '<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">';
        if (totalMed > 0) {
            html += '<div style="flex:1;min-width:150px;text-align:center;padding:14px;background:#f59e0b10;border:1px solid #f59e0b30;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#fbbf24;">' + (med.or || 0) + '</div><div style="font-size:11px;color:#8b949e;">Or</div></div>';
            html += '<div style="flex:1;min-width:150px;text-align:center;padding:14px;background:#94a3b810;border:1px solid #94a3b830;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#94a3b8;">' + (med.argent || 0) + '</div><div style="font-size:11px;color:#8b949e;">Argent</div></div>';
            html += '<div style="flex:1;min-width:150px;text-align:center;padding:14px;background:#b4540010;border:1px solid #b4540030;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#cd7f32;">' + (med.bronze || 0) + '</div><div style="font-size:11px;color:#8b949e;">Bronze</div></div>';
        }
        if (totalPod > 0) {
            html += '<div style="flex:1;min-width:150px;text-align:center;padding:14px;background:#10b98110;border:1px solid #10b98130;border-radius:12px;"><div style="font-size:24px;font-weight:700;color:#34d399;">' + totalPod + '</div><div style="font-size:11px;color:#8b949e;">Podiums</div></div>';
        }
        html += '</div>';
    }

    if (sel.nb_selections > 0) {
        html += '<div style="margin-bottom:16px;padding:12px;background:#6366f110;border:1px solid #6366f130;border-radius:10px;display:flex;gap:20px;flex-wrap:wrap;">';
        html += '<div><span style="font-size:20px;font-weight:700;color:#818cf8;">' + sel.nb_athletes + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">athlètes sélectionnés</span></div>';
        html += '<div><span style="font-size:20px;font-weight:700;color:#818cf8;">' + sel.nb_selections + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">sélections nationales</span></div>';
        if (sel.nb_competitions > 0) html += '<div><span style="font-size:20px;font-weight:700;color:#818cf8;">' + sel.nb_competitions + '</span><span style="color:#8b949e;font-size:12px;margin-left:6px;">compétitions</span></div>';
        html += '</div>';
    }

    if (rpa.length > 1) {
        html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Évolution par année</h4>';
        html += '<canvas id="clubEvoChart' + s + '" style="max-height:250px;"></canvas>';
    }

    var tv = d.top_villes || [];
    if (tv.length > 0) {
        html += '<h4 style="color:#8b949e;font-size:13px;margin:16px 0 8px;">Principaux lieux de compétition</h4>';
        var _tvTh = '<tr><th>#</th><th>Ville</th><th>Résultats</th><th>Athlètes</th></tr>';
        html += '<div class="table-wrap"><table class="bk-table">' + _tvTh + '</table><table class="bk-table">';
        tv.forEach(function(v, i) {
            html += '<tr><td>' + (i+1) + '</td><td><a href="?page=villes&open=' + encodeURIComponent(v.ville) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(v.ville) + '</a></td><td>' + v.nb_resultats + '</td><td>' + v.nb_athletes + '</td></tr>';
        });
        html += '</table><table class="bk-table">' + _tvTh + '</table></div>';
    }

    // Competition level curves
    var nivCounts = {};
    var nivOrd = {IE:100,IR:99};
    ['N','R','D'].forEach(function(p){var b={N:90,R:80,D:70}[p];for(var i=1;i<=8;i++) nivOrd[p+i]=b-i;});
    var epForNiv = d.epreuves || [];
    epForNiv.forEach(function(e) { (e.niveaux || []).forEach(function(n) { if (n && nivOrd[n]) nivCounts[n] = (nivCounts[n]||0) + 1; }); });
    var nivChartKeys = Object.keys(nivCounts).sort(function(a,b){ return (nivOrd[a]||0) - (nivOrd[b]||0); });
    var nivParAnnee = d.niveaux_par_annee || [];
    if (nivChartKeys.length > 2) {
        html += '<div style="margin-bottom:16px;background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;">';
        html += '<h4 style="margin:0 0 8px;color:#c9d1d9;font-size:13px;">Distribution des niveaux de compétition</h4>';
        html += '<canvas id="clubNivChart' + s + '" height="200"></canvas>';
        html += '</div>';
    }
    if (nivParAnnee.length > 1) {
        html += '<div style="margin-bottom:16px;background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;">';
        html += '<h4 style="margin:0 0 8px;color:#c9d1d9;font-size:13px;">\u00c9volution des niveaux par ann\u00e9e</h4>';
        html += '<canvas id="clubNivYearChart' + s + '" height="200"></canvas>';
        html += '</div>';
    }
    return html;
}

// ---- Resume tab ----
function _renderClubTabResume(d, s) {
    var mode = window['_clubResumeMode' + s] || 'global';
    var anneesDisp = d.annees_disponibles || [];
    var html = '';

    html += '<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">';
    ['global','annee','comparer'].forEach(function(m) {
        var labels = {global:'Global',annee:'Par annee',comparer:'Comparer'};
        var active = mode === m;
        html += '<button onclick="_clubSetResumeMode(\'' + m + '\',\'' + s + '\')" style="padding:8px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid ' + (active ? '#6c5ce7' : '#1a2540') + ';background:' + (active ? 'linear-gradient(135deg,#6c5ce7,#5541d0)' : '#080c14') + ';color:' + (active ? '#fff' : '#8b949e') + ';">' + labels[m] + '</button>';
    });
    html += '</div>';

    if (mode === 'global') {
        html += _buildResumeHTML(_buildResumeText(d, null));
    } else if (mode === 'annee') {
        var selYear = window['_clubResumeYear' + s];
        html += '<div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;">';
        html += '<select id="clubYearSelect' + s + '" onchange="_clubYearChanged(\'' + s + '\')" style="padding:8px 14px;background:#080c14;border:1px solid #1a2540;border-radius:8px;color:#d0d7e0;font-size:14px;">';
        html += '<option value="">-- Choisir une annee --</option>';
        anneesDisp.forEach(function(y) {
            html += '<option value="' + y + '"' + (selYear == y ? ' selected' : '') + '>' + y + '</option>';
        });
        html += '</select>';
        html += '<span id="clubYearLoading' + s + '" style="color:#5a6580;font-size:13px;display:none;">Chargement...</span>';
        html += '</div>';
        html += '<div id="clubYearResume' + s + '">';
        if (selYear && window['_clubYearDataCache' + s][selYear]) {
            html += _buildResumeHTML(_buildResumeText(window['_clubYearDataCache' + s][selYear], selYear));
        } else if (!selYear) {
            html += '<div style="color:#5a6580;text-align:center;padding:40px;">Selectionnez une annee pour afficher le resume</div>';
        }
        html += '</div>';
    } else if (mode === 'comparer') {
        var selYears = window['_clubCompareYears' + s] || [];
        html += '<div style="margin-bottom:16px;">';
        html += '<p style="color:#8b949e;font-size:13px;margin-bottom:10px;">Selectionnez jusqu\'a 6 annees a comparer :</p>';
        html += '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">';
        anneesDisp.forEach(function(y) {
            var checked = selYears.indexOf(y) !== -1;
            html += '<label style="display:flex;align-items:center;gap:4px;padding:6px 12px;background:' + (checked ? '#6c5ce715' : '#080c14') + ';border:1px solid ' + (checked ? '#6c5ce7' : '#1a2540') + ';border-radius:6px;cursor:pointer;color:' + (checked ? '#a29bfe' : '#8b949e') + ';font-size:13px;">';
            html += '<input type="checkbox" value="' + y + '" ' + (checked ? 'checked' : '') + ' onchange="_clubToggleCompareYear(' + y + ',this.checked,\'' + s + '\')" style="accent-color:#6c5ce7;"> ' + y;
            html += '</label>';
        });
        html += '</div>';
        html += '<button onclick="_clubRunCompare(\'' + s + '\')" style="padding:8px 24px;background:linear-gradient(135deg,#6c5ce7,#5541d0);border:none;border-radius:8px;color:#fff;font-size:13px;font-weight:600;cursor:pointer;"' + (selYears.length < 2 ? ' disabled' : '') + '>Comparer</button>';
        html += '<span style="color:#5a6580;font-size:12px;margin-left:10px;">' + selYears.length + '/6 annees</span>';
        html += '</div>';
        html += '<div id="clubCompareResult' + s + '"></div>';
    }
    return html;
}

// ============================================================
// Post-render charts for all tabs
// ============================================================
function _postRenderClubCharts(tab, d, s) {
    if (tab === 'epreuves') {
        var _cmpChartEl = document.getElementById('clubEpYearCmpChart' + s);
        var _cmpData = window['_clubEpYearCmpData' + s];
        if (_cmpChartEl && _cmpData) {
            var _cmpYrs = Object.keys(_cmpData).map(Number).sort();
            var _cmpColors = ['#6c5ce7','#00cec9','#fdcb6e','#e17055','#55efc4'];
            var _cmpDS = [
                { label:'Épreuves', key:'total_epreuves' },
                { label:'Athlètes', key:'total_athletes' },
                { label:'Records', key:'total_records' },
                { label:'Résultats', key:'nb_resultats' }
            ];
            new Chart(_cmpChartEl, {
                type: 'bar',
                data: {
                    labels: _cmpYrs.map(String),
                    datasets: _cmpDS.map(function(ds, di) {
                        return {
                            label: ds.label,
                            data: _cmpYrs.map(function(y) { return _cmpData[y][ds.key] || 0; }),
                            backgroundColor: _cmpColors[di]
                        };
                    })
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position:'bottom', labels: { color:'#8b949e', padding:12, font:{size:11} } } },
                    scales: {
                        x: { grid:{color:'#1e2a3a'}, ticks:{color:'#8b949e'} },
                        y: { beginAtZero:true, grid:{color:'#1e2a3a'}, ticks:{color:'#5a6580'} }
                    }
                }
            });
        }
    }
    if (tab === 'nationalites') {
        var _nat = d.nationalites || {};
        var _nk = Object.keys(_nat);
        var _totalN = 0;
        _nk.forEach(function(k) { _totalN += _nat[k]; });
        var _colors = ['#3b82f6','#ec4899','#8b5cf6','#f59e0b','#10b981','#ef4444','#06b6d4','#f97316','#84cc16','#6366f1','#64748b'];
        var _dc = document.getElementById('clubNatDonut' + s);
        if (_dc && _nk.length > 0) {
            var _top10 = _nk.slice(0, 10);
            var _otherC = 0;
            _nk.slice(10).forEach(function(k) { _otherC += _nat[k]; });
            var _lbl = _top10.map(function(k) { return k; });
            var _dt = _top10.map(function(k) { return _nat[k]; });
            if (_otherC > 0) { _lbl.push('Autres'); _dt.push(_otherC); }
            new Chart(_dc, {
                type: 'doughnut',
                data: { labels: _lbl, datasets: [{ data: _dt, backgroundColor: _colors.slice(0, _lbl.length), borderWidth: 0 }] },
                options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, font: { size: 10 }, color: '#8b949e' } } } }
            });
        }
        var _bc = document.getElementById('clubNatBar' + s);
        if (_bc && _nk.length > 0) {
            var _top15 = _nk.slice(0, 15);
            new Chart(_bc, {
                type: 'bar',
                data: { labels: _top15, datasets: [{ data: _top15.map(function(k) { return _nat[k]; }), backgroundColor: '#a78bfa', borderRadius: 4, barThickness: 16 }] },
                options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { display: false }, ticks: { color: '#c8cfd8' } } } }
            });
        }
        var _cmpD = window['_clubNatCmp' + s];
        var _cmpBar = document.getElementById('clubNatCmpBar' + s);
        if (_cmpBar && _cmpD) {
            var _natCl = ['#a78bfa','#f472b6','#34d399','#fbbf24','#60a5fa','#fb923c','#e879f9','#22d3ee'];
            new Chart(_cmpBar, {
                type: 'bar',
                data: { labels: _cmpD.map(function(n){return n.code;}), datasets: [{ label:'Athlètes', data: _cmpD.map(function(n){return n.nb_athletes;}), backgroundColor: _cmpD.map(function(_,i){return _natCl[i%_natCl.length];}), borderRadius:4, barThickness:28 }] },
                options: { responsive:true, plugins:{legend:{display:false}}, scales:{ x:{grid:{color:'#1e2a3a'},ticks:{color:'#c8cfd8',font:{weight:'bold'}}}, y:{grid:{color:'#1e2a3a'},ticks:{color:'#8b949e'},beginAtZero:true} } }
            });
        }
        var _cmpCat = document.getElementById('clubNatCmpCat' + s);
        if (_cmpCat && _cmpD) {
            var _natCl2 = ['#a78bfa','#f472b6','#34d399','#fbbf24','#60a5fa','#fb923c','#e879f9','#22d3ee'];
            var allCats = {};
            _cmpD.forEach(function(n) { Object.keys(n.par_categorie||{}).forEach(function(c) { allCats[c]=1; }); });
            var catKeys = Object.keys(allCats);
            new Chart(_cmpCat, {
                type: 'bar',
                data: { labels: catKeys, datasets: _cmpD.map(function(n,i) { return { label:n.code, data:catKeys.map(function(c){return (n.par_categorie||{})[c]||0;}), backgroundColor:_natCl2[i%_natCl2.length], borderRadius:3 }; }) },
                options: { responsive:true, plugins:{legend:{labels:{color:'#8b949e'}}}, scales:{ x:{grid:{color:'#1e2a3a'},ticks:{color:'#8b949e'}}, y:{grid:{color:'#1e2a3a'},ticks:{color:'#8b949e'},beginAtZero:true} } }
            });
        }
    }
    if (tab === 'stats') {
        var _sexe = d.par_sexe || {};
        var _cats = d.par_categorie || {};
        var _rpa = (d.resultats_par_annee || []).slice().reverse();
        var _sc = document.getElementById('clubSexeChart' + s);
        if (_sc) {
            var _sk = Object.keys(_sexe);
            new Chart(_sc, {
                type: 'doughnut',
                data: { labels: _sk.map(function(k){return k==='M'?'Hommes':(k==='F'?'Femmes':k);}), datasets: [{ data: _sk.map(function(k){return _sexe[k];}), backgroundColor: ['#3b82f6','#ec4899','#64748b'], borderWidth: 0 }] },
                options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 8, usePointStyle: true, font: { size: 10 }, color: '#8b949e' } } } }
            });
        }
        var _cc = document.getElementById('clubCatChart' + s);
        if (_cc) {
            var _ck = Object.keys(_cats).slice(0, 12);
            new Chart(_cc, {
                type: 'bar',
                data: { labels: _ck, datasets: [{ data: _ck.map(function(k){return _cats[k];}), backgroundColor: '#34d399', borderRadius: 4, barThickness: 16 }] },
                options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { display: false }, ticks: { color: '#c8cfd8' } } } }
            });
        }
        var _ec = document.getElementById('clubEvoChart' + s);
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
        var _ep = d.epreuves || [];
        var _nivC = {}, _nivO = {IE:100,IR:99};
        ['N','R','D'].forEach(function(p){var b={N:90,R:80,D:70}[p];for(var i=1;i<=8;i++) _nivO[p+i]=b-i;});
        _ep.forEach(function(e) { (e.niveaux||[]).forEach(function(n){ if(n&&_nivO[n]) _nivC[n]=(_nivC[n]||0)+1; }); });
        var _nck = Object.keys(_nivC).sort(function(a,b){ return (_nivO[a]||0)-(_nivO[b]||0); });
        var _cvs = document.getElementById('clubNivChart' + s);
        if (_cvs && _nck.length > 2) {
            var _clrs = _nck.map(function(k){ var c=k.charAt(0); return c==='I'?'#e879f9': c==='N'?'#fb7185': c==='R'?'#22d3ee': '#fb923c'; });
            new Chart(_cvs, {
                type: 'line',
                data: { labels: _nck, datasets: [{ label: '\u00c9preuves', data: _nck.map(function(k){ return _nivC[k]; }), borderColor: '#a29bfe', backgroundColor: '#a29bfe15', tension: 0.4, fill: true, pointBackgroundColor: _clrs, pointBorderColor: _clrs, pointRadius: 5, pointHoverRadius: 8, borderWidth: 2.5 }] },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return ctx.parsed.y + ' \u00e9preuve' + (ctx.parsed.y > 1 ? 's' : ''); } } } },
                    scales: {
                        x: { grid: { color: '#1e2a3a' }, ticks: { color: function(ctx) { var lbl=ctx.tick.label||'';var c=lbl.charAt(0);return c==='I'?'#e879f9':c==='N'?'#fb7185':c==='R'?'#22d3ee':'#fb923c'; }, font: { weight:'bold', size:11 } } },
                        y: { beginAtZero: true, grid: { color: '#1e2a3a' }, ticks: { color: '#5a6580', stepSize: 1 } }
                    }
                }
            });
        }
        var _npa = d.niveaux_par_annee || [];
        var _ycvs = document.getElementById('clubNivYearChart' + s);
        if (_ycvs && _npa.length > 1) {
            var _yLabels = _npa.map(function(r){ return r.annee; });
            var _families = [
                { key:'D', label:'D\u00e9partemental', color:'#fb923c', bg:'#fb923c20' },
                { key:'R', label:'R\u00e9gional', color:'#22d3ee', bg:'#22d3ee20' },
                { key:'N', label:'National', color:'#fb7185', bg:'#fb718520' },
                { key:'I', label:'International', color:'#e879f9', bg:'#e879f920' }
            ];
            var _yds = [];
            _families.forEach(function(f) {
                var hasData = _npa.some(function(r){ return (r[f.key]||0) > 0; });
                if (hasData) {
                    _yds.push({ label:f.label, data:_npa.map(function(r){return r[f.key]||0;}), borderColor:f.color, backgroundColor:f.bg, tension:0.4, fill:false, pointRadius:4, pointHoverRadius:7, borderWidth:2.5 });
                }
            });
            new Chart(_ycvs, {
                type: 'line',
                data: { labels: _yLabels, datasets: _yds },
                options: {
                    responsive: true, interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position:'bottom', labels: { padding:12, usePointStyle:true, pointStyleWidth:10, font:{size:11}, color:'#8b949e' } } },
                    scales: {
                        x: { grid:{color:'#1e2a3a'}, ticks:{color:'#8b949e', font:{size:11}} },
                        y: { beginAtZero:true, grid:{color:'#1e2a3a'}, ticks:{color:'#5a6580'} }
                    }
                }
            });
        }
    }
}

// ============================================================
// Resume text builders
// ============================================================
function _buildResumeText(d, annee) {
    var txt = [];
    var nom = d.club.nom_club;
    var nbAth = d.total_athletes || 0;
    var sexe = d.par_sexe || {};
    var cats = d.par_categorie || {};
    var nats = d.nationalites || {};
    var med = d.medailles || { or: 0, argent: 0, bronze: 0 };
    var medDetail = d.medailles_detail || [];
    var pod = d.podiums || {};
    var totalPod = d.total_podiums || 0;
    var podNiv = d.podium_niveaux || [];
    var sel = d.selections || {};
    var ep = d.epreuves || [];
    var rec = d.records || [];
    var totalRec = d.total_records || 0;
    var topAth = d.top_athletes || [];
    var topVilles = d.top_villes || [];
    var prog = d.progressions || {};
    var nivRes = d.niveaux_resultats || [];
    var nbResGlobal = d.nb_resultats_global || 0;
    var nbEpGlobal = d.nb_epreuves_global || 0;
    var anDebut = d.annee_debut;
    var anFin = d.annee_fin;
    var anDispo = d.annees_disponibles || [];
    var nivData = d.niveaux || [];
    var bestNiv = d.meilleur_niveau;
    var nivMap = {N1:'National 1 (\u00c9lite)',N2:'National 2',N3:'National 3',N4:'National 4',R1:'R\u00e9gional 1',R2:'R\u00e9gional 2',R3:'R\u00e9gional 3',R4:'R\u00e9gional 4',R5:'R\u00e9gional 5',R6:'R\u00e9gional 6',D1:'D\u00e9partemental 1',D2:'D\u00e9partemental 2',D3:'D\u00e9partemental 3',D4:'D\u00e9partemental 4',D5:'D\u00e9partemental 5',D6:'D\u00e9partemental 6',D7:'D\u00e9partemental 7',D8:'D\u00e9partemental 8',IR:'Interr\u00e9gional',IE:'International \u00c9lite'};
    var catMap = {SE:'S\u00e9niors',ES:'Espoirs',JU:'Juniors',CA:'Cadets',MI:'Minimes',BE:'Benjamins',PO:'Poussins',EA:'\u00c9cole d\'athl\u00e9tisme',V1:'V\u00e9t\u00e9rans 1',V2:'V\u00e9t\u00e9rans 2',V3:'V\u00e9t\u00e9rans 3',V4:'V\u00e9t\u00e9rans 4'};
    function _n(v) { return v ? v.toLocaleString('fr-FR') : '0'; }
    function _pl(n, s, p) { return n > 1 ? (p || s + 's') : s; }

    if (annee) {
        txt.push('En ' + annee + ', le ' + nom + ' comptait ' + _n(nbAth) + ' athl\u00e8te' + _pl(nbAth, '') + ' actif' + _pl(nbAth, '') + '.');
    } else {
        var intro = 'Le ' + nom + ' est un club d\'athl\u00e9tisme';
        if (anDebut && anFin && anFin !== anDebut) intro += ' actif de ' + anDebut + ' \u00e0 ' + anFin + ', soit ' + (anFin - anDebut + 1) + ' saisons d\'activit\u00e9';
        else if (anDebut && anFin && anFin === anDebut) intro += ' dont l\'activit\u00e9 se limite \u00e0 la saison ' + anDebut;
        else if (anDebut) intro += ' en activit\u00e9 depuis ' + anDebut;
        intro += '. Il totalise ' + _n(nbAth) + ' athl\u00e8te' + _pl(nbAth, '') + ' enregistr\u00e9' + _pl(nbAth, '') + '.';
        txt.push(intro);
    }

    if (nbAth > 0) {
        var pEff = '';
        var sParts = [];
        if (sexe['M']) { var pctM = Math.round(sexe['M'] / nbAth * 100); sParts.push(_n(sexe['M']) + ' hommes (' + pctM + '%)'); }
        if (sexe['F']) { var pctF = Math.round(sexe['F'] / nbAth * 100); sParts.push(_n(sexe['F']) + ' femmes (' + pctF + '%)'); }
        if (sParts.length > 0) pEff = 'La r\u00e9partition par sexe compte ' + sParts.join(' et ') + '.';
        if (pEff) txt.push(pEff);
    }

    var catKeys = Object.keys(cats);
    if (catKeys.length > 0) {
        var totalCatAth = 0;
        catKeys.forEach(function(k) { totalCatAth += cats[k]; });
        if (catKeys.length <= 5) {
            var catParts = catKeys.map(function(k) { return (catMap[k] || k) + ' (' + _n(cats[k]) + ', ' + Math.round(cats[k]/totalCatAth*100) + '%)'; });
            txt.push('Les athl\u00e8tes se r\u00e9partissent en ' + catKeys.length + ' cat\u00e9gories : ' + catParts.join(', ') + '.');
        } else {
            var topCats = catKeys.slice(0, 4).map(function(k) { return (catMap[k] || k) + ' (' + _n(cats[k]) + ', ' + Math.round(cats[k]/totalCatAth*100) + '%)'; });
            txt.push('Le club couvre ' + catKeys.length + ' cat\u00e9gories d\'\u00e2ge. Les plus repr\u00e9sent\u00e9es sont : ' + topCats.join(', ') + '.');
        }
    }

    var natKeys = Object.keys(nats);
    if (natKeys.length > 0) {
        var totalNatAth = 0;
        natKeys.forEach(function(k) { totalNatAth += nats[k]; });
        if (natKeys.length === 1) txt.push('Les athl\u00e8tes sont de nationalit\u00e9 ' + natKeys[0] + ' (' + _n(nats[natKeys[0]]) + ').');
        else if (natKeys.length <= 5) { var natParts = natKeys.map(function(k) { return k + ' (' + _n(nats[k]) + ')'; }); txt.push('Le club rassemble ' + natKeys.length + ' nationalit\u00e9s : ' + natParts.join(', ') + '.'); }
        else { var topNats = natKeys.slice(0, 4).map(function(k) { return k + ' (' + _n(nats[k]) + ')'; }); txt.push('Le club rassemble des athl\u00e8tes de ' + natKeys.length + ' nationalit\u00e9s diff\u00e9rentes, principalement ' + topNats.join(', ') + '.'); }
    }

    var nbRes = annee ? (d.nb_resultats || 0) : nbResGlobal;
    var nbEp = annee ? (d.nb_epreuves || 0) : nbEpGlobal;
    if (nbRes > 0) {
        txt.push((annee ? 'Sur cette saison, ' : 'Au total, ') + _n(nbRes) + ' r\u00e9sultat' + _pl(nbRes, '') + ' en comp\u00e9tition ' + (nbRes > 1 ? 'ont \u00e9t\u00e9 enregistr\u00e9s' : 'a \u00e9t\u00e9 enregistr\u00e9') + ' sur ' + _n(nbEp) + ' \u00e9preuve' + _pl(nbEp, '') + '.');
    }

    if (nivRes.length > 0) {
        var famCount = {D:0, R:0, N:0, I:0};
        var totalNivRes = 0;
        nivRes.forEach(function(n) { var f = n.niveau.charAt(0); if (famCount[f] !== undefined) famCount[f] += n.count; totalNivRes += n.count; });
        var famLabels = {D:'d\u00e9partemental',R:'r\u00e9gional',N:'national',I:'international'};
        var famParts = [];
        ['D','R','N','I'].forEach(function(f) { if (famCount[f] > 0) famParts.push(famLabels[f] + ' ' + Math.round(famCount[f]/totalNivRes*100) + '% (' + _n(famCount[f]) + ')'); });
        if (famParts.length > 0) txt.push('La r\u00e9partition par niveau de comp\u00e9tition : ' + famParts.join(', ') + '. Les niveaux les plus fr\u00e9quents sont ' + nivRes.slice(0,3).map(function(n){return n.niveau + ' (' + _n(n.count) + ')';}).join(', ') + '.');
    }

    if (ep.length > 0) {
        var topEp = ep.slice(0, 5).map(function(e) { return e.epreuve + ' (' + e.nb_athletes + ' athl\u00e8tes, ' + e.nb_records + ' records)'; });
        txt.push('Le club est actif sur ' + ep.length + ' discipline' + _pl(ep.length, '') + '. Les plus pratiqu\u00e9es : ' + topEp.join(', ') + '.');
        var bestRecs = ep.filter(function(e) { return e.best_perf && e.best_athlete; }).slice(0, 5);
        if (bestRecs.length > 0) { var recParts = bestRecs.map(function(e) { return e.best_perf + ' au ' + e.epreuve + ' par ' + e.best_athlete; }); txt.push('Meilleurs records du club : ' + recParts.join(' ; ') + '.'); }
    }

    if (totalRec > 0) {
        var discRec = {};
        rec.forEach(function(r) { if (r.epreuve) discRec[r.epreuve] = true; });
        var nbDiscRec = Object.keys(discRec).length;
        txt.push((annee ? 'Cette ann\u00e9e, ' : 'Au total, ') + _n(totalRec) + ' record' + _pl(totalRec, '') + ' personnel' + _pl(totalRec, '') + ' ' + (totalRec > 1 ? 'sont recens\u00e9s' : 'est recens\u00e9') + (nbDiscRec > 0 ? ', r\u00e9partis sur ' + nbDiscRec + ' discipline' + _pl(nbDiscRec, '') : '') + '.');
    }

    if (prog.nb_progressions > 0) txt.push(_n(prog.nb_progressions) + ' progression' + _pl(prog.nb_progressions, '') + ' enregistr\u00e9e' + _pl(prog.nb_progressions, '') + ' sur ' + prog.nb_epreuves + ' \u00e9preuve' + _pl(prog.nb_epreuves, '') + '.');

    var topMedAth = d.top_medaille_athletes || [];
    var topMedComp = d.top_medaille_competitions || [];
    var topMedEp = d.top_medaille_epreuves || [];
    var totalMed = (med.or || 0) + (med.argent || 0) + (med.bronze || 0);
    if (totalMed > 0) {
        var pMed = (annee ? 'Cette ann\u00e9e, les athl\u00e8tes ont remport\u00e9 ' : 'Les athl\u00e8tes du club ont collectivement remport\u00e9 ') + _n(totalMed) + ' m\u00e9daille' + _pl(totalMed, '');
        var detMed = [];
        if (med.or > 0) detMed.push(med.or + ' en or (' + Math.round(med.or/totalMed*100) + '%)');
        if (med.argent > 0) detMed.push(med.argent + ' en argent (' + Math.round(med.argent/totalMed*100) + '%)');
        if (med.bronze > 0) detMed.push(med.bronze + ' en bronze (' + Math.round(med.bronze/totalMed*100) + '%)');
        pMed += ' : ' + detMed.join(', ') + '.';
        txt.push(pMed);
        if (topMedAth.length > 0) {
            var athMedParts = topMedAth.slice(0, 5).map(function(a) { var info = a.athlete + ' (' + a.total + ' m\u00e9d.'; if (a.or > 0) info += ', ' + a.or + ' or'; info += ')'; return info; });
            txt.push('Les athl\u00e8tes les plus m\u00e9daill\u00e9s : ' + athMedParts.join(' ; ') + '.');
        }
        if (topMedComp.length > 0) {
            var compParts = topMedComp.slice(0, 4).map(function(c) { return c.competition + ' (' + c.total + ' m\u00e9d.' + (c.or > 0 ? ', ' + c.or + ' or' : '') + ')'; });
            txt.push('Comp\u00e9titions les plus m\u00e9daill\u00e9es : ' + compParts.join(', ') + '.');
        }
        if (topMedEp.length > 0) {
            var epMedParts = topMedEp.slice(0, 4).map(function(e) { return e.epreuve + ' (' + e.total + ' m\u00e9d.' + (e.or > 0 ? ', ' + e.or + ' or' : '') + ')'; });
            txt.push('\u00c9preuves les plus m\u00e9daill\u00e9es : ' + epMedParts.join(', ') + '.');
        }
        if (medDetail.length > 0) {
            var medEx = medDetail.slice(0, 5).map(function(m) { var s = m.type.charAt(0).toUpperCase() + m.type.slice(1) + ' : ' + m.athlete; if (m.epreuve) s += ' (' + m.epreuve + ')'; if (m.competition) s += ' \u00e0 ' + m.competition; if (m.annee && !annee) s += ' en ' + m.annee; return s; });
            txt.push('Derni\u00e8res m\u00e9dailles : ' + medEx.join(' ; ') + '.');
        }
    }

    var topPodEp = d.top_podium_epreuves || [];
    if (totalPod > 0) {
        var pPod = (annee ? 'Cette ann\u00e9e, ' : '') + _n(totalPod) + ' podium' + _pl(totalPod, '') + ' enregistr\u00e9' + _pl(totalPod, '');
        var podDet = [];
        if (pod['1er'] > 0) podDet.push(pod['1er'] + ' premi\u00e8re' + _pl(pod['1er'], '') + ' place' + _pl(pod['1er'], '') + ' (' + Math.round(pod['1er']/totalPod*100) + '%)');
        if (pod['2e'] > 0) podDet.push(pod['2e'] + ' deuxi\u00e8me' + _pl(pod['2e'], '') + ' place' + _pl(pod['2e'], '') + ' (' + Math.round(pod['2e']/totalPod*100) + '%)');
        if (pod['3e'] > 0) podDet.push(pod['3e'] + ' troisi\u00e8me' + _pl(pod['3e'], '') + ' place' + _pl(pod['3e'], '') + ' (' + Math.round(pod['3e']/totalPod*100) + '%)');
        if (podDet.length > 0) pPod += ' : ' + podDet.join(', ');
        pPod += '.';
        txt.push(pPod);
        if (podNiv.length > 0) txt.push('Les podiums ont \u00e9t\u00e9 obtenus aux niveaux : ' + podNiv.map(function(n) { return n.niveau + ' (' + _n(n.count) + ')'; }).join(', ') + '.');
        if (topPodEp.length > 0) { var podEpParts = topPodEp.slice(0, 4).map(function(e) { return e.epreuve + ' (' + e.total + ' podiums' + (e.nb_1er > 0 ? ', ' + e.nb_1er + ' victoire' + _pl(e.nb_1er, '') : '') + ')'; }); txt.push('\u00c9preuves les plus repr\u00e9sent\u00e9es sur les podiums : ' + podEpParts.join(', ') + '.'); }
    }

    var athSel = d.athletes_selectionnes || [];
    if (sel.nb_selections > 0) {
        var pSel = _n(sel.nb_athletes) + ' athl\u00e8te' + _pl(sel.nb_athletes, '') + ' du club ' + (sel.nb_athletes > 1 ? 'ont \u00e9t\u00e9 s\u00e9lectionn\u00e9s' : 'a \u00e9t\u00e9 s\u00e9lectionn\u00e9') + ' en \u00e9quipe nationale, pour un total de ' + _n(sel.nb_selections) + ' s\u00e9lection' + _pl(sel.nb_selections, '');
        if (sel.nb_competitions > 0) pSel += ' dans ' + sel.nb_competitions + ' comp\u00e9tition' + _pl(sel.nb_competitions, '');
        pSel += '.';
        if (athSel.length > 0) { var selParts = athSel.slice(0, 5).map(function(a) { return a.athlete + ' (' + a.nb_selections + ' s\u00e9l.)'; }); pSel += ' Les plus s\u00e9lectionn\u00e9s : ' + selParts.join(', ') + '.'; }
        txt.push(pSel);
    }

    var rpa = d.resultats_par_annee || [];
    if (rpa.length > 1) {
        var rpaSorted = rpa.slice().sort(function(a,b) { return a.annee - b.annee; });
        var first = rpaSorted[0]; var last = rpaSorted[rpaSorted.length - 1];
        var peak = rpaSorted.reduce(function(max, r) { return (r.nb_resultats||0) > (max.nb_resultats||0) ? r : max; }, rpaSorted[0]);
        var pEvo = 'L\'\u00e9volution de l\'activit\u00e9 montre ' + rpa.length + ' saisons de donn\u00e9es (de ' + first.annee + ' \u00e0 ' + last.annee + ').';
        pEvo += ' L\'ann\u00e9e la plus active est ' + peak.annee + ' avec ' + _n(peak.nb_resultats) + ' r\u00e9sultat' + _pl(peak.nb_resultats, '') + ' par ' + _n(peak.nb_athletes) + ' athl\u00e8te' + _pl(peak.nb_athletes, '') + '.';
        if (last.annee !== peak.annee) pEvo += ' En ' + last.annee + ' : ' + _n(last.nb_resultats) + ' r\u00e9sultat' + _pl(last.nb_resultats, '') + ' par ' + _n(last.nb_athletes) + ' athl\u00e8te' + _pl(last.nb_athletes, '') + '.';
        txt.push(pEvo);
    }

    if (nivData.length > 0) {
        var totalNivAth = 0;
        nivData.forEach(function(n) { totalNivAth += n.nb_athletes; });
        var pNiv = 'En termes de niveau de performance, ' + _n(totalNivAth) + ' athl\u00e8te' + _pl(totalNivAth, '') + ' ' + (totalNivAth > 1 ? 'sont class\u00e9s' : 'est class\u00e9') + ' sur ' + nivData.length + ' niveau' + _pl(nivData.length, 'x', 'x');
        var topNivs = nivData.slice(0, 5).map(function(n) { return (nivMap[n.code_niveau] || n.code_niveau) + ' (' + _n(n.nb_athletes) + ' athl\u00e8te' + _pl(n.nb_athletes, '') + (n.max_points ? ', max ' + _n(n.max_points) + ' pts' : '') + ')'; });
        pNiv += ' : ' + topNivs.join(', ');
        if (nivData.length > 5) pNiv += ', etc';
        pNiv += '.';
        if (bestNiv) {
            pNiv += ' Le meilleur niveau atteint est ' + (nivMap[bestNiv.code_niveau] || bestNiv.code_niveau);
            if (bestNiv.athlete) pNiv += ' par ' + bestNiv.athlete;
            if (bestNiv.annee && !annee) pNiv += ' en ' + bestNiv.annee;
            if (bestNiv.points) pNiv += ' (' + _n(bestNiv.points) + ' points)';
            pNiv += '.';
        }
        txt.push(pNiv);
    }

    if (topAth.length > 0) {
        var phares = topAth.slice(0, 5);
        var aParts = phares.map(function(a) { var info = a.nom_complet; var det = []; if (a.categorie) det.push(a.categorie); if (a.sexe) det.push(a.sexe === 'M' ? 'H' : 'F'); if (a.nb_resultats > 0) det.push(a.nb_resultats + ' r\u00e9sultats'); if (a.nb_records > 0) det.push(a.nb_records + ' records'); if (det.length > 0) info += ' (' + det.join(', ') + ')'; return info; });
        txt.push('Les athl\u00e8tes les plus actifs du club sont ' + aParts.join(' ; ') + '.');
    }

    if (topVilles.length > 0) {
        var vParts = topVilles.map(function(v) { return v.ville + ' (' + _n(v.nb_resultats) + ' r\u00e9sultats, ' + v.nb_athletes + ' athl\u00e8tes)'; });
        txt.push('Les principaux lieux de comp\u00e9tition : ' + vParts.join(', ') + '.');
    }

    if (!annee && anDebut && anFin) {
        var duree = anFin - anDebut;
        if (duree === 0) txt.push('Le club n\'a \u00e9t\u00e9 actif que durant une seule saison (' + anDebut + ').');
        else if (duree > 0) {
            var inactif = new Date().getFullYear() - anFin;
            if (inactif > 2) txt.push('Apr\u00e8s ' + (duree + 1) + ' ann\u00e9es d\'activit\u00e9, le club ne semble plus actif depuis ' + anFin + '.');
            else txt.push('Le club cumule ' + (duree + 1) + ' ann\u00e9es d\'activit\u00e9 \u00e0 ce jour.');
        }
    }

    if (!annee && anDispo.length > 1) {
        var recent = anDispo.slice(0, 3).join(', ');
        txt.push('Les saisons les plus r\u00e9centes avec donn\u00e9es : ' + recent + ' (sur ' + anDispo.length + ' saisons au total).');
    }

    return txt.join('\n\n');
}

function _buildNatResumeText(d) {
    var txt = [];
    var nom = d.club.nom_club;
    var nat = d.nationalites || {};
    var keys = Object.keys(nat);
    var totalNat = 0;
    keys.forEach(function(k) { totalNat += nat[k]; });
    if (keys.length === 0) return 'Aucune donnée de nationalité disponible pour ce club.';

    txt.push('Le club ' + nom + ' regroupe des athlètes de ' + keys.length + ' nationalité' + (keys.length > 1 ? 's' : '') + ' différente' + (keys.length > 1 ? 's' : '') + ', pour un total de ' + totalNat.toLocaleString('fr-FR') + ' athlètes.');

    var top1 = keys[0];
    var top1pct = Math.round(nat[top1] / totalNat * 100);
    var p2 = 'La nationalité la plus représentée est ' + top1 + ' avec ' + nat[top1].toLocaleString('fr-FR') + ' athlète' + (nat[top1] > 1 ? 's' : '') + ', soit ' + top1pct + '% de l\'effectif.';
    if (keys.length >= 2) { var top2 = keys[1]; var top2pct = Math.round(nat[top2] / totalNat * 100); p2 += ' Vient ensuite ' + top2 + ' avec ' + nat[top2].toLocaleString('fr-FR') + ' athlète' + (nat[top2] > 1 ? 's' : '') + ' (' + top2pct + '%).'; }
    if (keys.length >= 3) { var top3 = keys[2]; var top3pct = Math.round(nat[top3] / totalNat * 100); p2 += ' En troisième position, ' + top3 + ' avec ' + nat[top3].toLocaleString('fr-FR') + ' athlète' + (nat[top3] > 1 ? 's' : '') + ' (' + top3pct + '%).'; }
    txt.push(p2);

    if (keys.length >= 5) { var top5 = keys.slice(0, 5).map(function(k) { return k + ' (' + nat[k] + ')'; }); txt.push('Le top 5 des nationalités : ' + top5.join(', ') + '.'); }

    if (keys.length >= 10) {
        var topHalf = 0;
        var topCount = Math.min(5, keys.length);
        for (var i = 0; i < topCount; i++) topHalf += nat[keys[i]];
        var topPct = Math.round(topHalf / totalNat * 100);
        txt.push('Les ' + topCount + ' premières nationalités concentrent ' + topPct + '% des athlètes. Les ' + (keys.length - topCount) + ' autres se partagent les ' + (100 - topPct) + '% restants, témoignant ' + (keys.length >= 15 ? 'd\'une grande diversité culturelle.' : 'd\'une diversité notable.'));
    }

    if (keys.length >= 2) {
        var foreign = keys.filter(function(k) { return k !== top1; });
        var foreignTotal = 0;
        foreign.forEach(function(k) { foreignTotal += nat[k]; });
        var foreignPct = Math.round(foreignTotal / totalNat * 100);
        txt.push('En dehors des athlètes ' + top1 + ', ' + foreignTotal.toLocaleString('fr-FR') + ' athlète' + (foreignTotal > 1 ? 's' : '') + ' (' + foreignPct + '%) représentent ' + foreign.length + ' nationalité' + (foreign.length > 1 ? 's' : '') + ' étrangère' + (foreign.length > 1 ? 's' : '') + '.');
    }

    var rares = keys.filter(function(k) { return nat[k] === 1; });
    if (rares.length > 0) {
        if (rares.length <= 5) txt.push(rares.length + ' nationalité' + (rares.length > 1 ? 's sont' : ' est') + ' représentée' + (rares.length > 1 ? 's' : '') + ' par un seul athlète : ' + rares.join(', ') + '.');
        else txt.push(rares.length + ' nationalités ne comptent qu\'un seul représentant, parmi lesquelles ' + rares.slice(0, 5).join(', ') + '...');
    }

    var africa = ['MAR','ALG','TUN','SEN','CMR','CIV','CGO','COD','MLI','BEN','TGO','GIN','BFA','GAB','NER','MRT','TCD','RWA','BDI','ETH','KEN','GHA','NGA','EGY','MDG','COM','MUS','DJI','ERI','SOM','UGA','TZA','MOZ','ZAF','ZMB','ZWE','ANG','CPV'];
    var europe = ['FRA','GBR','ESP','POR','ITA','ALL','GER','DEU','BEL','NED','NLD','SUI','CHE','AUT','POL','ROU','HUN','CZE','SVK','GRE','BUL','CRO','SRB','BIH','MNE','MKD','SLO','SVN','LTU','LVA','EST','FIN','SWE','NOR','DEN','DNK','ISL','IRL','LUX','MLT','CYP','UKR','BLR','MDA','RUS','TUR','GEO','ARM','AZE'];
    var afCount = 0, euCount = 0, otherCount = 0;
    keys.forEach(function(k) { if (africa.indexOf(k) !== -1) afCount += nat[k]; else if (europe.indexOf(k) !== -1) euCount += nat[k]; else otherCount += nat[k]; });
    var parts = [];
    if (euCount > 0) parts.push('Europe : ' + euCount.toLocaleString('fr-FR') + ' (' + Math.round(euCount/totalNat*100) + '%)');
    if (afCount > 0) parts.push('Afrique : ' + afCount.toLocaleString('fr-FR') + ' (' + Math.round(afCount/totalNat*100) + '%)');
    if (otherCount > 0) parts.push('Autres : ' + otherCount.toLocaleString('fr-FR') + ' (' + Math.round(otherCount/totalNat*100) + '%)');
    if (parts.length >= 2) txt.push('Répartition géographique estimée : ' + parts.join(', ') + '.');

    var sexe = d.par_sexe || {};
    var nbH = sexe['M'] || 0;
    var nbF = sexe['F'] || 0;
    if (nbH > 0 && nbF > 0) { var ratioHF = Math.round(nbH / (nbH + nbF) * 100); txt.push('Au global, le club compte ' + ratioHF + '% d\'hommes et ' + (100 - ratioHF) + '% de femmes toutes nationalités confondues.'); }

    if (keys.length >= 5) txt.push('Avec ' + keys.length + ' nationalités représentées, ' + nom + ' est un club à forte dimension internationale, reflet de la diversité de l\'athlétisme français.');
    else if (keys.length >= 2) txt.push(nom + ' accueille des athlètes de ' + keys.length + ' nationalités différentes.');

    return txt.join('\n\n');
}

function _buildResumeHTML(text) {
    var h = '<div style="border-left:3px solid #6c5ce7;padding:16px 20px;">';
    h += '<p style="color:#c8cfd8;line-height:1.9;font-size:14px;margin:0;white-space:pre-line;">' + text + '</p>';
    h += '<button onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent).then(function(){alert(\'R\u00e9sum\u00e9 copi\u00e9 !\')})" style="margin-top:12px;background:#253049;color:#a29bfe;border:1px solid #6c5ce740;border-radius:8px;padding:6px 14px;cursor:pointer;font-size:12px;">&#128203; Copier le texte</button>';
    h += '</div>';
    return h;
}

// ============================================================
// Resume mode & year handlers
// ============================================================
function _clubSetResumeMode(mode, suffix) {
    var s = suffix || '';
    window['_clubResumeMode' + s] = mode;
    if (mode === 'comparer' && !window['_clubCompareYears' + s]) window['_clubCompareYears' + s] = [];
    _renderClubTab('resume', s);
}

function _clubYearChanged(suffix) {
    var s = suffix || '';
    var sel = document.getElementById('clubYearSelect' + s);
    var year = sel ? parseInt(sel.value) : 0;
    if (!year) {
        window['_clubResumeYear' + s] = null;
        var container = document.getElementById('clubYearResume' + s);
        if (container) container.innerHTML = '<div style="color:#5a6580;text-align:center;padding:40px;">Selectionnez une annee pour afficher le resume</div>';
        return;
    }
    window['_clubResumeYear' + s] = year;
    var cache = window['_clubYearDataCache' + s];
    if (cache[year]) {
        var container = document.getElementById('clubYearResume' + s);
        if (container) container.innerHTML = _buildResumeHTML(_buildResumeText(cache[year], year));
        return;
    }
    var loading = document.getElementById('clubYearLoading' + s);
    if (loading) loading.style.display = 'inline';
    var d = window['_clubDetailData' + s];
    fetch(BASE_API + '/club_stats.php?id=' + d.club.id_club + '&annee=' + year + _clubFilterParams(d))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (loading) loading.style.display = 'none';
            if (data.success) {
                window['_clubYearDataCache' + s][year] = data;
                var container = document.getElementById('clubYearResume' + s);
                if (container) container.innerHTML = _buildResumeHTML(_buildResumeText(data, year));
            }
        })
        .catch(function() { if (loading) loading.style.display = 'none'; });
}

function _clubToggleCompareYear(year, checked, suffix) {
    var s = suffix || '';
    var arr = window['_clubCompareYears' + s] || [];
    if (checked) { if (arr.length >= 6) { alert('Maximum 6 annees pour la comparaison'); _renderClubTab('resume', s); return; } if (arr.indexOf(year) === -1) arr.push(year); }
    else { arr = arr.filter(function(y) { return y !== year; }); }
    arr.sort(function(a,b) { return a - b; });
    window['_clubCompareYears' + s] = arr;
    _renderClubTab('resume', s);
}

function _clubRunCompare(suffix) {
    var s = suffix || '';
    var years = window['_clubCompareYears' + s] || [];
    if (years.length < 2) return;
    var d = window['_clubDetailData' + s];
    var cache = window['_clubYearDataCache' + s];
    var container = document.getElementById('clubCompareResult' + s);
    if (!container) return;
    container.innerHTML = '<div style="color:#5a6580;text-align:center;padding:20px;">Chargement des donnees...</div>';

    var fetches = years.map(function(y) {
        if (cache[y]) return Promise.resolve(cache[y]);
        return fetch(BASE_API + '/club_stats.php?id=' + d.club.id_club + '&annee=' + y + _clubFilterParams(d))
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.success) cache[y] = data; return data; });
    });

    Promise.all(fetches).then(function(results) {
        var validYears = [];
        var validData = [];
        years.forEach(function(y, i) { if (results[i] && results[i].success) { validYears.push(y); validData.push(results[i]); } });
        if (validYears.length < 2) { container.innerHTML = '<div style="color:#ff6b6b;text-align:center;padding:20px;">Donnees insuffisantes pour comparer</div>'; return; }

        var h = '<div class="table-wrap" style="margin-bottom:20px;">';
        h += '<table class="bk-table"><tr><th style="min-width:160px;">Indicateur</th>';
        validYears.forEach(function(y) { h += '<th style="text-align:center;">' + y + '</th>'; });
        h += '</tr>';
        h += '<tr><td style="color:#8b949e;">Athletes actifs</td>';
        validData.forEach(function(dt) { h += '<td style="text-align:center;font-weight:600;color:#55efc4;">' + (dt.total_athletes || 0) + '</td>'; });
        h += '</tr>';
        h += '<tr><td style="color:#8b949e;">Hommes / Femmes</td>';
        validData.forEach(function(dt) { var sx = dt.par_sexe || {}; h += '<td style="text-align:center;">' + (sx['M'] || 0) + ' / ' + (sx['F'] || 0) + '</td>'; });
        h += '</tr>';
        h += '<tr><td style="color:#8b949e;">Resultats</td>';
        validData.forEach(function(dt) { h += '<td style="text-align:center;">' + (dt.nb_resultats !== undefined ? dt.nb_resultats : '-') + '</td>'; });
        h += '</tr>';
        h += '<tr><td style="color:#8b949e;">Epreuves</td>';
        validData.forEach(function(dt) { h += '<td style="text-align:center;">' + (dt.nb_epreuves !== undefined ? dt.nb_epreuves : '-') + '</td>'; });
        h += '</tr>';
        h += '<tr><td style="color:#8b949e;">Medailles (total)</td>';
        validData.forEach(function(dt) { var m = dt.medailles || {}; h += '<td style="text-align:center;font-weight:600;">' + ((m.or||0)+(m.argent||0)+(m.bronze||0)) + '</td>'; });
        h += '</tr>';
        h += '<tr><td style="color:#8b949e;padding-left:24px;">Or / Argent / Bronze</td>';
        validData.forEach(function(dt) { var m = dt.medailles || {}; h += '<td style="text-align:center;font-size:12px;">' + (m.or||0) + ' / ' + (m.argent||0) + ' / ' + (m.bronze||0) + '</td>'; });
        h += '</tr>';
        h += '<tr><td style="color:#8b949e;">Records</td>';
        validData.forEach(function(dt) { h += '<td style="text-align:center;">' + (dt.records ? dt.records.length : 0) + '</td>'; });
        h += '</tr>';
        var nivMap2 = {N1:'N1 (Elite)',N2:'N2',N3:'N3',N4:'N4',R1:'R1',R2:'R2',R3:'R3',R4:'R4',R5:'R5',R6:'R6',D1:'D1',D2:'D2',D3:'D3',D4:'D4',D5:'D5',D6:'D6',D7:'D7',IR:'IR',IE:'IE'};
        h += '<tr><td style="color:#8b949e;">Meilleur niveau</td>';
        validData.forEach(function(dt) {
            var bn = dt.meilleur_niveau;
            if (bn) { h += '<td style="text-align:center;"><span style="color:#a29bfe;font-weight:600;">' + (nivMap2[bn.code_niveau]||bn.code_niveau) + '</span>'; if (bn.athlete) h += '<br><span style="font-size:11px;color:#5a6580;">' + escapeHtml(bn.athlete) + '</span>'; h += '</td>'; }
            else h += '<td style="text-align:center;color:#5a6580;">-</td>';
        });
        h += '</tr>';
        h += '<tr><td style="color:#8b949e;">Top performeur</td>';
        validData.forEach(function(dt) {
            var ta = dt.top_athletes || [];
            if (ta.length > 0) { h += '<td style="text-align:center;"><span style="color:#a29bfe;">' + escapeHtml(ta[0].nom_complet) + '</span><br><span style="font-size:11px;color:#5a6580;">' + ta[0].nb_resultats + ' res. / ' + ta[0].nb_records + ' rec.</span></td>'; }
            else h += '<td style="text-align:center;color:#5a6580;">-</td>';
        });
        h += '</tr>';
        h += '</table></div>';

        h += '<div style="margin-top:16px;"><canvas id="clubCompareChart' + s + '" height="280"></canvas></div>';
        container.innerHTML = h;

        var ctx = document.getElementById('clubCompareChart' + s);
        if (ctx) {
            if (window['_clubCompareChart' + s]) window['_clubCompareChart' + s].destroy();
            window['_clubCompareChart' + s] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: validYears.map(function(y) { return '' + y; }),
                    datasets: [
                        { label: 'Athletes', data: validData.map(function(dt) { return dt.total_athletes || 0; }), backgroundColor: '#6c5ce7' },
                        { label: 'Medailles', data: validData.map(function(dt) { var m=dt.medailles||{}; return (m.or||0)+(m.argent||0)+(m.bronze||0); }), backgroundColor: '#ffd700' },
                        { label: 'Records', data: validData.map(function(dt) { return dt.records ? dt.records.length : 0; }), backgroundColor: '#55efc4' },
                        { label: 'Resultats', data: validData.map(function(dt) { return dt.nb_resultats || 0; }), backgroundColor: '#ff7675' }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#8b949e' } } },
                    scales: { x: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' } }, y: { grid: { color: '#1e2a3a' }, ticks: { color: '#8b949e' }, beginAtZero: true } }
                }
            });
        }
    }).catch(function() { container.innerHTML = '<div style="color:#ff6b6b;text-align:center;padding:20px;">Erreur lors du chargement</div>'; });
}

// ============================================================
// Filter toggles & pagination
// ============================================================
function _clubToggleRecDisc(disc, suffix) {
    var s = suffix || '';
    if (disc === null) { window['_clubRecDiscFilter' + s] = null; }
    else { var cur = window['_clubRecDiscFilter' + s] || []; var idx = cur.indexOf(disc); if (idx === -1) cur.push(disc); else cur.splice(idx, 1); window['_clubRecDiscFilter' + s] = cur.length > 0 ? cur : null; }
    _renderClubTab('records', s);
}

function _clubToggleDisc(disc, suffix) {
    var s = suffix || '';
    if (disc === null) { window['_clubDiscFilter' + s] = null; }
    else { var cur = window['_clubDiscFilter' + s] || []; var idx = cur.indexOf(disc); if (idx === -1) cur.push(disc); else cur.splice(idx, 1); window['_clubDiscFilter' + s] = cur.length > 0 ? cur : null; }
    _renderClubTab('epreuves', s);
}

function _clubToggleNiv(niv, suffix) {
    var s = suffix || '';
    if (niv === null) { window['_clubNivFilter' + s] = null; }
    else { var cur = window['_clubNivFilter' + s] || []; var idx = cur.indexOf(niv); if (idx === -1) cur.push(niv); else cur.splice(idx, 1); window['_clubNivFilter' + s] = cur.length > 0 ? cur : null; }
    _renderClubTab('epreuves', s);
}

function loadClubRecPage(page, suffix) {
    var s = suffix || '';
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&rp=' + page;
    if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement page ' + page + '...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.records = data.records; d.total_records = data.total_records; d.rec_page = data.rec_page; d.rec_pages = data.rec_pages;
        _renderClubTab('records', s);
    });
}

function loadClubPerfsPage(page, suffix) {
    var s = suffix || '';
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var perfMode = window['_clubPerfMode' + s] || 'all';
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&pp=' + page + '&pm=' + perfMode;
    if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement page ' + page + '...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.performances = data.performances; d.total_performances = data.total_performances; d.perf_page = data.perf_page; d.perf_pages = data.perf_pages;
        _renderClubTab('performances', s);
    });
}

function _clubSetPerfMode(mode, suffix) {
    var s = suffix || '';
    window['_clubPerfMode' + s] = mode;
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&pp=1&pm=' + mode;
    if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.performances = data.performances; d.total_performances = data.total_performances; d.perf_page = data.perf_page; d.perf_pages = data.perf_pages;
        _renderClubTab('performances', s);
    });
}

function loadClubEpPage(page, suffix) {
    var s = suffix || '';
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var epMode = window['_clubEpMode' + s] || 'club';
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&ep=' + page;
    if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
    if (epMode === 'perso') url += '&perso=1';
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement page ' + page + '...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.epreuves = data.epreuves; d.total_epreuves = data.total_epreuves; d.ep_page = data.ep_page; d.ep_pages = data.ep_pages;
        _renderClubTab('epreuves', s);
    });
}

// ============================================================
// Year comparison for epreuves tab
// ============================================================
function _clubEpYearModeSet(mode, suffix) {
    var s = suffix || '';
    window['_clubEpYearMode' + s] = mode;
    if (mode === 'filter') { window['_clubEpYearCmp' + s] = []; window['_clubEpYearCmpData' + s] = null; }
    _renderClubTab('epreuves', s);
}

function _clubToggleEpYearCmp(yr, suffix) {
    var s = suffix || '';
    var cmp = window['_clubEpYearCmp' + s] || [];
    var idx = cmp.indexOf(yr);
    if (idx === -1) { if (cmp.length < 5) cmp.push(yr); } else { cmp.splice(idx, 1); }
    window['_clubEpYearCmp' + s] = cmp;
    window['_clubEpYearCmpData' + s] = null;
    _renderClubTab('epreuves', s);
}

function _clubRunEpYearCmp(suffix) {
    var s = suffix || '';
    var d = window['_clubDetailData' + s];
    var cmpYears = (window['_clubEpYearCmp' + s] || []).slice().sort();
    if (!d || !d.club || cmpYears.length < 2) return;
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement comparaison ' + cmpYears.join(' vs ') + '...</div>';
    var epMode = window['_clubEpMode' + s] || 'club';
    var results = {};
    var done = 0;
    cmpYears.forEach(function(yr) {
        var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&annee=' + yr;
        if (epMode === 'perso') url += '&perso=1';
        url += _clubFilterParams(d);
        fetch(url).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) results[yr] = data;
            done++;
            if (done === cmpYears.length) { window['_clubEpYearCmpData' + s] = results; _renderClubTab('epreuves', s); }
        }).catch(function() {
            done++;
            if (done === cmpYears.length) { window['_clubEpYearCmpData' + s] = results; _renderClubTab('epreuves', s); }
        });
    });
}

function _buildEpYearCmpHTML(cmpData, suffix) {
    var s = suffix || '';
    var years = Object.keys(cmpData).map(Number).sort();
    var h = '';
    h += '<div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;margin-bottom:16px;">';
    h += '<h4 style="margin:0 0 12px;color:#c9d1d9;font-size:14px;">Comparaison ' + years.join(' / ') + '</h4>';
    var thRow = '<tr><th>Métrique</th>';
    years.forEach(function(y) { thRow += '<th style="text-align:center;">' + y + '</th>'; });
    thRow += '</tr>';
    h += '<div class="table-wrap">';
    h += '<table class="bk-table">' + thRow + '</table>';
    h += '<table class="bk-table">';
    var metrics = [{k:'total_epreuves',l:'Épreuves'},{k:'total_athletes',l:'Athlètes'},{k:'total_records',l:'Records'},{k:'nb_resultats',l:'Résultats'}];
    metrics.forEach(function(m) {
        h += '<tr><td style="color:#8b949e;">' + m.l + '</td>';
        var vals = years.map(function(y) { return cmpData[y][m.k] || 0; });
        var maxV = Math.max.apply(null, vals);
        years.forEach(function(y, i) { var v = vals[i]; var clr = v === maxV && maxV > 0 ? '#55efc4' : '#c9d1d9'; h += '<td style="text-align:center;color:' + clr + ';font-weight:' + (v===maxV&&maxV>0?'700':'400') + ';">' + v.toLocaleString('fr-FR') + '</td>'; });
        h += '</tr>';
    });
    ['or','argent','bronze'].forEach(function(type) {
        var icons = {or:'&#129351;', argent:'&#129352;', bronze:'&#129353;'};
        h += '<tr><td style="color:#8b949e;">' + icons[type] + ' ' + type.charAt(0).toUpperCase()+type.slice(1) + '</td>';
        var vals = years.map(function(y) { var med = cmpData[y].medailles || {}; return med[type] || 0; });
        var maxV = Math.max.apply(null, vals);
        years.forEach(function(y, i) { var v = vals[i]; var clr = v === maxV && maxV > 0 ? '#ffd700' : '#c9d1d9'; h += '<td style="text-align:center;color:' + clr + ';font-weight:' + (v===maxV&&maxV>0?'700':'400') + ';">' + v + '</td>'; });
        h += '</tr>';
    });
    ['D','R','N','I'].forEach(function(fam) {
        var clrs = {D:'#fb923c',R:'#22d3ee',N:'#fb7185',I:'#e879f9'};
        h += '<tr><td style="color:' + clrs[fam] + ';">Niveau ' + fam + '</td>';
        var vals = years.map(function(y) { var npa = cmpData[y].niveaux_par_annee || []; var found = npa.find(function(n) { return n.annee == y; }); return found ? (found[fam] || 0) : 0; });
        var maxV = Math.max.apply(null, vals);
        years.forEach(function(y, i) { var v = vals[i]; h += '<td style="text-align:center;color:' + (v===maxV&&maxV>0?clrs[fam]:'#5a6580') + ';font-weight:' + (v===maxV&&maxV>0?'700':'400') + ';">' + v + '</td>'; });
        h += '</tr>';
    });
    h += '</table>';
    h += '<table class="bk-table">' + thRow + '</table>';
    h += '</div></div>';
    h += '<div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;margin-bottom:16px;">';
    h += '<canvas id="clubEpYearCmpChart' + s + '" height="250"></canvas>';
    h += '</div>';
    h += '<div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;margin-bottom:16px;">';
    h += '<h4 style="margin:0 0 12px;color:#c9d1d9;font-size:14px;">Top épreuves par année</h4>';
    h += '<div style="display:flex;gap:12px;flex-wrap:wrap;">';
    years.forEach(function(y) {
        var epList = (cmpData[y].epreuves || []).slice(0, 5);
        h += '<div style="flex:1;min-width:200px;">';
        h += '<h5 style="color:#a29bfe;margin:0 0 8px;font-size:13px;">' + y + '</h5>';
        if (epList.length === 0) h += '<span style="color:#5a6580;font-size:12px;">Aucune</span>';
        epList.forEach(function(e, i) { h += '<div style="font-size:12px;color:#c9d1d9;padding:2px 0;">' + (i+1) + '. ' + (e.epreuve||'') + '</div>'; });
        h += '</div>';
    });
    h += '</div></div>';
    // Textual summary
    h += '<div style="background:#0d1520;border:1px solid #1e2a3a;border-radius:10px;padding:16px;margin-bottom:16px;">';
    h += '<h4 style="margin:0 0 12px;color:#c9d1d9;font-size:14px;">Résumé comparatif</h4>';
    h += '<div style="color:#c9d1d9;font-size:13px;line-height:1.8;">';
    var _rLines = [];
    var _mKeys = [{k:'total_epreuves',l:'épreuves'},{k:'total_athletes',l:'athlètes'},{k:'total_records',l:'records'},{k:'nb_resultats',l:'résultats'}];
    _mKeys.forEach(function(m) {
        var best = null, bestV = -1;
        years.forEach(function(y) { var v = cmpData[y][m.k] || 0; if (v > bestV) { bestV = v; best = y; } });
        if (best && bestV > 0) {
            var worst = null, worstV = Infinity;
            years.forEach(function(y) { var v = cmpData[y][m.k] || 0; if (v < worstV) { worstV = v; worst = y; } });
            var diff = bestV - worstV;
            _rLines.push('<b>' + best + '</b> est la meilleure année en <span style="color:#a29bfe;">' + m.l + '</span> avec <b>' + bestV.toLocaleString('fr-FR') + '</b>' + (diff > 0 && worst !== best ? ' (<span style="color:#55efc4;">+' + diff.toLocaleString('fr-FR') + '</span> vs ' + worst + ')' : '') + '.');
        }
    });
    var _medTotals = {};
    years.forEach(function(y) { var med = cmpData[y].medailles || {}; _medTotals[y] = (med.or || 0) + (med.argent || 0) + (med.bronze || 0); });
    var _bestMedY = null, _bestMedV = -1;
    years.forEach(function(y) { if (_medTotals[y] > _bestMedV) { _bestMedV = _medTotals[y]; _bestMedY = y; } });
    if (_bestMedY && _bestMedV > 0) _rLines.push('<b>' + _bestMedY + '</b> cumule le plus de <span style="color:#ffd700;">médailles</span> avec <b>' + _bestMedV + '</b> au total.');
    var _bestOrY = null, _bestOrV = -1;
    years.forEach(function(y) { var v = (cmpData[y].medailles||{}).or||0; if(v>_bestOrV){_bestOrV=v;_bestOrY=y;} });
    if (_bestOrY && _bestOrV > 0) _rLines.push('<b>' + _bestOrY + '</b> détient le record de <span style="color:#ffd700;">médailles d\'or</span> avec <b>' + _bestOrV + '</b>.');
    if (years.length >= 2) {
        var first = years[0], last = years[years.length - 1];
        var _trends = [];
        _mKeys.forEach(function(m) { var vF = cmpData[first][m.k] || 0; var vL = cmpData[last][m.k] || 0; if (vF > 0 && vL > 0) { var pct = Math.round((vL - vF) / vF * 100); if (pct !== 0) _trends.push({l: m.l, pct: pct, up: pct > 0}); } });
        if (_trends.length > 0) {
            var ups = _trends.filter(function(t){return t.up;});
            var downs = _trends.filter(function(t){return !t.up;});
            if (ups.length > 0) _rLines.push('Entre <b>' + first + '</b> et <b>' + last + '</b>, progression en ' + ups.map(function(t){return '<span style="color:#55efc4;">' + t.l + ' (+' + t.pct + '%)</span>';}).join(', ') + '.');
            if (downs.length > 0) _rLines.push('En revanche, baisse en ' + downs.map(function(t){return '<span style="color:#ff6b6b;">' + t.l + ' (' + t.pct + '%)</span>';}).join(', ') + '.');
        }
    }
    var _nivFams = ['D','R','N','I'];
    var _nivLabels = {D:'départemental',R:'régional',N:'national',I:'international'};
    var _nivClrs = {D:'#fb923c',R:'#22d3ee',N:'#fb7185',I:'#e879f9'};
    _nivFams.forEach(function(fam) {
        var bestY = null, bestV = -1;
        years.forEach(function(y) { var npa = cmpData[y].niveaux_par_annee || []; var found = npa.find(function(n){return n.annee==y;}); var v = found ? (found[fam]||0) : 0; if (v > bestV) { bestV = v; bestY = y; } });
        if (bestY && bestV > 0) _rLines.push('Niveau <span style="color:' + _nivClrs[fam] + ';">' + _nivLabels[fam] + '</span> : <b>' + bestY + '</b> en tête avec <b>' + bestV + '</b> résultats.');
    });
    _rLines.forEach(function(line) { h += '<p style="margin:0 0 6px;padding-left:12px;border-left:2px solid #1e2a3a;">' + line + '</p>'; });
    h += '</div></div>';
    return h;
}

// ============================================================
// Epreuve mode & year setters
// ============================================================
function _clubSetEpYear(year, suffix) {
    var s = suffix || '';
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var epMode = window['_clubEpMode' + s] || 'club';
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&ep=1';
    if (year) url += '&annee=' + year;
    if (epMode === 'perso') url += '&perso=1';
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.epreuves = data.epreuves; d.total_epreuves = data.total_epreuves; d.ep_page = data.ep_page; d.ep_pages = data.ep_pages;
        d.annee_filtree = year || null;
        if (data.niveaux_par_annee) d.niveaux_par_annee = data.niveaux_par_annee;
        _renderClubTab('epreuves', s);
    });
}

function _clubSetEpMode(mode, suffix) {
    var s = suffix || '';
    window['_clubEpMode' + s] = mode;
    var d = window['_clubDetailData' + s];
    if (!d || !d.club) return;
    var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&ep=1';
    if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
    if (mode === 'perso') url += '&perso=1';
    url += _clubFilterParams(d);
    var content = document.getElementById('clubDetailContent' + s);
    if (content) content.innerHTML = '<div class="loading-msg">Chargement...</div>';
    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success) return;
        d.epreuves = data.epreuves; d.total_epreuves = data.total_epreuves; d.ep_page = data.ep_page; d.ep_pages = data.ep_pages;
        _renderClubTab('epreuves', s);
    });
}

// ============================================================
// Nationality mode & selection
// ============================================================
function _clubSetNatMode(mode, suffix) {
    var s = suffix || '';
    window['_clubNatMode' + s] = mode;
    if (mode === 'overview') window['_clubNatCmp' + s] = null;
    _renderClubTab('nationalites', s);
}

function _clubToggleNatSel(code, suffix) {
    var s = suffix || '';
    var sel = window['_clubNatSel' + s] || [];
    var idx = sel.indexOf(code);
    if (idx === -1) sel.push(code); else sel.splice(idx, 1);
    window['_clubNatSel' + s] = sel;
    window['_clubNatCmp' + s] = null;
    if (sel.length >= 2) {
        var d = window['_clubDetailData' + s];
        if (!d || !d.club) return;
        var url = BASE_API + '/club_stats.php?id=' + d.club.id_club + '&nat_detail=' + encodeURIComponent(sel.join(','));
        if (d.annee_filtree) url += '&annee=' + d.annee_filtree;
        url += _clubFilterParams(d);
        _renderClubTab('nationalites', s);
        fetch(url).then(function(r) { return r.json(); }).then(function(data) {
            if (data.nat_compare) { window['_clubNatCmp' + s] = data.nat_compare; _renderClubTab('nationalites', s); }
        });
    } else {
        _renderClubTab('nationalites', s);
    }
}

// ============================================================
// Page wrappers (shortcuts without suffix)
// ============================================================
function openClubDetail(id, nom) {
    var url = nom ? BASE_API + '/club_stats.php?nom=' + encodeURIComponent(nom) : BASE_API + '/club_stats.php?id=' + id;
    _openClubPanel(url, '');
}
function closeClubDetail() { _closeClubPanel(''); }
function switchClubTab(tab) { _switchClubTab(tab, ''); }
function renderClubTab(tab) { _renderClubTab(tab, ''); }

// Accueil wrappers
function openClubDetailAccueil(nom) { _openClubPanel(BASE_API + '/club_stats.php?nom=' + encodeURIComponent(nom), 'Accueil'); }
function closeClubDetailAccueil() { _closeClubPanel('Accueil'); }
function switchClubTabAccueil(tab) { _switchClubTab(tab, 'Accueil'); }
