<?php 
header("Access-Control-Allow-Origin: *");
$filename_config ="config_folder_verif.php" ; 
$myfile = fopen($filename_config, "w") or die("Unable to open file!");
$txt = "off";
fwrite($myfile, $txt); 
fclose($myfile);
?>