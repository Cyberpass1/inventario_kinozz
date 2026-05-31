<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\PdfService;

class QuotationControllerModern extends Controller
{
    public function index(): void
    {
        $quotationModel = new Quotation();
        ['role' => $currentRole, 'canFilter' => $canFilterHistory, 'filters' => $historyFilters, 'limit' => $historyLimit] = $this->resolveHistoryContext();
        $quotations = $quotationModel->history($historyFilters, $historyLimit);
        $clientHints = (new Client())->search('', 8);
        $products = (new Product())->sellableList();
        $nextNumber = $quotationModel->nextNumber();
        $rate = ['rate' => system_exchange_rate(date('Y-m-d')), 'currency_from' => base_currency(), 'currency_to' => secondary_currency()];
        $quoteValidDays = invoice_due_days();
        $historyExportQuery = $this->buildHistoryExportQuery($canFilterHistory, $historyFilters);

        $summary = [
            'operations' => count($quotations),
            'total' => array_reduce(
                array_filter($quotations, fn (array $row): bool => ($row['status'] ?? 'open') !== 'cancelled'),
                fn (float $carry, array $row): float => $carry + (float) ($row['total_converted'] ?? 0),
                0.0
            ),
            'converted' => count(array_filter(
                $quotations,
                static fn (array $row): bool => in_array((string) ($row['status'] ?? 'open'), ['converted_invoice', 'converted_delivery', 'converted_both'], true)
            )),
        ];

        $this->view('quotations/workspace', compact('quotations', 'clientHints', 'products', 'nextNumber', 'rate', 'summary', 'quoteValidDays', 'canFilterHistory', 'historyFilters', 'historyExportQuery', 'currentRole'), 'layouts/app_modern');
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
        $this->redirect('/quotations');
    }

    public function store(): void
    {
        validate_csrf();

        try {
            $clientId = (int) ($_POST['client_id'] ?? 0);
            if ($clientId <= 0) {
                throw new \RuntimeException('Debes seleccionar un cliente valido antes de cotizar.');
            }

            $documentDate = (string) ($_POST['quotation_date'] ?? date('Y-m-d'));
            $validUntil = document_due_date($documentDate, invoice_due_days());
            $rate = system_exchange_rate($documentDate);
            $currency = trim((string) ($_POST['currency_code'] ?? secondary_currency()));
            $items = $this->extractItems($_POST, $currency, $rate);
            $subtotalOriginal = round_money(array_reduce(
                $items,
                static fn (float $carry, array $item): float => $carry + (float) ($item['total_original'] ?? 0),
                0.0
            ));
            $subtotalConverted = round_money(array_reduce(
                $items,
                static fn (float $carry, array $item): float => $carry + (float) ($item['total_converted'] ?? 0),
                0.0
            ));

            $quotationId = (new Quotation())->create([
                'client_id' => $clientId,
                'quotation_number' => trim((string) ($_POST['quotation_number'] ?? '')),
                'quotation_date' => $documentDate,
                'valid_until' => $validUntil,
                'currency_code' => $currency,
                'exchange_rate' => $rate,
                'subtotal_original' => $subtotalOriginal,
                'total_original' => $subtotalOriginal,
                'subtotal_converted' => $subtotalConverted,
                'total_converted' => $subtotalConverted,
                'notes' => trim((string) ($_POST['notes'] ?? '')),
            ], $items);

            $documentPrompt = [
                'title' => 'Cotizacion registrada',
                'text' => 'Deseas abrir el reporte en vista previa de impresion?',
                'url' => app_url('/quotations/pdf/' . $quotationId),
                'confirm' => 'Abrir reporte',
                'cancel' => 'Seguir aqui',
            ];

            if ($this->wantsJson()) {
                $this->json([
                    'ok' => true,
                    'message' => 'Cotizacion registrada correctamente.',
                    'redirect' => app_url('/quotations'),
                    'document_prompt' => $documentPrompt,
                ]);
            }

            flash('success', 'Cotizacion registrada correctamente.');
            flash('document_prompt', json_encode($documentPrompt, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $exception) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }
            flash('error', $exception->getMessage());
        }

        $this->redirect('/quotations');
    }

