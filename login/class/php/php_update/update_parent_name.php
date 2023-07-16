<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
include("link.php") ; 

$child = $_POST["child"] ;
$parent = $_POST["parent"] ; 
 


 

if($_SESSION["information_user_id_sha1"]!="" )
{
echo $_SESSION["information_user_id_sha1"] ; 
}


$apple = new Insertion_Bdd(
      $servername,
      $username,
      $password,
      $dbname
      
      );
      $apple->set_msg_valudation("up ok") ;  
      $apple->set_sql('UPDATE `liste_projet` SET `liste_projet_id_parent` = "'.$parent.'" WHERE  `liste_projet_id_sha1` = "'.$child.'"') ; 
      $apple->execution() ;


      $apple = new Insertion_Bdd(
            $servername,
            $username,
            $password,
            $dbname
            
            );
            $apple->set_msg_valudation("up ok") ;  
            $apple->set_sql('UPDATE `liste_projet_admin` SET `liste_projet_admin_id_parent` = "'.$parent.'" WHERE  `liste_projet_admin_id_sha1` = "'.$child.'"') ; 
            $apple->execution() ;
?>