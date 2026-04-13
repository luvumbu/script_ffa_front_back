# BOKONZI (BK) — Reference rapide projet

## Stack technique
- **Backend**: PHP 8+ / MySQL (mysqli) / Apache (XAMPP)
- **Frontend**: HTML/CSS/JS vanilla + Chart.js 4.4.7
- **Serveur**: XAMPP local (C:\xampp\htdocs\BK), prod Hostinger (bokonzi.com)
- **Pas de framework** : tout est fait main
- **BDD** : connexion via `core/db.php` (require `core/credentials.php`)
- **index.php** inclut `core/db.php` en ligne 9 → `$conn` disponible partout

## Architecture
```
BK/
├── api/                API REST JSON (20+ endpoints, cache fichier 24h)
│   ├── config.php      Headers JSON + CORS + $conn + auth.php + jsonResponse() + cle API
│   ├── contact.php     Messages contact (POST=envoyer, GET=mark_read/delete/unban_ip)
│   ├── athlete.php     Fiche complete athlete (?id= ou ?id_athlete=)
│   ├── search.php      Recherche multi-criteres (12 filtres combinables)
│   ├── search_track.php Tracking recherches/consultations (POST sendBeacon)
│   ├── club_stats.php  Stats club (?id=, ?nom=, ?annee=, ?rp=, ?ep=, ?nationalite=, ?sexe=, ?categorie=)
│   ├── ville_stats.php Stats ville (?nom=, ?niv=, ?nat=, ?ans=)
│   ├── epreuve_stats.php Stats epreuve (?nom=, ?page=, ?limit=, ?sexe=, ?categorie=)
│   ├── clubs.php       Liste clubs paginee
│   ├── epreuves.php    Liste epreuves paginee
│   ├── villes.php      Liste villes paginee
│   ├── stats.php       Stats globales (?detail=1&top=30, ?nocache=1)
│   ├── classement.php  Classement par epreuve (temps reel, pas de cache)
│   ├── liste.php       Liste athletes paginee (8 ordres de tri)
│   ├── log.php         Logging actions utilisateur (POST=ecrire batch en BDD, GET=lire)
│   ├── follow.php      Suivi athletes + clubs (POST=toggle, GET=status)
│   ├── subscribe.php   Collecte email (newsletter, PDF)
│   ├── performances.php CRUD perfs manuelles (auth requise)
│   ├── top_searched.php Top clubs/athletes recherchés (search_tracking, cache 10min)
│   ├── epreuve_records.php Records paginés par épreuve
│   ├── ville_epreuves.php Épreuves par ville
│   ├── competitions.php Liste des compétitions
│   └── auth/           login.php, register.php, logout.php, me.php, forgot_password.php, reset_password.php, google_login.php, google_callback.php, verify_email.php, confirm_hide.php, confirm_contact.php
├── cache/              Cache JSON fichier (24h, protege .htaccess)
│   ├── stats_base.json           Cache stats sans detail
│   ├── stats_detail_30.json      Cache stats avec detail (top 30)
│   ├── topsearched_*.json        Cache top recherchés (1h TTL)
│   ├── clubstats_*.json          Cache club_stats par params
│   ├── villestats_*.json         Cache ville_stats par params
│   ├── ep_*.json                 Cache epreuve_stats par params
│   ├── search_*.json             Cache search par params
│   ├── athlete_*.json            Cache athlete par id
│   └── liste_*.json              Cache liste athletes
├── core/               Noyau applicatif
│   ├── credentials.php Identifiants BDD ($dbname, $username, $password)
│   ├── db.php          Connexion mysqli ($conn)
│   ├── auth.php        Auth (hash, sessions 30j, roles, requireAuth/requireRole)
│   ├── oauth_config.php Config OAuth Google (extensible Facebook/Instagram)
│   ├── ip_logger.php   Logger IP universel (rate limiting desactive)
│   ├── dbCheck_athle.php Schema BDD (23 tables, 30+ FK)
│   ├── insert_athle.php Import donnees → BDD
│   ├── seo.php         Generation meta/OG/Twitter/JSON-LD Schema.org
│   └── paths.php       Constante BK_BASE
├── admin/              Administration
│   ├── panel.php       Super Admin dashboard (16 sections, auth BDD credentials)
│   ├── setup_bdd.php   Creation BDD + toutes les tables
│   ├── clear_cache.php Vider cache (?prefix= pour cibler)
│   ├── cache_urls.php  Pre-generation cache
│   ├── fix_perf_int.php Correction INT perfs (padding dixiemes, ?go pour executer)
│   ├── logs.php        Visualisation logs (acces restreint par email)
│   └── remote_check.php API JSON admin a distance (test_scrape, scrape_status, count, query)
├── scraping/           Pipeline de collecte de donnees athle.fr
│   ├── scrape_functions.php  scrapeParallel() — curl_multi 7 athletes x 3 pages
│   ├── scraper.php           Scraping principal (batch 7, skip BDD, auto-refresh, bouton reset)
│   ├── check_sync.php        Verification + scraping des absents (2 phases)
│   ├── check_athletes.php    Comparaison src/ vs BDD → absents.json
│   └── import_bdd.php        Import fichiers JSON src/ → BDD
├── Class/              53 classes utilitaires
│   ├── DatabaseHandler.php  Wrapper BDD / ORM leger (63 KB)
│   └── ... (convertisseurs, validateurs, formatters, etc.)
├── pages/              Pages standalone (profil.php, global_athlete.php, recherche.php, classement.php, performances.php, test_api.php)
├── logs/               Logs IP + daily counters (protege .htaccess)
│   ├── ip_view.php     Viewer distant logs IP (auth email whitelist)
│   ├── ip_track_YYYY-MM.php  Log mensuel JSON (protege par die())
│   ├── ip_daily_YYYY-MM-DD.php  Compteurs rate limiting journaliers
│   ├── ip_banned.php        IPs bannies definitivement (JSON protege par die())
│   ├── .page_limits.php     Compteurs anti-scraping journaliers par IP (protege par die())
│   ├── .st_ignored_ips.php  IPs ignorees du search tracking (protege par die())
│   └── .sa_sessions.php     Sessions super admin (protege par die())
├── docs/               Documentation technique
├── generate_og_image.html Generateur image OG (canvas 1200x630)
├── index.php           PAGE PRINCIPALE (~8500 lignes PHP+HTML+JS, anti-scraping 10 pages/jour)
├── dashboard.css       Styles du dashboard (~550 lignes)
├── common.css          Styles globaux
├── login.php / register.php / forgot_password.php / reset_password.php / nav.php / panel.php
├── sitemap.php         Generation sitemap XML
├── robots.txt          SEO robots
├── README.md           Documentation utilisateur
└── FONCTIONNALITES.md  Documentation complete des fonctionnalites
```

## index.php — Structure des pages

| Page | URL | Description |
|------|-----|-------------|
| Accueil | `?page=accueil` | Stats globales, graphiques, top clubs/epreuves/athletes |
| Athletes | `?page=athletes` | Liste paginee, recherche live |
| Recherche | `?page=recherche` | Recherche avancee 12 filtres + barre live + select nationalites BDD |
| Profil | `?page=profil&id=X` | Fiche complete + bio auto-generee + tout cliquable |
| Clubs | `?page=clubs` | Liste clubs, panneau detail club 5 onglets |
| Epreuves | `?page=epreuves` | Liste epreuves, panneau detail epreuve 4 onglets |
| Villes | `?page=villes` | Liste villes / Detail ville (`&open=NomVille`) + filtres niv/nat/ans |
| Comparer | `?page=comparer` | Comparaison athletes/clubs (panier localStorage ou URL partageable) |
| Tuto | `?page=tuto` | Tutoriel anime 8 sections (scroll unique, animations IntersectionObserver) |

## index.php — Structure du code (reperes de lignes approximatifs)

