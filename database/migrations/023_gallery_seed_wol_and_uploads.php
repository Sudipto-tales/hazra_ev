<?php

/**
 * Migration 023: Seed Wall of Love photos and upload images into gallery tables.
 * Idempotent — checks for existing image_url before inserting.
 */
class GallerySeedWolAndUploads extends Migration
{
    public function up()
    {
        $now = gmdate('Y-m-d H:i:s');
        $items = [
            // --- Uploads from assets/uploads/images/ ---
            [
                'title' => 'Showroom highlight',
                'image_url' => 'assets/uploads/images/img_20260913_162656_8c71d3be.png',
                'category' => 'Uploads',
                'size' => 'wide',
                'caption' => 'Gallery upload from media library.',
                'order_num' => 20,
            ],
            [
                'title' => 'Uploaded product shot',
                'image_url' => 'assets/uploads/images/img_20260913_182453_a37b255d.png',
                'category' => 'Uploads',
                'size' => 'sm',
                'caption' => 'Media library upload.',
                'order_num' => 21,
            ],
            [
                'title' => 'Network map asset',
                'image_url' => 'assets/uploads/images/img_20260913_215414_205896ed.png',
                'category' => 'Uploads',
                'size' => 'tall',
                'caption' => 'Uploaded map visual.',
                'order_num' => 22,
            ],
            [
                'title' => 'Dealership visual',
                'image_url' => 'assets/uploads/images/img_20260913_220010_dbffb2a7.png',
                'category' => 'Uploads',
                'size' => 'lg',
                'caption' => 'Uploaded dealership visual.',
                'order_num' => 23,
            ],
            [
                'title' => 'Hero ride upload',
                'image_url' => 'assets/uploads/images/img_20260918_221556_988cbce1.png',
                'category' => 'Uploads',
                'size' => 'wide',
                'caption' => 'Large format upload for gallery.',
                'order_num' => 24,
            ],
            // --- Wall of Love (same photos as our-story) ---
            [
                'title' => 'Rider on Hazra scooter',
                'image_url' => 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=1200&q=80',
                'category' => 'Wall of Love',
                'size' => 'tall',
                'caption' => 'Verified owner — Wall of Love.',
                'order_num' => 30,
            ],
            [
                'title' => 'City commute',
                'image_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=1200&q=80',
                'category' => 'Wall of Love',
                'size' => 'wide',
                'caption' => 'Daily city ride.',
                'order_num' => 31,
            ],
            [
                'title' => 'Evening ride',
                'image_url' => 'https://images.unsplash.com/photo-1493238792000-8113da705763?auto=format&fit=crop&w=1200&q=80',
                'category' => 'Wall of Love',
                'size' => 'lg',
                'caption' => 'Golden hour on the road.',
                'order_num' => 32,
            ],
            [
                'title' => 'Scooter detail',
                'image_url' => 'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=1200&q=80',
                'category' => 'Wall of Love',
                'size' => 'sm',
                'caption' => 'Detail shot from the community wall.',
                'order_num' => 33,
            ],
            [
                'title' => 'Owner portrait',
                'image_url' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=1200&q=80',
                'category' => 'Wall of Love',
                'size' => 'wide',
                'caption' => 'Verified owner portrait.',
                'order_num' => 34,
            ],
        ];

        foreach ($items as $item) {
            $exists = db_fetch_one(
                "SELECT id FROM gallery_items WHERE image_url = ? LIMIT 1",
                [$item['image_url']]
            );
            if ($exists) {
                continue;
            }

            $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

            $vals = [
                $id,
                $item['title'],
                $item['image_url'],
                $item['image_url'],
                $item['title'],
                $item['caption'],
                $item['category'],
                $item['category'],
                $item['size'],
                $item['order_num'],
                'published',
                $now,
                $now,
            ];
            $q = "INSERT INTO %s (id, title, image_url, image_path, alt, caption, category, album, size, order_num, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            try { db_execute(sprintf($q, 'gallery_items'), $vals); } catch (\Throwable) {}
            try { db_execute(sprintf($q, 'admin_gallery_items'), $vals); } catch (\Throwable) {}
        }
    }

    public function down()
    {
    }
}