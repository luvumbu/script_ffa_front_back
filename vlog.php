<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
?>
 
<?php 

include("model/class/php/Select_datas.php") ;  
include("model/class/php/Insertion_Bdd.php") ; 




// PArtie en local 
$dbname="u489596434_ffa_2x";
$username="u489596434_ffa_2x";
$password="v3p9r3e@59A";
$servername="localhost";
//http://localhost/script_ffa_front_back/vlog.php/[id_info_all_array]/2512
 
/*
 // Partie en ligne 
// https://bokonzi.com/ffa/vlog.php/[get_result_users_nom_1_array_2]/ndenga
 

$dbname="u489596434_ffa_2x";
$username="u489596434_ffa_2x";
$password="v3p9r3e@59A";
$servername="localhost";

*/
 

$date_array = array() ; 
$ip_array = array() ; 


$liste_visite_page_id_array = array() ; 
$liste_visite_page_id_sha1_array = array() ; 
$liste_visite_page_ip_array = array() ; 
$liste_visite_page_reg_date_1_array = array() ; 
$liste_visite_page_reg_date_array = array() ; 



$ip_a = $_SERVER['REMOTE_ADDR'] ; 
$t = time(date_default_timezone_set('Europe/Paris')) ; 
$tiempo = date("Y-m-d",$t);


    


 

 




 
 

 
  
 
 

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


  



 $url = strrev($eman);



 
  


$eman="";
$name="";



 for($i=strlen($_SERVER['PHP_SELF'])-1; $i>-1;$i--){
    
     if($_SERVER['PHP_SELF'][$i]=='/'){
       break ; 
     }
     $eman = $eman.$_SERVER['PHP_SELF'][$i] ; 
 }




$recher_verif_element =false ; 
$recher_verif_data ="`" ; 

 for($y =0; $y<strlen($_SERVER['PHP_SELF']);$y++){
    


 


  if( $recher_verif_element==true &&  $_SERVER['PHP_SELF'][$y]!="]"){
 
    $recher_verif_data = $recher_verif_data.$_SERVER['PHP_SELF'][$y]  ; 

    
  }

  if($_SERVER['PHP_SELF'][$y]=="["){
    $recher_verif_element = true ; 
  }
  if($_SERVER['PHP_SELF'][$y]=="]"){
   break ;
  }


 
}



 



 $eman = $eman;



 for($i=strlen($eman)-1; $i>-1;$i--){
    


 $name = $name.$eman[$i] ;




}



$recher_verif_data =  $recher_verif_data.'`';


// SELECT * FROM `info_all_array` WHERE `get_result_users_nom_1_array_2`="ndenga";
 

/*

$apple = new Select_datas($servername,$username,$password,$dbname);

 array_push(
   $apple->row,

   'id_info_all_array' 
   

   );

 
   $apple->sql='SELECT * FROM `info_all_array` WHERE '.$recher_verif_data.'="'.$name.'"';
   $apple->execution();
   $myJSON = json_encode($apple->list_row); 

  echo   $myJSON ; 


  */


   
 

// CODE exeMPLe 
 
 
 

$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

 
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
 
   
    $apple->sql='SELECT * FROM `info_all_array` WHERE '.$recher_verif_data.'="'.$name.'" LIMIT 50';
    $apple->execution();
    $myJSON = json_encode($apple->list_row); 

    // echo   $myJSON ; 
 
    $apple->all_data_json() ; 

  ?>


 