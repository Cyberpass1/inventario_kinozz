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

    /**
     * Resuelve la cuenta para un pago a partir de metodo + moneda, SIN crear cuentas
     * nuevas. Es solo un fallback: el flujo normal envia treasury_account_id explicito.
     * Prefiere coincidencia exacta por metodo; si no hay, cualquier cuenta activa de
     * esa moneda. Si no existe ninguna, lanza una excepcion clara.
     */
    public function resolveId(string $method, string $currency, ?\PDO $db = null): int
    {
        $connection = $db ?? $this->db;
        $method = strtolower(trim($method));
        $currency = normalize_currency_code($currency);

        $statement = $connection->prepare(
            'SELECT id
             FROM cash_accounts
             WHERE is_active = 1
               AND currency_code = ?
             ORDER BY (method_type = ?) DESC, id ASC
             LIMIT 1'
        );
        $statement->execute([$currency, $method]);
        $existingId = (int) ($statement->fetchColumn() ?: 0);

        if ($existingId > 0) {
            return $existingId;
        }

        throw new \RuntimeException(
            'No hay una cuenta de tesoreria configurada para ' . $currency
            . '. Crea una en Ajustes > Cuentas de tesoreria.'
        );
    }

    public function allManaged(): array
    {
        return $this->db
            ->query('SELECT * FROM cash_accounts ORDER BY is_active DESC, account_type ASC, currency_code ASC, account_name ASC')
            ->fetchAll();
    }

    public function idsWithMovements(): array
    {
        $rows = $this->db
            ->query('SELECT DISTINCT cash_account_id FROM cash_movements')
            ->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $rows);
    }

    public function hasMovements(int $id): bool
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM cash_movements WHERE cash_account_id = ?');
        $statement->execute([$id]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function activeCountForCurrency(string $currency, int $excludeId = 0): int
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM cash_accounts WHERE is_active = 1 AND currency_code = ? AND id <> ?'
        );
        $statement->execute([normalize_currency_code($currency), $excludeId]);

        return (int) $statement->fetchColumn();
    }

    public function createAccount(array $data): int
    {
        $type = treasury_account_type_normalize((string) ($data['account_type'] ?? 'bank'));
        $currency = normalize_currency_code((string) ($data['currency_code'] ?? base_currency()));
        $method = (string) ($data['method_type'] ?? account_type_default_method($type));

        return $this->insert([
            'account_code' => $this->generateCode($type, $currency),
            'account_name' => trim((string) ($data['account_name'] ?? '')),
            'account_type' => $type,
            'method_type' => $method,
            'currency_code' => $currency,
            'opening_balance' => parse_money_input($data['opening_balance'] ?? 0),
            'is_active' => 1,
        ]);
    }

    public function updateAccount(int $id, array $data): void
    {
        $current = $this->find($id);
        if (! $current) {
            throw new \RuntimeException('Cuenta de tesoreria no encontrada.');
        }

        $type = treasury_account_type_normalize((string) ($data['account_type'] ?? $current['account_type']));
        $fields = [
            'account_name' => trim((string) ($data['account_name'] ?? $current['account_name'])),
            'account_type' => $type,
            'method_type' => (string) ($data['method_type'] ?? $current['method_type'] ?? account_type_default_method($type)),
            'opening_balance' => parse_money_input($data['opening_balance'] ?? $current['opening_balance']),
        ];

        // La moneda solo se puede cambiar si la cuenta no tiene movimientos
        // (cambiarla rompe la coherencia de cash_movements).
        if (isset($data['currency_code']) && ! $this->hasMovements($id)) {
            $fields['currency_code'] = normalize_currency_code((string) $data['currency_code']);
        }

        $this->update($id, $fields);
    }

    public function setStatus(int $id, bool $active): void
    {
        $this->update($id, ['is_active' => $active ? 1 : 0]);
    }

    /**
     * Genera un account_code unico: prefijo por tipo + sufijo por moneda + correlativo.
     */
    public function generateCode(string $type, string $currency): string
    {
        $typeCode = match (treasury_account_type_normalize($type)) {
            'cash' => '11',
            'wallet' => '15',
            default => '13',
        };
        $currencyCode = is_bolivar_currency($currency) ? '02' : '01';
        $base = '101' . $typeCode . $currencyCode;

        $statement = $this->db->prepare('SELECT account_code FROM cash_accounts WHERE account_code LIKE ?');
        $statement->execute([$base . '%']);
        $existing = array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));

        if (! in_array($base, $existing, true)) {
            return $base;
        }

        $suffix = 1;
        do {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        } while (in_array($candidate, $existing, true));

        return $candidate;
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
