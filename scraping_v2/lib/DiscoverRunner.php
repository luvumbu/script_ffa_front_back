<?php
/**
 * DiscoverRunner.php — Decouvre les epreuves pour une annee donnee
 *
 * Probleme : pour une nouvelle annee (ex: 2026), les tables sources n'ont pas
 * encore les lignes (url + page_total + epreuve). Il faut les creer.
 *
 * Strategie :
 *  1. Pour chaque table source, charger les combinaisons (url, libelle, sexe, vent)
 *     depuis l'annee de reference la plus recente avec des donnees
 *  2. Construire les URLs pour l'annee cible (remplace frmannee=YYYY)
 *  3. Visiter la page 1 → extraire page_total + verifier qu'il y a des resultats
 *  4. Si OK → INSERT dans la table source
 *
 * Modele identique a ScrapingRunner : flag + progress + cycle 25s + auto-refresh.
 */

class DiscoverRunner
{
    private $conn;
    private $stateDir;
    private $flagFile;
    private $progressFile;
    private $queuePattern;
    private $prefix;
    private $delayUs = 300000; // 0.3s anti-ban

    public function __construct(mysqli $conn, $stateDir = null)
    {
        $this->conn = $conn;
        $this->stateDir = $stateDir ?: __DIR__ . '/../state';
        if (!is_dir($this->stateDir)) @mkdir($this->stateDir, 0755, true);
        $this->flagFile     = $this->stateDir . '/discover_running.flag';
        $this->progressFile = $this->stateDir . '/discover_progress.json';
        $this->queuePattern = $this->stateDir . '/discover_queue_';
        $this->prefix       = 'u489596434_bokonzi_on';
    }

    public function isRunning()
    {
        return file_exists($this->flagFile);
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
        foreach (glob($this->queuePattern . '*.json') as $f) @unlink($f);
    }

