<?php 
session_start() ; 
header("Access-Control-Allow-Origin: *");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="projet.css">


<?php 


 
 
 
 

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



 
  
include("model/class/php/Select_datas.php") ;  

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

  
   $apple->sql='SELECT * FROM `liste_projet` WHERE 1';
   $apple->execution();
   $myJSON = json_encode($apple->list_row); 

   // echo   $myJSON ; 











  

   // echo   $myJSON ; 







 

 



 
 
/*

if($_SERVER['SERVER_NAME']=="localhost"){
  echo '<img src="http://localhost/Model_Vue8/login/src/img/all/qr_code/'.$name.'.png" alt="" srcset="">' ; 

}
else {


  echo '<div>';
  echo '<img src="https://'.$_SERVER['SERVER_NAME'].'/login/src/img/all/qr_code/'.$name.'.png">' ; 
  echo '</div>';
  


}
*/

$title_ = false ; 
 
for($i = 0 ; $i <count($apple->list_row) ; $i ++ ){


 
 
// echo $apple->list_row[$i].'<br/>' ; 

// echo(fmod($i, count($apple->row)) . "<br>");

 
 
 switch (fmod($i, count($apple->row))) {
   case 0:
  
    
 
 if($apple->list_row[$i+4]==""){

  if($title_==false){


$variable_http = "https://luvumbu.com/login/src/img/all/";

 



  $favicon = $variable_http.$apple->list_row[$i+1]   ; 
 
?>
 
<title>
  <?php echo $apple->list_row[$i] ; ?>
</title>

<link rel="icon" type="image/x-icon" href="<?php echo  $favicon ?>">
</head>


<?php 


  
echo '<body>';



echo '<div class="page_t1">';
$title_ = true ; 
 }



if($apple->list_row[$i+6]==""){
   echo '<h1>'.$apple->list_row[$i].'</h1>' ; 
}

 }

     

     break;
   case 1:

    if($apple->list_row[$i+5]==""){
     
   

    if($apple->list_row[$i+3]==""){
     
      if($apple->list_row[$i]==""){
        echo '<a href="https://images.pexels.com/photos/3705944/pexels-photo-3705944.jpeg?auto=compress&cs=tinysrgb&w=600">' ;
        echo '<img src="https://images.pexels.com/photos/3705944/pexels-photo-3705944.jpeg?auto=compress&cs=tinysrgb&w=600" alt="" srcset="">' ; 
        echo '</a>' ; 
 
 
  


   

        if($_SERVER['SERVER_NAME']=="localhost"){
          
          echo '<div>';
          echo '<a href="http://localhost/Model_Vue8/login/src/img/all/qr_code/'.$apple->list_row[$i+4].'.png">';
          echo ' <img src="http://localhost/Model_Vue8/login/src/img/all/qr_code/'.$apple->list_row[$i+4].'.png" alt="" srcset="">' ; 
          echo '</a>';
          echo '</div>';

       
        }
 
 
        

      }
      else {



        if($_SERVER['SERVER_NAME']=="localhost"){
          echo '<a href="http://localhost/Model_Vue8/login/src/img/all/'.$apple->list_row[$i].'">';
          echo '<img src="http://localhost/Model_Vue8/login/src/img/all/'.$apple->list_row[$i].'" alt="" srcset="">' ; 
          echo '</a>';
        }
        else {
  
          echo '<a href="https://'.$_SERVER['SERVER_NAME'].'/login/src/img/all/'.$apple->list_row[$i].'">';
          echo '<div>';
          echo '<img src="https://'.$_SERVER['SERVER_NAME'].'/login/src/img/all/'.$apple->list_row[$i].'">' ; 
         echo '</a>';
          echo '</div>';
          
 
 
        }

 
      }


      echo "<br/>"  ; 

      echo '<a href="vlog.php/'.$apple->list_row[$i+4].'">LIEN DE LA PAGE </a>' ;




    }
     
  }
     break;

     case 2:

      
      if($apple->list_row[$i+4]==""){
    if($apple->list_row[$i+2]==""){

      echo '<h3>'.$apple->list_row[$i].'</h3>' ; 

    }
 
      
       break;
      }

       case 3:
        if($apple->list_row[$i+3]==""){
 
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
     
        }
          
           break;
           


 }






}

echo '</div>';


// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!


?>


 <h1 class="voir_projet">
   
  <a href="index.php">Menu Principal</a>


 </h1>



 
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
</body>
</html>