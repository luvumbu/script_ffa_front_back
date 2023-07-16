function change_title(){
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


 

    return nom_localisation ;
     
    
}