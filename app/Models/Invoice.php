<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Invoice
{
    public function all(): array
    {
        return $this->history();
    }

    public function history(array $filters = [], ?int $limit = null): array
    {
        $db = Database::connection();
        $conditions = [];
        $params = [];

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $conditions[] = 'i.invoice_date >= ?';
            $params[] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $conditions[] = 'i.invoice_date <= ?';
            $params[] = $dateTo;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $conditions[] = "(
                i.invoice_number LIKE ?
                OR COALESCE(c.name, '') LIKE ?
                OR COALESCE(c.document, '') LIKE ?
                OR COALESCE(i.notes, '') LIKE ?
                OR EXISTS (
                    SELECT 1
                    FROM invoice_items si
                    INNER JOIN products sp ON sp.id = si.product_id
                    WHERE si.invoice_id = i.id
                      AND (
                          COALESCE(sp.name, '') LIKE ?
                          OR COALESCE(sp.sku, '') LIKE ?
                      )
                )
            )";
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $sql = "SELECT
                    i.*,
                    c.name AS client_name,
                    c.document AS client_document,
                    COALESCE(SUM(ii.quantity), 0) AS total_quantity,
                    COUNT(ii.id) AS line_count,
                    GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS products_summary
                FROM invoices i
                LEFT JOIN clients c ON c.id = i.client_id
                LEFT JOIN invoice_items ii ON ii.invoice_id = i.id
                LEFT JOIN products p ON p.id = ii.product_id";

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY
                    i.id,
                    i.client_id,
                    i.invoice_number,
                    i.invoice_date,
                    i.due_date,
                    i.currency_code,
                    i.exchange_rate,
                    i.subtotal_original,
                    i.tax_original,
                    i.total_original,
                    i.amount_paid_original,
                    i.balance_original,
                    i.subtotal_converted,
                    i.tax_converted,
                    i.total_converted,
                    i.amount_paid_converted,
                    i.balance_converted,
                    i.notes,
                    i.created_at,
                    i.status,
                    i.payment_status,
                    i.cancelled_at,
                    i.cancellation_reason,
                    c.name,
                    c.document
                ORDER BY i.id DESC";

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, (int) $limit);
        }

        $statement = $db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function nextNumber(): string
    {
        $row = Database::connection()->query('SELECT id FROM invoices ORDER BY id DESC LIMIT 1')->fetch();
        return 'FAC-' . str_pad((string) (((int) ($row['id'] ?? 0)) + 1), 6, '0', STR_PAD_LEFT);
    }

    public function create(array $header, array $items): int
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $statement = $db->prepare(
                'INSERT INTO invoices
                    (client_id, invoice_number, invoice_date, due_date, currency_code, exchange_rate, subtotal_original, tax_original, total_original, amount_paid_original, balance_original, subtotal_converted, tax_converted, total_converted, amount_paid_converted, balance_converted, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $header['client_id'],
                $header['invoice_number'],
                $header['invoice_date'],
                $header['due_date'] ?? $header['invoice_date'],
                $header['currency_code'],
                $header['exchange_rate'],
                $header['subtotal_original'],
                $header['tax_original'],
                $header['total_original'],
                0,
                $header['total_original'],
                $header['subtotal_converted'],
                $header['tax_converted'],
                $header['total_converted'],
                0,
                $header['total_converted'],
                $header['notes'] ?? null,
            ]);

            $invoiceId = (int) $db->lastInsertId();
            $detail = $db->prepare(
                'INSERT INTO invoice_items
                    (invoice_id, product_id, warehouse_id, quantity, price_original, price_converted, total_original, total_converted)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?)'
            );

            foreach ($items as $item) {
                $detail->execute([
                    $invoiceId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price_original'],
                    $item['price_converted'],
                    $item['total_original'],
                    $item['total_converted'],
                ]);

                $product = (new Product())->findVisible((int) $item['product_id']);
                if ($product && product_tracks_inventory($product)) {
                    Inventory::decrease(
                        (int) $item['product_id'],
                        (float) $item['quantity'],
                        'sale',
                        'FACTURA #' . $invoiceId,
                        'Salida por factura'
                    );
                }
            }

            $db->commit();

            return $invoiceId;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function cancel(int $id, string $reason = ''): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $invoice = $this->findFull($id);

            if (! $invoice) {
                throw new \RuntimeException('Factura no encontrada.');
            }

            if (($invoice['status'] ?? 'active') === 'cancelled') {
                throw new \RuntimeException('La factura ya estaba anulada.');
            }

            foreach ($invoice['items'] as $item) {
                if (product_tracks_inventory((string) ($item['product_type'] ?? 'merchandise'))) {
                    Inventory::increase(
                        (int) $item['product_id'],
                        (float) $item['quantity'],
                        'sale_cancel',
                        'ANULACION FACTURA #' . $id,
                        $reason !== '' ? $reason : 'Reversion por anulacion de factura'
                    );
                }
            }

            $statement = $db->prepare(
                'UPDATE invoices
                 SET status = ?, payment_status = ?, cancelled_at = NOW(), cancellation_reason = ?
                 WHERE id = ?'
            );
            $statement->execute(['cancelled', 'cancelled', $reason !== '' ? $reason : null, $id]);

            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function findFull(int $id): ?array
    {
        $db = Database::connection();
        $statement = $db->prepare(
            'SELECT i.*, c.name AS client_name, c.document AS client_document, c.phone AS client_phone
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE i.id = ?'
        );
        $statement->execute([$id]);
        $invoice = $statement->fetch();

        if (! $invoice) {
            return null;
        }

        $items = $db->prepare(
            'SELECT ii.*, p.name AS product_name, p.product_type
             FROM invoice_items ii
             LEFT JOIN products p ON p.id = ii.product_id
             WHERE ii.invoice_id = ?'
        );
        $items->execute([$id]);
        $invoice['items'] = $items->fetchAll();
        $invoice['payments'] = (new InvoicePayment())->byInvoice($id);
        $invoice = $this->applyEffectivePaymentState($invoice);
        $invoice['payment_status_effective'] = $this->resolvePaymentStatus($invoice);

        return $invoice;
    }

    public function registerPayment(int $id, array $payment): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $invoice = $this->findFull($id);

            if (! $invoice) {
                throw new \RuntimeException('Factura no encontrada.');
            }

            if (($invoice['status'] ?? 'active') === 'cancelled') {
                throw new \RuntimeException('No puedes registrar pagos en una factura anulada.');
            }

            $balanceOriginal = round_money((float) ($invoice['balance_original'] ?? 0));
            if ($balanceOriginal <= 0.01) {
                throw new \RuntimeException('La factura ya no tiene saldo pendiente.');
            }

            $appliedOriginal = round_money((float) ($payment['applied_original'] ?? 0));
            if ($appliedOriginal <= 0) {
                throw new \RuntimeException('El pago debe ser mayor a cero.');
            }

            if (payment_exceeds_balance($appliedOriginal, $balanceOriginal)) {
                throw new \RuntimeException('El pago excede el saldo pendiente de la factura.');
            }

            $statement = $db->prepare(
                'INSERT INTO invoice_payments
                    (invoice_id, payment_date, reference, payment_method, currency_code, exchange_rate, amount_original, amount_converted, applied_original, applied_converted, treasury_account_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $accountId = (int) ($payment['treasury_account_id'] ?? 0);
            if ($accountId <= 0) {
                $accountId = (new CashAccount())->resolveId(
                    (string) ($payment['payment_method'] ?? 'cash'),
                    (string) ($payment['currency_code'] ?? secondary_currency()),
                    $db
                );
            }
            $statement->execute([
                $id,
                $payment['payment_date'],
                $payment['reference'],
                $payment['payment_method'] ?? 'cash',
                $payment['currency_code'],
                $payment['exchange_rate'],
                $payment['amount_original'],
                $payment['amount_converted'],
                $payment['applied_original'],
                $payment['applied_converted'],
                $accountId,
                $payment['notes'] ?? null,
            ]);
            $paymentId = (int) $db->lastInsertId();

            (new CashMovement())->record([
                'cash_account_id' => $accountId,
                'movement_date' => $payment['payment_date'],
                'direction' => 'in',
                'currency_code' => $payment['currency_code'],
                'exchange_rate' => $payment['exchange_rate'],
                'amount_original' => $payment['amount_original'],
                'amount_converted' => $payment['amount_converted'],
                'source_type' => 'invoice_payment',
                'source_id' => $paymentId,
                'reference' => $payment['reference'],
                'notes' => $payment['notes'] ?? null,
            ], $db);

            $this->syncPaymentState($id, $db);
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function effectivePaymentStatus(array $invoice): string
    {
        return $this->resolvePaymentStatus($invoice);
    }

    private function syncPaymentState(int $invoiceId, \PDO $db): void
    {
        $paymentTotals = $db->prepare(
            'SELECT
                COALESCE(SUM(applied_original), 0) AS paid_original,
                COALESCE(SUM(applied_converted), 0) AS paid_converted
             FROM invoice_payments
             WHERE invoice_id = ?'
        );
        $paymentTotals->execute([$invoiceId]);
        $totals = $paymentTotals->fetch() ?: [];

        $invoice = $db->prepare('SELECT * FROM invoices WHERE id = ? LIMIT 1');
        $invoice->execute([$invoiceId]);
        $row = $invoice->fetch();
        if (! $row) {
            throw new \RuntimeException('Factura no encontrada.');
        }

        $paidOriginal = round_money(min((float) ($row['total_original'] ?? 0), (float) ($totals['paid_original'] ?? 0)));
        $paidConverted = round_money(min((float) ($row['total_converted'] ?? 0), (float) ($totals['paid_converted'] ?? 0)));
        $balanceOriginal = round_money(max(0.0, (float) ($row['total_original'] ?? 0) - $paidOriginal));
        $balanceConverted = round_money(max(0.0, (float) ($row['total_converted'] ?? 0) - $paidConverted));
        $paymentStatus = $this->resolvePaymentStatus([
            ...$row,
            'amount_paid_original' => $paidOriginal,
            'amount_paid_converted' => $paidConverted,
            'balance_original' => $balanceOriginal,
            'balance_converted' => $balanceConverted,
        ], false);

        $update = $db->prepare(
            'UPDATE invoices
             SET amount_paid_original = ?,
                 amount_paid_converted = ?,
                 balance_original = ?,
                 balance_converted = ?,
                 payment_status = ?
             WHERE id = ?'
        );
        $update->execute([
            $paidOriginal,
            $paidConverted,
            $balanceOriginal,
            $balanceConverted,
            $paymentStatus,
            $invoiceId,
        ]);
    }

    private function resolvePaymentStatus(array $invoice, bool $allowOverdue = true): string
    {
        if (($invoice['status'] ?? 'active') === 'cancelled') {
            return 'cancelled';
        }

        $balance = (float) ($invoice['balance_converted'] ?? 0);
        $paid = (float) ($invoice['amount_paid_converted'] ?? 0);

        if ($balance <= 0.01) {
            return 'paid';
        }

        $dueDate = (string) ($invoice['due_date'] ?? $invoice['invoice_date'] ?? '');
        if ($allowOverdue && $dueDate !== '' && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
            return $paid > 0 ? 'partial_overdue' : 'overdue';
        }

        return $paid > 0 ? 'partial' : 'pending';
    }

    private function applyEffectivePaymentState(array $invoice): array
    {
        $payments = is_array($invoice['payments'] ?? null) ? $invoice['payments'] : [];
        $paidOriginal = round_money(min(
            (float) ($invoice['total_original'] ?? 0),
            array_reduce(
                $payments,
                static fn (float $carry, array $payment): float => $carry + (float) ($payment['applied_original'] ?? 0),
                0.0
            )
        ));
        $paidConverted = round_money(min(
            (float) ($invoice['total_converted'] ?? 0),
            array_reduce(
                $payments,
                static fn (float $carry, array $payment): float => $carry + (float) ($payment['applied_converted'] ?? 0),
                0.0
            )
        ));

        $invoice['amount_paid_original'] = $paidOriginal;
        $invoice['amount_paid_converted'] = $paidConverted;
        $invoice['balance_original'] = round_money(max(0.0, (float) ($invoice['total_original'] ?? 0) - $paidOriginal));
        $invoice['balance_converted'] = round_money(max(0.0, (float) ($invoice['total_converted'] ?? 0) - $paidConverted));
        $invoice['payment_status'] = $this->resolvePaymentStatus($invoice, false);

        return $invoice;
    }
}
