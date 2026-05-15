<?php
/**
 * Partial : Panneau detail club (HTML shell)
 * Reutilise sur 3 pages : Accueil (suffix='Accueil'), Recherche (suffix=''), Clubs (suffix='')
 *
 * Variables attendues : $suffix (string), $defaultMsg (string, optionnel)
 */
$suffix = $suffix ?? '';
$defaultMsg = $defaultMsg ?? 'Cliquez sur un club pour voir ses details';
$fnSuffix = $suffix; // pour les noms de fonctions JS
?>
<div id="clubDetailPanel<?= $suffix ?>" class="club-detail-panel">
    <div class="club-detail-header">
        <h2 id="clubDetailName<?= $suffix ?>"></h2>
        <span class="meta-info" id="clubDetailMeta<?= $suffix ?>"></span>
        <button class="btn-follow btn-follow-club" id="btnFollowClub<?= $suffix ?>" style="display:none;">&#9825; Suivre</button>
        <button onclick="closeClubDetail<?= $fnSuffix ?>()" class="btn-close-detail">&times; Fermer</button>
    </div>
    <div class="club-detail-tabs">
        <button class="club-detail-tab active" data-tab="epreuves" onclick="switchClubTab<?= $fnSuffix ?>('epreuves')">Épreuves</button>
        <button class="club-detail-tab" data-tab="nationalites" onclick="switchClubTab<?= $fnSuffix ?>('nationalites')">Nationalités</button>
        <button class="club-detail-tab" data-tab="stats" onclick="switchClubTab<?= $fnSuffix ?>('stats')">Stats</button>
        <button class="club-detail-tab" data-tab="performances" onclick="switchClubTab<?= $fnSuffix ?>('performances')">Performances</button>
        <button class="club-detail-tab" data-tab="resume" onclick="switchClubTab<?= $fnSuffix ?>('resume')">Resume</button>
    </div>
    <div id="clubDetailContent<?= $suffix ?>" class="club-detail-content">
        <div class="loading-msg"><?= htmlspecialchars($defaultMsg) ?></div>
    </div>
    <div id="clubQR<?= $suffix ?>"></div>
</div>
