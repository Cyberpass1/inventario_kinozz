<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\Production;

class ProductionControllerModern extends Controller
{
    private function ensureEnabled(): void
    {
        if (! production_enabled()) {
            flash('error', 'El modulo de produccion esta desactivado. Actívalo en ajustes generales.');
            $this->redirect('/dashboard');
        }
    }

    public function index(): void
    {
        $this->ensureEnabled();

        $productModel = new Product();
        $productionModel = new Production();
        $skuOrder = "COALESCE(NULLIF(TRIM(p.sku), ''), p.name) ASC, p.name ASC, c.name ASC, p.id ASC";

        $products = $productModel->manufacturableList($skuOrder);
        $components = $productModel->rawMaterialList();
        $history = $productionModel->history();
        $recipesSummary = $productionModel->recipesSummary();
        $recipes = $productionModel->allRecipesGrouped();

        $summary = [
            'products' => count($products),
            'recipes' => count(array_filter($recipesSummary, static fn (array $row): bool => (int) ($row['components_count'] ?? 0) > 0)),
            'orders' => count($history),
        ];

        $this->view(
            'production/workspace',
            compact('products', 'components', 'history', 'recipes', 'recipesSummary', 'summary'),
            'layouts/app_modern'
        );
    }

    public function saveRecipe(string $id): void
    {
        $this->ensureEnabled();
        validate_csrf();

        try {
            (new Production())->saveRecipe((int) $id, (array) ($_POST['items'] ?? []));
            flash('success', 'Receta guardada correctamente.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/production');
    }

    public function store(): void
    {
        $this->ensureEnabled();
        validate_csrf();

        try {
            $productionDate = (string) ($_POST['production_date'] ?? date('Y-m-d'));
            $reference = trim((string) ($_POST['reference'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $items = $this->extractItems($_POST);

            $orderIds = (new Production())->produceMany($items, $productionDate, $reference, $notes);
            $count = count($orderIds);
            flash('success', $count === 1
                ? 'Produccion registrada y sumada al inventario como producto terminado.'
                : 'Produccion multiple registrada. Todos los productos terminados se sumaron al inventario.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/production');
    }

    public function cancel(string $id): void
    {
        $this->ensureEnabled();
        validate_csrf();

        try {
            (new Production())->cancel((int) $id, trim((string) ($_POST['reason'] ?? '')));
            flash('success', 'Pedido de produccion anulado y movimientos de inventario revertidos.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/production');
    }

    private function extractItems(array $source): array
    {
        $rows = $source['items'] ?? [];
        if (! is_array($rows) || $rows === []) {
            $rows = [[
                'product_id' => $source['product_id'] ?? '',
                'quantity_produced' => $source['quantity_produced'] ?? '',
            ]];
        }

        $items = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $hasAnyValue = trim((string) ($row['product_id'] ?? '')) !== ''
                || trim((string) ($row['quantity_produced'] ?? '')) !== '';
            if (! $hasAnyValue) {
                continue;
            }

            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                throw new \RuntimeException('Cada renglon de produccion debe tener un producto valido.');
            }

            $quantityProduced = (float) ($row['quantity_produced'] ?? 0);
            if ($quantityProduced <= 0) {
                throw new \RuntimeException('La cantidad de cada producto a producir debe ser mayor a cero.');
            }

            $items[] = [
                'product_id' => $productId,
                'quantity_produced' => $quantityProduced,
            ];
        }

        if ($items === []) {
            throw new \RuntimeException('Debes agregar al menos un producto para enviar a produccion.');
        }

        return $items;
    }
}
