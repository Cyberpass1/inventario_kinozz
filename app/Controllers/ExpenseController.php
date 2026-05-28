<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
class ExpenseController extends Controller
{
    public function index(): void
    {
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $expenses = (new Expense())->byRange($from, $to);
        $categoryModel = new ExpenseCategory();
        $categories = $categoryModel->all('name ASC');
        $categoryUsage = [];
        foreach ($categories as $category) {
            $categoryUsage[(int) $category['id']] = $categoryModel->expensesCount((int) $category['id']);
        }
        $rate = ['rate' => system_exchange_rate(date('Y-m-d')), 'currency_from' => 'USD', 'currency_to' => 'VES'];
        $summary = [
            'operations' => count($expenses),
            'total' => array_reduce(
                $expenses,
                static fn (float $carry, array $expense): float => $carry + expense_currency_breakdown(
                    (float) ($expense['amount_original'] ?? 0),
                    (string) ($expense['currency_code'] ?? ''),
                    (float) ($expense['exchange_rate'] ?? 0)
                )['amount_consolidated'],
                0.0
            ),
            'categories' => count($categories),
        ];

        $this->view('expenses/index', compact('expenses', 'categories', 'categoryUsage', 'rate', 'from', 'to', 'summary'), 'layouts/app_modern');
    }

    public function storeCategory(): void
    {
        validate_csrf();
        (new ExpenseCategory())->insert(['name' => trim($_POST['name'])]);
        flash('success', 'Categoria de gasto creada.');
        $this->redirect('/expenses');
    }

    public function updateCategory(string $id): void
    {
        validate_csrf();

        $categoryModel = new ExpenseCategory();
        $category = $categoryModel->find((int) $id);
        if (! $category) {
            flash('error', 'Categoria de gasto no encontrada.');
            $this->redirect('/expenses');
        }

        $categoryModel->update((int) $id, ['name' => trim((string) ($_POST['name'] ?? ''))]);
        flash('success', 'Categoria de gasto actualizada.');
        $this->redirect('/expenses');
    }

    public function deleteCategory(string $id): void
    {
        validate_csrf();

        $categoryModel = new ExpenseCategory();
        $categoryId = (int) $id;
        $category = $categoryModel->find($categoryId);
        if (! $category) {
            flash('error', 'Categoria de gasto no encontrada.');
            $this->redirect('/expenses');
        }

        if ($categoryModel->expensesCount($categoryId) > 0) {
            flash('error', 'No puedes eliminar esta categoria porque ya tiene gastos asociados.');
            $this->redirect('/expenses');
        }

        $categoryModel->delete($categoryId);
        flash('success', 'Categoria de gasto eliminada.');
        $this->redirect('/expenses');
    }

    public function store(): void
    {
        validate_csrf();
        try {
            (new Expense())->createRegistered($this->buildExpensePayload($_POST));

            flash('success', 'Gasto registrado.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/expenses');
    }

    public function update(string $id): void
    {
        validate_csrf();

        try {
            (new Expense())->updateRegistered((int) $id, $this->buildExpensePayload($_POST));
            flash('success', 'Gasto actualizado.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/expenses');
    }

    public function cancel(string $id): void
    {
        validate_csrf();

        try {
            (new Expense())->cancel((int) $id, trim((string) ($_POST['reason'] ?? '')));
            flash('success', 'Gasto anulado.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/expenses');
    }

    private function buildExpensePayload(array $source): array
    {
        $amount = round_money(parse_money_input($source['amount_original'] ?? 0));
        $documentDate = (string) ($source['expense_date'] ?? date('Y-m-d'));
        $paymentMethod = strtolower(trim((string) ($source['payment_method'] ?? 'cash')));
        if (!array_key_exists($paymentMethod, payment_method_options())) {
            throw new \RuntimeException('Debes seleccionar un metodo de salida valido para el gasto.');
        }

        $currency = normalize_currency_code((string) ($source['currency_code'] ?? secondary_currency()));
        $rate = system_exchange_rate($documentDate);

        if ($amount <= 0) {
            throw new \RuntimeException('Debes indicar un monto mayor a cero para registrar el gasto.');
        }

        $categoryId = (int) ($source['category_id'] ?? 0);
        $customCategoryName = trim((string) ($source['custom_category_name'] ?? ''));

        if ($categoryId <= 0 && $customCategoryName !== '') {
            $categoryId = $this->resolveOrCreateCategory($customCategoryName);
        }

        if ($categoryId <= 0) {
            throw new \RuntimeException('Debes seleccionar o crear una categoria de gasto.');
        }

        $amounts = expense_currency_breakdown($amount, $currency, $rate);

        return [
            'category_id' => $categoryId,
            'expense_date' => $documentDate,
            'reference' => trim((string) ($source['reference'] ?? '')),
            'description' => trim((string) ($source['description'] ?? '')),
            'currency_code' => $currency,
            'exchange_rate' => $rate,
            'amount_original' => $amounts['amount_original'],
            'amount_converted' => $amounts['amount_consolidated'],
            'payment_method' => $paymentMethod,
        ];
    }

    private function resolveOrCreateCategory(string $name): int
    {
        $categoryModel = new ExpenseCategory();
        $existing = $categoryModel->all('name ASC');
        $normalized = mb_strtolower(trim($name));

        foreach ($existing as $row) {
            if (mb_strtolower(trim((string) ($row['name'] ?? ''))) === $normalized) {
                return (int) $row['id'];
            }
        }

        return (int) $categoryModel->insert(['name' => trim($name)]);
    }
}
