<?php
// Run: php tests/config-v9.php. Uses a throwaway file-backed SQLite DB so the
// forked request children can share it; never touches an application database.
//
// Covers TrackingConfig version 9 (02-API-PLAN.md §3.12, 03-DATABASE-SCHEMA §4):
// the four server-tunable fields syncIntervalSeconds (300), distanceFilterMetres
// (20), movementExitSpeedKmh (3) and liveSyncIntervalSeconds (15), served
// through GET /config at version 9 on the existing ETag path, settable through
// the admin PUT /config path, and the per-rule validation table — which is NOT
// uniform: two rules are a 422 and two clamp. Migration 031 is the retarget of
// the never-deployed v8 file, so this replaces tests/config-v8.php.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../config/migrate.php';
require_once __DIR__ . '/../core/ApiRequest.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../api/support/bootstrap.php';
require_once __DIR__ . '/../api/controllers/ConfigController.php';

function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare(Dialect::dml($sql)); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

// NB: no register_shutdown_function for cleanup — a forked child would inherit
// it and delete the shared DB on its own exit. The file is unlinked at the end.
$DBFILE = tempnam(sys_get_temp_dir(), 'cfgv9') . '.sqlite';

$pdo = new PDO('sqlite:' . $DBFILE, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');
Dialect::boot($pdo, 'sqlite');

// --- Stage A: every migration applies cleanly, including the retargeted 031. --
foreach (migration_files() as $file) {
    $class = migration_class($file);
    if ($class !== null && is_subclass_of($class, Migration::class)) {
        (new $class($pdo))->up();
    }
}
check(class_exists('TrackingConfigV9'), '031 must declare TrackingConfigV9 — the v8 file is retargeted, not shipped');

$cols = fn(string $t) => array_column($pdo->query("PRAGMA table_info({$t})")->fetchAll(PDO::FETCH_ASSOC), 'name');
$cfgCols = $cols('tracking_configs');
foreach (['sync_interval_seconds', 'distance_filter_metres', 'movement_exit_speed_kmh', 'live_sync_interval_seconds'] as $column) {
    check(in_array($column, $cfgCols, true), "031 did not add tracking_configs.{$column}");
    check(in_array($column, $cols('tracking_config_history'), true), "history mirror missing {$column}");
}

// The companion storage, in the same migration and the same deploy (§14).
check(in_array('heading_deg', $cols('location_fixes'), true), '031 did not add location_fixes.heading_deg');
$watchCols = $cols('tracking_watches');
check($watchCols !== [], '031 did not create tracking_watches');
check($watchCols === ['employee_id', 'watching_until', 'updated_at', 'updated_by'], 'tracking_watches shape is not §4a: ' . implode(',', $watchCols));
$watchPk = array_values(array_filter(
    $pdo->query('PRAGMA table_info(tracking_watches)')->fetchAll(PDO::FETCH_ASSOC),
    fn(array $c) => (int) $c['pk'] === 1,
));
check(count($watchPk) === 1 && $watchPk[0]['name'] === 'employee_id', 'tracking_watches must be keyed per employee, not per admin');
$watchIdx = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'tracking_watches'")->fetchAll(PDO::FETCH_COLUMN);
check(in_array('idx_tracking_watches_until', $watchIdx, true), 'missing the watching_until index (§4a housekeeping)');

// --- Stage B: a pre-v9 row is floored to version 9, columns take defaults. -----
$now = '2026-11-04T08:00:00Z';
db_execute('INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)',
    ['org', 'Hazra EV', 'Asia/Kolkata', $now]);
db_execute('INSERT INTO tracking_configs (org_id, version, offline_threshold_minutes, updated_at) VALUES (?, 7, 10, ?)',
    ['org', $now]);
(new TrackingConfigV9($pdo))->up();                 // idempotent re-run drives the version floor
$row = db_fetch_one("SELECT * FROM tracking_configs WHERE org_id = 'org'");
check((int) $row['version'] === 9, 'version was not floored to 9, got ' . $row['version']);
check((int) $row['sync_interval_seconds'] === 300, 'sync_interval_seconds default should be 300');
check((int) $row['distance_filter_metres'] === 20, 'distance_filter_metres default should be 20');
check((float) $row['movement_exit_speed_kmh'] === 3.0, 'movement_exit_speed_kmh default should be 3');
check((int) $row['live_sync_interval_seconds'] === 15, 'live_sync_interval_seconds default should be 15');

// A row already past 9 is bumped rather than moved backwards, so the ETag still
// changes — the whole point of the floor.
db_execute("UPDATE tracking_configs SET version = 42 WHERE org_id = 'org'");
(new TrackingConfigV9($pdo))->up();
check((int) db_fetch_one("SELECT version FROM tracking_configs WHERE org_id = 'org'")['version'] === 43, 'a version above 9 must bump, never regress');
db_execute("UPDATE tracking_configs SET version = 9 WHERE org_id = 'org'");
$row = db_fetch_one("SELECT * FROM tracking_configs WHERE org_id = 'org'");

// --- Stage C: Present::config emits all four, camelCase, typed, at v9. ---------
$present = Present::config($row);
check($present['syncIntervalSeconds'] === 300, 'presenter syncIntervalSeconds wrong');
check($present['distanceFilterMetres'] === 20, 'presenter distanceFilterMetres wrong');
check($present['movementExitSpeedKmh'] === 3.0, 'presenter movementExitSpeedKmh wrong');
check($present['liveSyncIntervalSeconds'] === 15, 'presenter liveSyncIntervalSeconds wrong');
check($present['version'] === 9, 'presenter version should be 9');
check(is_int($present['syncIntervalSeconds']) && is_int($present['distanceFilterMetres'])
    && is_int($present['liveSyncIntervalSeconds']), 'the three second/metre knobs must serialise as ints');
check(is_float($present['movementExitSpeedKmh']), 'movementExitSpeedKmh is a double, not an int');

// A pre-migration row (no columns at all) must still serve the documented
// defaults rather than a 0 — a 0 s drain timer would be a device-killer.
$bare = Present::config(['location_interval_seconds' => 30, 'min_accuracy_metres' => 50, 'stop_radius_metres' => 75,
    'stop_threshold_minutes' => 10, 'long_stop_threshold_minutes' => 45, 'movement_speed_threshold_kmh' => 2,
    'max_jump_kmh' => 180, 'offline_threshold_minutes' => 10, 'location_unavailable_threshold_minutes' => 15,
    'sync_batch_size' => 50, 'version' => 7, 'updated_at' => $now, 'updated_by' => null]);
check($bare['movementExitSpeedKmh'] === 3.0 && $bare['liveSyncIntervalSeconds'] === 15, 'missing columns must fall back to the documented defaults');

// --- Stage D: the DB CHECK backstops reject a direct UPDATE. -------------------
$rejects = function (string $sql, string $message) use ($pdo) {
    try { $pdo->exec($sql); }
    catch (PDOException) { return; }
    throw new RuntimeException($message);
};
$rejects("UPDATE tracking_configs SET sync_interval_seconds = 9999 WHERE org_id = 'org'",
    'DB CHECK did not reject sync_interval_seconds above the offline window');
$rejects("UPDATE tracking_configs SET movement_exit_speed_kmh = 1 WHERE org_id = 'org'",
    'DB CHECK did not reject an inverted movement dead band');
$rejects("UPDATE tracking_configs SET live_sync_interval_seconds = 200 WHERE org_id = 'org'",
    'DB CHECK did not reject live_sync_interval_seconds outside 5..120');
$rejects("UPDATE tracking_configs SET live_sync_interval_seconds = 300 WHERE org_id = 'org'",
    'DB CHECK did not reject a live interval at the normal interval');
$still = db_fetch_one("SELECT * FROM tracking_configs WHERE org_id = 'org'");
check((int) $still['sync_interval_seconds'] === 300 && (int) $still['live_sync_interval_seconds'] === 15
    && (float) $still['movement_exit_speed_kmh'] === 3.0, 'a rejected write must leave every stored value untouched');

// An admin account for the PUT path's requireAdmin().
db_execute('INSERT INTO users (id, org_id, role, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    ['admin', 'org', 'admin', 'Boss', 'boss@test.example', password_hash('x', PASSWORD_BCRYPT), $now, $now]);

/** Runs one request in a forked child so the Envelope's exit() does not end the suite. */
function request(array $server, array $post, array $principal, string $method): array {
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
            ob_end_clean();                 // swallow it so nothing reaches the terminal
            file_put_contents($tmp, json_encode(['status' => $code === false ? 200 : $code, 'body' => $body]));
        });
        (new ConfigController())->{$method}();
        exit;
    }
    pcntl_waitpid($pid, $st);
    $out = json_decode(file_get_contents($tmp), true);
    @unlink($tmp);
    $out['json'] = $out['body'] === '' ? null : json_decode($out['body'], true);
    return $out;
}

