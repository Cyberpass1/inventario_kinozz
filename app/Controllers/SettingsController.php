<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\RateHistory;
use App\Models\Settings;
use App\Models\User;
use App\Services\BcvRateService;

class SettingsController extends Controller
{
    public function index(): void
    {
        $model = new Settings();
        $model->set('currency_base', 'USD');
        $model->set('currency_secondary', 'VES');

        $settings = $model->getAllKeyed();
        $rates = (new RateHistory())->all('rate_date DESC');
        $service = new BcvRateService();

        try {
            $resolved = $service->resolve(date('Y-m-d'));
            $rateMeta = $service->currentMeta($settings, $resolved);
        } catch (\Throwable) {
            $rateMeta = $service->currentMeta($settings);
        }

        $this->view('settings/index', compact('settings', 'rates', 'rateMeta'));
    }

    public function users(): void
    {
        $users = (new User())->allManaged();
        $this->view('settings/users', compact('users'));
    }

    public function save(): void
    {
        validate_csrf();

        $model = new Settings();
        $mode = strtolower(trim((string) ($_POST['exchange_rate_mode'] ?? 'bcv_usd')));
        $mode = in_array($mode, ['bcv_usd', 'bcv_eur', 'custom'], true) ? $mode : 'bcv_usd';
        $customCurrency = strtoupper(trim((string) ($_POST['exchange_rate_custom_currency'] ?? 'USD')));
        $customCurrency = in_array($customCurrency, ['USD', 'EUR'], true) ? $customCurrency : 'USD';
        $customRate = (float) ($_POST['exchange_rate_custom'] ?? 0);
        $taxPercent = trim((string) ($_POST['tax_percent'] ?? tax_percent()));
        $invoiceDueDays = max(0, (int) ($_POST['invoice_due_days'] ?? invoice_due_days()));
        $purchaseDueDays = max(0, (int) ($_POST['purchase_due_days'] ?? purchase_due_days()));
        $productionEnabled = isset($_POST['production_enabled']) ? '1' : '0';

        if ($mode === 'custom' && $customRate <= 0) {
            flash('error', 'La tasa personalizada debe ser mayor a cero.');
            $this->redirect('/settings');
        }

        $model->set('exchange_rate_mode', $mode);
        $model->set('exchange_rate_custom_currency', $customCurrency);
        $model->set('exchange_rate_custom', (string) $customRate);
        $model->set('tax_percent', $taxPercent);
        $model->set('invoice_due_days', (string) $invoiceDueDays);
        $model->set('purchase_due_days', (string) $purchaseDueDays);
        $model->set('production_enabled', $productionEnabled);
        $model->set('currency_base', 'USD');
        $model->set('currency_secondary', 'VES');

        try {
            $resolved = (new BcvRateService())->syncConfiguredRate(true);
            flash('success', 'Configuracion guardada. Tasa activa: ' . money($resolved['rate']) . ' ' . $resolved['currency_to'] . ' por ' . $resolved['currency_from'] . '.');
        } catch (\Throwable $exception) {
            flash('error', 'La configuracion se guardo, pero no se pudo sincronizar BCV en este momento: ' . $exception->getMessage());
        }

        $this->redirect('/settings/users');
    }

    public function syncRate(): void
    {
        validate_csrf();

        try {
            $resolved = (new BcvRateService())->syncConfiguredRate(true);
            flash('success', 'Tasa sincronizada desde ' . $resolved['source'] . ': ' . money($resolved['rate']) . ' ' . $resolved['currency_to'] . '.');
        } catch (\Throwable $exception) {
            flash('error', 'No se pudo sincronizar la tasa: ' . $exception->getMessage());
        }

        $this->redirect('/settings/users');
    }

