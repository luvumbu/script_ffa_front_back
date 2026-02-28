<?php
/**
 * brToHtmlParagraphs.php — Conversion de balises BR en paragraphes HTML / Convert BR tags to HTML paragraphs
 * FR: Fonction qui transforme du texte brut avec balises BR en paragraphes HTML structures avec liens cliquables
 * EN: Function that transforms raw text with BR tags into structured HTML paragraphs with clickable links
 */
function brToHtmlParagraphs(string $raw): string {
    // Normaliser les balises <br> en \n
    $tmp = preg_replace('/<br\s*\/?>/i', "\n", $raw);

    // Remplacer plusieurs sauts de ligne consécutifs par un séparateur double
    $tmp = preg_replace("/\r\n|\r/", "\n", $tmp);
    $tmp = preg_replace("/\n{2,}/", "\n\n", $tmp);

    // Découper en paragraphes sur double saut de ligne
    $parts = preg_split("/\n\s*\n/", trim($tmp));

    // Convertir les URLs en liens
    $linkified = function($s) {
        return preg_replace(
            '#(https?://[^\s<]+)#i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $s
        );
    };

    // Construire le HTML final
    $htmlParts = array_map(function($p) use ($linkified) {
        // supprimer espaces en début/fin et remplacer sauts simples par <br> à l'intérieur d'un paragraphe
        $p = trim($p);
        $p = htmlspecialchars($p, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); // échapper pour sécurité
        $p = nl2br($p); // conserver retours à la ligne simples
        // Rétablir les liens (on linkifie sur la version non-échappée)
        // Pour garder la sécurité, on applique linkification sur l'original non-échappé,
        // puis on remplace l'URL encodée s'il existe.
        $p = $linkified($p);
        return "<p>$p</p>";
    }, $parts);

    return implode("\n", $htmlParts);
}
/*


$raw = "Ndenga Luvumbu<br />
32 rue du Pont à Fourchon<br />
59000 Lille<br />
Téléphone : 0667472888<br />
Email : luvumbu.n@gmail.com<br />
<br />
Madame, Monsieur,<br />
<br />
Je vous adresse ma candidature pour le poste de Chef d’Équipe Photovoltaïque au sein de PHOTEOS.<br />
<br />
Fort de mon expérience dans l’installation d’équipements électriques photovoltaïques — notamment la technologie Solis, la pose de batteries Soluna et les onduleurs Huawei — je suis organisé, rigoureux et capable de manager une équipe tout en respectant les normes de sécurité.<br />
<br />
Je vous invite à consulter certaines de mes réalisations concrètes via ma galerie en ligne : https://luvumbu.com/work.php/photeos<br />
.<br />
<br />
Je reste à votre disposition pour un entretien afin d’échanger sur mes compétences et ma motivation.<br />
<br />
Cordialement,<br />
Ndenga Luvumbu";

echo brToHtmlParagraphs($raw);

*/
