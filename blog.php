<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
 
</head>
<div id="body"></div>
<link rel="icon" type="image/x-icon" href="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/4QETRXhpZgAASUkqAAgAAAADAA4BAgDPAAAAMgAAAJiCAgAKAAAAAQEAABIBAwABAAAAAQAAAAAAAABBbiBpbGx1c3RyYXRpb24gb2YgdGhlIGV4dGluY3QgRG9kbyBCaXJkIG9uIGEgd2hpdGUgYmFja2dyb3VuZC4gVGhlIGRvZG8gKFJhcGh1cyBjdWN1bGxhdHVzKSBpcyBhbiBleHRpbmN0IGZsaWdodGxlc3MgYmlyZCB0aGF0IHdhcyBlbmRlbWljIHRvIHRoZSBpc2xhbmQgb2YgTWF1cml0aXVzLCBlYXN0IG9mIE1hZGFnYXNjYXIgaW4gdGhlIEluZGlhbiBPY2Vhbi5BdW50X1NwcmF5/+0BLFBob3Rvc2hvcCAzLjAAOEJJTQQEAAAAAAEPHAJQAApBdW50X1NwcmF5HAJ4AM9BbiBpbGx1c3RyYXRpb24gb2YgdGhlIGV4dGluY3QgRG9kbyBCaXJkIG9uIGEgd2hpdGUgYmFja2dyb3VuZC4gVGhlIGRvZG8gKFJhcGh1cyBjdWN1bGxhdHVzKSBpcyBhbiBleHRpbmN0IGZsaWdodGxlc3MgYmlyZCB0aGF0IHdhcyBlbmRlbWljIHRvIHRoZSBpc2xhbmQgb2YgTWF1cml0aXVzLCBlYXN0IG9mIE1hZGFnYXNjYXIgaW4gdGhlIEluZGlhbiBPY2Vhbi4cAnQACkF1bnRfU3ByYXkcAm4AGEdldHR5IEltYWdlcy9pU3RvY2twaG90bwD/4QXpaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLwA8P3hwYWNrZXQgYmVnaW49Iu+7vyIgaWQ9Ilc1TTBNcENlaGlIenJlU3pOVGN6a2M5ZCI/Pgo8eDp4bXBtZXRhIHhtbG5zOng9ImFkb2JlOm5zOm1ldGEvIj4KCTxyZGY6UkRGIHhtbG5zOnJkZj0iaHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyI+CgkJPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6cGhvdG9zaG9wPSJodHRwOi8vbnMuYWRvYmUuY29tL3Bob3Rvc2hvcC8xLjAvIiB4bWxuczpJcHRjNHhtcENvcmU9Imh0dHA6Ly9pcHRjLm9yZy9zdGQvSXB0YzR4bXBDb3JlLzEuMC94bWxucy8iICAgeG1sbnM6R2V0dHlJbWFnZXNHSUZUPSJodHRwOi8veG1wLmdldHR5aW1hZ2VzLmNvbS9naWZ0LzEuMC8iIHhtbG5zOmRjPSJodHRwOi8vcHVybC5vcmcvZGMvZWxlbWVudHMvMS4xLyIgeG1sbnM6cGx1cz0iaHR0cDovL25zLnVzZXBsdXMub3JnL2xkZi94bXAvMS4wLyIgIHhtbG5zOmlwdGNFeHQ9Imh0dHA6Ly9pcHRjLm9yZy9zdGQvSXB0YzR4bXBFeHQvMjAwOC0wMi0yOS8iIHhtbG5zOnhtcFJpZ2h0cz0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3JpZ2h0cy8iIGRjOlJpZ2h0cz0iQXVudF9TcHJheSIgcGhvdG9zaG9wOkNyZWRpdD0iR2V0dHkgSW1hZ2VzL2lTdG9ja3Bob3RvIiBHZXR0eUltYWdlc0dJRlQ6QXNzZXRJRD0iNTExOTQ0MTkwIiB4bXBSaWdodHM6V2ViU3RhdGVtZW50PSJodHRwczovL3d3dy5nZXR0eWltYWdlcy5jb20vZXVsYT91dG1fbWVkaXVtPW9yZ2FuaWMmYW1wO3V0bV9zb3VyY2U9Z29vZ2xlJmFtcDt1dG1fY2FtcGFpZ249aXB0Y3VybCIgPgo8ZGM6Y3JlYXRvcj48cmRmOlNlcT48cmRmOmxpPkF1bnRfU3ByYXk8L3JkZjpsaT48L3JkZjpTZXE+PC9kYzpjcmVhdG9yPjxkYzpkZXNjcmlwdGlvbj48cmRmOkFsdD48cmRmOmxpIHhtbDpsYW5nPSJ4LWRlZmF1bHQiPkFuIGlsbHVzdHJhdGlvbiBvZiB0aGUgZXh0aW5jdCBEb2RvIEJpcmQgb24gYSB3aGl0ZSBiYWNrZ3JvdW5kLiBUaGUgZG9kbyAoUmFwaHVzIGN1Y3VsbGF0dXMpIGlzIGFuIGV4dGluY3QgZmxpZ2h0bGVzcyBiaXJkIHRoYXQgd2FzIGVuZGVtaWMgdG8gdGhlIGlzbGFuZCBvZiBNYXVyaXRpdXMsIGVhc3Qgb2YgTWFkYWdhc2NhciBpbiB0aGUgSW5kaWFuIE9jZWFuLjwvcmRmOmxpPjwvcmRmOkFsdD48L2RjOmRlc2Ny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">
 <div id="info"></div>
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
<script>


