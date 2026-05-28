<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Client;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Services\PdfService;

class DeliveryNoteControllerModern extends Controller
{
    public function index(): void
    {
        $noteModel = new DeliveryNote();
        ['role' => $currentRole, 'canFilter' => $canFilterHistory, 'filters' => $historyFilters, 'limit' => $historyLimit] = $this->resolveHistoryContext();
        $notes = $noteModel->history($historyFilters, $historyLimit);
        $clientHints = (new Client())->search('', 8);
        $products = (new Product())->sellableList();
        $nextNumber = $noteModel->nextNumber();
        $rate = ['rate' => system_exchange_rate(date('Y-m-d')), 'currency_from' => 'USD', 'currency_to' => 'VES'];
        $noteDueDays = invoice_due_days();
        $historyExportQuery = $this->buildHistoryExportQuery($canFilterHistory, $historyFilters);

        $summary = [
            'operations' => count($notes),
            'total' => array_reduce(
                array_filter($notes, fn (array $row): bool => ($row['status'] ?? 'active') !== 'cancelled'),
                fn (float $carry, array $row): float => $carry + equivalent_in_bolivars(
                    (float) ($row['total_original'] ?? 0),
                    (string) ($row['currency_code'] ?? ''),
                    (float) ($row['exchange_rate'] ?? 0)
                ),
                0.0
            ),
            'outstanding' => array_reduce(
                array_filter($notes, fn (array $row): bool => ($row['status'] ?? 'active') !== 'cancelled'),
                fn (float $carry, array $row): float => $carry + (float) ($row['balance_converted'] ?? 0),
                0.0
            ),
        ];

        $this->view('delivery_notes/workspace', compact('notes', 'clientHints', 'products', 'nextNumber', 'rate', 'summary', 'noteDueDays', 'canFilterHistory', 'historyFilters', 'historyExportQuery', 'currentRole'), 'layouts/app_modern');
    }

