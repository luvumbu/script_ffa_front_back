<?php
/**
 * admin/debug_login.php — Lecture du log de debug login
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (($_GET['bk_key'] ?? '') !== 'bk_s3cr3t_2026_xK9mP') {
    http_response_code(403);
    die('Acces refuse');
}

$logFile = __DIR__ . '/../logs/login_debug.log';

// Reset
if (isset($_GET['reset'])) {
    @unlink($logFile);
    header('Location: debug_login.php?bk_key=' . urlencode($_GET['bk_key']));
    exit;
}

$content = file_exists($logFile) ? file_get_contents($logFile) : '(fichier inexistant — aucune connexion ne l\'a encore declenche)';
$lines = $content ? explode("\n", trim($content)) : [];
$nbLines = count(array_filter($lines));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Debug Login</title>
<style>
body { font-family: monospace; background: #0d1117; color: #c9d1d9; padding: 24px; max-width: 1100px; margin: 0 auto; }
h1 { color: #58a6ff; }
.box { background: #161b22; border: 1px solid #30363d; padding: 16px; border-radius: 8px; margin-bottom: 16px; }
pre { background: #0d1117; padding: 12px; border-radius: 6px; overflow-x: auto; border: 1px solid #30363d; line-height: 1.5; }
a { color: #f85149; text-decoration: none; padding: 6px 12px; border: 1px solid #f85149; border-radius: 6px; }
a:hover { background: #f85149; color: #fff; }
.ok { color: #3fb950; }
.ko { color: #f85149; }
</style>
</head>
<body>
<h1>Log de debug login (<?= $nbLines ?> entrees)</h1>

<div class="box">
    <strong>Fichier :</strong> <?= htmlspecialchars($logFile) ?><br>
    <strong>Existe :</strong> <?= file_exists($logFile) ? 'OUI (' . filesize($logFile) . ' octets)' : 'NON' ?><br>
    <strong>Derniere modif :</strong> <?= file_exists($logFile) ? date('Y-m-d H:i:s', filemtime($logFile)) : '-' ?><br>
    <strong>Heure serveur :</strong> <?= date('Y-m-d H:i:s') ?>
</div>

<div class="box">
    <h3>Contenu</h3>
    <pre><?= htmlspecialchars($content) ?></pre>
</div>

<a href="?bk_key=<?= urlencode($_GET['bk_key']) ?>&reset=1" onclick="return confirm('Vider le log ?')">Vider le log</a>
&nbsp;<a href="?bk_key=<?= urlencode($_GET['bk_key']) ?>" style="border-color:#58a6ff;color:#58a6ff;">Rafraichir</a>
</body>
</html>
