<?php 
session_start();
header("Access-Control-Allow-Origin: *");
include("link.php") ;  
$information_user_login =$_POST["information_user_login"]; 
$information_user_password = sha1($_POST["information_user_password"]); 
/*
  1)
    $_SESSION["information_user_info"]=     "Nom d'utilisateur inconnue creation  ===400"  ; 
  2)
    $_SESSION["information_user_info"]=     "erreur mot de passe  ===100"  ; 
  3)
    $_SESSION["information_user_info"]=     "Connexion reussi ===200"  ; 
*/
/* 
echo $information_user_password  ; 
 echo $information_user_password  ; 
*/

 
$apple = new Select_datas($servername,$username,$password,$dbname);

array_push(
  $apple->row,
  "information_user_id"
 
  );

$apple->sql='SELECT * FROM `information_user` WHERE  `information_user_login`="'.$information_user_login.'" ';
$apple->execution();
$myJSON = json_encode($apple->list_row); 

 if(count($apple->list_row)>0){
  $apple = new Select_datas($servername,$username,$password,$dbname);
array_push(
  $apple->row,
   	"information_user_id",	 		 
	 	"information_user_id_sha1",	 
	 	"information_user_ip", 
 	  "information_user_login",	 
 	  "information_user_password", 
 	  "information_user_new_file", 
    "information_user_reg_date"
  );

$apple->sql='SELECT * FROM `information_user` WHERE `information_user_login`="'.$information_user_login.'" AND `information_user_password`="'.$information_user_password.'"';
$apple->execution();
$myJSON = json_encode($apple->list_row); 

if(strlen($myJSON)>3){
  $n_plus = 0 ; 
  $_SESSION["information_user_id"]= $apple->list_row[$n_plus] ;
  $n_plus ++ ;  
  $_SESSION["information_user_id_sha1"]= $apple->list_row[$n_plus] ;
  $n_plus ++ ;  

  $_SESSION["information_user_ip"]= $apple->list_row[$n_plus] ;
  $n_plus ++ ;  
  $_SESSION["information_user_login"]= $apple->list_row[$n_plus] ;
  $n_plus ++ ;  
  $_SESSION["information_user_password"]= $apple->list_row[$n_plus] ;
  $n_plus ++ ;  
  $_SESSION["information_user_new_file"]= $apple->list_row[$n_plus] ;
  $n_plus ++ ;  
  $_SESSION["information_user_reg_date"]= $apple->list_row[$n_plus] ;
  $n_plus ++ ;  
  $_SESSION["information_user_info"] = 200;
  // COnnection reussi
}
else {
  echo "ECHEC  de la connnexion" ; 
  $_SESSION["information_user_info"] = 100;
  // erreur de mot de passe 
}

 }
 else {
  $_SESSION["information_user_info"] = "400";
  // creation d'utilisateur
  $information_user_sha1 = sha1(rand(12, 9999999999999).$information_user_login) ; 

  $apple = new Insertion_Bdd(
    $servername,
    $username,
    $password,
    $dbname
    
    );
    $apple->set_msg_valudation("inserttion ok ") ;  
    $apple->set_sql("INSERT INTO information_user (information_user_login,information_user_password,information_user_id_sha1)
    VALUES ('$information_user_login','$information_user_password','$information_user_sha1')") ; 
    $apple->execution() ;
    $_SESSION["information_user_id"]= "" ; 
    $_SESSION["information_user_id_sha1"]=    $information_user_sha1  ; 
    $_SESSION["information_user_login"]=     $information_user_login  ; 
    $_SESSION["information_user_password"]=     $information_user_password   ; 
    $_SESSION["information_user_reg_date"]=    ""  ; 
 }
 /*
if($_SERVER['REMOTE_ADDR']=="::1") {
    echo "LOCAL ACTION" ;
}
*/
 ?>