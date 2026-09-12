<?php
require_once __DIR__ . '/../support/V1Controller.php';

/**
 * ContentController — admin CRUD for Posts (Blogs & News),
 * Gallery items, Jobs, and Job Applications.
 *
 * Auto-expiry of jobs is a read-side side-effect (no cron required):
 * listJobs() marks expired rows 'closed' before querying.
 *
 * Public reads (listPosts, listGallery, listJobs, storeApplication)
 * are intentionally unauthenticated so the public website can fetch them.
 * All writes require requireAdmin().
 */
final class ContentController extends V1Controller
{
    // =========================================================
    // Posts  (Blogs & News — unified table, split by `type`)
    // =========================================================

    private const POST_WRITABLE = [
        'type'        => 'type',
        'title'       => 'title',
        'slug'        => 'slug',
        'excerpt'     => 'excerpt',
        'content'     => 'content',
        'cover_image' => 'cover_image',
        'author'      => 'author',
        'published_at'=> 'published_at',
        'status'      => 'status',
    ];

    public function listPosts(): never
    {
        $type   = (string) $this->query('type', '');
        $status = (string) $this->query('status', '');
        $where  = [];
        $params = [];

        if ($type)   { $where[] = 'type = ?';   $params[] = $type;   }
        if ($status) { $where[] = 'status = ?'; $params[] = $status; }

        $clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $rows   = db_fetch_all("SELECT * FROM posts {$clause} ORDER BY created_at DESC", $params);
        if (!$rows) {
            $rows = db_fetch_all("SELECT * FROM admin_posts {$clause} ORDER BY created_at DESC", $params);
        }
        Envelope::ok($rows);
    }

    public function showPost(): never
    {
        $id  = (string) $this->param('id');
        $row = db_fetch_one('SELECT * FROM posts WHERE id = ?', [$id]);
        if (!$row) {
            $row = db_fetch_one('SELECT * FROM admin_posts WHERE id = ?', [$id]);
        }
        if (!$row) Envelope::notFound('POST_NOT_FOUND', 'No such post');
        Envelope::ok($row);
    }

    public function storePost(): never
    {
        $this->requireAdmin();
        $body = ApiRequest::body();
        $now  = Wire::now();
        $id   = Uuid::v4();

        $columns = ['id', 'created_at', 'updated_at'];
        $values  = [$id,  $now,         $now];

        foreach (self::POST_WRITABLE as $wire => $col) {
            $columns[] = $col;
            $values[]  = $body[$wire] ?? null;
        }

        $query = 'INSERT INTO %s (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')';

        db_execute(sprintf($query, 'posts'), $values);
        try { db_execute(sprintf($query, 'admin_posts'), $values); } catch (Throwable) {}

