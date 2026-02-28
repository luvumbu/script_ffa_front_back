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
│   ├── config.php      Headers JSON + CORS + $conn + jsonResponse()
│   ├── athlete.php     Fiche complete athlete (?id= ou ?id_athlete=)
│   ├── search.php      Recherche multi-criteres (12 filtres combinables)
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
│   ├── epreuve_records.php Records paginés par épreuve
│   ├── ville_epreuves.php Épreuves par ville
│   ├── competitions.php Liste des compétitions
│   └── auth/           login.php, register.php, logout.php, me.php
├── cache/              Cache JSON fichier (24h, protege .htaccess)
│   ├── stats_base.json           Cache stats sans detail
│   ├── stats_detail_30.json      Cache stats avec detail (top 30)
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
│   ├── ip_logger.php   Logger IP universel + rate limiting (20 req/jour)
│   ├── dbCheck_athle.php Schema BDD (22 tables, 30+ FK)
│   ├── insert_athle.php Import donnees → BDD
│   ├── seo.php         Generation meta/OG/Twitter/JSON-LD Schema.org
│   └── paths.php       Constante BK_BASE
├── admin/              Administration
│   ├── panel.php       Super Admin dashboard (12 sections, auth BDD credentials)
│   ├── setup_bdd.php   Creation BDD + toutes les tables
│   ├── drop_all.php    Suppression tables athletes
│   ├── reset.php       Remise a zero (?bdd=1 pour truncate)
│   ├── clear_cache.php Vider cache (?prefix= pour cibler)
│   ├── cache_urls.php  Pre-generation cache
│   └── logs.php        Visualisation logs (acces restreint par email)
├── Class/              53 classes utilitaires
│   ├── DatabaseHandler.php  Wrapper BDD / ORM leger (63 KB)
│   └── ... (convertisseurs, validateurs, formatters, etc.)
├── pages/              Pages standalone (profil.php, global_athlete.php, recherche.php, classement.php, performances.php, test_api.php)
├── logs/               Logs IP + daily counters (protege .htaccess)
│   ├── ip_view.php     Viewer distant logs IP (auth email whitelist)
│   ├── ip_track_YYYY-MM.php  Log mensuel JSON (protege par die())
│   └── ip_daily_YYYY-MM-DD.php  Compteurs rate limiting journaliers
├── docs/               Documentation technique
├── generate_og_image.html Generateur image OG (canvas 1200x630)
├── index.php           PAGE PRINCIPALE (~8400 lignes PHP+HTML+JS)
├── dashboard.css       Styles du dashboard (~550 lignes)
├── common.css          Styles globaux
├── login.php / register.php / nav.php / panel.php
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
| Comparer | `?page=comparer` | Comparaison athletes/clubs (panier localStorage) |
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
| `users` | id_user, email UNIQUE | Roles: athlete/coach/club/admin |
| `user_sessions` | id_session, token UNIQUE | TTL 30 jours |
| `athlete_perfs_manuelles` | id_perf | Perfs saisies manuellement |
| `logs` | id_log, ts, ip, sid, action, page | Tracking activite utilisateur (stocke en BDD) |
| `athlete_follows` | id_follow, email, athlete_id_ext | Suivi athlete par email (UK: email+athlete) |
| `club_follows` | id_follow, email, club_id | Suivi club par email (UK: email+club) |
| `email_subscribers` | id_sub, email, source, detail | Newsletter + PDF (UK: email+source) |

## Cache systeme
- **Emplacement** : `cache/` (fichiers JSON, protege .htaccess)
- **TTL** : 24h (86400s), 7j pour liste random
- **Cle** : MD5 de tous les parametres
- **Prefixes** : `athlete_`, `search_`, `clubstats_`, `villestats_`, `ep_`, `clubs_`, `epreuves_`, `villes_`, `stats_`, `liste_`
- **Vider** : `admin/clear_cache.php` (tout) ou `?prefix=clubstats` (specifique)
- **Bypass** : `?nocache=1` sur stats.php, club_stats.php

## Logging systeme

### Log BDD (JS → MySQL)
- **API** : `api/log.php` (POST batch en BDD, GET lecture avec filtres)
- **Stockage** : table `logs` en MySQL
- **JS** : batch toutes les 2s + sendBeacon au depart de page
- **Actions** : page_view, click_link, click_button, form_submit, input_change, copy, page_leave, js_error, navigation
- **Donnees** : IP (auto CloudFlare/proxy), UA, session ID, page, action, detail, screen, langue, referrer, duree
- **Visualisation** : `admin/logs.php` — acces restreint a `luvumbu.n@gmail.com`

### Log IP universel (PHP → fichier JSON)
- **Moteur** : `core/ip_logger.php` — appele via `logIp()` sur chaque page
- **Stockage** : `logs/ip_track_YYYY-MM.php` (JSON protege par `<?php die(); ?>`)
- **Rotation** : mensuelle automatique
- **Donnees par IP** : count, first/last visit, pages, dernières 100 requetes, UA
- **Donnees globales** : total_visits, unique_ips, daily stats, dernières 500 requetes (time, ip, page, url, method, referrer, ua)
- **Viewer** : `logs/ip_view.php` — auth email whitelist, params `?month=`, `?ip=`, `?raw=1`
- **Pages avec logIp()** : index.php, api/config.php, pages/*.php, login.php, register.php

### Rate limiting (IP)
- **Limite** : 20 requetes/jour par IP non connectee (`IP_DAILY_LIMIT`)
- **Compteurs** : fichiers legers `logs/ip_daily_YYYY-MM-DD.php` (separes du log principal)
- **Page blocage** : HTTP 429 avec CTA inscription/connexion (`showRateLimitPage()`)
- **Whitelist** : IPs serveur Hostinger + tous les ranges Google (Googlebot, etc.)
- **Constantes** : `IP_WHITELIST` (IPs exactes), `IP_GOOGLE_PREFIXES` (prefixes 66.249.*, 142.250.*, etc.)
- **Fonction** : `isWhitelistedIp($ip)` — check whitelist + prefixes Google
- **Exemptions** : users connectes (cookie `bk_token`/`bk_sa_token`), pages login/register/auth, bots connus, IPs whitelistees

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
- **Roles** : athlete (defaut), coach, club, admin
- **Hash** : `password_hash()` BCRYPT
- **Sessions** : token 64 chars hex, cookie `bk_token` (httpOnly, sameSite=Lax), TTL 30j
- **Fonctions** : `hashPassword()`, `verifyPassword()`, `generateToken()`, `createSession()`, `getCurrentUser()`, `requireAuth()`, `requireRole()`, `logout()`

### Super Admin
- **Login** : meme formulaire `login.php` (input type="text"), detecte si email = BDD username + password = BDD password
- **Cookie** : `bk_sa_token` (7 jours), stocke dans `logs/.sa_sessions.php`
- **Dashboard** : `admin/panel.php` — 12 sections (overview, requetes temps reel, activite horaire, top IPs, users, BDD info, etc.)
- **Detection** : `api/auth/login.php` compare avec `$username`/`$password` de `core/credentials.php`

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
- **AdSense** : Google AdSense (ca-pub-7899923856846249) dans `<head>`
- **Footer SEO** : liens internes vers toutes les pages principales

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
- FK vers epreuves/villes/clubs = ON DELETE SET NULL (pas CASCADE)
- FK vers athletes = ON DELETE CASCADE (supprime toutes les donnees liees)
