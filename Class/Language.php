<?php
/**
 * Language.php — Classe i18n / i18n Class
 * FR: Gestion de l'internationalisation (francais/anglais)
 * EN: Internationalization management (French/English)
 */

class Language {
    /** @var array Traductions chargees / Loaded translations */
    private static $translations = [];

    /** @var string Langue courante / Current language */
    private static $lang = 'fr';

    /**
     * FR: Initialise la langue depuis la session ou la valeur par defaut
     * EN: Initialize language from session or default value
     */
    public static function init($default = 'fr') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['fr', 'en'])) {
            self::$lang = $_SESSION['lang'];
        } else {
            self::$lang = $default;
            $_SESSION['lang'] = $default;
        }

        self::load();
    }

    /**
     * FR: Charge le fichier de traductions correspondant a la langue
     * EN: Load the translation file corresponding to the language
     */
    private static function load() {
        $file = __DIR__ . '/../lang/' . self::$lang . '.php';
        if (file_exists($file)) {
            self::$translations = include $file;
        } else {
            self::$translations = [];
        }
    }

    /**
     * FR: Retourne la traduction pour une cle donnee (fallback = cle brute)
     * EN: Return the translation for a given key (fallback = raw key)
     */
    public static function get($key) {
        return self::$translations[$key] ?? $key;
    }

    /**
     * FR: Retourne la langue courante
     * EN: Return the current language
     */
    public static function getLang() {
        return self::$lang;
    }

    /**
     * FR: Change la langue en session
     * EN: Change the language in session
     */
    public static function setLang($lang) {
        if (in_array($lang, ['fr', 'en'])) {
            self::$lang = $lang;
            $_SESSION['lang'] = $lang;
            self::load();
        }
    }
}

/**
 * FR: Fonction raccourci pour acceder aux traductions
 * EN: Shortcut function to access translations
 *
 * @param string $key Cle de traduction / Translation key
 * @return string Texte traduit / Translated text
 */
function t($key) {
    return Language::get($key);
}
