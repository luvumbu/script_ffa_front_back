# Changelog — Session 2026-05-15

Documentation des modifications effectuees dans la session.

## 1. Mode local : API et donnees toutes locales

### Bascule API distante → API locale
- `index.php:10` : `$BASE_API = BK_URL('/api')` (au lieu de `bokonzi.com/api` en local)
- `pages/profil.php:7` : pareil
- Pour le JS, separation entre URL absolue (PHP `apiCall`) et URL relative (JS frontend)
  - `$BASE_API` : `http(s)://<host>/BK/api` ou `/api`
  - `$BASE_API_JS = BK_BASE . '/api'` : `/BK/api` en local, `/api` en prod
- `index.php:10873` et `:16066` : injection `BASE_API` JS relative
- `app/Views/layouts/main.php:130` : `BASE_API` = `$baseUrl . '/api'` (relatif)
- `public/assets/js/utils.js:7` : fallback detecte auto local vs prod (jamais hardcode `bokonzi.com`)

### Bandeau LOCAL/PROD dans `admin/db_archive.php`
Encadre vert/rouge sous le titre avec :
- Badge `LOCAL` / `PROD`
- Host, DB name, MySQL user
- Fichier de credentials charge

## 2. Optimisations performance MySQL local

### Index manquants ajoutes
```sql
CREATE INDEX idx_prog_annee ON athlete_progressions (annee_progression);  -- 8M lignes, 4 min
CREATE INDEX idx_rec_date   ON athlete_records (date_record);
CREATE INDEX idx_res_date   ON athlete_resultats (date_resultat);
```

### Timeouts relaches en local
- `api/config.php` : `set_time_limit(300)` + `ini_set max_execution_time=300`
- `index.php:197` (`apiCall`) : timeout HTTP 90s en local, 30s en prod
- `api/liste.php:43` : cache TTL 7 jours en local (au lieu de 24h)

### Pre-generation de cache
- `admin/warm_athletes_cache.php` (utilitaire CLI/web) : genere les 4 caches de `liste.php`
  pour que la page `/athletes` soit instantanee.

### Header X-BK-KEY retire en local (gain ENORME)
`index.php:198-202` : `X-BK-KEY` etait inclus systematiquement par `apiCall()`. Ca activait
le mode super admin dans `search.php` → la clause `a.visible = 1` etait omise → MariaDB
perdait son chemin d'index → requete 11x plus lente (5.7s → 64s sur Mayer/Lille).
Solution : `$_hdr = BK_IS_LOCAL ? '' : "X-BK-KEY: ..."` → ne pas envoyer en local.

## 3. Dénormalisation pages liste

### `villes.php` + nouvelles colonnes
3 colonnes ajoutees a `villes` (rempli par `admin/refresh_villes_stats.php`) :
- `nb_athletes` INT (pre-calcule)
- `annee_debut_perf` SMALLINT
- `annee_fin_perf` SMALLINT
- Index `idx_villes_nb` sur `nb_athletes`

API `villes.php` lit directement ces colonnes au lieu du GROUP BY :
- **130s → 0.75s** (170x plus rapide)
- Premier appel cache puis hit cache instantane

### Table `villes_stats_annee` (filtre par annee)
```sql
CREATE TABLE villes_stats_annee (
  id_ville INT, annee SMALLINT, nb_athletes INT,
  PRIMARY KEY (id_ville, annee),
  INDEX idx_annee_nb (annee, nb_athletes)
);
```
44k lignes (ville × annee), <1 MB. Permet `/villes?annee=2024` instantane.
- Page `/villes` : selecteur annee dans l'UI + H1 dynamique.

### Script de maintenance : `admin/refresh_villes_stats.php`
Lance apres scraping : recalcule `nb_athletes` + repopule `villes_stats_annee`.

## 4. Mode fichier pour `athlete_progressions` (gain -1.2 GB BDD)

### Architecture file-backed store
- `core/progressions_store.php` (NOUVEAU) :
  - `progStoreEnabled()`, `progStoreSourcePath()`
  - `progStoreLoadForAthlete(int $idAthlete)` : lecture O(1) via index sharded
  - `progStoreLoadForAthletes(array $ids)` : batch
  - `progStoreAppendBatch(int $idAthlete, array $rows)` : append-only avec marker delete-and-replace
  - `progStoreEnrichForProfile(mysqli $conn, int $id, array $rows)` : resoud les FK (epreuves/villes/clubs/categories) en batch SQL

### Format `.jsonl` + index sharded
- Source : `archives/athlete_progressions_*.jsonl` (le plus recent ou `_live.jsonl`)
- Pointer : `archives/.prog_idx/source.txt` (nom du fichier source)
- Index sharded : `archives/.prog_idx/0.json` … `255.json` (sharding `id_athlete & 0xFF`)
- Marker `{"_op":"delete","id_athlete":X}` invalide l'historique d'un athlete avant son re-append (zero doublons)

