<?php
/**
 * SourceTableReader.php — Decouvre et lit les tables sources de URLs
 *
 * Les tables sources sont du type u489596434_bokonzi_on_<discipline>_<lieu>_<sexe>
 * Exemples : sprint_piste_h, demi_fond_piste_f, saut_salle_h, ...
 *
 * Chaque table contient :
 *   - une colonne URL (frmannee=YYYY&frmepreuve=N&frmsexe=F)
 *   - une colonne epreuve "YYYY | libelle | sexe"
 *   - un compteur de pages page_total
 */

class SourceTableReader
{
    private $conn;
    private $prefixe;

    public function __construct(mysqli $conn, $prefixe = 'u489596434_bokonzi_on_')
    {
        $this->conn = $conn;
        $this->prefixe = $prefixe;
    }

    /**
     * Liste toutes les tables sources detectees dans la BDD
     * Pattern : prefixe + (sprint|demi_fond|saut|lancer|haies|marche|combine) + ...
     */
    public function listerTables()
    {
        $disciplines = ['sprint', 'demi_fond', 'fond', 'saut', 'lancer', 'haies', 'marche', 'combine'];
        $patternsLike = [];
        foreach ($disciplines as $d) {
            $patternsLike[] = "TABLE_NAME LIKE '" . $this->prefixe . $d . "%'";
        }
        $where = implode(' OR ', $patternsLike);
        $dbName = $this->conn->query("SELECT DATABASE() AS d")->fetch_assoc()['d'];

        $sql = "SELECT TABLE_NAME, TABLE_ROWS
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = '$dbName'
                  AND ($where)
                ORDER BY TABLE_NAME";

        $r = $this->conn->query($sql);
        $tables = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $tables[] = [
                    'nom'           => $row['TABLE_NAME'],
                    'lignes_approx' => (int)$row['TABLE_ROWS'],
                    'meta'          => $this->extraireMeta($row['TABLE_NAME']),
                ];
            }
        }
        return $tables;
    }

    /**
     * Compte exact des lignes d'une table
     */
    public function compterLignes($table)
    {
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $r = $this->conn->query("SELECT COUNT(*) AS c FROM `$tableSafe`");
        return $r ? (int)$r->fetch_assoc()['c'] : 0;
    }

    /**
     * Retourne le prefixe de colonnes (commun a toutes les tables soeurs)
     */
    public function prefixeColonnes()
    {
        return $this->prefixe;
    }

    /**
     * Lit les N premieres lignes d'une table
     * NOTE : les colonnes sont nommees avec le prefixe FIXE (pas le nom de table)
     *        ex: table=u489596434_bokonzi_on_sprint_piste_h
     *            colonnes=u489596434_bokonzi_on_id, u489596434_bokonzi_on_url, ...
     */
    public function premieresLignes($table, $limit = 5)
    {
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $limit = (int)$limit;
        $p = rtrim($this->prefixe, '_');
        $colId   = $p . '_id';
        $colUrl  = $p . '_url';
        $colPage = $p . '_page_total';
        $colEpr  = $p . '_epreuve';
        $colTime = $p . '_time';

        $sql = "SELECT
                    `$colId`   AS id,
                    `$colUrl`  AS url,
                    `$colPage` AS page_total,
                    `$colEpr`  AS epreuve,
                    `$colTime` AS time
                FROM `$tableSafe`
                ORDER BY `$colId` ASC
                LIMIT $limit";

        $r = $this->conn->query($sql);
        $rows = [];
        if ($r) while ($row = $r->fetch_assoc()) $rows[] = $row;
        return $rows;
    }

    /**
     * Compte combien de lignes ont une annee >= seuil
     * (parsing de la colonne epreuve "YYYY | libelle | sexe")
     */
    public function compterParAnnee($table)
    {
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $p = rtrim($this->prefixe, '_');
        $colEpr = $p . '_epreuve';

        $sql = "SELECT TRIM(SUBSTRING_INDEX(`$colEpr`, '|', 1)) AS annee, COUNT(*) AS n
                FROM `$tableSafe`
                GROUP BY annee
                ORDER BY annee ASC";

        $r = $this->conn->query($sql);
        $stats = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $stats[(int)$row['annee']] = (int)$row['n'];
        }
        return $stats;
    }

    /**
     * Stats globales par annee : agrege toutes les tables sources.
     * Retourne pour chaque annee : urls (nb) + pages (somme page_total) + tables (liste).
     *
     * IMPORTANT : ces requetes GROUP BY sur longtext sont LENTES (~2 min) sur Hostinger.
     * On met en cache fichier (TTL 1h) pour eviter le timeout.
     * Force le recalcul avec ?refresh_stats=1.
     */
    public function statsParAnneeGlobal($forceRefresh = false)
    {
        $cacheFile = __DIR__ . '/../state/stats_par_annee.json';
        $cacheTtl = 3600; // 1h

        if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                // Reconvertir clefs en int (json_decode les met en string)
                $out = [];
                foreach ($cached as $k => $v) $out[(int)$k] = $v;
                krsort($out);
                return $out;
            }
        }

        $tables = $this->listerTables();
        $p = rtrim($this->prefixe, '_');
        $colEpr  = $p . '_epreuve';
        $colPage = $p . '_page_total';

        $agg = [];

        foreach ($tables as $t) {
            $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $t['nom']);
            $sql = "SELECT TRIM(SUBSTRING_INDEX(`$colEpr`, '|', 1)) AS annee,
                           COUNT(*) AS urls,
                           SUM(`$colPage`) AS pages
                    FROM `$tableSafe`
                    GROUP BY annee";
            $r = $this->conn->query($sql);
            if (!$r) continue;
            while ($row = $r->fetch_assoc()) {
                $an = (int)$row['annee'];
                if ($an < 1900 || $an > 2100) continue;
                if (!isset($agg[$an])) $agg[$an] = ['urls' => 0, 'pages' => 0, 'tables' => []];
                $agg[$an]['urls']   += (int)$row['urls'];
                $agg[$an]['pages']  += (int)$row['pages'];
                $agg[$an]['tables'][] = $t['nom'];
            }
        }
        krsort($agg);

        // Sauver le cache
        $stateDir = dirname($cacheFile);
        if (!is_dir($stateDir)) @mkdir($stateDir, 0755, true);
        @file_put_contents($cacheFile, json_encode($agg, JSON_UNESCAPED_UNICODE));

        return $agg;
    }

    /**
     * Extrait les meta du nom de table : discipline, lieu, sexe
     */
    private function extraireMeta($nomTable)
    {
        $sansPrefixe = substr($nomTable, strlen($this->prefixe));
        // Le nom peut contenir des doubles underscores (piste__courte)
        $sansPrefixe = str_replace('__', '_', $sansPrefixe);
        $morceaux = explode('_', $sansPrefixe);
        $sexe = end($morceaux);
        array_pop($morceaux);

        // Detecter le lieu (dernier morceau ou couple)
        $lieu = null;
        $disciplines = ['sprint', 'demi_fond', 'fond', 'saut', 'lancer', 'haies', 'marche', 'combine'];
        $reste = implode('_', $morceaux);
        $discipline = null;
        foreach ($disciplines as $d) {
            if (strpos($reste, $d) === 0) {
                $discipline = $d;
                $lieu = trim(substr($reste, strlen($d)), '_');
                break;
            }
        }

        return [
            'discipline' => $discipline,
            'lieu'       => $lieu,
            'sexe'       => strtoupper($sexe),
        ];
    }
}
