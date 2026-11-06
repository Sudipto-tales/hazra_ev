<?php
// Run: php tests/worked-time-blast-radius.php. Uses a throwaway file-backed
// SQLite DB; never touches an application database and never reaches
// production.
//
// Rehearses tools/blast-radius-worked-seconds.sql — the read-only script the
// user is meant to paste into a production console to see how many stored
// attendance_days rows the dark-time fix disagrees with.
//
//   A  the script is strictly SELECT-only: no write keyword, no temp table,
//      no view, no session-variable assignment. Asserted by reading the file,
//      because the whole point of it is that it is safe to paste into prod.
//   B  it is three statements, and each one parses and runs.
//   C  its arithmetic IS Engine::sessionWorkedSeconds. Six seeded days are
//      stored with the OLD (pre-fix) worked_seconds, the script's
//      evidence_seconds is captured, and then Engine::rollupDay is run over
//      the same fixtures: every evidence_seconds must equal what the Engine
//      actually writes. If the two ever drift, the number the user is shown
//      before deploying would be a different number from the one they get.
//   D  the headline row's counts and percentiles are exactly the fixtures'.
//
// The MariaDB -> SQLite translation is the three substitutions documented at
// the foot of the .sql file, applied here and then checked for leftovers, so
// the rehearsal cannot silently skip a construct.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$DBSTUB = tempnam(sys_get_temp_dir(), 'blast');
$DBFILE = $DBSTUB . '.sqlite';

$_ENV['DB_TYPE']     = 'sqlite';
$_ENV['DB_DATABASE'] = $DBFILE;

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../config/migrate.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/support/bootstrap.php';

register_shutdown_function(function () use ($DBSTUB, $DBFILE) {
    foreach ([$DBSTUB, $DBFILE, $DBFILE . '-wal', $DBFILE . '-shm'] as $file) {
        if (is_file($file)) { @unlink($file); }
    }
});

function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$SCRIPT = __DIR__ . '/../tools/blast-radius-worked-seconds.sql';
check(is_file($SCRIPT), 'Missing tools/blast-radius-worked-seconds.sql');
$sql = file_get_contents($SCRIPT);

// ======================================================= A — SELECT-only
//
// Comment lines are stripped first: the header talks ABOUT update and delete,
// and a scanner that cannot tell prose from SQL would either fail here or
// teach everyone to ignore it.
$code = implode("\n", array_filter(
    array_map(static fn(string $line): string => trim($line), explode("\n", $sql)),
    static fn(string $line): bool => $line !== '' && !str_starts_with($line, '--'),
));

foreach ([
    'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'MERGE', 'TRUNCATE',
    'CREATE', 'DROP', 'ALTER', 'RENAME', 'GRANT', 'REVOKE',
    'LOCK', 'UNLOCK', 'SET ', 'CALL ', 'LOAD ', 'INTO OUTFILE', 'INTO DUMPFILE', 'INTO @',
] as $forbidden) {
    check(!str_contains(strtoupper($code), $forbidden),
        "The script must be read-only, but its SQL contains {$forbidden}");
}

$statements = array_values(array_filter(array_map('trim', explode(';', $code)), static fn($s) => $s !== ''));
check(count($statements) === 3, 'Expected 3 statements, got ' . count($statements));

foreach ($statements as $i => $statement) {
    check(str_starts_with(strtoupper($statement), 'WITH '),
        'Statement ' . ($i + 1) . ' must open with a WITH clause feeding a SELECT');
}

// ====================================================== the translation
//
// Exactly the three substitutions documented at the foot of the .sql, and
// nothing else. Applied in order; the leftover check below is what keeps this
// honest.
$translate = static function (string $statement): string {
    $iso = "'%Y-%m-%dT%H:%i:%sZ'";
    $out = preg_replace(
        '/TIMESTAMPDIFF\(SECOND, STR_TO_DATE\(([^,()]+), ' . preg_quote($iso, '/') . '\), STR_TO_DATE\(([^,()]+), ' . preg_quote($iso, '/') . '\)\)/',
        "(strftime('%s', $2) - strftime('%s', $1))",
        $statement,
    );
    $out = str_replace('GREATEST(', 'MAX(', (string) $out);
    return str_replace('CURDATE()', "date('now')", $out);
};

