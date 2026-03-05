<?php
/**
 * dbCheck_athle.php — Creation schema BDD Athlétisme / DB schema creation
 * FR: Cree les tables normalisées, cles etrangeres pour le scraping athle.fr
 *
 * Attend : $databaseHandler (instance DatabaseHandler déjà connectée)
 */

// Récupération des tables existantes
$tables = $databaseHandler->getAllTables();

// ══════════════════════════════════════════════════════
//  TABLES DE RÉFÉRENCE (données uniques, jamais dupliquées)
// ══════════════════════════════════════════════════════

// ======================================================
// 1️⃣ TABLE villes                          ← get_result_villes_nom_array_2
//                                            ← get_emplacement
$columnsVilles = [
    "id_ville" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "nom_ville" => "VARCHAR(150) NOT NULL",
    "pays_ville" => "CHAR(3) DEFAULT ''",
    "departement_ville" => "VARCHAR(100) DEFAULT ''",
    "region_ville" => "VARCHAR(100) DEFAULT ''",
];
$tableName = "villes";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsVilles);
    $databaseHandler->action_sql("ALTER TABLE `villes` ADD UNIQUE KEY `uk_ville` (`nom_ville`, `pays_ville`)");
}

// ======================================================
// 2️⃣ TABLE clubs                            ← get_club_nom_complet_array_2
//                                            ← get_club_departement_array_2
//                                            ← get_club_region_array_2
//                                            ← id_get_club_nom_complet_array_2
//                                            ← id_get_club_departement_array_2
//                                            ← id_get_club_region_array_2
$columnsClubs = [
    "id_club" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "nom_club" => "VARCHAR(200) NOT NULL UNIQUE",
    "departement_club" => "VARCHAR(100) DEFAULT ''",
    "region_club" => "VARCHAR(100) DEFAULT ''",
    "vues" => "INT UNSIGNED DEFAULT 0",
];
$tableName = "clubs";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsClubs);
} else {
    $res = $databaseHandler->connection->query("SHOW COLUMNS FROM `clubs` LIKE 'vues'");
    if ($res && $res->num_rows === 0) {
        $databaseHandler->action_sql("ALTER TABLE `clubs` ADD COLUMN `vues` INT UNSIGNED DEFAULT 0");
    }
}

// ======================================================
// 3️⃣ TABLE epreuves                         ← get_epreuve_nom_complet
//                                            ← epreuve_sex_array_2
//                                            ← id_get_epreuve_nom_complet
//                                            ← id_epreuve_sex_array_2
$columnsEpreuves = [
    "id_epreuve" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "nom_epreuve" => "VARCHAR(100) NOT NULL UNIQUE",
    "sexe_epreuve" => "CHAR(1) DEFAULT ''",
];
$tableName = "epreuves";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsEpreuves);
}

// ======================================================
// 4️⃣ TABLE competitions
$columnsCompetitions = [
    "id_competition" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "nom_competition" => "VARCHAR(300) NOT NULL UNIQUE",
];
$tableName = "competitions";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsCompetitions);
}

// ======================================================
// 5️⃣ TABLE categories                        ← référence catégories FFA
//   Chaque catégorie a un code (SE, ES, JU...) et une tranche d'âge
$columnsCategories = [
    "id_categorie" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "code_categorie" => "VARCHAR(5) NOT NULL UNIQUE",
    "nom_categorie" => "VARCHAR(50) NOT NULL",
    "age_min" => "TINYINT UNSIGNED NOT NULL",
    "age_max" => "TINYINT UNSIGNED NOT NULL",
];
$tableName = "categories";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsCategories);

    // Pré-remplir les catégories FFA standards
    $cats = [
        ['EA', 'Éveil Athlétique', 4, 7],
        ['PO', 'Poussin',          8, 9],
        ['BE', 'Benjamin',         10, 11],
        ['MI', 'Minime',           12, 13],
        ['CA', 'Cadet',            14, 15],
        ['JU', 'Junior',           16, 17],
        ['ES', 'Espoir',           18, 22],
        ['SE', 'Senior',           23, 39],
        ['V1', 'Master 1',        40, 49],
        ['V2', 'Master 2',        50, 59],
        ['V3', 'Master 3',        60, 69],
        ['V4', 'Master 4',        70, 99],
    ];
    foreach ($cats as $c) {
        $databaseHandler->action_sql(
            "INSERT INTO `categories` (`code_categorie`, `nom_categorie`, `age_min`, `age_max`)
             VALUES ('{$c[0]}', '{$c[1]}', {$c[2]}, {$c[3]})"
        );
    }
}

// ======================================================
// 6️⃣ TABLE nationalites                      ← référence nationalités (code ISO)
$columnsNationalites = [
    "id_nationalite" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "code_nationalite" => "CHAR(3) NOT NULL UNIQUE",
    "nom_nationalite" => "VARCHAR(100) DEFAULT ''",
];
$tableName = "nationalites";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsNationalites);
}

