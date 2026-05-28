<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class DeliveryNote
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
            $conditions[] = 'd.note_date >= ?';
            $params[] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $conditions[] = 'd.note_date <= ?';
            $params[] = $dateTo;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $conditions[] = "(
                d.note_number LIKE ?
                OR COALESCE(c.name, '') LIKE ?
                OR COALESCE(c.document, '') LIKE ?
                OR COALESCE(d.notes, '') LIKE ?
                OR EXISTS (
                    SELECT 1
                    FROM delivery_note_items sdi
                    INNER JOIN products sp ON sp.id = sdi.product_id
                    WHERE sdi.delivery_note_id = d.id
                      AND (
                          COALESCE(sp.name, '') LIKE ?
                          OR COALESCE(sp.sku, '') LIKE ?
                      )
                )
            )";
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $sql = "SELECT
                    d.*,
                    c.name AS client_name,
                    c.document AS client_document,
                    COALESCE(SUM(di.quantity), 0) AS total_quantity,
                    COUNT(di.id) AS line_count,
                    GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS products_summary
                FROM delivery_notes d
                LEFT JOIN clients c ON c.id = d.client_id
                LEFT JOIN delivery_note_items di ON di.delivery_note_id = d.id
                LEFT JOIN products p ON p.id = di.product_id";

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY
                    d.id,
                    d.client_id,
                    d.note_number,
                    d.note_date,
                    d.due_date,
                    d.currency_code,
                    d.exchange_rate,
                    d.subtotal_original,
                    d.total_original,
                    d.amount_paid_original,
                    d.balance_original,
                    d.subtotal_converted,
                    d.total_converted,
                    d.amount_paid_converted,
                    d.balance_converted,
                    d.notes,
                    d.status,
                    d.payment_status,
                    d.cancelled_at,
                    d.cancellation_reason,
                    d.created_at,
                    c.name,
                    c.document
                ORDER BY d.id DESC";

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, (int) $limit);
        }

        $statement = $db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function nextNumber(): string
    {
        $row = Database::connection()->query('SELECT id FROM delivery_notes ORDER BY id DESC LIMIT 1')->fetch();
        return 'NE-' . str_pad((string) (((int) ($row['id'] ?? 0)) + 1), 6, '0', STR_PAD_LEFT);
    }

    public function create(array $header, array $items): int
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $statement = $db->prepare(
                'INSERT INTO delivery_notes
                    (client_id, note_number, note_date, due_date, currency_code, exchange_rate, subtotal_original, total_original, amount_paid_original, balance_original, subtotal_converted, total_converted, amount_paid_converted, balance_converted, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $header['client_id'],
                $header['note_number'],
                $header['note_date'],
                $header['due_date'] ?? $header['note_date'],
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

            $id = (int) $db->lastInsertId();
            $detail = $db->prepare(
                'INSERT INTO delivery_note_items
                    (delivery_note_id, product_id, warehouse_id, quantity, price_original, price_converted, total_original, total_converted)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?)'
            );

            foreach ($items as $item) {
                $detail->execute([
                    $id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price_original'] ?? 0,
                    $item['price_converted'] ?? 0,
                    $item['total_original'] ?? 0,
                    $item['total_converted'] ?? 0,
                ]);

                $product = (new Product())->findVisible((int) $item['product_id']);
                if ($product && product_tracks_inventory($product)) {
                    Inventory::decrease(
                        (int) $item['product_id'],
                        (float) $item['quantity'],
                        'delivery_note',
                        'NOTA #' . $id,
                        'Salida por nota de entrega'
                    );
                }
            }

            $db->commit();

            return $id;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function findFull(int $id): ?array
    {
        $db = Database::connection();
        $statement = $db->prepare(
            'SELECT d.*, c.name AS client_name, c.document AS client_document, c.phone AS client_phone
             FROM delivery_notes d
             LEFT JOIN clients c ON c.id = d.client_id
             WHERE d.id = ?'
        );
        $statement->execute([$id]);
        $note = $statement->fetch();

        if (! $note) {
            return null;
        }

        $items = $db->prepare(
            'SELECT di.*, p.name AS product_name, p.product_type
             FROM delivery_note_items di
             LEFT JOIN products p ON p.id = di.product_id
             WHERE di.delivery_note_id = ?'
        );
        $items->execute([$id]);
        $note['items'] = $items->fetchAll();
        $note['payments'] = (new DeliveryNotePayment())->byNote($id);
        $note = $this->applyEffectivePaymentState($note);
        $note['payment_status_effective'] = $this->resolvePaymentStatus($note);

        return $note;
    }

    public function registerPayment(int $id, array $payment): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $note = $this->findFull($id);

            if (! $note) {
                throw new \RuntimeException('Nota de entrega no encontrada.');
            }

            if (($note['status'] ?? 'active') === 'cancelled') {
                throw new \RuntimeException('No puedes registrar pagos en una nota anulada.');
            }

            $balanceOriginal = round_money((float) ($note['balance_original'] ?? 0));
            if ($balanceOriginal <= 0.01) {
                throw new \RuntimeException('La nota ya no tiene saldo pendiente.');
            }

            $appliedOriginal = round_money((float) ($payment['applied_original'] ?? 0));
            if ($appliedOriginal <= 0) {
                throw new \RuntimeException('El pago debe ser mayor a cero.');
            }

            if (payment_exceeds_balance($appliedOriginal, $balanceOriginal)) {
                throw new \RuntimeException('El pago excede el saldo pendiente de la nota.');
            }

            $statement = $db->prepare(
                'INSERT INTO delivery_note_payments
                    (delivery_note_id, payment_date, reference, payment_method, currency_code, exchange_rate, amount_original, amount_converted, applied_original, applied_converted, treasury_account_id, notes)
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
                $payment['payment_method'],
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
                'source_type' => 'delivery_note_payment',
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

    public function effectivePaymentStatus(array $note): string
    {
        return $this->resolvePaymentStatus($note);
    }

    public function cancel(int $id, string $reason = ''): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $note = $this->findFull($id);

            if (! $note) {
                throw new \RuntimeException('Nota de entrega no encontrada.');
            }

            if (($note['status'] ?? 'active') === 'cancelled') {
                throw new \RuntimeException('La nota de entrega ya estaba anulada.');
            }

            foreach ($note['items'] as $item) {
                if (product_tracks_inventory((string) ($item['product_type'] ?? 'merchandise'))) {
                    Inventory::increase(
                        (int) $item['product_id'],
                        (float) $item['quantity'],
                        'delivery_note_cancel',
                        'ANULACION NOTA #' . $id,
                        $reason !== '' ? $reason : 'Reversion por anulacion de nota de entrega'
                    );
                }
            }

            $statement = $db->prepare(
                'UPDATE delivery_notes
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

    private function syncPaymentState(int $noteId, \PDO $db): void
    {
        $paymentTotals = $db->prepare(
            'SELECT
                COALESCE(SUM(applied_original), 0) AS paid_original,
                COALESCE(SUM(applied_converted), 0) AS paid_converted
             FROM delivery_note_payments
             WHERE delivery_note_id = ?'
        );
        $paymentTotals->execute([$noteId]);
        $totals = $paymentTotals->fetch() ?: [];

        $note = $db->prepare('SELECT * FROM delivery_notes WHERE id = ? LIMIT 1');
        $note->execute([$noteId]);
        $row = $note->fetch();
        if (! $row) {
            throw new \RuntimeException('Nota no encontrada.');
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
            'UPDATE delivery_notes
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
            $noteId,
        ]);
    }

    private function resolvePaymentStatus(array $note, bool $allowOverdue = true): string
    {
        if (($note['status'] ?? 'active') === 'cancelled') {
            return 'cancelled';
        }

        $balance = (float) ($note['balance_converted'] ?? 0);
        $paid = (float) ($note['amount_paid_converted'] ?? 0);

        if ($balance <= 0.01) {
            return 'paid';
        }

        $dueDate = (string) ($note['due_date'] ?? $note['note_date'] ?? '');
        if ($allowOverdue && $dueDate !== '' && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
            return $paid > 0 ? 'partial_overdue' : 'overdue';
        }

        return $paid > 0 ? 'partial' : 'pending';
    }

    private function applyEffectivePaymentState(array $note): array
    {
        $payments = is_array($note['payments'] ?? null) ? $note['payments'] : [];
        $paidOriginal = round_money(min(
            (float) ($note['total_original'] ?? 0),
            array_reduce(
                $payments,
                static fn (float $carry, array $payment): float => $carry + (float) ($payment['applied_original'] ?? 0),
                0.0
            )
        ));
        $paidConverted = round_money(min(
            (float) ($note['total_converted'] ?? 0),
            array_reduce(
                $payments,
                static fn (float $carry, array $payment): float => $carry + (float) ($payment['applied_converted'] ?? 0),
                0.0
            )
        ));

        $note['amount_paid_original'] = $paidOriginal;
        $note['amount_paid_converted'] = $paidConverted;
        $note['balance_original'] = round_money(max(0.0, (float) ($note['total_original'] ?? 0) - $paidOriginal));
        $note['balance_converted'] = round_money(max(0.0, (float) ($note['total_converted'] ?? 0) - $paidConverted));
        $note['payment_status'] = $this->resolvePaymentStatus($note, false);

        return $note;
    }
}
