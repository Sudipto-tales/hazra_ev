<?php
// Run: php tests/admin-dashboard-recent.php. Throwaway SQLite, real migrations.
// Covers AdminController::recentActivity — the CMS dashboard's "latest activity"
// feed, which was hardcoded to [] and rendered a dead strip. It must merge the
// three inbound tables newest-first and cap the result.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('__BASEDIR__', dirname(__DIR__));

$DBSTUB = tempnam(sys_get_temp_dir(), 'dash');
$DBFILE = $DBSTUB . '.sqlite';
$_ENV['DB_TYPE']     = 'sqlite';
$_ENV['DB_DATABASE'] = $DBFILE;

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../config/migrate.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../api/support/bootstrap.php';
require_once __DIR__ . '/../core/messaging/Registry.php';
require_once __DIR__ . '/../api/controllers/AdminController.php';

register_shutdown_function(function () use ($DBSTUB, $DBFILE) {
    foreach ([$DBSTUB, $DBFILE, $DBFILE . '-wal', $DBFILE . '-shm'] as $f) {
        if (is_file($f)) { @unlink($f); }
    }
});

function check($cond, $msg) { if (!$cond) throw new RuntimeException($msg); }

foreach (migration_files() as $file) {
    $class = migration_class($file);
    if ($class !== null && is_subclass_of($class, Migration::class)) {
        (new $class($GLOBALS['pdo']))->up();
    }
}

// Timestamps a minute apart so newest-first ordering is unambiguous.
$t = fn(int $minsAgo): string => gmdate('Y-m-d H:i:s', time() - $minsAgo * 60);

db_execute("INSERT INTO website_leads (id, type, name, phone, email, details_json, status, created_at, updated_at)
            VALUES (?, 'test_drive', 'Asha Rao', '98765', 'a@x.com', '{}', 'new', ?, ?)",
    ['l1', $t(5), $t(5)]);
db_execute("INSERT INTO website_leads (id, type, name, phone, email, details_json, status, created_at, updated_at)
            VALUES (?, 'contact', 'Bo Das', '91234', 'b@x.com', '{}', 'new', ?, ?)",
    ['l2', $t(1), $t(1)]);   // newest of all

db_execute("INSERT INTO job_applications (id, job_id, applicant_name, name, phone, email, status, created_at, updated_at)
            VALUES (?, 'job-1', 'Carol M', 'Carol M', '70000', 'c@x.com', 'new', ?, ?)",
    ['app-1', $t(3), $t(3)]);

db_execute("INSERT INTO warranty_registrations
            (id, type, status, reference_no, customer_name, email, mobile, chassis_no, motor_no, purchase_date, invoice_path, created_at, updated_at)
            VALUES (?, 'free', 'pending', 'REF-1', 'Dev P', 'd@x.com', '60000', 'CH1', 'MO1', '2026-10-01', '/x.pdf', ?, ?)",
    ['w1', $t(10), $t(10)]);

$ctrl = new AdminController();
$ref = new ReflectionMethod($ctrl, 'recentActivity');
$ref->setAccessible(true);
$recent = $ref->invoke($ctrl, 10);

check(count($recent) === 4, 'A: expected 4 merged items, got ' . count($recent));

// Newest-first: the 1-min-old contact lead leads; the 30-min job posting is not
// itself an item (only its application is).
check($recent[0]['kind'] === 'lead' && $recent[0]['title'] === 'Bo Das',
    'B: newest item should be the contact lead, got ' . json_encode($recent[0]));
check($recent[1]['kind'] === 'application' && $recent[1]['title'] === 'Carol M',
    'C: second should be the application, got ' . json_encode($recent[1]));

// Order across the whole feed is strictly by time, newest first.
$times = array_column($recent, 'at');
$sorted = $times;
rsort($sorted);
check($times === $sorted, 'D: feed is not sorted newest-first');

// Each item is flat and renderable without a join.
foreach ($recent as $it) {
    foreach (['kind', 'title', 'subtitle', 'at', 'href'] as $k) {
        check(array_key_exists($k, $it), "E: item missing {$k}: " . json_encode($it));
    }
}

// The cap holds.
$capped = $ref->invoke($ctrl, 2);
check(count($capped) === 2, 'F: limit must cap the feed, got ' . count($capped));

echo "admin-dashboard-recent: all checks passed\n";
