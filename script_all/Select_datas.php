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

 

    
 

    for($i = 0 ; $i<count($this->list_row) ; $i++){
     
      array_push($a,$this->list_row[$i]);
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
// CODE exeMPLe 
/*
 session_start() ; 
 header("Access-Control-Allow-Origin: *");

include("link.php") ;  


$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

    'liste_projet_id',
    'liste_projet_id_sha1',
    'liste_projet_id_parent',
    'liste_projet_id_sha1_general',
    'liste_projet_ip',
    'liste_projet_img',
    'liste_projet_name',
    'liste_projet_description1',
    'liste_projet_description2',
    'liste_projet_visibilite1',
    'liste_projet_visibilite2',
    'liste_projet_type',
    'information_user_id_sha1',
    'liste_projet_new_file',
    'liste_projet_reg_date'
    

    );
 
    $id_information_user_sha1 =$_SESSION["information_user_id_sha1"] ; 
    $apple->sql='SELECT * FROM `liste_projet` WHERE `information_user_id_sha1`="'.$id_information_user_sha1.'"ORDER BY `liste_projet_id` ';
    $apple->execution();
    $myJSON = json_encode($apple->list_row); 

    // echo   $myJSON ; 
 
    $apple->all_data_json() ; 
    */
 ?>


<?php 


// exemple de code n° 2 

/*
 session_start() ; 
 header("Access-Control-Allow-Origin: *");

include("link.php") ;  



$liste_projet_img = $_SESSION["liste_projet_img"] ;
$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

    'liste_projet_id',
    'liste_projet_id_sha1',
    'liste_projet_ip',
    'liste_projet_name',
    'liste_projet_img',
    'liste_projet_description1',
    'liste_projet_description2',
    'liste_projet_visibilite1',
    'liste_projet_visibilite2',
    'liste_projet_type',
    'information_user_id_sha1',
    'liste_projet_new_file',
    'liste_projet_reg_date'

    );
 
    $id_information_user_sha1 =$_SESSION["information_user_id_sha1"] ; 
    $apple->sql='SELECT * FROM `liste_projet` WHERE  `liste_projet_id_sha1`="'.$liste_projet_img.'"';
    $apple->execution();
    $myJSON = json_encode($apple->list_row);     

    // echo   $myJSON ; 
 
   
    



    $apple2 = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple2->row,

    'liste_projet_id',
    'liste_projet_id_sha1',
    'liste_projet_ip',
    'liste_projet_img',
    'liste_projet_name',
    'liste_projet_description1',
    'liste_projet_description2',
    'liste_projet_visibilite1',
    'liste_projet_visibilite2',
    'liste_projet_type',
    'information_user_id_sha1',
    'liste_projet_new_file',
    'liste_projet_reg_date'

    );
 
    $id_information_user_sha1 =$_SESSION["information_user_id_sha1"] ; 
    $apple2->sql='SELECT * FROM `liste_projet` WHERE  `liste_projet_id_parent`="'.$liste_projet_img.'"';
    $apple2->execution();
    $myJSON = json_encode($apple2->list_row);     

    // echo   $myJSON ; 
 
echo "[";
    $apple->all_data_json() ;
    echo "," ; 
    $apple2->all_data_json() ;

    echo "]";






*/


    
 ?>

 
  
 
 
  
 