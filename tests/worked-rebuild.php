<?php
// Run: php tests/worked-rebuild.php. Throwaway file-backed SQLite through the
// real config/db.php (so the command can be run as itself); never touches an
// application database.
//
// Covers `vayu worked:rebuild` — the Phase 1 worked-seconds backfill.
//
//   A  --dry is genuinely read-only: not one row of any table moves, and it
//      still prints the before/after for every day it would change
//   B  a rebuilt day's worked_seconds and status are EXACTLY what
//      Engine::rollupDay would store for those two fields
//   C  THE GUARD ON THE WHOLE DECISION: work_sessions is byte-identical
//      before and after a real run — distance_km, incomplete_distance_km and
//      point_count included — and so is attendance_days.distance_km. This
//      command must never re-derive distance; that is a separate, still-open
//      decision, because day_closeouts freezes measured_distance_km on a
//      declaration an employee signed and never snapshotted worked time.
//      See the DO NOT DELETE note on section C.
//   D  re-running is idempotent: a second run changes nothing and writes
//      nothing, which is also what makes an interrupted run resumable
//   E  today is never touched, in either mode
//   F  the window is bounded: --days/--from/--to/--date/--employee all
//      restrict what is examined, and the default does not reach all history
//   G  one audit_log row per changed day, none in a dry run
//
// If section C ever fails, STOP. It means the command has started restating
// historical distance, which nobody has decided to do.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$DBSTUB = tempnam(sys_get_temp_dir(), 'wrebuild');
$DBFILE = $DBSTUB . '.sqlite';

$_ENV['DB_TYPE']     = 'sqlite';
$_ENV['DB_DATABASE'] = $DBFILE;

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../config/migrate.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/support/bootstrap.php';
require_once __DIR__ . '/../core/Console/WorkedRebuildCommand.php';

register_shutdown_function(function () use ($DBSTUB, $DBFILE) {
    foreach ([$DBSTUB, $DBFILE, $DBFILE . '-wal', $DBFILE . '-shm'] as $file) {
        if (is_file($file)) { @unlink($file); }
    }
});

function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

// =========================================================== fixtures

foreach (migration_files() as $file) {
    $class = migration_class($file);
    if ($class !== null && is_subclass_of($class, Migration::class)) {
        (new $class($GLOBALS['pdo']))->up();
    }
}

$ZONE = new DateTimeZone('Asia/Kolkata');
$UTC  = new DateTimeZone('UTC');
$now  = Wire::now();

// weekend_days emptied on purpose: classifyDay lets a weekend win over
// presence, and this suite asserts the worked-seconds backfill on worked days,
// so the fixture must not depend on which weekday the test happens to run on.
db_execute(
    'INSERT INTO organizations (id, name, timezone, weekend_days, created_at) VALUES (?, ?, ?, ?, ?)',
    ['org', 'Hazra EV', 'Asia/Kolkata', '[]', $now],
);

// Explicit: the command skips an org with no tracking_configs row rather than
// letting Ctx::config() materialise the defaults, because that would be a
// write and --dry must not write. The 10-minute threshold is the documented
// default and the dark-tail numbers below depend on it.
db_execute(
    'INSERT INTO tracking_configs (org_id, version, updated_at, location_interval_seconds, distance_filter_metres, min_accuracy_metres, offline_threshold_minutes)
     VALUES (?, 10, ?, 10, 10, 30, 10)',
    ['org', $now],
);

$TODAY = (new DateTimeImmutable('now', $ZONE))->format('Y-m-d');
$D2    = (new DateTimeImmutable('-2 days', $ZONE))->format('Y-m-d');   // inside --days=5
$D4    = (new DateTimeImmutable('-4 days', $ZONE))->format('Y-m-d');   // inside --days=5
$OLD   = (new DateTimeImmutable('-40 days', $ZONE))->format('Y-m-d');  // outside the 30-day default

$fixSeq = 0;

$employee = function (string $slug) use ($now): void {
    db_execute(
        'INSERT INTO users (id, org_id, role, name, email, password_hash, active, account_status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        ["emp-{$slug}", 'org', 'employee', "Rep {$slug}", "{$slug}@test.example",
         password_hash('x', PASSWORD_BCRYPT), 'active', $now, $now],
    );
};

