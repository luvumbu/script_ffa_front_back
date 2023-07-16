<!DOCTYPE html>
<html>
<body>

<div id="id01"></div>

<script>
var xmlhttp = new XMLHttpRequest();
var url = "http://localhost/Model_Vue8/login/class/php/php_on/test.php";
 

xmlhttp.onreadystatechange = function() {
  if (this.readyState == 4 && this.status == 200) {
    var myArr = JSON.parse(this.responseText);
    
    console.log(myArr) ; 
    

  }
};

xmlhttp.open("GET", url, true);
xmlhttp.send();

 
</script>

</body>
</html>
