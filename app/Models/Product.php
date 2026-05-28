<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;
use RuntimeException;

class Product extends Model
{
    protected string $table = 'products';

    public function all(string $orderBy = 'name ASC'): array
    {
        $statement = $this->db->query($this->buildCatalogQuery($orderBy, true));

        return $statement->fetchAll();
    }

    public function listWithCategory(array $types = [], string $orderBy = 'COALESCE(NULLIF(TRIM(p.sku), \'\'), p.name) ASC, p.name ASC, p.id ASC'): array
    {
        return $this->fetchWithCategory($types, false, $orderBy);
    }

    public function activeListWithCategory(
        string $orderBy = 'COALESCE(NULLIF(TRIM(p.sku), \'\'), p.name) ASC, p.name ASC, c.name ASC, p.id ASC',
        array $types = []
    ): array {
        return $this->fetchWithCategory($types, true, $orderBy);
    }

    public function sellableList(string $orderBy = 'p.name ASC, c.name ASC, p.id ASC'): array
    {
        return $this->fetchWithCategory(product_saleable_types(), true, $orderBy);
    }

    public function purchasableList(string $orderBy = 'p.name ASC, c.name ASC, p.id ASC'): array
    {
        return $this->fetchWithCategory(product_purchasable_types(), true, $orderBy);
    }

    public function serviceList(string $orderBy = 'p.name ASC, c.name ASC, p.id ASC'): array
    {
        return $this->fetchWithCategory(['service'], true, $orderBy);
    }

    public function rawMaterialList(string $orderBy = 'p.name ASC, c.name ASC, p.id ASC'): array
    {
        return $this->fetchWithCategory(['raw_material'], true, $orderBy);
    }

    public function manufacturableList(string $orderBy = 'COALESCE(NULLIF(TRIM(p.sku), \'\'), p.name) ASC, p.name ASC, c.name ASC, p.id ASC'): array
    {
        return $this->fetchWithCategory(product_manufacturable_types(), true, $orderBy);
    }

    public function stockManagedList(string $orderBy = 'p.name ASC, c.name ASC, p.id ASC'): array
    {
        return $this->fetchWithCategory(product_stock_managed_types(), true, $orderBy);
    }

    public function lowStock(int $limit = 6): array
    {
        [$typeSql, $typeParams] = $this->typeFilterSql(product_stock_managed_types(), 'p.product_type');
        $statement = $this->db->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.deleted_at IS NULL
               AND COALESCE(p.status, \'active\') = \'active\'
               ' . $typeSql . '
               AND p.stock <= p.stock_min
             ORDER BY p.stock ASC, p.name ASC
             LIMIT ?'
        );
        foreach ($typeParams as $index => $value) {
            $statement->bindValue($index + 1, $value);
        }
        $statement->bindValue(count($typeParams) + 1, $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function findVisible(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT *
             FROM products
             WHERE id = ?
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    public function findAny(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT *
             FROM products
             WHERE id = ?
             LIMIT 1'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    public function skuExists(string $sku, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM products WHERE sku = ?';
        $params = [$sku];

        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public function findBySku(string $sku, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM products WHERE sku = ?';
        $params = [$sku];

        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        $sql .= ' ORDER BY id ASC LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetch() ?: null;
    }

    public function updateCatalog(int $id, array $data): bool
    {
        return $this->update($id, $data);
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    public function softDelete(int $id): bool
    {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare(
                'SELECT sku
                 FROM products
                 WHERE id = ?
                   AND deleted_at IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );
            $statement->execute([$id]);
            $product = $statement->fetch();

            if (! $product) {
                $this->db->rollBack();
                return false;
            }

            $archivedSku = $this->archivedSku((string) ($product['sku'] ?? ''), $id);
            $update = $this->db->prepare(
                "UPDATE products
                 SET status = 'inactive',
                     deleted_at = NOW(),
                     sku = ?
                 WHERE id = ?
                   AND deleted_at IS NULL"
            );
            $success = $update->execute([$archivedSku, $id]);

            if (! $success) {
                throw new RuntimeException('No se pudo archivar el producto.');
            }

            $this->db->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function activeArchiveDependencies(int $id): array
    {
        $statement = $this->db->prepare(
            "SELECT
                (
                    SELECT COUNT(DISTINCT p.id)
                    FROM purchase_items pi
                    INNER JOIN purchases p ON p.id = pi.purchase_id
                    WHERE pi.product_id = ?
                      AND COALESCE(p.status, 'active') <> 'cancelled'
                ) AS active_purchases,
                (
                    SELECT COUNT(DISTINCT po.id)
                    FROM production_orders po
                    WHERE po.product_id = ?
                      AND COALESCE(po.status, 'active') <> 'cancelled'
                ) AS active_production_orders"
        );
        $statement->execute([$id, $id]);

        $row = $statement->fetch() ?: [];

        return [
            'active_purchases' => (int) ($row['active_purchases'] ?? 0),
            'active_production_orders' => (int) ($row['active_production_orders'] ?? 0),
        ];
    }

    private function fetchWithCategory(array $types, bool $activeOnly, string $orderBy): array
    {
        [$typeSql, $params] = $this->typeFilterSql($types, 'p.product_type');
        $statusSql = $activeOnly ? "AND COALESCE(p.status, 'active') = 'active'" : '';
        $statement = $this->db->prepare(
            "SELECT p.*, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.deleted_at IS NULL
             {$statusSql}
             {$typeSql}
             ORDER BY {$orderBy}"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function buildCatalogQuery(string $orderBy, bool $activeOnly): string
    {
        $statusSql = $activeOnly ? "AND COALESCE(status, 'active') = 'active'" : '';

        return "SELECT *
             FROM products
             WHERE deleted_at IS NULL
               {$statusSql}
             ORDER BY {$orderBy}";
    }

    private function typeFilterSql(array $types, string $column = 'product_type'): array
    {
        $types = array_values(array_filter(array_map(
            static fn (string $type): string => strtolower(trim($type)),
            $types
        )));

        if ($types === []) {
            return ['', []];
        }

        $placeholders = implode(',', array_fill(0, count($types), '?'));

        return [" AND COALESCE({$column}, 'merchandise') IN ({$placeholders})", $types];
    }

    private function archivedSku(string $sku, int $id): string
    {
        $base = strtoupper(trim($sku));
        $suffix = '--DEL-' . $id;

        if (preg_match('/--DEL-\d+$/', $base) === 1) {
            return $base;
        }

        $base = preg_replace('/\s+/', '-', $base) ?? '';
        $base = preg_replace('/[^A-Z0-9\-]+/', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'SKU';
        }

        return substr($base, 0, max(1, 80 - strlen($suffix))) . $suffix;
    }
}
