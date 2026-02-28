<?php
/**
 * test_api.php — Exemple d'utilisation de l'API depuis localhost
 *
 * Usage : http://localhost/BK/test_api.php
 */

$BASE_API = "https://bokonzi.com/api";

/**
 * Appel API simple
 */
function apiCall($url) {
    $json = @file_get_contents($url);
    if (!$json) return ['success' => false, 'error' => "Impossible de contacter $url"];
    return json_decode($json, true);
}

function dateFR($d) {
    if (!$d || $d === '-') return '-';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $d;
}

// Quelle demo afficher ?
$demo = $_GET['demo'] ?? 'menu';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test API Bokonzi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #0a0a1a; color: #eee; padding: 20px; }
        h1 { color: #00d4ff; margin-bottom: 20px; }
        h2 { color: #00d4ff; margin: 20px 0 10px; }
        a { color: #00d4ff; }
        .menu a { display: block; background: #16213e; padding: 15px; margin: 8px 0; border-radius: 8px; text-decoration: none; font-size: 18px; }
        .menu a:hover { background: #1a3a5e; }
        .menu span { color: #888; font-size: 14px; display: block; margin-top: 4px; }
        pre { background: #16213e; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 13px; margin: 10px 0; }
        .code { background: #1a1a2e; border: 1px solid #333; padding: 12px; border-radius: 6px; margin: 10px 0; font-family: monospace; font-size: 13px; color: #0f0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #0f3460; color: #00d4ff; padding: 8px; text-align: left; }
        td { padding: 6px 8px; border-bottom: 1px solid #222; }
        tr:hover { background: #1a1a3e; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .or { background: #ffd700; color: #000; }
        .argent { background: #c0c0c0; color: #000; }
        .bronze { background: #cd7f32; color: #000; }
        .back { display: inline-block; margin-bottom: 15px; padding: 8px 16px; background: #333; border-radius: 6px; text-decoration: none; color: #fff; }
        .stat-box { display: inline-block; background: #16213e; padding: 15px 25px; border-radius: 8px; margin: 5px; text-align: center; }
        .stat-box .num { font-size: 28px; font-weight: bold; color: #00d4ff; }
        .stat-box .label { font-size: 12px; color: #888; }
        .search-form { background: #16213e; padding: 20px; border-radius: 8px; margin: 15px 0; }
        .search-form input, .search-form select { background: #0a0a1a; border: 1px solid #333; color: #eee; padding: 8px 12px; border-radius: 4px; margin: 4px; }
        .search-form button { background: #00d4ff; color: #000; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

<?php if ($demo === 'menu'): ?>

<h1>Test API Bokonzi — Localhost</h1>
<p style="color:#888;margin-bottom:20px;">Exemples d'appels vers <b><?= $BASE_API ?></b> depuis ton localhost</p>

<div class="menu">
    <a href="?demo=stats">Statistiques globales
        <span>GET <?= $BASE_API ?>/stats.php?detail=1</span>
    </a>
    <a href="?demo=athlete&id=26134">Fiche athlete #26134
        <span>GET <?= $BASE_API ?>/athlete.php?id=26134</span>
    </a>
    <a href="?demo=search">Recherche d'athletes
        <span>GET <?= $BASE_API ?>/search.php?nom=...&sexe=...&categorie=...</span>
    </a>
    <a href="?demo=liste">Liste paginee
        <span>GET <?= $BASE_API ?>/liste.php?page=1&limit=20</span>
    </a>
    <a href="?demo=clubs">Clubs
        <span>GET <?= $BASE_API ?>/clubs.php</span>
    </a>
    <a href="?demo=epreuves">Epreuves
        <span>GET <?= $BASE_API ?>/epreuves.php</span>
    </a>
    <a href="?demo=code">Exemples de code PHP
        <span>Comment appeler l'API dans ton propre code</span>
    </a>
</div>


<?php elseif ($demo === 'stats'): ?>

<a href="?demo=menu" class="back">Retour</a>
<h1>Statistiques</h1>

<div class="code">GET <?= $BASE_API ?>/stats.php?detail=1</div>

<?php
$data = apiCall("$BASE_API/stats.php?detail=1");
if ($data['success'] ?? false):
?>

<div style="margin:15px 0;">
<?php foreach ($data['comptages'] as $key => $info): ?>
    <div class="stat-box">
        <div class="num"><?= number_format($info['count'], 0, ',', ' ') ?></div>
        <div class="label"><?= $info['label'] ?></div>
    </div>
<?php endforeach; ?>
</div>

<?php if (!empty($data['par_sexe'])): ?>
<h2>Par sexe</h2>
<table>
    <tr><th>Sexe</th><th>Nombre</th></tr>
    <?php foreach ($data['par_sexe'] as $sexe => $nb): ?>
    <tr><td><?= $sexe === 'M' ? 'Homme' : ($sexe === 'F' ? 'Femme' : $sexe) ?></td><td><?= number_format($nb, 0, ',', ' ') ?></td></tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (!empty($data['par_categorie'])): ?>
<h2>Par categorie</h2>
<table>
    <tr><th>Categorie</th><th>Nombre</th></tr>
    <?php foreach ($data['par_categorie'] as $cat => $nb): ?>
    <tr><td><?= htmlspecialchars($cat) ?></td><td><?= number_format($nb, 0, ',', ' ') ?></td></tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (!empty($data['medailles_par_type'])): ?>
<h2>Medailles</h2>
<table>
    <tr><th>Type</th><th>Nombre</th></tr>
    <?php foreach ($data['medailles_par_type'] as $type => $nb): ?>
    <tr><td><span class="badge <?= $type ?>"><?= $type ?></span></td><td><?= number_format($nb, 0, ',', ' ') ?></td></tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (!empty($data['top_clubs'])): ?>
<h2>Top 10 clubs</h2>
<table>
    <tr><th>#</th><th>Club</th><th>Athletes</th></tr>
    <?php foreach ($data['top_clubs'] as $i => $c): ?>
    <tr><td><?= $i + 1 ?></td><td><?= htmlspecialchars($c['club']) ?></td><td><?= $c['nb_athletes'] ?></td></tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php else: ?>
<p style="color:red;">Erreur : <?= htmlspecialchars($data['error'] ?? 'Serveur injoignable') ?></p>
<?php endif; ?>


<?php elseif ($demo === 'athlete'): ?>

<a href="?demo=menu" class="back">Retour</a>
<?php
$id = $_GET['id'] ?? 26134;
$url = "$BASE_API/athlete.php?id=$id";
?>
<h1>Fiche athlete #<?= htmlspecialchars($id) ?></h1>
<div class="code">GET <?= $url ?></div>

<?php
$data = apiCall($url);
if ($data['success'] ?? false):
    $i = $data['identite'];
?>

<h2><?= htmlspecialchars($i['nom_complet']) ?></h2>
<table>
    <tr><td>ID athle.fr</td><td><b><?= $i['athlete_id'] ?></b></td></tr>
    <tr><td>Naissance</td><td><?= dateFR($i['date_naissance'] ?? '-') ?> <?= $i['lieu_naissance'] ? '— ' . htmlspecialchars($i['lieu_naissance']) : '' ?></td></tr>
    <tr><td>Categorie</td><td><?= htmlspecialchars($i['categorie']) ?></td></tr>
    <tr><td>Sexe</td><td><?= $i['sexe'] ?></td></tr>
    <tr><td>Nationalite</td><td><?= htmlspecialchars($i['nationalite']) ?></td></tr>
    <?php if ($i['taille_cm']): ?><tr><td>Taille</td><td><?= $i['taille_cm'] ?> cm</td></tr><?php endif; ?>
    <?php if ($i['poids_kg']): ?><tr><td>Poids</td><td><?= $i['poids_kg'] ?> kg</td></tr><?php endif; ?>
    <tr><td>Licence</td><td><?= htmlspecialchars($i['licence']) ?></td></tr>
</table>

<?php if (!empty($data['clubs'])): ?>
<h2>Clubs (<?= count($data['clubs']) ?>)</h2>
<table>
    <tr><th>Club</th><th>Debut</th><th>Fin</th></tr>
    <?php foreach ($data['clubs'] as $c): ?>
    <tr><td><?= htmlspecialchars($c['nom_club']) ?></td><td><?= $c['annee_debut'] ?? '-' ?></td><td><?= $c['annee_fin'] ?? '-' ?></td></tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (!empty($data['medailles'])): ?>
<h2>Medailles (<?= count($data['medailles']) ?>)</h2>
<table>
    <tr><th>Type</th><th>Annee</th><th>Epreuve</th><th>Competition</th></tr>
    <?php foreach ($data['medailles'] as $m): ?>
    <tr>
        <td><span class="badge <?= $m['type'] ?>"><?= $m['type'] ?></span></td>
        <td><?= $m['annee'] ?></td>
        <td><?= htmlspecialchars($m['epreuve']) ?></td>
        <td><?= htmlspecialchars($m['competition']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (!empty($data['records'])): ?>
<h2>Records (<?= count($data['records']) ?>)</h2>
<table>
    <tr><th>Epreuve</th><th>Performance</th><th>Date</th><th>Lieu</th></tr>
    <?php foreach ($data['records'] as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r['epreuve']) ?></td>
        <td><b><?= htmlspecialchars($r['performance_brut'] ?: $r['performance']) ?></b></td>
        <td><?= dateFR($r['date'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['lieu']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (!empty($data['resultats'])): ?>
<h2>Resultats (<?= count($data['resultats']) ?>)</h2>
<table>
    <tr><th>Date</th><th>Epreuve</th><th>Perf</th><th>Place</th><th>Lieu</th></tr>
    <?php foreach (array_slice($data['resultats'], 0, 20) as $r): ?>
    <tr>
        <td><?= dateFR($r['date'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['epreuve']) ?></td>
        <td><b><?= htmlspecialchars($r['performance_brut'] ?: $r['performance']) ?></b></td>
        <td><?= $r['place'] ?></td>
        <td><?= htmlspecialchars($r['lieu']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (count($data['resultats']) > 20): ?>
    <tr><td colspan="5" style="color:#888">... et <?= count($data['resultats']) - 20 ?> autres resultats</td></tr>
    <?php endif; ?>
</table>
<?php endif; ?>

<h2>JSON brut</h2>
<pre><?= htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<?php else: ?>
<p style="color:red;">Athlete non trouve. <?= htmlspecialchars($data['error'] ?? '') ?></p>
<?php endif; ?>


<?php elseif ($demo === 'search'): ?>

<a href="?demo=menu" class="back">Retour</a>
<h1>Recherche</h1>

<div class="search-form">
    <form method="get">
        <input type="hidden" name="demo" value="search">
        <input type="text" name="nom" placeholder="Nom..." value="<?= htmlspecialchars($_GET['nom'] ?? '') ?>">
        <select name="sexe">
            <option value="">Sexe</option>
            <option value="M" <?= ($_GET['sexe'] ?? '') === 'M' ? 'selected' : '' ?>>Homme</option>
            <option value="F" <?= ($_GET['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Femme</option>
        </select>
        <input type="text" name="categorie" placeholder="Categorie (SE, JU...)" value="<?= htmlspecialchars($_GET['categorie'] ?? '') ?>" size="8">
        <input type="text" name="club" placeholder="Club..." value="<?= htmlspecialchars($_GET['club'] ?? '') ?>">
        <input type="text" name="nationalite" placeholder="NAT (FRA)" value="<?= htmlspecialchars($_GET['nationalite'] ?? '') ?>" size="5">
        <button type="submit">Rechercher</button>
    </form>
</div>

<?php
$params = [];
if (!empty($_GET['nom'])) $params['nom'] = $_GET['nom'];
if (!empty($_GET['sexe'])) $params['sexe'] = $_GET['sexe'];
if (!empty($_GET['categorie'])) $params['categorie'] = $_GET['categorie'];
if (!empty($_GET['club'])) $params['club'] = $_GET['club'];
if (!empty($_GET['nationalite'])) $params['nationalite'] = $_GET['nationalite'];
$params['page'] = $_GET['p'] ?? 1;

if (!empty($params) && count($params) > 1):
    $url = "$BASE_API/search.php?" . http_build_query($params);
?>
<div class="code">GET <?= htmlspecialchars($url) ?></div>

<?php
    $data = apiCall($url);
    if ($data['success'] ?? false):
?>
<p style="color:#888;"><?= $data['total'] ?> resultats — page <?= $data['page'] ?>/<?= $data['total_pages'] ?></p>

<table>
    <tr><th>ID</th><th>Nom</th><th>Naissance</th><th>Cat</th><th>Sexe</th><th>NAT</th><th></th></tr>
    <?php foreach ($data['athletes'] as $a): ?>
    <tr>
        <td><?= $a['athlete_id'] ?></td>
        <td><b><?= htmlspecialchars($a['nom_complet']) ?></b></td>
        <td><?= dateFR($a['date_naissance'] ?? '-') ?></td>
        <td><?= $a['categorie'] ?></td>
        <td><?= $a['sexe'] ?></td>
        <td><?= $a['nationalite'] ?></td>
        <td><a href="?demo=athlete&id=<?= $a['athlete_id'] ?>">Voir</a></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php if ($data['total_pages'] > 1): ?>
<p>
    <?php for ($p = 1; $p <= min($data['total_pages'], 10); $p++): ?>
        <?php $params['p'] = $p; ?>
        <a href="?demo=search&<?= http_build_query(array_merge($_GET, ['p' => $p])) ?>" style="padding:5px 10px;<?= $p == $data['page'] ? 'background:#00d4ff;color:#000;border-radius:4px;' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
</p>
<?php endif; ?>

<?php else: ?>
<p style="color:red;"><?= htmlspecialchars($data['error'] ?? 'Erreur') ?></p>
<?php endif; ?>
<?php endif; ?>


<?php elseif ($demo === 'liste'): ?>

<a href="?demo=menu" class="back">Retour</a>
<h1>Liste des athletes</h1>

<?php
$page = (int)($_GET['p'] ?? 1);
$url = "$BASE_API/liste.php?page=$page&limit=20&ordre=recent";
?>
<div class="code">GET <?= $url ?></div>

<?php
$data = apiCall($url);
if ($data['success'] ?? false):
?>
<p style="color:#888;"><?= number_format($data['total'], 0, ',', ' ') ?> athletes — page <?= $data['page'] ?>/<?= $data['total_pages'] ?></p>

<table>
    <tr><th>ID</th><th>Nom</th><th>Naissance</th><th>Cat</th><th>Sexe</th><th></th></tr>
    <?php foreach ($data['athletes'] as $a): ?>
    <tr>
        <td><?= $a['athlete_id'] ?></td>
        <td><?= htmlspecialchars($a['nom_complet']) ?></td>
        <td><?= dateFR($a['date_naissance'] ?? '-') ?></td>
        <td><?= $a['categorie'] ?></td>
        <td><?= $a['sexe'] ?></td>
        <td><a href="?demo=athlete&id=<?= $a['athlete_id'] ?>">Voir</a></td>
    </tr>
    <?php endforeach; ?>
</table>

<p>
    <?php if ($page > 1): ?><a href="?demo=liste&p=<?= $page - 1 ?>" class="back">Precedent</a><?php endif; ?>
    <span style="color:#888;margin:0 10px;">Page <?= $page ?> / <?= $data['total_pages'] ?></span>
    <?php if ($page < $data['total_pages']): ?><a href="?demo=liste&p=<?= $page + 1 ?>" class="back">Suivant</a><?php endif; ?>
</p>

<?php else: ?>
<p style="color:red;"><?= htmlspecialchars($data['error'] ?? 'Erreur') ?></p>
<?php endif; ?>


<?php elseif ($demo === 'clubs'): ?>

<a href="?demo=menu" class="back">Retour</a>
<h1>Clubs</h1>
<div class="code">GET <?= $BASE_API ?>/clubs.php?limit=20</div>

<?php $data = apiCall("$BASE_API/clubs.php?limit=20"); if ($data['success'] ?? false): ?>
<table>
    <tr><th>#</th><th>Club</th><th>Athletes</th></tr>
    <?php foreach ($data['clubs'] as $i => $c): ?>
    <tr><td><?= $i + 1 ?></td><td><?= htmlspecialchars($c['nom_club']) ?></td><td><?= $c['nb_athletes'] ?></td></tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
<p style="color:red;"><?= htmlspecialchars($data['error'] ?? 'Erreur') ?></p>
<?php endif; ?>


<?php elseif ($demo === 'epreuves'): ?>

<a href="?demo=menu" class="back">Retour</a>
<h1>Epreuves</h1>
<div class="code">GET <?= $BASE_API ?>/epreuves.php?limit=20</div>

<?php $data = apiCall("$BASE_API/epreuves.php?limit=20"); if ($data['success'] ?? false): ?>
<table>
    <tr><th>#</th><th>Epreuve</th><th>Athletes avec record</th></tr>
    <?php foreach ($data['epreuves'] as $i => $e): ?>
    <tr><td><?= $i + 1 ?></td><td><?= htmlspecialchars($e['nom_epreuve']) ?></td><td><?= $e['nb_athletes'] ?></td></tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
<p style="color:red;"><?= htmlspecialchars($data['error'] ?? 'Erreur') ?></p>
<?php endif; ?>


<?php elseif ($demo === 'code'): ?>

<a href="?demo=menu" class="back">Retour</a>
<h1>Exemples de code</h1>

<h2>PHP — Recuperer un athlete</h2>
<pre>
&lt;?php
$id = 26134;
$json = file_get_contents("https://bokonzi.com/api/athlete.php?id=$id");
$data = json_decode($json, true);

echo $data['identite']['nom_complet'];  // "DUPONT Jean"
echo $data['identite']['categorie'];    // "SE"

foreach ($data['records'] as $record) {
    echo $record['epreuve'] . " : " . $record['performance_brut'] . "\n";
}
</pre>

<h2>PHP — Rechercher des athletes</h2>
<pre>
&lt;?php
$params = http_build_query([
    'nom'       => 'dupont',
    'sexe'      => 'M',
    'categorie' => 'SE',
    'limit'     => 10,
]);
$json = file_get_contents("https://bokonzi.com/api/search.php?$params");
$data = json_decode($json, true);

echo "Total : " . $data['total'] . " resultats\n";
foreach ($data['athletes'] as $a) {
    echo $a['nom_complet'] . " (" . $a['categorie'] . ")\n";
}
</pre>

<h2>PHP — Statistiques</h2>
<pre>
&lt;?php
$json = file_get_contents("https://bokonzi.com/api/stats.php?detail=1");
$data = json_decode($json, true);

echo "Athletes : " . $data['comptages']['athletes']['count'] . "\n";
echo "Clubs : " . $data['comptages']['clubs']['count'] . "\n";

foreach ($data['top_clubs'] as $club) {
    echo $club['club'] . " : " . $club['nb_athletes'] . " athletes\n";
}
</pre>

<h2>JavaScript (fetch)</h2>
<pre>
// Fonctionne depuis n'importe quel site (CORS active)
fetch("https://bokonzi.com/api/athlete.php?id=26134")
  .then(response => response.json())
  .then(data => {
    console.log(data.identite.nom_complet);
    console.log(data.records);
  });
</pre>

<h2>JavaScript — Recherche avec formulaire</h2>
<pre>
async function searchAthletes(nom) {
    const response = await fetch(
        `https://bokonzi.com/api/search.php?nom=${encodeURIComponent(nom)}`
    );
    const data = await response.json();

    data.athletes.forEach(a => {
        console.log(`${a.nom_complet} - ${a.categorie} - ${a.nationalite}`);
    });
}

searchAthletes("dupont");
</pre>

<h2>cURL (terminal)</h2>
<pre>
# Un athlete
curl "https://bokonzi.com/api/athlete.php?id=26134"

# Recherche
curl "https://bokonzi.com/api/search.php?nom=dupont&sexe=M"

# Stats
curl "https://bokonzi.com/api/stats.php?detail=1"
</pre>

<?php endif; ?>

</body>
</html>
