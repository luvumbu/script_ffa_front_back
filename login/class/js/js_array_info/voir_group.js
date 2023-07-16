function    voir_group(){
    let   array_voir_group = [];
       var xmlhttp = new XMLHttpRequest();
var url = "class/php/php_on/voir_group.php";


xmlhttp.onreadystatechange = function() {
 if (this.readyState == 4 && this.status == 200) {
   var myArr = JSON.parse(this.responseText);
   
   console.log(myArr) ; 
   array_voir_group.push(myArr) ;


   

 }
};

xmlhttp.open("GET", url, true);
xmlhttp.send();



const myTimeout = setTimeout(myGreeting, 1000);

/*
<div class="display_flex">
    <div class="g">
        Nom du groupe 
    </div>
    <div class="d">
        Date ajour
    </div> 
    <div class="v">
       Voir groupe
    </div> 
</div>


<div class="display_flex">
    <div class="g">
       Groupe n1
    </div>
    <div class="d">
      18/11/1991
    </div> 
    <div class="v">
        <img width="20" height="20" src="https://img.icons8.com/external-kiranshastry-solid-kiranshastry/50/external-link-business-kiranshastry-solid-kiranshastry.png" alt="external-link-business-kiranshastry-solid-kiranshastry"/>
     </div> 
</div>


*/
function myGreeting() {
   console.log(array_voir_group) ; 
   document.getElementById("id_div_global_style2").innerHTML="" ; 


var para = document.createElement("div"); 
para.id = "info_group";
para.setAttribute("class","display_flexx") ; 

document.getElementById("id_div_global_style2").appendChild(para);


var para = document.createElement("div"); 
para.innerHTML = "Nom du groupe";

para.setAttribute("class","g") ; 
document.getElementById("info_group").appendChild(para);

var para = document.createElement("div"); 
para.innerHTML = "Date ajout";

para.setAttribute("class","d") ; 
document.getElementById("info_group").appendChild(para);

var para = document.createElement("div"); 
para.innerHTML = "Voir groupe";

para.setAttribute("class","v") ; 
document.getElementById("info_group").appendChild(para);

var para = document.createElement("div"); 
para.innerHTML = "Supprimer groupe";

para.setAttribute("class","v") ; 
document.getElementById("info_group").appendChild(para);


 


if(array_voir_group[0][0].liste_group_name!=undefined){
   for(var x = 0 ; x <array_voir_group[0].length ; x ++){
      console.log(array_voir_group[0][x].liste_group_name) ; 
   
      
   var para = document.createElement("div"); 
   para.id = "parent_"+array_voir_group[0][x].liste_group_id_sha1;
    
   para.setAttribute("class","display_flexx") ; 
   document.getElementById("id_div_global_style2").appendChild(para);
   
   
   var para = document.createElement("div"); 
   para.innerHTML = array_voir_group[0][x].liste_group_name;
   
   para.setAttribute("class","g") ; 
   document.getElementById("parent_"+array_voir_group[0][x].liste_group_id_sha1).appendChild(para);
   
   var para = document.createElement("div"); 
   para.innerHTML = array_voir_group[0][x].liste_group_reg_date;
   
   para.setAttribute("class","d") ; 
   document.getElementById("parent_"+array_voir_group[0][x].liste_group_id_sha1).appendChild(para);
   
   var para = document.createElement("div"); 
   para.innerHTML = '<img width="20" height="20" src="https://img.icons8.com/external-kiranshastry-solid-kiranshastry/20/external-link-business-kiranshastry-solid-kiranshastry.png" alt="external-link-business-kiranshastry-solid-kiranshastry"/>';
   para.title=array_voir_group[0][x].liste_group_id_sha1 ; 
   para.setAttribute("class","v") ; 
   document.getElementById("parent_"+array_voir_group[0][x].liste_group_id_sha1).appendChild(para);
   



   var para = document.createElement("div"); 
   para.innerHTML = '<img width="20" height="20" src="https://img.icons8.com/color/50/delete-forever.png" alt="delete-forever"/>';
   para.title=array_voir_group[0][x].liste_group_id_sha1 ; 
  
   para.setAttribute("onclick","option_remove3(this)") ; 

   
   para.setAttribute("class","v cursor_pointer") ; 
   document.getElementById("parent_"+array_voir_group[0][x].liste_group_id_sha1).appendChild(para);
   
   
   
   
   
     }
}



  var para = document.createElement("div"); 
para.id = "info_group2";
para.setAttribute("class","display_flexx") ; 

document.getElementById("id_div_global_style2").appendChild(para);


var para = document.createElement("div"); 
para.innerHTML = "Nom du groupe";

para.setAttribute("class","g") ; 
document.getElementById("info_group2").appendChild(para);

var para = document.createElement("div"); 
para.innerHTML = "Date ajout";

para.setAttribute("class","d") ; 
document.getElementById("info_group2").appendChild(para);

var para = document.createElement("div"); 
para.innerHTML = "Voir groupe";

para.setAttribute("class","v") ; 
document.getElementById("info_group2").appendChild(para);


var para = document.createElement("div"); 
para.innerHTML = "Supprimer groupe";

para.setAttribute("class","v") ; 
document.getElementById("info_group2").appendChild(para);
   
}

   }