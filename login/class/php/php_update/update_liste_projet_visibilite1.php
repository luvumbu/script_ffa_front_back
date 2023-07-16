<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
include("link.php") ; 

$liste_projet_id_sha1 = $_POST["liste_projet_id_sha1"] ;
$value_ = $_POST["value_"] ; 
 
 

$apple = new Insertion_Bdd(
      $servername,
      $username,
      $password,
      $dbname
      
      );
      $apple->set_msg_valudation("up ok") ;  
      $apple->set_sql('UPDATE `liste_projet` SET `liste_projet_visibilite1` = "'.$value_ .'" WHERE  `liste_projet_id_sha1` = "'.$liste_projet_id_sha1.'"') ; 
      $apple->execution() ;




      $apple = new Insertion_Bdd(
            $servername,
            $username,
            $password,
            $dbname
            
            );
            $apple->set_msg_valudation("up ok") ;  
            $apple->set_sql('UPDATE `liste_projet_admin` SET `liste_projet_admin_visibilite1` = "'.$value_ .'" WHERE  `liste_projet_admin_id_sha1` = "'.$liste_projet_id_sha1.'"') ; 
            $apple->execution() ;
      

    
?>