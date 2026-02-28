 <?php
/**
 * Element_.php — Classe d'element HTML generique / Generic HTML element class
 * FR: Classe Element pour representer et generer des elements HTML avec attributs, classes et rendu conditionnel
 * EN: Element class to represent and generate HTML elements with attributes, classes and conditional rendering
 */

/**
 * Classe Element / Element Class
 * ----------------
 * Represente un element HTML generique (input, div, span, form, etc.)
 * Represents a generic HTML element (input, div, span, form, etc.)
 */
class Element
{
    /**
     * Nom de la balise HTML
     * ex: div, input, span, form, button...
     */
    public string $tag;

    /**
     * Tableau associatif des attributs HTML
     * ex: ['id' => 'email', 'class' => 'form-control', 'value' => 'test']
     */
    public array $attrs;

    /**
     * Texte interne de l’élément (si balise non auto-fermante)
     * ex: <span>ICI</span>
     */
    public ?string $text;

    /**
     * Indique si la balise est auto-fermante
     * ex: <input />, <br />, <img />
     */
    public bool $self;

    /**
     * Indique si on génère uniquement la balise ouvrante
     * ex: <div>
     */
    public bool $open;

    /**
     * Indique si on génère uniquement la balise fermante
     * ex: </div>
     */
    public bool $close;

    /**
     * Flag logique utilisé par le GroupManager
     * true  → l’élément est pris en compte (ex: ajouté au JS / FormData)
     * false → l’élément est ignoré
     */
    public bool $flag;

    /**
     * Constructeur de l’élément HTML
     *
     * @param string      $tag   Nom de la balise HTML
     * @param array       $attrs Attributs HTML (id, class, value, etc.)
     * @param string|null $text  Texte interne éventuel
     * @param bool        $self  Balise auto-fermante
     * @param bool        $open  Générer uniquement la balise ouvrante
     * @param bool        $close Générer uniquement la balise fermante
     * @param bool        $flag  Flag logique pour le traitement JS
     */
    public function __construct(
        string $tag,
        array $attrs = [],
        ?string $text = null,
        bool $self = false,
        bool $open = false,
        bool $close = false,
        bool $flag = false
    ) {
        // Nom de la balise
        $this->tag = $tag;

        // Attributs HTML
        $this->attrs = $attrs;

        // Texte interne
        $this->text = $text;

        // Type de balise
        $this->self  = $self;
        $this->open  = $open;
        $this->close = $close;

        // Flag logique (utilisé par GroupManager)
        $this->flag = $flag;
    }

    /**
     * Ajoute ou modifie un attribut HTML
     *
     * @param string $key   Nom de l’attribut (id, class, value, etc.)
     * @param mixed  $value Valeur de l’attribut
     * @return self         Permet le chaînage (fluent interface)
     */
    public function setAttr(string $key, $value): self
    {
        $this->attrs[$key] = $value;
        return $this;
    }

    /**
     * Ajoute une classe CSS à l’attribut class
     * Gère automatiquement l’espace entre les classes
     *
     * ex:
     * ->addClass('btn')->addClass('btn-primary')
     * donne: class="btn btn-primary"
     *
     * @param string $class Nom de la classe CSS
     * @return self
     */
    public function addClass(string $class): self
    {
        // Si l’attribut class n’existe pas encore, on l’initialise
        if (!isset($this->attrs['class'])) {
            $this->attrs['class'] = '';
        }

        // Ajout propre avec espace si nécessaire
        $this->attrs['class'] .= ($this->attrs['class'] ? ' ' : '') . $class;

        return $this;
    }

    /**
     * Génère le HTML final de l’élément
     *
     * Gère automatiquement :
     * - les attributs
     * - les balises auto-fermantes
     * - les balises ouvrantes seules
     * - les balises fermantes seules
     *
     * @return string HTML généré
     */
    public function render(): string
    {
        // Construction de la chaîne des attributs HTML
        $attrStr = '';

        foreach ($this->attrs as $k => $v) {
            // Sécurisation contre l’injection HTML
            $attrStr .= ' '
                . htmlspecialchars($k)
                . '="'
                . htmlspecialchars($v)
                . '"';
        }

        // Cas 1 : balise auto-fermante OU uniquement ouvrante
        // ex: <input ...> ou <div>
        if ($this->self || $this->open) {
            return "<{$this->tag}{$attrStr}>";
        }

        // Cas 2 : balise fermante seule
        // ex: </div>
        if ($this->close) {
            return "</{$this->tag}>";
        }

        // Cas 3 : balise complète avec contenu
        // ex: <span>Texte</span>
        return "<{$this->tag}{$attrStr}>"
            . ($this->text ?? '')
            . "</{$this->tag}>";
    }
}


 
  ?>