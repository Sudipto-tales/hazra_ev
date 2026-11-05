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

// The documented defaults the gates fall back on: 50 m accuracy (002), 20 m
// distance filter (031 / 02 §3.12). Both are admin-tunable config columns —
// no new column, no new ConfigController::FIELDS entry, no contract change.
$config = Ctx::config();
check((float) $config['min_accuracy_metres'] === 50.0, 'Expected the documented default accuracy threshold');
check((int) $config['distance_filter_metres'] === 20, 'Expected the documented default distance filter');

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
near(Engine::chainDistanceKm($raw), (float) $route['total_distance_km'], 0.0001,
    'The live route sum must match the cached day_routes row');

fwrite(STDOUT, "tracking-distance: all checks passed\n");
