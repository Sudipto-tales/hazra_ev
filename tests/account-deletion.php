<?php
// Run: php tests/account-deletion.php. Never connects to an application database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../api/support/V1Controller.php';
function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare($sql); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function rejects(callable $action, string $class) { try { $action(); } catch (Throwable $error) { if ($error instanceof $class) return; throw $error; } throw new RuntimeException('Expected rejection: ' . $class); }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');
foreach (glob(__DIR__ . '/../database/migrations/00*.php') as $file) {
    $before = get_declared_classes(); require_once $file;
    foreach (array_diff(get_declared_classes(), $before) as $class) if (is_subclass_of($class, Migration::class)) (new $class($pdo))->up();
}
require_once __DIR__ . '/../database/migrations/027_account_deletion_requests.php';
$now = '2026-10-02T08:00:00Z';
db_execute('INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)', ['org', 'Test', 'Asia/Kolkata', $now]);
db_execute('INSERT INTO organizations (id, name, created_at) VALUES (?, ?, ?)', ['other', 'Other', $now]);
foreach (['employee', 'untouched', 'automatic', 'due-on-access', 'admin', 'other-admin'] as $id) {
    $role = str_contains($id, 'admin') ? 'admin' : 'employee';
    db_execute('INSERT INTO users (id, org_id, role, name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, $id === 'other-admin' ? 'other' : 'org', $role, 'Original ' . $id, $id . '@test.example', password_hash('password123', PASSWORD_BCRYPT), $now, $now]);
    if ($role === 'employee') db_execute('INSERT INTO employee_profiles (user_id, employee_code, joined_on, address) VALUES (?, ?, ?, ?)', [$id, 'EMP-' . $id, '2026-01-01', 'Private address']);
}
$before = db_fetch_one("SELECT * FROM users WHERE id = 'untouched'");
$migration = new AccountDeletionRequests($pdo); $migration->up(); $migration->up();
require_once __DIR__ . '/../database/migrations/030_account_deletion_admin_note.php';
(new AccountDeletionAdminNote($pdo))->up();
(new AccountDeletionAdminNote($pdo))->up();
$after = db_fetch_one("SELECT * FROM users WHERE id = 'untouched'");
foreach ($before as $key => $value) check($after[$key] === $value, 'Migration changed existing user: ' . $key);
check($after['account_status'] === 'active', 'Incorrect default account state');
$employee = db_fetch_one("SELECT * FROM users WHERE id = 'employee'");
$ticket = AccountDeletion::request($employee, 'Personal request', $now);
check($ticket['delete_after'] === '2026-11-01T08:00:00Z', 'Deadline must be exactly thirty days');
check(AccountDeletion::request($employee, 'Retry', '2026-10-03T08:00:00Z')['id'] === $ticket['id'], 'Duplicate ticket');
check(db_fetch_one('SELECT delete_after FROM account_deletion_requests WHERE id = ?', [$ticket['id']])['delete_after'] === $ticket['delete_after'], 'Retry extended deadline');
check(AccountDeletion::present($ticket)['employee']['name'] === 'Original employee', 'Missing employee snapshot');
rejects(fn() => AccountDeletion::request($employee, str_repeat('x', 2001), $now), InvalidArgumentException::class);
rejects(fn() => AccountDeletion::complete($ticket['id'], 'other', 'other-admin', $now), OutOfBoundsException::class);
rejects(fn() => AccountDeletion::complete($ticket['id'], 'org', 'employee', $now), DomainException::class);
rejects(fn() => AccountDeletion::complete($ticket['id'], 'org', null, $now), DomainException::class);
db_execute('INSERT INTO devices (id, user_id, platform, push_token) VALUES (?, ?, ?, ?)', ['device', 'employee', 'android', 'push']);
db_execute('INSERT INTO refresh_tokens (id, user_id, token_hash, created_at, expires_at) VALUES (?, ?, ?, ?, ?)', ['token', 'employee', 'hash', $now, '2027-01-01T00:00:00Z']);
db_execute('INSERT INTO work_sessions (id, employee_id, client_id, work_date, seq, started_at, start_lat, start_lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', ['session', 'employee', 'client', '2026-10-02', 1, '2026-10-02T07:00:00Z', 22, 88]);
db_execute('INSERT INTO stop_records (id, session_id, employee_id, work_date, arrival, departure, lat, lng, radius_m) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', ['stop', 'session', 'employee', '2026-10-02', '2026-10-02T07:00:00Z', '2026-10-02T07:30:00Z', 22, 88, 100]);
db_execute('INSERT INTO visit_reports (id, employee_id, client_id, work_date, company_name, title, body, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', ['report', 'employee', 'report-client', '2026-10-02', 'Customer', 'Sale report', 'Historic report text', $now]);
db_execute('INSERT INTO products (id, org_id, category, brand, name, model_code, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', ['product', 'org', 'scooty', 'Hazra', 'Scooter', 'scooter', $now]);
db_execute('INSERT INTO report_sales (id, report_id, product_id, product_name, category, units) VALUES (?, ?, ?, ?, ?, ?)', ['sale', 'report', 'product', 'Scooter', 'scooty', 1]);
db_execute('INSERT INTO day_routes (employee_id, work_date, point_count, polyline) VALUES (?, ?, ?, ?)', ['employee', '2026-10-02', 2, '[[22,88],[22.1,88.1]]']);
$history = [];
foreach (['stop_records', 'visit_reports', 'report_sales', 'day_routes'] as $table) $history[$table] = db_fetch_all('SELECT * FROM ' . $table);
Ctx::impersonate(['id' => 'admin', 'org_id' => 'org', 'role' => 'admin']);
$pdo->exec("CREATE TRIGGER simulate_failure BEFORE UPDATE ON devices BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END");
rejects(fn() => AccountDeletion::complete($ticket['id'], 'org', 'admin', $now), PDOException::class);
check(db_fetch_one("SELECT name FROM users WHERE id = 'employee'")['name'] === 'Original employee', 'Partial deletion was not rolled back');
check(db_fetch_one("SELECT revoked_at FROM refresh_tokens WHERE id = 'token'")['revoked_at'] === null, 'Token revocation was not rolled back');
$pdo->exec('DROP TRIGGER simulate_failure');
$deleted = AccountDeletion::complete($ticket['id'], 'org', 'admin', $now, '  Employee requested closure  ');
check(AccountDeletion::present($deleted)['adminNote'] === 'Employee requested closure', 'Admin note was not retained');
check(AccountDeletion::complete($ticket['id'], 'org', 'admin', $now, 'Changed')['admin_note'] === 'Employee requested closure', 'Retry changed deletion note');
rejects(fn() => AccountDeletion::complete($ticket['id'], 'org', 'admin', $now, str_repeat('x', 2001)), InvalidArgumentException::class);
foreach ($history as $table => $rows) check(db_fetch_all('SELECT * FROM ' . $table) === $rows, 'Historical records changed: ' . $table);
$user = db_fetch_one("SELECT * FROM users WHERE id = 'employee'");
check($user['id'] === 'employee' && $user['name'] === 'Unknown' && $user['account_status'] === 'deleted' && !$user['active'], 'Missing tombstone');
check(!password_verify('password123', $user['password_hash']), 'Old password still valid');
check($user['email'] === 'deleted-employee@invalid.local' && $user['phone'] === '', 'Current identity not anonymised');
check(db_fetch_one("SELECT revoked_at FROM refresh_tokens WHERE id = 'token'")['revoked_at'] === $now, 'Refresh token not revoked');
check(db_fetch_one("SELECT push_token FROM devices WHERE id = 'device'")['push_token'] === null, 'Push token not removed');
check(db_fetch_one("SELECT ended_at FROM work_sessions WHERE id = 'session'")['ended_at'] === $now, 'Open session not closed');
check(db_fetch_one("SELECT COUNT(*) AS n FROM attendance_days WHERE employee_id = 'employee'")['n'] === 1, 'Closed work day missing');
check(Ctx::id() === 'admin', 'Deletion replaced admin request context');
check($deleted['status'] === 'deleted' && $deleted['deletion_source'] === 'admin', 'Approval not recorded');
AccountDeletion::complete($ticket['id'], 'org', 'admin', '2026-10-03T08:00:00Z');
check(db_fetch_one('SELECT COUNT(*) AS n FROM account_deletion_events')['n'] === 1, 'Approval replay duplicated audit');
rejects(fn() => AccountDeletion::request($user, '', $now), DomainException::class);
$untouched = db_fetch_one("SELECT * FROM users WHERE id = 'untouched'");
foreach ($after as $key => $value) check($untouched[$key] === $value, 'Unrelated employee changed');
$automatic = db_fetch_one("SELECT * FROM users WHERE id = 'automatic'");
$autoTicket = AccountDeletion::request($automatic, '', '2026-08-01T08:00:00Z');
$autoDeleted = AccountDeletion::complete($autoTicket['id'], 'org', null, $now);
check($autoDeleted['deletion_source'] === 'automatic' && $autoDeleted['deleted_at'] === '2026-08-31T08:00:00Z', 'Late scheduler extended effective deletion date');
check(AccountDeletion::isDeleted(db_fetch_one("SELECT * FROM users WHERE id = 'automatic'")), 'Automatic deletion failed');
$dueOnAccess = db_fetch_one("SELECT * FROM users WHERE id = 'due-on-access'");
AccountDeletion::request($dueOnAccess, '', '2026-08-01T08:00:00Z');
$dueOnAccess = db_fetch_one("SELECT * FROM users WHERE id = 'due-on-access'");
check(AccountDeletion::isDeleted(AccountDeletion::expireIfDue($dueOnAccess)), 'Access deadline fallback failed');
AccountDeletion::checkAccess($untouched);
AccountDeletion::checkAccess(['id' => 'old-user', 'org_id' => 'org', 'active' => 1]);
echo "Account deletion lifecycle, migration, scope, retries and session closure passed.\n";
