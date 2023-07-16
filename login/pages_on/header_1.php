 
<div id="information_user_id_sha1" title="<?php echo $_SESSION["information_user_id_sha1"] ; ?>"></div>
 <div class="display_flex color_001"> 
    <div class="padding_10">             
                 
           <b id="titre_page3">LUVUMBU</b>
             
    </div>
    <div class="padding_10 color_white_2"><div class="color_white">
        <div>
                <img class="cursor_pointer" src="https://img.icons8.com/office/25/null/plus--v1.png"  onclick="action_etape_01()"/>
        </div>
        <div>
                <img class="cursor_pointer" src="https://img.icons8.com/ios/25/null/logout-rounded--v1.png" onclick="session_destroy()"/>
        </div>
        <div class="une_lettre">
        <?php 
if(!$_SESSION["information_user_id_sha1"]==""){
   // echo  strtoupper($_SESSION["information_user_login"][0]) ;
     
     
 if(isset($_SESSION["information_user_login"][0])){
   echo $_SESSION["information_user_login"][0] ; 
 }
 else{
     echo ":)";
 }
 
}
?>
</div>
</div>
    </div> 
</div>
<div title="<?php echo $_SESSION["information_user_id_sha1"] ?>" id="information_user_id_sha1">
</div>
<div id="login"></div>

<?php 
/*
include("etape_01.php"); 
include("etape_02.php"); 
include("pages_on/001.php"); 
*/
//include("etape_04.php"); 
?>
 <!-- 
   header_2_add_blog
   fonction dans le fichier  
-->
<div class="options_style">
    <div onclick="add_liste_projet_exe(this)" id="header_2_add_blogs" class="cursor_pointer">
        
        <div  class="cursor_pointer" >
        
            <img  src="https://img.icons8.com/external-flatart-icons-flat-flatarticons/50/null/external-blog-web-flatart-icons-flat-flatarticons.png"/>
        </div>
            <div>
                Créer un Blog
            </div> 
    </div>
<!--
    <div  id="header_2_add_blogs" class="cursor_pointer">
        
        <div  class="cursor_pointer" >
        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAACj0lEQVR4nO2a309SYRjH+e9s68JN7bYLb1pqtdRDulbdYM0u2rzICNYGgXVVbZSrcQQRzLlEpMNPEfDHCYlOHnDDA5jybefFcMy4AC3f151n+453z969ez7neZ/3OZy9Op1mDNmctbNj/vUVIw2as3Z2tA2iLpBPPIEiGltScWMS3qlueG1d8Nq7EHjfi+LmZMvrKEdSY1BjORWIuhBkW8sS/few5b+PZUcvws7riPL9ED5dQ/WnteW1FNF4viDi8gOs+Yax9O4qwnwfeyCHkgW+Vz3w2bvJ1kovjkIRn6GcMbX1UJTzzEhNLxF13YDTfImIf3EZB5KFwYxM9ZDfWrE/ZTMjolbsNnoyohW7TFmNaMUuU3pqaZ1dpigjWmeXKawR7TVepiQjWmeXKesjF6azV7ImrEz3ITDdTxSZGULUzWHVO4LKtpkdkIhrEEVpEdjfaJDqi7iG2AGJzXKQ0h+gyP4Gqb6Yh2MHJCuMwe8YgH/6ZqMcA8gKD9kBCTlvo1pZP7G1sL+OED/IEAjP/QWiJoEfZgOk9M2IxMLjpiCJhXGUMs/pB/keGkMu+bYpSG7tDXKhR/SDJD/fRamw0hSklA+QOdSDRFyN9bG38wXKzlKDL+LmWAC5Uw9486sJCe8I0ZZgPgZR59AOEnaP1gMOzxw/eTL+41fn0A4S4vX1gOPzBkixcaK4z1D3k+OZdpCYh0N5N0gCrpZTyETsROpY9ZULQcRm9fSDKKIRAq/HYSl54sRSfQLPkV5DPQhkGwrJCQQ/3oKUduDX3irRj5SD+HZTE2x9fDiQLNgOGhDz6BH36MlY/cPF6Hct25lI0UCOTMuIrG0t/LMaaecKx1krf9orHBfmUo1muv9vvwEz4OA4JecEUgAAAABJRU5ErkJggg==">
        </div>
            <div>
                Mes archives
            </div> 
    </div>
