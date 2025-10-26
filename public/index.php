<?php

declare(strict_types=1);

use App\Controllers\StaffRepairsController;
use App\Core\Router;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$router = new Router();

$router->get('/', [StaffRepairsController::class, 'index']);
$router->get('/repairs/new', [StaffRepairsController::class, 'create']);
$router->post('/repairs', [StaffRepairsController::class, 'store']);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