// ══════════════════════════════════════════════════════
//  TABLE PRINCIPALE
// ══════════════════════════════════════════════════════

// ======================================================
// 5️⃣ TABLE athletes
//   ← info_all_array_id                      → id_athlete
//   ← get_result_users_nom_1_array_2         → nom_1_athlete
//   ← get_result_users_nom_2_array_2         → nom_2_athlete
//   ← get_result_users_nom_3_array_2         → nom_3_athlete
//   ← get_result_users_nom_4_array_2         → nom_4_athlete
//   ← get_users_nom_complet_array            → nom_complet_athlete
//   ← get_users_naissance_array_2            → date_naissance_athlete
//   ← get_users_nationality_array_2          → nationalite_athlete
//   ← get_cat_array_2                        → categorie_athlete
//   ← id_get_users_nom_complet_array         → id_athlete (FK)
//   ← id_get_result_users_nom_1_array_2      → id_athlete (FK)
//   ← id_get_result_users_nom_4_array_2      → id_athlete (FK)
//   ← id_get_users_nationality_array_2       → id_athlete (FK)
//   ← id_get_cat_array_2                     → id_athlete (FK)
//   ← id_get_users_naissance_array_2         → id_athlete (FK)
$columnsAthletes = [
    "id_athlete" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "athlete_id_externe" => "INT UNSIGNED NOT NULL UNIQUE",
    "nom_1_athlete" => "VARCHAR(100) DEFAULT ''",
    "nom_2_athlete" => "VARCHAR(100) DEFAULT ''",
    "nom_3_athlete" => "VARCHAR(100) DEFAULT ''",
    "nom_4_athlete" => "VARCHAR(100) DEFAULT ''",
    "nom_complet_athlete" => "VARCHAR(200) DEFAULT ''",
    "date_naissance_athlete" => "DATE DEFAULT NULL",
    "annee_naissance_athlete" => "SMALLINT UNSIGNED DEFAULT NULL",
    "id_ville_naissance" => "INT UNSIGNED DEFAULT NULL",
    "taille_cm_athlete" => "SMALLINT UNSIGNED DEFAULT NULL",
    "poids_kg_athlete" => "SMALLINT UNSIGNED DEFAULT NULL",
    "categorie_athlete" => "VARCHAR(10) DEFAULT ''",
    "sexe_athlete" => "CHAR(1) DEFAULT ''",
    "nationalite_athlete" => "CHAR(3) DEFAULT ''",
    "id_nationalite" => "INT UNSIGNED DEFAULT NULL",
    "licence_athlete" => "VARCHAR(20) DEFAULT ''",
    "date_creation_athlete" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    "vues" => "INT UNSIGNED DEFAULT 0",
];
$tableName = "athletes";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsAthletes);
} else {
    // Si la table existe déjà, ajouter les colonnes manquantes
    $res = $databaseHandler->connection->query("SHOW COLUMNS FROM `athletes` LIKE 'annee_naissance_athlete'");
    if ($res && $res->num_rows === 0) {
        $databaseHandler->action_sql("ALTER TABLE `athletes` ADD COLUMN `annee_naissance_athlete` SMALLINT UNSIGNED DEFAULT NULL AFTER `date_naissance_athlete`");
    }
    $res = $databaseHandler->connection->query("SHOW COLUMNS FROM `athletes` LIKE 'id_nationalite'");
    if ($res && $res->num_rows === 0) {
        $databaseHandler->action_sql("ALTER TABLE `athletes` ADD COLUMN `id_nationalite` INT UNSIGNED DEFAULT NULL AFTER `nationalite_athlete`");
    }
    $res = $databaseHandler->connection->query("SHOW COLUMNS FROM `athletes` LIKE 'vues'");
    if ($res && $res->num_rows === 0) {
        $databaseHandler->action_sql("ALTER TABLE `athletes` ADD COLUMN `vues` INT UNSIGNED DEFAULT 0");
    }
}

// FK athletes → villes (ville de naissance)
$databaseHandler->addForeignKey(
    "athletes",
    "id_ville_naissance",
    "villes",
    "id_ville",
    "SET NULL",
    "CASCADE"
);
// FK athletes → nationalites
$databaseHandler->addForeignKey(
    "athletes",
    "id_nationalite",
    "nationalites",
    "id_nationalite",
    "SET NULL",
    "CASCADE"
);

// ══════════════════════════════════════════════════════
//  TABLES DE DONNÉES (liées par IDs)
// ══════════════════════════════════════════════════════

// ======================================================
// 6️⃣ TABLE athlete_clubs (quel athlète dans quel club, quelle période)
$columnsAthleteClubs = [
    "id_athlete_club" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_athlete" => "INT UNSIGNED NOT NULL",
    "id_club" => "INT UNSIGNED NOT NULL",
    "annee_debut" => "SMALLINT UNSIGNED DEFAULT NULL",
    "annee_fin" => "SMALLINT UNSIGNED DEFAULT NULL",
];
$tableName = "athlete_clubs";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsAthleteClubs);
}

