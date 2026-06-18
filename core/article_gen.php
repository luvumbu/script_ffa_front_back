<?php
/**
 * core/article_gen.php — Générateur d'articles « Fil BOKONZI »
 *
 * Transforme les données (club × épreuve, …) en article éditorial auto-rédigé,
 * prêt à être stocké dans la table `articles` puis servi dans le feed à scroll
 * infini personnalisé (api/articles.php).
 *
 * Chaque article est TAGGÉ (nom de club, ville/département, épreuve, discipline)
 * → c'est ce qui permet au feed de matcher « la personne cherche Bordeaux »
 *   avec « Le 100m au Bordeaux Athlé » via l'index FULLTEXT.
 */

require_once __DIR__ . '/db.php';

/**
 * Crée la table `articles` si elle n'existe pas (auto-installation).
 * Appelée par les générateurs + le feed → un simple déploiement des fichiers
 * suffit, aucun script de setup à lancer à la main en prod.
 * Idempotent et mis en cache statique (1 seul CREATE par requête).
 */
function bkEnsureArticlesTable($conn) {
    static $done = false;
    if ($done) return true;
    $done = true;
    return (bool)$conn->query(
        "CREATE TABLE IF NOT EXISTS articles (
            id_article    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type          VARCHAR(32)  NOT NULL,
            title         VARCHAR(255) NOT NULL,
            slug          VARCHAR(255) NOT NULL,
            excerpt       TEXT,
            body          MEDIUMTEXT,
            ref_key       VARCHAR(191) NOT NULL,
            tags          VARCHAR(512) NOT NULL DEFAULT '',
            cover         JSON,
            entity_club   INT NULL,
            entity_epreuve INT NULL,
            entity_athlete INT NULL,
            entity_ville  INT NULL,
            views         INT UNSIGNED NOT NULL DEFAULT 0,
            status        VARCHAR(16)  NOT NULL DEFAULT 'published',
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_slug (slug),
            UNIQUE KEY uk_ref (ref_key),
            INDEX idx_type (type),
            INDEX idx_status_created (status, created_at),
            INDEX idx_views (views),
            FULLTEXT KEY ft_relevance (title, excerpt, tags)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/** Slug URL propre depuis un texte (accents retirés, minuscules, tirets). */
function bkArtSlug($s) {
    $s = (string)$s;
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false) $s = $t;
    }
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/** L'épreuve se mesure-t-elle en distance (plus grand = meilleur) ? */
function bkArtIsDistance($epreuve) {
    return (bool)preg_match('/(longueur|hauteur|perche|triple|poids|disque|javelot|marteau|pentabond)/i', $epreuve);
}

/** Mot-clé discipline depuis le nom d'épreuve (pour les tags). */
function bkArtDiscipline($epreuve) {
    $e = mb_strtolower($epreuve);
    if (preg_match('/(poids|disque|javelot|marteau)/', $e))            return 'lancer';
    if (preg_match('/(longueur|hauteur|perche|triple|pentabond)/', $e))return 'saut';
    if (preg_match('/haies?/', $e))                                    return 'haies';
    if (preg_match('/marche/', $e))                                    return 'marche';
    if (preg_match('/\b(\d+)\s*m/', $e, $m)) {
        $d = (int)$m[1];
        if ($d <= 400)  return 'sprint';
        if ($d <= 1500) return 'demi-fond';
        return 'fond';
    }
    if (preg_match('/(marathon|10\s?km|semi|cross)/', $e))             return 'fond';
    return 'athletisme';
}

/**
 * Génère l'article « Le {épreuve} au {club} ».
 * @return array|null  {type,title,slug,excerpt,body,ref_key,tags,cover,entity_*} ou null si pas assez de données
 */
function bkGenClubEpreuveArticle($conn, $clubId, $epreuveId) {
    $clubId    = (int)$clubId;
    $epreuveId = (int)$epreuveId;

    // Club + épreuve
    $r = $conn->query("SELECT nom_club, departement_club, region_club FROM clubs WHERE id_club=$clubId");
    $club = $r ? $r->fetch_assoc() : null;
    $r = $conn->query("SELECT nom_epreuve FROM epreuves WHERE id_epreuve=$epreuveId");
    $ep = $r ? $r->fetch_assoc() : null;
    if (!$club || !$ep) return null;

    $clubNom = rtrim(trim($club['nom_club']), '* '); // le '*' est un artefact (cf. CLAUDE.md)
    $epNom   = trim($ep['nom_epreuve']);
    $isDist  = bkArtIsDistance($epNom);
    $dep     = trim($club['departement_club'] ?? '');
    $region  = trim($club['region_club'] ?? '');

    // Meilleures perfs réalisées sous les couleurs du club, dans cette épreuve
    $sql = "SELECT a.athlete_id_externe ext, a.nom_complet_athlete nom, a.sexe_athlete sexe,
                   a.categorie_athlete cat, r.performance_record perf, r.performance_brut_record txt,
                   YEAR(r.date_record) annee
            FROM athlete_records r
            JOIN athletes a ON a.id_athlete = r.id_athlete
            WHERE r.id_epreuve = $epreuveId AND r.id_club = $clubId
              AND r.performance_record > 0 AND a.visible = 1";
    $res = $conn->query($sql);
    if (!$res) return null;

    // Dédup par athlète (on garde sa meilleure perf), puis tri
    $best = [];
    $yMin = 9999; $yMax = 0;
    while ($row = $res->fetch_assoc()) {
        $ext = $row['ext'];
        $p   = (int)$row['perf'];
        if (!isset($best[$ext]) || ($isDist ? $p > (int)$best[$ext]['perf'] : $p < (int)$best[$ext]['perf'])) {
            $best[$ext] = $row;
        }
        $an = (int)$row['annee'];
        if ($an > 0) { $yMin = min($yMin, $an); $yMax = max($yMax, $an); }
    }
    if (count($best) < 3) return null; // pas assez de matière pour un article

    $list = array_values($best);
    usort($list, function ($a, $b) use ($isDist) {
        return $isDist ? ((int)$b['perf'] <=> (int)$a['perf']) : ((int)$a['perf'] <=> (int)$b['perf']);
    });

    $nb   = count($list);
    $top  = $list[0];
    $topN = array_slice($list, 0, 10);

    // Comptes utiles
    $nbH = 0; $nbF = 0;
    foreach ($list as $x) { if ($x['sexe'] === 'M') $nbH++; elseif ($x['sexe'] === 'F') $nbF++; }

    // ── Titre / slug / tags ──────────────────────────────────────────────
    $title   = "Le $epNom au $clubNom";
    $slug    = bkArtSlug($epNom . '-' . $clubNom) . '-' . $clubId . $epreuveId;
    $ref_key = "club_epreuve:$clubId:$epreuveId";
    $disc    = bkArtDiscipline($epNom);
    $tags    = trim(mb_strtolower(implode(' ', array_filter([
        $clubNom, $epNom, $disc, $dep, $region, 'club',
    ]))));

    // ── Corps de l'article (HTML) ────────────────────────────────────────
    $loc = $dep !== '' ? " ($dep" . ($region !== '' ? ", $region" : '') . ')' : ($region !== '' ? " ($region)" : '');
    $periode = ($yMax > 0) ? ($yMin === $yMax ? "en $yMax" : "entre $yMin et $yMax") : '';
    $genre = ($nbF > 0 && $nbH > 0) ? "$nbH hommes et $nbF femmes" : ($nbF > 0 ? "$nbF athlètes féminines" : "$nbH athlètes");

    $intro = "<p>Sur le <strong>" . htmlspecialchars($epNom) . "</strong>, le club <strong>"
        . htmlspecialchars($clubNom) . "</strong>$loc compte <strong>$nb athlètes</strong> "
        . "référencés ($genre)" . ($periode ? ", $periode" : '') . ". Tour d'horizon des meilleures performances.</p>";

    $reco = "<p>La meilleure performance revient à <strong>" . htmlspecialchars($top['nom'])
        . "</strong> avec <strong>" . htmlspecialchars($top['txt']) . "</strong>"
        . (!empty($top['annee']) ? " (" . (int)$top['annee'] . ")" : '') . ".</p>";

    $rows = '';
    $rank = 1;
    foreach ($topN as $x) {
        $rows .= '<tr><td>' . $rank . '</td>'
              . '<td><a href="?page=profil&id=' . (int)$x['ext'] . '">' . htmlspecialchars($x['nom']) . '</a></td>'
              . '<td>' . htmlspecialchars($x['cat'] ?: '') . '</td>'
              . '<td>' . htmlspecialchars($x['sexe'] ?: '') . '</td>'
              . '<td><strong>' . htmlspecialchars($x['txt']) . '</strong></td>'
              . '<td>' . (!empty($x['annee']) ? (int)$x['annee'] : '—') . '</td></tr>';
        $rank++;
    }
    $table = '<table class="bk-art-table"><thead><tr><th>#</th><th>Athlète</th><th>Cat.</th><th>Sexe</th><th>Perf.</th><th>Année</th></tr></thead><tbody>'
        . $rows . '</tbody></table>';

    $body = $intro . $reco
        . '<h3>Le top ' . count($topN) . '</h3>' . $table
        . '<p class="bk-art-foot">Données issues de la base BOKONZI — ' . htmlspecialchars($clubNom)
        . '. Classement par meilleure performance ' . ($isDist ? '(distance)' : '(chrono)') . '.</p>';

    $excerpt = "Les $nb meilleurs athlètes du $epNom au $clubNom : "
        . $top['nom'] . " en tête avec " . $top['txt'] . ($periode ? ", $periode" : '') . '.';

    return [
        'type'           => 'club_epreuve',
        'title'          => $title,
        'slug'           => $slug,
        'excerpt'        => $excerpt,
        'body'           => $body,
        'ref_key'        => $ref_key,
        'tags'           => $tags,
        'cover'          => json_encode([
            'nb_athletes' => $nb,
            'best'        => $top['txt'],
            'best_nom'    => $top['nom'],
            'best_ext'    => (int)$top['ext'],
            'epreuve'     => $epNom,
            'club'        => $clubNom,
            'annee_min'   => $yMin <= $yMax ? $yMin : null,
            'annee_max'   => $yMax ?: null,
            'discipline'  => $disc,
        ], JSON_UNESCAPED_UNICODE),
        'entity_club'    => $clubId,
        'entity_epreuve' => $epreuveId,
        'entity_athlete' => null,
        'entity_ville'   => null,
    ];
}

/* ═══════════════════════════════════════════════════════════════════════
 *  ANGLE « Le saviez-vous ? » — faits insolites / records uniques par club
 *  Ex. « X est la seule personne du club à avoir couru le 100m sous 11 s. »
 * ═══════════════════════════════════════════════════════════════════════ */

/**
 * Trouve un palier « rond » que le MEILLEUR a franchi mais PAS le 2e.
 * C'est ce palier qui rend le fait remarquable (« le seul à passer sous la barre »).
 * @param int   $p1    perf du meilleur (centièmes pour le temps, cm pour la distance)
 * @param int   $p2    perf du 2e
 * @param bool  $isDist true = distance (plus grand = mieux), false = temps (plus petit = mieux)
 * @param int[] $steps  granularités candidates, de la plus ronde à la moins ronde
 * @return int|null     le palier (même unité), ou null si aucun palier rond ne les sépare
 */
function bkArtNiceMilestone($p1, $p2, $isDist, $steps) {
    $p1 = (int)$p1; $p2 = (int)$p2;
    if ($isDist) { $lo = $p2; $hi = $p1; }   // distance : p2 < palier < p1
    else         { $lo = $p1; $hi = $p2; }   // temps    : p1 < palier < p2
    if ($hi - $lo < 2) return null;
    foreach ($steps as $step) {
        $m = (intdiv($lo, $step) + 1) * $step; // 1er multiple de step strictement > lo
        if ($m > $lo && $m < $hi) return $m;
    }
    return null;
}

/** Formate des centièmes en chrono lisible (utilisé si palier non rond en secondes). */
function bkArtFmtChrono($cent) {
    $cent = (int)$cent; $c = $cent % 100; $tot = intdiv($cent, 100);
    $s = $tot % 60; $min = intdiv($tot, 60);
    $base = $min > 0 ? sprintf('%d min %02d s', $min, $s) : ($s . ' s');
    if ($c > 0) $base .= sprintf(' %02d', $c);
    return $base;
}

/** Phrase « le saviez-vous » pour un athlète seul à avoir franchi un palier. */
function bkArtUniqueSentence($nom, $sexe, $isDist, $epNom, $disc, $milestone) {
    $seul = ($sexe === 'F') ? 'la seule' : 'le seul';
    $n = '<strong>' . htmlspecialchars($nom) . '</strong>';
    if ($isDist) {
        $v = $milestone / 100; // cm → m
        $val = ($milestone % 100 === 0) ? ($v . ' mètres') : (number_format($v, 2, ',', ' ') . ' m');
        if ($disc === 'lancer')
            return "$n est $seul athlète du club à avoir lancé au-delà des <strong>$val</strong> au "
                 . htmlspecialchars($epNom) . '.';
        return "$n est $seul athlète du club à avoir franchi la barre des <strong>$val</strong> en "
             . htmlspecialchars($epNom) . '.';
    }
    // Temps : palier garanti en secondes entières (cf. steps)
    if ($milestone < 6000)       $val = ($milestone / 100) . ' secondes';
    elseif ($milestone % 6000 === 0) $val = ($milestone / 6000) . ' minutes';
    else                          $val = bkArtFmtChrono($milestone);
    $verbe = ($disc === 'marche') ? 'parcouru' : 'couru';
    return "$n est $seul athlète du club à avoir $verbe le " . htmlspecialchars($epNom)
         . " en <strong>moins de $val</strong>.";
}

/**
 * Génère l'article « Le saviez-vous ? {club} » : une série de faits marquants
 * (records uniques, paliers franchis par un seul athlète, polyvalence).
 * @return array|null  null si pas assez de faits remarquables
 */
function bkGenClubFactsArticle($conn, $clubId) {
    $clubId = (int)$clubId;
    $r = $conn->query("SELECT nom_club, departement_club, region_club FROM clubs WHERE id_club=$clubId");
    $club = $r ? $r->fetch_assoc() : null;
    if (!$club) return null;
    $clubNom = rtrim(trim($club['nom_club']), '* ');
    $dep     = trim($club['departement_club'] ?? '');
    $region  = trim($club['region_club'] ?? '');

    // Tous les records du club, par épreuve
    $sql = "SELECT e.id_epreuve eid, e.nom_epreuve ep,
                   a.athlete_id_externe ext, a.nom_complet_athlete nom, a.sexe_athlete sexe,
                   r.performance_record perf, r.performance_brut_record txt, YEAR(r.date_record) annee
            FROM athlete_records r
            JOIN athletes a  ON a.id_athlete = r.id_athlete
            JOIN epreuves e  ON e.id_epreuve = r.id_epreuve
            WHERE r.id_club = $clubId AND r.performance_record > 0 AND a.visible = 1";
    $res = $conn->query($sql);
    if (!$res) return null;

    // Regroupe par épreuve, en gardant la meilleure perf par athlète
    $byEp = [];               // eid => ['ep'=>nom, 'dist'=>bool, 'disc'=>str, 'ath'=>[ext=>row]]
    $athNames = [];           // ext => nom (pour la polyvalence)
    while ($row = $res->fetch_assoc()) {
        $eid = (int)$row['eid'];
        if (!isset($byEp[$eid])) {
            $byEp[$eid] = [
                'ep'   => $row['ep'],
                'dist' => bkArtIsDistance($row['ep']),
                'disc' => bkArtDiscipline($row['ep']),
                'ath'  => [],
            ];
        }
        $ext = $row['ext'];
        $p   = (int)$row['perf'];
        $cur = $byEp[$eid]['ath'][$ext] ?? null;
        $better = !$cur || ($byEp[$eid]['dist'] ? $p > (int)$cur['perf'] : $p < (int)$cur['perf']);
        if ($better) $byEp[$eid]['ath'][$ext] = $row;
        $athNames[$ext] = $row['nom'];
    }

    // Construit les faits « unique » + compte les records détenus par athlète
    $facts   = [];            // [ ['html'=>..., 'score'=>int, 'ext'=>..] ]
    $heldBy  = [];            // ext => nb d'épreuves où il/elle est n°1
    $bestRec = null;          // record le plus « peuplé » → fait de repli
    foreach ($byEp as $eid => $e) {
        $ath = array_values($e['ath']);
        $n   = count($ath);
        if ($n < 1) continue;
        usort($ath, function ($a, $b) use ($e) {
            return $e['dist'] ? ((int)$b['perf'] <=> (int)$a['perf'])
                              : ((int)$a['perf'] <=> (int)$b['perf']);
        });
        $heldBy[$ath[0]['ext']] = ($heldBy[$ath[0]['ext']] ?? 0) + 1;

        // Record le plus représentatif (épreuve la plus pratiquée du club)
        if (!$bestRec || $n > $bestRec['n']) {
            $bestRec = ['n' => $n, 'row' => $ath[0], 'ep' => $e['ep']];
        }
        if ($n < 2) continue; // pour un fait « le seul », il faut au moins un 2e

        $steps = $e['dist'] ? [1000, 500, 100, 50, 10] : [6000, 3000, 1000, 100];
        $m = bkArtNiceMilestone((int)$ath[0]['perf'], (int)$ath[1]['perf'], $e['dist'], $steps);
        if ($m === null) continue;

        $facts[] = [
            'html'  => bkArtUniqueSentence($ath[0]['nom'], $ath[0]['sexe'], $e['dist'], $e['ep'], $e['disc'], $m),
            'score' => $n,                 // plus il y a d'athlètes, plus « être le seul » impressionne
            'ext'   => $ath[0]['ext'],
        ];
    }

    // Garde les plus impressionnants (max 2 faits par athlète, 6 au total)
    usort($facts, function ($a, $b) { return $b['score'] <=> $a['score']; });
    $picked = []; $perAth = [];
    foreach ($facts as $f) {
        $c = $perAth[$f['ext']] ?? 0;
        if ($c >= 2) continue;
        $perAth[$f['ext']] = $c + 1;
        $picked[] = $f['html'];
        if (count($picked) >= 6) break;
    }

    // Fait de polyvalence (athlète détenant le plus de records du club)
    if ($heldBy) {
        arsort($heldBy);
        $topExt = array_key_first($heldBy);
        $cnt    = $heldBy[$topExt];
        if ($cnt >= 3) {
            $picked[] = 'C\'est <strong>' . htmlspecialchars($athNames[$topExt]) . '</strong> qui détient le plus '
                . "de records au club : <strong>$cnt épreuves</strong> différentes.";
        }
    }

    // Repli pour atteindre le minimum de matière
    if (count($picked) < 2 && $bestRec) {
        $b = $bestRec['row'];
        $picked[] = 'Le record du club au ' . htmlspecialchars($bestRec['ep']) . ' est détenu par <strong>'
            . htmlspecialchars($b['nom']) . '</strong> avec <strong>' . htmlspecialchars($b['txt']) . '</strong>'
            . (!empty($b['annee']) ? ' (' . (int)$b['annee'] . ')' : '') . '.';
    }
    if (count($picked) < 2) return null; // pas assez de faits → on ne publie pas

    // ── Mise en forme de l'article ───────────────────────────────────────
    $loc = $dep !== '' ? " ($dep" . ($region !== '' ? ", $region" : '') . ')' : ($region !== '' ? " ($region)" : '');
    $intro = "<p>Le saviez-vous ? Plongée dans les performances du <strong>" . htmlspecialchars($clubNom)
        . "</strong>$loc. Voici quelques faits marquants tirés de nos données.</p>";

    $factsHtml = '';
    foreach ($picked as $f) {
        $factsHtml .= '<p class="bk-art-fact" style="margin:12px 0;padding:14px 16px;border-left:4px solid #6c5ce7;'
            . 'background:rgba(108,92,231,.08);border-radius:8px;">&#128161; ' . $f . '</p>';
    }
    $foot = '<p class="bk-art-foot">« Le saviez-vous ? » — faits générés à partir des records du '
        . htmlspecialchars($clubNom) . ' dans la base BOKONZI.</p>';
    $body = $intro . $factsHtml . $foot;

    $excerpt = trim(html_entity_decode(strip_tags($picked[0])));
    if (mb_strlen($excerpt) > 200) $excerpt = mb_substr($excerpt, 0, 197) . '…';

    $disc = bkArtDiscipline($bestRec['ep'] ?? '');
    $tags = trim(mb_strtolower(implode(' ', array_filter([
        $clubNom, $dep, $region, 'club', 'saviez-vous', 'insolite', 'records', $disc,
    ]))));

    return [
        'type'           => 'club_facts',
        'title'          => "Le saviez-vous ? $clubNom",
        'slug'           => bkArtSlug("le-saviez-vous-$clubNom") . '-' . $clubId,
        'excerpt'        => $excerpt,
        'body'           => $body,
        'ref_key'        => "club_facts:$clubId",
        'tags'           => $tags,
        'cover'          => json_encode([
            'club'      => $clubNom,
            'nb_faits'  => count($picked),
            'headline'  => $excerpt,
            'badge'     => 'Le saviez-vous ?',
        ], JSON_UNESCAPED_UNICODE),
        'entity_club'    => $clubId,
        'entity_epreuve' => null,
        'entity_athlete' => null,
        'entity_ville'   => null,
    ];
}

/**
 * Liste les épreuves d'un club classées par fréquence (nb d'athlètes ayant un
 * record sous ses couleurs). Sert à générer « l'épreuve au club » sur les
 * disciplines les plus pratiquées du club.
 * @return array  [ ['id'=>int,'nom'=>string,'nb'=>int], ... ]
 */
function bkClubTopEpreuves($conn, $clubId, $limit = 12) {
    $clubId = (int)$clubId; $limit = max(1, min(100, (int)$limit));
    $res = $conn->query(
        "SELECT e.id_epreuve id, e.nom_epreuve nom, COUNT(DISTINCT r.id_athlete) nb
         FROM athlete_records r
         JOIN epreuves e ON e.id_epreuve = r.id_epreuve
         JOIN athletes a ON a.id_athlete = r.id_athlete
         WHERE r.id_club = $clubId AND r.performance_record > 0 AND a.visible = 1
         GROUP BY e.id_epreuve, e.nom_epreuve
         HAVING nb >= 3
         ORDER BY nb DESC, e.nom_epreuve ASC
         LIMIT $limit"
    );
    $out = [];
    if ($res) while ($r = $res->fetch_assoc()) {
        $out[] = ['id' => (int)$r['id'], 'nom' => $r['nom'], 'nb' => (int)$r['nb']];
    }
    return $out;
}

/**
 * Liste (en cache 24h) des clubs éligibles aux articles (>= 60 records),
 * classés par richesse de données. La requête GROUP BY sur toute la table des
 * records est lourde → on la met en cache fichier pour ne pas ralentir les pages.
 * @return int[] ids de clubs
 */
function bkEligibleClubs($conn) {
    $cacheFile = __DIR__ . '/../cache/article_eligible_clubs.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
        $ids = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($ids) && $ids) return array_map('intval', $ids);
    }
    // NB : pas de filtre performance_record (forcerait un full scan de 2,6M lignes,
    // ~55s). Sans filtre, la requête utilise l'index id_club (index-only, ~1s).
    $res = $conn->query(
        "SELECT id_club, COUNT(*) c FROM athlete_records
         WHERE id_club IS NOT NULL
         GROUP BY id_club HAVING c >= 60
         ORDER BY c DESC LIMIT 300"
    );
    if (!$res) return [];
    $ids = [];
    while ($row = $res->fetch_assoc()) $ids[] = (int)$row['id_club'];
    if ($ids) @file_put_contents($cacheFile, json_encode($ids));
    return $ids;
}

