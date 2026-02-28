<?php
/**
 * session_destroy.php — Destruction de session et deconnexion / Session destruction and logout
 * FR: Script qui detruit la session utilisateur et redirige vers la page d'accueil
 * EN: Script that destroys the user session and redirects to the home page
 */
session_start() ;
unset($_SESSION["index"]);
 






session_destroy() ; 





?>

<style>
    body{
        background-color: black;
    }
</style>


<meta http-equiv="refresh" content="0; URL=../index.php">
 
 


<h1>Déconnexion en cours</h1>

 