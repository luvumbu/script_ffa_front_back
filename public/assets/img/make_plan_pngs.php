<?php
/**
 * make_plan_pngs.php — Génère les 4 images PNG des plans BOKONZI (pour Stripe).
 *
 * GD étant désactivé dans le php.ini de XAMPP, on le charge ponctuellement :
 *   C:\xampp\php\php.exe -d extension=gd make_plan_pngs.php
 *
 * Produit : stripe-bronze.png / stripe-argent.png / stripe-or.png / stripe-platine.png
 * (1024×1024, fond sombre + coureur du favicon + médaille de l'offre + nom).
 */

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD requis. Lance : php.exe -d extension=gd make_plan_pngs.php\n");
    exit(1);
}

$S = 1024;                 // taille du PNG (carré)
$k = $S / 600.0;           // facteur d'échelle depuis le gabarit 600px
$dir = __DIR__;

$fontBold = 'C:/Windows/Fonts/arialbd.ttf';
$fontReg  = 'C:/Windows/Fonts/arial.ttf';
$hasFont  = is_file($fontBold);

$plans = [
    'bronze'  => ['name' => 'BRONZE',  'stars' => 1,
        'light' => [240,185,125], 'mid' => [205,127,50],  'dark' => [111,66,20],  'accent' => [224,164,94]],
    'argent'  => ['name' => 'ARGENT',  'stars' => 2,
        'light' => [247,248,250], 'mid' => [192,196,204], 'dark' => [91,100,112], 'accent' => [212,216,222]],
    'or'      => ['name' => 'OR',      'stars' => 3,
        'light' => [254,240,176], 'mid' => [245,176,21],  'dark' => [169,118,10], 'accent' => [245,197,66]],
    'platine' => ['name' => 'PLATINE', 'stars' => 4,
        'light' => [221,214,254], 'mid' => [139,124,240], 'dark' => [76,29,149],  'accent' => [179,164,251]],
];

/** Étoile à 5 branches → liste de points pour imagefilledpolygon. */
function starPoints($cx, $cy, $rOut, $rIn) {
    $pts = [];
    for ($i = 0; $i < 5; $i++) {
        $aO = -M_PI / 2 + $i * 2 * M_PI / 5;
        $aI = $aO + M_PI / 5;
        $pts[] = $cx + $rOut * cos($aO);  $pts[] = $cy + $rOut * sin($aO);
        $pts[] = $cx + $rIn  * cos($aI);  $pts[] = $cy + $rIn  * sin($aI);
    }
    return $pts;
}

/** Trait épais à bouts arrondis (line + disques aux extrémités). */
function thickLine($im, $x1, $y1, $x2, $y2, $w, $col) {
    imagesetthickness($im, max(1, (int)round($w)));
    imageline($im, (int)$x1, (int)$y1, (int)$x2, (int)$y2, $col);
    $r = (int)round($w / 2);
    imagefilledellipse($im, (int)$x1, (int)$y1, $r * 2, $r * 2, $col);
    imagefilledellipse($im, (int)$x2, (int)$y2, $r * 2, $r * 2, $col);
}

/** Texte TTF centré horizontalement sur $cx, baseline à $y. */
function ttfCenter($im, $size, $cx, $y, $col, $font, $text) {
    $bb = imagettfbbox($size, 0, $font, $text);
    $w  = $bb[2] - $bb[0];
    imagettftext($im, $size, 0, (int)round($cx - $w / 2 - $bb[0]), (int)$y, $col, $font, $text);
}

