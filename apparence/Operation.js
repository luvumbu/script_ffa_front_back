 
class Operation {
    constructor(obj,tab_cotation,recherche_element) {
      
      this.obj = obj;  
      this.count_max = 0;
      this.tab_cotation = tab_cotation; 
      this.recherche_element = recherche_element; 


      this.i_level = 0 ;
      this.n_level = 0 ;
      this.ir_level = 0 ;
      this.r_level = 0 ;
      this.d_level = 0 ;





       




      
      this.x = [];
      this.x2 = [];  
      this.x=  new Array(30) ;
      this.x2=  new Array(30) ;  
      
      for (var i=0; i < 30; i++){  
          this.x[i] = new Array() ; 
          this.x2[i] = new Array() ;   
  
      }  
  
    }
    boucle_() {
  
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
  
  
   
      
     
  
      //console.log( this.tab_cotation) ; 




      

     
          
      for(var x = 0 ; x < this.obj.length ; x ++){
  
  
   
      
        this.count_max  ++ ; 
        
      
        // test 
  
       var  nombre_ = this.obj[x].get_result_users_perf_array_2 ; 
       var  nombre_2 = this.obj[x] ; 
  
     
        

  if(this.obj[x].get_epreuve_nom_complet==this.recherche_element){

  if(nombre_<=this.tab_cotation[n0] ){
  
   // console.log(this.obj[x].get_result_users_perf_array_2) ; 
  
   this.x[n0].push(nombre_) ; 
   this.x2[n0].push(nombre_2) ;    

   this.i_level  ++;


   //
 
   }
  
  
   if(nombre_<=this.tab_cotation[n1] && nombre_ >this.tab_cotation[n0]){
    this.x[n1].push(nombre_) ;
    this.x2[n1].push(nombre_2) ;  


   this.i_level  ++;

 







    
    
  
   }
   if(nombre_<=this.tab_cotation[n2] && nombre_ >this.tab_cotation[n1]){
    this.x[n2].push(nombre_) ; 
    this.x2[n2].push(nombre_2) ;   
   
    //this.n_level ++ ;

   this.i_level  ++;

   
   }
   if(nombre_<=this.tab_cotation[n3] && nombre_ >this.tab_cotation[n2]){
    this.x[n3].push(nombre_) ; 
    this.x2[n3].push(nombre_2) ; 
    
     
    this.n_level ++ ;
 

   }
   if(nombre_<=this.tab_cotation[n4] && nombre_ >this.tab_cotation[n3]){
    this.x[n4].push(nombre_) ;
    this.x2[n4].push(nombre_2) ; 
    
 
    this.n_level ++ ;
   
   }
   if(nombre_<=this.tab_cotation[n5] && nombre_ >this.tab_cotation[n4]){
    this.x[n5].push(nombre_) ; 
    this.x2[n5].push(nombre_2) ; 
    
    
    this.n_level ++ ;
 
   }
   if(nombre_<=this.tab_cotation[n6] && nombre_ >this.tab_cotation[n5]){
    this.x[n6].push(nombre_) ; 
    this.x2[n6].push(nombre_2) ; 
    
  
    this.n_level ++ ;
   
   }
   if(nombre_<=this.tab_cotation[n7] && nombre_ >this.tab_cotation[n6]){
    this.x[n7].push(nombre_) ; 
    this.x2[n7].push(nombre_2) ;
    
    
    this.ir_level ++ ;
     
   }
  
   if(nombre_<=this.tab_cotation[n8] && nombre_ >this.tab_cotation[n7]){
    this.x[n8].push(nombre_) ; 
    this.x2[n8].push(nombre_2) ; 
    
  
    this.ir_level ++ ;
 
   }
   if(nombre_<=this.tab_cotation[n9] && nombre_ >this.tab_cotation[n8]){
    this.x[n9].push(nombre_) ; 
    this.x2[n9].push(nombre_2) ; 
  
    this.ir_level ++ ;
  
   }
   if(nombre_<=this.tab_cotation[n10] && nombre_ >this.tab_cotation[n9]){
    this.x[n10].push(nombre_) ; 
    this.x2[n10].push(nombre_2) ;  


 
    this.ir_level ++ ;
    
   }
   if(nombre_<=this.tab_cotation[n11] && nombre_ >this.tab_cotation[n10]){
    this.x[n11].push(nombre_) ; 
    this.x2[n11].push(nombre_2) ; 
    
  
this.r_level ++ ;
  
   }
   if(nombre_<=this.tab_cotation[n12] && nombre_ >this.tab_cotation[n11]){
    this.x[n12].push(nombre_) ; 
    this.x2[n13].push(nombre_2) ; 
    
 
  this.r_level ++ ;
   
   }
   if(nombre_<=this.tab_cotation[n13] && nombre_ >this.tab_cotation[n12]){
    this.x[n13].push(nombre_) ; 
    this.x[n13].push(nombre_2) ;  
 
  this.r_level ++ ;
    
   }
   if(nombre_<=this.tab_cotation[n14] && nombre_ >this.tab_cotation[n13]){
    this.x[n14].push(nombre_) ; 
    this.x[n14].push(nombre_2) ; 
    
 
  this.r_level ++ ;
   
   }
     
   // 
   if(nombre_<=this.tab_cotation[n15] && nombre_ >this.tab_cotation[n14]){
    this.x[n15].push(nombre_) ; 
    this.x[n15].push(nombre_2) ; 
    
   
  this.r_level ++ ;
   
   }
   if(nombre_<=this.tab_cotation[n16] && nombre_ >this.tab_cotation[n15]){
    this.x[n16].push(nombre_) ; 
    this.x2[n16].push(nombre_2) ;  
 
     this.d_level ++ ;
    
   }
   if(nombre_<=this.tab_cotation[n17] && nombre_ >this.tab_cotation[n16]){
    this.x[n17].push(nombre_) ; 
    this.x2[n17].push(nombre_2) ; 
    
 
     this.d_level ++ ;
    
   }
   if(nombre_<=this.tab_cotation[n18] && nombre_ >this.tab_cotation[n17]){
    this.x[n18].push(nombre_) ; 
    this.x2[n18].push(nombre_2) ; 
    
 
     this.d_level ++ ;
   }
   if(nombre_<=this.tab_cotation[n19] && nombre_ >this.tab_cotation[n18]){
    this.x[n19].push(nombre_) ; 
    this.x2[n19].push(nombre_2) ;  
   
     this.d_level ++ ; 
   }
   if(nombre_<=this.tab_cotation[n20] && nombre_ >this.tab_cotation[n19]){
    this.x[n20].push(nombre_) ; 
    this.x2[n20].push(nombre_2) ;
 
     this.d_level ++ ;   
   }
   if(nombre_<=this.tab_cotation[n21] && nombre_ >this.tab_cotation[n20]){
    this.x[n21].push(nombre_) ; 
    this.x2[n21].push(nombre_2) ; 
    
     this.d_level ++ ;  
   }
   if(nombre_<=this.tab_cotation[n22] && nombre_ >this.tab_cotation[n21]){
    this.x[n22].push(nombre_) ; 
    this.x2[n22].push(nombre_2) ;   
  
     this.d_level ++ ;
   }
   if(  nombre_ >this.tab_cotation[n22]){
    this.x[n23].push(nombre_) ; 
    this.x2[n23].push(nombre_2) ;   
 
     this.d_level ++ ;


 

   
   } 
  
   
   this.x[n24].push(nombre_) ; 
   this.x2[n24].push(nombre_2) ; 

    
  }
   

 
          
  }


  
}
  
    
  
    count_max_() {
       
      return this.count_max;
    }
  
    
    myChart_(name) {
 
 

 


    var xValues = ["ItaInternational", "National", "Regional", "Inter regional", "Departemental"];


  

var yValues = [this.i_level,this.n_level ,  this.ir_level,this.r_level ,this.d_level ];
var barColors = ["#7dced1", "#ffcc00","#99ff99","#ccffff","#ffcc99"];

new Chart(name, {
  type: "bar",
  data: {
    labels: xValues,
    datasets: [{
      backgroundColor: barColors,
      data: yValues
    }]
  },
  options: {
    legend: {display: false},
    title: {
      display: true,
      text: "World Wine Production 2018"
    }
  }
});




  }
   
  
    x_() {
       
      return   this.x;
    }
    x_2() {
       
      return   this.x2;
    }
  }
  