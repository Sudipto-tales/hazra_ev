<?php
require_once __DIR__ . '/bootstrap.php';
require_once __BASEDIR__ . '/app/controllers/Welcome.php';
require_once __BASEDIR__ . '/app/controllers/Page.php';

foreach (glob(__BASEDIR__ . '/api/controllers/*.php') as $file) {
    require_once $file;
}

require_once __DIR__ . '/../core/RouteManager.php';

RouteManager::dispatch($routes);
