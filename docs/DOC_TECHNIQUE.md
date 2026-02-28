# BOKONZI — Documentation technique complete

Version : Fevrier 2026

---

## 1. ARCHITECTURE GLOBALE

### 1.1 Stack

- **Backend :** PHP 8+ natif (pas de framework), MySQL via mysqli
- **Frontend :** HTML/CSS/JS vanilla, Chart.js 4.4.7
- **Serveur :** Apache (XAMPP local, Hostinger prod)
- **Domaine :** bokonzi.com

### 1.2 Flux de donnees

```
MySQL (330 000+ athletes)
    ↓
api/*.php  →  cache/ (JSON 24h)
    ↓
index.php  ←  apiCall() (cURL interne)
```

- Les donnees sont stockees en MySQL (18 tables, 30+ FK)
- `index.php` consomme les APIs via `apiCall()` (cURL interne vers bokonzi.com/api/)
- Chaque endpoint API a un cache fichier JSON de 24h

### 1.3 Point d'entree unique

`index.php` est le point d'entree principal (~5500 lignes). Il contient :
- Fonctions PHP utilitaires (lignes 1-40)
- Connexion BDD via `core/db.php` (ligne 9)
- Fonction `apiCall()` pour appels API internes (ligne 43)
- SEO dynamique par page (lignes 160-180)
- Navigation HTML (lignes 455-465)
- 9 pages rendues via `if/elseif ($page === '...')` (lignes 475-4575)
- Panneaux overlay club et epreuve (lignes 4580-4700)
- JavaScript complet : ~100 fonctions (lignes 4700-fin)

---

## 2. BASE DE DONNEES

### 2.1 Schema complet (18 tables)

#### Tables de reference

```sql
athletes (
    id_athlete INT PK AUTO_INCREMENT,
    athlete_id_externe INT UNIQUE,    -- ID athle.fr (URL ?id=)
    nom_1_athlete VARCHAR,            -- Nom de famille
    nom_2_athlete VARCHAR,            -- Prenom
    nom_3_athlete VARCHAR,
    nom_4_athlete VARCHAR,
    nom_complet_athlete VARCHAR,
    date_naissance_athlete DATE,
    annee_naissance_athlete INT,
    id_ville_naissance INT FK → villes,
    taille_cm_athlete INT,
    poids_kg_athlete FLOAT,
    categorie_athlete VARCHAR,        -- SE, ES, JU, CA, MI, BE, V1...
    sexe_athlete CHAR(1),             -- M ou F
    nationalite_athlete VARCHAR(3),   -- ISO 3 lettres
    id_nationalite INT FK → nationalites,
    licence_athlete VARCHAR
)

clubs       (id_club PK, nom_club UNIQUE, departement_club, region_club)
epreuves    (id_epreuve PK, nom_epreuve, sexe_epreuve)
villes      (id_ville PK, nom_ville, pays_ville, departement_ville, region_ville)
competitions(id_competition PK, nom_competition)
categories  (id_categorie PK, code_categorie, nom_categorie, age_min, age_max)
nationalites(id_nationalite PK, code_nationalite UNIQUE, nom_nationalite)
```

#### Tables de donnees athletes

```sql
athlete_clubs (
    id_athlete_club PK, id_athlete FK, id_club FK,
    annee_debut INT, annee_fin INT
)

athlete_records (
    id_record PK, id_athlete FK, id_epreuve FK,
    performance_record INT,           -- En centisecondes (temps) ou cm (distances)
    performance_brut_record VARCHAR,  -- Format affichage ("10\"52", "7m85")
    id_categorie FK, date_record DATE, id_club FK,
    ligue_dept_record VARCHAR, id_ville FK
)

athlete_resultats (
    id_resultat PK, id_athlete FK, id_epreuve FK, id_ville FK,
    annee_resultat INT, date_resultat DATE,
    performance_resultat INT, performance_brut_resultat VARCHAR,
    vent_resultat VARCHAR, tour_resultat VARCHAR,
    place_resultat INT, niveau_resultat VARCHAR,  -- D1-D8, R1-R6, N1-N4, IE, IR
    points_resultat INT
)

athlete_progressions (
    id_progression PK, id_athlete FK, id_epreuve FK,
    annee_progression INT, performance_progression INT,
    performance_brut_progression VARCHAR,
    vent_progression VARCHAR, date_progression DATE,
    rang_perf_progression INT, ligue_dept_progression VARCHAR,
    id_categorie FK, id_club FK, id_ville FK
)

athlete_medailles (
    id_medaille PK, id_athlete FK,
    type_medaille ENUM('or','argent','bronze','autre'),
    annee_medaille INT, id_competition FK, id_epreuve FK, id_ville FK
)

athlete_podiums (
    id_podium PK, id_athlete FK,
    annee_podium INT, niveau_competition VARCHAR,
    place_podium VARCHAR, rang_podium INT,
    id_epreuve FK, performance_podium INT,
    performance_brut_podium VARCHAR,
    vent_podium VARCHAR, date_podium DATE, id_ville FK
)

athlete_selections (
    id_selection PK, id_athlete FK,
    type_selection VARCHAR, date_selection DATE,
    duree_jours_selection INT, age_selection INT,
    id_competition FK, id_epreuve FK,
    classement_selection INT,
    performance_selection INT, performance_brut_selection VARCHAR
)

athlete_niveaux (
    id_niveau PK, id_athlete FK,
    annee_niveau INT, code_niveau VARCHAR,  -- D1-D8, R1-R6, N1-N4, IE, IR
    points_niveau INT, id_club FK
)

athlete_niv_perfs (
    id_niveau_perf PK, id_niveau FK → athlete_niveaux,
    id_epreuve FK, performance_niveau_perf INT,
    code_perf_niveau VARCHAR
)
```

