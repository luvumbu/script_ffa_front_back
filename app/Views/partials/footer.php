<?php
/**
 * Partial : Footer avec formulaire contact
 */
?>
<footer style="border-top:1px solid #1e2a3a;margin-top:60px;padding:40px 20px 30px;color:#5a6580;font-size:13px;">
<div style="max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;gap:30px;justify-content:space-between;">
    <div>
        <strong style="color:#c9d1d9;font-size:15px;">Bokonzi</strong>
        <p style="margin:8px 0 0;max-width:300px;line-height:1.5;">Base de données complète de l'athlétisme français : athlètes, clubs, épreuves, records et classements.</p>
    </div>
    <div>
        <strong style="color:#8b949e;">Explorer</strong>
        <nav style="display:flex;flex-direction:column;gap:6px;margin-top:8px;" aria-label="Footer navigation">
            <a href="<?= $canonBase ?>/" style="color:#5a6580;text-decoration:none;">Accueil</a>
            <a href="<?= $canonBase ?>/index.php?page=athletes" style="color:#5a6580;text-decoration:none;">Tous les athlètes</a>
            <a href="<?= $canonBase ?>/index.php?page=recherche" style="color:#5a6580;text-decoration:none;">Recherche avancée</a>
            <a href="<?= $canonBase ?>/index.php?page=clubs" style="color:#5a6580;text-decoration:none;">Clubs</a>
        </nav>
    </div>
    <div>
        <strong style="color:#8b949e;">Données</strong>
        <nav style="display:flex;flex-direction:column;gap:6px;margin-top:8px;">
            <a href="<?= $canonBase ?>/index.php?page=epreuves" style="color:#5a6580;text-decoration:none;">Épreuves</a>
            <a href="<?= $canonBase ?>/index.php?page=villes" style="color:#5a6580;text-decoration:none;">Villes</a>
            <a href="<?= $canonBase ?>/pages/classement.php" style="color:#5a6580;text-decoration:none;">Classement</a>
        </nav>
    </div>
    <div>
        <strong style="color:#8b949e;">Contact</strong>
        <div style="margin-top:8px;">
            <button id="footerContactBtn" onclick="document.getElementById('footerContactForm').style.display='block';this.style.display='none';" style="background:#1e2a3a;border:1px solid #2d3a4a;color:#c9d1d9;font-size:13px;padding:8px 18px;border-radius:8px;cursor:pointer;">Nous contacter</button>
            <div id="footerContactForm" style="display:none;max-width:260px;">
                <input type="text" id="fcNom" maxlength="100" placeholder="Nom (optionnel)" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #1e2a3a;background:#0d1117;color:#c9d1d9;font-size:13px;margin-bottom:6px;">
                <input type="email" id="fcEmail" maxlength="200" placeholder="Email (optionnel)" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #1e2a3a;background:#0d1117;color:#c9d1d9;font-size:13px;margin-bottom:6px;">
                <textarea id="fcMsg" maxlength="2000" placeholder="Votre message..." style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #1e2a3a;background:#0d1117;color:#c9d1d9;font-size:13px;font-family:inherit;resize:vertical;min-height:70px;margin-bottom:6px;"></textarea>
                <button onclick="_footerContact()" style="width:100%;background:#6c5ce7;border:none;color:#fff;font-size:13px;font-weight:700;padding:9px;border-radius:8px;cursor:pointer;">Envoyer</button>
                <div id="fcStatus" style="font-size:12px;margin-top:6px;"></div>
            </div>
        </div>
    </div>
</div>
<div style="text-align:center;margin-top:24px;padding-top:16px;border-top:1px solid #1e2a3a;">
    <p>&copy; <?= date('Y') ?> Bokonzi — Base de données athlétisme français</p>
</div>
</footer>
