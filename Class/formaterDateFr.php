<?php
/**
 * formaterDateFr.php — Formatage de date avec locale francaise / Date formatting with French locale
 * FR: Fonction qui formate une date en utilisant la locale francaise du systeme (strftime)
 * EN: Function that formats a date using the system's French locale (strftime)
 */

function formaterDateFr($datetime) {
    // Essaye plusieurs formats selon le système
    $locales = ['fr_FR.UTF-8', 'fr_FR', 'fra', 'french'];
    foreach ($locales as $loc) {
        if (setlocale(LC_TIME, $loc)) break;
    }

    return strftime('%A %d %B %Y à %Hh%M', strtotime($datetime));
}


/*
$date = "2025-04-30 14:32:28";
echo formaterDateFr($date);
 */
?>
