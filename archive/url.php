<?php

// Fichier de sauvegarde de la progression
$progressFile = __DIR__ . "/progress.txt";

if (!isset($_SESSION["url"])) {
    // Session perdue → lire depuis progress.txt si il existe
    if (file_exists($progressFile)) {
        $_SESSION["url"] = (int) file_get_contents($progressFile);
    } else {
        $_SESSION["url"] = 0;
    }
    $id_nom_et_liens = $_SESSION["url"];
} else {
    $id_nom_et_liens = $_SESSION["url"];
}





        $databaseHandler = new DatabaseHandler($dbname, $username, $password);

        // Je veux ma propre requête
        $sql = 'SELECT * FROM `nom_et_liens` WHERE `id_nom_et_liens`="'.$id_nom_et_liens.'";';

        // On exécute et on crée une variable globale $mes_projets
        $result = $databaseHandler->select_custom_safe($sql, 'mes_projets');

        if ($result['success']) {
   
        } else {
            echo "Erreur : " . $result['message'];
        }



     
 
    

       
?>


<h1>
    <?php 

echo $id_nom_et_liens ;


echo "<br/>" ;

echo $id_nom_et_liens ;
?>
</h1>