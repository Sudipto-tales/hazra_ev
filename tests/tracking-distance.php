<?php
// Run: php tests/tracking-distance.php. Never connects to an application database.
//
// Covers the distance gates in Engine::refreshSession / Engine::chainDistanceKm.
// Before them, the session odometer summed EVERY consecutive fix pair, so a
// device parked for an hour reported hundreds of metres of GPS jitter as
// travel. Two gates now stand between the stored fixes and distance_km:
//
//   - accuracy    — a fix worse than config.min_accuracy_metres does not
//                   measure a leg (it is still stored and still drawn), and the
//                   last ACCEPTED fix is carried forward as the anchor so the
//                   skip cannot open a false long leg.
//   - minimum step — a hop shorter than config.distance_filter_metres adds 0
//                   AND leaves the anchor where it is, so jitter cannot
//                   accumulate in sub-threshold increments while a genuinely
//                   slow walk still measures in full.
//
// Both thresholds come from tracking_configs through Ctx, so retuning either
// re-derives on the next refresh — nothing is stored about the decision.
// point_count stays the RAW stored fix count; the gates shape distance only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../api/support/bootstrap.php';
function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare($sql); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function near(float $actual, float $expected, float $tolerance, string $what): void {
    check(abs($actual - $expected) <= $tolerance,
        sprintf('%s: expected %.4f ± %.4f, got %.4f', $what, $expected, $tolerance, $actual));
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');
Dialect::boot($pdo, 'sqlite');
foreach (glob(__DIR__ . '/../database/migrations/0*.php') as $file) {
    $before = get_declared_classes(); require_once $file;
    foreach (array_diff(get_declared_classes(), $before) as $class) if (is_subclass_of($class, Migration::class)) (new $class($pdo))->up();
}

$now = '2026-10-02T08:00:00Z';
db_execute('INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)', ['org', 'Test', 'Asia/Kolkata', $now]);
db_execute('INSERT INTO users (id, org_id, role, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    ['employee', 'org', 'employee', 'Field Rep', 'rep@test.example', password_hash('password123', PASSWORD_BCRYPT), $now, $now]);

function reload(): void { Ctx::impersonate(['id' => 'employee', 'org_id' => 'org', 'role' => 'employee']); }
reload();

// The documented defaults the v1 gates fall back on. Both moved in 036 — 50 ->
// 30 m accuracy and 20 -> 10 m capture filter — because capture has to sample
// finely enough not to cut the corners of a winding track. Measurement did NOT
// follow the filter down: it moved to its own column, min_step_metres (15 m),
// so a parked phone's drift still books nothing. See migration 036.
//
// Every session in this file is v1 (work_sessions.distance_algo_version
// defaults to 1), so everything below exercises the frozen v1 engine against
// these two columns. The v2 engine has its own section at the end.
$config = Ctx::config();
check((float) $config['min_accuracy_metres'] === 30.0, 'Expected the documented default accuracy threshold');
check((int) $config['distance_filter_metres'] === 10, 'Expected the documented default distance filter');
check((float) $config['min_step_metres'] === 15.0, 'Expected the documented default measurement step');

$base = [22.5700, 88.3600];

/** Offsets a lat/lng by metres north/east — flat-earth is exact enough at these scales. */
function at(array $origin, float $northM, float $eastM): array {
    return [
        $origin[0] + $northM / 111_320.0,
        $origin[1] + $eastM / (111_320.0 * cos(deg2rad($origin[0]))),
    ];
}

$sessionSeq = 0;
function makeSession(string $id): array {
    global $sessionSeq;
    db_execute('INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, start_lat, start_lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, 'employee', $id . '-client', '2026-10-02', ++$sessionSeq, '2026-10-02T07:00:00Z', 22.5700, 88.3600]);
    return db_fetch_one('SELECT * FROM work_sessions WHERE id = ?', [$id]);
}

$fixSeq = 0;
/** Inserts a fix directly: the geometry has to be exact, so ingest's JUMP guard is bypassed. */
function addFix(string $sessionId, array $point, float $accuracy, int $second): void {
    global $fixSeq;
    $ts = gmdate('Y-m-d\TH:i:s\Z', strtotime('2026-10-02T07:00:00Z') + $second);
    db_execute('INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$sessionId, 'employee', 'fix-' . (++$fixSeq), $ts, $ts, $point[0], $point[1], $accuracy, 0]);
}

/** The old behaviour, kept here as the thing being compared against. */
function naiveKm(string $sessionId): float {
    $rows = db_fetch_all('SELECT lat, lng FROM location_fixes WHERE session_id = ? ORDER BY recorded_at, id', [$sessionId]);
    $km = 0.0;
    for ($i = 1, $n = count($rows); $i < $n; $i++) {
        $km += Geo::distanceKm((float) $rows[$i - 1]['lat'], (float) $rows[$i - 1]['lng'], (float) $rows[$i]['lat'], (float) $rows[$i]['lng']);
    }
    return $km;
}

function stored(string $sessionId): array {
    return db_fetch_one('SELECT distance_km, point_count FROM work_sessions WHERE id = ?', [$sessionId]);
}

// ---------------------------------------------------------------------------
// 1. A parked device. Sixty good-accuracy fixes wandering inside a ~6 m circle.
// ---------------------------------------------------------------------------
$parked = makeSession('parked');
$jitter = [[0, 0], [4, 2], [-3, 5], [2, -6], [-5, -1], [6, 3], [-1, -4], [3, 6], [-6, 2], [1, -3]];
for ($i = 0; $i < 60; $i++) {
    $offset = $jitter[$i % count($jitter)];
    addFix('parked', at($base, $offset[0], $offset[1]), 8, $i * 30);
}

$parkedNaive = naiveKm('parked');
check($parkedNaive > 0.3, sprintf('The jitter fixture should inflate under the old sum, got %.4f km', $parkedNaive));

Engine::refreshSession('parked');
$row = stored('parked');

check((float) $row['distance_km'] === 0.0, sprintf('A parked device must sum to 0, got %s km', $row['distance_km']));
check((int) $row['point_count'] === 60, 'point_count must stay the raw fix count (60)');

// ---------------------------------------------------------------------------
// 2. A genuine straight-line walk, 60 m per fix. Nothing may be lost.
// ---------------------------------------------------------------------------
$walk = makeSession('walk');
for ($i = 0; $i <= 20; $i++) {
    addFix('walk', at($base, $i * 60, 0), 10, $i * 60);
}

Engine::refreshSession('walk');
$row = stored('walk');

$walkExpected = Geo::distanceKm($base[0], $base[1], ...at($base, 20 * 60, 0));   // ~1.2 km, one straight leg
near((float) $row['distance_km'], $walkExpected, 0.005, 'A straight-line walk must still sum correctly');
near((float) $row['distance_km'], naiveKm('walk'), 0.005, 'Above-threshold hops must be untouched by the gate');
check((int) $row['point_count'] === 21, 'point_count must be the raw fix count (21)');

// ---------------------------------------------------------------------------
// 3. A slow crawl: every hop is 8 m, UNDER the 20 m gate. The anchor holding
//    still is what keeps this from being measured as zero.
// ---------------------------------------------------------------------------
$crawl = makeSession('crawl');
for ($i = 0; $i <= 40; $i++) {
    addFix('crawl', at($base, $i * 8, 0), 10, $i * 20);
}

Engine::refreshSession('crawl');
$crawlKm = (float) stored('crawl')['distance_km'];

// 320 m walked; measured in >= 20 m chunks, so at most one trailing chunk is
// unbanked. Zero would mean the anchor had been advanced on a rejected hop.
check($crawlKm > 0.28 && $crawlKm < 0.33, sprintf('A sub-threshold crawl must still measure ~0.32 km, got %.4f', $crawlKm));

// ---------------------------------------------------------------------------
// 4. A low-accuracy fix mid-route must not open a false detour.
// ---------------------------------------------------------------------------
$weak = makeSession('weak');
for ($i = 0; $i < 3; $i++) {
    addFix('weak', at($base, $i * 100, 0), 10, $i * 60);                 // 0, 100, 200 m north
}
addFix('weak', at($base, 200, 300), 500, 200);                           // 300 m east, accuracy 500 m
for ($i = 3; $i <= 4; $i++) {
    addFix('weak', at($base, $i * 100, 0), 10, $i * 60);                 // 300, 400 m north
}

$weakNaive = naiveKm('weak');
check($weakNaive > 0.85, sprintf('The fixture should show a false detour under the old sum, got %.4f km', $weakNaive));

Engine::refreshSession('weak');
$row = stored('weak');

$weakExpected = Geo::distanceKm($base[0], $base[1], ...at($base, 400, 0));       // 400 m, one straight line
near((float) $row['distance_km'], $weakExpected, 0.01, 'A low-accuracy fix must not add a false leg');
check((int) $row['point_count'] === 6, 'point_count must count the low-accuracy fix too (6)');

// The skip must not break the chain: the 200 m -> 300 m leg across the bad fix
// is measured once from the last ACCEPTED fix, not dropped and not doubled.
check((float) $row['distance_km'] > 0.39, 'The leg across a skipped fix must still be measured');

// ---------------------------------------------------------------------------
// 5. Degenerate sessions.
// ---------------------------------------------------------------------------
$empty = makeSession('empty');
Engine::refreshSession('empty');
$row = stored('empty');
check((float) $row['distance_km'] === 0.0 && (int) $row['point_count'] === 0, 'An empty session must be 0 / 0');

$single = makeSession('single');
addFix('single', $base, 10, 0);
Engine::refreshSession('single');
$row = stored('single');
check((float) $row['distance_km'] === 0.0 && (int) $row['point_count'] === 1, 'A one-fix session must be 0 km / 1 point');

// A session of nothing but unusable fixes: no anchor is ever accepted, so the
// distance is 0 — but the fixes are still counted and still on record.
$blind = makeSession('blind');
for ($i = 0; $i < 5; $i++) { addFix('blind', at($base, $i * 200, 0), 400, $i * 60); }
Engine::refreshSession('blind');
$row = stored('blind');
check((float) $row['distance_km'] === 0.0, 'An all-low-accuracy session must not measure distance');
check((int) $row['point_count'] === 5, 'An all-low-accuracy session must still count its fixes');

// ---------------------------------------------------------------------------
// 6. Read-time semantics: both thresholds come from config, so retuning either
//    re-derives the SAME stored fixes without rewriting one of them.
// ---------------------------------------------------------------------------
db_execute("UPDATE tracking_configs SET min_accuracy_metres = 1000 WHERE org_id = 'org'");
reload();
check((float) Ctx::config()['min_accuracy_metres'] === 1000.0, 'Retune did not take');

Engine::refreshSession('weak');
near((float) stored('weak')['distance_km'], $weakNaive, 0.01,
    'A relaxed accuracy threshold must re-admit the stored fix on the next refresh');

db_execute("UPDATE tracking_configs SET min_accuracy_metres = 50 WHERE org_id = 'org'");
reload();
Engine::refreshSession('weak');
near((float) stored('weak')['distance_km'], $weakExpected, 0.01, 'Tightening the threshold must restore the gated sum');

// A non-positive filter disables its own gate rather than the whole session,
// which is both the degradation story and proof the number is config-driven.
db_execute("UPDATE tracking_configs SET distance_filter_metres = 0 WHERE org_id = 'org'");
reload();
Engine::refreshSession('parked');
near((float) stored('parked')['distance_km'], $parkedNaive, 0.001, 'A 0 m filter must fall back to the raw sum');

// Widening it the other way: a 200 m filter banks the same walk in coarser
// chunks and must not exceed the honest distance.
db_execute("UPDATE tracking_configs SET distance_filter_metres = 200 WHERE org_id = 'org'");
reload();
Engine::refreshSession('walk');
$coarse = (float) stored('walk')['distance_km'];
check($coarse > 0.9 && $coarse <= $walkExpected + 0.001,
    sprintf('A 200 m filter must still measure the walk without inflating it, got %.4f', $coarse));

Engine::refreshSession('parked');
check((float) stored('parked')['distance_km'] === 0.0, 'A wider filter must still hold a parked device at 0');

// ---------------------------------------------------------------------------
// 7. The day rollup and the drawn route read the same gated number.
// ---------------------------------------------------------------------------
db_execute("UPDATE tracking_configs SET distance_filter_metres = 20 WHERE org_id = 'org'");
reload();

db_execute("DELETE FROM location_fixes WHERE session_id <> 'parked'");
db_execute("DELETE FROM work_sessions WHERE id <> 'parked'");

// The backfill question, as a test: a session carrying the OLD inflated number
// is repaired by Engine::recompute alone — which is what a one-off backfill
// over historical days would call, and what a config change already calls.
db_execute("UPDATE work_sessions SET distance_km = 9.5 WHERE id = 'parked'");
Engine::recompute('employee', '2026-10-02');

check((float) stored('parked')['distance_km'] === 0.0, 'recompute must re-derive a stale session distance');

$route = db_fetch_one("SELECT point_count, total_distance_km FROM day_routes WHERE employee_id = 'employee' AND work_date = '2026-10-02'");
check($route !== false && $route !== null, 'The day route was not built');
check((float) $route['total_distance_km'] === 0.0,
    sprintf('day_routes.total_distance_km must use the same gate, got %s', $route['total_distance_km']));

$day = db_fetch_one("SELECT distance_km FROM attendance_days WHERE employee_id = 'employee' AND work_date = '2026-10-02'");
check((float) $day['distance_km'] === 0.0, 'attendance_days.distance_km must inherit the gated session sum');

// RoutesController measures today's not-yet-cached route through the same
// public helper, so the live number and the cached number must agree.
$raw = db_fetch_all("SELECT f.lat, f.lng, f.accuracy_m FROM location_fixes f
                       JOIN work_sessions s ON s.id = f.session_id
                      WHERE s.employee_id = 'employee' AND s.work_date = '2026-10-02'
                      ORDER BY f.recorded_at, f.id");
near(Engine::chainDistanceKm($raw, Engine::dayAlgoVersion('employee', '2026-10-02')),
    (float) $route['total_distance_km'], 0.0001,
    'The live route sum must match the cached day_routes row');

// The v1 day leaves every v2 reporting column NULL — that is what marks it as
// predating the universal engine, rather than as a day with perfect coverage.
$routeFull = db_fetch_one("SELECT * FROM day_routes WHERE employee_id = 'employee' AND work_date = '2026-10-02'");
check($routeFull['verified_distance_km'] === null, 'A v1 day_routes row must not claim a verified figure');
check($routeFull['gps_coverage_percent'] === null, 'A v1 day_routes row must not claim coverage');
check((int) $routeFull['distance_algo_version'] === 1, 'A day of v1 sessions must stay pinned to v1');
$dayRow = db_fetch_one("SELECT * FROM attendance_days WHERE employee_id = 'employee' AND work_date = '2026-10-02'");
check($dayRow['verified_distance_km'] === null, 'A v1 attendance_days row must not claim a verified figure');
check($dayRow['gps_coverage_percent'] === null, 'A v1 attendance_days row must not claim coverage');

// ===========================================================================
// 8. The v2 universal engine (migration 036). Cycling, road travel and train
//    are measured by the identical code — mode never reaches the arithmetic.
// ===========================================================================
//
// Thresholds are set explicitly rather than taken from the defaults, so these
// checks keep meaning the same thing if a default is retuned again.
db_execute("UPDATE tracking_configs SET min_step_metres = 15, accuracy_step_factor = 1.0,
            gps_gap_warning_seconds = 60, gps_gap_incomplete_seconds = 180,
            min_accuracy_metres = 30 WHERE org_id = 'org'");
reload();

$v2Date = '2026-10-03';
$v2Base = strtotime($v2Date . 'T07:00:00Z');

// Pinned to literal 2, NOT Engine::DISTANCE_ALGO_VERSION: this section exercises
// the frozen v2 engine, and the constant has since moved on to 3 (the client
// odometer, migration 037, which has its own section at the end of the file).
function makeSessionV2(string $id, string $date): array {
    global $sessionSeq;
    db_execute('INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, start_lat, start_lng, distance_algo_version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, 'employee', $id . '-client', $date, ++$sessionSeq, $date . 'T07:00:00Z', 22.5700, 88.3600, 2]);
    return db_fetch_one('SELECT * FROM work_sessions WHERE id = ?', [$id]);
}

function addFixAt(string $sessionId, array $point, float $accuracy, int $second, string $source = 'STREAM'): void {
    global $fixSeq, $v2Base;
    $ts = gmdate('Y-m-d\TH:i:s\Z', $v2Base + $second);
    db_execute('INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh, source)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$sessionId, 'employee', 'fix-' . (++$fixSeq), $ts, $ts, $point[0], $point[1], $accuracy, 0, $source]);
}

function gapsOf(string $sessionId): array {
    return db_fetch_all('SELECT * FROM session_gps_gaps WHERE session_id = ? ORDER BY gap_start', [$sessionId]);
}

// ---------------------------------------------------------------------------
// 8a. Weak GPS over real travel. THE under-count this change exists to fix.
//
// Five fixes 200 m apart with 100 m accuracy — an off-road track under tree
// cover, a factory yard, a village lane. v1 refuses to measure from any fix
// worse than min_accuracy_metres (30) and carries the anchor forward, so the
// whole 800 m collapses to nothing. v2 asks the fix to move further than it
// could be wrong by: the step is max(15, 1.0 * 100) = 100 m, which a 200 m hop
// clears, so the real travel survives.
//
// Hop and step are deliberately a factor of two apart. A fixture sitting ON
// its own threshold measures whatever `at()`'s flat-earth metre and the
// haversine disagree about in the last decimetre, which is a test that fails
// for a reason that has nothing to do with the engine.
// ---------------------------------------------------------------------------
$weakReal = makeSessionV2('weak-real', $v2Date);
for ($i = 0; $i <= 4; $i++) {
    addFixAt('weak-real', at($base, $i * 200, 0), 100, $i * 30);     // 30 s apart: no gap branch
}

$weakFixes = db_fetch_all("SELECT lat, lng, accuracy_m, recorded_at, source FROM location_fixes
                            WHERE session_id = 'weak-real' ORDER BY recorded_at, id");

near(Engine::chainDistanceKm($weakFixes, 1), 0.0, 0.0001,
    'v1 must still measure nothing here — that is the bug, and it stays frozen');
near(Engine::chainDistanceKm($weakFixes, 2), 0.8, 0.01,
    'v2 must measure the real 800 m through weak GPS');

Engine::refreshSession('weak-real');
$row = db_fetch_one("SELECT * FROM work_sessions WHERE id = 'weak-real'");
near((float) $row['distance_km'], 0.8, 0.01, 'distance_km must carry the v2 verified figure');
near((float) $row['verified_distance_km'], 0.8, 0.01, 'verified_distance_km must be populated for v2');
near((float) $row['incomplete_distance_km'], 0.0, 0.0001, 'Continuous GPS owes nothing to incomplete');
near((float) $row['gps_coverage_percent'], 100.0, 0.01, 'Continuous GPS is 100 % coverage');
check(gapsOf('weak-real') === [], 'Continuous GPS must record no gaps');

// A weak fix that CANNOT be told from its own error still measures nothing:
// 100 m hops under 400 m accuracy never clear the scaled step.
$weakNoise = makeSessionV2('weak-noise', $v2Date);
for ($i = 0; $i <= 3; $i++) { addFixAt('weak-noise', at($base, $i * 100, 0), 400, 600 + $i * 30); }
$noiseFixes = db_fetch_all("SELECT lat, lng, accuracy_m, recorded_at, source FROM location_fixes
                             WHERE session_id = 'weak-noise' ORDER BY recorded_at, id");
near(Engine::chainDistanceKm($noiseFixes, 2), 0.0, 0.0001,
    'Movement smaller than the fix error is not evidence of travel');

// factor = 0 collapses the scaling back to a flat floor, which is the
// documented escape hatch — the same fixes then measure in full.
db_execute("UPDATE tracking_configs SET accuracy_step_factor = 0 WHERE org_id = 'org'");
reload();
near(Engine::chainDistanceKm($noiseFixes, 2), 0.3, 0.01,
    'accuracy_step_factor = 0 must restore a flat min_step_metres floor');
db_execute("UPDATE tracking_configs SET accuracy_step_factor = 1.0 WHERE org_id = 'org'");
reload();

// ---------------------------------------------------------------------------
// 8b. A parked vehicle is NOT a GPS gap. The false positive that would have
//     made gps_coverage_percent meaningless.
//
//     The capture distance filter means a parked device emits nothing until its
//     five-minute heartbeat, which is past the 180 s incomplete threshold. On
//     elapsed time alone every lunch break and long customer visit would be
//     reported as broken coverage. Displacement is what decides.
// ---------------------------------------------------------------------------
$parkedV2 = makeSessionV2('parked-v2', $v2Date);
addFixAt('parked-v2', at($base, 0, 0), 10, 0);
addFixAt('parked-v2', at($base, 3, 2), 10, 300, 'HEARTBEAT');       // 10 min of silence,
addFixAt('parked-v2', at($base, 0, 4), 10, 600, 'HEARTBEAT');       // ~4 m of drift

Engine::refreshSession('parked-v2');
$row = db_fetch_one("SELECT * FROM work_sessions WHERE id = 'parked-v2'");
near((float) $row['distance_km'], 0.0, 0.0001, 'A parked v2 device must still measure 0');
near((float) $row['gps_coverage_percent'], 100.0, 0.01,
    'Ten minutes parked must not cost GPS coverage');
check(gapsOf('parked-v2') === [], 'A parked stretch is a stop, never a GPS gap');

// ---------------------------------------------------------------------------
// 8c. A tunnel IS a gap, and its straight line is never verified distance.
//
//     This is also the case ingest cannot catch: 5 km in 300 s implies 60 km/h,
//     far under maxJumpKmh (180), so the fix is accepted and v1 would book the
//     chord as travelled road.
// ---------------------------------------------------------------------------
$tunnel = makeSessionV2('tunnel', $v2Date);
addFixAt('tunnel', at($base, 0, 0), 10, 0);
addFixAt('tunnel', at($base, 5000, 0), 10, 300);        // 5 km later, nothing in between
addFixAt('tunnel', at($base, 5100, 0), 10, 330);        // then 100 m of real tracking

$tunnelFixes = db_fetch_all("SELECT lat, lng, accuracy_m, recorded_at, source FROM location_fixes
                              WHERE session_id = 'tunnel' ORDER BY recorded_at, id");
$measured = Engine::chainDistance($tunnelFixes, 2);

near($measured['verifiedKm'], 0.1, 0.01, 'Only the tracked 100 m is verified');
near($measured['incompleteKm'], 5.0, 0.02, 'The 5 km across the gap is incomplete, not verified');
check(count($measured['gaps']) === 1, 'The tunnel must be recorded as exactly one gap');
check($measured['gaps'][0]['class'] === 'INCOMPLETE', 'A 300 s gap is INCOMPLETE, not a warning');
near($measured['coveragePercent'], 9.09, 0.5, 'Coverage must fall to the tracked share of the span');

// v1 is untouched by any of this: it bridges the tunnel silently, which is
// precisely why historical days must keep measuring themselves their own way.
near(Engine::chainDistanceKm($tunnelFixes, 1), 5.1, 0.02,
    'v1 must still bridge the gap — frozen, not corrected');

Engine::refreshSession('tunnel');
$row = db_fetch_one("SELECT * FROM work_sessions WHERE id = 'tunnel'");
near((float) $row['distance_km'], 0.1, 0.01, 'distance_km is the verified figure, never verified + incomplete');
near((float) $row['incomplete_distance_km'], 5.0, 0.02, 'The gap distance is stored under its own label');

$gaps = gapsOf('tunnel');
check(count($gaps) === 1, 'The gap must be persisted');
check($gaps[0]['classification'] === 'INCOMPLETE', 'Persisted classification must match');
near((float) $gaps[0]['displacement_m'], 5000.0, 20.0, 'Persisted displacement must match');
check((int) $gaps[0]['seconds'] === 300, 'Persisted gap duration must match');

// Re-deriving owns the set outright rather than appending to it.
Engine::refreshSession('tunnel');
check(count(gapsOf('tunnel')) === 1, 'A re-derive must replace the gap set, not duplicate it');

// ---------------------------------------------------------------------------
// 8d. WARNING vs INCOMPLETE, either side of the two thresholds.
// ---------------------------------------------------------------------------
$twoGaps = makeSessionV2('two-gaps', $v2Date);
addFixAt('two-gaps', at($base, 0, 0), 10, 0);
addFixAt('two-gaps', at($base, 500, 0), 10, 100);       // 100 s: >= 60, < 180  -> WARNING
addFixAt('two-gaps', at($base, 1000, 0), 10, 400);      // 300 s: >= 180        -> INCOMPLETE

Engine::refreshSession('two-gaps');
$gaps = gapsOf('two-gaps');
check(count($gaps) === 2, 'Both quiet stretches must be recorded');
check($gaps[0]['classification'] === 'WARNING', 'A 100 s gap is a WARNING');
check($gaps[1]['classification'] === 'INCOMPLETE', 'A 300 s gap is INCOMPLETE');
near((float) stored('two-gaps')['distance_km'], 0.0, 0.0001,
    'Nothing across either gap may be verified');

// ---------------------------------------------------------------------------
// 8e. The day rollup and the drawn route carry the v2 figures, and a pause
//     between two sessions is not a gap.
// ---------------------------------------------------------------------------
Engine::recompute('employee', $v2Date);

$route = db_fetch_one("SELECT * FROM day_routes WHERE employee_id = 'employee' AND work_date = ?", [$v2Date]);
check((int) $route['distance_algo_version'] === 2, 'A day of v2 sessions must be pinned to v2');
check($route['verified_distance_km'] !== null, 'A v2 day must report a verified figure');
check($route['gps_coverage_percent'] !== null, 'A v2 day must report coverage');

$day = db_fetch_one("SELECT * FROM attendance_days WHERE employee_id = 'employee' AND work_date = ?", [$v2Date]);
near((float) $day['distance_km'], (float) $route['total_distance_km'], 0.01,
    'The day summary and the drawn route must agree — v2 measures both per session');
check((float) $day['incomplete_distance_km'] > 4.9, 'The day must carry the tunnel as incomplete distance');

// Five sessions on this day, each starting at the same base point and hours
// apart on the clock. Measuring the day as one chain would read every gap
// between them as travel AND as a GPS outage; measuring per session cannot.
$dayGaps = db_fetch_all("SELECT * FROM session_gps_gaps WHERE employee_id = 'employee' AND work_date = ?", [$v2Date]);
check(count($dayGaps) === 3, sprintf('Only the three real in-session gaps may exist, got %d', count($dayGaps)));

// ---------------------------------------------------------------------------
// 9. The client odometer (migration 037, Engine::DISTANCE_ALGO_VERSION = 3).
//
// THE real-world failure this exists to fix: the phone captures one fix every
// ~60 s while moving, which lands right on gps_gap_warning_seconds (60), so v2
// routes ordinary driving into incompleteKm and the verified headline
// collapses to a fraction of the distance. v3 keeps v2's derivation as a
// DIAGNOSTIC and makes the phone's own odometer the headline distance_km.
// ---------------------------------------------------------------------------
$v3Date = '2026-10-04';
$v3Base = strtotime($v3Date . 'T07:00:00Z');

function makeSessionV3(string $id, string $date, int $seq): array {
    db_execute('INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, start_lat, start_lng, distance_algo_version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, 'employee', $id . '-client', $date, $seq, $date . 'T07:00:00Z', 22.5700, 88.3600,
         Engine::DISTANCE_ALGO_VERSION]);
    return db_fetch_one('SELECT * FROM work_sessions WHERE id = ?', [$id]);
}
function addV3Fix(string $sessionId, array $point, float $accuracy, int $second): void {
    global $fixSeq, $v3Base;
    $ts = gmdate('Y-m-d\TH:i:s\Z', $v3Base + $second);
    db_execute('INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh, source)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$sessionId, 'employee', 'fix-' . (++$fixSeq), $ts, $ts, $point[0], $point[1], $accuracy, 0, 'STREAM']);
}

check(Engine::DISTANCE_ALGO_VERSION === 3, 'A new session must be stamped v3 (the client odometer)');

// A session whose every leg trips the gap gate: five fixes 1 km apart, 90 s
// between them (>= the 60 s warning threshold), good accuracy. v2 verifies none
// of it — the whole 4 km goes to incomplete. This is the bug, faithfully.
$odo = makeSessionV3('odo', $v3Date, 1);
for ($i = 0; $i <= 4; $i++) { addV3Fix('odo', at($base, $i * 1000, 0), 10, $i * 90); }

// Before any odometer report, a v3 session falls back to the derived figure —
// never 0 for want of a report, and here that derived figure IS the collapse.
Engine::refreshSession('odo');
$row = db_fetch_one("SELECT * FROM work_sessions WHERE id = 'odo'");
check($row['client_distance_km'] === null, 'No report yet means a NULL odometer');
near((float) $row['distance_km'], 0.0, 0.01, 'With no report the headline falls back to the derived (collapsed) figure');
near((float) $row['incomplete_distance_km'], 4.0, 0.05, 'The 4 km the gates rejected is still measured as incomplete');

// The phone reports what it actually measured — the real 4 km. The controller
// stores max(stored, reported); here that write is simulated directly, and the
// Engine must now read it as the headline while leaving the diagnostics alone.
db_execute("UPDATE work_sessions SET client_distance_km = 4.0 WHERE id = 'odo'");
Engine::refreshSession('odo');
$row = db_fetch_one("SELECT * FROM work_sessions WHERE id = 'odo'");
near((float) $row['distance_km'], 4.0, 0.0001, 'distance_km must now be the reported odometer, not the derived figure');
near((float) $row['verified_distance_km'], 0.0, 0.01, 'verified_distance_km stays the fix-derived figure — the odometer never overwrites it');
near((float) $row['incomplete_distance_km'], 4.0, 0.05, 'The gap diagnostics are untouched by the odometer');
near((float) $row['gps_coverage_percent'], 0.0, 0.01, 'Coverage still reports the day was all gap');
check(count(gapsOf('odo')) === 4, 'The four gap rows are still recorded for the admin map');

// A second v3 session the same day with clean GPS and a modest odometer, so the
// day is a mix: one collapsed-but-reported session and one clean one.
$odo2 = makeSessionV3('odo2', $v3Date, 2);
for ($i = 0; $i <= 4; $i++) { addV3Fix('odo2', at($base, $i * 200, 0), 10, 10000 + $i * 30); }
db_execute("UPDATE work_sessions SET client_distance_km = 0.8 WHERE id = 'odo2'");

Engine::recompute('employee', $v3Date);

$v3Route = db_fetch_one("SELECT * FROM day_routes WHERE employee_id = 'employee' AND work_date = ?", [$v3Date]);
check((int) $v3Route['distance_algo_version'] === 3, 'A day of v3 sessions must be pinned to v3');
near((float) $v3Route['total_distance_km'], 4.8, 0.05, 'The drawn route headline is the summed odometer (4.0 + 0.8)');
near((float) $v3Route['verified_distance_km'], 0.8, 0.05, 'The day verified figure is the derived sum (0.0 + 0.8), not the odometer');

$v3Day = db_fetch_one("SELECT * FROM attendance_days WHERE employee_id = 'employee' AND work_date = ?", [$v3Date]);
near((float) $v3Day['distance_km'], 4.8, 0.05, 'The day summary headline is the summed odometer');
near((float) $v3Day['distance_km'], (float) $v3Route['total_distance_km'], 0.0001,
    'The day summary and the drawn route must agree on the headline');
near((float) $v3Day['verified_distance_km'], 0.8, 0.05, 'The day verified figure stays the derived sum');

// The live route (open, uncached day) must read the same headline the cache
// holds — the stored odometer sum, not a re-derivation from the raw fixes.
$v3Fixes = db_fetch_all("SELECT f.lat, f.lng, f.accuracy_m, f.recorded_at, f.source
                           FROM location_fixes f JOIN work_sessions s ON s.id = f.session_id
                          WHERE s.employee_id = 'employee' AND s.work_date = ? ORDER BY f.recorded_at, f.id", [$v3Date]);
near(Engine::liveDayDistanceKm('employee', $v3Date, $v3Fixes), 4.8, 0.05,
    'The live headline must equal the cached odometer sum, not the fix-derived figure');

fwrite(STDOUT, "tracking-distance: all checks passed\n");
