<?php
/**
 * traitement.php — Traitement des donnees POST / POST data processing
 * FR: Script qui affiche toutes les donnees recues via la methode POST pour le debogage
 * EN: Script that displays all data received via the POST method for debugging
 */
// Verifier si $_POST n'est pas vide / Check if $_POST is not empty
if (!empty($_POST)) {
    foreach ($_POST as $key => $value) {
        // Si la valeur est un tableau (ex: plusieurs inputs avec le même nom)
        if (is_array($value)) {
            echo "Clé : $key <br>";
            foreach ($value as $subKey => $subValue) {
                echo " - $subKey : $subValue <br>";
            }
        } else {
            echo "Clé : $key, Valeur : $value <br>";
        }
    }
} else {
    echo "Aucune donnée POST reçue.";
}
?>
