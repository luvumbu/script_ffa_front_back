function plus2(_this){

    _this.className="display_none"; 
   let cookie_table_info2 ="";
  




var ok = new Information("class/php/php_add/add_liste_projet_child_exe.php"); // création de la classe 
console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 





 







let myTimeout = setTimeout(liste_projet_child_exe_, 100);

function liste_projet_child_exe_() {


_this.className=""; 







const xhttp = new XMLHttpRequest();
xhttp.onload = function() {
document.getElementById("cookie_table2").innerHTML = this.responseText;
//  console.log(this.responseText); 



cookie_table_info2= this.responseText ; 




var  para = document.createElement("div");
para.setAttribute("class","div_child_style1 space_info"); 
para.setAttribute("id","div_child_style1_xx"+plus2_); 
 


document.getElementById("id_div_global_style1_x").appendChild(para);


var  para = document.createElement("input");
para.setAttribute("class","input_child"); 
para.setAttribute("placeholder","Mon titre"); 
para.setAttribute("onkeyup","update_liste_projet_child_description1(this)"); 

para.setAttribute("title",plus2_); 

document.getElementById("div_child_style1_xx"+plus2_).appendChild(para);


var  para = document.createElement("textarea");
para.setAttribute("class","textarea_child"); 
para.setAttribute("placeholder","Description  du titre "); 
para.setAttribute("onkeyup","update_liste_projet_child_description2(this)"); 
para.setAttribute("title",plus2_); 

document.getElementById("div_child_style1_xx"+plus2_).appendChild(para);




var  para = document.createElement("img");
para.setAttribute("src",src_img1); 
para.setAttribute("title",plus2_); 
para.setAttribute("class","padding_15 cursor_pointer");

para.setAttribute("onclick","remove_element(this)"); 





document.getElementById("div_child_style1_xx"+plus2_).appendChild(para);
 
var  para = document.createElement("img");

para.setAttribute("src",src_img3); 
para.setAttribute("class","padding_15 cursor_pointer");


para.setAttribute("title",plus2_); 

para.setAttribute("onclick","updatet_child_visibilite1(this)"); 




document.getElementById("div_child_style1_xx"+plus2_).appendChild(para);



















var  para = document.createElement("img");
para.setAttribute("src",src_img5); 
para.setAttribute("onclick","dowload_img_2(this)");
para.setAttribute("class","padding_15 cursor_pointer");

para.setAttribute("title",plus2_);




document.getElementById("div_child_style1_xx"+plus2_).appendChild(para);














var  para = document.createElement("img");
para.setAttribute("src",src_img2); 
para.setAttribute("onclick","add_child_plus(this)");
para.setAttribute("class","padding_15 cursor_pointer");
para.setAttribute("title",plus2_);
document.getElementById("div_child_style1_xx"+plus2_).appendChild(para);


 //









 






 



plus2_ ++ ; 



window.scrollTo( 0,scrollTo_);
scrollTo_ = scrollTo_ + 700 ; 

}
xhttp.open("GET", "class/php/cookie_table/liste_projet_child_cookie.php");

xhttp.send();














}






}
