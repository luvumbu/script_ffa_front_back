<!-- 
Suprimmer le fichier de configuration a laide du l'url remove.php dans la racine
// il suffis d'entrer les information de la BDD 
-->
<?php 
    session_start(); 
    if(isset($_SESSION['remove'])){
 
        }

?>
<script src="model/class/js/remove.js"></script>
<?php
include("link.html");
// ajout des elemnts exterrieur
//echo dirname($_SERVER['SCRIPT_NAME']) ; 
  $filename = 'model/class/php/connexion.php';
  if (file_exists($filename)) {
?>
    <div class="alert alert-danger" role="alert">
        Entrer les informations pour supprimer le fichier connexion.php 
    </div>
 


    <form id="form-group">
      <div class="form-group">
        <label for="dbname">dbname</label>
        <input   class="form-control" id="dbname" aria-describedby="emailHelp" placeholder="dbname">
        <label for="username">username</label>
        <input   class="form-control" id="username" aria-describedby="emailHelp" placeholder="username">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" class="form-control" id="password" placeholder="Password">
      </div> 
      <div type="submit" id="submit" class="btn btn-primary cursor-pointer" onclick="remove()">Submit</div>
    </form>
    <?php 
    } else {    
    ?>
      <div class="alert alert-light" role="alert" style='font-family: "OwlCute";'>
      Aucun fichier a suprimer 
    </div>


    <meta http-equiv="refresh" content="1; URL=index.php">
<?php 
}
?>
 
 