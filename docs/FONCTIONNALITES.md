# BOKONZI — Documentation complete des fonctionnalites

Plateforme d'athletisme francais qui collecte les donnees de 300 000+ athletes depuis athle.fr,
les stocke en base de donnees MySQL, et offre un dashboard analytique complet avec graphiques interactifs,
recherche avancee, comparaison d'athletes et de clubs, fiches detaillees, biographies auto-generees,
QR codes dynamiques, SEO avance (Schema.org, Open Graph, sitemap XML) et outils d'analyse.

**URL de production :** https://bokonzi.com

---

## TABLE DES MATIERES

1. [Arborescence du projet](#arborescence-du-projet)
2. [Dossier core/](#dossier--core)
3. [Dossier api/](#dossier--api)
4. [Dossier scraping/](#dossier--scraping)
5. [Dossier pages/](#dossier--pages)
6. [Dossier admin/](#dossier--admin)
7. [Dossier Class/](#dossier--class)
8. [Dashboard principal (index.php)](#dashboard-principal-indexphp)
9. [SEO et referencement](#seo-et-referencement)
10. [QR Codes dynamiques](#qr-codes-dynamiques)
11. [Systeme de cache](#systeme-de-cache)
12. [Stockage des donnees](#stockage-des-donnees)
13. [Securite](#securite)
14. [Technologies](#technologies)
15. [Resume des fonctionnalites cles](#resume-des-fonctionnalites-cles)

---

## ARBORESCENCE DU PROJET

```
BK/
|
|-- core/                Noyau : connexion BDD, authentification, SEO, chemins
|   |-- credentials.php  Identifiants BDD (centralises, un seul fichier)
|   |-- db.php           Connexion mysqli ($conn)
|   |-- auth.php         Systeme d'authentification (9 fonctions)
|   |-- oauth_config.php Config OAuth Google (extensible Facebook/Instagram)
|   |-- paths.php        Constante BK_BASE (local /BK vs prod vide)
|   |-- seo.php          Generation SEO : meta, Open Graph, Twitter Cards, Schema.org JSON-LD
|   |-- insert_athle.php Insertion BDD depuis scraping (cache memoire)
|   |-- dbCheck_athle.php Creation schema BDD (23 tables + 38 FK)
|
|-- api/                 API REST JSON (22 endpoints publics + auth)
|   |-- config.php       Configuration partagee (BDD, headers JSON, CORS)
|   |-- athlete.php      Profil complet d'un athlete (10 categories de donnees)
|   |-- search.php       Recherche avancee multi-criteres (12 filtres)
|   |-- search_track.php Tracking recherches/consultations (POST sendBeacon)
|   |-- liste.php        Liste paginee de tous les athletes
|   |-- stats.php        Statistiques globales (cache 24h)
|   |-- classement.php   Classement par epreuve
|   |-- clubs.php        Liste des clubs
|   |-- club_stats.php   Statistiques detaillees d'un club
|   |-- epreuves.php     Liste des epreuves
|   |-- epreuve_stats.php Stats detaillees d'une epreuve
|   |-- epreuve_records.php Records d'une epreuve
|   |-- villes.php       Liste des villes
|   |-- ville_stats.php  Stats detaillees d'une ville
|   |-- top_searched.php Top clubs/athletes recherches (depuis search_tracking, cache 10min)
|   |-- competitions.php Liste des competitions
|   |-- performances.php CRUD performances manuelles
|   |-- contact.php      Messages contact (POST=envoyer, GET=admin actions)
|   |-- log.php          Logging actions utilisateur (POST batch BDD, GET lecture)
|   |-- follow.php       Suivi athletes + clubs (POST toggle, GET status)
|   |-- subscribe.php    Collecte email (newsletter, PDF)
|   |-- auth/            Endpoints d'authentification
|       |-- login.php    Connexion classique (POST, super admin, rate limit 5/jour)
|       |-- register.php Inscription classique (POST, legacy)
|       |-- logout.php   Deconnexion (POST)
|       |-- me.php       Utilisateur courant (GET)
|       |-- forgot_password.php  Demande reinitialisation mdp (POST email)
|       |-- reset_password.php   Reset mdp avec token (POST token+password)
|       |-- google_login.php    Initie le flux OAuth Google (state CSRF + redirect)
|       |-- google_callback.php Callback Google (echange code, cree/lie user, session)
|
|-- scraping/            Pipeline de collecte de donnees athle.fr
|   |-- scrape_functions.php  scrapeParallel() — curl_multi 7 athletes x 3 pages
|   |-- scraper.php           Scraping principal (batch 7, skip BDD, reset, test manuel)
|   |-- check_sync.php        Verification + scraping des absents (2 phases)
|   |-- check_athletes.php    Audit completude src/ vs BDD → absents.json
|   |-- import_bdd.php        Import fichiers JSON src/ → BDD
|
|-- pages/               Pages frontend independantes
|   |-- profil.php       Profil public partageable (SEO + QR code)
|   |-- global_athlete.php Fiche athlete complete (SEO + QR code)
|   |-- recherche_live.php Recherche instantanee
|   |-- classement.php   Classement par epreuve
|   |-- performances.php Saisie manuelle (auth requise)
|   |-- recherche.php    Recherche dans les fichiers
|   |-- exemples.php     Exemples d'utilisation API
|   |-- test_api.php     Interface test endpoints
|   |-- athlete.php      Affichage JSON brut
|
|-- admin/               Scripts d'administration
|   |-- panel.php        Super Admin dashboard (16 sections, auth BDD credentials)
|   |-- setup_bdd.php    Creation tables (23 tables + 30+ FK)
|   |-- clear_cache.php  Vider cache (?prefix= pour cibler)
|   |-- cache_urls.php   Regeneration cache URLs
|   |-- fix_perf_int.php Correction INT perfs (padding dixiemes)
|   |-- logs.php         Visualisation logs (acces restreint par email)
|   |-- remote_check.php API JSON admin a distance (scrape_status, test_scrape, count)
|
|-- Class/               Classes utilitaires PHP
|   |-- AthleteScraper.php  Scraper web athle.fr (56 Ko)
|   |-- DatabaseHandler.php Gestionnaire BDD avance (63 Ko)
|   |-- (30+ utilitaires) Formatage, validation, nettoyage, etc.
|
|-- src/                 300 000+ fichiers JSON scrapes (1 par athlete)
|-- cache/               Cache API fichier JSON (TTL 24h, protege .htaccess)
|-- logs/                Logs analytics JSON
|-- docs/                Documentation technique
|
|-- index.php            Dashboard principal (~7300 lignes, 8 sections)
|-- dashboard.css        Styles du dashboard (theme sombre)
|-- common.css           Styles communs (panel, auth, scraping)
|-- nav.php              Barre de navigation globale
|-- panel.php            Tour de controle admin
|-- login.php            Page de connexion (bouton Google OAuth uniquement)
|-- register.php         Page d'inscription (bouton Google OAuth uniquement)
|-- sitemap.php          Sitemap XML dynamique (index + sous-sitemaps pagines)
|-- robots.txt           Directives pour les robots de Google
|-- google3c52de7c1227f892.html  Verification Google Search Console
```

---

## DOSSIER : core/

Le noyau de l'application. Contient 7 fichiers qui fournissent les services essentiels.

### core/credentials.php
**Role** : Source unique des identifiants de la base de donnees.
- Definit `$dbname`, `$username`, `$password`
- Centralise les identifiants pour eviter la duplication

### core/db.php
**Role** : Connexion a la base de donnees MySQL.
- Cree la variable `$conn` (objet mysqli)
- Configure le charset en `utf8mb4` (supporte les emojis et caracteres speciaux)
- Affiche une erreur 500 si la connexion echoue
- PAS de headers HTTP (permet d'etre inclus par les pages HTML sans casser le Content-Type)
- Utilise par : toutes les pages frontend et par api/config.php

### core/auth.php
**Role** : Systeme complet d'authentification par session/token. 9 fonctions :
- `hashPassword($password)` : hache un mot de passe avec l'algorithme BCRYPT
- `verifyPassword($password, $hash)` : verifie un mot de passe contre son hash
- `generateToken()` : genere un token de session aleatoire (64 caracteres hexadecimaux, 32 octets aleatoires)
- `createSession($conn, $userId)` : cree une session en BDD + place un cookie `bk_token` (HTTPOnly, SameSite=Lax, expire dans 30 jours)
- `getCurrentUser($conn)` : lit le cookie `bk_token` et retourne l'utilisateur connecte (ou null si pas connecte)
- `requireAuth($conn)` : protege une page frontend — redirige vers login.php si l'utilisateur n'est pas connecte
- `requireRole($conn, $roles)` : verifie que l'utilisateur a un role specifique (athlete, coach, club, admin)
- `logout($conn)` : supprime la session en BDD + efface le cookie
- `requireAuthApi($conn)` : protege un endpoint API — retourne JSON 401 si non connecte

### core/oauth_config.php
**Role** : Configuration des providers OAuth pour la connexion sociale.
- Detection automatique local (`http://localhost/BK`) vs production (`https://bokonzi.com`)
- Constantes Google : `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- Extensible : emplacements pre-configures pour Facebook et Instagram (commentes)

### core/paths.php
**Role** : Gestion des chemins entre environnements.
- Definit la constante `BK_BASE` : `/BK` en local (XAMPP), `''` (vide) en production (Hostinger)
- Permet de construire des liens absolus qui fonctionnent depuis n'importe quel sous-dossier
- Exemple : `<a href="<?= BK_BASE ?>/pages/profil.php">` fonctionne partout

### core/seo.php
**Role** : Generation SEO avancee pour les pages athletes. Genere automatiquement :
- **Meta description** : resume l'athlete (nom, club, categorie, records, medailles, selections)
- **Canonical URL** : evite le contenu duplique dans Google
- **Open Graph** (7+ balises) : pour le partage sur Facebook, LinkedIn, etc.
- **Twitter Cards** (4 balises) : pour le partage sur Twitter/X
- **Schema.org JSON-LD** : donnees structurees pour Google (type Person + BreadcrumbList)
  - Nom, prenom, nationalite (35+ pays supportes), date/lieu de naissance
  - Taille, poids, club, lien athle.fr (sameAs)
  - Medailles comme recompenses (award)
  - Description structuree (sexe, categorie, club, records, medailles)
  - Fil d'Ariane (Accueil > Athletes > Nom)

**2 modes d'utilisation :**
1. `seoFromSrcFile($athleteIdExterne)` : lecture rapide depuis les fichiers src/ (pas d'appel API, pas de BDD — ~1ms)
2. `generateAthleteSEO($data)` : depuis les donnees API deja chargees (fallback)

Le fichier verifie l'existence du fichier src/ avec `file_exists()` avant de le lire.

### core/insert_athle.php
**Role** : Insertion optimisee d'un athlete complet en BDD apres scraping.

**Fonctions exportees** :
- `loadRefCache($conn) : array` — pre-charge 6 tables de reference en memoire (villes, clubs, epreuves, competitions, categories, nationalites). Elimine les SELECT repetitifs pendant l'insertion en masse. Appele 1 seule fois par session de scraping, reutilise pour 1000+ athletes.
- `fk($val) : string` — helper NULL-safe pour FK (`NULL` ou `'valeur'`)
- `cachedGetOrInsertId(&$cache, $conn, $table, $colId, $colNom, $valeur, $extraCols) : int|null` — lookup cache memoire (0 query si hit) → INSERT IGNORE si absent → SELECT ID. Met a jour le cache pour les lookups suivants.
- `cachedGetCategorieId(&$cache, $code) : int|null` — lookup cache seulement (categories pre-remplies, pas d'INSERT)
- `insertAthleteData($scraper, $conn, &$cache)` — insertion complete en 9 sections :
  1. **athletes** : INSERT ou UPDATE (si `athlete_id_externe` existe → DELETE CASCADE enfants → re-INSERT)
  2. **athlete_clubs** : batch INSERT (id_club, annee_debut, annee_fin)
  3. **athlete_medailles** : batch INSERT (type, competition, epreuve, ville, annee)
  4. **athlete_selections** : batch INSERT (type, date, duree, age, competition, epreuve, classement, perf)
  5. **athlete_progressions** : batch INSERT (epreuve, categorie, club, annee, perf, vent, date, lieu)
  6. **athlete_records** : batch INSERT (epreuve, categorie, perf, date, club, lieu)
  7. **athlete_podiums** : batch INSERT (annee, niveau, place, rang, epreuve, perf, vent, date, lieu)
  8. **athlete_resultats** : batch INSERT (annee, date, epreuve, perf, vent, tour, place, niveau, points, lieu)
  9. **athlete_niveaux + athlete_niv_perfs** : sequentiel (besoin de l'ID insere pour les sous-perfs)

**Optimisation** : 1 seul INSERT multi-lignes par section (pas 1 query par ligne)

### core/dbCheck_athle.php
**Role** : Creation automatique du schema de la base de donnees.
- Cree les 23 tables avec tous les champs et index
- Ajoute les 30+ cles etrangeres (ON DELETE CASCADE pour athletes, ON DELETE SET NULL pour references)
- Pre-remplit les categories FFA (EA, PO, BE, MI, CA, JU, ES, SE, V1-V4)
- Pre-remplit les nationalites (codes ISO 3 lettres)
- Idempotent : `CREATE TABLE IF NOT EXISTS` (peut etre relance sans risque)
- N'affiche plus le schema quand les tables existent deja

---

## DOSSIER : api/

API REST qui retourne du JSON. 21 endpoints independants. Tous retournent `{"success": true/false, ...}`.
Tous les endpoints incluent `api/config.php` qui fournit la connexion BDD + headers JSON + CORS.

### api/config.php
**Role** : Configuration partagee par tous les endpoints API.
- Inclut `core/db.php` pour la connexion BDD
- Definit les headers HTTP : `Content-Type: application/json; charset=utf-8`
- Configure les headers CORS (Access-Control-Allow-Origin: *, Methods, Headers, Credentials)
- Gere les requetes preflight OPTIONS (repond 200 et sort)
- Definit `jsonResponse($data, $code)` : helper pour envoyer une reponse JSON avec code HTTP
- Retourne 403 si appele directement (protection contre l'acces direct)

### api/athlete.php
**Role** : Recuperer toutes les donnees d'un athlete.
- **Parametres** : `?id=26134` (id athle.fr) ou `?id_athlete=5` (id interne)
- **Retourne 10 categories de donnees** :
  1. **identite** : nom complet (decompose en nom_1/nom_2/nom_3/nom_4), date de naissance, lieu de naissance, categorie, sexe, nationalite, taille, poids, licence FFA
  2. **clubs** : historique des clubs (nom, annee debut, annee fin)
  3. **medailles** : type (or/argent/bronze/autre), annee, epreuve, competition, lieu
  4. **selections** : competitions selectionnees, dates, lieux, niveaux de competition
  5. **progressions** : performances par annee et par epreuve (brut + details, date, lieu, vent)
  6. **records** : records personnels par epreuve (performance, date, lieu, competition, niveaux)
  7. **podiums** : podiums (place 1/2/3, annee, epreuve, competition, performance)
  8. **resultats** : tous les resultats de competition (performance, date, lieu, vent, salle, tour, place, points)
  9. **niveaux** : niveaux de performance atteints (code N1/R1/D1/IE/IR, points, annee)

### api/search.php
**Role** : Recherche avancee multi-criteres.
- **12 filtres combinables** :
  - `nom` : recherche sur le nom complet
  - `nom1` / `nom2` : nom de famille / prenom separement
  - `club` : affilie a un club
  - `categorie` : categorie (Senior, Junior, Cadet, etc.)
  - `sexe` : M ou F
  - `nationalite` : code nationalite (FRA, MAR, SEN, etc.)
  - `epreuve` : pratique une epreuve (via records)
  - `ville` : a participe dans une ville
  - `competition` : a participe a une competition
  - `medaille` : a obtenu un type de medaille (or, argent, bronze)
  - `annee` : annee de participation
  - `licence` : numero de licence FFA
- **Retourne** : liste filtree avec pagination, nombre de records par athlete
- **Colonnes contextuelles** : `filtre_debut` / `filtre_fin` selon le filtre actif
- **Niveaux** : retourne un tableau `niveaux` par athlete (depuis athlete_niveaux)
- **Top records** : retourne un tableau `top_records` par athlete (top 5 records avec epreuve, performance, niveaux colores)

### api/liste.php
**Role** : Liste paginee de tous les athletes.
- **Parametres** : `page`, `limit` (max 100, defaut 50), `ordre` (nom/date/id/recent/medailles/podiums/selections/records)
- **Retourne** : liste d'athletes avec nombre de records + infos de pagination (total, pages, page courante)

### api/stats.php
**Role** : Statistiques globales de la base.
- **Cache fichier** : resultat sauvegarde en JSON dans `cache/` (TTL 24h). Parametre `?nocache=1` pour forcer le recalcul.
- **Parametre `?top=N`** : limite le nombre de resultats dans les top listes (defaut 50, max 200)
- Mode simple : compte total d'athletes, clubs, epreuves, resultats, records, medailles, selections, villes
- Mode detaille (`?detail=1&top=30`) : ajoute :
  - Top clubs (par nombre d'athletes)
  - Top epreuves (par nombre de records)
  - Top villes (par nombre de resultats)
  - Top athletes
  - Repartition hommes/femmes
  - Repartition par categorie
  - Repartition par nationalite (top 10)
  - Repartition des medailles (or/argent/bronze)

### api/classement.php
**Role** : Classement des athletes par epreuve.
- **Parametres** : `epreuve` (obligatoire), `categorie`, `sexe`, `annee`, `limit`, `offset`
- Retourne les athletes classes par meilleure performance pour une epreuve donnee

### api/clubs.php
**Role** : Liste des clubs.
- **Parametres** : `nom` (recherche), `page`, `limit`
- **Retourne** : nom du club, nombre d'athletes, annees d'activite (debut/fin)
- **Top niveaux** : retourne `top_niveaux` par club (top 3 niveaux avec counts et pourcentages)

### api/club_stats.php
**Role** : Statistiques detaillees d'un club.
- **Parametres** : `id` ou `nom` (obligatoire)
- **Retourne** :
  - Informations du club (id, nom)
  - Nombre total d'athletes
  - Repartition par sexe (hommes/femmes)
  - Repartition par categorie (SE, ES, JU, CA, etc.)
  - Nationalites des athletes (avec compteurs par pays)
  - Nombre de medailles (or, argent, bronze)
  - **Epreuves paginees** (50 par page, parametre `&ep=`) : nom, nb_athletes, nb_records, meilleur record + detenteur + niveaux
  - Records des athletes du club groupes par epreuve
  - Top 10 epreuves, Top 10 athletes
  - Periode d'activite (annee debut, annee fin)
  - Top medaille athletes/competitions/epreuves
  - Top podium epreuves
  - Athletes selectionnes
  - Resultats par annee (evolution sur 10 ans)

### api/epreuves.php
**Role** : Liste des epreuves.
- **Parametres** : `nom` (recherche), `page`, `limit`
- **Retourne** : nom epreuve, nombre d'athletes, nombre de records, plage de dates

### api/epreuve_stats.php
**Role** : Statistiques detaillees d'une epreuve avec classement des performances.
- **Cache fichier** : TTL 24h, cle = `ep_` + md5(nom+page+limit+sexe+categorie)
- **Parametres** : `nom` (obligatoire), `page`, `limit` (defaut 50, max 100), `sexe`, `categorie`
- **Retourne** : totaux, periode, repartitions, records pagines classes, medailles, podiums, top clubs/villes, niveaux, selections, progressions, resultats par annee

### api/epreuve_records.php
**Role** : Top 50 records pour une epreuve.
- **Parametres** : `nom` (nom de l'epreuve, obligatoire)
- **Retourne** : records tries par performance avec athlete, categorie, sexe, nationalite, club, niveaux

### api/villes.php
**Role** : Liste des villes.
- **Parametres** : `nom` (recherche), `page`, `limit`
- **Retourne** : nom ville, nombre d'athletes, plage d'annees

### api/ville_stats.php
**Role** : Statistiques detaillees d'une ville.
- **Parametres** : `nom` (obligatoire)
- **Retourne** : nombre total d'athletes, repartitions (sexe, categorie, nationalites), top epreuves, clubs, athletes, niveaux, medailles (detail + top athletes), podiums (niveaux), records pagines avec niveaux, selections, progressions, resultats par annee (15 ans)

### api/competitions.php
**Role** : Liste des competitions.
- **Parametres** : `nom` (recherche), `page`, `limit`
- **Retourne** : nom competition, nombre d'athletes, plage d'annees

### api/performances.php
**Role** : CRUD des performances saisies manuellement.
- GET : lister les performances d'un athlete
- POST : ajouter une performance (authentification requise)
- PUT : modifier une performance (auteur uniquement)
- DELETE : supprimer une performance (auteur uniquement)
- Champs : epreuve, performance, date, lieu, notes

### api/auth/ (6 endpoints)

**api/auth/google_login.php** — Initie le flux OAuth Google
- Genere un state CSRF aleatoire, le stocke en `$_SESSION`
- Redirige (302) vers Google avec scope `openid email profile`

**api/auth/google_callback.php** — Callback Google OAuth
- Verifie le state CSRF contre `$_SESSION`
- Echange le code contre un access token via `https://oauth2.googleapis.com/token`
- Recupere le profil via `https://www.googleapis.com/oauth2/v2/userinfo`
- Cherche user par `google_id` → login direct, par `email` → lie le google_id, sinon → cree un user (role=athlete)
- Cree session BDD via `createSession()` + redirige vers `index.php`

**api/auth/login.php** — Connexion classique (super admin uniquement)
- Methode POST (JSON body) avec `email` et `password`
- Detecte les identifiants BDD pour le super admin
- Si user OAuth tente un login classique → message explicite
- Retourne un token de session + objet utilisateur

**api/auth/register.php** — Inscription classique (legacy)
- Methode POST avec `email`, `password` (min 8 caracteres), `nom`, `prenom`, `role` (athlete/coach/club)
- Conserve pour compatibilite, mais le frontend utilise Google OAuth

**api/auth/logout.php** — Deconnexion
- Methode POST, supprime la session en BDD + efface le cookie

**api/auth/me.php** — Utilisateur courant
- Methode GET, retourne les infos de l'utilisateur connecte ou 401

---

## DOSSIER : scraping/

Pipeline complet de collecte de donnees depuis athle.fr. Scrape les pages HTML des 300 000+ athletes, extrait les donnees structurees, et les insere en base de donnees MySQL.

### Architecture du pipeline

```
Table nom_et_liens (300k URLs)
    ↓ urls_cache.json (cache local)
    ↓
scraper.php (orchestrateur)
    ↓ Charge athlete_id_externe deja en BDD → skip sans scraper
    ↓ Batch de 7 athletes
    ↓
scrape_functions.php → scrapeParallel()
    ↓ curl_multi : 7 athletes x 3 pages = 21 requetes paralleles
    ↓ CURLOPT_TIMEOUT = 15s, User-Agent Mozilla
    ↓
AthleteScraper.php (Class/)
    ↓ Parsing HTML (regex) → 9 sections de donnees
    ↓ extractIdentite/Medailles/Clubs/Progressions/Records/Podiums/Resultats/Niveaux/Selections
    ↓
insert_athle.php (core/)
    ↓ Cache memoire (loadRefCache) → 0 SELECT repetitifs
    ↓ Batch INSERT (1 query par section, 9 tables enfants)
    ↓
MySQL (athletes + 9 tables FK) + src/{id}.php (JSON)
```

### scraping/scrape_functions.php
**Role** : Fonction `scrapeParallel()` partagee entre les scripts de scraping.

**Fonction** : `scrapeParallel(array $athleteIds, string $baseUrl = "https://athle.fr/athletes/") : array`
- Utilise `curl_multi` pour telecharger plusieurs pages simultanement
- 3 URLs par athlete : `/{id}/bilans`, `/{id}/records`, `/{id}/selections`
- Options curl : `CURLOPT_TIMEOUT = 15`, `CURLOPT_FOLLOWLOCATION = true`, `SSL_VERIFYPEER = false`
- Seules les reponses HTTP 200 sont conservees (sinon null)
- **Retourne** : `[athleteId => ['bilans' => html|null, 'records' => html|null, 'selections' => html|null]]`
- **Utilisee par** : `scraper.php`, `check_sync.php` (Phase 2)

### scraping/scraper.php
**Role** : Script de scraping principal et orchestrateur.
- **URL** : `https://bokonzi.com/scraping/scraper.php`
- **Constantes** : `$TIME_LIMIT = 25` (secondes max par cycle), `$PARALLEL = 7` (athletes en parallele)

**Workflow par cycle** (25s max, puis auto-refresh automatique) :
1. Charge toutes les URLs depuis table `nom_et_liens` → cache local `urls_cache.json`
2. Charge tous les `athlete_id_externe` deja en BDD dans `$existingAthletes[]` (SET en memoire)
3. Affiche progression : `X / Y traites (Z%)` avec barre de progression rouge
4. Boucle batch :
   - Collecte 7 prochains athletes **non-existants** en BDD (skip sans meme scraper)
   - `scrapeParallel()` → telecharge 21 pages en parallele
   - Pour chaque athlete du batch :
     - `AthleteScraper` → extraction des 9 sections
     - Sauvegarde JSON → `src/{id}.php` (avec headers PHP CORS)
     - Insertion BDD → `insertAthleteData($scraper, $conn, $cache)`
     - Ajout a `$existingAthletes` pour eviter doublons dans le meme cycle
   - Echecs → `failed.json` (nom, ID, date, message erreur)
   - `sleep(1)` entre chaque batch (protection athle.fr)
5. Sauvegarde progression → `progress.txt` + `$_SESSION["url"]`
6. Affiche resume : athletes traites, duree, ETA
7. `header("Refresh: 1")` → page se recharge automatiquement

**Bouton reset** :
- Formulaire en haut de page : champ numerique + bouton "Reset" + bouton "Tout reprendre (0)"
- `?reset_to=N` : ecrit N dans `progress.txt`, efface la session, redirige

**Test manuel d'URL** (independant du scraping principal) :
- Champ texte "Tester" a cote des boutons reset
- Accepte un **ID nu** (`123456`) ou une **URL complete** (`https://athle.fr/athletes/123456/bilans`)
- `?test_url=123456` : scrape l'athlete, affiche identite + stats (clubs, records, medailles, etc.) + insere en BDD + sauve JSON
- `?test_url=123456&skip_bdd` : scrape uniquement, sans toucher la BDD (bouton "Test seul")
- `?test_url=123456&force` : force la re-insertion meme si deja en BDD
- Affiche : nom, temps de scrape, tableau des compteurs par section, identite complete
- Fait `exit` avant le code de scraping batch → totalement independant

**Interface visuelle** :
- Fond noir, texte vert (style terminal)
- Couleurs : vert (succes), rouge (erreur), orange (skip), cyan (timing)
- Barre de progression rouge avec pourcentage
- ETA estime en heures/minutes

**Performance** : ~3.5 jours pour 300 000 athletes (vs ~17 jours en sequentiel)

### scraping/check_sync.php
**Role** : Verification et synchronisation en 2 phases.

**Phase 1 — Verification** (execution unique) :
- Parcourt toutes les URLs de `nom_et_liens`
- Verifie si le fichier `src/{athleteId}.php` existe pour chaque URL
- Genere `absents2.json` avec la liste des athletes manquants
- Si aucun absent : affiche "Tous les athletes sont presents"

**Phase 2 — Scraping des absents** (automatique apres Phase 1) :
- Charge la liste depuis `absents2.json`
- Scrape les athletes manquants par batch de 7 (meme logique que `scraper.php`)
- Progression dans `progress_absents.txt`
- Echecs dans `failed_absents.json`
- Auto-refresh jusqu'a completion

**Reset** : `?reset=1` → supprime `absents2.json`, `progress_absents.txt`, `failed_absents.json`

### scraping/check_athletes.php
**Role** : Audit de completude BDD vs fichiers JSON.
- Compare chaque `athlete_id_externe` de la table `athletes` avec l'existence du fichier `src/{id}.php`
- Genere `absents.json` avec la liste detaillee :
  ```json
  {"total_verifies": 250000, "total_presents": 249500, "total_absents": 500,
   "absents": [{"id_athlete": 123, "athlete_id_externe": 456789, "nom_complet": "...", "fichier_attendu": "src/456789.php"}]}
  ```
- Interface avec 4 cartes stats : Total, Present, Absent, Progression %

### scraping/import_bdd.php
**Role** : Import en masse depuis les fichiers JSON existants.
- Parcourt tous les fichiers `src/*.php` dans l'ordre alphabetique
- Pour chaque fichier : extrait le JSON (supprime le wrapper PHP) → `insertAthleteData()`
- Progression dans `import_progress.txt` + `$_SESSION["import"]`
- Auto-refresh chaque seconde jusqu'a completion
- **Quand l'utiliser** : si les fichiers JSON ont ete scrapes offline ou copies separement

### Fichiers de donnees generes par le scraping

| Fichier | Contenu | Genere par |
|---------|---------|------------|
| `urls_cache.json` | Cache de la table `nom_et_liens` (300k URLs) | `scraper.php` (1ere execution) |
| `progress.txt` | Position ID courante du scraping | `scraper.php` |
| `progress_absents.txt` | Position courante de check_sync Phase 2 | `check_sync.php` |
| `import_progress.txt` | Index fichier courant de l'import BDD | `import_bdd.php` |
| `failed.json` | Athletes echoues (ID, erreur, date) | `scraper.php` |
| `failed_absents.json` | Athletes echoues du rattrapage | `check_sync.php` |
| `absents.json` | Fichiers src/ manquants vs BDD | `check_athletes.php` |
| `absents2.json` | URLs manquantes vs fichiers src/ | `check_sync.php` |
| `src/{id}.php` | JSON athlete avec headers PHP (CORS + Content-Type) | `scraper.php`, `check_sync.php` |

### Controle a distance (admin/remote_check.php)
API JSON securisee par cle API (`?bk_key=bk_s3cr3t_2026_xK9mP`).

| Action | Params | Description |
|--------|--------|-------------|
| `scrape_status` | - | Retourne : total_urls, total_bdd, restants, pct, progress_file |
| `test_scrape` | `id`, `skip_bdd`, `force` | Scrape 1 athlete de test, retourne identite + stats + timing |
| `count` | - | Compteurs de toutes les tables |
| `columns` | `table` | Schema d'une table specifique |

---

## DOSSIER : pages/

Pages frontend independantes, chacune dans son propre fichier PHP.

### pages/profil.php
**Role** : Profil public d'un athlete, partageable par URL.
- **URL** : `profil.php?id={id_athlete}` (id interne)
- **SEO complet** : utilise `core/seo.php` — meta description, Open Graph, Twitter Cards, JSON-LD (Person + BreadcrumbList)
- **Donnees SEO** : tente d'abord la lecture depuis `src/{id}.php` (rapide, ~1ms), sinon utilise les donnees API
- Affiche : avatar (initiales), nom, club actuel, categorie, sexe, nationalite
- Medailles avec compteurs visuels (or, argent, bronze)
- Tableaux : records personnels (avec niveaux colores), medailles, dernieres performances, progressions, selections
- Bouton "Copier le lien" pour partager
- **QR code dynamique** en bas de page (genere a la volee via api.qrserver.com)

### pages/global_athlete.php
**Role** : Fiche athlete complete avec recherche avancee.
- **SEO complet** : meme systeme que profil.php (src/ puis API)
- Titre dynamique avec le nom de l'athlete
- Formulaire de recherche : nom1/nom2, annee naissance, categorie, sexe, nationalite, club, ville
- Fiche complete avec toutes les sections (medailles, records, progressions, resultats, podiums, selections, niveaux)
- **QR code dynamique** en bas de la fiche

### pages/recherche_live.php
**Role** : Page de recherche live d'athletes (standalone).
- Champ de recherche avec filtrage en temps reel (min 2 caracteres)
- Boutons filtres : categories, sexes, nationalites
- Affichage des resultats avec details
- Design responsive

### pages/classement.php
**Role** : Classement des athletes par epreuve.
- Selection d'epreuve (dropdown)
- Filtres : categorie, sexe (radio), annee
- Tableau de classement avec pagination
- Tri par meilleure performance

### pages/performances.php
**Role** : Saisie manuelle de performances (page protegee).
- Requiert une authentification (redirige vers login sinon)
- Recherche d'athlete dans le systeme
- Formulaire d'ajout : epreuve, performance, date, lieu, notes
- Liste des performances existantes avec modification/suppression
- Seul l'auteur peut modifier/supprimer ses saisies

### pages/recherche.php
**Role** : Recherche dans les fichiers JSON sources.

### pages/exemples.php
**Role** : Page de documentation et exemples de l'API.
- Exemples d'appels API avec resultats en direct
- Montre la structure des reponses
- Permet de tester les endpoints directement

### pages/test_api.php
**Role** : Interface de test des endpoints API pour le developpement.

### pages/athlete.php
**Role** : Affichage JSON brut des donnees d'un athlete.

---

## DOSSIER : admin/

Scripts d'administration de la base de donnees. A executer manuellement.

### admin/setup_bdd.php
**Role** : Creation de toutes les tables de la BDD (schema initial).
- Cree les 20 tables avec tous les champs et contraintes
- Ajoute les 38 cles etrangeres (ON DELETE CASCADE/SET NULL)
- Pre-remplit les categories FFA (EA, PO, BE, MI, CA, JU, ES, SE, V1, V2, V3, V4)
- Idempotent (peut etre relance sans risque)

### admin/remote_check.php
**Role** : API JSON d'administration a distance (securisee par cle API).
- **Securite** : `?bk_key=bk_s3cr3t_2026_xK9mP` (param URL ou header `X-BK-KEY`)
- `?action=scrape_status` : progression du scraping (total_urls, total_bdd, restants, pct)
- `?action=test_scrape&id=123` : scrape 1 athlete de test (+ `&skip_bdd`, `&force`)
- `?action=count` : compteurs de lignes de toutes les tables
- `?action=columns&table=athletes` : schema detaille d'une table
- `?action=query&q=SELECT...` : requete SQL en lecture seule

**Note** : `admin/drop_all.php` et `admin/reset.php` ont ete **supprimes** pour des raisons de securite.

### admin/cache_urls.php
**Role** : Regeneration du cache URLs.
- Regenere `urls_cache.json` depuis la table `nom_et_liens`

---

## DOSSIER : Class/

Classes utilitaires PHP reutilisables.

### Class/AthleteScraper.php (56 Ko)
**Role** : Scraper web pour athle.fr — parsing HTML → donnees structurees.

**Constructeur** : `new AthleteScraper($input)` — accepte un ID entier ou une URL complete (`/athletes/12345/bilans`)

**Proprietes publiques** (remplies par les methodes extract) :
- `$identite[]` — nom, prenom, date naissance, lieu naissance, taille, poids, categorie, sexe, nationalite, licence
- `$clubs[]` — liste des clubs avec annees debut/fin
- `$medailles[]` — or/argent/bronze avec competition, epreuve, lieu, annee
- `$selections[]` — selections en equipe (type, date, duree, age, competition, epreuve, classement, perf)
- `$progressions[]` — meilleures perfs par saison (epreuve, annee, categorie, club, perf, vent, date, lieu)
- `$records[]` — records personnels (epreuve, categorie, perf, date, club, lieu)
- `$podiums[]` — top 3 (annee, niveau, place, epreuve, perf, vent, date, lieu)
- `$resultats[]` — tous les resultats de competition (annee, date, epreuve, perf, place, niveau D/R/N/I, points, lieu)
- `$niveaux[]` — qualifications (code, points, annee) + perfs requises par epreuve

**Methodes d'extraction** (chacune parse `$this->html`) :
- `extractIdentite()` — regex sur `<h1>`, dates, taille/poids, categorie/sexe/nationalite
- `extractClubs()` — deduit depuis les progressions (agregation min/max annees)
- `extractMedailles()` — pattern "Medaille d'or / 1997 - Ljubljana (SLO) : 4 x 400 m"
- `extractSelections()` — blocs selections equipe
- `extractProgressions()` — section "Meilleures performances par saison" (groupees par epreuve)
- `extractRecords()` — page records (similaire aux progressions)
- `extractPodiums()` — top 3 finishes avec lieux
- `extractResultats()` — tous les resultats competition (avec niveaux D1-IE)
- `extractNiveaux()` — qualifications + perfs requises

**Methodes utilitaires** :
- `toArray() : array` — export toutes les proprietes en tableau associatif (pour JSON)
- `scrapeAll() : array` — tout-en-un : fetch 3 pages + extract tout + retourne resultat

**Methodes statiques critiques** :
- `performanceToInt($perf) : int|null` — conversion texte → centiemes (7 patterns). **ATTENTION** : `str_pad($digit, 2, '0', STR_PAD_RIGHT)` pour les dixiemes (10''9 → 1090, pas 1009)
- `splitNomPrenom($nom) : array` — separation nom/prenom par heuristique majuscules
- `getCategorieCode($anneeNaissance, $anneeSaison) : string` — age → code FFA (EA/PO/BE/MI/CA/JU/ES/SE/V1-V4)

### Class/DatabaseHandler.php (63 Ko)
**Role** : Gestionnaire de base de donnees avance.
- Creation de tables, ajout de FK, SELECT/INSERT/UPDATE/DELETE
- Gestion des transactions
- Requetes preparees

### Autres utilitaires (30+)
- `FrenchClock.php` : formatage de dates en francais
- `EmailValidator.php` : validation d'adresses email
- `CSSValidator.php` : validation de code CSS
- `ImageResizer.php` / `redimensionnerImageLargeurMax.php` : redimensionnement d'images
- `Creat_form.php` : generation dynamique de formulaires HTML
- `AsciiConverter.php` : conversion et nettoyage de caracteres speciaux
- `Language.php` / `LanguageSwitcher.php` : gestion multilingue
- `cleanHTML.php` / `cleanHtmlToPlainText.php` : nettoyage HTML
- `formatDateFr.php` / `formaterDateFr.php` : formatage de dates
- `limiterMots.php` / `extraireAlphabetique.php` : manipulation de texte
- `Data_send_class.php` : envoi de donnees
- `Div_page.php` : pagination
- `IsLocal.php` : detection environnement local/prod
- `Path_config.php` : configuration des chemins
- `SpeechCard.php` / `SpeechController.php` : gestion de synthese vocale

---

## DASHBOARD PRINCIPAL (index.php)

Le coeur de l'application. Fichier unique de ~7300 lignes contenant 8 sections accessibles via le parametre `?page=`.

---

### Section : Accueil (`?page=accueil` ou par defaut)

**C'est quoi ?** La page d'accueil du dashboard. Elle affiche un resume de toutes les statistiques de la base de donnees en temps reel.

**Chargement en 2 phases** pour la rapidite :
- **Phase 1 (PHP synchrone)** : lecture du cache `stats_base.json` → 8 cartes + 2 graphiques (instantane, 0 requete HTTP)
- **Phase 2 (injection directe ou AJAX)** : si le cache `stats_detail_30.json` existe, les donnees sont injectees directement en JavaScript (0 requete HTTP) ; sinon, un appel AJAX est effectue (1er visiteur uniquement)

**Contenu :**
- 8 cartes statistiques : Athletes, Clubs, Epreuves, Resultats, Records, Medailles, Selections, Villes
- **Top Clubs Consultes** : tableau des clubs les plus visites, avec onglets periode (Jour, Semaine, Mois, Annee)
- **Top Athletes Consultes** : tableau des athletes les plus visites, avec onglets periode (Jour, Semaine, Mois, Annee)
- Donnees depuis tables de tracking IP (`club_vues_ip`, `athlete_vues_ip`) filtrees par `created_at`
- API : `top_searched.php?type=clubs|athletes&days=1|7|30|365`
- Pagination 10/page, bouton "Voir tout", auto-refresh 60s
- Fallback si aucune vue : utilise les stats globales (`stats_detail_30.json`)
- 4 graphiques Chart.js :
  - Repartition Hommes/Femmes (Doughnut)
  - Repartition des Medailles or/argent/bronze (Doughnut)
  - Repartition par Categorie (Barre horizontale)
  - Top 10 Epreuves par records (Barre horizontale)
- Tableau Top 10 clubs (noms cliquables → panneau de detail)
- Tableau Top 10 epreuves
- Graphique Top Clubs (Doughnut, filtrable par clubs ignores)
- Panneau de detail club (voir section dediee)

---

### Section : Athletes (`?page=athletes`)

**C'est quoi ?** La liste de tous les athletes de la base de donnees, avec pagination et tri.

- Liste paginee de tous les athletes (50 par page)
- Tri possible : Nom, Plus recent, ID athle.fr
- 3 graphiques : Hommes/Femmes, Categories, Nationalites (Top 8)
- Tableau : #, Nom (cliquable → profil), Date naissance, Categorie, Sexe, Nationalite, Nb records
- Bouton "+" pour ajouter un athlete au panier de comparaison
- Recherche live (filtre instantane par nom)

---

### Section : Recherche (`?page=recherche`)

**C'est quoi ?** Un moteur de recherche puissant avec 12 criteres combinables pour trouver des athletes precis.

**Mode classique (recherche par nom/filtres) :**
- Recherche live par nom (debounced — attend que l'utilisateur arrete de taper)
- Filtres avances : nom, prenom, club, epreuve, ville, categorie, sexe, nationalite, competition, medaille, annee
- 3 graphiques de resultats (sexe, categorie, nationalite)
- 100 resultats par page avec pagination
- Tableau : #, Nom (cliquable), Date naissance, Categorie, Sexe, Nationalite, **Niveaux** (badges colores), **Records** (top 5 records avec epreuve + performance + niveaux colores)
- Epreuves cliquables dans la colonne Records → lien vers la page epreuve
- Colonnes masquees sur mobile (<768px) : Naissance, Sexe, NAT, Records
- Bouton "+" pour panier de comparaison
- Panneau de detail club (voir section dediee)

**Mode epreuve (`?page=recherche&epreuve=NOM`) :**
- Header avec nom de l'epreuve, nombre total d'athletes, nombre de records, periode
- Filtres par categorie (boutons toggle) et par sexe (boutons toggle M/F)
- Classement par performance (du meilleur au moins bon)
- 50 resultats par page
- Tableau : #, Athlete, Performance, Date, Club (cliquable), Cat, Sexe, NAT, Niveaux
- Section Top Clubs avec boutons cliquables

---

### Section : Profil (`?page=profil&id=X`)

**C'est quoi ?** La fiche complete d'un athlete avec toutes ses donnees, graphiques et une biographie ecrite automatiquement.

**Identite :**
- Nom complet, date de naissance, age, lieu de naissance
- Categorie, sexe, nationalite, taille, poids, licence FFA
- Lien "Profil public" → `profil.php?id={id_athlete}`

**Historique des clubs** : tableau des clubs avec annees debut/fin

**Medailles et palmares :**
- Compteurs visuels : or, argent, bronze
- Tableau detaille : type, annee, epreuve, competition, lieu

**Selections en equipe** : tableau avec competition, date, lieu, niveaux de competition (badges colores)

**Progressions par epreuve** : tableau par epreuve et par annee avec performances et niveaux

**Courbe de progression interactive :**
- Selecteur de discipline (dropdown)
- Mode "Toutes les disciplines" : graphique multi-lignes (meilleure perf par annee pour chaque epreuve)
- Mode discipline unique : courbe detaillee avec chaque performance datee, zone remplie, tooltips avec lieu de competition, et tableau de detail
- Auto-detection du sens de l'axe Y : croissant pour les lancers/sauts (distance), decroissant pour les courses (temps)

**Records personnels** : tableau avec epreuve, performance, date, lieu, competition, niveaux (badges colores)

**Podiums** : tableau avec place, annee, epreuve, competition

**Resultats de competitions** : tableau complet avec performance, date, lieu, vent, salle

**Niveaux de performance** : tableau avec niveau (N1/R1/D1/IE/IR), performances associees

**4 graphiques** : progression, medailles (Doughnut), resultats par annee (Bar), records

**Biographie auto-generee :**
- Texte fluide genere automatiquement a partir de toutes les donnees disponibles
- Sections conditionnelles : seules les informations presentes sont mentionnees
- Detection de carriere active/terminee (inactive depuis plus de 2 ans → "ancien(ne) athlete")
- Detection saison unique ("n'a effectue qu'une seule saison en XXXX")
- Nationalite avec adjectif francais genre (40+ pays : francais/francaise, senegalais/senegalaise, etc.)
- Clubs avec duree, records, medailles, niveaux, podiums, selections
- Bouton "Copier le texte"

**QR code dynamique** en bas de la section profil

---

### Section : Clubs (`?page=clubs`)

**C'est quoi ?** La liste de tous les clubs d'athletisme de la base, avec la possibilite de voir le detail de chaque club.

- Liste paginee des clubs avec nombre d'athletes et annees d'activite
- Colonnes : #, Nom (cliquable → detail), Athletes, Debut, Fin, Top niveaux (top 3 badges colores avec pourcentages)
- 2 graphiques filtrables (excluant les clubs ignores) :
  - Top Clubs par nombre d'athletes (Bar)
  - Periode d'activite des clubs (Bar empile)
- Recherche live par nom de club
- Bouton "+" pour panier de comparaison de clubs
- Bouton "interdire" pour ignorer un club (le masque des stats et graphiques)
- Panneau de gestion des clubs ignores (avec bouton "Restaurer")
- Panneau de detail club (voir section dediee)

---

### Section : Epreuves (`?page=epreuves`)

**C'est quoi ?** La liste de toutes les epreuves d'athletisme (100m, 200m, saut en longueur, etc.) avec leurs statistiques.

- Liste de toutes les epreuves avec nombre d'athletes et records
- Colonnes : #, Epreuve (cliquable → detail), Athletes avec record, Nb records
- 2 graphiques : Top 10 epreuves (Doughnut), Nombre de records par epreuve (Bar)
- Recherche live par nom d'epreuve
- **Panneau de detail epreuve** :
  - S'ouvre au clic sur un nom d'epreuve
  - Appel API vers `api/epreuve_records.php`
  - Tableau des records (top 50) : #, Athlete (cliquable), Categorie, Sexe, Nationalite, Performance, Date, Club, Niveaux (badges colores)
  - Bouton "Comparer" sur chaque ligne
  - **QR code dynamique** dans le panneau

---

### Section : Villes (`?page=villes`)

**C'est quoi ?** Toutes les villes ou se sont deroulees des competitions d'athletisme, avec une fiche analytique complete pour chaque ville.

**Mode liste :**
- Liste paginee de toutes les villes (50 par page)
- Recherche live par nom de ville
- Colonnes : #, Ville (cliquable → detail), Athletes, Periode, Top 3 niveaux, Top 10 chart

**Mode detail (`?page=villes&open=NomVille`) :**
Fiche analytique complete d'une ville avec toutes ses donnees, graphiques et resume auto-genere.

**Resume auto-genere** (15 paragraphes conditionnels) :
1. Presentation de la ville
2. Filtres actifs
3. Repartition par sexe
4. Categories d'athletes
5. Nationalites representees
6. Niveaux de competition
7. Epreuves pratiquees
8. Clubs presents
9. Athletes principaux
10. Medailles obtenues
11. Podiums
12. Records personnels
13. Selections
14. Progressions
15. Evolution par annee / annees d'activite
- Bouton "Copier le texte"

**Filtres interactifs (chainables via URL : `&niv=&nat=&ans=`) :**
- Niveaux : boutons D1-D8 / R1-R6 / N1-N4 / IE / IR avec courbe chart
- Nationalites : boutons selectionnables
- Annees : boutons selectionnables

**Contenu de la fiche :**
- 4 graphiques : sexe (Doughnut), categorie (Bar), nationalites (Bar), top epreuves (Bar)
- Niveaux de competition (Doughnut)
- Athletes (tableau pagine, filtrable par niveaux)
- Epreuves, Clubs (tableaux pagines avec top niveaux)
- Medailles (cartes or/argent/bronze avec pourcentages + top 15 medaillees)
- Podiums (cartes 1er/2e/3e + niveaux)
- Records personnels (tableau avec niveaux colores)
- Evolution par annee (graphique en ligne)
- **QR code dynamique** dans la fiche ville

---

### Section : Comparer (`?page=comparer`)

**C'est quoi ?** Un outil pour comparer plusieurs athletes entre eux, ou plusieurs clubs entre eux, avec des graphiques et un resume ecrit automatiquement.

**Comparaison d'athletes :**
- Ajout/suppression d'athletes via le panier flottant
- Selection d'epreuve commune pour la comparaison
- Graphique de progression comparee (Line)
- Radar de comparaison multi-epreuves
- Comparaison des medailles (Bar empile)
- **Resume textuel auto-genere** :
  - Introduction avec noms et categories
  - Comparaison d'experience (debut de carriere, duree)
  - Detection saison unique
  - Polyvalence (nombre de disciplines)
  - Duel sur l'epreuve selectionnee
  - Analyse de toutes les epreuves communes
  - Comparaison des medailles detaillee
  - Podiums, selections, volume de competitions
  - Conclusion avec verdict global (comptage des avantages)
  - Gestion de 3+ athletes
  - Bouton "Copier le texte"

**Comparaison de clubs :**
- Ajout/suppression de clubs
- Total athletes par club
- Repartition Hommes/Femmes, par categorie
- Comparaison des medailles, specialisation par epreuve
- Radar des metriques

---

### Panneau de detail club (composant reutilisable)

**C'est quoi ?** Un panneau qui s'ouvre quand on clique sur un nom de club, affichant toutes les statistiques de ce club. Present sur 3 pages : Accueil, Clubs, Recherche.

**4 onglets :**

1. **Epreuves** :
   - Tableau pagine des epreuves du club (50 par page)
   - Colonnes : #, Epreuve, Athletes, Records, Meilleur record, Detenteur (cliquable), Niveaux (badges colores)
   - Pagination avec controles de navigation

2. **Nationalites** :
   - Doughnut chart (top 10)
   - Horizontal bar chart (top 15)
   - Boutons de nationalite cliquables avec compteurs et pourcentages
   - Tableau detaille avec barre de pourcentage

3. **Records** :
   - Selecteur de discipline (dropdown)
   - Vue "Toutes les disciplines" : records groupes par epreuve avec sous-tableaux
   - Vue discipline unique : tableau filtre
   - Colonnes : #, Athlete (cliquable), Categorie, Sexe, Performance, Date, Niveaux
   - Bouton "Comparer" sur chaque athlete

4. **Resume** :
   - Texte auto-genere decrivant le club
   - Introduction, effectifs, categories, nationalites, disciplines, records, medailles, top athletes
   - Detection d'inactivite, detection saison unique
   - Bouton "Copier le texte"

**QR code dynamique** a la fin du panneau

---

### Systeme de coloration des niveaux (transversal)

**C'est quoi ?** Un systeme de badges colores applique partout dans l'application pour identifier visuellement le niveau de competition d'un athlete.

**Code couleur :**

| Prefixe | Niveaux | Signification | Couleur | Code hex |
|---------|---------|---------------|---------|----------|
| D | D1-D8 | Departemental | Orange | #f97316 |
| R | R1-R6 | Regional | Cyan | #0891b2 |
| N | N1-N4 | National | Rose | #e11d48 |
| I | IE, IR | International | Fuchsia | #c026d3 |

**Helpers JavaScript :**
- `_nivBadge(code)` : genere un badge HTML colore pour un code niveau
- `_nivBadges(arr)` : genere une serie de badges a partir d'un tableau

**Present dans :** records, progressions, selections, epreuve detail, recherche, club epreuves/records, ville athletes/epreuves/clubs/records, clubs listing

---

### Fonctionnalites JavaScript transversales

**Panier de comparaison** (persistant dans localStorage) :
- `bk_cmp_athletes` et `bk_cmp_clubs` : stockent les athletes/clubs selectionnes
- Fonctions : `getBasketAthletes()`, `addAthleteToBasket()`, `removeAthleteFromBasket()`, `toggleAthleteBasket()`, `clearBasket()`, `updateBasketBadge()`, `updateAllCmpButtons()`
- Meme fonctions pour les clubs

**Clubs ignores** (persistant dans localStorage) :
- `bk_ignored_clubs` : stocke les clubs masques
- Fonctions : `getIgnoredClubs()`, `addIgnoredClub()`, `removeIgnoredClub()`, `toggleIgnoreClub()`, `applyIgnoredClubs()`, `renderIgnoredPanel()`, `rebuildClubCharts()`

**Recherche live** (5 instances) :
- `liveSearch(inputId, statusId, resultsId, paginatedId, config)` : moteur generique
- Requetes AJAX debounced, mise en surbrillance, pagination dynamique
- Instances : Athletes, Recherche, Clubs, Epreuves, Villes

**QR codes dynamiques :**
- `bkQR(url)` : fonction globale qui genere le HTML d'un QR code via api.qrserver.com

---

## SEO ET REFERENCEMENT

Le systeme SEO de Bokonzi est concu pour que chaque page soit parfaitement comprise par Google et les reseaux sociaux.

### core/seo.php — Balises SEO des pages athletes
- **Meta description** : resume automatique de l'athlete (nom, club, categorie, records, medailles)
- **Canonical URL** : URL unique de reference pour eviter le contenu duplique
- **Open Graph** (Facebook, LinkedIn) : titre, description, URL, type profil, nom/prenom
- **Twitter Cards** : titre, description, site @bokonzi
- **Schema.org JSON-LD** : donnees structurees `Person` avec nom, nationalite (35+ pays), naissance, taille, poids, club, medailles, lien athle.fr
- **Fil d'Ariane** (BreadcrumbList) : Accueil > Athletes > Nom de l'athlete
- **Priorite** : lecture depuis `src/{id}.php` (rapide, ~1ms) avec fallback API

### Titres dynamiques (index.php)
Les titres de pages changent en fonction du contenu affiche :
- `?page=clubs&open=NomClub` → "NomClub — Detail Club — Bokonzi"
- `?page=epreuves&nom=100m` → "100m — Epreuve — Bokonzi"
- `?page=villes&nom=Paris` → "Paris — Ville — Bokonzi"
- `?page=recherche` → "Recherche Athletes — Bokonzi"
- `?page=profil&id=X` → "Profil Athlete — Bokonzi"
- Page d'accueil → "Bokonzi — Plateforme d'athletisme francais"

### sitemap.php — Sitemap XML dynamique
- **Sitemap index** : `sitemap.php` genere un index qui pointe vers des sous-sitemaps pagines
- **Page 0** : pages principales (accueil, recherche, athletes, classement)
- **Pages 1+** : athletes pagines par 500 (generes depuis la BDD)
- ~660 sous-sitemaps pour les 330 000+ athletes
- Format XML standard compatible Google Search Console

### robots.txt — Directives pour les robots
- **Autorise** : `/index.php`, `/pages/`, `/login.php`, `/register.php`
- **Bloque** : `/admin/`, `/scraping/`, `/api/`, `/core/`, `/cache/`, `/logs/`, `/archive/`, `/Class/`, `/src/`, `/panel.php`
- Pointe vers le sitemap : `Sitemap: https://bokonzi.com/sitemap.php`

### Google Search Console
- Verification du domaine par DNS TXT
- Fichier de verification : `google3c52de7c1227f892.html`
- Sitemap soumis et indexe

---

## QR CODES DYNAMIQUES

**C'est quoi ?** Un QR code est un code-barres 2D que l'on peut scanner avec un telephone pour ouvrir directement la page web. Bokonzi genere des QR codes pour chaque element affiche.

**Comment ca marche ?**
- Les QR codes ne sont PAS stockes sur le serveur — ils sont generes a la volee par un service externe (api.qrserver.com)
- Une simple balise `<img>` avec l'URL de la page en parametre suffit
- Le QR code contient l'URL complete de la page (ex: `https://bokonzi.com/pages/profil.php?id=123`)
- Quand on scanne le QR code avec un telephone, le navigateur ouvre automatiquement la page

**Ou sont les QR codes ?**
- **Profil athlete** (`pages/profil.php`) : en bas du profil
- **Fiche athlete** (`pages/global_athlete.php`) : en bas de la fiche
- **Detail club** (`index.php`) : dans le panneau de detail club
- **Detail epreuve** (`index.php`) : dans le panneau de detail epreuve
- **Detail ville** (`index.php`) : dans la fiche ville
- **Profil dans le dashboard** (`index.php`) : en bas de la section profil

**Fonction JavaScript :**
```javascript
function bkQR(url) {
    return '<div class="qr-share"><img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' +
           encodeURIComponent(url) + '" alt="QR Code" width="120" height="120">' +
           '<div class="qr-label">Scannez pour partager</div></div>';
}
```

**Style :** fond sombre, QR code avec bordure arrondie et fond blanc, texte "Scannez pour partager" en gris

---

## SYSTEME DE CACHE

### Principe
Les resultats des API lourdes sont sauvegardes en fichiers JSON dans le dossier `cache/`. Les appels suivants lisent directement le fichier JSON au lieu de requeter la base de donnees. Duree de validite : 24 heures.

### Fichiers de cache

| Fichier | API source | Contenu |
|---------|------------|---------|
| `cache/stats_base.json` | `stats.php` (mode simple) | Compteurs globaux |
| `cache/stats_detail_30.json` | `stats.php?detail=1&top=30` | Top listes + repartitions |
| `cache/ep_MD5HASH.json` | `epreuve_stats.php?nom=X&...` | Stats d'une epreuve |
| `cache/athlete_MD5.json` | `athlete.php?id=X` | Donnees d'un athlete |
| `cache/search_MD5.json` | `search.php?...` | Resultats de recherche |
| `cache/clubs_MD5.json` | `clubs.php?...` | Liste de clubs |
| `cache/clubstats_MD5.json` | `club_stats.php?...` | Stats d'un club |
| `cache/villestats_MD5.json` | `ville_stats.php?...` | Stats d'une ville |

### Protection
- Dossier `cache/` protege par `.htaccess` (`Deny from all`)
- Fichiers non accessibles depuis le web

### Lecture locale dans `apiCall()`
La fonction `apiCall()` de `index.php` verifie l'existence du cache local AVANT de faire un appel HTTP :
1. Parse l'URL pour identifier l'API et les parametres
2. Calcule le chemin du fichier cache correspondant
3. Si le cache existe et est valide → lit le JSON localement (0 HTTP, 0 MySQL)
4. Sinon → fallback HTTP classique

### Injection directe (page Accueil)
Pour la page d'accueil, les donnees detaillees sont injectees directement en JavaScript via PHP si le cache existe → 0 appel AJAX.

### Parametres
- `?nocache=1` sur `stats.php` : force le recalcul (ignore le cache)
- TTL configurable (actuellement 86400 secondes = 24h)

---

## STOCKAGE DES DONNEES

### Tables MySQL (20 tables)

| Table | Role |
|-------|------|
| `athletes` | Donnees d'identite (nom, naissance, categorie, sexe, nationalite, taille, poids, licence) — 300 000+ lignes |
| `clubs` | Liste des clubs d'athletisme |
| `athlete_clubs` | Relation athlete-club (annee debut, annee fin) |
| `epreuves` | Liste des epreuves (100m, 200m, saut, lancer, etc.) |
| `competitions` | Liste des competitions |
| `villes` | Liste des villes |
| `categories` | Categories FFA (EA, PO, BE, MI, CA, JU, ES, SE, V1, V2, V3, V4) |
| `nationalites` | Codes nationalite ISO + nom |
| `athlete_records` | Records personnels (athlete + epreuve + performance + date) |
| `athlete_resultats` | Resultats de competition (performance, date, lieu, vent, tour, place, points, salle) |
| `athlete_medailles` | Medailles (type or/argent/bronze, annee, epreuve, competition) |
| `athlete_selections` | Selections en equipe nationale/regionale |
| `athlete_progressions` | Progressions par annee et epreuve (performance, date, lieu, vent) |
| `athlete_podiums` | Podiums (place 1/2/3, annee, epreuve, performance) |
| `athlete_niveaux` | Niveaux de performance atteints (N1, R1, D1, IE, etc.) |
| `athlete_niv_perfs` | Performances associees aux niveaux |
| `athlete_perfs_manuelles` | Performances saisies manuellement par les utilisateurs |
| `users` | Comptes utilisateurs (email, password hash, nom, prenom, role) |
| `user_sessions` | Sessions actives (token, expiration 30 jours) |
| `coach_athletes` | Liaison many-to-many coach ↔ athlete |

### Fichiers JSON (src/)
- 300 000+ fichiers, un par athlete (ex: `src/2688957.php`)
- Contiennent les donnees brutes scrapees au format JSON
- Utilises par le SEO pour une lecture rapide (~1ms) sans appel API ni BDD

### localStorage (navigateur)

| Cle | Role |
|-----|------|
| `bk_cmp_athletes` | Panier de comparaison athletes (JSON: [{id, name}]) |
| `bk_cmp_clubs` | Panier de comparaison clubs (JSON: [{id, name}]) |
| `bk_ignored_clubs` | Clubs ignores/masques (JSON: [{id, name}]) |

### Cookies

| Nom | Role |
|-----|------|
| `bk_token` | Token de session (HTTPOnly, SameSite=Lax, expire 30 jours) |

---

## SECURITE

- **Mots de passe** haches avec BCRYPT (password_hash / password_verify)
- **Sessions** par token aleatoire de 64 caracteres hex (32 octets aleatoires) + expiration 30 jours
- **Cookies** HTTPOnly (inaccessibles en JavaScript) + SameSite=Lax (protection CSRF)
- **Requetes preparees** (parametres lies) contre les injections SQL dans toute l'API
- **Headers CORS** configures (Access-Control-Allow-Origin, Methods, Headers, Credentials)
- **Protection fichiers** : `.htaccess` Deny from all dans /cache/ et /logs/
- **Controle d'acces** par role (athlete, coach, club, admin)
- **Validation des entrees** (format email, longueur mot de passe min 8 car.)
- **Echappement HTML** en sortie (`htmlspecialchars` en PHP, `escapeHtml()` en JS) contre XSS
- **Acces direct** bloque : `api/config.php` retourne 403 si appele directement
- **robots.txt** bloque l'acces aux dossiers sensibles (admin/, api/, core/, scraping/, src/, cache/, logs/)

---

## TECHNOLOGIES

| Composant | Technologie |
|-----------|-------------|
| Backend | PHP 8+ (vanilla, pas de framework) |
| Base de donnees | MySQL (via mysqli) — Hostinger |
| Frontend | HTML/CSS/JavaScript vanilla (pas de framework JS) |
| Graphiques | Chart.js 4.4.7 |
| Scraping | cURL + curl_multi (scraping parallele, 7 athletes x 3 pages) |
| Auth | Sessions par token + cookies HttpOnly (bcrypt) |
| SEO | Schema.org JSON-LD, Open Graph, Twitter Cards, Sitemap XML |
| QR Codes | api.qrserver.com (generation a la volee, pas de stockage) |
| Stockage client | localStorage (panier, clubs ignores) |
| Cache | Fichiers JSON (TTL 24h, protege .htaccess) |
| Serveur local | Apache (XAMPP) |
| Hebergement | Hostinger |
| Deploiement | XAMPP local → push vers Hostinger |

---

## RESUME DES FONCTIONNALITES CLES

### 1. Scraping et import de donnees
- Scraping automatise de athle.fr (scraping parallele via curl_multi, 7 athletes x 3 pages)
- Import en masse en BDD MySQL (cache memoire des tables de reference)
- 300 000+ athletes collectes
- Fichiers JSON individuels dans src/

### 2. Dashboard analytique
- 8 cartes de statistiques globales
- 12+ graphiques Chart.js (Doughnut, Bar, Line, Radar)
- Graphiques filtrables (clubs ignores exclus)
- Theme sombre professionnel avec accents cyan (#00d4ff)

### 3. Navigation et recherche
- 5 barres de recherche live (Athletes, Recherche, Clubs, Epreuves, Villes)
- Recherche debounced avec mise en surbrillance
- 12 filtres avances combinables
- Pagination dynamique
- Recherche par epreuve avec classement

### 4. Fiches detaillees
- Profil athlete complet avec 9 categories de donnees
- Panneau de detail club avec 4 onglets (Epreuves, Nationalites, Records, Resume)
- Panneau de detail epreuve avec records classes
- Fiche detail ville avec 15 paragraphes auto-generes, filtres chainables, medailles, podiums, records, evolution

### 5. Graphiques interactifs
- Courbe de progression par discipline avec selecteur dynamique
- Auto-detection distance vs temps pour l'axe Y
- Mode multi-disciplines et mode discipline unique
- Destruction/recreation dynamique des charts

### 6. Textes auto-generes (4 types)
- **Biographie athlete** : texte fluide avec detection de carriere (active/terminee/saison unique), nationalite genree, niveaux
- **Resume club** : effectifs, nationalites, disciplines, records, medailles, detection d'inactivite
- **Resume ville** : 15 paragraphes conditionnels (presentation, filtres, sexe, categories, nationalites, niveaux, epreuves, clubs, athletes, medailles, podiums, records, selections, progressions, evolution)
- **Comparaison d'athletes** : analyse comparative avec verdict final
- Tous les textes sont conditionnels + bouton "Copier le texte"

### 7. Comparaison avancee
- Panier de comparaison persistant (localStorage)
- Comparaison multi-athletes : progression, radar, medailles, resume textuel
- Comparaison multi-clubs : effectifs, categories, medailles, specialisation
- Badge flottant avec compteur

### 8. Gestion des clubs
- Clubs ignorables (masques des stats et graphiques)
- Panneau de gestion des clubs ignores avec restauration
- Reconstruction dynamique des graphiques

### 9. SEO et referencement
- Schema.org JSON-LD (Person + BreadcrumbList) sur toutes les pages athletes
- Open Graph + Twitter Cards pour le partage social
- Titres dynamiques par section/element
- Sitemap XML dynamique (index + 660+ sous-sitemaps)
- robots.txt configuré
- Google Search Console active
- Lecture SEO depuis src/ (~1ms) avec fallback API

### 10. QR codes dynamiques
- QR code genere a la volee (api.qrserver.com) sur 6 emplacements
- Pas de stockage local — l'image est generee par URL
- Scannez pour ouvrir directement la page

### 11. Authentification et gestion utilisateur
- **Connexion uniquement via Google OAuth** (bouton Google sur login.php et register.php)
- Flux : Google login → callback → creation/liaison automatique du compte → session
- Auto-register : si email Google inconnu, cree un compte (role=athlete)
- Merge : si email existe deja (compte classique), lie le google_id sans creer de doublon
- Sessions par token + cookies HTTPOnly (30 jours)
- Controle d'acces par role (athlete, coach, club, admin)
- Extensible : pret pour Facebook, Instagram (constantes commentees dans oauth_config.php)
- Saisie manuelle de performances (CRUD, auteur uniquement)

### 12. Partage et export
- Profil public partageable par URL (`profil.php?id={id_athlete}`)
- Meta tags Open Graph pour les reseaux sociaux
- Bouton "Copier le lien" sur les profils publics
- Bouton "Copier le texte" sur toutes les biographies et resumes

### 13. Niveaux de competition (systeme de coloration)
- Badges colores dans toute l'application
- 4 familles : D departemental (orange), R regional (cyan), N national (rose), I international (fuchsia)
- Helpers JS : `_nivBadge(code)` et `_nivBadges(arr)`
- Present dans 15+ contextes differents

### 14. Responsive mobile
- Classe CSS `hide-mobile` avec `@media (max-width: 768px)`
- Colonnes non essentielles masquees sur petits ecrans
- Design adaptatif pour smartphones et tablettes

### 15. Cache et performance
- Cache fichier JSON (TTL 24h) pour toutes les API lourdes
- Lecture locale dans `apiCall()` (0 HTTP, 0 MySQL si cache valide)
- Injection directe en JS sur la page Accueil (0 AJAX si cache existe)
- Chargement en 2 phases : Phase 1 sync (cartes) + Phase 2 async (tableaux)
- Cache memoire des tables de reference lors de l'insertion en masse

### 16. Administration
- Panel de controle (`panel.php`) avec stats live BDD
- Scripts de setup, reset, drop de la BDD
- Verification et synchronisation des donnees
- Import en masse depuis les fichiers JSON

---

*Documentation generee le 22/02/2026 — Bokonzi v2.0*
