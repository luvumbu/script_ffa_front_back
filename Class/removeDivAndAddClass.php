<?php
/**
 * removeDivAndAddClass.php — Nettoyage HTML en texte brut / Clean HTML to plain text
 * FR: Fonction qui nettoie le HTML pour ne garder que le texte brut propre
 * EN: Function that cleans HTML to keep only clean plain text
 */

/**
 * Nettoie n'importe quel HTML pour ne garder que le texte brut.
 * Cleans any HTML to keep only plain text.
 *
 * @param string $html Le HTML a nettoyer / The HTML to clean
 * @return string Texte brut propre / Clean plain text
 */
function cleanHtmlToPlainText($html) {
    // Supprimer tous les tags HTML
    $text = strip_tags($html);

    // Nettoyer les espaces en début et fin
    $text = trim($text);

    // Remplacer plusieurs espaces ou retours à la ligne par un seul espace
    $text = preg_replace('/\s+/u', ' ', $text);

    return $text;
}


?>
