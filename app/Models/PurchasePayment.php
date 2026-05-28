<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PurchasePayment extends Model
{
    protected string $table = 'purchase_payments';

    public function byPurchase(int $purchaseId): array
    {
        $statement = $this->db->prepare(
            'SELECT *
             FROM purchase_payments
             WHERE purchase_id = :purchase_id
             ORDER BY payment_date DESC, id DESC'
        );
        $statement->execute(['purchase_id' => $purchaseId]);

        return $statement->fetchAll();
    }
}
