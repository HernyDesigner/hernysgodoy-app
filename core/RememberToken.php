<?php

final class RememberToken
{
    private const COOKIE_NAME = 'turnero_remember';
    private const COOKIE_PATH = '/app';
    private const TOKEN_BYTES = 32;
    private const ABSOLUTE_LIFETIME_SECONDS = 2592000;
    private const PREVIOUS_TOKEN_TOLERANCE_SECONDS = 60;

    public function __construct(private PDO $db)
    {
    }

    public static function cookieValue(): ?string
    {
        $value = $_COOKIE[self::COOKIE_NAME] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function storeCookie(string $token, DateTimeImmutable $expiresAt): bool
    {
        if (self::decodeToken($token) === null) {
            return false;
        }

        return setcookie(self::COOKIE_NAME, $token, [
            'expires' => $expiresAt->getTimestamp(),
            'path' => self::COOKIE_PATH,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 42000,
            'path' => self::COOKIE_PATH,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function create(int $userId, int $authVersion): array
    {
        if ($userId <= 0 || $authVersion < 0) {
            throw new InvalidArgumentException('Datos de usuario invalidos.');
        }

        $rawToken = random_bytes(self::TOKEN_BYTES);
        $encodedToken = self::encodeToken($rawToken);
        $now = $this->databaseNow();
        $expiresAt = $now->modify('+' . self::ABSOLUTE_LIFETIME_SECONDS . ' seconds');

        $insert = $this->db->prepare("
            INSERT INTO auth_remember_tokens
            (
                user_id,
                auth_version,
                token_hash,
                previous_token_hash,
                previous_valid_until,
                created_at,
                rotated_at,
                last_used_at,
                expires_at
            )
            VALUES
            (?, ?, ?, NULL, NULL, ?, ?, ?, ?)
        ");
        $timestamp = $this->formatTimestamp($now);
        $insert->execute([
            $userId,
            $authVersion,
            self::hashToken($rawToken),
            $timestamp,
            $timestamp,
            $timestamp,
            $this->formatTimestamp($expiresAt),
        ]);

        if ($insert->rowCount() !== 1) {
            throw new RuntimeException('No se pudo crear el acceso persistente.');
        }

        $tokenId = (int) $this->db->lastInsertId();

        if ($tokenId <= 0) {
            throw new RuntimeException('No se pudo identificar el acceso persistente.');
        }

        return [
            'token_id' => $tokenId,
            'token' => $encodedToken,
            'expires_at' => $expiresAt,
        ];
    }

    public function recover(string $encodedToken, callable $authenticateUser): ?array
    {
        $rawToken = self::decodeToken($encodedToken);

        if ($rawToken === null) {
            return null;
        }

        $presentedHash = self::hashToken($rawToken);
        $lookup = $this->db->prepare("
            SELECT id, user_id
            FROM auth_remember_tokens
            WHERE token_hash = ?
               OR previous_token_hash = ?
            LIMIT 1
        ");
        $lookup->execute([$presentedHash, $presentedHash]);
        $reference = $lookup->fetch();

        if (!$reference) {
            return null;
        }

        if ($this->db->inTransaction()) {
            throw new RuntimeException('Ya existe una transaccion activa.');
        }

        $this->db->beginTransaction();

        try {
            $now = $this->databaseNow();
            $user = $this->lockUser((int) $reference['user_id']);

            if (!$user) {
                $this->db->rollBack();
                return null;
            }

            $token = $this->lockToken(
                (int) $reference['id'],
                (int) $reference['user_id']
            );

            if (!$token) {
                $this->db->rollBack();
                return null;
            }

            $currentMatches = hash_equals((string) $token['token_hash'], $presentedHash);
            $previousHash = $token['previous_token_hash'];
            $previousMatches = is_string($previousHash)
                && hash_equals($previousHash, $presentedHash);

            if (!$currentMatches && !$previousMatches) {
                $this->db->rollBack();
                return null;
            }

            $expiresAt = $this->parseTimestamp((string) $token['expires_at']);

            if ($expiresAt <= $now) {
                $this->deleteLockedToken((int) $token['id']);
                $this->db->commit();
                return null;
            }

            $previousValidUntil = $token['previous_valid_until'] !== null
                ? $this->parseTimestamp((string) $token['previous_valid_until'])
                : null;
            $previousToleranceActive = is_string($previousHash)
                && $previousValidUntil !== null
                && $previousValidUntil > $now;

            if ($previousMatches && !$previousToleranceActive) {
                $this->deleteLockedToken((int) $token['id']);
                $this->db->commit();
                return null;
            }

            if ((int) $token['auth_version'] !== (int) $user['auth_version']) {
                $this->deleteLockedToken((int) $token['id']);
                $this->db->commit();
                return null;
            }

            $authenticatedUser = $authenticateUser($user);

            if (!is_array($authenticatedUser)) {
                $this->deleteLockedToken((int) $token['id']);
                $this->db->commit();
                return null;
            }

            $rotatedToken = null;

            if ($currentMatches && !$previousToleranceActive) {
                $newRawToken = random_bytes(self::TOKEN_BYTES);
                $rotatedToken = self::encodeToken($newRawToken);
                $previousValidUntil = $now->modify(
                    '+' . self::PREVIOUS_TOKEN_TOLERANCE_SECONDS . ' seconds'
                );
                $update = $this->db->prepare("
                    UPDATE auth_remember_tokens
                    SET
                        token_hash = ?,
                        previous_token_hash = ?,
                        previous_valid_until = ?,
                        rotated_at = ?,
                        last_used_at = ?
                    WHERE id = ?
                ");
                $timestamp = $this->formatTimestamp($now);
                $update->execute([
                    self::hashToken($newRawToken),
                    $token['token_hash'],
                    $this->formatTimestamp($previousValidUntil),
                    $timestamp,
                    $timestamp,
                    (int) $token['id'],
                ]);
            } else {
                $update = $this->db->prepare("
                    UPDATE auth_remember_tokens
                    SET last_used_at = ?
                    WHERE id = ?
                ");
                $update->execute([
                    $this->formatTimestamp($now),
                    (int) $token['id'],
                ]);
            }

            $this->db->commit();

            return [
                'authenticated_user' => $authenticatedUser,
                'token_id' => (int) $token['id'],
                'rotated_token' => $rotatedToken,
                'expires_at' => $expiresAt,
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function revokeById(int $tokenId): void
    {
        if ($tokenId <= 0) {
            return;
        }

        $delete = $this->db->prepare("
            DELETE FROM auth_remember_tokens
            WHERE id = ?
        ");
        $delete->execute([$tokenId]);
    }

    public function revokePresentedToken(string $encodedToken): void
    {
        $rawToken = self::decodeToken($encodedToken);

        if ($rawToken === null) {
            return;
        }

        $tokenHash = self::hashToken($rawToken);
        $delete = $this->db->prepare("
            DELETE FROM auth_remember_tokens
            WHERE token_hash = ?
               OR previous_token_hash = ?
        ");
        $delete->execute([$tokenHash, $tokenHash]);
    }

    public function revokeAllForUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $delete = $this->db->prepare("
            DELETE FROM auth_remember_tokens
            WHERE user_id = ?
        ");
        $delete->execute([$userId]);
    }

    private function lockUser(int $userId): ?array
    {
        $select = $this->db->prepare("
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
            FOR UPDATE
        ");
        $select->execute([$userId]);
        $user = $select->fetch();

        return $user ?: null;
    }

    private function lockToken(int $tokenId, int $userId): ?array
    {
        $select = $this->db->prepare("
            SELECT
                id,
                user_id,
                auth_version,
                token_hash,
                previous_token_hash,
                previous_valid_until,
                expires_at
            FROM auth_remember_tokens
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $select->execute([$tokenId, $userId]);
        $token = $select->fetch();

        return $token ?: null;
    }

    private function deleteLockedToken(int $tokenId): void
    {
        $delete = $this->db->prepare("
            DELETE FROM auth_remember_tokens
            WHERE id = ?
        ");
        $delete->execute([$tokenId]);
    }

    private function databaseNow(): DateTimeImmutable
    {
        $row = $this->db->query('SELECT UTC_TIMESTAMP(6) AS utc_now')->fetch();

        if (!$row || empty($row['utc_now'])) {
            throw new RuntimeException('No se pudo obtener la hora de la base de datos.');
        }

        return $this->parseTimestamp((string) $row['utc_now']);
    }

    private function parseTimestamp(string $value): DateTimeImmutable
    {
        $timezone = new DateTimeZone('UTC');
        $timestamp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $value, $timezone);

        if (!$timestamp) {
            $timestamp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);
        }

        if (!$timestamp) {
            throw new RuntimeException('Fecha invalida en el acceso persistente.');
        }

        return $timestamp;
    }

    private function formatTimestamp(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private static function encodeToken(string $rawToken): string
    {
        return rtrim(strtr(base64_encode($rawToken), '+/', '-_'), '=');
    }

    private static function decodeToken(string $encodedToken): ?string
    {
        if (preg_match('/\A[A-Za-z0-9_-]{43}\z/D', $encodedToken) !== 1) {
            return null;
        }

        $padding = (4 - (strlen($encodedToken) % 4)) % 4;
        $decoded = base64_decode(
            strtr($encodedToken, '-_', '+/') . str_repeat('=', $padding),
            true
        );

        return is_string($decoded) && strlen($decoded) === self::TOKEN_BYTES
            ? $decoded
            : null;
    }

    private static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken, true);
    }
}
