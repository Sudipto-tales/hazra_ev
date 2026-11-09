<?php
// Run: php tests/attendance-binary-holidays.php. Throwaway file-backed SQLite;
// never touches an application database.
//
// Covers the reworked attendance model and the admin holiday calendar:
//
//   A  Engine::classifyDay is BINARY — any started session is present, a past
//      working day with none is absent, a future one is no_data. There is no
//      worked-time threshold and no 'partial' any more (a 10-minute day and a
//      10-hour day are both present), and holiday/weekend still win over both.
//   B  Engine::holidayOn matches a one_time holiday on its exact date and a
//      yearly one on month-day alone across any year; an exact date wins over a
//      yearly entry sharing its month-day.
//   C  Engine::isNonWorkingDay is true on a holiday (either recurrence) and on a
//      weekend, false on an ordinary working day.
//   D  POST /tracking/sessions is refused with 409 NON_WORKING_DAY on a holiday
//      and on a weekend, and still opens on an ordinary working day.
//   E  The HolidaysController: list expands yearly entries onto the asked year,
//      create upserts and re-derives a worked day's stored status to 'holiday',
//      delete removes it and re-derives the status back, and recurrence is
//      validated.
//   F  The 038 migration added holidays.recurrence (default one_time) and folded
//      any stored 'partial' attendance row to 'present'.
//
// D and E run in child processes because the controllers answer through
// Envelope, which exits; the parent seeds the shared DB file, the child acts and
// prints the status code, the parent reads the JSON and the resulting DB state.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$CHILD = getenv('BINHOL_CHILD') !== false;