foreach ($plans as $key => $p) {
    $im = imagecreatetruecolor($S, $S);
    imagealphablending($im, true);
    imagesavealpha($im, true);

    [$lr,$lg,$lb] = $p['light'];
    [$mr,$mg,$mb] = $p['mid'];
    [$dr,$dg,$db] = $p['dark'];
    [$ar,$ag,$ab] = $p['accent'];

    // ---- Fond : dégradé radial sombre ----
    imagefilledrectangle($im, 0, 0, $S, $S, imagecolorallocate($im, 0x0b, 0x0b, 0x12));
    $gcx = $S * 0.5; $gcy = $S * 0.40; $maxR = $S * 1.18;
    for ($r = $maxR; $r > 0; $r -= 3) {
        $t  = $r / $maxR; // 1 au bord, 0 au centre
        $cc = imagecolorallocate($im,
            (int)round(0x26 + $t * (0x0b - 0x26)),
            (int)round(0x26 + $t * (0x0b - 0x26)),
            (int)round(0x3c + $t * (0x12 - 0x3c)));
        imagefilledellipse($im, (int)$gcx, (int)$gcy, (int)$r, (int)$r, $cc);
    }
    // halo coloré derrière la médaille
    for ($i = 0; $i < 5; $i++) {
        $gr = $S * (0.50 - $i * 0.05);
        $gc = imagecolorallocatealpha($im, $mr, $mg, $mb, 118);
        imagefilledellipse($im, (int)$gcx, (int)$gcy, (int)($gr * 2), (int)($gr * 2), $gc);
    }

    // ---- Lignes de vitesse (charte : violet) en haut à gauche ----
    $purple = imagecolorallocate($im, 139, 124, 240);
    thickLine($im, 44*$k, 150*$k, 158*$k, 150*$k, 8*$k, $purple);
    $purpleF1 = imagecolorallocatealpha($im, 139, 124, 240, 55);
    thickLine($im, 24*$k, 188*$k, 126*$k, 188*$k, 8*$k, $purpleF1);
    $purpleF2 = imagecolorallocatealpha($im, 139, 124, 240, 90);
    thickLine($im, 50*$k, 226*$k, 134*$k, 226*$k, 8*$k, $purpleF2);

    // ---- Médaille ----
    $mcx = 300 * $k; $mcy = 220 * $k;
    imagefilledellipse($im, (int)$mcx, (int)$mcy, (int)(2*172*$k), (int)(2*172*$k), imagecolorallocate($im, $dr, $dg, $db));
    imagefilledellipse($im, (int)$mcx, (int)$mcy, (int)(2*163*$k), (int)(2*163*$k), imagecolorallocate($im, $mr, $mg, $mb));
    // reflet clair en haut-gauche
    $sheen = imagecolorallocatealpha($im, $lr, $lg, $lb, 70);
    imagefilledellipse($im, (int)($mcx - 46*$k), (int)($mcy - 52*$k), (int)(2*92*$k), (int)(2*92*$k), $sheen);
    // disque intérieur sombre
    imagefilledellipse($im, (int)$mcx, (int)$mcy, (int)(2*134*$k), (int)(2*134*$k), imagecolorallocate($im, 0x14, 0x14, 0x1f));
    // anneau blanc fin
    imagesetthickness($im, max(2, (int)round(3*$k)));
    $white = imagecolorallocatealpha($im, 255, 255, 255, 85);
    imageellipse($im, (int)$mcx, (int)$mcy, (int)(2*134*$k), (int)(2*134*$k), $white);

    // ---- Coureur (motif exact du favicon) ----
    $sr = 3.9 * $k;                 // échelle du coureur
    $TX = 183 * $k; $TY = 95.2 * $k;
    $px = function ($x) use ($TX, $sr) { return $TX + $x * $sr; };
    $py = function ($y) use ($TY, $sr) { return $TY + $y * $sr; };
    $light = imagecolorallocate($im, 236, 237, 245);
    $wBody = 3.0 * $sr; $wArm = 2.5 * $sr; $wLeg = 3.0 * $sr;

    // tête
    imagefilledellipse($im, (int)$px(34), (int)$py(14), (int)(2*6*$sr), (int)(2*6*$sr), $light);
    // corps
    thickLine($im, $px(32),$py(20), $px(28),$py(36), $wBody, $light);
    // bras arrière
    thickLine($im, $px(30),$py(24), $px(22),$py(20), $wArm, $light);
    thickLine($im, $px(22),$py(20), $px(18),$py(26), $wArm, $light);
    // bras avant
    thickLine($im, $px(30),$py(24), $px(38),$py(30), $wArm, $light);
    thickLine($im, $px(38),$py(30), $px(42),$py(24), $wArm, $light);
    // jambe arrière
    thickLine($im, $px(28),$py(36), $px(20),$py(44), $wLeg, $light);
    thickLine($im, $px(20),$py(44), $px(14),$py(42), $wLeg, $light);
    // jambe avant
    thickLine($im, $px(28),$py(36), $px(36),$py(44), $wLeg, $light);
    thickLine($im, $px(36),$py(44), $px(38),$py(54), $wLeg, $light);
    // lignes de vitesse internes (violet clair)
    $pl = imagecolorallocate($im, 196, 181, 253);
    thickLine($im, $px(8),$py(28),  $px(17),$py(28), 2.2*$sr, $pl);
    $plf1 = imagecolorallocatealpha($im, 196,181,253, 45);
    thickLine($im, $px(5),$py(34),  $px(15),$py(34), 2.2*$sr, $plf1);
    $plf2 = imagecolorallocatealpha($im, 196,181,253, 75);
    thickLine($im, $px(9),$py(40),  $px(17),$py(40), 2.2*$sr, $plf2);

    imagesetthickness($im, 1);

    // ---- Bordure fine aux couleurs du plan ----
    $accent = imagecolorallocatealpha($im, $ar, $ag, $ab, 70);
    imagesetthickness($im, max(2, (int)round(3*$k)));
    imagerectangle($im, (int)(14*$k), (int)(14*$k), (int)($S - 14*$k), (int)($S - 14*$k), $accent);
    imagesetthickness($im, 1);

    // ---- Textes ----
    if ($hasFont) {
        $cWhite  = imagecolorallocate($im, 244, 244, 248);
        $cAccent = imagecolorallocate($im, $ar, $ag, $ab);
        ttfCenter($im, 58 * $k, $S / 2, 452 * $k, $cWhite,  $fontBold, 'BOKONZI');
        ttfCenter($im, 40 * $k, $S / 2, 506 * $k, $cAccent, $fontBold, $p['name']);
        // étoiles (polygones, indépendant des polices)
        $n = $p['stars']; $rOut = 17 * $k; $rIn = 6.8 * $k; $gap = 12 * $k;
        $stepW = 2 * $rOut + $gap;
        $startX = $S / 2 - (($n - 1) * $stepW) / 2;
        $starY  = 540 * $k;
        for ($i = 0; $i < $n; $i++) {
            $pts = starPoints($startX + $i * $stepW, $starY, $rOut, $rIn);
            imagefilledpolygon($im, array_map('intval', $pts), $cAccent);
        }
    }

    $out = $dir . '/stripe-' . $key . '.png';
    imagepng($im, $out);
    imagedestroy($im);
    echo "OK  " . basename($out) . "  (" . round(filesize($out) / 1024) . " Ko)\n";
}

echo "Terminé — 4 PNG générés dans " . $dir . "\n";
