<?php

/**
 * Provider credentials and per-event delivery chains, both admin-editable.
 *
 * Credentials deliberately do NOT live in website_settings: the masking in
 * WebsiteController::settings() and AdminController::getSettings() is a deny-list
 * on key names, so a credential kept outside that table cannot leak through
 * either endpoint by somebody forgetting to extend a list. A secret field is
 * AES-256-GCM ciphertext (core/Secrets.php) and `hint` is the only part any
 * response is allowed to carry; a non-secret field an operator has to be able
 * to read back — a template name, a sender ID, a route — is stored in
 * value_plain and returned as-is. The same table holds both so one provider's
 * configuration is one query and one atomic save.
 *
 * The seeded chains reproduce today's behaviour rather than changing it. Every
 * step whose provider has no credentials is skipped at dispatch time, so an
 * operator with only one provider configured gets exactly that provider, and
 * the email step — first in each chain, as asked — stays inert until SMTP
 * credentials are entered in the panel.
 */
class MessagingCredentials extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS messaging_credentials (
                id           {uuid} PRIMARY KEY,
                provider_key {str:40} NOT NULL,
                field_key    {str:40} NOT NULL,
                is_secret    {bool} NOT NULL DEFAULT 1,
                value_cipher {text},
                value_plain  {text},
                hint         {str:80},
                updated_by   {uuid},
                updated_at   {ts} NOT NULL,
                CONSTRAINT uq_messaging_credential UNIQUE (provider_key, field_key)
            ) {opts};

            CREATE TABLE IF NOT EXISTS messaging_routes (
                id           {uuid} PRIMARY KEY,
                event_name   {str:40} NOT NULL,
                provider_key {str:40} NOT NULL,
                position     {int} NOT NULL DEFAULT 10,
                enabled      {bool} NOT NULL DEFAULT 1,
                updated_at   {ts} NOT NULL,
                CONSTRAINT uq_messaging_route UNIQUE (event_name, provider_key)
            ) {opts};

            CREATE INDEX IF NOT EXISTS idx_messaging_route_event ON messaging_routes(event_name, position);
        ");

        $this->seedChains();
    }

    /**
     * Only ever fills an event that has no rows at all, so an operator's own
     * ordering survives a re-run of this migration.
     */
    private function seedChains(): void
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');

        // [event, [[provider, position, enabled], ...]]
        $defaults = [
            // Email first, as asked. Falls through to WhatsApp then every SMS
            // provider; unconfigured steps are skipped, so this is self-tuning.
            'otp' => [
                ['email.smtp', 10, 1],
                ['whatsapp.meta', 20, 1],
                ['whatsapp.msg91', 30, 1],
                ['sms.msg91', 40, 1],
                ['sms.fast2sms', 50, 1],
                ['sms.twilio', 60, 1],
                ['sms.log', 70, 1],
            ],
            // Exactly today's order: EmailService first, WhatsApp after.
            'lead_received' => [['email.smtp', 10, 1], ['whatsapp.meta', 20, 1], ['whatsapp.msg91', 30, 1]],
            'lead_approved' => [['email.smtp', 10, 1], ['whatsapp.meta', 20, 1], ['whatsapp.msg91', 30, 1]],
            'lead_rejected' => [['email.smtp', 10, 1], ['whatsapp.meta', 20, 1], ['whatsapp.msg91', 30, 1]],
            // WhatsApp-only today. The email step is seeded DISABLED so that
            // deploying this does not start mailing customers a confirmation
            // they have never received before; the panel can switch it on.
            'warranty_received' => [['email.smtp', 10, 0], ['whatsapp.meta', 20, 1], ['whatsapp.msg91', 30, 1]],
        ];

        $existing = $this->pdo->query('SELECT DISTINCT event_name FROM messaging_routes')->fetchAll(PDO::FETCH_COLUMN);
        $insert = $this->pdo->prepare(
            'INSERT INTO messaging_routes (id, event_name, provider_key, position, enabled, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($defaults as $event => $steps) {
            if (in_array($event, $existing ?: [], true)) {
                continue;
            }

            foreach ($steps as [$provider, $position, $enabled]) {
                $insert->execute([$this->uuid(), $event, $provider, $position, $enabled, $now]);
            }
        }
    }

    /** Uuid::v4 is an api/support class a migration cannot assume is loaded. */
    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public function down()
    {
        $this->drop(['messaging_credentials', 'messaging_routes']);
    }
}
