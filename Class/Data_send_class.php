<?php
/**
 * Data_send_class.php — Classe d'envoi de donnees via AJAX / Data sending class via AJAX
 * FR: Classe qui genere du JavaScript pour envoyer des donnees de formulaire via XMLHttpRequest
 * EN: Class that generates JavaScript to send form data via XMLHttpRequest
 */
class Data_send_class
{ 

private $array_add = []; 
private $src_push = "" ; 

 

 function __construct($src_push) {

   $this->src_push = $src_push ; 
   
 }


 function voir($info) {
 
  for($a = 0 ; $a < count($info) ; $a ++) {
 
  



if($info[$a][1]=="input") {
   $this->set_array_add($info[$a][0],$info[$a][1]) ;  
}
  
    }


 }


  

 function set_array_add($var_nam,$value) {
   //array_push($this->array_add,$array_add);



  $this->array_add[] =  array($var_nam,$value);

 }


 

 function get_array_add() {
   return $this->array_add;
 }

 function push_send_class() {
?>
<script>

    var ok = new Information('<?php echo $this->src_push ?>'); // création de la classe 

</script>
<?php 
for($a = 0 ; $a <count($this->get_array_add()) ; $a ++ ){

?>
<script>
 ok.add(<?php echo "'".$this->get_array_add()[$a][0]."','".$this->get_array_add()[$a][1]."'" ?>); // ajout de l'information pour lenvoi 
</script>

<?php 
}

?>
<script>
 
   console.log(ok.info()); // demande l'information dans le tableau
   ok.push(); // envoie l'information au code pkp 

</script>

<?php 
 }
}


?>