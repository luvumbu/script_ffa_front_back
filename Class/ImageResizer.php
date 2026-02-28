<?php
/**
 * ImageResizer.php — Redimensionnement d'images / Image resizing
 * FR: Classe pour charger, redimensionner et sauvegarder des images (JPEG, PNG, GIF) en conservant le ratio
 * EN: Class to load, resize and save images (JPEG, PNG, GIF) while maintaining aspect ratio
 */
class ImageResizer
{
    private $image;
    private $width;
    private $height;
    private $imageType;

    // Constructeur : charge l'image et récupère ses dimensions
    public function __construct($filename)
    {
        $this->load($filename);
    }

    // Charge l'image en fonction de son type
    private function load($filename)
    {
        $imageInfo = getimagesize($filename);
        $this->width = $imageInfo[0];
        $this->height = $imageInfo[1];
        $this->imageType = $imageInfo[2];

        switch ($this->imageType) {
            case IMAGETYPE_JPEG:
                $this->image = imagecreatefromjpeg($filename);
                break;
            case IMAGETYPE_GIF:
                $this->image = imagecreatefromgif($filename);
                break;
            case IMAGETYPE_PNG:
                $this->image = imagecreatefrompng($filename);
                break;
            default:
                throw new Exception("Unsupported image type");
        }
    }

    // Redimensionne l'image tout en maintenant le rapport d'aspect
    public function resize($maxWidth, $maxHeight)
    {
        // Calculer le ratio de l'image originale
        $aspectRatio = $this->width / $this->height;

        // Calculer la nouvelle largeur et hauteur en conservant le rapport d'aspect
        if ($maxWidth / $maxHeight > $aspectRatio) {
            $newWidth = $maxHeight * $aspectRatio;
            $newHeight = $maxHeight;
        } else {
            $newWidth = $maxWidth;
            $newHeight = $maxWidth / $aspectRatio;
        }

        // Créer une nouvelle image avec les nouvelles dimensions
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Si l'image est PNG ou GIF, préserver la transparence
        if ($this->imageType == IMAGETYPE_PNG) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefill($newImage, 0, 0, $transparent);
        } elseif ($this->imageType == IMAGETYPE_GIF) {
            $transparentIndex = imagecolortransparent($this->image);
            if ($transparentIndex >= 0) {
                $transparentColor = imagecolorsforindex($this->image, $transparentIndex);
                $newTransparentIndex = imagecolorallocate($newImage, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
                imagecolortransparent($newImage, $newTransparentIndex);
            }
        }

        // Redimensionner l'image
        imagecopyresampled($newImage, $this->image, 0, 0, 0, 0, $newWidth, $newHeight, $this->width, $this->height);
        $this->image = $newImage;
        $this->width = $newWidth;
        $this->height = $newHeight;
    }

    // Sauvegarde l'image redimensionnée dans un fichier
    public function save($filename, $imageType = IMAGETYPE_JPEG, $compression = 75, $permissions = null)
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                imagejpeg($this->image, $filename, $compression);
                break;
            case IMAGETYPE_GIF:
                imagegif($this->image, $filename);
                break;
            case IMAGETYPE_PNG:
                imagepng($this->image, $filename);
                break;
            default:
                throw new Exception("Unsupported image type");
        }

        if ($permissions !== null) {
            chmod($filename, $permissions);
        }
    }

    // Affiche l'image directement dans le navigateur
    public function output($imageType = IMAGETYPE_JPEG)
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                header('Content-Type: image/jpeg');
                imagejpeg($this->image);
                break;
            case IMAGETYPE_GIF:
                header('Content-Type: image/gif');
                imagegif($this->image);
                break;
            case IMAGETYPE_PNG:
                header('Content-Type: image/png');
                imagepng($this->image);
                break;
            default:
                throw new Exception("Unsupported image type");
        }
    }

    // Libère la mémoire utilisée par l'image
    public function __destruct()
    {
        imagedestroy($this->image);
    }
}
?>

<?php
/*
$imageResizer = new ImageResizer('path/to/your/image.jpg'); // Charger l'image
$imageResizer->resize(800, 600); // Redimensionner l'image (800px de large, 600px de hauteur max)
$imageResizer->output(); // Afficher l'image dans le navigateur
*/
?>

<?php
/*
$imageResizer = new ImageResizer('path/to/your/image.jpg'); // Charger l'image
$imageResizer->resize(800, 600); // Redimensionner l'image
$imageResizer->save('path/to/save/resized_image.jpg'); // Sauvegarder l'image redimensionnée
*/
?>