#### Tables utilisateurs

```sql
users (
    id_user PK, email UNIQUE, password_hash VARCHAR,
    nom VARCHAR, prenom VARCHAR,
    role ENUM('athlete','coach','club','admin'),
    id_athlete FK → athletes
)

user_sessions (
    id_session PK, id_user FK → users,
    token VARCHAR(64) UNIQUE,
    expire_at DATETIME  -- 30 jours
)

coach_athletes (id PK, id_coach FK → users, id_athlete FK → athletes)

athlete_perfs_manuelles (
    id_perf PK, id_athlete FK, id_user FK, id_epreuve FK,
    performance INT, performance_brut VARCHAR,
    date_perf DATE, lieu VARCHAR, notes TEXT,
    created_at TIMESTAMP, updated_at TIMESTAMP
)
```

### 2.2 Double systeme d'IDs athletes

**CRITIQUE** — Les athletes ont 2 identifiants :
- `athlete_id_externe` = ID sur athle.fr (utilise dans les URLs `?id=539676`)
- `id_athlete` = ID auto-increment MySQL interne

L'API `athlete.php` accepte les deux : `?id=` (externe) ou `?id_athlete=` (interne).
Le dashboard utilise `athlete_id_externe` partout dans les URLs.

### 2.3 Cles etrangeres

Toutes les tables athlete_* ont `ON DELETE CASCADE` vers `athletes`.
Les FK vers `epreuves`, `villes`, `clubs`, `competitions`, `categories` sont `ON DELETE SET NULL`.

---

## 3. API REST — REFERENCE COMPLETE

### 3.1 Configuration commune (`api/config.php`)

Inclus par chaque endpoint :
- Headers CORS (`Access-Control-Allow-Origin: *`)
- Connexion BDD via `core/db.php`
- `jsonResponse($data, $code)` — encode JSON + exit

### 3.2 Endpoint : `athlete.php`

**But :** Profil complet d'un athlete

| Param | Type | Description |
|-------|------|-------------|
| `id` | int | athlete_id_externe (athle.fr) |
| `id_athlete` | int | ID interne BDD |

**Retour :** `{ identite, clubs[], medailles[], selections[], progressions[], records[], podiums[], resultats[], niveaux[] }`

Chaque record/progression/selection inclut un sous-tableau `niveaux[]` (via subquery GROUP_CONCAT sur `athlete_resultats.niveau_resultat`).

**Cache :** 24h, cle `athlete_[md5(id)]`

### 3.3 Endpoint : `search.php`

**But :** Recherche multi-criteres

| Param | Type | Description |
|-------|------|-------------|
| `nom` | string | Recherche dans nom_complet (LIKE) |
| `nom1` | string | Nom de famille |
| `nom2` | string | Prenom |
| `club` | string | Nom du club (LIKE, via JOIN) |
| `categorie` | string | Code FFA (SE, ES, JU...) |
| `sexe` | string | M ou F |
| `nationalite` | string | Code ISO 3 lettres |
| `epreuve` | string | Nom epreuve (LIKE, via JOIN records) |
| `ville` | string | Nom ville (LIKE, via JOIN resultats) |
| `competition` | string | Nom competition (LIKE, via JOIN medailles) |
| `medaille` | string | Type (or, argent, bronze) |
| `annee` | int | Annee de resultat |
| `licence` | string | Numero de licence (LIKE) |
| `page` | int | Page (defaut: 1) |
| `limit` | int | Resultats/page (defaut: 50, max: 100) |

