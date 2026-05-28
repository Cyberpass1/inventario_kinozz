<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class CashMovement extends Model
{
    protected string $table = 'cash_movements';

    public function record(array $data, ?\PDO $db = null): int
    {
        $connection = $db ?? $this->db;
        $statement = $connection->prepare(
            'INSERT INTO cash_movements
                (cash_account_id, movement_date, direction, currency_code, exchange_rate, amount_original, amount_converted, source_type, source_id, reference, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $data['cash_account_id'],
            $data['movement_date'],
            $data['direction'],
            normalize_currency_code((string) ($data['currency_code'] ?? '')),
            $data['exchange_rate'] ?? 0,
            $data['amount_original'] ?? 0,
            $data['amount_converted'] ?? 0,
            $data['source_type'] ?? null,
            $data['source_id'] ?? null,
            $data['reference'] ?? '',
            $data['notes'] ?? null,
        ]);

        return (int) $connection->lastInsertId();
    }

    public function reverseSource(string $sourceType, int $sourceId, string $reason = '', ?\PDO $db = null): void
    {
        $connection = $db ?? $this->db;
        $statement = $connection->prepare(
            'UPDATE cash_movements
             SET is_reversed = 1,
                 reversed_at = NOW(),
                 reversal_reason = ?
             WHERE source_type = ?
               AND source_id = ?
               AND is_reversed = 0'
        );
        $statement->execute([$reason !== '' ? $reason : null, $sourceType, $sourceId]);
    }

    public function reconcileToRealBalance(
        int $accountId,
        float $realBalance,
        string $movementDate,
        float $rate,
        string $notes = ''
    ): int {
        $account = (new CashAccount())->find($accountId);
        if (! $account) {
            throw new \RuntimeException('Cuenta de tesoreria no encontrada.');
        }

        $currency = normalize_currency_code((string) ($account['currency_code'] ?? ''));
        $systemBalance = $this->balanceAt($accountId, $movementDate);
        $difference = round($realBalance - $systemBalance, 2);

        if (abs($difference) <= 0.009) {
            throw new \RuntimeException('La cuenta ya coincide con el saldo real indicado.');
        }

        $amount = abs($difference);
        $direction = $difference > 0 ? 'in' : 'out';
        $detail = trim($notes);
        $systemText = money($systemBalance) . ' ' . $currency;
        $realText = money($realBalance) . ' ' . $currency;
        $adjustmentNotes = 'Conciliacion manual. Saldo sistema: ' . $systemText . '. Saldo real: ' . $realText . '.';
        if ($detail !== '') {
            $adjustmentNotes .= ' ' . $detail;
        }

        return $this->record([
            'cash_account_id' => $accountId,
            'movement_date' => $movementDate,
            'direction' => $direction,
            'currency_code' => $currency,
            'exchange_rate' => $rate,
            'amount_original' => $amount,
            'amount_converted' => equivalent_in_bolivars($amount, $currency, $rate),
            'source_type' => 'treasury_adjustment',
            'source_id' => null,
            'reference' => 'AJUSTE TESORERIA',
            'notes' => $adjustmentNotes,
        ]);
    }

    public function balanceAt(int $accountId, string $date): float
    {
        $statement = $this->db->prepare(
            "SELECT
                COALESCE(a.opening_balance, 0)
                + COALESCE(SUM(CASE
                    WHEN m.is_reversed = 0 AND m.movement_date <= ? AND m.direction = 'in' THEN m.amount_original
                    WHEN m.is_reversed = 0 AND m.movement_date <= ? AND m.direction = 'out' THEN -m.amount_original
                    ELSE 0
                END), 0) AS balance
             FROM cash_accounts a
             LEFT JOIN cash_movements m ON m.cash_account_id = a.id
             WHERE a.id = ?
             GROUP BY a.id, a.opening_balance"
        );
        $statement->execute([$date, $date, $accountId]);

        return (float) ($statement->fetch()['balance'] ?? 0);
    }

    public function reverseManualAdjustment(int $id, string $reason = ''): void
    {
        $movement = $this->find($id);
        if (! $movement) {
            throw new \RuntimeException('Movimiento de tesoreria no encontrado.');
        }

        if (($movement['source_type'] ?? '') !== 'treasury_adjustment') {
            throw new \RuntimeException('Solo puedes revertir ajustes manuales desde tesoreria.');
        }

        if ((int) ($movement['is_reversed'] ?? 0) === 1) {
            throw new \RuntimeException('Este ajuste ya estaba reversado.');
        }

        $statement = $this->db->prepare(
            'UPDATE cash_movements
             SET is_reversed = 1,
                 reversed_at = NOW(),
                 reversal_reason = ?
             WHERE id = ?'
        );
        $statement->execute([$reason !== '' ? $reason : null, $id]);
    }
}
