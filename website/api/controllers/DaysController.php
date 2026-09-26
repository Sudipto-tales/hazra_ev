<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../support/Users.php';

/**
 * One route serves employee Home and the admin day view.
 *
 *   /days/me?include=sessions,summary,activity,visits,stops,lastFix,sync -> HomeSnapshot
 *   /days/{id}?date=…&include=…,reports,attendance                       -> EmployeeDay
 *   /days/me?date=…&include=activity                                     -> ActivityEvent[]
 *
 * Replaces home(), activity() and employeeDay(). `status`, `movement`,
 * `locationHealth` and `dayState` are always present — they are the payload's
 * reason for existing.
 *
 * The two writes close the day and undo that:
 *
 *   POST /days/me/closeout       -> DayCloseout   (employee, idempotent)
 *   POST /days/{id}/reopen       -> {dayState}    (admin)
 */
final class DaysController extends V1Controller
{
    /** Mirrors DayCloseoutLimits in mobile_app/lib/data/models/day_closeout.dart. */
    private const MAX_DECLARED_KM = 1000;
    private const MAX_DECLARED_VISITS = 50;
    private const FEEDBACK_MAX_CHARS = 500;
    private const REASON_MAX_CHARS = 500;

    /** Stored snake_case; the wire form is the Dart enum name (vehicleIssue). */
    private const FEEDBACK_TAGS = [
        'traffic', 'vehicle_issue', 'customer_unavailable', 'weather', 'no_leads', 'good_day',
    ];

    public function show(): never
    {
        Envelope::noStore();

        $subject = Ctx::subject((string) $this->param('subject'), allowTeam: false);
        $employeeId = $subject['id'];
        $date = $this->dateParam();

        $live = Engine::liveStatus($employeeId, $date);
        $lock = Engine::dayLock($employeeId, $date);

        $payload = [
            'date'           => $date,
            'status'         => $live['status'],
            'movement'       => $live['movement'],
            'locationHealth' => $live['locationHealth'],
            // Unconditional, not an include. Wire.homeSnapshot defaults a
            // missing dayState to `open`, so omitting it would read a locked
            // day as workable — the one thing the lock exists to prevent.
            'dayState'       => $lock['state'],
        ];

        $day = db_fetch_one(
            "SELECT * FROM attendance_days WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $date],
        );

        // The admin mirror carries the employee it is about; the employee
        // payload does not, because the token already said who it is.
        if ($subject['scope'] !== 'self') {
            $payload['employee'] = Present::employee(Users::byId($employeeId));
        }

        if ($this->wants('summary') || $this->includes() === []) {
            $payload['summary'] = Present::daySummary($day ?: null);
        }

        if ($this->wants('attendance')) {
            $payload['attendance'] = $day ? Present::attendance($day) : null;
        }

        // The declaration outlives the lock: after a reopen the day is workable
        // again but the admin card still shows declared-versus-tracked, so this
        // is the latest row regardless of whether it is the active lock.
        if ($this->wants('closeout')) {
            $payload['closeout'] = $lock['row'] ? Present::closeout($lock['row']) : null;
        }

        if ($this->wants('sessions')) {
            $payload['sessions'] = array_map(
                [Present::class, 'session'],
                db_fetch_all(
                    "SELECT * FROM work_sessions WHERE employee_id = ? AND work_date = ? ORDER BY seq",
                    [$employeeId, $date],
                ),
            );
        }

        if ($this->wants('stops')) {
            $payload['stops'] = array_map(
                [Present::class, 'stop'],
                db_fetch_all(
                    "SELECT * FROM stop_records WHERE employee_id = ? AND work_date = ? ORDER BY arrival",
                    [$employeeId, $date],
                ),
            );
        }

        if ($this->wants('visits')) {
            $payload['visits'] = $this->visits($employeeId, $date);
        }

        if ($this->wants('reports')) {
            $payload['reports'] = $this->reports($employeeId, $date);
        }

        if ($this->wants('activity')) {
            $payload['activity'] = array_map(
                [Present::class, 'activity'],
                db_fetch_all(
                    // rowid is SQLite's implicit key; MySQL has no such column
                    // and answers 1054. id is a uuid rather than an insertion
                    // counter, but this only breaks ties between two events at
                    // the same instant, and it has to be stable on both engines.
                    "SELECT * FROM activity_events WHERE employee_id = ? AND work_date = ?
                      ORDER BY occurred_at, id",
                    [$employeeId, $date],
                ),
            );
        }

        if ($this->wants('lastFix')) {
            $payload['lastFix'] = Present::fix($live['lastFix']);
        }

        // `sync` is only meaningful for `me` and is omitted for an admin
        // subject — the admin sees queue depth through RouteTrack.queuedCount.
        if ($this->wants('sync') && $subject['scope'] === 'self') {
            $payload['sync'] = $this->sync($employeeId, $live['lastFix']);
        }

        Envelope::ok($payload);
    }

