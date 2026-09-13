<?php
/**
 * Migration 013: Additional admin content tables for blogs and news.
 *
 * NOTE: These standalone admin_blogs and admin_news tables were an early
 * approach before the unified admin_posts table (with a `type` column) was
 * adopted in migrations 012 and 014. They are kept for backward compatibility
 * but are not used by the current admin panel — the panel reads admin_posts
 * filtered by type.
 *
 * Tables (all IF NOT EXISTS):
 *   - admin_blogs
 *   - admin_news
 */
class WebsiteAdminExtraTables extends Migration
{
    public function up()
    {
        // admin_blogs — standalone blog table (legacy, superseded by admin_posts.type='blog')
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS admin_blogs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL DEFAULT '',
            content TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );");

        // admin_news — standalone news table (legacy, superseded by admin_posts.type='news')
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS admin_news (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL DEFAULT '',
            content TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );");
    }

    public function down()
    {
        $this->drop(['admin_news', 'admin_blogs']);
    }
}
