let   array_voir_liste_projet =["007"];

function    voir_liste_projet(){
  
 
       var xmlhttp = new XMLHttpRequest();
var url = "class/php/php_on/voir_liste_projet.php";


xmlhttp.onreadystatechange = function() {
 if (this.readyState == 4 && this.status == 200) {
   var myArr = JSON.parse(this.responseText);
   
 

 


 array_voir_liste_projet = myArr ;
 

 }
};

xmlhttp.open("GET", url, true);
xmlhttp.send();



 
   }