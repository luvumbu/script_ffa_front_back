<?php
session_start();
$filename_config = "model/class/php/config_folder_verif.php"; 
// <direct_01>
// Localisation du fichier de configuration utilisé pour vérifier l'état de l'application.
$filename_config2 = "model/class/php/connexion.php"; 
// <direct_02>
// Localisation du fichier de connexion 

// si le fichier ne sont pas présent ont affiche le contenu de configuration qui est situé 
// view/config.php <direct_03>
// si il ya existence de deux fichier et que la connexion est ok 
// la page fais in redirection vers le fichier le dossier login 


/*
// model/class/php/config_folder_verif.php
// model/class/php/connexion.php

c'est deux fichier sont créer uniquement lorsqu'on reussi a se connecter à la basse de donnée /!\
si l'un de deux fichier n'est pas existant la verification ce fait sur le parametre n°1 nom = pr:01
// <pr:01> verification existence de deux fichier </pr:01>
*/

// <pr:01>
if (!file_exists($filename_config) || !file_exists($filename_config2)) {
  if (file_exists($filename_config)) {
    unlink($filename_config);
    // efface le fichier s'il n'existe pas 
    // filename_config <direct_01>
  }
  if (file_exists($filename_config2)) {
    unlink($filename_config2);
    // efface le fichier s'il n'existe pas 
    // filename_config2 <direct_02> 
  }
} else {
  // paramètre de lecture du fichier 
  $ressource = fopen($filename_config, 'rb');
  $fread_ressource = fread($ressource, filesize($filename_config));
  if ($fread_ressource != "off") {
    //config_folder_verif.php
   // header('Location:login/index.php');    
    //header('Location:login/index.php');
    //header("HTTP/1.1 404 Not Found")
    // lors de la création du document si on a coché sur la case on fait une redirection si non on reste sur la même page
    // redirection en foction du parametre selectionne lors de la création de la Bdd 
  }
}
// </pr:01>
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Ndenga Luvumbu application</title>
  <meta charset="UTF-8">
  <link rel="icon" type="image/x-icon" href="src/img/ndenga.gif">
 

</head>
<body>
  <?php
  include("link.html"); // Source totale de style et script    
  /*
        Liste des ressources nécessaires au programme 
        exemple: 
        * Source css
        * Class JS 
        * Class Php 
   */
  ?>
  <div id="body">
    <!--
    @mouseover="position_mouse"    
    Code Vue JS 
    Enregistre la position de la souris lorsque l'utilisateur survole la page web
    Racine vue.js
-->
    <?php
    include("model/class/php/body.php");
    //include("json_active.php");
    // fichier configuration à l'intérieur 
    // contenu général de la page dans class\php\index_body.php 
    /*
    include("model/class/php/Class.php");  
    include("model/class/php/index_body.php");  
    <index_body>
      <header>
      </header>
      <section>
      </section>
      <footer>
      </footer>
    </index_body>
    */
    ?>
 
  </div>
  <!--
  Besoin de vue Js pour savoir le comportement des utilisateurs 
-->
  <?php 
  /*
// la variable est crée sur le fichier 
// model/class/php/Class.php
// et la verification ce fais principalement sur ce fichier 

 
  */
  if ($config_ == false) {

    // si la page de connexion n'existe pas elle affiche le formulaire de connexion 
    // si non elle efface à l'aide du CSS mais le formulaire est toujours présent juste invisible 
 
  } else {
    include("model/class/php/remove_db.php");
    /*
    Execution d'un test s'il n'y a pas d'erreur dans la Bdd 
    S'il y a une erreur il efface le fichier de configuration 
    */
 
  }
  //unlink('model/class/php/config_folder_verif.php');

  ?>
  <!--
  <div id="showcoords_position"></div> 
  <script src="js.js"></script> 
  -->

</body>


<script src="model/class/js/Ajax.js"></script>
<script src="js.js"></script>

<link rel="stylesheet" href="view/pages/general_model.css">
<link rel="stylesheet" href="view/pages/Inscription1.css">
<link rel="stylesheet" href="view/pages/Inscription2.css">
<link rel="stylesheet" href="view/pages/Login.css">
<link rel="stylesheet" href="view/pages/pages.css">
<link rel="stylesheet" href="view/pages/pages_1.css">
<link rel="stylesheet" href="view/pages/recherche_04.css">





</html>

<script>

function showHint(str) {

 
  
 
  if (str.length == 0) {
    document.getElementById("txtHint").innerHTML = "";
    return;
  } else {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        document.getElementById("txtHint").innerHTML = this.responseText;
      }
    }
    xmlhttp.open("GET", resultat_recherche+str[0]+""+".php?q="+str, true);
    xmlhttp.send();
  }
}

function redirection_ok(_this){
  Ajax("optiones_total","http://localhost/script_ffa_front_back/user_blog.php");
//  Ajax("optiones_total","view/pages/"+_this.title+".php");


  

}
 


