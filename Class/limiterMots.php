<?php
/**
 * limiterMots.php — Limitation du nombre de mots dans un texte / Limit word count in text
 * FR: Fonction qui tronque un texte a un nombre maximum de mots avec ajout de points de suspension
 * EN: Function that truncates text to a maximum number of words with ellipsis added
 */
function limiterMots($texte, $limite = 400) {
    // Séparer le texte en mots
    $mots = preg_split('/\s+/', trim($texte));
    
    // Vérifier si on dépasse la limite
    if (count($mots) > $limite) {
        $mots = array_slice($mots, 0, $limite); // Garder seulement les 400 premiers mots
        $texte = implode(' ', $mots) . '...'; // Reconstituer le texte avec "..."
    }
    
    return $texte;
}

/*

// Exemple d'utilisation
$contenu = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. ... (beaucoup de texte)";
?>

<div>
    <?= limiterMots($contenu, 400) ?>
</div>


*/