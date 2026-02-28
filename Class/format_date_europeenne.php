<?php
/**
 * format_date_europeenne.php — Formatage de date europeenne / European date formatting
 * FR: Fonction qui convertit une date ISO en format europeen francais (ex: 31 decembre 2025 a 23h59)
 * EN: Function that converts an ISO date to French European format (e.g., 31 decembre 2025 a 23h59)
 */
function format_date_europeenne($date_iso)
{
    // Définir la timezone
    date_default_timezone_set('Europe/Paris');

    // Créer un objet DateTime
    $date = DateTime::createFromFormat('Y-m-d H:i:s', $date_iso);

    // Vérifier si la date est valide
    if (!$date) {
        return "Date invalide";
    }

    // Tableau des mois en français
    $mois_fr = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
    ];

    $jour = $date->format('d');
    $mois = $mois_fr[(int)$date->format('m')];
    $annee = $date->format('Y');
    $heure = $date->format('H');
    $minute = $date->format('i');

    return "$jour $mois $annee à ${heure}h$minute";
}

/*

$date_originale = "2025-12-31 23:59:00";
echo format_date_europeenne($date_originale);
// Résultat : 31 décembre 2025 à 23h59



*/
