<?php
class Insertion_Bdd {

    public $servername ; 
    public $username ; 
    public $password ; 
    public $dbname ; 
    public $sql ="0"; 

  function __construct(
    $servername,
    $username,
    $password,
    $dbname
    ) {
    $this->servername = $servername;
    $this->username = $username;
    $this->password = $password;
    $this->dbname = $dbname;
  }
  function get_servername() {
    return $this->servername;
  }
  function get_username() {
    return $this->username;
  }
  function get_password() {
    return $this->password;
  }
  function get_dbname() {
    return $this->dbname;
  }

  function set_sql($sql){
    $this->sql = $sql ; 
  }
  function set_msg_valudation($msg_valudation){
    $this->msg_valudation = $msg_valudation ; 
  }

  function execution(){
 
                            // Create connection
                $conn = new mysqli($this->servername, $this->username, $this->password, $this->dbname);
                // Check connection
                if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
                }
  
  


                if($this->sql!="0"){
                  if ($conn->query($this->sql) === TRUE) {
                    echo  $this->msg_valudation ; 
                    } else {
                    echo "Error: " . $this->sql . "<br>" . $conn->error;
                    }
                }
                else {
                  echo "?" ; 
                }



                $conn->close();
                }
}



 ?>


<?php 
// exemple de code foctionnel 
/*
session_start() ; 
header("Access-Control-Allow-Origin: *");
include("link.php") ; 
 
      $t = time(date_default_timezone_set('Europe/Paris')) ; 
      $tiempo = date("Y-m-d",$t);



      $_SESSION["add_liste_projet"] = $t ; 
     $information_user_info = $_SESSION["information_user_id_sha1"] ; 
 
      
      $apple = new Insertion_Bdd(
        $servername,
        $username,
        $password,
        $dbname
        
        );
            
       
        $apple->set_msg_valudation("inserttion ok ") ;  
        $apple->set_sql("INSERT INTO liste_projet_admin (liste_projet_admin_id_sha1,information_user_id_sha1,liste_projet_admin_new_file)
                
        VALUES ('$t','$information_user_info','$tiempo')") ; 
        $apple->execution() ;




        $apple = new Insertion_Bdd(
          $servername,
          $username,
          $password,
          $dbname
          
          );
              
         
          $apple->set_msg_valudation("inserttion ok ") ;  
          $apple->set_sql("INSERT INTO liste_projet (liste_projet_id_sha1,information_user_id_sha1,liste_projet_new_file)
                  
          VALUES ('$t','$information_user_info','$tiempo')") ; 
          $apple->execution() ;


          */
        
?>
 