| Section | Lignes approx | Contenu |
|---------|---------------|---------|
| PHP utils + apiCall() | 1-55 | dateFR(), apiCall(), require db.php |
| SEO dynamique | 137-250 | $seoTitle, canonical, og, Twitter Cards, JSON-LD |
| Navigation HTML | 455-465 | Liens nav avec classe active |
| Page Accueil | 475-810 | Stats, graphiques, tops |
| Page Athletes | 810-1027 | Liste paginee |
| Page Recherche | 1027-1540 | Formulaire + resultats + titre club dynamique |
| Page Profil | 1540-2510 | Header, clubs, records, medailles, progressions, podiums, resultats, niveaux, bio |
| Page Clubs | 2510-2625 | Liste clubs |
| Page Epreuves | 2625-2710 | Liste epreuves |
| Page Villes | 2710-4120 | Detail ville PHP (resume, tables, filtres) |
| Page Comparer | 4120-4250 | Comparaison |
| Page Tuto | 4250-4575 | 8 etapes animees |
| Panneau Epreuve (overlay) | 4580-4700 | HTML + tabs |
| Panneau Club (overlay) | 4700-4800 | HTML + tabs |
| JavaScript global | 4800-8200 | ~100 fonctions (club panel, epreuve panel, charts, etc.) |
| Modal + JS Follow athlete | 8195-8305 | Bouton suivre athlete + modal email + localStorage |
| JS Follow club | 8305-8400 | Bouton suivre club (dans panneaux club) + hook modal partagee |
| JS PDF + Newsletter | 8400-fin | Telecharger PDF profil + barre newsletter |

