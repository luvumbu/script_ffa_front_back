<?php
/**
 * Partial : Modals globales (Newsletter, PDF, Follow)
 */
?>
<!-- Banniere Newsletter -->
<div class="newsletter-bar" id="newsletterBar">
    <span class="nl-text">&#128232; Recevez le classement hebdo par email</span>
    <span class="nl-sub">— Aucun spam, desabonnement en 1 clic</span>
    <input type="email" id="nlEmail" placeholder="votre@email.com" autocomplete="email">
    <button class="nl-btn" id="nlBtn" onclick="subscribeNewsletter()">S'inscrire</button>
    <button class="nl-close" onclick="closeNewsletter()">&times;</button>
</div>

<!-- Modal Email PDF -->
<div class="follow-overlay" id="pdfOverlay">
    <div class="follow-modal" style="position:relative;">
        <button class="btn-close" onclick="closePdfModal()">&times;</button>
        <h3>&#128196; Telecharger la fiche PDF</h3>
        <p>Entrez votre email pour telecharger la fiche complete de cet athlete en PDF.</p>
        <input type="email" id="pdfEmail" placeholder="votre@email.com" autocomplete="email">
        <button class="btn-confirm" onclick="confirmPdf()">Telecharger le PDF</button>
    </div>
</div>

<!-- Modal Suivre Athlete -->
<div class="follow-overlay" id="followOverlay">
    <div class="follow-modal" style="position:relative;">
        <button class="btn-close" onclick="closeFollowModal()">&times;</button>
        <h3 id="followModalTitle">&#9825; Suivre cet athlete</h3>
        <p id="followModalDesc">Entrez votre email pour etre notifie des nouveaux resultats.</p>
        <input type="email" id="followEmail" placeholder="votre@email.com" autocomplete="email">
        <button class="btn-confirm" id="followConfirmBtn" onclick="confirmFollow()">Suivre</button>
        <p style="font-size:11px;color:#484f58;margin-top:12px;margin-bottom:0;">Aucun spam. Vous pouvez vous desabonner a tout moment.</p>
    </div>
</div>
