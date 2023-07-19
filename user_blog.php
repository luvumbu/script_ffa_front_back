<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">


<?php 

include("model/class/php/Select_datas.php") ;  
include("model/class/php/Insertion_Bdd.php") ; 
 
$date_array = array() ; 
$ip_array = array() ; 


$id_get_result_users_nom_2_array_2 = array() ; 
$id_get_result_users_nom_3_array_2 = array() ; 
$id_get_result_users_nom_4_array_2 = array() ; 
$id_get_users_nationality_array_2 = array() ; 
$id_get_club_nom_complet_array_2 = array() ; 

$id_get_club_departement_array_2 = array() ; 
$id_get_club_region_array_2 = array() ; 
$id_get_cat_array_2 = array() ; 
$id_get_users_naissance_array_2 = array() ; 
$id_get_result_date_perf_array_2 = array() ; 

$id_get_result_villes_nom_array_2 = array() ; 
$id_epreuve_sex_array_2 = array() ; 
$id_get_users_nom_complet_array = array() ; 
$id_get_epreuve_nom_complet = array() ; 
$get_emplacement = array() ; 

$get_rp_array_2 = array() ; 
$get_vent_array_2 = array() ; 
$get_result_users_perf_array_2 = array() ; 
$get_result_users_nom_1_array_2 = array() ; 
$get_result_users_nom_2_array_2 = array() ; 

$get_result_users_nom_3_array_2 = array() ; 
$get_result_users_nom_4_array_2 = array() ; 
$get_users_nationality_array_2 = array() ; 
$get_club_nom_complet_array_2 = array() ; 
$get_club_departement_array_2 = array() ; 

$get_club_region_array_2 = array() ; 
$get_cat_array_2 = array() ; 
$get_users_naissance_array_2 = array() ; 
$get_result_date_perf_array_2 = array() ; 
$get_result_villes_nom_array_2 = array() ; 
 

$epreuve_sex_array_2 = array() ; 
$get_users_nom_complet_array = array() ; 
$info_all_array_click = array() ; 
$info_all_array_ip = array() ; 
$info_all_array_src_name = array() ; 

$window_location_href = array() ; 
$get_epreuve_nom_complet = array() ; 
$reg_date = array() ; 
 



$ip_a = $_SERVER['REMOTE_ADDR'] ; 
$t = time(date_default_timezone_set('Europe/Paris')) ; 
$tiempo = date("Y-m-d",$t);

 

 

 // Exemple URL 
 // http://localhost/script_ffa_front_back/user_blog.php/[get_result_users_nom_1_array_2]/BANTWELL

  
 
 

 $name ="" ; 
 $eman ="" ; 




 for($i = 0 ; $i<strlen($_SERVER['PHP_SELF']) ; $i ++){


  if(strrev($_SERVER['PHP_SELF'])[$i]=="/") {
    break ;
  }
  else {
    $eman =  $eman .strrev($_SERVER['PHP_SELF'])[$i] ; 
  }
 }




 $apli= false ; 

$where_element = "`";

 for($i = 0 ; $i<strlen($_SERVER['PHP_SELF']) ; $i ++){


  if($_SERVER['PHP_SELF'][$i]=="[") {

    $apli=true  ; 
  }
  if($_SERVER['PHP_SELF'][$i]=="]") {

    $apli=false  ; 
    $where_element = $where_element."`";
  }

  if( $apli==true && $_SERVER['PHP_SELF'][$i]!="[" ){

    $where_element = $where_element.$_SERVER['PHP_SELF'][$i] ; 
    
 

  }
 
 }




 

  



 $url = strrev($eman);



 
  


