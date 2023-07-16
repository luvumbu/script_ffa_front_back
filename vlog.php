<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">


<?php 

include("model/class/php/Select_datas.php") ;  
include("model/class/php/Insertion_Bdd.php") ; 
 
$date_array = array() ; 
$ip_array = array() ; 


$liste_visite_page_id_array = array() ; 
$liste_visite_page_id_sha1_array = array() ; 
$liste_visite_page_ip_array = array() ; 
$liste_visite_page_reg_date_1_array = array() ; 
$liste_visite_page_reg_date_array = array() ; 



$ip_a = $_SERVER['REMOTE_ADDR'] ; 
$t = time(date_default_timezone_set('Europe/Paris')) ; 
$tiempo = date("Y-m-d",$t);

 
$apple = new Select_datas($servername,$username,$password,$dbname);

  array_push(
    $apple->row,

    'liste_visite_page_id',
    'liste_visite_page_id_sha1',    
    'liste_visite_page_ip',    
    'liste_visite_page_reg_date_1',
    'liste_visite_page_reg_date'
 

    );
 
 
    $apple->sql='SELECT * FROM `liste_visite_page` WHERE 1 ';
    $apple->execution();
    $myJSON = json_encode($apple->list_row);     

    // echo   $myJSON ; 



 if(count($apple->list_row)>0){
 





  // Ajout de l'adresse ip 





  
  
  
  

  
  
  


 



    


       for($i= 0 ; $i<count($apple->list_row) ; $i++){
     //   echo $i.'<br/>' ; 


       //echo(fmod($i, 4) . "<br>");


 


       switch (fmod($i, 5)) {
        case 0:
              array_push($liste_visite_page_id_array,$apple->list_row[$i]);
          break;

        case 1:
                array_push($liste_visite_page_id_sha1_array,$apple->list_row[$i]);
          break;

        case 2:
                  array_push($liste_visite_page_ip_array,$apple->list_row[$i]);
          break;
        case 3:
                    array_push($liste_visite_page_reg_date_1_array,$apple->list_row[$i]);
           break;
        case 4:
                      array_push($liste_visite_page_reg_date_array,$apple->list_row[$i]);
           break;
 
      }

 




 


       }
 
  
       
  
  

  // fin ajout adresse ip 
 }

    


 

 




 
 

 
  
 
 

 $name ="" ; 
 $eman ="" ; 




 for($i = 0 ; $i<strlen($_SERVER['PHP_SELF']) ; $i ++){


  if(strrev($_SERVER['PHP_SELF'])[$i]=="/") {
    break ;
  }
  else {
    $eman =  $eman .strrev($_SERVER['PHP_SELF'])[$i] ; 
  }
 }


  



 $url = strrev($eman);



 
  


$eman="";
$name="";



 for($i=strlen($_SERVER['PHP_SELF'])-1; $i>-1;$i--){
    
     if($_SERVER['PHP_SELF'][$i]=='/'){
       break ; 
     }
     $eman = $eman.$_SERVER['PHP_SELF'][$i] ; 
 }

 $eman = $eman;



 for($i=strlen($eman)-1; $i>-1;$i--){
    


 $name = $name.$eman[$i] ;




}








