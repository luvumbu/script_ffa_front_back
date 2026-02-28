<?php
/**
 * AsciiConverter2.php — Conversion ASCII et generation d'iframe YouTube / ASCII conversion and YouTube iframe generation
 * FR: Classe pour convertir ASCII en chaines, chaines en ASCII et generer des iframes YouTube a partir de liens encodes
 * EN: Class to convert ASCII to strings, strings to ASCII and generate YouTube iframes from encoded links
 */
class AsciiConverter2
{
    // Méthode pour convertir une chaîne de valeurs ASCII en chaîne de caractères
    public static function asciiToString($asciiString)
    {
        $asciiArray = array_map('trim', explode(',', $asciiString));

        $string = '';
        foreach ($asciiArray as $ascii) {
            if (is_numeric($ascii)) {
                $string .= chr((int)$ascii);
            }
        }
        return $string;
    }

    // Méthode pour convertir une chaîne de caractères en valeurs ASCII
    public static function stringToAscii($string)
    {
        $asciiArray = [];
        for ($i = 0; $i < strlen($string); $i++) {
            $asciiArray[] = ord($string[$i]);
        }
        return implode(',', $asciiArray);
    }

    // Méthode pour générer un iframe à partir d'un lien YouTube
    public static function generateYoutubeIframe($youtubeLink)
    {
        // Vérifier si le lien est un lien ASCII et le décoder
        if (preg_match('/^[\d, ]+$/', $youtubeLink)) {
            $youtubeLink = self::asciiToString($youtubeLink);
        }

        // Extraire l'ID de la vidéo YouTube
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $youtubeLink, $matches)) {
            $videoId = $matches[1];

            // Générer l'iframe
            return sprintf(
                '<iframe width="560" height="315" src="https://www.youtube.com/embed/%s" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
                htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8')
            );
        }

        // Retourner un message d'erreur si le lien est invalide
        return 'Lien YouTube invalide.';
    }
}

// Exemple d'utilisation
$asciiYoutubeLink = AsciiConverter2::stringToAscii("Terminator (The Terminator) est un film de science-fiction américain réalisé par James Cameron et sorti en 1984. Il met en scène Arnold Schwarzenegger, Michael Biehn et Linda Hamilton dans les rôles principaux.

Traitant du voyage dans le temps et de la menace que pourraient faire naitre des robots créés par une superintelligence issue de la singularité technologique, Terminator est devenu l'un des classiques du cinéma d'action et d’anticipation des années 1980. Si son succès commercial ou critique est resté incertain jusqu'à sa sortie en salles, le film fut néanmoins en tête du box-office américain pendant deux semaines. Il a aidé à lancer la carrière cinématographique de Cameron et à consolider celle de Schwarzenegger, confortant son statut de vedette de films d'action acquis en 1982 avec Conan le Barbare.");

echo "Lien encodé ASCII : $asciiYoutubeLink <br>";

// Décoder et générer l'iframe
$iframe = AsciiConverter2::generateYoutubeIframe($asciiYoutubeLink);

echo $iframe;
?>
