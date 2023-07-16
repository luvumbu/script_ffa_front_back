<?php
if ($_FILES["image"]["error"] == UPLOAD_ERR_OK) {
    $target = $_SERVER['DOCUMENT_ROOT'] . "/" . basename($_FILES["image"]["name"]);
    move_uploaded_file($_FILES["image"]["tmp_name"], $target);
    echo $target;
  }
  
?>