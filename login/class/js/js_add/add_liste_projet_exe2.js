function add_liste_projet_exe2(_this){


  n_ = 0 ; 
  nom_localisation="";
  for(var i = 0 ; i < window.location.href.length ; i ++){
   
   if(window.location.href[i]=="/"){
    
   n_ = n_ +1 ; 
   }
   else{
   }
  
  
   if(n_==2){
   
    if(window.location.href[i]!="/"){
   
       nom_localisation = nom_localisation+window.location.href[i] ;
    }
  
   }
  
  }


  
  var ok = new Information("class/php/php_add/add_liste_projet.php"); // création de la classe 
 
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 


  const myTimeout = setTimeout(myGreeting, 250);

function myGreeting() {
   
  const xhttp = new XMLHttpRequest();
  xhttp.onload = function() {
  

    var node = document.createElement("div");
    node.id="parent_"+ this.responseText ;  
 
    node.setAttribute("title", this.responseText);
    document.getElementById("id_div_global_style2").appendChild(node);





     
  var node = document.createElement("input");
  node.id="liste_projet_description1" ;  
  node.placeholder= "Votre tire" ; 
  node.setAttribute("onkeyup","update_value(this)");
  node.setAttribute("id","i_"+ this.responseText);
  node.setAttribute("class","input_class_general");
  node.setAttribute("maxlength","50");
  node.setAttribute("title", this.responseText);
  document.getElementById("parent_"+ this.responseText).appendChild(node);
  
  var node = document.createElement("textarea");
  node.id="liste_projet_description2" ;  
  node.placeholder= "Votre description" ; 
  node.setAttribute("maxlength","1800");
  node.setAttribute("onkeyup","update_value(this)");
  node.setAttribute("id","t_"+ this.responseText);
  node.setAttribute("title", this.responseText);
  node.setAttribute("class","text_area_class_general");

  document.getElementById("parent_"+ this.responseText).appendChild(node);







  var node = document.createElement("img");
 
  node.setAttribute("title",this.responseText) ;
  node.src=src_img5;
  node.setAttribute("onclick","dowload_img(this)") ; 
  document.getElementById("parent_"+ this.responseText).appendChild(node);






  
 
  var node = document.createElement("img");
  node.src=src_img1;
  node.setAttribute("title",this.responseText) ;
  node.setAttribute("onclick","option_remove2(this)") ;
  document.getElementById("parent_"+ this.responseText).appendChild(node);


 

  
  window.scrollTo( 0,scrollTo_);
  scrollTo_ = scrollTo_ + 1800 ; 




  // UPDATE INFO 

  var ok = new Information("class/php/php_update/update_parent_name.php"); // création de la classe 
   
 
  ok.add("child",this.responseText); // ajout d'une deuxieme information denvoi  
  ok.add("parent",  _this.title); // ajout d'une deuxieme information denvoi  
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 
 // UPDATE INFO 







 var ok = new Information("class/php/php_add/qr_code/index.php"); // création de la classe 
 if(nom_localisation=="localhost" || nom_localisation=="127.0.0.1"){
 
  ok.add("qr_code", "http://"+nom_localisation+"/blog.php/"+this.responseText); // ajout de l'information pour lenvoi 
  
  
  
  }
  else{
   
   
   
  
  ok.add("qr_code", "https://"+nom_localisation+"/blog.php/"+this.responseText); // ajout de l'information pour lenvoi 
    
  
  }
console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 


  }
  xhttp.open("GET", "class/php/cookie_table/liste_projet_cookie.php");
  xhttp.send();
  
}
  
}