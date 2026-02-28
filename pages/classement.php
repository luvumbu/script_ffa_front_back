<?php
/**
 * classement.php — Page de classement des athlètes par épreuve
 */
require_once __DIR__ . '/../core/ip_logger.php';
logIp();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classement — Bokonzi</title>
    <link rel="stylesheet" href="../dashboard.css">
    <style>
    .classement-container {
        max-width: 1100px;
        margin: 20px auto;
        padding: 0 20px;
    }
    .classement-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .classement-header h1 {
        font-size: 28px;
        background: linear-gradient(135deg, #a29bfe, #6c5ce7);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 8px;
    }
    .classement-header p { color: #5a6580; }
    .filters-card {
        background: linear-gradient(135deg, #111830 0%, #0d1220 100%);
        border: 1px solid #1a2540;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .filters-row {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .filter-group {
        flex: 1;
        min-width: 150px;
    }
    .filter-group label {
        display: block;
        color: #8b949e;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    .filter-group select, .filter-group input {
        width: 100%;
        padding: 10px 14px;
        background: #080c14;
        border: 1px solid #1a2540;
        border-radius: 8px;
        color: #d0d7e0;
        font-size: 14px;
    }
    .filter-group select:focus, .filter-group input:focus {
        outline: none;
        border-color: #6c5ce7;
    }
    .filter-group select option { background: #080c14; }
    .sexe-radios {
        display: flex;
        gap: 8px;
    }
    .sexe-radios label {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        background: #080c14;
        border: 1px solid #1a2540;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        color: #d0d7e0;
        text-transform: none;
        font-weight: normal;
        transition: all 0.2s;
    }
    .sexe-radios input[type="radio"] { display: none; }
    .sexe-radios input[type="radio"]:checked + label {
        border-color: #6c5ce7;
        background: #6c5ce715;
        color: #a29bfe;
    }
    .btn-search {
        padding: 10px 28px;
        background: linear-gradient(135deg, #6c5ce7, #5541d0);
        border: none;
        border-radius: 8px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: opacity 0.2s;
        white-space: nowrap;
    }
    .btn-search:hover { opacity: 0.9; }
    .btn-search:disabled { opacity: 0.5; }
    .results-card {
        background: linear-gradient(135deg, #111830 0%, #0d1220 100%);
        border: 1px solid #1a2540;
        border-radius: 12px;
        padding: 24px;
        overflow-x: auto;
    }
    .results-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        color: #5a6580;
        font-size: 14px;
    }
    .results-info strong { color: #a29bfe; }
    /* tableaux gérés par .bk-table dans dashboard.css */
    .rang-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        font-weight: 700;
        font-size: 13px;
    }
    .rang-1 { background: linear-gradient(135deg, #ffd700, #ffaa00); color: #000; }
    .rang-2 { background: linear-gradient(135deg, #c0c0c0, #a0a0a0); color: #000; }
    .rang-3 { background: linear-gradient(135deg, #cd7f32, #a0622e); color: #fff; }
    .rang-other { background: #1a2540; color: #7c85a0; }
    .perf-value { color: #55efc4; font-weight: 600; font-family: 'Courier New', monospace; }
    .athlete-link { color: #a29bfe; text-decoration: none; }
    .athlete-link:hover { text-decoration: underline; }
    .pagination-cls {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 20px;
    }
    .pagination-cls button {
        padding: 8px 16px;
        background: #1a2540;
        border: 1px solid #253560;
        border-radius: 6px;
        color: #d0d7e0;
        cursor: pointer;
    }
    .pagination-cls button:hover { background: #253560; }
    .pagination-cls button:disabled { opacity: 0.3; cursor: not-allowed; }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #5a6580;
    }
    .empty-state p { font-size: 16px; margin-bottom: 8px; }
    @media (max-width: 600px) {
        .filters-row { flex-direction: column; }
        .filter-group { min-width: 100%; }
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/../nav.php'; ?>

<div class="classement-container">
    <div class="classement-header">
        <h1>Classement Athlètes</h1>
        <p>Classement par épreuve, catégorie et sexe</p>
    </div>

    <div class="filters-card">
        <div class="filters-row">
            <div class="filter-group">
                <label>Épreuve</label>
                <select id="filterEpreuve">
                    <option value="">Chargement...</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Catégorie</label>
                <select id="filterCategorie">
                    <option value="">Toutes</option>
                    <option value="EA">Éveil Athlétique</option>
                    <option value="PO">Poussin</option>
                    <option value="BE">Benjamin</option>
                    <option value="MI">Minime</option>
                    <option value="CA">Cadet</option>
                    <option value="JU">Junior</option>
                    <option value="ES">Espoir</option>
                    <option value="SE">Senior</option>
                    <option value="V1">Master 1</option>
                    <option value="V2">Master 2</option>
                    <option value="V3">Master 3</option>
                    <option value="V4">Master 4</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Sexe</label>
                <div class="sexe-radios">
                    <input type="radio" name="sexe" id="sexeAll" value="" checked>
                    <label for="sexeAll">Tous</label>
                    <input type="radio" name="sexe" id="sexeM" value="M">
                    <label for="sexeM">Hommes</label>
                    <input type="radio" name="sexe" id="sexeF" value="F">
                    <label for="sexeF">Femmes</label>
                </div>
            </div>
            <div class="filter-group">
                <label>Annee</label>
                <input type="number" id="filterAnnee" placeholder="Toutes" min="2000" max="2030">
            </div>
            <div>
                <button class="btn-search" id="btnSearch" onclick="searchClassement()">Rechercher</button>
            </div>
        </div>
    </div>

    <div class="results-card" id="resultsCard" style="display:none;">
        <div class="results-info">
            <span><strong id="totalResults">0</strong> athlètes trouvés</span>
            <span id="sortInfo"></span>
        </div>
        <div class="table-wrap">
            <table class="bk-table">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Athlete</th>
                        <th>Club</th>
                        <th>Cat.</th>
                        <th>Performance</th>
                        <th>Date</th>
                    </tr>
                </thead>
            </table>
            <table class="bk-table">
                <tbody id="clsBody"></tbody>
            </table>
            <table class="bk-table">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Athlete</th>
                        <th>Club</th>
                        <th>Cat.</th>
                        <th>Performance</th>
                        <th>Date</th>
                    </tr>
                </thead>
            </table>
        </div>
        <div class="pagination-cls" id="paginationCls"></div>
    </div>

    <div class="empty-state" id="emptyState">
        <p>Sélectionnez une épreuve et lancez la recherche</p>
    </div>
</div>

<script>
let currentOffset = 0;
const PAGE_SIZE = 50;

// Charger la liste des epreuves
fetch('../api/epreuves.php?limit=500')
    .then(r => r.json())
    .then(data => {
        const sel = document.getElementById('filterEpreuve');
        sel.innerHTML = '<option value="">-- Choisir une épreuve --</option>';
        if (data.success && data.epreuves) {
            data.epreuves.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id_epreuve;
                opt.textContent = e.nom_epreuve + (e.sexe_epreuve ? ' (' + e.sexe_epreuve + ')' : '');
                sel.appendChild(opt);
            });
        }
    });

function searchClassement(offset) {
    offset = offset || 0;
    currentOffset = offset;
    const epreuve   = document.getElementById('filterEpreuve').value;
    const categorie = document.getElementById('filterCategorie').value;
    const sexe      = document.querySelector('input[name="sexe"]:checked').value;
    const annee     = document.getElementById('filterAnnee').value;

    if (!epreuve) {
        alert('Veuillez sélectionner une épreuve');
        return;
    }

    const btn = document.getElementById('btnSearch');
    btn.disabled = true;
    btn.textContent = 'Recherche...';

    let url = '../api/classement.php?epreuve=' + epreuve + '&limit=' + PAGE_SIZE + '&offset=' + offset;
    if (categorie) url += '&categorie=' + encodeURIComponent(categorie);
    if (sexe)      url += '&sexe=' + encodeURIComponent(sexe);
    if (annee)     url += '&annee=' + annee;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Rechercher';

            if (!data.success) {
                alert(data.error || 'Erreur');
                return;
            }

            document.getElementById('resultsCard').style.display = 'block';
            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('totalResults').textContent = data.total;
            document.getElementById('sortInfo').textContent = data.sort === 'ASC' ? 'Tri : meilleur temps' : 'Tri : meilleure distance';

            const tbody = document.getElementById('clsBody');
            tbody.innerHTML = '';

            if (data.classement.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#5a6580;padding:40px;">Aucun résultat</td></tr>';
                document.getElementById('paginationCls').innerHTML = '';
                return;
            }

            data.classement.forEach(a => {
                const rangClass = a.rang <= 3 ? 'rang-' + a.rang : 'rang-other';
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';
                tr.onclick = function() { window.location = 'global_athlete.php?id=' + a.athlete_id_externe; };
                tr.innerHTML =
                    '<td><span class="rang-badge ' + rangClass + '">' + a.rang + '</span></td>' +
                    '<td><a class="athlete-link" href="global_athlete.php?id=' + a.athlete_id_externe + '">' + esc(a.nom_complet_athlete) + '</a></td>' +
                    '<td>' + (a.nom_club ? '<a class="athlete-link" href="global_athlete.php?club=' + encodeURIComponent(a.nom_club) + '">' + esc(a.nom_club) + '</a>' : '-') + '</td>' +
                    '<td>' + esc(a.categorie_athlete || '-') + '</td>' +
                    '<td class="perf-value">' + esc(a.performance || '-') + '</td>' +
                    '<td>' + dateFR(a.date_progression || '-') + '</td>';
                tbody.appendChild(tr);
            });

            // Pagination
            const pagDiv = document.getElementById('paginationCls');
            pagDiv.innerHTML = '';
            if (offset > 0) {
                const prev = document.createElement('button');
                prev.textContent = 'Précédent';
                prev.onclick = function() { searchClassement(offset - PAGE_SIZE); };
                pagDiv.appendChild(prev);
            }
            if (offset + PAGE_SIZE < data.total) {
                const next = document.createElement('button');
                next.textContent = 'Suivant';
                next.onclick = function() { searchClassement(offset + PAGE_SIZE); };
                pagDiv.appendChild(next);
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Rechercher';
            alert('Erreur réseau');
        });
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
function dateFR(d) {
    if (!d || d === '-') return '-';
    var m = d.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] : d;
}
</script>
</body>
</html>
