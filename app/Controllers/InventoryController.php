<?php declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Core\Database;
class InventoryController extends Controller
{
    public function index(): void
    {
        $products = (new Product())->listWithCategory();
        $categories = (new Category())->all("name ASC");
        $warehouses = (new Warehouse())->all("name ASC");
        $this->view(
            "inventory/index",
            compact("products", "categories", "warehouses"),
        );
    }
    public function storeCategory(): void
    {
        validate_csrf();
        (new Category())->insert([
            "name" => trim($_POST["name"]),
            "description" => trim($_POST["description"] ?? ""),
        ]);
        flash("success", "Categoría creada.");
        $this->redirect("/inventory");
    }
    public function storeProduct(): void
    {
        validate_csrf();
        $id = (new Product())->insert([
            "category_id" => (int) $_POST["category_id"],
            "sku" => trim($_POST["sku"]),
            "name" => trim($_POST["name"]),
            "description" => trim($_POST["description"] ?? ""),
            "stock" => 0,
            "stock_min" => (float) $_POST["stock_min"],
            "cost" => (float) $_POST["cost"],
            "price" => (float) $_POST["price"],
            "currency_code" => trim($_POST["currency_code"]),
        ]);
        if (!empty($_POST["warehouse_id"]) && !empty($_POST["initial_stock"])) {
            Inventory::increase(
                $id,
                (int) $_POST["warehouse_id"],
                (float) $_POST["initial_stock"],
                "initial",
                "INICIAL",
                "Carga inicial",
            );
        }
        flash("success", "Producto creado.");
        $this->redirect("/inventory");
    }
    public function movements(): void
    {
        $from = $_GET["from"] ?? date("Y-m-01");
        $to = $_GET["to"] ?? date("Y-m-d");
        $s = Database::connection()->prepare(
            "SELECT m.*, p.name AS product_name, c.name AS category_name, w.name AS warehouse_name FROM inventory_movements m LEFT JOIN products p ON p.id=m.product_id LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN warehouses w ON w.id=m.warehouse_id WHERE DATE(m.created_at) BETWEEN ? AND ? ORDER BY m.created_at DESC",
        );
        $s->execute([$from, $to]);
        $movements = $s->fetchAll();
        $this->view("inventory/movements", compact("movements", "from", "to"));
    }
    public function adjust(): void
    {
        validate_csrf();
        $quantity = (float) $_POST["quantity"];
        if ($quantity >= 0) {
            Inventory::increase(
                (int) $_POST["product_id"],
                (int) $_POST["warehouse_id"],
                $quantity,
                "adjustment_in",
                "AJUSTE",
                trim($_POST["notes"] ?? ""),
            );
        } else {
            Inventory::decrease(
                (int) $_POST["product_id"],
                (int) $_POST["warehouse_id"],
                abs($quantity),
                "adjustment_out",
                "AJUSTE",
                trim($_POST["notes"] ?? ""),
            );
        }
        flash("success", "Ajuste aplicado.");
        $this->redirect("/inventory/movements");
    }
    public function warehouseStock(): void
    {
        $rows = Database::connection()
            ->query(
                "SELECT ws.*, p.name AS product_name, w.name AS warehouse_name FROM warehouse_stock ws LEFT JOIN products p ON p.id=ws.product_id LEFT JOIN warehouses w ON w.id=ws.warehouse_id ORDER BY w.name,p.name",
            )
            ->fetchAll();
        $this->view("inventory/warehouse_stock", ["rows" => $rows]);
    }
}
