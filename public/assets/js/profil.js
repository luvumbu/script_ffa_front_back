/**
 * profil.js — Profile page JavaScript (bio builder + profile charts)
 * Extracted from index.php (lines ~2033-2584)
 *
 * Dependencies (must be set inline in index.php via PHP before this file):
 *   - ATHLETE_DATA (global) — <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>
 *   - profilProgDetail (global) — <?= json_encode($progDetail) ?>
 *   - profilProgByYear (global) — <?= json_encode($progByEpreuve) ?>
 *   - profColors (global) — <?= json_encode($colors) ?>
 *   - profilMedData (global) — { or: N, argent: N, bronze: N }
 *   - profilResByYear (global) — { labels: [...], data: [...] }
 *
 * Dependencies (must be loaded before this file):
 *   - Chart.js 4.4.7
 *   - escapeHtml(), dateFR(), _nivBadge(), _highestNiveau() from utils.js
 */

// === Bio year filter state ===
var _bioSelectedYears = [];
var _bioAvailableYears = [];

function _bioCollectYears(d) {
    var ys = {};
    (d.resultats||[]).forEach(function(r){if(r.annee)ys[r.annee]=1;});
    (d.progressions||[]).forEach(function(p){if(p.annee)ys[p.annee]=1;});
    (d.podiums||[]).forEach(function(p){if(p.annee)ys[p.annee]=1;});
    (d.medailles||[]).forEach(function(m){if(m.annee)ys[m.annee]=1;});
    (d.niveaux||[]).forEach(function(n){if(n.annee)ys[n.annee]=1;});
    (d.selections||[]).forEach(function(s){if(s.date){var y=parseInt(s.date.substring(0,4));if(y>0)ys[y]=1;}});
    (d.records||[]).forEach(function(r){if(r.date){var y=parseInt(r.date.substring(0,4));if(y>0)ys[y]=1;}});
    return Object.keys(ys).map(Number).sort(function(a,b){return a-b;});
}

function _bioRenderYearSelector() {
    var c = document.getElementById('bioYearSelector'); if(!c) return;
    var isTotal = _bioSelectedYears.length === 0;
    var h = '<button onclick="_bioSelectTotal()" style="padding:6px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid '+(isTotal?'#6c5ce7':'#1a2540')+';background:'+(isTotal?'linear-gradient(135deg,#6c5ce7,#5541d0)':'#080c14')+';color:'+(isTotal?'#fff':'#8b949e')+';">Total</button>';
    h += '<span style="color:#253049;font-size:18px;">|</span>';
    _bioAvailableYears.forEach(function(y){
        var sel = _bioSelectedYears.indexOf(y)!==-1;
        h += '<button onclick="_bioToggleYear('+y+')" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid '+(sel?'#6c5ce7':'#1a2540')+';background:'+(sel?'linear-gradient(135deg,#6c5ce7,#5541d0)':'#080c14')+';color:'+(sel?'#fff':'#8b949e')+';transition:all .2s;">'+y+'</button>';
    });
    if(_bioSelectedYears.length>0) h+='<span style="color:#5a6580;font-size:12px;margin-left:4px;">'+_bioSelectedYears.length+'/6 ann\u00e9es</span>';
    c.innerHTML = h;
}

function _bioSelectTotal(){_bioSelectedYears=[];_bioRenderYearSelector();_bioRebuild();}
function _bioToggleYear(y){
    var idx=_bioSelectedYears.indexOf(y);
    if(idx!==-1){_bioSelectedYears.splice(idx,1);}
    else{if(_bioSelectedYears.length>=6){alert('Maximum 6 ann\u00e9es');return;}_bioSelectedYears.push(y);_bioSelectedYears.sort(function(a,b){return a-b;});}
    _bioRenderYearSelector();_bioRebuild();
}
function _bioRebuild(){var el=document.getElementById('bioText');if(el)el.textContent=buildAthleteBio(ATHLETE_DATA,_bioSelectedYears);}

