<?php
/**
 * extraireAlphabetique.php — Extraction des caracteres alphabetiques / Extract alphabetic characters
 * FR: Fonction qui extrait uniquement les lettres alphabetiques d'une chaine de caracteres
 * EN: Function that extracts only alphabetic letters from a character string
 */

function extraireAlphabetique($str) {
    return preg_replace('/[^a-zA-Z]/', '', $str);
}
/*


$texte = "H3ll0 W0rld! 2024";
$resultat = extraireAlphabetique($texte);

echo $resultat; // Affichera "HllWrld"



*/
?>