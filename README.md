# BOKONZI

Base de donnees complete de l'athletisme francais. Explorez **330 000+ athletes**, **3 000+ clubs**, **400+ epreuves**, avec records, medailles, podiums, selections et statistiques detaillees.

**Production :** [https://bokonzi.com](https://bokonzi.com)

---

## Apercu

Bokonzi est une plateforme d'analyse de l'athletisme francais. Elle structure les donnees de 330 000+ athletes en base de donnees MySQL et les presente dans un dashboard interactif complet avec API REST, graphiques, comparaisons et fiches detaillees.

### Fonctionnalites principales

- **Recherche avancee** — 12 filtres combinables (nom, club, epreuve, sexe, categorie, nationalite, ville, competition, medaille, annee, licence)
- **Fiches athletes** — Profil complet avec records, progressions, medailles, podiums, selections, niveaux, biographie auto-generee
- **Panneaux clubs** — 5 onglets (epreuves, nationalites, records, stats, resume) avec filtres par nationalite/sexe/categorie
- **Panneaux epreuves** — 4 onglets (records, nationalites, stats, resume) avec classements
- **Pages villes** — Statistiques par lieu de competition avec filtres niveaux/nationalites/annees
- **Comparateur** — Comparaison visuelle d'athletes et de clubs avec graphiques
- **Tutoriel interactif** — Guide anime etape par etape
- **Graphiques** — Chart.js pour doughnut, barres, lignes, radar
- **SEO avance** — Schema.org JSON-LD, Open Graph, Twitter Cards, sitemap XML
- **Logging** — Suivi des actions utilisateur (batch, sendBeacon)
- **API REST** — 20+ endpoints JSON avec cache fichier 24h

---

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | PHP 8+ / MySQL (mysqli) |
| Frontend | HTML / CSS / JS vanilla |
| Graphiques | Chart.js 4.4.7 |
| Serveur local | XAMPP (Apache) |
| Serveur prod | Hostinger |
| Framework | Aucun — tout fait main |

---

## Installation locale

### Pre-requis
- XAMPP (PHP 8+, MySQL, Apache)
- Git

### Etapes

```bash
# 1. Cloner le projet dans htdocs
cd C:\xampp\htdocs
git clone <repo-url> BK

# 2. Configurer les identifiants BDD
# Editer core/credentials.php avec vos identifiants MySQL

# 3. Creer la base de donnees
# Acceder a : http://localhost/BK/admin/setup_bdd.php

# 4. Acceder au dashboard
# http://localhost/BK/index.php
```

### Configuration BDD

Editer `core/credentials.php` :
```php
$dbname   = "bokonzi";
$username = "root";
$password = "";
```

---

## Architecture du projet

```
BK/
├── api/                 API REST JSON (20+ endpoints, cache 24h)
│   ├── config.php       Headers CORS + connexion BDD + jsonResponse()
│   ├── athlete.php      Profil complet athlete (?id= ou ?id_athlete=)
│   ├── search.php       Recherche multi-criteres (12 filtres)
│   ├── club_stats.php   Stats club (?id=, ?nom=, ?annee=, ?nationalite=...)
│   ├── ville_stats.php  Stats ville (?nom=, ?niv=, ?nat=, ?ans=)
│   ├── epreuve_stats.php Stats epreuve (?nom=, ?sexe=, ?categorie=)
│   ├── clubs.php        Liste clubs paginee
│   ├── epreuves.php     Liste epreuves paginee
│   ├── villes.php       Liste villes paginee
│   ├── stats.php        Stats globales (?detail=1, ?top=, ?nocache=1)
│   ├── classement.php   Classement par epreuve
│   ├── liste.php        Liste athletes paginee (8 ordres de tri)
│   ├── log.php          Logging actions (POST batch, GET lecture)
│   ├── performances.php CRUD performances manuelles
│   └── auth/            Authentification
│       ├── login.php    POST email/password → token
│       ├── register.php POST inscription → token
│       ├── logout.php   POST deconnexion
│       └── me.php       GET utilisateur courant
├── cache/               Cache JSON fichier (TTL 24h)
├── core/                Noyau applicatif
│   ├── credentials.php  Identifiants BDD
│   ├── db.php           Connexion mysqli
│   ├── auth.php         Authentification (hash, sessions, roles)
│   ├── dbCheck_athle.php Schema BDD (18 tables, 30+ FK)
│   ├── insert_athle.php Import donnees scraping
│   ├── seo.php          Generation meta/OG/JSON-LD
│   └── paths.php        Constante BK_BASE
├── admin/               Administration
│   ├── setup_bdd.php    Creation BDD + tables
│   ├── drop_all.php     Suppression tables
│   ├── reset.php        Remise a zero
│   └── clear_cache.php  Vider le cache (?prefix=)
├── Class/               Classes utilitaires (53 fichiers)
│   └── DatabaseHandler.php  Wrapper BDD (ORM leger)
├── pages/               Pages standalone (profil, recherche, classement...)
├── logs/                Logs JSON quotidiens (protege .htaccess)
├── docs/                Documentation technique
├── index.php            DASHBOARD PRINCIPAL (~5500 lignes PHP+HTML+JS)
├── dashboard.css        Styles du dashboard (~550 lignes)
├── common.css           Styles globaux
├── login.php            Page connexion
├── register.php         Page inscription
├── nav.php              Template navigation
├── panel.php            Panneau admin
├── sitemap.php          Generation sitemap XML
└── robots.txt           Configuration robots SEO
```

---

## Pages du dashboard

| Page | URL | Description |
|------|-----|-------------|
| Accueil | `?page=accueil` | Stats globales, graphiques, top clubs/epreuves/athletes |
| Athletes | `?page=athletes` | Liste paginee avec recherche live |
| Recherche | `?page=recherche` | Recherche avancee 12 filtres + barre live |
| Profil | `?page=profil&id=X` | Fiche athlete complete + bio auto-generee |
| Clubs | `?page=clubs` | Liste clubs + panneau detail 5 onglets |
| Epreuves | `?page=epreuves` | Liste epreuves + panneau detail 4 onglets |
| Villes | `?page=villes` | Liste villes + detail avec filtres avances |
| Comparer | `?page=comparer` | Comparaison athletes/clubs (panier localStorage) |
| Tuto | `?page=tuto` | Tutoriel interactif anime (8 sections) |

---

## API REST

Tous les endpoints retournent du JSON avec headers CORS. Cache fichier 24h par defaut.

### Endpoints principaux

| Endpoint | Methode | Parametres cles | Description |
|----------|---------|-----------------|-------------|
| `/api/athlete.php` | GET | `id` (externe) ou `id_athlete` (interne) | Profil complet |
| `/api/search.php` | GET | `nom`, `club`, `epreuve`, `sexe`, `categorie`, `nationalite`, `ville`, `competition`, `medaille`, `annee`, `licence`, `page`, `limit` | Recherche multi-criteres |
| `/api/club_stats.php` | GET | `id`/`nom`, `annee`, `rp`, `ep`, `nationalite`, `sexe`, `categorie` | Stats detaillees club |
| `/api/epreuve_stats.php` | GET | `nom`, `page`, `limit`, `sexe`, `categorie` | Stats detaillees epreuve |
| `/api/ville_stats.php` | GET | `nom`, `niv`, `nat`, `ans`, `page`, `limit` | Stats detaillees ville |
| `/api/stats.php` | GET | `detail`, `top`, `nocache` | Stats globales plateforme |
| `/api/clubs.php` | GET | `nom`, `page`, `limit` | Liste clubs |
| `/api/epreuves.php` | GET | `nom`, `page`, `limit` | Liste epreuves |
| `/api/villes.php` | GET | `nom`, `page`, `limit` | Liste villes |
| `/api/classement.php` | GET | `epreuve`, `sexe`, `categorie`, `annee` | Classement par epreuve |
| `/api/liste.php` | GET | `page`, `limit`, `ordre` | Liste athletes triee |
| `/api/log.php` | POST/GET | `events` (POST), filtres (GET) | Logging actions |
| `/api/performances.php` | CRUD | `id_athlete`, `id_epreuve`, etc. | Perfs manuelles |

### Authentification

| Endpoint | Methode | Description |
|----------|---------|-------------|
| `/api/auth/login.php` | POST | Connexion (email + password → token) |
| `/api/auth/register.php` | POST | Inscription (email + password + role) |
| `/api/auth/logout.php` | POST | Deconnexion |
| `/api/auth/me.php` | GET | Utilisateur courant (cookie bk_token) |

---

## Base de donnees

### Schema (18 tables)

**Tables de reference :**
- `athletes` — Entite principale (double ID : `id_athlete` interne + `athlete_id_externe` athle.fr)
- `clubs`, `epreuves`, `villes`, `competitions`, `categories`, `nationalites`

**Tables de donnees athletes :**
- `athlete_clubs` — Affiliations club (avec periodes debut/fin)
- `athlete_records` — Records personnels par epreuve
- `athlete_resultats` — Resultats individuels avec niveau (D1-D8, R1-R6, N1-N4, IE, IR)
- `athlete_progressions` — Evolution annuelle des performances
- `athlete_medailles` — Medailles (or, argent, bronze)
- `athlete_podiums` — Classements podium
- `athlete_selections` — Selections en equipe
- `athlete_niveaux` — Niveaux de qualification annuels
- `athlete_niv_perfs` — Performances par niveau et epreuve

**Tables utilisateurs :**
- `users` — Comptes (roles : athlete, coach, club, admin)
- `user_sessions` — Sessions token (30 jours)
- `coach_athletes` — Liaison coach/athletes
- `athlete_perfs_manuelles` — Performances saisies manuellement

### Systeme de niveaux

| Famille | Codes | Couleur |
|---------|-------|---------|
| Departemental | D1-D8 | Orange |
| Regional | R1-R6 | Cyan |
| National | N1-N4 | Rose |
| International | IE, IR | Fuchsia |

Hierarchie : IE (100) > IR (99) > N1 (90) > ... > D8 (63)

---

## Cache

- **Emplacement :** `/cache/` (fichiers JSON)
- **TTL :** 24 heures (86400 secondes)
- **Cle :** MD5 de tous les parametres de la requete
- **Prefixes :** `athlete_`, `search_`, `clubstats_`, `villestats_`, `ep_`, `clubs_`, `epreuves_`, `villes_`, `stats_`, `liste_`
- **Bypass :** `?nocache=1` sur certains endpoints
- **Vider :** `admin/clear_cache.php` (tout) ou `?prefix=clubstats` (specifique)

---

## Logging

- **Fichiers :** `logs/log_YYYY-MM-DD.json` (JSON Lines, protege .htaccess)
- **Evenements :** page_view, click_link, click_button, form_submit, input_change, copy, page_leave, js_error, navigation
- **Donnees :** IP, user agent, session ID, page, action, detail, screen, langue, referrer, duree
- **Batch :** Flush toutes les 2s + sendBeacon au depart de page

---

## Licence

Projet prive. Tous droits reserves.
