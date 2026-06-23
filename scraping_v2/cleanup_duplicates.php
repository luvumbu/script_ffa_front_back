<?php
/**
 * cleanup_duplicates.php — Nettoyage intelligent des doublons de nom_et_liens
 *
 * Strategie en 3 phases (chacune AJAX, anti-timeout Hostinger) :
 *
 *  Phase 0 — PREPA : ajouter un INDEX sur url(191) si absent.
 *           Sans index, COUNT(DISTINCT url) et le SELF-JOIN sont O(n^2) → 503 garantis.
 *
 *  Phase 1 — ANALYZE : 1 seule requete GROUP BY → produit la liste des id_nom_et_liens
 *           a supprimer (tous sauf le MIN(id) pour chaque url duplique).
 *           Stocke cette liste dans state/cleanup_losers.json (JSON array d'IDs).
 *           Resultat fige : on sait EXACTEMENT combien on va supprimer.
 *
 *  Phase 2 — DELETE : par batch de 5000 IDs via WHERE id_nom_et_liens IN (...)
 *           → ultra rapide car PRIMARY KEY.
 *           Pas de SELF-JOIN, pas de table scan. ~1s par batch sur Hostinger.
 *
 *  Phase 3 — INDEX UNIQUE (optionnel) : ajoute UNIQUE KEY uk_url ON nom_et_liens(url(191))
 *           pour que les futurs INSERT IGNORE evitent definitivement les doublons.
 *
 * Toutes les queries lentes sont mises en cache (state/cleanup_stats.json, TTL 60s).
 * Aucune query bloquante au chargement de la page.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once dirname(__DIR__) . '/core/db.php';
require __DIR__ . '/_guard.php';

$stateDir = __DIR__ . '/state';
if (!is_dir($stateDir)) @mkdir($stateDir, 0755, true);
$statsFile   = $stateDir . '/cleanup_stats.json';
$losersFile  = $stateDir . '/cleanup_losers.json';
$progressFile= $stateDir . '/cleanup_progress.json';

$BATCH_SIZE  = 5000;   // IDs supprimes par requete (DELETE WHERE id IN (...))
$STATS_TTL   = 60;     // cache stats 60s

// ============================================================================
// HELPERS
// ============================================================================

function indexUrlInfo(mysqli $conn) {
    $r = $conn->query("SHOW INDEX FROM nom_et_liens WHERE Column_name = 'url'");
    if ($r && $r->num_rows > 0) return $r->fetch_assoc();
    return null;
}

function getStats(mysqli $conn, $statsFile, $force = false) {
    if (!$force && file_exists($statsFile) && (time() - filemtime($statsFile)) < 60) {
        return json_decode(file_get_contents($statsFile), true);
    }
    $total = (int)$conn->query("SELECT COUNT(*) c FROM nom_et_liens")->fetch_assoc()['c'];
    // COUNT(DISTINCT) ne tourne que si on a un index sur url, sinon trop lent
    $idx = indexUrlInfo($conn);
    $unique = null;
    if ($idx) {
        $r = $conn->query("SELECT COUNT(DISTINCT url) c FROM nom_et_liens");
        if ($r) $unique = (int)$r->fetch_assoc()['c'];
    }
    $stats = [
        'computed_at' => date('Y-m-d H:i:s'),
        'total'       => $total,
        'unique'      => $unique,
        'doublons'    => $unique !== null ? max(0, $total - $unique) : null,
        'has_index'   => $idx !== null,
        'index_name'  => $idx['Key_name'] ?? null,
        'index_unique'=> $idx ? ($idx['Non_unique'] == 0) : false,
    ];
    @file_put_contents($statsFile, json_encode($stats, JSON_UNESCAPED_UNICODE));
    return $stats;
}

// ============================================================================
// API AJAX (toutes les actions sont rapides : pas de blocage de page)
// ============================================================================

$api = $_GET['api'] ?? $_POST['api'] ?? null;
if ($api) {
    header('Content-Type: application/json; charset=utf-8');

    // --- stats actuelles (cache 60s) ----------------------------------------
    if ($api === 'stats') {
        $force = isset($_GET['force']);
        echo json_encode(getStats($conn, $statsFile, $force));
        exit;
    }

    // --- phase 0 : ajouter l'INDEX (non unique) -----------------------------
    if ($api === 'add_index') {
        $t0 = microtime(true);
        $ok = $conn->query("ALTER TABLE nom_et_liens ADD INDEX idx_url (url(191))");
        $duree = (int)round((microtime(true) - $t0) * 1000);
        if ($ok) {
            @unlink($statsFile); // force recalcul stats
            echo json_encode(['ok' => true, 'duree_ms' => $duree, 'msg' => "INDEX idx_url cree en {$duree}ms"]);
        } else {
            echo json_encode(['ok' => false, 'error' => $conn->error]);
        }
        exit;
    }

    // --- phase 1 : analyse → liste des IDs a supprimer (1 seul SELECT) ------
    if ($api === 'analyze') {
        $idx = indexUrlInfo($conn);
        if (!$idx) {
            echo json_encode(['ok' => false, 'error' => 'INDEX manquant sur url. Lance d\'abord la phase Preparation.']);
            exit;
        }
        $t0 = microtime(true);
        // Selectionne les "loosers" : tous les id sauf le MIN par url duplique
        $sql = "SELECT n.id_nom_et_liens
                FROM nom_et_liens n
                INNER JOIN (
                    SELECT url, MIN(id_nom_et_liens) AS keep_id, COUNT(*) AS n
                    FROM nom_et_liens
                    GROUP BY url
                    HAVING n > 1
                ) w ON w.url = n.url
                WHERE n.id_nom_et_liens <> w.keep_id";
        $r = $conn->query($sql);
        if (!$r) {
            echo json_encode(['ok' => false, 'error' => $conn->error]);
            exit;
        }
        $losers = [];
        while ($row = $r->fetch_assoc()) $losers[] = (int)$row['id_nom_et_liens'];
        $duree = round(microtime(true) - $t0, 1);
        @file_put_contents($losersFile, json_encode($losers));
        @file_put_contents($progressFile, json_encode([
            'started_at'    => date('Y-m-d H:i:s'),
            'total_a_del'   => count($losers),
            'deleted'       => 0,
            'cycles'        => 0,
            'last_batch_ms' => 0,
        ]));
        @unlink($statsFile);
        echo json_encode([
            'ok'       => true,
            'count'    => count($losers),
            'duree_s'  => $duree,
            'msg'      => count($losers) . " IDs a supprimer identifies en {$duree}s. Lance la phase Suppression.",
        ]);
        exit;
    }

    // --- phase 2 : 1 batch DELETE (5000 IDs par PK = ultra rapide) ----------
    if ($api === 'delete_batch') {
        if (!file_exists($losersFile)) {
            echo json_encode(['ok' => false, 'error' => 'Pas de liste : lance d\'abord la phase Analyze.']);
            exit;
        }
        $losers = @json_decode(file_get_contents($losersFile), true) ?: [];
        $progress = @json_decode(file_get_contents($progressFile), true) ?: ['deleted' => 0, 'cycles' => 0];
        if (empty($losers)) {
            // Plus rien a supprimer : finalize
            $progress['finished_at'] = date('Y-m-d H:i:s');
            @file_put_contents($progressFile, json_encode($progress));
            @unlink($statsFile);
            echo json_encode(['ok' => true, 'finished' => true, 'deleted_total' => $progress['deleted']]);
            exit;
        }
        $batch = array_slice($losers, 0, $BATCH_SIZE);
        $idsStr = implode(',', array_map('intval', $batch));
        $t0 = microtime(true);
        $ok = $conn->query("DELETE FROM nom_et_liens WHERE id_nom_et_liens IN ($idsStr)");
        $duree_ms = (int)round((microtime(true) - $t0) * 1000);
        if (!$ok) {
            echo json_encode(['ok' => false, 'error' => $conn->error]);
            exit;
        }
        $aff = $conn->affected_rows;
        // Retire les IDs traites de la liste persistante
        $remaining = array_slice($losers, $BATCH_SIZE);
        @file_put_contents($losersFile, json_encode($remaining));
        $progress['deleted']      += $aff;
        $progress['cycles']++;
        $progress['last_batch_ms'] = $duree_ms;
        $progress['last_batch_n']  = $aff;
        $progress['updated_at']    = date('Y-m-d H:i:s');
        if (empty($remaining)) $progress['finished_at'] = date('Y-m-d H:i:s');
        @file_put_contents($progressFile, json_encode($progress));
        @unlink($statsFile); // stats potentiellement obsoletes
        echo json_encode([
            'ok'        => true,
            'finished'  => empty($remaining),
            'deleted'   => $aff,
            'duree_ms'  => $duree_ms,
            'remaining' => count($remaining),
            'progress'  => $progress,
        ]);
        exit;
    }

    // --- phase 3 : finaliser avec UNIQUE INDEX ------------------------------
    if ($api === 'add_unique') {
        // Verifier d'abord qu'il n'y a plus aucun doublon
        $r = $conn->query("SELECT COUNT(*) c FROM (SELECT url FROM nom_et_liens GROUP BY url HAVING COUNT(*) > 1) d");
        $remaining = $r ? (int)$r->fetch_assoc()['c'] : -1;
        if ($remaining > 0) {
            echo json_encode(['ok' => false, 'error' => "Il reste $remaining doublons : nettoie d'abord."]);
            exit;
        }
        // Drop l'index non-unique avant d'ajouter l'unique (sinon conflit)
        $idx = indexUrlInfo($conn);
        if ($idx && $idx['Non_unique'] == 1) {
            $conn->query("ALTER TABLE nom_et_liens DROP INDEX " . $idx['Key_name']);
        }
        $t0 = microtime(true);
        $ok = $conn->query("ALTER TABLE nom_et_liens ADD UNIQUE KEY uk_url (url(191))");
        $duree = (int)round((microtime(true) - $t0) * 1000);
        @unlink($statsFile);
        if ($ok) {
            echo json_encode(['ok' => true, 'duree_ms' => $duree, 'msg' => "UNIQUE INDEX uk_url cree. Les futurs INSERT IGNORE seront sans doublon."]);
        } else {
            echo json_encode(['ok' => false, 'error' => $conn->error]);
        }
        exit;
    }

    // --- reset etat ---------------------------------------------------------
    if ($api === 'reset') {
        @unlink($losersFile);
        @unlink($progressFile);
        @unlink($statsFile);
        echo json_encode(['ok' => true, 'msg' => 'Etat efface.']);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'API inconnue']);
    exit;
}

// ============================================================================
// PAGE HTML : 0 query bloquante au chargement, tout en AJAX
// ============================================================================

$idx          = indexUrlInfo($conn);
$lastStats    = file_exists($statsFile)    ? json_decode(file_get_contents($statsFile), true)    : null;
$lastProgress = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) : null;
$losersCount  = file_exists($losersFile)   ? count(json_decode(file_get_contents($losersFile), true) ?: []) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Cleanup doublons — nom_et_liens (intelligent)</title>
<style>
* { box-sizing: border-box; }
body { font-family: 'Inter', -apple-system, sans-serif; background: #0d1117; color: #c9d1d9; margin: 0; padding: 24px; max-width: 1000px; margin-left: auto; margin-right: auto; }
h1 { color: #a78bfa; font-size: 22px; margin: 0 0 4px; }
.sub { color: #8b949e; font-size: 13px; margin-bottom: 22px; }
.nav { display: flex; gap: 8px; margin-bottom: 16px; }
.btn { background: #6366f1; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; text-decoration: none; display: inline-block; transition: all 0.15s; }
.btn:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(1.1); }
.btn:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-back { background: #374151; }
.btn-warn { background: #d97706; }
.btn-ok   { background: #16a34a; }
.btn-danger { background: #dc2626; }
.phase { background: #161b22; border: 1px solid #1f2937; border-radius: 10px; padding: 18px; margin-bottom: 14px; transition: border-color 0.3s; }
.phase.active { border-color: #fbbf24; box-shadow: 0 0 0 1px rgba(251,191,36,0.2); }
.phase.done   { border-color: #34d399; }
.phase.todo   { opacity: 0.55; }
.phase-h { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.phase-num { width: 28px; height: 28px; border-radius: 50%; background: #1f2937; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0; }
.phase.active .phase-num { background: #d97706; }
.phase.done .phase-num   { background: #16a34a; }
.phase h3 { margin: 0; font-size: 15px; color: #fff; }
.phase p { color: #8b949e; font-size: 13px; margin: 6px 0 10px; line-height: 1.5; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(140px,1fr)); gap: 10px; margin: 14px 0; }
.stat { background: #0a0e15; border: 1px solid #1f2937; border-radius: 8px; padding: 12px; text-align: center; }
.stat .v { font-size: 22px; font-weight: 700; color: #fff; }
.stat .l { font-size: 10px; color: #8b949e; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 4px; }
.stat.dup .v { color: #f87171; }
.stat.uniq .v { color: #34d399; }
.bar { background: #0a0e15; border-radius: 6px; height: 22px; overflow: hidden; border: 1px solid #1f2937; margin: 10px 0; }
.bar-inner { background: linear-gradient(90deg,#dc2626,#a78bfa); height: 100%; transition: width 0.4s; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 12px; min-width: 50px; }
.log { background: #000; border-radius: 6px; padding: 10px; font-family: 'JetBrains Mono', 'Consolas', monospace; font-size: 11.5px; line-height: 1.6; max-height: 200px; overflow-y: auto; color: #c4b5fd; }
.log .ok { color: #34d399; }
.log .err { color: #f87171; }
.log .warn { color: #fbbf24; }
.small { font-size: 11px; color: #6b7280; }
.spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #6366f1; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; }
@keyframes spin { to { transform: rotate(360deg); } }
.alert-warn { background: rgba(251,191,36,0.08); border-left: 3px solid #fbbf24; padding: 10px 14px; border-radius: 4px; margin: 8px 0; color: #fde68a; font-size: 13px; }
.alert-ok   { background: rgba(52,211,153,0.08); border-left: 3px solid #34d399; padding: 10px 14px; border-radius: 4px; margin: 8px 0; color: #6ee7b7; font-size: 13px; }
.alert-err  { background: rgba(248,113,113,0.08); border-left: 3px solid #f87171; padding: 10px 14px; border-radius: 4px; margin: 8px 0; color: #fca5a5; font-size: 13px; }
</style>
</head>
<body>

<h1>Cleanup doublons — <code>nom_et_liens</code></h1>
<div class="sub">Strategie intelligente en 3 phases : 0 query bloquante au chargement, tout en AJAX, anti-timeout Hostinger.</div>

<div class="nav">
    <a href="index.php" class="btn btn-back">&larr; Index</a>
    <a href="par_annee.php" class="btn btn-back">Par annee</a>
    <a href="diagnose.php" class="btn btn-back">Diagnose</a>
    <button class="btn btn-back" onclick="refreshStats(true)" id="btnRefresh">Recalculer stats</button>
</div>

<!-- ETAT GLOBAL : stats live -->
<div class="phase">
    <div class="phase-h"><div class="phase-num">i</div><h3>Etat actuel</h3></div>
    <div class="stats-grid">
        <div class="stat"><div class="v" id="sTotal">--</div><div class="l">Total lignes</div></div>
        <div class="stat uniq"><div class="v" id="sUnique">--</div><div class="l">URLs uniques</div></div>
        <div class="stat dup"><div class="v" id="sDup">--</div><div class="l">Doublons</div></div>
        <div class="stat"><div class="v" id="sIdx">--</div><div class="l">Index url</div></div>
    </div>
    <div class="small" id="sTime">Calcul des stats en cours...</div>
</div>

<!-- PHASE 0 : INDEX -->
<div class="phase" id="phase0">
    <div class="phase-h"><div class="phase-num">0</div><h3>Preparation : INDEX sur url</h3></div>
    <p>Sans index, toutes les operations sur <code>url</code> sont O(n^2) → timeout garanti. On ajoute un INDEX non-unique pour debloquer (les doublons restent autorises a ce stade).</p>
    <div id="phase0Status" class="small">Verification de l'index...</div>
    <div style="margin-top:10px;">
        <button class="btn btn-warn" onclick="runApi('add_index', this)" id="btnAddIdx" disabled>Ajouter INDEX idx_url(191)</button>
    </div>
</div>

<!-- PHASE 1 : ANALYZE -->
<div class="phase" id="phase1">
    <div class="phase-h"><div class="phase-num">1</div><h3>Analyse : identifier les IDs a supprimer</h3></div>
    <p>1 seule requete GROUP BY → produit la liste des <code>id_nom_et_liens</code> a supprimer (tous sauf le MIN pour chaque url dupliquee). Resultat fige dans un fichier. Ne touche pas a la BDD a ce stade.</p>
    <div id="phase1Status" class="small">En attente...</div>
    <div style="margin-top:10px;">
        <button class="btn" onclick="runApi('analyze', this)" id="btnAnalyze" disabled>Analyser doublons</button>
    </div>
</div>

<!-- PHASE 2 : DELETE -->
<div class="phase" id="phase2">
    <div class="phase-h"><div class="phase-num">2</div><h3>Suppression par batch (PRIMARY KEY)</h3></div>
    <p>DELETE par batch de <?= $BATCH_SIZE ?> IDs via <code>WHERE id_nom_et_liens IN (...)</code>. Ultra-rapide car PK INT (~1s par batch). Auto-loop en JS, peut etre interrompu.</p>
    <div id="phase2Status" class="small">En attente de la phase 1...</div>
    <div class="bar" style="display:none;" id="phase2Bar"><div class="bar-inner" id="phase2Fill" style="width:0%">0%</div></div>
    <div class="log" id="phase2Log" style="display:none;"></div>
    <div style="margin-top:10px;display:flex;gap:8px;">
        <button class="btn btn-danger" onclick="startDelete(this)" id="btnDelete" disabled>Demarrer suppression</button>
        <button class="btn btn-back" onclick="stopDelete()" id="btnStopDelete" style="display:none;">Pause</button>
    </div>
</div>

<!-- PHASE 3 : UNIQUE INDEX -->
<div class="phase" id="phase3">
    <div class="phase-h"><div class="phase-num">3</div><h3>Finalisation : UNIQUE KEY (anti-doublon permanent)</h3></div>
    <p>Apres cleanup, on transforme l'index en UNIQUE. Les futurs <code>INSERT IGNORE</code> n'inserteront plus de doublons. <strong>Action une seule fois, definitive.</strong></p>
    <div id="phase3Status" class="small">En attente du cleanup...</div>
    <div style="margin-top:10px;">
        <button class="btn btn-ok" onclick="runApi('add_unique', this)" id="btnUnique" disabled>Ajouter UNIQUE KEY uk_url</button>
    </div>
</div>

<div id="globalAlert"></div>

<div style="margin-top:24px;text-align:center;color:#6b7280;font-size:11px;">
    Batch <?= $BATCH_SIZE ?> IDs par cycle, pause 200ms entre batches, stats cache <?= $STATS_TTL ?>s
</div>

<script>
let deleteLoopActive = false;
let lastStats = null;

function fmt(n) { return Number(n||0).toLocaleString('fr-FR'); }

function setText(id, txt) {
    const el = document.getElementById(id);
    if (el) el.textContent = txt;
}
function setHtml(id, html) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = html;
}
function showAlert(type, msg) {
    const cls = type === 'ok' ? 'alert-ok' : (type === 'warn' ? 'alert-warn' : 'alert-err');
    document.getElementById('globalAlert').innerHTML = '<div class="' + cls + '">' + msg + '</div>';
    setTimeout(() => { document.getElementById('globalAlert').innerHTML = ''; }, 5000);
}

async function refreshStats(force) {
    const url = '?api=stats' + (force ? '&force=1' : '');
    setText('sTime', 'Calcul...');
    try {
        const r = await fetch(url);
        const d = await r.json();
        lastStats = d;
        setText('sTotal', fmt(d.total));
        setText('sUnique', d.unique === null ? '?' : fmt(d.unique));
        setText('sDup', d.doublons === null ? '?' : fmt(d.doublons));
        setText('sIdx', d.has_index ? (d.index_unique ? 'UNIQUE' : 'non-unique') : 'AUCUN');
        document.getElementById('sIdx').style.color = d.has_index
            ? (d.index_unique ? '#34d399' : '#fbbf24')
            : '#f87171';
        setText('sTime', 'Calcule a ' + d.computed_at);
        updatePhases(d);
    } catch (e) {
        setText('sTime', 'Erreur : ' + e.message);
    }
}

function updatePhases(d) {
    // Phase 0 : index
    const p0 = document.getElementById('phase0');
    const btn0 = document.getElementById('btnAddIdx');
    if (d.has_index) {
        p0.classList.add('done');
        p0.classList.remove('active');
        setHtml('phase0Status', '<span style="color:#6ee7b7;">INDEX present : <code>' + d.index_name + '</code> (' + (d.index_unique ? 'UNIQUE' : 'non-unique') + ')</span>');
        btn0.disabled = true;
        btn0.textContent = d.index_unique ? 'Deja UNIQUE' : 'INDEX deja present';
    } else {
        p0.classList.add('active');
        setHtml('phase0Status', '<span style="color:#fcd34d;">Aucun index sur url — phase obligatoire avant la suite.</span>');
        btn0.disabled = false;
    }

    // Phase 1 : analyze
    const p1 = document.getElementById('phase1');
    const btn1 = document.getElementById('btnAnalyze');
    btn1.disabled = !d.has_index;
    if (d.doublons === 0) {
        p1.classList.add('done');
        setHtml('phase1Status', '<span style="color:#6ee7b7;">Aucun doublon, rien a analyser.</span>');
        btn1.disabled = true;
    } else if (d.doublons === null) {
        setHtml('phase1Status', '<span style="color:#fcd34d;">Doublons inconnus (index manquant).</span>');
    } else {
        setHtml('phase1Status', '<span>' + fmt(d.doublons) + ' doublons detectes. Lance l\'analyse pour figer la liste des IDs a supprimer.</span>');
        if (d.has_index) p1.classList.add('active');
    }

    // Phase 2 : delete - status mis a jour par checkLosers()
    checkLosers();

    // Phase 3 : unique
    const p3 = document.getElementById('phase3');
    const btn3 = document.getElementById('btnUnique');
    if (d.has_index && d.index_unique) {
        p3.classList.add('done');
        setHtml('phase3Status', '<span style="color:#6ee7b7;">UNIQUE KEY <code>' + d.index_name + '</code> active. Les doublons sont definitivement bloques.</span>');
        btn3.disabled = true;
    } else if (d.doublons === 0 && d.has_index) {
        p3.classList.add('active');
        setHtml('phase3Status', '<span style="color:#fcd34d;">Pret a transformer l\'index en UNIQUE pour bloquer les futurs doublons.</span>');
        btn3.disabled = false;
    } else {
        setHtml('phase3Status', '<span class="small">En attente : nettoie d\'abord tous les doublons.</span>');
        btn3.disabled = true;
    }
}

async function runApi(action, btn) {
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> En cours...'; }
    try {
        const r = await fetch('?api=' + action, { method: 'POST' });
        const d = await r.json();
        if (d.ok) {
            showAlert('ok', d.msg || 'OK');
            await refreshStats(true);
        } else {
            showAlert('err', 'Erreur : ' + (d.error || 'inconnue'));
        }
    } catch (e) {
        showAlert('err', 'Erreur reseau : ' + e.message);
    }
    if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || btn.textContent.replace(/.*En cours\.\.\./, '').trim() || 'Lancer'; }
    // Recharge le bouton avec son texte original via data-label si defini
}

async function checkLosers() {
    // Verifie si une liste d'IDs a supprimer existe (phase 1 deja faite)
    // On le sait via le fichier de progress
    try {
        const r = await fetch('?api=stats');
        const d = await r.json();
        // Pas d'endpoint dedie : on regarde si lastProgress est dispo cote serveur en regardant le fichier
        // Plus simple : on ne sait que via la phase 1 — on garde l'etat en variables JS
    } catch (e) {}
}

async function startDelete(btn) {
    deleteLoopActive = true;
    btn.style.display = 'none';
    document.getElementById('btnStopDelete').style.display = 'inline-block';
    document.getElementById('phase2Bar').style.display = 'flex';
    document.getElementById('phase2Log').style.display = 'block';
    appendLog('phase2Log', 'Demarrage de la suppression...', 'warn');

    let totalDeleted = 0;
    let totalToDel = null;
    let cycles = 0;

    while (deleteLoopActive) {
        const t0 = Date.now();
        try {
            const r = await fetch('?api=delete_batch', { method: 'POST' });
            const d = await r.json();
            if (!d.ok) {
                appendLog('phase2Log', 'ERREUR : ' + (d.error || 'inconnue'), 'err');
                break;
            }
            cycles++;
            totalDeleted += (d.deleted || 0);
            if (totalToDel === null && d.progress) {
                totalToDel = (d.progress.deleted || 0) + (d.remaining || 0);
            }
            const pct = totalToDel > 0 ? Math.round((totalDeleted / totalToDel) * 100) : 0;
            document.getElementById('phase2Fill').style.width = pct + '%';
            document.getElementById('phase2Fill').textContent = pct + '%';
            setHtml('phase2Status', 'Cycle ' + cycles + ' : <b>' + fmt(d.deleted) + '</b> supprimes en ' + d.duree_ms + 'ms — Restant : <b>' + fmt(d.remaining) + '</b> — Total : <b>' + fmt(totalDeleted) + '</b>');
            appendLog('phase2Log', '[' + new Date().toTimeString().substr(0,8) + '] Cycle ' + cycles + ' : -' + d.deleted + ' (' + d.duree_ms + 'ms) — restant ' + d.remaining, 'ok');
            if (d.finished) {
                appendLog('phase2Log', 'TERMINE — ' + totalDeleted + ' doublons supprimes en ' + cycles + ' cycles', 'ok');
                showAlert('ok', 'Cleanup termine : ' + totalDeleted + ' doublons supprimes.');
                break;
            }
            // Pause anti-spam BDD
            await new Promise(res => setTimeout(res, 200));
        } catch (e) {
            appendLog('phase2Log', 'Erreur reseau : ' + e.message, 'err');
            break;
        }
    }
    deleteLoopActive = false;
    document.getElementById('btnDelete').style.display = 'inline-block';
    document.getElementById('btnStopDelete').style.display = 'none';
    await refreshStats(true);
}

function stopDelete() {
    deleteLoopActive = false;
    appendLog('phase2Log', 'Pause demande par l\'utilisateur (reprise possible)', 'warn');
}

function appendLog(id, msg, type) {
    const box = document.getElementById(id);
    if (!box) return;
    const line = document.createElement('div');
    line.className = type || '';
    line.textContent = msg;
    box.appendChild(line);
    box.scrollTop = box.scrollHeight;
}

// --- Init ----------------------------------------------------------------
refreshStats(false);

// Au chargement, on regarde si une analyse a deja produit une liste a supprimer
<?php if ($losersCount > 0): ?>
setHtml('phase2Status', '<span style="color:#fcd34d;">Liste d\'IDs prete : <b><?= $losersCount ?></b> a supprimer.</span>');
document.getElementById('btnDelete').disabled = false;
document.getElementById('phase2').classList.add('active');
<?php endif; ?>

// Apres une analyse, on active le bouton delete
const originalRunApi = runApi;
runApi = async function(action, btn) {
    await originalRunApi(action, btn);
    if (action === 'analyze') {
        // Recharge l'etat des losers via une nouvelle stats refresh
        location.reload();
    }
};
</script>

</body>
</html>
