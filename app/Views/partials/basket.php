<?php
/**
 * Partial : Panier comparaison flottant + JS basket
 */
?>
<!-- PANIER FLOTTANT -->
<div class="cmp-basket" id="cmpBasket">
    <div class="basket-counts">
        <span class="basket-count" id="basketAthCount" style="display:none;"><span class="num" id="basketAthNum">0</span> athletes</span>
        <span class="basket-count" id="basketClubCount" style="display:none;"><span class="num" id="basketClubNum" style="background:#8b5cf6;">0</span> clubs</span>
    </div>
    <a href="?page=comparer" class="basket-go">Comparer</a>
    <button class="basket-clear" onclick="clearBasket()" title="Vider">&times;</button>
</div>
<script src="<?= $baseUrl ?>/public/assets/js/basket.js"></script>
<script src="<?= $baseUrl ?>/public/assets/js/ignored-clubs.js"></script>
