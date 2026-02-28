<?php
/**
 * chercherIndex.php — Recherche d'index dans un tableau / Search for index in an array
 * FR: Fonction qui recherche l'index ou la cle d'une valeur dans un tableau
 * EN: Function that searches for the index or key of a value in an array
 */
function chercherIndex($tableau, $valeur) {
    // Utiliser array_search pour trouver l'index ou la clé de la valeur
    $index = array_search($valeur, $tableau);

    // Vérifier si la valeur a été trouvée
    if ($index !== false) {
        return $index;
    } else {
        return false; // Retourner false si la valeur n'est pas trouvée
    }
}


/*
// Exemple d'utilisation
$tableau = array("pomme", "banane", "orange", "fraise");
$valeur_a_chercher = "orange";

$resultat = chercherIndex($tableau, $valeur_a_chercher);

if ($resultat !== false) {
    echo "L'index de '$valeur_a_chercher' est $resultat.";
} else {
    echo "'$valeur_a_chercher' n'a pas été trouvé dans le tableau.";
}

*/
?>
