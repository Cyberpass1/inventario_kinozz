<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class CreditNote
{
    public function nextNumber(): string
    {
        $statement = Database::connection()->query(
            "SELECT credit_note_number FROM credit_notes ORDER BY id DESC LIMIT 1"
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $last = (string) ($row['credit_note_number'] ?? '');
        if (preg_match('/(\d+)$/', $last, $matches) === 1) {
            $next = (int) $matches[1] + 1;
        } else {
            $next = 1;
        }
        return 'NC-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Devuelve la factura con la cantidad ya acreditada por linea (para limitar las devoluciones).
     */
    public function availableForInvoice(int $invoiceId): ?array
    {
        $invoice = (new Invoice())->findFull($invoiceId);
        if (! $invoice) {
            return null;
        }
        if (($invoice['status'] ?? 'active') === 'cancelled') {
            return null;
        }

        $db = Database::connection();
        $statement = $db->prepare(
            "SELECT cni.invoice_item_id, COALESCE(SUM(cni.quantity), 0) AS returned
             FROM credit_note_items cni
             INNER JOIN credit_notes cn ON cn.id = cni.credit_note_id
             WHERE cn.invoice_id = ?
               AND COALESCE(cn.status, 'active') <> 'cancelled'
             GROUP BY cni.invoice_item_id"
        );
        $statement->execute([$invoiceId]);
        $returnedByItem = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $returnedByItem[(int) $row['invoice_item_id']] = (float) $row['returned'];
        }

        foreach ($invoice['items'] as &$item) {
            $itemId = (int) $item['id'];
            $returned = (float) ($returnedByItem[$itemId] ?? 0);
            $original = (float) ($item['quantity'] ?? 0);
            $item['returned_quantity'] = $returned;
            $item['remaining_quantity'] = max(0.0, $original - $returned);
        }
        unset($item);

        $totalsByStatus = $this->totalsByInvoice($invoiceId);
        $invoice['credit_notes_total_original'] = $totalsByStatus['total_original'];
        $invoice['credit_notes_total_converted'] = $totalsByStatus['total_converted'];
        $invoice['effective_total_original'] = max(0.0, (float) ($invoice['total_original'] ?? 0) - $totalsByStatus['total_original']);
        $invoice['effective_total_converted'] = max(0.0, (float) ($invoice['total_converted'] ?? 0) - $totalsByStatus['total_converted']);

        return $invoice;
    }

    public function create(array $header, array $items): int
    {
        $db = Database::connection();
        $invoiceModel = new Invoice();
        $invoice = $invoiceModel->findFull((int) $header['invoice_id']);
        if (! $invoice) {
            throw new \RuntimeException('Factura no encontrada.');
        }
        if (($invoice['status'] ?? 'active') === 'cancelled') {
            throw new \RuntimeException('No se puede emitir una NC sobre una factura anulada.');
        }
        if ($items === []) {
            throw new \RuntimeException('Debes seleccionar al menos un producto para la nota de credito.');
        }

        $invoiceItemsById = [];
        foreach ($invoice['items'] as $row) {
            $invoiceItemsById[(int) $row['id']] = $row;
        }

        $available = $this->availableForInvoice((int) $header['invoice_id']);
        $remainingByItem = [];
        foreach ($available['items'] as $row) {
            $remainingByItem[(int) $row['id']] = (float) ($row['remaining_quantity'] ?? 0);
        }

        $cleanItems = [];
        $subtotalOriginal = 0.0;
        $subtotalConverted = 0.0;
        foreach ($items as $row) {
            $invoiceItemId = (int) ($row['invoice_item_id'] ?? 0);
            $quantity = (float) ($row['quantity'] ?? 0);
            if ($invoiceItemId <= 0 || $quantity <= 0) {
                continue;
            }
            if (!isset($invoiceItemsById[$invoiceItemId])) {
                throw new \RuntimeException('Renglon de la factura no valido.');
            }
            $remaining = (float) ($remainingByItem[$invoiceItemId] ?? 0);
            if ($quantity > $remaining + 0.0001) {
                throw new \RuntimeException(
                    'No puedes devolver mas unidades de las facturadas. Maximo permitido en uno de los renglones: '
                    . number_format($remaining, 2, ',', '.')
                );
            }

            $invoiceItem = $invoiceItemsById[$invoiceItemId];
            $priceOriginal = (float) ($invoiceItem['price_original'] ?? 0);
            $priceConverted = (float) ($invoiceItem['price_converted'] ?? 0);
            $lineOriginal = round($quantity * $priceOriginal, 2);
            $lineConverted = round($quantity * $priceConverted, 2);

            $cleanItems[] = [
                'invoice_item_id' => $invoiceItemId,
                'product_id' => (int) $invoiceItem['product_id'],
                'quantity' => $quantity,
                'price_original' => $priceOriginal,
                'price_converted' => $priceConverted,
                'total_original' => $lineOriginal,
                'total_converted' => $lineConverted,
                'product_type' => (string) ($invoiceItem['product_type'] ?? 'merchandise'),
            ];

            $subtotalOriginal += $lineOriginal;
            $subtotalConverted += $lineConverted;
        }

        if ($cleanItems === []) {
            throw new \RuntimeException('No hay cantidades validas para emitir la nota de credito.');
        }

        $taxRate = (float) ($invoice['subtotal_original'] ?? 0) > 0
            ? ((float) ($invoice['tax_original'] ?? 0)) / ((float) $invoice['subtotal_original'])
            : 0.0;
        $taxOriginal = round($subtotalOriginal * $taxRate, 2);
        $taxConverted = round($subtotalConverted * $taxRate, 2);
        $totalOriginal = round($subtotalOriginal + $taxOriginal, 2);
        $totalConverted = round($subtotalConverted + $taxConverted, 2);

        $invoiceBalance = (float) ($invoice['balance_original'] ?? 0);
        $effectiveTotal = (float) ($available['effective_total_original'] ?? $invoice['total_original'] ?? 0);
        if ($totalOriginal > $effectiveTotal + 0.0001) {
            throw new \RuntimeException(
                'La nota de credito excede el total efectivo de la factura. '
                . 'Disponible para credito: ' . number_format($effectiveTotal, 2, ',', '.')
            );
        }

        $creditNoteNumber = trim((string) ($header['credit_note_number'] ?? ''));
        if ($creditNoteNumber === '') {
            $creditNoteNumber = $this->nextNumber();
        }
        $creditDate = (string) ($header['credit_note_date'] ?? date('Y-m-d'));
        $currency = (string) ($invoice['currency_code'] ?? base_currency());
        $exchangeRate = (float) ($invoice['exchange_rate'] ?? system_exchange_rate($creditDate));

        $db->beginTransaction();
        try {
            $statement = $db->prepare(
                'INSERT INTO credit_notes
                    (invoice_id, credit_note_number, credit_note_date, currency_code, exchange_rate,
                     subtotal_original, tax_original, total_original,
                     subtotal_converted, tax_converted, total_converted,
                     reason, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                (int) $header['invoice_id'],
                $creditNoteNumber,
                $creditDate,
                $currency,
                $exchangeRate,
                $subtotalOriginal,
                $taxOriginal,
                $totalOriginal,
                $subtotalConverted,
                $taxConverted,
                $totalConverted,
                trim((string) ($header['reason'] ?? '')) ?: null,
                trim((string) ($header['notes'] ?? '')) ?: null,
            ]);
            $creditNoteId = (int) $db->lastInsertId();

            $itemStatement = $db->prepare(
                'INSERT INTO credit_note_items
                    (credit_note_id, invoice_item_id, product_id, quantity, price_original, price_converted, total_original, total_converted)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($cleanItems as $item) {
                $itemStatement->execute([
                    $creditNoteId,
                    $item['invoice_item_id'],
                    $item['product_id'],
                    $item['quantity'],
                    $item['price_original'],
                    $item['price_converted'],
                    $item['total_original'],
                    $item['total_converted'],
                ]);

                if (product_tracks_inventory($item['product_type'])) {
                    Inventory::increase(
                        $item['product_id'],
                        $item['quantity'],
                        'sale_return',
                        $creditNoteNumber,
                        'Reingreso por nota de credito'
                    );
                }
            }

            $this->applyToInvoiceBalance((int) $header['invoice_id'], -$totalOriginal, -$totalConverted, $invoiceBalance, (float) ($invoice['balance_converted'] ?? 0));

            $db->commit();
            return $creditNoteId;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function cancel(int $id, string $reason = ''): void
    {
        $db = Database::connection();
        $note = $this->findFull($id);
        if (! $note) {
            throw new \RuntimeException('Nota de credito no encontrada.');
        }
        if (($note['status'] ?? 'active') === 'cancelled') {
            throw new \RuntimeException('Esta nota de credito ya estaba anulada.');
        }

        $db->beginTransaction();
        try {
            $invoice = (new Invoice())->findFull((int) $note['invoice_id']);
            $invoiceBalance = (float) ($invoice['balance_original'] ?? 0);
            $invoiceBalanceConverted = (float) ($invoice['balance_converted'] ?? 0);

            foreach ($note['items'] as $item) {
                $productType = (string) ($item['product_type'] ?? 'merchandise');
                if (product_tracks_inventory($productType)) {
                    Inventory::decrease(
                        (int) $item['product_id'],
                        (float) $item['quantity'],
                        'sale_return_cancel',
                        $note['credit_note_number'],
                        'Reversion por anulacion de nota de credito'
                    );
                }
            }

            $statement = $db->prepare(
                'UPDATE credit_notes
                 SET status = ?, cancelled_at = NOW(), cancellation_reason = ?
                 WHERE id = ?'
            );
            $statement->execute(['cancelled', $reason !== '' ? $reason : null, $id]);

            $this->applyToInvoiceBalance(
                (int) $note['invoice_id'],
                (float) ($note['total_original'] ?? 0),
                (float) ($note['total_converted'] ?? 0),
                $invoiceBalance,
                $invoiceBalanceConverted
            );

            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function findFull(int $id): ?array
    {
        $db = Database::connection();
        $statement = $db->prepare(
            'SELECT cn.*, i.invoice_number, c.name AS client_name, c.document AS client_document
             FROM credit_notes cn
             INNER JOIN invoices i ON i.id = cn.invoice_id
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE cn.id = ?'
        );
        $statement->execute([$id]);
        $note = $statement->fetch(PDO::FETCH_ASSOC);
        if (! $note) {
            return null;
        }

        $items = $db->prepare(
            'SELECT cni.*, p.name AS product_name, p.sku AS product_sku, p.product_type
             FROM credit_note_items cni
             LEFT JOIN products p ON p.id = cni.product_id
             WHERE cni.credit_note_id = ?'
        );
        $items->execute([$id]);
        $note['items'] = $items->fetchAll(PDO::FETCH_ASSOC);

        return $note;
    }

    public function byInvoice(int $invoiceId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, credit_note_number, credit_note_date, total_original, total_converted, currency_code, status, reason
             FROM credit_notes
             WHERE invoice_id = ?
             ORDER BY id DESC'
        );
        $statement->execute([$invoiceId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function history(array $filters = [], ?int $limit = null): array
    {
        $sql = "SELECT cn.id, cn.credit_note_number, cn.credit_note_date, cn.currency_code, cn.exchange_rate,
                       cn.subtotal_original, cn.tax_original, cn.total_original, cn.total_converted,
                       cn.status, cn.cancelled_at, cn.reason, cn.notes,
                       i.invoice_number, i.id AS invoice_id,
                       c.name AS client_name, c.document AS client_document
                FROM credit_notes cn
                INNER JOIN invoices i ON i.id = cn.invoice_id
                LEFT JOIN clients c ON c.id = i.client_id";

        $conditions = [];
        $params = [];
        if (!empty($filters['date_from'])) {
            $conditions[] = 'cn.credit_note_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'cn.credit_note_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['q'])) {
            $conditions[] = '(cn.credit_note_number LIKE ? OR i.invoice_number LIKE ? OR c.name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY cn.id DESC';
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function totalsByInvoice(int $invoiceId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                COALESCE(SUM(total_original), 0) AS total_original,
                COALESCE(SUM(total_converted), 0) AS total_converted
             FROM credit_notes
             WHERE invoice_id = ?
               AND COALESCE(status, 'active') <> 'cancelled'"
        );
        $statement->execute([$invoiceId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total_original' => (float) ($row['total_original'] ?? 0),
            'total_converted' => (float) ($row['total_converted'] ?? 0),
        ];
    }

    private function applyToInvoiceBalance(int $invoiceId, float $deltaOriginal, float $deltaConverted, float $currentBalanceOriginal, float $currentBalanceConverted): void
    {
        $newBalanceOriginal = max(0.0, $currentBalanceOriginal + $deltaOriginal);
        $newBalanceConverted = max(0.0, $currentBalanceConverted + $deltaConverted);
        $statement = Database::connection()->prepare(
            'UPDATE invoices
             SET balance_original = ?, balance_converted = ?
             WHERE id = ?'
        );
        $statement->execute([$newBalanceOriginal, $newBalanceConverted, $invoiceId]);
    }
}
