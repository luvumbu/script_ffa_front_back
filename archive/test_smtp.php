<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo '<pre>';

require_once __DIR__ . '/core/mailer.php';

echo "SMTP_HOST: " . BK_SMTP_HOST . "\n";
echo "SMTP_PORT: " . BK_SMTP_PORT . "\n";
echo "SMTP_USER: " . BK_SMTP_USER . "\n";
echo "SMTP_PASS: " . (BK_SMTP_PASS === '' ? 'VIDE !!!' : strlen(BK_SMTP_PASS) . ' chars') . "\n\n";

echo "Envoi test SMTP...\n";
$result = bkMail(
    'luvumbu.n@gmail.com',
    'Test SMTP Direct - ' . date('H:i:s'),
    '<html><body><h1 style="color:#6c5ce7;">Test SMTP Direct</h1><p>Si tu recois ce mail, le SMTP fonctionne !</p><p>Heure: ' . date('H:i:s') . '</p></body></html>'
);

echo "Resultat: " . ($result ? 'SUCCES' : 'ECHEC') . "\n";
echo '</pre>';
