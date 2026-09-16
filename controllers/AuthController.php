<?php

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: ' . APP_URL . '/dashboard');
            exit;
        }

        require __DIR__ . '/../views/admin/login.php';
    }

    public function login(): void
    {
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $_SESSION['flash_error'] = 'La sesión del formulario venció. Volvé a intentarlo.';
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $_SESSION['flash_error'] = 'Completá email y contraseña.';
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        if (Auth::attempt($email, $password)) {
            header('Location: ' . APP_URL . '/dashboard');
            exit;
        }

        $_SESSION['flash_error'] = 'Email o contraseña incorrectos.';
        header('Location: ' . APP_URL . '/login');
        exit;
    }

    public function logout(): void
    {
        Auth::logout();

        header('Location: ' . APP_URL . '/login');
        exit;
    }
}