// FK athlete_clubs → athletes
$databaseHandler->addForeignKey(
    "athlete_clubs",
    "id_athlete",
    "athletes",
    "id_athlete",
    "CASCADE",
    "CASCADE"
);
// FK athlete_clubs → clubs
$databaseHandler->addForeignKey(
    "athlete_clubs",
    "id_club",
    "clubs",
    "id_club",
    "CASCADE",
    "CASCADE"
);

// ======================================================
// 7️⃣ TABLE athlete_medailles
$columnsAthleteMedailles = [
    "id_medaille" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_athlete" => "INT UNSIGNED NOT NULL",
    "type_medaille" => "ENUM('or','argent','bronze','autre') NOT NULL",
    "annee_medaille" => "SMALLINT UNSIGNED NOT NULL",
    "id_competition" => "INT UNSIGNED DEFAULT NULL",
    "id_epreuve" => "INT UNSIGNED DEFAULT NULL",
    "id_ville" => "INT UNSIGNED DEFAULT NULL",
];
$tableName = "athlete_medailles";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsAthleteMedailles);
}

// FK athlete_medailles → athletes
$databaseHandler->addForeignKey(
    "athlete_medailles",
    "id_athlete",
    "athletes",
    "id_athlete",
    "CASCADE",
    "CASCADE"
);
// FK athlete_medailles → competitions
$databaseHandler->addForeignKey(
    "athlete_medailles",
    "id_competition",
    "competitions",
    "id_competition",
    "SET NULL",
    "CASCADE"
);
// FK athlete_medailles → epreuves
$databaseHandler->addForeignKey(
    "athlete_medailles",
    "id_epreuve",
    "epreuves",
    "id_epreuve",
    "SET NULL",
    "CASCADE"
);
// FK athlete_medailles → villes
$databaseHandler->addForeignKey(
    "athlete_medailles",
    "id_ville",
    "villes",
    "id_ville",
    "SET NULL",
    "CASCADE"
);

// ======================================================
// 8️⃣ TABLE athlete_selections
$columnsAthleteSelections = [
    "id_selection" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_athlete" => "INT UNSIGNED NOT NULL",
    "type_selection" => "VARCHAR(20) DEFAULT ''",
    "date_selection" => "DATE DEFAULT NULL",
    "duree_jours_selection" => "SMALLINT UNSIGNED DEFAULT NULL",
    "age_selection" => "TINYINT UNSIGNED DEFAULT NULL",
    "id_competition" => "INT UNSIGNED DEFAULT NULL",
    "id_epreuve" => "INT UNSIGNED DEFAULT NULL",
    "classement_selection" => "TINYINT UNSIGNED DEFAULT NULL",
    "performance_selection" => "INT UNSIGNED DEFAULT NULL",
    "performance_brut_selection" => "VARCHAR(30) DEFAULT ''",
];
$tableName = "athlete_selections";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsAthleteSelections);
}

// FK athlete_selections → athletes
$databaseHandler->addForeignKey(
    "athlete_selections",
    "id_athlete",
    "athletes",
    "id_athlete",
    "CASCADE",
    "CASCADE"
);
// FK athlete_selections → competitions
$databaseHandler->addForeignKey(
    "athlete_selections",
    "id_competition",
    "competitions",
    "id_competition",
    "SET NULL",
    "CASCADE"
);
// FK athlete_selections → epreuves
$databaseHandler->addForeignKey(
    "athlete_selections",
    "id_epreuve",
    "epreuves",
    "id_epreuve",
    "SET NULL",
    "CASCADE"
);

// ======================================================
// 9️⃣ TABLE athlete_progressions
//   ← get_result_users_perf_array_2          → performance_progression
//   ← get_result_users_perf_array            → (même donnée, doublon)
//   ← get_result_date_perf_array_2           → date_progression
//   ← id_get_result_date_perf_array_2        → id_progression (FK)
//   ← get_vent_array_2                       → vent_progression
//   ← get_rp_array_2                         → rang_perf_progression
//   ← info_all_array_date                    → date_progression
$columnsAthleteProgressions = [
    "id_progression" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_athlete" => "INT UNSIGNED NOT NULL",
    "id_epreuve" => "INT UNSIGNED DEFAULT NULL",
    "id_categorie" => "INT UNSIGNED DEFAULT NULL",
    "id_club" => "INT UNSIGNED DEFAULT NULL",
    "annee_progression" => "SMALLINT UNSIGNED NOT NULL",
    "performance_progression" => "INT UNSIGNED DEFAULT NULL",
    "performance_brut_progression" => "VARCHAR(30) DEFAULT ''",
    "vent_progression" => "VARCHAR(10) DEFAULT ''",
    "date_progression" => "DATE DEFAULT NULL",
    "rang_perf_progression" => "SMALLINT UNSIGNED DEFAULT NULL",
    "ligue_dept_progression" => "VARCHAR(50) DEFAULT ''",
    "id_ville" => "INT UNSIGNED DEFAULT NULL",
];
$tableName = "athlete_progressions";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsAthleteProgressions);
} else {
    // Ajouter les colonnes manquantes sur tables existantes
    $res = $databaseHandler->connection->query("SHOW COLUMNS FROM `athlete_progressions` LIKE 'id_categorie'");
    if ($res && $res->num_rows === 0) {
        $databaseHandler->action_sql("ALTER TABLE `athlete_progressions` ADD COLUMN `id_categorie` INT UNSIGNED DEFAULT NULL AFTER `id_epreuve`");
    }
    $res = $databaseHandler->connection->query("SHOW COLUMNS FROM `athlete_progressions` LIKE 'id_club'");
    if ($res && $res->num_rows === 0) {
        $databaseHandler->action_sql("ALTER TABLE `athlete_progressions` ADD COLUMN `id_club` INT UNSIGNED DEFAULT NULL AFTER `id_categorie`");
    }
    $res = $databaseHandler->connection->query("SHOW COLUMNS FROM `athlete_progressions` LIKE 'ligue_dept_progression'");
    if ($res && $res->num_rows === 0) {
        $databaseHandler->action_sql("ALTER TABLE `athlete_progressions` ADD COLUMN `ligue_dept_progression` VARCHAR(50) DEFAULT '' AFTER `rang_perf_progression`");
    }
}

