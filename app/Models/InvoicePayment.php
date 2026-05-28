<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class InvoicePayment extends Model
{
    protected string $table = 'invoice_payments';

    public function byInvoice(int $invoiceId): array
    {
        $statement = $this->db->prepare(
            'SELECT *
             FROM invoice_payments
             WHERE invoice_id = :invoice_id
             ORDER BY payment_date DESC, id DESC'
        );
        $statement->execute(['invoice_id' => $invoiceId]);

        return $statement->fetchAll();
    }
}
