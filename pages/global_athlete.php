<?php
/**
 * global_athlete.php — Recherche + affichage complet depuis la BDD
 *
 * Usage :
 *   global_athlete.php                   → page de recherche
 *   global_athlete.php?id=26134          → fiche athlete
 *   global_athlete.php?nom1=ABDOU        → resultats de recherche
 */
require_once __DIR__ . '/../core/ip_logger.php';
logIp();

$BASE_API = "https://bokonzi.com/api";

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

$id = $_GET['id'] ?? null;

// SEO — priorité fichier src/ (rapide), fallback API
$seoData = null;
$seoTags = null;
if ($id && is_numeric($id)) {
    require_once dirname(__DIR__) . '/core/seo.php';
    // 1) Essayer lecture directe fichier src/ (pas d'appel réseau)
    $seoTags = seoFromSrcFile($id, 'global');
    // 2) Fallback : appel API
    if (!$seoTags) {
        $seoData = apiCall("$BASE_API/athlete.php?id=$id");
        if ($seoData && ($seoData['success'] ?? false)) {
            $seoTags = generateAthleteSEO($seoData, 'global');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $seoTags ? $seoTags['title'] : ($id ? "Athlète #$id — Bokonzi" : "Recherche Athlètes — Bokonzi") ?></title>
<?php if ($seoTags): ?>
<?= $seoTags['meta'] ?>
<?= $seoTags['jsonld'] ?>
<?php else: ?>
    <meta name="description" content="Recherche d'athlètes français : filtres par nom, club, épreuve, ville, médaille. Base de données complète d'athlétisme sur Bokonzi.">
    <meta property="og:title" content="Recherche Athlètes — Bokonzi">
    <meta property="og:description" content="Recherche d'athlètes français : filtres par nom, club, épreuve, ville, médaille.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Bokonzi">
<?php endif; ?>
    <link rel="stylesheet" href="../dashboard.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #080c14; color: #d0d7e0; padding: 20px; }
        h1 { color: #a29bfe; margin-bottom: 5px; }
        h2 { color: #a29bfe; margin: 25px 0 10px; border-bottom: 1px solid #1a2540; padding-bottom: 5px; }
        .info { color: #7c85a0; margin-bottom: 20px; }
        .card { background: #111830; border-radius: 8px; padding: 15px; margin-bottom: 10px; }
        .card span { color: #7c85a0; }
        /* tableaux gérés par .bk-table dans dashboard.css */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-right: 5px; }
        .or { background: #ffd700; color: #000; }
        .argent { background: #c0c0c0; color: #000; }
        .bronze { background: #cd7f32; color: #000; }
        .section-count { color: #5a6580; font-size: 14px; }
        a { color: #a29bfe; text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Wrapper scrollable pour les tableaux */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 10px 0; }
        .table-wrap table { margin: 0; }

        /* Recherche */
        .search-bar { background: #111830; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .search-bar input, .search-bar select {
            background: #080c14; border: 1px solid #1a2540; color: #d0d7e0;
            padding: 8px 12px; border-radius: 6px; font-size: 14px; margin: 3px;
        }
        .search-bar input:focus, .search-bar select:focus { border-color: #6c5ce7; outline: none; }
        .search-bar button {
            background: #6c5ce7; color: #fff; border: none; padding: 8px 18px;
            border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; margin: 3px;
        }
        .search-bar button:hover { background: #7c6cf7; }
        .total { color: #a29bfe; font-size: 16px; margin: 10px 0; }
        .pager { margin: 15px 0; }
        .pager a, .pager span { padding: 5px 12px; border-radius: 4px; margin: 0 2px; font-size: 13px; }
        .pager a { background: #111830; color: #d0d7e0; }
        .pager a:hover { background: #1a2540; text-decoration: none; }
        .pager .current { background: #6c5ce7; color: #fff; font-weight: bold; }
        .back-link { display: inline-block; margin-bottom: 15px; padding: 6px 14px; background: #111830; border-radius: 6px; }
        .error { color: #ff7675; background: #ff767522; padding: 15px; border-radius: 8px; }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            body { padding: 10px; }
            .search-bar form { display: flex; flex-wrap: wrap; gap: 6px; }
            .search-bar input, .search-bar select { width: 100% !important; margin: 0; }
            .search-bar button { width: 100%; margin: 4px 0 0; }
            h1 { font-size: 20px; }
            /* responsive tableaux géré par dashboard.css */
            .pager { flex-wrap: wrap; }
        }
        @media (max-width: 600px) {
            body { padding: 8px; }
            .search-bar { padding: 10px; }
            h1 { font-size: 18px; }
            h2 { font-size: 16px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../nav.php'; ?>

<!-- ========================================
     BARRE DE RECHERCHE (toujours visible)
     ======================================== -->
<div class="search-bar">
    <form method="get">
        <input type="text" name="nom1" placeholder="Nom de famille" value="<?= htmlspecialchars($_GET['nom1'] ?? '') ?>" style="width:150px;">
        <input type="text" name="nom2" placeholder="Prenom" value="<?= htmlspecialchars($_GET['nom2'] ?? '') ?>" style="width:130px;">
        <select name="sexe">
            <option value="">Sexe</option>
            <option value="M" <?= ($_GET['sexe'] ?? '') === 'M' ? 'selected' : '' ?>>Homme</option>
            <option value="F" <?= ($_GET['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Femme</option>
        </select>
        <select name="categorie">
            <option value="">Catégorie</option>
            <?php foreach (['EA','PO','BE','MI','CA','JU','ES','SE','V1','V2','V3','V4'] as $c): ?>
            <option value="<?= $c ?>" <?= ($_GET['categorie'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="nationalite" placeholder="NAT" value="<?= htmlspecialchars($_GET['nationalite'] ?? '') ?>" style="width:55px;">
        <input type="text" name="club" placeholder="Club" value="<?= htmlspecialchars($_GET['club'] ?? '') ?>" style="width:130px;">
        <input type="text" name="epreuve" placeholder="Épreuve" value="<?= htmlspecialchars($_GET['epreuve'] ?? '') ?>" style="width:110px;">
        <input type="text" name="ville" placeholder="Ville" value="<?= htmlspecialchars($_GET['ville'] ?? '') ?>" style="width:110px;">
        <select name="medaille">
            <option value="">Médaille</option>
            <option value="or" <?= ($_GET['medaille'] ?? '') === 'or' ? 'selected' : '' ?>>Or</option>
            <option value="argent" <?= ($_GET['medaille'] ?? '') === 'argent' ? 'selected' : '' ?>>Argent</option>
            <option value="bronze" <?= ($_GET['medaille'] ?? '') === 'bronze' ? 'selected' : '' ?>>Bronze</option>
        </select>
        <input type="text" name="annee" placeholder="Annee" value="<?= htmlspecialchars($_GET['annee'] ?? '') ?>" style="width:70px;">
        <input type="text" name="licence" placeholder="N° Licence" value="<?= htmlspecialchars($_GET['licence'] ?? '') ?>" style="width:120px;">
        <button type="submit">Rechercher</button>
    </form>
</div>


<?php
// ================================================================
//  FICHE ATHLETE (si ?id=)
// ================================================================
if ($id && is_numeric($id)):

    $data = $seoData ?: apiCall("$BASE_API/athlete.php?id=$id");

    if (!$data || !($data['success'] ?? false)):
?>
    <div class="error">Athlète #<?= htmlspecialchars($id) ?> non trouvé dans la base de données.</div>
<?php else:
    $identite     = $data['identite']     ?? [];
    $clubs        = $data['clubs']        ?? [];
    $medailles    = $data['medailles']    ?? [];
    $selections   = $data['selections']   ?? [];
    $progressions = $data['progressions'] ?? [];
    $records      = $data['records']      ?? [];
    $podiums      = $data['podiums']      ?? [];
    $resultats    = $data['resultats']    ?? [];
    $niveaux      = $data['niveaux']      ?? [];
    $nom = htmlspecialchars($identite['nom_complet'] ?? 'Inconnu');
?>

<a href="global_athlete.php" class="back-link">← Retour recherche</a>

<!-- IDENTITE -->
<h1><?= $nom ?></h1>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 4px;">
    <a href="../index.php?page=profil&id=<?= intval($identite['athlete_id'] ?? 0) ?>" style="display:inline-block;padding:6px 16px;background:#6c5ce720;border:1px solid #6c5ce740;border-radius:6px;color:#a29bfe;font-size:13px;font-weight:600;">Voir profil complet &#8599;</a>
</div>
<div class="info">
    <?php if (!empty($identite['date_naissance'])): ?>
        Naissance : <?= substr($identite['date_naissance'], 0, 4) ?>
        <?php if (!empty($identite['lieu_naissance'])): ?> — <?= htmlspecialchars($identite['lieu_naissance']) ?><?php endif; ?>
        <br/>
    <?php endif; ?>
    <?php if (!empty($identite['categorie'])): ?>Catégorie : <?= htmlspecialchars($identite['categorie']) ?> | <?php endif; ?>
    <?php if (!empty($identite['sexe'])): ?>Sexe : <?= htmlspecialchars($identite['sexe']) ?> | <?php endif; ?>
    <?php if (!empty($identite['nationalite'])): ?>Nationalite : <?= htmlspecialchars($identite['nationalite']) ?> | <?php endif; ?>
    <?php if (!empty($identite['taille_cm'])): ?>Taille : <?= $identite['taille_cm'] ?>cm | <?php endif; ?>
    <?php if (!empty($identite['poids_kg'])): ?>Poids : <?= $identite['poids_kg'] ?>kg | <?php endif; ?>
    <?php if (!empty($identite['licence'])): ?>Licence : <?= htmlspecialchars($identite['licence']) ?> | <?php endif; ?>
    ID athle.fr : <?= $identite['athlete_id'] ?>
</div>

<!-- CLUBS -->
<?php if (!empty($clubs)): ?>
<h2>Clubs <span class="section-count">(<?= count($clubs) ?>)</span></h2>
<?php foreach ($clubs as $c): ?>
    <div class="card">
        <b><a href="?club=<?= urlencode($c['nom_club']) ?>"><?= htmlspecialchars($c['nom_club']) ?></a></b>
        <span><?= ($c['annee_debut'] ?? '?') ?> → <?= ($c['annee_fin'] ?? '?') ?></span>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<!-- MEDAILLES -->
<?php if (!empty($medailles)): ?>
<h2>Médailles <span class="section-count">(<?= count($medailles) ?>)</span></h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>Type</th><th>Épreuve</th><th>Compétition</th><th>Année</th><th>Lieu</th></tr></table>
<table class="bk-table">
    <?php foreach ($medailles as $m): ?>
    <tr>
        <td><span class="badge <?= $m['type'] ?>"><?= $m['type'] ?></span></td>
        <td><a href="../index.php?page=recherche&epreuve=<?= urlencode($m['epreuve']) ?>"><?= htmlspecialchars($m['epreuve']) ?></a></td>
        <td><?= htmlspecialchars($m['competition']) ?></td>
        <td><?= $m['annee'] ?></td>
        <td><?php if (!empty($m['lieu'])): ?><a href="?ville=<?= urlencode($m['lieu']) ?>"><?= htmlspecialchars($m['lieu']) ?></a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>Type</th><th>Épreuve</th><th>Compétition</th><th>Année</th><th>Lieu</th></tr></table>
</div>
<?php endif; ?>

<!-- RECORDS -->
<?php if (!empty($records)): ?>
<h2>Records personnels <span class="section-count">(<?= count($records) ?>)</span></h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>Épreuve</th><th>Performance</th><th>Date</th><th>Lieu</th><th>Club</th><th>Cat</th></tr></table>
<table class="bk-table">
    <?php foreach ($records as $r): ?>
    <tr>
        <td><a href="../index.php?page=recherche&epreuve=<?= urlencode($r['epreuve']) ?>"><?= htmlspecialchars($r['epreuve']) ?></a></td>
        <td><b><?= htmlspecialchars($r['performance_brut'] ?? $r['performance'] ?? '') ?></b></td>
        <td><?= dateFR($r['date'] ?? '') ?></td>
        <td><?php if (!empty($r['lieu'])): ?><a href="?ville=<?= urlencode($r['lieu']) ?>"><?= htmlspecialchars($r['lieu']) ?></a><?php else: ?><?php endif; ?></td>
        <td><?php if (!empty($r['club'])): ?><a href="?club=<?= urlencode($r['club']) ?>"><?= htmlspecialchars($r['club']) ?></a><?php else: ?><?php endif; ?></td>
        <td><?= htmlspecialchars($r['categorie'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>Épreuve</th><th>Performance</th><th>Date</th><th>Lieu</th><th>Club</th><th>Cat</th></tr></table>
</div>
<?php endif; ?>

<!-- PROGRESSIONS -->
<?php if (!empty($progressions)): ?>
<h2>Progressions <span class="section-count">(<?= count($progressions) ?>)</span></h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>Épreuve</th><th>Performance</th><th>Année</th><th>Vent</th><th>Date</th><th>Lieu</th><th>Club</th><th>Cat</th></tr></table>
<table class="bk-table">
    <?php foreach ($progressions as $p): ?>
    <tr>
        <td><a href="../index.php?page=recherche&epreuve=<?= urlencode($p['epreuve']) ?>"><?= htmlspecialchars($p['epreuve']) ?></a></td>
        <td><b><?= htmlspecialchars($p['performance_brut'] ?? $p['performance'] ?? '') ?></b></td>
        <td><?= $p['annee'] ?></td>
        <td><?= htmlspecialchars($p['vent'] ?? '') ?></td>
        <td><?= dateFR($p['date'] ?? '') ?></td>
        <td><?php if (!empty($p['lieu'])): ?><a href="?ville=<?= urlencode($p['lieu']) ?>"><?= htmlspecialchars($p['lieu']) ?></a><?php else: ?><?php endif; ?></td>
        <td><?php if (!empty($p['club'])): ?><a href="?club=<?= urlencode($p['club']) ?>"><?= htmlspecialchars($p['club']) ?></a><?php else: ?><?php endif; ?></td>
        <td><?= htmlspecialchars($p['categorie'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>Épreuve</th><th>Performance</th><th>Année</th><th>Vent</th><th>Date</th><th>Lieu</th><th>Club</th><th>Cat</th></tr></table>
</div>
<?php endif; ?>

<!-- PODIUMS -->
<?php if (!empty($podiums)): ?>
<h2>Podiums <span class="section-count">(<?= count($podiums) ?>)</span></h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>Épreuve</th><th>Performance</th><th>Place</th><th>Rang</th><th>Année</th><th>Niveau</th><th>Vent</th><th>Date</th><th>Lieu</th></tr></table>
<table class="bk-table">
    <?php foreach ($podiums as $p): ?>
    <tr>
        <td><a href="../index.php?page=recherche&epreuve=<?= urlencode($p['epreuve']) ?>"><?= htmlspecialchars($p['epreuve']) ?></a></td>
        <td><b><?= htmlspecialchars($p['performance_brut'] ?? $p['performance'] ?? '') ?></b></td>
        <td><?= htmlspecialchars($p['place'] ?? '') ?></td>
        <td><?= $p['rang'] ?? '' ?></td>
        <td><?= $p['annee'] ?></td>
        <td><?= htmlspecialchars($p['niveau_competition'] ?? '') ?></td>
        <td><?= htmlspecialchars($p['vent'] ?? '') ?></td>
        <td><?= dateFR($p['date'] ?? '') ?></td>
        <td><?php if (!empty($p['lieu'])): ?><a href="?ville=<?= urlencode($p['lieu']) ?>"><?= htmlspecialchars($p['lieu']) ?></a><?php else: ?><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>Épreuve</th><th>Performance</th><th>Place</th><th>Rang</th><th>Année</th><th>Niveau</th><th>Vent</th><th>Date</th><th>Lieu</th></tr></table>
</div>
<?php endif; ?>

<!-- RESULTATS -->
<?php if (!empty($resultats)): ?>
<h2>Résultats <span class="section-count">(<?= count($resultats) ?>)</span></h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>Épreuve</th><th>Perf</th><th>Date</th><th>Place</th><th>Vent</th><th>Tour</th><th>Niveau</th><th>Points</th><th>Lieu</th></tr></table>
<table class="bk-table">
    <?php foreach ($resultats as $r): ?>
    <tr>
        <td><a href="../index.php?page=recherche&epreuve=<?= urlencode($r['epreuve']) ?>"><?= htmlspecialchars($r['epreuve']) ?></a></td>
        <td><b><?= htmlspecialchars($r['performance_brut'] ?? $r['performance'] ?? '') ?></b></td>
        <td><?= dateFR($r['date'] ?? '') ?></td>
        <td><?= $r['place'] ?? '' ?></td>
        <td><?= htmlspecialchars($r['vent'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['tour'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['niveau'] ?? '') ?></td>
        <td><?= $r['points'] ?? '' ?></td>
        <td><?php if (!empty($r['lieu'])): ?><a href="?ville=<?= urlencode($r['lieu']) ?>"><?= htmlspecialchars($r['lieu']) ?></a><?php else: ?><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>Épreuve</th><th>Perf</th><th>Date</th><th>Place</th><th>Vent</th><th>Tour</th><th>Niveau</th><th>Points</th><th>Lieu</th></tr></table>
</div>
<?php endif; ?>

<!-- SELECTIONS -->
<?php if (!empty($selections)): ?>
<h2>Sélections <span class="section-count">(<?= count($selections) ?>)</span></h2>
<div class="table-wrap">
<table class="bk-table"><tr><th>Compétition</th><th>Épreuve</th><th>Performance</th><th>Type</th><th>Classement</th><th>Date</th><th>Durée</th><th>Age</th></tr></table>
<table class="bk-table">
    <?php foreach ($selections as $s): ?>
    <tr>
        <td><?= htmlspecialchars($s['competition'] ?? '') ?></td>
        <td><?php if (!empty($s['epreuve'])): ?><a href="../index.php?page=recherche&epreuve=<?= urlencode($s['epreuve']) ?>"><?= htmlspecialchars($s['epreuve']) ?></a><?php else: ?><?php endif; ?></td>
        <td><b><?= htmlspecialchars($s['performance_brut'] ?? $s['performance'] ?? '') ?></b></td>
        <td><?= htmlspecialchars($s['type'] ?? '') ?></td>
        <td><?= $s['classement'] ?? '' ?></td>
        <td><?= dateFR($s['date'] ?? '') ?></td>
        <td><?= !empty($s['duree_jours']) ? $s['duree_jours'] . 'j' : '' ?></td>
        <td><?= !empty($s['age']) ? $s['age'] . ' ans' : '' ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<table class="bk-table"><tr><th>Compétition</th><th>Épreuve</th><th>Performance</th><th>Type</th><th>Classement</th><th>Date</th><th>Durée</th><th>Age</th></tr></table>
</div>
<?php endif; ?>

<!-- NIVEAUX -->
<?php if (!empty($niveaux)): ?>
<h2>Niveaux <span class="section-count">(<?= count($niveaux) ?>)</span></h2>
<?php foreach ($niveaux as $n): ?>
    <div class="card">
        <b><?= $n['annee'] ?></b> — <?= htmlspecialchars($n['code_niveau']) ?>
        (<?= $n['points_niveau'] ?? '?' ?> pts)
        <span>| <?php if (!empty($n['club'])): ?><a href="?club=<?= urlencode($n['club']) ?>"><?= htmlspecialchars($n['club']) ?></a><?php endif; ?></span>
        <?php if (!empty($n['performances'])): ?>
            <table class="bk-table" style="margin-top:8px"><tr><th>Épreuve</th><th>Performance</th><th>Code</th></tr></table>
            <table class="bk-table">
                <?php foreach ($n['performances'] as $perf): ?>
                <tr>
                    <td><a href="../index.php?page=recherche&epreuve=<?= urlencode($perf['epreuve']) ?>"><?= htmlspecialchars($perf['epreuve']) ?></a></td>
                    <td><b><?= htmlspecialchars($perf['performance_brut'] ?? $perf['performance'] ?? '') ?></b></td>
                    <td><?= htmlspecialchars($perf['code_niveau'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <table class="bk-table"><tr><th>Épreuve</th><th>Performance</th><th>Code</th></tr></table>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<!-- JSON API -->
<h2>JSON brut (API)</h2>
<p><a href="<?= $BASE_API ?>/athlete.php?id=<?= $id ?>" target="_blank">Voir le JSON → api/athlete.php?id=<?= $id ?></a></p>

<!-- QR Code de partage -->
<div style="text-align:center;padding:20px;margin-top:20px;border-top:1px solid #1a2540;">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?= urlencode('https://bokonzi.com/pages/global_athlete.php?id=' . $id) ?>" alt="QR Code" width="120" height="120" style="border-radius:8px;background:#fff;padding:6px;">
    <div style="color:#5a6580;font-size:12px;margin-top:8px;">Scannez pour partager cette fiche</div>
</div>

<?php endif; // fin fiche athlete ?>


<?php
// ================================================================
//  RESULTATS DE RECHERCHE (si pas d'id mais des filtres)
// ================================================================
elseif (!$id):

    // Construire les parametres de recherche
    $params = [];
    foreach (['nom1','nom2','sexe','categorie','nationalite','club','epreuve','ville','medaille','annee','licence'] as $key) {
        if (!empty($_GET[$key])) $params[$key] = $_GET[$key];
    }

    if (!empty($params)):
        $p = max(1, (int)($_GET['p'] ?? 1));
        $params['page'] = $p;
        $params['limit'] = 50;
        $url = "$BASE_API/search.php?" . http_build_query($params);
        $data = apiCall($url);

        if ($data && ($data['success'] ?? false)):

        // Titre du filtre actif
        $filterLabels = [];
        if (!empty($params['epreuve'])) $filterLabels[] = 'Épreuve : ' . htmlspecialchars($params['epreuve']);
        if (!empty($params['club'])) $filterLabels[] = 'Club : ' . htmlspecialchars($params['club']);
        if (!empty($params['ville'])) $filterLabels[] = 'Ville : ' . htmlspecialchars($params['ville']);
        if (!empty($params['medaille'])) $filterLabels[] = 'Médaille : ' . htmlspecialchars($params['medaille']);
        if (!empty($params['annee'])) $filterLabels[] = 'Année : ' . htmlspecialchars($params['annee']);
        if (!empty($params['competition'])) $filterLabels[] = 'Compétition : ' . htmlspecialchars($params['competition']);
        if (!empty($params['nom1'])) $filterLabels[] = 'Nom : ' . htmlspecialchars($params['nom1']);
        if (!empty($params['nom2'])) $filterLabels[] = 'Prénom : ' . htmlspecialchars($params['nom2']);
        if (!empty($params['sexe'])) $filterLabels[] = 'Sexe : ' . htmlspecialchars($params['sexe']);
        if (!empty($params['categorie'])) $filterLabels[] = 'Catégorie : ' . htmlspecialchars($params['categorie']);
        if (!empty($params['nationalite'])) $filterLabels[] = 'Nationalité : ' . htmlspecialchars($params['nationalite']);

        // Fonction badge niveau
        function _nivBadge($code) {
            $p = ($code[0] ?? '');
            if ($p === 'N') { $bg = '#e11d4820'; $bc = '#e11d48'; $tc = '#fb7185'; }
            elseif ($p === 'I') { $bg = '#c026d320'; $bc = '#c026d3'; $tc = '#e879f9'; }
            elseif ($p === 'R') { $bg = '#0891b220'; $bc = '#0891b2'; $tc = '#22d3ee'; }
            else { $bg = '#f9731620'; $bc = '#f97316'; $tc = '#fb923c'; }
            return '<span style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:11px;background:'.$bg.';border:1px solid '.$bc.'40;color:'.$tc.';font-weight:600;margin:1px;">' . htmlspecialchars($code) . '</span>';
        }
?>

<?php if (!empty($filterLabels)): ?>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
    <?php foreach ($filterLabels as $fl): ?>
    <span style="display:inline-block;padding:5px 14px;border-radius:6px;font-size:13px;background:#6c5ce720;border:1px solid #6c5ce740;color:#a29bfe;font-weight:600;"><?= $fl ?></span>
    <?php endforeach; ?>
    <?php if (!empty($params['epreuve'])): ?>
    <a href="../index.php?page=epreuves&nom=<?= urlencode($params['epreuve']) ?>" style="padding:5px 14px;border-radius:6px;font-size:13px;background:#10b98120;border:1px solid #10b98140;color:#34d399;font-weight:600;">Voir stats épreuve &#8599;</a>
    <?php endif; ?>
    <?php if (!empty($params['club'])): ?>
    <a href="../index.php?page=clubs&open=<?= urlencode($params['club']) ?>" style="padding:5px 14px;border-radius:6px;font-size:13px;background:#10b98120;border:1px solid #10b98140;color:#34d399;font-weight:600;">Voir club &#8599;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<p class="total"><?= number_format($data['total'], 0, ',', ' ') ?> athlètes trouvés</p>

<div class="table-wrap">
<table class="bk-table">
    <tr>
        <th>#</th>
        <th>Athlète</th>
        <th>Cat</th>
        <th>Sexe</th>
        <th>NAT</th>
        <th>Niveaux</th>
        <th>Records</th>
        <?php if (isset($data['athletes'][0]['filtre_debut'])): ?><th>Période</th><?php endif; ?>
        <th>Top records</th>
        <th></th>
    </tr>
    <?php foreach ($data['athletes'] as $_idx => $a):
        $rank = ($p - 1) * 50 + $_idx + 1;
        $profUrl = '../index.php?page=profil&id=' . $a['athlete_id'];
    ?>
    <tr style="cursor:pointer;" onclick="window.location='<?= $profUrl ?>'">
        <td style="color:#5a6580;"><?= $rank ?></td>
        <td>
            <b><a href="<?= $profUrl ?>" style="color:#a29bfe;"><?= htmlspecialchars($a['nom_complet']) ?></a></b>
            <?php if (!empty($a['licence'])): ?><br><span style="font-size:11px;color:#5a6580;">Lic. <?= htmlspecialchars($a['licence']) ?></span><?php endif; ?>
        </td>
        <td><span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;background:#6c5ce720;color:#a29bfe;"><?= htmlspecialchars($a['categorie'] ?? '') ?></span></td>
        <td><?= htmlspecialchars($a['sexe'] ?? '') ?></td>
        <td><?= htmlspecialchars($a['nationalite'] ?? '') ?></td>
        <td><?php
            if (!empty($a['niveaux'])) {
                $shown = 0;
                foreach ($a['niveaux'] as $niv) {
                    if ($shown >= 3) { echo '<span style="color:#5a6580;font-size:11px;">+' . (count($a['niveaux']) - 3) . '</span>'; break; }
                    echo _nivBadge($niv);
                    $shown++;
                }
            } else { echo '-'; }
        ?></td>
        <td><span style="color:#fb923c;font-weight:600;"><?= $a['nb_records'] ?? 0 ?></span></td>
        <?php if (isset($a['filtre_debut'])): ?>
        <td style="font-size:12px;color:#8b949e;">
            <?php
                $fd = $a['filtre_debut'] ?? '';
                $ff = $a['filtre_fin'] ?? '';
                if ($fd) {
                    $fdY = substr($fd, 0, 4);
                    $ffY = $ff ? substr($ff, 0, 4) : '';
                    echo $fdY . ($ffY && $ffY !== $fdY ? ' → ' . $ffY : '');
                } else { echo '-'; }
            ?>
        </td>
        <?php endif; ?>
        <td style="font-size:12px;">
            <?php if (!empty($a['top_records'])):
                foreach (array_slice($a['top_records'], 0, 3) as $rec): ?>
                    <div style="margin:2px 0;">
                        <a href="../index.php?page=recherche&epreuve=<?= urlencode($rec['epreuve']) ?>" style="color:#818cf8;font-size:11px;"><?= htmlspecialchars($rec['epreuve']) ?></a>
                        <span style="color:#fbbf24;font-weight:600;font-size:11px;margin-left:4px;"><?= htmlspecialchars($rec['performance'] ?? '') ?></span>
                        <?php foreach (($rec['niveaux'] ?? []) as $rniv): ?>
                            <?= _nivBadge($rniv) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach;
                if (count($a['top_records']) > 3): ?>
                    <span style="color:#5a6580;font-size:11px;">+<?= count($a['top_records']) - 3 ?> autres</span>
                <?php endif;
            else: echo '-'; endif; ?>
        </td>
        <td><a href="<?= $profUrl ?>" style="padding:4px 10px;border-radius:4px;background:#6c5ce720;color:#a29bfe;font-size:12px;">Profil</a></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>

<?php if ($data['total_pages'] > 1): ?>
<div class="pager">
    <?php
    $base = $_GET;
    unset($base['p']);
    $qs = http_build_query($base);
    ?>
    <?php if ($p > 1): ?><a href="?<?= $qs ?>&p=<?= $p - 1 ?>">← Precedent</a><?php endif; ?>
    <?php for ($i = max(1, $p - 4); $i <= min($data['total_pages'], $p + 4); $i++): ?>
        <?php if ($i == $p): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?<?= $qs ?>&p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($p < $data['total_pages']): ?><a href="?<?= $qs ?>&p=<?= $p + 1 ?>">Suivant →</a><?php endif; ?>
    <span style="color:#666;margin-left:10px;">(<?= $data['total_pages'] ?> pages)</span>
</div>
<?php endif; ?>

<?php elseif ($data): ?>
        <div class="error"><?= htmlspecialchars($data['error'] ?? 'Erreur') ?></div>
<?php else: ?>
        <div class="error">Impossible de contacter le serveur.</div>
<?php endif; ?>

<?php else: ?>
        <h1>Recherche Athletes</h1>
        <p class="info">Remplis au moins un champ et clique sur Rechercher.</p>
<?php endif; ?>

<?php endif; ?>

</body>
</html>
