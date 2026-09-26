<?php

/**
 * Migration 022 — App download logs
 * Who downloaded which APK (employee code or phone).
 */
class AppDownloadLogs extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS app_download_logs (
                id              {uuid} PRIMARY KEY,
                release_id      {uuid} NOT NULL,
                employee_id     {uuid},
                employee_code   {str:64} NOT NULL DEFAULT '',
                mobile          {str:20} NOT NULL DEFAULT '',
                version_name    {str:32} NOT NULL DEFAULT '',
                ip              {str:64} NOT NULL DEFAULT '',
                user_agent      {str:512} NOT NULL DEFAULT '',
                created_at      {ts} NOT NULL
            ) {opts};

            CREATE INDEX IF NOT EXISTS idx_adl_release ON app_download_logs (release_id);
            CREATE INDEX IF NOT EXISTS idx_adl_code ON app_download_logs (employee_code);
            CREATE INDEX IF NOT EXISTS idx_adl_created ON app_download_logs (created_at);
        ");
    }

    public function down()
    {
        $this->drop(['app_download_logs']);
    }
}