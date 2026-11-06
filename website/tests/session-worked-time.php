<?php
// Run: php tests/session-worked-time.php. Uses a throwaway file-backed SQLite
// DB (the real config/db.php, pointed at a temp file through $_ENV) so the
// `days:close` command can be run as itself; never touches an application DB.
//
// Covers session dark-time accounting — Engine::sessionWorkedSeconds (through
// Engine::rollupDay) and the evidence-capped midnight stamp in
// CloseDaysCommand.
//
// THE RULE: a session's worked time runs from its start to the last moment we
// have evidence for. Dark time counts as worked ONLY when it sits between two
// known-good fixes.
//
//   A  a trailing dark tail is EXCLUDED — no fix follows it, so there is no
//      evidence of work, and a phantom-hours day's verdict flips with it
//   B  an interior dark gap is NOT excluded — fixes on both sides, the
//      employee was working, the device just had no signal (and backfills the
//      stretch from its offline queue anyway)
//   C  an open session is capped at its last fix, not at the clock, so a dead
//      phone stops accruing hours against a roster that already reads offline
//   D  a session with zero fixes contributes 0, not its wall-clock span
//   E  the threshold boundary: a tail shorter than (or exactly at)
//      offlineThresholdMinutes is not trimmed, and the threshold is read from
//      config at read time, so retuning re-derives both ways
//   F  Engine::recompute composes — rollupDay runs last and is not undone
//   G  `days:close` stamps last-evidence, not 23:59:59, on the session, its
//      stops, its visits and the stand-in declaration's ended_at
//   H  re-running `days:close` stays idempotent, and the priorCloses /
//      client_id scheme still produces a second row after a reopen
//
// Evidence means a stored location_fixes row. `offline` (stale received_at)
// and `location_unavailable` (stale recorded_at / blind device_health) stay
// different statuses for display; for this arithmetic they are the same thing,
// dark, so nothing here reads a status.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$DBSTUB = tempnam(sys_get_temp_dir(), 'worked');
$DBFILE = $DBSTUB . '.sqlite';

// Read by config/db.php below, and again by CloseDaysCommand::run(), which
// requires the same file. One connection, one temp database, the real db_*
// helpers (and so the real Dialect translation) rather than hand-shimmed ones.
$_ENV['DB_TYPE']     = 'sqlite';
$_ENV['DB_DATABASE'] = $DBFILE;

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../config/migrate.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/support/bootstrap.php';
require_once __DIR__ . '/../core/Console/CloseDaysCommand.php';

register_shutdown_function(function () use ($DBSTUB, $DBFILE) {
    foreach ([$DBSTUB, $DBFILE, $DBFILE . '-wal', $DBFILE . '-shm'] as $file) {
        if (is_file($file)) { @unlink($file); }
    }
});

function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

foreach (migration_files() as $file) {
    $class = migration_class($file);
    if ($class !== null && is_subclass_of($class, Migration::class)) {
        (new $class($GLOBALS['pdo']))->up();
    }
}

// --------------------------------------------------------------- fixtures

$ZONE = new DateTimeZone('Asia/Kolkata');
$UTC  = new DateTimeZone('UTC');
$now  = Wire::now();

// weekend_days is emptied on purpose: classifyDay lets a weekend win over
// presence, and this suite asserts present-vs-partial, so the fixture must not
// depend on which weekday the test happens to run on.
db_execute(
    'INSERT INTO organizations (id, name, timezone, weekend_days, created_at) VALUES (?, ?, ?, ?, ?)',
    ['org', 'Hazra EV', 'Asia/Kolkata', '[]', $now],
);

$PAST  = (new DateTimeImmutable('-3 days', $ZONE))->format('Y-m-d');
$TODAY = (new DateTimeImmutable('now', $ZONE))->format('Y-m-d');

/** Org-local wall clock on a work date -> the stored UTC timestamp. */
$at = function (string $date, string $localTime) use ($ZONE, $UTC): string {
    return (new DateTimeImmutable($date . ' ' . $localTime, $ZONE))
        ->setTimezone($UTC)->format(Wire::TS);
};