if (!in_array($tiempo, $liste_visite_page_reg_date_1_array) &&  !in_array($ip_a, $liste_visite_page_ip_array) )
{


   $apple = new Insertion_Bdd(
    $servername,
    $username,
    $password,
    $dbname
    
    );
        
   
    $apple->set_msg_valudation("") ;  
    $apple->set_sql("INSERT INTO liste_visite_page (liste_projet_id_sha1_info,liste_visite_page_id_sha1,liste_visite_page_ip,liste_visite_page_reg_date_1)
            
    VALUES ('$name','$t','$ip_a','$tiempo')") ; 
    $apple->execution() ;
}



 



$apple = new Select_datas($servername,$username,$password,$dbname);

 array_push(
   $apple->row,

   'liste_projet_description1',
   'liste_projet_img',
   'liste_projet_description2', 
   'liste_projet_reg_date',
   'liste_projet_visibilite1',
   'liste_projet_id_sha1',
   'liste_projet_id_parent' 
   

   );

 
   $apple->sql='SELECT * FROM `liste_projet` WHERE `liste_projet_id_sha1`="'.$url.'" ';
   $apple->execution();
   $myJSON = json_encode($apple->list_row); 

   // echo   $myJSON ; 











   $apple2 = new Select_datas($servername,$username,$password,$dbname);

 array_push(
   $apple2->row,

   'liste_projet_description1',
   'liste_projet_img',
   'liste_projet_description2', 
   'liste_projet_reg_date',
   'liste_projet_visibilite1',
   'liste_projet_id_sha1' 
   

   );

  
   $apple2->sql='SELECT * FROM `liste_projet` WHERE `liste_projet_id_parent`="'.$name.'"';
   $apple2->execution();
   $myJSON = json_encode($apple2->list_row); 

   // echo   $myJSON ; 




// COUNT PAGE 

$apple3 = new Select_datas($servername,$username,$password,$dbname);

array_push(
  $apple3->row,

  'COUNT("'.$name.'")' 
  

  );

 
  $apple3->sql='SELECT COUNT("'.$name.'") FROM `liste_visite_page` WHERE 1';
  $apple3->execution();
  $myJSON = json_encode($apple3->list_row); 



// FIN COUNT PAGE 

?>
<div class="display_flex">
  <div>
  <img width="30" id="yd_total" height="30" src="https://img.icons8.com/ios/30/visible--v1.png" alt="visible--v1"/>
  </div>
  <div>
  <?php 

echo count($apple3->list_row) ; 
?>
  </div>
</div>

<?php 
 
 


echo '<div class="page_t1">';
 


if($_SERVER['SERVER_NAME']=="localhost"){
  echo '<img src="http://localhost/Model_Vue8/login/src/img/all/qr_code/'.$name.'.png" alt="" srcset="">' ; 

}
else {


  echo '<div>';
  echo '<img src="https://'.$_SERVER['SERVER_NAME'].'/login/src/img/all/qr_code/'.$name.'.png">' ; 
  echo '</div>';
  


}

 


for($i = 0 ; $i <count($apple->list_row) ; $i ++ ){
// echo $apple->list_row[$i].'<br/>' ; 

// echo(fmod($i, count($apple->row)) . "<br>");

 

 switch (fmod($i, count($apple->row))) {
   case 0:
  
    
 
 if($apple->list_row[$i+4]==""){
?>

<title>
  <?php echo $apple->list_row[$i] ; ?>
</title>
</head>
<body>

<?php 

   echo '<h1>'.$apple->list_row[$i].'</h1>' ; 


 







 }

     

     break;
   case 1:

    if($apple->list_row[$i+3]==""){
     
      if($apple->list_row[$i]==""){
        echo '<img src="https://images.pexels.com/photos/3705944/pexels-photo-3705944.jpeg?auto=compress&cs=tinysrgb&w=600" alt="" srcset="">' ; 
 
 
 
 


   

        if($_SERVER['SERVER_NAME']=="localhost"){
          echo '<div>';
          echo '<img src="http://localhost/Model_Vue8/login/src/img/all/qr_code/'.$apple->list_row[$i+4].'.png" alt="" srcset="">' ; 

          echo '</div>';
       
        }
 
 
        

      }
      else {



        if($_SERVER['SERVER_NAME']=="localhost"){
          echo '<img src="http://localhost/Model_Vue8/login/src/img/all/'.$apple->list_row[$i].'" alt="" srcset="">' ; 

        }
        else {
  

          echo '<div>';
          echo '<img src="https://'.$_SERVER['SERVER_NAME'].'/login/src/img/all/'.$apple->list_row[$i].'">' ; 
          echo '</div>';
          
 
 
        }

 
      }


    }
     

     break;

     case 2:
  
    if($apple->list_row[$i+2]==""){

      echo '<h3>'.$apple->list_row[$i].'</h3>' ; 

    }
 
      
       break;


       case 3:
 
 
        if($_SERVER['SERVER_NAME']=="localhost"){
          if($apple->list_row[$i+2]==""){

            echo "<img src='http://localhost/Model_Vue8/login/src/img/all/qr_code/".$apple->list_row[$i+2].".png'>" ; 

          }

        }
        else {

          if($apple->list_row[$i+1]==""){
            echo '<img src="https://'.$_SERVER['SERVER_NAME'].'/login/src/img/all/qr_code/'.$apple->list_row[$i+2].'.png">' ; 


          }

          
  
          
 
 
        }



        if($apple->list_row[$i+1]==""){
        echo '<h4>'.$apple->list_row[$i].'</h4>' ; 

        }

      
          
           break;
           


 }






}

echo '</div>';


// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!




echo '<div class="page_t1">';
 
 
if($apple->list_row[4]!="i")
{



for($i = 0 ; $i <count($apple2->list_row) ; $i ++ ){
  // echo $apple2->list_row[$i].'<br/>' ; 
  
  // echo(fmod($i, count($apple2->row)) . "<br>");
  
   
  
   switch (fmod($i, count($apple2->row))) {
     case 0:
    
      
   
   if($apple2->list_row[$i+4]==""){
     echo '<h1>'.$apple2->list_row[$i].'</h1>' ; 
   }
  
       
  
       break;
     case 1:


  
      if($apple2->list_row[$i+3]==""){
        echo "<div><a href='".$apple2->list_row[$i+4]."'>Voir article</a>

        </div>" ; 
        

       
        if($apple2->list_row[$i]==""){
          echo '<img src="https://images.pexels.com/photos/3705944/pexels-photo-3705944.jpeg?auto=compress&cs=tinysrgb&w=600" alt="" srcset="">' ; 
   
        }
        else {
  
  
  
          if($_SERVER['SERVER_NAME']=="localhost"){
            echo '<img src="http://localhost/Model_Vue8/login/src/img/all/'.$apple2->list_row[$i].'" alt="" srcset="">' ; 
  
          }
          else {
    
  
            echo '<div>';
            echo '<img src="https://'.$_SERVER['SERVER_NAME'].'/login/src/img/all/'.$apple2->list_row[$i].'">' ; 
            echo '</div>';
            
   

          }


       
   
        }
  
  
      }
       
  
       break;
  
       case 2:
    
   
    
        
         break;
  
  
         case 3:
   
  
          if($_SERVER['SERVER_NAME']=="localhost"){
            //echo '<img src="http://localhost/Model_Vue8/login/src/img/all/'.$apple2->list_row[$i+2].'" alt="" srcset="">' ; 
  
          }
          else {
    
            //echo '<img src="https://'.$_SERVER['SERVER_NAME'].'/login/src/img/all/qr_code/'.$apple2->list_row[$i+2].'.png">' ; 
            
   
   
          }

          
  
          if($apple2->list_row[$i+1]==""){
         
                echo '<h3>'. $apple2->list_row[$i-1].'</h3>' ;

              echo '<h3>'. $apple2->list_row[$i].'</h3>' ; 

              

          }
          
        

  
             break;
             
  
  
   }
  
  
  
  
  
  
  }

  


 
}
  echo '</div>';

?>


 <h1 class="voir_projet">
   
  <a href="../projet.php" >Tous les articles</a>

 <br/>
 <br/> <br/> <br/>  

<?php

if($apple->list_row[6] !=""){
?>

<a href="<?php echo $apple->list_row[6]  ?>">Source article </a>
<?php 
}

?>

 </h1>



<style>
  .voir_projet{
    text-align:center ; 
    margin-top:50px; 
    margin-bottom : 50px; 
  }
 .page_t1{
   text-align:center ; 
   max-width: 60%;
   margin:auto ; 
 }
 .page_t1 img{
  margin-top:50px ; 
  max-width:60%;
 }
  .page_t1 h1{
padding-top:50px;
 }
 .page_t1 h3{
color:rgba(0,0,0,0.3) ; 
margin-top:80px; 
text-align: justify;
font-size:1.2em; 
color:white ; 
 }

 .page_t1 h4{
color:rgba(0,0,0,0.1) ; 
color:white ; 

 }

 @media screen and (max-width: 1200px) {
   .page_t1{
   text-align:center ; 
   max-width: 90%;
   margin:auto ; 
 }
 .page_t1 img{
  margin-top:50px ; 
  max-width:90%;
 }
}
</style>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
</body>
</html>








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
 

 <style>
  .display_flex{
    display:flex ; 
    justify-content:space-around ; 
    padding:20px; 
  }

  body{
    
    color:white ; 

    background-image: linear-gradient(to right,#1f1748, #3e1f50);
  }
 </style>


<script>


const src__1 ="https://img.icons8.com/ios/30/visible--v1.png" ; 
const src__2 ="https://img.icons8.com/ios/30/visible--v1.png" ; 


 
  
  
 
 


 
</script>
