<?php  
session_start() ; 
header("Access-Control-Allow-Origin: *");

 
$dbname= $_POST["dbname"];
$username= $_POST["username"];
$password= $_POST["password"];
$servername="localhost";




 
 
// Si la connexion ne pas bonne avec la BDD on efface le fichier de connexion
try {
    $_SESSION['remove'] = true;  
    $conn = new PDO("mysql:host=$servername;dbname=".$dbname, $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  unlink('connexion.php');
  unlink('config_folder_verif.php');
 
  } catch(PDOException $e) { 
    //echo "Connection failed: " . $e->getMessage();

 // si le programme trouve une erreur de connexion il efface le fichier de configuration
 // Connexion.php   
 $_SESSION['remove'] = false;


 
  } 
 

?>