**Logique speciale :**
- Au moins 1 filtre requis
- Exclut les clubs >5000 athletes (sauf si filtre club actif)
- Chaque athlete retourne : `niveaux[]`, `top_records[5]` avec `top_niveau`
- Batch queries pour eviter N+1

**Cache :** 24h, cle `search_[md5(tous_params)]`

### 3.4 Endpoint : `club_stats.php`

**But :** Stats detaillees d'un club

| Param | Type | Description |
|-------|------|-------------|
| `id` | int | ID club |
| `nom` | string | Nom exact du club |
| `annee` | int | Filtre par annee |
| `rp` | int | Page records (10/page) |
| `ep` | int | Page epreuves (50/page) |
| `nationalite` | string | Filtre athletes par nationalite |
| `sexe` | string | Filtre athletes par sexe |
| `categorie` | string | Filtre athletes par categorie |
| `nat_detail` | string | Codes nat pour comparaison detaillee |
| `perso` | flag | Mode records personnels |
| `nocache` | flag | Bypass cache |

**Logique speciale :**
- Filtre universel `$athFilter` : subquery `AND ac.id_athlete IN (SELECT _af.id_athlete FROM athletes _af WHERE ...)` appliquee a toutes les requetes (~30) quand nationalite/sexe/categorie sont actifs
- Verification d'appartenance au club par periode (`annee_debut`/`annee_fin` via `$mcRec`, `$mcRes`, etc.)
- Classification epreuves par discipline (sprint, demi-fond, fond, haies, sauts, lancers, etc.) avec couleurs

**Retour :** `{ club, total_athletes, par_sexe, par_categorie, nationalites, medailles, medailles_detail, podiums, selections, epreuves (paginées), records (pagines), top_athletes, top_villes, niveaux, progressions, resultats_par_annee, annees_disponibles, ... }`

**Cache :** 24h, cle `clubstats_[md5(tous_params)]`

### 3.5 Endpoint : `epreuve_stats.php`

**But :** Stats detaillees d'une epreuve

| Param | Type | Description |
|-------|------|-------------|
| `nom` | string | Nom exact de l'epreuve (requis) |
| `page` | int | Page records (50/page) |
| `limit` | int | Records/page (defaut: 50) |
| `sexe` | string | Filtre par sexe |
| `categorie` | string | Filtre par categorie |

**Logique speciale :**
- Detection auto temps vs distance pour le tri (ASC pour sprint, DESC pour lancers/sauts)
- Regex sur nom epreuve : `Poids|Disque|Javelot|Marteau|Hauteur|Perche|Longueur|Triple|Decathlon|Heptathlon`

**Cache :** 24h, cle `ep_[md5(params)]`

### 3.6 Endpoint : `ville_stats.php`

**But :** Stats detaillees d'une ville

| Param | Type | Description |
|-------|------|-------------|
| `nom` | string | Nom exact de la ville (requis) |
| `page` | int | Page (30/page) |
| `limit` | int | Resultats/page (max: 100) |
| `niv` | string | Filtrer niveaux : `D3,D2,R1` |
| `nat` | string | Filtrer nationalites : `FRA,MAR` |
| `ans` | string | Filtrer annees : `2023,2024` |

**Cache :** 24h, cle `villestats_[md5(params)]`

### 3.7 Endpoint : `stats.php`

**But :** Stats globales de la plateforme

| Param | Type | Description |
|-------|------|-------------|
| `detail` | flag | Inclure top clubs/epreuves/athletes/villes |
| `top` | int | Nombre d'items dans les tops (10-200, defaut: 50) |
| `nocache` | flag | Bypass cache |

**Retour :** `{ comptages{15 tables}, par_sexe, par_categorie, par_nationalite, medailles_par_type, [top_clubs, top_epreuves, top_villes, top_athletes] }`

**Cache :** 24h, cles `stats_base` et `stats_detail_N`

### 3.8 Endpoint : `classement.php`

**But :** Classement athletes par epreuve

| Param | Type | Description |
|-------|------|-------------|
| `epreuve` | int | ID epreuve (requis) |
| `categorie` | string | Filtre categorie |
| `sexe` | string | Filtre sexe |
| `annee` | int | Filtre annee |
| `limit` | int | Resultats (defaut: 50, max: 200) |
| `offset` | int | Pagination |

**Logique :** ROW_NUMBER() OVER (PARTITION BY athlete ORDER BY perf) pour meilleure perf par athlete.

**Cache :** Aucun (temps reel)

### 3.9 Endpoint : `liste.php`

**But :** Liste paginee de tous les athletes