/**
 * One session on one day, with a chain of fixes, plus the stored
 * attendance_days row carrying the OLD (pre-fix) worked_seconds — which is
 * what every production row contains.
 *
 * The stored distances are deliberately ARBITRARY and deliberately
 * INCONSISTENT with each other: the day's distance_km (41.4142) is not the sum
 * of its sessions' distance_km (17.8321). Nothing in this command may
 * reconcile them. Engine::rollupDay WOULD — it re-sums the sessions into the
 * day row — so this inconsistency is the tripwire: swap the targeted UPDATE
 * for a rollupDay() call and section C fails.
 */
$seed = function (
    string $slug,
    string $workDate,
    string $startedAt,
    ?string $endedAt,
    ?string $firstFixAt,
    int $fixCount,
    int $everySeconds,
    int $storedSeconds,
    string $storedStatus,
    string $sessionId = null,
) use (&$fixSeq, $now): void {
    $sessionId ??= "s-{$slug}";

    db_execute(
        'INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, ended_at,
                                    start_lat, start_lng, distance_km, point_count, incomplete_distance_km)
         VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, 17.8321, 137, 3.5009)',
        [$sessionId, "emp-{$slug}", $sessionId . '-client', $workDate, $startedAt, $endedAt, 22.57, 88.36],
    );

    for ($i = 0; $i < $fixCount; $i++) {
        $ts = gmdate(Wire::TS, strtotime((string) $firstFixAt) + $i * $everySeconds);
        db_execute(
            'INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$sessionId, "emp-{$slug}", 'fix-' . (++$fixSeq), $ts, $ts, 22.57 + $i * 0.001, 88.36, 12, 18],
        );
    }

    db_execute(
        'INSERT INTO attendance_days
            (employee_id, work_date, status, joining_time, end_time, session_count, worked_seconds,
             distance_km, companies_visited, reports_submitted, stop_seconds, longest_stop_seconds, computed_at)
         VALUES (?, ?, ?, ?, ?, 1, ?, 41.4142, 0, 0, 0, 0, ?)',
        ["emp-{$slug}", $workDate, $storedStatus, $startedAt, $endedAt, $storedSeconds, $now],
    );
};

$at = fn(string $date, string $localTime): string =>
    (new DateTimeImmutable($date . ' ' . $localTime, $ZONE))->setTimezone($UTC)->format(Wire::TS);

foreach (['tail', 'gap', 'nofix', 'old', 'today', 'agree', 'other'] as $slug) {
    $employee($slug);
}

// emp-tail — 09:00 to 12:30 of fixes, End Day declared at 18:00. The 5½-hour
// dark tail no longer counts: 32400 stored -> 12600 derived. Attendance is
// binary, so the status stays present; only worked_seconds moves.
$seed('tail', $D2, $at($D2, '09:00:00'), $at($D2, '18:00:00'), $at($D2, '09:00:00'), 43, 300, 32400, 'present');

// emp-gap — an INTERIOR hole. Fixes 09:00-10:00 and 13:00-14:00, ended 14:05.
// The gap counts in full, so this row must NOT move. A command that "fixed"
// this day would be wrong, and the figure must not be quoted as part of the
// blast radius.
$seed('gap', $D2, $at($D2, '09:00:00'), $at($D2, '14:05:00'), $at($D2, '09:00:00'), 13, 300, 18300, 'present');
for ($i = 0; $i < 13; $i++) {
    $ts = gmdate(Wire::TS, strtotime($at($D2, '13:00:00')) + $i * 300);
    db_execute(
        'INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        ['s-gap', 'emp-gap', 'fix-' . (++$fixSeq), $ts, $ts, 22.60 + $i * 0.001, 88.36, 12, 18],
    );
}

// emp-nofix — a session with no evidence at all: 32400 stored -> 0.
$seed('nofix', $D4, $at($D4, '09:00:00'), $at($D4, '18:00:00'), null, 0, 0, 32400, 'present');

// emp-old — 40 days back, same defect. Outside the 30-day default window, so
// the default run must leave it alone and --from must be able to reach it.
$seed('old', $OLD, $at($OLD, '09:00:00'), $at($OLD, '18:00:00'), $at($OLD, '09:00:00'), 43, 300, 32400, 'present');

