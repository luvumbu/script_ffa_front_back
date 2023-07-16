<?php 

 session_start() ; 
 header("Access-Control-Allow-Origin: *");

include("link.php") ;  


$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

    'liste_group_id',
    'liste_group_id_sha1', 
    'liste_group_name', 
    'liste_group_ip', 
    'liste_group_information_user_id_sha1', 
    'liste_group_liste_projet_id_sha1', 
    'liste_group_liste_projet_child_sha1', 
    'liste_group_new_file', 
    'liste_group_reg_date' 

    );
 
    $id_information_user_sha1 =$_SESSION["information_user_id_sha1"] ; 
    $apple->sql='SELECT * FROM `liste_group` WHERE `liste_group_information_user_id_sha1`="'.$id_information_user_sha1.'"';
    $apple->execution();
    $myJSON = json_encode($apple->list_row); 

    // echo   $myJSON ; 
 
    $apple->all_data_json() ; 
 ?>

 
  
 