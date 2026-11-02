<?php
// Run: php tests/config-v8.php. Uses a throwaway file-backed SQLite DB so the
// forked request children can share it; never touches an application database.
//
// Covers TrackingConfig version 8 (02-API-PLAN.md §3.12, 03-DATABASE-SCHEMA §4):
// the two new server-tunable knobs syncIntervalSeconds (300) and
// distanceFilterMetres (20), served through GET /config at version 8 on the
// existing ETag path, settable through the admin PUT /config path, and the
// cross-field rule syncIntervalSeconds <= offlineThresholdMinutes × 60 / 2
// enforced as a 422 up front and a DB CHECK backstop (migration 031).
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
$DBFILE = tempnam(sys_get_temp_dir(), 'cfgv8') . '.sqlite';

$pdo = new PDO('sqlite:' . $DBFILE, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');
Dialect::boot($pdo, 'sqlite');

// --- Stage A: every migration applies cleanly, including 031. -----------------
foreach (migration_files() as $file) {
    $class = migration_class($file);
    if ($class !== null && is_subclass_of($class, Migration::class)) {
        (new $class($pdo))->up();
    }
}
$cols = fn(string $t) => array_column($pdo->query("PRAGMA table_info({$t})")->fetchAll(PDO::FETCH_ASSOC), 'name');
$cfgCols = $cols('tracking_configs');
check(in_array('sync_interval_seconds', $cfgCols, true), '031 did not add sync_interval_seconds');
check(in_array('distance_filter_metres', $cfgCols, true), '031 did not add distance_filter_metres');
$histCols = $cols('tracking_config_history');
check(in_array('sync_interval_seconds', $histCols, true), 'history mirror missing sync_interval_seconds');
check(in_array('distance_filter_metres', $histCols, true), 'history mirror missing distance_filter_metres');

// --- Stage B: a pre-v8 row is floored to version 8, columns take defaults. -----
$now = '2026-11-03T08:00:00Z';
db_execute('INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)',
    ['org', 'Hazra EV', 'Asia/Kolkata', $now]);
db_execute('INSERT INTO tracking_configs (org_id, version, offline_threshold_minutes, updated_at) VALUES (?, 3, 10, ?)',
    ['org', $now]);
(new TrackingConfigV8($pdo))->up();                 // idempotent re-run drives the version bump
$row = db_fetch_one("SELECT * FROM tracking_configs WHERE org_id = 'org'");
check((int) $row['version'] === 8, 'version was not floored to 8, got ' . $row['version']);
check((int) $row['sync_interval_seconds'] === 300, 'sync_interval_seconds default should be 300');
check((int) $row['distance_filter_metres'] === 20, 'distance_filter_metres default should be 20');

// --- Stage C: Present::config emits both fields, camelCase, as ints, v8. -------
$present = Present::config($row);
check($present['syncIntervalSeconds'] === 300, 'presenter syncIntervalSeconds wrong');
check($present['distanceFilterMetres'] === 20, 'presenter distanceFilterMetres wrong');
check($present['version'] === 8, 'presenter version should be 8');
check(is_int($present['syncIntervalSeconds']) && is_int($present['distanceFilterMetres']), 'knobs must serialise as ints');

// --- Stage D: the DB CHECK backstop rejects an out-of-range value directly. ----
$checkFired = false;
try { $pdo->exec("UPDATE tracking_configs SET sync_interval_seconds = 9999 WHERE org_id = 'org'"); }
catch (PDOException) { $checkFired = true; }
check($checkFired, 'DB CHECK did not reject sync_interval_seconds above the window');
$still = db_fetch_one("SELECT sync_interval_seconds FROM tracking_configs WHERE org_id = 'org'");
check((int) $still['sync_interval_seconds'] === 300, 'rejected write must leave the stored value untouched');

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

// --- Stage E: GET /config returns version 8 with both fields. ------------------
$get = request([], [], $employee, 'show');
check($get['status'] === 200, 'GET /config status should be 200, got ' . $get['status']);
check($get['json']['data']['version'] === 8, 'GET /config version should be 8');
check($get['json']['data']['syncIntervalSeconds'] === 300, 'GET /config missing syncIntervalSeconds');
check($get['json']['data']['distanceFilterMetres'] === 20, 'GET /config missing distanceFilterMetres');

// The ETag the client caches is config-<org>-<version>; a matching If-None-Match
// is a 304 through the same freshness() path every config field already uses.
$notMod = request(['HTTP_IF_NONE_MATCH' => '"config-org-8"'], [], $employee, 'show');
check($notMod['status'] === 304, 'matching ETag should 304, got ' . $notMod['status']);
check($notMod['body'] === '', '304 must carry no body');

// --- Stage F: out-of-range syncIntervalSeconds is a 422. -----------------------
$bad = request($formHdr, ['syncIntervalSeconds' => 400], $admin, 'update'); // offline stays 10 → ceiling 300
check($bad['status'] === 422, 'over-window syncIntervalSeconds should be 422, got ' . $bad['status']);
check($bad['json']['error']['code'] === 'VALIDATION_FAILED', 'wrong error code on rejection');
check($bad['json']['error']['field'] === 'syncIntervalSeconds', 'rejection should name the field');

// The pair is validated together: lowering offlineThresholdMinutes tightens the
// ceiling on the same write (4 min → 120s), so sync=200 is rejected.
$pair = request($formHdr, ['offlineThresholdMinutes' => 4, 'syncIntervalSeconds' => 200], $admin, 'update');
check($pair['status'] === 422, 'pair rule (offline lowered) should 422, got ' . $pair['status']);

// --- Stage G: a valid write succeeds and lands in history. ---------------------
$ok = request($formHdr, ['offlineThresholdMinutes' => 20, 'syncIntervalSeconds' => 500, 'distanceFilterMetres' => 35], $admin, 'update');
check($ok['status'] === 200, 'valid config write should 200, got ' . $ok['status'] . ' ' . $ok['body']);
check($ok['json']['data']['syncIntervalSeconds'] === 500, 'write did not apply syncIntervalSeconds');
check($ok['json']['data']['distanceFilterMetres'] === 35, 'write did not apply distanceFilterMetres');
check($ok['json']['data']['version'] === 9, 'a write must bump version 8 → 9');

$pdo = new PDO('sqlite:' . $DBFILE, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stored = db_fetch_one("SELECT * FROM tracking_configs WHERE org_id = 'org'");
check((int) $stored['sync_interval_seconds'] === 500, 'stored syncIntervalSeconds not persisted');
check((int) $stored['distance_filter_metres'] === 35, 'stored distanceFilterMetres not persisted');
$hist = db_fetch_one("SELECT * FROM tracking_config_history WHERE org_id = 'org' ORDER BY version DESC LIMIT 1");
check((int) $hist['sync_interval_seconds'] === 500, 'history mirror did not record syncIntervalSeconds');
check((int) $hist['distance_filter_metres'] === 35, 'history mirror did not record distanceFilterMetres');

@unlink($DBFILE);
echo "config-v8: all checks passed\n";
