var tr = document.getElementsByTagName("tr");
var epreuve_sex =tr[2].innerText;
var taille = tr.length ; 
let taille2 = parseInt(taille);
var exe = false ;
var window_location_href2 ="" ; 

const getFullYear_ = new Date().getFullYear();
let getFullYear_verif ="x" ;  

//http://www.asoftech.com/ata/ 
/*

var get_result_users_perf =tr[nombre].children[2].innerText;
var get_users_nom_complet =tr[nombre].children[6].innerText);
var get_club_nom_complet =tr[nombre].children[8].innerText ;
var get_club_departement = tr[nombre].children[10].innerText ;
var get_club_region =tr[nombre].children[12].innerText;
var get_cat =tr[nombre].children[14].innerText ; 
var get_users_naissance = tr[nombre].children[16].innerText ;
var get_result_date_perf = tr[nombre].children[18].innerText ; 
var get_result_villes_nom =tr[nombre].children[20].innerText ; 


*/
var get_epreuve_nom_complet =tr[2].innerText;
var get_result_users_perf_array =[];
var get_users_nom_complet_array =[];
var get_club_nom_complet_array =[];
var get_club_departement_array =[];
var get_club_region_array =[];
var get_cat_array =[];
var get_users_naissance_array =[];
var get_result_date_perf_array =[];
var get_result_villes_nom_array =[];
var get_epreuve_nom_complet_array =[];


var get_rp_array_2=[] ;//2
var get_vent_array_2=[] ;//3
var get_epreuve_nom_complet_2 =tr[2].innerText;
var get_result_users_perf_array_2 =[];// 1
var get_users_nom_complet_array_2 =[];
var get_club_nom_complet_array_2 =[];
var get_club_departement_array_2 =[];
var get_club_region_array_2 =[];
var get_cat_array_2 =[];
var get_users_naissance_array_2 =[];
var get_result_date_perf_array_2 =[];
var get_result_villes_nom_array_2 =[];
var get_epreuve_nom_complet_array_2 =[];
var epreuve_sex_array_2=[];


var get_result_users_nom_1_array_2 = [];
var get_result_users_nom_2_array_2 = [];
var get_result_users_nom_3_array_2 = [];
var get_result_users_nom_4_array_2 = [];
var get_users_nationality_array_2 = [];


