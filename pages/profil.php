<?php
/**
 * profil.php — Profil public d'un athlète (partageable)
 * URL: profil.php?id=123
 */

require_once __DIR__ . '/../core/paths.php';
$BASE_API    = BK_URL('/api');     // PHP : URL absolue pour apiCall HTTP
$BASE_API_JS = BK_BASE . '/api';   // JS : URL relative pour suivre le host courant
require_once __DIR__ . '/../core/ip_logger.php';
logIp();

function dateFR($d) {
    if (!$d || $d === '-') return '-';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $d;
}

// Categorisation epreuves (pour onglets records)
function _profCategEpreuve($nom) {
    $n = strtolower((string)$nom);
    if (preg_match('/(poids|disque|javelot|marteau)/', $n))     return 'lancers';
    if (preg_match('/(hauteur|perche|longueur|triple)/', $n))   return 'sauts';
    if (preg_match('/haies/', $n))                              return 'haies';
    if (preg_match('/steeple/', $n))                            return 'steeple';
    if (preg_match('/(pentathlon|heptathlon|decathlon)/', $n))  return 'combines';
    if (preg_match('/marathon|semi/', $n))                      return 'fond';
    if (preg_match('/(\d+)\s*km/', $n))                         return 'fond';
    if (preg_match('/^\s*(\d+)\s*m\b/', $n, $m)) {
        $d = (int)$m[1];
        if ($d <= 400)  return 'sprint';
        if ($d <= 3000) return 'demi-fond';
        return 'fond';
    }
    return 'autre';
}
function _profNivOrder($code) {
    $o = ['IA'=>1,'IB'=>2,'IE'=>3,'IR'=>4,'IR1'=>4,'IR2'=>5,'IR3'=>6,'IR4'=>7,'N1'=>10,'N2'=>11,'N3'=>12,'N4'=>13,'R1'=>20,'R2'=>21,'R3'=>22,'R4'=>23,'R5'=>24,'R6'=>25,'D1'=>30,'D2'=>31,'D3'=>32,'D4'=>33,'D5'=>34,'D6'=>35,'D7'=>36,'D8'=>37];
    return $o[$code] ?? 99;
}
function _profNivStyle($code) {
    $f = $code[0] ?? '';
    if ($f === 'I') return 'background:#c026d320;border:1px solid #c026d340;color:#e879f9;';
    if ($f === 'N') return 'background:#e11d4820;border:1px solid #e11d4840;color:#fb7185;';
    if ($f === 'R') return 'background:#0891b220;border:1px solid #0891b240;color:#22d3ee;';
    if ($f === 'D') return 'background:#f9731620;border:1px solid #f9731640;color:#fb923c;';
    return 'background:#30363d;border:1px solid #6e7681;color:#c9d1d9;';
}
function _profBestNiv($records) {
    $best = null; $bestO = 99;
    foreach ($records as $r) {
        foreach (($r['niveaux'] ?? []) as $code) {
            $o = _profNivOrder($code);
            if ($o < $bestO) { $bestO = $o; $best = $code; }
        }
    }
    return $best;
}

function apiCall($url) {
    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $json = @file_get_contents($url, false, $ctx);
    if (!$json) return null;
    return json_decode($json, true);
}

$idAthlete = intval($_GET['id'] ?? 0);
if ($idAthlete <= 0) {
    http_response_code(404);
    echo "Athlète introuvable";
    exit;
}

// Verifier visibilite en BDD
require_once __DIR__ . '/../core/db.php';
$_isProfileHidden = false;
$_isAdmin = !empty($_COOKIE['bk_sa_token']);
$_chkVis = $conn->query("SELECT visible, athlete_id_externe FROM athletes WHERE id_athlete = " . (int)$idAthlete);
if ($_chkVis && $_chkRow = $_chkVis->fetch_assoc()) {
    $_isProfileHidden = ((int)$_chkRow['visible'] === 0);
    $_athleteIdExterne = (int)$_chkRow['athlete_id_externe'];
}

// Si masque + admin → appeler l'API avec _all pour voir le profil
if ($_isProfileHidden && $_isAdmin) {
    $data = apiCall("$BASE_API/athlete.php?id_athlete=$idAthlete&_all=1");
} else {
    $data = apiCall("$BASE_API/athlete.php?id_athlete=$idAthlete");
}

