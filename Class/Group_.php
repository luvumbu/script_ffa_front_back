<?php
/**
 * Group_.php — Classe de groupe d'elements HTML / HTML element group class
 * FR: Classe Group pour regrouper des elements HTML avec gestion de flags et conversion tableau vers Element
 * EN: Group class to group HTML elements with flag management and array to Element conversion
 */

/**
 * Classe Group / Group Class
 * ------------
 * Represente un groupe logique d'elements HTML (`Element`).
 * Represents a logical group of HTML elements (`Element`).
 */
class Group
{
    /**
     * Liste des éléments du groupe
     * Chaque élément est une instance de Element
     */
    private array $elements = [];

    /**
     * Flag du groupe
     *
     * Ce flag peut servir à :
     * - activer / désactiver un groupe entier
     * - transmettre une information logique au GroupManager
     * - appliquer un comportement commun à tous les éléments
     *
     * (actuellement stocké pour extension future)
     */
    public bool $flag;

    /**
     * Constructeur du groupe
     *
     * @param bool $flag Flag logique du groupe (défaut: false)
     */
    public function __construct(bool $flag = false)
    {
        $this->flag = $flag;
    }

    /**
     * Ajoute un élément au groupe
     *
     * Accepte :
     * - soit un objet Element
     * - soit un tableau associatif (converti automatiquement en Element)
     *
     * @param Element|array $el Élément ou définition de l’élément
     * @return self              Permet le chaînage
     */
    public function addElement($el): self
    {
        // Si l’élément est défini sous forme de tableau,
        // on le convertit automatiquement en instance de Element
        if (is_array($el)) {
            $el = $this->arrayToElement($el);
        }

        // Ajout à la liste des éléments du groupe
        $this->elements[] = $el;

        return $this;
    }

    /**
     * Convertit un tableau associatif en objet Element
     *
     * Structure attendue du tableau :
     * [
     *   'tag'   => 'input',
     *   'attrs' => ['id' => 'email', 'type' => 'text'],
     *   'text'  => null,
     *   'self'  => true,
     *   'open'  => false,
     *   'close' => false,
     *   'flag'  => true
     * ]
     *
     * @param array $arr Définition de l’élément
     * @return Element   Instance construite
     */
    private function arrayToElement(array $arr): Element
    {
        return new Element(
            $arr['tag'],            // Nom de la balise HTML
            $arr['attrs'] ?? [],    // Attributs HTML
            $arr['text'] ?? null,   // Texte interne
            $arr['self'] ?? false,  // Balise auto-fermante
            $arr['open'] ?? false,  // Balise ouvrante seule
            $arr['close'] ?? false, // Balise fermante seule
            $arr['flag'] ?? false   // Flag logique pour le JS
        );
    }

    /**
     * Retourne tous les éléments du groupe
     *
     * Utilisé par le GroupManager pour :
     * - générer le HTML
     * - collecter les éléments flag=true
     *
     * @return Element[]
     */
    public function getElements(): array
    {
        return $this->elements;
    }
}


?>
