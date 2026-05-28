<?php
declare(strict_types=1);

namespace App\Services;

require_once dirname(__DIR__) . '/Libraries/fpdf/fpdf.php';

use FPDF;

class PdfService
{
    private const C_TEXT = [28, 37, 48];
    private const C_TEXT_SOFT = [92, 107, 122];
    private const C_LINE = [223, 228, 235];
    private const C_HEADER = [18, 32, 47];
    private const C_ACCENT = [46, 109, 164];
    private const C_PANEL = [247, 249, 251];
    private const C_WHITE = [255, 255, 255];

    public function invoice(array $invoice): void
    {
        $pdf = $this->makeDocument('Factura', (string) ($invoice['invoice_number'] ?? ''));

        $meta = [
            ['Numero', (string) ($invoice['invoice_number'] ?? '')],
            ['Fecha', (string) ($invoice['invoice_date'] ?? '')],
            ['Cliente', (string) ($invoice['client_name'] ?? '')],
            ['Documento', (string) ($invoice['client_document'] ?? '')],
        ];

        if (!empty($invoice['exchange_rate']) && (string) ($invoice['currency_code'] ?? '') !== (string) base_currency()) {
            $meta[] = ['Tasa', $this->money($invoice['exchange_rate'] ?? 0)];
        }

        $this->renderMetaGrid($pdf, $meta);
        $this->renderTable(
            $pdf,
            ['Concepto', 'Cant.', 'Precio', 'Importe'],
            [92, 22, 32, 34],
            array_map(static fn (array $item): array => [
                (string) ($item['product_name'] ?? ''),
                number_format((float) ($item['quantity'] ?? 0), 2, ',', '.'),
                number_format((float) ($item['price_original'] ?? 0), 2, ',', '.'),
                number_format((float) ($item['total_original'] ?? 0), 2, ',', '.'),
            ], $invoice['items'] ?? []),
            ['L', 'R', 'R', 'R']
        );

        $totals = [
            ['Subtotal', $this->money($invoice['subtotal_original'] ?? 0) . ' ' . (string) ($invoice['currency_code'] ?? '')],
            ['Impuesto', $this->money($invoice['tax_original'] ?? 0) . ' ' . (string) ($invoice['currency_code'] ?? '')],
            ['Total', $this->money($invoice['total_original'] ?? 0) . ' ' . (string) ($invoice['currency_code'] ?? '')],
        ];

        if ((string) ($invoice['currency_code'] ?? '') !== (string) base_currency()) {
            $totals[] = ['Total ' . (string) base_currency(), $this->money($invoice['total_converted'] ?? 0)];
        }

        $this->renderTotalsBlock($pdf, $totals);
        $this->renderNotesBlock($pdf, 'Observaciones', (string) ($invoice['notes'] ?? ''));

        $pdf->Output('I', $this->safeFileName('factura-' . ($invoice['invoice_number'] ?? 'documento')) . '.pdf');
    }

    public function deliveryNote(array $note): void
    {
        $pdf = $this->makeDocument('Nota de entrega', (string) ($note['note_number'] ?? ''));

        $meta = [
            ['Numero', (string) ($note['note_number'] ?? '')],
            ['Fecha', (string) ($note['note_date'] ?? '')],
            ['Cliente', (string) ($note['client_name'] ?? '')],
            ['Documento', (string) ($note['client_document'] ?? '')],
        ];

        if (!empty($note['exchange_rate']) && (string) ($note['currency_code'] ?? '') !== (string) base_currency()) {
            $meta[] = ['Tasa', $this->money($note['exchange_rate'] ?? 0)];
        }

        $this->renderMetaGrid($pdf, $meta);
        $this->renderTable(
            $pdf,
            ['Concepto', 'Cant.', 'Precio', 'Importe'],
            [92, 22, 32, 34],
            array_map(static fn (array $item): array => [
                (string) ($item['product_name'] ?? ''),
                number_format((float) ($item['quantity'] ?? 0), 2, ',', '.'),
                number_format((float) ($item['price_original'] ?? 0), 2, ',', '.'),
                number_format((float) ($item['total_original'] ?? 0), 2, ',', '.'),
            ], $note['items'] ?? []),
            ['L', 'R', 'R', 'R']
        );

        $totals = [
            ['Total', $this->money($note['total_original'] ?? 0) . ' ' . (string) ($note['currency_code'] ?? '')],
        ];

        if ((string) ($note['currency_code'] ?? '') !== (string) base_currency()) {
            $totals[] = ['Total ' . (string) base_currency(), $this->money($note['total_converted'] ?? 0)];
        }

        $this->renderTotalsBlock($pdf, $totals);
        $this->renderNotesBlock($pdf, 'Observaciones', (string) ($note['notes'] ?? ''));

        $pdf->Output('I', $this->safeFileName('nota-' . ($note['note_number'] ?? 'documento')) . '.pdf');
    }

    public function purchase(array $purchase): void
    {
        $pdf = $this->makeDocument('Compra', (string) ($purchase['doc_number'] ?? ''));

        $meta = [
            ['Documento', (string) ($purchase['doc_number'] ?? '')],
            ['Fecha', (string) ($purchase['purchase_date'] ?? '')],
            ['Proveedor', (string) ($purchase['supplier_name'] ?? '')],
            ['Documento fiscal', (string) ($purchase['supplier_document'] ?? '')],
        ];

        if (!empty($purchase['exchange_rate']) && (string) ($purchase['currency_code'] ?? '') !== (string) base_currency()) {
            $meta[] = ['Tasa', $this->money($purchase['exchange_rate'] ?? 0)];
        }

        $this->renderMetaGrid($pdf, $meta);
        $this->renderTable(
            $pdf,
            ['Concepto', 'Cant.', 'Costo', 'Importe'],
            [92, 22, 32, 34],
            array_map(static fn (array $item): array => [
                (string) ($item['product_name'] ?? ''),
                number_format((float) ($item['quantity'] ?? 0), 2, ',', '.'),
                number_format((float) ($item['cost_original'] ?? 0), 2, ',', '.'),
                number_format((float) ($item['total_original'] ?? 0), 2, ',', '.'),
            ], $purchase['items'] ?? []),
            ['L', 'R', 'R', 'R']
        );

        $totals = [
            ['Total', $this->money($purchase['total_original'] ?? 0) . ' ' . (string) ($purchase['currency_code'] ?? '')],
        ];

        if ((string) ($purchase['currency_code'] ?? '') !== (string) base_currency()) {
            $totals[] = ['Total ' . (string) base_currency(), $this->money($purchase['total_converted'] ?? 0)];
        }

        $this->renderTotalsBlock($pdf, $totals);
        $this->renderNotesBlock($pdf, 'Notas', (string) ($purchase['notes'] ?? ''));

        $pdf->Output('I', $this->safeFileName('compra-' . ($purchase['doc_number'] ?? 'documento')) . '.pdf');
    }

    public function balanceSheet(
        string $title,
        array $balance,
        array $meta,
        string $fileName,
        array $overview = [],
        array $sectionLabels = [],
        string $disclaimer = '',
        ?float $currentRate = null
    ): void
    {
        $pdf = $this->makeDocument($title, 'Resumen operativo y financiero', 'L');
        $this->renderMetaGrid($pdf, $meta);
        $reportingCurrency = (string) secondary_currency();
        $referenceCurrency = (string) base_currency();
        $currentRate = $currentRate ?? system_exchange_rate(date('Y-m-d'));
        $overview = is_array($balance['overview'] ?? null) && $overview === [] ? $balance['overview'] : $overview;
        $assetsTotal = $this->sumAmounts($balance['assets'] ?? []);
        $liabilitiesTotal = $this->sumAmounts($balance['liabilities'] ?? []);
        $equityTotal = $this->sumAmounts($balance['equity'] ?? []);

        $overview = array_merge([
            'sales' => 0.0,
            'purchases' => 0.0,
            'expenses' => 0.0,
            'outflows' => 0.0,
            'estimated_result' => 0.0,
            'cash_available' => 0.0,
            'receivables' => 0.0,
            'payables' => 0.0,
            'inventory_value' => 0.0,
            'assets_total' => $assetsTotal,
            'liabilities_total' => $liabilitiesTotal,
            'equity_total' => $equityTotal,
        ], $overview);

        if ($disclaimer !== '') {
            $this->renderNotesBlock($pdf, 'Lectura del reporte', $disclaimer);
        }

        $this->renderDashboardMetricCards($pdf, [
            ['Dinero disponible', $this->money($overview['cash_available']) . ' ' . $reportingCurrency, $this->referenceAmountText((float) $overview['cash_available'], $reportingCurrency, $referenceCurrency, $currentRate)],
            ['Vendiste', $this->money($overview['sales']) . ' ' . $reportingCurrency, $this->referenceAmountText((float) $overview['sales'], $reportingCurrency, $referenceCurrency, $currentRate)],
            ['Gastaste', $this->money($overview['outflows']) . ' ' . $reportingCurrency, 'Compras + gastos'],
            ['Resultado estimado', $this->money($overview['estimated_result']) . ' ' . $reportingCurrency, $this->referenceAmountText((float) $overview['estimated_result'], $reportingCurrency, $referenceCurrency, $currentRate)],
        ]);

        $this->renderSectionTitle($pdf, 'Lectura rapida');
        $this->renderTable(
            $pdf,
            ['Concepto', 'Monto ' . $reportingCurrency, 'Referencia ' . $referenceCurrency, 'Lectura'],
            [54, 34, 34, 132],
            [
                ['Ventas del periodo', $this->money($overview['sales']) . ' ' . $reportingCurrency, $this->referenceAmountText((float) $overview['sales'], $reportingCurrency, $referenceCurrency, $currentRate), 'Lo que facturaste o entregaste como venta.'],
                ['Compras', $this->money($overview['purchases']) . ' ' . $reportingCurrency, $this->referenceAmountText((float) $overview['purchases'], $reportingCurrency, $referenceCurrency, $currentRate), 'Mercancia, materiales o insumos comprados.'],
                ['Gastos', $this->money($overview['expenses']) . ' ' . $reportingCurrency, $this->referenceAmountText((float) $overview['expenses'], $reportingCurrency, $referenceCurrency, $currentRate), 'Egresos operativos registrados.'],
                ['Por cobrar', $this->money($overview['receivables']) . ' ' . $reportingCurrency, $this->referenceAmountText((float) $overview['receivables'], $reportingCurrency, $referenceCurrency, $currentRate), 'Dinero vendido que aun no ha entrado.'],
                ['Por pagar', $this->money($overview['payables']) . ' ' . $reportingCurrency, $this->referenceAmountText((float) $overview['payables'], $reportingCurrency, $referenceCurrency, $currentRate), 'Compromisos pendientes con proveedores.'],
                ['Inventario valorizado', $this->money($overview['inventory_value']) . ' ' . $reportingCurrency, $this->referenceAmountText((float) $overview['inventory_value'], $reportingCurrency, $referenceCurrency, $currentRate), 'Valor actual registrado en existencias.'],
            ],
            ['L', 'R', 'R', 'L']
        );

        foreach ([
            'assets' => $sectionLabels['assets'] ?? 'Activos integrados',
            'liabilities' => $sectionLabels['liabilities'] ?? 'Pasivos integrados',
            'equity' => $sectionLabels['equity'] ?? 'Patrimonio operativo',
        ] as $key => $label) {
            $rows = array_map(fn (array $row): array => [
                (string) ($row['name'] ?? ''),
                $this->money($row['amount'] ?? 0) . ' ' . $reportingCurrency,
                $this->referenceAmountText((float) ($row['amount'] ?? 0), $reportingCurrency, $referenceCurrency, $currentRate),
            ], $balance[$key] ?? []);

            $subtotal = $this->sumAmounts($balance[$key] ?? []);
            $rows[] = [
                'Subtotal',
                $this->money($subtotal) . ' ' . $reportingCurrency,
                $this->referenceAmountText($subtotal, $reportingCurrency, $referenceCurrency, $currentRate),
            ];

            $this->renderSectionTitle($pdf, $label);
            $this->renderTable($pdf, ['Concepto', 'Importe ' . $reportingCurrency, 'Referencia ' . $referenceCurrency], [138, 48, 48], $rows, ['L', 'R', 'R']);
        }

        $this->renderTotalsBlock($pdf, [
            ['Activos integrados', $this->money($assetsTotal) . ' ' . $reportingCurrency . ' | ' . $this->referenceAmountText($assetsTotal, $reportingCurrency, $referenceCurrency, $currentRate)],
            ['Pasivos integrados', $this->money($liabilitiesTotal) . ' ' . $reportingCurrency . ' | ' . $this->referenceAmountText($liabilitiesTotal, $reportingCurrency, $referenceCurrency, $currentRate)],
            ['Patrimonio operativo', $this->money($equityTotal) . ' ' . $reportingCurrency . ' | ' . $this->referenceAmountText($equityTotal, $reportingCurrency, $referenceCurrency, $currentRate)],
            ['Diferencia', $this->money($assetsTotal - ($liabilitiesTotal + $equityTotal)) . ' ' . $reportingCurrency],
        ], 134.0);

        $pdf->Output('I', $this->safeFileName($fileName) . '.pdf');
    }