    public function storeUser(): void
    {
        validate_csrf();

        try {
            $userModel = new User();
            $username = $this->sanitizeUsername((string) ($_POST['username'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = $this->sanitizeEmail((string) ($_POST['email'] ?? ''));
            $role = $this->sanitizeManagedRole((string) ($_POST['role'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($name === '') {
                throw new \RuntimeException('Debes indicar el nombre del usuario.');
            }

            if ($username === '') {
                throw new \RuntimeException('Debes indicar un nombre de usuario valido.');
            }

            if ($password === '' || strlen($password) < 6) {
                throw new \RuntimeException('La contrasena debe tener al menos 6 caracteres.');
            }

            if ($userModel->usernameExists($username)) {
                throw new \RuntimeException('Ese nombre de usuario ya existe.');
            }

            if ($email !== '' && $userModel->emailExists($email)) {
                throw new \RuntimeException('Ese correo ya esta registrado.');
            }

            $userModel->insert([
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'role' => $role,
                'is_active' => 1,
            ]);

            flash('success', 'Usuario creado correctamente.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/settings/users');
    }

    public function updateUser(string $id): void
    {
        validate_csrf();

        try {
            $userModel = new User();
            $userId = (int) $id;
            $user = $userModel->find($userId);

            if (! $user) {
                throw new \RuntimeException('Usuario no encontrado.');
            }

            $username = $this->sanitizeUsername((string) ($_POST['username'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = $this->sanitizeEmail((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($name === '') {
                throw new \RuntimeException('Debes indicar el nombre del usuario.');
            }

            if ($username === '') {
                throw new \RuntimeException('Debes indicar un nombre de usuario valido.');
            }

            if ($userModel->usernameExists($username, $userId)) {
                throw new \RuntimeException('Ese nombre de usuario ya existe.');
            }

            if ($email !== '' && $userModel->emailExists($email, $userId)) {
                throw new \RuntimeException('Ese correo ya esta registrado.');
            }

            $data = [
                'username' => $username,
                'name' => $name,
                'email' => $email !== '' ? $email : null,
            ];

            if (($user['role'] ?? '') !== 'administrator') {
                $data['role'] = $this->sanitizeManagedRole((string) ($_POST['role'] ?? ''));
            }

            if ($password !== '') {
                if (strlen($password) < 6) {
                    throw new \RuntimeException('La nueva contrasena debe tener al menos 6 caracteres.');
                }

                $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $userModel->update($userId, $data);

            $sessionUser = auth_user();
            if ((int) ($sessionUser['id'] ?? 0) === $userId) {
                $_SESSION['user']['username'] = $data['username'];
                $_SESSION['user']['name'] = $data['name'];
                if (isset($data['role'])) {
                    $_SESSION['user']['role'] = $data['role'];
                }
            }

            flash('success', 'Usuario actualizado correctamente.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/settings/users');
    }

    public function toggleUserStatus(string $id): void
    {
        validate_csrf();

        try {
            $userModel = new User();
            $userId = (int) $id;
            $user = $userModel->find($userId);

            if (! $user) {
                throw new \RuntimeException('Usuario no encontrado.');
            }

            if (($user['role'] ?? '') === 'administrator') {
                throw new \RuntimeException('No puedes desactivar cuentas de administrador desde este modulo.');
            }

            $sessionUser = auth_user();
            if ((int) ($sessionUser['id'] ?? 0) === $userId) {
                throw new \RuntimeException('No puedes desactivar tu propia sesion.');
            }

            $newState = ((int) ($user['is_active'] ?? 1) === 1) ? 0 : 1;
            $userModel->update($userId, ['is_active' => $newState]);

            flash('success', $newState === 1 ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/settings/users');
    }

    public function profile(): void
    {
        $sessionUser = auth_user();
        $userId = (int) ($sessionUser['id'] ?? 0);

        if ($userId <= 0) {
            $this->redirect('/login');
        }

        $user = (new User())->find($userId);

        if (! $user) {
            flash('error', 'No se pudo cargar tu perfil.');
            $this->redirect('/dashboard');
        }

        $this->view('settings/profile', compact('user'));
    }

    public function updateProfile(): void
    {
        validate_csrf();

        $sessionUser = auth_user();
        $userId = (int) ($sessionUser['id'] ?? 0);

        if ($userId <= 0) {
            $this->redirect('/login');
        }

        try {
            $userModel = new User();
            $user = $userModel->find($userId);

            if (! $user) {
                throw new \RuntimeException('No se pudo cargar tu perfil.');
            }

            $username = $this->sanitizeUsername((string) ($_POST['username'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = $this->sanitizeEmail((string) ($_POST['email'] ?? ''));
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

            if ($name === '') {
                throw new \RuntimeException('Debes indicar tu nombre.');
            }

            if ($username === '') {
                throw new \RuntimeException('Debes indicar un nombre de usuario valido.');
            }

            if ($userModel->usernameExists($username, $userId)) {
                throw new \RuntimeException('Ese nombre de usuario ya esta en uso.');
            }

            if ($email !== '' && $userModel->emailExists($email, $userId)) {
                throw new \RuntimeException('Ese correo ya esta registrado por otra cuenta.');
            }

            $data = [
                'username' => $username,
                'name' => $name,
                'email' => $email !== '' ? $email : null,
            ];

            if ($newPassword !== '' || $newPasswordConfirm !== '' || $currentPassword !== '') {
                if ($currentPassword === '' || ! password_verify($currentPassword, (string) ($user['password'] ?? ''))) {
                    throw new \RuntimeException('La contrasena actual no es correcta.');
                }

                if (strlen($newPassword) < 6) {
                    throw new \RuntimeException('La nueva contrasena debe tener al menos 6 caracteres.');
                }

                if ($newPassword !== $newPasswordConfirm) {
                    throw new \RuntimeException('Las contrasenas nuevas no coinciden.');
                }

                $data['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            $userModel->update($userId, $data);

            $_SESSION['user']['username'] = $data['username'];
            $_SESSION['user']['name'] = $data['name'];
            if (array_key_exists('email', $data)) {
                $_SESSION['user']['email'] = $data['email'];
            }

            flash('success', 'Tu perfil se actualizo correctamente.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        $this->redirect('/profile');
    }

    private function sanitizeManagedRole(string $role): string
    {
        $role = strtolower(trim($role));
        if (!in_array($role, ['vendor', 'general_consultant'], true)) {
            throw new \RuntimeException('Debes seleccionar un rol permitido.');
        }

        return $role;
    }

    private function sanitizeUsername(string $username): string
    {
        $username = strtolower(trim($username));
        $username = preg_replace('/[^a-z0-9._-]/', '', $username) ?? '';
        return $username;
    }

    private function sanitizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return '';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Debes indicar un correo valido.');
        }

        return $email;
    }
}

