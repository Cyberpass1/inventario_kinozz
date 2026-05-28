<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Purchase
{
    public function nextNumber(): string
    {
        $row = Database::connection()->query('SELECT id FROM purchases ORDER BY id DESC LIMIT 1')->fetch();
        return $this->formatNumber(((int) ($row['id'] ?? 0)) + 1);
    }

    public function all(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(p.doc_number LIKE ? OR s.name LIKE ? OR pr.name LIKE ? OR pr.sku LIKE ? OR p.notes LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term, $term);
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $conditions[] = 'p.purchase_date >= ?';
            $params[] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $conditions[] = 'p.purchase_date <= ?';
            $params[] = $dateTo;
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $statement = Database::connection()->prepare(
            "SELECT
                p.*,
                s.name AS supplier_name,
                COALESCE(SUM(pi.quantity), 0) AS total_quantity,
                COUNT(pi.id) AS line_count,
                GROUP_CONCAT(DISTINCT pr.name ORDER BY pr.name SEPARATOR ', ') AS products_summary
             FROM purchases p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN purchase_items pi ON pi.purchase_id = p.id
             LEFT JOIN products pr ON pr.id = pi.product_id
             {$whereSql}
             GROUP BY
                p.id,
                p.supplier_id,
                p.warehouse_id,
                p.doc_number,
                p.purchase_date,
                p.due_date,
                p.currency_code,
                p.exchange_rate,
                p.subtotal_original,
                p.total_original,
                p.amount_paid_original,
                p.balance_original,
                p.subtotal_converted,
                p.total_converted,
                p.amount_paid_converted,
                p.balance_converted,
                p.notes,
                p.created_at,
                p.status,
                p.payment_status,
                p.cancelled_at,
                p.cancellation_reason,
                s.name
             ORDER BY p.id DESC"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function create(array $header, array $items): int
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $documentNumber = trim((string) ($header['doc_number'] ?? ''));
            if ($documentNumber === '') {
                $documentNumber = '__PENDING__';
            }

            $statement = $db->prepare(
                'INSERT INTO purchases
                    (supplier_id, warehouse_id, doc_number, purchase_date, due_date, currency_code, exchange_rate, subtotal_original, total_original, amount_paid_original, balance_original, subtotal_converted, total_converted, amount_paid_converted, balance_converted, notes)
                 VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $header['supplier_id'],
                $documentNumber,
                $header['purchase_date'],
                $header['due_date'] ?? $header['purchase_date'],
                $header['currency_code'],
                $header['exchange_rate'],
                $header['subtotal_original'],
                $header['total_original'],
                0,
                $header['total_original'],
                $header['subtotal_converted'],
                $header['total_converted'],
                0,
                $header['total_converted'],
                $header['notes'] ?? null,
            ]);

            $purchaseId = (int) $db->lastInsertId();
            if ($documentNumber === '__PENDING__') {
                $documentNumber = $this->formatNumber($purchaseId);
                $db->prepare('UPDATE purchases SET doc_number = ? WHERE id = ?')->execute([$documentNumber, $purchaseId]);
            }
            $detail = $db->prepare(
                'INSERT INTO purchase_items
                    (purchase_id, product_id, quantity, cost_original, cost_converted, total_original, total_converted)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($items as $item) {
                $detail->execute([
                    $purchaseId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['cost_original'],
                    $item['cost_converted'],
                    $item['total_original'],
                    $item['total_converted'],
                ]);

                $update = $db->prepare('UPDATE products SET cost = ?, currency_code = ? WHERE id = ?');
                $update->execute([$item['cost_converted'], $header['currency_code'], $item['product_id']]);

                Inventory::increase(
                    (int) $item['product_id'],
                    (float) $item['quantity'],
                    'purchase',
                    'COMPRA #' . $purchaseId,
                    'Entrada por compra'
                );
            }

            $db->commit();

            return $purchaseId;
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
            $purchase = $this->findFull($id);

            if (! $purchase) {
                throw new \RuntimeException('Compra no encontrada.');
            }

            if (($purchase['status'] ?? 'active') === 'cancelled') {
                throw new \RuntimeException('La compra ya estaba anulada.');
            }

            foreach ($purchase['items'] as $item) {
                $this->assertItemStockCanBeReversed($item, 'anular la compra');
                Inventory::decrease(
                    (int) $item['product_id'],
                    (float) $item['quantity'],
                    'purchase_cancel',
                    'ANULACION COMPRA #' . $id,
                    $reason !== '' ? $reason : 'Reversion por anulacion de compra'
                );
            }

            $statement = $db->prepare(
                'UPDATE purchases
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

    public function updatePurchase(int $id, array $header, array $items): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $purchase = $this->findFull($id);

            if (! $purchase) {
                throw new \RuntimeException('Compra no encontrada.');
            }

            if (($purchase['status'] ?? 'active') === 'cancelled') {
                throw new \RuntimeException('No se puede editar una compra anulada.');
            }

            foreach ($purchase['items'] as $item) {
                $this->assertItemStockCanBeReversed($item, 'editar la compra');
                Inventory::decrease(
                    (int) $item['product_id'],
                    (float) $item['quantity'],
                    'purchase_edit_reverse',
                    'EDICION COMPRA #' . $id,
                    'Reversion previa por edicion de compra'
                );
            }

            $statement = $db->prepare(
                'UPDATE purchases
                 SET supplier_id = ?,
                     doc_number = ?,
                     purchase_date = ?,
                     due_date = ?,
                     currency_code = ?,
                     exchange_rate = ?,
                     subtotal_original = ?,
                     total_original = ?,
                     balance_original = ?,
                     subtotal_converted = ?,
                     total_converted = ?,
                     balance_converted = ?,
                     notes = ?
                 WHERE id = ?'
            );
            $statement->execute([
                $header['supplier_id'],
                $header['doc_number'],
                $header['purchase_date'],
                $header['due_date'] ?? $header['purchase_date'],
                $header['currency_code'],
                $header['exchange_rate'],
                $header['subtotal_original'],
                $header['total_original'],
                max(0.0, (float) $header['total_original'] - (float) ($purchase['amount_paid_original'] ?? 0)),
                $header['subtotal_converted'],
                $header['total_converted'],
                max(0.0, (float) $header['total_converted'] - (float) ($purchase['amount_paid_converted'] ?? 0)),
                $header['notes'] ?? null,
                $id,
            ]);

            $db->prepare('DELETE FROM purchase_items WHERE purchase_id = ?')->execute([$id]);

            $detail = $db->prepare(
                'INSERT INTO purchase_items
                    (purchase_id, product_id, quantity, cost_original, cost_converted, total_original, total_converted)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($items as $item) {
                $detail->execute([
                    $id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['cost_original'],
                    $item['cost_converted'],
                    $item['total_original'],
                    $item['total_converted'],
                ]);

                $update = $db->prepare('UPDATE products SET cost = ?, currency_code = ? WHERE id = ?');
                $update->execute([$item['cost_converted'], $header['currency_code'], $item['product_id']]);

                Inventory::increase(
                    (int) $item['product_id'],
                    (float) $item['quantity'],
                    'purchase_edit',
                    'EDICION COMPRA #' . $id,
                    'Entrada actualizada por edicion de compra'
                );
            }

            $this->syncPaymentState($id, $db);
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function deletePurchase(int $id, string $reason = ''): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $purchase = $this->findFull($id);

            if (! $purchase) {
                throw new \RuntimeException('Compra no encontrada.');
            }

            if (($purchase['status'] ?? 'active') === 'cancelled') {
                throw new \RuntimeException('No se puede eliminar una compra anulada.');
            }

            foreach ($purchase['items'] as $item) {
                $this->assertItemStockCanBeReversed($item, 'eliminar la compra');
                Inventory::decrease(
                    (int) $item['product_id'],
                    (float) $item['quantity'],
                    'purchase_delete',
                    'ELIMINACION COMPRA #' . $id,
                    $reason !== '' ? $reason : 'Reversion por eliminacion de compra'
                );
            }

            $db->prepare('DELETE FROM purchases WHERE id = ?')->execute([$id]);

            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function findFull(int $id): ?array
    {
        $db = Database::connection();
        $header = $db->prepare(
            'SELECT p.*, s.name AS supplier_name, s.document AS supplier_document, s.phone AS supplier_phone, s.email AS supplier_email
             FROM purchases p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.id = ?'
        );
        $header->execute([$id]);
        $purchase = $header->fetch();

        if (! $purchase) {
            return null;
        }

        $items = $db->prepare(
            'SELECT
                pi.*,
                pr.name AS product_name,
                pr.sku AS product_sku,
                pr.product_type,
                pr.unit_label,
                pr.stock AS product_stock,
                pr.status AS product_status,
                pr.deleted_at AS product_deleted_at
             FROM purchase_items pi
             LEFT JOIN products pr ON pr.id = pi.product_id
             WHERE pi.purchase_id = ?'
        );
        $items->execute([$id]);
        $purchase['items'] = $items->fetchAll();
        $purchase['payments'] = (new PurchasePayment())->byPurchase($id);
        $purchase['payment_status_effective'] = $this->resolvePaymentStatus($purchase);

        return $purchase;
    }

    public function registerPayment(int $id, array $payment): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $purchase = $this->findFull($id);

            if (! $purchase) {
                throw new \RuntimeException('Compra no encontrada.');
            }

            if (($purchase['status'] ?? 'active') === 'cancelled') {
                throw new \RuntimeException('No puedes registrar pagos en una compra anulada.');
            }

            $balanceConverted = round_money((float) ($purchase['balance_converted'] ?? 0));
            if ($balanceConverted <= 0.01) {
                throw new \RuntimeException('La compra ya no tiene saldo pendiente.');
            }

            $appliedConverted = round_money((float) ($payment['applied_converted'] ?? 0));
            if ($appliedConverted <= 0) {
                throw new \RuntimeException('El pago debe ser mayor a cero.');
            }

            if (payment_exceeds_balance($appliedConverted, $balanceConverted)) {
                throw new \RuntimeException('El pago excede el saldo pendiente de la compra.');
            }

            $statement = $db->prepare(
                'INSERT INTO purchase_payments
                    (purchase_id, payment_date, reference, payment_method, currency_code, exchange_rate, amount_original, amount_converted, applied_original, applied_converted, treasury_account_id, notes)
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
                'direction' => 'out',
                'currency_code' => $payment['currency_code'],
                'exchange_rate' => $payment['exchange_rate'],
                'amount_original' => $payment['amount_original'],
                'amount_converted' => $payment['amount_converted'],
                'source_type' => 'purchase_payment',
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

    public function effectivePaymentStatus(array $purchase): string
    {
        return $this->resolvePaymentStatus($purchase);
    }

    private function syncPaymentState(int $purchaseId, \PDO $db): void
    {
        $paymentTotals = $db->prepare(
            'SELECT
                COALESCE(SUM(applied_original), 0) AS paid_original,
                COALESCE(SUM(applied_converted), 0) AS paid_converted
             FROM purchase_payments
             WHERE purchase_id = ?'
        );
        $paymentTotals->execute([$purchaseId]);
        $totals = $paymentTotals->fetch() ?: [];

        $purchase = $db->prepare('SELECT * FROM purchases WHERE id = ? LIMIT 1');
        $purchase->execute([$purchaseId]);
        $row = $purchase->fetch();
        if (! $row) {
            throw new \RuntimeException('Compra no encontrada.');
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
            'UPDATE purchases
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
            $purchaseId,
        ]);
    }

    private function resolvePaymentStatus(array $purchase, bool $allowOverdue = true): string
    {
        if (($purchase['status'] ?? 'active') === 'cancelled') {
            return 'cancelled';
        }

        $balance = (float) ($purchase['balance_converted'] ?? 0);
        $paid = (float) ($purchase['amount_paid_converted'] ?? 0);

        if ($balance <= 0.01) {
            return 'paid';
        }

        $dueDate = (string) ($purchase['due_date'] ?? $purchase['purchase_date'] ?? '');
        if ($allowOverdue && $dueDate !== '' && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
            return $paid > 0 ? 'partial_overdue' : 'overdue';
        }

        return $paid > 0 ? 'partial' : 'pending';
    }

    private function formatNumber(int $id): string
    {
        return 'C-' . str_pad((string) max(1, $id), 4, '0', STR_PAD_LEFT);
    }

    private function assertItemStockCanBeReversed(array $item, string $action): void
    {
        $requiredQuantity = (float) ($item['quantity'] ?? 0);
        $availableStock = (float) ($item['product_stock'] ?? 0);

        if ($requiredQuantity <= 0 || $availableStock + 0.00001 >= $requiredQuantity) {
            return;
        }

        $productName = trim((string) ($item['product_name'] ?? 'Producto sin nombre'));
        $unitLabel = trim((string) ($item['unit_label'] ?? 'und'));
        $archivedNote = ! empty($item['product_deleted_at']) ? ' El producto ademas esta archivado.' : '';

        throw new \RuntimeException(
            'No se puede ' . $action . ' porque el producto "' . $productName . '" solo tiene '
            . money($availableStock) . ' ' . $unitLabel . ' disponibles y la reversa necesita '
            . money($requiredQuantity) . ' ' . $unitLabel . '.' . $archivedNote
        );
    }
}
