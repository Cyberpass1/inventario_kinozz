<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Database;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;

class InventoryControllerModern extends Controller
{
    public function index(): void
    {
        $skuOrder = "COALESCE(NULLIF(TRIM(p.sku), ''), p.name) ASC, p.name ASC, c.name ASC, p.id ASC";
        $products = (new Product())->listWithCategory([], $skuOrder);
        $activeProducts = (new Product())->activeListWithCategory($skuOrder);
        $categoryModel = new Category();
        $categories = $categoryModel->all('name ASC');
        $alerts = (new Product())->lowStock();
        $categoryUsage = $categoryModel->usageCounts();
        $inventoryUsd = array_reduce(
            array_filter($activeProducts, static fn (array $product): bool => product_tracks_inventory($product) && (float) ($product['stock'] ?? 0) > 0),
            fn (float $carry, array $product): float => $carry + amount_to_reference_currency(
                ((float) ($product['stock'] ?? 0)) * ((float) ($product['cost'] ?? 0)),
                (string) ($product['currency_code'] ?? base_currency()),
                system_exchange_rate(date('Y-m-d'))
            ),
            0.0
        );
        $inventoryBs = array_reduce(
            array_filter($activeProducts, static fn (array $product): bool => product_tracks_inventory($product) && (float) ($product['stock'] ?? 0) > 0),
            fn (float $carry, array $product): float => $carry + equivalent_in_bolivars(
                ((float) ($product['stock'] ?? 0)) * ((float) ($product['cost'] ?? 0)),
                (string) ($product['currency_code'] ?? base_currency()),
                system_exchange_rate(date('Y-m-d'))
            ),
            0.0
        );

        $summary = [
            'products' => count(array_filter($activeProducts, static fn (array $product): bool => (string) ($product['product_type'] ?? '') !== 'service')),
            'services' => count(array_filter($activeProducts, static fn (array $product): bool => (string) ($product['product_type'] ?? '') === 'service')),
            'low_stock' => count($alerts),
            'inventory_value_usd' => $inventoryUsd,
            'inventory_value_bs' => $inventoryBs,
        ];

        $this->view('inventory/workspace', compact('products', 'categories', 'categoryUsage', 'alerts', 'summary'), 'layouts/app_modern');
    }

    public function storeCategory(): void
    {
        validate_csrf();

        (new Category())->insert([
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description'] ?? ''),
        ]);

        flash('success', 'Categoria creada.');
        $this->redirect('/inventory');
    }

    public function updateCategory(string $id): void
    {
        validate_csrf();

        $categoryModel = new Category();
        $category = $categoryModel->find((int) $id);
        if (! $category) {
            flash('error', 'Categoria no encontrada.');
            $this->redirect('/inventory');
        }

        $categoryModel->update((int) $id, [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
        ]);

        flash('success', 'Categoria actualizada.');
        $this->redirect('/inventory');
    }

    public function deleteCategory(string $id): void
    {
        validate_csrf();

        $categoryModel = new Category();
        $category = $categoryModel->find((int) $id);
        if (! $category) {
            flash('error', 'Categoria no encontrada.');
            $this->redirect('/inventory');
        }

        $categoryModel->delete((int) $id);
        flash('success', 'Categoria eliminada. Los productos relacionados quedaron sin categoria.');
        $this->redirect('/inventory');
    }

