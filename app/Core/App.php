<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

class App
{
    public function run(): void
    {
        try {
            $router = require dirname(__DIR__) . '/routes/web.php';
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri = app_request_path();
            $router->dispatch($method, $uri);
        } catch (HttpException $e) {
            http_response_code($e->getCode() ?: 500);
            View::renderError($e->getCode(), $e->getMessage());
        } catch (\Throwable $e) {
            http_response_code(500);
            if ((bool) env('APP_DEBUG', false)) {
                echo '<pre>' . e($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
            } else {
                View::renderError(500, 'Ha ocurrido un error interno.');
            }
        }
    }
}
