<?php
/**
 * IntegratedRunner.php — Scraping integre : classement + profil en une seule passe
 *
 * Pour chaque page de classement :
 *  1. Fetch la page (50 athletes max)
 *  2. Extract la liste d'athletes
 *  3. Pour chaque batch de N athletes :
 *     a. scrapeParallel (3 pages chacun en parallele)
 *     b. AthleteScraper -> extractAll()
 *     c. insertAthleteData (UPDATE si existe / INSERT sinon)
 *  4. Sauvegarde progression
 *
 * Modele : flag + progress.json + cycle 25s + auto-refresh.
 *
 * Etats fichiers (sous state/) :
 *  integrated_running.flag
 *  integrated_progress.json
 *  integrated_queue_<hash>.json
 */

class IntegratedRunner
{
    private $conn;
    private $stateDir;
    private $flagFile;
    private $progressFile;
    private $queuePattern;
    private $parallel;
    private $delayUs;
    private $refCache;  // cache des FK (clubs, epreuves, villes, ...)

    public function __construct(mysqli $conn, $stateDir = null)
    {
        $this->conn = $conn;
        $this->stateDir = $stateDir ?: __DIR__ . '/../state';
        if (!is_dir($this->stateDir)) @mkdir($this->stateDir, 0755, true);
        $this->flagFile     = $this->stateDir . '/integrated_running.flag';
        $this->progressFile = $this->stateDir . '/integrated_progress.json';
        $this->queuePattern = $this->stateDir . '/integrated_queue_';
        $this->parallel     = 4;       // 4 athletes en parallele (safe anti-ban)
        $this->delayUs      = 200000;  // 0.2s entre batches
    }

    public function isRunning() { return file_exists($this->flagFile); }

    public function getProgress()
    {
        if (!file_exists($this->progressFile)) return null;
        return json_decode(file_get_contents($this->progressFile), true);
    }

    public function reset()
    {
        @unlink($this->flagFile);
        @unlink($this->progressFile);
        foreach (glob($this->queuePattern . '*.json') as $f) @unlink($f);
    }

    public function stop()
    {
        @unlink($this->flagFile);
        $p = $this->getProgress();
        if ($p) {
            $p['stopped_at'] = date('Y-m-d H:i:s');
            $this->saveProgress($p);
        }
    }

    /**
     * Demarre le scraping integre pour une annee donnee (toutes tables sources).
     */
    public function start($annee)
    {
        require_once __DIR__ . '/SourceTableReader.php';
        $reader = new SourceTableReader($this->conn);
        $tables = array_column($reader->listerTables(), 'nom');

        $candidates = [];
        foreach ($tables as $t) {
            $rows = $this->loadCandidates($t, [(int)$annee]);
            foreach ($rows as &$r) $r['source_table'] = $t;
            unset($r);
            $candidates = array_merge($candidates, $rows);
        }

        $totalPages = 0;
        foreach ($candidates as $c) $totalPages += max(1, (int)$c['page_total']);

        $hash = substr(md5("$annee|" . microtime(true)), 0, 10);
        $queueFile = $this->queuePattern . $hash . '.json';
        file_put_contents($queueFile, json_encode($candidates, JSON_UNESCAPED_UNICODE));

        $progress = [
            'started_at'     => date('Y-m-d H:i:s'),
            'last_action_at' => date('Y-m-d H:i:s'),
            'annee'          => (int)$annee,
            'queue_file'     => basename($queueFile),
            'url_index'      => 0,
            'page_courante'  => 1,
            'stats' => [
                'urls_total'        => count($candidates),
                'urls_terminees'    => 0,
                'pages_total'       => $totalPages,
                'pages_traitees'    => 0,
                'athletes_vus'      => 0,
                'athletes_updated'  => 0,
                'athletes_inserted' => 0,
                'athletes_errors'   => 0,
                'fetch_errors'      => 0,
                'tables_count'      => count($tables),
            ],
            'log_tail'       => [],
            'finished'       => false,
        ];
        $this->saveProgress($progress);
        file_put_contents($this->flagFile, date('Y-m-d H:i:s'));
        return $progress;
    }