$translated = array_map($translate, $statements);

foreach ($translated as $i => $statement) {
    foreach (['TIMESTAMPDIFF', 'STR_TO_DATE', 'GREATEST', 'CURDATE'] as $leftover) {
        check(!str_contains($statement, $leftover),
            'Statement ' . ($i + 1) . " still contains the MariaDB-only {$leftover} after translation — "
            . 'the rehearsal would not be exercising the same logic');
    }
}

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

db_execute(
    'INSERT INTO organizations (id, name, timezone, weekend_days, created_at) VALUES (?, ?, ?, ?, ?)',
    ['org', 'Hazra EV', 'Asia/Kolkata', '[]', $now],
);

// Explicit, because the script JOINs tracking_configs and the headline numbers
// below depend on the 10-minute threshold being the one in force.
db_execute(
    'INSERT INTO tracking_configs (org_id, version, updated_at, location_interval_seconds, distance_filter_metres, min_accuracy_metres, offline_threshold_minutes)
     VALUES (?, 10, ?, 10, 10, 30, 10)',
    ['org', $now],
);

$DATE = (new DateTimeImmutable('-4 days', $ZONE))->format('Y-m-d');

$at = fn(string $localTime): string => (new DateTimeImmutable($DATE . ' ' . $localTime, $ZONE))
    ->setTimezone($UTC)->format(Wire::TS);
$plus = fn(string $ts, int $seconds): string => gmdate(Wire::TS, strtotime($ts) + $seconds);

$fixSeq = 0;

/**
 * One employee, one session, one stored attendance_days row carrying the OLD
 * worked_seconds — which is what production rows contain.
 */
$seed = function (
    string $slug,
    string $startedAt,
    ?string $endedAt,
    ?string $firstFixAt,
    int $fixCount,
    int $everySeconds,
    int $storedSeconds,
    string $storedStatus,
) use (&$fixSeq, $now, $DATE): void {
    db_execute(
        'INSERT INTO users (id, org_id, role, name, email, password_hash, active, account_status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        ["emp-{$slug}", 'org', 'employee', "Rep {$slug}", "{$slug}@test.example",
         password_hash('x', PASSWORD_BCRYPT), 'active', $now, $now],
    );

    db_execute(
        'INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, ended_at, start_lat, start_lng)
         VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)',
        ["s-{$slug}", "emp-{$slug}", "s-{$slug}-client", $DATE, $startedAt, $endedAt, 22.57, 88.36],
    );

    for ($i = 0; $i < $fixCount; $i++) {
        $ts = gmdate(Wire::TS, strtotime((string) $firstFixAt) + $i * $everySeconds);
        db_execute(
            'INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ["s-{$slug}", "emp-{$slug}", 'fix-' . (++$fixSeq),
             $ts, $ts, 22.57 + $i * 0.001, 88.36, 12, 18],
        );
    }

    db_execute(
        'INSERT INTO attendance_days
            (employee_id, work_date, status, joining_time, end_time, session_count, worked_seconds,
             distance_km, companies_visited, reports_submitted, stop_seconds, longest_stop_seconds, computed_at)
         VALUES (?, ?, ?, ?, ?, 1, ?, 0, 0, 0, 0, 0, ?)',
        ["emp-{$slug}", $DATE, $storedStatus, $startedAt, $endedAt, $storedSeconds, $now],
    );
};

