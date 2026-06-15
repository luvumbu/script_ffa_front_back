<?php
/**
 * recherche.php — Moteur de recherche fichiers / File search engine
 * FR: Analyseur de fichiers PRO V4 avec recherche par mot-cle
 * EN: File Analyzer PRO V4 with keyword search
 */
session_start();
require_once dirname(__DIR__) . "/Class/Language.php";
require_once dirname(__DIR__) . "/Class/LanguageSwitcher.php";
Language::init('fr');
ini_set('memory_limit', '900M');
function formatTaille($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    if ($bytes > 1) return $bytes . ' bytes';
    if ($bytes == 1) return '1 byte';
    return '0 byte';
}
function getPermissions($file) {
    return substr(sprintf('%o', fileperms($file)), -4);
}
function parcourirDossier($racine, $chaineRecherche, $mode = 2) {

    $resultats = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $fileInfo) {

        $cheminComplet = $fileInfo->getPathname();

        if ($fileInfo->isFile()) {

            $lignesTrouvees = [];
            $nbOccurrences = 0;

            $handle = @fopen($cheminComplet, "r");

            if ($handle) {

                $numeroLigne = 0;

                while (($ligne = fgets($handle)) !== false) {
                    $numeroLigne++;

                    if (stripos($ligne, $chaineRecherche) !== false) {

                        $nbOccurrences += substr_count(
                            strtolower($ligne),
                            strtolower($chaineRecherche)
                        );

                        $ligneSecure = htmlspecialchars($ligne);

                        $ligneHighlight = str_ireplace(
                            $chaineRecherche,
                            "<mark>$chaineRecherche</mark>",
                            $ligneSecure
                        );

                        $lignesTrouvees[] = [
                            'numero' => $numeroLigne,
                            'contenu' => trim($ligneHighlight)
                        ];
                    }
                }

                fclose($handle);
            }

            $correspond = $nbOccurrences > 0;

            if (($mode == 1) || ($mode == 2 && $correspond)) {

                $resultats[] = [
                    'type' => 'file',
                    'path' => $cheminComplet,
                    'size' => formatTaille($fileInfo->getSize()),
                    'extension' => $fileInfo->getExtension(),
                    'created' => date("d/m/Y H:i:s", $fileInfo->getCTime()),
                    'last_modified' => date("d/m/Y H:i:s", $fileInfo->getMTime()),
                    'last_access' => date("d/m/Y H:i:s", $fileInfo->getATime()),
                    'permissions' => getPermissions($cheminComplet),
                    'found' => $correspond,
                    'occurrences' => $nbOccurrences,
                    'lignes' => $lignesTrouvees
                ];
            }

        } else {
            if ($mode == 1) {
                $resultats[] = [
                    'type' => 'dir',
                    'path' => $cheminComplet,
                    'permissions' => getPermissions($cheminComplet)
                ];
            }
        }
    }

    return $resultats;
}
$racine = dirname(__DIR__);
?>
<!DOCTYPE html>
<html lang="<?= Language::getLang() ?>">
<head>
<meta charset="UTF-8">
<meta name="google-adsense-account" content="ca-pub-7899923856846249">
<title><?= t('search_title') ?></title>
<link rel="stylesheet" href="../css.css">
<?php require_once __DIR__ . '/../core/theme.php'; bkRenderThemeHead(); ?>
</head>
<body>
<div class="simpson-container">
<div style="text-align:right;margin-bottom:10px;"><?= LanguageSwitcher::render() ?></div>
<h1 class="simpson-h1"><?= t('search_title') ?></h1>
<div class="simpson-form">
    <form method="post">
        <label><?= t('search_label') ?></label>
        <input type="text" name="chaine" required>

        <label><input type="radio" name="mode" value="1"> <?= t('search_show_all') ?></label>
        <label><input type="radio" name="mode" value="2" checked> <?= t('search_only_found') ?></label>
        <br><br>
        <button type="submit"><?= t('search_launch') ?></button>
    </form>
</div>
<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $chaineRecherche = trim($_POST["chaine"]);
    $mode = intval($_POST["mode"]);

    echo "<h2 class='simpson-h2'>" . t('search_results_for') . " <em>$chaineRecherche</em></h2>";

    $resultats = parcourirDossier($racine, $chaineRecherche, $mode);

    if (empty($resultats)) {
        echo "<p>" . t('search_no_results') . "</p>";
    }

    foreach ($resultats as $item) {

        if ($item['type'] === 'dir') {

            echo "<div class='simpson-card dir'>";
            echo "<strong>📂 " . t('search_folder') . "</strong>";
            echo "<div class='path'>{$item['path']}</div>";
            echo "<div class='small'>";
            echo t('search_permissions') . " : {$item['permissions']}";
            echo "</div>";
            echo "</div>";

        } else {

            $class = $item['found'] ? "simpson-card found" : "simpson-card file";

            echo "<div class='$class'>";
            echo "<strong>📄 " . t('search_file') . "</strong>";
            echo "<div class='path'>{$item['path']}</div>";
            echo "<div class='small'>";
            echo t('search_extension') . " : {$item['extension']}<br>";
            echo t('search_size') . " : {$item['size']}<br>";
            echo t('search_created') . " : {$item['created']}<br>";
            echo t('search_modified') . " : {$item['last_modified']}<br>";
            echo t('search_accessed') . " : {$item['last_access']}<br>";
            echo t('search_permissions') . " : {$item['permissions']}<br>";
            echo "<strong>" . t('search_occurrences') . " : {$item['occurrences']}</strong>";
            echo "</div>";

            if (!empty($item['lignes'])) {
                foreach ($item['lignes'] as $ligne) {
                    echo "<div class='line'>";
                    echo t('search_line') . " {$ligne['numero']} : {$ligne['contenu']}";
                    echo "</div>";
                }
            }

            echo "</div>";
        }
    }
}
?>

</div>
</body>
</html>
