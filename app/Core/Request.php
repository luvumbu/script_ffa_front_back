<?php
/**
 * app/Core/Request.php — Wrapper requete HTTP
 */

class Request
{
    public function get($key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public function post($key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    public function cookie($key, $default = null)
    {
        return $_COOKIE[$key] ?? $default;
    }

    public function server($key, $default = null)
    {
        return $_SERVER[$key] ?? $default;
    }

    public function getPage()
    {
        return $_GET['page'] ?? 'accueil';
    }

    public function getId()
    {
        $id = $_GET['id'] ?? '';
        return $id !== '' ? (int)$id : null;
    }

    public function getInt($key, $default = 0)
    {
        return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
    }

    public function getString($key, $default = '')
    {
        return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
    }

    public function getMethod()
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function getUri()
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public function getIp()
    {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
        return trim(explode(',', $ip)[0]);
    }

    public function isLocal()
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        return strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
    }

    public function all()
    {
        return $_GET;
    }
}
