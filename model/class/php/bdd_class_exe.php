<?php
class Bdd_class {
  // Properties 
public $servername ; 
public $username ; 
public $password ; 
public $dbname ;
public $boolean_connection ;
// variable de construction
  function __construct(
  $servername,
  $username,
  $password,
  $dbname) {
    $this->servername = $servername;
    $this->username = $username;
    $this->password = $password;
    $this->dbname = $dbname;
    $this->exe_resul = false;
  }

function exe(){ // Si la connexion est ok return true si non false
    try {
        $this->conn = new PDO("mysql:host=$this->servername;dbname=$this->dbname", $this->username, $this->password);
        // set the PDO error mode to exception
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->conn = new mysqli($this->servername, $this->username, $this->password, $this->dbname);
        $this->exe_resul = true;
        return true  ; 
      } catch(PDOException $e) {
       // echo "Connection failed: " . $e->getMessage();
       $this->exe_resul = false;
        return false; 
      }
      
}
function set_action($sql){
if($this->exe()){
// Create connection
$conn = new mysqli($this->servername, $this->username, $this->password, $this->dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
// sql to create table
 $conn->query($sql) ;
 $conn->close(); 

}
}
    function get_exe_resul(){
        return $this->exe_resul ; 
    }
}  
// exemple d'execution
 /* 
 $execution_formulaire_php = new Bdd_class(
  $servername,
  $username,
  $password,
  $dbname);

$execution_formulaire_php->set_action ("CREATE TABLE showCoords (
    id_showCoords	 INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    adresse_ip VARCHAR(30) NOT NULL,
    click VARCHAR(30) NOT NULL,
    x_position VARCHAR(30) NOT NULL,

    width_taille_page VARCHAR(30) NOT NULL,
    height_taille_page VARCHAR(30) NOT NULL,

    y_position VARCHAR(50),
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");  
    */
?>