### Scripts d'init / activation
- `admin/progressions_init.php` (NOUVEAU, CLI ou web) : pointe automatiquement vers le fichier le plus recent + construit l'index sharded (8.1M lignes en ~26s).
- `admin/progressions_activate.php` (NOUVEAU, web) : verifie fichier+index, puis `TRUNCATE athlete_progressions` + active `data_source.athlete_progressions = "file"`. Confirmation par `&confirm=1`.

### Code lecteur
- `api/athlete.php:319` : si mode `file`, lit depuis le store et resout les FK ; sinon SQL classique. UX identique.

### Code ecriture (scraping)
- `core/insert_athle.php:244` : si mode `file`, append dans le `.jsonl` avec marker delete-and-replace. Sinon INSERT BDD classique.

### Resultats prod
| | Avant | Apres |
|---|---|---|
| Table `athlete_progressions` | 1188 MB / 8.1M lignes | **0 MB / 0 lignes** |
| BDD totale Hostinger | 2776 MB | **1588 MB** (−43%) |
| Profil Mayer charge | OK | **0.42s, 382 progressions** depuis fichier |

## 5. Profil athlete : verifications et fixes

### Bug majeur `$data` ecrase
- `index.php:9077` (avant) : `$labels = []; $data = []; $bruts = [];`
  → `$data` global de l'API ecrase par une variable temporaire de boucle.
- Consequence : `$_career` ne voyait jamais `$data['medailles']` → `nb_or = nb_argent = 0` → divs Medailles or/argent JAMAIS affichees malgre 9+4 medailles de Kevin Mayer.
- Fix : renomme en `$_dataPts`.

### Career stats corriges
- `nb_disciplines` : agregue depuis `records + progressions + resultats` (au lieu de juste `resultats`). 1 → 20 pour LUVUMBU.
- `nb_competitions` : compte les `(date, lieu)` uniques sur toutes les sources (records, resultats, progressions, podiums, medailles).
- Compteur "Records perso" retire (la valeur 154 n'etait pas le nb de PB mais le nb d'entrees).
- "Annees actives" affiche maintenant la **periode editoriale** : `2004 ─── 2024` avec 21 annees actives.

### Bug `ville_stats.php` (warnings PHP)
- `api/ville_stats.php:555` : `progressions` et `selections` sont des **objets** `{nb_*: int}`, pas des listes annuelles. `array_values()` ecrasait les cles. Retire du foreach `$__bkFilterByYear`.

### Bug SQL `club_stats.php`
- `api/club_stats.php:662` : colonne `s.competition_selection` inexistante → `s.id_competition`.

### Bug `liste.php`
- `api/liste.php:188` : `$yearWhere` utilise avant sa definition → `$totalSql` ne tenait pas compte du filtre annee. Defini au bon endroit.

### Bug SQL `search.php` (WHERE vide)
- `api/search.php:111` : `$where = ['1=1']` au lieu de `$where = []` (evite `WHERE ` vide quand seuls des joins sont presents + super admin actif).
- `api/search.php:272` : garde-fou "filtre requis" ignore la sentinelle `1=1`.

## 6. Profil athlete : enrichissements UI

### Palmares enrichi par niveau de competition
Bloc Palmares dans le hero passe de "13 medailles" a :
```
36 titres
Champion du Monde       5×
Champion d'Europe       4× (+ 2× 2e)
Champion de France      7× (+ 3× 2e)
Champion Interregional  1× (+ 2× 2e)
Champion Regional       9× (+ 1× 2e)
Champion Departemental 10× (+ 2× 2e)
─────
Medailles internationales : 9 or, 4 argent
```

### Vitesse km/h sur les courses
- `bkPerfSpeedKmh($epreuve, $perfInt)` : detecte course (filtre sauts/lancers/combines), extrait la distance (100m, marathon, 10 km, semi…), calcule vitesse km/h.
- `bkPerfBadge($ep, $brut, $int)` : badge perf + vitesse km/h en violet a cote.
- Applique sur tableaux Records / Progressions / Podiums du profil et dans le rendu magazine (`p2-rec-row1`).
- Sur Kevin Mayer : 29 vitesses affichees, jusqu'a 34.3 km/h.

### Niveau plus visible (`.p2-niv-badge`)
- Font Bodoni Moda 15px, border 2px, glow `box-shadow currentColor`, brightness 1.05.
- 177 badges sur le profil Mayer, bien plus voyants qu'avant.

### Watermark annee verticale (sur le profil)
- `<div class="p2-year-watermark">` : 2 annees en `writing-mode: vertical-rl` + rotation 180deg
- Position fixed sur le cote gauche, derriere tout le contenu (z-index 0)
- 9 couches de `text-shadow` (glow violet multi-niveaux + bevel 3D + ombre portee)
- Tailles responsive : `22-56px` desktop, `18-38px` mobile

