<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

class Router
{
    private array $routes = [];

    public function get(string $path, array $action, array $middleware = []): void { $this->add('GET', $path, $action, $middleware); }
    public function post(string $path, array $action, array $middleware = []): void { $this->add('POST', $path, $action, $middleware); }

    private function add(string $method, string $path, array $action, array $middleware): void
    {
        $this->routes[$method][] = compact('path', 'action', 'middleware');
    }

    public function dispatch(string $method, string $uri): void
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                foreach ($route['middleware'] as $middleware) {
                    [$class, $params] = $this->parseMiddleware($middleware);
                    (new $class())->handle($params);
                }
                [$controllerClass, $method] = $route['action'];
                $controller = new $controllerClass();
                call_user_func_array([$controller, $method], $matches);
                return;
            }
        }
        throw new HttpException('Página no encontrada', 404);
    }

    private function parseMiddleware(string $middleware): array
    {
        if (str_contains($middleware, ':')) {
            [$class, $params] = explode(':', $middleware, 2);
            return [$class, explode(',', $params)];
        }
        return [$middleware, []];
    }
}