    public function tableReport(
        string $title,
        array $headers,
        array $widths,
        array $rows,
        array $meta,
        string $fileName,
        string $orientation = 'L',
        array $alignments = [],
        array $summary = [],
        array $sections = []
    ): void {
        $pdf = $this->makeDocument($title, '', $orientation);

        $this->renderMetaGrid($pdf, $meta);
        if ($summary !== []) {
            $this->renderCompactSummary($pdf, $summary);
        }

        $resolvedWidths = $this->fitWidthsToPage($pdf, $widths);
        $resolvedAlignments = $this->resolveAlignments($headers, $rows, $alignments);

        $this->renderTable($pdf, $headers, $resolvedWidths, $rows, $resolvedAlignments);

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionRows = is_array($section['rows'] ?? null) ? $section['rows'] : [];
            $sectionHeaders = is_array($section['headers'] ?? null) ? $section['headers'] : [];
            $sectionWidths = is_array($section['widths'] ?? null) ? $section['widths'] : [];

            if ($sectionHeaders === [] || $sectionWidths === []) {
                continue;
            }

            $this->renderSectionTitle($pdf, (string) ($section['title'] ?? 'Detalle'));
            if (is_array($section['summary'] ?? null) && $section['summary'] !== []) {
                $this->renderCompactSummary($pdf, $section['summary']);
            }
            $this->renderTable(
                $pdf,
                $sectionHeaders,
                $sectionWidths,
                $sectionRows,
                is_array($section['alignments'] ?? null) ? $section['alignments'] : []
            );
        }

