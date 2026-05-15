/**
 * tuto.js — Tutoriel interactif (animated tutorial)
 * Extracted from index.php (lines ~4930-5295)
 *
 * Depends on:
 *   - BASE_API (global)
 *   - escapeHtml() (from utils.js or index.php)
 *   - _fillClubPanel(), _switchClubTab() (club panel JS in index.php)
 *
 * DOM elements referenced:
 *   tutoStep1..tutoStep9, tutoProgress, tutoTyping,
 *   tutoClubInput, tutoClubResults, tutoClubDone, tutoClubSearchWrap,
 *   clubDetailNameTuto, clubDetailMetaTuto, clubDetailContentTuto,
 *   tutoTabsCount, tutoClubTabsDoneTop, tutoClubTabsDone,
 *   tutoAthClubName, tutoAthInput, tutoAthResults, tutoAthDone, tutoAthSearchWrap,
 *   tutoAthPreview, tutoAthProfileLink, tutoAthDoneStep5
 */

// ===============================================
// TUTORIEL INTERACTIF — JS
// ===============================================

var _tutoState = {
    current: 1,
    completed: [],
    selectedClub: null,   // {id, name}
    selectedAthlete: null, // {id, name}
    visitedTabs: [],
    searchTimer: null
};

// Restore progress from localStorage
try {
    var saved = JSON.parse(localStorage.getItem('bk_tuto_progress') || '[]');
    if (Array.isArray(saved)) _tutoState.completed = saved;
} catch(e) {}

// ——— Navigation ———
function tutoGoStep(n) {
    // Hide all steps
    for (var i = 1; i <= 9; i++) {
        var el = document.getElementById('tutoStep' + i);
        if (el) {
            if (i === n) { el.style.display = ''; el.classList.add('visible','tuto-enter'); }
            else { el.style.display = 'none'; el.classList.remove('tuto-enter'); }
        }
    }
    _tutoState.current = n;
    // Update progress bar
    document.querySelectorAll('.tuto-progress-step').forEach(function(ps) {
        var s = parseInt(ps.dataset.step);
        ps.classList.remove('active', 'done');
        if (_tutoState.completed.indexOf(s) >= 0) ps.classList.add('done');
        if (s === n) ps.classList.add('active');
    });
    // Trigger step-specific animations
    _tutoTriggerStep(n);
    // Scroll to top of tuto container
    var container = document.querySelector('.tuto-container');
    if (container) container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    // Auto-load step 2 club suggestions
    if (n === 2 && !_tutoState.selectedClub) {
        _tutoLoadClubSuggestions();
    }
    // Auto-load step 3 club panel if club selected
    if (n === 3 && _tutoState.selectedClub && !document.getElementById('clubDetailNameTuto').textContent) {
        _tutoLoadClubPanel(_tutoState.selectedClub.id, _tutoState.selectedClub.name);
    }
    // Auto-load step 4 with club name
    if (n === 4 && _tutoState.selectedClub) {
        var cn = document.getElementById('tutoAthClubName');
        if (cn) cn.textContent = _tutoState.selectedClub.name;
        // Auto-search all athletes of club
        _tutoSearchAthletes('');
    }
    // Auto-load step 5 athlete preview
    if (n === 5 && _tutoState.selectedAthlete) {
        _tutoLoadAthPreview(_tutoState.selectedAthlete.id);
    }
    // Mark descriptive steps as auto-complete
    if ([6, 7, 8].indexOf(n) >= 0) {
        _tutoMarkComplete(n);
    }
}

function _tutoMarkComplete(n) {
    if (_tutoState.completed.indexOf(n) < 0) {
        _tutoState.completed.push(n);
        try { localStorage.setItem('bk_tuto_progress', JSON.stringify(_tutoState.completed)); } catch(e) {}
    }
    // Update progress dot
    var dot = document.querySelector('.tuto-progress-step[data-step="' + n + '"]');
    if (dot) dot.classList.add('done');
}

function tutoSkip() {
    try { localStorage.setItem('bk_tuto_seen', '1'); } catch(e) {}
    window.location.href = '?page=accueil';
}

function tutoComplete() {
    try { localStorage.setItem('bk_tuto_seen', '1'); } catch(e) {}
    _tutoMarkComplete(9);
    window.location.href = '?page=accueil';
}

