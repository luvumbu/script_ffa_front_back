<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
include("Get_elements_tab.php") ; 
include("Insertion_Bdd.php") ; 
 $mail_information_user = $_POST["mail_information_user"] ; 
 $password_information_user = $_POST["password_information_user"] ; 
//exemple complet 
//exemple complet 

$apple = new Get_elements_tab($servername,$dbname,$username,$password);
// n°1 
array_push($apple->row ,"mail_information_user");
// n°2
$apple->set_sql('SELECT * FROM `information_user` WHERE `mail_information_user`="'.$mail_information_user.'" ') ; 
// n°3
 
$apple->execution_total(); 




$apple_2 = new Get_elements_tab($servername,$dbname,$username,$password);
// n°1 
array_push($apple_2->row ,"mail_information_user","id_information_user");
// n°2
$apple_2->set_sql('SELECT * FROM `information_user` WHERE `mail_information_user`="'.$mail_information_user.'" AND `password_information_user`="'.$password_information_user.'" ') ; 
// n°3
 
$apple_2->execution_total(); 
 

 

 
// n°4
 if(count($apple->list_row)!=0){
    echo count($apple->list_row) ; 


    





    if(count($apple_2->list_row)!=0){
      echo count($apple_2->list_row) ; 
  
  
      $_SESSION['start']  = $mail_information_user ; 
      $_SESSION['id_information_user']  = $apple_2->list_row[1] ; 
      
   }
    
 }
 else {
 

 

$apple = new Insertion_Bdd(

  $servername,
  $username,
  $password,
  $dbname
  
  );
  
  $apple->set_msg_valudation("Enregistrement ok") ;  
  $apple->set_sql('INSERT INTO information_user (mail_information_user,password_information_user)     VALUES ("'.$mail_information_user.'","'.$password_information_user.'")') ; 
  $apple->execution() ; 
 
 
       
 }






 


?> 