$plus = fn(string $ts, int $seconds): string => gmdate(Wire::TS, strtotime($ts) + $seconds);
$ago  = fn(int $seconds): string => gmdate(Wire::TS, time() - $seconds);

$employees = [
    'tail', 'gap', 'open', 'nofix', 'b599', 'b600', 'b601', 'close', 'closenofix',
];

foreach ($employees as $slug) {
    db_execute(
        'INSERT INTO users (id, org_id, role, name, email, password_hash, active, account_status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        ["emp-{$slug}", 'org', 'employee', "Rep {$slug}", "{$slug}@test.example",
         password_hash('x', PASSWORD_BCRYPT), 'active', $now, $now],
    );
}

$BASE = [22.5700, 88.3600];

/** Offsets a lat/lng by metres north/east — flat-earth is exact enough here. */
function at_metres(array $origin, float $northM, float $eastM): array {
    return [
        $origin[0] + $northM / 111_320.0,
        $origin[1] + $eastM / (111_320.0 * cos(deg2rad($origin[0]))),
    ];
}

$fixSeq = 0;

/**
 * Inserts a session plus a chain of fixes walking steadily away from base, one
 * every $everySeconds from $firstFixAt. Fixes move (~120 m a step) so no stop
 * is derived and the fixtures stay about worked time only.
 */
$session = function (
    string $employeeId,
    string $sessionId,
    string $workDate,
    string $startedAt,
    ?string $endedAt,
    ?string $firstFixAt,
    int $fixCount,
    int $everySeconds,
    int $metresPerStep = 120,
) use (&$fixSeq, $BASE): void {
    db_execute(
        'INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, ended_at, start_lat, start_lng)
         VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)',
        [$sessionId, $employeeId, $sessionId . '-client', $workDate, $startedAt, $endedAt, $BASE[0], $BASE[1]],
    );

    for ($i = 0; $i < $fixCount; $i++) {
        $ts = gmdate(Wire::TS, strtotime((string) $firstFixAt) + $i * $everySeconds);
        $point = at_metres($BASE, $i * $metresPerStep, 0);
        db_execute(
            'INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$sessionId, $employeeId, 'fix-' . (++$fixSeq), $ts, $ts, $point[0], $point[1], 12, 18],
        );
    }
};

/** Adds more fixes to an existing session, starting at $firstFixAt. */
$extraFixes = function (
    string $employeeId,
    string $sessionId,
    string $firstFixAt,
    int $fixCount,
    int $everySeconds,
    int $startMetres,
) use (&$fixSeq, $BASE): void {
    for ($i = 0; $i < $fixCount; $i++) {
        $ts = gmdate(Wire::TS, strtotime($firstFixAt) + $i * $everySeconds);
        $point = at_metres($BASE, $startMetres + $i * 120, 0);
        db_execute(
            'INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$sessionId, $employeeId, 'fix-' . (++$fixSeq), $ts, $ts, $point[0], $point[1], 12, 18],
        );
    }
};

/** Re-impersonating also drops Ctx's cached config, which some checks retune. */
$reload = function (string $employeeId): void {
    Ctx::impersonate(['id' => $employeeId, 'org_id' => 'org', 'role' => 'employee']);
};

$day = fn(string $employeeId, string $workDate): array => db_fetch_one(
    'SELECT status, worked_seconds, session_count FROM attendance_days WHERE employee_id = ? AND work_date = ?',
    [$employeeId, $workDate],
) ?: [];

$worked = function (string $employeeId, string $workDate) use ($day): int {
    $row = $day($employeeId, $workDate);
    check($row !== [], "No attendance_days row for {$employeeId} {$workDate}");
    return (int) $row['worked_seconds'];
};

$reload('emp-tail');
$config = Ctx::config();
check((int) $config['offline_threshold_minutes'] === 10,
    'Expected the documented 10-minute offline threshold, got ' . json_encode($config['offline_threshold_minutes']));