$employee = ['id' => 'admin', 'org_id' => 'org', 'role' => 'employee'];
$admin    = ['id' => 'admin', 'org_id' => 'org', 'role' => 'admin'];
$formHdr  = ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'];

// --- Stage E: GET /config returns version 9 with all four fields. --------------
$get = request([], [], $employee, 'show');
check($get['status'] === 200, 'GET /config status should be 200, got ' . $get['status']);
check($get['json']['data']['version'] === 9, 'GET /config version should be 9');
check($get['json']['data']['syncIntervalSeconds'] === 300, 'GET /config missing syncIntervalSeconds');
check($get['json']['data']['distanceFilterMetres'] === 20, 'GET /config missing distanceFilterMetres');
check($get['json']['data']['movementExitSpeedKmh'] === 3.0, 'GET /config missing movementExitSpeedKmh');
check($get['json']['data']['liveSyncIntervalSeconds'] === 15, 'GET /config missing liveSyncIntervalSeconds');

// The ETag the client caches is config-<org>-<version>, so a v7 or v8 ETag now
// misses and a matching v9 one is still a 304 through the same freshness() path.
$stale = request(['HTTP_IF_NONE_MATCH' => '"config-org-7"'], [], $employee, 'show');
check($stale['status'] === 200, 'a held v7 ETag must MISS after the bump, got ' . $stale['status']);
$notMod = request(['HTTP_IF_NONE_MATCH' => '"config-org-9"'], [], $employee, 'show');
check($notMod['status'] === 304, 'matching ETag should 304, got ' . $notMod['status']);
check($notMod['body'] === '', '304 must carry no body');

