<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

class AuthMiddleware
{
    public function handle(array $params = []): void
    {
        if (!Auth::check()) { header('Location: ' . app_url('/login')); exit; }
    }
}
