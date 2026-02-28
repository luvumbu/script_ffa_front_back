<?php
/**
 * FrenchClock.php — Horloge francaise avec calcul de temps et anniversaires / French clock with time calculation and anniversaries
 * FR: Classe pour afficher la date/heure en francais, calculer les differences de temps et les prochains anniversaires
 * EN: Class to display date/time in French, calculate time differences and upcoming anniversaries
 */
class FrenchClock
{
    private $tz;
    private $base;

    public function __construct($baseDateTime, $timezone = 'Europe/Paris')
    {
        $this->tz   = new DateTimeZone($timezone);
        $this->base = new DateTime($baseDateTime, $this->tz);
    }

    // -------------------- DATE/HEURE --------------------
    public function now() { return new DateTime('now', $this->tz); }
    public function base() { return clone $this->base; }

    public function formatNow($pattern='Y-m-d H:i:s') { return $this->now()->format($pattern); }
    public function formatBase($pattern='Y-m-d H:i:s') { return $this->base->format($pattern); }

    public function formatNowFrench()
    {
        $jours = array("dimanche","lundi","mardi","mercredi","jeudi","vendredi","samedi");
        $mois  = array("","janvier","février","mars","avril","mai","juin","juillet","août","septembre","octobre","novembre","décembre");

        $dt = $this->now();
        $jourSemaine = $jours[(int)$dt->format("w")];
        $jour = $dt->format("d");
        $moisTxt = $mois[(int)$dt->format("n")];
        $annee = $dt->format("Y");
        $heure = $dt->format("H:i:s");

        return "$jourSemaine $jour $moisTxt $annee à $heure";
    }

    // -------------------- DIFFÉRENCES --------------------
    public function diffFromBase() { return $this->base->diff($this->now()); }

    public function diffFromBaseHuman()
    {
        $d = $this->diffFromBase();
        $parts = array();
        if ($d->y) $parts[] = $d->y.' an'.($d->y>1?'s':'');
        if ($d->m) $parts[] = $d->m.' mois';
        if ($d->d) $parts[] = $d->d.' jour'.($d->d>1?'s':'');
        if ($d->h) $parts[] = $d->h.' h';
        if ($d->i) $parts[] = $d->i.' min';
        if ($d->s && !$d->h && !$d->i) $parts[] = $d->s.' s';
        return $parts ? implode(' ', $parts) : '0 s';
    }

    public function totalDaysSinceBase() { return (int)$this->diffFromBase()->days; }
    public function totalWeeksSinceBase() { return floor($this->totalDaysSinceBase()/7); }
    public function totalMonthsSinceBase() { $diff=$this->diffFromBase(); return $diff->y*12 + $diff->m; }
    public function yearsSinceBase() { return $this->diffFromBase()->y; }

    // -------------------- ANNIVERSAIRE --------------------
    public function getNextAnniversaryDate()
    {
        $now = $this->now();
        $jour = $this->base->format("d");
        $mois = $this->base->format("m");
        $year = $now->format("Y");
        $anniv = new DateTime("$year-$mois-$jour 00:00:00", $this->tz);
        if ($anniv < $now) $anniv->modify("+1 year");
        return $anniv;
    }

    public function daysToNextAnniversary()
    {
        $diff = $this->now()->diff($this->getNextAnniversaryDate());
        return (int)$diff->days;
    }

    public function weeksToNextAnniversary()
    {
        return floor($this->daysToNextAnniversary()/7);
    }

    public function monthsToNextAnniversary()
    {
        $diff = $this->now()->diff($this->getNextAnniversaryDate());
        return (int)$diff->m;
    }

    public function timeToAnniversary()
    {
        $diff = $this->now()->diff($this->getNextAnniversaryDate());
        return array(
            'mois'=>$diff->m,
            'jours'=>$diff->d,
            'heures'=>$diff->h,
            'minutes'=>$diff->i,
            'secondes'=>$diff->s
        );
    }

    public function nextAnniversary()
    {
        $anniv = $this->getNextAnniversaryDate();
        if ($anniv->format("Y-m-d") == $this->now()->format("Y-m-d")) return "🎉 Aujourd'hui c'est l'anniversaire !";
        $info = $this->timeToAnniversary();
        $parts=array();
        if($info['mois']) $parts[]=$info['mois'].' mois';
        if($info['jours']) $parts[]=$info['jours'].' jour'.($info['jours']>1?'s':'');
        if($info['heures']) $parts[]=$info['heures'].' h';
        if($info['minutes']) $parts[]=$info['minutes'].' min';
        return "Prochain anniversaire dans ".implode(" ",$parts);
    }
}



/* 


$baseDate = "1995-11-17 15:42:00";
$clock = new FrenchClock($baseDate);

echo "<b>1️⃣ Heure actuelle (technique):</b> ".$clock->formatNow()."<br>";
echo "<b>2️⃣ Date de base:</b> ".$clock->formatBase()."<br>";
echo "<b>3️⃣ Temps écoulé depuis la base:</b> ".$clock->diffFromBaseHuman()."<br>";
echo "<b>4️⃣ Heure actuelle (FR):</b> ".$clock->formatNowFrench()."<br>";
echo "<b>5️⃣ Prochain anniversaire (DateTime):</b> ".$clock->getNextAnniversaryDate()->format('Y-m-d H:i:s')."<br>";
echo "<b>6️⃣ Jours avant prochain anniversaire:</b> ".$clock->daysToNextAnniversary()." jours<br>";
echo "<b>7️⃣ Semaines avant prochain anniversaire:</b> ".$clock->weeksToNextAnniversary()." semaines<br>";
echo "<b>8️⃣ Mois avant prochain anniversaire:</b> ".$clock->monthsToNextAnniversary()." mois<br>";

$details = $clock->timeToAnniversary();
echo "<b>9️⃣ Détail temps avant anniversaire:</b> ".$details['mois']." mois, ".$details['jours']." jours, ".$details['heures']."h ".$details['minutes']."min<br>";

echo "<b>🔟 Âge:</b> ".$clock->yearsSinceBase()." ans<br>";
echo "<b>1️⃣1️⃣ Total jours depuis la base:</b> ".$clock->totalDaysSinceBase()." jours<br>";
echo "<b>1️⃣2️⃣ Total semaines depuis la base:</b> ".$clock->totalWeeksSinceBase()." semaines<br>";
echo "<b>1️⃣3️⃣ Total mois depuis la base:</b> ".$clock->totalMonthsSinceBase()." mois<br>";
echo "<b>1️⃣4️⃣ Texte global anniversaire:</b> ".$clock->nextAnniversary()."<br>";




*/