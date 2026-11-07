<?php

/**
 * Re-derives worked_seconds and the attendance verdict on stored past days.
 *
 * WHAT THIS IS FOR
 * ----------------
 * `09-SESSION-DARK-TIME-PLAN.md` Phase 1 made worked time evidence-bounded: a
 * session's hours run from its start to the last moment there is a fix for, so
 * a trailing dark tail no longer earns hours (an interior gap still does).
 * That fix is NOT retroactive — a stored attendance_days row keeps the number
 * it was written with until something re-derives it, which may be never, or
 * may be weeks from now when somebody reopens the day for an unrelated reason.
 * This command does that re-derivation deliberately, in a bounded window, so
 * the correction lands as one reviewable event instead of as a drip of days
 * each of which looks like a fresh bug.
 *
 * WHAT IT DOES NOT TOUCH, AND WHY THAT IS THE POINT
 * -------------------------------------------------
 * It does NOT call Engine::recompute(). recompute() starts with
 * refreshSessions(), which re-derives work_sessions.distance_km from the raw
 * fixes under today's gates — and whether historical DISTANCE should be
 * restated is a separate, still-open decision, because
 * day_closeouts.measured_distance_km is frozen on a declaration an employee
 * signed. day_closeouts never snapshotted worked time, so hours contradict no
 * signature and distance does.
 *
 * So this command writes exactly two derived fields, both read back out of the
 * Engine itself:
 *
 *     attendance_days.worked_seconds  <- Engine::dayWorkedSeconds()
 *     attendance_days.status          <- Engine::classifyDay()
 *     attendance_days.computed_at     <- now (so the ETag over the month moves)
 *
 * and nothing else. work_sessions is never written, in any mode: no
 * distance_km, no incomplete_distance_km, no point_count, no stops, no visits,
 * no route geometry, no attendance_days.distance_km. `tests/worked-rebuild.php`
 * pins that with a byte-for-byte snapshot of work_sessions and attendance_days
 * taken before and after a real run. If that guard is ever deleted, the
 * separation of the two backfill decisions goes with it.
 *
 *   php vayu worked:rebuild --dry                  the last 30 days, write nothing
 *   php vayu worked:rebuild                        the last 30 days, for real
 *   php vayu worked:rebuild --days=365 --dry       a year
 *   php vayu worked:rebuild --from=2020-01-01      all history (matches the SQL)
 *   php vayu worked:rebuild --date=2026-10-12      one day
 *   php vayu worked:rebuild --employee=<id> --dry  one employee
 *   php vayu worked:rebuild --batch=50             smaller transactions
 *
 * Idempotent and resumable by construction: it only writes a day whose stored
 * figure disagrees with the Engine, so a second run finds nothing and an
 * interrupted run is resumed by simply running it again. No cursor file, no
 * state to get stale. Each batch is its own transaction; there is never one
 * transaction over the whole history.
 *
 * CROSS-CHECKING AGAINST tools/blast-radius-worked-seconds.sql
 * ------------------------------------------------------------
 * The SQL predicts, this command does, and the two agreeing is the evidence
 * that the prediction was sound. The summary prints the four figures statement
 * 1 of that file reports, under the same names. They are computed from
 * different code (SQL arithmetic vs the Engine), which is what makes the
 * agreement worth anything — and it also means they CAN disagree. Four ways,
 * all of them stated so a difference is diagnosable rather than mysterious:
 *
 *   1. RANGE. The SQL has no lower bound; this command defaults to 30 days.
 *      For a like-for-like comparison run `--from=2020-01-01` (or any date
 *      before the first work_sessions row).
 *   2. THE TODAY BOUNDARY. The SQL uses CURDATE(), the database server's date;
 *      this command uses each organisation's OWN local today, as days:close
 *      does. For Asia/Kolkata on a UTC server those differ between 18:30 and
 *      24:00 UTC, by exactly one day at the end of the window.
 *   3. STATUS. The SQL only predicts present -> partial. This command takes
 *      status from Engine::classifyDay, which also re-reads holidays and the
 *      org's weekend days — so a holiday added after the fact can move a day's
 *      status for a reason that has nothing to do with worked time. Those are
 *      counted and printed SEPARATELY as "other status changes"; the SQL
 *      predicts zero of them, and a non-zero count is a real difference to
 *      look at, not noise.
 *   4. ORGS WITH NO tracking_configs ROW. The SQL inner-joins tracking_configs
 *      and drops such an org entirely. This command skips it too (printing a
 *      line) rather than letting Ctx::config() materialise the defaults, which
 *      would be a write — and --dry must not write.
 *
 * WHAT IT LOGS
 * ------------
 * One audit_log row per CHANGED day: entity `attendance_day`, entity_id
 * `<employee>:<date>`, action `worked_rebuild`, with before/after holding both
 * fields. actor_id is NULL because no human did it. That answers "why did this
 * day change" months later, and it is deliberately NOT Engine::logAlert: that
 * writes a day_events row, which is the employee's own timeline, and a burst
 * of "your hours were recalculated" rows across months of history would read
 * as an incident on every one of those days. An admin-side correction belongs
 * in the admin-side audit trail.
 */
