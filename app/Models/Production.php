<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Production
{
    public function recipesSummary(): array
    {
        $statement = Database::connection()->query(
            "SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.sku AS product_sku,
                p.unit_label AS product_unit_label,
                c.name AS category_name,
                COUNT(ri.id) AS components_count,
                GROUP_CONCAT(CONCAT(cp.name, ' x ', FORMAT(ri.quantity, 2), ' ', COALESCE(cp.unit_label, 'und')) ORDER BY cp.name SEPARATOR ', ') AS recipe_summary
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN product_recipe_items ri ON ri.product_id = p.id
             LEFT JOIN products cp ON cp.id = ri.component_product_id
             WHERE p.deleted_at IS NULL
               AND COALESCE(p.status, 'active') = 'active'
               AND COALESCE(p.product_type, 'merchandise') IN ('finished_good', 'merchandise')
             GROUP BY p.id, p.name, p.sku, p.unit_label, c.name
             ORDER BY COALESCE(NULLIF(TRIM(p.sku), ''), p.name) ASC, p.name ASC, p.id ASC"
        );

        return $statement->fetchAll();
    }

    public function recipeForProduct(int $productId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                ri.*,
                cp.name AS component_name,
                cp.sku AS component_sku,
                cp.stock AS component_stock,
                cp.cost AS component_cost,
                cp.currency_code AS component_currency_code,
                cp.product_type AS component_type,
                cp.unit_label AS component_unit_label
             FROM product_recipe_items ri
             INNER JOIN products cp ON cp.id = ri.component_product_id
             WHERE ri.product_id = ?
             ORDER BY cp.name ASC, ri.id ASC"
        );
        $statement->execute([$productId]);

        return $statement->fetchAll();
    }

    public function allRecipesGrouped(): array
    {
        $statement = Database::connection()->query(
            "SELECT
                ri.*,
                cp.name AS component_name,
                cp.sku AS component_sku,
                cp.stock AS component_stock,
                cp.cost AS component_cost,
                cp.currency_code AS component_currency_code,
                cp.product_type AS component_type,
                cp.unit_label AS component_unit_label
             FROM product_recipe_items ri
             INNER JOIN products cp ON cp.id = ri.component_product_id
             ORDER BY ri.product_id ASC, cp.name ASC, ri.id ASC"
        );

        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $grouped[$productId][] = $row;
        }

        return $grouped;
    }

    public function saveRecipe(int $productId, array $items): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $product = (new Product())->findVisible($productId);
            if (! $product) {
                throw new \RuntimeException('Producto no encontrado para la receta.');
            }

            if (! in_array((string) ($product['product_type'] ?? ''), product_manufacturable_types(), true)) {
                throw new \RuntimeException('Solo los productos fabricados pueden tener receta.');
            }

            $normalizedItems = [];
            foreach ($items as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $componentId = (int) ($row['component_product_id'] ?? 0);
                $quantity = (float) ($row['quantity'] ?? 0);

                if ($componentId <= 0 && $quantity <= 0) {
                    continue;
                }

                if ($componentId <= 0 || $quantity <= 0) {
                    throw new \RuntimeException('Cada componente de la receta debe tener producto y cantidad mayor a cero.');
                }

                if ($componentId === $productId) {
                    throw new \RuntimeException('Un producto no puede componerse de si mismo.');
                }

                $component = (new Product())->findVisible($componentId);
                if (! $component) {
                    throw new \RuntimeException('Uno de los componentes ya no existe.');
                }

                if ((string) ($component['product_type'] ?? '') !== 'raw_material') {
                    throw new \RuntimeException('En la receta solo se permiten materias primas.');
                }

                $normalizedItems[$componentId] = [
                    'component_product_id' => $componentId,
                    'quantity' => $quantity,
                    'notes' => trim((string) ($row['notes'] ?? '')),
                ];
            }

            if ($normalizedItems === []) {
                throw new \RuntimeException('Debes agregar al menos un insumo en la receta.');
            }

            $db->prepare('DELETE FROM product_recipe_items WHERE product_id = ?')->execute([$productId]);
            $insert = $db->prepare(
                'INSERT INTO product_recipe_items (product_id, component_product_id, quantity, notes)
                 VALUES (?, ?, ?, ?)'
            );

            foreach ($normalizedItems as $item) {
                $insert->execute([
                    $productId,
                    $item['component_product_id'],
                    $item['quantity'],
                    $item['notes'] !== '' ? $item['notes'] : null,
                ]);
            }

            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function history(int $limit = 20): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                po.*,
                p.name AS product_name,
                p.sku AS product_sku,
                p.unit_label AS product_unit_label,
                GROUP_CONCAT(CONCAT(cp.name, ' x ', FORMAT(poi.quantity_consumed, 2), ' ', COALESCE(cp.unit_label, 'und')) ORDER BY cp.name SEPARATOR ', ') AS components_summary
             FROM production_orders po
             INNER JOIN products p ON p.id = po.product_id
             LEFT JOIN production_order_items poi ON poi.production_order_id = po.id
             LEFT JOIN products cp ON cp.id = poi.component_product_id
             GROUP BY po.id, po.product_id, po.reference, po.production_date, po.quantity_produced, po.unit_cost, po.total_cost, po.notes, po.status, po.cancelled_at, po.cancellation_reason, po.created_at, p.name, p.sku, p.unit_label
             ORDER BY po.id DESC
             LIMIT ?"
        );
        $statement->bindValue(1, $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function findFull(int $id): ?array
    {
        $db = Database::connection();

        $header = $db->prepare(
            "SELECT
                po.*,
                p.name AS product_name,
                p.sku AS product_sku,
                p.product_type AS product_type,
                p.unit_label AS product_unit_label,
                p.stock AS product_stock,
                p.deleted_at AS product_deleted_at
             FROM production_orders po
             INNER JOIN products p ON p.id = po.product_id
             WHERE po.id = ?
             LIMIT 1"
        );
        $header->execute([$id]);
        $order = $header->fetch();

        if (! $order) {
            return null;
        }

        $items = $db->prepare(
            "SELECT
                poi.*,
                cp.name AS component_name,
                cp.sku AS component_sku,
                cp.product_type AS component_type,
                cp.unit_label AS component_unit_label
             FROM production_order_items poi
             INNER JOIN products cp ON cp.id = poi.component_product_id
             WHERE poi.production_order_id = ?
             ORDER BY poi.id ASC"
        );
        $items->execute([$id]);
        $order['items'] = $items->fetchAll();

        return $order;
    }

    public function cancel(int $id, string $reason = ''): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $order = $this->findFull($id);
            if (! $order) {
                throw new \RuntimeException('Pedido de produccion no encontrado.');
            }

            if (($order['status'] ?? 'active') === 'cancelled') {
                throw new \RuntimeException('El pedido de produccion ya estaba anulado.');
            }

            $this->assertFinishedProductStockCanBeReversed($order);

            Inventory::decrease(
                (int) ($order['product_id'] ?? 0),
                (float) ($order['quantity_produced'] ?? 0),
                'production_cancel_out',
                'ANULACION ' . (string) ($order['reference'] ?? ('PROD #' . $id)),
                $reason !== '' ? $reason : 'Salida por anulacion de pedido de produccion'
            );

            foreach ((array) ($order['items'] ?? []) as $item) {
                Inventory::increase(
                    (int) ($item['component_product_id'] ?? 0),
                    (float) ($item['quantity_consumed'] ?? 0),
                    'production_cancel_in',
                    'ANULACION ' . (string) ($order['reference'] ?? ('PROD #' . $id)),
                    $reason !== '' ? $reason : 'Reposicion de insumos por anulacion de produccion'
                );
            }

            $statement = $db->prepare(
                'UPDATE production_orders
                 SET status = ?, cancelled_at = NOW(), cancellation_reason = ?
                 WHERE id = ?'
            );
            $statement->execute([
                'cancelled',
                $reason !== '' ? $reason : null,
                $id,
            ]);

            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function produce(array $payload): int
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $orderId = $this->persistProduction($payload, $db);

            $db->commit();

            return $orderId;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function produceMany(array $items, string $productionDate, string $reference = '', string $notes = ''): array
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $normalizedItems = array_values(array_filter($items, static fn (array $item): bool => (int) ($item['product_id'] ?? 0) > 0));
            if ($normalizedItems === []) {
                throw new \RuntimeException('Debes agregar al menos un producto para enviar a produccion.');
            }

            $sharedReference = trim($reference);
            if ($sharedReference === '' && count($normalizedItems) > 1) {
                $sharedReference = 'LOTE-' . date('Ymd-His');
            }

            $orderIds = [];
            foreach ($normalizedItems as $item) {
                $orderIds[] = $this->persistProduction([
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'quantity_produced' => (float) ($item['quantity_produced'] ?? 0),
                    'production_date' => $productionDate,
                    'reference' => $sharedReference !== '' ? $sharedReference : trim((string) ($item['reference'] ?? '')),
                    'notes' => trim((string) ($item['notes'] ?? '')) !== '' ? trim((string) $item['notes']) : $notes,
                ], $db);
            }

            $db->commit();

            return $orderIds;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    private function persistProduction(array $payload, \PDO $db): int
    {
        $productId = (int) ($payload['product_id'] ?? 0);
        $quantityProduced = (float) ($payload['quantity_produced'] ?? 0);
        $productionDate = (string) ($payload['production_date'] ?? date('Y-m-d'));
        $notes = trim((string) ($payload['notes'] ?? ''));

        if ($productId <= 0) {
            throw new \RuntimeException('Debes seleccionar un producto fabricado.');
        }

        if ($quantityProduced <= 0) {
            throw new \RuntimeException('La cantidad a producir debe ser mayor a cero.');
        }

        $product = (new Product())->findVisible($productId);
        if (! $product) {
            throw new \RuntimeException('Producto fabricado no encontrado.');
        }

        if (! in_array((string) ($product['product_type'] ?? ''), product_manufacturable_types(), true)) {
            throw new \RuntimeException('Solo puedes producir productos marcados como fabricados.');
        }

        $recipe = $this->recipeForProduct($productId);
        if ($recipe === []) {
            throw new \RuntimeException('El producto "' . (string) ($product['name'] ?? 'seleccionado') . '" no tiene receta configurada.');
        }

        $reference = trim((string) ($payload['reference'] ?? ''));
        if ($reference === '') {
            $reference = '__PENDING__';
        }

        $insertOrder = $db->prepare(
            'INSERT INTO production_orders (product_id, reference, production_date, quantity_produced, unit_cost, total_cost, notes, status)
             VALUES (?, ?, ?, ?, 0, 0, ?, ?)'
        );
        $insertOrder->execute([
            $productId,
            $reference,
            $productionDate,
            $quantityProduced,
            $notes !== '' ? $notes : null,
            'active',
        ]);

        $orderId = (int) $db->lastInsertId();
        if ($reference === '__PENDING__') {
            $reference = 'PROD-' . str_pad((string) max(1, $orderId), 5, '0', STR_PAD_LEFT);
            $db->prepare('UPDATE production_orders SET reference = ? WHERE id = ?')->execute([$reference, $orderId]);
        }

        $insertItem = $db->prepare(
            'INSERT INTO production_order_items
                (production_order_id, component_product_id, quantity_per_unit, quantity_consumed, unit_cost, total_cost)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $totalCost = 0.0;
        foreach ($recipe as $component) {
            $componentId = (int) ($component['component_product_id'] ?? 0);
            $quantityPerUnit = (float) ($component['quantity'] ?? 0);
            $quantityConsumed = $quantityPerUnit * $quantityProduced;
            $unitCost = (float) ($component['component_cost'] ?? 0);
            $lineCost = $quantityConsumed * $unitCost;

            Inventory::decrease(
                $componentId,
                $quantityConsumed,
                'production_out',
                $reference,
                $notes !== '' ? $notes : 'Consumo de insumos por produccion'
            );

            $insertItem->execute([
                $orderId,
                $componentId,
                $quantityPerUnit,
                $quantityConsumed,
                $unitCost,
                $lineCost,
            ]);

            $totalCost += $lineCost;
        }

        $unitCost = $quantityProduced > 0 ? ($totalCost / $quantityProduced) : 0.0;
        $db->prepare('UPDATE products SET cost = ? WHERE id = ?')->execute([$unitCost, $productId]);

        Inventory::increase(
            $productId,
            $quantityProduced,
            'production_in',
            $reference,
            $notes !== '' ? $notes : 'Entrada por orden de produccion'
        );

        $db->prepare(
            'UPDATE production_orders
             SET unit_cost = ?, total_cost = ?
             WHERE id = ?'
        )->execute([$unitCost, $totalCost, $orderId]);

        return $orderId;
    }

    private function assertFinishedProductStockCanBeReversed(array $order): void
    {
        $requiredQuantity = (float) ($order['quantity_produced'] ?? 0);
        $availableStock = (float) ($order['product_stock'] ?? 0);

        if ($requiredQuantity <= 0 || $availableStock + 0.00001 >= $requiredQuantity) {
            return;
        }

        $productName = trim((string) ($order['product_name'] ?? 'Producto sin nombre'));
        $unitLabel = trim((string) ($order['product_unit_label'] ?? 'und'));
        $archivedNote = ! empty($order['product_deleted_at']) ? ' El producto terminado ademas esta archivado.' : '';

        throw new \RuntimeException(
            'No se puede anular este pedido de produccion porque el producto "' . $productName . '" solo tiene '
            . money($availableStock) . ' ' . $unitLabel . ' disponibles y la reversa necesita '
            . money($requiredQuantity) . ' ' . $unitLabel . '.' . $archivedNote
        );
    }
}
