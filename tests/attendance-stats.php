<?php
// Run: php tests/attendance-stats.php. Throwaway file-backed SQLite with the
// real migrations and db_* helpers (same pattern as session-worked-time.php).
//
// THE RULE under test: attendance percentages divide by the organisation's
// WORKING CALENDAR, not by the set of stored attendance_days rows. The rollup
// writes no row for a day nobody worked, so counting rows made the denominator
// equal the numerator and every employee read 100% present. The denominator is
// weekdays in range that are neither weekend nor holiday, up to today, and from
// the employee's joining date.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$DBSTUB = tempnam(sys_get_temp_dir(), 'attend');
$DBFILE = $DBSTUB . '.sqlite';
$_ENV['DB_TYPE']     = 'sqlite';
$_ENV['DB_DATABASE'] = $DBFILE;

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../config/migrate.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../api/support/bootstrap.php';
require_once __DIR__ . '/../api/controllers/StatisticsController.php';

register_shutdown_function(function () use ($DBSTUB, $DBFILE) {
    foreach ([$DBSTUB, $DBFILE, $DBFILE . '-wal', $DBFILE . '-shm'] as $f) {
        if (is_file($f)) { @unlink($f); }
    }
});

function check($cond, $msg) { if (!$cond) throw new RuntimeException($msg); }

foreach (migration_files() as $file) {
    $class = migration_class($file);
    if ($class !== null && is_subclass_of($class, Migration::class)) {
        (new $class($GLOBALS['pdo']))->up();
    }
}

$now = Wire::now();

// Weekend = Saturday+Sunday (ISO 6,7). October 2026 is entirely in the past
// relative to any plausible run date after it, so the range is not clipped by
// "today".
db_execute(
    'INSERT INTO organizations (id, name, timezone, weekend_days, created_at) VALUES (?, ?, ?, ?, ?)',
    ['org', 'Hazra EV', 'Asia/Kolkata', '[6,7]', $now],
);

function mkEmployee(string $id, string $joinedOn, string $now): void {
    db_execute(
        'INSERT INTO users (id, org_id, role, name, email, password_hash, active, account_status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        [$id, 'org', 'employee', "Rep {$id}", "{$id}@test.example",
         password_hash('x', PASSWORD_BCRYPT), 'active', $now, $now],
    );
    db_execute(
        'INSERT INTO employee_profiles (user_id, employee_code, designation, department, region, joined_on)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$id, strtoupper($id), 'Rep', 'Sales', 'West', $joinedOn],
    );
}

mkEmployee('emp-a', '2026-01-01', $now);   // joined long before the range

// Seven worked weekdays in October: 5 present, 2 partial. All non-holiday,
// non-weekend. (Oct 2026: 1=Thu, so 5,6,7,8,9 are Mon–Fri of week 2; 12,13 the
// following Mon/Tue.)
$present = ['2026-10-05','2026-10-06','2026-10-07','2026-10-08','2026-10-09'];
$partial = ['2026-10-12','2026-10-13'];
foreach ([['present',$present],['partial',$partial]] as [$status,$dates]) {
    foreach ($dates as $d) {
        db_execute(
            'INSERT INTO attendance_days (employee_id, work_date, status, session_count, worked_seconds,
                distance_km, companies_visited, reports_submitted, computed_at)
             VALUES (?, ?, ?, 1, 28800, 10.0, 2, 1, ?)',
            ['emp-a', $d, $status, $now],
        );
    }
}

// One holiday on a weekday in range — it must drop out of the denominator.
db_execute('INSERT INTO holidays (org_id, date, name) VALUES (?, ?, ?)',
    ['org', '2026-10-02', 'Gandhi Jayanti']);

Ctx::impersonate(['id' => 'emp-a', 'org_id' => 'org', 'role' => 'employee']);

$ctrl = new StatisticsController();
$ref = new ReflectionClass($ctrl);

