<?php declare(strict_types=1);
namespace App\Models;
use App\Core\Model;
class User extends Model
{
    protected string $table = "users";

    public function allManaged(): array
    {
        return $this->db->query(
            "SELECT id, username, name, email, role, is_active, created_at, updated_at
             FROM users
             ORDER BY FIELD(role, 'administrator', 'vendor', 'general_consultant'), name ASC, id ASC"
        )->fetchAll();
    }

    public function findByUsername(string $username): ?array
    {
        $s = $this->db->prepare(
            "SELECT * FROM users WHERE username=:u LIMIT 1",
        );
        $s->execute(["u" => $username]);
        return $s->fetch() ?: null;
    }

    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        $params = ['username' => $username];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }
}