$THRESHOLD = 600;

// ===========================================================================
// A — a trailing dark tail is EXCLUDED, and the day's verdict flips with it
//
// Started 09:00 local, fixes every 5 minutes to 12:30, then nothing. The
// employee tapped End Day at 18:00 — or their phone was closed out — but the
// last five and a half hours have no evidence behind them at all.
// ===========================================================================
$tailStart = $at($PAST, '09:00:00');
$session('emp-tail', 's-tail', $PAST, $tailStart, $at($PAST, '18:00:00'), $tailStart, 43, 300);

$reload('emp-tail');
Engine::rollupDay('emp-tail', $PAST);

$wall = Wire::seconds($tailStart, $at($PAST, '18:00:00'));
check($wall === 32400, "Fixture check: the wall-clock span should be 9 h, got {$wall}");
check($worked('emp-tail', $PAST) === 12600,
    'A trailing dark tail must be excluded: expected 12600 s (09:00 -> 12:30), got ' . $worked('emp-tail', $PAST));

// The verdict, which is the part a human reads. 9 h of wall clock is a full
// day; 3.5 h of evidence is not.
check(Engine::classifyDay($PAST, 1, $wall) === 'present',
    'Fixture check: the uncorrected 9 h span must have classified as present');
check($day('emp-tail', $PAST)['status'] === 'partial',
    'A phantom-hours day must read partial once its dark tail is removed, got ' . json_encode($day('emp-tail', $PAST)['status']));

// ===========================================================================
// B — an interior dark gap is NOT excluded
//
// The asymmetry this whole change exists for. Same shape of hole as A — three
// hours with no fix — but a fix follows it, so the employee demonstrably kept
// working through it and the stretch counts in full. Nothing here subdivides
// the span, so an interior gap cannot be trimmed by construction.
// ===========================================================================
$gapStart = $at($PAST, '09:00:00');
$gapEnd   = $at($PAST, '14:05:00');       // 5 min after the last fix: under the threshold
$session('emp-gap', 's-gap', $PAST, $gapStart, $gapEnd, $gapStart, 13, 300);          // 09:00 -> 10:00
$extraFixes('emp-gap', 's-gap', $at($PAST, '13:00:00'), 13, 300, 1560);              // 13:00 -> 14:00

$reload('emp-gap');
Engine::rollupDay('emp-gap', $PAST);

$gapWall = Wire::seconds($gapStart, $gapEnd);
check($gapWall === 18300, "Fixture check: expected an 18300 s span, got {$gapWall}");
check($worked('emp-gap', $PAST) === 18300,
    'An interior dark gap must count in full: expected 18300 s, got ' . $worked('emp-gap', $PAST));

// Said the other way round, so the intent is unmistakable: worked time is NOT
// the sum of the covered stretches.
$covered = 3600 + 3600;
check($worked('emp-gap', $PAST) > $covered,
    'Worked time must not collapse to the covered stretches only');
check($worked('emp-gap', $PAST) - $covered === 11100,
    'The 3 h hole plus the 5 min tail must both be inside worked time');

// And the same day still classifies as present, where A did not — the interior
// hole is the only difference between the two fixtures.
check($day('emp-gap', $PAST)['status'] === 'present',
    'A day with an interior gap must still read present, got ' . json_encode($day('emp-gap', $PAST)['status']));

// ===========================================================================
// C — an open session is capped at its last fix, not at the clock
//
// The dead-phone case. Started two hours ago, last fix ninety minutes ago, no
// End Day. The roster already says offline; the timesheet must agree.
// ===========================================================================
$openStart = $ago(7200);
$session('emp-open', 's-open', $TODAY, $openStart, null, $openStart, 7, 300);  // last fix at -5400

$reload('emp-open');
Engine::rollupDay('emp-open', $TODAY);

check($worked('emp-open', $TODAY) === 1800,
    'An open session must stop at its last fix: expected 1800 s, got ' . $worked('emp-open', $TODAY));
