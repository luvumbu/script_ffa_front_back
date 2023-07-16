<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
include("link.php") ; 
 
      $t = time(date_default_timezone_set('Europe/Paris')) ; 
      $tiempo = date("Y-m-d",$t); 

      $liste_group_name = $_POST["liste_group_name"] ;    
      $information_user_info = $_SESSION["information_user_id_sha1"] ;  
      
      $apple = new Insertion_Bdd(
        $servername,
        $username,
        $password,
        $dbname
        
        );
            
       
        $apple->set_msg_valudation("inserttion ok ") ;  
        $apple->set_sql("INSERT INTO liste_group (liste_group_id_sha1,liste_group_information_user_id_sha1,liste_group_new_file,liste_group_name)
                
        VALUES ('$t','$information_user_info','$tiempo','$liste_group_name')") ; 
        $apple->execution() ;





        $apple = new Insertion_Bdd(
          $servername,
          $username,
          $password,
          $dbname
          
          );
              
         
          $apple->set_msg_valudation("inserttion ok ") ;  
          $apple->set_sql("INSERT INTO liste_group_admin (liste_group_admin_id_sha1,liste_group_admin_information_user_id_sha1,liste_group_admin_new_file,liste_group_admin_name)
                  
          VALUES ('$t','$information_user_info','$tiempo','$liste_group_name')") ; 
          $apple->execution() ;
 
        
?>