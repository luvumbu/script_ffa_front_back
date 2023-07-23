<?php 
header("Access-Control-Allow-Origin: *");
$getFullYear_verif = $_POST["getFullYear_verif"];

echo "Bonjour" ; 
 include("Select_datas.php");


 

 
$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

 
    'window_location_href' 
  

    

    );
 
   
    $apple->sql='SELECT * FROM `info_all_array` WHERE 1 ';
    $apple->execution();
    $myJSON = json_encode($apple->list_row); 



 
    if (in_array("https://bases.athle.fr/asp.net/liste.aspx?frmpostback=true&frmbase=bilans&frmmode=1&frmespace=0&frmannee=2021&frmathlerama=&frmfcompetition=&frmfepreuve=&frmepreuve=110&frmplaces=&frmnationalite=&frmamini=&frmamaxi=&frmsexe=M&frmcategorie=&frmvent=VR&frmposition=5",$apple->list_row))
    {
    echo "Match found";
          
          
                if($getFullYear_verif==1){
          include("execution_final.php") ; 
      }
      else {
          echo "Pas dexecution pour le moment ; " ; 
      }
    }
    else {
      echo "pas de valeur trouve" ; 
            if($getFullYear_verif==1){
          
      }
      

    }
    // 1 une seul valeur // echo   $myJSON ; 
 
    // valeur complette s$apple->all_data_json() ; 
 
    
    
  
?>


 

 