| Param | Type | Description |
|-------|------|-------------|
| `page` | int | Page (defaut: 1) |
| `limit` | int | Resultats/page (defaut: 50, max: 100) |
| `ordre` | string | `nom`, `date`, `id`, `recent`, `medailles`, `podiums`, `selections`, `records`, `random` |

**Cache :** 24h (7 jours pour `random`)

### 3.10 Endpoint : `log.php`

**But :** Logging actions utilisateur

**POST :** `{ events: [{ page, action, detail, value, sid, screen, lang, referrer, duration_ms }] }`
**GET :** `?date=YYYY-MM-DD&limit=100&ip=&action=&page_filter=`

**Stockage :** `logs/log_YYYY-MM-DD.json` (JSON Lines, 1 event/ligne)

### 3.11 Endpoint : `performances.php`

**But :** CRUD performances manuelles (authentification requise)

- **GET** `?id_athlete=X` — Lister les perfs
- **POST** `{ id_athlete, id_epreuve, performance_brut, date_perf, lieu, notes }` — Creer
- **PUT** `{ id_perf, ... }` — Modifier (auteur ou admin uniquement)
- **DELETE** `{ id_perf }` — Supprimer (auteur ou admin uniquement)

### 3.12 Authentification (`api/auth/`)

| Endpoint | Methode | Body | Retour |
|----------|---------|------|--------|
| `login.php` | POST | `{ email, password }` | `{ token, user }` |
| `register.php` | POST | `{ email, password, nom, prenom, role }` | `{ token, user }` |
| `logout.php` | POST | — | `{ success }` |
| `me.php` | GET | Cookie `bk_token` | `{ authenticated, user }` |

Roles : `athlete` (defaut), `coach`, `club`, `admin`.
Sessions : token 64 chars, cookie `bk_token`, TTL 30 jours.

---

## 4. FRONTEND — COMPOSANTS PRINCIPAUX

### 4.1 Panneau detail club (JS)

Composant reutilisable present sur 3 pages (Accueil, Clubs, Recherche) avec systeme de suffixes.

**Fonctions :**
- `_openClubPanel(url, suffix)` — Charge les donnees et affiche le panneau overlay
- `_closeClubPanel(suffix)` — Ferme
- `_fillClubPanel(data, suffix)` — Remplit le header et declenche le 1er onglet
- `_switchClubTab(tab, suffix)` — Change d'onglet
- `_renderClubTab(tab, suffix)` — Rendu HTML d'un onglet

**5 onglets :**
1. **Epreuves** (50/page) — Records du club par discipline, meilleur H et F, badges niveaux
2. **Nationalites** — Repartition, graphiques doughnut, comparaison detaillee
3. **Records** (10/page) — Records personnels des athletes du club
4. **Stats** — Graphiques sexe/categorie/evolution + medailles/podiums/selections
5. **Resume** — Texte auto-genere (3 modes : Global, Par annee, Comparer)

**Filtrage :**
- `_clubFilterParams(d)` — Construit les params URL pour filtres nationalite/sexe/categorie actifs
- Les filtres sont propages a toutes les paginations (records, epreuves, resume par annee)

### 4.2 Panneau detail epreuve (JS)

**Fonctions :** `openEpreuveDetail(nom)`, `closeEpreuveDetail()`, `switchEpreuveTab(tab)`, `loadEpreuveRecPage(page)`, `_renderEpreuveTab(tab)`

**4 onglets :** records (50/page), nationalites, stats, resume

### 4.3 Bio athlete auto-generee (JS)

Fonction `buildAthleteBio(data, selectedYears)` (~500 lignes) qui genere une biographie textuelle a partir des donnees du profil. Filtrable par annees via selecteur de checkboxes.

### 4.4 Resume club auto-genere (JS)

Fonction `_buildResumeText(d, annee)` (~300 lignes, 18 paragraphes conditionnels). 3 modes :
- **Global** — Resume complet toutes annees
- **Par annee** — Resume filtre sur une annee
- **Comparer** — Comparaison de 2-3 annees

### 4.5 Comparateur (JS + localStorage)

- Panier athletes : `localStorage.bk_cmp_athletes` (array `{id, name}`)
- Panier clubs : `localStorage.bk_cmp_clubs`
- Boutons `+` sur chaque athlete/club
- Badge flottant en bas a droite
- Page `?page=comparer` : graphiques barres + radar

### 4.6 Systeme de niveaux (JS + PHP)

```javascript
_nivBadge(code)    // Retourne le HTML d'un badge colore
_nivBadges(arr)    // Plusieurs badges
_highestNiveau(arr) // Meilleur niveau d'un tableau
```

