<?php

declare(strict_types=1);

if (!function_exists('view')) {
    /**
     * Render a PHP view.
     *
     * @param array<string, mixed> $data
     */
    function view(string $name, array $data = []): void
    {
        $path = BASE_PATH . '/app/Views/' . str_replace('.', '/', $name) . '.php';

        if (!file_exists($path)) {
            throw new \RuntimeException("View {$name} not found at {$path}");
        }

        extract($data, EXTR_SKIP);

        require $path;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}
