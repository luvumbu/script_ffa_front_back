<?php
/**
 * Vue : Tutoriel animé (9 étapes)
 * Variables : $nbAth, $nbClub, $nbEp
 */
?>
<div class="tuto-container">

<!-- Skip button -->
<button class="tuto-skip-btn" onclick="tutoSkip()">Passer &rarr;</button>

<!-- Progress bar -->
<div class="tuto-progress" id="tutoProgress">
<?php for ($ts = 1; $ts <= 9; $ts++): ?>
    <div class="tuto-progress-step" data-step="<?= $ts ?>" onclick="tutoGoStep(<?= $ts ?>)" style="cursor:pointer;">
        <span class="tuto-progress-dot"><?= $ts ?></span>
    </div>
<?php endfor; ?>
</div>

<!-- ========== ÉTAPE 1 : BIENVENUE ========== -->
<div class="tuto-step visible" data-step="1" id="tutoStep1">
    <div class="tuto-title" style="background:linear-gradient(135deg,#6c5ce7,#a29bfe);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
        <span id="tutoTyping"></span><span class="tuto-cursor"></span>
    </div>
    <p class="tuto-subtitle">
        Bokonzi est la base de données la plus complète de l'athlétisme français. Ce tutoriel interactif vous apprend à l'utiliser en quelques minutes.
    </p>
    <div class="tuto-cards">
        <div class="tuto-card"><div class="num" data-count="<?= $nbAth ?>">0</div><div class="label">Athlètes</div></div>
        <div class="tuto-card"><div class="num" data-count="<?= $nbClub ?>">0</div><div class="label">Clubs</div></div>
        <div class="tuto-card"><div class="num" data-count="<?= $nbEp ?>">0</div><div class="label">Épreuves</div></div>
    </div>
    <div style="text-align:center;margin-top:24px;">
        <button class="tuto-next-btn" onclick="tutoGoStep(2)" style="font-size:16px;padding:14px 36px;">Commencer le tutoriel &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 2 : RECHERCHER UN CLUB ========== -->
<div class="tuto-step" data-step="2" id="tutoStep2" style="display:none;">
    <div class="tuto-title" style="color:#34d399;">Recherchez un club</div>
    <p class="tuto-subtitle">Tapez le nom d'un club pour le trouver. Essayez par exemple <b>"Lille"</b>, <b>"Paris"</b> ou le nom de votre club.</p>

    <div class="tuto-live-search tuto-highlight" id="tutoClubSearchWrap">
        <span style="font-size:18px;flex-shrink:0;">&#128269;</span>
        <input type="text" id="tutoClubInput" placeholder="Tapez un nom de club..." autocomplete="off" oninput="_tutoSearchClubs(this.value)">
    </div>
    <div class="tuto-live-results" id="tutoClubResults"></div>
    <div id="tutoClubDone" style="display:none;text-align:center;margin-top:14px;">
        <div class="tuto-complete-badge">&#10003; Club sélectionné !</div>
        <button class="tuto-next-btn" onclick="tutoGoStep(3)" style="margin-top:10px;">Explorer le panneau club &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 3 : PANNEAU CLUB (EMBEDDED) ========== -->
