<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Client;

class ClientControllerModern extends Controller
{
    public function search(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $results = (new Client())->search($query, 10);

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'results' => array_map(static fn (array $client): array => [
                'id' => (int) $client['id'],
                'name' => (string) ($client['name'] ?? ''),
                'document' => (string) ($client['document'] ?? ''),
                'phone' => (string) ($client['phone'] ?? ''),
                'email' => (string) ($client['email'] ?? ''),
                'invoices_count' => (int) ($client['invoices_count'] ?? 0),
                'last_invoice_date' => (string) ($client['last_invoice_date'] ?? ''),
            ], $results),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index(): void
    {
        $clientModel = new Client();
        $clients = $clientModel->allWithStats();
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $currentClient = $editId > 0 ? $clientModel->find($editId) : null;

        $summary = [
            'clients' => count($clients),
            'with_invoices' => count(array_filter($clients, fn (array $client): bool => (int) $client['invoices_count'] > 0)),
        ];

        $this->view('clients/workspace', compact('clients', 'currentClient', 'summary'), 'layouts/app_modern');
    }

    public function store(): void
    {
        validate_csrf();

        (new Client())->insert([
            'name' => trim($_POST['name']),
            'document' => trim($_POST['document'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
        ]);

        flash('success', 'Cliente creado.');
        $this->redirect($this->redirectTarget());
    }

    public function update(string $id): void
    {
        validate_csrf();

        (new Client())->update((int) $id, [
            'name' => trim($_POST['name']),
            'document' => trim($_POST['document'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
        ]);

        flash('success', 'Cliente actualizado.');
        $this->redirect($this->redirectTarget());
    }

    private function redirectTarget(): string
    {
        $target = trim((string) ($_POST['redirect_to'] ?? '/clients'));
        return $target !== '' ? $target : '/clients';
    }
}
