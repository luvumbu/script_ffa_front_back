
var tab_lancers = 
[   
 
"Disque (1.0 kg) | F" , 
"Disque (2.0 kg) | M" ,  
"Javelot (600 g) | F" , 
"Javelot (800 g) | M" ,  
"Marteau (4 kg) | F" , 
"Marteau (7.26 kg) | M" ,  
"Poids (7.26 kg) | M"
]
 



var tab_demi_fond = 
[   
"1 500m | F" , 
"1 500m | M" , 
"3 000m | F" , 
"3 000m | M" , 
"3000m Steeple (76) | F" , 
"3000m Steeple (91) | M" ,  
"800m | F" , 
"800m | M" , 

]



;
var tab_sprint = 
[   
"50m - Salle | F" ,
"60m - Salle | F",
"100m | F | Vent : VR",
"200m - Salle | F",
"400m - Salle | F"
]
/*

var tab_sprint = 
[   
"50m - Salle | F" ,
"60m - Salle | F",
"100m | F | Vent : VR",
"200m - Salle | F",
"400m - Salle | F",
"50m Haies (84)-Salle | F",
"60m Haies (84)-Salle | F",

"200m | F | Vent : VR",
"400m | F",
"100m Haies (84) | F | Vent : VR",
"400m Haies (76) | F" 
]

 */


// .... table cotation 

// D8   1
// D7   2  
// D6   3
// D5   4
// D4   5
// D3   6
// D2   7
// D1   8
// R6   9
// R5   10
// R4   11
// R2   12
// R1   13
// IR4  14
// IR3  15
// IR2  16
// IR1  17
// N4   18
// N3   19
// N2   20
// N1   21
// IB   22






// table cotation

 



 


var tab_font= 
[   
"1 500m | F" , 
"1 500m | M" , 
"10 000m | F" , 
"10 000m | M" , 
/*
"10 Km Route | F | Vent : VR" , 
"10 Km Route | M | Vent : VR" , 
 
"24 Heures | M" , 
 */
"5 000m | F" , 
"5 000m | M" , 
"5 Km Route | F | Vent : VR" , 
"5 Km Route | M | Vent : VR"  
 
 
]
 ; 
 

   

  var cotation_x = [
   // 50m
   [
   6.24,
   6.34,
   6.54,
   6.64,
   6.74,
   6.84,
   6.94,
   7.02,
   7.08,
   7.14,
   7.24,
   7.34,
   7.44,
   7.54,
   7.64,
   7.74,
   7.84,
   7.94,
   8.04,
   8.14,
   8.24,
   8.34,
   8.44
    ]  , 
    // 60m
    [
       7.24,
       7.34,
       7.44,
       7.64,
       7.84,
       7.94,
       8.04,
       8.14,
       8.24,
       8.34,
       8.44,
       8.64,
       8.74,
       8.94,
       9.04,
       9.14,
       9.24,
       9.34,
       9.44,
       9.64,
       9.74,
       9.94,
       10.14
           ] ,
           [

               // 100m
11.14,
11.34,
11.54,
11.84,
12.04,
12.24,
12.44,
12.64,
12.84,
13.04,
13.24,
13.44,
13.64,
13.84,
14.04,
14.24,
14.54,
14.94,
15.34,
15.74,
16.14,
16.54,
16.94
                              ],

                              [
                               // 400m
51.24,
52.04,
53.44,
54.74,
56.04,
57.64,
59.14,
60.74,
61.74,
62.74,
64.04,
65.34,
66.64,
67.94,
69.24,
70.54,
71.84,
73.14,
74.44,
75.54,
76.84,
78.24,
80.54

                       ] ,
                              [
                               // 200m
   22.74,
   23.14,
   23.74,
   24.24,
   24.74,
   25.24,
   25.74,
   26.24,
   26.74,
   27.24,
   27.74,
   28.24,
   28.74,
   29.24,
   29.74,
   30.34,
   30.94,
   31.54,
   32.14,
   32.74,
   33.24,
   33.74,
   34.24,

                            ] , 

                       [
                           //800m
   1.5984,
   2.0184,
   2.0600,
   2.1000,
   2.1300,
   2.1700,
   2.2000,
   2.2250,
   2.2500,
   2.2800,
   2.3100,
   2.3400,
   2.3700,
   2.4000,
   2.4400,
   2.4600,
   2.4800,
   2.5000,
   2.5300,
   2.5600,
   2.5900,
   3.0300,
   3.0800
   ] , 
   [
       // 1500m
4.0600,
4.1100,
4.2100,
4.2800,
4.3500,
4.4100,
4.4700,
4.5500,
5.0000,
5.0500,
3.1200,
3.1600,
3.1900,
3.2300,
3.2900,
3.3200,
3.3500,
3.3800,
3.4200,
3.4600,
3.5000,
3.5500,
4.0200

   ],
   [
       //3000m
   8.5300,
   9.0500,
   9.2000,
   9.3500,
   9.5000,
   10.0500,
   10.2000,
   10.4000,
   10.5000,
   11.0000,
   11.1000,
   11.2000,
   11.3000,
   11.4500,
   12.0000,
   12.1500,
   12.3000,
   12.4500,
   13.0000,
   13.2000,
   13.4000,
   14.1000,
   14.4000

   ] ,
   [
       //5000m
       15.1000,
       15.3000,
       16.3000,
       17.0000,
       17.2000,
       17.4000,
       18.0000,
       18.2000,
       18.4000,
       19.0000,
       19.2000,
       19.4000,
       20.0000,
       20.2000,
       20.4000,
       21.0000,
       21.3000,
       22.0000,
       22.4500,
       23.3000,
       24.0000,
       25.0000,
       26.0000
       ] ,

       [
           //10km 10 000m
   31.40,
   32.40,
   34.00,
   35.00,
   36.00,
   37.00,
   38.00,
   39.00,
   40.00,
   41.00,
   42.00,
   43.00,
   44.00,
   45.00,
   46.00,
   47.00,
   48.00,
   49.00,
   50.00,
   52.00,
   54.00,
   56.00,
   "1h00.00"
                                                               
   ],
[
       "50.00",
       "50.45",
       "52.30",
       "54.00",
       "55.30",
       "57.00",
       "58.30",
       "1h00.00",
       "1h01.30",
       "1h03'00",	         
       "1h04'30",
       "1h06'00",
       "1h07'30",
       "1h09'00",
       "1h10'30",
       "1h12'00",
       "1h14'00",
       "1h15'30",
       "1h17'00",
       "1h18'30",
       "1h20'00",
       "1h23'00",
       "1h26'00"
       ]                                                    


   ] ; 







    



