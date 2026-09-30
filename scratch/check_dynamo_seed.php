<?php
// Standalone verification against an in-memory database, never the configured DB.
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE products (id TEXT PRIMARY KEY, model_code TEXT, brand TEXT, hero_image TEXT, updated_at TEXT, name TEXT)');
$pdo->exec('CREATE TABLE product_colors (id TEXT PRIMARY KEY, product_id TEXT)');
$pdo->exec('CREATE TABLE product_color_images (id TEXT PRIMARY KEY, color_id TEXT, url TEXT, position INTEGER)');
$pdo->exec("INSERT INTO products VALUES ('a', 'DYN-X1', 'Dynamo', 'old.png', 'original', 'Keep specs'), ('b', 'DYN-X1', 'Dynamo', 'old.png', 'original', 'Other org'), ('c', 'DYN-X1', 'Other', 'keep.png', 'original', 'Other brand'), ('s', 'DYN-SMILEY', 'Dynamo', 'smiley.png', 'original', 'Smiley')");
$pdo->exec("INSERT INTO product_colors VALUES ('red', 'a'), ('blue', 'a'), ('black', 'b'), ('other', 'c')");
$pdo->exec("INSERT INTO product_color_images VALUES ('primary', 'red', 'old.png', 0), ('gallery', 'red', 'gallery.png', 1), ('other', 'other', 'keep.png', 0)");
$seed = require __DIR__ . '/../database/seeds/005_dynamo_product_images_seed.php';
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
ob_start();
$seed($pdo);
ob_end_clean();
check($pdo->query("SELECT COUNT(*) FROM products WHERE hero_image = 'assets/scooters/dynamo/x1.png'")->fetchColumn() == 2, 'Both matching products must update');
check($pdo->query("SELECT COUNT(*) FROM product_color_images WHERE position = 0 AND url = 'assets/scooters/dynamo/x1.png'")->fetchColumn() == 3, 'Existing and missing primary images');
check($pdo->query("SELECT url FROM product_color_images WHERE id = 'gallery'")->fetchColumn() === 'gallery.png', 'Gallery must survive');
check($pdo->query("SELECT hero_image FROM products WHERE id = 'c'")->fetchColumn() === 'keep.png', 'Other brand must survive');
check($pdo->query("SELECT hero_image FROM products WHERE id = 's'")->fetchColumn() === 'smiley.png', 'Smiley must survive');
$before = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$images = $pdo->query('SELECT * FROM product_color_images ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
ob_start();
$seed($pdo);
$output = ob_get_clean();
check(str_contains($output, '0 products updated, 0 primary images changed'), 'Second run must do no writes');
check($before === $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'Products must remain unchanged on rerun');
check($images === $pdo->query('SELECT * FROM product_color_images ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'Images must remain unchanged on rerun');
$manifest = json_decode(file_get_contents(__DIR__ . '/../assets/scooters/dynamo/sources.json'), true, 512, JSON_THROW_ON_ERROR);
check(count($manifest) === 19, 'Expected 19 assets');
foreach ($manifest as $source) {
    $info = getimagesize(__DIR__ . '/../assets/scooters/dynamo/' . $source['file']);
    check($info && $info[0] > 0 && $info[1] > 0 && $info[2] === IMAGETYPE_PNG, 'Invalid PNG');
}
echo "PASS: 19 valid PNGs; insert/update primary images; multiple matching products; preserve galleries, other brands and Smiley; idempotent rerun.\n";
