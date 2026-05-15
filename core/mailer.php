<?php
/**
 * core/mailer.php — Envoi email via SMTP direct (instantane)
 *
 * Usage :
 *   require_once 'core/mailer.php';
 *   bkMail('dest@gmail.com', 'Sujet', '<html>...</html>');
 *   bkMail('dest@gmail.com', 'Sujet', '<html>...</html>', 'reply@email.com');
 */

// Config SMTP Hostinger
define('BK_SMTP_HOST', 'smtp.hostinger.com');
define('BK_SMTP_PORT', 465);
define('BK_SMTP_USER', 'contact@bokonzi.com');
define('BK_SMTP_PASS', ''); // A REMPLIR avec le mot de passe de contact@bokonzi.com
define('BK_SMTP_FROM_NAME', 'Bokonzi');
define('BK_SMTP_FROM_EMAIL', 'contact@bokonzi.com');

/**
 * Envoie un email via SMTP direct
 * @param string $to Destinataire
 * @param string $subject Sujet
 * @param string $htmlBody Corps HTML
 * @param string $replyTo Reply-To (optionnel)
 * @return bool Succes
 */
function bkMail($to, $subject, $htmlBody, $replyTo = '') {
    // Fallback si pas de mot de passe SMTP configure
    if (BK_SMTP_PASS === '') {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . BK_SMTP_FROM_NAME . " <" . BK_SMTP_FROM_EMAIL . ">\r\n";
        if ($replyTo) $headers .= "Reply-To: $replyTo\r\n";
        return @mail($to, $subject, $htmlBody, $headers, '-f ' . BK_SMTP_FROM_EMAIL);
    }

    $socket = @fsockopen('ssl://' . BK_SMTP_HOST, BK_SMTP_PORT, $errno, $errstr, 10);
    if (!$socket) return false;

    // Lire la banniere
    _smtpRead($socket);

    // EHLO
    _smtpCmd($socket, 'EHLO bokonzi.com');

    // AUTH LOGIN
    _smtpCmd($socket, 'AUTH LOGIN');
    _smtpCmd($socket, base64_encode(BK_SMTP_USER));
    $authResp = _smtpCmd($socket, base64_encode(BK_SMTP_PASS));
    if (strpos($authResp, '235') === false) {
        fclose($socket);
        return false;
    }

    // MAIL FROM
    _smtpCmd($socket, 'MAIL FROM:<' . BK_SMTP_FROM_EMAIL . '>');

    // RCPT TO
    _smtpCmd($socket, 'RCPT TO:<' . $to . '>');

    // DATA
    _smtpCmd($socket, 'DATA');

    // Headers + Body
    $boundary = md5(uniqid(time()));
    $msg  = "From: " . BK_SMTP_FROM_NAME . " <" . BK_SMTP_FROM_EMAIL . ">\r\n";
    $msg .= "To: $to\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    if ($replyTo) $msg .= "Reply-To: $replyTo\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n";
    $msg .= "Date: " . date('r') . "\r\n";
    $msg .= "Message-ID: <" . $boundary . "@bokonzi.com>\r\n";
    $msg .= "\r\n";
    $msg .= chunk_split(base64_encode($htmlBody));
    $msg .= "\r\n.\r\n";

    $resp = _smtpCmd($socket, $msg, false);

    // QUIT
    _smtpCmd($socket, 'QUIT');
    fclose($socket);

    return (strpos($resp, '250') !== false);
}

function _smtpCmd($socket, $cmd, $addCrlf = true) {
    fwrite($socket, $cmd . ($addCrlf ? "\r\n" : ""));
    return _smtpRead($socket);
}

function _smtpRead($socket) {
    $resp = '';
    while ($line = fgets($socket, 512)) {
        $resp .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $resp;
}
