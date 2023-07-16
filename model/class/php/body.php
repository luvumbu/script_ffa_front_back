<?php 
$affiche_div=false; 
$config_=false;

$filename = 'model/class/php/connexion.php';
// eplacement du fichier et nom datribution
if (!file_exists($filename)) {
   // echo "Le fichier $filename existe pas";
     include("view/config.php"); // demande un tableau des valeurs    
} else {
       include("connexion.php");
       include("index_body.php"); 
       /*
            <header,section,footer/>
       */
       $affiche_div =true;
       $config_=true; 
  //  echo "Le fichier $filename n'existe pas.";
}
?>