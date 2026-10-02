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

        $this->cache($to);

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

        $this->cache($to);

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

    /** A window that ends before today can no longer change. */
    private function cache(string $to): void
    {
        if ($to < Ctx::today()) {
            header('Cache-Control: ' . Envelope::IMMUTABLE);
            return;
        }

        Envelope::noStore();
    }
}
