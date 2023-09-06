<?php
session_start();
$filename_config = "model/class/php/config_folder_verif.php"; 
// <direct_01>
// Localisation du fichier de configuration utilisé pour vérifier l'état de l'application.
$filename_config2 = "model/class/php/connexion.php"; 
// <direct_02>
// Localisation du fichier de connexion 

// si le fichier ne sont pas présent ont affiche le contenu de configuration qui est situé 
// view/config.php <direct_03>
// si il ya existence de deux fichier et que la connexion est ok 
// la page fais in redirection vers le fichier le dossier login 


/*
// model/class/php/config_folder_verif.php
// model/class/php/connexion.php

c'est deux fichier sont créer uniquement lorsqu'on reussi a se connecter à la basse de donnée /!\
si l'un de deux fichier n'est pas existant la verification ce fait sur le parametre n°1 nom = pr:01
// <pr:01> verification existence de deux fichier </pr:01>
*/

// <pr:01>
if (!file_exists($filename_config) || !file_exists($filename_config2)) {
  if (file_exists($filename_config)) {
    unlink($filename_config);
    // efface le fichier s'il n'existe pas 
    // filename_config <direct_01>
  }
  if (file_exists($filename_config2)) {
    unlink($filename_config2);
    // efface le fichier s'il n'existe pas 
    // filename_config2 <direct_02> 
  }
} else {
  // paramètre de lecture du fichier 
  $ressource = fopen($filename_config, 'rb');
  $fread_ressource = fread($ressource, filesize($filename_config));
  if ($fread_ressource != "off") {
    //config_folder_verif.php
   // header('Location:login/index.php');    
    //header('Location:login/index.php');
    //header("HTTP/1.1 404 Not Found")
    // lors de la création du document si on a coché sur la case on fait une redirection si non on reste sur la même page
    // redirection en foction du parametre selectionne lors de la création de la Bdd 
  }
}
// </pr:01>
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Ndenga Luvumbu application</title>
  <meta charset="UTF-8">
  <link rel="icon" type="image/x-icon" href="src/img/ndenga.gif">
  <meta charset="utf-8">
  <link rel="icon" type="image/x-icon" href="data:image/jpeg;base64,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">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.5.0/Chart.min.js"></script>
 

</head>
<body>
  <?php
  include("link.html"); // Source totale de style et script    
  /*
        Liste des ressources nécessaires au programme 
        exemple: 
        * Source css
        * Class JS 
        * Class Php 
   */
  ?>
  <div id="body">
    <!--
    @mouseover="position_mouse"    
    Code Vue JS 
    Enregistre la position de la souris lorsque l'utilisateur survole la page web
    Racine vue.js
-->
    <?php
    include("model/class/php/body.php");
    //include("json_active.php");
    // fichier configuration à l'intérieur 
    // contenu général de la page dans class\php\index_body.php 
    /*
    include("model/class/php/Class.php");  
    include("model/class/php/index_body.php");  
    <index_body>
      <header>
      </header>
      <section>
      </section>
      <footer>
      </footer>
    </index_body>
    */
    ?>
 
  </div>
  <!--
  Besoin de vue Js pour savoir le comportement des utilisateurs 
-->
  <?php 
  /*
// la variable est crée sur le fichier 
// model/class/php/Class.php
// et la verification ce fais principalement sur ce fichier 

 
  */
  if ($config_ == false) {

    // si la page de connexion n'existe pas elle affiche le formulaire de connexion 
    // si non elle efface à l'aide du CSS mais le formulaire est toujours présent juste invisible 
 
  } else {
    include("model/class/php/remove_db.php");
    /*
    Execution d'un test s'il n'y a pas d'erreur dans la Bdd 
    S'il y a une erreur il efface le fichier de configuration 
    */
 
  }
  //unlink('model/class/php/config_folder_verif.php');

  ?>
  <!--
  <div id="showcoords_position"></div> 
  <script src="js.js"></script> 
  -->

</body>


<script src="model/class/js/Ajax.js"></script>
<script src="js.js"></script>



<script src="get_epreuve_nom_complet_list.js"></script>
 
<script src="Operation.js"></script>

<script src="scrypt_boucle2.js"></script>
 









</html>




<script>
  
/*
 el1 ="get_club_nom_complet_array_2" ; 
var el2 ="60m Haies (84)-Salle | F" ; 
 el2 ="Lille Metropole Athletisme*" ; 

 */

var test_1 = [] ; 

function alors_() {
  console.log(test_1);
}
 var limits ="{0,10000}" ;


onclick_recherche2("Lille Metropole Athletisme*") ; 




</script>
<style>
  .info_1{
    padding-top: 10px;
  
  }
  .width_01{
    width:30px; 

  }

  .width_02{
    width:100px; 
    
  }
  .p_clas{
    width : 500px; 
 margin-bottom : 25px; 
 margin-top: 25px; 
 

    
  }
  .chil_1{
    display: flex;
    justify-content:space-between ; 

    margin-bottom: 4px; 
  }
  .carre1{
    width: 40px; 
    height: 40px;
   background-color: #ffcc00;
  }
  .carre2{
    width: 40px; 
    height: 40px;
   background-color: #99ff99;
  }
  .carre3{
    width: 40px; 
    height: 40px;
   background-color: #ccffff;
  }
  .carre4{
    width: 40px; 
    height: 40px;
   background-color: #ffcc99;
  }
  .carre5{
    width: 40px; 
    height: 40px;
   background-color: #ff99cc;
  }
</style>
 