    public function stop()
    {
        if (file_exists($this->flagFile)) @unlink($this->flagFile);
        $p = $this->getProgress();
        if ($p) {
            $p['stopped_at'] = date('Y-m-d H:i:s');
            file_put_contents($this->progressFile, json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Demarre la decouverte pour une annee cible.
     * Construit la file d'attente : toutes les combos (table, url_target, libelle, sexe, vent) a verifier.
     */
    public function start($targetYear, $referenceYear = null)
    {
        require_once __DIR__ . '/SourceTableReader.php';
        $reader = new SourceTableReader($this->conn);
        $tables = array_column($reader->listerTables(), 'nom');

        $queue = [];
        foreach ($tables as $table) {
            $combos = $this->getReferenceCombos($table, $referenceYear);
            foreach ($combos as $c) {
                $targetUrl = $this->buildUrlForYear($c['url_template'], $targetYear);
                $queue[] = [
                    'table'    => $table,
                    'url'      => $targetUrl,
                    'libelle'  => $c['libelle'],
                    'sexe'     => $c['sexe'],
                    'vent'     => $c['vent_marker'],
                ];
            }
        }

        $hash = substr(md5($targetYear . '|' . microtime(true)), 0, 10);
        $queueFile = $this->queuePattern . $hash . '.json';
        file_put_contents($queueFile, json_encode($queue, JSON_UNESCAPED_UNICODE));

        $progress = [
            'started_at'   => date('Y-m-d H:i:s'),
            'target_year'  => (int)$targetYear,
            'reference_year' => $referenceYear ?: 'auto',
            'mode'         => 'discover',
            'queue_file'   => basename($queueFile),
            'queue_index'  => 0,
            'stats' => [
                'total'    => count($queue),
                'inserted' => 0,
                'skipped'  => 0,
                'errors'   => 0,
                'empty'    => 0,  // page 1 sans resultat
            ],
            'log_tail'     => [],
            'finished'     => false,
        ];
        file_put_contents($this->progressFile, json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($this->flagFile, date('Y-m-d H:i:s'));
        return $progress;
    }

    /**
     * Mode VERIFY : reverifie le page_total de toutes les URLs deja en BDD pour 1 annee.
     * Charge les lignes existantes (WHERE epreuve LIKE '$annee |%') et bruteforce chacune.
     * Garantie : aucune autre annee n'est touchee.
     */
    public function startVerify($targetYear)
    {
        require_once __DIR__ . '/SourceTableReader.php';
        $reader = new SourceTableReader($this->conn);
        $tables = array_column($reader->listerTables(), 'nom');

        $colUrl = $this->prefix . '_url';
        $colEpr = $this->prefix . '_epreuve';
        $colPage = $this->prefix . '_page_total';

        $queue = [];
        foreach ($tables as $table) {
            $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $sql = "SELECT `$colUrl` AS url, `$colEpr` AS epreuve, `$colPage` AS page_total
                    FROM `$tableSafe`
                    WHERE `$colEpr` LIKE '$targetYear |%'
                    ORDER BY `" . $this->prefix . "_id`";
            $r = $this->conn->query($sql);
            if (!$r) continue;
            while ($row = $r->fetch_assoc()) {
                // Parser epreuve : "YYYY | libelle | sexe | Vent : VR"
                $parts = explode('|', $row['epreuve']);
                $libelle = isset($parts[1]) ? trim($parts[1]) : '';
                $sexe    = isset($parts[2]) ? trim($parts[2]) : '';
                $vent    = '';
                if (strpos($row['url'], 'frmvent=VR') !== false) $vent = 'VR';

                $queue[] = [
                    'table'    => $table,
                    'url'      => $row['url'],
                    'libelle'  => $libelle,
                    'sexe'     => $sexe,
                    'vent'     => $vent,
                ];
            }
        }

        $hash = substr(md5("verify|$targetYear|" . microtime(true)), 0, 10);
        $queueFile = $this->queuePattern . $hash . '.json';
        file_put_contents($queueFile, json_encode($queue, JSON_UNESCAPED_UNICODE));

        $progress = [
            'started_at'   => date('Y-m-d H:i:s'),
            'target_year'  => (int)$targetYear,
            'mode'         => 'verify',
            'queue_file'   => basename($queueFile),
            'queue_index'  => 0,
            'stats' => [
                'total'    => count($queue),
                'inserted' => 0,   // MAJ comptees ici
                'skipped'  => 0,   // page_total inchange
                'errors'   => 0,
                'empty'    => 0,
            ],
            'log_tail'     => [],
            'finished'     => false,
        ];
        file_put_contents($this->progressFile, json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($this->flagFile, date('Y-m-d H:i:s'));
        return $progress;
    }

    /**
     * Cycle d'execution (25s max).
     */
    public function runCycle($maxSeconds = 25)
    {
        $progress = $this->getProgress();
        if (!$progress || !$this->isRunning()) return null;

        $queueFile = $this->stateDir . '/' . $progress['queue_file'];
        if (!file_exists($queueFile)) {
            $progress['error'] = 'Queue introuvable';
            $progress['finished'] = true;
            file_put_contents($this->progressFile, json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->stop();
            return $progress;
        }
        $queue = json_decode(file_get_contents($queueFile), true);

        require_once __DIR__ . '/PageAnalyzer.php';
        $pa = new PageAnalyzer();

        $start = microtime(true);
        $log = [];

        while ((microtime(true) - $start) < $maxSeconds) {
            if ($progress['queue_index'] >= count($queue)) {
                $progress['finished'] = true;
                $log[] = 'TERMINE — toute la file traitee.';
                $this->stop();
                break;
            }

            $combo = $queue[$progress['queue_index']];
            $result = $this->processCombo($combo, $progress['target_year'], $pa);

            $log[] = $result['msg'];
            $progress['log_tail'][] = $result['msg'];
            $progress['log_tail'] = array_slice($progress['log_tail'], -40);
            $progress['stats'][$result['status']]++;
            $progress['queue_index']++;
            $progress['last_action_at'] = date('Y-m-d H:i:s');

            file_put_contents($this->progressFile, json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ((microtime(true) - $start) < $maxSeconds) usleep($this->delayUs);
        }

        return [
            'progress' => $progress,
            'log'      => $log,
            'duree_s'  => round(microtime(true) - $start, 1),
        ];
    }

    /**
     * Lit les combos (url + libelle + sexe + vent) connus depuis une annee de reference.
     */
    private function getReferenceCombos($table, $referenceYear = null)
    {
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $colUrl = $this->prefix . '_url';
        $colEpr = $this->prefix . '_epreuve';

        // Determiner l'annee de reference si non specifie : derniere annee non-vide
        if (!$referenceYear) {
            $r = $this->conn->query("
                SELECT TRIM(SUBSTRING_INDEX(`$colEpr`, '|', 1)) AS y, COUNT(*) c
                FROM `$tableSafe`
                GROUP BY y
                ORDER BY CAST(y AS UNSIGNED) DESC
                LIMIT 1
            ");
            if ($r && $row = $r->fetch_assoc()) $referenceYear = (int)$row['y'];
        }
        if (!$referenceYear) return [];

        $r = $this->conn->query("
            SELECT `$colUrl` AS url, `$colEpr` AS epreuve
            FROM `$tableSafe`
            WHERE `$colEpr` LIKE '$referenceYear |%'
            ORDER BY `" . $this->prefix . "_id`
        ");
        $combos = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $parts = explode('|', $row['epreuve']);
            $libelle = isset($parts[1]) ? trim($parts[1]) : '';
            $sexe    = isset($parts[2]) ? trim($parts[2]) : '';
            $vent    = '';
            if (strpos($row['url'], 'frmvent=VR') !== false) $vent = 'VR';
            $combos[] = [
                'url_template' => $row['url'],
                'libelle'      => $libelle,
                'sexe'         => $sexe,
                'vent_marker'  => $vent,
            ];
        }
        return $combos;
    }

    private function buildUrlForYear($urlTemplate, $year)
    {
        return preg_replace('/frmannee=\d{4}/', "frmannee=$year", $urlTemplate);
    }

    /**
     * Traite 1 combo : bruteforce frmposition pour determiner le vrai page_total,
     * puis INSERT (si nouvelle ligne) ou UPDATE (si page_total a evolue).
     */
    private function processCombo(array $combo, $targetYear, PageAnalyzer $pa)
    {
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $combo['table']);
        $colUrl    = $this->prefix . '_url';
        $colPage   = $this->prefix . '_page_total';
        $colEpr    = $this->prefix . '_epreuve';
        $esc = $this->conn->real_escape_string($combo['url']);

        // Verifier si la ligne existe deja (pour decider INSERT vs UPDATE)
        $rExist = $this->conn->query("SELECT `$colPage` AS pt FROM `$tableSafe` WHERE `$colUrl` = '$esc' LIMIT 1");
        $exists = false;
        $oldPageTotal = 0;
        if ($rExist && $row = $rExist->fetch_assoc()) {
            $exists = true;
            $oldPageTotal = (int)$row['pt'];
        }

        // Bruteforce du page_total reel
        // On part de la valeur connue (si on revisite), sinon de 1, et on incremente
        // jusqu'a tomber sur une page vide. Le dernier index avec data = page_total.
        $detected = $this->bruteforcePageTotal($combo['url'], $pa, max(1, $oldPageTotal));

        if ($detected === 0) {
            if ($exists) {
                return [
                    'status' => 'empty',
                    'msg'    => "VIDE {$combo['table']} → $targetYear | {$combo['libelle']} | {$combo['sexe']} (etait $oldPageTotal pages, plus de data)",
                ];
            }
            return [
                'status' => 'empty',
                'msg'    => "VIDE {$combo['table']} → $targetYear | {$combo['libelle']} | {$combo['sexe']} — aucun resultat",
            ];
        }

        $epreuve = "$targetYear | {$combo['libelle']} | {$combo['sexe']}";
        if (!empty($combo['vent'])) $epreuve .= " | Vent : {$combo['vent']}";
        $escEpr = $this->conn->real_escape_string($epreuve);

        if ($exists) {
            if ($detected === $oldPageTotal) {
                return [
                    'status' => 'skipped',
                    'msg'    => "SKIP {$combo['table']} → $epreuve — deja $oldPageTotal pages (inchange)",
                ];
            }
            // UPDATE page_total
            $sql = "UPDATE `$tableSafe` SET `$colPage` = '$detected', `$colEpr` = '$escEpr' WHERE `$colUrl` = '$esc'";
            if (!$this->conn->query($sql)) {
                return ['status' => 'errors', 'msg' => "ERR UPDATE: " . $this->conn->error];
            }
            return [
                'status' => 'inserted', // compte comme "traite avec succes"
                'msg'    => "MAJ  {$combo['table']} → $epreuve — $oldPageTotal -> $detected pages",
            ];
        }

        // INSERT nouveau
        $sql = "INSERT INTO `$tableSafe`
                (`$colUrl`, `$colPage`, `$colEpr`, `get_result_users_nom_1_array_2`)
                VALUES ('$esc', '$detected', '$escEpr', '')";
        if (!$this->conn->query($sql)) {
            return ['status' => 'errors', 'msg' => "ERR INSERT: " . $this->conn->error];
        }
        return [
            'status' => 'inserted',
            'msg'    => "OK   {$combo['table']} → $epreuve — $detected pages (bruteforce)",
        ];
    }

    /**
     * Bruteforce du nombre reel de pages pour une URL.
     *
     * Strategie : test page 1, 2, 3... en incrementant frmposition de 50,
     * jusqu'a tomber sur une page avec 0 athletes. La derniere page avec data = total.
     *
     * Optimisation : si on a un hint (oldPageTotal > 1), on commence un peu en avance
     * mais on s'assure d'avoir au moins un test "page valide -> page suivante vide".
     *
     * Retourne 0 si la page 1 est vide (epreuve sans data cette annee).
     */
    private function bruteforcePageTotal($baseUrl, PageAnalyzer $pa, $hint = 1)
    {
        $maxGuard = 300; // 300 pages = 15000 resultats (cap de securite)
        $lastNonEmpty = 0;

        // Phase 1 : verifier page 1
        $r = $pa->analyze($this->urlForPage($baseUrl, 1));
        if (!$r['success'] || count($r['athletes']) === 0) {
            return 0;
        }
        $lastNonEmpty = 1;
        usleep(200000);

        // Phase 2 : incrementer lineairement (avec un saut initial si hint)
        $start = max(2, (int)$hint);
        for ($p = $start; $p <= $maxGuard; $p++) {
            $r = $pa->analyze($this->urlForPage($baseUrl, $p));
            if (!$r['success']) {
                // Erreur HTTP -> on s'arrete prudemment
                break;
            }
            if (count($r['athletes']) === 0) {
                // Page vide trouvee
                // Si on a saute (hint > 2), il faut verifier qu'on n'a pas saute des pages valides
                // mais comme athle.fr garde la pagination continue, page vide = fin
                break;
            }
            $lastNonEmpty = $p;
            usleep(200000);
        }
        return $lastNonEmpty;
    }

    private function urlForPage($baseUrl, $page)
    {
        // athle.fr 2025+ : frmposition = (page-1), 0-indexed (plus de ×50)
        $pos = max(0, (int)$page - 1);
        return preg_replace('/frmposition=\d+/', "frmposition=$pos", $baseUrl);
    }
}