// Six days. `stored` is the number the OLD rule wrote; `expected` is what the
// corrected Engine must now derive.
//                                                       stored  expected  moves
$seed('tail',  $at('09:00:00'), $at('18:00:00'), $at('09:00:00'), 43, 300, 32400, 'present'); // 12600  yes, flips
$seed('gap',   $at('09:00:00'), $at('14:05:00'), $at('09:00:00'), 13, 300, 18300, 'present'); // 18300  no
$seed('nofix', $at('09:00:00'), $at('18:00:00'), null,             0,   0, 32400, 'present'); //     0  yes, flips
$seed('b600',  $at('09:00:00'), $plus($at('11:00:00'), 600), $at('09:00:00'), 25, 300, 7800, 'partial'); // 7800 no
$seed('b601',  $at('09:00:00'), $plus($at('11:00:00'), 601), $at('09:00:00'), 25, 300, 7801, 'partial'); // 7200 yes
$seed('open',  $at('09:00:00'), null,            $at('09:00:00'),  7, 300, 32400, 'present'); //  1800  yes, flips

// emp-gap's interior hole: fixes 09:00-10:00, nothing until 13:00, then
// 13:00-14:00, ended 14:05. The gap counts, so this row must NOT move — the
// script has to agree with the Engine about that too, or a backfill would be
// quoted as touching days it leaves alone.
for ($i = 0; $i < 13; $i++) {
    $ts = gmdate(Wire::TS, strtotime($at('13:00:00')) + $i * 300);
    db_execute(
        'INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        ['s-gap', 'emp-gap', 'fix-gap-' . $i, $ts, $ts, 22.60 + $i * 0.001, 88.36, 12, 18],
    );
}

// ========================================== B/C — run it, compare to Engine

$headline = db_fetch_all($translated[0]);
check(count($headline) === 1, 'Statement 1 must return exactly one row, got ' . count($headline));
$h = $headline[0];

$worst = db_fetch_all($translated[1]);
$perEmployee = db_fetch_all($translated[2]);

// Statement 2 carries the per-day detail, which is what C cross-checks.
$predicted = [];
foreach ($worst as $row) {
    $predicted[$row['employee']] = (float) $row['evidence_hours'];
}

$expected = [
    'tail'  => 12600,
    'gap'   => 18300,
    'nofix' => 0,
    'b600'  => 7800,
    'b601'  => 7200,
    'open'  => 1800,
];

check(count($worst) === 4, 'Statement 2 should list the 4 moving days, got ' . count($worst));

$crossChecked = 0;

foreach ($expected as $slug => $seconds) {
    Ctx::impersonate(['id' => "emp-{$slug}", 'org_id' => 'org', 'role' => 'employee']);
    Engine::rollupDay("emp-{$slug}", $DATE);

    $stored = (int) db_fetch_one(
        'SELECT worked_seconds FROM attendance_days WHERE employee_id = ? AND work_date = ?',
        ["emp-{$slug}", $DATE],
    )['worked_seconds'];

    check($stored === $seconds,
        "Fixture drift: Engine::rollupDay wrote {$stored} s for {$slug}, the fixture expects {$seconds} s");

    // The script's own figure for the same day, from statement 2 when the day
    // moves, in hours to 2 dp.
    if (isset($predicted["Rep {$slug}"])) {
        check(abs($predicted["Rep {$slug}"] - $seconds / 3600) < 0.005,
            "The script predicted {$predicted["Rep {$slug}"]} h for {$slug}, the Engine wrote " . round($seconds / 3600, 2) . ' h');
        $crossChecked++;
    }
}

// The comparison above is skipped for the two days that do not move, so it
// must have fired exactly four times — otherwise a renamed column would turn
// the cross-check into a no-op that still passes.
check($crossChecked === 4, "The Engine cross-check ran {$crossChecked} times, expected 4");

// The two days that must NOT appear anywhere in the moved set.
$movedNames = array_column($worst, 'employee');
check(!in_array('Rep gap', $movedNames, true),
    'A day whose only dark time is interior must not be reported as moving');