### Carriere stylee
- Bloc carriere : `2004 ─── 2024` en grand Bodoni Moda 30px avec dash degrade
- Sous-titre italique "21 annees actives"
- Prend 2 colonnes dans le grid `.p2-careers`

### Records personnels : derouler progressif
- Affiche 4 records par defaut
- Bouton "Voir plus (+5)" en violet → revele 5 de plus par clic
- Quand tout est affiche, bascule en "Voir moins" en **ambre/or** (couleur differente)
- Reset a 4 au changement d'onglet categorie

### Reorganisation grid
- Bloc Records personnels et Bloc Clubs passent en `p2-w12` (pleine largeur, ne sont plus cote a cote).

## 7. Reseaux sociaux : preview iframe

### Champ `_embed` dans la modale `p2SocModal`
Sous les 5 URLs profils, ajout d'un champ "Preview embed" pour coller l'URL d'un contenu (post/video).

### Fonction `bkSocialEmbed($url)` (index.php)
Detecte la plateforme et retourne `['platform','html','script','height']` avec **methode officielle** :
- **YouTube** : iframe directe `youtube.com/embed/ID` (pas de script requis)
- **TikTok** : `<blockquote class="tiktok-embed">` + script `tiktok.com/embed.js`
- **Instagram** : `<blockquote class="instagram-media">` + script `instagram.com/embed.js`
- **X/Twitter** : `<blockquote class="twitter-tweet">` + script `platform.twitter.com/widgets.js`
- **Facebook** : iframe plugin direct (videos/posts)

Les scripts sont charges **en async**, **uniquement** si l'embed concerne est present.

### API mise a jour
- `api/athlete_socials.php` accepte le champ `_embed` (validation domaines social).

## 8. Fichiers ajoutes / modifies

### Nouveaux
- `core/progressions_store.php`
- `admin/progressions_init.php`
- `admin/progressions_activate.php`
- `admin/refresh_villes_stats.php`
- `CHANGELOG.md` (ce fichier)

### Modifies
- `index.php` (gros : compteurs, palmares, vitesse, niveau, watermark, carriere, records "voir plus", embed socials, fix `$data`)
- `pages/profil.php` (`$BASE_API` separation, `_apiBase` JS relatif)
- `app/Views/layouts/main.php` (`BASE_API` relatif)
- `public/assets/js/utils.js` (fallback detecte auto)
- `api/config.php` (`set_time_limit(300)` en local + chargement `paths.php`)
- `api/search.php` (fix WHERE vide)
- `api/liste.php` (fix `$yearWhere`, TTL 7j)
- `api/athlete.php` (lit du store si data_source = file)
- `api/club_stats.php` (fix `competition_selection`)
- `api/ville_stats.php` (fix warnings array_values)
- `api/villes.php` (denormalisation + filtre annee)
- `api/athlete_socials.php` (validation `_embed`)
- `core/insert_athle.php` (append fichier si mode file)
- `admin/db_archive.php` (bandeau LOCAL/PROD)

### Utilitaires CLI/dev (non a deployer en prod)
- `admin/restore_athletes_local.php` (one-shot import)
- `admin/check_bk_local.php` (diagnostic)
- `admin/check_profil_full.php` (verif profil)
- `admin/warm_athletes_cache.php` (pre-genere cache liste)
- `admin/find_athletes_urls.php`
- `admin/check_prod_archives.php`
- `admin/debug_profil.php`

## 9. Gains mesurables

| Page / endpoint | Avant | Apres |
|---|---|---|
| `/villes` (liste) | 130s | **0.75s** (170×) |
| `/athletes` (liste) | 180s | **0.1s** (1800×) |
| `/recherche?club=X` (1er hit) | 64s | **5.7s** (11×) |
| `api/athlete.php?id=X` | ~0.4s | **0.13s** (depuis store) |
| BDD prod totale | 2776 MB | **1588 MB** (−43%) |
| `athlete_progressions` (prod) | 1188 MB | **0.1 MB** |

## 10. Procedure de deploiement prod

Voir `CHANGELOG.md` section "Mode fichier" :
1. Upload des 16 fichiers (3 nouveaux + 13 modifies)
2. `https://bokonzi.com/admin/refresh_villes_stats.php?bk_key=...` (cree colonnes + remplit)
3. `https://bokonzi.com/admin/progressions_init.php?bk_key=...` (pointe vers fichier existant + construit index)
4. `https://bokonzi.com/admin/progressions_activate.php?bk_key=...&confirm=1` (TRUNCATE + active mode file)

Rollback si besoin : panel `db_archive.php` → "Basculer vers BDD" sur `athlete_progressions` (re-importe le `.jsonl`).
