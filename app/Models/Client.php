<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Client extends Model
{
    protected string $table = 'clients';

    public function search(string $term, int $limit = 8): array
    {
        $limit = max(1, min($limit, 20));
        $normalized = trim($term);
        $like = '%' . $normalized . '%';
        $prefix = $normalized . '%';

        $statement = $this->db->prepare(
            "SELECT
                c.id,
                c.name,
                c.document,
                c.phone,
                c.email,
                COUNT(i.id) AS invoices_count,
                MAX(i.invoice_date) AS last_invoice_date
             FROM clients c
             LEFT JOIN invoices i ON i.client_id = c.id
             WHERE (:term = '' OR c.name LIKE :like OR c.document LIKE :like)
             GROUP BY c.id, c.name, c.document, c.phone, c.email
             ORDER BY
                CASE
                    WHEN :term <> '' AND c.document = :exact THEN 0
                    WHEN :term <> '' AND c.document LIKE :prefix THEN 1
                    WHEN :term <> '' AND c.name LIKE :prefix THEN 2
                    ELSE 3
                END,
                c.name ASC
             LIMIT {$limit}"
        );
        $statement->execute([
            'term' => $normalized,
            'like' => $like,
            'exact' => $normalized,
            'prefix' => $prefix,
        ]);

        return $statement->fetchAll();
    }

    public function allWithStats(): array
    {
        return $this->db->query(
            'SELECT c.*,
                    COUNT(i.id) AS invoices_count,
                    MAX(i.invoice_date) AS last_invoice_date
             FROM clients c
             LEFT JOIN invoices i ON i.client_id = c.id
             GROUP BY c.id
             ORDER BY c.name ASC'
        )->fetchAll();
    }
}
