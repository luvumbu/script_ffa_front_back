<?php
/**
 * admin/audit_athletes.php — Audit integrite des donnees athletes
 *
 * Pour chaque athlete en BDD, verifie s'il a :
 *   - identite complete (nom, sexe, categorie, nationalite, annee naissance)
 *   - clubs / records / progressions / resultats / medailles / podiums / selections / niveaux
 *
 * Sortie :
 *   - Stats globales : nb d'athletes sans chaque categorie de donnee
 *   - Top 200 athletes les plus incomplets (sortables)
 *   - Bouton "re-scraper cet athlete" pour reparer
 *
 * Performance : LEFT JOIN sur 330k+ athletes peut prendre 30-60s.
 *   → Cache disque 1h, recalcul manuel via bouton.
 *
 * Securite : cle API (bk_key) requise.
 */

$key = $_GET['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
if ($key !== 'bk_s3cr3t_2026_xK9mP') {
    http_response_code(403);
    die('Interdit');
}

@set_time_limit(180);
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/../core/db.php';

$cacheFile = __DIR__ . '/../logs/.audit_athletes.json';
$cacheTtl  = 3600;

// ============================================================================
// API : recalcul / lecture
// ============================================================================
$action = $_GET['action'] ?? null;
$force  = isset($_GET['force']);

function runAudit($conn, $cacheFile) {
    $t0 = microtime(true);

    // Total
    $total = (int)$conn->query("SELECT COUNT(*) c FROM athletes")->fetch_assoc()['c'];

    // Stats identite (rapide, c'est la table athletes elle-meme)
    $identite = $conn->query("
        SELECT
          SUM(nom_complet_athlete = '' OR nom_complet_athlete IS NULL)  AS sans_nom_complet,
          SUM(nom_1_athlete       = '' OR nom_1_athlete IS NULL)        AS sans_nom_1,
          SUM(sexe_athlete        = '' OR sexe_athlete IS NULL)         AS sans_sexe,
          SUM(categorie_athlete   = '' OR categorie_athlete IS NULL)    AS sans_categorie,
          SUM(nationalite_athlete = '' OR nationalite_athlete IS NULL)  AS sans_nationalite,
          SUM(licence_athlete     = '' OR licence_athlete IS NULL)      AS sans_licence,
          SUM(annee_naissance_athlete IS NULL)                          AS sans_annee_naissance
        FROM athletes
    ")->fetch_assoc();

    // Stats enfants : combien d'athletes n'ont AUCUNE entree dans chaque table enfant.
    // Use NOT EXISTS qui est plus rapide que LEFT JOIN avec WHERE NULL sur grosses tables.
    $enfants = [
        'sans_clubs'        => "SELECT COUNT(*) c FROM athletes a WHERE NOT EXISTS (SELECT 1 FROM athlete_clubs        WHERE id_athlete = a.id_athlete)",
        'sans_records'      => "SELECT COUNT(*) c FROM athletes a WHERE NOT EXISTS (SELECT 1 FROM athlete_records      WHERE id_athlete = a.id_athlete)",
        'sans_progressions' => "SELECT COUNT(*) c FROM athletes a WHERE NOT EXISTS (SELECT 1 FROM athlete_progressions WHERE id_athlete = a.id_athlete)",
        'sans_resultats'    => "SELECT COUNT(*) c FROM athletes a WHERE NOT EXISTS (SELECT 1 FROM athlete_resultats    WHERE id_athlete = a.id_athlete)",
        'sans_medailles'    => "SELECT COUNT(*) c FROM athletes a WHERE NOT EXISTS (SELECT 1 FROM athlete_medailles    WHERE id_athlete = a.id_athlete)",
        'sans_podiums'      => "SELECT COUNT(*) c FROM athletes a WHERE NOT EXISTS (SELECT 1 FROM athlete_podiums      WHERE id_athlete = a.id_athlete)",
        'sans_selections'   => "SELECT COUNT(*) c FROM athletes a WHERE NOT EXISTS (SELECT 1 FROM athlete_selections   WHERE id_athlete = a.id_athlete)",
        'sans_niveaux'      => "SELECT COUNT(*) c FROM athletes a WHERE NOT EXISTS (SELECT 1 FROM athlete_niveaux      WHERE id_athlete = a.id_athlete)",
    ];
    $stats = [];
    foreach ($enfants as $key => $sql) {
        $r = $conn->query($sql);
        $stats[$key] = $r ? (int)$r->fetch_assoc()['c'] : 0;
    }

    // Top 200 athletes les plus incomplets (score = nb de categories manquantes)
    // Un athlete avec 5+ manques est tres probablement un fantome (page athle.fr vide).
    $sqlTop = "
        SELECT a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete,
               a.sexe_athlete, a.categorie_athlete, a.nationalite_athlete,
               (NOT EXISTS(SELECT 1 FROM athlete_clubs        WHERE id_athlete = a.id_athlete)) +
               (NOT EXISTS(SELECT 1 FROM athlete_records      WHERE id_athlete = a.id_athlete)) +
               (NOT EXISTS(SELECT 1 FROM athlete_progressions WHERE id_athlete = a.id_athlete)) +
               (NOT EXISTS(SELECT 1 FROM athlete_resultats    WHERE id_athlete = a.id_athlete)) +
               (NOT EXISTS(SELECT 1 FROM athlete_niveaux      WHERE id_athlete = a.id_athlete)) +
               (a.nom_complet_athlete = '' OR a.nom_complet_athlete IS NULL) +
               (a.sexe_athlete = '' OR a.sexe_athlete IS NULL)
               AS score_manques
        FROM athletes a
        ORDER BY score_manques DESC, a.id_athlete DESC
        LIMIT 200
    ";
    $rTop = $conn->query($sqlTop);
    $top  = [];
    if ($rTop) while ($row = $rTop->fetch_assoc()) {
        // Pour chaque athlete du top, recharge les flags manquants en detail
        $idA = (int)$row['id_athlete'];
        $rDet = $conn->query("
            SELECT
              EXISTS(SELECT 1 FROM athlete_clubs        WHERE id_athlete = $idA) AS has_clubs,
              EXISTS(SELECT 1 FROM athlete_records      WHERE id_athlete = $idA) AS has_records,
              EXISTS(SELECT 1 FROM athlete_progressions WHERE id_athlete = $idA) AS has_progressions,
              EXISTS(SELECT 1 FROM athlete_resultats    WHERE id_athlete = $idA) AS has_resultats,
              EXISTS(SELECT 1 FROM athlete_medailles    WHERE id_athlete = $idA) AS has_medailles,
              EXISTS(SELECT 1 FROM athlete_podiums      WHERE id_athlete = $idA) AS has_podiums,
              EXISTS(SELECT 1 FROM athlete_selections   WHERE id_athlete = $idA) AS has_selections,
              EXISTS(SELECT 1 FROM athlete_niveaux      WHERE id_athlete = $idA) AS has_niveaux
        ");
        $det = $rDet ? $rDet->fetch_assoc() : [];
        $row['details'] = $det;
        $top[] = $row;
    }

    $duree_s = round(microtime(true) - $t0, 1);

    $result = [
        'computed_at' => date('Y-m-d H:i:s'),
        'duree_s'     => $duree_s,
        'total'       => $total,
        'identite'    => array_map('intval', $identite),
        'enfants'     => $stats,
        'top'         => $top,
    ];
    @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));
    return $result;
}

// API JSON pour fetch externe (par exemple par remote_check)
if ($action === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    if ($force || !file_exists($cacheFile)) {
        echo json_encode(runAudit($conn, $cacheFile));
    } else {
        echo file_get_contents($cacheFile);
    }
    exit;
}

// Recalcul force via formulaire
if ($action === 'refresh') {
    runAudit($conn, $cacheFile);
    header('Location: audit_athletes.php?bk_key=' . urlencode($key));
    exit;
}

// Lecture du cache ou calcul initial
$data = null;
$cacheAge = -1;
if (file_exists($cacheFile)) {
    $data = json_decode(file_get_contents($cacheFile), true);
    $cacheAge = time() - filemtime($cacheFile);
}
// Si pas de cache OU age > TTL → on note qu'il faut recalculer
$cacheStale = ($cacheAge < 0) || ($cacheAge >= $cacheTtl);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Audit integrite athletes</title>
<style>
* { box-sizing: border-box; }
body { font-family: 'Inter', -apple-system, sans-serif; background: #0a0e14; color: #d9e1ec; margin: 0; padding: 24px; max-width: 1300px; margin-left: auto; margin-right: auto; }
h1 { color: #a78bfa; margin: 0 0 4px; }
.sub { color: #7a869a; font-size: 13px; margin-bottom: 22px; }
.btn { background: #6366f1; color: #fff; border: none; padding: 9px 18px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; text-decoration: none; display: inline-block; transition: all 0.15s; }
.btn:hover { transform: translateY(-1px); filter: brightness(1.1); }
.btn-back { background: #374151; }
.btn-warn { background: #d97706; }
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
.card { background: #11161f; border: 1px solid #232b3a; border-radius: 10px; padding: 14px 16px; }
.card .lbl { color: #525d72; font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; }
.card .val { font-size: 22px; font-weight: 700; color: #fff; }
.card .pct { font-size: 11px; color: #7a869a; margin-top: 4px; }
.card.warn .val { color: #f59e0b; }
.card.danger .val { color: #ef4444; }
.card.ok .val { color: #10b981; }
.section-h { display: flex; justify-content: space-between; align-items: center; margin: 28px 0 10px; }
.section-h h2 { color: #fff; font-size: 16px; margin: 0; }
.bar { height: 8px; background: #1a2230; border-radius: 4px; overflow: hidden; margin-top: 6px; }
.bar-inner { height: 100%; transition: width 0.4s; }
table { width: 100%; border-collapse: collapse; font-size: 12.5px; background: #11161f; border: 1px solid #232b3a; border-radius: 10px; overflow: hidden; }
th, td { padding: 8px 10px; border-bottom: 1px solid #1c2330; text-align: left; }
th { color: #7a869a; font-size: 10px; text-transform: uppercase; letter-spacing: 0.6px; background: #161c28; }
tr:hover td { background: #161c28; }
.flag { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; margin-right: 2px; }
.flag-ok { background: rgba(16,185,129,0.15); color: #6ee7b7; }
.flag-no { background: rgba(239,68,68,0.15); color: #fca5a5; }
.athlete-link { color: #a5b4fc; text-decoration: none; }
.athlete-link:hover { text-decoration: underline; color: #fff; }
.score { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
.score-0 { background: rgba(16,185,129,0.2); color: #10b981; }
.score-1 { background: rgba(251,191,36,0.15); color: #fbbf24; }
.score-2, .score-3 { background: rgba(245,158,11,0.18); color: #f59e0b; }
.score-4, .score-5 { background: rgba(239,68,68,0.18); color: #ef4444; }
.score-6, .score-7 { background: rgba(239,68,68,0.3); color: #fca5a5; font-weight: 800; }
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
.alert-warn { background: rgba(251,191,36,0.08); border-left: 3px solid #fbbf24; color: #fde68a; }
.alert-ok { background: rgba(16,185,129,0.08); border-left: 3px solid #10b981; color: #6ee7b7; }
code { background: #1a2230; color: #a5b4fc; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-family: 'JetBrains Mono', Consolas, monospace; }
</style>
</head>
<body>

<h1>Audit integrite athletes</h1>
<div class="sub">
    Detecte les athletes avec donnees partielles ou manquantes.
    <?php if ($data): ?>
        Recalcule a <code><?= htmlspecialchars($data['computed_at']) ?></code> en <?= $data['duree_s'] ?>s.
        <?php if ($cacheAge >= 0): ?>
            Cache age : <?= $cacheAge < 60 ? $cacheAge.'s' : round($cacheAge/60).'min' ?>.
        <?php endif; ?>
    <?php endif; ?>
</div>

<div style="display:flex;gap:8px;margin-bottom:20px;">
    <a href="../scraping_v2/par_annee.php" class="btn btn-back">&larr; Par annee</a>
    <a href="panel.php" class="btn btn-back">Panel admin</a>
    <a href="?bk_key=<?= urlencode($key) ?>&action=refresh" class="btn btn-warn"
       onclick="return confirm('Recalcul complet (peut prendre 30-60s) ?')">
        Recalculer l'audit
    </a>
</div>

<?php if (!$data): ?>
    <div class="alert alert-warn">
        Aucun audit en cache. <a href="?bk_key=<?= urlencode($key) ?>&action=refresh" style="color:#fff;font-weight:700;">Lancer un premier audit</a> (30-60s).
    </div>
<?php else:
    $total       = $data['total'];
    $identite    = $data['identite'];
    $enfants     = $data['enfants'];
    $top         = $data['top'];
    function pct($n, $total) { return $total > 0 ? round($n / $total * 100, 2) : 0; }
?>

<?php if ($cacheStale): ?>
    <div class="alert alert-warn">
        Cache > 1h. Les chiffres peuvent etre obsoletes —
        <a href="?bk_key=<?= urlencode($key) ?>&action=refresh" style="color:#fff;font-weight:700;">recalcule</a>
        pour des donnees fraiches.
    </div>
<?php endif; ?>

<!-- TOTAL -->
<div class="cards">
    <div class="card ok">
        <div class="lbl">Total athletes</div>
        <div class="val"><?= number_format($total, 0, ',', ' ') ?></div>
        <div class="pct">100%</div>
    </div>

    <!-- IDENTITE -->
    <div class="card <?= $identite['sans_nom_complet'] > 0 ? 'danger' : 'ok' ?>">
        <div class="lbl">Sans nom complet</div>
        <div class="val"><?= number_format($identite['sans_nom_complet'], 0, ',', ' ') ?></div>
        <div class="pct"><?= pct($identite['sans_nom_complet'], $total) ?>%</div>
        <div class="bar"><div class="bar-inner" style="width:<?= pct($identite['sans_nom_complet'], $total) ?>%;background:#ef4444;"></div></div>
    </div>
    <div class="card <?= $identite['sans_sexe'] > 0 ? 'warn' : 'ok' ?>">
        <div class="lbl">Sans sexe</div>
        <div class="val"><?= number_format($identite['sans_sexe'], 0, ',', ' ') ?></div>
        <div class="pct"><?= pct($identite['sans_sexe'], $total) ?>%</div>
        <div class="bar"><div class="bar-inner" style="width:<?= pct($identite['sans_sexe'], $total) ?>%;background:#f59e0b;"></div></div>
    </div>
    <div class="card <?= $identite['sans_categorie'] > $total * 0.1 ? 'warn' : 'ok' ?>">
        <div class="lbl">Sans categorie</div>
        <div class="val"><?= number_format($identite['sans_categorie'], 0, ',', ' ') ?></div>
        <div class="pct"><?= pct($identite['sans_categorie'], $total) ?>%</div>
        <div class="bar"><div class="bar-inner" style="width:<?= pct($identite['sans_categorie'], $total) ?>%;background:#f59e0b;"></div></div>
    </div>
    <div class="card">
        <div class="lbl">Sans nationalite</div>
        <div class="val"><?= number_format($identite['sans_nationalite'], 0, ',', ' ') ?></div>
        <div class="pct"><?= pct($identite['sans_nationalite'], $total) ?>%</div>
    </div>
    <div class="card">
        <div class="lbl">Sans licence</div>
        <div class="val"><?= number_format($identite['sans_licence'], 0, ',', ' ') ?></div>
        <div class="pct"><?= pct($identite['sans_licence'], $total) ?>%</div>
    </div>
    <div class="card">
        <div class="lbl">Sans annee naissance</div>
        <div class="val"><?= number_format($identite['sans_annee_naissance'], 0, ',', ' ') ?></div>
        <div class="pct"><?= pct($identite['sans_annee_naissance'], $total) ?>%</div>
    </div>
</div>

<div class="section-h">
    <h2>Donnees enfants manquantes</h2>
    <div style="color:#7a869a;font-size:11px;">% d'athletes sans aucune entree dans la table</div>
</div>
<div class="cards">
    <?php
    $cards = [
        'sans_clubs'        => ['Clubs',        '#fbbf24'],
        'sans_records'      => ['Records',      '#ef4444'],
        'sans_progressions' => ['Progressions', '#ef4444'],
        'sans_resultats'    => ['Resultats',    '#ef4444'],
        'sans_niveaux'      => ['Niveaux',      '#f59e0b'],
        'sans_medailles'    => ['Medailles',    '#7a869a'],
        'sans_podiums'      => ['Podiums',      '#7a869a'],
        'sans_selections'   => ['Selections',   '#7a869a'],
    ];
    foreach ($cards as $key => [$label, $color]):
        $n = $enfants[$key];
        $p = pct($n, $total);
        $cls = $p > 50 ? 'warn' : ($p > 20 ? '' : 'ok');
    ?>
    <div class="card <?= $cls ?>">
        <div class="lbl">Sans <?= $label ?></div>
        <div class="val"><?= number_format($n, 0, ',', ' ') ?></div>
        <div class="pct"><?= $p ?>%</div>
        <div class="bar"><div class="bar-inner" style="width:<?= $p ?>%;background:<?= $color ?>;"></div></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="section-h">
    <h2>Top 200 athletes les plus incomplets</h2>
    <div style="color:#7a869a;font-size:11px;">Score = nb de categories manquantes (sur 7)</div>
</div>

<table>
    <thead>
        <tr>
            <th>Score</th>
            <th>ID interne</th>
            <th>ID athle.fr</th>
            <th>Nom</th>
            <th>Sexe</th>
            <th>Cat</th>
            <th>Nat</th>
            <th>Clubs</th>
            <th>Records</th>
            <th>Progressions</th>
            <th>Resultats</th>
            <th>Medailles</th>
            <th>Podiums</th>
            <th>Selections</th>
            <th>Niveaux</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($top as $row):
        $d = $row['details'] ?? [];
        $score = (int)$row['score_manques'];
    ?>
        <tr>
            <td><span class="score score-<?= min($score, 7) ?>"><?= $score ?>/7</span></td>
            <td><?= $row['id_athlete'] ?></td>
            <td><a href="https://athle.fr/athletes/<?= (int)$row['athlete_id_externe'] ?>/bilans" target="_blank" rel="noopener" class="athlete-link"><?= (int)$row['athlete_id_externe'] ?></a></td>
            <td><?= $row['nom_complet_athlete'] !== '' ? htmlspecialchars($row['nom_complet_athlete']) : '<span style="color:#ef4444;">(vide)</span>' ?></td>
            <td><?= $row['sexe_athlete'] !== '' ? htmlspecialchars($row['sexe_athlete']) : '<span style="color:#ef4444;">--</span>' ?></td>
            <td><?= $row['categorie_athlete'] !== '' ? htmlspecialchars($row['categorie_athlete']) : '<span style="color:#ef4444;">--</span>' ?></td>
            <td><?= $row['nationalite_athlete'] !== '' ? htmlspecialchars($row['nationalite_athlete']) : '<span style="color:#ef4444;">--</span>' ?></td>
            <td><span class="flag <?= $d['has_clubs']        ? 'flag-ok' : 'flag-no' ?>"><?= $d['has_clubs']        ? 'OK' : 'NO' ?></span></td>
            <td><span class="flag <?= $d['has_records']      ? 'flag-ok' : 'flag-no' ?>"><?= $d['has_records']      ? 'OK' : 'NO' ?></span></td>
            <td><span class="flag <?= $d['has_progressions'] ? 'flag-ok' : 'flag-no' ?>"><?= $d['has_progressions'] ? 'OK' : 'NO' ?></span></td>
            <td><span class="flag <?= $d['has_resultats']    ? 'flag-ok' : 'flag-no' ?>"><?= $d['has_resultats']    ? 'OK' : 'NO' ?></span></td>
            <td><span class="flag <?= $d['has_medailles']    ? 'flag-ok' : 'flag-no' ?>"><?= $d['has_medailles']    ? 'OK' : 'NO' ?></span></td>
            <td><span class="flag <?= $d['has_podiums']      ? 'flag-ok' : 'flag-no' ?>"><?= $d['has_podiums']      ? 'OK' : 'NO' ?></span></td>
            <td><span class="flag <?= $d['has_selections']   ? 'flag-ok' : 'flag-no' ?>"><?= $d['has_selections']   ? 'OK' : 'NO' ?></span></td>
            <td><span class="flag <?= $d['has_niveaux']      ? 'flag-ok' : 'flag-no' ?>"><?= $d['has_niveaux']      ? 'OK' : 'NO' ?></span></td>
            <td>
                <a href="remote_check.php?bk_key=<?= urlencode($key) ?>&action=test_scrape&id=<?= (int)$row['athlete_id_externe'] ?>&force"
                   target="_blank" rel="noopener"
                   style="font-size:11px;color:#a5b4fc;text-decoration:none;border:1px solid #232b3a;padding:2px 8px;border-radius:4px;"
                   title="Re-scraper cet athlete via remote_check (force=1)">Re-scrape</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div style="margin-top:24px;padding:14px 18px;background:#11161f;border:1px solid #232b3a;border-radius:10px;font-size:12px;color:#7a869a;">
    <b style="color:#fff;">Comment lire :</b><br>
    &bull; <b>Score 0/7</b> = athlete complet (toutes categories renseignees). <br>
    &bull; <b>Score 1-2/7</b> = quelques donnees manquantes mais normales (pas tous les athletes ont medailles/podiums/selections).<br>
    &bull; <b>Score 5+/7</b> = athlete fantome ou erreur scraping. Page athle.fr probablement vide ou timeout. Lance "Re-scrape" pour reparer.
</div>

<?php endif; ?>

</body>
</html>
