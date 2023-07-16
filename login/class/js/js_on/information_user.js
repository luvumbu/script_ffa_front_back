   
let array_information_user = [] ; 

function information_user(){
  var xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200) {    
      const obj = JSON.parse(  this.responseText);      
        
         
          console.log(obj); 
          array_information_user.push(obj) ; 
       }            
  };
  xhttp.open("GET","class/php/php_on/information_user.php", true);
  // xhttp.open("GET",php_on_link_stock[0][x], true);
  
  
  
  xhttp.send(); 
}