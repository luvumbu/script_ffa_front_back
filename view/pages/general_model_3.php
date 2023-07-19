<div class="recherche_1_2">
    <input type="text" placeholder="Rechercher un athlete" id="nom_model_3"    name="fname" onkeyup="showHint(this.value)">
</div>
<div id="resulta_recherche">
</div>
<div style="margin-bottom:200px">
<p id="txtHint" class="recherche_1_2 result_recherche"></p>

<style>
    .result_recherche{
        width:80%;
        margin:auto ; 

    }
    .result_recherche div {
        border:1px solid black ; 
        padding:10px; 
        text-align:center ;
        transition:1s all ;

    }
    .result_recherche div:hover {
        cursor:pointer ; 
        background-color:grey ; 
        transition:1s all ;
        color:white ; 
    }
</style>