$call = function (string $method, array $args) use ($ctrl, $ref) {
    $m = $ref->getMethod($method);
    $m->setAccessible(true);
    return $m->invokeArgs($ctrl, $args);
};

$rows = db_fetch_all(
    'SELECT * FROM attendance_days WHERE employee_id = ? AND work_date BETWEEN ? AND ? ORDER BY work_date',
    ['emp-a', '2026-10-01', '2026-10-31'],
);

// --- A: calendarWorkingDays over the whole month, no join cap --------------
// Oct 2026 has 22 weekdays (Mon–Fri); minus the one holiday = 21.
$wd = $call('calendarWorkingDays', ['2026-10-01', '2026-10-31', null]);
check($wd === 21, "A: expected 21 working days, got {$wd}");

// --- B: period() divides by the calendar, not by stored rows ---------------
$p = $call('period', [$rows, 'October', '2026-10-01', '2026-10-31', 'emp-a']);
check($p['workingDays'] === 21, "B: workingDays expected 21, got {$p['workingDays']}");
check($p['presentDays'] === 7, "B: presentDays expected 7, got {$p['presentDays']}");
check($p['absentDays'] === 14, "B: absentDays expected 14, got {$p['absentDays']}");
check(abs($p['attendancePercent'] - (7 * 100 / 21)) < 0.01,
    "B: attendancePercent expected ~33.33, got {$p['attendancePercent']}");

// --- C: a day nobody worked is NOT 100% (the original bug) -----------------
$pEmpty = $call('period', [[], 'October', '2026-10-01', '2026-10-31', 'emp-a']);
check($pEmpty['attendancePercent'] === 0.0,
    "C: no rows must read 0%, got {$pEmpty['attendancePercent']}");
check($pEmpty['absentDays'] === 21, "C: absentDays expected 21, got {$pEmpty['absentDays']}");

// --- D: joining date clips the denominator ---------------------------------
db_execute('UPDATE employee_profiles SET joined_on = ? WHERE user_id = ?',
    ['2026-10-20', 'emp-a']);
// Oct 20–31: 20=Tue … 31. Weekends 24,25,31 → 9 working days, no holiday in range.
$wdJoin = $call('calendarWorkingDays', ['2026-10-01', '2026-10-31', '2026-10-20']);
check($wdJoin === 9, "D: expected 9 working days from the join date, got {$wdJoin}");
$pJoin = $call('period', [$rows, 'October', '2026-10-01', '2026-10-31', 'emp-a']);
check($pJoin['workingDays'] === 9, "D: period workingDays expected 9, got {$pJoin['workingDays']}");

// --- E: future dates never count as working days ---------------------------
$future = $call('calendarWorkingDays', ['2099-01-01', '2099-01-31', null]);
check($future === 0, "E: a wholly future range must be 0, got {$future}");

// --- F: team() divides attended employee-days by calendar x headcount ------
db_execute('UPDATE employee_profiles SET joined_on = ? WHERE user_id = ?',
    ['2026-01-01', 'emp-a']);
mkEmployee('emp-b', '2026-01-01', $now);   // emp-b worked nothing
$teamRows = db_fetch_all(
    'SELECT a.* FROM attendance_days a WHERE a.employee_id IN (?, ?) AND a.work_date BETWEEN ? AND ? ORDER BY a.work_date',
    ['emp-a', 'emp-b', '2026-10-01', '2026-10-31'],
);
$t = $call('team', [$teamRows, ['emp-a', 'emp-b'], 'October', '2026-10-01', '2026-10-31']);
// attended = 7 (emp-a); possible = 21 working days x 2 heads = 42 → 16.67%.
check(abs($t['attendancePercent'] - (7 * 100 / 42)) < 0.01,
    "F: team attendancePercent expected ~16.67, got {$t['attendancePercent']}");
check($t['workingDays'] === 21, "F: team workingDays expected 21, got {$t['workingDays']}");

echo "attendance-stats: all checks passed\n";