check(!in_array('Rep b600', $movedNames, true),
    'A tail exactly at the threshold must not be reported as moving');
check(in_array('Rep tail', $movedNames, true), 'The phantom-tail day must be reported as moving');
check(in_array('Rep b601', $movedNames, true), 'A 601 s tail must be reported as moving');

// ====================================================== D — headline numbers
//
// Removed seconds, ascending: 601 (b601), 19800 (tail), 30600 (open),
// 32400 (nofix). n = 4, total 83401 s = 23.2 h.
check((int) $h['days_examined'] === 6, 'days_examined should be 6, got ' . json_encode($h['days_examined']));
check((int) $h['days_that_move'] === 4, 'days_that_move should be 4, got ' . json_encode($h['days_that_move']));
check(abs((float) $h['hours_removed_total'] - 23.2) < 0.05,
    'hours_removed_total should be 23.2, got ' . json_encode($h['hours_removed_total']));
check(abs((float) $h['median_hours_removed'] - 5.5) < 0.005,
    'median_hours_removed should be 5.5 (19800 s), got ' . json_encode($h['median_hours_removed']));
check(abs((float) $h['p90_hours_removed'] - 9.0) < 0.005,
    'p90_hours_removed should be 9.0 (32400 s), got ' . json_encode($h['p90_hours_removed']));
check(abs((float) $h['max_hours_removed'] - 9.0) < 0.005,
    'max_hours_removed should be 9.0, got ' . json_encode($h['max_hours_removed']));
check(abs((float) $h['mean_hours_removed'] - 5.79) < 0.02,
    'mean_hours_removed should be about 5.79, got ' . json_encode($h['mean_hours_removed']));
check((int) $h['days_present_to_partial'] === 3,
    'three stored-present days should fall under 4 h, got ' . json_encode($h['days_present_to_partial']));

// Worst first, and the flip spelled out per row.
check($worst[0]['employee'] === 'Rep nofix', 'The worst day should lead the list, got ' . json_encode($worst[0]['employee']));
check($worst[0]['status_after'] === 'partial', 'A stored-present day under 4 h must read partial after');
foreach ($worst as $row) {
    check($row['stored_status'] !== 'partial' || $row['status_after'] === 'partial',
        'A partial day must not be reported as changing status');
}

// Per-employee grain: one row each, four of them, same totals.
check(count($perEmployee) === 4, 'Statement 3 should list 4 employees, got ' . count($perEmployee));
check($perEmployee[0]['employee'] === 'Rep nofix', 'Statement 3 must be worst-first');
check(array_sum(array_map(static fn(array $r): int => (int) $r['days_that_move'], $perEmployee)) === 4,
    'Statement 3 days must sum to the headline count');

// And one row that is deliberately out of scope: today never appears, because
// an open session's stored figure is a snapshot of the last rollup.
db_execute(
    'INSERT INTO attendance_days
        (employee_id, work_date, status, joining_time, end_time, session_count, worked_seconds,
         distance_km, companies_visited, reports_submitted, stop_seconds, longest_stop_seconds, computed_at)
     VALUES (?, ?, ?, ?, NULL, 1, ?, 0, 0, 0, 0, 0, ?)',
    ['emp-tail', gmdate('Y-m-d'), 'present', $now, 99999, $now],
);
db_execute(
    'INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, ended_at, start_lat, start_lng)
     VALUES (?, ?, ?, ?, 2, ?, NULL, ?, ?)',
    ['s-today', 'emp-tail', 's-today-client', gmdate('Y-m-d'), $now, 22.57, 88.36],
);

$after = db_fetch_all($translated[0]);
check((int) $after[0]['days_examined'] === 6,
    "Today's row must stay out of the count, got " . json_encode($after[0]['days_examined']));

echo "worked-time-blast-radius: SELECT-only, 3 statements, and its arithmetic matches Engine::rollupDay on 6 fixtures\n";
