<?php 
include("header_1.php") ; 


$_SESSION["add_liste_projet"] = array("");
/*
header_1  : 

Ensemble du  menu principal 
Liste des option dans le menu 

1) Nom de l'application
2) Option qui permet d'ajouter une page web (Inactive)
3) Fonction qui permet à l'utilisateur de se déconnecter
4) Option qui permet d'achicher la premier lettre du NOm de l'utilisateur 

*/
//include("01.php") ; 
//include("etape_04.php") ; 
//include("info_user.php") ; 
    include("img_add.php") ;  

?>
<link rel="stylesheet" href="pages_on/header_1.css">
<script src="class/js/js_add/add_liste_projet_exe.js"></script>
<script src="class/js/js_add/add_liste_projet_exe2.js"></script>

<script src="class/js/js_upload/update_value.js"></script> 

<div id="cookie_table"> </div>
<div id="cookie_table2"></div>
<div id="cookie_table3"></div>




<script>

    let scrollTo_ = 700 ; 
 



 
</script>




 <link rel="stylesheet" href="pages_on/login.css">
<?php 
if($_SESSION["information_user_id_sha1"]==""){
    session_destroy();
?>


<meta http-equiv="refresh" content="0"
<?php
}




include("section.php") ;
?>

 

