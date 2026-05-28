<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Supplier extends Model
{
    protected string $table = 'suppliers';

    public function active(string $orderBy = 'name ASC'): array
    {
        $statement = $this->db->prepare(
            "SELECT *
             FROM {$this->table}
             WHERE COALESCE(is_active, 1) = 1
             ORDER BY {$orderBy}"
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    public function allWithStats(): array
    {
        return $this->db->query(
            "SELECT
                s.*,
                COUNT(p.id) AS purchases_count,
                MAX(p.purchase_date) AS last_purchase_date
             FROM suppliers s
             LEFT JOIN purchases p ON p.supplier_id = s.id
             GROUP BY s.id
             ORDER BY
                COALESCE(s.is_active, 1) DESC,
                s.name ASC"
        )->fetchAll();
    }

    public function toggleStatus(int $id): void
    {
        $statement = $this->db->prepare(
            "UPDATE {$this->table}
             SET is_active = CASE WHEN COALESCE(is_active, 1) = 1 THEN 0 ELSE 1 END
             WHERE id = ?"
        );
        $statement->execute([$id]);
    }
}