// emp-today — TODAY, with a wildly wrong stored figure and an open session.
// Never this command's business in any mode: an open session's stored figure
// is a live snapshot and rollupDay rewrites it on the next batch anyway.
$seed('today', $TODAY, $at($TODAY, '09:00:00'), null, $at($TODAY, '09:00:00'), 7, 300, 99999, 'present');

// emp-agree — reserved for section B, so the rollupDay() call it needs cannot
// disturb the byte-identity snapshot of anybody else.
$seed('agree', $D4, $at($D4, '09:00:00'), $at($D4, '17:30:00'), $at($D4, '09:00:00'), 25, 300, 30600, 'present');

// emp-other — the --employee filter's control: broken, in range, and must stay
// broken when somebody else is named.
$seed('other', $D4, $at($D4, '09:00:00'), $at($D4, '18:00:00'), $at($D4, '09:00:00'), 43, 300, 32400, 'present');

// ----------------------------------------------------------------- helpers

$run = function (array $args): string {
    ob_start();
    (new WorkedRebuildCommand(dirname(__DIR__), []))->run($args);
    return ob_get_clean();
};

/** Every row of a table, ordered, serialised — a byte-for-byte fingerprint. */
$snapshot = function (string $table, string $order): string {
    return serialize(db_fetch_all("SELECT * FROM {$table} ORDER BY {$order}"));
};

$dayRow = fn(string $slug, string $date): array => db_fetch_one(
    'SELECT * FROM attendance_days WHERE employee_id = ? AND work_date = ?',
    ["emp-{$slug}", $date],
) ?: [];

$auditCount = fn(): int => (int) (db_fetch_one(
    "SELECT COUNT(*) AS n FROM audit_log WHERE action = 'worked_rebuild'",
)['n'] ?? 0);

// ===========================================================================
// A — --dry is genuinely read-only
//
// Fingerprints taken over every table the command could conceivably reach,
// including tracking_configs (Ctx::config() materialises a missing row, which
// is a write, which is why the command checks for the row itself first).
// ===========================================================================
$tables = [
    'work_sessions'   => 'id',
    'attendance_days' => 'employee_id, work_date',
    'location_fixes'  => 'id',
    'stop_records'    => 'id',
    'company_visits'  => 'id',
    'day_routes'      => 'employee_id, work_date',
    'day_closeouts'   => 'id',
    'audit_log'       => 'id',
    'activity_events' => 'id',
    'tracking_configs' => 'org_id',
];

$before = [];
foreach ($tables as $table => $order) {
    $before[$table] = $snapshot($table, $order);
}

$out = $run(['--dry', '--days=5']);

foreach ($tables as $table => $order) {
    check($snapshot($table, $order) === $before[$table],
        "--dry wrote to {$table}; a dry run must not write anything at all");
}

check($auditCount() === 0, '--dry must not file an audit row');

// ...and it must still SHOW the blast radius, or it is no use for deciding.
check(str_contains($out, 'would change'), "--dry must print what it would change, said:\n{$out}");
check(str_contains($out, 'nothing was written'), '--dry must say that nothing was written');
check(str_contains($out, '9.00 h ->') && str_contains($out, '3.50 h'),
    "--dry must print the before and after hours for emp-tail, said:\n{$out}");
check(!str_contains($out, '-> partial'), 'Binary attendance: there is no present -> partial flip any more');
check(preg_match('/days_examined\s+5/', $out) === 1,
    "5 in-range days with sessions, got:\n{$out}");
check(preg_match('/days_that_move\s+4/', $out) === 1,
    "4 of them move on worked_seconds (tail, nofix, agree, other — not gap), got:\n{$out}");
check(preg_match('/days_present_to_partial\s+0/', $out) === 1,
    "partial is retired, so no day flips present -> partial, got:\n{$out}");
check(preg_match('/other status changes\s+0/', $out) === 1,
    "a started session stays present, so no status moves at all, got:\n{$out}");
// (32400-12600) + (32400-0) + (30600-7200) + (32400-12600) = 95400 s = 26.5 h
check(preg_match('/hours_removed_total\s+26\.5/', $out) === 1,
    "net hours removed must be 26.5, got:\n{$out}");

// The interior-gap day is named nowhere in the output, because it does not move.
check(!str_contains($out, 'Rep gap'), 'An interior dark gap must not be reported as changing');

