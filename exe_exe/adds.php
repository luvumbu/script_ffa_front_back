

<?php
 
 
include("connexion.php") ; 


// connection a la basse de donne appartir d'un serveur distant 
// ce fichier doit etre a l'emplacement la ou ont voudrais copier nos fochiers 
 
 

$get_emplacement = $_POST["get_emplacement"] ; 
 
class Fruit {
  public $name;
  public $my_array = array();
  public $my_array_x = array();
  public $info ="" ; 
  public $my_string ="";

 
  
  function get_my_array() {
    return $this->my_array;
  }
  function set_my_array($val1ue) {
    array_push($this->my_array,$val1ue);
    
  }
  function set_replace_element($recherche,$replacement){
    $this->my_array= str_replace($recherche,$replacement,$this->my_array);
  }
  function replace_all(){
          for($i=0;$i<count($this->my_array_x);$i++){
            $this->my_array_x[$i] =  str_replace("'","",$this->my_array_x[$i]);
          }
  }

  function set_my_string($val1){
    $this->my_string= $val1;
  }
  function get_my_string(){
    return  $this->my_string;
   }
  function recherche_dans_string(){

    for($i=0;$i<strlen($this->my_string);$i++){


        if($this->my_string[$i]==","){
    
         
            array_push($this->my_array_x,$this->info);
            $this->info = "" ; 
        }
        else {
          // echo $this->my_array[$i] ; 
            $this->info = $this->info.$this->my_string[$i];
        }
    
    
       
    
      
    }
    array_push($this->my_array_x,$this->info);
  }
}


/*

echo $_POST["get_result_users_nom_1_array_2"][0] ; 
echo $_POST["get_result_users_nom_2_array_2"][0] ; 
echo $_POST["get_result_users_nom_3_array_2"][0] ; 
echo $_POST["get_result_users_nom_4_array_2"][0] ; 
echo $_POST["get_users_nationality_array_2"][0] ; 

echo $_POST["epreuve_sex_array_2"][0] ; 
echo $_POST["get_club_nom_complet_array_2"][0] ; 
echo $_POST["get_club_departement_array_2"][0] ; 
echo $_POST["get_club_region_array_2"][0] ; 
echo $_POST["get_cat_array_2"][0] ; 

echo $_POST["get_users_naissance_array_2"][0] ; 
echo $_POST["get_result_date_perf_array_2"][0] ; 
echo $_POST["get_result_villes_nom_array_2"][0] ; 
echo $_POST["get_rp_array_2"][0] ; 
echo $_POST["get_vent_array_2"][0] ; 


echo $_POST["get_result_users_perf_array_2"][0] ; 
echo $_POST["get_result_users_perf_array"][0] ; 
echo $_POST["get_users_nom_complet_array"][0] ; 
echo $_POST["get_club_nom_complet_array"][0] ; 
echo $_POST["get_club_departement_array"][0] ; 


echo $_POST["get_club_region_array"][0] ; 
echo $_POST["get_cat_array"][0] ; 
echo $_POST["get_users_naissance_array"][0] ; 
echo $_POST["get_result_date_perf_array"][0] ; 
echo $_POST["get_result_villes_nom_array"][0] ; 
  */
 
// get_epreuve_nom_complet

// get_result_users_nom_1_array_2



$get_result_villes_nom_array_2 = $_POST["get_result_villes_nom_array_2"];
$get_result_villes_nom_array_2_xx = new Fruit();
$get_result_villes_nom_array_2_xx->set_my_string($get_result_villes_nom_array_2) ; 
$get_result_villes_nom_array_2_xx->set_replace_element("'","") ; 
 
$get_result_villes_nom_array_2_xx->recherche_dans_string() ;
$get_result_villes_nom_array_2_xx->replace_all() ;

$val0 =$get_result_villes_nom_array_2_xx->my_array_x[0];







