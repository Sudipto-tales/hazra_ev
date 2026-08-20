<?php

/**
 * Notifications fan out to recipients rather than carrying a read flag: one
 * product listing notifies the whole field team and each person's read state
 * is their own. The badge is the partial index on unread rows.
 */
class NotificationAuditTables
{
    protected $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function up()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id         TEXT PRIMARY KEY,
                org_id     TEXT NOT NULL REFERENCES organizations(id),
                kind       TEXT NOT NULL CHECK (kind IN
                             ('new_product','product_updated','report_reviewed','announcement')),
                title      TEXT NOT NULL,
                message    TEXT NOT NULL DEFAULT '',
                product_id TEXT REFERENCES products(id) ON DELETE CASCADE,
                report_id  TEXT REFERENCES visit_reports(id) ON DELETE CASCADE,
                created_at TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_notifications_org ON notifications (org_id, created_at DESC);

            CREATE TABLE IF NOT EXISTS notification_recipients (
                notification_id TEXT NOT NULL REFERENCES notifications(id) ON DELETE CASCADE,
                user_id         TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                read_at         TEXT,
                PRIMARY KEY (notification_id, user_id)
            );
            CREATE INDEX IF NOT EXISTS idx_unread_notifications ON notification_recipients (user_id) WHERE read_at IS NULL;
            CREATE INDEX IF NOT EXISTS idx_recipients_user ON notification_recipients (user_id);

            -- Tracking-rule changes, roster edits and delistings are decisions
            -- somebody will later be asked to justify.
            CREATE TABLE IF NOT EXISTS audit_log (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                org_id      TEXT NOT NULL,
                actor_id    TEXT REFERENCES users(id),
                entity      TEXT NOT NULL,   -- tracking_config | employee | product | report_review
                entity_id   TEXT NOT NULL,
                action      TEXT NOT NULL,   -- create | update | delist | deactivate
                before      TEXT,            -- JSON
                after       TEXT,            -- JSON
                occurred_at TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_log (entity, entity_id, occurred_at DESC);
        ");
    }

    public function down()
    {
        foreach (['audit_log', 'notification_recipients', 'notifications'] as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
