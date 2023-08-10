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

        <div class="container-fluid">
  <div class="row">
    <div class="col-sm-4">Haut nideau</div>
    <div class="col-sm-4">Reccord</div>
    <div class="col-sm-4">Statistiques</div>
  </div>
</div>

<div style="margin-top:100px">

</div>
        <p class="reccord display-3 text-center" id="id_nom_club__2">Niveau du sprint Homme club</p> 
 

    <div class="progress">
  <div class="progress-bar bg-warning" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
</div>

    <div class="progress">
  <div class="progress-bar bg-success" role="progressbar" style="width: 25%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
</div>
<div class="progress">
  <div class="progress-bar bg-info" role="progressbar" style="width: 50%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
</div>


<div class="reccord display-1 text-center">
  <h1>
  reccord du club 
  </h1>

</div>



<select class="form-select display-1 text-center" aria-label="Default select example" style="font-size:1.5em">
  <option selected>Sprint</option>
  <option value="1">Demi-font</option>
  <option value="2">Saut</option>
  <option value="3">Lancée</option>
</select>

<?php 
/*
<p class="" id="id_nom_club__2">Niveau du sprint Femme club</p> 
 

 <div class="progress">
<div class="progress-bar bg-warning" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
</div>

 <div class="progress">
<div class="progress-bar bg-success" role="progressbar" style="width: 25%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
</div>
<div class="progress">
<div class="progress-bar bg-info" role="progressbar" style="width: 50%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
</div>






<p class="" id="id_nom_club__2">Niveau du  demi-font Homme club</p> 
 

 <div class="progress">
<div class="progress-bar bg-warning" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
</div>

 <div class="progress">
<div class="progress-bar bg-success" role="progressbar" style="width: 25%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
</div>
<div class="progress">
<div class="progress-bar bg-info" role="progressbar" style="width: 50%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
</div>



<p class="" id="id_nom_club__2">Niveau du  demi-font Femme club</p> 
 

 <div class="progress">
<div class="progress-bar bg-warning" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
</div>

 <div class="progress">
<div class="progress-bar bg-success" role="progressbar" style="width: 25%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
</div>
<div class="progress">
<div class="progress-bar bg-info" role="progressbar" style="width: 50%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
</div>

</div>


<style>
  #myChart{
    width:50%;
    margin:auto ; 
  }
</style>

<div>
  <canvas id="myChart"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
  const ctx = document.getElementById('myChart');

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Red', 'Blue', 'Yellow'],
      datasets: [{
        label: '# of Votes',
        data: [12, 19, 3],
        borderWidth: 1
      }]
    },
    options: {
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });
</script>

<?php 
/*

<div class="container mt-3">
   
    </div>

    <div class="container mt-3">
      <h1 class="">Niveau du club 2023</h1>

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

<meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>



<style>
  .container-fluid .col-sm-4{
background-color:#373737 ; 
color:white ; 
padding:10px; 
text-align:center ; 
border:1px solid #373838 ; 
  }

  .container-fluid .col-sm-4:hover{
background-color:white ; 
color:#373737 ; 
padding:10px; 
text-align:center ; 
cursor:pointer; 
  }



  @font-face {
  font-family: reccord;
  src: url(reccord.ttf);
}


.reccord{
  font-family: reccord;
}
.text-center{
  text-align:center ; 
  margin-top:100px; 
  margin-bottom : 50px; 
}




</style>


          <script>
            

            var information_g  ; 
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
              if (this.readyState == 4 && this.status == 200) {
                var myObj = JSON.parse(this.responseText);
 
              information_g = myObj ; 


              console.log(myObj) ; 
              }
            };
            xmlhttp.open("GET", "https://bokonzi.com/ffa/vlog.php/[get_result_villes_nom_array_2]/Valence", true);
            xmlhttp.send();







            const liste_elements_array = [
  'get_emplacement',	
	'get_rp_array_2',	
	'get_vent_array_2',	
	'get_result_users_perf_array_2',	
	'get_result_users_nom_1_array_2',	
	'get_result_users_nom_2_array_2',	
	'get_result_users_nom_3_array_2',	
	'get_result_users_nom_4_array_2',	
	'get_users_nationality_array_2',	
	'get_club_nom_complet_array_2',	
	'get_club_departement_array_2',	
	'get_club_region_array_2',	
	'get_cat_array_2',	
	'get_users_naissance_array_2',	
	'get_result_date_perf_array_2',	
	'get_result_villes_nom_array_2',	
	'epreuve_sex_array_2',	
	'get_users_nom_complet_array',	
	'info_all_array_click',	
	'info_all_array_ip',	
	'info_all_array_src_name',	
	'window_location_href',	
	'get_epreuve_nom_complet',	
	'reg_date'
]

let get_emplacement = [] ; 
let get_rp_array_2 = [] ; 
let get_vent_array_2 = [] ; 
let get_result_users_perf_array_2 = [] ; 
let get_result_users_nom_1_array_2 = [] ; 

let get_result_users_nom_2_array_2 = [] ; 
let get_result_users_nom_3_array_2 = [] ; 
let get_result_users_nom_4_array_2 = [] ; 
let get_users_nationality_array_2 = [] ; 
let get_club_nom_complet_array_2 = [] ; 

let get_club_departement_array_2 = [] ; 
let get_club_region_array_2 = [] ; 
let get_cat_array_2 = [] ; 
let get_users_naissance_array_2 = [] ; 
let get_result_date_perf_array_2 = [] ; 

let get_result_villes_nom_array_2 = [] ; 
let epreuve_sex_array_2 = [] ; 
let get_users_nom_complet_array = [] ; 
let info_all_array_click = [] ; 
let info_all_array_ip = [] ; 
let info_all_array_src_name = [] ; 
let window_location_href = [] ; 
let get_epreuve_nom_complet = [] ; 
let reg_date = [] ; 


let get_emplacement_sort = [] ; 
let get_rp_array_2_sort = [] ; 
let get_vent_array_2_sort = [] ; 
let get_result_users_perf_array_2_sort = [] ; 
let get_result_users_nom_1_array_2_sort = [] ; 

let get_result_users_nom_2_array_2_sort = [] ; 
let get_result_users_nom_3_array_2_sort = [] ; 
let get_result_users_nom_4_array_2_sort = [] ; 
let get_users_nationality_array_2_sort = [] ; 
let get_club_nom_complet_array_2_sort = [] ; 

let get_club_departement_array_2_sort = [] ; 
let get_club_region_array_2_sort = [] ; 
let get_cat_array_2_sort = [] ; 
let get_users_naissance_array_2_sort = [] ; 
let get_result_date_perf_array_2_sort = [] ; 

let get_result_villes_nom_array_2_sort = [] ; 
let epreuve_sex_array_2_sort = [] ; 
let get_users_nom_complet_array_sort = [] ; 
let info_all_array_click_sort = [] ; 
let info_all_array_ip_sort = [] ; 
let info_all_array_src_name_sort = [] ; 
let window_location_href_sort = [] ; 
let get_epreuve_nom_complet_sort = [] ; 
let reg_date_sort = [] ; 



const myTimeout = setTimeout(test_time, 400);

function test_time() {


  
 

for(var x = 0 ; x<information_g.length; x++  )


{


 
  // X
  if(!get_rp_array_2.includes(information_g[x].get_rp_array_2))
  {

  

    get_rp_array_2.push(information_g[x].get_rp_array_2) ; 


  }
  // X
  if(!get_vent_array_2.includes(information_g[x].get_vent_array_2))
  {

    get_vent_array_2.push(information_g[x].get_vent_array_2) ; 
  }
  // X
  if(!get_result_users_perf_array_2.includes(information_g[x].get_result_users_perf_array_2))
  {

    get_result_users_perf_array_2.push(information_g[x].get_result_users_perf_array_2) ; 
  }
  // X
  if(!get_result_users_nom_1_array_2.includes(information_g[x].get_result_users_nom_1_array_2))
  {

    get_result_users_nom_1_array_2.push(information_g[x].get_result_users_nom_1_array_2) ; 
  }
  // X
  if(!get_result_users_nom_2_array_2.includes(information_g[x].get_result_users_nom_2_array_2))
  {

    get_result_users_nom_2_array_2.push(information_g[x].get_result_users_nom_2_array_2) ; 
  }
  // X
  if(!get_result_users_nom_3_array_2.includes(information_g[x].get_result_users_nom_3_array_2))
  {

    get_result_users_nom_3_array_2.push(information_g[x].get_result_users_nom_3_array_2) ; 
  }
  // X
  if(!get_result_users_nom_4_array_2.includes(information_g[x].get_result_users_nom_4_array_2))
  {

    get_result_users_nom_4_array_2.push(information_g[x].get_result_users_nom_4_array_2) ; 
  }
  // X
  if(!get_users_nationality_array_2.includes(information_g[x].get_users_nationality_array_2))
  {

    get_users_nationality_array_2.push(information_g[x].get_users_nationality_array_2) ; 
  }
  // X
  if(!get_club_nom_complet_array_2.includes(information_g[x].get_club_nom_complet_array_2))
  {

    get_club_nom_complet_array_2.push(information_g[x].get_club_nom_complet_array_2) ; 
  }
  // X
  if(!get_club_departement_array_2.includes(information_g[x].get_club_departement_array_2))
  {

    get_club_departement_array_2.push(information_g[x].get_club_departement_array_2) ; 
  }
  // X
  if(!get_club_region_array_2.includes(information_g[x].get_club_region_array_2))
  {

    get_club_region_array_2.push(information_g[x].get_club_region_array_2) ; 
  }
  // X
  if(!get_cat_array_2.includes(information_g[x].get_cat_array_2))
  {

    get_cat_array_2.push(information_g[x].get_cat_array_2) ; 
  }
  // X
  if(!get_users_naissance_array_2.includes(information_g[x].get_users_naissance_array_2))
  {

    get_users_naissance_array_2.push(information_g[x].get_users_naissance_array_2) ; 
  }
  // X
  if(!get_result_date_perf_array_2.includes(information_g[x].get_result_date_perf_array_2))
  {

    get_result_date_perf_array_2.push(information_g[x].get_result_date_perf_array_2) ; 
  }
  // X
  if(!get_result_villes_nom_array_2.includes(information_g[x].get_result_villes_nom_array_2))
  {

    get_result_villes_nom_array_2.push(information_g[x].get_result_villes_nom_array_2) ; 
  }
  // X
  if(!epreuve_sex_array_2.includes(information_g[x].epreuve_sex_array_2))
  {

    epreuve_sex_array_2.push(information_g[x].epreuve_sex_array_2) ; 
  }
  // X
  if(!get_users_nom_complet_array.includes(information_g[x].get_users_nom_complet_array))
  {

    get_users_nom_complet_array.push(information_g[x].get_users_nom_complet_array) ; 
  }
  // X
  if(!info_all_array_click.includes(information_g[x].info_all_array_click))
  {

    info_all_array_click.push(information_g[x].info_all_array_click) ; 
  }
  // X
  if(!info_all_array_ip.includes(information_g[x].info_all_array_ip))
  {

    info_all_array_ip.push(information_g[x].info_all_array_ip) ; 
  }
  // X
  if(!info_all_array_src_name.includes(information_g[x].info_all_array_src_name))
  {

    info_all_array_src_name.push(information_g[x].info_all_array_src_name) ; 
  }
  // X
  if(!window_location_href.includes(information_g[x].window_location_href))
  {

    window_location_href.push(information_g[x].window_location_href) ; 
  }
  // X
  if(!get_epreuve_nom_complet.includes(information_g[x].get_epreuve_nom_complet))
  {

    get_epreuve_nom_complet.push(information_g[x].get_epreuve_nom_complet) ; 
  }
  // X
  if(!reg_date.includes(information_g[x].reg_date))
  {

    reg_date.push(information_g[x].reg_date) ; 
  }
  
   

  
 
}





 


 
 

console.log(get_result_users_nom_1_array_2) ; 



/*
 


get_emplacement_sort = get_emplacement.sort() ; 

get_rp_array_2_sort = get_rp_array_2.sort() ; 
get_vent_array_2_sort = get_vent_array_2.sort() ; 
get_result_users_perf_array_2_sort = get_result_users_perf_array_2.sort() ; 
get_result_users_nom_1_array_2_sort = get_result_users_nom_1_array_2.sort() ; 
get_result_users_nom_2_array_2_sort = get_result_users_nom_2_array_2.sort() ; 
get_result_users_nom_3_array_2_sort = get_result_users_nom_3_array_2.sort() ; 
get_result_users_nom_4_array_2_sort = get_result_users_nom_4_array_2.sort() ; 
get_users_nationality_array_2_sort = get_users_nationality_array_2.sort() ;  
 

get_club_nom_complet_array_2_sort = get_club_nom_complet_array_2.sort() ; 

 

get_club_departement_array_2_sort = get_club_departement_array_2.sort() ; 
get_club_region_array_2_sort = get_club_region_array_2.sort() ; 
get_cat_array_2_sort = get_cat_array_2.sort() ; 
get_users_naissance_array_2_sort = get_users_naissance_array_2.sort() ; 
get_result_date_perf_array_2_sort = get_result_date_perf_array_2.sort() ; 
get_result_villes_nom_array_2_sort = get_result_villes_nom_array_2.sort() ; 
epreuve_sex_array_2_sort = epreuve_sex_array_2.sort() ; 
get_users_nom_complet_array_sort = get_users_nom_complet_array.sort() ; 
info_all_array_click_sort = info_all_array_click.sort() ; 
info_all_array_ip_sort = info_all_array_ip.sort() ; 






 */
































}




 

const fruits = ["Banana", "Orange", "Apple", "Mango"];





var mon = ["x"] ; 


function ma_boucle(nombre,tab,recheche,tab_ajout,element_ajout){

  for(var x  = 0 ; x<nombre; x++){
  
 

    if(!tab.includes(recheche)){
      
      tab_ajout.push(element_ajout) ;
    }
  }
}
 
ma_boucle(10,fruits,"Mangox",mon,"123456789") ;



 

          </script>