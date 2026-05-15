/**
 * comparer.js — Comparateur d'Athletes et de Clubs
 * Extrait de index.php (lignes ~8203-9252)
 *
 * Depends on: Chart.js, BASE_API, escapeHtml(),
 *   addAthleteToBasket(), removeAthleteFromBasket(),
 *   addClubToBasket(), removeClubFromBasket(),
 *   getBasketAthletes(), getBasketClubs(),
 *   updateAllCmpButtons()
 */

// ================================================================
//  COMPARATEUR D'ATHLETES - JS
// ================================================================
var cmpAthletes = []; // [{id, name, data}]
var cmpChart = null;
var cmpRadarChart = null;
var cmpMedChart = null;
var cmpColors = ['#3b82f6','#ec4899','#10b981','#f59e0b','#8b5cf6','#06b6d4','#ef4444','#84cc16'];
var cmpDebounce = null;

// Recherche athletes pour comparateur
(function() {
    var input = document.getElementById('cmpSearch');
    if (!input) return;
    var box = document.getElementById('cmpSuggestions');

    input.addEventListener('input', function() {
        clearTimeout(cmpDebounce);
        var q = this.value.trim();
        if (q.length < 2) { box.style.display = 'none'; return; }
        cmpDebounce = setTimeout(function() {
            fetch(BASE_API + '/search.php?nom=' + encodeURIComponent(q) + '&limit=8')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    box.innerHTML = '';
                    if (!data.success || !data.athletes || data.athletes.length === 0) { box.style.display = 'none'; return; }
                    data.athletes.forEach(function(a) {
                        // Ne pas ajouter si deja dans la liste
                        if (cmpAthletes.find(function(x) { return x.id === a.athlete_id; })) return;
                        var d = document.createElement('div');
                        d.textContent = a.nom_complet + ' (' + (a.categorie || '?') + ' / ' + (a.sexe || '?') + ')';
                        d.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:14px;border-bottom:1px solid #1e2a3a;color:#c9d1d9;';
                        d.onmouseover = function() { d.style.background = '#161b22'; };
                        d.onmouseout = function() { d.style.background = 'transparent'; };
                        d.onclick = function() { addAthleteToCompare(a.athlete_id, a.nom_complet); };
                        box.appendChild(d);
                    });
                    box.style.display = 'block';
                });
        }, 300);
    });

    // Fermer suggestions au clic dehors
    document.addEventListener('click', function(e) {
        if (e.target !== input && e.target !== box) box.style.display = 'none';
    });
})();

function addAthleteToCompare(id, name) {
    if (cmpAthletes.find(function(x) { return x.id === id; })) return;
    if (cmpAthletes.length >= 6) { alert('Maximum 6 athlètes'); return; }

    var searchEl = document.getElementById('cmpSearch');
    var suggestEl = document.getElementById('cmpSuggestions');
    if (searchEl) searchEl.value = '';
    if (suggestEl) suggestEl.style.display = 'none';

    var color = cmpColors[cmpAthletes.length % cmpColors.length];
    cmpAthletes.push({ id: id, name: name, data: null, color: color });
    addAthleteToBasket(id, name);
    updateAllCmpButtons();
    renderSelectedAthletes();

    // Charger les donnees de l'athlete
    fetch(BASE_API + '/athlete.php?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            var ath = cmpAthletes.find(function(x) { return x.id === id; });
            if (ath) ath.data = data;
            updateEpreuveList();
        });
}

function removeFromCompare(id) {
    cmpAthletes = cmpAthletes.filter(function(x) { return x.id !== id; });
    removeAthleteFromBasket(id);
    updateAllCmpButtons();
    renderSelectedAthletes();
    updateEpreuveList();
    if (cmpAthletes.length < 2) {
        document.getElementById('cmpChartArea').style.display = 'none';
    }
}

function renderSelectedAthletes() {
    var container = document.getElementById('cmpSelected');
    container.innerHTML = '';
    cmpAthletes.forEach(function(a) {
        var chip = document.createElement('span');
        chip.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:' + a.color + '22;border:1px solid ' + a.color + '60;border-radius:8px;color:' + a.color + ';font-size:13px;font-weight:600;';
        chip.innerHTML = escapeHtml(a.name) + (a.data ? '' : ' <span style="color:#5a6580;font-size:11px;">chargement...</span>') + ' <span onclick="removeFromCompare(' + a.id + ')" style="cursor:pointer;margin-left:4px;color:#ff6b6b;font-size:16px;">&times;</span>';
        container.appendChild(chip);
    });
}

function updateEpreuveList() {
    var sel = document.getElementById('cmpEpreuve');
    var epreuves = {};

    cmpAthletes.forEach(function(a) {
        if (!a.data) return;
        (a.data.progressions || []).forEach(function(p) {
            if (p.epreuve) epreuves[p.epreuve] = (epreuves[p.epreuve] || 0) + 1;
        });
    });

    // Trier par frequence
    var sorted = Object.keys(epreuves).sort(function(a, b) { return epreuves[b] - epreuves[a]; });

    sel.innerHTML = '<option value="">-- Choisir une epreuve --</option>';
    sorted.forEach(function(ep) {
        var opt = document.createElement('option');
        opt.value = ep;
        opt.textContent = ep + ' (' + epreuves[ep] + ' donnees)';
        sel.appendChild(opt);
    });
}

function compareNow() {
    var ready = cmpAthletes.filter(function(a) { return a.data; });
    if (ready.length < 2) { alert('Ajoutez au moins 2 athlètes (attendez le chargement)'); return; }

    var epreuve = document.getElementById('cmpEpreuve').value;
    document.getElementById('cmpChartArea').style.display = 'block';

    // 1. LINE CHART - Progressions croisees
    if (epreuve) {
        buildProgressionChart(ready, epreuve);
    }

    // 2. RADAR CHART - Epreuves communes
    buildRadarChart(ready);

    // 3. MEDAILLES CHART
    buildMedaillesChart(ready);

    // 4. RECORDS TABLE
    buildRecordsTable(ready);

    // 5. ANALYSE COMPARATIVE TEXTUELLE
    buildComparisonSummary(ready, epreuve);

    // Scroll vers les graphiques
    document.getElementById('cmpChartArea').scrollIntoView({ behavior: 'smooth' });
}

