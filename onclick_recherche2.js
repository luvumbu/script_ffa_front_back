
function onclick_recherche2(_this){

console.log("alert ok ") ; 

console.log(cotation_x);
console.log(cotation_x.length); 
console.log("alert ok ") ; 
    console.log("canvas") ;


 
var onclick_recherche_element ="get_club_nom_complet_array_2" ;
var el2 ="Lille Metropole Athletisme*" ;

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




  var  para = document.createElement("h1");
  para.setAttribute("id",el2) ;
  para.setAttribute("class","text-center") ;
  para.setAttribute("style","margin-bottom:100px") ;


  para.innerHTML=el2 ; 
  document.getElementById("optiones_total").appendChild(para);
var i_level = 0 ; 
  var n_level = 0 ; 
var ir_level = 0 ; 
var r_level = 0 ; 
  var d_level = 0 ; 
  for(var x = nc ; x< cotation_x.length ; x ++){  
    var  myOperation = new Operation(myObj,cotation_x[x],tab_sprint[x]);
    myOperation.boucle_() ;
    myOperation.myChart_("canvas"+x) ; 


    i_level = i_level + myOperation.i_level  ; 
    n_level = n_level + myOperation.n_level  ; 
    ir_level = ir_level + myOperation.ir_level  ; 
    r_level = r_level + myOperation.r_level  ; 
    d_level = d_level + myOperation.d_level  ; 
  
  }
console.log(i_level) ; 
console.log(n_level) ; 
console.log(ir_level) ; 
console.log(r_level) ; 
console.log(d_level) ; 


  console.log(
    i_level+
    n_level+
    ir_level+
    r_level+
    d_level) ; 

  /*
x = 6; 
 
   var  myOperation = new Operation(myObj,cotation_x[x],tab_sprint[x]);
    myOperation.boucle_() ;
    myOperation.myChart_("canvas"+x) ; 
  */



                }
              };
   
                //xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/["+onclick_recherche_element+"]/"+_this, true);
                xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/"+limits+"["+onclick_recherche_element+"]/"+el2, true);
            xmlhttp.send();
  }  
  // fin on recherche