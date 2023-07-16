<?php 



 




$filename = "../model/class/php/connexion.php";
$verif_name = "information_user" ; 
if (file_exists($filename)) {
   


 include($filename) ; 
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Vérifier si la table existe
$result = mysqli_query($conn, "SHOW TABLES LIKE '".$verif_name."'");
$tableExists = mysqli_num_rows($result) > 0;

// Fermer la connexion à la base de données
mysqli_close($conn);

// Afficher le résultat
if ($tableExists) {
 
} else {
 
 


    session_destroy();



    unlink('../model/class/php/connexion.php');
 

 ?>
 <meta http-equiv="refresh" content="0; url=../index.php">
  
<?php 

}



} else {
    session_destroy();
    unlink('../model/class/php/connexion.php');


    ?>
 <meta http-equiv="refresh" content="0; url=../index.php">
  
<?php 
}













?>