var get_epreuve_nom_complet =tr[2].innerText;
 for(var i = 3; i < taille-1 ; i ++){

 
    get_result_users_perf_array.push(tr[i].children[2].innerText);// ok
    get_users_nom_complet_array.push(tr[i].children[6].innerText);// ok
    get_club_nom_complet_array.push(tr[i].children[8].innerText);// ok
    get_club_departement_array.push(tr[i].children[10].innerText);    // ok
    get_club_region_array.push(tr[i].children[12].innerText); // ok 
    get_cat_array.push(tr[i].children[14].innerText); // ok 
    get_users_naissance_array.push(tr[i].children[16].innerText); // ok
    get_result_date_perf_array.push(tr[i].children[18].innerText);// ok
    get_result_villes_nom_array.push(tr[i].children[20].innerText);
 
        
        if(i==taille-2){
            exe =true;
        }
  
 
 } 
 class Get_result_users_nom {
    constructor(name) {
       this.name=name ; 
       this.get_result_users_nom_1 ="" ;
       this.get_result_users_nom_2 ="" ; 
       this.get_result_users_nom_3 ="" ; 
       this.get_result_users_nom_4 ="" ; 
  
       this.get_users_nationality ="";
  
  var space= 0;
  var etranger= 0;
   for(var i = 0 ; i<this.name.length; i++){
  
      console.log(this.name[i]);
      if( this.name[i]==" "){
          space ++ ; 
      }
      if( this.name[i]=="("){
          etranger ++ ; 
      }
   }
   var nom_1="";
   var nom_2="";
   var nom_3="";
   var nom_4="";
   var nom_5="";
  
   var verif_nom=0;
   var  verif_nombre = 1;
   var  etranger=false;
   
   for(var i = 0 ; i<this.name.length; i++){
      switch(verif_nombre) {
          case 1:            
              nom_1= nom_1+this.name[i];
              verif_nom=1;             
              break;
          case 2:
              nom_2= nom_2+this.name[i];
              verif_nom=2;             
              break;
          case 3:
              verif_nom=3;
              if(this.name[i]=="(" || this.name[i]==")"){
                  etranger =true;              
              }
              else{
                   nom_3= nom_3+this.name[i];
              } 
              break;
          case 4:
              verif_nom=4;
              if(this.name[i]=="(" || this.name[i]==")"){
                  etranger =true;
              }
              else{
                   nom_4= nom_4+this.name[i];
              }
              break;
          case 5:
              verif_nom=5;
              if(this.name[i]=="(" || this.name[i]==")"){
                  etranger =true;
              }
              else{
                   nom_5= nom_5+this.name[i];
              }
              break;
  }
      if(this.name[i]==" "){
          verif_nombre ++ ; 
      }  
  }
  /*
  
  alert(nom_1.length) ; 
  alert(nom_2.length) ; 
  alert(nom_3.length) ; 
  alert(nom_4.length) ; 
  alert(nom_5.length) ; 
  */
  
  
  
   /*
    if(space==1 && etranger==0){
      alert(nom_1) ; 
      alert(nom_2) ; 
  
    }
  
    */ 
   
  
  //alert(verif_nom);
   
  
  if(etranger==true){
    switch(verif_nom) {
    case 3:  
      
         this.get_result_users_nom_1 =nom_1;
         this.get_result_users_nom_2 ="";
         this.get_result_users_nom_3 =""; 
         this.get_result_users_nom_4 =nom_2;  
         this.get_users_nationality =nom_3; 
         
   
      break;
    case 4:
         this.get_result_users_nom_1 =nom_1;
         this.get_result_users_nom_2 =nom_2;
         this.get_result_users_nom_3 =""; 
         this.get_result_users_nom_4 =nom_3;   
         this.get_users_nationality =nom_4; 
         
  
      break;
      case 5:
         // nom_1 prenom1
         // nom_2 prenom2 
         // nom_3 prenom3 
         // nom_4 prenom4
         // nom_5 nom_de_famille 
         
         this.get_result_users_nom_1 =nom_1;
         this.get_result_users_nom_2 =nom_2;
         this.get_result_users_nom_3 =nom_3;
         this.get_result_users_nom_4 =nom_4;
         this.get_users_nationality = nom_5;
        
  
  
  
      break;
   
  
  } 
  }
  else{
  
   
  
         switch(verif_nom) {
    case 2:
         this.get_result_users_nom_1 =nom_1;
         this.get_result_users_nom_2 ="";
         this.get_result_users_nom_3 ="";
         this.get_result_users_nom_4 =nom_2;
         this.get_users_nationality ="FR";
      break;
    case 3:
         this.get_result_users_nom_1 =nom_1;
         this.get_result_users_nom_2 =nom_2; 
         this.get_result_users_nom_3 ="";     
         this.get_result_users_nom_4 =nom_3;
         this.get_users_nationality ="FR";
      break;
      case 4:
         this.get_result_users_nom_1 =nom_1;
         this.get_result_users_nom_2 =nom_2; 
         this.get_result_users_nom_3 =nom_3;     
         this.get_result_users_nom_4 =nom_4;
         this.get_users_nationality ="FR";
      break;
   
  }
   
  }
  
    }
    
    get get_result_users_nom_1_(){
              return this.get_result_users_nom_1 ; 
    }
    get get_result_users_nom_2_(){
              return this.get_result_users_nom_2 ; 
    }
    get get_result_users_nom_3_(){
              return this.get_result_users_nom_3 ; 
    }
    get get_result_users_nom_4_(){
              return this.get_result_users_nom_4 ; 
    }
    get get_result_users_nom_5_(){
              return this.get_result_users_nom_5 ; 
    }
    get get_users_nationality_(){
              return this.get_users_nationality ;
    }  
  }
 class Get_result_users_perf {
    constructor(name,element_1,element_2) {
  
      this.name = name;
      this.element_1 = element_1;
      this.element_2 = element_2;
  
      this.par1 ="" ; 
      this.par2 = "" ; 
  
  
      this.par3 ="" ; 
      this.par4 = "" ; 
  
  
      this.v1 = "" ; 
      this.v2 = "" ; 
  
  
      var veri_parametre1 = 0 ;
      this.get_result_users_perf= "" ;
  
  
  
  
  
   
  
  
  
  
      for(var i = 0 ; i<this.name.length;i++){
  
          if(veri_parametre1==0){
              
              if(this.name[i]!="("){
                      this.get_result_users_perf =this.get_result_users_perf+this.name[i];
              }
  
          }
       //   console.log(this.name[i]) ; 
          if(this.name[i]=="("){
              this.par1=i ; 
              veri_parametre1++;
              
          }
          if(this.name[i]==")"){
              this.par2=i ; 
              break;
          }
      }
  
      for(var i = 0 ; i<this.name.length;i++){
       //   console.log(this.name[i]) ; 
          if(this.name[i]=="("){
              this.par3=i ; 
          }
          if(this.name[i]==")"){
              this.par4=i ; 
             
          }
      }
  
      for(var o = this.par1+1;o<this.par2;o++){        
          this.v1=this.v1+this.name[o] ;        
      }
      for(var o = this.par3+1;o<this.par4;o++){        
          this.v2=this.v2+this.name[o] ;        
      }
  
      if(this.v1==this.v2){
          this.v2="";
      }
  
  
   
  
  
  
       
    }
    val_1() {
       
      return  this.v1;
    }
    val_2() {
       
       return  this.v2;
     }
    
  }
 class Replace_all {
    constructor(name1,name2,name3,name4,name5) {
      this.name1 = name1; 
      this.name2 = name2; 
      this.name3 = name3; 
      this.name4 = name4; 
      this.name5 = name5;   
  
      // mot recherche possibilite d'ajouter 5 maximum 
    }
  
    // fonction qui permet de charnger la valeur meme si on a deja donne par le constructeur
    set Change_name1(e){
      this.name1 = e ;
    }
    set Change_name2(e){
      this.name2 = e ;
    }
    set Change_name3(e){
      this.name3 = e ;
    }
    set Change_name4(e){
      this.name4 = e ;
    }
    set Change_name5(e){
      this.name5 = e ;
    }
  
     replace_name1(val1,val2) {
     
      if(val2.length==1){
            // val 1 est la valeur que nous recherchons et val 2 est la valeur a remplacer
                var element  ="";
                for(var i = 0 ; i<this.name1.length;i++){
                 // console.log(this.name1[i]) ;
                  
                  if(this.name1[i]==val1)
                  {
                    element = element+val2 ;
                  }
                  else {
                    element = element+this.name1[i] ;
                  }       
                }
                  this.name1 = element ; 
                
      }
      else {
        element = this.name1.replace(val1,val2); 
                        this.name1 = element ; 
      }
      
    }
  
  
  
  
    replace_name2(val1,val2) {
     
      if(val2.length==1){
            // val 1 est la valeur que nous recherchons et val 2 est la valeur a remplacer
                var element  ="";
                for(var i = 0 ; i<this.name2.length;i++){
                  //console.log(this.name2[i]) ;
                  
                  if(this.name2[i]==val1)
                  {
                    element = element+val2 ;
                  }
                  else {
                    element = element+this.name2[i] ;
                  }       
                }
                  this.name2 = element ; 
                
      }
      else {
        element = this.name2.replace(val1,val2); 
                        this.name2 = element ; 
      }
      
    }
  
    replace_name3(val1,val2) {
     
      if(val2.length==1){
            // val 1 est la valeur que nous recherchons et val 2 est la valeur a remplacer
                var element  ="";
                for(var i = 0 ; i<this.name3.length;i++){
                  //console.log(this.name3[i]) ;
                  
                  if(this.name3[i]==val1)
                  {
                    element = element+val2 ;
                  }
                  else {
                    element = element+this.name3[i] ;
                  }       
                }
                  this.name3 = element ; 
                
      }
      else {
        element = this.name3.replace(val1,val2); 
                        this.name3 = element ; 
      }
      
    }
  
    replace_name4(val1,val2) {
     
      if(val2.length==1){
            // val 1 est la valeur que nous recherchons et val 2 est la valeur a remplacer
                var element  ="";
                for(var i = 0 ; i<this.name4.length;i++){
                  //console.log(this.name4[i]) ;
                  
                  if(this.name4[i]==val1)
                  {
                    element = element+val2 ;
                  }
                  else {
                    element = element+this.name4[i] ;
                  }       
                }
                  this.name4 = element ; 
                
      }
      else {
        element = this.name4.replace(val1,val2); 
                        this.name4 = element ; 
      }
      
    }
  
    replace_name5(val1,val2) {
     
      if(val2.length==1){
            // val 1 est la valeur que nous recherchons et val 2 est la valeur a remplacer
                var element  ="";
                for(var i = 0 ; i<this.name5.length;i++){
                  //console.log(this.name5[i]) ;
                  
                  if(this.name5[i]==val1)
                  {
                    element = element+val2 ;
                  }
                  else {
                    element = element+this.name5[i] ;
                  }       
                }
                  this.name5 = element ; 
                
      }
      else {
        element = this.name5.replace(val1,val2); 
                        this.name5 = element ; 
      }
      
    }
    
    
    myReplace1() {
     
      return  this.name1;
    }
    myReplace2() {
     
      return  this.name2;
    }
    myReplace3() {
     
      return  this.name3;
    }
    myReplace4() {
     
      return  this.name4;
    }
    myReplace5() {
     
      return  this.name5;
    }
  }  
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
  class Sex {
  constructor(name ) {
    this.name = name;
    this.monsex ="F";
     this.verif =false ; 
    for(var t = 0 ; t<this.name.length;t++ ){
              if(name[t]=="M"){
                  this.verif =true ; 
                  this.monsex="M";
              } 
    }   
  }
  get Getsex(){
          return  this.monsex ;
  } 
}  
class Url_page_class {
        
