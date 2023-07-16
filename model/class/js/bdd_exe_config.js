var checkBox = document.getElementById("myCheck");
/* 
myCheck est le nom du boutton emplacement 
view/config

*/
function bdd_exe_config(){

    var dbname=     document.getElementById("dbname").value; 
    var password=   document.getElementById("password").value; 
    var username=   document.getElementById("username").value; 
    var checkBox_checked ="";
 

if(checkBox.checked){
  checkBox_checked ="on";
}
else {
  checkBox_checked ="off";

}
 
    var ok = new    Information("model/class/php/bdd_exe_config.php"); // création de la classe 
    ok.add("dbname", dbname); // ajout de l'information pour lenvoi 
    ok.add("password", password); // ajout d'une deuxieme information denvoi  
    ok.add("username", username); // ajout d'une deuxieme information denvoi  
    ok.add("checkBox_checked",checkBox_checked); // ajout d'une deuxieme information denvoi  
    console.log(ok.info()); // demande l'information dans le tableau
    ok.push(); // envoie l'information au code pkp 
 
    setTimeout(function(){
      document.location.reload(true);
      
      }, 250);
     /*
    Execution du programme et récuperation d'information repertoire 
    model\class\php
 */
    }