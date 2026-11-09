<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../support/Users.php';

/**
 * One route, two scopes.
 *
 *   ?subject=me|{id}                   -> PeriodStatistics
 *   ?subject=team&include=perEmployee  -> TeamStatistics
 *
 * Field names are the same in both, which is already true of the models.
 * `include=daily` adds the dailyDistance series; the chart is the only caller
 * that needs it, so it is opt-in.
 *
 * Everything here aggregates attendance_days. No statistics query ever touches
 * a location fix.
 */
final class StatisticsController extends V1Controller
{
    public function index(): never
    {
        $subject = $this->subject();
        [$from, $to, $label] = $this->rangeParam();

        [$clause, $params] = $this->inClause('a.employee_id', $subject['ids']);

        $rows = db_fetch_all(
            "SELECT a.* FROM attendance_days a
              WHERE {$clause} AND a.work_date BETWEEN ? AND ?
              ORDER BY a.work_date",
            [...$params, $from, $to],
        );

        $data = $subject['scope'] === 'team'
            ? $this->team($rows, $subject['ids'], $label, $from, $to)
            : $this->period($rows, $label, $from, $to, $subject['ids'][0] ?? null);

        if ($this->wants('daily')) {
            $data['dailyDistance'] = $this->daily($rows, $from, $to);
        } else {
            $data['dailyDistance'] = [];
        }

        Envelope::noStore();
        Envelope::ok($data, ['from' => $from, 'to' => $to]);
    }

    // ------------------------------------------------------------- internals

    /**
     * Working days are the organisation's working calendar, NOT the set of
     * stored rows. No `attendance_days` row is written for a day nobody worked
     * (the rollup only runs when a session exists), so counting rows made every
     * denominator equal its numerator and every employee read 100% present.
     * The denominator is instead the weekdays in range that are neither a
     * weekend nor a holiday, up to today, and from this employee's joining date
     * — so a month with twenty working days and three worked reads 15%, not
     * 100%.
     */
    private function period(array $rows, string $label, string $from, string $to, ?string $employeeId): array
    {
        $totals = $this->tally($rows);

        $joinedOn = $employeeId === null ? null : ($this->joinedOn($employeeId));
        $workingDays = $this->calendarWorkingDays($from, $to, $joinedOn);
        $attended = $totals['present'] + $totals['partial'];
        $absentDays = max(0, $workingDays - $attended);

        return [
            'rangeLabel'            => $label,
            'averageJoiningTime'    => $this->averageJoiningTime($rows),
            'averageWorkedDuration' => $attended === 0 ? 0 : (int) round($totals['worked'] / $attended),
            'averageDistanceKm'     => $attended === 0 ? 0.0 : Wire::float($totals['distance'] / $attended),
            'totalDistanceKm'       => Wire::float($totals['distance']),
            'averageVisitsPerDay'   => $attended === 0 ? 0.0 : Wire::float($totals['visits'] / $attended),
            'totalVisits'           => $totals['visits'],
            'totalReports'          => $totals['reports'],
            'averageReportsPerDay'  => $attended === 0 ? 0.0 : Wire::float($totals['reports'] / $attended),
            'workingDays'           => $workingDays,
            'presentDays'           => $attended,
            'absentDays'            => $absentDays,
            'attendancePercent'     => $workingDays === 0 ? 0.0 : Wire::float($attended * 100 / $workingDays),
        ];
    }

    private function team(array $rows, array $employeeIds, string $label, string $from, string $to): array
    {
        $totals = $this->tally($rows);

        $attended = $totals['present'] + $totals['partial'];

        // The working calendar, same as period() — weekdays in range that are
        // neither weekend nor holiday, up to today — not the stored rows (a day
        // nobody worked has no row). This is the per-employee denominator; the
        // team percentage divides the attended employee-days by this times the
        // roster size.
        //
        // KNOWN APPROXIMATION: this does not subtract per-employee joining
        // dates from the team denominator, so a month with recent hires reads a
        // little low. period() (one employee) does honour joined_on; doing it
        // per head here would mean one calendar pass per employee. Flagged for
        // docs-agent rather than silently fudged.
        $calendarDays = $this->calendarWorkingDays($from, $to, null);
        $headcount = count($employeeIds);
        $possibleEmployeeDays = $calendarDays * $headcount;

        $data = [
            'rangeLabel'            => $label,
            'perEmployee'           => [],
            'totalDistanceKm'       => Wire::float($totals['distance']),
            'totalVisits'           => $totals['visits'],
            'totalReports'          => $totals['reports'],
            'averageDistanceKm'     => $attended === 0 ? 0.0 : Wire::float($totals['distance'] / $attended),
            'averageWorkedDuration' => $attended === 0 ? 0 : (int) round($totals['worked'] / $attended),
            'attendancePercent'     => $possibleEmployeeDays === 0 ? 0.0 : Wire::float($attended * 100 / $possibleEmployeeDays),
            'workingDays'           => $calendarDays,
        ];

        if (!$this->wants('perEmployee')) {
            return $data;
        }

        $names = [];
        $joined = [];
        foreach (Users::many("WHERE u.org_id = ? AND u.role = 'employee'", [Ctx::orgId()]) as $employee) {
            $names[$employee['id']] = $employee['name'];
            $joined[$employee['id']] = $employee['joined_on'] ?? null;
        }

        $byEmployee = [];
        foreach ($rows as $row) {
            $byEmployee[$row['employee_id']][] = $row;
        }

        foreach ($employeeIds as $employeeId) {
            $own = $this->tally($byEmployee[$employeeId] ?? []);
            $ownWorking = $this->calendarWorkingDays($from, $to, $joined[$employeeId] ?? null);
            $ownAttended = $own['present'] + $own['partial'];
            $ownAbsent = max(0, $ownWorking - $ownAttended);

            $data['perEmployee'][] = Present::employeeMetric([
                'employeeId'            => $employeeId,
                'name'                  => $names[$employeeId] ?? '',
                'distanceKm'            => $own['distance'],
                'visits'                => $own['visits'],
                'reports'               => $own['reports'],
                'presentDays'           => $ownAttended,
                'absentDays'            => $ownAbsent,
                'attendancePercent'     => $ownWorking === 0 ? 0.0 : $ownAttended * 100 / $ownWorking,
                'averageWorkedDuration' => $ownAttended === 0 ? 0 : (int) round($own['worked'] / $ownAttended),
            ]);
        }

        return $data;
    }

