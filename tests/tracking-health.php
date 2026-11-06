<?php
// Run: php tests/tracking-health.php. Uses a throwaway file-backed SQLite DB so
// the forked request children can share it; never touches an application DB.
//
// Covers POST /tracking/health and the one timeline row it may write:
//
//   A  poor_accuracy is accepted and stored, and writes NO activity row —
//      weak GPS is a property of the fixes that DID arrive, not an outage
//   B  every genuine outage writes exactly one row, titled "Offline", with a
//      plain-language reason rather than a camelCase enum
//   C  one row per transition: a repeat of the same value is silent
//   D  coming out of an outage writes a "Back online" row that is NOT an alert
//      and carries how long the gap was, so a day with a hole in it can be read
//   E  a timeline rebuild preserves both kinds (they are not derivable)
//   F  an unknown locationHealth is still a 422
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

$DBFILE = tempnam(sys_get_temp_dir(), 'health') . '.sqlite';

$pdo = new PDO('sqlite:' . $DBFILE, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');
Dialect::boot($pdo, 'sqlite');

foreach (migration_files() as $file) {
    $class = migration_class($file);
    if ($class !== null && is_subclass_of($class, Migration::class)) {
        (new $class($pdo))->up();
    }
}

$now  = gmdate(Wire::TS);
$date = gmdate('Y-m-d');
$ago  = fn(int $seconds) => gmdate(Wire::TS, time() - $seconds);

db_execute('INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)',
    ['org', 'Hazra EV', 'UTC', $now]);

foreach ([['emp1', 'employee', 'Rep One'], ['admin', 'admin', 'Boss']] as [$id, $role, $name]) {
    db_execute(
        'INSERT INTO users (id, org_id, role, name, email, password_hash, active, account_status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        [$id, 'org', $role, $name, $id . '@test.example', password_hash('x', PASSWORD_BCRYPT), 'active', $now, $now],
    );
}

db_execute(
    'INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, ended_at, start_lat, start_lng)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
    ['session-1', 'emp1', 'session-1-client', $date, 1, $ago(7200), null, 22.57, 88.36],
);

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

$ping = fn(string $health) => request('TrackingController', 'health', ['locationHealth' => $health], $emp1);
$rows = fn() => db_fetch_all("SELECT * FROM activity_events WHERE employee_id = 'emp1' ORDER BY occurred_at, title");
$stored = fn() => db_fetch_one("SELECT location_health FROM device_health WHERE employee_id = 'emp1'")['location_health'] ?? null;

Ctx::impersonate($emp1);

// ===================================================== A — weak GPS is not news
//
// It is accepted (an older app still sends it, and a 422 on a fire-and-forget
// ping is a pointless error in that app's logs) and it is still stored, so the
// value is there for anyone who wants it. What it must NOT do is put a red row
// on the employee's day: an approximate fix is still a recorded position.
$weak = $ping('poorAccuracy');
check($weak['status'] === 204, 'poorAccuracy must still be accepted, got ' . $weak['status']);
check($stored() === 'poor_accuracy', 'poorAccuracy must still be stored, got ' . json_encode($stored()));
check($rows() === [], 'weak GPS must write no activity row, got ' . json_encode($rows()));

// A run of them, from ok and back, still writes nothing.
foreach (['ok', 'poorAccuracy', 'ok', 'poorAccuracy'] as $value) {
    check($ping($value)['status'] === 204, "{$value} must be accepted");
}
check($rows() === [], 'no number of accuracy transitions may write a row');

// ======================================================= B — a real outage does
//
// Title is the word the record needs ("Offline"); the subtitle is the reason in
// language an employee reads, not the wire enum. The old row said "Tracking
// issue" / "serviceDisabled", which is the API talking to itself.
$reasons = [
    'serviceDisabled'         => 'Device location was switched off',
    'permissionDenied'        => 'Location permission was denied',
    'permissionDeniedForever' => 'Location permission is blocked in system settings',
    'backgroundDenied'        => 'Background location is not allowed',
    'noInternet'              => 'No internet connection, so fixes are queued on the device',
];

foreach ($reasons as $wire => $reason) {
    db_execute("DELETE FROM activity_events WHERE employee_id = 'emp1'");
    db_execute("DELETE FROM device_health WHERE employee_id = 'emp1'");

    check($ping($wire)['status'] === 204, "{$wire} must be accepted");

    $written = $rows();
    check(count($written) === 1, "{$wire} must write exactly one row, got " . count($written));
    check($written[0]['type'] === 'tracking_issue', "{$wire} row must keep the alert type");
    check((int) $written[0]['is_alert'] === 1, "{$wire} row must be an alert");
    check($written[0]['title'] === 'Offline', "{$wire} title must be Offline, got " . json_encode($written[0]['title']));
    check($written[0]['subtitle'] === $reason, "{$wire} subtitle must read '{$reason}', got " . json_encode($written[0]['subtitle']));
    check(!str_contains($written[0]['subtitle'], $wire), "{$wire} subtitle must not leak the wire enum");
}

// ======================================================== C — one row per change
db_execute("DELETE FROM activity_events WHERE employee_id = 'emp1'");
db_execute("DELETE FROM device_health WHERE employee_id = 'emp1'");

$ping('serviceDisabled');
$ping('serviceDisabled');
$ping('serviceDisabled');
check(count($rows()) === 1, 'a repeated outage value must not stack rows, got ' . count($rows()));

$ping('ok');
check(count($rows()) === 2, 'coming back from an outage must write its own row');
check($stored() === 'ok', 'ok must be stored');

$ping('ok');
$ping('ok');
check(count($rows()) === 2, 'already being ok is not a recovery');

$ping('noInternet');
check(count($rows()) === 3, 'a different outage after a recovery is a new row');

// Weak GPS ENDS an outage — a device reporting weak GPS is a device reporting —
// but never starts one.
$ping('poorAccuracy');
$recovered = $rows();
check(count($recovered) === 4, 'poorAccuracy must close an outage, got ' . count($recovered));
// By title, not by position: these rows land in the same second, so the order
// they come back in says nothing.
check(count(array_filter($recovered, fn(array $r) => $r['title'] === 'Back online')) === 2,
    'each of the two outages must have been closed by a recovery row');
$ping('ok');
check(count($rows()) === 4, 'ok after poorAccuracy is not a second recovery');

// ================================================= D — the row that closes it
db_execute("DELETE FROM activity_events WHERE employee_id = 'emp1'");
db_execute("DELETE FROM device_health WHERE employee_id = 'emp1'");

$ping('serviceDisabled');

// Age the Offline row so the gap is a known length. The endpoint measures from
// that row, not from device_health, which this very request overwrites.
db_execute(
    "UPDATE activity_events SET occurred_at = ? WHERE employee_id = 'emp1' AND title = 'Offline'",
    [gmdate(Wire::TS, time() - 14 * 60)],
);

$ping('ok');
$pair = $rows();
check(count($pair) === 2, 'an outage and its recovery are two rows, got ' . count($pair));

$back = array_values(array_filter($pair, fn(array $r) => $r['title'] === 'Back online'))[0] ?? null;
check($back !== null, 'the recovery row must be titled Back online');
check((int) $back['is_alert'] === 0, 'a recovery is good news and must NOT be an alert');
check($back['type'] === 'tracking_issue', 'the recovery shares the preserved row type');
check($back['subtitle'] === 'Tracking was down for 14 minutes',
    'the recovery must carry the gap length, got ' . json_encode($back['subtitle']));

// The wording bands, driven off the same aged row.
$gapWording = function (int $seconds) use ($ping, $rows) {
    db_execute("DELETE FROM activity_events WHERE employee_id = 'emp1'");
    db_execute("DELETE FROM device_health WHERE employee_id = 'emp1'");
    $ping('serviceDisabled');
    db_execute(
        "UPDATE activity_events SET occurred_at = ? WHERE employee_id = 'emp1' AND title = 'Offline'",
        [gmdate(Wire::TS, time() - $seconds)],
    );
    $ping('ok');
    $all = $rows();
    return array_values(array_filter($all, fn(array $r) => $r['title'] === 'Back online'))[0]['subtitle'];
};

check($gapWording(20) === 'Tracking was down for less than a minute', 'sub-minute wording: ' . $gapWording(20));
check($gapWording(60) === 'Tracking was down for 1 minute', 'singular minute: ' . $gapWording(60));
check($gapWording(3600) === 'Tracking was down for 1 hour', 'a clean hour drops the minutes: ' . $gapWording(3600));
check($gapWording(3600 + 25 * 60) === 'Tracking was down for 1 hour 25 min', 'hours + minutes: ' . $gapWording(3600 + 25 * 60));
check($gapWording(2 * 3600) === 'Tracking was down for 2 hours', 'plural hours: ' . $gapWording(2 * 3600));

// An outage that began before any row existed still recovers cleanly.
db_execute("DELETE FROM activity_events WHERE employee_id = 'emp1'");
db_execute("DELETE FROM device_health WHERE employee_id = 'emp1'");
db_execute(
    "INSERT INTO device_health (employee_id, location_health, is_online, reported_at) VALUES (?, 'service_disabled', 0, ?)",
    ['emp1', gmdate(Wire::TS)],
);
$ping('ok');
$orphan = $rows();
check(count($orphan) === 1 && $orphan[0]['title'] === 'Back online', 'a recovery with no Offline row on record must still be written');
check($orphan[0]['subtitle'] === 'Location and uploads are working again',
    'with nothing to measure from it must not invent a duration, got ' . json_encode($orphan[0]['subtitle']));

// ====================================================== E — rebuilds keep them
//
// These rows are not derivable from sessions and stops, so the rebuild that
// regenerates everything else must step around them.
db_execute("DELETE FROM activity_events WHERE employee_id = 'emp1'");
db_execute("DELETE FROM device_health WHERE employee_id = 'emp1'");
$ping('serviceDisabled');
$ping('ok');

$before = count($rows());
check($before === 2, 'expected an Offline + Back online pair before the rebuild');

Engine::rebuildActivity('emp1', $date);
$after = $rows();
$kept = array_values(array_filter($after, fn(array $r) => $r['type'] === 'tracking_issue'));
check(count($kept) === $before, 'a rebuild must preserve BOTH rows, kept ' . count($kept) . ' of ' . $before);
check(count(array_filter($kept, fn(array $r) => $r['title'] === 'Back online')) === 1,
    'the recovery row must survive the rebuild too — it is not derivable either');
check(count($after) > count($kept), 'the rebuild should also have written the session rows');

// ========================================================= F — garbage is a 422
$bad = request('TrackingController', 'health', ['locationHealth' => 'weakSignal'], $emp1);
check($bad['status'] === 422, 'an unknown locationHealth must be a 422, got ' . $bad['status']);
check(($bad['json']['error']['field'] ?? null) === 'locationHealth', 'the 422 must name the field');

// An admin has no device to report on.
$asAdmin = request('TrackingController', 'health', ['locationHealth' => 'serviceDisabled'], $admin);
check($asAdmin['status'] === 403, 'health is written by employee devices only, got ' . $asAdmin['status']);

@unlink($DBFILE);
echo "tracking-health: all checks passed\n";
