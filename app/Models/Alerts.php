<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Alerts
{
    private const UPCOMING_DAYS = 7;
    private const MAX_PER_GROUP = 8;

    public function summary(): array
    {
        $today = date('Y-m-d');
        $upcomingLimit = date('Y-m-d', strtotime('+' . self::UPCOMING_DAYS . ' days', strtotime($today)));

        $groups = [
            'stock' => $this->stockAlerts(),
            'invoices_overdue' => $this->documentsOverdue('invoices', 'invoice_number', 'invoice_date', '/invoices', $today, false),
            'invoices_upcoming' => $this->documentsOverdue('invoices', 'invoice_number', 'invoice_date', '/invoices', $today, true, $upcomingLimit),
            'deliveries_overdue' => $this->documentsOverdue('delivery_notes', 'note_number', 'note_date', '/delivery-notes', $today, false),
            'purchases_overdue' => $this->purchasesOverdue($today, false),
            'purchases_upcoming' => $this->purchasesOverdue($today, true, $upcomingLimit),
        ];

        $total = 0;
        foreach ($groups as $items) {
            $total += count($items);
        }

        return [
            'total' => $total,
            'groups' => $groups,
        ];
    }

    private function stockAlerts(): array
    {
        $statement = Database::connection()->query(
            "SELECT id, sku, name, stock, stock_min, unit_label, product_type
             FROM products
             WHERE deleted_at IS NULL
               AND COALESCE(status, 'active') = 'active'
               AND COALESCE(product_type, 'merchandise') <> 'service'
               AND stock <= stock_min
             ORDER BY (stock_min - stock) DESC, name ASC
             LIMIT " . self::MAX_PER_GROUP
        );

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $stock = (float) ($row['stock'] ?? 0);
            $min = (float) ($row['stock_min'] ?? 0);
            $unit = trim((string) ($row['unit_label'] ?? 'und')) ?: 'und';
            $severity = $stock <= 0 ? 'critical' : 'warning';
            $title = $stock <= 0 ? 'Sin existencia' : 'Stock por debajo del minimo';

            $result[] = [
                'severity' => $severity,
                'title' => (string) ($row['name'] ?? 'Producto'),
                'meta' => $title . ' - ' . $this->formatNumber($stock) . ' ' . $unit
                    . ' / minimo ' . $this->formatNumber($min) . ' ' . $unit,
                'href' => '/inventory',
                'sku' => (string) ($row['sku'] ?? ''),
            ];
        }

        return $result;
    }

    private function documentsOverdue(
        string $table,
        string $numberColumn,
        string $dateColumn,
        string $hrefBase,
        string $today,
        bool $upcoming = false,
        ?string $upcomingLimit = null
    ): array {
        $db = Database::connection();

        if ($upcoming) {
            $sql = "SELECT t.id, t.{$numberColumn} AS doc_number, t.due_date, t.balance_converted, t.balance_original, t.currency_code,
                           c.name AS contact_name
                    FROM {$table} t
                    LEFT JOIN clients c ON c.id = t.client_id
                    WHERE COALESCE(t.status, 'active') <> 'cancelled'
                      AND COALESCE(t.balance_converted, 0) > 0.01
                      AND t.due_date IS NOT NULL
                      AND t.due_date >= ?
                      AND t.due_date <= ?
                    ORDER BY t.due_date ASC
                    LIMIT " . self::MAX_PER_GROUP;
            $statement = $db->prepare($sql);
            $statement->execute([$today, $upcomingLimit ?? $today]);
        } else {
            $sql = "SELECT t.id, t.{$numberColumn} AS doc_number, t.due_date, t.balance_converted, t.balance_original, t.currency_code,
                           c.name AS contact_name
                    FROM {$table} t
                    LEFT JOIN clients c ON c.id = t.client_id
                    WHERE COALESCE(t.status, 'active') <> 'cancelled'
                      AND COALESCE(t.balance_converted, 0) > 0.01
                      AND t.due_date IS NOT NULL
                      AND t.due_date < ?
                    ORDER BY t.due_date ASC
                    LIMIT " . self::MAX_PER_GROUP;
            $statement = $db->prepare($sql);
            $statement->execute([$today]);
        }

        return array_map(function (array $row) use ($hrefBase, $today, $upcoming, $dateColumn): array {
            $dueDate = (string) ($row['due_date'] ?? '');
            $days = $this->dayDiff($dueDate, $today);
            $balance = (float) ($row['balance_original'] ?? 0);
            $currency = (string) ($row['currency_code'] ?? '');
            $contact = trim((string) ($row['contact_name'] ?? '')) ?: 'Sin cliente';

            return [
                'severity' => $upcoming ? 'info' : 'critical',
                'title' => $contact . ' - ' . (string) ($row['doc_number'] ?? ''),
                'meta' => ($upcoming
                    ? 'Vence en ' . max(0, -$days) . ' dia(s)'
                    : 'Vencida hace ' . max(0, $days) . ' dia(s)')
                    . ' - Saldo ' . $this->formatNumber($balance) . ' ' . $currency,
                'href' => $hrefBase,
            ];
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function purchasesOverdue(string $today, bool $upcoming = false, ?string $upcomingLimit = null): array
    {
        $db = Database::connection();

        if ($upcoming) {
            $sql = "SELECT p.id, p.doc_number, p.due_date, p.balance_converted, p.balance_original, p.currency_code,
                           s.name AS contact_name
                    FROM purchases p
                    LEFT JOIN suppliers s ON s.id = p.supplier_id
                    WHERE COALESCE(p.status, 'active') <> 'cancelled'
                      AND COALESCE(p.balance_converted, 0) > 0.01
                      AND p.due_date IS NOT NULL
                      AND p.due_date >= ?
                      AND p.due_date <= ?
                    ORDER BY p.due_date ASC
                    LIMIT " . self::MAX_PER_GROUP;
            $statement = $db->prepare($sql);
            $statement->execute([$today, $upcomingLimit ?? $today]);
        } else {
            $sql = "SELECT p.id, p.doc_number, p.due_date, p.balance_converted, p.balance_original, p.currency_code,
                           s.name AS contact_name
                    FROM purchases p
                    LEFT JOIN suppliers s ON s.id = p.supplier_id
                    WHERE COALESCE(p.status, 'active') <> 'cancelled'
                      AND COALESCE(p.balance_converted, 0) > 0.01
                      AND p.due_date IS NOT NULL
                      AND p.due_date < ?
                    ORDER BY p.due_date ASC
                    LIMIT " . self::MAX_PER_GROUP;
            $statement = $db->prepare($sql);
            $statement->execute([$today]);
        }

        return array_map(function (array $row) use ($today, $upcoming): array {
            $dueDate = (string) ($row['due_date'] ?? '');
            $days = $this->dayDiff($dueDate, $today);
            $balance = (float) ($row['balance_original'] ?? 0);
            $currency = (string) ($row['currency_code'] ?? '');
            $contact = trim((string) ($row['contact_name'] ?? '')) ?: 'Sin proveedor';

            return [
                'severity' => $upcoming ? 'info' : 'critical',
                'title' => $contact . ' - ' . (string) ($row['doc_number'] ?? ''),
                'meta' => ($upcoming
                    ? 'Vence en ' . max(0, -$days) . ' dia(s)'
                    : 'Vencida hace ' . max(0, $days) . ' dia(s)')
                    . ' - Saldo ' . $this->formatNumber($balance) . ' ' . $currency,
                'href' => '/purchases',
            ];
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function dayDiff(string $dueDate, string $today): int
    {
        if ($dueDate === '') {
            return 0;
        }
        $dueTs = strtotime($dueDate);
        $todayTs = strtotime($today);
        if ($dueTs === false || $todayTs === false) {
            return 0;
        }
        return (int) floor(($todayTs - $dueTs) / 86400);
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }
}