var n_projet = ""  ; 

for(var i = window.location.href.length-1 ; i>0 ; i-- ){
   
  if(window.location.href[i]=="/"){
    break ; 
  }

  n_projet = n_projet+window.location.href[i] ; 


}


var n_final = "" ; 
for(var i = n_projet.length-1; i>-1 ; i--){
 
  n_final = n_final+n_projet[i] ; 
 
 }


 

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
 

 


var URL = "";
if(nom_localisation=="localhost" || nom_localisation=="127.0.0.1"){
  URL ="http://"+nom_localisation+"/Model_Vue8/model/class/php/liste_projet.php/"+n_final;
}
else{
 
URL ="http://"+nom_localisation+"//model/class/php/liste_projet.php/"+n_final;
    
 
}
 
 
 var val_statu =false;
var xmlhttp = new XMLHttpRequest();
xmlhttp.onreadystatechange = function() {
  if (this.readyState == 4 && this.status == 200) {
  var myObj = JSON.parse(this.responseText);
 

 

  val_statu = myObj ; 
 
  //calculer = myObj[0][0].information_user_id_sha1.length ; 



//"http://localhost/Model_Vue8/model/class/php/liste_projet.php/"+n_final
  }
};
xmlhttp.open("GET", URL, true);
xmlhttp.send();    






const myTimeout = setTimeout(m404, 100);

