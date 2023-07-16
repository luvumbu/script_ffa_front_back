<?php 

 session_start() ; 
 header("Access-Control-Allow-Origin: *");

include("link.php") ;  


$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,
    "id_information_user",
    "password_information_user",
    "mail_information_user",
    "reg_date"
  
    );

  $apple->sql='SELECT * FROM `information_user` WHERE 1 ORDER BY `information_user_id`';
  $apple->execution();
  $myJSON = json_encode($apple->list_row); 
 echo $myJSON;



 

 ?>

 
 