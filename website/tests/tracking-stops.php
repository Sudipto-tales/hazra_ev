<?php
// Run: php tests/tracking-stops.php. Uses a throwaway file-backed SQLite DB,
// same pattern as tests/tracking-live.php; never touches an application DB.
//
// Regression guard for Engine::clusterStops() (Engine.php). The clustering
// window is CENTROID-based: a fix joins the current stop while it sits within
// stop_radius_metres of the running mean of the fixes accepted so far, not
// within that radius of the immutable FIRST fix.
//
// The bug this reproduces: an anchor-based window pins the run to its first
// fix, so ordinary stationary GPS jitter that drifts past the 75 m first-fix
// radius shatters a single long stop into sub-threshold fragments that get
// dropped at the stop_threshold_minutes gate. A user lying at home for ~1 hour
// then shows as a ~10-minute stop (or, as the fixture below demonstrates under
// the old logic, no stop at all).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../config/migrate.php';
require_once __DIR__ . '/../core/ApiRequest.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../api/support/bootstrap.php';

function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare(Dialect::dml($sql)); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$DBFILE = tempnam(sys_get_temp_dir(), 'stops') . '.sqlite';
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

db_execute('INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)',
    ['org', 'Hazra EV', 'UTC', $now]);
db_execute(
    'INSERT INTO users (id, org_id, role, name, email, password_hash, active, account_status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    ['emp1', 'org', 'employee', 'Rep One', 'emp1@test.example', password_hash('x', PASSWORD_BCRYPT), 1, 'active', $now, $now],
);

// Impersonate an org member so Engine::deriveStops() can read config + orgId.
Ctx::impersonate(['id' => 'admin', 'org_id' => 'org', 'role' => 'admin']);

// Confirm we are testing against the documented defaults — the fixture below is
// built around exactly these numbers.
$config = Ctx::config();
check((float) $config['stop_radius_metres'] === 75.0, 'fixture assumes stop_radius_metres default 75, got ' . $config['stop_radius_metres']);
check((int) $config['stop_threshold_minutes'] === 10, 'fixture assumes stop_threshold_minutes default 10, got ' . $config['stop_threshold_minutes']);

$openSession = function (string $sessionId) use ($date, $now) {
    db_execute(
        'INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, ended_at, start_lat, start_lng)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$sessionId, 'emp1', $sessionId . '-client', $date, 1, gmdate(Wire::TS, time() - 7200), null, 22.57, 88.36],
    );
};

// ============================================================================
// One stationary ~39-minute period. Every fix lies within 70 m of the centre
// (22.5700, 88.3600) — the person never leaves the room — but the 2-D GPS
// jitter is wide enough that CONSECUTIVE fixes land up to ~134 m apart and 19
// of them sit more than 75 m from the FIRST fix. Anchor-based clustering
// shatters this into sub-threshold fragments and reports nothing; centroid-
// based clustering recognises one continuous stop.
//
// [lat, lng] pairs, one fix per minute (deterministic, derived offline from a
// fixed jitter seed; north/east offset in metres noted per row).
$jitter = [
    [22.570438, 88.360042], // n=48.8  e=4.3
    [22.569965, 88.359544], // n=-3.9  e=-46.8
    [22.569872, 88.360425], // n=-14.3 e=43.7
    [22.570274, 88.360591], // n=30.4  e=60.8
    [22.570344, 88.360231], // n=38.2  e=23.7
    [22.569819, 88.359587], // n=-20.2 e=-42.5
    [22.570137, 88.360645], // n=15.3  e=66.3
    [22.569914, 88.359473], // n=-9.6  e=-54.2
    [22.569512, 88.360132], // n=-54.3 e=13.5
    [22.569867, 88.360505], // n=-14.8 e=51.9
    [22.569843, 88.360466], // n=-17.4 e=47.9
    [22.570320, 88.359732], // n=35.6  e=-27.5
    [22.569834, 88.359506], // n=-18.5 e=-50.7
    [22.569601, 88.360148], // n=-44.5 e=15.2
    [22.569517, 88.359957], // n=-53.8 e=-4.4
    [22.569499, 88.359813], // n=-55.8 e=-19.2
    [22.570111, 88.359399], // n=12.3  e=-61.8
    [22.570096, 88.359505], // n=10.7  e=-50.9
    [22.570583, 88.359884], // n=64.9  e=-11.9
    [22.569927, 88.359412], // n=-8.2  e=-60.4
    [22.570041, 88.359457], // n=4.6   e=-55.8
    [22.570524, 88.360205], // n=58.3  e=21.1
    [22.570295, 88.360511], // n=32.9  e=52.6
    [22.569907, 88.359336], // n=-10.3 e=-68.2
    [22.570067, 88.359460], // n=7.5   e=-55.5
    [22.570368, 88.360327], // n=41.0  e=33.6
    [22.569877, 88.359352], // n=-13.7 e=-66.6
    [22.569601, 88.359594], // n=-44.5 e=-41.8
    [22.569755, 88.359640], // n=-27.3 e=-37.0
    [22.570291, 88.360548], // n=32.4  e=56.3
    [22.569812, 88.359590], // n=-20.9 e=-42.1
    [22.570393, 88.360385], // n=43.8  e=39.6
    [22.569575, 88.360211], // n=-47.3 e=21.6
    [22.570402, 88.359782], // n=44.7  e=-22.4
    [22.570342, 88.359656], // n=38.1  e=-35.4
    [22.570258, 88.359567], // n=28.7  e=-44.6
    [22.569371, 88.360005], // n=-70.0 e=0.5
    [22.570574, 88.359962], // n=63.9  e=-3.9
    [22.570343, 88.359465], // n=38.2  e=-55.0
    [22.569785, 88.359551], // n=-23.9 e=-46.2
];

