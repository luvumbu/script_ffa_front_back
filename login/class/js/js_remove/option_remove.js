function option_remove(_this){
  
        console.log(_this.title) ; 
 
        var ok = new Information("class/php/php_remove/option_remove.php"); // création de la classe 
        ok.add("liste_projet_name", _this.title); // ajout de l'information pour lenvoi 
   
        console.log(ok.info()); // demande l'information dans le tableau
        ok.push(); // envoie l'information au code pkp


        let element = document.getElementById(_this.title);
element.remove();
 
 
}


 


function option_remove2(_this){
  
        console.log(_this.title) ; 
 
        var ok = new Information("class/php/php_remove/option_remove.php"); // création de la classe 
        ok.add("liste_projet_name", _this.title); // ajout de l'information pour lenvoi 
   
        console.log(ok.info()); // demande l'information dans le tableau
        ok.push(); // envoie l'information au code pkp




        let element = document.getElementById("parent_"+_this.title);
        element.remove();
 
 
 
}



function option_remove3(_this){
  

     
  
        console.log(_this.title) ; 
 
        var ok = new Information("class/php/php_remove/option_remove3.php"); // création de la classe 
        ok.add("liste_projet_name", _this.title); // ajout de l'information pour lenvoi 
   
        console.log(ok.info()); // demande l'information dans le tableau
        ok.push(); // envoie l'information au code pkp




        let element = document.getElementById("parent_"+_this.title);
        element.remove();

       
 
 
 
}


 