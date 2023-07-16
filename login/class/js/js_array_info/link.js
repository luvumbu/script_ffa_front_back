function link(_this){
 
  document.getElementById("id_div_global_style2").innerHTML="" ; 


  var ok = new Information("class/php/cookie_table/liste_projet_img.php"); // création de la classe 
  ok.add("liste_projet_img", _this.title); // ajout de l'information pour lenvoi 
 
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 







  const myTimeout = setTimeout(myGreeting, 500);


  var array_local = [] ; 
function myGreeting() {

  
  var url = "class/php/php_on/voir_link.php";
  var xmlhttp = new XMLHttpRequest();
  
      xmlhttp.onreadystatechange = function() {
          if (this.readyState == 4 && this.status == 200) {
            var myArr = JSON.parse(this.responseText);
            
         
            array_local = myArr ; 
            const myTimeout2 = setTimeout(myGreetingxx, 100);
         
          }
         };
         
         xmlhttp.open("GET", url, true);
         xmlhttp.send();
}






function myGreetingxx() {


var node = document.createElement("div");
 
node.id="myGreetingxx" ;  
//node.innerHTML="vmax_ok" ;  
document.getElementById("id_div_global_style2").appendChild(node);






var node = document.createElement("input");
node.id="liste_projet_description1" ;  
node.setAttribute("maxlength","50");
node.value=array_local[0][0].liste_projet_description1 ; 
node.setAttribute("onkeyup","update_value(this)");
node.setAttribute("id","i_"+array_local[0][0].liste_projet_id_sha1);
  node.setAttribute("class","input_class_general");
node.setAttribute("title",array_local[0][0].liste_projet_id_sha1);
document.getElementById("id_div_global_style2").appendChild(node);

var node = document.createElement("textarea");
node.id="liste_projet_description2" ; 
node.setAttribute("maxlength","800");

node.value=array_local[0][0].liste_projet_description2 ; 
node.setAttribute("onkeyup","update_value(this)");
node.setAttribute("id","t_"+array_local[0][0].liste_projet_id_sha1);
  node.setAttribute("class","text_area_class_general");
node.setAttribute("title",array_local[0][0].liste_projet_id_sha1);
document.getElementById("id_div_global_style2").appendChild(node);




var node = document.createElement("img");
node.id="liste_projet_description2" ;  

if(array_local[0][0].liste_projet_img!=""){
node.src="src/img/all/"+array_local[0][0].liste_projet_img ; 
node.style="width:200px" ;   
}
else{
  node.src=src_img5 ; 

}

node.setAttribute("onclick","dowload_img3(this)"); 
node.setAttribute("title",array_local[0][0].liste_projet_id_sha1);
document.getElementById("id_div_global_style2").appendChild(node);











var node = document.createElement("div"); 
node.id="div_elementss"
document.getElementById("id_div_global_style2").appendChild(node);


 

var node = document.createElement("img"); 
node.style="margin:15px;" ; 
node.src=src_img2 ;  
node.title=array_local[0][0].liste_projet_id_sha1 ;  

node.onclick="add_liste_projet_exe2(this)";
node.setAttribute("onclick","add_liste_projet_exe2(this)") ;   
document.getElementById("div_elementss").appendChild(node);


var node = document.createElement("img"); 
node.style="margin:15px;" ; 
node.setAttribute("onclick","update_liste_projet_visibilite1(this)") ; 
node.title=array_local[0][0].liste_projet_id_sha1 ;  

if(array_local[0][0].liste_projet_visibilite1!="i"){
node.src=src_img3 ;  
}
else{
  node.src=src_img3_2 ;  
}

document.getElementById("div_elementss").appendChild(node);




var node = document.createElement("img"); 
node.style="margin:15px;" ; 
node.src=stc_img7 ;  
node.title=array_local[0][0].liste_projet_id_sha1  ;  

node.setAttribute("onclick","link_w3(this)") ; 
document.getElementById("div_elementss").appendChild(node);

 

 




var node = document.createElement("div");
node.id="liste_projet_description2" ;  


node.innerHTML=array_local[0][0].liste_projet_reg_date ; 
 
document.getElementById("id_div_global_style2").appendChild(node);






 



 



 if(array_local[1][0]!="404"){
  for(var f =0 ; f<array_local[1].length; f++){
 



    var node = document.createElement("div");
 
  
    node.id="parent_"+array_local[1][f].liste_projet_id_sha1 ;
    document.getElementById("id_div_global_style2").appendChild(node);






 



    


  var node = document.createElement("input");
  node.id=array_local[1][f].liste_projet_id_sha1 ;  
  node.value=array_local[1][f].liste_projet_description1 ; 
  node.setAttribute("onkeyup","update_value(this)");
  node.setAttribute("maxlength","55");
  node.setAttribute("id","i_"+array_local[1][f].liste_projet_id_sha1);
  node.setAttribute("class","input_class_general");

  node.setAttribute("title",array_local[1][f].liste_projet_id_sha1);
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  
  var node = document.createElement("textarea");
  node.id="liste_projet_description2" ;  
  node.value=array_local[1][f].liste_projet_description2 ; 
  node.setAttribute("onkeyup","update_value(this)");
  node.setAttribute("maxlength","255");
  node.setAttribute("id","t_"+array_local[1][f].liste_projet_id_sha1);
  node.setAttribute("title",array_local[1][f].liste_projet_id_sha1);
  node.setAttribute("class","text_area_class_general");

  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  
  
  
  





  if(array_local[1][f].liste_projet_img==""){
      var node = document.createElement("img");
  node.src="src/img/all/"+array_local[1][f].liste_projet_img ;
      node.src=src_img5 ; 
 
      node.style="width:100px;height:100px" ;

     
  node.setAttribute("title",array_local[1][f].liste_projet_id_sha1);
  node.setAttribute("class","text_area_class_general cursor_pointer");
  node.setAttribute("onclick","dowload_img3(this)");  

  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);

  }
  else {
    var node = document.createElement("img");
    node.style="width:100px;height:100px" ;
      node.src="src/img/all/"+array_local[1][f].liste_projet_img ; 
      node.setAttribute("onclick","dowload_img3(this)");         
    node.setAttribute("title",array_local[1][f].liste_projet_id_sha1);
    node.setAttribute("class","text_area_class_general cursor_pointer");  
    document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  }




  
  
  /*
  var node = document.createElement("div");
  node.id="liste_projet_id" ;  
  node.innerHTML=array_local[1][f].liste_projet_id ;  
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  
  var node = document.createElement("div");
  node.id="liste_projet_id_sha1" ;  
  node.innerHTML=array_local[1][f].liste_projet_id_sha1 ;  
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  
  var node = document.createElement("div");
  node.id="liste_projet_ip" ;  
  node.innerHTML=array_local[1][f].liste_projet_ip ;  
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  
  var node = document.createElement("div");
  node.id="liste_projet_name" ;  
  node.innerHTML=array_local[1][f].liste_projet_name ;  
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  
  
  
  var node = document.createElement("div");
  node.id="liste_projet_description2" ;  
  node.innerHTML=array_local[1][f].liste_projet_description2 ;  
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  
  var node = document.createElement("div");
  node.id="liste_projet_visibilite1" ;  
  node.innerHTML=array_local[1][f].liste_projet_visibilite1 ;  
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  
  var node = document.createElement("div");
  node.id="liste_projet_visibilite2" ;  
  node.innerHTML=array_local[1][f].liste_projet_visibilite2 ;  
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  
  var node = document.createElement("div");
  node.id="liste_projet_type" ;  
  node.innerHTML=array_local[1][f].liste_projet_type ;  
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  
  var node = document.createElement("div");
  node.id="information_user_id_sha1" ;  
  node.innerHTML=array_local[1][f].information_user_id_sha1 ;  
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  */
  var node = document.createElement("div");
 
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);



  var node = document.createElement("img");
  node.src=src_img1 ;
  node.style="margin-bottom:25px" ;

  node.title=array_local[1][f].liste_projet_id_sha1 ; 
  node.setAttribute("onclick","option_remove2(this)") ;  
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);


//
 


  var  node = document.createElement("img");

  if(array_local[1][f].liste_projet_visibilite1!="i"){
    node.src=src_img3 ;
}
else {
  node.src=src_img3_2 ;
}
  node.className="input_color";
  node.style="margin-bottom:25px" ;
  node.title=array_local[1][f].liste_projet_id_sha1; 
  node.setAttribute("onclick","update_liste_projet_visibilite1(this)") ; 
 
  
 
  node.setAttribute("class","cursor_pointer") ;   
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);



  


//

  var node = document.createElement("div");
  node.id="liste_projet_new_file" ;  
  node.innerHTML=array_local[1][f].liste_projet_reg_date ;  
  document.getElementById("parent_"+array_local[1][f].liste_projet_id_sha1 ).appendChild(node);
  var node = document.createElement("div");
  node.id="liste_projet_reg_date" ; 

  
  }

 }
 
 
 

}

       
}


function link_w3(_this){
   window.location.replace("../vlog.php/"+_this.title);
}
