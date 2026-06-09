<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\CashAccount;

class TreasuryAccountController extends Controller
{
    public function index(): void
    {
        $model = new CashAccount();
        $accounts = $model->allManaged();
        $accountsWithMovements = $model->idsWithMovements();
        $this->view('settings/treasury_accounts', compact('accounts', 'accountsWithMovements'));
    }

    public function store(): void
    {
        validate_csrf();

        try {
            $name = trim((string) ($_POST['account_name'] ?? ''));
            if ($name === '') {
                throw new \RuntimeException('El nombre de la cuenta es obligatorio.');
            }

            $type = treasury_account_type_normalize((string) ($_POST['account_type'] ?? 'bank'));
            $currency = normalize_currency_code((string) ($_POST['currency_code'] ?? base_currency()));

            (new CashAccount())->createAccount([
                'account_name' => $name,
                'account_type' => $type,
                'currency_code' => $currency,
                'opening_balance' => $_POST['opening_balance'] ?? 0,
            ]);

            flash('success', 'Cuenta de tesoreria creada.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/settings/treasury-accounts');
    }

    public function update(string $id): void
    {
        validate_csrf();

        try {
            $accountId = (int) $id;
            $name = trim((string) ($_POST['account_name'] ?? ''));
            if ($name === '') {
                throw new \RuntimeException('El nombre de la cuenta es obligatorio.');
            }

            (new CashAccount())->updateAccount($accountId, [
                'account_name' => $name,
                'account_type' => (string) ($_POST['account_type'] ?? 'bank'),
                'currency_code' => $_POST['currency_code'] ?? null,
                'opening_balance' => $_POST['opening_balance'] ?? 0,
            ]);

            flash('success', 'Cuenta de tesoreria actualizada.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/settings/treasury-accounts');
    }

    public function toggleStatus(string $id): void
    {
        validate_csrf();

        try {
            $model = new CashAccount();
            $accountId = (int) $id;
            $account = $model->find($accountId);
            if (! $account) {
                throw new \RuntimeException('Cuenta de tesoreria no encontrada.');
            }

            $isActive = (int) ($account['is_active'] ?? 1) === 1;

            if ($isActive && $model->activeCountForCurrency((string) $account['currency_code'], $accountId) === 0) {
                throw new \RuntimeException(
                    'No puedes desactivar la unica cuenta activa en ' . $account['currency_code']
                    . '. Crea o activa otra cuenta en esa moneda primero.'
                );
            }

            $model->setStatus($accountId, ! $isActive);
            flash('success', $isActive ? 'Cuenta desactivada.' : 'Cuenta activada.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/settings/treasury-accounts');
    }
}
