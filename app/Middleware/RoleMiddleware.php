<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

class RoleMiddleware
{
    public function handle(array $params = []): void
    {
        if (!Auth::hasRole($params)) { http_response_code(403); exit('Acceso denegado'); }
    }
}
