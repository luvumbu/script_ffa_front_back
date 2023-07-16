<?php 


$filename_1="../model/class/php/connexion.php";
$filename_2="../model/class/php/Insertion_Bdd.php";
$filename_3="../model/class/php/Select_datas.php";






$filename_4="../model/class/php/config_folder_verif.php";

 
if (!file_exists($filename_1) ) {
 

 
  ?>


  <meta http-equiv="Refresh" content="0; url=../" />
  <?php


} else {
  include($filename_1) ; 
  include($filename_2) ;
  include($filename_3) ; 
}
 



?> 