
function update_value(_this){
    var ok = new Information("class/php/php_update/update_value.php"); // création de la classe 
    ok.add("_this_value", _this.value); // ajout de l'information pour lenvoi 
    ok.add("_this_title", _this.title); // ajout d'une deuxieme information denvoi  
    ok.add("_this_id", _this.id); // ajout d'une deuxieme information denvoi  
    
    console.log(ok.info()); // demande l'information dans le tableau
    ok.push(); // envoie l'information au code pkp 
    
    
     
    
      }