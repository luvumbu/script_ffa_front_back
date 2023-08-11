
function scrypt_boucle(tab_sprint_3,cotation_tab_sprint_3 ){

  const tab_cotation_maxx = 25 ; 
  var tab_sprint_1_niveau__=  new Array(tab_cotation_maxx) ;
  for (i=0; i < tab_cotation_maxx+1; i++){  
      tab_sprint_1_niveau__[i] = new Array() ;   
  }



    for(var t = 0 ; t <tab_sprint_3.length ; t ++  ) {
   
   nombre_ = tab_sprint_3[t].get_result_users_perf_array_2 ; 
   
  
 
   
   tab_sprint_1_niveau__[n24].push(nombre_) ;
   

  if(nombre_<=cotation_tab_sprint_3[n0] ){
   tab_sprint_1_niveau__[n0].push(tab_sprint_3[t]) ;
  }
   if(nombre_<=cotation_tab_sprint_3[n1] && nombre_ >cotation_tab_sprint_3[n0]){
   tab_sprint_1_niveau__[n1].push(tab_sprint_3[t]) ;
  }
  
  if(nombre_<=cotation_tab_sprint_3[n2] && nombre_ >cotation_tab_sprint_3[n1]){
   tab_sprint_1_niveau__[n2].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n3] && nombre_ >cotation_tab_sprint_3[n2]){
   tab_sprint_1_niveau__[n3].push(tab_sprint_3[t]) ;
  }
  
  if(nombre_<=cotation_tab_sprint_3[n4] && nombre_ >cotation_tab_sprint_3[n3]){
   tab_sprint_1_niveau__[n4].push(tab_sprint_3[t]) ;
  }
  
  if(nombre_<=cotation_tab_sprint_3[n5] && nombre_ >cotation_tab_sprint_3[n4]){
   tab_sprint_1_niveau__[n5].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n6] && nombre_ >cotation_tab_sprint_3[n5]){
   tab_sprint_1_niveau__[n6].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n7] && nombre_ >cotation_tab_sprint_3[n6]){
   tab_sprint_1_niveau__[n7].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n8] && nombre_ >cotation_tab_sprint_3[n7]){
   tab_sprint_1_niveau__[n8].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n9] && nombre_ >cotation_tab_sprint_3[n8]){
   tab_sprint_1_niveau__[n9].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n10] && nombre_ >cotation_tab_sprint_3[n9]){
   tab_sprint_1_niveau__[n10].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n11] && nombre_ >cotation_tab_sprint_3[n10]){
   tab_sprint_1_niveau__[n11].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n12] && nombre_ >cotation_tab_sprint_3[n11]){
   tab_sprint_1_niveau__[n12].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n13] && nombre_ >cotation_tab_sprint_3[n12]){
   tab_sprint_1_niveau__[n13].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n14] && nombre_ >cotation_tab_sprint_3[n13]){
   tab_sprint_1_niveau__[n14].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n15] && nombre_ >cotation_tab_sprint_3[n14]){
   tab_sprint_1_niveau__[n15].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n16] && nombre_ >cotation_tab_sprint_3[n15]){
   tab_sprint_1_niveau__[n16].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n17] && nombre_ >cotation_tab_sprint_3[n16]){
   tab_sprint_1_niveau__[n17].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n18] && nombre_ >cotation_tab_sprint_3[n17]){
   tab_sprint_1_niveau__[n18].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n19] && nombre_ >cotation_tab_sprint_3[n17]){
   tab_sprint_1_niveau__[n19].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n20] && nombre_ >cotation_tab_sprint_3[n18]){
   tab_sprint_1_niveau__[n20].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n21] && nombre_ >cotation_tab_sprint_3[n20]){
   tab_sprint_1_niveau__[n21].push(tab_sprint_3[t]) ;
  }
  if(nombre_<=cotation_tab_sprint_3[n22] && nombre_ >cotation_tab_sprint_3[n21]){
   tab_sprint_1_niveau__[n22].push(tab_sprint_3[t]) ;
  }
  if(  nombre_ >cotation_tab_sprint_3[n22]){
   tab_sprint_1_niveau__[n23].push(tab_sprint_3[t]) ;
  } 
  
  }
   tab_sprint_1_niveau__[n25].push(  tab_sprint_1_niveau__[n24].sort()) ;
   
  return tab_sprint_1_niveau__ ; 
  
  }
