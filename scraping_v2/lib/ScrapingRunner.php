<?php
/**
 * ScrapingRunner.php — Orchestrateur scraping v2
 *
 * Modele scraper.php : flag start/stop + progression sauvegardee + cycle 25s + auto-refresh.
 *
 * Fait :
 *  1. Charge les URLs candidates (filtre par annees cochees) depuis la table source
 *  2. Pour chaque URL : pour chaque page (1..page_total), fetch + parse + INSERT
 *  3. Sauve la progression apres chaque page (reprise possible apres crash)
 *  4. INSERT brut sans IGNORE → doublons autorises (cleanup_duplicates.php pour nettoyer)
 *
 * Etats fichiers :
 *  scraper_v2_running.flag        Drapeau "en cours"
 *  scraper_v2_progress.json       Etat detaille (url_index, page, stats, ...)
 *  scraper_v2_candidates_<hash>   Cache de la liste d'URLs filtrees pour cette session
 */

class ScrapingRunner
{
    private $conn;
    private $stateDir;
    private $flagFile;
    private $progressFile;
    private $candidatesPattern;
    private $delayUs = 300000; // 0.3s entre fetches (anti-ban)

    public function __construct(mysqli $conn, $stateDir = null)
    {
        $this->conn = $conn;
        $this->stateDir = $stateDir ?: __DIR__ . '/../state';
        if (!is_dir($this->stateDir)) @mkdir($this->stateDir, 0755, true);
        $this->flagFile          = $this->stateDir . '/scraper_v2_running.flag';
        $this->progressFile      = $this->stateDir . '/scraper_v2_progress.json';
        $this->candidatesPattern = $this->stateDir . '/scraper_v2_candidates_';
    }

    // =========================================================================
    // Controle start / stop / etat
    // =========================================================================

    public function isRunning()
    {
        return file_exists($this->flagFile);
    }

    public function start($table, array $annees)
    {
        $annees = array_values(array_unique(array_map('intval', $annees)));
        sort($annees);

        // Charger et figer les URLs candidates
        $candidates = $this->loadCandidatesFromDb($table, $annees);
        $cacheFile  = $this->candidatesPattern . $this->sessionHash($table, $annees) . '.json';
        file_put_contents($cacheFile, json_encode($candidates, JSON_UNESCAPED_UNICODE));

        $totalPages = 0;
        foreach ($candidates as $c) $totalPages += max(1, (int)$c['page_total']);

        $progress = [
            'started_at'      => date('Y-m-d H:i:s'),
            'last_action_at'  => date('Y-m-d H:i:s'),
            'table'           => $table,
            'annees'          => $annees,
            'cache_file'      => basename($cacheFile),
            'url_index'       => 0,
            'page_courante'   => 1,
            'stats' => [
                'urls_total'        => count($candidates),
                'urls_terminees'    => 0,
                'pages_total'       => $totalPages,
                'pages_traitees'    => 0,
                'athletes_inserts'  => 0,
                'doublons_potentiels'=> 0,
                'fetch_errors'      => 0,
            ],
            'last_url'        => null,
            'finished'        => false,
        ];
        $this->saveProgress($progress);
        file_put_contents($this->flagFile, date('Y-m-d H:i:s'));
        return $progress;
    }

