function login_user(_this) {
   

//document.getElementById(_this.id).className="display_none" ; 

//document.getElementById("my_body").className="display_none" ; 
    //let text = "X coords: " + x + ", Y coords: " + y;
    //document.getElementById("el").innerHTML = text;
    //document.getElementById("info").innerHTML = "hauteur : "+ window.innerWidth+" largeur : "+window.innerHeight;
    var ok = new Information("class/php/php_off/login_user.php"); 
   
    // création de la classe 
let information_user_login = document.getElementById("information_user_login").value ; 
let information_user_password = document.getElementById("information_user_password").value ; 

    //let select_info_ip_user = document.getElementById("ip_info_cookie_cool_cook").title ;
 


    ok.add("information_user_login",information_user_login); // ajout de l'information pour lenvoi 
    ok.add("information_user_password",information_user_password); // ajout de l'information pour lenvoi  
    //console.log(ok.info()); // demande l'information dans le tableau
    ok.push(); // envoie l'information au code pkp 







    const myTimeout = setTimeout(exe__, 1000);

function exe__() {
  /*
  document.getElementById(_this.id).className="index_class_4" ; 
    const xhttp = new XMLHttpRequest();
  xhttp.onload = function() {
    document.getElementById("login_user_information").innerHTML = this.responseText;

     

     
    }
  xhttp.open("GET", "class/php/php_off/login_user_information.php", true);
  xhttp.send();
  */
}



const myTimeout_2 = setTimeout(information_user_info_function, 250);
const myTimeout_ = setTimeout(reload_page, 2220);




function reload_page() {
  location.reload() ; 
}


function information_user_info_function() {


  


  var information_user_info = document.getElementById("information_user_info");
 


  const xhttp = new XMLHttpRequest();
  xhttp.onload = function() {
    information_user_info.innerHTML =
    this.responseText;




const myTimeout_x = setTimeout(info, 200);

var  information_user_info_log = document.getElementById("information_user_info_log") ;
function info(){
 


  if (information_user_info_log.title==400) {
    information_user_info_log.innerHTML ="Création d'un utilisateur succes" ;
    information_user_info_log.className="alert alert-primary text_center";

  } else if (information_user_info_log.title==200) {
    information_user_info_log.innerHTML ="Connexion reussi";
    information_user_info_log.className="alert alert-success text_center";

        location.reload() ; 

  } else {
    information_user_info_log.innerHTML="Mot de passe Incorrecte" ; 
    information_user_info_log.className="alert alert-danger text_center";

    
  }

}




  }
  xhttp.open("GET", "class/php/php_off/information_user_info.php");
  xhttp.send();
}


  



  }