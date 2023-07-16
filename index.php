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
    header('Location:login/index.php');    
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

<link rel="stylesheet" href="view/pages/general_model.css">
<link rel="stylesheet" href="view/pages/Inscription1.css">
<link rel="stylesheet" href="view/pages/Inscription2.css">
<link rel="stylesheet" href="view/pages/Login.css">
<link rel="stylesheet" href="view/pages/pages.css">
<link rel="stylesheet" href="view/pages/pages_1.css">
<link rel="stylesheet" href="view/pages/recherche_04.css">





</html>


