<?php
/**
 * Creat_form.php — Creation dynamique de formulaires HTML/JS / Dynamic HTML/JS form creation
 * FR: Classe pour creer dynamiquement des formulaires HTML avec generation de JavaScript et gestion de base de donnees
 * EN: Class to dynamically create HTML forms with JavaScript generation and database management
 */

require_once "CheckFileExists.php";
require_once "Delete_file.php";
require_once "DatabaseHandler.php";
// Définition d'une classe appelée `Creat_form`
class Creat_form
{
    // Propriétés privées pour stocker le nom du formulaire et le type d'élément HTML
    private $name_form;
    private $name_type;
    // Propriété publique pour stocker des informations sur les enfants du formulaire
    public $child_info = [];
    public $array_select_Atribut = [];
    public $nom_dossier;
    public $nom_fichier;
    public $path;
    public $config_bool = true;
    public $path_config = "class/path_config.php";
    public $bool_config = false;

    public $config_dbname  = false;
    public $config_password  = false;
    public $databaseHandler_verif = false;


    // Constructeur de la classe, qui initialise les propriétés et génère du code JavaScript pour créer un élément HTML
    function __construct($name_form, $name_type, $para)
    {
        // Initialisation des propriétés avec les valeurs fournies en argument
        $this->name_form = $name_form;
        $this->name_type = $name_type;
        // Génération de code JavaScript pour créer un nouvel élément HTML et lui attribuer un ID
        //$_SESSION["ok"]= time();
        $_SESSION["DatabaseHandler_switch"] = "DatabaseHandler_switch";
        if (CheckFileExists($this->path_config)) {
            require_once $this->path_config;
            $this->config_dbname  = $config_dbname;
            $this->config_password  = $config_password;
            //$databaseHandler = new DatabaseHandler($config_dbname, $config_password);
            $databaseHandler = new DatabaseHandler($config_dbname, $config_password);
            $this->databaseHandler_verif = $databaseHandler->verif;
            if ($this->databaseHandler_verif) {
            } else {

                if (!isset($_SESSION["session_log"])) {
                    delete_file($this->path_config);
                }
            }
        } else {
        }
?>
        <script>
            var envoyer = true;
            // Stocke le nom du formulaire dans une variable JavaScript
            var name_form_parent = "<?php echo $this->name_form ?>";
            // Crée un nouvel élément HTML du type spécifié
            var para = document.createElement("<?php echo $this->name_type ?>");
            // Assigne l'ID du formulaire au nouvel élément créé
            // Stocke le nom du formulaire dans une variable JavaScript
            var name_form_parent = "<?php echo $this->name_form ?>";
            // Crée un nouvel élément HTML du type spécifié
            var para = document.createElement("<?php echo $this->name_type ?>");
            // Assigne l'ID du formulaire au nouvel élément créé
            para.setAttribute("id", name_form_parent);
        </script>
        <?php
        // Si le paramètre `$para` est vide, l'élément est ajouté directement au corps du document
        if ($para == "") {
        ?>
            <script>
                // Ajoute l'élément directement au body
                document.body.appendChild(para);
            </script>
        <?php
        } else {
            // Sinon, l'élément est ajouté en tant qu'enfant d'un autre élément spécifié par `$para`
        ?>
            <script>
                // Ajoute l'élément comme enfant de l'élément spécifié
                document.getElementById("<?php echo $para ?>").appendChild(para);
            </script>
        <?php
        }
        ?>
    <?php
    }

