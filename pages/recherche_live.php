<?php $currentPage = basename(__FILE__); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche Athletes</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; }

        .container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }

        h1 { text-align: center; color: #00d4ff; margin-bottom: 30px; font-size: 1.8em; }

        /* Filtres */
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-bottom: 20px;
        }
        .filters button {
            padding: 8px 16px;
            border: 2px solid #333;
            border-radius: 20px;
            background: #16213e;
            color: #aaa;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filters button:hover {
            border-color: #00d4ff;
            color: #fff;
        }
        .filters button.active {
            background: #00d4ff;
            color: #1a1a2e;
            border-color: #00d4ff;
            font-weight: bold;
        }

        /* Barre de recherche */
        .search-box {
            position: relative;
            width: 100%;
            margin-bottom: 10px;
        }
        .search-box input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            font-size: 18px;
            border: 2px solid #333;
            border-radius: 12px;
            background: #16213e;
            color: #fff;
            outline: none;
            transition: border-color 0.3s;
        }
        .search-box input:focus {
            border-color: #00d4ff;
        }
        .search-box input::placeholder { color: #666; }
        .search-box .icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #666;
        }

        /* Indicateur */
        .status {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-bottom: 20px;
            min-height: 20px;
        }
        .status.loading { color: #00d4ff; }
        .status.error { color: #ff4757; }

        /* Resultats */
        .results { list-style: none; }
        .results li {
            background: #16213e;
            border: 1px solid #2a2a4a;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .results li:hover {
            background: #1e2d50;
            border-color: #00d4ff;
            transform: translateX(4px);
        }
        .results li .nom {
            font-size: 16px;
            font-weight: bold;
            color: #fff;
        }
        .results li .nom mark {
            background: none;
            color: #00d4ff;
            font-weight: bold;
        }
        .results li .infos {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
            color: #aaa;
            margin-top: 4px;
        }
        .results li .infos span { white-space: nowrap; }
        .results li .badge {
            background: #0a3d62;
            color: #00d4ff;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            white-space: nowrap;
        }
        .results li .arrow { color: #555; font-size: 20px; }
        .results li:hover .arrow { color: #00d4ff; }
        .left { flex: 1; }

        /* Aucun resultat */
        .no-result {
            text-align: center;
            color: #666;
            padding: 40px;
            font-size: 16px;
            cursor: default;
        }
        .no-result:hover { transform: none; border-color: #2a2a4a; background: #16213e; }

        /* Compteur */
        .count {
            text-align: right;
            color: #666;
            font-size: 13px;
            margin-bottom: 12px;
        }

        /* Badge filtre actif */
        .active-filter {
            text-align: center;
            margin-bottom: 15px;
        }
        .active-filter span {
            display: inline-block;
            background: #0a3d62;
            color: #00d4ff;
            padding: 4px 14px;
            border-radius: 12px;
            font-size: 13px;
        }

        /* RESPONSIVE */
        @media (max-width: 600px) {
            .container { padding: 20px 10px; }
            h1 { font-size: 1.3em; margin-bottom: 20px; }
            .search-box input { font-size: 16px; padding: 12px 14px 12px 42px; }
            .search-box .icon { font-size: 18px; left: 12px; }
            .filters button { padding: 6px 12px; font-size: 12px; }
            .results li { padding: 10px 12px; flex-wrap: wrap; gap: 8px; }
            .results li .nom { font-size: 14px; }
            .results li .infos { font-size: 12px; gap: 8px; }
            .results li .badge { font-size: 11px; padding: 3px 8px; }
            .results li .arrow { display: none; }
        }
    </style>
</head>
<body>
<?php include dirname(__DIR__) . '/nav.php'; ?>
<div class="container">
    <h1>Recherche</h1>

    <div class="filters" id="filters">
        <button class="active" data-filter="nom" data-placeholder="Tapez un nom...">Nom</button>
        <button data-filter="club" data-placeholder="Tapez un club...">Club</button>
        <button data-filter="ville" data-placeholder="Tapez une ville...">Ville</button>
        <button data-filter="epreuve" data-placeholder="Tapez une epreuve...">Epreuve</button>
        <button data-filter="competition" data-placeholder="Tapez une competition...">Competition</button>
    </div>

    <div class="active-filter" id="activeFilter">
        <span>Recherche par : Nom complet</span>
    </div>

    <div class="search-box">
        <span class="icon">&#128269;</span>
        <input type="text" id="search" placeholder="Tapez un nom..." autofocus autocomplete="off">
    </div>

    <div class="status" id="status"></div>
    <div class="count" id="count"></div>
    <ul class="results" id="results"></ul>

    <!-- Sous-liste athletes apres clic sur ville/club/etc -->
    <div id="subSection" style="display:none;">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:15px;">
            <button id="backBtn" style="background:#0a3d62; border:none; color:#00d4ff; padding:8px 16px; border-radius:8px; cursor:pointer; font-size:14px;">&#8592; Retour</button>
            <span id="subTitle" style="color:#00d4ff; font-size:16px; font-weight:bold;"></span>
        </div>
        <div class="count" id="subCount"></div>
        <ul class="results" id="subResults"></ul>
    </div>
</div>

<script>
const BASE = "https://bokonzi.com/api";
const input = document.getElementById('search');
const resultsList = document.getElementById('results');
const statusEl = document.getElementById('status');
const countEl = document.getElementById('count');
const activeFilterEl = document.getElementById('activeFilter');
const filterBtns = document.querySelectorAll('#filters button');

const subSection = document.getElementById('subSection');
const subResults = document.getElementById('subResults');
const subCount = document.getElementById('subCount');
const subTitle = document.getElementById('subTitle');
const backBtn = document.getElementById('backBtn');

let timer = null;
let controller = null;
let currentFilter = 'nom';

// Config par filtre : endpoint API, cle de reponse, rendu
const filters = {
    nom: {
        label: 'Nom complet',
        url: q => BASE + '/search.php?nom=' + encodeURIComponent(q) + '&limit=50',
        key: 'athletes',
        render: (item, q) => {
            const nom = highlight(item.nom_complet, q);
            return '<li onclick="window.location.href=\'global_athlete.php?id=' + item.athlete_id + '\'">'
                + '<div class="left">'
                + '<div class="nom">' + nom + '</div>'
                + '<div class="infos">'
                + '<span>' + (item.sexe || '—') + '</span>'
                + '<span>' + (item.categorie || '—') + '</span>'
                + '<span>' + (item.nationalite || '—') + '</span>'
                + '<span>Ne(e) ' + (item.annee_naissance || '—') + '</span>'
                + '</div></div>'
                + '<span class="arrow">›</span></li>';
        }
    },
    club: {
        label: 'Club',
        url: q => BASE + '/clubs.php?nom=' + encodeURIComponent(q) + '&limit=50',
        key: 'clubs',
        searchParam: 'club',
        render: (item, q) => {
            const nom = highlight(item.nom_club, q);
            const periode = formatPeriode(item.annee_debut, item.annee_fin);
            return '<li onclick="showAthletes(\'club\', \'' + escapeAttr(item.nom_club) + '\')">'
                + '<div class="left">'
                + '<div class="nom">' + nom + '</div>'
                + '<div class="infos">' + periode + '</div>'
                + '</div>'
                + '<span class="badge">' + item.nb_athletes + ' athlete' + (item.nb_athletes > 1 ? 's' : '') + '</span>'
                + '</li>';
        }
    },
    ville: {
        label: 'Ville',
        url: q => BASE + '/villes.php?nom=' + encodeURIComponent(q) + '&limit=50',
        key: 'villes',
        searchParam: 'ville',
        render: (item, q) => {
            const nom = highlight(item.nom_ville, q);
            const periode = formatPeriode(item.annee_debut, item.annee_fin);
            return '<li onclick="showAthletes(\'ville\', \'' + escapeAttr(item.nom_ville) + '\')">'
                + '<div class="left">'
                + '<div class="nom">' + nom + '</div>'
                + '<div class="infos">' + periode + '</div>'
                + '</div>'
                + '<span class="badge">' + item.nb_athletes + ' athlete' + (item.nb_athletes > 1 ? 's' : '') + '</span>'
                + '</li>';
        }
    },
    epreuve: {
        label: 'Epreuve',
        url: q => BASE + '/epreuves.php?nom=' + encodeURIComponent(q) + '&limit=50',
        key: 'epreuves',
        searchParam: 'epreuve',
        render: (item, q) => {
            const nom = highlight(item.nom_epreuve, q);
            const periode = formatPeriode(item.date_debut, item.date_fin);
            return '<li onclick="showAthletes(\'epreuve\', \'' + escapeAttr(item.nom_epreuve) + '\')">'
                + '<div class="left">'
                + '<div class="nom">' + nom + '</div>'
                + '<div class="infos">' + periode + '</div>'
                + '</div>'
                + '<span class="badge">' + item.nb_athletes + ' athlete' + (item.nb_athletes > 1 ? 's' : '') + '</span>'
                + '</li>';
        }
    },
    competition: {
        label: 'Competition',
        url: q => BASE + '/competitions.php?nom=' + encodeURIComponent(q) + '&limit=50',
        key: 'competitions',
        searchParam: 'competition',
        render: (item, q) => {
            const nom = highlight(item.nom_competition, q);
            const periode = formatPeriode(item.annee_debut, item.annee_fin);
            return '<li onclick="showAthletes(\'competition\', \'' + escapeAttr(item.nom_competition) + '\')">'
                + '<div class="left">'
                + '<div class="nom">' + nom + '</div>'
                + '<div class="infos">' + periode + '</div>'
                + '</div>'
                + '<span class="badge">' + item.nb_athletes + ' athlete' + (item.nb_athletes > 1 ? 's' : '') + '</span>'
                + '</li>';
        }
    }
};

// Boutons filtre
filterBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        filterBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentFilter = this.dataset.filter;
        input.placeholder = this.dataset.placeholder;
        activeFilterEl.innerHTML = '<span>Recherche par : ' + filters[currentFilter].label + '</span>';
        input.value = '';
        resultsList.innerHTML = '';
        countEl.textContent = '';
        statusEl.textContent = '';
        input.focus();
    });
});

input.addEventListener('input', function() {
    const q = this.value.trim();
    clearTimeout(timer);

    if (q.length < 2) {
        resultsList.innerHTML = '';
        countEl.textContent = '';
        statusEl.textContent = q.length === 1 ? 'Tapez au moins 2 caracteres...' : '';
        statusEl.className = 'status';
        return;
    }

    statusEl.textContent = 'Recherche...';
    statusEl.className = 'status loading';

    timer = setTimeout(() => fetchResults(q), 300);
});

async function fetchResults(query) {
    if (controller) controller.abort();
    controller = new AbortController();

    const cfg = filters[currentFilter];

    try {
        const resp = await fetch(cfg.url(query), { signal: controller.signal });
        const data = await resp.json();

        if (!data.success) {
            statusEl.textContent = data.error || 'Erreur';
            statusEl.className = 'status error';
            resultsList.innerHTML = '';
            countEl.textContent = '';
            return;
        }

        statusEl.textContent = '';
        statusEl.className = 'status';

        const items = data[cfg.key];

        if (!items || items.length === 0) {
            resultsList.innerHTML = '<li class="no-result">Aucun resultat pour "' + escapeHtml(query) + '"</li>';
            countEl.textContent = '0 resultat';
            return;
        }

        countEl.textContent = data.total + ' resultat' + (data.total > 1 ? 's' : '')
            + (data.total > 50 ? ' (50 affiches)' : '');

        resultsList.innerHTML = items.map(item => cfg.render(item, query)).join('');

    } catch (e) {
        if (e.name === 'AbortError') return;
        statusEl.textContent = 'Erreur de connexion';
        statusEl.className = 'status error';
    }
}

function highlight(text, query) {
    if (!text) return '';
    const regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

function formatPeriode(debut, fin) {
    if (!debut && !fin) return '<span>Pas de dates</span>';
    // Extraire juste l'annee si c'est une date complete (2024-05-12 → 2024)
    const d = debut ? String(debut).substring(0, 4) : '?';
    const f = fin ? String(fin).substring(0, 4) : '?';
    if (d === f) return '<span>Annee : ' + d + '</span>';
    return '<span>Periode : ' + d + ' → ' + f + '</span>';
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function escapeAttr(str) {
    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Labels pour la periode selon le filtre
const periodeLabels = {
    club: 'Dans ce club',
    ville: 'Resultats',
    epreuve: 'Records',
    competition: 'Medailles'
};

// Clic sur un resultat (ville, club, epreuve, competition) → affiche les athletes
async function showAthletes(param, value) {
    // Cacher la recherche, afficher la sous-section
    document.getElementById('filters').style.display = 'none';
    document.getElementById('activeFilter').style.display = 'none';
    document.querySelector('.search-box').style.display = 'none';
    statusEl.style.display = 'none';
    countEl.style.display = 'none';
    resultsList.style.display = 'none';

    subSection.style.display = 'block';
    subTitle.textContent = filters[param].label + ' : ' + value;
    subResults.innerHTML = '<li class="no-result">Chargement...</li>';
    subCount.textContent = '';

    try {
        const url = BASE + '/search.php?' + param + '=' + encodeURIComponent(value) + '&limit=50';
        const resp = await fetch(url);
        const data = await resp.json();

        if (!data.success || data.athletes.length === 0) {
            subResults.innerHTML = '<li class="no-result">Aucun athlete trouve</li>';
            subCount.textContent = '0 resultat';
            return;
        }

        subCount.textContent = data.total + ' athlete' + (data.total > 1 ? 's' : '')
            + (data.total > 50 ? ' (50 affiches)' : '');

        const pLabel = periodeLabels[param] || 'Periode';

        subResults.innerHTML = data.athletes.map(a => {
            let periodeText = '';
            if (a.filtre_debut || a.filtre_fin) {
                const d = a.filtre_debut ? String(a.filtre_debut).substring(0, 4) : '?';
                const f = a.filtre_fin ? String(a.filtre_fin).substring(0, 4) : '?';
                if (d === f) {
                    periodeText = '<span style="color:#00d4ff;">' + pLabel + ' : ' + d + '</span>';
                } else {
                    periodeText = '<span style="color:#00d4ff;">' + pLabel + ' : ' + d + ' → ' + f + '</span>';
                }
            }
            return '<li onclick="window.location.href=\'global_athlete.php?id=' + a.athlete_id + '\'">'
                + '<div class="left">'
                + '<div class="nom">' + escapeHtml(a.nom_complet) + '</div>'
                + '<div class="infos">'
                + '<span>' + (a.sexe || '—') + '</span>'
                + '<span>' + (a.categorie || '—') + '</span>'
                + '<span>' + (a.nationalite || '—') + '</span>'
                + '<span>Ne(e) ' + (a.annee_naissance || '—') + '</span>'
                + periodeText
                + '</div></div>'
                + '<span class="arrow">›</span></li>';
        }).join('');

    } catch (e) {
        subResults.innerHTML = '<li class="no-result">Erreur de connexion</li>';
    }
}

// Bouton retour
backBtn.addEventListener('click', function() {
    subSection.style.display = 'none';
    document.getElementById('filters').style.display = 'flex';
    document.getElementById('activeFilter').style.display = 'block';
    document.querySelector('.search-box').style.display = 'block';
    statusEl.style.display = 'block';
    countEl.style.display = 'block';
    resultsList.style.display = 'block';
    input.focus();
});
</script>
</body>
</html>
