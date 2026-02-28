<?php
/**
 * removeHtmlTags.php — Suppression des balises HTML / Remove HTML tags
 * FR: Fonction qui supprime toutes les balises HTML d'une chaine de caracteres
 * EN: Function that removes all HTML tags from a character string
 */

function removeHtmlTags($input){
    return strip_tags($input);
}

?>