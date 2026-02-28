 <?php
/**
 * AsciiConverter.php — Conversion ASCII et filtrage de chaines / ASCII conversion and string filtering
 * FR: Classe pour convertir entre ASCII et chaines de caracteres, filtrer et normaliser les espaces, avec tests unitaires
 * EN: Class to convert between ASCII and strings, filter and normalize whitespace, with unit tests
 */
class AsciiConverter
{
    // ======================================================
    // Conversion ASCII → String
    // ======================================================
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

    public static function asciiToStringInfo()
    {
        return "asciiToString(\$asciiString) : Convertit une chaîne de nombres ASCII séparés par des virgules en chaîne de caractères.\n"
             . "Exemple : asciiToString('72, 101, 108, 108, 111') → 'Hello'";
    }

    // ======================================================
    // Conversion String → ASCII
    // ======================================================
    public static function stringToAscii($string)
    {
        $asciiArray = [];
        for ($i = 0; $i < strlen($string); $i++) {
            $asciiArray[] = ord($string[$i]);
        }
        return implode(',', $asciiArray);
    }

    public static function stringToAsciiInfo()
    {
        return "stringToAscii(\$string) : Convertit une chaîne de caractères en valeurs ASCII séparées par des virgules.\n"
             . "Exemple : stringToAscii('Hello') → '72,101,108,108,111'";
    }

    // ======================================================
    // Filtrage String : lettres + espace/tabulation
    // ======================================================
    public static function filterString($string)
    {
        return preg_replace('/[^a-zA-Z\s\t]/', '', $string);
    }

    public static function filterStringInfo()
    {
        return "filterString(\$string) : Ne conserve que les lettres a-z, A-Z, les espaces et les tabulations.\n"
             . "Exemple : filterString('Hello 123 ! \\tWorld#') → 'Hello   World'";
    }

    // ======================================================
    // Normalisation des espaces/tabulations
    // ======================================================
    public static function normalizeWhitespace($string)
    {
        return preg_replace('/\s+/', ' ', $string);
    }

    public static function normalizeWhitespaceInfo()
    {
        return "normalizeWhitespace(\$string) : Transforme toutes les séquences d'espaces et tabulations en un seul espace.\n"
             . "Exemple : normalizeWhitespace('Hello   \\tWorld') → 'Hello World'";
    }
}

/**
 * ============================================================
 *  CLASS : AsciiConverterTest
 *  DESCRIPTION : Tests unitaires pour AsciiConverter
 * ============================================================
 */
class AsciiConverterTest
{
    private static $successCount = 0;
    private static $failCount = 0;

    private static function assertEquals($expected, $actual, $testName)
    {
        $ok = ($expected === $actual);
        $color = $ok ? 'green' : 'red';
        echo "<div style='color:white;background:$color;padding:10px;margin:5px;border-radius:6px;'>";
        echo ($ok ? '✅ ' : '❌ ') . "<b>$testName</b> — ";
        if (!$ok) {
            echo "Attendu : <b>$expected</b> | Obtenu : <b>$actual</b>";
            self::$failCount++;
        } else {
            echo "OK";
            self::$successCount++;
        }
        echo "</div>";
    }

    public static function runTests()
    {
        echo "<div style='font-family:Arial;background:#111;padding:20px;border-radius:10px;color:#fff;'>";
        echo "<h2>Résultats des tests — AsciiConverter</h2>";

        $inputAscii = "72, 101, 108, 108, 111";
        $inputString = "Hello";

        self::assertEquals('Hello', AsciiConverter::asciiToString($inputAscii), 'ASCII → String');
        self::assertEquals('72,101,108,108,111', AsciiConverter::stringToAscii($inputString), 'String → ASCII');
        self::assertEquals('Hello', AsciiConverter::asciiToString(' 72 , 101 ,108 ,108 , 111 '), 'Espaces multiples');
        self::assertEquals('Hlo', AsciiConverter::asciiToString('72,ABC,108,hello,111'), 'Valeurs non numériques ignorées');

        $roundTrip = 'Hello';
        $ascii = AsciiConverter::stringToAscii($roundTrip);
        self::assertEquals($roundTrip, AsciiConverter::asciiToString($ascii), 'Aller-retour String ↔ ASCII');

        self::assertEquals('', AsciiConverter::asciiToString(''), 'Chaîne vide ASCII → String');
        self::assertEquals('', AsciiConverter::stringToAscii(''), 'Chaîne vide String → ASCII');

        self::assertEquals('64,35', AsciiConverter::stringToAscii('@#'), 'Caractères spéciaux @# → ASCII');
        self::assertEquals('@#', AsciiConverter::asciiToString('64,35'), 'ASCII → Caractères spéciaux @#');

        // Test de filterString avec normalisation
        $testString = "Hello 123 ! \tWorld#";
        $filtered = AsciiConverter::filterString($testString);
        $normalized = AsciiConverter::normalizeWhitespace($filtered);
        self::assertEquals("Hello World", $normalized, 'FilterString + normalizeWhitespace');

        echo "<hr style='border:1px solid #555;'>";
        echo "<p style='color:#ccc;'>Tests réussis : <b style='color:lightgreen;'>" . self::$successCount . "</b> | ";
        echo "Échecs : <b style='color:red;'>" . self::$failCount . "</b></p>";
        echo "</div>";
    }
}

/*

// ---------- Exécution ----------
AsciiConverterTest::runTests();

 
echo AsciiConverter::asciiToStringInfo() . "\n\n";
echo AsciiConverter::stringToAsciiInfo() . "\n\n";
echo AsciiConverter::filterStringInfo() . "\n\n";
echo AsciiConverter::normalizeWhitespaceInfo();
 
?>

<?php
// 1️⃣ ASCII → String
$ascii = "72,101,108,108,111";
echo AsciiConverter::asciiToString($ascii); 
// Résultat : Hello

// 2️⃣ String → ASCII
$str = "Hello";
echo AsciiConverter::stringToAscii($str); 
// Résultat : 72,101,108,108,111

// 3️⃣ Filtrage : lettres + espace/tabulation
$dirty = "Hello 123 ! \tWorld#";
echo AsciiConverter::filterString($dirty); 
// Résultat : Hello    World

// 4️⃣ Normalisation des espaces/tabulations
$messy = "Hello   \tWorld";
echo AsciiConverter::normalizeWhitespace($messy); 
// Résultat : Hello World

// 5️⃣ Combinaison : filtrage + normalisation
$input = "Hi 123!   \tThere";
$clean = AsciiConverter::normalizeWhitespace(AsciiConverter::filterString($input));
echo $clean;
// Résultat : Hi There

// 6️⃣ Infos intégrées pour chaque fonction
echo AsciiConverter::asciiToStringInfo();
echo "\n\n";
echo AsciiConverter::stringToAsciiInfo();
echo "\n\n";
echo AsciiConverter::filterStringInfo();
echo "\n\n";
echo AsciiConverter::normalizeWhitespaceInfo();

*/
?>
