<?php
/**
 * index copy.php — Systeme complet Group/GroupManager/Element avec exemple / Complete Group/GroupManager/Element system with example
 * FR: Contient les classes Group, GroupManager et Element pour generer dynamiquement des formulaires HTML avec envoi AJAX, avec un exemple complet
 * EN: Contains Group, GroupManager and Element classes to dynamically generate HTML forms with AJAX submission, with a complete example
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




/**
 * Classe GroupManager
 * -------------------
 * Cœur du système.
 *
 * Cette classe orchestre :
 * - le rendu HTML des groupes d’éléments
 * - la génération automatique du JavaScript
 * - la collecte des valeurs des éléments (flag=true)
 * - l’envoi final des données via FormData + AJAX
 *
 * Elle agit comme un mini framework PHP → JS → PHP.
 */
class GroupManager
{
    /**
     * Liste des groupes gérés
     * Chaque groupe contient plusieurs Element
     */
    private array $groups = [];

    /**
     * Liste interne des IDs HTML déjà utilisés
     * Permet d’éviter les doublons d’ID dans le DOM
     */
    private array $ids = [];

    /**
     * Nom de la variable JavaScript utilisée côté client
     *
     * Par défaut : ok
     * Exemple personnalisé : formData
     *
     * Ce nom est utilisé pour :
     * - ajouter des valeurs (formData.add)
     * - envoyer les données (formData.push)
     */
    private string $jsVarName;

    /**
     * Constructeur du GroupManager
     *
     * @param string $jsVarName Nom de la variable JS (défaut : "ok")
     */
    public function __construct(string $jsVarName = 'ok')
    {
        // Sécurisation du nom JS (évite l’injection JS)
        $this->jsVarName = preg_replace('/[^a-zA-Z0-9_]/', '', $jsVarName);
    }

    /**
     * Ajoute un groupe au manager
     *
     * - Vérifie l’unicité des ID HTML
     * - Empêche les conflits DOM
     * - Stocke le groupe pour traitement ultérieur
     *
     * @param Group $group Groupe à ajouter
     * @return self        Permet le chaînage
     *
     * @throws Exception si un ID est dupliqué
     */
    public function addGroup(Group $group): self
    {
        // Vérification des ID HTML du groupe
        foreach ($group->getElements() as $el) {
            if (!empty($el->attrs['id'])) {

                // Si l’ID existe déjà → erreur critique
                if (in_array($el->attrs['id'], $this->ids, true)) {
                    throw new Exception(
                        "Erreur : l'ID '{$el->attrs['id']}' existe déjà !"
                    );
                }

                // Enregistrement de l’ID
                $this->ids[] = $el->attrs['id'];
            }
        }

        // Ajout du groupe à la liste
        $this->groups[] = $group;

        return $this;
    }

    /**
     * Génère le HTML final
     *
     * Règles :
     * - les balises open / close sont toujours rendues
     * - les autres éléments ne sont rendus que si flag=true
     *
     * @return string HTML généré
     */
    public function render(): string
    {
        $html = '';

        foreach ($this->groups as $group) {
            foreach ($group->getElements() as $el) {

                // Balise ouvrante ou fermante → toujours rendue
                if ($el->open || $el->close) {
                    $html .= $el->render();

                // Élément normal → rendu conditionnel
                } elseif ($el->flag) {
                    $html .= $el->render();
                }
            }
        }

        return $html;
    }

