<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$publicPath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($publicPath) && !is_dir($publicPath)) {
    return false;
}

// Mirror the .htaccess rewrite (index.php?route=$1).
//
// Without this the built-in server sets SCRIPT_NAME to the requested path for a
// non-existent file, so RouteManager's base-path strip removes everything but
// the last segment and /api/v1/me resolves as 'me'.
$_GET['route'] = trim($uri, '/');

require __DIR__ . '/index.php';