function buildProgressionChart(athletes, epreuve) {
    document.getElementById('cmpChartTitle').textContent = 'Progression — ' + epreuve;

    // Collecter toutes les annees
    var allYears = {};
    var datasets = [];

    athletes.forEach(function(a) {
        var progByYear = {};
        (a.data.progressions || []).forEach(function(p) {
            if (p.epreuve !== epreuve || !p.performance) return;
            var yr = p.annee;
            if (!progByYear[yr] || p.performance < progByYear[yr].perf) {
                progByYear[yr] = { perf: p.performance, brut: p.performance_brut };
            }
            allYears[yr] = true;
        });

        var sortedYears = Object.keys(allYears).sort();
        datasets.push({
            label: a.name,
            yearData: progByYear,
            borderColor: a.color,
            backgroundColor: a.color + '33',
            tension: 0.3,
            pointRadius: 5,
            pointHoverRadius: 9,
            borderWidth: 3,
            fill: false,
            spanGaps: true
        });
    });

    var sortedYears = Object.keys(allYears).sort();
    datasets.forEach(function(ds) {
        ds.data = sortedYears.map(function(y) { return ds.yearData[y] ? ds.yearData[y].perf : null; });
        ds._brutData = sortedYears.map(function(y) { return ds.yearData[y] ? ds.yearData[y].brut : null; });
        delete ds.yearData;
    });

    // Detecter si c'est une epreuve de distance
    var isDistance = /poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon/i.test(epreuve);

    var canvas = document.getElementById('cmpChart');
    if (cmpChart) cmpChart.destroy();
    cmpChart = new Chart(canvas, {
        type: 'line',
        data: { labels: sortedYears, datasets: datasets },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { padding: 16, usePointStyle: true, font: { size: 13, weight: 600 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var brut = ctx.dataset._brutData ? ctx.dataset._brutData[ctx.dataIndex] : null;
                            return ctx.dataset.label + ': ' + (brut || ctx.parsed.y);
                        }
                    }
                }
            },
            scales: {
                x: { grid: { color: '#1e2a3a' }, title: { display: true, text: 'Annee' } },
                y: { grid: { color: '#1e2a3a' }, reverse: !isDistance, title: { display: true, text: isDistance ? 'Performance (plus haut = meilleur)' : 'Performance centisecondes (plus bas = meilleur)' } }
            }
        }
    });
}

function buildRadarChart(athletes) {
    // Trouver les epreuves communes (au moins 2 athletes ont un record)
    var epCounts = {};
    athletes.forEach(function(a) {
        var seen = {};
        (a.data.records || []).forEach(function(r) {
            if (r.epreuve && r.performance && !seen[r.epreuve]) {
                seen[r.epreuve] = true;
                epCounts[r.epreuve] = (epCounts[r.epreuve] || 0) + 1;
            }
        });
    });

    var commonEp = Object.keys(epCounts).filter(function(ep) { return epCounts[ep] >= 2; }).slice(0, 8);
    if (commonEp.length < 2) {
        document.getElementById('cmpRadar').parentElement.parentElement.style.display = 'none';
        return;
    }
    document.getElementById('cmpRadar').parentElement.parentElement.style.display = '';

    // Normaliser: pour chaque epreuve, trouver le max pour normaliser a 100
    var maxPerf = {};
    athletes.forEach(function(a) {
        (a.data.records || []).forEach(function(r) {
            if (commonEp.indexOf(r.epreuve) === -1 || !r.performance) return;
            if (!maxPerf[r.epreuve] || r.performance > maxPerf[r.epreuve]) maxPerf[r.epreuve] = r.performance;
        });
    });

    var radarDatasets = athletes.map(function(a) {
        var data = commonEp.map(function(ep) {
            var best = null;
            (a.data.records || []).forEach(function(r) {
                if (r.epreuve === ep && r.performance) {
                    if (!best || r.performance < best) best = r.performance;
                }
            });
            if (!best || !maxPerf[ep]) return 0;
            // Inverser pour les courses (plus petit = meilleur = score plus haut)
            return Math.round((1 - (best / maxPerf[ep]) + 1) * 50);
        });
        return {
            label: a.name,
            data: data,
            borderColor: a.color,
            backgroundColor: a.color + '22',
            borderWidth: 2,
            pointRadius: 4
        };
    });

    var canvas = document.getElementById('cmpRadar');
    if (cmpRadarChart) cmpRadarChart.destroy();
    cmpRadarChart = new Chart(canvas, {
        type: 'radar',
        data: { labels: commonEp, datasets: radarDatasets },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top', labels: { padding: 12, usePointStyle: true, font: { size: 12 } } } },
            scales: { r: { grid: { color: '#1e2a3a' }, angleLines: { color: '#1e2a3a' }, pointLabels: { font: { size: 10 }, color: '#8b949e' }, ticks: { display: false } } }
        }
    });
}

