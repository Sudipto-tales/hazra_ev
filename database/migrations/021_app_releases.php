<?php

/**
 * Migration 021 — App Releases
 *
 * Stores metadata for uploaded APK builds (CI or manual).
 * Files live on disk at storage/apk/{version_name}/; this table is the index.
 */
class AppReleases extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS app_releases (
                id                {uuid} PRIMARY KEY,
                platform          {str:16}  NOT NULL DEFAULT 'android',
                version_name      {str:32}  NOT NULL,
                version_code      {int}     NOT NULL,
                file_name         {str:255} NOT NULL,
                file_path         {str:512} NOT NULL,
                file_size         {int}     NOT NULL DEFAULT 0,
                checksum_sha256   {str:64}  NOT NULL DEFAULT '',
                mime              {str:128} NOT NULL DEFAULT 'application/vnd.android.package-archive',
                status            {str:16}  NOT NULL DEFAULT 'draft',
                channel           {str:16}  NOT NULL DEFAULT 'production',
                release_notes     {text},
                min_android_sdk   {int},
                git_tag           {str:64},
                git_sha           {str:64},
                build_source      {str:32}  NOT NULL DEFAULT 'admin_upload',
                published_at      {ts},
                published_by      {uuid},
                created_at        {ts}      NOT NULL,
                updated_at        {ts}      NOT NULL
            ) {opts};

            CREATE UNIQUE INDEX IF NOT EXISTS idx_app_rel_plat_vcode
                ON app_releases (platform, version_code);

            CREATE INDEX IF NOT EXISTS idx_app_rel_status
                ON app_releases (status);
        ");
    }

    public function down()
    {
        $this->drop(['app_releases']);
    }
}
