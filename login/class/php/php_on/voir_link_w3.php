<?php 

 session_start() ; 
 header("Access-Control-Allow-Origin: *");

include("link.php") ;  



$liste_projet_img = $_SESSION["liste_projet_img"] ;
$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

    'liste_projet_id',
    'liste_projet_id_sha1',
    'liste_projet_ip',
    'liste_projet_name',
    'liste_projet_description1',
    'liste_projet_description2',
    'liste_projet_visibilite1',
    'liste_projet_visibilite2',
    'liste_projet_type',
    'information_user_id_sha1',
    'liste_projet_new_file',
    'liste_projet_reg_date'

    );
 
    $id_information_user_sha1 =$_SESSION["information_user_id_sha1"] ; 
    $apple->sql='SELECT * FROM `liste_projet` WHERE  `liste_projet_id_sha1`="'.$liste_projet_img.'"';
    $apple->execution();
    $myJSON = json_encode($apple->list_row);     

    // echo   $myJSON ; 
 
   
    



    $apple2 = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple2->row,

    'liste_projet_id',
    'liste_projet_id_sha1',
    'liste_projet_ip',
    'liste_projet_name',
    'liste_projet_img',
    'liste_projet_description1',
    'liste_projet_description2',
    'liste_projet_visibilite1',
    'liste_projet_visibilite2',
    'liste_projet_type',
    'information_user_id_sha1',
    'liste_projet_new_file',
    'liste_projet_reg_date'

    );
 
    $id_information_user_sha1 =$_SESSION["information_user_id_sha1"] ; 
    $apple2->sql='SELECT * FROM `liste_projet` WHERE  `liste_projet_id_parent`="'.$liste_projet_img.'"';
    $apple2->execution();
    $myJSON = json_encode($apple2->list_row);     

    // echo   $myJSON ; 
 
echo "[";
    $apple->all_data_json() ;
    echo "," ; 
    $apple2->all_data_json() ;

    echo "]";









    
 ?>

 
  
 