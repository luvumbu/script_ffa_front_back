<?php
$pokemon = "pikachu";
$url = "https://pokeapi.co/api/v2/pokemon/" . $pokemon;

$response = file_get_contents($url);
$data = json_decode($response, true);

// Fonction récursive pour afficher toutes les images
function afficherImages($arr) {
    foreach ($arr as $key => $value) {
        if (is_array($value)) {
            afficherImages($value);
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            echo "<p>$key:</p><img src='$value' style='width:120px;'><br>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Images de <?php echo ucfirst($pokemon); ?></title>
</head>
<body>
<h1>Images de <?php echo ucfirst($pokemon); ?></h1>
<?php
afficherImages($data["sprites"]);
?>
</body>
</html>