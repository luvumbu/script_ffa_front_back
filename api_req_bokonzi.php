<!DOCTYPE html>
<html>
<body>
 
<script>

var el1 ="get_result_users_nom_1_array_2" ; 
var el2 ="a" ; 
var limits="{0,1000}";
var xmlhttp = new XMLHttpRequest();
xmlhttp.onreadystatechange = function() {
  if (this.readyState == 4 && this.status == 200) {
    var myObj = JSON.parse(this.responseText);
    console.log(myObj)
  }
}; 

xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/{10,1000}[get_result_users_nom_1_array_2]/ARRON", true);

xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/%7B0,2%7D[get_result_users_nom_1_array_2]/ndenga", true);


xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/{0,2}[get_result_users_nom_1_array_2]/ndenga", true);
xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/"+limits+"["+el1+"]/"+el2, true);

xmlhttp.send();
</script>

 

</body>
</html>