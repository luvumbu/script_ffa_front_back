<?php
/**
 * core/nationalites_map.php — Mapping code ISO 3 lettres -> nom francais + ISO 2 lettres (pour drapeau emoji)
 * Couvre les 165 codes presents dans la table `nationalites`.
 * NE TOUCHE PAS LA BDD — uniquement utilise pour l'affichage.
 *
 * Usage :
 *   $natMap = include __DIR__ . '/../core/nationalites_map.php';
 *   $info = $natMap['FRA'] ?? null;  // ['nom' => 'France', 'iso2' => 'FR']
 *   $flag = bk_flag_emoji($info['iso2']);  // emoji 🇫🇷
 */

if (!function_exists('bk_flag_emoji')) {
    /**
     * Convertit un code ISO 2 lettres en emoji drapeau (regional indicator symbols)
     * Ex: 'FR' -> 🇫🇷 ; '' -> '' ; 'XK' (Kosovo) -> 🇽🇰 (rendu varie selon font)
     * NOTE : Windows ne rend pas les emoji drapeaux. Privilegier bk_flag_html() pour les pages publiques.
     */
    function bk_flag_emoji($iso2) {
        if (!is_string($iso2) || strlen($iso2) !== 2) return '';
        $iso2 = strtoupper($iso2);
        if (!ctype_alpha($iso2)) return '';
        $a = mb_chr(0x1F1E6 + (ord($iso2[0]) - 0x41), 'UTF-8');
        $b = mb_chr(0x1F1E6 + (ord($iso2[1]) - 0x41), 'UTF-8');
        return $a . $b;
    }
}

if (!function_exists('bk_flag_html')) {
    /**
     * Genere un <img> de drapeau via flagcdn.com (PNG fiable, marche sur Windows).
     * Tailles dispo : 16, 20, 24, 28, 32, 36, 40, 48, 56, 64, 72, 80, 84, 96, 108, 128
     * Usage : bk_flag_html('FR', 16)
     */
    function bk_flag_html($iso2, $size = 16) {
        if (!is_string($iso2) || strlen($iso2) !== 2) return '';
        $iso2 = strtolower($iso2);
        if (!ctype_alpha($iso2)) return '';
        $allowed = [16,20,24,28,32,36,40,48,56,64,72,80,84,96,108,128];
        if (!in_array($size, $allowed, true)) $size = 16;
        $h = (int)round($size * 0.75);
        return '<img src="https://flagcdn.com/' . $size . 'x' . $h . '/' . $iso2 . '.png" '
             . 'srcset="https://flagcdn.com/' . ($size*2) . 'x' . ($h*2) . '/' . $iso2 . '.png 2x" '
             . 'width="' . $size . '" height="' . $h . '" '
             . 'alt="" loading="lazy" '
             . 'style="display:inline-block;vertical-align:-2px;margin-right:4px;border-radius:1px;">';
    }
}

