<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class DeliveryNotePayment extends Model
{
    protected string $table = 'delivery_note_payments';

    public function byNote(int $noteId): array
    {
        $statement = $this->db->prepare(
            'SELECT *
             FROM delivery_note_payments
             WHERE delivery_note_id = :delivery_note_id
             ORDER BY payment_date DESC, id DESC'
        );
        $statement->execute(['delivery_note_id' => $noteId]);

        return $statement->fetchAll();
    }
}
