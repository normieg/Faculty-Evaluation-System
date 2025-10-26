<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

class Router
{
    /**
     * @var array<string, array<string, Closure|array{0: string|object, 1: string}>>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
    ];

    /**
     * Register a GET route.
     */
    public function get(string $path, Closure|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, Closure|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Dispatch a route based on the current request method and path.
     */
    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        if (is_array($handler)) {
            [$class, $action] = $handler;

            if (is_string($class)) {
                if (!class_exists($class)) {
                    throw new RuntimeException("Controller {$class} not found");
                }

                $class = new $class();
            }

            if (!method_exists($class, $action)) {
                $name = is_object($class) ? get_debug_type($class) : (string) $class;
                throw new RuntimeException("Action {$action} not found on controller {$name}");
            }

            $class->{$action}();
            return;
        }

        $handler();
    }

    /**
     * @param Closure|array{0: string|object, 1: string} $handler
     */
    private function addRoute(string $method, string $path, Closure|array $handler): void
    {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');
        $this->routes[$method][$path] = $handler;
    }
}
