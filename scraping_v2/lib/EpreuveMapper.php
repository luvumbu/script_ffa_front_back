<?php
/**
 * EpreuveMapper.php — Construit dynamiquement le mapping code FFA -> libelle
 *
 * Strategie : on n'a pas de liste hardcodee. On lit la table source qui
 * contient deux choses :
 *   - URL avec frmepreuve=208
 *   - colonne epreuve "2004 | 800m | F"
 *
 * En croisant les deux on apprend que 208 = "800m". Le mapping s'enrichit
 * automatiquement avec les nouvelles tables.
 */

class EpreuveMapper
{
    private $conn;
    private $prefixe;
    private $cache = [];
    private $cacheChargee = false;

    public function __construct(mysqli $conn, $prefixe = 'u489596434_bokonzi_on_')
    {
        $this->conn = $conn;
        $this->prefixe = rtrim($prefixe, '_');
    }

    /**
     * Charge le mapping en croisant URL.frmepreuve <-> colonne epreuve
     * NOTE : les colonnes utilisent le prefixe fixe, pas le nom de table
     *
     * @param array $tables Liste de tables sources a parcourir
     */
    public function chargerDepuisTables(array $tables)
    {
        $colUrl = $this->prefixe . '_url';
        $colEpr = $this->prefixe . '_epreuve';

        foreach ($tables as $table) {
            $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

            // Verifier que les colonnes existent
            $check = $this->conn->query("SHOW COLUMNS FROM `$tableSafe` LIKE '$colUrl'");
            if (!$check || $check->num_rows === 0) continue;

            $r = $this->conn->query("SELECT `$colUrl` AS url, `$colEpr` AS epr FROM `$tableSafe`");
            if (!$r) continue;

            while ($row = $r->fetch_assoc()) {
                $code = $this->extraireCode($row['url']);
                $libelle = $this->extraireLibelle($row['epr']);
                if ($code !== null && $libelle !== null) {
                    if (!isset($this->cache[$code])) {
                        $this->cache[$code] = [
                            'libelle'   => $libelle,
                            'tables'    => [$tableSafe],
                            'occurrences' => 1,
                        ];
                    } else {
                        $this->cache[$code]['occurrences']++;
                        if (!in_array($tableSafe, $this->cache[$code]['tables'])) {
                            $this->cache[$code]['tables'][] = $tableSafe;
                        }
                    }
                }
            }
        }

        ksort($this->cache, SORT_NUMERIC);
        $this->cacheChargee = true;
    }

    /**
     * Recupere le libelle pour un code donne
     */
    public function libelle($code)
    {
        return $this->cache[$code]['libelle'] ?? null;
    }

    /**
     * Retourne le mapping complet
     */
    public function tousLesMappings()
    {
        return $this->cache;
    }

    /**
     * Nb total de mappings appris
     */
    public function nombreMappings()
    {
        return count($this->cache);
    }

    /**
     * Extrait frmepreuve d'une URL
     */
    private function extraireCode($url)
    {
        $parts = parse_url($url);
        if (empty($parts['query'])) return null;
        parse_str($parts['query'], $params);
        return $params['frmepreuve'] ?? null;
    }

    /**
     * Extrait le libelle de la colonne epreuve "2004 | 800m | F"
     */
    private function extraireLibelle($epreuveColonne)
    {
        $parts = array_map('trim', explode('|', $epreuveColonne));
        // Format attendu : annee | libelle | sexe
        if (count($parts) >= 2) return $parts[1];
        return null;
    }
}