    /**
     * Cycle d'execution : ~25s max.
     */
    public function runCycle($maxSeconds = 25)
    {
        $progress = $this->getProgress();
        if (!$progress || !$this->isRunning()) return null;

        $queueFile = $this->stateDir . '/' . $progress['queue_file'];
        if (!file_exists($queueFile)) {
            $progress['error'] = 'Queue introuvable';
            $progress['finished'] = true;
            $this->saveProgress($progress);
            $this->stop();
            return $progress;
        }
        $candidates = json_decode(file_get_contents($queueFile), true);

        require_once __DIR__ . '/PageAnalyzer.php';
        require_once __DIR__ . '/UrlAnalyzer.php';
        require_once dirname(__DIR__, 2) . '/Class/AthleteScraper.php';
        require_once dirname(__DIR__, 2) . '/core/insert_athle.php';
        require_once dirname(__DIR__, 2) . '/scraping/scrape_functions.php';

        $pa = new PageAnalyzer();
        $ua = new UrlAnalyzer();

        if (!$this->refCache) {
            $this->refCache = loadRefCache($this->conn);
        }

        // Check existants en BDD pour decider UPDATE vs INSERT
        $existingCache = [];

        $start = microtime(true);
        $log = [];

        while ((microtime(true) - $start) < $maxSeconds) {
            // Fini ?
            if ($progress['url_index'] >= count($candidates)) {
                $progress['finished'] = true;
                $progress['last_action_at'] = date('Y-m-d H:i:s');
                $this->saveProgress($progress);
                $this->stop();
                $log[] = 'TERMINE — toutes les URLs traitees.';
                break;
            }

            $cur = $candidates[$progress['url_index']];
            $pageTotal = max(1, (int)$cur['page_total']);
            $page      = (int)$progress['page_courante'];
            $urlPage   = $ua->urlPourPage($cur['url'], $page);

            // 1. Fetch page de classement
            $rPage = $pa->analyze($urlPage);
            $progress['last_url'] = $urlPage;
            $progress['last_action_at'] = date('Y-m-d H:i:s');

            if (!$rPage['success']) {
                $progress['stats']['fetch_errors']++;
                $log[] = "FETCH KO #{$cur['id']} p$page (HTTP {$rPage['http_code']})";
            } else {
                $athletes = $rPage['athletes'];
                $progress['stats']['athletes_vus'] += count($athletes);

                // 2. Pour chaque batch d'athletes : scrapeParallel + insertAthleteData
                $batches = array_chunk($athletes, $this->parallel);
                foreach ($batches as $batch) {
                    if ((microtime(true) - $start) >= $maxSeconds) break;

                    $extIds = [];
                    foreach ($batch as $a) {
                        if (preg_match('#/athletes/(\d+)/#', $a['url_fiche'] ?? '', $m)) {
                            $extIds[] = (int)$m[1];
                        }
                    }
                    if (empty($extIds)) continue;

                    // Fetch 3 pages × N athletes en parallele
                    $pages = scrapeParallel($extIds);

                    foreach ($extIds as $idExt) {
                        $pgs = $pages[$idExt] ?? null;
                        if (!$pgs || empty($pgs['bilans'])) {
                            $progress['stats']['athletes_errors']++;
                            continue;
                        }
                        try {
                            // Check si existant en BDD
                            if (!isset($existingCache[$idExt])) {
                                $r = $this->conn->query("SELECT id_athlete FROM athletes WHERE athlete_id_externe = $idExt LIMIT 1");
                                $existingCache[$idExt] = $r && $r->num_rows > 0;
                            }
                            $wasExisting = $existingCache[$idExt];

                            // Scraping complet
                            $s = new AthleteScraper($idExt);
                            $s->html = $pgs['bilans'];
                            $s->extractIdentite();
                            $s->extractMedailles();
                            $s->extractProgressions();
                            $s->extractClubs();
                            $s->extractPodiums();
                            $s->extractResultats();
                            $s->extractNiveaux();
                            if (!empty($pgs['records'])) {
                                $s->html = $pgs['records'];
                                $s->extractRecords();
                            }
                            if (!empty($pgs['selections'])) {
                                $s->html = $pgs['selections'];
                                $s->extractSelections();
                            }

                            ob_start();
                            insertAthleteData($s, $this->conn, $this->refCache);
                            ob_end_clean();

                            if ($wasExisting) $progress['stats']['athletes_updated']++;
                            else              $progress['stats']['athletes_inserted']++;

                        } catch (Throwable $e) {
                            $progress['stats']['athletes_errors']++;
                        }
                    }
                    usleep($this->delayUs);
                }

                $log[] = "OK #{$cur['id']} p$page → " . count($athletes) . " athletes traites";
            }

            $progress['stats']['pages_traitees']++;
            $progress['log_tail'][] = end($log);
            $progress['log_tail'] = array_slice($progress['log_tail'], -40);

            // Avancer : page++ ou next URL
            if ($page >= $pageTotal) {
                $progress['url_index']++;
                $progress['page_courante'] = 1;
                $progress['stats']['urls_terminees']++;
            } else {
                $progress['page_courante'] = $page + 1;
            }

            $this->saveProgress($progress);
        }

        return [
            'progress' => $progress,
            'log'      => $log,
            'duree_s'  => round(microtime(true) - $start, 1),
        ];
    }

    private function saveProgress($p)
    {
        file_put_contents($this->progressFile, json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function loadCandidates($table, array $annees)
    {
        $prefix = 'u489596434_bokonzi_on';
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
                ORDER BY `$colId`";
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