class WorkedRebuildCommand
{
    /** Days per transaction. Small enough to never hold a long lock. */
    private const BATCH = 200;

    /** Default lookback when neither --from nor --days is given. */
    private const DEFAULT_DAYS = 30;

    private string $baseDir;
    private array $framework;

    public function __construct(string $baseDir, array $framework)
    {
        $this->baseDir = $baseDir;
        $this->framework = $framework;
    }

    public function run(array $args): void
    {
        global $pdo;

        require_once $this->baseDir . '/config/db.php';
        require_once $this->baseDir . '/api/support/Wire.php';
        require_once $this->baseDir . '/api/support/Uuid.php';
        require_once $this->baseDir . '/api/support/Envelope.php';
        require_once $this->baseDir . '/api/support/Ctx.php';
        require_once $this->baseDir . '/api/support/Geo.php';
        require_once $this->baseDir . '/api/support/Polyline.php';
        require_once $this->baseDir . '/api/support/MapMatch.php';
        require_once $this->baseDir . '/api/support/Present.php';
        require_once $this->baseDir . '/api/support/Engine.php';

        if (!isset($pdo)) {
            echo PHP_EOL . "  \033[31mNo PDO connection.\033[0m" . PHP_EOL . PHP_EOL;
            exit(1);
        }

        $dry      = in_array('--dry', $args, true) || in_array('--dry-run', $args, true);
        $date     = $this->option($args, 'date');
        $employee = $this->option($args, 'employee');
        $from     = $this->option($args, 'from');
        $to       = $this->option($args, 'to');
        $days     = $this->option($args, 'days');
        $batchOpt = $this->option($args, 'batch');

        $batch = $batchOpt === null ? self::BATCH : max(1, (int) $batchOpt);

        // Normalised through Wire::date so every comparison below is against a
        // canonical yyyy-mm-dd, whatever the operator typed.
        $date = $this->day($date, 'date');
        $from = $this->day($from, 'from');
        $to   = $this->day($to, 'to');

        if ($date !== null && ($from !== null || $to !== null || $days !== null)) {
            echo PHP_EOL . "  \033[31m--date cannot be combined with --from/--to/--days\033[0m" . PHP_EOL . PHP_EOL;
            exit(1);
        }

        echo PHP_EOL;
        echo "  \033[1mworked:rebuild\033[0m" . ($dry ? " \033[33m(dry run — nothing is written)\033[0m" : '') . PHP_EOL;
        echo "  \033[2mworked_seconds + status only. No distance, no stops, no route.\033[0m" . PHP_EOL . PHP_EOL;

        $orgs = db_fetch_all("SELECT id, name, timezone FROM organizations ORDER BY name");

        if (!$orgs) {
            echo "  \033[2mno organisations\033[0m" . PHP_EOL . PHP_EOL;
            return;
        }

        $total = [
            'examined' => 0,
            'changed'  => 0,
            'seconds'  => 0,   // net stored - rebuilt, the SQL's SUM(removed_seconds)
            'up'       => 0,   // days whose figure went UP (hidden inside a net sum)
            'flips'    => 0,   // present -> partial
            'other'    => 0,   // any other status move
        ];

        foreach ($orgs as $org) {
            $zone  = $this->zone($org['timezone'] ?? 'UTC');
            $today = (new DateTimeImmutable('now', $zone))->format('Y-m-d');

            // Strictly before the org's own today, always. An open session's
            // stored figure is a live snapshot and rollupDay rewrites it on
            // every batch anyway, so today is never this command's business.
            $yesterday = (new DateTimeImmutable($today . ' 00:00:00', $zone))
                ->modify('-1 day')->format('Y-m-d');

            [$windowFrom, $windowTo] = $this->window($date, $from, $to, $days, $yesterday, $zone);

            echo "  \033[1m{$org['name']}\033[0m \033[2m{$zone->getName()} · local today {$today}"
                . " · window {$windowFrom} … {$windowTo}\033[0m" . PHP_EOL;

            if ($windowFrom > $windowTo) {
                echo "    \033[2mempty window\033[0m" . PHP_EOL . PHP_EOL;
                continue;
            }

            // Matches the SQL's inner join: an org with no tracking_configs row
            // has no offline threshold to read, and materialising the defaults
            // (which Ctx::config() would do) is a write.
            $hasConfig = db_fetch_one("SELECT org_id FROM tracking_configs WHERE org_id = ?", [$org['id']]);

            if (!$hasConfig) {
                echo "    \033[33mskipped — no tracking_configs row, so no threshold to read\033[0m"
                    . PHP_EOL . PHP_EOL;
                continue;
            }

            // Anything that escapes mid-batch rolls THAT batch back. The batches
            // already committed stand — that is the point of batching — and a
            // re-run then picks up where it stopped, because a settled day is a
            // no-op. There is never one transaction over the whole history.
            $this->guarded(function () use ($org, $windowFrom, $windowTo, $employee, $dry, $batch, &$total): void {
                $this->organisation($org, $windowFrom, $windowTo, $employee, $dry, $batch, $total);
            });
        }

        $this->summary($total, $dry);
    }

