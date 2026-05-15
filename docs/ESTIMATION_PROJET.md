# Estimation de la valeur du projet Bokonzi

**Date** : Avril 2026
**URL** : bokonzi.com
**Type** : Plateforme web de donnees athletisme francais

---

## 1. Resume executif

Bokonzi est une plateforme complete de consultation, analyse et comparaison de donnees d'athletisme francais. Elle agrege les donnees de +300 000 athletes depuis athle.fr, offre des outils d'analyse avances (profils, clubs, epreuves, villes, comparaisons) et un systeme de comptes utilisateurs avec Google OAuth.

---

## 2. Metriques techniques

| Metrique | Valeur |
|----------|--------|
| Lignes de code totales | **~61 000** |
| Fichiers PHP | 188 |
| Fichiers JS | 16 |
| Fichiers CSS | 3 |
| Classes PHP | 53 |
| Fonctions JavaScript | ~375 |
| Requetes SQL | ~315 |
| Tables MySQL | 29 |
| Endpoints API REST | 36 |
| Pages distinctes | 11 |
| Fichier principal (index.php) | ~10 800 lignes |

### Repartition du code

| Type | Lignes | % |
|------|--------|---|
| PHP (backend + templates) | 51 131 | 84% |
| JavaScript (frontend) | 5 723 | 9.4% |
| HTML | 1 660 | 2.7% |
| CSS | 1 128 | 1.9% |
| JSON/Config | 1 188 | 2% |

---

## 3. Fonctionnalites majeures

### 3.1 Donnees et scraping
- Pipeline de scraping parallele (curl_multi, 7 athletes x 3 pages = 21 requetes simultanees)
- Import automatique vers 9 tables MySQL
- Cache memoire pour 0 requete repetitive
- ~300 000 athletes indexes avec records, progressions, medailles, podiums, selections, niveaux
- Temps de scraping complet : ~3.5 jours

### 3.2 Consultation et recherche
- Recherche avancee 12 filtres combinables (nom, club, epreuve, sexe, categorie, nationalite, ville, competition, medaille, annee, licence)
- Recherche multi-mots ordre libre
- Fiches athletes completes (header, clubs, records, medailles, progressions, podiums, resultats, niveaux, bio auto-generee)
- Panneaux club detail (5 onglets, filtres avances, recherche interne)
- Panneaux epreuve detail (4 onglets)
- Page villes avec statistiques et filtres
- Classement par epreuve en temps reel

### 3.3 Analyse et comparaison
- Comparateur athletes et clubs (panier, URLs partageables)
- Bio auto-generee (~500 lignes JS, 18 paragraphes conditionnels)
- Resume club auto-genere (~300 lignes JS, 3 modes)
- Graphiques Chart.js (courbes niveaux, evolution, repartition sexe/categorie/nationalite)
- Profils similaires avec algorithme de similarite base sur le bareme FFA officiel
- Calcul automatique des niveaux FFA via interpolation sur 45 epreuves x 21 niveaux

### 3.4 Bareme FFA integre
- 45 epreuves avec 21 paliers de points (IA=40pts a D5=4pts)
- Conversion performance → points FFA par interpolation lineaire
- Calcul automatique du niveau pour chaque performance (records, progressions, resultats)
- Niveau global de l'athlete calcule meme si absent de la BDD
- Mapping 52 alias noms d'epreuves BDD → noms bareme

### 3.5 Systeme utilisateur
- Authentification Google OAuth 2.0 (Authorization Code Flow, cote serveur)
- Roles : athlete, coach, club, admin
- Sessions 30 jours, cookies securises
- Super Admin avec dashboard 16 sections
- Suivi athletes et clubs par email
- Mon Espace (athletes/clubs suivis, historique recherches)

### 3.6 Securite et performance
- Rate limiting (recherches : 10/jour anonymes, 100/jour connectes)
- Anti-scraping (10 pages/jour anonymes)
- Protection login (5 tentatives/jour)
- Cache fichier JSON 24h sur tous les endpoints
- Systeme de contact avec confirmation email (anti-spam)
- Signalement profil + retrait self-service par email

### 3.7 SEO et monetisation
- Titres dynamiques, canonical URLs, Open Graph, Twitter Cards
- JSON-LD (WebSite, SportsOrganization, Person, BreadcrumbList)
- Sitemap dynamique
- Google Tag Manager
- Google AdSense integre
- ads.txt configure

### 3.8 Administration
- Panel admin 16 sections (overview, requetes, logs, users, analytics, search tracking, contacts, signalements)
- 7 onglets search tracking interactifs avec KPIs et graphiques
- Gestion IPs (blocage, bannissement, whitelist)
- Actions a distance (reset vues, clear cache, test scrape)
- Logging BDD complet (page_view, clicks, forms, errors)
- Logging IP universel (rotation mensuelle)

---

## 4. Infrastructure et integrations

| Integration | Usage |
|-------------|-------|
| Google OAuth 2.0 | Authentification utilisateurs |
| athle.fr | Source de donnees (scraping) |
| Google AdSense | Monetisation publicitaire |
| Google Tag Manager | Analytics et tracking |
| Chart.js 4.4.7 | Graphiques interactifs |
| Systeme email (SMTP) | Confirmations, notifications admin |
| Bareme FFA officiel | Calcul niveaux de performance |

---

## 5. Estimation du temps de developpement

### Par composant

