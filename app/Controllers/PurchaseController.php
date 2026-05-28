<?php declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
class PurchaseController extends Controller
{
    public function index(): void
    {
        $purchases = (new Purchase())->all();
        $suppliers = (new Supplier())->all("name ASC");
        $products = (new Product())->all("name ASC");
        $warehouses = (new Warehouse())->all("name ASC");
        $rate = ['rate' => system_exchange_rate(date('Y-m-d')), 'currency_from' => 'USD', 'currency_to' => 'VES'];
        $this->view(
            "purchases/index",
            compact("purchases", "suppliers", "products", "warehouses", "rate"),
        );
    }
    public function storeSupplier(): void
    {
        validate_csrf();
        (new Supplier())->insert([
            "name" => trim($_POST["name"]),
            "document" => trim($_POST["document"] ?? ""),
            "phone" => trim($_POST["phone"] ?? ""),
            "email" => trim($_POST["email"] ?? ""),
            "address" => trim($_POST["address"] ?? ""),
        ]);
        flash("success", "Proveedor creado.");
        $this->redirect("/purchases");
    }
    public function store(): void
    {
        validate_csrf();
        $productId = (int) $_POST["product_id"];
        $qty = (float) $_POST["quantity"];
        $costOriginal = (float) $_POST["cost_original"];
        $rate = (float) $_POST["exchange_rate"];
        $currency = trim($_POST["currency_code"]);
        $costConverted = $this->toBaseAmount($costOriginal, $currency, $rate);
        $subtotalOriginal = $qty * $costOriginal;
        $subtotalConverted = $qty * $costConverted;
        (new Purchase())->create(
            [
                "supplier_id" => (int) $_POST["supplier_id"],
                "warehouse_id" => (int) $_POST["warehouse_id"],
                "doc_number" => trim($_POST["doc_number"]),
                "purchase_date" => $_POST["purchase_date"],
                "currency_code" => $currency,
                "exchange_rate" => $rate,
                "subtotal_original" => $subtotalOriginal,
                "total_original" => $subtotalOriginal,
                "subtotal_converted" => $subtotalConverted,
                "total_converted" => $subtotalConverted,
                "notes" => trim($_POST["notes"] ?? ""),
            ],
            [
                [
                    "product_id" => $productId,
                    "quantity" => $qty,
                    "cost_original" => $costOriginal,
                    "cost_converted" => $costConverted,
                    "total_original" => $subtotalOriginal,
                    "total_converted" => $subtotalConverted,
                ],
            ],
        );
        flash("success", "Compra registrada e inventario actualizado.");
        $this->redirect("/purchases");
    }

    private function toBaseAmount(float $amount, string $currency, float $rate): float
    {
        $currency = strtoupper(trim($currency));
        $baseCurrency = strtoupper(base_currency());

        if ($currency === $baseCurrency) {
            return $amount;
        }

        if ($baseCurrency === "USD" && $currency === "VES") {
            return $rate > 0 ? $amount / $rate : 0.0;
        }

        return $rate > 0 ? $amount / $rate : $amount;
    }
}
