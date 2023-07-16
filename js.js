function header_option(this_){
    console.log(this_.title) ;    
    
     


 



  if(this_.title =="Login"){
        //   Ajax("optiones_total","view/pages/Login.php");

        window.location.replace("login/index.php");

  }
  else {
    Ajax("optiones_total","view/pages/general_model.php");

    const myTimeout = setTimeout(myStopFunction, 100);

function myStopFunction() {

  document.getElementById("nom_model_1").innerHTML=this_.title ; 
  document.getElementById("nom_model_2").innerHTML=this_.title ; 


 
  Ajax("link_css","view/pages/link_css_"+this_.title+".html");
  
 let result = this_.title.replace("s", "");
  switch(this_.title) {
            case "Clubs":
                     document.getElementById("nom_model_3").placeholder="Recherche un "+result ;
                     document.getElementById("nom_model_2").backgroundImage="sport/women-655353_1920.jpg" ; 


                break;
            case "Athletes":
           
                         document.getElementById("nom_model_3").placeholder="Recherche un "+result ; 

                break;
                case "Villes":

                         document.getElementById("nom_model_3").placeholder="Recherche une "+result ; 

            break ;
         
                        }
  }


}
     
     //       Ajax("optiones_total","view/pages/"+this_.title+".php");

    
     
    
    
        }


        
function Inscription1() {
    Ajax("optiones_total","view/pages/Inscription1.php");
  }
  function Inscription2() {
    Ajax("optiones_total","view/pages/Inscription2.php");
  }