// --- Stage F: the two 422 rules. ----------------------------------------------
$bad = request($formHdr, ['syncIntervalSeconds' => 400], $admin, 'update'); // offline stays 10 → ceiling 300
check($bad['status'] === 422, 'over-window syncIntervalSeconds should be 422, got ' . $bad['status']);
check($bad['json']['error']['code'] === 'VALIDATION_FAILED', 'wrong error code on rejection');
check($bad['json']['error']['field'] === 'syncIntervalSeconds', 'rejection should name the field');

// The pair is validated together against POST-WRITE values: lowering
// offlineThresholdMinutes tightens the ceiling on the same write (4 min → 120s).
$pair = request($formHdr, ['offlineThresholdMinutes' => 4, 'syncIntervalSeconds' => 200], $admin, 'update');
check($pair['status'] === 422, 'pair rule (offline lowered) should 422, got ' . $pair['status']);

// An inverted dead band is a 422 on movementExitSpeedKmh, not a clamp —
// whichever side of the pair the write moved.
$inverted = request($formHdr, ['movementExitSpeedKmh' => 1], $admin, 'update');
check($inverted['status'] === 422, 'exit below threshold should 422, got ' . $inverted['status']);
check($inverted['json']['error']['field'] === 'movementExitSpeedKmh', '422 must name movementExitSpeedKmh');
$invertedOther = request($formHdr, ['movementSpeedThresholdKmh' => 10], $admin, 'update');
check($invertedOther['status'] === 422, 'raising the lower edge past the stored upper edge should 422');
check($invertedOther['json']['error']['field'] === 'movementExitSpeedKmh', 'the pair rule names movementExitSpeedKmh either way');

// Equality is legal: it means "no dead band", i.e. the pre-v9 behaviour.
$equal = request($formHdr, ['movementSpeedThresholdKmh' => 3, 'movementExitSpeedKmh' => 3], $admin, 'update');
check($equal['status'] === 200, 'an equal pair is a legal zero-width band, got ' . $equal['status']);
$back = request($formHdr, ['movementSpeedThresholdKmh' => 2, 'movementExitSpeedKmh' => 3], $admin, 'update');
check($back['status'] === 200, 'restoring the default band should succeed');

// Garbage is never clamped — non-numeric input on any of the four is a 422.
foreach (['movementExitSpeedKmh', 'liveSyncIntervalSeconds', 'syncIntervalSeconds', 'distanceFilterMetres'] as $field) {
    $garbage = request($formHdr, [$field => 'fast'], $admin, 'update');
    check($garbage['status'] === 422, "non-numeric {$field} should 422, got " . $garbage['status']);
    check($garbage['json']['error']['field'] === $field, "non-numeric {$field} must name the field");
}

// --- Stage G: the two clamped rules — never an error, always acknowledged. -----
$clampHigh = request($formHdr, ['movementExitSpeedKmh' => 99], $admin, 'update');
check($clampHigh['status'] === 200, 'out-of-range movementExitSpeedKmh must clamp, not fail');
check($clampHigh['json']['data']['movementExitSpeedKmh'] === 50.0, 'movementExitSpeedKmh should clamp to 50, got ' . json_encode($clampHigh['json']['data']['movementExitSpeedKmh']));
// The two kinds of rule compose in that order: the range clamps first, then the
// pair rule judges the CLAMPED value. -5 clamps to 0, and 0 is below the stored
// lower edge (2), so it is a 422 naming movementExitSpeedKmh — not a silent 0
// that would invert the band and make the hold case unreachable.
$clampLow = request($formHdr, ['movementExitSpeedKmh' => -5], $admin, 'update');
check($clampLow['status'] === 422, 'a negative exit speed clamps to 0, which then fails the pair rule');
check($clampLow['json']['error']['field'] === 'movementExitSpeedKmh', 'the clamped-then-invalid value must name its own field');
check((float) db_fetch_one("SELECT movement_exit_speed_kmh FROM tracking_configs WHERE org_id = 'org'")['movement_exit_speed_kmh'] === 50.0,
    'a rejected write must leave the previously clamped 50 in place');