| Composant | Estimation (jours dev senior) |
|-----------|-------------------------------|
| Architecture et setup (BDD, config, auth) | 8 |
| Pipeline scraping (curl_multi, parsing HTML, import BDD) | 15 |
| AthleteScraper (53 classes, parsing athle.fr) | 12 |
| API REST (36 endpoints, cache, rate limiting) | 20 |
| index.php — Pages frontend (11 pages, ~10 800 lignes) | 25 |
| Panneaux club (5 onglets, filtres, recherche, resume, comparaison annees) | 10 |
| Panneaux epreuve (4 onglets, stats, records) | 5 |
| Page profil (header, records, progressions, niveaux, bio auto) | 8 |
| Page recherche (12 filtres, resultats, panneau club auto) | 5 |
| Page comparer (panier, URLs partageables, auto-compare) | 5 |
| Page villes (detail serveur, filtres, resume) | 4 |
| Page tuto (8 sections animees, IntersectionObserver) | 3 |
| Profils similaires (algorithme, bareme FFA, 3 modes) | 6 |
| Systeme suivi (follow athletes/clubs, modal, localStorage) | 3 |
| Systeme contact (confirmation email, rate limit, admin) | 4 |
| Signalement profil + retrait self-service | 4 |
| Search tracking (JS sendBeacon, BDD, panel admin 7 tabs) | 5 |
| Panel admin (16 sections, KPIs, graphiques, actions) | 12 |
| SEO (meta, OG, JSON-LD, sitemap, canonical) | 3 |
| CSS/Design (dashboard.css, responsive, dark theme) | 5 |
| Pages standalone (profil.php, global_athlete.php, etc.) | 4 |
| Logging (BDD + fichier, IP tracker, viewer) | 3 |
| Google OAuth integration | 2 |
| Tests, debug, optimisation, cache | 8 |
| **TOTAL** | **~200 jours** |

### Conversion en cout

| Base | Calcul | Montant |
|------|--------|---------|
| TJM dev senior PHP/JS (France) | 200 jours x 450EUR/jour | **90 000 EUR** |
| TJM dev senior (tarif marche) | 200 jours x 550EUR/jour | **110 000 EUR** |
| Agence web (avec marge) | 200 jours x 800EUR/jour | **160 000 EUR** |

---

## 6. Estimation de la valeur du projet

### 6.1 Valeur de remplacement (cout de reconstruction)

Le cout pour reconstruire ce projet de zero avec un developpeur senior :

| Scenario | Montant |
|----------|---------|
| Developpeur freelance senior | 90 000 - 110 000 EUR |
| Equipe agence (2-3 devs) | 130 000 - 160 000 EUR |
| Avec gestion projet + design | 150 000 - 200 000 EUR |

### 6.2 Valeur des donnees

| Element | Valeur estimee |
|---------|----------------|
| Base de donnees ~300 000 athletes (scrapee, nettoyee, structuree) | 15 000 - 30 000 EUR |
| Pipeline de scraping fonctionnel et optimise | 5 000 - 10 000 EUR |
| Bareme FFA integre (45 epreuves x 21 niveaux) | 2 000 - 5 000 EUR |
| SEO et positionnement Google | 3 000 - 8 000 EUR |

### 6.3 Valeur de marche (potentiel de revenus)

| Source de revenu | Potentiel annuel |
|------------------|------------------|
| Google AdSense (trafic athletics niche) | 1 000 - 5 000 EUR/an |
| Abonnements premium (athletes, coaches, clubs) | 5 000 - 20 000 EUR/an |
| API payante (donnees pour apps tierces) | 3 000 - 15 000 EUR/an |
| Partenariats federations/clubs | 5 000 - 30 000 EUR/an |

### 6.4 Synthese de la valorisation

| Methode | Fourchette basse | Fourchette haute |
|---------|------------------|------------------|
| Cout de remplacement | 90 000 EUR | 200 000 EUR |
| Valeur des actifs (code + donnees + SEO) | 110 000 EUR | 240 000 EUR |
| Multiplicateur revenus (5x revenu annuel potentiel) | 50 000 EUR | 350 000 EUR |
| **Estimation globale** | **100 000 EUR** | **250 000 EUR** |

---

## 7. Points forts differenciants

1. **Niche unique** : pas de concurrent direct en France pour une base athletes aussi complete et accessible
2. **Donnees massives** : +300 000 athletes avec historique complet (records, progressions, medailles, selections)
3. **Algorithme proprietaire** : calcul de similarite base sur le bareme FFA officiel, unique sur le marche
4. **Tout-en-un** : consultation + analyse + comparaison + suivi — pas besoin d'outils externes
5. **Pipeline automatise** : scraping parallele capable de mettre a jour 300k athletes en 3.5 jours
6. **SEO natif** : JSON-LD, sitemap dynamique, meta/OG optimises par page
7. **Zero framework** : code PHP/JS vanilla = aucune dependance, maintenance simplifiee, performances optimales

---

## 8. Points d'amelioration (opportunites de valorisation)

1. **App mobile** : version React Native ou PWA (+30-50% de valeur)
2. **Abonnements premium** : analyses avancees, exports PDF, alertes perf
3. **API commerciale** : acces donnees pour apps tierces, federations, medias
4. **Extension femmes** : bareme femmes (deja extensible dans l'architecture)
5. **Internationalisation** : autres federations europeennes (meme pipeline scraping)
6. **Machine learning** : predictions de performance, detection de talents

---

*Document genere le 3 avril 2026 — Projet Bokonzi (bokonzi.com)*
