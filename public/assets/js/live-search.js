/**
 * live-search.js — Live search factory + instances
 * Extracted from index.php (lines ~7905-8199)
 *
 * Dependencies (must be loaded before this file):
 *   - BASE_API (global)
 *   - escapeHtml(), _nivBadge(), _highestNiveau() from utils or index.php
 *   - isAthleteInBasket(), toggleAthleteBasket() from basket.js
 *   - isClubInBasket(), toggleClubBasket() from basket.js
 *   - isClubIgnoredById(), toggleIgnoreClub() from ignored-clubs.js
 *   - openClubDetail() from index.php
 *   - _rchExtraParams (set inline in index.php via PHP)
 */

// --- Search tracking helper ---
function _trackSearch(params) {
    try {
        navigator.sendBeacon(BASE_API + '/search_track.php', JSON.stringify(params));
    } catch(e) {}
}
var _trackTimer = null;

function liveSearch(inputId, statusId, resultsId, paginatedId, config) {
    const input = document.getElementById(inputId);
    const status = document.getElementById(statusId);
    const results = document.getElementById(resultsId);
    const paginated = document.getElementById(paginatedId);
    if (!input) return;

    let timer = null;
    let controller = null;

    input.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(timer);
        clearTimeout(_trackTimer);

        var minLen = config.minLength || 2;
        if (q.length < minLen) {
            input.style.borderColor = '#1a2540';
            input.classList.remove('ls-loading');
            results.style.display = 'none';
            results.innerHTML = '';
            paginated.style.display = 'block';
            status.textContent = q.length > 0 && q.length < minLen ? 'Tapez au moins ' + minLen + ' caractère' + (minLen > 1 ? 's' : '') + '...' : '';
            status.className = 'ls-status';
            return;
        }

        input.style.borderColor = '#a29bfe';
        input.classList.add('ls-loading');
        status.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;"><span class="ls-spinner"></span> Recherche en cours...</span>';
        status.className = 'ls-status loading';

        timer = setTimeout(async () => {
            if (controller) controller.abort();
            controller = new AbortController();

            try {
                const url = config.url(q);
                const resp = await fetch(url, { signal: controller.signal });
                const rawText = await resp.text();
                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (parseErr) {
                    console.error('[live-search] JSON parse failed', {
                        url, status: resp.status,
                        contentType: resp.headers.get('content-type'),
                        bodyPreview: rawText.substring(0, 500),
                    });
                    throw parseErr;
                }

                input.style.borderColor = '#1a2540';
                input.classList.remove('ls-loading');

                if (!data.success) {
                    status.textContent = data.error || ('Erreur HTTP ' + resp.status);
                    status.className = 'ls-status error';
                    input.style.borderColor = '#ff7675';
                    console.warn('[live-search] API a renvoye success=false', { url, status: resp.status, data });
                    return;
                }

                const items = data[config.key];
                const total = data.total || 0;

                status.innerHTML = '<span style="color:#34d399;">&#10003;</span> ' + total + ' résultat' + (total > 1 ? 's' : '') + (total > 50 ? ' (50 affichés)' : '');
                status.className = 'ls-status';
                input.style.borderColor = '#34d399';
                setTimeout(function() { input.style.borderColor = '#1a2540'; }, 1500);

                paginated.style.display = 'none';

                if (!items || items.length === 0) {
                    results.innerHTML = '<p style="color:#484f58;text-align:center;padding:20px;">Aucun résultat pour "' + escapeHtml(q) + '"</p>';
                    results.style.display = 'block';
                    // Track search with 0 results after 2s settled
                    clearTimeout(_trackTimer);
                    _trackTimer = setTimeout(function() {
                        _trackSearch({ q: q, type: config.trackType || 'general', source: 'live_search', results: 0, pg: config.trackPage || '' });
                    }, 2000);
                    return;
                }

                results.innerHTML = config.render(items, q);
                results.style.display = 'block';

                // Track search after 2s settled (debounce: only the final query)
                clearTimeout(_trackTimer);
                _trackTimer = setTimeout(function() {
                    _trackSearch({ q: q, type: config.trackType || 'general', source: 'live_search', results: total, pg: config.trackPage || '' });
                }, 2000);

            } catch (e) {
                if (e.name === 'AbortError') return;
                input.style.borderColor = '#ff7675';
                input.classList.remove('ls-loading');
                status.textContent = 'Erreur de connexion (' + (e.name || 'fetch') + ')';
                status.className = 'ls-status error';
                console.error('[live-search] fetch ou parse echoue', e);
            }
        }, 300);
    });
}

