<?php

require_once __DIR__ . '/../support/V1Controller.php';

final class AdminController extends V1Controller
{
    private function requireAdminSession(): void
    {
        Csrf::ensureSession();

        if (empty($_SESSION['admin_logged_in']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
            Envelope::unauthorized('Admin sign-in required');
        }
    }

    public function summary(): never
    {
        $this->requireAdminSession();

        $productsCount = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM products WHERE active = 1")['c'] ?? 0);
        $postsCount    = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM posts WHERE type = 'blog'")['c'] ?? 0);
        $newsCount     = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM posts WHERE type = 'news'")['c'] ?? 0);
        $galleryCount  = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM gallery_items")['c'] ?? 0);
        $openJobs      = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM jobs WHERE status = 'open'")['c'] ?? 0);

        $testDriveLeads  = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM website_leads WHERE type = 'test_drive'")['c'] ?? 0);
        $dealershipLeads = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM website_leads WHERE type = 'dealership'")['c'] ?? 0);
        $contactLeads    = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM website_leads WHERE type = 'contact'")['c'] ?? 0);

        $careerApps    = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM job_applications")['c'] ?? 0);

        Envelope::ok([
            'stats' => [
                'products' => $productsCount,
                'blogs' => $postsCount,
                'news' => $newsCount,
                'gallery' => $galleryCount,
                'open_jobs' => $openJobs,
                'test_drive_leads' => $testDriveLeads,
                'dealership_leads' => $dealershipLeads,
                'contact_leads' => $contactLeads,
                'career_applications' => $careerApps,
            ],
            'recent' => [],
        ]);
    }

    // public function bootstrap(): never
    // {
    //     $this->requireAdminSession();

    //     Envelope::ok([
    //         'products' => db_fetch_all("SELECT * FROM products WHERE active = 1 ORDER BY created_at DESC") ?? [],
    //         'posts' => db_fetch_all("SELECT * FROM posts ORDER BY created_at DESC") ?? [],
    //         'jobs' => db_fetch_all("SELECT * FROM jobs ORDER BY created_at DESC") ?? [],
    //     ]);
    // }
    public function bootstrap(): never
{
    $this->requireAdminSession();

    Envelope::ok([
        'products' => db_fetch_all(
            "SELECT * FROM products WHERE active = 1 ORDER BY updated_at DESC"
        ) ?? [],
        'posts' => db_fetch_all(
            "SELECT * FROM posts ORDER BY created_at DESC"
        ) ?? [],
        'jobs' => db_fetch_all(
            "SELECT * FROM jobs ORDER BY created_at DESC"
        ) ?? [],
    ]);
}

    public function getSettings(): never
    {
        $this->requireAdminSession();

        $rows = db_fetch_all("SELECT * FROM website_settings") ?? [];
        $settings = [];

        foreach ($rows as $row) {
            $group = $row['group_name'] ?? 'general';
            $key   = $row['setting_key'] ?? $row['key'] ?? '';
            $val   = $row['setting_value'] ?? $row['value'] ?? '';

            if (!isset($settings[$group])) {
                $settings[$group] = [];
            }
            $settings[$group][$key] = $val;
        }

        if (empty($settings)) {
            $settings = [
                'general' => [
                    'site_name' => 'Hazra EV',
                    'tagline' => 'Powering the Future of Mobility',
                ],
                'contact' => [
                    'phone' => '+91 98765 43210',
                    'email' => 'info@hazraev.com',
                    'address' => 'Kolkata, West Bengal, India',
                    'whatsapp' => '+91 98765 43210',
                ],
                'social' => [
                    'facebook' => '',
                    'instagram' => '',
                    'youtube' => '',
                    'linkedin' => '',
                ],
            ];
        }

        Envelope::ok($settings);
    }

    public function patchSettings(array $args): never
    {
        $this->requireAdminSession();

        $group = $args['group'] ?? 'general';
        $body = ApiRequest::body();

        if (is_array($body)) {
            foreach ($body as $key => $val) {
                db_execute(
                    "INSERT INTO website_settings (group_name, setting_key, setting_value, updated_at)
                     VALUES (?, ?, ?, ?)
                     ON CONFLICT(group_name, setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at",
                    [$group, $key, (string) $val, gmdate('Y-m-d H:i:s')]
                );
            }
        }

        Envelope::ok($body ?? []);
    }
}
