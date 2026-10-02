<?php

/**
 * Backfills the road-matched geometry cache.
 *
 * `GET /routes` matches lazily, so this command is never required — it exists
 * so the first admin to open a day is not the one who pays the matcher round
 * trip, and so a profile or engine switch can be rolled through history off the
 * request path instead of a day at a time.
 *
 *   php vayu match                       last 7 days, every employee
 *   php vayu match --days=30             wider window
 *   php vayu match --date=2026-08-19     one day
 *   php vayu match --employee=<id>       one employee
 *   php vayu match --force               re-match days already cached
 */
class MatchCommand
{
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
        require_once $this->baseDir . '/api/support/Geo.php';
        require_once $this->baseDir . '/api/support/Polyline.php';
        require_once $this->baseDir . '/api/support/MapMatch.php';

        if (!isset($pdo)) {
            echo PHP_EOL . "  \033[31mNo PDO connection.\033[0m" . PHP_EOL . PHP_EOL;
            exit(1);
        }

        $force = in_array('--force', $args, true);
        $days  = (int) ($this->option($args, 'days') ?? 7);
        $date  = $this->option($args, 'date');
        $employee = $this->option($args, 'employee');

        echo PHP_EOL;

        if (!MapMatch::enabled()) {
            echo "  \033[33mMATCH_ENABLED is false\033[0m — nothing to do." . PHP_EOL . PHP_EOL;
            exit(0);
        }

        echo "  \033[1mMatching\033[0m \033[2m" . MapMatch::engine() . ' · ' . MapMatch::profile() . "\033[0m" . PHP_EOL;

        // Driven off day_routes, not location_fixes: a day with no drawn route
        // has nothing to match, and this keeps the scan off the fix table.
        $sql = "SELECT employee_id, work_date FROM day_routes WHERE point_count >= 2";
        $params = [];

        if ($date !== null) {
            $sql .= " AND work_date = ?";
            $params[] = $date;
        } else {
            // Today is excluded: its trace is still being written, so a cached
            // match would be stale by the next batch.
            $sql .= " AND work_date < ? AND work_date >= ?";
            $params[] = date('Y-m-d');
            $params[] = date('Y-m-d', strtotime("-{$days} days"));
        }

        if ($employee !== null) {
            $sql .= " AND employee_id = ?";
            $params[] = $employee;
        }

        $rows = db_fetch_all($sql . " ORDER BY work_date DESC, employee_id", $params);

        if (!$rows) {
            echo "  \033[2mno days in range\033[0m" . PHP_EOL . PHP_EOL;
            return;
        }

        $ok = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $result = MapMatch::forDay($row['employee_id'], $row['work_date'], $force);

            if ($result === null) {
                $failed++;
                printf(
                    "  \033[31mfailed\033[0m   %s  %s\n",
                    $row['work_date'],
                    $row['employee_id'],
                );
                continue;
            }

            $ok++;
            printf(
                "  \033[32m%-8s\033[0m %s  %s  \033[2mconf %.2f · %.1f km\033[0m\n",
                $result['status'],
                $row['work_date'],
                $row['employee_id'],
                $result['confidence'],
                $result['distanceKm'],
            );
        }

        echo PHP_EOL . "  \033[1m{$ok}\033[0m matched, \033[1m{$failed}\033[0m unmatched" . PHP_EOL . PHP_EOL;
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
