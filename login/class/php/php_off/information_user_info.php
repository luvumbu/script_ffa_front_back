<?php 
session_start();
header("Access-Control-Allow-Origin: *");
?>

 

<div  role="alert" id="information_user_info_log" title="<?php echo $_SESSION["information_user_info"] ?> ">
  <?php 

echo $_SESSION["information_user_info"] ;


?>
</div>
 

<style>
  .text_center{
    text-align:center;
    width:300px;
    margin:auto ; 
  }
</style>