<?php
/**
 * formatMailTextToHtml.php — Conversion de texte email en HTML / Convert email text to HTML
 * FR: Fonction qui transforme du texte brut d'email avec balises BR en HTML structure avec paragraphes et liens cliquables
 * EN: Function that transforms raw email text with BR tags into structured HTML with paragraphs and clickable links
 */
function formatMailTextToHtml(string $raw): string {
    // 1. Normaliser toutes les balises <br> en sauts de ligne
    $tmp = preg_replace('/<br\s*\/?>/i', "\n", $raw);

    // 2. Nettoyer retours à la ligne (éviter doublons)
    $tmp = preg_replace("/\r\n|\r/", "\n", $tmp);
    $tmp = preg_replace("/\n{2,}/", "\n\n", $tmp); // max 2 sauts

    // 3. Découper en paragraphes sur double saut
    $parts = preg_split("/\n\s*\n/", trim($tmp));

    // 4. Transformer chaque paragraphe
    $htmlParts = array_map(function($p) {
        $p = trim($p);
        // sécuriser
        $p = htmlspecialchars($p, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // conserver les retours simples
        $p = nl2br($p, false);
        // transformer les URLs en liens cliquables
        $p = preg_replace(
            '#(https?://[^\s<]+)#i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $p
        );
        return "<p>$p</p>";
    }, $parts);

    // 5. Retourner le HTML final
    return implode("\n", $htmlParts);
}
/*


$rawText = "
<br />
<br />
Toute l’équipe vous souhaite une belle rentrée.<br />
<br />
Merci de noter quelques dates.<br />
<br />
A vos agendas !<br />
<br />
";

// Transformer en HTML propre
$html = formatMailTextToHtml($rawText);

// Exemple d’affichage
echo $html;



*/