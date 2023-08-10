<?php 

// Liste des id preparation 


/*
id_nom_recherche 
id_nom_club_1
id_nom_club_2
id_nom_club_1

*/

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Bootstrap Example</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
  <link rel="icon" type="image/x-icon" href="https://www.ramonville.fr/mediatheque/illustrations/Sport_Equipements_sportifs/Piste_athl%C3%A9.jpg">
    <div class="container mt-3">
        <h1 class="display-1" id="id_nom_recherche"><strong>RECHERCHE</strong> </h1>
        <h1 class="display-1" id="id_nom_club__1">LILLE METROPOLE ATHLETISME </h1>
        <h1 class="display-2" id="id_nom_club__2">LILLE</h1>

    

 



        


 
      </div>



<?php 
/*

<div class="container mt-3">
   
    </div>

    <div class="container mt-3">
      <h1 class="display-4">Niveau du club 2023</h1>

      <div class="progress">
        <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">N1</div>
      </div>
  </div>
</div>



    <div class="container mt-3">
        <h2>Dérnier performence</h2>

<p>
  Bien sûr, voici quelques exemples d'athlètes du club Les Étoiles Sportives, âgés de maximum 22 ans, qui ont amélioré leurs records personnels et ont montré une progression significative tout au long de la saison régionale en athlétisme :

  Emma Dupont (20 ans) : Elle a participé aux épreuves de sprint tout au long de la saison régionale et a constamment amélioré ses temps. Elle a battu son record personnel au 100 mètres et au 200 mètres, se classant parmi les meilleures sprinteuses de la région dans sa catégorie d'âge.
  
  Lucas Martin (21 ans) : Lucas s'est spécialisé dans les épreuves de saut en hauteur et de saut en longueur. Au fil des compétitions régionales, il a augmenté ses performances, battant son record personnel au saut en hauteur et obtenant de meilleurs résultats au saut en longueur.
  
  Sarah Leclerc (22 ans) : Sarah a participé aux épreuves de lancer du poids et du javelot. Au cours de la saison, elle a montré une amélioration constante de ses lancers, atteignant de nouvelles distances personnelles et obtenant des scores élevés dans ces disciplines.
  
  Nicolas Roussel (19 ans) : Nicolas s'est concentré sur les épreuves de demi-fond, notamment le 800 mètres et le 1500 mètres. Il a progressé de manière impressionnante tout au long de la saison, abaissant ses temps dans ces courses et se classant parmi les meilleurs coureurs de sa catégorie.
  
  Camille Lavoie (20 ans) : Camille a excellé dans les épreuves combinées, notamment l'heptathlon. Elle a travaillé dur pour améliorer ses performances dans les différentes disciplines de l'heptathlon, comme le saut en hauteur, le saut en longueur et les courses, démontrant ainsi une progression constante.
</p>        <table class="table">
          <thead>
            <tr>
              <th>Niveau athlet</th>
     
              <th>Nombre total</th>
            </tr>
          </thead>
          <tbody>
            <tr class="table-warning">
              <td>Warning</td>
             
              <td>5</td>
            </tr>

     
 
            <tr class="table-success">
              <td>Success</td>
            
              <td>35</td>
            </tr>
 
            <tr class="table-info">
              <td>Info</td>
      
              <td>204</td>
            </tr>

     
 
          </tbody>
        </table>
    

        <div class="container mt-3">
          <ul class="pagination">
            <li class="page-item"><a class="page-link" href="#">Previous</a></li>
            <li class="page-item"><a class="page-link" href="#">1</a></li>
            <li class="page-item"><a class="page-link" href="#">2</a></li>
            <li class="page-item"><a class="page-link" href="#">3</a></li>
            <li class="page-item"><a class="page-link" href="#">4</a></li>
            <li class="page-item"><a class="page-link" href="#">5</a></li>
            <li class="page-item"><a class="page-link" href="#">6</a></li>
            <li class="page-item"><a class="page-link" href="#">7</a></li>
            <li class="page-item"><a class="page-link" href="#">8</a></li>
            <li class="page-item"><a class="page-link" href="#">9</a></li>
            <li class="page-item"><a class="page-link" href="#">Next</a></li>
          </ul>
        </div>


  
        <h2>Example of Jumbotron</h2>
        <div class="mt-4 p-5 bg-primary text-white rounded">
          <h1>Jumbotron Example</h1> 
         
         
         <p>
          Bien sûr, voici quelques exemples d'athlètes du club Les Étoiles Sportives, âgés de maximum 22 ans, qui ont amélioré leurs records personnels et ont montré une progression significative tout au long de la saison régionale en athlétisme :

Emma Dupont (20 ans) : Elle a participé aux épreuves de sprint tout au long de la saison régionale et a constamment amélioré ses temps. Elle a battu son record personnel au 100 mètres et au 200 mètres, se classant parmi les meilleures sprinteuses de la région dans sa catégorie d'âge.

Lucas Martin (21 ans) : Lucas s'est spécialisé dans les épreuves de saut en hauteur et de saut en longueur. Au fil des compétitions régionales, il a augmenté ses performances, battant son record personnel au saut en hauteur et obtenant de meilleurs résultats au saut en longueur.

Sarah Leclerc (22 ans) : Sarah a participé aux épreuves de lancer du poids et du javelot. Au cours de la saison, elle a montré une amélioration constante de ses lancers, atteignant de nouvelles distances personnelles et obtenant des scores élevés dans ces disciplines.

Nicolas Roussel (19 ans) : Nicolas s'est concentré sur les épreuves de demi-fond, notamment le 800 mètres et le 1500 mètres. Il a progressé de manière impressionnante tout au long de la saison, abaissant ses temps dans ces courses et se classant parmi les meilleurs coureurs de sa catégorie.

Camille Lavoie (20 ans) : Camille a excellé dans les épreuves combinées, notamment l'heptathlon. Elle a travaillé dur pour améliorer ses performances dans les différentes disciplines de l'heptathlon, comme le saut en hauteur, le saut en longueur et les courses, démontrant ainsi une progression constante.
         </p>
        </div>





        <div class="container mt-3">
            <h2>Pérformence par niveau</h2>


            <div class="alert alert-warning">
                <strong>IA,IB</strong> 
              </div>

                        <ul class="list-group">
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Sprint
              <span class="badge bg-primary rounded-pill">12</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Demi-font
              <span class="badge bg-primary rounded-pill">50</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Lancée
              <span class="badge bg-primary rounded-pill">99</span>
            </li>
          </ul>

          <div class="container mt-3">
            <ul class="pagination">
              <li class="page-item"><a class="page-link" href="#">Previous</a></li>
              <li class="page-item"><a class="page-link" href="#">1</a></li>
              <li class="page-item"><a class="page-link" href="#">2</a></li>
              <li class="page-item"><a class="page-link" href="#">3</a></li>
              <li class="page-item"><a class="page-link" href="#">4</a></li>
              <li class="page-item"><a class="page-link" href="#">5</a></li>
              <li class="page-item"><a class="page-link" href="#">6</a></li>
              <li class="page-item"><a class="page-link" href="#">7</a></li>
              <li class="page-item"><a class="page-link" href="#">8</a></li>
              <li class="page-item"><a class="page-link" href="#">9</a></li>
              <li class="page-item"><a class="page-link" href="#">Next</a></li>
            </ul>
          </div>

            <div class="alert alert-success">
              <strong>N1,N2,N3,N4</strong> 
            </div>
                      <ul class="list-group">
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Sprint
              <span class="badge bg-primary rounded-pill">12</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Demi-font
              <span class="badge bg-primary rounded-pill">50</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Lancée
              <span class="badge bg-primary rounded-pill">99</span>
            </li>
          </ul>

          <div class="container mt-3">
            <ul class="pagination">
              <li class="page-item"><a class="page-link" href="#">Previous</a></li>
              <li class="page-item"><a class="page-link" href="#">1</a></li>
              <li class="page-item"><a class="page-link" href="#">2</a></li>
              <li class="page-item"><a class="page-link" href="#">3</a></li>
              <li class="page-item"><a class="page-link" href="#">4</a></li>
              <li class="page-item"><a class="page-link" href="#">5</a></li>
              <li class="page-item"><a class="page-link" href="#">6</a></li>
              <li class="page-item"><a class="page-link" href="#">7</a></li>
              <li class="page-item"><a class="page-link" href="#">8</a></li>
              <li class="page-item"><a class="page-link" href="#">9</a></li>
              <li class="page-item"><a class="page-link" href="#">Next</a></li>
            </ul>
          </div>

          
            <div class="alert alert-info">
              <strong>IR1,IR2,IR3,IR4</strong> 
            </div>



          


          <ul class="list-group">
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Sprint
              <span class="badge bg-primary rounded-pill">12</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Demi-font
              <span class="badge bg-primary rounded-pill">50</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Lancée
              <span class="badge bg-primary rounded-pill">99</span>
            </li>
          </ul>



          <div class="container mt-3">
            <ul class="pagination">
              <li class="page-item"><a class="page-link" href="#">Previous</a></li>
              <li class="page-item"><a class="page-link" href="#">1</a></li>
              <li class="page-item"><a class="page-link" href="#">2</a></li>
              <li class="page-item"><a class="page-link" href="#">3</a></li>
              <li class="page-item"><a class="page-link" href="#">4</a></li>
              <li class="page-item"><a class="page-link" href="#">5</a></li>
              <li class="page-item"><a class="page-link" href="#">6</a></li>
              <li class="page-item"><a class="page-link" href="#">7</a></li>
              <li class="page-item"><a class="page-link" href="#">8</a></li>
              <li class="page-item"><a class="page-link" href="#">9</a></li>
              <li class="page-item"><a class="page-link" href="#">Next</a></li>
            </ul>
          </div>



 
          */
          ?>