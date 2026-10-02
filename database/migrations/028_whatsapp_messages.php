<?php

class WhatsAppMessages extends Migration
{
    public function up()
    {
        $this->addColumn('warranty_registrations', 'whatsapp_consent_at', '{ts}');
        $this->exec("CREATE TABLE IF NOT EXISTS whatsapp_messages (
            id {uuid} PRIMARY KEY,
            reference_id {str:64} NOT NULL,
            event_name {str:40} NOT NULL,
            mobile {str:20} NOT NULL,
            template_name {str:128} NOT NULL,
            provider_id {str:255},
            status {str:20} NOT NULL,
            error_code {str:80},
            created_at {ts} NOT NULL,
            updated_at {ts} NOT NULL
        ) {opts};
        CREATE INDEX IF NOT EXISTS idx_whatsapp_reference ON whatsapp_messages(reference_id, created_at);
        CREATE INDEX IF NOT EXISTS idx_whatsapp_provider ON whatsapp_messages(provider_id);");
    }
}
