<?php
/**
 * test_page.php — Interface standalone pour tester PageAnalyzer.php
 *
 * Saisis une URL bases.athle.fr/...liste.aspx, le script telecharge et parse.
 * Affiche pagination, total resultats, et la liste complete des athletes
 * avec leurs colonnes (rang, perf, nom, club, ligue, dept, cat/an, date, lieu).
 *
 * Pas de BDD, pas d'insertion : test 100% lecture.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/_guard.php';

require_once __DIR__ . '/lib/PageAnalyzer.php';

$urlDefaut = 'https://www.athle.fr/bases/liste.aspx?frmpostback=true&frmbase=bilans&frmmode=1&frmespace=0&frmannee=2026&frmcategorie=&frmsexe=F&frmepreuve=110&frmvent=&frmligue=&frmdepartement=&frmclub=&frmnationalite=&frmamini=&frmamaxi=&frmplaces=&frmposition=1';

$url = $_GET['url'] ?? '';
$resultat = null;
if ($url !== '') {
    $pa = new PageAnalyzer();
    $resultat = $pa->analyze($url);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Scraping v2 — Test PageAnalyzer</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #0d1117; color: #c9d1d9; margin: 0; padding: 24px; line-height: 1.5; }
        h1 { color: #a78bfa; font-size: 22px; margin: 0 0 4px; }
        h2 { color: #60a5fa; font-size: 15px; margin: 22px 0 8px; padding-bottom: 6px; border-bottom: 1px solid #1f2937; }
        .sub { color: #8b949e; font-size: 13px; margin-bottom: 22px; }
        .ctrl { background: #161b22; border: 1px solid #1f2937; border-radius: 8px; padding: 14px; margin-bottom: 16px; }
        .ctrl label { color: #8b949e; font-size: 12px; display: block; margin-bottom: 4px; }
        .ctrl input[type=text] { width: 100%; background: #0d1117; color: #fff; border: 1px solid #30363d; padding: 8px 10px; border-radius: 4px; font-family: monospace; font-size: 12px; }
        .ctrl button { background: #6366f1; color: #fff; border: none; padding: 8px 18px; border-radius: 4px; cursor: pointer; font-weight: 600; margin-top: 10px; }
        .ctrl button:hover { background: #818cf8; }
        .card { background: #161b22; border: 1px solid #1f2937; border-radius: 8px; padding: 14px; margin-bottom: 12px; }
        .kv { display: grid; grid-template-columns: 200px 1fr; gap: 6px 12px; font-size: 13px; }
        .kv .k { color: #8b949e; }
        .kv .v { color: #fff; }
        .kv .v.ok { color: #34d399; }
        .kv .v.bad { color: #f87171; }
        .kv .v.code { font-family: monospace; color: #fbbf24; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 8px; }
        th, td { text-align: left; padding: 5px 8px; border-bottom: 1px solid #1f2937; vertical-align: top; }
        th { color: #8b949e; font-weight: 500; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; background: #0a0e15; position: sticky; top: 0; }
        tr:hover { background: #1c2128; }
        td.id { color: #fbbf24; font-family: monospace; }
        td.nom { color: #fff; font-weight: 600; }
        td.perf { color: #34d399; font-family: monospace; }
        a { color: #a78bfa; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .ok-box { background: #022c22; border-left: 3px solid #34d399; padding: 10px; border-radius: 4px; margin: 8px 0; }
        .bad-box { background: #450a0a; border-left: 3px solid #f87171; padding: 10px; border-radius: 4px; margin: 8px 0; }
        .examples { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
        .examples a { background: #0d1117; border: 1px solid #30363d; padding: 4px 10px; border-radius: 4px; font-size: 11px; color: #93c5fd; }
        .examples a:hover { border-color: #60a5fa; text-decoration: none; }
        .small { font-size: 11px; color: #6b7280; }
    </style>
</head>
<body>

<h1>Scraping v2 — Test PageAnalyzer</h1>
<div class="sub">Telecharge une page de classement bases.athle.fr et affiche les athletes extraits.</div>

<form class="ctrl" method="GET">
    <label>URL bases.athle.fr/liste.aspx :</label>
    <input type="text" name="url" value="<?= htmlspecialchars($url ?: $urlDefaut) ?>" placeholder="https://www.athle.fr/bases/liste.aspx?...">
    <button type="submit">Analyser la page</button>

    <div class="examples">
        <span class="small" style="align-self:center;">Exemples rapides :</span>
        <a href="?url=<?= urlencode('https://www.athle.fr/bases/liste.aspx?frmpostback=true&frmbase=bilans&frmmode=1&frmespace=0&frmannee=2026&frmsexe=F&frmepreuve=110&frmposition=1') ?>">100m F 2026</a>
        <a href="?url=<?= urlencode('https://www.athle.fr/bases/liste.aspx?frmpostback=true&frmbase=bilans&frmmode=1&frmespace=0&frmannee=2026&frmsexe=M&frmepreuve=110&frmposition=1') ?>">100m H 2026</a>
        <a href="?url=<?= urlencode('https://www.athle.fr/bases/liste.aspx?frmpostback=true&frmbase=bilans&frmmode=1&frmespace=0&frmannee=2025&frmsexe=F&frmepreuve=208&frmposition=1') ?>">800m F 2025</a>
    </div>
</form>

<?php if ($resultat): ?>

    <h2>1. Resultat du fetch</h2>
    <div class="card">
        <div class="kv">
            <div class="k">URL</div>
            <div class="v code" style="word-break:break-all;"><?= htmlspecialchars($resultat['url']) ?></div>

            <div class="k">Status</div>
            <div class="v <?= $resultat['success'] ? 'ok' : 'bad' ?>">
                <?= $resultat['success'] ? 'OK' : 'ECHEC' ?>
                (HTTP <?= $resultat['http_code'] ?>)
            </div>

            <div class="k">Duree fetch</div>
            <div class="v"><?= $resultat['duree_ms'] ?> ms</div>

            <div class="k">Taille HTML</div>
            <div class="v"><?= number_format($resultat['taille_html'], 0, ',', ' ') ?> octets</div>

            <?php if (!empty($resultat['erreur'])): ?>
                <div class="k">Erreur curl</div>
                <div class="v bad"><?= htmlspecialchars($resultat['erreur']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($resultat['success']): ?>

        <h2>2. Pagination et compteur global</h2>
        <div class="card">
            <div class="kv">
                <div class="k">Page actuelle</div>
                <div class="v"><?= $resultat['pagination']['page_actuelle'] ?? '<span class="small">non detectee</span>' ?></div>

                <div class="k">Total pages</div>
                <div class="v <?= $resultat['pagination']['total_pages'] ? 'ok' : '' ?>">
                    <?= $resultat['pagination']['total_pages'] ?? '<span class="small">non detectee</span>' ?>
                </div>

                <div class="k">Total resultats (compteur global)</div>
                <div class="v <?= $resultat['total_resultats'] ? 'ok' : '' ?>">
                    <?= $resultat['total_resultats'] !== null ? number_format($resultat['total_resultats'], 0, ',', ' ') : '<span class="small">non detecte</span>' ?>
                </div>

                <div class="k">Athletes sur cette page</div>
                <div class="v ok"><?= count($resultat['athletes']) ?></div>
            </div>
        </div>

        <h2>3. Athletes extraits (<?= count($resultat['athletes']) ?>)</h2>
        <?php if (empty($resultat['athletes'])): ?>
            <div class="bad-box">Aucun athlete trouve. Le pattern HTML a peut-etre change, ou la page est vide.</div>
        <?php else: ?>
            <div class="card" style="overflow:auto;max-height:600px;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>Rang</th>
                            <th>Perf</th>
                            <th>Nom</th>
                            <th>Club</th>
                            <th>Ligue</th>
                            <th>Dept</th>
                            <th>Cat/An</th>
                            <th>Date</th>
                            <th>Lieu</th>
                            <th>Lien</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultat['athletes'] as $i => $a): ?>
                            <tr>
                                <td class="small"><?= $i + 1 ?></td>
                                <td class="id"><?= $a['id'] ?></td>
                                <td><?= htmlspecialchars($a['rang']) ?></td>
                                <td class="perf"><?= htmlspecialchars($a['perf']) ?></td>
                                <td class="nom"><?= htmlspecialchars($a['nom']) ?></td>
                                <td><?= htmlspecialchars($a['club']) ?></td>
                                <td><?= htmlspecialchars($a['ligue']) ?></td>
                                <td><?= htmlspecialchars($a['dept']) ?></td>
                                <td><?= htmlspecialchars($a['cat_an']) ?></td>
                                <td><?= htmlspecialchars($a['date']) ?></td>
                                <td><?= htmlspecialchars($a['lieu']) ?></td>
                                <td><a href="https://athle.fr<?= htmlspecialchars($a['url_fiche']) ?>" target="_blank">fiche &rarr;</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h2>4. Cellules brutes (debug — 1ere ligne)</h2>
            <div class="card">
                <div class="small" style="margin-bottom:6px;">Si le mapping rang/perf/nom est faux, regarde l'index reel des cellules ici :</div>
                <table>
                    <thead><tr><th>idx</th><th>contenu</th></tr></thead>
                    <tbody>
                        <?php foreach (($resultat['athletes'][0]['cells'] ?? []) as $idx => $c): ?>
                            <tr>
                                <td class="small">[<?= $idx ?>]</td>
                                <td><?= htmlspecialchars($c) ?: '<span class="small">(vide)</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    <?php endif; ?>

<?php else: ?>

    <div class="ok-box">
        <strong>Pret.</strong> Saisis une URL ou clique un exemple, puis "Analyser la page".
    </div>

<?php endif; ?>

<div style="margin-top:30px;color:#6b7280;font-size:11px;text-align:center;">
    PageAnalyzer.php &mdash; test standalone, aucune ecriture en BDD.
</div>

</body>
</html>
