<?php
/**
 * creerDossierSiExistePas.php — Creation de dossier si inexistant / Create directory if it does not exist
 * FR: Fonction qui cree un dossier avec les permissions specifiees s'il n'existe pas deja
 * EN: Function that creates a directory with specified permissions if it does not already exist
 */
/**
 * Cree un dossier s'il n'existe pas deja. / Creates a directory if it does not already exist.
 *
 * @param string $cheminDossier Le chemin du dossier à créer.
 * @param int $permissions (Optionnel) Les permissions à appliquer au dossier (par défaut 0755).
 * @return string Un message indiquant le résultat de l'opération.
 */
function creerDossierSiExistePas(string $cheminDossier, int $permissions = 0755): string {
    if (!is_dir($cheminDossier)) {
        if (mkdir($cheminDossier, $permissions, true)) {
            return "Dossier '$cheminDossier' créé avec succès.";
        } else {
            return "Erreur : impossible de créer le dossier '$cheminDossier'.";
        }
    } else {
        return "Le dossier '$cheminDossier' existe déjà.";
    }
}

// Exemple d'utilisation
//echo creerDossierSiExistePas('mon_dossier');
?>