// ===========================================================================
// C — THE GUARD ON THE WHOLE DECISION.  *** DO NOT DELETE ***
//
// A real run must leave work_sessions byte-identical — distance_km,
// incomplete_distance_km and point_count included — and must leave
// attendance_days.distance_km exactly as stored, even though the stored day
// figure (41.4142) deliberately disagrees with the sum of its sessions
// (17.8321). Engine::recompute would re-derive the session distances from the
// raw fixes; Engine::rollupDay would re-sum them into the day row. This
// command does neither, and that is the only reason a worked-seconds backfill
// could be decided separately from the distance one, which is still open.
//
// If this section fails, the justification for the command is gone. Stop and
// escalate rather than adjusting the expectation.
// ===========================================================================
$sessionsBefore = $snapshot('work_sessions', 'id');
$dayDistances = db_fetch_all('SELECT employee_id, work_date, distance_km FROM attendance_days ORDER BY employee_id, work_date');

$out = $run(['--days=5']);

check($snapshot('work_sessions', 'id') === $sessionsBefore,
    'work_sessions MUST be byte-identical after a run: no distance_km, no incomplete_distance_km, no point_count');

check(serialize(db_fetch_all('SELECT employee_id, work_date, distance_km FROM attendance_days ORDER BY employee_id, work_date'))
    === serialize($dayDistances),
    'attendance_days.distance_km MUST NOT move — this command does not re-sum distance');

check((float) $dayRow('tail', $D2)['distance_km'] === 41.4142,
    'The day distance must still disagree with the session sum; nothing here reconciles them');

check($snapshot('location_fixes', 'id') === $before['location_fixes'],
    'location_fixes must never be written');
check($snapshot('day_routes', 'employee_id, work_date') === $before['day_routes'],
    'No route geometry may be rebuilt');
check($snapshot('stop_records', 'id') === $before['stop_records'], 'No stop may be re-derived');
check($snapshot('company_visits', 'id') === $before['company_visits'], 'No visit may be re-promoted');
check($snapshot('day_closeouts', 'id') === $before['day_closeouts'],
    'A signed declaration must never be rewritten');
check($snapshot('activity_events', 'id') === $before['activity_events'],
    'No activity_events row: a correction burst must not read as an incident on the employee timeline');

// ...and the two fields it DOES write, written.
check((int) $dayRow('tail', $D2)['worked_seconds'] === 12600,
    'emp-tail must now read 09:00 -> 12:30 = 12600 s, got ' . json_encode($dayRow('tail', $D2)['worked_seconds']));
check($dayRow('tail', $D2)['status'] === 'present', 'emp-tail stays present — only its worked_seconds moved');
check((int) $dayRow('nofix', $D4)['worked_seconds'] === 0, 'A fixless day must read 0');
check($dayRow('nofix', $D4)['status'] === 'present', 'A started session is present even with no fixes');
check((int) $dayRow('gap', $D2)['worked_seconds'] === 18300,
    'The interior-gap day must still read 18300 s — untouched, not rewritten to the same value');
check($dayRow('gap', $D2)['status'] === 'present', 'The interior-gap day stays present');

check(str_contains($out, '4 day(s) rewritten'), "The run must report 4 rewrites, said:\n{$out}");

// ===========================================================================
// E — today is never touched, in either mode
// ===========================================================================
check((int) $dayRow('today', $TODAY)['worked_seconds'] === 99999,
    "Today's row must be left exactly as it was, got " . json_encode($dayRow('today', $TODAY)['worked_seconds']));
check($dayRow('today', $TODAY)['status'] === 'present', "Today's status must not be reclassified");

$out = $run(["--date={$TODAY}"]);
check(str_contains($out, 'empty window'), "--date=today must resolve to an empty window, said:\n{$out}");
check((int) $dayRow('today', $TODAY)['worked_seconds'] === 99999, 'Naming today explicitly must still not touch it');

// ===========================================================================
// F — the window is bounded
// ===========================================================================
check((int) $dayRow('old', $OLD)['worked_seconds'] === 32400,
    'A day 40 days back is outside the default window and outside --days=5');

$out = $run(['--dry', '--days=30']);
check(preg_match('/days_that_move\s+0/', $out) === 1,
    "After the run the in-range days are settled, got:\n{$out}");

