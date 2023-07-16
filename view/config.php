  <form  id="form-group" >
  <div class="form-group">
    <label for="dbname">DB NAME</label>
    <input type="text" class="form-control" id="dbname"  placeholder="Enter dbname">  
  </div>
  <div class="form-group">
    <label for="dbname">UserName</label>
    <input type="text" class="form-control" id="username"  placeholder="Enter dbname">  
  </div>
  <div class="form-group">
    <label for="exampleInputPassword1">Password</label>
    <input type="password" class="form-control" id="password" placeholder="Password">
  </div> 

  <label for="myCheck" class="myCheck">
    <h1>
      <b>
          Framework Web :
    </b> </h1></label> 
<input type="checkbox" id="myCheck" class="myCheck2" style=" width:40px; heigth: 40px; padding:40px">


<div type="submit" class="btn btn-primary cursor-pointer" id="submit" onclick="bdd_exe_config()">Submit</div>

</form>
<link rel="stylesheet" href="model/css/config.css">
<p>
  <a href="readme/index.php">besoin d'aide cliquez ici</a>
</p>
 <script src="model/class/js/bdd_exe_config.js"></script>
 <?php 
 if(isset($_SESSION["alpha"])){
  // Indixation utilisateur puis perte de saission 
  session_destroy(); 
 }
 ?>


<style>
  /*
  .myCheck{
    padding:15px;
  }
  .myCheck2{
    padding:15px;
    background-color:red;
    width:40px;
  }
  */
</style>


 

 