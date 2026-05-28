<?php declare(strict_types=1);
namespace App\Models;
use App\Core\Model;
class Category extends Model
{
    protected string $table = "categories";

    public function usageCounts(): array
    {
        $statement = $this->db->query(
            'SELECT category_id, COUNT(*) AS total
             FROM products
             WHERE deleted_at IS NULL
               AND category_id IS NOT NULL
             GROUP BY category_id'
        );

        $counts = [];
        foreach ($statement->fetchAll() as $row) {
            $counts[(int) ($row['category_id'] ?? 0)] = (int) ($row['total'] ?? 0);
        }

        return $counts;
    }

    public function productsCount(int $id): int
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM products WHERE category_id = ? AND deleted_at IS NULL'
        );
        $statement->execute([$id]);

        return (int) $statement->fetchColumn();
    }
}
