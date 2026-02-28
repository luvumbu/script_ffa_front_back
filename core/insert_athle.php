<?php
/**
 * insert_athle.php — Version optimisée (fonctions)
 *
 * Optimisations :
 *   - Cache mémoire des tables de référence (0 SELECT pour les FK connues)
 *   - Connexion directe $conn->query() (pas de reconnexion par requête)
 *   - INSERT batch (1 requête au lieu de N par section)
 *
 * Fonctions exportées :
 *   fk($val)                          → NULL ou 'valeur' pour SQL
 *   loadRefCache($conn)               → charge les 6 tables de référence en mémoire
 *   cachedGetOrInsertId(...)           → lookup mémoire, INSERT si absent
 *   cachedGetCategorieId(...)          → lookup mémoire uniquement
 *   insertAthleteData($scraper,$conn,&$cache) → insère les 9 sections
 */

// =============================================
// Helpers
// =============================================

function fk($val)
{
    return ($val === null || $val === 0 || $val === '') ? "NULL" : "'$val'";
}

/**
 * Charge toutes les tables de référence en mémoire
 * → élimine les SELECT répétitifs pour les FK
 */
function loadRefCache($conn)
{
    $cache = [
        'villes'       => [],
        'clubs'        => [],
        'epreuves'     => [],
        'competitions' => [],
        'categories'   => [],
        'nationalites' => [],
    ];

    $res = $conn->query("SELECT id_ville, nom_ville FROM villes");
    if ($res) while ($row = $res->fetch_assoc()) $cache['villes'][trim($row['nom_ville'])] = (int)$row['id_ville'];

    $res = $conn->query("SELECT id_club, nom_club FROM clubs");
    if ($res) while ($row = $res->fetch_assoc()) $cache['clubs'][trim($row['nom_club'])] = (int)$row['id_club'];

    $res = $conn->query("SELECT id_epreuve, nom_epreuve FROM epreuves");
    if ($res) while ($row = $res->fetch_assoc()) $cache['epreuves'][trim($row['nom_epreuve'])] = (int)$row['id_epreuve'];

    $res = $conn->query("SELECT id_competition, nom_competition FROM competitions");
    if ($res) while ($row = $res->fetch_assoc()) $cache['competitions'][trim($row['nom_competition'])] = (int)$row['id_competition'];

    $res = $conn->query("SELECT id_categorie, code_categorie FROM categories");
    if ($res) while ($row = $res->fetch_assoc()) $cache['categories'][trim($row['code_categorie'])] = (int)$row['id_categorie'];

    $res = $conn->query("SELECT id_nationalite, code_nationalite FROM nationalites");
    if ($res) while ($row = $res->fetch_assoc()) $cache['nationalites'][trim($row['code_nationalite'])] = (int)$row['id_nationalite'];

    return $cache;
}

/**
 * Cherche l'ID dans le cache mémoire, sinon INSERT + mise en cache
 * → remplace getOrInsertId() qui faisait 1-2 requêtes SQL à chaque appel
 */
function cachedGetOrInsertId(&$cache, $conn, $table, $colId, $colNom, $valeur, $extraCols = [])
{
    $valeur = trim($valeur);
    if (empty($valeur)) return null;

    // Lookup mémoire → 0 requête SQL
    if (isset($cache[$table][$valeur])) {
        return $cache[$table][$valeur];
    }

    // Pas en cache → INSERT IGNORE (gère les doublons)
    $valEsc = $conn->real_escape_string($valeur);
    $cols = "`$colNom`";
    $vals = "'$valEsc'";
    foreach ($extraCols as $col => $val) {
        $cols .= ", `$col`";
        $vals .= ", '" . $conn->real_escape_string($val) . "'";
    }

    $conn->query("INSERT IGNORE INTO `$table` ($cols) VALUES ($vals)");
    $id = (int)$conn->insert_id;

    if ($id === 0) {
        // Existait déjà → récupérer l'ID
        $res = $conn->query("SELECT `$colId` FROM `$table` WHERE `$colNom` = '$valEsc' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $id = (int)$res->fetch_assoc()[$colId];
        }
    }

    if ($id > 0) {
        $cache[$table][$valeur] = $id;
        return $id;
    }
    return null;
}

