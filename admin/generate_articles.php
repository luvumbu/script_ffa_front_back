<?php
/**
 * admin/generate_articles.php — Générateur d'articles « Fil BOKONZI »
 *
 * Permet de CHOISIR ce qu'on génère :
 *   • « Le saviez-vous ? » sur un club (faits insolites / records uniques)
 *   • « L'épreuve au club » sur une épreuve précise
 *   • Génération selon la FRÉQUENCE des épreuves du club (top N pratiquées)
 *   • Le « club de la semaine » (rotation auto)
 *   • Génération en masse (saviez-vous sur les N plus gros clubs)
 *
 * Accès : super admin (cookie bk_sa_token) OU clé API ?bk_key=...
 * Actions AJAX : ?action=...  → JSON
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/article_gen.php';

// ── Auth : super admin OU clé API ────────────────────────────────────────
function genArtIsAuthed() {
    $key = $_GET['bk_key'] ?? $_POST['bk_key'] ?? $_SERVER['HTTP_X_BK_KEY'] ?? '';
    if ($key === 'bk_s3cr3t_2026_xK9mP') return true;
    if (!empty($_COOKIE['bk_sa_token'])) {
        $saFile = __DIR__ . '/../logs/.sa_sessions.php';
        if (file_exists($saFile)) {
            $raw = file_get_contents($saFile);
            $pos = strpos($raw, "\n");
            if ($pos !== false) {
                $sessions = json_decode(substr($raw, $pos + 1), true) ?: [];
                $tok = $_COOKIE['bk_sa_token'];
                if (isset($sessions[$tok]) && ($sessions[$tok]['expires'] ?? 0) > time()) return true;
            }
        }
    }
    return false;
}
if (!genArtIsAuthed()) { http_response_code(403); die('Accès réservé (super admin ou clé API).'); }

// Auto-installe la table si absente (utile au 1er déploiement prod)
bkEnsureArticlesTable($conn);

$action = $_GET['action'] ?? '';

/** Vide le cache du fil après génération (best-effort). */
function genArtClearCache() {
    foreach (glob(__DIR__ . '/../cache/article*.json') ?: [] as $f) @unlink($f);
}

