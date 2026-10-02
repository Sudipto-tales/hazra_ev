<?php
// php tests/whatsapp.php — isolated SQLite, fake Meta transport; no application DB or network.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../api/support/Uuid.php';
require_once __DIR__ . '/../core/OtpService.php';
require_once __DIR__ . '/../database/migrations/028_whatsapp_messages.php';
require_once __DIR__ . '/../database/migrations/029_message_delivery.php';
function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare($sql); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function setting($key, $value) { db_execute("INSERT INTO website_settings VALUES (?, 'general', ?, ?, '') ON CONFLICT(group_name, setting_key) DO UPDATE SET setting_value = excluded.setting_value", [Uuid::v4(), $key, $value]); }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE warranty_registrations (id TEXT PRIMARY KEY);
CREATE TABLE website_settings (id TEXT PRIMARY KEY, group_name TEXT, setting_key TEXT, setting_value TEXT, updated_at TEXT, UNIQUE(group_name, setting_key));
CREATE TABLE otp_challenges (id TEXT PRIMARY KEY, mobile TEXT, purpose TEXT, code_hash TEXT, expires_at TEXT, attempts INTEGER, created_at TEXT, ip_address TEXT, verified_at TEXT, session_token TEXT, consumed_at TEXT);");
$migration = new WhatsAppMessages($pdo); $migration->up(); $migration->up();
$deliveryMigration = new MessageDelivery($pdo); $deliveryMigration->up(); $deliveryMigration->up();
class FakeWhatsApp extends WhatsAppService
{
    public static array $payload = [];
    public static bool $reject = false;
    protected static function request(string $version, string $phoneId, string $token, array $payload): array
    {
        self::$payload = $payload;
        return self::$reject ? [['error' => ['code' => 131026]], 400] : [['messages' => [['id' => 'meta-' . Uuid::v4()]]], 200];
    }
}
check(WhatsAppService::normalizeMobile('+91 90029 21509') === '919002921509', 'Indian normalization');
check(WhatsAppService::normalizeMobile('9002921509') === '919002921509', 'Default country');
check(WhatsAppService::normalizeMobile('90029abc1509') === null, 'Invalid phone accepted');
check(!WhatsAppService::accepted(['error' => ['code' => 1]], 200), 'Provider error accepted');
check(!WhatsAppService::accepted(['messages' => [['id' => 'id']]], 500), 'HTTP failure accepted');
check(!WhatsAppService::accepted([], 200), 'Malformed success accepted');
check(WhatsAppService::accepted(['messages' => [['id' => 'id']]], 200), 'Valid acceptance rejected');
$_ENV['WHATSAPP_ACCESS_TOKEN'] = 'fake-token'; $_ENV['WHATSAPP_PHONE_NUMBER_ID'] = '123'; $_ENV['WHATSAPP_GRAPH_VERSION'] = 'v25.0';
setting('wa_enabled', '1'); setting('wa_template_otp', 'otp'); setting('wa_template_lead_received', 'received');
$sent = FakeWhatsApp::send('9002921509', 'otp', ['123456'], 'challenge');
check($sent['success'], 'Template sending failed');
check(FakeWhatsApp::$payload['template']['components'][1]['parameters'][0]['text'] === '123456', 'OTP copy-code button mismatch');
$row = db_fetch_one('SELECT * FROM whatsapp_messages WHERE id = ?', [$sent['id']]);
check($row['status'] === 'accepted' && !str_contains(json_encode($row), '123456') && !str_contains(json_encode($row), 'fake-token'), 'Secret stored in history');
FakeWhatsApp::$reject = true;
$failed = FakeWhatsApp::send('9002921509', 'otp', ['654321'], 'failure');
check(!$failed['success'] && db_fetch_one('SELECT * FROM whatsapp_messages WHERE id = ?', [$failed['id']])['error_code'] === '131026', 'Failure not recorded');
FakeWhatsApp::$reject = false;
$lead = ['id' => 'lead', 'name' => 'Test', 'type' => 'contact', 'phone' => '9002921509', 'details' => []];
check(!FakeWhatsApp::notifyLead($lead, 'lead_received')['success'], 'Missing consent allowed');
$lead['details']['whatsapp_consent'] = '0';
check(!FakeWhatsApp::notifyLead($lead, 'lead_received')['success'], 'False consent allowed');
$lead['details']['whatsapp_consent'] = '1';
check(FakeWhatsApp::notifyLead($lead, 'lead_received')['success'], 'Consented message rejected');
check(!FakeWhatsApp::notifyLead($lead, 'lead_received')['success'], 'Duplicate retry allowed');
$_ENV['WHATSAPP_APP_SECRET'] = 'secret'; $raw = '{"entry":[]}';
check(WhatsAppService::validSignature($raw, 'sha256=' . hash_hmac('sha256', $raw, 'secret')), 'Valid signature rejected');
check(!WhatsAppService::validSignature($raw . ' ', 'sha256=' . hash_hmac('sha256', $raw, 'secret')), 'Tampered webhook accepted');
function statusEvent($id, $status) { return ['entry' => [['changes' => [['value' => ['statuses' => [['id' => $id, 'status' => $status]]]]]]]]; }
foreach (['delivered', 'sent', 'failed', 'read', 'delivered'] as $status) WhatsAppService::updateStatuses(statusEvent($row['provider_id'], $status));
check(db_fetch_one('SELECT status FROM whatsapp_messages WHERE id = ?', [$row['id']])['status'] === 'read', 'Out-of-order webhook regressed status');
WhatsAppService::updateStatuses(statusEvent('unknown-id', 'delivered'));

