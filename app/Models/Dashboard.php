<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Dashboard
{
    public function stats(): array
    {
        $db = Database::connection();
        $rate = system_exchange_rate(date('Y-m-d'));
        $baseCurrency = base_currency();
        $secondaryCurrency = secondary_currency();
        $expenseAmountSql = $this->expenseConsolidatedSql();
        $queries = [
            'products' => 'SELECT COUNT(*) total FROM products WHERE deleted_at IS NULL',
            'clients' => 'SELECT COUNT(*) total FROM clients',
            'suppliers' => 'SELECT COUNT(*) total FROM suppliers',
            'expenses' => "SELECT COALESCE(SUM({$expenseAmountSql}), 0) total FROM expenses WHERE COALESCE(status, 'active') <> 'cancelled'",
            'sales' => "SELECT (
                    COALESCE((SELECT SUM(total_converted) FROM invoices WHERE COALESCE(status, 'active') <> 'cancelled'), 0)
                    + COALESCE((SELECT SUM(total_converted) FROM delivery_notes WHERE COALESCE(status, 'active') <> 'cancelled'), 0)
                ) AS total",
            'purchases' => "SELECT COALESCE(SUM(total_converted), 0) total FROM purchases WHERE COALESCE(status, 'active') <> 'cancelled'",
            'receivables' => "SELECT (
                    COALESCE((SELECT SUM(balance_converted) FROM invoices WHERE COALESCE(status, 'active') <> 'cancelled'), 0)
                    + COALESCE((SELECT SUM(balance_converted) FROM delivery_notes WHERE COALESCE(status, 'active') <> 'cancelled'), 0)
                ) AS total",
            'payables' => "SELECT COALESCE(SUM(balance_converted), 0) total FROM purchases WHERE COALESCE(status, 'active') <> 'cancelled'",
        ];

        $data = [];
        foreach ($queries as $key => $sql) {
            $data[$key] = (float) $db->query($sql)->fetch()['total'];
        }

        foreach (['expenses', 'sales', 'purchases', 'receivables', 'payables'] as $amountKey) {
            $data[$amountKey . '_base'] = convert_currency_amount(
                (float) ($data[$amountKey] ?? 0),
                $secondaryCurrency,
                $baseCurrency,
                $rate
            );
            $data[$amountKey . '_secondary'] = (float) ($data[$amountKey] ?? 0);
        }

        $inventoryRows = $db->query(
            "SELECT stock, cost, currency_code
             FROM products
             WHERE deleted_at IS NULL
               AND COALESCE(status, 'active') = 'active'
               AND COALESCE(product_type, 'merchandise') <> 'service'"
        )->fetchAll(PDO::FETCH_ASSOC);
        $inventoryValueBase = 0.0;
        $inventoryValueSecondary = 0.0;
        foreach ($inventoryRows as $row) {
            $lineAmount = ((float) ($row['stock'] ?? 0)) * ((float) ($row['cost'] ?? 0));
            $lineCurrency = (string) ($row['currency_code'] ?? $baseCurrency);
            $inventoryValueBase += amount_to_reference_currency($lineAmount, $lineCurrency, $rate);
            $inventoryValueSecondary += equivalent_in_bolivars($lineAmount, $lineCurrency, $rate);
        }
        $data['inventory_value_base'] = $inventoryValueBase;
        $data['inventory_value_secondary'] = $inventoryValueSecondary;
        $data['low_stock'] = (int) $db->query(
            "SELECT COUNT(*) total
             FROM products
             WHERE deleted_at IS NULL
               AND COALESCE(status, 'active') = 'active'
               AND COALESCE(product_type, 'merchandise') <> 'service'
               AND stock <= stock_min"
        )->fetch()['total'];

        return $data;
    }

    public function cashFlow(string $from, string $to): array
    {
        $db = Database::connection();

        $labels = [];
        $sales = [];
        $purchases = [];
        $expenses = [];

        $range = $this->dateRange($from, $to);
        foreach ($range as $date) {
            $labels[] = (string) date('d/m', strtotime($date));
            $sales[$date] = 0.0;
            $purchases[$date] = 0.0;
            $expenses[$date] = 0.0;
        }

        $statement = $db->prepare(
            "SELECT dt, COALESCE(SUM(total), 0) total
             FROM (
                SELECT invoice_date AS dt, total_converted AS total
                FROM invoices
                WHERE invoice_date BETWEEN ? AND ?
                  AND COALESCE(status, 'active') <> 'cancelled'

                UNION ALL

                SELECT note_date AS dt, total_converted AS total
                FROM delivery_notes
                WHERE note_date BETWEEN ? AND ?
                  AND COALESCE(status, 'active') <> 'cancelled'
             ) sales_rows
             GROUP BY dt"
        );
        $statement->execute([$from, $to, $from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) $row['dt'];
            if (array_key_exists($key, $sales)) {
                $sales[$key] = (float) $row['total'];
            }
        }

        $statement = $db->prepare(
            "SELECT purchase_date AS dt, COALESCE(SUM(total_converted), 0) total
             FROM purchases
             WHERE purchase_date BETWEEN ? AND ?
               AND COALESCE(status, 'active') <> 'cancelled'
             GROUP BY purchase_date"
        );
        $statement->execute([$from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) $row['dt'];
            if (array_key_exists($key, $purchases)) {
                $purchases[$key] = (float) $row['total'];
            }
        }

        $statement = $db->prepare(
            "SELECT expense_date AS dt, COALESCE(SUM({$this->expenseConsolidatedSql()}), 0) total
             FROM expenses
             WHERE expense_date BETWEEN ? AND ?
               AND COALESCE(status, 'active') <> 'cancelled'
             GROUP BY expense_date"
        );
        $statement->execute([$from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) $row['dt'];
            if (array_key_exists($key, $expenses)) {
                $expenses[$key] = (float) $row['total'];
            }
        }

        return [
            'labels' => array_values(array_map(fn (string $date): string => date('d/m', strtotime($date)), $range)),
            'sales' => array_values($sales),
            'purchases' => array_values($purchases),
            'expenses' => array_values($expenses),
        ];
    }

    public function composition(string $from, string $to): array
    {
        $db = Database::connection();

        $statement = $db->prepare(
            "SELECT (
                COALESCE((SELECT SUM(total_converted)
                          FROM invoices
                          WHERE invoice_date BETWEEN ? AND ?
                            AND COALESCE(status, 'active') <> 'cancelled'), 0)
                + COALESCE((SELECT SUM(total_converted)
                            FROM delivery_notes
                            WHERE note_date BETWEEN ? AND ?
                              AND COALESCE(status, 'active') <> 'cancelled'), 0)
            ) AS total"
        );
        $statement->execute([$from, $to, $from, $to]);
        $sales = (float) $statement->fetch()['total'];

        $statement = $db->prepare(
            "SELECT COALESCE(SUM(total_converted), 0) total
             FROM purchases
             WHERE purchase_date BETWEEN ? AND ?
               AND COALESCE(status, 'active') <> 'cancelled'"
        );
        $statement->execute([$from, $to]);
        $purchases = (float) $statement->fetch()['total'];

        $statement = $db->prepare(
            "SELECT COALESCE(SUM({$this->expenseConsolidatedSql()}), 0) total
             FROM expenses
             WHERE expense_date BETWEEN ? AND ?
               AND COALESCE(status, 'active') <> 'cancelled'"
        );
        $statement->execute([$from, $to]);
        $expenses = (float) $statement->fetch()['total'];

        return [
            'labels' => ['Ventas', 'Compras', 'Gastos'],
            'values' => [$sales, $purchases, $expenses],
        ];
    }

    public function topProducts(string $from, string $to, int $limit = 6): array
    {
        $statement = Database::connection()->prepare(
            "SELECT sales.product_name AS name,
                    COALESCE(SUM(sales.quantity), 0) AS quantity,
                    COALESCE(SUM(sales.total), 0) AS total
             FROM (
                SELECT ii.product_id,
                       p.name AS product_name,
                       ii.quantity,
                       ii.total_converted AS total
                FROM invoice_items ii
                INNER JOIN invoices i ON i.id = ii.invoice_id
                INNER JOIN products p ON p.id = ii.product_id
                WHERE i.invoice_date BETWEEN ? AND ?
                  AND COALESCE(i.status, 'active') <> 'cancelled'

                UNION ALL

                SELECT di.product_id,
                       p.name AS product_name,
                       di.quantity,
                       di.total_converted AS total
                FROM delivery_note_items di
                INNER JOIN delivery_notes d ON d.id = di.delivery_note_id
                INNER JOIN products p ON p.id = di.product_id
                WHERE d.note_date BETWEEN ? AND ?
                  AND COALESCE(d.status, 'active') <> 'cancelled'
             ) sales
             GROUP BY sales.product_id, sales.product_name
             ORDER BY total DESC
             LIMIT ?"
        );
        $statement->bindValue(1, $from);
        $statement->bindValue(2, $to);
        $statement->bindValue(3, $from);
        $statement->bindValue(4, $to);
        $statement->bindValue(5, $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function dateRange(string $from, string $to): array
    {
        $start = new \DateTime($from);
        $end = new \DateTime($to);
        $end->setTime(0, 0, 0);
        $current = new \DateTime($from);
        $current->setTime(0, 0, 0);

        $dates = [];
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $dates;
    }

    private function expenseConsolidatedSql(string $alias = ''): string
    {
        $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

        return "CASE
            WHEN UPPER(COALESCE({$prefix}currency_code, '')) IN ('VES', 'VEF', 'BS', 'BS.S', 'BSS', 'BOLIVARES')
                THEN COALESCE({$prefix}amount_original, 0)
            ELSE COALESCE({$prefix}amount_converted, 0)
        END";
    }
}