$eman="";
$name="";



 for($i=strlen($_SERVER['PHP_SELF'])-1; $i>-1;$i--){
    
     if($_SERVER['PHP_SELF'][$i]=='/'){
       break ; 
     }
     $eman = $eman.$_SERVER['PHP_SELF'][$i] ; 
 }

 $eman = $eman;



 for($i=strlen($eman)-1; $i>-1;$i--){
    


 $name = $name.$eman[$i] ;




}



 
$_mon_coump =[
'id_get_result_users_nom_2_array_2',
'id_get_result_users_nom_3_array_2',
'id_get_result_users_nom_4_array_2',
'id_get_users_nationality_array_2',
'id_get_club_nom_complet_array_2',
'id_get_club_departement_array_2',
'id_get_club_region_array_2',
'id_get_cat_array_2',
'id_get_users_naissance_array_2',
'id_get_result_date_perf_array_2',
'id_get_result_villes_nom_array_2',
'id_epreuve_sex_array_2',
'id_get_users_nom_complet_array',
'id_get_epreuve_nom_complet',
'get_emplacement',
'get_rp_array_2',
'get_vent_array_2',
'get_result_users_perf_array_2',
'get_result_users_nom_1_array_2',
'get_result_users_nom_2_array_2',
'get_result_users_nom_3_array_2',
'get_result_users_nom_4_array_2',
'get_users_nationality_array_2',
'get_club_nom_complet_array_2',
'get_club_departement_array_2',
'get_club_region_array_2',
'get_cat_array_2',
'get_users_naissance_array_2',
'get_result_date_perf_array_2',
'get_result_villes_nom_array_2',
'epreuve_sex_array_2',
'get_users_nom_complet_array',
'info_all_array_click',
'info_all_array_ip',
'info_all_array_src_name',
'window_location_href',
'get_epreuve_nom_complet',
'reg_date'];


 $dbname="u489596434_ffa_2x" ; 

 $recherche="`get_result_users_nom_1_array_2`" ; 
 $nom_recherche =$name;
 
