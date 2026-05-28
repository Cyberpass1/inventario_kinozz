<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Product;
use App\Models\ReportsData;
use App\Services\PdfService;

class ReportsControllerModern extends Controller
{
    private const VALID_TYPES = ['sales', 'delivery_notes', 'purchases', 'expenses', 'receivables', 'payables', 'inventory', 'movements', 'treasury', 'journal', 'ledger', 'balance'];

    public function index(): void
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $type = $this->normalizeType($_GET['type'] ?? 'sales');
        $inventoryBreakdown = $this->normalizeInventoryBreakdown($_GET['inventory_view'] ?? 'category');
        $currentRate = $this->currentRate($to);
        $payload = $this->resolveReport($type, $from, $to, $currentRate, $inventoryBreakdown);
        $user = auth_user() ?? [];
        $canManageTreasury = (string) ($user['role'] ?? '') === 'administrator';

        $this->view('reports/workspace', [
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'title' => $payload['title'],
            'description' => $payload['description'],
            'mode' => $payload['mode'],
            'records' => $payload['records'] ?? [],
            'balance' => $payload['balance'] ?? ['assets' => [], 'liabilities' => [], 'equity' => []],
            'summaryCards' => $payload['summaryCards'] ?? [],
            'infoCards' => $payload['infoCards'] ?? [],
            'pdfUrl' => $payload['pdfUrl'],
            'inventoryBreakdown' => $payload['inventoryBreakdown'] ?? $inventoryBreakdown,
            'inventoryBreakdownOptions' => $payload['inventoryBreakdownOptions'] ?? $this->inventoryBreakdownOptions(),
            'inventoryGroups' => $payload['inventoryGroups'] ?? [],
            'inventoryOverview' => $payload['inventoryOverview'] ?? [],
            'inventoryRecap' => $payload['inventoryRecap'] ?? [],
            'baseCurrency' => base_currency(),
            'secondaryCurrency' => secondary_currency(),
            'currentRate' => $currentRate,
            'isFinancial' => in_array($type, ['journal', 'ledger', 'balance', 'treasury'], true),
            'balanceLabels' => $payload['balanceLabels'] ?? [
                'assets' => 'Activos',
                'liabilities' => 'Pasivos',
                'equity' => 'Patrimonio',
            ],
            'balanceDisclaimer' => $payload['balanceDisclaimer'] ?? null,
            'balanceOverview' => $payload['balanceOverview'] ?? [],
            'canManageTreasury' => $canManageTreasury,
            'treasuryMovements' => $payload['treasuryMovements'] ?? [],
            'productSummary' => $payload['productSummary'] ?? [],
            'productSummaryOverview' => $payload['productSummaryOverview'] ?? [],
        ], 'layouts/app_modern');
    }

    public function saveOpeningBalances(): void
    {
        validate_csrf();

        try {
            $balances = $_POST['opening_balances'] ?? [];
            if (!is_array($balances) || $balances === []) {
                throw new \RuntimeException('No se recibieron cuentas para actualizar el saldo inicial.');
            }

            $normalized = [];
            foreach ($balances as $accountId => $amount) {
                $normalized[(int) $accountId] = $amount;
            }

            (new CashAccount())->setOpeningBalances($normalized);
            flash('success', 'Saldos iniciales de tesoreria actualizados.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $from = $this->sanitizeDate($_POST['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_POST['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $this->redirect('/reports?type=treasury&from=' . rawurlencode($from) . '&to=' . rawurlencode($to));
    }

    public function saveTreasuryAdjustment(): void
    {
        validate_csrf();

        try {
            $accountId = (int) ($_POST['cash_account_id'] ?? 0);
            $realBalance = parse_money_input($_POST['real_balance'] ?? 0);
            $movementDate = $this->sanitizeDate($_POST['movement_date'] ?? date('Y-m-d'));
            $rate = $this->currentRate($movementDate);
            $notes = trim((string) ($_POST['notes'] ?? ''));

            (new CashMovement())->reconcileToRealBalance($accountId, $realBalance, $movementDate, $rate, $notes);
            flash('success', 'Ajuste de conciliacion registrado. El saldo de la cuenta queda alineado con el monto real indicado.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $from = $this->sanitizeDate($_POST['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_POST['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $this->redirect('/reports?type=treasury&from=' . rawurlencode($from) . '&to=' . rawurlencode($to));
    }

    public function reverseTreasuryAdjustment(string $id): void
    {
        validate_csrf();

        try {
            (new CashMovement())->reverseManualAdjustment((int) $id, trim((string) ($_POST['reason'] ?? '')));
            flash('success', 'Ajuste manual reversado.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $from = $this->sanitizeDate($_POST['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_POST['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $this->redirect('/reports?type=treasury&from=' . rawurlencode($from) . '&to=' . rawurlencode($to));
    }

    public function pdf(): void
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $type = $this->normalizeType($_GET['type'] ?? 'sales');
        $inventoryBreakdown = $this->normalizeInventoryBreakdown($_GET['inventory_view'] ?? 'category');

        if (in_array($type, ['journal', 'ledger', 'balance'], true)) {
            $target = match ($type) {
                'journal' => '/reports/journal/pdf',
                'ledger' => '/reports/ledger/pdf',
                default => '/reports/balance-sheet/pdf',
            };

            $this->redirect($target . '?from=' . rawurlencode($from) . '&to=' . rawurlencode($to));
        }

        $payload = $this->resolveReport($type, $from, $to, $this->currentRate($to), $inventoryBreakdown);

        if ($type === 'inventory') {
            (new PdfService())->inventoryReport(
                $payload['pdfTitle'],
                [
                    ['Desde', $from],
                    ['Hasta', $to],
                    ['Moneda consolidada', secondary_currency()],
                    ['Desglose', $payload['inventoryBreakdownLabel'] ?? 'Por categoria'],
                ],
                $payload['inventoryOverview'] ?? [],
                $payload['inventoryGroups'] ?? [],
                $payload['records'] ?? [],
                $payload['inventoryBreakdownLabel'] ?? 'Por categoria',
                'reporte-' . $type
            );
            return;
        }

        $pdfMeta = [['Desde', $from], ['Hasta', $to], ['Moneda consolidada', secondary_currency()]];
        if ($type === 'inventory') {
            $pdfMeta[] = ['Desglose', $payload['inventoryBreakdownOptions'][$payload['inventoryBreakdown']] ?? 'Por categoria'];
        }

        (new PdfService())->tableReport(
            $payload['pdfTitle'],
            $payload['headers'],
            $payload['widths'],
            $payload['rows'],
            $pdfMeta,
            'reporte-' . $type,
            'L',
            $payload['alignments'] ?? [],
            $payload['pdfSummary'] ?? [],
            $payload['pdfSections'] ?? []
        );
    }

    public function inventoryChartsPdf(): void
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $chart = $this->sanitizeInventoryChart((string) ($_GET['chart'] ?? 'all'));
        $rate = $this->currentRate($to);

        (new PdfService())->inventoryCharts([
            'chart' => $chart,
            'chart_label' => $this->inventoryChartOptions()[$chart] ?? 'Todas las graficas',
            'from' => $from,
            'to' => $to,
            'rate' => $rate,
            'base_currency' => base_currency(),
            'secondary_currency' => secondary_currency(),
            'highest_stock' => $this->productStockRanking('desc'),
            'lowest_stock' => $this->productStockRanking('asc'),
            'highest_movement' => $this->productMovementRanking($from, $to),
            'highest_value' => $this->productValueRanking($rate),
        ]);
    }

    public function journal(): void
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $this->redirect('/reports?type=journal&from=' . rawurlencode($from) . '&to=' . rawurlencode($to));
    }

    public function journalPdf(): void
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $rows = (new ReportsData())->journal($from, $to);
        $totalDebit = array_reduce($rows, fn (float $carry, array $row): float => $carry + (float) $row['debit'], 0.0);
        $totalCredit = array_reduce($rows, fn (float $carry, array $row): float => $carry + (float) $row['credit'], 0.0);

        (new PdfService())->tableReport(
            'Libro diario integrado',
            ['Fecha', 'Origen', 'Referencia', 'Cuenta', 'Tercero', 'Moneda', 'Monto doc.', 'Debe ' . secondary_currency(), 'Haber ' . secondary_currency()],
            [22, 20, 28, 42, 28, 14, 20, 18, 18],
            array_map(fn (array $row): array => [
                (string) $row['trans_date'],
                (string) $row['source'],
                (string) $row['reference'],
                (string) ($row['account_name'] ?? ''),
                (string) ($row['counterparty'] ?? ''),
                (string) ($row['currency_code'] ?? ''),
                money($row['original_amount'] ?? 0),
                money($row['debit']),
                money($row['credit']),
            ], $rows),
            [['Desde', $from], ['Hasta', $to], ['Moneda consolidada', secondary_currency()]],
            'libro-diario',
            'L',
            ['L', 'L', 'L', 'L', 'L', 'L', 'R', 'R', 'R'],
            [
                ['Renglones', (string) count($rows)],
                ['Total Debe ' . secondary_currency(), money($totalDebit)],
                ['Total Haber ' . secondary_currency(), money($totalCredit)],
                ['Diferencia', money($totalDebit - $totalCredit)],
            ]
        );
    }

    public function ledger(): void
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $this->redirect('/reports?type=ledger&from=' . rawurlencode($from) . '&to=' . rawurlencode($to));
    }

    public function ledgerPdf(): void
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $rows = (new ReportsData())->ledger($from, $to);
        $totalDebit = array_reduce($rows, fn (float $carry, array $row): float => $carry + (float) $row['debit'], 0.0);
        $totalCredit = array_reduce($rows, fn (float $carry, array $row): float => $carry + (float) $row['credit'], 0.0);
        $netBalance = array_reduce($rows, fn (float $carry, array $row): float => $carry + (float) ($row['balance'] ?? 0), 0.0);

        (new PdfService())->tableReport(
            'Libro mayor por cuentas',
            ['Codigo', 'Cuenta', 'Movimientos', 'Ult. mov.', 'Debe ' . secondary_currency(), 'Haber ' . secondary_currency(), 'Saldo ' . secondary_currency()],
            [20, 58, 22, 22, 24, 24, 24],
            array_map(fn (array $row): array => [
                (string) ($row['account_code'] ?? ''),
                (string) ($row['account_name'] ?? ''),
                (string) ($row['entry_count'] ?? 0),
                (string) ($row['last_date'] ?? ''),
                money($row['debit']),
                money($row['credit']),
                money($row['balance']),
            ], $rows),
            [['Desde', $from], ['Hasta', $to], ['Moneda consolidada', secondary_currency()]],
            'libro-mayor',
            'L',
            ['L', 'L', 'R', 'L', 'R', 'R', 'R'],
            [
                ['Cuentas', (string) count($rows)],
                ['Total Debe ' . secondary_currency(), money($totalDebit)],
                ['Total Haber ' . secondary_currency(), money($totalCredit)],
                ['Saldo Neto ' . secondary_currency(), money($netBalance)],
            ]
        );
    }

    public function balanceSheet(): void
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $this->redirect('/reports?type=balance&from=' . rawurlencode($from) . '&to=' . rawurlencode($to));
    }

    public function balanceSheetPdf(): void
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-01'));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));
        [$from, $to] = $this->normalizeRange($from, $to);
        $currentRate = $this->currentRate($to);
        $payload = $this->balancePayload($from, $to, $currentRate);

        (new PdfService())->balanceSheet(
            'Balance general',
            $payload['balance'],
            [['Desde', $from], ['Hasta', $to], ['Moneda consolidada', secondary_currency()], ['Tasa actual', money($currentRate)]],
            'balance-general',
            $payload['balanceOverview'] ?? [],
            $payload['balanceLabels'] ?? [],
            (string) ($payload['balanceDisclaimer'] ?? ''),
            $currentRate
        );
    }

    private function resolveReport(string $type, string $from, string $to, float $currentRate, string $inventoryBreakdown = 'category'): array
    {
        return match ($type) {
            'delivery_notes' => $this->deliveryNotesPayload($from, $to, $currentRate),
            'purchases' => $this->purchasesPayload($from, $to, $currentRate),
            'expenses' => $this->expensesPayload($from, $to, $currentRate),
            'receivables' => $this->receivablesPayload($from, $to, $currentRate),
            'payables' => $this->payablesPayload($from, $to, $currentRate),
            'inventory' => $this->inventoryPayload($from, $to, $inventoryBreakdown),
            'movements' => $this->movementsPayload($from, $to),
            'treasury' => $this->treasuryPayload($from, $to, $currentRate),
            'journal' => $this->journalPayload($from, $to, $currentRate),
            'ledger' => $this->ledgerPayload($from, $to, $currentRate),
            'balance' => $this->balancePayload($from, $to, $currentRate),
            default => $this->salesPayload($from, $to, $currentRate),
        };
    }

    private function salesPayload(string $from, string $to, float $currentRate): array
    {
        $reports = new ReportsData();
        $records = array_map(fn (array $row): array => [
            'date' => (string) $row['invoice_date'],
            'reference' => (string) $row['invoice_number'],
            'party' => (string) ($row['client_name'] ?? 'Sin cliente'),
            'currency_code' => (string) ($row['currency_code'] ?? ''),
            'exchange_rate' => (float) ($row['exchange_rate'] ?? 0),
            'original_amount' => (float) ($row['total_original'] ?? 0),
            'base_amount' => (float) ($row['total_converted'] ?? 0),
        ], $reports->sales($from, $to));
        $productSummary = array_map(fn (array $row): array => [
            'sku' => (string) ($row['sku'] ?? ''),
            'product_label' => product_display_name((string) ($row['product_name'] ?? ''), (string) ($row['category_name'] ?? '')),
            'unit_label' => product_unit_label([
                'unit_label' => (string) ($row['unit_label'] ?? ''),
                'product_type' => (string) ($row['product_type'] ?? 'merchandise'),
            ]),
            'quantity' => (float) ($row['total_quantity'] ?? 0),
            'document_count' => (int) ($row['document_count'] ?? 0),
        ], $reports->salesProductQuantities($from, $to));

        $payload = $this->buildDocumentsPayload(
            'Ventas',
            'Facturacion emitida en el periodo, mostrando moneda del documento y total consolidado en moneda base.',
            '/reports/pdf?type=sales&from=' . rawurlencode($from) . '&to=' . rawurlencode($to),
            $records,
            $currentRate
        );

        $totalQuantity = array_reduce($productSummary, fn (float $carry, array $row): float => $carry + (float) ($row['quantity'] ?? 0), 0.0);
        $productCount = count($productSummary);
        $leadProduct = $productSummary[0] ?? null;

        $payload['productSummary'] = $productSummary;
        $payload['productSummaryOverview'] = [
            'total_quantity' => $totalQuantity,
            'product_count' => $productCount,
            'lead_product' => (string) ($leadProduct['product_label'] ?? ''),
            'lead_quantity' => (float) ($leadProduct['quantity'] ?? 0),
            'lead_unit_label' => (string) ($leadProduct['unit_label'] ?? 'und'),
        ];
        $payload['summaryCards'][] = $this->summaryCard('Unidades vendidas', money($totalQuantity), 'Suma de cantidades facturadas en el periodo.');
        $payload['summaryCards'][] = $this->summaryCard('Productos vendidos', (string) $productCount, 'Productos distintos con salida en facturacion.');
        if ($leadProduct !== null) {
            $payload['infoCards'][] = $this->summaryCard(
                'Producto lider',
                money((float) ($leadProduct['quantity'] ?? 0)) . ' ' . (string) ($leadProduct['unit_label'] ?? 'und'),
                (((string) ($leadProduct['sku'] ?? '')) !== '' ? 'SKU ' . (string) ($leadProduct['sku'] ?? '') . ' | ' : '')
                . (string) ($leadProduct['product_label'] ?? '')
            );
        }
        $payload['pdfSummary'][] = ['Unidades vendidas', money($totalQuantity)];
        $payload['pdfSummary'][] = ['Productos vendidos', (string) $productCount];
        $payload['pdfSections'] = $productSummary === []
            ? []
            : [[
                'title' => 'Cantidades vendidas por producto',
                'headers' => ['SKU', 'Producto', 'Unidad', 'Cantidad', 'Facturas'],
                'widths' => [28, 86, 18, 24, 20],
                'alignments' => ['L', 'L', 'L', 'R', 'R'],
                'rows' => array_map(fn (array $row): array => [
                    (string) ($row['sku'] !== '' ? $row['sku'] : 'Sin SKU'),
                    (string) ($row['product_label'] ?? ''),
                    (string) ($row['unit_label'] ?? 'und'),
                    money($row['quantity'] ?? 0),
                    (string) ($row['document_count'] ?? 0),
                ], $productSummary),
                'summary' => [
                    ['Unidades vendidas', money($totalQuantity)],
                    ['Productos vendidos', (string) $productCount],
                ],
            ]];

        return $payload;
    }

    private function deliveryNotesPayload(string $from, string $to, float $currentRate): array
    {
        $records = array_map(fn (array $row): array => [
            'date' => (string) $row['note_date'],
            'reference' => (string) $row['note_number'],
            'party' => (string) ($row['client_name'] ?? 'Sin cliente'),
            'currency_code' => (string) ($row['currency_code'] ?? ''),
            'exchange_rate' => (float) ($row['exchange_rate'] ?? 0),
            'original_amount' => (float) ($row['total_original'] ?? 0),
            'base_amount' => (float) ($row['total_converted'] ?? 0),
            'quantity' => (float) ($row['total_quantity'] ?? 0),
            'line_count' => (int) ($row['line_count'] ?? 0),
        ], (new ReportsData())->deliveryNotes($from, $to));

        $payload = $this->buildDocumentsPayload(
            'Notas de entrega',
            'Despachos operativos registrados en el periodo. Este reporte muestra la salida comercial documentada y no sustituye la facturacion fiscal.',
            '/reports/pdf?type=delivery_notes&from=' . rawurlencode($from) . '&to=' . rawurlencode($to),
            $records,
            $currentRate
        );

        $totalQuantity = array_reduce($records, fn (float $carry, array $row): float => $carry + (float) ($row['quantity'] ?? 0), 0.0);
        $payload['summaryCards'][] = $this->summaryCard('Unidades despachadas', money($totalQuantity), 'Total de cantidades registradas en las notas.');
        $payload['infoCards'][] = $this->summaryCard('Lectura contable', 'Venta sin IVA', 'Las notas ya alimentan tesoreria y contabilidad como venta operativa sin debito fiscal.');
        $payload['pdfSummary'][] = ['Unidades', money($totalQuantity)];

        return $payload;
    }

    private function purchasesPayload(string $from, string $to, float $currentRate): array
    {
        $records = array_map(function (array $row): array {
            $currency = strtoupper((string) ($row['currency_code'] ?? ''));
            $rate = (float) ($row['exchange_rate'] ?? 0);
            $quantity = (float) ($row['quantity'] ?? 0);
            $costOriginal = (float) ($row['cost_original'] ?? 0);
            $lineOriginal = (float) ($row['total_original'] ?? 0);

            return [
                'date' => (string) $row['purchase_date'],
                'reference' => (string) $row['doc_number'],
                'party' => (string) ($row['supplier_name'] ?? 'Sin proveedor'),
                'product_name' => (string) ($row['product_name'] ?? 'Sin producto'),
                'currency_code' => $currency,
                'exchange_rate' => $rate,
                'quantity' => $quantity,
                'cost_usd' => $this->amountToUsd($costOriginal, $currency, $rate),
                'line_usd' => $this->amountToUsd($lineOriginal, $currency, $rate),
                'line_ves' => $this->amountToVes($lineOriginal, $currency, $rate),
            ];
        }, (new ReportsData())->purchaseDetails($from, $to));

        $documents = [];
        $totalUnits = 0.0;
        $totalUsd = 0.0;
        $totalVes = 0.0;

        foreach ($records as $row) {
            $documents[$row['reference']] = true;
            $totalUnits += (float) $row['quantity'];
            $totalUsd += (float) $row['line_usd'];
            $totalVes += (float) $row['line_ves'];
        }

        return [
            'title' => 'Compras',
            'description' => 'Detalle por producto comprado. Cada renglon muestra cantidad, costo en USD y su equivalente en bolivares usando la tasa fija con la que se cerro la compra.',
            'mode' => 'purchase_lines',
            'records' => $records,
            'summaryCards' => [
                $this->summaryCard('Documentos', (string) count($documents), 'Compras encontradas en el rango.'),
                $this->summaryCard('Renglones', (string) count($records), 'Productos comprados dentro de esos documentos.'),
                $this->summaryCard('Unidades', money($totalUnits), 'Suma de cantidades compradas.'),
                $this->summaryCard('Total USD', money($totalUsd) . ' USD', 'Recalculado por renglon con la tasa de cierre.'),
            ],
            'infoCards' => [
                $this->summaryCard('Equivalente VES', money($totalVes) . ' VES', 'Calculado con la tasa guardada en cada compra.'),
                $this->summaryCard('Lectura', 'USD + VES fijo', 'No se usa la tasa actual para este reporte de compras.'),
            ],
            'pdfUrl' => '/reports/pdf?type=purchases&from=' . rawurlencode($from) . '&to=' . rawurlencode($to),
            'pdfTitle' => 'Compras detalladas',
            'headers' => ['Fecha', 'Documento', 'Proveedor', 'Producto', 'Cantidad', 'Moneda doc.', 'Tasa cierre', 'Costo USD', 'Total USD', 'Equiv. VES'],
            'widths' => [20, 24, 34, 52, 18, 18, 20, 22, 24, 28],
            'alignments' => ['L', 'L', 'L', 'L', 'R', 'L', 'R', 'R', 'R', 'R'],
            'rows' => array_map(fn (array $row): array => [
                (string) $row['date'],
                (string) $row['reference'],
                (string) $row['party'],
                (string) $row['product_name'],
                money($row['quantity']),
                (string) $row['currency_code'],
                money($row['exchange_rate']),
                money($row['cost_usd']),
                money($row['line_usd']),
                money($row['line_ves']),
            ], $records),
            'pdfSummary' => [
                ['Documentos', (string) count($documents)],
                ['Renglones', (string) count($records)],
                ['Unidades', money($totalUnits)],
                ['Total USD', money($totalUsd) . ' USD'],
                ['Equivalente VES', money($totalVes) . ' VES'],
            ],
        ];
    }

    private function expensesPayload(string $from, string $to, float $currentRate): array
    {
        $records = array_map(function (array $row): array {
            $currency = normalize_currency_code((string) ($row['currency_code'] ?? ''));
            $originalAmount = (float) ($row['amount_original'] ?? 0);

            return [
                'date' => (string) $row['expense_date'],
                'reference' => (string) $row['reference'],
                'party' => (string) ($row['category_name'] ?? 'Sin categoria'),
                'currency_code' => $currency,
                'exchange_rate' => (float) ($row['exchange_rate'] ?? 0),
                'original_amount' => $originalAmount,
                'base_amount' => is_bolivar_currency($currency) ? $originalAmount : (float) ($row['amount_converted'] ?? 0),
            ];
        }, (new ReportsData())->expenses($from, $to));

        return $this->buildDocumentsPayload(
            'Gastos',
            'Egresos operativos del periodo, mostrando el monto original del documento y su consolidado en moneda base.',
            '/reports/pdf?type=expenses&from=' . rawurlencode($from) . '&to=' . rawurlencode($to),
            $records,
            $currentRate
        );
    }

    private function receivablesPayload(string $from, string $to, float $currentRate): array
    {
        $records = (new ReportsData())->accountsReceivable($from, $to);
        return $this->buildOpenItemsPayload(
            'Cuentas por cobrar',
            'Facturas con saldo pendiente dentro del rango consultado. Se muestran vencimiento, saldo abierto y dias de atraso para gestionar cobranza real.',
            '/reports/pdf?type=receivables&from=' . rawurlencode($from) . '&to=' . rawurlencode($to),
            $records,
            $currentRate,
            'client_name',
            'invoice_number'
        );
    }

    private function payablesPayload(string $from, string $to, float $currentRate): array
    {
        $records = (new ReportsData())->accountsPayable($from, $to);
        return $this->buildOpenItemsPayload(
            'Cuentas por pagar',
            'Compras con saldo pendiente dentro del rango consultado. Este reporte sirve para ordenar pagos a proveedores y detectar vencimientos reales.',
            '/reports/pdf?type=payables&from=' . rawurlencode($from) . '&to=' . rawurlencode($to),
            $records,
            $currentRate,
            'supplier_name',
            'doc_number'
        );
    }

    private function treasuryPayload(string $from, string $to, float $currentRate): array
    {
        $reportsData = new ReportsData();
        $records = $reportsData->treasury($from, $to, $currentRate);
        $treasuryMovements = $reportsData->treasuryMovements($from, $to);
        $groupedTotals = [];
        $periodIn = 0.0;
        $periodOut = 0.0;
        $totalReporting = 0.0;
        $totalReference = 0.0;

        foreach ($records as $row) {
            $currency = (string) ($row['currency_code'] ?? 'N/A');
            $groupedTotals[$currency] = ($groupedTotals[$currency] ?? 0.0) + (float) ($row['balance_original'] ?? 0);
            $periodIn += (float) ($row['period_in_reporting'] ?? 0);
            $periodOut += (float) ($row['period_out_reporting'] ?? 0);
            $totalReporting += (float) ($row['balance_reporting'] ?? 0);
            $totalReference += (float) ($row['balance_reference'] ?? 0);
        }

        $summaryCards = [
            $this->moneyCard('Tesoreria consolidada', $totalReporting, $currentRate, 'Saldo disponible total expresado en ' . secondary_currency() . '.'),
            $this->summaryCard('Equivalente en ' . base_currency(), money($totalReference) . ' ' . base_currency(), 'Conversion calculada con la tasa del corte.'),
            $this->moneyCard('Entradas del periodo', $periodIn, $currentRate, 'Cobros y entradas registradas entre las fechas.'),
            $this->moneyCard('Salidas del periodo', $periodOut, $currentRate, 'Pagos y egresos registrados entre las fechas.'),
        ];

        $infoCards = [];
        foreach ($groupedTotals as $currency => $amount) {
            $infoCards[] = $this->summaryCard(
                'Saldo en ' . $currency,
                money($amount) . ' ' . $currency,
                'Disponible en cuentas de tesoreria para esa moneda.'
            );
        }

        return [
            'title' => 'Tesoreria multimoneda',
            'description' => 'Disponibilidad real por metodo y moneda segun cobros, pagos y gastos ya registrados. El consolidado se muestra en una moneda, pero cada cuenta conserva su saldo original y su equivalente.',
            'mode' => 'treasury_accounts',
            'records' => $records,
            'treasuryMovements' => $treasuryMovements,
            'summaryCards' => $summaryCards,
            'infoCards' => $infoCards,
            'pdfUrl' => '/reports/pdf?type=treasury&from=' . rawurlencode($from) . '&to=' . rawurlencode($to),
            'pdfTitle' => 'Tesoreria multimoneda',
            'headers' => ['Cuenta', 'Metodo', 'Moneda', 'Saldo original', 'Equiv. VES', 'Equiv. USD', 'Entradas', 'Salidas', 'Neto periodo'],
            'widths' => [44, 26, 16, 24, 24, 24, 22, 22, 24],
            'alignments' => ['L', 'L', 'L', 'R', 'R', 'R', 'R', 'R', 'R'],
            'rows' => array_map(fn (array $row): array => [
                (string) ($row['account_name'] ?? ''),
                payment_method_label((string) ($row['method_type'] ?? '')),
                (string) ($row['currency_code'] ?? ''),
                money($row['balance_original'] ?? 0),
                money($row['balance_reporting'] ?? 0),
                money($row['balance_reference'] ?? 0),
                money($row['period_in_original'] ?? 0),
                money($row['period_out_original'] ?? 0),
                money($row['period_net_original'] ?? 0),
            ], $records),
            'pdfSummary' => [
                ['Equiv. total ' . secondary_currency(), money($totalReporting)],
                ['Equiv. total ' . base_currency(), money($totalReference)],
                ['Entradas ' . secondary_currency(), money($periodIn)],
                ['Salidas ' . secondary_currency(), money($periodOut)],
            ],
        ];
    }

    private function buildDocumentsPayload(string $title, string $description, string $pdfUrl, array $records, float $currentRate): array
    {
        $totalBase = array_reduce($records, fn (float $carry, array $row): float => $carry + (float) ($row['base_amount'] ?? 0), 0.0);

        return [
            'title' => $title,
            'description' => $description,
            'mode' => 'documents',
            'records' => $records,
            'summaryCards' => [
                $this->summaryCard('Registros', (string) count($records), 'Documentos encontrados en el rango.'),
                $this->moneyCard('Total consolidado', $totalBase, $currentRate, 'Consolidado en ' . secondary_currency() . '.'),
                $this->summaryCard('Lectura', 'Original + consolidado', 'Cada fila muestra monto del documento y monto consolidado en bolivares.'),
            ],
            'infoCards' => [
                $this->summaryCard('Moneda de referencia', base_currency(), 'La moneda del documento puede ser distinta al consolidado financiero.'),
            ],
            'pdfUrl' => $pdfUrl,
            'pdfTitle' => $title,
            'headers' => ['Fecha', 'Referencia', 'Tercero', 'Moneda', 'Tasa', 'Monto doc.', 'Consolidado ' . secondary_currency()],
            'widths' => [26, 34, 48, 18, 18, 26, 26],
            'alignments' => ['L', 'L', 'L', 'L', 'R', 'R', 'R'],
            'rows' => array_map(fn (array $row): array => [
                (string) $row['date'],
                (string) $row['reference'],
                (string) $row['party'],
                (string) $row['currency_code'],
                money($row['exchange_rate']),
                money($row['original_amount']),
                money($row['base_amount']),
            ], $records),
            'pdfSummary' => [
                ['Registros', (string) count($records)],
                ['Total consolidado ' . secondary_currency(), money($totalBase)],
            ],
        ];
    }

    private function buildOpenItemsPayload(
        string $title,
        string $description,
        string $pdfUrl,
        array $records,
        float $currentRate,
        string $partyKey,
        string $referenceKey
    ): array {
        $totalBalance = array_reduce($records, fn (float $carry, array $row): float => $carry + (float) ($row['balance_converted'] ?? 0), 0.0);
        $overdueCount = count(array_filter($records, fn (array $row): bool => in_array((string) ($row['payment_status_effective'] ?? ''), ['overdue', 'partial_overdue'], true)));

        return [
            'title' => $title,
            'description' => $description,
            'mode' => 'open_items',
            'records' => $records,
            'summaryCards' => [
                $this->summaryCard('Documentos abiertos', (string) count($records), 'Documentos con saldo pendiente.'),
                $this->moneyCard('Saldo abierto', $totalBalance, $currentRate, 'Total pendiente en moneda consolidada.'),
                $this->summaryCard('Vencidos', (string) $overdueCount, 'Documentos ya fuera de plazo.'),
            ],
            'infoCards' => [
                $this->summaryCard('Corte', 'Hasta la fecha final consultada', 'El estado se basa en saldo pendiente y vencimiento del documento.'),
            ],
            'pdfUrl' => $pdfUrl,
            'pdfTitle' => $title,
            'headers' => ['Fecha', 'Vence', 'Referencia', 'Tercero', 'Moneda', 'Total doc.', 'Abonado', 'Saldo', 'Dias atraso', 'Estado'],
            'widths' => [20, 20, 28, 42, 16, 24, 24, 24, 18, 24],
            'alignments' => ['L', 'L', 'L', 'L', 'L', 'R', 'R', 'R', 'R', 'L'],
            'rows' => array_map(fn (array $row): array => [
                (string) ($row['invoice_date'] ?? $row['note_date'] ?? $row['purchase_date'] ?? $row['document_date'] ?? ''),
                (string) ($row['due_date'] ?? ''),
                (string) ($row[$referenceKey] ?? ''),
                (string) ($row[$partyKey] ?? ''),
                (string) ($row['currency_code'] ?? ''),
                money($row['total_original'] ?? 0),
                money($row['amount_paid_original'] ?? 0),
                money($row['balance_original'] ?? 0),
                (string) ($row['days_overdue'] ?? 0),
                $this->paymentStatusLabel((string) ($row['payment_status_effective'] ?? 'pending')),
            ], $records),
            'pdfSummary' => [
                ['Documentos abiertos', (string) count($records)],
                ['Saldo abierto', money($totalBalance)],
                ['Vencidos', (string) $overdueCount],
            ],
        ];
    }

    private function inventoryPayload(string $from, string $to, string $breakdown = 'category'): array
    {
        $records = array_map(fn (array $row): array => [
            'sku' => (string) ($row['sku'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'category_name' => (string) ($row['category_name'] ?? 'Sin categoria'),
            'stock' => (float) ($row['stock'] ?? 0),
            'stock_min' => (float) ($row['stock_min'] ?? 0),
            'unit_label' => product_unit_label($row),
            'currency_code' => (string) ($row['currency_code'] ?? ''),
            'cost' => (float) ($row['cost'] ?? 0),
            'inventory_total' => (float) ($row['inventory_total'] ?? 0),
        ], (new ReportsData())->inventoryValued());

        $records = array_map(function (array $row): array {
            $row['category_name'] = trim((string) ($row['category_name'] ?? '')) !== '' ? (string) $row['category_name'] : 'Sin categoria';
            $row['is_critical'] = (float) ($row['stock'] ?? 0) <= (float) ($row['stock_min'] ?? 0);
            return $row;
        }, $records);

        usort($records, function (array $left, array $right): int {
            $categoryCompare = strcasecmp((string) ($left['category_name'] ?? ''), (string) ($right['category_name'] ?? ''));
            if ($categoryCompare !== 0) {
                return $categoryCompare;
            }

            $skuCompare = strcasecmp((string) ($left['sku'] ?? ''), (string) ($right['sku'] ?? ''));
            if ($skuCompare !== 0) {
                return $skuCompare;
            }

            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        $filteredRecords = $records;
        if ($breakdown === 'stock_desc') {
            usort($filteredRecords, function (array $left, array $right): int {
                $stockCompare = (float) ($right['stock'] ?? 0) <=> (float) ($left['stock'] ?? 0);
                if ($stockCompare !== 0) {
                    return $stockCompare;
                }

                return strcasecmp((string) ($left['sku'] ?? ''), (string) ($right['sku'] ?? ''));
            });
        } elseif ($breakdown === 'critical') {
            $filteredRecords = array_values(array_filter($records, static fn (array $row): bool => (bool) ($row['is_critical'] ?? false)));
        } elseif ($breakdown === 'value_desc') {
            usort($filteredRecords, function (array $left, array $right): int {
                $valueCompare = (float) ($right['inventory_total'] ?? 0) <=> (float) ($left['inventory_total'] ?? 0);
                if ($valueCompare !== 0) {
                    return $valueCompare;
                }

                return strcasecmp((string) ($left['sku'] ?? ''), (string) ($right['sku'] ?? ''));
            });
        }

        $overview = $this->buildInventoryOverview($filteredRecords);
        $groups = $breakdown === 'category' ? $this->buildInventoryCategoryGroups($filteredRecords) : [];
        $recap = array_map(static fn (array $group): array => [
            'category_name' => (string) ($group['category_name'] ?? 'Sin categoria'),
            'product_count' => (int) ($group['product_count'] ?? 0),
            'units' => (float) ($group['units'] ?? 0),
            'critical_count' => (int) ($group['critical_count'] ?? 0),
            'totals_text' => (string) ($group['totals_text'] ?? ''),
        ], $groups);

        $summaryCards = [
            $this->summaryCard('Productos en vista', (string) count($filteredRecords), 'Registros visibles segun el desglose seleccionado.'),
            $this->summaryCard('Categorias', (string) count(array_unique(array_map(static fn (array $row): string => (string) ($row['category_name'] ?? 'Sin categoria'), $filteredRecords))), 'Categorias presentes en la consulta actual.'),
            $this->summaryCard('Criticos', (string) $overview['critical_count'], 'Productos con existencia igual o por debajo del minimo.'),
            $this->summaryCard('Existencia total', money($overview['units']), 'Suma de existencias de la vista actual.'),
        ];

        $infoCards = [[
            'label' => 'Desglose activo',
            'value' => $this->inventoryBreakdownOptions()[$breakdown] ?? 'Por categoria',
            'hint' => 'Puedes cambiarlo desde el selector del reporte de inventario.',
        ]];
        foreach ($overview['currency_totals'] as $currency => $amount) {
            $infoCards[] = [
                'label' => 'Total registrado en ' . $currency,
                'value' => money($amount) . ' ' . $currency,
                'hint' => 'Costo actual multiplicado por existencia.',
            ];
        }

        $pdfRows = $breakdown === 'category'
            ? $this->buildInventoryGroupedPdfRows($groups)
            : array_map(fn (array $row): array => [
                $row['sku'],
                $row['name'],
                money($row['stock']) . ' ' . $row['unit_label'],
                money($row['stock_min']) . ' ' . $row['unit_label'],
                $row['currency_code'],
                money($row['cost']),
                money($row['inventory_total']),
            ], $filteredRecords);

        $pdfSummary = [
            ['Productos', (string) count($filteredRecords)],
            ['Categorias', (string) count(array_unique(array_map(static fn (array $row): string => (string) ($row['category_name'] ?? 'Sin categoria'), $filteredRecords)))],
            ['Criticos', (string) $overview['critical_count']],
            ['Existencia total', money($overview['units'])],
        ];
        foreach ($overview['currency_totals'] as $currency => $amount) {
            $pdfSummary[] = ['Total ' . $currency, money($amount) . ' ' . $currency];
        }

        $description = match ($breakdown) {
            'stock_desc' => 'Inventario ordenado de mayor a menor existencia para detectar rapidamente donde esta concentrado el stock.',
            'critical' => 'Inventario filtrado solo a productos criticos de stock para facilitar reposicion y seguimiento.',
            'value_desc' => 'Inventario ordenado por mayor valor registrado dentro de la moneda original de cada producto.',
            default => 'Vista global del inventario separada por categorias, con resumen individual por rubro y un recuento general al final.',
        };

        return [
            'title' => 'Inventario global',
            'description' => $description,
            'mode' => 'inventory',
            'records' => $filteredRecords,
            'inventoryBreakdown' => $breakdown,
            'inventoryBreakdownLabel' => $this->inventoryBreakdownOptions()[$breakdown] ?? 'Por categoria',
            'inventoryBreakdownOptions' => $this->inventoryBreakdownOptions(),
            'inventoryGroups' => $groups,
            'inventoryOverview' => $overview,
            'inventoryRecap' => $recap,
            'summaryCards' => $summaryCards,
            'infoCards' => $infoCards,
            'pdfUrl' => '/reports/pdf?type=inventory&from=' . rawurlencode($from) . '&to=' . rawurlencode($to) . '&inventory_view=' . rawurlencode($breakdown),
            'pdfTitle' => 'Inventario global',
            'headers' => ['SKU', 'Producto', 'Existencia', 'Minimo', 'Moneda', 'Costo', 'Total'],
            'widths' => [24, 72, 24, 24, 18, 22, 28],
            'alignments' => ['L', 'L', 'R', 'R', 'L', 'R', 'R'],
            'rows' => $pdfRows,
            'pdfSummary' => $pdfSummary,
        ];
    }

    private function normalizeInventoryBreakdown(string $value): string
    {
        $value = trim(strtolower($value));
        return array_key_exists($value, $this->inventoryBreakdownOptions()) ? $value : 'category';
    }

    private function sanitizeInventoryChart(string $value): string
    {
        $value = strtolower(trim($value));
        return array_key_exists($value, $this->inventoryChartOptions()) ? $value : 'all';
    }

    private function inventoryChartOptions(): array
    {
        return [
            'all' => 'Todas las graficas',
            'highest_stock' => 'Mayor stock',
            'lowest_stock' => 'Menor stock',
            'highest_movement' => 'Mayor movimiento',
            'highest_value' => 'Mayor valor de inventario',
        ];
    }

    private function productStockRanking(string $direction): array
    {
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $types = product_stock_managed_types();
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $statement = Database::connection()->prepare(
            "SELECT p.id, p.sku, p.name, p.product_type, p.stock, p.stock_min, p.unit_label, p.currency_code, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.deleted_at IS NULL
               AND COALESCE(p.status, 'active') = 'active'
               AND COALESCE(p.product_type, 'merchandise') IN ({$placeholders})
             ORDER BY p.stock {$direction}, p.name ASC"
        );

        foreach ($types as $index => $type) {
            $statement->bindValue($index + 1, $type);
        }
        $statement->execute();

        return $statement->fetchAll();
    }

    private function productMovementRanking(string $from, string $to): array
    {
        $types = array_values(array_filter(product_saleable_types(), static fn (string $type): bool => $type !== 'service'));
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $statement = Database::connection()->prepare(
            "SELECT
                p.id,
                p.sku,
                p.name,
                p.product_type,
                p.stock,
                p.stock_min,
                p.unit_label,
                c.name AS category_name,
                COALESCE(SUM(ABS(m.quantity)), 0) AS movement_quantity,
                COALESCE(SUM(CASE WHEN m.quantity > 0 THEN m.quantity ELSE 0 END), 0) AS incoming_quantity,
                COALESCE(SUM(CASE WHEN m.quantity < 0 THEN ABS(m.quantity) ELSE 0 END), 0) AS outgoing_quantity,
                COALESCE(SUM(CASE WHEN m.movement_type = 'production_in' THEN m.quantity ELSE 0 END), 0) AS produced_quantity,
                COALESCE(SUM(CASE WHEN m.movement_type = 'purchase' THEN m.quantity ELSE 0 END), 0) AS purchased_quantity,
                COALESCE(SUM(CASE WHEN m.movement_type IN ('sale', 'delivery_note') THEN ABS(m.quantity) ELSE 0 END), 0) AS sold_quantity,
                COALESCE(SUM(CASE WHEN m.movement_type IN ('adjustment_in', 'adjustment_out', 'initial') THEN ABS(m.quantity) ELSE 0 END), 0) AS adjustment_quantity
             FROM products p
             LEFT JOIN inventory_movements m ON m.product_id = p.id
                AND DATE(m.created_at) BETWEEN ? AND ?
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.deleted_at IS NULL
               AND COALESCE(p.status, 'active') = 'active'
               AND COALESCE(p.product_type, 'merchandise') IN ({$placeholders})
             GROUP BY p.id, p.sku, p.name, p.product_type, p.stock, p.stock_min, p.unit_label, c.name
             ORDER BY movement_quantity DESC, p.name ASC"
        );

        $position = 1;
        $statement->bindValue($position++, $from);
        $statement->bindValue($position++, $to);
        foreach ($types as $type) {
            $statement->bindValue($position++, $type);
        }
        $statement->execute();

        return $statement->fetchAll();
    }

    private function productValueRanking(float $rate): array
    {
        $products = (new Product())->stockManagedList('p.name ASC, c.name ASC, p.id ASC');
        $ranked = [];

        foreach ($products as $product) {
            $stock = (float) ($product['stock'] ?? 0);
            $cost = (float) ($product['cost'] ?? 0);
            $currency = (string) ($product['currency_code'] ?? base_currency());
            $originalValue = $stock * $cost;
            $ranked[] = [
                ...$product,
                'product_type' => (string) ($product['product_type'] ?? 'merchandise'),
                'inventory_value_original' => $originalValue,
                'inventory_value_base' => amount_to_reference_currency($originalValue, $currency, $rate),
                'inventory_value_secondary' => equivalent_in_bolivars($originalValue, $currency, $rate),
            ];
        }

        usort(
            $ranked,
            static fn (array $left, array $right): int => ((float) ($right['inventory_value_base'] ?? 0)) <=> ((float) ($left['inventory_value_base'] ?? 0))
        );

        return $ranked;
    }

    private function inventoryBreakdownOptions(): array
    {
        return [
            'category' => 'Por categoria',
            'stock_desc' => 'Mayor a menor stock',
            'critical' => 'Productos criticos',
            'value_desc' => 'Mayor valor registrado',
        ];
    }

    private function buildInventoryOverview(array $records): array
    {
        $units = array_reduce($records, fn (float $carry, array $row): float => $carry + (float) ($row['stock'] ?? 0), 0.0);
        $criticalCount = array_reduce($records, fn (int $carry, array $row): int => $carry + (((bool) ($row['is_critical'] ?? false)) ? 1 : 0), 0);
        $currencyTotals = [];
        foreach ($records as $row) {
            $currency = (string) ($row['currency_code'] ?? 'N/A');
            $currency = $currency !== '' ? $currency : 'N/A';
            $currencyTotals[$currency] = ($currencyTotals[$currency] ?? 0.0) + (float) ($row['inventory_total'] ?? 0);
        }

        ksort($currencyTotals);

        return [
            'product_count' => count($records),
            'category_count' => count(array_unique(array_map(static fn (array $row): string => (string) ($row['category_name'] ?? 'Sin categoria'), $records))),
            'critical_count' => $criticalCount,
            'units' => $units,
            'currency_totals' => $currencyTotals,
            'totals_text' => $this->formatInventoryCurrencyTotals($currencyTotals),
        ];
    }

    private function buildInventoryCategoryGroups(array $records): array
    {
        $groups = [];
        foreach ($records as $row) {
            $category = (string) ($row['category_name'] ?? 'Sin categoria');
            if (!isset($groups[$category])) {
                $groups[$category] = [
                    'category_name' => $category,
                    'records' => [],
                    'product_count' => 0,
                    'critical_count' => 0,
                    'units' => 0.0,
                    'currency_totals' => [],
                    'totals_text' => '',
                ];
            }

            $groups[$category]['records'][] = $row;
            $groups[$category]['product_count'] += 1;
            $groups[$category]['critical_count'] += !empty($row['is_critical']) ? 1 : 0;
            $groups[$category]['units'] += (float) ($row['stock'] ?? 0);
            $currency = (string) ($row['currency_code'] ?? 'N/A');
            $currency = $currency !== '' ? $currency : 'N/A';
            $groups[$category]['currency_totals'][$currency] = ($groups[$category]['currency_totals'][$currency] ?? 0.0) + (float) ($row['inventory_total'] ?? 0);
        }

        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($groups as &$group) {
            usort($group['records'], function (array $left, array $right): int {
                $skuCompare = strcasecmp((string) ($left['sku'] ?? ''), (string) ($right['sku'] ?? ''));
                if ($skuCompare !== 0) {
                    return $skuCompare;
                }

                return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            });
            ksort($group['currency_totals']);
            $group['totals_text'] = $this->formatInventoryCurrencyTotals($group['currency_totals']);
        }
        unset($group);

        return array_values($groups);
    }

    private function formatInventoryCurrencyTotals(array $totals): string
    {
        if ($totals === []) {
            return 'Sin monto registrado';
        }

        $parts = [];
        foreach ($totals as $currency => $amount) {
            $parts[] = money((float) $amount) . ' ' . $currency;
        }

        return implode(' | ', $parts);
    }

    private function buildInventoryGroupedPdfRows(array $groups): array
    {
        $rows = [];
        foreach ($groups as $group) {
            $rows[] = [
                '',
                'Categoria: ' . (string) ($group['category_name'] ?? 'Sin categoria'),
                '',
                '',
                '',
                '',
                '',
            ];

            foreach (($group['records'] ?? []) as $row) {
                $rows[] = [
                    (string) ($row['sku'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    money((float) ($row['stock'] ?? 0)) . ' ' . (string) ($row['unit_label'] ?? 'und'),
                    money((float) ($row['stock_min'] ?? 0)) . ' ' . (string) ($row['unit_label'] ?? 'und'),
                    (string) ($row['currency_code'] ?? ''),
                    money((float) ($row['cost'] ?? 0)),
                    money((float) ($row['inventory_total'] ?? 0)),
                ];
            }

            $rows[] = [
                '',
                'Resumen: ' . (int) ($group['product_count'] ?? 0) . ' productos | Criticos: ' . (int) ($group['critical_count'] ?? 0),
                money((float) ($group['units'] ?? 0)),
                '',
                '',
                '',
                (string) ($group['totals_text'] ?? ''),
            ];
        }

        return $rows;
    }

    private function movementsPayload(string $from, string $to): array
    {
        $records = array_map(fn (array $row): array => [
            'created_at' => (string) ($row['created_at'] ?? ''),
            'product_name' => (string) ($row['product_name'] ?? ''),
            'category_name' => (string) ($row['category_name'] ?? ''),
            'product_label' => product_display_name(
                (string) ($row['product_name'] ?? ''),
                (string) ($row['category_name'] ?? '')
            ),
            'warehouse_name' => (string) ($row['warehouse_name'] ?? 'Sin almacen'),
            'movement_type' => (string) ($row['movement_type'] ?? ''),
            'quantity' => (float) ($row['quantity'] ?? 0),
            'reference' => (string) ($row['reference'] ?? ''),
        ], (new ReportsData())->inventoryMovements($from, $to));

        $entries = array_reduce($records, fn (float $carry, array $row): float => $carry + max(0.0, (float) $row['quantity']), 0.0);
        $outputs = array_reduce($records, fn (float $carry, array $row): float => $carry + abs(min(0.0, (float) $row['quantity'])), 0.0);

        return [
            'title' => 'Movimientos de inventario',
            'description' => 'Trazabilidad de entradas, salidas y ajustes con producto, almacen y referencia operativa.',
            'mode' => 'movements',
            'records' => $records,
            'summaryCards' => [
                $this->summaryCard('Registros', (string) count($records), 'Movimientos en el rango consultado.'),
                $this->summaryCard('Entradas', money($entries), 'Cantidades positivas acumuladas.'),
                $this->summaryCard('Salidas', money($outputs), 'Cantidades negativas acumuladas.'),
            ],
            'infoCards' => [
                $this->summaryCard('Balance neto', money($entries - $outputs), 'Entradas menos salidas del rango.'),
            ],
            'pdfUrl' => '/reports/pdf?type=movements&from=' . rawurlencode($from) . '&to=' . rawurlencode($to),
            'pdfTitle' => 'Movimientos de inventario',
            'headers' => ['Fecha', 'Producto', 'Almacen', 'Tipo', 'Cantidad', 'Referencia'],
            'widths' => [34, 52, 34, 28, 20, 40],
            'alignments' => ['L', 'L', 'L', 'L', 'R', 'L'],
            'rows' => array_map(fn (array $row): array => [
                $row['created_at'],
                $row['product_label'],
                $row['warehouse_name'],
                $row['movement_type'],
                money($row['quantity']),
                $row['reference'],
            ], $records),
            'pdfSummary' => [
                ['Registros', (string) count($records)],
                ['Entradas', money($entries)],
                ['Salidas', money($outputs)],
                ['Balance', money($entries - $outputs)],
            ],
        ];
    }

    private function journalPayload(string $from, string $to, float $currentRate): array
    {
        $records = (new ReportsData())->journal($from, $to);
        $totalDebit = array_reduce($records, fn (float $carry, array $row): float => $carry + (float) $row['debit'], 0.0);
        $totalCredit = array_reduce($records, fn (float $carry, array $row): float => $carry + (float) $row['credit'], 0.0);

        return [
            'title' => 'Libro diario',
            'description' => 'Asientos derivados de facturas, notas de entrega, compras, tesoreria y gastos reales del sistema. El diario ahora reconoce la cuenta de tesoreria usada y deja visibles las diferencias cambiarias cuando cobranza y liquidacion no coinciden al mismo tipo de cambio.',
            'mode' => 'journal_entries',
            'records' => $records,
            'summaryCards' => [
                $this->summaryCard('Renglones', (string) count($records), 'Lineas contables derivadas de documentos reales.'),
                $this->moneyCard('Debe acumulado', $totalDebit, $currentRate, 'Cargos integrados en moneda consolidada.'),
                $this->moneyCard('Haber acumulado', $totalCredit, $currentRate, 'Abonos integrados en moneda consolidada.'),
            ],
            'infoCards' => [
                $this->summaryCard('Cobertura', 'Ventas, entregas, tesoreria, compras y gastos', 'Toda la tesoreria registrada ya alimenta el diario con su cuenta especifica.'),
            ],
            'pdfUrl' => '/reports/journal/pdf?from=' . rawurlencode($from) . '&to=' . rawurlencode($to),
        ];
    }

    private function ledgerPayload(string $from, string $to, float $currentRate): array
    {
        $records = (new ReportsData())->ledger($from, $to);
        $totalDebit = array_reduce($records, fn (float $carry, array $row): float => $carry + (float) $row['debit'], 0.0);
        $totalCredit = array_reduce($records, fn (float $carry, array $row): float => $carry + (float) $row['credit'], 0.0);
        $netBalance = array_reduce($records, fn (float $carry, array $row): float => $carry + (float) ($row['balance'] ?? 0), 0.0);

        return [
            'title' => 'Libro mayor',
            'description' => 'Resumen por cuenta de los renglones contables generados por la operacion real. Aqui se ve cuanto cargo, cuanto abono y el saldo neto de cada cuenta operacional.',
            'mode' => 'ledger_accounts',
            'records' => $records,
            'summaryCards' => [
                $this->summaryCard('Cuentas', (string) count($records), 'Cuentas operativas con movimiento en el periodo.'),
                $this->moneyCard('Debe acumulado', $totalDebit, $currentRate, 'Total debitado entre todas las cuentas.'),
                $this->moneyCard('Saldo neto', $netBalance, $currentRate, 'Sumatoria de saldos por cuenta en moneda consolidada.'),
            ],
            'infoCards' => [
                $this->summaryCard('Interpretacion', 'Saldo = Debe menos Haber', 'Un saldo negativo indica predominio de abonos en esa cuenta.'),
            ],
            'pdfUrl' => '/reports/ledger/pdf?from=' . rawurlencode($from) . '&to=' . rawurlencode($to),
        ];
    }

    private function balancePayload(string $from, string $to, float $currentRate): array
    {
        $balance = (new ReportsData())->balanceSheet($from, $to);
        $overview = is_array($balance['overview'] ?? null) ? $balance['overview'] : [];
        $assetsTotal = $this->sumAmounts($balance['assets'] ?? []);
        $liabilitiesTotal = $this->sumAmounts($balance['liabilities'] ?? []);
        $equityTotal = $this->sumAmounts($balance['equity'] ?? []);
        $sales = (float) ($overview['sales'] ?? 0);
        $purchases = (float) ($overview['purchases'] ?? 0);
        $expenses = (float) ($overview['expenses'] ?? 0);
        $outflows = (float) ($overview['outflows'] ?? ($purchases + $expenses));
        $estimatedResult = (float) ($overview['estimated_result'] ?? 0);
        $cashAvailable = (float) ($overview['cash_available'] ?? 0);
        $receivables = (float) ($overview['receivables'] ?? 0);
        $payables = (float) ($overview['payables'] ?? 0);

        return [
            'title' => 'Balance general',
            'description' => 'Resumen claro del periodo: cuanto vendiste, cuanto salio en compras y gastos, que resultado estimado queda y cuanto dinero disponible hay en tesoreria.',
            'mode' => 'balance',
            'balance' => $balance,
            'summaryCards' => [
                $this->moneyCard('Disponible', $cashAvailable, $currentRate, 'Saldo en caja y bancos segun tesoreria.'),
                $this->moneyCard('Resultado estimado', $estimatedResult, $currentRate, 'Ventas menos compras y gastos del periodo.'),
                $this->moneyCard('Salidas', $outflows, $currentRate, 'Compras mas gastos registrados.'),
            ],
            'infoCards' => [
                $this->moneyCard('Ventas del periodo', $sales, $currentRate, 'Facturas y notas de entrega registradas.'),
                $this->moneyCard('Compras', $purchases, $currentRate, 'Mercancia o insumos comprados en el periodo.'),
                $this->moneyCard('Gastos', $expenses, $currentRate, 'Egresos operativos registrados.'),
                $this->moneyCard('Por cobrar', $receivables, $currentRate, 'Facturas y notas pendientes de cobro.'),
                $this->moneyCard('Por pagar', $payables, $currentRate, 'Compras pendientes de pago.'),
            ],
            'pdfUrl' => '/reports/balance-sheet/pdf?from=' . rawurlencode($from) . '&to=' . rawurlencode($to),
            'balanceLabels' => [
                'assets' => 'Activos integrados',
                'liabilities' => 'Pasivos integrados',
                'equity' => 'Patrimonio operativo',
            ],
            'balanceDisclaimer' => 'La lectura principal es operativa: disponible, ventas, compras, gastos y resultado estimado. Abajo queda el desglose contable para auditar de donde salen los saldos.',
            'balanceOverview' => [
                'sales' => $sales,
                'purchases' => $purchases,
                'expenses' => $expenses,
                'outflows' => $outflows,
                'estimated_result' => $estimatedResult,
                'cash_available' => $cashAvailable,
                'receivables' => $receivables,
                'payables' => $payables,
                'inventory_value' => (float) ($overview['inventory_value'] ?? 0),
                'assets_total' => $assetsTotal,
                'liabilities_total' => $liabilitiesTotal,
                'equity_total' => $equityTotal,
            ],
        ];
    }

    private function currentRate(?string $date = null): float
    {
        return system_exchange_rate($date ?? date('Y-m-d'));
    }

    private function normalizeType(string $type): string
    {
        return in_array($type, self::VALID_TYPES, true) ? $type : 'sales';
    }

    private function sanitizeDate(string $value): string
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date !== false ? $date->format('Y-m-d') : date('Y-m-d');
    }

    private function normalizeRange(string $from, string $to): array
    {
        if (strtotime($from) > strtotime($to)) {
            return [$to, $from];
        }

        return [$from, $to];
    }

    private function sumAmounts(array $rows): float
    {
        return array_reduce(
            $rows,
            fn (float $carry, array $row): float => $carry + (float) ($row['amount'] ?? 0),
            0.0
        );
    }

    private function summaryCard(string $label, string $value, string $hint = ''): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'hint' => $hint,
        ];
    }

    private function moneyCard(string $label, float $consolidatedAmount, float $currentRate, string $hint = ''): array
    {
        $referenceAmount = convert_currency_amount($consolidatedAmount, secondary_currency(), base_currency(), $currentRate);
        $fullHint = '~ ' . money($referenceAmount) . ' ' . base_currency();

        if ($hint !== '') {
            $fullHint .= ' | ' . $hint;
        }

        return [
            'label' => $label,
            'value' => money($consolidatedAmount) . ' ' . secondary_currency(),
            'hint' => $fullHint,
        ];
    }

    private function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Pagada',
            'partial' => 'Parcial',
            'overdue' => 'Vencida',
            'partial_overdue' => 'Parcial vencida',
            'cancelled' => 'Anulada',
            default => 'Pendiente',
        };
    }

    private function amountToUsd(float $amount, string $currency, float $rate): float
    {
        $currency = strtoupper(trim($currency));

        if ($currency === 'USD') {
            return $amount;
        }

        if ($currency === 'VES') {
            return $rate > 0 ? ($amount / $rate) : 0.0;
        }

        return $amount;
    }

    private function amountToVes(float $amount, string $currency, float $rate): float
    {
        $currency = strtoupper(trim($currency));

        if ($currency === 'VES') {
            return $amount;
        }

        if ($currency === 'USD') {
            return $amount * $rate;
        }

        return $amount;
    }
}
