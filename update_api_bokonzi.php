<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
 
include("model/class/php/Select_datas.php") ;  
include("model/class/php/Insertion_Bdd.php") ; 



$get_epreuve_nom_complet = $_POST["get_epreuve_nom_complet"] ;
$id_get_epreuve_nom_complet = $_POST["id_get_epreuve_nom_complet"] ;

 

// PArtie en local 
 
//http://localhost/script_ffa_front_back/vlog.php/[id_info_all_array]/2512
 
 
 // Partie en ligne 
// https://bokonzi.com/ffa/vlog.php/[get_result_users_nom_1_array_2]/ndenga
 

$dbname="u489596434_bokonzi_on";
$username="u489596434_bokonzi_on";
$password="v3p9r3e@59A";
$servername="localhost";

 
 
 
 
       $apple = new Insertion_Bdd(
        $servername,
        $username,
        $password,
        $dbname
        
        );
            
       
        $apple->set_msg_valudation("inserttion ok ") ;  
        $apple->set_sql('UPDATE `info_all_array` SET `id_get_epreuve_nom_complet`="'.$id_get_epreuve_nom_complet.'" WHERE `get_epreuve_nom_complet` ="'.$get_epreuve_nom_complet.'"') ; 
        $apple->execution() ;
    
    
    

  ?>


 