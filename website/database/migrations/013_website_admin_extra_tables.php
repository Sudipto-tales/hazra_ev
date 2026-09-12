<?php
/**
 * Migration: Additional admin content tables for blogs and news.
 *
 * Tables:
 *   - admin_blogs
 *   - admin_news
 */

class Migration_013_website_admin_extra_tables {
    public function up(PDO $db) {
        // admin_blogs
        $db->exec("CREATE TABLE IF NOT EXISTS admin_blogs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            content TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );");

        // admin_news
        $db->exec("CREATE TABLE IF NOT EXISTS admin_news (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            content TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );");
    }

    public function down(PDO $db) {
        $db->exec("DROP TABLE IF EXISTS admin_news;");
        $db->exec("DROP TABLE IF EXISTS admin_blogs;");
    }
}
?>