// FK athlete_progressions → athletes
$databaseHandler->addForeignKey(
    "athlete_progressions",
    "id_athlete",
    "athletes",
    "id_athlete",
    "CASCADE",
    "CASCADE"
);
// FK athlete_progressions → epreuves
$databaseHandler->addForeignKey(
    "athlete_progressions",
    "id_epreuve",
    "epreuves",
    "id_epreuve",
    "SET NULL",
    "CASCADE"
);
// FK athlete_progressions → villes
$databaseHandler->addForeignKey(
    "athlete_progressions",
    "id_ville",
    "villes",
    "id_ville",
    "SET NULL",
    "CASCADE"
);
// FK athlete_progressions → categories
$databaseHandler->addForeignKey(
    "athlete_progressions",
    "id_categorie",
    "categories",
    "id_categorie",
    "SET NULL",
    "CASCADE"
);
// FK athlete_progressions → clubs
$databaseHandler->addForeignKey(
    "athlete_progressions",
    "id_club",
    "clubs",
    "id_club",
    "SET NULL",
    "CASCADE"
);

// ======================================================
// 🔟 TABLE athlete_records                   ← get_rp_array_2
$columnsAthleteRecords = [
    "id_record" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_athlete" => "INT UNSIGNED NOT NULL",
    "id_epreuve" => "INT UNSIGNED DEFAULT NULL",
    "id_categorie" => "INT UNSIGNED DEFAULT NULL",
    "performance_record" => "INT UNSIGNED DEFAULT NULL",
    "performance_brut_record" => "VARCHAR(30) DEFAULT ''",
    "date_record" => "DATE DEFAULT NULL",
    "id_club" => "INT UNSIGNED DEFAULT NULL",
    "ligue_dept_record" => "VARCHAR(50) DEFAULT ''",
    "id_ville" => "INT UNSIGNED DEFAULT NULL",
];
$tableName = "athlete_records";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsAthleteRecords);
} else {
    // Ajouter id_categorie si manquant (remplace categorie_record)
    $res = $databaseHandler->connection->query("SHOW COLUMNS FROM `athlete_records` LIKE 'id_categorie'");
    if ($res && $res->num_rows === 0) {
        $databaseHandler->action_sql("ALTER TABLE `athlete_records` ADD COLUMN `id_categorie` INT UNSIGNED DEFAULT NULL AFTER `id_epreuve`");
    }
}

// FK athlete_records → athletes
$databaseHandler->addForeignKey(
    "athlete_records",
    "id_athlete",
    "athletes",
    "id_athlete",
    "CASCADE",
    "CASCADE"
);
// FK athlete_records → epreuves
$databaseHandler->addForeignKey(
    "athlete_records",
    "id_epreuve",
    "epreuves",
    "id_epreuve",
    "SET NULL",
    "CASCADE"
);
// FK athlete_records → clubs
$databaseHandler->addForeignKey(
    "athlete_records",
    "id_club",
    "clubs",
    "id_club",
    "SET NULL",
    "CASCADE"
);
// FK athlete_records → villes
$databaseHandler->addForeignKey(
    "athlete_records",
    "id_ville",
    "villes",
    "id_ville",
    "SET NULL",
    "CASCADE"
);
// FK athlete_records → categories
$databaseHandler->addForeignKey(
    "athlete_records",
    "id_categorie",
    "categories",
    "id_categorie",
    "SET NULL",
    "CASCADE"
);

