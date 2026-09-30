<?php

/**
 * Refresh existing Dynamo heroes and colour primary images from official assets.
 * Automatically discovered by the seed runner. Missing models are skipped;
 * specifications, other brands and gallery images after position zero survive.
 */
return static function (PDO $pdo): void {
    require_once __DIR__ . '/../../api/support/Uuid.php';
    require_once __DIR__ . '/../../api/support/Wire.php';

    $root = dirname(__DIR__, 2);
    $sources = json_decode(file_get_contents($root . '/assets/scooters/dynamo/sources.json'), true, 512, JSON_THROW_ON_ERROR);

    // Validate before any write so an incomplete deployment cannot be recorded
    // as a successfully applied seed by the runner's ledger.
    foreach ($sources as $source) {
        $asset = $root . '/assets/scooters/dynamo/' . $source['file'];
        $info = is_file($asset) ? @getimagesize($asset) : false;
        if (!$info || $info[2] !== IMAGETYPE_PNG) {
            throw new RuntimeException('Missing or invalid Dynamo PNG: ' . $source['file']);
        }
    }

    $find = $pdo->prepare('SELECT id, hero_image FROM products WHERE model_code = ? AND LOWER(brand) = ?');
    $update = $pdo->prepare('UPDATE products SET hero_image = ?, updated_at = ? WHERE id = ?');
    $colors = $pdo->prepare('SELECT id FROM product_colors WHERE product_id = ?');
    $primary = $pdo->prepare('SELECT id, url FROM product_color_images WHERE color_id = ? AND position = 0');
    $replace = $pdo->prepare('UPDATE product_color_images SET url = ? WHERE id = ?');
    $insert = $pdo->prepare('INSERT INTO product_color_images (id, color_id, url, position) VALUES (?, ?, ?, 0)');
    $updated = $images = $skipped = 0;
    $now = Wire::now();
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();

    try {
        foreach ($sources as $source) {
            $path = 'assets/scooters/dynamo/' . $source['file'];
            foreach ($source['codes'] as $code) {
                $find->execute([$code, 'dynamo']);
                $products = $find->fetchAll(PDO::FETCH_ASSOC);
                if (!$products) {
                    echo "  skip (no Dynamo product): {$code}\n";
                    $skipped++;
                }
                foreach ($products as $product) {
                    $changed = $product['hero_image'] !== $path;
                    $colors->execute([$product['id']]);
                    foreach ($colors->fetchAll(PDO::FETCH_ASSOC) as $color) {
                        $primary->execute([$color['id']]);
                        $existing = $primary->fetchAll(PDO::FETCH_ASSOC);
                        if (!$existing) {
                            $insert->execute([Uuid::v4(), $color['id'], $path]);
                            $images++;
                            $changed = true;
                        }
                        foreach ($existing as $image) {
                            if ($image['url'] !== $path) {
                                $replace->execute([$path, $image['id']]);
                                $images++;
                                $changed = true;
                            }
                        }
                    }
                    if ($changed) {
                        $update->execute([$path, $now, $product['id']]);
                        $updated++;
                    }
                }
            }
        }
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $error) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    echo "  Dynamo images: {$updated} products updated, {$images} primary images changed, {$skipped} models skipped\n";
};
