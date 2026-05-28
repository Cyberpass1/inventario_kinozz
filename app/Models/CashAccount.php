<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class CashAccount extends Model
{
    protected string $table = 'cash_accounts';

    public function active(?string $currency = null): array
    {
        $sql = 'SELECT *
                FROM cash_accounts
                WHERE is_active = 1';
        $params = [];

        if ($currency !== null && trim($currency) !== '') {
            $sql .= ' AND currency_code = :currency';
            $params['currency'] = normalize_currency_code($currency);
        }

        $sql .= ' ORDER BY currency_code ASC, account_name ASC, id ASC';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function resolveId(string $method, string $currency, ?\PDO $db = null): int
    {
        $connection = $db ?? $this->db;
        $method = strtolower(trim($method));
        $currency = normalize_currency_code($currency);

        $statement = $connection->prepare(
            'SELECT id
             FROM cash_accounts
             WHERE method_type = ?
               AND currency_code = ?
             ORDER BY is_active DESC, id ASC
             LIMIT 1'
        );
        $statement->execute([$method, $currency]);
        $existingId = (int) ($statement->fetchColumn() ?: 0);

        if ($existingId > 0) {
            return $existingId;
        }

        $insert = $connection->prepare(
            'INSERT INTO cash_accounts
                (account_code, account_name, method_type, currency_code, opening_balance, is_active)
             VALUES (?, ?, ?, ?, 0, 1)'
        );
        $insert->execute([
            treasury_account_code($method, $currency),
            treasury_account_label($method, $currency),
            $method,
            $currency,
        ]);

        return (int) $connection->lastInsertId();
    }

    public function setOpeningBalances(array $balances): void
    {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare(
                'UPDATE cash_accounts
                 SET opening_balance = ?
                 WHERE id = ?'
            );

            foreach ($balances as $accountId => $amount) {
                $statement->execute([
                    parse_money_input($amount),
                    (int) $accountId,
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
}
