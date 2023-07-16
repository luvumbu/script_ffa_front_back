<?php 

 session_start() ; 
 header("Access-Control-Allow-Origin: *");

include("link.php") ;  

 

$liste_projet_name =  $_POST["liste_projet_name"]  ;
 
 

 

 
 

 
 
 
$apple = new Insertion_Bdd(
  $servername,
  $username,
  $password,
  $dbname
  
  );
  $apple->set_msg_valudation("remove ok ") ;  
  $apple->set_sql('DELETE FROM  `liste_group` WHERE  `liste_group_id_sha1` = "'.$liste_projet_name.'"') ; 
  $apple->execution() ;
 

 


 




 
 

 



 
 
 ?>

 
  
 