    /**
     * POST /days/{subject}/closeout — the employee's end-of-day declaration,
     * and the lock it puts on the day.
     *
     * Declared figures are a claim, not a measurement. They are stored beside
     * the GPS ones and never overwrite them: the gap is the signal, and merging
     * the two would destroy it.
     */
    public function closeout(): never
    {
        $subject = Ctx::subject((string) $this->param('subject'), allowTeam: false);

        // An admin cannot declare somebody else's day. Reopening is the only
        // thing the admin side gets to do to a closeout.
        if (Ctx::isAdmin() || $subject['scope'] !== 'self') {
            Envelope::forbidden('An employee closes their own day');
        }

        $employeeId = $subject['id'];
        $date = Ctx::today();
        $body = ApiRequest::body();

        $clientId = (string) ($body['clientId'] ?? '');

        if ($clientId === '') {
            Envelope::invalid('clientId is required so a retry cannot close the day twice', 'clientId');
        }

        // Same clientId, same declaration. A double tap is not a second day.
        $existing = db_fetch_one(
            "SELECT * FROM day_closeouts WHERE employee_id = ? AND client_id = ?",
            [$employeeId, $clientId],
        );

        if ($existing) {
            Envelope::ok(Present::closeout($existing));
        }

        if (Engine::dayLock($employeeId, $date)['state'] !== 'open') {
            Envelope::conflict('DAY_ALREADY_CLOSED', 'Today is already closed. An admin can reopen it.');
        }

        $draft = $this->validateCloseout($body);

        $this->idempotent(
            'days.closeout',
            fn(): array => $this->createCloseout($employeeId, $date, $clientId, $draft),
        );
    }

    /**
     * POST /days/{id}/reopen — admin only, reason mandatory and kept.
     *
     * The employee's declaration is stamped, never deleted. It is history, not
     * a mistake, and the admin day view keeps showing it after the reopen.
     */
    public function reopen(): never
    {
        $this->requireAdmin();

        $employeeId = (string) $this->param('id');
        $employee = Users::byId($employeeId);

        if (!$employee || $employee['org_id'] !== Ctx::orgId() || $employee['role'] !== 'employee') {
            Envelope::notFound('EMPLOYEE_NOT_FOUND', 'No such employee');
        }

        $body = ApiRequest::body();
        $date = Wire::date((string) ($body['date'] ?? ''));

        if ($date === null) {
            Envelope::invalid('date is required and must be yyyy-mm-dd', 'date');
        }

        $reason = trim((string) ($body['reason'] ?? ''));

        if ($reason === '') {
            Envelope::invalid('reason is required — a reopen with no reason cannot be explained later', 'reason');
        }

        if (mb_strlen($reason) > self::REASON_MAX_CHARS) {
            Envelope::invalid('reason must be ' . self::REASON_MAX_CHARS . ' characters or fewer', 'reason');
        }

        $active = db_fetch_one(
            "SELECT * FROM day_closeouts
              WHERE employee_id = ? AND work_date = ? AND reopened_at IS NULL
              ORDER BY submitted_at DESC LIMIT 1",
            [$employeeId, $date],
        );

        if (!$active) {
            Envelope::notFound('DAY_NOT_CLOSED', 'That day is not closed, so there is nothing to reopen');
        }

        $now = Wire::now();

        db_execute("UPDATE day_closeouts SET reopened_at = ? WHERE id = ?", [$now, $active['id']]);

        db_execute(
            "INSERT INTO day_reopen_audit (closeout_id, employee_id, work_date, actor_id, reason, occurred_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$active['id'], $employeeId, $date, Ctx::id(), $reason, $now],
        );

        Envelope::ok(['dayState' => 'open', 'date' => $date]);
    }

    // ------------------------------------------------------- closeout internals

    /** Bounds match DayCloseoutLimits in the app, so both sides agree. */
    private function validateCloseout(array $body): array
    {
        $distance = Wire::float($body['declaredDistanceKm'] ?? 0);

        if ($distance < 0 || $distance > self::MAX_DECLARED_KM) {
            Envelope::invalid(
                'declaredDistanceKm must be between 0 and ' . self::MAX_DECLARED_KM,
                'declaredDistanceKm',
            );
        }

        $visits = Wire::int($body['declaredVisits'] ?? 0);

        if ($visits < 0 || $visits > self::MAX_DECLARED_VISITS) {
            Envelope::invalid(
                'declaredVisits must be between 0 and ' . self::MAX_DECLARED_VISITS,
                'declaredVisits',
            );
        }

        $rating = Wire::int($body['rating'] ?? 0);

        if ($rating < 1 || $rating > 5) {
            Envelope::invalid('rating must be between 1 and 5', 'rating');
        }

        $tags = [];
        foreach (is_array($body['tags'] ?? null) ? $body['tags'] : [] as $tag) {
            $stored = Wire::enumIn(is_string($tag) ? $tag : null, self::FEEDBACK_TAGS);

            if ($stored === null) {
                Envelope::invalid("Unknown feedback tag: " . json_encode($tag), 'tags');
            }

            // Two taps on the same chip is not two reasons.
            if (!in_array($stored, $tags, true)) {
                $tags[] = $stored;
            }
        }

        $feedback = Wire::text($body['feedback'] ?? null);

        if ($feedback !== null && mb_strlen($feedback) > self::FEEDBACK_MAX_CHARS) {
            Envelope::invalid(
                'feedback must be ' . self::FEEDBACK_MAX_CHARS . ' characters or fewer',
                'feedback',
            );
        }

        return [
            'endedAt'  => Wire::ts((string) ($body['endedAt'] ?? '')) ?? Wire::now(),
            'distance' => $distance,
            'visits'   => $visits,
            'rating'   => $rating,
            'tags'     => $tags,
            'feedback' => $feedback === null || $feedback === '' ? null : $feedback,
        ];
    }

