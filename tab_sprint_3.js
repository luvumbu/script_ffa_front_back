


 function tab_sprint_3(){
 // DEBUT DU SCRYPT DE RECHERCHE 
 var IA_tab_sprint_3  =10.10 ;  	
 var IB_IA_tab_sprint_3  =10.20 ;  	
 var N1_tab_sprint_3  =10.34 ;  	
 var N2_tab_sprint_3  =10.64 ;  	
 var N3_tab_sprint_3  =10.84 ;  	
 var N4_tab_sprint_3  =10.94 ;  	
 var IR1_tab_sprint_3  =11.14 ;  	
 var IR2_tab_sprint_3  =11.34 ;  	
 var IR3_tab_sprint_3  =11.44 ;  	
 var IR4_tab_sprint_3  =11.54 ;  
 var R1_tab_sprint_3  =11.74 ;  	
 var R2_tab_sprint_3  =11.94 ;  
 var R3_tab_sprint_3  =12.14 ;  	
 var R4_tab_sprint_3  =12.24 ;  	
 var R5_tab_sprint_3  =12.34 ;  	
 var R6_tab_sprint_3  =12.44 ;  	
 var D1_tab_sprint_3  =12.54 ;  	
 var D2_tab_sprint_3  =12.74 ;  	
 var D3_tab_sprint_3  =12.94 ;  	
 var D4_tab_sprint_3  =13.14 ;  	
 var D5_tab_sprint_3  =13.44 ;  	
 var D6_tab_sprint_3  =13.74 ;  	
 var D7_tab_sprint_3  =14.14 ; 
 var D8_tab_sprint_3 =14.15 ;
 
 var level_tab_sprint_3 = [
  10.10,  	
  10.20,  	
  10.34,  	
  10.64,  	
  10.84,  	
  10.94,  	
  11.14,  	
  11.34,  	
  11.44,  	
  11.54,  
  11.74,  	
  11.94,  
  12.14,  	
  12.24,  	
  12.34,  	
  12.44,  	
  12.54,  	
  12.74,  	
  12.94,  	
  13.14,  	
  13.44,  	
  13.74,  	
  14.14, 
  14.15
 ]
 
 
 
 // ................................. debut condition 
 
 
 
 
 
 
 
 
 
 
 
 for(var t = 0 ; t <tab_sprint_3.length ; t ++  ) {
  
   nombre_ = tab_sprint_3[t] ; 
 
 if(nombre_>D8_tab_sprint_3){ 
   tab_sprint_1_niveau_[0].push(tab_sprint_3[t]) ;  
 }
 
 if(nombre_<=D7_tab_sprint_3 && nombre_ >D6_tab_sprint_3){ 
   tab_sprint_1_niveau_[1].push(tab_sprint_3[t]) ;
 }
 
 if(nombre_<=D6_tab_sprint_3&& nombre_ >D5_tab_sprint_3){
    tab_sprint_1_niveau_[2].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=D5_tab_sprint_3 && nombre_ >D4_tab_sprint_3){
   tab_sprint_1_niveau_[3].push(tab_sprint_3[t]) ;
 }
 
 if(nombre_<=D4_tab_sprint_3 && nombre_ >D3_tab_sprint_3){
   tab_sprint_1_niveau_[4].push(tab_sprint_3[t]) ;
 }
 
 if(nombre_<=D3_tab_sprint_3 && nombre_ >D2_tab_sprint_3){
   tab_sprint_1_niveau_[5].push(tab_sprint_3[t]) ;
   }
 if(nombre_<=D2_tab_sprint_3 && nombre_ >D1_tab_sprint_3){
   tab_sprint_1_niveau_[6].push(tab_sprint_3[t]) ;
  }
 if(nombre_<=D1_tab_sprint_3 && nombre_ >R6_tab_sprint_3){
   tab_sprint_1_niveau_[7].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=R6_tab_sprint_3 && nombre_ >R5_tab_sprint_3){
   tab_sprint_1_niveau_[8].push(tab_sprint_3[t]) ;
  }
 if(nombre_<=R5_tab_sprint_3 && nombre_ >R4_tab_sprint_3){
   tab_sprint_1_niveau_[9].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=R4_tab_sprint_3 && nombre_ >R3_tab_sprint_3){
   tab_sprint_1_niveau_[10].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=R3_tab_sprint_3 && nombre_ >R2_tab_sprint_3){
   tab_sprint_1_niveau_[11].push(tab_sprint_3[t]) ;
 }
  
 if(nombre_<=R2_tab_sprint_3 && nombre_ >R1_tab_sprint_3){
   tab_sprint_1_niveau_[11].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=R1_tab_sprint_3 && nombre_ >IR4_tab_sprint_3){
   tab_sprint_1_niveau_[12].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=IR4_tab_sprint_3 && nombre_ >IR3_tab_sprint_3){
   tab_sprint_1_niveau_[13].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=IR3_tab_sprint_3 && nombre_ >IR2_tab_sprint_3){
   tab_sprint_1_niveau_[14].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=IR2_tab_sprint_3 && nombre_ >IR1_tab_sprint_3){
   tab_sprint_1_niveau_[15].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=IR1_tab_sprint_3 && nombre_ >N4_tab_sprint_3){
   tab_sprint_1_niveau_[16].push(tab_sprint_3[t]) ;
  }
 if(nombre_<=N4_tab_sprint_3 && nombre_ >N3_tab_sprint_3){
   tab_sprint_1_niveau_[17].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=N3_tab_sprint_3 && nombre_ >N2_tab_sprint_3){
   tab_sprint_1_niveau_[18].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=N2_tab_sprint_3 && nombre_ >N1_tab_sprint_3){
   tab_sprint_1_niveau_[19].push(tab_sprint_3[t]) ;
 }
 if(nombre_<=N1_tab_sprint_3 && nombre_ >IB_IA_tab_sprint_3){
   tab_sprint_1_niveau_[20].push(tab_sprint_3[t]) ;
 }
  if(nombre_<=IB_IA_tab_sprint_3 && nombre_ >IA_tab_sprint_3){
   tab_sprint_1_niveau_[21].push(tab_sprint_3[t]) ;
  }
 
 if(nombre_<=IA_tab_sprint_3 ){
   tab_sprint_1_niveau_[22].push(tab_sprint_3[t]) ;
 }
  
 
 
 
 }
 console.log(tab_sprint_1_niveau_);
 
 //console.log(tab_sprint_1_niveau_.length) ; 
 

 }