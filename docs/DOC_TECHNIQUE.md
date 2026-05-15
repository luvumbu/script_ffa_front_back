# BOKONZI — Documentation technique complete

**Version** : Avril 2026
**URL prod** : bokonzi.com
**Type** : Plateforme web statistiques athletisme francais

---

## TABLE DES MATIERES

1. Architecture globale
2. Base de donnees (schema complet)
3. BACKEND — API REST (36 endpoints)
4. BACKEND — Authentification (Google OAuth + Super Admin)
5. BACKEND — Securite, rate limiting, logging
6. FRONTEND — Structure de index.php
7. FRONTEND — Pages et composants
8. FRONTEND — Systeme de theme (clair / sombre / auto)
9. SCRAPING — Pipeline complet
10. SCRAPING — AthleteScraper et insertion BDD
11. SCRAPING — Orchestrateur et controle
12. ADMIN — Panel et outils
13. Cache systeme
14. SEO & Monetisation
15. Profil masque & Signalement
16. Search tracking
17. Systeme de contact (avec confirmation)
18. Patterns recurrents
19. Deploiement
20. Historique des changements

---

## 1. ARCHITECTURE GLOBALE

### 1.1 Stack
- **Backend** : PHP 8+ natif (pas de framework), MySQL via mysqli
- **Frontend** : HTML/CSS/JS vanilla, Chart.js 4.4.7, Three.js (CDN)
- **Serveur** : Apache (XAMPP local, Hostinger prod)
- **Domaine** : bokonzi.com (Hostinger), local : `C:\xampp\htdocs\BK\`

### 1.2 Flux de donnees
```
athle.fr (HTML brut)
    | curl_multi (7 athletes x 3 pages = 21 req parallel)
    v
scraping/ (Pipeline)
    | parsing AthleteScraper.php
    | insertion insert_athle.php
    v
MySQL (29 tables, 30+ FK, ~300 000 athletes)
    |
    v
