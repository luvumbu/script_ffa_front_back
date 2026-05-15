<?php
/**
 * par_epreuve.php — Scraping epreuve par epreuve avec console live
 *
 * UI : 1 dropdown annee + tableau des epreuves + boutons par ligne
 * Chaque action streame son output via Server-Sent Events (SSE) ou fallback polling.
 *
 * Actions disponibles par epreuve :
 *   - VERIFIER : bruteforce le page_total reel
 *   - SCRAPER  : visite toutes les pages → INSERT URLs athletes dans nom_et_liens
 *   - MAJ      : re-scrape complet de chaque athlete (UPDATE/INSERT)
 *
 * URL : https://bokonzi.com/scraping_v2/par_epreuve.php
 */

@ini_set('display_errors', '0');
@ini_set('output_buffering', 'Off');
@ini_set('zlib.output_compression', 'Off');

require_once dirname(__DIR__) . '/core/db.php';
require_once __DIR__ . '/lib/SourceTableReader.php';
require_once __DIR__ . '/lib/PageAnalyzer.php';
require_once __DIR__ . '/lib/UrlAnalyzer.php';
require_once dirname(__DIR__) . '/Class/AthleteScraper.php';
require_once dirname(__DIR__) . '/core/insert_athle.php';
require_once dirname(__DIR__) . '/scraping/scrape_functions.php';

$reader = new SourceTableReader($conn);
$prefix = 'u489596434_bokonzi_on';

