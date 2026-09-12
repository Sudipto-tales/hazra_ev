<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __BASEDIR__ . '/core/Mailer.php';

final class WebsiteController extends V1Controller
{
    /** Public GET /api/v1/website/products */
    public function products(): never
    {
        $where = ['p.active = 1'];
        $params = [];

        if ($category = Wire::enumIn((string) $this->query('category', ''), ['scooty', 'bike', 'bicycle', 'others'])) {
            $where[] = 'p.category = ?';
            $params[] = $category;
        }

        $clause = implode(' AND ', $where);

        $rows = db_fetch_all(
            "SELECT p.* FROM products p WHERE {$clause} ORDER BY p.updated_at DESC",
            $params
        );

        $colors = [];
        $images = [];
        if ($rows) {
            $ids = array_column($rows, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $colors = db_fetch_all(
                "SELECT * FROM product_colors WHERE product_id IN ({$placeholders}) ORDER BY position, name",
                $ids
            );

            if ($colors) {
                $colorIds = array_column($colors, 'id');
                $cph = implode(',', array_fill(0, count($colorIds), '?'));

                foreach (db_fetch_all(
                    "SELECT * FROM product_color_images WHERE color_id IN ({$cph}) ORDER BY position",
                    $colorIds
                ) as $image) {
                    $images[$image['color_id']][] = $image['url'];
                }
            }
        }

        $byProduct = [];
        foreach ($colors as $color) {
            $byProduct[$color['product_id']][] = Present::productColor($color, $images[$color['id']] ?? []);
        }

        $result = array_map(
            static fn(array $p) => Present::product($p, $byProduct[$p['id']] ?? []),
            $rows
        );

        Envelope::ok($result);
    }

    /** GET /api/v1/website/settings (Public get) */
    public function settings(): never
    {
        $rows = db_fetch_all("SELECT setting_key, setting_value FROM website_settings");
        $settings = [];
        foreach ($rows as $row) {
            // Mask password if non-admin
            if (!Ctx::isAdmin() && str_contains($row['setting_key'], 'password')) {
                continue;
            }
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        // Provide defaults if empty
        $defaults = [
            'hero_title' => 'Follow Elegant',
            'hero_subtitle' => 'Built for everyday Indian riding.',
            'phone' => '+91 98000 00000',
            'email' => 'contact@hazraev.com',
            'address' => 'Bardhaman, West Bengal, India',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => '587',
            'smtp_from_email' => 'contact@hazraev.com',
            'smtp_from_name' => 'Hazra EV',
            'recipient_emails' => 'sales@hazraev.com',
        ];

        foreach ($defaults as $k => $v) {
            if (!isset($settings[$k])) {
                $settings[$k] = $v;
            }
        }

        Envelope::ok($settings);
    }

    /** POST /api/v1/website/settings (Admin only) */
    public function updateSettings(): never
    {
        $this->requireAdmin();
        $body = ApiRequest::body();

        if (!is_array($body)) {
            Envelope::invalid('Settings payload must be a JSON object');
        }

        $now = Wire::now();

        foreach ($body as $key => $value) {
            $keyStr = (string) $key;
            $valStr = is_array($value) ? json_encode($value) : (string) $value;

            db_execute(
                "INSERT INTO website_settings (setting_key, setting_value, updated_at)
                 VALUES (?, ?, ?)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = EXCLUDED.updated_at",
                [$keyStr, $valStr, $now]
            );
        }

        Envelope::ok(['message' => 'Settings updated successfully']);
    }

    /** POST /api/v1/website/leads (Public form submission) */
    public function storeLead(): never
    {
        $body = ApiRequest::body();

        $type  = Wire::enumIn((string) ($body['type'] ?? 'contact'), ['contact', 'test_ride', 'dealer']) ?? 'contact';
        $name  = trim((string) ($body['name'] ?? $body['owner'] ?? $body['fullName'] ?? ''));
        $phone = trim((string) ($body['phone'] ?? $body['mobile'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));

        if ($name === '' && $phone === '' && $email === '') {
            Envelope::invalid('Name, phone or email is required');
        }

        $id = Uuid::v4();
        $now = Wire::now();
        $detailsJson = json_encode($body);

        db_execute(
            "INSERT INTO website_leads (id, type, name, phone, email, details_json, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'new', ?, ?)",
            [$id, $type, $name, $phone, $email, $detailsJson, $now, $now]
        );

        // Try to dispatch notification email to site admins if SMTP configured
        try {
            $recipientsStr = db_fetch_one("SELECT setting_value FROM website_settings WHERE setting_key = 'recipient_emails'")['setting_value'] ?? '';
            if ($recipientsStr) {
                $recipients = array_filter(array_map('trim', explode(',', $recipientsStr)));
                $subject = "New " . strtoupper($type) . " Lead: " . ($name ?: $phone);
                $bodyText = "You have received a new inquiry on Hazra EV Website:\n\n" .
                            "Type: {$type}\n" .
                            "Name: {$name}\n" .
                            "Phone: {$phone}\n" .
                            "Email: {$email}\n" .
                            "Details:\n" . json_encode($body, JSON_PRETTY_PRINT) . "\n\n" .
                            "Submitted at: {$now}";
                foreach ($recipients as $recipient) {
                    if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                        Mailer::send($recipient, $subject, $bodyText);
                    }
                }
            }
        } catch (Throwable $e) {
            // Log or ignore email failures so lead submission succeeds
            error_log("Lead mailer error: " . $e->getMessage());
        }

        Envelope::created([
            'id' => $id,
            'message' => 'Thank you! Your inquiry has been received successfully.'
        ]);
    }

    /** GET /api/v1/website/leads (Admin only) */
    public function leads(): never
    {
        $this->requireAdmin();

        $where = ['1=1'];
        $params = [];

        if ($type = Wire::enumIn((string) $this->query('type', ''), ['contact', 'test_ride', 'dealer'])) {
            $where[] = 'type = ?';
            $params[] = $type;
        }

        if ($status = Wire::enumIn((string) $this->query('status', ''), ['new', 'read', 'archived', 'contacted'])) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $clause = implode(' AND ', $where);

        $rows = db_fetch_all(
            "SELECT * FROM website_leads WHERE {$clause} ORDER BY created_at DESC",
            $params
        );

        $leads = array_map(function ($r) {
            $r['details'] = json_decode($r['details_json'], true) ?? [];
            return $r;
        }, $rows);

        Envelope::ok($leads);
    }

    /** PATCH /api/v1/website/leads/{id} (Admin only) */
    public function updateLeadStatus(): never
    {
        $this->requireAdmin();

        $id = (string) $this->param('id');
        $body = ApiRequest::body();

        $status = Wire::enumIn((string) ($body['status'] ?? ''), ['new', 'read', 'archived', 'contacted']);

        if (!$status) {
            Envelope::invalid('Valid status (new, read, archived, contacted) is required');
        }

        $now = Wire::now();

        $updated = db_execute(
            "UPDATE website_leads SET status = ?, updated_at = ? WHERE id = ?",
            [$status, $now, $id]
        );

        if (!$updated) {
            Envelope::notFound('Lead not found');
        }

        Envelope::ok(['id' => $id, 'status' => $status, 'updated_at' => $now]);
    }
}
