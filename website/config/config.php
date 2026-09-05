<?php
// Application configuration loaded from environment variables when available
$baseDir = dirname(__DIR__); // Get the base directory of the project
define('__BASEDIR__', $baseDir);

define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Kolkata'));

$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

if ($httpHost !== '') {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dirName = str_replace('\\', '/', dirname($scriptName));
    $basePath = ($dirName === '/' || $dirName === '.') ? '' : $dirName;
    $base_url = env('APP_URL', ($isHttps ? 'https://' : 'http://') . $httpHost . $basePath);
} else {
    $base_url = env('APP_URL', 'http://localhost');
}

$GLOBALS['base_url'] = $base_url;

$frontendRoutes = require __DIR__ . '/../app/view.php';
$apiRoutes = require __DIR__ . '/../api/gateway.php';
$routes = $frontendRoutes + $apiRoutes;

?>