<div class="tuto-step" data-step="3" id="tutoStep3" style="display:none;">
    <div class="tuto-title" style="color:#a29bfe;">Le panneau club</div>
    <p class="tuto-subtitle">Explorez les onglets du club. Naviguez dans au moins <b>2 onglets</b> pour continuer.</p>
    <p class="tuto-subtitle" id="tutoClubTabsProgress" style="font-size:12px;color:#5a6580;">Onglets visités : <span id="tutoTabsCount">0</span>/2</p>

    <div id="clubDetailPanelTuto" class="tuto-inline-panel">
        <div class="club-detail-header">
            <h2 id="clubDetailNameTuto" style="margin:0;"></h2>
            <span class="meta-info" id="clubDetailMetaTuto"></span>
        </div>
        <div class="club-detail-tabs" id="clubTabsTuto">
            <button class="club-detail-tab active" data-tab="epreuves" onclick="switchClubTabTuto('epreuves')">Épreuves</button>
            <button class="club-detail-tab" data-tab="nationalites" onclick="switchClubTabTuto('nationalites')">Nationalités</button>
            <button class="club-detail-tab" data-tab="stats" onclick="switchClubTabTuto('stats')">Stats</button>
            <button class="club-detail-tab" data-tab="performances" onclick="switchClubTabTuto('performances')">Performances</button>
            <button class="club-detail-tab" data-tab="resume" onclick="switchClubTabTuto('resume')">Résumé</button>
        </div>
        <div id="tutoClubTabsDoneTop" style="display:none;text-align:center;padding:10px;background:#10b98110;border:1px solid #10b98130;border-radius:8px;margin:8px 12px;">
            <div class="tuto-complete-badge" style="margin-bottom:6px;">&#10003; Onglets explorés !</div>
            <button class="tuto-next-btn" onclick="tutoGoStep(4)">Chercher un athlète &rarr;</button>
        </div>
        <div id="clubDetailContentTuto" class="club-detail-content">
            <div class="loading-msg">Sélectionnez un club à l'étape précédente</div>
        </div>
    </div>
    <div id="tutoClubTabsDone" style="display:none;text-align:center;margin-top:14px;">
        <div class="tuto-complete-badge">&#10003; Onglets explorés !</div>
        <button class="tuto-next-btn" onclick="tutoGoStep(4)" style="margin-top:10px;">Chercher un athlète &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 4 : CHERCHER UN ATHLÈTE ========== -->
<div class="tuto-step" data-step="4" id="tutoStep4" style="display:none;">
    <div class="tuto-title" style="color:#818cf8;">Cherchez un athlète</div>
    <p class="tuto-subtitle">Recherchez un athlète dans le club <b id="tutoAthClubName">sélectionné</b>. Tapez un nom ou laissez vide pour voir tous les athlètes.</p>

    <div class="tuto-live-search tuto-highlight" id="tutoAthSearchWrap">
        <span style="font-size:18px;flex-shrink:0;">&#128100;</span>
        <input type="text" id="tutoAthInput" placeholder="Tapez un nom d'athlète..." autocomplete="off" oninput="_tutoSearchAthletes(this.value)">
    </div>
    <div class="tuto-live-results" id="tutoAthResults"></div>
    <div id="tutoAthDone" style="display:none;text-align:center;margin-top:14px;">
        <div class="tuto-complete-badge">&#10003; Athlète sélectionné !</div>
        <button class="tuto-next-btn" onclick="tutoGoStep(5)" style="margin-top:10px;">Voir l'aperçu profil &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 5 : APERÇU PROFIL ATHLÈTE ========== -->
<div class="tuto-step" data-step="5" id="tutoStep5" style="display:none;">
    <div class="tuto-title" style="color:#ec4899;">Profil athlète</div>
    <p class="tuto-subtitle">Voici un aperçu du profil. Chaque élément est <b>cliquable</b> : clubs, épreuves, villes, catégories...</p>

    <div id="tutoAthPreview" class="tuto-inline-panel">
        <div class="loading-msg">Sélectionnez un athlète à l'étape précédente</div>
    </div>
    <div id="tutoAthDoneStep5" style="display:none;text-align:center;margin-top:14px;">
        <a href="#" id="tutoAthProfileLink" class="tuto-try" target="_blank" style="margin-right:10px;">&#128073; Voir le profil complet</a>
        <button class="tuto-next-btn" onclick="tutoGoStep(6)" style="margin-top:10px;">Continuer &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 6 : ÉPREUVES & VILLES ========== -->
