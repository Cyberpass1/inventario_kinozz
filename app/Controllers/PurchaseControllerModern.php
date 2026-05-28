<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PdfService;

class PurchaseControllerModern extends Controller
{
    public function index(): void
    {
        $purchaseModel = new Purchase();
        $filters = $this->resolveHistoryFilters();
        $purchases = $purchaseModel->all($filters);
        $supplierModel = new Supplier();
        $suppliers = $supplierModel->active('name ASC');
        $products = (new Product())->purchasableList();
        $nextNumber = $purchaseModel->nextNumber();
        $rate = ['rate' => system_exchange_rate(date('Y-m-d')), 'currency_from' => 'USD', 'currency_to' => 'VES'];
        $user = auth_user() ?? [];
        $purchaseDueDays = purchase_due_days();

        $summary = [
            'operations' => count($purchases),
            'total' => array_reduce(
                array_filter($purchases, fn (array $row): bool => ($row['status'] ?? 'active') !== 'cancelled'),
                fn (float $carry, array $row): float => $carry + equivalent_in_bolivars(
                    (float) ($row['total_original'] ?? 0),
                    (string) ($row['currency_code'] ?? ''),
                    (float) ($row['exchange_rate'] ?? 0)
                ),
                0.0
            ),
            'outstanding' => array_reduce(
                array_filter($purchases, fn (array $row): bool => ($row['status'] ?? 'active') !== 'cancelled'),
                fn (float $carry, array $row): float => $carry + (float) ($row['balance_converted'] ?? 0),
                0.0
            ),
        ];

        $purchaseFilters = $filters;
        $this->view('purchases/workspace', compact('purchases', 'suppliers', 'products', 'nextNumber', 'rate', 'summary', 'user', 'purchaseDueDays', 'purchaseFilters'), 'layouts/app_modern');
    }

