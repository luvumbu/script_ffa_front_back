<?php
/**
 * getFileExtension.php — Detection d'extension et categorie de fichier / File extension and category detection
 * FR: Fonctions pour extraire l'extension d'un fichier et detecter sa categorie (image, video, musique)
 * EN: Functions to extract a file extension and detect its category (image, video, music)
 */

function getFileExtension($filepath) {
    return strtolower(substr(strrchr($filepath, '.'), 1));
}

 /* 

$ext = getFileExtension('uploads/1752175632_1752175654.jpg');
// $ext vaut "jpg"

*/
 

function detectFileCategory($input) {
    // Si le input contient un point, on prend l'extension après le dernier point
    if (strpos($input, '.') !== false) {
        $ext = strtolower(substr(strrchr($input, '.'), 1));
    } else {
        // Sinon, on suppose que c'est déjà une extension comme "jpg", "mp4", etc.
        $ext = strtolower($input);
    }

    switch ($ext) {
        // 📷 Images
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'bmp':
        case 'webp':
        case 'tiff':
        case 'tif':
        case 'svg':
        case 'ico':
        case 'heic':
        case 'raw':
        case 'psd':
        case 'ai':
        case 'eps':
            return 'image';

        // 🎥 Vidéos
        case 'mp4':
        case 'm4v':
        case 'mkv':
        case 'mov':
        case 'avi':
        case 'wmv':
        case 'flv':
        case 'f4v':
        case 'webm':
        case 'mpg':
        case 'mpeg':
        case '3gp':
        case '3g2':
        case 'ts':
        case 'vob':
        case 'ogv':
        case 'm2ts':
        case 'divx':
            return 'video';

        // 🎵 Musique / Audio
        case 'mp3':
        case 'wav':
        case 'aac':
        case 'ogg':
        case 'oga':
        case 'flac':
        case 'wma':
        case 'm4a':
        case 'aiff':
        case 'aif':
        case 'opus':
        case 'alac':
        case 'mid':
        case 'midi':
            return 'musique';

        // ❓ Autre
        default:
            return 'inconnu';
    }
}





/*

echo detectFileCategory('media/clip_2025.MP4');    // Affiche : video
echo detectFileCategory('audio/song_final.FLAC');  // Affiche : musique
echo detectFileCategory('img/photo_final.heic');   // Affiche : image
echo detectFileCategory('doc/presentation.pdf');   // Affiche : inconnu



*/



?>