<?php
// Run: php tests/attendance-cache.php. Throwaway file-backed SQLite; never
// touches an application database.
//
// Covers the AttendanceController cache ruling (09-SESSION-DARK-TIME-PLAN.md
// §7.5). The controller used to send `private, max-age=86400` for any window
// ending before today, on the premise that "a window that ends before today
// can no longer change". Phase 1 falsified that premise and `worked:rebuild`
// falsifies it in bursts: a past day's status moves whenever its rollup is
// re-derived. A past window now gets a VALIDATOR instead of a lifetime —
// `private, no-cache` plus an ETag over the covered rollup rows.
//
//   A  the falsified premise is gone from the source, because as written it
//      was the reason somebody would reintroduce the bug
//   B  the ETag is stable when nothing moves — otherwise it is just a slower
//      no-store
//   C  it moves when a covered rollup row's worked_seconds/status/computed_at
//      moves (i.e. exactly what worked:rebuild writes)
//   D  it moves when a holiday is added in the window, because fallbackStatus
//      renders a day with no rollup row from the holiday table alone
//   E  it is scoped: another employee's month, another shape (grid vs
//      calendar) and another date range never share a tag
//   F  a matching If-None-Match really does short-circuit to 304 with no body
//      — run in a child process, because freshness() exits on a hit
//   G  today's window is still no-store and gets no validator at all
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$CHILD = getenv('ATT_CACHE_CHILD') !== false;

$DBSTUB = null;
if ($CHILD) {
    $DBFILE = (string) getenv('ATT_CACHE_DB');
} else {
    $DBSTUB = tempnam(sys_get_temp_dir(), 'attcache');
    $DBFILE = $DBSTUB . '.sqlite';
}

$_ENV['DB_TYPE']     = 'sqlite';
$_ENV['DB_DATABASE'] = $DBFILE;

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../config/migrate.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/ApiRequest.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../api/support/bootstrap.php';
require_once __DIR__ . '/../api/controllers/AttendanceController.php';

// Only the parent owns the temp file. The child must not delete the database
// out from under the process that spawned it.
if (!$CHILD) {
    register_shutdown_function(function () use ($DBSTUB, $DBFILE) {
        foreach ([$DBSTUB, $DBFILE, $DBFILE . '-wal', $DBFILE . '-shm'] as $file) {
            if (is_file($file)) { @unlink($file); }
        }
    });
}

function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$controller = new AttendanceController();

/** The validator and the cache decision are private; this suite is their only caller. */
$tagOf = function (string $shape, array $ids, string $from, string $to) use ($controller): string {
    $method = new ReflectionMethod(AttendanceController::class, 'validator');
    return (string) $method->invoke($controller, $shape, $ids, $from, $to);
};

$decide = function (string $shape, string $from, string $to, array $ids) use ($controller): void {
    (new ReflectionMethod(AttendanceController::class, 'cache'))
        ->invoke($controller, $shape, $from, $to, $ids);
};

// =================================================== the child process (F)

if ($CHILD) {
    Ctx::impersonate(['id' => 'emp-a', 'org_id' => 'org', 'role' => 'employee']);

    $_SERVER['HTTP_IF_NONE_MATCH'] = '"' . getenv('ATT_CACHE_TAG') . '"';

    // Runs even after freshness() exits, which is the whole point.
    register_shutdown_function(function () {
        fwrite(STDOUT, 'CODE=' . http_response_code() . PHP_EOL);
    });

    $decide('calendar', (string) getenv('ATT_CACHE_FROM'), (string) getenv('ATT_CACHE_TO'), ['emp-a']);

    // Only reached if the validator did NOT match, i.e. no short-circuit.
    fwrite(STDOUT, 'NO_SHORTCIRCUIT' . PHP_EOL);
    exit;
}

// =========================================================== fixtures

foreach (migration_files() as $file) {
    $class = migration_class($file);
    if ($class !== null && is_subclass_of($class, Migration::class)) {
        (new $class($GLOBALS['pdo']))->up();
    }
}

