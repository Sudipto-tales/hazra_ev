<?php
// Run: php tests/user-password.php. Never connects to an application database.
//
// Covers the bootstrap seed (no hardcoded admin123, must_change flagged) and the
// pure resolve/apply core of `php vayu user:password`. The interactive layer of
// the command (stdin read with echo off, and run()'s require of config/db.php)
// is deliberately not driven here: config/db.php opens a live connection and the
// password read needs a TTY, neither of which belongs in an in-memory test. The
// logic that matters — who gets matched and what gets written — lives in the
// static resolve()/apply() methods and is exercised directly.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../api/support/Wire.php';
require_once __DIR__ . '/../api/support/Envelope.php';
require_once __DIR__ . '/../api/support/Password.php';
require_once __DIR__ . '/../core/Console/UserPasswordCommand.php';

function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare($sql); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function rejects(callable $action, string $class) { try { $action(); } catch (Throwable $error) { if ($error instanceof $class) return; throw $error; } throw new RuntimeException('Expected rejection: ' . $class); }

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');

// Core identity + tracking tables (00x) and the must_change_password column (010).
foreach (glob(__DIR__ . '/../database/migrations/00*.php') as $file) {
    $before = get_declared_classes(); require_once $file;
    foreach (array_diff(get_declared_classes(), $before) as $class) if (is_subclass_of($class, Migration::class)) (new $class($pdo))->up();
}
require_once __DIR__ . '/../database/migrations/010_password_credentials.php';
(new PasswordCredentials($pdo))->up();

// --- the bootstrap seed no longer ships a known password -------------------
ob_start();
(require __DIR__ . '/../database/seeds/001_bootstrap_admin.php')($pdo);
$seedOutput = ob_get_clean();

check(str_contains($seedOutput, 'initial admin password'), 'Seed did not print the first-login credential');
check(!str_contains($seedOutput, 'admin123'), 'Seed still references admin123');

$admin = db_fetch_one("SELECT * FROM users WHERE email = 'admin@hazraev.com'");
check($admin !== false && $admin !== null, 'Seed did not create the admin');
check((int) $admin['must_change_password'] === 1, 'Seed did not flag must_change_password');
check(!password_verify('admin123', $admin['password_hash']), 'admin123 still unlocks the seeded admin');
$orgId = $admin['org_id'];

// --- resolve() --------------------------------------------------------------
$resolved = UserPasswordCommand::resolve('admin@hazraev.com', null);
check($resolved['id'] === $admin['id'], 'resolve returned the wrong user');

rejects(fn() => UserPasswordCommand::resolve('nobody@hazraev.com', null), RuntimeException::class);
rejects(fn() => UserPasswordCommand::resolve('admin@hazraev.com', 'no-such-org'), RuntimeException::class);

// --- apply() updates the hash and the flag ----------------------------------
UserPasswordCommand::apply('admin@hazraev.com', $orgId, 'FreshPass42', true);
$after = db_fetch_one("SELECT * FROM users WHERE email = 'admin@hazraev.com'");
check(password_verify('FreshPass42', $after['password_hash']), 'apply did not set the new password');
check((int) $after['must_change_password'] === 1, 'apply did not keep must_change_password = 1 by default');
check($after['password_hash'] !== $admin['password_hash'], 'apply left the old hash in place');

// --no-must-change opts the flag back off
UserPasswordCommand::apply('admin@hazraev.com', $orgId, 'ChosenPass99', false);
$chosen = db_fetch_one("SELECT * FROM users WHERE email = 'admin@hazraev.com'");
check(password_verify('ChosenPass99', $chosen['password_hash']), 'apply did not set the chosen password');
check((int) $chosen['must_change_password'] === 0, 'apply did not clear must_change_password on --no-must-change');

// --- ambiguity across orgs forces --org -------------------------------------
$now = Wire::now();
db_execute("INSERT INTO organizations (id, name, timezone, created_at) VALUES (?, ?, ?, ?)", ['org2', 'Second', 'Asia/Kolkata', $now]);
db_execute(
    "INSERT INTO users (id, org_id, role, name, email, password_hash, active, created_at, updated_at)
     VALUES (?, ?, 'admin', ?, ?, ?, 1, ?, ?)",
    ['admin2', 'org2', 'Admin Two', 'admin@hazraev.com', password_hash('whatever8', PASSWORD_BCRYPT), $now, $now],
);

rejects(fn() => UserPasswordCommand::resolve('admin@hazraev.com', null), AmbiguousUserException::class);
$disambiguated = UserPasswordCommand::resolve('admin@hazraev.com', 'org2');
check($disambiguated['id'] === 'admin2', 'resolve did not honour --org');

// scoped update only touches the named org
UserPasswordCommand::apply('admin@hazraev.com', 'org2', 'Org2Pass77', true);
check(password_verify('Org2Pass77', db_fetch_one("SELECT password_hash FROM users WHERE id = 'admin2'")['password_hash']), 'scoped apply missed its target');
check(password_verify('ChosenPass99', db_fetch_one("SELECT password_hash FROM users WHERE id = ?", [$admin['id']])['password_hash']), 'scoped apply leaked into another org');

echo "user:password resolve/apply, seed hardening, scope and ambiguity passed.\n";
