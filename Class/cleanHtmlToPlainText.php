<?php
/**
 * cleanHtmlToPlainText.php — Conversion HTML en texte brut / Convert HTML to plain text
 * FR: Fonction qui nettoie n'importe quel HTML pour ne garder que le texte brut
 * EN: Function that cleans any HTML to keep only plain text
 */

/**
 * Nettoie n'importe quel HTML pour ne garder que le texte brut.
 * Cleans any HTML to keep only plain text.
 *
 * @param string $html Le HTML a nettoyer / The HTML to clean
 * @return string Texte brut propre / Clean plain text
 */
function cleanHtmlToPlainText($html) {
    // Supprimer tous les tags HTML / Remove all HTML tags
    $text = strip_tags($html);

    // Nettoyer les espaces en debut et fin / Clean leading and trailing spaces
    $text = trim($text);

    // Remplacer plusieurs espaces ou retours a la ligne par un seul espace / Replace multiple spaces or line breaks with a single space
    $text = preg_replace('/\s+/u', ' ', $text);

    return $text;
}

/*

// ------------------------
// Exemple d'utilisation / Usage example
$html = '<span><p><span>ALARME</span></p><div><span><br></span></div></span>
        <div>Suite du texte avec <b>gras</b></div>';

$result = cleanHtmlToPlainText($html);

echo $result;


*/
?>