    public function exportHistory(): void
    {
        $purchases = (new Purchase())->all($this->resolveHistoryFilters());
        $fileName = 'historial-compras-' . date('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $stream = fopen('php://output', 'wb');
        if ($stream === false) {
            http_response_code(500);
            exit('No se pudo generar la exportacion.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'Fecha',
            'Documento',
            'Proveedor',
            'Productos',
            'Renglones',
            'Cantidad total',
            'Moneda',
            'Tasa de cierre',
            'Total documento',
            'Equiv. Bs',
            'Estado',
        ], ';');

        foreach ($purchases as $purchase) {
            fputcsv($stream, [
                (string) ($purchase['purchase_date'] ?? ''),
                (string) ($purchase['doc_number'] ?? ''),
                (string) ($purchase['supplier_name'] ?? ''),
                (string) ($purchase['products_summary'] ?? ''),
                (string) ($purchase['line_count'] ?? 0),
                money($purchase['total_quantity'] ?? 0),
                (string) ($purchase['currency_code'] ?? ''),
                money($purchase['exchange_rate'] ?? 0),
                money($purchase['total_original'] ?? 0),
                money(equivalent_in_bolivars(
                    (float) ($purchase['total_original'] ?? 0),
                    (string) ($purchase['currency_code'] ?? ''),
                    (float) ($purchase['exchange_rate'] ?? 0)
                )) . ' ' . secondary_currency(),
                (($purchase['status'] ?? 'active') === 'cancelled') ? 'Anulada' : 'Activa',
            ], ';');
        }

        fclose($stream);
        exit;
    }

    public function storeSupplier(): void
    {
        validate_csrf();

        (new Supplier())->insert([
            'name' => trim($_POST['name']),
            'document' => trim($_POST['document'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'is_active' => 1,
        ]);

        flash('success', 'Proveedor creado.');
        $this->redirect('/purchases');
    }

    public function store(): void
    {
        validate_csrf();

        try {
        [$header, $items] = $this->buildPurchasePayload($_POST);
            $initialPayment = null;

            if ((float) ($_POST['payment_amount_original'] ?? 0) > 0) {
                $initialPayment = $this->buildPaymentPayload($_POST, [
                    'currency_code' => $header['currency_code'],
                    'exchange_rate' => $header['exchange_rate'],
                    'total_original' => $header['total_original'],
                    'total_converted' => $header['total_converted'],
                    'balance_original' => $header['total_original'],
                    'balance_converted' => $header['total_converted'],
                ], (string) ($header['purchase_date'] ?? date('Y-m-d')), 'Pago inicial');
            }

            $purchaseId = (new Purchase())->create($header, $items);

            if ($initialPayment !== null) {
                (new Purchase())->registerPayment($purchaseId, $initialPayment);
            }

            $successMessage = $initialPayment !== null
                ? 'Compra registrada, inventario actualizado y pago inicial aplicado.'
                : 'Compra registrada e inventario actualizado. Puedes consultarla luego en la tabla.';

            if ($this->wantsJson()) {
                $this->json([
                    'ok' => true,
                    'message' => $successMessage,
                    'redirect' => app_url('/purchases'),
                ]);
            }

            flash('success', $successMessage);
        } catch (\Throwable $exception) {
            if ($this->wantsJson()) {
                $this->json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }
            flash('error', $exception->getMessage());
        }

        $this->redirect('/purchases');
    }

    public function update(string $id): void
    {
        validate_csrf();

        try {
            $purchaseId = (int) $id;
            $purchase = (new Purchase())->findFull($purchaseId);
            if (! $purchase) {
                throw new \RuntimeException('Compra no encontrada.');
            }

            $this->validateAdministratorConfirmation(trim((string) ($_POST['admin_password'] ?? '')));
            [$header, $items] = $this->buildPurchasePayload($_POST, true);

            (new Purchase())->updatePurchase($purchaseId, $header, $items);

            if ($this->wantsJson()) {
                $this->json([
                    'ok' => true,
                    'message' => 'Compra actualizada correctamente.',
                    'redirect' => app_url('/purchases'),
                ]);
            }

            flash('success', 'Compra actualizada correctamente.');
        } catch (\Throwable $exception) {
            if ($this->wantsJson()) {
                $this->json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }
            flash('error', $exception->getMessage());
        }

        $this->redirect('/purchases');
    }

    public function cancel(string $id): void
    {
        validate_csrf();

        try {
            (new Purchase())->cancel((int) $id, trim((string) ($_POST['reason'] ?? '')));
            flash('success', 'Compra anulada y stock revertido.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/purchases');
    }

    public function delete(string $id): void
    {
        validate_csrf();

        try {
            $purchaseId = (int) $id;
            $purchaseModel = new Purchase();
            $purchase = $purchaseModel->findFull($purchaseId);
            if (! $purchase) {
                throw new \RuntimeException('Compra no encontrada.');
            }

            $this->validateAdministratorConfirmation(trim((string) ($_POST['admin_password'] ?? '')));

            $expectedDocument = trim((string) ($purchase['doc_number'] ?? ''));
            $confirmedDocument = trim((string) ($_POST['confirm_doc_number'] ?? ''));
            if ($expectedDocument === '' || $confirmedDocument !== $expectedDocument) {
                throw new \RuntimeException('La segunda validacion fallo. Debes escribir el numero de documento exacto.');
            }

            $purchaseModel->deletePurchase($purchaseId, 'Eliminacion confirmada por administrador');
            flash('success', 'Compra eliminada y stock revertido.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/purchases');
    }

    public function print(string $id): void
    {
        $purchase = (new Purchase())->findFull((int) $id);
        $this->view('purchases/print', ['purchase' => $purchase, 'backUrl' => '/purchases'], 'layouts/print');
    }

    public function pdf(string $id): void
    {
        $purchase = (new Purchase())->findFull((int) $id);
        (new PdfService())->purchase($purchase ?? []);
    }

    public function editModal(string $id): void
    {
        $purchase = (new Purchase())->findFull((int) $id);
        if (! $purchase) {
            $this->json([
                'ok' => false,
                'message' => 'Compra no encontrada.',
            ], 404);
        }

        $html = $this->renderPurchaseEditModalContent(
            $purchase,
            (new Supplier())->all('name ASC'),
            (new Product())->purchasableList(),
            purchase_due_days(),
        );

        $this->json([
            'ok' => true,
            'html' => $html,
            'title' => 'Editar ' . (string) ($purchase['doc_number'] ?? ''),
        ]);
    }

    public function registerPayment(string $id): void
    {
        validate_csrf();

        try {
            $purchaseId = (int) $id;
            $purchase = (new Purchase())->findFull($purchaseId);
            if (! $purchase) {
                throw new \RuntimeException('Compra no encontrada.');
            }

            $paymentDate = (string) ($_POST['payment_date'] ?? date('Y-m-d'));
            (new Purchase())->registerPayment($purchaseId, $this->buildPaymentPayload($_POST, $purchase, $paymentDate));

            if ($this->wantsJson()) {
                $this->json([
                    'ok' => true,
                    'message' => 'Pago a proveedor registrado correctamente.',
                    'redirect' => app_url('/purchases'),
                ]);
            }

            flash('success', 'Pago a proveedor registrado correctamente.');
        } catch (\Throwable $exception) {
            if ($this->wantsJson()) {
                $this->json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }
            flash('error', $exception->getMessage());
        }

        $this->redirect('/purchases');
    }

    private function toBolivarAmount(float $amount, string $currency, float $rate): float
    {
        return equivalent_in_bolivars($amount, $currency, $rate);
    }

    private function buildPaymentPayload(array $source, array $purchase, string $paymentDate, string $referencePrefix = 'Pago'): array
    {
        $currency = trim((string) ($source['payment_currency_code'] ?? $source['currency_code'] ?? ($purchase['currency_code'] ?? secondary_currency())));
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

        $documentCurrency = (string) ($purchase['currency_code'] ?? $currency);
        $availableOriginal = round_money((float) ($purchase['balance_original'] ?? 0));
        $appliedOriginal = round_money(convert_currency_amount($amount, $currency, $documentCurrency, $rate));
        $availableInPaymentCurrency = round_money(
            convert_currency_amount($availableOriginal, $documentCurrency, $currency, $rate)
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
            throw new \RuntimeException('El pago excede el saldo pendiente de la compra.');
        }

        $appliedConverted = round_money(equivalent_in_bolivars($appliedOriginal, $documentCurrency, (float) ($purchase['exchange_rate'] ?? $rate)));
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

    private function buildPurchasePayload(array $source, bool $allowArchivedProducts = false): array
    {
        $documentDate = (string) ($source['purchase_date'] ?? date('Y-m-d'));
        $dueDate = document_due_date($documentDate, purchase_due_days());
        $rate = system_exchange_rate($documentDate);
        $currency = trim((string) ($source['currency_code'] ?? secondary_currency()));
        $items = $this->extractItems($source, $currency, $rate, $allowArchivedProducts);
        $subtotalOriginal = array_reduce(
            $items,
            static fn (float $carry, array $item): float => $carry + (float) ($item['total_original'] ?? 0),
            0.0
        );
        $subtotalConverted = array_reduce(
            $items,
            static fn (float $carry, array $item): float => $carry + (float) ($item['total_converted'] ?? 0),
            0.0
        );

        return [[
            'supplier_id' => (int) ($source['supplier_id'] ?? 0),
            'doc_number' => trim((string) ($source['doc_number'] ?? '')),
            'purchase_date' => $documentDate,
            'due_date' => $dueDate,
            'currency_code' => $currency,
            'exchange_rate' => $rate,
            'subtotal_original' => $subtotalOriginal,
            'total_original' => $subtotalOriginal,
            'subtotal_converted' => $subtotalConverted,
            'total_converted' => $subtotalConverted,
            'notes' => trim((string) ($source['notes'] ?? '')),
        ], $items];
    }

    private function extractItems(array $source, string $documentCurrency, float $rate, bool $allowArchivedProducts = false): array
    {
        $productModel = new Product();
        $rows = $source['items'] ?? [];
        if (!is_array($rows) || $rows === []) {
            $rows = [[
                'product_id' => $source['product_id'] ?? '',
                'quantity' => $source['quantity'] ?? '',
                'cost_original' => $source['cost_original'] ?? '',
                'source_currency' => $source['source_currency'] ?? base_currency(),
            ]];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $customName = trim((string) ($row['custom_name'] ?? ''));
            $hasAnyValue = trim((string) ($row['product_id'] ?? '')) !== ''
                || $customName !== ''
                || trim((string) ($row['quantity'] ?? '')) !== ''
                || trim((string) ($row['cost_original'] ?? '')) !== '';
            if (! $hasAnyValue) {
                continue;
            }

            $productId = (int) ($row['product_id'] ?? 0);

            if ($productId <= 0 && $customName !== '') {
                $product = $this->createCustomProduct($productModel, $row);
                $productId = (int) ($product['id'] ?? 0);
            } else {
                if ($productId <= 0) {
                    throw new \RuntimeException('Cada renglon debe tener un producto valido.');
                }

                $product = $productModel->findVisible($productId);
                if (! $product && $allowArchivedProducts) {
                    $product = $productModel->findAny($productId);
                }
            }

            if (! $product || ! product_is_purchasable($product)) {
                throw new \RuntimeException('Solo puedes comprar materias primas, productos directos o productos fabricados.');
            }

            $quantity = (float) ($row['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw new \RuntimeException('La cantidad de cada producto debe ser mayor a cero.');
            }

            $costReference = (float) ($row['cost_original'] ?? 0);
            if ($costReference < 0) {
                throw new \RuntimeException('El costo unitario no puede ser negativo.');
            }

            $sourceCurrency = trim((string) ($row['source_currency'] ?? base_currency()));
            if ($sourceCurrency === '') {
                $sourceCurrency = base_currency();
            }

            $costOriginal = convert_currency_amount($costReference, $sourceCurrency, $documentCurrency, $rate);
            $costConverted = equivalent_in_bolivars($costOriginal, $documentCurrency, $rate);
            $lineTotalOriginal = $quantity * $costOriginal;
            $lineTotalConverted = $quantity * $costConverted;

            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'cost_original' => $costOriginal,
                'cost_converted' => $costConverted,
                'total_original' => $lineTotalOriginal,
                'total_converted' => $lineTotalConverted,
            ];
        }

        if ($items === []) {
            throw new \RuntimeException('Debes agregar al menos un producto para registrar la compra.');
        }

        return $items;
    }

    private function validateAdministratorConfirmation(string $password): void
    {
        $sessionUser = auth_user();
        if (! $sessionUser || (string) ($sessionUser['role'] ?? '') !== 'administrator') {
            throw new \RuntimeException('Solo un administrador puede confirmar esta accion.');
        }

        if ($password === '') {
            throw new \RuntimeException('Debes ingresar tu contrasena de administrador para confirmar.');
        }

        $user = (new User())->findByUsername((string) ($sessionUser['username'] ?? ''));
        if (! $user || !password_verify($password, (string) ($user['password'] ?? ''))) {
            throw new \RuntimeException('La segunda validacion no coincide con la contrasena del administrador.');
        }
    }

    private function resolveHistoryFilters(): array
    {
        return [
            'search' => trim((string) ($_GET['q'] ?? '')),
            'date_from' => trim((string) ($_GET['from'] ?? '')),
            'date_to' => trim((string) ($_GET['to'] ?? '')),
        ];
    }

    private function createCustomProduct(Product $productModel, array $row): array
    {
        $name = trim((string) ($row['custom_name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('El producto nuevo necesita un nombre.');
        }

        $sku = strtoupper(trim((string) ($row['custom_sku'] ?? '')));
        $type = strtolower(trim((string) ($row['custom_product_type'] ?? 'merchandise')));
        if (! in_array($type, ['merchandise', 'raw_material'], true)) {
            $type = 'merchandise';
        }
        $unitLabel = trim((string) ($row['custom_unit_label'] ?? 'und'));
        if ($unitLabel === '') {
            $unitLabel = 'und';
        }

        if ($sku === '') {
            $prefix = $type === 'raw_material' ? 'MP' : 'PR';
            $attempts = 0;
            do {
                $candidate = $prefix . '-' . strtoupper(substr(uniqid('', false), -5));
                $attempts++;
            } while ($productModel->skuExists($candidate) && $attempts < 10);
            $sku = $candidate;
        } elseif ($productModel->skuExists($sku)) {
            throw new \RuntimeException('Ya existe un producto con el SKU "' . $sku . '". Elige otro o deja el campo vacio para generar uno automatico.');
        }

        $newId = $productModel->insert([
            'sku' => $sku,
            'name' => $name,
            'product_type' => $type,
            'unit_label' => $unitLabel,
            'stock' => 0,
            'stock_min' => 0,
            'cost' => (float) ($row['cost_original'] ?? 0),
            'price' => 0,
            'currency_code' => base_currency(),
            'status' => 'active',
        ]);

        $product = $productModel->findVisible((int) $newId);
        if (! $product) {
            throw new \RuntimeException('No se pudo crear el producto "' . $name . '".');
        }

        return $product;
    }

    private function renderPurchaseEditModalContent(array $detail, array $suppliersAll, array $products, int $purchaseDueDays): string
    {
        $productsById = [];
        foreach ($products as $product) {
            $productsById[(int) ($product['id'] ?? 0)] = $product;
        }

        ob_start();
        require dirname(__DIR__) . '/Views/purchases/_edit_modal_content.php';
        return trim((string) ob_get_clean());
    }
}
