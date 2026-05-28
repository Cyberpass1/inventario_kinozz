<?php
declare(strict_types=1);

namespace App\Core;

class View
{
    public static function make(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = dirname(__DIR__) . '/Views/' . $view . '.php';
        $layoutFile = dirname(__DIR__) . '/Views/' . $layout . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException('Vista no encontrada: ' . $view);
        }
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        ob_start();
        require $layoutFile;
        echo prefix_root_relative_urls((string) ob_get_clean());
    }

    public static function renderError(int $code, string $message): void
    {
        $content = '<div class="card"><h1>Error ' . $code . '</h1><p>' . e($message) . '</p><a class="btn" href="' . e(app_url('/')) . '">Volver</a></div>';
        ob_start();
        require dirname(__DIR__) . '/Views/layouts/error.php';
        echo prefix_root_relative_urls((string) ob_get_clean());
    }
}
