# Documentation — Système de Scraping BOKONZI

## Vue d'ensemble

Le scraping consiste a collecter les donnees de **~300 000 athletes** depuis le site officiel **athle.fr**, les sauvegarder en local (fichiers JSON) et les inserer dans la base de donnees MySQL.

Tout le pipeline est concu pour etre **autonome** (auto-refresh navigateur), **resumable** (peut s'arreter et reprendre) et **parallele** (7 athletes scrapes en meme temps via `curl_multi`).

---

## Architecture du flux

```
                  ┌────────────────────┐
                  │  athle.fr (HTML)   │
                  └─────────┬──────────┘
                            │  curl_multi (7 athletes × 3 pages = 21 requetes)
                            ▼
                  ┌────────────────────┐
                  │ scrape_functions   │  → telecharge HTML brut
                  │ scrapeParallel()   │
                  └─────────┬──────────┘
                            ▼
                  ┌────────────────────┐
                  │ AthleteScraper     │  → parse HTML, extrait les donnees
                  │ (Class/)           │
                  └─────────┬──────────┘
                            ▼
              ┌─────────────┴─────────────┐
              ▼                           ▼
    ┌──────────────────┐         ┌──────────────────┐
    │ src/{id}.php     │         │  insertAthleteData │
    │ (JSON local)     │         │  (9 tables MySQL)  │
    └──────────────────┘         └──────────────────┘
```

---

## ETAPE 1 — SCRAPING PRINCIPAL

### Script : `scraping/scraper.php`

**But** : parcourir la table `nom_et_liens` (qui contient les URLs de tous les athletes), aller scraper chaque page sur athle.fr, et remplir a la fois `src/` (JSON) et la BDD.

### Fonctionnement detaille

1. **Demarrage** : l'utilisateur clique sur le bouton **DEMARRER** dans l'interface
2. Cela cree un fichier flag `scraping_running.flag` a la racine du projet
3. Le script charge en memoire :
   - Toutes les URLs depuis `nom_et_liens` (cache `urls_cache.json` pour eviter de re-requeter la BDD)
   - Tous les `athlete_id_externe` deja presents dans `athletes` (table) → liste `$existingAthletes`
4. **Boucle par batch de 7** :
   - Selectionne les 7 prochains athletes **non-existants** (skip ceux deja en BDD)
   - Appelle `scrapeParallel()` → telecharge 21 pages en parallele (7 athletes × 3 pages : `bilans`, `records`, `selections`)
   - Pour chaque athlete :
     - Cree un objet `AthleteScraper`
     - Injecte le HTML deja telecharge dans `$scraper->html`
     - Appelle les methodes `extract*()` (Identite, Medailles, Progressions, Clubs, Podiums, Resultats, Niveaux, Records, Selections)
     - Genere le JSON → `src/{athlete_id_externe}.php`
     - Appelle `insertAthleteData($scraper, $conn, $cache)` → insertion BDD
5. **Sauvegarde** : `progress.txt` est mis a jour avec la position courante apres chaque batch
6. **Auto-refresh** : `header("Refresh: 1")` recharge la page toutes les ~25 secondes (limite `$TIME_LIMIT`)
7. **Arret** : si l'utilisateur clique **ARRETER**, le flag est supprime et le script s'arrete proprement au prochain cycle

### Constantes (en haut du fichier)

| Constante | Valeur | Role |
|-----------|--------|------|
| `$TIME_LIMIT` | 25 secondes | Temps max par cycle avant refresh |
| `$PARALLEL` | 7 | Nombre d'athletes scrapes en parallele |

### Fichiers generes

| Fichier | Contenu |
|---------|---------|
| `src/{id}.php` | JSON de chaque athlete (avec headers PHP de protection) |
| `progress.txt` | Position courante (ID `nom_et_liens`) |
| `urls_cache.json` | Cache de la table `nom_et_liens` |
| `failed.json` | Liste des athletes qui ont echoue (timeout, page vide, erreur) |
| `scraping_running.flag` | Flag de controle start/stop (a la racine, pas dans `scraping/`) |

### Modes speciaux

#### Mode "Test manuel" — `?test_url=ID`

Permet de scraper **1 seul athlete** sans toucher au batch principal.

```
https://bokonzi.com/scraping/scraper.php?test_url=2688957
```

Options :
- `&skip_bdd` : scrape mais n'insere PAS en BDD (utile pour debug)
- `&force` : re-insere meme si l'athlete existe deja en BDD

Affiche : nom, temps de scraping, stats (clubs, records, medailles...), identite complete.

#### Mode "Reset progression" — `?reset_to=N`

Reinitialise le compteur a une position donnee.

```
https://bokonzi.com/scraping/scraper.php?reset_to=5000
```

Utile pour reprendre depuis le debut (`?reset_to=0`) ou apres un certain ID.

### Ce qu'il NE FAIT PAS

**`scraper.php` ne met PAS a jour les athletes deja en BDD.** Il les skip systematiquement (ligne 317).

Pour rafraichir un athlete existant, il faut soit :
- Le supprimer de la BDD avant
- Utiliser `?test_url=ID&force` (1 athlete a la fois)

---

## ETAPE 2 — VERIFICATION + RATTRAPAGE

### Script : `scraping/check_sync.php`

**But** : verifier qu'il n'y a pas de "trous" dans `src/`, puis scraper automatiquement les manquants.

### Fonctionnement detaille (2 phases)

#### Phase 1 — Verification

1. Lit toutes les URLs de `nom_et_liens` (par batch de 5000)
2. Pour chaque URL, extrait l'ID athlete (regex `#/athletes/(\d+)/#`)
3. Verifie si `src/{id}.php` existe
4. Si absent → ajoute a la liste `$absents`
5. Sauvegarde le resultat → `absents2.json`
6. Log live dans le navigateur (couleurs, compteurs, barre de progression)
7. **Refresh automatique** vers Phase 2 quand termine

#### Phase 2 — Scraping des absents

1. Lit `absents2.json`
2. Boucle par batch de 7 (meme logique que `scraper.php`)
3. Skip les fichiers qui sont apparus entre-temps (re-check `file_exists()`)
4. Pour chaque batch :
   - `scrapeParallel()` → telecharge
   - Parse avec `AthleteScraper`
   - Genere `src/{id}.php` + insertion BDD
5. Sauvegarde la progression dans `progress_absents.txt`
6. Auto-refresh tant qu'il reste des absents
7. Termine quand `$idx >= $nbAbsents`

### Fichiers generes

| Fichier | Contenu |
|---------|---------|
| `absents2.json` | Liste complete des manquants (avec metadonnees) |
| `progress_absents.txt` | Position courante phase 2 |
| `failed_absents.json` | Echecs lors du rattrapage |

### Quand l'utiliser

- Apres une session `scraper.php` qui a connu des erreurs
- Pour s'assurer que **tous** les athletes de `nom_et_liens` ont bien leur fichier `src/`
- Apres une coupure reseau ou un crash athle.fr

### Reset

`?reset=1` supprime `absents2.json`, `progress_absents.txt` et `failed_absents.json` → relance Phase 1.

---

## ETAPE 3 — IMPORT BDD (REINJECTION)

### Script : `scraping/import_bdd.php`

**But** : relire tous les fichiers `src/*.php` et les inserer en BDD un par un.

### Fonctionnement detaille

1. `glob()` sur `src/*.php` → liste triee
2. Connexion BDD + `loadRefCache()` (charge en memoire les villes, clubs, epreuves, competitions, categories, nationalites pour eviter les SELECT repetitifs)
3. Boucle (1 fichier par cycle de page, auto-refresh) :
   - Lit le contenu (separe les headers PHP du JSON)
   - `json_decode()`
   - Cree un objet `stdClass` avec les memes proprietes que `AthleteScraper`
   - Appelle `insertAthleteData()` → 9 tables MySQL
   - Incremente `import_progress.txt`
4. Termine quand tous les fichiers sont traites

### Fichiers utilises

| Fichier | Contenu |
|---------|---------|
| `import_progress.txt` | Index courant (numero de fichier dans la liste triee) |

### Quand l'utiliser

**`import_bdd.php` est UTILE seulement dans 3 cas** :

1. **Apres un crash BDD** — la table `athletes` a ete corrompue ou supprimee, mais on a encore les fichiers `src/`
2. **Apres un changement de schema** — modification d'`insertAthleteData()` pour ajouter une nouvelle table → on relance l'import
3. **Migration BDD** — passer d'un serveur a un autre

**En fonctionnement normal, on ne lance JAMAIS `import_bdd.php`** car `scraper.php` fait deja JSON + BDD en meme temps.

### Reset

Supprimer `import_progress.txt` pour recommencer depuis le debut.

---

## Tableau recapitulatif

| # | Script | Entree | Sortie | Quand l'utiliser |
|---|--------|--------|--------|------------------|
| 1 | `scraper.php` | Table `nom_et_liens` | `src/*.php` + BDD (9 tables) | Scraping normal — toujours utilise |
| 2 | `check_sync.php` | `nom_et_liens` vs `src/` | `absents2.json` + scraping cible | Apres erreurs, pour rattraper les manquants |
| 3 | `import_bdd.php` | `src/*.php` | BDD (9 tables) | Apres crash BDD ou migration |

### Audit additionnel : `check_athletes.php`

**Bonus** : ce script ne fait que verifier la coherence entre la BDD et `src/`, sans rien scraper. Il genere `absents.json` (liste des athletes en BDD mais sans fichier `src/` correspondant). Utile pour detecter une incoherence.

---

## Composants partages (utilises par les 3 etapes)

### `scraping/scrape_functions.php`

Fonction `scrapeParallel($athleteIds, $baseUrl)` :
- Initialise un `curl_multi`
- Cree 3 handles par athlete (bilans, records, selections)
- Lance toutes les requetes en parallele
- Recupere le HTML, ferme proprement les handles
- Retourne `[athleteId => ['bilans' => html|null, 'records' => html|null, 'selections' => html|null]]`

### `Class/AthleteScraper.php`

Classe principale d'extraction. Methodes cles :

| Methode | Role |
|---------|------|
| `extractIdentite()` | Nom, prenom, sexe, categorie, nationalite, club principal, ID licence |
| `extractClubs()` | Liste des clubs avec dates de membership |
| `extractMedailles()` | Or, argent, bronze par competition |
| `extractSelections()` | Selections en equipe nationale |
| `extractProgressions()` | Evolution annuelle des perfs |
| `extractRecords()` | Records personnels par epreuve |
| `extractPodiums()` | Top 3 par competition |
| `extractResultats()` | Tous les resultats avec niveau D/R/N/I |
| `extractNiveaux()` | Qualifications departementales/regionales/nationales |
| `toArray()` | Export complet en tableau associatif |
| `scrapeAll()` | Tout-en-un (fetch + extract) — utilise par le mode test |

Methodes statiques utilitaires :
- `performanceToInt($perf)` — convertit un texte de performance en entier (centiemes ou centimetres)
- `splitNomPrenom($nom)` — separe nom et prenom
- `getCategorieCode($anneeNaissance, $anneeSaison)` — calcule la categorie FFA selon l'age

### `core/insert_athle.php`

Trois fonctions cles :

| Fonction | Role |
|----------|------|
| `loadRefCache($conn)` | Charge en memoire les 6 tables de reference (villes, clubs, epreuves, competitions, categories, nationalites) → 0 query repetitive |
| `cachedGetOrInsertId(&$cache, $conn, ...)` | Lookup en cache → si absent, INSERT IGNORE puis SELECT |
| `insertAthleteData($scraper, $conn, &$cache)` | Insertion complete des 9 sections (athlete + clubs + medailles + ...) |

**Strategie UPDATE** : si l'`athlete_id_externe` existe deja, le code fait un DELETE CASCADE sur les enfants puis re-INSERT tout. C'est plus simple qu'un diff intelligent.

---

## Tables BDD remplies (9 tables)

| Table | Contenu |
|-------|---------|
| `athletes` | Identite + cle externe `athlete_id_externe` |
| `athlete_clubs` | Periodes de membership avec dates debut/fin |
| `athlete_medailles` | Or/argent/bronze par competition |
| `athlete_selections` | Selections equipe nationale |
| `athlete_progressions` | Evolution annuelle |
| `athlete_records` | Records personnels |
| `athlete_podiums` | Top 3 |
| `athlete_resultats` | Tous les resultats avec niveau D/R/N/I |
| `athlete_niveaux` | Qualifications + perfs par niveau |

Les FK vers `athletes` sont en `ON DELETE CASCADE` → supprimer un athlete supprime toutes ses donnees liees.
Les FK vers `epreuves`, `villes`, `clubs` sont en `ON DELETE SET NULL`.

---

## Performances et duree estimee

- **Scraping sequentiel** : ~17 jours pour 300k athletes (1 par 1)
- **Scraping parallele actuel** : ~3.5 jours (7 en parallele)
- **Goulot** : la latence reseau athle.fr (timeout 15s par requete)
- **Pause inter-batch** : `sleep(1)` pour ne pas surcharger athle.fr

---

## Workflow recommande (cas d'usage normaux)

### Cas 1 : nouveau projet from scratch

```
1. Remplir la table nom_et_liens (URLs des 300k athletes)
2. Lancer scraper.php → DEMARRER
3. Laisser tourner ~3.5 jours (auto-refresh)
4. Quand termine, lancer check_sync.php pour rattraper les echecs
5. Done
```

### Cas 2 : ajout de nouvelles URLs dans nom_et_liens

```
1. Inserer les nouvelles URLs dans nom_et_liens
2. Lancer scraper.php → DEMARRER (skip automatiquement les deja en BDD)
3. Optionnel : check_sync.php pour verifier
```

### Cas 3 : rafraichir un athlete specifique

```
scraper.php?test_url={id}&force
```

### Cas 4 : crash BDD avec src/ intact

```
1. Recreer la BDD (admin/setup_bdd.php)
2. Lancer import_bdd.php → reinjecte tout
```

### Cas 5 : verifier la coherence

```
1. check_sync.php (nom_et_liens vs src/) — phase 1 seulement, ne rien rescrape : Ctrl+C apres phase 1
2. check_athletes.php (athletes en BDD vs src/)
```

---

## Points d'attention et pieges

1. **Le flag `scraping_running.flag` doit etre a la racine** du projet (`dirname(__DIR__)`), pas dans `scraping/`
2. **Ne pas lancer `scraper.php` et `check_sync.php` en meme temps** — ils ecrivent tous les deux dans `src/` et la BDD
3. **`failed.json` n'est pas reset automatiquement** — il s'accumule, le supprimer manuellement au besoin
4. **Cache memoire** : `loadRefCache()` charge plusieurs MB en RAM — important pour les performances mais limite si tres gros volumes
5. **`insertAthleteData()` fait un DELETE+INSERT** sur les enfants — si interrompu au milieu, les donnees peuvent etre incompletes
6. **Le mode test `?test_url=` ne respecte pas le flag start/stop** — il s'execute toujours
7. **Hostinger (prod)** : le `set_time_limit(0)` peut etre ignore — d'ou le decoupage en cycles de 25s avec auto-refresh
8. **CLI** : possible d'executer en ligne de commande mais le HTML/JS est inutile dans ce cas
