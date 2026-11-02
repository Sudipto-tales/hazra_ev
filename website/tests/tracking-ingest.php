<?php
// Run: php tests/tracking-ingest.php. Never connects to an application database.
//
// Covers the P0.2 behaviour change: a fix worse than min_accuracy_metres is no
// longer rejected with reason ACCURACY — it is stored like any other fix so a
// weak-GPS stretch still leaves a trail. Low-accuracy is derived at read time
// from the stored accuracy_m against config.min_accuracy_metres, not frozen in
// a column, so a later threshold change stays honest. JUMP, DUPLICATE and
// MALFORMED are unchanged, and the {accepted, rejected} wire shape is unchanged.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../api/support/bootstrap.php';
function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare($sql); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');
foreach (glob(__DIR__ . '/../database/migrations/00*.php') as $file) {
    $before = get_declared_classes(); require_once $file;
    foreach (array_diff(get_declared_classes(), $before) as $class) if (is_subclass_of($class, Migration::class)) (new $class($pdo))->up();
}

$now = '2026-10-02T08:00:00Z';
db_execute('INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)', ['org', 'Test', 'Asia/Kolkata', $now]);
db_execute('INSERT INTO users (id, org_id, role, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    ['employee', 'org', 'employee', 'Field Rep', 'rep@test.example', password_hash('password123', PASSWORD_BCRYPT), $now, $now]);

Ctx::impersonate(['id' => 'employee', 'org_id' => 'org', 'role' => 'employee']);

// Default config materialises on first read: min_accuracy_metres = 50, max_jump_kmh = 180.
$minAccuracy = (float) Ctx::config()['min_accuracy_metres'];
check($minAccuracy === 50.0, 'Expected the documented default accuracy threshold');

db_execute('INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, start_lat, start_lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    ['session', 'employee', 'session-client', '2026-10-02', 1, '2026-10-02T07:00:00Z', 22.5700, 88.3600]);
$session = db_fetch_one("SELECT * FROM work_sessions WHERE id = 'session'");

// A fix already on record so a replay in the batch collides as DUPLICATE, and
// so the first real fix is not read as a JUMP from an empty chain.
db_execute('INSERT INTO location_fixes (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
    ['session', 'employee', 'dup-existing', '2026-10-02T06:59:00Z', $now, 22.5700, 88.3600, 10, 0]);

$fixes = [
    ['id' => 'good',         'latitude' => 22.5700, 'longitude' => 88.3600, 'accuracy' => 10,  'speedKmh' => 0, 'recordedAt' => '2026-10-02T07:00:00Z'],
    ['id' => 'dup-existing', 'latitude' => 22.5700, 'longitude' => 88.3600, 'accuracy' => 10,  'speedKmh' => 0, 'recordedAt' => '2026-10-02T07:00:30Z'],
    // 120 m > 50 m threshold: this is the fix that used to vanish as ACCURACY.
    ['id' => 'lowacc',       'latitude' => 22.5705, 'longitude' => 88.3605, 'accuracy' => 120, 'speedKmh' => 0, 'recordedAt' => '2026-10-02T07:01:00Z'],
    // ~150 km in 60 s — impossible speed, still a JUMP.
    ['id' => 'jump',         'latitude' => 23.5700, 'longitude' => 89.3600, 'accuracy' => 10,  'speedKmh' => 0, 'recordedAt' => '2026-10-02T07:02:00Z'],
    // No latitude at all — MALFORMED.
    ['id' => 'bad',          'longitude' => 88.3600, 'accuracy' => 10, 'speedKmh' => 0, 'recordedAt' => '2026-10-02T07:03:00Z'],
];

$result = Engine::ingest($session, $fixes);

// Wire shape is exactly the contract — nothing added, nothing renamed.
check(array_keys($result) === ['accepted', 'rejected'], 'Response shape changed');

// The low-accuracy fix now lands instead of disappearing.
check(in_array('lowacc', $result['accepted'], true), 'Low-accuracy fix must be accepted, not dropped');
check(in_array('good', $result['accepted'], true), 'The clean fix must still be accepted');
check(count($result['accepted']) === 2, 'Exactly the two valid fixes should be accepted');

$reasons = [];
foreach ($result['rejected'] as $row) { $reasons[$row['id']] = $row['reason']; }
check(($reasons['dup-existing'] ?? null) === 'DUPLICATE', 'Replay must still be DUPLICATE');
check(($reasons['jump'] ?? null) === 'JUMP', 'Teleport must still be JUMP');
check(($reasons['bad'] ?? null) === 'MALFORMED', 'Missing coordinate must still be MALFORMED');
check(count($result['rejected']) === 3, 'Only DUPLICATE/JUMP/MALFORMED should be rejected');

// ACCURACY is gone as a rejection reason entirely.
foreach ($result['rejected'] as $row) { check($row['reason'] !== 'ACCURACY', 'ACCURACY must no longer reject a fix'); }

// The low-accuracy fix is genuinely stored, with its real accuracy intact.
$stored = db_fetch_one("SELECT * FROM location_fixes WHERE employee_id = 'employee' AND client_id = 'lowacc'");
check($stored !== false && $stored !== null, 'Low-accuracy fix was not stored');
check((float) $stored['accuracy_m'] === 120.0, 'Stored accuracy_m was altered');

// Rejected fixes left no row behind.
check(db_fetch_one("SELECT 1 FROM location_fixes WHERE client_id = 'jump'") === false, 'A JUMP fix must not be stored');
check(db_fetch_one("SELECT 1 FROM location_fixes WHERE client_id = 'bad'") === false, 'A MALFORMED fix must not be stored');

// Low-accuracy is DERIVED at read time: accuracy_m vs the current threshold.
$good = db_fetch_one("SELECT accuracy_m FROM location_fixes WHERE employee_id = 'employee' AND client_id = 'good'");
check((float) $stored['accuracy_m'] > $minAccuracy, 'Low-accuracy fix should derive as low-accuracy');
check((float) $good['accuracy_m'] <= $minAccuracy, 'Clean fix should not derive as low-accuracy');

// Read-time semantics: retuning the threshold reclassifies the SAME stored row
// without any rewrite — which a frozen column could not do.
db_execute("UPDATE tracking_configs SET min_accuracy_metres = 150 WHERE org_id = 'org'");
Ctx::impersonate(['id' => 'employee', 'org_id' => 'org', 'role' => 'employee']); // drop the cached config
$relaxed = (float) Ctx::config()['min_accuracy_metres'];
check((float) $stored['accuracy_m'] <= $relaxed, 'A relaxed threshold must reclassify the stored fix as acceptable');

fwrite(STDOUT, "tracking-ingest: all checks passed\n");
