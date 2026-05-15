<?php
/**
 * admin/test_mail.php — Envoie 3 mails de test automatiquement a l'ouverture
 * Acces protege par cookie super admin OU bk_key
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../core/db.php';

function isSA() {
    if (empty($_COOKIE['bk_sa_token'])) return false;
    $f = __DIR__ . '/../logs/.sa_sessions.php';
    if (!file_exists($f)) return false;
    $raw = file_get_contents($f);
    $pos = strpos($raw, "\n");
    if ($pos === false) return false;
    $s = json_decode(substr($raw, $pos + 1), true) ?: [];
    return isset($s[$_COOKIE['bk_sa_token']]) && ($s[$_COOKIE['bk_sa_token']]['expires'] ?? 0) > time();
}
if (!isSA() && ($_GET['bk_key'] ?? '') !== 'bk_s3cr3t_2026_xK9mP') {
    http_response_code(403);
    die('Acces refuse. Ajouter ?bk_key=bk_s3cr3t_2026_xK9mP a l\'URL ou se connecter en super admin.');
}

$to = $_GET['to'] ?? 'luvumbu.n@gmail.com';
$results = [];

// =====================================================
// TEST 1 : mail() natif avec From=noreply@bokonzi.com
// =====================================================
$h1 = "MIME-Version: 1.0\r\n";
$h1 .= "Content-Type: text/html; charset=UTF-8\r\n";
$h1 .= "From: Bokonzi <noreply@bokonzi.com>\r\n";
$h1 .= "Reply-To: noreply@bokonzi.com\r\n";
$ok1 = mail($to, '[TEST 1] mail() From=noreply - ' . date('H:i:s'), '<h1>Test 1</h1><p>Envoye le ' . date('Y-m-d H:i:s') . '</p>', $h1);
$err1 = error_get_last();
$results[] = [
    'test' => '1. mail() natif avec From=noreply@bokonzi.com',
    'success' => $ok1,
    'error' => $err1
];

// =====================================================
// TEST 2 : mail() natif avec From=contact@bokonzi.com
// =====================================================
$h2 = "MIME-Version: 1.0\r\n";
$h2 .= "Content-Type: text/html; charset=UTF-8\r\n";
$h2 .= "From: Bokonzi <contact@bokonzi.com>\r\n";
$h2 .= "Reply-To: contact@bokonzi.com\r\n";
$ok2 = mail($to, '[TEST 2] mail() From=contact - ' . date('H:i:s'), '<h1>Test 2</h1><p>Envoye le ' . date('Y-m-d H:i:s') . '</p>', $h2, '-f contact@bokonzi.com');
$err2 = error_get_last();
$results[] = [
    'test' => '2. mail() natif avec From=contact@bokonzi.com (+ -f flag)',
    'success' => $ok2,
    'error' => $err2
];

// =====================================================
// TEST 3 : SMTP authentifie via bkMail()
// =====================================================
require_once __DIR__ . '/../core/mailer.php';
$ok3 = bkMail($to, '[TEST 3] bkMail SMTP - ' . date('H:i:s'), '<h1>Test 3</h1><p>Envoye le ' . date('Y-m-d H:i:s') . '</p>');
$results[] = [
    'test' => '3. bkMail() SMTP authentifie',
    'success' => $ok3,
    'smtp_pass_configure' => BK_SMTP_PASS !== '' ? 'OUI' : 'NON (vide → fallback mail() natif)',
    'smtp_host' => BK_SMTP_HOST,
    'smtp_user' => BK_SMTP_USER
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Test Mail - Resultats</title>
<style>
body { font-family: -apple-system, monospace; background: #0d1117; color: #c9d1d9; padding: 24px; max-width: 900px; margin: 0 auto; line-height: 1.6; }
h1 { color: #58a6ff; }
.box { background: #161b22; border: 1px solid #30363d; padding: 16px 20px; border-radius: 8px; margin-bottom: 16px; }
.box h3 { margin: 0 0 10px; }
.ok { color: #3fb950; font-weight: bold; }
.ko { color: #f85149; font-weight: bold; }
pre { background: #0d1117; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 12px; border: 1px solid #30363d; }
code { background: #21262d; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
.diag { background: #1c1606; border: 1px solid #f59e0b; }
</style>
</head>
<body>
<h1>Test envoi mail — <?= date('Y-m-d H:i:s') ?></h1>

<div class="box">
    <strong>Destinataire :</strong> <?= htmlspecialchars($to) ?> &nbsp;
    <small>(changer avec <code>?to=autre@email.com</code>)</small>
</div>

<?php foreach ($results as $r): ?>
    <div class="box">
        <h3><?= htmlspecialchars($r['test']) ?></h3>
        <p>Resultat PHP : <span class="<?= $r['success'] ? 'ok' : 'ko' ?>"><?= $r['success'] ? '✓ TRUE (envoi accepte)' : '✗ FALSE (echec)' ?></span></p>
        <pre><?= htmlspecialchars(print_r($r, true)) ?></pre>
    </div>
<?php endforeach; ?>

<div class="box diag">
    <h3>Comment lire le resultat</h3>
    <ul>
        <li><strong>TRUE</strong> = PHP a accepte d'envoyer (ne garantit PAS la livraison)</li>
        <li><strong>FALSE</strong> = echec immediat (mail() pas dispo, SMTP refuse, etc.)</li>
    </ul>
    <p>Maintenant <strong>verifie ta boite mail luvumbu.n@gmail.com (boite + spam)</strong> et compte combien de mails arrivent (Test 1, 2, 3).</p>
    <p>Tu vas avoir 3 mails avec [TEST 1], [TEST 2], [TEST 3] dans le sujet. Dis-moi lesquels sont arrives.</p>
</div>

<div class="box">
    <h3>Infos serveur</h3>
    <pre>HTTP Host : <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') ?>
PHP version : <?= phpversion() ?>
sendmail_path : <?= ini_get('sendmail_path') ?: '(vide)' ?>
SMTP php.ini : <?= ini_get('SMTP') ?: '(vide)' ?>
smtp_port php.ini : <?= ini_get('smtp_port') ?: '(vide)' ?></pre>
</div>
</body>
</html>
