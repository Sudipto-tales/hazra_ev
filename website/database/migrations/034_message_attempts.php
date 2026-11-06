<?php

/**
 * Email joins the delivery log, and every send learns which chain attempt it was.
 *
 * email_messages mirrors sms_messages column for column so
 * MessageDeliveryLog::unionSql() only needs a third arm — listing, filtering,
 * retry eligibility and the webhook status guards all keep working unchanged.
 * `provider` is 'smtp' rather than a vendor name because SMTP is the transport.
 *
 * attempt_group ties the steps of one fallback chain together (one OTP request
 * that tried email, then WhatsApp, then SMS is three rows and one group), and
 * chain_position records where in the chain each attempt sat. is_test marks
 * sends made by the panel's Send-test button so they can be filtered out of
 * real delivery numbers.
 *
 * Existing rows get no group, which reads correctly as "sent before chains
 * existed" rather than being backfilled into a group that never happened.
 */
class MessageAttempts extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS email_messages (
                id           {uuid} PRIMARY KEY,
                reference_id {str:64} NOT NULL,
                event_name   {str:40} NOT NULL,
                mobile       {str:191} NOT NULL,
                provider     {str:20} NOT NULL,
                provider_id  {str:255},
                status       {str:20} NOT NULL,
                error_code   {str:80},
                error_detail {str:512},
                created_at   {ts} NOT NULL,
                updated_at   {ts} NOT NULL
            ) {opts};

            CREATE INDEX IF NOT EXISTS idx_email_reference ON email_messages(reference_id, created_at);
            CREATE INDEX IF NOT EXISTS idx_email_status_time ON email_messages(status, created_at);
        ");

        // `mobile` carries the recipient address on the email arm. The column
        // keeps its name so the three-way UNION stays a plain column list;
        // MessageDeliveryLog presents it as "recipient" either way.
        foreach (['whatsapp_messages', 'sms_messages', 'email_messages'] as $table) {
            $this->addColumn($table, 'attempt_group', '{uuid}');
            $this->addColumn($table, 'chain_position', '{int}');
            $this->addColumn($table, 'is_test', '{bool} NOT NULL DEFAULT 0');
        }

        // WhatsApp can now arrive through Meta directly or through MSG91, so the
        // channel alone no longer identifies the provider. Existing rows predate
        // the indirect route and are all Meta, which is what the union's
        // COALESCE to 'meta' says for a NULL.
        $this->addColumn('whatsapp_messages', 'provider', '{str:20}');

        $this->exec("
            CREATE INDEX IF NOT EXISTS idx_whatsapp_attempt ON whatsapp_messages(attempt_group);
            CREATE INDEX IF NOT EXISTS idx_sms_attempt ON sms_messages(attempt_group);
            CREATE INDEX IF NOT EXISTS idx_email_attempt ON email_messages(attempt_group);
        ");
    }

    public function down()
    {
        $this->drop(['email_messages']);
    }
}