    /**
     * Génère dynamiquement le JavaScript :
     *
     * - définition de la classe JS Information (si absente)
     * - création de l’instance JS
     * - ajout automatique des éléments flag=true
     *
     * @param string $sourcePhp Script PHP recevant le POST AJAX
     */
    public function generateJsInformation(string $sourcePhp): void
    {
        $js = $this->jsVarName;

        echo "<script>\n";

        /*
        |--------------------------------------------------------------------------
        | Définition de la classe JS Information (une seule fois)
        |--------------------------------------------------------------------------
        */
        echo "if (typeof window.Information === 'undefined') {\n";
        echo "window.Information = class {\n";
        echo "constructor(link){\n";
        echo "this.link = link;\n";
        echo "this.identite = new FormData();\n";
        echo "this.req = new XMLHttpRequest();\n";
        echo "this.identite_tab = [];\n";
        echo "}\n";
        echo "info(){ return this.identite_tab; }\n";
        echo "add(info,text){ this.identite_tab.push([info,text]); }\n";
        echo "push(){\n";
        echo "for(let i=0;i<this.identite_tab.length;i++){\n";
        echo "this.identite.append(this.identite_tab[i][0],this.identite_tab[i][1]);\n";
        echo "}\n";
        echo "this.req.open('POST',this.link);\n";
        echo "this.req.send(this.identite);\n";
        echo "console.log(this.req);\n";
        echo "}\n";
        echo "};\n";
        echo "}\n\n";

        /*
        |--------------------------------------------------------------------------
        | Création de l’instance JavaScript
        |--------------------------------------------------------------------------
        */
        echo "if (typeof {$js} === 'undefined') {\n";
        echo "var {$js} = new window.Information('{$sourcePhp}');\n";
        echo "}\n\n";

        /*
        |--------------------------------------------------------------------------
        | Ajout AUTOMATIQUE des éléments flag=true
        |--------------------------------------------------------------------------
        */
        foreach ($this->groups as $group) {
            foreach ($group->getElements() as $el) {
                if ($el->flag && !empty($el->attrs['id'])) {

                    // ID HTML = clé POST
                    $id = addslashes($el->attrs['id']);

                    // Valeur associée (ex: value d’un input)
                    $value = addslashes($el->attrs['value'] ?? '');

                    echo "{$js}.add('{$id}','{$value}');\n";
                }
            }
        }

        echo "console.log('Valeurs ajoutées automatiquement :', {$js}.info());\n";
        echo "</script>\n";
    }

    /**
     * Ajoute manuellement une valeur au FormData JS
     *
     * Utilisé pour :
     * - données système
     * - configuration backend
     * - actions (insert_safe, table, uniqueColumn, etc.)
     *
     * @param string $id    Clé POST
     * @param string $value Valeur associée
     */
    public function addJsElement(string $id, string $value): void
    {
        $js = $this->jsVarName;
        $valueEscaped = addslashes($value);

        echo "<script>\n";
        echo "if (typeof {$js} !== 'undefined') {\n";
        echo "    {$js}.add('{$id}', '{$valueEscaped}');\n";
        echo "} else {\n";
        echo "    console.warn('La variable {$js} n\\'existe pas encore.');\n";
        echo "}\n";
        echo "</script>\n";
    }

    /**
     * Envoi FINAL des données
     *
     * Appelle toujours :
     *   jsVarName.push()
     *
     * Ce point centralise l’envoi AJAX et évite
     * les soumissions multiples ou incohérentes.
     */
    public function pushJs(): void
    {
        $js = $this->jsVarName;

        echo "<script>\n";
        echo "if (typeof {$js} !== 'undefined') {\n";
        echo "    {$js}.push();\n";
        echo "} else {\n";
        echo "    console.warn('La variable {$js} n\\'existe pas encore.');\n";
        echo "}\n";
        echo "</script>\n";
    }
}




/**
 * Classe Element
 * ----------------
 * Représente un élément HTML générique (input, div, span, form, etc.)
 * Cette classe est utilisée comme brique de base pour construire dynamiquement
 * du HTML côté PHP, avec contrôle fin du rendu et du comportement JS.
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


 
 

/* ==============================
   GROUPE PRINCIPAL
================================ */
$group = new Group(false);

/* ==============================
   DIV CONTAINER PRINCIPAL
================================ */
$group->addElement([
    'tag'   => 'div',
    'attrs' => ['id' => 'main_container'],
    'open'  => true,
    'flag'  => false
]);

/* ==============================
   TITRE DU FORMULAIRE
================================ */
$group->addElement([
    'tag'   => 'h1',
    'attrs' => ['class'=>"h1_tag"],
    'text'  => 'Mon formulaire',
    'flag'  => true
]);

