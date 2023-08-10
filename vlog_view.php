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
 
    //$apple->all_data_json() ; 


   
  ?>


<!DOCTYPE html>
 <html lang="en">
 <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php       echo $name ;     ?></title>
    
 </head>
 <body>
    <link href="https://fonts.googleapis.com/css?family=Anton" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <title>Page par défaut</title>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta content="Page par défaut" name="description">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i,800,800i&amp;subset=cyrillic,cyrillic-ext,greek,greek-ext,latin-ext,vietnamese" rel="stylesheet">
   
	<div class="clas_flex_1 color_1">
		<div class="tr"></div>
		<div class="">
			<i class="fa fa-facebook-square"></i>
			<i class="fa fa-twitter-square"></i>
			<i class="fa fa-linkedin-square"></i>
			<i class="fa fa-instagram"></i>
			<i class="fa fa-google-plus"></i>
		</div>
	</div>
 
 

<div class="largeur_toute_page" >

    <div>
        <h1 class="Inversionz" id="nom_du_clum">
        <?php       echo $name ;     ?>
        </h1>
        <h2 class="Inversionz">
            

            <?php       
            
            for($i = 0 ; $i< strlen($name ) ; $i ++ ){

         echo $name[$i] ; 
              if($name[$i]==" "){
                break ; 
              }

            }
            ?>
        </h2>
    </div>







</div>
<div class="clas_flex_2" style="font-size:2em">
    <div>
      Acceuil
    </div>
    <div>
        Athlètes

    </div>
 
    <div>
        Niveau du club 
    </div>
    <div>
        Pérformence
    </div>
    <div>
        Hactualités
    </div>

</div>




<div class="clas_flex_3">

    <h1>
        Conseils graphiques
    </h1>
    <h2>
        Web design : comment créer le guide de style parfait
    </h2>
    <h3>
        1 année Commenter1 565 Vues4 min de lecture
    </h3>

    <div>
        <img src="https://i-h2.pinimg.com/564x/22/04/40/2204407b1a8fad1695e08916d2df6f94.jpg" alt="" srcset="">
    </div>
</div>

    <style>
        .fa{
            font-size:1.6em ; 
        }
        .clas_flex_1{
            display:flex ; 
            justify-content: space-between;
            padding : 5px; 
        }
        .clas_flex_2{
            border:1px solid #f0f0f0 ; 
            border-left:0 ; 
            border-right:0 ; 


            padding: 5px;
            width:100% ; 
            margin: auto;
            display: flex;
            justify-content: space-around;
            text-align: center;
            padding-left: 0;
            padding-right: 0;


        }
        .clas_flex_2 div{
padding: 7px;           
            width:100% ; 
            background-color: #373737;
            color: #f0f0f0;
            margin: 0;
        }
        .clas_flex_2 div:hover{

            background-color: #f0f0f0;
            color: #373737;
            cursor: pointer;
           
        }
.clas_flex_3{
    border:1px solid rgba(37,37,37,0.4) ; 
    width:60%;
    margin:auto ; 
    text-align: center;
    margin-top: 100px ; 
}

.clas_flex_3 img{
    width:100% ; 
}
        
        .clas_flex_1 i {
            padding : 2px; 
            background-color:white ; 
            margin: 4px; 
            border-radius: 5px;
            transition : 1s all ; 
        }
        .clas_flex_1 i:hover {
            padding : 2px; 
            background-color:rgb(195, 230, 67) ; 
            margin: 4px; 
            border-radius: 5px;
            transition : 1s all ; 
            cursor:pointer ; 
        }
        .color_1{
            background-color : #373737 ; 

        }
        .largeur_toute_page {
            width: 80%;
            margin:auto ; 
        }
        .Inversionz{
  font-family: Inversionz;
  color:#cccccc ;
  font-size:3.3em;

        }



        @font-face {
  font-family: Inversionz;
  src: url(font/Inversionz.ttf);
}
 

.largeur_toute_page h1  {
        color:#373737 ; 
   
  font-family: Inversionz;

        }

    </style>
	<div class="menu3"></div>


    
<script>





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

    console.log( tab_liste.length) ; 

var xmlhttp = new XMLHttpRequest();
xmlhttp.onreadystatechange = function() {
  if (this.readyState == 4 && this.status == 200) {
    var myObj = JSON.parse(this.responseText);

 
    total_info = myObj ; 
 


   
  }
};
xmlhttp.open("GET", "https://bokonzi.com/ffa/vlog.php/[get_club_nom_complet_array_2]/17 Soupapes", true);
xmlhttp.send();






const myTimeout = setTimeout(myGreeting, 5000);

function myGreeting() {
 console.log(total_info) ; 
}
</script>










 </body>
 </html>


 