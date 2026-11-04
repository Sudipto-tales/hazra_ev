<?php

require_once __DIR__ . '/../support/V1Controller.php';

/**
 * The write path, and the highest-volume surface in the system.
 *
 * Stops and visits are derived server-side from the fix chain; the client never
 * posts them, because posting them would let two devices disagree about what a
 * stop is. Sessions carry a clientId because they are opened offline — a
 * repeated open with the same clientId returns the existing session, which is
 * what makes "Start Day" safe to retry on a dead network.
 */
final class TrackingController extends V1Controller
{
    /** POST /tracking/watch — §3.11. A list, deliberately: one map, one call. */
    private const WATCH_MAX_IDS = 200;
    private const WATCH_TTL_DEFAULT = 120;
    private const WATCH_TTL_MIN = 30;
    private const WATCH_TTL_MAX = 600;


    /** POST /tracking/sessions — startTime comes from the first fix, not the tap. */
    public function openSession(): never
    {
        $this->requireEmployee();

        $clientId = (string) ($this->input('clientId') ?? '');

        if ($clientId === '') {
            Envelope::invalid('clientId is required so an offline retry is safe', 'clientId');
        }

        $existing = db_fetch_one(
            "SELECT * FROM work_sessions WHERE employee_id = ? AND client_id = ?",
            [Ctx::id(), $clientId],
        );

        if ($existing) {
            Envelope::ok(Present::session($existing));
        }

        // Checked after the clientId replay, not before: a retry of a session
        // opened while the day was still open must keep succeeding. Only a
        // genuinely new session is refused.
        if (Engine::dayLock(Ctx::id(), Ctx::today())['state'] !== 'open') {
            Envelope::conflict('DAY_CLOSED', 'Today is closed. An admin has to reopen it before you can work it.');
        }

        $fix = $this->input('fix');

        if (!is_array($fix) || !isset($fix['latitude'], $fix['longitude'])) {
            Envelope::invalid('fix with latitude and longitude is required — a session starts at a GPS fix', 'fix');
        }

        $startedAt = Wire::ts((string) ($this->input('startedAt') ?? $fix['recordedAt'] ?? '')) ?? Wire::now();
        $workDate = Ctx::today();

        $seq = 1 + (int) (db_fetch_one(
            "SELECT COALESCE(MAX(seq), 0) AS s FROM work_sessions WHERE employee_id = ? AND work_date = ?",
            [Ctx::id(), $workDate],
        )['s'] ?? 0);

        $id = Uuid::v4();

        db_execute(
            "INSERT INTO work_sessions
                (id, employee_id, client_id, work_date, seq, started_at, start_lat, start_lng)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, Ctx::id(), $clientId, $workDate, $seq, $startedAt, (float) $fix['latitude'], (float) $fix['longitude']],
        );

        $session = db_fetch_one("SELECT * FROM work_sessions WHERE id = ?", [$id]);

        // The opening fix is a fix like any other and belongs in the chain.
        Engine::ingest($session, [$fix + ['recordedAt' => $startedAt]]);