    /**
     * Demarre un scraping sur 1 annee, en agregant TOUTES les tables sources.
     * Ideal pour le mode "1 annee = je scrape tout ce qui existe".
     */
    public function startForYear($annee)
    {
        $annee = (int)$annee;
        require_once __DIR__ . '/SourceTableReader.php';
        $reader = new SourceTableReader($this->conn);
        $tables = array_column($reader->listerTables(), 'nom');

        $candidates = [];
        foreach ($tables as $t) {
            $rows = $this->loadCandidatesFromDb($t, [$annee]);
            // Annoter chaque candidat avec sa table d'origine (utile pour le log)
            foreach ($rows as &$r) $r['source_table'] = $t;
            unset($r);
            $candidates = array_merge($candidates, $rows);
        }

        $cacheFile  = $this->candidatesPattern . $this->sessionHash('ALL', [$annee]) . '.json';
        file_put_contents($cacheFile, json_encode($candidates, JSON_UNESCAPED_UNICODE));

        $totalPages = 0;
        foreach ($candidates as $c) $totalPages += max(1, (int)$c['page_total']);

        $progress = [
            'started_at'      => date('Y-m-d H:i:s'),
            'last_action_at'  => date('Y-m-d H:i:s'),
            'mode'            => 'year',
            'table'           => 'ALL',
            'annees'          => [$annee],
            'cache_file'      => basename($cacheFile),
            'url_index'       => 0,
            'page_courante'   => 1,
            'stats' => [
                'urls_total'        => count($candidates),
                'urls_terminees'    => 0,
                'pages_total'       => $totalPages,
                'pages_traitees'    => 0,
                'athletes_inserts'  => 0,
                'doublons_potentiels'=> 0,
                'fetch_errors'      => 0,
                'tables_count'      => count($tables),
            ],
            'last_url'        => null,
            'finished'        => false,
        ];
        $this->saveProgress($progress);
        file_put_contents($this->flagFile, date('Y-m-d H:i:s'));
        return $progress;
    }

    public function stop()
    {
        if (file_exists($this->flagFile)) @unlink($this->flagFile);
        $p = $this->getProgress();
        if ($p) {
            $p['stopped_at'] = date('Y-m-d H:i:s');
            $this->saveProgress($p);
        }
    }

    public function getProgress()
    {
        if (!file_exists($this->progressFile)) return null;
        return json_decode(file_get_contents($this->progressFile), true);
    }

    public function reset()
    {
        if (file_exists($this->flagFile))     @unlink($this->flagFile);
        if (file_exists($this->progressFile)) @unlink($this->progressFile);
        foreach (glob($this->candidatesPattern . '*.json') as $f) @unlink($f);
    }

    // =========================================================================
    // Cycle d'execution (appele a chaque chargement de page tant que flag existe)
    // =========================================================================