// ═══════════════════════ ACTIONS AJAX (JSON) ═══════════════════════════════
if ($action !== '') {
    header('Content-Type: application/json; charset=utf-8');

    // Autocomplete clubs
    if ($action === 'club_search') {
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) { echo json_encode([]); exit; }
        $qe = $conn->real_escape_string($q);
        $res = $conn->query(
            "SELECT c.id_club id, c.nom_club nom, COUNT(DISTINCT r.id_athlete) nb
             FROM clubs c
             LEFT JOIN athlete_records r ON r.id_club = c.id_club AND r.performance_record > 0
             WHERE c.nom_club LIKE '%$qe%'
             GROUP BY c.id_club, c.nom_club
             ORDER BY (c.nom_club LIKE '$qe%') DESC, nb DESC
             LIMIT 15"
        );
        $out = [];
        if ($res) while ($r = $res->fetch_assoc()) {
            $out[] = ['id' => (int)$r['id'], 'nom' => rtrim(trim($r['nom']), '* '), 'nb' => (int)$r['nb']];
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
    }

    // Épreuves d'un club par fréquence
    if ($action === 'club_epreuves') {
        $cid = (int)($_GET['club'] ?? 0);
        echo json_encode(bkClubTopEpreuves($conn, $cid, 30), JSON_UNESCAPED_UNICODE); exit;
    }

    // Génération « Le saviez-vous ? » d'un club
    if ($action === 'gen_facts') {
        $cid = (int)($_GET['club'] ?? 0);
        $art = bkGenClubFactsArticle($conn, $cid);
        if (!$art) { echo json_encode(['ok' => false, 'msg' => 'Pas assez de faits remarquables pour ce club.']); exit; }
        $id = bkSaveArticle($conn, $art);
        genArtClearCache();
        echo json_encode(['ok' => (bool)$id, 'id' => $id, 'title' => $art['title'], 'slug' => $art['slug']], JSON_UNESCAPED_UNICODE); exit;
    }

    // Génération « L'épreuve au club »
    if ($action === 'gen_epreuve') {
        $cid = (int)($_GET['club'] ?? 0);
        $eid = (int)($_GET['epreuve'] ?? 0);
        $art = bkGenClubEpreuveArticle($conn, $cid, $eid);
        if (!$art) { echo json_encode(['ok' => false, 'msg' => 'Pas assez de données (min. 3 athlètes) sur cette épreuve.']); exit; }
        $id = bkSaveArticle($conn, $art);
        genArtClearCache();
        echo json_encode(['ok' => (bool)$id, 'id' => $id, 'title' => $art['title'], 'slug' => $art['slug']], JSON_UNESCAPED_UNICODE); exit;
    }

    // Génération par FRÉQUENCE : top N épreuves du club
    if ($action === 'gen_freq') {
        $cid = (int)($_GET['club'] ?? 0);
        $n   = max(1, min(30, (int)($_GET['n'] ?? 8)));
        $eps = bkClubTopEpreuves($conn, $cid, $n);
        $done = []; $skip = 0;
        foreach ($eps as $e) {
            $art = bkGenClubEpreuveArticle($conn, $cid, $e['id']);
            if ($art && bkSaveArticle($conn, $art)) $done[] = $art['title'];
            else $skip++;
        }
        genArtClearCache();
        echo json_encode(['ok' => true, 'created' => count($done), 'skipped' => $skip, 'titles' => $done], JSON_UNESCAPED_UNICODE); exit;
    }

    // Club de la semaine (rotation auto) → article « saviez-vous »
    if ($action === 'gen_week') {
        $cid = bkPickClubOfWeek($conn);
        if (!$cid) { echo json_encode(['ok' => false, 'msg' => 'Aucun club éligible.']); exit; }
        $art = bkGenClubFactsArticle($conn, $cid);
        if (!$art) { echo json_encode(['ok' => false, 'msg' => 'Le club tiré n\'a pas assez de faits.']); exit; }
        $id = bkSaveArticle($conn, $art);
        genArtClearCache();
        echo json_encode(['ok' => (bool)$id, 'id' => $id, 'club_id' => $cid, 'title' => $art['title'], 'slug' => $art['slug']], JSON_UNESCAPED_UNICODE); exit;
    }

    // Génération en masse : saviez-vous sur les N plus gros clubs
    if ($action === 'gen_bulk') {
        $n = max(1, min(100, (int)($_GET['n'] ?? 20)));
        $ids = array_slice(bkEligibleClubs($conn), 0, $n);
        $created = 0; $skip = 0;
        foreach ($ids as $cid) {
            $art = bkGenClubFactsArticle($conn, (int)$cid);
            if ($art && bkSaveArticle($conn, $art)) $created++; else $skip++;
        }
        genArtClearCache();
        echo json_encode(['ok' => true, 'created' => $created, 'skipped' => $skip], JSON_UNESCAPED_UNICODE); exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Action inconnue.']); exit;
}

// ═══════════════════════ STATS pour l'en-tête ══════════════════════════════
$totalArt = ($r = $conn->query("SELECT COUNT(*) c FROM articles")) ? (int)$r->fetch_assoc()['c'] : 0;
$byType   = [];
if ($r = $conn->query("SELECT type, COUNT(*) c FROM articles GROUP BY type")) {
    while ($x = $r->fetch_assoc()) $byType[$x['type']] = (int)$x['c'];
}
$weekClub = bkPickClubOfWeek($conn);
$weekClubNom = '';
if ($weekClub) {
    $r = $conn->query("SELECT nom_club FROM clubs WHERE id_club=" . (int)$weekClub);
    if ($r) $weekClubNom = rtrim(trim($r->fetch_assoc()['nom_club'] ?? ''), '* ');
}
$bkKey = htmlspecialchars($_GET['bk_key'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Générateur d'articles — BOKONZI</title>
<style>
:root { --bg:#0d1117; --card:#161b22; --bd:#30363d; --txt:#c9d1d9; --mut:#8b949e; --pri:#6c5ce7; --ok:#2ea043; --warn:#d29922; }
* { box-sizing:border-box; }
body { margin:0; background:var(--bg); color:var(--txt); font-family:-apple-system,Segoe UI,Roboto,sans-serif; }
.wrap { max-width:980px; margin:0 auto; padding:24px 18px 80px; }
h1 { font-size:24px; margin:0 0 4px; }
.sub { color:var(--mut); margin:0 0 20px; font-size:14px; }
.grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:22px; }
.stat { background:var(--card); border:1px solid var(--bd); border-radius:12px; padding:14px 16px; }
.stat .n { font-size:26px; font-weight:800; color:#fff; }
.stat .l { color:var(--mut); font-size:13px; }
.card { background:var(--card); border:1px solid var(--bd); border-radius:14px; padding:18px 18px 20px; margin-bottom:18px; }
.card h2 { font-size:17px; margin:0 0 4px; display:flex; align-items:center; gap:8px; }
.card .hint { color:var(--mut); font-size:13px; margin:0 0 14px; }
label { display:block; font-size:13px; color:var(--mut); margin:10px 0 5px; }
input[type=text], input[type=number], select {
  width:100%; padding:11px 12px; background:#0d1117; border:1px solid var(--bd);
  border-radius:9px; color:var(--txt); font-size:14px; font-family:inherit;
}
.row { display:flex; gap:12px; flex-wrap:wrap; }
.row > div { flex:1; min-width:160px; }
button {
  margin-top:14px; padding:11px 20px; border:0; border-radius:10px; cursor:pointer;
  background:linear-gradient(135deg,#8e7bff,var(--pri)); color:#fff; font-weight:700; font-size:14px;
}
button.alt { background:#21262d; border:1px solid var(--bd); }
button:disabled { opacity:.55; cursor:default; }
.ac { position:relative; }
.ac-list { position:absolute; z-index:50; left:0; right:0; top:100%; background:#0d1117;
  border:1px solid var(--bd); border-top:0; border-radius:0 0 9px 9px; max-height:280px; overflow:auto; display:none; }
.ac-item { padding:9px 12px; cursor:pointer; font-size:14px; border-top:1px solid #21262d; }
.ac-item:hover, .ac-item.sel { background:#1f2937; }
.ac-item .meta { color:var(--mut); font-size:12px; }
.chosen { margin-top:8px; font-size:14px; color:#fff; }
.chosen b { color:#a78bfa; }
#log { margin-top:18px; background:#0d1117; border:1px solid var(--bd); border-radius:10px;
  padding:12px 14px; font-family:ui-monospace,monospace; font-size:13px; max-height:320px; overflow:auto; }
.li-ok { color:#3fb950; } .li-err { color:#f85149; } .li-info { color:#8b949e; }
.li-ok a, #log a { color:#a78bfa; }
.badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:12px; background:rgba(108,92,231,.18); color:#a78bfa; }
.week { background:linear-gradient(135deg,rgba(108,92,231,.18),rgba(108,92,231,.05)); }
</style>
</head>
<body>
<div class="wrap">
  <h1>📰 Générateur d'articles — Le Fil BOKONZI</h1>
  <p class="sub">Choisissez ce que vous publiez dans le fil. <a href="../?page=fil" style="color:#a78bfa;">Voir le fil →</a></p>

  <div class="grid">
    <div class="stat"><div class="n"><?= $totalArt ?></div><div class="l">articles publiés</div></div>
    <div class="stat"><div class="n"><?= $byType['club_facts'] ?? 0 ?></div><div class="l">« Le saviez-vous ? »</div></div>
    <div class="stat"><div class="n"><?= $byType['club_epreuve'] ?? 0 ?></div><div class="l">« Épreuve au club »</div></div>
  </div>

  <!-- Sélection du club (partagée) -->
  <div class="card">
    <h2>🏟 Club</h2>
    <p class="hint">Tapez le nom d'un club. Il sera utilisé par les générateurs ci-dessous.</p>
    <div class="ac">
      <input type="text" id="clubInput" placeholder="ex. Bordeaux Athlé, ES Massy…" autocomplete="off">
      <div class="ac-list" id="clubAc"></div>
    </div>
    <div class="chosen" id="clubChosen" style="display:none;"></div>
  </div>

  <!-- Le saviez-vous -->
  <div class="card">
    <h2>💡 « Le saviez-vous ? » <span class="badge">faits uniques</span></h2>
    <p class="hint">Génère les faits insolites du club : « le seul à avoir couru sous 11 s », records uniques, polyvalence…</p>
    <button id="btnFacts" disabled onclick="genFacts()">Générer pour le club choisi</button>
  </div>

  <!-- Par épreuve -->
  <div class="card">
    <h2>🏁 « L'épreuve au club »</h2>
    <p class="hint">Choisissez une épreuve précise du club (classées par fréquence de pratique).</p>
    <label for="epSelect">Épreuve</label>
    <select id="epSelect" disabled><option value="">— choisissez d'abord un club —</option></select>
    <button id="btnEp" disabled onclick="genEpreuve()">Générer l'article de cette épreuve</button>
  </div>

  <!-- Par fréquence -->
  <div class="card">
    <h2>📊 Par fréquence du club</h2>
    <p class="hint">Génère automatiquement « l'épreuve au club » pour les épreuves les plus pratiquées du club.</p>
    <div class="row">
      <div>
        <label for="freqN">Nombre d'épreuves (les plus fréquentes)</label>
        <input type="number" id="freqN" value="8" min="1" max="30">
      </div>
    </div>
    <button id="btnFreq" disabled onclick="genFreq()">Générer le top épreuves du club</button>
  </div>

  <!-- Club de la semaine -->
  <div class="card week">
    <h2>📅 Club de la semaine</h2>
    <p class="hint">Rotation automatique (un club par semaine ISO). Cette semaine :
      <b style="color:#a78bfa;"><?= $weekClubNom ? htmlspecialchars($weekClubNom) : '—' ?></b>.</p>
    <button onclick="genWeek()">Générer le « saviez-vous » du club de la semaine</button>
  </div>

  <!-- En masse -->
  <div class="card">
    <h2>⚡ Génération en masse</h2>
    <p class="hint">« Le saviez-vous ? » sur les N plus gros clubs (≥ 60 records). Peut prendre quelques secondes.</p>
    <div class="row">
      <div>
        <label for="bulkN">Nombre de clubs</label>
        <input type="number" id="bulkN" value="20" min="1" max="100">
      </div>
    </div>
    <button class="alt" onclick="genBulk()">Lancer la génération en masse</button>
  </div>

  <div id="log"><span class="li-info">Journal de génération…</span></div>
</div>

<script>
var BK_KEY = <?= json_encode($_GET['bk_key'] ?? '') ?>;
var CLUB = null; // {id, nom}

function api(action, params) {
  params = params || {};
  if (BK_KEY) params.bk_key = BK_KEY;
  var qs = Object.keys(params).map(function(k){ return k + '=' + encodeURIComponent(params[k]); }).join('&');
  return fetch('?action=' + action + (qs ? '&' + qs : ''), { credentials: 'same-origin' }).then(function(r){ return r.json(); });
}
function log(html, cls) {
  var box = document.getElementById('log');
  var line = document.createElement('div');
  line.className = cls || 'li-info';
  line.innerHTML = html;
  box.appendChild(line);
  box.scrollTop = box.scrollHeight;
}
function setBusy(b) {
  ['btnFacts','btnEp','btnFreq'].forEach(function(id){
    var el = document.getElementById(id); if (el && !el.dataset.lock) el.disabled = b || !CLUB;
  });
}

// ── Autocomplete club ────────────────────────────────────────────────────
var clubInput = document.getElementById('clubInput'), clubAc = document.getElementById('clubAc');
var acTimer, acItems = [], acSel = -1;
clubInput.addEventListener('input', function(){
  clearTimeout(acTimer);
  var q = clubInput.value.trim();
  if (q.length < 2) { clubAc.style.display = 'none'; return; }
  acTimer = setTimeout(function(){
    api('club_search', { q: q }).then(function(list){
      acItems = list || []; acSel = -1;
      if (!acItems.length) { clubAc.style.display = 'none'; return; }
      clubAc.innerHTML = acItems.map(function(c, i){
        return '<div class="ac-item" data-i="' + i + '">' + esc(c.nom) +
               ' <span class="meta">· ' + c.nb + ' athlètes</span></div>';
      }).join('');
      clubAc.style.display = 'block';
      Array.prototype.forEach.call(clubAc.children, function(el){
        el.onclick = function(){ pickClub(acItems[+el.dataset.i]); };
      });
    });
  }, 220);
});
clubInput.addEventListener('keydown', function(e){
  if (clubAc.style.display !== 'block') return;
  if (e.key === 'ArrowDown') { acSel = Math.min(acSel + 1, acItems.length - 1); paintSel(); e.preventDefault(); }
  else if (e.key === 'ArrowUp') { acSel = Math.max(acSel - 1, 0); paintSel(); e.preventDefault(); }
  else if (e.key === 'Enter') { if (acSel >= 0) { pickClub(acItems[acSel]); e.preventDefault(); } }
  else if (e.key === 'Escape') { clubAc.style.display = 'none'; }
});
function paintSel(){ Array.prototype.forEach.call(clubAc.children, function(el, i){ el.classList.toggle('sel', i === acSel); }); }
document.addEventListener('click', function(e){ if (!clubAc.contains(e.target) && e.target !== clubInput) clubAc.style.display = 'none'; });

function pickClub(c){
  CLUB = c;
  clubAc.style.display = 'none';
  clubInput.value = c.nom;
  var ch = document.getElementById('clubChosen');
  ch.style.display = 'block';
  ch.innerHTML = 'Club sélectionné : <b>' + esc(c.nom) + '</b> (id ' + c.id + ', ' + c.nb + ' athlètes)';
  document.getElementById('btnFacts').disabled = false;
  document.getElementById('btnFreq').disabled = false;
  loadEpreuves(c.id);
}
function loadEpreuves(cid){
  var sel = document.getElementById('epSelect'), btn = document.getElementById('btnEp');
  sel.innerHTML = '<option value="">Chargement…</option>'; sel.disabled = true; btn.disabled = true;
  api('club_epreuves', { club: cid }).then(function(list){
    if (!list || !list.length) { sel.innerHTML = '<option value="">Aucune épreuve exploitable</option>'; return; }
    sel.innerHTML = '<option value="">— choisir une épreuve —</option>' + list.map(function(e){
      return '<option value="' + e.id + '">' + esc(e.nom) + ' (' + e.nb + ' athlètes)</option>';
    }).join('');
    sel.disabled = false;
    sel.onchange = function(){ btn.disabled = !sel.value; };
  });
}

// ── Générateurs ──────────────────────────────────────────────────────────
function artLink(d){
  return d.slug ? ' → <a href="../?page=article&slug=' + encodeURIComponent(d.slug) + '" target="_blank">voir</a>' : '';
}
function genFacts(){
  if (!CLUB) return;
  log('💡 Génération « saviez-vous » pour <b>' + esc(CLUB.nom) + '</b>…');
  setBusy(true);
  api('gen_facts', { club: CLUB.id }).then(function(d){
    setBusy(false);
    if (d.ok) log('✓ ' + esc(d.title) + artLink(d), 'li-ok');
    else log('✗ ' + esc(d.msg || 'Échec'), 'li-err');
  });
}
function genEpreuve(){
  var sel = document.getElementById('epSelect');
  if (!CLUB || !sel.value) return;
  log('🏁 Génération épreuve « ' + esc(sel.options[sel.selectedIndex].text) + ' »…');
  setBusy(true);
  api('gen_epreuve', { club: CLUB.id, epreuve: sel.value }).then(function(d){
    setBusy(false);
    if (d.ok) log('✓ ' + esc(d.title) + artLink(d), 'li-ok');
    else log('✗ ' + esc(d.msg || 'Échec'), 'li-err');
  });
}
function genFreq(){
  if (!CLUB) return;
  var n = document.getElementById('freqN').value || 8;
  log('📊 Génération des ' + n + ' épreuves les plus fréquentes de <b>' + esc(CLUB.nom) + '</b>…');
  setBusy(true);
  api('gen_freq', { club: CLUB.id, n: n }).then(function(d){
    setBusy(false);
    if (d.ok) {
      log('✓ ' + d.created + ' article(s) créé(s), ' + d.skipped + ' ignoré(s).', 'li-ok');
      (d.titles || []).forEach(function(t){ log('&nbsp;&nbsp;• ' + esc(t), 'li-info'); });
    } else log('✗ ' + esc(d.msg || 'Échec'), 'li-err');
  });
}
function genWeek(){
  log('📅 Génération du club de la semaine…');
  api('gen_week', {}).then(function(d){
    if (d.ok) log('✓ ' + esc(d.title) + artLink(d), 'li-ok');
    else log('✗ ' + esc(d.msg || 'Échec'), 'li-err');
  });
}
function genBulk(){
  var n = document.getElementById('bulkN').value || 20;
  log('⚡ Génération en masse sur ' + n + ' clubs… (patientez)');
  api('gen_bulk', { n: n }).then(function(d){
    if (d.ok) log('✓ ' + d.created + ' article(s) créé(s), ' + d.skipped + ' ignoré(s).', 'li-ok');
    else log('✗ ' + esc(d.msg || 'Échec'), 'li-err');
  });
}
function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
</script>
</body>
</html>
