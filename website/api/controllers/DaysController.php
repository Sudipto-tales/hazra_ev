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
 * Replaces home(), activity() and employeeDay(). `status`, `movement` and
 * `locationHealth` are always present — they are the payload's reason for
 * existing.
 */
final class DaysController extends V1Controller
{
    public function show(): never
    {
        Envelope::noStore();

        $subject = Ctx::subject((string) $this->param('subject'), allowTeam: false);
        $employeeId = $subject['id'];
        $date = $this->dateParam();

        $live = Engine::liveStatus($employeeId, $date);

        $payload = [
            'date'           => $date,
            'status'         => $live['status'],
            'movement'       => $live['movement'],
            'locationHealth' => $live['locationHealth'],
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
                    "SELECT * FROM activity_events WHERE employee_id = ? AND work_date = ?
                      ORDER BY occurred_at, rowid",
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