// A missing WhatsApp credential must fall back to development SMS, preserving the challenge.
$_ENV['WHATSAPP_ACCESS_TOKEN'] = ''; $_ENV['SMS_PROVIDER'] = 'log'; $_ENV['APP_ENV'] = 'testing';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$otp = OtpService::sendOtp('9002921509', 'warranty_free');
check($otp['success'] && $otp['channel'] === 'sms' && !empty($otp['debug_code']), 'SMS fallback failed');
check(!OtpService::sendOtp('9002921509', 'warranty_free', 'sms')['success'], 'Channel switch bypassed cooldown');
check(!OtpService::verifyOtp('9002921509', 'warranty_paid', $otp['debug_code'])['success'], 'OTP crossed purposes');
check(OtpService::verifyOtp('9002921509', 'warranty_free', $otp['debug_code'])['success'], 'Fallback code cannot verify');
check(!OtpService::verifyOtp('9002921509', 'warranty_free', $otp['debug_code'])['success'], 'Verified OTP replayed');

// Subsequent SMS request invalidates earlier challenge.
$otp = OtpService::sendOtp('9002921510', 'warranty_free', 'sms');
$old = db_fetch_one('SELECT * FROM otp_challenges WHERE mobile = ?', ['9002921510']);
db_execute("UPDATE otp_challenges SET created_at = ? WHERE id = ?", [gmdate('Y-m-d\TH:i:s\Z', time() - 61), $old['id']]);
db_execute("UPDATE website_settings SET updated_at = '1970-01-01T00:00:00Z' WHERE setting_key = 'wa:otp:9002921510'");
$new = OtpService::sendOtp('9002921510', 'warranty_free', 'sms');
check($new['success'], 'Subsequent request rejected');
check(strtotime(db_fetch_one('SELECT expires_at FROM otp_challenges WHERE id = ?', [$old['id']])['expires_at']) <= time(), 'Old OTP remained active');
for ($i = 0; $i < 5; $i++) OtpService::verifyOtp('9002921510', 'warranty_free', '000000');
check(!OtpService::verifyOtp('9002921510', 'warranty_free', $new['debug_code'])['success'], 'Attempt limit bypassed');
$_ENV['APP_ENV'] = 'production';
$production = OtpService::sendOtp('9002921511', 'warranty_free', 'sms');
check(!$production['success'] && !isset($production['debug_code']), 'Production log delivered/exposed OTP');
check(!OtpService::verifyOtp('9002921511', 'warranty_free', '000000')['success'], 'Failed delivery challenge valid');
check(!OtpService::sendOtp('invalid', 'warranty_free')['success'], 'Invalid mobile accepted');
check(!OtpService::sendOtp('9002921512', 'unknown')['success'], 'Unknown purpose accepted');

class FakeOtp extends OtpService
{
    public static string $whatsappCode = '';
    public static string $smsCode = '';
    public static bool $acceptWhatsApp = false;
    protected static function sendWhatsAppOtp(string $mobile, string $code, string $id): array
    {
        self::$whatsappCode = $code;
        return ['success' => self::$acceptWhatsApp];
    }
    protected static function sendSmsOtp(string $provider, string $mobile, string $code, string $purpose, string $reference): bool
    {
        self::$smsCode = $code;
        return true;
    }
}
$fallback = FakeOtp::sendOtp('9002921513', 'warranty_free');
check($fallback['success'] && $fallback['channel'] === 'sms' && FakeOtp::$whatsappCode === FakeOtp::$smsCode, 'Fallback changed OTP');
check(!isset($fallback['debug_code']), 'Production transport exposed OTP');
check(FakeOtp::verifyOtp('9002921513', 'warranty_free', FakeOtp::$smsCode)['success'], 'Production fallback code failed');
FakeOtp::$acceptWhatsApp = true; FakeOtp::$smsCode = '';
$wa = FakeOtp::sendOtp('9002921514', 'warranty_free');
check($wa['success'] && $wa['channel'] === 'whatsapp' && FakeOtp::$smsCode === '', 'Accepted WhatsApp send also sent SMS');
check(FakeOtp::verifyOtp('9002921514', 'warranty_free', FakeOtp::$whatsappCode)['success'], 'WhatsApp OTP did not verify');
for ($i = 0; $i < 5; $i++) db_execute('INSERT INTO otp_challenges (id, mobile, purpose, created_at) VALUES (?, ?, ?, ?)', [Uuid::v4(), '9002921515', 'warranty_free', gmdate('Y-m-d\TH:i:s\Z', time() - 120)]);
check(!FakeOtp::sendOtp('9002921515', 'warranty_paid')['success'], 'Hourly limit crossed purposes');
for ($i = 0; $i < 20; $i++) db_execute('INSERT INTO otp_challenges (id, mobile, purpose, created_at, ip_address) VALUES (?, ?, ?, ?, ?)', [Uuid::v4(), 'other', 'warranty_free', gmdate('Y-m-d\TH:i:s\Z'), 'abusive']);
$_SERVER['REMOTE_ADDR'] = 'abusive';
check(!FakeOtp::sendOtp('9002921516', 'warranty_free')['success'], 'IP limit bypassed');

// MySQL migration expansion and lock upserts must remain portable.
Dialect::boot($pdo, 'mysql');
check(str_contains(Dialect::dml("INSERT INTO website_settings (id, group_name, setting_key) VALUES (?, 'whatsapp_locks', ?) ON CONFLICT(group_name, setting_key) DO NOTHING"), 'INSERT IGNORE'), 'MySQL cooldown reservation incompatible');
check(!str_contains(Dialect::ddl('CREATE TABLE whatsapp_messages (id {uuid}, created_at {ts}) {opts}'), '{'), 'MySQL migration tokens unexpanded');
Dialect::boot($pdo, 'sqlite');
echo "WhatsApp messaging and OTP tests passed.\n";
