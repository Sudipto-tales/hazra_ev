<?php
// Run: php tests/tracking-live.php. Uses a throwaway file-backed SQLite DB so
// the forked request children can share it; never touches an application DB.
//
// Covers the read/write half of TrackingConfig v9 — everything in
// 02-API-PLAN.md §3.11 and §3.14 that is not the config object itself (that is
// tests/config-v9.php):
//
//   A  heading ingest: 0 is due north and survives; -1, NaN, >= 360 and absent
//      all normalise to NULL, never to 0
//   B  the fix tuple grows slot 6 — a SIX-slot tuple from an older client still
//      means exactly what it meant before; data stays {accepted, rejected} and
//      liveUntil rides in meta
//   C  `activity` across every branch of §3.14's precedence order, including
//      the dead-band hold case and its cold-start default
//   D  POST /tracking/watch: admin only, list form, TTL clamping vs a 422 on
//      garbage, unknown ids landing in `rejected` rather than failing the call,
//      and two admins sharing one forward-only deadline
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../config/migrate.php';
require_once __DIR__ . '/../core/ApiRequest.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../api/support/bootstrap.php';
require_once __DIR__ . '/../api/controllers/TrackingController.php';

function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare(Dialect::dml($sql)); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$DBFILE = tempnam(sys_get_temp_dir(), 'livev9') . '.sqlite';