## IDs athletes — ATTENTION 2 systemes
- `athlete_id_externe` = ID athle.fr (dans l'URL `?id=`, utilise par dashboard)
- `id_athlete` = ID interne BDD (cle primaire auto-increment)
- `api/athlete.php` accepte les 2 : `?id=` (externe) ou `?id_athlete=` (interne)
- **TOUJOURS** utiliser `athlete_id_externe` dans les URLs du dashboard

## Systeme de niveaux de competition (couleurs)
Utilise partout dans les tableaux de performances.

| Famille | Codes | Couleur | Hex |
|---------|-------|---------|-----|
| Departemental | D1-D8 | Orange | bg:#f9731620, border:#f97316, text:#fb923c |
| Regional | R1-R6 | Cyan | bg:#0891b220, border:#0891b2, text:#22d3ee |
| National | N1-N4 | Rose | bg:#e11d4820, border:#e11d48, text:#fb7185 |
| International | IE, IR | Fuchsia | bg:#c026d320, border:#c026d3, text:#e879f9 |

**Hierarchie** : IE(100) > IR(99) > N1(90)...N4(86) > R1(80)...R6(75) > D1(70)...D8(63)
**JS** : `_nivBadge(code)`, `_nivBadges(arr)`, `_highestNiveau(arr)`
**PHP** : inline styles avec meme schema + fonction `villeNivStyle()` pour page Villes

## Panneau detail club (composant JS reutilisable)
Present sur 3 pages (Accueil, Clubs, Recherche) avec suffixe :
- `''` = Recherche, `'Accueil'` = Accueil

**Fonctions cles** :
- `_openClubPanel(url, suffix)` / `_closeClubPanel(suffix)`
- `_fillClubPanel(data, suffix)` / `_renderClubTab(tab, suffix)`
- `loadClubRecPage(page, suffix)` / `loadClubEpPage(page, suffix)`
- `_clubFilterParams(d)` — retourne `&nationalite=X&sexe=Y&categorie=Z` si filtres actifs

**5 onglets** : epreuves (50/page), nationalites (charts+cliquable+nat_detail), records (10/page), stats (charts sexe/cat/evo + medailles/podiums), resume (3 modes : Global/Annee/Comparer)

**Filtrage club_stats.php** : params `nationalite`, `sexe`, `categorie` → subquery universelle `$athFilter` appliquee a TOUTES les ~30 requetes SQL. Verification appartenance club par periode via `$mcRec`, `$mcRes`, `$mcMed`, etc.

## Panneau detail epreuve (composant JS)
Present sur page Epreuves + Accueil (via openEpreuveDetail).
**Fonctions** : `openEpreuveDetail(nom)`, `closeEpreuveDetail()`, `switchEpreuveTab(tab)`, `loadEpreuveRecPage(page)`, `_renderEpreuveTab(tab)`
**4 onglets** : records (50/page), nationalites (charts+cat), stats (sexe/cat/med/pod/sel/clubs/villes/evo), resume (auto-genere)
**API** : `epreuve_stats.php`

## Resume club — JS function `_buildResumeText(d, annee)`
~300 lignes, 18 paragraphes conditionnels. 3 modes : Global, Par annee, Comparer.
Utilise : top_medaille_athletes, top_medaille_competitions, top_medaille_epreuves, top_podium_epreuves, athletes_selectionnes, resultats_par_annee.

## Resume ville — PHP inline
15 paragraphes conditionnels avec percentages. Genere cote serveur dans la page Villes.

## Bio athlete — JS function `buildAthleteBio(data, selectedYears)`
~500 lignes, biographie auto-generee complete. Filtrable par annees via selecteur checkboxes.
Fonctions associees : `_bioCollectYears()`, `_bioRenderYearSelector()`, `_bioToggleYear()`, `_bioRebuild()`

## Page Recherche — Specificites
- **Titre dynamique** : si `?club=X` → affiche `<h1>🏟 Nom du Club</h1>` au lieu de "Recherche"
- **Filtres actifs affichés** : nationalite/sexe/categorie en badges gris a cote du titre
- **Select nationalites** : `<select>` charge depuis BDD (`$conn` via core/db.php) avec compteurs
- **Panneau club auto-ouvert** : si `?club=X`, charge le panneau club automatiquement avec filtres propages
- **SEO** : titre page = "Nom Club — Bokonzi" si club actif

## Page Profil — Tout cliquable
- **Header** : sexe → `?page=recherche&sexe=M`, categorie → `&categorie=SE`, nationalite → `&nationalite=FRA`
- **Lieu naissance** → `?page=villes&open=NomVille`
- **Clubs** → `?page=recherche&club=NomClub` (attention : `rtrim($club, '* ')` car API retourne `*`)
- **Records/Progressions/Niveaux** : clubs, epreuves, villes tous cliquables
- **Progression detail JS** : lieu cliquable vers page villes

## Page Comparer — Liens partageables
- **localStorage** : panier `bk_cmp_athletes` / `bk_cmp_clubs` (comportement par defaut)
- **URL partageable athletes** : `?page=comparer&ids=548525,2643370` (IDs externes separes par virgules)
- **URL par licence** : `?page=comparer&licences=131980,1586918` (numeros de licence)
- **URL clubs** : `?page=comparer&clubs=ES%20MASSY,BORDEAUX%20ATHLE` (noms URL-encodes)
- **Mix** : `?page=comparer&ids=548525&licences=1378169&clubs=ES%20MASSY`
- **Auto-compare** : si URL contient `ids`/`licences`/`clubs`, les donnees sont chargees, la 1ere epreuve commune est pre-selectionnee, et la comparaison se lance automatiquement (comme si on avait clique "Comparer")
- **Description SEO** : `<p id="cmpDescription">` genere automatiquement ("Comparaison entre X et Y — records, progressions, medailles...")
- **Bouton "Copier le lien"** : genere l'URL avec les athletes/clubs selectionnes
- **Priorite** : URL params > localStorage (si URL presente, localStorage ignore)
- **API athlete.php** : accepte `?id=` (externe), `?id_athlete=` (interne), `?licence=` (numero de licence)

## Panneau club — Barre de recherche athletes
- **Input** dans chaque panneau club (Accueil, Recherche, Clubs) entre les tabs et le contenu
- **ID** : `clubSearchInput{suffix}`, `clubSearchBar{suffix}`
- **Init** : `_clubSearchInit(suffix)` appele dans `_fillClubPanel()`
- **Recherche** : `_clubSearchExec(suffix)` — debounce 350ms, `search.php?club=NomClub&nom=Query&limit=50`
- **Resultats** : tableau 3-tables (#, Athlete, Cat, Sexe, NAT, Niveaux, Records)
- **Retour** : vider l'input restaure l'onglet actif, changer d'onglet efface la recherche

## API search.php — Recherche multi-mots (ordre libre)
- Le parametre `nom` est decoupe en mots (`preg_split('/\s+/')`)
- Chaque mot genere un `LIKE '%mot%'` combine par `AND`
- Permet de trouver "LECLERCQ Remi" en cherchant "Remi LECLERCQ" ou inversement

## Page Tuto — 8 sections animees
- Toutes les sections visibles sur une seule page (scroll)
- Animations declenchees par IntersectionObserver (threshold 0.25)
- Step 1 : typing effect + compteurs animes (from API stats)
- Step 2 : auto-typing dans mock barre de recherche
- Progress bar sticky en haut, cliquable pour sauter a une section
- CSS dans dashboard.css : `.tuto-container`, `.tuto-step`, animations `tutoFadeIn`, `tutoPulse`, `tutoBlink`, `tutoSlideRight`

## API club_stats.php — Parametres complets
- `id` ou `nom` : identification du club
- `annee` : filtre par annee
- `rp` : page records (10/page)
- `ep` : page epreuves (50/page)
- `nationalite` : filtre athletes par nationalite → `$athFilter`
- `sexe` : filtre athletes par sexe → `$athFilter`
- `categorie` : filtre athletes par categorie → `$athFilter`
- `nat_detail` : codes nat pour comparaison detaillee
- `perso` : mode records personnels (relache filtre periode)
- `nocache` : bypass cache
- **Retourne aussi** : `niveaux_par_annee` (D/R/N/I par année), `annees_disponibles`, `annee_filtree`

## API epreuve_stats.php — Parametres
- `nom` : nom de l'epreuve (requis)
- `page` / `limit` : pagination records (50/page par defaut)
- `sexe` / `categorie` : filtres
- Detection auto temps vs distance pour le tri (REGEXP sur nom epreuve)
- Retourne : total_athletes, total_records, par_sexe, par_categorie, nationalites, records (pagines), medailles, podiums, top_clubs, top_villes, niveaux_resultats, selections, progressions, resultats_par_annee

## API ville_stats.php — Parametres
- `nom` : nom de la ville (requis)
- `page` / `limit` : pagination (30/page par defaut)
- `niv` : filtre niveaux (ex: `D3,D2,R1`)
- `nat` : filtre nationalites (ex: `FRA,MAR`)
- `ans` : filtre annees (ex: `2023,2024`)

## API search.php — 12 filtres
`nom`, `nom1`, `nom2`, `club`, `categorie`, `sexe`, `nationalite`, `epreuve`, `ville`, `competition`, `medaille`, `annee`, `licence`, `page`, `limit`
- Au moins 1 filtre requis
- **Rate limiting** : 100/jour anonymes, 500/jour connectes, illimite super admin (badge dore clignotant dans nav)
- **Constantes** : `BK_SEARCH_LIMIT_ANON = 100`, `BK_SEARCH_LIMIT_LOGGED = 500` (definies en haut de search.php)
- Exclut clubs >5000 athletes (sauf si filtre club actif)
- Retourne : niveaux[] + top_records[5] avec top_niveau par athlete

## API stats.php
- `detail=1` : top clubs/epreuves/athletes/villes avec niveaux_pct (D/R/N/I)
- `top=N` : nombre items dans tops (10-200)
- `nocache=1` : bypass cache
- Score athletes : medailles*5 + podiums*3 + selections*4 + records

## API follow.php — Suivi athletes et clubs
- **POST** : `{ athlete_id, email }` ou `{ club_id, email }` — toggle follow/unfollow
- **GET** : `?athlete_id=X&email=Y` ou `?club_id=X&email=Y` — check status
- Retourne : `{ success, following: bool, count: int }`
- Si user connecte (cookie `bk_token`), email auto-detecte
- Tables : `athlete_follows` (athlete_id_ext) et `club_follows` (club_id)

## API classement.php
- `epreuve` (int, requis), `sexe`, `categorie`, `annee`, `limit`, `offset`
- ROW_NUMBER() pour meilleure perf par athlete
- PAS de cache (temps reel)

## API top_searched.php
- `type` (requis) : `clubs` ou `athletes`
- `limit` : max items (defaut 50, max 50)
- `days` : periode en jours (1, 7, 30, 365 — defaut 1)
- **Lit depuis `search_tracking`** : `COUNT(DISTINCT ip)` comme vues, filtre par `created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)`
- **2 sources** : entity_name (tracking serveur) + query_text (tracking JS live_search) — deduplique par nom
- **Enrichissement** : athletes (sexe, categorie, nationalite, club), clubs (nb_athletes)
- Cache 10min : `topsearched_{type}_{limit}_{days}d.json`
- `?nocache` : bypass cache
- `?reset=athletes|clubs|all&bk_key=...` : reset vues + `DELETE FROM search_tracking WHERE search_type = '...'`

## Page Accueil — Top Consultés
- 2 sections apres les stat cards, avant les courbes
- **Top Clubs Consultés** : colonnes #, Club, Athletes, Vues
- **Top Athlètes Consultés** : colonnes #, Athlete, Club, Cat, Sexe, Vues
- **Onglets periode** : Jour (1j, defaut), Semaine (7j), Mois (30j), Année (365j)
- Onglet actif = violet (#6c5ce7), inactif = gris
- **Donnees depuis `search_tracking`** via `top_searched.php` (COUNT DISTINCT ip)
- Pagination : 10/page, max 5 pages + bouton "Voir tout"
- Fallback : si vues=0, utilise `$detailData` de `stats_detail_30.json` via `_fbMapClubs()`/`_fbMapAth()`
- Auto-refresh : `setInterval` 60s avec `?nocache`
- Pattern 3-tables : header / `<tbody id="topSearchClubsBody">` / footer
- JS : `_loadTopClubs(nc)`, `_loadTopAth(nc)`, `_switchClubDays(d)`, `_switchAthDays(d)`, `_renderTabs()`

## Panneau club — Onglet Épreuves (filtres avancés)
### Filtres disponibles
1. **Mode** : Records du club / Records personnels (server-side via `&perso=1`)
2. **Discipline** : client-side, multi-select, `window['_clubDiscFilter' + s]`
3. **Niveaux** : client-side, multi-select D1-D8/R1-R6/N1-N4/IE/IR, `window['_clubNivFilter' + s]`
4. **Année** : server-side via `&annee=XXXX`, 2 modes :
   - **Filtrer** : 1 année, recharge via `_clubSetEpYear(year, suffix)`
   - **Comparer** : 2-5 années, `_clubRunEpYearCmp(suffix)` fetch parallèle

### Mode Comparer années
- Toggle via `_clubEpYearModeSet('compare', suffix)`
- Multi-select : `window['_clubEpYearCmp' + s]` (max 5)
- Résultats : `window['_clubEpYearCmpData' + s]`
- Affichage : tableau comparatif (3-tables) + graphique barres + top épreuves + résumé textuel
- **Résumé auto-généré** (`_buildEpYearCmpHTML`) : meilleure année par métrique, tendances %, médailles, niveaux D/R/N/I

### Courbes niveaux (dans onglet Stats)
- **Distribution des niveaux** : courbe Bezier (tension 0.4) des codes D1-IE
- **Évolution par année** : 4 courbes D/R/N/I sur niveaux_par_annee
- Données : `d.niveaux_par_annee` (ajouté dans club_stats.php)

## Pattern 3-tables (CONVENTION OBLIGATOIRE)
Toutes les tables `bk-table` DOIVENT utiliser ce pattern :
```html
<div class="table-wrap">
  <table class="bk-table"><tr><th>Col1</th><th>Col2</th></tr></table>
  <table class="bk-table"><!-- data rows only --></table>
  <table class="bk-table"><tr><th>Col1</th><th>Col2</th></tr></table>
</div>
```
En JS, stocker le TH dans une variable :
```javascript
var thRow = '<tr><th>#</th><th>Nom</th></tr>';
html += '<div class="table-wrap">';
html += '<table class="bk-table">' + thRow + '</table>';
html += '<table class="bk-table">';
// ... data rows ...
html += '</table>';
html += '<table class="bk-table">' + thRow + '</table>';
html += '</div>';
```

## Vider le cache prod à distance
- Tout : `https://bokonzi.com/admin/clear_cache.php`
- Ciblé : `https://bokonzi.com/admin/clear_cache.php?prefix=clubstats`
- Prefixes : `clubstats`, `villestats`, `ep`, `search`, `athlete`, `liste`, `stats`, `topsearched`

## Tables MySQL principales
| Table | Cle | Notes |
|-------|-----|-------|
| `athletes` | id_athlete PK, athlete_id_externe UNIQUE | Double ID system |
| `clubs` | id_club, nom_club | |
| `epreuves` | id_epreuve, nom_epreuve | |
| `villes` | id_ville, nom_ville | |
| `competitions` | id_competition, nom_competition | |
| `categories` | id_categorie, code_categorie | EA,PO,BE,MI,CA,JU,ES,SE,V1-V4 |
| `nationalites` | id_nationalite, code_nationalite UNIQUE | ISO 3 lettres |
| `athlete_clubs` | id_athlete + id_club + annee_debut/fin | Periodes membership |
| `athlete_records` | id_athlete + id_epreuve + perf + date + id_ville | Records personnels |
| `athlete_resultats` | id_athlete + id_epreuve + date + perf + niveau_resultat + id_ville | Avec niveaux D/R/N/I |
| `athlete_medailles` | id_athlete + type_medaille + id_epreuve + id_competition + id_ville | or/argent/bronze/autre |
| `athlete_podiums` | id_athlete + rang_podium + id_epreuve + id_ville | Top 3 |
| `athlete_selections` | id_athlete + id_competition + id_epreuve | Selections equipe |
| `athlete_progressions` | id_athlete + id_epreuve + annee + perf + id_ville | Evolution annuelle |
| `athlete_niveaux` | id_athlete + code_niveau + points_niveau + annee | Qualifications |
| `athlete_niv_perfs` | id_niveau + id_epreuve + performance | Perfs par niveau |
| `users` | id_user, email UNIQUE, google_id UNIQUE | Roles: athlete/coach/club/admin, OAuth (google_id, oauth_provider) |
| `user_sessions` | id_session, token UNIQUE | TTL 30 jours |
| `athlete_perfs_manuelles` | id_perf | Perfs saisies manuellement |
| `logs` | id_log, ts, ip, sid, action, page | Tracking activite utilisateur (stocke en BDD) |
| `athlete_follows` | id_follow, email, athlete_id_ext | Suivi athlete par email (UK: email+athlete) |
| `club_follows` | id_follow, email, club_id | Suivi club par email (UK: email+club) |
| `email_subscribers` | id_sub, email, source, detail | Newsletter + PDF (UK: email+source) |
| `contact_messages` | id_msg PK, ip, nom, email, message, lu | Messages contact (lu=0/1) |
| `password_resets` | id_reset PK, id_user FK, token UNIQUE, expire_at, used | Tokens reinitialisation mdp (1h TTL) |
| `search_tracking` | id_search PK, ip, query_text, search_type ENUM, source ENUM | Tracking recherches/consultations (entity_id, entity_name, result_count, page, created_at) |
| `profile_reports` | id_report PK, ip, athlete_id_ext, athlete_name, reason, email, status | Signalements profil (new/read/resolved) |
| `profile_hide_tokens` | id PK, athlete_id_ext, athlete_name, email, token UNIQUE, used, expires_at | Tokens retrait self-service (48h TTL) |
| `contact_confirm_tokens` | id PK, ip, nom, email, message, token UNIQUE, used, expires_at | Tokens confirmation contact (24h TTL) |

## Cache systeme
- **Emplacement** : `cache/` (fichiers JSON, protege .htaccess)
- **TTL** : 24h (86400s), 7j pour liste random
- **Cle** : MD5 de tous les parametres
- **Prefixes** : `athlete_`, `search_`, `clubstats_`, `villestats_`, `ep_`, `clubs_`, `epreuves_`, `villes_`, `stats_`, `liste_`
- **Vider** : `admin/clear_cache.php` (tout) ou `?prefix=clubstats` (specifique)
- **Bypass** : `?nocache=1` sur stats.php, club_stats.php
- **IMPORTANT** : apres vidage cache, appeler `stats.php?detail=1&top=30` pour regenerer `stats_detail_30.json`, sinon fallback top clubs/athletes sera vide sur l'accueil

## Logging systeme

### Log BDD (JS → MySQL)
- **API** : `api/log.php` (POST batch en BDD, GET lecture avec filtres)
- **Stockage** : table `logs` en MySQL
- **JS** : batch toutes les 2s + sendBeacon au depart de page
- **Actions** : page_view, click_link, click_button, form_submit, input_change, copy, page_leave, js_error, navigation
- **Donnees** : IP (auto CloudFlare/proxy), UA, session ID, **uid** (id_user si connecte), uname, page, action, detail, screen, langue, referrer, duree
- **uid rempli** grace a `config.php` qui inclut `auth.php` → `getCurrentUser()` disponible dans log.php
- **Panel historique** : Section 9B utilise `logs.uid` pour retrouver les IPs d'un user → croise avec `search_tracking`
- **Visualisation** : `admin/logs.php` — acces restreint a `luvumbu.n@gmail.com`

### Log IP universel (PHP → fichier JSON)
- **Moteur** : `core/ip_logger.php` — appele via `logIp()` sur chaque page
- **Stockage** : `logs/ip_track_YYYY-MM.php` (JSON protege par `<?php die(); ?>`)
- **Rotation** : mensuelle automatique
- **Donnees par IP** : count, first/last visit, pages, dernières 100 requetes, UA
- **Donnees globales** : total_visits, unique_ips, daily stats, dernières 500 requetes (time, ip, page, url, method, referrer, ua)
- **Viewer** : `logs/ip_view.php` — auth email whitelist, params `?month=`, `?ip=`, `?raw=1`
- **Pages avec logIp()** : index.php, api/config.php, pages/*.php, login.php, register.php

### Rate limiting
- **API search.php** : limites de recherches par jour par IP
  - **Anonymes** : **100 recherches/jour** (`BK_SEARCH_LIMIT_ANON`)
  - **Connectes** (`bk_token`) : **500 recherches/jour** (`BK_SEARCH_LIMIT_LOGGED`)
  - **Super admin** (`bk_sa_token`) : **illimite**
  - Fichier : `logs/.search_limits.php` (JSON protege par die(), reset quotidien)
  - Cle compteur = IP directement (meme cle pour connectes et anonymes, seule la limite change)
  - Retourne `{ success: false, limit_reached: true, limit: N, logged: bool }` (HTTP 429)
  - Chaque reponse reussie inclut `search_used` et `search_limit` pour mise a jour du badge
  - **Badge nav** : `<span id="searchQuota">` a cote du lien Recherche, affiche `N/500` ou `N/100`
    - **Dore clignotant** (`#ffd700`, animation `bkGoldBlink`) si < 80%, rouge clignotant rapide si > 80%
    - Lien "Recherche" aussi en dore clignotant
    - Mis a jour en temps reel via JS `_updateSearchQuota(data)` apres chaque recherche
    - Compteur PHP initial lu depuis `logs/.search_limits.php` au chargement de index.php
  - **Message limite atteinte** : fonction JS `_buildLimitMsg(data)` — bloc XXL (icone 70px, titre 28px rouge, bordure rouge)
  - Whitelist : Google, Hostinger, localhost, bots/curl → illimite
- **Admin login** (`api/auth/login.php`) : **5 tentatives/jour** par IP, blocage 24h apres 5 echecs
  - Fichier : `logs/.admin_attempts.php` (JSON protege par die())
  - **Whitelist illimitee** : Google (66.249.*, 66.102.*, 64.233.*, 72.14.*, 74.125.*, 209.85.*, 216.239.*, 35.*, 34.*), Hostinger (153.92.*, 31.170.*, 185.201.*), localhost (127.0.0.1, ::1)
  - Utilise `goto skipRateLimit;` pour bypass whitelistees
- **Fonctions conservees** (code present mais non appele) : `banIp()`, `isIpBanned()`, `readBannedIps()`, `showRateLimitPage()`
- **IPs whitelistees ne sont PAS loguees** (early return dans `logIp()`)

### Anti-scraping (protection pages)
- **Limite** : 10 pages/jour max pour les visiteurs anonymes
- **Compteur** : `logs/.page_limits.php` — compteurs journaliers par IP (JSON protege par die())
- **Apres 10 pages** : redirection vers `login.php?limit=1` (message "Connectez-vous avec Google pour continuer")
- **Whitelist illimitee** : Google, Hostinger, localhost (meme prefixes que admin login)
- **Utilisateurs connectes illimites** : cookie `bk_token` ou `bk_sa_token` → pas de limite
- **Implementation** : IIFE anonyme dans index.php apres `logIp()`, avant le contenu
- **Nettoyage** : fichier regenere quotidiennement (check date)

### Cle API (config.php)
- **Cle API conservee** : `BK_API_KEY = 'bk_s3cr3t_2026_xK9mP'`
- **Utilisation** : param URL `?bk_key=...` ou header `X-BK-KEY`
- **Sert pour** : reset vues (`top_searched.php?reset=`), bypass cache
- **PAS de restriction IP** : l'API est ouverte a toutes les IPs

### Systeme de contact (avec confirmation email)
- **API** : `api/contact.php` (PAS de config.php = accessible meme si IP bloquee)
- **Email obligatoire** : l'email n'est plus facultatif, il est requis pour envoyer un message
- **Flux avec confirmation** :
  1. L'utilisateur remplit le formulaire (nom, email obligatoire, message)
  2. Un email de confirmation est envoye a l'adresse indiquee (bouton "Confirmer et envoyer mon message")
  3. **Sans clic sur le lien** → le message n'est PAS transmis, l'admin ne recoit rien
  4. **Au clic** → le message est enregistre en BDD (`contact_messages`) + notification admin par email
- **Table tokens** : `contact_confirm_tokens` (auto-created, token 64 chars, expire 24h)
- **Endpoint confirmation** : `api/auth/confirm_contact.php?token=XXX` — verifie token, insere en BDD, notifie admin
- **Rate limit** : 3 demandes/jour par IP (sur `contact_confirm_tokens`)
- **GET admin** (cookie `bk_sa_token` requis) :
  - `?mark_read=ID` : marquer message comme lu
  - `?delete=ID` : supprimer message
  - `?unban_ip=X` : debannir une IP
- **4 formulaires contact** : footer index.php (`fcEmail`), overlay index.php (`ovEmail`), pages/profil.php (`pubEmail`), page blocage
- **Message rouge** dans chaque formulaire : "Un email de confirmation vous sera envoye. Votre message ne nous parviendra qu'apres validation du lien."
- **Admin panel** : section 14, alerte violette pulsante si messages non lus

### Signalement profil + Retrait self-service
- **API** : `api/report.php` (POST = signalement, GET = actions admin)
- **Table signalements** : `profile_reports` (ip, athlete_id_ext, athlete_name, reason, message, email, status)
- **Colonne visibilite** : `athletes.visible` (TINYINT, default 1)
- **5 motifs** : retrait, donnees_incorrectes, usurpation, vie_privee, autre

#### Retrait self-service par email
- **Declencheur** : motif "retrait" + email fourni dans le formulaire de signalement
- **Flux** :
  1. L'utilisateur choisit "Je souhaite retirer mon profil" + indique son email
  2. Un email de confirmation est envoye (bouton "Oui, masquer mon profil")
  3. **Au clic** → profil masque automatiquement (`visible=0`), cache vide, page de confirmation verte
  4. **Sans clic** → le signalement est enregistre mais traite manuellement (delai 1-30 jours)
- **Table tokens** : `profile_hide_tokens` (athlete_id_ext, athlete_name, email, token 64 chars, used, expires 48h)
- **Endpoint confirmation** : `api/auth/confirm_hide.php?token=XXX` — verifie token, SET visible=0, vide cache, page succes
- **Anti-abus (silencieux)** : une adresse email ne peut masquer qu'1 seul profil (tous temps confondus). Si deja utilisee → signalement enregistre normalement mais pas de lien envoye
- **Rate limit** : 1 demande par email+athlete par 24h, 3 signalements/jour par IP

#### Profil masque (visible=0)
- **Non-connectes** : page "Ce profil n'est plus disponible" (icone + message + lien retour)
- **Admin connecte** (`bk_sa_token`) : profil visible avec bandeau rouge "Profil masque — Inaccessible publiquement" + lien panel + texte en rouge
- **API `athlete.php`** : retourne `visible: false` si masque. Admin peut forcer avec `?_all=1`
- **2 pages profil** : `index.php?page=profil&id=X` (id externe) et `pages/profil.php?id=X` (id interne) — les deux respectent `visible`
- **`pages/profil.php`** : verifie `visible` directement en BDD via `core/db.php`, appelle API avec `_all=1` si admin
- **Cache** : vide automatiquement apres changement de visibilite (glob `athlete_*.json` + strpos sur contenu)

#### Actions admin (GET report.php, cookie admin requis)
- `?hide_athlete=ID` : masquer profil (visible=0 + vider cache)
- `?show_athlete=ID` : remonter profil (visible=1 + vider cache)
- `?mark_read=ID` : marquer signalement comme lu
- `?resolve=ID` : marquer signalement comme resolu
- `?delete=ID` : supprimer signalement

#### Formulaire de signalement
- **Present sur** : `index.php` (modal `#reportOverlay`) et `pages/profil.php` (modal)
- **Message rouge permanent** : "Indiquez votre email : nous vous enverrons un lien de confirmation... Sans confirmation, delai 1 a 30 jours."
- **Hint rouge dynamique** (quand motif = retrait) : "L'email est obligatoire pour ce motif. Un seul clic et votre profil sera masque immediatement."
- **Message de retour** (apres envoi retrait) : bloc encadre violet "Verifiez votre boite mail !" avec instructions claires

### Search Tracking (systeme complet)
Systeme de suivi de toutes les recherches et consultations sur le site.

#### Table `search_tracking`
- **Colonnes** : id_search, ip, query_text, search_type (ENUM: athlete/club/epreuve/ville/general), source (ENUM: live_search/page_view/panel_open), entity_id, entity_name, result_count, page, created_at
- **6 index** : idx_st_type, idx_st_source, idx_st_created, idx_st_ip, idx_st_entity, idx_st_query

#### Sources de tracking
1. **JS (sendBeacon)** → `api/search_track.php` :
   - `liveSearch()` : debounce 2s apres derniere frappe, envoie q/type/source/results/pg
   - `_openClubPanel()` : envoie type=club, source=panel_open, entity_name=nom_club, entity_id=id
   - `openEpreuveDetail()` : envoie type=epreuve, source=panel_open, entity_name=nom_epreuve
   - Helper JS : `_trackSearch(params)` + `_trackTimer` (clearTimeout pour debounce)
2. **PHP (INSERT direct)** dans index.php :
   - Page profil athlete : INSERT type=athlete, source=page_view, entity_name=nom+prenom, entity_id=athlete_id_externe
   - Page recherche avec `?club=X` : INSERT type=club, source=page_view, entity_name=nom_club, entity_id=id_club

#### API `search_track.php`
- **POST** : recoit JSON body `{ q, type, source, entity_id, entity_name, results, pg }`
- Gere aussi `$_POST` (fallback)
- IP detection CloudFlare/proxy
- **Nettoyage probabiliste** : 1% chance de `DELETE WHERE created_at < 90 jours`
- Retourne `{ ok: true }` (leger, pour sendBeacon)

#### Admin panel — Section Search Tracking (Section 15)
- **8 KPI cards** : total, aujourd'hui, 7j, 30j, IPs uniques, taux succes, IPs ignorees, derniere recherche
- **Chart 14 jours** : Chart.js bar stacked (5 datasets : athlete, club, epreuve, ville, general)
- **7 onglets interactifs** (pattern `.vue-tab`) :
  1. Recherches : top 50 queries (query, type badge, source, count, IPs, resultats moy, derniere date)
  2. Athletes : TOUTES les entrees type=athlete (nom, entity_id, IP, source, heure, nb resultats) — sans limite
  3. Clubs : TOUTES les entrees type=club — sans limite
  4. Entites : top 50 epreuves/villes (entity_name, type badge, vues, IPs)
  5. IPs : toutes les IPs avec count + bouton "Ignorer" par IP
  6. Horaire : Chart.js bar horizontal (distribution 0h-23h)
  7. Sources : Chart.js doughnut (live_search vs page_view vs panel_open) + detail par type
- **Chaque tab** : barre de recherche filtre + headers triables + lignes
- **Boutons reset** : athletes, clubs, tout le tracking (confirm JS requis)
- **Section IPs ignorees** : liste avec label + bouton "Reactiver", input ajout manuel, bouton "Ignorer mon IP actuelle"

#### Systeme d'IPs ignorees
- **Fichier** : `logs/.st_ignored_ips.php` (format `<?php die(); ?>\n` + JSON)
- **Structure** : `{"1.2.3.4": {"added":"2026-03-02","label":"Mon IP"}, ...}`
- **Impact** : toutes les queries SQL search_tracking dans panel.php ont `WHERE ip NOT IN (...)`
- **Actions** : POST vers panel.php avec `st_action` = `ignore_ip` / `unignore_ip` / `reset_tracking`

### Fichiers dangereux SUPPRIMES
- `admin/drop_all.php` — SUPPRIME (supprimait toutes les tables)
- `admin/reset.php` — SUPPRIME (truncate toutes les donnees)
- Boutons correspondants retires du panel admin

## Systeme de suivi (follow) athletes et clubs
- **API** : `api/follow.php` — supporte athletes (`athlete_id`) ET clubs (`club_id`)
- **POST** : toggle follow/unfollow `{ email, athlete_id }` ou `{ email, club_id }`
- **GET** : check status `?athlete_id=X&email=Y` ou `?club_id=X&email=Y`
- **Tables** : `athlete_follows` (email + athlete_id_ext) et `club_follows` (email + club_id)
- **Bouton profil athlete** : `.btn-follow#btnFollow` dans le header profil
- **Bouton panneau club** : `.btn-follow-club#btnFollowClub{suffix}` dans les 3 panneaux club (Accueil, Recherche, Clubs)
- **Modal partagee** : `#followOverlay` avec `data-mode="club"` pour distinguer athlete/club
- **JS** : `toggleFollow(id)`, `toggleFollowClub(id, suffix)`, `_checkFollowStatus()`, `_checkClubFollowStatus(id, suffix)`
- **localStorage** : `bk_follow_email` stocke l'email pour ne pas le redemander

## Collecte email (newsletter + PDF)
- **API** : `api/subscribe.php` — POST `{ email, source: "newsletter"|"pdf", detail }`
- **Table** : `email_subscribers` (UK: email + source)
- **PDF** : bouton `.btn-pdf` sur profil athlete, genere un print-to-PDF via window.print()
- **Newsletter** : barre fixe en bas (`#newsletterBar`), apparait apres 30s ou 50% scroll
- **localStorage** : `bk_pdf_email`, `bk_nl_done`, `bk_nl_closed`

## Auth systeme
- **Connexion uniquement via Google OAuth** (plus de formulaire email/mot de passe)
- **Roles** : athlete (defaut), coach, club, admin
- **Sessions** : token 64 chars hex, cookie `bk_token` (httpOnly, sameSite=Lax), TTL 30j
- **Fonctions** : `hashPassword()`, `verifyPassword()`, `generateToken()`, `createSession()`, `getCurrentUser()`, `requireAuth()`, `requireRole()`, `logout()`

### Google OAuth 2.0
- **Flux** : `login.php` → bouton Google → `api/auth/google_login.php` (genere state CSRF) → Google → `api/auth/google_callback.php` (echange code, cree/lie user) → `index.php`
- **Config** : `core/oauth_config.php` — credentials Google, detection auto local/prod pour redirect URI
- **Colonnes BDD** : `users.google_id` (VARCHAR 255, UNIQUE), `users.oauth_provider` (VARCHAR 50)
- **Merge comptes** : si email Google existe deja en BDD → lie le google_id au compte existant (pas de doublon)
- **Auto-register** : si email inconnu → cree un user (role=athlete, password_hash vide, oauth_provider=google)
- **Securite** : state CSRF en session, Authorization Code Flow (tout cote serveur), pas de token client-side
- **Extensible** : pret pour Facebook, Instagram (constantes commentees dans oauth_config.php)
- **Pages** : `login.php` ("Se connecter avec Google") + `register.php` ("S'inscrire avec Google") — meme flux

### Super Admin
- **Login** : meme formulaire `login.php` (input type="text"), detecte si email = BDD username + password = BDD password
- **Rate limit login** : 5 tentatives/jour par IP, Google/Hostinger whitelistees (illimite)
- **Cookie** : `bk_sa_token` (7 jours), stocke dans `logs/.sa_sessions.php`
- **Dashboard** : `admin/panel.php` — 16 sections :
  1. Overview (compteurs tables)
  2. Requetes temps reel
  3. Logs BDD (30 derniers, URLs cliquables)
  4. Activite horaire
  5. Top IPs
  6. Users
  7-10. (autres stats)
  11. Actions rapides (reset vues)
  12. Analytics Vues (tabs, charts, tri, filtre, drawer)
  13. IPs bloquees & bannies (avec bouton debannir)
  14. Messages contact (lu/non-lu/supprimer/tout marquer lu)
  15. **Search Tracking** (8 KPI, chart 14j, 7 tabs interactifs, reset, IPs ignorees)
  16. (autres)
- **Detection** : `api/auth/login.php` compare avec `$username`/`$password` de `core/credentials.php`
- **Actions rapides** : reset vues athletes/clubs (appelle `top_searched.php?reset=`)

## localStorage (navigateur)
- `bk_cmp_athletes` : panier comparaison athletes `[{id, name}]`
- `bk_cmp_clubs` : panier comparaison clubs `[{id, name}]`
- `bk_ignored_clubs` : clubs masques `[string]`
- `bk_follow_email` : email pour suivi athlete/club (partage entre les 2)
- `bk_pdf_email` : email pour telechargement PDF
- `bk_nl_done` : newsletter souscrite (true)
- `bk_nl_closed` : newsletter barre fermee (true)
- `bk_sid` (sessionStorage) : session ID pour le tracking

## Patterns de code recurrents

### Batch query niveaux (eviter N+1)
```php
$athIds = array_map(function($a) { return $a['id_athlete']; }, $athletes);
$idsList = implode(',', $athIds);
$nRes = $conn->query("SELECT n.id_athlete, n.code_niveau FROM athlete_niveaux n WHERE n.id_athlete IN ($idsList)...");
// grouper par id_athlete, assigner a chaque athlete
```

### Subquery niveaux pour records/progressions
```sql
(SELECT GROUP_CONCAT(DISTINCT ares.niveau_resultat ORDER BY ares.niveau_resultat SEPARATOR ',')
 FROM athlete_resultats ares
 WHERE ares.id_athlete = r.id_athlete AND ares.id_epreuve = r.id_epreuve
   AND ares.niveau_resultat IS NOT NULL AND ares.niveau_resultat != '') as niveaux
```

### Filtre universel club_stats ($athFilter)
```php
$athFilter = '';
if ($filterNat !== '' || $filterSexe !== '' || $filterCat !== '') {
    $afConds = [];
    if ($filterNat !== '') $afConds[] = "_af.nationalite_athlete = '...'";
    if ($filterSexe !== '') $afConds[] = "_af.sexe_athlete = '...'";
    if ($filterCat !== '') $afConds[] = "_af.categorie_athlete = '...'";
    $athFilter = " AND ac.id_athlete IN (SELECT _af.id_athlete FROM athletes _af WHERE " . implode(' AND ', $afConds) . ")";
}
// Ajoute a chaque WHERE dans les ~30 requetes
```

### API call depuis index.php
```php
$data = apiCall("$BASE_API/endpoint.php?" . http_build_query($params));
// $BASE_API = "https://bokonzi.com/api"
```

### Chart.js pattern (dashboard JS)
```javascript
new Chart(document.getElementById('canvasId'), {
    type: 'doughnut', // ou 'bar', 'line', 'radar'
    data: { labels: [...], datasets: [{ data: [...], backgroundColor: [...] }] },
    options: { responsive: true, plugins: { legend: { labels: { color: '#c9d1d9' } } } }
});
```

### Pagination pattern
```php
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;
$totalPages = ceil($total / $limit);
```

## SEO
- **Titres dynamiques** par page dans index.php (lignes 137-170)
- **Canonical URLs** : `<link rel="canonical">` pour chaque page
- **Open Graph** : og:title, og:description, og:url, og:image, og:locale
- **Twitter Cards** : summary_large_image
- **JSON-LD** : WebSite + SportsOrganization + BreadcrumbList (index.php), Person + BreadcrumbList (seo.php pour profils)
- **Sitemap** : `sitemap.php` genere dynamiquement (toutes pages + clubs + epreuves + villes + athletes pagines 500/page)
- **OG Image** : `generate_og_image.html` genere un PNG 1200x630 via canvas
- **H1** : "Base de Donnees Athletisme Francais — Athletes, Clubs, Records"
- **noindex** : pages comparer, tuto, profil 404
- **GTM** : Google Tag Manager (GTM-KPNTVXDF) dans `<head>` + noscript apres `<body>`
- **AdSense** : Google AdSense (ca-pub-7899923856846249) — script + meta `google-adsense-account` dans `<head>` + `ads.txt` a la racine
- **Footer SEO** : liens internes vers toutes les pages principales

## Epreuves/Records club — UNION records + progressions
L'onglet Epreuves et Records du panneau club utilise un **UNION de `athlete_records` + `athlete_progressions`** pour afficher des donnees completes.

### Pourquoi
`athlete_records` ne contient qu'1 record perso par athlete/epreuve. Si le record a ete etabli dans un autre club, l'epreuve n'apparaissait pas. `athlete_progressions` a `id_club` directement (FK) et contient les progressions annuelles = plus complet.

### Variables cles (club_stats.php)
- `$athFilterProg` : variante de `$athFilter` avec `ap.id_athlete` au lieu de `ac.id_athlete`
- `$progFilterYear` : filtre annee pour progressions (`AND ap.annee_progression = $annee`)
- `$epUnionSub` : sous-requete UNION reutilisable `SELECT DISTINCT (id_epreuve, id_athlete)` depuis records + progressions
- `$recUnionSub` : sous-requete UNION pour records avec `ROW_NUMBER()` dedup par (athlete, epreuve)

### 7 requetes modifiees
1. Total epreuves : `COUNT(DISTINCT id_epreuve)` depuis UNION
2. Liste epreuves paginee : `GROUP BY` sur UNION + ORDER BY discipline
3. Niveaux par epreuve : `JOIN athlete_resultats` sur UNION
4. Best perf par sexe : 2 queries (records + progressions) + merge via `_updateBestBySex()`
5. Total records : `COUNT` avec `GROUP BY athlete+epreuve` pour dedup
6. Records pagines : `ROW_NUMBER() OVER (PARTITION BY athlete, epreuve)` pour garder la meilleure perf
7. Top 10 epreuves : `COUNT(DISTINCT athlete)` sur UNION

### Regles
- **Mode perso** (`?perso=1`) : seulement `athlete_records` sans filtre membership (inchange)
- **Mode non-perso** (records du club) : UNION records + progressions
- **Performances invalides** : filtrees avec `WHERE performance > 0` partout
- **Helper `_updateBestBySex()`** : compare temps vs distance, ignore `perfInt <= 0`

## performanceToInt() — Conversion perf brut → entier
Fonction statique dans `Class/AthleteScraper.php` (ligne ~951). Convertit les performances texte en centièmes/centimètres (INT).

### 7 patterns (dans l'ordre de matching)
| Pattern | Exemple | Resultat | Notes |
|---------|---------|----------|-------|
| `Xh Y'ZZ''CC` | `1h23'45''12` | `(1*3600+23*60+45)*100+12` | Heures |
| `X'YY''CC` | `3'43''65` | `(3*60+43)*100+65` | Minutes+centièmes |
| `X'YY` | `12'09` | `(12*60+9)*100` | Minutes sans centièmes |
| `XX''CC` | `10''48` | `10*100+48 = 1048` | Secondes+centièmes |
| `XmYY` | `6m30` | `6*100+30 = 630` | Distance metres |
| `X.YY` | `7.34` | `7*100+34 = 734` | Distance decimale |
| `XXXX` | `734` | `734*100 = 73400` | Entier seul |

### ATTENTION — Padding des dixiemes (str_pad)
Quand il n'y a qu'1 chiffre apres `''`, `m` ou `.`, c'est un **dixieme** (pas un centieme).
`str_pad($digit, 2, '0', STR_PAD_RIGHT)` transforme `'9'` → `'90'`.

| Brut | Sans padding (BUG) | Avec padding (CORRECT) |
|------|--------------------|------------------------|
| `10''9` | 1009 (10.09s) | 1090 (10.9s) |
| `1'53''3` | 11303 (1:53.03) | 11330 (1:53.30) |
| `6m3` | 603 (6.03m) | 630 (6.30m) |

### Script de correction BDD
`admin/fix_perf_int.php` — corrige les INT existants pour les perfs avec 1 seul chiffre apres `''`.
- Sans param : dry run (montre le nombre de lignes affectees)
- `?go` : execute la correction SQL : `FLOOR(perf/100)*100 + MOD(perf,100)*10`
- Tables corrigees : `athlete_records`, `athlete_progressions`, `athlete_resultats`

## Systeme de scraping (pipeline complet)

### Architecture globale
```
athle.fr (HTML)
    ↓ curl_multi (7 athletes x 3 pages = 21 requetes paralleles)
    ↓ scrape_functions.php → scrapeParallel()
    ↓
AthleteScraper.php (parsing HTML → donnees structurees)
    ↓ extractIdentite/Medailles/Clubs/Progressions/Records/Podiums/Resultats/Niveaux/Selections
    ↓
insert_athle.php → insertAthleteData($scraper, $conn, $cache)
    ↓ cache memoire (loadRefCache) → 0 SELECT repetitifs
    ↓ batch INSERT (1 query par section)
    ↓
MySQL (9 tables enfants) + src/{id}.php (JSON)
```

### Fichiers et roles

| Fichier | Role | Entree | Sortie |
|---------|------|--------|--------|
| `scraping/scrape_functions.php` | Telechargement parallele | IDs athletes | HTML brut (bilans, records, selections) |
| `Class/AthleteScraper.php` | Parsing HTML athle.fr | HTML brut | Donnees structurees (identite, clubs, medailles...) |
| `core/insert_athle.php` | Insertion BDD optimisee | Objet scraper | 9 tables MySQL |
| `core/dbCheck_athle.php` | Creation schema BDD | - | Tables + FK + categories + nationalites |
| `scraping/scraper.php` | Orchestrateur principal | Table `nom_et_liens` | JSON + BDD |
| `scraping/import_bdd.php` | Import JSON → BDD | Fichiers `src/*.php` | BDD |
| `scraping/check_sync.php` | Verification + rattrapage | `nom_et_liens` vs `src/` | `absents2.json` + scrape manquants |
| `scraping/check_athletes.php` | Audit completude | `athletes` table vs `src/` | `absents.json` |

### scraping/scraper.php — Orchestrateur principal
- **URL** : `https://bokonzi.com/scraping/scraper.php`
- **Constantes** : `$TIME_LIMIT = 25s`, `$PARALLEL = 7`
- **Controle start/stop** : bouton DEMARRER/ARRETER avec fichier flag `scraping_running.flag`
  - **DEMARRER** : cree le flag → la boucle auto-refresh tourne
  - **ARRETER** : supprime le flag → arret propre au prochain cycle, progression sauvegardee
  - **Persistant** : le flag survit a la fermeture du navigateur, le scraping reprend si on revient
  - **Fin automatique** : le flag est supprime quand tous les athletes sont traites
- **Workflow par cycle** (25s max, puis auto-refresh) :
  1. Charge toutes les URLs depuis `nom_et_liens` → cache `urls_cache.json`
  2. Charge tous les `athlete_id_externe` deja en BDD → `$existingAthletes[]`
  3. Boucle batch : collecte 7 athletes non-existants, skip les existants sans meme les scraper
  4. `scrapeParallel()` → telecharge 21 pages en parallele
  5. Pour chaque athlete : `AthleteScraper` → extraction → JSON `src/{id}.php` → BDD `insertAthleteData()`
  6. Echecs → `failed.json`
  7. Progression → `progress.txt` + `$_SESSION["url"]`
  8. Verifie que le flag existe toujours → `header("Refresh: 1")` → cycle suivant
- **Bouton reset** : `?reset_to=N` pour reprendre a un numero choisi
- **Test manuel** : `?test_url=ID` ou `?test_url=URL` — scrape 1 athlete, affiche stats, insere en BDD (independant du batch)
  - `&skip_bdd` : test sans insertion
  - `&force` : re-insertion meme si deja en BDD
- **Performance** : ~3.5 jours pour 300k athletes (vs ~17 jours en sequentiel)

### scrapeParallel($athleteIds) — curl_multi
- **Fichier** : `scraping/scrape_functions.php`
- **Signature** : `scrapeParallel(array $athleteIds, string $baseUrl = "https://athle.fr/athletes/") : array`
- 3 URLs par athlete : `/bilans`, `/records`, `/selections`
- `CURLOPT_TIMEOUT = 15s`, User-Agent Mozilla
- **Retourne** : `[athleteId => ['bilans' => html|null, 'records' => html|null, 'selections' => html|null]]`

### AthleteScraper — Extraction HTML
- **Fichier** : `Class/AthleteScraper.php` (56 KB)
- **Constructeur** : `new AthleteScraper($id)` — accepte ID entier ou URL complete
- **Proprietes publiques** : `$identite`, `$clubs`, `$medailles`, `$selections`, `$progressions`, `$records`, `$podiums`, `$resultats`, `$niveaux`
- **Methodes d'extraction** : `extractIdentite()`, `extractClubs()`, `extractMedailles()`, `extractSelections()`, `extractProgressions()`, `extractRecords()`, `extractPodiums()`, `extractResultats()`, `extractNiveaux()`
- **Export** : `toArray()` → tableau associatif, `scrapeAll()` → tout-en-un (fetch + extract)
- **Methodes statiques** :
  - `performanceToInt($perf)` : conversion texte → centièmes (7 patterns, str_pad pour dixiemes)
  - `splitNomPrenom($nom)` : separation nom/prenom (heuristique majuscules)
  - `getCategorieCode($anneeNaissance, $anneeSaison)` : age → code FFA

### insertAthleteData() — Insertion BDD
- **Fichier** : `core/insert_athle.php`
- **Fonctions** :
  - `loadRefCache($conn)` : charge 6 tables de reference en memoire (villes, clubs, epreuves, competitions, categories, nationalites)
  - `cachedGetOrInsertId(&$cache, $conn, ...)` : lookup cache → INSERT IGNORE → SELECT (0 query si cache hit)
  - `insertAthleteData($scraper, $conn, &$cache)` : insertion complete 9 sections
- **Sections inserees** : athletes, athlete_clubs, athlete_medailles, athlete_selections, athlete_progressions, athlete_records, athlete_podiums, athlete_resultats, athlete_niveaux + athlete_niv_perfs
- **Strategie UPDATE** : si `athlete_id_externe` existe → DELETE CASCADE enfants → re-INSERT tout

### Fichiers de progression et logs

| Fichier | Contenu | Persistence |
|---------|---------|-------------|
| `progress.txt` | ID courant du scraper principal | Survit aux redemarrages |
| `progress_absents.txt` | ID courant de check_sync phase 2 | Idem |
| `import_progress.txt` | Index fichier import_bdd | Idem |
| `urls_cache.json` | Cache table `nom_et_liens` | Regenere si supprime |
| `failed.json` | Athletes echoues (scraper principal) | Accumule |
| `failed_absents.json` | Athletes echoues (check_sync) | Accumule |
| `absents.json` | Fichiers src/ manquants (check_athletes) | Regenere |
| `absents2.json` | URLs manquantes (check_sync) | Regenere |
| `src/{id}.php` | JSON athlete avec headers PHP | 1 fichier par athlete |

### Admin distant — remote_check.php
- **URL** : `https://bokonzi.com/admin/remote_check.php?bk_key=...`
- `?action=scrape_status` : total_urls, total_bdd, restants, pct, progress_file
- `?action=test_scrape&id=123` : scrape 1 athlete de test (+ `&skip_bdd`, `&force`)
- `?action=count` : compteurs de toutes les tables
- `?action=columns&table=athletes` : schema d'une table

## Page Accueil — Elements visuels (maj 2026-04-13)

### Stade 3D CSS
- Piste d'athletisme realiste (rectangle + 2 demi-cercles) avec terrain de foot rectangulaire au centre
- 8 couloirs, tribunes violettes, 4 projecteurs, ligne d'arrivee
- Rotation automatique lente (90s/tour) via `@keyframes stadeRotation`
- Etoiles scintillantes, texte BOKONZI en bas
- Pur CSS 3D (`perspective`, `transform-style: preserve-3d`, `translateZ`)

### Podium 3D (Three.js)
- Place apres la liste Top Clubs
- 3 marches : Or (centre, haut), Argent (gauche), Bronze (droite) avec numeros 1/2/3
- 3 medailles flottantes au-dessus (torus + disque + ruban violet)
- Sol piste rouge, particules dorees, camera oscillante
- Three.js charge via CDN (`three@0.160.0`)

### Elements supprimes de l'accueil
- Graphique repartition par sexe (doughnut)
- Graphiques Top 10 Clubs et Top 10 Epreuves (bar charts)
- Section Athletes aleatoires
- Sections Top Villes et Top Epreuves (HTML + JS)
- Cartes stats Resultats et Records
- Colonne Niveaux dans tous les tableaux accueil
- Lien Epreuves dans la nav

### Elements modifies accueil
- 4 stat cards restantes (Athletes, Clubs, Epreuves, Villes) : pleine largeur `grid-template-columns:repeat(4,1fr)` + texte centre
- Lien "Recherche" dans nav : dore clignotant (`#ffd700`, animation `bkGoldBlink`)
- Badge recherche : dore clignotant, rouge rapide si > 80%
- Overlay inscription : **1 heure** (3600000ms) au lieu de 25s

## Disclaimer / Avertissement legal

### Bandeau accueil
- Encadre orange (`#f59e0b`) sous le H1, avec lien "En savoir plus" vers `#footerDisclaimer`
- Texte : "Plateforme independante a caractere informatif"

### Popup page Athletes
- Apparait apres **2 secondes** (1ere visite) ou **immediatement** (refresh sans clic "ne plus afficher")
- 2 boutons : "J'ai lu" (ferme, revient) / "J'ai compris, ne plus afficher" (localStorage `bk_disclaimer_ok_v3`)
- `sessionStorage` pour tracker si deja vu dans la session → 0 delai au refresh

### Popup page Profil
- Meme comportement que page Athletes
- localStorage `bk_profil_disclaimer_ok_v3`, sessionStorage `bk_profil_disc_seen`

### Footer
- Texte legal complet (7 paragraphes) dans `#footerDisclaimer`
- Couvre : independance, sources publiques, pas d'erreurs garanties, droit de suppression, signalement

### Reset localStorage a distance
- Changer le suffixe des cles (`_v3` → `_v4`, etc.) pour forcer tous les visiteurs a revoir les popups

## Panel Admin — Courrier non confirme (maj 2026-04-13)
- Section rouge avant les messages confirmes dans `admin/panel.php`
- Affiche les tokens de `contact_confirm_tokens` avec `used = 0`
- Badge "NON CONFIRME" rouge + badge "EXPIRE" orange si token expire
- Nom, email, message complet, IP, date d'expiration

## Points d'attention CRITIQUES
- `index.php` est ENORME (~8400 lignes) : PHP + HTML + JS tout-en-un. Lire par sections.
- `index.php` inclut `core/db.php` → `$conn` disponible pour requetes directes (ex: select nationalites)
- Les panneaux club/epreuve sont des overlays JS charges via fetch(), pas des pages separees
- La page villes est rendue cote serveur (PHP), contrairement aux panneaux club (JS)
- Le CSS principal est dans `dashboard.css` mais beaucoup de styles inline dans index.php
- Les graphiques des panneaux club necessitent un post-render (chart creation apres innerHTML)
- Club names from API may contain `*` suffix → utiliser `rtrim($club, '* ')` dans les liens
- Clubs >5000 athletes exclus des resultats search sauf si filtre club explicite
- Detection temps vs distance pour tri : REGEXP sur nom epreuve (Poids|Disque|Javelot|etc = DESC)
- Performances avec `performance_int = 0` ou `NULL` = conversions echouees → toujours filtrer `> 0`
- FK vers epreuves/villes/clubs = ON DELETE SET NULL (pas CASCADE)
- FK vers athletes = ON DELETE CASCADE (supprime toutes les donnees liees)
- **Three.js** : charge 1 seule fois via CDN avant les sections 3D, utilise par le podium uniquement
- **Disclaimer** : texte legal affiche sur accueil (bandeau), athletes (popup), profil (popup), footer (complet)




