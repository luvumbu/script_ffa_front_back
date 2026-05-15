<?php
/**
 * Vue : Comparateur athletes/clubs
 */
?>
<h1 style="background:linear-gradient(135deg,#f59e0b,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Comparateur</h1>
<p class="subtitle">Comparez athletes ou clubs visuellement</p>

<!-- ONGLETS Athletes / Clubs -->
<div class="section-tabs" style="margin-bottom:20px;">
    <a href="#" onclick="switchCmpTab('athletes');return false;" id="cmpTabAthletes" class="active">Athlètes</a>
    <a href="#" onclick="switchCmpTab('clubs');return false;" id="cmpTabClubs">Clubs</a>
</div>

<!-- ════════════════ TAB ATHLETES ════════════════ -->
<div id="cmpAthletesPanel">
<div class="compare-controls" style="background:linear-gradient(135deg,#12182a 0%,#1a2035 100%);border:1px solid #1e2a3a;border-radius:12px;padding:24px;margin:20px 0;">
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;position:relative;">
            <label style="display:block;color:#8b949e;font-size:12px;font-weight:600;text-transform:uppercase;margin-bottom:6px;">Ajouter un athlete</label>
            <input type="text" id="cmpSearch" placeholder="Rechercher par nom..." autocomplete="off"
                style="width:100%;padding:10px 14px;background:#0d1117;border:1px solid #1e2a3a;border-radius:8px;color:#c9d1d9;font-size:14px;">
            <div id="cmpSuggestions" style="background:#0d1117;border:1px solid #1e2a3a;border-radius:8px;max-height:200px;overflow-y:auto;display:none;position:absolute;z-index:50;width:100%;"></div>
        </div>
    </div>
    <div id="cmpSelected" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;"></div>
    <div style="margin-top:16px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;">
            <label style="display:block;color:#8b949e;font-size:12px;font-weight:600;text-transform:uppercase;margin-bottom:6px;">Épreuve à comparer</label>
            <select id="cmpEpreuve" style="width:100%;padding:10px 14px;background:#0d1117;border:1px solid #1e2a3a;border-radius:8px;color:#c9d1d9;font-size:14px;">
                <option value="">-- Ajoutez des athletes d'abord --</option>
            </select>
        </div>
        <div>
            <button onclick="compareNow()" style="padding:10px 28px;background:linear-gradient(135deg,#f59e0b,#ec4899);border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:600;cursor:pointer;">Comparer</button>
        </div>
    </div>
</div>

<div id="cmpChartArea" style="display:none;">
    <div class="chart-card" style="margin:20px 0;">
        <h3><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">VS</span> <span id="cmpChartTitle">Comparaison</span></h3>
        <canvas id="cmpChart" style="max-height:500px;"></canvas>
    </div>
    <div class="chart-card" style="margin:20px 0;">
        <h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#128202;</span> Records personnels compares</h3>
        <div class="table-wrap" id="cmpRecordsTable"></div>
    </div>
    <div class="charts-row" style="margin:20px 0;">
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#127919;</span> Radar comparatif (epreuves communes)</h3>
            <canvas id="cmpRadar" style="max-height:400px;"></canvas>
        </div>
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">&#127942;</span> Médailles comparées</h3>
            <canvas id="cmpMedChart"></canvas>
        </div>
    </div>
    <div class="chart-card" style="margin:20px 0;border-left:3px solid #f59e0b;">
        <h3 style="margin-top:0;"><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#128221;</span> Analyse comparative</h3>
        <div id="cmpSummaryText" style="color:#c8cfd8;line-height:1.9;font-size:14px;"></div>
        <button onclick="navigator.clipboard.writeText(document.getElementById('cmpSummaryText').textContent).then(function(){alert('Analyse copiée !')})" style="margin-top:12px;background:#253049;color:#f59e0b;border:1px solid #f59e0b40;border-radius:8px;padding:6px 14px;cursor:pointer;font-size:12px;">&#128203; Copier le texte</button>
    </div>
