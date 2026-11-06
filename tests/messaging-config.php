<?php
// php tests/messaging-config.php — isolated SQLite, fake transports, no network.
//
// Covers the three things that make admin-managed messaging safe to ship:
// a credential never comes back out of an API-shaped response, an unkeyed
// server degrades to the environment instead of half-working, and a chain
// skips what it cannot send rather than failing the request.
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
function throws(callable $operation, string $class, string $message) {
    try { $operation(); } catch (Throwable $e) { if ($e instanceof $class) return; throw new RuntimeException($message . ' (got ' . get_class($e) . ')'); }
    throw new RuntimeException($message);
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE website_settings (id TEXT PRIMARY KEY, group_name TEXT, setting_key TEXT, setting_value TEXT, updated_at TEXT, UNIQUE(group_name, setting_key));
CREATE TABLE warranty_registrations (id TEXT PRIMARY KEY);
CREATE TABLE otp_challenges (id TEXT PRIMARY KEY, mobile TEXT, purpose TEXT, code_hash TEXT, expires_at TEXT, attempts INTEGER, created_at TEXT, ip_address TEXT, verified_at TEXT, session_token TEXT, consumed_at TEXT);");
(new WhatsAppMessages($pdo))->up();
(new MessageDelivery($pdo))->up();
foreach (['MessagingCredentials', 'MessageAttempts', 'OtpCodeCipher'] as $class) { (new $class($pdo))->up(); }

require_once __BASEDIR__ . '/core/Secrets.php';
require_once __BASEDIR__ . '/core/messaging/Router.php';

$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_URL'] = 'https://example.test';

// ---------------------------------------------------------------- no APP_KEY
$_ENV['APP_KEY'] = '';
Secrets::flushKey();
check(!Secrets::available(), 'Reported encryption available without a key');
throws(fn() => Secrets::encrypt('x', Secrets::PURPOSE_CREDENTIAL), RuntimeException::class, 'Encrypted without a key');
check(Secrets::decrypt('v1.bogus', Secrets::PURPOSE_CREDENTIAL) === null, 'Decrypted without a key');

$tokenField = MessagingRegistry::field('sms.msg91', 'webhook_token');
throws(fn() => MessagingCredentialStore::set('sms.msg91', $tokenField, 'secret-value'), RuntimeException::class, 'Stored a secret without a key');
// A non-secret must still be savable: template names and routes are not secrets
// and an operator should not need APP_KEY to set them.
MessagingCredentialStore::set('sms.msg91', MessagingRegistry::field('sms.msg91', 'template_id'), 'template-1');
check(MessagingRegistry::value('sms.msg91', 'template_id') === 'template-1', 'Non-secret field needs a key');

// ---------------------------------------------------------- with an APP_KEY
$_ENV['APP_KEY'] = Secrets::generateKey();
Secrets::flushKey();
check(Secrets::available(), 'A valid key was not accepted');

$packed = Secrets::encrypt('top-secret-value', Secrets::PURPOSE_CREDENTIAL);
check($packed !== 'top-secret-value' && !str_contains($packed, 'top-secret'), 'Ciphertext contains the plaintext');
check(Secrets::decrypt($packed, Secrets::PURPOSE_CREDENTIAL) === 'top-secret-value', 'Round trip failed');
// A purpose is additional authenticated data, so a credential ciphertext
// cannot be reused as an OTP one.
check(Secrets::decrypt($packed, Secrets::PURPOSE_OTP) === null, 'Purpose mismatch decrypted');
$tampered = substr($packed, 0, -4) . 'AAAA';
check(Secrets::decrypt($tampered, Secrets::PURPOSE_CREDENTIAL) === null, 'Tampered ciphertext decrypted');
// A foreign key must not decrypt, and must not throw either.
$_ENV['APP_KEY'] = Secrets::generateKey();
Secrets::flushKey();
check(Secrets::decrypt($packed, Secrets::PURPOSE_CREDENTIAL) === null, 'A different key decrypted the value');
$_ENV['APP_KEY'] = 'base64:not-32-bytes';
Secrets::flushKey();
check(!Secrets::available(), 'A malformed key was accepted');

$_ENV['APP_KEY'] = Secrets::generateKey();
Secrets::flushKey();
MessagingCredentialStore::flush();

// --------------------------------------------------- resolution and masking
$accessToken = MessagingRegistry::field('whatsapp.meta', 'access_token');
$_ENV['WHATSAPP_ACCESS_TOKEN'] = 'from-environment';
check(MessagingCredentialStore::get('whatsapp.meta', $accessToken) === 'from-environment', 'Environment fallback broken');
check(MessagingCredentialStore::source('whatsapp.meta', $accessToken) === 'environment', 'Source not reported as environment');

MessagingCredentialStore::set('whatsapp.meta', $accessToken, 'EAAG-stored-in-database', 'admin-1');
check(MessagingCredentialStore::get('whatsapp.meta', $accessToken) === 'EAAG-stored-in-database', 'Database value did not win over the environment');
check(MessagingCredentialStore::source('whatsapp.meta', $accessToken) === 'database', 'Source not reported as database');

$stored = db_fetch_one('SELECT * FROM messaging_credentials WHERE provider_key = ? AND field_key = ?', ['whatsapp.meta', 'access_token']);
check(!str_contains((string) $stored['value_cipher'], 'stored-in-database'), 'Plaintext written to the table');
check($stored['value_plain'] === null, 'A secret was written to the plaintext column');
check($stored['updated_by'] === 'admin-1', 'Credential change not attributed');

$state = MessagingCredentialStore::state('whatsapp.meta', $accessToken);
check($state['configured'] === true && $state['value'] === '', 'A secret leaked through state()');
check($state['hint'] === '••••base' || str_starts_with($state['hint'], '••••'), 'Hint is not masked: ' . $state['hint']);
check(!str_contains(json_encode($state), 'stored-in-database'), 'A secret leaked through the API shape');

// A blank save keeps the stored secret: the form posts every field each time
// and cannot echo one back.
MessagingCredentialStore::set('whatsapp.meta', $accessToken, '   ', 'admin-1');
check(MessagingRegistry::value('whatsapp.meta', 'access_token') === 'EAAG-stored-in-database', 'A blank save cleared a stored secret');
MessagingCredentialStore::forget('whatsapp.meta', 'access_token');
check(MessagingRegistry::value('whatsapp.meta', 'access_token') === 'from-environment', 'Clearing did not fall back to the environment');

// The old website_settings rows keep working until the panel takes a field over.
db_execute("INSERT INTO website_settings (id, group_name, setting_key, setting_value, updated_at) VALUES (?, 'general', 'wa_template_otp', 'legacy_otp_template', '')", [Uuid::v4()]);
MessagingCredentialStore::flush();
check(MessagingRegistry::value('whatsapp.meta', 'template_otp') === 'legacy_otp_template', 'Legacy settings fallback broken');
check(in_array('smtp_password', MessagingCredentialStore::reservedSettingKeys(), true), 'smtp_password is still writable through the generic settings endpoints');
check(in_array('whatsapp.meta.access_token', MessagingCredentialStore::reservedSettingKeys(), true), 'Credential keys not reserved');

// ------------------------------------------------------ readiness reporting
$_ENV['WHATSAPP_ACCESS_TOKEN'] = ''; MessagingCredentialStore::flush();
check(!MessagingRegistry::configured('whatsapp.meta'), 'Reported configured with no access token');
check(MessagingRegistry::skipReason('whatsapp.meta', 'otp') === 'Credentials are incomplete.', 'Wrong skip reason for missing credentials');

$_ENV['WHATSAPP_ACCESS_TOKEN'] = 'token'; $_ENV['WHATSAPP_PHONE_NUMBER_ID'] = '123'; $_ENV['WHATSAPP_GRAPH_VERSION'] = 'v21.0';
MessagingCredentialStore::flush();
check(MessagingRegistry::configured('whatsapp.meta'), 'Complete credentials reported incomplete');
check(MessagingRegistry::skipReason('whatsapp.meta', 'lead_approved') !== '', 'An event with no template name was reported sendable');
check(MessagingRegistry::skipReason('whatsapp.meta', 'otp') === '', 'A fully configured event was reported unsendable');

// Fast2SMS: the DLT route needs a sender ID and a message ID; the OTP route does not.
$_ENV['FAST2SMS_API_KEY'] = 'key'; $_ENV['FAST2SMS_ROUTE'] = 'otp';
MessagingCredentialStore::flush();
check(MessagingRegistry::configured('sms.fast2sms'), 'Fast2SMS OTP route reported incomplete');
$_ENV['FAST2SMS_ROUTE'] = 'dlt';
MessagingCredentialStore::flush();
check(!MessagingRegistry::configured('sms.fast2sms'), 'Fast2SMS DLT route accepted without a sender ID');
$_ENV['FAST2SMS_SENDER_ID'] = 'HAZRAE'; $_ENV['FAST2SMS_MESSAGE_ID'] = '12345';
MessagingCredentialStore::flush();
check(MessagingRegistry::configured('sms.fast2sms'), 'Fast2SMS DLT route rejected with full credentials');

// SMS carries OTP only, and the development log is refused in production.
check(MessagingRegistry::skipReason('sms.msg91', 'lead_received') !== '', 'An SMS provider claimed to carry enquiry messages');
$_ENV['APP_ENV'] = 'production';
check(MessagingRegistry::skipReason('sms.log', 'otp') !== '', 'The development log was allowed in production');
$_ENV['APP_ENV'] = 'testing';
check(MessagingRegistry::skipReason('sms.log', 'otp') === '', 'The development log was refused in testing');

check(MessagingRegistry::webhookUrl('whatsapp.meta') === 'https://example.test/api/v1/whatsapp/webhook', 'Webhook URL not derived from APP_URL');
check(MessagingRegistry::webhookUrl('email.smtp') === '', 'SMTP was given a webhook URL');
check(!MessagingRegistry::webhookReady('whatsapp.meta'), 'Webhook reported ready with no app secret');
$_ENV['WHATSAPP_APP_SECRET'] = 'secret'; $_ENV['WHATSAPP_VERIFY_TOKEN'] = 'verify';
MessagingCredentialStore::flush();
check(MessagingRegistry::webhookReady('whatsapp.meta'), 'Webhook reported unready with full configuration');

// ------------------------------------------------------------------ chains
class RecordingTransport implements MessagingTransport
{
    /** @var list<string> every provider that was actually asked to send */
    public static array $calls = [];
    public function __construct(private string $provider, private bool $accept) {}
    public function send(array $message): array
    {
        self::$calls[] = $this->provider;
        return ['success' => $this->accept, 'id' => Uuid::v4(), 'status' => $this->accept ? 'accepted' : 'failed', 'error_detail' => 'fake'];
    }
}

db_execute("INSERT INTO website_settings (id, group_name, setting_key, setting_value, updated_at) VALUES (?, 'general', 'wa_enabled', '1', '')", [Uuid::v4()]);
MessagingCredentialStore::flush();

$chain = MessagingRouter::chain('otp');
check(array_column($chain, 'provider')[0] === 'email.smtp', 'Email is not the first step of the seeded OTP chain');
check(count($chain) === 7, 'Unexpected seeded OTP chain length');
$email = $chain[0];
check($email['willRun'] === false && str_contains($email['skipReason'], 'Credentials'), 'An unconfigured email step claimed it would run');

$_ENV['MSG91_AUTH_KEY'] = 'msg91-key';
MessagingCredentialStore::flush();
check(MessagingRegistry::configured('sms.msg91'), 'MSG91 SMS reported incomplete with a key and a template');
check(!MessagingRegistry::configured('whatsapp.msg91'), 'MSG91 WhatsApp reported complete with no integrated number');

MessagingRegistry::override('whatsapp.meta', new RecordingTransport('whatsapp.meta', false));
MessagingRegistry::override('sms.msg91', new RecordingTransport('sms.msg91', true));
MessagingRegistry::override('sms.fast2sms', new RecordingTransport('sms.fast2sms', true));

RecordingTransport::$calls = [];
$result = MessagingRouter::dispatch('otp', ['reference' => 'ref-1', 'mobile' => '9002921509', 'email' => '', 'params' => ['123456'], 'code' => '123456', 'payload' => ['purpose' => 'warranty_free']]);
check($result['success'] && $result['channel'] === 'sms', 'Chain did not fall through to SMS');
check(RecordingTransport::$calls === ['whatsapp.meta', 'sms.msg91'], 'Chain did not stop at the first acceptance: ' . implode(',', RecordingTransport::$calls));
$skipped = array_values(array_filter($result['attempts'], fn(array $a): bool => $a['skipped']));
check($skipped[0]['provider'] === 'email.smtp' && str_contains($skipped[0]['reason'], 'Credentials'), 'The unconfigured email step was not skipped with a reason');

// No recipient of that kind is a skip, not a failure — which is what makes an
// email-first default safe where no address was collected.
MessagingRegistry::override('email.smtp', new RecordingTransport('email.smtp', true));
$_ENV['SMTP_HOST'] = 'smtp.example.test'; $_ENV['SMTP_PORT'] = '587'; $_ENV['SMTP_USERNAME'] = 'u';
$_ENV['SMTP_PASSWORD'] = 'p'; $_ENV['SMTP_FROM_EMAIL'] = 'from@example.test';
MessagingCredentialStore::flush();
RecordingTransport::$calls = [];
$result = MessagingRouter::dispatch('otp', ['reference' => 'ref-2', 'mobile' => '9002921509', 'email' => '', 'code' => '123456', 'params' => ['123456']]);
check(!in_array('email.smtp', RecordingTransport::$calls, true), 'Email was attempted with no address');
$emailAttempt = array_values(array_filter($result['attempts'], fn(array $a): bool => $a['provider'] === 'email.smtp'))[0];
check(str_contains($emailAttempt['reason'], 'No email address'), 'Wrong skip reason for a missing address');

// With an address, email wins and nothing else is tried.
RecordingTransport::$calls = [];
$result = MessagingRouter::dispatch('otp', ['reference' => 'ref-3', 'mobile' => '9002921509', 'email' => 'customer@example.test', 'code' => '123456', 'params' => ['123456']]);
check($result['channel'] === 'email' && RecordingTransport::$calls === ['email.smtp'], 'Email-first chain did not stop at email');

// `only` pins a request to one channel.
RecordingTransport::$calls = [];
$result = MessagingRouter::dispatch('otp', ['reference' => 'ref-4', 'mobile' => '9002921509', 'email' => 'customer@example.test', 'code' => '123456', 'params' => ['123456'], 'only' => 'sms']);
check($result['channel'] === 'sms' && RecordingTransport::$calls === ['sms.msg91'], 'Channel pinning was not honoured');

// `all` runs every usable step — "WhatsApp alongside email" for enquiries.
MessagingRouter::saveSetting('chain_mode_lead_received', MessagingRouter::MODE_ALL);
db_execute("INSERT INTO website_settings (id, group_name, setting_key, setting_value, updated_at) VALUES (?, 'general', 'wa_auto_leads', '1', '')", [Uuid::v4()]);
db_execute("INSERT INTO website_settings (id, group_name, setting_key, setting_value, updated_at) VALUES (?, 'general', 'wa_template_lead_received', 'received', '')", [Uuid::v4()]);
MessagingCredentialStore::flush();
check(MessagingRouter::mode('lead_received') === MessagingRouter::MODE_ALL, 'Chain mode was not saved');
RecordingTransport::$calls = [];
$lead = ['id' => 'lead-1', 'name' => 'Test', 'type' => 'contact', 'phone' => '9002921509', 'email' => 'customer@example.test', 'details' => ['whatsapp_consent' => '1']];
$result = MessagingRouter::dispatch('lead_received', ['reference' => 'lead-1', 'mobile' => '9002921509', 'email' => 'customer@example.test',
    'params' => ['Test', 'Contact'], 'payload' => ['lead' => $lead, 'whatsappConsent' => true]]);
check(RecordingTransport::$calls === ['email.smtp', 'whatsapp.meta'], 'Mode `all` stopped early: ' . implode(',', RecordingTransport::$calls));

// Opt-in is required for an enquiry message, and is not required for an OTP the
// customer just asked for.
RecordingTransport::$calls = [];
$result = MessagingRouter::dispatch('lead_received', ['reference' => 'lead-2', 'mobile' => '9002921509', 'email' => 'customer@example.test',
    'params' => ['Test', 'Contact'], 'payload' => ['lead' => $lead, 'whatsappConsent' => false]]);
check(!in_array('whatsapp.meta', RecordingTransport::$calls, true), 'A WhatsApp enquiry message was sent without consent');

// Reordering is a plain save, and an unknown provider cannot enter a chain.
MessagingRouter::saveChain('otp', [
    ['provider' => 'sms.msg91', 'position' => 10, 'enabled' => true],
    ['provider' => 'email.smtp', 'position' => 20, 'enabled' => false],
]);
$chain = MessagingRouter::chain('otp');
check(count($chain) === 2 && $chain[0]['provider'] === 'sms.msg91', 'Chain reorder did not persist');
check($chain[1]['willRun'] === false && $chain[1]['skipReason'] === 'Switched off for this event.', 'A disabled step claimed it would run');

// An emptied chain falls back to the behaviour the code had before chains.
db_execute("DELETE FROM messaging_routes WHERE event_name = 'otp'");
$_ENV['SMS_PROVIDER'] = 'msg91';
$fallbackChain = MessagingRouter::chain('otp');
check(array_column($fallbackChain, 'provider') === ['whatsapp.meta', 'sms.msg91'], 'Legacy fallback chain changed');

MessagingRegistry::override('email.smtp', null);
MessagingRegistry::override('whatsapp.meta', null);
MessagingRegistry::override('sms.msg91', null);
MessagingRegistry::override('sms.fast2sms', null);

echo "Messaging credential store, provider registry and delivery chains passed.\n";