// ======================================================
// 1️⃣1️⃣ TABLE athlete_podiums
$columnsAthletePodiums = [
    "id_podium" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_athlete" => "INT UNSIGNED NOT NULL",
    "annee_podium" => "SMALLINT UNSIGNED NOT NULL",
    "niveau_competition" => "VARCHAR(30) DEFAULT ''",
    "place_podium" => "VARCHAR(100) DEFAULT ''",
    "rang_podium" => "TINYINT UNSIGNED DEFAULT NULL",
    "id_epreuve" => "INT UNSIGNED DEFAULT NULL",
    "performance_podium" => "INT UNSIGNED DEFAULT NULL",
    "performance_brut_podium" => "VARCHAR(30) DEFAULT ''",
    "vent_podium" => "VARCHAR(10) DEFAULT ''",
    "date_podium" => "DATE DEFAULT NULL",
    "id_ville" => "INT UNSIGNED DEFAULT NULL",
];
$tableName = "athlete_podiums";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsAthletePodiums);
}

// FK athlete_podiums → athletes
$databaseHandler->addForeignKey("athlete_podiums", "id_athlete", "athletes", "id_athlete", "CASCADE", "CASCADE");
// FK athlete_podiums → epreuves
$databaseHandler->addForeignKey("athlete_podiums", "id_epreuve", "epreuves", "id_epreuve", "SET NULL", "CASCADE");
// FK athlete_podiums → villes
$databaseHandler->addForeignKey("athlete_podiums", "id_ville", "villes", "id_ville", "SET NULL", "CASCADE");

// ======================================================
// 1️⃣2️⃣ TABLE athlete_resultats
$columnsAthleteResultats = [
    "id_resultat" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_athlete" => "INT UNSIGNED NOT NULL",
    "annee_resultat" => "SMALLINT UNSIGNED NOT NULL",
    "date_resultat" => "DATE DEFAULT NULL",
    "id_epreuve" => "INT UNSIGNED DEFAULT NULL",
    "performance_resultat" => "INT UNSIGNED DEFAULT NULL",
    "performance_brut_resultat" => "VARCHAR(30) DEFAULT ''",
    "vent_resultat" => "VARCHAR(10) DEFAULT ''",
    "tour_resultat" => "VARCHAR(30) DEFAULT ''",
    "place_resultat" => "TINYINT UNSIGNED DEFAULT NULL",
    "niveau_resultat" => "VARCHAR(10) DEFAULT ''",
    "points_resultat" => "SMALLINT UNSIGNED DEFAULT NULL",
    "id_ville" => "INT UNSIGNED DEFAULT NULL",
];
$tableName = "athlete_resultats";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsAthleteResultats);
}

// FK athlete_resultats → athletes
$databaseHandler->addForeignKey("athlete_resultats", "id_athlete", "athletes", "id_athlete", "CASCADE", "CASCADE");
// FK athlete_resultats → epreuves
$databaseHandler->addForeignKey("athlete_resultats", "id_epreuve", "epreuves", "id_epreuve", "SET NULL", "CASCADE");
// FK athlete_resultats → villes
$databaseHandler->addForeignKey("athlete_resultats", "id_ville", "villes", "id_ville", "SET NULL", "CASCADE");

// ======================================================
// 1️⃣3️⃣ TABLE athlete_niveaux (parent)
$columnsAthleteNiveaux = [
    "id_niveau" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_athlete" => "INT UNSIGNED NOT NULL",
    "annee_niveau" => "SMALLINT UNSIGNED NOT NULL",
    "code_niveau" => "VARCHAR(10) DEFAULT ''",
    "points_niveau" => "SMALLINT UNSIGNED DEFAULT NULL",
    "id_club" => "INT UNSIGNED DEFAULT NULL",
];
$tableName = "athlete_niveaux";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsAthleteNiveaux);
    $databaseHandler->action_sql("ALTER TABLE `athlete_niveaux` ADD UNIQUE KEY `uk_athlete_annee` (`id_athlete`, `annee_niveau`)");
}

// FK athlete_niveaux → athletes
$databaseHandler->addForeignKey("athlete_niveaux", "id_athlete", "athletes", "id_athlete", "CASCADE", "CASCADE");
// FK athlete_niveaux → clubs
$databaseHandler->addForeignKey("athlete_niveaux", "id_club", "clubs", "id_club", "SET NULL", "CASCADE");

// ======================================================
// 1️⃣4️⃣ TABLE athlete_niv_perfs (enfant)
$columnsNiveauPerfs = [
    "id_niveau_perf" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_niveau" => "INT UNSIGNED NOT NULL",
    "id_epreuve" => "INT UNSIGNED DEFAULT NULL",
    "performance_niveau_perf" => "INT UNSIGNED DEFAULT NULL",
    "performance_brut_niveau_perf" => "VARCHAR(30) DEFAULT ''",
    "code_perf_niveau" => "VARCHAR(10) DEFAULT ''",
];
$tableName = "athlete_niv_perfs";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsNiveauPerfs);
}

// FK athlete_niv_perfs → athlete_niveaux (CASCADE car enfant)
$databaseHandler->addForeignKey("athlete_niv_perfs", "id_niveau", "athlete_niveaux", "id_niveau", "CASCADE", "CASCADE");
// FK athlete_niv_perfs → epreuves
$databaseHandler->addForeignKey("athlete_niv_perfs", "id_epreuve", "epreuves", "id_epreuve", "SET NULL", "CASCADE");