$liveLow = request($formHdr, ['liveSyncIntervalSeconds' => 1], $admin, 'update');
check($liveLow['status'] === 200, 'out-of-range liveSyncIntervalSeconds must clamp, not fail');
check($liveLow['json']['data']['liveSyncIntervalSeconds'] === 5, 'liveSyncIntervalSeconds should clamp up to 5');
$liveHigh = request($formHdr, ['liveSyncIntervalSeconds' => 9000], $admin, 'update');
check($liveHigh['json']['data']['liveSyncIntervalSeconds'] === 120, 'liveSyncIntervalSeconds should clamp down to 120');

// "at or above syncIntervalSeconds" clamps to syncIntervalSeconds - 1.
$below = request($formHdr, ['syncIntervalSeconds' => 60, 'liveSyncIntervalSeconds' => 90], $admin, 'update');
check($below['status'] === 200, 'a live interval above the normal one must clamp, not fail');
check($below['json']['data']['liveSyncIntervalSeconds'] === 59, 'live should clamp to syncIntervalSeconds - 1, got ' . $below['json']['data']['liveSyncIntervalSeconds']);

// And the same clamp when only the NORMAL interval moves under the stored live
// one — otherwise the DB CHECK would fail a save the contract says must not fail.
$narrow = request($formHdr, ['syncIntervalSeconds' => 30], $admin, 'update');
check($narrow['status'] === 200, 'lowering syncIntervalSeconds under the stored live value must still save');
check($narrow['json']['data']['liveSyncIntervalSeconds'] === 29, 'the stored live value should follow down to 29, got ' . $narrow['json']['data']['liveSyncIntervalSeconds']);

// --- Stage H: a valid write lands, and history mirrors all four. ---------------
$ok = request($formHdr, [
    'offlineThresholdMinutes' => 20, 'syncIntervalSeconds' => 500, 'distanceFilterMetres' => 35,
    'movementExitSpeedKmh' => 4.5, 'liveSyncIntervalSeconds' => 20,
], $admin, 'update');
check($ok['status'] === 200, 'valid config write should 200, got ' . $ok['status'] . ' ' . $ok['body']);
check($ok['json']['data']['syncIntervalSeconds'] === 500, 'write did not apply syncIntervalSeconds');
check($ok['json']['data']['distanceFilterMetres'] === 35, 'write did not apply distanceFilterMetres');
check($ok['json']['data']['movementExitSpeedKmh'] === 4.5, 'write did not apply movementExitSpeedKmh');
check($ok['json']['data']['liveSyncIntervalSeconds'] === 20, 'write did not apply liveSyncIntervalSeconds');
check($ok['json']['data']['version'] > 9, 'every write must bump version past 9');

$pdo = new PDO('sqlite:' . $DBFILE, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stored = db_fetch_one("SELECT * FROM tracking_configs WHERE org_id = 'org'");
check((int) $stored['sync_interval_seconds'] === 500, 'stored syncIntervalSeconds not persisted');
check((int) $stored['distance_filter_metres'] === 35, 'stored distanceFilterMetres not persisted');
check((float) $stored['movement_exit_speed_kmh'] === 4.5, 'stored movementExitSpeedKmh not persisted');
check((int) $stored['live_sync_interval_seconds'] === 20, 'stored liveSyncIntervalSeconds not persisted');
$hist = db_fetch_one("SELECT * FROM tracking_config_history WHERE org_id = 'org' ORDER BY version DESC LIMIT 1");
check((int) $hist['sync_interval_seconds'] === 500, 'history mirror did not record syncIntervalSeconds');
check((int) $hist['distance_filter_metres'] === 35, 'history mirror did not record distanceFilterMetres');
check((float) $hist['movement_exit_speed_kmh'] === 4.5, 'history mirror did not record movementExitSpeedKmh');
check((int) $hist['live_sync_interval_seconds'] === 20, 'history mirror did not record liveSyncIntervalSeconds');

@unlink($DBFILE);
echo "config-v9: all checks passed\n";
