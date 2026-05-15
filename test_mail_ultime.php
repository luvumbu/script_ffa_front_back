<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<pre>';
echo "=== TEST MAIL ULTIME ===\n\n";

$to = 'luvumbu.n@gmail.com';

// Test 1 : mail() simple texte
echo "1. Mail texte simple...\n";
$r1 = mail($to, 'Test Bokonzi 1 - Texte', 'Ceci est un test simple en texte brut.');
echo "   Resultat: " . ($r1 ? 'OK' : 'ECHEC') . "\n\n";

// Test 2 : mail() HTML avec From noreply
echo "2. Mail HTML From noreply@bokonzi.com...\n";
$h2 = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Bokonzi <noreply@bokonzi.com>\r\n";
$r2 = mail($to, 'Test Bokonzi 2 - Noreply', '<html><body><h1>Test 2</h1><p>From noreply@bokonzi.com</p></body></html>', $h2);
echo "   Resultat: " . ($r2 ? 'OK' : 'ECHEC') . "\n\n";

// Test 3 : mail() HTML avec From contact
echo "3. Mail HTML From contact@bokonzi.com...\n";
$h3 = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Bokonzi <contact@bokonzi.com>\r\n";
$r3 = mail($to, 'Test Bokonzi 3 - Contact', '<html><body><h1>Test 3</h1><p>From contact@bokonzi.com</p></body></html>', $h3);
echo "   Resultat: " . ($r3 ? 'OK' : 'ECHEC') . "\n\n";

// Test 4 : mail() sans From
echo "4. Mail sans From header...\n";
$h4 = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
$r4 = mail($to, 'Test Bokonzi 4 - Sans From', '<html><body><h1>Test 4</h1><p>Sans header From</p></body></html>', $h4);
echo "   Resultat: " . ($r4 ? 'OK' : 'ECHEC') . "\n\n";

// Test 5 : mail() avec -f flag
echo "5. Mail avec -f flag (envelope sender)...\n";
$h5 = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Bokonzi <noreply@bokonzi.com>\r\n";
$r5 = mail($to, 'Test Bokonzi 5 - Flag -f', '<html><body><h1>Test 5</h1><p>Avec -f noreply@bokonzi.com</p></body></html>', $h5, '-f noreply@bokonzi.com');
echo "   Resultat: " . ($r5 ? 'OK' : 'ECHEC') . "\n\n";

echo "=== FIN ===\n";
echo "Verifie ta boite " . $to . " + SPAMS\n";
echo "Tu devrais recevoir entre 1 et 5 mails selon ce qui marche.\n";
echo '</pre>';