// ══════════════════════════════════════════════════════
//  TABLES UTILISATEURS & AUTHENTIFICATION
// ══════════════════════════════════════════════════════

// ======================================================
// 1️⃣5️⃣ TABLE users
$columnsUsers = [
    "id_user" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "email" => "VARCHAR(255) NOT NULL UNIQUE",
    "password_hash" => "VARCHAR(255) DEFAULT ''",
    "nom" => "VARCHAR(100) DEFAULT ''",
    "prenom" => "VARCHAR(100) DEFAULT ''",
    "role" => "ENUM('athlete','coach','club','admin') DEFAULT 'athlete'",
    "id_athlete" => "INT UNSIGNED DEFAULT NULL",
    "google_id" => "VARCHAR(255) DEFAULT NULL",
    "oauth_provider" => "VARCHAR(50) DEFAULT NULL",
    "picture" => "TEXT DEFAULT NULL",
    "email_verified" => "TINYINT(1) DEFAULT NULL",
    "locale" => "VARCHAR(10) DEFAULT NULL",
    "last_login" => "DATETIME DEFAULT NULL",
    "date_creation" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
];
$tableName = "users";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsUsers);
    $databaseHandler->action_sql("ALTER TABLE `users` ADD UNIQUE INDEX `uk_google_id` (`google_id`)");
} else {
    // Migration OAuth : ajouter google_id si absent
    $res = $databaseHandler->connection->query("SHOW COLUMNS FROM `users` LIKE 'google_id'");
    if ($res && $res->num_rows === 0) {
        $databaseHandler->action_sql("ALTER TABLE `users` ADD COLUMN `google_id` VARCHAR(255) DEFAULT NULL AFTER `id_athlete`");
        $databaseHandler->action_sql("ALTER TABLE `users` ADD UNIQUE INDEX `uk_google_id` (`google_id`)");
    }
    // Migration OAuth : ajouter oauth_provider si absent
    $res = $databaseHandler->connection->query("SHOW COLUMNS FROM `users` LIKE 'oauth_provider'");
    if ($res && $res->num_rows === 0) {
        $databaseHandler->action_sql("ALTER TABLE `users` ADD COLUMN `oauth_provider` VARCHAR(50) DEFAULT NULL AFTER `google_id`");
    }
    // Migration OAuth : password_hash DEFAULT '' (users OAuth n'ont pas de mdp)
    // Idempotent — toujours safe a executer
    $databaseHandler->action_sql("ALTER TABLE `users` MODIFY COLUMN `password_hash` VARCHAR(255) DEFAULT ''");
    // Migration : colonnes profil Google (picture, email_verified, locale, last_login)
    $migCols = [
        'picture'        => "TEXT DEFAULT NULL AFTER `oauth_provider`",
        'email_verified' => "TINYINT(1) DEFAULT NULL AFTER `picture`",
        'locale'         => "VARCHAR(10) DEFAULT NULL AFTER `email_verified`",
        'last_login'     => "DATETIME DEFAULT NULL AFTER `locale`",
    ];
    foreach ($migCols as $col => $def) {
        $res = $databaseHandler->connection->query("SHOW COLUMNS FROM `users` LIKE '$col'");
        if ($res && $res->num_rows === 0) {
            $databaseHandler->action_sql("ALTER TABLE `users` ADD COLUMN `$col` $def");
        }
    }
    // Migration : picture VARCHAR(500) → TEXT (URLs Google trop longues)
    $databaseHandler->action_sql("ALTER TABLE `users` MODIFY COLUMN `picture` TEXT DEFAULT NULL");
}
// FK users → athletes (lien optionnel)
$databaseHandler->addForeignKey("users", "id_athlete", "athletes", "id_athlete", "SET NULL", "CASCADE");

// ======================================================
// 1️⃣6️⃣ TABLE user_sessions
$columnsUserSessions = [
    "id_session" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_user" => "INT UNSIGNED NOT NULL",
    "token" => "VARCHAR(64) NOT NULL UNIQUE",
    "expire_at" => "DATETIME NOT NULL",
    "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
];
$tableName = "user_sessions";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsUserSessions);
}
// FK user_sessions → users
$databaseHandler->addForeignKey("user_sessions", "id_user", "users", "id_user", "CASCADE", "CASCADE");

// ======================================================
// 1️⃣7️⃣ TABLE coach_athletes (relation coach → athletes suivis)
$columnsCoachAthletes = [
    "id" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_coach" => "INT UNSIGNED NOT NULL",
    "id_athlete" => "INT UNSIGNED NOT NULL",
];
$tableName = "coach_athletes";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsCoachAthletes);
    $databaseHandler->action_sql("ALTER TABLE `coach_athletes` ADD UNIQUE KEY `uk_coach_athlete` (`id_coach`, `id_athlete`)");
}
// FK coach_athletes → users (coach)
$databaseHandler->addForeignKey("coach_athletes", "id_coach", "users", "id_user", "CASCADE", "CASCADE");
// FK coach_athletes → athletes
$databaseHandler->addForeignKey("coach_athletes", "id_athlete", "athletes", "id_athlete", "CASCADE", "CASCADE");

