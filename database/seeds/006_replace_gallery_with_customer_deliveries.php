<?php

/**
 * One-time gallery replacement, applied locally and on deployment by db:sync.
 * Validate every source before clearing both gallery tables. The seeder ledger
 * prevents subsequent deployments from overwriting later admin edits.
 */
return static function (PDO $pdo): void {
    require_once __DIR__ . '/../../api/support/Uuid.php';
    require_once __DIR__ . '/../../api/support/Wire.php';

    $root = dirname(__DIR__, 2);
    $folder = $root . '/assets/upload_gallery';
    $titles = [
        'gallery_1.jpg' => 'A New Journey with a Red Dynamo Scooter',
        'gallery_2.jpg' => 'Blue Dynamo Scooter Delivery Day',
        'gallery_3.jpg' => 'Celebrating a New Red Scooter',
        'gallery_4.jpg' => 'White Dynamo Scooter, Happy New Owner',
        'gallery_5.jpg' => 'A Family Moment with a Pink Dynamo Scooter',
        'gallery_6.jpg' => 'Ready to Ride a Blue Dynamo Scooter',
    ];
    foreach (array_keys($titles) as $filename) {
        if (!is_file($folder . '/' . $filename)) {
            throw new RuntimeException('Missing required gallery image: ' . $filename);
        }
    }

    $files = array_values(array_filter(glob($folder . '/*') ?: [], static fn ($file) =>
        is_file($file) && preg_match('/\.(jpe?g|png|webp|gif)$/i', $file)
    ));
    natsort($files);
    $files = array_values($files);
    if (!$files) throw new RuntimeException('No gallery images found in assets/upload_gallery.');
    foreach ($files as $file) {
        if (!@getimagesize($file)) {
            throw new RuntimeException('Invalid gallery image: ' . basename($file));
        }
    }

    $tables = ['gallery_items', 'admin_gallery_items'];
    $sizes = ['lg', 'sm', 'sm', 'wide', 'wide', 'sm'];
    $now = Wire::now();
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $inserts = [];
        foreach ($tables as $table) {
            $inserts[$table] = $pdo->prepare("INSERT INTO {$table}
                (id, title, image_url, image_path, alt, caption, category, album, size, order_num, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        }
        foreach ($tables as $table) $pdo->exec("DELETE FROM {$table}");
        foreach ($files as $index => $file) {
            $filename = basename($file);
            $title = $titles[$filename] ?? ucwords(str_replace(['_', '-'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
            $path = 'assets/upload_gallery/' . $filename;
            $values = [Uuid::v4(), $title, $path, $path, $title,
                'Celebrating a Dynamo electric scooter delivery at Hazra Electrical Bike.',
                'Happy Customers', 'Happy Customers', $sizes[$index] ?? 'sm', $index + 1,
                'published', $now, $now];
            foreach ($inserts as $insert) $insert->execute($values);
        }
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $error) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    echo '  Gallery replaced with ' . count($files) . " customer delivery images in both tables\n";
};