$pdo = new PDO('sqlite:' . $DBFILE, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');
Dialect::boot($pdo, 'sqlite');

foreach (migration_files() as $file) {
    $class = migration_class($file);
    if ($class !== null && is_subclass_of($class, Migration::class)) {
        (new $class($pdo))->up();
    }
}

$now = gmdate(Wire::TS);
$date = gmdate('Y-m-d');
$ago = fn(int $seconds) => gmdate(Wire::TS, time() - $seconds);

db_execute('INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)',
    ['org', 'Hazra EV', 'UTC', $now]);

$people = [
    ['emp1', 'org', 'employee', 'Rep One', 1, 'active'],
    ['emp2', 'org', 'employee', 'Rep Two', 1, 'active'],
    ['gone', 'org', 'employee', 'Left Us', 0, 'active'],
    ['deleted', 'org', 'employee', 'Deleted', 1, 'deleted'],
    ['admin', 'org', 'admin', 'Boss', 1, 'active'],
    ['admin2', 'org', 'admin', 'Other Boss', 1, 'active'],
    ['outsider', 'other', 'employee', 'Someone Else', 1, 'active'],
];
db_execute('INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)',
    ['other', 'Another Co', 'UTC', $now]);

foreach ($people as [$id, $org, $role, $name, $active, $status]) {
    db_execute(
        'INSERT INTO users (id, org_id, role, name, email, password_hash, active, account_status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, $org, $role, $name, $id . '@test.example', password_hash('x', PASSWORD_BCRYPT), $active, $status, $now, $now],
    );
}

$openSession = function (string $employeeId, string $sessionId, int $seq, ?string $endedAt = null) use ($date, $ago) {
    db_execute(
        'INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, ended_at, start_lat, start_lng)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$sessionId, $employeeId, $sessionId . '-client', $date, $seq, $ago(7200), $endedAt, 22.57, 88.36],
    );

    return db_fetch_one('SELECT * FROM work_sessions WHERE id = ?', [$sessionId]);
};

Ctx::impersonate(['id' => 'emp1', 'org_id' => 'org', 'role' => 'employee']);
$session = $openSession('emp1', 'session-1', 1);

// ============================================================ A — heading ingest
//
// NULL is not 0: 0 is due north and has to survive, while -1 / NaN / out of
// range are all "unknown" and all store NULL. A default would render missing
// data as data on the admin map.
$headingCases = [
    ['h-valid',    123.4, 123.4],
    ['h-north',    0,     0.0],      // due north, NOT unknown
    ['h-minus1',   -1,    null],     // Android's "no bearing"
    ['h-nanstr',   'NaN', null],     // a JSON encoder that cannot express NaN
    ['h-nanfloat', NAN,   null],     // iOS, through a float column
    ['h-360',      360,   null],     // out of range: 0 <= heading < 360
    ['h-over',     540.5, null],
    ['h-blank',    null,  null],     // explicit null
];

$fixes = [];
$offset = 0;

foreach ($headingCases as [$id, $sent, ]) {
    $fix = [
        'id' => $id, 'latitude' => 22.5700 + ($offset * 0.00001), 'longitude' => 88.3600,
        'accuracy' => 10, 'speedKmh' => 0, 'recordedAt' => $ago(3600 - $offset),
    ];

    if ($sent !== null || $id === 'h-blank') {
        $fix['headingDeg'] = $sent;      // h-blank sends an explicit null
    }

    $fixes[] = $fix;
    $offset++;
}

// A fix with no headingDeg key at all — the pre-v9 object form.
$fixes[] = ['id' => 'h-absent', 'latitude' => 22.5701, 'longitude' => 88.3600,
            'accuracy' => 10, 'speedKmh' => 0, 'recordedAt' => $ago(3500)];

$result = Engine::ingest($session, $fixes);
check(array_keys($result) === ['accepted', 'rejected'], 'Engine::ingest wire shape changed');
check(count($result['rejected']) === 0, 'no heading value is ever a rejection reason: ' . json_encode($result['rejected']));

foreach ($headingCases as [$id, $sent, $expected]) {
    $row = db_fetch_one("SELECT heading_deg FROM location_fixes WHERE client_id = ?", [$id]);
    $stored = $row['heading_deg'] === null ? null : (float) $row['heading_deg'];
    check($stored === $expected, "{$id}: expected " . json_encode($expected) . ', stored ' . json_encode($stored));
}
check(db_fetch_one("SELECT heading_deg FROM location_fixes WHERE client_id = 'h-absent'")['heading_deg'] === null,
    'an absent headingDeg must store NULL');

// Present::fix carries headingDeg as float|null, and 0 stays 0.
$north = Present::fix(db_fetch_one("SELECT * FROM location_fixes WHERE client_id = 'h-north'"));
check(array_key_exists('headingDeg', $north), 'Present::fix must carry headingDeg');
check($north['headingDeg'] === 0.0, 'due north must present as 0.0, got ' . json_encode($north['headingDeg']));
$unknown = Present::fix(db_fetch_one("SELECT * FROM location_fixes WHERE client_id = 'h-minus1'"));
check($unknown['headingDeg'] === null, 'an unknown heading must present as null, never 0');
$valid = Present::fix(db_fetch_one("SELECT * FROM location_fixes WHERE client_id = 'h-valid'"));
check(is_float($valid['headingDeg']) && abs($valid['headingDeg'] - 123.4) < 0.001, 'headingDeg must present as a float');

// GET /routes slot 6 (§3.7): additive, nullable, same rules.
db_execute("DELETE FROM day_routes WHERE employee_id = 'emp1'");
Engine::buildDayRoute('emp1', $date);
$polyline = json_decode(db_fetch_one("SELECT polyline FROM day_routes WHERE employee_id = 'emp1'")['polyline'], true);
check(count($polyline[0]) === 7, 'route point tuples must carry slot 6, got ' . count($polyline[0]) . ' slots');

/** Runs one controller call in a forked child so the Envelope's exit() does not end the suite. */
function request(string $controller, string $method, array $post, array $principal, array $server = []): array {
    global $DBFILE;
    $tmp = tempnam(sys_get_temp_dir(), 'resp');
    $pid = pcntl_fork();
    if ($pid === 0) {
        $GLOBALS['pdo'] = new PDO('sqlite:' . $DBFILE, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $GLOBALS['pdo']->exec('PRAGMA foreign_keys = ON');
        Dialect::boot($GLOBALS['pdo'], 'sqlite');
        $_SERVER = array_merge($_SERVER, ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], $server);
        $_POST = $post;
        $_GET = [];
        Ctx::impersonate($principal);
        ob_start();
        register_shutdown_function(function () use ($tmp) {
            $code = http_response_code();
            $body = ob_get_contents();
            ob_end_clean();
            file_put_contents($tmp, json_encode(['status' => $code === false ? 200 : $code, 'body' => $body]));
        });
        (new $controller())->{$method}();
        exit;
    }
    pcntl_waitpid($pid, $st);
    $out = json_decode(file_get_contents($tmp), true);
    @unlink($tmp);
    $out['json'] = $out['body'] === '' ? null : json_decode($out['body'], true);
    return $out;
}

$emp1  = ['id' => 'emp1', 'org_id' => 'org', 'role' => 'employee'];
$admin = ['id' => 'admin', 'org_id' => 'org', 'role' => 'admin'];
$admin2 = ['id' => 'admin2', 'org_id' => 'org', 'role' => 'admin'];

// ======================================================== B — the fix tuple at 7
//
// [lat, lng, t, acc, spd, clientId, heading] — slot 6 is new, optional and
// nullable. A six-slot tuple is a complete, valid fix and must keep working
// untouched: no version negotiation, no deprecation.
$sixSlot = [22.5710, 88.3610, time() - 1200, 12, 4.0, 't-six'];
$sevenSlot = [22.5711, 88.3611, time() - 1100, 12, 4.0, 't-seven', 42.5];
$sevenNull = [22.5712, 88.3612, time() - 1000, 12, 4.0, 't-null', -1];

$post = request('TrackingController', 'locations',
    ['sessionId' => 'session-1', 'fixes' => [$sixSlot, $sevenSlot, $sevenNull]], $emp1);

check($post['status'] === 200, 'tuple batch should 200, got ' . $post['status'] . ' ' . $post['body']);
check(array_keys($post['json']['data']) === ['accepted', 'rejected'],
    'the data shape must stay byte-identical — the device queue depends on it');
check($post['json']['data']['accepted'] === ['t-six', 't-seven', 't-null'], 'all three tuples should be accepted');
check(array_key_exists('liveUntil', $post['json']['meta']), 'liveUntil must be in meta, not data');
check($post['json']['meta']['liveUntil'] === null, 'liveUntil is null when nobody is watching');
check($post['json']['meta']['sessionId'] === 'session-1', 'meta must still carry sessionId');
check(!array_key_exists('liveUntil', $post['json']['data']), 'liveUntil must NOT appear in data');

check(db_fetch_one("SELECT heading_deg FROM location_fixes WHERE client_id = 't-six'")['heading_deg'] === null,
    'a six-slot tuple has no heading and must store NULL');
check(abs((float) db_fetch_one("SELECT heading_deg FROM location_fixes WHERE client_id = 't-seven'")['heading_deg'] - 42.5) < 0.001,
    'slot 6 must persist');
check(db_fetch_one("SELECT heading_deg FROM location_fixes WHERE client_id = 't-null'")['heading_deg'] === null,
    'slot 6 of -1 must normalise to NULL');

// ========================================================= C — activity (§3.14)
$liveFor = function (string $employeeId) use ($date) {
    Ctx::impersonate(['id' => 'admin', 'org_id' => 'org', 'role' => 'admin']);   // drops the config cache
    return Engine::liveStatus($employeeId, $date);
};
$setFix = function (string $clientId, float $speed, int $recordedAgo, int $receivedAgo) use ($ago) {
    db_execute("DELETE FROM location_fixes WHERE employee_id = 'emp1'");
    db_execute(
        'INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        ['session-1', 'emp1', $clientId, $ago($recordedAgo), $ago($receivedAgo), 22.57, 88.36, 10, $speed],
    );
};
$chain = function (array $speeds) use ($ago) {
    // speeds newest-first; written oldest-first so recorded_at ordering matches.
    db_execute("DELETE FROM location_fixes WHERE employee_id = 'emp1'");
    $i = count($speeds);
    foreach (array_reverse($speeds) as $index => $speed) {
        db_execute(
            'INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['session-1', 'emp1', 'chain-' . $index, $ago(60 * $i), $ago(60 * $i), 22.57, 88.36, 10, $speed],
        );
        $i--;
    }
};

// Rule 1 — no session today at all.
check($liveFor('emp2')['activity'] === 'notStarted', 'no session today must be notStarted');

// Rule 6 — moving, well above the exit threshold.
$setFix('a-moving', 25.0, 30, 30);
$live = $liveFor('emp1');
check($live['activity'] === 'moving', 'a fast fix must be moving, got ' . $live['activity']);
check($live['movement'] === 'moving', 'movement must agree with activity');
check($live['status'] === 'working', 'status must keep its existing meaning beside activity');

// Rule 7 — stationary under the lower edge and no open stop yet.
$setFix('a-idle', 0.4, 30, 30);
check($liveFor('emp1')['activity'] === 'idle', 'a settled fix with no open stop must be idle');

// Rule 6/7 under the DEAD BAND — the hold case, which is the whole point of v9.
// 2.5 km/h sits between movementSpeedThresholdKmh (2) and movementExitSpeedKmh
// (3), so it carries no information: the answer is whatever the previous
// decisive fix said, re-derived by walking back through the chain.
$chain([2.5, 2.6, 25.0]);                       // newest first; the 25 is decisive
check($liveFor('emp1')['activity'] === 'moving', 'a fix inside the dead band must HOLD moving');
$chain([2.5, 2.6, 0.2]);                        // decisive the other way
check($liveFor('emp1')['activity'] === 'idle', 'a fix inside the dead band must HOLD stationary');
$chain([2.5, 2.6, 2.9]);                        // nothing decisive anywhere
check($liveFor('emp1')['activity'] === 'idle', 'with no decisive fix the cold-start default is stationary');
check($liveFor('emp1')['movement'] === 'stationary', 'the cold-start default must be stationary, not unknown');

// And the flicker the dead band exists to kill: 2.5 would have flipped to
// `moving` under the old single-threshold test (speed >= 2).
check((float) 2.5 >= 2.0, 'sanity: 2.5 is above the old single threshold');

// Rule 5 — an open stop beats both movement answers.
$chain([25.0]);
db_execute(
    'INSERT INTO stop_records (id, session_id, employee_id, work_date, arrival, lat, lng, radius_m)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    ['stop-1', 'session-1', 'emp1', $date, $ago(1800), 22.57, 88.36, 75],
);
check($liveFor('emp1')['activity'] === 'stopped', 'an open stop row must read stopped even while the last fix is fast');
db_execute("UPDATE stop_records SET departure = ? WHERE id = 'stop-1'", [$now]);
check($liveFor('emp1')['activity'] === 'moving', 'a closed stop must stop forcing stopped');

// Rule 4 — fixes arriving but stale inside: the radio works, the GPS does not.
$setFix('a-stale', 25.0, 3600, 30);
check($liveFor('emp1')['activity'] === 'locationUnavailable', 'a stale recorded_at must read locationUnavailable');

// Rule 3 before 4 — nothing has ARRIVED: that is a network statement.
$setFix('a-offline', 25.0, 3600, 3600);
check($liveFor('emp1')['activity'] === 'offline', 'an old received_at must read offline, and 3 outranks 4');

// Rule 2 before 3 — a known cause beats an inferred one.
db_execute('INSERT INTO device_health (employee_id, location_health, is_online, reported_at) VALUES (?, ?, ?, ?)',
    ['emp1', 'permission_denied', 1, $now]);
check($liveFor('emp1')['activity'] === 'locationUnavailable', 'revoked permission must beat offline (rule 2 before 3)');
db_execute("UPDATE device_health SET location_health = 'ok' WHERE employee_id = 'emp1'");

// Rule 8 / 8b — no open session: paused between sessions, ended once closed out.
$setFix('a-paused', 0.2, 60, 60);
db_execute("UPDATE work_sessions SET ended_at = ? WHERE id = 'session-1'", [$now]);
check($liveFor('emp1')['activity'] === 'paused', 'a closed session on an open day is paused');
db_execute(
    'INSERT INTO day_closeouts (id, employee_id, work_date, client_id, state, submitted_at, ended_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)',
    ['close-1', 'emp1', $date, 'close-client', 'closed_by_employee', $now, $now],
);
check($liveFor('emp1')['activity'] === 'ended', 'a closed-out day is ended');
db_execute("UPDATE day_closeouts SET reopened_at = ? WHERE id = 'close-1'", [$now]);
check($liveFor('emp1')['activity'] === 'paused', 'a reopened day goes back to paused, not a third reading');

// activity never replaces the three enums it sits beside.
$live = $liveFor('emp1');
foreach (['status', 'movement', 'locationHealth', 'activity'] as $key) {
    check(array_key_exists($key, $live), "liveStatus must still carry {$key}");
}

// Back to one open session for the watch stage.
db_execute("UPDATE work_sessions SET ended_at = NULL WHERE id = 'session-1'");
db_execute("DELETE FROM day_closeouts WHERE id = 'close-1'");

// ============================================================ D — POST /watch
$watchBody = fn(array $ids, $ttl = null) => $ttl === null
    ? ['employeeIds' => $ids]
    : ['employeeIds' => $ids, 'ttlSeconds' => $ttl];

// Admin only.
$denied = request('TrackingController', 'watch', $watchBody(['emp1']), $emp1);
check($denied['status'] === 403, 'an employee token must not be able to watch, got ' . $denied['status']);

// employeeIds is required, must be a non-empty list.
foreach ([[], ['employeeIds' => 'emp1'], ['employeeIds' => ['x' => 'emp1']]] as $body) {
    $bad = request('TrackingController', 'watch', is_array($body) && isset($body['employeeIds']) ? $body : [], $admin);
    check($bad['status'] === 422, 'a missing or non-list employeeIds must 422, got ' . $bad['status']);
    check($bad['json']['error']['field'] === 'employeeIds', 'the 422 must name employeeIds');
}

// 200 is fine, 201 is not.
$tooMany = request('TrackingController', 'watch', $watchBody(array_fill(0, 201, 'emp1')), $admin);
check($tooMany['status'] === 422, 'more than 200 employeeIds must 422');
check($tooMany['json']['error']['field'] === 'employeeIds', 'the roster cap names employeeIds');

// Garbage ttlSeconds is a 422; an out-of-range integer CLAMPS.
$garbage = request('TrackingController', 'watch', $watchBody(['emp1'], 'soon'), $admin);
check($garbage['status'] === 422, 'non-integer ttlSeconds must 422, got ' . $garbage['status']);
check($garbage['json']['error']['field'] === 'ttlSeconds', 'the 422 must name ttlSeconds');

$defaulted = request('TrackingController', 'watch', $watchBody(['emp1']), $admin);
check($defaulted['json']['data']['ttlSeconds'] === 120, 'ttlSeconds must default to 120');

$clampDown = request('TrackingController', 'watch', $watchBody(['emp1'], 5), $admin);
check($clampDown['status'] === 200, 'a small ttlSeconds must clamp, never fail — a map must always open');
check($clampDown['json']['data']['ttlSeconds'] === 30, 'ttlSeconds should clamp up to 30');
$clampUp = request('TrackingController', 'watch', $watchBody(['emp1'], 99999), $admin);
check($clampUp['json']['data']['ttlSeconds'] === 600, 'ttlSeconds should clamp down to 600');

// The clamp probes above left emp1 with a live 600 s deadline, and the deadline
// only ever moves forward — so clear the table before asserting on a fresh
// window, or the forward-only rule (correctly) keeps the longer one.
db_execute('DELETE FROM tracking_watches');

// The list form: unknown and unwatchable ids are REJECTED, they do not fail the
// call. One stale id cannot stop a map of eight from going live.
$mixed = request('TrackingController', 'watch',
    $watchBody(['emp1', 'emp2', 'nope', 'admin2', 'gone', 'deleted', 'outsider'], 120), $admin);
check($mixed['status'] === 200, 'unknown ids must not fail the call, got ' . $mixed['status']);
$watching = array_column($mixed['json']['data']['watching'], 'employeeId');
check($watching === ['emp1', 'emp2'], 'only the two watchable employees should be watching: ' . json_encode($watching));
$reasons = [];
foreach ($mixed['json']['data']['rejected'] as $row) { $reasons[$row['employeeId']] = $row['reason']; }
check(($reasons['nope'] ?? null) === 'NOT_FOUND', 'an unknown id is NOT_FOUND');
check(($reasons['outsider'] ?? null) === 'NOT_IN_ORG', 'another org\'s employee is NOT_IN_ORG');
check(($reasons['gone'] ?? null) === 'INACTIVE', 'a deactivated employee is INACTIVE');
check(($reasons['deleted'] ?? null) === 'INACTIVE', 'a deleted account is INACTIVE');
check(($reasons['admin2'] ?? null) === 'NOT_FOUND', 'an admin is not a device to watch');
check($mixed['json']['meta']['requestedCount'] === 7, 'meta.requestedCount counts what was asked for');
check($mixed['json']['meta']['watchingCount'] === 2, 'meta.watchingCount counts what is actually watched');

// Absolute ISO-8601 UTC, never a duration.
$until = $mixed['json']['data']['watchedUntil'];
check(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $until) === 1, 'watchedUntil must be absolute ISO-8601 UTC');
check($mixed['json']['data']['watching'][0]['watchingUntil'] === $until, 'each id carries the deadline, absolutely');