$openSession('session-stop');

// Fixes one minute apart, starting an hour ago so the whole stop sits in-day.
$t0 = time() - 3600;
foreach ($jitter as $k => [$lat, $lng]) {
    db_execute(
        'INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        ['session-stop', 'emp1', 'fix-' . $k, gmdate(Wire::TS, $t0 + $k * 60), gmdate(Wire::TS, $t0 + $k * 60), $lat, $lng, 20, 0.2],
    );
}

// Sanity on the fixture itself: nobody actually left (<=75 m from centre) yet
// the jitter crosses the 75 m first-fix radius that the old logic pinned to.
$distM = function (float $la, float $lo, float $lb, float $lp): float {
    return Geo::distanceM($la, $lo, $lb, $lp);
};
$maxFromCentre = 0.0;
$maxFromFirst  = 0.0;
$crossings     = 0;
foreach ($jitter as $k => [$lat, $lng]) {
    $maxFromCentre = max($maxFromCentre, $distM(22.5700, 88.3600, $lat, $lng));
    $df = $distM($jitter[0][0], $jitter[0][1], $lat, $lng);
    $maxFromFirst = max($maxFromFirst, $df);
    if ($df > 75.0) { $crossings++; }
}
check($maxFromCentre <= 75.0, 'fixture must stay inside the stop radius of the centre, got ' . round($maxFromCentre, 1) . ' m');
check($maxFromFirst > 75.0, 'fixture must drift past the 75 m FIRST-fix radius to exercise the bug, got ' . round($maxFromFirst, 1) . ' m');
check($crossings >= 5, 'fixture should have several fixes outside the first-fix radius, got ' . $crossings);

Engine::deriveStops('emp1', $date);

$stops = db_fetch_all('SELECT * FROM stop_records WHERE session_id = ? ORDER BY arrival', ['session-stop']);
check(count($stops) === 1, 'a continuous stationary hour must yield exactly ONE stop, got ' . count($stops));

$stop = $stops[0];
$expectedArrival   = gmdate(Wire::TS, $t0);
$expectedDeparture = gmdate(Wire::TS, $t0 + (count($jitter) - 1) * 60);
check(Wire::ts($stop['arrival']) === $expectedArrival,
    'the stop must start at the first fix, got ' . $stop['arrival'] . ' expected ' . $expectedArrival);
check(Wire::ts($stop['departure']) === $expectedDeparture,
    'the stop must run to the last fix — the full 39 min, not a fragment; got ' . $stop['departure'] . ' expected ' . $expectedDeparture);

$span = Wire::seconds($stop['arrival'], $stop['departure']);
check($span === (count($jitter) - 1) * 60, 'the span must be the full 2340 s, got ' . $span . ' s');

// Returned shape reconcileStops persisted must be intact.
foreach (['arrival', 'departure', 'lat', 'lng', 'radius_m'] as $col) {
    check(array_key_exists($col, $stop), "stop_records must carry {$col}");
}
// radius_m is the observed max distance from the FINAL centroid: a real spread.
// It can edge past stop_radius_metres — membership is tested against the
// RUNNING centroid as fixes arrive, while this figure is recomputed against the
// final centroid over the whole cluster, so the two need not agree exactly.
$radius = (float) $stop['radius_m'];
check($radius > 40.0 && $radius < 90.0, 'radius_m must be the observed spread from the final centroid, got ' . $radius);
// The centroid sits near the true centre the jitter was drawn around.
check($distM(22.5700, 88.3600, (float) $stop['lat'], (float) $stop['lng']) < 20.0,
    'the stop centroid must land near the jitter centre');

// A re-derivation must be idempotent — same single stop, keyed by arrival minute.
Engine::deriveStops('emp1', $date);
$again = db_fetch_all('SELECT id FROM stop_records WHERE session_id = ?', ['session-stop']);
check(count($again) === 1, 're-deriving must not duplicate the stop, got ' . count($again));
check($again[0]['id'] === $stop['id'], 're-deriving must update the same row, not orphan it');

@unlink($DBFILE);
echo "tracking-stops: all checks passed\n";