check($worked('emp-open', $TODAY) < Wire::seconds($openStart, Wire::now()),
    'An open session must not accrue to the clock');

// Rolled up again at a later wall-clock moment: the same number. Worked time
// on a dark session is a function of the evidence, not of when you asked.
Engine::rollupDay('emp-open', $TODAY);
check($worked('emp-open', $TODAY) === 1800,
    'A second rollup must not move an open dark session, got ' . $worked('emp-open', $TODAY));

// ===========================================================================
// D — zero fixes contributes zero
// ===========================================================================
$session('emp-nofix', 's-nofix', $PAST, $at($PAST, '09:00:00'), $at($PAST, '18:00:00'), null, 0, 0);

$reload('emp-nofix');
Engine::rollupDay('emp-nofix', $PAST);

check($worked('emp-nofix', $PAST) === 0,
    'A session with no fixes must contribute 0, got ' . $worked('emp-nofix', $PAST));
// Still a session, and still not an absence: the day was declared, it just has
// nothing behind it.
check((int) $day('emp-nofix', $PAST)['session_count'] === 1, 'The fixless session must still be counted');
check($day('emp-nofix', $PAST)['status'] === 'partial',
    'A fixless day is partial, not absent, got ' . json_encode($day('emp-nofix', $PAST)['status']));

// ===========================================================================
// E — the threshold boundary, and that the threshold is read at read time
//
// Three identical sessions, 09:00 -> 11:00 of fixes, differing only in how
// long after the last fix ended_at falls: one second under the threshold,
// exactly on it, one second over. "Exceeds" is strict, so only the third is
// trimmed and nothing moves by an off-by-one second.
// ===========================================================================
$edges = ['b599' => $THRESHOLD - 1, 'b600' => $THRESHOLD, 'b601' => $THRESHOLD + 1];

foreach ($edges as $slug => $tail) {
    $start  = $at($PAST, '09:00:00');
    $lastAt = $at($PAST, '11:00:00');
    $session("emp-{$slug}", "s-{$slug}", $PAST, $start, $plus($lastAt, $tail), $start, 25, 300);
    $reload("emp-{$slug}");
    Engine::rollupDay("emp-{$slug}", $PAST);
}

check($worked('emp-b599', $PAST) === 7200 + 599,
    'A 599 s tail is under a 600 s threshold and must NOT be trimmed, got ' . $worked('emp-b599', $PAST));
check($worked('emp-b600', $PAST) === 7200 + 600,
    'A tail exactly at the threshold must NOT be trimmed (exceeds is strict), got ' . $worked('emp-b600', $PAST));
check($worked('emp-b601', $PAST) === 7200,
    'A 601 s tail exceeds the threshold and must be trimmed to the last fix, got ' . $worked('emp-b601', $PAST));

// Retune to 11 minutes: the same stored fixes, the same stored ended_at, and
// the 601 s tail is now inside the threshold. Nothing about the decision was
// ever stored, so it re-derives both ways.
db_execute('UPDATE tracking_configs SET offline_threshold_minutes = 11 WHERE org_id = ?', ['org']);
$reload('emp-b601');
check((int) Ctx::config()['offline_threshold_minutes'] === 11, 'The retune must be visible to Ctx');
Engine::rollupDay('emp-b601', $PAST);
check($worked('emp-b601', $PAST) === 7200 + 601,
    'Widening the threshold must re-derive worked time upward, got ' . $worked('emp-b601', $PAST));

db_execute('UPDATE tracking_configs SET offline_threshold_minutes = 10 WHERE org_id = ?', ['org']);
$reload('emp-b601');
Engine::rollupDay('emp-b601', $PAST);
check($worked('emp-b601', $PAST) === 7200,
    'Narrowing it back must trim again, got ' . $worked('emp-b601', $PAST));

