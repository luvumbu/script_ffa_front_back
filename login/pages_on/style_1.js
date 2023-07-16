
let src_img1 = "https://img.icons8.com/fluency/40/null/delete-forever.png" ; 
let src_img2 = "https://img.icons8.com/office/40/null/plus--v1.png" ; 
let src_img3 =   "https://img.icons8.com/material-two-tone/40/null/visible.png" ; 
let src_img3_2 = "https://img.icons8.com/material-two-tone/40/null/invisible.png" ; 
let src_img4 = "https://img.icons8.com/ios-glyphs/40/null/link--v1.png" ; 
let src_img5 ="https://img.icons8.com/ios/40/null/image-file.png";
let src_img6 ="https://img.icons8.com/ios/40/null/info--v1.png";
let stc_img7 = "https://img.icons8.com/ios/40/domain--v1.png";
let src_img_hd ="https://images.pexels.com/photos/443446/pexels-photo-443446.jpeg?cs=srgb&dl=pexels-eberhard-grossgasteiger-443446.jpg&fm=jpg";
let add_liste_projet_exe_info ="";



 var plus2_ = 0 ;

 function liste_projet_child_visibilite1(_this){
   var ok = new Information("class/php/php_update/update_liste_projet_child_visibilite1.php"); // création de la classe 


if(_this.src==src_img3){
    _this.src=src_img3_2 ; 


ok.add("liste_projet_child_visibilite1", "invisible"); // ajout de l'information pour lenvoi 
 

}
else {
    _this.src=src_img3 ; 

 

ok.add("liste_projet_child_visibilite1", ""); // ajout de l'information pour lenvoi 
 

}



ok.add("liste_projet_child_id",document.getElementById("div_child_style1_xx"+_this.title).title); // ajout de l'information pour lenvoi 



console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 
 }
 function list_projet_visibility(_this){
     

        var ok = new Information("class/php/php_update/update_list_projet_visibility.php"); // création de la classe 
 

        if( _this.src==src_img3){
            _this.src=src_img3_2 ;


            ok.add("list_projet_visibility", "invisible"); // ajout de l'information pour lenvoi 

                        
        }
        else {
            _this.src=src_img3 ; 
             
            ok.add("list_projet_visibility", ""); // ajout de l'information pour lenvoi 
        
        }
        console.log(ok.info()); // demande l'information dans le tableau
        ok.push(); // envoie l'information au code pkp 
        
    }
 
function myFunction() {
    
if (document.documentElement.scrollTop > 100) {
// console.log(document.documentElement.scrollTop);

document.getElementById("id_div_global_style1").className="div_global_style12";

document.getElementById("input_titre_style1").style="display:none";

document.getElementById("input_textarea_style1").style="display:none";

} else {
console.log(document.documentElement.scrollTop);

document.getElementById("input_titre_style1").style="display:block";

document.getElementById("input_textarea_style1").style="display:block";

}
}


function update_liste_projet_description1(_this){
    console.log(_this.value) ; 

    var ok = new Information("class/php/php_update/update_liste_projet_description1.php"); // création de la classe 

ok.add("input", _this.value); // ajout de l'information pour lenvoi 
 
console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 



}
function update_liste_projet_description2(_this){
 console.log(_this.value) ; 

var ok = new Information("class/php/php_update/update_liste_projet_description2.php"); // création de la classe 

ok.add("input", _this.value); // ajout de l'information pour lenvoi
ok.add("_this_title", _this.title); // ajout de l'information pour lenvoi 


console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 


}
function update_liste_projet_child_description1(_this){
var ok = new Information("class/php/php_update/update_liste_projet_child_description1.php"); // création de la classe 

ok.add("input",_this.value); // ajout de l'information pour lenvoi 
ok.add("_this_title", _this.title); // ajout de l'information pour lenvoi 

 
console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 
}

function update_liste_projet_child_description2(_this){
var ok = new Information("class/php/php_update/update_liste_projet_child_description2.php"); // création de la classe 

ok.add("input",_this.value); // ajout de l'information pour lenvoi 
ok.add("_this_title", _this.title); // ajout de l'information pour lenvoi 

 
console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 


}
function remove_element(_this){
    console.log("xxxxx")  ; 
console.log(_this.title) ; 


console.log(document.getElementById("div_child_style1_xx"+_this.title).title) ; 




 var ok = new Information("class/php/php_remove/remove_liste_projet_child.php"); // création de la classe 

ok.add("remove_info", document.getElementById("div_child_style1_xx"+_this.title).title); // ajout de l'information pour lenvoi 
 
 
console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 




document.getElementById("div_child_style1_xx"+_this.title).remove();


}
function remove_liste_projet(_this){


    
 
 


document.getElementById("div_input_titre_style1").innerHTML="";


    document.getElementById("add_blogs").className="" ;




    location.reload() ; 


 var ok = new Information("class/php/php_remove/remove_liste_projet.php"); // création de la classe 

 
 
console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 




//document.getElementById("div_child_style1_xx"+_this.title).remove();

}
 function link_projet1(){


    console.log(document.getElementById("cookie_table").innerHTML =
    this.responseText)
   // window.location.href = "http://localhost/Model_Vue6/login/blog/index.php/";
 }