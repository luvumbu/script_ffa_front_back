<?php
/**
 * Div_page.php — Gestion hierarchique des pages de projet / Hierarchical project page management
 * FR: Classe pour gerer l'arborescence des pages de projet avec relations parent-enfant et generation HTML
 * EN: Class to manage project page tree structure with parent-child relationships and HTML generation
 */

class Div_page
{
  // Tableau pour stocker les noms des enfants de la page
  public $div_page_child_name = array();
  public $div_page_child_title = array();
  public $div_page_child_description = array();

  public $div_page_child_parent = array();
  public $div_page_child_name_all = array();
  public $div_page_child_parent_all = array();
  // Tableau pour stocker les valeurs des enfants de la page
  public $div_page_child_value = array();
  public $div_page_child_value_all = array();
  public $div_page_row = array();
  public $div_page_row_bool = false;

  function __construct($div_page_child_1_name)
  {
    // Parcourt la liste des noms des pages enfants fournies en argument
    for ($i = 0; $i < count($div_page_child_1_name); $i++) {
      // Construit le chemin du fichier PHP correspondant
      $all_pages = "all_pages/" . $div_page_child_1_name[$i] . ".php";

      // Vérifie si le fichier existe avant de l'inclure
      if (file_exists($all_pages)) {
        include $all_pages;
       
          $this->div_page_row_bool = true;
          array_push($this->div_page_row, $row_projet);

        
      } else {
        continue; // Si le fichier n'existe pas, on passe au suivant
      }

      // Vérifie que `$row_projet` est bien défini et est un tableau
      if (!isset($row_projet) || !is_array($row_projet)) {
        continue; // Évite une erreur si `$row_projet` n'est pas disponible
      }

      // Boucle à travers les éléments du tableau `$row_projet`
      for ($ii = 0; $ii < count($row_projet); $ii++) {
        // Ajoute uniquement le premier élément dans `div_page_child_value`
        if ($ii == 0) {
          array_push($this->div_page_child_value, $row_projet[$ii]);
          array_push($this->div_page_child_name_all, $row_projet[$ii]["id_sha1_projet"]);
          array_push($this->div_page_child_parent_all, $row_projet[$ii]["id_sha1_parent_projet"]);
        }

        // Vérifie si l'ID du projet n'est pas déjà dans `div_page_child_1_name`
        if (!in_array($row_projet[$ii]["id_sha1_projet"], $div_page_child_1_name)) {
          // Vérifie si l'ID du projet n'est pas déjà enregistré dans `div_page_child_name`
          if (!in_array($row_projet[$ii]["id_sha1_projet"], $this->div_page_child_name)) {
            // Ajoute l'ID du projet et ses données aux tableaux correspondants
            array_push($this->div_page_child_name, $row_projet[$ii]["id_sha1_projet"]);
            array_push($this->div_page_child_title, $row_projet[$ii]["title_projet"]);
            array_push($this->div_page_child_description, $row_projet[$ii]["description_projet"]);
            
     







            

            array_push($this->div_page_child_name_all, $row_projet[$ii]["id_sha1_projet"]);
            array_push($this->div_page_child_value, $row_projet[$ii]);
            array_push($this->div_page_child_parent_all, $row_projet[$ii]["id_sha1_parent_projet"]);
            array_push($this->div_page_child_parent,   $row_projet[$ii]["id_sha1_parent_projet"]);
 
         
         
           
          }
        }
     
  
        
      }

      $this->div_page_child_value_all = $row_projet ; 
    }
  }

  // Fonction pour récupérer la liste des noms des enfants de la page
  function div_page_child_name()
  {
    return $this->div_page_child_name;
  }

  // Fonction pour récupérer la liste des valeurs des enfants de la page
  function div_page_child_value()
  {
    return $this->div_page_child_value;
  }
  function div_page_child_value_all()
  {
    return $this->div_page_child_value_all;
  }
  function div_page_child_parent()
  {
    return $this->div_page_child_parent;
  }
  function div_page_child_parent_all()
  {
    return $this->div_page_child_parent_all;
  }
  function div_page_child_name_all()
  {
    return $this->div_page_child_name_all;
  }

  function div_page_child_title()
  {
    return $this->div_page_child_title;
  }
  function div_page_child_description()
  {
    return $this->div_page_child_description;
  }

  function div_page_row()
  {
    return $this->div_page_row;
  }





  function generator_div($div_page_child_1_name, $apple_1, $class_1, $class_2, $class_3)
  {

    for ($i = 0; $i < count($div_page_child_1_name); $i++) {
      echo "<div id='" . $div_page_child_1_name[$i] . "' class='" . $class_1 . " " . $div_page_child_1_name[$i] . "'> ";
      for ($ii = 0; $ii < count($apple_1->div_page_child_parent_all()); $ii++) {
        if ($div_page_child_1_name[$i] == $apple_1->div_page_child_parent()[$ii]) {



          echo "<div class='" . $class_2 . "'>";
          echo (AsciiConverter::asciiToString($apple_1->div_page_child_title()[$ii]));
          echo "</div>";

          echo "<div class='" . $class_3 . "'>";
          echo (AsciiConverter::asciiToString($apple_1->div_page_child_description()[$ii]));
          echo "</div>";
        }
      }
      echo "</div>";
    }
  }


  function generator_div_($div_page_child_1_name, $apple_1, $class_1, $class_2, $value)
  {
 

    for ($i = 0; $i < count($div_page_child_1_name); $i++) {


   
      var_dump($apple_1->div_page_child_value()[$i]);
      echo "<div id='" . $div_page_child_1_name[$i] . "' class='" . $class_1 . " " . $div_page_child_1_name[$i] . "'> ";
      for ($ii = 0; $ii < 2; $ii++) {
        if ($div_page_child_1_name[$i] == $apple_1->div_page_child_parent()[$ii]) {


          echo "<div id='" . $apple_1->div_page_child_name()[$ii] . "' class='" . $class_2 . "'>" . AsciiConverter::asciiToString($apple_1->div_page_child_title()[$ii][$value]) . "</div>";
        }
      }
      echo "</div>";
    }
  }
}

/*

  var_dump($div_page_child_1_name);
  // Vérifier que `$div_page_child_1_name` est défini avant d'instancier `Div_page`

  $apple_1 = new Div_page($div_page_child_1_name);
  var_dump($apple_1 ->div_page_child_name()) ; 

  $apple_2 = new Div_page($apple_1 ->div_page_child_name());
  var_dump($apple_2 ->div_page_child_name()) ; 

  $apple_3 = new Div_page($apple_2 ->div_page_child_name());
  var_dump($apple_3 ->div_page_child_name()) ; 
  $apple_4 = new Div_page($apple_3 ->div_page_child_name());
  var_dump($apple_4 ->div_page_child_name()) ; 


  $apple_5 = new Div_page($apple_4 ->div_page_child_name());
  var_dump($apple_5 ->div_page_child_name()) ; 


  $apple_6= new Div_page($apple_5 ->div_page_child_name());
  var_dump($apple_6->div_page_child_name()) ;  

  $apple_7= new Div_page($apple_6 ->div_page_child_name());
  var_dump($apple_7->div_page_child_name()) ;  
 

*/