// One row per EMPLOYEE, not per admin.
check((int) db_fetch_one("SELECT COUNT(*) c FROM tracking_watches WHERE employee_id = 'emp1'")['c'] === 1,
    'tracking_watches must hold one row per employee however many admins watch');

// Now the device learns about it — in meta, on a batch it was going to send.
$live = request('TrackingController', 'locations',
    ['sessionId' => 'session-1', 'fixes' => [[22.5720, 88.3620, time(), 10, 3.0, 'watched-1']]], $emp1);
check($live['json']['meta']['liveUntil'] === $until, 'liveUntil must come back on the next batch, got ' . json_encode($live['json']['meta']['liveUntil']));
check(array_keys($live['json']['data']) === ['accepted', 'rejected'], 'a watch must not change the data shape');

// Two admins share one FORWARD-ONLY deadline.
$short = request('TrackingController', 'watch', $watchBody(['emp1'], 30), $admin2);
check($short['status'] === 200, 'a second admin watching must succeed');
check($short['json']['data']['watching'][0]['watchingUntil'] === $until,
    'a shorter TTL must not cut the other admin off — the deadline moves forward only');
check($short['json']['data']['watching'][0]['extended'] === false, 'a deadline that did not move is not extended');
check(db_fetch_one("SELECT watching_until FROM tracking_watches WHERE employee_id = 'emp1'")['watching_until'] === $until,
    'the stored deadline must not regress');