-->


    <div  class="cursor_pointer" onclick="dowload_img(this)" title="all_picture">
        <div>
            <img src="https://img.icons8.com/pastel-glyph/50/null/image--v1.png"/>

        </div>
            <div>
             Ajouter des images
            </div> 
    </div> 

    <div  class="cursor_pointer" onclick="voir_group(this)" title="all_picture">
        <div>
            <img src="https://img.icons8.com/external-flatart-icons-lineal-color-flatarticons/50/external-document-statistical-analysis-flatart-icons-lineal-color-flatarticons.png" alt="external-document-statistical-analysis-flatart-icons-lineal-color-flatarticons"/>

        </div>
            <div>Voir groupe</div> 
    </div> 

    <div  class="cursor_pointer" onclick="myGreeting()" title="all_picture">
        <div>
            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAACj0lEQVR4nO2a309SYRjH+e9s68JN7bYLb1pqtdRDulbdYM0u2rzICNYGgXVVbZSrcQQRzLlEpMNPEfDHCYlOHnDDA5jybefFcMy4AC3f151n+453z969ez7neZ/3OZy9Op1mDNmctbNj/vUVIw2as3Z2tA2iLpBPPIEiGltScWMS3qlueG1d8Nq7EHjfi+LmZMvrKEdSY1BjORWIuhBkW8sS/few5b+PZUcvws7riPL9ED5dQ/WnteW1FNF4viDi8gOs+Yax9O4qwnwfeyCHkgW+Vz3w2bvJ1kovjkIRn6GcMbX1UJTzzEhNLxF13YDTfImIf3EZB5KFwYxM9ZDfWrE/ZTMjolbsNnoyohW7TFmNaMUuU3pqaZ1dpigjWmeXKawR7TVepiQjWmeXKesjF6azV7ImrEz3ITDdTxSZGULUzWHVO4LKtpkdkIhrEEVpEdjfaJDqi7iG2AGJzXKQ0h+gyP4Gqb6Yh2MHJCuMwe8YgH/6ZqMcA8gKD9kBCTlvo1pZP7G1sL+OED/IEAjP/QWiJoEfZgOk9M2IxMLjpiCJhXGUMs/pB/keGkMu+bYpSG7tDXKhR/SDJD/fRamw0hSklA+QOdSDRFyN9bG38wXKzlKDL+LmWAC5Uw9486sJCe8I0ZZgPgZR59AOEnaP1gMOzxw/eTL+41fn0A4S4vX1gOPzBkixcaK4z1D3k+OZdpCYh0N5N0gCrpZTyETsROpY9ZULQcRm9fSDKKIRAq/HYSl54sRSfQLPkV5DPQhkGwrJCQQ/3oKUduDX3irRj5SD+HZTE2x9fDiQLNgOGhDz6BH36MlY/cPF6Hct25lI0UCOTMuIrG0t/LMaaecKx1krf9orHBfmUo1muv9vvwEz4OA4JecEUgAAAABJRU5ErkJggg=="/>

        </div>
            <div>Voir Projet</div> 
    </div> 




    

    
 
    <div  class="cursor_pointer" onclick="add_group_affiche_form(this)" title="all_picture">
        <div>
            <img src="https://img.icons8.com/dusk/50/document--v1.png" alt="document--v1"/>

        </div>
            <div>
             Créer un groupe
            </div> 
    </div> 

    <?php 

if($_SESSION["information_user_id"]==1){
  
    include("super_user.php");
    }

?>
</div>
 <div id="add_liste_projet_div"></div> 
<div>
<div id="id_div_global_style1"></div>
<div id="info_"></div>
<link rel="stylesheet" href="pages_on/style_1.css">
<script src="pages_on/style_1.js"></script>
<script src="pages_on/plus2.js"></script>




 


<script>
    function mes_archives(_this){
        console.log("OK") ; 
    }
</script>