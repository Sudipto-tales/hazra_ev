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

        Envelope::ok($result, ['sessionId' => $session['id']]);
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
                'recordedAt' => $fix['recordedAt'] ?? null,
            ];
        }

        // Tuple form: [lat, lng, t, acc, spd, clientId].
        if (array_is_list($fix) && count($fix) >= 3) {
            return [
                'id'         => (string) ($fix[5] ?? ''),
                'latitude'   => $fix[0],
                'longitude'  => $fix[1],
                'accuracy'   => $fix[3] ?? 0,
                'speedKmh'   => $fix[4] ?? 0,
                'recordedAt' => is_numeric($fix[2]) ? gmdate(Wire::TS, (int) $fix[2]) : $fix[2],
            ];
        }

        return [];
    }
}
