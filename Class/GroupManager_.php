 <?php
/**
 * GroupManager_.php — Gestionnaire de groupes d'elements HTML / HTML element group manager
 * FR: Classe GroupManager pour orchestrer le rendu HTML, la generation JavaScript et l'envoi AJAX de formulaires
 * EN: GroupManager class to orchestrate HTML rendering, JavaScript generation and AJAX form submission
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
 

?>