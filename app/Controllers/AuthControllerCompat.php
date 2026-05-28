<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class AuthControllerCompat extends Controller
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }

        $this->view('auth/login', [], 'layouts/guest');
    }

    public function login(): void
    {
        validate_csrf();

        if (Auth::attempt(trim($_POST['username'] ?? ''), trim($_POST['password'] ?? ''))) {
            flash('success', 'Bienvenido al sistema.');
            $this->redirect('/dashboard');
        }

        flash('error', 'Credenciales inválidas.');
        $this->redirect('/login');
    }

    public function logout(): void
    {
        validate_csrf();
        Auth::logout();
        header('Location: ' . app_url('/login'));
        exit;
    }
}
