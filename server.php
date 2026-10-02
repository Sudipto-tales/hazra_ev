<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// The development server does not read Apache's APK access restriction.
// Normalize separators and dot segments before checking the storage path.
$segments = [];
foreach (explode('/', str_replace('\\', '/', $uri)) as $segment) {
    if ($segment === '' || $segment === '.') continue;
    if ($segment === '..') { array_pop($segments); continue; }
    $segments[] = $segment;
}
$normalizedPath = implode('/', $segments);
if (preg_match('~^storage/apk(?:/|$)~i', $normalizedPath)) {
    http_response_code(403);
    exit;
}

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