    private function tally(array $rows): array
    {
        $out = [
            'worked' => 0, 'distance' => 0.0, 'visits' => 0, 'reports' => 0,
            'present' => 0, 'partial' => 0, 'absent' => 0,
        ];

        foreach ($rows as $row) {
            $out['worked']   += (int) $row['worked_seconds'];
            $out['distance'] += (float) $row['distance_km'];
            $out['visits']   += (int) $row['companies_visited'];
            $out['reports']  += (int) $row['reports_submitted'];

            if (isset($out[$row['status']])) {
                $out[$row['status']]++;
            }
        }

        return $out;
    }

    /**
     * A time-of-day average, carried as a datetime whose date part the client
     * ignores. Averaged over the days somebody actually started.
     */
    private function averageJoiningTime(array $rows): string
    {
        $seconds = 0;
        $count = 0;

        foreach ($rows as $row) {
            if ($row['joining_time'] === null) {
                continue;
            }

            $local = (new DateTimeImmutable($row['joining_time']))->setTimezone(Ctx::timezone());
            $seconds += (int) $local->format('H') * 3600 + (int) $local->format('i') * 60 + (int) $local->format('s');
            $count++;
        }

        $average = $count === 0 ? 0 : (int) round($seconds / $count);

        return (new DateTimeImmutable(Ctx::today(), Ctx::timezone()))
            ->modify("+{$average} seconds")
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(Wire::TS);
    }

    /** Oldest first, with a zero row for every day in the range. */
    private function daily(array $rows, string $from, string $to): array
    {
        $byDate = [];
        foreach ($rows as $row) {
            $date = $row['work_date'];
            $byDate[$date]['km'] = ($byDate[$date]['km'] ?? 0) + (float) $row['distance_km'];
            $byDate[$date]['visits'] = ($byDate[$date]['visits'] ?? 0) + (int) $row['companies_visited'];
        }

        $out = [];
        $cursor = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);

        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $out[] = Present::dailyMetric($date, $byDate[$date]['km'] ?? 0.0, $byDate[$date]['visits'] ?? 0);
            $cursor = $cursor->modify('+1 day');
        }

        return $out;
    }

    /** This employee's joining date, or null when the profile has none. */
    private function joinedOn(string $employeeId): ?string
    {
        $row = db_fetch_one(
            "SELECT joined_on FROM employee_profiles WHERE user_id = ?",
            [$employeeId],
        );

        return $row['joined_on'] ?? null;
    }

    /**
     * Working days in [from, to]: weekdays that are neither a weekend (per the
     * org's `weekend_days`) nor a holiday, never counting past today, and never
     * before [joinedOn] when given. This is the attendance denominator — it
     * must not depend on whether an attendance_days row was ever written, which
     * is the whole point (a day nobody worked has no row).
     */
    private function calendarWorkingDays(string $from, string $to, ?string $joinedOn): int
    {
        $start = $from;
        if ($joinedOn !== null && $joinedOn !== '' && strcmp($joinedOn, $start) > 0) {
            $start = substr($joinedOn, 0, 10);
        }

        $today = Ctx::today();
        $end = strcmp($to, $today) > 0 ? $today : $to;

        if (strcmp($start, $end) > 0) {
            return 0;
        }

        // One rule for "not a working day", shared with classifyDay and the
        // openSession guard: Engine::isNonWorkingDay is weekend- AND holiday-aware
        // including yearly holidays, so a recurring holiday drops out of the
        // denominator on every year it falls in, not only its anchor year.
        $orgId = Ctx::orgId();
        $count = 0;

        $cursor = new DateTimeImmutable($start);
        $last = new DateTimeImmutable($end);

        while ($cursor <= $last) {
            if (!Engine::isNonWorkingDay($orgId, $cursor->format('Y-m-d'))) {
                $count++;
            }

            $cursor = $cursor->modify('+1 day');
        }

        return $count;
    }
}
