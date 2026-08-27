<?php

/**
 * Force-closes a day nobody ended.
 *
 * The employee's End Day is a declaration, and a declaration nobody made cannot
 * be invented. But a day left open forever is worse: the session keeps
 * accruing worked_seconds against a phone in somebody's pocket, `dayState`
 * never leaves `open`, and the roster shows them working at 4am. So at the
 * org's local midnight the day is closed *by the system* and says so.
 *
 *   php vayu days:close                  every org, every unclosed past day
 *   php vayu days:close --date=2026-08-26  one day
 *   php vayu days:close --employee=<id>     one employee
 *   php vayu days:close --dry               show what would close, write nothing
 *
 * Meant for cron, just after local midnight. For Asia/Kolkata on a UTC server:
 *
 *   35 18 * * *  cd /path/to/website && php vayu days:close >> storage/logs/days-close.log 2>&1
 *
 * (18:35 UTC is 00:05 IST. Adjust if the server clock is not UTC — the command
 * itself reads each organisation's own timezone and does not assume IST.)
 *
 * Re-running is safe: client_id is derived from the employee and the date, and
 * day_closeouts has UNIQUE (employee_id, client_id).
 */
class CloseDaysCommand
{
    /**
     * What the auto-submitted declaration says.
     *
     * Rating is a fixed 5 because nobody was there to give one, and the record
     * has to carry something. This is a placeholder, not an opinion — read it
     * together with `state = 'closed_by_system'`, which is the field that says
     * whether a human filled this in. Nothing aggregates rating today; if
     * anything ever averages it, it must exclude system closeouts or the
     * average becomes a measure of how often people forget.
     */
    private const AUTO_RATING = 5;

    private const AUTO_FEEDBACK =
        'Closed automatically at midnight — the day was never ended in the app.';

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

        echo PHP_EOL;
        echo "  \033[1mdays:close\033[0m" . ($dry ? " \033[33m(dry run)\033[0m" : '') . PHP_EOL . PHP_EOL;

        $orgs = db_fetch_all("SELECT id, name, timezone FROM organizations ORDER BY name");

        if (!$orgs) {
            echo "  \033[2mno organisations\033[0m" . PHP_EOL . PHP_EOL;
            return;
        }

        $closed = 0;
        $skipped = 0;

        foreach ($orgs as $org) {
            $zone = $this->zone($org['timezone'] ?? 'UTC');
            $today = (new DateTimeImmutable('now', $zone))->format('Y-m-d');

            // Strictly before the org's *own* today. A day still in progress is
            // not stale, and a server in another timezone must not decide that
            // for them.
            $rows = $this->stale($org['id'], $today, $date, $employee);

            echo "  \033[1m{$org['name']}\033[0m \033[2m{$zone->getName()} · local today {$today}\033[0m" . PHP_EOL;

            if (!$rows) {
                echo "    \033[2mnothing open\033[0m" . PHP_EOL . PHP_EOL;
                continue;
            }

            foreach ($rows as $row) {
                if ($dry) {
                    printf("    \033[33mwould close\033[0m  %s  %s\n", $row['work_date'], $row['employee_id']);
                    $skipped++;
                    continue;
                }

                $this->close($row['employee_id'], $row['work_date'], $org, $zone);
                $closed++;
                printf("    \033[32mclosed\033[0m       %s  %s\n", $row['work_date'], $row['employee_id']);
            }

            echo PHP_EOL;
        }