// ===========================================================================
// F — Engine::recompute composes with the trim
//
// recompute() re-derives sessions, stops, visits, the timeline and the route
// before the rollup. If any of that undid the trim, a full day rebuild would
// quietly restore the phantom hours.
// ===========================================================================
$reload('emp-tail');
Engine::recompute('emp-tail', $PAST);
check($worked('emp-tail', $PAST) === 12600,
    'recompute() must not restore a trimmed dark tail, got ' . $worked('emp-tail', $PAST));
check($day('emp-tail', $PAST)['status'] === 'partial', 'recompute() must not restore the present verdict');

// ===========================================================================
// G — `days:close` stamps last evidence, not 23:59:59
//
// Phone alive 09:00 -> 14:10 local, then dark, never ended. The stop and the
// visit open inside that session must be stamped with the same capped moment,
// so neither can outlive its session.
// ===========================================================================
$closeStart = $at($PAST, '09:00:00');
$session('emp-close', 's-close', $PAST, $closeStart, null, $closeStart, 32, 600);  // last fix 14:10
$lastEvidence = $at($PAST, '14:10:00');
$midnight     = $at($PAST, '23:59:59');

// A hand-linked stop + visit. The branch is deliberately far away so
// promoteVisits matches nothing and leaves both rows exactly as stamped.
db_execute('INSERT INTO companies (id, org_id, name, category, updated_at) VALUES (?, ?, ?, ?, ?)',
    ['co-1', 'org', 'Acme Motors', 'Retail', $now]);
db_execute('INSERT INTO branches (id, company_id, name, address, lat, lng) VALUES (?, ?, ?, ?, ?, ?)',
    ['br-1', 'co-1', 'Far Branch', 'Elsewhere', 23.9000, 89.9000]);
db_execute(
    'INSERT INTO company_visits (id, session_id, employee_id, work_date, company_id, branch_id, stop_id, arrival, departure, lat, lng, status)
     VALUES (?, ?, ?, ?, ?, ?, NULL, ?, NULL, ?, ?, ?)',
    ['visit-1', 's-close', 'emp-close', $PAST, 'co-1', 'br-1', $at($PAST, '10:00:00'), $BASE[0], $BASE[1], 'in_progress'],
);
db_execute(
    'INSERT INTO stop_records (id, session_id, employee_id, work_date, arrival, departure, lat, lng, radius_m, visit_id)
     VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?)',
    ['stop-1', 's-close', 'emp-close', $PAST, $at($PAST, '10:00:00'), $BASE[0], $BASE[1], 40, 'visit-1'],
);

$runClose = function (array $args) {
    ob_start();
    (new CloseDaysCommand(dirname(__DIR__), []))->run($args);
    return ob_get_clean();
};

$out = $runClose(['--employee=emp-close']);
check(str_contains($out, 'closed'), "days:close should report a close, said: {$out}");

$closedSession = db_fetch_one('SELECT ended_at FROM work_sessions WHERE id = ?', ['s-close']);
check(Wire::ts($closedSession['ended_at']) === $lastEvidence,
    'The session must be stamped at its last fix (' . $lastEvidence . '), got ' . json_encode($closedSession['ended_at']));
check(Wire::ts($closedSession['ended_at']) !== $midnight, 'The session must not be stamped at local midnight');

$stop = db_fetch_one('SELECT departure FROM stop_records WHERE id = ?', ['stop-1']);
check(Wire::ts($stop['departure']) === $lastEvidence,
    'An open stop must depart at the same capped moment, got ' . json_encode($stop['departure']));

$visit = db_fetch_one('SELECT departure, status FROM company_visits WHERE id = ?', ['visit-1']);
check(Wire::ts($visit['departure']) === $lastEvidence,
    'An open visit must depart at the same capped moment, got ' . json_encode($visit['departure']));
check($visit['status'] === 'completed', 'The visit must be completed, got ' . json_encode($visit['status']));

