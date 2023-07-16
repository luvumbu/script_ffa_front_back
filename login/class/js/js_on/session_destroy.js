function session_destroy(){


    const xhttp = new XMLHttpRequest();
    xhttp.onload = function() {
      document.getElementById("my_body").innerHTML =
      this.responseText;
    }
    xhttp.open("GET", "class/php/php_on/session_destroy.php");
    xhttp.send();



    const myTimeout = setTimeout(reload_page, 280);

function reload_page() {
  location.reload() ; 
}
}