        $pdf->Output('I', $this->safeFileName($fileName) . '.pdf');
    }

    public function inventoryReport(
        string $title,
        array $meta,
        array $overview,
        array $groups,
        array $records,
        string $breakdownLabel,
        string $fileName
    ): void {
        $pdf = $this->makeDocument($title, '', 'L');
        $this->renderMetaGrid($pdf, $meta);

        $summary = [
            ['Desglose', $breakdownLabel],
            ['Productos', (string) ($overview['product_count'] ?? count($records))],
            ['Categorias', (string) ($overview['category_count'] ?? count($groups))],
            ['Criticos', (string) ($overview['critical_count'] ?? 0)],
            ['Existencia total', $this->money((float) ($overview['units'] ?? 0))],
        ];

        foreach (($overview['currency_totals'] ?? []) as $currency => $amount) {
            $summary[] = ['Total ' . (string) $currency, $this->money((float) $amount) . ' ' . (string) $currency];
        }

        $this->renderCompactSummary($pdf, $summary);

        $headers = ['SKU', 'Producto', 'Existencia', 'Minimo', 'Moneda', 'Costo', 'Total'];
        $widths = [24, 72, 24, 24, 18, 22, 28];
        $alignments = ['L', 'L', 'R', 'R', 'L', 'R', 'R'];

        if ($groups !== []) {
            foreach ($groups as $group) {
                $this->renderSectionTitle($pdf, (string) ($group['category_name'] ?? 'Sin categoria'));
                $this->renderCompactSummary($pdf, [
                    ['Productos', (string) ($group['product_count'] ?? 0)],
                    ['Existencia', $this->money((float) ($group['units'] ?? 0))],
                    ['Criticos', (string) ($group['critical_count'] ?? 0)],
                    ['Total categoria', (string) ($group['totals_text'] ?? 'Sin monto registrado')],
                ]);

                $rows = array_map(static fn (array $row): array => [
                    (string) ($row['sku'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    number_format((float) ($row['stock'] ?? 0), 2, ',', '.') . ' ' . (string) ($row['unit_label'] ?? 'und'),
                    number_format((float) ($row['stock_min'] ?? 0), 2, ',', '.') . ' ' . (string) ($row['unit_label'] ?? 'und'),
                    (string) ($row['currency_code'] ?? ''),
                    number_format((float) ($row['cost'] ?? 0), 2, ',', '.'),
                    number_format((float) ($row['inventory_total'] ?? 0), 2, ',', '.') . ' ' . (string) ($row['currency_code'] ?? ''),
                ], $group['records'] ?? []);

                $this->renderTable($pdf, $headers, $widths, $rows, $alignments);
            }

            $this->renderSectionTitle($pdf, 'Cierre general');
            $totals = [
                ['Categorias', (string) ($overview['category_count'] ?? count($groups))],
                ['Productos', (string) ($overview['product_count'] ?? count($records))],
                ['Criticos', (string) ($overview['critical_count'] ?? 0)],
                ['Existencia total', $this->money((float) ($overview['units'] ?? 0))],
            ];
            foreach (($overview['currency_totals'] ?? []) as $currency => $amount) {
                $totals[] = ['Total ' . (string) $currency, $this->money((float) $amount) . ' ' . (string) $currency];
            }
            $this->renderTotalsBlock($pdf, $totals, 124.0);
        } else {
            $this->renderSectionTitle($pdf, 'Detalle del inventario');
            $rows = array_map(static fn (array $row): array => [
                (string) ($row['sku'] ?? ''),
                (string) ($row['name'] ?? ''),
                number_format((float) ($row['stock'] ?? 0), 2, ',', '.') . ' ' . (string) ($row['unit_label'] ?? 'und'),
                number_format((float) ($row['stock_min'] ?? 0), 2, ',', '.') . ' ' . (string) ($row['unit_label'] ?? 'und'),
                (string) ($row['currency_code'] ?? ''),
                number_format((float) ($row['cost'] ?? 0), 2, ',', '.'),
                number_format((float) ($row['inventory_total'] ?? 0), 2, ',', '.') . ' ' . (string) ($row['currency_code'] ?? ''),
            ], $records);

            $this->renderTable($pdf, $headers, $widths, $rows, $alignments);
            $this->renderTotalsBlock($pdf, [
                ['Desglose', $breakdownLabel],
                ['Productos', (string) ($overview['product_count'] ?? count($records))],
                ['Criticos', (string) ($overview['critical_count'] ?? 0)],
                ['Existencia total', $this->money((float) ($overview['units'] ?? 0))],
                ['Total registrado', (string) ($overview['totals_text'] ?? 'Sin monto registrado')],
            ], 124.0);
        }

        $pdf->Output('I', $this->safeFileName($fileName) . '.pdf');
    }

    public function dashboard(array $payload): void
    {
        $from = (string) ($payload['from'] ?? date('Y-m-d'));
        $to = (string) ($payload['to'] ?? date('Y-m-d'));
        $stats = is_array($payload['stats'] ?? null) ? $payload['stats'] : [];
        $rate = is_array($payload['rate'] ?? null) ? $payload['rate'] : [];
        $cashFlow = is_array($payload['cashFlow'] ?? null) ? $payload['cashFlow'] : [];
        $composition = is_array($payload['composition'] ?? null) ? $payload['composition'] : [];
        $topProducts = is_array($payload['topProducts'] ?? null) ? $payload['topProducts'] : [];
        $alerts = is_array($payload['alerts'] ?? null) ? $payload['alerts'] : [];

        $baseCurrency = (string) base_currency();
        $secondaryCurrency = (string) secondary_currency();
        $exchangeRate = (float) ($rate['rate'] ?? system_exchange_rate($to));

        $pdf = $this->makeDocument('Panel principal', 'Resumen del negocio', 'L');
        $this->renderMetaGrid($pdf, [
            ['Periodo', $this->formatDate($from) . ' - ' . $this->formatDate($to)],
            ['Tasa vigente', '1 ' . $baseCurrency . ' = ' . $this->money($exchangeRate) . ' ' . $secondaryCurrency],
            ['Clientes', (string) (int) ($stats['clients'] ?? 0)],
            ['Proveedores', (string) (int) ($stats['suppliers'] ?? 0)],
        ]);

        $this->renderDashboardMetricCards($pdf, [
            ['Ventas', $this->money($stats['sales_base'] ?? 0) . ' ' . $baseCurrency, $this->money($stats['sales_secondary'] ?? $stats['sales'] ?? 0) . ' ' . $secondaryCurrency],
            ['Compras', $this->money($stats['purchases_base'] ?? 0) . ' ' . $baseCurrency, $this->money($stats['purchases_secondary'] ?? $stats['purchases'] ?? 0) . ' ' . $secondaryCurrency],
            ['Gastos', $this->money($stats['expenses_base'] ?? 0) . ' ' . $baseCurrency, $this->money($stats['expenses_secondary'] ?? $stats['expenses'] ?? 0) . ' ' . $secondaryCurrency],
            ['Inventario', $this->money($stats['inventory_value_base'] ?? 0) . ' ' . $baseCurrency, $this->money($stats['inventory_value_secondary'] ?? 0) . ' ' . $secondaryCurrency],
            ['Por cobrar', $this->money($stats['receivables_base'] ?? 0) . ' ' . $baseCurrency, $this->money($stats['receivables_secondary'] ?? $stats['receivables'] ?? 0) . ' ' . $secondaryCurrency],
            ['Por pagar', $this->money($stats['payables_base'] ?? 0) . ' ' . $baseCurrency, $this->money($stats['payables_secondary'] ?? $stats['payables'] ?? 0) . ' ' . $secondaryCurrency],
            ['Productos', (string) (int) ($stats['products'] ?? 0), 'Registrados'],
            ['Stock bajo', (string) (int) ($stats['low_stock'] ?? 0), 'Alertas activas'],
        ]);

        $this->renderDashboardLineChart(
            $pdf,
            'Flujo de caja',
            array_map('strval', $cashFlow['labels'] ?? []),
            [
                ['label' => 'Ventas', 'values' => array_map('floatval', $cashFlow['sales'] ?? []), 'color' => [47, 111, 104]],
                ['label' => 'Compras', 'values' => array_map('floatval', $cashFlow['purchases'] ?? []), 'color' => [96, 125, 155]],
                ['label' => 'Gastos', 'values' => array_map('floatval', $cashFlow['expenses'] ?? []), 'color' => [200, 149, 83]],
            ],
           // 'Montos en ' . $secondaryCurrency
        );

        $this->ensureSpace($pdf, 106);
        $left = $pdf->getLeftMargin();
        $top = $pdf->GetY();
        $gap = 8.0;
        $columnWidth = ($this->usableWidth($pdf) - $gap) / 2;
        $chartHeight = 96.0;

        $this->renderDashboardCompositionAt(
            $pdf,
            $left,
            $top,
            $columnWidth,
            $chartHeight,
            array_map('strval', $composition['labels'] ?? []),
            array_map('floatval', $composition['values'] ?? []),
            $secondaryCurrency,
            $baseCurrency,
            $exchangeRate
        );
        $this->renderDashboardTopProductsAt(
            $pdf,
            $left + $columnWidth + $gap,
            $top,
            $columnWidth,
            $chartHeight,
            $topProducts,
            $secondaryCurrency,
            $baseCurrency,
            $exchangeRate
        );
        $pdf->SetY($top + $chartHeight + 7);

        $this->renderSectionTitle($pdf, 'Stock bajo');
        $this->renderTable(
            $pdf,
            ['Producto', 'SKU', 'Existencia', 'Minimo'],
            [110, 45, 35, 35],
            array_map(static fn (array $row): array => [
                (string) ($row['name'] ?? ''),
                (string) ($row['sku'] ?? ''),
                number_format((float) ($row['stock'] ?? 0), 2, ',', '.'),
                number_format((float) ($row['stock_min'] ?? 0), 2, ',', '.'),
            ], array_slice($alerts, 0, 12)),
            ['L', 'L', 'R', 'R']
        );

        $pdf->Output('I', $this->safeFileName('panel-principal-' . $from . '-' . $to) . '.pdf');
    }

    public function inventoryCharts(array $payload): void
    {
        $chart = (string) ($payload['chart'] ?? 'all');
        $from = (string) ($payload['from'] ?? date('Y-m-01'));
        $to = (string) ($payload['to'] ?? date('Y-m-d'));
        $baseCurrency = (string) ($payload['base_currency'] ?? base_currency());
        $secondaryCurrency = (string) ($payload['secondary_currency'] ?? secondary_currency());
        $rate = (float) ($payload['rate'] ?? system_exchange_rate($to));

        $charts = [
            'highest_stock' => [
                'title' => 'Productos con mayor stock',
                'subtitle' => 'Existencia actual por producto',
                'rows' => $this->inventoryChartRows($payload['highest_stock'] ?? [], 'stock'),
            ],
            'lowest_stock' => [
                'title' => 'Productos con menor stock',
                'subtitle' => 'Existencia actual por producto',
                'rows' => $this->inventoryChartRows($payload['lowest_stock'] ?? [], 'stock'),
            ],
            'highest_movement' => [
                'title' => 'Productos vendibles con mayor movimiento',
                'subtitle' => 'Produccion, ventas y despachos del periodo',
                'rows' => $this->inventoryChartRows($payload['highest_movement'] ?? [], 'movement'),
            ],
            'highest_value' => [
                'title' => 'Productos con mayor valor',
                'subtitle' => 'Valor estimado en ' . $baseCurrency,
                'rows' => $this->inventoryChartRows($payload['highest_value'] ?? [], 'value', $baseCurrency, $secondaryCurrency),
            ],
        ];

        $selectedKeys = $chart === 'all' || !array_key_exists($chart, $charts)
            ? array_keys($charts)
            : [$chart];

        $pdf = $this->makeDocument('Graficas de inventario', (string) ($payload['chart_label'] ?? 'Rankings de productos'), 'L');
        $this->renderMetaGrid($pdf, [
            ['Periodo de movimientos', $this->formatDate($from) . ' - ' . $this->formatDate($to)],
            ['Productos', 'Todos los disponibles'],
            ['Tasa vigente', '1 ' . $baseCurrency . ' = ' . $this->money($rate) . ' ' . $secondaryCurrency],
            ['Exportacion', (string) ($payload['chart_label'] ?? 'Graficas')],
        ]);

        $this->renderNotesBlock(
            $pdf,
            'Cobertura',
            'Se incluyen todos los productos disponibles para cada ranking. Cuando la lista es larga, la grafica se divide en bloques consecutivos para mantener legibles las etiquetas y valores.'
        );

        foreach ($selectedKeys as $key) {
            $this->renderInventoryRankingChartPages(
                $pdf,
                $charts[$key]['title'],
                $charts[$key]['subtitle'],
                $charts[$key]['rows']
            );
        }

        $this->renderSectionTitle($pdf, 'Detalle completo de los rankings');
        foreach ($selectedKeys as $key) {
            $this->renderSectionTitle($pdf, $charts[$key]['title']);
            $this->renderTable(
                $pdf,
                ['Producto', 'SKU', 'Tipo', 'Valor', 'Lectura'],
                [74, 26, 32, 32, 92],
                array_map(static fn (array $row): array => [
                    (string) ($row['label'] ?? ''),
                    (string) ($row['sku'] ?? ''),
                    (string) ($row['type_label'] ?? ''),
                    (string) ($row['value_text'] ?? ''),
                    (string) ($row['detail'] ?? ''),
                ], $charts[$key]['rows']),
                ['L', 'L', 'L', 'R', 'L']
            );
        }

        $pdf->Output('I', $this->safeFileName('graficas-inventario-' . $chart . '-' . $from . '-' . $to) . '.pdf');
    }

    public function chartsReport(array $payload): void
    {
        $from = (string) ($payload['from'] ?? date('Y-m-01'));
        $to = (string) ($payload['to'] ?? date('Y-m-d'));
        $granularity = (string) ($payload['granularity'] ?? 'auto');
        $granularityLabel = match ($granularity) {
            'day' => 'Diaria',
            'week' => 'Semanal',
            'month' => 'Mensual',
            default => 'Automatica',
        };

        $salesByPeriod = is_array($payload['salesByPeriod'] ?? null) ? $payload['salesByPeriod'] : ['labels' => [], 'values' => []];
        $compareFlows = is_array($payload['compareFlows'] ?? null) ? $payload['compareFlows'] : ['labels' => [], 'sales' => [], 'purchases' => [], 'expenses' => []];
        $topProducts = is_array($payload['topProducts'] ?? null) ? $payload['topProducts'] : [];
        $topClients = is_array($payload['topClients'] ?? null) ? $payload['topClients'] : [];
        $abc = is_array($payload['abc'] ?? null) ? $payload['abc'] : ['rows' => [], 'classCount' => ['A' => 0, 'B' => 0, 'C' => 0]];
        $aging = is_array($payload['aging'] ?? null) ? $payload['aging'] : ['labels' => [], 'values' => [], 'total' => 0];
        $paymentMethods = is_array($payload['paymentMethods'] ?? null) ? $payload['paymentMethods'] : ['labels' => [], 'values' => []];
        $forecast = is_array($payload['forecast'] ?? null) ? $payload['forecast'] : ['labels' => [], 'historical' => [], 'trend' => [], 'forecast' => []];

        $baseCurrency = (string) base_currency();
        $secondaryCurrency = (string) secondary_currency();
        $exchangeRate = (float) ($payload['exchange_rate'] ?? system_exchange_rate($to));

        $totalSales = array_sum(array_map('floatval', $salesByPeriod['values'] ?? []));
        $totalPurchases = array_sum(array_map('floatval', $compareFlows['purchases'] ?? []));
        $totalExpenses = array_sum(array_map('floatval', $compareFlows['expenses'] ?? []));
        $totalAging = (float) ($aging['total'] ?? 0);

        $pdf = $this->makeDocument('Graficas analiticas', 'Resumen del periodo', 'L');
        $this->renderMetaGrid($pdf, [
            ['Periodo', $this->formatDate($from) . ' - ' . $this->formatDate($to)],
            ['Granularidad', $granularityLabel],
            ['Tasa vigente', '1 ' . $baseCurrency . ' = ' . $this->money($exchangeRate) . ' ' . $secondaryCurrency],
            ['Documentos analizados', 'Facturas + notas de entrega + compras + gastos'],
        ]);

        $this->renderDashboardMetricCards($pdf, [
            ['Ventas del periodo', $this->money(convert_currency_amount($totalSales, $secondaryCurrency, $baseCurrency, $exchangeRate)) . ' ' . $baseCurrency, $this->money($totalSales) . ' ' . $secondaryCurrency],
            ['Compras del periodo', $this->money(convert_currency_amount($totalPurchases, $secondaryCurrency, $baseCurrency, $exchangeRate)) . ' ' . $baseCurrency, $this->money($totalPurchases) . ' ' . $secondaryCurrency],
            ['Gastos del periodo', $this->money(convert_currency_amount($totalExpenses, $secondaryCurrency, $baseCurrency, $exchangeRate)) . ' ' . $baseCurrency, $this->money($totalExpenses) . ' ' . $secondaryCurrency],
            ['Por cobrar al cierre', $this->money(convert_currency_amount($totalAging, $secondaryCurrency, $baseCurrency, $exchangeRate)) . ' ' . $baseCurrency, $this->money($totalAging) . ' ' . $secondaryCurrency],
            ['Productos con venta', (string) count($abc['rows'] ?? []), 'Clase A: ' . (int) ($abc['classCount']['A'] ?? 0)],
            ['Top clientes', (string) count($topClients), 'Clase B: ' . (int) ($abc['classCount']['B'] ?? 0)],
        ]);

        $this->renderDashboardLineChart(
            $pdf,
            'Ventas globales por periodo',
            array_map('strval', $salesByPeriod['labels'] ?? []),
            [
                ['label' => 'Ventas', 'values' => array_map('floatval', $salesByPeriod['values'] ?? []), 'color' => [47, 111, 104]],
            ],
            'Granularidad ' . $granularityLabel . ' | montos en ' . $secondaryCurrency
        );

        $this->renderDashboardLineChart(
            $pdf,
            'Comparativo: Ventas vs Compras vs Gastos',
            array_map('strval', $compareFlows['labels'] ?? []),
            [
                ['label' => 'Ventas', 'values' => array_map('floatval', $compareFlows['sales'] ?? []), 'color' => [47, 111, 104]],
                ['label' => 'Compras', 'values' => array_map('floatval', $compareFlows['purchases'] ?? []), 'color' => [37, 99, 235]],
                ['label' => 'Gastos', 'values' => array_map('floatval', $compareFlows['expenses'] ?? []), 'color' => [200, 149, 83]],
            ],
            'Montos en ' . $secondaryCurrency
        );

        // Fila: Top productos + Top clientes
        $this->ensureSpace($pdf, 110);
        $left = $pdf->getLeftMargin();
        $top = $pdf->GetY();
        $gap = 8.0;
        $columnWidth = ($this->usableWidth($pdf) - $gap) / 2;
        $chartHeight = 100.0;

        $this->renderChartsRankingAt(
            $pdf,
            $left,
            $top,
            $columnWidth,
            $chartHeight,
            'Top productos',
            'Por monto facturado (' . $secondaryCurrency . ')',
            array_map(static fn (array $row): array => [
                'label' => (string) ($row['name'] ?? 'Producto'),
                'value' => (float) ($row['total'] ?? 0),
                'meta' => number_format((float) ($row['quantity'] ?? 0), 2, ',', '.') . ' und.',
            ], array_slice($topProducts, 0, 10)),
            [47, 111, 104]
        );

        $this->renderChartsRankingAt(
            $pdf,
            $left + $columnWidth + $gap,
            $top,
            $columnWidth,
            $chartHeight,
            'Top clientes',
            'Por facturacion (' . $secondaryCurrency . ')',
            array_map(static fn (array $row): array => [
                'label' => (string) ($row['name'] ?? 'Cliente'),
                'value' => (float) ($row['total'] ?? 0),
                'meta' => (int) ($row['documents'] ?? 0) . ' docs.',
            ], array_slice($topClients, 0, 10)),
            [96, 125, 155]
        );

        $pdf->SetY($top + $chartHeight + 7);

        // ABC
        $abcRows = $abc['rows'] ?? [];
        $abcRowsForChart = array_map(static function (array $row): array {
            $color = match ($row['class'] ?? 'C') {
                'A' => [47, 111, 104],
                'B' => [200, 149, 83],
                default => [148, 163, 184],
            };
            return [
                'label' => (string) ($row['name'] ?? ''),
                'value' => (float) ($row['total'] ?? 0),
                'meta' => 'Clase ' . ($row['class'] ?? 'C') . ' | ' . number_format(((float) ($row['cumulative'] ?? 0)) * 100, 1, ',', '.') . '% acum.',
                'color' => $color,
            ];
        }, array_slice($abcRows, 0, 14));

        $abcSubtitle = 'A: ' . (int) ($abc['classCount']['A'] ?? 0)
            . ' | B: ' . (int) ($abc['classCount']['B'] ?? 0)
            . ' | C: ' . (int) ($abc['classCount']['C'] ?? 0)
            . ' | Montos en ' . $secondaryCurrency;

        $this->ensureSpace($pdf, 120);
        $left = $pdf->getLeftMargin();
        $top = $pdf->GetY();
        $abcWidth = $this->usableWidth($pdf);
        $abcHeight = 110.0;
        $this->renderChartsRankingAt(
            $pdf,
            $left,
            $top,
            $abcWidth,
            $abcHeight,
            'Analisis ABC',
            $abcSubtitle,
            $abcRowsForChart,
            [47, 111, 104]
        );
        $pdf->SetY($top + $abcHeight + 7);

        // Fila: Aging + Metodos de pago
        $this->ensureSpace($pdf, 110);
        $left = $pdf->getLeftMargin();
        $top = $pdf->GetY();
        $columnWidth = ($this->usableWidth($pdf) - $gap) / 2;
        $chartHeight = 100.0;

        $this->renderChartsCompositionAt(
            $pdf,
            $left,
            $top,
            $columnWidth,
            $chartHeight,
            'Antiguedad de cuentas por cobrar',
            'Saldos pendientes al ' . $this->formatDate($to),
            array_map('strval', $aging['labels'] ?? []),
            array_map('floatval', $aging['values'] ?? []),
            $secondaryCurrency,
            $baseCurrency,
            $exchangeRate,
            [[47, 111, 104], [200, 149, 83], [217, 119, 6], [196, 95, 95], [127, 29, 29]]
        );

        $this->renderChartsCompositionAt(
            $pdf,
            $left + $columnWidth + $gap,
            $top,
            $columnWidth,
            $chartHeight,
            'Ventas por metodo de pago',
            'Cobros aplicados en el periodo',
            array_map('strval', $paymentMethods['labels'] ?? []),
            array_map('floatval', $paymentMethods['values'] ?? []),
            $secondaryCurrency,
            $baseCurrency,
            $exchangeRate,
            [[47, 111, 104], [96, 125, 155], [200, 149, 83], [14, 165, 233], [168, 85, 247], [249, 115, 22]]
        );

        $pdf->SetY($top + $chartHeight + 7);

        // Pronostico
        $this->renderDashboardLineChart(
            $pdf,
            'Prediccion de ventas',
            array_map('strval', $forecast['labels'] ?? []),
            [
                ['label' => 'Historico', 'values' => array_map(static fn ($v) => $v === null ? 0.0 : (float) $v, $forecast['historical'] ?? []), 'color' => [47, 111, 104]],
                ['label' => 'Tendencia', 'values' => array_map(static fn ($v) => $v === null ? 0.0 : (float) $v, $forecast['trend'] ?? []), 'color' => [200, 149, 83]],
                ['label' => 'Pronostico', 'values' => array_map(static fn ($v) => $v === null ? 0.0 : (float) $v, $forecast['forecast'] ?? []), 'color' => [196, 95, 95]],
            ],
            'Regresion lineal sobre 12 meses, proyectada 3 meses (' . $secondaryCurrency . ')'
        );

        // Detalle ABC en tabla
        if ($abcRows !== []) {
            $this->renderSectionTitle($pdf, 'Detalle del analisis ABC');
            $this->renderTable(
                $pdf,
                ['#', 'Producto', 'SKU', 'Monto', '% individual', '% acumulado', 'Clase'],
                [12, 88, 32, 38, 32, 32, 22],
                array_map(static function (array $row, int $index): array {
                    return [
                        (string) ($index + 1),
                        (string) ($row['name'] ?? ''),
                        (string) ($row['sku'] ?? ''),
                        number_format((float) ($row['total'] ?? 0), 2, ',', '.'),
                        number_format(((float) ($row['share'] ?? 0)) * 100, 2, ',', '.') . '%',
                        number_format(((float) ($row['cumulative'] ?? 0)) * 100, 2, ',', '.') . '%',
                        (string) ($row['class'] ?? 'C'),
                    ];
                }, array_slice($abcRows, 0, 30), array_keys(array_slice($abcRows, 0, 30))),
                ['C', 'L', 'L', 'R', 'R', 'R', 'C']
            );
        }

        $pdf->Output('I', $this->safeFileName('graficas-' . $from . '-' . $to) . '.pdf');
    }

    private function renderChartsRankingAt(MinimalPdfDocument $pdf, float $x, float $y, float $width, float $height, string $title, string $subtitle, array $rows, array $defaultColor): void
    {
        $this->drawChartPanel($pdf, $x, $y, $width, $height, $title, $subtitle);

        if ($rows === []) {
            $this->drawEmptyChartMessage($pdf, $x, $y, $width, $height);
            return;
        }

        $max = max(0.0, ...array_map(static fn (array $row): float => (float) ($row['value'] ?? 0), $rows));
        if ($max <= 0.0) {
            $this->drawEmptyChartMessage($pdf, $x, $y, $width, $height);
            return;
        }

        $labelWidth = $width * 0.32;
        $barX = $x + 6 + $labelWidth;
        $barWidth = $width - $labelWidth - 18;
        $availableHeight = max(40.0, $height - 24);
        $rowHeight = max(5.6, min(9.5, $availableHeight / max(1, count($rows))));
        $rowY = $y + 19;

        foreach ($rows as $row) {
            if ($rowY + $rowHeight > $y + $height - 3) {
                break;
            }

            $value = (float) ($row['value'] ?? 0);
            $color = is_array($row['color'] ?? null) ? $row['color'] : $defaultColor;
            $fillWidth = $barWidth * ($value / $max);
            $meta = (string) ($row['meta'] ?? '');
            $valueText = $this->money($value);

            $pdf->SetXY($x + 6, $rowY);
            $pdf->SetFont('Helvetica', '', 7.4);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($labelWidth - 2, 4.2, $this->truncateText($pdf, (string) ($row['label'] ?? ''), $labelWidth - 3), 0, 0, 'L');

            $pdf->SetFillColor(235, 239, 244);
            $pdf->Rect($barX, $rowY + 0.6, $barWidth, 3.8, 'F');
            $pdf->SetFillColor(...$color);
            $pdf->Rect($barX, $rowY + 0.6, $fillWidth, 3.8, 'F');

            $pdf->SetXY($barX, $rowY + 4.6);
            $pdf->SetFont('Helvetica', '', 6.9);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $detail = $valueText . ($meta !== '' ? ' | ' . $meta : '');
            $pdf->Cell($barWidth, 3, $this->truncateText($pdf, $detail, $barWidth - 1), 0, 0, 'R');

            $rowY += $rowHeight;
        }
    }

    private function renderChartsCompositionAt(MinimalPdfDocument $pdf, float $x, float $y, float $width, float $height, string $title, string $subtitle, array $labels, array $values, string $currency, string $referenceCurrency, float $rate, array $palette): void
    {
        $this->drawChartPanel($pdf, $x, $y, $width, $height, $title, $subtitle);

        $sum = array_sum(array_map('floatval', $values));
        if ($sum <= 0.0) {
            $this->drawEmptyChartMessage($pdf, $x, $y, $width, $height);
            return;
        }

        $barX = $x + 7;
        $barY = $y + 22;
        $barWidth = $width - 14;
        $barHeight = 10;
        $cursor = $barX;

        foreach ($values as $index => $value) {
            $segmentWidth = $barWidth * ((float) $value / $sum);
            $pdf->SetFillColor(...($palette[$index % count($palette)]));
            $pdf->Rect($cursor, $barY, $segmentWidth, $barHeight, 'F');
            $cursor += $segmentWidth;
        }

        $pdf->SetDrawColor(...self::C_LINE);
        $pdf->Rect($barX, $barY, $barWidth, $barHeight);

        $rowY = $barY + 14;
        foreach ($labels as $index => $label) {
            $value = (float) ($values[$index] ?? 0);
            $percent = $sum > 0 ? ($value / $sum) * 100 : 0;
            $reference = convert_currency_amount($value, $currency, $referenceCurrency, $rate);

            $pdf->SetFillColor(...($palette[$index % count($palette)]));
            $pdf->Rect($barX, $rowY + 1.2, 3.2, 3.2, 'F');
            $pdf->SetXY($barX + 5, $rowY);
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($barWidth * 0.32, 5, $this->truncateText($pdf, (string) $label, ($barWidth * 0.32) - 1), 0, 0, 'L');
            $pdf->SetFont('Helvetica', 'B', 7.5);
            $pdf->SetTextColor(...self::C_TEXT);
            $pdf->Cell($barWidth * 0.3, 5, $this->money($value) . ' ' . $currency, 0, 0, 'R');
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($barWidth * 0.26, 5, '~ ' . $this->money($reference) . ' ' . $referenceCurrency, 0, 0, 'R');
            $pdf->Cell($barWidth * 0.12, 5, number_format($percent, 1, ',', '.') . '%', 0, 1, 'R');

            $rowY += 5.6;
            if ($rowY > $y + $height - 3) {
                break;
            }
        }
    }

    private function inventoryChartRows(array $rows, string $mode, string $baseCurrency = '', string $secondaryCurrency = ''): array
    {
        return array_map(function (array $row) use ($mode, $baseCurrency, $secondaryCurrency): array {
            $unit = trim((string) ($row['unit_label'] ?? 'und')) ?: 'und';
            $name = (string) ($row['name'] ?? 'Producto');
            $sku = (string) ($row['sku'] ?? '');
            $category = trim((string) ($row['category_name'] ?? 'Sin categoria'));
            $type = strtolower(trim((string) ($row['product_type'] ?? 'merchandise')));
            $typeLabel = product_type_label($type);

            if ($mode === 'movement') {
                $value = (float) ($row['movement_quantity'] ?? 0);
                $incoming = (float) ($row['incoming_quantity'] ?? 0);
                $outgoing = (float) ($row['outgoing_quantity'] ?? 0);
                $produced = (float) ($row['produced_quantity'] ?? 0);
                $purchased = (float) ($row['purchased_quantity'] ?? 0);
                $sold = (float) ($row['sold_quantity'] ?? 0);
                $adjusted = (float) ($row['adjustment_quantity'] ?? 0);

                if ($type === 'finished_good' || $produced > 0.0) {
                    $detail = 'Producidas ' . $this->money($produced) . ' | Vendidas/despachadas ' . $this->money($sold);
                } else {
                    $detail = 'Entradas por compra ' . $this->money($purchased) . ' | Vendidas/despachadas ' . $this->money($sold);
                }

                if ($adjusted > 0.0) {
                    $detail .= ' | Ajustes ' . $this->money($adjusted);
                }

                return [
                    'label' => $name,
                    'sku' => $sku,
                    'type_label' => $typeLabel,
                    'value' => $value,
                    'value_text' => $this->money($value) . ' ' . $unit,
                    'detail' => $detail . ' | Unidad ' . $unit . ' | ' . $category,
                ];
            }

            if ($mode === 'value') {
                $value = (float) ($row['inventory_value_base'] ?? 0);
                $secondaryValue = (float) ($row['inventory_value_secondary'] ?? 0);
                return [
                    'label' => $name,
                    'sku' => $sku,
                    'type_label' => $typeLabel,
                    'value' => $value,
                    'value_text' => $this->money($value) . ' ' . $baseCurrency,
                    'detail' => 'Equiv. ' . $this->money($secondaryValue) . ' ' . $secondaryCurrency . ' | Stock ' . $this->money($row['stock'] ?? 0) . ' ' . $unit,
                ];
            }

            $value = (float) ($row['stock'] ?? 0);
            return [
                'label' => $name,
                'sku' => $sku,
                'type_label' => $typeLabel,
                'value' => $value,
                'value_text' => $this->money($value) . ' ' . $unit,
                'detail' => 'Minimo ' . $this->money($row['stock_min'] ?? 0) . ' ' . $unit . ' | Unidad ' . $unit . ' | ' . $category,
            ];
        }, array_values($rows));
    }

    private function renderInventoryRankingChartPages(MinimalPdfDocument $pdf, string $title, string $subtitle, array $rows): void
    {
        $rowsPerChart = 14;
        $chunks = $rows === [] ? [[]] : array_chunk($rows, $rowsPerChart);
        $totalChunks = count($chunks);
        $totalRows = count($rows);

        foreach ($chunks as $index => $chunk) {
            $fromPosition = ($index * $rowsPerChart) + 1;
            $toPosition = min(($index + 1) * $rowsPerChart, $totalRows);
            $chunkTitle = $totalChunks > 1 ? $title . ' (' . ($index + 1) . '/' . $totalChunks . ')' : $title;
            $chunkSubtitle = $subtitle . ' | ' . (
                $totalRows > 0
                    ? 'productos ' . $fromPosition . '-' . $toPosition . ' de ' . $totalRows
                    : '0 productos'
            );
            $height = $chunk === []
                ? 82.0
                : min(150.0, max(86.0, 28.0 + (count($chunk) * 8.4)));

            $height = $this->fitBlockHeightToPage($pdf, $height, 82.0, 7.0);
            $top = $pdf->GetY();
            $this->renderInventoryRankingChartAt(
                $pdf,
                $pdf->getLeftMargin(),
                $top,
                $this->usableWidth($pdf),
                $height,
                $chunkTitle,
                $chunkSubtitle,
                $chunk
            );
            $pdf->SetY($top + $height + 7);
        }
    }

    private function renderInventoryRankingChartAt(MinimalPdfDocument $pdf, float $x, float $y, float $width, float $height, string $title, string $subtitle, array $rows): void
    {
        $this->drawChartPanel($pdf, $x, $y, $width, $height, $title, $subtitle);

        if ($rows === []) {
            $this->drawEmptyChartMessage($pdf, $x, $y, $width, $height);
            return;
        }

        $max = max(0.0, ...array_map(static fn (array $row): float => (float) ($row['value'] ?? 0), $rows));
        if ($max <= 0.0) {
            $max = 1.0;
        }

        $labelWidth = $width * 0.38;
        $barX = $x + 7 + $labelWidth;
        $barWidth = $width - $labelWidth - 19;
        $availableHeight = max(30.0, $height - 24);
        $rowHeight = max(5.9, min(8.2, $availableHeight / max(1, count($rows))));
        $rowY = $y + 18;

        foreach ($rows as $row) {
            if ($rowY + $rowHeight > $y + $height - 3) {
                break;
            }

            $value = (float) ($row['value'] ?? 0);
            $fillWidth = $barWidth * ($value / $max);

            $pdf->SetXY($x + 7, $rowY + 0.3);
            $pdf->SetFont('Helvetica', '', 7.3);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($labelWidth - 2, 3.8, $this->truncateText($pdf, (string) ($row['label'] ?? ''), $labelWidth - 3), 0, 0, 'L');

            $pdf->SetFillColor(235, 239, 244);
            $pdf->Rect($barX, $rowY + 0.8, $barWidth, 3.6, 'F');
            $pdf->SetFillColor(47, 111, 104);
            $pdf->Rect($barX, $rowY + 0.8, $fillWidth, 3.6, 'F');

            $pdf->SetXY($barX, $rowY + 4.4);
            $pdf->SetFont('Helvetica', '', 6.7);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($barWidth, 3, $this->truncateText($pdf, (string) ($row['value_text'] ?? ''), $barWidth - 1), 0, 0, 'R');

            $rowY += $rowHeight;
        }
    }

    private function renderDashboardMetricCards(MinimalPdfDocument $pdf, array $metrics): void
    {
        if ($metrics === []) {
            return;
        }

        $columns = 4;
        $gap = 5.0;
        $cardWidth = ($this->usableWidth($pdf) - ($gap * ($columns - 1))) / $columns;
        $cardHeight = 20.0;
        $rows = (int) ceil(count($metrics) / $columns);

        $this->ensureSpace($pdf, ($rows * $cardHeight) + (($rows - 1) * $gap) + 4);

        $left = $pdf->getLeftMargin();
        $top = $pdf->GetY();

        foreach (array_values($metrics) as $index => $metric) {
            $column = $index % $columns;
            $row = intdiv($index, $columns);
            $x = $left + ($column * ($cardWidth + $gap));
            $y = $top + ($row * ($cardHeight + $gap));

            $pdf->SetFillColor(...self::C_PANEL);
            $pdf->SetDrawColor(...self::C_LINE);
            $pdf->Rect($x, $y, $cardWidth, $cardHeight, 'DF');

            $pdf->SetXY($x + 3.2, $y + 2.7);
            $pdf->SetFont('Helvetica', '', 7.6);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($cardWidth - 6.4, 3.6, strtoupper($this->text((string) ($metric[0] ?? ''))), 0, 1, 'L');

            $pdf->SetX($x + 3.2);
            $pdf->SetFont('Helvetica', 'B', 10.2);
            $pdf->SetTextColor(...self::C_HEADER);
            $pdf->Cell($cardWidth - 6.4, 5.5, $this->truncateText($pdf, (string) ($metric[1] ?? ''), $cardWidth - 7), 0, 1, 'L');

            $pdf->SetX($x + 3.2);
            $pdf->SetFont('Helvetica', '', 7.8);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($cardWidth - 6.4, 4, $this->truncateText($pdf, (string) ($metric[2] ?? ''), $cardWidth - 7), 0, 1, 'L');
        }

        $pdf->SetY($top + ($rows * $cardHeight) + (($rows - 1) * $gap) + 6);
    }

    private function renderDashboardLineChart(MinimalPdfDocument $pdf, string $title, array $labels, array $series, string $subtitle = ''): void
    {
        $height = 86.0;
        $this->ensureSpace($pdf, $height + 8);

        $left = $pdf->getLeftMargin();
        $top = $pdf->GetY();
        $width = $this->usableWidth($pdf);

        $this->drawChartPanel($pdf, $left, $top, $width, $height, $title, $subtitle);

        $chartLeft = $left + 12;
        $chartTop = $top + 19;
        $chartWidth = $width - 22;
        $chartHeight = $height - 34;

        $values = [];
        foreach ($series as $item) {
            $values = array_merge($values, array_map('floatval', $item['values'] ?? []));
        }
        $max = max(0.0, ...$values);

        if ($max <= 0.0 || $labels === []) {
            $this->drawEmptyChartMessage($pdf, $left, $top, $width, $height);
            $pdf->SetY($top + $height + 7);
            return;
        }

        $max *= 1.12;
        $pdf->SetDrawColor(230, 234, 240);
        $pdf->SetLineWidth(0.15);
        for ($i = 0; $i <= 4; $i++) {
            $y = $chartTop + ($chartHeight * $i / 4);
            $pdf->Line($chartLeft, $y, $chartLeft + $chartWidth, $y);
        }

        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(...self::C_TEXT_SOFT);
        $labelCount = count($labels);
        $step = max(1, (int) ceil($labelCount / 8));
        foreach ($labels as $index => $label) {
            if ($index % $step !== 0 && $index !== $labelCount - 1) {
                continue;
            }

            $x = $chartLeft + ($labelCount <= 1 ? $chartWidth / 2 : ($index * $chartWidth / ($labelCount - 1)));
            $pdf->SetXY($x - 8, $chartTop + $chartHeight + 2.5);
            $pdf->Cell(16, 3.5, $this->truncateText($pdf, (string) $label, 15), 0, 0, 'C');
        }

        $legendX = $left + $width - 90;
        $legendY = $top + 6.5;
        foreach ($series as $index => $item) {
            $color = $item['color'] ?? self::C_ACCENT;
            $pdf->SetFillColor(...$color);
            $pdf->Rect($legendX + ($index * 30), $legendY + 0.8, 3.2, 3.2, 'F');
            $pdf->SetXY($legendX + 4.8 + ($index * 30), $legendY);
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell(23, 4.6, $this->truncateText($pdf, (string) ($item['label'] ?? ''), 22), 0, 0, 'L');
        }

        foreach ($series as $item) {
            $points = [];
            $data = array_map('floatval', $item['values'] ?? []);
            foreach ($labels as $index => $_label) {
                $value = (float) ($data[$index] ?? 0);
                $points[] = [
                    $chartLeft + ($labelCount <= 1 ? $chartWidth / 2 : ($index * $chartWidth / ($labelCount - 1))),
                    $chartTop + $chartHeight - (($value / $max) * $chartHeight),
                ];
            }

            $color = $item['color'] ?? self::C_ACCENT;
            $pdf->SetDrawColor(...$color);
            $pdf->SetFillColor(...$color);
            $pdf->SetLineWidth(0.65);
            for ($i = 1; $i < count($points); $i++) {
                $pdf->Line($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1]);
            }
            foreach ($points as $point) {
                $pdf->Rect($point[0] - 0.7, $point[1] - 0.7, 1.4, 1.4, 'F');
            }
        }

        $pdf->SetLineWidth(0.2);
        $pdf->SetY($top + $height + 7);
    }

    private function renderDashboardCompositionAt(
        MinimalPdfDocument $pdf,
        float $x,
        float $y,
        float $width,
        float $height,
        array $labels,
        array $values,
        string $currency,
        string $referenceCurrency,
        float $rate
    ): void
    {
        $colors = [[47, 111, 104], [96, 125, 155], [200, 149, 83], [196, 95, 95]];
        $this->drawChartPanel($pdf, $x, $y, $width, $height, 'Distribucion del periodo', $currency . ' con referencia ' . $referenceCurrency);

        $sum = array_sum(array_map('floatval', $values));
        if ($sum <= 0.0) {
            $this->drawEmptyChartMessage($pdf, $x, $y, $width, $height);
            return;
        }

        $barX = $x + 7;
        $barY = $y + 24;
        $barWidth = $width - 14;
        $barHeight = 12;
        $cursor = $barX;

        foreach ($values as $index => $value) {
            $segmentWidth = $barWidth * ((float) $value / $sum);
            $pdf->SetFillColor(...($colors[$index % count($colors)]));
            $pdf->Rect($cursor, $barY, $segmentWidth, $barHeight, 'F');
            $cursor += $segmentWidth;
        }

        $pdf->SetDrawColor(...self::C_LINE);
        $pdf->Rect($barX, $barY, $barWidth, $barHeight);

        $rowY = $barY + 18;
        foreach ($labels as $index => $label) {
            $value = (float) ($values[$index] ?? 0);
            $percent = $sum > 0 ? ($value / $sum) * 100 : 0;
            $reference = convert_currency_amount($value, $currency, $referenceCurrency, $rate);
            $pdf->SetFillColor(...($colors[$index % count($colors)]));
            $pdf->Rect($barX, $rowY + 1.2, 3.3, 3.3, 'F');
            $pdf->SetXY($barX + 5.2, $rowY);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($barWidth * 0.32, 5.2, $this->truncateText($pdf, (string) $label, ($barWidth * 0.32) - 1), 0, 0, 'L');
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->SetTextColor(...self::C_TEXT);
            $pdf->Cell($barWidth * 0.27, 5.2, $this->truncateText($pdf, $this->money($value) . ' ' . $currency, ($barWidth * 0.27) - 1), 0, 0, 'R');
            $pdf->SetFont('Helvetica', '', 7.6);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($barWidth * 0.25, 5.2, $this->truncateText($pdf, '~ ' . $this->money($reference) . ' ' . $referenceCurrency, ($barWidth * 0.25) - 1), 0, 0, 'R');
            $pdf->Cell($barWidth * 0.12, 5.2, number_format($percent, 1, ',', '.') . '%', 0, 1, 'R');
            $rowY += 6.2;
        }
    }

    private function renderDashboardTopProductsAt(
        MinimalPdfDocument $pdf,
        float $x,
        float $y,
        float $width,
        float $height,
        array $products,
        string $currency,
        string $referenceCurrency,
        float $rate
    ): void
    {
        $this->drawChartPanel($pdf, $x, $y, $width, $height, 'Productos mas vendidos', $currency . ' con referencia ' . $referenceCurrency);

        $products = array_values(array_slice($products, 0, 6));
        $max = 0.0;
        foreach ($products as $product) {
            $max = max($max, (float) ($product['total'] ?? 0));
        }

        if ($products === [] || $max <= 0.0) {
            $this->drawEmptyChartMessage($pdf, $x, $y, $width, $height);
            return;
        }

        $labelWidth = $width * 0.38;
        $barX = $x + 7 + $labelWidth;
        $barWidth = $width - $labelWidth - 19;
        $rowY = $y + 20;
        $rowHeight = 10.4;

        foreach ($products as $product) {
            $name = (string) ($product['name'] ?? 'Producto');
            $total = (float) ($product['total'] ?? 0);
            $quantity = (float) ($product['quantity'] ?? 0);
            $reference = convert_currency_amount($total, $currency, $referenceCurrency, $rate);
            $fillWidth = $barWidth * ($total / $max);

            $pdf->SetXY($x + 7, $rowY + 0.5);
            $pdf->SetFont('Helvetica', '', 7.7);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($labelWidth - 2, 4.2, $this->truncateText($pdf, $name, $labelWidth - 3), 0, 0, 'L');

            $pdf->SetFillColor(235, 239, 244);
            $pdf->Rect($barX, $rowY + 1, $barWidth, 4.2, 'F');
            $pdf->SetFillColor(47, 111, 104);
            $pdf->Rect($barX, $rowY + 1, $fillWidth, 4.2, 'F');

            $pdf->SetXY($barX, $rowY + 5);
            $pdf->SetFont('Helvetica', '', 6.8);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($barWidth, 3, $this->truncateText($pdf, $this->money($total) . ' ' . $currency . ' | ~ ' . $this->money($reference) . ' ' . $referenceCurrency, $barWidth - 1), 0, 0, 'R');
            $pdf->SetXY($barX, $rowY + 8);
            $pdf->Cell($barWidth, 2.8, $this->money($quantity) . ' und.', 0, 0, 'R');

            $rowY += $rowHeight;
        }
    }

    private function drawChartPanel(MinimalPdfDocument $pdf, float $x, float $y, float $width, float $height, string $title, string $subtitle = ''): void
    {
        $pdf->SetFillColor(...self::C_WHITE);
        $pdf->SetDrawColor(...self::C_LINE);
        $pdf->Rect($x, $y, $width, $height, 'DF');

        $pdf->SetXY($x + 5.5, $y + 5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(...self::C_HEADER);
        $pdf->Cell($width * 0.55, 5, $this->truncateText($pdf, $title, ($width * 0.55) - 1), 0, 0, 'L');

        if ($subtitle !== '') {
            $pdf->SetFont('Helvetica', '', 7.6);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($width * 0.38, 5, $this->truncateText($pdf, $subtitle, ($width * 0.38) - 1), 0, 0, 'R');
        }
    }

    private function drawEmptyChartMessage(MinimalPdfDocument $pdf, float $x, float $y, float $width, float $height): void
    {
        $pdf->SetXY($x + 6, $y + ($height / 2) - 2);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(...self::C_TEXT_SOFT);
        $pdf->Cell($width - 12, 5, $this->text('Sin datos para graficar en este rango.'), 0, 0, 'C');
    }

    private function makeDocument(string $title, string $reference = '', string $orientation = 'P'): MinimalPdfDocument
    {
        $pdf = new MinimalPdfDocument($orientation, 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->SetMargins(16, 18, 16);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->docTitle = $this->text($title);
        $pdf->docReference = $this->text($reference);
        $pdf->companyName = $this->text((string) (company()['name'] ?? app_name()));
        $pdf->companyMeta = $this->text(trim((string) (company()['rif'] ?? '')));
        $pdf->generatedAt = date('d/m/Y H:i');
        $pdf->logoPath = $this->resolveLogoPath();
        $pdf->AddPage();

        return $pdf;
    }

    private function resolveLogoPath(): string
    {
        $configured = trim((string) env('PDF_HEADER_LOGO', ''));
        if ($configured === '') {
            return '';
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured);
        $root = dirname(__DIR__, 2);

        if (preg_match('/^[A-Za-z]:\\\\|^\//', $normalized) === 1) {
            return is_file($normalized) ? $normalized : '';
        }

        $fullPath = $root . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
        return is_file($fullPath) ? $fullPath : '';
    }

    private function renderMetaGrid(MinimalPdfDocument $pdf, array $meta): void
    {
        $meta = array_values(array_filter($meta, static fn (array $item): bool => trim((string) ($item[1] ?? '')) !== ''));
        if ($meta === []) {
            return;
        }

        $left = $pdf->getLeftMargin();
        $usableWidth = $this->usableWidth($pdf);
        $columns = 2;
        $gap = 8.0;
        $columnWidth = ($usableWidth - $gap) / $columns;
        $labelHeight = 3.6;
        $valueHeight = 5.2;
        $rowHeight = 10.6;
        $y = $pdf->GetY();

        $this->ensureSpace($pdf, (ceil(count($meta) / $columns) * $rowHeight) + 2);

        foreach ($meta as $index => $item) {
            $column = $index % $columns;
            $row = intdiv($index, $columns);
            $x = $left + ($column * ($columnWidth + $gap));
            $itemY = $y + ($row * $rowHeight);

            $pdf->SetXY($x, $itemY);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell($columnWidth, $labelHeight, strtoupper($this->text((string) ($item[0] ?? ''))), 0, 0, 'L');

            $pdf->SetXY($x, $itemY + $labelHeight + 0.4);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(...self::C_TEXT);
            $pdf->Cell($columnWidth, $valueHeight, $this->truncateText($pdf, (string) ($item[1] ?? ''), $columnWidth), 0, 0, 'L');
        }

        $pdf->SetY($y + (ceil(count($meta) / $columns) * $rowHeight) + 1.2);
        $this->drawDivider($pdf);
        $pdf->Ln(3.2);
    }

    private function renderCompactSummary(MinimalPdfDocument $pdf, array $summary): void
    {
        $summary = array_values(array_filter($summary, static fn (array $item): bool => trim((string) ($item[1] ?? '')) !== ''));
        if ($summary === []) {
            return;
        }

        $this->ensureSpace($pdf, 10 + (count($summary) * 5.6));

        $left = $pdf->getLeftMargin();
        $width = $this->usableWidth($pdf);
        $panelY = $pdf->GetY();

        $pdf->SetFillColor(...self::C_PANEL);
        $pdf->SetDrawColor(...self::C_LINE);
        $pdf->Rect($left, $panelY, $width, 7 + (count($summary) * 5.2), 'DF');

        $pdf->SetXY($left + 4, $panelY + 2.8);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(...self::C_HEADER);
        $pdf->Cell($width - 8, 4, $this->text('Resumen'), 0, 1, 'L');

        foreach ($summary as $item) {
            $pdf->SetX($left + 4);
            $pdf->SetFont('Helvetica', '', 8.8);
            $pdf->SetTextColor(...self::C_TEXT_SOFT);
            $pdf->Cell(($width - 8) * 0.58, 5, $this->truncateText($pdf, (string) ($item[0] ?? ''), (($width - 8) * 0.58) - 1), 0, 0, 'L');
            $pdf->SetFont('Helvetica', 'B', 8.8);
            $pdf->SetTextColor(...self::C_TEXT);
            $pdf->Cell(($width - 8) * 0.42, 5, $this->truncateText($pdf, (string) ($item[1] ?? ''), (($width - 8) * 0.42) - 1), 0, 1, 'R');
        }

        $pdf->Ln(4);
    }

    private function renderSectionTitle(MinimalPdfDocument $pdf, string $title): void
    {
        $this->ensureSpace($pdf, 10);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(...self::C_HEADER);
        $pdf->Cell(0, 6, $this->text($title), 0, 1, 'L');
        $pdf->SetDrawColor(...self::C_ACCENT);
        $pdf->Line($pdf->getLeftMargin(), $pdf->GetY(), $pdf->getLeftMargin() + 26, $pdf->GetY());
        $pdf->Ln(3);
    }

    private function renderTable(
        MinimalPdfDocument $pdf,
        array $headers,
        array $widths,
        array $rows,
        array $alignments = []
    ): void {
        $headers = array_values($headers);
        $widths = $this->fitWidthsToPage($pdf, $widths);
        $alignments = $this->resolveAlignments($headers, $rows, $alignments);

        $this->ensureSpace($pdf, 12);

        $pdf->SetFillColor(...self::C_PANEL);
        $pdf->SetDrawColor(...self::C_LINE);
        $pdf->SetTextColor(...self::C_HEADER);
        $pdf->SetFont('Helvetica', 'B', 8.5);

        foreach ($headers as $index => $header) {
            $width = $widths[$index] ?? 20;
            $pdf->Cell($width, 7.6, $this->truncateText($pdf, (string) $header, $width - 2), 'B', 0, $alignments[$index] ?? 'L', true);
        }

        $pdf->Ln();
        $pdf->SetFont('Helvetica', '', 8.8);
        $pdf->SetTextColor(...self::C_TEXT);

        if ($rows === []) {
            $pdf->SetDrawColor(...self::C_LINE);
            $pdf->Cell(array_sum($widths), 8.5, $this->text('Sin registros para mostrar.'), 'B', 1, 'C');
            $pdf->Ln(4);
            return;
        }

        foreach ($rows as $row) {
            $prepared = array_map(fn ($value): string => $this->text((string) $value), $row);
            $rowHeight = $this->tableRowHeight($pdf, $prepared, $widths);
            $this->ensureSpace($pdf, $rowHeight + 1.5);

            $x = $pdf->GetX();
            $y = $pdf->GetY();

            foreach ($headers as $index => $_header) {
                $width = $widths[$index] ?? 20;
                $text = $prepared[$index] ?? '';
                $align = $alignments[$index] ?? 'L';

                $pdf->Rect($x, $y, $width, $rowHeight);
                $pdf->SetXY($x + 1.2, $y + 1.2);
                $pdf->MultiCell($width - 2.4, 4.2, $text, 0, $align);
                $x += $width;
                $pdf->SetXY($x, $y);
            }

            $pdf->SetXY($pdf->getLeftMargin(), $y + $rowHeight);
        }

        $pdf->Ln(4);
    }

    private function renderTotalsBlock(MinimalPdfDocument $pdf, array $totals, ?float $preferredWidth = null): void
    {
        $totals = array_values(array_filter($totals, static fn (array $item): bool => trim((string) ($item[1] ?? '')) !== ''));
        if ($totals === []) {
            return;
        }

        $maxBlockWidth = $this->usableWidth($pdf);
        $blockWidth = $preferredWidth !== null
            ? min($preferredWidth, $maxBlockWidth)
            : min(78.0, $this->usableWidth($pdf) * 0.46);
        $labelWidth = $blockWidth * 0.52;
        $valueWidth = $blockWidth - $labelWidth;
        $height = 7 + (count($totals) * 5.4);

        $this->ensureSpace($pdf, $height + 2);

        $left = $pdf->GetPageWidth() - $pdf->getRightMargin() - $blockWidth;
        $top = $pdf->GetY();

        $pdf->SetFillColor(...self::C_PANEL);
        $pdf->SetDrawColor(...self::C_LINE);
        $pdf->Rect($left, $top, $blockWidth, $height, 'DF');

        $pdf->SetXY($left + 3.5, $top + 2.4);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(...self::C_HEADER);
        $pdf->Cell($blockWidth - 7, 4, $this->text('Totales'), 0, 1, 'L');

        foreach ($totals as $index => $item) {
            $isLast = $index === array_key_last($totals);

            $pdf->SetX($left + 3.5);
            $pdf->SetFont('Helvetica', $isLast ? 'B' : '', $isLast ? 9.6 : 8.7);
            $pdf->SetTextColor(...($isLast ? self::C_HEADER : self::C_TEXT_SOFT));
            $pdf->Cell($labelWidth - 3.5, 5.1, $this->truncateText($pdf, (string) ($item[0] ?? ''), $labelWidth - 5), 0, 0, 'L');

            $pdf->SetFont('Helvetica', 'B', $isLast ? 9.6 : 8.9);
            $pdf->SetTextColor(...self::C_TEXT);
            $pdf->Cell($valueWidth, 5.1, $this->truncateText($pdf, (string) ($item[1] ?? ''), $valueWidth - 1), 0, 1, 'R');
        }

        $pdf->Ln(4);
    }

    private function renderNotesBlock(MinimalPdfDocument $pdf, string $title, string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        $this->ensureSpace($pdf, 18);
        $this->renderSectionTitle($pdf, $title);

        $left = $pdf->getLeftMargin();
        $width = $this->usableWidth($pdf);
        $top = $pdf->GetY();
        $lines = $this->wrapTextLines($pdf, $this->text($value), $width - 8);
        $height = 6 + (count($lines) * 4.6);

        $pdf->SetFillColor(...self::C_PANEL);
        $pdf->SetDrawColor(...self::C_LINE);
        $pdf->Rect($left, $top, $width, $height, 'DF');

        $pdf->SetXY($left + 4, $top + 3);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(...self::C_TEXT);
        $pdf->MultiCell($width - 8, 4.6, implode("\n", $lines), 0, 'L');
        $pdf->Ln(2);
    }

    private function drawDivider(MinimalPdfDocument $pdf): void
    {
        $pdf->SetDrawColor(...self::C_LINE);
        $pdf->Line($pdf->getLeftMargin(), $pdf->GetY(), $pdf->GetPageWidth() - $pdf->getRightMargin(), $pdf->GetY());
    }

    private function fitWidthsToPage(MinimalPdfDocument $pdf, array $widths): array
    {
        $widths = array_map(static fn ($value): float => max(12.0, (float) $value), $widths);
        $available = $this->usableWidth($pdf);
        $current = array_sum($widths);

        if ($current <= 0) {
            return $widths;
        }

        if ($current <= $available) {
            return $widths;
        }

        $ratio = $available / $current;
        return array_map(static fn (float $value): float => max(12.0, round($value * $ratio, 2)), $widths);
    }

    private function resolveAlignments(array $headers, array $rows, array $alignments): array
    {
        if ($alignments !== []) {
            return $alignments;
        }

        return array_map(
            static function (int $index) use ($rows): string {
                foreach ($rows as $row) {
                    $value = trim((string) ($row[$index] ?? ''));
                    if ($value === '') {
                        continue;
                    }

                    return preg_match('/^-?[\d\.,]+(?:\s+[A-Z]{2,4})?$/', $value) === 1 ? 'R' : 'L';
                }

                return 'L';
            },
            array_keys($headers)
        );
    }

    private function tableRowHeight(MinimalPdfDocument $pdf, array $row, array $widths): float
    {
        $maxLines = 1;

        foreach ($row as $index => $value) {
            $width = $widths[$index] ?? 20;
            $lines = $this->wrapTextLines($pdf, $value, max(8.0, $width - 2.4));
            $maxLines = max($maxLines, count($lines));
        }

        return max(7.8, ($maxLines * 4.2) + 2.4);
    }

    private function wrapTextLines(MinimalPdfDocument $pdf, string $text, float $width): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return [''];
        }

        $result = [];

        foreach (explode("\n", $text) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $result[] = '';
                continue;
            }

            $current = '';
            foreach (preg_split('/\s+/', $paragraph) ?: [] as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if ($pdf->GetStringWidth($candidate) <= $width) {
                    $current = $candidate;
                    continue;
                }

                if ($current !== '') {
                    $result[] = $current;
                }

                if ($pdf->GetStringWidth($word) <= $width) {
                    $current = $word;
                    continue;
                }

                foreach ($this->splitLongWord($pdf, $word, $width) as $chunk) {
                    $result[] = $chunk;
                }

                $current = '';
            }

            if ($current !== '') {
                $result[] = $current;
            }
        }

        return $result === [] ? [''] : $result;
    }

    private function splitLongWord(MinimalPdfDocument $pdf, string $word, float $width): array
    {
        $parts = [];
        $current = '';

        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $candidate = $current . $char;
            if ($current !== '' && $pdf->GetStringWidth($candidate) > $width) {
                $parts[] = $current;
                $current = $char;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts === [] ? [$word] : $parts;
    }

    private function truncateText(MinimalPdfDocument $pdf, string $text, float $cellWidth): string
    {
        $text = trim($this->text($text));
        if ($text === '' || $cellWidth <= 2) {
            return '';
        }

        if ($pdf->GetStringWidth($text) <= $cellWidth) {
            return $text;
        }

        $ellipsis = '...';
        while ($text !== '' && $pdf->GetStringWidth($text . $ellipsis) > $cellWidth) {
            $text = substr($text, 0, -1);
        }

        return rtrim($text) . $ellipsis;
    }

    private function usableWidth(MinimalPdfDocument $pdf): float
    {
        return $pdf->GetPageWidth() - $pdf->getLeftMargin() - $pdf->getRightMargin();
    }

    private function bottomLimit(MinimalPdfDocument $pdf): float
    {
        return $pdf->GetPageHeight() - 18;
    }

    private function fitBlockHeightToPage(MinimalPdfDocument $pdf, float $desiredHeight, float $minimumHeight, float $bottomPadding = 0.0): float
    {
        if ($pdf->GetY() + $desiredHeight + $bottomPadding > $this->bottomLimit($pdf)) {
            $pdf->AddPage();
        }

        $availableHeight = max(24.0, $this->bottomLimit($pdf) - $pdf->GetY() - $bottomPadding);
        $minimumHeight = min($minimumHeight, $availableHeight);

        return min(max($desiredHeight, $minimumHeight), $availableHeight);
    }

    private function ensureSpace(MinimalPdfDocument $pdf, float $requiredSpace): void
    {
        if ($pdf->GetY() + $requiredSpace > $this->bottomLimit($pdf)) {
            $pdf->AddPage();
        }
    }

    private function sumAmounts(array $rows): float
    {
        return array_reduce(
            $rows,
            static fn (float $carry, array $row): float => $carry + (float) ($row['amount'] ?? 0),
            0.0
        );
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    private function referenceAmountText(float $amount, string $fromCurrency, string $toCurrency, float $rate): string
    {
        return $this->money(convert_currency_amount($amount, $fromCurrency, $toCurrency, $rate)) . ' ' . $toCurrency;
    }

    private function formatDate(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp !== false ? date('d/m/Y', $timestamp) : $value;
    }

    private function safeFileName(string $value): string
    {
        $normalized = $this->normalizeUtf8($value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $clean = strtolower(trim($ascii !== false ? $ascii : $normalized));
        $clean = preg_replace('/[^a-z0-9\-]+/', '-', $clean) ?: 'reporte';

        return trim($clean, '-');
    }

    private function normalizeUtf8(string $value): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($value === '') {
            return '';
        }

        if (preg_match('/Ãƒ.|Ã‚.|Ã¢./', $value) === 1 && function_exists('mb_convert_encoding')) {
            $repaired = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            if (is_string($repaired) && $repaired !== '') {
                $value = $repaired;
            }
        }

        return $value;
    }

    private function text(string $value): string
    {
        $normalized = $this->normalizeUtf8($value);
        $converted = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $normalized);
        return $converted !== false ? $converted : (preg_replace('/[^\x20-\x7E]/', '?', $normalized) ?? '');
    }
}

