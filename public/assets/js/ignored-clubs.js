/**
 * ignored-clubs.js
 * Gestion des clubs ignores (masques) via localStorage.
 * Extracted from index.php — fonctions inchangees.
 */

// ═══════════ CLUBS IGNORES (localStorage) ═══════════
function getIgnoredClubs() {
    try { return JSON.parse(localStorage.getItem('bk_ignored_clubs') || '[]'); } catch(e) { return []; }
}
function saveIgnoredClubs(list) { localStorage.setItem('bk_ignored_clubs', JSON.stringify(list)); }
function isClubIgnoredById(id) {
    return !!getIgnoredClubs().find(function(c) { return c.id === id; });
}
function isClubIgnoredByName(name) {
    var n = name.toLowerCase();
    return !!getIgnoredClubs().find(function(c) { return c.name.toLowerCase() === n; });
}

// ═══════════ GESTION CLUBS IGNORES ═══════════
function addIgnoredClub(id, name) {
    var list = getIgnoredClubs();
    if (list.find(function(c) { return c.id === id; })) return;
    list.push({ id: id, name: name });
    saveIgnoredClubs(list);
    applyIgnoredClubs();
    renderIgnoredPanel();
}
function removeIgnoredClub(id) {
    saveIgnoredClubs(getIgnoredClubs().filter(function(c) { return c.id !== id; }));
    applyIgnoredClubs();
    renderIgnoredPanel();
}
function toggleIgnoreClub(btn, id, name) {
    if (isClubIgnoredById(id)) {
        removeIgnoredClub(id);
    } else {
        addIgnoredClub(id, name);
    }
}
function applyIgnoredClubs() {
    var ignored = getIgnoredClubs();
    var ignoredNames = ignored.map(function(c) { return c.name.toLowerCase(); });
    document.querySelectorAll('tr[data-club-name]').forEach(function(row) {
        var name = row.getAttribute('data-club-name').toLowerCase();
        row.style.display = ignoredNames.indexOf(name) !== -1 ? 'none' : '';
    });
    rebuildClubCharts();
}
function renderIgnoredPanel() {
    var panel = document.getElementById('ignoredClubsPanel');
    if (!panel) return;
    var list = getIgnoredClubs();
    if (list.length === 0) { panel.style.display = 'none'; return; }
    panel.style.display = 'block';
    document.getElementById('ignoredClubsCount').textContent = '(' + list.length + ')';
    var html = '';
    list.forEach(function(c) {
        html += '<span class="ignored-chip">' + escapeHtml(c.name) + ' <span class="restore" onclick="removeIgnoredClub(' + c.id + ')">&#8617; Restaurer</span></span>';
    });
    document.getElementById('ignoredClubsList').innerHTML = html;
}
function rebuildClubCharts() {
    var ignored = getIgnoredClubs();
    var ignoredNames = ignored.map(function(c) { return c.name.toLowerCase(); });

    // --- Accueil: Top Clubs doughnut ---
    var canvas1 = document.getElementById('chartClubs');
    if (canvas1 && window._topClubsRaw) {
        var filtered = window._topClubsRaw.filter(function(c) {
            return ignoredNames.indexOf(c.name.toLowerCase()) === -1;
        });
        if (window._topClubsChart) window._topClubsChart.destroy();
        var ctx1 = canvas1.getContext('2d');
        var grad1 = ctx1.createLinearGradient(0, 0, 0, 300);
        grad1.addColorStop(0, '#6c5ce7'); grad1.addColorStop(1, '#fd79a8');
        var colors1 = ['#6c5ce7','#fd79a8','#55efc4','#fdcb6e','#ff7675','#a29bfe','#e84393','#00cec9','#e17055','#00b894'];
        window._topClubsChart = new Chart(canvas1, {
            type: 'doughnut',
            data: {
                labels: filtered.map(function(c) { return c.name; }),
                datasets: [{
                    data: filtered.map(function(c) { return c.count; }),
                    backgroundColor: colors1.slice(0, filtered.length),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { color: '#8b949e', padding: 8, font: { size: 11 } } }
                }
            }
        });
    }

    // --- Page Clubs: bar chart + period chart ---
    var canvas2 = document.getElementById('clubsChart');
    var canvas3 = document.getElementById('clubsPeriodChart');
    if ((canvas2 || canvas3) && window._clubsPageRaw) {
        var filtered2 = window._clubsPageRaw.filter(function(c) {
            return ignoredNames.indexOf(c.fullName.toLowerCase()) === -1;
        });

        if (canvas2) {
            if (window._clubsPageChart) window._clubsPageChart.destroy();
            var ctx2 = canvas2.getContext('2d');
            var grad2 = ctx2.createLinearGradient(0, 0, 0, 300);
            grad2.addColorStop(0, 'rgba(139,92,246,0.8)'); grad2.addColorStop(1, 'rgba(139,92,246,0.1)');
            window._clubsPageChart = new Chart(canvas2, {
                type: 'bar',
                data: {
                    labels: filtered2.map(function(c) { return c.labelShort; }),
                    datasets: [{
                        label: 'Athletes',
                        data: filtered2.map(function(c) { return c.count; }),
                        backgroundColor: grad2,
                        borderRadius: 6, borderSkipped: false
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: '#1e2a3a' }, ticks: { font: { size: 10 }, maxRotation: 45 } },
                        y: { grid: { color: '#1e2a3a' }, beginAtZero: true }
                    }
                }
            });
        }

        if (canvas3) {
            if (window._clubsPeriodChart) window._clubsPeriodChart.destroy();
            window._clubsPeriodChart = new Chart(canvas3, {
                type: 'bar',
                data: {
                    labels: filtered2.map(function(c) { return c.labelShort; }),
                    datasets: [
                        { label: 'Debut', data: filtered2.map(function(c) { return c.start; }), backgroundColor: '#14b8a6', borderRadius: 4 },
                        { label: 'Fin', data: filtered2.map(function(c) { return c.end; }), backgroundColor: '#f59e0b', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#8b949e' } } },
                    scales: {
                        x: { stacked: false, grid: { color: '#1e2a3a' }, ticks: { font: { size: 10 }, maxRotation: 45 } },
                        y: { stacked: false, grid: { color: '#1e2a3a' }, min: 1990 }
                    }
                }
            });
        }
    }
}