$longer = request('TrackingController', 'watch', $watchBody(['emp1'], 600), $admin2);
check($longer['json']['data']['watching'][0]['extended'] === true, 'moving an active deadline later IS extended');
check($longer['json']['data']['watching'][0]['watchingUntil'] > $until, 'the extended deadline must be the later one');
check($longer['json']['data']['watching'][0]['watchingUntil'] === $longer['json']['data']['watchedUntil'],
    'when the deadline moves, the per-id value is this call\'s');

// An expired watch is simply never true again: expiry is passive, nothing
// deletes it, and liveUntil reads null.
db_execute("UPDATE tracking_watches SET watching_until = ? WHERE employee_id = 'emp1'", [$ago(60)]);
$expired = request('TrackingController', 'locations',
    ['sessionId' => 'session-1', 'fixes' => [[22.5730, 88.3630, time(), 10, 3.0, 'expired-1']]], $emp1);
check($expired['json']['meta']['liveUntil'] === null, 'a lapsed watch must read as not watched');
check((int) db_fetch_one("SELECT COUNT(*) c FROM tracking_watches")['c'] === 2,
    'expiry is passive — the row stays, it is just never true again');

// A re-watch after expiry is a fresh window, not an extension of a dead one.
$revived = request('TrackingController', 'watch', $watchBody(['emp1'], 120), $admin);
check($revived['json']['data']['watching'][0]['extended'] === false, 'reviving a lapsed watch is not an extension');

@unlink($DBFILE);
echo "tracking-live: all checks passed\n";
