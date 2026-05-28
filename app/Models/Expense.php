<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Expense extends Model
{
    protected string $table = 'expenses';

    public function byRange(?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT e.*, c.name AS category_name, a.account_name AS treasury_account_name
                FROM expenses e
                LEFT JOIN expense_categories c ON c.id = e.category_id
                LEFT JOIN cash_accounts a ON a.id = e.treasury_account_id
                WHERE 1=1';
        $params = [];

        if ($from) {
            $sql .= ' AND e.expense_date >= :from';
            $params['from'] = $from;
        }

        if ($to) {
            $sql .= ' AND e.expense_date <= :to';
            $params['to'] = $to;
        }

        $sql .= ' ORDER BY e.expense_date DESC, e.id DESC';

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function createRegistered(array $data): int
    {
        $this->db->beginTransaction();

        try {
            $method = strtolower(trim((string) ($data['payment_method'] ?? 'cash')));
            $currency = normalize_currency_code((string) ($data['currency_code'] ?? secondary_currency()));
            $accountId = (int) ($data['treasury_account_id'] ?? 0);
            if ($accountId <= 0) {
                $accountId = (new CashAccount())->resolveId($method, $currency, $this->db);
            }

            $statement = $this->db->prepare(
                'INSERT INTO expenses
                    (category_id, expense_date, reference, description, currency_code, exchange_rate, amount_original, amount_converted, payment_method, treasury_account_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $data['category_id'],
                $data['expense_date'],
                $data['reference'],
                $data['description'] ?? null,
                $currency,
                $data['exchange_rate'],
                $data['amount_original'],
                $data['amount_converted'],
                $method,
                $accountId,
            ]);

            $expenseId = (int) $this->db->lastInsertId();
            (new CashMovement())->record([
                'cash_account_id' => $accountId,
                'movement_date' => $data['expense_date'],
                'direction' => 'out',
                'currency_code' => $currency,
                'exchange_rate' => $data['exchange_rate'],
                'amount_original' => $data['amount_original'],
                'amount_converted' => $data['amount_converted'],
                'source_type' => 'expense',
                'source_id' => $expenseId,
                'reference' => $data['reference'],
                'notes' => $data['description'] ?? null,
            ], $this->db);

            $this->db->commit();

            return $expenseId;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function updateRegistered(int $id, array $data): void
    {
        $this->db->beginTransaction();

        try {
            $expenseStatement = $this->db->prepare(
                'SELECT *
                 FROM expenses
                 WHERE id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $expenseStatement->execute([$id]);
            $expense = $expenseStatement->fetch() ?: null;

            if (! is_array($expense)) {
                throw new \RuntimeException('Gasto no encontrado.');
            }

            if (($expense['status'] ?? 'active') === 'cancelled') {
                throw new \RuntimeException('No puedes editar un gasto anulado.');
            }

            $method = strtolower(trim((string) ($data['payment_method'] ?? 'cash')));
            $currency = normalize_currency_code((string) ($data['currency_code'] ?? secondary_currency()));
            $accountId = (int) ($data['treasury_account_id'] ?? 0);
            if ($accountId <= 0) {
                $accountId = (new CashAccount())->resolveId($method, $currency, $this->db);
            }

            $updateExpense = $this->db->prepare(
                'UPDATE expenses
                 SET category_id = ?,
                     expense_date = ?,
                     reference = ?,
                     description = ?,
                     currency_code = ?,
                     exchange_rate = ?,
                     amount_original = ?,
                     amount_converted = ?,
                     payment_method = ?,
                     treasury_account_id = ?
                 WHERE id = ?'
            );
            $updateExpense->execute([
                $data['category_id'],
                $data['expense_date'],
                $data['reference'],
                $data['description'] !== '' ? $data['description'] : null,
                $currency,
                $data['exchange_rate'],
                $data['amount_original'],
                $data['amount_converted'],
                $method,
                $accountId,
                $id,
            ]);

            $movementLookup = $this->db->prepare(
                'SELECT id
                 FROM cash_movements
                 WHERE source_type = ?
                   AND source_id = ?
                   AND is_reversed = 0
                 ORDER BY id ASC
                 LIMIT 1'
            );
            $movementLookup->execute(['expense', $id]);
            $movement = $movementLookup->fetch() ?: null;

            if (is_array($movement)) {
                $updateMovement = $this->db->prepare(
                    'UPDATE cash_movements
                     SET cash_account_id = ?,
                         movement_date = ?,
                         currency_code = ?,
                         exchange_rate = ?,
                         amount_original = ?,
                         amount_converted = ?,
                         reference = ?,
                         notes = ?
                     WHERE id = ?'
                );
                $updateMovement->execute([
                    $accountId,
                    $data['expense_date'],
                    $currency,
                    $data['exchange_rate'],
                    $data['amount_original'],
                    $data['amount_converted'],
                    $data['reference'],
                    $data['description'] !== '' ? $data['description'] : null,
                    (int) ($movement['id'] ?? 0),
                ]);
            } else {
                (new CashMovement())->record([
                    'cash_account_id' => $accountId,
                    'movement_date' => $data['expense_date'],
                    'direction' => 'out',
                    'currency_code' => $currency,
                    'exchange_rate' => $data['exchange_rate'],
                    'amount_original' => $data['amount_original'],
                    'amount_converted' => $data['amount_converted'],
                    'source_type' => 'expense',
                    'source_id' => $id,
                    'reference' => $data['reference'],
                    'notes' => $data['description'] ?? null,
                ], $this->db);
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function cancel(int $id, string $reason = ''): void
    {
        $expense = $this->find($id);

        if (! $expense) {
            throw new \RuntimeException('Gasto no encontrado.');
        }

        if (($expense['status'] ?? 'active') === 'cancelled') {
            throw new \RuntimeException('El gasto ya estaba anulado.');
        }

        $statement = $this->db->prepare(
            'UPDATE expenses
             SET status = ?, cancelled_at = NOW(), cancellation_reason = ?
             WHERE id = ?'
        );
        $this->db->beginTransaction();

        try {
            $statement->execute(['cancelled', $reason !== '' ? $reason : null, $id]);
            (new CashMovement())->reverseSource('expense', $id, $reason, $this->db);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
}
