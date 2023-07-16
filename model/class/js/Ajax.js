function Ajax(id,source){
	var xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200) {
      document.getElementById(id).innerHTML =
      this.responseText;
    }
  };
  xhttp.open("GET", source, true);
  xhttp.send();
}

// exemple de code 

/* 
Ajax(nomId,document/source.txt);
*/