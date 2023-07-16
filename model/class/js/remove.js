function remove() {
    const dbname=   document.getElementById("dbname").value ;
    const username=   document.getElementById("username").value ;
    const password=   document.getElementById("password").value ;
    var ok = new Information("model/class/php/remove_db_manuel.php"); // création de la classe 
    ok.add("dbname", dbname); // ajout de l'information pour lenvoi 
    ok.add("username", username); // ajout de l'information pour lenvoi 
    ok.add("password", password); // ajout de l'information pour lenvoi   
    console.log(ok.info()); // demande l'information dans le tableau
    ok.push(); // envoie l'information au code pkp 



    const myTimeout = setTimeout(myGreeting, 500);

function myGreeting() {
 
  location.reload();
}
  
  }