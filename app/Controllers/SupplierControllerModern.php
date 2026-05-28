<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Supplier;

class SupplierControllerModern extends Controller
{
    public function index(): void
    {
        $supplierModel = new Supplier();
        $suppliers = $supplierModel->allWithStats();
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $currentSupplier = $editId > 0 ? $supplierModel->find($editId) : null;

        $summary = [
            'suppliers' => count($suppliers),
            'active' => count(array_filter($suppliers, static fn (array $supplier): bool => (int) ($supplier['is_active'] ?? 1) === 1)),
            'with_purchases' => count(array_filter($suppliers, static fn (array $supplier): bool => (int) ($supplier['purchases_count'] ?? 0) > 0)),
        ];

        $this->view('suppliers/workspace', compact('suppliers', 'currentSupplier', 'summary'), 'layouts/app_modern');
    }

    public function store(): void
    {
        validate_csrf();

        (new Supplier())->insert([
            'name' => trim((string) ($_POST['name'] ?? '')),
            'document' => trim((string) ($_POST['document'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'is_active' => 1,
        ]);

        flash('success', 'Proveedor creado.');
        $this->redirect('/suppliers');
    }

    public function update(string $id): void
    {
        validate_csrf();

        (new Supplier())->update((int) $id, [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'document' => trim((string) ($_POST['document'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
        ]);

        flash('success', 'Proveedor actualizado.');
        $this->redirect('/suppliers');
    }

    public function toggleStatus(string $id): void
    {
        validate_csrf();

        $supplierModel = new Supplier();
        $supplier = $supplierModel->find((int) $id);
        if (! $supplier) {
            flash('error', 'Proveedor no encontrado.');
            $this->redirect('/suppliers');
            return;
        }

        $supplierModel->toggleStatus((int) $id);
        $active = (int) ($supplier['is_active'] ?? 1) === 1;

        flash('success', $active ? 'Proveedor inactivado.' : 'Proveedor reactivado.');
        $this->redirect('/suppliers');
    }
}
