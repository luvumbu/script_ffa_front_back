<?php

session_start() ; 

header("Access-Control-Allow-Origin: *");
 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $photo = $_FILES['photo'];
  
  
  $liste_img_information_user_id_sha1 =     $_SESSION["information_user_id_sha1"];
  
  mkdir('../../../src/img/all/'.$liste_img_information_user_id_sha1);
 
 
 $nom_img= sha1(rand(10,9999999999));
  $path ='../../../src/img/all/'.$liste_img_information_user_id_sha1.'/'.$nom_img.".jpg";
  


  $_SESSION["img_pictur"] =$liste_img_information_user_id_sha1.'/'.$nom_img.".jpg";
  move_uploaded_file($photo['tmp_name'], $path);
}





/*


















*/































include("link.php") ; 
$information_user_id_sha1 =    $_SESSION["information_user_id_sha1"];
$liste_projet_child_sha1 =    $_SESSION["liste_projet_child_sha1"];
//$_POST["liste_projet_child_id"] ;  
$liste_img_type =  $liste_img_information_user_id_sha1.'/'.$nom_img.".jpg" ;
$liste_img_type_sha1 = sha1($liste_img_type) ; 
$liste_img_ip = $_SERVER['REMOTE_ADDR'] ;


  

  $apple = new Insertion_Bdd(
    $servername,
    $username,
    $password,
    $dbname
    
    );
   
   
   
   
 
    
   
    $apple->set_msg_valudation("inserttion ok ") ;  
    $apple->set_sql("INSERT INTO liste_img (liste_img_name,liste_img_id_sha1,liste_img_type,liste_img_ip,liste_img_information_user_id_sha1)
    
    
    VALUES ('$liste_img_type','$liste_img_type_sha1','img','$liste_img_ip','$liste_img_information_user_id_sha1')") ; 
    
   
    
   
    $apple->execution() ;
   
   
    
 

 


$liste_projet_img = $_SESSION["liste_projet_img"]  ; 


    $apple = new Insertion_Bdd(
      $servername,
      $username,
      $password,
      $dbname
      
      );
      $apple->set_msg_valudation("name group up") ;  
      $apple->set_sql('UPDATE `liste_projet` SET `liste_projet_img` = "'.$liste_img_type.'" WHERE  `liste_projet_id_sha1` = "'.$liste_projet_img.'"') ; 
      $apple->execution() ;




















      // partie admin 

      $apple = new Insertion_Bdd(
        $servername,
        $username,
        $password,
        $dbname
        
        );
       
       
       
       
     
        
       
        $apple->set_msg_valudation("inserttion ok ") ;  
        $apple->set_sql("INSERT INTO liste_img_admin (liste_img_admin_name,liste_img_admin_id_sha1,liste_img_admin_type,liste_img_admin_ip,liste_img_admin_information_user_id_sha1)
        
        
        VALUES ('$liste_img_type','$liste_img_type_sha1','img','$liste_img_ip','$liste_img_information_user_id_sha1')") ; 
        
       
        
       
        $apple->execution() ;
       
       
        
     
    
     
    
    
    $liste_projet_img = $_SESSION["liste_projet_img"]  ; 
    
    
        $apple = new Insertion_Bdd(
          $servername,
          $username,
          $password,
          $dbname
          
          );
          $apple->set_msg_valudation("name group up") ;  
          $apple->set_sql('UPDATE `liste_projet_admin` SET `liste_projet_admin_img` = "'.$liste_img_type.'" WHERE  `liste_projet_admin_id_sha1` = "'.$liste_projet_img.'"') ; 
          $apple->execution() ;


    


?>