$closeouts = db_fetch_all(
    'SELECT * FROM day_closeouts WHERE employee_id = ? AND work_date = ? ORDER BY client_id',
    ['emp-close', $PAST],
);
check(count($closeouts) === 1, 'One closeout row expected, got ' . count($closeouts));
check($closeouts[0]['state'] === 'closed_by_system', 'The closeout must say closed_by_system');
check($closeouts[0]['client_id'] === 'auto-' . $PAST, 'Unexpected client_id ' . json_encode($closeouts[0]['client_id']));
check(Wire::ts($closeouts[0]['ended_at']) === $lastEvidence,
    'The declaration ended_at must follow the evidence, not 23:59:59 — got ' . json_encode($closeouts[0]['ended_at']));

check($worked('emp-close', $PAST) === 18600,
    'The closed day must report 09:00 -> 14:10 = 18600 s, got ' . $worked('emp-close', $PAST));

// ===========================================================================
// H — re-running `days:close` is still idempotent, and a reopen still gets its
//     own row
// ===========================================================================
$out = $runClose(['--employee=emp-close']);
check(str_contains($out, 'nothing open'), "A second run should find nothing, said: {$out}");

$again = db_fetch_all('SELECT * FROM day_closeouts WHERE employee_id = ? AND work_date = ?', ['emp-close', $PAST]);
check(count($again) === 1, 'A re-run must not write a second closeout, got ' . count($again));
check($again[0]['ended_at'] === $closeouts[0]['ended_at'], 'A re-run must not move the declaration ended_at');
check(
    db_fetch_one('SELECT ended_at FROM work_sessions WHERE id = ?', ['s-close'])['ended_at'] === $closedSession['ended_at'],
    'A re-run must not move the session stamp',
);

// Reopened by an admin, left open again, swept a second time: append-only, so
// the client_id has to differ or the insert collides on UNIQUE (employee_id,
// client_id).
db_execute('UPDATE day_closeouts SET reopened_at = ? WHERE id = ?', [Wire::now(), $closeouts[0]['id']]);
db_execute('UPDATE work_sessions SET ended_at = NULL WHERE id = ?', ['s-close']);

$runClose(['--employee=emp-close']);
$twice = db_fetch_all(
    'SELECT * FROM day_closeouts WHERE employee_id = ? AND work_date = ? ORDER BY client_id',
    ['emp-close', $PAST],
);
check(count($twice) === 2, 'A reopened-then-stale day must get a second row, got ' . count($twice));
check($twice[1]['client_id'] === 'auto-' . $PAST . '-2',
    'Unexpected second client_id ' . json_encode($twice[1]['client_id']));
check(Wire::ts($twice[1]['ended_at']) === $lastEvidence,
    'The second close must land on the same evidence, got ' . json_encode($twice[1]['ended_at']));
check(Wire::ts(db_fetch_one('SELECT ended_at FROM work_sessions WHERE id = ?', ['s-close'])['ended_at']) === $lastEvidence,
    'The second close must re-stamp the session at its last fix');

// ===========================================================================
// I — a fixless session at the midnight sweep
//
// Nothing to cap to, so the 23:59:59 stamp stands — and nothing is overstated
// by it, because the rollup contributes 0 seconds for a session with no
// evidence whatever its ended_at says.
// ===========================================================================
$session('emp-closenofix', 's-closenofix', $PAST, $at($PAST, '09:00:00'), null, null, 0, 0);

$runClose(['--employee=emp-closenofix']);

check(Wire::ts(db_fetch_one('SELECT ended_at FROM work_sessions WHERE id = ?', ['s-closenofix'])['ended_at']) === $midnight,
    'With no fix at all the midnight stamp stands');
check($worked('emp-closenofix', $PAST) === 0,
    'A fixless force-closed day must still report 0 worked seconds, got ' . $worked('emp-closenofix', $PAST));
$nofixCloseout = db_fetch_one(
    'SELECT ended_at FROM day_closeouts WHERE employee_id = ? AND work_date = ?',
    ['emp-closenofix', $PAST],
);
check(Wire::ts($nofixCloseout['ended_at']) === $midnight,
    'The fixless declaration keeps the midnight ended_at, got ' . json_encode($nofixCloseout['ended_at']));

echo "session-worked-time: all checks passed\n";
