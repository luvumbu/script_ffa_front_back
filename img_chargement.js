function img_chargement() {
 



    var para_ok = document.createElement("div");
     
    para_ok.setAttribute("id","chargement_encours") ; 
    para_ok.setAttribute("class","container mt-3 text-center") ; 
    
    para_ok.innerHTML="<h1>chargement</h1>" ; 
    
    
    document.getElementById("optiones_total").appendChild(para_ok);
    
    var para_ok = document.createElement("div");
     
    para_ok.setAttribute("class","spinner-border container mt-3") ;
    document.getElementById("chargement_encours").appendChild(para_ok);
    
    
     
    
    
    
    }