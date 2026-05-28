<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class ReportsData
{
    public function sales(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            "SELECT i.*, c.name AS client_name
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE i.invoice_date BETWEEN ? AND ?
               AND COALESCE(i.status, 'active') <> 'cancelled'
             ORDER BY i.invoice_date DESC, i.id DESC"
        );
        $statement->execute([$from, $to]);

        return $statement->fetchAll();
    }

    public function salesProductQuantities(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                ii.product_id,
                p.sku,
                p.name AS product_name,
                p.unit_label,
                p.product_type,
                c.name AS category_name,
                COALESCE(SUM(ii.quantity), 0) AS total_quantity,
                COUNT(DISTINCT i.id) AS document_count
             FROM invoices i
             INNER JOIN invoice_items ii ON ii.invoice_id = i.id
             LEFT JOIN products p ON p.id = ii.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE i.invoice_date BETWEEN ? AND ?
               AND COALESCE(i.status, 'active') <> 'cancelled'
             GROUP BY
                ii.product_id,
                p.sku,
                p.name,
                p.unit_label,
                p.product_type,
                c.name
             ORDER BY total_quantity DESC, p.name ASC, ii.product_id ASC"
        );
        $statement->execute([$from, $to]);

        return $statement->fetchAll();
    }

    public function deliveryNotes(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                d.*,
                c.name AS client_name,
                c.document AS client_document,
                COALESCE(SUM(di.quantity), 0) AS total_quantity,
                COUNT(di.id) AS line_count
             FROM delivery_notes d
             LEFT JOIN clients c ON c.id = d.client_id
             LEFT JOIN delivery_note_items di ON di.delivery_note_id = d.id
             WHERE d.note_date BETWEEN ? AND ?
               AND COALESCE(d.status, 'active') <> 'cancelled'
             GROUP BY
                d.id,
                d.client_id,
                d.note_number,
                d.note_date,
                d.currency_code,
                d.exchange_rate,
                d.subtotal_original,
                d.total_original,
                d.subtotal_converted,
                d.total_converted,
                d.notes,
                d.status,
                d.cancelled_at,
                d.cancellation_reason,
                d.created_at,
                c.name,
                c.document
             ORDER BY d.note_date DESC, d.id DESC"
        );
        $statement->execute([$from, $to]);

        return $statement->fetchAll();
    }

    public function purchases(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            "SELECT p.*, s.name AS supplier_name
             FROM purchases p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.purchase_date BETWEEN ? AND ?
               AND COALESCE(p.status, 'active') <> 'cancelled'
             ORDER BY p.purchase_date DESC, p.id DESC"
        );
        $statement->execute([$from, $to]);

        return $statement->fetchAll();
    }

    public function purchaseDetails(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                p.id AS purchase_id,
                p.doc_number,
                p.purchase_date,
                p.currency_code,
                p.exchange_rate,
                p.status,
                s.name AS supplier_name,
                pi.product_id,
                pi.quantity,
                pi.cost_original,
                pi.total_original,
                pr.name AS product_name
             FROM purchases p
             INNER JOIN purchase_items pi ON pi.purchase_id = p.id
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN products pr ON pr.id = pi.product_id
             WHERE p.purchase_date BETWEEN ? AND ?
               AND COALESCE(p.status, 'active') <> 'cancelled'
             ORDER BY p.purchase_date DESC, p.id DESC, pi.id ASC"
        );
        $statement->execute([$from, $to]);

        return $statement->fetchAll();
    }

    public function expenses(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            "SELECT e.*, c.name AS category_name
             FROM expenses e
             LEFT JOIN expense_categories c ON c.id = e.category_id
             WHERE e.expense_date BETWEEN ? AND ?
               AND COALESCE(e.status, 'active') <> 'cancelled'
             ORDER BY e.expense_date DESC, e.id DESC"
        );
        $statement->execute([$from, $to]);

        return $statement->fetchAll();
    }

    public function accountsReceivable(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            "SELECT * FROM (
                SELECT
                    'invoice' AS document_type,
                    i.id,
                    i.invoice_date AS document_date,
                    i.invoice_number AS reference,
                    i.invoice_date,
                    NULL AS note_date,
                    i.due_date,
                    i.currency_code,
                    i.exchange_rate,
                    i.total_original,
                    i.amount_paid_original,
                    i.balance_original,
                    i.total_converted,
                    i.amount_paid_converted,
                    i.balance_converted,
                    i.status,
                    i.payment_status,
                    c.name AS client_name,
                    c.document AS client_document
                FROM invoices i
                LEFT JOIN clients c ON c.id = i.client_id
                WHERE i.invoice_date BETWEEN ? AND ?
                  AND COALESCE(i.status, 'active') <> 'cancelled'
                  AND COALESCE(i.balance_converted, 0) > 0

                UNION ALL

                SELECT
                    'delivery_note' AS document_type,
                    d.id,
                    d.note_date AS document_date,
                    d.note_number AS reference,
                    NULL AS invoice_date,
                    d.note_date,
                    d.due_date,
                    d.currency_code,
                    d.exchange_rate,
                    d.total_original,
                    d.amount_paid_original,
                    d.balance_original,
                    d.total_converted,
                    d.amount_paid_converted,
                    d.balance_converted,
                    d.status,
                    d.payment_status,
                    c.name AS client_name,
                    c.document AS client_document
                FROM delivery_notes d
                LEFT JOIN clients c ON c.id = d.client_id
                WHERE d.note_date BETWEEN ? AND ?
                  AND COALESCE(d.status, 'active') <> 'cancelled'
                  AND COALESCE(d.balance_converted, 0) > 0
            ) receivables_rows
             ORDER BY due_date ASC, document_date ASC, id ASC"
        );
        $statement->execute([$from, $to, $from, $to]);
        $rows = $statement->fetchAll();

        return array_map(fn (array $row): array => [
            ...$row,
            'invoice_number' => (string) ($row['reference'] ?? ''),
            'days_overdue' => $this->daysOverdue((string) ($row['due_date'] ?? $row['document_date'] ?? '')),
            'payment_status_effective' => $this->effectivePaymentStatus($row),
        ], $rows);
    }

    public function accountsPayable(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                p.*,
                s.name AS supplier_name,
                s.document AS supplier_document
             FROM purchases p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.purchase_date BETWEEN ? AND ?
               AND COALESCE(p.status, 'active') <> 'cancelled'
               AND COALESCE(p.balance_converted, 0) > 0
             ORDER BY p.due_date ASC, p.purchase_date ASC, p.id ASC"
        );
        $statement->execute([$from, $to]);
        $rows = $statement->fetchAll();

        return array_map(fn (array $row): array => [
            ...$row,
            'days_overdue' => $this->daysOverdue((string) ($row['due_date'] ?? $row['purchase_date'] ?? '')),
            'payment_status_effective' => $this->effectivePaymentStatus($row),
        ], $rows);
    }

    public function inventoryValued(): array
    {
        return Database::connection()->query(
            'SELECT p.*, c.name AS category_name, (p.stock * p.cost) AS inventory_total
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.deleted_at IS NULL
               AND COALESCE(p.product_type, \'merchandise\') <> \'service\'
             ORDER BY p.name'
        )->fetchAll();
    }

    public function inventoryMovements(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            'SELECT m.*, p.name AS product_name, c.name AS category_name, w.name AS warehouse_name
             FROM inventory_movements m
             LEFT JOIN products p ON p.id = m.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN warehouses w ON w.id = m.warehouse_id
             WHERE DATE(m.created_at) BETWEEN ? AND ?
             ORDER BY m.created_at DESC, m.id DESC'
        );
        $statement->execute([$from, $to]);

        return $statement->fetchAll();
    }

    public function treasury(string $from, string $to, float $currentRate): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                a.id,
                a.account_code,
                a.account_name,
                a.method_type,
                a.currency_code,
                a.opening_balance,
                COALESCE(SUM(CASE
                    WHEN m.is_reversed = 0 AND m.movement_date < ? AND m.direction = 'in' THEN m.amount_original
                    WHEN m.is_reversed = 0 AND m.movement_date < ? AND m.direction = 'out' THEN -m.amount_original
                    ELSE 0
                END), 0) AS previous_movement_balance_original,
                COALESCE(SUM(CASE
                    WHEN m.is_reversed = 0 AND m.movement_date <= ? AND m.direction = 'in' THEN m.amount_original
                    WHEN m.is_reversed = 0 AND m.movement_date <= ? AND m.direction = 'out' THEN -m.amount_original
                    ELSE 0
                END), 0) AS movement_balance_original,
                COALESCE(SUM(CASE
                    WHEN m.is_reversed = 0 AND m.movement_date BETWEEN ? AND ? AND m.direction = 'in' THEN m.amount_original
                    ELSE 0
                END), 0) AS period_in_original,
                COALESCE(SUM(CASE
                    WHEN m.is_reversed = 0 AND m.movement_date BETWEEN ? AND ? AND m.direction = 'out' THEN m.amount_original
                    ELSE 0
                END), 0) AS period_out_original
             FROM cash_accounts a
             LEFT JOIN cash_movements m ON m.cash_account_id = a.id
             WHERE a.is_active = 1
             GROUP BY
                a.id,
                a.account_code,
                a.account_name,
                a.method_type,
                a.currency_code,
                a.opening_balance
             ORDER BY a.currency_code ASC, a.account_name ASC, a.id ASC"
        );
        $statement->execute([$from, $from, $to, $to, $from, $to, $from, $to]);

        return array_map(function (array $row) use ($currentRate): array {
            $balanceOriginal = (float) ($row['opening_balance'] ?? 0) + (float) ($row['movement_balance_original'] ?? 0);
            $previousBalanceOriginal = (float) ($row['opening_balance'] ?? 0) + (float) ($row['previous_movement_balance_original'] ?? 0);
            $currency = (string) ($row['currency_code'] ?? '');

            return [
                ...$row,
                'balance_original' => $balanceOriginal,
                'previous_balance_original' => $previousBalanceOriginal,
                'period_net_original' => (float) ($row['period_in_original'] ?? 0) - (float) ($row['period_out_original'] ?? 0),
                'balance_reporting' => amount_to_reporting_currency($balanceOriginal, $currency, $currentRate),
                'balance_reference' => amount_to_reference_currency($balanceOriginal, $currency, $currentRate),
                'period_in_reporting' => amount_to_reporting_currency((float) ($row['period_in_original'] ?? 0), $currency, $currentRate),
                'period_out_reporting' => amount_to_reporting_currency((float) ($row['period_out_original'] ?? 0), $currency, $currentRate),
            ];
        }, $statement->fetchAll());
    }

    public function treasuryMovements(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                m.*,
                a.account_code,
                a.account_name,
                a.method_type,
                a.currency_code AS account_currency_code
             FROM cash_movements m
             INNER JOIN cash_accounts a ON a.id = m.cash_account_id
             WHERE m.movement_date BETWEEN ? AND ?
             ORDER BY m.cash_account_id ASC, m.movement_date DESC, m.id DESC"
        );
        $statement->execute([$from, $to]);

        $grouped = [];
        foreach ($statement->fetchAll() as $movement) {
            $grouped[(int) ($movement['cash_account_id'] ?? 0)][] = $movement;
        }

        return $grouped;
    }

    public function journal(string $from, string $to): array
    {
        $rows = [];
        $push = static function (array $row) use (&$rows): void {
            $rows[] = $row;
        };

        foreach ($this->sales($from, $to) as $invoice) {
            $push([
                'trans_date' => (string) ($invoice['invoice_date'] ?? ''),
                'source' => 'Factura',
                'reference' => (string) ($invoice['invoice_number'] ?? ''),
                'counterparty' => (string) ($invoice['client_name'] ?? ''),
                'account_code' => '1101',
                'account_name' => 'Cuentas por cobrar clientes',
                'currency_code' => (string) ($invoice['currency_code'] ?? ''),
                'exchange_rate' => (float) ($invoice['exchange_rate'] ?? 0),
                'original_amount' => (float) ($invoice['total_original'] ?? 0),
                'debit' => (float) ($invoice['total_converted'] ?? 0),
                'credit' => 0.0,
            ]);
            $push([
                'trans_date' => (string) ($invoice['invoice_date'] ?? ''),
                'source' => 'Factura',
                'reference' => (string) ($invoice['invoice_number'] ?? ''),
                'counterparty' => (string) ($invoice['client_name'] ?? ''),
                'account_code' => '4101',
                'account_name' => 'Ventas facturadas',
                'currency_code' => (string) ($invoice['currency_code'] ?? ''),
                'exchange_rate' => (float) ($invoice['exchange_rate'] ?? 0),
                'original_amount' => (float) ($invoice['subtotal_original'] ?? 0),
                'debit' => 0.0,
                'credit' => (float) ($invoice['subtotal_converted'] ?? 0),
            ]);

            if ((float) ($invoice['tax_converted'] ?? 0) > 0.01) {
                $push([
                    'trans_date' => (string) ($invoice['invoice_date'] ?? ''),
                    'source' => 'Factura',
                    'reference' => (string) ($invoice['invoice_number'] ?? ''),
                    'counterparty' => (string) ($invoice['client_name'] ?? ''),
                    'account_code' => '2102',
                    'account_name' => 'IVA debito fiscal',
                    'currency_code' => (string) ($invoice['currency_code'] ?? ''),
                    'exchange_rate' => (float) ($invoice['exchange_rate'] ?? 0),
                    'original_amount' => (float) ($invoice['tax_original'] ?? 0),
                    'debit' => 0.0,
                    'credit' => (float) ($invoice['tax_converted'] ?? 0),
                ]);
            }
        }

        foreach ($this->deliveryNotes($from, $to) as $note) {
            $push([
                'trans_date' => (string) ($note['note_date'] ?? ''),
                'source' => 'Nota de entrega',
                'reference' => (string) ($note['note_number'] ?? ''),
                'counterparty' => (string) ($note['client_name'] ?? ''),
                'account_code' => '1101',
                'account_name' => 'Cuentas por cobrar clientes',
                'currency_code' => (string) ($note['currency_code'] ?? ''),
                'exchange_rate' => (float) ($note['exchange_rate'] ?? 0),
                'original_amount' => (float) ($note['total_original'] ?? 0),
                'debit' => (float) ($note['total_converted'] ?? 0),
                'credit' => 0.0,
            ]);
            $push([
                'trans_date' => (string) ($note['note_date'] ?? ''),
                'source' => 'Nota de entrega',
                'reference' => (string) ($note['note_number'] ?? ''),
                'counterparty' => (string) ($note['client_name'] ?? ''),
                'account_code' => '4102',
                'account_name' => 'Ventas operativas por entrega',
                'currency_code' => (string) ($note['currency_code'] ?? ''),
                'exchange_rate' => (float) ($note['exchange_rate'] ?? 0),
                'original_amount' => (float) ($note['total_original'] ?? 0),
                'debit' => 0.0,
                'credit' => (float) ($note['total_converted'] ?? 0),
            ]);
        }

        $paymentQueries = [
            [
                'sql' => 'SELECT ip.*, i.invoice_number AS doc_reference, c.name AS party_name, a.account_code, a.account_name
                          FROM invoice_payments ip
                          INNER JOIN invoices i ON i.id = ip.invoice_id
                          LEFT JOIN clients c ON c.id = i.client_id
                          LEFT JOIN cash_accounts a ON a.id = ip.treasury_account_id
                          WHERE ip.payment_date BETWEEN ? AND ?
                            AND COALESCE(i.status, \'active\') <> \'cancelled\'',
                'source' => 'Cobro',
                'receivable_account' => ['1101', 'Cuentas por cobrar clientes'],
                'fx_account' => '4791',
                'fx_name' => 'Resultado cambiario por cobranza',
                'cash_direction' => 'debit',
            ],
            [
                'sql' => 'SELECT dnp.*, d.note_number AS doc_reference, c.name AS party_name, a.account_code, a.account_name
                          FROM delivery_note_payments dnp
                          INNER JOIN delivery_notes d ON d.id = dnp.delivery_note_id
                          LEFT JOIN clients c ON c.id = d.client_id
                          LEFT JOIN cash_accounts a ON a.id = dnp.treasury_account_id
                          WHERE dnp.payment_date BETWEEN ? AND ?
                            AND COALESCE(d.status, \'active\') <> \'cancelled\'',
                'source' => 'Cobro nota',
                'receivable_account' => ['1101', 'Cuentas por cobrar clientes'],
                'fx_account' => '4791',
                'fx_name' => 'Resultado cambiario por cobranza',
                'cash_direction' => 'debit',
            ],
            [
                'sql' => 'SELECT pp.*, p.doc_number AS doc_reference, s.name AS party_name, a.account_code, a.account_name
                          FROM purchase_payments pp
                          INNER JOIN purchases p ON p.id = pp.purchase_id
                          LEFT JOIN suppliers s ON s.id = p.supplier_id
                          LEFT JOIN cash_accounts a ON a.id = pp.treasury_account_id
                          WHERE pp.payment_date BETWEEN ? AND ?
                            AND COALESCE(p.status, \'active\') <> \'cancelled\'',
                'source' => 'Pago compra',
                'receivable_account' => ['2101', 'Cuentas por pagar proveedores'],
                'fx_account' => '4792',
                'fx_name' => 'Resultado cambiario por pago',
                'cash_direction' => 'credit',
            ],
        ];

        foreach ($paymentQueries as $meta) {
            $statement = Database::connection()->prepare($meta['sql']);
            $statement->execute([$from, $to]);

            foreach ($statement->fetchAll() as $payment) {
                $cashAccountCode = (string) ($payment['account_code'] ?? '1011');
                $cashAccountName = (string) ($payment['account_name'] ?? 'Tesoreria');
                $amountConverted = (float) ($payment['amount_converted'] ?? 0);
                $appliedConverted = (float) ($payment['applied_converted'] ?? 0);
                $difference = $amountConverted - $appliedConverted;

                $push([
                    'trans_date' => (string) ($payment['payment_date'] ?? ''),
                    'source' => $meta['source'],
                    'reference' => (string) ($payment['reference'] ?? $payment['doc_reference'] ?? ''),
                    'counterparty' => (string) ($payment['party_name'] ?? ''),
                    'account_code' => $cashAccountCode,
                    'account_name' => $cashAccountName,
                    'currency_code' => (string) ($payment['currency_code'] ?? ''),
                    'exchange_rate' => (float) ($payment['exchange_rate'] ?? 0),
                    'original_amount' => (float) ($payment['amount_original'] ?? 0),
                    'debit' => $meta['cash_direction'] === 'debit' ? $amountConverted : 0.0,
                    'credit' => $meta['cash_direction'] === 'credit' ? $amountConverted : 0.0,
                ]);

                if (abs($difference) > 0.01) {
                    $push([
                        'trans_date' => (string) ($payment['payment_date'] ?? ''),
                        'source' => $meta['source'],
                        'reference' => (string) ($payment['reference'] ?? $payment['doc_reference'] ?? ''),
                        'counterparty' => (string) ($payment['party_name'] ?? ''),
                        'account_code' => $meta['fx_account'],
                        'account_name' => $meta['fx_name'],
                        'currency_code' => (string) ($payment['currency_code'] ?? ''),
                        'exchange_rate' => (float) ($payment['exchange_rate'] ?? 0),
                        'original_amount' => abs($difference),
                        'debit' => $meta['cash_direction'] === 'debit'
                            ? ($difference < 0 ? abs($difference) : 0.0)
                            : ($difference > 0 ? abs($difference) : 0.0),
                        'credit' => $meta['cash_direction'] === 'debit'
                            ? ($difference > 0 ? abs($difference) : 0.0)
                            : ($difference < 0 ? abs($difference) : 0.0),
                    ]);
                }

                $push([
                    'trans_date' => (string) ($payment['payment_date'] ?? ''),
                    'source' => $meta['source'],
                    'reference' => (string) ($payment['reference'] ?? $payment['doc_reference'] ?? ''),
                    'counterparty' => (string) ($payment['party_name'] ?? ''),
                    'account_code' => $meta['receivable_account'][0],
                    'account_name' => $meta['receivable_account'][1],
                    'currency_code' => (string) ($payment['currency_code'] ?? ''),
                    'exchange_rate' => (float) ($payment['exchange_rate'] ?? 0),
                    'original_amount' => (float) ($payment['applied_original'] ?? 0),
                    'debit' => $meta['cash_direction'] === 'debit' ? 0.0 : $appliedConverted,
                    'credit' => $meta['cash_direction'] === 'debit' ? $appliedConverted : 0.0,
                ]);
            }
        }

        foreach ($this->purchases($from, $to) as $purchase) {
            $push([
                'trans_date' => (string) ($purchase['purchase_date'] ?? ''),
                'source' => 'Compra',
                'reference' => (string) ($purchase['doc_number'] ?? ''),
                'counterparty' => (string) ($purchase['supplier_name'] ?? ''),
                'account_code' => '1201',
                'account_name' => 'Inventario y abastecimiento',
                'currency_code' => (string) ($purchase['currency_code'] ?? ''),
                'exchange_rate' => (float) ($purchase['exchange_rate'] ?? 0),
                'original_amount' => (float) ($purchase['total_original'] ?? 0),
                'debit' => (float) ($purchase['total_converted'] ?? 0),
                'credit' => 0.0,
            ]);
            $push([
                'trans_date' => (string) ($purchase['purchase_date'] ?? ''),
                'source' => 'Compra',
                'reference' => (string) ($purchase['doc_number'] ?? ''),
                'counterparty' => (string) ($purchase['supplier_name'] ?? ''),
                'account_code' => '2101',
                'account_name' => 'Cuentas por pagar proveedores',
                'currency_code' => (string) ($purchase['currency_code'] ?? ''),
                'exchange_rate' => (float) ($purchase['exchange_rate'] ?? 0),
                'original_amount' => (float) ($purchase['total_original'] ?? 0),
                'debit' => 0.0,
                'credit' => (float) ($purchase['total_converted'] ?? 0),
            ]);
        }

        $expenseStatement = Database::connection()->prepare(
            "SELECT e.*, ec.name AS category_name, a.account_code, a.account_name
             FROM expenses e
             LEFT JOIN expense_categories ec ON ec.id = e.category_id
             LEFT JOIN cash_accounts a ON a.id = e.treasury_account_id
             WHERE e.expense_date BETWEEN ? AND ?
               AND COALESCE(e.status, 'active') <> 'cancelled'"
        );
        $expenseStatement->execute([$from, $to]);

        foreach ($expenseStatement->fetchAll() as $expense) {
            $expenseConverted = $this->expenseConsolidatedAmount($expense);
            $expenseCurrency = normalize_currency_code((string) ($expense['currency_code'] ?? ''));
            $push([
                'trans_date' => (string) ($expense['expense_date'] ?? ''),
                'source' => 'Gasto',
                'reference' => (string) ($expense['reference'] ?? ''),
                'counterparty' => (string) ($expense['category_name'] ?? 'Sin categoria'),
                'account_code' => '5101',
                'account_name' => 'Gasto operativo',
                'currency_code' => $expenseCurrency,
                'exchange_rate' => (float) ($expense['exchange_rate'] ?? 0),
                'original_amount' => (float) ($expense['amount_original'] ?? 0),
                'debit' => $expenseConverted,
                'credit' => 0.0,
            ]);
            $push([
                'trans_date' => (string) ($expense['expense_date'] ?? ''),
                'source' => 'Gasto',
                'reference' => (string) ($expense['reference'] ?? ''),
                'counterparty' => (string) ($expense['category_name'] ?? 'Sin categoria'),
                'account_code' => (string) ($expense['account_code'] ?? '1011'),
                'account_name' => (string) ($expense['account_name'] ?? 'Tesoreria'),
                'currency_code' => $expenseCurrency,
                'exchange_rate' => (float) ($expense['exchange_rate'] ?? 0),
                'original_amount' => (float) ($expense['amount_original'] ?? 0),
                'debit' => 0.0,
                'credit' => $expenseConverted,
            ]);
        }

        usort($rows, static function (array $left, array $right): int {
            return [
                (string) ($left['trans_date'] ?? ''),
                (string) ($left['source'] ?? ''),
                (string) ($left['reference'] ?? ''),
                (string) ($left['account_code'] ?? ''),
            ] <=> [
                (string) ($right['trans_date'] ?? ''),
                (string) ($right['source'] ?? ''),
                (string) ($right['reference'] ?? ''),
                (string) ($right['account_code'] ?? ''),
            ];
        });

        return $rows;
    }

    public function ledger(string $from, string $to): array
    {
        $rows = $this->journal($from, $to);
        $accounts = [];

        foreach ($rows as $row) {
            $key = (string) ($row['account_code'] ?? '') . '|' . (string) ($row['account_name'] ?? '');

            if (!isset($accounts[$key])) {
                $accounts[$key] = [
                    'account_code' => (string) ($row['account_code'] ?? ''),
                    'account_name' => (string) ($row['account_name'] ?? ''),
                    'entry_count' => 0,
                    'last_date' => (string) ($row['trans_date'] ?? ''),
                    'debit' => 0.0,
                    'credit' => 0.0,
                    'balance' => 0.0,
                ];
            }

            $accounts[$key]['entry_count']++;
            $accounts[$key]['last_date'] = max((string) $accounts[$key]['last_date'], (string) ($row['trans_date'] ?? ''));
            $accounts[$key]['debit'] += (float) ($row['debit'] ?? 0);
            $accounts[$key]['credit'] += (float) ($row['credit'] ?? 0);
            $accounts[$key]['balance'] = $accounts[$key]['debit'] - $accounts[$key]['credit'];
        }

        usort($accounts, static function (array $left, array $right): int {
            return [$left['account_code'], $left['account_name']] <=> [$right['account_code'], $right['account_name']];
        });

        return $accounts;
    }

    public function balanceSheet(string $from, string $to): array
    {
        $db = Database::connection();
        $treasuryBalances = array_filter(
            $this->treasury($from, $to, system_exchange_rate($to)),
            static fn (array $row): bool => abs((float) ($row['balance_reporting'] ?? 0)) > 0.01
        );
        $openInvoiceReceivables = $this->sumByCutoff($db, 'invoices', 'invoice_date', 'balance_converted', $to);
        $openDeliveryReceivables = $this->sumByCutoff($db, 'delivery_notes', 'note_date', 'balance_converted', $to);
        $openPayables = $this->sumByCutoff($db, 'purchases', 'purchase_date', 'balance_converted', $to);
        $salesNet = $this->sumByRange($db, 'invoices', 'invoice_date', 'subtotal_converted', $from, $to)
            + $this->sumByRange($db, 'delivery_notes', 'note_date', 'total_converted', $from, $to);
        $salesTax = $this->sumByRange($db, 'invoices', 'invoice_date', 'tax_converted', $from, $to);
        $purchasesTotal = $this->sumByRange($db, 'purchases', 'purchase_date', 'total_converted', $from, $to);
        $expenseTotal = $this->sumExpensesByRange($db, $from, $to);
        $cashAvailable = array_reduce(
            $treasuryBalances,
            static fn (float $carry, array $row): float => $carry + (float) ($row['balance_reporting'] ?? 0),
            0.0
        );
        $inventoryValue = (float) $db->query(
            'SELECT COALESCE(SUM(stock * cost), 0) total
             FROM products
             WHERE deleted_at IS NULL
               AND COALESCE(product_type, \'merchandise\') <> \'service\''
        )->fetch()['total'];

        $assets = array_map(static fn (array $row): array => [
            'name' => 'Tesoreria: ' . (string) ($row['account_name'] ?? ''),
            'amount' => (float) ($row['balance_reporting'] ?? 0),
        ], $treasuryBalances);
        $assets[] = ['name' => 'Cuentas por cobrar facturas', 'amount' => $openInvoiceReceivables];
        $assets[] = ['name' => 'Cuentas por cobrar notas de entrega', 'amount' => $openDeliveryReceivables];
        $assets[] = ['name' => 'Inventario actual valorizado', 'amount' => $inventoryValue];

        $liabilities = [
            ['name' => 'Cuentas por pagar abiertas', 'amount' => $openPayables],
        ];

        if ($salesTax > 0) {
            $liabilities[] = ['name' => 'IVA debito fiscal del periodo', 'amount' => $salesTax];
        }

        $assetsTotal = $this->sumAmounts($assets);
        $liabilitiesTotal = $this->sumAmounts($liabilities);
        $estimatedResult = $salesNet - $purchasesTotal - $expenseTotal;
        $equityTarget = $assetsTotal - $liabilitiesTotal;
        $inventoryAdjustment = $equityTarget - $estimatedResult;

        $equity = [
            ['name' => 'Resultado operativo del periodo', 'amount' => $estimatedResult],
        ];

        if (abs($inventoryAdjustment) >= 0.01) {
            $equity[] = ['name' => 'Ajuste por inventario y costo no historizado', 'amount' => $inventoryAdjustment];
        }

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'overview' => [
                'sales' => $salesNet,
                'purchases' => $purchasesTotal,
                'expenses' => $expenseTotal,
                'outflows' => $purchasesTotal + $expenseTotal,
                'estimated_result' => $estimatedResult,
                'cash_available' => $cashAvailable,
                'receivables' => $openInvoiceReceivables + $openDeliveryReceivables,
                'payables' => $openPayables,
                'inventory_value' => $inventoryValue,
            ],
        ];
    }

    public function effectivePaymentStatus(array $document): string
    {
        if (($document['status'] ?? 'active') === 'cancelled') {
            return 'cancelled';
        }

        $balance = (float) ($document['balance_converted'] ?? 0);
        $paid = (float) ($document['amount_paid_converted'] ?? 0);
        $dueDate = (string) ($document['due_date'] ?? $document['invoice_date'] ?? $document['purchase_date'] ?? '');

        if ($balance <= 0.01) {
            return 'paid';
        }

        if ($dueDate !== '' && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
            return $paid > 0 ? 'partial_overdue' : 'overdue';
        }

        return $paid > 0 ? 'partial' : 'pending';
    }

    private function daysOverdue(string $dueDate): int
    {
        if ($dueDate === '' || strtotime($dueDate) >= strtotime(date('Y-m-d'))) {
            return 0;
        }

        return (int) floor((strtotime(date('Y-m-d')) - strtotime($dueDate)) / 86400);
    }

    private function sumPayments(\PDO $db, string $table, string $dateColumn, string $amountColumn, string $to): float
    {
        $statement = $db->prepare(
            "SELECT COALESCE(SUM({$amountColumn}), 0) total
             FROM {$table}
             WHERE {$dateColumn} <= ?"
        );
        $statement->execute([$to]);

        return (float) ($statement->fetch()['total'] ?? 0);
    }

    private function sumByCutoff(
        \PDO $db,
        string $table,
        string $dateColumn,
        string $amountColumn,
        string $to
    ): float {
        $statement = $db->prepare(
            "SELECT COALESCE(SUM({$amountColumn}), 0) total
             FROM {$table}
             WHERE {$dateColumn} <= ?
               AND COALESCE(status, 'active') <> 'cancelled'"
        );
        $statement->execute([$to]);

        return (float) ($statement->fetch()['total'] ?? 0);
    }

    private function sumByRange(
        \PDO $db,
        string $table,
        string $dateColumn,
        string $amountColumn,
        string $from,
        string $to
    ): float {
        $statement = $db->prepare(
            "SELECT COALESCE(SUM({$amountColumn}), 0) total
             FROM {$table}
             WHERE {$dateColumn} BETWEEN ? AND ?
               AND COALESCE(status, 'active') <> 'cancelled'"
        );
        $statement->execute([$from, $to]);

        return (float) ($statement->fetch()['total'] ?? 0);
    }

    private function sumExpensesByRange(\PDO $db, string $from, string $to): float
    {
        $statement = $db->prepare(
            "SELECT COALESCE(SUM(
                CASE
                    WHEN LOWER(COALESCE(payment_method, '')) IN ('point_of_sale', 'bank_transfer', 'mobile_payment')
                        THEN COALESCE(amount_original, 0)
                    ELSE COALESCE(amount_converted, 0)
                END
             ), 0) total
             FROM expenses
             WHERE expense_date BETWEEN ? AND ?
               AND COALESCE(status, 'active') <> 'cancelled'"
        );
        $statement->execute([$from, $to]);

        return (float) ($statement->fetch()['total'] ?? 0);
    }

    private function expenseConsolidatedAmount(array $expense): float
    {
        $currency = normalize_currency_code((string) ($expense['currency_code'] ?? ''));

        return is_bolivar_currency($currency)
            ? (float) ($expense['amount_original'] ?? 0)
            : (float) ($expense['amount_converted'] ?? 0);
    }

    private function sumAmounts(array $rows): float
    {
        return array_reduce(
            $rows,
            static fn (float $carry, array $row): float => $carry + (float) ($row['amount'] ?? 0),
            0.0
        );
    }
}