</div>
</div>

<!-- ════════════════ TAB CLUBS ════════════════ -->
<div id="cmpClubsPanel" style="display:none;">
<div class="compare-controls" style="background:linear-gradient(135deg,#12182a 0%,#1a2035 100%);border:1px solid #1e2a3a;border-radius:12px;padding:24px;margin:20px 0;">
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;position:relative;">
            <label style="display:block;color:#8b949e;font-size:12px;font-weight:600;text-transform:uppercase;margin-bottom:6px;">Ajouter un club</label>
            <input type="text" id="cmpClubSearch" placeholder="Rechercher un club..." autocomplete="off"
                style="width:100%;padding:10px 14px;background:#0d1117;border:1px solid #1e2a3a;border-radius:8px;color:#c9d1d9;font-size:14px;">
            <div id="cmpClubSuggestions" style="background:#0d1117;border:1px solid #1e2a3a;border-radius:8px;max-height:200px;overflow-y:auto;display:none;position:absolute;z-index:50;width:100%;"></div>
        </div>
    </div>
    <div id="cmpClubSelected" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;"></div>
    <div style="margin-top:16px;">
        <button onclick="compareClubsNow()" style="padding:10px 28px;background:linear-gradient(135deg,#8b5cf6,#06b6d4);border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:600;cursor:pointer;">Comparer les clubs</button>
    </div>
</div>

<div id="cmpClubChartArea" style="display:none;">
    <!-- Effectifs -->
    <div class="charts-row" style="margin:20px 0;">
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#128101;</span> Nombre d'athletes</h3>
            <canvas id="clubCmpTotal"></canvas>
        </div>
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">M/F</span> Repartition par sexe</h3>
            <canvas id="clubCmpSexe"></canvas>
        </div>
    </div>
    <!-- Categories + Medailles -->
    <div class="charts-row" style="margin:20px 0;">
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">Cat</span> Athletes par categorie</h3>
            <canvas id="clubCmpCat"></canvas>
        </div>
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#f59e0b20;color:#fbbf24;">&#127942;</span> Médailles</h3>
            <canvas id="clubCmpMed"></canvas>
        </div>
    </div>
    <!-- Top Epreuves + Radar -->
    <div class="charts-row" style="margin:20px 0;">
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#ec489920;color:#f472b6;">&#127939;</span> Top epreuves comparees</h3>
            <canvas id="clubCmpEpreuves"></canvas>
        </div>
        <div class="chart-card">
            <h3><span class="chart-icon" style="background:#10b98120;color:#34d399;">&#128200;</span> Radar comparatif</h3>
            <canvas id="clubCmpRadar" style="max-height:400px;"></canvas>
        </div>
    </div>
    <!-- Tableau top athletes -->
    <div class="chart-card" style="margin:20px 0;">
        <h3><span class="chart-icon" style="background:#3b82f620;color:#60a5fa;">&#11088;</span> Top athletes par club</h3>
        <div class="table-wrap" id="clubCmpAthletesTable"></div>
    </div>
    <!-- Resume textuel -->
    <div class="chart-card" style="margin:20px 0;border-left:3px solid #8b5cf6;">
        <h3><span class="chart-icon" style="background:#8b5cf620;color:#a78bfa;">&#128221;</span> Resume comparatif</h3>
        <div id="cmpClubSummaryText" style="color:#c9d1d9;font-size:14px;line-height:1.8;padding:8px 0;"></div>
        <button onclick="var t=document.getElementById('cmpClubSummaryText').innerText;navigator.clipboard.writeText(t).then(function(){alert('Texte copié !');});" style="margin-top:10px;padding:6px 18px;background:#8b5cf630;border:1px solid #8b5cf6;border-radius:6px;color:#a78bfa;font-size:12px;cursor:pointer;">Copier le texte</button>
    </div>
</div>
</div>

<script src="<?= $baseUrl ?>/public/assets/js/comparer.js"></script>
