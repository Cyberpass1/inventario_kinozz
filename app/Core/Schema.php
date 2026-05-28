<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

class Schema
{
    private static bool $checked = false;

    public static function ensure(PDO $db): void
    {
        if (self::$checked) {
            return;
        }

        self::$checked = true;

        $updates = [
            ['inventory_movements', 'warehouse_id', 'ALTER TABLE inventory_movements MODIFY warehouse_id INT NULL'],
            ['purchases', 'warehouse_id', 'ALTER TABLE purchases MODIFY warehouse_id INT NULL'],
            ['invoice_items', 'warehouse_id', 'ALTER TABLE invoice_items MODIFY warehouse_id INT NULL'],
            ['delivery_note_items', 'warehouse_id', 'ALTER TABLE delivery_note_items MODIFY warehouse_id INT NULL'],
        ];

        foreach ($updates as [$table, $column, $sql]) {
            if (!self::columnExists($db, $table, $column)) {
                continue;
            }

            $db->exec($sql);
        }

        self::ensureDocumentStatus($db, 'invoices');
        self::ensureDocumentStatus($db, 'purchases');
        self::ensureDocumentStatus($db, 'expenses');
        self::ensureDocumentStatus($db, 'delivery_notes');
        self::ensureProductLifecycle($db);
        self::ensureProduction($db);
        self::ensureDeliveryNoteCommercials($db);
        self::ensureReceivablesAndPayables($db);
        self::ensureRateSettings($db);
        self::ensureUsersManagement($db);
        self::ensureSuppliersManagement($db);
        self::ensureTreasury($db);
    }

    private static function ensureDocumentStatus(PDO $db, string $table): void
    {
        if (!self::columnExists($db, $table, 'status')) {
            $db->exec("ALTER TABLE {$table} ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
        }

        if (!self::columnExists($db, $table, 'cancelled_at')) {
            $db->exec("ALTER TABLE {$table} ADD COLUMN cancelled_at DATETIME NULL");
        }

        if (!self::columnExists($db, $table, 'cancellation_reason')) {
            $db->exec("ALTER TABLE {$table} ADD COLUMN cancellation_reason TEXT NULL");
        }
    }

    private static function ensureProductLifecycle(PDO $db): void
    {
        if (!self::columnExists($db, 'products', 'status')) {
            $db->exec("ALTER TABLE products ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
        }

        if (!self::columnExists($db, 'products', 'deleted_at')) {
            $db->exec('ALTER TABLE products ADD COLUMN deleted_at DATETIME NULL');
        }

        if (!self::columnExists($db, 'products', 'product_type')) {
            $db->exec("ALTER TABLE products ADD COLUMN product_type VARCHAR(30) NOT NULL DEFAULT 'merchandise' AFTER description");
        }

        if (!self::columnExists($db, 'products', 'unit_label')) {
            $db->exec("ALTER TABLE products ADD COLUMN unit_label VARCHAR(20) NOT NULL DEFAULT 'und' AFTER product_type");
        }

        $db->exec(
            "UPDATE products
             SET product_type = 'merchandise'
             WHERE COALESCE(TRIM(product_type), '') = ''"
        );

        $db->exec(
            "UPDATE products
             SET unit_label = CASE
                WHEN COALESCE(TRIM(product_type), 'merchandise') = 'service' THEN 'serv'
                ELSE 'und'
             END
             WHERE COALESCE(TRIM(unit_label), '') = ''"
        );

        $db->exec(
            "UPDATE products
             SET sku = CONCAT(
                LEFT(
                    TRIM(BOTH '-' FROM REGEXP_REPLACE(
                        UPPER(COALESCE(NULLIF(TRIM(sku), ''), 'SKU')),
                        '[^A-Z0-9-]+',
                        '-'
                    )),
                    80 - CHAR_LENGTH(CONCAT('--DEL-', id))
                ),
                '--DEL-',
                id
             )
             WHERE deleted_at IS NOT NULL
               AND sku NOT REGEXP '--DEL-[0-9]+$'"
        );
    }

    private static function ensureProduction(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS product_recipe_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                component_product_id INT NOT NULL,
                quantity DECIMAL(14,4) NOT NULL,
                notes VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_recipe_component (product_id, component_product_id),
                CONSTRAINT fk_recipe_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                CONSTRAINT fk_recipe_component_product FOREIGN KEY (component_product_id) REFERENCES products(id) ON DELETE CASCADE
            )"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS production_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                reference VARCHAR(80) NOT NULL,
                production_date DATE NOT NULL,
                quantity_produced DECIMAL(14,2) NOT NULL,
                unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
                total_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_production_order_product FOREIGN KEY (product_id) REFERENCES products(id)
            )"
        );