        Envelope::created(Present::session(db_fetch_one("SELECT * FROM work_sessions WHERE id = ?", [$id])));
    }

    /** PATCH /tracking/sessions/{id} — reason pause|end; both close the session. */
    public function closeSession(): never
    {
        $this->requireEmployee();

        $session = db_fetch_one(
            "SELECT * FROM work_sessions WHERE id = ? AND employee_id = ?",
            [(string) $this->param('id'), Ctx::id()],
        );

        if (!$session) {
            Envelope::notFound('SESSION_NOT_FOUND', 'No such session for this employee');
        }

        $reason = (string) ($this->input('reason') ?? 'end');

        if (!in_array($reason, ['pause', 'end'], true)) {
            Envelope::invalid('reason must be pause or end', 'reason');
        }

        $fix = $this->input('fix');

        if (is_array($fix) && isset($fix['latitude'], $fix['longitude'])) {
            Engine::ingest($session, [$fix]);
        }

        if ($session['ended_at'] === null) {
            $endedAt = Wire::ts((string) ($this->input('endedAt') ?? '')) ?? Wire::now();

            db_execute("UPDATE work_sessions SET ended_at = ? WHERE id = ?", [$endedAt, $session['id']]);

            // An open stop cannot outlive its session.
            db_execute(
                "UPDATE stop_records SET departure = ? WHERE session_id = ? AND departure IS NULL",
                [$endedAt, $session['id']],
            );
            db_execute(
                "UPDATE company_visits SET departure = ?, status = 'completed'
                  WHERE session_id = ? AND departure IS NULL",
                [$endedAt, $session['id']],
            );
        }

        Engine::recompute(Ctx::id(), $session['work_date']);

        Envelope::ok(Present::session(db_fetch_one("SELECT * FROM work_sessions WHERE id = ?", [$session['id']])));
    }

    /**
     * The only batched endpoint. Accepts up to config.syncBatchSize fixes.
     *
     * `fixes` may be objects, or the flat [lat, lng, t, acc, spd, clientId]
     * tuples the route payload uses — the tuple form carries the device id in
     * the sixth slot, because the accepted/rejected response has to name them.
     */
    public function locations(): never
    {
        $this->requireEmployee();

        $sessionId = (string) ($this->input('sessionId') ?? '');

        $session = db_fetch_one(
            "SELECT * FROM work_sessions WHERE id = ? AND employee_id = ?",
            [$sessionId, Ctx::id()],
        );

        if (!$session) {
            Envelope::notFound('SESSION_NOT_FOUND', 'No such session for this employee');
        }

        $fixes = $this->input('fixes', []);

        if (!is_array($fixes) || !$fixes) {
            Envelope::invalid('fixes must be a non-empty array', 'fixes');
        }

        $batchSize = (int) Ctx::config()['sync_batch_size'];

        if (count($fixes) > $batchSize) {
            Envelope::invalid("At most {$batchSize} fixes per request — see config.syncBatchSize", 'fixes');
        }

        $result = Engine::ingest($session, array_map([$this, 'normaliseFix'], $fixes));

        // `data` stays byte-identical — {accepted, rejected}, the two lists the
        // device walks to clear its queue. `liveUntil` is a statement about the
        // DEVICE, not about this batch, so it rides in `meta` beside sessionId,
        // which is a statement exactly like it (§3.11). Absent and null mean
        // the same thing: nobody is watching.
        Envelope::ok($result, [
            'sessionId' => $session['id'],
            'liveUntil' => $this->liveUntil(Ctx::id()),
        ]);
    }

    /**
     * POST /tracking/watch — admin only. Makes watched devices drain faster.
     *
     * `employeeIds` is a list because opening a map of eight people is one call,
     * not eight. Unknown ids come back in `rejected` rather than failing the
     * call: one stale id must not stop a map of eight from going live. There is
     * no unwatch — closing the map stops re-posting and the TTL lapses, because
     * an explicit unwatch would have to be delivered to the device, which is
     * precisely the capability this design does not have.
     *
     * A watch changes how often a device talks and nothing else: not what is
     * stored, not what is derived, not what any endpoint returns about a fix.
     */
    public function watch(): never
    {
        $this->requireAdmin();

        $ids = $this->input('employeeIds');

        if (!is_array($ids) || !array_is_list($ids) || !$ids) {
            Envelope::invalid('employeeIds must be a non-empty list of employee ids', 'employeeIds');
        }

        if (count($ids) > self::WATCH_MAX_IDS) {
            Envelope::invalid(
                'At most ' . self::WATCH_MAX_IDS . ' employeeIds per request — the roster cap',
                'employeeIds',
            );
        }

        $ttl = $this->watchTtl();
        $now = Wire::now();

        // Absolute ISO-8601 UTC, never a duration: the device compares it to its
        // own clock minutes after it was issued, and a relative value would be
        // wrong by exactly the latency this mechanism exists to fix.
        $until = gmdate(Wire::TS, time() + $ttl);

        $wanted = [];
        $rejected = [];

        foreach ($ids as $raw) {
            $id = is_string($raw) || is_int($raw) ? (string) $raw : '';

            if ($id === '') {
                $rejected[] = ['employeeId' => '', 'reason' => 'NOT_FOUND'];
                continue;
            }

            $wanted[$id] = true;         // keys dedupe a repeated id
        }

        $users = [];
        $existing = [];

        if ($wanted) {
            [$userClause, $userParams] = $this->inClause('id', array_keys($wanted));

            foreach (db_fetch_all(
                "SELECT id, org_id, role, active, account_status FROM users WHERE {$userClause}",
                $userParams,
            ) as $row) {
                $users[$row['id']] = $row;
            }

            [$watchClause, $watchParams] = $this->inClause('employee_id', array_keys($wanted));

            foreach (db_fetch_all(
                "SELECT employee_id, watching_until FROM tracking_watches WHERE {$watchClause}",
                $watchParams,
            ) as $row) {
                $existing[$row['employee_id']] = (string) $row['watching_until'];
            }
        }

        $watching = [];

        foreach (array_keys($wanted) as $id) {
            $user = $users[$id] ?? null;
            $reason = match (true) {
                $user === null                        => 'NOT_FOUND',
                $user['org_id'] !== Ctx::orgId()      => 'NOT_IN_ORG',
                $user['role'] !== 'employee'          => 'NOT_FOUND',   // an admin is not a device
                !(int) $user['active']                => 'INACTIVE',
                ($user['account_status'] ?? 'active') !== 'active' => 'INACTIVE',
                default                               => null,
            };

            if ($reason !== null) {
                $rejected[] = ['employeeId' => $id, 'reason' => $reason];
                continue;
            }

            $previous = $existing[$id] ?? null;

            // One row per EMPLOYEE, not per admin (03 §4a), so two admins
            // watching the same person share one deadline. It moves forward
            // only: whoever is still watching keeps the device live and neither
            // admin can cut the other off. The CASE is in SQL so two concurrent
            // watch calls cannot lose one another's deadline.
            db_execute(
                "INSERT INTO tracking_watches (employee_id, watching_until, updated_at, updated_by)
                 VALUES (?, ?, ?, ?)
                 ON CONFLICT (employee_id) DO UPDATE SET
                    watching_until = CASE
                        WHEN excluded.watching_until > tracking_watches.watching_until
                        THEN excluded.watching_until ELSE tracking_watches.watching_until END,
                    updated_at = excluded.updated_at,
                    updated_by = excluded.updated_by",
                [$id, $until, $now, Ctx::id()],
            );

            // What the device will actually see — the forward-only deadline, not
            // necessarily this call's. `extended` says this call moved an
            // already-active watch later.
            $effective = $previous !== null && strcmp($previous, $until) > 0 ? $previous : $until;
            $extended = $previous !== null
                && strcmp($previous, $now) > 0
                && strcmp($until, $previous) > 0;

            $watching[] = [
                'employeeId'    => $id,
                'watchingUntil' => Wire::ts($effective),
                'extended'      => $extended,
            ];
        }

        Envelope::ok(
            [
                'ttlSeconds'   => $ttl,
                'watchedUntil' => Wire::ts($until),
                'watching'     => $watching,
                'rejected'     => $rejected,
            ],
            ['requestedCount' => count($ids), 'watchingCount' => count($watching)],
        );
    }

    /**
     * What turns a TeamMember red on the dashboard while the employee's GPS is
     * off. Cheap, fire-and-forget, no response body.
     */
    public function health(): never
    {
        $this->requireEmployee();

        $health = Wire::unenum((string) ($this->input('locationHealth') ?? 'ok')) ?? 'ok';

        $allowed = ['ok', 'service_disabled', 'permission_denied', 'permission_denied_forever',
                    'background_denied', 'poor_accuracy', 'no_internet'];

        if (!in_array($health, $allowed, true)) {
            Envelope::invalid('Unknown locationHealth value', 'locationHealth');
        }

        $previous = db_fetch_one("SELECT location_health FROM device_health WHERE employee_id = ?", [Ctx::id()]);

        db_execute(
            "INSERT INTO device_health (employee_id, location_health, permission, is_online, reported_at)
             VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (employee_id) DO UPDATE SET
                location_health = excluded.location_health,
                permission = excluded.permission,
                is_online = excluded.is_online,
                reported_at = excluded.reported_at",
            [
                Ctx::id(), $health,
                Wire::text($this->input('permission')),
                Wire::bool($this->input('isOnline', true)) ? 1 : 0,
                Wire::now(),
            ],
        );

        // A newly degraded stack is a timeline event, not just a status flag.
        if ($health !== 'ok' && ($previous['location_health'] ?? 'ok') !== $health) {
            Engine::logAlert(Ctx::id(), Ctx::today(), 'Tracking issue', Wire::enum($health));
        }

        Envelope::noContent();
    }

    // ------------------------------------------------------------- internals

    private function requireEmployee(): void
    {
        if (Ctx::isAdmin()) {
            Envelope::forbidden('Tracking is written by employee devices');
        }
    }

    /**
     * ttlSeconds defaults to 120 and CLAMPS to 30..600 — an admin's map must
     * never fail to open over a slider value. Only a malformed (non-integer)
     * ttlSeconds is a 422; an out-of-range integer is silently clamped and the
     * response's ttlSeconds tells the caller what it actually got (§3.11).
     */
    private function watchTtl(): int
    {
        $raw = $this->input('ttlSeconds');

        if ($raw === null || $raw === '') {
            return self::WATCH_TTL_DEFAULT;
        }

        // A fractional value is not an integer number of seconds; 120.0 is.
        if (!is_numeric($raw) || (float) $raw !== (float) (int) (float) $raw) {
            Envelope::invalid('ttlSeconds must be an integer number of seconds', 'ttlSeconds');
        }

        return max(self::WATCH_TTL_MIN, min(self::WATCH_TTL_MAX, (int) (float) $raw));
    }

    /**
     * The live-watch deadline for this device, or null when nobody is watching.
     *
     * Exactly one primary-key lookup per batch — this is the ingest hot path
     * (03 §4a). A watch is an optimisation, never a reason to fail an upload,
     * so a missing table on a half-applied deploy reads as "not watched"
     * instead of turning every POST /tracking/locations into a 500.
     */
    private function liveUntil(string $employeeId): ?string
    {
        try {
            $row = db_fetch_one(
                "SELECT watching_until FROM tracking_watches WHERE employee_id = ?",
                [$employeeId],
            );
        } catch (PDOException) {
            return null;
        }

        if (!$row) {
            return null;
        }

        return strcmp((string) $row['watching_until'], Wire::now()) > 0
            ? Wire::ts($row['watching_until'])
            : null;
    }

    private function normaliseFix(mixed $fix): array
    {
        if (!is_array($fix)) {
            return [];
        }

        // Object form.
        if (isset($fix['latitude']) || isset($fix['lat'])) {
            return [
                'id'         => $fix['id'] ?? $fix['clientId'] ?? '',
                'latitude'   => $fix['latitude'] ?? $fix['lat'],
                'longitude'  => $fix['longitude'] ?? $fix['lng'],
                'accuracy'   => $fix['accuracy'] ?? 0,
                'speedKmh'   => $fix['speedKmh'] ?? 0,
                // Optional and nullable. Engine::ingest normalises -1/NaN/out
                // of range to NULL — never to 0, which is due north.
                'headingDeg' => $fix['headingDeg'] ?? $fix['heading'] ?? null,
                'recordedAt' => $fix['recordedAt'] ?? null,
            ];
        }

        // Tuple form: [lat, lng, t, acc, spd, clientId, heading].
        //            0    1   2   3    4      5           6  <- 6 is new in v9
        //
        // Slot 6 is a widening this parser tolerates by construction: the
        // count >= 3 gate and the ?? defaults mean a SIX-slot tuple from an
        // older client is still a complete, valid fix meaning exactly what it
        // meant before. No version negotiation, and slot 6 is never required.
        if (array_is_list($fix) && count($fix) >= 3) {
            return [
                'id'         => (string) ($fix[5] ?? ''),
                'latitude'   => $fix[0],
                'longitude'  => $fix[1],
                'accuracy'   => $fix[3] ?? 0,
                'speedKmh'   => $fix[4] ?? 0,
                'headingDeg' => $fix[6] ?? $fix['headingDeg'] ?? null,
                'recordedAt' => is_numeric($fix[2]) ? gmdate(Wire::TS, (int) $fix[2]) : $fix[2],
            ];
        }

        return [];
    }
}