    /**
     * Closes what is still open, recomputes, then stores the declaration
     * against the freshly computed measurement — in that order, so the two
     * numbers in the row are the two numbers the employee was shown.
     */
    private function createCloseout(string $employeeId, string $date, string $clientId, array $draft): array
    {
        $endedAt = $draft['endedAt'];

        $open = db_fetch_all(
            "SELECT id FROM work_sessions WHERE employee_id = ? AND work_date = ? AND ended_at IS NULL",
            [$employeeId, $date],
        );

        // Same settle-up as TrackingController::closeSession: an open stop
        // cannot outlive its session, and a session cannot outlive the day.
        foreach ($open as $session) {
            db_execute("UPDATE work_sessions SET ended_at = ? WHERE id = ?", [$endedAt, $session['id']]);
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

        Engine::recompute($employeeId, $date);

        $day = db_fetch_one(
            "SELECT distance_km, companies_visited FROM attendance_days WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $date],
        );

        $id = Uuid::v4();

        db_execute(
            "INSERT INTO day_closeouts
                (id, employee_id, work_date, client_id, state, submitted_at, ended_at,
                 declared_distance_km, declared_visits, measured_distance_km, measured_visits,
                 rating, tags, feedback)
             VALUES (?, ?, ?, ?, 'closed_by_employee', ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $id, $employeeId, $date, $clientId, Wire::now(), $endedAt,
                $draft['distance'], $draft['visits'],
                Wire::float($day['distance_km'] ?? 0), Wire::int($day['companies_visited'] ?? 0),
                $draft['rating'], json_encode($draft['tags']), $draft['feedback'],
            ],
        );

        return Present::closeout(db_fetch_one("SELECT * FROM day_closeouts WHERE id = ?", [$id]));
    }

    /** A visit can carry many reports, so reportIds is a reverse lookup. */
    private function visits(string $employeeId, string $date): array
    {
        $visits = db_fetch_all(
            "SELECT * FROM company_visits WHERE employee_id = ? AND work_date = ? ORDER BY arrival",
            [$employeeId, $date],
        );

        if (!$visits) {
            return [];
        }

        $byVisit = [];
        foreach (db_fetch_all(
            "SELECT id, visit_id FROM visit_reports WHERE employee_id = ? AND work_date = ? AND visit_id IS NOT NULL",
            [$employeeId, $date],
        ) as $report) {
            $byVisit[$report['visit_id']][] = $report['id'];
        }

        return array_map(
            static fn(array $v) => Present::visit($v, $byVisit[$v['id']] ?? []),
            $visits,
        );
    }

    private function reports(string $employeeId, string $date): array
    {
        $reports = db_fetch_all(
            "SELECT * FROM visit_reports WHERE employee_id = ? AND work_date = ? ORDER BY submitted_at",
            [$employeeId, $date],
        );

        if (!$reports) {
            return [];
        }

        $ids = array_column($reports, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));

        $sales = [];
        foreach (db_fetch_all("SELECT * FROM report_sales WHERE report_id IN ({$ph}) ORDER BY position", $ids) as $line) {
            $sales[$line['report_id']][] = $line;
        }

        $images = [];
        foreach (db_fetch_all("SELECT report_id, COUNT(*) AS n FROM report_images WHERE report_id IN ({$ph}) GROUP BY report_id", $ids) as $row) {
            $images[$row['report_id']] = (int) $row['n'];
        }

        return array_map(
            static fn(array $r) => Present::report($r, $sales[$r['id']] ?? [], $images[$r['id']] ?? 0),
            $reports,
        );
    }

    /**
     * SyncSnapshot is device-local queue state and is never persisted, so the
     * server answers with what it can actually see: nothing outstanding on its
     * side, when it last heard from the device, and whether the device says it
     * is online. The client overlays its own queue depth on top.
     */
    private function sync(string $employeeId, ?array $lastFix): array
    {
        $health = db_fetch_one("SELECT is_online FROM device_health WHERE employee_id = ?", [$employeeId]);

        return [
            'queued'       => 0,
            'failed'       => 0,
            'lastSyncedAt' => Wire::ts($lastFix['received_at'] ?? null),
            'isOnline'     => (bool) ($health['is_online'] ?? 1),
        ];
    }
}