// ——— Typing + Counter animations ———
function _tutoTypeText(el, text, speed, cb) {
    var i = 0; el.textContent = '';
    var iv = setInterval(function() {
        if (i < text.length) { el.textContent += text[i]; i++; }
        else { clearInterval(iv); if (cb) cb(); }
    }, speed || 50);
}
function _tutoAnimateCounter(el, target) {
    var duration = 1500, startTime = null;
    function step(ts) {
        if (!startTime) startTime = ts;
        var progress = Math.min((ts - startTime) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString('fr-FR');
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

var _tutoAnimated = {};
function _tutoTriggerStep(n) {
    if (_tutoAnimated[n]) return;
    _tutoAnimated[n] = true;
    if (n === 1) {
        var typEl = document.getElementById('tutoTyping');
        if (typEl) _tutoTypeText(typEl, 'Bienvenue sur Bokonzi', 60);
        setTimeout(function() {
            document.querySelectorAll('.tuto-step[data-step="1"] .tuto-card .num').forEach(function(el) {
                var target = parseInt(el.dataset.count);
                if (target) _tutoAnimateCounter(el, target);
            });
        }, 800);
    }
}

// ——— Step 2: Club search ———
function _tutoSearchClubs(query) {
    clearTimeout(_tutoState.searchTimer);
    var results = document.getElementById('tutoClubResults');
    if (!query || query.length < 2) { results.style.display = 'none'; return; }
    _tutoState.searchTimer = setTimeout(function() {
        results.style.display = 'block';
        results.innerHTML = '<div style="padding:12px;color:#5a6580;text-align:center;">Recherche...</div>';
        fetch(BASE_API + '/clubs.php?nom=' + encodeURIComponent(query) + '&limit=10')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.clubs || data.clubs.length === 0) {
                    results.innerHTML = '<div style="padding:12px;color:#5a6580;text-align:center;">Aucun club trouv\u00e9</div>';
                    return;
                }
                var html = '';
                data.clubs.forEach(function(c) {
                    html += '<div class="tuto-club-result" onclick="_tutoSelectClub(' + c.id_club + ', \'' + escapeHtml(c.nom_club).replace(/'/g, "\\'") + '\')">'
                        + '<div style="flex:1;"><span style="color:#a29bfe;font-weight:600;">' + escapeHtml(c.nom_club) + '</span></div>'
                        + '<span style="color:#34d399;font-size:12px;">' + (c.nb_athletes || 0) + ' athl\u00e8tes</span>'
                        + '</div>';
                });
                results.innerHTML = html;
            })
            .catch(function() {
                results.innerHTML = '<div style="padding:12px;color:#ef4444;text-align:center;">Erreur de connexion</div>';
            });
    }, 300);
}

function _tutoSelectClub(id, name) {
    _tutoState.selectedClub = { id: id, name: name };
    document.getElementById('tutoClubResults').style.display = 'none';
    document.getElementById('tutoClubInput').value = name;
    document.getElementById('tutoClubDone').style.display = 'block';
    document.getElementById('tutoClubSearchWrap').classList.remove('tuto-highlight');
    _tutoMarkComplete(2);
}

// Suggestions populaires (auto-loaded)
function _tutoLoadClubSuggestions() {
    var results = document.getElementById('tutoClubResults');
    if (!results || _tutoState.selectedClub) return;
    results.style.display = 'block';
    results.innerHTML = '<div style="padding:8px 12px;color:#8b949e;font-size:11px;font-weight:600;">CLUBS POPULAIRES</div><div style="padding:12px;color:#5a6580;text-align:center;">Chargement...</div>';
    fetch(BASE_API + '/clubs.php?limit=8')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.clubs || data.clubs.length === 0) return;
            var html = '<div style="padding:8px 12px;color:#8b949e;font-size:11px;font-weight:600;">CLUBS POPULAIRES \u2014 cliquez pour s\u00e9lectionner</div>';
            data.clubs.forEach(function(c) {
                html += '<div class="tuto-club-result" onclick="_tutoSelectClub(' + c.id_club + ', \'' + escapeHtml(c.nom_club).replace(/'/g, "\\'") + '\')">'
                    + '<div style="flex:1;"><span style="color:#a29bfe;font-weight:600;">' + escapeHtml(c.nom_club) + '</span></div>'
                    + '<span style="color:#34d399;font-size:12px;">' + (c.nb_athletes || 0) + ' athl\u00e8tes</span>'
                    + '</div>';
            });
            html += '<div style="padding:8px 12px;color:#5a6580;font-size:11px;text-align:center;font-style:italic;">...ou tapez un nom ci-dessus pour chercher</div>';
            results.innerHTML = html;
        })
        .catch(function() {
            results.innerHTML = '<div style="padding:8px 12px;color:#8b949e;font-size:11px;font-weight:600;">CLUBS POPULAIRES</div><div style="padding:12px;color:#ef4444;text-align:center;">Erreur de chargement</div>';
        });
}

