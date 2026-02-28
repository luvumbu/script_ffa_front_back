<?php
/**
 * removeHtmlTags_asciiToString.php — Conversion ASCII et nettoyage HTML / ASCII conversion and HTML cleaning
 * FR: Classe qui convertit des valeurs ASCII en texte et supprime les balises HTML
 * EN: Class that converts ASCII values to text and removes HTML tags
 */

class removeHtmlTags_asciiToString
{
    // Convertit une chaîne ASCII en texte sans nettoyage
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

    // Convertit une chaîne de texte en valeurs ASCII
    public static function stringToAscii($string)
    {
        $asciiArray = [];
        for ($i = 0; $i < strlen($string); $i++) {
            $asciiArray[] = ord($string[$i]);
        }
        return implode(',', $asciiArray);
    }

    // Supprime les balises HTML d'une chaîne
    public static function removeHtmlTags($input)
    {
        return strip_tags($input);
    }

    // Convertit une chaîne ASCII en texte et nettoie le HTML
    public static function asciiToCleanString($asciiString)
    {
        $text = self::asciiToString($asciiString);
        return self::removeHtmlTags($text);
    }
}

// 🧪 Exemples d’utilisation :


/*
$asciiHtml = "60,104,49,72,101,108,108,111,60,47,104,49,62"; // <h1>Hello</h1>

// Option 1 : Conversion brute
$rawText = removeHtmlTags_asciiToString::asciiToString($asciiHtml);
echo "Texte brut      : " . $rawText . "\n"; // Affiche : <h1>Hello</h1>

// Option 2 : Conversion + nettoyage HTML
$cleanText = removeHtmlTags_asciiToString::asciiToCleanString($asciiHtml);
echo "Texte nettoyé   : " . $cleanText . "\n"; // Affiche : Hello

?>
