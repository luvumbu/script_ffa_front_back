<!DOCTYPE html>
<html>
<body>


 <div id="canvas"></div>
<script src="apparence/get_epreuve_nom_complet_list.js"></script>
<script src="apparence/Operation_list.js"></script>
<script src="apparence/Operation.js"></script>

<script src="apparence/scrypt_boucle2.js"></script>
 <?php 

include("test_include.html") ; 

?> 


	<div></div>
<script>
class Information {
	constructor(link) {
		this.link = link;
		this.identite = new FormData();
		this.req = new XMLHttpRequest();
		this.identite_tab = [
		];
	}
	info() {
		return this.identite_tab; 
	}
	add(info, text){
		this.identite_tab.push([info, text]); 
	}
	push(){
		for(var i = 0 ; i < this.identite_tab.length ; i++){
			console.log(this.identite_tab[i][1]);
			this.identite.append(this.identite_tab[i][0], this.identite_tab[i][1]);		 
		}		
		this.req.open("POST",this.link);
		this.req.send(this.identite);
		console.log(this.req);	 
	}
}




/*
	get_rp_array_2	
	get_vent_array_2	
	get_result_users_perf_array_2	
	get_result_users_nom_1_array_2	
	get_result_users_nom_2_array_2		
	get_result_users_nom_3_array_2	
	get_result_users_nom_4_array_2	
	get_users_nationality_array_2	
	get_club_nom_complet_array_2	
	get_club_departement_array_2	
	
	get_club_region_array_2	
	get_cat_array_2	
	get_users_naissance_array_2	
	get_result_date_perf_array_2	
	get_result_villes_nom_array_2	
	
	epreuve_sex_array_2	
	get_users_nom_complet_array	
	info_all_array_click	
	info_all_array_ip	
	info_all_array_src_name	
	
	window_location_href	
	get_epreuve_nom_complet	
	reg_date
	*/

var el1 ="get_club_nom_complet_array_2" ; 
var el2 ="Lille Metropole Athletisme*" ; 

 

var limits="";
var limits="{0,50}";
var general_el = [] ; 
var x_ = 0 ; 
var xmlhttp = new XMLHttpRequest();
xmlhttp.onreadystatechange = function() {

  if (this.readyState == 4 && this.status == 200) {
    var myObj = JSON.parse(this.responseText);
     

    general_el = myObj ; 


 document.getElementById("title").innerHTML = el2 ; 

    console.log(myObj);
   

	for(var x = 0 ; x <myObj.length  ; x ++){

   //console.log(myObj[x].get_result_users_perf_array_2+" : "+x +myObj[x].get_users_nom_complet_array  +"  =  "+myObj[x].get_epreuve_nom_complet ) ; 
 


  
	}
 

	switch (el1) {
  case "get_result_users_perf_array_2":
    
    break;
	case "get_result_users_nom_1_array_2":
    
    break;
	case "get_result_users_nom_2_array_2":
    
    break;
	case "get_result_users_nom_3_array_2":
    
    break;
	case "get_result_users_nom_4_array_2":
    
    break;
	case "get_users_nationality_array_2":
    
    break;

	case "get_club_nom_complet_array_2":
     
    break;
	case "get_club_departement_array_2":
    
    break;
	case "get_club_region_array_2":
    
    break;
	case "get_cat_array_2":
    
    break;
	case "get_users_naissance_array_2":
    
    break;

	case "get_result_date_perf_array_2":
    
    break;
	case "get_result_villes_nom_array_2":
    
    break;
	case "epreuve_sex_array_2":
    
    break;
	case "get_users_nom_complet_array":
    
    break;
	case "info_all_array_click":
    
    break;

	case "info_all_array_ip":
    
    break;
	case "info_all_array_src_name":
    
    break;
	case "window_location_href":
    
    break;
	case "get_epreuve_nom_complet":
    
    break;
	case 0:
    day = "Sunday";
    break;
 
  
}


//console.log(cotation_x) ;
const myCar1 = new Operation(myObj,cotation_x[0],tab_sprint[1]);

myCar1.boucle_();

myCar1.myChart_("canvas");

  }
}; 

xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/{10,1000}[get_result_users_nom_1_array_2]/ARRON", true);

xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/%7B0,2%7D[get_result_users_nom_1_array_2]/ndenga", true);


xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/{0,2}[get_result_users_nom_1_array_2]/ndenga", true);

xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/["+el1+"]/"+el2, true);

xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/"+limits+"["+el1+"]/"+el2, true);









 




xmlhttp.open("GET", "https://bokonzi.com/ffa/api_bokonzi.php/"+limits+"["+el1+"]/"+el2, true);

xmlhttp.send();
</script>

 

</body>
</html>
