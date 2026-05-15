<?php
/**
 * app/Services/ViewHelpers.php — Fonctions utilitaires pour les vues
 *
 * Extrait de index.php lignes 49-76
 */

class ViewHelpers
{
    /**
     * Formate une date en format francais dd/mm/YYYY
     */
    public static function dateFR($d)
    {
        if (!$d || $d === '-') return '-';
        if (str_starts_with($d, '0000')) return '-';
        $t = strtotime($d);
        return $t ? date('d/m/Y', $t) : $d;
    }

    /**
     * Retourne le niveau le plus eleve d'un tableau de niveaux
     */
    public static function highestNiveau($niveaux)
    {
        $order = ['IE' => 100, 'IR' => 99];
        foreach (['N' => 90, 'R' => 80, 'D' => 70] as $p => $b) {
            for ($i = 1; $i <= 8; $i++) $order[$p . $i] = $b - $i;
        }
        $best = null;
        $bestS = -1;
        foreach ($niveaux as $n) {
            $s = $order[trim($n)] ?? 0;
            if ($s > $bestS) { $bestS = $s; $best = trim($n); }
        }
        return $best;
    }

    /**
     * Genere un badge HTML colore pour un code niveau
     */
    public static function nivBadgeHtml($code)
    {
        if (!$code) return '-';
        $nc = $code[0] ?? '';
        if ($nc === 'N') { $bg = '#e11d4820'; $bc = '#e11d48'; $tc = '#fb7185'; }
        elseif ($nc === 'I') { $bg = '#c026d320'; $bc = '#c026d3'; $tc = '#e879f9'; }
        elseif ($nc === 'R') { $bg = '#0891b220'; $bc = '#0891b2'; $tc = '#22d3ee'; }
        else { $bg = '#f9731620'; $bc = '#f97316'; $tc = '#fb923c'; }
        return '<span style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;margin:1px;background:'.$bg.';border:1px solid '.$bc.'40;color:'.$tc.';">'.htmlspecialchars($code).'</span>';
    }
}

// === Fonctions globales pour les templates ===
// Raccourcis pour eviter d'ecrire ViewHelpers:: partout dans les vues

if (!function_exists('dateFR')) {
    function dateFR($d) { return ViewHelpers::dateFR($d); }
}
if (!function_exists('highestNiveau')) {
    function highestNiveau($niveaux) { return ViewHelpers::highestNiveau($niveaux); }
}
if (!function_exists('nivBadgeHtml')) {
    function nivBadgeHtml($code) { return ViewHelpers::nivBadgeHtml($code); }
}
