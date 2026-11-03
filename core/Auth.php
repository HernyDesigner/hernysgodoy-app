<?php

require_once __DIR__ . '/RememberToken.php';

class Auth
{
    private const ROLE_SUPER_ADMIN = 'super_admin';
    private const ROLE_BUSINESS_ADMIN = 'business_admin';
    private const ROLE_PROFESSIONAL = 'professional';
    private const DUMMY_PASSWORD_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    private static bool $sessionValidated = false;
    private static ?array $authenticatedUser = null;

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

    public static function attempt(
        string $email,
        string $password,
        string $remoteAddress,
        bool $remember = false
    ): bool
    {
        self::startSession();

        $email = trim($email);

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !filter_var($remoteAddress, FILTER_VALIDATE_IP)
        ) {
            return false;
        }

        try {
            $db = Database::connectOrThrow();
            $rateLimiter = new LoginRateLimiter($db);
            $reservation = $rateLimiter->reserve($email, $remoteAddress);

            if ($reservation === null) {
                return false;
            }

            $stmt = $db->prepare("
                SELECT
                    id,
                    business_id,
                    name,
                    email,
                    password_hash,
                    role,
                    status,
                    auth_version
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $passwordHash = is_array($user) && is_string($user['password_hash'] ?? null)
                ? $user['password_hash']
                : self::DUMMY_PASSWORD_HASH;
            $passwordMatches = password_verify($password, $passwordHash);
            $authenticatedUser = null;

            if ($user && $passwordMatches) {
                $authenticatedUser = self::buildAuthenticatedUser($db, $user);
            }

            $succeeded = $authenticatedUser !== null;

            if (!$rateLimiter->finalize($reservation, $succeeded)) {
                return false;
            }

            if (!$succeeded) {
                return false;
            }

            $update = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $update->execute([$user['id']]);
        } catch (Throwable $e) {
            return false;
        }

        session_regenerate_id(true);

        $existingTokenId = (int) ($_SESSION['remember_token_id'] ?? 0);
        $_SESSION['user'] = $authenticatedUser;
        unset($_SESSION['remember_token_id']);
        self::$authenticatedUser = $authenticatedUser;
        self::$sessionValidated = true;

        try {
            $rememberTokens = new RememberToken($db);
            $presentedToken = RememberToken::cookieValue();

            if ($existingTokenId > 0) {
                $rememberTokens->revokeById($existingTokenId);
            } elseif ($presentedToken !== null) {
                $rememberTokens->revokePresentedToken($presentedToken);
            }

            if ($remember) {
                $credential = $rememberTokens->create(
                    (int) $authenticatedUser['id'],
                    (int) $authenticatedUser['auth_version']
                );

                if (!RememberToken::storeCookie($credential['token'], $credential['expires_at'])) {
                    $rememberTokens->revokeById((int) $credential['token_id']);
                    throw new RuntimeException('No se pudo guardar la cookie persistente.');
                }

                $_SESSION['remember_token_id'] = (int) $credential['token_id'];
            } else {
                RememberToken::clearCookie();
            }
        } catch (Throwable $e) {
            unset($_SESSION['remember_token_id']);
            RememberToken::clearCookie();
        }

        return true;
    }

    public static function check(): bool
    {
        self::startSession();

        if (self::$sessionValidated) {
            return self::$authenticatedUser !== null;
        }

        self::$sessionValidated = true;

        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        if ($userId <= 0) {
            if (self::restoreRememberedSession()) {
                return true;
            }

            self::$authenticatedUser = null;
            return false;
        }

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                business_id,
                name,
                email,
                role,
                status,
                auth_version
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            self::invalidateSession(true);
            return false;
        }

        $authenticatedUser = self::buildAuthenticatedUser($db, $user);

        if ($authenticatedUser === null) {
            self::invalidateSession(true);
            return false;
        }

        $sessionAuthVersion = (int) ($_SESSION['user']['auth_version'] ?? 0);
        $currentAuthVersion = (int) ($authenticatedUser['auth_version'] ?? 0);

        if ($sessionAuthVersion !== $currentAuthVersion) {
            self::invalidateSession(true);
            return false;
        }

