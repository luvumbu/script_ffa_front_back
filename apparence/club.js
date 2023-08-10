    let get_emplacement	 = [""] ; 
	let get_rp_array_2	 = [""] ; 
	let get_vent_array_2	 = [""] ; 
	let get_result_users_perf_array_2	 = [""] ; 
	let get_result_users_nom_1_array_2	 = [""] ; 
	let get_result_users_nom_2_array_2	 = [""] ; 
	let get_result_users_nom_3_array_2	 = [""] ; 
	let get_result_users_nom_4_array_2	 = [""] ; 
	let get_users_nationality_array_2	 = [""] ; 
	let get_club_nom_complet_array_2	 = [""] ; 
	let get_club_departement_array_2	 = [""] ; 
	let get_club_region_array_2	 = [""] ; 
	let get_cat_array_2	 = [""] ; 
	let get_users_naissance_array_2	 = [""] ; 
	let get_result_date_perf_array_2	 = [""] ; 
	let get_result_villes_nom_array_2 = [""] ; 	
	let epreuve_sex_array_2	 = [""] ; 
	let get_users_nom_complet_array	 = [""] ; 
	let info_all_array_click	 = [""] ; 
	let info_all_array_ip	 = [""] ; 
	let info_all_array_src_name	 = [""] ; 
	let window_location_href	 = [""] ; 
	let get_epreuve_nom_complet	 = [""] ; 
	let reg_date = [""] ; 



    tab_liste = [

 	
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
 ; 


 var total_info  ; 

    console.log( tab_liste.length) ; 

var xmlhttp = new XMLHttpRequest();
xmlhttp.onreadystatechange = function() {
  if (this.readyState == 4 && this.status == 200) {
    var myObj = JSON.parse(this.responseText);

 
    total_info = myObj ; 
 


   
  }
};
xmlhttp.open("GET", "https://bokonzi.com/ffa/vlog.php/[get_club_nom_complet_array_2]/17 Soupapes", true);
xmlhttp.send();






const myTimeout = setTimeout(myGreeting, 5000);

function myGreeting() {
 console.log(total_info) ; 
}
