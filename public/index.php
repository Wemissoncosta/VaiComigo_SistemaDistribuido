<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load autoloader - try different paths
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    '/var/www/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php'
];

$autoloaderLoaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaderLoaded = true;
        break;
    }
}

if (!$autoloaderLoaded) {
    die('Autoloader not found. Please run composer install.');
}

use App\Core\Router;

// Load routes
$routes = require __DIR__ . '/../src/app/Core/routes.php';

// Create router instance
$router = new Router($routes);

// Dispatch request
$router->dispatch($_SERVER['REQUEST_URI']);
