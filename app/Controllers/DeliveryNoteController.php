<?php declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\DeliveryNote;
use App\Models\Client;
use App\Models\Product;
use App\Models\Warehouse;
class DeliveryNoteController extends Controller
{
    public function index(): void
    {
        $m = new DeliveryNote();
        $notes = $m->all();
        $clientModel = new Client();
        $productModel = new Product();
        $warehouseModel = new Warehouse();
        $clients = $clientModel->all("name ASC");
        $products = $productModel->all("name ASC");
        $warehouses = $warehouseModel->all("name ASC");
        $nextNumber = $m->nextNumber();
        $this->view(
            "delivery_notes/index",
            compact("notes", "clients", "products", "warehouses", "nextNumber"),
        );
    }
    public function store(): void
    {
        validate_csrf();
        $deliveryNoteModel = new DeliveryNote();
        $deliveryNoteModel->create(
            [
                "client_id" => (int) $_POST["client_id"],
                "note_number" => trim($_POST["note_number"]),
                "note_date" => $_POST["note_date"],
                "notes" => trim($_POST["notes"] ?? ""),
            ],
            [
                [
                    "product_id" => (int) $_POST["product_id"],
                    "warehouse_id" => (int) $_POST["warehouse_id"],
                    "quantity" => (float) $_POST["quantity"],
                ],
            ],
        );
        flash(
            "success",
            "Nota de entrega registrada. Puedes consultarla luego en la tabla.",
        );
        $this->redirect("/delivery-notes");
    }
    public function print(string $id): void
    {
        $deliveryNoteModel = new DeliveryNote();
        $note = $deliveryNoteModel->findFull((int) $id);
        $this->view("delivery_notes/print", compact("note"), "layouts/print");
    }
}
