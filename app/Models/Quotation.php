<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Quotation
{
    public function history(array $filters = [], ?int $limit = null): array
    {
        $db = Database::connection();
        $conditions = [];
        $params = [];

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $conditions[] = 'q.quotation_date >= ?';
            $params[] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $conditions[] = 'q.quotation_date <= ?';
            $params[] = $dateTo;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $conditions[] = "(
                q.quotation_number LIKE ?
                OR COALESCE(c.name, '') LIKE ?
                OR COALESCE(c.document, '') LIKE ?
                OR COALESCE(q.notes, '') LIKE ?
                OR EXISTS (
                    SELECT 1
                    FROM quotation_items qi2
                    INNER JOIN products p2 ON p2.id = qi2.product_id
                    WHERE qi2.quotation_id = q.id
                      AND (COALESCE(p2.name, '') LIKE ? OR COALESCE(p2.sku, '') LIKE ?)
                )
            )";
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $sql = "SELECT
                    q.*,
                    c.name AS client_name,
                    c.document AS client_document,
                    COALESCE(SUM(qi.quantity), 0) AS total_quantity,
                    COUNT(qi.id) AS line_count,
                    GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS products_summary
                FROM quotations q
                LEFT JOIN clients c ON c.id = q.client_id
                LEFT JOIN quotation_items qi ON qi.quotation_id = q.id
                LEFT JOIN products p ON p.id = qi.product_id";

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY
                    q.id,
                    q.client_id,
                    q.quotation_number,
                    q.quotation_date,
                    q.valid_until,
                    q.currency_code,
                    q.exchange_rate,
                    q.subtotal_original,
                    q.total_original,
                    q.subtotal_converted,
                    q.total_converted,
                    q.notes,
                    q.status,
                    q.invoice_id,
                    q.delivery_note_id,
                    q.cancelled_at,
                    q.cancellation_reason,
                    q.created_at,
                    c.name,
                    c.document
                ORDER BY q.id DESC";

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, (int) $limit);
        }

        $statement = $db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function nextNumber(): string
    {
        $row = Database::connection()->query('SELECT id FROM quotations ORDER BY id DESC LIMIT 1')->fetch();
        return 'COT-' . str_pad((string) (((int) ($row['id'] ?? 0)) + 1), 6, '0', STR_PAD_LEFT);
    }

    public function create(array $header, array $items): int
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $statement = $db->prepare(
                'INSERT INTO quotations
                    (client_id, quotation_number, quotation_date, valid_until, currency_code, exchange_rate, subtotal_original, total_original, subtotal_converted, total_converted, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $header['client_id'],
                $header['quotation_number'],
                $header['quotation_date'],
                $header['valid_until'] ?? $header['quotation_date'],
                $header['currency_code'],
                $header['exchange_rate'],
                $header['subtotal_original'],
                $header['total_original'],
                $header['subtotal_converted'],
                $header['total_converted'],
                $header['notes'] ?? null,
            ]);

            $quotationId = (int) $db->lastInsertId();
            $detail = $db->prepare(
                'INSERT INTO quotation_items
                    (quotation_id, product_id, quantity, price_original, price_converted, total_original, total_converted)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($items as $item) {
                $detail->execute([
                    $quotationId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price_original'] ?? 0,
                    $item['price_converted'] ?? 0,
                    $item['total_original'] ?? 0,
                    $item['total_converted'] ?? 0,
                ]);
            }

            $db->commit();

            return $quotationId;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function findFull(int $id): ?array
    {
        $db = Database::connection();
        $statement = $db->prepare(
            'SELECT q.*, c.name AS client_name, c.document AS client_document, c.phone AS client_phone
             FROM quotations q
             LEFT JOIN clients c ON c.id = q.client_id
             WHERE q.id = ?'
        );
        $statement->execute([$id]);
        $quotation = $statement->fetch();

        if (! $quotation) {
            return null;
        }

        $items = $db->prepare(
            'SELECT qi.*, p.name AS product_name, p.product_type
             FROM quotation_items qi
             LEFT JOIN products p ON p.id = qi.product_id
             WHERE qi.quotation_id = ?'
        );
        $items->execute([$id]);
        $quotation['items'] = $items->fetchAll();

        return $quotation;
    }

    public function convertToDeliveryNote(int $id): int
    {
        $quotation = $this->findFull($id);
        if (! $quotation) {
            throw new \RuntimeException('Cotizacion no encontrada.');
        }

        if (($quotation['status'] ?? 'open') === 'cancelled') {
            throw new \RuntimeException('No puedes convertir una cotizacion anulada.');
        }

        if ((int) ($quotation['delivery_note_id'] ?? 0) > 0) {
            return (int) $quotation['delivery_note_id'];
        }

        $noteModel = new DeliveryNote();
        $noteId = $noteModel->create([
            'client_id' => (int) $quotation['client_id'],
            'note_number' => $noteModel->nextNumber(),
            'note_date' => date('Y-m-d'),
            'due_date' => document_due_date(date('Y-m-d'), invoice_due_days()),
            'currency_code' => (string) $quotation['currency_code'],
            'exchange_rate' => (float) $quotation['exchange_rate'],
            'subtotal_original' => (float) $quotation['subtotal_original'],
            'total_original' => (float) $quotation['total_original'],
            'subtotal_converted' => (float) $quotation['subtotal_converted'],
            'total_converted' => (float) $quotation['total_converted'],
            'notes' => trim('Generada desde cotizacion ' . (string) $quotation['quotation_number'] . "\n" . (string) ($quotation['notes'] ?? '')),
        ], $this->conversionItems($quotation), (int) ($quotation['invoice_id'] ?? 0) <= 0);

        $this->markConverted($id, 'delivery_note_id', $noteId);

        return $noteId;
    }

    public function convertToInvoice(int $id): int
    {
        $quotation = $this->findFull($id);
        if (! $quotation) {
            throw new \RuntimeException('Cotizacion no encontrada.');
        }

        if (($quotation['status'] ?? 'open') === 'cancelled') {
            throw new \RuntimeException('No puedes convertir una cotizacion anulada.');
        }

        if ((int) ($quotation['invoice_id'] ?? 0) > 0) {
            return (int) $quotation['invoice_id'];
        }

        $taxOriginal = round_money((float) $quotation['subtotal_original'] * (tax_percent() / 100));
        $taxConverted = round_money((float) $quotation['subtotal_converted'] * (tax_percent() / 100));
        $invoiceModel = new Invoice();
        $invoiceId = $invoiceModel->create([
            'client_id' => (int) $quotation['client_id'],
            'invoice_number' => $invoiceModel->nextNumber(),
            'invoice_date' => date('Y-m-d'),
            'due_date' => document_due_date(date('Y-m-d'), invoice_due_days()),
            'currency_code' => (string) $quotation['currency_code'],
            'exchange_rate' => (float) $quotation['exchange_rate'],
            'subtotal_original' => (float) $quotation['subtotal_original'],
            'tax_original' => $taxOriginal,
            'total_original' => round_money((float) $quotation['subtotal_original'] + $taxOriginal),
            'subtotal_converted' => (float) $quotation['subtotal_converted'],
            'tax_converted' => $taxConverted,
            'total_converted' => round_money((float) $quotation['subtotal_converted'] + $taxConverted),
            'notes' => trim('Generada desde cotizacion ' . (string) $quotation['quotation_number'] . "\n" . (string) ($quotation['notes'] ?? '')),
        ], $this->conversionItems($quotation), (int) ($quotation['delivery_note_id'] ?? 0) <= 0);

        $this->markConverted($id, 'invoice_id', $invoiceId);

        return $invoiceId;
    }

    public function cancel(int $id, string $reason = ''): void
    {
        $statement = Database::connection()->prepare(
            "UPDATE quotations
             SET status = 'cancelled', cancelled_at = NOW(), cancellation_reason = ?
             WHERE id = ? AND status <> 'cancelled'"
        );
        $statement->execute([$reason !== '' ? $reason : null, $id]);
    }

    public function attachDocument(int $id, string $type, int $documentId): void
    {
        $quotation = $this->findFull($id);
        if (! $quotation) {
            throw new \RuntimeException('Cotizacion no encontrada.');
        }

        if (($quotation['status'] ?? 'open') === 'cancelled') {
            throw new \RuntimeException('No puedes actualizar una cotizacion anulada.');
        }

        $column = match ($type) {
            'invoice' => 'invoice_id',
            'delivery_note' => 'delivery_note_id',
            default => throw new \InvalidArgumentException('Documento de destino invalido.'),
        };

        $currentDocumentId = (int) ($quotation[$column] ?? 0);
        if ($currentDocumentId > 0 && $currentDocumentId !== $documentId) {
            throw new \RuntimeException('Esta cotizacion ya tiene ese documento generado.');
        }

        $this->markConverted($id, $column, $documentId);
    }

    private function conversionItems(array $quotation): array
    {
        return array_map(static fn (array $item): array => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (float) $item['quantity'],
            'price_original' => (float) $item['price_original'],
            'price_converted' => (float) $item['price_converted'],
            'total_original' => (float) $item['total_original'],
            'total_converted' => (float) $item['total_converted'],
        ], is_array($quotation['items'] ?? null) ? $quotation['items'] : []);
    }

    private function markConverted(int $id, string $column, int $documentId): void
    {
        if (!in_array($column, ['invoice_id', 'delivery_note_id'], true)) {
            throw new \InvalidArgumentException('Documento de destino invalido.');
        }

        $db = Database::connection();
        $db->prepare("UPDATE quotations SET {$column} = ? WHERE id = ?")->execute([$documentId, $id]);
        $db->prepare(
            "UPDATE quotations
             SET status = CASE
                WHEN invoice_id IS NOT NULL AND delivery_note_id IS NOT NULL THEN 'converted_both'
                WHEN invoice_id IS NOT NULL THEN 'converted_invoice'
                WHEN delivery_note_id IS NOT NULL THEN 'converted_delivery'
                ELSE status
             END
             WHERE id = ?"
        )->execute([$id]);
    }
}
