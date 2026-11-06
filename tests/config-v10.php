<?php
// Run: php tests/config-v10.php. Uses a throwaway file-backed SQLite DB so the
// forked request children can share it; never touches an application database.
//
// Covers migration 036 — the universal distance engine's contract half, the
// companion to tests/config-v9.php:
//
//   * the five new tracking_configs knobs and their history mirror
//   * that there is NO separate moderate/poor threshold column — that edge is
//     minAccuracyMetres, and a second column for it would be two sources of
//     truth for one fact
//   * the new defaults a materialised row takes (10 s / 10 m / 30 m, v10)
//   * the four DB CHECK backstops
//   * the two new 422 rules and the two new clamps on PUT /config
//   * the retirement of highAccuracyMode / syncOnMobileData / batterySaver as
//     per-employee settings: no longer writable, still on the wire as fixed
//     values so an app build already in the field reads the behaviour it
//     should now have
//
// The distance arithmetic itself is tests/tracking-distance.php §8.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../config/migrate.php';
require_once __DIR__ . '/../core/ApiRequest.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../api/support/bootstrap.php';
require_once __DIR__ . '/../api/controllers/ConfigController.php';
require_once __DIR__ . '/../api/controllers/MeController.php';

function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare(Dialect::dml($sql)); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$DBFILE = tempnam(sys_get_temp_dir(), 'cfgv10') . '.sqlite';
$pdo = new PDO('sqlite:' . $DBFILE, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');
Dialect::boot($pdo, 'sqlite');

// --- Stage A: the whole ledger applies, 036 included. -------------------------
foreach (migration_files() as $file) {
    $class = migration_class($file);
    if ($class !== null && is_subclass_of($class, Migration::class)) {
        (new $class($pdo))->up();
    }
}
check(class_exists('UniversalDistanceEngine'), '036 must declare UniversalDistanceEngine');

$cols = fn(string $t) => array_column($pdo->query("PRAGMA table_info({$t})")->fetchAll(PDO::FETCH_ASSOC), 'name');

$cfgCols = $cols('tracking_configs');
$histCols = $cols('tracking_config_history');
foreach ([
    'min_step_metres', 'accuracy_good_metres', 'accuracy_step_factor',
    'gps_gap_warning_seconds', 'gps_gap_incomplete_seconds',
] as $column) {
    check(in_array($column, $cfgCols, true), "036 did not add tracking_configs.{$column}");
    check(in_array($column, $histCols, true), "history mirror missing {$column}");
}

// The column that deliberately does not exist. minAccuracyMetres is the
// moderate/poor edge and already means exactly that; a second column for the
// same boundary could drift until the engine's "poor" and the admin map's
// "rough" disagreed about the same fix.
check(!in_array('accuracy_moderate_metres', $cfgCols, true),
    'There must be no separate moderate/poor column — min_accuracy_metres is that edge');

// min_step_metres is NOT distance_filter_metres. One column was doing the
// device's capture filter and the server's measurement floor, and those want
// opposite values; both must now exist, separately.
check(in_array('distance_filter_metres', $cfgCols, true),
    'distance_filter_metres must survive as the device capture filter');

check(in_array('source', $cols('location_fixes'), true), '036 did not add location_fixes.source');
foreach (['work_sessions', 'day_routes'] as $table) {
    check(in_array('distance_algo_version', $cols($table), true), "036 did not add {$table}.distance_algo_version");
}
foreach (['work_sessions', 'day_routes', 'attendance_days'] as $table) {
    foreach (['verified_distance_km', 'incomplete_distance_km', 'gps_coverage_percent'] as $column) {
        check(in_array($column, $cols($table), true), "036 did not add {$table}.{$column}");
    }
}
check($cols('session_gps_gaps') !== [], '036 did not create session_gps_gaps');
check($cols('session_transport_modes') !== [], '036 did not create session_transport_modes');

// --- Stage B: a materialised row takes the new documented defaults. -----------
$now = '2026-11-06T08:00:00Z';
db_execute('INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)', ['org', 'Test', 'Asia/Kolkata', $now]);
db_execute('INSERT INTO users (id, org_id, role, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    ['admin', 'org', 'admin', 'Admin', 'admin@test.example', password_hash('password123', PASSWORD_BCRYPT), $now, $now]);

Ctx::impersonate(['id' => 'admin', 'org_id' => 'org', 'role' => 'admin']);
$config = Ctx::config();

// Capture went DOWN so a winding track keeps its corners; measurement did not
// follow it down, because a parked phone drifts ~13 m at 15 m accuracy.
check((int) $config['location_interval_seconds'] === 10, 'A new row must take the 10 s capture interval');
check((int) $config['distance_filter_metres'] === 10, 'A new row must take the 10 m capture filter');
check((float) $config['min_accuracy_metres'] === 30.0, 'A new row must take the 30 m accuracy threshold');
check((float) $config['min_step_metres'] === 15.0, 'A new row must take the 15 m measurement step');
check((float) $config['accuracy_good_metres'] === 20.0, 'A new row must take the 20 m good band');
check((float) $config['accuracy_step_factor'] === 1.0, 'A new row must take a 1.0 step factor');
check((int) $config['gps_gap_warning_seconds'] === 60, 'A new row must take the 60 s gap warning');
check((int) $config['gps_gap_incomplete_seconds'] === 180, 'A new row must take the 180 s incomplete threshold');
check((int) $config['version'] === 10, 'A materialised row must be at version 10 so every held ETag misses');

// --- Stage C: Present::config emits all five, camelCase and typed. ------------
$wire = Present::config($config);
foreach ([
    'minStepMetres' => 15.0, 'accuracyGoodMetres' => 20.0, 'accuracyStepFactor' => 1.0,
    'gpsGapWarningSeconds' => 60, 'gpsGapIncompleteSeconds' => 180,
] as $key => $expected) {
    check(array_key_exists($key, $wire), "Present::config omits {$key}");
    check($wire[$key] === $expected, "Present::config {$key} should be " . var_export($expected, true)
        . ', got ' . var_export($wire[$key], true));
}
check(!array_key_exists('accuracyModerateMetres', $wire), 'The contract must not carry a moderate threshold');

// --- Stage D: the DB CHECK backstops reject a direct UPDATE. ------------------
foreach ([
    'accuracy_good_metres = 999'       => 'good band above the poor edge',
    'gps_gap_incomplete_seconds = 10'  => 'incomplete threshold below the warning',
    'accuracy_step_factor = -1'        => 'negative step factor',
    'min_step_metres = -5'             => 'negative measurement step',
] as $set => $what) {
    $rejected = false;
    try { $pdo->exec("UPDATE tracking_configs SET {$set} WHERE org_id = 'org'"); }
    catch (PDOException) { $rejected = true; }
    check($rejected, "The DB CHECK must reject {$what}");
}

/** Runs one request in a forked child so the Envelope's exit() does not end the suite. */
function request(string $controller, string $method, array $server, array $post, array $principal): array {
    global $DBFILE;
    $tmp = tempnam(sys_get_temp_dir(), 'resp');
    $pid = pcntl_fork();
    if ($pid === 0) {
        $GLOBALS['pdo'] = new PDO('sqlite:' . $DBFILE, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $GLOBALS['pdo']->exec('PRAGMA foreign_keys = ON');
        Dialect::boot($GLOBALS['pdo'], 'sqlite');
        $_SERVER = array_merge($_SERVER, $server);
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

$admin    = ['id' => 'admin', 'org_id' => 'org', 'role' => 'admin'];
$employee = ['id' => 'admin', 'org_id' => 'org', 'role' => 'employee'];
$formHdr  = ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'];
$cfg = fn() => db_fetch_one("SELECT * FROM tracking_configs WHERE org_id = 'org'");

// --- Stage E: the two new 422 rules. ------------------------------------------
//
// A 422 rather than a clamp, for the reason the v9 pair rules are: an admin who
// typed a number that cannot hold has to be told, not quietly given another.
$bad = request('ConfigController', 'update', $formHdr, ['accuracyGoodMetres' => 999], $admin);
check($bad['status'] === 422, 'A good band above minAccuracyMetres should be 422, got ' . $bad['status']);
check(($bad['json']['error']['field'] ?? null) === 'accuracyGoodMetres', 'The 422 must name accuracyGoodMetres');

$bad = request('ConfigController', 'update', $formHdr, ['gpsGapIncompleteSeconds' => 30], $admin);
check($bad['status'] === 422, 'An incomplete threshold under the warning should be 422, got ' . $bad['status']);

// Both sides moving at once is judged on what the row is about to BECOME, which
// is the post-write discipline the v9 rules already follow.
$ok = request('ConfigController', 'update', $formHdr,
    ['accuracyGoodMetres' => 60, 'minAccuracyMetres' => 80], $admin);
check($ok['status'] === 200, 'Moving both sides of the band together must succeed, got ' . $ok['status']);
check((float) $cfg()['accuracy_good_metres'] === 60.0, 'The widened good band did not land');

// --- Stage F: the two new clamps — never an error, always acknowledged. -------
$res = request('ConfigController', 'update', $formHdr, ['accuracyStepFactor' => 99], $admin);
check($res['status'] === 200, 'An out-of-range step factor must clamp, not fail: ' . $res['status']);
check($res['json']['data']['accuracyStepFactor'] === 5.0, 'The response must acknowledge the clamped 5.0');
check((float) $cfg()['accuracy_step_factor'] === 5.0, 'The clamped step factor must be what was stored');

$res = request('ConfigController', 'update', $formHdr, ['accuracyStepFactor' => -3], $admin);
check($res['json']['data']['accuracyStepFactor'] === 0.0, 'A negative step factor must clamp to 0, which disables scaling');

$res = request('ConfigController', 'update', $formHdr, ['minStepMetres' => 9999], $admin);
check($res['json']['data']['minStepMetres'] === 500.0, 'An absurd measurement step must clamp to 500 m');

// --- Stage G: a valid write lands and history mirrors all five. ---------------
$res = request('ConfigController', 'update', $formHdr, [
    'minStepMetres' => 18, 'accuracyGoodMetres' => 25, 'accuracyStepFactor' => 1.5,
    'gpsGapWarningSeconds' => 90, 'gpsGapIncompleteSeconds' => 240,
], $admin);
check($res['status'] === 200, 'A valid v10 write should be 200, got ' . $res['status']);

$row = $cfg();
check((float) $row['min_step_metres'] === 18.0, 'minStepMetres did not land');
check((float) $row['accuracy_good_metres'] === 25.0, 'accuracyGoodMetres did not land');
check((float) $row['accuracy_step_factor'] === 1.5, 'accuracyStepFactor did not land');
check((int) $row['gps_gap_warning_seconds'] === 90, 'gpsGapWarningSeconds did not land');
check((int) $row['gps_gap_incomplete_seconds'] === 240, 'gpsGapIncompleteSeconds did not land');

$hist = db_fetch_one("SELECT * FROM tracking_config_history WHERE org_id = 'org' ORDER BY version DESC LIMIT 1");
check($hist !== false && $hist !== null, 'The write recorded no history row');
check((float) $hist['min_step_metres'] === 18.0, 'History did not mirror min_step_metres');
check((float) $hist['accuracy_good_metres'] === 25.0, 'History did not mirror accuracy_good_metres');
check((float) $hist['accuracy_step_factor'] === 1.5, 'History did not mirror accuracy_step_factor');
check((int) $hist['gps_gap_warning_seconds'] === 90, 'History did not mirror gps_gap_warning_seconds');
check((int) $hist['gps_gap_incomplete_seconds'] === 240, 'History did not mirror gps_gap_incomplete_seconds');
$payload = json_decode($hist['payload'], true);
check(($payload['min_step_metres'] ?? null) !== null, 'The history payload must carry the whole row too');

// Still admin-only: collection is organisation config, not a device setting.
$denied = request('ConfigController', 'update', $formHdr, ['minStepMetres' => 11], $employee);
check($denied['status'] === 403, 'An employee token must not write config, got ' . $denied['status']);
check((float) $cfg()['min_step_metres'] === 18.0, 'A refused write must change nothing');

// --- Stage H: the three per-employee tracking flags are retired. --------------
//
// They used to let one phone collect less aggressively than the organisation's
// rule. Collection is org config, identical for every employee, so the write
// path is gone — but the fields stay on the wire as fixed values, because
// dropping a field breaks every build already in the field and these are the
// values those builds should now behave as if they had.
db_execute('INSERT INTO user_preferences (user_id) VALUES (?)', ['admin']);

$get = request('MeController', 'preferences', [], [], $admin);
check($get['status'] === 200, 'GET /me/preferences should be 200, got ' . $get['status']);
foreach (['highAccuracyMode' => true, 'syncOnMobileData' => true, 'batterySaver' => false] as $key => $expected) {
    check(array_key_exists($key, $get['json']['data']), "{$key} must stay on the wire for older builds");
    check($get['json']['data'][$key] === $expected, "{$key} must report the fixed uniform value");
}

// An old build trying to throttle itself is answered 200 and ignored: it is not
// making an error, it is sending a field that stopped meaning anything.
$patch = request('MeController', 'updatePreferences', $formHdr,
    ['highAccuracyMode' => '0', 'batterySaver' => '1', 'syncOnMobileData' => '0'], $admin);
check($patch['status'] === 200, 'A write of only retired fields must still be 200, got ' . $patch['status']);
check($patch['json']['data']['highAccuracyMode'] === true, 'highAccuracyMode must not be writable');
check($patch['json']['data']['batterySaver'] === false, 'batterySaver must not be writable');
check($patch['json']['data']['syncOnMobileData'] === true, 'syncOnMobileData must not be writable');

$stored = db_fetch_one("SELECT * FROM user_preferences WHERE user_id = 'admin'");
check((int) $stored['high_accuracy_mode'] === 1, 'The stored column must be left at the uniform value');
check((int) $stored['battery_saver'] === 0, 'The stored column must be left at the uniform value');
check((int) $stored['sync_on_mobile_data'] === 1, 'The stored column must be left at the uniform value');

// Real preferences in the same body still work — the retirement must not have
// cost an employee their theme.
$patch = request('MeController', 'updatePreferences', $formHdr,
    ['themeMode' => 'dark', 'batterySaver' => '1'], $admin);
check($patch['json']['data']['themeMode'] === 'dark', 'A real preference beside a retired one must still save');
check($patch['json']['data']['batterySaver'] === false, 'The retired field must still be ignored');

@unlink($DBFILE);
fwrite(STDOUT, "config-v10: all checks passed\n");