// ——— Step 3: Club panel (embedded) ———
function _tutoLoadClubPanel(id, name) {
    var content = document.getElementById('clubDetailContentTuto');
    if (!content) return;
    content.innerHTML = '<div class="loading-msg">Chargement de ' + escapeHtml(name) + '...</div>';
    document.getElementById('clubDetailNameTuto').textContent = name;
    document.getElementById('clubDetailMetaTuto').textContent = 'Chargement...';
    var url = BASE_API + '/club_stats.php?id=' + id;
    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { content.innerHTML = '<div class="loading-msg">Club non trouv\u00e9</div>'; return; }
            _fillClubPanel(data, 'Tuto');
            _tutoState.visitedTabs = ['epreuves'];
            _tutoUpdateTabCount();
        })
        .catch(function() { content.innerHTML = '<div class="loading-msg">Erreur de chargement</div>'; });
}

function switchClubTabTuto(tab) {
    _switchClubTab(tab, 'Tuto');
    // Track visited tabs
    if (_tutoState.visitedTabs.indexOf(tab) < 0) {
        _tutoState.visitedTabs.push(tab);
    }
    _tutoUpdateTabCount();
}
function closeClubDetailTuto() {} // no-op for embedded panel

function _tutoUpdateTabCount() {
    var cnt = _tutoState.visitedTabs.length;
    var el = document.getElementById('tutoTabsCount');
    if (el) el.textContent = cnt;
    if (cnt >= 2) {
        _tutoMarkComplete(3);
        var top = document.getElementById('tutoClubTabsDoneTop');
        var bot = document.getElementById('tutoClubTabsDone');
        if (top) top.style.display = 'block';
        if (bot) bot.style.display = 'block';
    }
}

// ——— Step 4: Athlete search ———
function _tutoSearchAthletes(query) {
    clearTimeout(_tutoState.searchTimer);
    var results = document.getElementById('tutoAthResults');
    if (!_tutoState.selectedClub) {
        results.innerHTML = '<div style="padding:12px;color:#5a6580;text-align:center;">S\u00e9lectionnez d\'abord un club</div>';
        results.style.display = 'block';
        return;
    }
    _tutoState.searchTimer = setTimeout(function() {
        results.style.display = 'block';
        results.innerHTML = '<div style="padding:12px;color:#5a6580;text-align:center;">Recherche...</div>';
        var params = 'club=' + encodeURIComponent(_tutoState.selectedClub.name) + '&limit=15';
        if (query && query.length >= 2) params += '&nom=' + encodeURIComponent(query);
        fetch(BASE_API + '/search.php?' + params)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.athletes || data.athletes.length === 0) {
                    results.innerHTML = '<div style="padding:12px;color:#5a6580;text-align:center;">Aucun athl\u00e8te trouv\u00e9</div>';
                    return;
                }
                var html = '<div style="padding:8px 12px;color:#8b949e;font-size:11px;font-weight:600;">ATHL\u00c8TES DU CLUB \u2014 cliquez pour s\u00e9lectionner</div>';
                data.athletes.forEach(function(a) {
                    var extId = a.athlete_id || a.athlete_id_externe || a.id;
                    var nom = a.nom_complet || ((a.prenom_athlete || '') + ' ' + (a.nom_athlete || '')).trim();
                    var cat = a.categorie || a.categorie_athlete || '';
                    var sexe = a.sexe || a.sexe_athlete || '';
                    var nat = a.nationalite || a.nationalite_athlete || '';
                    html += '<div class="tuto-ath-result" onclick="_tutoSelectAthlete(' + extId + ', \'' + escapeHtml(nom).replace(/'/g, "\\'") + '\')">'
                        + '<div style="flex:1;">'
                        + '<span style="color:#c9d1d9;font-weight:600;">' + escapeHtml(nom) + '</span>'
                        + (cat ? ' <span class="badge badge-cat" style="font-size:10px;">' + escapeHtml(cat) + '</span>' : '')
                        + (sexe ? ' <span class="badge badge-' + sexe.toLowerCase() + '" style="font-size:10px;">' + escapeHtml(sexe) + '</span>' : '')
                        + '</div>'
                        + (nat ? '<span style="color:#8b949e;font-size:11px;">' + escapeHtml(nat) + '</span>' : '')
                        + '</div>';
                });
                results.innerHTML = html;
            })
            .catch(function() {
                results.innerHTML = '<div style="padding:12px;color:#ef4444;text-align:center;">Erreur de connexion</div>';
            });
    }, 300);
}

function _tutoSelectAthlete(id, name) {
    _tutoState.selectedAthlete = { id: id, name: name };
    document.getElementById('tutoAthResults').style.display = 'none';
    document.getElementById('tutoAthInput').value = name;
    document.getElementById('tutoAthDone').style.display = 'block';
    document.getElementById('tutoAthSearchWrap').classList.remove('tuto-highlight');
    _tutoMarkComplete(4);
}

