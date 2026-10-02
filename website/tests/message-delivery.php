<?php
// php tests/message-delivery.php — isolated SQLite and fake transports only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('__BASEDIR__', dirname(__DIR__));
require_once __BASEDIR__ . '/config/env.php';
require_once __BASEDIR__ . '/config/migration.php';
require_once __BASEDIR__ . '/core/ApiController.php';
require_once __BASEDIR__ . '/core/ApiRequest.php';
require_once __BASEDIR__ . '/api/controllers/MessageDeliveryController.php';
require_once __BASEDIR__ . '/api/controllers/SmsWebhookController.php';
require_once __BASEDIR__ . '/database/migrations/028_whatsapp_messages.php';
require_once __BASEDIR__ . '/database/migrations/029_message_delivery.php';
function db_query($sql, $params = []) { global $pdo; $q = $pdo->prepare($sql); $q->execute($params); return $q; }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function rejects(callable $operation, string $class) { try { $operation(); } catch (Throwable $e) { if ($e instanceof $class) return; throw $e; } throw new RuntimeException('Expected ' . $class); }
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE warranty_registrations (id TEXT PRIMARY KEY, mobile TEXT, customer_name TEXT, reference_no TEXT);
CREATE TABLE website_leads (id TEXT PRIMARY KEY, name TEXT, type TEXT, phone TEXT, status TEXT, details_json TEXT, scheduled_at TEXT);
CREATE TABLE website_settings (id TEXT PRIMARY KEY, group_name TEXT, setting_key TEXT, setting_value TEXT, updated_at TEXT, UNIQUE(group_name, setting_key));
CREATE TABLE otp_challenges (id TEXT PRIMARY KEY, mobile TEXT, purpose TEXT, code_hash TEXT, expires_at TEXT, attempts INTEGER, created_at TEXT, ip_address TEXT, verified_at TEXT, session_token TEXT, consumed_at TEXT);");
(new WhatsAppMessages($pdo))->up(); $migration = new MessageDelivery($pdo); $migration->up(); $migration->up();
function setting($key, $value) { db_execute("INSERT INTO website_settings VALUES (?, 'general', ?, ?, '') ON CONFLICT(group_name, setting_key) DO UPDATE SET setting_value = excluded.setting_value", [Uuid::v4(), $key, $value]); }
function challenge($id, $mobile = '9002921509', $age = 120) { db_execute('INSERT INTO otp_challenges (id, mobile, purpose, code_hash, expires_at, attempts, created_at, ip_address) VALUES (?, ?, ?, ?, ?, 0, ?, ?)', [$id, $mobile, 'warranty_free', 'old-hash', gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z', time() - $age), 'old-ip']); }
function seedMessage($channel, $id, $reference, $event, $status, $date = '2026-10-02T08:00:00Z') {
    if ($channel === 'sms') db_execute('INSERT INTO sms_messages (id, reference_id, event_name, mobile, provider, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$id, $reference, $event, '919002921509', 'twilio', $status, $date, $date]);
    else db_execute('INSERT INTO whatsapp_messages (id, reference_id, event_name, mobile, template_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$id, $reference, $event, '919002921509', 'template', $status, $date, $date]);
}

// Controller probes exit through real envelopes and HTTP status handling.
if (($argv[1] ?? '') === '--controller') {
    $mode = $argv[2];
    register_shutdown_function(static function () {
        if (isset($GLOBALS['callbackExpected'])) {
            $row = db_fetch_one('SELECT status FROM sms_messages WHERE id = ?', ['probe']);
            if ($row['status'] !== $GLOBALS['callbackExpected']) fwrite(STDERR, 'Callback did not update the record.');
        }
        fwrite(STDERR, (string) http_response_code());
    });
    Ctx::impersonate(['id' => 'test', 'role' => $mode === 'employee' ? 'employee' : 'admin']);
    if (str_starts_with($mode, 'twilio')) {
        $_ENV['TWILIO_TOKEN'] = 'test-token'; $_ENV['SMS_TWILIO_CALLBACK_URL'] = 'https://example.test/api/v1/sms/webhook/twilio';
        seedMessage('sms', 'probe', 'r', 'warranty_free', 'accepted');
        $_GET['message_id'] = 'probe'; $_POST = ['MessageSid' => 'SM-test', 'MessageStatus' => 'delivered'];
        $url = SmsDeliveryService::callbackUrl('probe'); ksort($_POST, SORT_STRING);
        foreach ($_POST as $key => $value) $url .= $key . $value;
        $_SERVER['HTTP_X_TWILIO_SIGNATURE'] = $mode === 'twilio-valid' ? base64_encode(hash_hmac('sha1', $url, 'test-token', true)) : 'bad';
        if ($mode === 'twilio-valid') $GLOBALS['callbackExpected'] = 'delivered';
        (new SmsWebhookController())->twilio();
    }
    if ($mode === 'msg91-invalid') { (new SmsWebhookController())->msg91(); }
    if ($mode === 'msg91-valid') {
        $_ENV['SMS_MSG91_WEBHOOK_TOKEN'] = 'hook-secret'; $_SERVER['HTTP_X_SMS_WEBHOOK_TOKEN'] = 'hook-secret';
        db_execute("INSERT INTO sms_messages (id, reference_id, event_name, mobile, provider, status, created_at, updated_at) VALUES ('probe', 'r', 'warranty_free', '919002921509', 'msg91', 'accepted', '', '')");
        (new ReflectionProperty(ApiRequest::class, 'parsedBody'))->setValue(null, ['CRQID' => 'probe', 'requestId' => 'request', 'telNum' => '919002921509', 'status' => '2', 'failureReason' => 'Absent Subscriber']);
        $GLOBALS['callbackExpected'] = 'failed';
        (new SmsWebhookController())->msg91();
    }
    if ($mode === 'retry-csrf') {
        // Exercise CSRF rejection without creating a real session file.
        session_set_save_handler(new class implements SessionHandlerInterface {
            public function open(string $path, string $name): bool { return true; }
            public function close(): bool { return true; }
            public function read(string $id): string { return ''; }
            public function write(string $id, string $data): bool { return true; }
            public function destroy(string $id): bool { return true; }
            public function gc(int $max_lifetime): int { return 0; }
        }, true);
        (new MessageDeliveryController())->retry();
    }
    seedMessage('sms', 'probe', 'r', 'warranty_free', 'accepted');
    $_GET = $mode === 'bad-filter' ? ['channel' => 'email'] : [];
    (new MessageDeliveryController())->index();
}

class FakeSmsDelivery extends SmsDeliveryService
{
    public static array $response = ['sid' => 'SM-test', 'status' => 'queued'];
    public static int $http = 201;
    public static array $requestData = [];
    public static bool $earlyCallback = false;
    protected static function request(string $url, array $data, array $headers, string $credentials = ''): array
    {
        self::$requestData = $data;
        if (self::$earlyCallback) {
            parse_str(parse_url($data['StatusCallback'], PHP_URL_QUERY), $query);
            self::updateStatus('twilio', $query['message_id'], 'SM-early', 'delivered');
        }
        return [self::$response, self::$http];
    }
}
$_ENV['TWILIO_SID'] = 'AC' . str_repeat('1', 32); $_ENV['TWILIO_TOKEN'] = 'test-token'; $_ENV['TWILIO_FROM'] = '+12025550123';
$_ENV['SMS_TWILIO_CALLBACK_URL'] = 'https://example.test/api/v1/sms/webhook/twilio';
$sent = FakeSmsDelivery::sendOtp('twilio', '9002921509', '123456', 'warranty_free', 'otp1');
check($sent['success'] && $sent['status'] === 'accepted', 'Twilio acceptance');
$row = db_fetch_one('SELECT * FROM sms_messages WHERE id = ?', [$sent['id']]);
check($row['provider_id'] === 'SM-test', 'Provider ID missing');
check(!str_contains(json_encode($row), '123456') && !str_contains(json_encode($row), 'test-token'), 'SMS history leaked a secret');
check(str_contains(FakeSmsDelivery::$requestData['StatusCallback'], 'message_id=' . $row['id']), 'Callback correlation missing');
$parameters = ['MessageStatus' => 'delivered', 'MessageSid' => 'SM-test', 'ExtraField' => 'new-value'];
$signatureData = SmsDeliveryService::callbackUrl($row['id']); ksort($parameters, SORT_STRING);
foreach ($parameters as $key => $value) $signatureData .= $key . $value;
$signature = base64_encode(hash_hmac('sha1', $signatureData, 'test-token', true));
check(SmsDeliveryService::validTwilioSignature($row['id'], $parameters, $signature), 'Valid Twilio signature');
$parameters['ExtraField'] = 'tampered';
check(!SmsDeliveryService::validTwilioSignature($row['id'], $parameters, $signature), 'Tampered callback accepted');
SmsDeliveryService::updateStatus('twilio', $row['id'], 'wrong-sid', 'delivered');
check(db_fetch_one('SELECT status FROM sms_messages WHERE id = ?', [$row['id']])['status'] === 'accepted', 'Mismatched provider ID accepted');
foreach (['sent', 'delivered', 'failed', 'accepted'] as $status) SmsDeliveryService::updateStatus('twilio', $row['id'], 'SM-test', $status);
check(db_fetch_one('SELECT status FROM sms_messages WHERE id = ?', [$row['id']])['status'] === 'delivered', 'Callback order regressed delivery');

FakeSmsDelivery::$earlyCallback = true; FakeSmsDelivery::$response = ['sid' => 'SM-early'];
$early = FakeSmsDelivery::sendOtp('twilio', '9002921509', '654321', 'warranty_free', 'early');
check(db_fetch_one('SELECT status FROM sms_messages WHERE id = ?', [$early['id']])['status'] === 'delivered', 'Early callback overwritten by HTTP response');
FakeSmsDelivery::$earlyCallback = false;
FakeSmsDelivery::$response = ['code' => 21610]; FakeSmsDelivery::$http = 400;
$rejected = FakeSmsDelivery::sendOtp('twilio', '9002921509', '123456', 'warranty_free', 'rejected');
check(!$rejected['success'] && db_fetch_one('SELECT error_code FROM sms_messages WHERE id = ?', [$rejected['id']])['error_code'] === '21610', 'Provider rejection missing');
$_ENV['MSG91_AUTH_KEY'] = 'msg-key'; $_ENV['MSG91_TEMPLATE_ID'] = 'template';
FakeSmsDelivery::$response = ['type' => 'success', 'message' => 'msg-request']; FakeSmsDelivery::$http = 200;
$msg = FakeSmsDelivery::sendOtp('msg91', '9002921509', '123456', 'warranty_free', 'msg');
check($msg['success'] && FakeSmsDelivery::$requestData['CRQID'] === $msg['id'], 'MSG91 correlation');
$_ENV['SMS_MSG91_WEBHOOK_TOKEN'] = 'hook-secret';
check(SmsDeliveryService::validMsg91Token('hook-secret') && !SmsDeliveryService::validMsg91Token('wrong'), 'MSG91 webhook authentication');
SmsDeliveryService::updateStatus('msg91', $msg['id'], 'msg-request', 'delivered', null, null, '919000000000');
check(db_fetch_one('SELECT status FROM sms_messages WHERE id = ?', [$msg['id']])['status'] === 'accepted', 'Recipient mismatch accepted');
SmsDeliveryService::updateStatus('msg91', $msg['id'], 'msg-request', 'delivered', null, null, '919002921509');
check(db_fetch_one('SELECT status FROM sms_messages WHERE id = ?', [$msg['id']])['status'] === 'delivered', 'MSG91 delivery update');
$_ENV['APP_ENV'] = 'testing';
$logged = SmsDeliveryService::sendOtp('log', '9002921509', '123456', 'warranty_free', 'logged');
check($logged['status'] === 'logged', 'Development log labelled as delivery');
$_ENV['APP_ENV'] = 'production';
$failed = SmsDeliveryService::sendOtp('log', '9002921509', '123456', 'warranty_free', 'failed');
check(!$failed['success'], 'Production log dispatch allowed');

// Existing WhatsApp records appear alongside SMS; filters and pagination work in the DB.
seedMessage('whatsapp', 'wa-existing', 'lead1', 'lead_received', 'delivered');
seedMessage('sms', 'sms-day-end', 'r', 'warranty_paid', 'sent', '2026-10-02T23:59:59Z');
seedMessage('sms', 'sms-next-day', 'r', 'warranty_paid', 'sent', '2026-10-03T00:00:00Z');
$all = MessageDeliveryLog::listing([]);
check($all['total'] === 9 && count($all['items']) === 9, 'Combined history lost records');
$wa = MessageDeliveryLog::listing(['channel' => 'whatsapp']);
check($wa['total'] === 1 && $wa['items'][0]['id'] === 'wa-existing', 'Channel filter');
check(MessageDeliveryLog::listing(['q' => 'sms-next-day'])['total'] === 0, 'Search should match public references not internal IDs');
check(MessageDeliveryLog::listing(['q' => 'msg-request'])['total'] === 1, 'Provider ID search');
check(MessageDeliveryLog::listing(['channel' => 'sms', 'status' => 'sent', 'from' => '2026-10-02', 'to' => '2026-10-02'])['total'] === 1, 'Inclusive end-date filter');
foreach ([['channel' => 'email'], ['status' => 'nonsense'], ['from' => '2026-02-30'], ['from' => '2026-10-03', 'to' => '2026-10-02'], ['q' => str_repeat('x', 121)]] as $filter) rejects(fn() => MessageDeliveryLog::listing($filter), InvalidArgumentException::class);
for ($i = 0; $i < 30; $i++) seedMessage('whatsapp', 'page-' . $i, 'reference-' . $i, 'lead_received', 'delivered');
$first = MessageDeliveryLog::listing(['channel' => 'whatsapp', 'page' => 1]); $second = MessageDeliveryLog::listing(['channel' => 'whatsapp', 'page' => 2]);
check(count($first['items']) === 25 && count($second['items']) === 6 && !array_intersect(array_column($first['items'], 'id'), array_column($second['items'], 'id')), 'Pagination repeated or lost messages');

// Failed OTP retry creates a fresh challenge, never recovers or exposes the old code.
challenge('retry-otp'); seedMessage('sms', 'retry-sms', 'retry-otp', 'warranty_free', 'failed', gmdate('Y-m-d\TH:i:s\Z', time() - 90));
check(MessageDeliveryLog::retryReason(MessageDeliveryLog::find('sms', 'retry-sms')) === '', 'Fresh failed OTP not retryable');
$_ENV['SMS_PROVIDER'] = 'log'; $_ENV['APP_ENV'] = 'testing'; $_SERVER['REMOTE_ADDR'] = 'admin-test'; setting('wa_enabled', '0');
$retry = MessageDeliveryLog::retry('sms', 'retry-sms');
check($retry['success'] && !isset($retry['debug_code']), 'Admin retry failed or leaked OTP');
check(db_fetch_one("SELECT COUNT(*) AS c FROM otp_challenges WHERE mobile = '9002921509'")['c'] === 2, 'Retry did not create new challenge');
rejects(fn() => MessageDeliveryLog::retry('sms', 'retry-sms'), DomainException::class);
challenge('paired', '9002921510'); seedMessage('whatsapp', 'paired-wa', 'paired', 'otp', 'failed'); seedMessage('sms', 'paired-sms', 'paired', 'warranty_free', 'accepted');
check(MessageDeliveryLog::retryReason(MessageDeliveryLog::find('whatsapp', 'paired-wa')) !== '', 'Accepted SMS fallback allowed duplicate OTP retry');
db_execute("UPDATE otp_challenges SET verified_at = 'verified' WHERE id = 'paired'");
check(MessageDeliveryLog::retryReason(MessageDeliveryLog::find('whatsapp', 'paired-wa')) !== '', 'Verified OTP retry allowed');
challenge('expired', '9002921511', 700); seedMessage('sms', 'expired-sms', 'expired', 'warranty_free', 'failed');
rejects(fn() => MessageDeliveryLog::retry('sms', 'expired-sms'), DomainException::class);

db_execute('INSERT INTO website_leads VALUES (?, ?, ?, ?, ?, ?, ?)', ['lead-retry', 'Test', 'contact', '9002921509', 'approved', '{"whatsapp_consent":true}', '2026-10-03']);
seedMessage('whatsapp', 'lead-old', 'lead-retry', 'lead_received', 'failed');
check(MessageDeliveryLog::retryReason(MessageDeliveryLog::find('whatsapp', 'lead-old')) !== '', 'Stale enquiry event retry allowed');
seedMessage('whatsapp', 'lead-current', 'lead-retry', 'lead_approved', 'failed');
check(MessageDeliveryLog::retryReason(MessageDeliveryLog::find('whatsapp', 'lead-current')) === '', 'Current enquiry retry denied');
db_execute("UPDATE website_leads SET details_json = '{}' WHERE id = 'lead-retry'");
rejects(fn() => MessageDeliveryLog::retry('whatsapp', 'lead-current'), DomainException::class);
rejects(fn() => MessageDeliveryLog::retry('invalid', 'id'), OutOfBoundsException::class);
check(!str_contains(json_encode(MessageDeliveryLog::listing([])), 'old-hash'), 'History exposed OTP hashes');

foreach (['employee' => 403, 'admin' => 200, 'bad-filter' => 422, 'retry-csrf' => 403, 'twilio-invalid' => 403, 'twilio-valid' => 204, 'msg91-invalid' => 403, 'msg91-valid' => 204] as $mode => $expected) {
    $process = proc_open([PHP_BINARY, __FILE__, '--controller', $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $body = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($process) === 0 && trim($error) === (string) $expected, "Controller {$mode} returned unexpected status: {$error}");
    if ($mode === 'admin') check(json_decode($body, true)['data']['total'] === 1, 'Admin endpoint response invalid');
}
echo "Message delivery tracking, filtering, retries, callbacks and authorization passed.\n";
