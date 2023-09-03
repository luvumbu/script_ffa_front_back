function onclick_recherche(_this){

 
  el2 = _this; 
 
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

  
  
  for(var x = nc ; x< cotation_x.length ; x ++){  


    var  myOperation = new Operation(myObj,cotation_x[x],tab_sprint[x]);
    myOperation.boucle_() ;
    myOperation.myChart_("canvas"+x) ; 


 

  
  }

 


      // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!


/*
  this.i_level = 0 ;
      this.n_level = 0 ;
      this.ir_level = 0 ;
      this.r_level = 0 ;
      this.d_level = 0 ;





       




      
      this.x = [];
      this.x2 = [];  


*/
      // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  
  
   //myObj,cotation_x[x],tab_sprint[x] ; 
  
  
  
    
  // nono
  //console.log(myOperation.array_1_info()) ; 
      
  information_g = myObj ;





console.log(onclick_recherche_element) ;
  console.log(_this) ;
  
   
                }
              };
   
             
               xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/"+limits+"["+onclick_recherche_element+"]/"+el2, true);
            xmlhttp.send();
  }  
  // fin on recherche