// ======================================================
// 1️⃣8️⃣ TABLE athlete_perfs_manuelles (performances saisies manuellement)
$columnsPerfsManuel = [
    "id_perf" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_athlete" => "INT UNSIGNED NOT NULL",
    "id_user" => "INT UNSIGNED NOT NULL",
    "id_epreuve" => "INT UNSIGNED DEFAULT NULL",
    "performance" => "INT UNSIGNED DEFAULT NULL",
    "performance_brut" => "VARCHAR(30) DEFAULT ''",
    "date_perf" => "DATE DEFAULT NULL",
    "lieu" => "VARCHAR(200) DEFAULT ''",
    "notes" => "TEXT DEFAULT NULL",
    "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    "updated_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
];
$tableName = "athlete_perfs_manuelles";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsPerfsManuel);
}
// FK athlete_perfs_manuelles → athletes
$databaseHandler->addForeignKey("athlete_perfs_manuelles", "id_athlete", "athletes", "id_athlete", "CASCADE", "CASCADE");
// FK athlete_perfs_manuelles → users (auteur)
$databaseHandler->addForeignKey("athlete_perfs_manuelles", "id_user", "users", "id_user", "CASCADE", "CASCADE");
// FK athlete_perfs_manuelles → epreuves
$databaseHandler->addForeignKey("athlete_perfs_manuelles", "id_epreuve", "epreuves", "id_epreuve", "SET NULL", "CASCADE");

// ======================================================
// 1️⃣9️⃣ TABLE logs (tracking activite utilisateur)
$columnsLogs = [
    "id_log" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "ts" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    "ip" => "VARCHAR(45) DEFAULT ''",
    "ua" => "VARCHAR(500) DEFAULT ''",
    "sid" => "VARCHAR(100) DEFAULT ''",
    "uid" => "INT UNSIGNED DEFAULT NULL",
    "uname" => "VARCHAR(100) DEFAULT ''",
    "page" => "VARCHAR(500) DEFAULT ''",
    "action" => "VARCHAR(50) DEFAULT 'unknown'",
    "detail" => "VARCHAR(500) DEFAULT ''",
    "value" => "VARCHAR(1000) DEFAULT ''",
    "target" => "VARCHAR(200) DEFAULT ''",
    "screen" => "VARCHAR(20) DEFAULT ''",
    "lang" => "VARCHAR(10) DEFAULT ''",
    "referrer" => "VARCHAR(500) DEFAULT ''",
    "duration_ms" => "INT UNSIGNED DEFAULT NULL",
];
$tableName = "logs";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsLogs);
    // Index pour les requetes frequentes
    $databaseHandler->action_sql("ALTER TABLE `logs` ADD INDEX `idx_logs_ts` (`ts`)");
    $databaseHandler->action_sql("ALTER TABLE `logs` ADD INDEX `idx_logs_action` (`action`)");
    $databaseHandler->action_sql("ALTER TABLE `logs` ADD INDEX `idx_logs_sid` (`sid`)");
    $databaseHandler->action_sql("ALTER TABLE `logs` ADD INDEX `idx_logs_ip` (`ip`)");
}

// ======================================================
// 2️⃣0️⃣ TABLE athlete_follows (suivi athlete par email)
$columnsFollows = [
    "id_follow" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "email" => "VARCHAR(255) NOT NULL",
    "athlete_id_ext" => "INT UNSIGNED NOT NULL",
    "id_user" => "INT UNSIGNED DEFAULT NULL",
    "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
];
$tableName = "athlete_follows";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsFollows);
    $databaseHandler->action_sql("ALTER TABLE `athlete_follows` ADD UNIQUE KEY `uk_email_athlete` (`email`, `athlete_id_ext`)");
    $databaseHandler->action_sql("ALTER TABLE `athlete_follows` ADD INDEX `idx_follows_athlete` (`athlete_id_ext`)");
    $databaseHandler->action_sql("ALTER TABLE `athlete_follows` ADD INDEX `idx_follows_email` (`email`)");
}

// ======================================================
// 2️⃣1️⃣ TABLE email_subscribers (newsletter + PDF)
$columnsSubs = [
    "id_sub" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "email" => "VARCHAR(255) NOT NULL",
    "source" => "VARCHAR(30) DEFAULT 'newsletter'",
    "detail" => "VARCHAR(255) DEFAULT ''",
    "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
];
$tableName = "email_subscribers";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsSubs);
    $databaseHandler->action_sql("ALTER TABLE `email_subscribers` ADD UNIQUE KEY `uk_email_source` (`email`, `source`)");
    $databaseHandler->action_sql("ALTER TABLE `email_subscribers` ADD INDEX `idx_subs_email` (`email`)");
}

