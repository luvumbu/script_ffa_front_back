<?php
/**
 * Partial : Panneau detail epreuve (HTML shell global)
 */
?>
<div id="epreuveDetailPanel" class="club-detail-panel">
    <div class="club-detail-header">
        <h2 id="epreuveDetailName"></h2>
        <span class="meta-info" id="epreuveDetailMeta"></span>
        <button onclick="closeEpreuveDetail()" class="btn-close-detail">&times; Fermer</button>
    </div>
    <div class="club-detail-tabs">
        <button class="club-detail-tab active" data-tab="records" onclick="switchEpreuveTab('records')">Records</button>
        <button class="club-detail-tab" data-tab="nationalites" onclick="switchEpreuveTab('nationalites')">Nationalités</button>
        <button class="club-detail-tab" data-tab="stats" onclick="switchEpreuveTab('stats')">Stats</button>
        <button class="club-detail-tab" data-tab="resume" onclick="switchEpreuveTab('resume')">Résumé</button>
    </div>
    <div id="epreuveDetailContent" class="club-detail-content">
        <div class="loading-msg">Cliquez sur une épreuve pour voir ses détails</div>
    </div>
    <div id="epreuveQR"></div>
</div>
