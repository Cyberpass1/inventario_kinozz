<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $user = (new User())->findByUsername($username);
        if (
            !$user
            || !((int) ($user['is_active'] ?? 1) === 1)
            || !password_verify($password, $user['password'])
        ) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'role' => $user['role'],
        ];
        return true;
    }
    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function check(): bool { return isset($_SESSION['user']); }
    public static function logout(): void { unset($_SESSION['user']); session_destroy(); }
    public static function hasRole(array $roles): bool { return self::check() && in_array(self::user()['role'], $roles, true); }
}
