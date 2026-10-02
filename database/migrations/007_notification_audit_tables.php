<?php

/**
 * Notifications fan out to recipients rather than carrying a read flag: one
 * product listing notifies the whole field team and each person's read state
 * is their own. The badge is the index on unread rows — partial on SQLite,
 * a plain index on MySQL, which has no partial indexes.
 *
 * Types are Dialect tokens; see config/dialect.php and the port note in 001.
 */
class NotificationAuditTables extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id         {uuid} PRIMARY KEY,
                org_id     {uuid} NOT NULL,
                kind       {str:32} NOT NULL CHECK (kind IN
                             ('new_product','product_updated','report_reviewed','announcement')),
                title      {str} NOT NULL,
                message    {text} NOT NULL {default ''},
                product_id {uuid},
                report_id  {uuid},
                created_at {ts} NOT NULL,
                FOREIGN KEY (org_id) REFERENCES organizations(id),
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (report_id) REFERENCES visit_reports(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_notifications_org ON notifications (org_id, created_at DESC);

            CREATE TABLE IF NOT EXISTS notification_recipients (
                notification_id {uuid} NOT NULL,
                user_id         {uuid} NOT NULL,
                read_at         {ts},
                PRIMARY KEY (notification_id, user_id),
                FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_unread_notifications ON notification_recipients (user_id) WHERE read_at IS NULL;
            CREATE INDEX IF NOT EXISTS idx_recipients_user ON notification_recipients (user_id);

            -- Tracking-rule changes, roster edits and delistings are decisions
            -- somebody will later be asked to justify.
            CREATE TABLE IF NOT EXISTS audit_log (
                id          {autoid},
                org_id      {uuid} NOT NULL,
                actor_id    {uuid},
                entity      {str:64} NOT NULL,   -- tracking_config | employee | product | report_review
                entity_id   {str:128} NOT NULL,
                action      {str:32} NOT NULL,   -- create | update | delist | deactivate
                -- `before` is a MySQL reserved word; both are quoted so the pair
                -- reads the same everywhere. Backticks are valid in SQLite too.
                `before`    {json},              -- JSON
                `after`     {json},              -- JSON
                occurred_at {ts} NOT NULL,
                FOREIGN KEY (actor_id) REFERENCES users(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_log (entity, entity_id, occurred_at DESC);
        ");
    }

    public function down()
    {
        $this->drop(['audit_log', 'notification_recipients', 'notifications']);
    }
}
