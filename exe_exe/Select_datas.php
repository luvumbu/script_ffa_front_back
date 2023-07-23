<?php

 include("connexion.php") ; 

 class Select_datas {
  public $servername;
  public $username;
  public $password;
  public $dbname;
  public $sql;
  public $verif_info= 0;
  public $row = array();
  public $list_row =array();

  function __construct($servername,$username,$password,$dbname) {
    $this->servername = $servername;
    $this->username = $username;
    $this->password = $password;
    $this->dbname = $dbname; 
  }

  function execution(){

      $conn = new mysqli($this->servername, $this->username, $this->password, $this->dbname);
      // Check connection
      if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
      }
      
      $sql = $this->sql;
      $result = $conn->query($sql);
      
      if ($result->num_rows > 0) {
        // output data of each row
        while($row = $result->fetch_assoc()) {
      
        //  echo "id: " . $row["id_liste_projet"];


        foreach ($this->row as $value) {
     

          array_push($this->list_row,$row[$value]);
          $this->verif_info = "1" ; 
        }
    
      
        }
      } else {
        
      }
      $conn->close();
  }
 
  function all_data_json(){


    $a=array();

    $a1=array();
 


 

    
 
 



    for($i = 0 ; $i<count($this->list_row) ; $i++){
     
      array_push($a1,str_replace("'","",$this->list_row[$i]));
    }


    for($i = 0 ; $i<count($this->list_row) ; $i++){
     
      array_push($a,str_replace('"',"",$a1[$i]));
    }
    





 
if(count($this->list_row)>0){



 



     echo "[" ; 
     echo "{" ; 
    for($i = 0 ; $i<count($a) ; $i++){
      echo '"'.$this->row[fmod($i, count($this->row))].'"' ; 
      echo ':';
      echo '"'.$a[$i].'"'  ; 
      if($i!=count($a)-1){
        echo "," ;
          }
    
    if(fmod($i, count($this->row))==count($this->row)-1)
    {
    
    if(count($this->row)!=$i){
        if($i==count($a)-1){
     echo "}" ;
       }
       else {
     
      echo '"":""';
     echo "}" ;
     echo ",";
     echo "{" ; 
    
       }
    }
   }
    } 
    echo "]" ; 


    
  }
  else {
    echo '["404"]' ; 
  }
}

}




 ?>


<?php  
/*

$servername = "" ; 
$username = "" ; 
$password = "" ; 
$dbname = "" ; 

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
 
   
    $apple->sql='SELECT * FROM `info_all_array` WHERE 1 LIMIT 50';
    $apple->execution();
    $myJSON = json_encode($apple->list_row); 

    // 1 une seul valeur // echo   $myJSON ; 
 
    // valeur complette s$apple->all_data_json() ; 
*/

    ?>