<?php

/**
 * Migration 018: Add category, album, and size fields to gallery tables,
 * and seed initial gallery data for public masonry display.
 */
class GalleryCategoryAndSize extends Migration
{
    public function up()
    {
        // 1. Add columns to gallery_items & admin_gallery_items
        $this->addColumn('gallery_items', 'category', '{str:64} DEFAULT \'General\'');
        $this->addColumn('gallery_items', 'album', '{str:64} DEFAULT \'General\'');
        $this->addColumn('gallery_items', 'size', '{str:16} DEFAULT \'sm\'');

        $this->addColumn('admin_gallery_items', 'category', '{str:64} DEFAULT \'General\'');
        $this->addColumn('admin_gallery_items', 'album', '{str:64} DEFAULT \'General\'');
        $this->addColumn('admin_gallery_items', 'size', '{str:16} DEFAULT \'sm\'');

        // 2. Seed initial gallery items if table is empty
        $count = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM gallery_items")['c'] ?? 0);
        if ($count === 0) {
            $now = gmdate('Y-m-d H:i:s');
            $seedItems = [
                [
                    'title' => 'Hazra Scooter Lineup',
                    'image_url' => 'assets/scutie_light.png',
                    'category' => 'Products',
                    'size' => 'lg',
                    'caption' => 'The futuristic Hazra electric scooter series engineered for long range and maximum comfort.',
                    'order_num' => 1
                ],
                [
                    'title' => 'Chalo 1000 V2 - Matte Black',
                    'image_url' => 'assets/dark_scutie.png',
                    'category' => 'Products',
                    'size' => 'wide',
                    'caption' => 'Chalo 1000 V2 featuring dual disc brakes, keyless start, and ultra-bright LED lighting.',
                    'order_num' => 2
                ],
                [
                    'title' => 'Hazra Electric Bikes Event',
                    'image_url' => 'assets/storm.png',
                    'category' => 'Events',
                    'size' => 'tall',
                    'caption' => 'Unveiling the new Storm edition electric scooter at our annual rider meet in Ghaziabad.',
                    'order_num' => 3
                ],
                [
                    'title' => 'Factory Assembly Line',
                    'image_url' => 'assets/scutie_light.webp',
                    'category' => 'Factory',
                    'size' => 'sm',
                    'caption' => 'Precision automated assembly line ensuring zero defect manufacturing standards.',
                    'order_num' => 4
                ],
                [
                    'title' => 'On The Road With Hazra',
                    'image_url' => 'assets/dark_scutie.webp',
                    'category' => 'Riders',
                    'size' => 'wide',
                    'caption' => 'Riders cruising through mountain roads with zero noise and maximum torque.',
                    'order_num' => 5
                ],
                [
                    'title' => 'Chalo Smart Pro Display',
                    'image_url' => 'assets/storm.webp',
                    'category' => 'Products',
                    'size' => 'sm',
                    'caption' => 'Aerodynamic body contours designed for optimal efficiency and road stability.',
                    'order_num' => 6
                ],
                [
                    'title' => 'National EV Expo Highlights',
                    'image_url' => 'assets/hazraevLogo.jpg',
                    'category' => 'Events',
                    'size' => 'sm',
                    'caption' => 'Hazra EV pavilion at the India International Electric Vehicle Exposition.',
                    'order_num' => 7
                ],
                [
                    'title' => 'Authorized Dealership Showroom',
                    'image_url' => 'assets/ind-map.png',
                    'category' => 'Showroom',
                    'size' => 'tall',
                    'caption' => 'Our expanding nationwide network of state-of-the-art dealerships and service centers.',
                    'order_num' => 8
                ],
                [
                    'title' => 'Community Ride Out',
                    'image_url' => 'assets/scutie_light.png',
                    'category' => 'Riders',
                    'size' => 'wide',
                    'caption' => 'Over 100 Hazra EV riders joined our Sunday green mobility rally.',
                    'order_num' => 9
                ]
            ];

            foreach ($seedItems as $item) {
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
                    $now
                ];

                $q = "INSERT INTO %s (id, title, image_url, image_path, alt, caption, category, album, size, order_num, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                try { db_execute(sprintf($q, 'gallery_items'), $vals); } catch (\Throwable) {}
                try { db_execute(sprintf($q, 'admin_gallery_items'), $vals); } catch (\Throwable) {}
            }
        }
    }

    public function down()
    {
    }
}