function buildAthleteBio(data, selectedYears) {
    var filterByYear = selectedYears.length > 0;
    var yearSet = {}; selectedYears.forEach(function(y){yearSet[y]=true;});
    function inYears(a){if(!filterByYear)return true;return yearSet[a]===true;}
    function dateInYears(d){if(!filterByYear)return true;if(!d)return false;var y=parseInt(d.substring(0,4));return yearSet[y]===true;}

    var i = data.identite;
    var eF = i.sexe==='F'?'e':'';
    var ilElle = i.sexe==='M'?'Il':'Elle';
    var ilElleMin = i.sexe==='M'?'il':'elle';
    var sonSa = i.sexe==='M'?'son':'sa';
    var bio = [];

    var natMap = {'FRA':'fran\u00e7ais'+eF,'MAR':'marocain'+eF,'SEN':'s\u00e9n\u00e9galais'+eF,'CMR':'camerounais'+eF,'ALG':'alg\u00e9rien'+(i.sexe==='F'?'ne':''),'TUN':'tunisien'+(i.sexe==='F'?'ne':''),'BEL':'belge','SUI':'suisse','CIV':'ivoirien'+(i.sexe==='F'?'ne':''),'GBR':'britannique','USA':'am\u00e9ricain'+eF,'ESP':'espagnol'+eF,'ITA':'italien'+(i.sexe==='F'?'ne':''),'POR':'portugais'+eF,'GER':'allemand'+eF,'BRA':'br\u00e9silien'+(i.sexe==='F'?'ne':''),'JAM':'jama\u00efcain'+eF,'HAI':'ha\u00eftien'+(i.sexe==='F'?'ne':''),'COD':'congolais'+eF,'COG':'congolais'+eF,'MLI':'malien'+(i.sexe==='F'?'ne':''),'GIN':'guin\u00e9en'+(i.sexe==='F'?'ne':''),'GAB':'gabonais'+eF,'BUR':'burkinab\u00e8','NIG':'nig\u00e9rien'+(i.sexe==='F'?'ne':''),'BEN':'b\u00e9ninois'+eF,'TOG':'togolais'+eF,'RWA':'rwandais'+eF,'MAD':'malgache','LUX':'luxembourgeois'+eF,'NED':'n\u00e9erlandais'+eF,'ROU':'roumain'+eF,'POL':'polonais'+eF,'GRE':'grec'+(i.sexe==='F'?'que':''),'TUR':'turc'+(i.sexe==='F'?'que':''),'KEN':'k\u00e9nyan'+eF,'ETH':'\u00e9thiopien'+(i.sexe==='F'?'ne':''),'RSA':'sud-africain'+eF,'JPN':'japonais'+eF,'CHN':'chinois'+eF,'AUS':'australien'+(i.sexe==='F'?'ne':''),'CAN':'canadien'+(i.sexe==='F'?'ne':''),'MEX':'mexicain'+eF,'COL':'colombien'+(i.sexe==='F'?'ne':''),'ARG':'argentin'+eF,'CHI':'chilien'+(i.sexe==='F'?'ne':''),'CUB':'cubain'+eF,'DOM':'dominicain'+eF,'TRI':'trinidadien'+(i.sexe==='F'?'ne':''),'BAH':'baham\u00e9en'+(i.sexe==='F'?'ne':'')};
    var catMap = {'SE':'Senior','ES':'Espoir','JU':'Junior','CA':'Cadet'+(i.sexe==='F'?'te':''),'MI':'Minime','BE':'Benjamin'+eF,'PO':'Poussin'+eF,'EA':'\u00c9veil athl\u00e9tique','MA':'Master','V1':'V\u00e9t\u00e9ran','V2':'V\u00e9t\u00e9ran','V3':'V\u00e9t\u00e9ran','V4':'V\u00e9t\u00e9ran','V5':'V\u00e9t\u00e9ran'};
    var nivMap = {'N1':'Niveau National 1 (\u00c9lite)','N2':'Niveau National 2','N3':'Niveau National 3','N4':'Niveau National 4','R1':'Niveau R\u00e9gional 1','R2':'Niveau R\u00e9gional 2','R3':'Niveau R\u00e9gional 3','R4':'Niveau R\u00e9gional 4','R5':'Niveau R\u00e9gional 5','R6':'Niveau R\u00e9gional 6','D1':'Niveau D\u00e9partemental 1','D2':'Niveau D\u00e9partemental 2','D3':'Niveau D\u00e9partemental 3','D4':'Niveau D\u00e9partemental 4','D5':'Niveau D\u00e9partemental 5','D6':'Niveau D\u00e9partemental 6','D7':'Niveau D\u00e9partemental 7','IR':'Interr\u00e9gional','IE':'International \u00c9lite'};

    // Filtrer les donn\u00e9es par ann\u00e9e
    var fResultats = (data.resultats||[]).filter(function(r){return inYears(r.annee);});
    var fProgressions = (data.progressions||[]).filter(function(p){return inYears(p.annee);});
    var fPodiums = (data.podiums||[]).filter(function(p){return inYears(p.annee);});
    var fMedailles = (data.medailles||[]).filter(function(m){return inYears(m.annee);});
    var fNiveaux = (data.niveaux||[]).filter(function(n){return inYears(n.annee);});
    var fSelections = (data.selections||[]).filter(function(s){return dateInYears(s.date);});
    var fRecords = (data.records||[]).filter(function(r){return dateInYears(r.date);});
    var fClubs = (data.clubs||[]).filter(function(c){
        if(!filterByYear)return true;
        for(var yy in yearSet){var y=parseInt(yy);var d=c.annee_debut||0;var f=c.annee_fin||9999;if(y>=d&&y<=f)return true;}
        return false;
    });

    // Ann\u00e9es d'activit\u00e9
    var derniereAnnee=0, premiereAnnee=9999;
    fResultats.forEach(function(r){if(r.annee>derniereAnnee)derniereAnnee=r.annee;if(r.annee>0&&r.annee<premiereAnnee)premiereAnnee=r.annee;});
    fProgressions.forEach(function(p){if(p.annee>derniereAnnee)derniereAnnee=p.annee;if(p.annee>0&&p.annee<premiereAnnee)premiereAnnee=p.annee;});
    fPodiums.forEach(function(p){if(p.annee>derniereAnnee)derniereAnnee=p.annee;if(p.annee>0&&p.annee<premiereAnnee)premiereAnnee=p.annee;});
    fMedailles.forEach(function(m){if(m.annee>derniereAnnee)derniereAnnee=m.annee;if(m.annee>0&&m.annee<premiereAnnee)premiereAnnee=m.annee;});
    fClubs.forEach(function(c){if(c.annee_debut&&c.annee_debut<premiereAnnee)premiereAnnee=c.annee_debut;});
    if(premiereAnnee===9999)premiereAnnee=0;
    var currentYear=new Date().getFullYear();
    var carriereTerminee=(derniereAnnee>0&&(currentYear-derniereAnnee)>2);

    // \u00c2ge
    var age=null;
    if(i.date_naissance){var bd=new Date(i.date_naissance);var td=new Date();age=td.getFullYear()-bd.getFullYear();var mo=td.getMonth()-bd.getMonth();if(mo<0||(mo===0&&td.getDate()<bd.getDate()))age--;}
    else if(i.annee_naissance){age=currentYear-i.annee_naissance;}

    // === 1. IDENTIT\u00c9 ===
    var intro = i.nom_complet;
    if(carriereTerminee){intro+=' est un'+eF+' ancien'+(i.sexe==='F'?'ne':'')+' athl\u00e8te';}
    else{intro+=' est un'+eF+' athl\u00e8te';}
    if(i.nationalite&&natMap[i.nationalite]){intro+=' '+natMap[i.nationalite];}
    else if(i.nationalite){intro+=' de nationalit\u00e9 '+i.nationalite;}
    if(i.categorie&&catMap[i.categorie]){intro+=' \u00e9voluant en cat\u00e9gorie '+catMap[i.categorie];}
    if(i.date_naissance||i.annee_naissance){
        intro+=', n\u00e9'+eF;
        intro+=' en '+(i.date_naissance?i.date_naissance.substring(0,4):i.annee_naissance);
        if(i.lieu_naissance)intro+=' \u00e0 '+i.lieu_naissance;
        if(age)intro+=' ('+age+' ans)';
    }
    if(i.taille_cm&&i.poids_kg){intro+=', mesurant '+(i.taille_cm/100).toFixed(2).replace('.',',')+' m pour '+i.poids_kg+' kg';}
    else if(i.taille_cm){intro+=', mesurant '+(i.taille_cm/100).toFixed(2).replace('.',',')+' m';}
    intro+='.';
    bio.push(intro);

    // === 2. CONTEXTE ANN\u00c9E ===
    if(filterByYear){
        if(selectedYears.length===1)bio.push('Ce r\u00e9sum\u00e9 couvre la saison '+selectedYears[0]+'.');
        else bio.push('Ce r\u00e9sum\u00e9 couvre les saisons '+selectedYears.join(', ')+'.');
    }

    // === 3. CARRI\u00c8RE ET CLUBS ===
    if(fClubs.length>0){
        var nbClubs=fClubs.length, clubRecent=fClubs[0], clubAncien=fClubs[nbClubs-1];
        var dureeCarriere=(premiereAnnee&&derniereAnnee)?(derniereAnnee-premiereAnnee):0;
        if(filterByYear){
            if(nbClubs===1)bio.push(ilElle+' \u00e9voluait au sein du club '+clubRecent.nom_club+'.');
            else{var cn=fClubs.map(function(c){return c.nom_club;});bio.push(ilElle+' \u00e9voluait au sein de '+nbClubs+' clubs : '+(cn.length<=3?cn.join(', '):cn.slice(0,3).join(', ')+' et '+(nbClubs-3)+' autre'+(nbClubs-3>1?'s':''))+'.');}
        }else{
            var uneSeuleAnnee=(dureeCarriere===0&&premiereAnnee>0);
            if(uneSeuleAnnee){
                var pc=ilElle+' n\'a effectu\u00e9 qu\'une seule saison en '+premiereAnnee;
                if(nbClubs===1)pc+=' au sein du club '+clubRecent.nom_club;
                pc+='.'; bio.push(pc);
            }else if(carriereTerminee){
                var pc=ilElle+' a men\u00e9 '+sonSa+' carri\u00e8re';
                if(premiereAnnee)pc+=' de '+premiereAnnee+' \u00e0 '+derniereAnnee;
                if(dureeCarriere>0)pc+=' ('+dureeCarriere+' ans d\'activit\u00e9)';
                if(nbClubs===1)pc+=' au sein du club '+clubRecent.nom_club;
                else pc+=', passant par '+nbClubs+' clubs';
                pc+='. '+ilElle+' a mis fin \u00e0 '+sonSa+' carri\u00e8re sportive en '+derniereAnnee+'.';
                bio.push(pc);
            }else{
                var pc;
                if(nbClubs===1){pc=ilElle+' \u00e9volue au '+clubRecent.nom_club;if(clubRecent.annee_debut)pc+=' depuis '+clubRecent.annee_debut;pc+='.';}
                else{
                    pc='Form\u00e9'+eF+' au '+clubAncien.nom_club;
                    if(clubAncien.annee_debut)pc+=' d\u00e8s '+clubAncien.annee_debut;
                    pc+=', '+ilElleMin+' \u00e9volue d\u00e9sormais au '+clubRecent.nom_club;
                    if(clubRecent.annee_debut)pc+=' depuis '+clubRecent.annee_debut;
                    if(nbClubs>2)pc+=' apr\u00e8s \u00eatre pass\u00e9'+eF+' par '+(nbClubs-2)+' autre'+(nbClubs-2>1?'s':'')+' club'+(nbClubs-2>1?'s':'');
                    pc+='.';
                }
                if(premiereAnnee){var dur=currentYear-premiereAnnee;if(dur>1)pc+=' Sa carri\u00e8re s\'\u00e9tend sur '+dur+' saisons.';else if(dur<=1)pc+=' '+ilElle+' en est \u00e0 '+sonSa+' premi\u00e8re saison.';}
                bio.push(pc);
            }
        }
    }

    // === 4. DISCIPLINES ET RECORDS ===
    var recordsToUse = filterByYear ? fRecords : (data.records||[]);
    if(recordsToUse.length>0){
        var recsByEp={};
        recordsToUse.forEach(function(r){if(r.epreuve&&r.performance_brut)recsByEp[r.epreuve]=r;});
        var epNames=Object.keys(recsByEp), nbEp=epNames.length;
        if(nbEp>0){
            var pr;
            if(nbEp===1){
                var rec=recsByEp[epNames[0]];
                pr=ilElle+' est sp\u00e9cialis\u00e9'+eF+' sur le '+epNames[0]+' o\u00f9 '+ilElleMin+' d\u00e9tient un record personnel de '+rec.performance_brut;
                if(rec.lieu)pr+=' r\u00e9alis\u00e9 \u00e0 '+rec.lieu;
                pr+='.';
            }else if(nbEp<=3){
                var rd=[];for(var ep in recsByEp){rd.push(recsByEp[ep].performance_brut+' au '+ep+(recsByEp[ep].lieu?' (\u00e0 '+recsByEp[ep].lieu+')':''));}
                pr=ilElle+' est sp\u00e9cialis\u00e9'+eF+' en '+epNames.join(' et ')+', avec des records personnels de '+rd.join(', ')+'.';
            }else{
                var top=epNames.slice(0,4);var rd=top.map(function(ep){return recsByEp[ep].performance_brut+' au '+ep;});
                pr='Polyvalent'+eF+' avec '+nbEp+' disciplines \u00e0 '+sonSa+' actif, '+ilElleMin+' affiche notamment '+rd.join(', ')+'.';
            }
            bio.push(pr);
        }
    }

    // === 5. M\u00c9DAILLES ===
    if(fMedailles.length>0){
        var medOr=0,medArgent=0,medBronze=0,competitions={},epreuvesMed={};
        fMedailles.forEach(function(m){
            if(m.type==='or')medOr++;else if(m.type==='argent')medArgent++;else if(m.type==='bronze')medBronze++;
            if(m.competition)competitions[m.competition]=1;if(m.epreuve)epreuvesMed[m.epreuve]=1;
        });
        var totalMed=medOr+medArgent+medBronze;
        if(totalMed>0){
            var pMed=filterByYear?'Sur cette p\u00e9riode, '+ilElleMin+' a remport\u00e9 '+totalMed+' m\u00e9daille'+(totalMed>1?'s':''):'Son palmar\u00e8s compte '+totalMed+' m\u00e9daille'+(totalMed>1?'s':'');
            var detMed=[];if(medOr>0)detMed.push(medOr+' en or');if(medArgent>0)detMed.push(medArgent+' en argent');if(medBronze>0)detMed.push(medBronze+' en bronze');
            if(detMed.length>1){var last=detMed.pop();pMed+=', dont '+detMed.join(', ')+' et '+last;}
            else if(detMed.length===1)pMed+=', dont '+detMed[0];
            var compNames=Object.keys(competitions);
            if(compNames.length===1)pMed+=', obtenue'+(totalMed>1?'s':'')+' lors des '+compNames[0];
            else if(compNames.length<=3&&compNames.length>1){var lc=compNames.pop();pMed+=', remport\u00e9e'+(totalMed>1?'s':'')+' aux '+compNames.join(', ')+' et '+lc;}
            else if(compNames.length>3)pMed+=', d\u00e9cern\u00e9e'+(totalMed>1?'s':'')+' lors de '+compNames.length+' comp\u00e9titions';
            var epMedNames=Object.keys(epreuvesMed);
            if(epMedNames.length>0&&epMedNames.length<=3)pMed+=' en '+epMedNames.join(', ');
            pMed+='.'; bio.push(pMed);
        }
    }

    // === 6. PODIUMS ===
    if(fPodiums.length>0){
        var nbPod=fPodiums.length,p1=0,p2=0,p3=0,podEp={},podNiv={};
        fPodiums.forEach(function(pod){var rg=pod.rang||0;if(rg===1)p1++;else if(rg===2)p2++;else if(rg===3)p3++;if(pod.epreuve)podEp[pod.epreuve]=1;if(pod.niveau_competition)podNiv[pod.niveau_competition]=1;});
        var pPod=filterByYear?ilElle+' est mont\u00e9'+eF+' sur '+nbPod+' podium'+(nbPod>1?'s':'')+' durant cette p\u00e9riode':ilElle+' est mont\u00e9'+eF+' sur '+nbPod+' podium'+(nbPod>1?'s':'');
        var detPod=[];if(p1>0)detPod.push(p1+' premi\u00e8re'+(p1>1?'s':'')+' place'+(p1>1?'s':''));if(p2>0)detPod.push(p2+' deuxi\u00e8me'+(p2>1?'s':'')+' place'+(p2>1?'s':''));if(p3>0)detPod.push(p3+' troisi\u00e8me'+(p3>1?'s':'')+' place'+(p3>1?'s':''));
        if(detPod.length>0){var ldp=detPod.pop();pPod+=' avec '+(detPod.length>0?detPod.join(', ')+' et '+ldp:ldp);}
        var podEpList=Object.keys(podEp);
        if(podEpList.length>0&&podEpList.length<=4)pPod+=', en '+podEpList.join(', ');
        else if(podEpList.length>4)pPod+=', r\u00e9partis sur '+podEpList.length+' \u00e9preuves';
        pPod+='.'; bio.push(pPod);
    }

    // === 7. S\u00c9LECTIONS ===
    if(fSelections.length>0){
        var nbSel=fSelections.length,selComp={},selEp={};
        fSelections.forEach(function(s){if(s.competition)selComp[s.competition]=1;if(s.epreuve)selEp[s.epreuve]=1;});
        var pSel=ilElle+' a \u00e9t\u00e9 s\u00e9lectionn\u00e9'+eF+' '+nbSel+' fois en \u00e9quipe nationale';
        var scl=Object.keys(selComp);if(scl.length>0&&scl.length<=3)pSel+=' pour '+scl.join(', ');else if(scl.length>3)pSel+=' pour '+scl.length+' comp\u00e9titions';
        var sel=Object.keys(selEp);if(sel.length>0&&sel.length<=3)pSel+=' en '+sel.join(', ');
        pSel+='.'; bio.push(pSel);
    }

    // === 8. ACTIVIT\u00c9 EN COMP\u00c9TITION ===
    if(fResultats.length>0){
        var nbRes=fResultats.length,anneesRes={},villesRes={},epreuvesRes={},bestPlace=999;
        fResultats.forEach(function(r){if(r.annee)anneesRes[r.annee]=1;if(r.lieu)villesRes[r.lieu]=1;if(r.epreuve)epreuvesRes[r.epreuve]=(epreuvesRes[r.epreuve]||0)+1;if(r.place&&r.place>0&&r.place<bestPlace)bestPlace=r.place;});
        var nbVilles=Object.keys(villesRes).length, nbEpRes=Object.keys(epreuvesRes).length;
        var annees=Object.keys(anneesRes).sort();
        var pRes=filterByYear?'Sur cette p\u00e9riode, '+nbRes+' participation'+(nbRes>1?'s':'')+' en comp\u00e9tition '+(nbRes>1?'sont':'est')+' recens\u00e9e'+(nbRes>1?'s':''):'Au total, '+nbRes+' participation'+(nbRes>1?'s':'')+' en comp\u00e9tition '+(nbRes>1?'sont':'est')+' recens\u00e9e'+(nbRes>1?'s':'');
        if(!filterByYear){if(annees.length>=2)pRes+=' sur la p\u00e9riode '+annees[0]+'-'+annees[annees.length-1];else if(annees.length===1)pRes+=' en '+annees[0];}
        if(nbEpRes>1)pRes+=', couvrant '+nbEpRes+' \u00e9preuves diff\u00e9rentes';
        if(nbVilles>1){pRes+=', \u00e0 travers '+nbVilles+' villes';var vl=Object.keys(villesRes);if(vl.length<=5)pRes+=' ('+vl.join(', ')+')';}
        else if(nbVilles===1)pRes+=' \u00e0 '+Object.keys(villesRes)[0];
        pRes+='.'; bio.push(pRes);
        if(bestPlace<999&&bestPlace<=10)bio.push('Sa meilleure place obtenue est la '+bestPlace+(bestPlace===1?'\u00e8re':'\u00e8me')+' position.');
    }

    // === 9. MEILLEURES PERFORMANCES ===
    if(fProgressions.length>0){
        var progByEp={};
        fProgressions.forEach(function(p){if(p.epreuve&&p.performance_brut){if(!progByEp[p.epreuve])progByEp[p.epreuve]=[];progByEp[p.epreuve].push(p);}});
        var progEpNames=Object.keys(progByEp);
        if(progEpNames.length>0){
            var bestPerfs=[];
            progEpNames.forEach(function(ep){var perfs=progByEp[ep];var best=perfs[0];perfs.forEach(function(p){if(p.performance&&p.performance<best.performance)best=p;});bestPerfs.push({epreuve:ep,perf:best.performance_brut,lieu:best.lieu});});
            if(bestPerfs.length<=4){var pp=bestPerfs.map(function(bp){return bp.perf+' au '+bp.epreuve+(bp.lieu?' \u00e0 '+bp.lieu:'');});bio.push('Ses meilleures performances incluent '+pp.join(', ')+'.');}
            else{var pp=bestPerfs.slice(0,4).map(function(bp){return bp.perf+' au '+bp.epreuve;});bio.push('Parmi ses meilleures performances, on note '+pp.join(', ')+', sur un total de '+progEpNames.length+' \u00e9preuves.');}
        }
    }

    // === 10. NIVEAUX DE PERFORMANCE ===
    if(fNiveaux.length>0){
        var meilleurNiv=null,meilleurPts=0;
        fNiveaux.forEach(function(niv){if((niv.points_niveau||0)>meilleurPts){meilleurPts=niv.points_niveau;meilleurNiv=niv;}});
        if(!meilleurNiv)meilleurNiv=fNiveaux[0];
        var nivNom=nivMap[meilleurNiv.code_niveau]||meilleurNiv.code_niveau;
        var pNiv='En termes de classement, '+ilElleMin+' a atteint le '+nivNom;
        if(meilleurNiv.annee)pNiv+=' en '+meilleurNiv.annee;
        if(meilleurPts>0)pNiv+=' avec '+meilleurPts+' points';
        if(meilleurNiv.club)pNiv+=' sous les couleurs du '+meilleurNiv.club;
        pNiv+='.';
        if(fNiveaux.length>1){var allNiv=fNiveaux.map(function(n){return(nivMap[n.code_niveau]||n.code_niveau)+' ('+n.annee+')';});pNiv+=' Les diff\u00e9rents niveaux atteints sont : '+allNiv.join(', ')+'.';}
        if(meilleurNiv.performances&&meilleurNiv.performances.length>0){var np=meilleurNiv.performances.slice(0,3).map(function(p){return(p.performance_brut||p.performance)+' en '+p.epreuve;});pNiv+=' Les performances correspondantes incluent '+np.join(', ')+'.';}
        bio.push(pNiv);
    }

    // === 11. CONCLUSION ===
    if(!filterByYear&&bio.length>2&&carriereTerminee){
        bio.push(i.nom_complet+' laisse derri\u00e8re '+(i.sexe==='M'?'lui':'elle')+' un parcours riche dans l\'athl\u00e9tisme.');
    }

    return bio.join(' ');
}