function buildMedaillesChart(athletes) {
    var labels = athletes.map(function(a) { return a.name; });
    var ors = athletes.map(function(a) { return (a.data.medailles || []).filter(function(m) { return m.type === 'or'; }).length; });
    var argents = athletes.map(function(a) { return (a.data.medailles || []).filter(function(m) { return m.type === 'argent'; }).length; });
    var bronzes = athletes.map(function(a) { return (a.data.medailles || []).filter(function(m) { return m.type === 'bronze'; }).length; });

    var canvas = document.getElementById('cmpMedChart');
    if (cmpMedChart) cmpMedChart.destroy();
    cmpMedChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Or', data: ors, backgroundColor: '#fbbf24', borderRadius: 4 },
                { label: 'Argent', data: argents, backgroundColor: '#d1d5db', borderRadius: 4 },
                { label: 'Bronze', data: bronzes, backgroundColor: '#d97706', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top', labels: { padding: 12, usePointStyle: true } } },
            scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
}

function buildRecordsTable(athletes) {
    // Collecter toutes les epreuves avec records
    var allEp = {};
    athletes.forEach(function(a) {
        (a.data.records || []).forEach(function(r) {
            if (r.epreuve) allEp[r.epreuve] = true;
        });
    });
    var epreuves = Object.keys(allEp).sort();

    var html = '<table class="bk-table">';
    html += '<tr><th style="text-align:left;padding:8px 12px;font-size:12px;border-bottom:1px solid #1e2a3a;">Épreuve</th>';
    athletes.forEach(function(a) {
        html += '<th style="text-align:center;padding:8px 12px;font-size:12px;border-bottom:1px solid #1e2a3a;"><a href="?page=profil&id=' + a.id + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(a.name) + '</a></th>';
    });
    html += '</tr>';

    epreuves.forEach(function(ep) {
        // Trouver la meilleure perf (plus petite) pour highlighter
        var perfs = athletes.map(function(a) {
            var best = null;
            (a.data.records || []).forEach(function(r) {
                if (r.epreuve === ep && r.performance) {
                    if (!best || r.performance < best) best = { perf: r.performance, brut: r.performance_brut };
                }
            });
            return best;
        });
        var bestPerf = Math.min.apply(null, perfs.filter(function(p) { return p; }).map(function(p) { return p.perf; }));

        html += '<tr><td style="padding:8px 12px;border-bottom:1px solid #1e2a3a10;font-size:13px;"><a href="?page=recherche&epreuve=' + encodeURIComponent(ep) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(ep) + '</a></td>';
        perfs.forEach(function(p) {
            var isBest = p && p.perf === bestPerf && athletes.length > 1;
            var style = 'text-align:center;padding:8px 12px;border-bottom:1px solid #1e2a3a10;font-size:13px;font-family:Courier New,monospace;';
            if (isBest) style += 'color:#34d399;font-weight:700;';
            else style += 'color:#c9d1d9;';
            html += '<td style="' + style + '">' + (p ? escapeHtml(p.brut) : '-') + '</td>';
        });
        html += '</tr>';
    });

    html += '</table>';
    document.getElementById('cmpRecordsTable').innerHTML = html;
}

function buildComparisonSummary(athletes, epreuve) {
    var txt = [];

    // --- Helpers ---
    function getMedals(a) {
        var o = 0, ar = 0, b = 0;
        (a.data.medailles || []).forEach(function(m) { if (m.type === 'or') o++; else if (m.type === 'argent') ar++; else if (m.type === 'bronze') b++; });
        return { or: o, argent: ar, bronze: b, total: o + ar + b };
    }
    function getRecords(a) {
        var recs = {};
        (a.data.records || []).forEach(function(r) { if (r.epreuve && r.performance) recs[r.epreuve] = { perf: r.performance, brut: r.performance_brut }; });
        return recs;
    }
    function getYearsActive(a) {
        var min = 9999, max = 0;
        (a.data.progressions || []).forEach(function(p) { if (p.annee) { if (p.annee < min) min = p.annee; if (p.annee > max) max = p.annee; } });
        (a.data.resultats || []).forEach(function(r) { if (r.annee) { if (r.annee < min) min = r.annee; if (r.annee > max) max = r.annee; } });
        return min < 9999 ? { debut: min, fin: max } : null;
    }
    function getNbPodiums(a) {
        return (a.data.podiums || []).length;
    }
    function getNbSelections(a) {
        return (a.data.selections || []).length;
    }
    function getNbResultats(a) {
        return (a.data.resultats || []).length;
    }
    function getNbEpreuves(a) {
        var ep = {};
        (a.data.records || []).forEach(function(r) { if (r.epreuve) ep[r.epreuve] = true; });
        return Object.keys(ep).length;
    }
    function getIdentite(a) {
        return a.data.identite || {};
    }
    function getCat(a) {
        var catMap = { SE: 'Senior', ES: 'Espoir', JU: 'Junior', CA: 'Cadet', MI: 'Minime', V1: 'Veteran', V2: 'Veteran', V3: 'Veteran' };
        var id = getIdentite(a);
        return catMap[id.categorie] || id.categorie || '';
    }

    var a1 = athletes[0], a2 = athletes[1];
    var n1 = a1.name, n2 = a2.name;

    // --- INTRO ---
    txt.push('Cette analyse compare ' + n1 + ' et ' + n2 + ', deux athletes');
    var c1 = getCat(a1), c2 = getCat(a2);
    if (c1 && c2 && c1 === c2) { txt[0] += ' de la categorie ' + c1 + '.'; }
    else if (c1 && c2) { txt[0] += ' (' + n1 + ' en ' + c1 + ', ' + n2 + ' en ' + c2 + ').'; }
    else { txt[0] += '.'; }

    // --- EXPERIENCE / CARRIERE ---
    var y1 = getYearsActive(a1), y2 = getYearsActive(a2);
    if (y1 && y2) {
        var d1 = y1.fin - y1.debut, d2 = y2.fin - y2.debut;
        var oneYear1 = (d1 === 0), oneYear2 = (d2 === 0);
        // Cas ou l'un ou les deux n'ont fait qu'une seule saison
        if (oneYear1 && oneYear2) {
            if (y1.debut === y2.debut) txt.push('Les deux athletes n\'ont effectue qu\'une seule saison, en ' + y1.debut + '.');
            else txt.push(n1 + ' n\'a effectue qu\'une seule saison en ' + y1.debut + ', tout comme ' + n2 + ' en ' + y2.debut + '.');
        } else if (oneYear1) {
            txt.push(n1 + ' n\'a effectue qu\'une seule saison en ' + y1.debut + ', tandis que ' + n2 + ' a ete actif de ' + y2.debut + ' a ' + y2.fin + ' (' + d2 + ' saisons).');
        } else if (oneYear2) {
            txt.push(n2 + ' n\'a effectue qu\'une seule saison en ' + y2.debut + ', tandis que ' + n1 + ' a ete actif de ' + y1.debut + ' a ' + y1.fin + ' (' + d1 + ' saisons).');
        } else {
            if (y1.debut < y2.debut) {
                txt.push(n1 + ' a debute plus tot (des ' + y1.debut + ') tandis que ' + n2 + ' est apparu en ' + y2.debut + '.');
            } else if (y2.debut < y1.debut) {
                txt.push(n2 + ' a debute plus tot (des ' + y2.debut + ') tandis que ' + n1 + ' est apparu en ' + y1.debut + '.');
            }
            if (d1 > d2) txt.push(n1 + ' possede une carriere plus longue avec ' + d1 + ' saisons d\'activite contre ' + d2 + ' pour ' + n2 + '.');
            else if (d2 > d1) txt.push(n2 + ' possede une carriere plus longue avec ' + d2 + ' saisons contre ' + d1 + ' pour ' + n1 + '.');
            else txt.push('Les deux athletes partagent une duree de carriere similaire de ' + d1 + ' saisons.');
        }
    }

    // --- POLYVALENCE ---
    var ep1 = getNbEpreuves(a1), ep2 = getNbEpreuves(a2);
    if (ep1 > 0 && ep2 > 0) {
        if (ep1 > ep2) txt.push('En termes de polyvalence, ' + n1 + ' est plus diversifie avec ' + ep1 + ' disciplines contre ' + ep2 + ' pour ' + n2 + '.');
        else if (ep2 > ep1) txt.push(n2 + ' se montre plus polyvalent avec ' + ep2 + ' disciplines contre ' + ep1 + ' pour ' + n1 + '.');
        else txt.push('Les deux athletes pratiquent le meme nombre de disciplines (' + ep1 + ').');
    }

    // --- DUEL SUR L'EPREUVE SELECTIONNEE ---
    if (epreuve) {
        var r1 = getRecords(a1), r2 = getRecords(a2);
        var rec1 = r1[epreuve], rec2 = r2[epreuve];
        if (rec1 && rec2) {
            if (rec1.perf < rec2.perf) {
                txt.push('Sur le ' + epreuve + ', ' + n1 + ' devance ' + n2 + ' avec un record de ' + rec1.brut + ' contre ' + rec2.brut + '.');
            } else if (rec2.perf < rec1.perf) {
                txt.push('Sur le ' + epreuve + ', ' + n2 + ' prend l\'avantage avec ' + rec2.brut + ' face aux ' + rec1.brut + ' de ' + n1 + '.');
            } else {
                txt.push('Sur le ' + epreuve + ', les deux athletes sont au coude-a-coude avec un record identique de ' + rec1.brut + '.');
            }
        } else if (rec1 && !rec2) {
            txt.push('Seul ' + n1 + ' possede un record au ' + epreuve + ' (' + rec1.brut + '), ' + n2 + ' n\'ayant pas de reference sur cette discipline.');
        } else if (rec2 && !rec1) {
            txt.push('Seul ' + n2 + ' possede un record au ' + epreuve + ' (' + rec2.brut + '), ' + n1 + ' n\'ayant pas de reference sur cette discipline.');
        }
    }

    // --- EPREUVES COMMUNES : qui domine ---
    var r1 = getRecords(a1), r2 = getRecords(a2);
    var communes = [];
    for (var ep in r1) { if (r2[ep]) communes.push(ep); }
    if (communes.length > 0) {
        var wins1 = 0, wins2 = 0, ties = 0;
        communes.forEach(function(ep) {
            if (r1[ep].perf < r2[ep].perf) wins1++;
            else if (r2[ep].perf < r1[ep].perf) wins2++;
            else ties++;
        });
        if (communes.length >= 2) {
            if (wins1 > wins2) txt.push('Sur les ' + communes.length + ' epreuves communes, ' + n1 + ' domine avec ' + wins1 + ' meilleurs records contre ' + wins2 + ' pour ' + n2 + (ties > 0 ? ' (' + ties + ' egalite' + (ties > 1 ? 's' : '') + ')' : '') + '.');
            else if (wins2 > wins1) txt.push('Sur les ' + communes.length + ' epreuves communes, ' + n2 + ' s\'impose avec ' + wins2 + ' meilleurs records contre ' + wins1 + ' pour ' + n1 + (ties > 0 ? ' (' + ties + ' egalite' + (ties > 1 ? 's' : '') + ')' : '') + '.');
            else txt.push('Sur les ' + communes.length + ' epreuves communes, les deux athletes sont a egalite avec ' + wins1 + ' victoires chacun.');
        }
    }

    // --- PALMARES : MEDAILLES ---
    var m1 = getMedals(a1), m2 = getMedals(a2);
    if (m1.total > 0 || m2.total > 0) {
        if (m1.total > 0 && m2.total > 0) {
            if (m1.total > m2.total) {
                txt.push('Au niveau du palmares, ' + n1 + ' se distingue avec ' + m1.total + ' medailles (' + m1.or + ' or, ' + m1.argent + ' argent, ' + m1.bronze + ' bronze) contre ' + m2.total + ' pour ' + n2 + ' (' + m2.or + ' or, ' + m2.argent + ' argent, ' + m2.bronze + ' bronze).');
            } else if (m2.total > m1.total) {
                txt.push('Au niveau du palmares, ' + n2 + ' se distingue avec ' + m2.total + ' medailles (' + m2.or + ' or, ' + m2.argent + ' argent, ' + m2.bronze + ' bronze) contre ' + m1.total + ' pour ' + n1 + ' (' + m1.or + ' or, ' + m1.argent + ' argent, ' + m1.bronze + ' bronze).');
            } else {
                txt.push('Les deux athletes totalisent le meme nombre de medailles (' + m1.total + '), avec ' + m1.or + ' or pour ' + n1 + ' contre ' + m2.or + ' pour ' + n2 + '.');
            }
        } else if (m1.total > 0) {
            txt.push(n1 + ' possede ' + m1.total + ' medaille' + (m1.total > 1 ? 's' : '') + ' a son actif tandis que ' + n2 + ' n\'en compte aucune.');
        } else {
            txt.push(n2 + ' possede ' + m2.total + ' medaille' + (m2.total > 1 ? 's' : '') + ' a son actif tandis que ' + n1 + ' n\'en compte aucune.');
        }
    }

    // --- PODIUMS ---
    var pod1 = getNbPodiums(a1), pod2 = getNbPodiums(a2);
    if (pod1 > 0 || pod2 > 0) {
        if (pod1 > 0 && pod2 > 0) {
            if (pod1 > pod2) txt.push(n1 + ' totalise ' + pod1 + ' podiums en competition contre ' + pod2 + ' pour ' + n2 + '.');
            else if (pod2 > pod1) txt.push(n2 + ' totalise ' + pod2 + ' podiums contre ' + pod1 + ' pour ' + n1 + '.');
            else txt.push('Les deux athletes comptent le meme nombre de podiums (' + pod1 + ').');
        } else if (pod1 > 0) {
            txt.push(n1 + ' a decroche ' + pod1 + ' podium' + (pod1 > 1 ? 's' : '') + ' en competition, ' + n2 + ' n\'en comptant aucun.');
        } else {
            txt.push(n2 + ' a decroche ' + pod2 + ' podium' + (pod2 > 1 ? 's' : '') + ' en competition, ' + n1 + ' n\'en comptant aucun.');
        }
    }

    // --- SELECTIONS ---
    var sel1 = getNbSelections(a1), sel2 = getNbSelections(a2);
    if (sel1 > 0 || sel2 > 0) {
        if (sel1 > 0 && sel2 > 0) {
            if (sel1 > sel2) txt.push(n1 + ' a ete selectionne ' + sel1 + ' fois en equipe nationale contre ' + sel2 + ' pour ' + n2 + '.');
            else if (sel2 > sel1) txt.push(n2 + ' a ete selectionne ' + sel2 + ' fois contre ' + sel1 + ' pour ' + n1 + '.');
            else txt.push('Chacun compte ' + sel1 + ' selection' + (sel1 > 1 ? 's' : '') + ' en equipe nationale.');
        } else if (sel1 > 0) {
            txt.push(n1 + ' a ete selectionne ' + sel1 + ' fois en equipe nationale, contrairement a ' + n2 + '.');
        } else {
            txt.push(n2 + ' a ete selectionne ' + sel2 + ' fois en equipe nationale, contrairement a ' + n1 + '.');
        }
    }

    // --- VOLUME DE COMPETITION ---
    var res1 = getNbResultats(a1), res2 = getNbResultats(a2);
    if (res1 > 0 && res2 > 0) {
        if (res1 > res2 * 1.5) txt.push('En volume, ' + n1 + ' affiche une activite nettement plus dense avec ' + res1 + ' participations en competition contre ' + res2 + ' pour ' + n2 + '.');
        else if (res2 > res1 * 1.5) txt.push(n2 + ' se montre nettement plus actif en competition avec ' + res2 + ' participations contre ' + res1 + ' pour ' + n1 + '.');
        else if (res1 !== res2) txt.push('Les deux athletes affichent un volume de competition comparable : ' + res1 + ' participations pour ' + n1 + ' et ' + res2 + ' pour ' + n2 + '.');
    }

    // --- CONCLUSION ---
    var avantages1 = 0, avantages2 = 0;
    if (m1.total > m2.total) avantages1++; else if (m2.total > m1.total) avantages2++;
    if (pod1 > pod2) avantages1++; else if (pod2 > pod1) avantages2++;
    if (sel1 > sel2) avantages1++; else if (sel2 > sel1) avantages2++;
    if (communes.length > 0) {
        var w1 = 0, w2 = 0;
        communes.forEach(function(ep) { if (r1[ep].perf < r2[ep].perf) w1++; else if (r2[ep].perf < r1[ep].perf) w2++; });
        if (w1 > w2) avantages1++; else if (w2 > w1) avantages2++;
    }
    if (avantages1 > avantages2 && avantages1 >= 2) {
        txt.push('Au regard de l\'ensemble de ces criteres, ' + n1 + ' presente un profil globalement superieur, meme si ' + n2 + ' conserve des atouts indeniables.');
    } else if (avantages2 > avantages1 && avantages2 >= 2) {
        txt.push('Au regard de l\'ensemble de ces criteres, ' + n2 + ' presente un profil globalement superieur, bien que ' + n1 + ' ne soit pas en reste.');
    } else {
        txt.push('Au final, les deux athletes presentent des profils complementaires, chacun ayant ses points forts dans des domaines differents.');
    }

    // --- Pour 3+ athletes : ajouter un paragraphe supplementaire ---
    if (athletes.length > 2) {
        var others = athletes.slice(2);
        var otherNames = others.map(function(a) { return a.name; });
        txt.push('');
        txt.push('Ce comparatif inclut egalement ' + otherNames.join(' et ') + '.');
        others.forEach(function(a) {
            var m = getMedals(a);
            var ep = getNbEpreuves(a);
            var pod = getNbPodiums(a);
            var rec = getRecords(a);
            var parts = [];
            if (ep > 0) parts.push(ep + ' discipline' + (ep > 1 ? 's' : ''));
            if (m.total > 0) parts.push(m.total + ' medaille' + (m.total > 1 ? 's' : ''));
            if (pod > 0) parts.push(pod + ' podium' + (pod > 1 ? 's' : ''));
            if (parts.length > 0) txt.push(a.name + ' affiche ' + parts.join(', ') + '.');

            if (epreuve && rec[epreuve]) {
                txt.push('Au ' + epreuve + ', ' + a.name + ' detient un record de ' + rec[epreuve].brut + '.');
            }
        });
    }

    document.getElementById('cmpSummaryText').innerHTML = '<p>' + txt.join(' ') + '</p>';
}

// ================================================================
//  ONGLETS COMPARER : Athletes / Clubs
// ================================================================
function switchCmpTab(tab) {
    document.getElementById('cmpAthletesPanel').style.display = tab === 'athletes' ? 'block' : 'none';
    document.getElementById('cmpClubsPanel').style.display = tab === 'clubs' ? 'block' : 'none';
    document.getElementById('cmpTabAthletes').className = tab === 'athletes' ? 'active' : '';
    document.getElementById('cmpTabClubs').className = tab === 'clubs' ? 'active' : '';
}

// ================================================================
//  COMPARATEUR DE CLUBS
// ================================================================
var cmpClubs = []; // [{id, name, data, color}]
var clubCharts = {};
var cmpClubDebounce = null;

(function() {
    var input = document.getElementById('cmpClubSearch');
    if (!input) return;
    var box = document.getElementById('cmpClubSuggestions');

    input.addEventListener('input', function() {
        clearTimeout(cmpClubDebounce);
        var q = this.value.trim();
        if (q.length < 2) { box.style.display = 'none'; return; }
        cmpClubDebounce = setTimeout(function() {
            fetch(BASE_API + '/clubs.php?nom=' + encodeURIComponent(q) + '&limit=8')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    box.innerHTML = '';
                    if (!data.success || !data.clubs || data.clubs.length === 0) { box.style.display = 'none'; return; }
                    data.clubs.forEach(function(c) {
                        if (cmpClubs.find(function(x) { return x.id === c.id_club; })) return;
                        var d = document.createElement('div');
                        d.textContent = c.nom_club + ' (' + c.nb_athletes + ' athletes)';
                        d.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:14px;border-bottom:1px solid #1e2a3a;color:#c9d1d9;';
                        d.onmouseover = function() { d.style.background = '#161b22'; };
                        d.onmouseout = function() { d.style.background = 'transparent'; };
                        d.onclick = function() { addClubToCompare(c.id_club, c.nom_club); };
                        box.appendChild(d);
                    });
                    box.style.display = 'block';
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== input && e.target !== box) box.style.display = 'none';
    });
})();

function addClubToCompare(id, name) {
    if (cmpClubs.find(function(x) { return x.id === id; })) return;
    if (cmpClubs.length >= 6) { alert('Maximum 6 clubs'); return; }

    var searchEl = document.getElementById('cmpClubSearch');
    var suggestEl = document.getElementById('cmpClubSuggestions');
    if (searchEl) searchEl.value = '';
    if (suggestEl) suggestEl.style.display = 'none';

    var color = cmpColors[cmpClubs.length % cmpColors.length];
    cmpClubs.push({ id: id, name: name, data: null, color: color });
    addClubToBasket(id, name);
    updateAllCmpButtons();
    renderSelectedClubs();

    fetch(BASE_API + '/club_stats.php?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            var club = cmpClubs.find(function(x) { return x.id === id; });
            if (club) club.data = data;
            renderSelectedClubs();
        });
}

function removeClubFromCompare(id) {
    cmpClubs = cmpClubs.filter(function(x) { return x.id !== id; });
    removeClubFromBasket(id);
    updateAllCmpButtons();
    renderSelectedClubs();
    if (cmpClubs.length < 2) {
        document.getElementById('cmpClubChartArea').style.display = 'none';
    }
}

function renderSelectedClubs() {
    var container = document.getElementById('cmpClubSelected');
    container.innerHTML = '';
    cmpClubs.forEach(function(c) {
        var chip = document.createElement('span');
        chip.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:' + c.color + '22;border:1px solid ' + c.color + '60;border-radius:8px;color:' + c.color + ';font-size:13px;font-weight:600;';
        chip.innerHTML = escapeHtml(c.name) + (c.data ? ' <span style="color:#5a6580;font-size:11px;">(' + c.data.total_athletes + ' ath.)</span>' : ' <span style="color:#5a6580;font-size:11px;">chargement...</span>') + ' <span onclick="removeClubFromCompare(' + c.id + ')" style="cursor:pointer;margin-left:4px;color:#ff6b6b;font-size:16px;">&times;</span>';
        container.appendChild(chip);
    });
}

function compareClubsNow() {
    var ready = cmpClubs.filter(function(c) { return c.data; });
    if (ready.length < 2) { alert('Ajoutez au moins 2 clubs (attendez le chargement)'); return; }

    document.getElementById('cmpClubChartArea').style.display = 'block';

    // Destroy existing charts
    Object.keys(clubCharts).forEach(function(k) { if (clubCharts[k]) clubCharts[k].destroy(); });
    clubCharts = {};

    var labels = ready.map(function(c) { return c.name.length > 25 ? c.name.substring(0, 25) + '...' : c.name; });
    var bgColors = ready.map(function(c) { return c.color; });

    // 1. TOTAL ATHLETES
    clubCharts.total = new Chart(document.getElementById('clubCmpTotal'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{ label: 'Athletes', data: ready.map(function(c) { return c.data.total_athletes; }), backgroundColor: bgColors, borderRadius: 6, barThickness: 40 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true } } }
    });

    // 2. PAR SEXE (grouped bar)
    clubCharts.sexe = new Chart(document.getElementById('clubCmpSexe'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Hommes', data: ready.map(function(c) { return c.data.par_sexe['M'] || 0; }), backgroundColor: '#3b82f6', borderRadius: 4 },
                { label: 'Femmes', data: ready.map(function(c) { return c.data.par_sexe['F'] || 0; }), backgroundColor: '#ec4899', borderRadius: 4 }
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'top', labels: { padding: 12, usePointStyle: true } } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true } } }
    });

    // 3. PAR CATEGORIE (grouped horizontal bar)
    var allCats = {};
    ready.forEach(function(c) { Object.keys(c.data.par_categorie).forEach(function(cat) { allCats[cat] = true; }); });
    var catLabels = Object.keys(allCats).sort();

    clubCharts.cat = new Chart(document.getElementById('clubCmpCat'), {
        type: 'bar',
        data: {
            labels: catLabels,
            datasets: ready.map(function(c) {
                return {
                    label: c.name.length > 20 ? c.name.substring(0, 20) + '...' : c.name,
                    data: catLabels.map(function(cat) { return c.data.par_categorie[cat] || 0; }),
                    backgroundColor: c.color + 'cc',
                    borderRadius: 3
                };
            })
        },
        options: { responsive: true, plugins: { legend: { position: 'top', labels: { padding: 10, usePointStyle: true, font: { size: 10 } } } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true } } }
    });

    // 4. MEDAILLES (grouped bar)
    clubCharts.med = new Chart(document.getElementById('clubCmpMed'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Or', data: ready.map(function(c) { return c.data.medailles.or || 0; }), backgroundColor: '#fbbf24', borderRadius: 4 },
                { label: 'Argent', data: ready.map(function(c) { return c.data.medailles.argent || 0; }), backgroundColor: '#d1d5db', borderRadius: 4 },
                { label: 'Bronze', data: ready.map(function(c) { return c.data.medailles.bronze || 0; }), backgroundColor: '#d97706', borderRadius: 4 }
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'top', labels: { padding: 12, usePointStyle: true } } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true } } }
    });

    // 5. TOP EPREUVES (horizontal bar avec les epreuves communes)
    var allEp = {};
    ready.forEach(function(c) {
        (c.data.top_epreuves || []).forEach(function(e) { allEp[e.epreuve] = true; });
    });
    var epLabels = Object.keys(allEp).slice(0, 10);

    clubCharts.ep = new Chart(document.getElementById('clubCmpEpreuves'), {
        type: 'bar',
        data: {
            labels: epLabels,
            datasets: ready.map(function(c) {
                return {
                    label: c.name.length > 20 ? c.name.substring(0, 20) + '...' : c.name,
                    data: epLabels.map(function(ep) {
                        var found = (c.data.top_epreuves || []).find(function(e) { return e.epreuve === ep; });
                        return found ? found.nb_records : 0;
                    }),
                    backgroundColor: c.color + 'cc',
                    borderRadius: 3
                };
            })
        },
        options: { indexAxis: 'y', responsive: true, plugins: { legend: { position: 'top', labels: { padding: 10, usePointStyle: true, font: { size: 10 } } } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
    });

    // 6. RADAR (athletes, medailles, epreuves, categories normalises)
    var radarLabels = ['Athlètes', 'Médailles Or', 'Médailles Total', 'Catégories', 'Épreuves'];
    var maxVals = [0, 0, 0, 0, 0];
    ready.forEach(function(c) {
        var vals = [
            c.data.total_athletes,
            c.data.medailles.or || 0,
            (c.data.medailles.or || 0) + (c.data.medailles.argent || 0) + (c.data.medailles.bronze || 0),
            Object.keys(c.data.par_categorie).length,
            (c.data.top_epreuves || []).length
        ];
        vals.forEach(function(v, i) { if (v > maxVals[i]) maxVals[i] = v; });
    });

    clubCharts.radar = new Chart(document.getElementById('clubCmpRadar'), {
        type: 'radar',
        data: {
            labels: radarLabels,
            datasets: ready.map(function(c) {
                var vals = [
                    c.data.total_athletes,
                    c.data.medailles.or || 0,
                    (c.data.medailles.or || 0) + (c.data.medailles.argent || 0) + (c.data.medailles.bronze || 0),
                    Object.keys(c.data.par_categorie).length,
                    (c.data.top_epreuves || []).length
                ];
                return {
                    label: c.name.length > 20 ? c.name.substring(0, 20) + '...' : c.name,
                    data: vals.map(function(v, i) { return maxVals[i] > 0 ? Math.round(v / maxVals[i] * 100) : 0; }),
                    borderColor: c.color,
                    backgroundColor: c.color + '22',
                    borderWidth: 2,
                    pointRadius: 4
                };
            })
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } } },
            scales: { r: { grid: { color: '#1e2a3a' }, angleLines: { color: '#1e2a3a' }, pointLabels: { font: { size: 11 }, color: '#8b949e' }, ticks: { display: false }, max: 100 } }
        }
    });

    // 7. TABLEAU TOP ATHLETES
    var html = '<table class="bk-table">';
    ready.forEach(function(c) {
        html += '<tr><td colspan="5" style="padding:12px;color:' + c.color + ';font-weight:700;font-size:15px;border-bottom:2px solid ' + c.color + '40;background:' + c.color + '08;">' + escapeHtml(c.name) + '</td></tr>';
        html += '<tr><th style="text-align:left;padding:6px 12px;font-size:11px;">Athlete</th><th style="padding:6px 12px;font-size:11px;">Cat</th><th style="padding:6px 12px;font-size:11px;">Sexe</th><th style="padding:6px 12px;font-size:11px;">Resultats</th><th style="padding:6px 12px;font-size:11px;">Records</th></tr>';
        (c.data.top_athletes || []).forEach(function(a) {
            html += '<tr>';
            html += '<td style="padding:6px 12px;font-size:13px;border-bottom:1px solid #1e2a3a10;"><a href="?page=profil&id=' + a.athlete_id + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(a.nom_complet) + '</a></td>';
            html += '<td style="padding:6px 12px;font-size:13px;text-align:center;border-bottom:1px solid #1e2a3a10;">' + escapeHtml(a.categorie) + '</td>';
            html += '<td style="padding:6px 12px;font-size:13px;text-align:center;border-bottom:1px solid #1e2a3a10;">' + escapeHtml(a.sexe) + '</td>';
            html += '<td style="padding:6px 12px;font-size:13px;text-align:center;border-bottom:1px solid #1e2a3a10;color:#34d399;">' + a.nb_resultats + '</td>';
            html += '<td style="padding:6px 12px;font-size:13px;text-align:center;border-bottom:1px solid #1e2a3a10;color:#f59e0b;">' + a.nb_records + '</td>';
            html += '</tr>';
        });
    });
    html += '</table>';
    document.getElementById('clubCmpAthletesTable').innerHTML = html;

    buildClubComparisonSummary(ready);

    document.getElementById('cmpClubChartArea').scrollIntoView({ behavior: 'smooth' });
}

