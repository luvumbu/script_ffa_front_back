
<?php
/**
 * CheckFileExists.php — Verification d'existence de fichier / Check if a file exists
 * FR: Fonction utilitaire qui verifie si un fichier existe a un chemin donne
 * EN: Utility function that checks if a file exists at a given path
 */
function checkFileExists($filePath) {
    if (file_exists($filePath)) {
        return true; // The file exists
    } else {
        return false; // The file does not exist
    }
}

/*
// Example usage
$path = "path/to/your/file.php";
if (checkFileExists($path)) {
    echo "The file exists.";
} else {
    echo "The file does not exist.";
}
    */


 
 
?>