$out = $run(['--from=2020-01-01', '--dry']);
check(preg_match('/days_that_move\s+1/', $out) === 1,
    "--from must reach the 40-day-old day, got:\n{$out}");

$out = $run(["--date={$OLD}"]);
check(str_contains($out, 'changed'), "--date must rebuild exactly that day, said:\n{$out}");
check((int) $dayRow('old', $OLD)['worked_seconds'] === 12600, 'The old day must now be corrected');

// ===========================================================================
// D — re-running is idempotent (and so an interrupted run is resumable)
// ===========================================================================
$settled = $snapshot('attendance_days', 'employee_id, work_date');
$audits  = $auditCount();

$out = $run(['--from=2020-01-01']);

check($snapshot('attendance_days', 'employee_id, work_date') === $settled,
    'A re-run must not write a single byte — not even computed_at');
check($auditCount() === $audits, 'A re-run must not file another audit row');
check(preg_match('/days_that_move\s+0/', $out) === 1, "A re-run must find nothing, got:\n{$out}");

// ===========================================================================
// G — one audit row per changed day, with both sides of the move
// ===========================================================================
check($audits === 5, "One audit row per changed day (4 + the old one), got {$audits}");

$row = db_fetch_one(
    "SELECT * FROM audit_log WHERE entity = 'attendance_day' AND entity_id = ? AND action = 'worked_rebuild'",
    ['emp-tail:' . $D2],
);
check($row !== false && $row !== null, 'The changed day must have an audit row keyed employee:date');
check($row['actor_id'] === null, 'No human did this, so actor_id must be NULL');
check(json_decode($row['before'], true) === ['worked_seconds' => 32400, 'status' => 'present'],
    'The audit row must carry the figure it replaced, got ' . json_encode($row['before']));
check(json_decode($row['after'], true) === ['worked_seconds' => 12600, 'status' => 'present'],
    'The audit row must carry what it wrote, got ' . json_encode($row['after']));

// ===========================================================================
// B — the written figure IS what Engine::rollupDay would store
//
// Last, on its own employee, because rollupDay rewrites the whole row and
// would otherwise disturb section C's fingerprints. Both the command and
// rollupDay take these two fields from Engine::dayWorkedSeconds and
// Engine::classifyDay — one definition — and this is the check that proves it
// rather than assuming it.
// ===========================================================================
$rebuilt = $dayRow('agree', $D4);
check((int) $rebuilt['worked_seconds'] === 7200,
    'emp-agree must read 09:00 -> 11:00 = 7200 s after the run, got ' . json_encode($rebuilt['worked_seconds']));

Ctx::impersonate(['id' => 'emp-agree', 'org_id' => 'org', 'role' => 'employee']);
Engine::rollupDay('emp-agree', $D4);

$viaEngine = $dayRow('agree', $D4);
check((int) $viaEngine['worked_seconds'] === (int) $rebuilt['worked_seconds'],
    'worked_seconds must equal what rollupDay writes: ' . json_encode([$rebuilt['worked_seconds'], $viaEngine['worked_seconds']]));
check($viaEngine['status'] === $rebuilt['status'],
    'status must equal what rollupDay writes: ' . json_encode([$rebuilt['status'], $viaEngine['status']]));

// And the --employee control: emp-other was corrected by the unfiltered run,
// so prove the filter from the other side — one named employee, one day.
db_execute(
    'UPDATE attendance_days SET worked_seconds = 32400, status = ? WHERE employee_id = ? AND work_date = ?',
    ['present', 'emp-other', $D4],
);
db_execute(
    'UPDATE attendance_days SET worked_seconds = 32400, status = ? WHERE employee_id = ? AND work_date = ?',
    ['present', 'emp-nofix', $D4],
);

$out = $run(['--employee=emp-other', '--days=10']);
check(preg_match('/days_that_move\s+1/', $out) === 1, "--employee must examine one employee only, got:\n{$out}");
check((int) $dayRow('other', $D4)['worked_seconds'] === 12600, 'The named employee must be corrected');
check((int) $dayRow('nofix', $D4)['worked_seconds'] === 32400,
    'An employee nobody named must be left alone');

echo "worked-rebuild: dry is read-only, distance is byte-identical, rebuild matches rollupDay, re-runs are no-ops\n";