  constructor(Url,tiempo) {
    this.Url = Url;
    this.numbers ="";  
    this.Url =    this.Url = window.location.href;
    var total_url=this.Url.length ; 
    var nombre = this.Url[total_url-1] ;      
    var elles =parseInt(nombre) ;
   




    const myTimeout = setTimeout(myGreeting, this.timer);

function myGreeting() {
    




    if(Number.isInteger(elles)==false){
        
        Url =Url +"&frmposition=0";
        window.location.replace(Url);
       
    }
    else {
        //window.location.replace(Url);

        var url_verif =false;
        var url_v1 ="" ;
        var url_v2 ="" ;

        var url_v3 ="" ;
        var url_v4 ="" ;

     for(var i = window.location.href.length ; i>-1;i--){
        console.log(window.location.href[i]) ; 
        if(window.location.href[i]=="="){
            url_verif = true ;            
        } 
        if(url_verif==true) {            
            
            if(window.location.href[i]!=undefined){
                url_v1 = url_v1+window.location.href[i] ; 
            }
        }
        else {
                if(window.location.href[i]!=undefined){
                    url_v2 = url_v2+window.location.href[i] ; 
                }
            }
     }

     for(var x =url_v2.length-1; x>-1;x--){
        url_v3 = url_v3+url_v2[x];
     }
     
     for(var y =url_v1.length; y>0;y-- ){      
      if(url_v1[y]!=undefined){
        url_v4=url_v4+url_v1[y] ;
      }
     }
 

     
 url_v3 =parseInt(url_v3);
 url_v3 = url_v3+1;
 
 var redirection =url_v4+"="+url_v3 ; 
 


var doc = document.getElementsByClassName("barSelect");
var page_total = doc[0].length;
var page_total_final1 ="";
var page_total_final2 ="";
var anne = "";
var t2 ="";
var ma_page ="";
var redirection_complette ="" ; 
//alert(page_total);
for(var t = 0 ; t<redirection.length; t++){
    var frmannee =redirection[t]+ redirection[t+1]+redirection[t+2]+redirection[t+3]+redirection[t+4]+redirection[t+5]+redirection[t+6]+redirection[t+7];
    var frmposition =redirection[t]+ redirection[t+1]+redirection[t+2]+redirection[t+3]+redirection[t+4]+redirection[t+5]+redirection[t+6]+redirection[t+7]+redirection[t+8]+redirection[t+9]+redirection[t+10];
    
        
        if(frmannee=="frmannee"){
            //alert(frmannee) ;
            
            
            t2 = t +9; 
             

            t =t +14 ; 
            anne = redirection[t2]+ redirection[t2+1]+redirection[t2+2]+redirection[t2+3];

            anne= parseInt(anne);
            var anne2= parseInt(anne)+1;

            page_total_final1 = page_total_final1+redirection[t]+"rmannee="+anne+"&f" ;
            page_total_final2 = page_total_final2+redirection[t]+"rmannee="+anne2+"&f" ;
            
        }
        else {
            page_total_final1 = page_total_final1+redirection[t];
            page_total_final2 = page_total_final2+redirection[t];
        }
       
var decimales =0;
        if(frmposition=="frmposition"){
            if(redirection[t+12]!=undefined){
                decimales = 1;
                
            } 

            if(redirection[t+13]!=undefined){
                decimales = 2;
            } 
            if(redirection[t+14]!=undefined){
                decimales = 3;
            } 
            if(redirection[t+15]!=undefined){
                decimales = 3;
            } 
            if(redirection[t+16]!=undefined){
                decimales = 4;
            } 

     


            var total = redirection[t+12];
             
 

            switch(decimales) {
                case 1:
                    
                    ma_page=redirection[t+12];
                  break;
                case 2:
                    ma_page=redirection[t+12]+redirection[t+13];

                  break;
                  case 3:
                    ma_page=redirection[t+12]+redirection[t+13]+redirection[t+14];
                 break;
                 case 4:
                    ma_page=redirection[t+12]+redirection[t+13]+redirection[t+14]+redirection[t+15];
                break;
                default:
                  // code block
              }
break ; 
            

        }

         
  
} 



 
if(getFullYear_==anne){
 // getFullYear_verif =1;
 
//let person = prompt("Voulez vous envoyer la data quand meme y pour envoyer ");
let person = "y" ; 
if(person=="y"){
  getFullYear_verif = 1 ; 
}
else {
  getFullYear_verif = 0 ; 

}
}
/*
else{
  getFullYear_verif =0 ; 
}
 */



 





if(page_total!=url_v3){
  
    
 //console.log(url_v4+"&frmposition=0");
 console.log(page_total_final1+"rmposition="+ma_page) ;
 //alert('anne suivante') ; 
  
  redirection_complette=page_total_final1+"rmposition="+ma_page;


  window_location_href2 = page_total_final1+"rmposition="+ma_page;

 
}
else{
     
   //console.log(url_v4+"&frmposition=0");
   console.log(page_total_final2+"rmposition=0") ;
   //alert("cette anne") ;    
 redirection_complette = page_total_final2+"rmposition=0";   

 window_location_href2 = page_total_final2+"rmposition=0";   

 

}
const myTimeout = setTimeout(myGreeting, tiempo);
function myGreeting() {
   window.location.replace(redirection_complette); 
}
}
} 
  }
 
}