/**
 * Catégorie : lookup mémoire uniquement (table pré-remplie par dbCheck)
 */
function cachedGetCategorieId(&$cache, $code)
{
    if (empty($code)) return null;
    return $cache['categories'][trim($code)] ?? null;
}

// =============================================
// Fonction principale d'insertion
// =============================================

function insertAthleteData($scraper, $conn, &$cache)
{
    // Raccourci pour l'escape
    $esc = function ($v) use ($conn) { return $conn->real_escape_string($v ?? ''); };

    // =============================================
    // 1. IDENTITÉ ATHLÈTE
    // =============================================

    $identite = $scraper->identite;
    $id_ville_naissance = cachedGetOrInsertId($cache, $conn, 'villes', 'id_ville', 'nom_ville', $identite['lieu_naissance']);
    $id_nationalite = cachedGetOrInsertId($cache, $conn, 'nationalites', 'id_nationalite', 'code_nationalite', $identite['nationalite']);

    $athleteIdExt = $conn->real_escape_string($identite['athlete_id']);
    $res = $conn->query("SELECT `id_athlete` FROM `athletes` WHERE `athlete_id_externe` = '$athleteIdExt' LIMIT 1");

    if ($res && $res->num_rows > 0) {
        // UPDATE existant
        $id_athlete = (int)$res->fetch_assoc()['id_athlete'];

        $sql = "UPDATE `athletes` SET
            `nom_1_athlete`           = '" . $esc($identite['nom_1']) . "',
            `nom_2_athlete`           = '" . $esc($identite['nom_2']) . "',
            `nom_3_athlete`           = '" . $esc($identite['nom_3']) . "',
            `nom_4_athlete`           = '" . $esc($identite['nom_4']) . "',
            `nom_complet_athlete`     = '" . $esc($identite['nom_complet']) . "',
            `date_naissance_athlete`  = " . fk($identite['date_naissance']) . ",
            `annee_naissance_athlete` = " . fk($identite['annee_naissance']) . ",
            `id_ville_naissance`      = " . fk($id_ville_naissance) . ",
            `taille_cm_athlete`       = " . fk($identite['taille_cm']) . ",
            `poids_kg_athlete`        = " . fk($identite['poids_kg']) . ",
            `categorie_athlete`       = '" . $esc($identite['categorie']) . "',
            `sexe_athlete`            = '" . $esc($identite['sexe']) . "',
            `nationalite_athlete`     = '" . $esc($identite['nationalite']) . "',
            `id_nationalite`          = " . fk($id_nationalite) . ",
            `licence_athlete`         = '" . $esc($identite['licence']) . "'
        WHERE `id_athlete` = '$id_athlete'";
        $conn->query($sql);

        // Supprimer les données enfants (seront ré-insérées)
        $childTables = [
            'athlete_clubs', 'athlete_medailles', 'athlete_selections',
            'athlete_progressions', 'athlete_records', 'athlete_podiums',
            'athlete_resultats', 'athlete_niveaux'
        ];
        foreach ($childTables as $t) {
            $conn->query("DELETE FROM `$t` WHERE `id_athlete` = '$id_athlete'");
        }
        echo "<p>Athlete mis a jour → id_athlete = $id_athlete</p>";

    } else {
        // INSERT nouveau
        $sql = "INSERT INTO `athletes` (
            `athlete_id_externe`, `nom_1_athlete`, `nom_2_athlete`, `nom_3_athlete`, `nom_4_athlete`,
            `nom_complet_athlete`, `date_naissance_athlete`, `annee_naissance_athlete`,
            `id_ville_naissance`, `taille_cm_athlete`, `poids_kg_athlete`,
            `categorie_athlete`, `sexe_athlete`, `nationalite_athlete`,
            `id_nationalite`, `licence_athlete`
        ) VALUES (
            '$athleteIdExt',
            '" . $esc($identite['nom_1']) . "', '" . $esc($identite['nom_2']) . "',
            '" . $esc($identite['nom_3']) . "', '" . $esc($identite['nom_4']) . "',
            '" . $esc($identite['nom_complet']) . "',
            " . fk($identite['date_naissance']) . ",
            " . fk($identite['annee_naissance']) . ",
            " . fk($id_ville_naissance) . ",
            " . fk($identite['taille_cm']) . ", " . fk($identite['poids_kg']) . ",
            '" . $esc($identite['categorie']) . "', '" . $esc($identite['sexe']) . "',
            '" . $esc($identite['nationalite']) . "',
            " . fk($id_nationalite) . ",
            '" . $esc($identite['licence']) . "'
        )";
        $conn->query($sql);
        $id_athlete = (int)$conn->insert_id;
        echo "<p>Nouvel athlete → id_athlete = $id_athlete</p>";
    }

    // =============================================
    // 2. CLUBS (batch INSERT)
    // =============================================

    if (!empty($scraper->clubs)) {
        $vals = [];
        foreach ($scraper->clubs as $c) {
            $id_club = cachedGetOrInsertId($cache, $conn, 'clubs', 'id_club', 'nom_club', $c['nom_club']);
            $vals[] = "('$id_athlete', " . fk($id_club) . ", '" . ($c['annee_debut'] ?? 0) . "', '" . ($c['annee_fin'] ?? 0) . "')";
        }
        $conn->query("INSERT INTO `athlete_clubs` (`id_athlete`, `id_club`, `annee_debut`, `annee_fin`) VALUES " . implode(",", $vals));
    }
    echo "<p>Clubs : " . count($scraper->clubs) . "</p>";

    // =============================================
    // 3. MÉDAILLES (batch INSERT)
    // =============================================

    if (!empty($scraper->medailles)) {
        $vals = [];
        foreach ($scraper->medailles as $m) {
            $id_ep = cachedGetOrInsertId($cache, $conn, 'epreuves', 'id_epreuve', 'nom_epreuve', $m['epreuve']);
            $id_vi = cachedGetOrInsertId($cache, $conn, 'villes', 'id_ville', 'nom_ville', $m['lieu'], ['pays_ville' => $m['pays']]);
            $id_co = cachedGetOrInsertId($cache, $conn, 'competitions', 'id_competition', 'nom_competition', $m['competition']);
            $vals[] = "('$id_athlete', '" . $esc($m['type']) . "', '" . $esc($m['annee']) . "', " . fk($id_co) . ", " . fk($id_ep) . ", " . fk($id_vi) . ")";
        }
        $conn->query("INSERT INTO `athlete_medailles` (`id_athlete`, `type_medaille`, `annee_medaille`, `id_competition`, `id_epreuve`, `id_ville`) VALUES " . implode(",", $vals));
    }
    echo "<p>Medailles : " . count($scraper->medailles) . "</p>";

    // =============================================
    // 4. SÉLECTIONS (batch INSERT)
    // =============================================

    if (!empty($scraper->selections)) {
        $vals = [];
        foreach ($scraper->selections as $s) {
            $id_ep = cachedGetOrInsertId($cache, $conn, 'epreuves', 'id_epreuve', 'nom_epreuve', $s['epreuve']);
            $id_co = cachedGetOrInsertId($cache, $conn, 'competitions', 'id_competition', 'nom_competition', $s['competition']);
            $vals[] = "('$id_athlete', '" . $esc($s['type']) . "', '" . $esc($s['date']) . "', '"
                . ($s['duree_jours'] ?? 0) . "', '" . ($s['age'] ?? 0) . "', "
                . fk($id_co) . ", " . fk($id_ep) . ", '"
                . ($s['classement'] ?? 0) . "', " . fk($s['performance']) . ", '"
                . $esc($s['performance_brut'] ?? '') . "')";
        }
        $conn->query("INSERT INTO `athlete_selections` (`id_athlete`, `type_selection`, `date_selection`, `duree_jours_selection`, `age_selection`, `id_competition`, `id_epreuve`, `classement_selection`, `performance_selection`, `performance_brut_selection`) VALUES " . implode(",", $vals));
    }
    echo "<p>Selections : " . count($scraper->selections) . "</p>";

    // =============================================
    // 5. PROGRESSIONS (batch INSERT)
    // =============================================

    if (!empty($scraper->progressions)) {
        $vals = [];
        foreach ($scraper->progressions as $p) {
            $id_ep = cachedGetOrInsertId($cache, $conn, 'epreuves', 'id_epreuve', 'nom_epreuve', $p['epreuve']);
            $id_vi = cachedGetOrInsertId($cache, $conn, 'villes', 'id_ville', 'nom_ville', $p['lieu']);
            $id_ca = cachedGetCategorieId($cache, $p['categorie'] ?? null);
            $id_cl = cachedGetOrInsertId($cache, $conn, 'clubs', 'id_club', 'nom_club', $p['club'] ?? '');
            $vals[] = "('$id_athlete', " . fk($id_ep) . ", " . fk($id_ca) . ", " . fk($id_cl)
                . ", '" . $esc($p['annee']) . "', " . fk($p['performance'])
                . ", '" . $esc($p['performance_brut'] ?? '') . "', '" . $esc($p['vent'])
                . "', '" . $esc($p['date']) . "', '" . $esc($p['ligue_dept'] ?? '')
                . "', " . fk($id_vi) . ")";
        }
        $conn->query("INSERT INTO `athlete_progressions` (`id_athlete`, `id_epreuve`, `id_categorie`, `id_club`, `annee_progression`, `performance_progression`, `performance_brut_progression`, `vent_progression`, `date_progression`, `ligue_dept_progression`, `id_ville`) VALUES " . implode(",", $vals));
    }
    echo "<p>Progressions : " . count($scraper->progressions) . "</p>";

    // =============================================
    // 6. RECORDS (batch INSERT)
    // =============================================

    if (!empty($scraper->records)) {
        $vals = [];
        foreach ($scraper->records as $r) {
            $id_ep = cachedGetOrInsertId($cache, $conn, 'epreuves', 'id_epreuve', 'nom_epreuve', $r['epreuve']);
            $id_cl = cachedGetOrInsertId($cache, $conn, 'clubs', 'id_club', 'nom_club', $r['club']);
            $id_vi = cachedGetOrInsertId($cache, $conn, 'villes', 'id_ville', 'nom_ville', $r['lieu']);
            $id_ca = cachedGetCategorieId($cache, $r['categorie'] ?? null);
            $vals[] = "('$id_athlete', " . fk($id_ep) . ", " . fk($id_ca) . ", "
                . fk($r['performance']) . ", '" . $esc($r['performance_brut'] ?? '')
                . "', '" . $esc($r['date']) . "', " . fk($id_cl) . ", '"
                . $esc($r['ligue_dept']) . "', " . fk($id_vi) . ")";
        }
        $conn->query("INSERT INTO `athlete_records` (`id_athlete`, `id_epreuve`, `id_categorie`, `performance_record`, `performance_brut_record`, `date_record`, `id_club`, `ligue_dept_record`, `id_ville`) VALUES " . implode(",", $vals));
    }
    echo "<p>Records : " . count($scraper->records) . "</p>";

    // =============================================
    // 7. PODIUMS (batch INSERT)
    // =============================================

    if (!empty($scraper->podiums)) {
        $vals = [];
        foreach ($scraper->podiums as $p) {
            $id_ep = cachedGetOrInsertId($cache, $conn, 'epreuves', 'id_epreuve', 'nom_epreuve', $p['epreuve']);
            $id_vi = cachedGetOrInsertId($cache, $conn, 'villes', 'id_ville', 'nom_ville', $p['lieu']);
            $vals[] = "('$id_athlete', '" . $esc($p['annee']) . "', '" . $esc($p['niveau_competition'])
                . "', '" . $esc($p['place']) . "', " . fk($p['rang']) . ", " . fk($id_ep) . ", "
                . fk($p['performance']) . ", '" . $esc($p['performance_brut'] ?? '')
                . "', '" . $esc($p['vent'] ?? '') . "', " . fk($p['date']) . ", " . fk($id_vi) . ")";
        }
        $conn->query("INSERT INTO `athlete_podiums` (`id_athlete`, `annee_podium`, `niveau_competition`, `place_podium`, `rang_podium`, `id_epreuve`, `performance_podium`, `performance_brut_podium`, `vent_podium`, `date_podium`, `id_ville`) VALUES " . implode(",", $vals));
    }
    echo "<p>Podiums : " . count($scraper->podiums) . "</p>";

    // =============================================
    // 8. RÉSULTATS (batch INSERT)
    // =============================================

    if (!empty($scraper->resultats)) {
        $vals = [];
        foreach ($scraper->resultats as $r) {
            $id_ep = cachedGetOrInsertId($cache, $conn, 'epreuves', 'id_epreuve', 'nom_epreuve', $r['epreuve']);
            $id_vi = cachedGetOrInsertId($cache, $conn, 'villes', 'id_ville', 'nom_ville', $r['lieu']);
            $vals[] = "('$id_athlete', '" . $esc($r['annee']) . "', " . fk($r['date']) . ", "
                . fk($id_ep) . ", " . fk($r['performance']) . ", '"
                . $esc($r['performance_brut'] ?? '') . "', '" . $esc($r['vent'] ?? '')
                . "', '" . $esc($r['tour'] ?? '') . "', " . fk($r['place']) . ", '"
                . $esc($r['niveau'] ?? '') . "', " . fk($r['points']) . ", " . fk($id_vi) . ")";
        }
        $conn->query("INSERT INTO `athlete_resultats` (`id_athlete`, `annee_resultat`, `date_resultat`, `id_epreuve`, `performance_resultat`, `performance_brut_resultat`, `vent_resultat`, `tour_resultat`, `place_resultat`, `niveau_resultat`, `points_resultat`, `id_ville`) VALUES " . implode(",", $vals));
    }
    echo "<p>Resultats : " . count($scraper->resultats) . "</p>";

    // =============================================
    // 9. NIVEAUX + PERFS (séquentiel : besoin de id_niveau pour les perfs)
    // =============================================

    $countNiveaux = 0;
    $countPerfs = 0;

    foreach ($scraper->niveaux as $niv) {
        $id_cl = cachedGetOrInsertId($cache, $conn, 'clubs', 'id_club', 'nom_club', $niv['club'] ?? '');

        $conn->query("INSERT INTO `athlete_niveaux` (`id_athlete`, `annee_niveau`, `code_niveau`, `points_niveau`, `id_club`) VALUES ('$id_athlete', '" . $esc($niv['annee']) . "', '" . $esc($niv['code_niveau']) . "', " . fk($niv['points_niveau']) . ", " . fk($id_cl) . ")");
        $countNiveaux++;
        $id_niveau = (int)$conn->insert_id;

        // Batch INSERT des performances de ce niveau
        if (!empty($niv['performances'])) {
            $vals = [];
            foreach ($niv['performances'] as $perf) {
                $id_ep = cachedGetOrInsertId($cache, $conn, 'epreuves', 'id_epreuve', 'nom_epreuve', $perf['epreuve']);
                $vals[] = "('$id_niveau', " . fk($id_ep) . ", " . fk($perf['performance']) . ", '"
                    . $esc($perf['performance_brut'] ?? '') . "', '" . $esc($perf['code_niveau'] ?? '') . "')";
                $countPerfs++;
            }
            $conn->query("INSERT INTO `athlete_niv_perfs` (`id_niveau`, `id_epreuve`, `performance_niveau_perf`, `performance_brut_niveau_perf`, `code_perf_niveau`) VALUES " . implode(",", $vals));
        }
    }
    echo "<p>Niveaux : $countNiveaux (avec $countPerfs performances)</p>";
}
