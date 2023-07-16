<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
$id_information_user =  $_SESSION["id_information_user"]; 

$t=time();
 
$tiempo = date("Y-m-d",$t);



$id = $_POST["id"] ; 
include("../../model/class/php/connexion.php") ; 
include("../../model/class/php/Insertion_Bdd.php") ;

      $apple = new Insertion_Bdd(
      $servername,
      $username,
      $password,
      $dbname
      
      );
      $apple->set_msg_valudation("inserttion ok ") ;  
      $apple->set_sql("INSERT INTO liste_projet_child (id_information_user,id_liste_projet,creation_file)
      VALUES ('$id_information_user','$id','$tiempo')") ; 
      $apple->execution() ;
?>