return [
    'AFG' => ['nom' => 'Afghanistan', 'iso2' => 'AF'],
    'AHO' => ['nom' => 'Antilles Neerlandaises', 'iso2' => 'AN'],
    'AIA' => ['nom' => 'Anguilla', 'iso2' => 'AI'],
    'ALB' => ['nom' => 'Albanie', 'iso2' => 'AL'],
    'ALG' => ['nom' => 'Algerie', 'iso2' => 'DZ'],
    'AND' => ['nom' => 'Andorre', 'iso2' => 'AD'],
    'ANG' => ['nom' => 'Angola', 'iso2' => 'AO'],
    'ARG' => ['nom' => 'Argentine', 'iso2' => 'AR'],
    'ARM' => ['nom' => 'Armenie', 'iso2' => 'AM'],
    'AUS' => ['nom' => 'Australie', 'iso2' => 'AU'],
    'AUT' => ['nom' => 'Autriche', 'iso2' => 'AT'],
    'AZE' => ['nom' => 'Azerbaidjan', 'iso2' => 'AZ'],
    'BAH' => ['nom' => 'Bahamas', 'iso2' => 'BS'],
    'BAN' => ['nom' => 'Bangladesh', 'iso2' => 'BD'],
    'BDI' => ['nom' => 'Burundi', 'iso2' => 'BI'],
    'BEL' => ['nom' => 'Belgique', 'iso2' => 'BE'],
    'BEN' => ['nom' => 'Benin', 'iso2' => 'BJ'],
    'BER' => ['nom' => 'Bermudes', 'iso2' => 'BM'],
    'BIH' => ['nom' => 'Bosnie-Herzegovine', 'iso2' => 'BA'],
    'BLR' => ['nom' => 'Belarus', 'iso2' => 'BY'],
    'BOL' => ['nom' => 'Bolivie', 'iso2' => 'BO'],
    'BOT' => ['nom' => 'Botswana', 'iso2' => 'BW'],
    'BRA' => ['nom' => 'Bresil', 'iso2' => 'BR'],
    'BRN' => ['nom' => 'Brunei', 'iso2' => 'BN'],
    'BUL' => ['nom' => 'Bulgarie', 'iso2' => 'BG'],
    'BUR' => ['nom' => 'Burkina Faso', 'iso2' => 'BF'],
    'CAF' => ['nom' => 'Republique Centrafricaine', 'iso2' => 'CF'],
    'CAM' => ['nom' => 'Cambodge', 'iso2' => 'KH'],
    'CAN' => ['nom' => 'Canada', 'iso2' => 'CA'],
    'CGO' => ['nom' => 'Congo', 'iso2' => 'CG'],
    'CHA' => ['nom' => 'Tchad', 'iso2' => 'TD'],
    'CHI' => ['nom' => 'Chili', 'iso2' => 'CL'],
    'CHN' => ['nom' => 'Chine', 'iso2' => 'CN'],
    'CIV' => ['nom' => 'Cote d\'Ivoire', 'iso2' => 'CI'],
    'CMR' => ['nom' => 'Cameroun', 'iso2' => 'CM'],
    'COD' => ['nom' => 'Republique Democratique du Congo', 'iso2' => 'CD'],
    'COL' => ['nom' => 'Colombie', 'iso2' => 'CO'],
    'COM' => ['nom' => 'Comores', 'iso2' => 'KM'],
    'CPV' => ['nom' => 'Cap-Vert', 'iso2' => 'CV'],
    'CRC' => ['nom' => 'Costa Rica', 'iso2' => 'CR'],
    'CRO' => ['nom' => 'Croatie', 'iso2' => 'HR'],
    'CUB' => ['nom' => 'Cuba', 'iso2' => 'CU'],
    'CYP' => ['nom' => 'Chypre', 'iso2' => 'CY'],
    'CZE' => ['nom' => 'Republique Tcheque', 'iso2' => 'CZ'],
    'DEN' => ['nom' => 'Danemark', 'iso2' => 'DK'],
    'DJI' => ['nom' => 'Djibouti', 'iso2' => 'DJ'],
    'DMA' => ['nom' => 'Dominique', 'iso2' => 'DM'],
    'DOM' => ['nom' => 'Republique Dominicaine', 'iso2' => 'DO'],
    'ECU' => ['nom' => 'Equateur', 'iso2' => 'EC'],
    'EGY' => ['nom' => 'Egypte', 'iso2' => 'EG'],
    'ERI' => ['nom' => 'Erythree', 'iso2' => 'ER'],
    'ESP' => ['nom' => 'Espagne', 'iso2' => 'ES'],
    'EST' => ['nom' => 'Estonie', 'iso2' => 'EE'],
    'ETH' => ['nom' => 'Ethiopie', 'iso2' => 'ET'],
    'FIN' => ['nom' => 'Finlande', 'iso2' => 'FI'],
    'FRA' => ['nom' => 'France', 'iso2' => 'FR'],
    'FSM' => ['nom' => 'Micronesie', 'iso2' => 'FM'],
    'GAB' => ['nom' => 'Gabon', 'iso2' => 'GA'],
    'GAM' => ['nom' => 'Gambie', 'iso2' => 'GM'],
    'GBR' => ['nom' => 'Royaume-Uni', 'iso2' => 'GB'],
    'GBS' => ['nom' => 'Guinee-Bissau', 'iso2' => 'GW'],
    'GEO' => ['nom' => 'Georgie', 'iso2' => 'GE'],
    'GEQ' => ['nom' => 'Guinee Equatoriale', 'iso2' => 'GQ'],
    'GER' => ['nom' => 'Allemagne', 'iso2' => 'DE'],
    'GHA' => ['nom' => 'Ghana', 'iso2' => 'GH'],
    'GRE' => ['nom' => 'Grece', 'iso2' => 'GR'],
    'GUA' => ['nom' => 'Guatemala', 'iso2' => 'GT'],
    'GUI' => ['nom' => 'Guinee', 'iso2' => 'GN'],
    'GUY' => ['nom' => 'Guyana', 'iso2' => 'GY'],
    'HAI' => ['nom' => 'Haiti', 'iso2' => 'HT'],
    'HKG' => ['nom' => 'Hong Kong', 'iso2' => 'HK'],
    'HON' => ['nom' => 'Honduras', 'iso2' => 'HN'],
    'HUN' => ['nom' => 'Hongrie', 'iso2' => 'HU'],
    'IND' => ['nom' => 'Inde', 'iso2' => 'IN'],
    'IRI' => ['nom' => 'Iran', 'iso2' => 'IR'],
    'IRL' => ['nom' => 'Irlande', 'iso2' => 'IE'],
    'IRQ' => ['nom' => 'Irak', 'iso2' => 'IQ'],
    'ISL' => ['nom' => 'Islande', 'iso2' => 'IS'],
    'ISR' => ['nom' => 'Israel', 'iso2' => 'IL'],
    'ITA' => ['nom' => 'Italie', 'iso2' => 'IT'],
    'JAM' => ['nom' => 'Jamaique', 'iso2' => 'JM'],
    'JOR' => ['nom' => 'Jordanie', 'iso2' => 'JO'],
    'JPN' => ['nom' => 'Japon', 'iso2' => 'JP'],
    'KAZ' => ['nom' => 'Kazakhstan', 'iso2' => 'KZ'],
    'KEN' => ['nom' => 'Kenya', 'iso2' => 'KE'],
    'KOR' => ['nom' => 'Coree du Sud', 'iso2' => 'KR'],
    'KOS' => ['nom' => 'Kosovo', 'iso2' => 'XK'],
    'KSA' => ['nom' => 'Arabie Saoudite', 'iso2' => 'SA'],
    'KUW' => ['nom' => 'Koweit', 'iso2' => 'KW'],
    'LAO' => ['nom' => 'Laos', 'iso2' => 'LA'],
    'LAT' => ['nom' => 'Lettonie', 'iso2' => 'LV'],
    'LBA' => ['nom' => 'Libye', 'iso2' => 'LY'],
    'LBR' => ['nom' => 'Liberia', 'iso2' => 'LR'],
    'LCA' => ['nom' => 'Sainte-Lucie', 'iso2' => 'LC'],
    'LES' => ['nom' => 'Lesotho', 'iso2' => 'LS'],
    'LIB' => ['nom' => 'Liban', 'iso2' => 'LB'],
    'LTU' => ['nom' => 'Lituanie', 'iso2' => 'LT'],
    'LUX' => ['nom' => 'Luxembourg', 'iso2' => 'LU'],
    'MAD' => ['nom' => 'Madagascar', 'iso2' => 'MG'],
    'MAR' => ['nom' => 'Maroc', 'iso2' => 'MA'],
    'MAS' => ['nom' => 'Malaisie', 'iso2' => 'MY'],
    'MDA' => ['nom' => 'Moldavie', 'iso2' => 'MD'],
    'MEX' => ['nom' => 'Mexique', 'iso2' => 'MX'],
    'MKD' => ['nom' => 'Macedoine du Nord', 'iso2' => 'MK'],
    'MLI' => ['nom' => 'Mali', 'iso2' => 'ML'],
    'MLT' => ['nom' => 'Malte', 'iso2' => 'MT'],
    'MON' => ['nom' => 'Mongolie', 'iso2' => 'MN'],
    'MOZ' => ['nom' => 'Mozambique', 'iso2' => 'MZ'],
    'MRI' => ['nom' => 'Maurice', 'iso2' => 'MU'],
    'MTN' => ['nom' => 'Mauritanie', 'iso2' => 'MR'],
    'NCA' => ['nom' => 'Nicaragua', 'iso2' => 'NI'],
    'NED' => ['nom' => 'Pays-Bas', 'iso2' => 'NL'],
    'NGR' => ['nom' => 'Nigeria', 'iso2' => 'NG'],
    'NIG' => ['nom' => 'Niger', 'iso2' => 'NE'],
    'NOR' => ['nom' => 'Norvege', 'iso2' => 'NO'],
    'NZL' => ['nom' => 'Nouvelle-Zelande', 'iso2' => 'NZ'],
    'PAK' => ['nom' => 'Pakistan', 'iso2' => 'PK'],
    'PER' => ['nom' => 'Perou', 'iso2' => 'PE'],
    'PLE' => ['nom' => 'Palestine', 'iso2' => 'PS'],
    'POL' => ['nom' => 'Pologne', 'iso2' => 'PL'],
    'POR' => ['nom' => 'Portugal', 'iso2' => 'PT'],
    'PYF' => ['nom' => 'Polynesie Francaise', 'iso2' => 'PF'],
    'QAT' => ['nom' => 'Qatar', 'iso2' => 'QA'],
    'ROU' => ['nom' => 'Roumanie', 'iso2' => 'RO'],
    'RSA' => ['nom' => 'Afrique du Sud', 'iso2' => 'ZA'],
    'RUS' => ['nom' => 'Russie', 'iso2' => 'RU'],
    'RWA' => ['nom' => 'Rwanda', 'iso2' => 'RW'],
    'SEN' => ['nom' => 'Senegal', 'iso2' => 'SN'],
    'SEY' => ['nom' => 'Seychelles', 'iso2' => 'SC'],
    'SIN' => ['nom' => 'Singapour', 'iso2' => 'SG'],
    'SLE' => ['nom' => 'Sierra Leone', 'iso2' => 'SL'],
    'SLO' => ['nom' => 'Slovenie', 'iso2' => 'SI'],
    'SMR' => ['nom' => 'Saint-Marin', 'iso2' => 'SM'],
    'SOM' => ['nom' => 'Somalie', 'iso2' => 'SO'],
    'SRB' => ['nom' => 'Serbie', 'iso2' => 'RS'],
    'SRI' => ['nom' => 'Sri Lanka', 'iso2' => 'LK'],
    'SSD' => ['nom' => 'Soudan du Sud', 'iso2' => 'SS'],
    'STP' => ['nom' => 'Sao Tome-et-Principe', 'iso2' => 'ST'],
    'SUD' => ['nom' => 'Soudan', 'iso2' => 'SD'],
    'SUI' => ['nom' => 'Suisse', 'iso2' => 'CH'],
    'SUR' => ['nom' => 'Suriname', 'iso2' => 'SR'],
    'SVK' => ['nom' => 'Slovaquie', 'iso2' => 'SK'],
    'SWE' => ['nom' => 'Suede', 'iso2' => 'SE'],
    'SWZ' => ['nom' => 'Eswatini', 'iso2' => 'SZ'],
    'SYR' => ['nom' => 'Syrie', 'iso2' => 'SY'],
    'TAN' => ['nom' => 'Tanzanie', 'iso2' => 'TZ'],
    'TGA' => ['nom' => 'Tonga', 'iso2' => 'TO'],
    'THA' => ['nom' => 'Thailande', 'iso2' => 'TH'],
    'TOG' => ['nom' => 'Togo', 'iso2' => 'TG'],
    'TPE' => ['nom' => 'Taipei Chinois', 'iso2' => 'TW'],
    'TTO' => ['nom' => 'Trinite-et-Tobago', 'iso2' => 'TT'],
    'TUN' => ['nom' => 'Tunisie', 'iso2' => 'TN'],
    'TUR' => ['nom' => 'Turquie', 'iso2' => 'TR'],
    'UAE' => ['nom' => 'Emirats Arabes Unis', 'iso2' => 'AE'],
    'UGA' => ['nom' => 'Ouganda', 'iso2' => 'UG'],
    'UKR' => ['nom' => 'Ukraine', 'iso2' => 'UA'],
    'URU' => ['nom' => 'Uruguay', 'iso2' => 'UY'],
    'USA' => ['nom' => 'Etats-Unis', 'iso2' => 'US'],
    'UZB' => ['nom' => 'Ouzbekistan', 'iso2' => 'UZ'],
    'VAN' => ['nom' => 'Vanuatu', 'iso2' => 'VU'],
    'VEN' => ['nom' => 'Venezuela', 'iso2' => 'VE'],
    'YEM' => ['nom' => 'Yemen', 'iso2' => 'YE'],
    'YUG' => ['nom' => 'Yougoslavie (ancien)', 'iso2' => ''],
    'ZAM' => ['nom' => 'Zambie', 'iso2' => 'ZM'],
    'ZIM' => ['nom' => 'Zimbabwe', 'iso2' => 'ZW'],
];