<div class="tuto-step" data-step="6" id="tutoStep6" style="display:none;">
    <div class="tuto-title" style="color:#f59e0b;">Épreuves & Villes</div>
    <p class="tuto-subtitle">Explorez les données par <b>épreuve</b> (100m, saut en hauteur...) ou par <b>ville</b> de compétition.</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="tuto-mock" style="margin:0;">
            <div style="color:#a29bfe;font-weight:700;font-size:14px;margin-bottom:8px;">&#127939; Épreuves</div>
            <div class="tuto-mock-row"><span style="color:#ef4444;">Sprint</span> 100m, 200m, 400m</div>
            <div class="tuto-mock-row"><span style="color:#f59e0b;">Demi-fond</span> 800m, 1500m</div>
            <div class="tuto-mock-row"><span style="color:#3b82f6;">Sauts</span> Hauteur, Longueur, Perche</div>
            <div class="tuto-mock-row"><span style="color:#6366f1;">Lancers</span> Poids, Disque, Javelot</div>
            <div style="margin-top:10px;"><a href="?page=epreuves" class="tuto-try" style="font-size:12px;padding:8px 16px;">Essayer les Épreuves</a></div>
        </div>
        <div class="tuto-mock" style="margin:0;">
            <div style="color:#60a5fa;font-weight:700;font-size:14px;margin-bottom:8px;">&#127961; Villes</div>
            <div class="tuto-mock-row"><span style="color:#60a5fa;">Paris</span> Stade de France</div>
            <div class="tuto-mock-row"><span style="color:#60a5fa;">Lyon</span> Stade Gerland</div>
            <div class="tuto-mock-row"><span style="color:#60a5fa;">Marseille</span> Stade Delort</div>
            <div class="tuto-mock-row"><span style="color:#60a5fa;">Lille</span> Stadium Nord</div>
            <div style="margin-top:10px;"><a href="?page=villes" class="tuto-try" style="font-size:12px;padding:8px 16px;">Essayer les Villes</a></div>
        </div>
    </div>
    <p class="tuto-subtitle" style="margin-top:16px;">Chaque épreuve et chaque ville a son panneau détaillé avec graphiques, records et résumé.</p>
    <div style="text-align:center;margin-top:14px;">
        <button class="tuto-next-btn" onclick="tutoGoStep(7)">Continuer &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 7 : COMPARER ========== -->
<div class="tuto-step" data-step="7" id="tutoStep7" style="display:none;">
    <div class="tuto-title" style="color:#f59e0b;">Comparer</div>
    <p class="tuto-subtitle">Ajoutez des athlètes ou clubs au <b>panier de comparaison</b> avec le bouton <b style="color:#a29bfe;">+</b>, puis comparez-les visuellement.</p>

    <div class="tuto-mock">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
            <span style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;background:#6c5ce730;border:1px solid #6c5ce7;border-radius:6px;color:#a29bfe;font-weight:700;font-size:14px;">+</span>
            <span style="color:#8b949e;font-size:12px;">Cliquez ce bouton sur n'importe quel athlète ou club pour l'ajouter</span>
        </div>
        <div style="display:flex;gap:8px;margin-bottom:12px;">
            <span style="padding:6px 12px;background:#6c5ce720;border:1px solid #6c5ce7;border-radius:8px;font-size:12px;color:#a29bfe;">DUPONT Jean &#10005;</span>
            <span style="padding:6px 12px;background:#6c5ce720;border:1px solid #6c5ce7;border-radius:8px;font-size:12px;color:#a29bfe;">MARTIN Pierre &#10005;</span>
        </div>
        <div style="display:flex;gap:12px;">
            <div style="flex:1;height:80px;background:#3b82f620;border:1px solid #3b82f640;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#60a5fa;">Graphique barres</div>
            <div style="flex:1;height:80px;background:#ec489920;border:1px solid #ec489940;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#f472b6;">Graphique radar</div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;margin-top:14px;padding:12px;background:#f59e0b10;border:1px solid #f59e0b30;border-radius:10px;">
        <span style="font-size:20px;">&#128161;</span>
        <span style="color:#fbbf24;font-size:13px;">Le panier flottant en bas à droite de l'écran affiche le nombre d'éléments sélectionnés.</span>
    </div>
    <div style="text-align:center;margin-top:14px;">
        <a href="?page=comparer" class="tuto-try" style="margin-right:10px;">&#128073; Aller au Comparateur</a>
        <button class="tuto-next-btn" onclick="tutoGoStep(8)" style="margin-top:10px;">Continuer &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 8 : SUIVRE & NOTIFICATIONS ========== -->
