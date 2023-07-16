<?php
 session_start() ; 
 
 header("Access-Control-Allow-Origin: *");
 include("../../model/class/php/Select_datas.php") ; 
$id_information_user= $_SESSION["id_information_user"] ;
$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,
    "id_liste_projet",
    "id_information_user",
    "type_liste_projet",
    "description_projet",
    "description_projet2",
    "visibilite1",
    "visibilite2",
    "reg_date",
    "src_element"
    );

  $apple->sql='SELECT * FROM `liste_projet` WHERE  `id_information_user` ="'.$id_information_user.'"  ORDER BY  `id_liste_projet`';
  $apple->execution();
  $myJSON = json_encode($apple->list_row); 
 echo $myJSON;
 ?>