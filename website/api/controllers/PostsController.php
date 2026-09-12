<?php
require_once __DIR__ . '/../support/V1Controller.php';

/**
 * Admin controller for managing blog posts.
 * Uses the admin_posts table created by the migration.
 */
final class PostsController extends V1Controller
{
    private const WRITABLE = [
        'title'   => 'title',
        'slug'    => 'slug',
        'content' => 'content',
        'status'  => 'status',
    ];

    public function index(): never
    {
        $rows = db_fetch_all('SELECT * FROM admin_posts ORDER BY created_at DESC');
        Envelope::ok($rows);
    }

    public function store(): never
    {
        $this->requireAdmin();
        $body = ApiRequest::body();
        $now = Wire::now();
        $id = Uuid::v4();
        $columns = ['id', 'created_at', 'updated_at'];
        $values = [$id, $now, $now];
        foreach (self::WRITABLE as $wire => $col) {
            $columns[] = $col;
            $values[] = $body[$wire] ?? null;
        }
        db_execute('INSERT INTO admin_posts (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')', $values);
        Envelope::created(['id' => $id]);
    }

    public function update(): never
    {
        $this->requireAdmin();
        $id = (string) $this->param('id');
        $body = ApiRequest::body();
        $sets = [];
        $params = [];
        foreach (self::WRITABLE as $wire => $col) {
            if (!array_key_exists($wire, $body)) continue;
            $sets[] = "{$col} = ?";
            $params[] = $body[$wire];
        }
        if ($sets) {
            $params[] = $now = Wire::now();
            $params[] = $id;
            db_execute('UPDATE admin_posts SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params);
        }
        Envelope::ok();
    }

    public function delete(): never
    {
        $this->requireAdmin();
        $id = (string) $this->param('id');
        db_execute('DELETE FROM admin_posts WHERE id = ?', [$id]);
        Envelope::ok();
    }
}
?>
