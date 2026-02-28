<?php
/**
 * profil.php — Profil public d'un athlète (partageable)
 * URL: profil.php?id=123
 */

$BASE_API = "https://bokonzi.com/api";
require_once __DIR__ . '/../core/ip_logger.php';
logIp();

function dateFR($d) {
    if (!$d || $d === '-') return '-';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $d;
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

// Appel API unique pour toutes les données
$data = apiCall("$BASE_API/athlete.php?id_athlete=$idAthlete");

if (!$data || !($data['success'] ?? false)) {
    http_response_code(404);
    echo "Athlète introuvable";
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

// Âge
$age = null;
if ($i['date_naissance']) {
    try { $age = (new DateTime())->diff(new DateTime($i['date_naissance']))->y; } catch (Exception $e) {}
} elseif ($i['annee_naissance']) {
    $age = date('Y') - $i['annee_naissance'];
}

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
if ($i['date_naissance'] || $i['annee_naissance']) {
    $intro .= ', né' . $eF;
    if ($i['date_naissance']) {
        $intro .= ' en ' . substr($i['date_naissance'], 0, 4);
    } else {
        $intro .= ' en ' . $i['annee_naissance'];
    }
    if ($i['lieu_naissance']) $intro .= ' à ' . $i['lieu_naissance'];
    if ($age) $intro .= ' (' . $age . ' ans)';
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
if (!empty($niveaux)) {
    foreach ($niveaux as $niv) {
        if (($niv['points_niveau'] ?? 0) > $meilleurPts) { $meilleurPts = $niv['points_niveau']; $meilleurNiv = $niv; }
    }
    if (!$meilleurNiv) $meilleurNiv = $niveaux[0];
    $nivMap = ['N1'=>'Niveau National 1 (Élite)','N2'=>'Niveau National 2','N3'=>'Niveau National 3','N4'=>'Niveau National 4','R1'=>'Niveau Régional 1','R2'=>'Niveau Régional 2','R3'=>'Niveau Régional 3','R4'=>'Niveau Régional 4','R5'=>'Niveau Régional 5','R6'=>'Niveau Régional 6','D1'=>'Niveau Départemental 1','D2'=>'Niveau Départemental 2','D3'=>'Niveau Départemental 3','D4'=>'Niveau Départemental 4','D5'=>'Niveau Départemental 5','D6'=>'Niveau Départemental 6','D7'=>'Niveau Départemental 7','IR'=>'Interrégional','IE'=>'International Élite'];
    $nivNom = $nivMap[$meilleurNiv['code_niveau']] ?? $meilleurNiv['code_niveau'];
    $pNiv = 'En termes de classement, ' . strtolower(substr($ilElle, 0, 1)) . substr($ilElle, 1) . ' a atteint le ' . $nivNom;
    if ($meilleurNiv['annee']) $pNiv .= ' en ' . $meilleurNiv['annee'];
    if ($meilleurPts > 0) $pNiv .= ' avec ' . $meilleurPts . ' points';
    if ($meilleurNiv['club']) $pNiv .= ' sous les couleurs du ' . $meilleurNiv['club'];
    $pNiv .= '.';
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

    <!-- Records personnels -->
    <div class="section-card">
        <h2>&#9201; Records personnels (<?= count($records) ?>)</h2>
        <?php if (count($records) > 0): ?>
        <div class="table-wrap">
            <table class="bk-table"><thead><tr><th>Épreuve</th><th>Performance</th><th>Date</th><th>Club</th><th>Lieu</th></tr></thead></table>
            <table class="bk-table">
                <tbody>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td><a class="epreuve-link" href="../index.php?page=recherche&epreuve=<?= urlencode($r['epreuve'] ?? '') ?>"><?= htmlspecialchars($r['epreuve'] ?? '-') ?></a></td>
                        <td class="perf-value"><?= htmlspecialchars($r['performance_brut'] ?? '-') ?></td>
                        <td><?= dateFR($r['date'] ?? '-') ?></td>
                        <td><?php if (!empty($r['club'])): ?><a class="epreuve-link" href="global_athlete.php?club=<?= urlencode($r['club']) ?>"><?= htmlspecialchars($r['club']) ?></a><?php else: ?>-<?php endif; ?></td>
                        <td><?php if (!empty($r['lieu'])): ?><a class="epreuve-link" href="global_athlete.php?ville=<?= urlencode($r['lieu']) ?>"><?= htmlspecialchars($r['lieu']) ?></a><?php else: ?>-<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <table class="bk-table"><thead><tr><th>Épreuve</th><th>Performance</th><th>Date</th><th>Club</th><th>Lieu</th></tr></thead></table>
        </div>
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

    <!-- Résultats récents -->
    <?php if (count($resultats) > 0): $resDisplay = array_slice($resultats, 0, 20); ?>
    <div class="section-card">
        <h2>&#128202; Résultats récents (<?= count($resultats) ?>)</h2>
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

    <!-- Dernières performances (progressions) -->
    <?php if (count($progressions) > 0): $progDisplay = array_slice($progressions, 0, 20); ?>
    <div class="section-card">
        <h2>&#128200; Dernières performances (<?= count($progressions) ?>)</h2>
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

<script>
function copyLink() {
    const url = '<?= $ogUrl ?>';
    navigator.clipboard.writeText(url).then(function() {
        const btn = document.querySelector('.btn-share');
        btn.textContent = 'Lien copié !';
        setTimeout(function() { btn.textContent = 'Copier le lien'; }, 2000);
    });
}
</script>
</body>
</html>
