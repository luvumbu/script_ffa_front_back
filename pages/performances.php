<?php
/**
 * performances.php — Page de gestion des performances manuelles
 * Accessible uniquement aux utilisateurs connectes
 */
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/ip_logger.php';
logIp();
require_once dirname(__DIR__) . '/core/auth.php';

$user = requireAuth($conn);
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-adsense-account" content="ca-pub-7899923856846249">
    <title>Mes Performances - Bokonzi</title>
    <link rel="stylesheet" href="../dashboard.css">
    <style>
    .perf-container {
        max-width: 1100px;
        margin: 20px auto;
        padding: 0 20px;
    }
    .perf-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .perf-header h1 {
        font-size: 28px;
        background: linear-gradient(135deg, #34d399, #00d4ff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 8px;
    }
    .perf-header p { color: #5a6580; }
    .search-athlete-card {
        background: linear-gradient(135deg, #12182a 0%, #1a2035 100%);
        border: 1px solid #1e2a3a;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .search-row {
        display: flex;
        gap: 12px;
        align-items: flex-end;
    }
    .search-row .filter-group { flex: 1; }
    .filter-group label {
        display: block;
        color: #8b949e;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    .filter-group input, .filter-group select {
        width: 100%;
        padding: 10px 14px;
        background: #0d1117;
        border: 1px solid #1e2a3a;
        border-radius: 8px;
        color: #c9d1d9;
        font-size: 14px;
    }
    .filter-group input:focus, .filter-group select:focus {
        outline: none; border-color: #00d4ff;
    }
    .filter-group select option { background: #0d1117; }
    .btn-action {
        padding: 10px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: opacity 0.2s;
        white-space: nowrap;
    }
    .btn-primary { background: linear-gradient(135deg, #00d4ff, #0099cc); color: #fff; }
    .btn-success { background: linear-gradient(135deg, #34d399, #059669); color: #fff; }
    .btn-danger { background: #ff353530; color: #ff6b6b; border: 1px solid #ff353540; }
    .btn-action:hover { opacity: 0.85; }
    .selected-athlete {
        background: #34d39915;
        border: 1px solid #34d39940;
        border-radius: 8px;
        padding: 12px 16px;
        margin-top: 12px;
        display: none;
        justify-content: space-between;
        align-items: center;
    }
    .selected-athlete .name { color: #34d399; font-weight: 600; }
    .selected-athlete .clear { color: #ff6b6b; cursor: pointer; font-size: 12px; }
    .athlete-suggestions {
        background: #0d1117;
        border: 1px solid #1e2a3a;
        border-radius: 8px;
        max-height: 200px;
        overflow-y: auto;
        margin-top: 4px;
        display: none;
    }
    .athlete-suggestions div {
        padding: 10px 14px;
        cursor: pointer;
        font-size: 14px;
        border-bottom: 1px solid #1e2a3a;
    }
    .athlete-suggestions div:hover { background: #161b22; }
    .form-card {
        background: linear-gradient(135deg, #12182a 0%, #1a2035 100%);
        border: 1px solid #1e2a3a;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        display: none;
    }
    .form-card h3 {
        color: #c9d1d9;
        margin-bottom: 16px;
        font-size: 16px;
    }
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }
    .form-grid textarea {
        width: 100%;
        padding: 10px 14px;
        background: #0d1117;
        border: 1px solid #1e2a3a;
        border-radius: 8px;
        color: #c9d1d9;
        font-size: 14px;
        resize: vertical;
        min-height: 60px;
    }
    .list-card {
        background: linear-gradient(135deg, #12182a 0%, #1a2035 100%);
        border: 1px solid #1e2a3a;
        border-radius: 12px;
        padding: 24px;
        display: none;
    }
    .list-card h3 {
        color: #c9d1d9;
        margin-bottom: 16px;
        font-size: 16px;
    }
    .perf-table {
        width: 100%;
        border-collapse: collapse;
    }
    .perf-table th {
        text-align: left;
        padding: 10px 12px;
        color: #8b949e;
        font-size: 12px;
        text-transform: uppercase;
        border-bottom: 1px solid #1e2a3a;
    }
    .perf-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #1e2a3a10;
        font-size: 14px;
    }
    .perf-table tr:hover { background: #ffffff06; }
    .perf-value { color: #34d399; font-weight: 600; font-family: 'Courier New', monospace; }
    .actions-cell { display: flex; gap: 6px; }
    .btn-sm {
        padding: 5px 12px;
        font-size: 12px;
        border-radius: 5px;
        border: none;
        cursor: pointer;
        font-weight: 500;
    }
    .btn-sm-edit { background: #00d4ff20; color: #00d4ff; }
    .btn-sm-del { background: #ff353520; color: #ff6b6b; }
    .btn-sm:hover { opacity: 0.8; }
    .msg-feedback {
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 13px;
        margin-bottom: 16px;
        display: none;
    }
    .msg-success { background: #34d39920; border: 1px solid #34d39940; color: #34d399; }
    .msg-error { background: #ff353520; border: 1px solid #ff353540; color: #ff6b6b; }
    @media (max-width: 600px) {
        .search-row { flex-direction: column; }
        .form-grid { grid-template-columns: 1fr; }
    }
    </style>
    <?php require_once __DIR__ . '/../core/theme.php'; bkRenderThemeHead(); ?>
</head>
<body>
<?php include dirname(__DIR__) . '/nav.php'; ?>

<div class="perf-container">
    <div class="perf-header">
        <h1>Gestion des Performances</h1>
        <p>Ajoutez et gerez les performances manuelles des athletes</p>
    </div>

    <!-- Recherche athlete -->
    <div class="search-athlete-card">
        <div class="search-row">
            <div class="filter-group">
                <label>Rechercher un athlete</label>
                <input type="text" id="athleteSearch" placeholder="Nom de l'athlete..." autocomplete="off">
                <div class="athlete-suggestions" id="suggestions"></div>
            </div>
        </div>
        <div class="selected-athlete" id="selectedAthlete">
            <span>Athlete : <span class="name" id="selName"></span></span>
            <span class="clear" onclick="clearAthlete()">Changer</span>
        </div>
    </div>

    <!-- Formulaire ajout -->
    <div class="form-card" id="formCard">
        <h3 id="formTitle">Ajouter une performance</h3>
        <div class="msg-feedback msg-error" id="formError"></div>
        <div class="msg-feedback msg-success" id="formSuccess"></div>
        <input type="hidden" id="editPerfId" value="">
        <div class="form-grid">
            <div class="filter-group">
                <label>Epreuve</label>
                <select id="perfEpreuve">
                    <option value="">Chargement...</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Performance (ex: 10.85, 1:52.30)</label>
                <input type="text" id="perfBrut" placeholder="10.85">
            </div>
            <div class="filter-group">
                <label>Date</label>
                <input type="date" id="perfDate">
            </div>
            <div class="filter-group">
                <label>Lieu</label>
                <input type="text" id="perfLieu" placeholder="Paris, Stade de France">
            </div>
        </div>
        <div class="filter-group" style="margin-bottom:16px;">
            <label>Notes</label>
            <textarea id="perfNotes" placeholder="Commentaires optionnels..."></textarea>
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn-action btn-success" id="btnSave" onclick="savePerf()">Enregistrer</button>
            <button class="btn-action btn-danger" id="btnCancel" onclick="cancelEdit()" style="display:none;">Annuler</button>
        </div>
    </div>

    <!-- Liste des performances -->
    <div class="list-card" id="listCard">
        <h3>Performances enregistrees</h3>
        <div class="table-wrap">
            <table class="perf-table">
                <thead>
                    <tr>
                        <th>Epreuve</th>
                        <th>Performance</th>
                        <th>Date</th>
                        <th>Lieu</th>
                        <th>Auteur</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
            <table class="perf-table">
                <tbody id="perfBody"></tbody>
            </table>
            <table class="perf-table">
                <thead>
                    <tr>
                        <th>Epreuve</th>
                        <th>Performance</th>
                        <th>Date</th>
                        <th>Lieu</th>
                        <th>Auteur</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<script>
const currentUserId = <?= (int) $user['id_user'] ?>;
const currentRole = '<?= htmlspecialchars($user['role']) ?>';
let selectedAthleteId = null;
let debounceTimer = null;

// Charger les epreuves
fetch('../api/epreuves.php?limit=500')
    .then(r => r.json())
    .then(data => {
        const sel = document.getElementById('perfEpreuve');
        sel.innerHTML = '<option value="">-- Epreuve --</option>';
        if (data.success && data.epreuves) {
            data.epreuves.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id_epreuve;
                opt.textContent = e.nom_epreuve;
                sel.appendChild(opt);
            });
        }
    });

// Recherche athlete avec debounce
document.getElementById('athleteSearch').addEventListener('input', function() {
    clearTimeout(debounceTimer);
    const q = this.value.trim();
    const box = document.getElementById('suggestions');
    if (q.length < 2) { box.style.display = 'none'; return; }
    debounceTimer = setTimeout(function() {
        fetch('../api/search.php?q=' + encodeURIComponent(q) + '&limit=8')
            .then(r => r.json())
            .then(data => {
                box.innerHTML = '';
                if (data.success && data.athletes && data.athletes.length > 0) {
                    data.athletes.forEach(a => {
                        const d = document.createElement('div');
                        d.textContent = a.nom_complet_athlete + ' (' + (a.categorie_athlete || '?') + ')';
                        d.onclick = function() { selectAthlete(a.id_athlete, a.nom_complet_athlete); };
                        box.appendChild(d);
                    });
                    box.style.display = 'block';
                } else {
                    box.style.display = 'none';
                }
            });
    }, 300);
});

function selectAthlete(id, name) {
    selectedAthleteId = id;
    document.getElementById('suggestions').style.display = 'none';
    document.getElementById('athleteSearch').style.display = 'none';
    document.getElementById('selectedAthlete').style.display = 'flex';
    document.getElementById('selName').textContent = name;
    document.getElementById('formCard').style.display = 'block';
    loadPerformances();
}

function clearAthlete() {
    selectedAthleteId = null;
    document.getElementById('athleteSearch').style.display = 'block';
    document.getElementById('athleteSearch').value = '';
    document.getElementById('selectedAthlete').style.display = 'none';
    document.getElementById('formCard').style.display = 'none';
    document.getElementById('listCard').style.display = 'none';
    cancelEdit();
}

function loadPerformances() {
    if (!selectedAthleteId) return;
    fetch('../api/performances.php?id_athlete=' + selectedAthleteId)
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('perfBody');
            const card = document.getElementById('listCard');
            tbody.innerHTML = '';
            if (!data.success || !data.performances.length) {
                card.style.display = 'block';
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#5a6580;padding:30px;">Aucune performance</td></tr>';
                return;
            }
            card.style.display = 'block';
            data.performances.forEach(p => {
                const canEdit = (p.id_user == currentUserId || currentRole === 'admin');
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + esc(p.nom_epreuve || '-') + '</td>' +
                    '<td class="perf-value">' + esc(p.performance_brut || '-') + '</td>' +
                    '<td>' + esc(p.date_perf || '-') + '</td>' +
                    '<td>' + esc(p.lieu || '-') + '</td>' +
                    '<td>' + esc((p.auteur_prenom || '') + ' ' + (p.auteur_nom || '')) + '</td>' +
                    '<td class="actions-cell">' +
                    (canEdit ? '<button class="btn-sm btn-sm-edit" onclick="editPerf(' + p.id_perf + ')">Modifier</button>' +
                    '<button class="btn-sm btn-sm-del" onclick="deletePerf(' + p.id_perf + ')">Supprimer</button>' : '') +
                    '</td>';
                tr.dataset.perf = JSON.stringify(p);
                tr.id = 'perf-row-' + p.id_perf;
                tbody.appendChild(tr);
            });
        });
}

function savePerf() {
    const editId = document.getElementById('editPerfId').value;
    const payload = {
        id_athlete: selectedAthleteId,
        id_epreuve: parseInt(document.getElementById('perfEpreuve').value) || 0,
        performance_brut: document.getElementById('perfBrut').value,
        performance: 0,
        date_perf: document.getElementById('perfDate').value,
        lieu: document.getElementById('perfLieu').value,
        notes: document.getElementById('perfNotes').value,
    };

    if (!payload.performance_brut) {
        showMsg('formError', 'La performance est requise');
        return;
    }

    const isEdit = editId !== '';
    if (isEdit) payload.id_perf = parseInt(editId);

    fetch('../api/performances.php', {
        method: isEdit ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showMsg('formSuccess', isEdit ? 'Performance modifiee' : 'Performance ajoutee');
            cancelEdit();
            loadPerformances();
        } else {
            showMsg('formError', data.error || 'Erreur');
        }
    })
    .catch(function() { showMsg('formError', 'Erreur reseau'); });
}

function editPerf(id) {
    const row = document.getElementById('perf-row-' + id);
    if (!row) return;
    const p = JSON.parse(row.dataset.perf);
    document.getElementById('editPerfId').value = p.id_perf;
    document.getElementById('perfEpreuve').value = p.id_epreuve || '';
    document.getElementById('perfBrut').value = p.performance_brut || '';
    document.getElementById('perfDate').value = p.date_perf || '';
    document.getElementById('perfLieu').value = p.lieu || '';
    document.getElementById('perfNotes').value = p.notes || '';
    document.getElementById('formTitle').textContent = 'Modifier la performance';
    document.getElementById('btnCancel').style.display = 'inline-block';
    document.getElementById('btnSave').textContent = 'Modifier';
    document.getElementById('formCard').scrollIntoView({ behavior: 'smooth' });
}

function deletePerf(id) {
    if (!confirm('Supprimer cette performance ?')) return;
    fetch('../api/performances.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ id_perf: id }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) loadPerformances();
        else alert(data.error || 'Erreur');
    });
}

function cancelEdit() {
    document.getElementById('editPerfId').value = '';
    document.getElementById('perfEpreuve').value = '';
    document.getElementById('perfBrut').value = '';
    document.getElementById('perfDate').value = '';
    document.getElementById('perfLieu').value = '';
    document.getElementById('perfNotes').value = '';
    document.getElementById('formTitle').textContent = 'Ajouter une performance';
    document.getElementById('btnCancel').style.display = 'none';
    document.getElementById('btnSave').textContent = 'Enregistrer';
    hideMsg('formError');
    hideMsg('formSuccess');
}

function showMsg(id, msg) {
    const el = document.getElementById(id);
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(function() { el.style.display = 'none'; }, 4000);
}
function hideMsg(id) { document.getElementById(id).style.display = 'none'; }

function esc(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
</script>
</body>
</html>
