<?php
/**
 * addClassToNthElementFirstChar.php — Ajout de classe au premier caractere / Add class to first character
 * FR: Fonctions pour ajouter une classe CSS au premier caractere d'un element HTML cible par attribut
 * EN: Functions to add a CSS class to the first character of an HTML element targeted by attribute
 */
/**
 * Ajoute une classe au premier caractere d'un element cible par attribut / Adds a class to the first character of an element targeted by attribute
 *
 * @param string $html Le HTML à traiter
 * @param string $attribute Type d'attribut ('class', 'id', 'tag', ou n'importe quel attribut HTML)
 * @param string $value Valeur de l'attribut à chercher
 * @param string $firstClass La classe à ajouter au premier caractère
 * @param int $position Index de l'élément cible (0 = premier, 1 = deuxième, etc.)
 * @return string HTML modifié
 */
function addClassToFirstChar($html, string $attribute, string $value, string $firstClass, int $position = 0) {
    if (empty($html)) {
        return $html;
    }

    // Nettoyage préliminaire
    $html = str_replace('&nbsp;', ' ', $html);
    
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    
    // Charger le HTML
    @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    // Supprimer scripts et styles
    removeNodesByTagName($doc, 'script');
    removeNodesByTagName($doc, 'style');
    
    // Récupérer les éléments selon l'attribut
    $elements = getElementsByAttribute($doc, $attribute, $value);
    
    if (!empty($elements) && isset($elements[$position])) {
        $target = $elements[$position];
        
        // Trouver le premier nœud texte
        $firstTextNode = findFirstTextNode($target);
        
        if ($firstTextNode && trim($firstTextNode->textContent) !== '') {
            $text = $firstTextNode->textContent;
            $firstChar = mb_substr($text, 0, 1);
            $rest = mb_substr($text, 1);

            // Créer le span
            $span = $doc->createElement('span');
            $span->setAttribute('class', $firstClass);
            $span->appendChild($doc->createTextNode($firstChar));

            // Créer le fragment
            $fragment = $doc->createDocumentFragment();
            $fragment->appendChild($span);
            if ($rest !== '') {
                $fragment->appendChild($doc->createTextNode($rest));
            }

            // Remplacer
            if ($firstTextNode->parentNode) {
                $firstTextNode->parentNode->replaceChild($fragment, $firstTextNode);
            }
        }
    }

    return $doc->saveHTML();
}

/**
 * Supprime tous les nœuds d'un type donné
 */
function removeNodesByTagName($doc, $tagName) {
    $nodes = $doc->getElementsByTagName($tagName);
    $toRemove = [];
    foreach ($nodes as $node) {
        $toRemove[] = $node;
    }
    foreach ($toRemove as $node) {
        if ($node->parentNode) {
            $node->parentNode->removeChild($node);
        }
    }
}

/**
 * Récupère les éléments selon un attribut et sa valeur
 */
function getElementsByAttribute($doc, $attribute, $value) {
    $elements = [];
    $xpath = new DOMXPath($doc);
    
    // Par TAG (nom de balise)
    if ($attribute === 'tag') {
        $nodes = $doc->getElementsByTagName($value);
        foreach ($nodes as $node) {
            $elements[] = $node;
        }
        return $elements;
    }
    
    // Par ID
    if ($attribute === 'id') {
        $el = $doc->getElementById($value);
        if ($el) {
            $elements[] = $el;
        }
        return $elements;
    }
    
    // Par CLASS
    if ($attribute === 'class') {
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $value ')]");
        foreach ($nodes as $node) {
            $elements[] = $node;
        }
        return $elements;
    }
    
    // Par n'importe quel attribut
    $nodes = $xpath->query("//*[@{$attribute}='{$value}']");
    foreach ($nodes as $node) {
        $elements[] = $node;
    }
    
    return $elements;
}

/**
 * Trouve le premier nœud texte non vide (récursif)
 */
function findFirstTextNode($node) {
    if (!$node || !$node->childNodes) {
        return null;
    }
    
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMText && trim($child->textContent) !== '') {
            return $child;
        }
        if ($child instanceof DOMElement) {
            if (in_array($child->tagName, ['script', 'style', 'img'])) {
                continue;
            }
            $result = findFirstTextNode($child);
            if ($result !== null) {
                return $result;
            }
        }
    }
    return null;
}

 
?>