function highlight(text, query) {
    if (!text) return '';
    const safe = escapeHtml(text);
    const regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return safe.replace(regex, '<mark style="background:#1f6feb44;color:#58a6ff;">$1</mark>');
}

// --- ATHLETES ---
liveSearch('lsAthletes', 'lsAthletesStatus', 'lsAthletesResults', 'athletesPaginated', {
    url: q => BASE_API + '/search.php?nom=' + encodeURIComponent(q) + '&limit=50',
    key: 'athletes',
    trackType: 'athlete',
    trackPage: 'athletes',
    render: (items, q) => {
        var thAth = '<tr><th>#</th><th>Athlète</th><th>Cat</th><th>Sexe</th><th>NAT</th><th>Niveaux</th><th>Records (top 5)</th><th></th><th></th></tr>';
        let html = '<div class="table-wrap">';
        html += '<table class="bk-table">' + thAth + '</table>';
        html += '<table class="bk-table">';
        var _num = 0;
        items.forEach(a => {
            _num++;
            var inBasket = isAthleteInBasket(a.athlete_id);
            var nbRec = a.nb_records || 0;
            var topRecs = a.top_records || [];
            var recHtml = '';
            if (topRecs.length > 0) {
                topRecs.forEach(function(tr) {
                    recHtml += '<div style="font-size:11px;line-height:1.6;"><a href="?page=recherche&epreuve=' + encodeURIComponent(tr.epreuve) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(tr.epreuve) + '</a> <span class="perf-val" style="font-size:11px;">' + escapeHtml(tr.performance) + '</span> ' + _nivBadge(tr.top_niveau || _highestNiveau(tr.niveaux || [])) + '</div>';
                });
            } else if (nbRec > 0) {
                recHtml = '<span class="badge badge-perf">' + nbRec + '</span>';
            } else {
                recHtml = '-';
            }
            html += '<tr>'
                + '<td>' + _num + '</td>'
                + '<td><b><a href="?page=profil&id=' + a.athlete_id + '">' + highlight(a.nom_complet, q) + '</a></b>'
                + (a.date_naissance ? '<br><span style="font-size:11px;color:#5a6580;">' + a.date_naissance.substring(0,4) + '</span>' : '')
                + '</td>'
                + '<td><span class="badge badge-cat">' + escapeHtml(a.categorie) + '</span></td>'
                + '<td><span class="badge badge-' + (a.sexe||'').toLowerCase() + '">' + escapeHtml(a.sexe) + '</span></td>'
                + '<td>' + escapeHtml(a.nationalite) + '</td>'
                + '<td>' + _nivBadge(_highestNiveau(a.niveaux || [])) + '</td>'
                + '<td>' + recHtml + '</td>'
                + '<td><a href="?page=profil&id=' + a.athlete_id + '&s=records" style="font-size:12px;">Profil</a></td>'
                + '<td><button class="btn-cmp-add' + (inBasket ? ' added' : '') + '" data-cmp-ath="' + a.athlete_id + '" data-name="' + escapeHtml(a.nom_complet) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">' + (inBasket ? '\u2713' : '+') + '</button></td>'
                + '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thAth + '</table>';
        html += '</div>';
        return html;
    }
});

// --- RECHERCHE ---
// NOTE: _rchExtraParams must be defined inline in index.php (PHP-generated) before this script loads
liveSearch('lsRecherche', 'lsRechercheStatus', 'lsRechercheResults', 'recherchePaginated', {
    url: q => BASE_API + '/search.php?nom=' + encodeURIComponent(q) + '&limit=50' + (_rchExtraParams ? '&' + _rchExtraParams : ''),
    minLength: _rchExtraParams ? 1 : 2,
    key: 'athletes',
    trackType: 'athlete',
    trackPage: 'recherche',
    render: (items, q) => {
        var thAth2 = '<tr><th>#</th><th>Nom complet</th><th>Naissance</th><th>Cat</th><th>Sexe</th><th>NAT</th><th>Niveaux</th><th>Records</th><th></th><th></th></tr>';
        let html = '<div class="table-wrap">';
        html += '<table class="bk-table">' + thAth2 + '</table>';
        html += '<table class="bk-table">';
        var _num = 0;
        items.forEach(a => {
            _num++;
            var inBasket = isAthleteInBasket(a.athlete_id);
            var nbRec = a.nb_records || 0;
            html += '<tr>'
                + '<td>' + _num + '</td>'
                + '<td><b><a href="?page=profil&id=' + a.athlete_id + '">' + highlight(a.nom_complet, q) + '</a></b></td>'
                + '<td>' + (a.date_naissance ? a.date_naissance.substring(0,4) : '-') + '</td>'
                + '<td><span class="badge badge-cat">' + escapeHtml(a.categorie) + '</span></td>'
                + '<td><span class="badge badge-' + (a.sexe||'').toLowerCase() + '">' + escapeHtml(a.sexe) + '</span></td>'
                + '<td>' + escapeHtml(a.nationalite) + '</td>'
                + '<td>' + _nivBadge(_highestNiveau(a.niveaux || [])) + '</td>'
                + '<td>' + (nbRec > 0 ? '<span class="badge badge-perf">' + nbRec + '</span>' : '-') + '</td>'
                + '<td><a href="?page=profil&id=' + a.athlete_id + '&s=records">Profil</a></td>'
                + '<td><button class="btn-cmp-add' + (inBasket ? ' added' : '') + '" data-cmp-ath="' + a.athlete_id + '" data-name="' + escapeHtml(a.nom_complet) + '" onclick="toggleAthleteBasket(this,parseInt(this.dataset.cmpAth),this.dataset.name)">' + (inBasket ? '\u2713' : '+') + '</button></td>'
                + '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thAth2 + '</table>';
        html += '</div>';
        return html;
    }
});

// --- CLUBS ---
liveSearch('lsClubs', 'lsClubsStatus', 'lsClubsResults', 'clubsPaginated', {
    url: q => BASE_API + '/clubs.php?has_athletes=1&max_athletes=5000&nom=' + encodeURIComponent(q) + '&limit=50',
    key: 'clubs',
    trackType: 'club',
    trackPage: 'clubs',
    render: (items, q) => {
        var thClub = '<tr><th>#</th><th>Club</th><th>Athlètes</th><th></th><th></th><th></th></tr>';
        let html = '<div class="table-wrap">';
        html += '<table class="bk-table">' + thClub + '</table>';
        html += '<table class="bk-table">';
        var num = 0;
        items.forEach((c, i) => {
            if (isClubIgnoredById(c.id_club)) return;
            num++;
            var inBasket = isClubInBasket(c.id_club);
            html += '<tr data-club-name="' + escapeHtml(c.nom_club) + '">'
                + '<td>' + num + '</td>'
                + '<td><b><a href="#clubDetailPanel" onclick="openClubDetail(' + c.id_club + ');return false;" style="color:#a29bfe;text-decoration:none;cursor:pointer;">' + highlight(c.nom_club, q) + '</a></b></td>'
                + '<td>' + c.nb_athletes + '</td>'
                + '<td><a href="?page=recherche&club=' + encodeURIComponent(c.nom_club) + '">Voir athletes</a></td>'
                + '<td><button class="btn-cmp-add btn-cmp-add-club' + (inBasket ? ' added' : '') + '" data-cmp-club="' + c.id_club + '" data-name="' + escapeHtml(c.nom_club) + '" onclick="toggleClubBasket(this,parseInt(this.dataset.cmpClub),this.dataset.name)">' + (inBasket ? '\u2713' : '+') + '</button></td>'
                + '<td><button class="btn-cmp-ignore" data-ignore-club="' + c.id_club + '" data-name="' + escapeHtml(c.nom_club) + '" onclick="toggleIgnoreClub(this,parseInt(this.dataset.ignoreClub),this.dataset.name)" title="Ignorer ce club">&#8856;</button></td>'
                + '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thClub + '</table>';
        html += '</div>';
        return html;
    }
});

// --- EPREUVES ---
liveSearch('lsEpreuves', 'lsEpreuvesStatus', 'lsEpreuvesResults', 'epreuvesPaginated', {
    url: q => BASE_API + '/epreuves.php?has_athletes=1&nom=' + encodeURIComponent(q) + '&limit=50',
    key: 'epreuves',
    trackType: 'epreuve',
    trackPage: 'epreuves',
    render: (items, q) => {
        var thEpLs = '<tr><th>#</th><th>Épreuve</th><th>Athlètes avec record</th></tr>';
        let html = '<div class="table-wrap">';
        html += '<table class="bk-table">' + thEpLs + '</table>';
        html += '<table class="bk-table">';
        items.forEach((e, i) => {
            html += '<tr>'
                + '<td>' + (i + 1) + '</td>'
                + '<td><b><a href="?page=recherche&epreuve=' + encodeURIComponent(e.nom_epreuve) + '" style="color:#a29bfe;text-decoration:none;cursor:pointer;">' + highlight(e.nom_epreuve, q) + '</a></b></td>'
                + '<td>' + e.nb_athletes + '</td>'
                + '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thEpLs + '</table>';
        html += '</div>';
        return html;
    }
});

// --- VILLES ---
liveSearch('lsVilles', 'lsVillesStatus', 'lsVillesResults', 'villesPaginated', {
    url: q => BASE_API + '/villes.php?has_athletes=1&nom=' + encodeURIComponent(q) + '&limit=50',
    key: 'villes',
    trackType: 'ville',
    trackPage: 'villes',
    render: (items, q) => {
        var thVille = '<tr><th>#</th><th>Ville</th><th>Athlètes</th><th>Période</th><th>Top 3 niveaux</th></tr>';
        let html = '<div class="table-wrap">';
        html += '<table class="bk-table">' + thVille + '</table>';
        html += '<table class="bk-table">';
        items.forEach((v, i) => {
            var periode = v.annee_debut ? v.annee_debut + ' - ' + (v.annee_fin || '...') : '-';
            var niveaux = '-';
            if (v.top_niveaux && v.top_niveaux.length > 0) {
                niveaux = v.top_niveaux.map(n => {
                    var fc = (n.niveau||'')[0] || '';
                    var bg, bc, tc;
                    if (fc === 'N') { bg='#e11d4820'; bc='#e11d48'; tc='#fb7185'; }
                    else if (fc === 'I') { bg='#c026d320'; bc='#c026d3'; tc='#e879f9'; }
                    else if (fc === 'R') { bg='#0891b220'; bc='#0891b2'; tc='#22d3ee'; }
                    else { bg='#f9731620'; bc='#f97316'; tc='#fb923c'; }
                    return '<span style="display:inline-block;margin:1px 2px;padding:2px 8px;border-radius:6px;font-size:11px;background:'+bg+';border:1px solid '+bc+'40;color:'+tc+';">' + escapeHtml(n.niveau) + ' <b>' + n.pct + '%</b></span>';
                }).join('');
            }
            html += '<tr>'
                + '<td>' + (i + 1) + '</td>'
                + '<td><b><a href="?page=villes&open=' + encodeURIComponent(v.nom_ville) + '" style="color:#a29bfe;text-decoration:none;cursor:pointer;">' + highlight(v.nom_ville, q) + '</a></b></td>'
                + '<td>' + v.nb_athletes + '</td>'
                + '<td>' + periode + '</td>'
                + '<td>' + niveaux + '</td>'
                + '</tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thVille + '</table>';
        html += '</div>';
        return html;
    }
});
