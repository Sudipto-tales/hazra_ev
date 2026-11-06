<?php
// php tests/webhook-events.php — isolated SQLite, no network, no provider calls.
//
// The point of the webhook log is that a REFUSED call leaves a trace, so most
// of this suite is about what gets recorded when authentication fails, and
// about replay being safe to repeat.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('__BASEDIR__', dirname(__DIR__));
require_once __BASEDIR__ . '/config/env.php';
require_once __BASEDIR__ . '/config/migration.php';
require_once __BASEDIR__ . '/api/support/Uuid.php';
require_once __BASEDIR__ . '/core/ApiController.php';
require_once __BASEDIR__ . '/core/ApiRequest.php';
require_once __BASEDIR__ . '/database/migrations/028_whatsapp_messages.php';
require_once __BASEDIR__ . '/database/migrations/029_message_delivery.php';
require_once __BASEDIR__ . '/database/migrations/032_messaging_credentials.php';
require_once __BASEDIR__ . '/database/migrations/033_webhook_events.php';
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
CREATE TABLE otp_challenges (id TEXT PRIMARY KEY, mobile TEXT, purpose TEXT, code_hash TEXT, expires_at TEXT, attempts INTEGER, created_at TEXT, ip_address TEXT, verified_at TEXT, session_token TEXT, consumed_at TEXT);");
(new WhatsAppMessages($pdo))->up();
(new MessageDelivery($pdo))->up();
foreach (['MessagingCredentials', 'WebhookEvents', 'MessageAttempts', 'OtpCodeCipher'] as $class) { $m = new $class($pdo); $m->up(); $m->up(); }

require_once __BASEDIR__ . '/core/messaging/WebhookEventLog.php';

$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_URL'] = 'https://example.test';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

// ------------------------------------------------- a refused call is recorded
$_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . str_repeat('a', 64);
$_SERVER['HTTP_USER_AGENT'] = 'facebookplatform/1.0';
$rejected = WebhookEventLog::record([
    'provider' => 'whatsapp.meta', 'route' => 'api/v1/whatsapp/webhook',
    'signatureValid' => false, 'httpStatus' => 403,
    'payload' => '{"entry":[{"id":"1"}],"authkey":"super-secret"}',
    'errorCode' => 'signature_invalid', 'errorDetail' => 'HMAC mismatch.',
    'startedAt' => microtime(true),
]);
check($rejected !== '', 'A refused webhook was not recorded');
$row = WebhookEventLog::find($rejected);
check((int) $row['signature_valid'] === 0 && (int) $row['http_status'] === 403, 'Refusal not recorded faithfully');
check($row['ip'] === '203.0.113.9' && $row['method'] === 'POST', 'Request metadata missing');

// Nothing secret survives into the log.
check(!str_contains($row['payload_redacted'], 'super-secret'), 'A credential-shaped payload key was stored');
check(str_contains($row['payload_redacted'], '[redacted]'), 'Redaction marker missing');
check(!str_contains($row['headers_redacted'], str_repeat('a', 64)), 'A signature header value was stored');
check(str_contains($row['headers_redacted'], 'present, '), 'An authentication header was not reduced to its shape');
check(str_contains($row['headers_redacted'], 'facebookplatform'), 'A safe header was dropped');
unset($_SERVER['HTTP_X_HUB_SIGNATURE_256'], $_SERVER['HTTP_USER_AGENT']);

// --------------------------------------- an authenticated call that matched nothing
$unmatched = WebhookEventLog::record([
    'provider' => 'sms.msg91', 'route' => 'api/v1/sms/webhook/msg91',
    'signatureValid' => true, 'httpStatus' => 204, 'eventType' => 'delivered',
    'recipient' => '919002921509', 'matched' => [],
    'payload' => ['CRQID' => 'late-sms', 'requestId' => 'req-1', '_status' => 'delivered'],
    'errorCode' => 'no_match', 'errorDetail' => 'No recorded send matches.',
]);
check((int) WebhookEventLog::find($unmatched)['matched_count'] === 0, 'An unmatched call was counted as applied');

// ------------------------------------------------------------------ filters
$applied = WebhookEventLog::record([
    'provider' => 'whatsapp.meta', 'route' => 'api/v1/whatsapp/webhook',
    'signatureValid' => true, 'httpStatus' => 200, 'eventType' => 'delivered',
    'matched' => ['wa-1'], 'payload' => ['entry' => []],
]);
$all = WebhookEventLog::listing([]);
check($all['total'] === 3, 'Listing lost events');
check($all['summary']['rejected'] === 1 && $all['summary']['unmatched'] === 1 && $all['summary']['applied'] === 1, 'Summary counts wrong: ' . json_encode($all['summary']));
check(WebhookEventLog::listing(['provider' => 'sms.msg91'])['total'] === 1, 'Provider filter');
check(WebhookEventLog::listing(['result' => 'rejected'])['total'] === 1, 'Rejected filter');
check(WebhookEventLog::listing(['result' => 'unmatched'])['total'] === 1, 'Unmatched filter');
check(WebhookEventLog::listing(['q' => '919002921509'])['total'] === 1, 'Recipient search');
check(WebhookEventLog::listing(['q' => 'no_match'])['total'] === 1, 'Error code search');
foreach ([['provider' => 'nonsense'], ['result' => 'nonsense'], ['from' => '2026-02-30'], ['q' => str_repeat('x', 121)]] as $filter) {
    rejects(fn() => WebhookEventLog::listing($filter), InvalidArgumentException::class);
}

