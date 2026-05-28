<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('DB_HOST','db'), env('DB_PORT','3306'), env('DB_NAME','admin_inventario'));
            self::$pdo = new PDO($dsn, env('DB_USER','root'), env('DB_PASS',''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            Schema::ensure(self::$pdo);
        }
        return self::$pdo;
    }
}