        Envelope::created(['id' => $id]);
    }

    public function updatePost(): never
    {
        $this->requireAdmin();
        $id   = (string) $this->param('id');
        $body = ApiRequest::body();
        $sets   = [];
        $params = [];

        foreach (self::POST_WRITABLE as $wire => $col) {
            if (!array_key_exists($wire, $body)) continue;
            $sets[]   = "{$col} = ?";
            $params[] = $body[$wire];
        }

        if ($sets) {
            $params[] = Wire::now();
            $params[] = $id;
            db_execute('UPDATE posts SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params);
            try { db_execute('UPDATE admin_posts SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params); } catch (Throwable) {}
        }

        Envelope::ok();
    }

    public function deletePost(): never
    {
        $this->requireAdmin();
        $id = (string) $this->param('id');
        db_execute('DELETE FROM posts WHERE id = ?', [$id]);
        try { db_execute('DELETE FROM admin_posts WHERE id = ?', [$id]); } catch (Throwable) {}
        Envelope::ok();
    }

    // =========================================================
    // Gallery
    // =========================================================

    private const GALLERY_WRITABLE = [
        'title'      => 'title',
        'image_url'  => 'image_url',
        'image_path' => 'image_path',
        'alt'        => 'alt',
        'caption'    => 'caption',
        'order_num'  => 'order_num',
        'status'     => 'status',
    ];

    public function listGallery(): never
    {
        $rows = db_fetch_all('SELECT * FROM gallery_items ORDER BY order_num ASC, created_at DESC');
        if (!$rows) {
            $rows = db_fetch_all('SELECT * FROM admin_gallery_items ORDER BY created_at DESC');
        }
        Envelope::ok($rows);
    }

    public function showGallery(): never
    {
        $id  = (string) $this->param('id');
        $row = db_fetch_one('SELECT * FROM gallery_items WHERE id = ?', [$id]);
        if (!$row) {
            $row = db_fetch_one('SELECT * FROM admin_gallery_items WHERE id = ?', [$id]);
        }
        if (!$row) Envelope::notFound('GALLERY_NOT_FOUND', 'No such gallery item');
        Envelope::ok($row);
    }

    public function storeGallery(): never
    {
        $this->requireAdmin();
        $body    = ApiRequest::body();
        $now     = Wire::now();
        $id      = Uuid::v4();
        $columns = ['id', 'created_at', 'updated_at'];
        $values  = [$id,  $now,         $now];

        foreach (self::GALLERY_WRITABLE as $wire => $col) {
            $columns[] = $col;
            $values[]  = $body[$wire] ?? ($col === 'image_url' ? ($body['image_path'] ?? '') : ($col === 'image_path' ? ($body['image_url'] ?? '') : null));
        }

        $query = 'INSERT INTO %s (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')';

        db_execute(sprintf($query, 'gallery_items'), $values);
        try { db_execute(sprintf($query, 'admin_gallery_items'), $values); } catch (Throwable) {}

        Envelope::created(['id' => $id]);
    }

    public function updateGallery(): never
    {
        $this->requireAdmin();
        $id     = (string) $this->param('id');
        $body   = ApiRequest::body();
        $sets   = [];
        $params = [];

        foreach (self::GALLERY_WRITABLE as $wire => $col) {
            if (!array_key_exists($wire, $body)) continue;
            $sets[]   = "{$col} = ?";
            $params[] = $body[$wire];
        }

        if ($sets) {
            $params[] = Wire::now();
            $params[] = $id;
            db_execute('UPDATE gallery_items SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params);
            try { db_execute('UPDATE admin_gallery_items SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params); } catch (Throwable) {}
        }

        Envelope::ok();
    }

    public function deleteGallery(): never
    {
        $this->requireAdmin();
        $id = (string) $this->param('id');
        db_execute('DELETE FROM gallery_items WHERE id = ?', [$id]);
        try { db_execute('DELETE FROM admin_gallery_items WHERE id = ?', [$id]); } catch (Throwable) {}
        Envelope::ok();
    }

    // =========================================================
    // Jobs
    // =========================================================

    private const JOB_WRITABLE = [
        'title'        => 'title',
        'type'         => 'type',
        'location'     => 'location',
        'description'  => 'description',
        'requirements' => 'requirements',
        'positions'    => 'positions',
        'apply_email'  => 'apply_email',
        'expires_at'   => 'expires_at',
        'status'       => 'status',
    ];

    public function listJobs(): never
    {
        $now = Wire::now();
        // Auto-close rule: if expires_at < now set status = 'closed'
        db_execute(
            "UPDATE jobs SET status = 'closed' WHERE expires_at IS NOT NULL AND expires_at <> '' AND expires_at < ? AND status = 'open'",
            [$now]
        );
        try {
            db_execute(
                "UPDATE admin_jobs SET status = 'closed' WHERE expires_at IS NOT NULL AND expires_at <> '' AND expires_at < ? AND status = 'open'",
                [$now]
            );
        } catch (Throwable) {}

        $statusFilter = (string) $this->query('status', 'open');
        if ($statusFilter === 'all' || $statusFilter === '') {
            $rows = db_fetch_all('SELECT * FROM jobs ORDER BY created_at DESC');
            if (!$rows) $rows = db_fetch_all('SELECT * FROM admin_jobs ORDER BY created_at DESC');
        } else {
            $rows = db_fetch_all('SELECT * FROM jobs WHERE status = ? ORDER BY created_at DESC', [$statusFilter]);
            if (!$rows) $rows = db_fetch_all('SELECT * FROM admin_jobs WHERE status = ? ORDER BY created_at DESC', [$statusFilter]);
        }

        Envelope::ok($rows);
    }

    public function showJob(): never
    {
        $id  = (string) $this->param('id');
        $row = db_fetch_one('SELECT * FROM jobs WHERE id = ?', [$id]);
        if (!$row) $row = db_fetch_one('SELECT * FROM admin_jobs WHERE id = ?', [$id]);
        if (!$row) Envelope::notFound('JOB_NOT_FOUND', 'No such job');
        Envelope::ok($row);
    }

    public function storeJob(): never
    {
        $this->requireAdmin();
        $body    = ApiRequest::body();
        $now     = Wire::now();
        $id      = Uuid::v4();
        $columns = ['id', 'created_at', 'updated_at'];
        $values  = [$id,  $now,         $now];

        foreach (self::JOB_WRITABLE as $wire => $col) {
            $columns[] = $col;
            $values[]  = $wire === 'positions' ? (int) ($body[$wire] ?? 1) : ($body[$wire] ?? null);
        }

        $query = 'INSERT INTO %s (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')';

        db_execute(sprintf($query, 'jobs'), $values);
        try { db_execute(sprintf($query, 'admin_jobs'), $values); } catch (Throwable) {}

        Envelope::created(['id' => $id]);
    }

    public function updateJob(): never
    {
        $this->requireAdmin();
        $id     = (string) $this->param('id');
        $body   = ApiRequest::body();
        $sets   = [];
        $params = [];

        foreach (self::JOB_WRITABLE as $wire => $col) {
            if (!array_key_exists($wire, $body)) continue;
            $sets[]   = "{$col} = ?";
            $params[] = $wire === 'positions' ? (int) $body[$wire] : $body[$wire];
        }

        if ($sets) {
            $params[] = Wire::now();
            $params[] = $id;
            db_execute('UPDATE jobs SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params);
            try { db_execute('UPDATE admin_jobs SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params); } catch (Throwable) {}
        }

        Envelope::ok();
    }

    public function deleteJob(): never
    {
        $this->requireAdmin();
        $id = (string) $this->param('id');
        db_execute('DELETE FROM jobs WHERE id = ?', [$id]);
        try { db_execute('DELETE FROM admin_jobs WHERE id = ?', [$id]); } catch (Throwable) {}
        Envelope::ok();
    }

    // =========================================================
    // Job Applications
    // =========================================================

    private const APP_WRITABLE = [
        'job_id'         => 'job_id',
        'applicant_name' => 'applicant_name',
        'name'           => 'name',
        'email'          => 'email',
        'phone'          => 'phone',
        'resume_url'     => 'resume_url',
        'resume_path'    => 'resume_path',
        'message'        => 'message',
        'cover_letter'   => 'cover_letter',
        'status'         => 'status',
    ];

    public function listApplications(): never
    {
        $this->requireAdmin();
        $jobId  = (string) $this->query('job_id', '');
        $status = (string) $this->query('status', '');
        $where  = [];
        $params = [];

        if ($jobId)  { $where[] = 'job_id = ?';  $params[] = $jobId;  }
        if ($status) { $where[] = 'status = ?'; $params[] = $status; }

        $clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $rows   = db_fetch_all("SELECT * FROM job_applications {$clause} ORDER BY created_at DESC", $params);
        if (!$rows) {
            $rows = db_fetch_all("SELECT * FROM admin_job_applications {$clause} ORDER BY created_at DESC", $params);
        }
        Envelope::ok($rows);
    }

    public function storeApplication(): never
    {
        $body    = ApiRequest::body();
        $now     = Wire::now();
        $id      = Uuid::v4();
        $columns = ['id', 'created_at', 'updated_at'];
        $values  = [$id,  $now,         $now];

        $name = trim((string)($body['applicant_name'] ?? $body['name'] ?? ''));
        $email = trim((string)($body['email'] ?? ''));
        $phone = trim((string)($body['phone'] ?? ''));

        if ($name === '' || $email === '') {
            Envelope::invalid('Name and Email are required');
        }

        foreach (self::APP_WRITABLE as $wire => $col) {
            if ($wire === 'status') continue;
            $columns[] = $col;
            $values[]  = $body[$wire] ?? ($col === 'applicant_name' || $col === 'name' ? $name : null);
        }

        $query = 'INSERT INTO %s (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')';

        db_execute(sprintf($query, 'job_applications'), $values);
        try { db_execute(sprintf($query, 'admin_job_applications'), $values); } catch (Throwable) {}

        Envelope::created(['id' => $id, 'message' => 'Your application has been submitted successfully!']);
    }

    public function updateApplication(): never
    {
        $this->requireAdmin();
        $id     = (string) $this->param('id');
        $body   = ApiRequest::body();
        $sets   = [];
        $params = [];

        foreach (self::APP_WRITABLE as $wire => $col) {
            if (!array_key_exists($wire, $body)) continue;
            $sets[]   = "{$col} = ?";
            $params[] = $body[$wire];
        }

        if ($sets) {
            $params[] = Wire::now();
            $params[] = $id;
            db_execute('UPDATE job_applications SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params);
            try { db_execute('UPDATE admin_job_applications SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params); } catch (Throwable) {}
        }

        Envelope::ok();
    }

    public function deleteApplication(): never
    {
        $this->requireAdmin();
        $id = (string) $this->param('id');
        db_execute('DELETE FROM job_applications WHERE id = ?', [$id]);
        try { db_execute('DELETE FROM admin_job_applications WHERE id = ?', [$id]); } catch (Throwable) {}
        Envelope::ok();
    }
}