// === Bio initialization on DOMContentLoaded ===
document.addEventListener('DOMContentLoaded', function(){
    if (typeof ATHLETE_DATA !== 'undefined') {
        _bioAvailableYears = _bioCollectYears(ATHLETE_DATA);
        _bioRenderYearSelector();
        _bioRebuild();
    }
});

// =============================================================================
// Profile Charts — Progression, Medals, Results by year
// =============================================================================
// These require the following globals set inline in index.php via PHP:
//   var profilProgDetail = <?= json_encode($progDetail) ?>;
//   var profilProgByYear = <?= json_encode($progByEpreuve) ?>;
//   var profColors = <?= json_encode($colors) ?>;
//   var profilMedData = { or: N, argent: N, bronze: N };
//   var profilResByYear = { labels: [...], data: [...] };

var profilProgChart = null;

window.buildProfilProgChart = function(discipline) {
    var canvas = document.getElementById('profilProgChart');
    var tableDiv = document.getElementById('profilProgTable');
    if (!canvas) return;
    if (profilProgChart) profilProgChart.destroy();

    var isDistance = /poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon/i.test(discipline || '');

    if (discipline && profilProgDetail[discipline]) {
        // === Vue UNE discipline : chaque perf avec sa date ===
        document.getElementById('profilProgTitle').textContent = 'Progression \u2014 ' + discipline;
        var pts = profilProgDetail[discipline];
        var labels = pts.map(function(p) { return p.date || p.annee; });
        var dataPerf = pts.map(function(p) { return p.perf; });
        var dataBrut = pts.map(function(p) { return p.brut; });
        var dataLieu = pts.map(function(p) { return p.lieu || ''; });

        // Detecter direction pour cette discipline
        var isDist = /poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon/i.test(discipline);

        profilProgChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: discipline,
                    data: dataPerf,
                    borderColor: profColors[0],
                    backgroundColor: profColors[0] + '33',
                    tension: 0.3,
                    pointRadius: 6,
                    pointHoverRadius: 10,
                    borderWidth: 3,
                    fill: true,
                    spanGaps: true
                }]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function(items) { return items[0].label; },
                            label: function(ctx) {
                                var b = dataBrut[ctx.dataIndex] || ctx.parsed.y;
                                var l = dataLieu[ctx.dataIndex];
                                return discipline + ': ' + b + (l ? ' (' + l + ')' : '');
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { color: '#1e2a3a' }, ticks: { maxRotation: 45, font: { size: 11 } } },
                    y: { grid: { color: '#1e2a3a' }, reverse: !isDist, title: { display: true, text: isDist ? 'Performance (plus haut = meilleur)' : 'Performance (plus bas = meilleur)' } }
                }
            }
        });

        // Tableau detaille sous le graphique
        var thRow = '<tr><th>#</th><th>Performance</th><th>Niveaux</th><th>Date</th><th>Lieu</th><th>Ann\u00e9e</th></tr>';
        var html = '<div class="table-wrap">';
        html += '<table class="bk-table">' + thRow + '</table>';
        html += '<table class="bk-table">';
        pts.forEach(function(p, i) {
            html += '<tr><td>' + (i+1) + '</td>';
            html += '<td><span class="perf-val">' + escapeHtml(p.brut || String(p.perf)) + '</span></td>';
            html += '<td>' + _nivBadge(_highestNiveau(p.niveaux || [])) + '</td>';
            html += '<td>' + dateFR(p.date || '-') + '</td>';
            html += '<td>' + (p.lieu ? '<a href="?page=villes&open=' + encodeURIComponent(p.lieu) + '" style="color:#a29bfe;text-decoration:none;">' + escapeHtml(p.lieu) + '</a>' : '-') + '</td>';
            html += '<td>' + escapeHtml(String(p.annee || '-')) + '</td></tr>';
        });
        html += '</table>';
        html += '<table class="bk-table">' + thRow + '</table>';
        html += '</div>';
        tableDiv.innerHTML = html;
    } else {
        // === Vue TOUTES les disciplines : meilleure perf/annee ===
        document.getElementById('profilProgTitle').textContent = 'Progression par discipline';
        var allYears = {};
        var epNames = Object.keys(profilProgByYear).sort();
        epNames.forEach(function(ep) {
            var annees = profilProgByYear[ep];
            for (var y in annees) allYears[y] = true;
        });
        var yearLabels = Object.keys(allYears).sort();

        var datasets = [];
        epNames.forEach(function(ep, idx) {
            var annees = profilProgByYear[ep];
            datasets.push({
                label: ep,
                data: yearLabels.map(function(y) { return annees[y] ? annees[y].perf : null; }),
                _brutMap: annees,
                borderColor: profColors[idx % profColors.length],
                backgroundColor: profColors[idx % profColors.length] + '33',
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 7,
                borderWidth: 2,
                fill: false,
                spanGaps: true
            });
        });

        profilProgChart = new Chart(canvas, {
            type: 'line',
            data: { labels: yearLabels, datasets: datasets },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var bm = ctx.dataset._brutMap;
                                var yr = ctx.label;
                                var brut = bm && bm[yr] ? bm[yr].brut : ctx.parsed.y;
                                return ctx.dataset.label + ': ' + brut;
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { color: '#1e2a3a' } },
                    y: { grid: { color: '#1e2a3a' }, reverse: true, title: { display: true, text: 'Performance (plus bas = meilleur)', font: { size: 11 } } }
                }
            }
        });
        tableDiv.innerHTML = '';
    }
};