    function stylesheet($source)
    {
    ?>
        <link rel="stylesheet" href="monFormulaire1.css">
    <?php
        echo '<link rel="stylesheet" href="' . $source . '">';
    }
    function select_Atribut_function()
    {
    ?>
        <script>
            var list_id = [];
            var list_name = [];
        </script>
        <?php
        for ($x = 0; $x < count($this->array_select_Atribut); $x++) {
        ?><script>
                var element1 = document.getElementById("<?php echo $this->array_select_Atribut[$x][0] ?>").id
                var element2 = "<?php echo $this->array_select_Atribut[$x][0] ?>";
                list_id.push(element1);
                list_name.push(element2);
            </script>
        <?php
        }
        ?>
        <script>
            //  onclick 

            function input_1_onkeyup(_this) {
                _this.style.opacity = 0.2;
                /*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
                const myTimeout_1 = setTimeout(myGreeting, 289);

                function myGreeting() {
                    if (envoyer) {
                        const myTimeout_2 = setTimeout(myGreeting_2, 550);
                    }
                    envoyer = false;
                }



                function myGreeting_2() {
                    _this.style.opacity = 1;

                    envoyer = true;
                
                    var ok = new Information("<?php echo $this->path ?>"); // création de la classe 
                    for (var y = 0; y < list_name.length; y++) {

                        var input_valu = document.getElementById(list_name[y]).value;
                        ok.add(list_name[y], input_valu); // ajout de l'information pour lenvoi 
                    }
                    console.log(ok.info()); // demande l'information dans le tableau
                    ok.push(); // envoie l'information au code pkp  
                    /*

                        var _title_projet = document.getElementById(_this.title + "_title_projet").value;
                        var _name_projet = document.getElementById(_this.title + "_name_projet").value;

                        console.log(_name_projet);
                        console.log(_title_projet);
                        var ok = new Information("../update/left_update.php"); // création de la classe 

                        ok.add("id_projet", _this.title); // ajout de l'information pour lenvoi 

                        ok.add("title_projet", _title_projet); // ajout de l'information pour lenvoi 
                        ok.add("name_projet", _name_projet); // ajout de l'information pour lenvoi 


                        console.log(ok.info()); // demande l'information dans le tableau
                        ok.push(); // envoie l'information au code pkp 
                    */
                   


                    const myTimeout = setTimeout(xxx, 300);

                    function xxx() {
                        location.reload();
                    }



                }
                /*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
                for (var xx = 0; xx < list_id.length; xx++) {
                    var talk = document.getElementById(list_id[xx]).value;
                    console.log("001 : " + talk);
                    console.log("002 : " + list_id[xx]);
                }
            }
        </script>
    <?php

        // var_dump($this->array_select_Atribut) ; 
    }

    function select_Atribut($value, $nom_dossier, $nom_fichier)
    {
        for ($a = 0; $a < count($this->child_info); $a++) {


            if ($this->child_info[$a][1] == $value) {
                $this->array_select_Atribut[] = array($this->child_info[$a][0], $this->child_info[$a][1]);
                //var_dump($this->array_select_Atribut) ; 

            }
        }





        $this->nom_dossier = $nom_dossier;

        $this->nom_fichier = $nom_fichier;
        $this->path = $nom_dossier . "/" . $nom_fichier;

        // Chemin complet du fichier


        // Vérifie si le dossier existe, sinon le créer
        if (!is_dir($nom_dossier)) {
            mkdir($nom_dossier, 0777, true);
        }

        // Ouvre le fichier en mode écriture (création ou écrasement)
        $fichier = fopen($this->path, 'w');

        if ($fichier) {


            if ($this->config_bool) {



                // Initialisation du contenu du fichier avec l'ouverture du tag PHP
                $contenu = "<?php\n";
                $contenu .= "session_start();";
                $contenu .= "\n";
                $contenu .= 'require_once "../class/DatabaseHandler.php";';
                $contenu .= "\n";


                // Boucle pour ajouter chaque élément de `$this->array_select_Atribut` au contenu
                for ($xa = 0; $xa < count($this->array_select_Atribut); $xa++) {
                    // Récupère la première valeur de l'élément courant dans `$this->array_select_Atribut`
                    $data_x = $this->array_select_Atribut[$xa][0];

                    // Ajoute la valeur récupérée au contenu du fichier, suivi d'une nouvelle ligne
                    $contenu .= '$' . $data_x . '=$_POST["' . $data_x . '"];';
                    $contenu .= "\n";
                }
                $contenu .= 'require_once "../DatabaseHandler/' . $this->nom_fichier . '";';
                $contenu .= "\n";
                // Ajoute la fermeture du tag PHP au contenu
                $contenu .= "?>\n";

                // Écrit le contenu dans le fichier

                fwrite($fichier, $contenu);
                // Ferme le fichier
                fclose($fichier);




                // Message de confirmation
            } else {
                // Message d'erreur en cas de problème avec l'ouverture du fichier
                echo "Erreur lors de la création du fichier.";
            }
        }
    }
    function add_child_array($list_array)
    {
        //var_dump($list_array) ; 
        for ($x = 0; $x < count($list_array); $x++) {
            if ($x == 0) {
                $this->add_child($list_array[$x][0], $list_array[$x][1]);
            } else {
                $this->child_setAtribut($list_array[$x][0], $list_array[$x][1], $list_array[$x][2]);
            }
        }
    }
    // Méthode pour ajouter un enfant au formulaire
    function add_child($name, $type)
    {
        // Ajoute un tableau contenant le nom et le type de l'enfant à la propriété `child_info`
        $this->child_info[] = array($name, $type);
    ?>
        <script>
            // Crée un nouvel élément HTML pour l'enfant
            var para = document.createElement("<?php echo $type ?>");
            // Attribue un ID à l'élément enfant
            para.setAttribute("id", "<?php echo $name ?>")
            // Ajoute l'enfant comme élément du formulaire parent
            document.getElementById("<?php echo $this->name_form ?>").appendChild(para);
        </script>
        <?php
    }

    // Méthode pour obtenir le nom du formulaire
    function get_name_form()
    {
        return $this->name_form;
    }

    // Méthode pour définir un attribut ou le contenu d'un élément enfant
    function child_setAtribut($id, $name_atribute, $value_atribute)
    {
        // Si `$name_atribute` est vide, l'attribut 'innerHTML' de l'élément est défini
        if ($name_atribute == "") {
        ?>
            <script>
                document.getElementById("<?php echo $id ?>").innerHTML = "<?php echo $value_atribute ?>"
            </script>
        <?php
        } else {
            // Sinon, l'attribut spécifié est défini avec la valeur donnée
        ?>
            <script>
                document.getElementById("<?php echo $id ?>").setAttribute("<?php echo $name_atribute; ?>", "<?php echo $value_atribute ?>")
            </script>
        <?php
        }
    }

    // Méthode pour définir un attribut ou le contenu de l'élément parent (formulaire)
    function construct_setAtribut($name_atribute, $value_atribute)
    {
        // Si `$name_atribute` est vide, le contenu de l'élément parent est défini
        if ($name_atribute == "") {
        ?>
            <script>
                document.getElementById("<?php echo $this->name_form ?>").innerHTML = "<?php echo $value_atribute ?>"
            </script>
        <?php
        } else {
            // Sinon, l'attribut spécifié est défini avec la valeur donnée pour l'élément parent
        ?>
            <script>
                document.getElementById("<?php echo $this->name_form ?>").setAttribute("<?php echo $name_atribute; ?>", "<?php echo $value_atribute ?>")
            </script>
<?php
        }
    }
}

/*
    Explications :
Constructeur (__construct) : Initialise le formulaire avec un nom et un type d'élément HTML. Le code génère ensuite un élément HTML correspondant via du JavaScript, et l'ajoute soit au body, soit à un élément parent spécifique.

add_child : Ajoute un enfant au formulaire en créant un nouvel élément HTML avec un ID et l'ajoutant comme enfant de l'élément formulaire principal.

child_setAtribut : Permet de définir un attribut spécifique ou le contenu (innerHTML) d'un enfant du formulaire.

construct_setAtribut : Similaire à child_setAtribut, mais cette méthode agit directement sur l'élément parent (le formulaire).

EXEMPLE 




*/


/* 


<?php
// Inclure la classe Creat_form si elle est définie dans un autre fichier
// require_once('Creat_form.php');

// Créer une nouvelle instance de Creat_form
$form = new Creat_form("monFormulaire", "form", "");

// Ajouter un champ de texte (input) comme enfant du formulaire
$form->add_child("nomInput", "input");

// Ajouter un bouton de soumission (submit) comme enfant du formulaire
$form->add_child("submitButton", "button");

// Définir des attributs pour les éléments enfants
$form->child_setAtribut("nomInput", "type", "text"); // Définit l'attribut type="text" pour l'input
$form->child_setAtribut("nomInput", "placeholder", "Entrez votre nom"); // Définit l'attribut placeholder="Entrez votre nom"
$form->child_setAtribut("submitButton", "", "Envoyer"); // Définit le contenu texte du bouton

// Ajouter un attribut au formulaire principal
$form->construct_setAtribut("method", "POST"); // Définit l'attribut method="POST" pour le formulaire

?>
*/
?>