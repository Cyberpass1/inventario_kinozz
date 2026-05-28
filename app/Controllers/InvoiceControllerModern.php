<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\PdfService;

class InvoiceControllerModern extends Controller
{
    public function index(): void
    {
        $invoiceModel = new Invoice();
        ['role' => $currentRole, 'canFilter' => $canFilterHistory, 'filters' => $historyFilters, 'limit' => $historyLimit] = $this->resolveHistoryContext();
        $invoices = $invoiceModel->history($historyFilters, $historyLimit);
        $clientHints = (new Client())->search('', 8);
        $products = (new Product())->sellableList();
        $nextNumber = $invoiceModel->nextNumber();
        $rate = ['rate' => system_exchange_rate(date('Y-m-d')), 'currency_from' => 'USD', 'currency_to' => 'VES'];
        $invoiceDueDays = invoice_due_days();
        $historyExportQuery = $this->buildHistoryExportQuery($canFilterHistory, $historyFilters);

        $summary = [
            'operations' => count($invoices),
            'total' => array_reduce(
                array_filter($invoices, fn (array $row): bool => ($row['status'] ?? 'active') !== 'cancelled'),
                fn (float $carry, array $row): float => $carry + equivalent_in_bolivars(
                    (float) ($row['total_original'] ?? 0),
                    (string) ($row['currency_code'] ?? ''),
                    (float) ($row['exchange_rate'] ?? 0)
                ),
                0.0
            ),
            'outstanding' => array_reduce(
                array_filter($invoices, fn (array $row): bool => ($row['status'] ?? 'active') !== 'cancelled'),
                fn (float $carry, array $row): float => $carry + (float) ($row['balance_converted'] ?? 0),
                0.0
            ),
        ];

        $this->view('invoices/workspace', compact('invoices', 'clientHints', 'products', 'nextNumber', 'rate', 'summary', 'invoiceDueDays', 'canFilterHistory', 'historyFilters', 'historyExportQuery', 'currentRole'), 'layouts/app_modern');
    }

    public function exportHistory(): void
    {
        ['canFilter' => $canFilterHistory, 'filters' => $historyFilters, 'limit' => $historyLimit] = $this->resolveHistoryContext();
        $invoices = (new Invoice())->history($historyFilters, $historyLimit);
        $fileName = 'historial-facturas-' . date('Y-m-d-His') . '.csv';

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $stream = fopen('php://output', 'wb');
        if ($stream === false) {
            http_response_code(500);
            exit('No se pudo generar la exportacion.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'Fecha',
            'Numero',
            'Cliente',
            'Documento',
            'Productos',
            'Renglones',
            'Cantidad total',
            'Moneda',
            'Tasa de cierre',
            'Subtotal',
            'IVA',
            'Total documento',
            'Equiv. Bs',
            'Estado',
        ], ';');

        foreach ($invoices as $invoice) {
            fputcsv($stream, [
                (string) ($invoice['invoice_date'] ?? ''),
                (string) ($invoice['invoice_number'] ?? ''),
                (string) ($invoice['client_name'] ?? ''),
                (string) ($invoice['client_document'] ?? ''),
                (string) ($invoice['products_summary'] ?? ''),
                (string) ($invoice['line_count'] ?? 0),
                money($invoice['total_quantity'] ?? 0),
                (string) ($invoice['currency_code'] ?? ''),
                money($invoice['exchange_rate'] ?? 0),
                money($invoice['subtotal_original'] ?? 0),
                money($invoice['tax_original'] ?? 0),
                money($invoice['total_original'] ?? 0),
                money(equivalent_in_bolivars(
                    (float) ($invoice['total_original'] ?? 0),
                    (string) ($invoice['currency_code'] ?? ''),
                    (float) ($invoice['exchange_rate'] ?? 0)
                )) . ' ' . secondary_currency(),
                (($invoice['status'] ?? 'active') === 'cancelled') ? 'Anulada' : 'Activa',
            ], ';');
        }

        fclose($stream);
        exit;
    }

