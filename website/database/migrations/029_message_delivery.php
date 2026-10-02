<?php

class MessageDelivery extends Migration
{
    public function up()
    {
        $this->addColumn('whatsapp_messages', 'error_detail', '{str:512}');
        $this->exec("CREATE TABLE IF NOT EXISTS sms_messages (
            id {uuid} PRIMARY KEY,
            reference_id {str:64} NOT NULL,
            event_name {str:40} NOT NULL,
            mobile {str:20} NOT NULL,
            provider {str:20} NOT NULL,
            provider_id {str:255},
            status {str:20} NOT NULL,
            error_code {str:80},
            error_detail {str:512},
            created_at {ts} NOT NULL,
            updated_at {ts} NOT NULL
        ) {opts};
        CREATE INDEX IF NOT EXISTS idx_sms_reference ON sms_messages(reference_id, created_at);
        CREATE INDEX IF NOT EXISTS idx_sms_provider ON sms_messages(provider, provider_id);
        CREATE INDEX IF NOT EXISTS idx_sms_status_time ON sms_messages(status, created_at);
        CREATE INDEX IF NOT EXISTS idx_whatsapp_status_time ON whatsapp_messages(status, created_at);");
    }
}
