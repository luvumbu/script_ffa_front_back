function onclick_recherche(_this){

    document.getElementById("optiones_total").innerHTML="" ;  
   img_chargement() ; 
   // Ajax("optiones_total","apparence/general_info1_5.php");
  
    var recherche_ = "get_club_nom_complet_array_2" ; 
              var recherche_element ="A. La Riviere De Corps" ; 
              var recherche_element ="Clll Armentieres" ; 
              var recherche_element ="Lille Metropole Athletisme*" ; 
              var recherche_element ="Lagardere Paris Racing" ; 
              var recherche_element ="Ca Montreuil 93" ; 
              var recherche_element ="Neuilly-Plaisance Sports" ; 
              var total_s = 0 ;   
  
                     
             // console.log(tab_sprint) ; 
  
              var information_g  ; 
              var xmlhttp = new XMLHttpRequest();
              xmlhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                  var myObj = JSON.parse(this.responseText);
                  document.getElementById("optiones_total").innerHTML="" ;  
        
               
  console.log(myObj) ; 
                  switch(recherche_) {
    case "get_club_nom_complet_array_2":
    
   
      break;
   
   
  }
  //const myOperation = new Operation(myObj,tab_sprint,cotation_1);
  //const myOperation = new Operation(myObj,cotation_2);
   // nonono
   
  
  var nc = 0 ; 
  var nc2 = 5;
  
  
  /*
  for(var x = nc ; x< nc2 ; x ++){  
    var  myOperation = new Operation(myObj,cotation_x[x],tab_sprint[x]);
    myOperation.boucle_() ;
    myOperation.myChart_(nom_canvas[x]) ; 
  
  }
   
  */
  var  myOperation = new Operation(myObj,cotation_x[0],tab_sprint[0]);
    myOperation.boucle_() ;
    myOperation.myChart_("Mon_canvas") ; 
   
  
  
   //myObj,cotation_x[x],tab_sprint[x] ; 
  
  
  
    
  // nono
  //console.log(myOperation.array_1_info()) ; 
      
  information_g = myObj ;
  
   
                }
              };
   
                xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/"+limits+"["+onclick_recherche_element+"]/"+_this, true);
               // xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/"+limits+"["+onclick_recherche_element+"]/"+el2, true);
            xmlhttp.send();
  }  
  // fin on recherche