<?php

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/app',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_start();
        }
    }

    public static function attempt(string $email, string $password): bool
    {
        self::startSession();

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT 
                id,
                business_id,
                name,
                email,
                password_hash,
                role,
                status
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        if ($user['status'] !== 'active') {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $user['id'],
            'business_id' => $user['business_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ];

        $update = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $update->execute([$user['id']]);

        return true;
    }

    public static function check(): bool
    {
        self::startSession();
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . APP_URL . '/login');
            exit;
        }
    }

    public static function logout(): void
    {
        self::startSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function isSuperAdmin(): bool
    {
        $user = self::user();
        return $user && $user['role'] === 'super_admin';
    }
}