| Code | Couleur | Background | Bordure | Texte |
|------|---------|------------|---------|-------|
| D1-D8 | Orange | #f9731620 | #f97316 | #fb923c |
| R1-R6 | Cyan | #0891b220 | #0891b2 | #22d3ee |
| N1-N4 | Rose | #e11d4820 | #e11d48 | #fb7185 |
| IE, IR | Fuchsia | #c026d320 | #c026d3 | #e879f9 |

### 4.7 Logging client (JS)

```javascript
bkLog(action, detail, value)  // Ajoute au batch
// Flush automatique toutes les 2 secondes
// sendBeacon sur page_leave (beforeunload)
```

Actions trackees : `page_view`, `click_link`, `click_button`, `form_submit`, `input_change`, `copy`, `page_leave`, `js_error`, `navigation`

---

## 5. SYSTEME DE CACHE

### 5.1 Mecanisme

Chaque endpoint API :
1. Calcule une cle MD5 a partir de tous les parametres
2. Verifie si `cache/[prefix]_[md5].json` existe et a moins de 24h
3. Si oui : retourne le fichier cache directement
4. Si non : execute les requetes SQL, encode en JSON, ecrit le fichier, retourne

### 5.2 Prefixes

| Prefixe | Endpoint | TTL |
|---------|----------|-----|
| `athlete_` | athlete.php | 24h |
| `search_` | search.php | 24h |
| `clubstats_` | club_stats.php | 24h |
| `villestats_` | ville_stats.php | 24h |
| `ep_` | epreuve_stats.php | 24h |
| `clubs_` | clubs.php | 24h |
| `epreuves_` | epreuves.php | 24h |
| `villes_` | villes.php | 24h |
| `stats_base` / `stats_detail_N` | stats.php | 24h |
| `liste_` | liste.php | 24h (7j pour random) |

### 5.3 Invalidation

- `admin/clear_cache.php` — Vide tout le cache
- `admin/clear_cache.php?prefix=clubstats` — Vide un prefixe specifique
- `?nocache=1` sur stats.php, club_stats.php — Bypass cache

---

## 6. SECURITE

- Mots de passe hashes avec `password_hash()` (BCRYPT)
- Sessions via tokens aleatoires (64 chars hex)
- Cookie `bk_token` (httpOnly, sameSite=Lax)
- Prepared statements pour les mutations (performances.php)
- `real_escape_string()` pour les requetes de lecture
- Dossiers `cache/` et `logs/` proteges par `.htaccess` (Deny from all)
- Roles utilisateur (athlete, coach, club, admin) avec verification `requireRole()`
- Troncature des champs de log pour eviter les abus

---

## 7. SEO

- `core/seo.php` — Generation dynamique : `<title>`, `<meta description>`, Open Graph, Twitter Cards, Schema.org JSON-LD
- `sitemap.php` — Generation sitemap XML
- `robots.txt` — Interdit : admin/, cache/, logs/
- Profils athletes : breadcrumbs, Schema.org Person, AggregateRating

---

## 8. PATTERNS RECURRENTS

### Batch query niveaux (eviter N+1)
```php
$athIds = array_map(fn($a) => $a['id_athlete'], $athletes);
$idsList = implode(',', $athIds);
$nRes = $conn->query("SELECT id_athlete, code_niveau FROM athlete_niveaux WHERE id_athlete IN ($idsList)");
```

### Subquery niveaux pour records
```sql
(SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
 FROM athlete_resultats ares
 WHERE ares.id_athlete = r.id_athlete AND ares.id_epreuve = r.id_epreuve
   AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
```

### API call depuis index.php
```php
$data = apiCall("$BASE_API/endpoint.php?" . http_build_query($params));
// $BASE_API = "https://bokonzi.com/api"
```

### Chart.js
```javascript
new Chart(document.getElementById('canvasId'), {
    type: 'doughnut',
    data: { labels: [...], datasets: [{ data: [...], backgroundColor: [...] }] },
    options: { responsive: true, plugins: { legend: { labels: { color: '#c9d1d9' } } } }
});
```

### Pagination
```php
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;
$totalPages = ceil($total / $limit);
```

---

## 9. DEPLOIEMENT

### Local → Production

1. Push sur le repo Git
2. Pull sur Hostinger (via SSH ou panel Git)
3. Verifier `core/credentials.php` (identifiants prod)
4. Vider le cache si necessaire : `admin/clear_cache.php`

### Verifications post-deploiement

- `bokonzi.com/api/stats.php` — Stats globales OK
- `bokonzi.com/?page=accueil` — Dashboard charge
- `bokonzi.com/admin/clear_cache.php` — Cache vide si besoin
