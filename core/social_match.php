<?php
/**
 * core/social_match.php — Helper pour determiner si un user a le droit
 * d'editer la fiche d'un athlete (matching nom OU email).
 *
 * Seuil : 45% (configurable via constante)
 */

if (!defined('BK_SOCIAL_MATCH_THRESHOLD')) {
    define('BK_SOCIAL_MATCH_THRESHOLD', 45);
}

if (!function_exists('bk_normalize_str')) {
    function bk_normalize_str($s) {
        $s = mb_strtolower(trim((string)$s), 'UTF-8');
        $accents = [
            'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
            'ý'=>'y','ÿ'=>'y',
            'ç'=>'c','ñ'=>'n','œ'=>'oe','æ'=>'ae',
        ];
        $s = strtr($s, $accents);
        $s = preg_replace('/[^a-z0-9 ]/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }
}

if (!function_exists('bk_athlete_owner_match')) {
    /**
     * Determine si l'utilisateur (user) peut editer la fiche d'un athlete.
     * Match si :
     *   - Similarite nom complet >= seuil (45%)
     *   - OU similarite email vs nom complet >= seuil
     *   - OU partie locale email contient prenom ou nom (>= 4 chars)
     *
     * @param string $userPrenom prenom du user connecte
     * @param string $userNom    nom du user connecte
     * @param string $userEmail  email du user connecte
     * @param string $athleteFullName nom complet de l'athlete (ex: "LUVUMBU Ndenga")
     * @return array ['match' => bool, 'reason' => string, 'score' => int]
     */
    function bk_athlete_owner_match($userPrenom, $userNom, $userEmail, $athleteFullName) {
        $threshold = BK_SOCIAL_MATCH_THRESHOLD;

        $userFull = bk_normalize_str(trim($userPrenom . ' ' . $userNom));
        $athFull  = bk_normalize_str($athleteFullName);

        if ($userFull === '' || $athFull === '') {
            return ['match' => false, 'reason' => 'empty', 'score' => 0];
        }

        // 1. Similarite nom complet
        $sim1 = 0;
        similar_text($userFull, $athFull, $sim1);
        if ($sim1 >= $threshold) {
            return ['match' => true, 'reason' => 'nom', 'score' => round($sim1)];
        }

        // 2. Similarite email
        if ($userEmail && strpos($userEmail, '@') !== false) {
            $local = mb_strtolower(strtok($userEmail, '@'), 'UTF-8');
            $localSpaced = preg_replace('/[._\-+0-9]/', ' ', $local);
            $localSpaced = bk_normalize_str($localSpaced);

            // Substring : prenom ou nom present (>= 4 chars)
            $words = array_filter(explode(' ', $athFull), function($w) { return mb_strlen($w) >= 4; });
            foreach ($words as $w) {
                if (strpos($localSpaced, $w) !== false) {
                    return ['match' => true, 'reason' => 'email_substring', 'score' => 100];
                }
            }

            // Similarite globale
            if ($localSpaced !== '') {
                $sim2 = 0;
                similar_text($localSpaced, $athFull, $sim2);
                if ($sim2 >= $threshold) {
                    return ['match' => true, 'reason' => 'email_similarity', 'score' => round($sim2)];
                }
            }
        }

        return ['match' => false, 'reason' => 'no_match', 'score' => round(max($sim1, $sim2 ?? 0))];
    }
}