    public function storeProduct(): void
    {
        validate_csrf();

        $productModel = new Product();
        $productType = trim((string) ($_POST['product_type'] ?? 'merchandise'));
        $tracksInventory = product_tracks_inventory($productType);
        $sku = trim((string) ($_POST['sku'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0);

        if ($sku === '' || $name === '') {
            flash('error', 'Debes indicar al menos SKU y nombre del producto.');
            $this->redirect('/inventory');
        }

        $skuConflict = $productModel->findBySku($sku);
        if ($skuConflict) {
            $conflictLabel = ! empty($skuConflict['deleted_at'])
                ? 'Existe un producto archivado con ese SKU. Ya no sale en la tabla, pero seguia reservandolo.'
                : 'Ya existe otro producto con ese SKU. Usa uno distinto.';

            flash('error', $conflictLabel);
            $this->redirect('/inventory');
        }

        $id = $productModel->insert([
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'sku' => $sku,
            'name' => $name,
            'description' => trim($_POST['description'] ?? ''),
            'product_type' => $productType,
            'unit_label' => normalize_product_unit((string) ($_POST['unit_label'] ?? ''), $productType),
            'stock' => 0,
            'stock_min' => $tracksInventory ? (float) $_POST['stock_min'] : 0,
            'cost' => (float) $_POST['cost'],
            'price' => (float) $_POST['price'],
            'currency_code' => trim($_POST['currency_code']),
        ]);

        $product = (new Product())->findVisible($id);
        if ($product && product_tracks_inventory($product) && !empty($_POST['initial_stock'])) {
            Inventory::increase($id, (float) $_POST['initial_stock'], 'initial', 'INICIAL', 'Carga inicial del producto');
        }

        flash('success', 'Producto creado.');
        $this->redirect('/inventory');
    }

    public function importProducts(): void
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        if (!CSRF::validate((string) ($payload['_csrf'] ?? ($_POST['_csrf'] ?? '')))) {
            $this->json(['ok' => false, 'message' => 'CSRF invalido. Recarga la pagina e intentalo de nuevo.'], 419);
        }

        $rows = $payload['rows'] ?? [];
        if (!is_array($rows) || $rows === []) {
            $this->json(['ok' => false, 'message' => 'No recibimos filas para importar.'], 422);
        }

        $productModel = new Product();
        $categoryModel = new Category();

        $existingCategories = [];
        foreach ($categoryModel->all('name ASC') as $row) {
            $name = mb_strtolower(trim((string) ($row['name'] ?? '')));
            if ($name !== '') {
                $existingCategories[$name] = (int) ($row['id'] ?? 0);
            }
        }

        $allowedTypes = ['merchandise', 'raw_material', 'finished_good', 'service'];
        $allowedCurrencies = [base_currency(), secondary_currency(), 'USD', 'VES'];

        $db = Database::connection();
        $db->beginTransaction();

        $created = 0;
        $errors = [];

        try {
            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    $errors[] = ['row' => $index + 2, 'message' => 'Fila invalida'];
                    continue;
                }

                $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
                $name = trim((string) ($row['name'] ?? ''));
                if ($sku === '' || $name === '') {
                    $errors[] = ['row' => $index + 2, 'message' => 'SKU y nombre son obligatorios'];
                    continue;
                }

                if ($productModel->findBySku($sku)) {
                    $errors[] = ['row' => $index + 2, 'message' => 'SKU ya existe: ' . $sku];
                    continue;
                }

                $productType = strtolower(trim((string) ($row['product_type'] ?? 'merchandise')));
                if (!in_array($productType, $allowedTypes, true)) {
                    $productType = 'merchandise';
                }

                $currency = strtoupper(trim((string) ($row['currency_code'] ?? base_currency())));
                if (!in_array($currency, $allowedCurrencies, true)) {
                    $currency = base_currency();
                }

                $categoryId = null;
                $categoryName = trim((string) ($row['category'] ?? ''));
                if ($categoryName !== '') {
                    $key = mb_strtolower($categoryName);
                    if (isset($existingCategories[$key])) {
                        $categoryId = $existingCategories[$key];
                    } else {
                        $newId = (int) $categoryModel->insert(['name' => $categoryName, 'description' => '']);
                        if ($newId > 0) {
                            $existingCategories[$key] = $newId;
                            $categoryId = $newId;
                        }
                    }
                }

                $tracksInventory = product_tracks_inventory($productType);
                $unitLabel = normalize_product_unit((string) ($row['unit_label'] ?? ''), $productType);
                $cost = max(0, (float) ($row['cost'] ?? 0));
                $price = max(0, (float) ($row['price'] ?? 0));
                $stockMin = $tracksInventory ? max(0, (float) ($row['stock_min'] ?? 0)) : 0;
                $initialStock = $tracksInventory ? max(0, (float) ($row['initial_stock'] ?? 0)) : 0;

                try {
                    $productId = (int) $productModel->insert([
                        'category_id' => $categoryId,
                        'sku' => $sku,
                        'name' => $name,
                        'description' => trim((string) ($row['description'] ?? '')),
                        'product_type' => $productType,
                        'unit_label' => $unitLabel,
                        'stock' => 0,
                        'stock_min' => $stockMin,
                        'cost' => $cost,
                        'price' => $price,
                        'currency_code' => $currency,
                    ]);

                    if ($productId > 0 && $initialStock > 0) {
                        Inventory::increase($productId, $initialStock, 'initial', 'IMPORTACION', 'Carga inicial por importacion masiva');
                    }

                    $created += 1;
                } catch (\Throwable $rowException) {
                    $errors[] = ['row' => $index + 2, 'message' => $rowException->getMessage()];
                }
            }

            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->json([
                'ok' => false,
                'message' => 'Error durante la importacion: ' . $exception->getMessage(),
                'errors' => $errors,
            ], 422);
        }