class MinimalPdfDocument extends FPDF
{
    public string $docTitle = '';
    public string $docReference = '';
    public string $companyName = '';
    public string $companyMeta = '';
    public string $generatedAt = '';
    public string $logoPath = '';

    public function getLeftMargin(): float
    {
        return (float) $this->lMargin;
    }

    public function getRightMargin(): float
    {
        return (float) $this->rMargin;
    }

    public function Header(): void
    {
        $left = $this->getLeftMargin();
        $right = $this->GetPageWidth() - $this->getRightMargin();
        $width = $right - $left;

        $topY = 10.0;
        $logoWidth = $this->logoPath !== '' ? 24.0 : 0.0;
        $logoHeight = $this->logoPath !== '' ? 24.0 : 0.0;

        $rightBlockWidth = 62.0;
        $contentWidth = $width - $rightBlockWidth - 6;

        if ($this->logoPath !== '') {
            $logoX = $left;
            $logoY = $topY;
            $this->Image($this->logoPath, $logoX, $logoY, $logoWidth);
        }

        $rightBlockX = $right - $rightBlockWidth;

        $this->SetXY($rightBlockX, $topY + 2);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(92, 107, 122);
        $meta = trim($this->companyMeta . '  |  ' . $this->generatedAt, ' |');
        $this->Cell($rightBlockWidth, 4, $this->fitText($meta, $rightBlockWidth), 0, 1, 'R');

        $titleY = $this->logoPath !== '' ? ($topY + $logoHeight + 2.5) : $topY;

        $this->SetXY($left, $titleY);
        $this->SetTextColor(18, 32, 47);
        $this->SetFont('Helvetica', 'B', 18);
        $this->Cell($contentWidth, 7, $this->fitText($this->docTitle, $contentWidth), 0, 1, 'L');

        if ($this->docReference !== '') {
            $this->SetX($left);
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(92, 107, 122);
            $this->Cell($contentWidth, 5, $this->fitText($this->docReference, $contentWidth), 0, 1, 'L');
        }

        $this->SetX($left);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(18, 32, 47);
        $this->Cell($contentWidth, 5.5, $this->fitText($this->companyName, $contentWidth), 0, 1, 'L');

        $lineY = $this->GetY() + 2.8;
        $this->SetDrawColor(223, 228, 235);
        $this->Line($left, $lineY, $right, $lineY);

        $this->SetY($lineY + 5.5);
    }

    public function Footer(): void
    {
        $left = $this->getLeftMargin();
        $right = $this->GetPageWidth() - $this->getRightMargin();
        $lineY = $this->GetPageHeight() - 12.5;

        $this->SetDrawColor(223, 228, 235);
        $this->Line($left, $lineY, $right, $lineY);

        $this->SetY(-9);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(128, 140, 154);
        $this->Cell(0, 4, $this->fitText('Pagina ' . $this->PageNo() . ' de {nb}', 36), 0, 0, 'R');
    }

    private function fitText(string $text, float $maxWidth): string
    {
        $text = trim($text);
        if ($text === '' || $maxWidth <= 2) {
            return '';
        }

        if ($this->GetStringWidth($text) <= $maxWidth) {
            return $text;
        }

        $ellipsis = '...';
        while ($text !== '' && $this->GetStringWidth($text . $ellipsis) > $maxWidth) {
            $text = substr($text, 0, -1);
        }

        return rtrim($text) . $ellipsis;
    }
}