var window_location_href =window.location.href ; 


var redirection = new Url_page_class(window_location_href ,5000); 

let mySex = new Sex(epreuve_sex);
//mySex.Getsex

 


var y = -1 ; 

 

 



const myTimeout = setTimeout(myGreeting, 1000);

function myGreeting() {

   

    
    for(var x = 0 ; x<taille-4 ;x++ ){
    
  
        var el1 = get_result_users_perf_array[x] ; 
          
        
      let myCar1 = new Get_result_users_perf(el1,"(",")");
 
      var perf =myCar1.get_result_users_perf;
      
        
      let myReplace = new Replace_all(perf);  
          myReplace.replace_name1("'",".") ;

         
          
          

       
          //remplace i par y 
          get_rp_array_2.push(myCar1.v2);
          get_vent_array_2.push(myCar1.v1);
        
       //   get_result_users_perf_array_2.push(myReplace.myReplace1());
      //el1
      console.log("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!") ; 

    
      console.log("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!") ; 

        get_result_users_perf_array_2.push(myReplace.myReplace1().replace("..", "."));
       


      let myCar2 = new Get_result_users_nom(get_users_nom_complet_array[x]);
      
      get_result_users_nom_1_array_2.push(myCar2.get_result_users_nom_1);
      get_result_users_nom_2_array_2.push(myCar2.get_result_users_nom_2);
      get_result_users_nom_3_array_2.push(myCar2.get_result_users_nom_3);
      get_result_users_nom_4_array_2.push(myCar2.get_result_users_nom_4);
      get_users_nationality_array_2.push(myCar2.get_users_nationality);


      epreuve_sex_array_2=mySex.Getsex;
    
      
      // ajout nom du club array2
      
      get_club_nom_complet_array_2.push(get_club_nom_complet_array[x]);
      get_club_departement_array_2.push(get_club_departement_array[x]);
      get_club_region_array_2.push(get_club_region_array[x]);
      get_cat_array_2.push(get_cat_array[x]);
      get_users_naissance_array_2.push(get_users_naissance_array[x]);
      get_result_date_perf_array_2.push(get_result_date_perf_array[x]);
      get_result_villes_nom_array_2.push(get_result_villes_nom_array[x]);
    
        }
    console.log(get_result_users_nom_4_array_2) ; 




    var ok_ = new Information("https://bokonzi.com/ffa/script_all/exe_exe/exe_cut.php"); // création de la classe 
ok_.add("get_result_users_nom_1_array_2", get_result_users_nom_1_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_result_users_nom_2_array_2", get_result_users_nom_2_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_result_users_nom_3_array_2", get_result_users_nom_3_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_result_users_nom_4_array_2", get_result_users_nom_4_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_users_nationality_array_2", get_users_nationality_array_2); // ajout de l'information pour lenvoi 
ok_.add("epreuve_sex_array_2", epreuve_sex_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_club_nom_complet_array_2", get_club_nom_complet_array_2); // ajout de l'information pour lenvoi 

ok_.add("get_club_departement_array_2", get_club_departement_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_club_region_array_2", get_club_region_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_cat_array_2", get_cat_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_users_naissance_array_2", get_users_naissance_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_result_date_perf_array_2", get_result_date_perf_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_result_villes_nom_array_2", get_result_villes_nom_array_2); // ajout de l'information pour lenvoi 



ok_.add("get_rp_array_2", get_rp_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_vent_array_2", get_vent_array_2); // ajout de l'information pour lenvoi 
ok_.add("get_result_users_perf_array_2", get_result_users_perf_array_2); // ajout de l'information pour lenvoi 


ok_.add("getFullYear_verif", getFullYear_verif); // ajout de l'information pour lenvoi 



 
ok_.add("get_users_nom_complet_array",get_users_nom_complet_array); // ajout de l'information pour lenvoi 
ok_.add("get_club_nom_complet_array",get_club_nom_complet_array); // ajout de l'information pour lenvoi 
ok_.add("get_club_departement_array",get_club_departement_array); // ajout de l'information pour lenvoi 
ok_.add("get_club_region_array",get_club_region_array); // ajout de l'information pour lenvoi 
ok_.add("get_cat_array",get_cat_array); // ajout de l'information pour lenvoi 
ok_.add("get_users_naissance_array",get_users_naissance_array); // ajout de l'information pour lenvoi 
ok_.add("get_result_date_perf_array",get_result_date_perf_array); // ajout de l'information pour lenvoi 
ok_.add("get_result_villes_nom_array",get_result_villes_nom_array); // ajout de l'information pour lenvoi 
ok_.add("window_location_href",window_location_href); // ajout de l'information pour lenvoi 
ok_.add("window_location_href2",window_location_href2); // ajout de l'information pour lenvoi 




let text = get_epreuve_nom_complet ;
let position = text.search("Salle");



if(position==-1){
  ok_.add("get_emplacement","Piste"); // ajout de l'information pour lenvoi 

}
else{
  
ok_.add("get_emplacement","Salle"); // ajout de l'information pour lenvoi 

}



ok_.add("get_epreuve_nom_complet",get_epreuve_nom_complet); // ajout de l'information pour lenvoi 







 

 


 
 
console.log(ok_.info()); // demande l'information dans le tableau
ok_.push(); // envoie l'information au code pkp 


console.log("SEND") ; 
 
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
window_location_href
epreuve_sex_array_2
get_users_nom_complet_array
lettre
get_epreuve_nom_complet
get_code_result

*/

