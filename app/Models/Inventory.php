<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

class Inventory
{
    public static function increase(int $productId, float $qty, string $type, string $reference, string $notes = ''): void
    {
        self::move($productId, abs($qty), $type, $reference, $notes);
    }

    public static function decrease(int $productId, float $qty, string $type, string $reference, string $notes = ''): void
    {
        self::move($productId, -abs($qty), $type, $reference, $notes);
    }

    public static function move(int $productId, float $qty, string $type, string $reference, string $notes = ''): void
    {
        $db = Database::connection();
        $ownTransaction = ! $db->inTransaction();

        if ($ownTransaction) {
            $db->beginTransaction();
        }

        try {
            $statement = $db->prepare('SELECT stock, product_type FROM products WHERE id = ? FOR UPDATE');
            $statement->execute([$productId]);
            $product = $statement->fetch(PDO::FETCH_ASSOC);

            if (! $product) {
                throw new RuntimeException('Producto no encontrado.');
            }

            if (! product_tracks_inventory($product)) {
                throw new RuntimeException('El producto seleccionado no maneja inventario.');
            }

            $stock = (float) $product['stock'];

            if (($stock + $qty) < 0) {
                throw new RuntimeException('Stock insuficiente.');
            }

            $update = $db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            $update->execute([$qty, $productId]);

            $movement = $db->prepare(
                'INSERT INTO inventory_movements (product_id, warehouse_id, movement_type, quantity, reference, notes)
                 VALUES (?, NULL, ?, ?, ?, ?)'
            );
            $movement->execute([$productId, $type, $qty, $reference, $notes]);

            if ($ownTransaction) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownTransaction && $db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }
}
