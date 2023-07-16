<?php 

 session_start() ; 
 header("Access-Control-Allow-Origin: *");


include("link.php") ;   
$REMOTE_ADDR = $_SERVER['REMOTE_ADDR'] ; 

if(strlen($REMOTE_ADDR)<5){
 
  // local 

  $replace ="/Model_Vue8/login/class/php/blog/liste_projet.php/";

  $replace = str_replace($replace,"",$_SERVER['PHP_SELF']) ; 
}
 
  
 



$apple1 = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple1->row,

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
 
    $id_information_user_sha1 =$replace ;
    $apple1->sql='SELECT * FROM `liste_projet` WHERE `liste_projet_id_sha1`="'.$id_information_user_sha1.'"  ORDER BY `liste_projet_id`';
    $apple1->execution();
    $myJSON = json_encode($apple1->list_row); 

    // echo   $myJSON ; 
 






 
 


if($apple1->verif_info!=0){
  $test = $apple1->list_row[1] ;
}




    
$apple2 = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple2->row,

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
 
    $id_information_user_sha1 =$replace ;
    $apple2->sql='SELECT * FROM `liste_projet` WHERE `liste_projet_id_parent`="'.$test.'"  ORDER BY `liste_projet_id`';
    $apple2->execution();
    $myJSON = json_encode($apple2->list_row); 

    // echo   $myJSON ; 
 
if($apple2->verif_info!=0){
  $test = $apple2->list_row[1] ;
}



//  !!!!!!!

$apple3 = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple3->row,

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
 
    $id_information_user_sha1 =$replace ;
    $apple3->sql='SELECT * FROM `liste_projet` WHERE `liste_projet_id_parent`="'.$test.'"  ORDER BY `liste_projet_id`';
    $apple3->execution();
    $myJSON = json_encode($apple3->list_row); 

    // echo   $myJSON ; 
 
if($apple3->verif_info!=0){
  $test = $apple3->list_row[1] ;
}


//  !!!!!!!

$apple4 = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple4->row,

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
 
    $id_information_user_sha1 =$replace ;
    $apple4->sql='SELECT * FROM `liste_projet` WHERE `liste_projet_id_parent`="'.$test.'"  ORDER BY `liste_projet_id`';
    $apple4->execution();
    $myJSON = json_encode($apple4->list_row); 

    // echo   $myJSON ; 
 
if($apple4->verif_info!=0){
  $test = $apple4->list_row[1] ;
}
//  !!!!!!!

$apple5 = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple5->row,

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
 
    $id_information_user_sha1 =$replace ;
    $apple5->sql='SELECT * FROM `liste_projet` WHERE `liste_projet_id_parent`="'.$test.'"  ORDER BY `liste_projet_id`';
    $apple5->execution();
    $myJSON = json_encode($apple5->list_row); 

    // echo   $myJSON ; 
 
if($apple5->verif_info!=0){
  $test = $apple5->list_row[1] ;
}
//  !!!!!!!

$apple6 = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple6->row,

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
 
    $id_information_user_sha1 =$replace ;
    $apple6->sql='SELECT * FROM `liste_projet` WHERE `liste_projet_id_parent`="'.$test.'"  ORDER BY `liste_projet_id`';
    $apple6->execution();
    $myJSON = json_encode($apple6->list_row); 

    // echo   $myJSON ; 
 
if($apple6->verif_info!=0){
  $test = $apple6->list_row[1] ;
}
 
    echo "[";

 



if($apple1->verif_info!=0){
   $apple1->all_data_json() ;
}
else {
  include("404.php") ;
}

    echo "," ; 

    if($apple2->verif_info!=0){
      $apple2->all_data_json() ;
   }
   else {
     include("404.php") ;
   }
   echo "," ; 
 
   if($apple3->verif_info!=0){
    $apple3->all_data_json() ;
 }
 else {
   include("404.php") ;
 }

 echo "," ; 
 
 if($apple4->verif_info!=0){
  $apple4->all_data_json() ;
}
else {
 include("404.php") ;
}
echo "," ; 
 
if($apple5->verif_info!=0){
 $apple5->all_data_json() ;
}
else {
include("404.php") ;
}
echo "," ; 
 
if($apple6->verif_info!=0){
 $apple6->all_data_json() ;
}
else {
include("404.php") ;
}

    echo "]";


 ?>

 
  
 