        $this->json([
            'ok' => true,
            'created' => $created,
            'errors' => $errors,
            'message' => "Importacion completada. {$created} productos creados.",
        ]);
    }

    public function duplicateProduct(): void
    {
        validate_csrf();

        $productModel = new Product();
        $sourceId = (int) ($_POST['source_product_id'] ?? 0);
        $source = $productModel->findVisible($sourceId);

        if (! $source) {
            flash('error', 'Producto base no encontrado para duplicar.');
            $this->redirect('/inventory');
        }

        try {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($source['name'] ?? '')) . ' Copia';
            }

            $seedSku = trim((string) ($_POST['sku'] ?? ''));
            if ($seedSku === '') {
                $seedSku = trim((string) ($source['sku'] ?? '')) . '-COPIA';
            }

            $productType = (string) ($source['product_type'] ?? 'merchandise');
            $id = $productModel->insert([
                'category_id' => ! empty($source['category_id']) ? (int) $source['category_id'] : null,
                'sku' => $this->generateAvailableSku($productModel, $seedSku),
                'name' => $name,
                'description' => trim((string) ($source['description'] ?? '')),
                'product_type' => $productType,
                'unit_label' => normalize_product_unit((string) ($source['unit_label'] ?? ''), $productType),
                'stock' => 0,
                'stock_min' => product_tracks_inventory($productType) ? (float) ($source['stock_min'] ?? 0) : 0,
                'cost' => (float) ($source['cost'] ?? 0),
                'price' => (float) ($source['price'] ?? 0),
                'currency_code' => trim((string) ($source['currency_code'] ?? base_currency())),
                'status' => 'active',
            ]);

            $initialStock = (float) ($_POST['initial_stock'] ?? 0);
            if ($initialStock > 0 && product_tracks_inventory($productType)) {
                Inventory::increase($id, $initialStock, 'initial', 'DUPLICADO', 'Carga inicial por duplicado de producto');
            }

            flash('success', 'Producto duplicado correctamente.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/inventory');
    }

    public function createVariants(): void
    {
        validate_csrf();

        $productModel = new Product();
        $productType = trim((string) ($_POST['product_type'] ?? 'merchandise'));
        $tracksInventory = product_tracks_inventory($productType);
        $baseName = trim((string) ($_POST['base_name'] ?? ''));
        $skuPrefix = trim((string) ($_POST['sku_prefix'] ?? ''));
        $colors = $this->parseVariantValues((string) ($_POST['variant_colors'] ?? ''));
        $sizes = $this->parseVariantValues((string) ($_POST['variant_sizes'] ?? ''));

        if ($baseName === '') {
            flash('error', 'Debes indicar el nombre base para generar variantes.');
            $this->redirect('/inventory');
        }

        if ($skuPrefix === '') {
            flash('error', 'Debes indicar un prefijo SKU para generar variantes.');
            $this->redirect('/inventory');
        }

        if ($colors === [] && $sizes === []) {
            flash('error', 'Debes indicar al menos colores, tallas o ambos para generar variantes.');
            $this->redirect('/inventory');
        }

        $colors = $colors !== [] ? $colors : [''];
        $sizes = $sizes !== [] ? $sizes : [''];
        $combinations = count($colors) * count($sizes);
        if ($combinations > 500) {
            flash('error', 'La generacion supera el limite de 500 variantes por lote. Divide el proceso en bloques.');
            $this->redirect('/inventory');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $reservedSkus = [];
            $created = 0;
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $stockMin = $tracksInventory ? (float) ($_POST['stock_min'] ?? 0) : 0.0;
            $cost = (float) ($_POST['cost'] ?? 0);
            $price = (float) ($_POST['price'] ?? 0);
            $currencyCode = trim((string) ($_POST['currency_code'] ?? base_currency()));
            $description = trim((string) ($_POST['description'] ?? ''));
            $unitLabel = normalize_product_unit((string) ($_POST['unit_label'] ?? ''), $productType);
            $initialStock = $tracksInventory ? (float) ($_POST['initial_stock'] ?? 0) : 0.0;

            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    $variantName = $this->buildVariantName($baseName, $color, $size);
                    $variantSku = $this->generateAvailableSku(
                        $productModel,
                        $this->buildVariantSkuSeed($skuPrefix, $color, $size),
                        $reservedSkus
                    );

                    $id = $productModel->insert([
                        'category_id' => $categoryId > 0 ? $categoryId : null,
                        'sku' => $variantSku,
                        'name' => $variantName,
                        'description' => $description,
                        'product_type' => $productType,
                        'unit_label' => $unitLabel,
                        'stock' => 0,
                        'stock_min' => $stockMin,
                        'cost' => $cost,
                        'price' => $price,
                        'currency_code' => $currencyCode,
                        'status' => 'active',
                    ]);

                    if ($initialStock > 0 && $tracksInventory) {
                        Inventory::increase($id, $initialStock, 'initial', 'VARIANTES', 'Carga inicial por generacion de variantes');
                    }

                    $created++;
                }
            }

            $db->commit();
            flash('success', 'Se generaron ' . $created . ' variantes correctamente.');
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            flash('error', $exception->getMessage());
        }

        $this->redirect('/inventory');
    }

    public function updateProduct(string $id): void
    {
        validate_csrf();

        $productModel = new Product();
        $product = $productModel->findVisible((int) $id);

        if (! $product) {
            flash('error', 'Producto no encontrado.');
            $this->redirect('/inventory');
        }

        try {
            $productType = trim((string) ($_POST['product_type'] ?? 'merchandise'));
            $tracksInventory = product_tracks_inventory($productType);
            $sku = trim((string) ($_POST['sku'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $categoryId = (int) ($_POST['category_id'] ?? 0);

            if ($sku === '' || $name === '') {
                flash('error', 'Debes indicar al menos SKU y nombre del producto.');
                $this->redirect('/inventory');
            }

            $skuConflict = $productModel->findBySku($sku, (int) $id);
            if ($skuConflict) {
                $conflictLabel = ! empty($skuConflict['deleted_at'])
                    ? 'Existe un producto archivado con ese SKU. No sale en la tabla, pero la base lo estaba reservando.'
                    : 'Ya existe otro producto activo con ese SKU. Debes usar uno distinto.';

                flash('error', $conflictLabel);
                $this->redirect('/inventory');
            }

            $productModel->updateCatalog((int) $id, [
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'sku' => $sku,
                'name' => $name,
                'description' => trim($_POST['description'] ?? ''),
                'product_type' => $productType,
                'unit_label' => normalize_product_unit((string) ($_POST['unit_label'] ?? ''), $productType),
                'stock_min' => $tracksInventory ? (float) $_POST['stock_min'] : 0,
                'cost' => (float) $_POST['cost'],
                'price' => (float) $_POST['price'],
                'currency_code' => trim($_POST['currency_code']),
            ]);

            flash('success', 'Producto actualizado.');
        } catch (\Throwable $exception) {
            $message = trim((string) $exception->getMessage());

            if ($message === '') {
                $message = 'No se pudo actualizar el producto. Verifica SKU y datos ingresados.';
            }

            flash('error', $message);
        }

        $this->redirect('/inventory');
    }

    public function toggleStatus(string $id): void
    {
        validate_csrf();

        $productModel = new Product();
        $product = $productModel->findVisible((int) $id);

        if (! $product) {
            flash('error', 'Producto no encontrado.');
            $this->redirect('/inventory');
        }

        $nextStatus = (($product['status'] ?? 'active') === 'active') ? 'inactive' : 'active';
        $productModel->setStatus((int) $id, $nextStatus);

        flash('success', $nextStatus === 'inactive' ? 'Producto desactivado.' : 'Producto reactivado.');
        $this->redirect('/inventory');
    }

    public function softDelete(string $id): void
    {
        validate_csrf();

        $productModel = new Product();
        $product = $productModel->findVisible((int) $id);

        if (! $product) {
            flash('error', 'Producto no encontrado.');
            $this->redirect('/inventory');
        }

        if ((float) ($product['stock'] ?? 0) > 0) {
            flash('error', 'Para eliminar con borrado logico, el producto debe quedar con stock en cero.');
            $this->redirect('/inventory');
        }

        $dependencies = $productModel->activeArchiveDependencies((int) $id);
        $blockingDocuments = [];

        if (($dependencies['active_purchases'] ?? 0) > 0) {
            $blockingDocuments[] = (($dependencies['active_purchases'] ?? 0) === 1)
                ? '1 compra activa'
                : (($dependencies['active_purchases'] ?? 0) . ' compras activas');
        }

        if (($dependencies['active_production_orders'] ?? 0) > 0) {
            $blockingDocuments[] = (($dependencies['active_production_orders'] ?? 0) === 1)
                ? '1 pedido de produccion activo'
                : (($dependencies['active_production_orders'] ?? 0) . ' pedidos de produccion activos');
        }

        if ($blockingDocuments !== []) {
            flash(
                'error',
                'No puedes archivar este producto porque participa en ' . implode(' y ', $blockingDocuments) . '. Anula o elimina esos documentos primero para no dejar reversiones de inventario imposibles.'
            );
            $this->redirect('/inventory');
        }

        $productModel->softDelete((int) $id);
        flash('success', 'Producto eliminado con borrado logico.');
        $this->redirect('/inventory');
    }

    public function movements(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $products = (new Product())->stockManagedList("COALESCE(NULLIF(TRIM(p.sku), ''), p.name) ASC, p.name ASC, c.name ASC, p.id ASC");

        $statement = Database::connection()->prepare(
            'SELECT m.*, p.name AS product_name, c.name AS category_name
             FROM inventory_movements m
             LEFT JOIN products p ON p.id = m.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE DATE(m.created_at) BETWEEN ? AND ?
             ORDER BY m.created_at DESC'
        );
        $statement->execute([$from, $to]);
        $movements = $statement->fetchAll();

        $this->view('inventory/movements_modern', compact('movements', 'from', 'to', 'products'), 'layouts/app_modern');
    }

    public function adjust(): void
    {
        validate_csrf();

        $quantity = parse_money_input($_POST['quantity'] ?? 0);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $product = (new Product())->findVisible($productId);

        if (! $product) {
            flash('error', 'Producto no encontrado.');
            $this->redirect('/inventory/movements');
        }

        if (! product_tracks_inventory($product)) {
            flash('error', 'Ese producto no maneja inventario.');
            $this->redirect('/inventory/movements');
        }

        try {
            $quantity = $this->normalizeInventoryQuantity($quantity, $product, 'cantidad del ajuste');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('/inventory/movements');
        }

        if (abs($quantity) < 0.00001) {
            flash('error', 'Debes indicar una cantidad distinta de cero para aplicar el ajuste.');
            $this->redirect('/inventory/movements');
        }

        if ($quantity >= 0) {
            Inventory::increase($productId, $quantity, 'adjustment_in', 'AJUSTE', $notes);
        } else {
            Inventory::decrease($productId, abs($quantity), 'adjustment_out', 'AJUSTE', $notes);
        }

        flash('success', 'Ajuste aplicado.');
        $this->redirect('/inventory/movements');
    }

    public function adjustBulk(): void
    {
        validate_csrf();

        $targets = is_array($_POST['target_stock'] ?? null) ? $_POST['target_stock'] : [];
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $db = Database::connection();

        if ($targets === []) {
            flash('error', 'No llegaron productos para reajustar.');
            $this->redirect('/inventory/movements');
        }

        $applied = 0;
        $skipped = 0;

        $db->beginTransaction();

        try {
            $productStatement = $db->prepare(
                'SELECT *
                 FROM products
                 WHERE id = ?
                   AND deleted_at IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );

            foreach ($targets as $productId => $rawTarget) {
                $productId = (int) $productId;
                $targetText = trim((string) $rawTarget);

                if ($productId <= 0 || $targetText === '') {
                    continue;
                }

                $productStatement->execute([$productId]);
                $product = $productStatement->fetch() ?: null;

                if (! is_array($product)) {
                    throw new \RuntimeException('Uno de los productos seleccionados ya no existe o fue archivado.');
                }

                if (! product_tracks_inventory($product)) {
                    throw new \RuntimeException('El producto "' . (($product['name'] ?? 'Sin nombre')) . '" no maneja inventario.');
                }

                $targetStock = $this->normalizeInventoryQuantity(
                    parse_money_input($targetText),
                    $product,
                    'stock contado',
                    false
                );
                $currentStock = $this->normalizeInventoryQuantity((float) ($product['stock'] ?? 0), $product, 'stock actual');
                $difference = round_money($targetStock - $currentStock);

                if (abs($difference) < 0.00001) {
                    $skipped++;
                    continue;
                }

                $movementNotes = $notes !== ''
                    ? 'Reajuste masivo. ' . $notes
                    : 'Reajuste masivo por conteo.';

                if ($difference > 0) {
                    Inventory::increase($productId, $difference, 'adjustment_in', 'AJUSTE MASIVO', $movementNotes);
                } else {
                    Inventory::decrease($productId, abs($difference), 'adjustment_out', 'AJUSTE MASIVO', $movementNotes);
                }

                $applied++;
            }

            if ($applied === 0) {
                $db->rollBack();
                flash('error', 'No hubo cambios para aplicar. Escribe el stock contado solo en los productos que realmente necesitas reajustar.');
                $this->redirect('/inventory/movements');
            }

            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            flash('error', $exception->getMessage());
            $this->redirect('/inventory/movements');
        }

        $message = $applied === 1
            ? 'Reajuste masivo aplicado en 1 producto.'
            : 'Reajuste masivo aplicado en ' . $applied . ' productos.';
        if ($skipped > 0) {
            $message .= ' Se omitieron ' . $skipped . ' filas sin cambios.';
        }

        flash('success', $message);
        $this->redirect('/inventory/movements');
    }

    private function parseVariantValues(string $value): array
    {
        $chunks = preg_split('/[\r\n,;]+/', $value) ?: [];
        $items = [];
        $seen = [];

        foreach ($chunks as $chunk) {
            $normalized = trim($chunk);
            if ($normalized === '') {
                continue;
            }

            $key = strtolower($normalized);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = $normalized;
        }

        return $items;
    }

    private function buildVariantName(string $baseName, string $color, string $size): string
    {
        $parts = [trim($baseName)];
        if (trim($color) !== '') {
            $parts[] = trim($color);
        }
        if (trim($size) !== '') {
            $parts[] = 'Talla ' . trim($size);
        }

        return trim(implode(' ', array_filter($parts, static fn (string $value): bool => $value !== '')));
    }

    private function buildVariantSkuSeed(string $prefix, string $color, string $size): string
    {
        $segments = [$this->sanitizeSkuSegment($prefix)];
        if (trim($color) !== '') {
            $segments[] = $this->sanitizeSkuSegment($color);
        }
        if (trim($size) !== '') {
            $segments[] = $this->sanitizeSkuSegment($size);
        }

        return implode('-', array_values(array_filter($segments, static fn (string $value): bool => $value !== '')));
    }

    private function sanitizeSkuSegment(string $value): string
    {
        $normalized = strtoupper(trim($value));
        $normalized = preg_replace('/[^A-Z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return substr($normalized, 0, 24);
    }

    private function generateAvailableSku(Product $productModel, string $seed, array &$reserved = []): string
    {
        $base = $this->sanitizeSkuSegment($seed);
        if ($base === '') {
            $base = 'SKU';
        }

        $candidate = substr($base, 0, 80);
        $suffix = 2;

        while (in_array($candidate, $reserved, true) || $productModel->skuExists($candidate)) {
            $suffixText = '-' . $suffix;
            $candidate = substr($base, 0, max(1, 80 - strlen($suffixText))) . $suffixText;
            $suffix++;
        }

        $reserved[] = $candidate;

        return $candidate;
    }

    private function normalizeInventoryQuantity(
        float $quantity,
        array|string|null $product,
        string $fieldLabel,
        bool $allowNegative = true
    ): float {
        $normalized = round_money($quantity);
        $productType = is_array($product)
            ? strtolower(trim((string) ($product['product_type'] ?? 'merchandise')))
            : strtolower(trim((string) $product));

        if (! $allowNegative && $normalized < 0) {
            throw new \RuntimeException('El ' . $fieldLabel . ' no puede ser negativo.');
        }

        if ($productType !== 'raw_material' && abs($normalized - round($normalized)) > 0.00001) {
            throw new \RuntimeException('El ' . $fieldLabel . ' debe ser un numero entero para este producto.');
        }

        return $productType === 'raw_material'
            ? $normalized
            : (float) round($normalized);
    }

}