    public function storeClient(): void
    {
        validate_csrf();

        (new Client())->insert([
            'name' => trim($_POST['name']),
            'document' => trim($_POST['document'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
        ]);

        flash('success', 'Cliente creado.');
        $this->redirect('/invoices');
    }

    public function store(): void
    {
        validate_csrf();

        try {
            $clientId = (int) ($_POST['client_id'] ?? 0);
            if ($clientId <= 0) {
                throw new \RuntimeException('Debes seleccionar un cliente valido antes de facturar.');
            }
            $documentDate = (string) ($_POST['invoice_date'] ?? date('Y-m-d'));
            $dueDate = document_due_date($documentDate, invoice_due_days());
            $rate = system_exchange_rate($documentDate);
            $currency = trim((string) ($_POST['currency_code'] ?? secondary_currency()));
            $taxValue = tax_percent();
            $items = $this->extractItems($_POST, $currency, $rate);
            $subtotalOriginal = round(array_reduce(
                $items,
                static fn (float $carry, array $item): float => $carry + (float) ($item['total_original'] ?? 0),
                0.0
            ), 2);
            $subtotalConverted = round(array_reduce(
                $items,
                static fn (float $carry, array $item): float => $carry + (float) ($item['total_converted'] ?? 0),
                0.0
            ), 2);
            $taxOriginal = round($subtotalOriginal * ($taxValue / 100), 2);
            $taxConverted = round($subtotalConverted * ($taxValue / 100), 2);
            $totalOriginal = round($subtotalOriginal + $taxOriginal, 2);
            $totalConverted = round($subtotalConverted + $taxConverted, 2);
            $initialPayment = null;

            if (parse_money_input($_POST['payment_amount_original'] ?? 0) > 0) {
                $initialPayment = $this->buildPaymentPayload($_POST, [
                    'currency_code' => $currency,
                    'exchange_rate' => $rate,
                    'total_original' => $totalOriginal,
                    'total_converted' => $totalConverted,
                    'balance_original' => $totalOriginal,
                    'balance_converted' => $totalConverted,
                ], $documentDate, 'Cobro inicial');
            }

            $invoiceId = (new Invoice())->create([
                'client_id' => $clientId,
                'invoice_number' => trim($_POST['invoice_number']),
                'invoice_date' => $documentDate,
                'due_date' => $dueDate,
                'currency_code' => $currency,
                'exchange_rate' => $rate,
                'subtotal_original' => $subtotalOriginal,
                'tax_original' => $taxOriginal,
                'total_original' => $totalOriginal,
                'subtotal_converted' => $subtotalConverted,
                'tax_converted' => $taxConverted,
                'total_converted' => $totalConverted,
                'notes' => trim($_POST['notes'] ?? ''),
            ], $items);

            if ($initialPayment !== null) {
                (new Invoice())->registerPayment($invoiceId, $initialPayment);
            }

            $successMessage = 'Factura registrada. Puedes consultarla luego en la tabla.';
            $documentPrompt = [
                'title' => 'Factura registrada',
                'text' => 'Deseas abrir el reporte en vista previa de impresion?',
                'url' => app_url('/invoices/pdf/' . $invoiceId),
                'confirm' => 'Abrir reporte',
                'cancel' => 'Seguir aqui',
            ];

            if ($this->wantsJson()) {
                $this->json([
                    'ok' => true,
                    'message' => $successMessage,
                    'redirect' => app_url('/invoices'),
                    'document_prompt' => $documentPrompt,
                ]);
            }

            flash('success', $successMessage);
            flash('document_prompt', json_encode($documentPrompt, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $exception) {
            if ($this->wantsJson()) {
                $this->json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }
            flash('error', $exception->getMessage());
        }

        $this->redirect('/invoices');
    }

    public function registerPayment(string $id): void
    {
        validate_csrf();

        try {
            $invoiceId = (int) $id;
            $invoice = (new Invoice())->findFull($invoiceId);
            if (! $invoice) {
                throw new \RuntimeException('Factura no encontrada.');
            }

            $paymentDate = (string) ($_POST['payment_date'] ?? date('Y-m-d'));
            (new Invoice())->registerPayment($invoiceId, $this->buildPaymentPayload($_POST, $invoice, $paymentDate, 'Cobro'));

            if ($this->wantsJson()) {
                $this->json([
                    'ok' => true,
                    'message' => 'Cobro registrado correctamente.',
                    'redirect' => app_url('/invoices'),
                ]);
            }

            flash('success', 'Cobro registrado correctamente.');
        } catch (\Throwable $exception) {
            if ($this->wantsJson()) {
                $this->json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }
            flash('error', $exception->getMessage());
        }

        $this->redirect('/invoices');
    }

    public function cancel(string $id): void
    {
        validate_csrf();

        try {
            (new Invoice())->cancel((int) $id, trim((string) ($_POST['reason'] ?? '')));
            flash('success', 'Factura anulada y stock reintegrado.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/invoices');
    }

    public function print(string $id): void
    {
        $invoice = (new Invoice())->findFull((int) $id);
        $this->view('invoices/print', ['invoice' => $invoice, 'backUrl' => '/invoices'], 'layouts/print');
    }

    public function pdf(string $id): void
    {
        $invoice = (new Invoice())->findFull((int) $id);
        (new PdfService())->invoice($invoice ?? []);
    }

    public function details(string $id): void
    {
        $invoice = (new Invoice())->findFull((int) $id);
        if (! $invoice) {
            $this->json([
                'ok' => false,
                'message' => 'Factura no encontrada.',
            ], 404);
        }

        $this->json([
            'ok' => true,
            'detail' => [
                'id' => (int) ($invoice['id'] ?? 0),
                'number' => (string) ($invoice['invoice_number'] ?? ''),
                'client_name' => (string) ($invoice['client_name'] ?? ''),
                'date' => (string) ($invoice['invoice_date'] ?? ''),
                'currency_code' => (string) ($invoice['currency_code'] ?? ''),
                'line_count' => (int) ($invoice['line_count'] ?? count($invoice['items'] ?? [])),
                'total_original' => (float) ($invoice['total_original'] ?? 0),
                'notes' => trim((string) ($invoice['notes'] ?? '')),
                'products_summary' => (string) ($invoice['products_summary'] ?? ''),
                'items' => array_map(static fn (array $item): array => [
                    'product_name' => (string) ($item['product_name'] ?? 'Producto'),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'price_original' => (float) ($item['price_original'] ?? 0),
                    'total_original' => (float) ($item['total_original'] ?? 0),
                ], is_array($invoice['items'] ?? null) ? $invoice['items'] : []),
            ],
        ]);
    }

    private function toBolivarAmount(float $amount, string $currency, float $rate): float
    {
        return equivalent_in_bolivars($amount, $currency, $rate);
    }

    private function extractItems(array $source, string $documentCurrency, float $rate): array
    {
        $productModel = new Product();
        $rows = $source['items'] ?? [];
        if (!is_array($rows) || $rows === []) {
            $rows = [[
                'product_id' => $source['product_id'] ?? '',
                'quantity' => $source['quantity'] ?? '',
                'price_original' => $source['price_original'] ?? '',
                'source_currency' => $source['source_currency'] ?? base_currency(),
            ]];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $hasAnyValue = trim((string) ($row['product_id'] ?? '')) !== ''
                || trim((string) ($row['quantity'] ?? '')) !== ''
                || trim((string) ($row['price_original'] ?? '')) !== '';
            if (! $hasAnyValue) {
                continue;
            }

            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                throw new \RuntimeException('Cada renglon debe tener un producto valido.');
            }

            $product = $productModel->findVisible($productId);
            if (! $product || ! product_is_saleable($product)) {
                throw new \RuntimeException('Solo puedes facturar productos vendibles o servicios.');
            }

            $quantity = (float) ($row['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw new \RuntimeException('La cantidad de cada producto debe ser mayor a cero.');
            }

            $priceReference = (float) ($row['price_original'] ?? 0);
            if ($priceReference < 0) {
                throw new \RuntimeException('El precio unitario no puede ser negativo.');
            }

            $sourceCurrency = trim((string) ($row['source_currency'] ?? base_currency()));
            if ($sourceCurrency === '') {
                $sourceCurrency = base_currency();
            }

            $priceOriginal = round(convert_currency_amount($priceReference, $sourceCurrency, $documentCurrency, $rate), 2);
            $priceConverted = round(equivalent_in_bolivars($priceOriginal, $documentCurrency, $rate), 2);
            $lineTotalOriginal = round($quantity * $priceOriginal, 2);
            $lineTotalConverted = round($quantity * $priceConverted, 2);

            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'price_original' => $priceOriginal,
                'price_converted' => $priceConverted,
                'total_original' => $lineTotalOriginal,
                'total_converted' => $lineTotalConverted,
            ];
        }

        if ($items === []) {
            throw new \RuntimeException('Debes agregar al menos un producto para registrar la factura.');
        }

        return $items;
    }

    private function buildPaymentPayload(array $source, array $invoice, string $paymentDate, string $referencePrefix): array
    {
        $currency = trim((string) ($source['payment_currency_code'] ?? $source['currency_code'] ?? ($invoice['currency_code'] ?? secondary_currency())));
        $rate = system_exchange_rate($paymentDate);
        $amount = round_money(parse_money_input($source['payment_amount_original'] ?? $source['amount_original'] ?? 0));
        $paymentMethod = strtolower(trim((string) ($source['payment_method'] ?? 'cash')));
        if (!array_key_exists($paymentMethod, payment_method_options())) {
            throw new \RuntimeException('Debes seleccionar un metodo de pago valido.');
        }

        $reference = trim((string) ($source['payment_reference'] ?? $source['reference'] ?? ''));
        if ($reference === '') {
            $reference = default_payment_reference($paymentMethod, $referencePrefix);
        }

        $documentCurrency = (string) ($invoice['currency_code'] ?? $currency);
        $availableOriginal = round_money((float) ($invoice['balance_original'] ?? 0));
        $appliedOriginal = round_money(convert_currency_amount($amount, $currency, $documentCurrency, $rate));
        $availableInPaymentCurrency = round_money(
            convert_currency_amount($availableOriginal, $documentCurrency, $currency, $rate),
        );
        $difference = money_difference($appliedOriginal, $availableOriginal);
        $paymentCurrencyDifference = money_difference($amount, $availableInPaymentCurrency);
        $matchesDisplayedBalance = abs($amount - $availableInPaymentCurrency) <= 0.01
            || abs($appliedOriginal - $availableOriginal) <= 0.01;
        $roundingTolerance = payment_rounding_tolerance();

        if (
            $matchesDisplayedBalance
            || ($difference > 0 && $difference <= $roundingTolerance)
            || ($paymentCurrencyDifference > 0 && $paymentCurrencyDifference <= $roundingTolerance)
        ) {
            $amount = $availableInPaymentCurrency;
            $appliedOriginal = $availableOriginal;
        } elseif (
            payment_exceeds_balance($appliedOriginal, $availableOriginal, $roundingTolerance)
            || payment_exceeds_balance($amount, $availableInPaymentCurrency, $roundingTolerance)
        ) {
            throw new \RuntimeException(
                'El pago excede el saldo pendiente de la factura. Disponible: '
                . money($availableOriginal) . ' ' . $documentCurrency
                . ' / ' . money($availableInPaymentCurrency) . ' ' . $currency . '.'
            );
        }

        $appliedConverted = round_money(equivalent_in_bolivars($appliedOriginal, $documentCurrency, (float) ($invoice['exchange_rate'] ?? $rate)));
        $paymentConverted = round_money(equivalent_in_bolivars($amount, $currency, $rate));

        return [
            'payment_date' => $paymentDate,
            'reference' => $reference,
            'payment_method' => $paymentMethod,
            'currency_code' => $currency,
            'exchange_rate' => $rate,
            'amount_original' => $amount,
            'amount_converted' => $paymentConverted,
            'applied_original' => $appliedOriginal,
            'applied_converted' => $appliedConverted,
            'notes' => trim((string) ($source['payment_notes'] ?? $source['notes'] ?? '')),
        ];
    }

    private function resolveHistoryContext(): array
    {
        $user = auth_user() ?? [];
        $role = (string) ($user['role'] ?? '');
        $canFilter = in_array($role, ['administrator', 'general_consultant'], true);

        if (! $canFilter) {
            return [
                'role' => $role,
                'canFilter' => false,
                'filters' => [],
                'limit' => 10,
            ];
        }

        $dateFrom = $this->normalizeHistoryDate((string) ($_GET['date_from'] ?? '')) ?: date('Y-m-01');
        $dateTo = $this->normalizeHistoryDate((string) ($_GET['date_to'] ?? '')) ?: date('Y-m-d');

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'role' => $role,
            'canFilter' => true,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'q' => trim((string) ($_GET['q'] ?? '')),
            ],
            'limit' => null,
        ];
    }

    private function normalizeHistoryDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value) {
            return '';
        }

        return $value;
    }

    private function buildHistoryExportQuery(bool $canFilter, array $filters): string
    {
        if (! $canFilter) {
            return '';
        }

        $query = http_build_query(array_filter([
            'date_from' => (string) ($filters['date_from'] ?? ''),
            'date_to' => (string) ($filters['date_to'] ?? ''),
            'q' => trim((string) ($filters['q'] ?? '')),
        ], static fn ($value): bool => $value !== ''));

        return $query !== '' ? ('?' . $query) : '';
    }
}