/**
 * Choisit le « club de la semaine » de façon déterministe (rotation hebdo).
 * On tourne parmi les clubs ayant assez de matière (>= 60 records), classés
 * par richesse de données. La semaine ISO sert d'index → un club par semaine,
 * stable pendant 7 jours, sans intervention.
 * @return int|null id_club, ou null si aucun club éligible
 */
function bkPickClubOfWeek($conn) {
    $ids = bkEligibleClubs($conn);
    if (!$ids) return null;
    // Index de semaine monotone (année ISO * 53 + n° de semaine)
    $week = ((int)date('o')) * 53 + ((int)date('W'));
    return $ids[$week % count($ids)];
}

/**
 * Sauvegarde (ou met à jour) un article dans la table `articles`.
 * Upsert par ref_key. @return int id_article, ou 0 si échec.
 */
function bkSaveArticle($conn, $art) {
    if (!$art || empty($art['ref_key'])) return 0;
    bkEnsureArticlesTable($conn); // auto-installe la table en prod si absente
    $stmt = $conn->prepare(
        "INSERT INTO articles (type,title,slug,excerpt,body,ref_key,tags,cover,entity_club,entity_epreuve,entity_athlete,entity_ville)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE title=VALUES(title), excerpt=VALUES(excerpt), body=VALUES(body),
            tags=VALUES(tags), cover=VALUES(cover), updated_at=NOW()"
    );
    if (!$stmt) return 0;
    $stmt->bind_param(
        "ssssssssiiii",
        $art['type'], $art['title'], $art['slug'], $art['excerpt'], $art['body'],
        $art['ref_key'], $art['tags'], $art['cover'],
        $art['entity_club'], $art['entity_epreuve'], $art['entity_athlete'], $art['entity_ville']
    );
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) return 0;
    // Récupère l'id (insert ou update)
    $r = $conn->query("SELECT id_article FROM articles WHERE ref_key='" . $conn->real_escape_string($art['ref_key']) . "'");
    return $r ? (int)$r->fetch_assoc()['id_article'] : 0;
}
