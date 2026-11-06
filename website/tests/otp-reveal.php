<?php
// php tests/otp-reveal.php — isolated SQLite, fake transport, no network.
//
// The eye on the delivery page is the one feature here that could leak a
// customer's credential, so this suite is mostly about what it REFUSES: a
// verified code, a consumed code, an expired code, a superseded code, a
// disabled setting, and a non-admin. Plus: the code never appears in the
// listing payload, and every reveal leaves an audit row.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('__BASEDIR__', dirname(__DIR__));
require_once __BASEDIR__ . '/config/env.php';
require_once __BASEDIR__ . '/config/migration.php';
require_once __BASEDIR__ . '/api/support/Uuid.php';
require_once __BASEDIR__ . '/database/migrations/028_whatsapp_messages.php';
require_once __BASEDIR__ . '/database/migrations/029_message_delivery.php';
require_once __BASEDIR__ . '/database/migrations/032_messaging_credentials.php';
require_once __BASEDIR__ . '/database/migrations/034_message_attempts.php';
require_once __BASEDIR__ . '/database/migrations/035_otp_code_cipher.php';

function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare($sql); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function rejects(callable $operation, string $class) { try { $operation(); } catch (Throwable $e) { if ($e instanceof $class) return; throw $e; } throw new RuntimeException('Expected ' . $class); }

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE website_settings (id TEXT PRIMARY KEY, group_name TEXT, setting_key TEXT, setting_value TEXT, updated_at TEXT, UNIQUE(group_name, setting_key));
CREATE TABLE warranty_registrations (id TEXT PRIMARY KEY);
CREATE TABLE website_leads (id TEXT PRIMARY KEY);
CREATE TABLE otp_challenges (id TEXT PRIMARY KEY, mobile TEXT, purpose TEXT, code_hash TEXT, expires_at TEXT, attempts INTEGER, created_at TEXT, ip_address TEXT, verified_at TEXT, session_token TEXT, consumed_at TEXT);");
(new WhatsAppMessages($pdo))->up();
(new MessageDelivery($pdo))->up();
foreach (['MessagingCredentials', 'MessageAttempts', 'OtpCodeCipher'] as $class) { (new $class($pdo))->up(); }

require_once __BASEDIR__ . '/core/MessageDeliveryLog.php';

$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_KEY'] = Secrets::generateKey();
Secrets::flushKey();
$_SERVER['REMOTE_ADDR'] = '198.51.100.4';
$_SERVER['HTTP_USER_AGENT'] = 'test-admin-browser';
$admin = ['id' => 'admin-1', 'email' => 'admin@hazraev.test'];

