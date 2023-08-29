function header_option(this_){
    console.log(this_.title) ;    
    
     


 



  if(this_.title =="Login"){
           Ajax("optiones_total","view/pages/Login.php");
        


  }
  else {
    Ajax("optiones_total","view/pages/general_model.php");

    const myTimeout = setTimeout(myStopFunction, 200);

function myStopFunction() {

  document.getElementById("nom_model_1").innerHTML=this_.title ; 
  document.getElementById("nom_model_2").innerHTML=this_.title ; 


 
  

  const myTimeout = setTimeout(myGreeting, 300);

function myGreeting() {
  Ajax("link_css","view/pages/link_css_"+this_.title+".html");
}
  
 let result = this_.title.replace("s", "");
  switch(this_.title) {
            case "Clubs":
                     document.getElementById("nom_model_3").placeholder="Recherche un "+result ;
                     document.getElementById("nom_model_2").backgroundImage="sport/women-655353_1920.jpg" ; 

                     resultat_recherche ="script_all/get_club_nom_complet_array_2/" ;
                     onclick_recherche_element ="get_club_nom_complet_array_2";
                break;
            case "Athletes":
           
                         document.getElementById("nom_model_3").placeholder="Recherche un "+result ; 
                     resultat_recherche ="script_all/get_users_nom_complet_array/" ;
                     onclick_recherche_element ="get_users_nom_complet_array";



                break;
                case "Villes":
                  resultat_recherche ="script_all/get_result_villes_nom_array_2/" ;
                  onclick_recherche_element ="get_result_villes_nom_array_2";


                  


                         document.getElementById("nom_model_3").placeholder="Recherche une "+result ; 

            break ;
         
                        }
  }


}
     
     //       Ajax("optiones_total","view/pages/"+this_.title+".php");

    
     
    
    
        }


        