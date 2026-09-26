<?php

/**
 * Migration 022 — App Download Logs
 *
 * Tracks downloads of the employee mobile app APK via the verified download gate.
 */
class AppDownloadLogs extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS app_download_logs (
                id             {uuid} PRIMARY KEY,
                release_id     {uuid} NOT NULL,
                employee_code  {str:64} NOT NULL,
                mobile         {str:32} NOT NULL,
                ip             {str:45} NOT NULL DEFAULT '',
                user_agent     {str:512} NOT NULL DEFAULT '',
                created_at     {ts} NOT NULL,
                FOREIGN KEY (release_id) REFERENCES app_releases(id) ON DELETE CASCADE
            ) {opts};

            CREATE INDEX IF NOT EXISTS idx_app_dl_rel ON app_download_logs (release_id);
            CREATE INDEX IF NOT EXISTS idx_app_dl_emp ON app_download_logs (employee_code);
            CREATE INDEX IF NOT EXISTS idx_app_dl_created ON app_download_logs (created_at);
        ");
    }

    public function down()
    {
        $this->drop(['app_download_logs']);
    }
}