    public function exportHistory(): void
    {
        ['canFilter' => $canFilterHistory, 'filters' => $historyFilters, 'limit' => $historyLimit] = $this->resolveHistoryContext();
        $notes = (new DeliveryNote())->history($historyFilters, $historyLimit);
        $fileName = 'historial-notas-entrega-' . date('Y-m-d-His') . '.csv';

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
            'Total documento',
            'Equiv. Bs',
            'Estado',
            'Notas',
        ], ';');

        foreach ($notes as $note) {
            fputcsv($stream, [
                (string) ($note['note_date'] ?? ''),
                (string) ($note['note_number'] ?? ''),
                (string) ($note['client_name'] ?? ''),
                (string) ($note['client_document'] ?? ''),
                (string) ($note['products_summary'] ?? ''),
                (string) ($note['line_count'] ?? 0),
                money($note['total_quantity'] ?? 0),
                (string) ($note['currency_code'] ?? ''),
                money($note['exchange_rate'] ?? 0),
                money($note['total_original'] ?? 0),
                money(equivalent_in_bolivars(
                    (float) ($note['total_original'] ?? 0),
                    (string) ($note['currency_code'] ?? ''),
                    (float) ($note['exchange_rate'] ?? 0)
                )) . ' ' . secondary_currency(),
                (($note['status'] ?? 'active') === 'cancelled') ? 'Anulada' : 'Activa',
                (string) ($note['notes'] ?? ''),
            ], ';');
        }

        fclose($stream);
        exit;
    }

    public function store(): void
    {
        validate_csrf();

        try {
            $clientId = (int) ($_POST['client_id'] ?? 0);
            if ($clientId <= 0) {
                throw new \RuntimeException('Debes seleccionar un cliente valido antes de registrar la nota.');
            }
            $documentDate = (string) ($_POST['note_date'] ?? date('Y-m-d'));
            $dueDate = document_due_date($documentDate, invoice_due_days());
            $rate = system_exchange_rate($documentDate);
            $currency = trim((string) ($_POST['currency_code'] ?? secondary_currency()));
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
            $initialPayment = null;

            if (parse_money_input($_POST['payment_amount_original'] ?? 0) > 0) {
                $initialPayment = $this->buildPaymentPayload($_POST, [
                    'currency_code' => $currency,
                    'exchange_rate' => $rate,
                    'total_original' => $subtotalOriginal,
                    'total_converted' => $subtotalConverted,
                    'balance_original' => $subtotalOriginal,
                    'balance_converted' => $subtotalConverted,
                ], $documentDate, 'Cobro inicial');
            }

            $noteId = (new DeliveryNote())->create([
                'client_id' => $clientId,
                'note_number' => trim($_POST['note_number']),
                'note_date' => $documentDate,
                'due_date' => $dueDate,
                'currency_code' => $currency,
                'exchange_rate' => $rate,
                'subtotal_original' => $subtotalOriginal,
                'total_original' => $subtotalOriginal,
                'subtotal_converted' => $subtotalConverted,
                'total_converted' => $subtotalConverted,
                'notes' => trim($_POST['notes'] ?? ''),
            ], $items);

            if ($initialPayment !== null) {
                (new DeliveryNote())->registerPayment($noteId, $initialPayment);
            }

            $successMessage = 'Nota de entrega registrada. Puedes consultarla luego en la tabla.';
            $documentPrompt = [
                'title' => 'Nota de entrega registrada',
                'text' => 'Deseas abrir el reporte en vista previa de impresion?',
                'url' => app_url('/delivery-notes/pdf/' . $noteId),
                'confirm' => 'Abrir reporte',
                'cancel' => 'Seguir aqui',
            ];

            if ($this->wantsJson()) {
                $this->json([
                    'ok' => true,
                    'message' => $successMessage,
                    'redirect' => app_url('/delivery-notes'),
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

        $this->redirect('/delivery-notes');
    }

    public function registerPayment(string $id): void
    {
        validate_csrf();

        try {
            $noteId = (int) $id;
            $note = (new DeliveryNote())->findFull($noteId);
            if (! $note) {
                throw new \RuntimeException('Nota de entrega no encontrada.');
            }

            $paymentDate = (string) ($_POST['payment_date'] ?? date('Y-m-d'));
            (new DeliveryNote())->registerPayment($noteId, $this->buildPaymentPayload($_POST, $note, $paymentDate, 'Cobro'));

            if ($this->wantsJson()) {
                $this->json([
                    'ok' => true,
                    'message' => 'Cobro registrado correctamente en la nota de entrega.',
                    'redirect' => app_url('/delivery-notes'),
                ]);
            }

            flash('success', 'Cobro registrado correctamente en la nota de entrega.');
        } catch (\Throwable $exception) {
            if ($this->wantsJson()) {
                $this->json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }
            flash('error', $exception->getMessage());
        }

        $this->redirect('/delivery-notes');
    }

    public function cancel(string $id): void
    {
        validate_csrf();

        try {
            (new DeliveryNote())->cancel((int) $id, trim((string) ($_POST['reason'] ?? '')));
            flash('success', 'Nota de entrega anulada y stock reintegrado.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/delivery-notes');
    }

    public function print(string $id): void
    {
        $note = (new DeliveryNote())->findFull((int) $id);
        $this->view('delivery_notes/print', ['note' => $note, 'backUrl' => '/delivery-notes'], 'layouts/print');
    }

    public function pdf(string $id): void
    {
        $note = (new DeliveryNote())->findFull((int) $id);
        (new PdfService())->deliveryNote($note ?? []);
    }

    public function details(string $id): void
    {
        $note = (new DeliveryNote())->findFull((int) $id);
        if (! $note) {
            $this->json([
                'ok' => false,
                'message' => 'Nota de entrega no encontrada.',
            ], 404);
        }

        $this->json([
            'ok' => true,
            'detail' => [
                'id' => (int) ($note['id'] ?? 0),
                'number' => (string) ($note['note_number'] ?? ''),
                'client_name' => (string) ($note['client_name'] ?? ''),
                'date' => (string) ($note['note_date'] ?? ''),
                'currency_code' => (string) ($note['currency_code'] ?? ''),
                'line_count' => (int) ($note['line_count'] ?? count($note['items'] ?? [])),
                'total_original' => (float) ($note['total_original'] ?? 0),
                'notes' => trim((string) ($note['notes'] ?? '')),
                'products_summary' => (string) ($note['products_summary'] ?? ''),
                'items' => array_map(static fn (array $item): array => [
                    'product_name' => (string) ($item['product_name'] ?? 'Producto'),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'price_original' => (float) ($item['price_original'] ?? 0),
                    'total_original' => (float) ($item['total_original'] ?? 0),
                ], is_array($note['items'] ?? null) ? $note['items'] : []),
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
                throw new \RuntimeException('Solo puedes registrar productos vendibles o servicios.');
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
            throw new \RuntimeException('Debes agregar al menos un producto para registrar la nota.');
        }

        return $items;
    }

    private function buildPaymentPayload(array $source, array $note, string $paymentDate, string $referencePrefix): array
    {
        $currency = trim((string) ($source['payment_currency_code'] ?? $source['currency_code'] ?? ($note['currency_code'] ?? secondary_currency())));
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

        $documentCurrency = (string) ($note['currency_code'] ?? $currency);
        $availableOriginal = round_money((float) ($note['balance_original'] ?? 0));
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
                'El pago excede el saldo pendiente de la nota de entrega. Disponible: '
                . money($availableOriginal) . ' ' . $documentCurrency
                . ' / ' . money($availableInPaymentCurrency) . ' ' . $currency . '.'
            );
        }

        $appliedConverted = round_money(equivalent_in_bolivars($appliedOriginal, $documentCurrency, (float) ($note['exchange_rate'] ?? $rate)));
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
