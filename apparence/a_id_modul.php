 


 
          <script>


 
            var recherche_ = "get_club_nom_complet_array_2" ; 
  

            let n_ = 0 ; 





            


            
           // console.log(tab_sprint) ; 




            var information_g  = false ; 
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
              if (this.readyState == 4 && this.status == 200) {
                var myObj = JSON.parse(this.responseText);
                 


 console.log(recherche_element);

 
 information_g = true ; 

 recherche_element = myObj ; 

 

 


 

              
              
 
              }
            };
            xmlhttp.open("GET", "https://bokonzi.com/action.php", true);
            xmlhttp.send();
 


        





setInterval(displayHello, 2500);
var recherche_element ="Lille Metropole Athletisme*" ; 

 

 

var recherche_ = "get_club_nom_complet_array_2" ; 

function displayHello() {


  console.log(information_g) ;


  if(information_g){


  

  var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
              if (this.readyState == 4 && this.status == 200) {
                var myObj = JSON.parse(this.responseText);
                 


 console.log(myObj) ; 
 console.log(n_+" : "+recherche_element[n_]) ; 
 


 
  


 

              
              
 
              }
            };
           
            xmlhttp.open("GET", "https://bokonzi.com/ffa/vlog.php/["+recherche_+"]/"+recherche_element[n_], true);

            xmlhttp.send();


            n_ ++ ; 
            

  }

}




</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>

<script>

</script>