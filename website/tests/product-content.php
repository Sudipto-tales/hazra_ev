<?php
// Run: php tests/product-content.php. Uses an in-memory database only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../core/ProductContent.php';
require_once __DIR__ . '/../core/ApiController.php';
require_once __DIR__ . '/../config/migration.php';
require_once __DIR__ . '/../database/migrations/024_product_page_content.php';
require_once __DIR__ . '/../api/controllers/ProductsController.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function rejects(callable $action, string $message): void
{
    try { $action(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException($message);
}
function db_query($sql, $params = []) { global $pdo; $statement = $pdo->prepare($sql); $statement->execute($params); return $statement; }
function db_fetch_all($sql, $params = []) { return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function db_fetch_one($sql, $params = []) { return db_query($sql, $params)->fetch(PDO::FETCH_ASSOC); }
function db_execute($sql, $params = []) { return db_query($sql, $params)->rowCount(); }

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys = ON');
Dialect::boot($pdo, 'sqlite');
$pdo->exec("CREATE TABLE products (id TEXT PRIMARY KEY, name TEXT, brand TEXT, highlights TEXT, hero_image TEXT, range_km INTEGER, top_speed_kmph INTEGER, battery_capacity TEXT, motor_power TEXT, charging_time TEXT, load_capacity_kg INTEGER);
    CREATE TABLE product_colors (id TEXT PRIMARY KEY, product_id TEXT REFERENCES products(id), name TEXT, argb INTEGER, in_stock INTEGER, position INTEGER);
    CREATE TABLE product_color_images (id TEXT PRIMARY KEY, color_id TEXT REFERENCES product_colors(id) ON DELETE CASCADE, url TEXT, position INTEGER);
    CREATE TABLE website_leads (id TEXT PRIMARY KEY, type TEXT, details_json TEXT, created_at TEXT);");
db_execute('INSERT INTO products (id, name, brand, highlights, hero_image, range_km) VALUES (?, ?, ?, ?, ?, ?)', ['p1', 'City Scooter', 'Hazra', '["Comfort seat","Bright lighting"]', 'assets/fallback.webp', 100]);
db_execute('INSERT INTO products (id, name, brand, highlights, hero_image, range_km) VALUES (?, ?, ?, ?, ?, ?)', ['p2', 'Single Image', 'Hazra', '[]', 'assets/only.webp', 80]);
db_execute('INSERT INTO product_colors VALUES (?, ?, ?, ?, ?, ?)', ['red', 'p1', 'Red', 16711680, 1, 0]);
db_execute('INSERT INTO product_colors VALUES (?, ?, ?, ?, ?, ?)', ['blue', 'p1', 'Blue', 255, 0, 1]);
db_execute('INSERT INTO product_color_images VALUES (?, ?, ?, ?)', ['i-red', 'red', 'assets/red.webp', 0]);
db_execute('INSERT INTO product_color_images VALUES (?, ?, ?, ?)', ['i-blue', 'blue', 'assets/blue.webp', 0]);
db_execute('INSERT INTO website_leads VALUES (?, ?, ?, ?)', ['lead', 'test_drive', '{"product_id":"p1","color_id":"blue","source":"product_detail"}', '2026-01-01']);
$colorsBefore = db_fetch_all('SELECT * FROM product_colors ORDER BY id');
$imagesBefore = db_fetch_all('SELECT * FROM product_color_images ORDER BY id');
$migration = new ProductPageContent($pdo); $migration->up();
check($colorsBefore === db_fetch_all('SELECT * FROM product_colors ORDER BY id'), 'Migration changed existing colors');
check($imagesBefore === db_fetch_all('SELECT * FROM product_color_images ORDER BY id'), 'Migration changed existing photographs');
$product = db_fetch_one('SELECT * FROM products WHERE id = ?', ['p1']);
$cards = ProductContent::decode($product['feature_cards']);
check($cards[0]['title'] === 'Comfort seat' && $cards[0]['image'] === 'assets/red.webp', 'Highlights/images not backfilled');
check($cards[1]['image'] === 'assets/blue.webp', 'Existing image gallery not reused');
check($product['default_color_id'] === 'red', 'Default color not backfilled');
$single = ProductContent::decode(db_fetch_one('SELECT feature_cards FROM products WHERE id = ?', ['p2'])['feature_cards']);
check($single[0]['image'] === 'assets/only.webp', 'Single-image fallback lost');
check(db_fetch_one('SELECT product_id FROM website_leads WHERE id = ?', ['lead'])['product_id'] === 'p1', 'Historic lead not linked');
db_execute('UPDATE products SET page_content = ?, feature_cards = ? WHERE id = ?', ['{"hero_title":"Custom heading"}', '[]', 'p1']);
$migration->up();
$product = db_fetch_one('SELECT * FROM products WHERE id = ?', ['p1']);
check($product['page_content'] === '{"hero_title":"Custom heading"}' && $product['feature_cards'] === '[]', 'Rerunning migration overwrote custom content');

foreach (['javascript:alert(1)', '//untrusted.example/image.png', "assets/\nimage.png", 'data:image/png;base64,abc'] as $url) check(!ProductContent::safeUrl($url, true), 'Unsafe URL accepted');
foreach (['assets/red.webp', '/assets/red.webp', 'https://example.com/photo.webp', '#features'] as $url) check(ProductContent::safeUrl($url, true), 'Valid URL rejected');
rejects(fn() => ProductContent::validateFeatures([['title' => 'Seat', 'color_id' => 'foreign']], ['red']), 'Unknown color accepted');
rejects(fn() => ProductContent::validateFeatures([['title' => '']], ['red']), 'Empty feature title accepted');
rejects(fn() => ProductContent::validatePage(['cinematic_link' => 'javascript:alert(1)']), 'Unsafe CTA accepted');
check(ProductContent::text(ProductContent::page([]), 'booking_title', ['name' => 'City Scooter', 'brand' => 'Hazra']) === 'Experience City Scooter.', 'Product placeholder did not expand');

$controller = new ProductsController();
$write = new ReflectionMethod($controller, 'writeColors');
$write->invoke($controller, 'p1', [
    ['id' => 'blue', 'name' => 'Blue', 'argb' => 255, 'inStock' => false, 'imageUrls' => ['assets/blue.webp']],
    ['id' => 'red', 'name' => 'Red', 'argb' => 16711680, 'inStock' => true, 'imageUrls' => ['assets/red.webp']],
]);
check(db_fetch_one('SELECT id FROM product_color_images WHERE color_id = ?', ['blue'])['id'] === 'i-blue', 'Saving changed existing image ID');
check(db_fetch_one('SELECT id FROM product_color_images WHERE color_id = ?', ['red'])['id'] === 'i-red', 'Saving changed existing image ID');
check(db_fetch_one('SELECT in_stock FROM product_colors WHERE id = ?', ['blue'])['in_stock'] === 0, 'Out-of-stock state lost');
check(db_fetch_one('SELECT position FROM product_colors WHERE id = ?', ['blue'])['position'] === 0, 'Color order was not saved');
$write->invoke($controller, 'p1', [['id' => 'blue', 'name' => 'Blue', 'argb' => 255, 'imageUrls' => ['assets/blue.webp']]]);
check(!db_fetch_one('SELECT id FROM product_colors WHERE id = ?', ['red']), 'Removed color survived');
check(!db_fetch_one('SELECT id FROM product_color_images WHERE id = ?', ['i-red']), 'Removed color image relation survived');
check(db_fetch_one('SELECT details_json FROM website_leads WHERE id = ?', ['lead'])['details_json'] === '{"product_id":"p1","color_id":"blue","source":"product_detail"}', 'Lead snapshot was changed');
echo "PASS: migration preservation/idempotence, existing-image backfill, URL validation, feature validation, placeholders, stable gallery IDs, color order, stock state, and lead snapshots.\n";
