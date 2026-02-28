<?php
/**
 * LanguageSwitcher.php — Selecteur de langue / Language switcher
 * FR: Composant UI pour basculer entre francais et anglais
 * EN: UI component to switch between French and English
 */

class LanguageSwitcher {

    /**
     * FR: Affiche le selecteur de langue FR | EN
     * EN: Display the language switcher FR | EN
     *
     * @return string HTML du selecteur / Switcher HTML
     */
    public static function render() {
        $currentLang = Language::getLang();
        $frActive = ($currentLang === 'fr') ? 'lang-active' : '';
        $enActive = ($currentLang === 'en') ? 'lang-active' : '';

        return '
        <div class="lang-switcher">
            <span class="lang-option ' . $frActive . '" onclick="setLang(\'fr\')" title="Français">FR</span>
            <span class="lang-separator">|</span>
            <span class="lang-option ' . $enActive . '" onclick="setLang(\'en\')" title="English">EN</span>
        </div>
        <style>
            .lang-switcher {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-family: "Segoe UI", Arial, sans-serif;
                font-size: 14px;
                font-weight: 600;
                user-select: none;
            }
            .lang-option {
                cursor: pointer;
                padding: 4px 8px;
                border-radius: 6px;
                color: #94a3b8;
                transition: all 0.2s ease;
            }
            .lang-option:hover {
                color: #3b82f6;
                background: rgba(59,130,246,0.1);
            }
            .lang-option.lang-active {
                color: #3b82f6;
                background: rgba(59,130,246,0.15);
            }
            .lang-separator {
                color: #475569;
            }
        </style>
        <script>
            function setLang(lang) {
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "' . self::getBasePath() . 'req_on/set_lang.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onload = function() {
                    location.reload();
                };
                xhr.send("lang=" + lang);
            }
        </script>';
    }

    /**
     * FR: Determine le chemin de base vers la racine du site
     * EN: Determine the base path to site root
     */
    private static function getBasePath() {
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptDir === '/' || $scriptDir === '\\') {
            return '';
        }
        $depth = substr_count(trim($scriptDir, '/'), '/');
        return str_repeat('../', $depth);
    }
}
