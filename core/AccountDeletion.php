<?php

/** Account tombstones preserve the employee ID and all historical foreign keys. */
final class AccountDeletion
{
    public const CONFIRMATION = 'delete account';
    public const DAYS = 30;

    public static function isDeleted(array $user): bool
    {
        return ($user['account_status'] ?? '') === 'deleted' || !empty($user['deleted_at']);
    }

    /** Also enforces deadlines when a scheduled job is late or temporarily unavailable. */
    public static function checkAccess(array $user): void
    {
        $user = self::expireIfDue($user);
        if (self::isDeleted($user)) Envelope::fail('ACCOUNT_DELETED', 'Your account has been deleted. You can no longer sign in.', 410);
    }

    public static function expireIfDue(array $user): array
    {
        if (!empty($user['deletion_due_at']) && $user['deletion_due_at'] <= Wire::now() && !self::isDeleted($user)) {
            $ticket = db_fetch_one('SELECT * FROM account_deletion_requests WHERE employee_id = ?', [$user['id']]);
            if ($ticket && $ticket['status'] === 'pending') self::complete($ticket['id'], $user['org_id']);
            $user = db_fetch_one('SELECT * FROM users WHERE id = ?', [$user['id']]) ?: $user;
        }
        return $user;
    }

    public static function request(array $user, string $reason, ?string $now = null): array
    {
        if ($user['role'] !== 'employee') throw new DomainException('Only employees can request account deletion');
        if (strlen($reason) > 2000) throw new InvalidArgumentException('Use a reason of at most 2000 bytes');
        global $pdo;
        $now ??= Wire::now();
        $pdo->beginTransaction();
        try {
            // Acquire the same employee row lock as approval/expiry before reading.
            db_execute('UPDATE users SET deletion_due_at = deletion_due_at WHERE id = ? AND org_id = ?', [$user['id'], $user['org_id']]);
            $current = db_fetch_one('SELECT * FROM users WHERE id = ? AND org_id = ?', [$user['id'], $user['org_id']]);
            if (!$current || self::isDeleted($current)) throw new DomainException('This account is already deleted');
            $existing = db_fetch_one('SELECT * FROM account_deletion_requests WHERE employee_id = ?', [$user['id']]);
            if ($existing) { $pdo->commit(); return $existing; }
            $profile = db_fetch_one('SELECT employee_code, designation, department FROM employee_profiles WHERE user_id = ?', [$user['id']]) ?: [];
            $snapshot = array_merge(array_intersect_key($current, array_flip(['id', 'name', 'email', 'phone'])), $profile);
            $id = Uuid::v4();
            $deadline = (new DateTimeImmutable($now))->modify('+30 days')->setTimezone(new DateTimeZone('UTC'))->format(Wire::TS);
            db_execute('INSERT INTO account_deletion_requests (id, org_id, employee_id, employee_snapshot, reason, status, requested_at, delete_after) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $user['org_id'], $user['id'], json_encode($snapshot, JSON_THROW_ON_ERROR), trim($reason), 'pending', $now, $deadline]);
            db_execute('UPDATE users SET deletion_due_at = ? WHERE id = ?', [$deadline, $user['id']]);
            $ticket = db_fetch_one('SELECT * FROM account_deletion_requests WHERE id = ?', [$id]);
            $pdo->commit();
            return $ticket;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    /** An actor approves early. With no actor, only an expired ticket may complete. */
    public static function complete(string $id, string $orgId, ?string $actorId = null, ?string $now = null): array
    {
        global $pdo;
        $now ??= Wire::now();
        $pdo->beginTransaction();
        try {
            $ticket = db_fetch_one('SELECT * FROM account_deletion_requests WHERE id = ? AND org_id = ?', [$id, $orgId]);
            if (!$ticket) throw new OutOfBoundsException('Deletion request not found');
            db_execute('UPDATE users SET deletion_due_at = deletion_due_at WHERE id = ?', [$ticket['employee_id']]);
            $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $ticket = db_fetch_one('SELECT * FROM account_deletion_requests WHERE id = ? AND org_id = ?' . $lock, [$id, $orgId]);
            if ($ticket['status'] === 'deleted') { $pdo->commit(); return $ticket; }
            if ($actorId !== null) {
                $admin = db_fetch_one('SELECT role, active, account_status FROM users WHERE id = ? AND org_id = ?', [$actorId, $orgId]);
                if (!$admin || $admin['role'] !== 'admin' || !(int) $admin['active'] || $admin['account_status'] === 'deleted') throw new DomainException('Admin approval required');
            } elseif ($ticket['delete_after'] > $now) {
                throw new DomainException('Deletion deadline has not been reached');
            }
            $source = $ticket['delete_after'] <= $now ? 'automatic' : 'admin';
            $effective = $source === 'automatic' ? $ticket['delete_after'] : $now;
            $employee = $ticket['employee_id'];
            db_execute("UPDATE users SET name = 'Unknown', email = ?, phone = '', avatar_url = '', password_hash = ?, active = 0, account_status = 'deleted', deleted_at = ?, deletion_due_at = NULL, updated_at = ? WHERE id = ? AND org_id = ? AND role = 'employee'",
                ['deleted-' . $employee . '@invalid.local', password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT), $effective, $now, $employee, $orgId]);
            db_execute("UPDATE employee_profiles SET address = '', blood_group = '', banner_url = '' WHERE user_id = ?", [$employee]);
            db_execute('UPDATE refresh_tokens SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL', [$now, $employee]);
            db_execute('UPDATE devices SET push_token = NULL WHERE user_id = ?', [$employee]);
            $sessions = db_fetch_all('SELECT id, work_date, started_at FROM work_sessions WHERE employee_id = ? AND ended_at IS NULL', [$employee]);
            foreach ($sessions as $session) {
                $end = max($session['started_at'], $effective);
                db_execute('UPDATE work_sessions SET ended_at = ? WHERE id = ? AND ended_at IS NULL', [$end, $session['id']]);
                db_execute('UPDATE stop_records SET departure = ? WHERE session_id = ? AND departure IS NULL', [$end, $session['id']]);
                db_execute("UPDATE company_visits SET departure = ?, status = 'completed' WHERE session_id = ? AND departure IS NULL", [$end, $session['id']]);
            }
            Ctx::forOrganization($orgId, static function () use ($sessions, $employee): void {
                // Recalculate hours without re-deriving or removing historic stops/routes.
                foreach (array_unique(array_column($sessions, 'work_date')) as $date) Engine::rollupDay($employee, $date);
            });
            db_execute("UPDATE account_deletion_requests SET status = 'deleted', deleted_at = ?, deleted_by = ?, deletion_source = ? WHERE id = ?", [$effective, $actorId, $source, $id]);
            db_execute('INSERT INTO account_deletion_events (id, request_id, employee_id, actor_id, source, deleted_at) VALUES (?, ?, ?, ?, ?, ?)', [Uuid::v4(), $id, $employee, $actorId, $source, $effective]);
            $ticket = db_fetch_one('SELECT * FROM account_deletion_requests WHERE id = ?', [$id]);
            $pdo->commit();
            return $ticket;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function present(array $ticket): array
    {
        return [
            'id' => $ticket['id'], 'ticketNumber' => 'DEL-' . strtoupper(str_replace('-', '', $ticket['id'])),
            'employeeId' => $ticket['employee_id'], 'employee' => json_decode($ticket['employee_snapshot'], true),
            'reason' => $ticket['reason'], 'status' => $ticket['status'], 'requestedAt' => $ticket['requested_at'],
            'deleteAfter' => $ticket['delete_after'], 'deletedAt' => $ticket['deleted_at'],
            'deletedBy' => $ticket['deleted_by'], 'deletionSource' => $ticket['deletion_source'],
        ];
    }
}
