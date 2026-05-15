<?php
/**
 * admin/tools.php — Centre d'outils admin (URL, chemins, extraction, routes)
 *
 * Marche partout : local ET prod.
 * Auth : cookie bk_sa_token (Super Admin) OU bk_key URL param.
 *
 * URLs :
 *   Local : http://localhost/BK/admin/tools.php
 *   Prod  : https://bokonzi.com/admin/tools.php
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/credentials.php';
require_once __DIR__ . '/../core/paths.php';

// === Auth : bk_sa_token cookie OU bk_key param ===
$authenticated = false;

if (!empty($_COOKIE['bk_sa_token'])) {
    $saFile = __DIR__ . '/../logs/.sa_sessions.php';
    if (file_exists($saFile)) {
        $raw = file_get_contents($saFile);
        $pos = strpos($raw, "\n");
        $sessions = $pos !== false ? (json_decode(substr($raw, $pos + 1), true) ?: []) : [];
        $token = $_COOKIE['bk_sa_token'];
        if (isset($sessions[$token]) && ($sessions[$token]['expires'] ?? 0) > time()) {
            $authenticated = true;
        }
    }
}

if (!$authenticated && ($_GET['bk_key'] ?? '') === 'bk_s3cr3t_2026_xK9mP') {
    $authenticated = true;
}

if (!$authenticated) {
    // Redirige vers local_setup.php (qui a le formulaire de login)
    header('Location: ' . BK_BASE . '/admin/local_setup.php?from=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    exit;
}

$_localBase = (BK_IS_LOCAL ? 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') : 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bokonzi.com')) . BK_BASE;
$_bkKey = 'bk_s3cr3t_2026_xK9mP';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Outils Admin — BK</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0a0e14; --panel: #11161f; --border: #232b3a;
    --text: #d9e1ec; --text-dim: #7a869a; --text-muted: #525d72;
    --accent: #6366f1; --success: #10b981; --danger: #ef4444; --warning: #f59e0b;
  }
  * { box-sizing: border-box; }
  body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); padding: 24px; margin: 0; min-height: 100vh; }
  .wrap { max-width: 1200px; margin: 0 auto; }
  h1 { font-size: 24px; margin: 0 0 8px; color: #fff; letter-spacing: -0.5px; }
  .subtitle { color: var(--text-dim); font-size: 13px; margin-bottom: 24px; }
  .pill { display: inline-flex; align-items: center; gap: 6px; background: var(--panel); border: 1px solid var(--border); padding: 6px 12px; border-radius: 20px; font-size: 11px; color: var(--text-dim); margin-right: 8px; font-family: 'JetBrains Mono', monospace; }
  .pill.live::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--success); animation: pulse 2s infinite; }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

  .actions-bar { display: flex; gap: 8px; margin: 20px 0; flex-wrap: wrap; }
  .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; background: var(--panel); color: var(--text); border: 1px solid var(--border); border-radius: 8px; font-size: 13px; cursor: pointer; text-decoration: none; transition: all 0.15s; font-family: inherit; }
  .btn:hover { background: rgba(99,102,241,0.1); border-color: var(--accent); }
  .btn-primary-link { background: linear-gradient(135deg, var(--accent), #8b5cf6); color: #fff; border-color: var(--accent); }
  .btn-back { color: #fcd34d; }

  .section { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
  .section h3 { margin: 0 0 14px; font-size: 14px; color: #fff; display: flex; align-items: center; gap: 8px; }

  .tab-pane { display: none; }
  .tab-pane.active { display: block; }

  .tool-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; }
  .tool-card { background: var(--panel); border: 1px solid var(--border); border-radius: 10px; padding: 16px; transition: all 0.2s; }
  .tool-card:hover { border-color: var(--accent); transform: translateY(-2px); }
  .tool-card h4 { margin: 0 0 6px; color: #fff; font-size: 14px; display: flex; align-items: center; gap: 8px; }
  .tool-card .desc { color: var(--text-dim); font-size: 12px; margin: 0 0 10px; line-height: 1.5; min-height: 32px; }
  .tool-card .url { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #a5b4fc; background: var(--bg); padding: 6px 10px; border-radius: 6px; word-break: break-all; display: block; margin-bottom: 8px; }
  .tool-card .actions-row { display: flex; gap: 6px; flex-wrap: wrap; }
  .btn-mini { padding: 5px 10px; font-size: 11px; }
  .copy-btn { background: rgba(99,102,241,0.15); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3); }
  .copy-btn:hover { background: rgba(99,102,241,0.25); }

  .path-row { padding: 8px 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; margin-bottom: 6px; font-family: 'JetBrains Mono', monospace; font-size: 12px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
  .path-row .label { color: var(--text-dim); }
  .path-row .path { color: #fff; }

  .tag { display: inline-block; padding: 1px 7px; background: rgba(99,102,241,0.15); color: #a5b4fc; border-radius: 10px; font-size: 10px; font-weight: 600; }
  .tag-warn { background: rgba(245,158,11,0.15); color: #fcd34d; }
  .tag-danger { background: rgba(239,68,68,0.15); color: #fca5a5; }
  .tag-ok { background: rgba(16,185,129,0.15); color: #6ee7b7; }
  code { font-family: 'JetBrains Mono', monospace; background: var(--bg); padding: 2px 6px; border-radius: 3px; color: #a5b4fc; font-size: 12px; }
</style>
</head>
<body>
<div class="wrap">

<h1>Outils Administration</h1>
<div class="subtitle">
  <span class="pill live">Connecte</span>
  <span class="pill"><?= BK_IS_LOCAL ? 'LOCAL' : 'PROD' ?> &middot; <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') ?></span>
</div>

<div class="actions-bar">
  <a class="btn btn-primary-link" href="#tab-tools" onclick="showTab('tools')">Outils admin</a>
  <a class="btn" href="#tab-paths" onclick="showTab('paths')">Chemins fichiers</a>
  <a class="btn" href="#tab-extract" onclick="showTab('extract')">Extraction donnees</a>
  <a class="btn" href="#tab-routes" onclick="showTab('routes')">Routes du site</a>
  <a class="btn btn-back" href="panel.php">&larr; Panel admin</a>
  <a class="btn" href="<?= BK_BASE ?>/index.php">Site</a>
</div>

<!-- TAB : Outils admin -->
<div class="tab-pane active" id="tab-tools">
  <div class="section">
    <h3>Tous les outils administration</h3>
    <p style="color:var(--text-dim);font-size:12px;margin:0 0 16px">Cle API : <code><?= htmlspecialchars($_bkKey) ?></code></p>
    <div class="tool-grid">

      <div class="tool-card">
        <h4>Archive Manager <span class="tag tag-ok">PRINCIPAL</span></h4>
        <p class="desc">Export/Import des tables BDD, bascule BDD/Fichier, install depuis archive.</p>
        <a class="url" href="<?= $_localBase ?>/admin/db_archive.php?bk_key=<?= $_bkKey ?>" target="_blank"><?= $_localBase ?>/admin/db_archive.php?bk_key=...</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/db_archive.php?bk_key=<?= $_bkKey ?>" target="_blank">Ouvrir</a>
          <button class="btn btn-mini copy-btn" onclick="copyUrl('<?= $_localBase ?>/admin/db_archive.php?bk_key=<?= $_bkKey ?>', this)">Copier</button>
        </div>
      </div>

      <div class="tool-card">
        <h4>Diagnostic taille BDD <span class="tag">DIAGNOSTIC</span></h4>
        <p class="desc">Liste tables avec taille MB, lignes, % du total.</p>
        <a class="url" href="<?= $_localBase ?>/admin/db_size.php?bk_key=<?= $_bkKey ?>" target="_blank"><?= $_localBase ?>/admin/db_size.php?bk_key=...</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/db_size.php?bk_key=<?= $_bkKey ?>" target="_blank">Ouvrir</a>
          <button class="btn btn-mini copy-btn" onclick="copyUrl('<?= $_localBase ?>/admin/db_size.php?bk_key=<?= $_bkKey ?>', this)">Copier</button>
        </div>
      </div>

      <div class="tool-card">
        <h4>Setup BDD complete <span class="tag tag-warn">SCHEMA</span></h4>
        <p class="desc">Cree BDD + toutes les tables vides selon le schema.</p>
        <a class="url" href="<?= $_localBase ?>/admin/setup_bdd.php" target="_blank"><?= $_localBase ?>/admin/setup_bdd.php</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/setup_bdd.php" target="_blank">Ouvrir</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>Setup local (cette page) <span class="tag">SETUP</span></h4>
        <p class="desc">Verification tables + creation auto + auth super admin.</p>
        <a class="url" href="<?= $_localBase ?>/admin/local_setup.php" target="_blank"><?= $_localBase ?>/admin/local_setup.php</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/local_setup.php" target="_blank">Ouvrir</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>Panel Admin <span class="tag">DASHBOARD</span></h4>
        <p class="desc">Dashboard complet : KPIs, logs, search tracking, users, messages.</p>
        <a class="url" href="<?= $_localBase ?>/admin/panel.php" target="_blank"><?= $_localBase ?>/admin/panel.php</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/panel.php" target="_blank">Ouvrir</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>Visualisation Logs <span class="tag">LOGS</span></h4>
        <p class="desc">Logs de visite filtrables (date, IP, action, page). Mode BDD ou Fichier.</p>
        <a class="url" href="<?= $_localBase ?>/admin/logs.php" target="_blank"><?= $_localBase ?>/admin/logs.php</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/logs.php" target="_blank">Ouvrir</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>Vider le cache <span class="tag tag-warn">CACHE</span></h4>
        <p class="desc">Cache fichier JSON (tout ou par prefixe).</p>
        <a class="url" href="<?= $_localBase ?>/admin/clear_cache.php" target="_blank"><?= $_localBase ?>/admin/clear_cache.php?prefix=X</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/clear_cache.php" target="_blank">Tout</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/clear_cache.php?prefix=clubstats" target="_blank">Clubs</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/clear_cache.php?prefix=search" target="_blank">Search</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>Remote Check API <span class="tag">API</span></h4>
        <p class="desc">Endpoints JSON : count, users, sessions, query SQL.</p>
        <a class="url" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=count" target="_blank"><?= $_localBase ?>/admin/remote_check.php</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=count" target="_blank">count</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=users" target="_blank">users</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=ping" target="_blank">ping</a>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- TAB : Chemins -->
<div class="tab-pane" id="tab-paths">
  <div class="section">
    <h3>Chemins des fichiers cles</h3>

    <h4 style="color:#fff;margin:14px 0 8px;font-size:13px">Configuration</h4>
    <div class="path-row"><span class="label">Credentials prod</span> <span class="path">core/credentials.php</span></div>
    <div class="path-row"><span class="label">Credentials local</span> <span class="path">core/credentials_local.php</span></div>
    <div class="path-row"><span class="label">OAuth Google</span> <span class="path">core/oauth_config.php + oauth_credentials.php</span></div>
    <div class="path-row"><span class="label">Connexion BDD</span> <span class="path">core/db.php</span></div>
    <div class="path-row"><span class="label">Helpers URLs</span> <span class="path">core/paths.php</span></div>
    <div class="path-row"><span class="label">Source BDD/File</span> <span class="path">config/data_source.json</span></div>

    <h4 style="color:#fff;margin:14px 0 8px;font-size:13px">Donnees / Archives</h4>
    <div class="path-row"><span class="label">Archives JSONL</span> <span class="path">archives/*.jsonl</span></div>
    <div class="path-row"><span class="label">Cache JSON</span> <span class="path">cache/*.json</span></div>
    <div class="path-row"><span class="label">Donnees scraping</span> <span class="path">src/*.php</span></div>
    <div class="path-row"><span class="label">Schema BDD</span> <span class="path">core/dbCheck_athle.php</span></div>

    <h4 style="color:#fff;margin:14px 0 8px;font-size:13px">Logs &amp; Sessions</h4>
    <div class="path-row"><span class="label">Log IP mensuel</span> <span class="path">logs/ip_track_YYYY-MM.php</span></div>
    <div class="path-row"><span class="label">Sessions super admin</span> <span class="path">logs/.sa_sessions.php</span></div>
    <div class="path-row"><span class="label">Tentatives login</span> <span class="path">logs/.admin_attempts.php</span></div>
    <div class="path-row"><span class="label">Limites recherche</span> <span class="path">logs/.search_limits.php</span></div>
    <div class="path-row"><span class="label">Limites pages</span> <span class="path">logs/.page_limits.php</span></div>

    <h4 style="color:#fff;margin:14px 0 8px;font-size:13px">Code applicatif</h4>
    <div class="path-row"><span class="label">Page principale</span> <span class="path">index.php</span></div>
    <div class="path-row"><span class="label">API REST</span> <span class="path">api/*.php</span></div>
    <div class="path-row"><span class="label">Auth</span> <span class="path">core/auth.php</span></div>
    <div class="path-row"><span class="label">Scraper</span> <span class="path">Class/AthleteScraper.php</span></div>
  </div>
</div>

<!-- TAB : Extraction -->
<div class="tab-pane" id="tab-extract">
  <div class="section">
    <h3>Extraire les donnees</h3>
    <p style="color:var(--text-dim);font-size:12px;margin:0 0 16px">3 methodes pour extraire les donnees.</p>
    <div class="tool-grid">

      <div class="tool-card">
        <h4>1. Archive Manager <span class="tag tag-ok">RECOMMANDE</span></h4>
        <p class="desc">Export table -&gt; .jsonl avec CREATE TABLE inclus (portable, install ailleurs).</p>
        <a class="url" href="<?= $_localBase ?>/admin/db_archive.php?bk_key=<?= $_bkKey ?>" target="_blank"><?= $_localBase ?>/admin/db_archive.php</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/db_archive.php?bk_key=<?= $_bkKey ?>" target="_blank">Ouvrir</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>2. API directe JSON</h4>
        <p class="desc">Appel HTTP des endpoints API.</p>
        <a class="url" href="<?= $_localBase ?>/api/" target="_blank"><?= $_localBase ?>/api/{endpoint}.php</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/api/stats.php?detail=1&top=30" target="_blank">stats</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/api/clubs.php" target="_blank">clubs</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/api/epreuves.php" target="_blank">epreuves</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/api/villes.php" target="_blank">villes</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>3. Remote Check SQL</h4>
        <p class="desc">Execute SELECT en lecture seule via URL.</p>
        <a class="url" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=query&q=SELECT..." target="_blank">action=query&q=...</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=query&q=<?= urlencode('SELECT COUNT(*) FROM athletes') ?>" target="_blank">Count athletes</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=count" target="_blank">Count all</a>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- TAB : Routes -->
<div class="tab-pane" id="tab-routes">
  <div class="section">
    <h3>Routes principales du site</h3>
    <div class="tool-grid">
      <?php
      $routes = [
        ['', 'Accueil', 'Stats globales'],
        ['/recherche', 'Recherche', '12 filtres combinables'],
        ['/athletes', 'Athletes', 'Liste paginee'],
        ['/clubs', 'Clubs', 'Tous les clubs'],
        ['/epreuves', 'Epreuves', 'Toutes les disciplines'],
        ['/villes', 'Villes', 'Stats par ville'],
        ['/comparer', 'Comparer', 'Athletes/clubs'],
        ['/tuto', 'Tutoriel', '8 sections'],
        ['/profil/12345', 'Profil athlete', 'Fiche complete'],
        ['/login.php', 'Connexion', 'Google + admin'],
        ['/admin/panel.php', 'Panel admin', 'Dashboard'],
      ];
      foreach ($routes as $r):
        [$path, $label, $desc] = $r;
      ?>
      <div class="tool-card">
        <h4><?= htmlspecialchars($label) ?></h4>
        <p class="desc"><?= htmlspecialchars($desc) ?></p>
        <a class="url" href="<?= $_localBase . $path ?>" target="_blank"><?= htmlspecialchars($_localBase . $path) ?></a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function showTab(name) {
  document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
  const target = document.getElementById('tab-' + name);
  if (target) target.classList.add('active');
  history.replaceState(null, '', '#tab-' + name);
  document.querySelectorAll('.actions-bar .btn').forEach(b => {
    if (b.getAttribute('href') && b.getAttribute('href').startsWith('#tab-')) b.classList.remove('btn-primary-link');
  });
  const activeBtn = document.querySelector('.actions-bar .btn[href="#tab-' + name + '"]');
  if (activeBtn) activeBtn.classList.add('btn-primary-link');
}
function copyUrl(url, btn) {
  navigator.clipboard.writeText(url).then(() => {
    const orig = btn.textContent;
    btn.textContent = 'Copie !';
    setTimeout(() => { btn.textContent = orig; }, 1500);
  });
}
(function() {
  const hash = location.hash;
  if (hash && hash.startsWith('#tab-')) showTab(hash.slice(5));
})();
</script>

</div>
</body>
</html>
