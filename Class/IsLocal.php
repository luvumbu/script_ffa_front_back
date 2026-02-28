<?php
/**
 * IsLocal.php — Detection d'environnement local / Local environment detection
 * FR: Fonction qui detecte si l'application s'execute en environnement local ou de production
 * EN: Function that detects if the application is running in a local or production environment
 */

function IsLocal()
{
  // Vérifiez le nom d'hôte pour détecter l'environnement local
  $hostname = $_SERVER['HTTP_HOST'];

  // Liste des domaines ou sous-domaines utilisés en local
  $local_domains = ['localhost', '127.0.0.1', 'dev.example.com'];

  return in_array($hostname, $local_domains);
}

?>

<?php
/*
if (IsLocal()) {
    echo "Vous êtes en environnement local.";
} else {
    echo "Vous êtes en environnement de production.";
}
    */
?>