    public function runCycle($maxSeconds = 25)
    {
        $progress = $this->getProgress();
        if (!$progress || !$this->isRunning()) return null;

        $cacheFile = $this->stateDir . '/' . $progress['cache_file'];
        if (!file_exists($cacheFile)) {
            $progress['error'] = 'Cache candidates introuvable, relance start().';
            $progress['finished'] = true;
            $this->saveProgress($progress);
            $this->stop();
            return $progress;
        }
        $candidates = json_decode(file_get_contents($cacheFile), true);

        require_once __DIR__ . '/PageAnalyzer.php';
        require_once __DIR__ . '/UrlAnalyzer.php';
        $pa = new PageAnalyzer();
        $ua = new UrlAnalyzer();

        $insertSql = "INSERT INTO nom_et_liens (url) VALUES (?)";
        $stmt = $this->conn->prepare($insertSql);
        if (!$stmt) {
            $progress['error'] = 'Prepare INSERT failed: ' . $this->conn->error;
            $this->saveProgress($progress);
            return $progress;
        }

        // Preload blacklist (set d'IDs purges definitivement, on les ignore)
        $blacklist = [];
        $resBl = @$this->conn->query("SELECT athlete_id_ext FROM athlete_blacklist");
        if ($resBl) {
            while ($rowBl = $resBl->fetch_assoc()) $blacklist[(int)$rowBl['athlete_id_ext']] = true;
            $resBl->free();
        }

        $cycleStart = microtime(true);
        $cycleInserts = 0;
        $cyclePages = 0;
        $cycleSkipped = 0;
        $logLines = [];

        while ((microtime(true) - $cycleStart) < $maxSeconds) {
            // Fini ?
            if ($progress['url_index'] >= count($candidates)) {
                $progress['finished'] = true;
                $progress['last_action_at'] = date('Y-m-d H:i:s');
                $this->saveProgress($progress);
                $this->stop();
                $logLines[] = 'TERMINE — toutes les URLs traitees.';
                break;
            }

            $cur = $candidates[$progress['url_index']];
            $pageTotal = max(1, (int)$cur['page_total']);
            $page      = (int)$progress['page_courante'];
            $urlPage   = $ua->urlPourPage($cur['url'], $page);

            // Fetch + parse
            $r = $pa->analyze($urlPage);
            $progress['last_url'] = $urlPage;
            $progress['last_action_at'] = date('Y-m-d H:i:s');

            if (!$r['success']) {
                $progress['stats']['fetch_errors']++;
                $logLines[] = "FETCH KO #{$cur['id']} p$page (HTTP {$r['http_code']})";
            } else {
                // INSERT chaque athlete (skip si blackliste = athlete purge volontairement)
                $nbInsertsPage = 0;
                $nbSkipped = 0;
                foreach ($r['athletes'] as $a) {
                    $url = "https://athle.fr" . $a['url_fiche'];
                    if ($blacklist && preg_match('#/athletes/(\d+)/#', $url, $m) && isset($blacklist[(int)$m[1]])) {
                        $nbSkipped++;
                        continue;
                    }
                    $stmt->bind_param('s', $url);
                    if ($stmt->execute()) {
                        $nbInsertsPage++;
                        $progress['stats']['athletes_inserts']++;
                    }
                }
                $logLines[] = "OK #{$cur['id']} p$page → {$nbInsertsPage} INSERT" . ($nbSkipped > 0 ? " ({$nbSkipped} blacklist)" : "") . " (HTTP 200, " . count($r['athletes']) . " trouves)";
                $cycleInserts += $nbInsertsPage;
                $cycleSkipped += $nbSkipped;
            }
            $cyclePages++;
            $progress['stats']['pages_traitees']++;

            // Avancer : page++ ou prochaine URL
            if ($page >= $pageTotal) {
                $progress['url_index']++;
                $progress['page_courante'] = 1;
                $progress['stats']['urls_terminees']++;
            } else {
                $progress['page_courante'] = $page + 1;
            }

            $this->saveProgress($progress);

            // Anti-ban : pause entre 2 fetches
            if ((microtime(true) - $cycleStart) < $maxSeconds) {
                usleep($this->delayUs);
            }
        }

        $stmt->close();

        return [
            'progress'      => $progress,
            'cycle_pages'   => $cyclePages,
            'cycle_inserts' => $cycleInserts,
            'duree_s'       => round(microtime(true) - $cycleStart, 1),
            'log'           => $logLines,
        ];
    }

    // =========================================================================
    // Helpers prives
    // =========================================================================

    private function saveProgress($p)
    {
        file_put_contents($this->progressFile, json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function sessionHash($table, $annees)
    {
        return substr(md5($table . '|' . implode(',', $annees) . '|' . microtime(true)), 0, 12);
    }

    private function loadCandidatesFromDb($table, $annees)
    {
        require_once __DIR__ . '/SourceTableReader.php';
        $reader = new SourceTableReader($this->conn);
        $prefix = rtrim($reader->prefixeColonnes(), '_');

        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $colId   = $prefix . '_id';
        $colUrl  = $prefix . '_url';
        $colPage = $prefix . '_page_total';
        $colEpr  = $prefix . '_epreuve';

        $sql = "SELECT
                    `$colId`   AS id,
                    `$colUrl`  AS url,
                    `$colPage` AS page_total,
                    `$colEpr`  AS epreuve
                FROM `$tableSafe`
                ORDER BY `$colId` ASC";

        $r = $this->conn->query($sql);
        $candidates = [];
        if ($r) {
            $anneeSet = array_flip($annees);
            while ($row = $r->fetch_assoc()) {
                $annee = (int) trim(strtok((string)$row['epreuve'], '|'));
                if (!isset($anneeSet[$annee])) continue;
                $candidates[] = [
                    'id'         => (int)$row['id'],
                    'url'        => $row['url'],
                    'page_total' => (int)$row['page_total'],
                    'annee'      => $annee,
                    'epreuve'    => $row['epreuve'],
                ];
            }
        }
        return $candidates;
    }
}
