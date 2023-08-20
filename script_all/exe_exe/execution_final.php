<?php
header("Access-Control-Allow-Origin: *"); 
 


 include("Insertion_Bdd.php") ; 
 
 
 /*

 

 $servername="localhost";
$username ="u489596434_cnam";
$password ="v3p9r3e@59A" ;
$dbname ="u489596434_cnam";

*/
function compteur_general($compteur_info){





  $stock = array() ; 
  $stock_nombre = 0 ; 
  for($i =0 ; $i< strlen($compteur_info) ; $i ++ ) {
    

    if($compteur_info[$i]==","){
      $stock_nombre ++ ; 
    }
    if($compteur_info[$i]!=","){
      $stock[$stock_nombre] = $stock[$stock_nombre].$compteur_info[$i] ; 

    }
 
 
  }


  







// test 



// fin du test 






 
  
 return  $stock ; 




}
 
 
 



 $get_rp_array_2 =   compteur_general($_POST["get_rp_array_2"]) ; 
 $get_vent_array_2 =   compteur_general($_POST["get_vent_array_2"]) ; 
 $get_result_users_perf_array_2 =   compteur_general($_POST["get_result_users_perf_array_2"]) ; 
 $get_result_users_nom_1_array_2 =   compteur_general($_POST["get_result_users_nom_1_array_2"]) ; 
 
 $get_result_users_nom_2_array_2 =   compteur_general($_POST["get_result_users_nom_2_array_2"]) ; 
 $get_result_users_nom_3_array_2 =   compteur_general($_POST["get_result_users_nom_3_array_2"]) ; 
 $get_result_users_nom_4_array_2 =   compteur_general($_POST["get_result_users_nom_4_array_2"]) ; 
 $get_users_nationality_array_2 =   compteur_general($_POST["get_users_nationality_array_2"]) ; 
 $get_club_nom_complet_array_2 =   compteur_general($_POST["get_club_nom_complet_array_2"]) ; 
 

 $get_club_departement_array_2 =   compteur_general($_POST["get_club_departement_array_2"]) ; 
 $get_club_region_array_2 =   compteur_general($_POST["get_club_region_array_2"]) ; 
 $get_cat_array_2 =   compteur_general($_POST["get_cat_array_2"]) ; 
 $get_users_naissance_array_2 =   compteur_general($_POST["get_users_naissance_array_2"]) ; 
 $get_result_date_perf_array_2 =   compteur_general($_POST["get_result_date_perf_array_2"]) ; 
 
 $get_result_villes_nom_array_2 =   compteur_general($_POST["get_result_villes_nom_array_2"]) ; 
 $epreuve_sex_array_2 =   compteur_general($_POST["epreuve_sex_array_2"]) ; 
 $get_users_nom_complet_array =   compteur_general($_POST["get_users_nom_complet_array"]) ; 
 $info_all_array_click =   compteur_general($_POST["info_all_array_click"]) ; 
 $info_all_array_ip =   compteur_general($_POST["info_all_array_ip"]) ; 


 $info_all_array_src_name =   compteur_general($_POST["info_all_array_src_name"]) ; 
 $window_location_href =   compteur_general($_POST["window_location_href"]) ; 
  

 
 
 











 for($i = 0 ; $i < count($get_result_users_nom_1_array_2) ; $i ++) {
 
$get_emplacement_i =$_POST["get_emplacement"] ;
$get_rp_array_2_i = $get_rp_array_2[$i] ;
$get_vent_array_2_i = $get_vent_array_2[$i] ;
$get_result_users_perf_array_2_i = $get_result_users_perf_array_2[$i] ;
$get_result_users_nom_1_array_2_i = $get_result_users_nom_1_array_2[$i] ;
$get_result_users_nom_2_array_2_i = $get_result_users_nom_2_array_2[$i] ;
$get_result_users_nom_3_array_2_i = $get_result_users_nom_3_array_2[$i] ;
$get_result_users_nom_4_array_2_i = $get_result_users_nom_4_array_2[$i] ;
$get_users_nationality_array_2_i = $get_users_nationality_array_2[$i] ;
$get_club_nom_complet_array_2_i = $get_club_nom_complet_array_2[$i] ;
$get_club_departement_array_2_i = $get_club_departement_array_2[$i] ;
$get_club_region_array_2_i = $get_club_region_array_2[$i] ;
$get_cat_array_2_i = $get_cat_array_2[$i] ;
$get_users_naissance_array_2_i = $get_users_naissance_array_2[$i] ;
$get_result_date_perf_array_2_i = $get_result_date_perf_array_2[$i] ;
$get_result_villes_nom_array_2_i = $get_result_villes_nom_array_2[$i] ;
$epreuve_sex_array_2_i = $epreuve_sex_array_2[$i] ;
$get_users_nom_complet_array_i = $get_users_nom_complet_array[$i] ;
$info_all_array_click_i = $info_all_array_click[$i] ;
$info_all_array_ip_i = $info_all_array_ip[$i] ;
$info_all_array_src_name_i = $info_all_array_src_name[$i] ;
$window_location_href_i = $_POST["window_location_href"];
$get_epreuve_nom_complet_i = $_POST["get_epreuve_nom_complet"];



$apple = new Insertion_Bdd(
  $servername,
  $username,
  $password,
  $dbname
  
  );
      
 
  $apple->set_msg_valudation("inserttion ok ") ;  
  $apple->set_sql("INSERT INTO info_all_array (

    get_emplacement,
    get_rp_array_2,
    get_vent_array_2,
    get_result_users_perf_array_2,
    get_result_users_nom_1_array_2,

    get_result_users_nom_2_array_2,
    get_result_users_nom_3_array_2,
    get_result_users_nom_4_array_2,
    get_users_nationality_array_2,
    get_club_nom_complet_array_2,

    get_club_departement_array_2,
    get_club_region_array_2,
    get_cat_array_2,
    get_users_naissance_array_2,
    get_result_date_perf_array_2,

    get_result_villes_nom_array_2,
    epreuve_sex_array_2,
    get_users_nom_complet_array,
    info_all_array_click,
    info_all_array_ip,

    info_all_array_src_name,
    window_location_href,
    get_epreuve_nom_complet 



    

    )
          
  VALUES (

    '$get_emplacement_i',
    '$get_rp_array_2_i',
    '$get_vent_array_2_i',
    '$get_result_users_perf_array_2_i',
    '$get_result_users_nom_1_array_2_i',

    '$get_result_users_nom_2_array_2_i',
    '$get_result_users_nom_3_array_2_i',
    '$get_result_users_nom_4_array_2_i',
    '$get_users_nationality_array_2_i',
    '$get_club_nom_complet_array_2_i',

    '$get_club_departement_array_2_i',
    '$get_club_region_array_2_i',
    '$get_cat_array_2_i',
    '$get_users_naissance_array_2_i',
    '$get_result_date_perf_array_2_i',

    '$get_result_villes_nom_array_2_i',
    '$epreuve_sex_array_2_i',
    '$get_users_nom_complet_array_i',
    '$info_all_array_click_i',
    '$info_all_array_ip_i',

    '$info_all_array_src_name_i',
    '$window_location_href_i',
    '$get_epreuve_nom_complet_i'

    )") ; 
  $apple->execution() ;


 echo "FIN" ; 


 }

 
 
 
 

 
?>
