<?php

class AppReleaseDownloadAccess extends Migration
{
    public function up()
    {
        $this->addColumn('app_download_logs', 'download_started_at', '{ts}');
        $this->exec("
            CREATE TABLE IF NOT EXISTS app_download_tokens (
                token_hash   {str:64} PRIMARY KEY,
                release_id   {uuid} NOT NULL,
                employee_id  {uuid} NOT NULL,
                log_id       {uuid} NOT NULL,
                session_hash {str:64} NOT NULL,
                expires_at   {ts} NOT NULL,
                created_at   {ts} NOT NULL
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_app_download_token_expiry ON app_download_tokens (expires_at);
        ");
    }
}
