<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
 
$password_information_user =$_POST["password_information_user"] ;
$mail_information_user =$_POST["mail_information_user"] ;
$var_action_header_0 =$_POST["var_action_header_0"] ;
 
include("../model/class/php/connexion.php"); 
include("../model/class/php/bdd_all_class.php"); 





// Exemple de code 
 $apple = new Bdd_class($servername ,$username,$password,$dbname);
 /*
$apple->array_push_row_name("id_showCoords",0) ; 
$apple->array_push_row_name("adresse_ip",1) ; 
$apple->array_push_row_name("click",2) ; 
$apple->array_push_row_name("x_position",3) ; 
$apple->array_push_row_name("width_taille_page",4) ; 
$apple->array_push_row_name("height_taille_page",5) ; 
$apple->array_push_row_name("y_position",6) ; 
$apple->array_push_row_name("reg_date",7) ; 
$apple->array_push_row_name("width_taille_page",8) ; 
$apple->array_push_row_name("height_taille_page",9) ; 
$apple->array_push_row_name("y_position",10) ; 
$apple->array_push_row_name("reg_date",11) ; 

*/
 $apple->set_sql('SELECT * FROM `information_user` WHERE `password_information_user`="'.$password_information_user.'" AND `mail_information_user`="'.$mail_information_user.'"'); 

  $apple->exe_select() ; 
  echo $apple->get_exe_select_bool()  ;

  if($apple->get_exe_select_bool()=="1"){
    $_SESSION['server'] ="on";
  }
  else {
    $_SESSION['server'] ="off";
  }

//echo $apple-> get_row_1(0) ;  


/*

for($i=0 ; $i<5 ; $i++) {
 
  echo $apple->get_row_1($i) ;
  echo  "<br/>" ; 
  echo $apple->get_row_2($i) ;
  echo  "<br/>" ; 

  echo $apple->get_row_3($i) ;
  echo  "<br/>" ; 
  echo $apple->get_row_4($i) ;
  echo  "<br/>" ; 

  echo $apple->get_row_5($i) ;
  echo  "<br/>" ; 
  echo $apple->get_row_6($i) ;
  echo  "<br/>" ; 
  echo $apple->get_row_7($i) ;
  echo  "<br/>" ; 

  echo $apple->get_row_8($i) ;
  echo  "<br/>" ; 
  echo $apple->get_row_9($i) ;
  echo  "<br/>" ; 
  echo $apple->get_row_10($i) ;
  echo  "<br/>" ; 
  echo $apple->get_row_11($i) ;
  echo  "<br/>" ; 
    

}
*/
 
/*
effacer element d'une table 
$apple->set_action("DELETE FROM `showcoords` WHERE `showcoords`.`id_showCoords` = 89") ;
*/ 

//$apple->set_action("UPDATE `showcoords` SET `x_position` = 'BRAVO' WHERE `showcoords`.`id_showCoords` = 5") ;
/*
creer une table
$apple->set_action("CREATE TABLE Alomechor (
  id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firstname VARCHAR(30) NOT NULL,
  lastname VARCHAR(30) NOT NULL,
  email VARCHAR(50),
  reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )") ;


  */
?>