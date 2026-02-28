<?php
/**
 * CSSValidator.php — Validation syntaxique de code CSS / CSS code syntax validation
 * FR: Classe de validation syntaxique simple de code CSS, compatible PHP 5.4
 * EN: Simple CSS code syntax validation class, compatible with PHP 5.4
 */
class CSSValidator
{
    private $allowedProperties = array();
    private $lastError = '';

    public function __construct()
    {
        // Liste simplifiée (extensible)
        $this->allowedProperties = array(
            'color','background','background-color','border','border-color','border-width',
            'width','height','margin','padding','display','position','top','left','right','bottom',
            'font-size','font-weight','font-style','font-family','text-align','z-index',
            'flex','transform','transition','opacity','overflow','cursor'
        );
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Vérifie si le code CSS est valide
     * @param string $cssString
     * @return bool
     */
    public function isValid($cssString)
    {
        $this->lastError = '';
        $css = preg_replace('!/\*.*?\*/!s', '', $cssString);
        $css = trim($css);

        if ($css === '') {
            return $this->setError("Le CSS est vide.");
        }

        // Vérifie les accolades
        $openCount  = substr_count($css, '{');
        $closeCount = substr_count($css, '}');
        if ($openCount !== $closeCount) {
            return $this->setError("Les accolades ne sont pas équilibrées.");
        }

        preg_match_all('/([^{]+)\{([^}]+)\}/', $css, $matches, PREG_SET_ORDER);
        if (empty($matches)) {
            return $this->setError("Aucun bloc CSS valide trouvé.");
        }

        foreach ($matches as $block) {
            $selector = trim($block[1]);
            $body     = trim($block[2]);

            if (!preg_match('/^[a-zA-Z0-9\.\#\*\:\-\s\,\>\+\~\[\]\=\"\'\_]+$/', $selector)) {
                return $this->setError("Sélecteur invalide : $selector");
            }

            $lines = explode(';', $body);
            $propertiesUsed = array();

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;

                $parts = explode(':', $line, 2);
                if (count($parts) != 2) {
                    return $this->setError("Erreur de syntaxe : $line");
                }

                $prop = strtolower(trim($parts[0]));
                $val  = trim($parts[1]);

                if (!in_array($prop, $this->allowedProperties)) {
                    return $this->setError("Propriété non reconnue : $prop");
                }

                // Vérifie doublon (non bloquant mais détectable)
                if (isset($propertiesUsed[$prop])) {
                    // Pas une erreur CSS, mais on la note pour info
                    // $this->setError("Propriété dupliquée dans $selector : $prop");
                    // continue;
                }
                $propertiesUsed[$prop] = true;

                // Vérifie valeurs numériques avec unité
                if (preg_match('/(width|height|font-size|margin|padding|top|left|right|bottom)/', $prop)) {
                    if (!preg_match('/^(0|[0-9]+(\.[0-9]+)?(px|em|rem|%|vh|vw|vmin|vmax|pt|cm|mm|in))$/', $val)) {
                        return $this->setError("Valeur invalide pour $prop : '$val' (unité manquante ?)");
                    }
                }

                // Vérifie les couleurs
                if ($prop === 'color' || strpos($prop, 'background') === 0 || strpos($prop, 'border-color') === 0) {
                    if (!preg_match('/^(#[0-9a-fA-F]{3,8}|rgb[a]?\([^)]*\)|hsl[a]?\([^)]*\)|[a-zA-Z]+)$/', $val)) {
                        return $this->setError("Couleur invalide : $val");
                    }
                }

                if (strpos($val, '{') !== false || strpos($val, '}') !== false) {
                    return $this->setError("Accolade inattendue dans la valeur de $prop");
                }

                if (substr_count($val, '(') !== substr_count($val, ')')) {
                    return $this->setError("Parenthèses déséquilibrées dans la valeur de $prop");
                }
            }
        }

        return true;
    }

    private function setError($msg)
    {
        $this->lastError = $msg;
        return false;
    }
}
?>



<?php
 /*
$css = "
p {
  font-size: 16px;
  font-size: 16px;
  color: red;
}
div {
  width: 100%;
  height: 200px;
  background-color: #333;
}
";

$validator = new CSSValidator();
if ($validator->isValid($css)) {
    echo "✔️ CSS Valide";
} else {
    echo "❌ Erreur : " . $validator->getLastError();
}
    */
?>