/* ==============================
   BLOC EMAIL
================================ */
$group->addElement([
    'tag'  => 'div',
    'open' => true
]);

$group->addElement([
    'tag'   => 'label',
    'attrs' => ['for' => 'mon_id_1'],
    'text'  => 'EMAIL',
    'flag'  => false
]);

$group->addElement([
    'tag'   => 'input',
    'attrs' => [
        'type'        => 'text',
        'id'          => 'mon_id_1',
        'placeholder' => 'adresse mail'
    ],
    'self' => true,
    'flag' => true
]);

$group->addElement([
    'tag'   => 'div',
    'close' => true
]);

/* ==============================
   BLOC PASSWORD
================================ */
$group->addElement([
    'tag'  => 'div',
    'open' => true
]);

$group->addElement([
    'tag'   => 'label',
    'attrs' => ['for' => 'mon_id_2'],
    'text'  => 'Password',
    'flag'  => false
]);

$group->addElement([
    'tag'   => 'input',
    'attrs' => [
        'type'        => 'password', // caché
        'id'          => 'mon_id_2',
        'placeholder' => 'mot de passe',
        'value'=>'MOOOOOOOOON'
    ],
    'self' => true,
    'flag' => true
]);

$group->addElement([
    'tag'   => 'div',
    'close' => true
]);

/* ==============================
   LIEN MOT DE PASSE OUBLIÉ
================================ */
$group->addElement([
    'tag'   => 'a',
    'attrs' => ['href' => 'password_forgot.php'],
    'text'  => 'mot de passe oublié',
    'flag'  => false // ne sera pas collecté en JS
]);

/* ==============================
   BOUTON ENVOI
================================ */
$group->addElement([
    'tag'   => 'button',
    'attrs' => [
        'type'    => 'button',
        'onclick' => 'push()'
    ],
    'text'  => 'Envoyer',
    'flag'  => false
]);

/* ==============================
   FERMETURE CONTAINER
================================ */
$group->addElement([
    'tag'   => 'div',
    'close' => true
]);

$manager = new GroupManager('formData');
$manager->addGroup($group);

/* Génère la variable JS formData + tous les éléments flag=true */
echo $manager->render();
$manager->generateJsInformation('traitement.php');

/* Maintenant formData existe, on peut ajouter des valeurs manuelles */
$manager->addJsElement('action', 'login');
$manager->addJsElement('token', 'abc123');

/* Envoi final */
$manager->pushJs();



?>

<style>
/* ===== TITRE ===== */
.h1_tag {
    text-align: center;
    margin-bottom: 20px;
    font-size: 24px;
    color: #1877f2;
}

/* ===== CONTAINER ===== */
#main_container {
    width: 360px;
    margin: 50px auto;
    padding: 25px 20px;
    background-color: #f0f2f5;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    font-family: Arial, sans-serif;
}

/* Chaque div contenant un input */
#main_container > div {
    margin-bottom: 15px;
}

/* ===== INPUTS ===== */
#main_container input[type="text"],
#main_container input[type="password"] {
    width: 100%;
    padding: 12px 15px;
    font-size: 14px;
    border: 1px solid #ddd;
    border-radius: 6px;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.2s, box-shadow 0.2s;
}

/* Focus */
#main_container input:focus {
    border-color: #1877f2;
    box-shadow: 0 0 3px rgba(24,119,242,0.6);
}

/* Placeholder gris clair */
#main_container input::placeholder {
    color: #999;
}

/* ===== LIEN MOT DE PASSE OUBLIÉ ===== */
#main_container a {
    display: block;
    font-size: 13px;
    color: #1877f2;
    text-decoration: none;
    margin-bottom: 15px;
    text-align: right;
    transition: color 0.2s;
}

#main_container a:hover {
    text-decoration: underline;
    color: #145dbf;
}

/* ===== BOUTON ===== */
#main_container button {
    width: 100%;
    padding: 12px;
    background-color: #1877f2;
    color: #fff;
    font-size: 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.2s;
}

#main_container button:hover {
    background-color: #145dbf;
}
</style>