// === Profile charts initialization on DOMContentLoaded ===
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.color = '#8892a8';
    Chart.defaults.borderColor = '#1e2a3a';

    // Progression chart (if data exists)
    if (typeof profilProgDetail !== 'undefined' && profilProgDetail) {
        buildProfilProgChart('');
    }

    // Medals doughnut chart
    if (typeof profilMedData !== 'undefined' && profilMedData && (profilMedData.or + profilMedData.argent + profilMedData.bronze) > 0) {
        new Chart(document.getElementById('profilMedChart'), {
            type: 'doughnut',
            data: {
                labels: ['Or', 'Argent', 'Bronze'],
                datasets: [{ data: [profilMedData.or, profilMedData.argent, profilMedData.bronze], backgroundColor: ['#fbbf24','#d1d5db','#d97706'], borderWidth: 0 }]
            },
            options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true } } } }
        });
    }

    // Results by year bar chart
    if (typeof profilResByYear !== 'undefined' && profilResByYear && profilResByYear.labels.length > 0) {
        new Chart(document.getElementById('profilResChart'), {
            type: 'bar',
            data: {
                labels: profilResByYear.labels,
                datasets: [{ label: 'R\u00e9sultats', data: profilResByYear.data,
                    backgroundColor: '#3b82f688', borderColor: '#3b82f6', borderWidth: 1, borderRadius: 4 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { color: '#1e2a3a' } }, y: { grid: { color: '#1e2a3a' }, beginAtZero: true } } }
        });
    }
});