        foreach ([
            ['status', "ALTER TABLE production_orders ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER notes"],
            ['cancelled_at', 'ALTER TABLE production_orders ADD COLUMN cancelled_at DATETIME NULL AFTER status'],
            ['cancellation_reason', 'ALTER TABLE production_orders ADD COLUMN cancellation_reason TEXT NULL AFTER cancelled_at'],
        ] as [$column, $sql]) {
            if (! self::columnExists($db, 'production_orders', $column)) {
                $db->exec($sql);
            }
        }

        $db->exec(
            "UPDATE production_orders
             SET status = 'active'
             WHERE COALESCE(TRIM(status), '') = ''"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS production_order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                production_order_id INT NOT NULL,
                component_product_id INT NOT NULL,
                quantity_per_unit DECIMAL(14,4) NOT NULL,
                quantity_consumed DECIMAL(14,4) NOT NULL,
                unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
                total_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_production_item_order FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
                CONSTRAINT fk_production_item_component FOREIGN KEY (component_product_id) REFERENCES products(id)
            )"
        );
    }

    private static function ensureDeliveryNoteCommercials(PDO $db): void
    {
        $headerColumns = [
            ['currency_code', "ALTER TABLE delivery_notes ADD COLUMN currency_code VARCHAR(10) NOT NULL DEFAULT 'USD'"],
            ['exchange_rate', 'ALTER TABLE delivery_notes ADD COLUMN exchange_rate DECIMAL(14,4) NOT NULL DEFAULT 1'],
            ['subtotal_original', 'ALTER TABLE delivery_notes ADD COLUMN subtotal_original DECIMAL(14,2) NOT NULL DEFAULT 0'],
            ['total_original', 'ALTER TABLE delivery_notes ADD COLUMN total_original DECIMAL(14,2) NOT NULL DEFAULT 0'],
            ['subtotal_converted', 'ALTER TABLE delivery_notes ADD COLUMN subtotal_converted DECIMAL(14,2) NOT NULL DEFAULT 0'],
            ['total_converted', 'ALTER TABLE delivery_notes ADD COLUMN total_converted DECIMAL(14,2) NOT NULL DEFAULT 0'],
        ];

        foreach ($headerColumns as [$column, $sql]) {
            if (!self::columnExists($db, 'delivery_notes', $column)) {
                $db->exec($sql);
            }
        }

        $itemColumns = [
            ['price_original', 'ALTER TABLE delivery_note_items ADD COLUMN price_original DECIMAL(14,2) NOT NULL DEFAULT 0'],
            ['price_converted', 'ALTER TABLE delivery_note_items ADD COLUMN price_converted DECIMAL(14,2) NOT NULL DEFAULT 0'],
            ['total_original', 'ALTER TABLE delivery_note_items ADD COLUMN total_original DECIMAL(14,2) NOT NULL DEFAULT 0'],
            ['total_converted', 'ALTER TABLE delivery_note_items ADD COLUMN total_converted DECIMAL(14,2) NOT NULL DEFAULT 0'],
        ];

        foreach ($itemColumns as [$column, $sql]) {
            if (!self::columnExists($db, 'delivery_note_items', $column)) {
                $db->exec($sql);
            }
        }
    }

    private static function ensureReceivablesAndPayables(PDO $db): void
    {
        $invoiceColumns = [
            ['due_date', 'ALTER TABLE invoices ADD COLUMN due_date DATE NULL AFTER invoice_date'],
            ['amount_paid_original', 'ALTER TABLE invoices ADD COLUMN amount_paid_original DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER total_original'],
            ['amount_paid_converted', 'ALTER TABLE invoices ADD COLUMN amount_paid_converted DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER total_converted'],
            ['balance_original', 'ALTER TABLE invoices ADD COLUMN balance_original DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER amount_paid_original'],
            ['balance_converted', 'ALTER TABLE invoices ADD COLUMN balance_converted DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER amount_paid_converted'],
            ['payment_status', "ALTER TABLE invoices ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER status"],
        ];

        foreach ($invoiceColumns as [$column, $sql]) {
            if (!self::columnExists($db, 'invoices', $column)) {
                $db->exec($sql);
            }
        }

        $purchaseColumns = [
            ['due_date', 'ALTER TABLE purchases ADD COLUMN due_date DATE NULL AFTER purchase_date'],
            ['amount_paid_original', 'ALTER TABLE purchases ADD COLUMN amount_paid_original DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER total_original'],
            ['amount_paid_converted', 'ALTER TABLE purchases ADD COLUMN amount_paid_converted DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER total_converted'],
            ['balance_original', 'ALTER TABLE purchases ADD COLUMN balance_original DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER amount_paid_original'],
            ['balance_converted', 'ALTER TABLE purchases ADD COLUMN balance_converted DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER amount_paid_converted'],
            ['payment_status', "ALTER TABLE purchases ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER status"],
        ];

        foreach ($purchaseColumns as [$column, $sql]) {
            if (!self::columnExists($db, 'purchases', $column)) {
                $db->exec($sql);
            }
        }

        $deliveryNoteColumns = [
            ['due_date', 'ALTER TABLE delivery_notes ADD COLUMN due_date DATE NULL AFTER note_date'],
            ['amount_paid_original', 'ALTER TABLE delivery_notes ADD COLUMN amount_paid_original DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER total_original'],
            ['amount_paid_converted', 'ALTER TABLE delivery_notes ADD COLUMN amount_paid_converted DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER total_converted'],
            ['balance_original', 'ALTER TABLE delivery_notes ADD COLUMN balance_original DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER amount_paid_original'],
            ['balance_converted', 'ALTER TABLE delivery_notes ADD COLUMN balance_converted DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER amount_paid_converted'],
            ['payment_status', "ALTER TABLE delivery_notes ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER status"],
        ];

        foreach ($deliveryNoteColumns as [$column, $sql]) {
            if (!self::columnExists($db, 'delivery_notes', $column)) {
                $db->exec($sql);
            }
        }

        $db->exec(
            'CREATE TABLE IF NOT EXISTS invoice_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT NOT NULL,
                payment_date DATE NOT NULL,
                reference VARCHAR(120) NOT NULL,
                payment_method VARCHAR(40) NOT NULL DEFAULT \'cash\',
                currency_code VARCHAR(10) NOT NULL,
                exchange_rate DECIMAL(14,4) NOT NULL DEFAULT 1,
                amount_original DECIMAL(14,2) NOT NULL,
                amount_converted DECIMAL(14,2) NOT NULL,
                applied_original DECIMAL(14,2) NOT NULL,
                applied_converted DECIMAL(14,2) NOT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_invoice_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            )'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS purchase_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                purchase_id INT NOT NULL,
                payment_date DATE NOT NULL,
                reference VARCHAR(120) NOT NULL,
                payment_method VARCHAR(40) NOT NULL DEFAULT \'cash\',
                currency_code VARCHAR(10) NOT NULL,
                exchange_rate DECIMAL(14,4) NOT NULL DEFAULT 1,
                amount_original DECIMAL(14,2) NOT NULL,
                amount_converted DECIMAL(14,2) NOT NULL,
                applied_original DECIMAL(14,2) NOT NULL,
                applied_converted DECIMAL(14,2) NOT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_purchase_payments_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
            )'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS delivery_note_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                delivery_note_id INT NOT NULL,
                payment_date DATE NOT NULL,
                reference VARCHAR(120) NOT NULL,
                payment_method VARCHAR(40) NOT NULL DEFAULT \'cash\',
                currency_code VARCHAR(10) NOT NULL,
                exchange_rate DECIMAL(14,4) NOT NULL DEFAULT 1,
                amount_original DECIMAL(14,2) NOT NULL,
                amount_converted DECIMAL(14,2) NOT NULL,
                applied_original DECIMAL(14,2) NOT NULL,
                applied_converted DECIMAL(14,2) NOT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_delivery_note_payments_note FOREIGN KEY (delivery_note_id) REFERENCES delivery_notes(id) ON DELETE CASCADE
            )'
        );

        foreach ([
            ['invoice_payments', 'applied_original'],
            ['invoice_payments', 'applied_converted'],
            ['invoice_payments', 'payment_method'],
            ['purchase_payments', 'applied_original'],
            ['purchase_payments', 'applied_converted'],
            ['purchase_payments', 'payment_method'],
            ['delivery_note_payments', 'applied_original'],
            ['delivery_note_payments', 'applied_converted'],
            ['delivery_note_payments', 'payment_method'],
        ] as [$table, $column]) {
            if (!self::columnExists($db, $table, $column)) {
                if ($column === 'payment_method') {
                    $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} VARCHAR(40) NOT NULL DEFAULT 'cash'");
                    continue;
                }

                $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} DECIMAL(14,2) NOT NULL DEFAULT 0");
            }
        }

        $db->exec('UPDATE invoices SET due_date = COALESCE(due_date, invoice_date) WHERE due_date IS NULL');
        $db->exec('UPDATE purchases SET due_date = COALESCE(due_date, purchase_date) WHERE due_date IS NULL');
        $db->exec('UPDATE delivery_notes SET due_date = COALESCE(due_date, note_date) WHERE due_date IS NULL');
        $db->exec('UPDATE invoices SET balance_original = total_original WHERE balance_original = 0 AND total_original > 0 AND amount_paid_original = 0');
        $db->exec('UPDATE invoices SET balance_converted = total_converted WHERE balance_converted = 0 AND total_converted > 0 AND amount_paid_converted = 0');
        $db->exec("UPDATE invoices SET payment_status = CASE WHEN COALESCE(status, 'active') = 'cancelled' THEN 'cancelled' WHEN balance_converted <= 0 THEN 'paid' WHEN amount_paid_converted > 0 THEN 'partial' ELSE 'pending' END");
        $db->exec('UPDATE purchases SET balance_original = total_original WHERE balance_original = 0 AND total_original > 0 AND amount_paid_original = 0');
        $db->exec('UPDATE purchases SET balance_converted = total_converted WHERE balance_converted = 0 AND total_converted > 0 AND amount_paid_converted = 0');
        $db->exec("UPDATE purchases SET payment_status = CASE WHEN COALESCE(status, 'active') = 'cancelled' THEN 'cancelled' WHEN balance_converted <= 0 THEN 'paid' WHEN amount_paid_converted > 0 THEN 'partial' ELSE 'pending' END");
        $db->exec('UPDATE delivery_notes SET balance_original = total_original WHERE balance_original = 0 AND total_original > 0 AND amount_paid_original = 0');
        $db->exec('UPDATE delivery_notes SET balance_converted = total_converted WHERE balance_converted = 0 AND total_converted > 0 AND amount_paid_converted = 0');
        $db->exec("UPDATE delivery_notes SET payment_status = CASE WHEN COALESCE(status, 'active') = 'cancelled' THEN 'cancelled' WHEN balance_converted <= 0 THEN 'paid' WHEN amount_paid_converted > 0 THEN 'partial' ELSE 'pending' END");
    }

    private static function ensureRateSettings(PDO $db): void
    {
        $defaults = [
            'currency_base' => 'USD',
            'currency_secondary' => 'VES',
            'default_exchange_rate' => '36.50',
            'exchange_rate_mode' => 'bcv_usd',
            'exchange_rate_custom_currency' => 'USD',
            'exchange_rate_custom' => '36.50',
            'tax_percent' => '16',
            'invoice_due_days' => '10',
            'purchase_due_days' => '10',
        ];

        $statement = $db->prepare(
            'INSERT INTO settings (key_name, value)
             SELECT ?, ?
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM settings
                 WHERE key_name = ?
             )'
        );

        foreach ($defaults as $key => $value) {
            $statement->execute([$key, $value, $key]);
        }
    }

    private static function ensureUsersManagement(PDO $db): void
    {
        if (!self::columnExists($db, 'users', 'email')) {
            $db->exec("ALTER TABLE users ADD COLUMN email VARCHAR(160) NULL AFTER name");
        }

        if (!self::columnExists($db, 'users', 'is_active')) {
            $db->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
        }

        if (!self::columnExists($db, 'users', 'updated_at')) {
            $db->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
    }

    private static function ensureSuppliersManagement(PDO $db): void
    {
        if (!self::columnExists($db, 'suppliers', 'is_active')) {
            $db->exec("ALTER TABLE suppliers ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER address");
        }

        if (!self::columnExists($db, 'suppliers', 'created_at')) {
            $db->exec("ALTER TABLE suppliers ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active");
        }

        if (!self::columnExists($db, 'suppliers', 'updated_at')) {
            $db->exec("ALTER TABLE suppliers ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
    }

    private static function ensureTreasury(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS cash_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_code VARCHAR(20) NOT NULL,
                account_name VARCHAR(120) NOT NULL,
                method_type VARCHAR(40) NOT NULL,
                currency_code VARCHAR(10) NOT NULL,
                opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_cash_account_method_currency (method_type, currency_code)
            )"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS cash_movements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cash_account_id INT NOT NULL,
                movement_date DATE NOT NULL,
                direction VARCHAR(10) NOT NULL,
                currency_code VARCHAR(10) NOT NULL,
                exchange_rate DECIMAL(14,4) NOT NULL DEFAULT 1,
                amount_original DECIMAL(14,2) NOT NULL,
                amount_converted DECIMAL(14,2) NOT NULL,
                source_type VARCHAR(40) NULL,
                source_id INT NULL,
                reference VARCHAR(120) NOT NULL,
                notes TEXT NULL,
                is_reversed TINYINT(1) NOT NULL DEFAULT 0,
                reversed_at DATETIME NULL,
                reversal_reason TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_cash_movements_account FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id)
            )"
        );

        foreach ([
            ['invoice_payments', 'treasury_account_id', 'ALTER TABLE invoice_payments ADD COLUMN treasury_account_id INT NULL'],
            ['purchase_payments', 'treasury_account_id', 'ALTER TABLE purchase_payments ADD COLUMN treasury_account_id INT NULL'],
            ['delivery_note_payments', 'treasury_account_id', 'ALTER TABLE delivery_note_payments ADD COLUMN treasury_account_id INT NULL'],
            ['expenses', 'payment_method', "ALTER TABLE expenses ADD COLUMN payment_method VARCHAR(40) NOT NULL DEFAULT 'cash'"],
            ['expenses', 'treasury_account_id', 'ALTER TABLE expenses ADD COLUMN treasury_account_id INT NULL'],
        ] as [$table, $column, $sql]) {
            if (!self::columnExists($db, $table, $column)) {
                $db->exec($sql);
            }
        }

        $accountStatement = $db->prepare(
            'INSERT INTO cash_accounts (account_code, account_name, method_type, currency_code, opening_balance, is_active)
             SELECT ?, ?, ?, ?, 0, 1
             WHERE NOT EXISTS (
                SELECT 1
                FROM cash_accounts
                WHERE method_type = ?
                  AND currency_code = ?
             )'
        );
        $currencies = array_values(array_unique(array_filter([base_currency(), secondary_currency()])));

        foreach (array_keys(payment_method_options()) as $method) {
            foreach ($currencies as $currency) {
                $normalizedCurrency = normalize_currency_code($currency);
                $accountStatement->execute([
                    treasury_account_code($method, $normalizedCurrency),
                    treasury_account_label($method, $normalizedCurrency),
                    $method,
                    $normalizedCurrency,
                    $method,
                    $normalizedCurrency,
                ]);
            }
        }

        foreach ([
            ['invoice_payments', 'currency_code', 'payment_method'],
            ['purchase_payments', 'currency_code', 'payment_method'],
            ['delivery_note_payments', 'currency_code', 'payment_method'],
            ['expenses', 'currency_code', 'payment_method'],
        ] as [$table, $currencyColumn, $methodColumn]) {
            $statement = $db->query(
                "SELECT id, {$currencyColumn} AS currency_code, {$methodColumn} AS payment_method
                 FROM {$table}
                 WHERE treasury_account_id IS NULL"
            );

            foreach ($statement->fetchAll() as $row) {
                $update = $db->prepare("UPDATE {$table} SET treasury_account_id = ? WHERE id = ?");
                $update->execute([
                    self::cashAccountId(
                        $db,
                        (string) ($row['payment_method'] ?? 'cash'),
                        (string) ($row['currency_code'] ?? secondary_currency())
                    ),
                    $row['id'],
                ]);
            }
        }

        $db->exec(
            "INSERT INTO cash_movements
                (cash_account_id, movement_date, direction, currency_code, exchange_rate, amount_original, amount_converted, source_type, source_id, reference, notes)
             SELECT ip.treasury_account_id, ip.payment_date, 'in', ip.currency_code, ip.exchange_rate, ip.amount_original, ip.amount_converted, 'invoice_payment', ip.id, ip.reference, ip.notes
             FROM invoice_payments ip
             WHERE ip.treasury_account_id IS NOT NULL
               AND NOT EXISTS (
                    SELECT 1
                    FROM cash_movements cm
                    WHERE cm.source_type = 'invoice_payment'
                      AND cm.source_id = ip.id
               )"
        );

        $db->exec(
            "INSERT INTO cash_movements
                (cash_account_id, movement_date, direction, currency_code, exchange_rate, amount_original, amount_converted, source_type, source_id, reference, notes)
             SELECT pp.treasury_account_id, pp.payment_date, 'out', pp.currency_code, pp.exchange_rate, pp.amount_original, pp.amount_converted, 'purchase_payment', pp.id, pp.reference, pp.notes
             FROM purchase_payments pp
             WHERE pp.treasury_account_id IS NOT NULL
               AND NOT EXISTS (
                    SELECT 1
                    FROM cash_movements cm
                    WHERE cm.source_type = 'purchase_payment'
                      AND cm.source_id = pp.id
               )"
        );

        $db->exec(
            "INSERT INTO cash_movements
                (cash_account_id, movement_date, direction, currency_code, exchange_rate, amount_original, amount_converted, source_type, source_id, reference, notes)
             SELECT dnp.treasury_account_id, dnp.payment_date, 'in', dnp.currency_code, dnp.exchange_rate, dnp.amount_original, dnp.amount_converted, 'delivery_note_payment', dnp.id, dnp.reference, dnp.notes
             FROM delivery_note_payments dnp
             WHERE dnp.treasury_account_id IS NOT NULL
               AND NOT EXISTS (
                    SELECT 1
                    FROM cash_movements cm
                    WHERE cm.source_type = 'delivery_note_payment'
                      AND cm.source_id = dnp.id
               )"
        );

        $db->exec(
            "INSERT INTO cash_movements
                (cash_account_id, movement_date, direction, currency_code, exchange_rate, amount_original, amount_converted, source_type, source_id, reference, notes)
             SELECT e.treasury_account_id, e.expense_date, 'out', e.currency_code, e.exchange_rate, e.amount_original, e.amount_converted, 'expense', e.id, e.reference, e.description
             FROM expenses e
             WHERE COALESCE(e.status, 'active') <> 'cancelled'
               AND e.treasury_account_id IS NOT NULL
               AND NOT EXISTS (
                    SELECT 1
                    FROM cash_movements cm
                    WHERE cm.source_type = 'expense'
                      AND cm.source_id = e.id
               )"
        );
    }

    private static function cashAccountId(PDO $db, string $method, string $currency): int
    {
        $statement = $db->prepare(
            'SELECT id
             FROM cash_accounts
             WHERE method_type = ?
               AND currency_code = ?
             LIMIT 1'
        );
        $statement->execute([strtolower(trim($method)), normalize_currency_code($currency)]);

        return (int) ($statement->fetchColumn() ?: 0);
    }

    private static function columnExists(PDO $db, string $table, string $column): bool
    {
        $sql = 'SELECT COUNT(*) total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?';

        $statement = $db->prepare($sql);
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }
}

