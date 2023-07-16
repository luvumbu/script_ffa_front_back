<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
 
 
$_SESSION["liste_projet_img"] =   $_POST["liste_projet_img"] ;   


echo $_SESSION["liste_projet_img"]  ; 
        
?>