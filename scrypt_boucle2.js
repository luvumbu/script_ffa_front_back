
function scrypt_boucle(tab_sprint_3,cotation_tab_sprint_3 ){

  const tab_cotation_maxx = 30 ; 
  const n0=0 ; 
const n1=1 ; 
const n2=2 ; 
const n3=3 ; 
const n4=4 ; 
const n5=5 ; 

const n6=6 ; 
const n7=7 ; 
const n8=8 ; 
const n9=9 ; 
const n10=10 ; 

const n11=11 ; 
const n12=12 ; 
const n13=13 ; 
const n14=14 ; 
const n15=15 ; 

const n16=16 ; 
const n17=17 ; 
const n18=18 ; 
const n19=19; 
const n20=20; 

const n21=21 ; 
const n22=22 ; 
const n23=23 ; 
const n24=24 ; 
const n25=25 ; 
const n26=26 ; 

const n27=27 ; 

const n28=28 ; 

const n29=29 ; 

const n30=30 ; 

 




  this.tab_sprint_1_niveau__=  new Array(30) ;
  for (i=0; i < 30; i++){  
      this.tab_sprint_1_niveau__[i] = new Array() ;   
  }



    for(var t = 0 ; t <tab_sprint_3.length ; t ++  ) {
   
   nombre_ = tab_sprint_3[t].get_result_users_perf_array_2 ; 
   
  
 
   
   this.tab_sprint_1_niveau__[n24].push(nombre_) ;
   

  if(nombre_<=cotation_tab_sprint_3[n0] ){
   this.tab_sprint_1_niveau__[n0].push(tab_sprint_3[t]) ;
   level_i ++ ; 
  }
   if(nombre_<=cotation_tab_sprint_3[n1] && nombre_ >cotation_tab_sprint_3[n0]){
   this.tab_sprint_1_niveau__[n1].push(tab_sprint_3[t]) ;
   level_i ++
  }
  
  if(nombre_<=cotation_tab_sprint_3[n2] && nombre_ >cotation_tab_sprint_3[n1]){
   this.tab_sprint_1_niveau__[n2].push(tab_sprint_3[t]) ;
   level_n ++ ; 
  }
  if(nombre_<=cotation_tab_sprint_3[n3] && nombre_ >cotation_tab_sprint_3[n2]){
   this.tab_sprint_1_niveau__[n3].push(tab_sprint_3[t]) ;
   level_n ++ ; 

  }
  
  if(nombre_<=cotation_tab_sprint_3[n4] && nombre_ >cotation_tab_sprint_3[n3]){
   this.tab_sprint_1_niveau__[n4].push(tab_sprint_3[t]) ;
   level_n ++ ; 

  }
  
  if(nombre_<=cotation_tab_sprint_3[n5] && nombre_ >cotation_tab_sprint_3[n4]){
   this.tab_sprint_1_niveau__[n5].push(tab_sprint_3[t]) ;
   level_n ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n6] && nombre_ >cotation_tab_sprint_3[n5]){
   this.tab_sprint_1_niveau__[n6].push(tab_sprint_3[t]) ;
   level_ir ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n7] && nombre_ >cotation_tab_sprint_3[n6]){
   this.tab_sprint_1_niveau__[n7].push(tab_sprint_3[t]) ;
   level_ir ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n8] && nombre_ >cotation_tab_sprint_3[n7]){
   this.tab_sprint_1_niveau__[n8].push(tab_sprint_3[t]) ;
   level_ir ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n9] && nombre_ >cotation_tab_sprint_3[n8]){
   this.tab_sprint_1_niveau__[n9].push(tab_sprint_3[t]) ;
   level_ir ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n10] && nombre_ >cotation_tab_sprint_3[n9]){
   this.tab_sprint_1_niveau__[n10].push(tab_sprint_3[t]) ;
   level_r ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n11] && nombre_ >cotation_tab_sprint_3[n10]){
   this.tab_sprint_1_niveau__[n11].push(tab_sprint_3[t]) ;
   level_r ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n12] && nombre_ >cotation_tab_sprint_3[n11]){
   this.tab_sprint_1_niveau__[n12].push(tab_sprint_3[t]) ;
   level_r ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n13] && nombre_ >cotation_tab_sprint_3[n12]){
   this.tab_sprint_1_niveau__[n13].push(tab_sprint_3[t]) ;
   level_r ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n14] && nombre_ >cotation_tab_sprint_3[n13]){
   this.tab_sprint_1_niveau__[n14].push(tab_sprint_3[t]) ;
   level_r ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n15] && nombre_ >cotation_tab_sprint_3[n14]){
   this.tab_sprint_1_niveau__[n15].push(tab_sprint_3[t]) ;
   level_r ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n16] && nombre_ >cotation_tab_sprint_3[n15]){
   this.tab_sprint_1_niveau__[n16].push(tab_sprint_3[t]) ;
   level_d ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n17] && nombre_ >cotation_tab_sprint_3[n16]){
   this.tab_sprint_1_niveau__[n17].push(tab_sprint_3[t]) ;
   level_d ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n18] && nombre_ >cotation_tab_sprint_3[n17]){
   this.tab_sprint_1_niveau__[n18].push(tab_sprint_3[t]) ;
   level_d ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n19] && nombre_ >cotation_tab_sprint_3[n17]){
   this.tab_sprint_1_niveau__[n19].push(tab_sprint_3[t]) ;
   level_d ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n20] && nombre_ >cotation_tab_sprint_3[n18]){
   this.tab_sprint_1_niveau__[n20].push(tab_sprint_3[t]) ;
   level_d ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n21] && nombre_ >cotation_tab_sprint_3[n20]){
   this.tab_sprint_1_niveau__[n21].push(tab_sprint_3[t]) ;
   level_d ++ ; 

  }
  if(nombre_<=cotation_tab_sprint_3[n22] && nombre_ >cotation_tab_sprint_3[n21]){
   this.tab_sprint_1_niveau__[n22].push(tab_sprint_3[t]) ;
   level_d ++ ; 

  }
  if(  nombre_ >cotation_tab_sprint_3[n22]){
   this.tab_sprint_1_niveau__[n23].push(tab_sprint_3[t]) ;
   level_d ++ ; 

  } 
  
  }
   this.tab_sprint_1_niveau__[n25].push(  this.tab_sprint_1_niveau__[n24].sort()) ;

   this.tab_sprint_1_niveau__[n26].push(  level_i) ;
   this.tab_sprint_1_niveau__[n27].push(  level_n) ;
   this.tab_sprint_1_niveau__[n28].push(  level_r) ;
   this.tab_sprint_1_niveau__[n29].push(  level_ir) ;
   this.tab_sprint_1_niveau__[n30].push(  level_d) ;
    

   
  return this.tab_sprint_1_niveau__ ; 
  
  }
