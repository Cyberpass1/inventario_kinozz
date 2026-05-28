<?php declare(strict_types=1);
namespace App\Models;
use App\Core\Model;
class ExpenseCategory extends Model
{
    protected string $table = "expense_categories";

    public function expensesCount(int $id): int
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM expenses WHERE category_id = ?');
        $statement->execute([$id]);

        return (int) $statement->fetchColumn();
    }
}
