<?php declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
class Reports
{
    private function expenseConsolidatedSql(): string
    {
        return "CASE
            WHEN UPPER(COALESCE(currency_code, '')) IN ('VES', 'VEF', 'BS', 'BS.S', 'BSS', 'BOLIVARES')
                THEN COALESCE(amount_original, 0)
            ELSE COALESCE(amount_converted, 0)
        END";
    }

    public function sales(string $from, string $to): array
    {
        $s = Database::connection()->prepare(
            "SELECT i.*, c.name AS client_name FROM invoices i LEFT JOIN clients c ON c.id=i.client_id WHERE i.invoice_date BETWEEN ? AND ? ORDER BY i.invoice_date DESC",
        );
        $s->execute([$from, $to]);
        return $s->fetchAll();
    }
    public function purchases(string $from, string $to): array
    {
        $s = Database::connection()->prepare(
            "SELECT p.*, s.name AS supplier_name FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE p.purchase_date BETWEEN ? AND ? ORDER BY p.purchase_date DESC",
        );
        $s->execute([$from, $to]);
        return $s->fetchAll();
    }
    public function expenses(string $from, string $to): array
    {
        $s = Database::connection()->prepare(
            "SELECT e.*, c.name AS category_name FROM expenses e LEFT JOIN expense_categories c ON c.id=e.category_id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC",
        );
        $s->execute([$from, $to]);
        return $s->fetchAll();
    }
    public function inventoryValued(): array
    {
        return Database::connection()
            ->query(
                "SELECT p.*, c.name AS category_name, (p.stock * p.cost) AS inventory_total
                 FROM products p
                 LEFT JOIN categories c ON c.id=p.category_id
                 WHERE p.deleted_at IS NULL
                   AND COALESCE(p.product_type, 'merchandise') <> 'service'
                 ORDER BY p.name",
            )
            ->fetchAll();
    }
    public function inventoryMovements(string $from, string $to): array
    {
        $s = Database::connection()->prepare(
            "SELECT m.*, p.name AS product_name, c.name AS category_name, w.name AS warehouse_name FROM inventory_movements m LEFT JOIN products p ON p.id=m.product_id LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN warehouses w ON w.id=m.warehouse_id WHERE DATE(m.created_at) BETWEEN ? AND ? ORDER BY m.created_at DESC",
        );
        $s->execute([$from, $to]);
        return $s->fetchAll();
    }
    public function journal(string $from, string $to): array
    {
        $sql =
            "SELECT purchase_date AS trans_date,'Compra' AS source,doc_number AS reference,total_converted AS debit,0 AS credit FROM purchases WHERE purchase_date BETWEEN ? AND ? UNION ALL SELECT expense_date,'Gasto',reference," . $this->expenseConsolidatedSql() . ",0 FROM expenses WHERE expense_date BETWEEN ? AND ? UNION ALL SELECT invoice_date,'Venta',invoice_number,0,total_converted FROM invoices WHERE invoice_date BETWEEN ? AND ? ORDER BY trans_date ASC";
        $s = Database::connection()->prepare($sql);
        $s->execute([$from, $to, $from, $to, $from, $to]);
        return $s->fetchAll();
    }
    public function ledger(string $from, string $to): array
    {
        $rows = $this->journal($from, $to);
        $balance = 0.0;
        foreach ($rows as &$r) {
            $balance += ((float) $r["debit"]) - ((float) $r["credit"]);
            $r["balance"] = $balance;
        }
        return $rows;
    }
    public function balanceSheet(string $from, string $to): array
    {
        $db = Database::connection();
        $s = $db->prepare(
            "SELECT COALESCE(SUM(total_converted),0) total FROM invoices WHERE invoice_date BETWEEN ? AND ?",
        );
        $s->execute([$from, $to]);
        $sales = (float) $s->fetch()["total"];
        $p = $db->prepare(
            "SELECT COALESCE(SUM(total_converted),0) total FROM purchases WHERE purchase_date BETWEEN ? AND ?",
        );
        $p->execute([$from, $to]);
        $purchases = (float) $p->fetch()["total"];
        $e = $db->prepare(
            "SELECT COALESCE(SUM(" . $this->expenseConsolidatedSql() . "),0) total FROM expenses WHERE expense_date BETWEEN ? AND ?",
        );
        $e->execute([$from, $to]);
        $expenses = (float) $e->fetch()["total"];
        $inv = (float) $db
            ->query(
                "SELECT COALESCE(SUM(stock * cost),0) total
                 FROM products
                 WHERE deleted_at IS NULL
                   AND COALESCE(product_type, 'merchandise') <> 'service'"
            )
            ->fetch()["total"];
        return [
            "assets" => [
                ["name" => "Inventario valorizado", "amount" => $inv],
                ["name" => "Ventas acumuladas del período", "amount" => $sales],
            ],
            "liabilities" => [
                [
                    "name" => "Compras acumuladas del período",
                    "amount" => $purchases,
                ],
                [
                    "name" => "Gastos acumulados del período",
                    "amount" => $expenses,
                ],
            ],
            "equity" => [
                [
                    "name" => "Resultado estimado",
                    "amount" => $sales - $purchases - $expenses,
                ],
            ],
        ];
    }
}
