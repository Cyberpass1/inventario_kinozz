<?php declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Product;
use App\Models\Warehouse;
class InvoiceController extends Controller
{
    public function index(): void
    {
        $m = new Invoice();
        $invoices = $m->all();
        $clients = (new Client())->all("name ASC");
        $products = (new Product())->all("name ASC");
        $warehouses = (new Warehouse())->all("name ASC");
        $nextNumber = $m->nextNumber();
        $rate = ['rate' => system_exchange_rate(date('Y-m-d')), 'currency_from' => 'USD', 'currency_to' => 'VES'];
        $this->view(
            "invoices/index",
            compact(
                "invoices",
                "clients",
                "products",
                "warehouses",
                "nextNumber",
                "rate",
            ),
        );
    }
    public function storeClient(): void
    {
        validate_csrf();
        (new Client())->insert([
            "name" => trim($_POST["name"]),
            "document" => trim($_POST["document"] ?? ""),
            "phone" => trim($_POST["phone"] ?? ""),
            "email" => trim($_POST["email"] ?? ""),
            "address" => trim($_POST["address"] ?? ""),
        ]);
        flash("success", "Cliente creado.");
        $this->redirect("/invoices");
    }
    public function store(): void
    {
        validate_csrf();
        $qty = (float) $_POST["quantity"];
        $priceOriginal = (float) $_POST["price_original"];
        $rate = (float) $_POST["exchange_rate"];
        $currency = trim($_POST["currency_code"]);
        $taxPercent = (float) env("TAX_PERCENT", 16);
        $priceConverted =
            $currency === base_currency()
                ? $priceOriginal
                : $priceOriginal * $rate;
        $subtotalOriginal = $qty * $priceOriginal;
        $subtotalConverted = $qty * $priceConverted;
        $taxOriginal = $subtotalOriginal * ($taxPercent / 100);
        $taxConverted = $subtotalConverted * ($taxPercent / 100);
        $totalOriginal = $subtotalOriginal + $taxOriginal;
        $totalConverted = $subtotalConverted + $taxConverted;
        (new Invoice())->create(
            [
                "client_id" => (int) $_POST["client_id"],
                "invoice_number" => trim($_POST["invoice_number"]),
                "invoice_date" => $_POST["invoice_date"],
                "currency_code" => $currency,
                "exchange_rate" => $rate,
                "subtotal_original" => $subtotalOriginal,
                "tax_original" => $taxOriginal,
                "total_original" => $totalOriginal,
                "subtotal_converted" => $subtotalConverted,
                "tax_converted" => $taxConverted,
                "total_converted" => $totalConverted,
                "notes" => trim($_POST["notes"] ?? ""),
            ],
            [
                [
                    "product_id" => (int) $_POST["product_id"],
                    "warehouse_id" => (int) $_POST["warehouse_id"],
                    "quantity" => $qty,
                    "price_original" => $priceOriginal,
                    "price_converted" => $priceConverted,
                    "total_original" => $subtotalOriginal,
                    "total_converted" => $subtotalConverted,
                ],
            ],
        );
        flash(
            "success",
            "Factura registrada. Puedes consultarla luego en la tabla.",
        );
        $this->redirect("/invoices");
    }
    public function print(string $id): void
    {
        $invoice = (new Invoice())->findFull((int) $id);
        $this->view("invoices/print", compact("invoice"), "layouts/print");
    }
}
