function    voir_group(){
    let   array_voir_group = [];
       var xmlhttp = new XMLHttpRequest();
var url = "http://localhost/Model_Vue8/login/class/php/php_on/voir_group.php";


xmlhttp.onreadystatechange = function() {
 if (this.readyState == 4 && this.status == 200) {
   var myArr = JSON.parse(this.responseText);
   
   console.log(myArr) ; 
   array_voir_group.push(myArr) ;

   

 }
};

xmlhttp.open("GET", url, true);
xmlhttp.send();



const myTimeout = setTimeout(myGreeting, 5000);

function myGreeting() {
   console.log(array_voir_group) ; 
}

   }