        echo $dry
            ? "  \033[1m{$skipped}\033[0m would close, nothing written" . PHP_EOL . PHP_EOL
            : "  \033[1m{$closed}\033[0m day(s) closed by the system" . PHP_EOL . PHP_EOL;
    }

    // ------------------------------------------------------------- internals

    private function zone(string $name): DateTimeZone
    {
        try {
            return new DateTimeZone($name);
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * Employee-days that have work on them and no closeout still in force.
     *
     * Driven off work_sessions: a day with no session was never started, so
     * there is nothing to close and no declaration to stand in for. A day whose
     * closeout was reopened and then left open again is stale once more, which
     * the `reopened_at IS NULL` correlation gets right.
     */
    private function stale(string $orgId, string $today, ?string $date, ?string $employee): array
    {
        $sql = "SELECT DISTINCT s.employee_id, s.work_date
                  FROM work_sessions s
                  JOIN users u ON u.id = s.employee_id
                 WHERE u.org_id = ?
                   AND s.work_date < ?
                   AND NOT EXISTS (
                         SELECT 1 FROM day_closeouts c
                          WHERE c.employee_id = s.employee_id
                            AND c.work_date = s.work_date
                            AND c.reopened_at IS NULL
                       )";
        $params = [$orgId, $today];

        if ($date !== null) {
            $sql .= " AND s.work_date = ?";
            $params[] = $date;
        }

        if ($employee !== null) {
            $sql .= " AND s.employee_id = ?";
            $params[] = $employee;
        }

        return db_fetch_all($sql . " ORDER BY s.work_date, s.employee_id", $params);
    }

    /**
     * Settles the day, then files the stand-in declaration.
     *
     * Declared figures are set to the measured ones rather than to zero or to
     * an invented number. The whole point of a closeout is the gap between what
     * was claimed and what was tracked; with nobody claiming anything, the only
     * honest gap is none. A zero would read as "said they did nothing", which is
     * a claim the employee never made.
     */
    private function close(string $employeeId, string $workDate, array $org, DateTimeZone $zone): void
    {
        // Engine::rollupDay and classifyDay read the org through Ctx, which
        // outside a request has no principal to read it from.
        Ctx::impersonate([
            'id'     => $employeeId,
            'org_id' => $org['id'],
            'role'   => 'employee',
        ]);

        // The day ended when the day ended, not when this command ran. A cron
        // that fires late must not extend everyone's worked hours to match.
        $endedAt = (new DateTimeImmutable($workDate . ' 23:59:59', $zone))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(Wire::TS);

        $open = db_fetch_all(
            "SELECT id FROM work_sessions WHERE employee_id = ? AND work_date = ? AND ended_at IS NULL",
            [$employeeId, $workDate],
        );

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

        Engine::recompute($employeeId, $workDate);

        $day = db_fetch_one(
            "SELECT distance_km, companies_visited FROM attendance_days WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        );

        $measuredKm = Wire::float($day['distance_km'] ?? 0);
        $measuredVisits = Wire::int($day['companies_visited'] ?? 0);

        // A day may be closed, reopened by an admin, left open again, and
        // closed by this command a second time. Each close is its own row —
        // day_closeouts is append-only — so the client_id has to differ or the
        // insert collides on UNIQUE (employee_id, client_id). Still
        // deterministic: re-running on an unchanged day computes the same
        // suffix and is rejected, which is the idempotency this relies on.
        $priorCloses = (int) (db_fetch_one(
            "SELECT COUNT(*) AS n FROM day_closeouts WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        )['n'] ?? 0);

        $clientId = $priorCloses === 0
            ? 'auto-' . $workDate
            : 'auto-' . $workDate . '-' . ($priorCloses + 1);

        db_execute(
            "INSERT INTO day_closeouts
                (id, employee_id, work_date, client_id, state, submitted_at, ended_at,
                 declared_distance_km, declared_visits, measured_distance_km, measured_visits,
                 rating, tags, feedback)
             VALUES (?, ?, ?, ?, 'closed_by_system', ?, ?, ?, ?, ?, ?, ?, '[]', ?)",
            [
                Uuid::v4(), $employeeId, $workDate, $clientId,
                Wire::now(), $endedAt,
                $measuredKm, $measuredVisits, $measuredKm, $measuredVisits,
                self::AUTO_RATING, self::AUTO_FEEDBACK,
            ],
        );

        Engine::logAlert(
            $employeeId,
            $workDate,
            'Day closed automatically',
            'You did not end this day in the app, so it was closed at midnight.',
        );
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