    public function convertToDeliveryNote(string $id): void
    {
        validate_csrf();

        try {
            $noteId = (new Quotation())->convertToDeliveryNote((int) $id);
            flash('success', 'Cotizacion convertida en nota de entrega.');
            $this->redirect('/delivery-notes/pdf/' . $noteId . '?currency=' . rawurlencode(secondary_currency()));
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('/quotations');
        }
    }

    public function convertToInvoice(string $id): void
    {
        validate_csrf();

        try {
            $invoiceId = (new Quotation())->convertToInvoice((int) $id);
            flash('success', 'Cotizacion convertida en factura.');
            $this->redirect('/invoices/pdf/' . $invoiceId);
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('/quotations');
        }
    }

    public function cancel(string $id): void
    {
        validate_csrf();

        try {
            (new Quotation())->cancel((int) $id, trim((string) ($_POST['reason'] ?? '')));
            flash('success', 'Cotizacion anulada.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/quotations');
    }

    public function pdf(string $id): void
    {
        $quotation = (new Quotation())->findFull((int) $id);
        (new PdfService())->quotation($quotation ?? []);
    }

    public function details(string $id): void
    {
        $quotation = (new Quotation())->findFull((int) $id);
        if (! $quotation) {
            $this->json(['ok' => false, 'message' => 'Cotizacion no encontrada.'], 404);
        }

        $this->json([
            'ok' => true,
            'detail' => [
                'id' => (int) ($quotation['id'] ?? 0),
                'number' => (string) ($quotation['quotation_number'] ?? ''),
                'client_name' => (string) ($quotation['client_name'] ?? ''),
                'date' => (string) ($quotation['quotation_date'] ?? ''),
                'currency_code' => (string) ($quotation['currency_code'] ?? ''),
                'line_count' => count($quotation['items'] ?? []),
                'total_original' => (float) ($quotation['total_original'] ?? 0),
                'notes' => trim((string) ($quotation['notes'] ?? '')),
                'items' => array_map(static fn (array $item): array => [
                    'product_name' => (string) ($item['product_name'] ?? 'Producto'),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'price_original' => (float) ($item['price_original'] ?? 0),
                    'total_original' => (float) ($item['total_original'] ?? 0),
                ], is_array($quotation['items'] ?? null) ? $quotation['items'] : []),
            ],
        ]);
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
                throw new \RuntimeException('Cada renglon debe tener un producto o servicio valido.');
            }

            $product = $productModel->findVisible($productId);
            if (! $product || ! product_is_saleable($product)) {
                throw new \RuntimeException('Solo puedes cotizar productos vendibles o servicios.');
            }

            $quantity = (float) ($row['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw new \RuntimeException('La cantidad de cada renglon debe ser mayor a cero.');
            }

            $priceReference = (float) ($row['price_original'] ?? 0);
            if ($priceReference < 0) {
                throw new \RuntimeException('El precio unitario no puede ser negativo.');
            }

            $sourceCurrency = trim((string) ($row['source_currency'] ?? base_currency()));
            if ($sourceCurrency === '') {
                $sourceCurrency = base_currency();
            }

            $priceOriginal = round_money(convert_currency_amount($priceReference, $sourceCurrency, $documentCurrency, $rate));
            $priceConverted = round_money(equivalent_in_bolivars($priceOriginal, $documentCurrency, $rate));
            $lineTotalOriginal = round_money($quantity * $priceOriginal);
            $lineTotalConverted = round_money($quantity * $priceConverted);

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
            throw new \RuntimeException('Debes agregar al menos un producto o servicio para registrar la cotizacion.');
        }

        return $items;
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
            'q' => trim((string) ($_GET['q'] ?? '')),
        ], static fn ($value): bool => $value !== ''));

        return $query !== '' ? ('?' . $query) : '';
    }
}
