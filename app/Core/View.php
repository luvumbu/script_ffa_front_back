<?php
/**
 * app/Core/View.php — Moteur de templates PHP
 *
 * Utilise include + ob_start pour le rendu.
 * Les variables $data sont extraites et disponibles dans le template.
 */

class View
{
    private static $viewsPath = null;

    public static function getViewsPath()
    {
        if (self::$viewsPath === null) {
            self::$viewsPath = dirname(__DIR__) . '/Views';
        }
        return self::$viewsPath;
    }

    /**
     * Rend un template et retourne le HTML
     */
    public static function render($template, $data = [])
    {
        $file = self::getViewsPath() . '/' . $template . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("Vue introuvable : $template ($file)");
        }

        extract($data);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Rend un template dans un layout
     */
    public static function renderWithLayout($template, $data = [], $layout = 'main')
    {
        $data['__content'] = self::render('pages/' . $template, $data);
        echo self::render('layouts/' . $layout, $data);
    }

    /**
     * Inclut un partial (depuis un template)
     */
    public static function partial($name, $data = [])
    {
        $file = self::getViewsPath() . '/partials/' . $name . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("Partial introuvable : $name");
        }

        extract($data);
        include $file;
    }
}
