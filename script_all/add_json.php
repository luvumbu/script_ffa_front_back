<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
 
echo $_SESSION["start"] ; 
 
/*
$alphabet = array(
    "a",
    "b",
    "c",
    "d",
    "e",

    "f",
    "g",
    "h",
    "i",
    "j",

    "k",
    "l",
    "m",
    "n",
    "o",

    "p",
    "q",
    "r",
    "s",
    "t",

    "u",
    "w",
    "x",
    "y",
    "z"


);


*/

$alphabet = array(
    "v",
 
    "z"


);


$recherche_nom = array(
    "get_result_users_nom_1_array_2",
    "get_result_users_nom_2_array_2",
    "get_result_users_nom_3_array_2",
    "get_result_users_nom_4_array_2",

    "get_users_nationality_array_2",

    "get_club_nom_complet_array_2",
    "get_club_departement_array_2",
    "get_club_region_array_2",
    "get_cat_array_2",
   
    "get_result_date_perf_array_2",
    "get_result_villes_nom_array_2",
    "epreuve_sex_array_2",
    "get_users_nom_complet_array"
 



);


 


$lettre = $alphabet[$_SESSION["start"]];


$recherche_nom = $recherche_nom[$_SESSION["start2"]];


echo  $lettre ; 
include("Select_datas.php") ;  
include("Insertion_Bdd.php") ; 
 
 
// Array with names
$a[] = "";




$nom_file = $recherche_nom."/".$lettre.".php";


 
  
 

if (file_exists($recherche_nom)) {
    echo "Le fichier $recherche_nom existe.";

} else {
    echo "Le fichier $recherche_nom n'existe pas.";

     mkdir($recherche_nom);
}
 


 

 

$texte = "<?php";
$texte = $texte."\n";
$texte = $texte.'include("../debut.php") ;';
$texte = $texte."\n";
  
 
$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

    "$recherche_nom"

    );
 
   // $apple->sql ='SELECT *  FROM client  WHERE `get_result_users_nom_1_array_2` LIKE "'.$q.'%"' ;
    $apple->sql='SELECT  DISTINCT(`'.$recherche_nom.'`) FROM `info_all_array` WHERE `'.$recherche_nom.'` LIKE "'.$lettre.'%" ORDER BY `'.$recherche_nom.'`  ';
    $apple->execution();
    $myJSON = json_encode($apple->list_row);     
 
 

for($i = 0 ; $i<count($apple->list_row) ; $i ++){

 echo  $apple->list_row[$i] ; 
 echo "<br/>" ; 
    //


    $texte = $texte.'$a[] = "'.$apple->list_row[$i].'";'."\n";

    

 
      
   
   


    // 

}



$texte = $texte.'include("../fin.php") ;';
$texte = $texte."\n"." ?>";




 
?>


<?php
/*---------------------------------------------------------------*/
/*
    Titre : Créer un nouveau fichier                                                                                     
                                                                                                                          
    URL   : https://phpsources.net/code_s.php?id=337
    Date édition     : 05 Fév 2008                                                                                        
    Date mise à jour : 22 Sept 2019                                                                                      
    Rapport de la maj:                                                                                                    
    - fonctionnement du code vérifié                                                                                    
*/
/*---------------------------------------------------------------*/



    // création du fichier
    $f = fopen($nom_file, "w+");
    // écriture
    fputs($f, $texte );
    // fermeture
    fclose($f);



  



    if($lettre!="z"){
        $_SESSION["start"]  ++ ; 
?>

<meta http-equiv="refresh" content="2">


<?php 
    }
    else {

       



        if($recherche_nom=="get_users_nom_complet_array"){
            echo "FIN DU PROGRAMME" ; 

        }
        else {
            $_SESSION["start2"] ++ ; 

            ?>
            <script>
                window.location.href = "start2.php";
            </script>
            
            <?php 
        }

 
    }
?>


