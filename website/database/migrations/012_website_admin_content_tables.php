<?php
/**
 * Migration 012: Create admin content tables.
 *
 * Tables:
 *   - admin_posts      (blogs & news unified, differentiated by `type`)
 *   - admin_gallery_items
 *   - admin_jobs
 *   - admin_job_applications
 */
class Migration_012_website_admin_content_tables
{
    public function up(PDO $db): void
    {
        // admin_posts — shared table for blogs and news
        $db->exec("CREATE TABLE IF NOT EXISTS admin_posts (
            id           TEXT    PRIMARY KEY,
            type         TEXT    NOT NULL DEFAULT 'blog',
            title        TEXT    NOT NULL,
            slug         TEXT    NOT NULL UNIQUE,
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
        $db->exec("CREATE TABLE IF NOT EXISTS admin_gallery_items (
            id          TEXT    PRIMARY KEY,
            title       TEXT    NOT NULL,
            image_path  TEXT    NOT NULL,
            caption     TEXT,
            status      TEXT    NOT NULL DEFAULT 'active',
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );");

        // admin_jobs
        $db->exec("CREATE TABLE IF NOT EXISTS admin_jobs (
            id           TEXT    PRIMARY KEY,
            title        TEXT    NOT NULL,
            type         TEXT    NOT NULL DEFAULT 'full-time',
            location     TEXT,
            description  TEXT    NOT NULL DEFAULT '',
            requirements TEXT,
            positions    INTEGER DEFAULT 1,
            apply_email  TEXT,
            posted_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at   DATETIME,
            status       TEXT    NOT NULL DEFAULT 'open',
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
        );");

        // admin_job_applications
        $db->exec("CREATE TABLE IF NOT EXISTS admin_job_applications (
            id             TEXT    PRIMARY KEY,
            job_id         TEXT    NOT NULL,
            applicant_name TEXT    NOT NULL,
            email          TEXT    NOT NULL,
            phone          TEXT,
            resume_path    TEXT,
            cover_letter   TEXT,
            applied_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            status         TEXT    NOT NULL DEFAULT 'new',
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES admin_jobs(id) ON DELETE CASCADE
        );");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS admin_job_applications;');
        $db->exec('DROP TABLE IF EXISTS admin_jobs;');
        $db->exec('DROP TABLE IF EXISTS admin_gallery_items;');
        $db->exec('DROP TABLE IF EXISTS admin_posts;');
    }
}
