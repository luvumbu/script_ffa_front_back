<?php
/**
 * getFilesFromDir.php — Recuperation des fichiers d'un dossier / Get files from a directory
 * FR: Fonction qui liste les fichiers d'un dossier avec possibilite d'exclure un fichier specifique
 * EN: Function that lists files in a directory with the ability to exclude a specific file
 */

function getFilesFromDir($dir, $excludeFile = null) {
    $result = [];

    if (is_dir($dir)) {
        $files = scandir($dir);

        foreach ($files as $file) {
            // Ignore les entrées système
            if ($file === '.' || $file === '..') continue;

            // Ignore le fichier spécifié s'il y en a un
            if ($excludeFile !== null && $file === $excludeFile) continue;

            // Ajoute le fichier au tableau résultat
            $result[] = $file;
        }
    } else {
        echo "⚠️ Le dossier '$dir' n'existe pas.";
    }

    return $result;
}


/*
// Exemple d'utilisation :
$dir = 'data/blog/footer/xhtml';
$section_2_pages_projet = 'index.html'; // exemple de fichier à ignorer

$fichiers = getFilesFromDir($dir, "");

// Affiche le tableau pour vérification
print_r($fichiers);
*/



?>