<?php  
//header("Access-Control-Allow-Origin: *");
include("connexion.php");  
// Si la connexion ne pas bonne avec la BDD on efface le fichier de connexion
try {
    $conn = new PDO("mysql:host=$servername;dbname=".$dbname, $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch(PDOException $e) { 
    //echo "Connection failed: " . $e->getMessage();   
    unlink('model/class/php/connexion.php');
 // si le programme trouve une erreur de connexion il efface le fichier de configuration
 // Connexion.php   
  }
?>