// One fake SMS provider, so a send never leaves the process.
class FakeSms implements MessagingTransport
{
    public static string $lastCode = '';
    public function __construct(private string $provider = 'sms.msg91') {}
    public function send(array $message): array
    {
        self::$lastCode = (string) ($message['code'] ?? '');
        $id = Uuid::v4();
        db_execute('INSERT INTO sms_messages (id, reference_id, event_name, mobile, provider, status, attempt_group, chain_position, is_test, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [$id, $message['reference'], $message['event'], '91' . $message['recipient'], 'msg91', 'accepted',
             $message['attemptGroup'], $message['position'], gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
        return ['success' => true, 'id' => $id, 'status' => 'accepted'];
    }
}
MessagingRegistry::override('sms.msg91', new FakeSms());
$_ENV['MSG91_AUTH_KEY'] = 'key'; $_ENV['MSG91_TEMPLATE_ID'] = 'template';
MessagingRouter::saveChain('otp', [['provider' => 'sms.msg91', 'position' => 10, 'enabled' => true]]);
MessagingCredentialStore::flush();

/** One row out of the delivery listing, by id — created_at ties make position unreliable. */
function listedRow(string $id): array
{
    foreach (MessageDeliveryLog::listing([])['items'] as $row) {
        if ($row['id'] === $id) return $row;
    }
    throw new RuntimeException('Message ' . $id . ' is missing from the listing');
}

function sendCode(string $mobile): array
{
    db_execute("UPDATE website_settings SET updated_at = '1970-01-01T00:00:00Z' WHERE group_name = 'whatsapp_locks'");
    $result = OtpService::sendOtp($mobile, 'warranty_free');
    check($result['success'], 'The fake SMS send failed');
    $challenge = db_fetch_one('SELECT * FROM otp_challenges WHERE mobile = ? ORDER BY created_at DESC LIMIT 1', [$mobile]);
    $message = db_fetch_one('SELECT * FROM sms_messages WHERE reference_id = ? ORDER BY created_at DESC LIMIT 1', [$challenge['id']]);
    return [$challenge, $message];
}

// --------------------------------------------------- disabled by default
check(MessagingRouter::setting('otp_reveal_enabled', '0') === '0', 'Reveal is not off by default');
[$challenge, $message] = sendCode('9002921501');
check($challenge['code_cipher'] === null, 'A code was stored while reveal was switched off');
rejects(fn() => MessageDeliveryLog::reveal('sms', $message['id'], $admin), DomainException::class);
check(listedRow($message['id'])['canReveal'] === false, 'The eye was offered while reveal was off');

// ------------------------------------------------------------- switched on
MessagingRouter::saveSetting('otp_reveal_enabled', '1');
[$challenge, $message] = sendCode('9002921502');
check($challenge['code_cipher'] !== null, 'No code was stored with reveal switched on');
check(!str_contains((string) $challenge['code_cipher'], FakeSms::$lastCode), 'The code was stored in the clear');
check($challenge['code_hash'] !== '' && password_verify(FakeSms::$lastCode, $challenge['code_hash']), 'The bcrypt hash is no longer the verification path');

$listing = MessageDeliveryLog::listing([]);
check(listedRow($message['id'])['canReveal'] === true, 'The eye was not offered for a live code');
check(!str_contains(json_encode($listing), FakeSms::$lastCode), 'The listing payload carried the code');

$revealed = MessageDeliveryLog::reveal('sms', $message['id'], $admin);
check($revealed['code'] === FakeSms::$lastCode, 'Reveal returned the wrong code');

$audit = db_fetch_all('SELECT * FROM otp_reveal_audit');
check(count($audit) === 1, 'Reveal was not audited');
check($audit[0]['admin_user_id'] === 'admin-1' && $audit[0]['admin_email'] === 'admin@hazraev.test', 'The audit row does not name the admin');
check($audit[0]['ip'] === '198.51.100.4' && $audit[0]['challenge_id'] === $challenge['id'], 'The audit row is missing request detail');
check(!str_contains(json_encode($audit), FakeSms::$lastCode), 'The audit row stored the code');

// Revealing twice is allowed and audited twice — support may look again.
MessageDeliveryLog::reveal('sms', $message['id'], $admin);
check(count(db_fetch_all('SELECT * FROM otp_reveal_audit')) === 2, 'A second reveal was not audited');

// --------------------------------------------------- verification wipes it
check(OtpService::verifyOtp('9002921502', 'warranty_free', FakeSms::$lastCode)['success'], 'The code did not verify');
check(db_fetch_one('SELECT code_cipher FROM otp_challenges WHERE id = ?', [$challenge['id']])['code_cipher'] === null, 'Verification left the code revealable');
rejects(fn() => MessageDeliveryLog::reveal('sms', $message['id'], $admin), DomainException::class);
check(listedRow($message['id'])['canReveal'] === false, 'The eye was offered after verification');

// ------------------------------------------------------ expiry wipes it too
[$challenge, $message] = sendCode('9002921503');
db_execute('UPDATE otp_challenges SET expires_at = ?, code_cipher_expires_at = ? WHERE id = ?',
    [gmdate('Y-m-d\TH:i:s\Z', time() - 60), gmdate('Y-m-d\TH:i:s\Z', time() - 60), $challenge['id']]);
rejects(fn() => MessageDeliveryLog::reveal('sms', $message['id'], $admin), DomainException::class);
check(OtpService::purgeCiphers() >= 1, 'The purge did not wipe an expired code');
check(db_fetch_one('SELECT code_cipher FROM otp_challenges WHERE id = ?', [$challenge['id']])['code_cipher'] === null, 'An expired code stayed revealable');

// ------------------------------------------------ a superseded code is wiped
[$first, $firstMessage] = sendCode('9002921504');
db_execute('UPDATE otp_challenges SET created_at = ? WHERE id = ?', [gmdate('Y-m-d\TH:i:s\Z', time() - 61), $first['id']]);
[$second] = sendCode('9002921504');
check($second['id'] !== $first['id'], 'A second request reused the challenge');
check(db_fetch_one('SELECT code_cipher FROM otp_challenges WHERE id = ?', [$first['id']])['code_cipher'] === null, 'A superseded code stayed revealable');
rejects(fn() => MessageDeliveryLog::reveal('sms', $firstMessage['id'], $admin), DomainException::class);

// ------------------------------------------- a consumed challenge is refused
[$challenge, $message] = sendCode('9002921505');
db_execute('UPDATE otp_challenges SET consumed_at = ? WHERE id = ?', [gmdate('Y-m-d\TH:i:s\Z'), $challenge['id']]);
rejects(fn() => MessageDeliveryLog::reveal('sms', $message['id'], $admin), DomainException::class);

// ------------------------------- an unreadable cipher is refused, not fatal
[$challenge, $message] = sendCode('9002921506');
$_ENV['APP_KEY'] = Secrets::generateKey();
Secrets::flushKey();
rejects(fn() => MessageDeliveryLog::reveal('sms', $message['id'], $admin), DomainException::class);
check(OtpService::revealCode($challenge['id'])['code'] === null, 'A foreign key decrypted a code');

// --------------------------------------- only an OTP row offers a reveal
db_execute("INSERT INTO whatsapp_messages (id, reference_id, event_name, mobile, template_name, provider, status, created_at, updated_at)
    VALUES ('wa-lead', 'lead-1', 'lead_received', '919002921509', 'received', 'meta', 'accepted', ?, ?)",
    [gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
$leadRow = MessageDeliveryLog::find('whatsapp', 'wa-lead');
check($leadRow !== null, 'The lead row was not found');
rejects(fn() => MessageDeliveryLog::reveal('whatsapp', 'wa-lead', $admin), DomainException::class);
rejects(fn() => MessageDeliveryLog::reveal('sms', Uuid::v4(), $admin), OutOfBoundsException::class);

// ------------------------------------------------------------- rate limited
$_ENV['APP_KEY'] = Secrets::generateKey();
Secrets::flushKey();
[$challenge, $message] = sendCode('9002921507');
for ($i = 0; $i < 30; $i++) {
    db_execute('INSERT INTO otp_reveal_audit (id, challenge_id, admin_user_id, revealed_at) VALUES (?, ?, ?, ?)',
        [Uuid::v4(), 'other', 'admin-1', gmdate('Y-m-d\TH:i:s\Z')]);
}
rejects(fn() => MessageDeliveryLog::reveal('sms', $message['id'], $admin), DomainException::class);
// A different admin is not caught by someone else's rate limit.
check(MessageDeliveryLog::reveal('sms', $message['id'], ['id' => 'admin-2', 'email' => 'other@hazraev.test'])['code'] !== '', 'The rate limit is not per admin');

MessagingRegistry::override('sms.msg91', null);
echo "OTP reveal gating, wiping, auditing and rate limiting passed.\n";
