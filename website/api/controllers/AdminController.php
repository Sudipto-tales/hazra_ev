<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __BASEDIR__ . '/core/messaging/Registry.php';

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
        $warrantyCount = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM warranty_registrations WHERE status = 'pending'")['c'] ?? 0);

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
                'warranty_pending' => $warrantyCount,
            ],
            'recent' => $this->recentActivity(),
        ]);
    }

    /**
     * The newest handful of inbound items across the three lead-bearing tables,
     * merged and sorted newest-first. The dashboard's "latest activity" panel
     * was hardcoded to an empty array, so it rendered a dead strip; this fills
     * it from the same tables the stat tiles count.
     *
     * Each row is a flat {kind, title, subtitle, at, href} the panel renders
     * without joining anything. Capped at ten — this is a glance, not a list
     * screen (each table has its own).
     */
    private function recentActivity(int $limit = 10): array
    {
        $items = [];

        foreach (db_fetch_all(
            "SELECT type, name, phone, created_at FROM website_leads ORDER BY created_at DESC LIMIT ?",
            [$limit],
        ) ?? [] as $row) {
            $type = (string) ($row['type'] ?? 'contact');
            $href = str_starts_with($type, 'test') ? 'test-drive'
                  : (str_contains($type, 'dealer') ? 'dealership' : 'contact');
            $label = str_replace(['_', 'enquiry'], [' ', ''], $type);
            $items[] = [
                'kind'     => 'lead',
                'title'    => trim((string) ($row['name'] ?? '')) ?: 'New lead',
                'subtitle' => trim(ucfirst(trim($label)) . ' · ' . (string) ($row['phone'] ?? '')),
                'at'       => $row['created_at'],
                'href'     => $href,
            ];
        }

        foreach (db_fetch_all(
            "SELECT applicant_name, name, created_at FROM job_applications ORDER BY created_at DESC LIMIT ?",
            [$limit],
        ) ?? [] as $row) {
            $items[] = [
                'kind'     => 'application',
                'title'    => trim((string) ($row['applicant_name'] ?: $row['name'] ?? '')) ?: 'Applicant',
                'subtitle' => 'Career application',
                'at'       => $row['created_at'],
                'href'     => 'career-apps',
            ];
        }

        foreach (db_fetch_all(
            "SELECT customer_name, type, reference_no, created_at FROM warranty_registrations ORDER BY created_at DESC LIMIT ?",
            [$limit],
        ) ?? [] as $row) {
            $items[] = [
                'kind'     => 'warranty',
                'title'    => trim((string) ($row['customer_name'] ?? '')) ?: 'Warranty',
                'subtitle' => trim(ucfirst((string) ($row['type'] ?? '')) . ' warranty · ' . (string) ($row['reference_no'] ?? '')),
                'at'       => $row['created_at'],
                'href'     => 'warranty',
            ];
        }

        // Newest first across all three. created_at is ISO-8601 UTC, so a string
        // sort is a chronological sort.
        usort($items, static fn(array $a, array $b) => strcmp((string) $b['at'], (string) $a['at']));

        return array_slice($items, 0, $limit);
    }

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
            'warranty' => db_fetch_all(
                "SELECT id, type, status, reference_no, customer_name, mobile, chassis_no, purchase_date, created_at FROM warranty_registrations ORDER BY created_at DESC LIMIT 100"
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
            if ($group === 'whatsapp_locks') continue;
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

        if (!isset($settings['general'])) {
            $settings['general'] = [];
        }
        if (!isset($settings['general']['show_footer_bg'])) {
            $settings['general']['show_footer_bg'] = '1';
        }

        Envelope::ok($settings);
    }

    public function patchSettings(array $args): never
    {
        $this->requireAdminSession();

        $group = $args['group'] ?? 'general';
        $body = ApiRequest::body();
        if ($group === 'whatsapp_locks') Envelope::invalid('This settings group is server-managed');

        if (is_array($body)) {
            foreach ($body as $key => $val) {
                if (str_starts_with($key, 'wa:') || str_starts_with($key, 'whatsapp_')) Envelope::invalid('This setting is server-managed');
                if (in_array(strtolower((string) $key), MessagingCredentialStore::reservedSettingKeys(), true)) Envelope::invalid('This credential is managed in Admin → Settings → Messaging, where it is stored encrypted.');
                if (str_starts_with($key, 'wa_') && !in_array($key, ['wa_enabled', 'wa_auto_leads', 'wa_lead_mode', 'wa_language', 'wa_template_otp', 'wa_template_lead_received', 'wa_template_lead_approved', 'wa_template_lead_rejected', 'wa_template_warranty_received'], true)) Envelope::invalid('Unknown WhatsApp setting');
                if (str_starts_with($key, 'wa_') && $group !== 'general') Envelope::invalid('WhatsApp settings belong to general');
                db_execute(
                    "INSERT INTO website_settings (id, group_name, setting_key, setting_value, updated_at)
                     VALUES (?, ?, ?, ?, ?)
                     ON CONFLICT(group_name, setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at",
                    [Uuid::v4(), $group, $key, (string) $val, gmdate('Y-m-d H:i:s')]
                );
            }
        }

        Envelope::ok($body ?? []);
    }

    public function testMail(): never
    {
        $this->requireAdminSession();

        $body = ApiRequest::body();
        $target = trim((string) ($body['email'] ?? ''));

        if (!$target || !filter_var($target, FILTER_VALIDATE_EMAIL)) {
            Envelope::invalid('A valid recipient email address is required', 'email');
        }

        require_once __BASEDIR__ . '/core/Mailer.php';

        try {
            Mailer::send($target, "Test Email from Hazra EV Admin", "This is a test email sent from your Hazra EV Admin Panel. Your SMTP settings are working correctly!");
            Envelope::ok(['message' => "Test email successfully sent to {$target}"]);
        } catch (\Throwable $e) {
            Envelope::fail('MAIL_ERROR', 'Failed to send test email: ' . $e->getMessage(), 400);
        }
    }
}
