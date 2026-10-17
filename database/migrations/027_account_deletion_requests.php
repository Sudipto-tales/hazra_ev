<?php

class AccountDeletionRequests extends Migration
{
    public function up()
    {
        $this->addColumn('users', 'account_status', "{str:16} NOT NULL DEFAULT 'active'");
        $this->addColumn('users', 'deletion_due_at', '{ts}');
        $this->addColumn('users', 'deleted_at', '{ts}');
        $this->exec("
            CREATE TABLE IF NOT EXISTS account_deletion_requests (
                id {uuid} PRIMARY KEY,
                org_id {uuid} NOT NULL,
                employee_id {uuid} NOT NULL UNIQUE,
                employee_snapshot {text} NOT NULL,
                reason {text} NOT NULL,
                status {str:16} NOT NULL DEFAULT 'pending',
                requested_at {ts} NOT NULL,
                delete_after {ts} NOT NULL,
                deleted_at {ts},
                deleted_by {uuid},
                deletion_source {str:16},
                FOREIGN KEY (employee_id) REFERENCES users(id),
                FOREIGN KEY (org_id) REFERENCES organizations(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_deletion_due ON account_deletion_requests(status, delete_after);
            CREATE INDEX IF NOT EXISTS idx_deletion_org ON account_deletion_requests(org_id, requested_at);
            CREATE TABLE IF NOT EXISTS account_deletion_events (
                id {uuid} PRIMARY KEY,
                request_id {uuid} NOT NULL UNIQUE,
                employee_id {uuid} NOT NULL,
                actor_id {uuid},
                source {str:16} NOT NULL,
                deleted_at {ts} NOT NULL,
                FOREIGN KEY (request_id) REFERENCES account_deletion_requests(id),
                FOREIGN KEY (employee_id) REFERENCES users(id)
            ) {opts};
        ");
    }
}