    // ------------------------------------------------------------- internals

    /** A date option, normalised, or a hard exit if it is not a date at all. */
    private function day(?string $value, string $name): ?string
    {
        if ($value === null) {
            return null;
        }

        $parsed = Wire::date($value);

        if ($parsed === null) {
            echo PHP_EOL . "  \033[31m--{$name} must be yyyy-mm-dd\033[0m" . PHP_EOL . PHP_EOL;
            exit(1);
        }

        return $parsed;
    }

    private function zone(string $name): DateTimeZone
    {
        try {
            return new DateTimeZone($name);
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * Resolves the window, clamped so it can never include today or later.
     *
     * --date pins one day. --from/--to bound it explicitly. --days=N counts
     * back N days from yesterday. With none of them, DEFAULT_DAYS applies:
     * bounded by default on purpose, because "all of history" is a decision
     * somebody should have to type.
     *
     * @return array{0: string, 1: string}
     */
    private function window(
        ?string $date,
        ?string $from,
        ?string $to,
        ?string $days,
        string $yesterday,
        DateTimeZone $zone,
    ): array {
        if ($date !== null) {
            return [$date, min($date, $yesterday)];
        }

        $end = $to === null ? $yesterday : min($to, $yesterday);

        if ($from !== null) {
            return [$from, $end];
        }

        $span = $days === null ? self::DEFAULT_DAYS : max(1, (int) $days);

        $start = (new DateTimeImmutable($end . ' 00:00:00', $zone))
            ->modify('-' . ($span - 1) . ' days')->format('Y-m-d');

        return [$start, $end];
    }

    /**
     * One organisation, employee by employee.
     *
     * Grouped by employee because Ctx::impersonate() drops the cached org and
     * config, so impersonating per DAY would re-read both on every row.
     * Impersonation is needed at all because Engine::dayWorkedSeconds reads
     * the org's offline threshold and classifyDay reads its holidays and
     * weekend days through Ctx, and outside a request there is no principal to
     * read them from.
     *
     * @param array<string,int> $total
     */
    private function organisation(
        array $org,
        string $from,
        string $to,
        ?string $employee,
        bool $dry,
        int $batch,
        array &$total,
    ): void {
        $employees = $this->employees($org['id'], $from, $to, $employee);

        if (!$employees) {
            echo "    \033[2mno stored days in range\033[0m" . PHP_EOL . PHP_EOL;
            return;
        }

        $examined = 0;
        $changed  = 0;
        $pending  = 0;
        $open     = false;

        foreach ($employees as $person) {
            Ctx::impersonate([
                'id'     => $person['id'],
                'org_id' => $org['id'],
                'role'   => 'employee',
            ]);

            foreach ($this->storedDays($person['id'], $from, $to) as $row) {
                $examined++;
                $total['examined']++;

                $move = $this->evaluate($person['id'], $row);

                if ($move === null) {
                    continue;
                }

                $changed++;
                $total['changed']++;
                $total['seconds'] += $move['removed'];

                if ($move['removed'] < 0) {
                    $total['up']++;
                }

                if ($move['status_before'] !== $move['status_after']) {
                    if ($move['status_before'] === 'present' && $move['status_after'] === 'partial') {
                        $total['flips']++;
                    } else {
                        $total['other']++;
                    }
                }

                printf(
                    "    %s  %s  %-22s %7.2f h -> %7.2f h  %s%s\n",
                    $dry ? "\033[33mwould change\033[0m" : "\033[32mchanged\033[0m     ",
                    $row['work_date'],
                    $this->trim((string) ($person['name'] ?? $person['id'])),
                    $move['seconds_before'] / 3600,
                    $move['seconds_after'] / 3600,
                    $move['status_before'] === $move['status_after']
                        ? $move['status_before']
                        : "\033[1m{$move['status_before']} -> {$move['status_after']}\033[0m",
                    $move['status_before'] === 'present' && $move['status_after'] === 'partial' ? '  *' : '',
                );

                if ($dry) {
                    continue;
                }

                if (!$open) {
                    $this->begin();
                    $open = true;
                }

                $this->apply($person['id'], $row['work_date'], $move);
                $pending++;

                if ($pending >= $batch) {
                    $this->commit();
                    $open = false;
                    $pending = 0;
                    printf("    \033[2m… %d written so far\033[0m\n", $changed);
                }
            }
        }

        if ($open) {
            $this->commit();
        }

        printf(
            "    \033[2mexamined %d · %s %d\033[0m\n\n",
            $examined,
            $dry ? 'would change' : 'changed',
            $changed,
        );
    }

    /**
     * Employees in the org with at least one stored day in range.
     *
     * @return list<array<string,mixed>>
     */
    private function employees(string $orgId, string $from, string $to, ?string $employee): array
    {
        $sql = "SELECT u.id, u.name
                  FROM users u
                 WHERE u.org_id = ?
                   AND EXISTS (
                         SELECT 1 FROM attendance_days a
                          WHERE a.employee_id = u.id
                            AND a.work_date BETWEEN ? AND ?
                       )";
        $params = [$orgId, $from, $to];

        if ($employee !== null) {
            $sql .= " AND u.id = ?";
            $params[] = $employee;
        }

        return db_fetch_all($sql . " ORDER BY u.name, u.id", $params);
    }

    /**
     * Stored rollup rows in range that have sessions behind them.
     *
     * Driven off attendance_days, so this command can only ever UPDATE a row
     * that already exists — it never inserts a day, and a day with no stored
     * rollup stays unrolled. The EXISTS on work_sessions matches the SQL's
     * inner join: a synthesised absent/weekend/holiday row with no sessions
     * has nothing to re-derive.
     *
     * @return list<array<string,mixed>>
     */
    private function storedDays(string $employeeId, string $from, string $to): array
    {
        return db_fetch_all(
            "SELECT a.work_date, a.worked_seconds, a.status
               FROM attendance_days a
              WHERE a.employee_id = ?
                AND a.work_date BETWEEN ? AND ?
                AND EXISTS (
                      SELECT 1 FROM work_sessions s
                       WHERE s.employee_id = a.employee_id
                         AND s.work_date = a.work_date
                    )
              ORDER BY a.work_date",
            [$employeeId, $from, $to],
        );
    }

    /**
     * What the Engine says this day is, against what is stored.
     *
     * Returns null when they already agree — which is what makes a re-run a
     * no-op and the whole thing resumable. Read-only: this is the entirety of
     * what --dry does.
     *
     * @return array{seconds_before:int, seconds_after:int, removed:int, status_before:string, status_after:string}|null
     */
    private function evaluate(string $employeeId, array $row): ?array
    {
        $workDate = (string) $row['work_date'];

        $sessions = db_fetch_all(
            "SELECT * FROM work_sessions WHERE employee_id = ? AND work_date = ? ORDER BY seq",
            [$employeeId, $workDate],
        );

        // Both figures come straight out of the Engine, so what this prints is
        // by construction what rollupDay() would store for these two fields.
        $after       = Engine::dayWorkedSeconds($employeeId, $workDate, $sessions);
        $statusAfter = Engine::classifyDay($workDate, count($sessions), $after);

        $before       = (int) $row['worked_seconds'];
        $statusBefore = (string) $row['status'];

        if ($after === $before && $statusAfter === $statusBefore) {
            return null;
        }

        return [
            'seconds_before' => $before,
            'seconds_after'  => $after,
            'removed'        => $before - $after,
            'status_before'  => $statusBefore,
            'status_after'   => $statusAfter,
        ];
    }

    /**
     * Writes the two fields and files the audit row.
     *
     * A targeted UPDATE, not rollupDay(): rollupDay rewrites the whole row,
     * including distance_km (re-summed from the sessions) and stop_seconds
     * (which, for a day with an unclosed stop, is measured against the clock
     * and so moves every time it is recomputed). Neither has anything to do
     * with this correction, and this command's entire justification is that it
     * leaves the distance figures exactly as stored.
     */
    private function apply(string $employeeId, string $workDate, array $move): void
    {
        db_execute(
            "UPDATE attendance_days
                SET worked_seconds = ?, status = ?, computed_at = ?
              WHERE employee_id = ? AND work_date = ?",
            [$move['seconds_after'], $move['status_after'], Wire::now(), $employeeId, $workDate],
        );

        // actor_id NULL: no human did this. The column is nullable and carries
        // an FK to users, so a sentinel string would not insert anyway.
        db_execute(
            "INSERT INTO audit_log (org_id, actor_id, entity, entity_id, action, `before`, `after`, occurred_at)
             VALUES (?, NULL, 'attendance_day', ?, 'worked_rebuild', ?, ?, ?)",
            [
                Ctx::orgId(),
                $employeeId . ':' . $workDate,
                json_encode(['worked_seconds' => $move['seconds_before'], 'status' => $move['status_before']]),
                json_encode(['worked_seconds' => $move['seconds_after'], 'status' => $move['status_after']]),
                Wire::now(),
            ],
        );
    }

    /** @param array<string,int> $total */
    private function summary(array $total, bool $dry): void
    {
        $hours = $total['seconds'] / 3600;

        echo "  \033[1m" . str_repeat('─', 68) . "\033[0m" . PHP_EOL;
        echo "  \033[1mcompare these against tools/blast-radius-worked-seconds.sql statement 1\033[0m" . PHP_EOL;
        printf("    days_examined            %d\n", $total['examined']);
        printf("    days_that_move           %d\n", $total['changed']);
        printf("    hours_removed_total      %.1f   \033[2m(net; %d day(s) went UP)\033[0m\n", $hours, $total['up']);
        printf("    days_present_to_partial  %d\n", $total['flips']);
        printf(
            "    other status changes     %d   \033[2m(the SQL predicts 0 — holidays/weekends re-read)\033[0m\n",
            $total['other'],
        );
        echo PHP_EOL;

        echo $dry
            ? "  \033[33mdry run — nothing was written\033[0m" . PHP_EOL
                . "  \033[2mthe SQL has no lower date bound; use --from=2020-01-01 to compare like with like\033[0m"
                . PHP_EOL . PHP_EOL
            : "  \033[32m{$total['changed']} day(s) rewritten\033[0m"
                . " \033[2m(worked_seconds + status only; re-run to confirm it is now a no-op)\033[0m"
                . PHP_EOL . PHP_EOL;
    }

    private function begin(): void
    {
        global $pdo;

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
    }

    private function commit(): void
    {
        global $pdo;

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    }

    /** Never leave a half-written batch holding a transaction open. */
    private function guarded(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
    }

    private function rollback(): void
    {
        global $pdo;

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    private function trim(string $name): string
    {
        return mb_strlen($name) > 22 ? mb_substr($name, 0, 21) . '…' : $name;
    }

    private function option(array $args, string $name): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen($name) + 3);
            }
        }

        return null;
    }
}
