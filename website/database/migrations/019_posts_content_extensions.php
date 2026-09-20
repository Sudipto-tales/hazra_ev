<?php

/**
 * Migration 019: Posts Content Extensions & Unification.
 *
 * Extends the canonical `posts` table with fields for rich categorization,
 * tagging, location metadata, read estimates, featured flags, and SEO overrides.
 * Also synchronizes any orphaned rows from `admin_posts` into `posts` and
 * establishes performance and lookup indexes.
 */
class PostsContentExtensions extends Migration
{
    public function up()
    {
        // 1. Extend posts table
        $this->addColumn('posts', 'category', '{str:64} DEFAULT \'ev-trends\'');
        $this->addColumn('posts', 'tags', '{text}');
        $this->addColumn('posts', 'location', '{str:128}');
        $this->addColumn('posts', 'read_minutes', '{int} DEFAULT 4');
        $this->addColumn('posts', 'is_featured', '{int} DEFAULT 0');
        $this->addColumn('posts', 'meta_title', '{str}');
        $this->addColumn('posts', 'meta_description', '{text}');

        // Also extend admin_posts to avoid schema desync if referenced
        $this->addColumn('admin_posts', 'category', '{str:64} DEFAULT \'ev-trends\'');
        $this->addColumn('admin_posts', 'tags', '{text}');
        $this->addColumn('admin_posts', 'location', '{str:128}');
        $this->addColumn('admin_posts', 'read_minutes', '{int} DEFAULT 4');
        $this->addColumn('admin_posts', 'is_featured', '{int} DEFAULT 0');
        $this->addColumn('admin_posts', 'meta_title', '{str}');
        $this->addColumn('admin_posts', 'meta_description', '{text}');

        // 2. Composite & lookup indexes
        $this->exec("
            CREATE INDEX IF NOT EXISTS idx_posts_type_status_pub ON posts (type, status, published_at);
            CREATE INDEX IF NOT EXISTS idx_posts_slug ON posts (slug);
            CREATE INDEX IF NOT EXISTS idx_posts_category ON posts (category);
            CREATE INDEX IF NOT EXISTS idx_posts_is_featured ON posts (is_featured);
        ");

        // 3. Unify tables: copy any rows from admin_posts into canonical posts
        try {
            $adminRows = $this->pdo->query("SELECT * FROM admin_posts")->fetchAll(PDO::FETCH_ASSOC);
            if ($adminRows) {
                foreach ($adminRows as $row) {
                    $existsStmt = $this->pdo->prepare("SELECT COUNT(*) FROM posts WHERE id = ?");
                    $existsStmt->execute([$row['id']]);
                    if ((int)$existsStmt->fetchColumn() === 0) {
                        $insertStmt = $this->pdo->prepare("
                            INSERT INTO posts (
                                id, type, title, slug, excerpt, content, cover_image,
                                author, published_at, status, category, tags, location,
                                read_minutes, is_featured, meta_title, meta_description,
                                created_at, updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $insertStmt->execute([
                            $row['id'],
                            $row['type'] ?? 'blog',
                            $row['title'] ?? '',
                            $row['slug'] ?? '',
                            $row['excerpt'] ?? null,
                            $row['content'] ?? '',
                            $row['cover_image'] ?? null,
                            $row['author'] ?? 'Hazra EV Team',
                            $row['published_at'] ?? $row['created_at'] ?? date('Y-m-d H:i:s'),
                            $row['status'] ?? 'draft',
                            $row['category'] ?? 'ev-trends',
                            $row['tags'] ?? null,
                            $row['location'] ?? null,
                            $row['read_minutes'] ?? 4,
                            $row['is_featured'] ?? 0,
                            $row['meta_title'] ?? null,
                            $row['meta_description'] ?? null,
                            $row['created_at'] ?? date('Y-m-d H:i:s'),
                            $row['updated_at'] ?? date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            // Non-critical fallback if admin_posts has unexpected structure
        }
    }

    public function down()
    {
        // No-op rollback to protect data in production
    }
}
