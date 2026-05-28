<?php declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;

class WarehouseController extends Controller
{
    public function index(): void
    {
        $warehouseModel = new Warehouse();
        $productModel = new Product();
        $warehouses = $warehouseModel->all("name ASC");
        $products = $productModel->all("name ASC");
        $transfers = Database::connection()
            ->query(
                "SELECT t.*, p.name AS product_name, wf.name AS from_name, wt.name AS to_name FROM warehouse_transfers t LEFT JOIN products p ON p.id=t.product_id LEFT JOIN warehouses wf ON wf.id=t.from_warehouse_id LEFT JOIN warehouses wt ON wt.id=t.to_warehouse_id ORDER BY t.id DESC",
            )
            ->fetchAll();

        $this->view(
            "warehouses/index",
            compact("warehouses", "products", "transfers"),
        );
    }

    public function store(): void
    {
        validate_csrf();

        $warehouseModel = new Warehouse();
        $warehouseModel->insert([
            "name" => trim($_POST["name"]),
            "location" => trim($_POST["location"] ?? ""),
        ]);

        flash("success", "Almacen creado.");
        $this->redirect("/warehouses");
    }

    public function transfer(): void
    {
        validate_csrf();

        Inventory::transfer(
            (int) $_POST["product_id"],
            (int) $_POST["from_warehouse_id"],
            (int) $_POST["to_warehouse_id"],
            (float) $_POST["quantity"],
            "TRF-" . date("YmdHis"),
        );

        flash("success", "Transferencia registrada.");
        $this->redirect("/warehouses");
    }
}
