<?php
/**
 * admin/local_setup.php — Setup local : auth + verification des tables
 *
 * 1) Demande username/password (super admin)
 * 2) Apres login, affiche l'etat des tables (existantes vs manquantes)
 * 3) Liens vers les archives pour installer les tables manquantes
 *
 * URL : http://localhost/BK/admin/local_setup.php
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/credentials.php';
require_once __DIR__ . '/../core/paths.php';

// Liste des tables attendues (depuis dbCheck_athle.php + autres scripts)
$expectedTables = [
    // Reference
    'villes', 'clubs', 'epreuves', 'competitions', 'categories', 'nationalites',
    // Athletes (centrale + enfants)
    'athletes', 'athlete_clubs', 'athlete_medailles', 'athlete_selections',
    'athlete_progressions', 'athlete_records', 'athlete_podiums', 'athlete_resultats',
    'athlete_niveaux', 'athlete_niv_perfs', 'athlete_perfs_manuelles',
    // Auth
    'users', 'user_sessions', 'coach_athletes', 'password_resets',
    // Tracking
    'logs', 'athlete_follows', 'club_follows', 'email_subscribers',
    'athlete_vues_ip', 'club_vues_ip', 'search_tracking',
    // Contact + reports
    'contact_messages', 'sent_emails', 'contact_confirm_tokens',
    'profile_reports', 'profile_hide_tokens',
];

// === Auth simple via POST username/password ===
session_start();
$msg = '';
$msgType = '';
$authenticated = !empty($_SESSION['local_setup_ok']);

// AUTO-AUTH si cookie bk_sa_token deja present et valide
if (!$authenticated && !empty($_COOKIE['bk_sa_token'])) {
    $saFile = __DIR__ . '/../logs/.sa_sessions.php';
    if (file_exists($saFile)) {
        $raw = file_get_contents($saFile);
        $pos = strpos($raw, "\n");
        $sessions = $pos !== false ? (json_decode(substr($raw, $pos + 1), true) ?: []) : [];
        $token = $_COOKIE['bk_sa_token'];
        if (isset($sessions[$token]) && ($sessions[$token]['expires'] ?? 0) > time()) {
            $_SESSION['local_setup_ok'] = true;
            $authenticated = true;
        }
    }
}

// Credentials d'auth dedies au formulaire (defaut : prod creds, override par credentials_local.php)
$authUser = $GLOBALS['localAuthUser'] ?? $username;
$authPass = $GLOBALS['localAuthPass'] ?? $GLOBALS['password'];

if (!$authenticated && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if ($u === $authUser && $p === $authPass) {
        $_SESSION['local_setup_ok'] = true;
        $authenticated = true;

        // === Genere un cookie bk_sa_token (super admin) reconnu par TOUT le site ===
        $saToken = bin2hex(random_bytes(32));
        $expiry = time() + 86400 * 30; // 30 jours en local
        $saFile = __DIR__ . '/../logs/.sa_sessions.php';
        @mkdir(dirname($saFile), 0755, true);
        $sessions = [];
        if (file_exists($saFile)) {
            $raw = file_get_contents($saFile);
            $pos = strpos($raw, "\n");
            if ($pos !== false) $sessions = json_decode(substr($raw, $pos + 1), true) ?: [];
        }
        // Nettoie les sessions expirees
        $sessions = array_filter($sessions, fn($s) => ($s['expires'] ?? 0) > time());
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'localhost';
        $sessions[$saToken] = ['created' => date('Y-m-d H:i:s'), 'expires' => $expiry, 'ip' => $ip, 'source' => 'local_setup'];
        @file_put_contents($saFile, "<?php die('Acces interdit'); ?>\n" . json_encode($sessions, JSON_PRETTY_PRINT));

        setcookie('bk_sa_token', $saToken, [
            'expires'  => $expiry,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $msg = 'Authentification reussie. Cookie super admin defini : connecte dans tout le site.';
        $msgType = 'ok';
    } else {
        $msg = 'Identifiants incorrects (attendu : ' . htmlspecialchars($authUser) . ' / ' . (BK_IS_LOCAL ? '"' . htmlspecialchars($authPass) . '"' : '••••••') . ')';
        $msgType = 'err';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['local_setup_ok']);
    // Supprime aussi le cookie super admin
    if (!empty($_COOKIE['bk_sa_token'])) {
        $saFile = __DIR__ . '/../logs/.sa_sessions.php';
        if (file_exists($saFile)) {
            $raw = file_get_contents($saFile);
            $pos = strpos($raw, "\n");
            $sessions = $pos !== false ? (json_decode(substr($raw, $pos + 1), true) ?: []) : [];
            unset($sessions[$_COOKIE['bk_sa_token']]);
            @file_put_contents($saFile, "<?php die('Acces interdit'); ?>\n" . json_encode($sessions, JSON_PRETTY_PRINT));
        }
        setcookie('bk_sa_token', '', ['expires' => time() - 3600, 'path' => '/']);
    }
    header('Location: local_setup.php');
    exit;
}

// === ACTION : creer toutes les tables manquantes ===
$createMessages = [];
if ($authenticated && ($_POST['action'] ?? '') === 'create_tables') {
    @set_time_limit(120);

    try {
        // 1) Cree les tables via dbCheck_athle.php (29 tables principales)
        require_once dirname(__DIR__) . '/Class/DatabaseHandler.php';
        $dbnameLocal = $conn->query("SELECT DATABASE()")->fetch_row()[0];
        // Recupere les credentials actifs (locaux ou prod) depuis ce qui est deja en memoire
        $dbHostLocal = 'localhost';
        $dbUserLocal = $username; // de credentials_local.php si on est en local
        $dbPassLocal = $GLOBALS['password'];

        $databaseHandler = new DatabaseHandler($dbnameLocal, $dbUserLocal, $dbPassLocal);
        ob_start();
        require dirname(__DIR__) . '/core/dbCheck_athle.php';
        ob_end_clean();
        $databaseHandler->closeConnection();
        $createMessages[] = "Tables principales creees via dbCheck_athle.php (29 tables)";

        // 2) Cree les tables creees a la volee par les autres scripts
        // sent_emails (api/contact.php, api/report.php)
        $conn->query("CREATE TABLE IF NOT EXISTS `sent_emails` (
            `id_sent` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `to_email` VARCHAR(255) NOT NULL DEFAULT '',
            `to_name` VARCHAR(255) DEFAULT '',
            `subject` VARCHAR(500) DEFAULT '',
            `body` TEXT,
            `source` ENUM('contact','report','user_msg','other') DEFAULT 'other',
            `ref_id` INT UNSIGNED DEFAULT NULL,
            `sent_by` VARCHAR(100) DEFAULT '',
            `success` TINYINT(1) UNSIGNED DEFAULT 1,
            `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $createMessages[] = "Table sent_emails OK";

        // contact_confirm_tokens (api/contact.php)
        $conn->query("CREATE TABLE IF NOT EXISTS `contact_confirm_tokens` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `nom` VARCHAR(100) DEFAULT '',
            `email` VARCHAR(200) NOT NULL,
            `message` TEXT NOT NULL,
            `token` VARCHAR(64) NOT NULL UNIQUE,
            `used` TINYINT(1) UNSIGNED DEFAULT 0,
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $createMessages[] = "Table contact_confirm_tokens OK";

        // profile_reports (api/report.php)
        $conn->query("CREATE TABLE IF NOT EXISTS `profile_reports` (
            `id_report` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ip` VARCHAR(45) NOT NULL DEFAULT '',
            `athlete_id_ext` INT UNSIGNED NOT NULL,
            `athlete_name` VARCHAR(200) DEFAULT '',
            `reason` VARCHAR(50) NOT NULL DEFAULT '',
            `message` TEXT,
            `email` VARCHAR(200) DEFAULT '',
            `status` ENUM('new','read','resolved') DEFAULT 'new',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_pr_athlete` (`athlete_id_ext`),
            INDEX `idx_pr_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $createMessages[] = "Table profile_reports OK";

        // profile_hide_tokens (api/report.php, api/auth/confirm_hide.php)
        $conn->query("CREATE TABLE IF NOT EXISTS `profile_hide_tokens` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `athlete_id_ext` INT UNSIGNED NOT NULL,
            `athlete_name` VARCHAR(200) DEFAULT '',
            `email` VARCHAR(200) NOT NULL,
            `token` VARCHAR(64) NOT NULL UNIQUE,
            `used` TINYINT(1) UNSIGNED DEFAULT 0,
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $createMessages[] = "Table profile_hide_tokens OK";

        $msg = "Creation terminee : " . count($createMessages) . " etapes reussies.";
        $msgType = 'ok';
    } catch (Throwable $e) {
        $msg = "Erreur lors de la creation : " . $e->getMessage();
        $msgType = 'err';
    }
}

// === Verification tables si authentifie ===
$existing = [];
$missing = [];
$totalRows = 0;
$dbName = '';
if ($authenticated) {
    $dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];
    $r = $conn->query("SHOW TABLES");
    $existingAll = [];
    while ($row = $r->fetch_row()) $existingAll[] = $row[0];

    foreach ($expectedTables as $t) {
        if (in_array($t, $existingAll, true)) {
            $rows = (int)$conn->query("SELECT COUNT(*) FROM `$t`")->fetch_row()[0];
            $existing[$t] = $rows;
            $totalRows += $rows;
        } else {
            $missing[] = $t;
        }
    }

    // Verifie aussi les fichiers d'archive disponibles pour chaque table manquante
    $archives = [];
    foreach (glob(__DIR__ . '/../archives/*.jsonl') as $f) {
        $name = basename($f);
        if (preg_match('/^(.+?)_\d{4}-\d{2}-\d{2}_\d{6}\.jsonl$/', $name, $m)) {
            $archives[$m[1]][] = $name;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup Local — BK</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0a0e14; --panel: #11161f; --border: #232b3a;
    --text: #d9e1ec; --text-dim: #7a869a; --text-muted: #525d72;
    --accent: #6366f1; --success: #10b981; --danger: #ef4444; --warning: #f59e0b;
  }
  * { box-sizing: border-box; }
  body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); padding: 24px; margin: 0; min-height: 100vh; }
  .wrap { max-width: 900px; margin: 0 auto; }
  h1 { font-size: 24px; margin: 0 0 8px; color: #fff; letter-spacing: -0.5px; }
  .subtitle { color: var(--text-dim); font-size: 13px; margin-bottom: 28px; }

  /* Login card */
  .login-card { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 32px; max-width: 420px; margin: 60px auto; }
  .login-card h2 { margin: 0 0 6px; font-size: 18px; color: #fff; }
  .login-card .lead { color: var(--text-dim); font-size: 13px; margin-bottom: 22px; }
  .form-row { margin-bottom: 14px; }
  .form-row label { display: block; color: var(--text-dim); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
  .form-row input { width: 100%; padding: 11px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 14px; font-family: 'JetBrains Mono', monospace; outline: none; }
  .form-row input:focus { border-color: var(--accent); }
  .btn-primary { width: 100%; padding: 12px; background: linear-gradient(135deg, var(--accent), #8b5cf6); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: transform 0.15s; }
  .btn-primary:hover { transform: translateY(-1px); }

  .msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; border: 1px solid; }
  .msg.ok { background: rgba(16,185,129,0.08); color: #6ee7b7; border-color: rgba(16,185,129,0.3); }
  .msg.err { background: rgba(239,68,68,0.08); color: #fca5a5; border-color: rgba(239,68,68,0.3); }

  /* KPI cards */
  .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
  .kpi { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; }
  .kpi .label { color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; }
  .kpi .value { font-size: 26px; font-weight: 700; color: #fff; line-height: 1; }
  .kpi.ok .value { color: #6ee7b7; }
  .kpi.warn .value { color: #fcd34d; }
  .kpi.danger .value { color: #fca5a5; }

  /* Tables list */
  .section { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
  .section h3 { margin: 0 0 14px; font-size: 14px; color: #fff; display: flex; align-items: center; gap: 8px; }
  .section h3 .count { background: var(--bg); border: 1px solid var(--border); padding: 2px 9px; border-radius: 12px; font-size: 12px; color: var(--text-dim); font-weight: 500; }
  .tbl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; }
  .tbl-item { padding: 8px 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; font-family: 'JetBrains Mono', monospace; font-size: 12px; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
  .tbl-item.exists { border-left: 3px solid var(--success); }
  .tbl-item.missing { border-left: 3px solid var(--danger); background: rgba(239,68,68,0.04); }
  .tbl-item.empty { border-left: 3px solid var(--warning); }
  .tbl-item .name { color: #fff; font-weight: 500; }
  .tbl-item .rows { color: var(--text-dim); font-size: 11px; }
  .tbl-item .arc { font-size: 10px; color: var(--text-muted); margin-top: 2px; }
  .install-link { background: rgba(99,102,241,0.15); color: #a5b4fc; padding: 3px 7px; border-radius: 4px; font-size: 10px; text-decoration: none; border: 1px solid rgba(99,102,241,0.3); }
  .install-link:hover { background: rgba(99,102,241,0.25); }
  .no-arc { color: var(--text-muted); font-size: 10px; }

  .actions-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
  .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; background: var(--panel); color: var(--text); border: 1px solid var(--border); border-radius: 8px; font-size: 13px; cursor: pointer; text-decoration: none; transition: all 0.15s; }
  .btn:hover { background: rgba(99,102,241,0.1); border-color: var(--accent); }
  .btn-primary-link { background: linear-gradient(135deg, var(--accent), #8b5cf6); color: #fff; border-color: var(--accent); }
  .btn-logout { color: #fca5a5; border-color: rgba(239,68,68,0.3); }
  .pill { display: inline-flex; align-items: center; gap: 6px; background: var(--panel); border: 1px solid var(--border); padding: 6px 12px; border-radius: 20px; font-size: 11px; color: var(--text-dim); margin-bottom: 18px; font-family: 'JetBrains Mono', monospace; }
</style>
</head>
<body>
<div class="wrap">

<?php if (!$authenticated): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ETAPE 1 : AUTHENTIFICATION                  -->
<!-- ═══════════════════════════════════════════ -->
<?php if (!empty($_GET['from'])): ?>
<div style="max-width:420px;margin:30px auto 0;padding:14px 18px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:10px;color:#fcd34d;font-size:13px;text-align:center;line-height:1.6">
  <b>Redirection automatique</b><br>
  La table <code>athletes</code> n'existe pas en BDD locale.<br>
  Connecte-toi pour creer les tables manquantes.<br>
  <span style="font-size:11px;color:#92400e">Source : <code><?= htmlspecialchars($_GET['from']) ?></code></span>
</div>
<?php endif; ?>

<div class="login-card">
  <h2>Setup local BK</h2>
  <p class="lead">Saisis tes identifiants Super Admin pour acceder a la verification des tables.</p>

  <?php if ($msg): ?>
    <div class="msg <?= $msgType ?>"><?= $msg ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="form-row">
      <label>Identifiant</label>
      <input type="text" name="username" required autofocus placeholder="<?= htmlspecialchars($authUser) ?>">
    </div>
    <div class="form-row">
      <label>Mot de passe</label>
      <input type="password" name="password" required placeholder="<?= BK_IS_LOCAL ? htmlspecialchars($authPass) : '********' ?>">
    </div>
    <button type="submit" class="btn-primary">Verifier et acceder</button>
  </form>

  <p style="margin-top:18px;font-size:11px;color:var(--text-muted);text-align:center;font-family:'JetBrains Mono',monospace;line-height:1.6">
    <?php if (BK_IS_LOCAL): ?>
      Mode local : <code><?= htmlspecialchars($authUser) ?></code> / <code><?= htmlspecialchars($authPass) ?></code><br>
      <span style="color:#525d72">(modifiable dans <code>core/credentials_local.php</code>)</span>
    <?php else: ?>
      Identifiants Super Admin Hostinger (<code><?= htmlspecialchars($authUser) ?></code>)
    <?php endif; ?>
  </p>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ETAPE 2 : VERIFICATION DES TABLES           -->
<!-- ═══════════════════════════════════════════ -->
<h1>Setup local BK</h1>
<div class="subtitle">Authentifie comme <code><?= htmlspecialchars($username) ?></code></div>
<div class="pill">BDD active : <b><?= htmlspecialchars($dbName) ?></b></div>

<?php if ($msg): ?><div class="msg <?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<div class="kpis">
  <div class="kpi <?= empty($missing) ? 'ok' : 'danger' ?>">
    <div class="label">Tables attendues</div>
    <div class="value"><?= count($expectedTables) ?></div>
  </div>
  <div class="kpi <?= empty($existing) ? 'danger' : 'ok' ?>">
    <div class="label">Presentes</div>
    <div class="value"><?= count($existing) ?></div>
  </div>
  <div class="kpi <?= empty($missing) ? 'ok' : 'warn' ?>">
    <div class="label">Manquantes</div>
    <div class="value"><?= count($missing) ?></div>
  </div>
  <div class="kpi ok">
    <div class="label">Lignes totales</div>
    <div class="value" style="font-size:20px"><?= number_format($totalRows, 0, ',', ' ') ?></div>
  </div>
</div>

<div class="actions-bar">
  <a class="btn btn-primary-link" href="#tab-overview" onclick="showTab('overview')">Vue d'ensemble</a>
  <a class="btn" href="#tab-tools" onclick="showTab('tools')">Outils admin</a>
  <a class="btn" href="#tab-paths" onclick="showTab('paths')">Chemins fichiers</a>
  <a class="btn" href="#tab-extract" onclick="showTab('extract')">Extraction donnees</a>
  <a class="btn" href="#tab-routes" onclick="showTab('routes')">Routes du site</a>
  <a class="btn" href="../index.php">Voir le site</a>
  <a class="btn btn-logout" href="?logout=1">Deconnexion</a>
</div>

<style>
.tab-pane { display: none; }
.tab-pane.active { display: block; }
.tool-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; }
.tool-card { background: var(--panel); border: 1px solid var(--border); border-radius: 10px; padding: 16px; transition: all 0.2s; }
.tool-card:hover { border-color: var(--accent); transform: translateY(-2px); }
.tool-card h4 { margin: 0 0 6px; color: #fff; font-size: 14px; display: flex; align-items: center; gap: 8px; }
.tool-card .desc { color: var(--text-dim); font-size: 12px; margin: 0 0 10px; line-height: 1.5; min-height: 32px; }
.tool-card .url { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #a5b4fc; background: var(--bg); padding: 6px 10px; border-radius: 6px; word-break: break-all; display: block; margin-bottom: 8px; }
.tool-card .actions-row { display: flex; gap: 6px; flex-wrap: wrap; }
.tool-card .btn-mini { padding: 5px 10px; font-size: 11px; }
.copy-btn { background: rgba(99,102,241,0.15); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3); }
.copy-btn:hover { background: rgba(99,102,241,0.25); }
.path-row { padding: 8px 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; margin-bottom: 6px; font-family: 'JetBrains Mono', monospace; font-size: 12px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.path-row .label { color: var(--text-dim); }
.path-row .path { color: #fff; }
.tag { display: inline-block; padding: 1px 7px; background: rgba(99,102,241,0.15); color: #a5b4fc; border-radius: 10px; font-size: 10px; font-weight: 600; }
.tag-warn { background: rgba(245,158,11,0.15); color: #fcd34d; }
.tag-danger { background: rgba(239,68,68,0.15); color: #fca5a5; }
.tag-ok { background: rgba(16,185,129,0.15); color: #6ee7b7; }
</style>

<?php
// Detection du domaine actuel pour generer les bons liens
$_localBase = (BK_IS_LOCAL ? 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') : 'https://bokonzi.com') . BK_BASE;
$_bkKey = 'bk_s3cr3t_2026_xK9mP';
?>

<!-- ════════════════════════════════════════════ -->
<!-- TAB : Vue d'ensemble (par defaut)            -->
<!-- ════════════════════════════════════════════ -->
<div class="tab-pane active" id="tab-overview">

<?php if (!empty($missing)): ?>
<div class="section">
  <h3 style="color:#fca5a5">Tables manquantes <span class="count"><?= count($missing) ?></span></h3>
  <p style="color:var(--text-dim);font-size:12px;margin:0 0 12px">Ces tables n'existent pas dans la BDD locale. Tu peux les <b>creer toutes en un clic</b> (vides, schema identique a la prod), puis installer les donnees via les archives.</p>

  <form method="POST" style="margin-bottom:14px" onsubmit="return confirm('Creer toutes les tables manquantes (' + <?= count($missing) ?> + ') dans la BDD ' + <?= json_encode($dbName) ?> + ' ?\n\nLes tables seront creees VIDES avec le bon schema.\nLes tables existantes ne seront PAS touchees.')">
    <input type="hidden" name="action" value="create_tables">
    <button type="submit" style="padding:11px 20px;background:linear-gradient(135deg,var(--accent),#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit">
      Creer toutes les tables manquantes (<?= count($missing) ?>)
    </button>
  </form>

  <?php if (!empty($createMessages)): ?>
  <div style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.25);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.7">
    <?php foreach ($createMessages as $cm): ?>
      <div style="color:#6ee7b7">&#10003; <?= htmlspecialchars($cm) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="tbl-grid">
    <?php foreach ($missing as $t):
      $hasArc = !empty($archives[$t]);
    ?>
    <div class="tbl-item missing">
      <div>
        <div class="name"><?= htmlspecialchars($t) ?></div>
        <?php if ($hasArc): ?>
          <div class="arc">Archive : <?= htmlspecialchars($archives[$t][0]) ?></div>
        <?php else: ?>
          <div class="no-arc">Aucune archive disponible</div>
        <?php endif; ?>
      </div>
      <?php if ($hasArc): ?>
        <a class="install-link" href="db_archive.php?bk_key=bk_s3cr3t_2026_xK9mP" title="Aller installer cette archive">Installer</a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($existing)): ?>
<div class="section">
  <h3 style="color:#6ee7b7">Tables presentes <span class="count"><?= count($existing) ?></span></h3>
  <div class="tbl-grid">
    <?php foreach ($existing as $t => $rows): ?>
    <div class="tbl-item <?= $rows === 0 ? 'empty' : 'exists' ?>">
      <span class="name"><?= htmlspecialchars($t) ?></span>
      <span class="rows"><?= number_format($rows, 0, ',', ' ') ?> rows</span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (empty($missing) && !empty($existing)): ?>
<div class="section" style="border-left:3px solid var(--success);background:rgba(16,185,129,0.05)">
  <h3 style="color:#6ee7b7">Tout est OK</h3>
  <p style="color:var(--text);font-size:13px;margin:0">Toutes les tables attendues sont presentes en BDD. Tu peux utiliser le site librement : <a href="../index.php" style="color:#a5b4fc">../index.php</a></p>
</div>
<?php endif; ?>

</div><!-- /tab-overview -->

<!-- ════════════════════════════════════════════ -->
<!-- TAB : Outils admin                          -->
<!-- ════════════════════════════════════════════ -->
<div class="tab-pane" id="tab-tools">
  <div class="section">
    <h3>Outils administration</h3>
    <p style="color:var(--text-dim);font-size:12px;margin:0 0 16px">Tous les outils admin accessibles depuis cette page. Cle API requise : <code><?= htmlspecialchars($_bkKey) ?></code></p>
    <div class="tool-grid">

      <div class="tool-card">
        <h4>Archive Manager <span class="tag tag-ok">PRINCIPAL</span></h4>
        <p class="desc">Export/Import des tables BDD, bascule BDD/Fichier, verification, install depuis archive.</p>
        <a class="url" href="<?= $_localBase ?>/admin/db_archive.php?bk_key=<?= $_bkKey ?>" target="_blank"><?= $_localBase ?>/admin/db_archive.php?bk_key=...</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/db_archive.php?bk_key=<?= $_bkKey ?>" target="_blank">Ouvrir</a>
          <button class="btn btn-mini copy-btn" onclick="copyUrl('<?= $_localBase ?>/admin/db_archive.php?bk_key=<?= $_bkKey ?>', this)">Copier URL</button>
        </div>
      </div>

      <div class="tool-card">
        <h4>Diagnostic taille BDD <span class="tag">DIAGNOSTIC</span></h4>
        <p class="desc">Liste toutes les tables avec leur taille (MB), nombre de lignes, % du total.</p>
        <a class="url" href="<?= $_localBase ?>/admin/db_size.php?bk_key=<?= $_bkKey ?>" target="_blank"><?= $_localBase ?>/admin/db_size.php?bk_key=...</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/db_size.php?bk_key=<?= $_bkKey ?>" target="_blank">Ouvrir</a>
          <button class="btn btn-mini copy-btn" onclick="copyUrl('<?= $_localBase ?>/admin/db_size.php?bk_key=<?= $_bkKey ?>', this)">Copier URL</button>
        </div>
      </div>

      <div class="tool-card">
        <h4>Setup BDD complete <span class="tag tag-warn">SCHEMA</span></h4>
        <p class="desc">Cree la BDD et toutes les tables vides selon le schema dbCheck_athle.php.</p>
        <a class="url" href="<?= $_localBase ?>/admin/setup_bdd.php" target="_blank"><?= $_localBase ?>/admin/setup_bdd.php</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/setup_bdd.php" target="_blank">Ouvrir</a>
          <button class="btn btn-mini copy-btn" onclick="copyUrl('<?= $_localBase ?>/admin/setup_bdd.php', this)">Copier URL</button>
        </div>
      </div>

      <div class="tool-card">
        <h4>Panel Admin <span class="tag">SUPER ADMIN</span></h4>
        <p class="desc">Dashboard complet : KPIs, logs, search tracking, users, messages, signalements.</p>
        <a class="url" href="<?= $_localBase ?>/admin/panel.php" target="_blank"><?= $_localBase ?>/admin/panel.php</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/panel.php" target="_blank">Ouvrir</a>
          <button class="btn btn-mini copy-btn" onclick="copyUrl('<?= $_localBase ?>/admin/panel.php', this)">Copier URL</button>
        </div>
      </div>

      <div class="tool-card">
        <h4>Visualisation Logs <span class="tag">LOGS</span></h4>
        <p class="desc">Logs de visite avec filtres (date, IP, action, page, sid). Mode BDD ou Fichier.</p>
        <a class="url" href="<?= $_localBase ?>/admin/logs.php" target="_blank"><?= $_localBase ?>/admin/logs.php</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/logs.php" target="_blank">Ouvrir</a>
          <button class="btn btn-mini copy-btn" onclick="copyUrl('<?= $_localBase ?>/admin/logs.php', this)">Copier URL</button>
        </div>
      </div>

      <div class="tool-card">
        <h4>Cache : vider <span class="tag tag-warn">CACHE</span></h4>
        <p class="desc">Vide le cache fichier JSON (tout ou par prefixe : clubstats, villestats, ep, search, athlete...).</p>
        <a class="url" href="<?= $_localBase ?>/admin/clear_cache.php" target="_blank"><?= $_localBase ?>/admin/clear_cache.php?prefix=X</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/clear_cache.php" target="_blank">Tout</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/clear_cache.php?prefix=clubstats" target="_blank">Clubs</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/clear_cache.php?prefix=search" target="_blank">Search</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>Remote Check API <span class="tag">API</span></h4>
        <p class="desc">Endpoints JSON : users, sessions, count, query SQL lecture seule, logs, ping.</p>
        <a class="url" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=count" target="_blank"><?= $_localBase ?>/admin/remote_check.php?bk_key=...&action=...</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=count" target="_blank">count</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=users" target="_blank">users</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=ping" target="_blank">ping</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>Fix performance INT <span class="tag tag-warn">FIX</span></h4>
        <p class="desc">Correction des performances INT (padding dixiemes). ?go pour executer.</p>
        <a class="url" href="<?= $_localBase ?>/admin/fix_perf_int.php" target="_blank"><?= $_localBase ?>/admin/fix_perf_int.php?go</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/fix_perf_int.php" target="_blank">Dry run</a>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════ -->
<!-- TAB : Chemins fichiers                       -->
<!-- ════════════════════════════════════════════ -->
<div class="tab-pane" id="tab-paths">
  <div class="section">
    <h3>Chemins des fichiers cles</h3>
    <p style="color:var(--text-dim);font-size:12px;margin:0 0 16px">Localisation des fichiers de configuration, donnees, logs et cache.</p>

    <h4 style="color:#fff;margin:14px 0 8px;font-size:13px">Configuration</h4>
    <div class="path-row"><span class="label">Credentials prod</span> <span class="path">core/credentials.php</span></div>
    <div class="path-row"><span class="label">Credentials local</span> <span class="path">core/credentials_local.php</span></div>
    <div class="path-row"><span class="label">OAuth Google</span> <span class="path">core/oauth_config.php + core/oauth_credentials.php</span></div>
    <div class="path-row"><span class="label">Connexion BDD</span> <span class="path">core/db.php</span></div>
    <div class="path-row"><span class="label">Helpers URLs</span> <span class="path">core/paths.php (BK_BASE, BK_URL, BK_IS_LOCAL)</span></div>
    <div class="path-row"><span class="label">Source mode (BDD/File)</span> <span class="path">config/data_source.json</span></div>

    <h4 style="color:#fff;margin:14px 0 8px;font-size:13px">Donnees / Archives</h4>
    <div class="path-row"><span class="label">Archives JSONL</span> <span class="path">archives/*.jsonl</span></div>
    <div class="path-row"><span class="label">Cache JSON</span> <span class="path">cache/*.json</span></div>
    <div class="path-row"><span class="label">Donnees scraping</span> <span class="path">src/*.php (JSON par athlete)</span></div>
    <div class="path-row"><span class="label">Schema BDD</span> <span class="path">core/dbCheck_athle.php</span></div>

    <h4 style="color:#fff;margin:14px 0 8px;font-size:13px">Logs &amp; Sessions</h4>
    <div class="path-row"><span class="label">Log IP mensuel</span> <span class="path">logs/ip_track_YYYY-MM.php</span></div>
    <div class="path-row"><span class="label">Sessions super admin</span> <span class="path">logs/.sa_sessions.php</span></div>
    <div class="path-row"><span class="label">Tentatives login</span> <span class="path">logs/.admin_attempts.php</span></div>
    <div class="path-row"><span class="label">Limites recherche</span> <span class="path">logs/.search_limits.php</span></div>
    <div class="path-row"><span class="label">Limites pages</span> <span class="path">logs/.page_limits.php</span></div>
    <div class="path-row"><span class="label">IPs ignorees tracking</span> <span class="path">logs/.st_ignored_ips.php</span></div>

    <h4 style="color:#fff;margin:14px 0 8px;font-size:13px">Code applicatif</h4>
    <div class="path-row"><span class="label">Page principale</span> <span class="path">index.php (~8400 lignes)</span></div>
    <div class="path-row"><span class="label">API REST</span> <span class="path">api/*.php</span></div>
    <div class="path-row"><span class="label">API config + CORS</span> <span class="path">api/config.php</span></div>
    <div class="path-row"><span class="label">Authentification</span> <span class="path">core/auth.php</span></div>
    <div class="path-row"><span class="label">Scraper Class</span> <span class="path">Class/AthleteScraper.php</span></div>
    <div class="path-row"><span class="label">DatabaseHandler</span> <span class="path">Class/DatabaseHandler.php</span></div>
  </div>
</div>

<!-- ════════════════════════════════════════════ -->
<!-- TAB : Extraction donnees                     -->
<!-- ════════════════════════════════════════════ -->
<div class="tab-pane" id="tab-extract">
  <div class="section">
    <h3>Extraire les donnees</h3>
    <p style="color:var(--text-dim);font-size:12px;margin:0 0 16px">3 methodes pour extraire les donnees de la BDD.</p>

    <div class="tool-grid">

      <div class="tool-card">
        <h4>1) Export via Archive Manager <span class="tag tag-ok">RECOMMANDE</span></h4>
        <p class="desc">Export table -&gt; fichier .jsonl avec schema CREATE TABLE inclus (portable).</p>
        <a class="url" href="<?= $_localBase ?>/admin/db_archive.php?bk_key=<?= $_bkKey ?>" target="_blank"><?= $_localBase ?>/admin/db_archive.php?bk_key=...</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/db_archive.php?bk_key=<?= $_bkKey ?>" target="_blank">Ouvrir</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>2) API directe (JSON)</h4>
        <p class="desc">Appel HTTP des endpoints API. Cache 24h sauf classement et top_searched.</p>
        <a class="url" href="<?= $_localBase ?>/api/athletes/" target="_blank"><?= $_localBase ?>/api/{endpoint}.php?params</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/api/stats.php?detail=1&top=30" target="_blank">stats</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/api/clubs.php" target="_blank">clubs</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/api/epreuves.php" target="_blank">epreuves</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/api/villes.php" target="_blank">villes</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>3) Remote Check (query SQL)</h4>
        <p class="desc">Execute des requetes SELECT en lecture seule via URL. Utile pour debug.</p>
        <a class="url" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=query&q=SELECT..." target="_blank"><?= $_localBase ?>/admin/remote_check.php?action=query&q=...</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=query&q=<?= urlencode('SELECT COUNT(*) FROM athletes') ?>" target="_blank">Count athletes</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/remote_check.php?bk_key=<?= $_bkKey ?>&action=count" target="_blank">Count all</a>
        </div>
      </div>

      <div class="tool-card">
        <h4>Diagnostic taille</h4>
        <p class="desc">Voir quelles tables sont grosses avant d'extraire (priorisation).</p>
        <a class="url" href="<?= $_localBase ?>/admin/db_size.php?bk_key=<?= $_bkKey ?>" target="_blank"><?= $_localBase ?>/admin/db_size.php?bk_key=...</a>
        <div class="actions-row">
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/db_size.php?bk_key=<?= $_bkKey ?>" target="_blank">Ouvrir</a>
          <a class="btn btn-mini btn-primary-link" href="<?= $_localBase ?>/admin/db_size.php?bk_key=<?= $_bkKey ?>&json=1" target="_blank">JSON</a>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════ -->
<!-- TAB : Routes du site                         -->
<!-- ════════════════════════════════════════════ -->
<div class="tab-pane" id="tab-routes">
  <div class="section">
    <h3>Routes principales du site</h3>
    <p style="color:var(--text-dim);font-size:12px;margin:0 0 16px">Toutes les pages publiques accessibles. Cliquez pour ouvrir en nouvel onglet.</p>
    <div class="tool-grid">
      <?php
      $routes = [
        ['', 'Accueil', 'Stats globales, top clubs/athletes'],
        ['/recherche', 'Recherche', '12 filtres : nom, club, sexe, ville...'],
        ['/athletes', 'Athletes', 'Liste paginee multi-niveaux'],
        ['/clubs', 'Clubs', 'Tous les clubs francais'],
        ['/epreuves', 'Epreuves', 'Toutes les disciplines'],
        ['/villes', 'Villes', 'Stats par ville'],
        ['/comparer', 'Comparer', 'Comparaison athletes / clubs'],
        ['/tuto', 'Tutoriel', '8 sections animees'],
        ['/profil/12345', 'Profil athlete', 'Fiche complete (id externe)'],
        ['/login.php', 'Connexion', 'Login Google + admin'],
        ['/admin/local_setup.php', 'Setup local', 'Cette page (en local)'],
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
  // Update URL hash for shareability
  history.replaceState(null, '', '#tab-' + name);
  // Highlight active button
  document.querySelectorAll('.actions-bar .btn').forEach(b => b.classList.remove('btn-primary-link'));
  const activeBtn = document.querySelector('.actions-bar .btn[href="#tab-' + name + '"]');
  if (activeBtn) activeBtn.classList.add('btn-primary-link');
}

function copyUrl(url, btn) {
  navigator.clipboard.writeText(url).then(() => {
    const orig = btn.textContent;
    btn.textContent = 'Copie !';
    btn.style.background = 'rgba(16,185,129,0.25)';
    btn.style.color = '#6ee7b7';
    setTimeout(() => { btn.textContent = orig; btn.style.background = ''; btn.style.color = ''; }, 1500);
  });
}

// Auto-active tab from URL hash on load
(function() {
  const hash = location.hash;
  if (hash && hash.startsWith('#tab-')) {
    const name = hash.slice(5);
    showTab(name);
  }
})();
</script>

<?php endif; ?>

</div>
</body>
</html>
