<?php
/**
 * html_brut.php — Conversion HTML en texte brut et stylisation du premier caractere / HTML to plain text and first character styling
 * FR: Fonctions pour convertir du HTML en texte brut ASCII et ajouter une balise stylee autour du premier caractere
 * EN: Functions to convert HTML to plain ASCII text and add a styled tag around the first character
 */
// Fonction pour convertir HTML en texte brut / Function to convert HTML to plain text
function html_vers_texte_brut($html) {
    // Supprime toutes les balises HTML
    $texte = strip_tags($html);
    
    // Décode les entités HTML
    $texte = html_entity_decode($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    $resultat = '';
    $longueur = strlen($texte);
    $premier_trouve = false;
    
    for ($i = 0; $i < $longueur; $i++) {
        $caractere = $texte[$i];
        
        // Vérifier si c'est un vrai caractère (lettre, chiffre, ponctuation visible)
        if (ctype_print($caractere) && $caractere !== ' ' && $caractere !== "\t" && $caractere !== "\n" && $caractere !== "\r") {
            $premier_trouve = true;
        }
        
        // Une fois le premier caractère trouvé, on prend tout ce qui est imprimable
        if ($premier_trouve) {
            // Garde seulement les caractères ASCII imprimables
            if ($caractere >= ' ' && $caractere <= '~') {
                $resultat .= $caractere;
            } else if ($caractere === "\n" || $caractere === "\r" || $caractere === "\t") {
                $resultat .= ' '; // Remplacer par espace
            }
        }
    }
    
    // Supprime les espaces multiples
    $resultat = preg_replace('/\s+/', ' ', $resultat);
    
    // Supprime les espaces au début et à la fin
    $resultat = trim($resultat);
    
    return $resultat;
}

// Fonction pour ajouter une balise autour du premier caractère
function html_premier_caractere($html, $balise, $nom_classe) {
    // Utiliser une regex pour trouver le premier caractère de texte
    // et l'entourer de la balise choisie avec la classe
    $resultat = preg_replace_callback(
        '/>(.)/',  // Cherche le premier caractère après une balise >
        function($matches) use ($balise, $nom_classe) {
            static $premier_trouve = false;
            
            // Si c'est le premier caractère trouvé
            if (!$premier_trouve && trim($matches[1]) !== '') {
                $premier_trouve = true;
                return '><' . $balise . ' class="' . $nom_classe . '">' . $matches[1] . '</' . $balise . '>';
            }
            
            return $matches[0];
        },
        $html,
        1  // Remplacer seulement la première occurrence
    );
    
    return $resultat;
}
/*
// D'abord nettoyer le texte en gardant le HTML
$html_nettoye = html_vers_texte_brut($html);

// Ensuite entourer d'une div pour avoir une structure HTML
$html_avec_structure = '<div>' . $html_nettoye . '</div>';

// Enfin ajouter le span au premier caractère
$html_final = html_premier_caractere($html_avec_structure, 'span', 'rouge');
*/


?>