function onclick_recherche(_this){


  let get_emplacement	 = [""] ; 
	let get_rp_array_2	 = [""] ; 
	let get_vent_array_2	 = [""] ; 
	let get_result_users_perf_array_2	 = [""] ; 
	let get_result_users_nom_1_array_2	 = [""] ; 
	let get_result_users_nom_2_array_2	 = [""] ; 
	let get_result_users_nom_3_array_2	 = [""] ; 
	let get_result_users_nom_4_array_2	 = [""] ; 
	let get_users_nationality_array_2	 = [""] ; 
	let get_club_nom_complet_array_2	 = [""] ; 
	let get_club_departement_array_2	 = [""] ; 
	let get_club_region_array_2	 = [""] ; 
	let get_cat_array_2	 = [""] ; 
	let get_users_naissance_array_2	 = [""] ; 
	let get_result_date_perf_array_2	 = [""] ; 
	let get_result_villes_nom_array_2 = [""] ; 	
	let epreuve_sex_array_2	 = [""] ; 
	let get_users_nom_complet_array	 = [""] ; 
	let info_all_array_click	 = [""] ; 
	let info_all_array_ip	 = [""] ; 
	let info_all_array_src_name	 = [""] ; 
	let window_location_href	 = [""] ; 
	let get_epreuve_nom_complet	 = [""] ; 
	let reg_date = [""] ; 



    tab_liste = [

 	
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
    ]
 ; 


 var total_info  ; 

 var recherche_info="get_users_nom_complet_array" ; 
var recherche_info_element_1_div = "VICAUT    Willy " ; 
var recherche_info_element_2_div = "test" ; 


var list_get_club_nom_complet_array_2 = [] ; 
    console.log( tab_liste.length) ; 
 
 

Ajax("optiones_total","apparence/general_info1.php");
    




  console.log(_this.title);
  console.log("test") ; 
//  alert(onclick_recherche_element) ; 
document.getElementById("optiones_total").innerHTML="";


const myTimeout = setTimeout(myGreeting, 250);

function myGreeting() {
  document.getElementById("id_nom_club__2").innerHTML=_this.title;
 



  var el_ = "" ; 
  var el_bool = false ; 
for(var x = 0 ; x<_this.title.length ; x++){
 if(_this.title[x]==" "){
  el_bool =true ; 
break ; 
 } 
 else {
  el_ = el_+_this.title[x] ; 
 }
}



if(el_bool){
  document.getElementById("id_nom_club__1").innerHTML=el_;

}
else {
  document.getElementById("id_nom_club__1").innerHTML="";

}


 
 console.log(onclick_recherche_element) ; 

switch(onclick_recherche_element) {
  case "get_club_nom_complet_array_2":
    document.getElementById("id_nom_recherche").innerHTML="<strong>Recherche par club</strong>";

    break;
    case "get_users_nom_complet_array":
    document.getElementById("id_nom_recherche").innerHTML="<strong>Recherche par Nom sportive</strong>";

    break;
    case "get_result_villes_nom_array_2":
    document.getElementById("id_nom_recherche").innerHTML="<strong>Recherche par Villes</strong>";

    break;
 
  
 
}

}


//document.getElementById("id_nom_club__1").innerHTML=_this.title;





  var xmlhttp = new XMLHttpRequest();
xmlhttp.onreadystatechange = function() {
  if (this.readyState == 4 && this.status == 200) {
    var myObj = JSON.parse(this.responseText);
 
    var myObj = JSON.parse(this.responseText);

 
total_info = myObj ; 

console.log(myObj) ;  


 






/*



for(var i = 0 ; i <myObj.length ; i ++ ) {
  console.log(myObj[i].get_club_nom_complet_array_2) ; 

 


  if( list_get_club_nom_complet_array_2.includes(myObj[i].get_club_nom_complet_array_2)){

  }
  else {
    list_get_club_nom_complet_array_2.push(myObj[i].get_club_nom_complet_array_2);
  }
}


console.log("FIN") ; 
console.log(list_get_club_nom_complet_array_2) ; 

switch(recherche_info) {
case "get_club_nom_complet_array_2":
document.getElementById("recherche_info_div").innerHTML="ReCHeRCHe D'UN cLUB" ; 
break;
case "get_users_nom_complet_array":
document.getElementById("recherche_info_div").innerHTML="ReCHeRCHe par Nom d'athletB" ; 


document.getElementById("recherche_info_element_1_div").innerHTML=onclick_recherche_element ; 


recherche_info_element_2_div = "" ; 
for(var i = 0 ; i<onclick_recherche_element.length ; i ++){

console.log(i) ; 
_this.title = _this.title+onclick_recherche_element[i] ; 
if(onclick_recherche_element[i]==" "){
  break ; 
}
}
document.getElementById("recherche_info_element_2_div").innerHTML=_this.title ; 


break;
default:
// code block
}

*/

  }
};
xmlhttp.open("GET", "https://bokonzi.com/ffa/vlog_limit.php/["+onclick_recherche_element+"]/"+_this.title, true);
xmlhttp.send();


}
</script>

<style>
  #txtHint{


max-height : 500px; 
overflow: scroll;
overflow-x: hidden;

 
  }
</style>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
 
 