function m404() {
 
   
   if(val_statu[0]=="404"){

 


    const para = document.createElement("img");
 
para.src = "https://www.happy-beez.net/wp-content/uploads/2018/09/erreur404.png";
document.getElementById("info").appendChild(para);



   }
   else{

 


var para = document.createElement("div");
para.innerHTML = "<h1>"+val_statu[0][0].liste_projet_description1+"</h1>";
para.className="page_t1" ;
para.id="page_t1" ;

document.getElementById("body").appendChild(para);



var para = document.createElement("img");
if(val_statu[0][0].liste_projet_img==""){

 para.src="https://images.pexels.com/photos/3705944/pexels-photo-3705944.jpeg?auto=compress&cs=tinysrgb&w=600";

}
else{
  if(nom_localisation=="localhost" || nom_localisation=="127.0.0.1"){
    para.src ="http://"+nom_localisation+"/Model_Vue8/login/src/img/all/"+val_statu[0][0].liste_projet_img;
}
else{
 
  para.src ="https://"+nom_localisation+"/login/src/img/all/"+val_statu[0][0].liste_projet_img;

    
 
}

}
document.getElementById("page_t1").appendChild(para);



var para = document.createElement("h3");
para.innerHTML = val_statu[0][0].liste_projet_description2;
document.getElementById("page_t1").appendChild(para);




var para = document.createElement("img");
 

if(nom_localisation=="localhost" || nom_localisation=="127.0.0.1"){
    para.src ="http://"+nom_localisation+"/Model_Vue8/login/src/img/all/qr_code/"+val_statu[0][0].liste_projet_id_sha1+".png";
}
else{
 
  para.src ="https://"+nom_localisation+"/login/src/img/all/qr_code/"+val_statu[0][0].liste_projet_id_sha1+".png";

    
 
}
 

document.getElementById("page_t1").appendChild(para);




var para = document.createElement("h4");
para.innerHTML = val_statu[0][0].liste_projet_reg_date;
document.getElementById("page_t1").appendChild(para);





 

 




console.log("traitement ok") ; 


if(val_statu[1][0]!="404"){



  for(var s= 0 ; s<val_statu[1].length ; s ++){

console.log(val_statu[1][s]) ; 

var para = document.createElement("div");
para.innerHTML = "<h1>"+val_statu[1][s].liste_projet_description1+"</h1>";
para.className="page_t1" ;
para.id="page_t1" ;
document.getElementById("page_t1").appendChild(para);



var para = document.createElement("a");
para.innerHTML = "<p>Voir article</p>";
para.className="page_t1" ;
para.href=val_statu[1][s].liste_projet_id_sha1 ;

para.id="page_t1" ;
document.getElementById("page_t1").appendChild(para);



var para = document.createElement("img");
if(val_statu[1][s].liste_projet_img==""){

para.src="https://images.pexels.com/photos/3705944/pexels-photo-3705944.jpeg?auto=compress&cs=tinysrgb&w=600";

}
else{
 if(nom_localisation=="localhost" || nom_localisation=="127.0.0.1"){
   para.src ="http://"+nom_localisation+"/Model_Vue8/login/src/img/all/"+val_statu[1][s].liste_projet_img;
}
else{
 para.src ="https://"+nom_localisation+"/login/src/img/all/"+val_statu[1][s].liste_projet_img;
}

}
document.getElementById("page_t1").appendChild(para);
var para = document.createElement("div");
para.innerHTML = "<h3>"+val_statu[1][s].liste_projet_description2+"</h3>";
document.getElementById("page_t1").appendChild(para);




var para = document.createElement("div");
para.innerHTML = "<h4>"+val_statu[1][s].liste_projet_reg_date+"</h4>";
document.getElementById("page_t1").appendChild(para);

 

  }

}
   }
}







 

 
</script>

 



<style>
  .page_t1{
    text-align:center ; 
    max-width: 60%;
    margin:auto ; 
  }
  .page_t1 img{
   margin-top:50px ; 
   max-width:60%;
  }
   .page_t1 h1{
 padding-top:50px;
  }
  .page_t1 h3{
color:rgba(0,0,0,0.3) ; 
margin-top:80px; 
text-align: justify;
font-size:1.2em; 
  }

  .page_t1 h4{
color:rgba(0,0,0,0.1) ; 
 
  }

  @media screen and (max-width: 1200px) {
    .page_t1{
    text-align:center ; 
    max-width: 90%;
    margin:auto ; 
  }
  .page_t1 img{
   margin-top:50px ; 
   max-width:90%;
  }
}
</style>

</body>


<script>
  /*
  var separation = 0 ; 
  var eman="";
  var name ="" ;
 


 for(i = 0 ; i<window.location.href.length; i++){

  if(window.location.href[i]=="/"){
    separation ++ ; 
  }
 if(separation>1){
  if(window.location.href[i]!="/"){
    eman = eman+window.location.href[i] ; 

  }
 }

 if(separation>2){
  break;
 }
 
 }
 console.log( eman) ; 

 if(eman=="localhost" || eman=="127.0.0.1" ){
  console.log("Nous sommes en local") ; 
 }
 */
</script>
</html>

</body>
</html>