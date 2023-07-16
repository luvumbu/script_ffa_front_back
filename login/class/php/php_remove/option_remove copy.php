<?php 

 session_start() ; 
 header("Access-Control-Allow-Origin: *");

include("link.php") ;  

$liste_projet_name =  $_POST["liste_projet_name"]  ;
$_SESSION["option_remove_name"] = $liste_projet_name ; 
 

$_SESSION["option_remove_name_x"]=array();


 
 
$t = time(date_default_timezone_set('Europe/Paris')) ; 
$tiempo = date("Y-m-d",$t); 


 
$_SESSION["array_remove_liste_projet"] = $liste_projet_name ;
 
 
 
$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

    'liste_projet_id_sha1'

    );
 
    $apple->sql='SELECT * FROM `liste_projet` WHERE `liste_projet_id_parent` ="'.$liste_projet_name.'"';
    $apple->execution();
    $myJSON = json_encode($apple->list_row); 
    // echo   $myJSON ; 



    $a=array($liste_projet_name);



 for($i = 0 ; $i <count($apple->list_row)  ; $i ++){
   // echo $apple->list_row[$i] ; 
    echo "<br/>" ; 


 $$liste_projet_name = $apple->list_row[$i] ;
    array_push($a,$apple->list_row[$i]);



    $apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

    'liste_projet_id_sha1'

    );
 
    $apple->sql='SELECT * FROM `liste_projet` WHERE `liste_projet_id_parent` ="'.$liste_projet_name.'"';
    $apple->execution();
    $myJSON = json_encode($apple->list_row); 
    // echo   $myJSON ; 

    

 }
 


 for($i=0;$i<count($a) ; $i++){
    echo  $a[$i] ; 


    $liste_projet_cookie = $a[$i] ;
    array_push($_SESSION["option_remove_name_x"],$liste_projet_cookie);


    if($i>0){
        $_SESSION["option_remove_name"] = $_SESSION["option_remove_name"].",".$liste_projet_cookie;

    }
  
    $apple = new Insertion_Bdd(
        $servername,
        $username,
        $password,
        $dbname
        
        );
        $apple->set_msg_valudation("remove ok ") ;  
        $apple->set_sql('UPDATE `liste_projet` SET `liste_projet_remove` = "off" WHERE  `liste_projet_id_sha1` = "'.$liste_projet_cookie.'"') ; 
        $apple->execution() ;


 $t = sha1($t) ;

        $apple = new Insertion_Bdd(
            $servername,
            $username,
            $password,
            $dbname
            
            );
                
           
            $apple->set_msg_valudation("inserttion ok ") ;  

            
            $apple->set_sql("INSERT INTO liste_projet_remove_log (liste_projet_remove_log_id_sha1,liste_projet_remove_log_id_sha1_name)
                    
            VALUES ('$t','$liste_projet_cookie')") ; 
            $apple->execution() ;
 }
 




 



 




 
 

 



 
 
 ?>

 
  
 