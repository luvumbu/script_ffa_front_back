# Refonte : Environnement Local + Archivage BDD + SEO masquage

Documentation complete de tous les changements apportes dans cette session.

## Table des matieres

1. [Systeme d'archivage BDD reversible](#1-systeme-darchivage-bdd-reversible)
2. [Environnement local (XAMPP)](#2-environnement-local-xampp)
3. [Mode API distante (local + API prod)](#3-mode-api-distante)
4. [Helpers URLs adaptatives](#4-helpers-urls-adaptatives)
5. [Anti-cache navigateur en local](#5-anti-cache-navigateur-en-local)
6. [SEO : Masquage profils (visible=0)](#6-seo--masquage-profils)
7. [Centre d'outils admin (panel + tabs)](#7-centre-doutils-admin)
8. [Liste des fichiers crees/modifies](#8-liste-des-fichiers-creesmodifies)

---

## 1. Systeme d'archivage BDD reversible

### Objectif
Permettre d'exporter une table BDD vers un fichier `.jsonl` portable, puis de la
vider de la BDD pour liberer de l'espace. A tout moment, on peut restaurer.
Inclut une bascule "Source de lecture" (BDD ou Fichier) par table.

### Fichiers crees

| Fichier | Role |
|---|---|
| `admin/db_archive.php` | UI complete export/import/bascule + diagnostic |
| `admin/db_size.php` | Diagnostic taille de chaque table |
| `core/data_source.php` | Helpers (dataSourceMode, loadArchive, streamArchive) |
| `config/data_source.json` | Config : { "logs": "file" } |
| `archives/.htaccess` | Deny from all (protection web) |
| `core/visibility.php` | Helper visibilite athletes |

### Format fichier .jsonl

```
#META {"table":"logs","exported_at":"2026-05-13 14:30:12","columns":[...],"create_sql":"CREATE TABLE..."}
{"id_log":1,"ts":"2026-05-01 10:00:00","ip":"1.2.3.4",...}
{"id_log":2,...}
```

- Streaming-friendly (1 row par ligne)
- CREATE TABLE inclus dans META : portable, recreable sur n'importe quelle BDD
- Lisible humainement

### Workflow

```
┌─ Export ────────────────┐  ┌─ Vider ─────────────────┐  ┌─ Restore ──────────────┐
│ BDD --> fichier .jsonl  │  │ TRUNCATE TABLE (apres   │  │ Fichier --> BDD        │
│ (copie)                 │  │ verification fichier)   │  │ (reinjecte les rows)   │
└─────────────────────────┘  └─────────────────────────┘  └────────────────────────┘
```

### Securites integrees

| Securite | Detail |
|---|---|
| `bk_key` requis | Auth via URL param ou cookie bk_sa_token |
| `.htaccess Deny from all` | .jsonl non telechargeables via URL directe |
| Verification avant truncate | (1) fichier present, (2) #META valide, (3) colonnes matchent, (4) nb lignes exact, (5) JSON parseable |
| Confirmation JS | Sur toutes les actions critiques |
| `SET FOREIGN_KEY_CHECKS = 0` | Pendant import, remis a 1 ensuite |
| Transaction begin/commit | Rollback auto si erreur restore |
| Bouton "Vider" grise | Si aucune archive presente |
| Bouton "Forcer" | Pour fichiers corrompus (bypass verif) |

### Anti-timeout Hostinger (503)

Tous les exports/imports sont **chunked AJAX** :
- Export : 5 000 rows par requete HTTP (~5s chacune)
- Restore : 2 000 rows par requete HTTP
- 300ms entre chunks pour eviter le rate-limiting Hostinger
- Retry infini avec backoff exponentiel (1s -> 2s -> 4s, cap 30s) sur erreurs 5xx/429/network

### UI temps reel

L'overlay pendant les operations affiche :

```
- Spinner orbital double avec core qui pulse
- "..." animes dans le titre
- Barre de progression "%"
- Compteur lignes : 145 000 / 500 000
- Temps ecoule : 01:23
- Temps restant (ETA) : 3m 47s  ← calcul dynamique
- Vitesse : 6800/s              ← moyenne 30/70 (globale + recente)
- Fin estimee : 14:35:47
- Chunks traites : 12 / 100
- Derniere reponse : 248 ms
- Volume traite : 8.4 MB
- Tentatives retry : 0
- Derniere activite : a l'instant  ← heartbeat
- Console de logs avec timestamps
```

### Bascule BDD <-> Fichier (per table)

`config/data_source.json` :
```json
{ "logs": "file", "search_tracking": "bdd" }
```

- Mode `bdd` : le code lit dans MySQL (defaut)
- Mode `file` : le code lit le `.jsonl` (memoire ou streaming)
- Bouton "→ Fichier" : Export + Verify + Truncate + setSource=file (atomique)
- Bouton "→ BDD" : Restore + setSource=bdd
- `admin/logs.php` est patche pour honorer le mode (autres tables : a etendre)

### Installation portable

Le fichier `.jsonl` contient le `CREATE TABLE`. Tu peux :
1. Exporter une table sur prod
2. Telecharger le `.jsonl`
3. Mettre dans `archives/` d'une autre BDD (locale ou autre)
4. Cliquer **Installer** dans `db_archive.php`
   - Verifie si la table existe
   - Si non : execute le CREATE TABLE
   - Restore les donnees

---

## 2. Environnement local (XAMPP)

### Objectif
Permettre de developper en local sans alterer la prod. BDD locale separee,
credentials separes, masquage Google OAuth.

### Fichiers crees/modifies

| Fichier | Role |
|---|---|
| `core/credentials_local.php` | Credentials locaux (MySQL + auth) |
| `core/db.php` | Detection auto local/prod, anti-cache |
| `core/paths.php` | BK_BASE, BK_URL(), BK_HOST, BK_IS_LOCAL |
| `admin/local_setup.php` | Page setup local avec auth + verif tables |
| `login.php` | Cache Google OAuth en local |
| `register.php` | Redirige vers login en local |

### credentials_local.php (a NE PAS uploader sur Hostinger)

```php
// MySQL local
$dbname   = "bk_local";
$username = "root";
$password = "";  // XAMPP defaut

// Auth local_setup.php (independant de MySQL)
$localAuthUser = "root";
$localAuthPass = "root";
```

### Detection automatique

```php
$isLocal = (
    strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
    PHP_SAPI === 'cli'
);

if ($isLocal && file_exists(__DIR__ . '/credentials_local.php')) {
    require __DIR__ . '/credentials_local.php';  // OVERRIDE prod
}
```

### Page local_setup.php

URL : `http://localhost/BK/admin/local_setup.php`

**5 onglets** :
1. **Vue d'ensemble** : KPI (Tables attendues / Presentes / Manquantes / Lignes) + creation auto
2. **Outils admin** : 8 cards (Archive Manager, Diagnostic, Setup BDD, Logs, Cache, etc.)
3. **Chemins fichiers** : tous les paths importants (config, donnees, logs, code)
4. **Extraction donnees** : 3 methodes (Archive Manager, API directe, Remote Check SQL)
5. **Routes du site** : toutes les pages publiques cliquables

### Auth setup local

Apres login `root/root` :
- Session PHP `$_SESSION['local_setup_ok']`
- Cookie `bk_sa_token` (super admin) cree + stocke dans `logs/.sa_sessions.php`
- Cookie valable 30 jours
- Auto-reconnexion si cookie deja present (pas de re-saisie)

### Creation auto des tables

Bouton **"Creer toutes les tables manquantes"** :
1. Execute `core/dbCheck_athle.php` (29 tables principales)
2. Cree 4 tables additionnelles (sent_emails, contact_confirm_tokens, profile_reports, profile_hide_tokens)
3. Total : **33 tables** vides avec FK CASCADE/SET NULL

### Hide Google OAuth en local

Dans `login.php` :
- Bandeau jaune "Mode local detecte"
- Onglets Connexion/Admin caches
- Bouton Google cache
- Formulaire email user cache
- Seul l'onglet Admin reste visible

Dans `register.php` :
- Redirige direct vers `login.php` (OAuth Google ne marche pas en local)

---

## 3. Mode API distante

### Objectif
En local, utiliser l'API prod (bokonzi.com) au lieu d'avoir besoin d'une BDD
locale complete. Front local + data prod = developpement immediat sans import.

### Implementation

```php
// index.php ligne 8
$BASE_API = BK_IS_LOCAL ? 'https://bokonzi.com/api' : BK_URL('/api');
```

### Auto-injection cle API (bypass rate limits)

```js
// JS patch global de fetch en local
window.fetch = function(url, opts) {
    if (url.indexOf('bokonzi.com') !== -1 && url.indexOf('bk_key=') === -1) {
        url += (url.indexOf('?') === -1 ? '?' : '&') + 'bk_key=bk_s3cr3t_2026_xK9mP';
    }
    return originalFetch(url, opts);
};
```

```php
// PHP apiCall patch
if (BK_IS_LOCAL && strpos($url, 'bokonzi.com') !== false && strpos($url, 'bk_key=') === false) {
    $url .= (strpos($url, '?') === false ? '?' : '&') . 'bk_key=bk_s3cr3t_2026_xK9mP';
}
```

### Anti-CORS preflight

**Important** : ne PAS ajouter de header `X-BK-KEY` cote JS. Sinon le navigateur
declenche un OPTIONS preflight que prod refuse (CORS autorise uniquement
Content-Type et Authorization).

Solution : utiliser uniquement `bk_key` en URL param.

### Badge search quota local

En local, le badge nav affiche **toujours `∞`** :
```php
if (BK_IS_LOCAL) { $_slIsSA = true; $_slLimit = -1; }
```
JS `_updateSearchQuota()` ignore les valeurs prod en local.

### Verification tables (off par defaut)

Le redirect auto vers `local_setup.php` quand `athletes` manque est **desactive**
par defaut (puisqu'on utilise l'API distante). Pour reactiver :
```php
// dans credentials_local.php :
$checkLocalTables = true;
```

---

## 4. Helpers URLs adaptatives

### core/paths.php

```php
BK_BASE       // '' en prod, '/BK' en local
BK_HOST       // 'bokonzi.com' ou 'localhost'
BK_IS_LOCAL   // bool

BK_URL($path) // 'https://bokonzi.com/X' ou 'http://localhost/BK/X'
```

### Nav links adaptatifs

Dans `index.php` :
```php
$_canonBase = BK_IS_LOCAL
    ? 'http://' . $_SERVER['HTTP_HOST'] . BK_BASE
    : 'https://bokonzi.com';
```

Tous les `href="/recherche"` ont ete remplaces par :
```html
<a href="<?= BK_BASE ?>/recherche">  <!-- /BK/recherche en local, /recherche en prod -->
```

### Logs : URL des pages affichees

`admin/logs.php` :
```php
$fullUrl = BK_URL($pg);  // au lieu de 'https://bokonzi.com/' . $pg
```

---

## 5. Anti-cache navigateur en local

`core/db.php` ajoute en local :
```php
if ($isLocal && PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
```

**Resultat** : plus besoin de Ctrl+F5 en developpement. Chaque page est fraiche.

---

## 6. SEO : Masquage profils

### Objectif
Quand un profil athlete est masque (`visible=0`), il ne doit **JAMAIS** apparaitre
nulle part : ni en SEO Google, ni dans les listes du site. Seul l'admin
(cookie `bk_sa_token`) peut le voir.

### Protection page profil (4 niveaux)

```
1. HTTP 404 Not Found
   └─ Google interprete : page n'existe pas, deindexe

2. Meta robots dans le HTML
   └─ <meta name="robots" content="noindex, nofollow, noarchive">

3. Header HTTP X-Robots-Tag
   └─ X-Robots-Tag: noindex, nofollow, noarchive

4. Content vide (ou page d'erreur si admin)
```

Fichiers : `index.php` (lignes 380-402), `pages/profil.php` (lignes 76-98).
Status code : **404** (passe de 410 a 404 sur demande utilisateur).

### Filtre helper visibility.php

```php
isAdminViewing()           // true si cookie bk_sa_token valide ou ?bk_key=...
athleteVisibilityClause('a')  // "a.visible = 1" si non-admin, "1=1" si admin
athleteVisibilityAnd('a')     // " AND a.visible = 1" si non-admin, "" si admin
```

### Pattern SQL applique

```sql
JOIN athletes a ON a.id_athlete = ar.id_athlete AND (a.visible = 1 OR 1={$_isAdminInt})
```

- `$_isAdminInt = 1` si admin -> `(visible=1 OR 1=1)` = TRUE -> tout visible
- `$_isAdminInt = 0` si non-admin -> `(visible=1 OR 1=0)` = visible=1 -> filtre

### API patches

| Endpoint | Statut | Detail |
|---|---|---|
| `api/club_stats.php` | Patche | 8 jointures (ac, ar, ap, am, ares, comb, n, s) |
| `api/ville_stats.php` | Patche | 3 jointures (ar, am, r) |
| `api/epreuve_stats.php` | Patche | 2 jointures (ar, am) |
| `api/classement.php` | Patche | 1 jointure (p) |
| `api/search.php` | Deja | `WHERE a.visible = 1` |
| `api/stats.php` | Deja | `WHERE a.visible = 1` |
| `api/liste.php` | Deja | `WHERE a.visible = 1` |
| `api/similar.php` | Deja | `WHERE a.visible = 1` |
| `api/same_day_perf.php` | Deja | `WHERE (a.visible = 1 OR ...)` |
| `api/top_searched.php` | Deja | `WHERE a.visible = 1` |
| `api/athlete.php` | Deja | 404 si `visible=0` (sauf `?_all=1`) |
| `sitemap.php` | Deja | `WHERE visible = 1` |

### Apres patch : vider le cache

Les anciennes reponses JSON dans `cache/*.json` contiennent encore les hidden.
Apres upload des patches, faire :
```
https://bokonzi.com/admin/clear_cache.php
```

### Test admin vs non-admin

- **Non-admin** : aucun nom de hidden visible sur le site
- **Admin** (cookie `bk_sa_token` ou `?bk_key=...`) : tous les hidden visibles
  - Bandeau rouge sur la fiche masquee
  - Acces au panel pour debloquer

---

## 7. Centre d'outils admin

### Onglets dans `admin/panel.php`

Le panel principal a maintenant **9 onglets** au lieu de 5 :

```
ATHLETES | NIVEAUX | INSCRIPTION | MAILS | REPORTS
  + Outils | Chemins | Extraction | Routes
```

### Page autonome `admin/tools.php`

URL unifiee : `http(s)://[host]/admin/tools.php`
- Marche en local ET en prod
- Auth via cookie `bk_sa_token` OU `?bk_key=...`
- 4 onglets : Outils / Chemins / Extraction / Routes

### Bouton dans header panel

`admin/panel.php` header :
```html
<a href="tools.php" style="...">⚙ Outils admin</a>
```

### Contenu des onglets

**Outils admin (8 cards)** :
- Archive Manager (PRINCIPAL)
- Diagnostic taille BDD
- Setup BDD complete
- Setup local + verif tables
- Visualisation Logs
- Vider le cache (Tout / Clubs / Search)
- Remote Check API (count, users, ping)
- Fix performance INT

**Chemins fichiers** : configuration, donnees/archives, logs, code applicatif.

**Extraction donnees (3 methodes)** :
- Archive Manager (recommande)
- API directe JSON (stats, clubs, epreuves, villes)
- Remote Check SQL (SELECT lecture seule)

**Routes du site** : Accueil, Recherche, Athletes, Clubs, Epreuves, Villes, Comparer, Tuto, Profil, Login, Panel.

### URLs adaptatives

Toutes les URLs des cards utilisent `$_localBase` qui s'adapte :
- Local : `http://localhost/BK/admin/db_archive.php?bk_key=...`
- Prod : `https://bokonzi.com/admin/db_archive.php?bk_key=...`

### Bouton "Copier URL"

Chaque card a un bouton **Copier** qui copie l'URL dans le presse-papier
(via `navigator.clipboard.writeText`) avec feedback visuel "Copie !".

---

## 8. Liste des fichiers crees/modifies

### Nouveaux fichiers

| Fichier | Description |
|---|---|
| `admin/db_archive.php` | UI export/import/bascule BDD |
| `admin/db_size.php` | Diagnostic taille tables |
| `admin/local_setup.php` | Setup local + tabs |
| `admin/tools.php` | Centre d'outils unifie |
| `core/data_source.php` | Helper bascule BDD/Fichier |
| `core/credentials_local.php` | Credentials locaux + auth |
| `core/paths.php` | BK_BASE, BK_URL, BK_IS_LOCAL |
| `core/visibility.php` | Helper filtre visible athletes |
| `config/data_source.json` | Config sources |
| `archives/.htaccess` | Deny from all |
| `docs/REFONTE_LOCAL_ET_ARCHIVAGE.md` | Cette doc |

### Fichiers modifies

| Fichier | Changements |
|---|---|
| `core/db.php` | Detection local/prod, anti-cache headers, check tables |
| `index.php` | $BASE_API adaptatif, $_canonBase adaptatif, fetch patch JS, profil 410->404, badge local force `∞`, href="/X" -> href="<?=BK_BASE?>/X" |
| `login.php` | Hide Google + form user en local |
| `register.php` | Redirect vers login en local |
| `pages/profil.php` | 410 -> 404 sur hidden |
| `admin/panel.php` | + bouton "Outils admin" header, + 4 nouveaux onglets (tools/paths/extract/routes) |
| `admin/logs.php` | Lecture mode BDD ou Fichier, bandeau orange si mode file, $fullUrl via BK_URL() |
| `api/club_stats.php` | Filtre visible sur 8 jointures |
| `api/ville_stats.php` | Filtre visible sur 3 jointures |
| `api/epreuve_stats.php` | Filtre visible sur 2 jointures |
| `api/classement.php` | Filtre visible sur 1 jointure |

### Variables d'environnement automatiques

| Variable | Local | Prod |
|---|---|---|
| `BK_BASE` | `/BK` | `''` |
| `BK_HOST` | `localhost` | `bokonzi.com` |
| `BK_IS_LOCAL` | `true` | `false` |
| `BK_URL('/x')` | `http://localhost/BK/x` | `https://bokonzi.com/x` |
| `$BASE_API` (index.php) | `https://bokonzi.com/api` | `https://bokonzi.com/api` |
| `$_canonBase` (index.php) | `http://localhost/BK` | `https://bokonzi.com` |

---

## Quick reference : URLs

### Outils admin (avec bk_key)

```
Local : http://localhost/BK/admin/db_archive.php?bk_key=bk_s3cr3t_2026_xK9mP
Prod  : https://bokonzi.com/admin/db_archive.php?bk_key=bk_s3cr3t_2026_xK9mP

Local : http://localhost/BK/admin/db_size.php?bk_key=...
Prod  : https://bokonzi.com/admin/db_size.php?bk_key=...

Local : http://localhost/BK/admin/local_setup.php
Prod  : https://bokonzi.com/admin/local_setup.php

Local : http://localhost/BK/admin/tools.php
Prod  : https://bokonzi.com/admin/tools.php

Local : http://localhost/BK/admin/panel.php
Prod  : https://bokonzi.com/admin/panel.php
```

### Cle API

```
bk_s3cr3t_2026_xK9mP
```

Utilisation : `?bk_key=bk_s3cr3t_2026_xK9mP`

### Credentials par defaut

```
Local (MySQL)           : root / (vide)
Local (local_setup.php) : root / root
Prod (Super Admin)      : u489596434_bokonzi_on / [voir credentials.php]
```

---

## Workflow type : developper localement

```
1. Ouvre http://localhost/BK/admin/local_setup.php
2. Login : root / root
3. La table 'athletes' n'existe pas en local -> pas grave, mode API distante actif
4. Va sur http://localhost/BK/recherche -> donnees viennent de bokonzi.com
5. Modifie du code en local -> recharge la page (anti-cache off)
6. Quand satisfait : upload les fichiers modifies sur Hostinger

OPTIONNEL : import donnees prod en local
1. Sur prod : Archive Manager -> Export d'une table
2. Telecharger le .jsonl
3. En local : Archive Manager -> Installer
   -> Cree la table + injecte les donnees
```

## Workflow type : archiver une table prod

```
1. https://bokonzi.com/admin/db_archive.php?bk_key=...
2. Clic "→ Fichier" sur la table a archiver
3. Le systeme :
   a) Export par chunks de 5000 lignes
   b) Verification (lignes BDD == lignes fichier)
   c) Si OK : TRUNCATE TABLE + source=file
   d) Si KO : rien ne se passe, BDD intacte
4. La table est videe en BDD, les donnees sont dans archives/
5. Pour restaurer : clic "→ BDD" -> reinjecte
```

## Workflow type : masquer un athlete

```
1. Admin panel -> Section signalements
2. Clic "Masquer le profil" sur un athlete
3. SQL : UPDATE athletes SET visible = 0 WHERE id_athlete = X
4. Effets automatiques :
   - /profil/X retourne 404 + noindex + X-Robots-Tag
   - Absent du sitemap.xml
   - Absent de toutes les listes (search, club, ville, epreuve)
   - Admin (cookie bk_sa_token) le voit toujours avec bandeau rouge
5. Vider le cache : /admin/clear_cache.php
6. Google deindexe sous 1-7 jours
```

---

**Date** : 2026-05-13
**Auteur** : Refonte assistee par Claude
