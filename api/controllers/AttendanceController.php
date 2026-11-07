<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../support/Users.php';

/**
 * One route serves both calendars and the team matrix.
 *
 *   ?subject=me&month=2026-08              -> Attendance[]
 *   ?subject=me&from=…&to=…                -> Attendance[] of length <= 1
 *   ?subject={id}&month=…                  -> Attendance[]
 *   ?subject=team&month=…&format=grid      -> TeamAttendanceGrid
 *
 * `format=grid` pivots server-side and returns byDayKey maps keyed yyyymmdd,
 * exactly as the matrix consumes them — the client does no date arithmetic.
 */
final class AttendanceController extends V1Controller
{
    public function index(): never
    {
        $subject = $this->subject();
        [$from, $to] = $this->window();

        if ($subject['scope'] === 'team' || $this->query('format') === 'grid') {
            $this->requireAdmin();
            $this->grid($subject['ids'], $from, $to);
        }

        $rows = db_fetch_all(
            "SELECT * FROM attendance_days
              WHERE employee_id = ? AND work_date BETWEEN ? AND ?
              ORDER BY work_date",
            [$subject['id'], $from, $to],
        );

        // Days with no rollup row still have to appear, or the calendar has
        // holes where a weekend or an unworked day should be.
        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row['work_date']] = $row;
        }

        $data = [];
        foreach ($this->days($from, $to) as $date) {
            $data[] = Present::attendance(
                $byDate[$date] ?? [
                    'employee_id' => $subject['id'],
                    'work_date'   => $date,
                    'status'      => $this->fallbackStatus($date),
                ],
            );
        }

        $this->cache('calendar', $from, $to, [$subject['id']]);

        Envelope::ok($data, ['total' => count($data), 'from' => $from, 'to' => $to]);
    }

    // ------------------------------------------------------------- internals

    private function grid(array $employeeIds, string $from, string $to): never
    {
        $employees = Users::many(
            "WHERE u.org_id = ? AND u.role = 'employee' ORDER BY u.name",
            [Ctx::orgId()],
        );

        [$clause, $params] = $this->inClause('employee_id', $employeeIds);

        $byEmployee = [];
        foreach (db_fetch_all(
            "SELECT employee_id, work_date, status FROM attendance_days
              WHERE {$clause} AND work_date BETWEEN ? AND ?",
            [...$params, $from, $to],
        ) as $row) {
            $byEmployee[$row['employee_id']][$row['work_date']] = $row['status'];
        }

        $days = $this->days($from, $to);

        $rows = [];
        foreach ($employees as $employee) {
            $byDayKey = [];
            $present = $absent = $partial = 0;
            $working = 0;

            foreach ($days as $date) {
                $status = $byEmployee[$employee['id']][$date] ?? $this->fallbackStatus($date);
                $byDayKey[(int) str_replace('-', '', $date)] = Wire::enum($status);

                // Weekends and holidays are not working days, so they must
                // not drag the percentage down.
                if (in_array($status, ['present', 'partial', 'absent'], true)) {
                    $working++;
                    if ($status === 'present') {
                        $present++;
                    } elseif ($status === 'partial') {
                        $partial++;
                    } else {
                        $absent++;
                    }
                }
            }

            $rows[] = [
                'employee'          => Present::employee($employee),
                'byDayKey'          => $byDayKey,
                'presentDays'       => $present,
                'absentDays'        => $absent,
                'partialDays'       => $partial,
                'attendancePercent' => $working === 0 ? 0.0 : Wire::float(($present + $partial) * 100 / $working),
            ];
        }

        $this->cache('grid', $from, $to, $employeeIds);

        Envelope::ok([
            'month' => substr($from, 0, 7),
            'days'  => $days,
            'rows'  => $rows,
        ], ['from' => $from, 'to' => $to, 'total' => count($rows)]);
    }

    /** `month` and `from`/`to` are alternatives; `from`/`to` wins when present. */
    private function window(): array
    {
        if ($this->hasQuery('from') || $this->hasQuery('to')) {
            $from = Wire::date((string) $this->query('from', ''));
            $to   = Wire::date((string) $this->query('to', $from ?? ''));

            if ($from === null || $to === null) {
                Envelope::invalid('from and to must both be yyyy-mm-dd', 'from');
            }

            if ($from > $to) {
                Envelope::invalid('from must not be after to', 'from');
            }

            return [$from, $to];
        }

        return $this->monthParam();
    }

    /** @return string[] */
    private function days(string $from, string $to): array
    {
        $days = [];
        $cursor = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);

        while ($cursor <= $end) {
            $days[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        return $days;
    }

    /**
     * A day with no rollup row is a weekend, a holiday, a day that has not
     * happened yet, or a day nobody worked.
     */
    private function fallbackStatus(string $date): string
    {
        $holiday = db_fetch_one("SELECT date FROM holidays WHERE org_id = ? AND date = ?", [Ctx::orgId(), $date]);

        if ($holiday) {
            return 'holiday';
        }

        if (in_array((int) (new DateTimeImmutable($date))->format('N'), Ctx::weekendDays(), true)) {
            return 'weekend';
        }

        return $date >= Ctx::today() ? 'no_data' : 'absent';
    }

    /**
     * A past window is STABLE UNTIL SOMETHING RE-DERIVES IT — which is not the
     * same as unchangeable, and the difference is a bug this used to carry.
     *
     * It used to send `private, max-age=86400` for any window ending before
     * today, on the premise that "a window that ends before today can no
     * longer change". That premise is false. A past day's status moves
     * whenever its rollup is re-derived: an admin reopens a closeout, a late
     * offline batch arrives with fixes for a day that already closed, a
     * holiday is added to the calendar, `days:close` runs, a tracking
     * threshold is retuned, or `vayu worked:rebuild` corrects historical
     * worked time in a burst (`09-SESSION-DARK-TIME-PLAN.md` §7.5). Under
     * max-age the client kept showing the old verdict for up to 24 hours with
     * no way to find out, and the comment was the reason somebody would
     * reintroduce it.
     *
     * So a past window now gets a VALIDATOR instead of a lifetime:
     * `private, no-cache` plus an ETag over the rollup rows the window
     * actually covers. The body is still cached — `no-cache` means
     * "revalidate", not "do not store" — but every read checks, and a
     * revalidation of an untouched month is a 304 with no body, which is
     * cheaper than the max-age it replaces was honest. Nothing in either
     * client sends `If-None-Match` by hand; browsers do it themselves from the
     * stored ETag, which is where the 24-hour staleness actually bit.
     *
     * Today's window stays `no-store` exactly as before.
     */
    private function cache(string $shape, string $from, string $to, array $employeeIds): void
    {
        if ($to < Ctx::today()) {
            Envelope::freshness($this->validator($shape, $employeeIds, $from, $to), Envelope::REVALIDATE);
            return;
        }

        Envelope::noStore();
    }

    /**
     * An opaque tag that changes if and only if something the payload is built
     * from has moved.
     *
     * Four inputs, because the payload has four:
     *
     *   1. the covered attendance_days rows — MAX(computed_at) catches every
     *      re-derivation, because every writer of this table restamps it:
     *      rollupDay() does, and so does `worked:rebuild`. The count and the
     *      two aggregates catch a row appearing or disappearing without the
     *      max moving. (If a third writer is ever added that sets `status`
     *      WITHOUT touching computed_at, it must be added here too — that is
     *      the one assumption this tag rests on.)
     *   2. the holidays in the window, because fallbackStatus() reads them and
     *      a day with no rollup row renders from them alone;
     *   3. the org's weekend days, which feed fallbackStatus() for the same
     *      reason and live on the organisations row;
     *   4. the subject and the shape, so one employee's month can never
     *      validate another's and the grid can never validate a calendar.
     *
     * Deliberately NOT a hash of the rendered payload: that would mean
     * building the payload before deciding, which is the work a 304 exists to
     * skip. These are two aggregates over indexed columns.
     */
    private function validator(string $shape, array $employeeIds, string $from, string $to): string
    {
        [$clause, $params] = $this->inClause('employee_id', $employeeIds);

        $rollup = db_fetch_one(
            "SELECT COUNT(*) AS n, MAX(computed_at) AS at,
                    SUM(worked_seconds) AS worked, COUNT(DISTINCT status) AS states
               FROM attendance_days
              WHERE {$clause} AND work_date BETWEEN ? AND ?",
            [...$params, $from, $to],
        ) ?: [];

        $holidays = db_fetch_one(
            "SELECT COUNT(*) AS n, MAX(date) AS at FROM holidays
              WHERE org_id = ? AND date BETWEEN ? AND ?",
            [Ctx::orgId(), $from, $to],
        ) ?: [];

        $parts = [
            $shape, $from, $to,
            implode(',', $employeeIds),
            (string) ($rollup['n'] ?? 0),
            (string) ($rollup['at'] ?? ''),
            (string) ($rollup['worked'] ?? ''),
            (string) ($rollup['states'] ?? ''),
            (string) ($holidays['n'] ?? 0),
            (string) ($holidays['at'] ?? ''),
            (string) (Ctx::org()['weekend_days'] ?? ''),
        ];

        return 'att-' . md5(implode('|', $parts));
    }
}