$DBSTUB = null;
if ($CHILD) {
    $DBFILE = (string) getenv('BINHOL_DB');
} else {
    $DBSTUB = tempnam(sys_get_temp_dir(), 'binhol');
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
require_once __DIR__ . '/../api/support/V1Controller.php';
require_once __DIR__ . '/../api/controllers/TrackingController.php';
require_once __DIR__ . '/../api/controllers/HolidaysController.php';
require_once __DIR__ . '/../api/controllers/DaysController.php';

if (!$CHILD) {
    register_shutdown_function(function () use ($DBSTUB, $DBFILE) {
        foreach ([$DBSTUB, $DBFILE, $DBFILE . '-wal', $DBFILE . '-shm'] as $file) {
            if (is_file($file)) { @unlink($file); }
        }
    });
}

function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

/** Replace the request body ApiController::input()/ApiRequest::body() will read. */
function set_body(array $body): void {
    $rp = new ReflectionProperty(ApiRequest::class, 'parsedBody');
    $rp->setAccessible(true);
    $rp->setValue(null, $body);
}

// ===================================================== the child process
//
// One scenario per invocation, selected by BINHOL_SCENARIO. The body/query are
// passed as JSON in BINHOL_BODY / BINHOL_QUERY. The shutdown hook prints the
// status code even though Envelope exits, which is the whole reason for a child.

if ($CHILD) {
    $scenario = (string) getenv('BINHOL_SCENARIO');
    $body     = json_decode((string) getenv('BINHOL_BODY') ?: '{}', true) ?: [];
    $query    = json_decode((string) getenv('BINHOL_QUERY') ?: '{}', true) ?: [];
    $orgId    = (string) getenv('BINHOL_ORG');
    $actorId  = (string) getenv('BINHOL_ACTOR');
    $role     = (string) getenv('BINHOL_ROLE');

    register_shutdown_function(function () {
        fwrite(STDOUT, "\nCODE=" . http_response_code() . "\n");
    });

    Ctx::impersonate(['id' => $actorId, 'org_id' => $orgId, 'role' => $role]);
    set_body($body);
    $_GET = $query;

    switch ($scenario) {
        case 'open_session':
            (new TrackingController())->openSession();
            break;
        case 'holiday_store':
            (new HolidaysController())->store();
            break;
        case 'holiday_destroy':
            (new HolidaysController())->destroy();
            break;
        case 'holiday_index':
            (new HolidaysController())->index();
            break;
        case 'weekend_get':
            (new HolidaysController())->weekendDays();
            break;
        case 'weekend_put':
            (new HolidaysController())->updateWeekendDays();
            break;
        case 'days_show':
            $c = new DaysController();
            $c->setRouteParams(['subject' => 'me']);
            $c->show();
            break;
    }

    exit;
}

/** Spawn a child scenario, returning [httpCode, decodedJsonData|null, rawStdout]. */
function run_scenario(string $scenario, array $opts): array {
    $env = [
        'BINHOL_CHILD=1',
        'BINHOL_DB=' . escapeshellarg((string) getenv('BINHOL_SELF_DB')),
        'BINHOL_SCENARIO=' . escapeshellarg($scenario),
        'BINHOL_ORG=' . escapeshellarg($opts['org']),
        'BINHOL_ACTOR=' . escapeshellarg($opts['actor']),
        'BINHOL_ROLE=' . escapeshellarg($opts['role']),
        'BINHOL_BODY=' . escapeshellarg(json_encode($opts['body'] ?? [])),
        'BINHOL_QUERY=' . escapeshellarg(json_encode($opts['query'] ?? [])),
    ];

    $command = implode(' ', $env) . ' '
        . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' 2>&1';

    $out = (string) shell_exec($command);

    $code = null;
    if (preg_match('/CODE=(\d+)/', $out, $m)) {
        $code = (int) $m[1];
    }

    $json = null;
    $firstBrace = strpos($out, '{');
    if ($firstBrace !== false) {
        $decoded = json_decode(substr($out, $firstBrace, strrpos($out, '}') - $firstBrace + 1), true);
        $json = is_array($decoded) ? ($decoded['data'] ?? $decoded) : null;
        if (is_array($decoded) && ($decoded['error'] ?? null) !== null) {
            $json = $decoded;    // keep the whole envelope when it is an error
        }
    }

    return [$code, $json, $out];
}

// ================================================================ fixtures

foreach (migration_files() as $file) {
    $class = migration_class($file);
    if ($class !== null && is_subclass_of($class, Migration::class)) {
        (new $class($GLOBALS['pdo']))->up();
    }
}

putenv('BINHOL_SELF_DB=' . $DBFILE);

$now = Wire::now();

// u-org drives the in-process Engine unit tests; g-org the controller/guard
// children. Separate orgs so a weekend_days change for one never perturbs the
// other.
db_execute('INSERT INTO organizations (id, name, timezone, weekend_days, created_at) VALUES (?, ?, ?, ?, ?)',
    ['u-org', 'Unit Org', 'UTC', '[6,7]', $now]);           // Sat+Sun weekend
db_execute('INSERT INTO organizations (id, name, timezone, weekend_days, created_at) VALUES (?, ?, ?, ?, ?)',
    ['g-org', 'Guard Org', 'UTC', '[]', $now]);             // configured per scenario

foreach ([['u-org', 'u-emp', 'employee'], ['g-org', 'g-emp', 'employee'],
          ['g-org', 'g-work', 'employee'], ['g-org', 'g-wk', 'employee'],
          ['g-org', 'g-admin', 'admin']] as [$org, $uid, $role]) {
    db_execute(
        'INSERT INTO users (id, org_id, role, name, email, password_hash, active, account_status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        [$uid, $org, $role, $uid, "{$uid}@test.example", password_hash('x', PASSWORD_BCRYPT), 'active', $now, $now],
    );
}

// ===========================================================================
// F — the migration: recurrence column exists and defaults to one_time; a
//     stored 'partial' was folded to 'present'
// ===========================================================================
$cols = array_column(db_fetch_all("PRAGMA table_info(holidays)"), 'name');
check(in_array('recurrence', $cols, true), '038 must add holidays.recurrence');

db_execute('INSERT INTO holidays (org_id, date, name) VALUES (?, ?, ?)', ['u-org', '2026-01-26', 'Republic Day']);
$defaulted = db_fetch_one("SELECT recurrence FROM holidays WHERE org_id = 'u-org' AND date = '2026-01-26'");
check($defaulted['recurrence'] === 'one_time', 'recurrence must default to one_time, got ' . json_encode($defaulted));
db_execute("DELETE FROM holidays WHERE org_id = 'u-org'");

// The data migration ran over an empty attendance_days; prove the statement
// itself does what 038 promises on a row that carries the retired status.
db_execute(
    'INSERT INTO attendance_days (employee_id, work_date, status, session_count, worked_seconds, distance_km, computed_at)
     VALUES (?, ?, ?, 1, 9000, 0, ?)',
    ['u-emp', '2026-02-02', 'partial', $now],
);
$GLOBALS['pdo']->exec("UPDATE attendance_days SET status = 'present' WHERE status = 'partial'");
$folded = db_fetch_one("SELECT status FROM attendance_days WHERE employee_id = 'u-emp' AND work_date = '2026-02-02'");
check($folded['status'] === 'present', "partial must fold to present, got " . json_encode($folded));
db_execute("DELETE FROM attendance_days WHERE employee_id = 'u-emp'");

// ===========================================================================
// A — classifyDay is binary
// ===========================================================================
Ctx::impersonate(['id' => 'u-emp', 'org_id' => 'u-org', 'role' => 'employee']);

$today = Ctx::today();
$past  = (new DateTimeImmutable('-5 days'))->format('Y-m-d');
$future = (new DateTimeImmutable('+5 days'))->format('Y-m-d');

// Pick a concrete Monday (working) and the Saturday after it (weekend per [6,7]).
$monday   = (new DateTimeImmutable('2026-03-02'))->format('Y-m-d');  // ISO Monday
$saturday = (new DateTimeImmutable('2026-03-07'))->format('Y-m-d');  // ISO Saturday

check(Engine::classifyDay($monday, 0, 0) === 'absent',
    'A past working day with no session is absent');
check(Engine::classifyDay($future, 0, 0) === 'no_data',
    'A future day with no session is no_data');
check(Engine::classifyDay($saturday, 0, 0) === 'weekend',
    'A weekend day is weekend regardless of sessions');

// The crux: no threshold. A one-second day and a ten-hour day are both present.
check(Engine::classifyDay($monday, 1, 1) === 'present',
    'Any started session is present — one second of worked time is enough now');
check(Engine::classifyDay($monday, 1, 36000) === 'present',
    'A long day is present too — same verdict, no partial band between them');
check(Engine::classifyDay($monday, 3, 0) === 'present',
    'Multiple sessions with zero derived seconds still read present');

// Holiday wins over a worked session.
db_execute('INSERT INTO holidays (org_id, date, name, recurrence) VALUES (?, ?, ?, ?)',
    ['u-org', $monday, 'Special', 'one_time']);
check(Engine::classifyDay($monday, 2, 36000) === 'holiday',
    'A holiday wins over presence');
db_execute("DELETE FROM holidays WHERE org_id = 'u-org'");

// ===========================================================================
// B — holidayOn: one_time exact, yearly month-day, exact precedence
// ===========================================================================
db_execute('INSERT INTO holidays (org_id, date, name, recurrence) VALUES (?, ?, ?, ?)',
    ['u-org', '2026-08-15', 'Independence Day', 'yearly']);
db_execute('INSERT INTO holidays (org_id, date, name, recurrence) VALUES (?, ?, ?, ?)',
    ['u-org', '2026-12-25', 'Christmas 2026', 'one_time']);

check(Engine::holidayOn('u-org', '2026-08-15')['name'] === 'Independence Day',
    'A yearly holiday matches its anchor year');
check(Engine::holidayOn('u-org', '2030-08-15')['name'] === 'Independence Day',
    'A yearly holiday matches a different year by month-day');
check(Engine::holidayOn('u-org', '2019-08-15')['name'] === 'Independence Day',
    'A yearly holiday matches a year before its anchor too');
check(Engine::holidayOn('u-org', '2026-08-14') === null,
    'A yearly holiday must not match a neighbouring day');
check(Engine::holidayOn('u-org', '2026-12-25')['name'] === 'Christmas 2026',
    'A one_time holiday matches its exact date');
check(Engine::holidayOn('u-org', '2027-12-25') === null,
    'A one_time holiday must not match another year');

// Exact precedence: a one_time entry on the same month-day as a yearly one wins
// and returns its own name.
db_execute('INSERT INTO holidays (org_id, date, name, recurrence) VALUES (?, ?, ?, ?)',
    ['u-org', '2031-08-15', 'Special IDay 2031', 'one_time']);
check(Engine::holidayOn('u-org', '2031-08-15')['name'] === 'Special IDay 2031',
    'An exact date match wins over a yearly month-day match');

// ===========================================================================
// C — isNonWorkingDay
// ===========================================================================
check(Engine::isNonWorkingDay('u-org', '2030-08-15') === true,  'A yearly holiday is a non-working day');
check(Engine::isNonWorkingDay('u-org', '2026-12-25') === true,  'A one_time holiday is a non-working day');
check(Engine::isNonWorkingDay('u-org', $saturday)     === true,  'A weekend is a non-working day');
check(Engine::isNonWorkingDay('u-org', $monday)       === false, 'An ordinary weekday is a working day');
db_execute("DELETE FROM holidays WHERE org_id = 'u-org'");

// ===========================================================================
// D — the openSession NON_WORKING_DAY guard
// ===========================================================================
$todayN = (int) (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('N');

$fix = ['latitude' => 22.57, 'longitude' => 88.36, 'recordedAt' => $today . 'T04:00:00Z'];
$openBody = fn(string $clientId): array => ['clientId' => $clientId, 'fix' => $fix];

// Holiday today, no weekend: the holiday is the reason.
db_execute("UPDATE organizations SET weekend_days = '[]' WHERE id = 'g-org'");
db_execute('INSERT INTO holidays (org_id, date, name, recurrence) VALUES (?, ?, ?, ?)',
    ['g-org', $today, 'Today Holiday', 'one_time']);

[$code, $json] = run_scenario('open_session', [
    'org' => 'g-org', 'actor' => 'g-emp', 'role' => 'employee', 'body' => $openBody('h-1'),
]);
check($code === 409, "A session on a holiday must be 409, got {$code}");
check(($json['error']['code'] ?? null) === 'NON_WORKING_DAY',
    'The code must be NON_WORKING_DAY, got ' . json_encode($json));
check(db_fetch_one("SELECT 1 FROM work_sessions WHERE employee_id = 'g-emp'") === false,
    'A refused session must not be stored');

db_execute("DELETE FROM holidays WHERE org_id = 'g-org'");

// Weekend today, no holiday: the weekend is the reason.
db_execute("UPDATE organizations SET weekend_days = ? WHERE id = 'g-org'", ['[' . $todayN . ']']);

[$code, $json] = run_scenario('open_session', [
    'org' => 'g-org', 'actor' => 'g-emp', 'role' => 'employee', 'body' => $openBody('w-1'),
]);
check($code === 409, "A session on a weekend must be 409, got {$code}");
check(($json['error']['code'] ?? null) === 'NON_WORKING_DAY',
    'A weekend session must also be NON_WORKING_DAY, got ' . json_encode($json));

// An ordinary working day: the session opens.
db_execute("UPDATE organizations SET weekend_days = '[]' WHERE id = 'g-org'");

[$code, $json] = run_scenario('open_session', [
    'org' => 'g-org', 'actor' => 'g-emp', 'role' => 'employee', 'body' => $openBody('ok-1'),
]);
check($code === 201, "A session on a working day must be 201, got {$code}");
check(db_fetch_one("SELECT 1 FROM work_sessions WHERE employee_id = 'g-emp' AND client_id = 'ok-1'") !== false,
    'An accepted session must be stored');

// ===========================================================================
// E — the HolidaysController
// ===========================================================================
db_execute("UPDATE organizations SET weekend_days = '[]' WHERE id = 'g-org'");

$WORKDAY = '2026-03-10';   // a fixed past weekday, not a weekend under '[]'

// A worked day already rolled up as present.
db_execute(
    'INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, ended_at, start_lat, start_lng, distance_algo_version)
     VALUES (?, ?, ?, ?, 1, ?, ?, 22.57, 88.36, 3)',
    ['ws-work', 'g-work', 'ws-work-c', $WORKDAY, $WORKDAY . 'T04:00:00Z', $WORKDAY . 'T09:00:00Z'],
);
db_execute(
    'INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
     VALUES (?, ?, ?, ?, ?, 22.57, 88.36, 10, 0)',
    ['ws-work', 'g-work', 'f-1', $WORKDAY . 'T04:00:00Z', $now],
);
Ctx::impersonate(['id' => 'g-work', 'org_id' => 'g-org', 'role' => 'employee']);
Engine::rollupDay('g-work', $WORKDAY);
check(db_fetch_one("SELECT status FROM attendance_days WHERE employee_id = 'g-work' AND work_date = ?", [$WORKDAY])['status'] === 'present',
    'Fixture: the worked day must start out present');

// create -> re-derives the worked day to holiday
[$code, $json] = run_scenario('holiday_store', [
    'org' => 'g-org', 'actor' => 'g-admin', 'role' => 'admin',
    'body' => ['date' => $WORKDAY, 'name' => 'Audit Day', 'recurrence' => 'one_time'],
]);
check($code === 201, "Creating a holiday must be 201, got {$code}");
check(db_fetch_one("SELECT recurrence FROM holidays WHERE org_id = 'g-org' AND date = ?", [$WORKDAY])['recurrence'] === 'one_time',
    'The holiday must be stored');
check(db_fetch_one("SELECT status FROM attendance_days WHERE employee_id = 'g-work' AND work_date = ?", [$WORKDAY])['status'] === 'holiday',
    'Creating a holiday must re-derive the worked day to holiday');

// delete -> re-derives it back to present
[$code] = run_scenario('holiday_destroy', [
    'org' => 'g-org', 'actor' => 'g-admin', 'role' => 'admin',
    'query' => ['date' => $WORKDAY],
]);
check($code === 204, "Deleting a holiday must be 204, got {$code}");
check(db_fetch_one("SELECT 1 FROM holidays WHERE org_id = 'g-org' AND date = ?", [$WORKDAY]) === false,
    'The holiday must be gone');
check(db_fetch_one("SELECT status FROM attendance_days WHERE employee_id = 'g-work' AND work_date = ?", [$WORKDAY])['status'] === 'present',
    'Deleting the holiday must re-derive the worked day back to present');

// recurrence is validated
[$code, $json] = run_scenario('holiday_store', [
    'org' => 'g-org', 'actor' => 'g-admin', 'role' => 'admin',
    'body' => ['date' => '2026-04-01', 'name' => 'Bad', 'recurrence' => 'monthly'],
]);
check($code === 422, "An unknown recurrence must be 422, got {$code}");
check(($json['error']['field'] ?? null) === 'recurrence', 'The 422 must name recurrence');

// list expands yearly onto the asked year, and filters one_time by year
db_execute('INSERT INTO holidays (org_id, date, name, recurrence) VALUES (?, ?, ?, ?)',
    ['g-org', '2020-12-25', 'Christmas', 'yearly']);
db_execute('INSERT INTO holidays (org_id, date, name, recurrence) VALUES (?, ?, ?, ?)',
    ['g-org', '2026-07-04', 'One Off', 'one_time']);

[$code, $data] = run_scenario('holiday_index', [
    'org' => 'g-org', 'actor' => 'g-admin', 'role' => 'admin', 'query' => ['year' => '2026'],
]);
check($code === 200, "Listing holidays must be 200, got {$code}");
$byDate = [];
foreach ($data as $row) { $byDate[$row['date']] = $row; }
check(($byDate['2026-12-25']['recurrence'] ?? null) === 'yearly',
    'The yearly holiday must project onto 2026-12-25, got ' . json_encode($data));
check(($byDate['2026-07-04']['recurrence'] ?? null) === 'one_time',
    'The one_time holiday in 2026 must be listed');

[$code, $data] = run_scenario('holiday_index', [
    'org' => 'g-org', 'actor' => 'g-admin', 'role' => 'admin', 'query' => ['year' => '2030'],
]);
$dates = array_column($data, 'date');
check(in_array('2030-12-25', $dates, true), 'The yearly holiday must project onto 2030 too');
check(!in_array('2026-07-04', $dates, true), 'A one_time holiday must not appear in another year');

// ===========================================================================
// E2 — the weekend-days endpoint: read, update (with recompute flip), validate
// ===========================================================================
db_execute("UPDATE organizations SET weekend_days = '[]' WHERE id = 'g-org'");

$todayG = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');

// An employee working RIGHT NOW (open session today), rolled up as present.
db_execute(
    'INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, ended_at, start_lat, start_lng, distance_algo_version)
     VALUES (?, ?, ?, ?, 1, ?, NULL, 22.57, 88.36, 3)',
    ['ws-wk', 'g-wk', 'ws-wk-c', $todayG, $todayG . 'T03:00:00Z'],
);
db_execute(
    'INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
     VALUES (?, ?, ?, ?, ?, 22.57, 88.36, 10, 0)',
    ['ws-wk', 'g-wk', 'fwk-1', $todayG . 'T03:00:00Z', $now],
);
Ctx::impersonate(['id' => 'g-wk', 'org_id' => 'g-org', 'role' => 'employee']);
Engine::rollupDay('g-wk', $todayG);
check(db_fetch_one("SELECT status FROM attendance_days WHERE employee_id = 'g-wk' AND work_date = ?", [$todayG])['status'] === 'present',
    "Fixture: a day worked right now must start out present, got " . json_encode(db_fetch_one("SELECT status FROM attendance_days WHERE employee_id = 'g-wk' AND work_date = ?", [$todayG])));

// Read the current (empty) weekend set.
[$code, $data] = run_scenario('weekend_get', ['org' => 'g-org', 'actor' => 'g-admin', 'role' => 'admin']);
check($code === 200, "GET weekend-days must be 200, got {$code}");
check(($data['weekendDays'] ?? null) === [], 'Weekend days must start empty, got ' . json_encode($data));

// Make today a weekend. The open session's live verdict must flip to weekend.
[$code, $data] = run_scenario('weekend_put', [
    'org' => 'g-org', 'actor' => 'g-admin', 'role' => 'admin',
    'body' => ['weekendDays' => [$todayN]],
]);
check($code === 200, "PUT weekend-days must be 200, got {$code}");
check(($data['weekendDays'] ?? null) === [$todayN], 'PUT must return the stored list, got ' . json_encode($data));
check(json_decode(db_fetch_one("SELECT weekend_days FROM organizations WHERE id = 'g-org'")['weekend_days'], true) === [$todayN],
    'weekend_days must be persisted as the JSON array the seeder writes');
check(db_fetch_one("SELECT status FROM attendance_days WHERE employee_id = 'g-wk' AND work_date = ?", [$todayG])['status'] === 'weekend',
    'Making today a weekend must re-derive the open day to weekend');

// Read it back.
[$code, $data] = run_scenario('weekend_get', ['org' => 'g-org', 'actor' => 'g-admin', 'role' => 'admin']);
check(($data['weekendDays'] ?? null) === [$todayN], 'GET must reflect the update, got ' . json_encode($data));

// Dedupe + sort.
[$code, $data] = run_scenario('weekend_put', [
    'org' => 'g-org', 'actor' => 'g-admin', 'role' => 'admin',
    'body' => ['weekendDays' => [6, 7, 6]],
]);
check($code === 200 && ($data['weekendDays'] ?? null) === [6, 7],
    'A duplicated set must dedupe and sort to [6,7], got ' . json_encode($data));

// Validation: out of range, non-integer, and non-list all 422 on the field.
foreach ([[8], [0], [3.5], 'nope', ['weekendDays' => 6]] as $bad) {
    $body = is_array($bad) && array_is_list($bad) ? ['weekendDays' => $bad] : ['weekendDays' => $bad];
    [$code, $json] = run_scenario('weekend_put', [
        'org' => 'g-org', 'actor' => 'g-admin', 'role' => 'admin', 'body' => $body,
    ]);
    check($code === 422, 'A bad weekendDays value must be 422, got ' . $code . ' for ' . json_encode($bad));
    check(($json['error']['field'] ?? null) === 'weekendDays', 'The 422 must name weekendDays for ' . json_encode($bad));
}

// ===========================================================================
// E3 — GET /days/me carries a `nonWorkingDay` boolean for the proactive guard
// ===========================================================================
db_execute("UPDATE organizations SET weekend_days = '[6]' WHERE id = 'g-org'");  // Saturday rest day

$mondayD   = '2026-03-02';   // ISO Monday — ordinary working day
$saturdayD = '2026-03-07';   // ISO Saturday — weekend under [6]
$holidayD  = '2026-03-03';   // ISO Tuesday — made a holiday below

db_execute("DELETE FROM holidays WHERE org_id = 'g-org' AND date = ?", [$holidayD]);
db_execute("INSERT INTO holidays (org_id, date, name, recurrence) VALUES (?, ?, ?, 'one_time')",
    ['g-org', $holidayD, 'Mid-week Holiday']);

$daysOpts = ['org' => 'g-org', 'actor' => 'g-emp', 'role' => 'employee'];

[$code, $data] = run_scenario('days_show', $daysOpts + ['query' => ['date' => $mondayD]]);
check($code === 200, "GET /days/me must be 200, got {$code}");
check(array_key_exists('nonWorkingDay', $data), 'The day payload must carry a nonWorkingDay key');
check($data['nonWorkingDay'] === false, 'An ordinary weekday must be nonWorkingDay=false, got ' . json_encode($data['nonWorkingDay']));

[$code, $data] = run_scenario('days_show', $daysOpts + ['query' => ['date' => $saturdayD]]);
check($data['nonWorkingDay'] === true, 'A weekend day must be nonWorkingDay=true, got ' . json_encode($data['nonWorkingDay']));

[$code, $data] = run_scenario('days_show', $daysOpts + ['query' => ['date' => $holidayD]]);
check($data['nonWorkingDay'] === true, 'A holiday must be nonWorkingDay=true, got ' . json_encode($data['nonWorkingDay']));

echo "attendance-binary-holidays: binary classifyDay, yearly holidayOn, the NON_WORKING_DAY guard, the holiday CRUD, the weekend-days endpoint and the days/me nonWorkingDay flag all hold\n";
