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
        $this->exec("
            CREATE TABLE IF NOT EXISTS admin_blogs (
                id         {autoid},
                title      {str} NOT NULL,
                slug       {str} NOT NULL DEFAULT '',
                content    {text} NOT NULL DEFAULT '',
                status     {str:32} NOT NULL DEFAULT 'draft',
                created_at {ts} NOT NULL,
                updated_at {ts} NOT NULL
            ) {opts};

            CREATE TABLE IF NOT EXISTS admin_news (
                id         {autoid},
                title      {str} NOT NULL,
                slug       {str} NOT NULL DEFAULT '',
                content    {text} NOT NULL DEFAULT '',
                status     {str:32} NOT NULL DEFAULT 'draft',
                created_at {ts} NOT NULL,
                updated_at {ts} NOT NULL
            ) {opts};
        ");
    }

    public function down()
    {
        $this->drop(['admin_news', 'admin_blogs']);
    }
}