if ($_isProfileHidden && !$_isAdmin) {
    http_response_code(404);
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Profil non disponible — Bokonzi</title>
    <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { background:#080c14; color:#c9d1d9; font-family:'Segoe UI',system-ui,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .card { background:#111830; border:1px solid #1a2540; border-radius:16px; padding:40px 36px; max-width:500px; width:90%; text-align:center; }
    </style>
</head>
<body>
<div class="card">
    <div style="font-size:56px;margin-bottom:20px;">&#128683;</div>
    <h2 style="color:#ef4444;font-size:22px;margin-bottom:12px;">Ce profil n'est plus disponible</h2>
    <p style="color:#5a6580;font-size:14px;line-height:1.6;">Ce profil a ete retire a la demande de l'interesse(e) ou suite a un signalement.</p>
    <a href="../index.php" style="display:inline-block;margin-top:24px;padding:10px 24px;background:#1e2a3a;border:1px solid #2a3560;border-radius:8px;color:#a29bfe;text-decoration:none;font-size:14px;font-weight:600;">Retour a l'accueil</a>
</div>
</body>
</html>
    <?php
    exit;
}

// --- Limite offre gratuite : 1 fiche profil par jour + minuteur 2 min ---
require_once __DIR__ . '/../core/profile_gate.php';
$_pGate = bkProfileGateStatus($conn, $_athleteIdExterne ?? 0);
if (!$_pGate['allowed']) {
    header('X-Robots-Tag: noindex');
    ?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Acc&egrave;s limit&eacute; &mdash; Bokonzi</title>
<style>*{margin:0;padding:0;box-sizing:border-box;}body{background:#080c14;min-height:100vh;}</style>
</head><body>
<?php echo bkProfilePaywallHtml($_pGate['reason']); ?>
</body></html><?php
    exit;
}

$i           = $data['identite'];
$clubs       = $data['clubs'] ?? [];
$records     = $data['records'] ?? [];
$medailles   = $data['medailles'] ?? [];
$progressions = $data['progressions'] ?? [];
$podiums     = $data['podiums'] ?? [];
$resultats   = $data['resultats'] ?? [];
$niveaux     = $data['niveaux'] ?? [];
$selections  = $data['selections'] ?? [];

$clubName = !empty($clubs) ? $clubs[0]['nom_club'] : '';

// SEO — priorité fichier src/, fallback données API
require_once dirname(__DIR__) . '/core/seo.php';
$seo = seoFromSrcFile($i['athlete_id'], 'profil') ?: generateAthleteSEO($data, 'profil');
$ogTitle = $seo['title'];
$ogUrl = $seo['canonical'];

$nbOr = count(array_filter($medailles, function($m){ return $m['type'] === 'or'; }));
$nbArgent = count(array_filter($medailles, function($m){ return $m['type'] === 'argent'; }));
$nbBronze = count(array_filter($medailles, function($m){ return $m['type'] === 'bronze'; }));

// ========== RÉSUMÉ BIOGRAPHIQUE AUTOMATIQUE ==========
$bio = [];
$prenom = $i['nom_2'] ?: explode(' ', $i['nom_complet'])[0];
$nomComplet = $i['nom_complet'];
$eF = $i['sexe'] === 'F' ? 'e' : '';
$ilElle = $i['sexe'] === 'M' ? 'Il' : 'Elle';
$sonSa = $i['sexe'] === 'M' ? 'son' : 'sa';

// Dernière activité
$derniereAnneeActivite = 0;
foreach ($resultats as $r) { if (($r['annee'] ?? 0) > $derniereAnneeActivite) $derniereAnneeActivite = $r['annee']; }
foreach ($progressions as $pr) { if (($pr['annee'] ?? 0) > $derniereAnneeActivite) $derniereAnneeActivite = $pr['annee']; }
foreach ($podiums as $pod) { if (($pod['annee'] ?? 0) > $derniereAnneeActivite) $derniereAnneeActivite = $pod['annee']; }
foreach ($medailles as $m) { if (($m['annee'] ?? 0) > $derniereAnneeActivite) $derniereAnneeActivite = $m['annee']; }
$carriereTerminee = ($derniereAnneeActivite > 0 && (date('Y') - $derniereAnneeActivite) > 2);

// Première année d'activité
$premiereAnnee = 0;
foreach ($clubs as $c) { if ($c['annee_debut'] && (!$premiereAnnee || $c['annee_debut'] < $premiereAnnee)) $premiereAnnee = $c['annee_debut']; }
if (!$premiereAnnee) {
    foreach ($resultats as $r) { if (($r['annee'] ?? 0) > 0 && (!$premiereAnnee || $r['annee'] < $premiereAnnee)) $premiereAnnee = $r['annee']; }
}

$age = isset($i['age']) ? $i['age'] : null;

// INTRO
$natMap = ['FRA'=>'français'.$eF,'MAR'=>'marocain'.$eF,'SEN'=>'sénégalais'.$eF,'CMR'=>'camerounais'.$eF,'ALG'=>'algérien'.($i['sexe']==='F'?'ne':''),'TUN'=>'tunisien'.($i['sexe']==='F'?'ne':''),'BEL'=>'belge','SUI'=>'suisse','CIV'=>'ivoirien'.($i['sexe']==='F'?'ne':''),'GBR'=>'britannique','USA'=>'américain'.$eF,'ESP'=>'espagnol'.$eF,'ITA'=>'italien'.($i['sexe']==='F'?'ne':''),'POR'=>'portugais'.$eF,'GER'=>'allemand'.$eF,'BRA'=>'brésilien'.($i['sexe']==='F'?'ne':''),'JAM'=>'jamaïcain'.$eF,'HAI'=>'haïtien'.($i['sexe']==='F'?'ne':''),'COD'=>'congolais'.$eF,'COG'=>'congolais'.$eF,'MLI'=>'malien'.($i['sexe']==='F'?'ne':''),'GIN'=>'guinéen'.($i['sexe']==='F'?'ne':''),'GAB'=>'gabonais'.$eF,'BUR'=>'burkinabè','NIG'=>'nigérien'.($i['sexe']==='F'?'ne':''),'BEN'=>'béninois'.$eF,'TOG'=>'togolais'.$eF,'RWA'=>'rwandais'.$eF,'MAD'=>'malgache','LUX'=>'luxembourgeois'.$eF,'NED'=>'néerlandais'.$eF,'ROU'=>'roumain'.$eF,'POL'=>'polonais'.$eF,'GRE'=>'grec'.($i['sexe']==='F'?'que':''),'TUR'=>'turc'.($i['sexe']==='F'?'que':''),'KEN'=>'kényan'.$eF,'ETH'=>'éthiopien'.($i['sexe']==='F'?'ne':''),'RSA'=>'sud-africain'.$eF,'JPN'=>'japonais'.$eF,'CHN'=>'chinois'.$eF,'AUS'=>'australien'.($i['sexe']==='F'?'ne':''),'CAN'=>'canadien'.($i['sexe']==='F'?'ne':''),'MEX'=>'mexicain'.$eF,'COL'=>'colombien'.($i['sexe']==='F'?'ne':''),'ARG'=>'argentin'.$eF,'CHI'=>'chilien'.($i['sexe']==='F'?'ne':''),'CUB'=>'cubain'.$eF,'DOM'=>'dominicain'.$eF,'TRI'=>'trinidadien'.($i['sexe']==='F'?'ne':''),'BAH'=>'bahaméen'.($i['sexe']==='F'?'ne':'')];
$catMap = ['SE'=>'Senior','ES'=>'Espoir','JU'=>'Junior','CA'=>'Cadet'.($i['sexe']==='F'?'te':''),'MI'=>'Minime','BE'=>'Benjamin'.$eF,'PO'=>'Poussin'.$eF,'EA'=>'Éveil athlétique','V1'=>'Vétéran','V2'=>'Vétéran','V3'=>'Vétéran','V4'=>'Vétéran','V5'=>'Vétéran'];

$intro = $nomComplet;
if ($carriereTerminee) {
    $intro .= ' est un' . $eF . ' ancien' . ($i['sexe'] === 'F' ? 'ne' : '') . ' athlète';
} else {
    $intro .= ' est un' . $eF . ' athlète';
}
if ($i['nationalite'] && isset($natMap[$i['nationalite']])) {
    $intro .= ' ' . $natMap[$i['nationalite']];
} elseif ($i['nationalite']) {
    $intro .= ' de nationalité ' . $i['nationalite'];
}
if ($i['categorie'] && isset($catMap[$i['categorie']])) {
    $intro .= ' évoluant en catégorie ' . $catMap[$i['categorie']];
}
if ($i['lieu_naissance']) {
    $intro .= ', originaire de ' . $i['lieu_naissance'];
}
if ($i['taille_cm'] && $i['poids_kg']) {
    $intro .= ', mesurant ' . number_format($i['taille_cm']/100, 2, ',', '') . ' m pour ' . $i['poids_kg'] . ' kg';
} elseif ($i['taille_cm']) {
    $intro .= ', mesurant ' . number_format($i['taille_cm']/100, 2, ',', '') . ' m';
}
$intro .= '.';
$bio[] = $intro;

// CARRIÈRE
if (!empty($clubs)) {
    $nbClubs = count($clubs);
    $clubRecent = $clubs[0];
    $clubAncien = $clubs[$nbClubs - 1];
    $dureeCarriere = ($premiereAnnee && $derniereAnneeActivite) ? ($derniereAnneeActivite - $premiereAnnee) : 0;
    $uneSeuleAnnee = ($dureeCarriere === 0 && $premiereAnnee > 0);

    if ($uneSeuleAnnee) {
        $pc = $ilElle . ' n\'a effectué qu\'une seule saison en ' . $premiereAnnee;
        if ($nbClubs === 1) $pc .= ' au sein du club ' . $clubRecent['nom_club'];
        $pc .= '.';
    } elseif ($carriereTerminee) {
        $pc = $ilElle . ' a mené ' . $sonSa . ' carrière';
        if ($premiereAnnee) $pc .= ' de ' . $premiereAnnee . ' à ' . $derniereAnneeActivite;
        if ($dureeCarriere > 0) $pc .= ' (' . $dureeCarriere . ' ans d\'activité)';
        if ($nbClubs === 1) $pc .= ' au sein du club ' . $clubRecent['nom_club'];
        else $pc .= ', passant par ' . $nbClubs . ' clubs';
        $pc .= '. ' . $ilElle . ' a mis fin à ' . $sonSa . ' carrière sportive en ' . $derniereAnneeActivite . '.';
    } else {
        if ($nbClubs === 1) {
            $pc = $ilElle . ' évolue au ' . $clubRecent['nom_club'];
            if ($clubRecent['annee_debut']) $pc .= ' depuis ' . $clubRecent['annee_debut'];
            $pc .= '.';
        } else {
            $pc = 'Formé' . $eF . ' au ' . $clubAncien['nom_club'];
            if ($clubAncien['annee_debut']) $pc .= ' dès ' . $clubAncien['annee_debut'];
            $pc .= ', ' . strtolower(substr($ilElle, 0, 1)) . substr($ilElle, 1) . ' évolue désormais au ' . $clubRecent['nom_club'];
            if ($clubRecent['annee_debut']) $pc .= ' depuis ' . $clubRecent['annee_debut'];
            if ($nbClubs > 2) $pc .= ' après être passé' . $eF . ' par ' . ($nbClubs - 2) . ' autre' . ($nbClubs - 2 > 1 ? 's' : '') . ' club' . ($nbClubs - 2 > 1 ? 's' : '');
            $pc .= '.';
        }
        if ($premiereAnnee) {
            $duree = date('Y') - $premiereAnnee;
            if ($duree > 1) $pc .= ' Sa carrière s\'étend sur ' . $duree . ' saisons.';
            elseif ($duree <= 1) $pc .= ' ' . $ilElle . ' en est à ' . $sonSa . ' première saison.';
        }
    }
    $bio[] = $pc;
}

// DISCIPLINES et RECORDS
if (!empty($records)) {
    $recsByEp = [];
    foreach ($records as $r) { if ($r['epreuve'] && $r['performance_brut']) $recsByEp[$r['epreuve']] = $r['performance_brut']; }
    $nbEp = count($recsByEp);
    if ($nbEp > 0) {
        $epNames = array_keys($recsByEp);
        if ($nbEp === 1) {
            $pr = $ilElle . ' est spécialisé' . $eF . ' sur le ' . $epNames[0] . ' où ' . strtolower(substr($ilElle, 0, 1)) . substr($ilElle, 1) . ' détient un record personnel de ' . $recsByEp[$epNames[0]] . '.';
        } elseif ($nbEp <= 3) {
            $recDetail = [];
            foreach ($recsByEp as $ep => $perf) $recDetail[] = $perf . ' au ' . $ep;
            $pr = $ilElle . ' est spécialisé' . $eF . ' en ' . implode(' et ', $epNames) . ', avec des records personnels de ' . implode(', ', $recDetail) . '.';
        } else {
            $top = array_slice($recsByEp, 0, 4, true);
            $recDetail = [];
            foreach ($top as $ep => $perf) $recDetail[] = $perf . ' au ' . $ep;
            $pr = 'Polyvalent' . $eF . ' avec ' . $nbEp . ' disciplines à ' . $sonSa . ' actif, ' . strtolower(substr($ilElle, 0, 1)) . substr($ilElle, 1) . ' affiche notamment ' . implode(', ', $recDetail) . '.';
        }
        $bio[] = $pr;
    }
}

// PALMARÈS
if (!empty($medailles)) {
    $medOr = $medArgent = $medBronze = 0;
    $competitions = [];
    foreach ($medailles as $m) {
        if ($m['type'] === 'or') $medOr++;
        elseif ($m['type'] === 'argent') $medArgent++;
        elseif ($m['type'] === 'bronze') $medBronze++;
        if ($m['competition']) $competitions[$m['competition']] = true;
    }
    $totalMed = $medOr + $medArgent + $medBronze;
    if ($totalMed > 0) {
        $pMed = 'Son palmarès compte ' . $totalMed . ' médaille' . ($totalMed > 1 ? 's' : '');
        $detMed = [];
        if ($medOr > 0) $detMed[] = $medOr . ' en or';
        if ($medArgent > 0) $detMed[] = $medArgent . ' en argent';
        if ($medBronze > 0) $detMed[] = $medBronze . ' en bronze';
        if (count($detMed) > 1) { $last = array_pop($detMed); $pMed .= ', dont ' . implode(', ', $detMed) . ' et ' . $last; }
        else $pMed .= ', dont ' . $detMed[0];
        $pMed .= '.';
        $bio[] = $pMed;
    }
}

// PODIUMS
if (!empty($podiums)) {
    $nbPod = count($podiums);
    $p1 = $p2 = $p3 = 0;
    foreach ($podiums as $pod) {
        $rg = $pod['rang'] ?? 0;
        if ($rg === 1) $p1++;
        elseif ($rg === 2) $p2++;
        elseif ($rg === 3) $p3++;
    }
    $pPod = $ilElle . ' est monté' . $eF . ' sur ' . $nbPod . ' podium' . ($nbPod > 1 ? 's' : '');
    $detPod = [];
    if ($p1 > 0) $detPod[] = $p1 . ' première' . ($p1 > 1 ? 's' : '') . ' place' . ($p1 > 1 ? 's' : '');
    if ($p2 > 0) $detPod[] = $p2 . ' deuxième' . ($p2 > 1 ? 's' : '') . ' place' . ($p2 > 1 ? 's' : '');
    if ($p3 > 0) $detPod[] = $p3 . ' troisième' . ($p3 > 1 ? 's' : '') . ' place' . ($p3 > 1 ? 's' : '');
    if (!empty($detPod)) {
        $lastDp = array_pop($detPod);
        $pPod .= ' avec ' . (!empty($detPod) ? implode(', ', $detPod) . ' et ' . $lastDp : $lastDp);
    }
    $pPod .= '.';
    $bio[] = $pPod;
}

// SÉLECTIONS
if (!empty($selections)) {
    $bio[] = $ilElle . ' a été sélectionné' . $eF . ' ' . count($selections) . ' fois en équipe nationale.';
}

// ACTIVITÉ COMPÉTITION
if (!empty($resultats)) {
    $nbRes = count($resultats);
    $anneesRes = [];
    foreach ($resultats as $r) { if ($r['annee']) $anneesRes[$r['annee']] = true; }
    $annees = array_keys($anneesRes);
    sort($annees);
    $pRes = 'Au total, ' . $nbRes . ' participation' . ($nbRes > 1 ? 's' : '') . ' en compétition ' . ($nbRes > 1 ? 'sont' : 'est') . ' recensée' . ($nbRes > 1 ? 's' : '');
    if (count($annees) >= 2) $pRes .= ' sur la période ' . $annees[0] . '-' . end($annees);
    elseif (count($annees) === 1) $pRes .= ' en ' . $annees[0];
    $pRes .= '.';
    $bio[] = $pRes;
}

// NIVEAU DE PERFORMANCE
$meilleurNiv = null;
$meilleurPts = 0;
$nivMap = ['IA'=>'International A (Élite)','IB'=>'International B','N1'=>'Niveau National 1 (Élite)','N2'=>'Niveau National 2','N3'=>'Niveau National 3','N4'=>'Niveau National 4','IR1'=>'Interrégional 1','IR2'=>'Interrégional 2','IR3'=>'Interrégional 3','IR4'=>'Interrégional 4','R1'=>'Niveau Régional 1','R2'=>'Niveau Régional 2','R3'=>'Niveau Régional 3','R4'=>'Niveau Régional 4','R5'=>'Niveau Régional 5','R6'=>'Niveau Régional 6','D1'=>'Niveau Départemental 1','D2'=>'Niveau Départemental 2','D3'=>'Niveau Départemental 3','D4'=>'Niveau Départemental 4','D5'=>'Niveau Départemental 5','D6'=>'Niveau Départemental 6','D7'=>'Niveau Départemental 7','IR'=>'Interrégional','IE'=>'International Élite'];
if (!empty($niveaux)) {
    foreach ($niveaux as $niv) {
        if (($niv['points_niveau'] ?? 0) > $meilleurPts) { $meilleurPts = $niv['points_niveau']; $meilleurNiv = $niv; }
    }
    if (!$meilleurNiv) $meilleurNiv = $niveaux[0];
    $nivNom = $nivMap[$meilleurNiv['code_niveau']] ?? $meilleurNiv['code_niveau'];
    $pNiv = 'En termes de classement, ' . strtolower(substr($ilElle, 0, 1)) . substr($ilElle, 1) . ' a atteint le ' . $nivNom;
    if ($meilleurNiv['annee']) $pNiv .= ' en ' . $meilleurNiv['annee'];
    if ($meilleurPts > 0) $pNiv .= ' avec ' . $meilleurPts . ' points';
    if ($meilleurNiv['club']) $pNiv .= ' sous les couleurs du ' . $meilleurNiv['club'];
    $pNiv .= '.';
    $bio[] = $pNiv;
}
// Fallback : si pas de niveau BDD, utiliser le meilleur_niveau calcule via bareme FFA
if (!$meilleurNiv && !empty($identite['meilleur_niveau'])) {
    $calcCode = $identite['meilleur_niveau'];
    $meilleurNiv = ['code_niveau' => $calcCode, 'annee' => null, 'club' => '', 'points_niveau' => 0, 'performances' => []];
    $nivNom = $nivMap[$calcCode] ?? $calcCode;
    $pNiv = 'En termes de classement, ' . strtolower(substr($ilElle, 0, 1)) . substr($ilElle, 1) . ' a atteint le ' . $nivNom . ' (estimé).';
    $bio[] = $pNiv;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $ogTitle ?></title>
<?= $seo['meta'] ?>
<?= $seo['jsonld'] ?>
    <link rel="stylesheet" href="../dashboard.css">
    <style>
    .profil-container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
    .profil-hero {
        background: linear-gradient(135deg, #111830 0%, #0d1220 100%);
        border: 1px solid #1a2540;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 24px;
        text-align: center;
    }
    .profil-hero .avatar {
        width: 80px; height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6c5ce7, #fd79a8);
        display: flex; align-items: center; justify-content: center;
        font-size: 32px; font-weight: 800; color: #fff;
        margin: 0 auto 16px;
    }
    .profil-hero h1 { font-size: 26px; color: #fff; margin-bottom: 8px; }
    .profil-hero .meta {
        color: #5a6580; font-size: 14px;
        display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;
    }
    .profil-hero .meta span { display: inline-flex; align-items: center; gap: 4px; }
    .profil-hero .meta .tag {
        background: #6c5ce718; color: #a29bfe;
        padding: 3px 10px; border-radius: 10px; font-size: 12px; font-weight: 600;
    }
    .profil-hero .meta a {
        color: #a29bfe; text-decoration: none; transition: color 0.2s;
    }
    .profil-hero .meta a:hover { color: #d0ccff; text-decoration: underline; }
    .medailles-summary {
        display: flex; gap: 16px; justify-content: center; margin-top: 16px;
    }
    .medaille-count {
        display: flex; align-items: center; gap: 6px; font-size: 16px; font-weight: 700;
    }
    .med-or { color: #ffd700; }
    .med-argent { color: #c0c0c0; }
    .med-bronze { color: #cd7f32; }
    .hero-actions {
        display: flex; justify-content: center; gap: 10px; margin-top: 16px; flex-wrap: wrap;
    }
    .btn-share, .btn-dashboard {
        padding: 8px 20px; border-radius: 8px; border: 1px solid #1a2540;
        background: #080c14; color: #d0d7e0; font-size: 13px; cursor: pointer;
        transition: all 0.2s; text-decoration: none; display: inline-block;
    }
    .btn-share:hover { border-color: #6c5ce7; color: #a29bfe; }
    .btn-dashboard { border-color: #6c5ce740; color: #a29bfe; background: #6c5ce710; }
    .btn-dashboard:hover { border-color: #6c5ce7; background: #6c5ce720; }
    .btn-report-pub { border-color: #48505840; color: #8b949e; background: #30363d; }
    .btn-report-pub:hover { border-color: #da3636; color: #f85149; background: #da363620; }
    .report-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.7); z-index: 100000; align-items: center; justify-content: center;
    }
    .report-overlay.active { display: flex; }
    .report-modal {
        background: #161b22; border: 1px solid #30363d; border-radius: 16px;
        padding: 32px; max-width: 480px; width: 90%; position: relative;
        box-shadow: 0 16px 48px rgba(0,0,0,0.4);
    }
    .report-modal h3 { color: #f0f6fc; margin: 0 0 8px; font-size: 20px; }
    .report-modal p { color: #8b949e; margin: 0 0 20px; font-size: 14px; line-height: 1.5; }
    .report-modal label { display: block; color: #c9d1d9; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
    .report-modal select, .report-modal textarea, .report-modal input[type="email"] {
        width: 100%; padding: 10px 12px; background: #0d1117; border: 1px solid #30363d;
        border-radius: 8px; color: #c9d1d9; font-size: 14px; font-family: inherit;
        margin-bottom: 16px; box-sizing: border-box; transition: border-color 0.2s;
    }
    .report-modal select:focus, .report-modal textarea:focus, .report-modal input:focus {
        outline: none; border-color: #da3636;
    }
    .report-modal textarea { resize: vertical; min-height: 80px; }
    .contact-section {
        background: linear-gradient(135deg, #111830 0%, #0d1220 100%);
        border: 1px solid #1a2540; border-radius: 12px; padding: 24px; margin-top: 24px; text-align: center;
    }
    .section-card {
        background: linear-gradient(135deg, #111830 0%, #0d1220 100%);
        border: 1px solid #1a2540;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 20px;
    }
    .section-card h2 {
        font-size: 18px; color: #d0d7e0; margin-bottom: 16px;
        padding-bottom: 10px; border-bottom: 1px solid #1a2540;
        display: flex; align-items: center; gap: 8px;
    }
    .bio-text { color: #c8cfd8; line-height: 1.9; font-size: 14px; }
    .perf-value { color: #55efc4; font-weight: 600; font-family: 'Courier New', monospace; }
    .empty-msg { color: #5a6580; text-align: center; padding: 30px; font-size: 14px; }
    .rang-badge {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 50%;
        font-weight: 700; font-size: 12px;
    }
    .rang-1 { background: linear-gradient(135deg, #ffd700, #ffaa00); color: #000; }
    .rang-2 { background: linear-gradient(135deg, #c0c0c0, #a0a0a0); color: #000; }
    .rang-3 { background: linear-gradient(135deg, #cd7f32, #a0622e); color: #fff; }
    .rang-other { background: #1a2540; color: #7c85a0; }
    .niveau-badge {
        display: inline-block; padding: 6px 16px; border-radius: 8px;
        font-weight: 700; font-size: 14px; letter-spacing: 0.5px;
    }
    .niveau-elite { background: #ffd70025; color: #ffd700; border: 1px solid #ffd70040; }
    .niveau-national { background: #6c5ce725; color: #a29bfe; border: 1px solid #6c5ce740; }
    .niveau-regional { background: #00cec925; color: #55efc4; border: 1px solid #00cec940; }
    .niveau-departemental { background: #fdcb6e25; color: #fdcb6e; border: 1px solid #fdcb6e40; }
    .epreuve-link { color: #a29bfe; text-decoration: none; }
    .epreuve-link:hover { text-decoration: underline; color: #d0ccff; }
    .table-wrap .bk-table { margin-bottom: 0; }
    .table-wrap .bk-table + .bk-table { border-top: none; }
    .qr-share { text-align:center; padding:20px; margin-top:20px; border-top:1px solid #1a2540; }
    .qr-share img { border-radius:8px; background:#fff; padding:6px; }
    .qr-share .qr-label { color:#5a6580; font-size:12px; margin-top:8px; }
    @media (max-width: 600px) {
        .profil-hero { padding: 20px; }
        .profil-hero h1 { font-size: 20px; }
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/../nav.php'; ?>
<?php if (!$_pGate['exempt']) echo bkProfileTimerBlock($_pGate['remaining']); ?>

<?php if ($_isProfileHidden): ?>
<div style="background:#ef444418;border:2px solid #ef4444;border-radius:12px;padding:16px 24px;margin:0 auto 16px;max-width:900px;display:flex;align-items:center;gap:14px;">
    <span style="font-size:28px;">&#128683;</span>
    <div>
        <strong style="color:#ef4444;font-size:15px;">Profil masque — Inaccessible publiquement</strong>
        <p style="color:#8b949e;font-size:12px;margin:4px 0 0;">Ce profil a ete signale et n'est plus visible par les visiteurs. <a href="../admin/panel.php#signalements" style="color:#a29bfe;">Gerer dans le panel</a></p>
    </div>
</div>
<style>
.profil-hero, .profil-hero h1, .profil-hero .meta, .profil-hero .meta a,
.section h2, .bk-table td, .bk-table td a, .tag { color: #ef4444 !important; -webkit-text-fill-color: #ef4444 !important; }
.profil-hero, .section, .bk-table { border-color: #ef444440 !important; }
</style>
<?php endif; ?>

<div class="profil-container">
    <!-- Hero -->
    <div class="profil-hero">
        <div class="avatar"><?= mb_strtoupper(mb_substr($i['nom_complet'], 0, 2)) ?></div>
        <h1><?= htmlspecialchars($i['nom_complet']) ?></h1>
        <div class="meta">
            <?php if ($clubName): ?><span><a href="global_athlete.php?club=<?= urlencode($clubName) ?>"><?= htmlspecialchars($clubName) ?></a></span><?php endif; ?>
            <?php if ($i['categorie']): ?><span class="tag"><?= htmlspecialchars($i['categorie']) ?></span><?php endif; ?>
            <?php if ($i['sexe']): ?><span><?= $i['sexe'] === 'M' ? 'Homme' : 'Femme' ?></span><?php endif; ?>
            <?php if ($i['nationalite']): ?><span><?= htmlspecialchars($i['nationalite']) ?></span><?php endif; ?>
            <?php if (!empty($i['meilleur_niveau'])):
                $__mn = $i['meilleur_niveau'];
                $__nc = $__mn[0] ?? '';
                if ($__nc === 'N') { $__bg='#e11d4820'; $__bc='#e11d48'; $__tc='#fb7185'; }
                elseif ($__nc === 'I') { $__bg='#c026d320'; $__bc='#c026d3'; $__tc='#e879f9'; }
                elseif ($__nc === 'R') { $__bg='#0891b220'; $__bc='#0891b2'; $__tc='#22d3ee'; }
                else { $__bg='#f9731620'; $__bc='#f97316'; $__tc='#fb923c'; }
            ?><span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600;background:<?= $__bg ?>;border:1px solid <?= $__bc ?>40;color:<?= $__tc ?>;"><?= htmlspecialchars($__mn) ?></span><?php endif; ?>
            <?php if ($age): ?><span><?= $age ?> ans</span><?php endif; ?>
        </div>
        <?php if ($nbOr + $nbArgent + $nbBronze > 0): ?>
        <div class="medailles-summary">
            <?php if ($nbOr > 0): ?><span class="medaille-count med-or">&#127942; <?= $nbOr ?></span><?php endif; ?>
            <?php if ($nbArgent > 0): ?><span class="medaille-count med-argent">&#129352; <?= $nbArgent ?></span><?php endif; ?>
            <?php if ($nbBronze > 0): ?><span class="medaille-count med-bronze">&#129353; <?= $nbBronze ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="hero-actions">
            <button class="btn-share" onclick="copyLink()">Copier le lien</button>
            <a class="btn-dashboard" href="../index.php?page=profil&id=<?= $i['athlete_id'] ?>">Voir sur le Dashboard &#8599;</a>
            <button class="btn-share btn-report-pub" onclick="openReportModal()">&#9888; Signaler</button>
        </div>
    </div>

    <!-- Description -->
    <?php if (!empty($bio)): ?>
    <div class="section-card">
        <h2>&#128221; À propos</h2>
        <p class="bio-text"><?= implode(' ', $bio) ?></p>
    </div>
    <?php endif; ?>

    <!-- Niveau de performance -->
    <?php if ($meilleurNiv): ?>
    <div class="section-card">
        <h2>&#127942; Niveau de performance</h2>
        <?php
        $code = $meilleurNiv['code_niveau'];
        $nivClass = 'niveau-departemental';
        if ($code === 'IE' || $code === 'N1') $nivClass = 'niveau-elite';
        elseif (strpos($code, 'N') === 0) $nivClass = 'niveau-national';
        elseif (strpos($code, 'R') === 0 || $code === 'IR') $nivClass = 'niveau-regional';
        $nivNomDisplay = $nivMap[$code] ?? $code;
        ?>
        <div style="text-align:center;margin-bottom:16px;">
            <span class="niveau-badge <?= $nivClass ?>"><?= htmlspecialchars($nivNomDisplay) ?></span>
            <?php if ($meilleurNiv['annee']): ?><div style="color:#5a6580;font-size:13px;margin-top:8px;">Atteint en <?= $meilleurNiv['annee'] ?><?php if ($meilleurNiv['club']): ?> — <?= htmlspecialchars($meilleurNiv['club']) ?><?php endif; ?><?php if ($meilleurPts > 0): ?> — <?= $meilleurPts ?> points<?php endif; ?></div><?php endif; ?>
        </div>
        <?php if (!empty($meilleurNiv['performances'])): ?>
        <div class="table-wrap">
            <table class="bk-table"><thead><tr><th>Épreuve</th><th>Performance</th><th>Code</th></tr></thead></table>
            <table class="bk-table">
                <tbody>
                <?php foreach ($meilleurNiv['performances'] as $perf): ?>
                    <tr>
                        <td><a class="epreuve-link" href="../index.php?page=recherche&epreuve=<?= urlencode($perf['epreuve'] ?? '') ?>"><?= htmlspecialchars($perf['epreuve'] ?? '-') ?></a></td>
                        <td class="perf-value"><?= htmlspecialchars($perf['performance_brut'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($perf['code_niveau'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <table class="bk-table"><thead><tr><th>Épreuve</th><th>Performance</th><th>Code</th></tr></thead></table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Records personnels avec onglets par categorie -->
    <div class="section-card">
        <h2>&#9201; Records personnels (<?= count($records) ?>)</h2>
        <?php if (count($records) > 0):
            // Grouper par categorie
            $_recByCat = [];
            foreach ($records as $r) {
                $c = _profCategEpreuve($r['epreuve'] ?? '');
                if (!isset($_recByCat[$c])) $_recByCat[$c] = [];
                $_recByCat[$c][] = $r;
            }
            $_catLabels = ['sprint'=>'Sprint','haies'=>'Haies','sauts'=>'Sauts','lancers'=>'Lancers','demi-fond'=>'Demi-fond','fond'=>'Fond','steeple'=>'Steeple','combines'=>'Combines','autre'=>'Autres'];
            $_catOrder = ['sprint','haies','sauts','lancers','demi-fond','fond','steeple','combines','autre'];

            // Synthese par categorie
            $_summaries = ['all' => ['nb'=>count($records), 'best_niv'=>_profBestNiv($records), 'min'=>null, 'max'=>null]];
            foreach ($_catOrder as $c) {
                if (empty($_recByCat[$c])) continue;
                $minY=null; $maxY=null;
                foreach ($_recByCat[$c] as $r) {
                    $y = !empty($r['date']) ? (int)substr($r['date'],0,4) : 0;
                    if ($y > 0) { if ($minY===null||$y<$minY) $minY=$y; if ($maxY===null||$y>$maxY) $maxY=$y; }
                }
                $_summaries[$c] = ['nb'=>count($_recByCat[$c]), 'best_niv'=>_profBestNiv($_recByCat[$c]), 'min'=>$minY, 'max'=>$maxY];
            }
            $minA=null; $maxA=null;
            foreach ($records as $r) {
                $y = !empty($r['date']) ? (int)substr($r['date'],0,4) : 0;
                if ($y > 0) { if ($minA===null||$y<$minA) $minA=$y; if ($maxA===null||$y>$maxA) $maxA=$y; }
            }
            $_summaries['all']['min'] = $minA; $_summaries['all']['max'] = $maxA;
        ?>

        <!-- Onglets categorie -->
        <div class="rec-cat-tabs" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #21262d;">
            <button type="button" class="rec-cat-tab is-active" data-cat="all" onclick="_profRecFilter('all',this)" style="background:linear-gradient(135deg,rgba(162,155,254,0.15),rgba(108,92,231,0.08));border:1px solid rgba(162,155,254,0.55);color:#f0f6fc;font-size:12px;font-weight:600;padding:7px 14px;border-radius:100px;cursor:pointer;display:inline-flex;align-items:center;gap:7px;">
                Tous <span style="background:rgba(162,155,254,0.25);color:#fff;font-weight:700;font-size:11px;padding:1px 7px;border-radius:100px;"><?= count($records) ?></span>
            </button>
            <?php foreach ($_catOrder as $cat):
                if (empty($_recByCat[$cat])) continue;
            ?>
            <button type="button" class="rec-cat-tab" data-cat="<?= $cat ?>" onclick="_profRecFilter('<?= $cat ?>',this)" style="background:transparent;border:1px solid rgba(162,155,254,0.18);color:#8b949e;font-size:12px;font-weight:500;padding:7px 14px;border-radius:100px;cursor:pointer;display:inline-flex;align-items:center;gap:7px;">
                <?= $_catLabels[$cat] ?> <span style="background:rgba(162,155,254,0.12);color:#a29bfe;font-weight:700;font-size:11px;padding:1px 7px;border-radius:100px;"><?= count($_recByCat[$cat]) ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Synthese par categorie (un par cat, on affiche celle active) -->
        <?php foreach ($_summaries as $sk => $su):
            $lbl = $sk === 'all' ? 'Tous niveaux confondus' : ($_catLabels[$sk] ?? $sk);
            $bn = $su['best_niv'];
            $periode = ($su['min'] && $su['max']) ? ($su['min']===$su['max'] ? $su['min'] : ($su['min'].' &mdash; '.$su['max'])) : '';
        ?>
        <div class="rec-cat-summary" data-cat="<?= $sk ?>" style="<?= $sk === 'all' ? '' : 'display:none;' ?>background:linear-gradient(135deg,rgba(162,155,254,0.06),rgba(108,92,231,0.02));border:1px solid rgba(162,155,254,0.18);border-left:3px solid #a29bfe;border-radius:10px;padding:12px 16px;margin-bottom:14px;">
            <div style="font-size:11px;color:#a29bfe;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:8px;"><?= htmlspecialchars($lbl) ?></div>
            <div style="display:flex;flex-wrap:wrap;gap:24px;">
                <div><div style="font-size:24px;font-weight:800;color:#f0f6fc;line-height:1;"><?= (int)$su['nb'] ?></div><div style="font-size:10px;color:#6e7681;text-transform:uppercase;letter-spacing:1.5px;margin-top:3px;">Records</div></div>
                <?php if ($bn): ?>
                <div><div style="line-height:1;"><span style="display:inline-block;padding:5px 12px;border-radius:6px;font-weight:700;font-size:14px;<?= _profNivStyle($bn) ?>"><?= htmlspecialchars($bn) ?></span></div><div style="font-size:10px;color:#6e7681;text-transform:uppercase;letter-spacing:1.5px;margin-top:6px;">Niveau max</div></div>
                <?php endif; ?>
                <?php if ($periode): ?>
                <div><div style="font-size:18px;font-weight:600;color:#c9d1d9;line-height:1;"><?= $periode ?></div><div style="font-size:10px;color:#6e7681;text-transform:uppercase;letter-spacing:1.5px;margin-top:3px;">Periode</div></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="table-wrap">
            <table class="bk-table"><thead><tr><th>Épreuve</th><th>Performance</th><th>Niveau</th><th>Date</th><th>Club</th><th>Lieu</th></tr></thead></table>
            <table class="bk-table">
                <tbody id="profRecTbody">
                <?php foreach ($records as $r):
                    $rcat = _profCategEpreuve($r['epreuve'] ?? '');
                    // Meilleur niveau du record
                    $rBestNiv = null; $rBestO = 99;
                    foreach (($r['niveaux'] ?? []) as $nc) {
                        $o = _profNivOrder($nc);
                        if ($o < $rBestO) { $rBestO = $o; $rBestNiv = $nc; }
                    }
                ?>
                    <tr data-cat="<?= $rcat ?>">
                        <td><a class="epreuve-link" href="../index.php?page=recherche&epreuve=<?= urlencode($r['epreuve'] ?? '') ?>"><?= htmlspecialchars($r['epreuve'] ?? '-') ?></a></td>
                        <td class="perf-value"><?= htmlspecialchars($r['performance_brut'] ?? '-') ?></td>
                        <td><?php if ($rBestNiv): ?><span style="display:inline-block;padding:2px 8px;border-radius:5px;font-weight:700;font-size:11px;<?= _profNivStyle($rBestNiv) ?>"><?= htmlspecialchars($rBestNiv) ?></span><?php else: ?>—<?php endif; ?></td>
                        <td><?= dateFR($r['date'] ?? '-') ?></td>
                        <td><?php if (!empty($r['club'])): ?><a class="epreuve-link" href="global_athlete.php?club=<?= urlencode($r['club']) ?>"><?= htmlspecialchars($r['club']) ?></a><?php else: ?>-<?php endif; ?></td>
                        <td><?php if (!empty($r['lieu'])): ?><a class="epreuve-link" href="global_athlete.php?ville=<?= urlencode($r['lieu']) ?>"><?= htmlspecialchars($r['lieu']) ?></a><?php else: ?>-<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <table class="bk-table"><thead><tr><th>Épreuve</th><th>Performance</th><th>Niveau</th><th>Date</th><th>Club</th><th>Lieu</th></tr></thead></table>
        </div>

        <script>
        function _profRecFilter(cat, btn) {
            document.querySelectorAll('.rec-cat-tab').forEach(function(t){
                t.classList.remove('is-active');
                t.style.cssText = 'background:transparent;border:1px solid rgba(162,155,254,0.18);color:#8b949e;font-size:12px;font-weight:500;padding:7px 14px;border-radius:100px;cursor:pointer;display:inline-flex;align-items:center;gap:7px;';
                var c = t.querySelector('span'); if (c) c.style.cssText = 'background:rgba(162,155,254,0.12);color:#a29bfe;font-weight:700;font-size:11px;padding:1px 7px;border-radius:100px;';
            });
            btn.classList.add('is-active');
            btn.style.cssText = 'background:linear-gradient(135deg,rgba(162,155,254,0.15),rgba(108,92,231,0.08));border:1px solid rgba(162,155,254,0.55);color:#f0f6fc;font-size:12px;font-weight:600;padding:7px 14px;border-radius:100px;cursor:pointer;display:inline-flex;align-items:center;gap:7px;';
            var c2 = btn.querySelector('span'); if (c2) c2.style.cssText = 'background:rgba(162,155,254,0.25);color:#fff;font-weight:700;font-size:11px;padding:1px 7px;border-radius:100px;';
            document.querySelectorAll('#profRecTbody tr').forEach(function(tr){
                tr.style.display = (cat === 'all' || tr.dataset.cat === cat) ? '' : 'none';
            });
            document.querySelectorAll('.rec-cat-summary').forEach(function(s){
                s.style.display = (s.dataset.cat === cat) ? '' : 'none';
            });
        }
        </script>
        <?php else: ?>
            <div class="empty-msg">Aucun record enregistré</div>
        <?php endif; ?>
    </div>

    <!-- Podiums -->
    <?php if (count($podiums) > 0): ?>
    <div class="section-card">
        <h2>&#127941; Podiums (<?= count($podiums) ?>)</h2>
        <div class="table-wrap">
            <table class="bk-table"><thead><tr><th>Place</th><th>Épreuve</th><th>Performance</th><th>Niveau</th><th>Année</th><th>Lieu</th></tr></thead></table>
            <table class="bk-table">
                <tbody>
                <?php foreach ($podiums as $pod): ?>
                    <tr>
                        <td>
                            <?php
                            $rg = $pod['rang'] ?? 0;
                            $rgClass = $rg <= 3 ? 'rang-' . $rg : 'rang-other';
                            $rgLabel = $rg === 1 ? '1er' : ($rg . 'ème');
                            ?>
                            <span class="rang-badge <?= $rgClass ?>"><?= $rg ?></span> <?= $rgLabel ?>
                        </td>
                        <td><a class="epreuve-link" href="../index.php?page=recherche&epreuve=<?= urlencode($pod['epreuve'] ?? '') ?>"><?= htmlspecialchars($pod['epreuve'] ?? '-') ?></a></td>
                        <td class="perf-value"><?= htmlspecialchars($pod['performance_brut'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($pod['niveau_competition'] ?? '-') ?></td>
                        <td><?= $pod['annee'] ?></td>
                        <td><?php if (!empty($pod['lieu'])): ?><a class="epreuve-link" href="global_athlete.php?ville=<?= urlencode($pod['lieu']) ?>"><?= htmlspecialchars($pod['lieu']) ?></a><?php else: ?>-<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <table class="bk-table"><thead><tr><th>Place</th><th>Épreuve</th><th>Performance</th><th>Niveau</th><th>Année</th><th>Lieu</th></tr></thead></table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Médailles -->
    <?php if (count($medailles) > 0): ?>
    <div class="section-card">
        <h2>&#127942; Médailles (<?= count($medailles) ?>)</h2>
        <div class="table-wrap">
            <table class="bk-table"><thead><tr><th>Type</th><th>Épreuve</th><th>Compétition</th><th>Année</th><th>Lieu</th></tr></thead></table>
            <table class="bk-table">
                <tbody>
                <?php foreach ($medailles as $m): ?>
                    <tr>
                        <td>
                            <?php
                            $medIcons = ['or' => '&#127942;', 'argent' => '&#129352;', 'bronze' => '&#129353;'];
                            echo ($medIcons[$m['type']] ?? '') . ' ' . ucfirst($m['type']);
                            ?>
                        </td>
                        <td><a class="epreuve-link" href="../index.php?page=recherche&epreuve=<?= urlencode($m['epreuve'] ?? '') ?>"><?= htmlspecialchars($m['epreuve'] ?? '-') ?></a></td>
                        <td><?= htmlspecialchars($m['competition'] ?? '-') ?></td>
                        <td><?= $m['annee'] ?></td>
                        <td><?php if (!empty($m['lieu'])): ?><a class="epreuve-link" href="global_athlete.php?ville=<?= urlencode($m['lieu']) ?>"><?= htmlspecialchars($m['lieu']) ?></a><?php else: ?>-<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <table class="bk-table"><thead><tr><th>Type</th><th>Épreuve</th><th>Compétition</th><th>Année</th><th>Lieu</th></tr></thead></table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Résultats -->
    <?php if (count($resultats) > 0): $resDisplay = $resultats; ?>
    <div class="section-card">
        <h2>&#128202; Résultats (<?= count($resultats) ?>)</h2>
        <div class="table-wrap">
            <table class="bk-table"><thead><tr><th>Place</th><th>Épreuve</th><th>Performance</th><th>Date</th><th>Niveau</th><th>Lieu</th></tr></thead></table>
            <table class="bk-table">
                <tbody>
                <?php foreach ($resDisplay as $r): ?>
                    <tr>
                        <td>
                            <?php
                            $pl = $r['place'] ?? null;
                            if ($pl && $pl <= 3) {
                                $plClass = 'rang-' . $pl;
                                echo '<span class="rang-badge ' . $plClass . '">' . $pl . '</span>';
                            } elseif ($pl) {
                                echo '<span class="rang-badge rang-other">' . $pl . '</span>';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><a class="epreuve-link" href="../index.php?page=recherche&epreuve=<?= urlencode($r['epreuve'] ?? '') ?>"><?= htmlspecialchars($r['epreuve'] ?? '-') ?></a></td>
                        <td class="perf-value"><?= htmlspecialchars($r['performance_brut'] ?? '-') ?></td>
                        <td><?= dateFR($r['date'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['niveau'] ?? '-') ?></td>
                        <td><?php if (!empty($r['lieu'])): ?><a class="epreuve-link" href="global_athlete.php?ville=<?= urlencode($r['lieu']) ?>"><?= htmlspecialchars($r['lieu']) ?></a><?php else: ?>-<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <table class="bk-table"><thead><tr><th>Place</th><th>Épreuve</th><th>Performance</th><th>Date</th><th>Niveau</th><th>Lieu</th></tr></thead></table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Performances (progressions) -->
    <?php if (count($progressions) > 0): $progDisplay = $progressions; ?>
    <div class="section-card">
        <h2>&#128200; Performances (<?= count($progressions) ?>)</h2>
        <div class="table-wrap">
            <table class="bk-table"><thead><tr><th>Épreuve</th><th>Performance</th><th>Date</th><th>Année</th><th>Club</th><th>Lieu</th></tr></thead></table>
            <table class="bk-table">
                <tbody>
                <?php foreach ($progDisplay as $p): ?>
                    <tr>
                        <td><a class="epreuve-link" href="../index.php?page=recherche&epreuve=<?= urlencode($p['epreuve'] ?? '') ?>"><?= htmlspecialchars($p['epreuve'] ?? '-') ?></a></td>
                        <td class="perf-value"><?= htmlspecialchars($p['performance_brut'] ?? '-') ?></td>
                        <td><?= dateFR($p['date'] ?? '-') ?></td>
                        <td><?= $p['annee'] ?></td>
                        <td><?php if (!empty($p['club'])): ?><a class="epreuve-link" href="global_athlete.php?club=<?= urlencode($p['club']) ?>"><?= htmlspecialchars($p['club']) ?></a><?php else: ?>-<?php endif; ?></td>
                        <td><?php if (!empty($p['lieu'])): ?><a class="epreuve-link" href="global_athlete.php?ville=<?= urlencode($p['lieu']) ?>"><?= htmlspecialchars($p['lieu']) ?></a><?php else: ?>-<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <table class="bk-table"><thead><tr><th>Épreuve</th><th>Performance</th><th>Date</th><th>Année</th><th>Club</th><th>Lieu</th></tr></thead></table>
        </div>
    </div>
    <?php endif; ?>

    <!-- QR Code de partage -->
    <div class="qr-share">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?= urlencode($ogUrl) ?>" alt="QR Code" width="120" height="120">
        <div class="qr-label">Scannez pour partager ce profil</div>
    </div>
</div>

<!-- Section contact + signalement -->
<div class="profil-container">
    <div class="contact-section">
        <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">
            <button onclick="openReportModal()" style="background:#da363620;border:1px solid #da363660;color:#f85149;font-size:13px;font-weight:600;padding:10px 20px;border-radius:8px;cursor:pointer;">&#9888; Signaler ce profil</button>
            <button onclick="document.getElementById('pubContactForm').style.display=document.getElementById('pubContactForm').style.display==='none'?'block':'none';" style="background:#1e2a3a;border:1px solid #2d3a4a;color:#c9d1d9;font-size:13px;padding:10px 20px;border-radius:8px;cursor:pointer;">&#9993; Nous contacter</button>
        </div>
        <p style="color:#5a6580;font-size:11px;line-height:1.5;margin:12px 0 0;max-width:400px;display:inline-block;">Pour demander le retrait de ce profil ou signaler des informations incorrectes, cliquez sur <span style="color:#f85149;">Signaler</span>. Pour toute autre question, utilisez le formulaire de contact.</p>
        <div id="pubContactForm" style="display:none;max-width:480px;margin:16px auto 0;text-align:left;">
            <div style="background:#dc262618;border:2px solid #dc2626;border-radius:10px;padding:16px;margin:0 0 12px;">
                <p style="color:#fca5a5;font-size:16px;font-weight:800;margin:0 0 10px;text-transform:uppercase;letter-spacing:0.5px;line-height:1.3;">&#9888; Retirer son profil soi-meme</p>
                <ol style="color:#fca5a5;font-size:13px;line-height:1.7;margin:0;padding-left:20px;font-weight:600;">
                    <li>Cliquez sur le bouton <b style="color:#f85149;">&#9888; Signaler ce profil</b> ci-dessus</li>
                    <li>Choisissez le motif <b>&laquo; Je souhaite retirer mon profil &raquo;</b></li>
                    <li>Indiquez votre adresse email</li>
                    <li style="color:#86efac;">&#10003; Votre profil est masque <b>immediatement</b></li>
                </ol>
            </div>
            <p style="color:#ef4444;font-size:12px;line-height:1.5;margin:0 0 10px;">&#9993; Un email de confirmation vous sera envoye. Votre message ne nous parviendra qu'apres validation du lien.</p>
            <input type="text" id="pubNom" maxlength="100" placeholder="Votre nom (facultatif)" style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid #30363d;background:#0d1117;color:#c9d1d9;font-size:13px;margin-bottom:8px;box-sizing:border-box;">
            <input type="email" id="pubEmail" maxlength="200" placeholder="Email *" style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid #30363d;background:#0d1117;color:#c9d1d9;font-size:13px;margin-bottom:8px;box-sizing:border-box;" required>
            <textarea id="pubMsg" maxlength="2000" placeholder="Votre message..." style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid #30363d;background:#0d1117;color:#c9d1d9;font-size:13px;font-family:inherit;resize:vertical;min-height:80px;margin-bottom:10px;box-sizing:border-box;"></textarea>
            <button onclick="_pubContact()" id="pubBtn" style="width:100%;padding:10px;background:#6c5ce7;border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;">Envoyer</button>
            <div id="pubStatus" style="font-size:13px;margin-top:8px;text-align:center;"></div>
        </div>
    </div>
</div>

<!-- Modal Signaler profil -->
<div class="report-overlay" id="reportOverlay">
    <div class="report-modal">
        <button onclick="closeReportModal()" style="position:absolute;top:12px;right:12px;background:none;border:none;color:#8b949e;font-size:24px;cursor:pointer;">&times;</button>
        <h3>&#9888; Signaler ce profil</h3>
        <p style="color:#c9d1d9;font-weight:600;margin-bottom:4px;"><?= htmlspecialchars($i['nom_complet']) ?></p>
        <p style="color:#ef4444;font-size:13px;font-weight:600;margin:0 0 16px;line-height:1.5;">&#9993; Votre adresse email est <u>obligatoire</u> pour valider votre signalement. Pour un retrait, nous vous enverrons un lien de confirmation pour retirer votre profil instantanement. Sans email valide, votre demande ne sera pas enregistree.</p>
        <label>Motif du signalement</label>
        <select id="reportReason" onchange="(function(v){var h=document.getElementById('reportRetraitHint');if(v==='retrait'){h.style.display='block';}else{h.style.display='none';}})(this.value)">
            <option value="">-- Choisir un motif --</option>
            <option value="retrait">Je souhaite retirer mon profil</option>
            <option value="donnees_incorrectes">Donnees incorrectes</option>
            <option value="usurpation">Usurpation d'identite</option>
            <option value="vie_privee">Atteinte a la vie privee</option>
            <option value="autre">Autre</option>
        </select>
        <div id="reportRetraitHint" style="display:none;background:#ef444415;border:1px solid #ef444440;border-radius:8px;padding:10px 14px;margin:8px 0 12px;">
            <p style="color:#ef4444;font-size:13px;font-weight:600;margin:0;line-height:1.5;">&#9888; L'email est obligatoire pour ce motif. Vous recevrez un lien de confirmation : un seul clic et votre profil sera masque immediatement.</p>
        </div>
        <label>Details (facultatif)</label>
        <textarea id="reportMessage" placeholder="Precisez votre demande..." maxlength="2000"></textarea>
        <label id="reportEmailLabel">Email de contact <span style="color:#ef4444">*</span></label>
        <input type="email" id="reportEmail" placeholder="votre@email.com" required>
        <button onclick="submitReport()" id="btnSubmitReport" style="width:100%;padding:12px;background:#da3636;border:none;border-radius:8px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;">Envoyer le signalement</button>
        <div id="reportFeedback" style="margin-top:12px;font-size:14px;text-align:center;"></div>
    </div>
</div>

<script>
function copyLink() {
    var url = '<?= $ogUrl ?>';
    navigator.clipboard.writeText(url).then(function() {
        var btn = document.querySelector('.btn-share');
        btn.textContent = 'Lien copie !';
        setTimeout(function() { btn.textContent = 'Copier le lien'; }, 2000);
    });
}

var _athleteId = <?= (int)$i['athlete_id'] ?>;
var _athleteName = <?= json_encode($i['nom_complet']) ?>;
var _apiBase = <?= json_encode($BASE_API_JS) ?>;

function openReportModal() {
    document.getElementById('reportReason').value = '';
    document.getElementById('reportMessage').value = '';
    document.getElementById('reportEmail').value = '';
    document.getElementById('reportFeedback').innerHTML = '';
    document.getElementById('btnSubmitReport').disabled = false;
    document.getElementById('btnSubmitReport').textContent = 'Envoyer le signalement';
    document.getElementById('reportOverlay').classList.add('active');
}
function closeReportModal() {
    document.getElementById('reportOverlay').classList.remove('active');
}
document.getElementById('reportOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeReportModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('reportOverlay').classList.contains('active')) closeReportModal();
});

function submitReport() {
    var reason = document.getElementById('reportReason').value;
    var message = document.getElementById('reportMessage').value.trim();
    var email = document.getElementById('reportEmail').value.trim();
    var fb = document.getElementById('reportFeedback');
    var btn = document.getElementById('btnSubmitReport');
    if (!reason) { fb.innerHTML = '<span style="color:#f85149">Veuillez choisir un motif.</span>'; return; }
    if (!email) { fb.innerHTML = '<span style="color:#f85149">&#9993; Adresse email obligatoire pour valider votre signalement.</span>'; return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { fb.innerHTML = '<span style="color:#f85149">&#9888; Adresse email invalide. Veuillez verifier votre saisie.</span>'; return; }
    btn.disabled = true; btn.textContent = 'Envoi...';
    fetch(_apiBase + '/report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ athlete_id: _athleteId, athlete_name: _athleteName, reason: reason, message: message, email: email })
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            if (d.confirm_sent) {
                fb.innerHTML = '<div style="background:#a29bfe15;border:2px solid #a29bfe;border-radius:12px;padding:18px 20px;margin-top:14px;text-align:center;line-height:1.7;">'
                    + '<div style="font-size:36px;margin-bottom:8px;">&#9993;</div>'
                    + '<strong style="color:#a29bfe;font-size:16px;">Verifiez votre boite mail !</strong><br>'
                    + '<span style="color:#c9d1d9;font-size:14px;">Un email a ete envoye a <strong>' + email + '</strong></span><br><br>'
                    + '<span style="color:#f0f6fc;font-size:15px;font-weight:700;">&#x1F449; Ouvrez l\'email et cliquez sur le bouton de confirmation pour masquer votre profil.</span><br><br>'
                    + '<span style="color:#8b949e;font-size:12px;">Sans confirmation, votre profil restera visible.<br>Pensez a verifier vos spams. Le lien expire dans 48h.</span>'
                    + '</div>';
            } else {
                fb.innerHTML = '<span style="color:#3fb950">&#10003; ' + (d.message || 'Signalement envoye.') + '</span>';
            }
            btn.textContent = 'Envoye';
        } else {
            fb.innerHTML = '<span style="color:#f85149">' + (d.error || 'Erreur') + '</span>';
            btn.disabled = false; btn.textContent = 'Envoyer le signalement';
        }
    }).catch(function() {
        fb.innerHTML = '<span style="color:#f85149">Erreur de connexion.</span>';
        btn.disabled = false; btn.textContent = 'Envoyer le signalement';
    });
}

function _pubContact() {
    var email = document.getElementById('pubEmail').value.trim();
    var msg = document.getElementById('pubMsg').value.trim();
    var fb = document.getElementById('pubStatus');
    if (!email) { fb.innerHTML = '<span style="color:#f85149">Veuillez indiquer votre email.</span>'; return; }
    if (!msg) { fb.innerHTML = '<span style="color:#f85149">Ecrivez un message.</span>'; return; }
    var btn = document.getElementById('pubBtn'); btn.disabled = true; btn.textContent = 'Envoi...';
    fetch(_apiBase + '/contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nom: document.getElementById('pubNom').value.trim() || 'Visiteur (profil public #' + _athleteId + ')', email: email, message: '[Profil public #' + _athleteId + ' - ' + _athleteName + '] ' + msg })
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { document.getElementById('pubContactForm').innerHTML = '<div style="margin-top:12px;line-height:1.6;"><p style="color:#3fb950;font-size:14px;font-weight:600;">&#9993; Verifiez votre boite mail !</p><p style="color:#8b949e;font-size:12px;">Un email de confirmation a ete envoye a <strong style="color:#c9d1d9;">' + email + '</strong>. Cliquez sur le lien pour que votre message nous parvienne.</p></div>'; }
        else { fb.innerHTML = '<span style="color:#f85149">' + (d.error || 'Erreur') + '</span>'; btn.disabled = false; btn.textContent = 'Envoyer'; }
    }).catch(function() { fb.innerHTML = '<span style="color:#f85149">Erreur de connexion.</span>'; btn.disabled = false; btn.textContent = 'Envoyer'; });
}
</script>
</body>
</html>
