<?php
/**
 * Migration 012: Create admin content tables.
 *
 * NOTE: These tables are also created by migration 014 with proper Dialect
 * tokens. This migration uses raw SQLite SQL and was originally skipped due
 * to a class-naming mismatch. The tables already exist from 014, so this
 * migration is effectively a no-op — it exists only to clear the "skipped"
 * warning and record itself in the ledger.
 *
 * Tables (all IF NOT EXISTS):
 *   - admin_posts      (blogs & news unified, differentiated by `type`)
 *   - admin_gallery_items
 *   - admin_jobs
 *   - admin_job_applications
 */
class WebsiteAdminContentTables extends Migration
{
    public function up()
    {
        // admin_posts — shared table for blogs and news
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS admin_posts (
            id           TEXT    PRIMARY KEY,
            type         TEXT    NOT NULL DEFAULT 'blog',
            title        TEXT    NOT NULL,
            slug         TEXT    NOT NULL DEFAULT '',
            excerpt      TEXT,
            content      TEXT    NOT NULL DEFAULT '',
            cover_image  TEXT,
            author       TEXT,
            published_at DATETIME,
            status       TEXT    NOT NULL DEFAULT 'draft',
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
        );");

        // admin_gallery_items
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS admin_gallery_items (
            id          TEXT    PRIMARY KEY,
            title       TEXT    NOT NULL,
            image_url   TEXT    NOT NULL DEFAULT '',
            image_path  TEXT    NOT NULL DEFAULT '',
            alt         TEXT    NOT NULL DEFAULT '',
            caption     TEXT,
            order_num   INTEGER NOT NULL DEFAULT 0,
            status      TEXT    NOT NULL DEFAULT 'active',
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );");

        // admin_jobs
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS admin_jobs (
            id           TEXT    PRIMARY KEY,
            title        TEXT    NOT NULL,
            type         TEXT    NOT NULL DEFAULT 'full-time',
            location     TEXT,
            description  TEXT    NOT NULL DEFAULT '',
            requirements TEXT,
            positions    INTEGER DEFAULT 1,
            apply_email  TEXT,
            expires_at   DATETIME,
            status       TEXT    NOT NULL DEFAULT 'open',
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
        );");

        // admin_job_applications
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS admin_job_applications (
            id             TEXT    PRIMARY KEY,
            job_id         TEXT    NOT NULL,
            applicant_name TEXT    NOT NULL DEFAULT '',
            name           TEXT    NOT NULL DEFAULT '',
            email          TEXT    NOT NULL DEFAULT '',
            phone          TEXT,
            resume_url     TEXT,
            resume_path    TEXT,
            message        TEXT,
            cover_letter   TEXT,
            status         TEXT    NOT NULL DEFAULT 'new',
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES admin_jobs(id) ON DELETE CASCADE
        );");
    }

    public function down()
    {
        $this->drop(['admin_job_applications', 'admin_jobs', 'admin_gallery_items', 'admin_posts']);
    }
}