$now = Wire::now();

db_execute(
    'INSERT INTO organizations (id, name, timezone, weekend_days, created_at) VALUES (?, ?, ?, ?, ?)',
    ['org', 'Hazra EV', 'Asia/Kolkata', '[6]', $now],
);

foreach (['a', 'b'] as $slug) {
    db_execute(
        'INSERT INTO users (id, org_id, role, name, email, password_hash, active, account_status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        ["emp-{$slug}", 'org', 'employee', "Rep {$slug}", "{$slug}@test.example",
         password_hash('x', PASSWORD_BCRYPT), 'active', $now, $now],
    );
}

Ctx::impersonate(['id' => 'emp-a', 'org_id' => 'org', 'role' => 'employee']);

$ZONE  = new DateTimeZone('Asia/Kolkata');
$TODAY = (new DateTimeImmutable('now', $ZONE))->format('Y-m-d');
$DAY   = (new DateTimeImmutable('-20 days', $ZONE))->format('Y-m-d');
$FROM  = (new DateTimeImmutable('-30 days', $ZONE))->format('Y-m-d');
$TO    = (new DateTimeImmutable('-10 days', $ZONE))->format('Y-m-d');

$day = function (string $slug, string $date, string $status, int $seconds) use ($now): void {
    db_execute(
        'INSERT INTO attendance_days
            (employee_id, work_date, status, joining_time, end_time, session_count, worked_seconds,
             distance_km, companies_visited, reports_submitted, stop_seconds, longest_stop_seconds, computed_at)
         VALUES (?, ?, ?, ?, ?, 1, ?, 0, 0, 0, 0, 0, ?)',
        ["emp-{$slug}", $date, $status, $now, $now, $seconds, $now],
    );
};

$day('a', $DAY, 'present', 32400);
$day('b', $DAY, 'present', 32400);

// ===========================================================================
// A — the falsified premise must not still be standing in the source
// ===========================================================================
$src = file_get_contents(__DIR__ . '/../api/controllers/AttendanceController.php');
check(!str_contains($src, 'can no longer change'),
    'The falsified premise comment is still in AttendanceController — it is the reason the bug comes back');
check(str_contains($src, 'Envelope::REVALIDATE'), 'A past window must use the validator path');
check(!preg_match('/cache\(.*\n.*Envelope::IMMUTABLE/', $src),
    'A past attendance window must no longer be served with a 24-hour lifetime');
check(Envelope::REVALIDATE === 'private, no-cache',
    'REVALIDATE must cache-and-check, got ' . Envelope::REVALIDATE);

// ===========================================================================
// B — stable when nothing moves
// ===========================================================================
$tag = $tagOf('calendar', ['emp-a'], $FROM, $TO);
check($tag === $tagOf('calendar', ['emp-a'], $FROM, $TO), 'The tag must be stable across identical reads');
check(str_starts_with($tag, 'att-'), 'Unexpected tag shape: ' . $tag);

// A read of the same month that touches nothing must not move it either.
db_fetch_all('SELECT * FROM attendance_days');
check($tag === $tagOf('calendar', ['emp-a'], $FROM, $TO), 'Reading must not move the tag');

// ===========================================================================
// C — moves when the covered rollup moves, which is what worked:rebuild does
// ===========================================================================
db_execute(
    'UPDATE attendance_days SET worked_seconds = ?, status = ?, computed_at = ? WHERE employee_id = ? AND work_date = ?',
    [12600, 'partial', gmdate(Wire::TS, time() + 5), 'emp-a', $DAY],
);

$afterRebuild = $tagOf('calendar', ['emp-a'], $FROM, $TO);
check($afterRebuild !== $tag,
    'A corrected past day MUST invalidate its own month — this is the whole bug');

// A day appearing inside the window moves it too.
$day('a', (new DateTimeImmutable('-15 days', $ZONE))->format('Y-m-d'), 'present', 28800);
$afterInsert = $tagOf('calendar', ['emp-a'], $FROM, $TO);
check($afterInsert !== $afterRebuild, 'A new rollup row in the window must invalidate it');

