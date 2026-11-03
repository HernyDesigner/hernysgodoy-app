<?php

final class LoginRateLimiterCliDiagnosticException extends RuntimeException
{
    public function __construct(
        private string $diagnosticStage,
        Throwable $previous
    ) {
        parent::__construct('Login limiter CLI diagnostic failure.', 0, $previous);
    }

    public function getDiagnosticStage(): string
    {
        return $this->diagnosticStage;
    }
}

final class LoginRateLimiter
{
    private const ACCOUNT_SCOPE = 'account';
    private const IP_SCOPE = 'ip';

    private const WINDOW_SECONDS = 900;
    private const RESERVATION_SECONDS = 120;
    private const ACCOUNT_LIMIT = 5;
    private const IP_LIMIT = 20;
    private const ACCOUNT_BLOCK_SECONDS = 900;
    private const IP_BLOCK_SECONDS = 1800;
    private const RETENTION_SECONDS = 3600;
    private const CLEANUP_CHANCE = 100;
    private const CLEANUP_LIMIT = 500;

    private string $diagnosticStage = 'initialization';

    public function __construct(private PDO $db)
    {
    }

    public function reserve(string $email, string $remoteAddress): ?array
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->rejectCliDiagnostic('reserve.validate_email');
            return null;
        }

        if (!filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            $this->rejectCliDiagnostic('reserve.validate_remote_address');
            return null;
        }

        $packedAddress = inet_pton($remoteAddress);

        if ($packedAddress === false) {
            $this->rejectCliDiagnostic('reserve.pack_remote_address');
            return null;
        }

        $this->setDiagnosticStage('reserve.generate_attempt_id');

        try {
            $attemptId = random_bytes(16);
        } catch (Throwable $e) {
            $this->throwCliDiagnostic($e);
            return null;
        }

        $accountHash = hash('sha256', "account\0" . $email, true);
        $ipHash = hash('sha256', "ip\0" . $packedAddress, true);

        $this->setDiagnosticStage('reserve.cleanup');

        if (!$this->cleanupExpiredRows()) {
            $this->rejectCliDiagnostic('reserve.cleanup_returned_false');
            return null;
        }

        $buckets = $this->bucketDefinitions($accountHash, $ipHash);

        try {
            $this->setDiagnosticStage('reserve.transaction_guard');

            if ($this->db->inTransaction()) {
                $this->rejectCliDiagnostic('reserve.transaction_already_active');
                return null;
            }

            $this->setDiagnosticStage('reserve.begin_transaction');
            $this->db->beginTransaction();
            $this->setDiagnosticStage('reserve.database_clock');
            $now = $this->databaseNow();
            $initialExpiresAt = $this->calculateBucketExpiresAt($now, null);

            $this->setDiagnosticStage('reserve.prepare_bucket_insert');
            $insertBucket = $this->db->prepare("
                INSERT INTO login_rate_limits
                (
                    scope,
                    key_hash,
                    generation,
                    revision,
                    failure_count,
                    window_started_at,
                    blocked_until,
                    last_attempt_at,
                    expires_at
                )
                VALUES
                (?, ?, 1, 0, 0, ?, NULL, ?, ?)
                ON DUPLICATE KEY UPDATE
                    key_hash = VALUES(key_hash)
            ");

            foreach ($buckets as $bucket) {
                $this->setDiagnosticStage('reserve.insert_bucket.' . $bucket['scope']);
                $insertBucket->execute([
                    $bucket['scope'],
                    $bucket['key_hash'],
                    $this->formatTimestamp($now),
                    $this->formatTimestamp($now),
                    $this->formatTimestamp($initialExpiresAt),
                ]);
            }

            $this->setDiagnosticStage('reserve.lock_buckets');
            $states = $this->lockBuckets($buckets, $now, true);

            foreach ($buckets as $bucket) {
                $state = $states[$bucket['scope']];

                if ($this->isBlocked($state, $now)) {
                    $this->rollbackQuietly();
                    $this->rejectCliDiagnostic('reserve.blocked.' . $bucket['scope']);
                    return null;
                }

                $this->setDiagnosticStage('reserve.count_active.' . $bucket['scope']);
                $activeReservations = $this->countActiveReservations($bucket, $state, $now);

                if (($state['failure_count'] + $activeReservations) >= $bucket['limit']) {
                    $this->rollbackQuietly();
                    $this->rejectCliDiagnostic('reserve.limit_reached.' . $bucket['scope']);
                    return null;
                }
            }

            $reservationExpiresAt = $this->calculateReservationExpiresAt($states, $now);

            foreach ($buckets as $bucket) {
                $state = $states[$bucket['scope']];
                $state['last_attempt_at'] = $now;
                $state['expires_at'] = $this->calculateBucketExpiresAt(
                    $state['window_started_at'],
                    $state['blocked_until']
                );
                $this->setDiagnosticStage('reserve.write_bucket.' . $bucket['scope']);
                $this->writeBucket($bucket, $state);
            }

            $this->setDiagnosticStage('reserve.prepare_reservation_insert');
            $insertReservation = $this->db->prepare("
                INSERT INTO login_attempt_reservations
                (
                    attempt_id,
                    account_key_hash,
                    ip_key_hash,
                    account_generation,
                    ip_generation,
                    account_revision,
                    ip_revision,
                    reserved_at,
                    expires_at,
                    outcome
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $this->setDiagnosticStage('reserve.insert_reservation');
            $insertReservation->execute([
                $attemptId,
                $accountHash,
                $ipHash,
                $states[self::ACCOUNT_SCOPE]['generation'],
                $states[self::IP_SCOPE]['generation'],
                $states[self::ACCOUNT_SCOPE]['revision'],
                $states[self::IP_SCOPE]['revision'],
                $this->formatTimestamp($now),
                $this->formatTimestamp($reservationExpiresAt),
            ]);

            if ($insertReservation->rowCount() !== 1) {
                $this->setDiagnosticStage('reserve.verify_reservation_insert');
                throw new RuntimeException('No se pudo crear la reserva de acceso.');
            }

            $this->setDiagnosticStage('reserve.commit');
            $this->db->commit();

            return ['attempt_id' => $attemptId];
        } catch (Throwable $e) {
            $this->rollbackQuietly();
            $this->throwCliDiagnostic($e);
            return null;
        }
    }

    public function finalize(array $reservation, bool $succeeded): bool
    {
        $attemptId = $reservation['attempt_id'] ?? null;

        if (!is_string($attemptId) || strlen($attemptId) !== 16) {
            $this->rejectCliDiagnostic('finalize.validate_attempt_id');
            return false;
        }

        $this->setDiagnosticStage('finalize.find_reservation');

        try {
            $reservationSnapshot = $this->findReservation($attemptId);
        } catch (Throwable $e) {
            $this->throwCliDiagnostic($e);
            return false;
        }

        if (!$reservationSnapshot || $reservationSnapshot['outcome'] !== 'pending') {
            $this->rejectCliDiagnostic('finalize.reservation_not_pending');
            return false;
        }

        $accountHash = $reservationSnapshot['account_key_hash'] ?? null;
        $ipHash = $reservationSnapshot['ip_key_hash'] ?? null;

        if (
            !is_string($accountHash)
            || strlen($accountHash) !== 32
            || !is_string($ipHash)
            || strlen($ipHash) !== 32
        ) {
            $this->rejectCliDiagnostic('finalize.validate_bucket_hashes');
            return false;
        }

        $buckets = $this->bucketDefinitions($accountHash, $ipHash);

        try {
            $this->setDiagnosticStage('finalize.transaction_guard');

            if ($this->db->inTransaction()) {
                $this->rejectCliDiagnostic('finalize.transaction_already_active');
                return false;
            }

            $this->setDiagnosticStage('finalize.begin_transaction');
            $this->db->beginTransaction();
            $this->setDiagnosticStage('finalize.database_clock');
            $now = $this->databaseNow();
            $this->setDiagnosticStage('finalize.lock_buckets');
            $states = $this->lockBuckets($buckets, $now, false);
            $this->setDiagnosticStage('finalize.lock_reservation');
            $lockedReservation = $this->lockReservation($attemptId);

            if (!$lockedReservation || $lockedReservation['outcome'] !== 'pending') {
                $this->rollbackQuietly();
                $this->rejectCliDiagnostic('finalize.locked_reservation_not_pending');
                return false;
            }

            if (
                !hash_equals((string) $lockedReservation['account_key_hash'], $accountHash)
                || !hash_equals((string) $lockedReservation['ip_key_hash'], $ipHash)
            ) {
                $this->setDiagnosticStage('finalize.verify_bucket_hashes');
                throw new RuntimeException('La reserva de acceso cambió durante la operación.');
            }

            $this->setDiagnosticStage('finalize.validate_expiration_and_generation');
            $reservationExpired = $this->parseTimestamp(
                (string) $lockedReservation['expires_at']
            ) <= $now;
            $generationChanged = (
                (int) $lockedReservation['account_generation']
                    !== $states[self::ACCOUNT_SCOPE]['generation']
                || (int) $lockedReservation['ip_generation']
                    !== $states[self::IP_SCOPE]['generation']
            );

            if ($reservationExpired || $generationChanged) {
                $this->setDiagnosticStage('finalize.mark_expired');
                $this->markReservation($attemptId, 'expired', $now);
                $this->setDiagnosticStage('finalize.commit_expired');
                $this->db->commit();
                $this->rejectCliDiagnostic(
                    $reservationExpired
                        ? 'finalize.reservation_expired'
                        : 'finalize.generation_changed'
                );
                return false;
            }

            if ($succeeded) {
                $accountState = $states[self::ACCOUNT_SCOPE];
                $accountRevisionAtReserve = (int) $lockedReservation['account_revision'];

                if ($accountState['revision'] === $accountRevisionAtReserve) {
                    $accountState['failure_count'] = 0;
                    $accountState['blocked_until'] = null;
                    $accountState['revision'] = $this->incrementSequence($accountState['revision']);
                    $accountState['last_attempt_at'] = $now;
                    $accountState['expires_at'] = $this->calculateBucketExpiresAt(
                        $accountState['window_started_at'],
                        null
                    );
                    $this->setDiagnosticStage('finalize.write_success_account_bucket');
                    $this->writeBucket($buckets[0], $accountState);
                }
            } else {
                foreach ($buckets as $bucket) {
                    $state = $states[$bucket['scope']];
                    $state['failure_count'] = min(65535, $state['failure_count'] + 1);
                    $state['revision'] = $this->incrementSequence($state['revision']);

                    if ($state['failure_count'] >= $bucket['limit']) {
                        $candidateBlock = $now->modify('+' . $bucket['block_seconds'] . ' seconds');

                        if (
                            $state['blocked_until'] === null
                            || $candidateBlock > $state['blocked_until']
                        ) {
                            $state['blocked_until'] = $candidateBlock;
                        }
                    }

                    $state['last_attempt_at'] = $now;
                    $state['expires_at'] = $this->calculateBucketExpiresAt(
                        $state['window_started_at'],
                        $state['blocked_until']
                    );
                    $this->setDiagnosticStage('finalize.write_failed_bucket.' . $bucket['scope']);
                    $this->writeBucket($bucket, $state);
                }
            }

            $this->setDiagnosticStage('finalize.mark_reservation');
            $this->markReservation($attemptId, $succeeded ? 'succeeded' : 'failed', $now);
            $this->setDiagnosticStage('finalize.commit');
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->rollbackQuietly();
            $this->throwCliDiagnostic($e);
            return false;
        }
    }

    private function bucketDefinitions(string $accountHash, string $ipHash): array
    {
        return [
            [
                'scope' => self::ACCOUNT_SCOPE,
                'key_hash' => $accountHash,
                'limit' => self::ACCOUNT_LIMIT,
                'block_seconds' => self::ACCOUNT_BLOCK_SECONDS,
            ],
            [
                'scope' => self::IP_SCOPE,
                'key_hash' => $ipHash,
                'limit' => self::IP_LIMIT,
                'block_seconds' => self::IP_BLOCK_SECONDS,
            ],
        ];
    }

    private function lockBuckets(
        array $buckets,
        DateTimeImmutable $now,
        bool $rollExpiredWindow
    ): array {
        $this->setDiagnosticStage('bucket_lock.prepare');
        $select = $this->db->prepare("
            SELECT
                generation,
                revision,
                failure_count,
                window_started_at,
                blocked_until,
                last_attempt_at,
                expires_at
            FROM login_rate_limits
            WHERE scope = ?
              AND key_hash = ?
            LIMIT 1
            FOR UPDATE
        ");

        $states = [];

        foreach ($buckets as $bucket) {
            $this->setDiagnosticStage('bucket_lock.execute.' . $bucket['scope']);
            $select->execute([$bucket['scope'], $bucket['key_hash']]);
            $this->setDiagnosticStage('bucket_lock.fetch.' . $bucket['scope']);
            $row = $select->fetch();

            if (!$row) {
                $this->setDiagnosticStage('bucket_lock.not_found.' . $bucket['scope']);
                throw new RuntimeException('No se pudo bloquear el contador de acceso.');
            }

            $this->setDiagnosticStage('bucket_lock.hydrate.' . $bucket['scope']);
            $state = $this->hydrateBucket($row);
            $states[$bucket['scope']] = $rollExpiredWindow
                ? $this->rollExpiredWindow($state, $now)
                : $state;
        }

        return $states;
    }

    private function hydrateBucket(array $row): array
    {
        return [
            'generation' => max(1, (int) $row['generation']),
            'revision' => max(0, (int) $row['revision']),
            'failure_count' => max(0, (int) $row['failure_count']),
            'window_started_at' => $this->parseTimestamp((string) $row['window_started_at']),
            'blocked_until' => $row['blocked_until'] !== null
                ? $this->parseTimestamp((string) $row['blocked_until'])
                : null,
            'last_attempt_at' => $this->parseTimestamp((string) $row['last_attempt_at']),
            'expires_at' => $this->parseTimestamp((string) $row['expires_at']),
        ];
    }

    private function rollExpiredWindow(array $state, DateTimeImmutable $now): array
    {
        $windowEnded = $state['window_started_at']
            ->modify('+' . self::WINDOW_SECONDS . ' seconds') <= $now;
        $blockEnded = $state['blocked_until'] !== null && $state['blocked_until'] <= $now;
        $blockActive = $state['blocked_until'] !== null && $state['blocked_until'] > $now;

        if (!$blockActive && ($windowEnded || $blockEnded)) {
            $state['generation'] = $this->incrementSequence($state['generation']);
            $state['revision'] = $this->incrementSequence($state['revision']);
            $state['failure_count'] = 0;
            $state['window_started_at'] = $now;
            $state['blocked_until'] = null;
            $state['last_attempt_at'] = $now;
            $state['expires_at'] = $this->calculateBucketExpiresAt($now, null);
        }

        return $state;
    }

    private function countActiveReservations(
        array $bucket,
        array $state,
        DateTimeImmutable $now
    ): int {
        if ($bucket['scope'] === self::ACCOUNT_SCOPE) {
            $column = 'account_key_hash';
            $generationColumn = 'account_generation';
        } else {
            $column = 'ip_key_hash';
            $generationColumn = 'ip_generation';
        }

        $this->setDiagnosticStage('reservation_count.prepare.' . $bucket['scope']);
        $count = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM login_attempt_reservations
            WHERE {$column} = ?
              AND {$generationColumn} = ?
              AND outcome = 'pending'
              AND expires_at > ?
        ");
        $this->setDiagnosticStage('reservation_count.execute.' . $bucket['scope']);
        $count->execute([
            $bucket['key_hash'],
            $state['generation'],
            $this->formatTimestamp($now),
        ]);
        $this->setDiagnosticStage('reservation_count.fetch.' . $bucket['scope']);
        $row = $count->fetch();

        if (!$row) {
            $this->setDiagnosticStage('reservation_count.no_result.' . $bucket['scope']);
            throw new RuntimeException('No se pudieron contar las reservas de acceso.');
        }

        return max(0, (int) $row['total']);
    }

    private function calculateReservationExpiresAt(
        array $states,
        DateTimeImmutable $now
    ): DateTimeImmutable {
        $expiresAt = $now->modify('+' . self::RESERVATION_SECONDS . ' seconds');

        foreach ($states as $state) {
            $windowEndsAt = $state['window_started_at']
                ->modify('+' . self::WINDOW_SECONDS . ' seconds');

            if ($windowEndsAt < $expiresAt) {
                $expiresAt = $windowEndsAt;
            }
        }

        return $expiresAt;
    }

    private function findReservation(string $attemptId): ?array
    {
        $this->setDiagnosticStage('reservation_find.prepare');
        $select = $this->db->prepare("
            SELECT
                attempt_id,
                account_key_hash,
                ip_key_hash,
                account_generation,
                ip_generation,
                account_revision,
                ip_revision,
                reserved_at,
                expires_at,
                outcome,
                finalized_at
            FROM login_attempt_reservations
            WHERE attempt_id = ?
            LIMIT 1
        ");
        $this->setDiagnosticStage('reservation_find.execute');
        $select->execute([$attemptId]);
        $this->setDiagnosticStage('reservation_find.fetch');
        $row = $select->fetch();

        return $row ?: null;
    }

    private function lockReservation(string $attemptId): ?array
    {
        $this->setDiagnosticStage('reservation_lock.prepare');
        $select = $this->db->prepare("
            SELECT
                attempt_id,
                account_key_hash,
                ip_key_hash,
                account_generation,
                ip_generation,
                account_revision,
                ip_revision,
                reserved_at,
                expires_at,
                outcome,
                finalized_at
            FROM login_attempt_reservations
            WHERE attempt_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $this->setDiagnosticStage('reservation_lock.execute');
        $select->execute([$attemptId]);
        $this->setDiagnosticStage('reservation_lock.fetch');
        $row = $select->fetch();

        return $row ?: null;
    }

    private function markReservation(
        string $attemptId,
        string $outcome,
        DateTimeImmutable $finalizedAt
    ): void {
        if (!in_array($outcome, ['succeeded', 'failed', 'expired'], true)) {
            $this->setDiagnosticStage('reservation_mark.validate_outcome');
            throw new RuntimeException('Resultado de reserva inválido.');
        }

        $this->setDiagnosticStage('reservation_mark.prepare');
        $update = $this->db->prepare("
            UPDATE login_attempt_reservations
            SET
                outcome = ?,
                finalized_at = ?
            WHERE attempt_id = ?
              AND outcome = 'pending'
        ");
        $this->setDiagnosticStage('reservation_mark.execute');
        $update->execute([
            $outcome,
            $this->formatTimestamp($finalizedAt),
            $attemptId,
        ]);

        if ($update->rowCount() !== 1) {
            $this->setDiagnosticStage('reservation_mark.verify_affected_rows');
            throw new RuntimeException('La reserva de acceso ya fue finalizada.');
        }
    }

    private function writeBucket(array $bucket, array $state): void
    {
        $this->setDiagnosticStage('bucket_write.prepare.' . $bucket['scope']);
        $update = $this->db->prepare("
            UPDATE login_rate_limits
            SET
                generation = ?,
                revision = ?,
                failure_count = ?,
                window_started_at = ?,
                blocked_until = ?,
                last_attempt_at = ?,
                expires_at = ?
            WHERE scope = ?
              AND key_hash = ?
        ");

        $this->setDiagnosticStage('bucket_write.execute.' . $bucket['scope']);
        $update->execute([
            $state['generation'],
            $state['revision'],
            $state['failure_count'],
            $this->formatTimestamp($state['window_started_at']),
            $state['blocked_until'] !== null
                ? $this->formatTimestamp($state['blocked_until'])
                : null,
            $this->formatTimestamp($state['last_attempt_at']),
            $this->formatTimestamp($state['expires_at']),
            $bucket['scope'],
            $bucket['key_hash'],
        ]);
    }

    private function isBlocked(array $state, DateTimeImmutable $now): bool
    {
        return $state['blocked_until'] !== null && $state['blocked_until'] > $now;
    }

    private function databaseNow(): DateTimeImmutable
    {
        $this->setDiagnosticStage('database_clock.query');
        $row = $this->db->query('SELECT UTC_TIMESTAMP(6) AS utc_now')->fetch();

        if (!$row || empty($row['utc_now'])) {
            $this->setDiagnosticStage('database_clock.no_result');
            throw new RuntimeException('No se pudo obtener la hora de la base de datos.');
        }

        $this->setDiagnosticStage('database_clock.parse');
        return $this->parseTimestamp((string) $row['utc_now']);
    }

    private function calculateBucketExpiresAt(
        DateTimeImmutable $windowStartedAt,
        ?DateTimeImmutable $blockedUntil
    ): DateTimeImmutable {
        $windowEndsAt = $windowStartedAt->modify('+' . self::WINDOW_SECONDS . ' seconds');
        $retentionStartsAt = $blockedUntil !== null && $blockedUntil > $windowEndsAt
            ? $blockedUntil
            : $windowEndsAt;

        return $retentionStartsAt->modify('+' . self::RETENTION_SECONDS . ' seconds');
    }

    private function incrementSequence(int $value): int
    {
        if ($value >= PHP_INT_MAX) {
            throw new RuntimeException('Se agotó la secuencia del contador de acceso.');
        }

        return $value + 1;
    }

    private function parseTimestamp(string $value): DateTimeImmutable
    {
        $timezone = new DateTimeZone('UTC');
        $timestamp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $value, $timezone);

        if (!$timestamp) {
            $timestamp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);
        }

        if (!$timestamp) {
            throw new RuntimeException('Fecha inválida en el contador de acceso.');
        }

        return $timestamp;
    }

    private function formatTimestamp(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private function cleanupExpiredRows(): bool
    {
        try {
            $this->setDiagnosticStage('cleanup.transaction_guard');

            if ($this->db->inTransaction()) {
                $this->rejectCliDiagnostic('cleanup.transaction_already_active');
                return false;
            }

            $this->setDiagnosticStage('cleanup.random_decision');

            if (random_int(1, self::CLEANUP_CHANCE) !== 1) {
                return true;
            }

            $this->setDiagnosticStage('cleanup.prepare_reservations');
            $cleanupReservations = $this->db->prepare("
                DELETE FROM login_attempt_reservations
                WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 HOUR)
                ORDER BY expires_at ASC
                LIMIT " . self::CLEANUP_LIMIT . "
            ");

            $this->setDiagnosticStage('cleanup.execute_reservations');

            if (!$cleanupReservations->execute()) {
                $this->rejectCliDiagnostic('cleanup.reservations_returned_false');
                return false;
            }

            $this->setDiagnosticStage('cleanup.prepare_buckets');
            $cleanupBuckets = $this->db->prepare("
                DELETE FROM login_rate_limits
                WHERE expires_at < UTC_TIMESTAMP(6)
                  AND window_started_at < DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 15 MINUTE)
                  AND (blocked_until IS NULL OR blocked_until < UTC_TIMESTAMP(6))
                ORDER BY expires_at ASC
                LIMIT " . self::CLEANUP_LIMIT . "
            ");

            $this->setDiagnosticStage('cleanup.execute_buckets');
            $executed = $cleanupBuckets->execute();

            if (!$executed) {
                $this->rejectCliDiagnostic('cleanup.buckets_returned_false');
            }

            return $executed;
        } catch (Throwable $e) {
            $this->throwCliDiagnostic($e);
            return false;
        }
    }

    private function setDiagnosticStage(string $stage): void
    {
        $this->diagnosticStage = $stage;
    }

    private function rejectCliDiagnostic(string $stage): void
    {
        $this->setDiagnosticStage($stage);
        $this->throwCliDiagnostic(new RuntimeException('CLI diagnostic rejection.'));
    }

    private function throwCliDiagnostic(Throwable $exception): void
    {
        if (
            PHP_SAPI !== 'cli'
            || getenv('TURNERO_LOGIN_LIMITER_CLI_DIAGNOSTIC') !== '1'
        ) {
            return;
        }

        if ($exception instanceof LoginRateLimiterCliDiagnosticException) {
            throw $exception;
        }

        throw new LoginRateLimiterCliDiagnosticException(
            $this->diagnosticStage,
            $exception
        );
    }

    private function rollbackQuietly(): void
    {
        if (!$this->db->inTransaction()) {
            return;
        }

        try {
            $this->db->rollBack();
        } catch (Throwable $e) {
        }
    }
}