function buildClubComparisonSummary(clubs) {
    var txt = [];

    // --- Helpers ---
    function getTotalAthletes(c) { return c.data.total_athletes || 0; }
    function getHommes(c) { return c.data.par_sexe['M'] || 0; }
    function getFemmes(c) { return c.data.par_sexe['F'] || 0; }
    function getMedTotal(c) { return (c.data.medailles.or || 0) + (c.data.medailles.argent || 0) + (c.data.medailles.bronze || 0); }
    function getMedOr(c) { return c.data.medailles.or || 0; }
    function getMedArgent(c) { return c.data.medailles.argent || 0; }
    function getMedBronze(c) { return c.data.medailles.bronze || 0; }
    function getNbCategories(c) { return Object.keys(c.data.par_categorie || {}).length; }
    function getCategories(c) { return Object.keys(c.data.par_categorie || {}); }
    function getNbNationalites(c) { return Object.keys(c.data.nationalites || {}).length; }
    function getNbEpreuves(c) { return (c.data.epreuves || c.data.top_epreuves || []).length; }
    function getEpreuves(c) {
        return (c.data.epreuves || c.data.top_epreuves || []).map(function(e) { return e.epreuve || e.nom_epreuve; });
    }
    function getNbRecords(c) {
        var total = 0;
        (c.data.epreuves || c.data.top_epreuves || []).forEach(function(e) { total += (e.nb_records || e.c || 0); });
        return total;
    }
    function getPeriode(c) {
        return { debut: c.data.annee_debut, fin: c.data.annee_fin };
    }
    function getDuree(c) {
        var p = getPeriode(c);
        if (p.debut && p.fin) return p.fin - p.debut;
        return 0;
    }

    var c1 = clubs[0], c2 = clubs[1];
    var n1 = c1.name, n2 = c2.name;

    // --- INTRO ---
    txt.push('Cette analyse compare les clubs ' + n1 + ' et ' + n2 + '.');

    // --- PERIODE D'ACTIVITE ---
    var p1 = getPeriode(c1), p2 = getPeriode(c2);
    var d1 = getDuree(c1), d2 = getDuree(c2);
    if (p1.debut && p2.debut) {
        var oneS1 = (d1 === 0 && p1.debut), oneS2 = (d2 === 0 && p2.debut);
        if (oneS1 && oneS2) {
            if (p1.debut === p2.debut) txt.push('Les deux clubs n\'ont ete actifs que sur une seule saison, en ' + p1.debut + '.');
            else txt.push(n1 + ' n\'a ete actif qu\'une seule saison en ' + p1.debut + ', tout comme ' + n2 + ' en ' + p2.debut + '.');
        } else if (oneS1) {
            txt.push(n1 + ' n\'a ete actif qu\'une seule saison en ' + p1.debut + ', tandis que ' + n2 + ' a ete actif de ' + p2.debut + ' a ' + p2.fin + ' (' + d2 + ' ans).');
        } else if (oneS2) {
            txt.push(n2 + ' n\'a ete actif qu\'une seule saison en ' + p2.debut + ', tandis que ' + n1 + ' a ete actif de ' + p1.debut + ' a ' + p1.fin + ' (' + d1 + ' ans).');
        } else {
            if (p1.debut < p2.debut) txt.push(n1 + ' est un club plus ancien, actif depuis ' + p1.debut + ', tandis que ' + n2 + ' a demarre en ' + p2.debut + '.');
            else if (p2.debut < p1.debut) txt.push(n2 + ' est un club plus ancien, actif depuis ' + p2.debut + ', tandis que ' + n1 + ' a demarre en ' + p1.debut + '.');
            else txt.push('Les deux clubs ont demarre la meme annee, en ' + p1.debut + '.');
            if (d1 > d2) txt.push(n1 + ' affiche une longevite superieure avec ' + d1 + ' annees d\'activite contre ' + d2 + ' pour ' + n2 + '.');
            else if (d2 > d1) txt.push(n2 + ' affiche une longevite superieure avec ' + d2 + ' annees d\'activite contre ' + d1 + ' pour ' + n1 + '.');
        }
    }

    // --- EFFECTIFS ---
    var t1 = getTotalAthletes(c1), t2 = getTotalAthletes(c2);
    if (t1 > 0 && t2 > 0) {
        if (t1 > t2) txt.push('En termes d\'effectif, ' + n1 + ' est le plus grand club avec ' + t1 + ' athletes contre ' + t2 + ' pour ' + n2 + '.');
        else if (t2 > t1) txt.push(n2 + ' dispose d\'un effectif plus important avec ' + t2 + ' athletes contre ' + t1 + ' pour ' + n1 + '.');
        else txt.push('Les deux clubs comptent le meme nombre d\'athletes (' + t1 + ').');
    }

    // --- REPARTITION PAR SEXE ---
    var h1 = getHommes(c1), f1 = getFemmes(c1), h2 = getHommes(c2), f2 = getFemmes(c2);
    if ((h1 + f1) > 0 && (h2 + f2) > 0) {
        var pctF1 = t1 > 0 ? Math.round(f1 / t1 * 100) : 0;
        var pctF2 = t2 > 0 ? Math.round(f2 / t2 * 100) : 0;
        if (Math.abs(pctF1 - pctF2) > 10) {
            if (pctF1 > pctF2) txt.push(n1 + ' presente une feminisation plus marquee (' + pctF1 + '% de femmes) par rapport a ' + n2 + ' (' + pctF2 + '%).');
            else txt.push(n2 + ' affiche un taux de feminisation plus eleve (' + pctF2 + '% de femmes) contre ' + pctF1 + '% pour ' + n1 + '.');
        } else {
            txt.push('La repartition hommes/femmes est comparable entre les deux clubs (environ ' + pctF1 + '% et ' + pctF2 + '% de femmes respectivement).');
        }
    }

    // --- DIVERSITE DES CATEGORIES ---
    var cat1 = getNbCategories(c1), cat2 = getNbCategories(c2);
    if (cat1 > 0 && cat2 > 0) {
        if (cat1 > cat2) txt.push(n1 + ' couvre davantage de categories d\'age (' + cat1 + ') que ' + n2 + ' (' + cat2 + '), signe d\'une structure plus diversifiee.');
        else if (cat2 > cat1) txt.push(n2 + ' offre plus de diversite de categories (' + cat2 + ') contre ' + cat1 + ' pour ' + n1 + '.');
        else txt.push('Les deux clubs couvrent le meme nombre de categories d\'age (' + cat1 + ').');
    }

    // --- NATIONALITES ---
    var nat1 = getNbNationalites(c1), nat2 = getNbNationalites(c2);
    if (nat1 > 0 || nat2 > 0) {
        if (nat1 > 1 && nat2 > 1) {
            if (nat1 > nat2) txt.push(n1 + ' se distingue par sa diversite internationale avec ' + nat1 + ' nationalites representees contre ' + nat2 + ' pour ' + n2 + '.');
            else if (nat2 > nat1) txt.push(n2 + ' est plus cosmopolite avec ' + nat2 + ' nationalites contre ' + nat1 + ' pour ' + n1 + '.');
            else txt.push('Les deux clubs comptent le meme nombre de nationalites (' + nat1 + ').');
        } else if (nat1 > 1) {
            txt.push(n1 + ' compte ' + nat1 + ' nationalites representees.');
        } else if (nat2 > 1) {
            txt.push(n2 + ' compte ' + nat2 + ' nationalites representees.');
        }
    }

    // --- DISCIPLINES / EPREUVES ---
    var ep1 = getNbEpreuves(c1), ep2 = getNbEpreuves(c2);
    if (ep1 > 0 && ep2 > 0) {
        if (ep1 > ep2) txt.push('En matiere de disciplines, ' + n1 + ' propose un eventail plus large avec ' + ep1 + ' epreuves contre ' + ep2 + ' pour ' + n2 + '.');
        else if (ep2 > ep1) txt.push(n2 + ' couvre plus de disciplines (' + ep2 + ') que ' + n1 + ' (' + ep1 + ').');
        else txt.push('Les deux clubs pratiquent le meme nombre de disciplines (' + ep1 + ').');

        // Epreuves communes
        var epList1 = getEpreuves(c1), epList2 = getEpreuves(c2);
        var communes = epList1.filter(function(e) { return epList2.indexOf(e) !== -1; });
        if (communes.length > 0 && communes.length < Math.max(ep1, ep2)) {
            txt.push('Ils partagent ' + communes.length + ' epreuve' + (communes.length > 1 ? 's' : '') + ' en commun' + (communes.length <= 5 ? ' : ' + communes.join(', ') : '') + '.');
        } else if (communes.length === Math.min(ep1, ep2) && communes.length > 0) {
            txt.push('Toutes les epreuves du club le moins diversifie sont egalement pratiquees par l\'autre.');
        }
    }

    // --- RECORDS ---
    var rec1 = getNbRecords(c1), rec2 = getNbRecords(c2);
    if (rec1 > 0 || rec2 > 0) {
        if (rec1 > 0 && rec2 > 0) {
            if (rec1 > rec2) txt.push(n1 + ' totalise ' + rec1 + ' records enregistres contre ' + rec2 + ' pour ' + n2 + ', temoignant d\'un volume de performance superieur.');
            else if (rec2 > rec1) txt.push(n2 + ' cumule ' + rec2 + ' records contre ' + rec1 + ' pour ' + n1 + '.');
            else txt.push('Les deux clubs comptent le meme nombre de records (' + rec1 + ').');
        } else if (rec1 > 0) {
            txt.push(n1 + ' totalise ' + rec1 + ' records, ' + n2 + ' n\'en ayant aucun d\'enregistre.');
        } else {
            txt.push(n2 + ' totalise ' + rec2 + ' records, ' + n1 + ' n\'en ayant aucun d\'enregistre.');
        }
    }

    // --- MEDAILLES ---
    var mt1 = getMedTotal(c1), mt2 = getMedTotal(c2);
    if (mt1 > 0 || mt2 > 0) {
        var mo1 = getMedOr(c1), ma1 = getMedArgent(c1), mb1 = getMedBronze(c1);
        var mo2 = getMedOr(c2), ma2 = getMedArgent(c2), mb2 = getMedBronze(c2);
        if (mt1 > 0 && mt2 > 0) {
            if (mt1 > mt2) {
                txt.push('Au palmares, ' + n1 + ' domine avec ' + mt1 + ' medailles (dont ' + mo1 + ' en or, ' + ma1 + ' en argent et ' + mb1 + ' en bronze) contre ' + mt2 + ' pour ' + n2 + ' (' + mo2 + ' or, ' + ma2 + ' argent, ' + mb2 + ' bronze).');
            } else if (mt2 > mt1) {
                txt.push(n2 + ' s\'impose au palmares avec ' + mt2 + ' medailles (dont ' + mo2 + ' en or, ' + ma2 + ' en argent et ' + mb2 + ' en bronze) face aux ' + mt1 + ' de ' + n1 + ' (' + mo1 + ' or, ' + ma1 + ' argent, ' + mb1 + ' bronze).');
            } else {
                txt.push('Les deux clubs totalisent le meme nombre de medailles (' + mt1 + '), avec ' + mo1 + ' en or pour ' + n1 + ' contre ' + mo2 + ' pour ' + n2 + '.');
            }
        } else if (mt1 > 0) {
            txt.push(n1 + ' possede ' + mt1 + ' medaille' + (mt1 > 1 ? 's' : '') + ' a son actif (dont ' + mo1 + ' en or), tandis que ' + n2 + ' n\'en compte aucune.');
        } else {
            txt.push(n2 + ' possede ' + mt2 + ' medaille' + (mt2 > 1 ? 's' : '') + ' a son actif (dont ' + mo2 + ' en or), tandis que ' + n1 + ' n\'en compte aucune.');
        }
    }

    // --- TOP ATHLETES ---
    var top1 = c1.data.top_athletes || [], top2 = c2.data.top_athletes || [];
    if (top1.length > 0 && top2.length > 0) {
        var best1 = top1[0], best2 = top2[0];
        txt.push('L\'athlete le plus actif de ' + n1 + ' est ' + best1.nom_complet + ' avec ' + best1.nb_resultats + ' resultats et ' + best1.nb_records + ' records, tandis que chez ' + n2 + ' c\'est ' + best2.nom_complet + ' (' + best2.nb_resultats + ' resultats, ' + best2.nb_records + ' records).');
    }

    // --- CONCLUSION ---
    var avantages1 = 0, avantages2 = 0;
    if (t1 > t2) avantages1++; else if (t2 > t1) avantages2++;
    if (mt1 > mt2) avantages1++; else if (mt2 > mt1) avantages2++;
    if (ep1 > ep2) avantages1++; else if (ep2 > ep1) avantages2++;
    if (rec1 > rec2) avantages1++; else if (rec2 > rec1) avantages2++;
    if (cat1 > cat2) avantages1++; else if (cat2 > cat1) avantages2++;
    if (nat1 > nat2) avantages1++; else if (nat2 > nat1) avantages2++;

    if (avantages1 > avantages2 && avantages1 >= 3) {
        txt.push('En conclusion, ' + n1 + ' se demarque comme le club le plus complet sur la majorite des criteres analyses, meme si ' + n2 + ' conserve des atouts dans certains domaines.');
    } else if (avantages2 > avantages1 && avantages2 >= 3) {
        txt.push('En conclusion, ' + n2 + ' se positionne comme le club le plus complet sur la majorite des criteres, bien que ' + n1 + ' presente egalement des points forts notables.');
    } else {
        txt.push('En conclusion, les deux clubs presentent des profils complementaires avec chacun leurs forces, offrant des atouts differents a leurs athletes respectifs.');
    }

    // --- Pour 3+ clubs ---
    if (clubs.length > 2) {
        var others = clubs.slice(2);
        var otherNames = others.map(function(c) { return c.name; });
        txt.push('');
        txt.push('Ce comparatif inclut egalement ' + otherNames.join(' et ') + '.');
        others.forEach(function(c) {
            var parts = [];
            var ta = getTotalAthletes(c);
            if (ta > 0) parts.push(ta + ' athlete' + (ta > 1 ? 's' : ''));
            var mt = getMedTotal(c);
            if (mt > 0) parts.push(mt + ' medaille' + (mt > 1 ? 's' : ''));
            var ep = getNbEpreuves(c);
            if (ep > 0) parts.push(ep + ' discipline' + (ep > 1 ? 's' : ''));
            var nr = getNbRecords(c);
            if (nr > 0) parts.push(nr + ' records');
            if (parts.length > 0) txt.push(c.name + ' regroupe ' + parts.join(', ') + '.');
        });
    }

    document.getElementById('cmpClubSummaryText').innerHTML = '<p>' + txt.join(' ') + '</p>';
}

// ════════════ AUTO-LOAD from localStorage basket ════════════
(function() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('page') !== 'comparer') return;

    var basketAth = getBasketAthletes();
    var basketClb = getBasketClubs();

    if (basketAth.length > 0) {
        basketAth.forEach(function(a) { addAthleteToCompare(a.id, a.name); });
    }
    if (basketClb.length > 0) {
        if (basketAth.length === 0) switchCmpTab('clubs');
        basketClb.forEach(function(c) { addClubToCompare(c.id, c.name); });
    }
})();