// ===========================================================================
// D — a holiday added in the window moves it, because a day with no rollup row
//     renders from the holiday table alone
// ===========================================================================
db_execute(
    'INSERT INTO holidays (org_id, date, name) VALUES (?, ?, ?)',
    ['org', (new DateTimeImmutable('-25 days', $ZONE))->format('Y-m-d'), 'Test Holiday'],
);

$afterHoliday = $tagOf('calendar', ['emp-a'], $FROM, $TO);
check($afterHoliday !== $afterInsert, 'A holiday added in the window must invalidate it');

// ===========================================================================
// E — scoped by subject, by shape and by range
// ===========================================================================
check($tagOf('calendar', ['emp-b'], $FROM, $TO) !== $afterHoliday,
    "One employee's month must never validate another's");
check($tagOf('grid', ['emp-a'], $FROM, $TO) !== $afterHoliday,
    'The team grid and the calendar are different payloads and need different tags');
check($tagOf('calendar', ['emp-a'], $FROM, $DAY) !== $afterHoliday, 'A different range is a different tag');
check($tagOf('calendar', ['emp-a', 'emp-b'], $FROM, $TO) !== $tagOf('calendar', ['emp-a'], $FROM, $TO),
    'A wider subject set is a different tag');

// An empty window still produces a usable tag rather than blowing up.
$empty = (new DateTimeImmutable('-400 days', $ZONE))->format('Y-m-d');
check($tagOf('calendar', ['emp-a'], $empty, $empty) !== '', 'A window with no rows must still tag');

// ===========================================================================
// G — today's window is still no-store, and gets no validator
//
// header() is a no-op under CLI, so what is asserted here is the branch: the
// past-window path short-circuits a matching request (section F proves it) and
// the today path returns without ever consulting a tag. Confirming the literal
// header on the wire needs a served instance — the same limitation the board
// already records for tests/admin-session-guard.php.
// ===========================================================================
$decide('calendar', $FROM, $TODAY, ['emp-a']);   // must simply return
$_SERVER['HTTP_IF_NONE_MATCH'] = '"' . $afterHoliday . '"';
$decide('calendar', $FROM, $TODAY, ['emp-a']);   // even with a matching tag held
unset($_SERVER['HTTP_IF_NONE_MATCH']);
check(http_response_code() !== 304, "Today's window must never answer 304 from a held tag");

// ===========================================================================
// F — a matching If-None-Match short-circuits to 304, in a child process
//     because freshness() exits on a hit
// ===========================================================================
$tagNow = $tagOf('calendar', ['emp-a'], $FROM, $TO);

$command = sprintf(
    'ATT_CACHE_CHILD=1 ATT_CACHE_DB=%s ATT_CACHE_TAG=%s ATT_CACHE_FROM=%s ATT_CACHE_TO=%s %s %s 2>&1',
    escapeshellarg($DBFILE), escapeshellarg($tagNow),
    escapeshellarg($FROM), escapeshellarg($TO),
    escapeshellarg(PHP_BINARY), escapeshellarg(__FILE__),
);

$hit = (string) shell_exec($command);
check(str_contains($hit, 'CODE=304'), "A matching tag must answer 304, child said:\n{$hit}");
check(!str_contains($hit, 'NO_SHORTCIRCUIT'), "A matching tag must not fall through, child said:\n{$hit}");

$stale = (string) shell_exec(str_replace(
    'ATT_CACHE_TAG=' . escapeshellarg($tagNow),
    'ATT_CACHE_TAG=' . escapeshellarg('att-stale'),
    $command,
));
check(str_contains($stale, 'NO_SHORTCIRCUIT'), "A stale tag must fall through to the body, child said:\n{$stale}");
check(!str_contains($stale, 'CODE=304'), "A stale tag must not answer 304, child said:\n{$stale}");

echo "attendance-cache: past windows revalidate, a corrected day invalidates its own month, a match is a 304\n";
