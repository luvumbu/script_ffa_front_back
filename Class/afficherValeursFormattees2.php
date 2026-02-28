<?php
/**
 * afficherValeursFormattees2.php — Fonction JS pour separer une chaine / JS function to split a string
 * FR: Fonction JavaScript qui separe une chaine en tableau selon un separateur donne
 * EN: JavaScript function that splits a string into an array using a given separator
 */
?>
<script>
    function afficherValeursFormattees2(chaine, separation) {
        // La méthode split() sépare la chaîne en un tableau de sous-chaînes, en utilisant "__" comme séparateur
        const valeurs = chaine.split(separation);
        // Retourne le tableau des valeurs obtenues après séparation
        return valeurs;
    }


    var __ = "__";
</script>

