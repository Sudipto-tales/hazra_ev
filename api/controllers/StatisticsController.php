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
            ? $this->team($rows, $subject['ids'], $label)
            : $this->period($rows, $label);

        if ($this->wants('daily')) {
            $data['dailyDistance'] = $this->daily($rows, $from, $to);
        } else {
            $data['dailyDistance'] = [];
        }

        Envelope::noStore();
        Envelope::ok($data, ['from' => $from, 'to' => $to]);
    }

    // ------------------------------------------------------------- internals

    /** Working days exclude weekends, holidays and days with no data. */
    private function period(array $rows, string $label): array
    {
        $totals = $this->tally($rows);

        $workingDays = $totals['present'] + $totals['partial'] + $totals['absent'];
        $attended = $totals['present'] + $totals['partial'];

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
            'presentDays'           => $totals['present'] + $totals['partial'],
            'absentDays'            => $totals['absent'],
            'attendancePercent'     => $workingDays === 0 ? 0.0 : Wire::float($attended * 100 / $workingDays),
        ];
    }

    private function team(array $rows, array $employeeIds, string $label): array
    {
        $totals = $this->tally($rows);

        $workingDays = $totals['present'] + $totals['partial'] + $totals['absent'];
        $attended = $totals['present'] + $totals['partial'];

        // Distinct calendar days in the range that counted as working days —
        // the per-employee denominator, not the summed one.
        $calendarDays = count(array_unique(array_column(
            array_filter($rows, static fn(array $r) => in_array($r['status'], ['present', 'partial', 'absent'], true)),
            'work_date',
        )));

        $data = [
            'rangeLabel'            => $label,
            'perEmployee'           => [],
            'totalDistanceKm'       => Wire::float($totals['distance']),
            'totalVisits'           => $totals['visits'],
            'totalReports'          => $totals['reports'],
            'averageDistanceKm'     => $attended === 0 ? 0.0 : Wire::float($totals['distance'] / $attended),
            'averageWorkedDuration' => $attended === 0 ? 0 : (int) round($totals['worked'] / $attended),
            'attendancePercent'     => $workingDays === 0 ? 0.0 : Wire::float($attended * 100 / $workingDays),
            'workingDays'           => $calendarDays,
        ];

        if (!$this->wants('perEmployee')) {
            return $data;
        }

        $names = [];
        foreach (Users::many("WHERE u.org_id = ? AND u.role = 'employee'", [Ctx::orgId()]) as $employee) {
            $names[$employee['id']] = $employee['name'];
        }

        $byEmployee = [];
        foreach ($rows as $row) {
            $byEmployee[$row['employee_id']][] = $row;
        }

        foreach ($employeeIds as $employeeId) {
            $own = $this->tally($byEmployee[$employeeId] ?? []);
            $ownWorking = $own['present'] + $own['partial'] + $own['absent'];
            $ownAttended = $own['present'] + $own['partial'];

            $data['perEmployee'][] = Present::employeeMetric([
                'employeeId'            => $employeeId,
                'name'                  => $names[$employeeId] ?? '',
                'distanceKm'            => $own['distance'],
                'visits'                => $own['visits'],
                'reports'               => $own['reports'],
                'presentDays'           => $ownAttended,
                'absentDays'            => $own['absent'],
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
}
