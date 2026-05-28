<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Charts
{
    public function salesByPeriod(string $from, string $to, string $granularity): array
    {
        $db = Database::connection();
        [$groupExpr, $labelFormat] = $this->granularityExpressions($granularity);
        $buckets = $this->bucketsForRange($from, $to, $granularity);
        $documentTotalSql = $this->documentTotalVes();

        $statement = $db->prepare(
            "SELECT {$groupExpr} AS bucket, COALESCE(SUM(total), 0) AS total
             FROM (
                SELECT invoice_date AS dt, {$documentTotalSql} AS total
                FROM invoices
                WHERE invoice_date BETWEEN ? AND ?
                  AND COALESCE(status, 'active') <> 'cancelled'

                UNION ALL

                SELECT note_date AS dt, {$documentTotalSql} AS total
                FROM delivery_notes
                WHERE note_date BETWEEN ? AND ?
                  AND COALESCE(status, 'active') <> 'cancelled'
             ) sales_rows
             GROUP BY bucket"
        );
        $statement->execute([$from, $to, $from, $to]);
        $rowsByBucket = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rowsByBucket[(string) $row['bucket']] = (float) $row['total'];
        }

        $labels = [];
        $values = [];
        foreach ($buckets as $bucket) {
            $labels[] = $this->formatBucketLabel($bucket, $granularity);
            $values[] = $rowsByBucket[$bucket] ?? 0.0;
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'buckets' => $buckets,
            'granularity' => $granularity,
        ];
    }

    public function compareFlows(string $from, string $to, string $granularity): array
    {
        $db = Database::connection();
        [$groupExpr] = $this->granularityExpressions($granularity);
        $buckets = $this->bucketsForRange($from, $to, $granularity);
        $expenseSql = $this->expenseConsolidatedSql();
        $documentTotalSql = $this->documentTotalVes();

        $sales = $this->emptySeries($buckets);
        $purchases = $this->emptySeries($buckets);
        $expenses = $this->emptySeries($buckets);

        $statement = $db->prepare(
            "SELECT {$groupExpr} AS bucket, COALESCE(SUM(total), 0) AS total
             FROM (
                SELECT invoice_date AS dt, {$documentTotalSql} AS total
                FROM invoices
                WHERE invoice_date BETWEEN ? AND ?
                  AND COALESCE(status, 'active') <> 'cancelled'

                UNION ALL

                SELECT note_date AS dt, {$documentTotalSql} AS total
                FROM delivery_notes
                WHERE note_date BETWEEN ? AND ?
                  AND COALESCE(status, 'active') <> 'cancelled'
             ) sales_rows
             GROUP BY bucket"
        );
        $statement->execute([$from, $to, $from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sales[(string) $row['bucket']] = (float) $row['total'];
        }

        $statement = $db->prepare(
            "SELECT " . str_replace('dt', 'purchase_date', $groupExpr) . " AS bucket,
                    COALESCE(SUM({$documentTotalSql}), 0) AS total
             FROM purchases
             WHERE purchase_date BETWEEN ? AND ?
               AND COALESCE(status, 'active') <> 'cancelled'
             GROUP BY bucket"
        );
        $statement->execute([$from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $purchases[(string) $row['bucket']] = (float) $row['total'];
        }

        $statement = $db->prepare(
            "SELECT " . str_replace('dt', 'expense_date', $groupExpr) . " AS bucket,
                    COALESCE(SUM({$expenseSql}), 0) AS total
             FROM expenses
             WHERE expense_date BETWEEN ? AND ?
               AND COALESCE(status, 'active') <> 'cancelled'
             GROUP BY bucket"
        );
        $statement->execute([$from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $expenses[(string) $row['bucket']] = (float) $row['total'];
        }

        return [
            'labels' => array_map(fn (string $b): string => $this->formatBucketLabel($b, $granularity), $buckets),
            'sales' => array_values($sales),
            'purchases' => array_values($purchases),
            'expenses' => array_values($expenses),
        ];
    }

    public function topProducts(string $from, string $to, int $limit = 10): array
    {
        $statement = Database::connection()->prepare(
            "SELECT p.id, p.name,
                    COALESCE(SUM(sales.quantity), 0) AS quantity,
                    COALESCE(SUM(sales.total), 0) AS total
             FROM (
                SELECT ii.product_id, ii.quantity, ii.total_converted AS total
                FROM invoice_items ii
                INNER JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.invoice_date BETWEEN ? AND ?
                  AND COALESCE(i.status, 'active') <> 'cancelled'

                UNION ALL

                SELECT di.product_id, di.quantity, di.total_converted AS total
                FROM delivery_note_items di
                INNER JOIN delivery_notes d ON d.id = di.delivery_note_id
                WHERE d.note_date BETWEEN ? AND ?
                  AND COALESCE(d.status, 'active') <> 'cancelled'
             ) sales
             INNER JOIN products p ON p.id = sales.product_id
             GROUP BY p.id, p.name
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

    public function abcAnalysis(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            "SELECT p.id, p.name, p.sku,
                    COALESCE(SUM(sales.total), 0) AS total
             FROM (
                SELECT ii.product_id, ii.total_converted AS total
                FROM invoice_items ii
                INNER JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.invoice_date BETWEEN ? AND ?
                  AND COALESCE(i.status, 'active') <> 'cancelled'

                UNION ALL

                SELECT di.product_id, di.total_converted AS total
                FROM delivery_note_items di
                INNER JOIN delivery_notes d ON d.id = di.delivery_note_id
                WHERE d.note_date BETWEEN ? AND ?
                  AND COALESCE(d.status, 'active') <> 'cancelled'
             ) sales
             INNER JOIN products p ON p.id = sales.product_id
             GROUP BY p.id, p.name, p.sku
             HAVING total > 0
             ORDER BY total DESC"
        );
        $statement->execute([$from, $to, $from, $to]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $grandTotal = array_sum(array_map(static fn (array $r): float => (float) $r['total'], $rows));
        if ($grandTotal <= 0) {
            return ['rows' => [], 'classCount' => ['A' => 0, 'B' => 0, 'C' => 0]];
        }

        $cumulative = 0.0;
        $result = [];
        $classCount = ['A' => 0, 'B' => 0, 'C' => 0];
        foreach ($rows as $row) {
            $total = (float) $row['total'];
            $share = $total / $grandTotal;
            $cumulative += $share;
            $class = $cumulative <= 0.80 ? 'A' : ($cumulative <= 0.95 ? 'B' : 'C');
            $classCount[$class] += 1;
            $result[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'sku' => (string) ($row['sku'] ?? ''),
                'total' => $total,
                'share' => $share,
                'cumulative' => min(1.0, $cumulative),
                'class' => $class,
            ];
        }

        return ['rows' => $result, 'classCount' => $classCount, 'grandTotal' => $grandTotal];
    }

    public function topClients(string $from, string $to, int $limit = 10): array
    {
        $statement = Database::connection()->prepare(
            "SELECT c.id, c.name,
                    COALESCE(SUM(sales.total), 0) AS total,
                    COUNT(DISTINCT sales.doc_id) AS documents
             FROM (
                SELECT i.id AS doc_id, i.client_id, i.total_converted AS total
                FROM invoices i
                WHERE i.invoice_date BETWEEN ? AND ?
                  AND COALESCE(i.status, 'active') <> 'cancelled'

                UNION ALL

                SELECT d.id AS doc_id, d.client_id, d.total_converted AS total
                FROM delivery_notes d
                WHERE d.note_date BETWEEN ? AND ?
                  AND COALESCE(d.status, 'active') <> 'cancelled'
             ) sales
             INNER JOIN clients c ON c.id = sales.client_id
             GROUP BY c.id, c.name
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

    public function receivablesAging(string $asOf): array
    {
        $statement = Database::connection()->prepare(
            "SELECT due_date, balance_converted
             FROM (
                SELECT due_date, balance_converted
                FROM invoices
                WHERE COALESCE(status, 'active') <> 'cancelled'
                  AND COALESCE(balance_converted, 0) > 0.01

                UNION ALL

                SELECT due_date, balance_converted
                FROM delivery_notes
                WHERE COALESCE(status, 'active') <> 'cancelled'
                  AND COALESCE(balance_converted, 0) > 0.01
             ) pending"
        );
        $statement->execute();

        $buckets = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            '90_plus' => 0.0,
        ];

        $asOfTs = strtotime($asOf) ?: time();
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dueDate = (string) ($row['due_date'] ?? '');
            $balance = (float) ($row['balance_converted'] ?? 0);
            if ($balance <= 0) {
                continue;
            }

            $dueTs = $dueDate !== '' ? (strtotime($dueDate) ?: $asOfTs) : $asOfTs;
            $daysOverdue = (int) floor(($asOfTs - $dueTs) / 86400);

            if ($daysOverdue <= 0) {
                $buckets['current'] += $balance;
            } elseif ($daysOverdue <= 30) {
                $buckets['1_30'] += $balance;
            } elseif ($daysOverdue <= 60) {
                $buckets['31_60'] += $balance;
            } elseif ($daysOverdue <= 90) {
                $buckets['61_90'] += $balance;
            } else {
                $buckets['90_plus'] += $balance;
            }
        }

        return [
            'labels' => ['Al dia', '1-30 dias', '31-60 dias', '61-90 dias', '+90 dias'],
            'values' => array_values($buckets),
            'total' => array_sum($buckets),
        ];
    }

    public function salesByPaymentMethod(string $from, string $to): array
    {
        $statement = Database::connection()->prepare(
            "SELECT payment_method, COALESCE(SUM(applied_converted), 0) AS total
             FROM (
                SELECT payment_method, applied_converted
                FROM invoice_payments ip
                INNER JOIN invoices i ON i.id = ip.invoice_id
                WHERE ip.payment_date BETWEEN ? AND ?
                  AND COALESCE(i.status, 'active') <> 'cancelled'

                UNION ALL

                SELECT payment_method, applied_converted
                FROM delivery_note_payments dp
                INNER JOIN delivery_notes d ON d.id = dp.delivery_note_id
                WHERE dp.payment_date BETWEEN ? AND ?
                  AND COALESCE(d.status, 'active') <> 'cancelled'
             ) payments
             GROUP BY payment_method
             ORDER BY total DESC"
        );
        $statement->execute([$from, $to, $from, $to]);

        $labels = [];
        $values = [];
        $methodOptions = function_exists('payment_method_options') ? payment_method_options() : [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $method = (string) ($row['payment_method'] ?? '');
            $labels[] = (string) ($methodOptions[$method] ?? ucfirst(str_replace('_', ' ', $method)));
            $values[] = (float) ($row['total'] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Predicción simple: regresión lineal por mínimos cuadrados sobre la serie
     * de ventas mensuales de los últimos $monthsBack meses, proyectando $monthsForward.
     */
    public function salesForecast(int $monthsBack = 12, int $monthsForward = 3): array
    {
        $db = Database::connection();
        $end = new \DateTime('first day of this month');
        $start = (clone $end)->modify('-' . ($monthsBack - 1) . ' months');

        $documentTotalSql = $this->documentTotalVes();
        $statement = $db->prepare(
            "SELECT DATE_FORMAT(dt, '%Y-%m') AS bucket, COALESCE(SUM(total), 0) AS total
             FROM (
                SELECT invoice_date AS dt, {$documentTotalSql} AS total
                FROM invoices
                WHERE invoice_date BETWEEN ? AND ?
                  AND COALESCE(status, 'active') <> 'cancelled'

                UNION ALL

                SELECT note_date AS dt, {$documentTotalSql} AS total
                FROM delivery_notes
                WHERE note_date BETWEEN ? AND ?
                  AND COALESCE(status, 'active') <> 'cancelled'
             ) sales
             GROUP BY bucket
             ORDER BY bucket ASC"
        );
        $endOfMonth = (clone $end)->modify('last day of this month')->format('Y-m-d');
        $startStr = $start->format('Y-m-d');
        $statement->execute([$startStr, $endOfMonth, $startStr, $endOfMonth]);
        $rowsByBucket = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rowsByBucket[(string) $row['bucket']] = (float) $row['total'];
        }

        // Serie histórica completa (rellena meses sin ventas con 0)
        $labels = [];
        $historical = [];
        $cursor = clone $start;
        while ($cursor <= $end) {
            $bucket = $cursor->format('Y-m');
            $labels[] = $this->formatMonthLabel($bucket);
            $historical[] = $rowsByBucket[$bucket] ?? 0.0;
            $cursor->modify('+1 month');
        }

        // Regresión lineal y = a + bx
        $n = count($historical);
        $forecast = array_fill(0, $n, null);
        $trend = array_fill(0, $n, null);

        if ($n >= 2) {
            $sumX = 0.0;
            $sumY = 0.0;
            $sumXY = 0.0;
            $sumX2 = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $x = $i;
                $y = $historical[$i];
                $sumX += $x;
                $sumY += $y;
                $sumXY += $x * $y;
                $sumX2 += $x * $x;
            }
            $denominator = ($n * $sumX2) - ($sumX * $sumX);
            $slope = $denominator !== 0.0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denominator : 0.0;
            $intercept = ($sumY - ($slope * $sumX)) / $n;

            for ($i = 0; $i < $n; $i++) {
                $trend[$i] = max(0.0, $intercept + ($slope * $i));
            }

            // Extender la proyección a $monthsForward meses futuros
            $futureCursor = (clone $end)->modify('+1 month');
            for ($j = 0; $j < $monthsForward; $j++) {
                $labels[] = $this->formatMonthLabel($futureCursor->format('Y-m'));
                $historical[] = null;
                $trend[] = null;
                $forecast[] = max(0.0, $intercept + ($slope * ($n + $j)));
                $futureCursor->modify('+1 month');
            }
        }

        return [
            'labels' => $labels,
            'historical' => $historical,
            'trend' => $trend,
            'forecast' => $forecast,
        ];
    }

    /**
     * Devuelve la granularidad sugerida según el ancho del rango.
     */
    public function suggestedGranularity(string $from, string $to): string
    {
        $diff = (strtotime($to) - strtotime($from)) / 86400;
        if ($diff <= 31) {
            return 'day';
        }
        if ($diff <= 180) {
            return 'week';
        }

        return 'month';
    }

    private function granularityExpressions(string $granularity): array
    {
        return match ($granularity) {
            'week' => ["DATE_FORMAT(dt, '%x-W%v')", 'week'],
            'month' => ["DATE_FORMAT(dt, '%Y-%m')", 'month'],
            default => ["DATE_FORMAT(dt, '%Y-%m-%d')", 'day'],
        };
    }

    private function bucketsForRange(string $from, string $to, string $granularity): array
    {
        $start = new \DateTime($from);
        $end = new \DateTime($to);
        $start->setTime(0, 0, 0);
        $end->setTime(0, 0, 0);

        $buckets = [];
        $current = clone $start;

        switch ($granularity) {
            case 'week':
                // Anclar al lunes ISO
                $current->modify('monday this week');
                $endAligned = (clone $end)->modify('monday this week');
                while ($current <= $endAligned) {
                    $buckets[] = $current->format('o-\WW');
                    $current->modify('+1 week');
                }
                break;
            case 'month':
                $current->modify('first day of this month');
                $endAligned = (clone $end)->modify('first day of this month');
                while ($current <= $endAligned) {
                    $buckets[] = $current->format('Y-m');
                    $current->modify('+1 month');
                }
                break;
            default:
                while ($current <= $end) {
                    $buckets[] = $current->format('Y-m-d');
                    $current->modify('+1 day');
                }
        }

        return $buckets;
    }

    private function emptySeries(array $buckets): array
    {
        $series = [];
        foreach ($buckets as $bucket) {
            $series[$bucket] = 0.0;
        }
        return $series;
    }

    private function formatBucketLabel(string $bucket, string $granularity): string
    {
        switch ($granularity) {
            case 'week':
                // bucket viene como YYYY-Www
                return $bucket;
            case 'month':
                return $this->formatMonthLabel($bucket);
            default:
                $ts = strtotime($bucket);
                return $ts ? date('d/m', $ts) : $bucket;
        }
    }

    private function formatMonthLabel(string $yearMonth): string
    {
        $ts = strtotime($yearMonth . '-01');
        if (!$ts) {
            return $yearMonth;
        }
        $months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
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

    /**
     * Devuelve una expresion SQL que representa el total del documento en VES,
     * usando total_converted cuando esta poblado y reconstruyendolo desde
     * total_original + currency_code + exchange_rate cuando es 0 (datos legados).
     */
    private function documentTotalVes(string $alias = ''): string
    {
        $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

        return "CASE
            WHEN COALESCE({$prefix}total_converted, 0) > 0 THEN {$prefix}total_converted
            WHEN UPPER(COALESCE({$prefix}currency_code, '')) IN ('VES', 'VEF', 'BS', 'BS.S', 'BSS', 'BOLIVARES')
                THEN COALESCE({$prefix}total_original, 0)
            ELSE COALESCE({$prefix}total_original, 0) * COALESCE({$prefix}exchange_rate, 0)
        END";
    }
}
