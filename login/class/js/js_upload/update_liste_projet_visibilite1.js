function update_liste_projet_visibilite1(_this){
    console.log("update_liste_projet_visibilite1") ; 
    var ok = new Information("class/php/php_update/update_liste_projet_visibilite1.php"); // création de la classe 
    ok.add("liste_projet_id_sha1", _this.title); // ajout de l'information pour lenvoi
   if( _this.src==src_img3_2){
        _this.src = src_img3 ; 
       ok.add("value_", ""); // ajout de l'information pour lenvoi 
    }
    else {
        _this.src = src_img3_2 ; 
        ok.add("value_", "i"); // ajout de l'information pour lenvoi 
 
    }

    console.log(ok.info()); // demande l'information dans le tableau
        ok.push(); // envoie l'information au code pkp 
 
}