// ——— Step 5: Athlete preview ———
function _tutoLoadAthPreview(id) {
    var container = document.getElementById('tutoAthPreview');
    if (!container) return;
    container.innerHTML = '<div class="loading-msg">Chargement du profil...</div>';
    fetch(BASE_API + '/athlete.php?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { container.innerHTML = '<div class="loading-msg">Athl\u00e8te non trouv\u00e9</div>'; return; }
            var a = data.athlete || data;
            var nom = (a.prenom_athlete || '') + ' ' + (a.nom_athlete || '');
            var html = '<div style="padding:16px;">';
            // Header
            html += '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">';
            html += '<span style="font-size:18px;font-weight:800;color:#c9d1d9;">' + escapeHtml(nom.trim()) + '</span>';
            if (a.sexe_athlete) html += '<span class="badge badge-' + (a.sexe_athlete||'').toLowerCase() + '">' + escapeHtml(a.sexe_athlete) + '</span>';
            if (a.categorie_athlete) html += '<span class="badge badge-cat">' + escapeHtml(a.categorie_athlete) + '</span>';
            if (a.nationalite_athlete) html += '<span style="padding:3px 8px;border-radius:6px;font-size:11px;background:#30363d;color:#c9d1d9;">' + escapeHtml(a.nationalite_athlete) + '</span>';
            html += '</div>';
            // Infos
            var infos = [];
            if (a.date_naissance && a.date_naissance.indexOf('0000') !== 0) infos.push('N\u00e9(e) : ' + a.date_naissance.substring(0, 4));
            if (a.lieu_naissance) infos.push('Lieu : ' + a.lieu_naissance);
            if (infos.length) html += '<div style="color:#5a6580;font-size:12px;margin-bottom:10px;">' + escapeHtml(infos.join(' \u2014 ')) + '</div>';
            // Clubs
            if (data.clubs && data.clubs.length > 0) {
                html += '<div style="margin-bottom:10px;"><span style="color:#8b949e;font-size:11px;font-weight:600;">CLUBS :</span> ';
                data.clubs.forEach(function(c) {
                    html += '<span style="display:inline-block;margin:2px 4px;padding:3px 10px;background:#10b98115;border:1px solid #10b98130;border-radius:6px;font-size:12px;color:#34d399;">' + escapeHtml((c.nom_club||'').replace(/\*\s*$/, '')) + '</span>';
                });
                html += '</div>';
            }
            // Records summary
            if (data.records && data.records.length > 0) {
                html += '<div style="margin-bottom:10px;"><span style="color:#8b949e;font-size:11px;font-weight:600;">TOP RECORDS :</span>';
                var topRec = data.records.slice(0, 5);
                topRec.forEach(function(r) {
                    html += '<div style="display:flex;gap:8px;align-items:center;padding:4px 0;border-bottom:1px solid #1e2a3a15;">'
                        + '<span style="color:#a29bfe;font-size:12px;min-width:80px;">' + escapeHtml(r.epreuve || '') + '</span>'
                        + '<span style="color:#60a5fa;font-weight:600;font-size:13px;">' + escapeHtml(r.performance || '') + '</span>'
                        + '</div>';
                });
                html += '</div>';
            }
            // Stats summary
            var stats = [];
            if (data.medailles) {
                var m = data.medailles;
                if (m.or > 0) stats.push('<span style="color:#fbbf24;">&#129351;' + m.or + '</span>');
                if (m.argent > 0) stats.push('<span style="color:#94a3b8;">&#129352;' + m.argent + '</span>');
                if (m.bronze > 0) stats.push('<span style="color:#d97706;">&#129353;' + m.bronze + '</span>');
            }
            if (data.podiums && data.podiums.length > 0) stats.push('<span style="color:#34d399;">' + data.podiums.length + ' podiums</span>');
            if (data.selections && data.selections.length > 0) stats.push('<span style="color:#818cf8;">' + data.selections.length + ' s\u00e9lections</span>');
            if (stats.length) html += '<div style="display:flex;gap:12px;flex-wrap:wrap;font-size:13px;margin-top:8px;">' + stats.join('') + '</div>';
            html += '</div>';
            container.innerHTML = html;
            // Show link + done
            var link = document.getElementById('tutoAthProfileLink');
            if (link) link.href = '?page=profil&id=' + id;
            document.getElementById('tutoAthDoneStep5').style.display = 'block';
            _tutoMarkComplete(5);
        })
        .catch(function() { container.innerHTML = '<div class="loading-msg">Erreur de chargement</div>'; });
}

// ——— Step 6: Advanced search ———
// ——— Init ———
document.addEventListener('DOMContentLoaded', function() {
    // Step 1 is visible by default, trigger its animation
    _tutoTriggerStep(1);
    // Update progress bar from saved state
    _tutoState.completed.forEach(function(n) {
        var dot = document.querySelector('.tuto-progress-step[data-step="' + n + '"]');
        if (dot) dot.classList.add('done');
    });
    // Mark step 1 as active
    var dot1 = document.querySelector('.tuto-progress-step[data-step="1"]');
    if (dot1) dot1.classList.add('active');
});
