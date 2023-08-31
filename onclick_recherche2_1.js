
function onclick_recherche2(_this){


    console.log("canvas") ;


var limits ="" ;
var onclick_recherche_element ="get_club_nom_complet_array_2" ;
var el2 ="Ca Montreuil 93" ;

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
                  document.getElementById("optiones_total").innerHTML="" ;  
                  var myObj = JSON.parse(this.responseText);
        
               
  console.log(myObj) ; 
                  switch(recherche_) {
    case "get_club_nom_complet_array_2":
    
   
      break;
   
   
  }
  var nc = 0 ; 
  var nc2 = tab_sprint.length;






  
  for(var x = nc ; x< nc2 ; x ++){  
    var  myOperation = new Operation(myObj,cotation_x[x],tab_sprint[x]);
    myOperation.boucle_() ;
    myOperation.myChart_("canvas"+x) ; 
  
  }




                }
              };
   
                //xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/["+onclick_recherche_element+"]/"+_this, true);
                xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/"+limits+"["+onclick_recherche_element+"]/"+el2, true);
            xmlhttp.send();
  }  
  // fin on recherche