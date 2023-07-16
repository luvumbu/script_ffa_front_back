function add_group_affiche_form(_this) {

    const xhttp = new XMLHttpRequest();
    xhttp.onload = function() {
      document.getElementById("id_div_global_style1").innerHTML =
      this.responseText;
    }
    xhttp.open("GET", "pages_on/add_group_affiche_form.html");
    xhttp.send();
}



function add_group(_this){
let liste_group_name = document.getElementById("liste_group_name").value  ; 


 
 
var ok = new Information("class/php/php_add/add_group.php"); // création de la classe 
ok.add("liste_group_name", liste_group_name); // ajout de l'information pour lenvoi 
console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 
document.getElementById("id_div_global_style1").innerHTML=
'<div class="alert alert-success" role="alert">Ajout du groupe '+liste_group_name+'</div>' ; 
const myTimeout = setTimeout(myGreeting, 200);
function myGreeting() {
    document.getElementById("id_div_global_style1").innerHTML="" ; 
}
 
}

function add_group_affiche_form_disable(){
    document.getElementById("id_div_global_style1").innerHTML="" ; 

}