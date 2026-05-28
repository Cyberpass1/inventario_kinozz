<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Category;
use App\Models\Product;

class ServiceControllerModern extends Controller
{
    public function index(): void
    {
        $productModel = new Product();
        $services = $productModel->serviceList();
        $categories = (new Category())->all('name ASC');
        $summary = [
            'services' => count($services),
            'active' => count(array_filter($services, static fn (array $service): bool => ($service['status'] ?? 'active') === 'active')),
        ];

        $this->view('services/workspace', compact('services', 'categories', 'summary'), 'layouts/app_modern');
    }

    public function store(): void
    {
        validate_csrf();

        try {
            (new Product())->insert([
                'category_id' => (int) ($_POST['category_id'] ?? 0) ?: null,
                'sku' => trim((string) ($_POST['sku'] ?? '')),
                'name' => trim((string) ($_POST['name'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
                'product_type' => 'service',
                'stock' => 0,
                'stock_min' => 0,
                'cost' => (float) ($_POST['cost'] ?? 0),
                'price' => (float) ($_POST['price'] ?? 0),
                'currency_code' => trim((string) ($_POST['currency_code'] ?? base_currency())),
            ]);
            flash('success', 'Servicio creado.');
        } catch (\Throwable $exception) {
            flash('error', 'No se pudo crear el servicio. Verifica SKU y datos ingresados.');
        }

        $this->redirect('/services');
    }

    public function update(string $id): void
    {
        validate_csrf();

        $productModel = new Product();
        $service = $productModel->findVisible((int) $id);

        if (! $service || (string) ($service['product_type'] ?? '') !== 'service') {
            flash('error', 'Servicio no encontrado.');
            $this->redirect('/services');
        }

        try {
            $productModel->updateCatalog((int) $id, [
                'category_id' => (int) ($_POST['category_id'] ?? 0) ?: null,
                'sku' => trim((string) ($_POST['sku'] ?? '')),
                'name' => trim((string) ($_POST['name'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
                'product_type' => 'service',
                'stock_min' => 0,
                'cost' => (float) ($_POST['cost'] ?? 0),
                'price' => (float) ($_POST['price'] ?? 0),
                'currency_code' => trim((string) ($_POST['currency_code'] ?? base_currency())),
            ]);
            flash('success', 'Servicio actualizado.');
        } catch (\Throwable $exception) {
            flash('error', 'No se pudo actualizar el servicio.');
        }

        $this->redirect('/services');
    }

    public function toggleStatus(string $id): void
    {
        validate_csrf();

        $productModel = new Product();
        $service = $productModel->findVisible((int) $id);

        if (! $service || (string) ($service['product_type'] ?? '') !== 'service') {
            flash('error', 'Servicio no encontrado.');
            $this->redirect('/services');
        }

        $nextStatus = (($service['status'] ?? 'active') === 'active') ? 'inactive' : 'active';
        $productModel->setStatus((int) $id, $nextStatus);
        flash('success', $nextStatus === 'inactive' ? 'Servicio desactivado.' : 'Servicio activado.');
        $this->redirect('/services');
    }
}