$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

    'id_get_result_users_nom_2_array_2',
    'id_get_result_users_nom_3_array_2',
    'id_get_result_users_nom_4_array_2',
    'id_get_users_nationality_array_2',
    'id_get_club_nom_complet_array_2',
    'id_get_club_departement_array_2',
    'id_get_club_region_array_2',
    'id_get_cat_array_2',
    'id_get_users_naissance_array_2',
    'id_get_result_date_perf_array_2',
    'id_get_result_villes_nom_array_2',
    'id_epreuve_sex_array_2',
    'id_get_users_nom_complet_array',
    'id_get_epreuve_nom_complet',
    'get_emplacement',
    'get_rp_array_2',
    'get_vent_array_2',
    'get_result_users_perf_array_2',
    'get_result_users_nom_1_array_2',
    'get_result_users_nom_2_array_2',
    'get_result_users_nom_3_array_2',
    'get_result_users_nom_4_array_2',
    'get_users_nationality_array_2',
    'get_club_nom_complet_array_2',
    'get_club_departement_array_2',
    'get_club_region_array_2',
    'get_cat_array_2',
    'get_users_naissance_array_2',
    'get_result_date_perf_array_2',
    'get_result_villes_nom_array_2',
    'epreuve_sex_array_2',
    'get_users_nom_complet_array',
    'info_all_array_click',
    'info_all_array_ip',
    'info_all_array_src_name',
    'window_location_href',
    'get_epreuve_nom_complet',
    'reg_date'
 

    );
 
 
    $apple->sql='SELECT * FROM `info_all_array` WHERE  '.$where_element.' = "'.$nom_recherche.'" ';
    $apple->execution();
    $myJSON = json_encode($apple->list_row);     

    // echo   $myJSON ; 



 if(count($apple->list_row)>0){
 





  // Ajout de l'adresse ip 





  
  
  
  

  
  
  


 



    


       for($i= 0 ; $i<count($apple->list_row) ; $i++){
     //   echo $i.'<br/>' ; 


       //echo(fmod($i, 4) . "<br>");


 


       switch (fmod($i, count($_mon_coump))) {
        case 0:
              array_push($id_get_result_users_nom_2_array_2,$apple->list_row[$i]);
        break;
        case 1:
          array_push($id_get_result_users_nom_3_array_2,$apple->list_row[$i]);
        break;    
        case 2:
          array_push($id_get_result_users_nom_4_array_2,$apple->list_row[$i]);
        break;
        case 3:
          array_push($id_get_users_nationality_array_2,$apple->list_row[$i]);
        break;  
        case 4:
          array_push($id_get_club_nom_complet_array_2,$apple->list_row[$i]);
        break; 
        
    case 5:
          array_push($id_get_club_departement_array_2,$apple->list_row[$i]);
    break;
    case 6:
      array_push($id_get_club_region_array_2,$apple->list_row[$i]);
    break;    
    case 7:
      array_push($id_get_cat_array_2,$apple->list_row[$i]);
    break;
    case 8:
      array_push($id_get_users_naissance_array_2,$apple->list_row[$i]);
    break;  
    case 9:
      array_push($id_get_result_date_perf_array_2,$apple->list_row[$i]);
    break; 

    case 10:
      array_push($id_get_result_villes_nom_array_2,$apple->list_row[$i]);
    break;
    case 11:
      array_push($id_epreuve_sex_array_2,$apple->list_row[$i]);
    break;    
    case 12:
      array_push($id_get_users_nom_complet_array,$apple->list_row[$i]);
    break;
    case 13:
      array_push($id_get_epreuve_nom_complet,$apple->list_row[$i]);
    break;  
    case 14:
      array_push($get_emplacement,$apple->list_row[$i]);
    break; 

    
    case 15:
      array_push($get_rp_array_2,$apple->list_row[$i]);
    break;
    case 16:
      array_push($get_vent_array_2,$apple->list_row[$i]);
    break;    
    case 17:
      array_push($get_result_users_perf_array_2,$apple->list_row[$i]);
    break;
    case 18:
      array_push($get_result_users_nom_1_array_2,$apple->list_row[$i]);
    break;  
    case 19:
      array_push($get_result_users_nom_2_array_2,$apple->list_row[$i]);
    break; 

    case 20:
      array_push($get_result_users_nom_3_array_2,$apple->list_row[$i]);
    break;
    case 21:
      array_push($get_result_users_nom_4_array_2,$apple->list_row[$i]);
    break;    
    case 22:
      array_push($get_users_nationality_array_2,$apple->list_row[$i]);
    break;
    case 23:
      array_push($get_club_nom_complet_array_2,$apple->list_row[$i]);
    break;  
    case 24:
      array_push($get_club_departement_array_2,$apple->list_row[$i]);
    break; 

    case 25:
      array_push($get_club_region_array_2,$apple->list_row[$i]);
    break;
    case 26:
      array_push($get_cat_array_2,$apple->list_row[$i]);
    break;    
    case 27:
      array_push($get_users_naissance_array_2,$apple->list_row[$i]);
    break;
    case 28:
      array_push($get_result_date_perf_array_2,$apple->list_row[$i]);
    break;  
    case 29:
      array_push($get_result_villes_nom_array_2,$apple->list_row[$i]);
    break; 

    case 30:
      array_push($epreuve_sex_array_2,$apple->list_row[$i]);
    break;
    case 31:
      array_push($get_users_nom_complet_array,$apple->list_row[$i]);
    break;    
    case 32:
      array_push($info_all_array_click,$apple->list_row[$i]);
    break;
    case 33:
      array_push($info_all_array_ip,$apple->list_row[$i]);
    break;  
    case 34:
      array_push($info_all_array_src_name,$apple->list_row[$i]);
    break; 

    case 35:
      array_push($window_location_href,$apple->list_row[$i]);
    break;
    case 36:
      array_push($get_epreuve_nom_complet,$apple->list_row[$i]);
    break;    
    case 37:
      array_push($reg_date,$apple->list_row[$i]);
    break; 
        


 
 
      }

 




 


       }
 
  
       
  
  

  // fin ajout adresse ip 
 }

    


 

 




 print_r($reg_date) ; 












 // Datas paralleles 



 //  Datas

 