        $_SESSION['user'] = $authenticatedUser;
        self::$authenticatedUser = $authenticatedUser;

        return true;
    }

    public static function user(): ?array
    {
        return self::check() ? self::$authenticatedUser : null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . APP_URL . '/login');
            exit;
        }
    }

    public static function requireAdministrativeAccess(bool $json = false): void
    {
        if (!self::check()) {
            if ($json) {
                self::respondJsonAccessDenied(401, 'No autorizado');
            }

            header('Location: ' . APP_URL . '/login');
            exit;
        }

        if (!self::isAdministrator()) {
            if ($json) {
                self::respondJsonAccessDenied(403, 'Acceso no permitido');
            }

            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Acceso no permitido.';
            exit;
        }
    }

    public static function requireAppointmentAccess(bool $json = false): void
    {
        if (!self::check()) {
            if ($json) {
                self::respondJsonAccessDenied(401, 'No autorizado');
            }

            header('Location: ' . APP_URL . '/login');
            exit;
        }

        if (!self::canManageAppointments()) {
            if ($json) {
                self::respondJsonAccessDenied(403, 'Acceso no permitido');
            }

            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Acceso no permitido.';
            exit;
        }
    }

    public static function requireScheduleAccess(bool $json = false): void
    {
        if (!self::check()) {
            if ($json) {
                self::respondJsonAccessDenied(401, 'No autorizado');
            }

            header('Location: ' . APP_URL . '/login');
            exit;
        }

        if (!self::canManageSchedule()) {
            if ($json) {
                self::respondJsonAccessDenied(403, 'Acceso no permitido');
            }

            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Acceso no permitido.';
            exit;
        }
    }

    public static function logout(): void
    {
        self::startSession();

        self::invalidateSession(true);
    }

    public static function isAdministrator(): bool
    {
        return self::hasRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_BUSINESS_ADMIN,
        ]);
    }

    public static function canManageAppointments(): bool
    {
        return self::hasRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_PROFESSIONAL,
        ]);
    }

    public static function canManageSchedule(): bool
    {
        return self::isAdministrator()
            || (self::isProfessional() && self::canManageOwnSchedule());
    }

    public static function isBusinessAdmin(): bool
    {
        return self::hasRole([self::ROLE_BUSINESS_ADMIN]);
    }

    public static function isProfessional(): bool
    {
        return self::hasRole([self::ROLE_PROFESSIONAL]);
    }

    public static function businessId(): ?int
    {
        $user = self::user();

        if (!$user) {
            return null;
        }

        if ($user['role'] === self::ROLE_SUPER_ADMIN) {
            return 1;
        }

        $businessId = (int) ($user['business_id'] ?? 0);

        return $businessId > 0 ? $businessId : null;
    }

    public static function professionalId(): ?int
    {
        $user = self::user();
        $professionalId = (int) ($user['professional_id'] ?? 0);

        return $professionalId > 0 ? $professionalId : null;
    }

    public static function canManageOwnSchedule(): bool
    {
        $user = self::user();

        return $user !== null
            && $user['role'] === self::ROLE_PROFESSIONAL
            && ($user['can_manage_own_schedule'] ?? false) === true;
    }

    public static function isSuperAdmin(): bool
    {
        return self::hasRole([self::ROLE_SUPER_ADMIN]);
    }

    private static function hasRole(array $roles): bool
    {
        $user = self::user();

        return $user !== null && in_array($user['role'], $roles, true);
    }

    private static function restoreRememberedSession(): bool
    {
        $presentedToken = RememberToken::cookieValue();

        if ($presentedToken === null) {
            return false;
        }

        try {
            $db = Database::connectOrThrow();
            $rememberTokens = new RememberToken($db);
            $recovery = $rememberTokens->recover(
                $presentedToken,
                static function (array $user) use ($db): ?array {
                    return self::buildAuthenticatedUser($db, $user);
                }
            );
        } catch (Throwable $e) {
            self::$authenticatedUser = null;
            return false;
        }

        if (!is_array($recovery)) {
            RememberToken::clearCookie();
            self::$authenticatedUser = null;
            return false;
        }

        $authenticatedUser = $recovery['authenticated_user'] ?? null;
        $tokenId = (int) ($recovery['token_id'] ?? 0);

        if (!is_array($authenticatedUser) || $tokenId <= 0) {
            RememberToken::clearCookie();
            self::$authenticatedUser = null;
            return false;
        }

        $rotatedToken = $recovery['rotated_token'] ?? null;

        if (is_string($rotatedToken)) {
            $expiresAt = $recovery['expires_at'] ?? null;

            if (
                !($expiresAt instanceof DateTimeImmutable)
                || !RememberToken::storeCookie($rotatedToken, $expiresAt)
            ) {
                try {
                    $rememberTokens->revokeById((int) $recovery['token_id']);
                } catch (Throwable $e) {
                }

                RememberToken::clearCookie();
                self::$authenticatedUser = null;
                return false;
            }
        }

        session_regenerate_id(true);
        $_SESSION['user'] = $authenticatedUser;
        $_SESSION['remember_token_id'] = $tokenId;
        self::$authenticatedUser = $authenticatedUser;
        self::$sessionValidated = true;

        return true;
    }

    private static function buildAuthenticatedUser($db, array $user): ?array
    {
        if (($user['status'] ?? '') !== 'active') {
            return null;
        }

        $role = (string) ($user['role'] ?? '');

        if (!in_array($role, self::allowedRoles(), true)) {
            return null;
        }

        $businessId = isset($user['business_id']) ? (int) $user['business_id'] : null;
        $professionalId = null;
        $canManageOwnSchedule = false;

        if ($role === self::ROLE_BUSINESS_ADMIN && (!$businessId || $businessId <= 0)) {
            return null;
        }

        if ($role === self::ROLE_PROFESSIONAL) {
            if (!$businessId || $businessId <= 0) {
                return null;
            }

            $stmt = $db->prepare("
                SELECT
                    id,
                    business_id,
                    is_active,
                    can_manage_own_schedule
                FROM professionals
                WHERE user_id = ?
                ORDER BY id ASC
                LIMIT 2
            ");

            $stmt->execute([(int) $user['id']]);
            $professionals = $stmt->fetchAll();

            if (count($professionals) !== 1) {
                return null;
            }

            $professional = $professionals[0];
            $professionalBusinessId = (int) ($professional['business_id'] ?? 0);

            if (
                $professionalBusinessId <= 0
                || $professionalBusinessId !== $businessId
                || (int) ($professional['is_active'] ?? 0) !== 1
            ) {
                return null;
            }

            $professionalId = (int) $professional['id'];
            $canManageOwnSchedule = (int) ($professional['can_manage_own_schedule'] ?? 0) === 1;
        }

        return [
            'id' => (int) $user['id'],
            'business_id' => $businessId,
            'professional_id' => $professionalId,
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => $role,
            'can_manage_own_schedule' => $canManageOwnSchedule,
            'auth_version' => (int) ($user['auth_version'] ?? 0),
        ];
    }

    private static function allowedRoles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_PROFESSIONAL,
        ];
    }

    private static function respondJsonAccessDenied(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function invalidateSession(bool $revokeRememberToken = false): void
    {
        if ($revokeRememberToken) {
            self::revokeRememberTokenForCurrentDevice();
        }

        RememberToken::clearCookie();
        self::$authenticatedUser = null;
        self::$sessionValidated = true;

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

    private static function revokeRememberTokenForCurrentDevice(): void
    {
        $tokenId = (int) ($_SESSION['remember_token_id'] ?? 0);
        $presentedToken = RememberToken::cookieValue();

        if ($tokenId <= 0 && $presentedToken === null) {
            return;
        }

        try {
            $rememberTokens = new RememberToken(Database::connectOrThrow());

            if ($tokenId > 0) {
                $rememberTokens->revokeById($tokenId);
            } elseif ($presentedToken !== null) {
                $rememberTokens->revokePresentedToken($presentedToken);
            }
        } catch (Throwable $e) {
        }
    }

    public static function csrfToken(): string
    {
        self::startSession();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(?string $token): bool
    {
        self::startSession();

        if (
            empty($_SESSION['csrf_token']) ||
            empty($token)
        ) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

}
