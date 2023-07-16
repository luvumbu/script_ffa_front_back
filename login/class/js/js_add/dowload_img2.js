 


 
 
function dowload_img2(_this) {
 
  liste_projet_img  = _this.title ; 
  document.getElementById("parent_label_fileInput").className="parent_label_fileInput" ; 
  document.getElementById("parent_fileInput2").className="parent_fileInput2" ; 
  document.getElementById("id_div_global_style1").className="display_none" ; 


  var ok = new Information("class/php/cookie_table/liste_projet_img.php"); // création de la classe 
ok.add("liste_projet_img", liste_projet_img); // ajout de l'information pour lenvoi 
 
console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 
 
}

 



 
 