$get_result_users_nom_1_array_2 = $_POST["get_result_users_nom_1_array_2"];
$get_result_users_nom_1_array_2_xx = new Fruit();
$get_result_users_nom_1_array_2_xx->set_my_string($get_result_users_nom_1_array_2) ; 
$get_result_users_nom_1_array_2_xx->set_replace_element("'","") ; 
 
$get_result_users_nom_1_array_2_xx->recherche_dans_string() ;
$get_result_users_nom_1_array_2_xx->replace_all() ;

$val1 =$get_result_users_nom_1_array_2_xx->my_array_x[0];

























$get_result_users_nom_2_array_2 = $_POST["get_result_users_nom_2_array_2"];
$get_result_users_nom_2_array_2_xx = new Fruit();
$get_result_users_nom_2_array_2_xx->set_my_string($get_result_users_nom_2_array_2) ; 
$get_result_users_nom_2_array_2_xx->set_replace_element("'","") ; 
 
$get_result_users_nom_2_array_2_xx->recherche_dans_string() ;
$get_result_users_nom_2_array_2_xx->replace_all() ;

$val2 =$get_result_users_nom_2_array_2_xx->my_array_x[0];


$get_result_users_nom_3_array_2 = $_POST["get_result_users_nom_3_array_2"];
$get_result_users_nom_3_array_2_xx = new Fruit();
$get_result_users_nom_3_array_2_xx->set_my_string($get_result_users_nom_3_array_2) ; 
$get_result_users_nom_3_array_2_xx->set_replace_element("'","") ; 
 
$get_result_users_nom_3_array_2_xx->recherche_dans_string() ;
$get_result_users_nom_3_array_2_xx->replace_all() ;

$val3 =$get_result_users_nom_3_array_2_xx->my_array_x[0];



$get_result_users_nom_4_array_2 = $_POST["get_result_users_nom_4_array_2"];
$get_result_users_nom_4_array_2_xx = new Fruit();
$get_result_users_nom_4_array_2_xx->set_my_string($get_result_users_nom_4_array_2) ; 
$get_result_users_nom_4_array_2_xx->set_replace_element("'","") ; 
 
$get_result_users_nom_4_array_2_xx->recherche_dans_string() ;
$get_result_users_nom_4_array_2_xx->replace_all() ;

$val4 =$get_result_users_nom_4_array_2_xx->my_array_x[0];





$get_rp_array_2 = $_POST["get_rp_array_2"];
$get_rp_array_2_xx = new Fruit();
$get_rp_array_2_xx->set_my_string($get_rp_array_2) ; 
$get_rp_array_2_xx->set_replace_element("'","") ; 
 
$get_rp_array_2_xx->recherche_dans_string() ;
$get_rp_array_2_xx->replace_all() ;

$val5 =$get_rp_array_2_xx->my_array_x[0];





$get_vent_array_2 = $_POST["get_vent_array_2"];
$get_vent_array_2_xx = new Fruit();
$get_vent_array_2_xx->set_my_string($get_vent_array_2) ; 
$get_vent_array_2_xx->set_replace_element("'","") ; 
 
$get_vent_array_2_xx->recherche_dans_string() ;
$get_vent_array_2_xx->replace_all() ;

$val6 =$get_vent_array_2_xx->my_array_x[0];





$get_users_nationality_array_2 = $_POST["get_users_nationality_array_2"];
$get_users_nationality_array_2_xx = new Fruit();
$get_users_nationality_array_2_xx->set_my_string($get_users_nationality_array_2) ; 
$get_users_nationality_array_2_xx->set_replace_element("'","") ; 
 
$get_users_nationality_array_2_xx->recherche_dans_string() ;
$get_users_nationality_array_2_xx->replace_all() ;

$val7 =$get_users_nationality_array_2_xx->my_array_x[0];




$get_result_users_perf_array_2 = $_POST["get_result_users_perf_array_2"];
$get_result_users_perf_array_2_xx = new Fruit();
$get_result_users_perf_array_2_xx->set_my_string($get_result_users_perf_array_2) ; 
$get_result_users_perf_array_2_xx->set_replace_element("'","") ; 
 
