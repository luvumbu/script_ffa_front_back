<?php
/**
 * Give_url.php — Extraction et analyse d'URL / URL extraction and parsing
 * FR: Classe pour extraire le nom du fichier courant depuis l'URL et le separer en elements
 * EN: Class to extract the current filename from the URL and split it into elements
 */
class Give_url {
    // Propriété pour stocker le nom de base du fichier
    public $basename;
    // Propriété pour stocker le tableau des éléments séparés
    public $elements = [];

    // Constructeur pour initialiser la propriété avec $_SERVER['PHP_SELF'] par défaut
    public function __construct($basename = null) {
        // Si aucune valeur n'est passée, utiliser $_SERVER['PHP_SELF']
        if ($basename === null) {
            $this->basename = basename($_SERVER['PHP_SELF']);
        } else {
            $this->basename = $basename;
        }
    }

    // Méthode pour définir la valeur de basename
    public function set_basename($basename) {
        $this->basename = $basename;
    }

    // Méthode pour récupérer la valeur de basename
    public function get_basename() {
        return $this->basename;
    }

    // Nouvelle méthode pour séparer la chaîne en utilisant un séparateur donné et stocker dans un tableau
    public function split_basename($separator) {
        // On utilise explode pour séparer le basename avec le séparateur
        $this->elements = explode($separator, $this->basename);
    }

    // Méthode pour récupérer le tableau des éléments séparés
    public function get_elements() {
        return $this->elements;
    }
}



/*
// Création d'une instance de la classe, avec $_SERVER['PHP_SELF'] par défaut
$url = new Give_url();
// Afficher le nom du fichier actuel
echo "Le nom du fichier actuel est : " . $url->get_basename() . "\n";
// Utilisation de la méthode split_basename pour séparer par "_"
$url->split_basename('_');
var_dump($url->get_elements()) ; 

*/
?>
