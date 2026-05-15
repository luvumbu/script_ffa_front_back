<?php
/**
 * app/Core/Response.php — Helpers reponse HTTP
 */

class Response
{
    public static function html($content, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    public static function redirect($url, $status = 302)
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function notFound()
    {
        http_response_code(404);
        echo '<h1>404 — Page non trouvee</h1>';
        exit;
    }
}
