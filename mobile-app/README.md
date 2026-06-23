# Bokonzi — Application Android

Application Android **native** qui emballe le site [bokonzi.com](https://bokonzi.com)
via **Capacitor**. L'app charge directement le site en ligne (`server.url`) :
toute mise à jour du site se reflète instantanément dans l'app, sans republier.

- **Nom** : Bokonzi
- **Package (applicationId)** : `com.bokonzi.app`
- **Min Android** : 7.0 (API 24) — **Cible** : API 36
- **URL chargée** : `https://bokonzi.com` (voir `capacitor.config.json`)

---

## Prérequis (déjà présents sur cette machine)

| Outil | Rôle | Statut machine |
|-------|------|----------------|
| Node + npm | Capacitor CLI | ✅ Node 25 / npm 11 |
| Android SDK | Build Android | ✅ `…\AppData\Local\Android\Sdk` |
| **JDK 17+** | Compilation | ✅ JBR 21 fourni par Android Studio (`…\Android Studio\jbr`) |
| Android Studio | Build / signature / émulateur | ✅ installé |

> ⚠️ Le `java` global est en version 8 (trop vieux). On utilise le **JDK 21 d'Android Studio**.
> En ligne de commande il faut donc pointer `JAVA_HOME` dessus (voir ci-dessous).
> Dans Android Studio, c'est automatique.

---

## Construire l'APK (ligne de commande)

```powershell
# Depuis mobile-app/android
$env:JAVA_HOME = "C:\Program Files\Android\Android Studio\jbr"
$env:ANDROID_SDK_ROOT = "C:\Users\maste\AppData\Local\Android\Sdk"
.\gradlew.bat assembleDebug
```

APK de test généré dans :
`android\app\build\outputs\apk\debug\app-debug.apk`

→ Copie ce fichier sur ton téléphone (USB / Drive / mail) et installe-le
(autorise « sources inconnues »). C'est suffisant pour **tester**.

## Construire via Android Studio (recommandé pour publier)

```powershell
# Depuis mobile-app/
npm run open      # ouvre le projet android/ dans Android Studio
```

Dans Android Studio : **Build > Build Bundle(s)/APK(s)**, ou
**Build > Generate Signed Bundle / APK** pour le Play Store (voir ci-dessous).

---

## Publier sur Google Play

1. **Compte développeur Google Play** : 25 $ une fois — https://play.google.com/console
2. **Générer une clé de signature** (à garder précieusement, jamais committée) :
   ```powershell
   & "C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe" -genkey -v `
     -keystore bokonzi-release.jks -keyalg RSA -keysize 2048 -validity 10000 `
     -alias bokonzi
   ```
3. **Construire l'AAB signé** : Android Studio → *Generate Signed Bundle (Android App Bundle)*.
   Fichier produit : `android\app\build\outputs\bundle\release\app-release.aab`
4. **Play Console** : créer l'app → uploader l'`.aab` → fiche (icône, captures,
   description, politique de confidentialité) → soumettre. Validation : quelques jours.

> Incrémente `versionCode` (entier) et `versionName` à chaque nouvelle version,
> dans `android/app/build.gradle`.

---

## Icône & écran de démarrage (splash)

Par défaut, l'icône est le logo Capacitor. Pour mettre le logo Bokonzi :

```powershell
# place une image carrée 1024x1024 dans mobile-app/assets/icon.png
npm i -D @capacitor/assets
npx capacitor-assets generate --android
```

---

## ⚠️ Limitation connue : connexion Google

Le site se connecte **uniquement via Google OAuth**. Or Google **refuse**
l'authentification OAuth dans une WebView embarquée (erreur `disallowed_useragent`).

➡️ Conséquence : la **navigation et la consultation des données fonctionnent**,
mais le **bouton « Se connecter avec Google » échouera dans l'app**.

**Solutions possibles** (à décider) :
- Ouvrir le login dans un **onglet système (Custom Tab)** via le plugin
  `@capacitor/browser`, puis récupérer la session (recommandé).
- Ou passer l'app en **TWA (Trusted Web Activity)** : utilise Chrome → OAuth autorisé.
- Ou ajouter un login **email/mot de passe** côté site pour l'app.

Dis-le moi et je l'implémente.

---

## Configuration

Toute la config est dans **`capacitor.config.json`** :

- `server.url` : le site chargé. Pour tester en local sur XAMPP depuis l'émulateur,
  remplace par `http://10.0.2.2` (l'hôte vu depuis l'émulateur) **et** passe
  `cleartext` à `true` (HTTP non chiffré autorisé en dev uniquement).
- `server.errorPath` : page locale affichée si le site est injoignable (`www/error.html`).
- `android.backgroundColor` : couleur de fond avant chargement.

Après toute modification de config ou des fichiers `www/`, resynchronise :
```powershell
npm run sync
```
