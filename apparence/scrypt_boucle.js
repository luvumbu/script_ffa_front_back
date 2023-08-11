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



var tab_sprint_1_niveau_=  new Array(24) ;
var tab_sprint_2_niveau_=  new Array(24) ;
var tab_sprint_3_niveau_=  new Array(24) ;
var tab_sprint_4_niveau_=  new Array(24) ;
var tab_sprint_5_niveau_=  new Array(24) ;

var tab_sprint_6_niveau_=  new Array(24) ;
var tab_sprint_7_niveau_=  new Array(24) ;
var tab_sprint_8_niveau_=  new Array(24) ;
var tab_sprint_9_niveau_=  new Array(24) ;
var tab_sprint_10_niveau_=  new Array(24) ;

var tab_sprint_11_niveau_=  new Array(24) ;
var tab_sprint_12_niveau_=  new Array(24) ;
var tab_sprint_13_niveau_=  new Array(24) ;
var tab_sprint_14_niveau_=  new Array(24) ;
var tab_sprint_15_niveau_=  new Array(24) ;

var tab_sprint_16_niveau_=  new Array(24) ;




for (i=0; i < 25; i++){

    tab_sprint_1_niveau_[i] = new Array() ; 
    tab_sprint_2_niveau_[i] = new Array() ; 
    tab_sprint_3_niveau_[i] = new Array() ; 
    tab_sprint_4_niveau_[i] = new Array() ; 
    tab_sprint_5_niveau_[i] = new Array() ;

    tab_sprint_6_niveau_[i] = new Array() ; 
    tab_sprint_7_niveau_[i] = new Array() ; 
    tab_sprint_8_niveau_[i] = new Array() ; 
    tab_sprint_9_niveau_[i] = new Array() ; 
    tab_sprint_10_niveau_[i] = new Array() ; 

    tab_sprint_11_niveau_[i] = new Array() ; 
    tab_sprint_12_niveau_[i] = new Array() ; 
    tab_sprint_13_niveau_[i] = new Array() ; 
    tab_sprint_14_niveau_[i] = new Array() ; 
    tab_sprint_15_niveau_[i] = new Array() ; 
    tab_sprint_16_niveau_[i] = new Array() ; 




}



var cotation_tab_sprint_3  = [
  10.10,  	
  10.20,   	
  10.34,
  10.64, 
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
  14.15, 
] ;
var n0=0 ; 
var n1=1 ; 
var n2=2 ; 
var n3=3 ; 
var n4=4 ; 
var n5=5 ; 

var n6=6 ; 
var n7=7 ; 
var n8=8 ; 
var n9=9 ; 
var n10=10 ; 

var n11=11 ; 
var n12=12 ; 
var n13=13 ; 
var n14=14 ; 
var n15=15 ; 

var n16=16 ; 
var n17=17 ; 
var n18=18 ; 
var n19=19; 
var n20=20; 

var n21=21 ; 
var n22=22 ; 
var n23=23 ; 
var n24=24 ; 
var n25=25 ; 





// ................................. debut condition 






var cotation_tab_sprint_3  = [
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
  14.15, 
] ;




function scrypt_boucle(tab_sprint_3,cotation_tab_sprint_3,tab_sprint_1_niveau_ ){
 

 

 




  
    for(var t = 0 ; t <tab_sprint_3.length ; t ++  ) {
   
   nombre_ = tab_sprint_3[t].get_result_users_perf_array_2 ; 
   
  
   
   tab_sprint_1_niveau_[n24].push(nombre_) ;
   

  if(nombre_<=cotation_tab_sprint_3[n0] ){
   tab_sprint_1_niveau_[n0].push(tab_sprint_3[t]) ;
  }
   if(nombre_<=cotation_tab_sprint_3[n1] && nombre_ >cotation_tab_sprint_3[n0]){
   tab_sprint_1_niveau_[n1].push(tab_sprint_3[t]) ;
  }
  
  if(nombre_<=cotation_tab_sprint_3[n2] && nombre_ >cotation_tab_sprint_3[n1]){
   tab_sprint_1_niveau_[n2].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n3] && nombre_ >cotation_tab_sprint_3[n2]){
   tab_sprint_1_niveau_[n3].push(tab_sprint_3[t]) ;
  }
  
  if(nombre_<=cotation_tab_sprint_3[n4] && nombre_ >cotation_tab_sprint_3[n3]){
   tab_sprint_1_niveau_[n4].push(tab_sprint_3[t]) ;
  }
  
  if(nombre_<=cotation_tab_sprint_3[n5] && nombre_ >cotation_tab_sprint_3[n4]){
   tab_sprint_1_niveau_[n5].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n6] && nombre_ >cotation_tab_sprint_3[n5]){
   tab_sprint_1_niveau_[n6].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n7] && nombre_ >cotation_tab_sprint_3[n6]){
   tab_sprint_1_niveau_[n7].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n8] && nombre_ >cotation_tab_sprint_3[n7]){
   tab_sprint_1_niveau_[n8].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n9] && nombre_ >cotation_tab_sprint_3[n8]){
   tab_sprint_1_niveau_[n9].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n10] && nombre_ >cotation_tab_sprint_3[n9]){
   tab_sprint_1_niveau_[n10].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n11] && nombre_ >cotation_tab_sprint_3[n10]){
   tab_sprint_1_niveau_[n11].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n12] && nombre_ >cotation_tab_sprint_3[n11]){
   tab_sprint_1_niveau_[n12].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n13] && nombre_ >cotation_tab_sprint_3[n12]){
   tab_sprint_1_niveau_[n13].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n14] && nombre_ >cotation_tab_sprint_3[n13]){
   tab_sprint_1_niveau_[n14].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n15] && nombre_ >cotation_tab_sprint_3[n14]){
   tab_sprint_1_niveau_[n15].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n16] && nombre_ >cotation_tab_sprint_3[n15]){
   tab_sprint_1_niveau_[n16].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n17] && nombre_ >cotation_tab_sprint_3[n16]){
   tab_sprint_1_niveau_[n17].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n18] && nombre_ >cotation_tab_sprint_3[n17]){
   tab_sprint_1_niveau_[n18].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n19] && nombre_ >cotation_tab_sprint_3[n17]){
   tab_sprint_1_niveau_[n19].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n20] && nombre_ >cotation_tab_sprint_3[n18]){
   tab_sprint_1_niveau_[n20].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n21] && nombre_ >cotation_tab_sprint_3[n20]){
   tab_sprint_1_niveau_[n21].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n22] && nombre_ >cotation_tab_sprint_3[n21]){
   tab_sprint_1_niveau_[n22].push(tab_sprint_3[t]) ;
  }
  if(  nombre_ >cotation_tab_sprint_3[n22]){
   tab_sprint_1_niveau_[n23].push(tab_sprint_3[t]) ;
  }
  
  
  
  }
  
  
  
  return tab_sprint_1_niveau_ ; 
  
  }


  // exemple de code 


  // 