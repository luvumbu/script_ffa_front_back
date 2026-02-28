<?php
/**
 * Get_anne_html_html.php — Calcul du temps restant pour affichage HTML / Calculate remaining time for HTML display
 * FR: Classe qui calcule le temps restant (annees, jours, heures, minutes, secondes) pour un affichage HTML
 * EN: Class that calculates remaining time (years, days, hours, minutes, seconds) for HTML display
 */
class Get_anne_html_html
{
    public $name;

    function __construct($name)
    {
        $this->name = $name;
    }

    function get_temps_restant()
    {
        date_default_timezone_set('Europe/Paris');
        $date_cible = strtotime($this->name);
        $date_actuelle = time();

        $difference = $date_cible - $date_actuelle;

        $annees = floor(abs($difference) / (3600 * 24 * 365));
        $jours = floor((abs($difference) % (3600 * 24 * 365)) / (3600 * 24));
        $heures = floor((abs($difference) % (3600 * 24)) / 3600);
        $minutes = floor((abs($difference) % 3600) / 60);
        $secondes = abs($difference) % 60;

        return [
            "annees" => $annees,
            "jours" => $jours,
            "heures" => $heures,
            "minutes" => $minutes,
            "secondes" => $secondes,
            "is_past" => $difference < 0
        ];
    }
}



 

 