// =========================================================================
// TABLE DE SUIVI (anti-doublon journalier + reprise sur erreur)
// =========================================================================
$conn->query("CREATE TABLE IF NOT EXISTS scraping_v2_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_name VARCHAR(255) NOT NULL,
    epreuve_id INT NOT NULL,
    action_type ENUM('verify','scrape','refresh') NOT NULL,
    athlete_id_ext INT NULL,
    status ENUM('ok','err','skip','start') NOT NULL,
    error_msg TEXT NULL,
    ran_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ep_action (epreuve_id, action_type, ran_at),
    INDEX idx_athlete (epreuve_id, action_type, athlete_id_ext, ran_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Helpers progress
function progressIsDoneToday(mysqli $conn, $tableName, $epId, $actionType) {
    $tableName = $conn->real_escape_string($tableName);
    $epId = (int)$epId;
    $actionType = $conn->real_escape_string($actionType);
    // Pour refresh ET scrape : la colonne athlete_id_ext sert au tracking unitaire
    // (athlete pour refresh, page pour scrape). La row FINALE a athlete_id_ext NULL.
    $athleteCond = ($actionType === 'refresh' || $actionType === 'scrape') ? "AND athlete_id_ext IS NULL" : "";
    $r = $conn->query("SELECT id, ran_at FROM scraping_v2_progress
                      WHERE table_name='$tableName' AND epreuve_id=$epId
                        AND action_type='$actionType' AND status='ok'
                        $athleteCond
                        AND DATE(ran_at) = CURDATE()
                      ORDER BY ran_at DESC LIMIT 1");
    return ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
}
function progressLastError(mysqli $conn, $tableName, $epId, $actionType) {
    $tableName = $conn->real_escape_string($tableName);
    $epId = (int)$epId;
    $actionType = $conn->real_escape_string($actionType);
    $r = $conn->query("SELECT error_msg, ran_at FROM scraping_v2_progress
                      WHERE table_name='$tableName' AND epreuve_id=$epId
                        AND action_type='$actionType' AND status='err'
                      ORDER BY ran_at DESC LIMIT 1");
    return ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
}
function progressRecord(mysqli $conn, $tableName, $epId, $actionType, $status, $errorMsg = null, $athleteIdExt = null) {
    $stmt = $conn->prepare("INSERT INTO scraping_v2_progress
        (table_name, epreuve_id, action_type, athlete_id_ext, status, error_msg)
        VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) return false;
    $athleteIdExt = $athleteIdExt !== null ? (int)$athleteIdExt : null;
    $stmt->bind_param('sisiss', $tableName, $epId, $actionType, $athleteIdExt, $status, $errorMsg);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
function progressAthletesOkToday(mysqli $conn, $epId) {
    $epId = (int)$epId;
    $r = $conn->query("SELECT DISTINCT athlete_id_ext FROM scraping_v2_progress
                      WHERE epreuve_id=$epId AND action_type='refresh' AND status='ok'
                        AND DATE(ran_at) = CURDATE() AND athlete_id_ext IS NOT NULL");
    $ids = [];
    if ($r) while ($row = $r->fetch_assoc()) $ids[(int)$row['athlete_id_ext']] = true;
    return $ids;
}
// Pour scrape : on reutilise la colonne athlete_id_ext pour stocker le numero de page deja faite
function progressPagesOkToday(mysqli $conn, $epId) {
    $epId = (int)$epId;
    $r = $conn->query("SELECT DISTINCT athlete_id_ext AS page_num FROM scraping_v2_progress
                      WHERE epreuve_id=$epId AND action_type='scrape' AND status='ok'
                        AND DATE(ran_at) = CURDATE() AND athlete_id_ext IS NOT NULL");
    $pages = [];
    if ($r) while ($row = $r->fetch_assoc()) $pages[(int)$row['page_num']] = true;
    return $pages;
}

// =========================================================================
// MODE API
// =========================================================================
$action = $_GET['action'] ?? '';

if ($action === 'list_annees') {
    header('Content-Type: application/json');
    $tables = array_column($reader->listerTables(), 'nom');
    $annees = [];
    foreach ($tables as $t) {
        $tSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
        $r = $conn->query("SELECT DISTINCT TRIM(SUBSTRING_INDEX(`{$prefix}_epreuve`, '|', 1)) AS y FROM `$tSafe`");
        if ($r) while ($row = $r->fetch_assoc()) {
            $y = (int)$row['y'];
            if ($y > 1900 && $y < 2100) $annees[$y] = true;
        }
    }
    $list = array_keys($annees);
    rsort($list);
    echo json_encode($list);
    exit;
}

if ($action === 'list_epreuves') {
    header('Content-Type: application/json');
    $annee = (int)($_GET['annee'] ?? 0);
    if ($annee < 1900) { echo json_encode([]); exit; }

    $tables = array_column($reader->listerTables(), 'nom');
    $out = [];

    // Pre-charge le statut du jour (verify/scrape/refresh) en 1 query.
    // Pour refresh : on regarde uniquement la row FINALE (athlete_id_ext NULL).
    // Les rows per-athlete servent a la reprise mais pas a l'affichage de statut global.
    $todayStatus = [];
    $r0 = $conn->query("SELECT epreuve_id, action_type,
                               SUM(CASE WHEN status='ok' THEN 1 ELSE 0 END) AS ok_cnt,
                               SUM(CASE WHEN status='err' THEN 1 ELSE 0 END) AS err_cnt,
                               MAX(ran_at) AS last_ran
                        FROM scraping_v2_progress
                        WHERE DATE(ran_at) = CURDATE()
                          AND (action_type = 'verify' OR athlete_id_ext IS NULL)
                        GROUP BY epreuve_id, action_type");
    if ($r0) while ($row = $r0->fetch_assoc()) {
        $todayStatus[(int)$row['epreuve_id']][$row['action_type']] = [
            'ok'    => (int)$row['ok_cnt'],
            'err'   => (int)$row['err_cnt'],
            'last'  => $row['last_ran'],
        ];
    }

    // Pour refresh/scrape : detecter aussi les runs en cours (unites traitees mais pas de row finale)
    $r1 = $conn->query("SELECT epreuve_id, action_type, COUNT(*) AS done_cnt
                        FROM scraping_v2_progress
                        WHERE DATE(ran_at) = CURDATE()
                          AND action_type IN ('refresh','scrape') AND status='ok'
                          AND athlete_id_ext IS NOT NULL
                        GROUP BY epreuve_id, action_type");
    $refreshInProgress = []; $scrapeInProgress = [];
    if ($r1) while ($row = $r1->fetch_assoc()) {
        if ($row['action_type'] === 'refresh') $refreshInProgress[(int)$row['epreuve_id']] = (int)$row['done_cnt'];
        else                                   $scrapeInProgress[(int)$row['epreuve_id']]  = (int)$row['done_cnt'];
    }

    foreach ($tables as $t) {
        $tSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
        $r = $conn->query("SELECT `{$prefix}_id` AS id,
                                  `{$prefix}_url` AS url,
                                  `{$prefix}_page_total` AS page_total,
                                  `{$prefix}_epreuve` AS epreuve,
                                  `{$prefix}_time` AS last_update
                           FROM `$tSafe`
                           WHERE `{$prefix}_epreuve` LIKE '$annee |%'
                           ORDER BY `{$prefix}_epreuve`");
        if (!$r) continue;
        while ($row = $r->fetch_assoc()) {
            // Parser "YYYY | libelle | sexe | Vent : VR"
            $parts = explode('|', $row['epreuve']);
            $libelle = isset($parts[1]) ? trim($parts[1]) : '';
            $sexe    = isset($parts[2]) ? trim($parts[2]) : '';
            $vent    = (stripos($row['url'], 'frmvent=VR') !== false) ? 'VR' : '';
            $epId    = (int)$row['id'];
            $st      = $todayStatus[$epId] ?? [];
            $out[] = [
                'id'         => $epId,
                'table'      => $tSafe,
                'libelle'    => $libelle,
                'sexe'       => $sexe,
                'vent'       => $vent,
                'page_total' => (int)$row['page_total'],
                'url'        => $row['url'],
                'last_update'=> $row['last_update'],
                'family'     => preg_replace("/^{$prefix}_/", '', $tSafe),
                'today'      => [
                    'verify'  => isset($st['verify'])  ? ($st['verify']['ok']  > 0 ? 'ok' : 'err') : null,
                    'scrape'  => isset($st['scrape'])  ? ($st['scrape']['ok']  > 0 ? 'ok' : 'err')
                                 : (isset($scrapeInProgress[$epId])  ? 'partial' : null),
                    'refresh' => isset($st['refresh']) ? ($st['refresh']['ok'] > 0 ? 'ok' : 'err')
                                 : (isset($refreshInProgress[$epId]) ? 'partial' : null),
                ],
                'scrape_done_today'  => $scrapeInProgress[$epId]  ?? 0,
                'refresh_done_today' => $refreshInProgress[$epId] ?? 0,
            ];
        }
    }
    echo json_encode($out);
    exit;
}

// ----- ACTIONS STREAMEES (SSE) -----
function sse($type, $msg) {
    echo "data: " . json_encode(['type' => $type, 'msg' => $msg, 't' => date('H:i:s')]) . "\n\n";
    @ob_flush();
    @flush();
}

// =========================================================================
// ACTION GLOBALE : verification doublons nom_et_liens (long, streamé)
// =========================================================================
if ($action === 'do_check_dup') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @set_time_limit(600);         // 10 min de marge
    @ini_set('memory_limit', '256M');
    @ini_set('max_execution_time', '600');

    function bailIfAborted() {
        if (connection_aborted()) {
            sse('err', 'Client deconnecte — arret propre (aucune ecriture en cours)');
            exit;
        }
    }

    sse('info', '=== VERIFICATION DOUBLONS nom_et_liens ===');
    sse('info', 'Operation lecture seule (aucun risque pour les donnees)');

    // --- 1) Compte total (rapide, < 1s) ---
    sse('info', '[1/5] Comptage total des lignes...');
    $t0 = microtime(true);
    $r = $conn->query("SELECT COUNT(*) AS c FROM nom_et_liens");
    $total = $r ? (int)$r->fetch_assoc()['c'] : 0;
    sse('ok', "  Total : " . number_format($total) . " lignes (" . round(microtime(true)-$t0, 2) . "s)");
    bailIfAborted();

    // --- 2) Compte distinct (lent, full scan colonne url) ---
    sse('info', '[2/5] Comptage URLs uniques (peut prendre 30s a 2min)...');
    $t0 = microtime(true);
    $r = $conn->query("SELECT COUNT(DISTINCT url) AS c FROM nom_et_liens");
    $uniques = $r ? (int)$r->fetch_assoc()['c'] : 0;
    $elapsed = round(microtime(true)-$t0, 2);
    sse('ok', "  Uniques : " . number_format($uniques) . " URLs ({$elapsed}s)");
    bailIfAborted();

    // --- 3) Calcul doublons ---
    $doublons = $total - $uniques;
    $pct = $total > 0 ? round($doublons / $total * 100, 2) : 0;
    sse('info', "[3/5] Resultat : " . number_format($doublons) . " doublons ({$pct}% de la table)");
    bailIfAborted();

    // --- 4) Top 10 URLs les plus dupliquees (utile pour comprendre) ---
    if ($doublons > 0) {
        sse('info', '[4/5] Top 10 URLs les plus dupliquees...');
        $t0 = microtime(true);
        $r = $conn->query("SELECT url, COUNT(*) AS cnt FROM nom_et_liens
                          GROUP BY url HAVING cnt > 1 ORDER BY cnt DESC LIMIT 10");
        if ($r) {
            $i = 0;
            while ($row = $r->fetch_assoc()) {
                $i++;
                sse('ok', "  $i. " . $row['cnt'] . "x : " . $row['url']);
                if ($i % 3 === 0) bailIfAborted();
            }
        }
        sse('info', "  (top 10 en " . round(microtime(true)-$t0, 2) . "s)");
    } else {
        sse('ok', '[4/5] Aucun doublon — table propre');
    }
    bailIfAborted();

    // --- 5) Etat de la protection UNIQUE ---
    sse('info', '[5/5] Verification de la protection UNIQUE...');
    $r = $conn->query("SHOW INDEX FROM nom_et_liens WHERE Non_unique = 0 AND Column_name = 'url'");
    $hasUnique = $r && $r->num_rows > 0;
    if ($hasUnique) {
        sse('ok', "  UNIQUE KEY active sur url — protection anti-doublon en place");
    } else {
        sse('err', "  UNIQUE KEY ABSENTE sur url — nouveaux doublons possibles a chaque scrape");
        sse('info', "  → Pour proteger : admin/dedup_nom_et_liens.php?bk_key=...&step=alter (apres step=delete)");
    }

    // Resume final
    if ($doublons > 0) {
        sse('err', "BILAN : $doublons doublons a nettoyer. Lance admin/dedup_nom_et_liens.php?bk_key=... pour la procedure complete.");
    } else {
        sse('ok', "BILAN : table propre, " . number_format($total) . " URLs uniques.");
    }
    sse('done', 'Verification terminee');
    exit;
}

if ($action === 'do_verify' || $action === 'do_scrape' || $action === 'do_refresh') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @set_time_limit($action === 'do_refresh' ? 300 : 120);
    // Pas de ignore_user_abort : si le browser ferme, le script s'arrete proprement.
    // La progression est deja persistee row par row → on peut reprendre en re-cliquant.

    $id    = (int)($_GET['id'] ?? 0);
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['table'] ?? '');
    $force = !empty($_GET['force']);
    $actionType = substr($action, 3); // "verify" / "scrape" / "refresh"
    if ($id < 1 || $table === '') { sse('err', 'Parametres invalides'); exit; }

    // === Anti-doublon : skip si deja fait OK aujourd'hui (sauf force=1) ===
    if (!$force) {
        $alreadyDone = progressIsDoneToday($conn, $table, $id, $actionType);
        if ($alreadyDone) {
            sse('info', "Deja fait aujourd'hui (a " . $alreadyDone['ran_at'] . ")");
            sse('done', "SKIP — ajoute &force=1 a l'URL pour relancer quand meme");
            exit;
        }
    } else {
        sse('info', "Mode FORCE actif (ignore l'historique du jour)");
    }

    // === Capture des fatals pour qu'on sache POURQUOI ca s'est arrete ===
    $shutdownArgs = ['table' => $table, 'id' => $id, 'action_type' => $actionType, 'fired' => false];
    register_shutdown_function(function() use (&$shutdownArgs, $conn) {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $msg = "FATAL " . $err['message'] . " @ " . basename($err['file']) . ":" . $err['line'];
            echo "data: " . json_encode(['type' => 'err', 'msg' => $msg, 't' => date('H:i:s')]) . "\n\n";
            @flush();
            progressRecord($conn, $shutdownArgs['table'], $shutdownArgs['id'], $shutdownArgs['action_type'], 'err', $msg);
        }
    });

    $r = $conn->query("SELECT `{$prefix}_url` AS url, `{$prefix}_page_total` AS pt, `{$prefix}_epreuve` AS ep
                       FROM `$table` WHERE `{$prefix}_id` = $id LIMIT 1");
    if (!$r || !($row = $r->fetch_assoc())) {
        sse('err', 'Epreuve non trouvee');
        progressRecord($conn, $table, $id, $actionType, 'err', 'Epreuve non trouvee en BDD');
        exit;
    }
    $url    = $row['url'];
    $oldPt  = (int)$row['pt'];
    $label  = $row['ep'];

    sse('info', "Epreuve : $label");
    sse('info', "Page total actuel en BDD : $oldPt");
    sse('info', "URL : " . substr($url, 0, 80) . "...");

    // Afficher la derniere erreur en date (si existe) pour aider au debug
    $lastErr = progressLastError($conn, $table, $id, $actionType);
    if ($lastErr) {
        sse('info', "Derniere erreur connue (" . $lastErr['ran_at'] . ") : " . $lastErr['error_msg']);
    }

    $pa = new PageAnalyzer();

    // ============================================
    // VERIFIER : bruteforce page_total (athle.fr 2025+ : frmposition = page-1 direct)
    // ============================================
    if ($action === 'do_verify') {
        sse('info', "[1/4] Demarrage VERIFICATION pour cette epreuve...");
        sse('info', "[2/4] Methode : on incremente frmposition (0, 1, 2, ...) jusqu'a page vide.");

        // 1) On tente d'abord de lire la pagination depuis la page 1 (rapide)
        sse('info', "[3/4] Test page 1 pour detecter la pagination native d'athle.fr...");
        $firstUrl = preg_replace('/frmposition=\d+/', "frmposition=0", $url);
        $rFirst = $pa->analyze($firstUrl);
        $detectedFromHtml = (int)($rFirst['pagination']['total_pages'] ?? 0);
        $nbFirst = count($rFirst['athletes'] ?? []);
        sse('ok', "  → Page 1 : $nbFirst athletes detectes, pagination HTML : " . ($detectedFromHtml ?: '(absente)'));

        if ($nbFirst === 0) {
            sse('info', "Page 1 vide → cette epreuve n'a aucun resultat en $oldPt");
            if (0 !== $oldPt) {
                $conn->query("UPDATE `$table` SET `{$prefix}_page_total` = '0' WHERE `{$prefix}_id` = $id");
                sse('done', "MAJ BDD : $oldPt → 0 (epreuve sans data)");
            } else {
                sse('done', "Page total deja a 0 — aucune MAJ");
            }
            progressRecord($conn, $table, $id, 'verify', 'ok', "page_total=0 (vide)");
            exit;
        }

        // Si la pagination HTML est fiable, on l'utilise (rapide)
        if ($detectedFromHtml > 0) {
            sse('ok', "[4/4] Pagination HTML detectee : $detectedFromHtml pages — verification croisee sur derniere page...");
            // Test la derniere page pour confirmer
            $lastUrl = preg_replace('/frmposition=\d+/', "frmposition=" . ($detectedFromHtml - 1), $url);
            $rLast = $pa->analyze($lastUrl);
            $nbLast = count($rLast['athletes'] ?? []);
            sse('ok', "  → Page $detectedFromHtml (derniere) : $nbLast athletes");
            if ($nbLast > 0) {
                if ($detectedFromHtml !== $oldPt) {
                    $conn->query("UPDATE `$table` SET `{$prefix}_page_total` = '$detectedFromHtml' WHERE `{$prefix}_id` = $id");
                    sse('done', "MAJ BDD : $oldPt → $detectedFromHtml pages (via pagination HTML, confirmee)");
                } else {
                    sse('done', "Page total deja correct ($oldPt) — aucune MAJ necessaire");
                }
                progressRecord($conn, $table, $id, 'verify', 'ok', "page_total=$detectedFromHtml");
                exit;
            }
            sse('info', "  Derniere page vide — fallback en bruteforce...");
        }

        // 2) Bruteforce : incremente page par page jusqu'a vide
        sse('info', "[4/4] Bruteforce en cours (incrementation page par page)...");
        $maxGuard = 300;
        $lastNonEmpty = 1; // page 1 deja confirmee non-vide
        sse('ok', "  → Page 1 : $nbFirst athletes (deja teste)");

        for ($p = 2; $p <= $maxGuard; $p++) {
            $pos = $p - 1; // 0-indexed
            $testUrl = preg_replace('/frmposition=\d+/', "frmposition=$pos", $url);
            sse('info', "  ⤷ Test page $p (frmposition=$pos)...");
            $r2 = $pa->analyze($testUrl);
            if (!$r2['success']) {
                sse('err', "  Page $p : HTTP " . ($r2['http_code'] ?? '???') . " — STOP");
                break;
            }
            $nb = count($r2['athletes'] ?? []);
            if ($nb === 0) {
                sse('info', "  Page $p : VIDE → derniere page valide = $lastNonEmpty");
                break;
            }
            $lastNonEmpty = $p;
            sse('ok', "  Page $p : $nb athletes");
            usleep(200000);
        }

        if ($lastNonEmpty !== $oldPt) {
            $sql = "UPDATE `$table` SET `{$prefix}_page_total` = '$lastNonEmpty' WHERE `{$prefix}_id` = $id";
            if ($conn->query($sql)) {
                sse('done', "MAJ BDD : $oldPt → $lastNonEmpty pages");
                progressRecord($conn, $table, $id, 'verify', 'ok', "page_total=$lastNonEmpty (bruteforce)");
            } else {
                sse('err', "Erreur SQL : " . $conn->error);
                progressRecord($conn, $table, $id, 'verify', 'err', "SQL: " . $conn->error);
            }
        } else {
            sse('done', "Page total deja correct ($oldPt) — aucune MAJ necessaire");
            progressRecord($conn, $table, $id, 'verify', 'ok', "page_total inchange ($oldPt)");
        }
        exit;
    }

    // ============================================
    // SCRAPER : visite chaque page → INSERT URLs athletes
    // ============================================
    if ($action === 'do_scrape') {
        if ($oldPt < 1) {
            sse('err', "page_total = 0, lance VERIFIER d'abord");
            progressRecord($conn, $table, $id, 'scrape', 'err', 'page_total=0, VERIFIER requis');
            exit;
        }
        // RESUMABLE : skip les pages deja faites OK aujourd'hui
        $pagesAlreadyOk = $force ? [] : progressPagesOkToday($conn, $id);
        $alreadyCount = count($pagesAlreadyOk);
        $todoCount = $oldPt - $alreadyCount;

        sse('info', "[1/3] Demarrage SCRAPING — $oldPt pages au total"
            . ($alreadyCount > 0 ? " ($alreadyCount deja faites aujourd'hui → reprise auto, $todoCount restantes)" : "")
            . ($force ? " [FORCE actif]" : ""));
        sse('info', "[2/3] Methode : pour chaque page, on extrait les URLs athletes -> INSERT dans nom_et_liens");
        sse('info', "[3/3] Calcul frmposition : page 1=0, page 2=1, ... (0-indexed athle.fr 2025+)");

        if ($todoCount === 0) {
            sse('done', "TERMINE — toutes les $oldPt pages deja faites aujourd'hui (force=1 pour refaire)");
            progressRecord($conn, $table, $id, 'scrape', 'ok', "Rien a faire (deja tout fait : $oldPt pages)");
            exit;
        }

        // INSERT IGNORE : si une UNIQUE constraint est ajoutee sur url, les doublons
        // sont silencieusement ignores. Compatible avant ET apres l'ALTER UNIQUE.
        $stmt = $conn->prepare("INSERT IGNORE INTO nom_et_liens (url) VALUES (?)");
        if (!$stmt) {
            sse('err', 'PREPARE failed');
            progressRecord($conn, $table, $id, 'scrape', 'err', 'PREPARE INSERT nom_et_liens failed');
            exit;
        }
        $totalInserts = 0; $pagesFailed = 0; $pagesDone = 0; $duplicatesSkipped = 0;

        for ($p = 1; $p <= $oldPt; $p++) {
            if (isset($pagesAlreadyOk[$p])) {
                sse('info', "  ⤷ Page $p/$oldPt — deja faite aujourd'hui, SKIP");
                continue;
            }
            // Heartbeat
            if (connection_aborted()) {
                sse('err', "Client deconnecte — arret propre (pages deja faites = sauvees, reprise possible)");
                break;
            }
            $pos = $p - 1; // 0-indexed
            $pageUrl = preg_replace('/frmposition=\d+/', "frmposition=$pos", $url);
            sse('info', "  ⤷ Page $p/$oldPt — fetch en cours (frmposition=$pos)...");
            // Retry avec backoff exponentiel (transient errors athle.fr)
            $r2 = $pa->analyze($pageUrl);
            $retryCount = 0;
            while (!$r2['success'] && $retryCount < 2) {
                $retryCount++;
                $waitSec = 2 * $retryCount; // 2s, 4s
                sse('info', "  Page $p : echec (HTTP " . ($r2['http_code'] ?? '???') . "), attente {$waitSec}s puis retry $retryCount/2...");
                sleep($waitSec);
                $r2 = $pa->analyze($pageUrl);
            }
            if (!$r2['success']) {
                $errMsg = "HTTP " . ($r2['http_code'] ?? '???') . " (apres $retryCount retries)";
                sse('err', "  Page $p : $errMsg — skip");
                progressRecord($conn, $table, $id, 'scrape', 'err', "Page $p: $errMsg", $p);
                $pagesFailed++;
                continue;
            }
            if ($retryCount > 0) sse('ok', "  Page $p : succes apres $retryCount retry(s)");
            $nb = count($r2['athletes']);
            $ins = 0; $dup = 0;
            foreach ($r2['athletes'] as $a) {
                $u = "https://athle.fr" . $a['url_fiche'];
                $stmt->bind_param('s', $u);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) { $ins++; $totalInserts++; }
                    else { $dup++; $duplicatesSkipped++; } // doublon ignore par INSERT IGNORE
                }
            }
            sse('ok', "  Page $p/$oldPt : $nb athletes → $ins ajoutes, $dup doublons ignores");
            // Persiste la page comme faite OK (resume possible)
            progressRecord($conn, $table, $id, 'scrape', 'ok', "Page $p: $nb athletes, $ins inserts", $p);
            $pagesDone++;
            usleep(300000); // anti-ban
        }
        $stmt->close();

        $remainingPages = $todoCount - $pagesDone - $pagesFailed;
        if ($remainingPages > 0) {
            sse('err', "ARRET PARTIEL — fait=$pagesDone err=$pagesFailed restant=$remainingPages. Re-clique SCRAPER pour reprendre.");
            progressRecord($conn, $table, $id, 'scrape', 'err',
                "Partiel: fait=$pagesDone err=$pagesFailed restant=$remainingPages sur $oldPt");
        } else {
            sse('done', "TERMINE — $totalInserts URLs nouvelles, $duplicatesSkipped doublons ignores (pages OK: $pagesDone, erreurs: $pagesFailed)");
            $finalStatus = ($pagesFailed >= $oldPt) ? 'err' : 'ok';
            progressRecord($conn, $table, $id, 'scrape', $finalStatus,
                "FIN: nouvelles=$totalInserts, doublons=$duplicatesSkipped, pages_ok=$pagesDone, pages_err=$pagesFailed/$oldPt");
        }
        exit;
    }

    // ============================================
    // MAJ profils : re-scrape les athletes apparus pour cette epreuve
    // RESUMABLE : skip ceux deja faits OK aujourd'hui, record par athlete
    // ============================================
    if ($action === 'do_refresh') {
        sse('info', "Recherche des athletes de cette epreuve dans nom_et_liens...");

        $sinceDate = !empty($row['ep']) ? date('Y-m-d', strtotime('-30 days')) : date('Y-m-d');
        $escDate = $conn->real_escape_string($sinceDate);
        $q = $conn->query("SELECT DISTINCT
                CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(url, '/athletes/', -1), '/', 1) AS UNSIGNED) AS id_ext
            FROM nom_et_liens
            WHERE nom_et_liens_date >= '$escDate'
              AND url REGEXP '/athletes/[0-9]+/'
            ORDER BY id_ext
            LIMIT 1000"); // safety cap
        $allIds = [];
        if ($q) while ($row2 = $q->fetch_assoc()) {
            $idExt = (int)$row2['id_ext'];
            if ($idExt > 0) $allIds[] = $idExt;
        }
        $totalAll = count($allIds);

        // === Skip ceux deja faits OK aujourd'hui (reprise apres crash/timeout) ===
        $alreadyOk = $force ? [] : progressAthletesOkToday($conn, $id);
        $ids = [];
        foreach ($allIds as $idExt) {
            if (!isset($alreadyOk[$idExt])) $ids[] = $idExt;
        }
        $skippedCount = $totalAll - count($ids);
        sse('info', "Trouve $totalAll athletes ; $skippedCount deja traites OK aujourd'hui → " . count($ids) . " a faire");

        if (empty($ids)) {
            sse('done', "Aucun athlete restant — tout deja fait aujourd'hui");
            progressRecord($conn, $table, $id, 'refresh', 'ok', "Rien a faire (deja tout traite : $totalAll)");
            exit;
        }

        $cache = loadRefCache($conn);
        $done = 0; $okCnt = 0; $errCnt = 0;
        $lastErrMsg = null;
        $totalToDo = count($ids);

        foreach (array_chunk($ids, 4) as $batchIdx => $batch) {
            // Heartbeat : si client deconnecte, on s'arrete proprement (mais sinon on continue)
            if (connection_aborted()) {
                sse('err', "Connexion client perdue — arret propre (les athletes deja traites sont sauves en BDD)");
                break;
            }
            $pages = scrapeParallel($batch);

            // Retry des fetch echoues du batch (athle.fr transient errors)
            $missingIds = [];
            foreach ($batch as $idExt) {
                if (empty($pages[$idExt]['bilans'])) $missingIds[] = $idExt;
            }
            if (!empty($missingIds)) {
                sse('info', "  Batch: " . count($missingIds) . "/" . count($batch) . " echecs fetch, attente 3s puis retry...");
                sleep(3);
                $retryPages = scrapeParallel($missingIds);
                foreach ($missingIds as $idExt) {
                    if (!empty($retryPages[$idExt]['bilans'])) $pages[$idExt] = $retryPages[$idExt];
                }
                $stillMissing = 0;
                foreach ($missingIds as $idExt) if (empty($pages[$idExt]['bilans'])) $stillMissing++;
                if ($stillMissing < count($missingIds)) {
                    sse('ok', "  Retry: " . (count($missingIds) - $stillMissing) . " recuperes apres retry");
                }
            }

            foreach ($batch as $idExt) {
                $pgs = $pages[$idExt] ?? null;
                if (!$pgs || empty($pgs['bilans'])) {
                    sse('err', "id=$idExt FETCH FAIL (apres retry)");
                    progressRecord($conn, $table, $id, 'refresh', 'err', 'FETCH FAIL apres retry (bilans vide)', $idExt);
                    $errCnt++; $done++; $lastErrMsg = "id=$idExt FETCH FAIL";
                    continue;
                }
                try {
                    $rExist = $conn->query("SELECT id_athlete FROM athletes WHERE athlete_id_externe = $idExt LIMIT 1");
                    $tag = ($rExist && $rExist->num_rows > 0) ? 'MAJ' : 'NEW';
                    $s = new AthleteScraper($idExt);
                    $s->html = $pgs['bilans'];
                    $s->extractIdentite(); $s->extractMedailles(); $s->extractProgressions();
                    $s->extractClubs(); $s->extractPodiums(); $s->extractResultats(); $s->extractNiveaux();
                    if (!empty($pgs['records'])) { $s->html = $pgs['records']; $s->extractRecords(); }
                    if (!empty($pgs['selections'])) { $s->html = $pgs['selections']; $s->extractSelections(); }

                    ob_start();
                    insertAthleteData($s, $conn, $cache);
                    ob_end_clean();

                    sse('ok', "[" . ($done+1) . "/$totalToDo] id=$idExt $tag");
                    progressRecord($conn, $table, $id, 'refresh', 'ok', $tag, $idExt);
                    $okCnt++;
                } catch (Throwable $e) {
                    $msg = $e->getMessage() . " @ " . basename($e->getFile()) . ":" . $e->getLine();
                    sse('err', "[" . ($done+1) . "/$totalToDo] id=$idExt ERR $msg");
                    progressRecord($conn, $table, $id, 'refresh', 'err', $msg, $idExt);
                    $errCnt++;
                    $lastErrMsg = "id=$idExt : $msg";
                }
                $done++;
            }
            usleep(200000);
        }

        $remaining = $totalToDo - $done;
        $summary = "ok=$okCnt err=$errCnt skipped_today=$skippedCount restant=$remaining";
        if ($remaining > 0) {
            sse('err', "ARRET PARTIEL — $summary. Re-clique MAJ profils pour reprendre (les $okCnt deja OK seront skippes auto)");
            progressRecord($conn, $table, $id, 'refresh', 'err',
                "Partiel: $summary" . ($lastErrMsg ? " | derniere err: $lastErrMsg" : ''));
        } else {
            sse('done', "TERMINE — $summary");
            // Marquer l'epreuve comme OK globalement seulement si tout est passe (ou au moins majoritairement)
            $globalStatus = ($errCnt > 0 && $okCnt === 0) ? 'err' : 'ok';
            progressRecord($conn, $table, $id, 'refresh', $globalStatus, "FIN: $summary");
        }
        exit;
    }
}

// =========================================================================
// MODE UI
// =========================================================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Scraping v2 — Par epreuve</title>
    <style>
    * { box-sizing:border-box; }
    body { font-family:'Inter',sans-serif; background:#0d1117; color:#c9d1d9; padding:24px; max-width:1200px; margin:auto; }
    h1 { color:#a78bfa; margin:0 0 4px; }
    .sub { color:#8b949e; font-size:13px; margin-bottom:16px; }
    .card { background:#161b22; border:1px solid #1f2937; border-radius:10px; padding:14px; margin-bottom:14px; }
    select { background:#0a0e15; color:#fff; border:1px solid #30363d; padding:8px 12px; border-radius:6px; font-size:14px; }
    table { width:100%; border-collapse:collapse; font-size:13px; }
    th, td { padding:6px 10px; border-bottom:1px solid #1f2937; text-align:left; }
    th { color:#8b949e; text-transform:uppercase; font-size:11px; letter-spacing:1px; }
    tr:hover { background:#1e2a3a; }
    .btn { background:#374151; color:#fff; border:none; padding:5px 12px; border-radius:5px; cursor:pointer; font-size:11px; margin-right:4px; }
    .btn:hover { opacity:.85; }
    .btn-verify { background:#60a5fa; }
    .btn-scrape { background:#6366f1; }
    .btn-refresh { background:#10b981; }
    .badge { display:inline-block; padding:2px 6px; border-radius:8px; font-size:10px; font-weight:600; }
    .badge-h { background:#1e3a8a; color:#93c5fd; }
    .badge-f { background:#831843; color:#fbcfe8; }
    .stat-dot { display:inline-block; width:18px; height:18px; line-height:18px; text-align:center; border-radius:4px; font-size:10px; font-weight:700; margin-right:2px; color:#4b5563; background:#1f2937; cursor:help; }
    .stat-ok      { background:#065f46; color:#34d399; }
    .stat-err     { background:#7f1d1d; color:#fca5a5; }
    .stat-partial { background:#78350f; color:#fbbf24; }
    #console { background:#000; color:#0f0; font-family:'Courier New',monospace; font-size:12px; padding:14px; border-radius:8px; max-height:400px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; line-height:1.5; }
    #console .log-info  { color:#60a5fa; }
    #console .log-ok    { color:#34d399; }
    #console .log-err   { color:#f87171; }
    #console .log-done  { color:#a78bfa; font-weight:bold; }
    .pulse { display:inline-block; width:8px; height:8px; border-radius:50%; background:#34d399; animation:pulse 1.2s infinite; vertical-align:middle; }
    @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }
    .console-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
    .filter-input { background:#0a0e15; color:#fff; border:1px solid #30363d; padding:6px 10px; border-radius:6px; width:200px; }
    </style>
</head>
<body>
    <h1>Scraping v2 — Par epreuve</h1>
    <p class="sub">
        Choisis une annee, puis l'epreuve precise a traiter. Chaque clic = 1 action ciblee.
        <a style="color:#60a5fa;" href="par_annee.php">Mode par annee (tout)</a>
    </p>

    <div class="card">
        <label>Annee : <select id="sel-annee"><option value="">...</option></select></label>
        &nbsp;&nbsp;
        <label>Filtre : <input type="text" class="filter-input" id="filter" placeholder="ex: 100m, perche, M, F..."></label>
        &nbsp;&nbsp;
        <label style="color:#fbbf24;font-size:12px;">
            <input type="checkbox" id="chk-force" style="vertical-align:middle;"> Forcer (refaire meme si deja fait aujourd'hui)
        </label>
        &nbsp;&nbsp;
        <label style="color:#34d399;font-size:12px;">
            <input type="checkbox" id="chk-skip-done" checked style="vertical-align:middle;"> Masquer celles deja faites
        </label>
        &nbsp;&nbsp;
        <span id="count" style="color:#8b949e;font-size:12px;"></span>
    </div>

    <!-- ACTIONS GLOBALES : TOUT FAIRE -->
    <div class="card" style="border-color:#dc2626;background:#1a0a0a;">
        <h3 style="margin:0 0 10px;color:#fca5a5;font-size:14px;">⚠ Actions globales (toutes les epreuves visibles)</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <button class="btn" style="background:#374151;" onclick="selectAllVisible()">TOUT COCHER</button>
            <button class="btn" style="background:#374151;" onclick="clearSelection()">TOUT DECOCHER</button>
            <span style="color:#8b949e;">|</span>
            <button class="btn btn-verify"  onclick="runAll('verify')">TOUT VERIFIER</button>
            <button class="btn btn-scrape"  onclick="runAll('scrape')">TOUT SCRAPER</button>
            <button class="btn btn-refresh" onclick="runAll('refresh')">TOUT MAJ profils</button>
        </div>
        <p style="color:#8b949e;font-size:11px;margin:8px 0 0;">
            Applique l'action a toutes les epreuves <b>visibles</b> (filtre actif inclus). Tu peux ARRETER le batch a tout moment.
        </p>
    </div>

    <!-- VERIFICATION DOUBLONS (lecture seule, long) -->
    <div class="card" style="border-color:#fbbf24;background:#1a1408;">
        <h3 style="margin:0 0 10px;color:#fde68a;font-size:14px;">🔍 Diagnostic doublons nom_et_liens</h3>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <button class="btn" style="background:#fbbf24;color:#000;" onclick="checkDuplicates()">VERIFIER DOUBLONS</button>
            <span style="color:#8b949e;font-size:11px;">
                Lecture seule. Operation longue (30s a 2min) selon la taille de la table. Ne modifie rien — affiche juste le compteur + top 10 + etat de la protection UNIQUE.
            </span>
        </div>
    </div>

    <!-- BARRE D'ACTIONS GROUPEES -->
    <div class="card" id="bulk-bar" style="display:none;border-color:#a78bfa;">
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <span style="color:#a78bfa;font-weight:600;"><b id="bulk-count">0</b> selectionnees</span>
            <button class="btn btn-verify"  onclick="bulkRun('verify')">VERIFIER selection</button>
            <button class="btn btn-scrape"  onclick="bulkRun('scrape')">SCRAPER selection</button>
            <button class="btn btn-refresh" onclick="bulkRun('refresh')">MAJ profils selection</button>
            <button class="btn" style="background:#dc2626;" id="bulk-stop" style="display:none;" onclick="bulkStop()">ARRETER batch</button>
            <button class="btn" style="background:#374151;margin-left:auto;" onclick="clearSelection()">Vider selection</button>
        </div>
        <!-- Barre de progression batch -->
        <div id="bulk-progress" style="display:none;margin-top:10px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#8b949e;margin-bottom:4px;">
                <span id="bulk-progress-label">Traitement en cours...</span>
                <span id="bulk-progress-pct">0%</span>
            </div>
            <div style="height:18px;background:#1a2540;border-radius:9px;overflow:hidden;">
                <div id="bulk-progress-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#34d399,#a78bfa);transition:width .3s;"></div>
            </div>
            <div style="font-size:11px;color:#5a6580;margin-top:4px;text-align:center;" id="bulk-progress-detail">
                en attente...
            </div>
        </div>
    </div>

    <div class="card">
        <table id="tbl">
            <thead><tr>
                <th style="width:30px;"><input type="checkbox" id="chk-all" onchange="toggleAll(this.checked)"></th>
                <th>Epreuve</th><th>Sexe</th><th>Vent</th><th>Pages BDD</th><th>Famille</th>
                <th title="Statut du jour : V=verify, S=scrape, M=MAJ profils. Vert=OK, Rouge=erreur, Orange=partiel">Jour</th>
                <th>Derniere MAJ</th><th>Actions</th>
            </tr></thead>
            <tbody id="rows"><tr><td colspan="9" style="text-align:center;color:#8b949e;">Selectionne une annee.</td></tr></tbody>
        </table>
    </div>

    <!-- URL en cours (toujours visible, gros) -->
    <div class="card" id="current-url-card" style="display:none;border-color:#fbbf24;background:#1c1408;">
        <div style="display:flex;align-items:center;gap:12px;">
            <span class="pulse" style="background:#fbbf24;"></span>
            <span style="color:#fbbf24;font-size:11px;text-transform:uppercase;letter-spacing:2px;font-weight:600;">URL en cours</span>
        </div>
        <div id="current-url-action" style="color:#fde68a;font-size:14px;font-weight:600;margin-top:6px;">—</div>
        <div id="current-url" style="background:#0a0e15;padding:8px 12px;border-radius:6px;margin-top:8px;font-family:'Courier New',monospace;font-size:11px;color:#a78bfa;word-break:break-all;line-height:1.5;">—</div>
        <div id="current-url-step" style="color:#8b949e;font-size:12px;margin-top:6px;">—</div>
    </div>

    <div class="card">
        <div class="console-bar">
            <div>
                <b style="color:#34d399;" id="console-title">Console live</b>
                <span id="console-pulse" style="display:none;"><span class="pulse"></span> en cours...</span>
            </div>
            <button class="btn" onclick="document.getElementById('console').textContent='';">Vider</button>
        </div>
        <div id="console">En attente d'une action...</div>
    </div>

<script>
const sel = document.getElementById('sel-annee');
const rows = document.getElementById('rows');
const cnt = document.getElementById('count');
const filterInput = document.getElementById('filter');
const consoleEl = document.getElementById('console');
const consolePulse = document.getElementById('console-pulse');
const consoleTitle = document.getElementById('console-title');

let currentEpreuves = [];
let currentEventSrc = null;
let selectedIds = new Set(); // IDs des epreuves cochees
let bulkQueue = null;          // Liste des epreuves en cours de traitement batch
let bulkVerb = null;
let bulkStopRequested = false;

function log(type, msg, t) {
    const cls = type === 'info' ? 'log-info' : type === 'ok' ? 'log-ok' : type === 'err' ? 'log-err' : 'log-done';
    const ts = t || new Date().toLocaleTimeString();
    consoleEl.insertAdjacentHTML('beforeend', `<span class="${cls}">[${ts}] ${msg}</span>\n`);
    consoleEl.scrollTop = consoleEl.scrollHeight;
}

function clearConsole() { consoleEl.textContent = ''; }

function setBusy(busy, title) {
    consolePulse.style.display = busy ? 'inline-block' : 'none';
    if (title) consoleTitle.textContent = title;
}

// === Chargement des annees ===
fetch('?action=list_annees').then(r => r.json()).then(data => {
    sel.innerHTML = '<option value="">-- choisir --</option>' + data.map(a => `<option value="${a}">${a}</option>`).join('');
    // Pre-selectionne l'annee la plus recente
    if (data[0]) { sel.value = data[0]; loadEpreuves(data[0]); }
});

sel.addEventListener('change', () => loadEpreuves(sel.value));
filterInput.addEventListener('input', () => renderRows());

function loadEpreuves(annee) {
    if (!annee) { rows.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#8b949e;">Selectionne une annee.</td></tr>'; cnt.textContent = ''; return; }
    rows.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#8b949e;">Chargement...</td></tr>';
    fetch('?action=list_epreuves&annee=' + annee).then(r => r.json()).then(data => {
        currentEpreuves = data;
        renderRows();
    });
}

function statDot(letter, state, title) {
    const cls = state === 'ok' ? 'stat-ok' : state === 'err' ? 'stat-err' : state === 'partial' ? 'stat-partial' : '';
    return `<span class="stat-dot ${cls}" title="${title}">${letter}</span>`;
}

function isAllDoneToday(e) {
    const t = e.today || {};
    return t.verify === 'ok' && t.scrape === 'ok' && t.refresh === 'ok';
}

function renderRows() {
    const filter = filterInput.value.toLowerCase().trim();
    const skipDone = document.getElementById('chk-skip-done').checked;
    let list = filter ? currentEpreuves.filter(e =>
        e.libelle.toLowerCase().includes(filter) ||
        e.sexe.toLowerCase() === filter ||
        e.family.toLowerCase().includes(filter)
    ) : currentEpreuves;
    if (skipDone) list = list.filter(e => !isAllDoneToday(e));
    cnt.textContent = list.length + ' / ' + currentEpreuves.length + ' epreuves';
    if (list.length === 0) {
        rows.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#8b949e;">Aucune epreuve.</td></tr>';
        return;
    }
    rows.innerHTML = list.map(e => {
        const sxClass = e.sexe === 'M' ? 'badge-h' : 'badge-f';
        const checked = selectedIds.has(e.id) ? 'checked' : '';
        const t = e.today || {};
        const refreshPart = t.refresh === 'partial' && e.refresh_done_today
            ? ` (${e.refresh_done_today} athletes faits, reprise auto)` : '';
        const scrapePart  = t.scrape === 'partial' && e.scrape_done_today
            ? ` (${e.scrape_done_today} pages faites, reprise auto)` : '';
        const statusHtml = statDot('V', t.verify,  'VERIFIER : ' + (t.verify  || 'jamais aujourd\'hui'))
                        + statDot('S', t.scrape,  'SCRAPER : '  + (t.scrape  || 'jamais aujourd\'hui') + scrapePart)
                        + statDot('M', t.refresh, 'MAJ profils : ' + (t.refresh || 'jamais aujourd\'hui') + refreshPart);
        return `<tr data-id="${e.id}">
            <td><input type="checkbox" class="row-chk" value="${e.id}" ${checked} onchange="toggleOne(${e.id}, this.checked)"></td>
            <td><b>${e.libelle}</b></td>
            <td><span class="badge ${sxClass}">${e.sexe}</span></td>
            <td>${e.vent ? '<span class="badge" style="background:#374151;color:#9ca3af;">VR</span>' : ''}</td>
            <td><b>${e.page_total}</b></td>
            <td style="color:#8b949e;font-size:11px;">${e.family}</td>
            <td style="white-space:nowrap;">${statusHtml}</td>
            <td style="color:#8b949e;font-size:11px;">${e.last_update || ''}</td>
            <td>
                <button class="btn btn-verify"  onclick="doAction('verify',  ${e.id}, '${e.table}', '${escapeJs(e.libelle)} ${e.sexe}')">VERIFIER</button>
                <button class="btn btn-scrape"  onclick="doAction('scrape',  ${e.id}, '${e.table}', '${escapeJs(e.libelle)} ${e.sexe}')">SCRAPER</button>
                <button class="btn btn-refresh" onclick="doAction('refresh', ${e.id}, '${e.table}', '${escapeJs(e.libelle)} ${e.sexe}')">MAJ profils</button>
            </td>
        </tr>`;
    }).join('');
    updateBulkBar();
}

// Re-render quand on toggle "Masquer celles deja faites"
document.addEventListener('DOMContentLoaded', () => {
    const chk = document.getElementById('chk-skip-done');
    if (chk) chk.addEventListener('change', renderRows);
});

// === Selection ===
function toggleOne(id, checked) {
    if (checked) selectedIds.add(id); else selectedIds.delete(id);
    updateBulkBar();
}
function toggleAll(checked) {
    // Coche/decoche uniquement les LIGNES VISIBLES (filtre courant)
    document.querySelectorAll('.row-chk').forEach(chk => {
        const id = parseInt(chk.value);
        chk.checked = checked;
        if (checked) selectedIds.add(id); else selectedIds.delete(id);
    });
    updateBulkBar();
}
function clearSelection() {
    selectedIds.clear();
    document.querySelectorAll('.row-chk').forEach(chk => chk.checked = false);
    document.getElementById('chk-all').checked = false;
    updateBulkBar();
}
function updateBulkBar() {
    const bar = document.getElementById('bulk-bar');
    const count = selectedIds.size;
    document.getElementById('bulk-count').textContent = count;
    bar.style.display = count > 0 ? 'block' : 'none';
}

// === Tout cocher (visibles uniquement) ===
function selectAllVisible() {
    document.querySelectorAll('.row-chk').forEach(chk => {
        const id = parseInt(chk.value);
        chk.checked = true;
        selectedIds.add(id);
    });
    document.getElementById('chk-all').checked = true;
    updateBulkBar();
}

// === TOUT FAIRE : coche tout puis lance directement ===
function runAll(verb) {
    selectAllVisible();
    if (selectedIds.size === 0) { alert('Aucune epreuve dans la liste actuelle'); return; }
    const titles = { verify: 'VERIFIER pages', scrape: 'SCRAPER classements', refresh: 'MAJ profils' };
    if (!confirm('TOUT ' + titles[verb] + ' pour ' + selectedIds.size + ' epreuves visibles ?\n\nTu peux arreter a tout moment.')) {
        clearSelection();
        return;
    }
    bulkRun(verb);
}

// === Batch run ===
function bulkRun(verb) {
    if (selectedIds.size === 0) { alert('Aucune epreuve selectionnee'); return; }
    if (currentEventSrc || bulkQueue) {
        if (!confirm('Une action est deja en cours. La stopper ?')) return;
        bulkStopRequested = true;
        if (currentEventSrc) currentEventSrc.close();
        bulkQueue = null;
    }
    // Construit la queue ordonnee depuis la liste visible filtree
    const filter = filterInput.value.toLowerCase().trim();
    const list = filter ? currentEpreuves.filter(e =>
        e.libelle.toLowerCase().includes(filter) ||
        e.sexe.toLowerCase() === filter ||
        e.family.toLowerCase().includes(filter)
    ) : currentEpreuves;
    bulkQueue = list.filter(e => selectedIds.has(e.id));
    // Si force OFF : pre-filtrer les epreuves deja faites OK pour cette action aujourd'hui
    const forceOn = document.getElementById('chk-force').checked;
    let preSkipped = 0;
    if (!forceOn) {
        const before = bulkQueue.length;
        bulkQueue = bulkQueue.filter(e => {
            const status = (e.today || {})[verb];
            // On garde si pas fait ou en erreur ou partiel — on skip uniquement si "ok"
            return status !== 'ok';
        });
        preSkipped = before - bulkQueue.length;
    }
    bulkVerb = verb;
    bulkStopRequested = false;

    const titles = { verify: 'VERIFIER', scrape: 'SCRAPER', refresh: 'MAJ profils' };
    clearConsole();
    log('info', '====== BATCH ' + titles[verb] + ' : ' + bulkQueue.length + ' a faire'
        + (preSkipped > 0 ? ' (' + preSkipped + ' deja OK aujourd\'hui — skip auto, coche FORCER pour refaire)' : '')
        + ' ======');
    if (bulkQueue.length === 0) {
        log('done', 'Rien a faire — tout est deja fait aujourd\'hui pour cette action');
        bulkFinish();
        return;
    }

    // Initialiser barre de progression
    document.getElementById('bulk-progress').style.display = 'block';
    document.getElementById('bulk-stop').style.display = 'inline-block';
    updateBulkProgress(0, bulkQueue.length, '');
    processNextInQueue();
}

function bulkStop() {
    if (confirm('Arreter le batch en cours ? L\'action courante se termine, puis stop.')) {
        bulkStopRequested = true;
        log('err', 'STOP demande — arret apres l\'epreuve courante');
    }
}

let bulkDoneCount = 0;
function processNextInQueue() {
    if (bulkStopRequested || !bulkQueue || bulkQueue.length === 0) {
        bulkFinish();
        return;
    }
    const total = bulkDoneCount + bulkQueue.length;
    const e = bulkQueue.shift();
    updateBulkProgress(bulkDoneCount, total, e.libelle + ' ' + e.sexe);
    doAction(bulkVerb, e.id, e.table, e.libelle + ' ' + e.sexe, () => {
        bulkDoneCount++;
        updateBulkProgress(bulkDoneCount, total, '');
        processNextInQueue();
    });
}

function bulkFinish() {
    const totalDone = bulkDoneCount;
    log('done', '====== BATCH TERMINE — ' + totalDone + ' epreuves traitees ======');
    bulkQueue = null;
    bulkVerb = null;
    bulkDoneCount = 0;
    document.getElementById('bulk-stop').style.display = 'none';
    setTimeout(() => {
        document.getElementById('bulk-progress').style.display = 'none';
    }, 5000);
    loadEpreuves(sel.value);
}

function updateBulkProgress(done, total, currentLabel) {
    const pct = total > 0 ? Math.round(done / total * 1000) / 10 : 0;
    document.getElementById('bulk-progress-bar').style.width = pct + '%';
    document.getElementById('bulk-progress-pct').textContent = pct + '%';
    document.getElementById('bulk-progress-label').textContent =
        currentLabel ? `Traitement : ${currentLabel}` : `${done} / ${total} epreuves`;
    document.getElementById('bulk-progress-detail').textContent =
        `${done} / ${total} terminees — restant : ${total - done}`;
}

function escapeJs(s) { return (s || '').replace(/'/g, '\\\'').replace(/"/g, '\\"'); }

// Helpers pour la zone URL en cours
function showCurrentUrl(verb, label) {
    const titles = { verify: 'VERIFIER pages', scrape: 'SCRAPER classements', refresh: 'MAJ profils' };
    const card = document.getElementById('current-url-card');
    card.style.display = 'block';
    document.getElementById('current-url-action').textContent = titles[verb] + ' — ' + label;
    document.getElementById('current-url').textContent = '(URL en cours de chargement...)';
    document.getElementById('current-url-step').textContent = '—';
}
function hideCurrentUrl() {
    document.getElementById('current-url-card').style.display = 'none';
}
function setCurrentUrlText(url) {
    document.getElementById('current-url').textContent = url;
}
function setCurrentUrlStep(step) {
    document.getElementById('current-url-step').textContent = step;
}

// === Verification doublons nom_et_liens (operation longue, lecture seule) ===
function checkDuplicates() {
    if (currentEventSrc && !confirm('Une action est deja en cours. La stopper et lancer la verif ?')) return;
    if (currentEventSrc) currentEventSrc.close();
    clearConsole();
    setBusy(true, 'Verification doublons nom_et_liens');
    log('info', '─── Diagnostic doublons (peut prendre 30s a 2min) ───');

    const evt = new EventSource('?action=do_check_dup');
    currentEventSrc = evt;
    let sawDone = false;

    evt.onmessage = (e) => {
        try {
            const d = JSON.parse(e.data);
            log(d.type, d.msg, d.t);
            if (d.type === 'done') {
                sawDone = true;
                evt.close();
                currentEventSrc = null;
                setBusy(false, 'Console live');
            }
        } catch (err) { log('err', 'Parse error: ' + e.data); }
    };
    evt.onerror = () => {
        evt.close();
        currentEventSrc = null;
        setBusy(false, 'Console live');
        if (!sawDone) log('err', 'Connexion perdue avant la fin (serveur trop lent ?). Re-clique pour reessayer.');
    };
}

// === Lancement d'une action avec streaming SSE ===
// retryCount : compteur interne pour auto-retry sur SSE perdu (max 2 retries auto)
function doAction(verb, id, table, label, onComplete, retryCount) {
    retryCount = retryCount || 0;
    if (currentEventSrc) {
        if (!onComplete && retryCount === 0 && !confirm('Une action est deja en cours. La stopper et lancer la nouvelle ?')) return;
        currentEventSrc.close();
    }
    if (!onComplete && retryCount === 0) clearConsole(); // En mode batch, on garde le log cumule
    const titles = { verify: 'VERIFIER pages', scrape: 'SCRAPER classements', refresh: 'MAJ profils' };
    setBusy(true, titles[verb] + ' — ' + label);
    if (retryCount === 0) log('info', '─── ' + titles[verb] + ' : ' + label + ' ───');
    else log('info', '─── Retry auto #' + retryCount + ' (la reprise saute ce qui est deja fait) ───');
    showCurrentUrl(verb, label);

    const force = document.getElementById('chk-force').checked ? '&force=1' : '';
    const url = '?action=do_' + verb + '&id=' + id + '&table=' + encodeURIComponent(table) + force;
    const evt = new EventSource(url);
    currentEventSrc = evt;
    let baseUrl = null;
    let sawDoneMessage = false; // pour distinguer fin propre vs vraie deconnexion

    evt.onmessage = (e) => {
        try {
            const d = JSON.parse(e.data);
            log(d.type, d.msg, d.t);

            // Detecter l'URL de base depuis le message "URL : https://..."
            if (d.msg.startsWith('URL : ')) {
                baseUrl = d.msg.substring(6).replace(/\.\.\.$/, '');
                setCurrentUrlText(baseUrl);
            }
            // Detecter la page en cours : "Page N : ..." ou "  Page N : ..." ou "  ⤷ Test page N..."
            const pageMatch = d.msg.match(/Page (\d+)/i);
            if (pageMatch) {
                const pNum = pageMatch[1];
                setCurrentUrlStep(d.msg.trim());
                // Construire l'URL de la page courante : frmposition = pNum - 1 (0-indexed)
                if (baseUrl) {
                    const pos = parseInt(pNum) - 1;
                    const pageUrl = baseUrl.replace(/frmposition=\d+/, 'frmposition=' + pos);
                    setCurrentUrlText(pageUrl);
                }
            } else if (d.type === 'info' && !d.msg.startsWith('URL') && !d.msg.startsWith('Epreuve')) {
                setCurrentUrlStep(d.msg);
            }

            if (d.type === 'done') {
                sawDoneMessage = true;
                evt.close();
                currentEventSrc = null;
                setBusy(false, 'Console live');
                hideCurrentUrl();
                if (verb === 'verify' || verb === 'scrape' || verb === 'refresh') {
                    // Si on n'est PAS en batch, recharger maintenant. En batch, on charge a la fin.
                    if (!onComplete) loadEpreuves(sel.value);
                }
                if (onComplete) setTimeout(onComplete, 200);
            }
        } catch (err) {
            log('err', 'Parse error: ' + e.data);
        }
    };
    evt.onerror = () => {
        evt.close();
        currentEventSrc = null;
        setBusy(false, 'Console live');
        hideCurrentUrl();
        // Si on a vu un message 'done', c'est une fin propre → pas de retry
        if (sawDoneMessage) {
            if (onComplete) setTimeout(onComplete, 200);
            return;
        }
        // Sinon : vraie deconnexion (crash PHP, timeout). On retry auto max 2 fois.
        const MAX_AUTO_RETRY = 2;
        if (retryCount < MAX_AUTO_RETRY && (verb === 'scrape' || verb === 'refresh')) {
            const waitSec = 5 * (retryCount + 1); // 5s, 10s
            log('err', `Connexion perdue — retry auto ${retryCount + 1}/${MAX_AUTO_RETRY} dans ${waitSec}s (reprise auto sur ce qui reste)`);
            setTimeout(() => doAction(verb, id, table, label, onComplete, retryCount + 1), waitSec * 1000);
        } else {
            log('err', 'Connexion perdue definitivement (retries epuises). Re-clique pour reprendre manuellement.');
            if (onComplete) setTimeout(onComplete, 200);
        }
    };
}
</script>
</body></html>
