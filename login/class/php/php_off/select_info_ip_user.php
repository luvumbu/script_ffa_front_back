<?php 

 //header("Access-Control-Allow-Origin: *");

include("link2.php") ;  



 
 
$pdo = new PDO('mysql:host='.$servername.';dbname='.$dbname,$username,$password);

$sql = 'SHOW TABLES FROM '.$dbname.' LIKE "liste_like"';
$req = $pdo->query($sql);

if($req->rowCount() > 0)
{
  $apple = new Select_datas($servername,$username,$password,$dbname);

array_push(
  $apple->row,
  "id_info_cookie_cool_cook",
  "id_information_user_cook", 
  "ip_info_cookie_cool_cook",
  "action_info_cookie_cool_cook",
  "creation_file_cook", 
  "reg_date_cook"

  );

$apple->sql='SELECT * FROM `info_cookie_cool` WHERE 1';
$apple->execution();
$myJSON = json_encode($apple->list_row); 




$REMOTE_ADDR=  $_SERVER["REMOTE_ADDR"] ; 
if (in_array($REMOTE_ADDR, $apple->list_row))
{





$apple = new Insertion_Bdd(
  $servername,
  $username,
  $password,
  $dbname
  
  );
  $apple->set_msg_valudation("") ;  
  $apple->set_sql("INSERT INTO info_cookie_save (ip_info_cookie_save_sv)
  VALUES ('$REMOTE_ADDR')") ; 
  $apple->execution() ;
}
else {

 include("pages_off/cookie.html");

}
}
   
else
{
  header('Location: ../index.php');

  unlink('../model/class/php/connexion.php"');
  unlink('../model/class/php/config_folder_verif.php');
  exit();
}
    

$req->closeCursor();

 

 
   

 






 

 ?>

 
 