$get_result_users_perf_array_2_xx->recherche_dans_string() ;
$get_result_users_perf_array_2_xx->replace_all() ;

$val8 =$get_result_users_perf_array_2_xx->my_array_x[0];


$get_club_nom_complet_array_2 = $_POST["get_club_nom_complet_array_2"];
$get_club_nom_complet_array_2_xx = new Fruit();
$get_club_nom_complet_array_2_xx->set_my_string($get_club_nom_complet_array_2) ; 
$get_club_nom_complet_array_2_xx->set_replace_element("'","") ; 
 
$get_club_nom_complet_array_2_xx->recherche_dans_string() ;
$get_club_nom_complet_array_2_xx->replace_all() ;

$val8 =$get_club_nom_complet_array_2_xx->my_array_x[0];





$get_club_departement_array_2 = $_POST["get_club_departement_array_2"];
$get_club_departement_array_2_xx = new Fruit();
$get_club_departement_array_2_xx->set_my_string($get_club_departement_array_2) ; 
$get_club_departement_array_2_xx->set_replace_element("'","") ; 
 
$get_club_departement_array_2_xx->recherche_dans_string() ;
$get_club_departement_array_2_xx->replace_all() ;

$val9 =$get_club_departement_array_2_xx->my_array_x[0];



$get_club_departement_array_2 = $_POST["get_club_departement_array_2"];
$get_club_departement_array_2_xx = new Fruit();
$get_club_departement_array_2_xx->set_my_string($get_club_departement_array_2) ; 
$get_club_departement_array_2_xx->set_replace_element("'","") ; 
 
$get_club_departement_array_2_xx->recherche_dans_string() ;
$get_club_departement_array_2_xx->replace_all() ;

$val10 =$get_club_departement_array_2_xx->my_array_x[0];





$get_club_region_array_2 = $_POST["get_club_region_array_2"];
$get_club_region_array_2_xx = new Fruit();
$get_club_region_array_2_xx->set_my_string($get_club_region_array_2) ; 
$get_club_region_array_2_xx->set_replace_element("'","") ; 
 
$get_club_region_array_2_xx->recherche_dans_string() ;
$get_club_region_array_2_xx->replace_all() ;

$val11 =$get_club_region_array_2_xx->my_array_x[0];

 
 


$get_cat_array_2 = $_POST["get_cat_array_2"];
$get_cat_array_2_xx = new Fruit();
$get_cat_array_2_xx->set_my_string($get_cat_array_2) ; 
$get_cat_array_2_xx->set_replace_element("'","") ; 
 
$get_cat_array_2_xx->recherche_dans_string() ;
$get_cat_array_2_xx->replace_all() ;

$val12 =$get_cat_array_2_xx->my_array_x[0];





$get_users_naissance_array_2 = $_POST["get_users_naissance_array_2"];
$get_users_naissance_array_2_xx = new Fruit();
$get_users_naissance_array_2_xx->set_my_string($get_users_naissance_array_2) ; 
$get_users_naissance_array_2_xx->set_replace_element("'","") ; 
 
$get_users_naissance_array_2_xx->recherche_dans_string() ;
$get_users_naissance_array_2_xx->replace_all() ;

$val13 =$get_users_naissance_array_2_xx->my_array_x[0];
 






$get_result_date_perf_array_2 = $_POST["get_result_date_perf_array_2"];
$get_result_date_perf_array_2_xx = new Fruit();
$get_result_date_perf_array_2_xx->set_my_string($get_result_date_perf_array_2) ; 
$get_result_date_perf_array_2_xx->set_replace_element("'","") ; 
 
$get_result_date_perf_array_2_xx->recherche_dans_string() ;
$get_result_date_perf_array_2_xx->replace_all() ;

$val14 =$get_result_date_perf_array_2_xx->my_array_x[0];






 
$epreuve_sex_array_2 = $_POST["epreuve_sex_array_2"] ; 