// ------------------------------------------------------------------- replay
check(!WebhookEventLog::find($rejected)['signature_valid'], 'precondition');
rejects(fn() => WebhookEventLog::replay($rejected), DomainException::class);          // refused call
rejects(fn() => WebhookEventLog::replay($applied), DomainException::class);           // already applied
rejects(fn() => WebhookEventLog::replay(Uuid::v4()), OutOfBoundsException::class);    // unknown id

// A verified call that arrived before its message row existed: replay once the
// row is there, and it applies.
db_execute("INSERT INTO sms_messages (id, reference_id, event_name, mobile, provider, provider_id, status, created_at, updated_at)
    VALUES ('late-sms', 'ref-late', 'warranty_free', '919002921509', 'msg91', 'req-1', 'accepted', '2026-11-01T00:00:00Z', '2026-11-01T00:00:00Z')");
$result = WebhookEventLog::replay($unmatched);
check($result['applied'] === 1, 'Replay did not apply a late callback');
check(db_fetch_one("SELECT status FROM sms_messages WHERE id = 'late-sms'")['status'] === 'delivered', 'Replay did not move the message');
check(WebhookEventLog::find($unmatched)['replayed_at'] !== null, 'Replay was not stamped');

// Replaying again is refused because it has now been applied, and even forcing
// the same payload through the handler cannot regress a delivered message.
rejects(fn() => WebhookEventLog::replay($unmatched), DomainException::class);
require_once __BASEDIR__ . '/core/SmsDeliveryService.php';
SmsDeliveryService::updateStatus('msg91', 'late-sms', 'req-1', 'sent');
check(db_fetch_one("SELECT status FROM sms_messages WHERE id = 'late-sms'")['status'] === 'delivered', 'An out-of-order callback regressed a delivered message');

// --------------------------------------- MSG91 WhatsApp status correlation
db_execute("INSERT INTO whatsapp_messages (id, reference_id, event_name, mobile, template_name, provider, provider_id, status, created_at, updated_at)
    VALUES ('wa-msg91', 'ref-1', 'otp', '919002921509', 'otp', 'msg91', 'msg91-req-9', 'accepted', '2026-11-01T00:00:00Z', '2026-11-01T00:00:00Z')");
require_once __BASEDIR__ . '/core/WhatsAppService.php';
check(WhatsAppService::updateMsg91Status('msg91-req-9', '919002921509', 'delivered') === ['wa-msg91'], 'MSG91 WhatsApp report did not match by request id');
check(WhatsAppService::updateMsg91Status('msg91-req-9', '919002921509', 'sent') === [], 'MSG91 WhatsApp report regressed a delivered message');

// A Meta report must not touch an MSG91 row, and the other way round.
db_execute("UPDATE whatsapp_messages SET status = 'accepted' WHERE id = 'wa-msg91'");
$metaPayload = ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'msg91-req-9', 'status' => 'read']]]]]]]];
check(WhatsAppService::updateStatuses($metaPayload) === [], 'A Meta webhook moved an MSG91 row');
check(db_fetch_one("SELECT status FROM whatsapp_messages WHERE id = 'wa-msg91'")['status'] === 'accepted', 'Cross-provider status leak');

// Correlation by recipient, for a report that carries no request id.
db_execute("INSERT INTO whatsapp_messages (id, reference_id, event_name, mobile, template_name, provider, status, created_at, updated_at)
    VALUES ('wa-msg91-b', 'ref-2', 'otp', '919002921599', 'otp', 'msg91', 'accepted', '2026-11-02T00:00:00Z', '2026-11-02T00:00:00Z')");
check(WhatsAppService::updateMsg91Status('', '919002921599', 'delivered') === ['wa-msg91-b'], 'Recipient correlation failed');
check(WhatsAppService::updateMsg91Status('', '919000000000', 'delivered') === [], 'Recipient correlation matched a stranger');

// ------------------------------------------------------------------- prune
db_execute("UPDATE webhook_events SET received_at = ? WHERE id = ?", [gmdate('Y-m-d\TH:i:s\Z', time() - 40 * 86400), $applied]);
check(WebhookEventLog::prune(30) === 1, 'Prune did not remove an expired event');
check(WebhookEventLog::listing([])['total'] === 2, 'Prune removed the wrong rows');
MessagingRouter::saveSetting('webhook_retention_days', '1');
db_execute("UPDATE webhook_events SET received_at = ?", [gmdate('Y-m-d\TH:i:s\Z', time() - 2 * 86400)]);
check(WebhookEventLog::prune() === 2, 'Prune did not read the configured retention');

echo "Webhook event logging, filtering, replay and provider correlation passed.\n";
