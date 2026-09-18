<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __BASEDIR__ . '/core/Mailer.php';
require_once __BASEDIR__ . '/core/EmailService.php';

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
            static function (array $p) use ($byProduct) {
                $item = Present::product($p, $byProduct[$p['id']] ?? []);
                $firstColor = $item['colors'][0] ?? null;
                $rawImg = ($firstColor && !empty($firstColor['imageUrls'])) ? $firstColor['imageUrls'][0] : ($p['hero_image'] ?? 'assets/scutie_light.webp');
                $imgUrl = ($rawImg && (str_starts_with($rawImg, 'http') || str_starts_with($rawImg, '/'))) ? $rawImg : base_url($rawImg);

                $item['slug'] = $p['slug'] ?? '';
                $item['heroImage'] = $imgUrl;
                $item['image'] = $imgUrl;
                $item['series'] = $p['brand'] ?? 'Hazra';
                $item['speed_type'] = ($item['topSpeedKmph'] ?? 45) >= 55 ? 'high' : 'city';
                return $item;
            },
            $rows
        );

        Envelope::ok($result);
    }

    /** Public GET /api/v1/website/products/{id} (where {id} can be id, slug, or model_code) */
    public function product(): never
    {
        $idOrSlug = (string) $this->param('id');
        $p = db_fetch_one(
            "SELECT * FROM products WHERE (id = ? OR slug = ? OR model_code = ?) AND active = 1 LIMIT 1",
            [$idOrSlug, $idOrSlug, $idOrSlug]
        );

        if (!$p) {
            Envelope::notFound('PRODUCT_NOT_FOUND', 'Product not found');
        }

        $colors = db_fetch_all(
            "SELECT * FROM product_colors WHERE product_id = ? ORDER BY position, name",
            [$p['id']]
        );

        $images = [];
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

        $colorList = [];
        foreach ($colors as $color) {
            $colorList[] = Present::productColor($color, $images[$color['id']] ?? []);
        }

        $item = Present::product($p, $colorList);
        $firstColor = $item['colors'][0] ?? null;
        $rawImg = ($firstColor && !empty($firstColor['imageUrls'])) ? $firstColor['imageUrls'][0] : ($p['hero_image'] ?? 'assets/scutie_light.webp');
        $imgUrl = ($rawImg && (str_starts_with($rawImg, 'http') || str_starts_with($rawImg, '/'))) ? $rawImg : base_url($rawImg);

        $item['slug'] = $p['slug'] ?? '';
        $item['heroImage'] = $imgUrl;
        $item['image'] = $imgUrl;
        $item['series'] = $p['brand'] ?? 'Hazra';
        $item['speed_type'] = ($item['topSpeedKmph'] ?? 45) >= 55 ? 'high' : 'city';

        Envelope::ok($item);
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

        $rawType = (string) ($body['type'] ?? 'contact');
        $type = match ($rawType) {
            'test_drive', 'test_ride', 'test-drive' => 'test_drive',
            'dealership', 'dealer', 'dealership_enquiry' => 'dealership',
            default => 'contact',
        };

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

        $leadRecord = [
            'id' => $id,
            'type' => $type,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'details' => $body,
            'status' => 'new',
            'created_at' => $now,
        ];

        // Trigger email notification
        EmailService::notifyNewLead($leadRecord);

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

        $typeQuery = (string) $this->query('type', '');
        if ($typeQuery !== '') {
            if (in_array($typeQuery, ['test_drive', 'test_ride', 'test-drive'], true)) {
                $where[] = "type IN ('test_drive', 'test_ride', 'test-drive')";
            } elseif (in_array($typeQuery, ['dealership', 'dealer', 'dealership_enquiry'], true)) {
                $where[] = "type IN ('dealership', 'dealer', 'dealership_enquiry')";
            } else {
                $where[] = 'type = ?';
                $params[] = $typeQuery;
            }
        }

        $statusQuery = (string) $this->query('status', '');
        if ($statusQuery !== '' && $statusQuery !== 'all') {
            $where[] = 'status = ?';
            $params[] = $statusQuery;
        }

        $clause = implode(' AND ', $where);

        $rows = db_fetch_all(
            "SELECT * FROM website_leads WHERE {$clause} ORDER BY created_at DESC",
            $params
        );

        $leads = array_map(function ($r) {
            $r['details'] = json_decode($r['details_json'] ?? '{}', true) ?? [];
            $r['subject'] = $r['details']['subject'] ?? $r['details']['model'] ?? ucwords(str_replace('_', ' ', $r['type'])) . ' Enquiry';
            $r['message'] = $r['details']['message'] ?? $r['details']['city'] ?? $r['phone'] ?? '';
            $r['receivedAt'] = $r['created_at'];
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

        $existing = db_fetch_one("SELECT * FROM website_leads WHERE id = ?", [$id]);
        if (!$existing) {
            Envelope::notFound('Lead not found');
        }

        $status = Wire::enumIn((string) ($body['status'] ?? $existing['status']), ['new', 'read', 'archived', 'contacted', 'approved', 'rejected', 'replied', 'closed', 'spam']) ?? $existing['status'];
        $scheduledAt = isset($body['scheduled_at']) ? (string)$body['scheduled_at'] : ($existing['scheduled_at'] ?? null);
        $adminNote = isset($body['admin_note']) ? (string)$body['admin_note'] : ($existing['admin_note'] ?? null);

        $now = Wire::now();

        try {
            db_execute(
                "UPDATE website_leads SET status = ?, scheduled_at = ?, admin_note = ?, updated_at = ? WHERE id = ?",
                [$status, $scheduledAt, $adminNote, $now, $id]
            );
        } catch (\Throwable $e) {
            // Fallback if migration 017 hasn't run yet
            db_execute(
                "UPDATE website_leads SET status = ?, updated_at = ? WHERE id = ?",
                [$status, $now, $id]
            );
        }

        $updatedLead = array_merge($existing, [
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'admin_note' => $adminNote,
            'updated_at' => $now,
            'details' => json_decode($existing['details_json'] ?? '{}', true) ?? [],
        ]);

        if ($status === 'approved' && $existing['status'] !== 'approved') {
            EmailService::notifyLeadApproved($updatedLead);
        } elseif ($status === 'rejected' && $existing['status'] !== 'rejected') {
            EmailService::notifyLeadRejected($updatedLead);
        }

        Envelope::ok($updatedLead);
    }

    /** DELETE /api/v1/website/leads/{id} (Admin only) */
    public function deleteLead(): never
    {
        $this->requireAdmin();

        $id = (string) $this->param('id');
        $existing = db_fetch_one("SELECT * FROM website_leads WHERE id = ?", [$id]);

        if (!$existing) {
            Envelope::notFound('Lead not found');
        }

        db_execute("DELETE FROM website_leads WHERE id = ?", [$id]);

        Envelope::ok(['deleted' => true, 'id' => $id]);
    }
}