/*

$get_result_date_perf_array_2 = $_POST["get_result_date_perf_array_2"];
$get_result_date_perf_array_2_xx = new Fruit();
$get_result_date_perf_array_2_xx->set_my_string($get_result_date_perf_array_2) ; 
$get_result_date_perf_array_2_xx->set_replace_element("'","") ; 
 
$get_result_date_perf_array_2_xx->recherche_dans_string() ;
$get_result_date_perf_array_2_xx->replace_all() ;

$val14 =$get_result_date_perf_array_2_xx->my_array_x[0];


*/ 




$window_location_href =$_POST["window_location_href"] ; 
$get_epreuve_nom_complet = $_POST["get_epreuve_nom_complet"] ; 


echo $get_epreuve_nom_complet ; 


  $window_location_href ; 


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}


$sql = "INSERT INTO info_all_array (
  get_result_users_nom_1_array_2)
VALUES (
   ''

  );";


 



for($i=1;$i<count($get_result_users_nom_1_array_2_xx->my_array_x);$i++){
  

  $val1 = $get_result_users_nom_1_array_2_xx->my_array_x[$i] ;
  $val2 = $get_result_users_nom_2_array_2_xx->my_array_x[$i] ;
  $val3 = $get_result_users_nom_3_array_2_xx->my_array_x[$i] ;
  $val4 = $get_result_users_nom_4_array_2_xx->my_array_x[$i] ;
  $val5 = $get_rp_array_2_xx->my_array_x[$i];
  $val6 =$get_vent_array_2_xx->my_array_x[$i];
  $val7 =$get_users_nationality_array_2_xx->my_array_x[$i];
  $val8 =$get_result_users_perf_array_2_xx->my_array_x[$i];
  $val9 =$get_club_nom_complet_array_2_xx->my_array_x[$i];
  $val10 =$get_club_departement_array_2_xx->my_array_x[$i];
  $val11 =$get_club_region_array_2_xx->my_array_x[$i];
  $val12 =$get_cat_array_2_xx->my_array_x[$i];
  $val13 =$get_users_naissance_array_2_xx->my_array_x[$i];

  $val14 = $get_result_date_perf_array_2_xx->my_array_x[$i];
  $val0 =$get_result_villes_nom_array_2_xx->my_array_x[$i];
  



  

  
  $get_users_nom_complet_array =$val1.' '.$val2.' '.$val3.' '.$val4.' ';

  
  



  $sql .= "INSERT INTO info_all_array (
    get_result_users_nom_1_array_2,
    get_result_users_nom_2_array_2,
    get_result_users_nom_3_array_2,
    get_result_users_nom_4_array_2,
    get_rp_array_2,
    get_vent_array_2,
    get_users_nationality_array_2,
    get_result_users_perf_array_2,
    get_club_nom_complet_array_2,
    get_club_departement_array_2,
    get_club_region_array_2,
    get_cat_array_2,
    get_users_naissance_array_2,
    window_location_href,
    get_epreuve_nom_complet,
    get_result_date_perf_array_2,
    get_result_villes_nom_array_2,
    epreuve_sex_array_2,
    get_emplacement,
 
    get_users_nom_complet_array 
    )
  VALUES (
    '$val1'
    ,'$val2'
    ,'$val3'
    ,'$val4'
    ,'$val5'
    ,'$val6'
    ,'$val7'
    ,'$val8'
    ,'$val9'
    ,'$val10'
    ,'$val11'
    ,'$val12'
    ,'$val13'
    ,'$window_location_href2'
    ,'$get_epreuve_nom_complet'
    ,'$val14'
    ,'$val0'
    ,'$epreuve_sex_array_2',
    '$get_emplacement',
 
    '$get_users_nom_complet_array'
    );";
  
  
  
  
  
}


if ($conn->multi_query($sql) === TRUE) {
  echo "New records created successfully";
} else {
  echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();





 
 

 
 
?>
