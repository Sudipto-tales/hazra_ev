<?php

// Load Composer autoload and dotenv if available
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;

    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->safeLoad();
    }
}

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/framework.php';
require_once __DIR__ . '/db.php';

// Load all core files dynamically
foreach (glob(__BASEDIR__ . '/core/*.php') as $filename) {
    require_once $filename;
}

// Function to load view files dynamically
function load_view($path, $data = []) {
    $file_path = __BASEDIR__ . '/' . ltrim($path, '/');
    if (file_exists($file_path)) {
        extract($data);
        require $file_path;
    } else {
        error_log("[Vayu] View '{$path}' not found!");
        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo "Error: View '{$path}' not found!";
        }
    }
}

function base_url($path = '') {
    global $base_url;
    $root = rtrim($base_url ?: 'http://localhost', '/');
    return $path === '' ? $root : $root . '/' . ltrim($path, '/');
}

function e($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