// ======================================================
// 2️⃣2️⃣ TABLE club_follows (suivi club par email)
$columnsClubFollows = [
    "id_follow" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "email" => "VARCHAR(255) NOT NULL",
    "club_id" => "INT UNSIGNED NOT NULL",
    "id_user" => "INT UNSIGNED DEFAULT NULL",
    "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
];
$tableName = "club_follows";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsClubFollows);
    $databaseHandler->action_sql("ALTER TABLE `club_follows` ADD UNIQUE KEY `uk_email_club` (`email`, `club_id`)");
    $databaseHandler->action_sql("ALTER TABLE `club_follows` ADD INDEX `idx_cfollows_club` (`club_id`)");
    $databaseHandler->action_sql("ALTER TABLE `club_follows` ADD INDEX `idx_cfollows_email` (`email`)");
}

// Schema BDD verifie (pas d'affichage si tout existe deja)

// ======================================================
// 2️⃣3️⃣ TABLE password_resets (reinitialisation mot de passe)
$columnsPasswordResets = [
    "id_reset" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "id_user" => "INT UNSIGNED NOT NULL",
    "token" => "VARCHAR(64) NOT NULL UNIQUE",
    "expire_at" => "DATETIME NOT NULL",
    "used" => "TINYINT(1) UNSIGNED DEFAULT 0",
    "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
];
$tableName = "password_resets";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, $columnsPasswordResets);
    $databaseHandler->action_sql("ALTER TABLE `password_resets` ADD INDEX `idx_pr_token` (`token`)");
    $databaseHandler->action_sql("ALTER TABLE `password_resets` ADD INDEX `idx_pr_user` (`id_user`)");
}
// FK password_resets → users
$databaseHandler->addForeignKey("password_resets", "id_user", "users", "id_user", "CASCADE", "CASCADE");

// ======================================================
// TABLE athlete_vues_ip — Tracking vues profil par IP unique
// ======================================================
$tableName = "athlete_vues_ip";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, [
        "ip" => "VARCHAR(45) NOT NULL",
        "athlete_id_ext" => "INT UNSIGNED NOT NULL",
        "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    ]);
    $databaseHandler->action_sql("ALTER TABLE `athlete_vues_ip` ADD PRIMARY KEY (`ip`, `athlete_id_ext`)");
}

// ======================================================
// TABLE club_vues_ip — Tracking vues club par IP unique
// ======================================================
$tableName = "club_vues_ip";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, [
        "ip" => "VARCHAR(45) NOT NULL",
        "club_id" => "INT UNSIGNED NOT NULL",
        "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    ]);
    $databaseHandler->action_sql("ALTER TABLE `club_vues_ip` ADD PRIMARY KEY (`ip`, `club_id`)");
}

// ======================================================
// TABLE search_tracking — Tracking recherches et consultations
// ======================================================
$tableName = "search_tracking";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, [
        "id_search" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
        "ip" => "VARCHAR(45) NOT NULL DEFAULT ''",
        "query_text" => "VARCHAR(255) NOT NULL DEFAULT ''",
        "search_type" => "ENUM('athlete','club','epreuve','ville','general') DEFAULT 'general'",
        "source" => "ENUM('live_search','page_view','panel_open') DEFAULT 'live_search'",
        "entity_id" => "INT UNSIGNED DEFAULT NULL",
        "entity_name" => "VARCHAR(255) DEFAULT NULL",
        "result_count" => "INT UNSIGNED DEFAULT 0",
        "page" => "VARCHAR(50) DEFAULT NULL",
        "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    ]);
    $databaseHandler->action_sql("ALTER TABLE `search_tracking` ADD INDEX `idx_st_type` (`search_type`)");
    $databaseHandler->action_sql("ALTER TABLE `search_tracking` ADD INDEX `idx_st_source` (`source`)");
    $databaseHandler->action_sql("ALTER TABLE `search_tracking` ADD INDEX `idx_st_created` (`created_at`)");
    $databaseHandler->action_sql("ALTER TABLE `search_tracking` ADD INDEX `idx_st_ip` (`ip`)");
    $databaseHandler->action_sql("ALTER TABLE `search_tracking` ADD INDEX `idx_st_entity` (`search_type`, `entity_id`)");
    $databaseHandler->action_sql("ALTER TABLE `search_tracking` ADD INDEX `idx_st_query` (`query_text`(100))");
}

// ======================================================
// TABLE contact_messages — Messages de contact (page blocage)
// ======================================================
$tableName = "contact_messages";
if (!in_array($tableName, $tables, true)) {
    $databaseHandler->create_table($tableName, [
        "id_msg" => "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
        "ip" => "VARCHAR(45) NOT NULL DEFAULT ''",
        "nom" => "VARCHAR(100) NOT NULL DEFAULT ''",
        "email" => "VARCHAR(200) NOT NULL DEFAULT ''",
        "message" => "TEXT NOT NULL",
        "lu" => "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0",
        "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    ]);
}

// ======================================================
