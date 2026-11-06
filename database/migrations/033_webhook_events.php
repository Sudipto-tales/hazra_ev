<?php

/**
 * Every inbound provider callback, including the ones that are rejected.
 *
 * Before this table a bad signature returned 403 and left nothing behind, which
 * is precisely the case an operator needs to debug: a webhook that "does not
 * work" is almost always arriving and being refused. Rejected calls are
 * therefore recorded with signature_valid = 0 and the status that was returned.
 *
 * Nothing secret is stored: headers are filtered to a known-safe list and the
 * body is truncated, with any token-shaped value replaced before insert
 * (WebhookEventLog::record). Retention is admin-set and pruned by
 * `php vayu webhooks:prune`, because this table grows with provider traffic.
 */
class WebhookEvents extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS webhook_events (
                id                 {uuid} PRIMARY KEY,
                provider_key       {str:40} NOT NULL,
                route              {str:128} NOT NULL,
                method             {str:8} NOT NULL,
                ip                 {str:64},
                signature_valid    {bool} NOT NULL DEFAULT 0,
                http_status        {int} NOT NULL,
                event_type         {str:40},
                recipient          {str:32},
                matched_message_id {uuid},
                matched_count      {int} NOT NULL DEFAULT 0,
                payload_redacted   {text},
                headers_redacted   {text},
                error_code         {str:80},
                error_detail       {str:512},
                processing_ms      {int} NOT NULL DEFAULT 0,
                replayed_at        {ts},
                received_at        {ts} NOT NULL
            ) {opts};

            CREATE INDEX IF NOT EXISTS idx_webhook_events_time ON webhook_events(received_at);
            CREATE INDEX IF NOT EXISTS idx_webhook_events_provider ON webhook_events(provider_key, received_at);
            CREATE INDEX IF NOT EXISTS idx_webhook_events_valid ON webhook_events(signature_valid, received_at);
            CREATE INDEX IF NOT EXISTS idx_webhook_events_matched ON webhook_events(matched_message_id);
        ");
    }

    public function down()
    {
        $this->drop(['webhook_events']);
    }
}
