<?php
 

$dbname="u489596434_ffa_2x";
$username="u489596434_ffa_2x";
$password="v3p9r3e@59A";
$servername="localhost";

 


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
 


$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

    'id_info_all_array' 
    

    );
 
   
    $apple->sql='SELECT * FROM `info_all_array` WHERE 1';
    $apple->execution();
    $myJSON = json_encode($apple->list_row); 

    // echo   $myJSON ; 
 
    $apple->all_data_json() ; 
    
 ?>

 