api/*.php (36 endpoints)
    | cache fichier JSON 24h
    v
index.php (~10 800 lignes, 11 pages)
    | apiCall() (cURL interne vers /api/)
    v
Browser (HTML/CSS/JS)
```

### 1.3 Point d'entree unique
`index.php` est le point d'entree principal (~10 800 lignes). Contient :
- Fonctions PHP utilitaires (1-55) : `dateFR()`, `apiCall()`, `require core/db.php`
- SEO dynamique par page (137-250) : `$seoTitle`, `$canonical`, OG, Twitter, JSON-LD
- Navigation HTML (455-465)
- 11 pages rendues via `if/elseif ($page === '...')` (475-4575)
- Panneaux overlay club/epreuve (4580-4800)
- JavaScript : ~375 fonctions (4800-fin)

### 1.4 Repartition du code
| Type | Lignes | % |
|------|--------|---|
| PHP (backend + templates) | 51 131 | 84% |
| JavaScript (frontend) | 5 723 | 9.4% |
| HTML | 1 660 | 2.7% |
| CSS | 1 128 | 1.9% |
| JSON/Config | 1 188 | 2% |
| **TOTAL** | **~61 000** | 100% |

---

## 2. BASE DE DONNEES (29 tables)

### 2.1 Tables de reference

```sql
athletes (
    id_athlete INT PK AUTO_INCREMENT,
    athlete_id_externe INT UNIQUE,    -- ID athle.fr
    nom_1_athlete VARCHAR,             -- Nom de famille
    nom_2_athlete VARCHAR,             -- Prenom
    nom_complet_athlete VARCHAR,
    date_naissance_athlete DATE,
    annee_naissance_athlete INT,
    id_ville_naissance INT FK,
    taille_cm_athlete INT,
    poids_kg_athlete FLOAT,
    categorie_athlete VARCHAR,         -- SE, ES, JU, CA, MI, BE, V1...
    sexe_athlete CHAR(1),              -- M ou F
    nationalite_athlete VARCHAR(3),
    id_nationalite INT FK,
    licence_athlete VARCHAR,
    visible TINYINT DEFAULT 1          -- 0 = profil masque
)

clubs       (id_club PK, nom_club UNIQUE, departement, region)
epreuves    (id_epreuve PK, nom_epreuve, sexe_epreuve)
villes      (id_ville PK, nom_ville, pays, departement, region)
competitions(id_competition PK, nom_competition)
categories  (id_categorie PK, code_categorie, age_min, age_max)
nationalites(id_nationalite PK, code_nationalite UNIQUE, nom)
```

### 2.2 Tables donnees athletes (9 tables)

| Table | Cle | Notes |
|-------|-----|-------|
| `athlete_clubs` | id_athlete + id_club + annee_debut/fin | Periodes membership |
| `athlete_records` | id_athlete + id_epreuve + perf + date + id_ville | Records personnels |
| `athlete_resultats` | id_athlete + id_epreuve + date + perf + niveau_resultat | D/R/N/I |
| `athlete_medailles` | id_athlete + type_medaille + id_epreuve + id_competition | or/argent/bronze/autre |
| `athlete_podiums` | id_athlete + rang_podium + id_epreuve | Top 3 |
| `athlete_selections` | id_athlete + id_competition + id_epreuve | Equipe France |
| `athlete_progressions` | id_athlete + id_epreuve + annee + perf | Evolution annuelle |
| `athlete_niveaux` | id_athlete + code_niveau + points_niveau + annee | Qualifications |
| `athlete_niv_perfs` | id_niveau + id_epreuve + performance | Perfs par niveau |

**Performances** : stockees en INT (centisecondes pour temps, centimetres pour distances) + brut texte (`10''52`, `7m85`).

**Niveau competition** : `D1-D8` (departemental orange), `R1-R6` (regional cyan), `N1-N4` (national rose), `IE/IR` (international fuchsia).

### 2.3 Tables utilisateurs

```sql
users (
    id_user PK, email UNIQUE, password_hash VARCHAR,
    nom, prenom, role ENUM('athlete','coach','club','admin'),
    google_id VARCHAR(255) UNIQUE,
    oauth_provider VARCHAR(50)         -- 'google' / null
)

user_sessions (id_session PK, id_user FK, token VARCHAR(64) UNIQUE, expire_at)

athlete_perfs_manuelles (id_perf PK, id_athlete FK, id_user FK, ...)
```

### 2.4 Tables systeme (10 tables)

| Table | Role |
|-------|------|
| `logs` | Tracking activite utilisateur (page_view, clicks, forms, errors) |
| `athlete_follows` | Suivi athletes (UK: email + athlete_id_ext) |
| `club_follows` | Suivi clubs (UK: email + club_id) |
| `email_subscribers` | Newsletter + PDF (UK: email + source) |
| `contact_messages` | Messages contact confirmes (lu = 0/1) |
| `contact_confirm_tokens` | Tokens confirmation email contact (24h TTL) |
| `password_resets` | Tokens reinitialisation mdp (1h TTL) |
| `search_tracking` | Recherches/consultations (5 search_type, 3 source) |
| `profile_reports` | Signalements profil (5 motifs, status new/read/resolved) |
| `profile_hide_tokens` | Tokens retrait self-service (48h TTL, 1/email) |

### 2.5 Double systeme d'IDs athletes (CRITIQUE)
- `athlete_id_externe` = ID athle.fr (URL `?id=`, dashboard)
- `id_athlete` = PK auto-increment MySQL interne
- L'API `athlete.php` accepte les 2 : `?id=` (externe) ou `?id_athlete=` (interne)
- **TOUJOURS** utiliser `athlete_id_externe` dans les URLs publiques

### 2.6 Cles etrangeres
- `athlete_*` -> `athletes` : **ON DELETE CASCADE**
- vers `epreuves`, `villes`, `clubs`, `competitions`, `categories` : **ON DELETE SET NULL**

---

## 3. BACKEND — API REST (36 endpoints)

### 3.1 Configuration commune (`api/config.php`)
Inclus par chaque endpoint :
- Headers JSON + CORS (`Access-Control-Allow-Origin: *`)
- Connexion BDD via `core/db.php`
- `core/auth.php` charge automatiquement -> `getCurrentUser()` disponible
- `jsonResponse($data, $code)` — encode JSON + exit
- Constante `BK_API_KEY = 'bk_s3cr3t_2026_xK9mP'` (bypass cache, reset vues)

### 3.2 Endpoints donnees

| Endpoint | But | Cache |
|----------|-----|-------|
| `athlete.php` | Profil complet (`?id=` ou `?id_athlete=` ou `?licence=`) | 24h |
| `search.php` | Recherche multi-criteres (12 filtres) | 24h |
| `liste.php` | Liste paginee athletes (8 ordres de tri) | 24h-7j |
| `clubs.php` | Liste clubs paginee | 24h |
| `club_stats.php` | Stats club (id/nom, annee, rp/ep, filtres) | 24h |
| `epreuves.php` | Liste epreuves paginee | 24h |
| `epreuve_stats.php` | Stats epreuve detaillee | 24h |
| `epreuve_records.php` | Records pagines par epreuve | 24h |
| `villes.php` | Liste villes paginee | 24h |
| `ville_stats.php` | Stats ville (filtres niv/nat/ans) | 24h |
| `ville_epreuves.php` | Epreuves par ville | 24h |
| `competitions.php` | Liste competitions | 24h |
| `classement.php` | Classement temps reel par epreuve | **0** |
| `stats.php` | Stats globales (`?detail=1&top=N`) | 24h |
| `top_searched.php` | Top consultes (clubs/athletes, 1j/7j/30j/1an) | 10min |
| `similar.php` | Profils similaires (algo bareme FFA) | 24h |
| `composition.php` | Composition equipes/clubs | 24h |
| `quota.php` | Statut rate limiting (badge nav) | 0 |
| `athlete_socials.php` | Reseaux sociaux athlete | 0 |

### 3.3 Endpoints utilisateurs

| Endpoint | Methode | But |
|----------|---------|-----|
| `auth/login.php` | POST | Login email+mdp OU credentials super admin |
| `auth/register.php` | POST | Inscription manuelle |
| `auth/logout.php` | POST | Deconnexion |
| `auth/me.php` | GET | Statut auth (cookie `bk_token`) |
| `auth/google_login.php` | GET | Init OAuth Google (genere state CSRF) |
| `auth/google_callback.php` | GET | Echange code Google -> creation/lien user |
| `auth/forgot_password.php` | POST | Demande reset mdp |
| `auth/reset_password.php` | POST | Reset mdp via token |
| `auth/verify_email.php` | GET | Verification email |
| `auth/confirm_hide.php` | GET | Confirme retrait profil (token 48h) |
| `auth/confirm_contact.php` | GET | Confirme envoi message contact (token 24h) |

### 3.4 Endpoints actions

| Endpoint | But |
|----------|-----|
| `contact.php` | POST = demande envoi (email confirmation) / GET admin (mark_read, delete, unban_ip) |
| `report.php` | POST = signalement profil / GET admin (hide/show/resolve/delete) |
| `follow.php` | POST = toggle follow athlete/club / GET = check status |
| `subscribe.php` | POST = collecte email newsletter/PDF |
| `performances.php` | CRUD perfs manuelles (auth requise) |
| `log.php` | POST batch BDD / GET lecture (admin) |
| `search_track.php` | POST = tracking recherche/consultation (sendBeacon) |
| `send_profile_email.php` | Envoi profil par email |

### 3.5 Endpoint cle : `search.php`

**12 filtres combinables** : `nom`, `nom1`, `nom2`, `club`, `categorie`, `sexe`, `nationalite`, `epreuve`, `ville`, `competition`, `medaille`, `annee`, `licence`, `page`, `limit`

**Logique** :
- Au moins 1 filtre requis
- Recherche multi-mots ordre libre (`preg_split('/\s+/')` -> `LIKE %mot%` AND chained)
- Exclut clubs >5000 athletes (sauf filtre club explicite)
- Batch queries pour eviter N+1
- Detection temps vs distance pour tri

**Rate limiting** :
- Anonymes : `BK_SEARCH_LIMIT_ANON = 100/jour`
- Connectes : `BK_SEARCH_LIMIT_LOGGED = 500/jour`
- Super admin : illimite
- Whitelist : Google, Hostinger, localhost

Reponse contient `search_used` et `search_limit` pour mise a jour badge nav temps reel.

### 3.6 Endpoint cle : `club_stats.php`

**Parametres** :
| Param | Description |
|-------|-------------|
| `id` ou `nom` | Identification club |
| `annee` | Filtre annee |
| `rp`/`ep` | Pages records (10/page) / epreuves (50/page) |
| `nationalite`/`sexe`/`categorie` | Filtres athletes -> `$athFilter` |
| `nat_detail` | Comparaison detaillee |
| `perso` | Mode records personnels (relache filtre periode) |
| `nocache` | Bypass cache |

**Filtre universel `$athFilter`** : subquery appliquee aux ~30 requetes SQL :
```php
$athFilter = " AND ac.id_athlete IN (
    SELECT _af.id_athlete FROM athletes _af
    WHERE _af.nationalite_athlete = '...' AND ...
)";
```

**Verification membership par periode** via variables `$mcRec`, `$mcRes`, `$mcMed` etc. (annee_debut/annee_fin sur `athlete_clubs`).

**UNION records + progressions** pour epreuves : `athlete_records` ne contient qu'1 record perso, donc UNION avec `athlete_progressions` (qui a `id_club` direct) pour donnees completes.

**Retour** : `{ club, total_athletes, par_sexe, par_categorie, nationalites, medailles, podiums, selections, epreuves[], records[], top_athletes, top_villes, niveaux, niveaux_par_annee, progressions, resultats_par_annee, annees_disponibles, ... }`

### 3.7 Endpoint cle : `top_searched.php`

**Parametres** : `type` (clubs/athletes), `limit` (max 50), `days` (1/7/30/365)

**Logique** :
- Lit `search_tracking` : `COUNT(DISTINCT ip)` comme vues
- Filtre `created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)`
- 2 sources fusionnees : `entity_name` (tracking serveur) + `query_text` (live search)
- Enrichissement : athletes (sexe, cat, nat, club), clubs (nb_athletes)

**Cache 10min** : `topsearched_{type}_{limit}_{days}d.json`

**Reset** : `?reset=athletes|clubs|all&bk_key=...`

---

## 4. BACKEND — AUTHENTIFICATION

### 4.1 Connexion uniquement via Google OAuth (utilisateurs)
- Plus de formulaire email+mdp pour les utilisateurs
- Bouton "Se connecter avec Google" sur `login.php` et `register.php`

### 4.2 Flux Google OAuth 2.0
```
login.php
    | clic "Se connecter avec Google"
    v
api/auth/google_login.php
    | genere state CSRF en session
    | redirect vers Google avec client_id + state
    v
Google (consentement utilisateur)
    | redirect vers callback avec code + state
    v
api/auth/google_callback.php
    | verifie state CSRF
    | echange code contre access_token (POST cote serveur)
    | recupere profil Google (email, nom, google_id)
    | si email existe en BDD : lie google_id au compte existant
    | sinon : cree user (role=athlete, oauth_provider=google)
    | createSession() -> cookie bk_token (30j)
    v
index.php (connecte)
```

### 4.3 Configuration OAuth (`core/oauth_config.php`)
- Client ID + secret Google
- Detection auto local/prod pour redirect URI
- Extensible Facebook/Instagram (constantes commentees)

### 4.4 Sessions
- Token : 64 chars hex generes via `bin2hex(random_bytes(32))`
- Cookie : `bk_token` (httpOnly, sameSite=Lax, TTL 30j)
- Stockage : table `user_sessions` (id_user, token, expire_at)
- Validation : `getCurrentUser()` dans `core/auth.php`

### 4.5 Roles
- `athlete` (defaut)
- `coach`
- `club`
- `admin`

Verification : `requireAuth()`, `requireRole('admin')`.

### 4.6 Super Admin (separe)
- Login via `login.php` (input type="text") en utilisant credentials BDD
- Detection : `api/auth/login.php` compare email/password avec `core/credentials.php`
- Cookie : `bk_sa_token` (7 jours)
- Stockage : `logs/.sa_sessions.php` (JSON proteges par die())
- Acces : `admin/panel.php` (16 sections)
- Rate limit login : 5 tentatives/jour par IP, blocage 24h apres 5 echecs
- Whitelist illimitee : Google, Hostinger, localhost

---

## 5. BACKEND — SECURITE, RATE LIMITING, LOGGING

### 5.1 Rate limiting (3 niveaux)

#### Recherches (`api/search.php`)
- Anonymes : 100/jour
- Connectes (`bk_token`) : 500/jour
- Super admin (`bk_sa_token`) : illimite
- Stockage : `logs/.search_limits.php` (JSON die())
- Cle compteur = IP directe (meme cle, limite differente)
- Reponse 429 si limite atteinte avec `{ limit_reached: true, limit, logged }`
- Badge nav `<span id="searchQuota">N/500` ou `N/100`, **dore clignotant** (`#ffd700`, animation `bkGoldBlink`), rouge rapide si > 80%

#### Login admin
- 5 tentatives/jour par IP (`logs/.admin_attempts.php`)
- Blocage 24h apres 5 echecs
- Whitelist : Google (66.249, 66.102, 64.233, 72.14, 74.125, 209.85, 216.239, 35., 34.), Hostinger (153.92, 31.170, 185.201), localhost

#### Anti-scraping (visiteurs anonymes)
- 10 pages/jour max
- Compteur : `logs/.page_limits.php`
- Apres 10 pages -> redirection `login.php?limit=1`
- Implementation : IIFE anonyme dans `index.php` apres `logIp()`
- Whitelist : Google, Hostinger, localhost
- Connectes : illimite

### 5.2 Logging — 2 systemes

#### Logging BDD (JS -> MySQL)
- API : `api/log.php` (POST batch, GET lecture filtree)
- Stockage : table `logs` MySQL
- JS : batch toutes les 2s + sendBeacon au `beforeunload`
- Donnees : IP (CloudFlare), UA, sid, **uid (id_user)**, page, action, detail, screen, langue, referrer, duree
- `uid` rempli grace a `config.php` qui inclut `auth.php` -> `getCurrentUser()`
- Visualisation : `admin/logs.php` (acces restreint a `luvumbu.n@gmail.com`)

#### Logging IP universel (PHP -> fichier JSON)
- Moteur : `core/ip_logger.php` (`logIp()`)
- Stockage : `logs/ip_track_YYYY-MM.php` (JSON proteges par `<?php die(); ?>`)
- Rotation mensuelle automatique
- Donnees IP : count, first/last, pages, dernieres 100 requetes, UA
- Donnees globales : total_visits, unique_ips, daily, dernieres 500 requetes
- Viewer : `logs/ip_view.php` (auth email whitelist)

### 5.3 Securite generale
- Mots de passe : `password_hash()` BCRYPT
- Sessions : tokens 64 chars hex aleatoires
- Cookie : httpOnly, sameSite=Lax
- Prepared statements pour mutations
- `real_escape_string()` pour lectures
- Dossiers `cache/` et `logs/` proteges (`.htaccess` Deny from all)
- Fichiers JSON sensibles : `<?php die(); ?>` en debut

### 5.4 Cle API
- `BK_API_KEY = 'bk_s3cr3t_2026_xK9mP'` dans `api/config.php`
- Param URL `?bk_key=...` ou header `X-BK-KEY`
- Sert a : reset vues, bypass cache
- **Pas de restriction IP** : ouverte a toutes les IPs

---

## 6. FRONTEND — Structure de index.php

### 6.1 Reperes de lignes

| Section | Lignes | Contenu |
|---------|--------|---------|
| PHP utils + apiCall() | 1-55 | Fonctions de base |
| SEO dynamique | 137-250 | Titles, OG, Twitter, JSON-LD |
| Anti-flash theme + bouton flottant | 628-760 | (voir section 8) |
| JS theme | 940-1010 | bkSetTheme, bkApplyTheme, etc. |
| Navigation HTML | 1180-1250 | Liens nav avec active class |
| Page Accueil | 475-810 | Stats, podium 3D, top consultes |
| Page Athletes | 810-1027 | Top 100 IA/IB |
| Page Recherche | 1027-1540 | 12 filtres + barre live |
| Page Profil | 1540-2510 | Header, records, niveaux, bio |
| Page Clubs | 2510-2625 | Liste clubs |
| Page Epreuves | 2625-2710 | Liste epreuves |
| Page Villes | 2710-4120 | Detail ville (PHP serveur) |
| Page Comparer | 4120-4250 | Panier + URLs partageables |
| Page Tuto | 4250-4575 | 8 etapes IntersectionObserver |
| Page Contact | 10515-10580 | Page dediee + bloc retrait |
| Panneau Epreuve overlay | 4580-4700 | HTML + tabs |
| Panneau Club overlay | 4700-4800 | HTML + tabs |
| JavaScript global | 4800-fin | ~375 fonctions |

### 6.2 11 pages principales

| Page | URL | Description |
|------|-----|-------------|
| Accueil | `?page=accueil` | Stats globales, podium 3D, top consultes |
| Athletes | `?page=athletes` | Top 100 IA/IB sur 7 epreuves |
| Recherche | `?page=recherche` | 12 filtres + select nationalites BDD |
| Profil | `?page=profil&id=X` | Fiche complete + bio auto + tout cliquable |
| Clubs | `?page=clubs` | Liste + panneau detail 5 onglets |
| Epreuves | `?page=epreuves` | Liste + panneau detail 4 onglets |
| Villes | `?page=villes&open=X` | Liste + detail (filtres niv/nat/ans) |
| Comparer | `?page=comparer&ids=X,Y` | Panier localStorage ou URLs partageables |
| Tuto | `?page=tuto` | 8 sections animees |
| Contact | `?page=contact` | Page contact dediee |
| Mon Espace | `?page=espace` | Athletes/clubs suivis, historique recherches |

---

## 7. FRONTEND — Pages et composants

### 7.1 Page Accueil (refonte 2026-04-13)
- 4 stat cards pleine largeur (Athletes, Clubs, Epreuves, Villes)
- **Stade 3D CSS** : piste rectangle + 2 demi-cercles, terrain foot, 8 couloirs, tribunes violettes, rotation 90s/tour (`@keyframes stadeRotation`)
- **Top Clubs Consultes / Top Athletes Consultes** : 4 onglets periode (Jour/Semaine/Mois/Annee), pagination 10/page max 5, donnees `top_searched.php`
- **Podium 3D Three.js** : 3 marches or/argent/bronze + medailles flottantes + sol piste rouge + particules dorees (charge via CDN `three@0.160.0`)
- Auto-refresh top consultes toutes les 60s

**Elements supprimes** (vs ancienne version) : graphique sexe doughnut, top 10 clubs/epreuves bar charts, athletes aleatoires, top villes, top epreuves, cartes Resultats/Records, colonne Niveaux dans tableaux, lien Epreuves dans nav.

### 7.2 Page Athletes (refonte 2026-04-09)
- Top 100 niveau **IA/IB** uniquement
- Epreuves : 100m, 200m, 400m Haies, 110m Haies, Longueur, Triple saut, Perche
- Pas de pagination, pas de tri, pas de search box, pas de graphiques
- Layout cards (`.ath-grid`)
- Bouton "Rechercher un athlete" -> `?page=recherche`
- **API** : `liste.php?niveau=IA|IB&epreuve=...&limit=200`

### 7.3 Page Profil
- Header : sexe/categorie/nationalite cliquables -> recherche
- Lieu naissance -> `?page=villes&open=X`
- Clubs cliquables (attention : `rtrim($club, '* ')` car `*` retourne par API)
- Records / Progressions / Niveaux : tout cliquable
- **Bio auto-generee** (`buildAthleteBio`, ~500 lignes JS) : 18 paragraphes conditionnels, filtrable par annees via checkboxes
- **Donnees masquees** : date_naissance, annee_naissance, licence retournent null dans l'API (conservees en BDD)
- ID athle.fr : lien cliquable vers athle.fr/athletes/{id}/bilans
- **Disclaimer popup** : 2s 1ere visite, immediat au refresh, `localStorage.bk_profil_disclaimer_ok_v3`

### 7.4 Panneau detail club (composant JS reutilisable)
Present sur 3 pages (Accueil, Clubs, Recherche) avec systeme de suffixes (`''`, `'Accueil'`).

**Fonctions cles** :
- `_openClubPanel(url, suffix)` / `_closeClubPanel(suffix)`
- `_fillClubPanel(data, suffix)` / `_renderClubTab(tab, suffix)`
- `loadClubRecPage(page, suffix)` / `loadClubEpPage(page, suffix)`
- `_clubFilterParams(d)` -> retourne `&nationalite=X&sexe=Y&categorie=Z`

**5 onglets** : epreuves (50/page), nationalites (charts cliquables), records (10/page), stats (charts sexe/cat/evo), resume (Global/Annee/Comparer)

**Filtres avances onglet Epreuves** :
1. Mode : Records club / Records personnels (server-side `?perso=1`)
2. Discipline : client-side multi-select
3. Niveaux : client-side multi-select D1-D8/R1-R6/N1-N4/IE/IR
4. Annee : server-side `?annee=`, mode Filtrer ou Comparer (2-5 annees)

**Mode Comparer annees** : fetch parallele, tableau comparatif + chart barres + top epreuves + resume textuel auto-genere (`_buildEpYearCmpHTML`)

**Barre de recherche athletes interne** : input dans chaque panneau, debounce 350ms, `search.php?club=X&nom=Q&limit=50`

### 7.5 Panneau detail epreuve
**Fonctions** : `openEpreuveDetail(nom)`, `closeEpreuveDetail()`, `switchEpreuveTab(tab)`, `loadEpreuveRecPage(page)`, `_renderEpreuveTab(tab)`

**4 onglets** : records (50/page), nationalites, stats (sexe/cat/med/pod/sel/clubs/villes/evo), resume

**API** : `epreuve_stats.php`

### 7.6 Page Comparer (URLs partageables)
- localStorage : `bk_cmp_athletes` / `bk_cmp_clubs` (panier)
- URL athletes : `?page=comparer&ids=548525,2643370`
- URL licences : `?page=comparer&licences=131980,1586918`
- URL clubs : `?page=comparer&clubs=ES%20MASSY,BORDEAUX`
- Mix : `?page=comparer&ids=X&licences=Y&clubs=Z`
- **Auto-compare** : si URL params, charge donnees + lance comparaison auto
- Bouton "Copier le lien" genere URL avec selection
- **Priorite** : URL params > localStorage

### 7.7 Page Mon Espace (`?page=espace`)
- Athletes suivis (table `athlete_follows`)
- Clubs suivis (table `club_follows`)
- Historique recherches
- Boutons unfollow / effacer historique

### 7.8 Modal Signaler (sur profil)
- 5 motifs : retrait, donnees_incorrectes, usurpation, vie_privee, autre
- Email obligatoire pour motif "retrait"
- Hint rouge dynamique : "L'email est obligatoire pour ce motif. Un seul clic et votre profil sera masque."
- Confirmation : bloc encadre violet "Verifiez votre boite mail"

### 7.9 Pattern 3-tables (CONVENTION OBLIGATOIRE)
Toutes les `bk-table` doivent utiliser :
```html
<div class="table-wrap">
  <table class="bk-table"><tr><th>...</th></tr></table>  <!-- header -->
  <table class="bk-table"><!-- data rows --></table>     <!-- data -->
  <table class="bk-table"><tr><th>...</th></tr></table>  <!-- footer -->
</div>
```
En JS, stocker le TH dans `var thRow` et le reutiliser 2 fois.

### 7.10 Systeme de niveaux (couleurs et hierarchie)

| Famille | Codes | Couleur | Hex |
|---------|-------|---------|-----|
| Departemental | D1-D8 | Orange | bg:#f9731620 / border:#f97316 / text:#fb923c |
| Regional | R1-R6 | Cyan | bg:#0891b220 / border:#0891b2 / text:#22d3ee |
| National | N1-N4 | Rose | bg:#e11d4820 / border:#e11d48 / text:#fb7185 |
| International | IE, IR | Fuchsia | bg:#c026d320 / border:#c026d3 / text:#e879f9 |

**Hierarchie** : IE(100) > IR(99) > N1(90)..N4(86) > R1(80)..R6(75) > D1(70)..D8(63)

**JS** : `_nivBadge(code)`, `_nivBadges(arr)`, `_highestNiveau(arr)`

---

## 8. FRONTEND — Systeme de theme (clair / sombre / auto)

### 8.1 Architecture
- Variables CSS sur `:root` (couleurs, spacing, radius, shadows, transitions)
- `body.p2-light` reassigne les variables -> tous les composants suivent automatiquement

### 8.2 3 modes (depuis 2026-04-30)
- `light` : papier creme `#f4ede0` + brand cognac `#6b4f2c` + texte noir
- `dark` : fond `#0d1117` + brand violet + texte clair (par defaut historique)
- `auto` : sombre **21h-6h**, clair sinon (par defaut nouveaux visiteurs)

### 8.3 Stockage localStorage
- `bk_theme_mode` = `'light'` | `'dark'` | `'auto'`
- `bk_p2_light` = `'1'` (light) | `'0'` (dark) — legacy, tenu en sync pour compat

### 8.4 Bouton flottant + menu
- Bouton `<button id="bkLightFloat">` fixed bottom-right (44x44, rond, icone soleil/lune)
- Click ouvre menu deroulant `<div id="bkThemeMenu">` avec 3 options
- Click exterieur ferme le menu (event listener document)
- Mode actif surligne dans le menu (`is-active`)

### 8.5 Anti-flash (3 emplacements identiques)
```javascript
(function(){ try {
    var mode = localStorage.getItem('bk_theme_mode');
    if (mode === null) {
        var legacy = localStorage.getItem('bk_p2_light');
        mode = (legacy === null) ? 'auto' : (legacy === '1' ? 'light' : 'dark');
    }
    var isLight;
    if (mode === 'auto') {
        var h = new Date().getHours();
        isLight = !(h >= 21 || h < 6);
    } else {
        isLight = (mode === 'light');
    }
    if (isLight) document.body.classList.add('p2-light');
} catch(e){} })();
```

Emplacements : `index.php:630-644`, `index.php:3834-3848`, `index.php:5900-5915`.

### 8.6 Re-application periodique (mode auto)
- `setInterval(..., 60000)` toutes les 60s
- `document.addEventListener('visibilitychange', ...)` au retour de l'onglet
- Synchronise les boutons `.p2-light-toggle` (`is-on`)

### 8.7 Per-page profile toggle
`p2ToggleLight(btn)` (line 5920) : toggle direct light/dark, sort du mode auto explicitement.

---

## 9. SCRAPING — Pipeline complet

### 9.1 Vue d'ensemble
```
nom_et_liens (table BDD avec URLs athle.fr)
    | scraping/scraper.php (orchestrateur, batch 7, 25s/cycle)
    v
scrapeParallel($athleteIds)
    | scraping/scrape_functions.php
    | curl_multi : 7 athletes x 3 pages = 21 req parallel
    | URLs : /bilans, /records, /selections
    | timeout 15s, UA Mozilla
    v
{athleteId => {bilans: html, records: html, selections: html}}
    | foreach athleteId
    v
new AthleteScraper($id)
    | Class/AthleteScraper.php (56 KB)
    | parsing HTML -> donnees structurees
    v
Donnees structurees (identite, clubs, medailles, etc.)
    | json_encode -> src/{id}.php (JSON avec headers PHP)
    | insertAthleteData($scraper, $conn, $cache)
    v
core/insert_athle.php
    | loadRefCache : 6 tables ref en memoire (0 SELECT repetitif)
    | cachedGetOrInsertId : INSERT IGNORE + lookup
    | DELETE CASCADE enfants si athlete existe
    v
9 tables MySQL renseignees
```

### 9.2 Performances
- Sequentiel : 1 athlete = ~2s -> 600k pages = ~17 jours
- **Parallele curl_multi** : 7 athletes (21 pages) en ~3s -> ~3.5 jours
- Cache memoire ref tables : evite 6 SELECT par athlete

### 9.3 Fichiers et roles

| Fichier | Role |
|---------|------|
| `scraping/scrape_functions.php` | Telechargement parallele curl_multi |
| `Class/AthleteScraper.php` | Parsing HTML athle.fr -> donnees |
| `core/insert_athle.php` | Insertion BDD optimisee |
| `core/dbCheck_athle.php` | Schema BDD (creation tables + FK) |
| `scraping/scraper.php` | Orchestrateur principal |
| `scraping/import_bdd.php` | Import fichiers `src/*.php` -> BDD |
| `scraping/check_sync.php` | Verification + scraping des absents (2 phases) |
| `scraping/check_athletes.php` | Audit completude `athletes` vs `src/` |

---

## 10. SCRAPING — AthleteScraper et insertion BDD

### 10.1 AthleteScraper (`Class/AthleteScraper.php`)
**Constructeur** : `new AthleteScraper($id)` accepte ID entier ou URL complete.

**Proprietes publiques** (apres extraction) :
- `$identite` (nom, prenom, sexe, date naissance, nationalite, club actuel, taille, poids, licence)
- `$clubs[]` (annee_debut, annee_fin, club)
- `$medailles[]`
- `$selections[]`
- `$progressions[]`
- `$records[]`
- `$podiums[]`
- `$resultats[]`
- `$niveaux[]` + niveau_perfs[]

**Methodes d'extraction** (parsing HTML, regex + DOMDocument) :
- `extractIdentite()`
- `extractClubs()`
- `extractMedailles()`
- `extractSelections()`
- `extractProgressions()`
- `extractRecords()`
- `extractPodiums()`
- `extractResultats()`
- `extractNiveaux()`

**Methodes export** :
- `toArray()` -> tableau associatif
- `scrapeAll()` -> tout-en-un (fetch + extract)

### 10.2 Methodes statiques utilitaires

#### `performanceToInt($perf)` — Conversion perf brut -> entier
Convertit perf texte en centiemes (temps) ou centimetres (distances). 7 patterns dans l'ordre :

| Pattern | Exemple | Resultat |
|---------|---------|----------|
| `Xh Y'ZZ''CC` | `1h23'45''12` | `(1*3600+23*60+45)*100+12` |
| `X'YY''CC` | `3'43''65` | `(3*60+43)*100+65` |
| `X'YY` | `12'09` | `(12*60+9)*100` |
| `XX''CC` | `10''48` | `10*100+48 = 1048` |
| `XmYY` | `6m30` | `6*100+30 = 630` |
| `X.YY` | `7.34` | `7*100+34 = 734` |
| `XXXX` | `734` | `734*100 = 73400` |

**ATTENTION — Padding dixiemes** : quand 1 seul chiffre apres `''`, `m`, ou `.`, c'est un dixieme. `str_pad($digit, 2, '0', STR_PAD_RIGHT)` : `'9'` -> `'90'`.

**Sans padding (BUG)** : `10''9` -> 1009 (10.09s, faux)
**Avec padding (CORRECT)** : `10''9` -> 1090 (10.9s)

Script de correction : `admin/fix_perf_int.php` (dry run sans param, `?go` execute correction SQL).

#### `splitNomPrenom($nom)`
Heuristique majuscules pour separer nom/prenom (athle.fr stocke "DUPONT Jean Pierre").

#### `getCategorieCode($anneeNaissance, $anneeSaison)`
Calcule le code FFA selon l'age (EA, PO, BE, MI, CA, JU, ES, SE, V1-V4).

### 10.3 insertAthleteData (`core/insert_athle.php`)

**Fonction** : `insertAthleteData($scraper, $conn, &$cache)`

**Strategie** :
1. `loadRefCache($conn)` une seule fois : charge 6 tables ref (villes, clubs, epreuves, competitions, categories, nationalites) en memoire
2. `cachedGetOrInsertId(&$cache, $conn, ...)` : lookup cache -> INSERT IGNORE -> SELECT (0 query si cache hit)
3. Insertion 9 sections (athletes, athlete_clubs, athlete_medailles, ..., athlete_niveaux + athlete_niv_perfs)
4. Si `athlete_id_externe` existe : DELETE CASCADE enfants -> re-INSERT tout
5. Batch INSERT : 1 query par section (multi-rows VALUES)

**Optimisation** : 6 SELECT vers ref tables economises par athlete. Sur 300k athletes : 1.8M queries economisees.

---

## 11. SCRAPING — Orchestrateur et controle

### 11.1 Orchestrateur principal (`scraping/scraper.php`)
- **URL** : `https://bokonzi.com/scraping/scraper.php`
- **Constantes** : `$TIME_LIMIT = 25s`, `$PARALLEL = 7`
- **Auto-refresh** : `header("Refresh: 1")` -> nouveau cycle apres 25s

### 11.2 Controle Start/Stop (flag fichier)
- **Bouton DEMARRER** : cree `scraping_running.flag` -> boucle tourne
- **Bouton ARRETER** : supprime le flag -> arret propre au prochain cycle
- **Flag persistant** : survit a fermeture navigateur, scraping reprend si on revient
- **Fin auto** : flag supprime quand tous les athletes traites

### 11.3 Workflow par cycle (25s max)
1. Charge URLs depuis `nom_et_liens` -> cache `urls_cache.json`
2. Charge tous les `athlete_id_externe` deja en BDD -> `$existingAthletes[]` (skip)
3. Boucle batch : collecte 7 athletes non-existants
4. `scrapeParallel()` -> telecharge 21 pages parallel
5. Pour chaque : `AthleteScraper` -> extraction -> JSON `src/{id}.php` -> BDD
6. Echecs -> `failed.json`
7. Progression -> `progress.txt` + session
8. Verifie flag -> reload ou stop

### 11.4 Bouton reset
- `?reset_to=N` : reprend a un numero choisi

### 11.5 Mode test manuel
- `?test_url=ID` ou `?test_url=URL` : scrape 1 athlete (test isole)
- `&skip_bdd` : test sans insertion
- `&force` : re-insertion meme si deja en BDD

### 11.6 Fichiers de progression et logs

| Fichier | Contenu |
|---------|---------|
| `progress.txt` | ID courant scraper principal |
| `progress_absents.txt` | ID courant check_sync phase 2 |
| `import_progress.txt` | Index fichier import_bdd |
| `urls_cache.json` | Cache table `nom_et_liens` |
| `failed.json` | Athletes echoues (scraper principal) |
| `failed_absents.json` | Athletes echoues (check_sync) |
| `absents.json` | Fichiers `src/` manquants |
| `absents2.json` | URLs manquantes |
| `src/{id}.php` | JSON athlete avec headers PHP |

### 11.7 Admin remote (`admin/remote_check.php`)
- URL : `bokonzi.com/admin/remote_check.php?bk_key=...`
- `?action=scrape_status` : total_urls, total_bdd, restants, pct
- `?action=test_scrape&id=123` : scrape 1 test (+ skip_bdd, force)
- `?action=count` : compteurs toutes tables
- `?action=columns&table=X` : schema d'une table

---

## 12. ADMIN — Panel et outils

### 12.1 Acces
- URL : `admin/panel.php`
- Auth : cookie `bk_sa_token` (super admin) OU credentials BDD
- Login : `login.php` (input type="text") -> compare avec `core/credentials.php`

### 12.2 16 sections du panel
1. **Overview** — Compteurs toutes tables
2. **Requetes temps reel** — Logs en direct
3. **Logs BDD** — 30 derniers, URLs cliquables
4. **Activite horaire** — Distribution 0-23h
5. **Top IPs** — Plus actives
6. **Users** — Gestion comptes
7-10. **(stats diverses)**
11. **Actions rapides** — Reset vues athletes/clubs
12. **Analytics Vues** — Tabs, charts, tri, filtre, drawer
13. **IPs bloquees & bannies** — Bouton debannir
14. **Messages contact** — Lu / non-lu / supprimer / tout marquer lu (alerte violette pulsante si non lu)
15. **Search Tracking** — 8 KPI + chart 14j + 7 tabs interactifs + reset + IPs ignorees
16. **Courrier non confirme** (rouge) — Tokens `contact_confirm_tokens` not used + badge EXPIRE
17. **Profils comportementaux** — Users >1 connexion, phrase auto-generee, detection self-profile (badge fuchsia)

### 12.3 Section 15 — Search Tracking (detaille)
- **8 KPI** : total, aujourd'hui, 7j, 30j, IPs uniques, taux succes, IPs ignorees, derniere
- **Chart 14j** : Chart.js bar stacked, 5 datasets (athlete/club/epreuve/ville/general)
- **7 onglets** :
  1. Recherches : top 50 queries
  2. Athletes : TOUTES les entrees type=athlete (sans limite)
  3. Clubs : TOUTES les entrees type=club
  4. Entites : top 50 epreuves/villes
  5. IPs : toutes avec count + bouton "Ignorer"
  6. Horaire : Chart.js bar horizontal
  7. Sources : Chart.js doughnut (live_search/page_view/panel_open)
- **Reset** : athletes / clubs / tout (confirm JS)

### 12.4 Outils admin

| Fichier | Role |
|---------|------|
| `admin/panel.php` | Dashboard 16 sections |
| `admin/setup_bdd.php` | Creation BDD + tables |
| `admin/clear_cache.php` | Vider cache (`?prefix=` cible) |
| `admin/cache_urls.php` | Pre-generation cache |
| `admin/fix_perf_int.php` | Correction INT perfs (`?go` execute) |
| `admin/logs.php` | Visualisation logs (acces restreint) |
| `admin/remote_check.php` | API JSON admin distant |
| `admin/test_mail.php` | Test envoi mail |
| `admin/debug_login.php` | Debug auth |

### 12.5 Fichiers dangereux SUPPRIMES
- `admin/drop_all.php` — supprimait toutes les tables
- `admin/reset.php` — truncate toutes les donnees

---

## 13. CACHE SYSTEME

### 13.1 Mecanisme
1. Cle MD5 a partir de tous les parametres
2. Verifie `cache/[prefix]_[md5].json` < 24h
3. Si oui : retourne cache directement
4. Sinon : execute SQL, encode JSON, ecrit fichier, retourne

### 13.2 Prefixes et TTL

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
| `liste_` | liste.php | 24h (7j random) |
| `topsearched_` | top_searched.php | **10min** |

### 13.3 Invalidation
- **Tout** : `https://bokonzi.com/admin/clear_cache.php`
- **Cible** : `?prefix=clubstats`
- **Bypass** : `?nocache=1`
- **APRES VIDAGE** : appeler `stats.php?detail=1&top=30` pour regenerer `stats_detail_30.json` (sinon top clubs/athletes vide sur accueil)

---

## 14. SEO & MONETISATION

### 14.1 SEO
- **Titres dynamiques** par page (index.php:137-170)
- **Canonical URLs** : `<link rel="canonical">` chaque page
- **Open Graph** : og:title, og:description, og:url, og:image, og:locale
- **Twitter Cards** : `summary_large_image`
- **JSON-LD** : WebSite + SportsOrganization + BreadcrumbList (index), Person + BreadcrumbList (seo.php profils)
- **H1** : "Base de Donnees Athletisme Francais — Athletes, Clubs, Records"
- **Sitemap dynamique** : `sitemap.php` (toutes pages + clubs + epreuves + villes + athletes pagines 500/page)
- **OG Image** : `generate_og_image.html` (canvas 1200x630)
- **noindex** : pages comparer, tuto, profil 404
- **Footer SEO** : liens internes vers toutes pages principales

### 14.2 Monetisation
- **GTM** : Google Tag Manager (GTM-KPNTVXDF) dans `<head>`
- **AdSense** : ca-pub-7899923856846249 (script + meta google-adsense-account)
- **ads.txt** : a la racine

### 14.3 Disclaimer legal (2026-04-13)
- **Bandeau accueil** : encadre orange `#f59e0b` sous H1, lien "En savoir plus" vers `#footerDisclaimer`
- **Popup page Athletes** : 2s 1ere visite, immediat refresh, 2 boutons (J'ai lu / Ne plus afficher)
- **Popup page Profil** : meme comportement, `localStorage.bk_profil_disclaimer_ok_v3`
- **Footer texte legal complet** : 7 paragraphes (independance, sources publiques, droit retrait, signalement, etc.)
- **Reset visiteurs** : changer suffixe `_v3` -> `_v4` pour forcer

---

## 15. PROFIL MASQUE & SIGNALEMENT

### 15.1 Colonne visibilite
`athletes.visible` TINYINT default 1. Si 0 -> profil masque publiquement.

### 15.2 API report.php
**POST** : signalement `{ athlete_id_ext, athlete_name, reason, message, email }`
**GET admin** (cookie super admin) :
- `?hide_athlete=ID` -> visible=0 + vider cache
- `?show_athlete=ID` -> visible=1
- `?mark_read=ID` / `?resolve=ID` / `?delete=ID`

### 15.3 5 motifs
- `retrait` : self-removal flow declenche si email fourni
- `donnees_incorrectes`
- `usurpation`
- `vie_privee`
- `autre`

### 15.4 Self-removal par email (motif "retrait")
1. User choisit motif "retrait" + email
2. Email confirmation envoye (bouton "Oui, masquer mon profil")
3. **Au clic** : `api/auth/confirm_hide.php?token=X` -> `visible=0` + vider cache + page succes verte
4. **Sans clic** : signalement enregistre, traite manuellement (1-30 jours)

**Anti-abus** : 1 email = 1 profil masque max (tout temps confondus). Si deja utilise -> signalement enregistre normalement mais pas de lien envoye (silencieux).

**Rate limit** : 1 demande par email+athlete par 24h, 3 signalements/jour par IP.

**Token** : `profile_hide_tokens` (athlete_id_ext, email, token 64 chars, used, expires 48h).

### 15.5 Comportement profil masque (visible=0)
- **Non-connectes** : page "Ce profil n'est plus disponible" (icone + message + lien retour)
- **Admin** (`bk_sa_token`) : profil visible avec bandeau rouge "Profil masque — Inaccessible publiquement"
- **API athlete.php** : retourne `visible: false`. Admin force avec `?_all=1`
- **2 pages profil** : `index.php?page=profil&id=X` (id externe) et `pages/profil.php?id=X` (id interne) — les deux respectent visible
- **Cache** : vide automatiquement apres changement (glob `athlete_*.json` + strpos sur contenu)

### 15.6 Message rouge "Retirer son profil soi-meme" (2026-04-30)
Bloc rouge prominent (2-3px border, font-weight 800, uppercase) present a 6 endroits :

| Emplacement | Quand visible |
|---|---|
| Footer index.php — section "Signaler un profil" | Toujours visible |
| Footer formulaire contact (footerContactForm) | Pas de bloc (doublon retire 2026-04-30) |
| Overlay contact (ovContactWrap) | A l'ouverture du formulaire |
| `pages/profil.php` formulaire contact (pubContactForm) | A l'ouverture du formulaire |
| `core/ip_logger.php` formulaire contact | A l'ouverture du formulaire |
| Page `?page=contact` (haut, avant le formulaire) | Toujours visible (3px border, 22px) |

**Liste 5 etapes** :
1. Allez sur votre profil d'athlete
2. Cliquez sur **Signaler ce profil**
3. Motif : **Je souhaite retirer mon profil**
4. Indiquez votre email
5. Profil masque **immediatement** (vert)

---

## 16. SEARCH TRACKING

### 16.1 Table `search_tracking`
Colonnes : `id_search PK, ip, query_text, search_type ENUM(athlete/club/epreuve/ville/general), source ENUM(live_search/page_view/panel_open), entity_id, entity_name, result_count, page, created_at`

6 index : `idx_st_type, idx_st_source, idx_st_created, idx_st_ip, idx_st_entity, idx_st_query`

### 16.2 Sources
1. **JS sendBeacon** -> `api/search_track.php`
   - `liveSearch()` : debounce 2s apres derniere frappe
   - `_openClubPanel()` : type=club, source=panel_open
   - `openEpreuveDetail()` : type=epreuve, source=panel_open
   - Helper JS : `_trackSearch(params)` + `_trackTimer`
2. **PHP INSERT direct** dans `index.php`
   - Page profil athlete : INSERT type=athlete, source=page_view
   - Page recherche `?club=X` : INSERT type=club, source=page_view

### 16.3 API search_track.php
- POST : JSON body `{ q, type, source, entity_id, entity_name, results, pg }`
- Fallback `$_POST`
- IP CloudFlare/proxy
- **Nettoyage probabiliste** : 1% chance de DELETE > 90 jours
- Retour `{ ok: true }` (leger pour sendBeacon)

### 16.4 IPs ignorees
- Fichier : `logs/.st_ignored_ips.php` (`<?php die(); ?>` + JSON)
- Structure : `{"1.2.3.4": {"added":"2026-03-02","label":"Mon IP"}, ...}`
- Impact : queries `WHERE ip NOT IN (...)`
- Actions panel : `ignore_ip` / `unignore_ip` / `reset_tracking`

---

## 17. SYSTEME DE CONTACT (avec confirmation)

### 17.1 Flux
1. User remplit formulaire (nom, **email obligatoire**, message)
2. POST -> `api/contact.php` cree token dans `contact_confirm_tokens` (24h TTL)
3. Email envoye au user avec lien "Confirmer et envoyer mon message"
4. **Sans clic** : message non transmis a l'admin
5. **Au clic** : `api/auth/confirm_contact.php?token=X` -> insert `contact_messages` + notif admin

### 17.2 Tables
- `contact_messages` (id_msg PK, ip, nom, email, message, lu)
- `contact_confirm_tokens` (auto-created, token 64 chars, expire 24h)

### 17.3 Rate limit
- 3 demandes/jour par IP

### 17.4 4 formulaires contact
- Footer `index.php` (`fcEmail`)
- Overlay `index.php` (`ovEmail`)
- `pages/profil.php` (`pubEmail`)
- `core/ip_logger.php` page de blocage (`cEmail`)

### 17.5 Page Contact dediee
`?page=contact` : page complete avec gros bloc rouge "Retirer son profil" en haut + formulaire.

### 17.6 Admin
- Cookie `bk_sa_token` requis
- GET `?mark_read=ID` / `?delete=ID` / `?unban_ip=X`
- Section 14 du panel : alerte violette pulsante si non lu
- Section 16 : courrier non confirme (tokens not used)

---

## 18. PATTERNS RECURRENTS

### 18.1 Batch query niveaux (eviter N+1)
```php
$athIds = array_map(fn($a) => $a['id_athlete'], $athletes);
$idsList = implode(',', $athIds);
$nRes = $conn->query("SELECT id_athlete, code_niveau FROM athlete_niveaux WHERE id_athlete IN ($idsList)");
// grouper par id_athlete et assigner
```

### 18.2 Subquery niveaux pour records
```sql
(SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
 FROM athlete_resultats ares
 WHERE ares.id_athlete = r.id_athlete AND ares.id_epreuve = r.id_epreuve
   AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
```

### 18.3 Filtre universel club_stats ($athFilter)
```php
$athFilter = '';
if ($filterNat || $filterSexe || $filterCat) {
    $afConds = [];
    if ($filterNat) $afConds[] = "_af.nationalite_athlete = '...'";
    if ($filterSexe) $afConds[] = "_af.sexe_athlete = '...'";
    if ($filterCat) $afConds[] = "_af.categorie_athlete = '...'";
    $athFilter = " AND ac.id_athlete IN (
        SELECT _af.id_athlete FROM athletes _af
        WHERE " . implode(' AND ', $afConds) . "
    )";
}
// Ajoute a chaque WHERE des ~30 requetes
```

### 18.4 API call depuis index.php
```php
$data = apiCall("$BASE_API/endpoint.php?" . http_build_query($params));
// $BASE_API = "https://bokonzi.com/api"
```

### 18.5 Chart.js
```javascript
new Chart(document.getElementById('canvasId'), {
    type: 'doughnut',
    data: { labels: [...], datasets: [{ data: [...], backgroundColor: [...] }] },
    options: { responsive: true, plugins: { legend: { labels: { color: '#c9d1d9' } } } }
});
```

### 18.6 Pagination
```php
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;
$totalPages = ceil($total / $limit);
```

### 18.7 UNION records + progressions (club_stats)
```sql
SELECT DISTINCT id_epreuve, id_athlete FROM athlete_records WHERE ... AND performance > 0
UNION
SELECT DISTINCT id_epreuve, id_athlete FROM athlete_progressions WHERE ... AND performance > 0
```

---

## 19. DEPLOIEMENT

### 19.1 Local -> Production
- Local : `C:\xampp\htdocs\BK\` (XAMPP)
- Prod : Hostinger (`bokonzi.com`)
- Workflow : copie manuelle via file manager Hostinger
- PHP CLI local : `/c/xampp/php/php.exe`

### 19.2 Verifications post-deploiement
- `bokonzi.com/api/stats.php` -> stats globales OK
- `bokonzi.com/?page=accueil` -> dashboard charge
- `bokonzi.com/admin/clear_cache.php` -> vider cache si necessaire
- Relancer `stats.php?detail=1&top=30` apres clear_cache

### 19.3 Hostinger contraintes
- Headers bloques (certains)
- Loopback sans cookies
- Verifier colonnes BDD (peut differer de local)
- Erreurs 500 a logger

---

## 20. HISTORIQUE DES CHANGEMENTS

### 2026-04-30
- **Theme auto** : 3 modes (clair/sombre/auto), defaut auto, sombre 21h-6h
- Bouton flottant -> menu deroulant 3 options
- Anti-flash mis a jour (3 emplacements)
- localStorage : `bk_theme_mode` (avec migration de `bk_p2_light`)
- Re-application 60s + visibilitychange
- **Message rouge "Retirer son profil"** : bloc prominent dans 6 emplacements contact
- 5 etapes detaillees, dernier point en vert
- Doublon footer retire (le bloc est deja toujours visible au-dessus du formulaire)

### 2026-04-13 (Accueil refonte + Disclaimer)
- Stade 3D CSS (piste rotative 90s)
- Podium 3D Three.js (or/argent/bronze + medailles flottantes)
- Suppression : graph sexe, top 10 clubs/epreuves, athletes random, top villes/epreuves, cartes Resultats/Records
- 4 stat cards pleine largeur
- Lien Epreuves retire de la nav
- Disclaimer : bandeau accueil + popups Athletes/Profil + footer 7 paragraphes
- Panel section "Courrier non confirme"

### 2026-04-09 (Page Athletes refonte)
- Top 100 IA/IB sur 7 epreuves
- API `liste.php?niveau=&epreuve=`
- Panel Section 17 : Profils comportementaux

### 2026-04-01 (Panel v2)
- Sections 3-12 supprimees, recherche live
- Variables CSS `:root` dans dashboard.css
- Police Inter prioritaire
- Reglage style profil athlete configurable

### 2026-03 (rate limiting + tracking)
- Limites recherches : 100 anonymes / 500 connectes / illimite admin
- Badge dore clignotant (`#ffd700`, `bkGoldBlink`)
- Search tracking complet (table + 7 tabs admin)
- Profil masque (visible=0) self-service

### 2026-02 (Google OAuth)
- Connexion uniquement Google (plus de mdp)
- `core/oauth_config.php` (extensible Facebook/Instagram)
- Suppression `admin/drop_all.php` et `admin/reset.php`

---

*Documentation mise a jour le 30 avril 2026 — Bokonzi (bokonzi.com)*