<div class="tuto-step" data-step="8" id="tutoStep8" style="display:none;">
    <div class="tuto-title" style="color:#10b981;">Suivre & Notifications</div>
    <p class="tuto-subtitle">Restez informé des athlètes et clubs qui vous intéressent.</p>

    <div class="tuto-features">
        <div class="tuto-feature"><span class="icon">&#9825;</span><div><div class="title">Suivre un athlète</div><div class="desc">Cliquez le bouton &#9825; sur un profil pour le suivre. Renseignez votre email une seule fois.</div></div></div>
        <div class="tuto-feature"><span class="icon">&#127963;</span><div><div class="title">Suivre un club</div><div class="desc">Le bouton &#9825; dans le panneau club permet de suivre un club entier.</div></div></div>
        <div class="tuto-feature"><span class="icon">&#128233;</span><div><div class="title">Newsletter</div><div class="desc">Inscrivez-vous à la newsletter pour recevoir les actualités de l'athlétisme français.</div></div></div>
        <div class="tuto-feature"><span class="icon">&#128196;</span><div><div class="title">Télécharger PDF</div><div class="desc">Sur chaque profil, le bouton PDF génère une fiche imprimable complète.</div></div></div>
    </div>
    <div style="text-align:center;margin-top:14px;">
        <button class="tuto-next-btn" onclick="tutoGoStep(9)">Terminer &rarr;</button>
    </div>
</div>

<!-- ========== ÉTAPE 9 : C'EST PARTI ! ========== -->
<div class="tuto-step" data-step="9" id="tutoStep9" style="display:none;">
    <div class="tuto-title" style="background:linear-gradient(135deg,#6c5ce7,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Vous êtes prêt !</div>
    <p class="tuto-subtitle">Voici les codes couleurs des <b>niveaux de compétition</b> que vous verrez partout :</p>

    <div class="tuto-niv-demo">
        <span style="background:#f9731620;border:1px solid #f9731640;color:#fb923c;">D1-D8 Départemental</span>
        <span style="background:#0891b220;border:1px solid #0891b240;color:#22d3ee;">R1-R6 Régional</span>
        <span style="background:#e11d4820;border:1px solid #e11d4840;color:#fb7185;">N1-N4 National</span>
        <span style="background:#c026d320;border:1px solid #c026d340;color:#e879f9;">IE/IR International</span>
    </div>

    <p class="tuto-subtitle" style="margin-top:20px;">Récapitulatif des fonctionnalités :</p>
    <div class="tuto-features">
        <div class="tuto-feature"><span class="icon">&#128269;</span><div><div class="title">Recherche multi-critères</div><div class="desc">Nom, club, épreuve, sexe, catégorie, nationalité</div></div></div>
        <div class="tuto-feature"><span class="icon">&#127963;</span><div><div class="title">Panneaux détaillés</div><div class="desc">Clubs et épreuves avec 5+ onglets</div></div></div>
        <div class="tuto-feature"><span class="icon">&#128100;</span><div><div class="title">Profils complets</div><div class="desc">Bio auto-générée, filtrable par année</div></div></div>
        <div class="tuto-feature"><span class="icon">&#128202;</span><div><div class="title">Graphiques interactifs</div><div class="desc">Évolution, répartition, comparaison</div></div></div>
        <div class="tuto-feature"><span class="icon">&#128279;</span><div><div class="title">Tout est cliquable</div><div class="desc">Clubs, épreuves, villes, catégories, nationalités</div></div></div>
        <div class="tuto-feature"><span class="icon">&#9878;</span><div><div class="title">Filtres combinables</div><div class="desc">Club + nationalité + sexe + catégorie...</div></div></div>
    </div>

    <div style="text-align:center;margin-top:24px;">
        <button class="tuto-next-btn" onclick="tutoComplete()" style="font-size:16px;padding:14px 36px;background:linear-gradient(135deg,#6c5ce7,#ec4899);">&#127881; Commencer l'exploration !</button>
    </div>
</div>

</div>

<script src="<?= $baseUrl ?>/public/assets/js/tuto.js"></script>
