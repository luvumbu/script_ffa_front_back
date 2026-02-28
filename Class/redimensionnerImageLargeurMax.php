<?php
/**
 * redimensionnerImageLargeurMax.php — Redimensionnement par largeur maximale / Resize by maximum width
 * FR: Fonction qui redimensionne une image en definissant une largeur maximale tout en conservant le ratio
 * EN: Function that resizes an image by setting a maximum width while maintaining the aspect ratio
 */

function redimensionnerImageLargeurMax($source, $destination, $largeurMax) {
    // Vérifier si le fichier source existe
    if (!file_exists($source)) {
        throw new Exception("Le fichier source n'existe pas : " . $source);
    }

    // Charger l'image source
    $infosImage = getimagesize($source);
    if (!$infosImage) {
        throw new Exception("Impossible de lire les informations de l'image : " . $source);
    }

    $largeurOriginale = $infosImage[0];
    $hauteurOriginale = $infosImage[1];
    $typeImage = $infosImage[2];

    // Calcul du ratio pour ajuster la hauteur
    $ratioOriginal = $largeurOriginale / $hauteurOriginale;

    // Calculer la nouvelle largeur et la nouvelle hauteur
    $nouvelleLargeur = $largeurMax;
    $nouvelleHauteur = $largeurMax / $ratioOriginal;

    // Créer une nouvelle image à partir de l'image source
    switch ($typeImage) {
        case IMAGETYPE_JPEG:
            $imageSource = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $imageSource = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $imageSource = imagecreatefromgif($source);
            break;
        // Suppression du support WebP si ce n'est pas nécessaire
        // case IMAGETYPE_WEBP:
        //     $imageSource = imagecreatefromwebp($source);
        //     break;
        default:
            throw new Exception("Type d'image non pris en charge");
    }

    // Créer l'image redimensionnée
    $imageRedimensionnee = imagecreatetruecolor($nouvelleLargeur, $nouvelleHauteur);

    // Conserver la transparence pour les PNG, GIF
    if (in_array($typeImage, [IMAGETYPE_PNG, IMAGETYPE_GIF])) {
        imagecolortransparent($imageRedimensionnee, imagecolorallocatealpha($imageRedimensionnee, 0, 0, 0, 127));
        imagealphablending($imageRedimensionnee, false);
        imagesavealpha($imageRedimensionnee, true);
    }

    // Redimensionner l'image
    imagecopyresampled(
        $imageRedimensionnee,
        $imageSource,
        0, 0, 0, 0,
        $nouvelleLargeur, $nouvelleHauteur,
        $largeurOriginale, $hauteurOriginale
    );

    // Sauvegarder l'image redimensionnée
    switch ($typeImage) {
        case IMAGETYPE_JPEG:
            imagejpeg($imageRedimensionnee, $destination, 90); // 90 = qualité
            break;
        case IMAGETYPE_PNG:
            imagepng($imageRedimensionnee, $destination);
            break;
        case IMAGETYPE_GIF:
            imagegif($imageRedimensionnee, $destination);
            break;
        // Retirer ou commenter le cas WebP si non nécessaire
        // case IMAGETYPE_WEBP:
        //     imagewebp($imageRedimensionnee, $destination);
        //     break;
    }

    // Libérer la mémoire
    imagedestroy($imageSource);
    imagedestroy($imageRedimensionnee);
}


?>